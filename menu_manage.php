<?php
require 'includes/config.php';
require_admin();
$title = 'Manage Menu';
$errors = [];
$action = $_GET['action'] ?? 'list';
$row = ['menu_id' => 0, 'category_id' => '', 'item_name' => '', 'description' => '', 'price' => '', 'is_available' => 1];
$recipeInput = [];
$inTransaction = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  try {
    if (($_POST['do'] ?? '') === 'delete') {
      q("DELETE FROM menu_items WHERE menu_id=?", 'i', [(int) ($_POST['id'] ?? 0)]);
      flash('success', 'Menu item deleted.');
      redirect('menu_manage.php');
    }
    $row = [
      'menu_id' => (int) ($_POST['menu_id'] ?? 0),
      'category_id' => (int) ($_POST['category_id'] ?? 0),
      'item_name' => trim($_POST['item_name'] ?? ''),
      'description' => trim($_POST['description'] ?? ''),
      'price' => trim($_POST['price'] ?? ''),
      'is_available' => isset($_POST['is_available']) ? 1 : 0
    ];
    $ingredientIds = $_POST['ingredient_id'] ?? [];
    $ingredientQtys = $_POST['ingredient_qty'] ?? [];
    if (!is_array($ingredientIds) || !is_array($ingredientQtys)) {
      $ingredientIds = [];
      $ingredientQtys = [];
    }
    foreach ($ingredientIds as $index => $ingredientId) {
      $ingredientId = (int) $ingredientId;
      $quantity = trim((string) ($ingredientQtys[$index] ?? ''));
      if ($ingredientId <= 0 && $quantity === '')
        continue;
      if ($ingredientId <= 0 || !is_numeric($quantity) || (float) $quantity <= 0 || (float) $quantity > 9999999.999) {
        $errors[] = 'Choose a valid ingredient and enter a recipe quantity greater than 0.';
        continue;
      }
      if (isset($recipeInput[$ingredientId])) {
        $errors[] = 'Each ingredient can only appear once in a recipe.';
        continue;
      }
      $recipeInput[$ingredientId] = $quantity;
    }
    if ($row['item_name'] === '')
      $errors[] = 'Item name is required.';
    if ($row['category_id'] <= 0)
      $errors[] = 'Please choose a category.';
    if (!is_numeric($row['price']) || $row['price'] <= 0 || $row['price'] > 99999)
      $errors[] = 'Price must be a number greater than 0.';
    if (!$recipeInput)
      $errors[] = 'Add at least one ingredient so inventory can be deducted when this item is completed.';
    if (!$errors) {
      $ids = array_keys($recipeInput);
      $placeholders = implode(',', array_fill(0, count($ids), '?'));
      $validIngredients = rows(q("SELECT inventory_id FROM inventory_items WHERE item_type='ingredient' AND inventory_id IN ($placeholders)", str_repeat('i', count($ids)), $ids));
      if (count($validIngredients) !== count($ids))
        $errors[] = 'One or more selected ingredients are no longer available as ingredients.';
    }
    if (!$errors) {
      mysqli_begin_transaction($conn);
      $inTransaction = true;
      $ids = array_keys($recipeInput);
      $placeholders = implode(',', array_fill(0, count($ids), '?'));
      $lockedIngredients = rows(q(
        "SELECT inventory_id FROM inventory_items WHERE item_type='ingredient' AND inventory_id IN ($placeholders) FOR UPDATE",
        str_repeat('i', count($ids)),
        $ids
      ));
      if (count($lockedIngredients) !== count($ids)) {
        throw new RuntimeException('One or more selected ingredients are no longer available as ingredients. Refresh and update the recipe.');
      }
      if ($row['menu_id']) {
        q(
          "UPDATE menu_items SET category_id=?, item_name=?, description=?, price=?, is_available=? WHERE menu_id=?",
          'issdii',
          [$row['category_id'], $row['item_name'], $row['description'], $row['price'], $row['is_available'], $row['menu_id']]
        );
        $menuId = $row['menu_id'];
      } else {
        $st = q(
          "INSERT INTO menu_items (category_id, item_name, description, price, is_available) VALUES (?,?,?,?,?)",
          'issdi',
          [$row['category_id'], $row['item_name'], $row['description'], $row['price'], $row['is_available']]
        );
        $menuId = mysqli_stmt_insert_id($st);
      }
      q("DELETE FROM menu_ingredients WHERE menu_id=?", 'i', [$menuId]);
      foreach ($recipeInput as $ingredientId => $quantity) {
        q("INSERT INTO menu_ingredients (menu_id, inventory_id, quantity) VALUES (?,?,?)", 'iid', [$menuId, $ingredientId, $quantity]);
      }
      mysqli_commit($conn);
      $inTransaction = false;
      flash('success', 'Menu item and ingredient recipe saved.');
      redirect('menu_manage.php');
    }
    $action = $row['menu_id'] ? 'edit' : 'add';
  } catch (mysqli_sql_exception $ex) {
    if ($inTransaction)
      mysqli_rollback($conn);
    $errors[] = db_error_message($ex, 'Cannot delete: this item appears in past orders. Mark it unavailable instead.');
    $action = $row['menu_id'] ? 'edit' : 'add';
  } catch (RuntimeException $ex) {
    if ($inTransaction)
      mysqli_rollback($conn);
    $errors[] = $ex->getMessage();
    $action = $row['menu_id'] ? 'edit' : 'add';
  }
}
if ($action === 'edit' && !$errors) {
  $row = one("SELECT * FROM menu_items WHERE menu_id=?", 'i', [(int) ($_GET['id'] ?? 0)]);
  if (!$row) {
    flash('danger', 'Menu item not found.');
    redirect('menu_manage.php');
  }
  $recipeInput = [];
  foreach (rows(q("SELECT inventory_id, quantity FROM menu_ingredients WHERE menu_id=? ORDER BY inventory_id", 'i', [(int) $row['menu_id']])) as $recipe) {
    $recipeInput[(int) $recipe['inventory_id']] = $recipe['quantity'];
  }
}
$cats = rows(q("SELECT * FROM categories ORDER BY category_name"));
$ingredients = rows(q("SELECT inventory_id, item_name, unit, quantity FROM inventory_items WHERE item_type='ingredient' ORDER BY item_name"));
$search = trim($_GET['search'] ?? '');
$cat = (int) ($_GET['cat'] ?? 0);
$avail = $_GET['avail'] ?? '';
$sql = "SELECT m.*, c.category_name, (SELECT COUNT(*) FROM menu_ingredients mi WHERE mi.menu_id=m.menu_id) AS ingredient_count
        FROM menu_items m JOIN categories c ON c.category_id=m.category_id WHERE (m.item_name LIKE ? OR m.description LIKE ?)";
$t = 'ss';
$p = ["%$search%", "%$search%"];
if ($cat) {
  $sql .= " AND m.category_id=?";
  $t .= 'i';
  $p[] = $cat;
}
if ($avail === '0' || $avail === '1') {
  $sql .= " AND m.is_available=?";
  $t .= 'i';
  $p[] = (int) $avail;
}
$list = rows(q($sql . " ORDER BY c.category_name, m.item_name", $t, $p));
include 'includes/header.php';
?>
<?php if ($action === 'add' || $action === 'edit'): ?>
  <div class="card" style="max-width:760px">
    <div class="card-body p-4">
      <h4 class="mb-1"><?= $action === 'add' ? 'Add Menu Item' : 'Edit Menu Item & Recipe' ?></h4>
      <p class="text-muted mb-3">Set ingredient quantities per serving. Stock will be deducted automatically upon order completion.</p>
      <form method="post"><input type="hidden" name="menu_id" value="<?= e($row['menu_id']) ?>">
        <div class="row g-3">
          <div class="col-md-7">
            <label class="form-label">Item name</label>
            <input class="form-control" name="item_name" value="<?= e($row['item_name']) ?>" required placeholder="e.g. Classic Beef Burger">
          </div>
          <div class="col-md-5">
            <label class="form-label">Category</label>
            <select class="form-select" name="category_id" required>
              <option value="">Select category...</option>
              <?php foreach ($cats as $c): ?>
                <option value="<?= $c['category_id'] ?>" <?= $row['category_id'] == $c['category_id'] ? 'selected' : '' ?>>
                  <?= e($c['category_name']) ?>
                </option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="col-12">
            <label class="form-label">Description</label>
            <input class="form-control" name="description" value="<?= e($row['description']) ?>" placeholder="Short description of the dish">
          </div>
          <div class="col-md-6">
            <label class="form-label">Price (₱)</label>
            <input type="number" min="0.01" step="0.01" class="form-control" name="price" value="<?= e($row['price']) ?>" required placeholder="0.00">
          </div>
          <div class="col-md-6 d-flex align-items-center pt-3">
            <div class="form-check">
              <input type="checkbox" class="form-check-input" name="is_available" id="av" <?= $row['is_available'] ? 'checked' : '' ?>>
              <label for="av" class="form-check-label fw-semibold">Available for customer ordering</label>
            </div>
          </div>
        </div>

        <hr class="my-4">

        <div class="d-flex justify-content-between align-items-center mb-3">
          <div>
            <h5 class="mb-0">Recipe Ingredients</h5>
            <small class="text-muted">Quantity deducted from inventory per order item</small>
          </div>
          <button class="btn btn-sm btn-outline-primary" type="button" id="addIngredient">+ Add Ingredient</button>
        </div>

        <div id="recipeRows">
          <?php foreach ($recipeInput as $ingredientId => $quantity): ?>
            <div class="row g-2 align-items-end recipe-row mb-2">
              <div class="col-md-7">
                <label class="form-label">Ingredient</label>
                <select class="form-select" name="ingredient_id[]" required>
                  <option value="">Choose ingredient...</option>
                  <?php foreach ($ingredients as $ingredient): ?>
                    <option value="<?= (int) $ingredient['inventory_id'] ?>" <?= $ingredientId == $ingredient['inventory_id'] ? 'selected' : '' ?>>
                      <?= e($ingredient['item_name']) ?> (<?= e($ingredient['unit']) ?> · <?= e($ingredient['quantity'] + 0) ?> available)
                    </option>
                  <?php endforeach; ?>
                </select>
              </div>
              <div class="col-md-3">
                <label class="form-label">Qty / serving</label>
                <input type="number" min="0.001" step="0.001" class="form-control" name="ingredient_qty[]" value="<?= e($quantity) ?>" required>
              </div>
              <div class="col-md-2">
                <button type="button" class="btn btn-outline-danger w-100 remove-ingredient">Remove</button>
              </div>
            </div>
          <?php endforeach; ?>
        </div>

        <?php if (!$ingredients): ?>
          <div class="alert alert-warning py-2">Add ingredient-type stock in Inventory before creating a menu recipe.</div>
        <?php endif; ?>

        <div class="d-flex gap-2 pt-3">
          <button class="btn btn-yo">Save Menu Item &amp; Recipe</button>
          <a class="btn btn-outline-secondary" href="menu_manage.php">Cancel</a>
        </div>
      </form>
    </div>
  </div>

  <template id="ingredientTemplate">
    <div class="row g-2 align-items-end recipe-row mb-2">
      <div class="col-md-7">
        <label class="form-label">Ingredient</label>
        <select class="form-select" name="ingredient_id[]" required>
          <option value="">Choose ingredient...</option>
          <?php foreach ($ingredients as $ingredient): ?>
            <option value="<?= (int) $ingredient['inventory_id'] ?>"><?= e($ingredient['item_name']) ?> (<?= e($ingredient['unit']) ?> · <?= e($ingredient['quantity'] + 0) ?> available)</option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col-md-3">
        <label class="form-label">Qty / serving</label>
        <input type="number" min="0.001" step="0.001" class="form-control" name="ingredient_qty[]" required placeholder="0.00">
      </div>
      <div class="col-md-2">
        <button type="button" class="btn btn-outline-danger w-100 remove-ingredient">Remove</button>
      </div>
    </div>
  </template>

  <script>
    document.getElementById('addIngredient').addEventListener('click', () => {
      document.getElementById('recipeRows').append(document.getElementById('ingredientTemplate').content.cloneNode(true));
    });
    document.getElementById('recipeRows').addEventListener('click', (event) => {
      if (event.target.closest('.remove-ingredient')) event.target.closest('.recipe-row').remove();
    });
  </script>

<?php else: ?>
  <div class="page-header">
    <h3>Menu Items</h3>
    <a class="btn btn-yo" href="?action=add">+ Add Item</a>
  </div>
  <form class="filter-bar auto-filter" method="get">
    <div class="row g-2 align-items-center">
      <div class="col-md-4">
        <input type="search" class="form-control" name="search" placeholder="Search menu items..." value="<?= e($search) ?>" aria-label="Search menu">
      </div>
      <div class="col-md-3">
        <select class="form-select" name="cat" aria-label="Category">
          <option value="0">All categories</option>
          <?php foreach ($cats as $c): ?>
            <option value="<?= $c['category_id'] ?>" <?= $cat == $c['category_id'] ? 'selected' : '' ?>>
              <?= e($c['category_name']) ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col-md-3">
        <select class="form-select" name="avail" aria-label="Availability">
          <option value="">Any status</option>
          <option value="1" <?= $avail === '1' ? 'selected' : '' ?>>Available</option>
          <option value="0" <?= $avail === '0' ? 'selected' : '' ?>>Unavailable</option>
        </select>
      </div>
      <div class="col-md-2">
        <a class="reset-link" href="menu_manage.php">↺ Reset Filter</a>
      </div>
    </div>
  </form>
  <div class="card">
    <div class="table-responsive">
      <table class="table table-hover align-middle mb-0">
        <thead>
          <tr>
            <th>Item</th>
            <th>Category</th>
            <th>Price</th>
            <th>Recipe</th>
            <th>Status</th>
            <th width="180">Actions</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($list as $m): ?>
            <tr>
              <td><b><?= e($m['item_name']) ?></b><br><small class="text-muted"><?= e($m['description']) ?></small></td>
              <td><?= e($m['category_name']) ?></td>
              <td class="fw-semibold"><?= peso($m['price']) ?></td>
              <td><span class="badge bg-secondary"><?= (int) $m['ingredient_count'] ?> ingredient(s)</span></td>
              <td>
                <?= $m['is_available'] ? "<span class='badge bg-success'>Available</span>" : "<span class='badge bg-secondary'>Unavailable</span>" ?>
              </td>
              <td>
                <div class="action-btns">
                  <a class="btn btn-sm btn-outline-primary" href="?action=edit&amp;id=<?= $m['menu_id'] ?>">Edit Recipe</a>
                  <form method="post" class="d-inline" onsubmit="return confirm('Delete this menu item?')">
                    <input type="hidden" name="do" value="delete">
                    <input type="hidden" name="id" value="<?= $m['menu_id'] ?>">
                    <button class="btn btn-sm btn-outline-danger">Delete</button>
                  </form>
                </div>
              </td>
            </tr>
          <?php endforeach;
          if (!$list)
            echo "<tr><td colspan='6' class='empty-state'><p>No menu items found.</p></td></tr>"; ?>
        </tbody>
      </table>
    </div>
  </div>
<?php endif;
include 'includes/footer.php'; ?>

THE YO'S - Web-Based Restaurant Information and Online Ordering System with Inventory Management
PHP (mysqli) + MySQL (phpMyAdmin) + Bootstrap 5.3.2

SETUP (XAMPP)
1. Copy the "theyos" folder to C:\xampp\htdocs\
2. Start Apache and MySQL in the XAMPP Control Panel.
3. Open http://localhost/phpmyadmin > Import > choose sql/theyos_db.sql > Go.
4. Open http://localhost/theyos/
   Admin login:  admin / admin123   (the ONE admin account)
   Anyone who registers on register.php becomes a CUSTOMER automatically.
   Admins can create STAFF accounts in Manage > Accounts. Staff can handle orders and inventory;
   account, menu, category, and supplier management remain admin-only.
   Existing database: run these statements once in phpMyAdmin's SQL tab to enable staff accounts
   and add void reasons to orders. The staff-account form will show an error until the first
   statement has been applied:
   ALTER TABLE accounts MODIFY accesslevel ENUM('admin','staff','user') NOT NULL DEFAULT 'user';
   ALTER TABLE orders MODIFY status ENUM('Pending','Preparing','Completed','Cancelled','Voided') NOT NULL DEFAULT 'Pending';
   ALTER TABLE orders ADD void_reason TEXT DEFAULT NULL AFTER notes;
   UPDATE orders SET status='Voided', void_reason=COALESCE(NULLIF(notes,''),'Previously cancelled; reason was not recorded.') WHERE status='Cancelled';
   ALTER TABLE orders MODIFY status ENUM('Pending','Preparing','Completed','Voided') NOT NULL DEFAULT 'Pending';
   ALTER TABLE inventory_items ADD item_type ENUM('ingredient','item') NOT NULL DEFAULT 'ingredient' AFTER item_name;
   ALTER TABLE inventory_items MODIFY quantity DECIMAL(10,3) NOT NULL DEFAULT 0, MODIFY reorder_level DECIMAL(10,3) NOT NULL DEFAULT 10;
   ALTER TABLE inventory_logs MODIFY qty DECIMAL(10,3) NOT NULL;
   CREATE TABLE menu_ingredients (menu_id INT NOT NULL, inventory_id INT NOT NULL, quantity DECIMAL(10,3) NOT NULL, PRIMARY KEY (menu_id, inventory_id), INDEX idx_menu_ingredients_inventory (inventory_id), FOREIGN KEY (menu_id) REFERENCES menu_items(menu_id) ON DELETE CASCADE, FOREIGN KEY (inventory_id) REFERENCES inventory_items(inventory_id) ON DELETE RESTRICT) ENGINE=InnoDB;
   After the schema update, edit each menu item and assign its ingredient quantities. Orders cannot
   be completed until every ordered menu item has a recipe and sufficient ingredient stock.
If your MySQL root has a password, edit includes/config.php.

REQUIREMENT CHECKLIST
1. Login/registration + SESSIONS ........ index.php, register.php, logout.php, includes/config.php
   Roles: admin / staff / user (customer). Public registration always creates a customer; only
   admins can create staff accounts. Roles cannot be edited, the admin cannot be deleted, and a
   UNIQUE generated column in the accounts table blocks a second admin at the database level.
2. Library files (CRUD + search + filter):
   categories.php, menu_manage.php, suppliers.php, inventory.php, users.php, orders_admin.php,
   transactions.php (admin transaction history with date/status filters and order details)
   Menu recipes link inventory ingredients to each menu item. Completing an order deducts its
   recipe ingredients atomically and logs each stock movement; non-ingredient inventory items
   are tracked separately and are not auto-consumed. Inventory, menu, order, account, supplier,
   category, and transaction lists update automatically as filters change.
3. Comprehensive views (not just tables): dashboard.php (stat cards, low stock, best sellers),
   menu.php (card grid), suppliers.php (cards), order_view.php (receipt), inventory_view.php
   (stock gauge + movement history), my_orders.php (order cards), transactions.php (sales ledger)
4. Related tables: accounts, categories, menu_items, suppliers, inventory_items, inventory_logs,
   orders, order_items (foreign keys + JOINs)
5. Error trapping: try/catch on every query (mysqli exceptions), input validation, duplicate and
   foreign-key messages, DB transactions with rollback (checkout, stock movement), role guards
6. Design: Bootstrap 5.3.2 (local copy) + css/style.css

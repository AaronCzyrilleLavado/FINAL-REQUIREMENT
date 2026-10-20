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
   and add void reasons to orders:
   ALTER TABLE accounts MODIFY accesslevel ENUM('admin','staff','user') NOT NULL DEFAULT 'user';
   ALTER TABLE orders MODIFY status ENUM('Pending','Preparing','Completed','Cancelled','Voided') NOT NULL DEFAULT 'Pending';
   ALTER TABLE orders ADD void_reason TEXT DEFAULT NULL AFTER notes;
   UPDATE orders SET status='Voided', void_reason=COALESCE(NULLIF(notes,''),'Previously cancelled; reason was not recorded.') WHERE status='Cancelled';
   ALTER TABLE orders MODIFY status ENUM('Pending','Preparing','Completed','Voided') NOT NULL DEFAULT 'Pending';
If your MySQL root has a password, edit includes/config.php.

REQUIREMENT CHECKLIST
1. Login/registration + SESSIONS ........ index.php, register.php, logout.php, includes/config.php
   Roles: admin / staff / user (customer). Public registration always creates a customer; only
   admins can create staff accounts. Roles cannot be edited, the admin cannot be deleted, and a
   UNIQUE generated column in the accounts table blocks a second admin at the database level.
2. Library files (CRUD + search + filter):
   categories.php, menu_manage.php, suppliers.php, inventory.php, users.php, orders_admin.php,
   transactions.php (admin transaction history with date/status filters and order details)
3. Comprehensive views (not just tables): dashboard.php (stat cards, low stock, best sellers),
   menu.php (card grid), suppliers.php (cards), order_view.php (receipt), inventory_view.php
   (stock gauge + movement history), my_orders.php (order cards), transactions.php (sales ledger)
4. Related tables: accounts, categories, menu_items, suppliers, inventory_items, inventory_logs,
   orders, order_items (foreign keys + JOINs)
5. Error trapping: try/catch on every query (mysqli exceptions), input validation, duplicate and
   foreign-key messages, DB transactions with rollback (checkout, stock movement), role guards
6. Design: Bootstrap 5.3.2 (local copy) + css/style.css

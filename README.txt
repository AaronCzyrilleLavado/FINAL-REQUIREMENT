THE YO'S - Web-Based Restaurant Information and Online Ordering System with Inventory Management
PHP (mysqli) + MySQL (phpMyAdmin) + Bootstrap 5.3.2

SETUP (XAMPP)
1. Copy the "theyos" folder to C:\xampp\htdocs\
2. Start Apache and MySQL in the XAMPP Control Panel.
3. Open http://localhost/phpmyadmin > Import > choose sql/theyos_db.sql > Go.
4. Open http://localhost/theyos/
   Admin login:  admin / admin123   (the ONE admin account)
   Anyone who registers on register.php becomes a USER automatically.
If your MySQL root has a password, edit includes/config.php.

REQUIREMENT CHECKLIST
1. Login/registration + SESSIONS ........ index.php, register.php, logout.php, includes/config.php
   Roles: admin / user. Registration always saves 'user'. Only one admin: it is seeded in the SQL,
   registration never sets a role, roles cannot be edited, the admin cannot be deleted, and a
   UNIQUE generated column in the accounts table blocks a second admin at the database level.
2. Library files (CRUD + search + filter):
   categories.php, menu_manage.php, suppliers.php, inventory.php, users.php, orders_admin.php
3. Comprehensive views (not just tables): dashboard.php (stat cards, low stock, best sellers),
   menu.php (card grid), suppliers.php (cards), order_view.php (receipt), inventory_view.php
   (stock gauge + movement history), my_orders.php (order cards)
4. Related tables: accounts, categories, menu_items, suppliers, inventory_items, inventory_logs,
   orders, order_items (foreign keys + JOINs)
5. Error trapping: try/catch on every query (mysqli exceptions), input validation, duplicate and
   foreign-key messages, DB transactions with rollback (checkout, stock movement), role guards
6. Design: Bootstrap 5.3.2 (local copy) + css/style.css

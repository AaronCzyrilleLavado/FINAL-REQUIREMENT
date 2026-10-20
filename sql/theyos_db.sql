-- The Yo's: Web-Based Restaurant Information and Online Ordering System with Inventory Management
-- Import in phpMyAdmin: Import tab > choose this file > Go
CREATE DATABASE IF NOT EXISTS theyos_db CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;
USE theyos_db;

DROP TABLE IF EXISTS inventory_logs, order_items, orders, inventory_items, suppliers, menu_items, categories, accounts;

CREATE TABLE accounts (
  account_id INT AUTO_INCREMENT PRIMARY KEY,
  fullname VARCHAR(100) NOT NULL,
  username VARCHAR(50) NOT NULL UNIQUE,
  email VARCHAR(100) NOT NULL UNIQUE,
  phone VARCHAR(20) DEFAULT NULL,
  address VARCHAR(255) DEFAULT NULL,
  password VARCHAR(255) NOT NULL,
  accesslevel ENUM('admin','staff','user') NOT NULL DEFAULT 'user',
  -- generated column + UNIQUE = the database itself allows only ONE admin
  admin_flag TINYINT GENERATED ALWAYS AS (CASE WHEN accesslevel='admin' THEN 1 ELSE NULL END) STORED UNIQUE,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE categories (
  category_id INT AUTO_INCREMENT PRIMARY KEY,
  category_name VARCHAR(60) NOT NULL UNIQUE,
  description VARCHAR(255) DEFAULT NULL
) ENGINE=InnoDB;

CREATE TABLE menu_items (
  menu_id INT AUTO_INCREMENT PRIMARY KEY,
  category_id INT NOT NULL,
  item_name VARCHAR(100) NOT NULL,
  description VARCHAR(255) DEFAULT NULL,
  price DECIMAL(10,2) NOT NULL,
  is_available TINYINT(1) NOT NULL DEFAULT 1,
  FOREIGN KEY (category_id) REFERENCES categories(category_id) ON DELETE RESTRICT
) ENGINE=InnoDB;

CREATE TABLE suppliers (
  supplier_id INT AUTO_INCREMENT PRIMARY KEY,
  supplier_name VARCHAR(100) NOT NULL,
  contact_person VARCHAR(100) DEFAULT NULL,
  phone VARCHAR(20) DEFAULT NULL
) ENGINE=InnoDB;

CREATE TABLE inventory_items (
  inventory_id INT AUTO_INCREMENT PRIMARY KEY,
  supplier_id INT NOT NULL,
  item_name VARCHAR(100) NOT NULL,
  unit VARCHAR(20) NOT NULL,
  quantity DECIMAL(10,2) NOT NULL DEFAULT 0,
  reorder_level DECIMAL(10,2) NOT NULL DEFAULT 10,
  FOREIGN KEY (supplier_id) REFERENCES suppliers(supplier_id) ON DELETE RESTRICT
) ENGINE=InnoDB;

CREATE TABLE inventory_logs (
  log_id INT AUTO_INCREMENT PRIMARY KEY,
  inventory_id INT NOT NULL,
  account_id INT NOT NULL,
  movement ENUM('IN','OUT') NOT NULL,
  qty DECIMAL(10,2) NOT NULL,
  remarks VARCHAR(150) DEFAULT NULL,
  logged_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (inventory_id) REFERENCES inventory_items(inventory_id) ON DELETE CASCADE,
  FOREIGN KEY (account_id) REFERENCES accounts(account_id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE orders (
  order_id INT AUTO_INCREMENT PRIMARY KEY,
  account_id INT NOT NULL,
  order_type ENUM('Dine-in','Take-out','Delivery') NOT NULL DEFAULT 'Dine-in',
  status ENUM('Pending','Preparing','Completed','Voided') NOT NULL DEFAULT 'Pending',
  total DECIMAL(10,2) NOT NULL DEFAULT 0,
  notes VARCHAR(255) DEFAULT NULL,
  void_reason TEXT DEFAULT NULL,
  ordered_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (account_id) REFERENCES accounts(account_id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE order_items (
  order_item_id INT AUTO_INCREMENT PRIMARY KEY,
  order_id INT NOT NULL,
  menu_id INT NOT NULL,
  qty INT NOT NULL,
  unit_price DECIMAL(10,2) NOT NULL,
  FOREIGN KEY (order_id) REFERENCES orders(order_id) ON DELETE CASCADE,
  FOREIGN KEY (menu_id) REFERENCES menu_items(menu_id) ON DELETE RESTRICT
) ENGINE=InnoDB;

-- The ONE admin account. Username: admin   Password: admin123  (change after first login)
INSERT INTO accounts (fullname, username, email, password, accesslevel) VALUES
('System Administrator','admin','admin@theyos.local','$2y$10$T1ZmlonOFktvXfirJupY8e/IUajSkuNj5qnHSYwgCAyVpfPrZu4Oa','admin');

INSERT INTO categories (category_name, description) VALUES
('Rice Meals','Served with steamed rice'),('Noodles','Pancit and pasta'),('Drinks','Cold and hot beverages'),('Desserts','Sweet treats');

INSERT INTO menu_items (category_id,item_name,description,price) VALUES
(1,'Chicken Adobo','Soy-vinegar braised chicken',120.00),
(1,'Pork Sisig','Sizzling chopped pork',150.00),
(2,'Pancit Canton','Stir-fried noodles with vegetables',110.00),
(3,'Iced Tea','House blend, glass',45.00),
(3,'Calamansi Juice','Fresh calamansi',55.00),
(4,'Halo-Halo','Mixed shaved ice dessert',95.00);

INSERT INTO suppliers (supplier_name, contact_person, phone) VALUES
('Tuguegarao Public Market Meats','Mang Ben','09171234567'),
('Cagayan Fresh Produce','Aling Rosa','09181234567');

INSERT INTO inventory_items (supplier_id,item_name,unit,quantity,reorder_level) VALUES
(1,'Chicken','kg',25,10),(1,'Pork','kg',8,10),(2,'Onion','kg',15,5),(2,'Calamansi','kg',3,5),(2,'Rice','sack',4,2);

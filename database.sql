CREATE DATABASE IF NOT EXISTS restaurant_system CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE restaurant_system;

CREATE TABLE IF NOT EXISTS menu_items (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(120) NOT NULL,
    price DECIMAL(10,2) NOT NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS orders (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    order_no VARCHAR(30) NOT NULL UNIQUE,
    customer_name VARCHAR(120) NOT NULL,
    table_no VARCHAR(20) NOT NULL,
    notes TEXT NULL,
    total DECIMAL(10,2) NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS order_items (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    order_id INT UNSIGNED NOT NULL,
    menu_item_id INT UNSIGNED NULL,
    item_name VARCHAR(120) NOT NULL,
    price DECIMAL(10,2) NOT NULL,
    qty INT UNSIGNED NOT NULL,
    line_total DECIMAL(10,2) NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_order_items_order
        FOREIGN KEY (order_id) REFERENCES orders(id)
        ON DELETE CASCADE,
    CONSTRAINT fk_order_items_menu
        FOREIGN KEY (menu_item_id) REFERENCES menu_items(id)
        ON DELETE SET NULL
);

INSERT INTO menu_items (id, name, price, is_active)
VALUES
    (1, 'Margherita Pizza', 220.00, 1),
    (2, 'Chicken Burger', 160.00, 1),
    (3, 'Pasta Alfredo', 190.00, 1),
    (4, 'Caesar Salad', 140.00, 1),
    (5, 'Iced Tea', 60.00, 1),
    (6, 'Chocolate Cake', 110.00, 1)
ON DUPLICATE KEY UPDATE
    name = VALUES(name),
    price = VALUES(price),
    is_active = VALUES(is_active);

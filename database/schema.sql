-- Expense Tracker: database, table and sample data.
-- Run as a MySQL admin user:  mysql -u root -p < database/schema.sql

CREATE DATABASE IF NOT EXISTS expense_tracker
    CHARACTER SET utf8mb4
    COLLATE utf8mb4_unicode_ci;

USE expense_tracker;

CREATE TABLE IF NOT EXISTS expenses (
    id           INT UNSIGNED  NOT NULL AUTO_INCREMENT,
    description  VARCHAR(255)  NOT NULL,
    amount       DECIMAL(10,2) NOT NULL,
    category     ENUM('Food', 'Transportation', 'Shopping', 'Bills', 'Other') NOT NULL DEFAULT 'Other',
    expense_date DATE          NOT NULL,
    PRIMARY KEY (id),
    INDEX idx_expense_date (expense_date),
    CONSTRAINT chk_amount_positive CHECK (amount > 0)
) ENGINE=InnoDB;

-- Sample data (dates relative to today so the "current month" card shows data).
INSERT INTO expenses (description, amount, category, expense_date) VALUES
    ('Groceries at supermarket', 54.30, 'Food',           CURDATE()),
    ('Monthly bus pass',         45.00, 'Transportation', DATE_FORMAT(CURDATE(), '%Y-%m-01')),
    ('Electricity bill',         82.15, 'Bills',          DATE_FORMAT(CURDATE(), '%Y-%m-01')),
    ('Lunch with colleagues',    18.75, 'Food',           CURDATE() - INTERVAL 3 DAY),
    ('New running shoes',        89.99, 'Shopping',       CURDATE() - INTERVAL 1 MONTH),
    ('Internet subscription',    39.99, 'Bills',          CURDATE() - INTERVAL 1 MONTH),
    ('Birthday gift',            25.00, 'Other',          CURDATE() - INTERVAL 2 MONTH);

-- Application user with only the privileges it needs.
-- CHANGE THE PASSWORD and put the same value in config/.env (DB_PASSWORD).
CREATE USER IF NOT EXISTS 'expense_user'@'localhost' IDENTIFIED BY 'your_password';
GRANT SELECT, INSERT, UPDATE, DELETE ON expense_tracker.* TO 'expense_user'@'localhost';
FLUSH PRIVILEGES;

-- Test databases: ecom_testing plus ecom_testing_N created by `pest --parallel`.
CREATE DATABASE IF NOT EXISTS ecom_testing CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
GRANT ALL PRIVILEGES ON `ecom\_testing%`.* TO 'ecom'@'%';
FLUSH PRIVILEGES;

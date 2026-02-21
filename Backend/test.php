<?php
require_once "config.php";

header("Content-Type: text/html");

echo "<h2>Resetting Bill Payment Tables</h2>";

// Drop existing tables
echo "<p>Dropping old tables...</p>";
$conn->query("DROP TABLE IF EXISTS bill_payments");
$conn->query("DROP TABLE IF EXISTS billers");
$conn->query("DROP TABLE IF EXISTS bill_categories");
echo "<p style='color:green'>✅ Old tables dropped</p>";

// Create bill categories table
echo "<p>Creating bill_categories table...</p>";
$conn->query("
CREATE TABLE bill_categories (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(50) NOT NULL,
    icon VARCHAR(10) DEFAULT '📄'
)");
echo "<p style='color:green'>✅ Bill categories table created</p>";

// Create billers table (with email instead of account_number)
echo "<p>Creating billers table...</p>";
$conn->query("
CREATE TABLE billers (
    id INT AUTO_INCREMENT PRIMARY KEY,
    category_id INT,
    name VARCHAR(100) NOT NULL,
    email VARCHAR(100),
    FOREIGN KEY (category_id) REFERENCES bill_categories(id) ON DELETE CASCADE
)");
echo "<p style='color:green'>✅ Billers table created</p>";

// Create bill payments table
echo "<p>Creating bill_payments table...</p>";
$conn->query("
CREATE TABLE bill_payments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    biller_id INT NOT NULL,
    amount DECIMAL(10,2) NOT NULL,
    account_number VARCHAR(100) NOT NULL,
    reference VARCHAR(50) UNIQUE,
    status VARCHAR(20) DEFAULT 'completed',
    payment_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (biller_id) REFERENCES billers(id) ON DELETE CASCADE
)");
echo "<p style='color:green'>✅ Bill payments table created</p>";

// Insert sample categories
echo "<p>Inserting sample categories...</p>";
$conn->query("INSERT INTO bill_categories (name, icon) VALUES 
              ('Electricity', '⚡'),
              ('Water', '💧'),
              ('Internet', '🌐'),
              ('Mobile', '📱'),
              ('Gas', '🔥')");
echo "<p style='color:green'>✅ Sample categories inserted</p>";

// Insert sample billers with emails
echo "<p>Inserting sample billers...</p>";
$conn->query("INSERT INTO billers (category_id, name, email) VALUES 
              (1, 'National Electric', 'billing@nationalelectric.com'),
              (1, 'City Power', 'payments@citypower.com'),
              (2, 'Water Authority', 'bills@waterauth.com'),
              (3, 'FastNet', 'support@fastnet.com'),
              (4, 'Mobile Telecom', 'billing@mobiletel.com')");
echo "<p style='color:green'>✅ Sample billers inserted</p>";

echo "<h3 style='color:green'>✅ Bill payment tables reset complete!</h3>";
echo "<p><a href='../Frontend/dashboard.html'>Go to Dashboard</a></p>";
?>
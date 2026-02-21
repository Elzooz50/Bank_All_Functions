<?php
// Add CORS headers
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");

// Check if server accept this method
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

$host = "localhost";
$user = "root";
$pass = "";

// Connect to MySQL
$conn = new mysqli($host, $user, $pass);

if ($conn->connect_error) {
    die(json_encode(["error" => "Connection failed: " . $conn->connect_error]));
}

// Create Database if not exists
$conn->query("CREATE DATABASE IF NOT EXISTS bank_app");
$conn->select_db("bank_app");


/* Create Users Table */
$conn->query("
CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    email VARCHAR(100) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    balance DECIMAL(10,2) DEFAULT 2500.00,
    profile_pic VARCHAR(255) DEFAULT NULL,
    reset_pin VARCHAR(3) DEFAULT NULL,
    reset_expiry DATETIME DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)");
/* Create Transactions Table */
$conn->query("
CREATE TABLE IF NOT EXISTS transactions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    sender_id INT,
    receiver_id INT,
    amount DECIMAL(10,2),
    type ENUM('transfer', 'loan_payment', 'bill_payment', 'card_funding') DEFAULT 'transfer',
    description TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (sender_id) REFERENCES users(id) ON DELETE SET NULL,
    FOREIGN KEY (receiver_id) REFERENCES users(id) ON DELETE SET NULL
)");

/* Create Loans Table */
$conn->query("
CREATE TABLE IF NOT EXISTS loans (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    amount DECIMAL(10,2) NOT NULL,
    purpose TEXT,
    status ENUM('pending', 'approved', 'rejected', 'paid') DEFAULT 'pending',
    request_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    approval_date TIMESTAMP NULL,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
)");

/* Create Virtual Cards Table */
$conn->query("
CREATE TABLE IF NOT EXISTS virtual_cards (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    card_number VARCHAR(16) UNIQUE NOT NULL,
    card_holder VARCHAR(100) NOT NULL,
    expiry_month INT NOT NULL,
    expiry_year INT NOT NULL,
    cvv VARCHAR(3) NOT NULL,
    balance DECIMAL(10,2) DEFAULT 0.00,
    status ENUM('active', 'frozen', 'closed') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
)");

/* Create Bill Categories Table */
$conn->query("
CREATE TABLE IF NOT EXISTS bill_categories (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(50) NOT NULL,
    icon VARCHAR(10) DEFAULT '📄'
)");


/* Create Billers Table */
$conn->query("
CREATE TABLE IF NOT EXISTS billers (
    id INT AUTO_INCREMENT PRIMARY KEY,
    category_id INT,
    name VARCHAR(100) NOT NULL,
    email VARCHAR(100),
    FOREIGN KEY (category_id) REFERENCES bill_categories(id) ON DELETE CASCADE
)");

/* Create Bill Payments Table */
$conn->query("
CREATE TABLE IF NOT EXISTS bill_payments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    biller_id INT NOT NULL,
    amount DECIMAL(10,2) NOT NULL,
    account_number VARCHAR(50) NOT NULL,
    reference VARCHAR(50) UNIQUE,
    status ENUM('pending', 'completed', 'failed') DEFAULT 'completed',
    payment_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (biller_id) REFERENCES billers(id) ON DELETE CASCADE
)");

// Insert sample bill categories if empty
$check = $conn->query("SELECT COUNT(*) as count FROM bill_categories");
$count = $check->fetch_assoc()['count'];
if ($count == 0) {
    $conn->query("INSERT INTO bill_categories (name, icon) VALUES 
                    ('Electricity', '⚡'),
                    ('Water', '💧'),
                    ('Internet', '🌐'),
                    ('Mobile', '📱'),
                    ('Gas', '🔥')");
    
  // Insert sample billers with emails instead of account numbers
$conn->query("INSERT INTO billers (category_id, name, email) VALUES 
                (1, 'National Electric', 'billing@nationalelectric.com'),
                (1, 'City Power', 'payments@citypower.com'),
                (2, 'Water Authority', 'bills@waterauth.com'),
                (3, 'FastNet', 'support@fastnet.com'),
                (4, 'Mobile Telecom', 'billing@mobiletel.com')");
}

// Set timezone
date_default_timezone_set('Africa/Cairo');

?>
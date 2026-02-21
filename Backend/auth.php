<?php

error_reporting(E_ALL & ~E_WARNING & ~E_NOTICE);
ini_set('display_errors', 0);

header("Content-Type: application/json");
header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");

// Check if server accept this request method
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

require_once "config.php";

// Check if vendor autoload exists
if (file_exists("vendor/autoload.php")) {
    require_once "vendor/autoload.php";
} else {
    echo json_encode(["error" => "JWT library not installed. Run: composer require firebase/php-jwt"]);
    exit;
}

use Firebase\JWT\JWT;
use Firebase\JWT\Key;

$secret_key = "YourSuperSecretKeyForBankApp2024!@#$%^&*"; // At least 32 characters for security
$algorithm = "HS256";

// Get request data
$data = json_decode(file_get_contents("php://input"));

// Route based on action parameter
$action = isset($_GET['action']) ? $_GET['action'] : '';

switch($action) {
    case 'signup':
        handleSignup($conn, $data);
        break;
    case 'login':
        handleLogin($conn, $data, $secret_key, $algorithm);
        break;
    case 'verify':
        verifyToken($conn, $secret_key, $algorithm);
        break;
    default:
        echo json_encode(["error" => "Invalid action"]);
}

function handleSignup($conn, $data) {
    if (!isset($data->name) || !isset($data->email) || !isset($data->password)) {
        echo json_encode(["error" => "All fields required"]);
        exit;
    }
    
    $name = $conn->real_escape_string(trim($data->name));
    $email = $conn->real_escape_string(trim($data->email));
    $password = $data->password;
    
    // Validate email
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        echo json_encode(["error" => "Invalid email format"]);
        exit;
    }
    
    // Validate password length
    if (strlen($password) < 6) {
        echo json_encode(["error" => "Password must be at least 6 characters"]);
        exit;
    }
    
    // Check if user exists
    $result = $conn->query("SELECT * FROM users WHERE email='$email'");
    if ($result->num_rows > 0) {
        echo json_encode(["error" => "Email already exists"]);
        exit;
    }
    
    // Hash password and insert user with balance 2500
    $hashed_password = password_hash($password, PASSWORD_DEFAULT);
    $sql = "INSERT INTO users (name, email, password, balance) VALUES ('$name', '$email', '$hashed_password', 2500.00)";
    
    if ($conn->query($sql)) {
        echo json_encode([
            "message" => "User created successfully with $2500 welcome bonus!",
            "user" => ["name" => $name, "email" => $email]
        ]);
    } else {
        echo json_encode(["error" => "Registration failed: " . $conn->error]);
    }
}

function handleLogin($conn, $data, $secret_key, $algorithm) {
    if (!isset($data->email) || !isset($data->password)) {
        echo json_encode(["error" => "Email and password required"]);
        exit;
    }
    
    $email = $conn->real_escape_string(trim($data->email));
    $password = $data->password;
    
    // Get user
    $sql = "SELECT * FROM users WHERE email = '$email'";
    $result = $conn->query($sql);
    
    if ($result->num_rows === 0) {
        echo json_encode(["error" => "Invalid email or password"]);
        exit;
    }
    
    $user = $result->fetch_assoc();
    
    // Verify password
    if (!password_verify($password, $user['password'])) {
        echo json_encode(["error" => "Invalid email or password"]);
        exit;
    }
    
    // Generate JWT
    $payload = [
        "user_id" => $user['id'],
        "email" => $user['email'],
        "name" => $user['name'],
        "iat" => time(),
        "exp" => time() + 3600
    ];
    
    $jwt = JWT::encode($payload, $secret_key, $algorithm);
    
    echo json_encode([
        "message" => "Login successful",
        "token" => $jwt,
        "user" => [
            "id" => $user['id'],
            "name" => $user['name'],
            "email" => $user['email'],
            "balance" => floatval($user['balance']),
            "profile_pic" => $user['profile_pic']
        ]
    ]);
}

function verifyToken($conn, $secret_key, $algorithm) {
    $headers = getallheaders();
    $authHeader = isset($headers['Authorization']) ? $headers['Authorization'] : '';
    
    if (!$authHeader) {
        echo json_encode(["error" => "No token provided"]);
        exit;
    }
    
    $token = str_replace("Bearer ", "", $authHeader);
    
    try {
        $decoded = JWT::decode($token, new Key($secret_key, $algorithm));
        echo json_encode([
            "valid" => true,
            "user" => $decoded
        ]);
    } catch (Exception $e) {
        echo json_encode(["error" => "Invalid token"]);
    }
}
?> 






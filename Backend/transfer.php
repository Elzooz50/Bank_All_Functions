<?php
// Check errors
error_reporting(0);
ini_set('display_errors', 0);

header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, GET, PUT, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");

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

$secret_key = "YourSuperSecretKeyForBankApp2024!@#$%^&*";
$algorithm = "HS256";

// Check if server accept this request method >>
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Verify JWT token for all requests except public ones
$public_actions = ['get_categories', 'get_billers'];
$action = isset($_GET['action']) ? $_GET['action'] : '';

if (!in_array($action, $public_actions)) {
    $headers = getallheaders();
    $authHeader = isset($headers['Authorization']) ? $headers['Authorization'] : '';
    
    if (!$authHeader) {
        echo json_encode(["error" => "No token provided"]);
        exit;
    }
    
    $token = str_replace("Bearer ", "", $authHeader);
    
    try {
        $decoded = JWT::decode($token, new Key($secret_key, $algorithm));
        $user_id = $decoded->user_id;
    } catch (Exception $e) {
        echo json_encode(["error" => "Invalid or expired token"]);
        exit;
    }
}

// Get request data
$data = json_decode(file_get_contents("php://input"));

// Route based on action
switch($action) {
    case 'get_profile':
        getUserProfile($conn, $user_id);
        break;
    case 'transfer':
        handleTransfer($conn, $user_id, $data);
        break;
    case 'get_transactions':
        getTransactionHistory($conn, $user_id, $data);
        break;
    case 'request_loan':
        requestLoan($conn, $user_id, $data);
        break;
    case 'get_loans':
        getUserLoans($conn, $user_id);
        break;
    case 'create_card':
        createVirtualCard($conn, $user_id, $data);
        break;
    case 'get_cards':
        getUserCards($conn, $user_id);
        break;
    case 'toggle_card':
        toggleCardStatus($conn, $user_id, $data);
        break;
    case 'fund_card':
        fundCard($conn, $user_id, $data);
        break;
    case 'get_categories':
        getBillCategories($conn);
        break;
    case 'get_billers':
        getBillers($conn);
        break;
    case 'pay_bill':
        payBill($conn, $user_id, $data);
        break;
    case 'get_bill_history':
        getBillHistory($conn, $user_id);
        break;
    case 'request_reset':
        requestPasswordReset($conn, $data);
        break;
    case 'reset_password':
        resetPassword($conn, $data);
        break;
    case 'upload_pic':
        uploadProfilePicture($conn, $user_id);
        break;
    default:
        echo json_encode(["error" => "Invalid action"]);
}

// ============ USER FUNCTIONS ============
function getUserProfile($conn, $user_id) {
    // First check if user exists
    $result = $conn->query("SELECT id, name, email, balance, profile_pic, created_at FROM users WHERE id = $user_id");
    
    if (!$result) {
        echo json_encode(["error" => "Database query failed: " . $conn->error]);
        exit;
    }
    
    if ($result->num_rows === 0) {
        echo json_encode(["error" => "User not found"]);
        exit;
    }
    
    $user = $result->fetch_assoc();
    
    // Ensure balance is a float
    $balance = floatval($user['balance']);
    
    echo json_encode([
        "user" => [
            "id" => $user['id'],
            "name" => $user['name'],
            "email" => $user['email'],
            "balance" => $balance,
            "profile_pic" => $user['profile_pic'],
            "member_since" => $user['created_at']
        ]
    ]);
}

function handleTransfer($conn, $user_id, $data) {
    if (!isset($data->receiver_email) || !isset($data->amount)) {
        echo json_encode(["error" => "Receiver email and amount required"]);
        exit;
    }
    
    $receiver_email = $conn->real_escape_string($data->receiver_email);
    $amount = floatval($data->amount);
    $description = isset($data->description) ? $conn->real_escape_string($data->description) : 'Transfer';
    
    if ($amount <= 0) {
        echo json_encode(["error" => "Amount must be greater than 0"]);
        exit;
    }
    
    // Check if sender exist
    $sender_result = $conn->query("SELECT id, name, balance FROM users WHERE id = $user_id");
    if (!$sender_result || $sender_result->num_rows === 0) {
        echo json_encode(["error" => "Sender not found"]);
        exit;
    }
    $sender = $sender_result->fetch_assoc();
    
    // DEBUG: Log the balance
    error_log("Sender balance: " . $sender['balance'] . " (type: " . gettype($sender['balance']) . ")");
    error_log("Attempting to send: $amount");
    
    // Compare as floats
    if (floatval($sender['balance']) < floatval($amount)) {
        echo json_encode([
            "error" => "Insufficient balance",
            "debug" => [
                "your_balance" => floatval($sender['balance']),
                "attempted_amount" => $amount,
                "balance_raw" => $sender['balance'],
                "balance_type" => gettype($sender['balance'])
            ]
        ]);
        exit;
    }
    
    // Get receiver
    $receiver_result = $conn->query("SELECT id, name FROM users WHERE email = '$receiver_email'");
    if (!$receiver_result || $receiver_result->num_rows === 0) {
        echo json_encode(["error" => "Receiver not found"]);
        exit;
    }
    $receiver = $receiver_result->fetch_assoc();
    
    if ($receiver['id'] == $user_id) {
        echo json_encode(["error" => "Cannot transfer to yourself"]);
        exit;
    }
    
    // Start transaction
    $conn->begin_transaction();
    
    try {
        // Update balances
        if (!$conn->query("UPDATE users SET balance = balance - $amount WHERE id = $user_id")) {
            throw new Exception("Failed to update sender balance: " . $conn->error);
        }
        if (!$conn->query("UPDATE users SET balance = balance + $amount WHERE id = " . $receiver['id'])) {
            throw new Exception("Failed to update receiver balance: " . $conn->error);
        }
        
        // Record transaction
        $insert_query = "INSERT INTO transactions (sender_id, receiver_id, amount, description) 
                            VALUES ($user_id, {$receiver['id']}, $amount, '$description')";
        
        if (!$conn->query($insert_query)) {
            throw new Exception("Failed to record transaction: " . $conn->error);
        }
        
        $conn->commit();
        
        // Get new balance
        $new_balance_result = $conn->query("SELECT balance FROM users WHERE id = $user_id");
        $new_balance = $new_balance_result->fetch_assoc()['balance'];
        
        echo json_encode([
            "message" => "Transfer successful",
            "new_balance" => floatval($new_balance),
            "receiver" => $receiver['name']
        ]);
    } catch (Exception $e) {
        $conn->rollback();
        echo json_encode(["error" => $e->getMessage()]);
    }
}

function getTransactionHistory($conn, $user_id, $data) {
    $limit = isset($data->limit) ? intval($data->limit) : 20;
    $offset = isset($data->offset) ? intval($data->offset) : 0;
    
    $result = $conn->query("
        SELECT t.*, 
        sender.name as sender_name, sender.email as sender_email,
        receiver.name as receiver_name, receiver.email as receiver_email
        FROM transactions t
        LEFT JOIN users sender ON t.sender_id = sender.id
        LEFT JOIN users receiver ON t.receiver_id = receiver.id
        WHERE t.sender_id = $user_id OR t.receiver_id = $user_id
        ORDER BY t.created_at DESC
        LIMIT $limit OFFSET $offset
    ");
    
    if (!$result) {
        echo json_encode(["error" => "Failed to load transactions"]);
        return;
    }
    
    $transactions = [];
    while ($row = $result->fetch_assoc()) {
        $row['amount'] = floatval($row['amount']);
        $row['type'] = ($row['sender_id'] == $user_id) ? 'sent' : 'received';
        $transactions[] = $row;
    }
    
    echo json_encode(["transactions" => $transactions]);
}

function requestLoan($conn, $user_id, $data) {
    if (!isset($data->amount) || !isset($data->purpose)) {
        echo json_encode(["error" => "Amount and purpose required"]);
        exit;
    }
    
    $amount = floatval($data->amount);
    $purpose = $conn->real_escape_string($data->purpose);
    
    if ($amount <= 0 || $amount > 50000) {
        echo json_encode(["error" => "Loan amount must be between 1 and 50,000"]);
        exit;
    }
    
    if ($conn->query("INSERT INTO loans (user_id, amount, purpose) VALUES ($user_id, $amount, '$purpose')")) {
        echo json_encode([
            "message" => "Loan request submitted successfully",
            "loan_id" => $conn->insert_id
        ]);
    } else {
        echo json_encode(["error" => "Failed to submit loan request"]);
    }
}

function getUserLoans($conn, $user_id) {
    $result = $conn->query("SELECT * FROM loans WHERE user_id = $user_id ORDER BY request_date DESC");
    
    if (!$result) {
        echo json_encode(["error" => "Failed to load loans"]);
        return;
    }
    
    $loans = [];
    while ($row = $result->fetch_assoc()) {
        $row['amount'] = floatval($row['amount']);
        $loans[] = $row;
    }
    
    echo json_encode(["loans" => $loans]);
}

function createVirtualCard($conn, $user_id, $data) {
    // Get user name
    $user_result = $conn->query("SELECT name FROM users WHERE id = $user_id");
    if (!$user_result || $user_result->num_rows === 0) {
        echo json_encode(["error" => "User not found"]);
        exit;
    }
    $user = $user_result->fetch_assoc();
    $card_holder = strtoupper($user['name']);
    
    // Generate card number
    $card_number = '';
    for ($i = 0; $i < 16; $i++) {
        $card_number .= rand(0, 9);
    }
    
    // Check uniqueness
    $check = $conn->query("SELECT id FROM virtual_cards WHERE card_number = '$card_number'");
    while ($check && $check->num_rows > 0) {
        $card_number = '';
        for ($i = 0; $i < 16; $i++) {
            $card_number .= rand(0, 9);
        }
        $check = $conn->query("SELECT id FROM virtual_cards WHERE card_number = '$card_number'");
    }
    
    $expiry_month = rand(1, 12);
    $expiry_year = date('Y') + 3;
    $cvv = sprintf("%03d", rand(0, 999));
    $initial_balance = isset($data->initial_deposit) ? floatval($data->initial_deposit) : 0;
    
    $conn->begin_transaction();
    
    try {
        if (!$conn->query("INSERT INTO virtual_cards (user_id, card_number, card_holder, expiry_month, expiry_year, cvv, balance) 
                VALUES ($user_id, '$card_number', '$card_holder', $expiry_month, $expiry_year, '$cvv', $initial_balance)")) {
            throw new Exception("Failed to create card");
        }
        
        if ($initial_balance > 0) {
            if (!$conn->query("UPDATE users SET balance = balance - $initial_balance WHERE id = $user_id")) {
                throw new Exception("Failed to update balance");
            }
        }
        
        $card_id = $conn->insert_id;
        $conn->commit();
        
        echo json_encode([
            "message" => "Virtual card created successfully",
            "card" => [
                "id" => $card_id,
                "card_number" => substr($card_number, 0, 4) . " **** **** " . substr($card_number, -4),
                "card_holder" => $card_holder,
                "expiry" => sprintf("%02d/%d", $expiry_month, $expiry_year),
                "balance" => $initial_balance,
                "status" => "active"
            ]
        ]);
    } catch (Exception $e) {
        $conn->rollback();
        echo json_encode(["error" => $e->getMessage()]);
    }
}

function getUserCards($conn, $user_id) {
    $result = $conn->query("SELECT * FROM virtual_cards WHERE user_id = $user_id ORDER BY created_at DESC");
    
    if (!$result) {
        echo json_encode(["error" => "Failed to load cards"]);
        return;
    }
    
    $cards = [];
    while ($row = $result->fetch_assoc()) {
        $cards[] = [
            "id" => $row['id'],
            "card_number" => substr($row['card_number'], 0, 4) . " **** **** " . substr($row['card_number'], -4),
            "card_holder" => $row['card_holder'],
            "expiry" => sprintf("%02d/%d", $row['expiry_month'], $row['expiry_year']),
            "balance" => floatval($row['balance']),
            "status" => $row['status']
        ];
    }
    
    echo json_encode(["cards" => $cards]);
}

function toggleCardStatus($conn, $user_id, $data) {
    if (!isset($data->card_id)) {
        echo json_encode(["error" => "Card ID required"]);
        exit;
    }
    
    $card_id = intval($data->card_id);
    
    $result = $conn->query("SELECT status FROM virtual_cards WHERE id = $card_id AND user_id = $user_id");
    if (!$result || $result->num_rows === 0) {
        echo json_encode(["error" => "Card not found"]);
        exit;
    }
    
    $card = $result->fetch_assoc();
    $new_status = ($card['status'] == 'active') ? 'frozen' : 'active';
    
    if ($conn->query("UPDATE virtual_cards SET status = '$new_status' WHERE id = $card_id")) {
        echo json_encode([
            "message" => "Card " . ($new_status == 'frozen' ? "frozen" : "unfrozen") . " successfully",
            "status" => $new_status
        ]);
    } else {
        echo json_encode(["error" => "Failed to update card status"]);
    }
}

function fundCard($conn, $user_id, $data) {
    if (!isset($data->card_id) || !isset($data->amount)) {
        echo json_encode(["error" => "Card ID and amount required"]);
        exit;
    }
    
    $card_id = intval($data->card_id);
    $amount = floatval($data->amount);
    
    if ($amount <= 0) {
        echo json_encode(["error" => "Amount must be greater than 0"]);
        exit;
    }
    
    // Check card ownership
    $card_result = $conn->query("SELECT id FROM virtual_cards WHERE id = $card_id AND user_id = $user_id");
    if (!$card_result || $card_result->num_rows === 0) {
        echo json_encode(["error" => "Card not found"]);
        exit;
    }
    
    // Check user balance
    $user_result = $conn->query("SELECT balance FROM users WHERE id = $user_id");
    if (!$user_result || $user_result->num_rows === 0) {
        echo json_encode(["error" => "User not found"]);
        exit;
    }
    $user = $user_result->fetch_assoc();
    
    if ($user['balance'] < $amount) {
        echo json_encode(["error" => "Insufficient balance"]);
        exit;
    }
    
    $conn->begin_transaction();
    
    try {
        if (!$conn->query("UPDATE users SET balance = balance - $amount WHERE id = $user_id")) {
            throw new Exception("Failed to update user balance");
        }
        if (!$conn->query("UPDATE virtual_cards SET balance = balance + $amount WHERE id = $card_id")) {
            throw new Exception("Failed to update card balance");
        }
        if (!$conn->query("INSERT INTO transactions (sender_id, receiver_id, amount, type, description) 
                            VALUES ($user_id, NULL, $amount, 'card_funding', 'Card funding')")) {
            throw new Exception("Failed to record transaction");
        }
        
        $conn->commit();
        
        // Get new balances
        $new_balance_result = $conn->query("SELECT balance FROM users WHERE id = $user_id");
        $new_balance = $new_balance_result->fetch_assoc()['balance'];
        $card_balance_result = $conn->query("SELECT balance FROM virtual_cards WHERE id = $card_id");
        $card_balance = $card_balance_result->fetch_assoc()['balance'];
        
        echo json_encode([
            "message" => "Card funded successfully",
            "new_balance" => floatval($new_balance),
            "card_balance" => floatval($card_balance)
        ]);
    } catch (Exception $e) {
        $conn->rollback();
        echo json_encode(["error" => $e->getMessage()]);
    }
}

function getBillCategories($conn) {
    $result = $conn->query("SELECT * FROM bill_categories");
    
    if (!$result) {
        echo json_encode(["error" => "Failed to load categories"]);
        return;
    }
    
    $categories = [];
    while ($row = $result->fetch_assoc()) {
        $categories[] = $row;
    }
    
    echo json_encode(["categories" => $categories]);
}

function getBillers($conn) {
    $category_id = isset($_GET['category_id']) ? intval($_GET['category_id']) : 0;
    
    if ($category_id <= 0) {
        echo json_encode(["error" => "Category ID required"]);
        exit;
    }
    
    $result = $conn->query("SELECT id, name, email FROM billers WHERE category_id = $category_id");
    
    if (!$result) {
        echo json_encode(["error" => "Failed to load billers"]);
        return;
    }
    
    $billers = [];
    while ($row = $result->fetch_assoc()) {
        $billers[] = $row;
    }
    
    echo json_encode(["billers" => $billers]);
}

function payBill($conn, $user_id, $data) {
    if (!isset($data->biller_id) || !isset($data->amount) || !isset($data->email)) {
        echo json_encode(["error" => "Biller ID, amount, and email required"]);
        exit;
    }
    
    $biller_id = intval($data->biller_id);
    $amount = floatval($data->amount);
    $email = $conn->real_escape_string($data->email);
    
    // Check user balance
    $user_result = $conn->query("SELECT balance FROM users WHERE id = $user_id");
    if (!$user_result || $user_result->num_rows === 0) {
        echo json_encode(["error" => "User not found"]);
        exit;
    }
    $user = $user_result->fetch_assoc();
    
    if ($user['balance'] < $amount) {
        echo json_encode(["error" => "Insufficient balance"]);
        exit;
    }
    
    // Get biller info
    $biller_result = $conn->query("SELECT * FROM billers WHERE id = $biller_id");
    if (!$biller_result || $biller_result->num_rows === 0) {
        echo json_encode(["error" => "Biller not found"]);
        exit;
    }
    $biller = $biller_result->fetch_assoc();
    
    // Generate reference
    $reference = "BILL" . time() . rand(100, 999);
    
    $conn->begin_transaction();
    
    try {
        // Deduct from user balance
        if (!$conn->query("UPDATE users SET balance = balance - $amount WHERE id = $user_id")) {
            throw new Exception("Failed to update balance");
        }
        
        // Record bill payment
        $insert_payment = "INSERT INTO bill_payments (user_id, biller_id, amount, account_number, reference) 
                          VALUES ($user_id, $biller_id, $amount, '$email', '$reference')";
        if (!$conn->query($insert_payment)) {
            throw new Exception("Failed to record payment: " . $conn->error);
        }
        
        // Record transaction
        $insert_transaction = "INSERT INTO transactions (sender_id, receiver_id, amount, type, description) 
                              VALUES ($user_id, NULL, $amount, 'bill_payment', 'Bill payment to " . $biller['name'] . "')";
        if (!$conn->query($insert_transaction)) {
            throw new Exception("Failed to record transaction");
        }
        
        $conn->commit();
        
        // Get new balance
        $new_balance_result = $conn->query("SELECT balance FROM users WHERE id = $user_id");
        $new_balance = $new_balance_result->fetch_assoc()['balance'];
        
        echo json_encode([
            "message" => "Payment successful to " . $biller['name'],
            "reference" => $reference,
            "new_balance" => floatval($new_balance)
        ]);
    } catch (Exception $e) {
        $conn->rollback();
        echo json_encode(["error" => $e->getMessage()]);
    }
}


function getBillHistory($conn, $user_id) {
    $result = $conn->query("
        SELECT bp.*, b.name as biller_name, bc.name as category_name, bc.icon
        FROM bill_payments bp
        JOIN billers b ON bp.biller_id = b.id
        JOIN bill_categories bc ON b.category_id = bc.id
        WHERE bp.user_id = $user_id
        ORDER BY bp.payment_date DESC
        LIMIT 20
    ");
    
    if (!$result) {
        echo json_encode(["error" => "Failed to load bill history"]);
        return;
    }
    
    $payments = [];
    while ($row = $result->fetch_assoc()) {
        $row['amount'] = floatval($row['amount']);
        $payments[] = $row;
    }
    
    echo json_encode(["payments" => $payments]);
}

function requestPasswordReset($conn, $data) {
    if (!isset($data->email)) {
        echo json_encode(["error" => "Email required"]);
        exit;
    }
    
    $email = $conn->real_escape_string($data->email);
    
    $result = $conn->query("SELECT id FROM users WHERE email = '$email'");
    if (!$result || $result->num_rows === 0) {
        echo json_encode(["error" => "Email not found"]);
        exit;
    }
    
    $user = $result->fetch_assoc();
    
    // Generate 3-digit PIN
    $pin = sprintf("%03d", rand(0, 999));
    $expiry = date('Y-m-d H:i:s', strtotime('+15 minutes'));
    
    if ($conn->query("UPDATE users SET reset_pin = '$pin', reset_expiry = '$expiry' WHERE id = " . $user['id'])) {
        echo json_encode([
            "message" => "Reset PIN generated",
            "pin" => $pin,
            "expiry" => $expiry
        ]);
    } else {
        echo json_encode(["error" => "Failed to generate reset PIN"]);
    }
}

function resetPassword($conn, $data) {
    if (!isset($data->email) || !isset($data->pin) || !isset($data->new_password)) {
        echo json_encode(["error" => "Email, PIN, and new password required"]);
        exit;
    }
    
    $email = $conn->real_escape_string($data->email);
    $pin = $conn->real_escape_string($data->pin);
    $new_password = password_hash($data->new_password, PASSWORD_DEFAULT);
    
    $result = $conn->query("SELECT id FROM users WHERE email = '$email' AND reset_pin = '$pin' AND reset_expiry > NOW()");
    
    if (!$result || $result->num_rows === 0) {
        echo json_encode(["error" => "Invalid or expired PIN"]);
        exit;
    }
    
    $user = $result->fetch_assoc();
    
    if ($conn->query("UPDATE users SET password = '$new_password', reset_pin = NULL, reset_expiry = NULL WHERE id = " . $user['id'])) {
        echo json_encode(["message" => "Password reset successful"]);
    } else {
        echo json_encode(["error" => "Failed to reset password"]);
    }
}

function uploadProfilePicture($conn, $user_id) {
    $upload_dir = "../Frontend/uploads/";
    if (!file_exists($upload_dir)) {
        mkdir($upload_dir, 0777, true);
    }
    
    if (!isset($_FILES['profile_pic'])) {
        echo json_encode(["error" => "No file uploaded"]);
        exit;
    }
    
    $file = $_FILES['profile_pic'];
    $file_ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    $allowed = ['jpg', 'jpeg', 'png', 'gif'];
    
    if (!in_array($file_ext, $allowed)) {
        echo json_encode(["error" => "File type not allowed"]);
        exit;
    }
    
    if ($file['size'] > 5 * 1024 * 1024) {
        echo json_encode(["error" => "File too large (max 5MB)"]);
        exit;
    }
    
    $new_filename = "user_" . $user_id . "_" . time() . "." . $file_ext;
    $destination = $upload_dir . $new_filename;
    
    if (move_uploaded_file($file['tmp_name'], $destination)) {
        $profile_path = "uploads/" . $new_filename;
        if ($conn->query("UPDATE users SET profile_pic = '$profile_path' WHERE id = $user_id")) {
            echo json_encode([
                "message" => "Profile picture uploaded",
                "profile_pic" => $profile_path
            ]);
        } else {
            echo json_encode(["error" => "Failed to update database"]);
        }
    } else {
        echo json_encode(["error" => "Upload failed"]);
    }
}
?>
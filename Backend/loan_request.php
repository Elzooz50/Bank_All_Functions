<?php
require_once "config.php";
require_once "auth_middleware.php";

$data = json_decode(file_get_contents("php://input"), true);

$user_id = $decoded->user_id; // from JWT
$amount = floatval($data['amount']);

if ($amount <= 0) {
    echo json_encode(["error" => "Invalid amount"]);
    exit;
}

$interest_rate = 5.0;
$total_amount = $amount + ($amount * $interest_rate / 100);

$stmt = $conn->prepare("INSERT INTO loans 
(user_id, amount, interest_rate, total_amount, remaining_amount, status) 
VALUES (?, ?, ?, ?, ?, 'pending')");

$stmt->bind_param("idddd", 
    $user_id, 
    $amount, 
    $interest_rate, 
    $total_amount, 
    $total_amount
);

$stmt->execute();

echo json_encode(["message" => "Loan request submitted"]);
?>

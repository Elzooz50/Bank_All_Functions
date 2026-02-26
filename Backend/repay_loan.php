<?php
require_once "config.php";

$data = json_decode(file_get_contents("php://input"), true);

$loan_id = intval($data['loan_id']);
$payment = floatval($data['payment']);
$user_id = $decoded->user_id;

$conn->begin_transaction();

$loan = $conn->query("SELECT * FROM loans 
WHERE id=$loan_id AND user_id=$user_id AND status='approved'")
->fetch_assoc();

if (!$loan) {
    echo json_encode(["error" => "Invalid loan"]);
    exit;
}

$user = $conn->query("SELECT balance FROM users WHERE id=$user_id")->fetch_assoc();

if ($user['balance'] < $payment) {
    echo json_encode(["error" => "Insufficient balance"]);
    exit;
}

$new_remaining = $loan['remaining_amount'] - $payment;

$conn->query("UPDATE users SET balance = balance - $payment WHERE id=$user_id");
$conn->query("UPDATE loans SET remaining_amount=$new_remaining WHERE id=$loan_id");

if ($new_remaining <= 0) {
    $conn->query("UPDATE loans SET status='paid' WHERE id=$loan_id");
}

$conn->commit();

echo json_encode(["message" => "Payment successful"]);
?>

<?php
require_once "config.php";

// Verify admin role
if ($decoded->role !== 'admin') {
    echo json_encode(["error" => "Unauthorized"]);
    exit;
}

$data = json_decode(file_get_contents("php://input"), true);

$loan_id = intval($data['loan_id']);
$action = $data['action']; // approve or reject

$conn->begin_transaction();

$loan = $conn->query("SELECT * FROM loans WHERE id = $loan_id")->fetch_assoc();

if (!$loan || $loan['status'] !== 'pending') {
    echo json_encode(["error" => "Invalid loan"]);
    exit;
}

if ($action === 'approve') {

    $conn->query("UPDATE loans SET 
        status='approved',
        approved_at=NOW(),
        due_date=DATE_ADD(NOW(), INTERVAL 30 DAY)
        WHERE id=$loan_id");

    $conn->query("UPDATE users SET 
        balance = balance + {$loan['amount']}
        WHERE id={$loan['user_id']}");

    $conn->commit();

    echo json_encode(["message" => "Loan approved"]);
}
elseif ($action === 'reject') {

    $conn->query("UPDATE loans SET status='rejected' WHERE id=$loan_id");
    $conn->commit();

    echo json_encode(["message" => "Loan rejected"]);
}
?>

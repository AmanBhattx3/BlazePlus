<?php
define('ADMIN_CONTEXT', true);
require_once '../config.php';
header('Content-Type: application/json');

if (($_SESSION['user_type'] ?? '') !== 'admin' || empty($_SESSION['admin_id'])) {
    echo json_encode(['ok' => false, 'error' => 'Not authorized.']);
    exit;
}

$id = intval($_POST['id'] ?? 0);
$action = $_POST['action'] ?? '';
$reason = trim($_POST['reason'] ?? '') ?: 'unknown identity';

if (!$id || !in_array($action, ['accept', 'reject'])) {
    echo json_encode(['ok' => false, 'error' => 'Bad request.']);
    exit;
}

$stmt = $conn->prepare("SELECT * FROM unverified WHERE id = ?");
$stmt->bind_param('i', $id);
$stmt->execute();
$row = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$row || $row['emp_no'] === null) {
    echo json_encode(['ok' => false, 'error' => 'User not found or not yet submitted for review.']);
    exit;
}

$conn->begin_transaction();
try {
    if ($action === 'accept') {
        $stmt = $conn->prepare("INSERT INTO users (name, email, phone, password, emp_no, dob, department, role) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->bind_param('ssssssss', $row['name'], $row['email'], $row['phone'], $row['password'], $row['emp_no'], $row['dob'], $row['department'], $row['role']);
        $stmt->execute();
        $newUserId = $stmt->insert_id;
        $stmt->close();

        $stmt = $conn->prepare("INSERT INTO verify (user_id, name, email, phone, emp_no, dob, department, role) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->bind_param('isssssss', $newUserId, $row['name'], $row['email'], $row['phone'], $row['emp_no'], $row['dob'], $row['department'], $row['role']);
        $stmt->execute();
        $stmt->close();
    } else {
        $stmt = $conn->prepare("INSERT INTO banned (name, email, phone, emp_no, dob, department, role, reason) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->bind_param('ssssssss', $row['name'], $row['email'], $row['phone'], $row['emp_no'], $row['dob'], $row['department'], $row['role'], $reason);
        $stmt->execute();
        $stmt->close();
    }

    $stmt = $conn->prepare("DELETE FROM unverified WHERE id = ?");
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $stmt->close();

    $conn->commit();
    echo json_encode(['ok' => true]);
} catch (Exception $e) {
    $conn->rollback();
    echo json_encode(['ok' => false, 'error' => 'Database error.']);
}   
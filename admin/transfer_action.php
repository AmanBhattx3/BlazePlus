<?php
define('ADMIN_CONTEXT', true);
require_once '../config.php';

error_reporting(E_ALL);
ini_set('display_errors', '0');
ob_start();

header('Content-Type: application/json');

if (($_SESSION['user_type'] ?? '') !== 'admin' || empty($_SESSION['admin_id'])) {
    ob_end_clean();
    echo json_encode(['ok' => false, 'error' => 'Not authorized.']);
    exit;
}

$id = intval($_POST['id'] ?? 0);
$action = $_POST['action'] ?? '';

if (!$id || !in_array($action, ['accept', 'reject'])) {
    ob_end_clean();
    echo json_encode(['ok' => false, 'error' => 'Bad request.']);
    exit;
}

$stmt = $conn->prepare("SELECT * FROM transfer_requests WHERE id = ? AND status = 'pending'");
$stmt->bind_param('i', $id);
$stmt->execute();
$row = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$row) {
    ob_end_clean();
    echo json_encode(['ok' => false, 'error' => 'Request not found or already decided.']);
    exit;
}

$conn->begin_transaction();
try {
    if ($action === 'accept') {
        $stmt = $conn->prepare("UPDATE users SET department = ?, role = ? WHERE id = ?");
        $stmt->bind_param('ssi', $row['requested_department'], $row['requested_role'], $row['user_id']);
        $stmt->execute();
        $stmt->close();

        $stmt = $conn->prepare("UPDATE transfer_requests SET status = 'approved', decided_at = NOW() WHERE id = ?");
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $stmt->close();
    } else {
        $stmt = $conn->prepare("UPDATE transfer_requests SET status = 'rejected', decided_at = NOW() WHERE id = ?");
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $stmt->close();
    }

    $conn->commit();
    ob_end_clean();
    echo json_encode(['ok' => true]);
} catch (\Throwable $e) {
    $conn->rollback();
    ob_end_clean();
    echo json_encode(['ok' => false, 'error' => 'Database error: ' . $e->getMessage()]);
}
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

if (!$id || !in_array($action, ['resolve', 'dismiss', 'delete_message'])) {
    ob_end_clean();
    echo json_encode(['ok' => false, 'error' => 'Bad request.']);
    exit;
}

$stmt = $conn->prepare("SELECT * FROM message_reports WHERE id = ? AND status = 'open'");
$stmt->bind_param('i', $id);
$stmt->execute();
$row = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$row) {
    ob_end_clean();
    echo json_encode(['ok' => false, 'error' => 'Report not found or already decided.']);
    exit;
}

$conn->begin_transaction();
try {
    if ($action === 'delete_message') {
        $table = $row['message_type'] === 'chat' ? 'chat_messages' : 'announcement_messages';
        $stmt = $conn->prepare("DELETE FROM $table WHERE id = ?");
        $stmt->bind_param('i', $row['message_id']);
        $stmt->execute();
        $stmt->close();

        $stmt = $conn->prepare("UPDATE message_reports SET status = 'resolved', decided_at = NOW() WHERE id = ?");
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $stmt->close();
    } else {
        $newStatus = $action === 'resolve' ? 'resolved' : 'dismissed';
        $stmt = $conn->prepare("UPDATE message_reports SET status = ?, decided_at = NOW() WHERE id = ?");
        $stmt->bind_param('si', $newStatus, $id);
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
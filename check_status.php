<?php
require_once 'config.php';
header('Content-Type: application/json');

if (($_SESSION['user_type'] ?? '') !== 'unverified' || empty($_SESSION['unverified_id'])) {
    echo json_encode(['status' => 'error']);
    exit;
}

$id = $_SESSION['unverified_id'];

// Still in unverified table?
$stmt = $conn->prepare("SELECT email, verify_submitted_at FROM unverified WHERE id = ?");
$stmt->bind_param('i', $id);
$stmt->execute();
$row = $stmt->get_result()->fetch_assoc();
$stmt->close();

if ($row) {
    // Check for expiry
    $deadline = strtotime($row['verify_submitted_at']) + (VERIFY_TIMEOUT_MINUTES * 60);
    if (time() > $deadline) {
        $del = $conn->prepare("DELETE FROM unverified WHERE id = ?");
        $del->bind_param('i', $id);
        $del->execute();
        $del->close();
        session_destroy();
        echo json_encode(['status' => 'expired']);
        exit;
    }
    echo json_encode(['status' => 'pending']);
    exit;
}

// Not in unverified anymore — it was either approved (moved to users) or rejected (moved to banned).
// login.php stores the email in $_SESSION['check_email'] for exactly this lookup.
if (!empty($_SESSION['check_email'])) {
    $email = $_SESSION['check_email'];
} else {
    // We lost email since unverified row is gone; this path won't normally hit because
    // login.php sets unverified_id, so also store email for this exact lookup.
    $email = null;
}

if ($email) {
    $stmt = $conn->prepare("SELECT id FROM users WHERE email = ?");
    $stmt->bind_param('s', $email);
    $stmt->execute();
    $u = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if ($u) {
        $_SESSION['user_type'] = 'user';
        $_SESSION['user_id'] = $u['id'];
        unset($_SESSION['unverified_id']);
        echo json_encode(['status' => 'approved']);
        exit;
    }

    $stmt = $conn->prepare("SELECT reason FROM banned WHERE email = ? ORDER BY banned_at DESC LIMIT 1");
    $stmt->bind_param('s', $email);
    $stmt->execute();
    $b = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if ($b) {
        echo json_encode(['status' => 'rejected', 'reason' => $b['reason']]);
        exit;
    }
}

echo json_encode(['status' => 'pending']);

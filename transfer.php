<?php
require_once 'config.php';

if (($_SESSION['user_type'] ?? '') !== 'user' || empty($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$myId = $_SESSION['user_id'];
$stmt = $conn->prepare("SELECT * FROM users WHERE id = ?");
$stmt->bind_param('i', $myId);
$stmt->execute();
$me = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$me) { session_destroy(); header('Location: login.php'); exit; }

$departments = ['IT', 'HR', 'Finance', 'Sales', 'Operations', 'Marketing'];
$roles = ['employee', 'manager', 'senior'];
$errors = [];
$success = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $reqDept = trim($_POST['requested_department'] ?? '');
    $reqRole = trim($_POST['requested_role'] ?? '');
    $reason  = trim($_POST['reason'] ?? '');

    if (!in_array($reqDept, $departments)) {
        $errors[] = 'Select a valid department.';
    }
    if (!in_array($reqRole, $roles)) {
        $errors[] = 'Select a valid role.';
    }
    if ($reason === '') {
        $errors[] = 'Please provide a reason for this transfer request.';
    }

    if (empty($errors)) {
        $stmt = $conn->prepare("INSERT INTO transfer_requests (user_id, current_department, current_role, requested_department, requested_role, reason) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->bind_param('isssss', $myId, $me['department'], $me['role'], $reqDept, $reqRole, $reason);
        if ($stmt->execute()) {
            $success = 'Your transfer request has been submitted for admin review.';
        } else {
            $errors[] = 'Something went wrong. Please try again.';
        }
        $stmt->close();
    }
}

// Fetch this user's transfer history
$stmt = $conn->prepare("SELECT * FROM transfer_requests WHERE user_id = ? ORDER BY requested_at DESC");
$stmt->bind_param('i', $myId);
$stmt->execute();
$history = [];
$res = $stmt->get_result();
while ($row = $res->fetch_assoc()) { $history[] = $row; }
$stmt->close();

$roomDept   = $me['department'];
$deptSlug   = strtolower(preg_replace('/\s+/', '_', $roomDept));
$deptRoomFile = $deptSlug . '.php';
$deptAnnFile  = $deptSlug . '_announcements.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Transfers · BlazePlus</title>
<link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
<div class="app-shell">
  <div class="sidebar">
    <div>
      <div class="brand">Blaze<span>Plus</span></div>
      <div class="nav-section-label">Workspace</div>
      <a href="index.php" class="nav-item"><span class="label">Directory</span></a>
      <a href="transfer.php" class="nav-item active"><span class="label">Transfers</span></a>
      <div class="nav-section-label">Rooms · <?= htmlspecialchars($roomDept) ?></div>
      <a href="<?= $deptRoomFile ?>" class="nav-item soon"><span class="label">#<?= strtolower(htmlspecialchars($roomDept)) ?> (<?= $deptRoomFile ?>)</span></a>
      <a href="general.php" class="nav-item soon"><span class="label">#general (general.php)</span></a>
      <a href="<?= $deptAnnFile ?>" class="nav-item soon"><span class="label">#<?= strtolower(htmlspecialchars($roomDept)) ?>-announcements (<?= $deptAnnFile ?>)</span></a>
      <a href="announcement.php" class="nav-item soon"><span class="label">#all-announcements (announcement.php)</span></a>
    </div>
    <div class="sidebar-user">
      <?= htmlspecialchars($me['name']) ?><br>
      <span class="mono"><?= htmlspecialchars($me['emp_no']) ?></span> · <?= ucfirst($me['role']) ?><br>
      <a href="logout.php" style="color:#BFE3D6;">Log out</a>
    </div>
  </div>

  <div class="main">
    <div class="main-header">
      <div>
        <h1>Transfers</h1>
        <div class="sub">Request a department or role change — your admin will review it.</div>
      </div>
    </div>

    <div class="room-card" style="max-width:420px;margin-bottom:30px;">
      <div class="tag">CURRENT</div>
      <div class="row"><span class="k">Department</span> <?= htmlspecialchars($me['department']) ?></div>
      <div class="row"><span class="k">Role</span> <?= ucfirst($me['role']) ?></div>
    </div>

    <div class="auth-card" style="max-width:480px;">
      <h1 style="font-size:18px;">Request a transfer</h1>

      <?php if ($success): ?><div class="alert alert-ok"><?= htmlspecialchars($success) ?></div><?php endif; ?>
      <?php foreach ($errors as $e): ?>
        <div class="alert alert-error"><?= htmlspecialchars($e) ?></div>
      <?php endforeach; ?>

      <form method="POST" novalidate>
        <div class="field">
          <label>Requested department</label>
          <select name="requested_department" required>
            <?php foreach ($departments as $d): ?>
              <option value="<?= $d ?>" <?= (($_POST['requested_department'] ?? $me['department']) === $d) ? 'selected' : '' ?>><?= $d ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="field">
          <label>Requested role</label>
          <select name="requested_role" required>
            <?php foreach ($roles as $r): ?>
              <option value="<?= $r ?>" <?= (($_POST['requested_role'] ?? $me['role']) === $r) ? 'selected' : '' ?>><?= ucfirst($r) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="field">
          <label>Reason</label>
          <textarea name="reason" rows="4" required style="width:100%;padding:10px;border-radius:8px;border:1px solid #ccc;font:inherit;"><?= htmlspecialchars($_POST['reason'] ?? '') ?></textarea>
        </div>
        <button type="submit" class="btn">Submit request</button>
      </form>
    </div>

    <div style="margin-top:40px;max-width:700px;">
      <h1 style="font-size:18px;">Your requests</h1>
      <?php if (empty($history)): ?>
        <div class="sub" style="margin-top:8px;">You haven't submitted any transfer requests yet.</div>
      <?php else: ?>
        <table class="admin-table" style="margin-top:12px;">
          <thead>
            <tr><th>Requested Dept</th><th>Requested Role</th><th>Reason</th><th>Status</th><th>Submitted</th></tr>
          </thead>
          <tbody>
            <?php foreach ($history as $h): ?>
              <tr>
                <td><?= htmlspecialchars($h['requested_department']) ?></td>
                <td><?= ucfirst($h['requested_role']) ?></td>
                <td><?= htmlspecialchars($h['reason']) ?></td>
                <td>
                  <span class="pill" style="<?= $h['status'] === 'approved' ? 'background:#DCEEE4;color:#1E6B4A;' : ($h['status'] === 'rejected' ? 'background:#F7DADA;color:#A32B2B;' : '') ?>">
                    <?= ucfirst($h['status']) ?>
                  </span>
                </td>
                <td class="mono" style="color:var(--muted);font-size:12.5px;"><?= htmlspecialchars($h['requested_at']) ?></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      <?php endif; ?>
    </div>
  </div>
</div>
</body>
</html>
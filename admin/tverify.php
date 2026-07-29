<?php
define('ADMIN_CONTEXT', true);
require_once '../config.php';

if (($_SESSION['user_type'] ?? '') !== 'admin' || empty($_SESSION['admin_id'])) {
    header('Location: login.php');
    exit;
}

$result = $conn->query("
    SELECT tr.*, u.name AS emp_name, u.emp_no
    FROM transfer_requests tr
    JOIN users u ON u.id = tr.user_id
    WHERE tr.status = 'pending'
    ORDER BY tr.requested_at ASC
");
$queue = [];
while ($r = $result->fetch_assoc()) { $queue[] = $r; }
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Transfer Requests · BlazePlus Admin</title>
<link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>
<div class="app-shell">
  <div class="sidebar">
    <div>
      <div class="brand">Blaze<span>Plus</span></div>
      <div class="nav-section-label">Admin</div>
      <a href="dashboard.php" class="nav-item"><span class="label">Review Queue</span></a>
      <a href="tverify.php" class="nav-item active"><span class="label">Transfer Requests</span></a>
      <a href="complaints.php" class="nav-item"><span class="label">Complaints (complaints.php)</span></a>
    </div>
    <div class="sidebar-user">
      <?= htmlspecialchars($_SESSION['admin_name']) ?><br>
      <span class="mono">ADMIN</span><br>
      <a href="logout.php" style="color:#BFE3D6;">Log out</a>
    </div>
  </div>

  <div class="main">
    <div class="main-header">
      <div>
        <h1>Transfer Requests</h1>
        <div class="sub"><?= count($queue) ?> request<?= count($queue) === 1 ? '' : 's' ?> waiting on your decision.</div>
      </div>
    </div>

    <?php if (empty($queue)): ?>
      <div class="empty-note">Nothing to review right now.</div>
    <?php else: ?>
    <table class="admin-table">
      <thead>
        <tr><th>Name</th><th>Current</th><th>Requested</th><th>Reason</th><th>Submitted</th><th>Actions</th></tr>
      </thead>
      <tbody>
        <?php foreach ($queue as $q): ?>
        <tr>
          <td><?= htmlspecialchars($q['emp_name']) ?> <span class="mono" style="color:var(--muted);">(<?= htmlspecialchars($q['emp_no']) ?>)</span></td>
          <td><?= htmlspecialchars($q['current_department']) ?> · <?= ucfirst($q['current_role']) ?></td>
          <td><?= htmlspecialchars($q['requested_department']) ?> · <?= ucfirst($q['requested_role']) ?></td>
          <td><?= htmlspecialchars($q['reason']) ?></td>
          <td class="mono" style="color:var(--muted);font-size:12.5px;"><?= htmlspecialchars($q['requested_at']) ?></td>
          <td>
            <button class="mini-btn accept" onclick="doAction(<?= $q['id'] ?>, 'accept')">Accept</button>
            <button class="mini-btn reject" onclick="doAction(<?= $q['id'] ?>, 'reject')">Reject</button>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
    <?php endif; ?>
  </div>
</div>

<script>
function doAction(id, action) {
  const confirmMsg = action === 'accept'
    ? "Approve this transfer? The user's department/role will be updated immediately."
    : 'Reject this transfer request?';
  if (!confirm(confirmMsg)) return;

  fetch('transfer_action.php', {
    method: 'POST',
    headers: {'Content-Type': 'application/x-www-form-urlencoded'},
    body: `id=${id}&action=${action}`
  })
  .then(async r => {
    const text = await r.text();
    let data;
    try {
      data = JSON.parse(text);
    } catch (e) {
      console.error('Non-JSON response:', text);
      alert('Server error — check console for details.');
      return;
    }
    if (data.ok) {
      window.location.reload();
    } else {
      alert(data.error || 'Something went wrong.');
    }
  })
  .catch(err => {
    console.error('Network/fetch error:', err);
    alert('Request failed — check your network connection or console.');
  });
}
</script>
</body>
</html> 
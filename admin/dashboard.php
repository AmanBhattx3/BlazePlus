<?php
define('ADMIN_CONTEXT', true);
require_once '../config.php';

if (($_SESSION['user_type'] ?? '') !== 'admin' || empty($_SESSION['admin_id'])) {
    header('Location: login.php');
    exit;
}

// Only show rows where the user has completed verify.php (emp_no set)
$result = $conn->query("SELECT * FROM unverified WHERE emp_no IS NOT NULL ORDER BY verify_submitted_at ASC");
$queue = [];
while ($r = $result->fetch_assoc()) { $queue[] = $r; }
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Admin Dashboard · BlazePlus</title>
<link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>
<div class="app-shell">
  <div class="sidebar">
  <div>
    <div class="brand">Blaze<span>Plus</span></div>
    <div class="nav-section-label">Admin</div>
    <a href="dashboard.php" class="nav-item active"><span class="label">Review Queue</span></a>
    <a href="tverify.php" class="nav-item"><span class="label">Transfer Requests</span></a>
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
        <h1>Review Queue</h1>
        <div class="sub"><?= count($queue) ?> account<?= count($queue) === 1 ? '' : 's' ?> waiting on your decision.</div>
      </div>
    </div>

    <?php if (empty($queue)): ?>
      <div class="empty-note">Nothing to review right now.</div>
    <?php else: ?>
    <table class="admin-table">
      <thead>
        <tr><th>Name</th><th>Department</th><th>Employee No.</th><th>Submitted</th><th>Actions</th></tr>
      </thead>
      <tbody>
        <?php foreach ($queue as $q): ?>
        <tr>
          <td><?= htmlspecialchars($q['name']) ?></td>
          <td><span class="pill"><?= htmlspecialchars($q['department']) ?></span></td>
          <td class="mono"><?= htmlspecialchars($q['emp_no']) ?></td>
          <td class="mono" style="color:var(--muted);font-size:12.5px;"><?= htmlspecialchars($q['verify_submitted_at']) ?></td>
          <td>
            <button class="mini-btn" onclick='viewDetails(<?= json_encode($q) ?>)'>View details</button>
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

<div class="modal-backdrop" id="modalBackdrop">
  <div class="modal">
    <h3>Full details</h3>
    <div id="modalBody"></div>
    <button class="btn btn-outline modal-close" onclick="closeModal()">Close</button>
  </div>
</div>

<script>
function viewDetails(row) {
  const fields = [
    ['Name', row.name], ['Email', row.email], ['Phone', row.phone],
    ['Employee No.', row.emp_no], ['DOB', row.dob],
    ['Department', row.department], ['Role', row.role],
    ['Submitted', row.verify_submitted_at]
  ];
  document.getElementById('modalBody').innerHTML = fields.map(f =>
    `<div class="row"><span class="k">${f[0]}</span><span>${f[1] ?? ''}</span></div>`
  ).join('');
  document.getElementById('modalBackdrop').classList.add('show');
}
function closeModal() {
  document.getElementById('modalBackdrop').classList.remove('show');
}

function doAction(id, action) {
  let reason = null;
  if (action === 'reject') {
    reason = prompt('Reason for rejection:', 'unknown identity');
    if (reason === null) return; // cancelled
    if (reason.trim() === '') reason = 'unknown identity';
  } else {
    if (!confirm('Approve this user and grant access?')) return;
  }
  fetch('action.php', {
    method: 'POST',
    headers: {'Content-Type': 'application/x-www-form-urlencoded'},
    body: `id=${id}&action=${action}&reason=${encodeURIComponent(reason || '')}`
  })
  .then(r => r.json())
  .then(data => {
    if (data.ok) {
      window.location.reload();
    } else {
      alert(data.error || 'Something went wrong.');
    }
  });
}
</script>
</body>
</html>
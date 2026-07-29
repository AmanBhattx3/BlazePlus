<?php
define('ADMIN_CONTEXT', true);
require_once '../config.php';

if (($_SESSION['user_type'] ?? '') !== 'admin' || empty($_SESSION['admin_id'])) {
    header('Location: login.php');
    exit;
}

$result = $conn->query("
    SELECT mr.*, r.name AS reporter_name, r.emp_no AS reporter_emp_no,
           t.name AS reported_name, t.emp_no AS reported_emp_no
    FROM message_reports mr
    JOIN users r ON r.id = mr.reporter_id
    JOIN users t ON t.id = mr.reported_user_id
    WHERE mr.status = 'open'
    ORDER BY mr.reported_at ASC
");
$queue = [];
while ($r = $result->fetch_assoc()) { $queue[] = $r; }
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Complaints · BlazePlus Admin</title>
<link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>
<div class="app-shell">
  <div class="sidebar">
    <div>
      <div class="brand">Blaze<span>Plus</span></div>
      <div class="nav-section-label">Admin</div>
      <a href="dashboard.php" class="nav-item"><span class="label">Review Queue</span></a>
      <a href="tverify.php" class="nav-item"><span class="label">Transfer Requests</span></a>
      <a href="complaints.php" class="nav-item active"><span class="label">Complaints</span></a>
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
        <h1>Complaints</h1>
        <div class="sub"><?= count($queue) ?> report<?= count($queue) === 1 ? '' : 's' ?> waiting on your review.</div>
      </div>
    </div>

    <?php if (empty($queue)): ?>
      <div class="empty-note">Nothing to review right now.</div>
    <?php else: ?>
      <?php foreach ($queue as $q): ?>
      <div class="room-card" style="max-width:700px;margin-bottom:20px;">
        <div class="tag"><?= strtoupper($q['message_type']) ?> · #<?= htmlspecialchars($q['room_key']) ?></div>
        <div class="row"><span class="k">Reported</span> <?= htmlspecialchars($q['reported_name']) ?> (<?= htmlspecialchars($q['reported_emp_no']) ?>)</div>
        <div class="row"><span class="k">Reported by</span> <?= htmlspecialchars($q['reporter_name']) ?> (<?= htmlspecialchars($q['reporter_emp_no']) ?>)</div>
        <div class="row"><span class="k">Reason</span> <?= htmlspecialchars($q['reason']) ?></div>
        <?php if ($q['message_text']): ?><div class="row"><span class="k">Message</span> <?= htmlspecialchars($q['message_text']) ?></div><?php endif; ?>
        <?php if ($q['image_path']): ?><div style="margin-top:8px;"><img src="../<?= htmlspecialchars($q['image_path']) ?>" style="max-width:220px;border-radius:8px;"></div><?php endif; ?>
        <?php if ($q['pdf_path']): ?><div style="margin-top:8px;"><a href="../<?= htmlspecialchars($q['pdf_path']) ?>" target="_blank">📄 View PDF</a></div><?php endif; ?>
        <div class="row"><span class="k">Reported at</span> <span class="mono" style="color:var(--muted);font-size:12.5px;"><?= htmlspecialchars($q['reported_at']) ?></span></div>
        <div style="margin-top:12px;display:flex;gap:8px;">
          <button class="mini-btn accept" onclick="doAction(<?= $q['id'] ?>, 'resolve')">Resolve</button>
          <button class="mini-btn" onclick="doAction(<?= $q['id'] ?>, 'dismiss')">Dismiss</button>
          <button class="mini-btn reject" onclick="doAction(<?= $q['id'] ?>, 'delete_message')">Delete Message</button>
        </div>
      </div>
      <?php endforeach; ?>
    <?php endif; ?>
  </div>
</div>

<script>
function doAction(id, action) {
  let confirmMsg = 'Mark this report as ' + action + '?';
  if (action === 'delete_message') confirmMsg = 'Delete the reported message permanently? This also resolves the report.';
  if (!confirm(confirmMsg)) return;

  fetch('complaints_action.php', {
    method: 'POST',
    headers: {'Content-Type': 'application/x-www-form-urlencoded'},
    body: `id=${id}&action=${action}`
  })
  .then(async r => {
    const text = await r.text();
    let data;
    try { data = JSON.parse(text); }
    catch (e) { console.error('Non-JSON response:', text); alert('Server error — check console.'); return; }
    if (data.ok) window.location.reload();
    else alert(data.error || 'Something went wrong.');
  })
  .catch(err => { console.error(err); alert('Request failed.'); });
}
</script>
</body>
</html>
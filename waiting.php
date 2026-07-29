<?php
require_once 'config.php';

if (($_SESSION['user_type'] ?? '') !== 'unverified' || empty($_SESSION['unverified_id'])) {
    header('Location: login.php');
    exit;
}

$id = $_SESSION['unverified_id'];
$stmt = $conn->prepare("SELECT name, verify_submitted_at FROM unverified WHERE id = ?");
$stmt->bind_param('i', $id);
$stmt->execute();
$row = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$row) { header('Location: login.php'); exit; }
if ($row['verify_submitted_at'] === null) { header('Location: verify.php'); exit; }

$deadline = strtotime($row['verify_submitted_at']) + (VERIFY_TIMEOUT_MINUTES * 60);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Awaiting Verification · BlazePlus</title>
<link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
<div class="wait-shell">
  <div class="tag mono" style="color:var(--muted);font-size:12px;letter-spacing:.08em;">REVIEW QUEUE</div>
  <h1 style="margin-top:8px;">You're in the queue, <?= htmlspecialchars($row['name']) ?></h1>
  <p class="status-note">An admin needs to confirm your details before you can access the directory. If no one reviews you before the timer runs out, your request expires and you'll need to sign up again.</p>

  <div class="pipeline">
    <div class="pipe-step">
      <div class="pipe-dot done"></div>
      <div class="pipe-label">SIGNED UP</div>
    </div>
    <div class="pipe-line done"></div>
    <div class="pipe-step">
      <div class="pipe-dot done"></div>
      <div class="pipe-label">DETAILS SENT</div>
    </div>
    <div class="pipe-line" id="line2"></div>
    <div class="pipe-step">
      <div class="pipe-dot active" id="dot3"></div>
      <div class="pipe-label">ADMIN REVIEW</div>
    </div>
    <div class="pipe-line"></div>
    <div class="pipe-step">
      <div class="pipe-dot" id="dot4"></div>
      <div class="pipe-label">ACCESS GRANTED</div>
    </div>
  </div>

  <div class="timer-label">TIME REMAINING</div>
  <div class="timer mono" id="timer">--:--</div>

  <div id="statusMsg" style="margin-top:24px;"></div>
</div>

<script>
const deadline = <?= $deadline ?> * 1000;

function tick() {
  const remaining = deadline - Date.now();
  const timerEl = document.getElementById('timer');
  if (remaining <= 0) {
    timerEl.textContent = '00:00';
    return;
  }
  const mins = Math.floor(remaining / 60000);
  const secs = Math.floor((remaining % 60000) / 1000);
  timerEl.textContent = String(mins).padStart(2, '0') + ':' + String(secs).padStart(2, '0');
}
tick();
setInterval(tick, 1000);

function poll() {
  fetch('check_status.php')
    .then(r => r.json())
    .then(data => {
      const msg = document.getElementById('statusMsg');
      if (data.status === 'approved') {
        document.getElementById('dot3').classList.add('done');
        document.getElementById('dot3').classList.remove('active');
        document.getElementById('dot4').classList.add('active');
        msg.innerHTML = '<div class="alert alert-ok">Verified! Redirecting to your directory…</div>';
        setTimeout(() => window.location.href = 'index.php', 1200);
      } else if (data.status === 'rejected') {
        msg.innerHTML = '<div class="alert alert-error">Your request was declined: ' + data.reason + '. Contact xyz@gmail.com for details.</div>';
        setTimeout(() => window.location.href = 'login.php', 3500);
      } else if (data.status === 'expired') {
        msg.innerHTML = '<div class="alert alert-error">Your review window expired. Please sign up again.</div>';
        setTimeout(() => window.location.href = 'signup.php', 3000);
      }
    })
    .catch(() => {});
}
poll();
setInterval(poll, 4000);
</script>
</body>
</html>

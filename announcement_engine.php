<?php
// Included by announcement files. Expects $roomKey and $roomDept ('IT','HR',... or null for all-depts) set before inclusion.
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

if ($roomDept !== null && $me['department'] !== $roomDept) {
    header('Location: index.php');
    exit;
}

$canPost = in_array($me['role'], ['manager', 'senior']);
$canPostPdf = ($me['role'] === 'senior');

const MAX_IMAGE_BYTES = 2 * 1024 * 1024;
const MAX_PDF_BYTES = 5 * 1024 * 1024;

function fetchAnnouncements($conn, $roomKey) {
    $stmt = $conn->prepare("
        SELECT am.*, u.name, u.emp_no, u.role
        FROM announcement_messages am
        JOIN users u ON u.id = am.user_id
        WHERE am.room_key = ?
        ORDER BY am.created_at DESC
        LIMIT 50
    ");
    $stmt->bind_param('s', $roomKey);
    $stmt->execute();
    $res = $stmt->get_result();
    $msgs = [];
    while ($r = $res->fetch_assoc()) { $msgs[] = $r; }
    $stmt->close();
    return array_reverse($msgs);
}

// ---- AJAX: fetch latest messages ----
if (isset($_GET['ajax']) && $_GET['ajax'] === 'messages') {
    header('Content-Type: application/json');
    echo json_encode(fetchAnnouncements($conn, $roomKey));
    exit;
}

// ---- AJAX: send an announcement ----
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ajax_send'])) {
    header('Content-Type: application/json');

    if (!$canPost) {
        echo json_encode(['ok' => false, 'error' => 'Only managers and seniors can post here.']);
        exit;
    }

    $text = trim($_POST['message'] ?? '');
    $imagePath = null;
    $pdfPath = null;
    $error = null;

    if (!empty($_FILES['image']['name'])) {
        $file = $_FILES['image'];
        $mime = mime_content_type($file['tmp_name']);
        if (!in_array($mime, ['image/jpeg', 'image/png', 'image/gif', 'image/webp'])) {
            $error = 'Only image files are allowed.';
        } elseif ($file['size'] > MAX_IMAGE_BYTES) {
            $error = 'Image must be 2MB or smaller.';
        } else {
            $dir = __DIR__ . '/uploads/chat/';
            if (!is_dir($dir)) mkdir($dir, 0755, true);
            $fname = 'img_' . uniqid() . '.' . pathinfo($file['name'], PATHINFO_EXTENSION);
            if (move_uploaded_file($file['tmp_name'], $dir . $fname)) $imagePath = 'uploads/chat/' . $fname;
            else $error = 'Failed to upload image.';
        }
    }

    if (!$error && !empty($_FILES['pdf']['name'])) {
        if (!$canPostPdf) {
            $error = 'Only seniors can attach PDFs.';
        } else {
            $file = $_FILES['pdf'];
            if (mime_content_type($file['tmp_name']) !== 'application/pdf') {
                $error = 'Only PDF files are allowed for this upload.';
            } elseif ($file['size'] > MAX_PDF_BYTES) {
                $error = 'PDF must be 5MB or smaller.';
            } else {
                $dir = __DIR__ . '/uploads/chat/';
                if (!is_dir($dir)) mkdir($dir, 0755, true);
                $fname = 'pdf_' . uniqid() . '.pdf';
                if (move_uploaded_file($file['tmp_name'], $dir . $fname)) $pdfPath = 'uploads/chat/' . $fname;
                else $error = 'Failed to upload PDF.';
            }
        }
    }

    if (!$error && $text === '' && !$imagePath && !$pdfPath) {
        $error = 'Message cannot be empty.';
    }

    if (!$error) {
        $stmt = $conn->prepare("INSERT INTO announcement_messages (room_key, user_id, message, image_path, pdf_path) VALUES (?, ?, ?, ?, ?)");
        $stmt->bind_param('sssss', $roomKey, $myId, $text, $imagePath, $pdfPath);
        $stmt->execute();
        $stmt->close();
        echo json_encode(['ok' => true]);
    } else {
        echo json_encode(['ok' => false, 'error' => $error]);
    }
    exit;
}

// ---- AJAX: report a message ----
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ajax_report'])) {
    header('Content-Type: application/json');

    $reportMsgId = intval($_POST['message_id'] ?? 0);
    $reason = trim($_POST['reason'] ?? '');

    if (!$reportMsgId || $reason === '') {
        echo json_encode(['ok' => false, 'error' => 'Reason is required.']);
        exit;
    }

    $stmt = $conn->prepare("SELECT * FROM announcement_messages WHERE id = ? AND room_key = ?");
    $stmt->bind_param('is', $reportMsgId, $roomKey);
    $stmt->execute();
    $msg = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$msg) {
        echo json_encode(['ok' => false, 'error' => 'Message not found.']);
        exit;
    }
    if ($msg['user_id'] == $myId) {
        echo json_encode(['ok' => false, 'error' => 'You cannot report your own message.']);
        exit;
    }

    $stmt = $conn->prepare("INSERT INTO message_reports (message_id, message_type, room_key, reporter_id, reported_user_id, message_text, image_path, pdf_path, reason) VALUES (?, 'announcement', ?, ?, ?, ?, ?, ?, ?)");
    $stmt->bind_param('isiissss', $reportMsgId, $roomKey, $myId, $msg['user_id'], $msg['message'], $msg['image_path'], $msg['pdf_path'], $reason);
    $stmt->execute();
    $stmt->close();

    echo json_encode(['ok' => true]);
    exit;
}

$messages = fetchAnnouncements($conn, $roomKey);

$sidebarDeptSlug = strtolower(preg_replace('/\s+/', '_', $me['department']));
$sidebarDeptRoomFile = $sidebarDeptSlug . '.php';
$sidebarDeptAnnFile  = $sidebarDeptSlug . '_announcements.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>#<?= strtolower(htmlspecialchars($roomKey)) ?> · BlazePlus</title>
<link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
<div class="app-shell">
  <div class="sidebar">
    <div>
      <div class="brand">Blaze<span>Plus</span></div>
      <div class="nav-section-label">Workspace</div>
      <a href="index.php" class="nav-item"><span class="label">Directory</span></a>
      <a href="transfer.php" class="nav-item"><span class="label">Transfers</span></a>
      <div class="nav-section-label">Rooms · <?= htmlspecialchars($me['department']) ?></div>
      <a href="<?= $sidebarDeptRoomFile ?>" class="nav-item"><span class="label">#<?= strtolower(htmlspecialchars($me['department'])) ?> (<?= $sidebarDeptRoomFile ?>)</span></a>
      <a href="general.php" class="nav-item"><span class="label">#general (general.php)</span></a>
      <a href="<?= $sidebarDeptAnnFile ?>" class="nav-item <?= $roomKey === $sidebarDeptSlug . '_announcements' ? 'active' : '' ?>"><span class="label">#<?= strtolower(htmlspecialchars($me['department'])) ?>-announcements (<?= $sidebarDeptAnnFile ?>)</span></a>
      <a href="announcement.php" class="nav-item <?= $roomKey === 'announcement' ? 'active' : '' ?>"><span class="label">#all-announcements (announcement.php)</span></a>
    </div>
    <div class="sidebar-user">
      <?= htmlspecialchars($me['name']) ?><br>
      <span class="mono"><?= htmlspecialchars($me['emp_no']) ?></span> · <?= ucfirst($me['role']) ?><br>
      <a href="logout.php" style="color:#BFE3D6;">Log out</a>
    </div>
  </div>

  <div class="main" style="display:flex;flex-direction:column;height:100vh;box-sizing:border-box;">
    <div class="main-header" style="flex:0 0 auto;">
      <div>
        <h1>#<?= strtolower(htmlspecialchars($roomKey)) ?></h1>
        <div class="sub"><?= $canPost ? 'You can post here.' : 'View only — only managers/seniors can post.' ?></div>
      </div>
    </div>

    <div id="sendError"></div>

    <div id="chatBox" style="flex:1 1 auto;min-height:0;height:80vh;width:100%;overflow-y:auto;border:1px solid #ddd;border-radius:10px;padding:16px;margin-bottom:12px;background:#fff;box-sizing:border-box;">
      <?php foreach ($messages as $m): ?>
        <div class="msg-row" style="margin-bottom:14px;">
          <div style="font-size:13px;color:var(--muted);">
            <strong><?= htmlspecialchars($m['name']) ?></strong>
            <span class="mono"> (<?= htmlspecialchars($m['emp_no']) ?>)</span> · <?= ucfirst($m['role']) ?>
            · <?= htmlspecialchars($m['created_at']) ?>
          </div>
          <?php if ($m['message']): ?><div><?= nl2br(htmlspecialchars($m['message'])) ?></div><?php endif; ?>
          <?php if ($m['image_path']): ?><img src="<?= htmlspecialchars($m['image_path']) ?>" style="max-width:260px;border-radius:8px;margin-top:6px;"><?php endif; ?>
          <?php if ($m['pdf_path']): ?><div style="margin-top:6px;"><a href="<?= htmlspecialchars($m['pdf_path']) ?>" target="_blank">📄 View PDF</a></div><?php endif; ?>
          <?php if ($m['user_id'] != $myId): ?>
            <a href="#" onclick="reportMessage(<?= $m['id'] ?>); return false;" style="font-size:11px;color:#B23B3B;">Report</a>
          <?php endif; ?>
        </div>
      <?php endforeach; ?>
    </div>

    <?php if ($canPost): ?>
    <form id="chatForm" style="flex:0 0 auto;width:100%;box-sizing:border-box;">
      <div style="display:flex;gap:10px;align-items:center;flex-wrap:wrap;">
        <input type="text" name="message" id="messageInput" placeholder="Type an announcement…"
               style="flex:1 1 auto;width:100%;min-width:0;box-sizing:border-box;padding:14px 16px;border-radius:8px;border:1px solid #ccc;font:inherit;font-size:15px;">
        <input type="file" name="image" id="imageInput" accept="image/*" style="flex:0 0 auto;">
        <?php if ($canPostPdf): ?><input type="file" name="pdf" id="pdfInput" accept="application/pdf" style="flex:0 0 auto;"><?php endif; ?>
        <button type="submit" class="btn" style="flex:0 0 auto;white-space:nowrap;padding:6px 10px;font-size:12px;min-width:0;width:auto;">Send</button>
      </div>
    </form>
    <?php endif; ?>
  </div>
</div>

<script>
const roomUrl = window.location.pathname;
const chatBox = document.getElementById('chatBox');
const canPost = <?= $canPost ? 'true' : 'false' ?>;
const CURRENT_USER_ID = <?= $myId ?>;

function escapeHtml(str) {
  const div = document.createElement('div');
  div.textContent = str ?? '';
  return div.innerHTML;
}

function renderMessages(messages) {
  chatBox.innerHTML = messages.map(m => {
    let html = '<div class="msg-row" style="margin-bottom:14px;">';
    html += '<div style="font-size:13px;color:var(--muted);"><strong>' + escapeHtml(m.name) + '</strong>';
    html += ' <span class="mono">(' + escapeHtml(m.emp_no) + ')</span> · ' + escapeHtml(m.role.charAt(0).toUpperCase() + m.role.slice(1));
    html += ' · ' + escapeHtml(m.created_at) + '</div>';
    if (m.message) html += '<div>' + escapeHtml(m.message).replace(/\n/g, '<br>') + '</div>';
    if (m.image_path) html += '<img src="' + escapeHtml(m.image_path) + '" style="max-width:260px;border-radius:8px;margin-top:6px;">';
    if (m.pdf_path) html += '<div style="margin-top:6px;"><a href="' + escapeHtml(m.pdf_path) + '" target="_blank">📄 View PDF</a></div>';
    if (m.user_id != CURRENT_USER_ID) html += '<a href="#" onclick="reportMessage(' + m.id + '); return false;" style="font-size:11px;color:#B23B3B;">Report</a>';
    html += '</div>';
    return html;
  }).join('');
}

function isNearBottom() {
  return chatBox.scrollHeight - chatBox.scrollTop - chatBox.clientHeight < 60;
}

async function pollMessages() {
  try {
    const wasNearBottom = isNearBottom();
    const r = await fetch(roomUrl + '?ajax=messages');
    const messages = await r.json();
    renderMessages(messages);
    if (wasNearBottom) chatBox.scrollTop = chatBox.scrollHeight;
  } catch (e) {
    console.error('Poll failed:', e);
  }
}

async function reportMessage(id) {
  const reason = prompt('Why are you reporting this message?');
  if (reason === null) return;
  if (reason.trim() === '') { alert('A reason is required.'); return; }

  try {
    const formData = new FormData();
    formData.append('ajax_report', '1');
    formData.append('message_id', id);
    formData.append('reason', reason.trim());
    const r = await fetch(roomUrl, { method: 'POST', body: formData });
    const data = await r.json();
    alert(data.ok ? 'Message reported. Admin will review it.' : (data.error || 'Failed to report.'));
  } catch (e) {
    alert('Failed to report — check your connection.');
  }
}

if (canPost) {
  const form = document.getElementById('chatForm');
  const messageInput = document.getElementById('messageInput');
  const imageInput = document.getElementById('imageInput');
  const pdfInput = document.getElementById('pdfInput');
  const errorBox = document.getElementById('sendError');

  form.addEventListener('submit', async (e) => {
    e.preventDefault();
    errorBox.innerHTML = '';

    const formData = new FormData();
    formData.append('ajax_send', '1');
    formData.append('message', messageInput.value);
    if (imageInput.files[0]) formData.append('image', imageInput.files[0]);
    if (pdfInput && pdfInput.files[0]) formData.append('pdf', pdfInput.files[0]);

    try {
      const r = await fetch(roomUrl, { method: 'POST', body: formData });
      const data = await r.json();
      if (data.ok) {
        messageInput.value = '';
        imageInput.value = '';
        if (pdfInput) pdfInput.value = '';
        await pollMessages();
        chatBox.scrollTop = chatBox.scrollHeight;
      } else {
        errorBox.innerHTML = '<div class="alert alert-error">' + escapeHtml(data.error) + '</div>';
      }
    } catch (err) {
      console.error('Send failed:', err);
      errorBox.innerHTML = '<div class="alert alert-error">Failed to send — check your connection.</div>';
    }
  });
}

chatBox.scrollTop = chatBox.scrollHeight;
setInterval(pollMessages, 4000);
</script>
</body>
</html>
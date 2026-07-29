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

$viewId = isset($_GET['id']) ? intval($_GET['id']) : $myId;
$isOwnProfile = ($viewId === (int)$myId);

$myRole = $me['role'];
if ($myRole === 'employee') {
    $visibleRoles = ['employee', 'manager']; // manager dept-check happens below
} elseif ($myRole === 'manager') {
    $visibleRoles = ['employee', 'manager'];
} else {
    $visibleRoles = ['employee', 'manager', 'senior'];
}

if ($isOwnProfile) {
    $target = $me;
} else {
    $stmt = $conn->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->bind_param('i', $viewId);
    $stmt->execute();
    $target = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    $allowed = $target && in_array($target['role'], $visibleRoles);
    // Employees can only view managers within their own department
    if ($allowed && $myRole === 'employee' && $target['role'] === 'manager' && $target['department'] !== $me['department']) {
        $allowed = false;
    }
    if (!$allowed) {
        header('Location: index.php');
        exit;
    }
}

function fieldVisible($viewer, $target, $field, $approvedGrants) {
    $hideCol = ($field === 'phone') ? 'hide_contact' : 'hide_email';
    if ($viewer['role'] === 'employee' && in_array($target['role'], ['manager', 'senior']) && $target['id'] != $viewer['id']) {
        $grant = $approvedGrants[$target['id']] ?? null;
        return ($grant === 'both' || $grant === $field);
    }
    if ($target['id'] == $viewer['id']) return true;
    if (!$target[$hideCol]) return true;
    if ($target['role'] === 'employee' && $viewer['role'] === 'employee') return false;
    if ($target['role'] === 'senior' && $viewer['role'] === 'senior') return false;
    return true;
}

$errors = [];
$success = null;
const RATE_LIMIT_DAYS = 30;

function daysRemaining($lastChange) {
    if ($lastChange === null) return 0;
    $elapsed = (time() - strtotime($lastChange)) / 86400;
    return max(0, ceil(RATE_LIMIT_DAYS - $elapsed));
}

function logChange($conn, $userId, $field, $old, $new) {
    $stmt = $conn->prepare("INSERT INTO logs (user_id, field_changed, old_value, new_value) VALUES (?, ?, ?, ?)");
    $stmt->bind_param('isss', $userId, $field, $old, $new);
    $stmt->execute();
    $stmt->close();
}

$phoneLockDays = daysRemaining($me['last_phone_change']);
$emailLockDays = daysRemaining($me['last_email_change']);

// ---- POST handling ----
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['form_action'] ?? '';

    // Edit own contact info (own profile only)
    if ($isOwnProfile && $action === 'edit_profile') {
        $newPhone = trim($_POST['phone'] ?? '');
        $newEmail = trim($_POST['email'] ?? '');
        $hidePhone = isset($_POST['hide_contact']) ? 1 : 0;
        $hideEmail = isset($_POST['hide_email']) ? 1 : 0;

        $phoneChanged = ($newPhone !== $me['phone']);
        if ($phoneChanged) {
            if ($phoneLockDays > 0) $errors[] = "You can change your phone number again in {$phoneLockDays} day(s).";
            elseif (!preg_match('/^[0-9]{10}$/', $newPhone)) $errors[] = 'Enter a valid 10-digit phone number.';
        }

        $emailChanged = ($newEmail !== $me['email']);
        if ($emailChanged) {
            if ($emailLockDays > 0) $errors[] = "You can change your email again in {$emailLockDays} day(s).";
            elseif (!filter_var($newEmail, FILTER_VALIDATE_EMAIL)) $errors[] = 'Enter a valid email address.';
            else {
                $chk = $conn->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
                $chk->bind_param('si', $newEmail, $myId);
                $chk->execute();
                if ($chk->get_result()->num_rows > 0) $errors[] = 'That email is already in use by another account.';
                $chk->close();
            }
        }

        if (empty($errors)) {
            $conn->begin_transaction();
            try {
                if ($phoneChanged) {
                    $stmt = $conn->prepare("UPDATE users SET phone = ?, last_phone_change = NOW() WHERE id = ?");
                    $stmt->bind_param('si', $newPhone, $myId);
                    $stmt->execute(); $stmt->close();
                    logChange($conn, $myId, 'phone', $me['phone'], $newPhone);
                    $me['phone'] = $newPhone;
                }
                if ($emailChanged) {
                    $stmt = $conn->prepare("UPDATE users SET email = ?, last_email_change = NOW() WHERE id = ?");
                    $stmt->bind_param('si', $newEmail, $myId);
                    $stmt->execute(); $stmt->close();
                    logChange($conn, $myId, 'email', $me['email'], $newEmail);
                    $me['email'] = $newEmail;
                }
                if ($hidePhone != $me['hide_contact']) {
                    $stmt = $conn->prepare("UPDATE users SET hide_contact = ? WHERE id = ?");
                    $stmt->bind_param('ii', $hidePhone, $myId);
                    $stmt->execute(); $stmt->close();
                    logChange($conn, $myId, 'hide_contact', $me['hide_contact'], $hidePhone);
                    $me['hide_contact'] = $hidePhone;
                }
                if ($hideEmail != $me['hide_email']) {
                    $stmt = $conn->prepare("UPDATE users SET hide_email = ? WHERE id = ?");
                    $stmt->bind_param('ii', $hideEmail, $myId);
                    $stmt->execute(); $stmt->close();
                    logChange($conn, $myId, 'hide_email', $me['hide_email'], $hideEmail);
                    $me['hide_email'] = $hideEmail;
                }
                $conn->commit();
                $success = 'Profile updated.';
                $target = $me;
                $phoneLockDays = daysRemaining($me['last_phone_change']);
                $emailLockDays = daysRemaining($me['last_email_change']);
            } catch (\Throwable $e) {
                $conn->rollback();
                $errors[] = 'Something went wrong. Please try again.';
            }
        }
    }

    // Request contact (viewing someone else's profile)
    if (!$isOwnProfile && $action === 'request_contact') {
        $reason = trim($_POST['reason'] ?? '');
        if ($reason === '') {
            $errors[] = 'Please provide a reason for your request.';
        } else {
            $chk = $conn->prepare("SELECT id FROM contact_requests WHERE requester_id = ? AND target_id = ? AND status = 'pending'");
            $chk->bind_param('ii', $myId, $viewId);
            $chk->execute();
            if ($chk->get_result()->num_rows > 0) {
                $errors[] = 'You already have a pending request to this person.';
            }
            $chk->close();

            if (empty($errors)) {
                $stmt = $conn->prepare("INSERT INTO contact_requests (requester_id, target_id, reason) VALUES (?, ?, ?)");
                $stmt->bind_param('iis', $myId, $viewId, $reason);
                $stmt->execute();
                $stmt->close();
                $success = 'Your contact request has been sent.';
            }
        }
    }

    // Approve / decline a contact request (own profile, manager/senior only)
    if ($isOwnProfile && in_array($myRole, ['manager', 'senior']) && in_array($action, ['approve_contact', 'decline_contact'])) {
        $reqId = intval($_POST['request_id'] ?? 0);
        $stmt = $conn->prepare("SELECT * FROM contact_requests WHERE id = ? AND target_id = ? AND status = 'pending'");
        $stmt->bind_param('ii', $reqId, $myId);
        $stmt->execute();
        $reqRow = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if ($reqRow) {
            if ($action === 'approve_contact') {
                $shareField = $_POST['share_field'] ?? 'phone';
                if (!in_array($shareField, ['phone', 'email', 'both'])) $shareField = 'phone';
                $stmt = $conn->prepare("UPDATE contact_requests SET status = 'approved', shared_field = ?, decided_at = NOW() WHERE id = ?");
                $stmt->bind_param('si', $shareField, $reqId);
                $stmt->execute();
                $stmt->close();
            } else {
                $stmt = $conn->prepare("UPDATE contact_requests SET status = 'declined', decided_at = NOW() WHERE id = ?");
                $stmt->bind_param('i', $reqId);
                $stmt->execute();
                $stmt->close();
            }
            $success = 'Request updated.';
        }
    }
}

// Approved grant for THIS specific requester/target pair (used to reveal fields on a non-own profile)
$approvedGrants = [];
if (!$isOwnProfile) {
    $stmt = $conn->prepare("SELECT target_id, shared_field FROM contact_requests WHERE requester_id = ? AND status = 'approved'");
    $stmt->bind_param('i', $myId);
    $stmt->execute();
    $res = $stmt->get_result();
    while ($r = $res->fetch_assoc()) { $approvedGrants[$r['target_id']] = $r['shared_field']; }
    $stmt->close();
}

$phoneVisible = fieldVisible($me, $target, 'phone', $approvedGrants);
$emailVisible = fieldVisible($me, $target, 'email', $approvedGrants);

$hasPendingRequest = false;
if (!$isOwnProfile) {
    $chk = $conn->prepare("SELECT id FROM contact_requests WHERE requester_id = ? AND target_id = ? AND status = 'pending'");
    $chk->bind_param('ii', $myId, $viewId);
    $chk->execute();
    $hasPendingRequest = $chk->get_result()->num_rows > 0;
    $chk->close();
}

$showRequestButton = !$isOwnProfile && $myRole === 'employee' && in_array($target['role'], ['manager', 'senior']) && (!$phoneVisible || !$emailVisible);

// Pending requests directed at ME (only shown on own profile, for managers/seniors)
$myPendingRequests = [];
if ($isOwnProfile && in_array($myRole, ['manager', 'senior'])) {
    $stmt = $conn->prepare("
        SELECT cr.*, u.name AS requester_name, u.emp_no AS requester_emp_no
        FROM contact_requests cr
        JOIN users u ON u.id = cr.requester_id
        WHERE cr.target_id = ? AND cr.status = 'pending'
        ORDER BY cr.requested_at ASC
    ");
    $stmt->bind_param('i', $myId);
    $stmt->execute();
    $res = $stmt->get_result();
    while ($r = $res->fetch_assoc()) { $myPendingRequests[] = $r; }
    $stmt->close();
}

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
<title><?= $isOwnProfile ? 'My Profile' : htmlspecialchars($target['name']) ?> · BlazePlus</title>
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
        <h1><?= $isOwnProfile ? 'My Profile' : htmlspecialchars($target['name']) . "'s Profile" ?></h1>
        <div class="sub"><?= $isOwnProfile ? 'Manage your contact info and visibility.' : 'Viewing another employee — read only.' ?></div>
      </div>
    </div>

    <?php if ($success): ?><div class="alert alert-ok"><?= htmlspecialchars($success) ?></div><?php endif; ?>
    <?php foreach ($errors as $e): ?>
      <div class="alert alert-error"><?= htmlspecialchars($e) ?></div>
    <?php endforeach; ?>

    <div class="rooms-grid" style="margin-bottom:30px;">
      <div class="room-card">
        <div class="tag">NAME</div>
        <h3><?= htmlspecialchars($target['name']) ?></h3>
      </div>
      <div class="room-card">
        <div class="tag">EMPLOYEE NO.</div>
        <h3 class="mono"><?= htmlspecialchars($target['emp_no']) ?></h3>
      </div>
      <div class="room-card">
        <div class="tag">DEPARTMENT</div>
        <h3><?= htmlspecialchars($target['department']) ?></h3>
      </div>
      <div class="room-card">
        <div class="tag">ROLE</div>
        <h3><?= ucfirst($target['role']) ?></h3>
      </div>
      <div class="room-card">
        <div class="tag">PHONE</div>
        <h3><?= $phoneVisible ? htmlspecialchars($target['phone']) : 'Hidden' ?></h3>
      </div>
      <div class="room-card">
        <div class="tag">EMAIL</div>
        <h3><?= $emailVisible ? htmlspecialchars($target['email']) : 'Hidden' ?></h3>
      </div>
    </div>

    <?php if ($showRequestButton): ?>
      <?php if ($hasPendingRequest): ?>
        <div class="room-card" style="max-width:420px;margin-bottom:30px;">
          <div class="tag">CONTACT REQUEST</div>
          <div class="status">Your request is pending review.</div>
        </div>
      <?php else: ?>
        <div class="auth-card" style="max-width:420px;margin-bottom:30px;">
          <h1 style="font-size:16px;">Request Contact</h1>
          <form method="POST">
            <input type="hidden" name="form_action" value="request_contact">
            <div class="field">
              <label>Reason for contacting <?= htmlspecialchars($target['name']) ?></label>
              <textarea name="reason" rows="3" required style="width:100%;padding:10px;border-radius:8px;border:1px solid #ccc;font:inherit;"></textarea>
            </div>
            <button type="submit" class="btn">Send Request</button>
          </form>
        </div>
      <?php endif; ?>
    <?php endif; ?>

    <?php if ($isOwnProfile): ?>
    <div class="auth-card" style="max-width:480px;">
      <h1 style="font-size:18px;">Edit Contact Info</h1>
      <form method="POST" novalidate>
        <input type="hidden" name="form_action" value="edit_profile">
        <div class="field">
          <label>Phone number</label>
          <input type="tel" name="phone" value="<?= htmlspecialchars($me['phone']) ?>" <?= $phoneLockDays > 0 ? 'disabled' : '' ?>>
          <?php if ($phoneLockDays > 0): ?>
            <div class="sub" style="margin-top:4px;">Locked — you can change this again in <?= $phoneLockDays ?> day(s).</div>
          <?php endif; ?>
          <label style="display:flex;align-items:center;gap:8px;margin-top:8px;font-weight:400;">
            <input type="checkbox" name="hide_contact" value="1" <?= $me['hide_contact'] ? 'checked' : '' ?> style="width:auto;">
            Hide my phone number from same-level peers
          </label>
        </div>
        <div class="field">
          <label>Email</label>
          <input type="email" name="email" value="<?= htmlspecialchars($me['email']) ?>" <?= $emailLockDays > 0 ? 'disabled' : '' ?>>
          <?php if ($emailLockDays > 0): ?>
            <div class="sub" style="margin-top:4px;">Locked — you can change this again in <?= $emailLockDays ?> day(s).</div>
          <?php endif; ?>
          <label style="display:flex;align-items:center;gap:8px;margin-top:8px;font-weight:400;">
            <input type="checkbox" name="hide_email" value="1" <?= $me['hide_email'] ? 'checked' : '' ?> style="width:auto;">
            Hide my email from same-level peers
          </label>
        </div>
        <button type="submit" class="btn">Save changes</button>
      </form>
    </div>

    <?php if (!empty($myPendingRequests)): ?>
    <div style="margin-top:40px;max-width:700px;">
      <h1 style="font-size:18px;">Contact Requests</h1>
      <table class="admin-table" style="margin-top:12px;">
        <thead>
          <tr><th>From</th><th>Reason</th><th>Submitted</th><th>Actions</th></tr>
        </thead>
        <tbody>
          <?php foreach ($myPendingRequests as $req): ?>
          <tr>
            <td><?= htmlspecialchars($req['requester_name']) ?> <span class="mono" style="color:var(--muted);">(<?= htmlspecialchars($req['requester_emp_no']) ?>)</span></td>
            <td><?= htmlspecialchars($req['reason']) ?></td>
            <td class="mono" style="color:var(--muted);font-size:12.5px;"><?= htmlspecialchars($req['requested_at']) ?></td>
            <td>
              <form method="POST" style="display:inline-flex;gap:6px;align-items:center;">
                <input type="hidden" name="form_action" value="approve_contact">
                <input type="hidden" name="request_id" value="<?= $req['id'] ?>">
                <select name="share_field" style="padding:4px;border-radius:6px;">
                  <option value="phone">Phone</option>
                  <option value="email">Email</option>
                  <option value="both">Both</option>
                </select>
                <button type="submit" class="mini-btn accept">Approve</button>
              </form>
              <form method="POST" style="display:inline;">
                <input type="hidden" name="form_action" value="decline_contact">
                <input type="hidden" name="request_id" value="<?= $req['id'] ?>">
                <button type="submit" class="mini-btn reject">Decline</button>
              </form>
            </td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <?php endif; ?>

    <?php else: ?>
      <a href="index.php" class="btn btn-outline" style="display:inline-block;text-decoration:none;">← Back to Directory</a>
    <?php endif; ?>

  </div>
</div>
</body>
</html>
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

$myRole = $me['role'];
$employees = [];

if ($myRole === 'employee') {
    // All employees, company-wide
    $stmt = $conn->prepare("SELECT * FROM users WHERE role = 'employee' ORDER BY name ASC");
    $stmt->execute();
    $res = $stmt->get_result();
    while ($r = $res->fetch_assoc()) { $employees[] = $r; }
    $stmt->close();

    // Only managers in MY OWN department
    $stmt = $conn->prepare("SELECT * FROM users WHERE role = 'manager' AND department = ? ORDER BY name ASC");
    $stmt->bind_param('s', $me['department']);
    $stmt->execute();
    $res = $stmt->get_result();
    while ($r = $res->fetch_assoc()) { $employees[] = $r; }
    $stmt->close();
} else {
    $visibleRoles = ($myRole === 'manager') ? ['employee', 'manager'] : ['employee', 'manager', 'senior'];
    $placeholders = implode(',', array_fill(0, count($visibleRoles), '?'));
    $types = str_repeat('s', count($visibleRoles));
    $stmt = $conn->prepare("SELECT * FROM users WHERE role IN ($placeholders) ORDER BY name ASC");
    $stmt->bind_param($types, ...$visibleRoles);
    $stmt->execute();
    $res = $stmt->get_result();
    while ($r = $res->fetch_assoc()) { $employees[] = $r; }
    $stmt->close();
}

usort($employees, fn($a, $b) => strcmp($a['name'], $b['name']));

// Approved contact-request grants belonging to ME (requester), keyed by target user id
$approvedGrants = [];
$stmt = $conn->prepare("SELECT target_id, shared_field FROM contact_requests WHERE requester_id = ? AND status = 'approved'");
$stmt->bind_param('i', $myId);
$stmt->execute();
$res = $stmt->get_result();
while ($r = $res->fetch_assoc()) { $approvedGrants[$r['target_id']] = $r['shared_field']; }
$stmt->close();

function fieldVisible($viewer, $target, $field, $approvedGrants) {
    $hideCol = ($field === 'phone') ? 'hide_contact' : 'hide_email';

    // New rule: employees can't see manager/senior contact info unless approved via a contact request
    if ($viewer['role'] === 'employee' && in_array($target['role'], ['manager', 'senior']) && $target['id'] != $viewer['id']) {
        $grant = $approvedGrants[$target['id']] ?? null;
        return ($grant === 'both' || $grant === $field);
    }

    // Existing peer-level hide rule
    if ($target['id'] == $viewer['id']) return true;
    if (!$target[$hideCol]) return true;
    if ($target['role'] === 'employee' && $viewer['role'] === 'employee') return false;
    if ($target['role'] === 'senior' && $viewer['role'] === 'senior') return false;
    return true;
}

$roomDept   = $me['department'];
$deptSlug   = strtolower(preg_replace('/\s+/', '_', $roomDept));
$deptRoomFile = $deptSlug . '.php';
$deptAnnFile  = $deptSlug . '_announcements.php';

$deptOptions = array_values(array_unique(array_map(fn($e) => $e['department'], $employees)));
sort($deptOptions);
$visibleRolesForFilter = array_values(array_unique(array_map(fn($e) => $e['role'], $employees)));
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Directory · BlazePlus</title>
<link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
<div class="app-shell">
  <div class="sidebar">
    <div>
      <div class="brand">Blaze<span>Plus</span></div>
      <div class="nav-section-label">Workspace</div>
      <a href="#directory" class="nav-item active"><span class="label">Directory</span></a>
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
        <h1 id="directory">Employee Directory</h1>
        <div class="sub">You're viewing as <?= ucfirst($myRole) ?> — <span id="visibleCount"><?= count($employees) ?></span> people visible to you.</div>
      </div>
    </div>

    <div class="filters-bar" style="display:flex;gap:10px;margin-bottom:20px;">
      <input type="text" class="search-bar" id="searchInput" placeholder="Search by name or department…" style="flex:1;margin-bottom:0;">
      <select id="deptFilter" class="search-bar" style="width:180px;margin-bottom:0;">
        <option value="">All departments</option>
        <?php foreach ($deptOptions as $d): ?>
          <option value="<?= strtolower(htmlspecialchars($d)) ?>"><?= htmlspecialchars($d) ?></option>
        <?php endforeach; ?>
      </select>
      <select id="roleFilter" class="search-bar" style="width:160px;margin-bottom:0;">
        <option value="">All roles</option>
        <?php foreach ($visibleRolesForFilter as $r): ?>
          <option value="<?= $r ?>"><?= ucfirst($r) ?></option>
        <?php endforeach; ?>
      </select>
    </div>

    <div class="directory-grid" id="grid">
      <?php foreach ($employees as $emp): ?>
        <?php $canSeePhone = fieldVisible($me, $emp, 'phone', $approvedGrants); ?>
        <div class="id-card"
             data-name="<?= strtolower(htmlspecialchars($emp['name'])) ?>"
             data-dept="<?= strtolower(htmlspecialchars($emp['department'])) ?>"
             data-role="<?= strtolower(htmlspecialchars($emp['role'])) ?>">
          <div class="card-role"><?= ucfirst($emp['role']) ?></div>
          <h3><?= htmlspecialchars($emp['name']) ?><?= $emp['id'] == $me['id'] ? ' (You)' : '' ?></h3>
          <div class="dept"><?= htmlspecialchars($emp['department']) ?></div>
          <div class="row"><span class="k">ID</span> <?= htmlspecialchars($emp['emp_no']) ?></div>
          <?php if ($canSeePhone): ?>
            <div class="row"><span class="k">Phone</span> <?= htmlspecialchars($emp['phone']) ?></div>
          <?php else: ?>
            <div class="row locked">Contact hidden</div>
          <?php endif; ?>
          <a href="profile.php?id=<?= $emp['id'] ?>" class="view-link">View details → (profile.php)</a>
        </div>
      <?php endforeach; ?>
    </div>
  </div>
</div>

<script>
function applyFilters() {
  const q = document.getElementById('searchInput').value.toLowerCase();
  const dept = document.getElementById('deptFilter').value;
  const role = document.getElementById('roleFilter').value;
  let visible = 0;

  document.querySelectorAll('#grid .id-card').forEach(card => {
    const matchesSearch = card.dataset.name.includes(q) || card.dataset.dept.includes(q);
    const matchesDept = !dept || card.dataset.dept === dept;
    const matchesRole = !role || card.dataset.role === role;
    const show = matchesSearch && matchesDept && matchesRole;
    card.style.display = show ? '' : 'none';
    if (show) visible++;
  });

  document.getElementById('visibleCount').textContent = visible;
}

document.getElementById('searchInput').addEventListener('input', applyFilters);
document.getElementById('deptFilter').addEventListener('change', applyFilters);
document.getElementById('roleFilter').addEventListener('change', applyFilters);
</script>
</body>
</html>
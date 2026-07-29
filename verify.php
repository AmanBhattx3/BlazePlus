<?php
require_once 'config.php';

if (($_SESSION['user_type'] ?? '') !== 'unverified' || empty($_SESSION['unverified_id'])) {
    header('Location: login.php');
    exit;
}

$id = $_SESSION['unverified_id'];

// If already submitted, go straight to waiting
$stmt = $conn->prepare("SELECT emp_no, name FROM unverified WHERE id = ?");
$stmt->bind_param('i', $id);
$stmt->execute();
$row = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$row) { header('Location: login.php'); exit; }
if ($row['emp_no'] !== null) { header('Location: waiting.php'); exit; }

$errors = [];
$departments = ['IT', 'HR', 'Finance', 'Sales', 'Operations', 'Marketing'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $emp_no = trim($_POST['emp_no'] ?? '');
    $dob = trim($_POST['dob'] ?? '');
    $department = trim($_POST['department'] ?? '');
    $role = trim($_POST['role'] ?? '');

    if ($emp_no === '' || $dob === '' || $department === '' || $role === '') {
        $errors[] = 'Please fill in all fields.';
    }
    if (!in_array($role, ['employee', 'manager', 'senior'])) {
        $errors[] = 'Select a valid role.';
    }
    if (!in_array($department, $departments)) {
        $errors[] = 'Select a valid department.';
    }

    if (empty($errors)) {
        $stmt = $conn->prepare("UPDATE unverified SET emp_no=?, dob=?, department=?, role=?, verify_submitted_at=NOW() WHERE id=?");
        $stmt->bind_param('ssssi', $emp_no, $dob, $department, $role, $id);
        if ($stmt->execute()) {
            header('Location: waiting.php');
            exit;
        } else {
            $errors[] = 'Something went wrong. Please try again.';
        }
        $stmt->close();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Verify Details · BlazePlus</title>
<link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
<div class="auth-shell">
  <div class="auth-side">
    <div class="brand">Blaze<span>Plus</span></div>
    <div class="pitch">
      <h2>Hey <?= htmlspecialchars($row['name']) ?>, let's confirm who you are.</h2>
      <p>This information goes straight to your admin for review — it's what lets them verify you belong here.</p>
    </div>
    <div class="badge-strip">
      <span>STEP 2 OF 2</span>
    </div>
  </div>
  <div class="auth-form-wrap">
    <div class="auth-card">
      <h1>Confirm your details</h1>
      <p class="sub">Once submitted, your account enters the review queue.</p>

      <?php foreach ($errors as $e): ?>
        <div class="alert alert-error"><?= htmlspecialchars($e) ?></div>
      <?php endforeach; ?>

      <form method="POST" novalidate>
        <div class="field">
          <label>Employee number</label>
          <input type="text" name="emp_no" value="<?= htmlspecialchars($_POST['emp_no'] ?? '') ?>" required>
        </div>
        <div class="field">
          <label>Date of birth</label>
          <input type="date" name="dob" value="<?= htmlspecialchars($_POST['dob'] ?? '') ?>" required>
        </div>
        <div class="field">
          <label>Department</label>
          <select name="department" required>
            <option value="">Select department</option>
            <?php foreach ($departments as $d): ?>
              <option value="<?= $d ?>" <?= (($_POST['department'] ?? '') === $d) ? 'selected' : '' ?>><?= $d ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="field">
          <label>Role</label>
          <select name="role" required>
            <option value="">Select role</option>
            <option value="employee" <?= (($_POST['role'] ?? '') === 'employee') ? 'selected' : '' ?>>Employee</option>
            <option value="manager" <?= (($_POST['role'] ?? '') === 'manager') ? 'selected' : '' ?>>Manager</option>
            <option value="senior" <?= (($_POST['role'] ?? '') === 'senior') ? 'selected' : '' ?>>Senior</option>
          </select>
        </div>
        <p class="sub" style="margin:-6px 0 16px;">Note: department and role can't be changed later — they set which chat rooms you belong to.</p>
        <button type="submit" class="btn">Submit for review</button>
      </form>
    </div>
  </div>
</div>
</body>
</html>

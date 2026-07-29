<?php
require_once 'config.php';

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm = $_POST['confirm_password'] ?? '';

    if ($name === '' || $email === '' || $phone === '' || $password === '' || $confirm === '') {
        $errors[] = 'Please fill in all fields.';
    }
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Enter a valid email address.';
    }
    if (!preg_match('/^[0-9]{10}$/', $phone)) {
        $errors[] = 'Enter a valid 10-digit phone number.';
    }
    if ($password !== $confirm) {
        $errors[] = 'Passwords do not match.';
    }
    if (strlen($password) < 6) {
        $errors[] = 'Password must be at least 6 characters.';
    }

    if (empty($errors)) {
        // Check if email already exists anywhere in the pipeline
        $stmt = $conn->prepare("SELECT id FROM unverified WHERE email = ? UNION SELECT id FROM users WHERE email = ?");
        $stmt->bind_param('ss', $email, $email);
        $stmt->execute();
        if ($stmt->get_result()->num_rows > 0) {
            $errors[] = 'An account with this email already exists.';
        }
        $stmt->close();
    }

    if (empty($errors)) {
        $hash = password_hash($password, PASSWORD_DEFAULT);
        $stmt = $conn->prepare("INSERT INTO unverified (name, email, phone, password) VALUES (?, ?, ?, ?)");
        $stmt->bind_param('ssss', $name, $email, $phone, $hash);
        if ($stmt->execute()) {
            header('Location: login.php?signup=success');
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
<title>Sign Up · BlazePlus</title>
<link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
<div class="auth-shell">
  <div class="auth-side">
    <div class="brand">Blaze<span>Plus</span></div>
    <div class="pitch">
      <h2>The employee directory your company was missing.</h2>
      <p>No more "contact xyz for his number." Search, connect, and know who's who — verified by your admin, visible on your terms.</p>
    </div>
    <div class="badge-strip">
      <span>DIRECTORY</span>
      <span>·</span>
      <span>DEPT ROOMS</span>
      <span>·</span>
      <span>VERIFIED ACCESS</span>
    </div>
  </div>
  <div class="auth-form-wrap">
    <div class="auth-card">
      <h1>Create your account</h1>
      <p class="sub">Step 1 of 2 — after this you'll confirm your employee details.</p>

      <?php foreach ($errors as $e): ?>
        <div class="alert alert-error"><?= htmlspecialchars($e) ?></div>
      <?php endforeach; ?>

      <form method="POST" novalidate>
        <div class="field">
          <label>Full name</label>
          <input type="text" name="name" value="<?= htmlspecialchars($_POST['name'] ?? '') ?>" required>
        </div>
        <div class="field">
          <label>Email</label>
          <input type="email" name="email" value="<?= htmlspecialchars($_POST['email'] ?? '') ?>" required>
        </div>
        <div class="field">
          <label>Phone number</label>
          <input type="tel" name="phone" value="<?= htmlspecialchars($_POST['phone'] ?? '') ?>" placeholder="10 digit number" required>
        </div>
        <div class="field-row">
          <div class="field">
            <label>Password</label>
            <input type="password" name="password" required>
          </div>
          <div class="field">
            <label>Confirm password</label>
            <input type="password" name="confirm_password" required>
          </div>
        </div>
        <button type="submit" class="btn">Create account</button>
      </form>
      <p class="foot-link">Already have an account? <a href="login.php">Log in</a></p>
    </div>
  </div>
</div>
</body>
</html>

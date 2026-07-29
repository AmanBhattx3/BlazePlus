<?php
define('ADMIN_CONTEXT', true);
require_once '../config.php';

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    $stmt = $conn->prepare("SELECT id, name, password FROM admins WHERE username = ?");
    $stmt->bind_param('s', $username);
    $stmt->execute();
    $admin = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if ($admin && password_verify($password, $admin['password'])) {
        $_SESSION['user_type'] = 'admin';
        $_SESSION['admin_id'] = $admin['id'];
        $_SESSION['admin_name'] = $admin['name'];
        header('Location: dashboard.php');
        exit;
    } else {
        $errors[] = 'Invalid admin credentials.';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Admin Login · BlazePlus</title>
<link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>
<div class="auth-shell">
  <div class="auth-side">
    <div class="brand">Blaze<span>Plus</span></div>
    <div class="pitch">
      <h2>Admin console</h2>
      <p>Review the queue, verify identities, and manage access. No restrictions here.</p>
    </div>
    <div class="badge-strip"><span>ADMIN ACCESS</span></div>
  </div>
  <div class="auth-form-wrap">
    <div class="auth-card">
      <h1>Admin log in</h1>
      <p class="sub">This is a separate login from regular employee accounts.</p>

      <?php foreach ($errors as $e): ?>
        <div class="alert alert-error"><?= htmlspecialchars($e) ?></div>
      <?php endforeach; ?>

      <form method="POST" novalidate>
        <div class="field">
          <label>Username</label>
          <input type="text" name="username" required>
        </div>
        <div class="field">
          <label>Password</label>
          <input type="password" name="password" required>
        </div>
        <button type="submit" class="btn">Log in</button>
      </form>
      <p class="foot-link"><a href="../login.php">← Back to employee login</a></p>
    </div>
  </div>
</div>
</body>
</html>

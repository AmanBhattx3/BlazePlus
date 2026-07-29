<?php
require_once 'config.php';

$errors = [];
$success = isset($_GET['signup']) ? 'Account created. Log in to continue with verification.' : null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($email === '' || $password === '') {
        $errors[] = 'Enter both email and password.';
    } else {
        // 1. Check banned table first
        $stmt = $conn->prepare("SELECT reason FROM banned WHERE email = ? ORDER BY banned_at DESC LIMIT 1");
        $stmt->bind_param('s', $email);
        $stmt->execute();
        $bannedRow = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if ($bannedRow) {
            $errors[] = 'You are banned. Please contact xyz@gmail.com for more details.';
        } else {
            // 2. Check verified users table
            $stmt = $conn->prepare("SELECT id, password FROM users WHERE email = ?");
            $stmt->bind_param('s', $email);
            $stmt->execute();
            $userRow = $stmt->get_result()->fetch_assoc();
            $stmt->close();

            if ($userRow && password_verify($password, $userRow['password'])) {
                $_SESSION['user_type'] = 'user';
                $_SESSION['user_id'] = $userRow['id'];
                header('Location: index.php');
                exit;
            } else {
                // 3. Check unverified table (pre-verify or waiting on admin)
                $stmt = $conn->prepare("SELECT id, password, emp_no FROM unverified WHERE email = ?");
                $stmt->bind_param('s', $email);
                $stmt->execute();
                $unRow = $stmt->get_result()->fetch_assoc();
                $stmt->close();

                if ($unRow && password_verify($password, $unRow['password'])) {
                    $_SESSION['user_type'] = 'unverified';
                    $_SESSION['unverified_id'] = $unRow['id'];
                    $_SESSION['check_email'] = $email;
                    if ($unRow['emp_no'] === null) {
                        header('Location: verify.php');
                    } else {
                        header('Location: waiting.php');
                    }
                    exit;
                } else {
                    $errors[] = 'Incorrect email or password.';
                }
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Log In · BlazePlus</title>
<link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
<div class="auth-shell">
  <div class="auth-side">
    <div class="brand">Blaze<span>Plus</span></div>
    <div class="pitch">
      <h2>Welcome back.</h2>
      <p>Pick up where you left off — whether that's finishing verification or jumping into your directory.</p>
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
      <h1>Log in</h1>
      <p class="sub">Enter your credentials to continue.</p>

      <?php if ($success): ?><div class="alert alert-ok"><?= htmlspecialchars($success) ?></div><?php endif; ?>
      <?php foreach ($errors as $e): ?>
        <div class="alert alert-error"><?= htmlspecialchars($e) ?></div>
      <?php endforeach; ?>

      <form method="POST" novalidate>
        <div class="field">
          <label>Email</label>
          <input type="email" name="email" value="<?= htmlspecialchars($_POST['email'] ?? '') ?>" required>
        </div>
        <div class="field">
          <label>Password</label>
          <input type="password" name="password" required>
        </div>
        <button type="submit" class="btn">Log in</button>
      </form>
      <p class="foot-link">New here? <a href="signup.php">Create an account</a></p>
      <p class="foot-link"><a href="admin/login.php">Admin login →</a></p>
    </div>
  </div>
</div>
</body>
</html>
<?php
// ---- BlazePlus DB Config ----
// Copy this file to config.php and update these to match your local MySQL setup
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'blazeplus');

// How long a submitted verify.php request waits before auto-delete
// (in minutes). Spec says 5 min for now, 24hr (1440) later.
define('VERIFY_TIMEOUT_MINUTES', 5);

date_default_timezone_set('Asia/Kolkata');

$conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
if ($conn->connect_error) {
    die('Database connection failed: ' . $conn->connect_error);
}

if (session_status() === PHP_SESSION_NONE) {
    // Admin pages get their own session cookie (set via ADMIN_CONTEXT before
    // including this file) so an admin logging in on one tab can't overwrite
    // an employee's session on another tab in the same browser.
    if (defined('ADMIN_CONTEXT')) {
        session_name('blazeplus_admin_sess');
    }
    session_start();
}

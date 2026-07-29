<?php
define('ADMIN_CONTEXT', true);
require_once '../config.php';
session_destroy();
header('Location: login.php');
exit;
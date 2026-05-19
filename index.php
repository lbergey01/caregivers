<?php
if (file_exists("install/index.php")) {
	header("Location: install/index.php");
}
require_once 'users/init.php';

// Logged-in users go straight to the schedule.
if ($user->isLoggedIn()) {
    header('Location: ' . $us_url_root . 'cg/index.php');
    exit;
}

// Default entry point is passwordless SMS login. Admins can still use the
// password form via the "Sign in with password instead" link on secure_login.php.
header('Location: ' . $us_url_root . 'secure_login.php');
exit;

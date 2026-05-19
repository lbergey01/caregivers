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

require_once $abs_us_root . $us_url_root . 'users/includes/template/prep.php';
?>
<main>
	<div class="px-4 py-5 my-5 bg-light text-center">
		<h1><?php echo $settings->site_name; ?></h1>
		<p class="text-muted">24/7 Care Scheduling</p>
		<p class="my-4">
			<a class="btn btn-warning mr-3 me-3" href="users/login.php" role="button"><span class="fa fa-sign-in mr-2 me-2"></span><?= lang("SIGNIN_TEXT"); ?></a>
			<a class="btn btn-info" href="users/join.php" role="button"><span class="fa fa-user-plus mr-2 me-2"></span><?= lang("SIGNUP_TEXT"); ?></a>
		</p>
	</div>
	<?php languageSwitcher(); ?>
</main>

<!-- Place any per-page javascript here -->
<?php require_once $abs_us_root . $us_url_root . 'users/includes/html_footer.php'; ?>
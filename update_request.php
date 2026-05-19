<?php
require_once 'users/init.php';
$patchfile='index.php';

// Logged-in users go straight to the schedule.

if(!isset($user) || !$user->isLoggedIn()){
    Redirect::to('users/login.php');
    die();
}

if($user->data()->id > 3){
    Redirect::to('index.php');
    die();
}
// 1. Clean up any stale files from previous runs
if (file_exists("_git_update.php")) { unlink("_git_update.php"); }
if (file_exists("updatedone.php")) { unlink("updatedone.php"); }

// If this is a Windows server, redirect to the patch page immediately
if (strpos($_SERVER['SERVER_SOFTWARE'], 'Win') == true) { 
    Redirect::to($patchfile); 
    die();
}

// 2. CONFIGURATION: Tell the backend script which local Git repo to pull
// (Change "taxsystem" to match the folder name inside /_git_programs/)
$repo_folder = "vbs"; 
$branch = "main";

// 3. GENERATE SECURE TRIGGER: Armor-plate with an immediate PHP die()
$update = fopen("_git_update.php", "w");
$payload = "<?php printf('Access Denied'); die(); ?>\n";
$payload .= "\$repo_folder = \"$repo_folder\";\n";
$payload .= "\$branch = \"$branch\";\n";
fwrite($update, $payload);
fclose($update);

echo "Update Requested for repository [$repo_folder]...<br>";
ob_flush();
flush();
echo "Please wait for the universal updater to complete...<br>";
ob_flush();
flush();

$filePath = 'updatedone.php';
$maxAttempts = 90; // Maximum time to wait in seconds
$attempts = 0;

// 4. POLLING LOOP: Wait for the Bash script to write the results file
while (!file_exists($filePath) && $attempts < $maxAttempts) {
    sleep(1);
    $attempts++;
}

if (file_exists($filePath)) {
    // Read the raw text log from the file
    $fileContent = file_get_contents($filePath);
    
    // Strip away the PHP protection line so it doesn't clutter the admin UI
    $logLines = explode("\n", $fileContent);
    if (isset($logLines[0]) && strpos($logLines[0], '<?php') !== false) {
        array_shift($logLines); // Removes the security line
    }
    $cleanContent = implode("\n", $logLines);
    $fileContentWithBreaks = nl2br(htmlspecialchars($cleanContent));
    
    // Render the terminal output safely inside a code box
    echo "<div style='background:#1e1e1e; color:#fff; padding:15px; font-family:monospace; border-radius:5px; margin:20px 0;'>$fileContentWithBreaks</div>";
    
    // Clean up the log file immediately for security
    unlink("updatedone.php");
    
    echo "<br>Update Completed Successfully...<br>";
    ob_flush();
    flush();
    echo "Please wait for the page to refresh to apply database changes...<br>";
    ob_flush();
    flush();
    sleep(4);
    
    // Redirect to the patch engine to complete the UserSpice updates
    Redirect::to($patchfile);
} else {
    echo "<strong style='color:red;'>Update Request Timed out. The background service might not be running.</strong>";
}
?>
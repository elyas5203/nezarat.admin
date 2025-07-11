<?php
// user/auth/logout.php
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Unset all of the session variables for the user.
// Avoid unsetting admin session variables if they might co-exist (though unlikely with separate logins)
// Specifically target user session variables if necessary.
// For simplicity here, we assume a full logout.
$_SESSION = array();


if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}

session_destroy();

// Determine the correct path to login.php for users
// Assuming 'my_site' is the root directory in htdocs
$login_url = '/my_site/user/auth/login.php';

header("Location: " . $login_url . "?status=loggedout");
exit;
?>


<?php
session_start();

/*
=====================================================
NORSU REGISTRAR SYSTEM
SECURE ADMIN LOGOUT

File Name:
admin_logout.php

Purpose:
- Securely log out the Administrator
- Clear all session variables
- Destroy the login session
- Delete the session cookie
- Redirect to the administrator login page
=====================================================
*/


// =====================================================
// CLEAR ALL SESSION VARIABLES
// =====================================================

$_SESSION = [];


// =====================================================
// DELETE SESSION COOKIE
// =====================================================

if (ini_get("session.use_cookies")) {

    $params = session_get_cookie_params();

    setcookie(
        session_name(),
        '',
        time() - 42000,
        $params["path"],
        $params["domain"],
        $params["secure"],
        $params["httponly"]
    );
}


// =====================================================
// DESTROY SESSION
// =====================================================

session_destroy();


// =====================================================
// REDIRECT TO LOGIN PAGE
//
// Change "login.php" to the actual filename
// of your NORSU Administrator login page.
// =====================================================

header("Location: log_in.php");
exit();

?>

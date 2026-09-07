```php
<?php

session_start();

/*
=========================================================
    NORSU REGISTRAR SYSTEM
    LOGIN + CLIENT SIGN UP

    File Name:
    log_in.php

    Database:
    haha

    FEATURES:
    - Automatic database table creation
    - Automatic default admin account
    - Admin Login
    - Client Login
    - Client Sign Up
    - Password Hashing
    - Show / Hide Password
    - Role-Based Redirect
    - NORSU Logo
    - Blue, White, Red, Yellow Theme

    DEFAULT ADMIN ACCOUNT:

    Email:
    admin@carcuevas.com

    Password:
    admin123

    IMPORTANT:
    Change the default password after first login.
=========================================================
*/


// =====================================================
// DATABASE CONNECTION
// =====================================================

$host = "localhost";
$dbUsername = "root";
$dbPassword = "";
$database = "haha";


// Connect first without selecting a database so the program can
// create the database automatically if it does not exist.
$conn = new mysqli(
    $host,
    $dbUsername,
    $dbPassword
);

if ($conn->connect_error) {
    die("Database Connection Failed: " . $conn->connect_error);
}

// Create the database automatically if it does not exist.
$databaseSafe = "`" . str_replace("`", "``", $database) . "`";

if (!$conn->query(
    "CREATE DATABASE IF NOT EXISTS " . $databaseSafe .
    " CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci"
)) {
    die("Unable to create database: " . $conn->error);
}

if (!$conn->select_db($database)) {
    die("Unable to select database '" . htmlspecialchars($database, ENT_QUOTES, "UTF-8") . "': " . $conn->error);
}

$conn->set_charset("utf8mb4");


// =====================================================
// CREATE USERS TABLE
// =====================================================

$createTable = "

CREATE TABLE IF NOT EXISTS users (

    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,

    full_name VARCHAR(150) NOT NULL,

    email VARCHAR(150) NOT NULL UNIQUE,

    password VARCHAR(255) NOT NULL,

    role ENUM('admin', 'student')
        NOT NULL DEFAULT 'student',

    created_at TIMESTAMP
        DEFAULT CURRENT_TIMESTAMP

)

";


if (!$conn->query($createTable)) {

    die(
        "Unable to create users table: "
        . $conn->error
    );

}


// =====================================================
// DEFAULT ADMIN INFORMATION
// =====================================================

$adminName =
    "NORSU Administrator";

$adminEmail =
    "admin@carcuevas.com";

$adminPassword =
    "admin123";

$adminRole =
    "admin";


// =====================================================
// CREATE DEFAULT ADMIN IF NOT EXISTS
// =====================================================

$checkAdminSql = "

    SELECT id

    FROM users

    WHERE email = ?

    LIMIT 1

";


$checkAdminStmt =
    $conn->prepare(
        $checkAdminSql
    );


if ($checkAdminStmt) {

    $checkAdminStmt->bind_param(
        "s",
        $adminEmail
    );

    $checkAdminStmt->execute();

    $adminResult =
        $checkAdminStmt->get_result();


    // =================================================
    // ADMIN DOES NOT EXIST
    // =================================================

    if (
        $adminResult->num_rows === 0
    ) {

        $hashedAdminPassword =
            password_hash(
                $adminPassword,
                PASSWORD_DEFAULT
            );


        $insertAdminSql = "

            INSERT INTO users
            (
                full_name,
                email,
                password,
                role
            )

            VALUES
            (
                ?,
                ?,
                ?,
                'admin'
            )

        ";


        $insertAdminStmt =
            $conn->prepare(
                $insertAdminSql
            );


        if ($insertAdminStmt) {

            $insertAdminStmt->bind_param(
                "sss",
                $adminName,
                $adminEmail,
                $hashedAdminPassword
            );


            $insertAdminStmt->execute();

            $insertAdminStmt->close();

        }

    }


    $checkAdminStmt->close();

}


// =====================================================
// VARIABLES
// =====================================================

$error = "";

$success = "";

$activeForm = "login";


// =====================================================
// DETERMINE FORM
// =====================================================

if (
    isset($_POST["action"]) &&
    $_POST["action"] === "register"
) {

    $activeForm = "register";

}


// =====================================================
// LOGIN PROCESS
// =====================================================

if (
    $_SERVER["REQUEST_METHOD"] === "POST" &&
    ($_POST["action"] ?? "") === "login"
) {

    $email =
        trim(
            $_POST["email"] ?? ""
        );

    $passwordInput =
        $_POST["password"] ?? "";


    // =================================================
    // VALIDATION
    // =================================================

    if (
        $email === "" ||
        $passwordInput === ""
    ) {

        $error =
            "Please enter your email and password.";

    } elseif (
        !filter_var(
            $email,
            FILTER_VALIDATE_EMAIL
        )
    ) {

        $error =
            "Please enter a valid email address.";

    } else {


        // =============================================
        // FIND USER
        // =============================================

        $sql = "

            SELECT
                id,
                full_name,
                email,
                password,
                role

            FROM users

            WHERE email = ?

            LIMIT 1

        ";


        $stmt =
            $conn->prepare($sql);


        if (!$stmt) {

            $error =
                "Unable to process login. Please try again.";

        } else {

            $stmt->bind_param(
                "s",
                $email
            );

            $stmt->execute();

            $result =
                $stmt->get_result();


            // =========================================
            // USER FOUND
            // =========================================

            if (
                $result->num_rows === 1
            ) {

                $user =
                    $result->fetch_assoc();


                // =====================================
                // VERIFY PASSWORD
                // =====================================

                if (
                    password_verify(
                        $passwordInput,
                        $user["password"]
                    )
                ) {

                    session_regenerate_id(true);


                    // =================================
                    // SESSION DATA
                    // =================================

                    $_SESSION["logged_in"] =
                        true;

                    $_SESSION["user_id"] =
                        $user["id"];

                    $_SESSION["full_name"] =
                        $user["full_name"];

                    $_SESSION["email"] =
                        $user["email"];

                    $_SESSION["role"] =
                        $user["role"];


                    // =================================
                    // ADMIN REDIRECT
                    // =================================

                    if (
                        strtolower(
                            trim(
                                $user["role"]
                            )
                        ) === "admin"
                    ) {

                        header(
                            "Location: admin_dashboard.php"
                        );

                        exit;

                    }


                    // =================================
                    // CLIENT REDIRECT
                    // =================================

                    header(
                        "Location: cdashboard.php"
                    );

                    exit;

                } else {

                    $error =
                        "Incorrect email or password.";

                }

            } else {

                $error =
                    "Incorrect email or password.";

            }


            $stmt->close();

        }

    }

}


// =====================================================
// CLIENT REGISTRATION
// =====================================================

if (
    $_SERVER["REQUEST_METHOD"] === "POST" &&
    ($_POST["action"] ?? "") === "register"
) {

    $fullName =
        trim(
            $_POST["full_name"] ?? ""
        );

    $email =
        trim(
            $_POST["register_email"] ?? ""
        );

    $passwordInput =
        $_POST["register_password"] ?? "";

    $confirmPassword =
        $_POST["confirm_password"] ?? "";


    $activeForm = "register";


    // =================================================
    // VALIDATION
    // =================================================

    if (
        $fullName === "" ||
        $email === "" ||
        $passwordInput === "" ||
        $confirmPassword === ""
    ) {

        $error =
            "Please complete all registration fields.";

    } elseif (
        strlen($fullName) < 2
    ) {

        $error =
            "Please enter your complete name.";

    } elseif (
        !filter_var(
            $email,
            FILTER_VALIDATE_EMAIL
        )
    ) {

        $error =
            "Please enter a valid email address.";

    } elseif (
        strlen($passwordInput) < 6
    ) {

        $error =
            "Password must contain at least 6 characters.";

    } elseif (
        $passwordInput !== $confirmPassword
    ) {

        $error =
            "Passwords do not match.";

    } else {


        // =============================================
        // CHECK EMAIL
        // =============================================

        $checkEmailSql = "

            SELECT id

            FROM users

            WHERE email = ?

            LIMIT 1

        ";


        $checkEmailStmt =
            $conn->prepare(
                $checkEmailSql
            );


        if (!$checkEmailStmt) {

            $error =
                "Unable to process registration.";

        } else {

            $checkEmailStmt->bind_param(
                "s",
                $email
            );

            $checkEmailStmt->execute();

            $emailResult =
                $checkEmailStmt->get_result();


            // =========================================
            // EMAIL ALREADY EXISTS
            // =========================================

            if (
                $emailResult->num_rows > 0
            ) {

                $error =
                    "That email address is already registered.";

            } else {


                // =====================================
                // HASH CLIENT PASSWORD
                // =====================================

                $hashedPassword =
                    password_hash(
                        $passwordInput,
                        PASSWORD_DEFAULT
                    );


                // =====================================
                // CREATE CLIENT
                // =====================================

                $insertUserSql = "

                    INSERT INTO users
                    (
                        full_name,
                        email,
                        password,
                        role
                    )

                    VALUES
                    (
                        ?,
                        ?,
                        ?,
                        'student'
                    )

                ";


                $insertUserStmt =
                    $conn->prepare(
                        $insertUserSql
                    );


                if (!$insertUserStmt) {

                    $error =
                        "Unable to create your account.";

                } else {

                    $insertUserStmt->bind_param(
                        "sss",
                        $fullName,
                        $email,
                        $hashedPassword
                    );


                    if (
                        $insertUserStmt->execute()
                    ) {

                        $success =
                            "Your account has been created successfully. You can now log in.";

                        $activeForm =
                            "login";

                    } else {

                        $error =
                            "Unable to create your account. Please try again.";

                    }


                    $insertUserStmt->close();

                }

            }


            $checkEmailStmt->close();

        }

    }

}


// =====================================================
// CLOSE DATABASE
// =====================================================

$conn->close();


// =====================================================
// HTML ESCAPE
// =====================================================

function e($value)
{

    return htmlspecialchars(
        $value,
        ENT_QUOTES,
        "UTF-8"
    );

}

?>

<!DOCTYPE html>

<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <title>
        NORSU Registrar System
    </title>


    <style>

/* NORSU COLOR PALETTE */
:root {
    --norsu-blue: #005baa;
    --norsu-red: #d71920;
    --norsu-yellow: #ffd200;
    --norsu-white: var(--norsu-white)fff;
    --norsu-light-blue: #eaf4ff;
    --norsu-light-yellow: var(--norsu-white)8cc;
}
/* =====================================================
   COLOR VARIABLES
===================================================== */

:root {

    --blue:
        #0057b8;

    --dark-blue:
        #003b7a;

    --light-blue:
        #eaf4ff;

    --red:
        #d71920;

    --dark-red:
        #a90000;

    --yellow:
        var(--norsu-yellow);

    --light-yellow:
        var(--norsu-white)8d6;

    --white:
        var(--norsu-white)fff;

    --gray:
        #f4f6f9;

    --dark:
        #222222;

    --text-gray:
        #666666;

    --border:
        #e5e5e5;
}

/* =====================================================
   RESET
===================================================== */

* {

    margin: 0;

    padding: 0;

    box-sizing: border-box;
}

body {

    margin: 0;

    min-height: 100vh;

    display: flex;

    justify-content: center;

    align-items: center;

    padding: 20px;

    box-sizing: border-box;

    font-family:
        Arial,
        Helvetica,
        sans-serif;

    background:
        var(--gray);

    color:
        var(--dark);
}

/* =====================================================
   SIDEBAR
===================================================== */

.sidebar {

    position: fixed;

    left: 0;

    top: 0;

    width: 265px;

    height: 100vh;

    background:
        linear-gradient(
            180deg,
            var(--blue),
            var(--dark-blue)
        );

    color:
        var(--white);

    overflow-y: auto;

    z-index: 1000;

    border-right:
        4px solid
        var(--yellow);
}

/* =====================================================
   SIDEBAR HEADER
===================================================== */

.sidebar-header {

    padding:
        25px 20px;

    text-align:
        center;

    background:
        var(--white);

    color:
        var(--blue);

    border-bottom:
        5px solid
        var(--red);
}

/* =====================================================
   NORSU SIDEBAR LOGO
   NO YELLOW CIRCLE
===================================================== */

.sidebar-logo {

    width:
        82px;

    height:
        82px;

    margin:
        0 auto 10px;

    display:
        block;

    object-fit:
        contain;

    background:
        transparent;

    border:
        none;

    border-radius:
        0;

    padding:
        0;

    box-shadow:
        none;
}

/* =====================================================
   SIDEBAR TITLE
===================================================== */

.sidebar-header h2 {

    font-size:
        22px;

    margin-bottom:
        5px;
}

.sidebar-header p {

    font-size:
        12px;

    color:
        var(--red);

    font-weight:
        bold;
}

/* =====================================================
   MENU
===================================================== */

.sidebar-menu {

    padding:
        15px 0;
}

.menu-title {

    padding:
        14px 20px 8px;

    font-size:
        11px;

    text-transform:
        uppercase;

    color:
        var(--yellow);

    font-weight:
        bold;

    letter-spacing:
        1px;
}

.sidebar-menu a {

    display:
        flex;

    align-items:
        center;

    gap:
        12px;

    padding:
        14px 20px;

    color:
        var(--white);

    text-decoration:
        none;

    font-size:
        14px;

    border-left:
        4px solid
        transparent;

    transition:
        all .2s;
}

.sidebar-menu a:hover {

    background:
        rgba(
            255,
            255,
            255,
            .12
        );

    border-left:
        4px solid
        var(--yellow);
}

.sidebar-menu a.active {

    background:
        var(--red);

    border-left:
        4px solid
        var(--yellow);

    font-weight:
        bold;
}

.icon {

    width:
        28px;

    text-align:
        center;

    font-size:
        17px;
}

/* =====================================================
   MAIN
===================================================== */

.main {

    margin-left:
        265px;

    min-height:
        100vh;
}

/* =====================================================
   TOPBAR
===================================================== */

.topbar {

    min-height:
        72px;

    background:
        var(--white);

    display:
        flex;

    align-items:
        center;

    justify-content:
        space-between;

    padding:
        10px 30px;

    box-shadow:
        0 2px 10px
        rgba(
            0,
            0,
            0,
            .10
        );

    border-bottom:
        4px solid
        var(--yellow);
}

/* =====================================================
   TOPBAR LEFT
===================================================== */

.topbar-left {

    display:
        flex;

    align-items:
        center;

    gap:
        15px;
}

/* =====================================================
   TOPBAR NORSU LOGO
   NO YELLOW CIRCLE
===================================================== */

.topbar-logo {

    width:
        48px;

    height:
        48px;

    object-fit:
        contain;

    background:
        transparent;

    border:
        none;

    border-radius:
        0;

    padding:
        0;

    box-shadow:
        none;
}

.topbar h1 {

    font-size:
        22px;

    color:
        var(--blue);
}

/* =====================================================
   ADMIN INFO
===================================================== */

.admin-info {

    display:
        flex;

    align-items:
        center;

    gap:
        12px;
}

.admin-details {

    text-align:
        right;
}

.admin-details strong {

    display:
        block;

    font-size:
        14px;

    color:
        var(--blue);
}

.admin-details span {

    font-size:
        11px;

    color:
        var(--text-gray);
}

/* =====================================================
   ADMIN AVATAR
   NO YELLOW BORDER
===================================================== */

.admin-avatar {

    width:
        42px;

    height:
        42px;

    border-radius:
        50%;

    background:
        var(--red);

    color:
        var(--white);

    border:
        none;

    display:
        flex;

    align-items:
        center;

    justify-content:
        center;

    font-weight:
        bold;
}

/* =====================================================
   CONTENT
===================================================== */

.content {

    padding:
        30px;
}

/* =====================================================
   WELCOME
===================================================== */

.welcome {

    background:
        var(--white);

    padding:
        25px;

    border-radius:
        12px;

    margin-bottom:
        25px;

    box-shadow:
        0 2px 8px
        rgba(
            0,
            0,
            0,
            .06
        );

    border-left:
        6px solid
        var(--blue);

    position:
        relative;

    overflow:
        hidden;
}

.welcome::after {

    content:
        "";

    position:
        absolute;

    right:
        -40px;

    top:
        -40px;

    width:
        120px;

    height:
        120px;

    border-radius:
        50%;

    background:
        var(--yellow);

    opacity:
        .25;
}

.welcome h2 {

    color:
        var(--blue);

    margin-bottom:
        7px;

    position:
        relative;

    z-index:
        1;
}

.welcome p {

    color:
        var(--text-gray);

    font-size:
        14px;

    position:
        relative;

    z-index:
        1;
}

/* =====================================================
   STAT GRID
===================================================== */

.stats-grid {

    display:
        grid;

    grid-template-columns:
        repeat(
            5,
            1fr
        );

    gap:
        18px;

    margin-bottom:
        30px;
}

/* =====================================================
   STAT CARD
===================================================== */

.stat-card {

    background:
        var(--white);

    border-radius:
        12px;

    padding:
        20px;

    box-shadow:
        0 2px 8px
        rgba(
            0,
            0,
            0,
            .06
        );

    position:
        relative;

    overflow:
        hidden;

    border-top:
        5px solid
        var(--blue);
}

.stat-card:nth-child(2) {

    border-top-color:
        var(--red);
}

.stat-card:nth-child(3) {

    border-top-color:
        var(--yellow);
}

.stat-card:nth-child(4) {

    border-top-color:
        var(--blue);
}

.stat-card:nth-child(5) {

    border-top-color:
        var(--red);
}

.stat-icon {

    width:
        45px;

    height:
        45px;

    display:
        flex;

    align-items:
        center;

    justify-content:
        center;

    border-radius:
        10px;

    background:
        var(--light-blue);

    font-size:
        22px;

    margin-bottom:
        12px;
}

.stat-card:nth-child(2)
.stat-icon {

    background:
        #ffe8e8;
}

.stat-card:nth-child(3)
.stat-icon {

    background:
        var(--light-yellow);
}

.stat-card h3 {

    font-size:
        30px;

    color:
        var(--blue);

    margin-bottom:
        5px;
}

.stat-card:nth-child(2) h3 {

    color:
        var(--red);
}

.stat-card:nth-child(3) h3 {

    color:
        #b78600;
}

.stat-card p {

    color:
        var(--text-gray);

    font-size:
        13px;
}

/* =====================================================
   PANELS
===================================================== */

.panel-grid {

    display:
        grid;

    grid-template-columns:
        2fr 1fr;

    gap:
        25px;
}

.panel {

    background:
        var(--white);

    border-radius:
        12px;

    box-shadow:
        0 2px 8px
        rgba(
            0,
            0,
            0,
            .06
        );

    overflow:
        hidden;
}

.panel-header {

    padding:
        18px 20px;

    border-bottom:
        1px solid
        var(--border);

    display:
        flex;

    justify-content:
        space-between;

    align-items:
        center;

    border-top:
        4px solid
        var(--blue);
}

.panel-header h3 {

    color:
        var(--blue);

    font-size:
        16px;
}

.panel-header a {

    color:
        var(--red);

    font-size:
        12px;

    text-decoration:
        none;

    font-weight:
        bold;
}

.panel-header a:hover {

    color:
        var(--blue);
}

.panel-body {

    padding:
        20px;
}

/* =====================================================
   TABLE
===================================================== */

.table-container {

    overflow-x:
        auto;
}

table {

    width:
        100%;

    border-collapse:
        collapse;
}

th {

    background:
        var(--blue);

    color:
        var(--white);

    text-align:
        left;

    padding:
        12px;

    font-size:
        12px;
}

td {

    padding:
        12px;

    border-top:
        1px solid
        var(--border);

    font-size:
        12px;

    color:
        #555;
}

tbody tr:hover {

    background:
        var(--light-blue);
}

/* =====================================================
   STATUS
===================================================== */

.status {

    display:
        inline-block;

    padding:
        5px 10px;

    border-radius:
        20px;

    font-size:
        10px;

    font-weight:
        bold;
}

.status-success {

    background:
        #dff5e4;

    color:
        #237a36;
}

.status-warning {

    background:
        var(--light-yellow);

    color:
        #856404;
}

.status-danger {

    background:
        #ffe0e0;

    color:
        var(--dark-red);
}

.status-default {

    background:
        #e9ecef;

    color:
        #555;
}

/* =====================================================
   QUICK ACTIONS
===================================================== */

.quick-actions {

    display:
        grid;

    grid-template-columns:
        repeat(
            2,
            1fr
        );

    gap:
        12px;
}

.quick-action {

    display:
        block;

    text-decoration:
        none;

    padding:
        18px;

    border:
        1px solid
        var(--border);

    border-radius:
        10px;

    color:
        var(--dark);

    transition:
        all .2s;

    border-left:
        4px solid
        var(--blue);
}

.quick-action:nth-child(2) {

    border-left-color:
        var(--red);
}

.quick-action:nth-child(3) {

    border-left-color:
        var(--yellow);
}

.quick-action:nth-child(4) {

    border-left-color:
        var(--blue);
}

.quick-action:hover {

    background:
        var(--light-blue);

    transform:
        translateY(-2px);

    box-shadow:
        0 4px 10px
        rgba(
            0,
            0,
            0,
            .08
        );
}

.qa-icon {

    font-size:
        24px;

    margin-bottom:
        8px;
}

.quick-action strong {

    display:
        block;

    font-size:
        13px;

    color:
        var(--blue);

    margin-bottom:
        3px;
}

.quick-action span {

    font-size:
        10px;

    color:
        var(--text-gray);
}

/* =====================================================
   SYSTEM SUMMARY
===================================================== */

.summary-grid {

    display:
        grid;

    grid-template-columns:
        repeat(
            3,
            1fr
        );

    gap:
        15px;
}

.summary-box {

    padding:
        20px;

    border-radius:
        10px;

    background:
        var(--light-blue);

    border-left:
        5px solid
        var(--blue);
}

.summary-box:nth-child(2) {

    background:
        #ffe8e8;

    border-left-color:
        var(--red);
}

.summary-box:nth-child(3) {

    background:
        var(--light-yellow);

    border-left-color:
        var(--yellow);
}

.summary-box strong {

    font-size:
        13px;
}

.summary-number {

    font-size:
        28px;

    font-weight:
        bold;

    margin-top:
        8px;

    color:
        var(--blue);
}

.summary-box:nth-child(2)
.summary-number {

    color:
        var(--red);
}

.summary-box:nth-child(3)
.summary-number {

    color:
        #b78600;
}

/* =====================================================
   EMPTY
===================================================== */

.empty {

    text-align:
        center;

    padding:
        35px;

    color:
        #999;

    font-size:
        13px;
}

/* =====================================================
   MOBILE MENU
===================================================== */

.menu-toggle {

    display:
        none;

    border:
        none;

    background:
        var(--blue);

    color:
        var(--white);

    padding:
        9px 13px;

    border-radius:
        6px;

    cursor:
        pointer;

    font-size:
        18px;
}

/* =====================================================
   RESPONSIVE
===================================================== */

@media (max-width: 1200px) {

    .stats-grid {

        grid-template-columns:
            repeat(
                3,
                1fr
            );
    }
}

@media (max-width: 950px) {

    .sidebar {

        transform:
            translateX(-100%);

        transition:
            transform .3s;
    }


    .sidebar.show {

        transform:
            translateX(0);
    }


    .main {

        margin-left:
            0;
    }


    .menu-toggle {

        display:
            block;
    }


    .panel-grid {

        grid-template-columns:
            1fr;
    }
}

@media (max-width: 650px) {

    .content {

        padding:
            15px;
    }


    .topbar {

        padding:
            8px 15px;
    }


    .topbar h1 {

        font-size:
            17px;
    }


    .topbar-logo {

        width:
            40px;

        height:
            40px;
    }


    .admin-details {

        display:
            none;
    }


    .stats-grid {

        grid-template-columns:
            1fr 1fr;

        gap:
            12px;
    }


    .stat-card {

        padding:
            15px;
    }


    .stat-card h3 {

        font-size:
            24px;
    }


    .summary-grid {

        grid-template-columns:
            1fr;
    }
}

@media (max-width: 400px) {

    .stats-grid {

        grid-template-columns:
            1fr;
    }


    .topbar-logo {

        display:
            none;
    }
}




/* =====================================================
   PAGE-SPECIFIC STYLES (preserved from original file)
===================================================== */


.login-wrapper {

            width: 100%;

            max-width: 1050px;

            min-height: 600px;

            margin: auto;

            box-sizing: border-box;

            background: var(--norsu-white)fff;

            border-radius: 20px;

            overflow: hidden;

            box-shadow:
                0 20px 50px
                rgba(
                    0,
                    0,
                    0,
                    0.25
                );

            display: flex;
        }

.left-panel {

            width: 50%;

            background:
                linear-gradient(
                    145deg,
                    #003b7a,
                    #0057b8
                );

            color: white;

            display: flex;

            flex-direction: column;

            justify-content: center;

            align-items: center;

            text-align: center;

            padding: 40px;

            position: relative;

            overflow: hidden;
        }

.left-panel::before {

            content: "";

            position: absolute;

            width: 320px;

            height: 320px;

            border-radius: 50%;

            background: var(--norsu-yellow);

            opacity: 0.08;

            top: -150px;

            left: -150px;
        }

.left-panel::after {

            content: "";

            position: absolute;

            width: 280px;

            height: 280px;

            border-radius: 50%;

            background: #d71920;

            opacity: 0.08;

            right: -120px;

            bottom: -120px;
        }

.norsu-logo {

            width: 270px;

            height: 270px;

            object-fit: contain;

            display: block;

            margin-bottom: 20px;

            position: relative;

            z-index: 2;

            filter:
                drop-shadow(
                    0 8px 15px
                    rgba(
                        0,
                        0,
                        0,
                        0.25
                    )
                );
        }

.left-panel h1 {

            font-size: 34px;

            margin-bottom: 8px;

            font-weight: 800;

            position: relative;

            z-index: 2;

            color: #0057b8;

            -webkit-text-stroke: 2px white;

            text-shadow: 2px 2px 4px rgba(0, 0, 0, 0.25);
        }

.left-panel h2 {

            font-size: 19px;

            color: var(--norsu-yellow);

            margin-bottom: 20px;

            position: relative;

            z-index: 2;
        }

.left-panel p {

            font-size: 15px;

            line-height: 1.7;

            max-width: 420px;

            color: #f1f5f9;

            position: relative;

            z-index: 2;
        }

.red-line {

            width: 80px;

            height: 5px;

            background: #d71920;

            margin: 20px auto;

            border-radius: 10px;

            position: relative;

            z-index: 2;
        }

.right-panel {

            width: 50%;

            padding: 50px;

            display: flex;

            justify-content: center;

            align-items: center;

            background: white;

            overflow-y: auto;
        }

.login-box {

            width: 100%;

            max-width: 430px;
        }

.login-box h2 {

            font-size: 30px;

            color: #003b7a;

            margin-bottom: 8px;
        }

.subtitle {

            color: #777;

            font-size: 14px;

            margin-bottom: 25px;
        }

.alert-error {

            background: #ffe5e5;

            color: #a90000;

            border-left:
                5px solid #d71920;

            padding: 14px 16px;

            border-radius: 8px;

            margin-bottom: 20px;

            font-size: 14px;

            line-height: 1.5;
        }

.alert-success {

            background: #e7f7ed;

            color: #176b38;

            border-left:
                5px solid #16803c;

            padding: 14px 16px;

            border-radius: 8px;

            margin-bottom: 20px;

            font-size: 14px;

            line-height: 1.5;
        }

.form-group {

            margin-bottom: 18px;
        }

.form-group label {

            display: block;

            margin-bottom: 8px;

            font-weight: 700;

            color: #003b7a;

            font-size: 14px;
        }

.input-wrapper {

            position: relative;
        }

.input-wrapper input {

            width: 100%;

            height: 50px;

            padding:
                0 50px
                0 16px;

            border:
                2px solid #d9e2ec;

            border-radius: 9px;

            outline: none;

            font-size: 15px;

            color: #222;

            background: white;

            transition: 0.3s;
        }

.input-wrapper input:focus {

            border-color: #0057b8;

            box-shadow:
                0 0 0 3px
                rgba(
                    0,
                    87,
                    184,
                    0.12
                );
        }

.input-icon {

            position: absolute;

            right: 16px;

            top: 50%;

            transform:
                translateY(-50%);

            color: #0057b8;

            font-size: 18px;

            user-select: none;
        }

.toggle-password {

            cursor: pointer;
        }

.login-button {

            width: 100%;

            height: 54px;

            border: none;

            border-radius: 9px;

            background:
                linear-gradient(
                    90deg,
                    #0057b8,
                    #003b7a
                );

            color: white;

            font-size: 16px;

            font-weight: 700;

            cursor: pointer;

            transition: 0.3s;

            margin-top: 5px;
        }

.login-button:hover {

            transform:
                translateY(-2px);

            box-shadow:
                0 8px 18px
                rgba(
                    0,
                    59,
                    122,
                    0.3
                );
        }

.register-text {

            text-align: center;

            margin-top: 23px;

            color: #666;

            font-size: 14px;
        }

.register-text a {

            color: #d71920;

            font-weight: 700;

            text-decoration: none;

            cursor: pointer;
        }

.register-text a:hover {

            text-decoration: underline;
        }

#registerForm {

            display: none;
        }

#loginForm {

            display: block;
        }

.show-register #loginForm {

            display: none;
        }

.show-register #registerForm {

            display: block;
        }

.back-login {

            text-align: center;

            margin-top: 20px;

            font-size: 14px;

            color: #666;
        }

.back-login a {

            color: #0057b8;

            font-weight: bold;

            text-decoration: none;

            cursor: pointer;
        }

.footer {

            text-align: center;

            margin-top: 25px;

            font-size: 12px;

            color: #999;
        }

@media (max-width: 850px) {

            .login-wrapper {

                max-width: 550px;

                min-height: auto;
            }


            .left-panel {

                display: none;
            }


            .right-panel {

                width: 100%;

                padding: 45px 30px;
            }

        }

@media (max-width: 480px) {

            body {

                padding: 10px;
            }


            .login-wrapper {

                border-radius: 14px;
            }


            .right-panel {

                padding:
                    35px 20px;
            }


            .login-box h2 {

                font-size: 25px;
            }



/* =====================================================
   FORM LINK / BUTTON SAFETY
===================================================== */

.register-text a,
.back-login a {
    cursor: pointer;
}

.login-button:disabled {
    opacity: .65;
    cursor: not-allowed;
    transform: none;
}

/* =====================================================
   MOBILE LOGIN IMPROVEMENTS
===================================================== */

@media (max-width: 850px) {
    body {
        min-height: 100vh;
        overflow-x: hidden;
    }

    .login-box {
        max-width: 100%;
    }
}

        }
/* =====================================================
   NORSU BLUE / RED / WHITE / YELLOW THEME
===================================================== */
body {
    background:
        linear-gradient(135deg, var(--norsu-blue) 0%, var(--norsu-blue) 42%,
                        var(--norsu-red) 42%, var(--norsu-red) 58%,
                        var(--norsu-yellow) 58%, var(--norsu-yellow) 100%);
    color: #1f2937;
}

.container, .login-container, .auth-container, .form-container {
    background: var(--norsu-white);
    border-top: 6px solid var(--norsu-blue);
    box-shadow: 0 18px 45px rgba(0, 0, 0, .20);
}

h1, h2, h3, .title, .logo-title {
    color: var(--norsu-blue);
}

label {
    color: var(--norsu-blue);
}

input, select, textarea {
    border: 2px solid #d8e6f5;
    background: var(--norsu-white);
}

input:focus, select:focus, textarea:focus {
    border-color: var(--norsu-blue);
    box-shadow: 0 0 0 3px rgba(0, 91, 170, .12);
    outline: none;
}

button, .btn, .login-btn, .submit-btn {
    background: var(--norsu-blue);
    color: var(--norsu-white);
    border: 2px solid var(--norsu-blue);
}

button:hover, .btn:hover, .login-btn:hover, .submit-btn:hover {
    background: var(--norsu-red);
    border-color: var(--norsu-red);
}

a {
    color: var(--norsu-blue);
}

a:hover {
    color: var(--norsu-red);
}

.register-link, .signup-link {
    color: var(--norsu-red);
    font-weight: 700;
}

.alert-success, .success-message {
    background: var(--norsu-yellow);
    color: #333;
    border-left: 5px solid var(--norsu-blue);
}

.alert-error, .error-message {
    background: #fff1f1;
    color: var(--norsu-red);
    border-left: 5px solid var(--norsu-red);
}

.logo, .brand-logo {
    border: 4px solid var(--norsu-yellow);
    background: var(--norsu-white);
}

.divider {
    border-color: var(--norsu-yellow);
}

@media (max-width: 600px) {
    body {
        background: linear-gradient(180deg, var(--norsu-blue) 0 45%,
                                    var(--norsu-red) 45% 72%,
                                    var(--norsu-yellow) 72% 100%);
    }
}

</style>

</head>


<body>


<div
    class="login-wrapper <?php
        echo $activeForm === "register"
            ? "show-register"
            : "";
    ?>"
    id="mainWrapper"
>


    <!-- =================================================
         LEFT PANEL
    ================================================= -->

    <div class="left-panel">


        <?php

        $logoFile = "norsu.png";

        ?>


        <?php if (
            file_exists(
                __DIR__ . "/" . $logoFile
            )
        ): ?>

            <img
                src="<?php echo e($logoFile); ?>"
                alt="NORSU Logo"
                class="norsu-logo"
            >

        <?php else: ?>

            <div
                style="
                    width:270px;
                    height:270px;
                    border-radius:50%;
                    background:var(--norsu-white)fff;
                    display:flex;
                    align-items:center;
                    justify-content:center;
                    color:#0057b8;
                    font-size:32px;
                    font-weight:bold;
                    position:relative;
                    z-index:2;
                    margin-bottom:20px;
                "
            >
                NORSU
            </div>

        <?php endif; ?>


        <h1>
            NORSU
        </h1>


        <h2>
            Registrar System
        </h2>


        <div class="red-line"></div>


        <p>

            Welcome to the NORSU Registrar System.

            Please log in to access your account
            and manage your registrar services.

        </p>


    </div>


    <!-- =================================================
         RIGHT PANEL
    ================================================= -->

    <div class="right-panel">


        <div class="login-box">


            <!-- =================================================
                 LOGIN FORM
            ================================================= -->

            <form
                method="POST"
                action=""
                id="loginForm"
                autocomplete="off"
            >


                <h2>
                    Welcome Back!
                </h2>


                <p class="subtitle">
                    Sign in to your account to continue.
                </p>


                <?php if (
                    $error !== "" &&
                    $activeForm === "login"
                ): ?>

                    <div class="alert-error">

                        <?php
                        echo e($error);
                        ?>

                    </div>

                <?php endif; ?>


                <?php if (
                    $success !== "" &&
                    $activeForm === "login"
                ): ?>

                    <div class="alert-success">

                        <?php
                        echo e($success);
                        ?>

                    </div>

                <?php endif; ?>


                <input
                    type="hidden"
                    name="action"
                    value="login"
                >


                <!-- EMAIL -->

                <div class="form-group">

                    <label for="email">
                        Email Address
                    </label>


                    <div class="input-wrapper">

                        <input
                            type="email"
                            id="email"
                            name="email"
                            placeholder="Enter your email address"
                            required
                            autocomplete="off"
                        >


                        <span class="input-icon">
                            ✉
                        </span>

                    </div>

                </div>


                <!-- PASSWORD -->

                <div class="form-group">

                    <label for="password">
                        Password
                    </label>


                    <div class="input-wrapper">

                        <input
                            type="password"
                            id="password"
                            name="password"
                            placeholder="Enter your password"
                            required
                            autocomplete="new-password"
                        >


                        <span
                            class="input-icon toggle-password"
                            onclick="togglePassword(
                                'password',
                                this
                            )"
                            title="Show or hide password"
                        >
                            👁
                        </span>

                    </div>

                </div>


                <button
                    type="submit"
                    class="login-button"
                >
                    LOGIN
                </button>


                <div class="register-text">

                    Don't have an account?

                    <a href="#register" onclick="showRegister(); return false;">
                        Sign Up
                    </a>

                </div>


            </form>


            <!-- =================================================
                 REGISTER FORM
            ================================================= -->

            <form
                method="POST"
                action=""
                id="registerForm"
                autocomplete="off"
            >


                <h2>
                    Create Account
                </h2>


                <p class="subtitle">
                    Register as a client to use the
                    NORSU Registrar System.
                </p>


                <?php if (
                    $error !== "" &&
                    $activeForm === "register"
                ): ?>

                    <div class="alert-error">

                        <?php
                        echo e($error);
                        ?>

                    </div>

                <?php endif; ?>


                <input
                    type="hidden"
                    name="action"
                    value="register"
                >


                <!-- FULL NAME -->

                <div class="form-group">

                    <label for="full_name">
                        Full Name
                    </label>


                    <div class="input-wrapper">

                        <input
                            type="text"
                            id="full_name"
                            name="full_name"
                            placeholder="Enter your full name"
                            required
                            maxlength="150"
                            autocomplete="off"
                        >


                        <span class="input-icon">
                            👤
                        </span>

                    </div>

                </div>


                <!-- EMAIL -->

                <div class="form-group">

                    <label for="register_email">
                        Email Address
                    </label>


                    <div class="input-wrapper">

                        <input
                            type="email"
                            id="register_email"
                            name="register_email"
                            placeholder="Enter your email address"
                            required
                            maxlength="150"
                            autocomplete="off"
                        >


                        <span class="input-icon">
                            ✉
                        </span>

                    </div>

                </div>


                <!-- PASSWORD -->

                <div class="form-group">

                    <label for="register_password">
                        Password
                    </label>


                    <div class="input-wrapper">

                        <input
                            type="password"
                            id="register_password"
                            name="register_password"
                            placeholder="Create a password"
                            required
                            minlength="6"
                            autocomplete="new-password"
                        >


                        <span
                            class="input-icon toggle-password"
                            onclick="togglePassword(
                                'register_password',
                                this
                            )"
                            title="Show or hide password"
                        >
                            👁
                        </span>

                    </div>

                </div>


                <!-- CONFIRM PASSWORD -->

                <div class="form-group">

                    <label for="confirm_password">
                        Confirm Password
                    </label>


                    <div class="input-wrapper">

                        <input
                            type="password"
                            id="confirm_password"
                            name="confirm_password"
                            placeholder="Confirm your password"
                            required
                            minlength="6"
                            autocomplete="new-password"
                        >


                        <span
                            class="input-icon toggle-password"
                            onclick="togglePassword(
                                'confirm_password',
                                this
                            )"
                            title="Show or hide password"
                        >
                            👁
                        </span>

                    </div>

                </div>


                <button
                    type="submit"
                    class="login-button"
                >
                    CREATE ACCOUNT
                </button>


                <div class="back-login">

                    Already have an account?

                    <a href="#login" onclick="showLogin(); return false;">
                        Login here
                    </a>

                </div>


            </form>


            <!-- FOOTER -->

            <div class="footer">

                NORSU Registrar System ©
                <?php echo date("Y"); ?>

            </div>


        </div>


    </div>


</div>


<script>


// =====================================================
// SHOW REGISTER FORM
// =====================================================

function showRegister()
{
    const wrapper = document.getElementById("mainWrapper");

    if (wrapper) {
        wrapper.classList.add("show-register");

        const email = document.getElementById("register_email");
        if (email) {
            setTimeout(function () {
                email.focus();
            }, 50);
        }
    }
}


// =====================================================
// SHOW LOGIN FORM
// =====================================================

function showLogin()
{
    const wrapper = document.getElementById("mainWrapper");

    if (wrapper) {
        wrapper.classList.remove("show-register");

        const email = document.getElementById("email");
        if (email) {
            setTimeout(function () {
                email.focus();
            }, 50);
        }
    }
}


// =====================================================
// SHOW / HIDE PASSWORD
// =====================================================

function togglePassword(inputId, icon)
{
    const password = document.getElementById(inputId);

    if (!password || !icon) {
        return;
    }

    if (password.type === "password") {
        password.type = "text";
        icon.textContent = "🙈";
        icon.setAttribute("aria-label", "Hide password");
    } else {
        password.type = "password";
        icon.textContent = "👁";
        icon.setAttribute("aria-label", "Show password");
    }
}

// Prevent registration when the two passwords do not match.
const registerForm = document.getElementById("registerForm");

if (registerForm) {
    registerForm.addEventListener("submit", function (event) {
        const password = document.getElementById("register_password");
        const confirmPassword = document.getElementById("confirm_password");

        if (password && confirmPassword &&
            password.value !== confirmPassword.value) {
            event.preventDefault();
            alert("Passwords do not match.");
            confirmPassword.focus();
        }
    });
}

</script>


</body>

</html>
```

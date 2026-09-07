<?php
session_start();

/*
=========================================================
    NORSU REGISTRAR SYSTEM
    ADMIN / USER MANAGEMENT

    File Name:
    users.php

    Database:
    haha

    FEATURES:
    - Add Admin
    - Edit Admin
    - Delete Admin
    - Registrar Staff
    - User Roles
    - Permissions
    - Account Status
    - Login Activity
=========================================================
*/

// =====================================================
// DATABASE CONNECTION
// =====================================================

mysqli_report(MYSQLI_REPORT_OFF);

$conn = new mysqli("localhost", "root", "", "haha");

if ($conn->connect_error) {
    die("Database connection failed: " . $conn->connect_error);
}

$conn->set_charset("utf8mb4");


// =====================================================
// CREATE USERS TABLE
// =====================================================

$conn->query("
    CREATE TABLE IF NOT EXISTS users (
        id INT AUTO_INCREMENT PRIMARY KEY,
        username VARCHAR(100) NOT NULL UNIQUE,
        password VARCHAR(255) NOT NULL,
        full_name VARCHAR(255) NOT NULL,
        email VARCHAR(255) DEFAULT '',
        contact_number VARCHAR(50) DEFAULT '',
        role ENUM('Admin','Registrar Staff','Viewer') NOT NULL DEFAULT 'Registrar Staff',
        status ENUM('Active','Inactive','Suspended') NOT NULL DEFAULT 'Active',
        last_login DATETIME NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        INDEX idx_role (role),
        INDEX idx_status (status)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
");


// =====================================================
// CREATE PERMISSIONS TABLE
// =====================================================

$conn->query("
    CREATE TABLE IF NOT EXISTS permissions (
        id INT AUTO_INCREMENT PRIMARY KEY,
        permission_key VARCHAR(100) NOT NULL UNIQUE,
        permission_name VARCHAR(150) NOT NULL,
        permission_group VARCHAR(100) NOT NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_permission_group (permission_group)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
");


// =====================================================
// CREATE USER PERMISSIONS TABLE
// =====================================================

$conn->query("
    CREATE TABLE IF NOT EXISTS user_permissions (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        permission_id INT NOT NULL,
        UNIQUE KEY unique_user_permission (user_id, permission_id),
        INDEX idx_user_id (user_id),
        INDEX idx_permission_id (permission_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
");


// =====================================================
// CREATE LOGIN ACTIVITY TABLE
// =====================================================

$conn->query("
    CREATE TABLE IF NOT EXISTS login_activity (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NULL,
        username VARCHAR(100) NOT NULL,
        activity VARCHAR(100) NOT NULL,
        ip_address VARCHAR(100) DEFAULT '',
        user_agent VARCHAR(500) DEFAULT '',
        activity_date DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_user_id (user_id),
        INDEX idx_username (username),
        INDEX idx_activity_date (activity_date)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
");


// =====================================================
// HELPER FUNCTIONS
// =====================================================

function e($value)
{
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function redirectSelf($extra = '')
{
    $url = basename($_SERVER['PHP_SELF']);

    if ($extra !== '') {
        $url .= '?' . $extra;
    }

    header("Location: " . $url);
    exit;
}

function flash($type, $message)
{
    $_SESSION['users_flash_type'] = $type;
    $_SESSION['users_flash_message'] = $message;
}

function getFlash()
{
    $data = [
        'type' => '',
        'message' => ''
    ];

    if (isset($_SESSION['users_flash_type'])) {
        $data['type'] = $_SESSION['users_flash_type'];
        unset($_SESSION['users_flash_type']);
    }

    if (isset($_SESSION['users_flash_message'])) {
        $data['message'] = $_SESSION['users_flash_message'];
        unset($_SESSION['users_flash_message']);
    }

    return $data;
}

function activity($conn, $userId, $username, $action)
{
    $ip = $_SERVER['REMOTE_ADDR'] ?? '';
    $agent = substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 500);

    $stmt = $conn->prepare("
        INSERT INTO login_activity
        (user_id, username, activity, ip_address, user_agent)
        VALUES (?, ?, ?, ?, ?)
    ");

    if ($stmt) {
        $stmt->bind_param(
            "issss",
            $userId,
            $username,
            $action,
            $ip,
            $agent
        );

        $stmt->execute();
        $stmt->close();
    }
}

function selectedPerms($conn, $userId)
{
    $selected = [];

    $stmt = $conn->prepare("
        SELECT permission_id
        FROM user_permissions
        WHERE user_id = ?
    ");

    if (!$stmt) {
        return $selected;
    }

    $stmt->bind_param("i", $userId);
    $stmt->execute();

    $result = $stmt->get_result();

    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $selected[] = (int)$row['permission_id'];
        }
    }

    $stmt->close();

    return $selected;
}

function syncPermissions($conn, $userId, $permissionIds)
{
    $stmt = $conn->prepare("DELETE FROM user_permissions WHERE user_id = ?");

    if ($stmt) {
        $stmt->bind_param("i", $userId);
        $stmt->execute();
        $stmt->close();
    }

    if (empty($permissionIds)) {
        return;
    }

    $insert = $conn->prepare("
        INSERT IGNORE INTO user_permissions
        (user_id, permission_id)
        VALUES (?, ?)
    ");

    if (!$insert) {
        return;
    }

    foreach ($permissionIds as $permissionId) {
        $permissionId = (int)$permissionId;

        if ($permissionId > 0) {
            $insert->bind_param("ii", $userId, $permissionId);
            $insert->execute();
        }
    }

    $insert->close();
}


// =====================================================
// SEED PERMISSIONS
// =====================================================

$permissionSeeds = [
    ['dashboard.view', 'View Dashboard', 'Dashboard'],

    ['students.view', 'View Students', 'Students'],
    ['students.manage', 'Manage Students', 'Students'],

    ['enrollment.view', 'View Enrollment', 'Enrollment'],
    ['enrollment.manage', 'Manage Enrollment', 'Enrollment'],

    ['programs.view', 'View Programs', 'Programs'],
    ['programs.manage', 'Manage Programs', 'Programs'],

    ['grades.view', 'View Grades', 'Grades'],
    ['grades.manage', 'Manage Grades', 'Grades'],

    ['documents.view', 'View Document Requests', 'Registrar Services'],
    ['documents.manage', 'Manage Document Requests', 'Registrar Services'],
    ['queue.manage', 'Manage Queue', 'Registrar Services'],
    ['graduation.manage', 'Manage Graduation', 'Registrar Services'],

    ['reports.view', 'View Reports', 'Administration'],
    ['users.view', 'View Users', 'Administration'],
    ['users.manage', 'Manage Users', 'Administration'],
    ['settings.manage', 'Manage Settings', 'Administration']
];

foreach ($permissionSeeds as $permission) {
    $stmt = $conn->prepare("
        INSERT IGNORE INTO permissions
        (permission_key, permission_name, permission_group)
        VALUES (?, ?, ?)
    ");

    if ($stmt) {
        $stmt->bind_param(
            "sss",
            $permission[0],
            $permission[1],
            $permission[2]
        );

        $stmt->execute();
        $stmt->close();
    }
}


// =====================================================
// CREATE DEFAULT ADMIN
// =====================================================

$defaultAdminExists = false;

$result = $conn->query("
    SELECT id
    FROM users
    WHERE username = 'admin'
    LIMIT 1
");

if ($result && $result->num_rows > 0) {
    $defaultAdminExists = true;
}

if (!$defaultAdminExists) {
    $defaultUsername = 'admin';
    $defaultPassword = password_hash('admin123', PASSWORD_DEFAULT);
    $defaultFullName = 'Administrator';
    $defaultEmail = '';
    $defaultContact = '';
    $defaultRole = 'Admin';
    $defaultStatus = 'Active';

    $stmt = $conn->prepare("
        INSERT INTO users
        (username, password, full_name, email, contact_number, role, status)
        VALUES (?, ?, ?, ?, ?, ?, ?)
    ");

    if ($stmt) {
        $stmt->bind_param(
            "sssssss",
            $defaultUsername,
            $defaultPassword,
            $defaultFullName,
            $defaultEmail,
            $defaultContact,
            $defaultRole,
            $defaultStatus
        );

        if ($stmt->execute()) {
            $newAdminId = $stmt->insert_id;

            $allPermissionIds = [];

            $permissionResult = $conn->query("SELECT id FROM permissions");

            if ($permissionResult) {
                while ($permissionRow = $permissionResult->fetch_assoc()) {
                    $allPermissionIds[] = (int)$permissionRow['id'];
                }
            }

            syncPermissions(
                $conn,
                $newAdminId,
                $allPermissionIds
            );
        }

        $stmt->close();
    }
}


// =====================================================
// MESSAGE VARIABLES
// =====================================================

$flash = getFlash();

$success = $flash['type'] === 'success'
    ? $flash['message']
    : '';

$error = $flash['type'] === 'error'
    ? $flash['message']
    : '';


// =====================================================
// SAVE USER
// =====================================================

if (isset($_POST['save_user'])) {

    $userId = (int)($_POST['user_id'] ?? 0);

    $username = trim($_POST['username'] ?? '');
    $fullName = trim($_POST['full_name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $contactNumber = trim($_POST['contact_number'] ?? '');
    $role = trim($_POST['role'] ?? 'Registrar Staff');
    $status = trim($_POST['status'] ?? 'Active');
    $password = $_POST['password'] ?? '';

    $permissionIds = $_POST['permissions'] ?? [];

    if (!is_array($permissionIds)) {
        $permissionIds = [];
    }

    $allowedRoles = [
        'Admin',
        'Registrar Staff',
        'Viewer'
    ];

    $allowedStatuses = [
        'Active',
        'Inactive',
        'Suspended'
    ];

    if ($username === '' || $fullName === '') {

        flash(
            'error',
            'Username and full name are required.'
        );

        redirectSelf(
            $userId > 0
                ? 'edit=' . $userId
                : ''
        );

    } elseif (!preg_match('/^[A-Za-z0-9._-]+$/', $username)) {

        flash(
            'error',
            'Username may only contain letters, numbers, dots, underscores, and hyphens.'
        );

        redirectSelf(
            $userId > 0
                ? 'edit=' . $userId
                : ''
        );

    } elseif (!in_array($role, $allowedRoles, true)) {

        flash(
            'error',
            'Invalid user role.'
        );

        redirectSelf(
            $userId > 0
                ? 'edit=' . $userId
                : ''
        );

    } elseif (!in_array($status, $allowedStatuses, true)) {

        flash(
            'error',
            'Invalid account status.'
        );

        redirectSelf(
            $userId > 0
                ? 'edit=' . $userId
                : ''
        );

    } elseif ($userId === 0 && $password === '') {

        flash(
            'error',
            'Password is required when creating a new account.'
        );

        redirectSelf();

    } else {

        // Check duplicate username.
        $duplicate = false;

        if ($userId > 0) {

            $stmt = $conn->prepare("
                SELECT id
                FROM users
                WHERE username = ?
                  AND id <> ?
                LIMIT 1
            ");

            if ($stmt) {
                $stmt->bind_param(
                    "si",
                    $username,
                    $userId
                );

                $stmt->execute();

                $result = $stmt->get_result();

                $duplicate = $result &&
                    $result->num_rows > 0;

                $stmt->close();
            }

        } else {

            $stmt = $conn->prepare("
                SELECT id
                FROM users
                WHERE username = ?
                LIMIT 1
            ");

            if ($stmt) {
                $stmt->bind_param(
                    "s",
                    $username
                );

                $stmt->execute();

                $result = $stmt->get_result();

                $duplicate = $result &&
                    $result->num_rows > 0;

                $stmt->close();
            }
        }

        if ($duplicate) {

            flash(
                'error',
                'Username already exists.'
            );

            redirectSelf(
                $userId > 0
                    ? 'edit=' . $userId
                    : ''
            );
        }

        // Admin accounts automatically receive every permission.
        if ($role === 'Admin') {

            $permissionIds = [];

            $permissionResult =
                $conn->query("SELECT id FROM permissions");

            if ($permissionResult) {
                while ($row = $permissionResult->fetch_assoc()) {
                    $permissionIds[] = (int)$row['id'];
                }
            }
        }

        if ($userId > 0) {

            if ($password !== '') {

                $hashedPassword =
                    password_hash(
                        $password,
                        PASSWORD_DEFAULT
                    );

                $stmt = $conn->prepare("
                    UPDATE users
                    SET username = ?,
                        password = ?,
                        full_name = ?,
                        email = ?,
                        contact_number = ?,
                        role = ?,
                        status = ?
                    WHERE id = ?
                ");

                if ($stmt) {
                    $stmt->bind_param(
                        "sssssssi",
                        $username,
                        $hashedPassword,
                        $fullName,
                        $email,
                        $contactNumber,
                        $role,
                        $status,
                        $userId
                    );

                    $ok = $stmt->execute();
                    $stmt->close();
                } else {
                    $ok = false;
                }

            } else {

                $stmt = $conn->prepare("
                    UPDATE users
                    SET username = ?,
                        full_name = ?,
                        email = ?,
                        contact_number = ?,
                        role = ?,
                        status = ?
                    WHERE id = ?
                ");

                if ($stmt) {
                    $stmt->bind_param(
                        "ssssssi",
                        $username,
                        $fullName,
                        $email,
                        $contactNumber,
                        $role,
                        $status,
                        $userId
                    );

                    $ok = $stmt->execute();
                    $stmt->close();
                } else {
                    $ok = false;
                }
            }

            if ($ok) {

                syncPermissions(
                    $conn,
                    $userId,
                    $permissionIds
                );

                activity(
                    $conn,
                    $userId,
                    $username,
                    'User Updated'
                );

                flash(
                    'success',
                    'User account updated successfully.'
                );

                redirectSelf();
            }

            flash(
                'error',
                'Unable to update user account.'
            );

            redirectSelf('edit=' . $userId);

        } else {

            $hashedPassword =
                password_hash(
                    $password,
                    PASSWORD_DEFAULT
                );

            $stmt = $conn->prepare("
                INSERT INTO users
                (username, password, full_name, email, contact_number, role, status)
                VALUES (?, ?, ?, ?, ?, ?, ?)
            ");

            if ($stmt) {

                $stmt->bind_param(
                    "sssssss",
                    $username,
                    $hashedPassword,
                    $fullName,
                    $email,
                    $contactNumber,
                    $role,
                    $status
                );

                if ($stmt->execute()) {

                    $newUserId = $stmt->insert_id;

                    $stmt->close();

                    syncPermissions(
                        $conn,
                        $newUserId,
                        $permissionIds
                    );

                    activity(
                        $conn,
                        $newUserId,
                        $username,
                        'User Created'
                    );

                    flash(
                        'success',
                        'User account created successfully.'
                    );

                    redirectSelf();
                }

                $stmt->close();
            }

            flash(
                'error',
                'Unable to create user account.'
            );

            redirectSelf();
        }
    }
}


// =====================================================
// DELETE USER
// =====================================================

if (isset($_GET['delete_user'])) {

    $id = (int)$_GET['delete_user'];

    $stmt = $conn->prepare("
        SELECT id, username, role
        FROM users
        WHERE id = ?
        LIMIT 1
    ");

    $user = null;

    if ($stmt) {

        $stmt->bind_param(
            "i",
            $id
        );

        $stmt->execute();

        $result = $stmt->get_result();

        $user = $result
            ? $result->fetch_assoc()
            : null;

        $stmt->close();
    }

    if (!$user) {

        flash(
            'error',
            'User account not found.'
        );

        redirectSelf();

    } elseif ($user['username'] === 'admin') {

        flash(
            'error',
            'The default administrator account cannot be deleted.'
        );

        redirectSelf();

    } else {

        $stmt = $conn->prepare("
            DELETE FROM users
            WHERE id = ?
        ");

        if ($stmt) {

            $stmt->bind_param(
                "i",
                $id
            );

            if ($stmt->execute()) {

                $stmt->close();

                flash(
                    'success',
                    'User account deleted successfully.'
                );

                redirectSelf();
            }

            $stmt->close();
        }

        flash(
            'error',
            'Unable to delete user account.'
        );

        redirectSelf();
    }
}


// =====================================================
// CHANGE ACCOUNT STATUS
// =====================================================

if (isset($_GET['status_user'])) {

    $id = (int)($_GET['status_user'] ?? 0);
    $newStatus = trim($_GET['new_status'] ?? '');

    $allowedStatuses = [
        'Active',
        'Inactive',
        'Suspended'
    ];

    if (!in_array($newStatus, $allowedStatuses, true)) {

        flash(
            'error',
            'Invalid account status.'
        );

        redirectSelf();
    }

    $stmt = $conn->prepare("
        SELECT id, username
        FROM users
        WHERE id = ?
        LIMIT 1
    ");

    $target = null;

    if ($stmt) {

        $stmt->bind_param(
            "i",
            $id
        );

        $stmt->execute();

        $result = $stmt->get_result();

        $target = $result
            ? $result->fetch_assoc()
            : null;

        $stmt->close();
    }

    if (!$target) {

        flash(
            'error',
            'User account not found.'
        );

        redirectSelf();
    }

    $stmt = $conn->prepare("
        UPDATE users
        SET status = ?
        WHERE id = ?
    ");

    if ($stmt) {

        $stmt->bind_param(
            "si",
            $newStatus,
            $id
        );

        if ($stmt->execute()) {

            $stmt->close();

            activity(
                $conn,
                $id,
                $target['username'],
                'Status Changed to ' . $newStatus
            );

            flash(
                'success',
                'Account status updated successfully.'
            );

            redirectSelf();
        }

        $stmt->close();
    }

    flash(
        'error',
        'Unable to update account status.'
    );

    redirectSelf();
}


// =====================================================
// FILTERS
// =====================================================

$search = trim(
    $_GET['search'] ?? ''
);

$roleFilter = trim(
    $_GET['role'] ?? ''
);

$statusFilter = trim(
    $_GET['status'] ?? ''
);

$allowedRoles = [
    'Admin',
    'Registrar Staff',
    'Viewer'
];

$allowedStatuses = [
    'Active',
    'Inactive',
    'Suspended'
];


// =====================================================
// EDIT USER
// =====================================================

$editUser = null;
$editPermissions = [];

if (isset($_GET['edit'])) {

    $editId = (int)$_GET['edit'];

    $stmt = $conn->prepare("
        SELECT *
        FROM users
        WHERE id = ?
        LIMIT 1
    ");

    if ($stmt) {

        $stmt->bind_param(
            "i",
            $editId
        );

        $stmt->execute();

        $result = $stmt->get_result();

        $editUser = $result
            ? $result->fetch_assoc()
            : null;

        $stmt->close();
    }

    if ($editUser) {
        $editPermissions =
            selectedPerms(
                $conn,
                $editId
            );
    }
}


// =====================================================
// PERMISSIONS
// =====================================================

$permissions = [];
$permissionGroups = [];

$permissionResult = $conn->query("
    SELECT *
    FROM permissions
    ORDER BY
        permission_group ASC,
        id ASC
");

if ($permissionResult) {

    while ($row = $permissionResult->fetch_assoc()) {

        $permissions[] = $row;

        $permissionGroups[
            $row['permission_group']
        ][] = $row;
    }
}


// =====================================================
// USER LIST
// =====================================================

$users = [];

$sql = "
    SELECT
        u.*,
        (
            SELECT COUNT(*)
            FROM user_permissions up
            WHERE up.user_id = u.id
        ) AS permission_count
    FROM users u
    WHERE 1=1
";

$params = [];
$types = '';

if ($search !== '') {

    $sql .= "
        AND (
            u.username LIKE ?
            OR u.full_name LIKE ?
            OR u.email LIKE ?
            OR u.contact_number LIKE ?
        )
    ";

    $like = '%' . $search . '%';

    $params[] = $like;
    $params[] = $like;
    $params[] = $like;
    $params[] = $like;

    $types .= 'ssss';
}

if (
    $roleFilter !== '' &&
    in_array($roleFilter, $allowedRoles, true)
) {

    $sql .= "
        AND u.role = ?
    ";

    $params[] = $roleFilter;
    $types .= 's';
}

if (
    $statusFilter !== '' &&
    in_array($statusFilter, $allowedStatuses, true)
) {

    $sql .= "
        AND u.status = ?
    ";

    $params[] = $statusFilter;
    $types .= 's';
}

$sql .= "
    ORDER BY
        u.created_at DESC,
        u.id DESC
";

$stmt = $conn->prepare($sql);

if ($stmt) {

    if (!empty($params)) {
        $stmt->bind_param(
            $types,
            ...$params
        );
    }

    $stmt->execute();

    $result = $stmt->get_result();

    if ($result) {

        while ($row = $result->fetch_assoc()) {
            $users[] = $row;
        }
    }

    $stmt->close();
}


// =====================================================
// STATISTICS
// =====================================================

$userCounts = [
    'all' => 0,
    'admins' => 0,
    'staff' => 0,
    'active' => 0,
    'inactive' => 0
];

$countResult = $conn->query("
    SELECT
        COUNT(*) AS all_users,
        SUM(role = 'Admin') AS admins,
        SUM(role = 'Registrar Staff') AS staff,
        SUM(status = 'Active') AS active_users,
        SUM(status <> 'Active') AS inactive_users
    FROM users
");

if ($countResult) {

    $row = $countResult->fetch_assoc();

    if ($row) {

        $userCounts['all'] =
            (int)($row['all_users'] ?? 0);

        $userCounts['admins'] =
            (int)($row['admins'] ?? 0);

        $userCounts['staff'] =
            (int)($row['staff'] ?? 0);

        $userCounts['active'] =
            (int)($row['active_users'] ?? 0);

        $userCounts['inactive'] =
            (int)($row['inactive_users'] ?? 0);
    }
}


// =====================================================
// LOGIN ACTIVITY
// =====================================================

$loginActivities = [];

$activityResult = $conn->query("
    SELECT
        la.*,
        u.full_name
    FROM login_activity la
    LEFT JOIN users u
        ON u.id = la.user_id
    ORDER BY
        la.activity_date DESC,
        la.id DESC
    LIMIT 50
");

if ($activityResult) {

    while ($row = $activityResult->fetch_assoc()) {
        $loginActivities[] = $row;
    }
}


// =====================================================
// CURRENT ADMIN
// =====================================================

$adminName =
    $_SESSION['admin_username']
    ?? $_SESSION['username']
    ?? 'Administrator';


// =====================================================
// HTML
// =====================================================

?>
<!DOCTYPE html>
<html lang="en">

<head>

<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">

<title>Admin / User Management | NORSU Registrar System</title>

<style>/* =====================================================
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
        #ffc107;

    --light-yellow:
        #fff8d6;

    --white:
        #ffffff;

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


.sidebar-menu .icon {
    width: 22px;
    text-align: center;
    font-size: 16px;
}

.page-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-bottom: 22px;
}

.page-title {
    font-size: 27px;
    color: var(--green);
    font-weight: bold;
}

.page-subtitle {
    color: var(--muted);
    margin-top: 6px;
}

.alert {
    padding: 13px 16px;
    border-radius: 6px;
    margin-bottom: 20px;
    font-size: 14px;
}

.alert-success {
    background: #d4edda;
    color: #155724;
    border: 1px solid #c3e6cb;
}

.alert-error {
    background: #fee2e2;
    color: #991b1b;
    border-left: 5px solid #dc2626;
}

.stats {
    display: grid;
    grid-template-columns: repeat(5, 1fr);
    gap: 18px;
    margin-bottom: 25px;
}

.stat-label {
    color: var(--muted);
    font-size: 13px;
}

.stat-number {
    color: var(--dark-blue);
    font-size: 30px;
    font-weight: bold;
    margin-top: 8px;
}

.card {
    background: white;
    border-radius: 10px;
    box-shadow: 0 2px 10px rgba(0,0,0,.08);
    margin-bottom: 25px;
    overflow: hidden;
}

.card-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 18px 22px;
    border-bottom: 1px solid var(--border);
}

.card-title {
    color: var(--green);
    font-size: 18px;
    font-weight: bold;
}

.card-body {
    padding: 22px;
}

.form-grid {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 18px;
}

.form-group {
    display: flex;
    flex-direction: column;
}

.form-group.full {
    grid-column: 1 / -1;
}

.form-group label {
    font-size: 13px;
    font-weight: bold;
    color: #444;
    margin-bottom: 7px;
}

.form-group input,
.form-group select,
.form-group textarea {
    width: 100%;
    padding: 11px 12px;
    border: 1px solid #d4d8dd;
    border-radius: 6px;
    font-size: 14px;
    font-family: inherit;
    background: white;
}

.form-group input:focus,
.form-group select:focus,
.form-group textarea:focus {
    outline: none;
    border-color: var(--blue);
    box-shadow: 0 0 0 3px rgba(0,87,184,.10);
}

.form-help {
    display: block;
    margin-top: 6px;
    color: #777;
    font-size: 12px;
    line-height: 1.4;
}

.form-buttons {
    display: flex;
    gap: 8px;
    margin-top: 20px;
}

.btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    padding: 9px 14px;
    border: none;
    border-radius: 6px;
    text-decoration: none;
    cursor: pointer;
    font-size: 13px;
    font-weight: 600;
    transition: .2s;
    white-space: nowrap;
}

.btn:hover {
    opacity: .9;
    transform: translateY(-1px);
}

.btn-primary {
    background: var(--blue);
    color: white;
}

.btn-success {
    background: var(--green);
    color: white;
}

.btn-danger {
    background: var(--red);
    color: white;
}

.btn-warning {
    background: var(--yellow);
    color: #222;
}

.btn-gray {
    background: #6c757d;
    color: white;
}

.btn-blue {
    background: var(--blue);
    color: white;
}

.btn-light {
    background: #f2f4f7;
    color: #333;
    border: 1px solid #d8dce2;
}

.btn-light:hover {
    background: #e7eaee;
}

.btn-sm {
    padding: 7px 10px;
    font-size: 12px;
}

.table-container,
.table-wrap {
    width: 100%;
    overflow-x: auto;
}

table th {
    background: var(--dark-blue);
    color: white;
    padding: 13px 12px;
    text-align: left;
    font-size: 12px;
    white-space: nowrap;
}

table td {
    padding: 12px;
    border-bottom: 1px solid var(--border);
    font-size: 13px;
    vertical-align: middle;
}

table tbody tr:hover {
    background: #f7f9fc;
}

.actions {
    display: flex;
    flex-wrap: wrap;
    gap: 4px;
}

.actions .btn {
    padding: 7px 10px;
    font-size: 12px;
}

.badge {
    display: inline-block;
    padding: 5px 10px;
    border-radius: 20px;
    font-size: 11px;
    font-weight: bold;
}

.badge-active {
    background: #d1e7dd;
    color: #0f5132;
}

.badge-inactive {
    background: #e2e3e5;
    color: #41464b;
}

.badge-suspended {
    background: #f8d7da;
    color: #842029;
}

.badge-admin {
    background: #cfe2ff;
    color: #084298;
}

.badge-staff {
    background: #d1e7dd;
    color: #0f5132;
}

.badge-viewer {
    background: #fff3cd;
    color: #856404;
}

.filter-grid {
    display: grid;
    grid-template-columns: 2fr 1fr 1fr auto;
    gap: 10px;
    align-items: end;
    margin-bottom: 20px;
}

.filter-group {
    display: flex;
    flex-direction: column;
}

.filter-group label {
    font-size: 12px;
    font-weight: bold;
    margin-bottom: 5px;
    color: #555;
}

.filter-group input,
.filter-group select {
    padding: 11px;
    border: 1px solid #ccc;
    border-radius: 6px;
    width: 100%;
    background: white;
}

.info-box {
    background: #eef5fb;
    border-left: 4px solid var(--blue);
    padding: 15px;
    border-radius: 6px;
    margin-bottom: 20px;
}

.info-box strong {
    color: var(--dark-blue);
}

.permission-toolbar {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 10px;
    margin-bottom: 15px;
    flex-wrap: wrap;
}

.permission-toolbar-left {
    display: flex;
    align-items: center;
    gap: 8px;
    flex-wrap: wrap;
}

.permission-toolbar-right {
    color: var(--muted);
    font-size: 12px;
}

.permission-groups {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 16px;
    width: 100%;
}

.permission-group {
    width: 100%;
    border: 1px solid #dfe4ea;
    border-radius: 10px;
    background: white;
    overflow: hidden;
    box-shadow: 0 2px 6px rgba(0,0,0,.04);
}

.permission-group-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 10px;
    padding: 13px 15px;
    background: #f7f9fc;
    border-bottom: 1px solid #e2e6eb;
}

.permission-group-title {
    color: var(--dark-blue);
    font-size: 14px;
    font-weight: bold;
}

.permission-group-list {
    display: flex;
    flex-direction: column;
    gap: 10px;
    padding: 15px;
}

.permission-item {
    width: 100%;
    min-height: 55px;
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 11px 12px;
    border: 1px solid #e1e5ea;
    border-radius: 7px;
    background: #fafbfc;
    cursor: pointer;
}

.permission-item:hover {
    background: #f1f6fb;
    border-color: #cbd7e5;
}

.permission-item input {
    width: 17px;
    height: 17px;
    flex: 0 0 auto;
    accent-color: var(--blue);
    cursor: pointer;
}

.permission-item-text {
    display: flex;
    flex-direction: column;
    gap: 2px;
}

.permission-item-name {
    font-size: 13px;
    font-weight: bold;
    color: #333;
}

.permission-item-key {
    font-size: 11px;
    color: var(--muted);
}

.permission-group-toggle {
    padding: 6px 9px;
    border: 1px solid #ccd3db;
    border-radius: 5px;
    background: white;
    color: #333;
    cursor: pointer;
    font-size: 11px;
    font-weight: bold;
}

.permission-group-toggle:hover {
    background: #eef4fb;
    border-color: var(--blue);
    color: var(--blue);
}

.permission-toolbar .btn:disabled {
    opacity: .55;
    cursor: not-allowed;
    transform: none;
}

.user-name {
    font-weight: bold;
    color: var(--dark-blue);
}

.username {
    font-weight: bold;
    color: var(--blue);
}

.small-muted {
    color: var(--muted);
    font-size: 11px;
    margin-top: 3px;
}

.activity-table table {
    min-width: 900px;
}

.activity-login {
    color: var(--green);
    font-weight: bold;
}

.activity-logout {
    color: var(--red);
    font-weight: bold;
}

.activity-other {
    color: var(--blue);
    font-weight: bold;
}

.role-note {
    margin-top: 6px;
    font-size: 12px;
    color: var(--muted);
}

.user-count {
    min-width: 30px;
    height: 30px;
    padding: 0 8px;
    border-radius: 20px;
    background: #eef4fb;
    color: var(--blue);
    display: inline-flex;
    align-items: center;
    justify-content: center;
    font-weight: bold;
    font-size: 12px;
}

@media(max-width:1200px) {

    .stats {
        grid-template-columns: repeat(3, 1fr);
    }

    .form-grid {
        grid-template-columns: repeat(2, 1fr);
    }

    .filter-grid {
        grid-template-columns: repeat(2, 1fr);
    }
}

@media(max-width:1000px) {

    .permission-groups {
        grid-template-columns: repeat(2, 1fr);
    }
}

@media(max-width:900px) {

    .sidebar {
        transform: translateX(-100%);
        transition: .3s;
    }

    .sidebar.show {
        transform: translateX(0);
    }

    .main {
        margin-left: 0;
    }

    .menu-toggle {
        display: flex;
        align-items: center;
        justify-content: center;
    }

    .stats {
        grid-template-columns: repeat(2, 1fr);
    }

    .permission-groups {
        grid-template-columns: 1fr;
    }
}

@media(max-width:650px) {

    .content {
        padding: 15px;
    }

    .topbar {
        padding: 10px 15px;
    }

    .topbar h1 {
        font-size: 16px;
    }

    .topbar-logo {
        width: 40px;
        height: 40px;
    }

    .admin-details {
        display: none;
    }

    .stats {
        grid-template-columns: 1fr;
    }

    .form-grid {
        grid-template-columns: 1fr;
    }

    .filter-grid {
        grid-template-columns: 1fr;
    }

    .page-header {
        flex-direction: column;
        align-items: flex-start;
        gap: 12px;
    }

    .card-header {
        flex-direction: column;
        align-items: flex-start;
        gap: 10px;
    }

    .form-buttons {
        flex-direction: column;
    }

    .form-buttons .btn {
        width: 100%;
    }
}</style>

</head>

<body>


<!-- =====================================================
     SIDEBAR
===================================================== -->

<aside class="sidebar" id="sidebar">

    <div class="sidebar-header">

        <?php if (file_exists(__DIR__ . "/norsu.png")): ?>

            <img
                src="norsu.png"
                alt="NORSU Logo"
                class="sidebar-logo"
            >

        <?php else: ?>

            <div
                class="sidebar-logo"
                style="
                    display:flex;
                    align-items:center;
                    justify-content:center;
                    background:#0057b8;
                    color:white;
                    font-weight:bold;
                    font-size:20px;
                "
            >
                NORSU
            </div>

        <?php endif; ?>

        <h2>NORSU</h2>
        <p>REGISTRAR SYSTEM</p>

    </div>


    <nav class="sidebar-menu">

        <div class="menu-title">Main</div>

        <a href="admin_dashboard.php">
            <span class="icon">🏠</span>
            Dashboard
        </a>

        <a href="students.php">
            <span class="icon">👨‍🎓</span>
            Students
        </a>

        <a href="enrollment.php">
            <span class="icon">📝</span>
            Enrollment
        </a>

        <a href="programs.php">
            <span class="icon">📚</span>
            Programs
        </a>

        <a href="grades.php">
            <span class="icon">📊</span>
            Grades
        </a>


        <div class="menu-title">Registrar Services</div>

        <a href="document_requests.php">
            <span class="icon">📄</span>
            Document Requests
        </a>

        <a href="queue.php">
            <span class="icon">🎫</span>
            Queue
        </a>

        <a href="graduation.php">
            <span class="icon">🎓</span>
            Graduation
        </a>


        <div class="menu-title">Administration</div>

        <a href="reports.php">
            <span class="icon">📈</span>
            Reports
        </a>

        <a href="users.php" class="active">
            <span class="icon">👥</span>
            Users
        </a>

        <a href="settings.php">
            <span class="icon">⚙️</span>
            Settings
        </a>

        <a href="admin_logout.php">
            <span class="icon">🚪</span>
            Logout
        </a>

    </nav>

</aside>


<!-- =====================================================
     MAIN
===================================================== -->

<main class="main">

<header class="topbar">

    <div class="topbar-left">

        <button
            class="menu-toggle"
            onclick="toggleSidebar()"
            type="button"
        >
            ☰
        </button>

        <?php if (file_exists(__DIR__ . "/norsu.png")): ?>

            <img
                src="norsu.png"
                alt="NORSU Logo"
                class="topbar-logo"
            >

        <?php endif; ?>

        <h1>Admin / User Management</h1>

    </div>


    <div class="admin-info">

        <div class="admin-details">
            <strong><?= e($adminName) ?></strong>
            <span>NORSU Registrar</span>
        </div>

        <div class="admin-avatar">
            <?= e(strtoupper(substr($adminName, 0, 1))) ?>
        </div>

    </div>

</header>


<section class="content">


    <!-- =================================================
         PAGE HEADER
    ================================================== -->

    <div class="page-header">

        <div>
            <div class="page-title">
                Admin / User Management
            </div>

            <div class="page-subtitle">
                Manage administrator accounts, registrar staff,
                roles, permissions, account status, and login activity.
            </div>
        </div>

        <?php if ($editUser): ?>

            <a
                href="users.php"
                class="btn btn-gray"
            >
                Cancel Edit
            </a>

        <?php endif; ?>

    </div>


    <!-- =================================================
         ALERTS
    ================================================== -->

    <?php if ($success !== ''): ?>

        <div class="alert alert-success">
            ✅ <?= e($success) ?>
        </div>

    <?php endif; ?>


    <?php if ($error !== ''): ?>

        <div class="alert alert-error">
            ❌ <?= e($error) ?>
        </div>

    <?php endif; ?>


    <!-- =================================================
         STATISTICS
    ================================================== -->

    <div class="stats">

        <div class="stat-card">
            <div class="stat-label">
                All Users
            </div>

            <div class="stat-number">
                <?= $userCounts['all'] ?>
            </div>
        </div>


        <div class="stat-card">
            <div class="stat-label">
                Administrators
            </div>

            <div class="stat-number">
                <?= $userCounts['admins'] ?>
            </div>
        </div>


        <div class="stat-card">
            <div class="stat-label">
                Registrar Staff
            </div>

            <div class="stat-number">
                <?= $userCounts['staff'] ?>
            </div>
        </div>


        <div class="stat-card">
            <div class="stat-label">
                Active Accounts
            </div>

            <div class="stat-number">
                <?= $userCounts['active'] ?>
            </div>
        </div>


        <div class="stat-card">
            <div class="stat-label">
                Inactive / Suspended
            </div>

            <div class="stat-number">
                <?= $userCounts['inactive'] ?>
            </div>
        </div>

    </div>


    <!-- =================================================
         ADD / EDIT USER
    ================================================== -->

    <div class="card">

        <div class="card-header">

            <h2 class="card-title">
                <?= $editUser
                    ? '✏️ Edit User Account'
                    : '➕ Add User Account' ?>
            </h2>

            <?php if ($editUser): ?>

                <a
                    href="users.php"
                    class="btn btn-gray btn-sm"
                >
                    Cancel
                </a>

            <?php endif; ?>

        </div>


        <div class="card-body">

            <form
                method="POST"
                autocomplete="off"
            >

                <input
                    type="hidden"
                    name="user_id"
                    value="<?= $editUser
                        ? (int)$editUser['id']
                        : 0 ?>"
                >


                <div class="form-grid">

                    <div class="form-group">

                        <label>
                            Username *
                        </label>

                        <input
                            type="text"
                            name="username"
                            value="<?= $editUser
                                ? e($editUser['username'])
                                : '' ?>"
                            placeholder="Enter username"
                            required
                        >

                        <small class="form-help">
                            Letters, numbers, dots, underscores, and hyphens only.
                        </small>

                    </div>


                    <div class="form-group">

                        <label>
                            Full Name *
                        </label>

                        <input
                            type="text"
                            name="full_name"
                            value="<?= $editUser
                                ? e($editUser['full_name'])
                                : '' ?>"
                            placeholder="Enter complete name"
                            required
                        >

                    </div>


                    <div class="form-group">

                        <label>
                            Email Address
                        </label>

                        <input
                            type="email"
                            name="email"
                            value="<?= $editUser
                                ? e($editUser['email'])
                                : '' ?>"
                            placeholder="example@norsu.edu.ph"
                        >

                    </div>


                    <div class="form-group">

                        <label>
                            Contact Number
                        </label>

                        <input
                            type="text"
                            name="contact_number"
                            value="<?= $editUser
                                ? e($editUser['contact_number'])
                                : '' ?>"
                            placeholder="09XXXXXXXXX"
                        >

                    </div>


                    <div class="form-group">

                        <label>
                            User Role *
                        </label>

                        <select
                            name="role"
                            id="userRole"
                            required
                        >

                            <?php foreach ($allowedRoles as $role): ?>

                                <option
                                    value="<?= e($role) ?>"
                                    <?= (
                                        $editUser
                                        && $editUser['role'] === $role
                                    )
                                    || (
                                        !$editUser
                                        && $role === 'Registrar Staff'
                                    )
                                    ? 'selected'
                                    : '' ?>
                                >
                                    <?= e($role) ?>
                                </option>

                            <?php endforeach; ?>

                        </select>

                        <div class="role-note">
                            Admin automatically receives all permissions.
                        </div>

                    </div>


                    <div class="form-group">

                        <label>
                            Account Status *
                        </label>

                        <select
                            name="status"
                            required
                        >

                            <?php foreach ($allowedStatuses as $accountStatus): ?>

                                <option
                                    value="<?= e($accountStatus) ?>"
                                    <?= (
                                        $editUser
                                        && $editUser['status'] === $accountStatus
                                    )
                                    || (
                                        !$editUser
                                        && $accountStatus === 'Active'
                                    )
                                    ? 'selected'
                                    : '' ?>
                                >
                                    <?= e($accountStatus) ?>
                                </option>

                            <?php endforeach; ?>

                        </select>

                    </div>


                    <div class="form-group full">

                        <label>
                            Password
                            <?= !$editUser ? '*' : '' ?>
                        </label>

                        <input
                            type="password"
                            name="password"
                            placeholder="<?= $editUser
                                ? 'Leave blank to keep current password'
                                : 'Enter password' ?>"
                            <?= !$editUser ? 'required' : '' ?>
                        >

                        <small class="form-help">
                            <?= $editUser
                                ? 'Leave blank if you do not want to change the current password.'
                                : 'The password will be securely encrypted using password_hash().' ?>
                        </small>

                    </div>

                </div>


                <!-- =================================================
                     PERMISSIONS
                ================================================== -->

                <div style="margin-top:25px;">

                    <div
                        style="
                            display:flex;
                            align-items:center;
                            justify-content:space-between;
                            gap:10px;
                            margin-bottom:10px;
                            flex-wrap:wrap;
                        "
                    >

                        <div>

                            <h3
                                style="
                                    color:var(--green);
                                    font-size:18px;
                                "
                            >
                                🔐 Permissions
                            </h3>

                            <div class="small-muted">
                                Select the modules and actions this account can access.
                            </div>

                        </div>

                    </div>


                    <div class="info-box">

                        <strong>Permission Guide:</strong>

                        Admin accounts automatically receive every permission.
                        For Registrar Staff and Viewer accounts, select only the
                        permissions required for their work.

                    </div>


                    <div class="permission-toolbar">

                        <div class="permission-toolbar-left">

                            <button
                                type="button"
                                class="btn btn-primary btn-sm btn-select-all"
                                onclick="selectAllPermissions()"
                            >
                                ☑️ Select All Permissions
                            </button>

                            <button
                                type="button"
                                class="btn btn-gray btn-sm btn-clear-all"
                                onclick="clearAllPermissions()"
                            >
                                ☐ Clear All Permissions
                            </button>

                        </div>


                        <div class="permission-toolbar-right">

                            <?= count($permissions) ?> available permissions

                        </div>

                    </div>


                    <div class="permission-groups">

                        <?php foreach ($permissionGroups as $groupName => $groupPermissions): ?>

                            <div class="permission-group">

                                <div class="permission-group-header">

                                    <div class="permission-group-title">
                                        <?= e($groupName) ?>
                                        <span class="user-count">
                                            <?= count($groupPermissions) ?>
                                        </span>
                                    </div>

                                    <button
                                        type="button"
                                        class="permission-group-toggle"
                                        onclick="togglePermissionGroup(this)"
                                    >
                                        Select All
                                    </button>

                                </div>


                                <div class="permission-group-list">

                                    <?php foreach ($groupPermissions as $permission): ?>

                                        <label class="permission-item">

                                            <input
                                                type="checkbox"
                                                name="permissions[]"
                                                value="<?= (int)$permission['id'] ?>"
                                                <?= in_array(
                                                    (int)$permission['id'],
                                                    $editPermissions,
                                                    true
                                                )
                                                ? 'checked'
                                                : '' ?>
                                            >

                                            <span class="permission-item-text">

                                                <span class="permission-item-name">
                                                    <?= e($permission['permission_name']) ?>
                                                </span>

                                                <span class="permission-item-key">
                                                    <?= e($permission['permission_key']) ?>
                                                </span>

                                            </span>

                                        </label>

                                    <?php endforeach; ?>

                                </div>

                            </div>

                        <?php endforeach; ?>

                    </div>

                </div>


                <div class="form-buttons">

                    <button
                        type="submit"
                        name="save_user"
                        class="btn btn-primary"
                    >
                        <?= $editUser
                            ? '💾 Update User'
                            : '➕ Add User' ?>
                    </button>


                    <?php if ($editUser): ?>

                        <a
                            href="users.php"
                            class="btn btn-gray"
                        >
                            Cancel
                        </a>

                    <?php endif; ?>

                </div>

            </form>

        </div>

    </div>


    <!-- =================================================
         USER LIST
    ================================================== -->

    <div class="card">

        <div class="card-header">

            <h2 class="card-title">
                👥 User Accounts
            </h2>

            <span class="muted">
                <?= count($users) ?> account(s) found
            </span>

        </div>


        <div class="card-body">

            <form
                method="GET"
                class="filter-grid"
            >

                <div class="filter-group">

                    <label>
                        Search
                    </label>

                    <input
                        type="text"
                        name="search"
                        value="<?= e($search) ?>"
                        placeholder="Username, name, email, contact..."
                    >

                </div>


                <div class="filter-group">

                    <label>
                        Role
                    </label>

                    <select name="role">

                        <option value="">
                            All Roles
                        </option>

                        <?php foreach ($allowedRoles as $role): ?>

                            <option
                                value="<?= e($role) ?>"
                                <?= $roleFilter === $role
                                    ? 'selected'
                                    : '' ?>
                            >
                                <?= e($role) ?>
                            </option>

                        <?php endforeach; ?>

                    </select>

                </div>


                <div class="filter-group">

                    <label>
                        Status
                    </label>

                    <select name="status">

                        <option value="">
                            All Statuses
                        </option>

                        <?php foreach ($allowedStatuses as $accountStatus): ?>

                            <option
                                value="<?= e($accountStatus) ?>"
                                <?= $statusFilter === $accountStatus
                                    ? 'selected'
                                    : '' ?>
                            >
                                <?= e($accountStatus) ?>
                            </option>

                        <?php endforeach; ?>

                    </select>

                </div>


                <div class="filter-group">

                    <label>
                        &nbsp;
                    </label>

                    <div
                        style="
                            display:flex;
                            gap:6px;
                        "
                    >

                        <button
                            type="submit"
                            class="btn btn-primary"
                        >
                            🔍 Filter
                        </button>

                        <a
                            href="users.php"
                            class="btn btn-gray"
                        >
                            Reset
                        </a>

                    </div>

                </div>

            </form>


            <div class="table-container">

                <table>

                    <thead>

                        <tr>

                            <th>
                                #
                            </th>

                            <th>
                                Username
                            </th>

                            <th>
                                Full Name
                            </th>

                            <th>
                                Contact / Email
                            </th>

                            <th>
                                Role
                            </th>

                            <th>
                                Permissions
                            </th>

                            <th>
                                Status
                            </th>

                            <th>
                                Last Login
                            </th>

                            <th>
                                Created
                            </th>

                            <th>
                                Actions
                            </th>

                        </tr>

                    </thead>


                    <tbody>

                    <?php if (empty($users)): ?>

                        <tr>

                            <td
                                colspan="10"
                                class="empty"
                            >
                                No user accounts found.
                            </td>

                        </tr>

                    <?php else: ?>

                        <?php foreach ($users as $user): ?>

                            <tr>

                                <td>
                                    <?= (int)$user['id'] ?>
                                </td>


                                <td>

                                    <div class="username">
                                        <?= e($user['username']) ?>
                                    </div>

                                    <?php if ($user['username'] === 'admin'): ?>

                                        <div class="small-muted">
                                            Default Administrator
                                        </div>

                                    <?php endif; ?>

                                </td>


                                <td>

                                    <div class="user-name">
                                        <?= e($user['full_name']) ?>
                                    </div>

                                </td>


                                <td>

                                    <div>
                                        <?= $user['contact_number'] !== ''
                                            ? e($user['contact_number'])
                                            : '—' ?>
                                    </div>

                                    <div class="small-muted">
                                        <?= $user['email'] !== ''
                                            ? e($user['email'])
                                            : 'No email' ?>
                                    </div>

                                </td>


                                <td>

                                    <?php
                                    $roleClass = 'badge-viewer';

                                    if ($user['role'] === 'Admin') {
                                        $roleClass = 'badge-admin';
                                    } elseif ($user['role'] === 'Registrar Staff') {
                                        $roleClass = 'badge-staff';
                                    }
                                    ?>

                                    <span class="badge <?= $roleClass ?>">
                                        <?= e($user['role']) ?>
                                    </span>

                                </td>


                                <td>

                                    <span class="user-count">
                                        <?= (int)$user['permission_count'] ?>
                                    </span>

                                </td>


                                <td>

                                    <?php
                                    $statusClass = 'badge-inactive';

                                    if ($user['status'] === 'Active') {
                                        $statusClass = 'badge-active';
                                    } elseif ($user['status'] === 'Suspended') {
                                        $statusClass = 'badge-suspended';
                                    }
                                    ?>

                                    <span class="badge <?= $statusClass ?>">
                                        <?= e($user['status']) ?>
                                    </span>

                                </td>


                                <td>

                                    <?= !empty($user['last_login'])
                                        ? e(date(
                                            'M d, Y h:i A',
                                            strtotime($user['last_login'])
                                        ))
                                        : 'Never' ?>

                                </td>


                                <td>

                                    <?= e(date(
                                        'M d, Y',
                                        strtotime($user['created_at'])
                                    )) ?>

                                </td>


                                <td>

                                    <div class="actions">

                                        <a
                                            href="users.php?edit=<?= (int)$user['id'] ?>"
                                            class="btn btn-primary"
                                        >
                                            ✏️ Edit
                                        </a>


                                        <?php if ($user['status'] !== 'Active'): ?>

                                            <a
                                                href="users.php?status_user=<?= (int)$user['id'] ?>&new_status=Active"
                                                class="btn btn-success"
                                                onclick="return confirm('Activate this account?');"
                                            >
                                                ✓ Activate
                                            </a>

                                        <?php else: ?>

                                            <a
                                                href="users.php?status_user=<?= (int)$user['id'] ?>&new_status=Inactive"
                                                class="btn btn-warning"
                                                onclick="return confirm('Set this account to inactive?');"
                                            >
                                                ⏸ Inactive
                                            </a>

                                        <?php endif; ?>


                                        <?php if (
                                            $user['status'] !== 'Suspended' &&
                                            $user['username'] !== 'admin'
                                        ): ?>

                                            <a
                                                href="users.php?status_user=<?= (int)$user['id'] ?>&new_status=Suspended"
                                                class="btn btn-gray"
                                                onclick="return confirm('Suspend this account?');"
                                            >
                                                🔒 Suspend
                                            </a>

                                        <?php endif; ?>


                                        <?php if ($user['username'] !== 'admin'): ?>

                                            <a
                                                href="users.php?delete_user=<?= (int)$user['id'] ?>"
                                                class="btn btn-danger"
                                                onclick="return confirm('Delete this user account? This action cannot be undone.');"
                                            >
                                                🗑 Delete
                                            </a>

                                        <?php endif; ?>

                                    </div>

                                </td>

                            </tr>

                        <?php endforeach; ?>

                    <?php endif; ?>

                    </tbody>

                </table>

            </div>

        </div>

    </div>


    <!-- =================================================
         LOGIN ACTIVITY
    ================================================== -->

    <div class="card activity-table">

        <div class="card-header">

            <h2 class="card-title">
                🕒 Login Activity
            </h2>

            <span class="muted">
                Latest 50 activity records
            </span>

        </div>


        <div class="card-body">

            <?php if (empty($loginActivities)): ?>

                <div class="empty">
                    No login activity records found.
                </div>

            <?php else: ?>

                <div class="table-wrap">

                    <table>

                        <thead>

                            <tr>

                                <th>
                                    Date / Time
                                </th>

                                <th>
                                    Username
                                </th>

                                <th>
                                    Full Name
                                </th>

                                <th>
                                    Activity
                                </th>

                                <th>
                                    IP Address
                                </th>

                                <th>
                                    User Agent
                                </th>

                            </tr>

                        </thead>


                        <tbody>

                        <?php foreach ($loginActivities as $log): ?>

                            <tr>

                                <td>

                                    <?= e(date(
                                        'M d, Y h:i A',
                                        strtotime($log['activity_date'])
                                    )) ?>

                                </td>


                                <td>

                                    <strong>
                                        <?= e($log['username']) ?>
                                    </strong>

                                </td>


                                <td>

                                    <?= e(
                                        $log['full_name']
                                        ?? 'Unknown User'
                                    ) ?>

                                </td>


                                <td>

                                    <?php
                                    $activityClass = 'activity-other';

                                    if (
                                        stripos(
                                            $log['activity'],
                                            'login'
                                        ) !== false
                                    ) {
                                        $activityClass = 'activity-login';
                                    }

                                    if (
                                        stripos(
                                            $log['activity'],
                                            'logout'
                                        ) !== false
                                    ) {
                                        $activityClass = 'activity-logout';
                                    }
                                    ?>

                                    <span class="<?= $activityClass ?>">
                                        <?= e($log['activity']) ?>
                                    </span>

                                </td>


                                <td>

                                    <?= e(
                                        $log['ip_address'] !== ''
                                        ? $log['ip_address']
                                        : '—'
                                    ) ?>

                                </td>


                                <td>

                                    <div
                                        style="
                                            max-width:350px;
                                            word-break:break-word;
                                            font-size:11px;
                                            color:#666;
                                        "
                                    >
                                        <?= e(
                                            $log['user_agent'] !== ''
                                            ? $log['user_agent']
                                            : '—'
                                        ) ?>
                                    </div>

                                </td>

                            </tr>

                        <?php endforeach; ?>

                        </tbody>

                    </table>

                </div>

            <?php endif; ?>

        </div>

    </div>


</section>

</main>


<script>

function toggleSidebar()
{
    const sidebar =
        document.getElementById("sidebar");

    sidebar.classList.toggle("show");
}


document
    .querySelectorAll(".sidebar-menu a")
    .forEach(function(link)
    {
        link.addEventListener(
            "click",
            function()
            {
                if (window.innerWidth <= 900) {

                    document
                        .getElementById("sidebar")
                        .classList
                        .remove("show");

                }
            }
        );
    });


// =====================================================
// GLOBAL PERMISSION CONTROLS
// =====================================================

function selectAllPermissions()
{
    const checkboxes =
        document.querySelectorAll(
            'input[name="permissions[]"]'
        );

    checkboxes.forEach(
        function(checkbox)
        {
            checkbox.checked = true;
        }
    );

    updatePermissionButtons();
}


function clearAllPermissions()
{
    const checkboxes =
        document.querySelectorAll(
            'input[name="permissions[]"]'
        );

    checkboxes.forEach(
        function(checkbox)
        {
            checkbox.checked = false;
        }
    );

    updatePermissionButtons();
}


// =====================================================
// PERMISSION GROUP CONTROLS
// =====================================================

function togglePermissionGroup(button)
{
    const group =
        button.closest('.permission-group');

    if (!group) {
        return;
    }

    const checkboxes =
        group.querySelectorAll(
            'input[name="permissions[]"]'
        );

    if (!checkboxes.length) {
        return;
    }

    let allChecked = true;

    checkboxes.forEach(
        function(checkbox)
        {
            if (!checkbox.checked) {
                allChecked = false;
            }
        }
    );

    checkboxes.forEach(
        function(checkbox)
        {
            checkbox.checked = !allChecked;
        }
    );

    updatePermissionButtons();
}


// =====================================================
// UPDATE PERMISSION BUTTON STATES
// =====================================================

function updatePermissionButtons()
{
    const allCheckboxes =
        Array.from(
            document.querySelectorAll(
                'input[name="permissions[]"]'
            )
        );

    const allSelected =
        allCheckboxes.length > 0 &&
        allCheckboxes.every(
            function(checkbox)
            {
                return checkbox.checked;
            }
        );

    const noneSelected =
        allCheckboxes.length > 0 &&
        allCheckboxes.every(
            function(checkbox)
            {
                return !checkbox.checked;
            }
        );


    document
        .querySelectorAll('.permission-group')
        .forEach(
            function(group)
            {
                const groupCheckboxes =
                    Array.from(
                        group.querySelectorAll(
                            'input[name="permissions[]"]'
                        )
                    );

                const button =
                    group.querySelector(
                        '.permission-group-toggle'
                    );

                if (
                    !button ||
                    !groupCheckboxes.length
                ) {
                    return;
                }

                const groupAllSelected =
                    groupCheckboxes.every(
                        function(checkbox)
                        {
                            return checkbox.checked;
                        }
                    );

                button.textContent =
                    groupAllSelected
                    ? 'Clear All'
                    : 'Select All';
            }
        );


    const selectButton =
        document.querySelector(
            '.btn-select-all'
        );

    const clearButton =
        document.querySelector(
            '.btn-clear-all'
        );


    if (selectButton) {

        selectButton.disabled =
            allSelected;

    }


    if (clearButton) {

        clearButton.disabled =
            noneSelected;

    }
}


// =====================================================
// ROLE CHANGE
// =====================================================

function handleRoleChange()
{
    const role =
        document.getElementById(
            'userRole'
        );

    if (!role) {
        return;
    }

    if (role.value === 'Admin') {

        selectAllPermissions();

    }

    updatePermissionButtons();
}


// =====================================================
// INITIALIZE
// =====================================================

document.addEventListener(
    'DOMContentLoaded',
    function()
    {
        const role =
            document.getElementById(
                'userRole'
            );

        if (role) {

            role.addEventListener(
                'change',
                handleRoleChange
            );

        }


        document
            .querySelectorAll(
                'input[name="permissions[]"]'
            )
            .forEach(
                function(checkbox)
                {
                    checkbox.addEventListener(
                        'change',
                        updatePermissionButtons
                    );
                }
            );


        updatePermissionButtons();
    }
);

</script>

</body>

</html>

<?php
$conn->close();
?>

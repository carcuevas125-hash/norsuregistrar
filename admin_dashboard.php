<?php

/*
=========================================================
    NORSU REGISTRAR SYSTEM
    ADMIN DASHBOARD

    File Name:
    admin_dashboard.php

    DATABASE:
    haha

    FEATURES:
    - NORSU Logo
    - No yellow circle around logo
    - Dashboard Statistics
    - Recent Document Requests
    - Quick Actions
    - System Summary
    - Responsive Sidebar
=========================================================
*/


// =====================================================
// DATABASE CONNECTION
// =====================================================

$host = "localhost";
$username = "root";
$password = "";
$database = "haha";

$conn = new mysqli(
    $host,
    $username,
    $password,
    $database
);

if ($conn->connect_error) {

    die(
        "Database connection failed: " .
        $conn->connect_error
    );

}

$conn->set_charset("utf8mb4");


// =====================================================
// NORSU LOGO
// =====================================================

$logoFile = "norsu.png";


// =====================================================
// HELPER FUNCTION
// =====================================================

function e($value)
{
    return htmlspecialchars(
        (string)$value,
        ENT_QUOTES,
        "UTF-8"
    );
}


// =====================================================
// CHECK IF TABLE EXISTS
// =====================================================

function tableExists(
    $conn,
    $table
) {

    $table = $conn->real_escape_string(
        $table
    );

    $result = $conn->query(
        "SHOW TABLES LIKE '$table'"
    );

    return (
        $result &&
        $result->num_rows > 0
    );
}


// =====================================================
// CHECK IF COLUMN EXISTS
// =====================================================

function columnExists(
    $conn,
    $table,
    $column
) {

    if (
        !tableExists(
            $conn,
            $table
        )
    ) {

        return false;
    }

    $table = str_replace(
        "`",
        "",
        $table
    );

    $column = $conn->real_escape_string(
        $column
    );

    $result = $conn->query(
        "SHOW COLUMNS FROM `$table`
         LIKE '$column'"
    );

    return (
        $result &&
        $result->num_rows > 0
    );
}


// =====================================================
// GET TOTAL COUNT
// =====================================================

function getCount(
    $conn,
    $table
) {

    if (
        !tableExists(
            $conn,
            $table
        )
    ) {

        return 0;
    }

    $table = str_replace(
        "`",
        "",
        $table
    );

    $result = $conn->query(
        "SELECT COUNT(*) AS total
         FROM `$table`"
    );

    if ($result) {

        $row =
            $result->fetch_assoc();

        return (int)(
            $row["total"] ?? 0
        );
    }

    return 0;
}


// =====================================================
// GET COUNT WITH CONDITION
// =====================================================

function getCountWhere(
    $conn,
    $table,
    $column,
    $value
) {

    if (
        !tableExists(
            $conn,
            $table
        ) ||
        !columnExists(
            $conn,
            $table,
            $column
        )
    ) {

        return 0;
    }

    $table = str_replace(
        "`",
        "",
        $table
    );

    $column = str_replace(
        "`",
        "",
        $column
    );

    $stmt = $conn->prepare(
        "SELECT COUNT(*) AS total
         FROM `$table`
         WHERE `$column` = ?"
    );

    if (!$stmt) {

        return 0;
    }

    $stmt->bind_param(
        "s",
        $value
    );

    $stmt->execute();

    $result =
        $stmt->get_result();

    if ($result) {

        $row =
            $result->fetch_assoc();

        $stmt->close();

        return (int)(
            $row["total"] ?? 0
        );
    }

    $stmt->close();

    return 0;
}


// =====================================================
// FORMAT DATE
// =====================================================

function formatDateTime(
    $date
) {

    if (
        empty($date)
    ) {

        return "-";
    }

    $timestamp =
        strtotime($date);

    if (!$timestamp) {

        return e($date);
    }

    return date(
        "M d, Y h:i A",
        $timestamp
    );
}


// =====================================================
// STATUS CLASS
// =====================================================

function statusClass(
    $status
) {

    $status =
        strtolower(
            trim(
                (string)$status
            )
        );

    switch ($status) {

        case "approved":
        case "completed":
        case "active":
        case "paid":

            return "status-success";

        case "pending":
        case "processing":

            return "status-warning";

        case "rejected":
        case "cancelled":
        case "declined":

            return "status-danger";

        default:

            return "status-default";
    }
}


// =====================================================
// DASHBOARD STATISTICS
// =====================================================

$totalStudents =
    getCount(
        $conn,
        "students"
    );

$totalEnrollments =
    getCount(
        $conn,
        "enrollments"
    );

$totalPrograms =
    getCount(
        $conn,
        "programs"
    );

$totalDocuments =
    getCount(
        $conn,
        "document_requests"
    );

$totalGraduating =
    getCount(
        $conn,
        "graduating_students"
    );


// =====================================================
// PENDING DOCUMENTS
// =====================================================

$pendingDocuments = 0;

if (
    tableExists(
        $conn,
        "document_requests"
    ) &&
    columnExists(
        $conn,
        "document_requests",
        "status"
    )
) {

    $pendingDocuments =
        getCountWhere(
            $conn,
            "document_requests",
            "status",
            "Pending"
        );

    if (
        $pendingDocuments === 0
    ) {

        $pendingDocuments =
            getCountWhere(
                $conn,
                "document_requests",
                "status",
                "pending"
            );
    }
}


// =====================================================
// ACTIVE ENROLLMENTS
// =====================================================

$activeEnrollments = 0;

if (
    tableExists(
        $conn,
        "enrollments"
    ) &&
    columnExists(
        $conn,
        "enrollments",
        "status"
    )
) {

    $activeEnrollments =
        getCountWhere(
            $conn,
            "enrollments",
            "status",
            "Active"
        );

    if (
        $activeEnrollments === 0
    ) {

        $activeEnrollments =
            getCountWhere(
                $conn,
                "enrollments",
                "status",
                "active"
            );
    }
}


// =====================================================
// RECENT DOCUMENT REQUESTS
// =====================================================

$recentDocuments = [];

if (
    tableExists(
        $conn,
        "document_requests"
    )
) {

    $dateColumn = null;

    if (
        columnExists(
            $conn,
            "document_requests",
            "created_at"
        )
    ) {

        $dateColumn =
            "created_at";

    }
    elseif (
        columnExists(
            $conn,
            "document_requests",
            "request_date"
        )
    ) {

        $dateColumn =
            "request_date";

    }
    elseif (
        columnExists(
            $conn,
            "document_requests",
            "date_requested"
        )
    ) {

        $dateColumn =
            "date_requested";
    }

    $orderBy = "";

    if ($dateColumn) {

        $orderBy =
            "ORDER BY `$dateColumn` DESC";
    }

    $result = $conn->query(
        "SELECT *
         FROM `document_requests`
         $orderBy
         LIMIT 5"
    );

    if ($result) {

        while (
            $row =
                $result->fetch_assoc()
        ) {

            $recentDocuments[] =
                $row;
        }
    }
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
    NORSU Registrar - Admin Dashboard
</title>


<style>

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

</style>

</head>


<body>


<!-- =====================================================
     SIDEBAR
===================================================== -->

<aside
    class="sidebar"
    id="sidebar"
>


    <!-- =================================================
         SIDEBAR HEADER
    ================================================= -->

    <div class="sidebar-header">


        <!-- NORSU LOGO -->

        <?php if (
            file_exists(
                __DIR__ . "/" . $logoFile
            )
        ): ?>

            <img
                src="<?= e($logoFile) ?>"
                alt="NORSU Logo"
                class="sidebar-logo"
            >

        <?php else: ?>

            <!-- LOGO FALLBACK -->

            <div
                class="sidebar-logo"
                style="
                    display:flex;
                    align-items:center;
                    justify-content:center;
                    background:#0057b8;
                    color:white;
                    font-size:20px;
                    font-weight:bold;
                "
            >
                NORSU
            </div>

        <?php endif; ?>


        <h2>
            NORSU
        </h2>


        <p>
            REGISTRAR SYSTEM
        </p>


    </div>


    <!-- =================================================
         SIDEBAR MENU
    ================================================= -->

    <div class="sidebar-menu">


        <div class="menu-title">
            Main
        </div>


        <a
            href="admin_dashboard.php"
            class="active"
        >

            <span class="icon">
                🏠
            </span>

            Dashboard

        </a>


        <a href="students.php">

            <span class="icon">
                👨‍🎓
            </span>

            Students

        </a>


        <a href="enrollments.php">

            <span class="icon">
                📝
            </span>

            Enrollment

        </a>


        <a href="programs.php">

            <span class="icon">
                📚
            </span>

            Programs

        </a>


        <a href="grades.php">

            <span class="icon">
                📊
            </span>

            Grades

        </a>


        <div class="menu-title">
            Registrar Services
        </div>


        <a href="document_requests.php">

            <span class="icon">
                📄
            </span>

            Document Requests

        </a>


        <a href="queue.php">

            <span class="icon">
                🎫
            </span>

            Queue

        </a>


        <a href="graduation.php">

            <span class="icon">
                🎓
            </span>

            Graduation

        </a>


        <div class="menu-title">
            Administration
        </div>


        <a href="reports.php">

            <span class="icon">
                📈
            </span>

            Reports

        </a>


        <a href="users.php">

            <span class="icon">
                👥
            </span>

            Users

        </a>


        <a href="settings.php">

            <span class="icon">
                ⚙️
            </span>

            Settings

        </a>


        <a href="admin_logout.php">

            <span class="icon">
                🚪
            </span>

            Logout

        </a>


    </div>

</aside>


<!-- =====================================================
     MAIN
===================================================== -->

<main class="main">


    <!-- =================================================
         TOPBAR
    ================================================= -->

    <header class="topbar">


        <div class="topbar-left">


            <button
                class="menu-toggle"
                onclick="toggleSidebar()"
                type="button"
            >
                ☰
            </button>


            <!-- NORSU LOGO WITHOUT YELLOW CIRCLE -->

            <?php if (
                file_exists(
                    __DIR__ . "/" . $logoFile
                )
            ): ?>

                <img
                    src="<?= e($logoFile) ?>"
                    alt="NORSU Logo"
                    class="topbar-logo"
                >

            <?php endif; ?>


            <h1>
                Admin Dashboard
            </h1>


        </div>


        <!-- =================================================
             ADMIN INFORMATION
        ================================================= -->

        <div class="admin-info">


            <div class="admin-details">

                <strong>
                    Administrator
                </strong>

                <span>
                    NORSU Registrar
                </span>

            </div>


            <!-- ADMIN AVATAR WITHOUT YELLOW BORDER -->

            <div class="admin-avatar">
                A
            </div>


        </div>


    </header>


    <!-- =================================================
         PAGE CONTENT
    ================================================= -->

    <section class="content">


        <!-- =================================================
             WELCOME
        ================================================= -->

        <div class="welcome">


            <h2>
                Welcome, Administrator!
            </h2>


            <p>
                Manage the NORSU Registrar System
                from your administrator dashboard.
            </p>


        </div>


        <!-- =================================================
             STATISTICS
        ================================================= -->

        <div class="stats-grid">


            <!-- STUDENTS -->

            <div class="stat-card">


                <div class="stat-icon">
                    👨‍🎓
                </div>


                <h3>
                    <?= e($totalStudents) ?>
                </h3>


                <p>
                    Total Students
                </p>


            </div>


            <!-- ENROLLMENTS -->

            <div class="stat-card">


                <div class="stat-icon">
                    📝
                </div>


                <h3>
                    <?= e($totalEnrollments) ?>
                </h3>


                <p>
                    Total Enrollments
                </p>


            </div>


            <!-- PROGRAMS -->

            <div class="stat-card">


                <div class="stat-icon">
                    📚
                </div>


                <h3>
                    <?= e($totalPrograms) ?>
                </h3>


                <p>
                    Programs
                </p>


            </div>


            <!-- DOCUMENTS -->

            <div class="stat-card">


                <div class="stat-icon">
                    📄
                </div>


                <h3>
                    <?= e($totalDocuments) ?>
                </h3>


                <p>
                    Document Requests
                </p>


            </div>


            <!-- GRADUATING -->

            <div class="stat-card">


                <div class="stat-icon">
                    🎓
                </div>


                <h3>
                    <?= e($totalGraduating) ?>
                </h3>


                <p>
                    Graduating Students
                </p>


            </div>


        </div>


        <!-- =================================================
             MAIN PANELS
        ================================================= -->

        <div class="panel-grid">


            <!-- =================================================
                 RECENT DOCUMENT REQUESTS
            ================================================= -->

            <div class="panel">


                <div class="panel-header">


                    <h3>
                        Recent Document Requests
                    </h3>


                    <a
                        href="document_requests.php"
                    >
                        View All
                    </a>


                </div>


                <div class="panel-body">


                    <?php if (
                        !empty(
                            $recentDocuments
                        )
                    ): ?>


                        <div class="table-container">


                            <table>


                                <thead>

                                    <tr>

                                        <th>
                                            Student
                                        </th>

                                        <th>
                                            Document
                                        </th>

                                        <th>
                                            Status
                                        </th>

                                        <th>
                                            Date
                                        </th>

                                    </tr>

                                </thead>


                                <tbody>


                                <?php foreach (
                                    $recentDocuments
                                    as $request
                                ): ?>


                                    <?php

                                    $studentName =
                                        $request[
                                            "student_name"
                                        ]
                                        ??
                                        $request[
                                            "full_name"
                                        ]
                                        ??
                                        $request[
                                            "name"
                                        ]
                                        ??
                                        "Student";


                                    $documentType =
                                        $request[
                                            "document_type"
                                        ]
                                        ??
                                        $request[
                                            "document"
                                        ]
                                        ??
                                        $request[
                                            "request_type"
                                        ]
                                        ??
                                        "Document";


                                    $status =
                                        $request[
                                            "status"
                                        ]
                                        ??
                                        "Pending";


                                    $requestDate =
                                        $request[
                                            "created_at"
                                        ]
                                        ??
                                        $request[
                                            "request_date"
                                        ]
                                        ??
                                        $request[
                                            "date_requested"
                                        ]
                                        ??
                                        "";

                                    ?>


                                    <tr>


                                        <td>
                                            <?= e(
                                                $studentName
                                            ) ?>
                                        </td>


                                        <td>
                                            <?= e(
                                                $documentType
                                            ) ?>
                                        </td>


                                        <td>

                                            <span
                                                class="
                                                    status
                                                    <?= e(
                                                        statusClass(
                                                            $status
                                                        )
                                                    )
                                                    ?>
                                                "
                                            >

                                                <?= e(
                                                    $status
                                                ) ?>

                                            </span>

                                        </td>


                                        <td>

                                            <?= formatDateTime(
                                                $requestDate
                                            ) ?>

                                        </td>


                                    </tr>


                                <?php endforeach; ?>


                                </tbody>


                            </table>


                        </div>


                    <?php else: ?>


                        <div class="empty">

                            No recent document
                            requests found.

                        </div>


                    <?php endif; ?>


                </div>


            </div>


            <!-- =================================================
                 QUICK ACTIONS
            ================================================= -->

            <div class="panel">


                <div class="panel-header">

                    <h3>
                        Quick Actions
                    </h3>

                </div>


                <div class="panel-body">


                    <div class="quick-actions">


                        <a
                            href="students.php"
                            class="quick-action"
                        >

                            <div class="qa-icon">
                                👨‍🎓
                            </div>

                            <strong>
                                Students
                            </strong>

                            <span>
                                Manage student records
                            </span>

                        </a>


                        <a
                            href="enrollments.php"
                            class="quick-action"
                        >

                            <div class="qa-icon">
                                📝
                            </div>

                            <strong>
                                Enrollment
                            </strong>

                            <span>
                                Manage enrollments
                            </span>

                        </a>


                        <a
                            href="document_requests.php"
                            class="quick-action"
                        >

                            <div class="qa-icon">
                                📄
                            </div>

                            <strong>
                                Documents
                            </strong>

                            <span>
                                Process requests
                            </span>

                        </a>


                        <a
                            href="reports.php"
                            class="quick-action"
                        >

                            <div class="qa-icon">
                                📊
                            </div>

                            <strong>
                                Reports
                            </strong>

                            <span>
                                View system reports
                            </span>

                        </a>


                    </div>


                </div>


            </div>


        </div>


        <!-- =================================================
             SYSTEM SUMMARY
        ================================================= -->

        <div
            class="panel"
            style="margin-top:25px;"
        >


            <div class="panel-header">

                <h3>
                    System Summary
                </h3>

            </div>


            <div class="panel-body">


                <div class="summary-grid">


                    <!-- PENDING DOCUMENTS -->

                    <div class="summary-box">


                        <strong>
                            Pending Documents
                        </strong>


                        <div class="summary-number">

                            <?= e(
                                $pendingDocuments
                            ) ?>

                        </div>


                    </div>


                    <!-- ACTIVE ENROLLMENTS -->

                    <div class="summary-box">


                        <strong>
                            Active Enrollments
                        </strong>


                        <div class="summary-number">

                            <?= e(
                                $activeEnrollments
                            ) ?>

                        </div>


                    </div>


                    <!-- GRADUATING -->

                    <div class="summary-box">


                        <strong>
                            Graduating Students
                        </strong>


                        <div class="summary-number">

                            <?= e(
                                $totalGraduating
                            ) ?>

                        </div>


                    </div>


                </div>


            </div>


        </div>


    </section>


</main>


<!-- =====================================================
     JAVASCRIPT
===================================================== -->

<script>


// =====================================================
// TOGGLE SIDEBAR
// =====================================================

function toggleSidebar()
{

    const sidebar =
        document.getElementById(
            "sidebar"
        );

    sidebar.classList.toggle(
        "show"
    );
}


// =====================================================
// CLOSE SIDEBAR WHEN CLICKING OUTSIDE
// =====================================================

document.addEventListener(
    "click",
    function(event)
    {

        const sidebar =
            document.getElementById(
                "sidebar"
            );


        const button =
            document.querySelector(
                ".menu-toggle"
            );


        if (
            window.innerWidth <= 950 &&
            sidebar.classList.contains("show") &&
            !sidebar.contains(event.target) &&
            !button.contains(event.target)
        ) {

            sidebar.classList.remove(
                "show"
            );

        }

    }
);

</script>


</body>

</html>

<?php

// =====================================================
// CLOSE DATABASE CONNECTION
// =====================================================

$conn->close();

?>
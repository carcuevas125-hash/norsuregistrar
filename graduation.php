<?php
session_start();

/*
=========================================================
    NORSU REGISTRAR SYSTEM
    GRADUATION MANAGEMENT

    File Name:
    graduation.php

    Database:
    haha

    FEATURES:
    - Graduating Students
    - Graduation Applicants
    - Graduation Requirements
    - Approved Graduates
    - Graduation Records
    - Add Graduation Applicant
    - Edit Applicant
    - Approve / Reject Applicant
    - Mark Requirements
    - Mark as Graduating / Not Graduating
    - Search and Filter
    - Delete Graduation Records
=========================================================
*/

mysqli_report(MYSQLI_REPORT_OFF);

/* =====================================================
   DATABASE CONNECTION
===================================================== */
$conn = new mysqli("localhost", "root", "", "haha");

if ($conn->connect_error) {
    die("Database connection failed: " . $conn->connect_error);
}

$conn->set_charset("utf8mb4");

/* =====================================================
   CREATE TABLE
===================================================== */
$conn->query("
CREATE TABLE IF NOT EXISTS graduation_records (
    id INT AUTO_INCREMENT PRIMARY KEY,
    application_no VARCHAR(50) NOT NULL UNIQUE,
    student_number VARCHAR(100) NOT NULL,
    student_name VARCHAR(255) NOT NULL,
    program VARCHAR(255) NOT NULL,
    year_level VARCHAR(100) DEFAULT '',
    graduation_year VARCHAR(20) NOT NULL,
    graduation_term VARCHAR(100) DEFAULT '',
    application_date DATE NOT NULL,
    status ENUM(
        'Applicant',
        'Graduating',
        'Approved',
        'Rejected',
        'Not Graduating'
    ) NOT NULL DEFAULT 'Applicant',
    requirements_status ENUM(
        'Incomplete',
        'Complete'
    ) NOT NULL DEFAULT 'Incomplete',
    tor_status TINYINT(1) NOT NULL DEFAULT 0,
    clearance_status TINYINT(1) NOT NULL DEFAULT 0,
    grades_status TINYINT(1) NOT NULL DEFAULT 0,
    financial_status TINYINT(1) NOT NULL DEFAULT 0,
    graduation_status TINYINT(1) NOT NULL DEFAULT 0,
    remarks VARCHAR(1000) DEFAULT '',
    approved_date DATE NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
        ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_student_number (student_number),
    INDEX idx_status (status),
    INDEX idx_grad_year (graduation_year),
    INDEX idx_requirements (requirements_status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
");

/* =====================================================
   HELPERS
===================================================== */
function e($value) {
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function redirectPage($query = '') {
    $url = basename($_SERVER['PHP_SELF']);
    if ($query !== '') {
        $url .= '?' . $query;
    }
    header("Location: " . $url);
    exit;
}

function generateApplicationNo($conn) {
    $prefix = 'GRAD-' . date('Y') . '-';

    $stmt = $conn->prepare("
        SELECT application_no
        FROM graduation_records
        WHERE application_no LIKE ?
        ORDER BY id DESC
        LIMIT 1
    ");

    $lastNumber = 0;

    if ($stmt) {
        $like = $prefix . '%';
        $stmt->bind_param("s", $like);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result && ($row = $result->fetch_assoc())) {
            $lastNumber = (int)substr($row['application_no'], -5);
        }

        $stmt->close();
    }

    return $prefix . str_pad($lastNumber + 1, 5, '0', STR_PAD_LEFT);
}

function statusClass($status) {
    $map = [
        'Applicant' => 'applicant',
        'Graduating' => 'graduating',
        'Approved' => 'approved',
        'Rejected' => 'rejected',
        'Not Graduating' => 'not-graduating'
    ];

    return $map[$status] ?? 'applicant';
}

/* =====================================================
   PROGRAMS
===================================================== */
$programs = [
    'Bachelor of Science in Information Technology',
    'Bachelor of Science in Computer Science',
    'Bachelor of Science in Business Administration',
    'Bachelor of Science in Accountancy',
    'Bachelor of Science in Hospitality Management',
    'Bachelor of Science in Education',
    'Bachelor of Arts in Communication',
    'Other Program'
];

/* =====================================================
   YEAR LEVELS
===================================================== */
$yearLevels = [
    '4th Year',
    '5th Year',
    'Other'
];

/* =====================================================
   ACTION HANDLERS
===================================================== */
$error = '';

/* ADD APPLICANT */
if (isset($_POST['add_applicant'])) {

    $studentNumber  = trim($_POST['student_number'] ?? '');
    $studentName    = trim($_POST['student_name'] ?? '');
    $program        = trim($_POST['program'] ?? '');
    $yearLevel      = trim($_POST['year_level'] ?? '');
    $graduationYear = trim($_POST['graduation_year'] ?? date('Y'));
    $graduationTerm = trim($_POST['graduation_term'] ?? '');
    $applicationDate = trim($_POST['application_date'] ?? date('Y-m-d'));
    $remarks        = trim($_POST['remarks'] ?? '');

    if ($studentNumber === '' || $studentName === '' || $program === '') {
        $error = "Student number, student name, and program are required.";
    } else {

        $applicationNo = generateApplicationNo($conn);

        $stmt = $conn->prepare("
            INSERT INTO graduation_records
            (
                application_no,
                student_number,
                student_name,
                program,
                year_level,
                graduation_year,
                graduation_term,
                application_date,
                status,
                requirements_status,
                remarks
            )
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'Applicant', 'Incomplete', ?)
        ");

        if ($stmt) {
            $stmt->bind_param(
                "sssssssss",
                $applicationNo,
                $studentNumber,
                $studentName,
                $program,
                $yearLevel,
                $graduationYear,
                $graduationTerm,
                $applicationDate,
                $remarks
            );

            if ($stmt->execute()) {
                $stmt->close();
                redirectPage("message=added");
            }

            $stmt->close();
        }

        $error = "Unable to save the graduation applicant.";
    }
}

/* EDIT APPLICANT */
if (isset($_POST['edit_applicant'])) {

    $id             = (int)($_POST['id'] ?? 0);
    $studentNumber  = trim($_POST['student_number'] ?? '');
    $studentName    = trim($_POST['student_name'] ?? '');
    $program        = trim($_POST['program'] ?? '');
    $yearLevel      = trim($_POST['year_level'] ?? '');
    $graduationYear = trim($_POST['graduation_year'] ?? '');
    $graduationTerm = trim($_POST['graduation_term'] ?? '');
    $applicationDate = trim($_POST['application_date'] ?? '');
    $remarks        = trim($_POST['remarks'] ?? '');

    if ($id <= 0 || $studentNumber === '' || $studentName === '' || $program === '') {
        $error = "Please provide all required applicant information.";
    } else {

        $stmt = $conn->prepare("
            UPDATE graduation_records
            SET student_number = ?,
                student_name = ?,
                program = ?,
                year_level = ?,
                graduation_year = ?,
                graduation_term = ?,
                application_date = ?,
                remarks = ?
            WHERE id = ?
        ");

        if ($stmt) {
            $stmt->bind_param(
                "ssssssssi",
                $studentNumber,
                $studentName,
                $program,
                $yearLevel,
                $graduationYear,
                $graduationTerm,
                $applicationDate,
                $remarks,
                $id
            );

            if ($stmt->execute()) {
                $stmt->close();
                redirectPage("message=updated");
            }

            $stmt->close();
        }

        $error = "Unable to update the graduation record.";
    }
}

/* UPDATE REQUIREMENTS */
if (isset($_POST['update_requirements'])) {

    $id = (int)($_POST['id'] ?? 0);

    $tor = isset($_POST['tor_status']) ? 1 : 0;
    $clearance = isset($_POST['clearance_status']) ? 1 : 0;
    $grades = isset($_POST['grades_status']) ? 1 : 0;
    $financial = isset($_POST['financial_status']) ? 1 : 0;
    $graduation = isset($_POST['graduation_status']) ? 1 : 0;

    $complete = (
        $tor &&
        $clearance &&
        $grades &&
        $financial &&
        $graduation
    );

    $requirementsStatus = $complete ? 'Complete' : 'Incomplete';

    $stmt = $conn->prepare("
        UPDATE graduation_records
        SET tor_status = ?,
            clearance_status = ?,
            grades_status = ?,
            financial_status = ?,
            graduation_status = ?,
            requirements_status = ?
        WHERE id = ?
    ");

    if ($stmt) {
        $stmt->bind_param(
            "iiiiisi",
            $tor,
            $clearance,
            $grades,
            $financial,
            $graduation,
            $requirementsStatus,
            $id
        );

        if ($stmt->execute()) {
            $stmt->close();
            redirectPage("message=requirements");
        }

        $stmt->close();
    }

    $error = "Unable to update graduation requirements.";
}

/* MARK GRADUATING */
if (isset($_POST['mark_graduating'])) {

    $id = (int)($_POST['id'] ?? 0);

    $stmt = $conn->prepare("
        UPDATE graduation_records
        SET status = 'Graduating'
        WHERE id = ?
        AND requirements_status = 'Complete'
        AND status NOT IN ('Approved')
    ");

    if ($stmt) {
        $stmt->bind_param("i", $id);

        if ($stmt->execute() && $stmt->affected_rows > 0) {
            $stmt->close();
            redirectPage("message=graduating");
        }

        $stmt->close();
    }

    $error = "The student must have complete graduation requirements before being marked as graduating.";
}

/* APPROVE GRADUATE */
if (isset($_POST['approve_graduate'])) {

    $id = (int)($_POST['id'] ?? 0);

    $stmt = $conn->prepare("
        UPDATE graduation_records
        SET status = 'Approved',
            approved_date = CURDATE()
        WHERE id = ?
        AND requirements_status = 'Complete'
        AND status IN ('Applicant','Graduating')
    ");

    if ($stmt) {
        $stmt->bind_param("i", $id);

        if ($stmt->execute() && $stmt->affected_rows > 0) {
            $stmt->close();
            redirectPage("message=approved");
        }

        $stmt->close();
    }

    $error = "Only applicants with complete requirements can be approved.";
}

/* REJECT */
if (isset($_POST['reject_graduate'])) {

    $id = (int)($_POST['id'] ?? 0);
    $rejectRemarks = trim($_POST['reject_remarks'] ?? '');

    $stmt = $conn->prepare("
        UPDATE graduation_records
        SET status = 'Rejected',
            remarks = ?
        WHERE id = ?
        AND status NOT IN ('Approved')
    ");

    if ($stmt) {
        $stmt->bind_param("si", $rejectRemarks, $id);

        if ($stmt->execute()) {
            $stmt->close();
            redirectPage("message=rejected");
        }

        $stmt->close();
    }

    $error = "Unable to reject this application.";
}

/* MARK NOT GRADUATING */
if (isset($_POST['mark_not_graduating'])) {

    $id = (int)($_POST['id'] ?? 0);

    $stmt = $conn->prepare("
        UPDATE graduation_records
        SET status = 'Not Graduating'
        WHERE id = ?
        AND status NOT IN ('Approved')
    ");

    if ($stmt) {
        $stmt->bind_param("i", $id);

        if ($stmt->execute()) {
            $stmt->close();
            redirectPage("message=not_graduating");
        }

        $stmt->close();
    }

    $error = "Unable to update graduation status.";
}

/* DELETE */
if (isset($_POST['delete_record'])) {

    $id = (int)($_POST['id'] ?? 0);

    $stmt = $conn->prepare("DELETE FROM graduation_records WHERE id = ?");

    if ($stmt) {
        $stmt->bind_param("i", $id);

        if ($stmt->execute()) {
            $stmt->close();
            redirectPage("message=deleted");
        }

        $stmt->close();
    }

    $error = "Unable to delete the graduation record.";
}

/* =====================================================
   COUNTS
===================================================== */
$counts = [
    'all' => 0,
    'applicant' => 0,
    'graduating' => 0,
    'approved' => 0,
    'complete' => 0
];

$countResult = $conn->query("
    SELECT
        COUNT(*) AS all_count,
        SUM(status = 'Applicant') AS applicant_count,
        SUM(status = 'Graduating') AS graduating_count,
        SUM(status = 'Approved') AS approved_count,
        SUM(requirements_status = 'Complete') AS complete_count
    FROM graduation_records
");

if ($countResult && ($row = $countResult->fetch_assoc())) {
    $counts['all'] = (int)$row['all_count'];
    $counts['applicant'] = (int)$row['applicant_count'];
    $counts['graduating'] = (int)$row['graduating_count'];
    $counts['approved'] = (int)$row['approved_count'];
    $counts['complete'] = (int)$row['complete_count'];
}

/* =====================================================
   SEARCH / FILTER
===================================================== */
$search = trim($_GET['search'] ?? '');
$statusFilter = trim($_GET['status'] ?? '');
$yearFilter = trim($_GET['graduation_year'] ?? '');

$allowedStatuses = [
    'Applicant',
    'Graduating',
    'Approved',
    'Rejected',
    'Not Graduating'
];

$records = [];

$sql = "
    SELECT *
    FROM graduation_records
    WHERE 1=1
";

$params = [];
$types = '';

if ($search !== '') {
    $sql .= "
        AND (
            application_no LIKE ?
            OR student_number LIKE ?
            OR student_name LIKE ?
            OR program LIKE ?
        )
    ";

    $like = '%' . $search . '%';

    $params[] = $like;
    $params[] = $like;
    $params[] = $like;
    $params[] = $like;

    $types .= 'ssss';
}

if (in_array($statusFilter, $allowedStatuses, true)) {
    $sql .= " AND status = ?";
    $params[] = $statusFilter;
    $types .= 's';
}

if ($yearFilter !== '') {
    $sql .= " AND graduation_year = ?";
    $params[] = $yearFilter;
    $types .= 's';
}

$sql .= " ORDER BY application_date DESC, id DESC";

$stmt = $conn->prepare($sql);

if ($stmt) {

    if (!empty($params)) {
        $stmt->bind_param($types, ...$params);
    }

    $stmt->execute();

    $result = $stmt->get_result();

    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $records[] = $row;
        }
    }

    $stmt->close();
}

/* YEARS FOR FILTER */
$years = [];

$yearResult = $conn->query("
    SELECT DISTINCT graduation_year
    FROM graduation_records
    ORDER BY graduation_year DESC
");

if ($yearResult) {
    while ($row = $yearResult->fetch_assoc()) {
        $years[] = $row['graduation_year'];
    }
}

/* CURRENT ADMIN */
$adminName = $_SESSION['admin_username']
    ?? $_SESSION['username']
    ?? 'Administrator';

?>
<!DOCTYPE html>
<html lang="en">
<head>

<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">

<title>Graduation Management | NORSU Registrar System</title>

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

.form-group textarea {
    min-height: 90px;
    resize: vertical;
}

.form-group input:focus,
.form-group select:focus,
.form-group textarea:focus {
    outline: none;
    border-color: var(--blue);
    box-shadow: 0 0 0 3px rgba(0,87,184,.10);
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

.badge-passed {
    background: #dcfce7;
    color: #166534;
}

.badge-failed {
    background: #fee2e2;
    color: #991b1b;
}

.badge-incomplete {
    background: #fff3cd;
    color: #856404;
}

.badge-corrected {
    background: #e0e7ff;
    color: #3730a3;
}

.filter-grid {
    display: grid;
    grid-template-columns: 2fr 1fr 1fr 1fr auto;
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

.report-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 24px;
}

.correction-table table {
    min-width: 900px;
}

@media(max-width: 1200px) {
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

@media(max-width: 900px) {
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

    .report-grid {
        grid-template-columns: 1fr;
    }
}

@media(max-width: 650px) {

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
}

.type-grid { display:grid; grid-template-columns:repeat(4,1fr); gap:14px; }

.type-card { border:1px solid var(--border); border-radius:10px; padding:16px; background:#fff; display:flex; align-items:center; justify-content:space-between; gap:12px; transition:.2s; }

.type-card:hover { transform:translateY(-2px); box-shadow:0 5px 14px rgba(0,0,0,.08); }

.type-card strong { font-size:14px; line-height:1.4; }

.type-card span { min-width:34px; height:34px; border-radius:50%; display:flex; align-items:center; justify-content:center; background:#eef4fb; color:var(--blue); font-weight:bold; }

.filters { display:grid; grid-template-columns:2fr 1fr 1.5fr auto; gap:15px; align-items:end; }

.filter-buttons { display:flex; gap:8px; flex-wrap:wrap; }

.form-control { width:100%; padding:11px 12px; border:1px solid #d6d6d6; border-radius:7px; background:#fff; color:var(--text); outline:none; font-size:14px; }

.form-control:focus { border-color:var(--blue); box-shadow:0 0 0 3px rgba(0,87,184,.10); }

textarea.form-control { min-height:95px; resize:vertical; }

.muted { color:var(--muted); font-size:12px; }

.btn-sm { padding:7px 10px; font-size:12px; }

.btn-light { background:#f2f4f7; color:#333; border:1px solid #d8dce2; }

.btn-light:hover { background:#e7eaee; }

.request-number { font-weight:bold; color:var(--blue); }

.student-name, .document-type { font-weight:600; }

.table-wrap, .table-container { width:100%; overflow-x:auto; }

.badge-pending, .badge-approved, .badge-released, .badge-rejected { display:inline-block; padding:5px 9px; border-radius:999px; font-size:12px; font-weight:bold; white-space:nowrap; }

.badge-pending { background:#fff3cd; color:#856404; }

.badge-approved { background:#d1e7dd; color:#0f5132; }

.badge-released { background:#cfe2ff; color:#084298; }

.badge-rejected { background:#f8d7da; color:#842029; }

.detail-grid { display:grid; grid-template-columns:repeat(2,1fr); gap:16px; }

.detail-item { border:1px solid var(--border); border-radius:8px; padding:13px; background:#fafafa; }

.detail-label { font-size:11px; color:var(--muted); text-transform:uppercase; letter-spacing:.4px; margin-bottom:5px; font-weight:bold; }

.detail-value { font-size:14px; line-height:1.5; word-break:break-word; }

.history-table { width:100%; border-collapse:collapse; }

.history-table th, .history-table td { padding:10px 12px; border-bottom:1px solid var(--border); text-align:left; vertical-align:top; font-size:13px; }

.modal-overlay { position:fixed; inset:0; background:rgba(0,0,0,.55); display:flex; align-items:center; justify-content:center; padding:20px; z-index:3000; }

.modal { width:min(720px,100%); max-height:90vh; overflow-y:auto; background:#fff; border-radius:12px; box-shadow:0 15px 45px rgba(0,0,0,.25); }

.modal-header { padding:16px 20px; border-bottom:1px solid var(--border); display:flex; justify-content:space-between; align-items:center; gap:10px; }

.modal-header h3 { font-size:18px; }

.modal-body { padding:20px; }

.close { font-size:26px; color:var(--muted); line-height:1; }

.close:hover { color:var(--red); }

@media (max-width:1000px) { .type-grid { grid-template-columns:repeat(2,1fr); } .filters { grid-template-columns:repeat(2,1fr); } }

@media (max-width:768px) { .type-grid, .filters, .detail-grid { grid-template-columns:1fr; } .filter-buttons .btn { flex:1; } }

.photo-preview {
            width: 120px;
            height: 120px;
            object-fit: cover;
            border: 1px solid #ddd;
            border-radius: 4px;
            display: block;
            background: #f7f7f7;
        }

.detail-photo {
            width: 150px;
            height: 150px;
        }

.form-help {
            display: block;
            margin-top: 6px;
            color: #777;
            font-size: 12px;
            line-height: 1.4;
        }

.photo-current {
            margin-top: 5px;
            font-size: 12px;
            color: #555;
        }

.requirement-grid {
    display:grid;
    grid-template-columns:repeat(2,minmax(0,1fr));
    gap:12px;
    margin-top:10px;
}

.requirement {
    border:1px solid var(--border);
    background:#fafafa;
    border-radius:8px;
    padding:14px;
}

.requirement label { display:flex; align-items:center; gap:10px; font-weight:600; cursor:pointer; }

.requirement input[type="checkbox"] { width:18px; height:18px; accent-color:var(--blue); }

.action-group { display:flex; flex-wrap:wrap; gap:6px; }

.action-group form { margin:0; }

.application-no { font-weight:700; color:var(--blue); }

.student-name { font-weight:700; }

.program-name { font-weight:600; }

.badge-applicant { background:#e8f1ff; color:var(--blue); }

.badge-graduating { background:#fff3cd; color:#856404; }

.badge-not-graduating { background:#e2e3e5; color:#41464b; }

.badge-complete { background:#d1e7dd; color:#0f5132; }

.error-message { background:#f8d7da; color:#842029; border:1px solid #f1aeb5; border-radius:6px; padding:12px 15px; margin-bottom:18px; }

@media (max-width: 900px) {
    .requirement-grid { grid-template-columns:1fr; }
}

.stat-card.applicant { border-left-color:var(--blue); }

.stat-card.graduating { border-left-color:var(--yellow); }

.stat-card.approved { border-left-color:var(--green); }

.stat-card.complete { border-left-color:#6f42c1; }</style>

</head>

<body>

<!-- =====================================================
     SIDEBAR
===================================================== -->
<aside class="sidebar" id="sidebar">

    <div class="sidebar-header">

        <?php if (file_exists(__DIR__ . "/norsu.png")): ?>
            <img src="norsu.png" alt="NORSU Logo" class="sidebar-logo">
        <?php else: ?>
            <div class="sidebar-logo" style="display:flex;align-items:center;justify-content:center;background:#0057b8;color:white;font-weight:bold;font-size:20px;">NORSU</div>
        <?php endif; ?>

        <h2>NORSU</h2>
        <p>REGISTRAR SYSTEM</p>
    </div>

    <nav class="sidebar-menu">
        <div class="menu-title">Main</div>
        <a href="admin_dashboard.php"><span class="icon">🏠</span>Dashboard</a>
        <a href="students.php"><span class="icon">👨‍🎓</span>Students</a>

        <div class="menu-title">Registrar Services</div>
        <a href="enrollment.php"><span class="icon">📝</span>Enrollment</a>
        <a href="programs.php"><span class="icon">📚</span>Programs</a>
        <a href="grades.php"><span class="icon">📊</span>Grades</a>
        <a href="document_requests.php"><span class="icon">📄</span>Document Requests</a>
        <a href="queue.php"><span class="icon">🎫</span>Queue</a>
        <a href="graduation.php" class="active"><span class="icon">🎓</span>Graduation</a>

        <div class="menu-title">Administration</div>
        <a href="reports.php"><span class="icon">📈</span>Reports</a>
        <a href="users.php"><span class="icon">👥</span>Users</a>
        <a href="settings.php"><span class="icon">⚙️</span>Settings</a>
        <a href="admin_logout.php"><span class="icon">🚪</span>Logout</a>
    </nav>

</aside>

<!-- =====================================================
     MAIN
===================================================== -->
<main class="main">

<header class="topbar">
    <div class="topbar-left">
        <button class="menu-toggle" onclick="toggleSidebar()" type="button">☰</button>
        <?php if (file_exists(__DIR__ . "/norsu.png")): ?>
            <img src="norsu.png" alt="NORSU Logo" class="topbar-logo">
        <?php endif; ?>
        <h1>Graduation Management</h1>
    </div>

    <div class="admin-info">
        <div class="admin-details">
            <strong><?= e($adminName) ?></strong>
            <span>NORSU Registrar</span>
        </div>
        <div class="admin-avatar">A</div>
    </div>
</header>

<section class="content">


    

        <div class="page-header">
            <div>
                <div class="page-title">🎓 Graduation Management</div>
                <div class="page-subtitle">Manage graduating students, graduation applications, requirements, approvals, and graduation records.</div>
            </div>
        </div>

        <?php if (isset($_GET['message'])): ?>

            <div class="alert alert-success">

                <?php
                $message = $_GET['message'];

                $messages = [
                    'added' => 'Graduation applicant added successfully.',
                    'updated' => 'Graduation applicant updated successfully.',
                    'requirements' => 'Graduation requirements updated successfully.',
                    'graduating' => 'Student marked as graduating successfully.',
                    'approved' => 'Graduate approved successfully.',
                    'rejected' => 'Graduation application rejected.',
                    'not_graduating' => 'Graduation status updated successfully.',
                    'deleted' => 'Graduation record deleted successfully.'
                ];

                echo e(
                    $messages[$message]
                    ?? 'Operation completed successfully.'
                );
                ?>

            </div>

        <?php endif; ?>

        <?php if ($error !== ''): ?>

            <div class="alert alert-error">
                <?= e($error) ?>
            </div>

        <?php endif; ?>

        <!-- =================================================
             STATISTICS
        ================================================= -->
        <div class="stats">

            <div class="stat-card">
                <div class="stat-label">
                    Graduation Records
                </div>

                <div class="stat-number">
                    <?= number_format($counts['all']) ?>
                </div>
            </div>

            <div class="stat-card applicant">
                <div class="stat-label">
                    Graduation Applicants
                </div>

                <div class="stat-number">
                    <?= number_format($counts['applicant']) ?>
                </div>
            </div>

            <div class="stat-card graduating">
                <div class="stat-label">
                    Graduating Students
                </div>

                <div class="stat-number">
                    <?= number_format($counts['graduating']) ?>
                </div>
            </div>

            <div class="stat-card approved">
                <div class="stat-label">
                    Approved Graduates
                </div>

                <div class="stat-number">
                    <?= number_format($counts['approved']) ?>
                </div>
            </div>

            <div class="stat-card complete">
                <div class="stat-label">
                    Complete Requirements
                </div>

                <div class="stat-number">
                    <?= number_format($counts['complete']) ?>
                </div>
            </div>

        </div>

        <!-- =================================================
             ADD GRADUATION APPLICANT
        ================================================= -->
        <div class="card">

            <div class="card-header">

                <h3>
                    🎓 Add Graduation Applicant
                </h3>

            </div>

            <div class="card-body">

                <form method="POST">

                    <div class="form-grid">

                        <div class="form-group">
                            <label>
                                Student Number *
                            </label>

                            <input
                                type="text"
                                name="student_number"
                                class="form-control"
                                placeholder="Enter student number"
                                required
                            >
                        </div>

                        <div class="form-group">
                            <label>
                                Student Name *
                            </label>

                            <input
                                type="text"
                                name="student_name"
                                class="form-control"
                                placeholder="Enter complete name"
                                required
                            >
                        </div>

                        <div class="form-group">
                            <label>
                                Program *
                            </label>

                            <select
                                name="program"
                                class="form-control"
                                required
                            >

                                <option value="">
                                    -- Select Program --
                                </option>

                                <?php foreach ($programs as $program): ?>

                                    <option value="<?= e($program) ?>">
                                        <?= e($program) ?>
                                    </option>

                                <?php endforeach; ?>

                            </select>
                        </div>

                        <div class="form-group">
                            <label>
                                Year Level
                            </label>

                            <select
                                name="year_level"
                                class="form-control"
                            >

                                <?php foreach ($yearLevels as $level): ?>

                                    <option value="<?= e($level) ?>">
                                        <?= e($level) ?>
                                    </option>

                                <?php endforeach; ?>

                            </select>
                        </div>

                        <div class="form-group">
                            <label>
                                Graduation Year *
                            </label>

                            <input
                                type="text"
                                name="graduation_year"
                                class="form-control"
                                value="<?= date('Y') ?>"
                                required
                            >
                        </div>

                        <div class="form-group">
                            <label>
                                Graduation Term
                            </label>

                            <select
                                name="graduation_term"
                                class="form-control"
                            >

                                <option value="">
                                    -- Select Term --
                                </option>

                                <option value="1st Semester">
                                    1st Semester
                                </option>

                                <option value="2nd Semester">
                                    2nd Semester
                                </option>

                                <option value="Summer">
                                    Summer
                                </option>

                            </select>
                        </div>

                        <div class="form-group">
                            <label>
                                Application Date
                            </label>

                            <input
                                type="date"
                                name="application_date"
                                class="form-control"
                                value="<?= date('Y-m-d') ?>"
                            >
                        </div>

                        <div class="form-group">
                            <label>
                                Remarks
                            </label>

                            <input
                                type="text"
                                name="remarks"
                                class="form-control"
                                placeholder="Optional remarks"
                            >
                        </div>

                    </div>

                    <div class="form-actions">

                        <button
                            type="submit"
                            name="add_applicant"
                            class="btn btn-primary"
                        >
                            ➕ Add Applicant
                        </button>

                        <button
                            type="reset"
                            class="btn btn-light"
                        >
                            Clear
                        </button>

                    </div>

                </form>

            </div>

        </div>

        <!-- =================================================
             GRADUATION REQUIREMENTS
        ================================================= -->
        <div class="card">

            <div class="card-header">

                <h3>
                    📋 Graduation Requirements
                </h3>

                <span style="font-size:12px;color:#777;">
                    Select a record below to update its requirements.
                </span>

            </div>

            <div class="card-body">

                <?php
                $requirementRecord = null;

                if (isset($_GET['requirements_id'])) {
                    $requirementRecord = null;
                    $reqId = (int)$_GET['requirements_id'];

                    $reqStmt = $conn->prepare("
                        SELECT *
                        FROM graduation_records
                        WHERE id = ?
                    ");

                    if ($reqStmt) {
                        $reqStmt->bind_param("i", $reqId);
                        $reqStmt->execute();
                        $reqResult = $reqStmt->get_result();

                        if ($reqResult) {
                            $requirementRecord = $reqResult->fetch_assoc();
                        }

                        $reqStmt->close();
                    }
                }
                ?>

                <?php if ($requirementRecord): ?>

                    <form method="POST">

                        <input
                            type="hidden"
                            name="id"
                            value="<?= (int)$requirementRecord['id'] ?>"
                        >

                        <div style="margin-bottom:15px;">

                            <strong>
                                <?= e($requirementRecord['student_name']) ?>
                            </strong>

                            <span style="color:#777;">
                                —
                                <?= e($requirementRecord['student_number']) ?>
                            </span>

                            <br>

                            <small style="color:#777;">
                                <?= e($requirementRecord['program']) ?>
                            </small>

                        </div>

                        <div class="requirement-grid">

                            <div class="requirement">
                                <label>
                                    <input
                                        type="checkbox"
                                        name="tor_status"
                                        <?= $requirementRecord['tor_status'] ? 'checked' : '' ?>
                                    >
                                    TOR / Academic Record
                                </label>
                            </div>

                            <div class="requirement">
                                <label>
                                    <input
                                        type="checkbox"
                                        name="clearance_status"
                                        <?= $requirementRecord['clearance_status'] ? 'checked' : '' ?>
                                    >
                                    Clearance
                                </label>
                            </div>

                            <div class="requirement">
                                <label>
                                    <input
                                        type="checkbox"
                                        name="grades_status"
                                        <?= $requirementRecord['grades_status'] ? 'checked' : '' ?>
                                    >
                                    Grades Completed
                                </label>
                            </div>

                            <div class="requirement">
                                <label>
                                    <input
                                        type="checkbox"
                                        name="financial_status"
                                        <?= $requirementRecord['financial_status'] ? 'checked' : '' ?>
                                    >
                                    Financial Clearance
                                </label>
                            </div>

                            <div class="requirement">
                                <label>
                                    <input
                                        type="checkbox"
                                        name="graduation_status"
                                        <?= $requirementRecord['graduation_status'] ? 'checked' : '' ?>
                                    >
                                    Graduation Requirement
                                </label>
                            </div>

                        </div>

                        <div class="form-actions">

                            <button
                                type="submit"
                                name="update_requirements"
                                class="btn btn-primary"
                            >
                                💾 Save Requirements
                            </button>

                            <a
                                href="graduation.php"
                                class="btn btn-light"
                            >
                                Cancel
                            </a>

                        </div>

                    </form>

                <?php else: ?>

                    <div class="empty">
                        Click the <strong>Requirements</strong> button
                        in a graduation record to manage that student's requirements.
                    </div>

                <?php endif; ?>

            </div>

        </div>

        <!-- =================================================
             GRADUATION RECORDS
        ================================================= -->
        <div class="card">

            <div class="card-header">

                <h3>
                    🎓 Graduation Records
                </h3>

                <span style="font-size:12px;color:#777;">
                    <?= count($records) ?> record(s)
                </span>

            </div>

            <form method="GET" class="search-grid">

                <input
                    type="text"
                    name="search"
                    class="form-control"
                    placeholder="Search application no., student no., name, or program..."
                    value="<?= e($search) ?>"
                >

                <select
                    name="status"
                    class="form-control"
                >

                    <option value="">
                        All Statuses
                    </option>

                    <?php foreach ($allowedStatuses as $status): ?>

                        <option
                            value="<?= e($status) ?>"
                            <?= $statusFilter === $status ? 'selected' : '' ?>
                        >
                            <?= e($status) ?>
                        </option>

                    <?php endforeach; ?>

                </select>

                <select
                    name="graduation_year"
                    class="form-control"
                >

                    <option value="">
                        All Years
                    </option>

                    <?php foreach ($years as $year): ?>

                        <option
                            value="<?= e($year) ?>"
                            <?= $yearFilter === $year ? 'selected' : '' ?>
                        >
                            <?= e($year) ?>
                        </option>

                    <?php endforeach; ?>

                </select>

                <button
                    type="submit"
                    class="btn btn-primary"
                >
                    🔍 Search
                </button>

            </form>

            <div class="table-container">

                <table>

                    <thead>

                        <tr>
                            <th>Application No.</th>
                            <th>Student</th>
                            <th>Program</th>
                            <th>Graduation</th>
                            <th>Requirements</th>
                            <th>Status</th>
                            <th>Application Date</th>
                            <th>Actions</th>
                        </tr>

                    </thead>

                    <tbody>

                        <?php if (!empty($records)): ?>

                            <?php foreach ($records as $record): ?>

                                <tr>

                                    <td>

                                        <div class="application-no">
                                            <?= e($record['application_no']) ?>
                                        </div>

                                    </td>

                                    <td>

                                        <div class="student-name">
                                            <?= e($record['student_name']) ?>
                                        </div>

                                        <small>
                                            <?= e($record['student_number']) ?>
                                        </small>

                                    </td>

                                    <td>

                                        <div class="program-name">
                                            <?= e($record['program']) ?>
                                        </div>

                                        <small>
                                            <?= e($record['year_level']) ?>
                                        </small>

                                    </td>

                                    <td>

                                        <strong>
                                            <?= e($record['graduation_year']) ?>
                                        </strong>

                                        <br>

                                        <small>
                                            <?= e($record['graduation_term']) ?>
                                        </small>

                                    </td>

                                    <td>

                                        <span
                                            class="badge requirement-badge <?= strtolower($record['requirements_status']) ?>"
                                        >
                                            <?= e($record['requirements_status']) ?>
                                        </span>

                                        <div
                                            style="
                                                margin-top:6px;
                                                font-size:10px;
                                                color:#777;
                                            "
                                        >
                                            TOR:
                                            <?= $record['tor_status'] ? '✓' : '✗' ?>
                                            &nbsp;
                                            Clearance:
                                            <?= $record['clearance_status'] ? '✓' : '✗' ?>
                                            &nbsp;
                                            Grades:
                                            <?= $record['grades_status'] ? '✓' : '✗' ?>
                                        </div>

                                    </td>

                                    <td>

                                        <span
                                            class="badge <?= statusClass($record['status']) ?>"
                                        >
                                            <?= e($record['status']) ?>
                                        </span>

                                    </td>

                                    <td>
                                        <?= e(
                                            date(
                                                'M d, Y',
                                                strtotime($record['application_date'])
                                            )
                                        ) ?>
                                    </td>

                                    <td>

                                        <div class="action-group">

                                            <!-- REQUIREMENTS -->
                                            <a
                                                href="graduation.php?requirements_id=<?= (int)$record['id'] ?>"
                                                class="btn btn-primary"
                                            >
                                                Requirements
                                            </a>

                                            <!-- MARK GRADUATING -->
                                            <?php if (
                                                $record['requirements_status'] === 'Complete' &&
                                                in_array(
                                                    $record['status'],
                                                    ['Applicant','Not Graduating']
                                                )
                                            ): ?>

                                                <form method="POST">
                                                    <input
                                                        type="hidden"
                                                        name="id"
                                                        value="<?= (int)$record['id'] ?>"
                                                    >

                                                    <button
                                                        type="submit"
                                                        name="mark_graduating"
                                                        class="btn btn-warning"
                                                    >
                                                        Graduating
                                                    </button>
                                                </form>

                                            <?php endif; ?>

                                            <!-- APPROVE -->
                                            <?php if (
                                                $record['requirements_status'] === 'Complete' &&
                                                in_array(
                                                    $record['status'],
                                                    ['Applicant','Graduating']
                                                )
                                            ): ?>

                                                <form method="POST">

                                                    <input
                                                        type="hidden"
                                                        name="id"
                                                        value="<?= (int)$record['id'] ?>"
                                                    >

                                                    <button
                                                        type="submit"
                                                        name="approve_graduate"
                                                        class="btn btn-success"
                                                        onclick="return confirm('Approve this student as a graduate?')"
                                                    >
                                                        Approve
                                                    </button>

                                                </form>

                                            <?php endif; ?>

                                            <!-- NOT GRADUATING -->
                                            <?php if (
                                                !in_array(
                                                    $record['status'],
                                                    ['Approved','Rejected']
                                                )
                                            ): ?>

                                                <form method="POST">

                                                    <input
                                                        type="hidden"
                                                        name="id"
                                                        value="<?= (int)$record['id'] ?>"
                                                    >

                                                    <button
                                                        type="submit"
                                                        name="mark_not_graduating"
                                                        class="btn btn-gray"
                                                    >
                                                        Not Graduating
                                                    </button>

                                                </form>

                                            <?php endif; ?>

                                            <!-- REJECT -->
                                            <?php if (
                                                !in_array(
                                                    $record['status'],
                                                    ['Approved','Rejected']
                                                )
                                            ): ?>

                                                <form
                                                    method="POST"
                                                    onsubmit="return rejectApplicant(this)"
                                                >

                                                    <input
                                                        type="hidden"
                                                        name="id"
                                                        value="<?= (int)$record['id'] ?>"
                                                    >

                                                    <input
                                                        type="hidden"
                                                        name="reject_remarks"
                                                        value=""
                                                    >

                                                    <button
                                                        type="submit"
                                                        name="reject_graduate"
                                                        class="btn btn-danger"
                                                    >
                                                        Reject
                                                    </button>

                                                </form>

                                            <?php endif; ?>

                                            <!-- DELETE -->
                                            <form method="POST">

                                                <input
                                                    type="hidden"
                                                    name="id"
                                                    value="<?= (int)$record['id'] ?>"
                                                >

                                                <button
                                                    type="submit"
                                                    name="delete_record"
                                                    class="btn btn-light"
                                                    onclick="return confirm('Delete this graduation record?')"
                                                >
                                                    Delete
                                                </button>

                                            </form>

                                        </div>

                                    </td>

                                </tr>

                            <?php endforeach; ?>

                        <?php else: ?>

                            <tr>
                                <td colspan="8" class="empty">
                                    No graduation records found.
                                </td>
                            </tr>

                        <?php endif; ?>

                    </tbody>

                </table>

            </div>

        </div>

        <!-- =================================================
             APPROVED GRADUATES
        ================================================= -->
        <div class="card">

            <div class="card-header">

                <h3>
                    ✅ Approved Graduates
                </h3>

                <span style="font-size:12px;color:#777;">
                    Officially approved graduation records
                </span>

            </div>

            <div class="table-container">

                <?php
                $approved = [];

                $approvedResult = $conn->query("
                    SELECT *
                    FROM graduation_records
                    WHERE status = 'Approved'
                    ORDER BY graduation_year DESC, student_name ASC
                ");

                if ($approvedResult) {
                    while ($row = $approvedResult->fetch_assoc()) {
                        $approved[] = $row;
                    }
                }
                ?>

                <?php if (!empty($approved)): ?>

                    <table>

                        <thead>

                            <tr>
                                <th>Student Number</th>
                                <th>Student Name</th>
                                <th>Program</th>
                                <th>Graduation Year</th>
                                <th>Approved Date</th>
                            </tr>

                        </thead>

                        <tbody>

                            <?php foreach ($approved as $graduate): ?>

                                <tr>

                                    <td>
                                        <?= e($graduate['student_number']) ?>
                                    </td>

                                    <td>
                                        <strong>
                                            <?= e($graduate['student_name']) ?>
                                        </strong>
                                    </td>

                                    <td>
                                        <?= e($graduate['program']) ?>
                                    </td>

                                    <td>
                                        <?= e($graduate['graduation_year']) ?>
                                        <br>
                                        <small>
                                            <?= e($graduate['graduation_term']) ?>
                                        </small>
                                    </td>

                                    <td>
                                        <?= $graduate['approved_date']
                                            ? e(
                                                date(
                                                    'M d, Y',
                                                    strtotime($graduate['approved_date'])
                                                )
                                            )
                                            : '—'
                                        ?>
                                    </td>

                                </tr>

                            <?php endforeach; ?>

                        </tbody>

                    </table>

                <?php else: ?>

                    <div class="empty">
                        No approved graduates yet.
                    </div>

                <?php endif; ?>

            </div>

        </div>

    </section>

</main>

<script>
function toggleSidebar(){
    const sidebar=document.getElementById('sidebar');
    if(sidebar) sidebar.classList.toggle('show');
}
</script>
<script>
function rejectApplicant(form) {
    const remarks = prompt(
        "Enter remarks/reason for rejecting this graduation application:",
        ""
    );

    if (remarks === null) {
        return false;
    }

    form.querySelector('[name="reject_remarks"]').value = remarks;

    return confirm("Reject this graduation application?");
}
</script>

</body>
</html>

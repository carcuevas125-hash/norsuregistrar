<?php
session_start();

/*
=========================================================
    NORSU REGISTRAR SYSTEM
    ENROLLMENT MANAGEMENT

    File Name:
    enrollments.php

    Database:
    haha

    FEATURES:
    - View Enrollments
    - Add Enrollment
    - Edit Enrollment
    - Delete Enrollment
    - Approve Enrollment
    - Pending Enrollment
    - Enrollment History
    - Student Subject Load
    - School Year
    - Semester
    - Enrollment Reports
=========================================================
*/


// =====================================================
// DATABASE CONNECTION
// =====================================================

mysqli_report(MYSQLI_REPORT_OFF);

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

if ($conn->connect_errno) {
    die(
        "<h2>Database Connection Error</h2>
        <p>" . htmlspecialchars($conn->connect_error) . "</p>
        <p>Make sure MySQL is running in XAMPP and that the database
        <strong>haha</strong> exists.</p>"
    );
}

$conn->set_charset("utf8mb4");


// =====================================================
// HELPER FUNCTION
// =====================================================

function e($value)
{
    return htmlspecialchars(
        (string)$value,
        ENT_QUOTES,
        'UTF-8'
    );
}


// =====================================================
// CHECK TABLE
// =====================================================

function tableExists($conn, $table)
{
    $table = $conn->real_escape_string($table);

    $result = $conn->query("
        SELECT TABLE_NAME
        FROM INFORMATION_SCHEMA.TABLES
        WHERE TABLE_SCHEMA = DATABASE()
        AND TABLE_NAME = '$table'
        LIMIT 1
    ");

    return $result && $result->num_rows > 0;
}


// =====================================================
// CHECK COLUMN
// =====================================================

function columnExists($conn, $table, $column)
{
    $table = $conn->real_escape_string($table);
    $column = $conn->real_escape_string($column);

    $result = $conn->query("
        SELECT COLUMN_NAME
        FROM INFORMATION_SCHEMA.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
        AND TABLE_NAME = '$table'
        AND COLUMN_NAME = '$column'
        LIMIT 1
    ");

    return $result && $result->num_rows > 0;
}


// =====================================================
// CREATE ENROLLMENTS TABLE IF MISSING
// =====================================================

if (!tableExists($conn, "enrollments")) {

    $sql = "
    CREATE TABLE enrollments (
        id INT UNSIGNED NOT NULL AUTO_INCREMENT,
        student_id VARCHAR(50) NOT NULL,
        school_year VARCHAR(30) NOT NULL DEFAULT '2025-2026',
        semester VARCHAR(50) NOT NULL DEFAULT '1st Semester',
        program VARCHAR(150) DEFAULT NULL,
        year_level VARCHAR(50) DEFAULT NULL,
        status VARCHAR(50) NOT NULL DEFAULT 'Pending',
        enrollment_date DATE DEFAULT NULL,
        approved_date DATE DEFAULT NULL,
        created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
            ON UPDATE CURRENT_TIMESTAMP,

        PRIMARY KEY (id),
        INDEX idx_student_id (student_id),
        INDEX idx_school_year (school_year),
        INDEX idx_semester (semester),
        INDEX idx_program (program),
        INDEX idx_status (status)
    )
    ENGINE=InnoDB
    DEFAULT CHARSET=utf8mb4
    COLLATE=utf8mb4_unicode_ci
    ";

    $conn->query($sql);
}


// =====================================================
// ADD MISSING ENROLLMENT COLUMNS
// =====================================================

if (!columnExists($conn, "enrollments", "student_id")) {

    $conn->query("
        ALTER TABLE enrollments
        ADD COLUMN student_id VARCHAR(50) NOT NULL
    ");
}

if (!columnExists($conn, "enrollments", "school_year")) {

    $conn->query("
        ALTER TABLE enrollments
        ADD COLUMN school_year VARCHAR(30)
        NOT NULL DEFAULT '2025-2026'
    ");
}

if (!columnExists($conn, "enrollments", "semester")) {

    $conn->query("
        ALTER TABLE enrollments
        ADD COLUMN semester VARCHAR(50)
        NOT NULL DEFAULT '1st Semester'
    ");
}

if (!columnExists($conn, "enrollments", "program")) {

    $conn->query("
        ALTER TABLE enrollments
        ADD COLUMN program VARCHAR(150)
        DEFAULT NULL
    ");
}

if (!columnExists($conn, "enrollments", "year_level")) {

    $conn->query("
        ALTER TABLE enrollments
        ADD COLUMN year_level VARCHAR(50)
        DEFAULT NULL
    ");
}

if (!columnExists($conn, "enrollments", "status")) {

    $conn->query("
        ALTER TABLE enrollments
        ADD COLUMN status VARCHAR(50)
        NOT NULL DEFAULT 'Pending'
    ");
}

if (!columnExists($conn, "enrollments", "enrollment_date")) {

    $conn->query("
        ALTER TABLE enrollments
        ADD COLUMN enrollment_date DATE
        DEFAULT NULL
    ");
}

if (!columnExists($conn, "enrollments", "approved_date")) {

    $conn->query("
        ALTER TABLE enrollments
        ADD COLUMN approved_date DATE
        DEFAULT NULL
    ");
}

if (!columnExists($conn, "enrollments", "created_at")) {

    $conn->query("
        ALTER TABLE enrollments
        ADD COLUMN created_at TIMESTAMP
        NOT NULL DEFAULT CURRENT_TIMESTAMP
    ");
}

if (!columnExists($conn, "enrollments", "updated_at")) {

    $conn->query("
        ALTER TABLE enrollments
        ADD COLUMN updated_at TIMESTAMP
        NOT NULL DEFAULT CURRENT_TIMESTAMP
        ON UPDATE CURRENT_TIMESTAMP
    ");
}


// =====================================================
// CREATE SUBJECT TABLE
// =====================================================

if (!tableExists($conn, "enrollment_subjects")) {

    $sql = "
    CREATE TABLE enrollment_subjects (

        id INT UNSIGNED NOT NULL AUTO_INCREMENT,

        enrollment_id INT UNSIGNED NOT NULL,

        student_id VARCHAR(50) NOT NULL,

        subject_code VARCHAR(50) NOT NULL,

        subject_name VARCHAR(150) NOT NULL,

        units DECIMAL(5,2) NOT NULL DEFAULT 0,

        instructor VARCHAR(150) DEFAULT NULL,

        schedule VARCHAR(150) DEFAULT NULL,

        room VARCHAR(100) DEFAULT NULL,

        created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,

        PRIMARY KEY (id),

        INDEX idx_enrollment_id (enrollment_id),

        INDEX idx_student_id (student_id),

        INDEX idx_subject_code (subject_code)

    )
    ENGINE=InnoDB
    DEFAULT CHARSET=utf8mb4
    COLLATE=utf8mb4_unicode_ci
    ";

    $conn->query($sql);
}


// =====================================================
// ADD SUBJECT ROOM COLUMN IF MISSING
// =====================================================

if (!columnExists(
    $conn,
    "enrollment_subjects",
    "room"
)) {

    $conn->query("
        ALTER TABLE enrollment_subjects
        ADD COLUMN room VARCHAR(100)
        DEFAULT NULL
    ");
}


// =====================================================
// APPROVE ENROLLMENT
// =====================================================

if (isset($_GET["approve"])) {

    $id = intval($_GET["approve"]);

    $stmt = $conn->prepare("
        UPDATE enrollments
        SET
            status = 'Approved',
            approved_date = CURDATE()
        WHERE id = ?
    ");

    if ($stmt) {

        $stmt->bind_param(
            "i",
            $id
        );

        $stmt->execute();

        $stmt->close();
    }

    header(
        "Location: enrollments.php?message=approved"
    );

    exit;
}


// =====================================================
// REJECT ENROLLMENT
// =====================================================

if (isset($_GET["reject"])) {

    $id = intval($_GET["reject"]);

    $stmt = $conn->prepare("
        UPDATE enrollments
        SET
            status = 'Rejected'
        WHERE id = ?
    ");

    if ($stmt) {

        $stmt->bind_param(
            "i",
            $id
        );

        $stmt->execute();

        $stmt->close();
    }

    header(
        "Location: enrollments.php?message=rejected"
    );

    exit;
}


// =====================================================
// DELETE ENROLLMENT
// =====================================================

if (isset($_GET["delete"])) {

    $id = intval($_GET["delete"]);


    // Delete subjects first

    $stmt = $conn->prepare("
        DELETE FROM enrollment_subjects
        WHERE enrollment_id = ?
    ");

    if ($stmt) {

        $stmt->bind_param(
            "i",
            $id
        );

        $stmt->execute();

        $stmt->close();
    }


    // Delete enrollment

    $stmt = $conn->prepare("
        DELETE FROM enrollments
        WHERE id = ?
    ");

    if ($stmt) {

        $stmt->bind_param(
            "i",
            $id
        );

        $stmt->execute();

        $stmt->close();
    }

    header(
        "Location: enrollments.php?message=deleted"
    );

    exit;
}


// =====================================================
// DELETE SUBJECT
// =====================================================

if (isset($_GET["delete_subject"])) {

    $id = intval(
        $_GET["delete_subject"]
    );

    $stmt = $conn->prepare("
        DELETE FROM enrollment_subjects
        WHERE id = ?
    ");

    if ($stmt) {

        $stmt->bind_param(
            "i",
            $id
        );

        $stmt->execute();

        $stmt->close();
    }

    $enrollment_id =
        intval(
            $_GET["enrollment_id"] ?? 0
        );

    header(
        "Location: enrollments.php?subjects=" .
        $enrollment_id
    );

    exit;
}


// =====================================================
// SAVE ENROLLMENT
// =====================================================

if (
    $_SERVER["REQUEST_METHOD"] === "POST"
    && isset($_POST["save_enrollment"])
) {

    $id = intval(
        $_POST["id"] ?? 0
    );

    $student_id = trim(
        $_POST["student_id"] ?? ""
    );

    $school_year = trim(
        $_POST["school_year"] ?? "2025-2026"
    );

    $semester = trim(
        $_POST["semester"] ?? "1st Semester"
    );

    $program = trim(
        $_POST["program"] ?? ""
    );

    $year_level = trim(
        $_POST["year_level"] ?? ""
    );

    $status = trim(
        $_POST["status"] ?? "Pending"
    );


    if ($student_id === "") {

        header(
            "Location: enrollments.php?message=missing_student"
        );

        exit;
    }


    // UPDATE

    if ($id > 0) {

        $stmt = $conn->prepare("
            UPDATE enrollments
            SET
                student_id = ?,
                school_year = ?,
                semester = ?,
                program = ?,
                year_level = ?,
                status = ?
            WHERE id = ?
        ");

        if ($stmt) {

            $stmt->bind_param(
                "ssssssi",
                $student_id,
                $school_year,
                $semester,
                $program,
                $year_level,
                $status,
                $id
            );

            $stmt->execute();

            $stmt->close();
        }

    }

    // ADD

    else {

        $stmt = $conn->prepare("
            INSERT INTO enrollments
            (
                student_id,
                school_year,
                semester,
                program,
                year_level,
                status,
                enrollment_date
            )
            VALUES
            (?, ?, ?, ?, ?, ?, CURDATE())
        ");

        if ($stmt) {

            $stmt->bind_param(
                "ssssss",
                $student_id,
                $school_year,
                $semester,
                $program,
                $year_level,
                $status
            );

            $stmt->execute();

            $stmt->close();
        }
    }


    header(
        "Location: enrollments.php?message=saved"
    );

    exit;
}


// =====================================================
// ADD SUBJECT
// =====================================================

if (
    $_SERVER["REQUEST_METHOD"] === "POST"
    && isset($_POST["add_subject"])
) {

    $enrollment_id = intval(
        $_POST["enrollment_id"] ?? 0
    );

    $student_id = trim(
        $_POST["student_id"] ?? ""
    );

    $subject_code = trim(
        $_POST["subject_code"] ?? ""
    );

    $subject_name = trim(
        $_POST["subject_name"] ?? ""
    );

    $units = floatval(
        $_POST["units"] ?? 0
    );

    $instructor = trim(
        $_POST["instructor"] ?? ""
    );

    $schedule = trim(
        $_POST["schedule"] ?? ""
    );

    $room = trim(
        $_POST["room"] ?? ""
    );


    if (
        $enrollment_id > 0 &&
        $student_id !== "" &&
        $subject_code !== "" &&
        $subject_name !== ""
    ) {

        $stmt = $conn->prepare("
            INSERT INTO enrollment_subjects
            (
                enrollment_id,
                student_id,
                subject_code,
                subject_name,
                units,
                instructor,
                schedule,
                room
            )
            VALUES
            (?, ?, ?, ?, ?, ?, ?, ?)
        ");

        if ($stmt) {

            $stmt->bind_param(
                "isssdsss",
                $enrollment_id,
                $student_id,
                $subject_code,
                $subject_name,
                $units,
                $instructor,
                $schedule,
                $room
            );

            $stmt->execute();

            $stmt->close();
        }
    }


    header(
        "Location: enrollments.php?subjects=" .
        $enrollment_id
    );

    exit;
}


// =====================================================
// REPORT COUNTS
// =====================================================

$total_enrollments = 0;

$pending_enrollments = 0;

$approved_enrollments = 0;

$rejected_enrollments = 0;


$result = $conn->query("
    SELECT COUNT(*) AS total
    FROM enrollments
");

if ($result) {

    $row = $result->fetch_assoc();

    $total_enrollments =
        intval($row["total"]);
}


$result = $conn->query("
    SELECT COUNT(*) AS total
    FROM enrollments
    WHERE status = 'Pending'
");

if ($result) {

    $row = $result->fetch_assoc();

    $pending_enrollments =
        intval($row["total"]);
}


$result = $conn->query("
    SELECT COUNT(*) AS total
    FROM enrollments
    WHERE status = 'Approved'
");

if ($result) {

    $row = $result->fetch_assoc();

    $approved_enrollments =
        intval($row["total"]);
}


$result = $conn->query("
    SELECT COUNT(*) AS total
    FROM enrollments
    WHERE status = 'Rejected'
");

if ($result) {

    $row = $result->fetch_assoc();

    $rejected_enrollments =
        intval($row["total"]);
}


// =====================================================
// FILTERS
// =====================================================

$search = trim(
    $_GET["search"] ?? ""
);

$status_filter = trim(
    $_GET["status"] ?? ""
);

$school_year_filter = trim(
    $_GET["school_year"] ?? ""
);

$semester_filter = trim(
    $_GET["semester"] ?? ""
);


// =====================================================
// GET ENROLLMENTS
// =====================================================

$sql = "
    SELECT
        id,
        student_id,
        school_year,
        semester,
        program,
        year_level,
        status,
        enrollment_date,
        approved_date,
        created_at
    FROM enrollments
    WHERE 1 = 1
";

$params = [];

$types = "";


if ($search !== "") {

    $sql .= "
        AND (
            student_id LIKE ?
            OR program LIKE ?
            OR year_level LIKE ?
        )
    ";

    $value = "%" . $search . "%";

    $params[] = $value;

    $params[] = $value;

    $params[] = $value;

    $types .= "sss";
}


if ($status_filter !== "") {

    $sql .= "
        AND status = ?
    ";

    $params[] =
        $status_filter;

    $types .= "s";
}


if ($school_year_filter !== "") {

    $sql .= "
        AND school_year = ?
    ";

    $params[] =
        $school_year_filter;

    $types .= "s";
}


if ($semester_filter !== "") {

    $sql .= "
        AND semester = ?
    ";

    $params[] =
        $semester_filter;

    $types .= "s";
}


$sql .= "
    ORDER BY id DESC
";


$stmt = $conn->prepare($sql);

$enrollments = [];


if ($stmt) {

    if (!empty($params)) {

        $stmt->bind_param(
            $types,
            ...$params
        );
    }

    $stmt->execute();

    $result =
        $stmt->get_result();

    while (
        $row =
        $result->fetch_assoc()
    ) {

        $enrollments[] =
            $row;
    }

    $stmt->close();
}


// =====================================================
// SCHOOL YEARS
// =====================================================

$school_years = [];

$result = $conn->query("
    SELECT DISTINCT school_year
    FROM enrollments
    WHERE school_year IS NOT NULL
    AND school_year != ''
    ORDER BY school_year DESC
");

if ($result) {

    while (
        $row =
        $result->fetch_assoc()
    ) {

        $school_years[] =
            $row["school_year"];
    }
}


// =====================================================
// AVAILABLE PROGRAMS
// (Wired from programs.php / "programs" table so the
// Enrollment form always reflects what was added there)
// =====================================================

$available_programs = [];

if (tableExists($conn, "programs")) {

    $result = $conn->query("
        SELECT program_name
        FROM programs
        WHERE status = 'Active'
        ORDER BY program_name ASC
    ");

    if ($result) {

        while (
            $row =
            $result->fetch_assoc()
        ) {

            $available_programs[] =
                $row["program_name"];
        }
    }
}


// =====================================================
// AVAILABLE YEAR LEVELS
// (Wired from programs.php / "year_levels" table)
// =====================================================

$available_year_levels = [];

if (tableExists($conn, "year_levels")) {

    $result = $conn->query("
        SELECT year_level
        FROM year_levels
        ORDER BY id ASC
    ");

    if ($result) {

        while (
            $row =
            $result->fetch_assoc()
        ) {

            $available_year_levels[] =
                $row["year_level"];
        }
    }
}

if (empty($available_year_levels)) {

    $available_year_levels = [
        "1st Year",
        "2nd Year",
        "3rd Year",
        "4th Year",
        "5th Year"
    ];
}


// =====================================================
// EDIT DATA
// =====================================================

$edit_data = null;

if (isset($_GET["edit"])) {

    $id = intval(
        $_GET["edit"]
    );

    $stmt = $conn->prepare("
        SELECT *
        FROM enrollments
        WHERE id = ?
        LIMIT 1
    ");

    if ($stmt) {

        $stmt->bind_param(
            "i",
            $id
        );

        $stmt->execute();

        $result =
            $stmt->get_result();

        $edit_data =
            $result->fetch_assoc();

        $stmt->close();
    }
}


// =====================================================
// SUBJECT LOAD
// =====================================================

$subject_enrollment = null;

$subjects = [];

if (isset($_GET["subjects"])) {

    $enrollment_id =
        intval($_GET["subjects"]);


    $stmt = $conn->prepare("
        SELECT *
        FROM enrollments
        WHERE id = ?
        LIMIT 1
    ");

    if ($stmt) {

        $stmt->bind_param(
            "i",
            $enrollment_id
        );

        $stmt->execute();

        $result =
            $stmt->get_result();

        $subject_enrollment =
            $result->fetch_assoc();

        $stmt->close();
    }


    if ($subject_enrollment) {

        $stmt = $conn->prepare("
            SELECT *
            FROM enrollment_subjects
            WHERE enrollment_id = ?
            ORDER BY id ASC
        ");

        if ($stmt) {

            $stmt->bind_param(
                "i",
                $enrollment_id
            );

            $stmt->execute();

            $result =
                $stmt->get_result();

            while (
                $row =
                $result->fetch_assoc()
            ) {

                $subjects[] =
                    $row;
            }

            $stmt->close();
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
    NORSU Registrar - Enrollment Management
</title>


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

    width:
        22px;

    text-align:
        center;

    font-size:
        16px;
}

.page-header {

    display:
        flex;

    align-items:
        center;

    justify-content:
        space-between;

    margin-bottom:
        22px;
}

.page-header h2 {

    color:
        var(--dark-blue);

    margin-bottom:
        5px;
}

.page-header p {

    color:
        var(--muted);

    font-size:
        14px;
}

.alert {

    padding:
        13px 16px;

    border-radius:
        6px;

    margin-bottom:
        20px;

    font-size:
        14px;
}

.alert-success {

    background:
        #d4edda;

    color:
        #155724;

    border:
        1px solid
        #c3e6cb;
}

.alert-warning {

    background:
        #fff3cd;

    color:
        #856404;

    border:
        1px solid
        #ffeeba;
}

.alert-danger {

    background:
        #f8d7da;

    color:
        #721c24;

    border:
        1px solid
        #f5c6cb;
}

.stat-card.pending {

    border-left-color:
        var(--yellow);
}

.stat-card.approved {

    border-left-color:
        var(--green);
}

.stat-card.rejected {

    border-left-color:
        var(--red);
}

.stat-card.pending .stat-icon {

    background:
        #fff3cd;

    color:
        #856404;
}

.stat-card.approved .stat-icon {

    background:
        #d4edda;

    color:
        #155724;
}

.stat-card.rejected .stat-icon {

    background:
        #f8d7da;

    color:
        #721c24;
}

.stat-info span {

    display:
        block;

    color:
        var(--muted);

    font-size:
        12px;

    margin-bottom:
        5px;
}

.stat-info strong {

    font-size:
        28px;

    color:
        var(--dark-blue);
}

.card {

    background:
        var(--white);

    border-radius:
        10px;

    box-shadow:
        0 2px 10px
        rgba(0,0,0,.08);

    margin-bottom:
        25px;

    overflow:
        hidden;
}

.card-header {

    display:
        flex;

    align-items:
        center;

    justify-content:
        space-between;

    padding:
        18px 22px;

    border-bottom:
        1px solid
        var(--border);

    background:
        #ffffff;
}

.card-header h3 {

    color:
        var(--dark-blue);

    font-size:
        18px;
}

.card-header > strong {

    color:
        var(--muted);

    font-size:
        13px;
}

.card-body {

    padding:
        22px;
}

.form-grid {

    display:
        grid;

    grid-template-columns:
        repeat(3, 1fr);

    gap:
        18px;
}

.form-group {

    display:
        flex;

    flex-direction:
        column;
}

.form-group.full {

    grid-column:
        1 / -1;
}

.form-group label {

    font-size:
        13px;

    font-weight:
        bold;

    color:
        #444;

    margin-bottom:
        7px;
}

.form-group input,
.form-group select,
.form-group textarea {

    width:
        100%;

    padding:
        11px 12px;

    border:
        1px solid
        #d4d8dd;

    border-radius:
        6px;

    font-size:
        14px;

    font-family:
        inherit;

    background:
        white;
}

.form-group textarea {

    min-height:
        90px;

    resize:
        vertical;
}

.form-group input:focus,
.form-group select:focus,
.form-group textarea:focus {

    outline:
        none;

    border-color:
        var(--blue);

    box-shadow:
        0 0 0 3px
        rgba(0,87,184,.10);
}

.form-buttons {

    display:
        flex;

    gap:
        8px;

    margin-top:
        20px;
}

.search-box {

    display:
        flex;

    gap:
        10px;

    margin-bottom:
        20px;
}

.search-box input {

    flex:
        1;

    padding:
        11px 13px;

    border:
        1px solid
        #ccc;

    border-radius:
        6px;

    font-size:
        14px;
}

.search-box input:focus {

    outline:
        none;

    border-color:
        var(--blue);
}

.filter-grid {

    display:
        grid;

    grid-template-columns:
        2fr 1fr 1fr 1fr auto;

    gap:
        10px;

    align-items:
        end;

    margin-bottom:
        20px;
}

.filter-group {

    display:
        flex;

    flex-direction:
        column;
}

.filter-group label {

    font-size:
        12px;

    font-weight:
        bold;

    margin-bottom:
        5px;

    color:
        #555;
}

.filter-group input,
.filter-group select {

    padding:
        11px;

    border:
        1px solid
        #ccc;

    border-radius:
        6px;

    width:
        100%;

    background:
        white;
}

.btn {

    display:
        inline-flex;

    align-items:
        center;

    justify-content:
        center;

    padding:
        9px 14px;

    border:
        none;

    border-radius:
        6px;

    text-decoration:
        none;

    cursor:
        pointer;

    font-size:
        13px;

    font-weight:
        600;

    transition:
        .2s;

    white-space:
        nowrap;
}

.btn:hover {

    opacity:
        .9;

    transform:
        translateY(-1px);
}

.btn-primary {

    background:
        var(--blue);

    color:
        white;
}

.btn-primary:hover {

    background:
        var(--dark-blue);
}

.btn-success {

    background:
        var(--green);

    color:
        white;
}

.btn-danger {

    background:
        var(--red);

    color:
        white;
}

.btn-warning {

    background:
        var(--yellow);

    color:
        #222;
}

.btn-gray {

    background:
        #6c757d;

    color:
        white;
}

table th {

    background:
        var(--dark-blue);

    color:
        white;

    padding:
        13px 12px;

    text-align:
        left;

    font-size:
        12px;

    white-space:
        nowrap;
}

table td {

    padding:
        12px;

    border-bottom:
        1px solid
        var(--border);

    font-size:
        13px;

    vertical-align:
        middle;
}

table tbody tr:hover {

    background:
        #f7f9fc;
}

.actions {

    display:
        flex;

    flex-wrap:
        wrap;

    gap:
        4px;
}

.actions .btn {

    padding:
        7px 10px;

    font-size:
        12px;
}

.status-pending {

    background:
        #fff3cd;

    color:
        #856404;
}

.status-approved {

    background:
        #d4edda;

    color:
        #155724;
}

.status-rejected {

    background:
        #f8d7da;

    color:
        #721c24;
}

.subject-info {

    background:
        #eef5fb;

    padding:
        18px;

    border-radius:
        8px;

    margin-bottom:
        20px;

    display:
        grid;

    grid-template-columns:
        repeat(4, 1fr);

    gap:
        15px;

    border-left:
        4px solid
        var(--blue);
}

.subject-item {

    display:
        flex;

    flex-direction:
        column;

    gap:
        4px;
}

.subject-item span {

    color:
        var(--muted);

    font-size:
        11px;

    text-transform:
        uppercase;

    font-weight:
        bold;
}

.subject-item strong {

    color:
        var(--dark-blue);

    font-size:
        14px;
}

@media(max-width: 1100px) {

    .stats-grid {

        grid-template-columns:
            repeat(2, 1fr);
    }

    .form-grid {

        grid-template-columns:
            repeat(2, 1fr);
    }

    .filter-grid {

        grid-template-columns:
            repeat(2, 1fr);
    }

    .subject-info {

        grid-template-columns:
            repeat(2, 1fr);
    }
}

@media(max-width: 900px) {

    .sidebar {

        transform:
            translateX(-100%);

        transition:
            .3s;
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
            flex;
    }

    .topbar-left {

        gap:
            8px;
    }
}

@media(max-width: 650px) {

    .content {

        padding:
            15px;
    }

    .topbar {

        padding:
            10px 15px;
    }

    .topbar h1 {

        font-size:
            16px;
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
            1fr;
    }

    .form-grid {

        grid-template-columns:
            1fr;
    }

    .filter-grid {

        grid-template-columns:
            1fr;
    }

    .subject-info {

        grid-template-columns:
            1fr;
    }

    .page-header {

        flex-direction:
            column;

        align-items:
            flex-start;

        gap:
            12px;
    }

    .card-header {

        flex-direction:
            column;

        align-items:
            flex-start;

        gap:
            10px;
    }

    .search-box {

        flex-direction:
            column;
    }

    .form-buttons {

        flex-direction:
            column;
    }

    .form-buttons .btn {

        width:
            100%;
    }
}</style>

</head>


<body>


<!-- =====================================================
     SIDEBAR
===================================================== -->

<aside
    class="sidebar"
    id="sidebar"
>

    <div class="sidebar-header">

        <?php if (
            file_exists(
                __DIR__ . "/norsu.png"
            )
        ): ?>

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


        <h2>
            NORSU
        </h2>

        <p>
            REGISTRAR SYSTEM
        </p>

    </div>


    <nav class="sidebar-menu">

        <div class="menu-title">
            Main
        </div>


        <a href="admin_dashboard.php">

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


        <a
            href="enrollments.php"
            class="active"
        >

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


        <?php if (
            file_exists(
                __DIR__ . "/norsu.png"
            )
        ): ?>

            <img
                src="norsu.png"
                alt="NORSU Logo"
                class="topbar-logo"
            >

        <?php endif; ?>


        <h1>
            Enrollment Management
        </h1>

    </div>


    <div class="admin-info">

        <div class="admin-details">

            <strong>
                Administrator
            </strong>

            <span>
                NORSU Registrar
            </span>

        </div>


        <div class="admin-avatar">
            A
        </div>

    </div>


</header>


<!-- =====================================================
     CONTENT
===================================================== -->

<section class="content">


<?php

$message =
    $_GET["message"] ?? "";

?>


<!-- =====================================================
     ALERTS
===================================================== -->

<?php if (
    $message === "approved"
): ?>

    <div class="alert alert-success">
        Enrollment approved successfully.
    </div>

<?php elseif (
    $message === "rejected"
): ?>

    <div class="alert alert-warning">
        Enrollment rejected successfully.
    </div>

<?php elseif (
    $message === "deleted"
): ?>

    <div class="alert alert-success">
        Enrollment deleted successfully.
    </div>

<?php elseif (
    $message === "saved"
): ?>

    <div class="alert alert-success">
        Enrollment saved successfully.
    </div>

<?php elseif (
    $message === "missing_student"
): ?>

    <div class="alert alert-warning">
        Student ID is required.
    </div>

<?php endif; ?>


<!-- =====================================================
     PAGE HEADER
===================================================== -->

<div class="page-header">

    <div>

        <h2>
            📝 Enrollment Management
        </h2>

        <p>
            Manage student enrollments, subject loads,
            approval, school year, and semester.
        </p>

    </div>


    <a
        href="enrollments.php"
        class="btn btn-primary"
    >
        + Enrollment
    </a>

</div>


<!-- =====================================================
     STATISTICS
===================================================== -->

<div class="stats-grid">


    <div class="stat-card">

        <div class="stat-icon">
            📋
        </div>

        <div class="stat-info">

            <span>
                Total Enrollments
            </span>

            <strong>
                <?= $total_enrollments ?>
            </strong>

        </div>

    </div>


    <div class="stat-card pending">

        <div class="stat-icon">
            ⏳
        </div>

        <div class="stat-info">

            <span>
                Pending Enrollment
            </span>

            <strong>
                <?= $pending_enrollments ?>
            </strong>

        </div>

    </div>


    <div class="stat-card approved">

        <div class="stat-icon">
            ✓
        </div>

        <div class="stat-info">

            <span>
                Approved Enrollment
            </span>

            <strong>
                <?= $approved_enrollments ?>
            </strong>

        </div>

    </div>


    <div class="stat-card rejected">

        <div class="stat-icon">
            ✕
        </div>

        <div class="stat-info">

            <span>
                Rejected Enrollment
            </span>

            <strong>
                <?= $rejected_enrollments ?>
            </strong>

        </div>

    </div>


</div>


<!-- =====================================================
     ADD / EDIT ENROLLMENT
===================================================== -->

<div class="card">

    <div class="card-header">

        <h3>

            <?php if ($edit_data): ?>

                ✏️ Edit Enrollment

            <?php else: ?>

                ➕ Add Enrollment

            <?php endif; ?>

        </h3>


        <?php if ($edit_data): ?>

            <a
                href="enrollments.php"
                class="btn btn-gray"
            >
                Cancel
            </a>

        <?php endif; ?>

    </div>


    <div class="card-body">


        <form
            method="POST"
            action="enrollments.php"
        >

            <input
                type="hidden"
                name="save_enrollment"
                value="1"
            >


            <input
                type="hidden"
                name="id"
                value="<?= e(
                    $edit_data["id"] ?? 0
                ) ?>"
            >


            <div class="form-grid">


                <div class="form-group">

                    <label>
                        Student ID *
                    </label>

                    <input
                        type="text"
                        name="student_id"
                        required
                        value="<?= e(
                            $edit_data["student_id"]
                            ?? ""
                        ) ?>"
                        placeholder="Example: 2026-0001"
                    >

                </div>


                <div class="form-group">

                    <label>
                        School Year
                    </label>

                    <input
                        type="text"
                        name="school_year"
                        value="<?= e(
                            $edit_data["school_year"]
                            ?? "2025-2026"
                        ) ?>"
                        placeholder="2025-2026"
                    >

                </div>


                <div class="form-group">

                    <label>
                        Semester
                    </label>

                    <select name="semester">

                        <option
                            value="1st Semester"
                            <?= (
                                ($edit_data["semester"] ?? "1st Semester")
                                === "1st Semester"
                            )
                            ? "selected"
                            : ""
                            ?>
                        >
                            1st Semester
                        </option>


                        <option
                            value="2nd Semester"
                            <?= (
                                ($edit_data["semester"] ?? "")
                                === "2nd Semester"
                            )
                            ? "selected"
                            : ""
                            ?>
                        >
                            2nd Semester
                        </option>


                        <option
                            value="Summer"
                            <?= (
                                ($edit_data["semester"] ?? "")
                                === "Summer"
                            )
                            ? "selected"
                            : ""
                            ?>
                        >
                            Summer
                        </option>

                    </select>

                </div>


                <div class="form-group">

                    <label>
                        Program
                    </label>

                    <select name="program">

                        <option value="">
                            Select Program
                        </option>


                        <?php foreach (
                            $available_programs
                            as $program_name
                        ): ?>

                            <option
                                value="<?= e($program_name) ?>"
                                <?= (
                                    ($edit_data["program"] ?? "")
                                    === $program_name
                                )
                                ? "selected"
                                : ""
                                ?>
                            >
                                <?= e($program_name) ?>
                            </option>

                        <?php endforeach; ?>


                        <?php if (
                            !empty($edit_data["program"])
                            && !in_array(
                                $edit_data["program"],
                                $available_programs,
                                true
                            )
                        ): ?>

                            <option
                                value="<?= e($edit_data["program"]) ?>"
                                selected
                            >
                                <?= e($edit_data["program"]) ?>
                            </option>

                        <?php endif; ?>

                    </select>

                </div>


                <div class="form-group">

                    <label>
                        Year Level
                    </label>

                    <select name="year_level">

                        <option value="">
                            Select Year Level
                        </option>


                        <?php foreach (
                            $available_year_levels
                            as $year
                        ): ?>

                            <option
                                value="<?= e($year) ?>"
                                <?= (
                                    ($edit_data["year_level"] ?? "")
                                    === $year
                                )
                                ? "selected"
                                : ""
                                ?>
                            >
                                <?= e($year) ?>
                            </option>

                        <?php endforeach; ?>

                    </select>

                </div>


                <div class="form-group">

                    <label>
                        Enrollment Status
                    </label>

                    <select name="status">

                        <option
                            value="Pending"
                            <?= (
                                ($edit_data["status"] ?? "Pending")
                                === "Pending"
                            )
                            ? "selected"
                            : ""
                            ?>
                        >
                            Pending
                        </option>


                        <option
                            value="Approved"
                            <?= (
                                ($edit_data["status"] ?? "")
                                === "Approved"
                            )
                            ? "selected"
                            : ""
                            ?>
                        >
                            Approved
                        </option>


                        <option
                            value="Rejected"
                            <?= (
                                ($edit_data["status"] ?? "")
                                === "Rejected"
                            )
                            ? "selected"
                            : ""
                            ?>
                        >
                            Rejected
                        </option>

                    </select>

                </div>


            </div>


            <div class="form-buttons">

                <button
                    type="submit"
                    class="btn btn-primary"
                >

                    <?php if ($edit_data): ?>

                        Update Enrollment

                    <?php else: ?>

                        Save Enrollment

                    <?php endif; ?>

                </button>


                <?php if ($edit_data): ?>

                    <a
                        href="enrollments.php"
                        class="btn btn-gray"
                    >
                        Cancel
                    </a>

                <?php endif; ?>

            </div>


        </form>

    </div>

</div>


<!-- =====================================================
     SEARCH / FILTER
===================================================== -->

<div class="card">

    <div class="card-header">

        <h3>
            🔎 Search Enrollment
        </h3>

    </div>


    <div class="card-body">


        <form
            method="GET"
            action="enrollments.php"
        >

            <div class="filter-grid">


                <div class="filter-group">

                    <label>
                        Search
                    </label>

                    <input
                        type="text"
                        name="search"
                        placeholder="Student ID / Program / Year Level"
                        value="<?= e($search) ?>"
                    >

                </div>


                <div class="filter-group">

                    <label>
                        Status
                    </label>

                    <select name="status">

                        <option value="">
                            All Status
                        </option>


                        <option
                            value="Pending"
                            <?= $status_filter === "Pending"
                                ? "selected"
                                : ""
                            ?>
                        >
                            Pending
                        </option>


                        <option
                            value="Approved"
                            <?= $status_filter === "Approved"
                                ? "selected"
                                : ""
                            ?>
                        >
                            Approved
                        </option>


                        <option
                            value="Rejected"
                            <?= $status_filter === "Rejected"
                                ? "selected"
                                : ""
                            ?>
                        >
                            Rejected
                        </option>

                    </select>

                </div>


                <div class="filter-group">

                    <label>
                        School Year
                    </label>

                    <select name="school_year">

                        <option value="">
                            All School Years
                        </option>


                        <?php foreach (
                            $school_years
                            as $year
                        ): ?>

                            <option
                                value="<?= e($year) ?>"
                                <?= $school_year_filter === $year
                                    ? "selected"
                                    : ""
                                ?>
                            >
                                <?= e($year) ?>
                            </option>

                        <?php endforeach; ?>

                    </select>

                </div>


                <div class="filter-group">

                    <label>
                        Semester
                    </label>

                    <select name="semester">

                        <option value="">
                            All Semesters
                        </option>


                        <option
                            value="1st Semester"
                            <?= $semester_filter === "1st Semester"
                                ? "selected"
                                : ""
                            ?>
                        >
                            1st Semester
                        </option>


                        <option
                            value="2nd Semester"
                            <?= $semester_filter === "2nd Semester"
                                ? "selected"
                                : ""
                            ?>
                        >
                            2nd Semester
                        </option>


                        <option
                            value="Summer"
                            <?= $semester_filter === "Summer"
                                ? "selected"
                                : ""
                            ?>
                        >
                            Summer
                        </option>

                    </select>

                </div>


                <div>

                    <button
                        type="submit"
                        class="btn btn-primary"
                    >
                        🔍 Search
                    </button>

                </div>


            </div>

        </form>

    </div>

</div>


<!-- =====================================================
     ENROLLMENT RECORDS
===================================================== -->

<div class="card">

    <div class="card-header">

        <h3>
            📋 Enrollment Records
        </h3>

        <strong>
            <?= count($enrollments) ?>
            Enrollment(s)
        </strong>

    </div>


    <div class="card-body">


        <?php if (
            !empty($enrollments)
        ): ?>


            <div class="table-container">

                <table>

                    <thead>

                        <tr>

                            <th>
                                ID
                            </th>

                            <th>
                                Student ID
                            </th>

                            <th>
                                School Year
                            </th>

                            <th>
                                Semester
                            </th>

                            <th>
                                Program
                            </th>

                            <th>
                                Year Level
                            </th>

                            <th>
                                Status
                            </th>

                            <th>
                                Enrollment Date
                            </th>

                            <th>
                                Actions
                            </th>

                        </tr>

                    </thead>


                    <tbody>


                    <?php foreach (
                        $enrollments
                        as $row
                    ): ?>


                        <?php

                        $status =
                            strtolower(
                                $row["status"]
                            );

                        ?>


                        <tr>


                            <td>

                                <?= e(
                                    $row["id"]
                                ) ?>

                            </td>


                            <td>

                                <strong>

                                    <?= e(
                                        $row["student_id"]
                                    ) ?>

                                </strong>

                            </td>


                            <td>

                                <?= e(
                                    $row["school_year"]
                                ) ?>

                            </td>


                            <td>

                                <?= e(
                                    $row["semester"]
                                ) ?>

                            </td>


                            <td>

                                <?= e(
                                    $row["program"]
                                    ?: "-"
                                ) ?>

                            </td>


                            <td>

                                <?= e(
                                    $row["year_level"]
                                    ?: "-"
                                ) ?>

                            </td>


                            <td>

                                <span
                                    class="
                                        status
                                        status-<?= e($status) ?>
                                    "
                                >

                                    <?= e(
                                        $row["status"]
                                    ) ?>

                                </span>

                            </td>


                            <td>

                                <?= e(
                                    $row["enrollment_date"]
                                    ?: "-"
                                ) ?>

                            </td>


                            <td>

                                <div class="actions">


                                    <?php if (
                                        $row["status"]
                                        !== "Approved"
                                    ): ?>

                                        <a
                                            href="enrollments.php?approve=<?= e($row["id"]) ?>"
                                            class="btn btn-success"
                                            onclick="
                                                return confirm(
                                                    'Approve this enrollment?'
                                                );
                                            "
                                        >
                                            Approve
                                        </a>

                                    <?php endif; ?>


                                    <?php if (
                                        $row["status"]
                                        !== "Rejected"
                                    ): ?>

                                        <a
                                            href="enrollments.php?reject=<?= e($row["id"]) ?>"
                                            class="btn btn-danger"
                                            onclick="
                                                return confirm(
                                                    'Reject this enrollment?'
                                                );
                                            "
                                        >
                                            Reject
                                        </a>

                                    <?php endif; ?>


                                    <a
                                        href="enrollments.php?subjects=<?= e($row["id"]) ?>"
                                        class="btn btn-primary"
                                    >
                                        Subjects
                                    </a>


                                    <a
                                        href="enrollments.php?edit=<?= e($row["id"]) ?>"
                                        class="btn btn-warning"
                                    >
                                        Edit
                                    </a>


                                    <a
                                        href="enrollments.php?delete=<?= e($row["id"]) ?>"
                                        class="btn btn-danger"
                                        onclick="
                                            return confirm(
                                                'Delete this enrollment?'
                                            );
                                        "
                                    >
                                        Delete
                                    </a>


                                </div>

                            </td>


                        </tr>


                    <?php endforeach; ?>


                    </tbody>

                </table>

            </div>


        <?php else: ?>


            <div class="empty">

                No enrollment records found.

            </div>


        <?php endif; ?>


    </div>

</div>


<!-- =====================================================
     STUDENT SUBJECT LOAD
===================================================== -->

<?php if (
    $subject_enrollment
): ?>


<div class="card">

    <div class="card-header">

        <h3>
            📚 Student Subject Load
        </h3>

        <a
            href="enrollments.php"
            class="btn btn-gray"
        >
            Back
        </a>

    </div>


    <div class="card-body">


        <div class="subject-info">


            <div class="subject-item">

                <span>
                    Student ID
                </span>

                <strong>
                    <?= e(
                        $subject_enrollment["student_id"]
                    ) ?>
                </strong>

            </div>


            <div class="subject-item">

                <span>
                    Program
                </span>

                <strong>
                    <?= e(
                        $subject_enrollment["program"]
                        ?: "-"
                    ) ?>
                </strong>

            </div>


            <div class="subject-item">

                <span>
                    School Year
                </span>

                <strong>
                    <?= e(
                        $subject_enrollment["school_year"]
                    ) ?>
                </strong>

            </div>


            <div class="subject-item">

                <span>
                    Semester
                </span>

                <strong>
                    <?= e(
                        $subject_enrollment["semester"]
                    ) ?>
                </strong>

            </div>


        </div>


        <!-- =================================================
             ADD SUBJECT
        ================================================= -->

        <form
            method="POST"
            action="enrollments.php"
        >


            <input
                type="hidden"
                name="add_subject"
                value="1"
            >


            <input
                type="hidden"
                name="enrollment_id"
                value="<?= e(
                    $subject_enrollment["id"]
                ) ?>"
            >


            <input
                type="hidden"
                name="student_id"
                value="<?= e(
                    $subject_enrollment["student_id"]
                ) ?>"
            >


            <div class="form-grid">


                <div class="form-group">

                    <label>
                        Subject Code *
                    </label>

                    <input
                        type="text"
                        name="subject_code"
                        placeholder="IT101"
                        required
                    >

                </div>


                <div class="form-group">

                    <label>
                        Subject Name *
                    </label>

                    <input
                        type="text"
                        name="subject_name"
                        placeholder="Introduction to IT"
                        required
                    >

                </div>


                <div class="form-group">

                    <label>
                        Units
                    </label>

                    <input
                        type="number"
                        name="units"
                        step="0.5"
                        min="0"
                        value="3"
                    >

                </div>


                <div class="form-group">

                    <label>
                        Instructor
                    </label>

                    <input
                        type="text"
                        name="instructor"
                        placeholder="Instructor Name"
                    >

                </div>


                <div class="form-group">

                    <label>
                        Schedule
                    </label>

                    <input
                        type="text"
                        name="schedule"
                        placeholder="MWF 8:00-9:00"
                    >

                </div>


                <div class="form-group">

                    <label>
                        Room
                    </label>

                    <input
                        type="text"
                        name="room"
                        placeholder="Room 101"
                    >

                </div>


            </div>


            <div class="form-buttons">

                <button
                    type="submit"
                    class="btn btn-primary"
                >
                    + Add Subject
                </button>

            </div>


        </form>


        <br>


        <!-- =================================================
             SUBJECT TABLE
        ================================================= -->

        <?php if (
            !empty($subjects)
        ): ?>


            <div class="table-container">

                <table>

                    <thead>

                        <tr>

                            <th>
                                #
                            </th>

                            <th>
                                Subject Code
                            </th>

                            <th>
                                Subject Name
                            </th>

                            <th>
                                Units
                            </th>

                            <th>
                                Instructor
                            </th>

                            <th>
                                Schedule
                            </th>

                            <th>
                                Room
                            </th>

                            <th>
                                Action
                            </th>

                        </tr>

                    </thead>


                    <tbody>


                    <?php foreach (
                        $subjects
                        as $index => $subject
                    ): ?>


                        <tr>


                            <td>

                                <?= $index + 1 ?>

                            </td>


                            <td>

                                <strong>

                                    <?= e(
                                        $subject["subject_code"]
                                    ) ?>

                                </strong>

                            </td>


                            <td>

                                <?= e(
                                    $subject["subject_name"]
                                ) ?>

                            </td>


                            <td>

                                <?= e(
                                    $subject["units"]
                                ) ?>

                            </td>


                            <td>

                                <?= e(
                                    $subject["instructor"]
                                    ?: "-"
                                ) ?>

                            </td>


                            <td>

                                <?= e(
                                    $subject["schedule"]
                                    ?: "-"
                                ) ?>

                            </td>


                            <td>

                                <?= e(
                                    $subject["room"]
                                    ?: "-"
                                ) ?>

                            </td>


                            <td>

                                <a
                                    href="enrollments.php?delete_subject=<?= e($subject["id"]) ?>&enrollment_id=<?= e($subject_enrollment["id"]) ?>"
                                    class="btn btn-danger"
                                    onclick="
                                        return confirm(
                                            'Delete this subject?'
                                        );
                                    "
                                >
                                    Delete
                                </a>

                            </td>


                        </tr>


                    <?php endforeach; ?>


                    </tbody>

                </table>

            </div>


        <?php else: ?>


            <div class="empty">

                No subjects loaded.

            </div>


        <?php endif; ?>


    </div>

</div>


<?php endif; ?>


</section>

</main>


<script>

/* =====================================================
   SIDEBAR TOGGLE
===================================================== */

function toggleSidebar()
{
    const sidebar =
        document.getElementById("sidebar");

    sidebar.classList.toggle("show");
}


/* =====================================================
   CLOSE SIDEBAR WHEN LINK IS CLICKED ON MOBILE
===================================================== */

document
    .querySelectorAll(".sidebar-menu a")
    .forEach(function(link)
    {
        link.addEventListener(
            "click",
            function()
            {
                if (
                    window.innerWidth <= 900
                ) {

                    document
                        .getElementById("sidebar")
                        .classList.remove("show");

                }
            }
        );
    });

</script>


</body>

</html>
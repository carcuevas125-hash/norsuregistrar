<?php

/*
=========================================================
    NORSU REGISTRAR SYSTEM
    STUDENT MANAGEMENT

    File Name:
    students.php

    Database:
    haha

    FEATURES:
    - Add Student
    - Edit Student
    - Delete Student
    - Search Student
    - View Student Profile
    - View Academic Records
    - View Enrollment History
    - View Student Status
=========================================================
*/

session_start();


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
    die("Database connection failed: " . $conn->connect_error);
}

$conn->set_charset("utf8mb4");


// =====================================================
// HELPER
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
// CREATE STUDENTS TABLE IF IT DOES NOT EXIST
// =====================================================

$createStudentsTable = "
CREATE TABLE IF NOT EXISTS students (

    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,

    student_id VARCHAR(50) NOT NULL UNIQUE,

    first_name VARCHAR(100) NOT NULL,

    middle_name VARCHAR(100) DEFAULT NULL,

    last_name VARCHAR(100) NOT NULL,

    email VARCHAR(150) DEFAULT NULL,

    contact_number VARCHAR(30) DEFAULT NULL,

    address VARCHAR(255) DEFAULT NULL,

    date_of_birth DATE DEFAULT NULL,

    gender VARCHAR(30) DEFAULT NULL,

    program VARCHAR(150) DEFAULT NULL,

    year_level VARCHAR(50) DEFAULT NULL,

    status VARCHAR(50) NOT NULL DEFAULT 'Active',

    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ON UPDATE CURRENT_TIMESTAMP

) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
";

$conn->query($createStudentsTable);


// =====================================================
// CREATE ACADEMIC RECORDS TABLE
// =====================================================

$createAcademicTable = "
CREATE TABLE IF NOT EXISTS academic_records (

    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,

    student_id VARCHAR(50) NOT NULL,

    subject_code VARCHAR(50) NOT NULL,

    subject_name VARCHAR(150) NOT NULL,

    units DECIMAL(5,2) DEFAULT 0,

    grade VARCHAR(20) DEFAULT NULL,

    school_year VARCHAR(30) DEFAULT NULL,

    semester VARCHAR(50) DEFAULT NULL,

    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP

) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
";

$conn->query($createAcademicTable);


// =====================================================
// CREATE ENROLLMENT HISTORY TABLE
// =====================================================

$createEnrollmentTable = "
CREATE TABLE IF NOT EXISTS enrollment_history (

    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,

    student_id VARCHAR(50) NOT NULL,

    school_year VARCHAR(30) NOT NULL,

    semester VARCHAR(50) NOT NULL,

    program VARCHAR(150) DEFAULT NULL,

    year_level VARCHAR(50) DEFAULT NULL,

    status VARCHAR(50) DEFAULT 'Enrolled',

    enrollment_date DATE DEFAULT NULL,

    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP

) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
";

$conn->query($createEnrollmentTable);


// =====================================================
// DELETE STUDENT
// =====================================================

if (
    isset($_GET["delete"]) &&
    is_numeric($_GET["delete"])
) {

    $id = (int)$_GET["delete"];

    $stmt = $conn->prepare(
        "SELECT student_id
         FROM students
         WHERE id = ?
         LIMIT 1"
    );

    $stmt->bind_param(
        "i",
        $id
    );

    $stmt->execute();

    $result = $stmt->get_result();

    $student = $result->fetch_assoc();

    $stmt->close();


    if ($student) {

        $studentNumber =
            $student["student_id"];


        // Delete academic records
        $stmt = $conn->prepare(
            "DELETE FROM academic_records
             WHERE student_id = ?"
        );

        $stmt->bind_param(
            "s",
            $studentNumber
        );

        $stmt->execute();

        $stmt->close();


        // Delete enrollment history
        $stmt = $conn->prepare(
            "DELETE FROM enrollment_history
             WHERE student_id = ?"
        );

        $stmt->bind_param(
            "s",
            $studentNumber
        );

        $stmt->execute();

        $stmt->close();


        // Delete student
        $stmt = $conn->prepare(
            "DELETE FROM students
             WHERE id = ?"
        );

        $stmt->bind_param(
            "i",
            $id
        );

        $stmt->execute();

        $stmt->close();


        header(
            "Location: students.php?message=deleted"
        );

        exit;
    }
}


// =====================================================
// ADD / UPDATE STUDENT
// =====================================================

if (
    $_SERVER["REQUEST_METHOD"] === "POST" &&
    isset($_POST["save_student"])
) {

    $id =
        isset($_POST["id"])
        ? (int)$_POST["id"]
        : 0;

    $studentId =
        trim(
            $_POST["student_id"] ?? ""
        );

    $firstName =
        trim(
            $_POST["first_name"] ?? ""
        );

    $middleName =
        trim(
            $_POST["middle_name"] ?? ""
        );

    $lastName =
        trim(
            $_POST["last_name"] ?? ""
        );

    $email =
        trim(
            $_POST["email"] ?? ""
        );

    $contact =
        trim(
            $_POST["contact_number"] ?? ""
        );

    $address =
        trim(
            $_POST["address"] ?? ""
        );

    $dateOfBirth =
        trim(
            $_POST["date_of_birth"] ?? ""
        );

    $gender =
        trim(
            $_POST["gender"] ?? ""
        );

    $program =
        trim(
            $_POST["program"] ?? ""
        );

    $yearLevel =
        trim(
            $_POST["year_level"] ?? ""
        );

    $status =
        trim(
            $_POST["status"] ?? "Active"
        );


    // -------------------------------------------------
    // VALIDATION
    // -------------------------------------------------

    if (
        $studentId === "" ||
        $firstName === "" ||
        $lastName === ""
    ) {

        header(
            "Location: students.php?message=required"
        );

        exit;
    }


    // -------------------------------------------------
    // UPDATE
    // -------------------------------------------------

    if ($id > 0) {

        $stmt = $conn->prepare(
            "UPDATE students
             SET
                student_id = ?,
                first_name = ?,
                middle_name = ?,
                last_name = ?,
                email = ?,
                contact_number = ?,
                address = ?,
                date_of_birth = NULLIF(?, ''),
                gender = ?,
                program = ?,
                year_level = ?,
                status = ?
             WHERE id = ?"
        );

        $stmt->bind_param(
            "ssssssssssssi",
            $studentId,
            $firstName,
            $middleName,
            $lastName,
            $email,
            $contact,
            $address,
            $dateOfBirth,
            $gender,
            $program,
            $yearLevel,
            $status,
            $id
        );

        if ($stmt->execute()) {

            $stmt->close();

            header(
                "Location: students.php?message=updated"
            );

            exit;
        }

        $stmt->close();

        header(
            "Location: students.php?message=error"
        );

        exit;
    }


    // -------------------------------------------------
    // ADD
    // -------------------------------------------------

    $stmt = $conn->prepare(
        "INSERT INTO students
        (
            student_id,
            first_name,
            middle_name,
            last_name,
            email,
            contact_number,
            address,
            date_of_birth,
            gender,
            program,
            year_level,
            status
        )
        VALUES
        (
            ?, ?, ?, ?, ?, ?, ?, NULLIF(?, ''),
            ?, ?, ?, ?
        )"
    );

    $stmt->bind_param(
        "ssssssssssss",
        $studentId,
        $firstName,
        $middleName,
        $lastName,
        $email,
        $contact,
        $address,
        $dateOfBirth,
        $gender,
        $program,
        $yearLevel,
        $status
    );


    if ($stmt->execute()) {

        $stmt->close();

        header(
            "Location: students.php?message=added"
        );

        exit;
    }


    $stmt->close();

    header(
        "Location: students.php?message=duplicate"
    );

    exit;
}


// =====================================================
// EDIT STUDENT
// =====================================================

$editStudent = null;

if (
    isset($_GET["edit"]) &&
    is_numeric($_GET["edit"])
) {

    $id =
        (int)$_GET["edit"];

    $stmt = $conn->prepare(
        "SELECT *
         FROM students
         WHERE id = ?
         LIMIT 1"
    );

    $stmt->bind_param(
        "i",
        $id
    );

    $stmt->execute();

    $result =
        $stmt->get_result();

    $editStudent =
        $result->fetch_assoc();

    $stmt->close();
}


// =====================================================
// VIEW STUDENT
// =====================================================

$viewStudent = null;

$academicRecords = [];

$enrollmentHistory = [];

if (
    isset($_GET["view"]) &&
    is_numeric($_GET["view"])
) {

    $id =
        (int)$_GET["view"];


    // -------------------------------------------------
    // STUDENT PROFILE
    // -------------------------------------------------

    $stmt = $conn->prepare(
        "SELECT *
         FROM students
         WHERE id = ?
         LIMIT 1"
    );

    $stmt->bind_param(
        "i",
        $id
    );

    $stmt->execute();

    $result =
        $stmt->get_result();

    $viewStudent =
        $result->fetch_assoc();

    $stmt->close();


    if ($viewStudent) {

        $studentNumber =
            $viewStudent["student_id"];


        // ---------------------------------------------
        // ACADEMIC RECORDS
        // ---------------------------------------------

        $stmt = $conn->prepare(
            "SELECT *
             FROM academic_records
             WHERE student_id = ?
             ORDER BY school_year DESC, semester ASC"
        );

        $stmt->bind_param(
            "s",
            $studentNumber
        );

        $stmt->execute();

        $result =
            $stmt->get_result();

        while (
            $row =
                $result->fetch_assoc()
        ) {

            $academicRecords[] =
                $row;
        }

        $stmt->close();


        // ---------------------------------------------
        // ENROLLMENT HISTORY
        // ---------------------------------------------

        $stmt = $conn->prepare(
            "SELECT *
             FROM enrollment_history
             WHERE student_id = ?
             ORDER BY enrollment_date DESC, id DESC"
        );

        $stmt->bind_param(
            "s",
            $studentNumber
        );

        $stmt->execute();

        $result =
            $stmt->get_result();

        while (
            $row =
                $result->fetch_assoc()
        ) {

            $enrollmentHistory[] =
                $row;
        }

        $stmt->close();
    }
}


// =====================================================
// SEARCH
// =====================================================

$search =
    trim(
        $_GET["search"] ?? ""
    );

$students = [];


if ($search !== "") {

    $searchValue =
        "%" . $search . "%";


    $stmt = $conn->prepare(
        "SELECT *
         FROM students
         WHERE
            student_id LIKE ?
            OR first_name LIKE ?
            OR middle_name LIKE ?
            OR last_name LIKE ?
            OR email LIKE ?
            OR program LIKE ?
         ORDER BY last_name ASC, first_name ASC"
    );

    $stmt->bind_param(
        "ssssss",
        $searchValue,
        $searchValue,
        $searchValue,
        $searchValue,
        $searchValue,
        $searchValue
    );

}
else {

    $stmt = $conn->prepare(
        "SELECT *
         FROM students
         ORDER BY last_name ASC, first_name ASC"
    );
}


$stmt->execute();

$result =
    $stmt->get_result();


while (
    $row =
        $result->fetch_assoc()
) {

    $students[] =
        $row;
}


$stmt->close();


// =====================================================
// MESSAGES
// =====================================================

$message =
    $_GET["message"] ?? "";

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
    NORSU Registrar - Student Management
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


.page-header {

    display:
        flex;

    justify-content:
        space-between;

    align-items:
        center;

    margin-bottom:
        20px;
}

.page-header h2 {

    color:
        var(--blue);

    font-size:
        25px;
}

.page-header p {

    color:
        var(--muted);

    font-size:
        13px;

    margin-top:
        5px;
}

.btn {

    display:
        inline-block;

    padding:
        10px 15px;

    border:
        none;

    border-radius:
        7px;

    text-decoration:
        none;

    cursor:
        pointer;

    font-size:
        13px;

    font-weight:
        bold;
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

.btn-red {

    background:
        var(--red);

    color:
        white;
}

.btn-red:hover {

    background:
        var(--dark-red);
}

.btn-yellow {

    background:
        var(--yellow);

    color:
        #222;
}

.btn-gray {

    background:
        #e9ecef;

    color:
        #333;
}

.btn-green {

    background:
        var(--green);

    color:
        white;
}

.alert {

    padding:
        14px 18px;

    border-radius:
        8px;

    margin-bottom:
        20px;

    font-size:
        13px;

    font-weight:
        bold;
}

.alert-success {

    background:
        #dff5e4;

    color:
        #176b2b;
}

.alert-danger {

    background:
        #ffe1e1;

    color:
        #9b0000;
}

.alert-warning {

    background:
        #fff4cc;

    color:
        #795b00;
}

.card {

    background:
        white;

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

    margin-bottom:
        25px;

    overflow:
        hidden;
}

.card-header {

    padding:
        17px 20px;

    border-top:
        4px solid
        var(--blue);

    border-bottom:
        1px solid
        var(--border);

    display:
        flex;

    justify-content:
        space-between;

    align-items:
        center;
}

.card-header h3 {

    color:
        var(--blue);

    font-size:
        17px;
}

.card-body {

    padding:
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
        12px;

    border:
        1px solid
        #ccc;

    border-radius:
        7px;

    outline:
        none;

    font-size:
        13px;
}

.search-box input:focus {

    border-color:
        var(--blue);
}

tr:hover td {

    background:
        #f5faff;
}

.status-active {

    background:
        #dff5e4;

    color:
        #176b2b;
}

.status-inactive {

    background:
        #e9ecef;

    color:
        #555;
}

.status-graduated {

    background:
        #fff1bd;

    color:
        #705900;
}

.status-dropped {

    background:
        #ffe1e1;

    color:
        #9b0000;
}

.actions {

    display:
        flex;

    gap:
        5px;

    flex-wrap:
        wrap;
}

.action-btn {

    padding:
        6px 9px;

    border-radius:
        5px;

    text-decoration:
        none;

    font-size:
        10px;

    font-weight:
        bold;
}

.action-view {

    background:
        var(--blue);

    color:
        white;
}

.action-edit {

    background:
        var(--yellow);

    color:
        #222;
}

.action-delete {

    background:
        var(--red);

    color:
        white;
}

.form-grid {

    display:
        grid;

    grid-template-columns:
        repeat(
            2,
            1fr
        );

    gap:
        15px;
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
        12px;

    font-weight:
        bold;

    margin-bottom:
        6px;

    color:
        #444;
}

.form-group input,
.form-group select,
.form-group textarea {

    width:
        100%;

    padding:
        11px;

    border:
        1px solid
        #ccc;

    border-radius:
        6px;

    font-size:
        13px;

    outline:
        none;

    font-family:
        inherit;
}

.form-group input:focus,
.form-group select:focus,
.form-group textarea:focus {

    border-color:
        var(--blue);
}

.form-group textarea {

    resize:
        vertical;

    min-height:
        80px;
}

.form-buttons {

    display:
        flex;

    gap:
        10px;

    margin-top:
        20px;
}

.profile-header {

    display:
        flex;

    gap:
        20px;

    align-items:
        center;

    background:
        linear-gradient(
            135deg,
            var(--blue),
            var(--dark-blue)
        );

    color:
        white;

    padding:
        25px;

    border-radius:
        10px;

    margin-bottom:
        20px;
}

.profile-avatar {

    width:
        80px;

    height:
        80px;

    background:
        var(--yellow);

    color:
        var(--blue);

    border-radius:
        50%;

    display:
        flex;

    align-items:
        center;

    justify-content:
        center;

    font-size:
        28px;

    font-weight:
        bold;
}

.profile-header h2 {

    font-size:
        22px;

    margin-bottom:
        5px;
}

.profile-header p {

    font-size:
        12px;

    opacity:
        .9;
}

.profile-grid {

    display:
        grid;

    grid-template-columns:
        repeat(
            2,
            1fr
        );

    gap:
        15px;
}

.info-box {

    padding:
        15px;

    border:
        1px solid
        var(--border);

    border-radius:
        8px;
}

.info-box label {

    display:
        block;

    font-size:
        10px;

    text-transform:
        uppercase;

    color:
        var(--muted);

    margin-bottom:
        5px;
}

.info-box strong {

    font-size:
        13px;
}

@media(max-width: 1000px) {

    .stats-grid {

        grid-template-columns:
            repeat(
                2,
                1fr
            );
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
            block;
    }


    .profile-grid {

        grid-template-columns:
            1fr;
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


    .admin-details {

        display:
            none;
    }


    .form-grid {

        grid-template-columns:
            1fr;
    }


    .form-group.full {

        grid-column:
            auto;
    }


    .page-header {

        flex-direction:
            column;

        align-items:
            flex-start;

        gap:
            12px;
    }


    .search-box {

        flex-direction:
            column;
    }


    .profile-header {

        flex-direction:
            column;

        text-align:
            center;
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


        <a
            href="students.php"
            class="active"
        >

            <span class="icon">
                👨‍🎓
            </span>

            Students

        </a>


        <a href="enrollment.php">

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
            Student Management
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


    <!-- =================================================
         ALERTS
    ================================================= -->

    <?php if (
        $message === "added"
    ): ?>

        <div class="alert alert-success">
            Student successfully added.
        </div>

    <?php elseif (
        $message === "updated"
    ): ?>

        <div class="alert alert-success">
            Student successfully updated.
        </div>

    <?php elseif (
        $message === "deleted"
    ): ?>

        <div class="alert alert-success">
            Student successfully deleted.
        </div>

    <?php elseif (
        $message === "required"
    ): ?>

        <div class="alert alert-warning">
            Please fill in the required student information.
        </div>

    <?php elseif (
        $message === "duplicate"
    ): ?>

        <div class="alert alert-danger">
            Student ID already exists.
        </div>

    <?php elseif (
        $message === "error"
    ): ?>

        <div class="alert alert-danger">
            Unable to save the student record.
        </div>

    <?php endif; ?>


    <!-- =================================================
         PAGE HEADER
    ================================================= -->

    <div class="page-header">


        <div>

            <h2>
                👨‍🎓 Student Management
            </h2>

            <p>
                Add, edit, search, and manage student records.
            </p>

        </div>


        <a
            href="students.php?action=add"
            class="btn btn-primary"
        >
            + Add Student
        </a>


    </div>


    <!-- =================================================
         ADD / EDIT STUDENT FORM
    ================================================= -->

    <?php if (
        isset($_GET["action"]) &&
        $_GET["action"] === "add"
        ||
        $editStudent
    ): ?>


        <div class="card">


            <div class="card-header">


                <h3>

                    <?php if (
                        $editStudent
                    ): ?>

                        ✏️ Edit Student

                    <?php else: ?>

                        ➕ Add Student

                    <?php endif; ?>

                </h3>


                <a
                    href="students.php"
                    class="btn btn-gray"
                >
                    Cancel
                </a>


            </div>


            <div class="card-body">


                <form
                    method="POST"
                    action="students.php"
                >


                    <?php if (
                        $editStudent
                    ): ?>

                        <input
                            type="hidden"
                            name="id"
                            value="<?= e(
                                $editStudent["id"]
                            ) ?>"
                        >

                    <?php endif; ?>


                    <div class="form-grid">


                        <!-- STUDENT ID -->

                        <div class="form-group">

                            <label>
                                Student ID *
                            </label>

                            <input
                                type="text"
                                name="student_id"
                                required
                                value="<?= e(
                                    $editStudent["student_id"]
                                    ?? ""
                                ) ?>"
                                placeholder="Example: 2026-0001"
                            >

                        </div>


                        <!-- FIRST NAME -->

                        <div class="form-group">

                            <label>
                                First Name *
                            </label>

                            <input
                                type="text"
                                name="first_name"
                                required
                                value="<?= e(
                                    $editStudent["first_name"]
                                    ?? ""
                                ) ?>"
                            >

                        </div>


                        <!-- MIDDLE NAME -->

                        <div class="form-group">

                            <label>
                                Middle Name
                            </label>

                            <input
                                type="text"
                                name="middle_name"
                                value="<?= e(
                                    $editStudent["middle_name"]
                                    ?? ""
                                ) ?>"
                            >

                        </div>


                        <!-- LAST NAME -->

                        <div class="form-group">

                            <label>
                                Last Name *
                            </label>

                            <input
                                type="text"
                                name="last_name"
                                required
                                value="<?= e(
                                    $editStudent["last_name"]
                                    ?? ""
                                ) ?>"
                            >

                        </div>


                        <!-- EMAIL -->

                        <div class="form-group">

                            <label>
                                Email
                            </label>

                            <input
                                type="email"
                                name="email"
                                value="<?= e(
                                    $editStudent["email"]
                                    ?? ""
                                ) ?>"
                            >

                        </div>


                        <!-- CONTACT -->

                        <div class="form-group">

                            <label>
                                Contact Number
                            </label>

                            <input
                                type="text"
                                name="contact_number"
                                value="<?= e(
                                    $editStudent["contact_number"]
                                    ?? ""
                                ) ?>"
                            >

                        </div>


                        <!-- DATE OF BIRTH -->

                        <div class="form-group">

                            <label>
                                Date of Birth
                            </label>

                            <input
                                type="date"
                                name="date_of_birth"
                                value="<?= e(
                                    $editStudent["date_of_birth"]
                                    ?? ""
                                ) ?>"
                            >

                        </div>


                        <!-- GENDER -->

                        <div class="form-group">

                            <label>
                                Gender
                            </label>

                            <select
                                name="gender"
                            >

                                <option value="">
                                    Select Gender
                                </option>

                                <option
                                    value="Male"
                                    <?= (
                                        ($editStudent["gender"] ?? "")
                                        === "Male"
                                    )
                                    ? "selected"
                                    : ""
                                    ?>
                                >
                                    Male
                                </option>

                                <option
                                    value="Female"
                                    <?= (
                                        ($editStudent["gender"] ?? "")
                                        === "Female"
                                    )
                                    ? "selected"
                                    : ""
                                    ?>
                                >
                                    Female
                                </option>

                            </select>

                        </div>


                        <!-- PROGRAM -->

                        <div class="form-group">

                            <label>
                                Program
                            </label>

                            <input
                                type="text"
                                name="program"
                                value="<?= e(
                                    $editStudent["program"]
                                    ?? ""
                                ) ?>"
                                placeholder="Example: BS Information Technology"
                            >

                        </div>


                        <!-- YEAR LEVEL -->

                        <div class="form-group">

                            <label>
                                Year Level
                            </label>

                            <select
                                name="year_level"
                            >

                                <option value="">
                                    Select Year Level
                                </option>

                                <?php

                                $yearLevels = [
                                    "1st Year",
                                    "2nd Year",
                                    "3rd Year",
                                    "4th Year",
                                    "5th Year"
                                ];

                                ?>

                                <?php foreach (
                                    $yearLevels
                                    as $year
                                ): ?>

                                    <option
                                        value="<?= e($year) ?>"
                                        <?= (
                                            ($editStudent["year_level"] ?? "")
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


                        <!-- ADDRESS -->

                        <div class="form-group full">

                            <label>
                                Address
                            </label>

                            <textarea
                                name="address"
                                placeholder="Student address"
                            ><?= e(
                                $editStudent["address"]
                                ?? ""
                            ) ?></textarea>

                        </div>


                        <!-- STATUS -->

                        <div class="form-group">

                            <label>
                                Student Status
                            </label>

                            <select
                                name="status"
                            >

                                <?php

                                $statuses = [
                                    "Active",
                                    "Inactive",
                                    "Graduated",
                                    "Dropped"
                                ];

                                ?>

                                <?php foreach (
                                    $statuses
                                    as $studentStatus
                                ): ?>

                                    <option
                                        value="<?= e(
                                            $studentStatus
                                        ) ?>"
                                        <?= (
                                            ($editStudent["status"] ?? "Active")
                                            === $studentStatus
                                        )
                                        ? "selected"
                                        : ""
                                        ?>
                                    >
                                        <?= e(
                                            $studentStatus
                                        ) ?>
                                    </option>

                                <?php endforeach; ?>

                            </select>

                        </div>


                    </div>


                    <div class="form-buttons">


                        <button
                            type="submit"
                            name="save_student"
                            class="btn btn-primary"
                        >

                            <?php if (
                                $editStudent
                            ): ?>

                                Update Student

                            <?php else: ?>

                                Save Student

                            <?php endif; ?>

                        </button>


                        <a
                            href="students.php"
                            class="btn btn-gray"
                        >
                            Cancel
                        </a>


                    </div>


                </form>


            </div>


        </div>


    <?php endif; ?>


    <!-- =================================================
         STUDENT PROFILE
    ================================================= -->

    <?php if (
        $viewStudent
    ): ?>


        <div class="card">


            <div class="card-header">


                <h3>
                    👤 Student Profile
                </h3>


                <a
                    href="students.php"
                    class="btn btn-gray"
                >
                    Back to Students
                </a>


            </div>


            <div class="card-body">


                <div class="profile-header">


                    <div class="profile-avatar">

                        <?= e(
                            strtoupper(
                                substr(
                                    $viewStudent["first_name"],
                                    0,
                                    1
                                )
                            )
                        ) ?>

                    </div>


                    <div>


                        <h2>

                            <?= e(
                                $viewStudent["first_name"]
                            ) ?>

                            <?= e(
                                $viewStudent["middle_name"]
                            ) ?>

                            <?= e(
                                $viewStudent["last_name"]
                            ) ?>

                        </h2>


                        <p>

                            Student ID:
                            <strong>
                                <?= e(
                                    $viewStudent["student_id"]
                                ) ?>
                            </strong>

                        </p>


                        <p>

                            Program:
                            <?= e(
                                $viewStudent["program"]
                                ?: "Not specified"
                            ) ?>

                        </p>


                    </div>


                </div>


                <!-- PROFILE INFORMATION -->

                <div class="profile-grid">


                    <div class="info-box">

                        <label>
                            Student ID
                        </label>

                        <strong>
                            <?= e(
                                $viewStudent["student_id"]
                            ) ?>
                        </strong>

                    </div>


                    <div class="info-box">

                        <label>
                            Full Name
                        </label>

                        <strong>

                            <?= e(
                                $viewStudent["first_name"]
                            ) ?>

                            <?= e(
                                $viewStudent["middle_name"]
                            ) ?>

                            <?= e(
                                $viewStudent["last_name"]
                            ) ?>

                        </strong>

                    </div>


                    <div class="info-box">

                        <label>
                            Email
                        </label>

                        <strong>
                            <?= e(
                                $viewStudent["email"]
                                ?: "Not specified"
                            ) ?>
                        </strong>

                    </div>


                    <div class="info-box">

                        <label>
                            Contact Number
                        </label>

                        <strong>
                            <?= e(
                                $viewStudent["contact_number"]
                                ?: "Not specified"
                            ) ?>
                        </strong>

                    </div>


                    <div class="info-box">

                        <label>
                            Date of Birth
                        </label>

                        <strong>
                            <?= e(
                                $viewStudent["date_of_birth"]
                                ?: "Not specified"
                            ) ?>
                        </strong>

                    </div>


                    <div class="info-box">

                        <label>
                            Gender
                        </label>

                        <strong>
                            <?= e(
                                $viewStudent["gender"]
                                ?: "Not specified"
                            ) ?>
                        </strong>

                    </div>


                    <div class="info-box">

                        <label>
                            Program
                        </label>

                        <strong>
                            <?= e(
                                $viewStudent["program"]
                                ?: "Not specified"
                            ) ?>
                        </strong>

                    </div>


                    <div class="info-box">

                        <label>
                            Year Level
                        </label>

                        <strong>
                            <?= e(
                                $viewStudent["year_level"]
                                ?: "Not specified"
                            ) ?>
                        </strong>

                    </div>


                    <div class="info-box">

                        <label>
                            Student Status
                        </label>


                        <?php

                        $studentStatus =
                            $viewStudent["status"]
                            ?: "Active";

                        $statusClass =
                            strtolower(
                                str_replace(
                                    " ",
                                    "-",
                                    $studentStatus
                                )
                            );

                        ?>


                        <span
                            class="
                                status
                                status-<?= e(
                                    $statusClass
                                ) ?>
                            "
                        >
                            <?= e(
                                $studentStatus
                            ) ?>
                        </span>


                    </div>


                    <div class="info-box">

                        <label>
                            Address
                        </label>

                        <strong>
                            <?= e(
                                $viewStudent["address"]
                                ?: "Not specified"
                            ) ?>
                        </strong>

                    </div>


                </div>


            </div>


        </div>


        <!-- =================================================
             ACADEMIC RECORDS
        ================================================= -->

        <div class="card">


            <div class="card-header">


                <h3>
                    📊 Academic Records
                </h3>


                <a
                    href="grades.php?student_id=<?= urlencode(
                        $viewStudent["student_id"]
                    ) ?>"
                    class="btn btn-primary"
                >
                    Manage Grades
                </a>


            </div>


            <div class="card-body">


                <?php if (
                    !empty(
                        $academicRecords
                    )
                ): ?>


                    <div class="table-container">


                        <table>


                            <thead>

                                <tr>

                                    <th>
                                        Subject Code
                                    </th>

                                    <th>
                                        Subject
                                    </th>

                                    <th>
                                        Units
                                    </th>

                                    <th>
                                        Grade
                                    </th>

                                    <th>
                                        School Year
                                    </th>

                                    <th>
                                        Semester
                                    </th>

                                </tr>

                            </thead>


                            <tbody>


                            <?php foreach (
                                $academicRecords
                                as $record
                            ): ?>


                                <tr>


                                    <td>
                                        <?= e(
                                            $record["subject_code"]
                                        ) ?>
                                    </td>


                                    <td>
                                        <?= e(
                                            $record["subject_name"]
                                        ) ?>
                                    </td>


                                    <td>
                                        <?= e(
                                            $record["units"]
                                        ) ?>
                                    </td>


                                    <td>
                                        <?= e(
                                            $record["grade"]
                                        ) ?>
                                    </td>


                                    <td>
                                        <?= e(
                                            $record["school_year"]
                                        ) ?>
                                    </td>


                                    <td>
                                        <?= e(
                                            $record["semester"]
                                        ) ?>
                                    </td>


                                </tr>


                            <?php endforeach; ?>


                            </tbody>


                        </table>


                    </div>


                <?php else: ?>


                    <div class="empty">

                        No academic records found
                        for this student.

                    </div>


                <?php endif; ?>


            </div>


        </div>


        <!-- =================================================
             ENROLLMENT HISTORY
        ================================================= -->

        <div class="card">


            <div class="card-header">


                <h3>
                    📝 Enrollment History
                </h3>


                <a
                    href="enrollment.php?student_id=<?= urlencode(
                        $viewStudent["student_id"]
                    ) ?>"
                    class="btn btn-primary"
                >
                    Manage Enrollment
                </a>


            </div>


            <div class="card-body">


                <?php if (
                    !empty(
                        $enrollmentHistory
                    )
                ): ?>


                    <div class="table-container">


                        <table>


                            <thead>

                                <tr>

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

                                </tr>

                            </thead>


                            <tbody>


                            <?php foreach (
                                $enrollmentHistory
                                as $enrollment
                            ): ?>


                                <tr>


                                    <td>
                                        <?= e(
                                            $enrollment["school_year"]
                                        ) ?>
                                    </td>


                                    <td>
                                        <?= e(
                                            $enrollment["semester"]
                                        ) ?>
                                    </td>


                                    <td>
                                        <?= e(
                                            $enrollment["program"]
                                        ) ?>
                                    </td>


                                    <td>
                                        <?= e(
                                            $enrollment["year_level"]
                                        ) ?>
                                    </td>


                                    <td>

                                        <span class="status status-active">

                                            <?= e(
                                                $enrollment["status"]
                                            ) ?>

                                        </span>

                                    </td>


                                    <td>
                                        <?= e(
                                            $enrollment["enrollment_date"]
                                            ?: "-"
                                        ) ?>
                                    </td>


                                </tr>


                            <?php endforeach; ?>


                            </tbody>


                        </table>


                    </div>


                <?php else: ?>


                    <div class="empty">

                        No enrollment history found
                        for this student.

                    </div>


                <?php endif; ?>


            </div>


        </div>


    <?php endif; ?>


    <!-- =================================================
         STUDENT LIST
    ================================================= -->

    <?php if (
        !$viewStudent
    ): ?>


        <div class="card">


            <div class="card-header">


                <h3>
                    Student Records
                </h3>


                <strong>

                    <?= count(
                        $students
                    ) ?>

                    Student(s)

                </strong>


            </div>


            <div class="card-body">


                <!-- SEARCH -->

                <form
                    method="GET"
                    action="students.php"
                    class="search-box"
                >


                    <input
                        type="text"
                        name="search"
                        value="<?= e(
                            $search
                        ) ?>"
                        placeholder="Search by Student ID, name, email, or program..."
                    >


                    <button
                        type="submit"
                        class="btn btn-primary"
                    >
                        🔍 Search
                    </button>


                    <?php if (
                        $search !== ""
                    ): ?>

                        <a
                            href="students.php"
                            class="btn btn-gray"
                        >
                            Clear
                        </a>

                    <?php endif; ?>


                </form>


                <!-- TABLE -->

                <?php if (
                    !empty(
                        $students
                    )
                ): ?>


                    <div class="table-container">


                        <table>


                            <thead>

                                <tr>

                                    <th>
                                        Student ID
                                    </th>

                                    <th>
                                        Student Name
                                    </th>

                                    <th>
                                        Email
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
                                        Actions
                                    </th>

                                </tr>

                            </thead>


                            <tbody>


                            <?php foreach (
                                $students
                                as $student
                            ): ?>


                                <?php

                                $fullName =
                                    trim(
                                        $student["first_name"]
                                        . " "
                                        . $student["middle_name"]
                                        . " "
                                        . $student["last_name"]
                                    );


                                $studentStatus =
                                    $student["status"]
                                    ?: "Active";


                                $statusClass =
                                    strtolower(
                                        str_replace(
                                            " ",
                                            "-",
                                            $studentStatus
                                        )
                                    );

                                ?>


                                <tr>


                                    <!-- STUDENT ID -->

                                    <td>

                                        <strong>
                                            <?= e(
                                                $student["student_id"]
                                            ) ?>
                                        </strong>

                                    </td>


                                    <!-- NAME -->

                                    <td>
                                        <?= e(
                                            $fullName
                                        ) ?>
                                    </td>


                                    <!-- EMAIL -->

                                    <td>
                                        <?= e(
                                            $student["email"]
                                            ?: "-"
                                        ) ?>
                                    </td>


                                    <!-- PROGRAM -->

                                    <td>
                                        <?= e(
                                            $student["program"]
                                            ?: "-"
                                        ) ?>
                                    </td>


                                    <!-- YEAR -->

                                    <td>
                                        <?= e(
                                            $student["year_level"]
                                            ?: "-"
                                        ) ?>
                                    </td>


                                    <!-- STATUS -->

                                    <td>


                                        <span
                                            class="
                                                status
                                                status-<?= e(
                                                    $statusClass
                                                )
                                                ?>
                                            "
                                        >

                                            <?= e(
                                                $studentStatus
                                            ) ?>

                                        </span>


                                    </td>


                                    <!-- ACTIONS -->

                                    <td>


                                        <div class="actions">


                                            <!-- VIEW PROFILE -->

                                            <a
                                                href="students.php?view=<?= (int)$student["id"] ?>"
                                                class="action-btn action-view"
                                                title="View Student Profile"
                                            >
                                                👁 View
                                            </a>


                                            <!-- EDIT -->

                                            <a
                                                href="students.php?edit=<?= (int)$student["id"] ?>"
                                                class="action-btn action-edit"
                                                title="Edit Student"
                                            >
                                                ✏️ Edit
                                            </a>


                                            <!-- DELETE -->

                                            <a
                                                href="students.php?delete=<?= (int)$student["id"] ?>"
                                                class="action-btn action-delete"
                                                title="Delete Student"
                                                onclick="
                                                    return confirm(
                                                        'Are you sure you want to delete this student? This will also delete the academic records and enrollment history associated with this student.'
                                                    );
                                                "
                                            >
                                                🗑 Delete
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

                        <?php if (
                            $search !== ""
                        ): ?>

                            No student found matching
                            "<strong><?= e(
                                $search
                            ) ?></strong>".

                        <?php else: ?>

                            No student records found.

                        <?php endif; ?>


                    </div>


                <?php endif; ?>


            </div>


        </div>


    <?php endif; ?>


</section>


</main>


<!-- =====================================================
     JAVASCRIPT
===================================================== -->

<script>


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
// CLOSE MOBILE SIDEBAR
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
            window.innerWidth <= 900 &&
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

$conn->close();

?>
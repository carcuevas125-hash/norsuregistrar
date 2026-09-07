<?php
session_start();

/*
=========================================================
    NORSU REGISTRAR SYSTEM
    COURSE / PROGRAM MANAGEMENT

    File Name:
    programs.php

    Database:
    haha

    FEATURES:
    - Add Program
    - Edit Program
    - Delete Program
    - View Programs
    - Manage Departments
    - Manage Year Levels
    - Manage Subjects

    DATABASE FIX:
    - Subjects table does NOT use foreign-key constraints.
    This prevents MySQL errno 150 when existing tables
    have incompatible column definitions.
=========================================================
*/


// =====================================================
// DATABASE CONNECTION
// =====================================================
// Prevent MySQL duplicate-entry errors from becoming fatal
// exceptions. The existing execute() checks below will
// handle failed INSERT/UPDATE operations normally.
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

if ($conn->connect_error) {
    die("Database connection failed: " . $conn->connect_error);
}

$conn->set_charset("utf8mb4");


// =====================================================
// CREATE DEPARTMENTS TABLE
// =====================================================

$conn->query("
CREATE TABLE IF NOT EXISTS departments (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    department_code VARCHAR(30) NOT NULL UNIQUE,
    department_name VARCHAR(150) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
");


// =====================================================
// CREATE PROGRAMS TABLE
// =====================================================

$conn->query("
CREATE TABLE IF NOT EXISTS programs (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    program_code VARCHAR(50) NOT NULL UNIQUE,
    program_name VARCHAR(200) NOT NULL,
    department_id INT UNSIGNED DEFAULT NULL,
    duration VARCHAR(50) DEFAULT NULL,
    status ENUM('Active','Inactive') NOT NULL DEFAULT 'Active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
");


// =====================================================
// CREATE YEAR LEVELS TABLE
// =====================================================

$conn->query("
CREATE TABLE IF NOT EXISTS year_levels (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    year_level VARCHAR(100) NOT NULL UNIQUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
");


// =====================================================
// CREATE SUBJECTS TABLE
//
// IMPORTANT:
// No FOREIGN KEY constraints are used here.
// This prevents errno 150 caused by incompatible
// existing programs/year_levels table definitions.
// =====================================================

$conn->query("
CREATE TABLE IF NOT EXISTS subjects (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    subject_code VARCHAR(50) NOT NULL UNIQUE,
    subject_name VARCHAR(200) NOT NULL,
    units DECIMAL(4,1) NOT NULL DEFAULT 3.0,
    program_id INT UNSIGNED DEFAULT NULL,
    year_level_id INT UNSIGNED DEFAULT NULL,
    semester VARCHAR(50) DEFAULT NULL,
    instructor VARCHAR(150) DEFAULT NULL,
    schedule VARCHAR(150) DEFAULT NULL,
    room VARCHAR(100) DEFAULT NULL,
    status ENUM('Active','Inactive') NOT NULL DEFAULT 'Active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
");


// =====================================================
// DEFAULT YEAR LEVELS
// =====================================================

$yearCountResult = $conn->query("
    SELECT COUNT(*) AS total
    FROM year_levels
");

$yearCount = 0;

if ($yearCountResult) {
    $yearRow = $yearCountResult->fetch_assoc();
    $yearCount = (int)$yearRow['total'];
}

if ($yearCount === 0) {

    $defaultYears = [
        "1st Year",
        "2nd Year",
        "3rd Year",
        "4th Year"
    ];

    $stmt = $conn->prepare("
        INSERT INTO year_levels (year_level)
        VALUES (?)
    ");

    if ($stmt) {

        foreach ($defaultYears as $year) {
            $stmt->bind_param("s", $year);
            $stmt->execute();
        }

        $stmt->close();
    }
}


// =====================================================
// MESSAGE VARIABLES
// =====================================================

$success = "";
$error = "";


// =====================================================
// ADD DEPARTMENT
// =====================================================

if (isset($_POST['add_department'])) {

    $department_code = trim(
        $_POST['department_code'] ?? ''
    );

    $department_name = trim(
        $_POST['department_name'] ?? ''
    );

    if (
        $department_code === '' ||
        $department_name === ''
    ) {

        $error = "Please complete all department fields.";

    } else {

        $stmt = $conn->prepare("
            INSERT INTO departments
            (
                department_code,
                department_name
            )
            VALUES (?, ?)
        ");

        if ($stmt) {

            $stmt->bind_param(
                "ss",
                $department_code,
                $department_name
            );

            if ($stmt->execute()) {

                $success = "Department added successfully.";

            } else {

                $error =
                    "Department code already exists or could not be added.";
            }

            $stmt->close();

        } else {

            $error = "Unable to prepare department query.";
        }
    }
}


// =====================================================
// EDIT DEPARTMENT
// =====================================================

if (isset($_POST['edit_department'])) {

    $id = (int)(
        $_POST['department_id'] ?? 0
    );

    $department_code = trim(
        $_POST['department_code'] ?? ''
    );

    $department_name = trim(
        $_POST['department_name'] ?? ''
    );

    if (
        $id <= 0 ||
        $department_code === '' ||
        $department_name === ''
    ) {

        $error = "Please complete all department fields.";

    } else {

        $stmt = $conn->prepare("
            UPDATE departments
            SET
                department_code = ?,
                department_name = ?
            WHERE id = ?
        ");

        if ($stmt) {

            $stmt->bind_param(
                "ssi",
                $department_code,
                $department_name,
                $id
            );

            if ($stmt->execute()) {

                $success =
                    "Department updated successfully.";

            } else {

                $error =
                    "Unable to update department.";
            }

            $stmt->close();

        } else {

            $error =
                "Unable to prepare department update.";
        }
    }
}


// =====================================================
// DELETE DEPARTMENT
// =====================================================

if (isset($_GET['delete_department'])) {

    $id = (int)$_GET['delete_department'];

    if ($id > 0) {

        $stmt = $conn->prepare("
            DELETE FROM departments
            WHERE id = ?
        ");

        if ($stmt) {

            $stmt->bind_param("i", $id);

            if ($stmt->execute()) {

                $success =
                    "Department deleted successfully.";

            } else {

                $error =
                    "Unable to delete department.";
            }

            $stmt->close();
        }
    }
}


// =====================================================
// ADD YEAR LEVEL
// =====================================================

if (isset($_POST['add_year_level'])) {

    $year_level = trim(
        $_POST['year_level'] ?? ''
    );

    if ($year_level === '') {

        $error =
            "Please enter a year level.";

    } else {

        $stmt = $conn->prepare("
            INSERT INTO year_levels
            (
                year_level
            )
            VALUES (?)
        ");

        if ($stmt) {

            $stmt->bind_param(
                "s",
                $year_level
            );

            if ($stmt->execute()) {

                $success =
                    "Year level added successfully.";

            } else {

                $error =
                    "Year level already exists.";
            }

            $stmt->close();

        } else {

            $error =
                "Unable to prepare year level query.";
        }
    }
}


// =====================================================
// EDIT YEAR LEVEL
// =====================================================

if (isset($_POST['edit_year_level'])) {
    $id = (int)($_POST['year_level_id'] ?? 0);
    $year_level = trim($_POST['year_level'] ?? '');
    if ($id <= 0 || $year_level === '') {
        $error = "Please enter a year level.";
    } else {
        $stmt = $conn->prepare("UPDATE year_levels SET year_level = ? WHERE id = ?");
        if ($stmt) {
            $stmt->bind_param("si", $year_level, $id);
            if ($stmt->execute()) {
                $success = "Year level updated successfully.";
            } else {
                $error = "Year level already exists or could not be updated.";
            }
            $stmt->close();
        } else {
            $error = "Unable to prepare year level update.";
        }
    }
}


// DELETE YEAR LEVEL
// =====================================================

if (isset($_GET['delete_year'])) {

    $id = (int)$_GET['delete_year'];

    if ($id > 0) {

        $stmt = $conn->prepare("
            DELETE FROM year_levels
            WHERE id = ?
        ");

        if ($stmt) {

            $stmt->bind_param(
                "i",
                $id
            );

            if ($stmt->execute()) {

                $success =
                    "Year level deleted successfully.";

            } else {

                $error =
                    "Unable to delete year level.";
            }

            $stmt->close();
        }
    }
}


// =====================================================
// ADD PROGRAM
// =====================================================

if (isset($_POST['add_program'])) {

    $program_code = trim(
        $_POST['program_code'] ?? ''
    );

    $program_name = trim(
        $_POST['program_name'] ?? ''
    );

    $department_id = (int)(
        $_POST['department_id'] ?? 0
    );

    $duration = trim(
        $_POST['duration'] ?? ''
    );

    $status = $_POST['status'] ?? 'Active';

    if (
        $program_code === '' ||
        $program_name === ''
    ) {

        $error =
            "Please complete the required program fields.";

    } else {

        if (
            $status !== 'Active' &&
            $status !== 'Inactive'
        ) {
            $status = 'Active';
        }

        $stmt = $conn->prepare("
            INSERT INTO programs
            (
                program_code,
                program_name,
                department_id,
                duration,
                status
            )
            VALUES (?, ?, ?, ?, ?)
        ");

        if ($stmt) {

            $stmt->bind_param(
                "ssiss",
                $program_code,
                $program_name,
                $department_id,
                $duration,
                $status
            );

            if ($stmt->execute()) {

                $success =
                    "Program added successfully.";

            } else {

                $error =
                    "Program code already exists or could not be added.";
            }

            $stmt->close();

        } else {

            $error =
                "Unable to prepare program query.";
        }
    }
}


// =====================================================
// EDIT PROGRAM
// =====================================================

if (isset($_POST['edit_program'])) {

    $id = (int)(
        $_POST['program_id'] ?? 0
    );

    $program_code = trim(
        $_POST['program_code'] ?? ''
    );

    $program_name = trim(
        $_POST['program_name'] ?? ''
    );

    $department_id = (int)(
        $_POST['department_id'] ?? 0
    );

    $duration = trim(
        $_POST['duration'] ?? ''
    );

    $status = $_POST['status'] ?? 'Active';

    if (
        $id <= 0 ||
        $program_code === '' ||
        $program_name === ''
    ) {

        $error =
            "Please complete the required program fields.";

    } else {

        if (
            $status !== 'Active' &&
            $status !== 'Inactive'
        ) {
            $status = 'Active';
        }

        $stmt = $conn->prepare("
            UPDATE programs
            SET
                program_code = ?,
                program_name = ?,
                department_id = ?,
                duration = ?,
                status = ?
            WHERE id = ?
        ");

        if ($stmt) {

            $stmt->bind_param(
                "ssissi",
                $program_code,
                $program_name,
                $department_id,
                $duration,
                $status,
                $id
            );

            if ($stmt->execute()) {

                $success =
                    "Program updated successfully.";

            } else {

                $error =
                    "Unable to update program.";
            }

            $stmt->close();

        } else {

            $error =
                "Unable to prepare program update.";
        }
    }
}


// =====================================================
// DELETE PROGRAM
// =====================================================

if (isset($_GET['delete_program'])) {

    $id = (int)$_GET['delete_program'];

    if ($id > 0) {

        $stmt = $conn->prepare("
            DELETE FROM programs
            WHERE id = ?
        ");

        if ($stmt) {

            $stmt->bind_param(
                "i",
                $id
            );

            if ($stmt->execute()) {

                $success =
                    "Program deleted successfully.";

            } else {

                $error =
                    "Unable to delete program.";
            }

            $stmt->close();
        }
    }
}


// =====================================================
// ADD SUBJECT
// =====================================================

if (isset($_POST['add_subject'])) {

    $subject_code = trim(
        $_POST['subject_code'] ?? ''
    );

    $subject_name = trim(
        $_POST['subject_name'] ?? ''
    );

    $units = (float)(
        $_POST['units'] ?? 3
    );

    $program_id = (int)(
        $_POST['program_id'] ?? 0
    );

    $year_level_id = (int)(
        $_POST['year_level_id'] ?? 0
    );

    $semester = trim(
        $_POST['semester'] ?? ''
    );

    $instructor = trim(
        $_POST['instructor'] ?? ''
    );

    $schedule = trim(
        $_POST['schedule'] ?? ''
    );

    $room = trim(
        $_POST['room'] ?? ''
    );

    $status = $_POST['status'] ?? 'Active';

    if (
        $subject_code === '' ||
        $subject_name === ''
    ) {

        $error =
            "Please complete the required subject fields.";

    } else {

        if (
            $status !== 'Active' &&
            $status !== 'Inactive'
        ) {
            $status = 'Active';
        }

        $stmt = $conn->prepare("
            INSERT INTO subjects
            (
                subject_code,
                subject_name,
                units,
                program_id,
                year_level_id,
                semester,
                instructor,
                schedule,
                room,
                status
            )
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");

        if ($stmt) {

            $stmt->bind_param(
                "ssdiiissss",
                $subject_code,
                $subject_name,
                $units,
                $program_id,
                $year_level_id,
                $semester,
                $instructor,
                $schedule,
                $room,
                $status
            );

            if ($stmt->execute()) {

                $success =
                    "Subject added successfully.";

            } else {

                $error =
                    "Subject code already exists or could not be added.";
            }

            $stmt->close();

        } else {

            $error =
                "Unable to prepare subject query.";
        }
    }
}


// =====================================================
// EDIT YEAR LEVEL LOOKUP
// =====================================================

$editYearLevel = null;

if (isset($_GET['edit_year'])) {
    $id = (int)$_GET['edit_year'];
    $stmt = $conn->prepare("SELECT * FROM year_levels WHERE id = ? LIMIT 1");
    if ($stmt) {
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $editYearLevel = $stmt->get_result()->fetch_assoc();
        $stmt->close();
    }
}


// EDIT SUBJECT
// =====================================================

if (isset($_POST['edit_subject'])) {

    $id = (int)(
        $_POST['subject_id'] ?? 0
    );

    $subject_code = trim(
        $_POST['subject_code'] ?? ''
    );

    $subject_name = trim(
        $_POST['subject_name'] ?? ''
    );

    $units = (float)(
        $_POST['units'] ?? 3
    );

    $program_id = (int)(
        $_POST['program_id'] ?? 0
    );

    $year_level_id = (int)(
        $_POST['year_level_id'] ?? 0
    );

    $semester = trim(
        $_POST['semester'] ?? ''
    );

    $instructor = trim(
        $_POST['instructor'] ?? ''
    );

    $schedule = trim(
        $_POST['schedule'] ?? ''
    );

    $room = trim(
        $_POST['room'] ?? ''
    );

    $status = $_POST['status'] ?? 'Active';

    if (
        $id <= 0 ||
        $subject_code === '' ||
        $subject_name === ''
    ) {

        $error =
            "Please complete the required subject fields.";

    } else {

        if (
            $status !== 'Active' &&
            $status !== 'Inactive'
        ) {
            $status = 'Active';
        }

        $stmt = $conn->prepare("
            UPDATE subjects
            SET
                subject_code = ?,
                subject_name = ?,
                units = ?,
                program_id = ?,
                year_level_id = ?,
                semester = ?,
                instructor = ?,
                schedule = ?,
                room = ?,
                status = ?
            WHERE id = ?
        ");

        if ($stmt) {

            $stmt->bind_param(
                "ssdiiissssi",
                $subject_code,
                $subject_name,
                $units,
                $program_id,
                $year_level_id,
                $semester,
                $instructor,
                $schedule,
                $room,
                $status,
                $id
            );

            if ($stmt->execute()) {

                $success =
                    "Subject updated successfully.";

            } else {

                $error =
                    "Unable to update subject.";
            }

            $stmt->close();

        } else {

            $error =
                "Unable to prepare subject update.";
        }
    }
}


// =====================================================
// DELETE SUBJECT
// =====================================================

if (isset($_GET['delete_subject'])) {

    $id = (int)$_GET['delete_subject'];

    if ($id > 0) {

        $stmt = $conn->prepare("
            DELETE FROM subjects
            WHERE id = ?
        ");

        if ($stmt) {

            $stmt->bind_param(
                "i",
                $id
            );

            if ($stmt->execute()) {

                $success =
                    "Subject deleted successfully.";

            } else {

                $error =
                    "Unable to delete subject.";
            }

            $stmt->close();
        }
    }
}


// =====================================================
// SEARCH
// =====================================================

$search = trim(
    $_GET['search'] ?? ''
);


// =====================================================
// LOAD DEPARTMENTS
// =====================================================

$departments = [];

$result = $conn->query("
    SELECT *
    FROM departments
    ORDER BY department_name ASC
");

if ($result) {

    while ($row = $result->fetch_assoc()) {
        $departments[] = $row;
    }
}


// =====================================================
// LOAD YEAR LEVELS
// =====================================================

$yearLevels = [];

$result = $conn->query("
    SELECT *
    FROM year_levels
    ORDER BY id ASC
");

if ($result) {

    while ($row = $result->fetch_assoc()) {
        $yearLevels[] = $row;
    }
}


// =====================================================
// LOAD PROGRAMS
// =====================================================

$programs = [];

if ($search !== '') {

    $stmt = $conn->prepare("
        SELECT
            p.*,
            d.department_code,
            d.department_name
        FROM programs p
        LEFT JOIN departments d
            ON p.department_id = d.id
        WHERE
            p.program_code LIKE ?
            OR p.program_name LIKE ?
            OR d.department_name LIKE ?
        ORDER BY p.program_name ASC
    ");

    if ($stmt) {

        $like = "%" . $search . "%";

        $stmt->bind_param(
            "sss",
            $like,
            $like,
            $like
        );

        $stmt->execute();

        $result = $stmt->get_result();

        while ($row = $result->fetch_assoc()) {
            $programs[] = $row;
        }

        $stmt->close();
    }

} else {

    $result = $conn->query("
        SELECT
            p.*,
            d.department_code,
            d.department_name
        FROM programs p
        LEFT JOIN departments d
            ON p.department_id = d.id
        ORDER BY p.program_name ASC
    ");

    if ($result) {

        while ($row = $result->fetch_assoc()) {
            $programs[] = $row;
        }
    }
}


// =====================================================
// LOAD SUBJECTS
// =====================================================

$subjects = [];

$result = $conn->query("
    SELECT
        s.*,
        p.program_code,
        p.program_name,
        y.year_level
    FROM subjects s
    LEFT JOIN programs p
        ON s.program_id = p.id
    LEFT JOIN year_levels y
        ON s.year_level_id = y.id
    ORDER BY s.subject_code ASC
");

if ($result) {

    while ($row = $result->fetch_assoc()) {
        $subjects[] = $row;
    }
}


// =====================================================
// COUNTS
// =====================================================

$programCount = 0;
$departmentCount = 0;
$yearLevelCount = 0;
$subjectCount = 0;


$result = $conn->query("
    SELECT COUNT(*) AS total
    FROM programs
");

if ($result) {
    $programCount =
        (int)$result->fetch_assoc()['total'];
}


$result = $conn->query("
    SELECT COUNT(*) AS total
    FROM departments
");

if ($result) {
    $departmentCount =
        (int)$result->fetch_assoc()['total'];
}


$result = $conn->query("
    SELECT COUNT(*) AS total
    FROM year_levels
");

if ($result) {
    $yearLevelCount =
        (int)$result->fetch_assoc()['total'];
}


$result = $conn->query("
    SELECT COUNT(*) AS total
    FROM subjects
");

if ($result) {
    $subjectCount =
        (int)$result->fetch_assoc()['total'];
}


// =====================================================
// EDIT PROGRAM
// =====================================================

$editProgram = null;

if (isset($_GET['edit_program'])) {

    $id = (int)$_GET['edit_program'];

    $stmt = $conn->prepare("
        SELECT *
        FROM programs
        WHERE id = ?
        LIMIT 1
    ");

    if ($stmt) {

        $stmt->bind_param(
            "i",
            $id
        );

        $stmt->execute();

        $editProgram =
            $stmt->get_result()->fetch_assoc();

        $stmt->close();
    }
}


// =====================================================
// EDIT DEPARTMENT
// =====================================================

$editDepartment = null;

if (isset($_GET['edit_department'])) {

    $id = (int)$_GET['edit_department'];

    $stmt = $conn->prepare("
        SELECT *
        FROM departments
        WHERE id = ?
        LIMIT 1
    ");

    if ($stmt) {

        $stmt->bind_param(
            "i",
            $id
        );

        $stmt->execute();

        $editDepartment =
            $stmt->get_result()->fetch_assoc();

        $stmt->close();
    }
}


// =====================================================
// EDIT SUBJECT
// =====================================================

$editSubject = null;

if (isset($_GET['edit_subject'])) {

    $id = (int)$_GET['edit_subject'];

    $stmt = $conn->prepare("
        SELECT *
        FROM subjects
        WHERE id = ?
        LIMIT 1
    ");

    if ($stmt) {

        $stmt->bind_param(
            "i",
            $id
        );

        $stmt->execute();

        $editSubject =
            $stmt->get_result()->fetch_assoc();

        $stmt->close();
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
    Course / Program Management | NORSU Registrar System
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

.btn-blue {
    background: var(--blue);
    color: white;
}

.alert-error {
    background: #fee2e2;
    color: #991b1b;
    border-left: 5px solid #dc2626;
}

.stats {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 18px;
    margin-bottom: 25px;
}

.stat-card.yellow {
    border-top-color: var(--yellow);
}

.stat-label {
    color: var(--muted);
    font-size: 13px;
}

.stat-number {
    color: var(--green);
    font-size: 30px;
    font-weight: bold;
    margin-top: 8px;
}

.card-title {
    color: var(--green);
    font-size: 18px;
    font-weight: bold;
}

.badge {
    display: inline-block;
    padding: 5px 10px;
    border-radius: 20px;
    font-size: 11px;
    font-weight: bold;
}

.badge-active {
    background: #dcfce7;
    color: #166534;
}

.badge-inactive {
    background: #fee2e2;
    color: #991b1b;
}

.two-columns {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 24px;
}

@media (max-width: 1000px) {

    .stats {
        grid-template-columns: repeat(2, 1fr);
    }

    .two-columns {
        grid-template-columns: 1fr;
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


        <a href="enrollment.php">

            <span class="icon">
                📝
            </span>

            Enrollment

        </a>


        <a
            href="programs.php"
            class="active"
        >

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
            Course / Program Management
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




        <!-- PAGE HEADER -->

        <div class="page-header">

            <div>

                <div class="page-title">
                    📚 Course / Program Management
                </div>

                <div class="page-subtitle">
                    Manage programs, departments, year levels, and subjects.
                </div>

            </div>


            <div style="display:flex; gap:8px; flex-wrap:wrap;">

                <a
                    href="#program-form"
                    class="btn btn-primary"
                >
                    + Add Program
                </a>

                <a
                    href="#department-form"
                    class="btn btn-blue"
                >
                    + Add Department
                </a>

                <a
                    href="#year-level-form"
                    class="btn btn-gray"
                >
                    + Add Year Level
                </a>

            </div>

        </div>


        <!-- ALERTS -->

        <?php if ($success !== ""): ?>

            <div class="alert alert-success">

                ✅
                <?= htmlspecialchars($success) ?>

            </div>

        <?php endif; ?>


        <?php if ($error !== ""): ?>

            <div class="alert alert-error">

                ❌
                <?= htmlspecialchars($error) ?>

            </div>

        <?php endif; ?>


        <!-- =================================================
            STATISTICS
        ================================================== -->

        <div class="stats">


            <div class="stat-card">

                <div class="stat-label">
                    Total Programs
                </div>

                <div class="stat-number">
                    <?= $programCount ?>
                </div>

            </div>


            <div class="stat-card yellow">

                <div class="stat-label">
                    Departments
                </div>

                <div class="stat-number">
                    <?= $departmentCount ?>
                </div>

            </div>


            <div class="stat-card">

                <div class="stat-label">
                    Year Levels
                </div>

                <div class="stat-number">
                    <?= $yearLevelCount ?>
                </div>

            </div>


            <div class="stat-card yellow">

                <div class="stat-label">
                    Subjects
                </div>

                <div class="stat-number">
                    <?= $subjectCount ?>
                </div>

            </div>

        </div>


        <!-- =================================================
            PROGRAM FORM
        ================================================== -->

        <div
            class="card"
            id="program-form"
        >


            <div class="card-header">

                <div class="card-title">

                    <?php
                    if ($editProgram) {
                        echo "✏️ Edit Program";
                    } else {
                        echo "➕ Add Program";
                    }
                    ?>

                </div>

            </div>


            <div class="card-body">

                <form method="POST">


                    <?php if ($editProgram): ?>

                        <input
                            type="hidden"
                            name="program_id"
                            value="<?= (int)$editProgram['id'] ?>"
                        >

                    <?php endif; ?>


                    <div class="form-grid">


                        <div class="form-group">

                            <label>
                                Program Code *
                            </label>

                            <input
                                type="text"
                                name="program_code"
                                required
                                placeholder="Example: BSIT"
                                value="<?= htmlspecialchars(
                                    $editProgram['program_code'] ?? ''
                                ) ?>"
                            >

                        </div>


                        <div class="form-group">

                            <label>
                                Program Name *
                            </label>

                            <input
                                type="text"
                                name="program_name"
                                required
                                placeholder="Bachelor of Science in Information Technology"
                                value="<?= htmlspecialchars(
                                    $editProgram['program_name'] ?? ''
                                ) ?>"
                            >

                        </div>


                        <div class="form-group">

                            <label>
                                Department
                            </label>

                            <select name="department_id">

                                <option value="0">
                                    -- Select Department --
                                </option>


                                <?php foreach ($departments as $department): ?>

                                    <option
                                        value="<?= (int)$department['id'] ?>"
                                        <?= (
                                            isset($editProgram['department_id']) &&
                                            $editProgram['department_id'] == $department['id']
                                        ) ? 'selected' : '' ?>
                                    >

                                        <?= htmlspecialchars(
                                            $department['department_code']
                                        ) ?>

                                        -

                                        <?= htmlspecialchars(
                                            $department['department_name']
                                        ) ?>

                                    </option>

                                <?php endforeach; ?>

                            </select>

                        </div>


                        <div class="form-group">

                            <label>
                                Duration
                            </label>

                            <input
                                type="text"
                                name="duration"
                                placeholder="Example: 4 Years"
                                value="<?= htmlspecialchars(
                                    $editProgram['duration'] ?? ''
                                ) ?>"
                            >

                        </div>


                        <div class="form-group">

                            <label>
                                Status
                            </label>

                            <select name="status">

                                <option
                                    value="Active"
                                    <?= (
                                        ($editProgram['status'] ?? 'Active')
                                        === 'Active'
                                    ) ? 'selected' : '' ?>
                                >
                                    Active
                                </option>


                                <option
                                    value="Inactive"
                                    <?= (
                                        ($editProgram['status'] ?? '')
                                        === 'Inactive'
                                    ) ? 'selected' : '' ?>
                                >
                                    Inactive
                                </option>

                            </select>

                        </div>

                    </div>


                    <div class="form-buttons">


                        <?php if ($editProgram): ?>

                            <button
                                type="submit"
                                name="edit_program"
                                class="btn btn-primary"
                            >
                                💾 Update Program
                            </button>


                            <a
                                href="programs.php"
                                class="btn btn-gray"
                            >
                                Cancel
                            </a>

                        <?php else: ?>

                            <button
                                type="submit"
                                name="add_program"
                                class="btn btn-primary"
                            >
                                ➕ Add Program
                            </button>

                        <?php endif; ?>


                    </div>

                </form>

            </div>

        </div>


        <!-- =================================================
            PROGRAM LIST
        ================================================== -->

        <div class="card">


            <div class="card-header">

                <div class="card-title">
                    📋 View Programs
                </div>


                <form
                    method="GET"
                    class="search-box"
                >

                    <input
                        type="text"
                        name="search"
                        placeholder="Search programs..."
                        value="<?= htmlspecialchars($search) ?>"
                    >


                    <button
                        type="submit"
                        class="btn btn-primary"
                    >
                        Search
                    </button>

                </form>

            </div>


            <div class="table-container">

                <table>

                    <thead>

                        <tr>

                            <th>
                                #
                            </th>

                            <th>
                                Program Code
                            </th>

                            <th>
                                Program Name
                            </th>

                            <th>
                                Department
                            </th>

                            <th>
                                Duration
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


                        <?php if (empty($programs)): ?>

                            <tr>

                                <td
                                    colspan="7"
                                    class="empty"
                                >
                                    No programs found.
                                </td>

                            </tr>

                        <?php else: ?>


                            <?php foreach (
                                $programs
                                as $index => $program
                            ): ?>

                                <tr>


                                    <td>
                                        <?= $index + 1 ?>
                                    </td>


                                    <td>

                                        <strong>
                                            <?= htmlspecialchars(
                                                $program['program_code']
                                            ) ?>
                                        </strong>

                                    </td>


                                    <td>
                                        <?= htmlspecialchars(
                                            $program['program_name']
                                        ) ?>
                                    </td>


                                    <td>

                                        <?php if (
                                            !empty(
                                                $program['department_name']
                                            )
                                        ): ?>

                                            <?= htmlspecialchars(
                                                $program['department_code']
                                            ) ?>

                                            -

                                            <?= htmlspecialchars(
                                                $program['department_name']
                                            ) ?>

                                        <?php else: ?>

                                            <span style="color:#999;">
                                                No Department
                                            </span>

                                        <?php endif; ?>

                                    </td>


                                    <td>
                                        <?= htmlspecialchars(
                                            $program['duration'] ?? ''
                                        ) ?>
                                    </td>


                                    <td>

                                        <?php if (
                                            $program['status']
                                            === 'Active'
                                        ): ?>

                                            <span
                                                class="badge badge-active"
                                            >
                                                Active
                                            </span>

                                        <?php else: ?>

                                            <span
                                                class="badge badge-inactive"
                                            >
                                                Inactive
                                            </span>

                                        <?php endif; ?>

                                    </td>


                                    <td>

                                        <div class="actions">


                                            <a
                                                href="programs.php?edit_program=<?= (int)$program['id'] ?>"
                                                class="btn btn-blue"
                                            >
                                                ✏️ Edit
                                            </a>


                                            <a
                                                href="programs.php?delete_program=<?= (int)$program['id'] ?>"
                                                class="btn btn-danger"
                                                onclick="return confirm('Are you sure you want to delete this program?');"
                                            >
                                                🗑️ Delete
                                            </a>


                                        </div>

                                    </td>


                                </tr>

                            <?php endforeach; ?>


                        <?php endif; ?>


                    </tbody>

                </table>

            </div>

        </div>


        <!-- =================================================
            DEPARTMENTS AND YEAR LEVELS
        ================================================== -->

        <div class="two-columns">


            <!-- DEPARTMENTS -->

            <div
                class="card"
                id="department-form"
            >


                <div class="card-header">

                    <div class="card-title">
                        🏢 Manage Departments
                    </div>

                </div>


                <div class="card-body">


                    <form method="POST">


                        <?php if ($editDepartment): ?>

                            <input
                                type="hidden"
                                name="department_id"
                                value="<?= (int)$editDepartment['id'] ?>"
                            >

                        <?php endif; ?>


                        <div class="form-group">

                            <label>
                                Department Code *
                            </label>

                            <input
                                type="text"
                                name="department_code"
                                required
                                placeholder="Example: CCS"
                                value="<?= htmlspecialchars(
                                    $editDepartment['department_code'] ?? ''
                                ) ?>"
                            >

                        </div>


                        <div
                            class="form-group"
                            style="margin-top:14px;"
                        >

                            <label>
                                Department Name *
                            </label>

                            <input
                                type="text"
                                name="department_name"
                                required
                                placeholder="College of Computer Studies"
                                value="<?= htmlspecialchars(
                                    $editDepartment['department_name'] ?? ''
                                ) ?>"
                            >

                        </div>


                        <div class="form-buttons">


                            <?php if ($editDepartment): ?>

                                <button
                                    type="submit"
                                    name="edit_department"
                                    class="btn btn-primary"
                                >
                                    💾 Update
                                </button>


                                <a
                                    href="programs.php"
                                    class="btn btn-gray"
                                >
                                    Cancel
                                </a>

                            <?php else: ?>

                                <button
                                    type="submit"
                                    name="add_department"
                                    class="btn btn-primary"
                                >
                                    + Add Department
                                </button>

                            <?php endif; ?>


                        </div>

                    </form>

                </div>


                <div class="table-container">

                    <table>

                        <thead>

                            <tr>

                                <th>
                                    Code
                                </th>

                                <th>
                                    Department
                                </th>

                                <th>
                                    Actions
                                </th>

                            </tr>

                        </thead>


                        <tbody>


                            <?php if (empty($departments)): ?>

                                <tr>

                                    <td
                                        colspan="3"
                                        class="empty"
                                    >
                                        No departments found.
                                    </td>

                                </tr>

                            <?php else: ?>


                                <?php foreach (
                                    $departments
                                    as $department
                                ): ?>

                                    <tr>


                                        <td>

                                            <?= htmlspecialchars(
                                                $department['department_code']
                                            ) ?>

                                        </td>


                                        <td>

                                            <?= htmlspecialchars(
                                                $department['department_name']
                                            ) ?>

                                        </td>


                                        <td>

                                            <div class="actions">


                                                <a
                                                    href="programs.php?edit_department=<?= (int)$department['id'] ?>"
                                                    class="btn btn-blue"
                                                >
                                                    Edit
                                                </a>


                                                <a
                                                    href="programs.php?delete_department=<?= (int)$department['id'] ?>"
                                                    class="btn btn-danger"
                                                    onclick="return confirm('Delete this department?');"
                                                >
                                                    Delete
                                                </a>


                                            </div>

                                        </td>


                                    </tr>

                                <?php endforeach; ?>


                            <?php endif; ?>


                        </tbody>

                    </table>

                </div>

            </div>


            <!-- YEAR LEVELS -->

            <div
                class="card"
                id="year-level-form"
            >


                <div class="card-header">

                    <div class="card-title">
                        🎓 Manage Year Levels
                    </div>

                </div>


                <div class="card-body">


                    <form method="POST">

                        <?php if ($editYearLevel): ?>
                            <input type="hidden" name="year_level_id" value="<?= (int)$editYearLevel['id'] ?>">
                        <?php endif; ?>

                        <div class="form-group">
                            <label>
                                Year Level *
                            </label>

                            <input
                                type="text"
                                name="year_level"
                                required
                                placeholder="Example: 1st Year"
                                value="<?= htmlspecialchars($editYearLevel['year_level'] ?? '') ?>"
                            >

                        </div>

                        <div class="form-buttons">
                            <?php if ($editYearLevel): ?>
                                <button
                                    type="submit"
                                    name="edit_year_level"
                                    class="btn btn-primary"
                                >
                                    💾 Update Year Level
                                </button>

                                <a
                                    href="programs.php"
                                    class="btn btn-gray"
                                >
                                    Cancel
                                </a>
                            <?php else: ?>
                                <button
                                    type="submit"
                                    name="add_year_level"
                                    class="btn btn-primary"
                                >
                                    + Add Year Level
                                </button>
                            <?php endif; ?>
                        </div>

                    </form>

                </div>


                <div class="table-container">

                    <table>

                        <thead>

                            <tr>

                                <th>
                                    #
                                </th>

                                <th>
                                    Year Level
                                </th>

                                <th>
                                    Action
                                </th>

                            </tr>

                        </thead>


                        <tbody>


                            <?php foreach (
                                $yearLevels
                                as $index => $year
                            ): ?>

                                <tr>


                                    <td>
                                        <?= $index + 1 ?>
                                    </td>


                                    <td>
                                        <?= htmlspecialchars(
                                            $year['year_level']
                                        ) ?>
                                    </td>


                                    <td>
                                        <div class="actions">

                                            <a
                                                href="programs.php?edit_year=<?= (int)$year['id'] ?>"
                                                class="btn btn-blue"
                                            >
                                                ✏️ Edit
                                            </a>

                                            <a
                                                href="programs.php?delete_year=<?= (int)$year['id'] ?>"
                                                class="btn btn-danger"
                                                onclick="return confirm('Are you sure you want to delete this year level?');"
                                            >
                                                🗑️ Delete
                                            </a>

                                        </div>
                                    </td>


                                </tr>

                            <?php endforeach; ?>


                        </tbody>

                    </table>

                </div>

            </div>


        </div>


        <!-- =================================================
            SUBJECT FORM
        ================================================== -->

        <div class="card">


            <div class="card-header">

                <div class="card-title">

                    <?php
                    if ($editSubject) {
                        echo "✏️ Edit Subject";
                    } else {
                        echo "📚 Manage Subjects";
                    }
                    ?>

                </div>

            </div>


            <div class="card-body">


                <form method="POST">


                    <?php if ($editSubject): ?>

                        <input
                            type="hidden"
                            name="subject_id"
                            value="<?= (int)$editSubject['id'] ?>"
                        >

                    <?php endif; ?>


                    <div class="form-grid">


                        <div class="form-group">

                            <label>
                                Subject Code *
                            </label>

                            <input
                                type="text"
                                name="subject_code"
                                required
                                placeholder="Example: IT101"
                                value="<?= htmlspecialchars(
                                    $editSubject['subject_code'] ?? ''
                                ) ?>"
                            >

                        </div>


                        <div class="form-group">

                            <label>
                                Subject Name *
                            </label>

                            <input
                                type="text"
                                name="subject_name"
                                required
                                placeholder="Introduction to Information Technology"
                                value="<?= htmlspecialchars(
                                    $editSubject['subject_name'] ?? ''
                                ) ?>"
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
                                value="<?= htmlspecialchars(
                                    $editSubject['units'] ?? '3'
                                ) ?>"
                            >

                        </div>


                        <div class="form-group">

                            <label>
                                Program
                            </label>

                            <select name="program_id">

                                <option value="0">
                                    -- Select Program --
                                </option>


                                <?php foreach (
                                    $programs
                                    as $program
                                ): ?>

                                    <option
                                        value="<?= (int)$program['id'] ?>"
                                        <?= (
                                            isset($editSubject['program_id']) &&
                                            $editSubject['program_id'] == $program['id']
                                        ) ? 'selected' : '' ?>
                                    >

                                        <?= htmlspecialchars(
                                            $program['program_code']
                                        ) ?>

                                        -

                                        <?= htmlspecialchars(
                                            $program['program_name']
                                        ) ?>

                                    </option>

                                <?php endforeach; ?>

                            </select>

                        </div>


                        <div class="form-group">

                            <label>
                                Year Level
                            </label>

                            <select name="year_level_id">

                                <option value="0">
                                    -- Select Year Level --
                                </option>


                                <?php foreach (
                                    $yearLevels
                                    as $year
                                ): ?>

                                    <option
                                        value="<?= (int)$year['id'] ?>"
                                        <?= (
                                            isset($editSubject['year_level_id']) &&
                                            $editSubject['year_level_id'] == $year['id']
                                        ) ? 'selected' : '' ?>
                                    >

                                        <?= htmlspecialchars(
                                            $year['year_level']
                                        ) ?>

                                    </option>

                                <?php endforeach; ?>

                            </select>

                        </div>


                        <div class="form-group">

                            <label>
                                Semester
                            </label>

                            <select name="semester">

                                <option value="">
                                    -- Select Semester --
                                </option>


                                <option
                                    value="1st Semester"
                                    <?= (
                                        ($editSubject['semester'] ?? '')
                                        === '1st Semester'
                                    ) ? 'selected' : '' ?>
                                >
                                    1st Semester
                                </option>


                                <option
                                    value="2nd Semester"
                                    <?= (
                                        ($editSubject['semester'] ?? '')
                                        === '2nd Semester'
                                    ) ? 'selected' : '' ?>
                                >
                                    2nd Semester
                                </option>


                                <option
                                    value="Summer"
                                    <?= (
                                        ($editSubject['semester'] ?? '')
                                        === 'Summer'
                                    ) ? 'selected' : '' ?>
                                >
                                    Summer
                                </option>

                            </select>

                        </div>


                        <div class="form-group">

                            <label>
                                Instructor
                            </label>

                            <input
                                type="text"
                                name="instructor"
                                placeholder="Instructor name"
                                value="<?= htmlspecialchars(
                                    $editSubject['instructor'] ?? ''
                                ) ?>"
                            >

                        </div>


                        <div class="form-group">

                            <label>
                                Schedule
                            </label>

                            <input
                                type="text"
                                name="schedule"
                                placeholder="Example: M/W/F 8:00-9:00 AM"
                                value="<?= htmlspecialchars(
                                    $editSubject['schedule'] ?? ''
                                ) ?>"
                            >

                        </div>


                        <div class="form-group">

                            <label>
                                Room
                            </label>

                            <input
                                type="text"
                                name="room"
                                placeholder="Example: Room 201"
                                value="<?= htmlspecialchars(
                                    $editSubject['room'] ?? ''
                                ) ?>"
                            >

                        </div>


                        <div class="form-group">

                            <label>
                                Status
                            </label>

                            <select name="status">

                                <option
                                    value="Active"
                                    <?= (
                                        ($editSubject['status'] ?? 'Active')
                                        === 'Active'
                                    ) ? 'selected' : '' ?>
                                >
                                    Active
                                </option>


                                <option
                                    value="Inactive"
                                    <?= (
                                        ($editSubject['status'] ?? '')
                                        === 'Inactive'
                                    ) ? 'selected' : '' ?>
                                >
                                    Inactive
                                </option>

                            </select>

                        </div>


                    </div>


                    <div class="form-buttons">


                        <?php if ($editSubject): ?>

                            <button
                                type="submit"
                                name="edit_subject"
                                class="btn btn-primary"
                            >
                                💾 Update Subject
                            </button>


                            <a
                                href="programs.php"
                                class="btn btn-gray"
                            >
                                Cancel
                            </a>

                        <?php else: ?>

                            <button
                                type="submit"
                                name="add_subject"
                                class="btn btn-primary"
                            >
                                + Add Subject
                            </button>

                        <?php endif; ?>


                    </div>

                </form>

            </div>

        </div>


        <!-- =================================================
            SUBJECT LIST
        ================================================== -->

        <div class="card">


            <div class="card-header">

                <div class="card-title">
                    📖 Subject List
                </div>

            </div>


            <div class="table-container">

                <table>

                    <thead>

                        <tr>

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
                                Program
                            </th>

                            <th>
                                Year Level
                            </th>

                            <th>
                                Semester
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
                                Status
                            </th>

                            <th>
                                Actions
                            </th>

                        </tr>

                    </thead>


                    <tbody>


                        <?php if (empty($subjects)): ?>

                            <tr>

                                <td
                                    colspan="11"
                                    class="empty"
                                >
                                    No subjects found.
                                </td>

                            </tr>

                        <?php else: ?>


                            <?php foreach (
                                $subjects
                                as $subject
                            ): ?>

                                <tr>


                                    <td>

                                        <strong>
                                            <?= htmlspecialchars(
                                                $subject['subject_code']
                                            ) ?>
                                        </strong>

                                    </td>


                                    <td>

                                        <?= htmlspecialchars(
                                            $subject['subject_name']
                                        ) ?>

                                    </td>


                                    <td>

                                        <?= htmlspecialchars(
                                            $subject['units']
                                        ) ?>

                                    </td>


                                    <td>

                                        <?= htmlspecialchars(
                                            $subject['program_code'] ?? ''
                                        ) ?>

                                    </td>


                                    <td>

                                        <?= htmlspecialchars(
                                            $subject['year_level'] ?? ''
                                        ) ?>

                                    </td>


                                    <td>

                                        <?= htmlspecialchars(
                                            $subject['semester'] ?? ''
                                        ) ?>

                                    </td>


                                    <td>

                                        <?= htmlspecialchars(
                                            $subject['instructor'] ?? ''
                                        ) ?>

                                    </td>


                                    <td>

                                        <?= htmlspecialchars(
                                            $subject['schedule'] ?? ''
                                        ) ?>

                                    </td>


                                    <td>

                                        <?= htmlspecialchars(
                                            $subject['room'] ?? ''
                                        ) ?>

                                    </td>


                                    <td>

                                        <?php if (
                                            $subject['status']
                                            === 'Active'
                                        ): ?>

                                            <span
                                                class="badge badge-active"
                                            >
                                                Active
                                            </span>

                                        <?php else: ?>

                                            <span
                                                class="badge badge-inactive"
                                            >
                                                Inactive
                                            </span>

                                        <?php endif; ?>

                                    </td>


                                    <td>

                                        <div class="actions">


                                            <a
                                                href="programs.php?edit_subject=<?= (int)$subject['id'] ?>"
                                                class="btn btn-blue"
                                            >
                                                Edit
                                            </a>


                                            <a
                                                href="programs.php?delete_subject=<?= (int)$subject['id'] ?>"
                                                class="btn btn-danger"
                                                onclick="return confirm('Delete this subject?');"
                                            >
                                                Delete
                                            </a>


                                        </div>

                                    </td>


                                </tr>

                            <?php endforeach; ?>


                        <?php endif; ?>


                    </tbody>

                </table>

            </div>

        </div>


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



/* =====================================================
   AUTOMATIC RESPONSIVE TABLE LABELS
   Copies desktop column headings into each mobile cell.
===================================================== */

document.querySelectorAll("table").forEach(function(table) {

    const headers = Array.from(
        table.querySelectorAll("thead th")
    ).map(function(th) {
        return th.textContent.trim();
    });

    table.querySelectorAll("tbody tr").forEach(function(row) {

        const cells = row.querySelectorAll("td");

        cells.forEach(function(cell, index) {

            if (cell.hasAttribute("colspan")) {
                return;
            }

            if (headers[index]) {
                cell.setAttribute("data-label", headers[index]);
            }

        });

    });

});

</script>


</body>

</html>
<?php

$conn->close();

?>
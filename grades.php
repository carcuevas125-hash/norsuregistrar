<?php
session_start();

/*
=========================================================
    NORSU REGISTRAR SYSTEM
    GRADES MANAGEMENT

    File Name:
    grades.php

    Database:
    haha

    FEATURES:
    - View Grades
    - Encode Grades
    - Update Grades
    - Grade Correction
    - View Student Academic History
    - Grade Reports

    NOTE:
    This page is designed to work independently.
    It stores the student number/name together with each
    grade record, so it does not depend on a specific
    students-table structure.
=========================================================
*/

mysqli_report(MYSQLI_REPORT_OFF);

// =====================================================
// DATABASE CONNECTION
// =====================================================

$host = "localhost";
$username = "root";
$password = "";
$database = "haha";

$conn = new mysqli($host, $username, $password, $database);

if ($conn->connect_error) {
    die("Database connection failed: " . htmlspecialchars($conn->connect_error));
}

$conn->set_charset("utf8mb4");

// =====================================================
// CREATE GRADES TABLE
// =====================================================

$conn->query("
CREATE TABLE IF NOT EXISTS grades (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    student_number VARCHAR(50) NOT NULL,
    student_name VARCHAR(200) NOT NULL,
    program_code VARCHAR(100) DEFAULT NULL,
    subject_code VARCHAR(50) NOT NULL,
    subject_name VARCHAR(200) NOT NULL,
    units DECIMAL(4,1) NOT NULL DEFAULT 3.0,
    school_year VARCHAR(30) NOT NULL,
    semester VARCHAR(50) NOT NULL,
    prelim DECIMAL(5,2) DEFAULT NULL,
    midterm DECIMAL(5,2) DEFAULT NULL,
    final DECIMAL(5,2) DEFAULT NULL,
    final_grade DECIMAL(5,2) DEFAULT NULL,
    remarks VARCHAR(50) DEFAULT NULL,
    instructor VARCHAR(150) DEFAULT NULL,
    status ENUM('Encoded','Corrected','Incomplete') NOT NULL DEFAULT 'Encoded',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_student_number (student_number),
    INDEX idx_subject_code (subject_code),
    INDEX idx_school_year (school_year)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
");

// =====================================================
// CREATE GRADE CORRECTIONS TABLE
// =====================================================

$conn->query("
CREATE TABLE IF NOT EXISTS grade_corrections (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    grade_id INT UNSIGNED NOT NULL,
    student_number VARCHAR(50) NOT NULL,
    student_name VARCHAR(200) NOT NULL,
    subject_code VARCHAR(50) NOT NULL,
    old_grade DECIMAL(5,2) DEFAULT NULL,
    new_grade DECIMAL(5,2) DEFAULT NULL,
    reason TEXT NOT NULL,
    corrected_by VARCHAR(150) NOT NULL DEFAULT 'Administrator',
    corrected_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_grade_id (grade_id),
    INDEX idx_student_number (student_number)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
");

// =====================================================
// HELPERS
// =====================================================

function gradeValue($value) {
    if ($value === null || $value === '') {
        return null;
    }

    $value = (float)$value;

    if ($value < 0 || $value > 100) {
        return null;
    }

    return $value;
}

function calculateFinalGrade($prelim, $midterm, $final) {
    $values = [];

    if ($prelim !== null) $values[] = $prelim;
    if ($midterm !== null) $values[] = $midterm;
    if ($final !== null) $values[] = $final;

    if (empty($values)) {
        return null;
    }

    return round(array_sum($values) / count($values), 2);
}

function gradeRemarks($grade) {
    if ($grade === null) {
        return 'Incomplete';
    }

    return $grade >= 75 ? 'Passed' : 'Failed';
}

// =====================================================
// MESSAGE VARIABLES
// =====================================================

$success = "";
$error = "";

// =====================================================
// ENCODE GRADE
// =====================================================

if (isset($_POST['encode_grade'])) {

    $student_number = trim($_POST['student_number'] ?? '');
    $student_name   = trim($_POST['student_name'] ?? '');
    $program_code   = trim($_POST['program_code'] ?? '');
    $subject_code   = trim($_POST['subject_code'] ?? '');
    $subject_name   = trim($_POST['subject_name'] ?? '');
    $units          = (float)($_POST['units'] ?? 3);
    $school_year    = trim($_POST['school_year'] ?? '');
    $semester       = trim($_POST['semester'] ?? '');
    $prelim         = gradeValue($_POST['prelim'] ?? '');
    $midterm        = gradeValue($_POST['midterm'] ?? '');
    $final          = gradeValue($_POST['final'] ?? '');
    $instructor     = trim($_POST['instructor'] ?? '');

    if (
        $student_number === '' ||
        $student_name === '' ||
        $subject_code === '' ||
        $subject_name === '' ||
        $school_year === '' ||
        $semester === ''
    ) {
        $error = "Please complete all required grade fields.";
    } elseif ($units < 0) {
        $error = "Units cannot be negative.";
    } elseif (
        ($_POST['prelim'] ?? '') !== '' && $prelim === null ||
        ($_POST['midterm'] ?? '') !== '' && $midterm === null ||
        ($_POST['final'] ?? '') !== '' && $final === null
    ) {
        $error = "Grades must be between 0 and 100.";
    } else {

        $final_grade = calculateFinalGrade($prelim, $midterm, $final);
        $remarks = gradeRemarks($final_grade);
        $status = ($final_grade === null) ? 'Incomplete' : 'Encoded';

        // Prevent accidental duplicate records for the same
        // student + subject + school year + semester.
        $check = $conn->prepare("
            SELECT id
            FROM grades
            WHERE student_number = ?
              AND subject_code = ?
              AND school_year = ?
              AND semester = ?
            LIMIT 1
        ");

        $duplicate = false;

        if ($check) {
            $check->bind_param(
                "ssss",
                $student_number,
                $subject_code,
                $school_year,
                $semester
            );
            $check->execute();
            $duplicate = (bool)$check->get_result()->fetch_assoc();
            $check->close();
        }

        if ($duplicate) {
            $error = "A grade record already exists for this student, subject, school year, and semester.";
        } else {

            $stmt = $conn->prepare("
                INSERT INTO grades (
                    student_number,
                    student_name,
                    program_code,
                    subject_code,
                    subject_name,
                    units,
                    school_year,
                    semester,
                    prelim,
                    midterm,
                    final,
                    final_grade,
                    remarks,
                    instructor,
                    status
                )
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");

            if ($stmt) {
                $stmt->bind_param(
                    "sssssdsdddddsss",
                    $student_number,
                    $student_name,
                    $program_code,
                    $subject_code,
                    $subject_name,
                    $units,
                    $school_year,
                    $semester,
                    $prelim,
                    $midterm,
                    $final,
                    $final_grade,
                    $remarks,
                    $instructor,
                    $status
                );

                if ($stmt->execute()) {
                    $success = "Grade encoded successfully.";
                } else {
                    $error = "Unable to encode grade.";
                }

                $stmt->close();
            } else {
                $error = "Unable to prepare grade query.";
            }
        }
    }
}

// =====================================================
// UPDATE GRADE
// =====================================================

if (isset($_POST['update_grade'])) {

    $id             = (int)($_POST['grade_id'] ?? 0);
    $student_number = trim($_POST['student_number'] ?? '');
    $student_name   = trim($_POST['student_name'] ?? '');
    $program_code   = trim($_POST['program_code'] ?? '');
    $subject_code   = trim($_POST['subject_code'] ?? '');
    $subject_name   = trim($_POST['subject_name'] ?? '');
    $units          = (float)($_POST['units'] ?? 3);
    $school_year    = trim($_POST['school_year'] ?? '');
    $semester       = trim($_POST['semester'] ?? '');
    $prelim         = gradeValue($_POST['prelim'] ?? '');
    $midterm        = gradeValue($_POST['midterm'] ?? '');
    $final          = gradeValue($_POST['final'] ?? '');
    $instructor     = trim($_POST['instructor'] ?? '');

    if (
        $id <= 0 ||
        $student_number === '' ||
        $student_name === '' ||
        $subject_code === '' ||
        $subject_name === '' ||
        $school_year === '' ||
        $semester === ''
    ) {
        $error = "Please complete all required grade fields.";
    } elseif ($units < 0) {
        $error = "Units cannot be negative.";
    } elseif (
        ($_POST['prelim'] ?? '') !== '' && $prelim === null ||
        ($_POST['midterm'] ?? '') !== '' && $midterm === null ||
        ($_POST['final'] ?? '') !== '' && $final === null
    ) {
        $error = "Grades must be between 0 and 100.";
    } else {

        $final_grade = calculateFinalGrade($prelim, $midterm, $final);
        $remarks = gradeRemarks($final_grade);
        $status = ($final_grade === null) ? 'Incomplete' : 'Encoded';

        $stmt = $conn->prepare("
            UPDATE grades
            SET
                student_number = ?,
                student_name = ?,
                program_code = ?,
                subject_code = ?,
                subject_name = ?,
                units = ?,
                school_year = ?,
                semester = ?,
                prelim = ?,
                midterm = ?,
                final = ?,
                final_grade = ?,
                remarks = ?,
                instructor = ?,
                status = ?
            WHERE id = ?
        ");

        if ($stmt) {
            $stmt->bind_param(
                "sssssdsdddddsssi",
                $student_number,
                $student_name,
                $program_code,
                $subject_code,
                $subject_name,
                $units,
                $school_year,
                $semester,
                $prelim,
                $midterm,
                $final,
                $final_grade,
                $remarks,
                $instructor,
                $status,
                $id
            );

            if ($stmt->execute()) {
                $success = "Grade updated successfully.";
            } else {
                $error = "Unable to update grade.";
            }

            $stmt->close();
        } else {
            $error = "Unable to prepare grade update.";
        }
    }
}

// =====================================================
// GRADE CORRECTION
// =====================================================

if (isset($_POST['correct_grade'])) {

    $id = (int)($_POST['correction_grade_id'] ?? 0);
    $new_grade = gradeValue($_POST['new_grade'] ?? '');
    $reason = trim($_POST['correction_reason'] ?? '');
    $corrected_by = trim($_POST['corrected_by'] ?? 'Administrator');

    if ($id <= 0 || $new_grade === null || $reason === '') {

        $error = "Please provide a valid new grade and correction reason.";

    } else {

        $stmt = $conn->prepare("
            SELECT
                id,
                student_number,
                student_name,
                subject_code,
                final_grade
            FROM grades
            WHERE id = ?
            LIMIT 1
        ");

        $record = null;

        if ($stmt) {
            $stmt->bind_param("i", $id);
            $stmt->execute();
            $record = $stmt->get_result()->fetch_assoc();
            $stmt->close();
        }

        if (!$record) {

            $error = "Grade record not found.";

        } else {

            $old_grade = $record['final_grade'];

            $update = $conn->prepare("
                UPDATE grades
                SET
                    final_grade = ?,
                    remarks = ?,
                    status = 'Corrected'
                WHERE id = ?
            ");

            $new_remarks = gradeRemarks($new_grade);

            if ($update) {

                $update->bind_param(
                    "dsi",
                    $new_grade,
                    $new_remarks,
                    $id
                );

                if ($update->execute()) {

                    $log = $conn->prepare("
                        INSERT INTO grade_corrections (
                            grade_id,
                            student_number,
                            student_name,
                            subject_code,
                            old_grade,
                            new_grade,
                            reason,
                            corrected_by
                        )
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?)
                    ");

                    if ($log) {
                        $log->bind_param(
                            "isssddss",
                            $id,
                            $record['student_number'],
                            $record['student_name'],
                            $record['subject_code'],
                            $old_grade,
                            $new_grade,
                            $reason,
                            $corrected_by
                        );
                        $log->execute();
                        $log->close();
                    }

                    $success = "Grade corrected successfully.";
                } else {
                    $error = "Unable to correct grade.";
                }

                $update->close();

            } else {
                $error = "Unable to prepare grade correction.";
            }
        }
    }
}

// =====================================================
// DELETE GRADE
// =====================================================

if (isset($_GET['delete_grade'])) {

    $id = (int)$_GET['delete_grade'];

    if ($id > 0) {

        $stmt = $conn->prepare("
            DELETE FROM grades
            WHERE id = ?
        ");

        if ($stmt) {
            $stmt->bind_param("i", $id);

            if ($stmt->execute()) {
                $success = "Grade record deleted successfully.";
            } else {
                $error = "Unable to delete grade record.";
            }

            $stmt->close();
        }
    }
}

// =====================================================
// SEARCH / FILTERS
// =====================================================

$search = trim($_GET['search'] ?? '');
$filter_program = trim($_GET['filter_program'] ?? '');
$filter_year = trim($_GET['filter_year'] ?? '');
$filter_semester = trim($_GET['filter_semester'] ?? '');

// =====================================================
// EDIT GRADE LOOKUP
// =====================================================

$editGrade = null;

if (isset($_GET['edit_grade'])) {

    $id = (int)$_GET['edit_grade'];

    $stmt = $conn->prepare("
        SELECT *
        FROM grades
        WHERE id = ?
        LIMIT 1
    ");

    if ($stmt) {
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $editGrade = $stmt->get_result()->fetch_assoc();
        $stmt->close();
    }
}

// =====================================================
// CORRECTION LOOKUP
// =====================================================

$correctionGrade = null;

if (isset($_GET['correct_grade'])) {

    $id = (int)$_GET['correct_grade'];

    $stmt = $conn->prepare("
        SELECT *
        FROM grades
        WHERE id = ?
        LIMIT 1
    ");

    if ($stmt) {
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $correctionGrade = $stmt->get_result()->fetch_assoc();
        $stmt->close();
    }
}

// =====================================================
// ACADEMIC HISTORY
// =====================================================

$historyStudent = trim($_GET['history_student'] ?? '');
$academicHistory = [];

if ($historyStudent !== '') {

    $stmt = $conn->prepare("
        SELECT *
        FROM grades
        WHERE student_number LIKE ?
        ORDER BY school_year DESC, semester DESC, subject_code ASC
    ");

    if ($stmt) {

        $likeHistory = "%" . $historyStudent . "%";

        $stmt->bind_param("s", $likeHistory);
        $stmt->execute();

        $result = $stmt->get_result();

        while ($row = $result->fetch_assoc()) {
            $academicHistory[] = $row;
        }

        $stmt->close();
    }
}

// =====================================================
// LOAD GRADES
// =====================================================

$grades = [];

$sql = "
    SELECT *
    FROM grades
    WHERE 1=1
";

$params = [];
$types = "";

if ($search !== '') {
    $sql .= "
        AND (
            student_number LIKE ?
            OR student_name LIKE ?
            OR subject_code LIKE ?
            OR subject_name LIKE ?
        )
    ";

    $like = "%" . $search . "%";

    $params[] = $like;
    $params[] = $like;
    $params[] = $like;
    $params[] = $like;

    $types .= "ssss";
}

if ($filter_program !== '') {
    $sql .= " AND program_code = ?";
    $params[] = $filter_program;
    $types .= "s";
}

if ($filter_year !== '') {
    $sql .= " AND school_year = ?";
    $params[] = $filter_year;
    $types .= "s";
}

if ($filter_semester !== '') {
    $sql .= " AND semester = ?";
    $params[] = $filter_semester;
    $types .= "s";
}

$sql .= " ORDER BY student_name ASC, subject_code ASC";

$stmt = $conn->prepare($sql);

if ($stmt) {

    if (!empty($params)) {
        $stmt->bind_param($types, ...$params);
    }

    $stmt->execute();

    $result = $stmt->get_result();

    while ($row = $result->fetch_assoc()) {
        $grades[] = $row;
    }

    $stmt->close();
}

// =====================================================
// LOAD FILTER OPTIONS
// =====================================================

$programOptions = [];
$yearOptions = [];

$result = $conn->query("
    SELECT DISTINCT program_code
    FROM grades
    WHERE program_code IS NOT NULL
      AND program_code <> ''
    ORDER BY program_code ASC
");

if ($result) {
    while ($row = $result->fetch_assoc()) {
        $programOptions[] = $row['program_code'];
    }
}

$result = $conn->query("
    SELECT DISTINCT school_year
    FROM grades
    WHERE school_year <> ''
    ORDER BY school_year DESC
");

if ($result) {
    while ($row = $result->fetch_assoc()) {
        $yearOptions[] = $row['school_year'];
    }
}

// =====================================================
// COUNTS / REPORTS
// =====================================================

$totalGrades = 0;
$passedGrades = 0;
$failedGrades = 0;
$incompleteGrades = 0;
$correctedGrades = 0;

$result = $conn->query("
    SELECT
        COUNT(*) AS total,
        SUM(CASE WHEN remarks = 'Passed' THEN 1 ELSE 0 END) AS passed,
        SUM(CASE WHEN remarks = 'Failed' THEN 1 ELSE 0 END) AS failed,
        SUM(CASE WHEN remarks = 'Incomplete' THEN 1 ELSE 0 END) AS incomplete,
        SUM(CASE WHEN status = 'Corrected' THEN 1 ELSE 0 END) AS corrected
    FROM grades
");

if ($result) {
    $row = $result->fetch_assoc();

    $totalGrades = (int)($row['total'] ?? 0);
    $passedGrades = (int)($row['passed'] ?? 0);
    $failedGrades = (int)($row['failed'] ?? 0);
    $incompleteGrades = (int)($row['incomplete'] ?? 0);
    $correctedGrades = (int)($row['corrected'] ?? 0);
}

// =====================================================
// CORRECTION HISTORY
// =====================================================

$corrections = [];

$result = $conn->query("
    SELECT *
    FROM grade_corrections
    ORDER BY corrected_at DESC
    LIMIT 100
");

if ($result) {
    while ($row = $result->fetch_assoc()) {
        $corrections[] = $row;
    }
}

// =====================================================
// REPORT FILTER
// =====================================================

$report_year = trim($_GET['report_year'] ?? '');
$report_semester = trim($_GET['report_semester'] ?? '');

$reportRows = [];

$reportSql = "
    SELECT
        program_code,
        COUNT(*) AS total,
        SUM(CASE WHEN remarks = 'Passed' THEN 1 ELSE 0 END) AS passed,
        SUM(CASE WHEN remarks = 'Failed' THEN 1 ELSE 0 END) AS failed,
        SUM(CASE WHEN remarks = 'Incomplete' THEN 1 ELSE 0 END) AS incomplete,
        ROUND(AVG(final_grade), 2) AS average_grade
    FROM grades
    WHERE 1=1
";

$reportParams = [];
$reportTypes = "";

if ($report_year !== '') {
    $reportSql .= " AND school_year = ?";
    $reportParams[] = $report_year;
    $reportTypes .= "s";
}

if ($report_semester !== '') {
    $reportSql .= " AND semester = ?";
    $reportParams[] = $report_semester;
    $reportTypes .= "s";
}

$reportSql .= "
    GROUP BY program_code
    ORDER BY program_code ASC
";

$stmt = $conn->prepare($reportSql);

if ($stmt) {

    if (!empty($reportParams)) {
        $stmt->bind_param($reportTypes, ...$reportParams);
    }

    $stmt->execute();

    $result = $stmt->get_result();

    while ($row = $result->fetch_assoc()) {
        $reportRows[] = $row;
    }

    $stmt->close();
}

?>
<!DOCTYPE html>
<html lang="en">

<head>

<meta charset="UTF-8">

<meta name="viewport" content="width=device-width, initial-scale=1.0">

<title>Grades Management | NORSU Registrar System</title>

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

        <a href="enrollments.php">
            <span class="icon">📝</span>
            Enrollment
        </a>

        <a href="programs.php">
            <span class="icon">📚</span>
            Programs
        </a>

        <a href="grades.php" class="active">
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

        <a href="users.php">
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

        <h1>Grades Management</h1>

    </div>

    <div class="admin-info">

        <div class="admin-details">
            <strong>Administrator</strong>
            <span>NORSU Registrar</span>
        </div>

        <div class="admin-avatar">A</div>

    </div>

</header>

<section class="content">

    <!-- PAGE HEADER -->

    <div class="page-header">

        <div>

            <div class="page-title">
                📊 Grades Management
            </div>

            <div class="page-subtitle">
                Encode, update, correct, view, and report student grades.
            </div>

        </div>

        <div style="display:flex;gap:8px;flex-wrap:wrap;">

            <a href="#encode-form" class="btn btn-primary">
                ➕ Encode Grade
            </a>

            <a href="#academic-history" class="btn btn-blue">
                📚 Academic History
            </a>

            <a href="#grade-reports" class="btn btn-success">
                📈 Grade Reports
            </a>

        </div>

    </div>

    <!-- ALERTS -->

    <?php if ($success !== ""): ?>

        <div class="alert alert-success">
            ✅ <?= htmlspecialchars($success) ?>
        </div>

    <?php endif; ?>

    <?php if ($error !== ""): ?>

        <div class="alert alert-error">
            ❌ <?= htmlspecialchars($error) ?>
        </div>

    <?php endif; ?>

    <!-- STATISTICS -->

    <div class="stats">

        <div class="stat-card">
            <div class="stat-label">Total Grades</div>
            <div class="stat-number"><?= $totalGrades ?></div>
        </div>

        <div class="stat-card">
            <div class="stat-label">Passed</div>
            <div class="stat-number"><?= $passedGrades ?></div>
        </div>

        <div class="stat-card">
            <div class="stat-label">Failed</div>
            <div class="stat-number"><?= $failedGrades ?></div>
        </div>

        <div class="stat-card">
            <div class="stat-label">Incomplete</div>
            <div class="stat-number"><?= $incompleteGrades ?></div>
        </div>

        <div class="stat-card">
            <div class="stat-label">Corrected</div>
            <div class="stat-number"><?= $correctedGrades ?></div>
        </div>

    </div>

    <!-- =================================================
         ENCODE / UPDATE FORM
    ================================================== -->

    <div class="card" id="encode-form">

        <div class="card-header">

            <div class="card-title">

                <?php if ($editGrade): ?>
                    ✏️ Update Grade
                <?php else: ?>
                    ➕ Encode Grade
                <?php endif; ?>

            </div>

        </div>

        <div class="card-body">

            <div class="info-box">
                <strong>Grade scale:</strong>
                Enter grades from 0 to 100. The Final Grade is calculated
                from the available Prelim, Midterm, and Final grades.
            </div>

            <form method="POST">

                <?php if ($editGrade): ?>

                    <input
                        type="hidden"
                        name="grade_id"
                        value="<?= (int)$editGrade['id'] ?>"
                    >

                <?php endif; ?>

                <div class="form-grid">

                    <div class="form-group">

                        <label>Student Number *</label>

                        <input
                            type="text"
                            name="student_number"
                            required
                            placeholder="Example: 2026-00001"
                            value="<?= htmlspecialchars($editGrade['student_number'] ?? '') ?>"
                        >

                    </div>

                    <div class="form-group">

                        <label>Student Name *</label>

                        <input
                            type="text"
                            name="student_name"
                            required
                            placeholder="Last Name, First Name"
                            value="<?= htmlspecialchars($editGrade['student_name'] ?? '') ?>"
                        >

                    </div>

                    <div class="form-group">

                        <label>Program</label>

                        <input
                            type="text"
                            name="program_code"
                            placeholder="Example: BSIT"
                            value="<?= htmlspecialchars($editGrade['program_code'] ?? '') ?>"
                        >

                    </div>

                    <div class="form-group">

                        <label>Subject Code *</label>

                        <input
                            type="text"
                            name="subject_code"
                            required
                            placeholder="Example: IT101"
                            value="<?= htmlspecialchars($editGrade['subject_code'] ?? '') ?>"
                        >

                    </div>

                    <div class="form-group">

                        <label>Subject Name *</label>

                        <input
                            type="text"
                            name="subject_name"
                            required
                            placeholder="Introduction to Information Technology"
                            value="<?= htmlspecialchars($editGrade['subject_name'] ?? '') ?>"
                        >

                    </div>

                    <div class="form-group">

                        <label>Units</label>

                        <input
                            type="number"
                            name="units"
                            step="0.5"
                            min="0"
                            value="<?= htmlspecialchars($editGrade['units'] ?? '3') ?>"
                        >

                    </div>

                    <div class="form-group">

                        <label>School Year *</label>

                        <input
                            type="text"
                            name="school_year"
                            required
                            placeholder="2026-2027"
                            value="<?= htmlspecialchars($editGrade['school_year'] ?? '') ?>"
                        >

                    </div>

                    <div class="form-group">

                        <label>Semester *</label>

                        <select name="semester" required>

                            <option value="">-- Select Semester --</option>

                            <option
                                value="1st Semester"
                                <?= (($editGrade['semester'] ?? '') === '1st Semester') ? 'selected' : '' ?>
                            >
                                1st Semester
                            </option>

                            <option
                                value="2nd Semester"
                                <?= (($editGrade['semester'] ?? '') === '2nd Semester') ? 'selected' : '' ?>
                            >
                                2nd Semester
                            </option>

                            <option
                                value="Summer"
                                <?= (($editGrade['semester'] ?? '') === 'Summer') ? 'selected' : '' ?>
                            >
                                Summer
                            </option>

                        </select>

                    </div>

                    <div class="form-group">

                        <label>Instructor</label>

                        <input
                            type="text"
                            name="instructor"
                            placeholder="Instructor name"
                            value="<?= htmlspecialchars($editGrade['instructor'] ?? '') ?>"
                        >

                    </div>

                    <div class="form-group">

                        <label>Prelim Grade</label>

                        <input
                            type="number"
                            name="prelim"
                            min="0"
                            max="100"
                            step="0.01"
                            placeholder="0 - 100"
                            value="<?= htmlspecialchars($editGrade['prelim'] ?? '') ?>"
                        >

                    </div>

                    <div class="form-group">

                        <label>Midterm Grade</label>

                        <input
                            type="number"
                            name="midterm"
                            min="0"
                            max="100"
                            step="0.01"
                            placeholder="0 - 100"
                            value="<?= htmlspecialchars($editGrade['midterm'] ?? '') ?>"
                        >

                    </div>

                    <div class="form-group">

                        <label>Final Grade</label>

                        <input
                            type="number"
                            name="final"
                            min="0"
                            max="100"
                            step="0.01"
                            placeholder="0 - 100"
                            value="<?= htmlspecialchars($editGrade['final'] ?? '') ?>"
                        >

                    </div>

                </div>

                <div class="form-buttons">

                    <?php if ($editGrade): ?>

                        <button
                            type="submit"
                            name="update_grade"
                            class="btn btn-primary"
                        >
                            💾 Update Grade
                        </button>

                        <a
                            href="grades.php"
                            class="btn btn-gray"
                        >
                            Cancel
                        </a>

                    <?php else: ?>

                        <button
                            type="submit"
                            name="encode_grade"
                            class="btn btn-primary"
                        >
                            ➕ Encode Grade
                        </button>

                    <?php endif; ?>

                </div>

            </form>

        </div>

    </div>

    <!-- =================================================
         VIEW GRADES
    ================================================== -->

    <div class="card">

        <div class="card-header">

            <div class="card-title">
                📋 View Grades
            </div>

        </div>

        <div class="card-body">

            <form method="GET" class="filter-grid">

                <div class="filter-group">

                    <label>Search</label>

                    <input
                        type="text"
                        name="search"
                        placeholder="Student number, name, subject..."
                        value="<?= htmlspecialchars($search) ?>"
                    >

                </div>

                <div class="filter-group">

                    <label>Program</label>

                    <select name="filter_program">

                        <option value="">All Programs</option>

                        <?php foreach ($programOptions as $program): ?>

                            <option
                                value="<?= htmlspecialchars($program) ?>"
                                <?= ($filter_program === $program) ? 'selected' : '' ?>
                            >
                                <?= htmlspecialchars($program) ?>
                            </option>

                        <?php endforeach; ?>

                    </select>

                </div>

                <div class="filter-group">

                    <label>School Year</label>

                    <select name="filter_year">

                        <option value="">All Years</option>

                        <?php foreach ($yearOptions as $year): ?>

                            <option
                                value="<?= htmlspecialchars($year) ?>"
                                <?= ($filter_year === $year) ? 'selected' : '' ?>
                            >
                                <?= htmlspecialchars($year) ?>
                            </option>

                        <?php endforeach; ?>

                    </select>

                </div>

                <div class="filter-group">

                    <label>Semester</label>

                    <select name="filter_semester">

                        <option value="">All Semesters</option>

                        <option
                            value="1st Semester"
                            <?= ($filter_semester === '1st Semester') ? 'selected' : '' ?>
                        >
                            1st Semester
                        </option>

                        <option
                            value="2nd Semester"
                            <?= ($filter_semester === '2nd Semester') ? 'selected' : '' ?>
                        >
                            2nd Semester
                        </option>

                        <option
                            value="Summer"
                            <?= ($filter_semester === 'Summer') ? 'selected' : '' ?>
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

            </form>

        </div>

        <div class="table-container">

            <table>

                <thead>

                    <tr>

                        <th>#</th>
                        <th>Student</th>
                        <th>Program</th>
                        <th>Subject</th>
                        <th>Units</th>
                        <th>School Year</th>
                        <th>Semester</th>
                        <th>Prelim</th>
                        <th>Midterm</th>
                        <th>Final</th>
                        <th>Final Grade</th>
                        <th>Remarks</th>
                        <th>Status</th>
                        <th>Actions</th>

                    </tr>

                </thead>

                <tbody>

                    <?php if (empty($grades)): ?>

                        <tr>

                            <td
                                colspan="14"
                                class="empty"
                            >
                                No grade records found.
                            </td>

                        </tr>

                    <?php else: ?>

                        <?php foreach ($grades as $index => $grade): ?>

                            <tr>

                                <td>
                                    <?= $index + 1 ?>
                                </td>

                                <td>

                                    <strong>
                                        <?= htmlspecialchars($grade['student_number']) ?>
                                    </strong>

                                    <br>

                                    <?= htmlspecialchars($grade['student_name']) ?>

                                </td>

                                <td>
                                    <?= htmlspecialchars($grade['program_code'] ?? '') ?>
                                </td>

                                <td>

                                    <strong>
                                        <?= htmlspecialchars($grade['subject_code']) ?>
                                    </strong>

                                    <br>

                                    <?= htmlspecialchars($grade['subject_name']) ?>

                                </td>

                                <td>
                                    <?= htmlspecialchars($grade['units']) ?>
                                </td>

                                <td>
                                    <?= htmlspecialchars($grade['school_year']) ?>
                                </td>

                                <td>
                                    <?= htmlspecialchars($grade['semester']) ?>
                                </td>

                                <td>
                                    <?= $grade['prelim'] !== null ? htmlspecialchars($grade['prelim']) : '-' ?>
                                </td>

                                <td>
                                    <?= $grade['midterm'] !== null ? htmlspecialchars($grade['midterm']) : '-' ?>
                                </td>

                                <td>
                                    <?= $grade['final'] !== null ? htmlspecialchars($grade['final']) : '-' ?>
                                </td>

                                <td>

                                    <strong>
                                        <?= $grade['final_grade'] !== null ? htmlspecialchars($grade['final_grade']) : '-' ?>
                                    </strong>

                                </td>

                                <td>

                                    <?php if ($grade['remarks'] === 'Passed'): ?>

                                        <span class="badge badge-passed">
                                            Passed
                                        </span>

                                    <?php elseif ($grade['remarks'] === 'Failed'): ?>

                                        <span class="badge badge-failed">
                                            Failed
                                        </span>

                                    <?php else: ?>

                                        <span class="badge badge-incomplete">
                                            Incomplete
                                        </span>

                                    <?php endif; ?>

                                </td>

                                <td>

                                    <?php if ($grade['status'] === 'Corrected'): ?>

                                        <span class="badge badge-corrected">
                                            Corrected
                                        </span>

                                    <?php elseif ($grade['status'] === 'Incomplete'): ?>

                                        <span class="badge badge-incomplete">
                                            Incomplete
                                        </span>

                                    <?php else: ?>

                                        <span class="badge badge-passed">
                                            Encoded
                                        </span>

                                    <?php endif; ?>

                                </td>

                                <td>

                                    <div class="actions">

                                        <a
                                            href="grades.php?edit_grade=<?= (int)$grade['id'] ?>"
                                            class="btn btn-blue"
                                        >
                                            ✏️ Edit
                                        </a>

                                        <a
                                            href="grades.php?correct_grade=<?= (int)$grade['id'] ?>"
                                            class="btn btn-warning"
                                        >
                                            🔧 Correct
                                        </a>

                                        <a
                                            href="grades.php?history_student=<?= urlencode($grade['student_number']) ?>#academic-history"
                                            class="btn btn-success"
                                        >
                                            📚 History
                                        </a>

                                        <a
                                            href="grades.php?delete_grade=<?= (int)$grade['id'] ?>"
                                            class="btn btn-danger"
                                            onclick="return confirm('Are you sure you want to delete this grade record?');"
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
         GRADE CORRECTION
    ================================================== -->

    <?php if ($correctionGrade): ?>

        <div class="card">

            <div class="card-header">

                <div class="card-title">
                    🔧 Grade Correction
                </div>

            </div>

            <div class="card-body">

                <div class="info-box">

                    <strong>
                        Student:
                    </strong>

                    <?= htmlspecialchars($correctionGrade['student_number']) ?>

                    -

                    <?= htmlspecialchars($correctionGrade['student_name']) ?>

                    <br>

                    <strong>
                        Subject:
                    </strong>

                    <?= htmlspecialchars($correctionGrade['subject_code']) ?>

                    -

                    <?= htmlspecialchars($correctionGrade['subject_name']) ?>

                    <br>

                    <strong>
                        Current Final Grade:
                    </strong>

                    <?= $correctionGrade['final_grade'] !== null
                        ? htmlspecialchars($correctionGrade['final_grade'])
                        : 'Incomplete'
                    ?>

                </div>

                <form method="POST">

                    <input
                        type="hidden"
                        name="correction_grade_id"
                        value="<?= (int)$correctionGrade['id'] ?>"
                    >

                    <div class="form-grid">

                        <div class="form-group">

                            <label>New Final Grade *</label>

                            <input
                                type="number"
                                name="new_grade"
                                required
                                min="0"
                                max="100"
                                step="0.01"
                                placeholder="0 - 100"
                            >

                        </div>

                        <div class="form-group">

                            <label>Corrected By</label>

                            <input
                                type="text"
                                name="corrected_by"
                                value="Administrator"
                            >

                        </div>

                        <div class="form-group full">

                            <label>Reason for Correction *</label>

                            <textarea
                                name="correction_reason"
                                required
                                placeholder="Explain why the grade needs to be corrected..."
                            ></textarea>

                        </div>

                    </div>

                    <div class="form-buttons">

                        <button
                            type="submit"
                            name="correct_grade"
                            class="btn btn-warning"
                        >
                            🔧 Save Grade Correction
                        </button>

                        <a
                            href="grades.php"
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
         ACADEMIC HISTORY
    ================================================== -->

    <div
        class="card"
        id="academic-history"
    >

        <div class="card-header">

            <div class="card-title">
                📚 Student Academic History
            </div>

        </div>

        <div class="card-body">

            <form method="GET" style="display:flex;gap:10px;">

                <input
                    type="text"
                    name="history_student"
                    placeholder="Enter student number"
                    value="<?= htmlspecialchars($historyStudent) ?>"
                    style="
                        flex:1;
                        padding:11px 13px;
                        border:1px solid #ccc;
                        border-radius:6px;
                        font-size:14px;
                    "
                >

                <button
                    type="submit"
                    class="btn btn-primary"
                >
                    🔍 View History
                </button>

            </form>

        </div>

        <?php if ($historyStudent !== ''): ?>

            <div class="card-body">

                <?php if (!empty($academicHistory)): ?>

                    <?php
                    $historyName = $academicHistory[0]['student_name'];
                    $historyProgram = $academicHistory[0]['program_code'];
                    ?>

                    <div class="info-box">

                        <strong>Student Number:</strong>
                        <?= htmlspecialchars($historyStudent) ?>

                        <br>

                        <strong>Student Name:</strong>
                        <?= htmlspecialchars($historyName) ?>

                        <br>

                        <strong>Program:</strong>
                        <?= htmlspecialchars($historyProgram ?? '') ?>

                    </div>

                    <div class="table-container">

                        <table>

                            <thead>

                                <tr>

                                    <th>School Year</th>
                                    <th>Semester</th>
                                    <th>Subject</th>
                                    <th>Units</th>
                                    <th>Final Grade</th>
                                    <th>Remarks</th>
                                    <th>Status</th>

                                </tr>

                            </thead>

                            <tbody>

                                <?php foreach ($academicHistory as $history): ?>

                                    <tr>

                                        <td>
                                            <?= htmlspecialchars($history['school_year']) ?>
                                        </td>

                                        <td>
                                            <?= htmlspecialchars($history['semester']) ?>
                                        </td>

                                        <td>

                                            <strong>
                                                <?= htmlspecialchars($history['subject_code']) ?>
                                            </strong>

                                            <br>

                                            <?= htmlspecialchars($history['subject_name']) ?>

                                        </td>

                                        <td>
                                            <?= htmlspecialchars($history['units']) ?>
                                        </td>

                                        <td>

                                            <strong>
                                                <?= $history['final_grade'] !== null
                                                    ? htmlspecialchars($history['final_grade'])
                                                    : '-'
                                                ?>
                                            </strong>

                                        </td>

                                        <td>

                                            <?php if ($history['remarks'] === 'Passed'): ?>

                                                <span class="badge badge-passed">
                                                    Passed
                                                </span>

                                            <?php elseif ($history['remarks'] === 'Failed'): ?>

                                                <span class="badge badge-failed">
                                                    Failed
                                                </span>

                                            <?php else: ?>

                                                <span class="badge badge-incomplete">
                                                    Incomplete
                                                </span>

                                            <?php endif; ?>

                                        </td>

                                        <td>
                                            <?= htmlspecialchars($history['status']) ?>
                                        </td>

                                    </tr>

                                <?php endforeach; ?>

                            </tbody>

                        </table>

                    </div>

                <?php else: ?>

                    <div class="empty">
                        No academic history found for
                        <strong><?= htmlspecialchars($historyStudent) ?></strong>.
                    </div>

                <?php endif; ?>

            </div>

        <?php endif; ?>

    </div>

    <!-- =================================================
         GRADE REPORTS
    ================================================== -->

    <div
        class="card"
        id="grade-reports"
    >

        <div class="card-header">

            <div class="card-title">
                📈 Grade Reports
            </div>

        </div>

        <div class="card-body">

            <form method="GET" class="filter-grid">

                <div class="filter-group">

                    <label>School Year</label>

                    <select name="report_year">

                        <option value="">
                            All School Years
                        </option>

                        <?php foreach ($yearOptions as $year): ?>

                            <option
                                value="<?= htmlspecialchars($year) ?>"
                                <?= ($report_year === $year) ? 'selected' : '' ?>
                            >
                                <?= htmlspecialchars($year) ?>
                            </option>

                        <?php endforeach; ?>

                    </select>

                </div>

                <div class="filter-group">

                    <label>Semester</label>

                    <select name="report_semester">

                        <option value="">
                            All Semesters
                        </option>

                        <option
                            value="1st Semester"
                            <?= ($report_semester === '1st Semester') ? 'selected' : '' ?>
                        >
                            1st Semester
                        </option>

                        <option
                            value="2nd Semester"
                            <?= ($report_semester === '2nd Semester') ? 'selected' : '' ?>
                        >
                            2nd Semester
                        </option>

                        <option
                            value="Summer"
                            <?= ($report_semester === 'Summer') ? 'selected' : '' ?>
                        >
                            Summer
                        </option>

                    </select>

                </div>

                <div>

                    <button
                        type="submit"
                        class="btn btn-success"
                    >
                        📊 Generate Report
                    </button>

                </div>

            </form>

        </div>

        <div class="table-container">

            <table>

                <thead>

                    <tr>

                        <th>Program</th>
                        <th>Total Grades</th>
                        <th>Passed</th>
                        <th>Failed</th>
                        <th>Incomplete</th>
                        <th>Average Grade</th>

                    </tr>

                </thead>

                <tbody>

                    <?php if (empty($reportRows)): ?>

                        <tr>

                            <td
                                colspan="6"
                                class="empty"
                            >
                                No report data available.
                            </td>

                        </tr>

                    <?php else: ?>

                        <?php foreach ($reportRows as $report): ?>

                            <tr>

                                <td>
                                    <?= htmlspecialchars($report['program_code'] ?: 'No Program') ?>
                                </td>

                                <td>
                                    <?= (int)$report['total'] ?>
                                </td>

                                <td>
                                    <?= (int)$report['passed'] ?>
                                </td>

                                <td>
                                    <?= (int)$report['failed'] ?>
                                </td>

                                <td>
                                    <?= (int)$report['incomplete'] ?>
                                </td>

                                <td>

                                    <strong>
                                        <?= $report['average_grade'] !== null
                                            ? htmlspecialchars($report['average_grade'])
                                            : '-'
                                        ?>
                                    </strong>

                                </td>

                            </tr>

                        <?php endforeach; ?>

                    <?php endif; ?>

                </tbody>

            </table>

        </div>

    </div>

    <!-- =================================================
         CORRECTION HISTORY
    ================================================== -->

    <div class="card correction-table">

        <div class="card-header">

            <div class="card-title">
                📝 Grade Correction History
            </div>

            <strong>
                Last 100 corrections
            </strong>

        </div>

        <div class="table-container">

            <table>

                <thead>

                    <tr>

                        <th>Date</th>
                        <th>Student</th>
                        <th>Subject</th>
                        <th>Old Grade</th>
                        <th>New Grade</th>
                        <th>Reason</th>
                        <th>Corrected By</th>

                    </tr>

                </thead>

                <tbody>

                    <?php if (empty($corrections)): ?>

                        <tr>

                            <td
                                colspan="7"
                                class="empty"
                            >
                                No grade corrections recorded.
                            </td>

                        </tr>

                    <?php else: ?>

                        <?php foreach ($corrections as $correction): ?>

                            <tr>

                                <td>
                                    <?= htmlspecialchars($correction['corrected_at']) ?>
                                </td>

                                <td>

                                    <strong>
                                        <?= htmlspecialchars($correction['student_number']) ?>
                                    </strong>

                                    <br>

                                    <?= htmlspecialchars($correction['student_name']) ?>

                                </td>

                                <td>
                                    <?= htmlspecialchars($correction['subject_code']) ?>
                                </td>

                                <td>
                                    <?= $correction['old_grade'] !== null
                                        ? htmlspecialchars($correction['old_grade'])
                                        : 'Incomplete'
                                    ?>
                                </td>

                                <td>
                                    <?= htmlspecialchars($correction['new_grade']) ?>
                                </td>

                                <td>
                                    <?= htmlspecialchars($correction['reason']) ?>
                                </td>

                                <td>
                                    <?= htmlspecialchars($correction['corrected_by']) ?>
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

function toggleSidebar()
{
    const sidebar = document.getElementById("sidebar");

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
                        .classList.remove("show");
                }
            }
        );
    });

</script>

</body>

</html>

<?php
$conn->close();
?>

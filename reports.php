<?php
session_start();

/*
=========================================================
    NORSU REGISTRAR SYSTEM
    REPORTS MANAGEMENT

    File Name:
    reports.php

    Database:
    haha

    FEATURES:
    - Student Population Report
    - Enrollment Report
    - Grade Report
    - Graduating Students Report
    - Document Request Report
    - Program / Course Report
    - Student Academic Records
    - Reports by School Year / Semester
    - Search and Filter
    - Print Report
    - CSV Export
=========================================================
*/

mysqli_report(MYSQLI_REPORT_OFF);

$conn = new mysqli("localhost", "root", "", "haha");

if ($conn->connect_error) {
    die("Database connection failed: " . $conn->connect_error);
}

$conn->set_charset("utf8mb4");

/* =====================================================
   HELPERS
===================================================== */
function e($value) {
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function tableExists($conn, $table) {
    $table = $conn->real_escape_string($table);
    $result = $conn->query("SHOW TABLES LIKE '{$table}'");
    return $result && $result->num_rows > 0;
}

function columnExists($conn, $table, $column) {
    if (!tableExists($conn, $table)) {
        return false;
    }

    $table = str_replace('`', '', $table);
    $column = $conn->real_escape_string($column);
    $result = $conn->query("SHOW COLUMNS FROM `{$table}` LIKE '{$column}'");
    return $result && $result->num_rows > 0;
}

function firstExistingTable($conn, $tables) {
    foreach ($tables as $table) {
        if (tableExists($conn, $table)) {
            return $table;
        }
    }
    return '';
}

function firstExistingColumn($conn, $table, $columns) {
    foreach ($columns as $column) {
        if (columnExists($conn, $table, $column)) {
            return $column;
        }
    }
    return '';
}

function getDistinctValues($conn, $table, $column, $limit = 100) {
    $values = [];

    if ($table === '' || $column === '' || !tableExists($conn, $table) || !columnExists($conn, $table, $column)) {
        return $values;
    }

    $limit = max(1, min(500, (int)$limit));

    $sql = "
        SELECT DISTINCT `{$column}` AS value
        FROM `{$table}`
        WHERE `{$column}` IS NOT NULL
          AND TRIM(`{$column}`) <> ''
        ORDER BY `{$column}` DESC
        LIMIT {$limit}
    ";

    $result = $conn->query($sql);

    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $values[] = $row['value'];
        }
    }

    return $values;
}

function safeCount($conn, $table, $where = '1=1') {
    if ($table === '' || !tableExists($conn, $table)) {
        return 0;
    }

    $result = $conn->query("SELECT COUNT(*) AS total FROM `{$table}` WHERE {$where}");

    if ($result && ($row = $result->fetch_assoc())) {
        return (int)$row['total'];
    }

    return 0;
}

function csvDownload($filename, $headers, $rows) {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');

    $output = fopen('php://output', 'w');
    fputcsv($output, $headers);

    foreach ($rows as $row) {
        fputcsv($output, $row);
    }

    fclose($output);
    exit;
}

function runQueryRows($conn, $sql) {
    $rows = [];
    $result = $conn->query($sql);

    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $rows[] = $row;
        }
    }

    return $rows;
}

/* =====================================================
   CURRENT ADMIN
===================================================== */
$adminName = $_SESSION['admin_username']
    ?? $_SESSION['username']
    ?? 'Administrator';

/* =====================================================
   FILTERS
===================================================== */
$report = trim($_GET['report'] ?? 'population');
$search = trim($_GET['search'] ?? '');
$schoolYear = trim($_GET['school_year'] ?? '');
$semester = trim($_GET['semester'] ?? '');

$allowedReports = [
    'population',
    'enrollment',
    'grades',
    'graduating',
    'documents',
    'programs',
    'academic'
];

if (!in_array($report, $allowedReports, true)) {
    $report = 'population';
}

/* =====================================================
   DISCOVER COMMON TABLES
===================================================== */
$studentsTable = firstExistingTable($conn, [
    'students',
    'student',
    'student_records'
]);

$enrollmentTable = firstExistingTable($conn, [
    'enrollments',
    'enrollment',
    'student_enrollments'
]);

$gradesTable = firstExistingTable($conn, [
    'grades',
    'grade_records',
    'student_grades'
]);

$programTable = firstExistingTable($conn, [
    'programs',
    'program',
    'courses'
]);

$documentsTable = firstExistingTable($conn, [
    'document_requests',
    'document_request'
]);

$graduationTable = firstExistingTable($conn, [
    'graduation_records',
    'graduation'
]);

/* =====================================================
   DISCOVER COLUMNS
===================================================== */
$studentNumberCol = firstExistingColumn($conn, $studentsTable, [
    'student_number',
    'student_no',
    'student_id'
]);

$studentNameCol = firstExistingColumn($conn, $studentsTable, [
    'student_name',
    'name',
    'full_name'
]);

$studentProgramCol = firstExistingColumn($conn, $studentsTable, [
    'program',
    'program_name',
    'course',
    'course_name'
]);

$studentYearLevelCol = firstExistingColumn($conn, $studentsTable, [
    'year_level',
    'year',
    'level'
]);

$studentSchoolYearCol = firstExistingColumn($conn, $studentsTable, [
    'school_year',
    'academic_year',
    'schoolyear'
]);

$studentSemesterCol = firstExistingColumn($conn, $studentsTable, [
    'semester',
    'term'
]);

$enrollmentStudentCol = firstExistingColumn($conn, $enrollmentTable, [
    'student_number',
    'student_no',
    'student_id'
]);

$enrollmentNameCol = firstExistingColumn($conn, $enrollmentTable, [
    'student_name',
    'name',
    'full_name'
]);

$enrollmentProgramCol = firstExistingColumn($conn, $enrollmentTable, [
    'program',
    'program_name',
    'course',
    'course_name'
]);

$enrollmentSchoolYearCol = firstExistingColumn($conn, $enrollmentTable, [
    'school_year',
    'academic_year',
    'schoolyear'
]);

$enrollmentSemesterCol = firstExistingColumn($conn, $enrollmentTable, [
    'semester',
    'term'
]);

$gradeStudentCol = firstExistingColumn($conn, $gradesTable, [
    'student_number',
    'student_no',
    'student_id'
]);

$gradeNameCol = firstExistingColumn($conn, $gradesTable, [
    'student_name',
    'name',
    'full_name'
]);

$gradeProgramCol = firstExistingColumn($conn, $gradesTable, [
    'program',
    'program_name',
    'course',
    'course_name'
]);

$gradeSubjectCol = firstExistingColumn($conn, $gradesTable, [
    'subject_code',
    'course_code',
    'subject',
    'course'
]);

$gradeValueCol = firstExistingColumn($conn, $gradesTable, [
    'grade',
    'final_grade',
    'grade_value',
    'final'
]);

$gradeSchoolYearCol = firstExistingColumn($conn, $gradesTable, [
    'school_year',
    'academic_year',
    'schoolyear'
]);

$gradeSemesterCol = firstExistingColumn($conn, $gradesTable, [
    'semester',
    'term'
]);

$programNameCol = firstExistingColumn($conn, $programTable, [
    'program_name',
    'program',
    'course_name',
    'course'
]);

$programCodeCol = firstExistingColumn($conn, $programTable, [
    'program_code',
    'code',
    'course_code'
]);

$programDepartmentCol = firstExistingColumn($conn, $programTable, [
    'department',
    'department_name'
]);

$programYearLevelCol = firstExistingColumn($conn, $programTable, [
    'year_level',
    'years'
]);

$documentStudentCol = firstExistingColumn($conn, $documentsTable, [
    'student_number',
    'student_no',
    'student_id'
]);

$documentNameCol = firstExistingColumn($conn, $documentsTable, [
    'student_name',
    'name',
    'full_name'
]);

$documentTypeCol = firstExistingColumn($conn, $documentsTable, [
    'document_type',
    'type',
    'request_type'
]);

$documentStatusCol = firstExistingColumn($conn, $documentsTable, [
    'status'
]);

$documentDateCol = firstExistingColumn($conn, $documentsTable, [
    'request_date',
    'created_at',
    'date_requested'
]);

$documentSchoolYearCol = firstExistingColumn($conn, $documentsTable, [
    'school_year',
    'academic_year',
    'schoolyear'
]);

$documentSemesterCol = firstExistingColumn($conn, $documentsTable, [
    'semester',
    'term'
]);

$gradStudentCol = firstExistingColumn($conn, $graduationTable, [
    'student_number',
    'student_no',
    'student_id'
]);

$gradNameCol = firstExistingColumn($conn, $graduationTable, [
    'student_name',
    'name',
    'full_name'
]);

$gradProgramCol = firstExistingColumn($conn, $graduationTable, [
    'program',
    'program_name',
    'course',
    'course_name'
]);

$gradYearCol = firstExistingColumn($conn, $graduationTable, [
    'graduation_year',
    'school_year',
    'academic_year'
]);

$gradTermCol = firstExistingColumn($conn, $graduationTable, [
    'graduation_term',
    'semester',
    'term'
]);

$gradStatusCol = firstExistingColumn($conn, $graduationTable, [
    'status',
    'graduation_status'
]);

/* =====================================================
   SCHOOL YEAR / SEMESTER OPTIONS
===================================================== */
$schoolYears = [];

foreach ([
    [$studentsTable, $studentSchoolYearCol],
    [$enrollmentTable, $enrollmentSchoolYearCol],
    [$gradesTable, $gradeSchoolYearCol],
    [$documentsTable, $documentSchoolYearCol],
    [$graduationTable, $gradYearCol]
] as $source) {
    if ($source[0] !== '' && $source[1] !== '') {
        foreach (getDistinctValues($conn, $source[0], $source[1]) as $value) {
            if (!in_array((string)$value, $schoolYears, true)) {
                $schoolYears[] = (string)$value;
            }
        }
    }
}

rsort($schoolYears);

$semesters = [
    '1st Semester',
    '2nd Semester',
    'Summer'
];

/* =====================================================
   REPORT DATA
===================================================== */
$rows = [];
$reportHeaders = [];
$reportTitle = '';
$reportDescription = '';
$totalRows = 0;
$sourceTable = '';

/* -----------------------------------------------------
   STUDENT POPULATION REPORT
----------------------------------------------------- */
if ($report === 'population') {

    $reportTitle = 'Student Population Report';
    $reportDescription = 'Summary of students grouped by program and year level.';
    $sourceTable = $studentsTable;

    if ($studentsTable !== '' && $studentProgramCol !== '') {

        $where = '1=1';

        if ($schoolYear !== '' && $studentSchoolYearCol !== '') {
            $where .= " AND `{$studentSchoolYearCol}` = '" . $conn->real_escape_string($schoolYear) . "'";
        }

        if ($semester !== '' && $studentSemesterCol !== '') {
            $where .= " AND `{$studentSemesterCol}` = '" . $conn->real_escape_string($semester) . "'";
        }

        if ($search !== '') {
            $like = $conn->real_escape_string('%' . $search . '%');
            $searchParts = [];

            if ($studentProgramCol !== '') {
                $searchParts[] = "`{$studentProgramCol}` LIKE '{$like}'";
            }

            if ($studentYearLevelCol !== '') {
                $searchParts[] = "`{$studentYearLevelCol}` LIKE '{$like}'";
            }

            if ($studentNameCol !== '') {
                $searchParts[] = "`{$studentNameCol}` LIKE '{$like}'";
            }

            if (!empty($searchParts)) {
                $where .= ' AND (' . implode(' OR ', $searchParts) . ')';
            }
        }

        $yearExpr = $studentYearLevelCol !== ''
            ? "`{$studentYearLevelCol}`"
            : "'Not Specified'";

        $sql = "
            SELECT
                `{$studentProgramCol}` AS program_name,
                {$yearExpr} AS year_level,
                COUNT(*) AS student_count
            FROM `{$studentsTable}`
            WHERE {$where}
            GROUP BY `{$studentProgramCol}`, {$yearExpr}
            ORDER BY `{$studentProgramCol}`, {$yearExpr}
        ";

        $rows = runQueryRows($conn, $sql);
        $reportHeaders = ['Program / Course', 'Year Level', 'Student Population', 'Percentage'];
        $totalRows = array_sum(array_map(function($r) {
            return (int)$r['student_count'];
        }, $rows));

    } else {
        $reportDescription .= ' The students table or expected program column was not found in the database.';
    }
}

/* -----------------------------------------------------
   ENROLLMENT REPORT
----------------------------------------------------- */
if ($report === 'enrollment') {

    $reportTitle = 'Enrollment Report';
    $reportDescription = 'Enrollment totals by school year, semester, and program.';
    $sourceTable = $enrollmentTable;

    if ($enrollmentTable !== '') {

        $where = '1=1';

        if ($schoolYear !== '' && $enrollmentSchoolYearCol !== '') {
            $where .= " AND `{$enrollmentSchoolYearCol}` = '" . $conn->real_escape_string($schoolYear) . "'";
        }

        if ($semester !== '' && $enrollmentSemesterCol !== '') {
            $where .= " AND `{$enrollmentSemesterCol}` = '" . $conn->real_escape_string($semester) . "'";
        }

        if ($search !== '') {
            $like = $conn->real_escape_string('%' . $search . '%');
            $searchParts = [];

            if ($enrollmentProgramCol !== '') {
                $searchParts[] = "`{$enrollmentProgramCol}` LIKE '{$like}'";
            }

            if ($enrollmentNameCol !== '') {
                $searchParts[] = "`{$enrollmentNameCol}` LIKE '{$like}'";
            }

            if (!empty($searchParts)) {
                $where .= ' AND (' . implode(' OR ', $searchParts) . ')';
            }
        }

        $groupColumn = $enrollmentProgramCol !== '' ? $enrollmentProgramCol : $enrollmentStudentCol;

        if ($groupColumn !== '') {

            $yearExpr = $enrollmentSchoolYearCol !== ''
                ? "`{$enrollmentSchoolYearCol}`"
                : "'Not Specified'";

            $semesterExpr = $enrollmentSemesterCol !== ''
                ? "`{$enrollmentSemesterCol}`"
                : "'Not Specified'";

            $sql = "
                SELECT
                    {$yearExpr} AS school_year,
                    {$semesterExpr} AS semester,
                    `{$groupColumn}` AS program_name,
                    COUNT(*) AS enrollment_count
                FROM `{$enrollmentTable}`
                WHERE {$where}
                GROUP BY {$yearExpr}, {$semesterExpr}, `{$groupColumn}`
                ORDER BY {$yearExpr} DESC, {$semesterExpr}, `{$groupColumn}`
            ";

            $rows = runQueryRows($conn, $sql);
            $reportHeaders = ['School Year', 'Semester', 'Program / Course', 'Enrollment'];
            $totalRows = array_sum(array_map(function($r) {
                return (int)$r['enrollment_count'];
            }, $rows));
        }
    }

    if ($enrollmentTable === '') {
        $reportDescription .= ' The enrollment table was not found in the database.';
    }
}

/* -----------------------------------------------------
   GRADE REPORT
----------------------------------------------------- */
if ($report === 'grades') {

    $reportTitle = 'Grade Report';
    $reportDescription = 'Grade records with student, subject, program, and academic period information.';
    $sourceTable = $gradesTable;

    if ($gradesTable !== '' && $gradeValueCol !== '') {

        $where = '1=1';

        if ($schoolYear !== '' && $gradeSchoolYearCol !== '') {
            $where .= " AND `{$gradeSchoolYearCol}` = '" . $conn->real_escape_string($schoolYear) . "'";
        }

        if ($semester !== '' && $gradeSemesterCol !== '') {
            $where .= " AND `{$gradeSemesterCol}` = '" . $conn->real_escape_string($semester) . "'";
        }

        if ($search !== '') {
            $like = $conn->real_escape_string('%' . $search . '%');
            $searchParts = [];

            foreach ([
                $gradeStudentCol,
                $gradeNameCol,
                $gradeProgramCol,
                $gradeSubjectCol
            ] as $column) {
                if ($column !== '') {
                    $searchParts[] = "`{$column}` LIKE '{$like}'";
                }
            }

            if (!empty($searchParts)) {
                $where .= ' AND (' . implode(' OR ', $searchParts) . ')';
            }
        }

        $studentExpr = $gradeStudentCol !== '' ? "`{$gradeStudentCol}`" : "''";
        $nameExpr = $gradeNameCol !== '' ? "`{$gradeNameCol}`" : "''";
        $programExpr = $gradeProgramCol !== '' ? "`{$gradeProgramCol}`" : "''";
        $subjectExpr = $gradeSubjectCol !== '' ? "`{$gradeSubjectCol}`" : "''";
        $yearExpr = $gradeSchoolYearCol !== '' ? "`{$gradeSchoolYearCol}`" : "''";
        $semesterExpr = $gradeSemesterCol !== '' ? "`{$gradeSemesterCol}`" : "''";

        $sql = "
            SELECT
                {$studentExpr} AS student_number,
                {$nameExpr} AS student_name,
                {$programExpr} AS program_name,
                {$subjectExpr} AS subject,
                `{$gradeValueCol}` AS grade,
                {$yearExpr} AS school_year,
                {$semesterExpr} AS semester
            FROM `{$gradesTable}`
            WHERE {$where}
            ORDER BY student_name ASC, subject ASC
            LIMIT 2000
        ";

        $rows = runQueryRows($conn, $sql);
        $reportHeaders = [
            'Student Number',
            'Student Name',
            'Program / Course',
            'Subject',
            'Grade',
            'School Year',
            'Semester'
        ];
        $totalRows = count($rows);

    } else {
        $reportDescription .= ' The grades table or grade column was not found in the database.';
    }
}

/* -----------------------------------------------------
   GRADUATING STUDENTS REPORT
----------------------------------------------------- */
if ($report === 'graduating') {

    $reportTitle = 'Graduating Students Report';
    $reportDescription = 'Students listed in the graduation records with their graduation status and requirements.';
    $sourceTable = $graduationTable;

    if ($graduationTable !== '') {

        $where = '1=1';

        if ($schoolYear !== '' && $gradYearCol !== '') {
            $where .= " AND `{$gradYearCol}` = '" . $conn->real_escape_string($schoolYear) . "'";
        }

        if ($semester !== '' && $gradTermCol !== '') {
            $where .= " AND `{$gradTermCol}` = '" . $conn->real_escape_string($semester) . "'";
        }

        if ($search !== '') {
            $like = $conn->real_escape_string('%' . $search . '%');
            $searchParts = [];

            foreach ([
                $gradStudentCol,
                $gradNameCol,
                $gradProgramCol,
                $gradStatusCol
            ] as $column) {
                if ($column !== '') {
                    $searchParts[] = "`{$column}` LIKE '{$like}'";
                }
            }

            if (!empty($searchParts)) {
                $where .= ' AND (' . implode(' OR ', $searchParts) . ')';
            }
        }

        $studentExpr = $gradStudentCol !== '' ? "`{$gradStudentCol}`" : "''";
        $nameExpr = $gradNameCol !== '' ? "`{$gradNameCol}`" : "''";
        $programExpr = $gradProgramCol !== '' ? "`{$gradProgramCol}`" : "''";
        $yearExpr = $gradYearCol !== '' ? "`{$gradYearCol}`" : "''";
        $termExpr = $gradTermCol !== '' ? "`{$gradTermCol}`" : "''";
        $statusExpr = $gradStatusCol !== '' ? "`{$gradStatusCol}`" : "''";

        $requirementsExpr = columnExists($conn, $graduationTable, 'requirements_status')
            ? "`requirements_status`"
            : "''";

        $sql = "
            SELECT
                {$studentExpr} AS student_number,
                {$nameExpr} AS student_name,
                {$programExpr} AS program_name,
                {$yearExpr} AS graduation_year,
                {$termExpr} AS graduation_term,
                {$statusExpr} AS status,
                {$requirementsExpr} AS requirements_status
            FROM `{$graduationTable}`
            WHERE {$where}
            ORDER BY graduation_year DESC, student_name ASC
        ";

        $rows = runQueryRows($conn, $sql);
        $reportHeaders = [
            'Student Number',
            'Student Name',
            'Program / Course',
            'Graduation Year',
            'Term / Semester',
            'Status',
            'Requirements'
        ];
        $totalRows = count($rows);

    } else {
        $reportDescription .= ' The graduation records table was not found in the database.';
    }
}

/* -----------------------------------------------------
   DOCUMENT REQUEST REPORT
----------------------------------------------------- */
if ($report === 'documents') {

    $reportTitle = 'Document Request Report';
    $reportDescription = 'Document requests grouped by document type and status.';
    $sourceTable = $documentsTable;

    if ($documentsTable !== '' && $documentTypeCol !== '') {

        $where = '1=1';

        if ($schoolYear !== '' && $documentSchoolYearCol !== '') {
            $where .= " AND `{$documentSchoolYearCol}` = '" . $conn->real_escape_string($schoolYear) . "'";
        }

        if ($semester !== '' && $documentSemesterCol !== '') {
            $where .= " AND `{$documentSemesterCol}` = '" . $conn->real_escape_string($semester) . "'";
        }

        if ($search !== '') {
            $like = $conn->real_escape_string('%' . $search . '%');
            $searchParts = [];

            foreach ([
                $documentTypeCol,
                $documentStatusCol,
                $documentNameCol,
                $documentStudentCol
            ] as $column) {
                if ($column !== '') {
                    $searchParts[] = "`{$column}` LIKE '{$like}'";
                }
            }

            if (!empty($searchParts)) {
                $where .= ' AND (' . implode(' OR ', $searchParts) . ')';
            }
        }

        $statusExpr = $documentStatusCol !== ''
            ? "`{$documentStatusCol}`"
            : "'Not Specified'";

        $sql = "
            SELECT
                `{$documentTypeCol}` AS document_type,
                {$statusExpr} AS status,
                COUNT(*) AS request_count
            FROM `{$documentsTable}`
            WHERE {$where}
            GROUP BY `{$documentTypeCol}`, {$statusExpr}
            ORDER BY request_count DESC, document_type ASC
        ";

        $rows = runQueryRows($conn, $sql);
        $reportHeaders = ['Document Type', 'Status', 'Number of Requests'];
        $totalRows = array_sum(array_map(function($r) {
            return (int)$r['request_count'];
        }, $rows));

    } else {
        $reportDescription .= ' The document requests table or document type column was not found in the database.';
    }
}

/* -----------------------------------------------------
   PROGRAM / COURSE REPORT
----------------------------------------------------- */
if ($report === 'programs') {

    $reportTitle = 'Program / Course Report';
    $reportDescription = 'Programs or courses available in the registrar database.';
    $sourceTable = $programTable;

    if ($programTable !== '' && $programNameCol !== '') {

        $where = '1=1';

        if ($search !== '') {
            $like = $conn->real_escape_string('%' . $search . '%');
            $searchParts = ["`{$programNameCol}` LIKE '{$like}'"];

            if ($programCodeCol !== '') {
                $searchParts[] = "`{$programCodeCol}` LIKE '{$like}'";
            }

            if ($programDepartmentCol !== '') {
                $searchParts[] = "`{$programDepartmentCol}` LIKE '{$like}'";
            }

            $where .= ' AND (' . implode(' OR ', $searchParts) . ')';
        }

        $codeExpr = $programCodeCol !== '' ? "`{$programCodeCol}`" : "''";
        $departmentExpr = $programDepartmentCol !== '' ? "`{$programDepartmentCol}`" : "''";
        $yearLevelExpr = $programYearLevelCol !== '' ? "`{$programYearLevelCol}`" : "''";

        $sql = "
            SELECT
                `{$programNameCol}` AS program_name,
                {$codeExpr} AS program_code,
                {$departmentExpr} AS department,
                {$yearLevelExpr} AS year_levels
            FROM `{$programTable}`
            WHERE {$where}
            ORDER BY program_name ASC
        ";

        $rows = runQueryRows($conn, $sql);
        $reportHeaders = ['Program / Course', 'Program Code', 'Department', 'Year Level / Duration'];
        $totalRows = count($rows);

    } else {
        $reportDescription .= ' The programs table or expected program name column was not found in the database.';
    }
}

/* -----------------------------------------------------
   STUDENT ACADEMIC RECORDS
----------------------------------------------------- */
if ($report === 'academic') {

    $reportTitle = 'Student Academic Records';
    $reportDescription = 'Academic record view combining student information with grade records when available.';
    $sourceTable = $gradesTable;

    if ($gradesTable !== '') {

        $where = '1=1';

        if ($schoolYear !== '' && $gradeSchoolYearCol !== '') {
            $where .= " AND `{$gradeSchoolYearCol}` = '" . $conn->real_escape_string($schoolYear) . "'";
        }

        if ($semester !== '' && $gradeSemesterCol !== '') {
            $where .= " AND `{$gradeSemesterCol}` = '" . $conn->real_escape_string($semester) . "'";
        }

        if ($search !== '') {
            $like = $conn->real_escape_string('%' . $search . '%');
            $searchParts = [];

            foreach ([
                $gradeStudentCol,
                $gradeNameCol,
                $gradeProgramCol,
                $gradeSubjectCol
            ] as $column) {
                if ($column !== '') {
                    $searchParts[] = "`{$column}` LIKE '{$like}'";
                }
            }

            if (!empty($searchParts)) {
                $where .= ' AND (' . implode(' OR ', $searchParts) . ')';
            }
        }

        $studentExpr = $gradeStudentCol !== '' ? "`{$gradeStudentCol}`" : "''";
        $nameExpr = $gradeNameCol !== '' ? "`{$gradeNameCol}`" : "''";
        $programExpr = $gradeProgramCol !== '' ? "`{$gradeProgramCol}`" : "''";
        $subjectExpr = $gradeSubjectCol !== '' ? "`{$gradeSubjectCol}`" : "''";
        $gradeExpr = "`{$gradeValueCol}`";
        $yearExpr = $gradeSchoolYearCol !== '' ? "`{$gradeSchoolYearCol}`" : "''";
        $semesterExpr = $gradeSemesterCol !== '' ? "`{$gradeSemesterCol}`" : "''";

        $sql = "
            SELECT
                {$studentExpr} AS student_number,
                {$nameExpr} AS student_name,
                {$programExpr} AS program_name,
                {$subjectExpr} AS subject,
                {$gradeExpr} AS grade,
                {$yearExpr} AS school_year,
                {$semesterExpr} AS semester
            FROM `{$gradesTable}`
            WHERE {$where}
            ORDER BY student_name ASC, school_year DESC, semester ASC, subject ASC
            LIMIT 3000
        ";

        $rows = runQueryRows($conn, $sql);
        $reportHeaders = [
            'Student Number',
            'Student Name',
            'Program / Course',
            'Subject',
            'Grade',
            'School Year',
            'Semester'
        ];
        $totalRows = count($rows);

    } elseif ($studentsTable !== '') {

        $studentExpr = $studentNumberCol !== '' ? "`{$studentNumberCol}`" : "''";
        $nameExpr = $studentNameCol !== '' ? "`{$studentNameCol}`" : "''";
        $programExpr = $studentProgramCol !== '' ? "`{$studentProgramCol}`" : "''";
        $yearExpr = $studentSchoolYearCol !== '' ? "`{$studentSchoolYearCol}`" : "''";
        $semesterExpr = $studentSemesterCol !== '' ? "`{$studentSemesterCol}`" : "''";

        $sql = "
            SELECT
                {$studentExpr} AS student_number,
                {$nameExpr} AS student_name,
                {$programExpr} AS program_name,
                '' AS subject,
                '' AS grade,
                {$yearExpr} AS school_year,
                {$semesterExpr} AS semester
            FROM `{$studentsTable}`
            ORDER BY student_name ASC
            LIMIT 3000
        ";

        $rows = runQueryRows($conn, $sql);
        $reportHeaders = [
            'Student Number',
            'Student Name',
            'Program / Course',
            'Subject',
            'Grade',
            'School Year',
            'Semester'
        ];
        $totalRows = count($rows);

    } else {
        $reportDescription .= ' No student or grade table was found in the database.';
    }
}

/* =====================================================
   CSV EXPORT
===================================================== */
if (isset($_GET['export']) && $_GET['export'] === 'csv') {

    $csvRows = [];

    foreach ($rows as $row) {

        if ($report === 'population') {
            $percentage = $totalRows > 0
                ? number_format(((int)$row['student_count'] / $totalRows) * 100, 2) . '%'
                : '0.00%';

            $csvRows[] = [
                $row['program_name'],
                $row['year_level'],
                $row['student_count'],
                $percentage
            ];

        } elseif ($report === 'enrollment') {
            $csvRows[] = [
                $row['school_year'],
                $row['semester'],
                $row['program_name'],
                $row['enrollment_count']
            ];

        } elseif ($report === 'grades' || $report === 'academic') {
            $csvRows[] = [
                $row['student_number'],
                $row['student_name'],
                $row['program_name'],
                $row['subject'],
                $row['grade'],
                $row['school_year'],
                $row['semester']
            ];

        } elseif ($report === 'graduating') {
            $csvRows[] = [
                $row['student_number'],
                $row['student_name'],
                $row['program_name'],
                $row['graduation_year'],
                $row['graduation_term'],
                $row['status'],
                $row['requirements_status']
            ];

        } elseif ($report === 'documents') {
            $csvRows[] = [
                $row['document_type'],
                $row['status'],
                $row['request_count']
            ];

        } elseif ($report === 'programs') {
            $csvRows[] = [
                $row['program_name'],
                $row['program_code'],
                $row['department'],
                $row['year_levels']
            ];
        }
    }

    $safeName = preg_replace('/[^A-Za-z0-9_-]+/', '_', strtolower($report));
    csvDownload(
        'norsu_' . $safeName . '_report_' . date('Ymd_His') . '.csv',
        $reportHeaders,
        $csvRows
    );
}

/* =====================================================
   STATISTICS
===================================================== */
$statStudents = $studentsTable !== '' ? safeCount($conn, $studentsTable) : 0;
$statEnrollment = $enrollmentTable !== '' ? safeCount($conn, $enrollmentTable) : 0;
$statGrades = $gradesTable !== '' ? safeCount($conn, $gradesTable) : 0;
$statGraduating = $graduationTable !== '' ? safeCount(
    $conn,
    $graduationTable,
    $gradStatusCol !== ''
        ? "`{$gradStatusCol}` IN ('Graduating','Approved')"
        : '1=1'
) : 0;
$statDocuments = $documentsTable !== '' ? safeCount($conn, $documentsTable) : 0;

/* =====================================================
   URL BUILDER
===================================================== */
function reportUrl($report, $search, $schoolYear, $semester, $export = '') {
    $params = [
        'report' => $report
    ];

    if ($search !== '') {
        $params['search'] = $search;
    }

    if ($schoolYear !== '') {
        $params['school_year'] = $schoolYear;
    }

    if ($semester !== '') {
        $params['semester'] = $semester;
    }

    if ($export !== '') {
        $params['export'] = $export;
    }

    return 'reports.php?' . http_build_query($params);
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">

<title>Reports | NORSU Registrar System</title>

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

.report-card {
    background:#fff;
    border:1px solid var(--border);
    border-radius:9px;
    box-shadow:0 2px 7px rgba(0,0,0,.04);
    padding:20px;
    transition:.2s;
}

.report-card:hover {
    transform:translateY(-2px);
    box-shadow:0 5px 14px rgba(0,0,0,.07);
}

.report-icon {
    width:46px;
    height:46px;
    border-radius:8px;
    display:flex;
    align-items:center;
    justify-content:center;
    background:#eaf2ff;
    color:var(--blue);
    font-size:22px;
    margin-bottom:13px;
}

.report-card h3 {
    color:var(--dark-blue);
    font-size:16px;
    margin-bottom:6px;
}

.report-card p {
    color:var(--muted);
    font-size:12px;
    line-height:1.5;
    min-height:36px;
}

.report-actions {
    display:flex;
    gap:7px;
    flex-wrap:wrap;
    margin-top:15px;
}

.btn-outline {
    background:#fff;
    color:var(--blue);
    border:1px solid var(--blue);
}

.btn-outline:hover {
    background:#eaf2ff;
}

.filter-grid .form-group {
    min-width:0;
}

.table-wrapper {
    overflow-x:auto;
}

.table {
    width:100%;
    border-collapse:collapse;
    min-width:760px;
}

.table th,
.table td {
    padding:11px 12px;
    border-bottom:1px solid var(--border);
    text-align:left;
    font-size:12px;
    vertical-align:top;
}

.table th {
    background:#f7f9fc;
    color:#555;
    font-weight:700;
    white-space:nowrap;
}

.table tr:hover td {
    background:#fbfcfe;
}

.report-title {
    color:var(--dark-blue);
    font-size:18px;
    margin-bottom:4px;
}

.report-subtitle {
    color:var(--muted);
    font-size:12px;
}

.report-meta {
    display:flex;
    gap:18px;
    flex-wrap:wrap;
    margin-top:12px;
    padding:12px 14px;
    background:#f7f9fc;
    border:1px solid var(--border);
    border-radius:7px;
    font-size:12px;
}

.report-meta strong {
    color:#444;
}

.kpi-row {
    display:grid;
    grid-template-columns:repeat(4,1fr);
    gap:12px;
    margin:15px 0 18px;
}

.kpi {
    background:#f8fafc;
    border:1px solid var(--border);
    border-radius:7px;
    padding:13px;
}

.kpi-label {
    font-size:11px;
    color:var(--muted);
    margin-bottom:4px;
}

.kpi-value {
    font-size:22px;
    font-weight:800;
    color:var(--dark-blue);
}

.print-only {
    display:none;
}

@media print {
    .sidebar,
    .topbar,
    .no-print,
    .report-grid,
    .filter-card,
    .page-title,
    .alerts {
        display:none !important;
    }
    .main {
        margin-left:0 !important;
    }
    .content {
        padding:0 !important;
    }
    .card {
        box-shadow:none !important;
        border:0 !important;
    }
    .print-only {
        display:block !important;
    }
    body {
        background:#fff !important;
    }
    .table th {
        background:#eee !important;
        -webkit-print-color-adjust:exact;
        print-color-adjust:exact;
    }
}

@media (max-width:1100px) {
    .report-grid { grid-template-columns:repeat(2,1fr); }
    .filter-grid { grid-template-columns:repeat(2,1fr); }
    .kpi-row { grid-template-columns:repeat(2,1fr); }
}

@media (max-width:700px) {
    .report-grid { grid-template-columns:1fr; }
    .filter-grid { grid-template-columns:1fr; }
    .kpi-row { grid-template-columns:1fr; }
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

        <a href="documents.php">
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

        <a href="reports.php" class="active">
            <span class="icon">📈</span>
            Reports
        </a>

        <div class="menu-title">Administration</div>

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

        <h1>Reports</h1>

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

    <!-- PAGE TITLE -->
    <div class="page-title">
        <h2>📈 Reports</h2>
        <p>
            Generate registrar reports for students, enrollment, grades,
            graduation, documents, programs, and academic records.
        </p>
    </div>

    <!-- =================================================
         STATISTICS
    ================================================== -->
    <div class="stats">

        <div class="stat-card">
            <div class="stat-label">Students</div>
            <div class="stat-number"><?= number_format($statStudents) ?></div>
        </div>

        <div class="stat-card pending">
            <div class="stat-label">Enrollment Records</div>
            <div class="stat-number"><?= number_format($statEnrollment) ?></div>
        </div>

        <div class="stat-card approved">
            <div class="stat-label">Grade Records</div>
            <div class="stat-number"><?= number_format($statGrades) ?></div>
        </div>

        <div class="stat-card released">
            <div class="stat-label">Graduating / Approved</div>
            <div class="stat-number"><?= number_format($statGraduating) ?></div>
        </div>

        <div class="stat-card">
            <div class="stat-label">Document Requests</div>
            <div class="stat-number"><?= number_format($statDocuments) ?></div>
        </div>

    </div>

    <!-- =================================================
         REPORT TYPES
    ================================================== -->
    <div class="card no-print">

        <div class="card-header">
            <div>
                <h3>Available Reports</h3>
                <span style="font-size:12px;color:#777;">
                    Select a report to generate registrar information.
                </span>
            </div>
        </div>

        <div class="card-body">

            <div class="report-grid">

                <div class="report-card">
                    <div class="report-icon">👨‍🎓</div>
                    <h3>Student Population Report</h3>
                    <p>View student population grouped by program and year level.</p>
                </div>

                <div class="report-card">
                    <div class="report-icon">📝</div>
                    <h3>Enrollment Report</h3>
                    <p>View enrollment totals by school year, semester, and program.</p>
                </div>

                <div class="report-card">
                    <div class="report-icon">📊</div>
                    <h3>Grade Report</h3>
                    <p>View grades by student, subject, program, and academic period.</p>
                </div>

                <div class="report-card">
                    <div class="report-icon">🎓</div>
                    <h3>Graduating Students Report</h3>
                    <p>View graduating, approved, rejected, and requirements information.</p>
                </div>

                <div class="report-card">
                    <div class="report-icon">📄</div>
                    <h3>Document Request Report</h3>
                    <p>View document requests grouped by document type and status.</p>
                </div>

                <div class="report-card">
                    <div class="report-icon">📚</div>
                    <h3>Program / Course Report</h3>
                    <p>View programs, course codes, departments, and year levels.</p>
                </div>

                <div class="report-card">
                    <div class="report-icon">📋</div>
                    <h3>Student Academic Records</h3>
                    <p>View academic records using available grade and student data.</p>
                </div>

                <div class="report-card">
                    <div class="report-icon">📅</div>
                    <h3>School Year / Semester Report</h3>
                    <p>Use the filters below to generate period-specific reports.</p>
                </div>

            </div>

        </div>

    </div>

    <!-- =================================================
         FILTERS
    ================================================== -->
    <div class="card filter-card no-print" id="report-filters">

        <div class="card-header">
            <div>
                <h3>Report Filters</h3>
                <span style="font-size:12px;color:#777;">
                    Filter reports by school year, semester, and keyword.
                </span>
            </div>
        </div>

        <div class="card-body">

            <form method="GET">

                <input type="hidden" name="report" value="<?= e($report) ?>">

                <div class="filter-grid">

                    <div class="form-group">

                        <label>Search</label>

                        <input
                            type="text"
                            name="search"
                            class="form-control"
                            value="<?= e($search) ?>"
                            placeholder="Student, program, subject, document..."
                        >

                    </div>

                    <div class="form-group">

                        <label>School Year</label>

                        <select name="school_year" class="form-control">

                            <option value="">All School Years</option>

                            <?php foreach ($schoolYears as $year): ?>

                                <option
                                    value="<?= e($year) ?>"
                                    <?= $schoolYear === (string)$year ? 'selected' : '' ?>
                                >
                                    <?= e($year) ?>
                                </option>

                            <?php endforeach; ?>

                        </select>

                    </div>

                    <div class="form-group">

                        <label>Semester</label>

                        <select name="semester" class="form-control">

                            <option value="">All Semesters</option>

                            <?php foreach ($semesters as $term): ?>

                                <option
                                    value="<?= e($term) ?>"
                                    <?= $semester === $term ? 'selected' : '' ?>
                                >
                                    <?= e($term) ?>
                                </option>

                            <?php endforeach; ?>

                        </select>

                    </div>

                    <div class="form-group">

                        <label>Report Type</label>

                        <select name="report" class="form-control">

                            <option value="population" <?= $report === 'population' ? 'selected' : '' ?>>
                                Student Population
                            </option>

                            <option value="enrollment" <?= $report === 'enrollment' ? 'selected' : '' ?>>
                                Enrollment
                            </option>

                            <option value="grades" <?= $report === 'grades' ? 'selected' : '' ?>>
                                Grade
                            </option>

                            <option value="graduating" <?= $report === 'graduating' ? 'selected' : '' ?>>
                                Graduating Students
                            </option>

                            <option value="documents" <?= $report === 'documents' ? 'selected' : '' ?>>
                                Document Requests
                            </option>

                            <option value="programs" <?= $report === 'programs' ? 'selected' : '' ?>>
                                Program / Course
                            </option>

                            <option value="academic" <?= $report === 'academic' ? 'selected' : '' ?>>
                                Student Academic Records
                            </option>

                        </select>

                    </div>

                    <div class="form-actions" style="margin-top:0;">

                        <button
                            type="submit"
                            class="btn btn-primary"
                        >
                            🔍 Generate
                        </button>

                        <a
                            href="<?= e(reportUrl($report, $search, $schoolYear, $semester, 'csv')) ?>"
                            class="btn btn-success"
                        >
                            ⬇ CSV
                        </a>

                        <button
                            type="button"
                            class="btn btn-outline"
                            onclick="window.print()"
                        >
                            🖨 Print
                        </button>

                    </div>

                </div>

            </form>

        </div>

    </div>

    <!-- =================================================
         GENERATED REPORT
    ================================================== -->
    <div class="card">

        <div class="card-header">

            <div>

                <div class="report-title">
                    <?= e($reportTitle) ?>
                </div>

                <div class="report-subtitle">
                    <?= e($reportDescription) ?>
                </div>

            </div>

            <div class="no-print">

                <a
                    href="<?= e(reportUrl($report, $search, $schoolYear, $semester, 'csv')) ?>"
                    class="btn btn-success"
                >
                    ⬇ Export CSV
                </a>

                <button
                    type="button"
                    class="btn btn-primary"
                    onclick="window.print()"
                >
                    🖨 Print
                </button>

            </div>

        </div>

        <div class="card-body">

            <div class="print-only">

                <h2 style="text-align:center;color:#003b7a;">
                    NEGROS ORIENTAL STATE UNIVERSITY
                </h2>

                <h3 style="text-align:center;margin-top:5px;">
                    OFFICE OF THE REGISTRAR
                </h3>

                <h2 style="text-align:center;margin-top:20px;">
                    <?= e($reportTitle) ?>
                </h2>

            </div>

            <div class="report-meta">

                <div>
                    <strong>Report:</strong>
                    <?= e($reportTitle) ?>
                </div>

                <div>
                    <strong>School Year:</strong>
                    <?= $schoolYear !== '' ? e($schoolYear) : 'All' ?>
                </div>

                <div>
                    <strong>Semester:</strong>
                    <?= $semester !== '' ? e($semester) : 'All' ?>
                </div>

                <div>
                    <strong>Generated:</strong>
                    <?= date('M d, Y h:i A') ?>
                </div>

            </div>

            <div class="kpi-row">

                <div class="kpi">
                    <div class="kpi-label">Report Rows</div>
                    <div class="kpi-value"><?= number_format(count($rows)) ?></div>
                </div>

                <div class="kpi">
                    <div class="kpi-label">
                        <?php if ($report === 'population'): ?>
                            Total Students
                        <?php elseif ($report === 'enrollment'): ?>
                            Total Enrollment
                        <?php elseif ($report === 'documents'): ?>
                            Total Requests
                        <?php else: ?>
                            Records
                        <?php endif; ?>
                    </div>

                    <div class="kpi-value">
                        <?php if (in_array($report, ['population','enrollment','documents'], true)): ?>
                            <?= number_format($totalRows) ?>
                        <?php else: ?>
                            <?= number_format(count($rows)) ?>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="kpi">
                    <div class="kpi-label">Source Table</div>
                    <div class="kpi-value" style="font-size:14px;">
                        <?= $sourceTable !== '' ? e($sourceTable) : 'N/A' ?>
                    </div>
                </div>

                <div class="kpi">
                    <div class="kpi-label">Status</div>
                    <div class="kpi-value" style="font-size:14px;">
                        <?= !empty($rows) ? 'Generated' : 'No Data' ?>
                    </div>
                </div>

            </div>

            <div class="table-wrapper">

                <table class="table">

                    <thead>

                        <tr>

                            <?php foreach ($reportHeaders as $header): ?>

                                <th><?= e($header) ?></th>

                            <?php endforeach; ?>

                        </tr>

                    </thead>

                    <tbody>

                    <?php if (!empty($rows)): ?>

                        <?php foreach ($rows as $row): ?>

                            <tr>

                                <?php if ($report === 'population'): ?>

                                    <?php
                                    $percentage = $totalRows > 0
                                        ? number_format(((int)$row['student_count'] / $totalRows) * 100, 2) . '%'
                                        : '0.00%';
                                    ?>

                                    <td><?= e($row['program_name']) ?></td>
                                    <td><?= e($row['year_level']) ?></td>
                                    <td><strong><?= number_format((int)$row['student_count']) ?></strong></td>
                                    <td><?= e($percentage) ?></td>

                                <?php elseif ($report === 'enrollment'): ?>

                                    <td><?= e($row['school_year']) ?></td>
                                    <td><?= e($row['semester']) ?></td>
                                    <td><?= e($row['program_name']) ?></td>
                                    <td><strong><?= number_format((int)$row['enrollment_count']) ?></strong></td>

                                <?php elseif ($report === 'grades' || $report === 'academic'): ?>

                                    <td><?= e($row['student_number']) ?></td>
                                    <td><strong><?= e($row['student_name']) ?></strong></td>
                                    <td><?= e($row['program_name']) ?></td>
                                    <td><?= e($row['subject']) ?></td>
                                    <td><strong><?= e($row['grade']) ?></strong></td>
                                    <td><?= e($row['school_year']) ?></td>
                                    <td><?= e($row['semester']) ?></td>

                                <?php elseif ($report === 'graduating'): ?>

                                    <td><?= e($row['student_number']) ?></td>
                                    <td><strong><?= e($row['student_name']) ?></strong></td>
                                    <td><?= e($row['program_name']) ?></td>
                                    <td><?= e($row['graduation_year']) ?></td>
                                    <td><?= e($row['graduation_term']) ?></td>
                                    <td><?= e($row['status']) ?></td>
                                    <td><?= e($row['requirements_status']) ?></td>

                                <?php elseif ($report === 'documents'): ?>

                                    <td><?= e($row['document_type']) ?></td>
                                    <td><?= e($row['status']) ?></td>
                                    <td><strong><?= number_format((int)$row['request_count']) ?></strong></td>

                                <?php elseif ($report === 'programs'): ?>

                                    <td><strong><?= e($row['program_name']) ?></strong></td>
                                    <td><?= e($row['program_code']) ?></td>
                                    <td><?= e($row['department']) ?></td>
                                    <td><?= e($row['year_levels']) ?></td>

                                <?php endif; ?>

                            </tr>

                        <?php endforeach; ?>

                    <?php else: ?>

                        <tr>

                            <td
                                colspan="<?= max(1, count($reportHeaders)) ?>"
                                class="empty"
                            >
                                No report data found for the selected filters.
                            </td>

                        </tr>

                    <?php endif; ?>

                    </tbody>

                </table>

            </div>

        </div>

    </div>

</section>

</main>

<script>
function toggleSidebar() {
    const sidebar = document.getElementById('sidebar');

    if (sidebar) {
        sidebar.classList.toggle('show');
    }
}

document.addEventListener('click', function(event) {

    const sidebar = document.getElementById('sidebar');
    const button = document.querySelector('.menu-toggle');

    if (!sidebar || !button) {
        return;
    }

    if (
        window.innerWidth <= 900 &&
        sidebar.classList.contains('show') &&
        !sidebar.contains(event.target) &&
        !button.contains(event.target)
    ) {
        sidebar.classList.remove('show');
    }

});
</script>

</body>
</html>

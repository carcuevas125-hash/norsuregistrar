<?php
session_start();

/*
=========================================================
    NORSU REGISTRAR SYSTEM
    DOCUMENT REQUESTS MANAGEMENT

    File Name:
    document_requests.php

    Database:
    haha

    FEATURES:
    - Transcript of Records (TOR)
    - Certificate of Enrollment
    - Certificate of Grades
    - Good Moral Certificate
    - Transfer Credentials
    - Form 137
    - Other Certifications
    - Pending Requests
    - Approved Requests
    - Released Documents
    - Add Request
    - Approve Request
    - Release Document
    - Reject Request
    - View Request Details
    - Search and Filter
    - Request History
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
// CREATE DOCUMENT REQUESTS TABLE
// =====================================================

$createRequestsTable = "
CREATE TABLE IF NOT EXISTS document_requests (
    id INT AUTO_INCREMENT PRIMARY KEY,
    request_number VARCHAR(50) NOT NULL UNIQUE,
    student_id VARCHAR(100) NOT NULL,
    student_name VARCHAR(255) NOT NULL,
    student_number VARCHAR(100) DEFAULT '',
    document_type VARCHAR(100) NOT NULL,
    purpose VARCHAR(500) DEFAULT '',
    quantity INT NOT NULL DEFAULT 1,
    request_date DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    status ENUM('Pending','Approved','Released','Rejected') NOT NULL DEFAULT 'Pending',
    approved_date DATETIME NULL,
    released_date DATETIME NULL,
    released_by VARCHAR(255) DEFAULT '',
    remarks TEXT,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_student_id (student_id),
    INDEX idx_student_number (student_number),
    INDEX idx_document_type (document_type),
    INDEX idx_status (status),
    INDEX idx_request_date (request_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
";

$conn->query($createRequestsTable);

// Add 2x2 photo attachment column to existing installations.
// This is safe to run repeatedly because the column is checked first.
$photoColumnCheck = $conn->query("SHOW COLUMNS FROM document_requests LIKE 'photo_attachment'");
if ($photoColumnCheck && $photoColumnCheck->num_rows === 0) {
    $conn->query("ALTER TABLE document_requests ADD COLUMN photo_attachment VARCHAR(255) DEFAULT '' AFTER student_number");
}


// =====================================================
// CREATE DOCUMENT REQUEST HISTORY TABLE
// =====================================================

$createHistoryTable = "
CREATE TABLE IF NOT EXISTS document_request_history (
    id INT AUTO_INCREMENT PRIMARY KEY,
    request_id INT NOT NULL,
    request_number VARCHAR(50) NOT NULL,
    action VARCHAR(100) NOT NULL,
    old_status VARCHAR(50) DEFAULT '',
    new_status VARCHAR(50) DEFAULT '',
    remarks TEXT,
    action_by VARCHAR(255) DEFAULT '',
    action_date DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_request_id (request_id),
    INDEX idx_request_number (request_number),
    INDEX idx_action_date (action_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
";

$conn->query($createHistoryTable);


// =====================================================
// HELPER FUNCTIONS
// =====================================================

function e($value) {
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function redirectPage($extra = '') {
    $url = basename($_SERVER['PHP_SELF']);
    if ($extra !== '') {
        $url .= '?' . $extra;
    }
    header("Location: " . $url);
    exit;
}

function generateRequestNumber($conn) {
    do {
        $number = 'DOC-' . date('Ymd') . '-' . strtoupper(substr(bin2hex(random_bytes(4)), 0, 8));

        $stmt = $conn->prepare("SELECT id FROM document_requests WHERE request_number = ? LIMIT 1");
        if (!$stmt) {
            return 'DOC-' . date('YmdHis');
        }

        $stmt->bind_param("s", $number);
        $stmt->execute();
        $exists = $stmt->get_result()->num_rows > 0;
        $stmt->close();
    } while ($exists);

    return $number;
}

function addHistory($conn, $requestId, $requestNumber, $action, $oldStatus, $newStatus, $remarks = '') {
    $actionBy = $_SESSION['admin_username'] ?? $_SESSION['username'] ?? 'Administrator';

    $stmt = $conn->prepare("
        INSERT INTO document_request_history
        (request_id, request_number, action, old_status, new_status, remarks, action_by)
        VALUES (?, ?, ?, ?, ?, ?, ?)
    ");

    if ($stmt) {
        $stmt->bind_param(
            "issssss",
            $requestId,
            $requestNumber,
            $action,
            $oldStatus,
            $newStatus,
            $remarks,
            $actionBy
        );
        $stmt->execute();
        $stmt->close();
    }
}

function getRequestById($conn, $id) {
    $stmt = $conn->prepare("SELECT * FROM document_requests WHERE id = ? LIMIT 1");
    if (!$stmt) {
        return null;
    }

    $stmt->bind_param("i", $id);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result ? $result->fetch_assoc() : null;
    $stmt->close();

    return $row;
}


// =====================================================
// MESSAGE VARIABLES
// =====================================================

$success = '';
$error = '';


// =====================================================
// 2X2 PHOTO UPLOAD HELPER
// =====================================================

function uploadTwoByTwoPhoto($file, &$errorMessage) {
    $errorMessage = '';

    if (!isset($file) || !isset($file['error']) || $file['error'] === UPLOAD_ERR_NO_FILE) {
        return '';
    }

    if ($file['error'] !== UPLOAD_ERR_OK) {
        $errorMessage = 'Unable to upload the 2x2 picture.';
        return '';
    }

    if ((int)$file['size'] > 2 * 1024 * 1024) {
        $errorMessage = 'The 2x2 picture must not exceed 2 MB.';
        return '';
    }

    $imageInfo = @getimagesize($file['tmp_name']);
    if ($imageInfo === false) {
        $errorMessage = 'Please upload a valid JPG, JPEG, or PNG image.';
        return '';
    }

    $mime = $imageInfo['mime'] ?? '';
    $allowed = [
        'image/jpeg' => 'jpg',
        'image/png'  => 'png'
    ];

    if (!isset($allowed[$mime])) {
        $errorMessage = 'Only JPG, JPEG, or PNG pictures are allowed.';
        return '';
    }

    $width  = (int)$imageInfo[0];
    $height = (int)$imageInfo[1];

    // A digital 2x2 photo should be square. We allow a small tolerance
    // so normal camera/scanned photos can still be accepted.
    if ($width < 200 || $height < 200) {
        $errorMessage = 'The 2x2 picture is too small. Please upload a clearer image.';
        return '';
    }

    $ratio = $width / max($height, 1);
    if ($ratio < 0.85 || $ratio > 1.15) {
        $errorMessage = 'Please upload a square 2x2 picture.';
        return '';
    }

    $uploadDir = __DIR__ . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . 'tor_photos';
    if (!is_dir($uploadDir) && !mkdir($uploadDir, 0755, true)) {
        $errorMessage = 'Unable to create the photo upload folder.';
        return '';
    }

    $fileName = 'TOR_' . date('YmdHis') . '_' . bin2hex(random_bytes(5)) . '.' . $allowed[$mime];
    $destination = $uploadDir . DIRECTORY_SEPARATOR . $fileName;

    if (!move_uploaded_file($file['tmp_name'], $destination)) {
        $errorMessage = 'Unable to save the 2x2 picture.';
        return '';
    }

    return 'uploads/tor_photos/' . $fileName;
}


// =====================================================
// ADD DOCUMENT REQUEST
// =====================================================

if (isset($_POST['add_request'])) {

    // Student ID is no longer required here. Student Number + Student Name
    // identify the requester, while TOR can have a 2x2 picture attachment.
    $studentId     = '';
    $studentName   = trim($_POST['student_name'] ?? '');
    $studentNumber = trim($_POST['student_number'] ?? '');
    $documentType  = trim($_POST['document_type'] ?? '');
    $purpose       = trim($_POST['purpose'] ?? '');
    $quantity      = (int)($_POST['quantity'] ?? 1);
    $remarks       = trim($_POST['remarks'] ?? '');
    $photoPath     = '';
    $photoError    = '';

    if ($studentName === '' || $documentType === '') {
        $error = "Please complete all required fields.";
    } elseif ($quantity < 1) {
        $error = "Quantity must be at least 1.";
    } else {

        $photoPath = uploadTwoByTwoPhoto($_FILES['photo_attachment'] ?? null, $photoError);

        if ($photoError !== '') {
            $error = $photoError;
        } elseif ($documentType === 'Transcript of Records (TOR)' && $photoPath === '') {
            $error = "Please attach a 2x2 picture for the Transcript of Records (TOR).";
        } else {

            $requestNumber = generateRequestNumber($conn);

            $stmt = $conn->prepare("
                INSERT INTO document_requests
                (request_number, student_id, student_name, student_number, photo_attachment, document_type,
                 purpose, quantity, status, remarks)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'Pending', ?)
            ");

            if ($stmt) {

                $stmt->bind_param(
                    "sssssssis",
                    $requestNumber,
                    $studentId,
                    $studentName,
                    $studentNumber,
                    $photoPath,
                    $documentType,
                    $purpose,
                    $quantity,
                    $remarks
                );

                if ($stmt->execute()) {

                    $requestId = $stmt->insert_id;

                    addHistory(
                        $conn,
                        $requestId,
                        $requestNumber,
                        'Request Created',
                        '',
                        'Pending',
                        'Document request was created.'
                    );

                    $stmt->close();

                    redirectPage("view_request=" . $requestId . "&created=1");

                } else {
                    $error = "Unable to create document request.";
                    $stmt->close();
                }

            } else {
                $error = "Unable to prepare document request.";
            }
        }
    }
}


// =====================================================
// APPROVE REQUEST
// =====================================================

if (isset($_POST['approve_request'])) {

    $id = (int)($_POST['request_id'] ?? 0);
    $remarks = trim($_POST['approval_remarks'] ?? '');

    $request = getRequestById($conn, $id);

    if (!$request) {
        $error = "Document request not found.";
    } elseif ($request['status'] !== 'Pending') {
        $error = "Only pending requests can be approved.";
    } else {

        $stmt = $conn->prepare("
            UPDATE document_requests
            SET status = 'Approved',
                approved_date = NOW(),
                remarks = ?
            WHERE id = ?
        ");

        if ($stmt) {

            $stmt->bind_param("si", $remarks, $id);

            if ($stmt->execute()) {

                addHistory(
                    $conn,
                    $id,
                    $request['request_number'],
                    'Request Approved',
                    'Pending',
                    'Approved',
                    $remarks
                );

                $stmt->close();

                redirectPage("view_request=" . $id . "&approved=1");

            } else {
                $error = "Unable to approve request.";
                $stmt->close();
            }

        } else {
            $error = "Unable to prepare approval.";
        }
    }
}


// =====================================================
// RELEASE DOCUMENT
// =====================================================

if (isset($_POST['release_request'])) {

    $id = (int)($_POST['request_id'] ?? 0);
    $releaseRemarks = trim($_POST['release_remarks'] ?? '');

    $request = getRequestById($conn, $id);

    if (!$request) {
        $error = "Document request not found.";
    } elseif ($request['status'] !== 'Approved') {
        $error = "Only approved requests can be released.";
    } else {

        $releasedBy = $_SESSION['admin_username'] ?? $_SESSION['username'] ?? 'Administrator';

        $stmt = $conn->prepare("
            UPDATE document_requests
            SET status = 'Released',
                released_date = NOW(),
                released_by = ?,
                remarks = ?
            WHERE id = ?
        ");

        if ($stmt) {

            $stmt->bind_param("ssi", $releasedBy, $releaseRemarks, $id);

            if ($stmt->execute()) {

                addHistory(
                    $conn,
                    $id,
                    $request['request_number'],
                    'Document Released',
                    'Approved',
                    'Released',
                    $releaseRemarks
                );

                $stmt->close();

                redirectPage("view_request=" . $id . "&released=1");

            } else {
                $error = "Unable to release document.";
                $stmt->close();
            }

        } else {
            $error = "Unable to prepare release.";
        }
    }
}


// =====================================================
// REJECT REQUEST
// =====================================================

if (isset($_POST['reject_request'])) {

    $id = (int)($_POST['request_id'] ?? 0);
    $rejectRemarks = trim($_POST['reject_remarks'] ?? '');

    $request = getRequestById($conn, $id);

    if (!$request) {
        $error = "Document request not found.";
    } elseif (!in_array($request['status'], ['Pending', 'Approved'], true)) {
        $error = "This request cannot be rejected.";
    } elseif ($rejectRemarks === '') {
        $error = "Please provide a reason for rejection.";
    } else {

        $oldStatus = $request['status'];

        $stmt = $conn->prepare("
            UPDATE document_requests
            SET status = 'Rejected',
                remarks = ?
            WHERE id = ?
        ");

        if ($stmt) {

            $stmt->bind_param("si", $rejectRemarks, $id);

            if ($stmt->execute()) {

                addHistory(
                    $conn,
                    $id,
                    $request['request_number'],
                    'Request Rejected',
                    $oldStatus,
                    'Rejected',
                    $rejectRemarks
                );

                $stmt->close();

                redirectPage("view_request=" . $id . "&rejected=1");

            } else {
                $error = "Unable to reject request.";
                $stmt->close();
            }

        } else {
            $error = "Unable to prepare rejection.";
        }
    }
}


// =====================================================
// UPDATE REQUEST
// =====================================================

if (isset($_POST['update_request'])) {

    $id            = (int)($_POST['request_id'] ?? 0);
    $studentId     = '';
    $studentName   = trim($_POST['student_name'] ?? '');
    $studentNumber = trim($_POST['student_number'] ?? '');
    $documentType  = trim($_POST['document_type'] ?? '');
    $purpose       = trim($_POST['purpose'] ?? '');
    $quantity      = (int)($_POST['quantity'] ?? 1);
    $remarks       = trim($_POST['remarks'] ?? '');
    $photoError    = '';

    $request = getRequestById($conn, $id);
    $photoPath = $request['photo_attachment'] ?? '';

    if (!$request) {
        $error = "Document request not found.";
    } elseif ($studentName === '' || $documentType === '') {
        $error = "Please complete all required fields.";
    } elseif ($quantity < 1) {
        $error = "Quantity must be at least 1.";
    } elseif ($request['status'] === 'Released') {
        $error = "Released requests cannot be edited.";
    } else {

        $newPhotoPath = uploadTwoByTwoPhoto($_FILES['photo_attachment'] ?? null, $photoError);

        if ($photoError !== '') {
            $error = $photoError;
        } elseif ($documentType === 'Transcript of Records (TOR)' && $photoPath === '' && $newPhotoPath === '') {
            $error = "Please attach a 2x2 picture for the Transcript of Records (TOR).";
        } else {

        if ($newPhotoPath !== '') {
            $photoPath = $newPhotoPath;
        }

        $stmt = $conn->prepare("
            UPDATE document_requests
            SET student_id = ?,
                student_name = ?,
                student_number = ?,
                photo_attachment = ?,
                document_type = ?,
                purpose = ?,
                quantity = ?,
                remarks = ?
            WHERE id = ?
        ");

        if ($stmt) {

            $stmt->bind_param(
                "ssssssisi",
                $studentId,
                $studentName,
                $studentNumber,
                $photoPath,
                $documentType,
                $purpose,
                $quantity,
                $remarks,
                $id
            );

            if ($stmt->execute()) {

                addHistory(
                    $conn,
                    $id,
                    $request['request_number'],
                    'Request Updated',
                    $request['status'],
                    $request['status'],
                    'Document request information was updated.'
                );

                $stmt->close();

                redirectPage("view_request=" . $id . "&updated=1");

            } else {
                $error = "Unable to update request.";
                $stmt->close();
            }

        } else {
            $error = "Unable to prepare update.";
        }

        }
    }
}


// =====================================================
// DELETE REQUEST
// =====================================================

if (isset($_GET['delete_request'])) {

    $id = (int)$_GET['delete_request'];

    $request = getRequestById($conn, $id);

    if (!$request) {
        $error = "Document request not found.";
    } elseif ($request['status'] === 'Released') {
        $error = "Released documents should not be deleted.";
    } else {

        $stmt = $conn->prepare("DELETE FROM document_requests WHERE id = ?");

        if ($stmt) {

            $stmt->bind_param("i", $id);

            if ($stmt->execute()) {
                $stmt->close();
                redirectPage("deleted=1");
            } else {
                $error = "Unable to delete request.";
                $stmt->close();
            }

        } else {
            $error = "Unable to prepare delete.";
        }
    }
}


// =====================================================
// URL MESSAGES
// =====================================================

if (isset($_GET['created'])) {
    $success = "Document request created successfully.";
}

if (isset($_GET['approved'])) {
    $success = "Document request approved successfully.";
}

if (isset($_GET['released'])) {
    $success = "Document released successfully.";
}

if (isset($_GET['rejected'])) {
    $success = "Document request rejected.";
}

if (isset($_GET['updated'])) {
    $success = "Document request updated successfully.";
}

if (isset($_GET['deleted'])) {
    $success = "Document request deleted successfully.";
}


// =====================================================
// FILTERS
// =====================================================

$search = trim($_GET['search'] ?? '');
$statusFilter = trim($_GET['status'] ?? '');
$typeFilter = trim($_GET['document_type'] ?? '');

$allowedStatuses = ['Pending', 'Approved', 'Released', 'Rejected'];

$documentTypes = [
    'Transcript of Records (TOR)',
    'Certificate of Enrollment',
    'Certificate of Grades',
    'Good Moral Certificate',
    'Transfer Credentials',
    'Form 137',
    'Other Certifications'
];


// =====================================================
// VIEW SINGLE REQUEST
// =====================================================

$viewRequest = null;
$viewHistory = [];

if (isset($_GET['view_request'])) {

    $viewId = (int)$_GET['view_request'];

    $viewRequest = getRequestById($conn, $viewId);

    if ($viewRequest) {

        $stmt = $conn->prepare("
            SELECT *
            FROM document_request_history
            WHERE request_id = ?
            ORDER BY action_date DESC, id DESC
        ");

        if ($stmt) {
            $stmt->bind_param("i", $viewId);
            $stmt->execute();
            $result = $stmt->get_result();

            if ($result) {
                while ($row = $result->fetch_assoc()) {
                    $viewHistory[] = $row;
                }
            }

            $stmt->close();
        }
    }
}


// =====================================================
// EDIT REQUEST
// =====================================================

$editRequest = null;

if (isset($_GET['edit_request'])) {

    $editId = (int)$_GET['edit_request'];

    $editRequest = getRequestById($conn, $editId);

    if ($editRequest && $editRequest['status'] === 'Released') {
        $error = "Released requests cannot be edited.";
        $editRequest = null;
    }
}


// =====================================================
// COUNTS
// =====================================================

$counts = [
    'all' => 0,
    'Pending' => 0,
    'Approved' => 0,
    'Released' => 0,
    'Rejected' => 0
];

$countResult = $conn->query("
    SELECT status, COUNT(*) AS total
    FROM document_requests
    GROUP BY status
");

if ($countResult) {
    while ($row = $countResult->fetch_assoc()) {
        $status = $row['status'];
        $total = (int)$row['total'];

        if (isset($counts[$status])) {
            $counts[$status] = $total;
        }

        $counts['all'] += $total;
    }
}


// =====================================================
// DOCUMENT TYPE COUNTS
// =====================================================

$typeCounts = [];

$typeResult = $conn->query("
    SELECT document_type, COUNT(*) AS total
    FROM document_requests
    GROUP BY document_type
    ORDER BY total DESC
");

if ($typeResult) {
    while ($row = $typeResult->fetch_assoc()) {
        $typeCounts[$row['document_type']] = (int)$row['total'];
    }
}


// =====================================================
// REQUEST LIST
// =====================================================

$requests = [];

$sql = "
    SELECT *
    FROM document_requests
    WHERE 1=1
";

$params = [];
$types = '';

if ($search !== '') {
    $sql .= "
        AND (
            request_number LIKE ?
            OR student_name LIKE ?
            OR student_number LIKE ?
            OR purpose LIKE ?
        )
    ";

    $searchLike = '%' . $search . '%';

    $params[] = $searchLike;
    $params[] = $searchLike;
    $params[] = $searchLike;
    $params[] = $searchLike;
    $params[] = $searchLike;

    $types .= 'sssss';
}

if ($statusFilter !== '' && in_array($statusFilter, $allowedStatuses, true)) {
    $sql .= " AND status = ?";
    $params[] = $statusFilter;
    $types .= 's';
}

if ($typeFilter !== '' && in_array($typeFilter, $documentTypes, true)) {
    $sql .= " AND document_type = ?";
    $params[] = $typeFilter;
    $types .= 's';
}

$sql .= " ORDER BY request_date DESC, id DESC";

$stmt = $conn->prepare($sql);

if ($stmt) {

    if (!empty($params)) {
        $stmt->bind_param($types, ...$params);
    }

    $stmt->execute();
    $result = $stmt->get_result();

    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $requests[] = $row;
        }
    }

    $stmt->close();
}


// =====================================================
// CURRENT ADMIN
// =====================================================

$adminName = $_SESSION['admin_username'] ?? $_SESSION['username'] ?? 'Administrator';


// =====================================================
// HTML
// =====================================================

?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">

<title>Document Requests | NORSU Registrar System</title>

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

        <a href="document_requests.php" class="active">
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

        <h1>Document Requests</h1>

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
                    <div class="stat-label">All Requests</div>
                    <div class="stat-number"><?= $counts['all'] ?></div>
                </div>

                <div class="stat-card pending">
                    <div class="stat-label">Pending Requests</div>
                    <div class="stat-number"><?= $counts['Pending'] ?></div>
                </div>

                <div class="stat-card approved">
                    <div class="stat-label">Approved Requests</div>
                    <div class="stat-number"><?= $counts['Approved'] ?></div>
                </div>

                <div class="stat-card released">
                    <div class="stat-label">Released Documents</div>
                    <div class="stat-number"><?= $counts['Released'] ?></div>
                </div>

                <div class="stat-card rejected">
                    <div class="stat-label">Rejected Requests</div>
                    <div class="stat-number"><?= $counts['Rejected'] ?></div>
                </div>

            </div>


            <!-- =================================================
                 DOCUMENT TYPES
            ================================================== -->

            <div class="card">

                <div class="card-header">
                    <h2>Document Types</h2>
                </div>

                <div class="card-body">

                    <div class="type-grid">

                        <?php foreach ($documentTypes as $type): ?>

                            <div class="type-card">

                                <strong>
                                    <?= e($type) ?>
                                </strong>

                                <span>
                                    <?= (int)($typeCounts[$type] ?? 0) ?>
                                </span>

                            </div>

                        <?php endforeach; ?>

                    </div>

                </div>

            </div>


            <!-- =================================================
                 ADD / EDIT REQUEST
            ================================================== -->

            <?php if ($editRequest): ?>

                <div class="card">

                    <div class="card-header">

                        <h2>✏️ Edit Document Request</h2>

                        <a
                            href="document_requests.php"
                            class="btn btn-gray btn-sm"
                        >
                            Cancel
                        </a>

                    </div>

                    <div class="card-body">

                        <form method="POST" enctype="multipart/form-data">

                            <input
                                type="hidden"
                                name="request_id"
                                value="<?= (int)$editRequest['id'] ?>"
                            >

                            <div class="form-grid">

                                <div class="form-group" id="editPhotoGroup" style="display:<?= $editRequest['document_type'] === 'Transcript of Records (TOR)' ? 'block' : 'none' ?>;">

                                    <label>2x2 Picture Attachment</label>

                                    <input
                                        type="file"
                                        name="photo_attachment"
                                        class="form-control"
                                        accept="image/jpeg,image/png"
                                        id="editPhotoAttachment"
                                        <?= ($editRequest['document_type'] === 'Transcript of Records (TOR)' && empty($editRequest['photo_attachment'])) ? 'required' : '' ?>
                                    >

                                    <small class="form-help">
                                        Required for TOR. JPG/JPEG/PNG, square photo, maximum 2 MB.
                                    </small>

                                    <?php if (!empty($editRequest['photo_attachment'])): ?>
                                        <div style="margin-top:10px;">
                                            <img
                                                src="<?= e($editRequest['photo_attachment']) ?>"
                                                alt="2x2 Picture"
                                                class="photo-preview"
                                            >
                                            <div class="photo-current">Current 2x2 picture</div>
                                        </div>
                                    <?php endif; ?>

                                </div>


                                <div class="form-group">

                                    <label>Student Number</label>

                                    <input
                                        type="text"
                                        name="student_number"
                                        class="form-control"
                                        value="<?= e($editRequest['student_number']) ?>"
                                    >

                                </div>


                                <div class="form-group">

                                    <label>Student Name *</label>

                                    <input
                                        type="text"
                                        name="student_name"
                                        class="form-control"
                                        value="<?= e($editRequest['student_name']) ?>"
                                        required
                                    >

                                </div>


                                <div class="form-group">

                                    <label>Document Type *</label>

                                    <select
                                        name="document_type"
                                        class="form-control"
                                        required
                                    >

                                        <option value="">
                                            -- Select Document --
                                        </option>

                                        <?php foreach ($documentTypes as $type): ?>

                                            <option
                                                value="<?= e($type) ?>"
                                                <?= $editRequest['document_type'] === $type ? 'selected' : '' ?>
                                            >
                                                <?= e($type) ?>
                                            </option>

                                        <?php endforeach; ?>

                                    </select>

                                </div>


                                <div class="form-group">

                                    <label>Quantity *</label>

                                    <input
                                        type="number"
                                        name="quantity"
                                        class="form-control"
                                        min="1"
                                        value="<?= (int)$editRequest['quantity'] ?>"
                                        required
                                    >

                                </div>


                                <div class="form-group">

                                    <label>Current Status</label>

                                    <input
                                        type="text"
                                        class="form-control"
                                        value="<?= e($editRequest['status']) ?>"
                                        readonly
                                    >

                                </div>


                                <div class="form-group full">

                                    <label>Purpose</label>

                                    <textarea
                                        name="purpose"
                                        class="form-control"
                                    ><?= e($editRequest['purpose']) ?></textarea>

                                </div>


                                <div class="form-group full">

                                    <label>Remarks</label>

                                    <textarea
                                        name="remarks"
                                        class="form-control"
                                    ><?= e($editRequest['remarks']) ?></textarea>

                                </div>

                            </div>


                            <div class="form-buttons">

                                <button
                                    type="submit"
                                    name="update_request"
                                    class="btn btn-primary"
                                >
                                    💾 Update Request
                                </button>

                                <a
                                    href="document_requests.php"
                                    class="btn btn-gray"
                                >
                                    Cancel
                                </a>

                            </div>

                        </form>

                    </div>

                </div>

            <?php else: ?>

                <div class="card">

                    <div class="card-header">
                        <h2>➕ Encode Document Request</h2>
                    </div>

                    <div class="card-body">

                        <form method="POST" enctype="multipart/form-data">

                            <div class="form-grid">

                                <div class="form-group" id="photoGroup" style="display:none;">

                                    <label>2x2 Picture Attachment</label>

                                    <input
                                        type="file"
                                        name="photo_attachment"
                                        class="form-control"
                                        accept="image/jpeg,image/png"
                                        id="photoAttachment"
                                    >

                                    <small class="form-help">
                                        Required when Document Type is TOR. Square JPG/JPEG/PNG, maximum 2 MB.
                                    </small>

                                    <div id="photoPreviewWrap" style="display:none;margin-top:10px;">
                                        <img id="photoPreview" src="#" alt="2x2 Picture Preview" class="photo-preview">
                                    </div>

                                </div>


                                <div class="form-group">

                                    <label>Student Number</label>

                                    <input
                                        type="text"
                                        name="student_number"
                                        class="form-control"
                                        placeholder="Enter student number"
                                    >

                                </div>


                                <div class="form-group">

                                    <label>Student Name *</label>

                                    <input
                                        type="text"
                                        name="student_name"
                                        class="form-control"
                                        placeholder="Enter complete student name"
                                        required
                                    >

                                </div>


                                <div class="form-group">

                                    <label>Document Type *</label>

                                    <select
                                        name="document_type"
                                        class="form-control"
                                        required
                                    >

                                        <option value="">
                                            -- Select Document --
                                        </option>

                                        <?php foreach ($documentTypes as $type): ?>

                                            <option value="<?= e($type) ?>">
                                                <?= e($type) ?>
                                            </option>

                                        <?php endforeach; ?>

                                    </select>

                                </div>


                                <div class="form-group">

                                    <label>Quantity *</label>

                                    <input
                                        type="number"
                                        name="quantity"
                                        class="form-control"
                                        min="1"
                                        value="1"
                                        required
                                    >

                                </div>


                                <div class="form-group full">

                                    <label>Purpose</label>

                                    <textarea
                                        name="purpose"
                                        class="form-control"
                                        placeholder="Reason for requesting the document"
                                    ></textarea>

                                </div>


                                <div class="form-group full">

                                    <label>Remarks</label>

                                    <textarea
                                        name="remarks"
                                        class="form-control"
                                        placeholder="Optional remarks"
                                    ></textarea>

                                </div>

                            </div>


                            <div class="form-buttons">

                                <button
                                    type="submit"
                                    name="add_request"
                                    class="btn btn-primary"
                                >
                                    + Submit Document Request
                                </button>

                            </div>

                        </form>

                    </div>

                </div>

            <?php endif; ?>


            <!-- =================================================
                 FILTERS
            ================================================== -->

            <div class="card">

                <div class="card-header">
                    <h2>🔎 Search and Filter Requests</h2>
                </div>

                <div class="card-body">

                    <form method="GET">

                        <div class="filters">

                            <div class="form-group">

                                <label>Search</label>

                                <input
                                    type="text"
                                    name="search"
                                    class="form-control"
                                    value="<?= e($search) ?>"
                                    placeholder="Request no., student ID, name, number..."
                                >

                            </div>


                            <div class="form-group">

                                <label>Status</label>

                                <select
                                    name="status"
                                    class="form-control"
                                >

                                    <option value="">All Status</option>

                                    <?php foreach ($allowedStatuses as $status): ?>

                                        <option
                                            value="<?= e($status) ?>"
                                            <?= $statusFilter === $status ? 'selected' : '' ?>
                                        >
                                            <?= e($status) ?>
                                        </option>

                                    <?php endforeach; ?>

                                </select>

                            </div>


                            <div class="form-group">

                                <label>Document Type</label>

                                <select
                                    name="document_type"
                                    class="form-control"
                                >

                                    <option value="">All Documents</option>

                                    <?php foreach ($documentTypes as $type): ?>

                                        <option
                                            value="<?= e($type) ?>"
                                            <?= $typeFilter === $type ? 'selected' : '' ?>
                                        >
                                            <?= e($type) ?>
                                        </option>

                                    <?php endforeach; ?>

                                </select>

                            </div>


                            <div class="filter-buttons">

                                <button
                                    type="submit"
                                    class="btn btn-primary"
                                >
                                    Search
                                </button>

                                <a
                                    href="document_requests.php"
                                    class="btn btn-gray"
                                >
                                    Reset
                                </a>

                            </div>

                        </div>

                    </form>

                </div>

            </div>


            <!-- =================================================
                 REQUEST TABLE
            ================================================== -->

            <div class="card">

                <div class="card-header">

                    <h2>
                        📄 Document Requests
                        <span class="muted">
                            (<?= count($requests) ?> found)
                        </span>
                    </h2>

                </div>

                <div class="card-body" style="padding:0;">

                    <?php if (empty($requests)): ?>

                        <div class="empty">
                            No document requests found.
                        </div>

                    <?php else: ?>

                        <div class="table-wrap">

                            <table>

                                <thead>

                                    <tr>

                                        <th>Request No.</th>

                                        <th>Student</th>

                                        <th>Document</th>

                                        <th>Purpose</th>

                                        <th>Qty.</th>

                                        <th>Date Requested</th>

                                        <th>Status</th>

                                        <th>Actions</th>

                                    </tr>

                                </thead>

                                <tbody>

                                <?php foreach ($requests as $request): ?>

                                    <tr>

                                        <td>
                                            <div class="request-number">
                                                <?= e($request['request_number']) ?>
                                            </div>

                                            <?php if ($request['student_number'] !== ''): ?>
                                                <div class="muted">
                                                    No. <?= e($request['student_number']) ?>
                                                </div>
                                            <?php endif; ?>
                                        </td>


                                        <td>

                                            <div class="student-name">
                                                <?= e($request['student_name']) ?>
                                            </div>

                                            <div class="muted">
                                                No.: <?= e($request['student_number']) ?>
                                            </div>

                                        </td>


                                        <td>

                                            <div class="document-type">
                                                <?= e($request['document_type']) ?>
                                            </div>

                                        </td>


                                        <td>

                                            <?php if ($request['purpose'] !== ''): ?>

                                                <?= e($request['purpose']) ?>

                                            <?php else: ?>

                                                <span class="muted">
                                                    No purpose specified
                                                </span>

                                            <?php endif; ?>

                                        </td>


                                        <td>
                                            <?= (int)$request['quantity'] ?>
                                        </td>


                                        <td>

                                            <?= e(date(
                                                'M d, Y',
                                                strtotime($request['request_date'])
                                            )) ?>

                                            <div class="muted">
                                                <?= e(date(
                                                    'h:i A',
                                                    strtotime($request['request_date'])
                                                )) ?>
                                            </div>

                                        </td>


                                        <td>

                                            <?php
                                            $badgeClass = 'badge-pending';

                                            if ($request['status'] === 'Approved') {
                                                $badgeClass = 'badge-approved';
                                            } elseif ($request['status'] === 'Released') {
                                                $badgeClass = 'badge-released';
                                            } elseif ($request['status'] === 'Rejected') {
                                                $badgeClass = 'badge-rejected';
                                            }
                                            ?>

                                            <span class="badge <?= $badgeClass ?>">
                                                <?= e($request['status']) ?>
                                            </span>

                                        </td>


                                        <td>

                                            <div class="actions">

                                                <a
                                                    href="document_requests.php?view_request=<?= (int)$request['id'] ?>"
                                                    class="btn btn-blue btn-sm"
                                                >
                                                    👁 View
                                                </a>


                                                <?php if ($request['status'] !== 'Released'): ?>

                                                    <a
                                                        href="document_requests.php?edit_request=<?= (int)$request['id'] ?>"
                                                        class="btn btn-light btn-sm"
                                                    >
                                                        ✏ Edit
                                                    </a>

                                                <?php endif; ?>


                                                <?php if ($request['status'] === 'Pending'): ?>

                                                    <form method="POST" style="display:inline;">

                                                        <input
                                                            type="hidden"
                                                            name="request_id"
                                                            value="<?= (int)$request['id'] ?>"
                                                        >

                                                        <button
                                                            type="submit"
                                                            name="approve_request"
                                                            class="btn btn-success btn-sm"
                                                            onclick="return confirm('Approve this document request?');"
                                                        >
                                                            ✓ Approve
                                                        </button>

                                                    </form>

                                                <?php endif; ?>


                                                <?php if ($request['status'] === 'Approved'): ?>

                                                    <form method="POST" style="display:inline;">

                                                        <input
                                                            type="hidden"
                                                            name="request_id"
                                                            value="<?= (int)$request['id'] ?>"
                                                        >

                                                        <button
                                                            type="submit"
                                                            name="release_request"
                                                            class="btn btn-primary btn-sm"
                                                            onclick="return confirm('Mark this document as released?');"
                                                        >
                                                            📤 Release
                                                        </button>

                                                    </form>

                                                <?php endif; ?>


                                                <?php if (
                                                    $request['status'] === 'Pending' ||
                                                    $request['status'] === 'Approved'
                                                ): ?>

                                                    <form method="POST" style="display:inline;">

                                                        <input
                                                            type="hidden"
                                                            name="request_id"
                                                            value="<?= (int)$request['id'] ?>"
                                                        >

                                                        <input
                                                            type="hidden"
                                                            name="reject_remarks"
                                                            value="Rejected by administrator."
                                                        >

                                                        <button
                                                            type="submit"
                                                            name="reject_request"
                                                            class="btn btn-danger btn-sm"
                                                            onclick="return confirm('Reject this request?');"
                                                        >
                                                            ✕ Reject
                                                        </button>

                                                    </form>

                                                <?php endif; ?>


                                                <?php if ($request['status'] !== 'Released'): ?>

                                                    <a
                                                        href="document_requests.php?delete_request=<?= (int)$request['id'] ?>"
                                                        class="btn btn-light btn-sm"
                                                        onclick="return confirm('Delete this document request?');"
                                                    >
                                                        🗑 Delete
                                                    </a>

                                                <?php endif; ?>

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


            <!-- =================================================
                 REQUEST DETAILS
            ================================================== -->

            <?php if ($viewRequest): ?>

                <div class="card" id="requestDetails">

                    <div class="card-header">

                        <h2>
                            📋 Request Details
                        </h2>

                        <a
                            href="document_requests.php"
                            class="btn btn-gray btn-sm"
                        >
                            Close
                        </a>

                    </div>


                    <div class="card-body">

                        <div class="detail-grid">

                            <div class="detail-item">

                                <div class="detail-label">
                                    Request Number
                                </div>

                                <div class="detail-value">
                                    <?= e($viewRequest['request_number']) ?>
                                </div>

                            </div>


                            <div class="detail-item">

                                <div class="detail-label">
                                    Status
                                </div>

                                <div class="detail-value">

                                    <?php
                                    $detailBadge = 'badge-pending';

                                    if ($viewRequest['status'] === 'Approved') {
                                        $detailBadge = 'badge-approved';
                                    } elseif ($viewRequest['status'] === 'Released') {
                                        $detailBadge = 'badge-released';
                                    } elseif ($viewRequest['status'] === 'Rejected') {
                                        $detailBadge = 'badge-rejected';
                                    }
                                    ?>

                                    <span class="badge <?= $detailBadge ?>">
                                        <?= e($viewRequest['status']) ?>
                                    </span>

                                </div>

                            </div>


                            <div class="detail-item">

                                <div class="detail-label">
                                    2x2 Picture Attachment
                                </div>

                                <div class="detail-value">
                                    <?php if (!empty($viewRequest['photo_attachment'])): ?>
                                        <img
                                            src="<?= e($viewRequest['photo_attachment']) ?>"
                                            alt="Student 2x2 Picture"
                                            class="photo-preview detail-photo"
                                        >
                                    <?php else: ?>
                                        <span style="color:#777;">No 2x2 picture attached.</span>
                                    <?php endif; ?>
                                </div>

                            </div>


                            <div class="detail-item">

                                <div class="detail-label">
                                    Student Number
                                </div>

                                <div class="detail-value">
                                    <?= $viewRequest['student_number'] !== ''
                                        ? e($viewRequest['student_number'])
                                        : '—' ?>
                                </div>

                            </div>


                            <div class="detail-item">

                                <div class="detail-label">
                                    Student Name
                                </div>

                                <div class="detail-value">
                                    <?= e($viewRequest['student_name']) ?>
                                </div>

                            </div>


                            <div class="detail-item">

                                <div class="detail-label">
                                    Document Type
                                </div>

                                <div class="detail-value">
                                    <?= e($viewRequest['document_type']) ?>
                                </div>

                            </div>


                            <div class="detail-item">

                                <div class="detail-label">
                                    Quantity
                                </div>

                                <div class="detail-value">
                                    <?= (int)$viewRequest['quantity'] ?>
                                </div>

                            </div>


                            <div class="detail-item">

                                <div class="detail-label">
                                    Date Requested
                                </div>

                                <div class="detail-value">
                                    <?= e(date(
                                        'F d, Y h:i A',
                                        strtotime($viewRequest['request_date'])
                                    )) ?>
                                </div>

                            </div>


                            <div class="detail-item full">

                                <div class="detail-label">
                                    Purpose
                                </div>

                                <div class="detail-value">
                                    <?= $viewRequest['purpose'] !== ''
                                        ? nl2br(e($viewRequest['purpose']))
                                        : 'No purpose specified.' ?>
                                </div>

                            </div>


                            <div class="detail-item full">

                                <div class="detail-label">
                                    Remarks
                                </div>

                                <div class="detail-value">
                                    <?= $viewRequest['remarks'] !== ''
                                        ? nl2br(e($viewRequest['remarks']))
                                        : 'No remarks.' ?>
                                </div>

                            </div>


                            <?php if ($viewRequest['approved_date']): ?>

                                <div class="detail-item">

                                    <div class="detail-label">
                                        Approved Date
                                    </div>

                                    <div class="detail-value">
                                        <?= e(date(
                                            'F d, Y h:i A',
                                            strtotime($viewRequest['approved_date'])
                                        )) ?>
                                    </div>

                                </div>

                            <?php endif; ?>


                            <?php if ($viewRequest['released_date']): ?>

                                <div class="detail-item">

                                    <div class="detail-label">
                                        Released Date
                                    </div>

                                    <div class="detail-value">
                                        <?= e(date(
                                            'F d, Y h:i A',
                                            strtotime($viewRequest['released_date'])
                                        )) ?>
                                    </div>

                                </div>


                                <div class="detail-item">

                                    <div class="detail-label">
                                        Released By
                                    </div>

                                    <div class="detail-value">
                                        <?= e($viewRequest['released_by']) ?>
                                    </div>

                                </div>

                            <?php endif; ?>

                        </div>


                        <!-- =================================================
                             ACTION FORMS
                        ================================================== -->

                        <div style="margin-top:25px;">

                            <h3 class="section-title">
                                Request Actions
                            </h3>


                            <div class="actions">

                                <?php if ($viewRequest['status'] === 'Pending'): ?>

                                    <button
                                        type="button"
                                        class="btn btn-success"
                                        onclick="openModal('approveModal')"
                                    >
                                        ✓ Approve Request
                                    </button>

                                <?php endif; ?>


                                <?php if ($viewRequest['status'] === 'Approved'): ?>

                                    <button
                                        type="button"
                                        class="btn btn-primary"
                                        onclick="openModal('releaseModal')"
                                    >
                                        📤 Release Document
                                    </button>

                                <?php endif; ?>


                                <?php if (
                                    $viewRequest['status'] === 'Pending' ||
                                    $viewRequest['status'] === 'Approved'
                                ): ?>

                                    <button
                                        type="button"
                                        class="btn btn-danger"
                                        onclick="openModal('rejectModal')"
                                    >
                                        ✕ Reject Request
                                    </button>

                                <?php endif; ?>


                                <?php if ($viewRequest['status'] !== 'Released'): ?>

                                    <a
                                        href="document_requests.php?edit_request=<?= (int)$viewRequest['id'] ?>"
                                        class="btn btn-light"
                                    >
                                        ✏️ Edit Request
                                    </a>

                                <?php endif; ?>

                            </div>

                        </div>


                        <!-- =================================================
                             HISTORY
                        ================================================== -->

                        <div style="margin-top:30px;">

                            <h3 class="section-title">
                                🕒 Request History
                            </h3>

                            <?php if (empty($viewHistory)): ?>

                                <div class="empty">
                                    No history found.
                                </div>

                            <?php else: ?>

                                <div class="table-wrap">

                                    <table class="history-table">

                                        <thead>

                                            <tr>
                                                <th>Date</th>
                                                <th>Action</th>
                                                <th>Old Status</th>
                                                <th>New Status</th>
                                                <th>Remarks</th>
                                                <th>Action By</th>
                                            </tr>

                                        </thead>

                                        <tbody>

                                        <?php foreach ($viewHistory as $history): ?>

                                            <tr>

                                                <td>
                                                    <?= e(date(
                                                        'M d, Y h:i A',
                                                        strtotime($history['action_date'])
                                                    )) ?>
                                                </td>

                                                <td>
                                                    <strong>
                                                        <?= e($history['action']) ?>
                                                    </strong>
                                                </td>

                                                <td>
                                                    <?= $history['old_status'] !== ''
                                                        ? e($history['old_status'])
                                                        : '—' ?>
                                                </td>

                                                <td>
                                                    <?= $history['new_status'] !== ''
                                                        ? e($history['new_status'])
                                                        : '—' ?>
                                                </td>

                                                <td>
                                                    <?= $history['remarks'] !== ''
                                                        ? nl2br(e($history['remarks']))
                                                        : '—' ?>
                                                </td>

                                                <td>
                                                    <?= e($history['action_by']) ?>
                                                </td>

                                            </tr>

                                        <?php endforeach; ?>

                                        </tbody>

                                    </table>

                                </div>

                            <?php endif; ?>

                        </div>

                    </div>

                </div>


                <!-- =================================================
                     APPROVE MODAL
                ================================================== -->

                <?php if ($viewRequest['status'] === 'Pending'): ?>

                    <div
                        class="modal-overlay"
                        id="approveModal"
                        style="display:none;"
                    >

                        <div class="modal">

                            <div class="modal-header">

                                <h3>Approve Request</h3>

                                <a
                                    href="javascript:void(0)"
                                    class="close"
                                    onclick="closeModal('approveModal')"
                                >
                                    ×
                                </a>

                            </div>

                            <div class="modal-body">

                                <p style="margin-bottom:15px;">
                                    Approve
                                    <strong>
                                        <?= e($viewRequest['document_type']) ?>
                                    </strong>
                                    for
                                    <strong>
                                        <?= e($viewRequest['student_name']) ?>
                                    </strong>?
                                </p>

                                <form method="POST">

                                    <input
                                        type="hidden"
                                        name="request_id"
                                        value="<?= (int)$viewRequest['id'] ?>"
                                    >

                                    <div class="form-group">

                                        <label>Approval Remarks</label>

                                        <textarea
                                            name="approval_remarks"
                                            class="form-control"
                                            placeholder="Optional approval remarks"
                                        ></textarea>

                                    </div>

                                    <div class="form-buttons">

                                        <button
                                            type="submit"
                                            name="approve_request"
                                            class="btn btn-success"
                                        >
                                            ✓ Approve
                                        </button>

                                        <button
                                            type="button"
                                            class="btn btn-gray"
                                            onclick="closeModal('approveModal')"
                                        >
                                            Cancel
                                        </button>

                                    </div>

                                </form>

                            </div>

                        </div>

                    </div>

                <?php endif; ?>


                <!-- =================================================
                     RELEASE MODAL
                ================================================== -->

                <?php if ($viewRequest['status'] === 'Approved'): ?>

                    <div
                        class="modal-overlay"
                        id="releaseModal"
                        style="display:none;"
                    >

                        <div class="modal">

                            <div class="modal-header">

                                <h3>Release Document</h3>

                                <a
                                    href="javascript:void(0)"
                                    class="close"
                                    onclick="closeModal('releaseModal')"
                                >
                                    ×
                                </a>

                            </div>

                            <div class="modal-body">

                                <p style="margin-bottom:15px;">
                                    Mark this document request as
                                    <strong>Released</strong>?
                                </p>

                                <form method="POST">

                                    <input
                                        type="hidden"
                                        name="request_id"
                                        value="<?= (int)$viewRequest['id'] ?>"
                                    >

                                    <div class="form-group">

                                        <label>Release Remarks</label>

                                        <textarea
                                            name="release_remarks"
                                            class="form-control"
                                            placeholder="Optional release remarks"
                                        ></textarea>

                                    </div>

                                    <div class="form-buttons">

                                        <button
                                            type="submit"
                                            name="release_request"
                                            class="btn btn-primary"
                                        >
                                            📤 Release Document
                                        </button>

                                        <button
                                            type="button"
                                            class="btn btn-gray"
                                            onclick="closeModal('releaseModal')"
                                        >
                                            Cancel
                                        </button>

                                    </div>

                                </form>

                            </div>

                        </div>

                    </div>

                <?php endif; ?>


                <!-- =================================================
                     REJECT MODAL
                ================================================== -->

                <?php if (
                    $viewRequest['status'] === 'Pending' ||
                    $viewRequest['status'] === 'Approved'
                ): ?>

                    <div
                        class="modal-overlay"
                        id="rejectModal"
                        style="display:none;"
                    >

                        <div class="modal">

                            <div class="modal-header">

                                <h3>Reject Request</h3>

                                <a
                                    href="javascript:void(0)"
                                    class="close"
                                    onclick="closeModal('rejectModal')"
                                >
                                    ×
                                </a>

                            </div>

                            <div class="modal-body">

                                <form method="POST">

                                    <input
                                        type="hidden"
                                        name="request_id"
                                        value="<?= (int)$viewRequest['id'] ?>"
                                    >

                                    <div class="form-group">

                                        <label>Reason for Rejection *</label>

                                        <textarea
                                            name="reject_remarks"
                                            class="form-control"
                                            placeholder="Enter the reason for rejecting this request..."
                                            required
                                        ></textarea>

                                    </div>

                                    <div class="form-buttons">

                                        <button
                                            type="submit"
                                            name="reject_request"
                                            class="btn btn-danger"
                                        >
                                            ✕ Reject Request
                                        </button>

                                        <button
                                            type="button"
                                            class="btn btn-gray"
                                            onclick="closeModal('rejectModal')"
                                        >
                                            Cancel
                                        </button>

                                    </div>

                                </form>

                            </div>

                        </div>

                    </div>

                <?php endif; ?>

            <?php endif; ?>


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


<script>
(function () {
    const torText = 'Transcript of Records (TOR)';

    document.querySelectorAll('form select[name="document_type"]').forEach(function (typeSelect) {
        const form = typeSelect.closest('form');
        if (!form) return;

        const photoInput = form.querySelector('input[name="photo_attachment"]');
        if (!photoInput) return;

        const photoGroup = photoInput.closest('.form-group');
        const previewWrap = form.querySelector('#photoPreviewWrap');
        const preview = form.querySelector('#photoPreview');
        const hasCurrentPhoto = form.querySelector('.photo-current') !== null;

        function updatePhotoRequirement() {
            const isTor = typeSelect.value === torText;

            if (photoGroup) {
                photoGroup.style.display = isTor ? '' : 'none';
            }

            // For a new request, TOR requires the photo.
            // For editing, an existing photo means a replacement is optional.
            if (isTor) {
                photoInput.required = !hasCurrentPhoto;
            } else {
                photoInput.required = false;
            }

            const help = photoGroup ? photoGroup.querySelector('.form-help') : null;
            if (help) {
                help.textContent = isTor
                    ? 'Required for TOR. Square JPG/JPEG/PNG, maximum 2 MB.'
                    : 'The 2x2 picture attachment is used only for TOR.';
            }
        }

        typeSelect.addEventListener('change', updatePhotoRequirement);
        updatePhotoRequirement();

        photoInput.addEventListener('change', function () {
            const file = this.files && this.files[0];
            if (!file) return;

            if (file.type !== 'image/jpeg' && file.type !== 'image/png') {
                alert('Please select a JPG, JPEG, or PNG image.');
                this.value = '';
                return;
            }

            if (file.size > 2 * 1024 * 1024) {
                alert('The 2x2 picture must not exceed 2 MB.');
                this.value = '';
                return;
            }

            if (preview && previewWrap) {
                preview.src = URL.createObjectURL(file);
                previewWrap.style.display = 'block';
            }
        });
    });
})();
</script>
</body>

</html>

<?php
$conn->close();
?>

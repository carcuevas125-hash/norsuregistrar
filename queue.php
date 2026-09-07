<?php
session_start();

/*
=========================================================
    NORSU REGISTRAR SYSTEM
    QUEUE MANAGEMENT

    File Name:
    queue_management.php

    Database:
    haha

    FEATURES:
    - Current Queue
    - Waiting
    - Processing
    - Completed
    - Called / Served Students
    - Add Student to Queue
    - Call Next Student
    - Start Processing
    - Complete Service
    - Recall Student
    - Cancel Queue
    - Search and Filter
    - Automatic queue number
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
   CREATE QUEUE TABLE
===================================================== */
$createTable = "
CREATE TABLE IF NOT EXISTS registrar_queue (
    id INT AUTO_INCREMENT PRIMARY KEY,
    queue_number VARCHAR(30) NOT NULL UNIQUE,
    student_number VARCHAR(100) DEFAULT '',
    student_name VARCHAR(255) NOT NULL,
    service VARCHAR(150) NOT NULL,
    purpose VARCHAR(500) DEFAULT '',
    status ENUM('Waiting','Called','Processing','Completed','Cancelled')
        NOT NULL DEFAULT 'Waiting',
    counter VARCHAR(100) DEFAULT '',
    called_at DATETIME NULL,
    processing_at DATETIME NULL,
    completed_at DATETIME NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
        ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_queue_number (queue_number),
    INDEX idx_student_number (student_number),
    INDEX idx_status (status),
    INDEX idx_created_at (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
";

$conn->query($createTable);

/* =====================================================
   MONITOR LAYOUT SETTINGS TABLE
   These settings are saved in the database so the
   extension / TV monitor layout can be edited from PHP.
===================================================== */
$conn->query("
    CREATE TABLE IF NOT EXISTS registrar_monitor_layout (
        id TINYINT UNSIGNED NOT NULL PRIMARY KEY,
        background_color VARCHAR(20) NOT NULL DEFAULT '#f4f6f9',
        header_background VARCHAR(20) NOT NULL DEFAULT '#ffffff',
        panel_background VARCHAR(20) NOT NULL DEFAULT '#ffffff',
        queue_color VARCHAR(20) NOT NULL DEFAULT '#0057b8',
        text_color VARCHAR(20) NOT NULL DEFAULT '#003b7a',
        panel_width TINYINT UNSIGNED NOT NULL DEFAULT 96,
        panel_height TINYINT UNSIGNED NOT NULL DEFAULT 78,
        panel_radius TINYINT UNSIGNED NOT NULL DEFAULT 28,
        queue_size TINYINT UNSIGNED NOT NULL DEFAULT 15,
        student_size TINYINT UNSIGNED NOT NULL DEFAULT 42,
        show_header TINYINT(1) NOT NULL DEFAULT 1,
        show_footer TINYINT(1) NOT NULL DEFAULT 1,
        office_name VARCHAR(150) NOT NULL DEFAULT 'NORSU Registrar Office',
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
");

$conn->query("
    INSERT IGNORE INTO registrar_monitor_layout
    (id, background_color, header_background, panel_background, queue_color,
     text_color, panel_width, panel_height, panel_radius, queue_size,
     student_size, show_header, show_footer, office_name)
    VALUES
    (1, '#f4f6f9', '#ffffff', '#ffffff', '#0057b8',
     '#003b7a', 96, 78, 28, 15, 42, 1, 1, 'NORSU Registrar Office')
");

function getMonitorLayout($conn) {
    $defaults = [
        'background_color'   => '#f4f6f9',
        'header_background'  => '#ffffff',
        'panel_background'   => '#ffffff',
        'queue_color'        => '#0057b8',
        'text_color'         => '#003b7a',
        'panel_width'        => 96,
        'panel_height'       => 78,
        'panel_radius'       => 28,
        'queue_size'         => 15,
        'student_size'       => 42,
        'show_header'        => 1,
        'show_footer'        => 1,
        'office_name'        => 'NORSU Registrar Office'
    ];

    $result = $conn->query("SELECT * FROM registrar_monitor_layout WHERE id = 1 LIMIT 1");

    if ($result && ($row = $result->fetch_assoc())) {
        return array_merge($defaults, $row);
    }

    return $defaults;
}

function validMonitorColor($value, $fallback) {
    $value = trim((string)$value);

    if (preg_match('/^#[0-9a-fA-F]{6}$/', $value)) {
        return strtolower($value);
    }

    return $fallback;
}

$monitorLayout = getMonitorLayout($conn);


/* =====================================================
   HELPER FUNCTIONS
===================================================== */
function e($value) {
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function redirectPage($message = '') {
    $url = basename($_SERVER['PHP_SELF']);
    if ($message !== '') {
        $url .= '?' . $message;
    }
    header("Location: " . $url);
    exit;
}

function generateQueueNumber($conn) {
    $prefix = 'Q' . date('Ymd') . '-';

    $result = $conn->query("
        SELECT queue_number
        FROM registrar_queue
        WHERE queue_number LIKE '" . $conn->real_escape_string($prefix) . "%'
        ORDER BY id DESC
        LIMIT 1
    ");

    $next = 1;

    if ($result && $result->num_rows > 0) {
        $last = $result->fetch_assoc()['queue_number'];
        $number = (int)substr($last, -4);
        $next = $number + 1;
    }

    return $prefix . str_pad($next, 4, '0', STR_PAD_LEFT);
}

function getQueueById($conn, $id) {
    $stmt = $conn->prepare("SELECT * FROM registrar_queue WHERE id = ?");
    if (!$stmt) return null;

    $stmt->bind_param("i", $id);
    $stmt->execute();

    $result = $stmt->get_result();
    $row = $result ? $result->fetch_assoc() : null;

    $stmt->close();
    return $row;
}

function statusClass($status) {
    switch ($status) {
        case 'Waiting': return 'waiting';
        case 'Called': return 'called';
        case 'Processing': return 'processing';
        case 'Completed': return 'completed';
        case 'Cancelled': return 'cancelled';
        default: return '';
    }
}


/* =====================================================
   MONITOR LAYOUT EDITOR
   Open:
   queue_management.php?monitor_editor=1

   Saves layout preferences into MySQL so the settings
   remain available on the extension / TV monitor.
===================================================== */
if (isset($_GET['monitor_editor']) && $_GET['monitor_editor'] === '1') {

    $editorMessage = '';
    $editorError = '';

    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_monitor_layout'])) {

        $backgroundColor  = validMonitorColor($_POST['background_color'] ?? '', '#f4f6f9');
        $headerBackground = validMonitorColor($_POST['header_background'] ?? '', '#ffffff');
        $panelBackground  = validMonitorColor($_POST['panel_background'] ?? '', '#ffffff');
        $queueColor       = validMonitorColor($_POST['queue_color'] ?? '', '#0057b8');
        $textColor        = validMonitorColor($_POST['text_color'] ?? '', '#003b7a');

        $panelWidth  = max(70, min(100, (int)($_POST['panel_width'] ?? 96)));
        $panelHeight = max(60, min(90, (int)($_POST['panel_height'] ?? 78)));
        $panelRadius = max(0, min(60, (int)($_POST['panel_radius'] ?? 28)));
        $queueSize   = max(8, min(20, (int)($_POST['queue_size'] ?? 15)));
        $studentSize = max(20, min(80, (int)($_POST['student_size'] ?? 42)));

        $showHeader = isset($_POST['show_header']) ? 1 : 0;
        $showFooter = isset($_POST['show_footer']) ? 1 : 0;

        $officeName = trim($_POST['office_name'] ?? 'NORSU Registrar Office');
        if ($officeName === '') {
            $officeName = 'NORSU Registrar Office';
        }
        $officeName = mb_substr($officeName, 0, 150);

        $stmt = $conn->prepare("
            UPDATE registrar_monitor_layout
            SET background_color = ?,
                header_background = ?,
                panel_background = ?,
                queue_color = ?,
                text_color = ?,
                panel_width = ?,
                panel_height = ?,
                panel_radius = ?,
                queue_size = ?,
                student_size = ?,
                show_header = ?,
                show_footer = ?,
                office_name = ?
            WHERE id = 1
        ");

        if ($stmt) {
            $stmt->bind_param(
                "sssssiiiiiiis",
                $backgroundColor,
                $headerBackground,
                $panelBackground,
                $queueColor,
                $textColor,
                $panelWidth,
                $panelHeight,
                $panelRadius,
                $queueSize,
                $studentSize,
                $showHeader,
                $showFooter,
                $officeName
            );

            if ($stmt->execute()) {
                $editorMessage = 'Monitor layout saved successfully.';
            } else {
                $editorError = 'Unable to save the monitor layout.';
            }

            $stmt->close();
        } else {
            $editorError = 'Unable to prepare the monitor layout settings.';
        }

        $monitorLayout = getMonitorLayout($conn);
    }

    if (isset($_POST['reset_monitor_layout'])) {

        $stmt = $conn->prepare("
            UPDATE registrar_monitor_layout
            SET background_color = '#f4f6f9',
                header_background = '#ffffff',
                panel_background = '#ffffff',
                queue_color = '#0057b8',
                text_color = '#003b7a',
                panel_width = 96,
                panel_height = 78,
                panel_radius = 28,
                queue_size = 15,
                student_size = 42,
                show_header = 1,
                show_footer = 1,
                office_name = 'NORSU Registrar Office'
            WHERE id = 1
        ");

        if ($stmt && $stmt->execute()) {
            $editorMessage = 'Monitor layout has been reset to default.';
            $stmt->close();
        } else {
            $editorError = 'Unable to reset the monitor layout.';
            if ($stmt) {
                $stmt->close();
            }
        }

        $monitorLayout = getMonitorLayout($conn);
    }
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Monitor Layout Editor | NORSU Registrar System</title>

<style>
:root {
    --blue: #0057b8;
    --dark-blue: #003b7a;
    --red: #d71920;
    --yellow: #ffc107;
    --gray: #f4f6f9;
    --white: #ffffff;
    --dark: #222222;
    --border: #dfe5eb;
}

* {
    box-sizing: border-box;
    margin: 0;
    padding: 0;
}

body {
    font-family: Arial, Helvetica, sans-serif;
    background: var(--gray);
    color: var(--dark);
    padding: 25px;
}

.editor {
    width: min(900px, 100%);
    margin: 0 auto;
    background: white;
    border-radius: 18px;
    overflow: hidden;
    box-shadow: 0 15px 45px rgba(0,0,0,.12);
}

.editor-header {
    background: var(--blue);
    color: white;
    padding: 25px;
    border-bottom: 5px solid var(--yellow);
}

.editor-header h1 {
    font-size: 26px;
    margin-bottom: 5px;
}

.editor-header p {
    font-size: 13px;
    opacity: .9;
}

.editor-body {
    padding: 25px;
}

.notice {
    padding: 12px 15px;
    border-radius: 8px;
    margin-bottom: 18px;
    font-weight: 700;
    font-size: 14px;
}

.success {
    background: #dff5e4;
    color: #237a36;
}

.error {
    background: #ffe0e0;
    color: #a90000;
}

.section {
    margin-bottom: 25px;
}

.section h2 {
    color: var(--dark-blue);
    font-size: 17px;
    padding-bottom: 8px;
    margin-bottom: 15px;
    border-bottom: 2px solid var(--border);
}

.grid {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 15px;
}

.field {
    display: flex;
    flex-direction: column;
    gap: 7px;
}

.field.full {
    grid-column: 1 / -1;
}

.field label {
    font-size: 13px;
    font-weight: 800;
}

.field input[type="text"],
.field input[type="number"] {
    width: 100%;
    padding: 11px 12px;
    border: 1px solid #cbd5df;
    border-radius: 8px;
    font-size: 14px;
}

.color-row {
    display: flex;
    gap: 8px;
}

.color-row input[type="color"] {
    width: 50px;
    height: 42px;
    padding: 2px;
    border: 1px solid #cbd5df;
    border-radius: 8px;
    cursor: pointer;
}

.color-row input[type="text"] {
    flex: 1;
}

.range-value {
    font-size: 12px;
    color: #666;
    font-weight: 700;
}

.field input[type="range"] {
    width: 100%;
}

.checks {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 10px;
}

.check {
    display: flex;
    align-items: center;
    gap: 8px;
    padding: 12px;
    background: #f7f9fb;
    border: 1px solid var(--border);
    border-radius: 8px;
    font-size: 13px;
    font-weight: 700;
}

.actions {
    display: flex;
    justify-content: space-between;
    gap: 10px;
    flex-wrap: wrap;
    margin-top: 10px;
}

button,
.button-link {
    border: 0;
    border-radius: 8px;
    padding: 12px 18px;
    font-weight: 800;
    cursor: pointer;
    text-decoration: none;
    display: inline-block;
}

.save {
    background: var(--blue);
    color: white;
}

.reset {
    background: #eeeeee;
    color: #222;
}

.preview {
    background: var(--red);
    color: white;
}

.help {
    margin-top: 20px;
    padding: 15px;
    background: #eaf4ff;
    border-left: 5px solid var(--blue);
    border-radius: 8px;
    font-size: 13px;
    line-height: 1.5;
}

@media (max-width: 650px) {
    body {
        padding: 10px;
    }

    .editor-body {
        padding: 17px;
    }

    .grid,
    .checks {
        grid-template-columns: 1fr;
    }

    .field.full {
        grid-column: auto;
    }
}
</style>
</head>

<body>

<div class="editor">

    <div class="editor-header">
        <h1>⚙ Monitor Layout Editor</h1>
        <p>NORSU Registrar Queue Monitor — Extension / TV / Projector Layout</p>
    </div>

    <div class="editor-body">

        <?php if ($editorMessage !== ''): ?>
            <div class="notice success"><?= e($editorMessage) ?></div>
        <?php endif; ?>

        <?php if ($editorError !== ''): ?>
            <div class="notice error"><?= e($editorError) ?></div>
        <?php endif; ?>

        <form method="POST">

            <div class="section">
                <h2>🎨 Colors</h2>

                <div class="grid">

                    <?php
                    $colorFields = [
                        'background_color' => ['Monitor Background', $monitorLayout['background_color']],
                        'header_background' => ['Header Background', $monitorLayout['header_background']],
                        'panel_background' => ['Queue Panel Background', $monitorLayout['panel_background']],
                        'queue_color' => ['Queue Number Color', $monitorLayout['queue_color']],
                        'text_color' => ['Student / Text Color', $monitorLayout['text_color']]
                    ];
                    ?>

                    <?php foreach ($colorFields as $name => $field): ?>
                        <div class="field">
                            <label><?= e($field[0]) ?></label>

                            <div class="color-row">
                                <input
                                    type="color"
                                    value="<?= e($field[1]) ?>"
                                    oninput="document.getElementById('<?= e($name) ?>').value=this.value"
                                >

                                <input
                                    type="text"
                                    id="<?= e($name) ?>"
                                    name="<?= e($name) ?>"
                                    value="<?= e($field[1]) ?>"
                                    maxlength="7"
                                >
                            </div>
                        </div>
                    <?php endforeach; ?>

                </div>
            </div>

            <div class="section">
                <h2>📐 Size and Spacing</h2>

                <div class="grid">

                    <div class="field">
                        <label for="panel_width">Queue Panel Width</label>
                        <input
                            type="range"
                            id="panel_width"
                            name="panel_width"
                            min="70"
                            max="100"
                            value="<?= (int)$monitorLayout['panel_width'] ?>"
                            oninput="document.getElementById('panel_width_value').textContent=this.value+'%'"
                        >
                        <span class="range-value" id="panel_width_value">
                            <?= (int)$monitorLayout['panel_width'] ?>%
                        </span>
                    </div>

                    <div class="field">
                        <label for="panel_height">Queue Panel Height</label>
                        <input
                            type="range"
                            id="panel_height"
                            name="panel_height"
                            min="60"
                            max="90"
                            value="<?= (int)$monitorLayout['panel_height'] ?>"
                            oninput="document.getElementById('panel_height_value').textContent=this.value+'vh'"
                        >
                        <span class="range-value" id="panel_height_value">
                            <?= (int)$monitorLayout['panel_height'] ?>vh
                        </span>
                    </div>

                    <div class="field">
                        <label for="panel_radius">Panel Corner Radius</label>
                        <input
                            type="range"
                            id="panel_radius"
                            name="panel_radius"
                            min="0"
                            max="60"
                            value="<?= (int)$monitorLayout['panel_radius'] ?>"
                            oninput="document.getElementById('panel_radius_value').textContent=this.value+'px'"
                        >
                        <span class="range-value" id="panel_radius_value">
                            <?= (int)$monitorLayout['panel_radius'] ?>px
                        </span>
                    </div>

                    <div class="field">
                        <label for="queue_size">Queue Number Size</label>
                        <input
                            type="range"
                            id="queue_size"
                            name="queue_size"
                            min="8"
                            max="20"
                            value="<?= (int)$monitorLayout['queue_size'] ?>"
                            oninput="document.getElementById('queue_size_value').textContent=this.value+'vw'"
                        >
                        <span class="range-value" id="queue_size_value">
                            <?= (int)$monitorLayout['queue_size'] ?>vw
                        </span>
                    </div>

                    <div class="field">
                        <label for="student_size">Student Name Size</label>
                        <input
                            type="range"
                            id="student_size"
                            name="student_size"
                            min="20"
                            max="80"
                            value="<?= (int)$monitorLayout['student_size'] ?>"
                            oninput="document.getElementById('student_size_value').textContent=this.value+'px'"
                        >
                        <span class="range-value" id="student_size_value">
                            <?= (int)$monitorLayout['student_size'] ?>px
                        </span>
                    </div>

                </div>
            </div>

            <div class="section">
                <h2>🖥️ Display Options</h2>

                <div class="grid">

                    <div class="field full">
                        <label for="office_name">Office Name</label>
                        <input
                            type="text"
                            id="office_name"
                            name="office_name"
                            maxlength="150"
                            value="<?= e($monitorLayout['office_name']) ?>"
                        >
                    </div>

                </div>

                <div class="checks" style="margin-top:15px;">

                    <label class="check">
                        <input
                            type="checkbox"
                            name="show_header"
                            <?= (int)$monitorLayout['show_header'] ? 'checked' : '' ?>
                        >
                        Show Monitor Header
                    </label>

                    <label class="check">
                        <input
                            type="checkbox"
                            name="show_footer"
                            <?= (int)$monitorLayout['show_footer'] ? 'checked' : '' ?>
                        >
                        Show Monitor Footer
                    </label>

                </div>
            </div>

            <div class="actions">

                <div>
                    <button
                        type="submit"
                        name="reset_monitor_layout"
                        class="reset"
                        onclick="return confirm('Reset the monitor layout to the default settings?')"
                    >
                        ↺ Reset
                    </button>

                    <a
                        href="<?= e(basename($_SERVER['PHP_SELF'])) ?>?monitor=1"
                        target="_blank"
                        class="button-link preview"
                    >
                        👁 Preview Monitor
                    </a>
                </div>

                <button
                    type="submit"
                    name="save_monitor_layout"
                    class="save"
                >
                    💾 Save Layout
                </button>

            </div>

        </form>

        <div class="help">
            <strong>How to use:</strong><br>
            1. Change the colors, sizes, header/footer visibility, or office name.<br>
            2. Click <strong>Save Layout</strong>.<br>
            3. Open the monitor using
            <strong>?monitor=1</strong> on your extension monitor, TV, projector, or second screen.<br>
            4. Refresh the monitor if it is already open.
        </div>

    </div>
</div>

</body>
</html>
<?php
    exit;
}


/* =====================================================
   EXTENSION MONITOR API
   Uses the existing registrar_queue table.
===================================================== */
if (isset($_GET['queue_monitor_api']) && $_GET['queue_monitor_api'] === '1') {

    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

    $monitorQueue = null;

    $monitorResult = $conn->query("
        SELECT
            id, queue_number, student_name, student_number,
            service, purpose, status, counter, called_at, processing_at
        FROM registrar_queue
        WHERE status IN ('Called','Processing')
        ORDER BY
            CASE WHEN status = 'Processing' THEN 0 ELSE 1 END,
            called_at ASC,
            id ASC
        LIMIT 1
    ");

    if ($monitorResult && $monitorResult->num_rows > 0) {
        $monitorQueue = $monitorResult->fetch_assoc();
    }

    $waitingCount = 0;

    $waitingResult = $conn->query("
        SELECT COUNT(*) AS total
        FROM registrar_queue
        WHERE status = 'Waiting'
    ");

    if ($waitingResult && ($waitingRow = $waitingResult->fetch_assoc())) {
        $waitingCount = (int)$waitingRow['total'];
    }

    echo json_encode([
        'success' => true,
        'current' => $monitorQueue,
        'waiting_count' => $waitingCount,
        'server_time' => date('Y-m-d H:i:s')
    ], JSON_UNESCAPED_UNICODE);

    exit;
}


/* =====================================================
   UNIVERSAL EXTENSION / TV QUEUE MONITOR
   Open:
   queue_management.php?monitor=1

   Responsive for:
   - 1366x768
   - 1600x900
   - 1920x1080
   - 2560x1440
   - 3840x2160 / 4K
   - TVs
   - Projectors
   - Second/extended monitors
   - Portrait and landscape screens
===================================================== */
if (isset($_GET['monitor']) && $_GET['monitor'] === '1') {

    $monitorQueue = null;

    $monitorResult = $conn->query("
        SELECT *
        FROM registrar_queue
        WHERE status IN ('Called','Processing')
        ORDER BY
            CASE WHEN status = 'Processing' THEN 0 ELSE 1 END,
            called_at ASC,
            id ASC
        LIMIT 1
    ");

    if ($monitorResult && $monitorResult->num_rows > 0) {
        $monitorQueue = $monitorResult->fetch_assoc();
    }

    $waitingCount = 0;

    $waitingResult = $conn->query("
        SELECT COUNT(*) AS total
        FROM registrar_queue
        WHERE status = 'Waiting'
    ");

    if ($waitingResult && ($waitingRow = $waitingResult->fetch_assoc())) {
        $waitingCount = (int)$waitingRow['total'];
    }
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta
    name="viewport"
    content="width=device-width, initial-scale=1.0, viewport-fit=cover"
>
<meta name="theme-color" content="#0057b8">
<title>NORSU Queue Monitor</title>

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


html,
body {
    width: 100%;
    height: 100%;
    min-width: 0;
    min-height: 0;
}

.monitor-shell {
    width: 100vw;
    height: 100dvh;
    min-height: 100vh;
    display: grid;
    grid-template-rows:
        clamp(60px, 10vh, 120px)
        minmax(0, 1fr)
        clamp(45px, 7vh, 82px);
    padding:
        env(safe-area-inset-top, 0px)
        env(safe-area-inset-right, 0px)
        env(safe-area-inset-bottom, 0px)
        env(safe-area-inset-left, 0px);
}

.monitor-header {
    min-width: 0;
    min-height: 0;
    background: #fff;
    border-bottom: clamp(3px, .5vh, 7px) solid var(--yellow);
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: clamp(8px, 2vw, 35px);
    padding: clamp(7px, 1.4vw, 25px) clamp(10px, 2.5vw, 40px);
}

.school-info {
    min-width: 0;
    display: flex;
    align-items: center;
    gap: clamp(7px, 1.3vw, 20px);
}

.school-logo {
    width: clamp(38px, 6vw, 82px);
    height: clamp(38px, 6vw, 82px);
    object-fit: contain;
    flex: 0 0 auto;
}

.school-title {
    min-width: 0;
}

.school-title h1 {
    color: var(--blue);
    font-size: clamp(18px, 3vw, 38px);
    line-height: 1;
    font-weight: 900;
    white-space: nowrap;
}

.school-title p {
    margin-top: clamp(2px, .5vh, 7px);
    color: var(--red);
    font-size: clamp(7px, 1vw, 15px);
    font-weight: 800;
    white-space: nowrap;
}

.monitor-title {
    min-width: 0;
    text-align: right;
}

.monitor-title h2 {
    color: var(--blue);
    font-size: clamp(14px, 2.4vw, 32px);
    line-height: 1.05;
    white-space: nowrap;
}

.monitor-title span {
    display: block;
    margin-top: clamp(2px, .4vh, 6px);
    color: #666;
    font-size: clamp(7px, .9vw, 14px);
    white-space: nowrap;
}

.monitor-main {
    min-width: 0;
    min-height: 0;
    padding: clamp(7px, 2vh, 25px) clamp(7px, 2vw, 30px);
    display: flex;
    align-items: center;
    justify-content: center;
    overflow: hidden;
}

.queue-panel {
    width: min(96vw, 1450px);
    height: min(78vh, 680px);
    min-height: 0;
    background: rgba(255,255,255,.98);
    border-radius: clamp(10px, 2vw, 28px);
    box-shadow: 0 1.5vh 5vw rgba(0,0,0,.28);
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    text-align: center;
    padding: clamp(16px, 3vw, 55px);
    overflow: hidden;
}

.display-label {
    color: #666;
    font-size: clamp(13px, 2vw, 30px);
    font-weight: 900;
    letter-spacing: clamp(1px, .35vw, 5px);
}

.queue-number {
    width: 100%;
    color: var(--blue);
    font-size: clamp(55px, 15vw, 205px);
    line-height: .88;
    font-weight: 900;
    letter-spacing: clamp(1px, .5vw, 8px);
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
    margin: clamp(4px, 1vh, 14px) 0 clamp(7px, 1.5vh, 20px);
}

.student-name {
    max-width: 95%;
    color: var(--dark-blue);
    font-size: clamp(20px, 4.2vw, 58px);
    line-height: 1.05;
    font-weight: 900;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.service {
    max-width: 90%;
    color: #555;
    font-size: clamp(13px, 2.3vw, 32px);
    line-height: 1.15;
    margin-top: clamp(4px, 1vh, 12px);
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.monitor-details {
    display: flex;
    align-items: center;
    justify-content: center;
    flex-wrap: wrap;
    gap: clamp(5px, 1vw, 14px);
    margin-top: clamp(10px, 2vh, 25px);
}

.monitor-pill {
    background: #eef4fb;
    color: var(--dark-blue);
    border-radius: 999px;
    padding: clamp(5px, 1vh, 12px) clamp(9px, 1.5vw, 24px);
    font-size: clamp(10px, 1.3vw, 20px);
    font-weight: 800;
    white-space: nowrap;
}

.status-processing {
    background: #fff3cd;
    color: #856404;
}

.status-called {
    background: #dbeafe;
    color: #1e40af;
}

.no-current {
    max-width: 90%;
    text-align: center;
}

.no-current-icon {
    font-size: clamp(42px, 7vw, 100px);
    margin-bottom: clamp(7px, 1.5vh, 18px);
}

.no-current h2 {
    color: var(--dark-blue);
    font-size: clamp(22px, 4vw, 52px);
    margin-bottom: clamp(4px, 1vh, 12px);
}

.no-current p {
    color: #666;
    font-size: clamp(12px, 1.8vw, 25px);
}

.monitor-footer {
    min-width: 0;
    min-height: 0;
    background: rgba(0,0,0,.28);
    color: white;
    display: grid;
    grid-template-columns: 1fr auto 1fr;
    align-items: center;
    gap: 10px;
    padding: 7px clamp(10px, 2.5vw, 40px);
    font-size: clamp(10px, 1.2vw, 18px);
}

.waiting-count,
.clock,
.office-name {
    white-space: nowrap;
}

.waiting-count {
    font-weight: 800;
}

.clock {
    font-weight: 800;
    text-align: center;
}

.office-name {
    text-align: right;
    font-weight: 700;
}

.monitor-controls {
    position: fixed;
    right: clamp(7px, 1vw, 18px);
    top: clamp(7px, 1vw, 18px);
    z-index: 9999;
    display: flex;
    gap: 5px;
    opacity: .22;
    transition: opacity .2s ease;
}

.monitor-controls:hover,
.monitor-controls:focus-within {
    opacity: 1;
}

.monitor-button {
    border: 0;
    border-radius: 6px;
    background: rgba(0,0,0,.55);
    color: white;
    padding: 7px 10px;
    cursor: pointer;
    font-size: clamp(9px, .8vw, 13px);
}

.monitor-button:hover {
    background: rgba(0,0,0,.8);
}

.monitor-settings-backdrop {
    position: fixed;
    inset: 0;
    z-index: 10000;
    display: none;
    align-items: center;
    justify-content: center;
    padding: 20px;
    background: rgba(0,0,0,.72);
}

.monitor-settings-backdrop.open { display: flex; }

.monitor-settings {
    width: min(560px, 96vw);
    max-height: 92vh;
    overflow-y: auto;
    background: #fff;
    border-radius: 18px;
    box-shadow: 0 20px 70px rgba(0,0,0,.4);
    padding: 24px;
    color: #222;
}

.monitor-settings h3 { color: var(--dark-blue); margin-bottom: 5px; font-size: 24px; }

.monitor-settings .settings-subtitle { color: #666; margin-bottom: 18px; font-size: 13px; }

.monitor-setting { margin-bottom: 14px; }

.monitor-setting label { display:block; font-weight: 800; margin-bottom: 6px; }

.monitor-setting input[type=text], .monitor-setting select {
    width:100%; padding:10px 12px; border:1px solid #ccd5df; border-radius:8px; font-size:14px;
}

.monitor-checks { display:grid; grid-template-columns:1fr 1fr; gap:8px; }

.monitor-check { display:flex; align-items:center; gap:7px; font-size:13px; }

.monitor-setting input[type=range] { width:100%; }

.monitor-settings-actions { display:flex; justify-content:flex-end; gap:8px; margin-top:18px; flex-wrap:wrap; }

.monitor-settings-actions button { border:0; border-radius:8px; padding:10px 14px; cursor:pointer; font-weight:800; }

.monitor-save { background: var(--blue); color:#fff; }

.monitor-reset { background:#eee; color:#222; }

.monitor-close { background:var(--red); color:#fff; }

@media (max-width: 600px) { .monitor-checks { grid-template-columns:1fr; } .monitor-settings { padding:18px; } }

.new-call {
    animation: monitorNewCall .8s ease;
}

@keyframes monitorNewCall {
    0% {
        transform: scale(.96);
        opacity: .45;
    }
    55% {
        transform: scale(1.02);
        opacity: 1;
    }
    100% {
        transform: scale(1);
        opacity: 1;
    }
}

@media (max-height: 600px) {

    .monitor-shell {
        grid-template-rows: 52px minmax(0, 1fr) 38px;
    }

    .queue-panel {
        height: 86vh;
        padding: 9px 22px;
    }

    .queue-number {
        font-size: clamp(48px, 12vw, 130px);
        margin: 2px 0 7px;
    }

    .student-name {
        font-size: clamp(17px, 3vw, 35px);
    }

    .service {
        font-size: clamp(11px, 1.7vw, 20px);
        margin-top: 3px;
    }

    .monitor-details {
        margin-top: 7px;
    }
}

@media (orientation: portrait) {

    .monitor-title span {
        display: none;
    }

    .monitor-title h2 {
        font-size: clamp(11px, 3vw, 20px);
    }

    .queue-panel {
        width: 94vw;
        height: 78vh;
    }

    .queue-number {
        font-size: clamp(50px, 22vw, 150px);
    }

    .student-name {
        white-space: normal;
        font-size: clamp(19px, 6vw, 40px);
    }

    .service {
        white-space: normal;
    }

    .monitor-footer {
        grid-template-columns: 1fr 1fr;
    }

    .office-name {
        display: none;
    }
}

@media (min-width: 2500px) {

    .queue-panel {
        width: min(92vw, 1900px);
        height: min(80vh, 900px);
    }
}

@media (prefers-reduced-motion: reduce) {

    .new-call {
        animation: none;
    }
}
/* =====================================================
   PHP-SAVED MONITOR LAYOUT
===================================================== */
:root {
    --monitor-background: <?= e($monitorLayout['background_color']) ?>;
    --monitor-header-background: <?= e($monitorLayout['header_background']) ?>;
    --monitor-panel-background: <?= e($monitorLayout['panel_background']) ?>;
    --monitor-queue-color: <?= e($monitorLayout['queue_color']) ?>;
    --monitor-text-color: <?= e($monitorLayout['text_color']) ?>;
}

body {
    background: var(--monitor-background);
}

.monitor-header {
    background: var(--monitor-header-background);
    display: <?= (int)$monitorLayout['show_header'] ? 'flex' : 'none' ?>;
}

.queue-panel {
    width: min(<?= (int)$monitorLayout['panel_width'] ?>vw, 1450px);
    height: min(<?= (int)$monitorLayout['panel_height'] ?>vh, 680px);
    background: <?= e($monitorLayout['panel_background']) ?>;
    border-radius: <?= (int)$monitorLayout['panel_radius'] ?>px;
}

.queue-number {
    width: 100%;
    color: var(--monitor-queue-color);
    font-size: clamp(42px, <?= min(12, max(8, (int)$monitorLayout['queue_size'])) ?>vw, 170px);
    line-height: .9;
    font-weight: 900;
    letter-spacing: clamp(0px, .35vw, 6px);
    white-space: nowrap;
    overflow: visible;
    text-overflow: clip;
    text-align: center;
    display: block;
    transform-origin: center;
}

.student-name {
    color: var(--monitor-text-color);
    font-size: clamp(20px, 4.2vw, <?= (int)$monitorLayout['student_size'] ?>px);
}

.monitor-footer {
    display: <?= (int)$monitorLayout['show_footer'] ? 'grid' : 'none' ?>;
}

</style>
</head>

<body>

<div class="monitor-shell">

    <header class="monitor-header">

        <div class="school-info">

            <?php if (file_exists(__DIR__ . "/norsu.png")): ?>

                <img
                    src="norsu.png"
                    class="school-logo"
                    alt="NORSU Logo"
                >

            <?php endif; ?>

            <div class="school-title">
                <h1>NORSU</h1>
                <p>NEGROS ORIENTAL STATE UNIVERSITY</p>
            </div>

        </div>

        <div class="monitor-title">
            <h2>REGISTRAR QUEUE</h2>
            <span>Student Service Queue Monitor</span>
        </div>

    </header>


    <div class="monitor-controls">

        <button
            type="button"
            class="monitor-button"
            onclick="openMonitorSettings()"
            title="Edit queue monitor display"
        >
            ⚙ Edit Monitor
        </button>

        <button
            type="button"
            class="monitor-button"
            onclick="toggleMonitorFullScreen()"
        >
            ⛶ Full Screen
        </button>

        <button
            type="button"
            class="monitor-button"
            onclick="location.reload()"
        >
            ↻
        </button>

    </div>


    <div
        class="monitor-settings-backdrop"
        id="monitorSettingsBackdrop"
        onclick="closeMonitorSettings(event)"
    >
        <div class="monitor-settings" onclick="event.stopPropagation()">
            <h3>Edit Queue Monitor</h3>
            <div class="settings-subtitle">Customize what appears on the TV, projector, or second monitor. These display preferences are saved in this browser.</div>

            <div class="monitor-setting">
                <label for="settingTitle">Monitor Title</label>
                <input type="text" id="settingTitle" value="REGISTRAR QUEUE" maxlength="60">
            </div>

            <div class="monitor-setting">
                <label for="settingSubtitle">Monitor Subtitle</label>
                <input type="text" id="settingSubtitle" value="Student Service Queue Monitor" maxlength="100">
            </div>

            <div class="monitor-setting">
                <label>Show on Monitor</label>
                <div class="monitor-checks">
                    <label class="monitor-check"><input type="checkbox" id="showStudent" checked> Student Name</label>
                    <label class="monitor-check"><input type="checkbox" id="showService" checked> Service</label>
                    <label class="monitor-check"><input type="checkbox" id="showCounter" checked> Counter</label>
                    <label class="monitor-check"><input type="checkbox" id="showStatus" checked> Status</label>
                    <label class="monitor-check"><input type="checkbox" id="showWaiting" checked> Waiting Count</label>
                    <label class="monitor-check"><input type="checkbox" id="showClock" checked> Date & Time</label>
                    <label class="monitor-check"><input type="checkbox" id="showOffice" checked> Office Name</label>
                    <label class="monitor-check"><input type="checkbox" id="voiceEnabled" checked> Voice Announcement</label>
                </div>
            </div>

            <div class="monitor-setting">
                <label for="settingScale">Display Size: <span id="scaleValue">100%</span></label>
                <input type="range" id="settingScale" min="80" max="125" value="100" step="5" oninput="document.getElementById('scaleValue').textContent=this.value+'%'">
            </div>

            <div class="monitor-setting">
                <label for="settingRefresh">Auto Refresh</label>
                <select id="settingRefresh">
                    <option value="1000">Every 1 second</option>
                    <option value="2000" selected>Every 2 seconds</option>
                    <option value="3000">Every 3 seconds</option>
                    <option value="5000">Every 5 seconds</option>
                    <option value="10000">Every 10 seconds</option>
                </select>
            </div>

            <div class="monitor-settings-actions">
                <button type="button" class="monitor-reset" onclick="resetMonitorSettings()">Reset</button>
                <button type="button" class="monitor-close" onclick="closeMonitorSettings()">Cancel</button>
                <button type="button" class="monitor-save" onclick="saveMonitorSettings()">Save Changes</button>
            </div>
        </div>
    </div>


    <main class="monitor-main">

        <section
            class="queue-panel"
            id="queuePanel"
        >

            <?php if ($monitorQueue): ?>

                <div class="display-label">
                    NOW SERVING
                </div>

                <div class="queue-number">
                    <?= e($monitorQueue['queue_number']) ?>
                </div>

                <div class="student-name">
                    <?= e($monitorQueue['student_name']) ?>
                </div>

                <div class="service">
                    <?= e($monitorQueue['service']) ?>
                </div>

                <div class="monitor-details">

                    <div class="monitor-pill">
                        Counter:
                        <?= e(
                            $monitorQueue['counter'] !== ''
                            ? $monitorQueue['counter']
                            : 'Registrar Counter'
                        ) ?>
                    </div>

                    <div
                        class="
                            monitor-pill
                            <?= $monitorQueue['status'] === 'Processing'
                                ? 'status-processing'
                                : 'status-called'
                            ?>
                        "
                    >
                        <?= e($monitorQueue['status']) ?>
                    </div>

                </div>

            <?php else: ?>

                <div class="no-current">

                    <div class="no-current-icon">
                        🎫
                    </div>

                    <h2>
                        NO CURRENT QUEUE
                    </h2>

                    <p>
                        Please wait for the next queue number to be called.
                    </p>

                </div>

            <?php endif; ?>

        </section>

    </main>


    <footer class="monitor-footer">

        <div class="waiting-count">
            Waiting:
            <span id="waitingCount">
                <?= $waitingCount ?>
            </span>
            student(s)
        </div>

        <div
            class="clock"
            id="monitorClock"
        >
            Loading...
        </div>

        <div class="office-name">
            <?= e($monitorLayout['office_name']) ?>
        </div>

    </footer>

</div>


<script>
const MONITOR_SETTINGS_KEY = 'norsu_queue_monitor_settings_v1';

const defaultMonitorSettings = {
    title: 'REGISTRAR QUEUE',
    subtitle: 'Student Service Queue Monitor',
    showStudent: true,
    showService: true,
    showCounter: true,
    showStatus: true,
    showWaiting: true,
    showClock: true,
    showOffice: true,
    voiceEnabled: true,
    scale: 100,
    refresh: 2000
};

let monitorSettings = loadMonitorSettings();

function loadMonitorSettings() {
    try {
        const saved = JSON.parse(localStorage.getItem(MONITOR_SETTINGS_KEY) || '{}');
        return Object.assign({}, defaultMonitorSettings, saved);
    } catch (error) {
        return Object.assign({}, defaultMonitorSettings);
    }
}

function applyMonitorSettings() {
    const title = document.querySelector('.monitor-title h2');
    const subtitle = document.querySelector('.monitor-title span');
    const student = document.querySelector('.student-name');
    const service = document.querySelector('.service');
    const details = document.querySelector('.monitor-details');
    const pills = document.querySelectorAll('.monitor-pill');
    const waiting = document.querySelector('.waiting-count');
    const clock = document.querySelector('.clock');
    const office = document.querySelector('.office-name');

    if (title) title.textContent = monitorSettings.title;
    if (subtitle) subtitle.textContent = monitorSettings.subtitle;
    if (student) student.style.display = monitorSettings.showStudent ? '' : 'none';
    if (service) service.style.display = monitorSettings.showService ? '' : 'none';
    if (details) details.style.display = (monitorSettings.showCounter || monitorSettings.showStatus) ? '' : 'none';
    if (pills[0]) pills[0].style.display = monitorSettings.showCounter ? '' : 'none';
    if (pills[1]) pills[1].style.display = monitorSettings.showStatus ? '' : 'none';
    if (waiting) waiting.style.display = monitorSettings.showWaiting ? '' : 'none';
    if (clock) clock.style.display = monitorSettings.showClock ? '' : 'none';
    if (office) office.style.display = monitorSettings.showOffice ? '' : 'none';

    document.documentElement.style.setProperty('--monitor-scale', String(Number(monitorSettings.scale) / 100));
    const panel = document.getElementById('queuePanel');
    if (panel) panel.style.transform = 'scale(' + (Number(monitorSettings.scale) / 100) + ')';
}

function openMonitorSettings() {
    const backdrop = document.getElementById('monitorSettingsBackdrop');
    document.getElementById('settingTitle').value = monitorSettings.title;
    document.getElementById('settingSubtitle').value = monitorSettings.subtitle;
    document.getElementById('showStudent').checked = monitorSettings.showStudent;
    document.getElementById('showService').checked = monitorSettings.showService;
    document.getElementById('showCounter').checked = monitorSettings.showCounter;
    document.getElementById('showStatus').checked = monitorSettings.showStatus;
    document.getElementById('showWaiting').checked = monitorSettings.showWaiting;
    document.getElementById('showClock').checked = monitorSettings.showClock;
    document.getElementById('showOffice').checked = monitorSettings.showOffice;
    document.getElementById('voiceEnabled').checked = monitorSettings.voiceEnabled;
    document.getElementById('settingScale').value = monitorSettings.scale;
    document.getElementById('scaleValue').textContent = monitorSettings.scale + '%';
    document.getElementById('settingRefresh').value = String(monitorSettings.refresh);
    if (backdrop) backdrop.classList.add('open');
}

function closeMonitorSettings(event) {
    if (event && event.target && event.target.id !== 'monitorSettingsBackdrop') return;
    const backdrop = document.getElementById('monitorSettingsBackdrop');
    if (backdrop) backdrop.classList.remove('open');
}

function saveMonitorSettings() {
    monitorSettings = {
        title: document.getElementById('settingTitle').value.trim() || defaultMonitorSettings.title,
        subtitle: document.getElementById('settingSubtitle').value.trim() || defaultMonitorSettings.subtitle,
        showStudent: document.getElementById('showStudent').checked,
        showService: document.getElementById('showService').checked,
        showCounter: document.getElementById('showCounter').checked,
        showStatus: document.getElementById('showStatus').checked,
        showWaiting: document.getElementById('showWaiting').checked,
        showClock: document.getElementById('showClock').checked,
        showOffice: document.getElementById('showOffice').checked,
        voiceEnabled: document.getElementById('voiceEnabled').checked,
        scale: Number(document.getElementById('settingScale').value),
        refresh: Number(document.getElementById('settingRefresh').value)
    };
    localStorage.setItem(MONITOR_SETTINGS_KEY, JSON.stringify(monitorSettings));
    applyMonitorSettings();
    closeMonitorSettings();
    startMonitorPolling();
}

function resetMonitorSettings() {
    monitorSettings = Object.assign({}, defaultMonitorSettings);
    localStorage.removeItem(MONITOR_SETTINGS_KEY);
    openMonitorSettings();
}

applyMonitorSettings();

let previousQueueNumber =
    <?= json_encode(
        $monitorQueue
        ? $monitorQueue['queue_number']
        : ''
    ) ?>;

let lastAnnouncementKey = '';
let monitorRequestRunning = false;
let monitorPollTimer = null;


/* =====================================================
   CLOCK
===================================================== */

function updateMonitorClock() {

    const clock =
        document.getElementById('monitorClock');

    if (!clock) {
        return;
    }

    const now = new Date();

    const date =
        now.toLocaleDateString('en-US', {
            year: 'numeric',
            month: 'long',
            day: 'numeric'
        });

    const time =
        now.toLocaleTimeString('en-US', {
            hour: '2-digit',
            minute: '2-digit',
            second: '2-digit'
        });

    clock.textContent =
        date + ' | ' + time;
}

updateMonitorClock();

setInterval(updateMonitorClock, 1000);


/* =====================================================
   HTML ESCAPE
===================================================== */

function escapeMonitorHtml(value) {

    if (
        value === null ||
        value === undefined
    ) {
        return '';
    }

    return String(value)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#039;');
}


/* =====================================================
   UPDATE MONITOR
===================================================== */

function fitQueueNumber() {

    const number = document.querySelector('.queue-number');
    const panel = document.getElementById('queuePanel');

    if (!number || !panel) {
        return;
    }

    let size = parseFloat(window.getComputedStyle(number).fontSize);
    const minimum = 36;
    const available = Math.max(220, panel.clientWidth - 40);

    number.style.fontSize = size + 'px';

    while (number.scrollWidth > available && size > minimum) {
        size -= 2;
        number.style.fontSize = size + 'px';
    }
}

window.addEventListener('resize', fitQueueNumber);


function updateQueueMonitor() {

    if (monitorRequestRunning) {
        return;
    }

    monitorRequestRunning = true;

    fetch(
        'queue_management.php?queue_monitor_api=1&_='
        + Date.now(),
        {
            method: 'GET',
            cache: 'no-store',
            headers: {
                'Cache-Control': 'no-cache'
            }
        }
    )
    .then(function(response) {

        if (!response.ok) {
            throw new Error(
                'HTTP ' + response.status
            );
        }

        return response.json();
    })
    .then(function(data) {

        if (!data || !data.success) {
            return;
        }

        const waiting =
            document.getElementById(
                'waitingCount'
            );

        if (waiting) {
            waiting.textContent =
                data.waiting_count ?? 0;
        }

        const panel =
            document.getElementById(
                'queuePanel'
            );

        if (!panel) {
            return;
        }


        /* ---------------------------------------------
           NO CURRENT QUEUE
        --------------------------------------------- */

        if (!data.current) {

            panel.innerHTML = `

                <div class="no-current">

                    <div class="no-current-icon">
                        🎫
                    </div>

                    <h2>
                        NO CURRENT QUEUE
                    </h2>

                    <p>
                        Please wait for the next
                        queue number to be called.
                    </p>

                </div>

            `;

            previousQueueNumber = '';
            return;
        }


        /* ---------------------------------------------
           CURRENT QUEUE
        --------------------------------------------- */

        const queue = data.current;

        const isNew =
            previousQueueNumber !==
            queue.queue_number;

        panel.innerHTML = `

            <div class="display-label">
                NOW SERVING
            </div>

            <div class="queue-number">
                ${escapeMonitorHtml(
                    queue.queue_number
                )}
            </div>

            <div class="student-name">
                ${escapeMonitorHtml(
                    queue.student_name
                )}
            </div>

            <div class="service">
                ${escapeMonitorHtml(
                    queue.service
                )}
            </div>

            <div class="monitor-details">

                <div class="monitor-pill">
                    Counter:
                    ${escapeMonitorHtml(
                        queue.counter ||
                        'Registrar Counter'
                    )}
                </div>

                <div
                    class="
                        monitor-pill
                        ${
                            queue.status === 'Processing'
                            ? 'status-processing'
                            : 'status-called'
                        }
                    "
                >
                    ${escapeMonitorHtml(
                        queue.status
                    )}
                </div>

            </div>

        `;

        applyMonitorSettings();

        if (isNew) {

            panel.classList.remove(
                'new-call'
            );

            void panel.offsetWidth;

            panel.classList.add(
                'new-call'
            );

            announceQueue(queue);
        }

        previousQueueNumber =
            queue.queue_number;

        fitQueueNumber();

    })
    .catch(function(error) {

        console.log(
            'Queue monitor update:',
            error.message
        );

    })
    .finally(function() {

        monitorRequestRunning = false;

    });
}


/* =====================================================
   AUTO REFRESH
===================================================== */

function startMonitorPolling() {

    if (monitorPollTimer) {
        clearInterval(monitorPollTimer);
    }

    updateQueueMonitor();

    monitorPollTimer = setInterval(
        updateQueueMonitor,
        Number(monitorSettings.refresh) || 2000
    );
}

startMonitorPolling();
fitQueueNumber();


/* =====================================================
   VOICE ANNOUNCEMENT
===================================================== */

function announceQueue(queue) {

    if (!monitorSettings.voiceEnabled) {
        return;
    }

    if (!('speechSynthesis' in window)) {
        return;
    }

    const announcementKey =
        String(queue.queue_number)
        + '|'
        + String(queue.counter || '');

    if (
        lastAnnouncementKey ===
        announcementKey
    ) {
        return;
    }

    lastAnnouncementKey =
        announcementKey;

    const message =
        'Now serving queue number '
        + queue.queue_number
        + '. '
        + queue.student_name
        + '. Please proceed to '
        + (
            queue.counter ||
            'the registrar counter'
        )
        + '.';

    try {

        window.speechSynthesis.cancel();

        const speech =
            new SpeechSynthesisUtterance(
                message
            );

        speech.rate = 0.85;
        speech.pitch = 1;
        speech.volume = 1;

        window.speechSynthesis.speak(
            speech
        );

    } catch (error) {

        console.log(
            'Voice announcement unavailable.'
        );
    }
}


/* =====================================================
   FULL SCREEN
===================================================== */

function toggleMonitorFullScreen() {

    if (!document.fullscreenElement) {

        if (
            document.documentElement.requestFullscreen
        ) {

            document.documentElement
                .requestFullscreen()
                .catch(function() {});

        }

    } else {

        if (document.exitFullscreen) {

            document.exitFullscreen()
                .catch(function() {});

        }
    }
}


/* =====================================================
   SCREEN ADAPTATION
===================================================== */

function updateScreenInformation() {

    document.documentElement.dataset.width =
        String(window.innerWidth);

    document.documentElement.dataset.height =
        String(window.innerHeight);

    document.documentElement.dataset.orientation =
        window.innerWidth >= window.innerHeight
            ? 'landscape'
            : 'portrait';
}

updateScreenInformation();

window.addEventListener(
    'resize',
    updateScreenInformation
);

window.addEventListener(
    'orientationchange',
    function() {
        setTimeout(
            updateScreenInformation,
            150
        );
    }
);


/* =====================================================
   KEYBOARD SHORTCUTS
===================================================== */

document.addEventListener(
    'keydown',
    function(event) {

        const key =
            String(event.key).toLowerCase();

        if (key === 'f') {
            toggleMonitorFullScreen();
        }

        if (key === 'e') {
        openMonitorSettings();
        return;
    }

    if (key === 'r') {
            updateQueueMonitor();
        }
    }
);


/* =====================================================
   START AUTO UPDATE
===================================================== */

updateQueueMonitor();

setInterval(
    updateQueueMonitor,
    2000
);
</script>

</body>
</html>

<?php
    exit;
}

/* =====================================================
   SERVICES
===================================================== */
$services = [
    'Document Requests',
    'Transcript of Records (TOR)',
    'Certificate of Enrollment',
    'Certificate of Grades',
    'Good Moral Certificate',
    'Registration / Enrollment',
    'Grades Concern',
    'Student Records',
    'Transfer Credentials',
    'Other Registrar Service'
];

/* =====================================================
   ACTION HANDLERS
===================================================== */
$error = '';

/* ADD TO QUEUE */
if (isset($_POST['add_queue'])) {

    $studentNumber = trim($_POST['student_number'] ?? '');
    $studentName   = trim($_POST['student_name'] ?? '');
    $service       = trim($_POST['service'] ?? '');
    $purpose       = trim($_POST['purpose'] ?? '');

    if ($studentName === '' || $service === '') {
        $error = "Please enter the student name and select a service.";
    } else {

        $queueNumber = generateQueueNumber($conn);

        $stmt = $conn->prepare("
            INSERT INTO registrar_queue
            (queue_number, student_number, student_name, service, purpose, status)
            VALUES (?, ?, ?, ?, ?, 'Waiting')
        ");

        if ($stmt) {
            $stmt->bind_param(
                "sssss",
                $queueNumber,
                $studentNumber,
                $studentName,
                $service,
                $purpose
            );

            if ($stmt->execute()) {
                $stmt->close();
                redirectPage("added=1");
            } else {
                $error = "Unable to add student to the queue.";
                $stmt->close();
            }
        } else {
            $error = "Unable to prepare queue request.";
        }
    }
}

/* CALL NEXT */
if (isset($_POST['call_next'])) {

    $counter = trim($_POST['counter'] ?? '');
    if ($counter === '') {
        $counter = 'Registrar Counter';
    }

    /* First finish/clear any currently called student at this counter */
    $stmt = $conn->prepare("
        SELECT id
        FROM registrar_queue
        WHERE status IN ('Called','Processing')
        AND counter = ?
        ORDER BY id ASC
        LIMIT 1
    ");

    $currentId = 0;

    if ($stmt) {
        $stmt->bind_param("s", $counter);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($result && ($row = $result->fetch_assoc())) {
            $currentId = (int)$row['id'];
        }
        $stmt->close();
    }

    if ($currentId > 0) {
        $error = "There is already a student being served at " . $counter . ". Complete that student first.";
    } else {

        $stmt = $conn->prepare("
            SELECT id
            FROM registrar_queue
            WHERE status = 'Waiting'
            ORDER BY id ASC
            LIMIT 1
        ");

        $nextId = 0;

        if ($stmt) {
            $stmt->execute();
            $result = $stmt->get_result();

            if ($result && ($row = $result->fetch_assoc())) {
                $nextId = (int)$row['id'];
            }

            $stmt->close();
        }

        if ($nextId > 0) {
            $stmt = $conn->prepare("
                UPDATE registrar_queue
                SET status = 'Called',
                    counter = ?,
                    called_at = NOW()
                WHERE id = ?
            ");

            if ($stmt) {
                $stmt->bind_param("si", $counter, $nextId);

                if ($stmt->execute()) {
                    $stmt->close();
                    redirectPage("called=1");
                }

                $stmt->close();
            }

            $error = "Unable to call the next student.";
        } else {
            $error = "There are no waiting students.";
        }
    }
}

/* START PROCESSING */
if (isset($_POST['start_processing'])) {

    $id = (int)($_POST['queue_id'] ?? 0);

    $stmt = $conn->prepare("
        UPDATE registrar_queue
        SET status = 'Processing',
            processing_at = NOW()
        WHERE id = ?
        AND status = 'Called'
    ");

    if ($stmt) {
        $stmt->bind_param("i", $id);

        if ($stmt->execute() && $stmt->affected_rows > 0) {
            $stmt->close();
            redirectPage("processing=1");
        }

        $stmt->close();
    }

    $error = "Unable to start processing this queue.";
}

/* COMPLETE SERVICE */
if (isset($_POST['complete_queue'])) {

    $id = (int)($_POST['queue_id'] ?? 0);

    $stmt = $conn->prepare("
        UPDATE registrar_queue
        SET status = 'Completed',
            completed_at = NOW()
        WHERE id = ?
        AND status IN ('Called','Processing')
    ");

    if ($stmt) {
        $stmt->bind_param("i", $id);

        if ($stmt->execute() && $stmt->affected_rows > 0) {
            $stmt->close();
            redirectPage("completed=1");
        }

        $stmt->close();
    }

    $error = "Unable to complete this queue.";
}

/* RECALL */
if (isset($_POST['recall_queue'])) {

    $id = (int)($_POST['queue_id'] ?? 0);

    $stmt = $conn->prepare("
        UPDATE registrar_queue
        SET status = 'Called',
            called_at = NOW()
        WHERE id = ?
        AND status IN ('Called','Processing')
    ");

    if ($stmt) {
        $stmt->bind_param("i", $id);

        if ($stmt->execute()) {
            $stmt->close();
            redirectPage("recalled=1");
        }

        $stmt->close();
    }

    $error = "Unable to recall this student.";
}

/* CANCEL */
if (isset($_POST['cancel_queue'])) {

    $id = (int)($_POST['queue_id'] ?? 0);

    $stmt = $conn->prepare("
        UPDATE registrar_queue
        SET status = 'Cancelled'
        WHERE id = ?
        AND status IN ('Waiting','Called','Processing')
    ");

    if ($stmt) {
        $stmt->bind_param("i", $id);

        if ($stmt->execute()) {
            $stmt->close();
            redirectPage("cancelled=1");
        }

        $stmt->close();
    }

    $error = "Unable to cancel this queue.";
}

/* DELETE COMPLETED/CANCELLED RECORD */
if (isset($_POST['delete_queue'])) {

    $id = (int)($_POST['queue_id'] ?? 0);

    $stmt = $conn->prepare("
        DELETE FROM registrar_queue
        WHERE id = ?
        AND status IN ('Completed','Cancelled')
    ");

    if ($stmt) {
        $stmt->bind_param("i", $id);

        if ($stmt->execute()) {
            $stmt->close();
            redirectPage("deleted=1");
        }

        $stmt->close();
    }

    $error = "Only completed or cancelled records can be deleted.";
}

/* =====================================================
   COUNTS
===================================================== */
$counts = [
    'all' => 0,
    'waiting' => 0,
    'processing' => 0,
    'completed' => 0,
    'called' => 0
];

$countResult = $conn->query("
    SELECT
        COUNT(*) AS all_count,
        SUM(status = 'Waiting') AS waiting_count,
        SUM(status = 'Processing') AS processing_count,
        SUM(status = 'Completed') AS completed_count,
        SUM(status = 'Called') AS called_count
    FROM registrar_queue
    WHERE DATE(created_at) = CURDATE()
");

if ($countResult && ($row = $countResult->fetch_assoc())) {
    $counts['all'] = (int)$row['all_count'];
    $counts['waiting'] = (int)$row['waiting_count'];
    $counts['processing'] = (int)$row['processing_count'];
    $counts['completed'] = (int)$row['completed_count'];
    $counts['called'] = (int)$row['called_count'];
}

/* =====================================================
   CURRENT QUEUE
===================================================== */
$currentQueue = null;

$result = $conn->query("
    SELECT *
    FROM registrar_queue
    WHERE status IN ('Called','Processing')
    ORDER BY
        CASE WHEN status = 'Processing' THEN 0 ELSE 1 END,
        called_at ASC
    LIMIT 1
");

if ($result) {
    $currentQueue = $result->fetch_assoc();
}

/* =====================================================
   WAITING QUEUE
===================================================== */
$waitingQueue = [];

$result = $conn->query("
    SELECT *
    FROM registrar_queue
    WHERE status = 'Waiting'
    ORDER BY created_at ASC, id ASC
");

if ($result) {
    while ($row = $result->fetch_assoc()) {
        $waitingQueue[] = $row;
    }
}

/* =====================================================
   CALLED / SERVED STUDENTS
===================================================== */
$search = trim($_GET['search'] ?? '');
$statusFilter = trim($_GET['status'] ?? '');

$history = [];

$sql = "
    SELECT *
    FROM registrar_queue
    WHERE 1=1
";

$params = [];
$types = '';

if ($search !== '') {
    $sql .= "
        AND (
            queue_number LIKE ?
            OR student_number LIKE ?
            OR student_name LIKE ?
            OR service LIKE ?
        )
    ";

    $searchLike = '%' . $search . '%';

    $params[] = $searchLike;
    $params[] = $searchLike;
    $params[] = $searchLike;
    $params[] = $searchLike;

    $types .= 'ssss';
}

if (in_array($statusFilter, ['Waiting','Called','Processing','Completed','Cancelled'], true)) {
    $sql .= " AND status = ?";
    $params[] = $statusFilter;
    $types .= 's';
}

$sql .= " ORDER BY created_at DESC, id DESC LIMIT 200";

$stmt = $conn->prepare($sql);

if ($stmt) {

    if (!empty($params)) {
        $stmt->bind_param($types, ...$params);
    }

    $stmt->execute();
    $result = $stmt->get_result();

    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $history[] = $row;
        }
    }

    $stmt->close();
}

/* =====================================================
   CURRENT ADMIN
===================================================== */
$adminName = $_SESSION['admin_username']
    ?? $_SESSION['username']
    ?? 'Administrator';

?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">

<title>Queue Management | NORSU Registrar System</title>

<link
    rel="stylesheet"
    href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css"
>

<style>

:root {
    --blue: #0057b8;
    --dark-blue: #003b7a;
    --red: #d71920;
    --yellow: #ffc107;
    --white: #ffffff;
    --gray: #f4f6f9;
    --text: #222222;
    --muted: #666666;
    --border: #e3e3e3;
    --green: #198754;
}

* {
    margin: 0;
    padding: 0;
    box-sizing: border-box;
}

body {
    font-family: Arial, Helvetica, sans-serif;
    background: var(--gray);
    color: var(--text);
}

.sidebar {
    position: fixed;
    left: 0;
    top: 0;
    width: 265px;
    height: 100vh;
    background: linear-gradient(180deg, var(--blue), var(--dark-blue));
    color: white;
    overflow-y: auto;
    z-index: 1000;
    border-right: 4px solid var(--yellow);
}

.sidebar-header {
    background: white;
    color: var(--blue);
    text-align: center;
    padding: 25px 15px;
    border-bottom: 5px solid var(--red);
}

.sidebar-logo {
    width: 82px;
    height: 82px;
    margin: 0 auto 10px;
    display: block;
    object-fit: contain;
}

.sidebar-header h2 {
    font-size: 22px;
}

.sidebar-header p {
    font-size: 11px;
    color: var(--red);
    font-weight: bold;
    margin-top: 4px;
}

.menu-title {
    padding: 18px 20px 8px;
    font-size: 11px;
    color: var(--yellow);
    text-transform: uppercase;
    letter-spacing: 1px;
    font-weight: bold;
}

.sidebar-menu a {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 13px 20px;
    color: white;
    text-decoration: none;
    border-left: 4px solid transparent;
    font-size: 14px;
    transition: .2s;
}

.sidebar-menu a:hover {
    background: rgba(255,255,255,.12);
    border-left-color: var(--yellow);
}

.sidebar-menu a.active {
    background: var(--red);
    border-left-color: var(--yellow);
    font-weight: bold;
}

.sidebar-menu .icon {
    width: 22px;
    text-align: center;
    font-size: 16px;
}

.main {
    margin-left: 265px;
    min-height: 100vh;
}

.topbar {
    height: 75px;
    background: var(--white);
    border-bottom: 4px solid var(--yellow);
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 10px 25px;
}

.topbar-left {
    display: flex;
    align-items: center;
    gap: 12px;
}

.topbar-logo {
    width: 48px;
    height: 48px;
    object-fit: contain;
}

.topbar h1 {
    font-size: 22px;
    color: var(--dark-blue);
}

.admin-info {
    display: flex;
    align-items: center;
    gap: 10px;
}

.admin-details {
    display: flex;
    flex-direction: column;
    text-align: right;
}

.admin-details strong {
    color: var(--dark-blue);
    font-size: 14px;
}

.admin-details span {
    color: var(--muted);
    font-size: 11px;
}

.admin-avatar {
    width: 42px;
    height: 42px;
    border-radius: 50%;
    background: var(--blue);
    color: white;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: bold;
}

.menu-toggle {
    display: none;
    border: none;
    background: var(--blue);
    color: white;
    width: 40px;
    height: 40px;
    border-radius: 6px;
    font-size: 20px;
    cursor: pointer;
}

.content {
    padding: 25px;
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

.stat-card {
    background: white;
    border-radius: 10px;
    padding: 20px;
    box-shadow: 0 2px 10px rgba(0,0,0,.08);
    border-left: 5px solid var(--blue);
}

.stat-card:nth-child(2) {
    border-left-color: var(--green);
}

.stat-card:nth-child(3) {
    border-left-color: var(--red);
}

.stat-card:nth-child(4) {
    border-left-color: var(--yellow);
}

.stat-card:nth-child(5) {
    border-left-color: #6c757d;
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

.table-container {
    width: 100%;
    overflow-x: auto;
}

table {
    width: 100%;
    border-collapse: collapse;
    min-width: 1150px;
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

.empty {
    text-align: center;
    padding: 35px;
    color: var(--muted);
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



/* =====================================================
   DOCUMENT REQUESTS - PAGE-SPECIFIC STYLES
   These styles extend the Grades Management design.
===================================================== */

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


/* =====================================================
   QUEUE MANAGEMENT SPECIFIC STYLES
===================================================== */
.dashboard-grid {
    display: grid;
    grid-template-columns: 1.15fr .85fr;
    gap: 20px;
    margin-bottom: 25px;
}

.current-queue {
    text-align: center;
    padding: 12px 5px 5px;
}

.current-number {
    font-size: 48px;
    line-height: 1.1;
    font-weight: bold;
    color: var(--blue);
    margin-bottom: 8px;
}

.current-name {
    font-size: 21px;
    font-weight: bold;
    color: var(--dark-blue);
    margin-bottom: 6px;
}

.current-service {
    color: var(--muted);
    font-size: 14px;
    margin-bottom: 18px;
}

.queue-meta {
    display: flex;
    justify-content: center;
    flex-wrap: wrap;
    gap: 8px;
}

.meta-pill {
    background: #f1f4f7;
    color: #555;
    border-radius: 20px;
    padding: 7px 12px;
    font-size: 12px;
}

.waiting-list {
    max-height: 390px;
    overflow-y: auto;
}

.waiting-item {
    display: flex;
    align-items: center;
    gap: 13px;
    padding: 13px 0;
    border-bottom: 1px solid var(--border);
}

.waiting-item:last-child {
    border-bottom: none;
}

.waiting-number {
    min-width: 85px;
    color: var(--blue);
    font-weight: bold;
}

.waiting-info {
    flex: 1;
    min-width: 0;
}

.waiting-info strong {
    display: block;
    color: #333;
    font-size: 13px;
}

.waiting-info span {
    display: block;
    color: var(--muted);
    font-size: 11px;
    margin-top: 3px;
}

.empty,
.no-data {
    text-align: center;
    padding: 35px 15px;
    color: var(--muted);
}

.filter-bar {
    display: grid;
    grid-template-columns: 1fr 220px auto;
    gap: 10px;
    padding: 16px 22px;
    background: #fafafa;
    border-bottom: 1px solid var(--border);
}

.table-wrapper {
    overflow-x: auto;
}

.badge {
    display: inline-block;
    padding: 5px 9px;
    border-radius: 20px;
    font-size: 11px;
    font-weight: bold;
}

.badge.waiting { background: #d4edda; color: #155724; }
.badge.called { background: #e2e3e5; color: #383d41; }
.badge.processing { background: #fff3cd; color: #856404; }
.badge.completed { background: #d4edda; color: #155724; }
.badge.cancelled { background: #e9ecef; color: #6c757d; }

.action-group {
    display: flex;
    flex-wrap: wrap;
    gap: 5px;
}

.queue-code {
    font-weight: bold;
    color: var(--blue);
}

.student-name {
    font-weight: bold;
    color: #333;
}

@media (max-width: 1100px) {
    .stats { grid-template-columns: repeat(3, 1fr); }
    .dashboard-grid { grid-template-columns: 1fr; }
}

@media (max-width: 800px) {
    .filter-bar { grid-template-columns: 1fr; }
}

@media (max-width: 600px) {
    .stats { grid-template-columns: 1fr; }
    .current-number { font-size: 40px; }
    .content { padding: 15px; }
}
</style>
</head>

<body>

<div class="layout">

    <!-- =================================================
         SIDEBAR
    ================================================== -->
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

        <a href="queue.php" class="active">
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

    <!-- =================================================
         MAIN
    ================================================== -->
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

        <h1>Queue Management</h1>

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

            <div class="page-header">
                <div>
                    <div class="page-title">Queue Management</div>
                    <div class="page-subtitle">Manage students waiting for registrar services.</div>
                </div>

                <div style="display:flex; gap:8px; flex-wrap:wrap; align-items:center;">

                    <a
                        href="queue.php?monitor=1"
                        target="_blank"
                        rel="noopener"
                        class="btn btn-success"
                        title="Open queue display on an extension monitor, TV or projector"
                    >
                        <i class="fa-solid fa-tv"></i>
                        Open Queue Monitor
                    </a>

                    <form method="POST">
                        <input
                            type="hidden"
                            name="counter"
                            value="Registrar Counter"
                        >

                        <button
                            type="submit"
                            name="call_next"
                            class="btn btn-primary"
                        >
                            <i class="fa-solid fa-bullhorn"></i>
                            Call Next Student
                        </button>
                    </form>

                </div>
            </div>

            <?php if (isset($_GET['added'])): ?>
                <div class="alert success">
                    Student was successfully added to the queue.
                </div>
            <?php endif; ?>

            <?php if (isset($_GET['called'])): ?>
                <div class="alert success">
                    The next student has been called.
                </div>
            <?php endif; ?>

            <?php if (isset($_GET['processing'])): ?>
                <div class="alert success">
                    Queue status changed to Processing.
                </div>
            <?php endif; ?>

            <?php if (isset($_GET['completed'])): ?>
                <div class="alert success">
                    Student service has been completed.
                </div>
            <?php endif; ?>

            <?php if (isset($_GET['recalled'])): ?>
                <div class="alert success">
                    Student has been recalled.
                </div>
            <?php endif; ?>

            <?php if (isset($_GET['cancelled'])): ?>
                <div class="alert success">
                    Queue entry has been cancelled.
                </div>
            <?php endif; ?>

            <?php if (isset($_GET['deleted'])): ?>
                <div class="alert success">
                    Queue record has been deleted.
                </div>
            <?php endif; ?>

            <?php if ($error !== ''): ?>
                <div class="alert error">
                    <?= e($error) ?>
                </div>
            <?php endif; ?>

            <!-- =================================================
                 STATISTICS
            ================================================== -->
            <div class="stats">

                <div class="stat-card">
                    <div class="stat-label">All Requests</div>
                    <div class="stat-number">
                        <?= number_format($counts['all']) ?>
                    </div>
                </div>

                <div class="stat-card waiting">
                    <div class="stat-label">Waiting</div>
                    <div class="stat-number">
                        <?= number_format($counts['waiting']) ?>
                    </div>
                </div>

                <div class="stat-card processing">
                    <div class="stat-label">Processing</div>
                    <div class="stat-number">
                        <?= number_format($counts['processing']) ?>
                    </div>
                </div>

                <div class="stat-card completed">
                    <div class="stat-label">Completed</div>
                    <div class="stat-number">
                        <?= number_format($counts['completed']) ?>
                    </div>
                </div>

                <div class="stat-card called">
                    <div class="stat-label">Called / Served</div>
                    <div class="stat-number">
                        <?= number_format($counts['called']) ?>
                    </div>
                </div>

            </div>

            <!-- =================================================
                 CURRENT QUEUE + WAITING
            ================================================== -->
            <div class="dashboard-grid">

                <!-- CURRENT QUEUE -->
                <div class="card">

                    <div class="card-header">
                        <h2>
                            <i class="fa-solid fa-bullhorn"></i>
                            Current Queue
                        </h2>

                        <a
                            href="queue.php?monitor=1"
                            target="_blank"
                            rel="noopener"
                            class="btn btn-success"
                            title="Open extension monitor"
                        >
                            <i class="fa-solid fa-tv"></i>
                            Monitor
                        </a>
                    </div>

                    <div class="card-body">

                        <?php if ($currentQueue): ?>

                            <div class="current-queue">

                                <div class="current-number">
                                    <?= e($currentQueue['queue_number']) ?>
                                </div>

                                <div class="current-name">
                                    <?= e($currentQueue['student_name']) ?>
                                </div>

                                <div class="current-service">
                                    <?= e($currentQueue['service']) ?>
                                </div>

                                <div class="queue-meta">

                                    <div class="meta-pill">
                                        <i class="fa-solid fa-user"></i>
                                        <?= e(
                                            $currentQueue['student_number'] !== ''
                                            ? $currentQueue['student_number']
                                            : 'No student number'
                                        ) ?>
                                    </div>

                                    <div class="meta-pill">
                                        <i class="fa-solid fa-desktop"></i>
                                        <?= e(
                                            $currentQueue['counter'] !== ''
                                            ? $currentQueue['counter']
                                            : 'Registrar Counter'
                                        ) ?>
                                    </div>

                                    <div class="meta-pill">
                                        <span class="badge <?= statusClass($currentQueue['status']) ?>">
                                            <?= e($currentQueue['status']) ?>
                                        </span>
                                    </div>

                                </div>

                                <div class="form-buttons" style="justify-content:center;">

                                    <?php if ($currentQueue['status'] === 'Called'): ?>

                                        <form method="POST">
                                            <input
                                                type="hidden"
                                                name="queue_id"
                                                value="<?= (int)$currentQueue['id'] ?>"
                                            >

                                            <button
                                                type="submit"
                                                name="start_processing"
                                                class="btn btn-warning"
                                            >
                                                <i class="fa-solid fa-play"></i>
                                                Start Processing
                                            </button>
                                        </form>

                                    <?php endif; ?>

                                    <?php if (
                                        $currentQueue['status'] === 'Called' ||
                                        $currentQueue['status'] === 'Processing'
                                    ): ?>

                                        <form method="POST">
                                            <input
                                                type="hidden"
                                                name="queue_id"
                                                value="<?= (int)$currentQueue['id'] ?>"
                                            >

                                            <button
                                                type="submit"
                                                name="complete_queue"
                                                class="btn btn-success"
                                            >
                                                <i class="fa-solid fa-check"></i>
                                                Complete
                                            </button>
                                        </form>

                                        <form method="POST">
                                            <input
                                                type="hidden"
                                                name="queue_id"
                                                value="<?= (int)$currentQueue['id'] ?>"
                                            >

                                            <button
                                                type="submit"
                                                name="recall_queue"
                                                class="btn btn-primary"
                                            >
                                                <i class="fa-solid fa-volume-high"></i>
                                                Recall
                                            </button>
                                        </form>

                                    <?php endif; ?>

                                </div>

                            </div>

                        <?php else: ?>

                            <div class="empty">
                                <i
                                    class="fa-solid fa-person-circle-check"
                                    style="font-size:35px; margin-bottom:12px;"
                                ></i>

                                <div>
                                    No student is currently being served.
                                </div>

                                <?php if (!empty($waitingQueue)): ?>
                                    <div style="margin-top:10px;">
                                        Click <strong>Call Next Student</strong>
                                        to call the next person.
                                    </div>
                                <?php endif; ?>
                            </div>

                        <?php endif; ?>

                    </div>
                </div>

                <!-- WAITING -->
                <div class="card">

                    <div class="card-header">
                        <h2>
                            <i class="fa-solid fa-clock"></i>
                            Waiting
                        </h2>

                        <span style="font-size:12px;color:#8994a0;">
                            <?= count($waitingQueue) ?> waiting
                        </span>
                    </div>

                    <div class="card-body">

                        <div class="waiting-list">

                            <?php if (!empty($waitingQueue)): ?>

                                <?php foreach ($waitingQueue as $index => $queue): ?>

                                    <div class="waiting-item">

                                        <div class="waiting-number">
                                            #<?= $index + 1 ?>
                                            <br>
                                            <span style="font-size:11px;">
                                                <?= e($queue['queue_number']) ?>
                                            </span>
                                        </div>

                                        <div class="waiting-info">
                                            <strong>
                                                <?= e($queue['student_name']) ?>
                                            </strong>

                                            <span>
                                                <?= e($queue['service']) ?>
                                            </span>
                                        </div>

                                    </div>

                                <?php endforeach; ?>

                            <?php else: ?>

                                <div class="empty">
                                    No students are currently waiting.
                                </div>

                            <?php endif; ?>

                        </div>

                    </div>
                </div>

            </div>

            <!-- =================================================
                 ADD TO QUEUE
            ================================================== -->
            <div class="card">

                <div class="card-header">
                    <h2>
                        <i class="fa-solid fa-user-plus"></i>
                        Add Student to Queue
                    </h2>
                </div>

                <div class="card-body">

                    <form method="POST">

                        <div class="form-grid">

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
                                <label>Registrar Service *</label>

                                <select
                                    name="service"
                                    class="form-control"
                                    required
                                >
                                    <option value="">
                                        -- Select Service --
                                    </option>

                                    <?php foreach ($services as $service): ?>

                                        <option value="<?= e($service) ?>">
                                            <?= e($service) ?>
                                        </option>

                                    <?php endforeach; ?>

                                </select>
                            </div>

                            <div class="form-group">
                                <label>Purpose</label>

                                <input
                                    type="text"
                                    name="purpose"
                                    class="form-control"
                                    placeholder="Optional purpose"
                                >
                            </div>

                        </div>

                        <div class="form-buttons">

                            <button
                                type="submit"
                                name="add_queue"
                                class="btn btn-primary"
                            >
                                <i class="fa-solid fa-plus"></i>
                                Add to Queue
                            </button>

                            <button
                                type="reset"
                                class="btn btn-light"
                            >
                                <i class="fa-solid fa-rotate-left"></i>
                                Clear
                            </button>

                        </div>

                    </form>

                </div>
            </div>

            <!-- =================================================
                 CALLED / SERVED STUDENTS
            ================================================== -->
            <div class="card table-card">

                <div class="card-header">
                    <h2>
                        <i class="fa-solid fa-users"></i>
                        Called / Served Students
                    </h2>
                </div>

                <form method="GET" class="filter-bar">

                    <input
                        type="text"
                        name="search"
                        class="form-control"
                        value="<?= e($search) ?>"
                        placeholder="Queue number, student number, student name, service..."
                    >

                    <select name="status" class="form-control">

                        <option value="">
                            All Statuses
                        </option>

                        <?php foreach (
                            ['Waiting','Called','Processing','Completed','Cancelled']
                            as $status
                        ): ?>

                            <option
                                value="<?= e($status) ?>"
                                <?= $statusFilter === $status ? 'selected' : '' ?>
                            >
                                <?= e($status) ?>
                            </option>

                        <?php endforeach; ?>

                    </select>

                    <button
                        type="submit"
                        class="btn btn-primary"
                    >
                        <i class="fa-solid fa-search"></i>
                        Search
                    </button>

                </form>

                <div class="table-wrapper">

                    <table>

                        <thead>

                            <tr>
                                <th>Queue No.</th>
                                <th>Student</th>
                                <th>Service</th>
                                <th>Counter</th>
                                <th>Status</th>
                                <th>Created</th>
                                <th>Actions</th>
                            </tr>

                        </thead>

                        <tbody>

                            <?php if (!empty($history)): ?>

                                <?php foreach ($history as $queue): ?>

                                    <tr>

                                        <td>
                                            <div class="queue-code">
                                                <?= e($queue['queue_number']) ?>
                                            </div>

                                            <?php if ($queue['student_number'] !== ''): ?>
                                                <small>
                                                    <?= e($queue['student_number']) ?>
                                                </small>
                                            <?php endif; ?>
                                        </td>

                                        <td>
                                            <div class="student-name">
                                                <?= e($queue['student_name']) ?>
                                            </div>
                                        </td>

                                        <td>
                                            <?= e($queue['service']) ?>
                                        </td>

                                        <td>
                                            <?= e(
                                                $queue['counter'] !== ''
                                                ? $queue['counter']
                                                : '—'
                                            ) ?>
                                        </td>

                                        <td>
                                            <span class="badge <?= statusClass($queue['status']) ?>">
                                                <?= e($queue['status']) ?>
                                            </span>
                                        </td>

                                        <td>
                                            <?= e(
                                                date(
                                                    'M d, Y h:i A',
                                                    strtotime($queue['created_at'])
                                                )
                                            ) ?>
                                        </td>

                                        <td>

                                            <div class="action-group">

                                                <?php if ($queue['status'] === 'Waiting'): ?>

                                                    <form method="POST">
                                                        <input
                                                            type="hidden"
                                                            name="queue_id"
                                                            value="<?= (int)$queue['id'] ?>"
                                                        >

                                                        <button
                                                            type="submit"
                                                            name="cancel_queue"
                                                            class="btn btn-danger btn-small"
                                                            onclick="return confirm('Cancel this queue entry?')"
                                                        >
                                                            Cancel
                                                        </button>
                                                    </form>

                                                <?php elseif ($queue['status'] === 'Called'): ?>

                                                    <form method="POST">
                                                        <input
                                                            type="hidden"
                                                            name="queue_id"
                                                            value="<?= (int)$queue['id'] ?>"
                                                        >

                                                        <button
                                                            type="submit"
                                                            name="start_processing"
                                                            class="btn btn-warning btn-small"
                                                        >
                                                            Process
                                                        </button>
                                                    </form>

                                                    <form method="POST">
                                                        <input
                                                            type="hidden"
                                                            name="queue_id"
                                                            value="<?= (int)$queue['id'] ?>"
                                                        >

                                                        <button
                                                            type="submit"
                                                            name="complete_queue"
                                                            class="btn btn-success btn-small"
                                                        >
                                                            Complete
                                                        </button>
                                                    </form>

                                                <?php elseif ($queue['status'] === 'Processing'): ?>

                                                    <form method="POST">
                                                        <input
                                                            type="hidden"
                                                            name="queue_id"
                                                            value="<?= (int)$queue['id'] ?>"
                                                        >

                                                        <button
                                                            type="submit"
                                                            name="complete_queue"
                                                            class="btn btn-success btn-small"
                                                        >
                                                            Complete
                                                        </button>
                                                    </form>

                                                    <form method="POST">
                                                        <input
                                                            type="hidden"
                                                            name="queue_id"
                                                            value="<?= (int)$queue['id'] ?>"
                                                        >

                                                        <button
                                                            type="submit"
                                                            name="recall_queue"
                                                            class="btn btn-primary btn-small"
                                                        >
                                                            Recall
                                                        </button>
                                                    </form>

                                                <?php elseif (
                                                    $queue['status'] === 'Completed' ||
                                                    $queue['status'] === 'Cancelled'
                                                ): ?>

                                                    <form method="POST">
                                                        <input
                                                            type="hidden"
                                                            name="queue_id"
                                                            value="<?= (int)$queue['id'] ?>"
                                                        >

                                                        <button
                                                            type="submit"
                                                            name="delete_queue"
                                                            class="btn btn-danger btn-small"
                                                            onclick="return confirm('Delete this queue record?')"
                                                        >
                                                            Delete
                                                        </button>
                                                    </form>

                                                <?php endif; ?>

                                            </div>

                                        </td>

                                    </tr>

                                <?php endforeach; ?>

                            <?php else: ?>

                                <tr>
                                    <td colspan="7" class="no-data">
                                        No queue records found.
                                    </td>
                                </tr>

                            <?php endif; ?>

                        </tbody>

                    </table>

                </div>
            </div>

        </section>

    </main>

</div>


<script>
function toggleSidebar() {
    const sidebar = document.getElementById('sidebar');
    if (sidebar) sidebar.classList.toggle('show');
}
</script>
</body>
</html>

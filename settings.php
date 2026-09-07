<?php
session_start();

/*
=========================================================
    NORSU REGISTRAR SYSTEM
    SYSTEM SETTINGS

    File Name:
    settings.php

    Database:
    haha

    FEATURES:
    - School Information
    - Campuses
    - School Years
    - Semesters
    - Programs
    - Subjects
    - Document Types
    - Registrar Settings
=========================================================
*/

mysqli_report(MYSQLI_REPORT_OFF);
$conn = new mysqli("localhost", "root", "", "haha");
if ($conn->connect_error) die("Database connection failed: " . $conn->connect_error);
$conn->set_charset("utf8mb4");

/* Tables. Existing tables are not modified; settings uses its own
   management tables so it will not conflict with your programs.php schema. */
$conn->query("CREATE TABLE IF NOT EXISTS school_information (
 id INT AUTO_INCREMENT PRIMARY KEY, school_name VARCHAR(255) NOT NULL,
 school_code VARCHAR(100) DEFAULT '', address TEXT, contact_number VARCHAR(100) DEFAULT '',
 email VARCHAR(255) DEFAULT '', website VARCHAR(255) DEFAULT '', president_name VARCHAR(255) DEFAULT '',
 registrar_name VARCHAR(255) DEFAULT '', logo VARCHAR(255) DEFAULT '', updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
$conn->query("CREATE TABLE IF NOT EXISTS setting_campuses (
 id INT AUTO_INCREMENT PRIMARY KEY, campus_name VARCHAR(255) NOT NULL, campus_code VARCHAR(100) DEFAULT '',
 address TEXT, contact_number VARCHAR(100) DEFAULT '', status ENUM('Active','Inactive') DEFAULT 'Active', created_at DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
$conn->query("CREATE TABLE IF NOT EXISTS setting_school_years (
 id INT AUTO_INCREMENT PRIMARY KEY, school_year VARCHAR(100) NOT NULL UNIQUE, start_date DATE NULL, end_date DATE NULL,
 status ENUM('Active','Inactive','Closed') DEFAULT 'Inactive', created_at DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
$conn->query("CREATE TABLE IF NOT EXISTS setting_semesters (
 id INT AUTO_INCREMENT PRIMARY KEY, semester_name VARCHAR(100) NOT NULL, semester_code VARCHAR(50) DEFAULT '',
 description TEXT, status ENUM('Active','Inactive') DEFAULT 'Active', created_at DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
$conn->query("CREATE TABLE IF NOT EXISTS setting_programs (
 id INT AUTO_INCREMENT PRIMARY KEY, program_code VARCHAR(100) NOT NULL, program_name VARCHAR(255) NOT NULL,
 department VARCHAR(255) DEFAULT '', degree_level VARCHAR(100) DEFAULT '', duration VARCHAR(100) DEFAULT '',
 status ENUM('Active','Inactive') DEFAULT 'Active', created_at DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
$conn->query("CREATE TABLE IF NOT EXISTS setting_subjects (
 id INT AUTO_INCREMENT PRIMARY KEY, subject_code VARCHAR(100) NOT NULL, subject_name VARCHAR(255) NOT NULL,
 units DECIMAL(5,2) DEFAULT 0, department VARCHAR(255) DEFAULT '', description TEXT,
 status ENUM('Active','Inactive') DEFAULT 'Active', created_at DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
$conn->query("CREATE TABLE IF NOT EXISTS setting_document_types (
 id INT AUTO_INCREMENT PRIMARY KEY, document_name VARCHAR(255) NOT NULL, document_code VARCHAR(100) DEFAULT '',
 description TEXT, processing_days INT DEFAULT 1, fee DECIMAL(10,2) DEFAULT 0, status ENUM('Active','Inactive') DEFAULT 'Active', created_at DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
$conn->query("CREATE TABLE IF NOT EXISTS registrar_settings (
 id INT AUTO_INCREMENT PRIMARY KEY, setting_key VARCHAR(150) NOT NULL UNIQUE, setting_value TEXT,
 setting_description TEXT, updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

function e($v){ return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }
function go($anchor=''){ header('Location: settings.php'.($anchor ? '#'.$anchor : '')); exit; }
function flash($type,$msg){ $_SESSION['settings_flash_type']=$type; $_SESSION['settings_flash']=$msg; }
function getFlash(){ $x=['type'=>'','message'=>'']; if(isset($_SESSION['settings_flash_type'])){$x['type']=$_SESSION['settings_flash_type'];unset($_SESSION['settings_flash_type']);} if(isset($_SESSION['settings_flash'])){$x['message']=$_SESSION['settings_flash'];unset($_SESSION['settings_flash']);} return $x; }

/* Defaults */
if (($r=$conn->query("SELECT COUNT(*) c FROM school_information")) && (int)$r->fetch_assoc()['c']===0) {
 $conn->query("INSERT INTO school_information (school_name,school_code) VALUES ('Negros Oriental State University','NORSU')");
}
if (($r=$conn->query("SELECT COUNT(*) c FROM setting_semesters")) && (int)$r->fetch_assoc()['c']===0) {
 $conn->query("INSERT INTO setting_semesters (semester_name,semester_code,description) VALUES ('1st Semester','1ST','First Semester'),('2nd Semester','2ND','Second Semester'),('Summer','SUMMER','Summer Term')");
}
$defaults=[
 ['office_name','Office of the University Registrar','Name of the registrar office'],
 ['office_email','','Official registrar email'],['office_contact','','Official registrar contact number'],
 ['office_address','','Registrar office address'],['document_processing_days','3','Default processing days'],
 ['document_release_time','8:00 AM - 5:00 PM','Document release schedule'],['office_hours','8:00 AM - 5:00 PM','Registrar office working hours'],
 ['allow_online_requests','Yes','Allow online document requests'],['allow_online_enrollment','Yes','Allow online enrollment'],
 ['system_name','NORSU Registrar System','System display name']
];
foreach($defaults as $d){$st=$conn->prepare("INSERT IGNORE INTO registrar_settings(setting_key,setting_value,setting_description) VALUES(?,?,?)");if($st){$st->bind_param('sss',$d[0],$d[1],$d[2]);$st->execute();$st->close();}}

if($_SERVER['REQUEST_METHOD']==='POST'){
 $a=$_POST['action']??'';
 if($a==='school'){
  $id=(int)($_POST['id']??0); $v=[trim($_POST['school_name']??''),trim($_POST['school_code']??''),trim($_POST['address']??''),trim($_POST['contact_number']??''),trim($_POST['email']??''),trim($_POST['website']??''),trim($_POST['president_name']??''),trim($_POST['registrar_name']??'')];
  if($v[0]===''){flash('error','School name is required.');go('school-information');}
  if($id){$st=$conn->prepare("UPDATE school_information SET school_name=?,school_code=?,address=?,contact_number=?,email=?,website=?,president_name=?,registrar_name=? WHERE id=?");$st->bind_param('ssssssssi',$v[0],$v[1],$v[2],$v[3],$v[4],$v[5],$v[6],$v[7],$id);}else{$st=$conn->prepare("INSERT INTO school_information(school_name,school_code,address,contact_number,email,website,president_name,registrar_name) VALUES(?,?,?,?,?,?,?,?)");$st->bind_param('ssssssss',$v[0],$v[1],$v[2],$v[3],$v[4],$v[5],$v[6],$v[7]);} $st->execute();$st->close();flash('success','School information saved successfully.');go('school-information');
 }
 if(in_array($a,['campus','school_year','semester','program','subject','document'],true)){
  $id=(int)($_POST['id']??0); $edit=$id>0;
  if($a==='campus'){$name=trim($_POST['name']??'');$code=trim($_POST['code']??'');$addr=trim($_POST['address']??'');$contact=trim($_POST['contact']??'');$status=$_POST['status']??'Active';$table='setting_campuses';$anchor='campuses';if($name===''){flash('error','Campus name is required.');go($anchor);} if($edit){$st=$conn->prepare("UPDATE setting_campuses SET campus_name=?,campus_code=?,address=?,contact_number=?,status=? WHERE id=?");$st->bind_param('sssssi',$name,$code,$addr,$contact,$status,$id);}else{$st=$conn->prepare("INSERT INTO setting_campuses(campus_name,campus_code,address,contact_number,status) VALUES(?,?,?,?,?)");$st->bind_param('sssss',$name,$code,$addr,$contact,$status);}}
  elseif($a==='school_year'){$name=trim($_POST['name']??'');$start=$_POST['start_date']?:null;$end=$_POST['end_date']?:null;$status=$_POST['status']??'Inactive';$anchor='school-years';if($name===''){flash('error','School year is required.');go($anchor);}if($edit){$st=$conn->prepare("UPDATE setting_school_years SET school_year=?,start_date=?,end_date=?,status=? WHERE id=?");$st->bind_param('ssssi',$name,$start,$end,$status,$id);}else{$st=$conn->prepare("INSERT INTO setting_school_years(school_year,start_date,end_date,status) VALUES(?,?,?,?)");$st->bind_param('ssss',$name,$start,$end,$status);}}
  elseif($a==='semester'){$name=trim($_POST['name']??'');$code=trim($_POST['code']??'');$desc=trim($_POST['description']??'');$status=$_POST['status']??'Active';$anchor='semesters';if($name===''){flash('error','Semester name is required.');go($anchor);}if($edit){$st=$conn->prepare("UPDATE setting_semesters SET semester_name=?,semester_code=?,description=?,status=? WHERE id=?");$st->bind_param('ssssi',$name,$code,$desc,$status,$id);}else{$st=$conn->prepare("INSERT INTO setting_semesters(semester_name,semester_code,description,status) VALUES(?,?,?,?)");$st->bind_param('ssss',$name,$code,$desc,$status);}}
  elseif($a==='program'){$code=trim($_POST['code']??'');$name=trim($_POST['name']??'');$dept=trim($_POST['department']??'');$degree=trim($_POST['degree']??'');$duration=trim($_POST['duration']??'');$status=$_POST['status']??'Active';$anchor='programs';if($code===''||$name===''){flash('error','Program code and program name are required.');go($anchor);}if($edit){$st=$conn->prepare("UPDATE setting_programs SET program_code=?,program_name=?,department=?,degree_level=?,duration=?,status=? WHERE id=?");$st->bind_param('ssssssi',$code,$name,$dept,$degree,$duration,$status,$id);}else{$st=$conn->prepare("INSERT INTO setting_programs(program_code,program_name,department,degree_level,duration,status) VALUES(?,?,?,?,?,?)");$st->bind_param('ssssss',$code,$name,$dept,$degree,$duration,$status);}}
  elseif($a==='subject'){$code=trim($_POST['code']??'');$name=trim($_POST['name']??'');$units=(float)($_POST['units']??0);$dept=trim($_POST['department']??'');$desc=trim($_POST['description']??'');$status=$_POST['status']??'Active';$anchor='subjects';if($code===''||$name===''){flash('error','Subject code and subject name are required.');go($anchor);}if($edit){$st=$conn->prepare("UPDATE setting_subjects SET subject_code=?,subject_name=?,units=?,department=?,description=?,status=? WHERE id=?");$st->bind_param('ssdsssi',$code,$name,$units,$dept,$desc,$status,$id);}else{$st=$conn->prepare("INSERT INTO setting_subjects(subject_code,subject_name,units,department,description,status) VALUES(?,?,?,?,?,?)");$st->bind_param('ssdsss',$code,$name,$units,$dept,$desc,$status);}}
  else{$name=trim($_POST['name']??'');$code=trim($_POST['code']??'');$desc=trim($_POST['description']??'');$days=max(1,(int)($_POST['processing_days']??1));$fee=max(0,(float)($_POST['fee']??0));$status=$_POST['status']??'Active';$anchor='document-types';if($name===''){flash('error','Document name is required.');go($anchor);}if($edit){$st=$conn->prepare("UPDATE setting_document_types SET document_name=?,document_code=?,description=?,processing_days=?,fee=?,status=? WHERE id=?");$st->bind_param('sssidsi',$name,$code,$desc,$days,$fee,$status,$id);}else{$st=$conn->prepare("INSERT INTO setting_document_types(document_name,document_code,description,processing_days,fee,status) VALUES(?,?,?,?,?,?)");$st->bind_param('sssids',$name,$code,$desc,$days,$fee,$status);}}
  if($st){$ok=$st->execute();$st->close();flash($ok?'success':'error',$ok?($edit?'Record updated successfully.':'Record added successfully.'):'Unable to save the record.');}go($anchor);
 }
 if($a==='registrar'){
  foreach(($_POST['setting']??[]) as $key=>$value){$st=$conn->prepare("UPDATE registrar_settings SET setting_value=? WHERE setting_key=?");if($st){$value=trim($value);$st->bind_param('ss',$value,$key);$st->execute();$st->close();}}
  flash('success','Registrar settings saved successfully.');go('registrar-settings');
 }
 if($a==='delete'){
  $map=['campus'=>['setting_campuses','campuses'],'school_year'=>['setting_school_years','school-years'],'semester'=>['setting_semesters','semesters'],'program'=>['setting_programs','programs'],'subject'=>['setting_subjects','subjects'],'document'=>['setting_document_types','document-types']];
  $type=$_POST['type']??'';$id=(int)($_POST['id']??0);if(isset($map[$type])&&$id>0){$st=$conn->prepare("DELETE FROM `{$map[$type][0]}` WHERE id=?");$st->bind_param('i',$id);$st->execute();$st->close();flash('success','Record deleted successfully.');go($map[$type][1]);}
 }
}

$school=$conn->query("SELECT * FROM school_information ORDER BY id LIMIT 1")->fetch_assoc() ?: [];
function rows($conn,$table,$order){$out=[];$r=$conn->query("SELECT * FROM `$table` ORDER BY $order");if($r)while($x=$r->fetch_assoc())$out[]=$x;return $out;}
$campuses=rows($conn,'setting_campuses','campus_name');
$schoolYears=rows($conn,'setting_school_years','school_year DESC');
$semesters=rows($conn,'setting_semesters','id ASC');
$programs=rows($conn,'setting_programs','program_name');
$subjects=rows($conn,'setting_subjects','subject_code');
$documents=rows($conn,'setting_document_types','document_name');
$reg=[];$r=$conn->query("SELECT * FROM registrar_settings ORDER BY id");if($r)while($x=$r->fetch_assoc())$reg[$x['setting_key']]=$x;
$editType=$_GET['edit_type']??'';$editId=(int)($_GET['edit_id']??0);$edit=null;
$editMap=['campus'=>'setting_campuses','school_year'=>'setting_school_years','semester'=>'setting_semesters','program'=>'setting_programs','subject'=>'setting_subjects','document'=>'setting_document_types'];
if($editId&&isset($editMap[$editType])){$st=$conn->prepare("SELECT * FROM `{$editMap[$editType]}` WHERE id=?");$st->bind_param('i',$editId);$st->execute();$edit=$st->get_result()->fetch_assoc();$st->close();}
$flash=getFlash();
$adminName=$_SESSION['admin_name']??$_SESSION['username']??'Administrator';
?>
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

    --green: #198754;
    --dark-green: #146c43;
    --muted: #6c757d;
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
   BUTTON / STATUS / SETTINGS LINK FIXES
===================================================== */

.btn-green { background: var(--green); color: var(--white); }
.btn-green:hover { background: var(--dark-green); color: var(--white); }
.btn-red { background: var(--red); color: var(--white); }
.btn-red:hover { background: var(--dark-red); color: var(--white); }
.btn-yellow { background: var(--yellow); color: #222; }
.btn-yellow:hover { background: #e0a800; color: #222; }
.btn-small { padding: 7px 10px !important; font-size: 12px !important; }

.status-badge {
    display:inline-block; padding:5px 10px; border-radius:20px;
    font-size:11px; font-weight:bold; white-space:nowrap;
}
.status-active { background:#d1e7dd; color:#0f5132; }
.status-inactive { background:#e2e3e5; color:#41464b; }

.settings-links {
    display:grid; grid-template-columns:repeat(4,1fr); gap:12px;
}
.settings-links a {
    display:block; padding:13px 15px; background:var(--light-blue);
    color:var(--dark-blue); border:1px solid #d8e7f7;
    border-left:4px solid var(--blue); border-radius:8px;
    text-decoration:none; font-size:13px; font-weight:600; transition:.2s;
}
.settings-links a:hover {
    background:var(--blue); color:var(--white);
    border-left-color:var(--yellow); transform:translateY(-1px);
}
a.btn { cursor:pointer; }

@media (max-width:1000px) {
    .settings-links { grid-template-columns:repeat(2,1fr); }
}
@media (max-width:600px) {
    .settings-links { grid-template-columns:1fr; }
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

<body>

<aside class="sidebar" id="sidebar">
    <div class="sidebar-header">
        <?php if(file_exists(__DIR__.'/norsu.png')): ?><img src="norsu.png" alt="NORSU Logo" class="sidebar-logo"><?php else: ?><div class="sidebar-logo" style="display:flex;align-items:center;justify-content:center;background:#0057b8;color:white;font-weight:bold;font-size:20px;">NORSU</div><?php endif; ?>
        <h2>NORSU</h2><p>REGISTRAR SYSTEM</p>
    </div>
    <nav class="sidebar-menu">
        <div class="menu-title">Main</div>
        <a href="admin_dashboard.php"><span class="icon">🏠</span>Dashboard</a>
        <a href="students.php"><span class="icon">👨‍🎓</span>Students</a>
        <a href="enrollment.php"><span class="icon">📝</span>Enrollment</a>
        <a href="programs.php"><span class="icon">📚</span>Programs</a>
        <a href="grades.php"><span class="icon">📊</span>Grades</a>
        <div class="menu-title">Registrar Services</div>
        <a href="document_requests.php"><span class="icon">📄</span>Document Requests</a>
        <a href="queue.php"><span class="icon">🎫</span>Queue</a>
        <a href="graduation.php"><span class="icon">🎓</span>Graduation</a>
        <div class="menu-title">Administration</div>
        <a href="reports.php"><span class="icon">📈</span>Reports</a>
        <a href="users.php"><span class="icon">👥</span>Users</a>
        <a href="settings.php" class="active"><span class="icon">⚙️</span>Settings</a>
        <a href="admin_logout.php"><span class="icon">🚪</span>Logout</a>
    </nav>
</aside>

<main class="main">
<header class="topbar">
    <div class="topbar-left">
        <button class="menu-toggle" onclick="toggleSidebar()" type="button">☰</button>
        <?php if(file_exists(__DIR__.'/norsu.png')): ?><img src="norsu.png" alt="NORSU Logo" class="topbar-logo"><?php endif; ?>
        <h1>System Settings</h1>
    </div>
    <div class="admin-info">
        <div class="admin-details"><strong><?=e($adminName)?></strong><span>NORSU Registrar</span></div>
        <div class="admin-avatar"><?=e(strtoupper(substr($adminName,0,1)))?></div>
    </div>
</header>

<section class="content">
    <div class="page-header">
        <div><div class="page-title">⚙️ System Settings</div><div class="page-subtitle">Manage school information, campuses, academic settings, programs, subjects, documents, and registrar settings.</div></div>
    </div>

    <?php if($flash['message']!==''): ?><div class="alert <?=$flash['type']==='success'?'alert-success':'alert-error'?>"><?= $flash['type']==='success'?'✅':'❌' ?> <?=e($flash['message'])?></div><?php endif; ?>

    <div class="stats">
        <div class="stat-card"><div class="stat-label">Campuses</div><div class="stat-number"><?=count($campuses)?></div></div>
        <div class="stat-card"><div class="stat-label">School Years</div><div class="stat-number"><?=count($schoolYears)?></div></div>
        <div class="stat-card"><div class="stat-label">Semesters</div><div class="stat-number"><?=count($semesters)?></div></div>
        <div class="stat-card"><div class="stat-label">Programs</div><div class="stat-number"><?=count($programs)?></div></div>
        <div class="stat-card"><div class="stat-label">Subjects</div><div class="stat-number"><?=count($subjects)?></div></div>
        <div class="stat-card"><div class="stat-label">Document Types</div><div class="stat-number"><?=count($documents)?></div></div>
    </div>

    <div class="card">
        <div class="card-header"><h2>Settings Categories</h2></div>
        <div class="card-body settings-links">
            <a href="#school-information">🏫 School Information</a><a href="#campuses">📍 Campuses</a><a href="#school-years">📅 School Years</a><a href="#semesters">📚 Semesters</a>
            <a href="#programs">🎓 Programs</a><a href="#subjects">📖 Subjects</a><a href="#document-types">📄 Document Types</a><a href="#registrar-settings">⚙️ Registrar Settings</a>
        </div>
    </div>

    <div class="card" id="school-information">
        <div class="card-header"><h2>🏫 School Information</h2></div><div class="card-body">
        <form method="post"><input type="hidden" name="action" value="school"><input type="hidden" name="id" value="<?=e($school['id']??0)?>">
        <div class="form-grid">
            <div class="form-group"><label>School Name *</label><input class="form-control" name="school_name" required value="<?=e($school['school_name']??'')?>"></div>
            <div class="form-group"><label>School Code</label><input class="form-control" name="school_code" value="<?=e($school['school_code']??'')?>"></div>
            <div class="form-group full"><label>Address</label><textarea class="form-control" name="address"><?=e($school['address']??'')?></textarea></div>
            <div class="form-group"><label>Contact Number</label><input class="form-control" name="contact_number" value="<?=e($school['contact_number']??'')?>"></div>
            <div class="form-group"><label>Email</label><input type="email" class="form-control" name="email" value="<?=e($school['email']??'')?>"></div>
            <div class="form-group"><label>Website</label><input class="form-control" name="website" value="<?=e($school['website']??'')?>"></div>
            <div class="form-group"><label>University President</label><input class="form-control" name="president_name" value="<?=e($school['president_name']??'')?>"></div>
            <div class="form-group"><label>University Registrar</label><input class="form-control" name="registrar_name" value="<?=e($school['registrar_name']??'')?>"></div>
        </div><div class="form-buttons"><button class="btn btn-green" type="submit">💾 Save School Information</button></div></form>
        </div>
    </div>

<?php
function formTop($type,$edit,$anchor){$id=(int)($edit['id']??0);echo '<form method="post"><input type="hidden" name="action" value="'.e($type).'"><input type="hidden" name="id" value="'.$id.'">';}
function formBottom($edit,$anchor){echo '<div class="form-buttons"><button class="btn btn-green" type="submit">'.($edit?'✏️ Update':'➕ Add').' </button>'.($edit?'<a class="btn btn-gray" href="settings.php#'.e($anchor).'">Cancel</a>':'').'</div></form>';}
function badge($status){return '<span class="status-badge '.($status==='Active'?'status-active':'status-inactive').'">'.e($status).'</span>';}
function delForm($type,$id,$anchor){return '<form method="post" style="display:inline" onsubmit="return confirm(\'Delete this record?\');"><input type="hidden" name="action" value="delete"><input type="hidden" name="type" value="'.e($type).'"><input type="hidden" name="id" value="'.(int)$id.'"><button type="submit" class="btn btn-red btn-small">🗑️ Delete</button></form>';} 
?>

    <div class="card" id="campuses"><div class="card-header"><h2>📍 Campuses</h2></div><div class="card-body">
    <?php formTop('campus',$editType==='campus'?$edit:null,'campuses'); ?>
    <div class="form-grid"><div class="form-group"><label>Campus Name *</label><input class="form-control" name="name" required value="<?=e($editType==='campus'?($edit['campus_name']??''):'')?>"></div><div class="form-group"><label>Campus Code</label><input class="form-control" name="code" value="<?=e($editType==='campus'?($edit['campus_code']??''):'')?>"></div><div class="form-group full"><label>Address</label><textarea class="form-control" name="address"><?=e($editType==='campus'?($edit['address']??''):'')?></textarea></div><div class="form-group"><label>Contact Number</label><input class="form-control" name="contact" value="<?=e($editType==='campus'?($edit['contact_number']??''):'')?>"></div><div class="form-group"><label>Status</label><select class="form-control" name="status"><option <?=($editType==='campus'&&($edit['status']??'')==='Inactive')?'':'selected'?>>Active</option><option value="Inactive" <?=($editType==='campus'&&($edit['status']??'')==='Inactive')?'selected':''?>>Inactive</option></select></div></div><?php formBottom($editType==='campus'&&$edit,'campuses'); ?>
    <div class="table-container"><table><thead><tr><th>#</th><th>Campus Name</th><th>Code</th><th>Address</th><th>Contact</th><th>Status</th><th>Actions</th></tr></thead><tbody><?php foreach($campuses as $i=>$x):?><tr><td><?=$i+1?></td><td><strong><?=e($x['campus_name'])?></strong></td><td><?=e($x['campus_code'])?></td><td><?=e($x['address'])?></td><td><?=e($x['contact_number'])?></td><td><?=badge($x['status'])?></td><td><a class="btn btn-yellow btn-small" href="?edit_type=campus&edit_id=<?=$x['id']?>#campuses">Edit</a> <?=delForm('campus',$x['id'],'campuses')?></td></tr><?php endforeach;if(!$campuses):?><tr><td colspan="7" class="empty">No campuses found.</td></tr><?php endif;?></tbody></table></div></div></div>

    <div class="card" id="school-years"><div class="card-header"><h2>📅 School Years</h2></div><div class="card-body">
    <?php formTop('school_year',$editType==='school_year'?$edit:null,'school-years'); ?><div class="form-grid"><div class="form-group"><label>School Year *</label><input class="form-control" name="name" required placeholder="2026-2027" value="<?=e($editType==='school_year'?($edit['school_year']??''):'')?>"></div><div class="form-group"><label>Status</label><select class="form-control" name="status"><?php $s=$editType==='school_year'?($edit['status']??'Inactive'):'Inactive';?><option value="Active" <?=$s==='Active'?'selected':''?>>Active</option><option value="Inactive" <?=$s==='Inactive'?'selected':''?>>Inactive</option><option value="Closed" <?=$s==='Closed'?'selected':''?>>Closed</option></select></div><div class="form-group"><label>Start Date</label><input type="date" class="form-control" name="start_date" value="<?=e($editType==='school_year'?($edit['start_date']??''):'')?>"></div><div class="form-group"><label>End Date</label><input type="date" class="form-control" name="end_date" value="<?=e($editType==='school_year'?($edit['end_date']??''):'')?>"></div></div><?php formBottom($editType==='school_year'&&$edit,'school-years'); ?>
    <div class="table-container"><table><thead><tr><th>#</th><th>School Year</th><th>Start</th><th>End</th><th>Status</th><th>Actions</th></tr></thead><tbody><?php foreach($schoolYears as $i=>$x):?><tr><td><?=$i+1?></td><td><strong><?=e($x['school_year'])?></strong></td><td><?=e($x['start_date']?:'-')?></td><td><?=e($x['end_date']?:'-')?></td><td><?=badge($x['status'])?></td><td><a class="btn btn-yellow btn-small" href="?edit_type=school_year&edit_id=<?=$x['id']?>#school-years">Edit</a> <?=delForm('school_year',$x['id'],'school-years')?></td></tr><?php endforeach;if(!$schoolYears):?><tr><td colspan="6" class="empty">No school years found.</td></tr><?php endif;?></tbody></table></div></div></div>

    <div class="card" id="semesters"><div class="card-header"><h2>📚 Semesters</h2></div><div class="card-body">
    <?php formTop('semester',$editType==='semester'?$edit:null,'semesters'); ?><div class="form-grid"><div class="form-group"><label>Semester Name *</label><input class="form-control" name="name" required value="<?=e($editType==='semester'?($edit['semester_name']??''):'')?>"></div><div class="form-group"><label>Semester Code</label><input class="form-control" name="code" value="<?=e($editType==='semester'?($edit['semester_code']??''):'')?>"></div><div class="form-group full"><label>Description</label><textarea class="form-control" name="description"><?=e($editType==='semester'?($edit['description']??''):'')?></textarea></div><div class="form-group"><label>Status</label><select class="form-control" name="status"><option value="Active">Active</option><option value="Inactive" <?=($editType==='semester'&&($edit['status']??'')==='Inactive')?'selected':''?>>Inactive</option></select></div></div><?php formBottom($editType==='semester'&&$edit,'semesters'); ?>
    <div class="table-container"><table><thead><tr><th>#</th><th>Semester</th><th>Code</th><th>Description</th><th>Status</th><th>Actions</th></tr></thead><tbody><?php foreach($semesters as $i=>$x):?><tr><td><?=$i+1?></td><td><strong><?=e($x['semester_name'])?></strong></td><td><?=e($x['semester_code'])?></td><td><?=e($x['description'])?></td><td><?=badge($x['status'])?></td><td><a class="btn btn-yellow btn-small" href="?edit_type=semester&edit_id=<?=$x['id']?>#semesters">Edit</a> <?=delForm('semester',$x['id'],'semesters')?></td></tr><?php endforeach;?></tbody></table></div></div></div>

    <div class="card" id="programs"><div class="card-header"><h2>🎓 Programs</h2></div><div class="card-body">
    <?php formTop('program',$editType==='program'?$edit:null,'programs'); ?><div class="form-grid"><div class="form-group"><label>Program Code *</label><input class="form-control" name="code" required value="<?=e($editType==='program'?($edit['program_code']??''):'')?>"></div><div class="form-group"><label>Program Name *</label><input class="form-control" name="name" required value="<?=e($editType==='program'?($edit['program_name']??''):'')?>"></div><div class="form-group"><label>Department</label><input class="form-control" name="department" value="<?=e($editType==='program'?($edit['department']??''):'')?>"></div><div class="form-group"><label>Degree Level</label><select class="form-control" name="degree"><option value="">Select Degree Level</option><?php foreach(['Certificate','Diploma','Bachelor','Master','Doctorate'] as $d):?><option <?=($editType==='program'&&($edit['degree_level']??'')===$d)?'selected':''?>><?=e($d)?></option><?php endforeach;?></select></div><div class="form-group"><label>Duration</label><input class="form-control" name="duration" placeholder="4 Years" value="<?=e($editType==='program'?($edit['duration']??''):'')?>"></div><div class="form-group"><label>Status</label><select class="form-control" name="status"><option value="Active">Active</option><option value="Inactive" <?=($editType==='program'&&($edit['status']??'')==='Inactive')?'selected':''?>>Inactive</option></select></div></div><?php formBottom($editType==='program'&&$edit,'programs'); ?>
    <div class="table-container"><table><thead><tr><th>#</th><th>Code</th><th>Program</th><th>Department</th><th>Degree</th><th>Duration</th><th>Status</th><th>Actions</th></tr></thead><tbody><?php foreach($programs as $i=>$x):?><tr><td><?=$i+1?></td><td><strong><?=e($x['program_code'])?></strong></td><td><?=e($x['program_name'])?></td><td><?=e($x['department'])?></td><td><?=e($x['degree_level'])?></td><td><?=e($x['duration'])?></td><td><?=badge($x['status'])?></td><td><a class="btn btn-yellow btn-small" href="?edit_type=program&edit_id=<?=$x['id']?>#programs">Edit</a> <?=delForm('program',$x['id'],'programs')?></td></tr><?php endforeach;?></tbody></table></div></div></div>

    <div class="card" id="subjects"><div class="card-header"><h2>📖 Subjects</h2></div><div class="card-body">
    <?php formTop('subject',$editType==='subject'?$edit:null,'subjects'); ?><div class="form-grid"><div class="form-group"><label>Subject Code *</label><input class="form-control" name="code" required value="<?=e($editType==='subject'?($edit['subject_code']??''):'')?>"></div><div class="form-group"><label>Subject Name *</label><input class="form-control" name="name" required value="<?=e($editType==='subject'?($edit['subject_name']??''):'')?>"></div><div class="form-group"><label>Units</label><input type="number" step="0.5" min="0" class="form-control" name="units" value="<?=e($editType==='subject'?($edit['units']??0):0)?>"></div><div class="form-group"><label>Department</label><input class="form-control" name="department" value="<?=e($editType==='subject'?($edit['department']??''):'')?>"></div><div class="form-group full"><label>Description</label><textarea class="form-control" name="description"><?=e($editType==='subject'?($edit['description']??''):'')?></textarea></div><div class="form-group"><label>Status</label><select class="form-control" name="status"><option value="Active">Active</option><option value="Inactive" <?=($editType==='subject'&&($edit['status']??'')==='Inactive')?'selected':''?>>Inactive</option></select></div></div><?php formBottom($editType==='subject'&&$edit,'subjects'); ?>
    <div class="table-container"><table><thead><tr><th>#</th><th>Code</th><th>Subject</th><th>Units</th><th>Department</th><th>Status</th><th>Actions</th></tr></thead><tbody><?php foreach($subjects as $i=>$x):?><tr><td><?=$i+1?></td><td><strong><?=e($x['subject_code'])?></strong></td><td><?=e($x['subject_name'])?></td><td><?=e($x['units'])?></td><td><?=e($x['department'])?></td><td><?=badge($x['status'])?></td><td><a class="btn btn-yellow btn-small" href="?edit_type=subject&edit_id=<?=$x['id']?>#subjects">Edit</a> <?=delForm('subject',$x['id'],'subjects')?></td></tr><?php endforeach;?></tbody></table></div></div></div>

    <div class="card" id="document-types"><div class="card-header"><h2>📄 Document Types</h2></div><div class="card-body">
    <?php formTop('document',$editType==='document'?$edit:null,'document-types'); ?><div class="form-grid"><div class="form-group"><label>Document Name *</label><input class="form-control" name="name" required value="<?=e($editType==='document'?($edit['document_name']??''):'')?>"></div><div class="form-group"><label>Document Code</label><input class="form-control" name="code" value="<?=e($editType==='document'?($edit['document_code']??''):'')?>"></div><div class="form-group"><label>Processing Days</label><input type="number" min="1" class="form-control" name="processing_days" value="<?=e($editType==='document'?($edit['processing_days']??1):1)?>"></div><div class="form-group"><label>Fee</label><input type="number" min="0" step="0.01" class="form-control" name="fee" value="<?=e($editType==='document'?($edit['fee']??0):0)?>"></div><div class="form-group"><label>Status</label><select class="form-control" name="status"><option value="Active">Active</option><option value="Inactive" <?=($editType==='document'&&($edit['status']??'')==='Inactive')?'selected':''?>>Inactive</option></select></div><div class="form-group full"><label>Description</label><textarea class="form-control" name="description"><?=e($editType==='document'?($edit['description']??''):'')?></textarea></div></div><?php formBottom($editType==='document'&&$edit,'document-types'); ?>
    <div class="table-container"><table><thead><tr><th>#</th><th>Document</th><th>Code</th><th>Processing</th><th>Fee</th><th>Status</th><th>Actions</th></tr></thead><tbody><?php foreach($documents as $i=>$x):?><tr><td><?=$i+1?></td><td><strong><?=e($x['document_name'])?></strong></td><td><?=e($x['document_code'])?></td><td><?=e($x['processing_days'])?> day(s)</td><td>₱<?=number_format((float)$x['fee'],2)?></td><td><?=badge($x['status'])?></td><td><a class="btn btn-yellow btn-small" href="?edit_type=document&edit_id=<?=$x['id']?>#document-types">Edit</a> <?=delForm('document',$x['id'],'document-types')?></td></tr><?php endforeach;?></tbody></table></div></div></div>

    <div class="card" id="registrar-settings"><div class="card-header"><h2>⚙️ Registrar Settings</h2></div><div class="card-body"><form method="post"><input type="hidden" name="action" value="registrar"><div class="form-grid">
    <?php foreach($reg as $key=>$x): ?><div class="form-group"><label><?=e(ucwords(str_replace('_',' ',$key)))?></label><?php $val=$x['setting_value']??'';if(in_array($key,['allow_online_requests','allow_online_enrollment'],true)):?><select class="form-control" name="setting[<?=e($key)?>]"><option <?= $val==='Yes'?'selected':''?>>Yes</option><option <?= $val==='No'?'selected':''?>>No</option></select><?php elseif($key==='document_processing_days'):?><input type="number" min="1" class="form-control" name="setting[<?=e($key)?>]" value="<?=e($val)?>"><?php elseif($key==='office_email'):?><input type="email" class="form-control" name="setting[<?=e($key)?>]" value="<?=e($val)?>"><?php else:?><input class="form-control" name="setting[<?=e($key)?>]" value="<?=e($val)?>"><?php endif;?><small><?=e($x['setting_description']??'')?></small></div><?php endforeach; ?></div><div class="form-buttons"><button class="btn btn-green" type="submit">💾 Save Registrar Settings</button></div></form></div></div>

</section></main>
<script>function toggleSidebar(){document.getElementById('sidebar').classList.toggle('show');}setTimeout(function(){var a=document.querySelector('.alert');if(a){a.style.transition='opacity .5s';a.style.opacity='0';setTimeout(function(){a.remove()},500)}},5000);</script>
</body></html>

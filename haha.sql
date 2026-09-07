-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Sep 07, 2026 at 07:35 AM
-- Server version: 10.4.32-MariaDB
-- PHP Version: 8.2.12

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `haha`
--

-- --------------------------------------------------------

--
-- Table structure for table `academic_records`
--

CREATE TABLE `academic_records` (
  `id` int(10) UNSIGNED NOT NULL,
  `student_id` varchar(50) NOT NULL,
  `subject_code` varchar(50) NOT NULL,
  `subject_name` varchar(150) NOT NULL,
  `units` decimal(5,2) DEFAULT 0.00,
  `grade` varchar(20) DEFAULT NULL,
  `school_year` varchar(30) DEFAULT NULL,
  `semester` varchar(50) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `admins`
--

CREATE TABLE `admins` (
  `id` int(11) NOT NULL,
  `username` varchar(100) NOT NULL,
  `password` varchar(255) NOT NULL,
  `full_name` varchar(150) NOT NULL,
  `email` varchar(150) DEFAULT NULL,
  `status` enum('Active','Inactive') DEFAULT 'Active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `admins`
--

INSERT INTO `admins` (`id`, `username`, `password`, `full_name`, `email`, `status`, `created_at`) VALUES
(1, 'admin', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC3mXG0bG0J8M6lP6e', 'System Administrator', 'admin@norsu.edu.ph', 'Active', '2026-09-06 03:30:31');

-- --------------------------------------------------------

--
-- Table structure for table `admin_activity_logs`
--

CREATE TABLE `admin_activity_logs` (
  `id` int(11) NOT NULL,
  `admin_id` int(11) DEFAULT NULL,
  `action` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `campuses`
--

CREATE TABLE `campuses` (
  `id` int(11) NOT NULL,
  `campus_name` varchar(255) NOT NULL,
  `campus_code` varchar(100) DEFAULT '',
  `address` text DEFAULT NULL,
  `contact_number` varchar(100) DEFAULT '',
  `status` enum('Active','Inactive') DEFAULT 'Active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `departments`
--

CREATE TABLE `departments` (
  `id` int(10) UNSIGNED NOT NULL,
  `department_code` varchar(30) NOT NULL,
  `department_name` varchar(150) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `code` varchar(50) NOT NULL DEFAULT '',
  `name` varchar(255) NOT NULL DEFAULT ''
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `departments`
--

INSERT INTO `departments` (`id`, `department_code`, `department_name`, `created_at`, `code`, `name`) VALUES
(4, 'CIT', 'College of Information Technology', '2026-09-06 07:37:26', '', ''),
(10, 'CTED', 'College of Teacher Education', '2026-09-06 07:39:36', '', '');

-- --------------------------------------------------------

--
-- Table structure for table `document_requests`
--

CREATE TABLE `document_requests` (
  `id` int(11) NOT NULL,
  `student_id` int(11) DEFAULT NULL,
  `student_name` varchar(150) NOT NULL,
  `document_type` varchar(150) NOT NULL,
  `purpose` varchar(255) DEFAULT NULL,
  `status` enum('Pending','Approved','Rejected','Completed') DEFAULT 'Pending',
  `request_date` timestamp NOT NULL DEFAULT current_timestamp(),
  `processed_date` timestamp NULL DEFAULT NULL,
  `remarks` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `document_request_history`
--

CREATE TABLE `document_request_history` (
  `id` int(11) NOT NULL,
  `request_id` int(11) NOT NULL,
  `request_number` varchar(50) NOT NULL,
  `action` varchar(100) NOT NULL,
  `old_status` varchar(50) DEFAULT '',
  `new_status` varchar(50) DEFAULT '',
  `remarks` text DEFAULT NULL,
  `action_by` varchar(255) DEFAULT '',
  `action_date` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `document_types`
--

CREATE TABLE `document_types` (
  `id` int(11) NOT NULL,
  `document_name` varchar(255) NOT NULL,
  `document_code` varchar(100) DEFAULT '',
  `description` text DEFAULT NULL,
  `processing_days` int(11) DEFAULT 1,
  `fee` decimal(10,2) DEFAULT 0.00,
  `status` enum('Active','Inactive') DEFAULT 'Active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `enrollments`
--

CREATE TABLE `enrollments` (
  `id` int(10) UNSIGNED NOT NULL,
  `student_id` varchar(50) NOT NULL,
  `school_year` varchar(30) NOT NULL DEFAULT '2025-2026',
  `semester` varchar(50) NOT NULL DEFAULT '1st Semester',
  `program` varchar(150) DEFAULT NULL,
  `year_level` varchar(50) DEFAULT NULL,
  `status` varchar(50) NOT NULL DEFAULT 'Pending',
  `enrollment_date` date DEFAULT NULL,
  `approved_date` date DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `enrollments_new`
--

CREATE TABLE `enrollments_new` (
  `id` int(10) UNSIGNED NOT NULL,
  `student_id` varchar(50) NOT NULL,
  `school_year` varchar(30) NOT NULL DEFAULT '2025-2026',
  `semester` varchar(50) NOT NULL DEFAULT '1st Semester',
  `program` varchar(150) DEFAULT NULL,
  `year_level` varchar(50) DEFAULT NULL,
  `status` varchar(50) NOT NULL DEFAULT 'Pending',
  `enrollment_date` date DEFAULT NULL,
  `approved_date` date DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `enrollment_history`
--

CREATE TABLE `enrollment_history` (
  `id` int(10) UNSIGNED NOT NULL,
  `student_id` varchar(50) NOT NULL,
  `school_year` varchar(30) NOT NULL,
  `semester` varchar(50) NOT NULL,
  `program` varchar(150) DEFAULT NULL,
  `year_level` varchar(50) DEFAULT NULL,
  `status` varchar(50) DEFAULT 'Enrolled',
  `enrollment_date` date DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `enrollment_subjects`
--

CREATE TABLE `enrollment_subjects` (
  `id` int(10) UNSIGNED NOT NULL,
  `enrollment_id` int(10) UNSIGNED NOT NULL,
  `student_id` varchar(50) NOT NULL,
  `subject_code` varchar(50) NOT NULL,
  `subject_name` varchar(150) NOT NULL,
  `units` decimal(5,2) NOT NULL DEFAULT 0.00,
  `instructor` varchar(150) DEFAULT NULL,
  `schedule` varchar(150) DEFAULT NULL,
  `room` varchar(100) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `grades`
--

CREATE TABLE `grades` (
  `id` int(10) UNSIGNED NOT NULL,
  `student_number` varchar(50) NOT NULL,
  `student_name` varchar(200) NOT NULL,
  `program_code` varchar(100) DEFAULT NULL,
  `subject_code` varchar(50) NOT NULL,
  `subject_name` varchar(200) NOT NULL,
  `units` decimal(4,1) NOT NULL DEFAULT 3.0,
  `school_year` varchar(30) NOT NULL,
  `semester` varchar(50) NOT NULL,
  `prelim` decimal(5,2) DEFAULT NULL,
  `midterm` decimal(5,2) DEFAULT NULL,
  `final` decimal(5,2) DEFAULT NULL,
  `final_grade` decimal(5,2) DEFAULT NULL,
  `remarks` varchar(50) DEFAULT NULL,
  `instructor` varchar(150) DEFAULT NULL,
  `status` enum('Encoded','Corrected','Incomplete') NOT NULL DEFAULT 'Encoded',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `grade_corrections`
--

CREATE TABLE `grade_corrections` (
  `id` int(10) UNSIGNED NOT NULL,
  `grade_id` int(10) UNSIGNED NOT NULL,
  `student_number` varchar(50) NOT NULL,
  `student_name` varchar(200) NOT NULL,
  `subject_code` varchar(50) NOT NULL,
  `old_grade` decimal(5,2) DEFAULT NULL,
  `new_grade` decimal(5,2) DEFAULT NULL,
  `reason` text NOT NULL,
  `corrected_by` varchar(150) NOT NULL DEFAULT 'Administrator',
  `corrected_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `graduating_students`
--

CREATE TABLE `graduating_students` (
  `id` int(11) NOT NULL,
  `student_id` int(11) NOT NULL,
  `academic_year` varchar(50) DEFAULT NULL,
  `semester` varchar(50) DEFAULT NULL,
  `status` enum('Pending','Qualified','Approved','Graduated') DEFAULT 'Pending',
  `graduation_date` date DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `graduation_records`
--

CREATE TABLE `graduation_records` (
  `id` int(11) NOT NULL,
  `application_no` varchar(50) NOT NULL,
  `student_number` varchar(100) NOT NULL,
  `student_name` varchar(255) NOT NULL,
  `program` varchar(255) NOT NULL,
  `year_level` varchar(100) DEFAULT '',
  `graduation_year` varchar(20) NOT NULL,
  `graduation_term` varchar(100) DEFAULT '',
  `application_date` date NOT NULL,
  `status` enum('Applicant','Graduating','Approved','Rejected','Not Graduating') NOT NULL DEFAULT 'Applicant',
  `requirements_status` enum('Incomplete','Complete') NOT NULL DEFAULT 'Incomplete',
  `tor_status` tinyint(1) NOT NULL DEFAULT 0,
  `clearance_status` tinyint(1) NOT NULL DEFAULT 0,
  `grades_status` tinyint(1) NOT NULL DEFAULT 0,
  `financial_status` tinyint(1) NOT NULL DEFAULT 0,
  `graduation_status` tinyint(1) NOT NULL DEFAULT 0,
  `remarks` varchar(1000) DEFAULT '',
  `approved_date` date DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `login_activity`
--

CREATE TABLE `login_activity` (
  `id` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `username` varchar(100) NOT NULL DEFAULT '',
  `activity_type` varchar(50) NOT NULL DEFAULT 'Login',
  `activity_date` datetime NOT NULL DEFAULT current_timestamp(),
  `ip_address` varchar(45) NOT NULL DEFAULT '',
  `user_agent` varchar(500) NOT NULL DEFAULT '',
  `details` varchar(500) DEFAULT ''
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `permissions`
--

CREATE TABLE `permissions` (
  `id` int(11) NOT NULL,
  `permission_key` varchar(100) NOT NULL,
  `permission_name` varchar(150) NOT NULL,
  `permission_group` varchar(100) NOT NULL DEFAULT 'General',
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `permissions`
--

INSERT INTO `permissions` (`id`, `permission_key`, `permission_name`, `permission_group`, `created_at`) VALUES
(1, 'dashboard.view', 'View Dashboard', 'Dashboard', '2026-09-06 20:12:17'),
(2, 'students.view', 'View Students', 'Students', '2026-09-06 20:12:17'),
(3, 'students.manage', 'Manage Students', 'Students', '2026-09-06 20:12:17'),
(4, 'enrollment.view', 'View Enrollment', 'Enrollment', '2026-09-06 20:12:17'),
(5, 'enrollment.manage', 'Manage Enrollment', 'Enrollment', '2026-09-06 20:12:17'),
(6, 'programs.view', 'View Programs', 'Programs', '2026-09-06 20:12:17'),
(7, 'programs.manage', 'Manage Programs', 'Programs', '2026-09-06 20:12:17'),
(8, 'grades.view', 'View Grades', 'Grades', '2026-09-06 20:12:17'),
(9, 'grades.manage', 'Manage Grades', 'Grades', '2026-09-06 20:12:17'),
(10, 'documents.view', 'View Document Requests', 'Registrar Services', '2026-09-06 20:12:17'),
(11, 'documents.manage', 'Manage Document Requests', 'Registrar Services', '2026-09-06 20:12:17'),
(12, 'queue.manage', 'Manage Queue', 'Registrar Services', '2026-09-06 20:12:17'),
(13, 'graduation.manage', 'Manage Graduation', 'Registrar Services', '2026-09-06 20:12:17'),
(14, 'reports.view', 'View Reports', 'Administration', '2026-09-06 20:12:17'),
(15, 'users.view', 'View Users', 'Administration', '2026-09-06 20:12:17'),
(16, 'users.manage', 'Manage Users', 'Administration', '2026-09-06 20:12:17'),
(17, 'settings.manage', 'Manage Settings', 'Administration', '2026-09-06 20:12:17');

-- --------------------------------------------------------

--
-- Table structure for table `programs`
--

CREATE TABLE `programs` (
  `id` int(11) NOT NULL,
  `program_code` varchar(50) NOT NULL,
  `program_name` varchar(200) NOT NULL,
  `department` varchar(150) DEFAULT NULL,
  `duration_years` int(11) DEFAULT 4,
  `status` enum('Active','Inactive') DEFAULT 'Active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `department_id` int(10) UNSIGNED DEFAULT NULL,
  `duration` varchar(50) DEFAULT NULL,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `queue`
--

CREATE TABLE `queue` (
  `id` int(11) NOT NULL,
  `student_id` int(11) DEFAULT NULL,
  `student_name` varchar(150) NOT NULL,
  `service` varchar(150) NOT NULL,
  `queue_number` int(11) NOT NULL,
  `status` enum('Waiting','Serving','Completed','Cancelled') DEFAULT 'Waiting',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `registrar_monitor_layout`
--

CREATE TABLE `registrar_monitor_layout` (
  `id` tinyint(3) UNSIGNED NOT NULL,
  `background_color` varchar(20) NOT NULL DEFAULT '#f4f6f9',
  `header_background` varchar(20) NOT NULL DEFAULT '#ffffff',
  `panel_background` varchar(20) NOT NULL DEFAULT '#ffffff',
  `queue_color` varchar(20) NOT NULL DEFAULT '#0057b8',
  `text_color` varchar(20) NOT NULL DEFAULT '#003b7a',
  `panel_width` tinyint(3) UNSIGNED NOT NULL DEFAULT 96,
  `panel_height` tinyint(3) UNSIGNED NOT NULL DEFAULT 78,
  `panel_radius` tinyint(3) UNSIGNED NOT NULL DEFAULT 28,
  `queue_size` tinyint(3) UNSIGNED NOT NULL DEFAULT 15,
  `student_size` tinyint(3) UNSIGNED NOT NULL DEFAULT 42,
  `show_header` tinyint(1) NOT NULL DEFAULT 1,
  `show_footer` tinyint(1) NOT NULL DEFAULT 1,
  `office_name` varchar(150) NOT NULL DEFAULT 'NORSU Registrar Office',
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `registrar_monitor_layout`
--

INSERT INTO `registrar_monitor_layout` (`id`, `background_color`, `header_background`, `panel_background`, `queue_color`, `text_color`, `panel_width`, `panel_height`, `panel_radius`, `queue_size`, `student_size`, `show_header`, `show_footer`, `office_name`, `updated_at`) VALUES
(1, '#f4f6f9', '#ffffff', '#ffffff', '#0057b8', '#003b7a', 96, 78, 28, 15, 42, 1, 1, 'NORSU Registrar Office', '2026-09-06 14:28:04');

-- --------------------------------------------------------

--
-- Table structure for table `registrar_queue`
--

CREATE TABLE `registrar_queue` (
  `id` int(11) NOT NULL,
  `queue_number` varchar(30) NOT NULL,
  `student_number` varchar(100) DEFAULT '',
  `student_name` varchar(255) NOT NULL,
  `service` varchar(150) NOT NULL,
  `purpose` varchar(500) DEFAULT '',
  `status` enum('Waiting','Called','Processing','Completed','Cancelled') NOT NULL DEFAULT 'Waiting',
  `counter` varchar(100) DEFAULT '',
  `called_at` datetime DEFAULT NULL,
  `processing_at` datetime DEFAULT NULL,
  `completed_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `registrar_settings`
--

CREATE TABLE `registrar_settings` (
  `id` int(11) NOT NULL,
  `setting_key` varchar(150) NOT NULL,
  `setting_value` text DEFAULT NULL,
  `setting_description` text DEFAULT NULL,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `registrar_settings`
--

INSERT INTO `registrar_settings` (`id`, `setting_key`, `setting_value`, `setting_description`, `updated_at`) VALUES
(1, 'office_name', 'Office of the University Registrar', 'Name of the registrar office', '2026-09-06 12:53:17'),
(2, 'office_email', '', 'Official registrar email', '2026-09-06 12:53:17'),
(3, 'office_contact', '', 'Official registrar contact number', '2026-09-06 12:53:17'),
(4, 'office_address', '', 'Registrar office address', '2026-09-06 12:53:17'),
(5, 'document_processing_days', '3', 'Default processing days for document requests', '2026-09-06 12:53:17'),
(6, 'document_release_time', '8:00 AM - 5:00 PM', 'Document release schedule', '2026-09-06 12:53:17'),
(7, 'office_hours', '8:00 AM - 5:00 PM', 'Registrar office working hours', '2026-09-06 12:53:17'),
(8, 'allow_online_requests', 'Yes', 'Allow students to submit online document requests', '2026-09-06 12:53:17'),
(9, 'allow_online_enrollment', 'Yes', 'Allow online enrollment', '2026-09-06 12:53:17'),
(10, 'system_name', 'NORSU Registrar System', 'System display name', '2026-09-06 12:53:17');

-- --------------------------------------------------------

--
-- Table structure for table `school_information`
--

CREATE TABLE `school_information` (
  `id` int(11) NOT NULL,
  `school_name` varchar(255) NOT NULL,
  `school_code` varchar(100) DEFAULT '',
  `address` text DEFAULT NULL,
  `contact_number` varchar(100) DEFAULT '',
  `email` varchar(255) DEFAULT '',
  `website` varchar(255) DEFAULT '',
  `president_name` varchar(255) DEFAULT '',
  `registrar_name` varchar(255) DEFAULT '',
  `logo` varchar(255) DEFAULT '',
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `school_information`
--

INSERT INTO `school_information` (`id`, `school_name`, `school_code`, `address`, `contact_number`, `email`, `website`, `president_name`, `registrar_name`, `logo`, `updated_at`) VALUES
(1, 'Negros Oriental State University', 'NORSU', 'Cadre, Poblacion Guihulngan City, Negros Oriental', '', '', '', '', '', '', '2026-09-06 14:19:04');

-- --------------------------------------------------------

--
-- Table structure for table `school_years`
--

CREATE TABLE `school_years` (
  `id` int(11) NOT NULL,
  `school_year` varchar(100) NOT NULL,
  `start_date` date DEFAULT NULL,
  `end_date` date DEFAULT NULL,
  `status` enum('Active','Inactive','Closed') DEFAULT 'Inactive',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `semesters`
--

CREATE TABLE `semesters` (
  `id` int(11) NOT NULL,
  `semester_name` varchar(100) NOT NULL,
  `semester_code` varchar(50) DEFAULT '',
  `description` text DEFAULT NULL,
  `status` enum('Active','Inactive') DEFAULT 'Active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `semesters`
--

INSERT INTO `semesters` (`id`, `semester_name`, `semester_code`, `description`, `status`, `created_at`) VALUES
(1, '1st Semester', '1ST', 'First Semester', 'Active', '2026-09-06 12:53:17'),
(2, '2nd Semester', '2ND', 'Second Semester', 'Active', '2026-09-06 12:53:17'),
(3, 'Summer', 'SUMMER', 'Summer Term', 'Active', '2026-09-06 12:53:17');

-- --------------------------------------------------------

--
-- Table structure for table `setting_campuses`
--

CREATE TABLE `setting_campuses` (
  `id` int(11) NOT NULL,
  `campus_name` varchar(255) NOT NULL,
  `campus_code` varchar(100) DEFAULT '',
  `address` text DEFAULT NULL,
  `contact_number` varchar(100) DEFAULT '',
  `status` enum('Active','Inactive') DEFAULT 'Active',
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `setting_document_types`
--

CREATE TABLE `setting_document_types` (
  `id` int(11) NOT NULL,
  `document_name` varchar(255) NOT NULL,
  `document_code` varchar(100) DEFAULT '',
  `description` text DEFAULT NULL,
  `processing_days` int(11) DEFAULT 1,
  `fee` decimal(10,2) DEFAULT 0.00,
  `status` enum('Active','Inactive') DEFAULT 'Active',
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `setting_programs`
--

CREATE TABLE `setting_programs` (
  `id` int(11) NOT NULL,
  `program_code` varchar(100) NOT NULL,
  `program_name` varchar(255) NOT NULL,
  `department` varchar(255) DEFAULT '',
  `degree_level` varchar(100) DEFAULT '',
  `duration` varchar(100) DEFAULT '',
  `status` enum('Active','Inactive') DEFAULT 'Active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `setting_school_years`
--

CREATE TABLE `setting_school_years` (
  `id` int(11) NOT NULL,
  `school_year` varchar(100) NOT NULL,
  `start_date` date DEFAULT NULL,
  `end_date` date DEFAULT NULL,
  `status` enum('Active','Inactive','Closed') DEFAULT 'Inactive',
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `setting_semesters`
--

CREATE TABLE `setting_semesters` (
  `id` int(11) NOT NULL,
  `semester_name` varchar(100) NOT NULL,
  `semester_code` varchar(50) DEFAULT '',
  `description` text DEFAULT NULL,
  `status` enum('Active','Inactive') DEFAULT 'Active',
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `setting_semesters`
--

INSERT INTO `setting_semesters` (`id`, `semester_name`, `semester_code`, `description`, `status`, `created_at`) VALUES
(1, '1st Semester', '1ST', 'First Semester', 'Active', '2026-09-06 20:57:52'),
(2, '2nd Semester', '2ND', 'Second Semester', 'Active', '2026-09-06 20:57:52'),
(3, 'Summer', 'SUMMER', 'Summer Term', 'Active', '2026-09-06 20:57:52');

-- --------------------------------------------------------

--
-- Table structure for table `setting_subjects`
--

CREATE TABLE `setting_subjects` (
  `id` int(11) NOT NULL,
  `subject_code` varchar(100) NOT NULL,
  `subject_name` varchar(255) NOT NULL,
  `units` decimal(5,2) DEFAULT 0.00,
  `department` varchar(255) DEFAULT '',
  `description` text DEFAULT NULL,
  `status` enum('Active','Inactive') DEFAULT 'Active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `students`
--

CREATE TABLE `students` (
  `id` int(11) NOT NULL,
  `student_id` varchar(50) NOT NULL,
  `student_name` varchar(150) NOT NULL,
  `first_name` varchar(100) DEFAULT NULL,
  `middle_name` varchar(100) DEFAULT NULL,
  `last_name` varchar(100) DEFAULT NULL,
  `email` varchar(150) DEFAULT NULL,
  `contact_number` varchar(30) DEFAULT NULL,
  `address` varchar(255) DEFAULT NULL,
  `program_id` int(11) DEFAULT NULL,
  `year_level` varchar(50) DEFAULT NULL,
  `gender` varchar(30) DEFAULT NULL,
  `birthdate` date DEFAULT NULL,
  `status` enum('Active','Inactive','Graduated') DEFAULT 'Active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `subjects`
--

CREATE TABLE `subjects` (
  `id` int(10) UNSIGNED NOT NULL,
  `subject_code` varchar(50) NOT NULL,
  `subject_name` varchar(200) NOT NULL,
  `units` decimal(4,1) NOT NULL DEFAULT 3.0,
  `program_id` int(10) UNSIGNED DEFAULT NULL,
  `year_level_id` int(10) UNSIGNED DEFAULT NULL,
  `semester` varchar(50) DEFAULT NULL,
  `instructor` varchar(150) DEFAULT NULL,
  `schedule` varchar(150) DEFAULT NULL,
  `room` varchar(100) DEFAULT NULL,
  `status` enum('Active','Inactive') NOT NULL DEFAULT 'Active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `school_year` varchar(100) DEFAULT ''
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int(10) UNSIGNED NOT NULL,
  `username` varchar(100) DEFAULT NULL,
  `full_name` varchar(150) NOT NULL,
  `email` varchar(150) NOT NULL,
  `password` varchar(255) NOT NULL,
  `role` enum('admin','student') NOT NULL DEFAULT 'student',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `contact_number` varchar(50) DEFAULT '',
  `status` varchar(30) NOT NULL DEFAULT 'Active',
  `last_login` datetime DEFAULT NULL,
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `username`, `full_name`, `email`, `password`, `role`, `created_at`, `contact_number`, `status`, `last_login`, `updated_at`) VALUES
(1, 'admin', 'NORSU Administrator', 'admin@carcuevas.com', '$2y$10$X8b4EW4bKuGVHfFsQT/4ieOP5n.Amz4j4jamNAnCS9/oeznTA/.dG', 'admin', '2026-09-06 06:12:52', '', 'Active', NULL, '2026-09-06 20:15:56');

-- --------------------------------------------------------

--
-- Table structure for table `user_permissions`
--

CREATE TABLE `user_permissions` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `permission_id` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `year_levels`
--

CREATE TABLE `year_levels` (
  `id` int(10) UNSIGNED NOT NULL,
  `year_level` varchar(100) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `name` varchar(100) NOT NULL DEFAULT '',
  `year_name` varchar(100) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `year_levels`
--

INSERT INTO `year_levels` (`id`, `year_level`, `created_at`, `name`, `year_name`) VALUES
(2, '2nd Year', '2026-09-06 07:18:36', '', NULL),
(3, '3rd Year', '2026-09-06 07:18:36', '', NULL),
(4, '4th Year', '2026-09-06 07:18:36', '', NULL),
(5, '5th Year', '2026-09-06 07:37:26', '', NULL),
(6, '1st Year', '2026-09-06 07:39:46', '', NULL);

--
-- Indexes for dumped tables
--

--
-- Indexes for table `academic_records`
--
ALTER TABLE `academic_records`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `admins`
--
ALTER TABLE `admins`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `username` (`username`);

--
-- Indexes for table `admin_activity_logs`
--
ALTER TABLE `admin_activity_logs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_activity_admin` (`admin_id`);

--
-- Indexes for table `campuses`
--
ALTER TABLE `campuses`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `departments`
--
ALTER TABLE `departments`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `department_code` (`department_code`);

--
-- Indexes for table `document_requests`
--
ALTER TABLE `document_requests`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_document_student` (`student_id`);

--
-- Indexes for table `document_request_history`
--
ALTER TABLE `document_request_history`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_request_id` (`request_id`),
  ADD KEY `idx_request_number` (`request_number`),
  ADD KEY `idx_action_date` (`action_date`);

--
-- Indexes for table `document_types`
--
ALTER TABLE `document_types`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `enrollments`
--
ALTER TABLE `enrollments`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_student_id` (`student_id`),
  ADD KEY `idx_school_year` (`school_year`),
  ADD KEY `idx_semester` (`semester`),
  ADD KEY `idx_status` (`status`);

--
-- Indexes for table `enrollments_new`
--
ALTER TABLE `enrollments_new`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `enrollment_history`
--
ALTER TABLE `enrollment_history`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `enrollment_subjects`
--
ALTER TABLE `enrollment_subjects`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_enrollment_id` (`enrollment_id`),
  ADD KEY `idx_subject_student_id` (`student_id`);

--
-- Indexes for table `grades`
--
ALTER TABLE `grades`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_student_number` (`student_number`),
  ADD KEY `idx_subject_code` (`subject_code`),
  ADD KEY `idx_school_year` (`school_year`);

--
-- Indexes for table `grade_corrections`
--
ALTER TABLE `grade_corrections`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_grade_id` (`grade_id`),
  ADD KEY `idx_student_number` (`student_number`);

--
-- Indexes for table `graduating_students`
--
ALTER TABLE `graduating_students`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_graduating_student` (`student_id`);

--
-- Indexes for table `graduation_records`
--
ALTER TABLE `graduation_records`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `application_no` (`application_no`),
  ADD KEY `idx_student_number` (`student_number`),
  ADD KEY `idx_status` (`status`),
  ADD KEY `idx_grad_year` (`graduation_year`),
  ADD KEY `idx_requirements` (`requirements_status`);

--
-- Indexes for table `login_activity`
--
ALTER TABLE `login_activity`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_user_id` (`user_id`),
  ADD KEY `idx_activity_date` (`activity_date`),
  ADD KEY `idx_activity_type` (`activity_type`);

--
-- Indexes for table `permissions`
--
ALTER TABLE `permissions`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `permission_key` (`permission_key`);

--
-- Indexes for table `programs`
--
ALTER TABLE `programs`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `program_code` (`program_code`);

--
-- Indexes for table `queue`
--
ALTER TABLE `queue`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_queue_student` (`student_id`);

--
-- Indexes for table `registrar_monitor_layout`
--
ALTER TABLE `registrar_monitor_layout`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `registrar_queue`
--
ALTER TABLE `registrar_queue`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `queue_number` (`queue_number`),
  ADD KEY `idx_queue_number` (`queue_number`),
  ADD KEY `idx_student_number` (`student_number`),
  ADD KEY `idx_status` (`status`),
  ADD KEY `idx_created_at` (`created_at`);

--
-- Indexes for table `registrar_settings`
--
ALTER TABLE `registrar_settings`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `setting_key` (`setting_key`);

--
-- Indexes for table `school_information`
--
ALTER TABLE `school_information`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `school_years`
--
ALTER TABLE `school_years`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `school_year` (`school_year`);

--
-- Indexes for table `semesters`
--
ALTER TABLE `semesters`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `setting_campuses`
--
ALTER TABLE `setting_campuses`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `setting_document_types`
--
ALTER TABLE `setting_document_types`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `setting_programs`
--
ALTER TABLE `setting_programs`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `setting_school_years`
--
ALTER TABLE `setting_school_years`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `school_year` (`school_year`);

--
-- Indexes for table `setting_semesters`
--
ALTER TABLE `setting_semesters`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `setting_subjects`
--
ALTER TABLE `setting_subjects`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `students`
--
ALTER TABLE `students`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `student_id` (`student_id`),
  ADD KEY `fk_students_program` (`program_id`);

--
-- Indexes for table `subjects`
--
ALTER TABLE `subjects`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `subject_code` (`subject_code`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `email` (`email`),
  ADD UNIQUE KEY `username` (`username`);

--
-- Indexes for table `user_permissions`
--
ALTER TABLE `user_permissions`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_user_permission` (`user_id`,`permission_id`),
  ADD KEY `idx_user_id` (`user_id`),
  ADD KEY `idx_permission_id` (`permission_id`);

--
-- Indexes for table `year_levels`
--
ALTER TABLE `year_levels`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `year_level` (`year_level`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `academic_records`
--
ALTER TABLE `academic_records`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `admins`
--
ALTER TABLE `admins`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `admin_activity_logs`
--
ALTER TABLE `admin_activity_logs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `campuses`
--
ALTER TABLE `campuses`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `departments`
--
ALTER TABLE `departments`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=24;

--
-- AUTO_INCREMENT for table `document_requests`
--
ALTER TABLE `document_requests`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `document_request_history`
--
ALTER TABLE `document_request_history`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `document_types`
--
ALTER TABLE `document_types`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `enrollments`
--
ALTER TABLE `enrollments`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `enrollments_new`
--
ALTER TABLE `enrollments_new`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `enrollment_history`
--
ALTER TABLE `enrollment_history`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `enrollment_subjects`
--
ALTER TABLE `enrollment_subjects`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `grades`
--
ALTER TABLE `grades`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `grade_corrections`
--
ALTER TABLE `grade_corrections`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `graduating_students`
--
ALTER TABLE `graduating_students`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `graduation_records`
--
ALTER TABLE `graduation_records`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `login_activity`
--
ALTER TABLE `login_activity`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `permissions`
--
ALTER TABLE `permissions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=596;

--
-- AUTO_INCREMENT for table `programs`
--
ALTER TABLE `programs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- AUTO_INCREMENT for table `queue`
--
ALTER TABLE `queue`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `registrar_queue`
--
ALTER TABLE `registrar_queue`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT for table `registrar_settings`
--
ALTER TABLE `registrar_settings`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=331;

--
-- AUTO_INCREMENT for table `school_information`
--
ALTER TABLE `school_information`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `school_years`
--
ALTER TABLE `school_years`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `semesters`
--
ALTER TABLE `semesters`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `setting_campuses`
--
ALTER TABLE `setting_campuses`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `setting_document_types`
--
ALTER TABLE `setting_document_types`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `setting_programs`
--
ALTER TABLE `setting_programs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `setting_school_years`
--
ALTER TABLE `setting_school_years`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `setting_semesters`
--
ALTER TABLE `setting_semesters`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `setting_subjects`
--
ALTER TABLE `setting_subjects`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `students`
--
ALTER TABLE `students`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `subjects`
--
ALTER TABLE `subjects`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `user_permissions`
--
ALTER TABLE `user_permissions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `year_levels`
--
ALTER TABLE `year_levels`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=25;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `admin_activity_logs`
--
ALTER TABLE `admin_activity_logs`
  ADD CONSTRAINT `fk_activity_admin` FOREIGN KEY (`admin_id`) REFERENCES `admins` (`id`) ON DELETE SET NULL ON UPDATE CASCADE;

--
-- Constraints for table `document_requests`
--
ALTER TABLE `document_requests`
  ADD CONSTRAINT `fk_document_student` FOREIGN KEY (`student_id`) REFERENCES `students` (`id`) ON DELETE SET NULL ON UPDATE CASCADE;

--
-- Constraints for table `graduating_students`
--
ALTER TABLE `graduating_students`
  ADD CONSTRAINT `fk_graduating_student` FOREIGN KEY (`student_id`) REFERENCES `students` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `queue`
--
ALTER TABLE `queue`
  ADD CONSTRAINT `fk_queue_student` FOREIGN KEY (`student_id`) REFERENCES `students` (`id`) ON DELETE SET NULL ON UPDATE CASCADE;

--
-- Constraints for table `students`
--
ALTER TABLE `students`
  ADD CONSTRAINT `fk_students_program` FOREIGN KEY (`program_id`) REFERENCES `programs` (`id`) ON DELETE SET NULL ON UPDATE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;

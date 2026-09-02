-- phpMyAdmin SQL Dump
-- version 5.2.0
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: 01 أبريل 2026 الساعة 15:30
-- إصدار الخادم: 10.4.27-MariaDB
-- PHP Version: 8.1.12

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `ctms_db1`
--

-- --------------------------------------------------------

--
-- بنية الجدول `activity_log`
--

CREATE TABLE `activity_log` (
  `id` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `user_type` enum('student','supervisor','coordinator','training_entity') DEFAULT NULL,
  `action` varchar(100) NOT NULL,
  `description` text DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- إرجاع أو استيراد بيانات الجدول `activity_log`
--

INSERT INTO `activity_log` (`id`, `user_id`, `user_type`, `action`, `description`, `ip_address`, `user_agent`, `created_at`) VALUES
(1, 1, 'student', 'login', 'تسجيل دخول', '192.168.1.1', NULL, '2026-03-04 00:51:58'),
(2, 1, 'student', 'apply', 'تقديم طلب', '192.168.1.1', NULL, '2026-03-04 00:51:58'),
(3, 16, 'supervisor', 'login', 'تسجيل دخول', '192.168.1.2', NULL, '2026-03-04 00:51:58'),
(4, 16, 'supervisor', 'evaluate', 'تقييم متدربة', '192.168.1.2', NULL, '2026-03-04 00:51:58'),
(5, 19, 'coordinator', 'approve_entity', 'اعتماد جهة', '192.168.1.3', NULL, '2026-03-04 00:51:58'),
(6, 1, 'student', 'login', 'تسجيل دخول', NULL, NULL, '2026-03-04 00:56:38'),
(7, 1, 'student', 'login', 'تسجيل دخول', NULL, NULL, '2026-03-04 20:38:36'),
(8, 1, 'student', 'logout', 'تسجيل خروج', NULL, NULL, '2026-03-04 21:08:59'),
(9, 1, 'student', 'login', 'تسجيل دخول', NULL, NULL, '2026-03-04 21:14:24'),
(10, 1, 'student', 'logout', 'تسجيل خروج', NULL, NULL, '2026-03-04 21:28:20'),
(11, 16, 'supervisor', 'login', 'تسجيل دخول', NULL, NULL, '2026-03-04 22:04:01'),
(12, 16, 'supervisor', 'logout', 'تسجيل خروج', NULL, NULL, '2026-03-04 22:10:58'),
(13, 1, 'student', 'login', 'تسجيل دخول', NULL, NULL, '2026-03-04 22:11:17'),
(14, 1, 'student', 'logout', 'تسجيل خروج', NULL, NULL, '2026-03-04 22:15:03'),
(15, 11, 'training_entity', 'login', 'تسجيل دخول', NULL, NULL, '2026-03-04 22:16:01'),
(16, 11, 'training_entity', 'logout', 'تسجيل خروج', NULL, NULL, '2026-03-04 22:50:14'),
(17, 1, 'student', 'login', 'تسجيل دخول', NULL, NULL, '2026-03-04 22:56:33'),
(18, 1, 'student', 'logout', 'تسجيل خروج', NULL, NULL, '2026-03-04 23:03:00'),
(19, 11, 'training_entity', 'login', 'تسجيل دخول', NULL, NULL, '2026-03-04 23:03:14'),
(20, 11, 'training_entity', 'logout', 'تسجيل خروج', NULL, NULL, '2026-03-04 23:11:22'),
(21, 19, 'coordinator', 'login', 'تسجيل دخول', NULL, NULL, '2026-03-04 23:11:48'),
(22, 19, 'coordinator', 'logout', 'تسجيل خروج', NULL, NULL, '2026-03-04 23:19:08'),
(23, 16, 'supervisor', 'login', 'تسجيل دخول', NULL, NULL, '2026-03-04 23:19:45'),
(24, 16, 'supervisor', 'logout', 'تسجيل خروج', NULL, NULL, '2026-03-04 23:26:54'),
(25, 1, 'student', 'login', 'تسجيل دخول', NULL, NULL, '2026-03-05 21:54:07'),
(26, 1, 'student', 'logout', 'تسجيل خروج', NULL, NULL, '2026-03-05 22:11:01'),
(27, 11, 'training_entity', 'login', 'تسجيل دخول', NULL, NULL, '2026-03-05 22:11:22'),
(28, 11, 'training_entity', 'logout', 'تسجيل خروج', NULL, NULL, '2026-03-05 22:36:10'),
(29, 19, 'coordinator', 'login', 'تسجيل دخول', NULL, NULL, '2026-03-05 22:36:47'),
(30, 19, 'coordinator', 'logout', 'تسجيل خروج', NULL, NULL, '2026-03-05 22:42:58'),
(31, 16, 'supervisor', 'login', 'تسجيل دخول', NULL, NULL, '2026-03-05 22:43:39'),
(32, 16, 'supervisor', 'logout', 'تسجيل خروج', NULL, NULL, '2026-03-05 22:47:53'),
(33, 20, 'student', 'login', 'تسجيل دخول', NULL, NULL, '2026-03-05 23:04:24'),
(34, 20, 'student', 'logout', 'تسجيل خروج', NULL, NULL, '2026-03-05 23:06:19'),
(35, 1, 'student', 'login', 'تسجيل دخول', NULL, NULL, '2026-03-29 18:49:33'),
(36, 1, 'student', 'logout', 'تسجيل خروج', NULL, NULL, '2026-03-29 18:51:53'),
(37, 11, 'training_entity', 'login', 'تسجيل دخول', NULL, NULL, '2026-03-29 18:52:08'),
(38, 11, 'training_entity', 'logout', 'تسجيل خروج', NULL, NULL, '2026-03-29 18:54:04'),
(39, 16, 'supervisor', 'login', 'تسجيل دخول', NULL, NULL, '2026-03-29 18:54:46');

-- --------------------------------------------------------

--
-- بنية الجدول `applications`
--

CREATE TABLE `applications` (
  `id` int(11) NOT NULL,
  `student_id` int(11) NOT NULL,
  `seat_id` int(11) NOT NULL,
  `application_date` timestamp NOT NULL DEFAULT current_timestamp(),
  `priority_number` int(11) DEFAULT NULL COMMENT 'رقم الأولوية',
  `gpa_at_application` decimal(3,2) DEFAULT NULL COMMENT 'المعدل وقت التقديم',
  `status` enum('pending','under_review','interview_scheduled','accepted_by_entity','rejected_by_entity','pending_supervisor','approved','rejected','cancelled') DEFAULT 'pending',
  `notes` text DEFAULT NULL,
  `cv_file` varchar(255) DEFAULT NULL COMMENT 'سيرة ذاتية مخصصة للطلب',
  `interview_date` datetime DEFAULT NULL,
  `interview_link` varchar(255) DEFAULT NULL,
  `interview_notes` text DEFAULT NULL,
  `entity_decision_by` varchar(100) DEFAULT NULL,
  `entity_decision_date` datetime DEFAULT NULL,
  `supervisor_id` int(11) DEFAULT NULL COMMENT 'المشرف المعين',
  `supervisor_decision` enum('pending','approved','rejected') DEFAULT 'pending',
  `supervisor_notes` text DEFAULT NULL,
  `supervisor_decision_date` datetime DEFAULT NULL,
  `coordinator_notes` text DEFAULT NULL,
  `final_status` enum('pending','approved','rejected') DEFAULT 'pending',
  `visit_date` date DEFAULT NULL COMMENT 'تاريخ الزيارة الميدانية',
  `visit_notes` text DEFAULT NULL COMMENT 'ملاحظات الزيارة',
  `visit_status` enum('scheduled','completed','cancelled') DEFAULT 'scheduled',
  `completion_date` date DEFAULT NULL COMMENT 'تاريخ إكمال التدريب',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- إرجاع أو استيراد بيانات الجدول `applications`
--

INSERT INTO `applications` (`id`, `student_id`, `seat_id`, `application_date`, `priority_number`, `gpa_at_application`, `status`, `notes`, `cv_file`, `interview_date`, `interview_link`, `interview_notes`, `entity_decision_by`, `entity_decision_date`, `supervisor_id`, `supervisor_decision`, `supervisor_notes`, `supervisor_decision_date`, `coordinator_notes`, `final_status`, `visit_date`, `visit_notes`, `visit_status`, `completion_date`, `created_at`, `updated_at`) VALUES
(21, 1, 1, '2026-03-04 00:39:57', NULL, '4.85', 'accepted_by_entity', 'مهتمة جداً بتطوير الواجهات', NULL, NULL, NULL, NULL, NULL, NULL, 1, 'pending', NULL, NULL, NULL, 'pending', NULL, NULL, 'scheduled', NULL, '2026-03-04 00:39:57', '2026-03-29 18:53:16'),
(22, 2, 2, '2026-03-04 00:39:57', NULL, '4.92', 'approved', 'لدي خبرة في تحليل النظم', NULL, NULL, NULL, NULL, NULL, NULL, 2, 'pending', NULL, NULL, NULL, 'pending', NULL, NULL, 'scheduled', NULL, '2026-03-04 00:39:57', '2026-03-04 23:17:21'),
(23, 3, 3, '2026-03-04 00:39:57', NULL, '3.95', 'approved', 'متاحة للمقابلة في أي وقت', NULL, NULL, NULL, NULL, NULL, NULL, 3, 'pending', NULL, NULL, NULL, 'pending', NULL, NULL, 'scheduled', NULL, '2026-03-04 00:39:57', '2026-03-04 00:39:57'),
(24, 4, 4, '2026-03-04 00:39:57', NULL, '4.25', 'approved', 'شكراً لقبولي', NULL, NULL, NULL, NULL, NULL, NULL, 1, 'pending', NULL, NULL, NULL, 'pending', NULL, NULL, 'scheduled', NULL, '2026-03-04 00:39:57', '2026-03-04 00:39:57'),
(25, 5, 5, '2026-03-04 00:39:57', NULL, '3.88', 'approved', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 2, 'pending', NULL, NULL, NULL, 'pending', NULL, NULL, 'scheduled', NULL, '2026-03-04 00:39:57', '2026-03-04 00:39:57'),
(26, 6, 1, '2026-03-04 00:39:57', NULL, '4.50', 'approved', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 1, 'pending', NULL, NULL, NULL, 'pending', NULL, NULL, 'scheduled', '2026-03-05', '2026-03-04 00:39:57', '2026-03-04 23:24:06'),
(27, 7, 2, '2026-03-04 00:39:57', NULL, '3.65', 'rejected', 'لم يتم القبول', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'pending', NULL, NULL, NULL, 'pending', NULL, NULL, 'scheduled', NULL, '2026-03-04 00:39:57', '2026-03-04 00:39:57'),
(28, 8, 3, '2026-03-04 00:39:57', NULL, '4.80', 'approved', 'تم قبولي رسمياً', NULL, NULL, NULL, NULL, NULL, NULL, 2, 'pending', NULL, NULL, NULL, 'pending', NULL, NULL, 'scheduled', NULL, '2026-03-04 00:39:57', '2026-03-04 00:39:57'),
(29, 9, 5, '2026-03-04 00:39:57', NULL, '4.15', 'approved', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 3, 'pending', NULL, NULL, NULL, 'pending', NULL, NULL, 'scheduled', NULL, '2026-03-04 00:39:57', '2026-03-04 00:39:57'),
(30, 10, 7, '2026-03-04 00:39:57', NULL, '4.35', 'approved', 'في انتظار المقابلة', NULL, NULL, NULL, NULL, NULL, NULL, 3, 'pending', NULL, NULL, NULL, 'pending', NULL, NULL, 'scheduled', NULL, '2026-03-04 00:39:57', '2026-03-04 00:39:57');

-- --------------------------------------------------------

--
-- بنية الجدول `attachments`
--

CREATE TABLE `attachments` (
  `id` int(11) NOT NULL,
  `related_type` enum('student','entity','application','report','evaluation','message') NOT NULL,
  `related_id` int(11) NOT NULL,
  `file_name` varchar(255) NOT NULL,
  `file_path` varchar(255) NOT NULL,
  `file_type` varchar(50) DEFAULT NULL,
  `file_size` int(11) DEFAULT NULL COMMENT 'بالبايت',
  `description` text DEFAULT NULL,
  `uploaded_by` int(11) DEFAULT NULL,
  `uploaded_by_type` enum('student','supervisor','coordinator','training_entity') DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- إرجاع أو استيراد بيانات الجدول `attachments`
--

INSERT INTO `attachments` (`id`, `related_type`, `related_id`, `file_name`, `file_path`, `file_type`, `file_size`, `description`, `uploaded_by`, `uploaded_by_type`, `created_at`) VALUES
(2, 'student', 2, 'السيرة الذاتية - نورة خالد.pdf', 'students/cv_nora.pdf', 'cv', 980000, NULL, 2, 'student', '2026-03-04 00:51:22'),
(3, 'application', 1, 'خطاب التوصية.pdf', 'applications/rec_letter1.pdf', 'other', 512000, NULL, 1, 'student', '2026-03-04 00:51:22'),
(4, 'report', 1, 'التقرير الأسبوعي 1.pdf', 'reports/week1.pdf', 'report', 320000, NULL, 1, 'student', '2026-03-04 00:51:22'),
(5, 'evaluation', 1, 'شهادة سارة أحمد.pdf', 'certificates/cert_sara.pdf', 'certificate', 450000, NULL, 16, 'supervisor', '2026-03-04 00:51:22');

-- --------------------------------------------------------

--
-- بنية الجدول `attendance`
--

CREATE TABLE `attendance` (
  `id` int(11) NOT NULL,
  `student_id` int(11) NOT NULL,
  `application_id` int(11) NOT NULL COMMENT 'الطلب المرتبط',
  `attendance_date` date NOT NULL,
  `check_in_time` time DEFAULT NULL,
  `check_out_time` time DEFAULT NULL,
  `total_hours` float DEFAULT NULL,
  `tasks_done` text DEFAULT NULL COMMENT 'المهام المنجزة',
  `notes` text DEFAULT NULL,
  `status` enum('present','absent','late','excused','holiday') DEFAULT 'present',
  `verified_by_entity` tinyint(1) DEFAULT 0,
  `entity_verified_at` datetime DEFAULT NULL,
  `verified_by_supervisor` tinyint(1) DEFAULT 0,
  `location_check` varchar(255) DEFAULT NULL COMMENT 'موقع التسجيل',
  `device_info` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- إرجاع أو استيراد بيانات الجدول `attendance`
--

INSERT INTO `attendance` (`id`, `student_id`, `application_id`, `attendance_date`, `check_in_time`, `check_out_time`, `total_hours`, `tasks_done`, `notes`, `status`, `verified_by_entity`, `entity_verified_at`, `verified_by_supervisor`, `location_check`, `device_info`, `created_at`) VALUES
(25, 1, 21, '2024-07-01', '08:00:00', '16:00:00', 8, 'تطوير واجهة المستخدم', NULL, 'present', 0, NULL, 0, NULL, NULL, '2026-03-04 00:42:49'),
(26, 1, 21, '2024-07-02', '08:15:00', '16:00:00', 7.75, 'مراجعة الكود', NULL, 'late', 0, NULL, 0, NULL, NULL, '2026-03-04 00:42:49'),
(27, 1, 21, '2026-07-03', '08:00:00', '16:00:00', 8, 'اختبار النظام', NULL, 'present', 0, NULL, 0, NULL, NULL, '2026-03-04 00:42:49'),
(28, 2, 22, '2024-07-01', '09:00:00', '17:00:00', 8, 'تصميم قاعدة البيانات', NULL, 'present', 0, NULL, 0, NULL, NULL, '2026-03-04 00:42:49'),
(29, 2, 22, '2024-07-02', '09:00:00', '17:00:00', 8, 'برمجة API', NULL, 'present', 0, NULL, 0, NULL, NULL, '2026-03-04 00:42:49'),
(30, 3, 23, '2024-07-01', '09:30:00', '17:00:00', 7.5, 'اختبار', NULL, 'late', 0, NULL, 0, NULL, NULL, '2026-03-04 00:42:49'),
(31, 6, 26, '2026-03-05', '01:18:00', '00:00:00', NULL, NULL, NULL, 'present', 0, NULL, 0, NULL, NULL, '2026-03-04 22:19:09'),
(32, 1, 21, '2026-03-05', '01:19:00', '00:00:00', NULL, NULL, NULL, 'present', 0, NULL, 0, NULL, NULL, '2026-03-04 22:19:16'),
(33, 2, 22, '2026-03-05', '01:19:00', '00:00:00', NULL, NULL, NULL, 'present', 0, NULL, 0, NULL, NULL, '2026-03-04 22:19:31'),
(34, 6, 26, '2026-03-06', '01:32:00', '00:00:00', NULL, NULL, NULL, 'present', 1, '2026-03-06 01:32:59', 0, NULL, NULL, '2026-03-05 22:32:33'),
(35, 2, 22, '2026-03-06', '01:32:00', '00:00:00', NULL, NULL, NULL, 'present', 1, '2026-03-06 01:32:55', 0, NULL, NULL, '2026-03-05 22:32:43');

-- --------------------------------------------------------

--
-- بنية الجدول `coordinators`
--

CREATE TABLE `coordinators` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `full_name_ar` varchar(100) NOT NULL,
  `full_name_en` varchar(100) DEFAULT NULL,
  `employee_id` varchar(20) NOT NULL,
  `national_id` varchar(20) DEFAULT NULL,
  `department` varchar(100) DEFAULT NULL,
  `position` varchar(100) DEFAULT 'منسقة التدريب',
  `profile_image` varchar(255) DEFAULT NULL,
  `signature_file` varchar(255) DEFAULT NULL COMMENT 'التوقيع الرسمي',
  `phone` varchar(20) DEFAULT NULL,
  `office_location` varchar(100) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- إرجاع أو استيراد بيانات الجدول `coordinators`
--

INSERT INTO `coordinators` (`id`, `user_id`, `full_name_ar`, `full_name_en`, `employee_id`, `national_id`, `department`, `position`, `profile_image`, `signature_file`, `phone`, `office_location`, `created_at`) VALUES
(1, 19, 'أ. نورة عبدالعزيز الشمري', 'Nora Al-Shammari', 'C001', '333111111', 'وحدة التدريب', 'منسقة التدريب', NULL, NULL, '0559999999', NULL, '2026-03-04 00:37:21');

-- --------------------------------------------------------

--
-- بنية الجدول `evaluations`
--

CREATE TABLE `evaluations` (
  `id` int(11) NOT NULL,
  `application_id` int(11) NOT NULL,
  `student_id` int(11) NOT NULL,
  `entity_evaluation` decimal(5,2) DEFAULT NULL COMMENT 'تقييم الجهة (من 100)',
  `entity_evaluation_date` datetime DEFAULT NULL,
  `entity_commitment` int(11) DEFAULT NULL COMMENT 'الالتزام',
  `entity_performance` int(11) DEFAULT NULL COMMENT 'الأداء',
  `entity_teamwork` int(11) DEFAULT NULL COMMENT 'العمل الجماعي',
  `entity_initiative` int(11) DEFAULT NULL COMMENT 'المبادرة',
  `entity_skills` int(11) DEFAULT NULL COMMENT 'المهارات',
  `entity_report` text DEFAULT NULL COMMENT 'تقرير الجهة النهائي',
  `entity_report_file` varchar(255) DEFAULT NULL,
  `supervisor_evaluation` decimal(5,2) DEFAULT NULL COMMENT 'تقييم المشرف (من 100)',
  `supervisor_evaluation_date` datetime DEFAULT NULL,
  `supervisor_report` text DEFAULT NULL,
  `supervisor_report_file` varchar(255) DEFAULT NULL,
  `visit_date` date DEFAULT NULL COMMENT 'تاريخ الزيارة الميدانية',
  `visit_notes` text DEFAULT NULL,
  `final_grade` decimal(5,2) DEFAULT NULL COMMENT 'الدرجة النهائية',
  `final_grade_letter` varchar(2) DEFAULT NULL COMMENT 'التقدير (A+, A, B+, ...)',
  `training_completed` tinyint(1) DEFAULT 0,
  `completion_date` date DEFAULT NULL,
  `certificate_file` varchar(255) DEFAULT NULL COMMENT 'شهادة إتمام التدريب',
  `certificate_number` varchar(50) DEFAULT NULL COMMENT 'رقم الشهادة',
  `certificate_issued_at` datetime DEFAULT NULL COMMENT 'تاريخ إصدار الشهادة',
  `issued_by` int(11) DEFAULT NULL COMMENT 'من أصدر الشهادة',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- إرجاع أو استيراد بيانات الجدول `evaluations`
--

INSERT INTO `evaluations` (`id`, `application_id`, `student_id`, `entity_evaluation`, `entity_evaluation_date`, `entity_commitment`, `entity_performance`, `entity_teamwork`, `entity_initiative`, `entity_skills`, `entity_report`, `entity_report_file`, `supervisor_evaluation`, `supervisor_evaluation_date`, `supervisor_report`, `supervisor_report_file`, `visit_date`, `visit_notes`, `final_grade`, `final_grade_letter`, `training_completed`, `completion_date`, `certificate_file`, `certificate_number`, `certificate_issued_at`, `issued_by`, `created_at`, `updated_at`) VALUES
(14, 21, 1, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '92.50', NULL, 'أداء ممتاز، التزام عالي', NULL, '2024-07-15', 'تمت الزيارة ووجدت المتدربة ملتزمة', '92.50', 'A', 1, NULL, NULL, 'CERT-2024-00001', '2026-03-04 03:48:45', 1, '2026-03-04 00:48:45', '2026-03-04 00:48:45'),
(15, 22, 2, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '88.00', NULL, 'متدربة مجتهدة تحتاج تطوير بعض المهارات', NULL, '2024-07-16', 'زيارة ناجحة', '88.00', 'B+', 1, NULL, NULL, 'CERT-2024-00002', '2026-03-04 03:48:45', 2, '2026-03-04 00:48:45', '2026-03-04 00:48:45'),
(16, 23, 3, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '95.00', NULL, 'متميزة جداً', NULL, NULL, NULL, '95.00', 'A+', 0, NULL, NULL, NULL, NULL, NULL, '2026-03-04 00:48:45', '2026-03-04 00:48:45'),
(18, 26, 6, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '95.00', NULL, 'ماشاء الله بالتوفيق استمري بالنجاح', NULL, '2026-03-05', 'لايوجد', '95.00', 'A+', 1, NULL, NULL, 'CERT-2026-00026', '2026-03-05 02:24:36', 1, '2026-03-04 23:24:06', '2026-03-04 23:24:36');

-- --------------------------------------------------------

--
-- بنية الجدول `interviews`
--

CREATE TABLE `interviews` (
  `id` int(11) NOT NULL,
  `application_id` int(11) NOT NULL,
  `interview_date` datetime NOT NULL,
  `interview_type` enum('online','in_person','phone') DEFAULT 'online',
  `meeting_link` varchar(255) DEFAULT NULL,
  `location` varchar(255) DEFAULT NULL,
  `interviewer_name` varchar(100) DEFAULT NULL,
  `interviewer_position` varchar(100) DEFAULT NULL,
  `status` enum('scheduled','completed','cancelled','rescheduled') DEFAULT 'scheduled',
  `result` enum('pending','passed','failed') DEFAULT 'pending',
  `feedback` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- إرجاع أو استيراد بيانات الجدول `interviews`
--

INSERT INTO `interviews` (`id`, `application_id`, `interview_date`, `interview_type`, `meeting_link`, `location`, `interviewer_name`, `interviewer_position`, `status`, `result`, `feedback`, `created_at`) VALUES
(3, 23, '2024-07-20 10:00:00', 'online', 'https://meet.google.com/abc-xyz', NULL, 'أ. محمد القرشي', NULL, 'scheduled', 'pending', NULL, '2026-03-04 00:54:01'),
(7, 30, '2024-07-25 11:30:00', 'in_person', NULL, NULL, 'أ. سعد القحطاني', NULL, 'scheduled', 'pending', NULL, '2026-03-04 00:55:32'),
(13, 21, '2026-03-05 04:04:00', 'phone', '', 'السعودية', 'أ. عبدالله السلمي', NULL, 'scheduled', '', 'ضروري الحضور والرد علا المقابلة', '2026-03-04 22:36:33');

-- --------------------------------------------------------

--
-- بنية الجدول `majors`
--

CREATE TABLE `majors` (
  `id` int(11) NOT NULL,
  `major_code` varchar(20) NOT NULL,
  `major_name_ar` varchar(100) NOT NULL,
  `major_name_en` varchar(100) DEFAULT NULL,
  `department` varchar(100) DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- إرجاع أو استيراد بيانات الجدول `majors`
--

INSERT INTO `majors` (`id`, `major_code`, `major_name_ar`, `major_name_en`, `department`, `is_active`) VALUES
(1, 'CS', 'علوم الحاسب', 'Computer Science', 'تقنية المعلومات', 1),
(2, 'IS', 'نظم المعلومات', 'Information Systems', 'تقنية المعلومات', 1),
(3, 'IT', 'تقنية المعلومات', 'Information Technology', 'تقنية المعلومات', 1),
(4, 'AI', 'الذكاء الاصطناعي', 'Artificial Intelligence', 'تقنية المعلومات', 1),
(5, 'CY', 'الأمن السيبراني', 'Cybersecurity', 'تقنية المعلومات', 1),
(6, 'SWE', 'هندسة البرمجيات', 'Software Engineering', 'تقنية المعلومات', 1),
(7, 'BUS', 'إدارة أعمال', 'Business Administration', 'الإدارة', 1),
(8, 'ACC', 'محاسبة', 'Accounting', 'الإدارة', 1),
(9, 'MKT', 'تسويق', 'Marketing', 'الإدارة', 1),
(10, 'HR', 'الموارد البشرية', 'Human Resources', 'الإدارة', 1);

-- --------------------------------------------------------

--
-- بنية الجدول `messages`
--

CREATE TABLE `messages` (
  `id` int(11) NOT NULL,
  `sender_id` int(11) NOT NULL COMMENT 'مرسل الرسالة',
  `sender_type` enum('student','supervisor','coordinator','training_entity') NOT NULL,
  `receiver_id` int(11) NOT NULL COMMENT 'مستلم الرسالة',
  `receiver_type` enum('student','supervisor','coordinator','training_entity') NOT NULL,
  `subject` varchar(255) NOT NULL,
  `message` text NOT NULL,
  `type` enum('general','application','interview','report','attendance','support') DEFAULT 'general',
  `application_id` int(11) DEFAULT NULL,
  `reply_to` int(11) DEFAULT NULL,
  `is_read` tinyint(1) DEFAULT 0,
  `read_at` datetime DEFAULT NULL,
  `has_reply` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- إرجاع أو استيراد بيانات الجدول `messages`
--

INSERT INTO `messages` (`id`, `sender_id`, `sender_type`, `receiver_id`, `receiver_type`, `subject`, `message`, `type`, `application_id`, `reply_to`, `is_read`, `read_at`, `has_reply`, `created_at`) VALUES
(1, 1, 'student', 16, 'supervisor', 'استفسار عن التقرير', 'السلام عليكم دكتور، لدي استفسار عن التقرير الأسبوعي', 'general', NULL, NULL, 0, NULL, 0, '2026-03-04 00:51:34'),
(2, 16, 'supervisor', 1, 'student', 'رد: استفسار عن التقرير', 'وعليكم السلام، يمكنك إرساله وسأراجعه', 'general', NULL, NULL, 1, NULL, 0, '2026-03-04 00:51:34'),
(3, 2, 'student', 17, 'supervisor', 'طلب موعد زيارة', 'أود تحديد موعد للزيارة الميدانية', 'general', NULL, NULL, 0, NULL, 0, '2026-03-04 00:51:34'),
(4, 17, 'supervisor', 2, 'student', 'تم تحديد الموعد', 'تم تحديد الزيارة يوم الأحد', 'general', NULL, NULL, 1, NULL, 0, '2026-03-04 00:51:34'),
(5, 19, 'coordinator', 16, 'supervisor', 'توزيع طالبات جديد', 'تم توزيع طالبات جديد عليك', 'general', NULL, NULL, 1, NULL, 0, '2026-03-04 00:51:34'),
(7, 1, 'student', 16, 'supervisor', 'رد: رد: استفسار عن التقرير', '\r\n----- الرسالة الأصلية -----\r\nوعليكم السلام، يمكنك إرساله وسأراجعه ', 'general', 21, NULL, 1, '2026-03-05 01:10:24', 0, '2026-03-04 20:59:01'),
(8, 16, 'supervisor', 1, 'student', 'رد: رد: استفسار عن التقرير', 'رد: رد: استفسار عن التقرير لماذا لم ترسلي التقرير', 'general', 26, NULL, 0, NULL, 0, '2026-03-04 22:08:58'),
(9, 1, 'student', 11, 'training_entity', 'رد: رد: رد: استفسار عن التقرير', '\r\n----- الرسالة الأصلية -----\r\nرد: رد: استفسار عن التقرير لماذا لم ترسلي التقرير', 'general', 0, NULL, 0, NULL, 0, '2026-03-29 18:51:13');

-- --------------------------------------------------------

--
-- بنية الجدول `notifications`
--

CREATE TABLE `notifications` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL COMMENT 'مستلم الإشعار',
  `user_type` enum('student','supervisor','coordinator','training_entity') NOT NULL,
  `title` varchar(255) NOT NULL,
  `message` text NOT NULL,
  `type` enum('info','success','warning','error') DEFAULT 'info',
  `related_link` varchar(255) DEFAULT NULL COMMENT 'رابط متعلق',
  `related_id` int(11) DEFAULT NULL COMMENT 'معرف العنصر المرتبط',
  `is_read` tinyint(1) DEFAULT 0,
  `read_at` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- إرجاع أو استيراد بيانات الجدول `notifications`
--

INSERT INTO `notifications` (`id`, `user_id`, `user_type`, `title`, `message`, `type`, `related_link`, `related_id`, `is_read`, `read_at`, `created_at`) VALUES
(1, 1, 'student', 'تم تقديم الطلب', 'تم تقديم طلبك بنجاح', 'success', NULL, NULL, 1, NULL, '2026-03-04 00:51:47'),
(2, 2, 'student', 'موعد مقابلة', 'تم تحديد مقابلة مع الجهة', 'info', NULL, NULL, 0, NULL, '2026-03-04 00:51:47'),
(3, 16, 'supervisor', 'تقرير جديد', 'تم رفع تقرير جديد من المتدربة سارة', 'info', NULL, NULL, 0, NULL, '2026-03-04 00:51:47'),
(4, 17, 'supervisor', 'تقييم مطلوب', 'في انتظار تقييم المتدربة نورة', 'warning', NULL, NULL, 0, NULL, '2026-03-04 00:51:47'),
(5, 19, 'coordinator', 'جهة جديدة', 'تم تسجيل جهة جديدة', 'info', NULL, NULL, 1, NULL, '2026-03-04 00:51:47'),
(6, 1, 'student', 'تم قبول طلبك', 'تم قبول طلبك في فرصة مطور واجهات أمامية من قبل الجهة التدريبية', 'success', 'applications/view.php?id=21', 21, 1, '2026-03-05 02:02:23', '2026-03-04 22:30:33'),
(7, 1, 'student', 'تم قبول طلبك', 'تم قبول طلبك في فرصة مطور واجهات أمامية من قبل الجهة التدريبية', 'success', 'applications/view.php?id=21', 21, 1, '2026-03-05 02:02:23', '2026-03-04 22:30:54'),
(8, 1, 'student', 'موعد مقابلة', 'تم تحديد موعد مقابلة للفرصة مطور واجهات أمامية بتاريخ ', 'info', 'applications/view.php?id=21', 21, 0, NULL, '2026-03-29 18:52:58'),
(9, 1, 'student', 'تم قبول طلبك', 'تم قبول طلبك في فرصة مطور واجهات أمامية من قبل الجهة التدريبية', 'success', 'applications/view.php?id=21', 21, 0, NULL, '2026-03-29 18:53:16');

-- --------------------------------------------------------

--
-- بنية الجدول `settings`
--

CREATE TABLE `settings` (
  `id` int(11) NOT NULL,
  `setting_key` varchar(100) NOT NULL,
  `setting_value` text DEFAULT NULL,
  `setting_type` enum('text','number','boolean','json','file') DEFAULT 'text',
  `description` text DEFAULT NULL,
  `updated_by` int(11) DEFAULT NULL,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- إرجاع أو استيراد بيانات الجدول `settings`
--

INSERT INTO `settings` (`id`, `setting_key`, `setting_value`, `setting_type`, `description`, `updated_by`, `updated_at`) VALUES
(1, 'application_deadline', '2024-12-31', 'text', 'آخر موعد للتقديم', NULL, '2026-03-04 00:35:59'),
(2, 'min_gpa', '2.5', 'number', 'الحد الأدنى للمعدل', NULL, '2026-03-04 00:35:59'),
(3, 'max_applications', '5', 'number', 'الحد الأقصى لعدد الطلبات للمتدربة', NULL, '2026-03-04 00:35:59'),
(4, 'training_duration_weeks', '8', 'number', 'مدة التدريب بالأسابيع', NULL, '2026-03-04 00:35:59'),
(5, 'auto_attendance_reminder', '1', 'boolean', 'تذكير تلقائي للحضور', NULL, '2026-03-04 00:35:59'),
(6, 'system_name_ar', 'تعاوني', 'text', 'اسم النظام بالعربية', NULL, '2026-03-04 00:35:59'),
(7, 'system_name_en', 'Taawoni', 'text', 'اسم النظام بالإنجليزية', NULL, '2026-03-04 00:35:59');

-- --------------------------------------------------------

--
-- بنية الجدول `students`
--

CREATE TABLE `students` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `student_id` varchar(20) NOT NULL COMMENT 'الرقم الجامعي',
  `full_name_ar` varchar(100) NOT NULL,
  `full_name_en` varchar(100) DEFAULT NULL,
  `national_id` varchar(20) NOT NULL COMMENT 'رقم الهوية',
  `nationality` varchar(50) DEFAULT 'سعودية',
  `birth_date` date DEFAULT NULL,
  `gender` enum('female') DEFAULT 'female',
  `gpa` decimal(3,2) DEFAULT NULL COMMENT 'المعدل التراكمي',
  `academic_level` int(11) DEFAULT NULL,
  `major_id` int(11) DEFAULT NULL COMMENT 'التخصص',
  `total_hours` int(11) DEFAULT 0 COMMENT 'الساعات المكتملة',
  `expected_graduation` date DEFAULT NULL COMMENT 'تاريخ التخرج المتوقع',
  `priority_points` int(11) DEFAULT 0 COMMENT 'نقاط الأولوية',
  `is_graduating` tinyint(1) DEFAULT 0 COMMENT 'متدربة متخرجة؟',
  `phone` varchar(20) DEFAULT NULL,
  `profile_image` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- إرجاع أو استيراد بيانات الجدول `students`
--

INSERT INTO `students` (`id`, `user_id`, `student_id`, `full_name_ar`, `full_name_en`, `national_id`, `nationality`, `birth_date`, `gender`, `gpa`, `academic_level`, `major_id`, `total_hours`, `expected_graduation`, `priority_points`, `is_graduating`, `phone`, `profile_image`, `created_at`) VALUES
(1, 1, '2024001', 'حصة صالح', 'hasa saleh', '1111111111', 'سعودية', '2002-05-15', 'female', '4.85', 8, 1, 124, '2026-04-01', 0, 0, '0501111111', NULL, '2026-03-04 00:36:35'),
(2, 2, '2024002', 'نورة خالد الدوسري', 'Nora Khalid Aldosari', '1111111112', 'سعودية', '2002-08-22', 'female', '4.92', 8, 2, 126, '2025-06-01', 0, 0, '0502222222', NULL, '2026-03-04 00:36:35'),
(3, 3, '2024003', 'لمى محمد الغامدي', 'Lama Mohammed Alghamdi', '1111111113', 'سعودية', '2003-01-10', 'female', '3.95', 7, 3, 98, '2026-06-01', 0, 0, '0503333333', NULL, '2026-03-04 00:36:35'),
(4, 4, '2024004', 'رنا عبدالله المطيري', 'Rana Abdullah Almotairi', '1111111114', 'سعودية', '2002-11-03', 'female', '4.25', 8, 4, 115, '2025-06-01', 0, 0, '0504444444', NULL, '2026-03-04 00:36:35'),
(5, 5, '2024005', 'ريم فهد الزهراني', 'Reem Fahad Alzahrani', '1111111115', 'سعودية', '2003-03-18', 'female', '3.88', 7, 5, 95, '2026-06-01', 0, 0, '0505555555', NULL, '2026-03-04 00:36:35'),
(6, 6, '2024006', 'أمل سلطان القحطاني', 'Amal Sultan Alqahtani', '1111111116', 'سعودية', '2002-07-25', 'female', '4.50', 8, 6, 122, '2025-06-01', 0, 0, '0506666666', NULL, '2026-03-04 00:36:35'),
(7, 7, '2024007', 'هيا إبراهيم العبدلي', 'Haya Ibrahim Alabdali', '1111111117', 'سعودية', '2003-09-12', 'female', '3.65', 7, 7, 90, '2026-06-01', 0, 0, '0507777777', NULL, '2026-03-04 00:36:35'),
(8, 8, '2024008', 'ديمة ناصر الشهري', 'Deema Nasser Alshehri', '1111111118', 'سعودية', '2002-12-30', 'female', '4.80', 8, 8, 125, '2025-06-01', 0, 0, '0508888888', NULL, '2026-03-04 00:36:35'),
(9, 9, '2024009', 'جوري سعد العتيبي', 'Jouri Saad Alotaibi', '1111111119', 'سعودية', '2003-04-05', 'female', '4.15', 7, 9, 102, '2026-06-01', 0, 0, '0509999999', NULL, '2026-03-04 00:36:35'),
(10, 10, '2024010', 'شهد عمر الحربي', 'Shahad Omar Alharbi', '1111111120', 'سعودية', '2003-06-19', 'female', '4.35', 7, 10, 110, '2026-06-01', 0, 0, '0510000000', NULL, '2026-03-04 00:36:35'),
(11, 20, '55555555', 'Bashar Yousef', NULL, '11111111', 'سعودية', NULL, 'female', '5.00', NULL, 3, 0, NULL, 0, 0, '0501111111', NULL, '2026-03-05 23:04:15');

-- --------------------------------------------------------

--
-- بنية الجدول `supervisors`
--

CREATE TABLE `supervisors` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `full_name_ar` varchar(100) NOT NULL,
  `full_name_en` varchar(100) DEFAULT NULL,
  `employee_id` varchar(20) NOT NULL,
  `national_id` varchar(20) DEFAULT NULL,
  `academic_title` varchar(50) DEFAULT NULL COMMENT 'دكتور / أستاذ / ...',
  `department` varchar(100) DEFAULT NULL,
  `specialization` varchar(100) DEFAULT NULL,
  `profile_image` varchar(255) DEFAULT NULL,
  `signature_file` varchar(255) DEFAULT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `office_location` varchar(100) DEFAULT NULL,
  `max_students` int(11) DEFAULT 15 COMMENT 'الحد الأقصى للإشراف',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- إرجاع أو استيراد بيانات الجدول `supervisors`
--

INSERT INTO `supervisors` (`id`, `user_id`, `full_name_ar`, `full_name_en`, `employee_id`, `national_id`, `academic_title`, `department`, `specialization`, `profile_image`, `signature_file`, `phone`, `office_location`, `max_students`, `created_at`) VALUES
(1, 16, 'د. أحمد سالم العنزي', 'Dr. Ahmed Al-Anzi', 'SUP001', '222111111', 'دكتور', 'تقنية المعلومات', NULL, NULL, NULL, '0551111111', NULL, 15, '2026-03-04 00:37:10'),
(2, 17, 'د. خالد عبدالله المطيري', 'Dr. Khalid Al-Mutairi', 'SUP002', '222111112', 'أستاذ مشارك', 'علوم الحاسب', NULL, NULL, NULL, '0552222222', NULL, 12, '2026-03-04 00:37:10'),
(3, 18, 'د. نورة محمد القحطاني', 'Dr. Nora Al-Qahtani', 'SUP003', '222111113', 'أستاذ مساعد', 'نظم المعلومات', NULL, NULL, NULL, '0553333333', NULL, 10, '2026-03-04 00:37:10');

-- --------------------------------------------------------

--
-- بنية الجدول `training_entities`
--

CREATE TABLE `training_entities` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `entity_name_ar` varchar(200) NOT NULL COMMENT 'اسم الجهة بالعربية',
  `entity_name_en` varchar(200) DEFAULT NULL,
  `commercial_registration` varchar(50) DEFAULT NULL COMMENT 'السجل التجاري',
  `license_file` varchar(255) DEFAULT NULL COMMENT 'ملف السجل/الترخيص',
  `entity_type` enum('شركة','مؤسسة','مستشفى','بنك','مدرسة','جهة حكومية','أخرى') DEFAULT 'شركة',
  `contact_person` varchar(100) DEFAULT NULL COMMENT 'الشخص المسؤول',
  `contact_position` varchar(100) DEFAULT NULL COMMENT 'مسمى المسؤول',
  `contact_phone` varchar(20) DEFAULT NULL,
  `contact_email` varchar(100) DEFAULT NULL,
  `website` varchar(255) DEFAULT NULL,
  `city` varchar(50) DEFAULT NULL,
  `address` text DEFAULT NULL,
  `logo` varchar(255) DEFAULT NULL COMMENT 'شعار الجهة',
  `is_approved` tinyint(1) DEFAULT 0 COMMENT 'معتمدة من المنسقة؟',
  `approved_by` int(11) DEFAULT NULL COMMENT 'المنسقة التي اعتمدت',
  `approved_at` datetime DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- إرجاع أو استيراد بيانات الجدول `training_entities`
--

INSERT INTO `training_entities` (`id`, `user_id`, `entity_name_ar`, `entity_name_en`, `commercial_registration`, `license_file`, `entity_type`, `contact_person`, `contact_position`, `contact_phone`, `contact_email`, `website`, `city`, `address`, `logo`, `is_approved`, `approved_by`, `approved_at`, `notes`, `created_at`) VALUES
(1, 11, 'شركة تكامل للحلول التقنية', 'Takamul Tech Solutions', '1011111111', NULL, 'شركة', 'أ. عبدالله السلمي', 'مدير التدريب', '0111111111', 'hr@takamul.com', 'www.takamul.com', 'الرياض', 'الرياض - طريق الملك فهد', NULL, 1, NULL, NULL, NULL, '2026-03-04 00:36:49'),
(2, 12, 'مؤسسة رواد المستقبل', 'Future Leaders Est', '1012222222', NULL, 'مؤسسة', 'أ. محمد القرشي', 'مسؤول التدريب', '0112222222', 'training@future.com', 'www.future.com', 'جدة', 'جدة - شارع التحلية', NULL, 1, NULL, NULL, NULL, '2026-03-04 00:36:49'),
(3, 13, 'بنك الريادة', 'ALRIYADAH Bank', '1013333333', NULL, 'بنك', 'أ. فهد العتيبي', 'مدير الموارد البشرية', '0113333333', 'hr@alriyadah.com', 'www.alriyadah.com', 'الرياض', 'برج الريادة - طريق الملك عبدالله', NULL, 1, NULL, NULL, NULL, '2026-03-04 00:36:49'),
(4, 14, 'شركة ابتكار', 'Ibtikar Company', '1014444444', NULL, 'شركة', 'أ. نوال الشمري', 'مديرة التدريب', '0114444444', 'careers@ibtikar.com', 'www.ibtikar.com', 'الخبر', 'الخبر - شارع الأمير فيصل', NULL, 1, NULL, '2026-03-05 02:14:54', NULL, '2026-03-04 00:36:49'),
(5, 15, 'مجموعة التميز', 'Al-Tameez Group', '1015555555', NULL, 'شركة', 'أ. سعد القحطاني', 'رئيس قسم التطوير', '0115555555', 'hr@tameez.com', 'www.tameez.com', 'الدمام', 'الدمام - شارع الملك سعود', NULL, 1, NULL, NULL, NULL, '2026-03-04 00:36:49');

-- --------------------------------------------------------

--
-- بنية الجدول `training_seats`
--

CREATE TABLE `training_seats` (
  `id` int(11) NOT NULL,
  `entity_id` int(11) NOT NULL,
  `major_id` int(11) DEFAULT NULL,
  `title_ar` varchar(200) NOT NULL COMMENT 'مسمى التدريب',
  `title_en` varchar(200) DEFAULT NULL,
  `description` text DEFAULT NULL COMMENT 'وصف التدريب',
  `requirements` text DEFAULT NULL COMMENT 'المتطلبات',
  `available_seats` int(11) NOT NULL,
  `filled_seats` int(11) DEFAULT 0,
  `start_date` date DEFAULT NULL,
  `end_date` date DEFAULT NULL,
  `duration_weeks` int(11) DEFAULT NULL,
  `daily_hours` float DEFAULT 8 COMMENT 'ساعات العمل اليومية',
  `training_type` enum('صيفي','تعاوني','ميداني') DEFAULT 'تعاوني',
  `location` varchar(255) DEFAULT NULL,
  `is_remote` tinyint(1) DEFAULT 0 COMMENT 'عن بعد؟',
  `has_salary` tinyint(1) DEFAULT 0 COMMENT 'مكافأة مالية؟',
  `salary_amount` decimal(10,2) DEFAULT NULL,
  `status` enum('available','full','closed') DEFAULT 'available',
  `applications_count` int(11) DEFAULT 0,
  `created_by` int(11) DEFAULT NULL COMMENT 'من أضاف المقعد',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- إرجاع أو استيراد بيانات الجدول `training_seats`
--

INSERT INTO `training_seats` (`id`, `entity_id`, `major_id`, `title_ar`, `title_en`, `description`, `requirements`, `available_seats`, `filled_seats`, `start_date`, `end_date`, `duration_weeks`, `daily_hours`, `training_type`, `location`, `is_remote`, `has_salary`, `salary_amount`, `status`, `applications_count`, `created_by`, `created_at`) VALUES
(1, 1, 1, 'مطور واجهات أمامية', 'Frontend Developer', 'التدريب على تطوير واجهات المستخدم باستخدام React و Vue.js', 'معرفة في HTML, CSS, JavaScript', 5, 0, '2024-06-01', '2024-08-01', 8, 8, 'تعاوني', 'الرياض', 0, 1, '3000.00', 'available', 0, NULL, '2026-03-04 00:37:43'),
(2, 1, 2, 'محلل نظم', 'Systems Analyst', 'تحليل وتصميم النظم وتوثيق المتطلبات', 'معرفة في UML وقواعد البيانات', 3, 1, '2024-06-15', '2024-08-15', 8, 7, 'تعاوني', 'الرياض', 1, 1, '3500.00', 'available', 0, NULL, '2026-03-04 00:37:43'),
(3, 2, 3, 'مهندس برمجيات', 'Software Engineer', 'تطوير تطبيقات الويب باستخدام Laravel', 'PHP - MySQL - Laravel', 4, 2, '2024-07-01', '2024-09-01', 10, 8, 'صيفي', 'جدة', 0, 1, '4000.00', 'full', 0, NULL, '2026-03-04 00:37:43'),
(4, 2, 5, 'أمن معلومات', 'Information Security', 'التدريب على أمن الشبكات واختبار الاختراق', 'أساسيات الشبكات', 2, 0, '2024-06-01', '2024-08-01', 8, 8, 'تعاوني', 'جدة', 0, 1, '4500.00', 'available', 0, NULL, '2026-03-04 00:37:43'),
(5, 3, 7, 'موظف إداري', 'Administrative Trainee', 'التدريب على الأعمال الإدارية والمكتبية', 'مهارات تنظيمية', 6, 3, '2024-05-15', '2024-08-15', 12, 7, 'صيفي', 'الرياض', 0, 1, '3000.00', 'available', 0, NULL, '2026-03-04 00:37:43'),
(6, 3, 8, 'مساعد محاسب', 'Accounting Assistant', 'التدريب على العمليات المحاسبية', 'تخصص محاسبة', 3, 1, '2024-07-01', '2024-09-01', 8, 7, 'تعاوني', 'الرياض', 0, 1, '3500.00', 'available', 0, NULL, '2026-03-04 00:37:43'),
(7, 4, 4, 'مطور تطبيقات', 'Mobile Developer', 'تطوير تطبيقات الهواتف باستخدام Flutter', 'معرفة في Dart', 4, 0, '2024-08-01', '2024-10-01', 8, 8, 'تعاوني', 'الخبر', 1, 0, NULL, 'available', 0, NULL, '2026-03-04 00:37:43'),
(8, 5, 9, 'مسوق رقمي', 'Digital Marketer', 'التدريب على التسويق الرقمي وإدارة الحملات', 'مهارات تسويقية', 5, 2, '2024-06-01', '2024-08-01', 8, 7, 'تعاوني', 'الدمام', 1, 1, '3000.00', 'available', 0, NULL, '2026-03-04 00:37:43'),
(9, 5, 10, 'أخصائي موارد بشرية', 'HR Specialist', 'التدريب على عمليات الموارد البشرية', 'تخصص موارد بشرية', 3, 0, '2024-07-01', '2024-09-01', 8, 7, 'تعاوني', 'الدمام', 0, 1, '3500.00', 'available', 0, NULL, '2026-03-04 00:37:43');

-- --------------------------------------------------------

--
-- بنية الجدول `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `email` varchar(100) NOT NULL,
  `password` varchar(255) NOT NULL,
  `user_type` enum('student','supervisor','coordinator','training_entity') NOT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `last_login` datetime DEFAULT NULL,
  `remember_token` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- إرجاع أو استيراد بيانات الجدول `users`
--

INSERT INTO `users` (`id`, `email`, `password`, `user_type`, `phone`, `is_active`, `last_login`, `remember_token`, `created_at`, `updated_at`) VALUES
(1, 'student1@ctms.edu', '123456', 'student', '0501111111', 1, '2026-03-29 21:49:33', NULL, '2026-03-04 00:35:34', '2026-03-29 18:49:33'),
(2, 'student2@ctms.edu', '123456', 'student', '0502222222', 1, NULL, NULL, '2026-03-04 00:35:34', '2026-03-04 00:35:34'),
(3, 'student3@ctms.edu', '123456', 'student', '0503333333', 1, NULL, NULL, '2026-03-04 00:35:34', '2026-03-04 00:35:34'),
(4, 'student4@ctms.edu', '123456', 'student', '0504444444', 1, NULL, NULL, '2026-03-04 00:35:34', '2026-03-04 00:35:34'),
(5, 'student5@ctms.edu', '123456', 'student', '0505555555', 1, NULL, NULL, '2026-03-04 00:35:34', '2026-03-04 00:35:34'),
(6, 'student6@ctms.edu', '123456', 'student', '0506666666', 1, NULL, NULL, '2026-03-04 00:35:34', '2026-03-04 00:35:34'),
(7, 'student7@ctms.edu', '123456', 'student', '0507777777', 1, NULL, NULL, '2026-03-04 00:35:34', '2026-03-04 00:35:34'),
(8, 'student8@ctms.edu', '123456', 'student', '0508888888', 1, NULL, NULL, '2026-03-04 00:35:34', '2026-03-04 00:35:34'),
(9, 'student9@ctms.edu', '123456', 'student', '0509999999', 1, NULL, NULL, '2026-03-04 00:35:34', '2026-03-04 00:35:34'),
(10, 'student10@ctms.edu', '123456', 'student', '0510000000', 1, NULL, NULL, '2026-03-04 00:35:34', '2026-03-04 00:35:34'),
(11, 'entity1@company.com', '123456', 'training_entity', '0111111111', 1, '2026-03-29 21:52:08', NULL, '2026-03-04 00:35:34', '2026-03-29 18:52:08'),
(12, 'entity2@company.com', '123456', 'training_entity', '0112222222', 1, NULL, NULL, '2026-03-04 00:35:34', '2026-03-04 00:35:34'),
(13, 'entity3@company.com', '123456', 'training_entity', '0113333333', 1, NULL, NULL, '2026-03-04 00:35:34', '2026-03-04 00:35:34'),
(14, 'entity4@company.com', '123456', 'training_entity', '0114444444', 1, NULL, NULL, '2026-03-04 00:35:34', '2026-03-04 00:35:34'),
(15, 'entity5@company.com', '123456', 'training_entity', '0115555555', 1, NULL, NULL, '2026-03-04 00:35:34', '2026-03-04 00:35:34'),
(16, 'supervisor1@university.edu', '123456', 'supervisor', '0551111111', 1, '2026-03-29 21:54:46', NULL, '2026-03-04 00:35:34', '2026-03-29 18:54:46'),
(17, 'supervisor2@university.edu', '123456', 'supervisor', '0552222222', 1, NULL, NULL, '2026-03-04 00:35:34', '2026-03-04 00:35:34'),
(18, 'supervisor3@university.edu', '123456', 'supervisor', '0553333333', 1, NULL, NULL, '2026-03-04 00:35:34', '2026-03-04 00:35:34'),
(19, 'coordinator@ctms.edu', '123456', 'coordinator', '0559999999', 1, '2026-03-06 01:36:47', NULL, '2026-03-04 00:35:34', '2026-03-05 22:36:47'),
(20, 'user@gmail.com', '123456', 'student', NULL, 1, '2026-03-06 02:04:24', NULL, '2026-03-05 23:04:15', '2026-03-05 23:04:24');

-- --------------------------------------------------------

--
-- بنية الجدول `visits`
--

CREATE TABLE `visits` (
  `id` int(11) NOT NULL,
  `application_id` int(11) NOT NULL,
  `supervisor_id` int(11) NOT NULL,
  `student_id` int(11) NOT NULL,
  `visit_date` date NOT NULL,
  `visit_time` time DEFAULT NULL,
  `location` varchar(255) DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `status` enum('scheduled','completed','cancelled') DEFAULT 'scheduled',
  `report_file` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- إرجاع أو استيراد بيانات الجدول `visits`
--

INSERT INTO `visits` (`id`, `application_id`, `supervisor_id`, `student_id`, `visit_date`, `visit_time`, `location`, `notes`, `status`, `report_file`, `created_at`, `updated_at`) VALUES
(5, 21, 1, 1, '2026-03-06', '10:00:00', 'شركة تكامل - الرياض', 'زيارة متابعة للأسبوع الأول', 'completed', NULL, '2026-03-04 00:50:32', '2026-03-04 22:05:20'),
(6, 22, 2, 2, '2026-03-08', '11:30:00', 'مؤسسة رواد المستقبل - جدة', 'متابعة سير التدريب', 'scheduled', NULL, '2026-03-04 00:50:32', '2026-03-04 00:50:32'),
(7, 23, 3, 3, '2026-03-01', '09:00:00', 'بنك الريادة - الرياض', 'تمت الزيارة بنجاح', 'completed', NULL, '2026-03-04 00:50:32', '2026-03-04 00:50:32'),
(8, 24, 1, 4, '2026-03-10', '13:00:00', 'شركة ابتكار - الخبر', 'زيارة أولى', 'cancelled', NULL, '2026-03-04 00:50:32', '2026-03-04 22:10:39'),
(9, 21, 1, 1, '2026-03-06', '10:00:00', 'شركة تكامل - الرياض', 'زيارة متابعة ', 'completed', NULL, '2026-03-04 00:54:01', '2026-03-04 22:05:04');

-- --------------------------------------------------------

--
-- بنية الجدول `weekly_reports`
--

CREATE TABLE `weekly_reports` (
  `id` int(11) NOT NULL,
  `student_id` int(11) NOT NULL,
  `application_id` int(11) NOT NULL,
  `week_number` int(11) NOT NULL COMMENT 'الأسبوع رقم',
  `start_date` date DEFAULT NULL,
  `end_date` date DEFAULT NULL,
  `report_file` varchar(255) DEFAULT NULL COMMENT 'ملف التقرير',
  `report_content` longtext DEFAULT NULL COMMENT 'محتوى التقرير',
  `tasks_completed` text DEFAULT NULL,
  `challenges` text DEFAULT NULL,
  `achievements` text DEFAULT NULL,
  `supervisor_notes` text DEFAULT NULL,
  `entity_notes` text DEFAULT NULL,
  `status` enum('draft','submitted','reviewed','approved') DEFAULT 'draft',
  `submitted_at` datetime DEFAULT NULL,
  `reviewed_at` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- إرجاع أو استيراد بيانات الجدول `weekly_reports`
--

INSERT INTO `weekly_reports` (`id`, `student_id`, `application_id`, `week_number`, `start_date`, `end_date`, `report_file`, `report_content`, `tasks_completed`, `challenges`, `achievements`, `supervisor_notes`, `entity_notes`, `status`, `submitted_at`, `reviewed_at`, `created_at`) VALUES
(4, 1, 21, 1, '2026-03-03', '1905-11-01', NULL, NULL, '1. تصميم واجهة المستخدم\n2. برمجة الصفحة الرئيسية\n3. اختبار الأداء', 'واجهت صعوبة في التجاوب مع الأجهزة المختلفة', 'تم إنجاز 80% من الواجهة', 'ممتاز، استمري', NULL, 'approved', '2026-03-04 03:44:40', NULL, '2026-03-04 00:44:40'),
(5, 1, 21, 2, '2024-07-08', '2024-07-12', NULL, NULL, '1. ربط قاعدة البيانات\n2. برمجة API\n3. اختبار', 'تأخر في تسليم بعض المهام', 'تم إنجاز API بالكامل', 'نحتاج تحسين سرعة العمل', NULL, 'reviewed', '2026-03-04 03:44:40', NULL, '2026-03-04 00:44:40'),
(6, 2, 22, 1, '2024-07-01', '2024-07-05', NULL, NULL, '1. تحليل المتطلبات\n2. تصميم ERD\n3. إعداد البيئة', 'لا توجد', 'تم الانتهاء من التصميم', 'عمل رائع', NULL, 'approved', '2026-03-04 03:44:40', NULL, '2026-03-04 00:44:40');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `activity_log`
--
ALTER TABLE `activity_log`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `applications`
--
ALTER TABLE `applications`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_student_application` (`student_id`,`seat_id`),
  ADD KEY `seat_id` (`seat_id`),
  ADD KEY `supervisor_id` (`supervisor_id`);

--
-- Indexes for table `attachments`
--
ALTER TABLE `attachments`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `attendance`
--
ALTER TABLE `attendance`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_attendance` (`student_id`,`attendance_date`),
  ADD KEY `application_id` (`application_id`);

--
-- Indexes for table `coordinators`
--
ALTER TABLE `coordinators`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `user_id` (`user_id`),
  ADD UNIQUE KEY `employee_id` (`employee_id`),
  ADD UNIQUE KEY `national_id` (`national_id`);

--
-- Indexes for table `evaluations`
--
ALTER TABLE `evaluations`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `application_id` (`application_id`),
  ADD KEY `student_id` (`student_id`),
  ADD KEY `issued_by` (`issued_by`);

--
-- Indexes for table `interviews`
--
ALTER TABLE `interviews`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `application_id` (`application_id`);

--
-- Indexes for table `majors`
--
ALTER TABLE `majors`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `major_code` (`major_code`);

--
-- Indexes for table `messages`
--
ALTER TABLE `messages`
  ADD PRIMARY KEY (`id`),
  ADD KEY `sender_id` (`sender_id`),
  ADD KEY `receiver_id` (`receiver_id`),
  ADD KEY `application_id` (`application_id`),
  ADD KEY `reply_to` (`reply_to`);

--
-- Indexes for table `notifications`
--
ALTER TABLE `notifications`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `settings`
--
ALTER TABLE `settings`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `setting_key` (`setting_key`);

--
-- Indexes for table `students`
--
ALTER TABLE `students`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `user_id` (`user_id`),
  ADD UNIQUE KEY `student_id` (`student_id`),
  ADD UNIQUE KEY `national_id` (`national_id`),
  ADD KEY `major_id` (`major_id`);

--
-- Indexes for table `supervisors`
--
ALTER TABLE `supervisors`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `user_id` (`user_id`),
  ADD UNIQUE KEY `employee_id` (`employee_id`),
  ADD UNIQUE KEY `national_id` (`national_id`);

--
-- Indexes for table `training_entities`
--
ALTER TABLE `training_entities`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `user_id` (`user_id`),
  ADD UNIQUE KEY `commercial_registration` (`commercial_registration`),
  ADD KEY `approved_by` (`approved_by`);

--
-- Indexes for table `training_seats`
--
ALTER TABLE `training_seats`
  ADD PRIMARY KEY (`id`),
  ADD KEY `entity_id` (`entity_id`),
  ADD KEY `major_id` (`major_id`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `email` (`email`);

--
-- Indexes for table `visits`
--
ALTER TABLE `visits`
  ADD PRIMARY KEY (`id`),
  ADD KEY `application_id` (`application_id`),
  ADD KEY `supervisor_id` (`supervisor_id`),
  ADD KEY `student_id` (`student_id`);

--
-- Indexes for table `weekly_reports`
--
ALTER TABLE `weekly_reports`
  ADD PRIMARY KEY (`id`),
  ADD KEY `student_id` (`student_id`),
  ADD KEY `application_id` (`application_id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `activity_log`
--
ALTER TABLE `activity_log`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=40;

--
-- AUTO_INCREMENT for table `applications`
--
ALTER TABLE `applications`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=34;

--
-- AUTO_INCREMENT for table `attachments`
--
ALTER TABLE `attachments`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `attendance`
--
ALTER TABLE `attendance`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=36;

--
-- AUTO_INCREMENT for table `coordinators`
--
ALTER TABLE `coordinators`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `evaluations`
--
ALTER TABLE `evaluations`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=19;

--
-- AUTO_INCREMENT for table `interviews`
--
ALTER TABLE `interviews`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=14;

--
-- AUTO_INCREMENT for table `majors`
--
ALTER TABLE `majors`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- AUTO_INCREMENT for table `messages`
--
ALTER TABLE `messages`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- AUTO_INCREMENT for table `notifications`
--
ALTER TABLE `notifications`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- AUTO_INCREMENT for table `settings`
--
ALTER TABLE `settings`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT for table `students`
--
ALTER TABLE `students`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=12;

--
-- AUTO_INCREMENT for table `supervisors`
--
ALTER TABLE `supervisors`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `training_entities`
--
ALTER TABLE `training_entities`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `training_seats`
--
ALTER TABLE `training_seats`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=21;

--
-- AUTO_INCREMENT for table `visits`
--
ALTER TABLE `visits`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- AUTO_INCREMENT for table `weekly_reports`
--
ALTER TABLE `weekly_reports`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- قيود الجداول المحفوظة
--

--
-- القيود للجدول `activity_log`
--
ALTER TABLE `activity_log`
  ADD CONSTRAINT `activity_log_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- القيود للجدول `applications`
--
ALTER TABLE `applications`
  ADD CONSTRAINT `applications_ibfk_1` FOREIGN KEY (`student_id`) REFERENCES `students` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `applications_ibfk_2` FOREIGN KEY (`seat_id`) REFERENCES `training_seats` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `applications_ibfk_3` FOREIGN KEY (`supervisor_id`) REFERENCES `supervisors` (`id`) ON DELETE SET NULL;

--
-- القيود للجدول `attendance`
--
ALTER TABLE `attendance`
  ADD CONSTRAINT `attendance_ibfk_1` FOREIGN KEY (`student_id`) REFERENCES `students` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `attendance_ibfk_2` FOREIGN KEY (`application_id`) REFERENCES `applications` (`id`) ON DELETE CASCADE;

--
-- القيود للجدول `coordinators`
--
ALTER TABLE `coordinators`
  ADD CONSTRAINT `coordinators_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- القيود للجدول `evaluations`
--
ALTER TABLE `evaluations`
  ADD CONSTRAINT `evaluations_ibfk_1` FOREIGN KEY (`application_id`) REFERENCES `applications` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `evaluations_ibfk_2` FOREIGN KEY (`student_id`) REFERENCES `students` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `evaluations_ibfk_3` FOREIGN KEY (`issued_by`) REFERENCES `supervisors` (`id`) ON DELETE SET NULL;

--
-- القيود للجدول `interviews`
--
ALTER TABLE `interviews`
  ADD CONSTRAINT `interviews_ibfk_1` FOREIGN KEY (`application_id`) REFERENCES `applications` (`id`) ON DELETE CASCADE;

--
-- القيود للجدول `notifications`
--
ALTER TABLE `notifications`
  ADD CONSTRAINT `notifications_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- القيود للجدول `students`
--
ALTER TABLE `students`
  ADD CONSTRAINT `students_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `students_ibfk_2` FOREIGN KEY (`major_id`) REFERENCES `majors` (`id`) ON DELETE SET NULL;

--
-- القيود للجدول `supervisors`
--
ALTER TABLE `supervisors`
  ADD CONSTRAINT `supervisors_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- القيود للجدول `training_seats`
--
ALTER TABLE `training_seats`
  ADD CONSTRAINT `training_seats_ibfk_1` FOREIGN KEY (`entity_id`) REFERENCES `training_entities` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `training_seats_ibfk_2` FOREIGN KEY (`major_id`) REFERENCES `majors` (`id`) ON DELETE SET NULL;

--
-- القيود للجدول `visits`
--
ALTER TABLE `visits`
  ADD CONSTRAINT `visits_ibfk_1` FOREIGN KEY (`application_id`) REFERENCES `applications` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `visits_ibfk_2` FOREIGN KEY (`supervisor_id`) REFERENCES `supervisors` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `visits_ibfk_3` FOREIGN KEY (`student_id`) REFERENCES `students` (`id`) ON DELETE CASCADE;

--
-- القيود للجدول `weekly_reports`
--
ALTER TABLE `weekly_reports`
  ADD CONSTRAINT `weekly_reports_ibfk_1` FOREIGN KEY (`student_id`) REFERENCES `students` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `weekly_reports_ibfk_2` FOREIGN KEY (`application_id`) REFERENCES `applications` (`id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;

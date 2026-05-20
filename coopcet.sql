-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: localhost
-- Generation Time: May 20, 2026 at 01:37 PM
-- Server version: 10.5.29-MariaDB
-- PHP Version: 8.0.30

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `coopcet`
--

-- --------------------------------------------------------

--
-- Table structure for table `companies`
--

CREATE TABLE `companies` (
  `company_id` int(11) NOT NULL,
  `company_name` varchar(255) NOT NULL COMMENT 'ชื่อสถานประกอบการ',
  `address` text NOT NULL COMMENT 'ที่อยู่สถานประกอบการ',
  `latitude` varchar(50) DEFAULT NULL COMMENT 'ละติจูด (จาก OpenStreetMap)',
  `longitude` varchar(50) DEFAULT NULL COMMENT 'ลองจิจูด (จาก OpenStreetMap)',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `companies`
--

INSERT INTO `companies` (`company_id`, `company_name`, `address`, `latitude`, `longitude`, `created_at`) VALUES
(7, 'บริษัท โทรคมนาคมแห่งชาติ จำกัด ศูนย์บริการ NT เพชรบุรี 1', 'อ 6 หมู่ 5 ต.บ้านหม้อ ถนน บันไดอิฐ อำเภอเมืองเพชรบุรี 76000', '13.102023884965007', '99.93347829132085', '2026-02-23 05:43:04'),
(8, 'มาร์เก็ตวิลเลจ หัวหิน', '234/1 ถ. เพชรเกษม ตำบลหัวหิน อำเภอหัวหิน ประจวบคีรีขันธ์ 77110', '12.5582185', '99.9590496', '2026-02-25 09:32:57'),
(11, 'บลูพอร์ต หัวหิน', '8 89 ซอย หมู่บ้าน ตำบล หนองแก อำเภอหัวหิน ประจวบคีรีขันธ์ 77110', '12.5480201', '99.9616412', '2026-03-04 05:51:19'),
(21, 'ศูนย์บริการ เอ็นที หัวหิน 1', 'ซอย ดำเนินเกษม ตำบลหัวหิน ประจวบคีรีขันธ์ 77110', '12.5680986', '99.9566704', '2026-05-20 10:32:40'),
(22, 'การประปาเทศบาลนครหัวหิน', 'HX84+RMH ตำบลหัวหิน อำเภอหัวหิน ประจวบคีรีขันธ์ 77110', '12.5670725', '99.9567492', '2026-05-20 10:36:31'),
(23, 'สำนักงานเทศบาลนครหัวหิน', '114, ถ. เพชรเกษม อำเภอหัวหิน ประจวบคีรีขันธ์ 77110', '12.5679902', '99.9577201', '2026-05-20 10:38:53'),
(24, 'โรงพยาบาลพระจอมเกล้า จังหวัดเพชรบุรี', '4W9Q+68P คลองกระแชง อำเภอเมืองเพชรบุรี เพชรบุรี 76000', '13.1183697', '99.9386394', '2026-05-20 10:40:13');

-- --------------------------------------------------------

--
-- Table structure for table `daily_logs`
--

CREATE TABLE `daily_logs` (
  `log_id` int(11) NOT NULL,
  `internship_id` int(11) NOT NULL COMMENT 'อ้างอิง ID การฝึกงานของนักศึกษา',
  `log_date` date NOT NULL COMMENT 'วันที่ปฏิบัติงาน',
  `work_done` text NOT NULL COMMENT 'รายละเอียดงานที่ทำ',
  `problem_found` text DEFAULT NULL COMMENT 'ปัญหาที่พบระหว่างการทำงาน',
  `solution` text DEFAULT NULL COMMENT 'วิธีแก้ปัญหา',
  `hours_worked` decimal(4,2) NOT NULL COMMENT 'จำนวนชั่วโมงที่ทำ',
  `is_holiday` tinyint(1) DEFAULT 0 COMMENT '0=วันทำงาน, 1=วันหยุด',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `daily_logs`
--

INSERT INTO `daily_logs` (`log_id`, `internship_id`, `log_date`, `work_done`, `problem_found`, `solution`, `hours_worked`, `is_holiday`, `created_at`) VALUES
(299, 84, '2026-05-11', 'ศึกษาดูงานเบื้องต้น', '-', '-', 8.00, 0, '2026-05-19 08:29:10'),
(300, 86, '2026-05-18', 'แก้ไขปริ้นเตอร์ IP', 'ปริ้นไม่ได้อยู่คนละวงแลน', 'ตั้งวงแลนให้ถูกและเซทอัพไอพีเครื่องปริ้นและลองทดสอบ', 8.00, 0, '2026-05-19 08:50:49'),
(301, 86, '2026-05-19', 'ออกพื้นที่บริการ', 'สาย LAN ขาด', 'เชื่อมสายและจัดระเบียบใหม่', 8.00, 0, '2026-05-19 08:51:35'),
(302, 86, '2026-05-20', 'ดสะ', '-', '-', 8.00, 0, '2026-05-20 02:41:26'),
(303, 89, '2026-05-18', '123', '-', '-', 5.00, 0, '2026-05-20 02:53:28'),
(304, 89, '2026-05-19', '123', '-', '-', 8.00, 0, '2026-05-20 02:53:36'),
(305, 89, '2026-05-20', 'ehs', '-', '-', 8.00, 0, '2026-05-20 02:53:43');

-- --------------------------------------------------------

--
-- Table structure for table `internship_rounds`
--

CREATE TABLE `internship_rounds` (
  `round_id` int(11) NOT NULL,
  `academic_year` varchar(10) NOT NULL COMMENT 'ปีการศึกษา เช่น 2566',
  `start_date` date NOT NULL COMMENT 'วันเริ่มฝึกงาน',
  `end_date` date NOT NULL COMMENT 'วันสิ้นสุดฝึกงาน',
  `round_status` enum('open','closed') DEFAULT 'open' COMMENT 'สถานะรอบการฝึกงาน',
  `created_by` varchar(50) NOT NULL COMMENT 'ID อาจารย์ที่สร้างรอบนี้',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `supervision_schedule_pdf` varchar(255) DEFAULT NULL COMMENT 'ไฟล์ PDF กำหนดการนิเทศอาจารย์'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `internship_rounds`
--

INSERT INTO `internship_rounds` (`round_id`, `academic_year`, `start_date`, `end_date`, `round_status`, `created_by`, `created_at`, `supervision_schedule_pdf`) VALUES
(22, '2565', '2026-05-18', '2026-05-20', 'open', 'ad1', '2026-05-19 08:27:10', NULL),
(23, '2566', '2026-05-11', '2026-05-20', 'open', 'ad1', '2026-05-19 08:27:29', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `internship_summaries`
--

CREATE TABLE `internship_summaries` (
  `summary_id` int(11) NOT NULL,
  `internship_id` int(11) NOT NULL,
  `student_id` varchar(20) CHARACTER SET utf8 COLLATE utf8_general_ci NOT NULL,
  `internship_year` varchar(10) CHARACTER SET utf8 COLLATE utf8_general_ci NOT NULL COMMENT 'ปีที่ออกฝึกงาน เช่น 2566',
  `has_benefits` enum('yes','no') CHARACTER SET utf8 COLLATE utf8_general_ci NOT NULL,
  `benefit_details` text CHARACTER SET utf8 COLLATE utf8_general_ci DEFAULT NULL,
  `position` varchar(100) CHARACTER SET utf8 COLLATE utf8_general_ci NOT NULL,
  `student_count` int(11) NOT NULL,
  `can_publish` enum('yes','no') CHARACTER SET utf8 COLLATE utf8_general_ci NOT NULL,
  `supervisor_name` varchar(150) CHARACTER SET utf8 COLLATE utf8_general_ci NOT NULL,
  `project_file_path` varchar(255) CHARACTER SET utf8 COLLATE utf8_general_ci DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

--
-- Dumping data for table `internship_summaries`
--

INSERT INTO `internship_summaries` (`summary_id`, `internship_id`, `student_id`, `internship_year`, `has_benefits`, `benefit_details`, `position`, `student_count`, `can_publish`, `supervisor_name`, `project_file_path`, `created_at`) VALUES
(31, 72, '2651031741112', '2568', 'yes', 'น้ำดื่ม, อาหารกลางวัน', 'IT Supports', 3, 'yes', 'ผู้ช่วยศาสตราจารย์พรประสิทธิ์ บุญทอง', 'uploads/project_2651031741112_1775556034.pdf', '2026-04-07 10:00:34'),
(34, 86, '2651031741112', '2567', 'no', '', 'it Supports', 1, 'yes', 'ผู้ช่วยศาสตราจารย์ ดร.ศิริเรือง  พัฒน์ช่วย', 'uploads/project_2651031741112_1779244912.pdf', '2026-05-20 02:41:52'),
(35, 89, '2651031741145', '2567', 'no', '', 'it Supports', 3, 'yes', 'ผู้ช่วยศาสตราจารย์พรประสิทธิ์ บุญทอง', 'uploads/project_2651031741145_1779245652.pdf', '2026-05-20 02:54:12');

-- --------------------------------------------------------

--
-- Table structure for table `student_internships`
--

CREATE TABLE `student_internships` (
  `internship_id` int(11) NOT NULL,
  `student_id` varchar(50) NOT NULL COMMENT 'รหัสนักศึกษา',
  `company_id` int(11) NOT NULL COMMENT 'รหัสบริษัทที่เลือก',
  `round_id` int(11) NOT NULL COMMENT 'รหัสรอบการฝึกงาน (อ้างอิงปีการศึกษา)',
  `status` enum('active','relocating','relocated','pending','finished') DEFAULT 'active' COMMENT 'สถานะ: active=ฝึกงาน, relocating=ขอย้าย, relocated=ย้ายแล้ว, pending=รออนุมัติจบ, finished=จบฝึกงาน',
  `custom_start_date` date DEFAULT NULL COMMENT 'วันเริ่มฝึกใหม่ (กรณีอาจารย์อนุมัติย้าย)',
  `custom_end_date` date DEFAULT NULL COMMENT 'วันจบฝึกใหม่ (กรณีอาจารย์อนุมัติย้าย)',
  `relocate_reason` text DEFAULT NULL COMMENT 'เหตุผลที่ขอย้ายสถานที่ (ถ้ามี)',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `student_internships`
--

INSERT INTO `student_internships` (`internship_id`, `student_id`, `company_id`, `round_id`, `status`, `custom_start_date`, `custom_end_date`, `relocate_reason`, `created_at`) VALUES
(84, '2661031741102', 8, 23, 'active', NULL, NULL, NULL, '2026-05-19 08:28:39'),
(86, '2651031741112', 7, 22, 'finished', NULL, NULL, NULL, '2026-05-19 08:49:03'),
(89, '2651031741145', 7, 22, 'finished', NULL, NULL, NULL, '2026-05-20 02:52:25');

-- --------------------------------------------------------

--
-- Table structure for table `teacher_evaluations`
--

CREATE TABLE `teacher_evaluations` (
  `eval_id` int(11) NOT NULL,
  `student_id` varchar(50) NOT NULL COMMENT 'รหัสนักศึกษา',
  `teacher_id` varchar(50) NOT NULL COMMENT 'รหัสอาจารย์ผู้ประเมิน',
  `q1` int(11) NOT NULL COMMENT 'ปริมาณงาน (20)',
  `q2` int(11) NOT NULL COMMENT 'คุณภาพงาน (20)',
  `q3` int(11) NOT NULL COMMENT 'วิชาการ (4)',
  `q4` int(11) NOT NULL COMMENT 'เรียนรู้ประยุกต์ (4)',
  `q5` int(11) NOT NULL COMMENT 'ปฏิบัติการ (4)',
  `q6` int(11) NOT NULL COMMENT 'วิจารณญาณ (4)',
  `q7` int(11) NOT NULL COMMENT 'สื่อสาร (4)',
  `q8` int(11) NOT NULL COMMENT 'ภาษาต่างประเทศ (4)',
  `q9` int(11) NOT NULL COMMENT 'เหมาะสมกับตำแหน่ง (4)',
  `q10` int(11) NOT NULL COMMENT 'เริ่มงานด้วยตนเอง (4)',
  `q11` int(11) NOT NULL COMMENT 'รับผิดชอบไว้ใจได้ (4)',
  `q12` int(11) NOT NULL COMMENT 'อุตสาหะ (4)',
  `q13` int(11) NOT NULL COMMENT 'ตอบสนองคำสั่ง (4)',
  `q14` int(11) NOT NULL COMMENT 'บุคลิกภาพ (4)',
  `q15` int(11) NOT NULL COMMENT 'มนุษยสัมพันธ์ (4)',
  `q16` int(11) NOT NULL COMMENT 'ระเบียบวินัย (4)',
  `q17` int(11) NOT NULL COMMENT 'คุณธรรม (4)',
  `total_score` int(11) NOT NULL COMMENT 'คะแนนรวม (100)',
  `strengths` text DEFAULT NULL COMMENT 'จุดเด่น',
  `improvements` text DEFAULT NULL COMMENT 'ข้อควรปรับปรุง',
  `hire_decision` varchar(50) DEFAULT NULL COMMENT 'รับทำงานหรือไม่',
  `overall_grade` varchar(50) DEFAULT NULL COMMENT 'ภาพรวมคุณภาพ',
  `comments` text DEFAULT NULL COMMENT 'ข้อคิดเห็นเพิ่มเติม',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp() COMMENT 'เวลาที่ประเมิน'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `teacher_evaluations`
--

INSERT INTO `teacher_evaluations` (`eval_id`, `student_id`, `teacher_id`, `q1`, `q2`, `q3`, `q4`, `q5`, `q6`, `q7`, `q8`, `q9`, `q10`, `q11`, `q12`, `q13`, `q14`, `q15`, `q16`, `q17`, `total_score`, `strengths`, `improvements`, `hire_decision`, `overall_grade`, `comments`, `created_at`) VALUES
(34, '2651031741112', 'COMPANY', 20, 18, 3, 4, 3, 4, 3, 4, 3, 4, 3, 4, 4, 4, 3, 4, 4, 92, 'เก่ง', '-', 'รับ', 'ดีมาก', '-', '2026-05-19 14:52:48'),
(35, '2651031741145', 'COMPANY', 20, 20, 2, 4, 4, 4, 4, 4, 4, 4, 4, 4, 4, 4, 4, 4, 4, 98, '=', '', 'รับ', 'ดีมาก', '', '2026-05-20 03:03:14');

-- --------------------------------------------------------

--
-- Table structure for table `teacher_evaluations_log`
--

CREATE TABLE `teacher_evaluations_log` (
  `log_id` int(11) NOT NULL,
  `student_id` varchar(50) NOT NULL,
  `total_score` int(11) NOT NULL,
  `overall_grade` varchar(50) DEFAULT NULL,
  `action_type` varchar(50) NOT NULL COMMENT 'เช่น ประเมินใหม่, แก้ไขครั้งที่ 1',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `teacher_evaluations_log`
--

INSERT INTO `teacher_evaluations_log` (`log_id`, `student_id`, `total_score`, `overall_grade`, `action_type`, `created_at`) VALUES
(61, '2651031741112', 92, 'ดีมาก', 'สถานประกอบการประเมิน (ใหม่)', '2026-05-19 14:52:48'),
(62, '2651031741145', 98, 'ดีมาก', 'สถานประกอบการประเมิน (ใหม่)', '2026-05-20 03:03:14');

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `users_name` varchar(50) NOT NULL,
  `full_name` varchar(255) DEFAULT NULL,
  `phone_number` varchar(15) DEFAULT NULL COMMENT 'เบอร์โทรศัพท์ติดต่อ',
  `academic_year` varchar(10) NOT NULL,
  `password` varchar(255) NOT NULL,
  `user_level` enum('a','t','s') NOT NULL,
  `session_token` varchar(255) DEFAULT NULL,
  `app_session_token` varchar(255) DEFAULT NULL,
  `failed_login_attempts` int(11) NOT NULL DEFAULT 0 COMMENT 'จำนวนครั้งที่ล็อกอินผิด',
  `lockout_time` datetime DEFAULT NULL COMMENT 'เวลาที่บัญชีจะปลดล็อก'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`users_name`, `full_name`, `phone_number`, `academic_year`, `password`, `user_level`, `session_token`, `app_session_token`, `failed_login_attempts`, `lockout_time`) VALUES
('2651031741105', 'นายจักกรี', NULL, '2565', '$2y$10$19e.47X54MotNwqMSfiNl.sZThlJkg.RtN4LpN7hqqhMJSKHt774K', 's', NULL, NULL, 0, NULL),
('2651031741112', 'นายปัญณทัต ไชยธรรม', '0957511443', '2565', '$2y$10$DN3i5WrTp9JPx4KG2kR5seOAsV4RoeDBYiZ1Xc1imLxbr8CuMFAd.', 's', NULL, NULL, 0, NULL),
('2651031741134', 'นายธนกฤต ดีล้วน', '0929485816', '2565', '$2y$10$xvUAWy5AbKN2eoi5Y/YO0udTD/aMv2TmuIi/C6r6rjTPO3vy1XFLu', 's', NULL, NULL, 0, NULL),
('2651031741145', 'นายศิรศักดิ์   ดินแดง', '0957511443', '2565', '$2y$10$FqG0v52M0uYuoHxZvCZJ8O0jkSJ8h0D6DLvAMrIbrkfKfU1FUqTlK', 's', NULL, NULL, 0, NULL),
('2651031741156', 'นายสังหณัท', NULL, '2565', '$2y$10$PAeb7csoSyPMhAF7GYeuouKM7JJM86AXNBcJm.xg4hvzwXtHbv4C6', 's', NULL, NULL, 0, NULL),
('2651031741161', 'นายณรง สาลี', NULL, '2565', '$2y$10$D.YjpsgMPDGbeDyNXuMqPeDCCY73ki5RFrkwwf/s5dX2VtaV1WfwC', 's', NULL, NULL, 0, NULL),
('2651031741166', 'นางสาวอภัส', NULL, '2565', '$2y$10$IidKSZVL/uzeaEEs0XgBCO2ZWnJVzKQ0PdC7l2JjLRdXYh845Q/FS', 's', NULL, NULL, 0, NULL),
('2651031741191', 'นายปอนด์', NULL, '2565', '$2y$10$RoVHMetgiK6JLc2I60EMdefl..VmTjZ976Mp6JgBgRVjvat1lMrOK', 's', NULL, NULL, 0, NULL),
('2651031741195', 'นายชิติวัต ธรรมเชื้อ', NULL, '2565', '$2y$10$6/3k/xU/ycNyX2NTnHqUm.hPMt2FAg5KayZZQ2qKKtyA9c7vNfKrm', 's', NULL, NULL, 0, NULL),
('2661031741102', 'นายณภัทร ลพบุตร', NULL, '2566', '$2y$10$XsbGMoM864nlJNgCUtPR6.qjIefjwIBXu1PoIS2C7oVRXfrPHxq6.', 's', NULL, NULL, 0, NULL),
('2661031741103', 'นางสาวบัณฑิตา สังข์ทอง', NULL, '2566', '$2y$10$Riqov53EkA3KsGeomce3pO6bFiOGYa17Am.GgT4OdRbh5Ri33pkia', 's', NULL, NULL, 0, NULL),
('2661031741113', 'นายศรสิวะพงษ์ โพธิวงศ์', NULL, '2566', '$2y$10$BtSbNSrQ4Q9FNXxLoLoLEuVVPBaWV4NXC7evR2Z.iHpxb2mW3BYV6', 's', NULL, NULL, 0, NULL),
('2661031741114', 'นางสาวจุฑามาศ สุวรรณนาเวศ', NULL, '2566', '$2y$10$wb4eqyfx0yv31MMzoRBqVeMx2btcbCFW8g2n4x8ny3rU3lZ3om/na', 's', NULL, NULL, 0, NULL),
('2661031741115', 'นายทีฆทัศน์ สืบสายชล', NULL, '2566', '$2y$10$rUPEDu.b59z43qavF2wcrurpA4/MA6VH./LbMN6ZfDr6OSzrIGqCS', 's', NULL, NULL, 0, NULL),
('2661031741121', 'นายปัณณธร ศรวงศ์', NULL, '2566', '$2y$10$jSOCO7KlJ7TXTtl/triEZupbuczASBc9XfT3C35E/xTX2KVODjnEK', 's', NULL, NULL, 0, NULL),
('2661031741123', 'นายพีรยุทธ ศรีระพร', NULL, '2566', '$2y$10$3RIxzXsj/qdfFK5dengil.ZwcQQFAYc627CC.fXCRpJ.a.GF/otgm', 's', NULL, NULL, 0, NULL),
('2661031741124', 'นายสุรเดช ทองนำ', NULL, '2566', '$2y$10$mqaiE/PS4vtAx2T.vfgURuGgf.v.W2LY86e1onIV.AuLDSX.jIyvO', 's', NULL, NULL, 0, NULL),
('2661031741126', 'นางสาวเกวลี ทองเทียบ', NULL, '2566', '$2y$10$V1RZNNdYtIkGp6CBqB8FXuoMOAmzD39Z2WuRdji5urje8LhBL6omm', 's', NULL, NULL, 0, NULL),
('2661031741129', 'นายชลธาร พกมณี', NULL, '2566', '$2y$10$ftNJoVnSOyrI1AvnkqZ1ruuWliLGRowbwe8MyOAyREgGuhmEgNPsu', 's', NULL, NULL, 0, NULL),
('2661031741130', 'นายธนบดี ปลีโรย', NULL, '2566', '$2y$10$LkObzqq.CccoprWZ/xRw3uY6M6UiNJoWCRaEr/PEe6eL.AlBOu.KC', 's', NULL, NULL, 0, NULL),
('2661031741131', 'นายปิยวัฒน์ เป็กทอง', NULL, '2566', '$2y$10$60yJD0LeYTlKf/Ny26S8r.JI3tK/VSfWGoHZIfNJvunSjjV/vFhiq', 's', NULL, NULL, 0, NULL),
('2661031741132', 'นายธนิน ทองมี', NULL, '2566', '$2y$10$DIvWw0jueaYrJDt.fsdY2elv5DCm8uKqIKh45yHYV5PfJ5iH3O5q.', 's', NULL, NULL, 0, NULL),
('2661031741133', 'นายรพิพล โอภิธากรณ์', NULL, '2566', '$2y$10$gGoOrjSc85KKGD.oac55Ju3E/xbBNp2dFfthAycoLmVkO92w4ykRe', 's', NULL, NULL, 0, NULL),
('2661031741135', 'นางสาวดรุณี นามมี', NULL, '2566', '$2y$10$y53q7ShHSDml8MtEA38.9.zBMzeEHaLhtfk2OTq5k3bHdIpv9q49G', 's', NULL, NULL, 0, NULL),
('2661031741140', 'นายก้องภพ สุพะนาม', NULL, '2566', '$2y$10$G.FAZTEU7CKPIOnUBFzcee4Na1DpKBEhFV5fCTtMHM05suCjay7wW', 's', NULL, NULL, 0, NULL),
('2661031741144', 'นายธนาธิป ทองสว่าง', NULL, '2566', '$2y$10$Xc1tsy5z1KLyzNMWe4YjdOUBN4CZLCdtTLe4/60DwxPLeyhvGP19q', 's', NULL, NULL, 0, NULL),
('2661031741145', 'นางสาวปาริชาติ คล้องนอก', NULL, '2566', '$2y$10$HdwPdaCtuE2xCvk/RcANduieGsIfPMuETPijEIMvJZ7GRzS9GfmoG', 's', NULL, NULL, 0, NULL),
('2661031741149', 'นายสยมพร พูลสวัสดิ์', NULL, '2566', '$2y$10$vy3BMdSlNubo6U3xoIoAtO1FQ1WfaGQD.vH1mYVIo6277l9C9oodG', 's', NULL, NULL, 0, NULL),
('2661031741151', 'นายพงศกร แตงอ่อน', NULL, '2566', '$2y$10$rIFZdsNxhjWCrJn1VmRudesZK3Cm94TOyELybxSl7ir.sCj5Gd0dW', 's', NULL, NULL, 0, NULL),
('2661031741157', 'นายศรัณ บุญเจือ', NULL, '2566', '$2y$10$r6UPZEDFfJb2RCs82hODieSKaC26WzfpVnX3sUHKYMtrukU.WiSgK', 's', NULL, NULL, 0, NULL),
('2661031741158', 'นายระพีพัฒน์ สีทอง', NULL, '2566', '$2y$10$PzDnvIdRTwTFIStEKVgYFuzjJiyLiLupuQNwqphUzg4VDl5/C5NGO', 's', NULL, NULL, 0, NULL),
('2661031741162', 'นายพงศกร บุษบา', NULL, '2566', '$2y$10$.aeuD6V/hQeWV0U1MQn3nO7Cj1U86G1yTg8bEUFHfFFBV8MWLUVIu', 's', NULL, NULL, 0, NULL),
('2661031741165', 'นางสาวณัฐชนันพร สุวรรณพรม', NULL, '2566', '$2y$10$cP3IUjC7CC/BPCoMcYdV9epWR.IM/jEDFkBCwaX8ZKRJ6/fUKUfhq', 's', NULL, NULL, 0, NULL),
('2661031741166', 'นางสาวนัทธมน สุนทร', NULL, '2566', '$2y$10$z40mOVW8m4mtZf1oPYzI7OhdKKT/DDJMtsEURmCAAKhhJt/Gjx2PW', 's', NULL, NULL, 0, NULL),
('2661031741167', 'นายจารุตม์ ดำเนินพงศ์วิวัฒน์', NULL, '2566', '$2y$10$NipBesTeK19m8mjVaP8fzOnWFgiZZKw1jnhZxjrH1SJD6tuvMUX9G', 's', NULL, NULL, 0, NULL),
('2661031741168', 'นายรชต จันทะสิงห์', NULL, '2566', '$2y$10$XUOUCi4Cg6WdZvHjdH6Q4O8NID3oiFqXiNOYdvJqfWjADHbUBmdZq', 's', NULL, NULL, 0, NULL),
('2661031741171', 'นายรชานนท์ เกิดทอง', NULL, '2566', '$2y$10$lMLp1KHKOKnOyQh/8vbFjOCSM5qXMZSPmsbQkP3TfTcNC9HbDe3B.', 's', NULL, NULL, 0, NULL),
('2661031741174', 'นายณภัทร มณีนวล', NULL, '2566', '$2y$10$76jXgHyjAub8NDOptI5FV.pYMGumcqhAuDKN0HM5oGchVw8OGdCRG', 's', NULL, NULL, 0, NULL),
('2661031741175', 'นายณัฐภัทร เพลาขำ', NULL, '2566', '$2y$10$M6yTnv.2z6Hqe9PvqoK/pexrjfLg6lT7qOEIJ0o0UqOMU3kvkev8K', 's', NULL, NULL, 0, NULL),
('2661031741176', 'นางสาวมนธิตา พิมพ์กำเหนิด', NULL, '2566', '$2y$10$WWWQ1LZIKWAETm28SHlwVeDEMz9u8XBCP0q4wN.ZKY45YL1/8oRYO', 's', NULL, NULL, 0, NULL),
('2661031741177', 'นายเกษมสุข กองแก้ว', NULL, '2566', '$2y$10$E.IK2bUptUBAVMwi2OgrlOyzrFUeb/IZzfUi5y7PYYIoDgDr6pzqW', 's', NULL, NULL, 0, NULL),
('2661031741184', 'นายเกษมสันต์ จันทร์วงค์', NULL, '2566', '$2y$10$NVfzfZFvhSqiXB3uCYxs9.SPQQ4j1v3CcNAIbhcz7fwoSDsC4pYF2', 's', NULL, NULL, 0, NULL),
('2661031741188', 'นายกฤษดา คะณา', NULL, '2566', '$2y$10$16n/Nvd/cKbBxPr0epIhNuupKQEW3pXpBcw3j27L.MBMegkMd2H06', 's', NULL, NULL, 0, NULL),
('2661031741189', 'นายภาณุวัชร์ ดีเหลือ', NULL, '2566', '$2y$10$Yynq43NWJmXRwGfoZL/3K.kpf9X4fhiNoA5UmF90P1YWQk9NAhQKq', 's', NULL, NULL, 0, NULL),
('2661031741193', 'นายศิริโรจน์ ชุมเพ็ชร', NULL, '2566', '$2y$10$MZXts1/cr32VaKLGKfcUSe1SQrhmbPU2imj6J4MzQMU7mt0Bp.Ezu', 's', NULL, NULL, 0, NULL),
('2661031741195', 'นางสาวศรัณย์พัชญ์ อุดมทรัพย์', NULL, '2566', '$2y$10$hsHY0mB.vCLpf4zI0Eaxs.iF/P3afh9R7bGZ9wa4dKuyZmNaKtCWK', 's', NULL, NULL, 0, NULL),
('2661031741203', 'นางสาวจารุวรรณ บุญนาค', NULL, '2566', '$2y$10$yaZrWFTCI3Jg9PLZ9pciu.ES5iPgIYU0/bwDT3lHez2tyRwySCwoG', 's', NULL, NULL, 0, NULL),
('2661031741204', 'นายธนวัฒน์ กุลน้อย', NULL, '2566', '$2y$10$oAY5G01zZnlTYS9mTMV5x.ADImt6abeZ28LS7dk8q2xxwpcWDJom6', 's', NULL, NULL, 0, NULL),
('2661031741208', 'นายภาคิน โค้วเจริญ', NULL, '2566', '$2y$10$L.xpapi6p/UFO/orRgiDPegzw7jCYoI6/Rxd3XB8IwaVoJZuGDib.', 's', NULL, NULL, 0, NULL),
('2661031741209', 'นายณัฐวัฒน์ คงดี', NULL, '2566', '$2y$10$b3n.FEZWyWB2SyTMcntuweyVAFzcFYoW/9rvW0bwuPdIgqoJgEMI.', 's', NULL, NULL, 0, NULL),
('2661031741214', 'นายอนุศิษฎ์ สินทอง', NULL, '2566', '$2y$10$iow209zrqLdj1kmbYriiiOwSuVk0bg.y7pV0hZQ6Tb5YHfkwQtxO6', 's', NULL, NULL, 0, NULL),
('2661031741217', 'นายภานุพงศ์ มานันท์', NULL, '2566', '$2y$10$j0Rc2qfKGh9iz6VKptriZuD82oImTxOEU/vKo.qdbnTF5Y/gKWkry', 's', NULL, NULL, 0, NULL),
('2661031741218', 'นายพิสิษฐ์พงศ์ ขันสำรี', NULL, '2566', '$2y$10$/SYaa9meT4g5Wbh44ez9fesNJbl59WXofkvwOnX3D./IYrpkhkUkC', 's', NULL, NULL, 0, NULL),
('2661031741223', 'นายกิตติพร ปรางงาม', NULL, '2566', '$2y$10$Uabb.espOf47HVVORsReJu6xALpoUUNtu2gF4KrndUiPYh0ckxtH2', 's', NULL, NULL, 0, NULL),
('ad1', 'ธนกฤต ดีล้วน', NULL, '-', '$2y$10$N72rdpDsyPCyjNaYdJkc6uVQelz9MTcA89PlRcXM/19saCgHWcsgW', 'a', '8e5e3ed05e0de0927c8320c6897e03c89fffea2c5f696fd998da50f073388967', NULL, 0, NULL),
('ad2', 'ศิรศักดิ์   ดินแดง', NULL, '-', '$2y$10$5jqdKfryL0MYg7YJ2qvGkeHMH7ZknIKy30OtB7rurhUIAMFZ5vzQ6', 'a', NULL, NULL, 0, NULL),
('t1', 'ผู้ช่วยศาสตราจารย์ ดร.ศิริเรือง  พัฒน์ช่วย', NULL, '-', '$2y$10$Itg0yCN4QnG3CFNFDvBLD.C2xDOyiovaObyQ50qJHJbRFZzypVenK', 't', NULL, NULL, 0, NULL),
('t2', 'ผู้ช่วยศาสตราจารย์พรประสิทธิ์ บุญทอง', NULL, '-', '$2y$10$Boprbp6i53IaN4fT554/IuMKGBC6n7YhpucKFpYsVSgmX9DsIPkPq', 't', NULL, NULL, 0, NULL),
('t3', 'ผู้ช่วยศาสตราจารย์วรุตม์ บุญเลี่ยม', NULL, '-', '$2y$10$.k1oNGKEyCAcVwuoqGPwiuaTNgSdvvtZoamOB0SWEz4TckOo0.qX.', 't', NULL, NULL, 0, NULL),
('t4', 'ผู้ช่วยศาสตราจารย์ศิวะพร วิวัฒน์ภิญโญ', NULL, '-', '$2y$10$blWEMJCyySNSicVIiPy6Le/qNuM1aw2fwmQ5RmLzgApI6DM/1x.1i', 't', 'dec69b366c1543e3689992c95df80df64bf351702ce117ea92dbd359051d3308', NULL, 0, NULL),
('t5', 'ผู้ช่วยศาสตราจารย์อาทิตย์ อยู่เย็น', NULL, '-', '$2y$10$84pRvj7wh0KX32GiNOdSG..4d/qKq3XSFVH6MsfmcKehbnQ116BG.', 't', NULL, NULL, 0, NULL);

--
-- Indexes for dumped tables
--

--
-- Indexes for table `companies`
--
ALTER TABLE `companies`
  ADD PRIMARY KEY (`company_id`);

--
-- Indexes for table `daily_logs`
--
ALTER TABLE `daily_logs`
  ADD PRIMARY KEY (`log_id`),
  ADD UNIQUE KEY `unique_log_date` (`internship_id`,`log_date`);

--
-- Indexes for table `internship_rounds`
--
ALTER TABLE `internship_rounds`
  ADD PRIMARY KEY (`round_id`),
  ADD KEY `created_by` (`created_by`);

--
-- Indexes for table `internship_summaries`
--
ALTER TABLE `internship_summaries`
  ADD PRIMARY KEY (`summary_id`),
  ADD UNIQUE KEY `unique_internship` (`internship_id`);

--
-- Indexes for table `student_internships`
--
ALTER TABLE `student_internships`
  ADD PRIMARY KEY (`internship_id`),
  ADD KEY `student_id` (`student_id`),
  ADD KEY `company_id` (`company_id`),
  ADD KEY `round_id` (`round_id`);

--
-- Indexes for table `teacher_evaluations`
--
ALTER TABLE `teacher_evaluations`
  ADD PRIMARY KEY (`eval_id`),
  ADD KEY `student_id` (`student_id`);

--
-- Indexes for table `teacher_evaluations_log`
--
ALTER TABLE `teacher_evaluations_log`
  ADD PRIMARY KEY (`log_id`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`users_name`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `companies`
--
ALTER TABLE `companies`
  MODIFY `company_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=25;

--
-- AUTO_INCREMENT for table `daily_logs`
--
ALTER TABLE `daily_logs`
  MODIFY `log_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=306;

--
-- AUTO_INCREMENT for table `internship_rounds`
--
ALTER TABLE `internship_rounds`
  MODIFY `round_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=24;

--
-- AUTO_INCREMENT for table `internship_summaries`
--
ALTER TABLE `internship_summaries`
  MODIFY `summary_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=36;

--
-- AUTO_INCREMENT for table `student_internships`
--
ALTER TABLE `student_internships`
  MODIFY `internship_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=90;

--
-- AUTO_INCREMENT for table `teacher_evaluations`
--
ALTER TABLE `teacher_evaluations`
  MODIFY `eval_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=36;

--
-- AUTO_INCREMENT for table `teacher_evaluations_log`
--
ALTER TABLE `teacher_evaluations_log`
  MODIFY `log_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=63;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `daily_logs`
--
ALTER TABLE `daily_logs`
  ADD CONSTRAINT `daily_logs_ibfk_1` FOREIGN KEY (`internship_id`) REFERENCES `student_internships` (`internship_id`) ON DELETE CASCADE;

--
-- Constraints for table `internship_rounds`
--
ALTER TABLE `internship_rounds`
  ADD CONSTRAINT `internship_rounds_ibfk_1` FOREIGN KEY (`created_by`) REFERENCES `users` (`users_name`) ON DELETE CASCADE;

--
-- Constraints for table `student_internships`
--
ALTER TABLE `student_internships`
  ADD CONSTRAINT `student_internships_ibfk_1` FOREIGN KEY (`student_id`) REFERENCES `users` (`users_name`) ON DELETE CASCADE,
  ADD CONSTRAINT `student_internships_ibfk_2` FOREIGN KEY (`company_id`) REFERENCES `companies` (`company_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `student_internships_ibfk_3` FOREIGN KEY (`round_id`) REFERENCES `internship_rounds` (`round_id`) ON DELETE CASCADE;

--
-- Constraints for table `teacher_evaluations`
--
ALTER TABLE `teacher_evaluations`
  ADD CONSTRAINT `teacher_eval_student_fk` FOREIGN KEY (`student_id`) REFERENCES `users` (`users_name`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;

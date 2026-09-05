/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;
DROP TABLE IF EXISTS `announcements`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `announcements` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `created_by` bigint(20) unsigned NOT NULL,
  `title` varchar(255) NOT NULL,
  `content` text NOT NULL,
  `target_role` varchar(255) NOT NULL DEFAULT 'all' COMMENT 'all, student, supervisor, faculty, coordinator, director',
  `category` varchar(40) NOT NULL DEFAULT 'general',
  `is_pinned` tinyint(1) NOT NULL DEFAULT 0,
  `expires_at` timestamp NULL DEFAULT NULL,
  `attachment_path` varchar(255) DEFAULT NULL,
  `attachment_original_name` varchar(255) DEFAULT NULL,
  `attachment_mime` varchar(120) DEFAULT NULL,
  `attachment_size` int(10) unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `announcements_created_by_foreign` (`created_by`),
  KEY `announcements_target_role_created_at_index` (`target_role`,`created_at`),
  KEY `announcements_category_index` (`category`),
  CONSTRAINT `announcements_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `appendix_requirements`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `appendix_requirements` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `code` varchar(50) NOT NULL,
  `title` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `is_required` tinyint(1) NOT NULL DEFAULT 1,
  `display_order` int(11) NOT NULL DEFAULT 0,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `appendix_requirements_code_unique` (`code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `appendix_uploads`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `appendix_uploads` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `portfolio_id` bigint(20) unsigned NOT NULL,
  `requirement_id` bigint(20) unsigned NOT NULL,
  `file_path` varchar(255) NOT NULL,
  `file_type` varchar(50) DEFAULT NULL COMMENT 'pdf, jpg, png',
  `file_size` bigint(20) unsigned DEFAULT NULL,
  `uploaded_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `appendix_uploads_portfolio_id_requirement_id_unique` (`portfolio_id`,`requirement_id`),
  KEY `appendix_uploads_requirement_id_foreign` (`requirement_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `attendance_logs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `attendance_logs` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `internship_id` bigint(20) unsigned NOT NULL,
  `date` date NOT NULL,
  `clock_in` time DEFAULT NULL,
  `clock_out` time DEFAULT NULL,
  `am_time_in` time DEFAULT NULL,
  `am_time_out` time DEFAULT NULL,
  `pm_time_in` time DEFAULT NULL,
  `pm_time_out` time DEFAULT NULL,
  `hours_rendered` decimal(5,2) DEFAULT NULL,
  `overtime_hours` decimal(5,2) NOT NULL DEFAULT 0.00,
  `status` enum('pending','validated','rejected','flagged') NOT NULL DEFAULT 'pending',
  `remarks` text DEFAULT NULL COMMENT 'Supervisor validation remarks',
  `validated_by` bigint(20) unsigned DEFAULT NULL,
  `validated_at` timestamp NULL DEFAULT NULL,
  `clock_in_location` varchar(255) DEFAULT NULL COMMENT 'GPS coordinates or description',
  `clock_out_location` varchar(255) DEFAULT NULL,
  `student_signature_path` varchar(255) DEFAULT NULL,
  `student_signed_name` varchar(255) DEFAULT NULL,
  `student_signed_at` timestamp NULL DEFAULT NULL,
  `student_privacy_accepted_at` timestamp NULL DEFAULT NULL,
  `hte_signature_path` varchar(255) DEFAULT NULL,
  `hte_signed_name` varchar(255) DEFAULT NULL,
  `hte_signed_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_attendance_per_day` (`internship_id`,`date`),
  KEY `attendance_logs_validated_by_foreign` (`validated_by`),
  KEY `attendance_logs_internship_id_date_index` (`internship_id`,`date`),
  KEY `attendance_logs_status_index` (`status`),
  CONSTRAINT `attendance_logs_internship_id_foreign` FOREIGN KEY (`internship_id`) REFERENCES `internships` (`id`) ON DELETE CASCADE,
  CONSTRAINT `attendance_logs_validated_by_foreign` FOREIGN KEY (`validated_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `audit_logs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `audit_logs` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint(20) unsigned DEFAULT NULL,
  `action` varchar(255) NOT NULL,
  `model_type` varchar(255) DEFAULT NULL,
  `model_id` bigint(20) unsigned DEFAULT NULL,
  `old_values` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`old_values`)),
  `new_values` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`new_values`)),
  `ip_address` varchar(255) DEFAULT NULL,
  `user_agent` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `audit_logs_user_id_created_at_index` (`user_id`,`created_at`),
  KEY `audit_logs_model_type_model_id_index` (`model_type`,`model_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `cache`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `cache` (
  `key` varchar(255) NOT NULL,
  `value` mediumtext NOT NULL,
  `expiration` int(11) NOT NULL,
  PRIMARY KEY (`key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `cache_locks`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `cache_locks` (
  `key` varchar(255) NOT NULL,
  `owner` varchar(255) NOT NULL,
  `expiration` int(11) NOT NULL,
  PRIMARY KEY (`key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `companies`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `companies` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `company_name` varchar(255) NOT NULL,
  `address` varchar(255) DEFAULT NULL,
  `industry` varchar(255) DEFAULT NULL,
  `contact_person` varchar(255) DEFAULT NULL,
  `contact_email` varchar(255) DEFAULT NULL,
  `contact_number` varchar(255) DEFAULT NULL,
  `moa_status` varchar(255) NOT NULL DEFAULT 'On Process',
  `moa_start_date` date DEFAULT NULL,
  `moa_expiry_date` date DEFAULT NULL,
  `moa_file_path` varchar(255) DEFAULT NULL,
  `slots_available` int(11) NOT NULL DEFAULT 0,
  `notes` text DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `companies_moa_status_index` (`moa_status`),
  KEY `companies_is_active_index` (`is_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `conversation_participants`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `conversation_participants` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `conversation_id` bigint(20) unsigned NOT NULL,
  `user_id` bigint(20) unsigned NOT NULL,
  `last_read_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `conversation_participants_conversation_id_user_id_unique` (`conversation_id`,`user_id`),
  KEY `conversation_participants_user_id_foreign` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `conversations`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `conversations` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `internship_id` bigint(20) unsigned NOT NULL,
  `subject` varchar(255) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `conversations_internship_id_unique` (`internship_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `departments`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `departments` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `code` varchar(255) NOT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `departments_name_unique` (`name`),
  UNIQUE KEY `departments_code_unique` (`code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `document_reviews`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `document_reviews` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `document_id` bigint(20) unsigned NOT NULL,
  `stage` varchar(30) NOT NULL,
  `action` varchar(30) NOT NULL,
  `from_status` varchar(40) DEFAULT NULL,
  `to_status` varchar(40) NOT NULL,
  `remarks` text DEFAULT NULL,
  `reviewed_by` bigint(20) unsigned NOT NULL,
  `signer_name` varchar(255) DEFAULT NULL,
  `signature_path` varchar(255) DEFAULT NULL,
  `signed_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `document_reviews_reviewed_by_foreign` (`reviewed_by`),
  KEY `document_reviews_document_id_created_at_index` (`document_id`,`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `documents`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `documents` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `internship_id` bigint(20) unsigned NOT NULL,
  `document_type` varchar(255) NOT NULL,
  `week_number` int(10) unsigned DEFAULT NULL,
  `drive_link` varchar(255) DEFAULT NULL,
  `file_path` varchar(255) DEFAULT NULL,
  `file_name` varchar(255) DEFAULT NULL,
  `file_size` varchar(255) DEFAULT NULL,
  `mime_type` varchar(255) DEFAULT NULL,
  `status` varchar(40) NOT NULL DEFAULT 'pending_review',
  `remarks` text DEFAULT NULL COMMENT 'Reviewer feedback',
  `reviewed_by` bigint(20) unsigned DEFAULT NULL,
  `reviewed_at` timestamp NULL DEFAULT NULL,
  `submitted_at` timestamp NULL DEFAULT NULL,
  `attestation_name` varchar(255) DEFAULT NULL,
  `attested_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  `current_stage` varchar(30) NOT NULL DEFAULT 'coordinator',
  PRIMARY KEY (`id`),
  KEY `documents_reviewed_by_foreign` (`reviewed_by`),
  KEY `documents_internship_id_document_type_index` (`internship_id`,`document_type`),
  KEY `documents_status_index` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `evaluations`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `evaluations` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `internship_id` bigint(20) unsigned NOT NULL,
  `evaluator_type` varchar(255) DEFAULT NULL,
  `evaluated_by` bigint(20) unsigned NOT NULL,
  `evaluation_period` varchar(255) DEFAULT NULL,
  `form_type` varchar(255) NOT NULL DEFAULT 'FO-24',
  `responses` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`responses`)),
  `total_score` decimal(6,2) DEFAULT NULL COMMENT 'Computed total',
  `average_score` decimal(5,2) DEFAULT NULL,
  `rating` varchar(255) DEFAULT NULL COMMENT 'e.g. Excellent, Very Good, Good',
  `general_comments` text DEFAULT NULL,
  `submitted_at` timestamp NULL DEFAULT NULL,
  `signer_name` varchar(255) DEFAULT NULL,
  `signature_path` varchar(255) DEFAULT NULL,
  `signed_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_eval_per_period` (`internship_id`,`form_type`,`evaluator_type`,`evaluation_period`),
  KEY `evaluations_evaluated_by_foreign` (`evaluated_by`),
  KEY `evaluations_internship_id_evaluation_period_evaluator_type_index` (`internship_id`,`evaluation_period`,`evaluator_type`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `faculty_profiles`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `faculty_profiles` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint(20) unsigned NOT NULL,
  `faculty_number` varchar(255) NOT NULL COMMENT 'e.g FAC-1001',
  `first_name` varchar(255) NOT NULL,
  `middle_name` varchar(255) DEFAULT NULL,
  `last_name` varchar(255) NOT NULL,
  `suffix` varchar(30) DEFAULT NULL,
  `email` varchar(255) DEFAULT NULL,
  `contact_number` varchar(255) DEFAULT NULL,
  `sex` enum('Male','Female') DEFAULT NULL,
  `position` varchar(255) DEFAULT NULL COMMENT 'e.g CCS Faculty Supervisor, CCS Coordinator, Director, MISD Administrator',
  `employment_status` varchar(255) DEFAULT NULL,
  `synced_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `department_id` bigint(20) unsigned DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `faculty_profiles_faculty_number_unique` (`faculty_number`),
  KEY `faculty_profiles_user_id_foreign` (`user_id`),
  KEY `faculty_profiles_department_id_foreign` (`department_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `faculty_section_assignments`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `faculty_section_assignments` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `program` varchar(255) DEFAULT NULL COMMENT 'e.g. BS Information Technology',
  `section` varchar(255) NOT NULL COMMENT 'UC section code e.g. 4ITA, 4ITB, 4ITC, 4ITD',
  `school_year` varchar(255) NOT NULL COMMENT 'e.g. 2025-2026',
  `semester` varchar(255) NOT NULL COMMENT 'e.g. 1st Semester, 2nd Semester, Summer',
  `faculty_user_id` bigint(20) unsigned NOT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `fsa_program_section_term_unique` (`program`,`section`,`school_year`,`semester`) USING HASH,
  KEY `faculty_section_assignments_faculty_user_id_foreign` (`faculty_user_id`),
  KEY `faculty_section_assignments_section_school_year_semester_index` (`section`,`school_year`,`semester`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `failed_jobs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `failed_jobs` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `uuid` varchar(255) NOT NULL,
  `connection` text NOT NULL,
  `queue` text NOT NULL,
  `payload` longtext NOT NULL,
  `exception` longtext NOT NULL,
  `failed_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `failed_jobs_uuid_unique` (`uuid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `hte_requests`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `hte_requests` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `student_id` bigint(20) unsigned NOT NULL,
  `company_name` varchar(255) NOT NULL,
  `address` varchar(255) DEFAULT NULL,
  `contact_person` varchar(255) DEFAULT NULL,
  `contact_email` varchar(255) DEFAULT NULL,
  `contact_number` varchar(255) DEFAULT NULL,
  `status` varchar(255) NOT NULL DEFAULT 'pending',
  `remarks` text DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `hte_requests_student_id_foreign` (`student_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `internship_applications`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `internship_applications` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `student_id` bigint(20) unsigned NOT NULL,
  `company_id` bigint(20) unsigned NOT NULL,
  `status` varchar(255) NOT NULL DEFAULT 'pending_coordinator_approval',
  `coordinator_remarks` text DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `internship_applications_student_id_foreign` (`student_id`),
  KEY `internship_applications_company_id_foreign` (`company_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `internship_status_histories`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `internship_status_histories` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `internship_id` bigint(20) unsigned NOT NULL,
  `from_status` varchar(40) DEFAULT NULL,
  `to_status` varchar(40) NOT NULL,
  `reason` text NOT NULL,
  `changed_by` bigint(20) unsigned NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `internship_status_histories_changed_by_foreign` (`changed_by`),
  KEY `internship_status_histories_internship_id_created_at_index` (`internship_id`,`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `internships`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `internships` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `student_id` bigint(20) unsigned NOT NULL,
  `company_id` bigint(20) unsigned DEFAULT NULL,
  `supervisor_id` bigint(20) unsigned DEFAULT NULL,
  `faculty_id` bigint(20) unsigned DEFAULT NULL,
  `coordinator_id` bigint(20) unsigned DEFAULT NULL,
  `school_year` varchar(255) NOT NULL COMMENT 'e.g. 2024-2025',
  `semester` varchar(255) NOT NULL COMMENT 'e.g. 1st Semester, 2nd Semester, Summer',
  `term` varchar(255) NOT NULL COMMENT 'e.g. AY 2024-2025, 2nd Semester',
  `program` varchar(255) DEFAULT NULL,
  `target_hours` int(11) NOT NULL DEFAULT 500,
  `total_hours_rendered` decimal(8,2) NOT NULL DEFAULT 0.00,
  `status` varchar(40) NOT NULL DEFAULT 'pending_placement',
  `start_date` date DEFAULT NULL,
  `end_date` date DEFAULT NULL,
  `expected_end_date` date DEFAULT NULL,
  `termination_reason` text DEFAULT NULL,
  `final_grade` decimal(5,2) DEFAULT NULL,
  `final_remarks` varchar(255) DEFAULT NULL,
  `certificate_eligible` tinyint(1) NOT NULL DEFAULT 0,
  `certificate_issued_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  `status_reason` text DEFAULT NULL,
  `absorption_status` enum('pending','absorbed','not_hired') DEFAULT NULL,
  `absorbed_at` date DEFAULT NULL,
  `job_title` varchar(255) DEFAULT NULL,
  `absorption_notes` text DEFAULT NULL,
  `absorption_recorded_by` bigint(20) unsigned DEFAULT NULL,
  `absorption_recorded_at` timestamp NULL DEFAULT NULL,
  `absorption_recorded_by_role` varchar(30) DEFAULT NULL,
  `student_declared_hired` tinyint(1) NOT NULL DEFAULT 0,
  `student_declared_at` timestamp NULL DEFAULT NULL,
  `student_declaration_notes` text DEFAULT NULL,
  `student_declaration_proofs` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`student_declaration_proofs`)),
  PRIMARY KEY (`id`),
  KEY `internships_company_id_foreign` (`company_id`),
  KEY `internships_supervisor_id_foreign` (`supervisor_id`),
  KEY `internships_faculty_id_foreign` (`faculty_id`),
  KEY `internships_coordinator_id_foreign` (`coordinator_id`),
  KEY `internships_student_id_school_year_semester_index` (`student_id`,`school_year`,`semester`),
  KEY `internships_status_index` (`status`),
  KEY `internships_absorption_recorded_by_foreign` (`absorption_recorded_by`),
  KEY `internships_absorption_status_index` (`absorption_status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `jobs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `jobs` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `queue` varchar(255) NOT NULL,
  `payload` longtext NOT NULL,
  `attempts` tinyint(3) unsigned NOT NULL,
  `reserved_at` int(10) unsigned DEFAULT NULL,
  `available_at` int(10) unsigned NOT NULL,
  `created_at` int(10) unsigned NOT NULL,
  PRIMARY KEY (`id`),
  KEY `jobs_queue_index` (`queue`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `journal_entries`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `journal_entries` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `internship_id` bigint(20) unsigned NOT NULL,
  `entry_number` int(10) unsigned NOT NULL,
  `week_number` int(10) unsigned DEFAULT NULL,
  `date` date DEFAULT NULL,
  `end_date` date DEFAULT NULL,
  `activities_summary` text DEFAULT NULL,
  `learnings` text DEFAULT NULL COMMENT 'What the student learned',
  `challenges` text DEFAULT NULL,
  `status` enum('draft','submitted','approved','needs_revision','rejected') NOT NULL DEFAULT 'draft',
  `score` int(11) DEFAULT NULL,
  `file_path` varchar(255) DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `supervisor_feedback` text DEFAULT NULL,
  `supervisor_reviewed_by` bigint(20) unsigned DEFAULT NULL,
  `supervisor_reviewed_at` timestamp NULL DEFAULT NULL,
  `faculty_feedback` text DEFAULT NULL,
  `faculty_reviewed_by` bigint(20) unsigned DEFAULT NULL,
  `faculty_reviewed_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `journal_entries_supervisor_reviewed_by_foreign` (`supervisor_reviewed_by`),
  KEY `journal_entries_faculty_reviewed_by_foreign` (`faculty_reviewed_by`),
  KEY `journal_entries_internship_id_date_index` (`internship_id`,`date`),
  KEY `journal_entries_status_index` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `meeting_attendees`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `meeting_attendees` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `meeting_id` bigint(20) unsigned NOT NULL,
  `user_id` bigint(20) unsigned NOT NULL,
  `rsvp` varchar(20) NOT NULL DEFAULT 'pending',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `meeting_attendees_meeting_id_user_id_unique` (`meeting_id`,`user_id`),
  KEY `meeting_attendees_user_id_foreign` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `meetings`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `meetings` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `title` varchar(255) NOT NULL,
  `type` varchar(40) NOT NULL DEFAULT 'other',
  `description` text DEFAULT NULL,
  `starts_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `ends_at` timestamp NULL DEFAULT NULL,
  `location` varchar(255) DEFAULT NULL,
  `meeting_url` varchar(255) DEFAULT NULL,
  `created_by` bigint(20) unsigned NOT NULL,
  `internship_id` bigint(20) unsigned DEFAULT NULL,
  `status` varchar(30) NOT NULL DEFAULT 'scheduled',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `meetings_created_by_foreign` (`created_by`),
  KEY `meetings_internship_id_foreign` (`internship_id`),
  KEY `meetings_starts_at_status_index` (`starts_at`,`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `message_thread_states`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `message_thread_states` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint(20) unsigned NOT NULL,
  `internship_id` bigint(20) unsigned NOT NULL,
  `peer_id` bigint(20) unsigned NOT NULL,
  `archived_at` timestamp NULL DEFAULT NULL,
  `cleared_before` timestamp NULL DEFAULT NULL,
  `cleared_before_message_id` bigint(20) unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `msg_thread_states_unique` (`user_id`,`internship_id`,`peer_id`),
  KEY `message_thread_states_internship_id_foreign` (`internship_id`),
  KEY `message_thread_states_peer_id_foreign` (`peer_id`),
  KEY `message_thread_states_user_id_archived_at_index` (`user_id`,`archived_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `messages`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `messages` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `internship_id` bigint(20) unsigned NOT NULL,
  `sender_id` bigint(20) unsigned NOT NULL,
  `sender_role` varchar(40) DEFAULT NULL,
  `recipient_id` bigint(20) unsigned NOT NULL,
  `recipient_role` varchar(40) DEFAULT NULL,
  `body` text DEFAULT NULL,
  `attachment_path` varchar(255) DEFAULT NULL,
  `attachment_original_name` varchar(255) DEFAULT NULL,
  `attachment_mime` varchar(120) DEFAULT NULL,
  `attachment_size` int(10) unsigned DEFAULT NULL,
  `read_at` timestamp NULL DEFAULT NULL,
  `unsent_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `messages_recipient_id_foreign` (`recipient_id`),
  KEY `messages_internship_id_created_at_index` (`internship_id`,`created_at`),
  KEY `messages_sender_id_recipient_id_index` (`sender_id`,`recipient_id`),
  KEY `messages_internship_id_sender_role_recipient_role_index` (`internship_id`,`sender_role`,`recipient_role`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `migrations`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `migrations` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `migration` varchar(255) NOT NULL,
  `batch` int(11) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `misd_sync_logs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `misd_sync_logs` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `direction` varchar(16) NOT NULL COMMENT 'push|pull',
  `entity_type` varchar(32) NOT NULL COMMENT 'student_assignment|student|faculty',
  `entity_key` varchar(255) DEFAULT NULL COMMENT 'student_number or employee_number',
  `status` varchar(16) NOT NULL COMMENT 'success|failed',
  `actor_user_id` bigint(20) unsigned DEFAULT NULL,
  `request_payload` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`request_payload`)),
  `response_payload` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`response_payload`)),
  `error_message` text DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `misd_sync_logs_entity_type_entity_key_index` (`entity_type`,`entity_key`),
  KEY `misd_sync_logs_direction_status_index` (`direction`,`status`),
  KEY `misd_sync_logs_actor_user_id_foreign` (`actor_user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `notifications`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `notifications` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint(20) unsigned NOT NULL,
  `type` varchar(255) NOT NULL,
  `title` varchar(255) NOT NULL,
  `message` varchar(255) NOT NULL,
  `link` varchar(255) DEFAULT NULL,
  `data` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`data`)),
  `read_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `notifications_user_id_read_at_index` (`user_id`,`read_at`),
  KEY `notifications_created_at_index` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `ojt_requirement_targets`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `ojt_requirement_targets` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `requirement_template_id` bigint(20) unsigned NOT NULL,
  `target_type` enum('student','section','program') NOT NULL,
  `target_id` varchar(255) NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `ojt_requirement_targets_requirement_template_id_foreign` (`requirement_template_id`),
  KEY `ojt_requirement_targets_target_type_target_id_index` (`target_type`,`target_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `ojt_requirement_templates`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `ojt_requirement_templates` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `template_file_path` varchar(255) DEFAULT NULL,
  `drive_link` text DEFAULT NULL,
  `category` varchar(255) NOT NULL DEFAULT 'general',
  `sort_order` int(10) unsigned NOT NULL DEFAULT 0,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `deadline` datetime DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `created_by` bigint(20) unsigned DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `ojt_requirement_templates_created_by_foreign` (`created_by`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `password_reset_tokens`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `password_reset_tokens` (
  `email` varchar(255) NOT NULL,
  `token` varchar(255) NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `personal_access_tokens`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `personal_access_tokens` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `tokenable_type` varchar(255) NOT NULL,
  `tokenable_id` bigint(20) unsigned NOT NULL,
  `name` text NOT NULL,
  `token` varchar(64) NOT NULL,
  `abilities` text DEFAULT NULL,
  `last_used_at` timestamp NULL DEFAULT NULL,
  `expires_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `personal_access_tokens_token_unique` (`token`),
  KEY `personal_access_tokens_tokenable_type_tokenable_id_index` (`tokenable_type`,`tokenable_id`),
  KEY `personal_access_tokens_expires_at_index` (`expires_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `programs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `programs` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `department_id` bigint(20) unsigned NOT NULL,
  `name` varchar(255) NOT NULL,
  `code` varchar(255) NOT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `programs_name_unique` (`name`),
  UNIQUE KEY `programs_code_unique` (`code`),
  KEY `programs_department_id_foreign` (`department_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `section_change_requests`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `section_change_requests` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `internship_id` bigint(20) unsigned NOT NULL,
  `old_section` varchar(255) NOT NULL,
  `old_faculty_id` bigint(20) unsigned DEFAULT NULL,
  `new_section` varchar(255) NOT NULL,
  `new_faculty_id` bigint(20) unsigned DEFAULT NULL,
  `status` enum('pending','approved','rejected') NOT NULL DEFAULT 'pending',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `section_change_requests_internship_id_foreign` (`internship_id`),
  KEY `section_change_requests_old_faculty_id_foreign` (`old_faculty_id`),
  KEY `section_change_requests_new_faculty_id_foreign` (`new_faculty_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `sessions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `sessions` (
  `id` varchar(255) NOT NULL,
  `user_id` bigint(20) unsigned DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` text DEFAULT NULL,
  `payload` longtext NOT NULL,
  `last_activity` int(11) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `sessions_user_id_index` (`user_id`),
  KEY `sessions_last_activity_index` (`last_activity`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `student_portfolios`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `student_portfolios` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `internship_id` bigint(20) unsigned NOT NULL,
  `user_id` bigint(20) unsigned NOT NULL,
  `company_name` varchar(255) DEFAULT NULL,
  `company_address` varchar(255) DEFAULT NULL,
  `company_vision` text DEFAULT NULL,
  `company_mission` text DEFAULT NULL,
  `company_history` text DEFAULT NULL,
  `assessment_ethical` text DEFAULT NULL,
  `assessment_learnings` text DEFAULT NULL,
  `assessment_experience` text DEFAULT NULL,
  `assessment_standards` text DEFAULT NULL,
  `assessment_recommendations` text DEFAULT NULL,
  `assessment_advice` text DEFAULT NULL,
  `custom_fields` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`custom_fields`)),
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `student_portfolios_internship_id_foreign` (`internship_id`),
  KEY `student_portfolios_user_id_foreign` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `student_profiles`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `student_profiles` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint(20) unsigned NOT NULL,
  `student_number` varchar(255) NOT NULL COMMENT 'e.g 2300600',
  `first_name` varchar(255) NOT NULL,
  `middle_name` varchar(255) DEFAULT NULL,
  `last_name` varchar(255) NOT NULL,
  `suffix` varchar(30) DEFAULT NULL,
  `email` varchar(255) DEFAULT NULL,
  `contact_number` varchar(255) DEFAULT NULL,
  `birthday` date DEFAULT NULL,
  `sex` enum('Male','Female') DEFAULT NULL,
  `course_description` varchar(255) DEFAULT NULL COMMENT 'e.g IT Practicum (500 hours)',
  `year_level` tinyint(4) DEFAULT NULL COMMENT 'e.g. // 1st Year, 2nd Year, 3rd Year, 4th Year',
  `section` varchar(255) DEFAULT NULL COMMENT 'e.g 1IT-A ... 4IT-C, 4IT-D',
  `school_year` varchar(255) DEFAULT NULL COMMENT 'e.g. 2025-2026, 2026-2027',
  `semester` varchar(255) DEFAULT NULL COMMENT 'e.g. 1st Semester, 2nd Semester, Summer',
  `enrollment_status` varchar(255) DEFAULT NULL COMMENT 'e.g. Enrolled, Graduated, Dropped Out',
  `synced_at` timestamp NULL DEFAULT NULL COMMENT 'Last sync from iEnroll',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `department_id` bigint(20) unsigned DEFAULT NULL,
  `program_id` bigint(20) unsigned DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `student_profiles_student_number_unique` (`student_number`),
  KEY `student_profiles_user_id_foreign` (`user_id`),
  KEY `student_profiles_department_id_foreign` (`department_id`),
  KEY `student_profiles_program_id_foreign` (`program_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `supervisor_invite_tokens`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `supervisor_invite_tokens` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `internship_id` bigint(20) unsigned NOT NULL,
  `student_id` bigint(20) unsigned NOT NULL,
  `token` varchar(64) NOT NULL,
  `expires_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `status` enum('pending','registered','approved','rejected','expired') NOT NULL DEFAULT 'pending',
  `supervisor_user_id` bigint(20) unsigned DEFAULT NULL,
  `first_name` varchar(255) DEFAULT NULL,
  `middle_name` varchar(255) DEFAULT NULL,
  `last_name` varchar(255) DEFAULT NULL,
  `suffix` varchar(30) DEFAULT NULL,
  `email` varchar(255) DEFAULT NULL,
  `contact_number` varchar(255) DEFAULT NULL,
  `position` varchar(255) DEFAULT NULL,
  `company_id` bigint(20) unsigned DEFAULT NULL,
  `fo29_file_path` varchar(255) DEFAULT NULL,
  `reviewed_by` bigint(20) unsigned DEFAULT NULL,
  `reviewed_at` timestamp NULL DEFAULT NULL,
  `review_remarks` text DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `supervisor_invite_tokens_token_unique` (`token`),
  KEY `supervisor_invite_tokens_internship_id_foreign` (`internship_id`),
  KEY `supervisor_invite_tokens_student_id_foreign` (`student_id`),
  KEY `supervisor_invite_tokens_supervisor_user_id_foreign` (`supervisor_user_id`),
  KEY `supervisor_invite_tokens_company_id_foreign` (`company_id`),
  KEY `supervisor_invite_tokens_reviewed_by_foreign` (`reviewed_by`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `supervisor_profiles`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `supervisor_profiles` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint(20) unsigned NOT NULL,
  `first_name` varchar(255) NOT NULL,
  `middle_name` varchar(255) DEFAULT NULL,
  `last_name` varchar(255) NOT NULL,
  `suffix` varchar(30) DEFAULT NULL,
  `email` varchar(255) DEFAULT NULL,
  `contact_number` varchar(255) DEFAULT NULL,
  `sex` enum('Male','Female') DEFAULT NULL,
  `position` varchar(255) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `supervisor_profiles_user_id_foreign` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `training_records`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `training_records` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `portfolio_id` bigint(20) unsigned NOT NULL,
  `record_type` varchar(50) NOT NULL DEFAULT 'training' COMMENT 'training or certification',
  `title` varchar(255) NOT NULL,
  `provider` varchar(255) DEFAULT NULL,
  `date_completed` date DEFAULT NULL,
  `certificate_path` varchar(255) DEFAULT NULL,
  `pre_test_result` varchar(255) DEFAULT NULL,
  `post_test_result` varchar(255) DEFAULT NULL,
  `documentation_path` varchar(255) DEFAULT NULL,
  `explanation` text DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `training_records_portfolio_id_foreign` (`portfolio_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `users`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `users` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `student_number` varchar(255) DEFAULT NULL COMMENT 'e.g 2300600',
  `faculty_number` varchar(255) DEFAULT NULL COMMENT 'e.g FAC-1001, COR-1001, DIR-1001, ADMIN-MISD-001',
  `email` varchar(255) DEFAULT NULL,
  `password` varchar(255) NOT NULL,
  `sex` enum('Male','Female') DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `must_change_password` tinyint(1) NOT NULL DEFAULT 0,
  `avatar_path` varchar(255) DEFAULT NULL,
  `notification_preferences` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`notification_preferences`)),
  `last_login_at` timestamp NULL DEFAULT NULL,
  `remember_token` varchar(100) DEFAULT NULL,
  `role` enum('admin','student','faculty','supervisor','director','coordinator') NOT NULL DEFAULT 'student',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `users_student_number_unique` (`student_number`),
  UNIQUE KEY `users_faculty_number_unique` (`faculty_number`),
  UNIQUE KEY `users_email_unique` (`email`),
  KEY `users_is_active_index` (`is_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (1,'2024_01_01_000001_create_users_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (2,'2024_01_01_000002_create_profiles_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (3,'2024_01_01_000003_create_companies_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (4,'2024_01_01_000004_create_internships_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (5,'2024_01_01_000005_create_attendance_logs_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (6,'2024_01_01_000006_create_journal_entries_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (7,'2024_01_01_000007_create_documents_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (8,'2024_01_01_000008_create_evaluations_announcements_audit_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (9,'2026_07_16_085625_create_personal_access_tokens_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (10,'2026_07_16_162621_create_portfolios_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (11,'2026_07_16_162622_create_portfolio_photos_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (12,'2026_07_17_072837_adjust_journal_entries_and_portfolio_photos',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (13,'2026_07_17_082711_add_company_logo_path_to_portfolios_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (14,'2026_07_18_084109_add_notifications_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (15,'2026_07_18_084109_update_moa_status_enum_add_on_process',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (16,'2026_07_18_180000_create_supervisor_invite_tokens_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (17,'2026_07_18_190000_change_documents_type_to_string',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (18,'2026_07_18_193000_add_avatar_path_to_users_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (19,'2026_07_19_120000_internship_status_history_and_scope_statuses',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (20,'2026_07_19_120100_document_routing_stages',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (21,'2026_07_19_210000_drop_system_evaluation_tables',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (22,'2026_07_19_223000_add_absorption_fields_to_internships_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (23,'2026_07_20_120000_create_faculty_section_assignments_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (24,'2026_07_20_210000_add_sex_to_all_profiles',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (25,'2026_07_20_220000_add_notification_preferences_to_users_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (26,'2026_07_20_220000_create_misd_sync_logs_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (27,'2026_07_20_230000_add_name_parts_suffix_to_profiles',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (28,'2026_07_20_233000_add_absorption_status_index_to_internships_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (29,'2026_07_21_000100_add_notification_preferences_to_users_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (30,'2026_07_21_100000_create_messages_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (31,'2026_07_21_120000_add_roles_to_messages_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (32,'2026_07_21_130000_add_realtime_chat_meetings_esign',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (33,'2026_07_21_140000_update_internship_target_hours_to_500',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (34,'2026_07_22_100000_add_message_thread_states_and_unsent',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (35,'2026_07_22_101000_add_cleared_before_message_id_to_thread_states',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (36,'2026_07_22_160000_add_must_change_password_to_users_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (37,'2026_07_22_170000_add_fo30_dtr_fields_to_attendance_logs',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (38,'2026_07_22_180000_add_attachments_to_messages_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (39,'2026_07_22_190000_add_attachments_to_announcements_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (40,'2026_07_22_200000_add_category_to_announcements_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (41,'2026_07_23_120000_fix_college_of_computing_studies_name',2);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (42,'2026_07_23_141825_create_ojt_requirement_templates_table',3);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (43,'2026_07_24_052040_create_portfolio_generations_table',4);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (44,'2026_07_25_100000_add_company_profile_to_portfolios_table',5);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (45,'2026_07_25_100001_add_sort_order_to_portfolio_photos_table',5);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (46,'2026_07_25_110000_remove_hours_declared_from_journal_entries_table',6);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (47,'2026_07_25_200000_create_portfolio_schema_tables',6);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (48,'2026_07_25_220000_create_portfolio_tables',7);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (50,'2026_07_27_000000_create_student_portfolios_table',8);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (51,'2026_07_27_100000_add_week_number_to_documents_table',9);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (54,'2026_07_27_110000_add_week_number_to_journal_entries_table',10);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (55,'2026_07_28_081630_add_end_date_to_journal_entries_table',11);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (56,'2026_07_30_120133_add_fo29_file_path_to_supervisor_invite_tokens',12);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (57,'2026_07_30_131218_create_section_change_requests_table',12);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (58,'2026_07_31_114647_add_student_declaration_proofs_to_internships_table',13);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (59,'2026_08_01_091515_add_custom_fields_to_student_portfolios_table',14);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (60,'2026_08_03_060810_update_evaluations_table_for_new_forms',15);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (61,'2026_08_03_102719_alter_evaluations_enum_to_string',16);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (62,'2026_08_03_133339_add_unique_constraint_to_evaluations_table',17);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (63,'2026_08_03_133350_add_certificate_fields_to_internships_table',17);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (64,'2026_08_06_072239_update_companies_moa_status_to_string',18);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (65,'2026_08_06_072240_create_internship_applications_table',18);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (66,'2026_08_06_072241_create_hte_requests_table',18);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (68,'2026_08_06_090256_modify_ojt_requirement_templates_and_create_targets_table',19);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (69,'2026_08_07_140930_add_deadline_to_ojt_requirement_templates_table',20);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (70,'2026_08_07_145326_add_drive_link_to_documents_table',21);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (71,'2026_08_07_151638_add_drive_link_to_ojt_requirement_templates',22);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (72,'2026_08_10_064224_add_score_to_journal_entries_table',23);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (73,'2026_08_10_074328_create_departments_table',24);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (74,'2026_08_10_074329_create_programs_table',24);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (75,'2026_08_10_074340_update_profiles_with_academic_ids',24);

# Report 9: MySQL Database Architecture & Schema Guide

This guide defines the normalized MySQL database schema for INTERNTRACK, supporting full persistence and querying of all 271 portfolio fields.

## 1. Entity Relationship Schema (DDL)

```sql
-- Create Database
CREATE DATABASE IF NOT EXISTS interntrack_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE interntrack_db;

-- Table 1: Companies (Training Establishments)
CREATE TABLE IF NOT EXISTS companies (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    address VARCHAR(500) NOT NULL,
    logo_path VARCHAR(500) NULL,
    vision_mission TEXT NOT NULL,
    org_chart_path VARCHAR(500) NULL,
    history TEXT NOT NULL,
    created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table 2: Interns (Student Profiles)
CREATE TABLE IF NOT EXISTS interns (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id BIGINT UNSIGNED NOT NULL,
    company_id BIGINT UNSIGNED NULL,
    student_number VARCHAR(50) NOT NULL UNIQUE,
    student_name VARCHAR(255) NOT NULL,
    course VARCHAR(150) NOT NULL DEFAULT 'Bachelor of Science in Computer Science',
    section VARCHAR(50) NOT NULL,
    instructor_name VARCHAR(255) NOT NULL,
    created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_intern_company FOREIGN KEY (company_id) REFERENCES companies(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table 3: Portfolios (Master Submission Record)
CREATE TABLE IF NOT EXISTS portfolios (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    intern_id BIGINT UNSIGNED NOT NULL,
    submission_date VARCHAR(50) NOT NULL,
    student_photo_path VARCHAR(500) NOT NULL,
    status ENUM('draft', 'submitted', 'under_review', 'approved', 'rejected') DEFAULT 'draft',
    generated_docx_path VARCHAR(500) NULL,
    generated_pdf_path VARCHAR(500) NULL,
    created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_portfolio_intern FOREIGN KEY (intern_id) REFERENCES interns(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table 4: Weekly Reports (Weeks 1 to 16)
CREATE TABLE IF NOT EXISTS weekly_reports (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    portfolio_id BIGINT UNSIGNED NOT NULL,
    week_number TINYINT UNSIGNED NOT NULL,
    start_date DATE NOT NULL,
    end_date DATE NOT NULL,
    objectives TEXT NOT NULL,
    tasks TEXT NOT NULL,
    skills TEXT NOT NULL,
    problems TEXT NULL,
    solutions TEXT NULL,
    reflection TEXT NOT NULL,
    faculty_remarks TEXT NULL,
    supervisor_remarks TEXT NULL,
    created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uk_portfolio_week (portfolio_id, week_number),
    CONSTRAINT fk_weekly_portfolio FOREIGN KEY (portfolio_id) REFERENCES portfolios(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table 5: Weekly Photos
CREATE TABLE IF NOT EXISTS weekly_photos (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    weekly_report_id BIGINT UNSIGNED NOT NULL,
    photo1_path VARCHAR(500) NULL,
    photo1_caption VARCHAR(255) NULL,
    photo2_path VARCHAR(500) NULL,
    photo2_caption VARCHAR(255) NULL,
    created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_photos_weekly FOREIGN KEY (weekly_report_id) REFERENCES weekly_reports(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table 6: Assessments (Chapter III)
CREATE TABLE IF NOT EXISTS assessments (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    portfolio_id BIGINT UNSIGNED NOT NULL UNIQUE,
    professional_ethics TEXT NOT NULL,
    it_learnings TEXT NOT NULL,
    people_experience TEXT NOT NULL,
    industry_standards TEXT NOT NULL,
    recommendations TEXT NOT NULL,
    advice TEXT NOT NULL,
    created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_assessment_portfolio FOREIGN KEY (portfolio_id) REFERENCES portfolios(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table 7: Appendices (Scanned Forms & Certificates)
CREATE TABLE IF NOT EXISTS appendices (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    portfolio_id BIGINT UNSIGNED NOT NULL UNIQUE,
    registration_form_path VARCHAR(500) NULL,
    medical_result_path VARCHAR(500) NULL,
    psychological_result_path VARCHAR(500) NULL,
    application_letter_path VARCHAR(500) NULL,
    cv_path VARCHAR(500) NULL,
    rec_letter_path VARCHAR(500) NULL,
    acceptance_form_path VARCHAR(500) NULL,
    consent_form_path VARCHAR(500) NULL,
    training_plan_path VARCHAR(500) NULL,
    dtr_path VARCHAR(500) NULL,
    perf_eval_path VARCHAR(500) NULL,
    moa_path VARCHAR(500) NULL,
    visitation_form_path VARCHAR(500) NULL,
    cert_completion_path VARCHAR(500) NULL,
    host_eval_path VARCHAR(500) NULL,
    prog_eval_path VARCHAR(500) NULL,
    ojt_photos_path VARCHAR(500) NULL,
    training_cert_path VARCHAR(500) NULL,
    training_pretest_path VARCHAR(500) NULL,
    training_posttest_path VARCHAR(500) NULL,
    training_doc1_path VARCHAR(500) NULL,
    training_doc2_path VARCHAR(500) NULL,
    cert_exam_path VARCHAR(500) NULL,
    certification_path VARCHAR(500) NULL,
    exam_doc1_path VARCHAR(500) NULL,
    exam_doc2_path VARCHAR(500) NULL,
    created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_appendix_portfolio FOREIGN KEY (portfolio_id) REFERENCES portfolios(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table 8: System Settings (University Configuration)
CREATE TABLE IF NOT EXISTS system_settings (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    key_name VARCHAR(100) NOT NULL UNIQUE,
    value_text TEXT NOT NULL,
    updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Insert default UC Vision and Mission
INSERT IGNORE INTO system_settings (key_name, value_text) VALUES
('uc_vision', 'A premier institution of higher learning in the region producing globally competitive, morally upright, and service-oriented professionals.'),
('uc_mission', 'The University of Cabuyao is committed to provide quality academic programs, promote research and extension services, and foster holistic student development.');
```

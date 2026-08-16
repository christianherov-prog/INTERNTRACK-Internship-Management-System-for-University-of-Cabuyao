# Report 3: Complete Placeholder Mapping Table

This architecture maps all 271 template placeholders across the frontend React application, backend Laravel request parameters, validation schemas, and MySQL database storage layer.

*(Note: Weekly progress report rows for Weeks 3 through 16 follow the identical mapping pattern shown for Weeks 1 and 2).*

| Placeholder | Description | React Field | Laravel Request | Validation Rule | Database Table | Database Column | Data Type | Image | Required |
|---|---|---|---|---|---|---|---|---|---|
| `{{student_name}}` | Student full name | `studentName` | `$request->student_name` | `required|string|max:255` | `interns` | `student_name` | `VARCHAR(255)` | No | Yes |
| `{{student_number}}` | Student ID Number | `studentNumber` | `$request->student_number` | `required|string|max:50` | `interns` | `student_number` | `VARCHAR(50)` | No | Yes |
| `{{course}}` | Degree program | `course` | `$request->course` | `required|string|max:150` | `interns` | `course` | `VARCHAR(150)` | No | Yes |
| `{{section}}` | Year and Section | `section` | `$request->section` | `required|string|max:50` | `interns` | `section` | `VARCHAR(50)` | No | Yes |
| `{{internship_instructor}}` | Instructor name | `instructorName` | `$request->instructor_name` | `required|string|max:255` | `interns` | `instructor_name` | `VARCHAR(255)` | No | Yes |
| `{{submission_month_year}}` | Submission date | `submissionDate` | `$request->submission_date` | `required|string|max:50` | `portfolios` | `submission_date` | `VARCHAR(50)` | No | Yes |
| `{{student_photo}}` | Student photo | `studentPhoto` | `$request->file('student_photo')` | `required|image|mimes:jpeg,png,jpg|max:5120` | `portfolios` | `student_photo_path` | `VARCHAR(500)` | Yes | Yes |
| `{{company_name}}` | Company name | `companyName` | `$request->company_name` | `required|string|max:255` | `companies` | `name` | `VARCHAR(255)` | No | Yes |
| `{{company_address}}` | Company address | `companyAddress` | `$request->company_address` | `required|string|max:500` | `companies` | `address` | `VARCHAR(500)` | No | Yes |
| `{{company_logo}}` | Company logo | `companyLogo` | `$request->file('company_logo')` | `nullable|image|mimes:jpeg,png,jpg|max:5120` | `companies` | `logo_path` | `VARCHAR(500)` | Yes | No |
| `{{company_vision_mission}}` | Vision & mission | `companyVisionMission` | `$request->vision_mission` | `required|string` | `companies` | `vision_mission` | `TEXT` | No | Yes |
| `{{organizational_chart}}` | Org chart image | `orgChart` | `$request->file('org_chart')` | `nullable|image|mimes:jpeg,png,jpg|max:10240` | `companies` | `org_chart_path` | `VARCHAR(500)` | Yes | No |
| `{{company_history}}` | Company history | `companyHistory` | `$request->company_history` | `required|string` | `companies` | `history` | `TEXT` | No | Yes |
| `{{uc_vision}}` | UC Vision statement | `ucVision` | `$request->uc_vision` | `required|string` | `settings` | `uc_vision` | `TEXT` | No | Yes |
| `{{uc_mission}}` | UC Mission statement | `ucMission` | `$request->uc_mission` | `required|string` | `settings` | `uc_mission` | `TEXT` | No | Yes |
| `{{week1_start_date}}` | Week 1 start date | `week1StartDate` | `$request->week1_start_date` | `required|date` | `weekly_reports` | `start_date` | `DATE` | No | Yes |
| `{{week1_end_date}}` | Week 1 end date | `week1EndDate` | `$request->week1_end_date` | `required|date|after_or_equal:start_date` | `weekly_reports` | `end_date` | `DATE` | No | Yes |
| `{{week1_objectives}}` | Week 1 objectives | `week1Objectives` | `$request->week1_objectives` | `required|string` | `weekly_reports` | `objectives` | `TEXT` | No | Yes |
| `{{week1_tasks}}` | Week 1 tasks | `week1Tasks` | `$request->week1_tasks` | `required|string` | `weekly_reports` | `tasks` | `TEXT` | No | Yes |
| `{{week1_skills}}` | Week 1 skills | `week1Skills` | `$request->week1_skills` | `required|string` | `weekly_reports` | `skills` | `TEXT` | No | Yes |
| `{{week1_problems}}` | Week 1 problems | `week1Problems` | `$request->week1_problems` | `nullable|string` | `weekly_reports` | `problems` | `TEXT` | No | No |
| `{{week1_solutions}}` | Week 1 solutions | `week1Solutions` | `$request->week1_solutions` | `nullable|string` | `weekly_reports` | `solutions` | `TEXT` | No | No |
| `{{week1_reflection}}` | Week 1 reflection | `week1Reflection` | `$request->week1_reflection` | `required|string` | `weekly_reports` | `reflection` | `TEXT` | No | Yes |
| `{{week1_faculty_remarks}}` | Week 1 faculty remarks | `week1FacultyRemarks` | `$request->week1_faculty_remarks` | `nullable|string` | `weekly_reports` | `faculty_remarks` | `TEXT` | No | No |
| `{{week1_supervisor_remarks}}` | Week 1 supervisor remarks | `week1SupervisorRemarks` | `$request->week1_supervisor_remarks` | `nullable|string` | `weekly_reports` | `supervisor_remarks` | `TEXT` | No | No |
| `{{week1_photo1}}` | Week 1 photo 1 | `week1Photo1` | `$request->file('week1_photo1')` | `nullable|image|mimes:jpeg,png,jpg|max:5120` | `weekly_photos` | `photo1_path` | `VARCHAR(500)` | Yes | No |
| `{{week1_photo1_caption}}` | Week 1 photo 1 caption | `week1Photo1Caption` | `$request->week1_photo1_caption` | `nullable|string|max:255` | `weekly_photos` | `photo1_caption` | `VARCHAR(255)` | No | No |
| `{{week1_photo2}}` | Week 1 photo 2 | `week1Photo2` | `$request->file('week1_photo2')` | `nullable|image|mimes:jpeg,png,jpg|max:5120` | `weekly_photos` | `photo2_path` | `VARCHAR(500)` | Yes | No |
| `{{week1_photo2_caption}}` | Week 1 photo 2 caption | `week1Photo2Caption` | `$request->week1_photo2_caption` | `nullable|string|max:255` | `weekly_photos` | `photo2_caption` | `VARCHAR(255)` | No | No |
| `{{week2_start_date}}` | Week 2 start date | `week2StartDate` | `$request->week2_start_date` | `required|date` | `weekly_reports` | `start_date` | `DATE` | No | Yes |
| `{{week2_end_date}}` | Week 2 end date | `week2EndDate` | `$request->week2_end_date` | `required|date|after_or_equal:start_date` | `weekly_reports` | `end_date` | `DATE` | No | Yes |
| `{{week2_objectives}}` | Week 2 objectives | `week2Objectives` | `$request->week2_objectives` | `required|string` | `weekly_reports` | `objectives` | `TEXT` | No | Yes |
| `{{week2_tasks}}` | Week 2 tasks | `week2Tasks` | `$request->week2_tasks` | `required|string` | `weekly_reports` | `tasks` | `TEXT` | No | Yes |
| `{{week2_skills}}` | Week 2 skills | `week2Skills` | `$request->week2_skills` | `required|string` | `weekly_reports` | `skills` | `TEXT` | No | Yes |
| `{{week2_problems}}` | Week 2 problems | `week2Problems` | `$request->week2_problems` | `nullable|string` | `weekly_reports` | `problems` | `TEXT` | No | No |
| `{{week2_solutions}}` | Week 2 solutions | `week2Solutions` | `$request->week2_solutions` | `nullable|string` | `weekly_reports` | `solutions` | `TEXT` | No | No |
| `{{week2_reflection}}` | Week 2 reflection | `week2Reflection` | `$request->week2_reflection` | `required|string` | `weekly_reports` | `reflection` | `TEXT` | No | Yes |
| `{{week2_faculty_remarks}}` | Week 2 faculty remarks | `week2FacultyRemarks` | `$request->week2_faculty_remarks` | `nullable|string` | `weekly_reports` | `faculty_remarks` | `TEXT` | No | No |
| `{{week2_supervisor_remarks}}` | Week 2 supervisor remarks | `week2SupervisorRemarks` | `$request->week2_supervisor_remarks` | `nullable|string` | `weekly_reports` | `supervisor_remarks` | `TEXT` | No | No |
| `{{week2_photo1}}` | Week 2 photo 1 | `week2Photo1` | `$request->file('week2_photo1')` | `nullable|image|mimes:jpeg,png,jpg|max:5120` | `weekly_photos` | `photo1_path` | `VARCHAR(500)` | Yes | No |
| `{{week2_photo1_caption}}` | Week 2 photo 1 caption | `week2Photo1Caption` | `$request->week2_photo1_caption` | `nullable|string|max:255` | `weekly_photos` | `photo1_caption` | `VARCHAR(255)` | No | No |
| `{{week2_photo2}}` | Week 2 photo 2 | `week2Photo2` | `$request->file('week2_photo2')` | `nullable|image|mimes:jpeg,png,jpg|max:5120` | `weekly_photos` | `photo2_path` | `VARCHAR(500)` | Yes | No |
| `{{week2_photo2_caption}}` | Week 2 photo 2 caption | `week2Photo2Caption` | `$request->week2_photo2_caption` | `nullable|string|max:255` | `weekly_photos` | `photo2_caption` | `VARCHAR(255)` | No | No |
| `{{assessment_professional_ethics}}` | Professional ethics essay | `professionalEthics` | `$request->professional_ethics` | `required|string` | `assessments` | `professional_ethics` | `TEXT` | No | Yes |
| `{{assessment_it_learnings}}` | IT learnings essay | `itLearnings` | `$request->it_learnings` | `required|string` | `assessments` | `it_learnings` | `TEXT` | No | Yes |
| `{{assessment_people_experience}}` | People experience essay | `peopleExperience` | `$request->people_experience` | `required|string` | `assessments` | `people_experience` | `TEXT` | No | Yes |
| `{{assessment_industry_standards}}` | Industry standards essay | `industryStandards` | `$request->industry_standards` | `required|string` | `assessments` | `industry_standards` | `TEXT` | No | Yes |
| `{{assessment_recommendations}}` | Recommendations essay | `recommendations` | `$request->recommendations` | `required|string` | `assessments` | `recommendations` | `TEXT` | No | Yes |
| `{{assessment_advice}}` | Advice to interns essay | `advice` | `$request->advice` | `required|string` | `assessments` | `advice` | `TEXT` | No | Yes |
| `{{registration_form}}` | Registration form image | `registrationForm` | `$request->file('registration_form')` | `required|image|mimes:jpeg,png,jpg|max:10240` | `appendices` | `registration_form_path` | `VARCHAR(500)` | Yes | Yes |
| `{{curriculum_vitae}}` | Curriculum vitae image | `curriculumVitae` | `$request->file('curriculum_vitae')` | `required|image|mimes:jpeg,png,jpg|max:10240` | `appendices` | `cv_path` | `VARCHAR(500)` | Yes | Yes |
| `{{certificate_completion}}` | Completion cert image | `certificateCompletion` | `$request->file('certificate_completion')` | `required|image|mimes:jpeg,png,jpg|max:10240` | `appendices` | `cert_completion_path` | `VARCHAR(500)` | Yes | Yes |

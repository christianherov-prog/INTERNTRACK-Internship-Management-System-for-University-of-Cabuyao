# INTERNTRACK HTE Model Generalization Plan

**Date:** September 3, 2026  
**Status:** DRAFT - Awaiting Review Before Implementation

---

## Executive Summary

This plan transforms INTERNTRACK's current single-HTE model (1 company, 1 hours target per student) into a flexible program-driven model where each Program defines N required placements, each with its own label and target hours. The design ensures **ZERO regression** for existing CCS/IT features while enabling multi-placement workflows for Education, Nursing, and Psychology programs.

---

## 1. Storage Schema

### 1.1 Program-Level Requirements: `program_hte_requirements` Table

**Purpose:** Define the HTE placement structure for each program (e.g., "BSIT needs 500h at 1 HTE", "Nursing needs 5 HTEs of 540.6h each").

```sql
CREATE TABLE program_hte_requirements (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    program_id BIGINT UNSIGNED NOT NULL,
    sequence_order INT NOT NULL DEFAULT 1,
    label VARCHAR(255) NOT NULL,  -- e.g., "Primary Internship", "Public School HTE", "HTE 1"
    required_hours DECIMAL(8,2) NOT NULL DEFAULT 0.00,
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,
    
    FOREIGN KEY (program_id) REFERENCES programs(id) ON DELETE CASCADE,
    UNIQUE KEY unique_program_sequence (program_id, sequence_order)
);
```

**Key Fields:**
- `program_id`: Links to the `programs` table
- `sequence_order`: Determines the order of placements (1, 2, 3...). For single-HTE programs, this is always 1
- `label`: Display name for this placement (e.g., "Clinical Setting", "Public School HTE")
- `required_hours`: Target hours for this specific placement

**Example Rows:**

| id | program_id | sequence_order | label | required_hours |
|----|------------|----------------|-------|----------------|
| 1  | 1 (BSIT)   | 1              | Primary Internship | 500.00 |
| 2  | 2 (BSCS)   | 1              | Primary Internship | 300.00 |
| 3  | 8 (Psychology) | 1          | Clinical Setting | 150.00 |
| 4  | 8 (Psychology) | 2          | Educational Setting | 150.00 |
| 5  | 8 (Psychology) | 3          | Industrial/Organizational Setting | 150.00 |

### 1.2 Student-Level Placements: `internship_placements` Table

**Purpose:** Track each actual HTE assignment per student with accumulated hours per placement.

```sql
CREATE TABLE internship_placements (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    internship_id BIGINT UNSIGNED NOT NULL,
    program_hte_requirement_id BIGINT UNSIGNED NOT NULL,
    company_id BIGINT UNSIGNED NULL,  -- nullable: student may not have company assigned yet
    supervisor_id BIGINT UNSIGNED NULL,
    sequence_order INT NOT NULL,
    label VARCHAR(255) NOT NULL,  -- copied from requirement for historical record
    required_hours DECIMAL(8,2) NOT NULL,
    accumulated_hours DECIMAL(8,2) NOT NULL DEFAULT 0.00,
    status VARCHAR(50) NOT NULL DEFAULT 'pending',  -- pending, active, completed
    start_date DATE NULL,
    end_date DATE NULL,
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,
    
    FOREIGN KEY (internship_id) REFERENCES internships(id) ON DELETE CASCADE,
    FOREIGN KEY (program_hte_requirement_id) REFERENCES program_hte_requirements(id),
    FOREIGN KEY (company_id) REFERENCES companies(id) ON DELETE SET NULL,
    FOREIGN KEY (supervisor_id) REFERENCES users(id) ON DELETE SET NULL,
    
    UNIQUE KEY unique_internship_sequence (internship_id, sequence_order)
);
```

**Key Fields:**
- `internship_id`: Links to the existing `internships` table (parent internship record)
- `program_hte_requirement_id`: Links to the program's requirement definition
- `company_id`, `supervisor_id`: Specific HTE assignment for this placement
- `sequence_order`, `label`, `required_hours`: Copied from requirement for historical integrity
- `accumulated_hours`: Hours completed for THIS placement only
- `status`: Lifecycle of this placement (pending → active → completed)

### 1.3 Modifications to Existing `internships` Table

**Keep existing columns for backward compatibility:**
- `company_id`, `supervisor_id`: Will represent the **primary/current** placement
- `target_hours`: Becomes the **total sum** of all required placement hours for this student's program
- `total_hours_rendered`: Becomes the **cumulative sum** of all placement hours

**New columns:**
```sql
ALTER TABLE internships 
ADD COLUMN current_placement_id BIGINT UNSIGNED NULL AFTER supervisor_id,
ADD FOREIGN KEY (current_placement_id) REFERENCES internship_placements(id) ON DELETE SET NULL;
```

**Rationale:** 
- Existing queries that reference `internships.target_hours` and `internships.total_hours_rendered` continue to work
- For single-HTE programs (BSIT, BSCS, etc.), there's exactly 1 placement, so these values match the placement's hours
- For multi-HTE programs, these are aggregated totals

### 1.4 Modifications to `attendance_logs` Table

**Current structure:** `attendance_logs` has `internship_id` FK.

**New column:**
```sql
ALTER TABLE attendance_logs
ADD COLUMN placement_id BIGINT UNSIGNED NULL AFTER internship_id,
ADD FOREIGN KEY (placement_id) REFERENCES internship_placements(id) ON DELETE SET NULL;
```

**Migration strategy:**
- For existing records, `placement_id` will be NULL initially (legacy DTR entries)
- New attendance logs will specify which placement the hours apply to
- Attendance calculation logic will sum hours per placement

---

## 2. Single-Placement Programs: N=1 Case (No Regression)

**Programs:** BSIT (500h), BSCS (300h), BSBA-Marketing (600h), BSBA-Financial Mgmt (600h), BS Accountancy (400h)

### 2.1 Data Initialization

**Seeder or Migration:** For each single-HTE program, create one `program_hte_requirements` row:

```php
// Example for BSIT
ProgramHteRequirement::create([
    'program_id' => Program::where('code', 'BSIT')->first()->id,
    'sequence_order' => 1,
    'label' => 'Primary Internship',
    'required_hours' => 500.00,
]);
```

### 2.2 Internship Creation Flow

When a new BSIT student gets an internship:

1. Create `internships` record (existing flow):
   - `target_hours` = 500 (sum of all requirements, which is just 1)
   - `company_id`, `supervisor_id` = assigned company

2. **NEW:** Auto-create one `internship_placements` row:
   - `internship_id` = the new internship ID
   - `program_hte_requirement_id` = the BSIT requirement row
   - `sequence_order` = 1
   - `label` = "Primary Internship"
   - `required_hours` = 500
   - `company_id`, `supervisor_id` = same as internship (sync)
   - `status` = 'active'

3. Set `internships.current_placement_id` = the new placement ID

### 2.3 Hours Accumulation

**Attendance validation:**
- When supervisor validates DTR, hours are added to:
  1. `attendance_logs.placement_id` = current_placement_id
  2. `internship_placements.accumulated_hours` += hours
  3. `internships.total_hours_rendered` += hours (keep synced for existing queries)

**Existing dashboard/certificate logic continues to work:**
- `$internship->total_hours_rendered` still reflects total hours
- `$internship->target_hours` still reflects total target
- Progress % = `(total_hours_rendered / target_hours) * 100`

### 2.4 Zero-Regression Guarantee

**Features that must remain unchanged:**

| Feature | Current Behavior | After Migration |
|---------|------------------|-----------------|
| Student Dashboard "Hours Rendered" | `{$internship->total_hours_rendered}/{$internship->target_hours}` | **Same** (totals remain at internship level) |
| Certificate eligibility check | `$internship->total_hours_rendered >= $internship->target_hours` | **Same** (totals used) |
| Faculty/Coord monitoring | Display `hours_rendered / target_hours` | **Same** (use internship-level totals) |
| DTR submission | Adds hours to `attendance_logs`, updates `internships.total_hours_rendered` | **Enhanced:** Also updates `internship_placements.accumulated_hours` |
| Progress percentage | `round(($total_hours / $target_hours) * 100, 1)` | **Same** (calculation unchanged) |

**Critical: Existing CCS/IT test accounts will see:**
- Same target hours (500 for BSIT)
- Same accumulated hours
- Same progress %
- Same certificate eligibility
- No UI changes (single placement is implicit)

---

## 3. Multi-Placement Programs: N>1 Case

**Programs:** 
- Education (BSED/BEED): 2 placements (Public School HTE 180h, Private School HTE 180h) = 360h total
- Nursing: 5 placements (HTE 1-5, 540.6h each) = 2,703h total
- Psychology: 3 placements (Clinical 150h, Educational 150h, Industrial/Organizational 150h) = 450h total

### 3.1 Data Initialization

**Example for Psychology (BSPSY):**

```php
$program = Program::where('code', 'BSPSY')->first();
ProgramHteRequirement::insert([
    ['program_id' => $program->id, 'sequence_order' => 1, 'label' => 'Clinical Setting', 'required_hours' => 150.00],
    ['program_id' => $program->id, 'sequence_order' => 2, 'label' => 'Educational Setting', 'required_hours' => 150.00],
    ['program_id' => $program->id, 'sequence_order' => 3, 'label' => 'Industrial/Organizational Setting', 'required_hours' => 150.00],
]);
```

### 3.2 Internship Creation & Placement Assignment

**Initial setup:**
1. Create `internships` record:
   - `target_hours` = 450 (sum of 150+150+150)
   - `company_id`, `supervisor_id` = NULL initially (no company assigned yet)
   - `status` = 'pending_placement' or 'active' (depends on workflow)

2. Auto-create 3 `internship_placements` rows (one per requirement):
   - Placement 1: Clinical Setting, 150h, status='pending'
   - Placement 2: Educational Setting, 150h, status='pending'
   - Placement 3: Industrial/Organizational Setting, 150h, status='pending'

3. **Sequential activation:** Student/coordinator assigns company for Placement 1:
   - `placement_1.company_id` = Company A
   - `placement_1.supervisor_id` = Supervisor X
   - `placement_1.status` = 'active'
   - `internships.current_placement_id` = placement_1.id
   - `internships.company_id` = Company A (keep synced for legacy queries)

### 3.3 Hours Tracking Across Placements

**DTR submission:**
- When student submits DTR, it's tagged with `placement_id` = current_placement_id
- Supervisor validates → hours added to:
  - `attendance_logs.placement_id` = placement_1.id
  - `placement_1.accumulated_hours` += hours
  - `internships.total_hours_rendered` += hours

**Transitioning to next placement:**
- When `placement_1.accumulated_hours >= placement_1.required_hours`:
  - `placement_1.status` = 'completed'
  - Activate placement 2:
    - `placement_2.company_id` = Company B (may be same or different)
    - `placement_2.status` = 'active'
    - `internships.current_placement_id` = placement_2.id
    - `internships.company_id` = Company B

**Completion:**
- When all placements are 'completed':
  - `internships.status` = 'completed'
  - `internships.total_hours_rendered` = sum of all placement hours

### 3.4 Dashboard Display for Multi-Placement Students

**Current Internship Card (enhanced):**

```
┌─────────────────────────────────────────────────┐
│ CURRENT INTERNSHIP                              │
├─────────────────────────────────────────────────┤
│ Overall Progress: 210 / 450 hrs (47%)          │
│                                                  │
│ Active Placement: Educational Setting (Rotation 2)│
│ Company: ABC Guidance Center                    │
│ Supervisor: Jane Doe                            │
│ This Placement: 60 / 150 hrs (40%)              │
│                                                  │
│ Rotation History:                               │
│ ✓ Clinical Setting: 150/150 hrs (Complete)     │
│ → Educational Setting: 60/150 hrs (Active)     │
│   Industrial/Organizational: 0/150 hrs (Pending)│
└─────────────────────────────────────────────────┘
```

**Stats Cards:**
- "Hours Rendered" stat: Show **total** hours (210/450)
- **NEW:** "Current Rotation Progress" stat: Show placement-specific hours (60/150)

### 3.5 Attendance/DTR View

**Enhancement:** DTR page will display which placement the attendance belongs to.

```
Date       | In    | Out   | Hours | Placement
-----------|-------|-------|-------|---------------------------
2026-09-01 | 08:00 | 17:00 | 8.0   | Clinical Setting
2026-09-02 | 08:00 | 17:00 | 8.0   | Clinical Setting
...
2026-09-15 | 08:00 | 16:00 | 7.0   | Educational Setting
```

### 3.6 Faculty/Coordinator Monitoring

**Enhanced monitoring table:**

| Student | Program | Overall Progress | Current Placement | Placement Progress |
|---------|---------|------------------|-------------------|--------------------|
| John Doe | Psychology | 210/450 (47%) | Educational Setting | 60/150 (40%) |
| Jane Smith | Nursing | 1,081.2/2,703 (40%) | HTE 2 | 540.6/540.6 (100%) |

---

## 4. Education College Confirmation

**Finding:** The codebase already has:
- **Department:** College of Education (COED), code 'COED'
- **Programs:**
  - Bachelor of Secondary Education (BSED)
      MAJORS:
      Social Studies 
      Filipino
      English 
      Mathematics 
  - Bachelor of Elementary Education (BEED)

**Location:** `backend/database/seeders/DatabaseSeeder.php`, lines 61-63

**HTE Structure to Apply:**

| Program | Placement 1 | Placement 2 | Total |
|---------|-------------|-------------|-------|
| BSED    | Public School HTE (180h) | Private School HTE (180h) | 360h |
| BEED    | Public School HTE (180h) | Private School HTE (180h) | 360h |

**Note:** Both BSED and BEED will have the same 2-placement structure with identical labels and hours.

---

## 5. Psychology "Industrial Setting" Label Reconciliation

**Current State:**
- Psychology Portfolio (`frontend/src/pages/student/portfolio/psychologyPortfolioStructure.js`, line 9):
  - Rotation 3: "Industrial Setting"

**Spec Requirement:**
- HTE Requirement label: "Industrial/Organizational Setting"

### Recommendation: Update Portfolio to Match Spec

**Rationale:**
1. "Industrial/Organizational Setting" is more accurate (reflects both Industrial and Organizational Psychology applications)
2. Portfolio content already built, only the label needs changing
3. HTE model will use the full label for consistency across the system

**Files to Update:**

| File | Line | Change |
|------|------|--------|
| `frontend/src/pages/student/portfolio/psychologyPortfolioStructure.js` | 9 | `{ id: 3, key: 'r3', title: 'Rotation 3: Industrial/Organizational Setting' }` |
| Any Psychology portfolio display components | Various | Update "Industrial Setting" → "Industrial/Organizational Setting" |

**Database Impact:** None (portfolio data is stored as JSON custom_fields, no hardcoded labels in schema)

**User Impact:** Students with existing portfolio data will see the updated label, but their saved content remains intact.

---

## 6. Features Requiring Updates

### 6.1 Student-Facing Features

| Feature | Current Assumption | Required Change | Priority |
|---------|-------------------|-----------------|----------|
| **Student Dashboard** (`StudentDashboard.jsx`) | Shows single company/hours target | Add placement breakdown UI for multi-HTE programs | HIGH |
| **Attendance/DTR Page** | Links to one internship | Tag each log with `placement_id`; display placement label | HIGH |
| **My Records** | Shows one internship row | Display current + past placements if multi-HTE | MEDIUM |
| **Certificates** | Checks total hours ≥ target | No change (uses internship totals) | LOW (no change) |
| **My Portfolio (Psychology)** | 3 rotations, no HTE link | Optionally link rotation to placement (enhancement) | LOW |

### 6.2 Faculty/Coordinator Features

| Feature | Current Assumption | Required Change | Priority |
|---------|-------------------|-----------------|----------|
| **Monitoring Dashboard** | Shows one company per student | Display current placement + progress per placement | HIGH |
| **Student Details Modal** | Single internship view | Show placement history/status | MEDIUM |
| **Reports/Export** | One row per internship | Add placement-level columns if needed | MEDIUM |

### 6.3 Coordinator-Specific Features

| Feature | Current Assumption | Required Change | Priority |
|---------|-------------------|-----------------|----------|
| **Placement Hub / Assign Company** | Assigns to `internships.company_id` | Assign to specific `internship_placements` row; allow multiple assignments | HIGH |
| **Eligible Companies** | Shows companies for "the internship" | May need placement-type filtering (e.g., "Show only public schools for Placement 1") | MEDIUM |
| **My Applications** (Student) | Applies for one internship | Apply per placement (if program has multiple) | MEDIUM |

### 6.4 Supervisor Features

| Feature | Current Assumption | Required Change | Priority |
|---------|-------------------|-----------------|----------|
| **Supervisor Dashboard** | Shows assigned interns (one HTE) | No change (supervisor sees interns at their company) | LOW |
| **DTR Validation** | Validates hours for the internship | Validate hours for the current placement | HIGH |
| **Intern Progress View** | Shows total hours | Display placement-specific progress if multi-HTE | MEDIUM |

### 6.5 Director/Admin Features

| Feature | Current Assumption | Required Change | Priority |
|---------|-------------------|-----------------|----------|
| **Analytics Dashboard** | Aggregates by internship | May need placement-level insights (e.g., "Nursing students: 40% on HTE 2") | LOW |
| **Reports** | Export internship data | Add placement details to export | MEDIUM |

### 6.6 Backend API Endpoints Requiring Updates

| Endpoint | Current Response | Required Change |
|----------|------------------|-----------------|
| `GET /student/dashboard` | Returns `internship` object with `company_id`, `target_hours` | Add `placements` array with per-placement details |
| `GET /faculty/assigned-students` | Returns internship summary | Add current placement info |
| `GET /coordinator/monitoring` | Returns internship list | Include placement progress |
| `POST /coordinator/placements/assign` | Assigns company to internship | Assign company to specific placement |
| `POST /student/attendance/submit` | Creates attendance log for internship | Tag log with `placement_id` |
| `POST /supervisor/attendance/{id}/validate` | Updates internship hours | Update placement hours + internship hours |
| `GET /student/certificate/eligibility` | Checks internship totals | No change (still uses totals) |

---

## 7. Implementation Phases

### Phase 1: Schema & Models (No UI Changes)
**Tasks:**
1. Create migration for `program_hte_requirements` table
2. Create migration for `internship_placements` table
3. Alter `internships` table: add `current_placement_id`
4. Alter `attendance_logs` table: add `placement_id`
5. Create Eloquent models: `ProgramHteRequirement`, `InternshipPlacement`
6. Add relationships: `Program::hteRequirements()`, `Internship::placements()`

**Verification:**
- Run migrations on test DB
- Manually insert test requirements for BSIT, Psychology
- Verify relationships load correctly

### Phase 2: Seeder & Legacy Data Migration
**Tasks:**
1. Create `ProgramHteRequirementsSeeder`:
   - Seed requirements for all 10 programs (BSIT, BSCS, BSBA×2, Accountancy, BSED, BEED, Nursing, Psychology)
2. Create migration to backfill existing internships:
   - For each existing `internships` row:
     - Fetch program requirements
     - Create matching `internship_placements` rows
     - Set `current_placement_id`
     - Distribute `total_hours_rendered` to placements (for single-HTE: all hours to placement 1)
3. Backfill `attendance_logs.placement_id` for existing records (set to first placement of internship)

**Verification:**
- Run on test DB with existing CCS/IT internships
- Confirm hours totals match before/after
- Confirm existing student dashboards still show correct data

### Phase 3: Backend API Updates (Hours Calculation)
**Tasks:**
1. Update `Internship::refreshTotalHours()` to sum from placements
2. Update `AttendanceLog` creation to require `placement_id`
3. Update supervisor attendance validation to update placement hours
4. Modify `/student/dashboard` API to include `placements` array
5. Modify placement assignment endpoints to work with `internship_placements`

**Verification:**
- Feature test: Submit DTR for single-HTE program → hours update correctly
- Feature test: Submit DTR for multi-HTE program → correct placement hours
- Assert `internships.total_hours_rendered` = sum of all placement hours

### Phase 4: Student UI Enhancements
**Tasks:**
1. Update `StudentDashboard.jsx`:
   - Show placement breakdown for multi-HTE programs
   - Keep simple view for single-HTE (no visual change)
2. Update DTR/Attendance page to display `placement.label` per log
3. Update "Current Internship" card to show active placement info
4. Update Psychology Portfolio label: "Industrial Setting" → "Industrial/Organizational Setting"

**Verification:**
- Visual test: BSIT student dashboard looks identical to before
- Visual test: Psychology student sees 3 placements with individual progress
- Visual test: DTR page shows placement labels

### Phase 5: Faculty/Coordinator UI Updates
**Tasks:**
1. Update `FacultyAssignedStudents.jsx`: Show current placement + progress
2. Update `CoordMonitoring.jsx`: Add placement progress column
3. Update coordinator placement assignment flow to target specific placements
4. Update monitoring modals to show placement history

**Verification:**
- Visual test: Faculty sees CCS student with single placement (no change in display)
- Visual test: Faculty sees Nursing student with 5 placements, progress per placement
- Integration test: Coordinator assigns companies to multiple placements sequentially

### Phase 6: Testing & Regression Prevention
**Tasks:**
1. Write feature tests:
   - `SinglePlacementProgramsTest`: Verify BSIT student workflow unchanged
   - `MultiPlacementProgramsTest`: Verify Psychology/Nursing workflows
   - `CertificateEligibilityTest`: Verify certificate logic works for both cases
2. Run full test suite
3. Manual testing with browser agent:
   - Existing CCS account: Verify hours/progress/certificate unchanged
   - Create test Psychology account: Verify multi-placement flow

**Verification:**
- All tests pass
- No regression in CCS/IT features
- Multi-HTE programs work as expected

---

## 8. Risk Mitigation & Rollback Strategy

### Risks

| Risk | Mitigation | Rollback Plan |
|------|------------|---------------|
| Existing CCS/IT hours data corrupted | Backup DB before migration; backfill migration is idempotent | Restore from backup; drop new tables |
| Dashboard UI breaks for single-HTE programs | Phase 4 includes conditional rendering (show simple view if 1 placement) | Revert UI changes; API still backward-compatible |
| Hours calculation mismatch | Extensive unit tests for hours aggregation; manual verification | Fix aggregation logic; re-run `refreshTotalHours()` for all internships |
| Attendance logs lose placement context | Backfill migration sets `placement_id` for legacy logs | Drop `placement_id` column; use internship-level logs only |
| Coordinator can't assign companies | New placement assignment endpoint; keep old endpoint as fallback | Use legacy assignment endpoint |

### Rollback Triggers

- **Phase 1-2:** If migration fails or data integrity issues → Drop new tables, revert migrations
- **Phase 3-4:** If hours calculations are wrong → Revert API changes, fix logic, re-deploy
- **Phase 5-6:** If UI breaks or user reports issues → Revert UI commits, keep backend changes (backward-compatible)

---

## 9. Post-Implementation Verification Checklist

### CCS/IT (Single-HTE) Accounts
- [ ] Student dashboard shows correct total hours (e.g., 320/500)
- [ ] Progress percentage matches before migration
- [ ] Certificate eligibility check returns same result
- [ ] DTR submission updates hours correctly
- [ ] Faculty monitoring shows correct hours/progress
- [ ] No new UI elements that confuse single-HTE users

### Psychology (Multi-HTE) Accounts
- [ ] Student sees 3 placements with individual progress
- [ ] Current placement is clearly indicated
- [ ] DTR logs are tagged with correct placement
- [ ] Hours accumulate to correct placement
- [ ] Total hours = sum of all placement hours
- [ ] Portfolio label updated to "Industrial/Organizational Setting"
- [ ] Transition from Rotation 1 → 2 → 3 works smoothly

### Nursing (Multi-HTE) Accounts
- [ ] Student sees 5 placements (HTE 1-5)
- [ ] Each placement shows 540.6h target
- [ ] Total target is 2,703h
- [ ] Placement progression works sequentially

### Education (Multi-HTE) Accounts
- [ ] BSED/BEED students see 2 placements (Public School HTE, Private School HTE)
- [ ] Each placement shows 180h target
- [ ] Total target is 360h

---

## 10. Files & Tables Touched (Summary)

### New Database Tables
- `program_hte_requirements`
- `internship_placements`

### Modified Database Tables
- `internships` (add `current_placement_id`)
- `attendance_logs` (add `placement_id`)

### New Backend Files
- `app/Models/ProgramHteRequirement.php`
- `app/Models/InternshipPlacement.php`
- `database/migrations/YYYY_MM_DD_create_program_hte_requirements_table.php`
- `database/migrations/YYYY_MM_DD_create_internship_placements_table.php`
- `database/migrations/YYYY_MM_DD_alter_internships_add_placement_tracking.php`
- `database/migrations/YYYY_MM_DD_alter_attendance_logs_add_placement_id.php`
- `database/migrations/YYYY_MM_DD_backfill_internship_placements.php`
- `database/seeders/ProgramHteRequirementsSeeder.php`

### Modified Backend Files
- `app/Models/Internship.php` (add relationships, update hours calculation)
- `app/Models/AttendanceLog.php` (add `placement_id`, relationships)
- `app/Models/Program.php` (add `hteRequirements()` relationship)
- `app/Http/Controllers/Api/StudentController.php` (dashboard API, attendance submission)
- `app/Http/Controllers/Api/SupervisorController.php` (attendance validation)
- `app/Http/Controllers/Api/CoordinatorController.php` (placement assignment, monitoring)
- `app/Http/Controllers/Api/FacultyController.php` (monitoring dashboard)
- `app/Services/CertificateEligibilityService.php` (may need enhancement, but likely no change)

### Modified Frontend Files
- `frontend/src/pages/student/StudentDashboard.jsx` (add placement breakdown)
- `frontend/src/pages/student/StudentRecords.jsx` (show placement history)
- `frontend/src/pages/student/Attendance.jsx` (display placement per log)
- `frontend/src/pages/faculty/FacultyAssignedStudents.jsx` (show placement progress)
- `frontend/src/pages/coordinator/CoordMonitoring.jsx` (add placement columns)
- `frontend/src/pages/coordinator/PlacementHub.jsx` (assign to specific placement)
- `frontend/src/pages/student/portfolio/psychologyPortfolioStructure.js` (update Rotation 3 label)
- `frontend/src/components/CurrentInternshipCard.jsx` (show active placement details) *if component exists*

---

## 11. Open Questions for Review

1. **Placement Transition Logic:** Should placement transitions be:
   - Automatic (when hours hit target)?
   - Manual (coordinator/student initiates)?
   - **Recommendation:** Manual with notification when hours target met

2. **Company Assignment Timing:** For multi-HTE programs:
   - Assign all companies upfront?
   - Assign sequentially as placements complete?
   - **Recommendation:** Sequential (aligns with real-world workflows)

3. **Psychology Portfolio Integration:** Should portfolio rotations automatically link to placements?
   - **Recommendation:** Soft link (display placement HTE name in portfolio), but keep rotations independent

4. **Legacy Data:** Existing internships with partial hours:
   - Assume all hours belong to Placement 1?
   - **Recommendation:** Yes, all legacy hours → first placement

5. **Attendance Logs Without Placement:** Should old attendance logs (pre-migration) be:
   - Left with `placement_id = NULL`?
   - Backfilled to first placement?
   - **Recommendation:** Backfill to first placement for data consistency

---

## 12. Approval & Next Steps

**Before proceeding with implementation:**
1. Review this plan with the team/user
2. Confirm program hours/labels are accurate (especially Education, Nursing)
3. Approve rollback strategy
4. Schedule testing phase with browser agent

**After approval:**
- Begin Phase 1 (Schema & Models)
- Proceed through phases sequentially
- Report completion and verification results

---

**Plan Status:** ✅ READY FOR REVIEW  
**Estimated Implementation Time:** 3-5 days (excluding testing)  
**Risk Level:** Medium (backward-compatible design mitigates high risk)

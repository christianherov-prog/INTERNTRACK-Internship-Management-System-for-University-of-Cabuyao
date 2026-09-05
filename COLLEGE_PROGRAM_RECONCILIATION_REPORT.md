# INTERNTRACK College/Program Data Reconciliation - Investigation Report

## Current Database State

### Colleges/Departments
| ID | Code | Name | Status |
|----|------|------|--------|
| 1 | CHAS | College of Health and Allied Sciences | ✓ Matches |
| 2 | CAS | College of Arts and Sciences | ✓ Matches |
| 3 | CBAA | College of Business, Accountancy and Administration | ✓ Matches |
| 6 | CCS | College of Computing Studies | ✓ Matches |
| 7 | COED | College of Education | ✓ Matches |
| 8 | COE | College of Engineering | ✓ Already exists! |
| 4 | MISD | Management Information Systems Department | (Administrative dept, not in authoritative list) |
| 5 | PALD | Placement, Alumni, & Linkages Department | (Administrative dept, not in authoritative list) |

### Programs by College

#### CCS - College of Computing Studies ✓ CORRECT
- BSIT: Bachelor of Science in Information Technology ✓
- BSCS: Bachelor of Science in Computer Science ✓

#### CAS - College of Arts and Sciences ✓ CORRECT
- BSPSY: Bachelor of Science in Psychology ✓

#### CHAS - College of Health and Allied Sciences ✓ CORRECT
- BSN: Bachelor of Science in Nursing ✓

#### CBAA - College of Business, Accountancy and Administration ✓ CORRECT
- BSA: Bachelor of Science in Accountancy ✓
- BSBAMM: Bachelor of Science in Business Administration major in Marketing Management ✓
- BSBAFM: Bachelor of Science in Business Administration major in Financial Management ✓

#### COE - College of Engineering ⚠️ NEEDS CORRECTION
**Current programs:**
- BSCPE: Bachelor of Science in Computer Engineering ✓ (matches authoritative list)
- BSCE: Bachelor of Science in Civil Engineering ⚠️ (NOT in authoritative list, has 1 student)

**Missing programs:**
- Bachelor of Science in Industrial Engineering
- Bachelor of Science in Electronics Engineering

**References to BSCE (ID: 10):**
- 1 student_profile record
- 0 program_hte_requirements records

#### COED - College of Education ⚠️ NEEDS EXPANSION
**Current programs:**
- BSED (ID: 8): Bachelor of Secondary Education ⚠️ (generic, needs 4 specific majors)
- BEED (ID: 9): Bachelor of Elementary Education ✓ (matches authoritative list - "no major")

**References to BSED (ID: 8):**
- 1 student: User ID 25, Student #2300601, "COED Student", Section: 4BSED-A
- 2 program_hte_requirements:
  - Seq 1: Public School HTE, 180h
  - Seq 2: Private School HTE, 180h

**References to BEED (ID: 9):**
- 0 students
- 2 program_hte_requirements:
  - Seq 1: Public School HTE, 180h
  - Seq 2: Private School HTE, 180h

## Diff Against Authoritative List

### ✓ Already Correct (Leave Untouched)
- CCS: Both programs match exactly
- CAS: Psychology matches exactly
- CHAS: Nursing matches exactly
- CBAA: All 3 programs match exactly
- COE: College already exists (no need to create)
- COED: College already exists, BEED program name is correct

### ⚠️ Requires Changes

#### 1. College of Engineering (COE)
**Action Required:**
- Add: Bachelor of Science in Industrial Engineering (BSIE)
- Add: Bachelor of Science in Electronics Engineering (BSECE)
- Decision needed: Bachelor of Science in Civil Engineering (BSCE)
  - Has 1 enrolled student
  - NOT in authoritative list
  - Options:
    a) Mark as deprecated (is_active = false) - keep for historical data
    b) Delete and reassign student to another program
    c) Clarify if Civil Engineering should be in the list

#### 2. College of Education (COED)
**Action Required:**
- Expand BSED into 4 specific majors (create new programs):
  - Bachelor of Secondary Education major in Social Studies
  - Bachelor of Secondary Education major in Filipino
  - Bachelor of Secondary Education major in English
  - Bachelor of Secondary Education major in Mathematics
- BEED: Name already correct, just keep as-is

**Safe Migration Path for BSED:**
1. Create 4 new specific BSED major programs
2. Migrate student 2300601 from generic BSED (ID: 8) to one of the new majors
   - Section is "4BSED-A" - suggests we should default to first major or prompt for clarification
   - Update student_profiles.program_id
3. The existing program_hte_requirements for BSED (ID: 8) will be automatically recreated for all 4 new programs by the existing ProgramHteRequirementsSeeder
4. Optionally mark generic BSED (ID: 8) as inactive after migration to preserve historical data

**Safe Migration Path for BEED:**
- No migration needed - the program name "Bachelor of Elementary Education" is correct
- The authoritative list says "(no major)" which is descriptive, not part of the official name
- Keep existing BEED (ID: 9) as-is

## Proposed Implementation Plan

### Phase 1: Create Missing Programs
1. Add 2 missing COE programs:
   - BSIE: Bachelor of Science in Industrial Engineering
   - BSECE: Bachelor of Science in Electronics Engineering

2. Add 4 specific BSED major programs to COED:
   - Code: BSEDSS, Name: Bachelor of Secondary Education major in Social Studies
   - Code: BSEDF, Name: Bachelor of Secondary Education major in Filipino
   - Code: BSEDE, Name: Bachelor of Secondary Education major in English
   - Code: BSEDM, Name: Bachelor of Secondary Education major in Mathematics

### Phase 2: Data Migration
1. Migrate student 2300601 from BSED (ID: 8) to one of the new BSED majors
   - Recommendation: Default to BSEDSS (Social Studies) or prompt for clarification
   
2. Mark generic BSED (ID: 8) as inactive (is_active = false) to preserve historical data

### Phase 3: HTE Requirements
- The existing ProgramHteRequirementsSeeder will handle creating HTE requirements for new programs
- Run seeder after programs are created

### Phase 4: Verification
- Query database to confirm all programs match authoritative list
- Verify student 2300601 can still access their internship data
- Verify no broken foreign key relationships

## Decision Points

**1. Civil Engineering (BSCE):**
- ⚠️ This program has 1 enrolled student but is NOT in the authoritative list
- Recommended action: Mark as inactive (is_active = false) rather than delete
- This preserves data integrity while preventing new enrollments

**2. Student 2300601 Migration:**
- Which BSED major should this student be assigned to?
- Section "4BSED-A" doesn't clearly indicate the major
- Recommended default: BSEDSS (Social Studies) or keep in generic until manually updated by admin

**3. Generic BSED/BEED Programs:**
- Recommended: Mark BSED as inactive after migration, keep BEED as-is
- Alternative: Delete after confirming no other references exist

## Summary

**No changes needed for:**
- CCS (2 programs) ✓
- CAS (1 program) ✓
- CHAS (1 program) ✓
- CBAA (3 programs) ✓

**Changes required:**
- COE: Add 2 programs (Industrial, Electronics), decide on Civil Engineering
- COED: Add 4 BSED major programs, migrate 1 student, handle generic BSED

**Data at risk:**
- 1 student in BSED (requires migration)
- 1 student in BSCE (requires decision)
- 4 program_hte_requirements records (will be recreated by seeder)

**Recommended approach:**
- Use migration + seeder for all changes
- Mark deprecated programs as inactive rather than delete
- Preserve all foreign key relationships
- Run ProgramHteRequirementsSeeder after creating new programs

# Manual account create and delete

Step-by-step guide for adding or removing a **single** InternTrack account. There is no generic “Create user” or “Delete user” button. Use the role-specific UI when you can; use Tinker only for faculty, extra students, or cleanup.

**Default password:** `interntrack123`  
(override with `INTERNTRACK_DEFAULT_PASSWORD` in `backend/.env`)

**Login ID:** student number **or** faculty number (not email).

All Tinker commands below assume:

```powershell
cd backend
php artisan tinker
```

List accounts first:

```powershell
cd backend
php artisan users:list
```

---

## Part A — Create an account

### A1. Coordinator or Director (MISD Admin UI)

1. Log in as **MISD Admin**.
2. Open **Coordinators** or **Directors**.
3. Click assign / add.
4. Enter a faculty number:
   - Coordinator: `COR-CCS-002`, `COR-COE-001`, …
   - Director: `DIR-001`, …
5. Fill name, email, and **department/college** (CCS, COE, COED, …).
6. Save. Password is the default (`interntrack123`) until they change it.
7. Log in with that faculty number and the default password.

---

### A2. Student (class list upload)

1. Log in as **Coordinator** or **Faculty** in the same college as the student.
2. Open the class list upload (Records / Assigned Students).
3. Choose:
   - Excel/CSV file (`email`, `student_id`, `first_name`, `last_name`)
   - **Program** in your department
   - **Section** (example: `4ITD`)
   - School year and semester
   - **Faculty** in the **same department** as the program
4. Upload.

The student can log in with **student_id** / `interntrack123`.

A pending internship is created. Faculty is attached from the section mapping if one exists.

---

### A3. Supervisor (invite flow)

1. Student opens **Invite Supervisor** and generates the QR / link.
2. Supervisor registers (or signs in and binds the invite).
3. Faculty opens **Supervisor approvals**, reviews the acceptance form, then **Approve**.

Do not create supervisors in Tinker unless you are debugging. The invite flow stores company and acceptance files the UI expects.

---

### A4. First-login provision (local / mock MISD)

Use this when the person already exists in MISD (or mock MISD) but **not** in InternTrack.

1. Confirm you are on local, or that `.env` has  
   `MISD_ALLOW_DEFAULT_PASSWORD_PROVISION=true`.
2. On the login page, enter the MISD ID and password `interntrack123`.
3. Accepted ID shapes:

   | Role | Example |
   |------|---------|
   | Student | `2023-00600` (hyphenated MISD form, not `2300600`) |
   | Faculty | `FAC-1001` |
   | Coordinator | `COR-CCS-001` |
   | Director | `DIR-001` |
   | Admin | `ADMIN-1001` |

4. The account is created on first successful login. They may be asked to change the password.

If login fails, the ID is not in MISD/mock — use the UI or Tinker instead.

---

### A5. Faculty or extra student (Tinker)

Faculty has no “Add faculty” screen. Students can also be inserted here if you are not using class list upload.

#### 1. Open Tinker

```powershell
cd backend
php artisan tinker
```

#### 2. Create faculty (CCS example)

Change the number, name, email, and department code as needed.

```php
$dept = App\Models\Department::where('code', 'CCS')->first();
$pw = Hash::make('interntrack123');

$u = App\Models\User::create([
  'faculty_number' => 'FAC-CCS-002',
  'email' => 'faculty.ccs2@uc.edu.ph',
  'password' => $pw,
  'role' => 'faculty',
  'is_active' => true,
]);

App\Models\FacultyProfile::create([
  'user_id' => $u->id,
  'faculty_number' => 'FAC-CCS-002',
  'first_name' => 'Ana',
  'last_name' => 'Santos',
  'email' => $u->email,
  'department_id' => $dept->id,
  'position' => 'Faculty',
  'employment_status' => 'Regular',
]);
```

Login: `FAC-CCS-002` / `interntrack123`

**Coordinator:** same snippet, but `'role' => 'coordinator'` and a number like `COR-CCS-002`.

#### 3. Map faculty to a section (so students auto-assign)

MISD Admin → **Section mappings**, **or** in Tinker:

```php
App\Models\FacultySectionAssignment::updateOrCreate(
  [
    'section' => '4ITD',
    'school_year' => '2025-2026',
    'semester' => '2nd Semester',
  ],
  [
    'faculty_user_id' => $u->id,
    'program' => 'Bachelor of Science in Information Technology',
    'is_active' => true,
  ]
);
```

#### 4. Create a student (same department as faculty)

```php
$dept = App\Models\Department::where('code', 'CCS')->first();
$program = App\Models\Program::where('code', 'BSIT')->first();
$pw = Hash::make('interntrack123');

$s = App\Models\User::create([
  'student_number' => '2300999',
  'email' => 'student.test@uc.edu.ph',
  'password' => $pw,
  'role' => 'student',
  'is_active' => true,
]);

App\Models\StudentProfile::create([
  'user_id' => $s->id,
  'student_number' => '2300999',
  'first_name' => 'Hero',
  'last_name' => 'Test',
  'email' => $s->email,
  'department_id' => $dept->id,
  'program_id' => $program->id,
  'section' => '4ITD',
  'school_year' => '2025-2026',
  'semester' => '2nd Semester',
  'year_level' => 4,
  'enrollment_status' => 'Enrolled',
]);
```

Saving the profile creates a pending internship and tries to attach faculty from the section mapping.

Login: `2300999` / `interntrack123`

Type `exit` when finished.

#### Department matching (required)

| Rule | Example |
|------|---------|
| Student `department_id` = Faculty `department_id` | CCS student → CCS faculty only |
| Coordinator only sees the same college | CCS coordinator cannot manage COE |
| Cross-college assignment is blocked | API returns `Access denied — different department` |

Use department codes: `CCS`, `COE`, `COED`, `CHAS`, `CAS`, `CBAA`.

---

## Part B — Delete (or disable) an account

Prefer **deactivate / archive**. Soft-delete hides the account. Force-delete is permanent and can break internships.

### B1. Find the account

```powershell
cd backend
php artisan users:list
```

Or in Tinker:

```php
User::where('student_number', '2300999')->first();
User::where('faculty_number', 'FAC-CCS-002')->first();
User::where('email', 'someone@uc.edu.ph')->first();
```

Note the `id`, `role`, and username before you continue.

---

### B2. Deactivate (recommended — any role)

Keeps all records. User cannot log in.

1. Log in as **MISD Admin**.
2. Open **Users**.
3. Find the row.
4. Click **Deactivate**. Confirm.

Staff (directors/coordinators) can also be deactivated from **Directors** / **Coordinators**.

You cannot deactivate your own admin account, the last director, or the last admin.

---

### B3. Archive a student (Faculty / Coordinator UI)

1. Log in as Faculty or Coordinator in the student’s college.
2. Open **Assigned Students** or **Records**.
3. Use **Archive** on that student.

This sets `is_active = 0`. Unarchive later to restore login.

---

### B4. Soft-delete (Tinker)

The row stays (`deleted_at` is set). Login stops. Related internships stay.

```powershell
cd backend
php artisan tinker
```

```php
$u = User::where('student_number', '2300999')->first();
// or: User::where('faculty_number', 'FAC-CCS-002')->first();

$u->tokens()->delete();   // end active sessions
$u->delete();             // soft-delete
```

**Restore later:**

```php
User::withTrashed()->where('student_number', '2300999')->first()->restore();
```

---

### B5. Permanent delete (Tinker — last resort)

Use only for test accounts. Foreign keys (internships, profiles, journals) may block the delete or leave orphans.

```php
$u = User::withTrashed()->where('student_number', '2300999')->first();
$u->tokens()->delete();
$u->forceDelete();
```

If MySQL reports a foreign-key error, unlink or remove related rows first. Example for a **test student**:

```php
$u = User::withTrashed()->where('student_number', '2300999')->first();

App\Models\Internship::where('student_id', $u->id)->get()->each->delete();
$u->studentProfile()?->delete();
$u->tokens()->delete();
$u->forceDelete();
```

For **faculty**, internships may still point at `faculty_id`. Unlink before force-delete:

```php
$u = User::withTrashed()->where('faculty_number', 'FAC-CCS-002')->first();
App\Models\Internship::where('faculty_id', $u->id)->update(['faculty_id' => null]);
App\Models\FacultySectionAssignment::where('faculty_user_id', $u->id)->delete();
$u->facultyProfile()?->delete();
$u->tokens()->delete();
$u->forceDelete();
```

---

### B6. Do not use this for one account

```powershell
php artisan supervisors:reset --force
```

That deletes **every** supervisor account.

```powershell
php artisan db:reset-fresh
php artisan migrate:fresh --seed
```

Those wipe **the whole database**.

---

## Quick decision

| Goal | What to do |
|------|------------|
| Stop login, keep history | Deactivate (Admin) or Archive (student) |
| Hide the user, maybe restore | Soft-delete in Tinker |
| Remove a test account | Force-delete after unlinking internships |
| Add coordinator / director | MISD Admin UI |
| Add student in bulk | Class list upload |
| Add one faculty or student | Tinker (Part A5) |
| Add supervisor | Invite Supervisor flow |

---

## After create — smoke check

1. Log in with the new ID and `interntrack123`.
2. Confirm the dashboard loads for that role.
3. For a student: Assigned Students / Coordinator monitoring shows them **only** in the matching college.
4. For faculty: they only see students in their department and mapped section(s).

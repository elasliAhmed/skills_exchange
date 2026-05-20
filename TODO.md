# TODO.md - Skills Exchange Project

## Database Tasks
- [x] Add lessons_count INT NOT NULL DEFAULT 1 to offers/courses table (user_skills table)
- [x] Add completed_lessons INT DEFAULT 0 to enrollments/bookings table
- [x] Add remaining_lessons INT DEFAULT 0 to enrollments/bookings table
- [x] Create lessons table with:
    - id
    - booking_id (enrollment_id)
    - lesson_number
    - status
    - completed_at
    - lesson_summary
    - teacher_comment
    - created_at

## Backend API Tasks
- [x] Update backend APIs to save lessons_count
- [x] Update enrollment creation logic to copy lessons_count and set completed/remaining lessons
- [x] Create API endpoints for lesson management (mark as completed, add lesson summary/comment)
- [x] Update existing offer APIs to handle lessons_count field

## Frontend Tasks
- [x] Update frontend forms (offer creation/editing) to allow teacher to enter lesson count
- [x] Build lesson tracking UI in enrollment details (for teachers)
- [x] Build student progress UI showing total/completed/remaining lessons, progress bar, lesson reports
- [x] Update existing offer display to show lessons count
- [x] Implement "Mark as Completed" button functionality
- [x] Implement lesson summary and comment input for teachers

## Testing Tasks
- [x] Test new offer creation with lessons count
- [x] Test enrollment flow and automatic lesson generation
- [x] Test lesson completion by teacher
- [x] Test progress updates for student
- [x] Test edge cases (invalid values, negative lessons, etc.)
- [x] Test existing offers default to 1 lesson

## Skills Table Removal (cleanup)
- [x] Replace backend/models/Skill.php with stub (removed skills table)
- [x] Replace backend/api/skills.php with dead-code response
- [x] Update backend/controllers/SkillController.php — remove getAllSkills()/addSkill() SQL calls, keep getUserSkills() (uses UserSkill only), reject others with HTTP 410
- [x] Update backend/api/index.php — remove SkillController require/instantiation, remove /skills and /my-skills routes
- [x] Stub frontend/js/api.js getSkills()/searchSkill() — return empty arrays, no network call
- [x] Stub frontend/js/app.js loadSkills()/renderSkills() — no-op functions
- [x] Remove duplicate credits-per-lesson input (lines 546-558) — HTML had two `<input name="credits">` blocks with the same id, causing `form.credits` to resolve to a NodeList and `parseInt()` to return NaN, triggering false validation failure

## CSS Restoration
- [x] Reconstructed frontend/css/style.css — `:root` variables, CSS reset, typography, layout, header/nav, buttons, hero, grids, cards, auth forms, modals, tabs, video call, progress/lesson styles
- [x] Aligned frontend/css/auth.css — replaced outdated hardcoded colors (`#2c3e50`, `#555`, `#3498db`, `#ddd`) with CSS variables matching the design-brief palette

## Progress Tracking
- [x] All tasks completed successfully

## Teacher Dashboard
### Backend
- [x] Create `backend/api/teacher.php` — single unified PHP API file for 3 endpoints:
  - `GET ?endpoint=stats` → total_students / active_enrollments / completed_lessons counts
  - `GET ?endpoint=enrollments` → teacher's full enrollment list (skill_name, learner_username, status, completed/remaining lessons)
  - `GET ?endpoint=lessons&enrollment_id=N&student_name=X` → individual enrollment details (all lesson rows with status)
  - `POST {action: complete_lesson, enrollment_id, lesson_number, teacher_comment}` → marks lesson done, updates enrollment counters, requires teacher-ownership check in DB
  - Requires JWT auth on every endpoint
### Frontend — HTML (`index.html`)
- [x] Teacher stat cards inside dashboard section (`#teacher-stats` grid — Total Students / Active Enrollments / Completed Lessons)
- [x] Teacher enrollment list `#teacher-enrollments-list` (skills-grid grid, each row has student name/lesson counts + "Open" button)
- [x] Enrollment details modal `#teacher-enrollment-modal` after learners modal: course title, student name, lesson list with status badges, comment textarea, "Close" button
### Frontend — CSS (`style.css`)
- [x] `.teacher-stats-grid` — 3-up responsive grid
- [x] `.teacher-stat-card` — card with icon + value/label
- [x] `.teacher-enrollment-row` — horizontal flex row for each student enrollment
- [x] `.status-active / .status-pending / .status-completed / .status-cancelled` — badge colours
### Frontend — JS (`app.js`)
- [x] `api.js:getTeacherStats() / getTeacherEnrollments() / getEnrollmentLessons() / markLessonComplete()` — 4 API methods
- [x] `loadTeacherStats()` — writes stats to the 3 stat card values
- [x] `loadTeacherEnrollments()` — renders teacher enrollment list with "Open" buttons, calls `openTeacherEnrollmentModal(id, student, course)` onclick
- [x] `openTeacherEnrollmentModal()` — fetches lessons from `teacher.php?endpoint=lessons`, populates modal (lesson items with status badges + Mark Complete buttons, pending lessons have enabled buttons; completed have disabled buttons)
- [x] `closeTeacherEnrollmentModal()` — hides modal + nullifies `_currentTeacherEnrollment`
- [x] `markLessonComplete()` — POSTs `complete_lesson` to `teacher.php`, re-opens modal on success + refreshes teacher stats
- [x] `loadDashboard()` — calls `loadEnrollmentsTab("all")` + `loadTeacherStats()` + `loadTeacherEnrollments()` on navigation to dashboard
- [x] `setupEventListeners()` — close handlers for `#teacher-enrollment-close` + `#teacher-modal-cancel`
- [x] Guard: `renderEnrollments` filter now uses `(e.status || '')` to avoid null `.toLowerCase()` crash

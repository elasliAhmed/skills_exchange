// Main App
let apiInstance = new API();

// Make functions globally available
function showPage(page) {
	if (!_authReady && (page !== 'login' && page !== 'register')) {
		console.debug('[showPage] waiting for auth…');
		return;
	}
	document
		.querySelectorAll(".page")
		.forEach((p) => p.classList.remove("active"));
	document.getElementById(`page-${page}`).classList.add("active");

	document.body.classList.toggle("dash-mode", page === "dashboard");
	if (page === "dashboard" && typeof DashboardUI !== "undefined") {
		DashboardUI.setActiveNav("dashboard");
		DashboardUI.syncProfileSidebar();
	}

	updateNav();
	if (page === "dashboard") loadDashboard();
	if (page === "profile") loadProfile();
	if (page === "offers") loadOffersPage();
	if (page === "add-offer") loadAddOfferPage();
	if (page === "messages") loadMessagesPage();
	else stopMessagesPolling();
}

async function handleLogin(e) {
	e.preventDefault();
	const form = e.target;
	const result = await apiInstance.login({
		username: form.username.value,
		password: form.password.value,
	});
	console.log('[login] result:', JSON.stringify(result));
	if (result.success) {
		apiInstance.setToken(result.data.token);
		const userStr = JSON.stringify(result.data.user);
		localStorage.setItem("user", userStr);
		console.log('[login] user stored:', userStr);
		updateNav();
		showPage("dashboard");
		loadDashboard();
	} else {
		console.error('[login] failed:', result.data);
	}
	alert(result.data.message || result.data.error || 'Login failed');
}

function logout() {
	apiInstance.clearToken();
	localStorage.removeItem("user");
	updateNav();
	showPage("home");
}

// Track whether auth check has finished
let _authReady = false;

async function checkAuth() {
	try {
		const result = await apiInstance.verifyToken();
		console.log('[checkAuth] verify:', JSON.stringify(result));
		if (result.success) {
			localStorage.setItem("user", JSON.stringify(result.data.user));
			console.log('[checkAuth] user stored:', result.data.user);
			console.log('[checkAuth] user credits:', result.data.user.credits);
		} else {
			// Token dead — nuke everything
			apiInstance.clearToken();
			localStorage.removeItem("user");
		}
	} catch (error) {
		console.error('[checkAuth] threw:', error);
		apiInstance.clearToken();
		localStorage.removeItem("user");
	}
	// Always re-evaluate the nav AFTER auth state is final
	updateNav();
	_authReady = true;
}

function updateNav() {
	const user = JSON.parse(localStorage.getItem("user"));
	console.debug('[updateNav] user:', user);
	console.debug('[updateNav] user.credits:', user?.credits);
	document.querySelectorAll(".auth-only").forEach((el) => {
		el.style.display = user ? "none" : "inline";
	});
	document.querySelectorAll(".user-only").forEach((el) => {
		el.style.display = user ? "inline" : "none";
	});
	const creditsEl = document.getElementById("header-credits");
	const amountEl = document.getElementById("header-credits-amount");
	if (user && creditsEl && amountEl) {
		creditsEl.style.display = "flex";
		amountEl.textContent = user.credits || 0;
		console.debug('[updateNav] Set credits to:', user.credits || 0);
	}
	if (user && typeof refreshUnreadBadge === 'function') {
		refreshUnreadBadge();
	} else {
		const badge = document.getElementById('messages-unread-badge');
		if (badge) badge.style.display = 'none';
	}
}

function getUser() {
	const user = JSON.parse(localStorage.getItem("user"));
	console.debug('[getUser]', user);
	return user;
}

document.addEventListener("DOMContentLoaded", async function () {
	console.log("DOM loaded");
	setupEventListeners();
	// Pulse nav from cached localStorage
	updateNav();
	// Wait for auth verification to finish before anything else
	await checkAuth();
	showPage("home");
	loadSkills();
	
	// Header scroll shadow
	window.addEventListener('scroll', function() {
		const header = document.querySelector('.header');
		if (window.scrollY > 10) {
			header.classList.add('scrolled');
		} else {
			header.classList.remove('scrolled');
		}
	});
});

function setupEventListeners() {
	console.log("Setting up event listeners");
	document.querySelectorAll("[data-page]").forEach((link) => {
		link.addEventListener("click", function (e) {
			e.preventDefault();
			console.log("Navigating to:", this.dataset.page);
			showPage(this.dataset.page);
		});
	});

	document.getElementById("logout")?.addEventListener("click", function (e) {
		e.preventDefault();
		logout();
	});

	document.getElementById("add-offer-btn")?.addEventListener("click", function () {
		updateNav();
		if (!getUser()) {
			alert("Please login first");
			showPage("login");
			return;
		}
		showPage("add-offer");
	});

	document.getElementById("login-form")?.addEventListener("submit", handleLogin);
	document.getElementById("register-form")?.addEventListener("submit", handleRegister);
	document.getElementById("profile-form")?.addEventListener("submit", handleProfileUpdate);
	document.getElementById("add-offer-form")?.addEventListener("submit", handleAddOffer);

	// ── Edit Offer Modal ──
	document.getElementById("edit-offer-close")?.addEventListener("click", closeEditModal);
	document.getElementById("edit-offer-cancel")?.addEventListener("click", closeEditModal);
	document.getElementById("edit-offer-form")?.addEventListener("submit", handleEditOfferSubmit);

	// ── Learners Modal ──
	document.getElementById("learners-close")?.addEventListener("click", closeLearnersModal);

	// ── Teacher Enrollment Details Modal ──
	document.getElementById("teacher-enrollment-close")?.addEventListener("click", closeTeacherEnrollmentModal);
	document.getElementById("teacher-modal-cancel")?.addEventListener("click", closeTeacherEnrollmentModal);

	// ── Student Lesson Details Modal ──
	document.getElementById("sl-close")?.addEventListener("click", closeStudentLessonsModal);
	document.getElementById("sl-close-btn")?.addEventListener("click", closeStudentLessonsModal);

	// Dashboard tab switching
	document.querySelectorAll(".tab-btn").forEach((btn) => {
		btn.addEventListener("click", function () {
			loadEnrollmentsTab(this.dataset.tab);
		});
	});
}

async function handleRegister(e) {
	e.preventDefault();
	const form = e.target;
	const result = await apiInstance.register({
		username: form.username.value,
		email: form.email.value,
		password: form.password.value,
		full_name: form.full_name.value,
	});
	if (result.success) {
		showPage("login");
	}
	alert(result.data.message || result.data.error);
}

async function handleProfileUpdate(e) {
	e.preventDefault();
	alert("Profile update not implemented");
}

// ═══ Teacher Dashboard ═══

async function loadTeacherStats() {
    const result = await apiInstance.getTeacherStats();
    if (result.success) {
        const d = result.data;
        document.getElementById('stat-students').textContent  = d.total_students || 0;
        document.getElementById('stat-active').textContent    = d.active_enrollments || 0;
        document.getElementById('stat-completed').textContent = d.completed_lessons || 0;
    } else {
        console.debug('[TeacherStats] none:', result.data);
    }
}

async function loadTeacherEnrollments() {
    const result = await apiInstance.getTeacherEnrollments();
    renderTeacherEnrollments(result.success ? result.data.enrollments || [] : []);
}

function renderTeacherEnrollments(list) {
    const container = document.getElementById("teacher-enrollments-list");
    if (!container) return;
    if (!list.length) {
        container.innerHTML = '<p class="dash-empty">No students yet. Share your offers to attract learners!</p>';
        return;
    }
    const D = typeof DashboardUI !== "undefined" ? DashboardUI : null;
    container.innerHTML = list.map((e) => {
        const lc = e.lessons_count ?? 0;
        const done = e.completed_lessons ?? 0;
        const pct = lc > 0 ? Math.round((done / lc) * 100) : 0;
        const studentName = e.learner_name || e.learner_username || "Student";
        const statusClass = e.status === "completed" ? "completed" : e.status === "active" ? "active" : "pending";
        const btnGhost = `<button type="button" class="dash-btn dash-btn-ghost" onclick="openMessagesWithUser(${e.learner_id}, '${studentName.replace(/'/g, "\\'")}')">Message</button>`;
        const btnPrimary = `<button type="button" class="dash-btn dash-btn-primary" onclick="openTeacherEnrollmentModal(${e.id}, '${(e.learner_username || "").replace(/'/g, "\\'")}', '${(e.skill_name || "").replace(/'/g, "\\'")}', ${e.credits ?? 0})">Open</button>`;
        if (D) {
            return D.lessonCardHtml({
                title: e.skill_name || "Untitled",
                meta: `Student: ${studentName}`,
                pct, done, total: lc,
                status: e.status || "active",
                statusClass,
                avatarName: studentName,
                avatarId: e.learner_id,
                btnPrimary, btnGhost,
            });
        }
        return `<div>${e.skill_name}</div>`;
    }).join("");
}

let _currentTeacherEnrollment = null;
window._editCommentOpen = {};

async function openTeacherEnrollmentModal(enrollment_id, student_name_in, course_title_in, credits_per_session) {
    _currentTeacherEnrollment = enrollment_id;
    document.getElementById('mark-complete-comment').value = '';

    const result = await apiInstance.getMyEnrollmentLessons(enrollment_id);
    if (!result.success) {
        alert('Failed to load lesson details: ' + (result.data.error || 'Unknown error'));
        return;
    }
    const d = result.data;

    document.getElementById('modal-course-title').textContent =
        `${d.course_title || course_title_in || 'Course'}${credits_per_session > 0 ? '  —  ' + credits_per_session + ' credits / lesson' : ''}`;
    document.getElementById('modal-student-info').textContent = 'Student: ' + (d.student_name || student_name_in || 'Unknown');

    const lessonsList = document.getElementById('teacher-lessons-list');
    const lessons     = d.lessons  || [];
    // lessons_count may come from the offer; fall back to number of lesson rows
    const total       = (d.lessons_count || lessons.length || 0);
    const done        = lessons.filter(l => l.status === 'completed').length;
    const pct         = total > 0 ? Math.round((done / total) * 100) : 0;

    // Lesson count summary bar
    const summaryHtml = `
        <div style="margin-bottom:var(--spacing-md);">
            <div class="progress-label">
                <span>Lesson Progress</span>
                <span class="progress-text">${done}/${total} completed</span>
            </div>
            <div class="progress-bar">
                <div class="progress-fill" style="width:${pct}%"></div>
            </div>
        </div>`;

    if (lessons.length === 0) {
        lessonsList.innerHTML = summaryHtml +
            '<p class="no-offers-msg">No lessons for this enrollment. Check the offer settings.</p>';
        } else {
        lessonsList.innerHTML = summaryHtml + lessons.map(
            (l) => {
                const completed     = l.status === 'completed';
                const completedAt   = l.completed_at
                    ? '<small style="color:var(--color-text-muted);margin-left:8px;">' +
                      new Date(l.completed_at).toLocaleDateString() + '</small>'
                    : '';
                const summary  = l.lesson_summary ? `<div class="lesson-summary" style="margin-top:6px;">${l.lesson_summary}</div>` : '';
                const hasComm  = l.teacher_comment && l.teacher_comment.trim();
                const commDisp = hasComm ? l.teacher_comment.replace(/</g,'&lt;').replace(/>/g,'&gt;') : '';
                const commHtml = hasComm
                    ? `<div class="teacher-comment" id="tc-disp-${l.lesson_number}" style="margin-top:6px;">"${commDisp}"</div>`
                    : '<div class="teacher-comment" id="tc-disp-' + l.lesson_number + '" style="margin-top:6px;font-style:italic;color:#999;">No comment yet</div>';
                const editHtml = completed
                    ? '<button class="btn btn-secondary" style="margin-top:6px;" onclick="toggleEditComment(' + enrollment_id + ',' + l.lesson_number + ')">Edit Comment</button>'
                    : '';
                const commentEditId = 'tc-edit-' + l.lesson_number;
                const editAreaHtml  = completed && !!(window._editCommentOpen && window._editCommentOpen[enrollment_id] === l.lesson_number)
                    ? '<div style="margin-top:6px;">' +
                          '<textarea id="' + commentEditId + '" rows="2" style="width:100%;padding:6px;border:1px solid #ccc;border-radius:6px;">' + commDisp + '</textarea>' +
                          '<button class="btn btn-primary" style="margin-top:4px;" onclick="saveComment(' + enrollment_id + ',' + l.lesson_number + ')">Save Comment</button>' +
                          '<button class="btn btn-secondary" style="margin-top:4px;" onclick="toggleEditComment(' + enrollment_id + ',' + l.lesson_number + ')">Cancel</button>' +
                      '</div>'
                    : '';

                const btnDisabled  = completed ? 'disabled' : '';
                const btnLabel     = completed ? '✓ Done' : 'Mark as Completed';
                const btnClass     = completed ? 'btn btn-secondary' : 'btn btn-primary';

                return `
                <div class="lesson-item ${completed ? 'completed' : ''}" data-lesson="${l.lesson_number}">
                    <div style="min-width:0;">
                        <div>
                            <span class="lesson-number">Lesson ${l.lesson_number}</span>
                            <span class="lesson-status ${completed ? 'completed' : 'pending'}">${l.status || 'pending'}</span>
                            ${completedAt}
                        </div>
                        ${summary}
                        ${commHtml}
                        ${editAreaHtml}
                    </div>
                    <div>
                        ${editHtml}
                        <button class="${btnClass}" ${btnDisabled}
                            onclick="markLessonComplete(${enrollment_id}, ${l.lesson_number})">
                            ${btnLabel}
                        </button>
                    </div>
                </div>`;
            }
        ).join('');
    }

    // ── Final Course Comment section ─────────────────────────
    const allDone    = (d.enrollment_status === 'completed')
                    || (lessons.length > 0 && lessons.every(l => l.status === 'completed'));
    const finalArea  = document.getElementById('teacher-final-comment-area');
    const finalText  = document.getElementById('teacher-final-comment');
    if (finalArea && finalText) {
        if (allDone) {
            finalText.value = d.final_teacher_comment || '';
            finalArea.style.display = 'block';
        } else {
            finalArea.style.display = 'none';
            finalText.value = '';
        }
    }
    // handle final comment save button
    document.getElementById('save-final-comment-btn').onclick = async () => {
        const comment = finalText.value.trim();
        if (!comment) { alert('Final comment cannot be empty'); return; }
        const res = await apiInstance.saveFinalComment({
            enrollment_id:       enrollment_id,
            final_teacher_comment: comment,
        });
        if (res.success) {
            alert(res.data.message || 'Final course comment saved');
            openTeacherEnrollmentModal(enrollment_id);
            loadTeacherStats();
            loadDashboard();
        } else {
            alert(res.data.error || 'Failed to save final comment');
        }
    };
    document.getElementById('teacher-enrollment-modal').style.display = 'flex';
}

function toggleEditComment(enrollment_id, lesson_number) {
    const key = enrollment_id;
    if (window._editCommentOpen[key] === lesson_number) {
        delete window._editCommentOpen[key];
    } else {
        window._editCommentOpen[key] = lesson_number;
    }
    if (_currentTeacherEnrollment === enrollment_id) {
        openTeacherEnrollmentModal(_currentTeacherEnrollment);
    }
}

async function saveComment(enrollment_id, lesson_number) {
    const textarea = document.getElementById('tc-edit-' + lesson_number);
    if (!textarea) return;
    const comment = textarea.value.trim();
    if (!comment) {
        alert('Comment cannot be empty');
        return;
    }
    const result = await apiInstance.saveLessonComment({
        enrollment_id: enrollment_id,
        lesson_number: lesson_number,
        teacher_comment: comment,
    });
    if (result.success) {
        if (window._editCommentOpen) delete window._editCommentOpen[enrollment_id];
        openTeacherEnrollmentModal(enrollment_id);
    } else {
        alert(result.data.error || 'Failed to save comment');
    }
}

function closeTeacherEnrollmentModal() {
    document.getElementById('teacher-enrollment-modal').style.display = 'none';
    _currentTeacherEnrollment = null;
}

async function markLessonComplete(enrollment_id, lesson_number) {
    const commentEl = document.getElementById('mark-complete-comment');
    const comment   = commentEl.value.trim();

    const result = await apiInstance.markLessonComplete({
        enrollment_id:   enrollment_id,
        lesson_number:   lesson_number,
        lesson_summary:  comment || 'Completed',
        teacher_comment: comment
    });

    if (result.success) {
        const credits  = result.data.credits_transferred  || 0;
        const completed = result.data.completed_lessons  || 0;
        const remaining = result.data.remaining_lessons  || 0;
        alert(
            (result.data.message || 'Lesson marked as completed') +
            (credits > 0 ? `  \n+${credits} credits transferred to teacher.` : '')
        );
        openTeacherEnrollmentModal(enrollment_id);
        loadTeacherStats();
    } else {
        alert(result.data.error || 'Failed to mark lesson as completed');
    }
}

// ═══ Student Dashboard ═══

async function loadStudentStats() {
    const result = await apiInstance.getStudentStats();
    if (result.success) {
        const d = result.data;
        document.getElementById('stat-enrollments').textContent = d.total_enrollments || 0;
        document.getElementById('stat-active-enrollments').textContent = d.active_enrollments || 0;
        document.getElementById('stat-lessons-completed').textContent = d.total_completed_lessons || 0;
    }
}

async function loadStudentEnrollments() {
    const result = await apiInstance.getStudentEnrollments();
    renderStudentEnrollments(result.success ? result.data.enrollments || [] : []);
}

function renderStudentEnrollments(list) {
    const container = document.getElementById("student-enrollments-list");
    if (!container) return;
    if (!list.length) {
        container.innerHTML = '<p class="dash-empty">No enrollments yet. Browse offers to get started!</p>';
        return;
    }
    const D = typeof DashboardUI !== "undefined" ? DashboardUI : null;
    container.innerHTML = list.map((e) => {
        const lessonsCount = e.lessons_count || 0;
        const completedLessons = e.completed_lessons || 0;
        const pct = lessonsCount > 0 ? Math.round((completedLessons / lessonsCount) * 100) : 0;
        const statusClass = e.status === "completed" ? "completed" : e.status === "active" ? "active" : "pending";
        const teacherName = e.teacher_full_name || e.teacher_name || "Unknown";
        const btnGhost = `<button type="button" class="dash-btn dash-btn-ghost" onclick="openMessagesWithUser(${e.teacher_id}, '${teacherName.replace(/'/g, "\\'")}')">Message</button>`;
        const btnPrimary = `<button type="button" class="dash-btn dash-btn-primary" onclick="openStudentLessonsModal(${e.id}, '${(e.skill_name || "").replace(/'/g, "\\'")}', '${teacherName.replace(/'/g, "\\'")}', ${e.credits ?? 0})">Continue</button>`;
        if (D) {
            return D.lessonCardHtml({
                title: e.skill_name || "Untitled",
                meta: `Teacher: ${teacherName}`,
                pct, done: completedLessons, total: lessonsCount,
                status: e.status || "active",
                statusClass,
                avatarName: teacherName,
                avatarId: e.teacher_id,
                btnPrimary, btnGhost,
            });
        }
        return `<div>${e.skill_name}</div>`;
    }).join("");
}

// ── Student Lesson Details ──────────────────────────────────────
let _currentStudentEnrollment = null;

async function viewLessonDetails(enrollment_id) {
    openStudentLessonsModal(enrollment_id);
}

function openStudentLessonsModal(enrollment_id, course_title, teacher_name, credits_per_session) {
    _currentStudentEnrollment = enrollment_id;
    
    apiInstance.getMyEnrollmentLessons(enrollment_id).then(result => {
        if (!result.success) {
            alert('Failed to load lesson details: ' + (result.data.error || 'Unknown error'));
            return;
        }
        const d = result.data;
        
        document.getElementById('sl-course-title').textContent =
            `${d.course_title || course_title || 'Course'}${credits_per_session > 0 ? '  —  ' + credits_per_session + ' credits / lesson' : ''}`;
        document.getElementById('sl-teacher-info').textContent = 'Teacher: ' + (d.teacher_full_name || teacher_name || 'Unknown');
        
        const lessonsList = document.getElementById('student-lessons-list');
        const lessons = d.lessons || [];
        const total = d.lessons_count || lessons.length || 0;
        const done = lessons.filter(l => l.status === 'completed').length;
        const pct = total > 0 ? Math.round((done / total) * 100) : 0;
        
        const summaryHtml = `
            <div style="margin-bottom:var(--spacing-md);">
                <div class="progress-label">
                    <span>Lesson Progress</span>
                    <span class="progress-text">${done}/${total} completed</span>
                </div>
                <div class="progress-bar">
                    <div class="progress-fill" style="width:${pct}%"></div>
                </div>
            </div>`;
        
        if (lessons.length === 0) {
            lessonsList.innerHTML = summaryHtml + '<p class="no-offers-msg">No lessons for this enrollment.</p>';
        } else {
            lessonsList.innerHTML = summaryHtml + lessons.map(
                (l) => {
                    const completed = l.status === 'completed';
                    const completedAt = l.completed_at
                        ? '<small style="color:var(--color-text-muted);margin-left:8px;">' +
                          new Date(l.completed_at).toLocaleDateString() + '</small>'
                        : '';
                    const summary = l.lesson_summary ? `<div class="lesson-summary" style="margin-top:6px;">${l.lesson_summary}</div>` : '';
                    const hasComm = l.teacher_comment && l.teacher_comment.trim();
                    const commDisp = hasComm ? l.teacher_comment.replace(/</g,'&lt;').replace(/>/g,'&gt;') : '';
                    const commHtml = hasComm
                        ? `<div class="teacher-comment" style="margin-top:6px;">"${commDisp}"</div>`
                        : '<div class="teacher-comment" style="margin-top:6px;font-style:italic;color:#999;">No comment yet</div>';
                    
                    return `
                    <div class="lesson-item ${completed ? 'completed' : ''}" data-lesson="${l.lesson_number}">
                        <div style="min-width:0;">
                            <div>
                                <span class="lesson-number">Lesson ${l.lesson_number}</span>
                                <span class="lesson-status ${completed ? 'completed' : 'pending'}">${l.status || 'pending'}</span>
                                ${completedAt}
                            </div>
                            ${summary}
                            ${commHtml}
                        </div>
                        <div>
                            <button class="${completed ? 'btn btn-secondary' : 'btn btn-primary'}" disabled>
                                ${completed ? '✓ Done' : 'Pending'}
                            </button>
                        </div>
                    </div>`;
                }
            ).join('');
        }
        
        // Show final comment if enrollment is completed
        const finalCommentArea = document.getElementById('student-final-comment');
        const finalCommentText = document.getElementById('student-final-comment-text');
        const finalCommentDate = document.getElementById('student-final-comment-date');
        
        if (finalCommentArea && finalCommentText && finalCommentDate) {
            if (d.enrollment_status === 'completed' && d.final_teacher_comment) {
                finalCommentText.textContent = d.final_teacher_comment;
                finalCommentDate.textContent = d.final_comment_created_at 
                    ? `Added on ${new Date(d.final_comment_created_at).toLocaleDateString()}` 
                    : '';
                finalCommentArea.style.display = 'block';
            } else {
                finalCommentArea.style.display = 'none';
            }
        }
        
        document.getElementById('student-lessons-modal').style.display = 'flex';
    });
}

function closeStudentLessonsModal() {
    document.getElementById('student-lessons-modal').style.display = 'none';
    _currentStudentEnrollment = null;
}


async function loadDashboard() {
	const user = getUser();
	if (!user) return;
	const amountEl = document.getElementById("header-credits-amount");
	if (amountEl) amountEl.textContent = user.credits || 0;

	if (typeof DashboardUI !== "undefined") DashboardUI.syncProfileSidebar();

	const offersResult = await apiInstance.getMyTeachingOffers();
	const isTeacher = offersResult.success && offersResult.data?.offers?.length > 0;
	const teachingCount = isTeacher ? offersResult.data.offers.length : 0;

	const teacherDashboard = document.getElementById("teacher-dashboard");
	const studentDashboard = document.getElementById("student-dashboard");
	if (teacherDashboard) teacherDashboard.style.display = isTeacher ? "contents" : "none";
	if (studentDashboard) studentDashboard.style.display = "contents";

	let teacherStats = {};
	let studentStats = {};
	let teacherEnrollments = [];
	let studentEnrollments = [];

	if (isTeacher) {
		const [ts, te] = await Promise.all([
			apiInstance.getTeacherStats(),
			apiInstance.getTeacherEnrollments(),
		]);
		if (ts.success) teacherStats = ts.data;
		if (te.success) teacherEnrollments = te.data.enrollments || [];
		loadTeacherStats();
		renderTeacherEnrollments(teacherEnrollments);
	}

	const [ss, se] = await Promise.all([
		apiInstance.getStudentStats(),
		apiInstance.getStudentEnrollments(),
	]);
	if (ss.success) studentStats = ss.data;
	if (se.success) studentEnrollments = se.data.enrollments || [];
	loadStudentStats();
	renderStudentEnrollments(studentEnrollments);

	if (typeof DashboardUI !== "undefined") {
		DashboardUI.updateHero(user, studentStats, teacherStats, isTeacher);
		DashboardUI.updateStatCards(user, studentStats, teacherStats, teachingCount, isTeacher);
		DashboardUI.renderActivityFeed(studentEnrollments, teacherEnrollments, user);
		DashboardUI.renderCalendarPreview(studentEnrollments.length ? studentEnrollments : teacherEnrollments);
		await DashboardUI.loadMessagesPreview();
	}
}

async function loadEnrollmentsTab(filter) {
	// Update active tab button
	document.querySelectorAll(".tab-btn").forEach((btn) => {
		btn.classList.toggle("active", btn.dataset.tab === filter);
	});

	const container = document.getElementById("enrollments-list");
	if (!container) return;

	container.innerHTML = '<p class="no-offers-msg">Loading...</p>';
	const result = await apiInstance.getMyEnrolledLessons();
	if (result.success) {
		renderEnrollments(result.data.enrollments, filter);
	} else {
		container.innerHTML = '<p class="no-offers-msg">' + (result.data.error || 'Failed to load') + '</p>';
	}
}

function renderEnrollments(enrollments, filter) {
	const container = document.getElementById("enrollments-list");
	let filtered = enrollments || [];
	if (filter && filter !== "all") {
		filtered = filtered.filter(e => (e.status || '').toLowerCase() === filter.toLowerCase());
	}
	if (filtered.length === 0) {
		container.innerHTML = '<p class="no-offers-msg">No enrollments found.</p>';
		return;
	}
	container.innerHTML = filtered
		.map(
			(e) => `
        <div class="skill-card offer-card">
            <h4>${e.skill_name || 'Untitled Skill'}</h4>
            <p class="offer-teacher">by ${e.teacher_username || 'Unknown teacher'}</p>
            <div class="lesson-progress">
                <div class="progress-label">
                    <span>Lessons:</span> 
                    <span class="progress-text">${e.completed_lessons || 0}/${e.lessons_count || 0}</span>
                </div>
                <div class="progress-bar">
                    <div class="progress-fill" style="width: ${e.lessons_count > 0 ? ((e.completed_lessons || 0) / e.lessons_count) * 100 : 0}%;"></div>
                </div>
            </div>
            ${e.scheduled_at ? `<p class="offer-credits">Scheduled: ${new Date(e.scheduled_at).toLocaleString()}</p>` : ''}
            ${e.status === 'confirmed' && e.scheduled_at ? `<button class="btn btn-primary" style="width:100%;margin-top:8px;">Join Session</button>` : ''}
            ${e.status !== 'cancelled' && e.status !== 'completed'
                ? `<button class="btn btn-secondary" style="width:100%;margin-top:8px;" onclick="cancelEnrollment(${e.id})">Cancel Enrollment</button>`
                : ''}
            ${e.remaining_lessons > 0
                ? `<button class="btn btn-info" style="width:100%;margin-top:8px;" onclick="viewLessonDetails(${e.id})">View Lessons</button>`
                : ''}        </div>
    `,
		)
		.join("");
}

async function loadProfile() {
	updateNav();
	loadUserTeachingOffers();
}

async function loadUserTeachingOffers() {
	const result = await apiInstance.getMyTeachingOffers();
	if (result.success) {
		renderUserTeachingOffers(result.data.offers);
	}
}

function renderUserTeachingOffers(offers) {
	const container = document.getElementById("my-teaching-offers");
	if (!offers || offers.length === 0) {
		container.innerHTML = '<p class="muted">You have no teaching offers yet.</p>';
		return;
	}
	container.innerHTML = offers
		.map(
			(offer) => `
        <div class="skill-card offer-card" data-offer-id="${offer.offer_id}">
            <h4>${offer.name || 'Untitled Skill'}</h4>
            <p>Credits: ${offer.credits} / lesson</p>
            <p class="offer-lessons">${offer.lessons_count || 1} lesson${offer.lessons_count !== 1 ? 's' : ''}</p>
            <div class="offer-actions">
                <button class="btn btn-secondary" onclick="openEditModal(${offer.offer_id}, '${(offer.name || '').replace(/'/g, "\\'")}', '${(offer.description || '').replace(/'/g, "\\'")}', '${(offer.skill_level || '').replace(/'/g, "\\'")}', '${(offer.lesson_format || '').replace(/'/g, "\\'")}', '${(offer.learner_gains || '').replace(/'/g, "\\'")}', ${offer.credits}, ${offer.lessons_count || 1})">Edit</button>
                <button class="btn btn-danger" onclick="deleteMyOffer(${offer.offer_id})">Delete</button>
            </div>
            <button class="btn btn-primary" style="width:100%;margin-top:6px;" onclick="loadOfferLearners(${offer.offer_id})">View Learners</button>
            <div id="learners-${offer.offer_id}" class="learners-inline" style="display:none;"></div>
        </div>
    `,
		)
		.join("");
}

async function deleteMyOffer(offer_id) {
	if (!confirm("Delete this teaching offer? This cannot be undone.")) return;
	const result = await apiInstance.removeTeachingOffer(offer_id);
	if (result.success) {
		loadUserTeachingOffers();
	}
	alert(result.data.message || result.data.error);
}

// ── Edit Modal ──
let _currentEditOfferId = null;

function openEditModal(offer_id, name, description, level, format, gains, credits, lesson_count) {
	_currentEditOfferId = offer_id;
	document.getElementById('edit-offer-id').value    = offer_id;
	document.getElementById('edit-skill-name').value  = name       || '';
	document.getElementById('edit-skill-description').value  = description || '';
	document.getElementById('edit-skill-level').value         = level       || '';
	document.getElementById('edit-lesson-format').value       = format      || '';
	document.getElementById('edit-learner-gains').value       = gains       || '';
	document.getElementById('edit-credits').value             = credits     || 5;
	document.getElementById('edit-lesson-count').value        = lesson_count || 1;
	document.getElementById('edit-offer-modal').style.display = 'flex';
}

function closeEditModal() {
	document.getElementById('edit-offer-modal').style.display = 'none';
	_currentEditOfferId = null;
}

async function handleEditOfferSubmit(e) {
	e.preventDefault();
	if (!_currentEditOfferId) return;
	const payload = {
		offer_id         : _currentEditOfferId,
		skill_name       : document.getElementById('edit-skill-name').value.trim(),
		skill_description: document.getElementById('edit-skill-description').value.trim(),
		skill_level      : document.getElementById('edit-skill-level').value,
		lesson_format    : document.getElementById('edit-lesson-format').value.trim(),
		learner_gains    : document.getElementById('edit-learner-gains').value.trim(),
		credits          : parseInt(document.getElementById('edit-credits').value),
		lessons_count    : parseInt(document.getElementById('edit-lesson-count').value),
	};
	const result = await apiInstance.editTeachingOffer(payload);
	if (result.success) {
		alert(result.data.message || 'Offer updated!');
		closeEditModal();
		loadUserTeachingOffers();
	} else {
		alert(result.data.error || 'Failed to update offer');
	}
}

// ── Learners Modal ──
async function loadOfferLearners(offer_id) {
	document.getElementById('learners-modal').style.display = 'flex';
	document.getElementById('learners-list').innerHTML = '<p>Loading…</p>';
	const result = await apiInstance.getOfferLearners(offer_id);
	if (result.success) {
		renderLearners(result.data.learners);
	} else {
		document.getElementById('learners-list').innerHTML = '<p>' + (result.data.error || 'Failed to load learners') + '</p>';
	}
}

function renderLearners(learners) {
	const container = document.getElementById('learners-list');
	if (!learners || learners.length === 0) {
		container.innerHTML = '<p class="no-learners">No learners have enrolled yet.</p>';
		return;
	}
	container.innerHTML = learners
		.map(
			(l) => `
        <div class="learner-card">
            <div class="learner-avatar">${(l.full_name || l.username || '?')[0].toUpperCase()}</div>
            <div>
                <div class="learner-name">${l.full_name || l.username}</div>
                <div class="learner-email">${l.email || ''}</div>
            </div>
            <span class="learner-status ${l.status}">${l.status}</span>
        </div>
    `,
		)
		.join("");
}

function closeLearnersModal() {
	document.getElementById('learners-modal').style.display = 'none';
}

async function loadSkills() {
	// skills table was removed; users enter skill names directly on create/edit forms
	console.debug('[loadSkills] stubbed — no skills catalogue');
}

// skills table removed — users type skill names directly into offer forms
function renderSkills(skills, containerId) {
    const container = document.getElementById(containerId);
    if (!container) return;
    container.innerHTML = "<p>Skill catalogue removed.</p>";
}

async function loadOffersPage() {
	await Promise.all([
		loadMyOffers(),
		loadAllOffers(),
	]);
}

async function loadMyOffers() {
	const result = await apiInstance.getMyTeachingOffers();
	if (result.success) {
		renderMyOffers(result.data.offers);
	}
}

async function loadAllOffers() {
	const result = await apiInstance.getTeachingOffers();
	if (result.success) {
		renderTeachingOffers(result.data.offers);
	}
}

function updateOffersCount(count) {
	const countEl = document.getElementById('offers-count');
	if (countEl) countEl.textContent = count;
}

async function loadSkills() {
	// skills table was removed; users enter skill names directly on create/edit forms
	console.debug('[loadSkills] stubbed — no skills catalogue');
}

// skills table removed — users type skill names directly into offer forms
function renderSkills(skills, containerId) {
    const container = document.getElementById(containerId);
    if (!container) return;
    container.innerHTML = "<p>Skill catalogue removed.</p>";
}

async function loadOffersPage() {
	await Promise.all([
		loadMyOffers(),
		loadAllOffers(),
	]);
}

async function loadMyOffers() {
	const result = await apiInstance.getMyTeachingOffers();
	if (result.success) {
		renderMyOffers(result.data.offers);
	} else {
		document.getElementById("my-offers-list").innerHTML = "";
	}
}

async function loadAllOffers() {
	const result = await apiInstance.getTeachingOffers();
	if (result.success) {
		renderTeachingOffers(result.data.offers);
	}
}

function renderMyOffers(offers) {
	const container  = document.getElementById("my-offers-list");
	if (!container) return;

	if (!offers || offers.length === 0) {
		container.innerHTML = '<p class="no-offers-msg">No offers yet. Create your first offer above.</p>';
		return;
	}
	container.innerHTML = offers
		.map(
			(offer) => `
        <div class="skill-card offer-card" data-offer-id="${offer.offer_id}">
            <h4>${offer.name || 'Untitled Skill'}</h4>
            ${offer.description ? `<p class="offer-desc">${offer.description}</p>` : ''}
            <span class="status-badge status-${offer.type || 'active'}">${offer.type || 'Active'}</span>
            <p class="offer-credits">${offer.credits} credits / lesson</p>
            <p class="offer-lessons">${offer.lessons_count || 1} lesson${offer.lessons_count !== 1 ? 's' : ''}</p>
            <div class="offer-actions" style="margin-top: 12px;">
                <button class="btn btn-secondary" onclick="openEditModal(${offer.offer_id}, '${(offer.name || '').replace(/'/g, "\\'")}', '${(offer.description || '').replace(/'/g, "\\'")}', '${(offer.skill_level || '').replace(/'/g, "\\'")}', '${(offer.lesson_format || '').replace(/'/g, "\\'")}', '${(offer.learner_gains || '').replace(/'/g, "\\'")}', ${offer.credits}, ${offer.lessons_count || 1})">Edit</button>
                <button class="btn btn-danger" onclick="deleteMyOffer(${offer.offer_id})">Delete</button>
            </div>
        </div>
    `,
		)
		.join("");
}

function cancelEnrollment(enrollmentId) {
	if (!confirm("Cancel this enrollment?")) return;
	alert("Cancel enrollment: " + enrollmentId);
}

function renderTeachingOffers(offers) {
	const container = document.getElementById("teaching-offers-grid");
	if (!offers || offers.length === 0) {
		container.innerHTML = '<div class="no-offers-msg" style="grid-column: 1/-1; padding: 60px 0;">No teaching offers found. Be the first to create one!</div>';
		updateOffersCount(0);
		return;
	}
	updateOffersCount(offers.length);
	container.innerHTML = offers.map((offer) => {
		const levelClass = offer.skill_level?.toLowerCase() || 'beginner';
		const teacherInitial = (offer.full_name || offer.username || '?')[0].toUpperCase();
		const description = offer.description || offer.learner_gains || 'No description available';
		const lessonCount = offer.lessons_count || 1;
		return `
			<div class="offer-card" data-offer-id="${offer.offer_id}">
				<div class="offer-card-header">
					<div class="offer-card-banner"></div>
					<span class="offer-level-badge">${offer.skill_level || 'Beginner'}</span>
				</div>
				<div class="offer-teacher-row">
					<div class="offer-teacher-avatar">${teacherInitial}</div>
					<div class="offer-teacher-details">
						<p class="offer-teacher-name">${offer.full_name || offer.username}</p>
						<div class="offer-rating">
							<span>★</span><span>4.8</span><span>(24)</span>
						</div>
					</div>
				</div>
				<div class="offer-content">
					<h3 class="offer-title">${offer.name || 'Untitled Skill'}</h3>
					<p class="offer-description">${description}</p>
					<div class="offer-features">
						<div class="offer-feature">
							<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
								<polygon points="22 11 12 2 2 11 12 20 21 11 12 2"></polygon>
							</svg>
							<span>Live practice sessions</span>
						</div>
						<div class="offer-feature">
							<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
								<path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path>
								<polyline points="22 4 12 14.01 9 11.01"></polyline>
							</svg>
							<span>Personalized learning path</span>
						</div>
					</div>
					<div class="offer-tags">
						<span class="offer-tag">${offer.skill_level || 'All Levels'}</span>
						<span class="offer-tag">${lessonCount} lesson${lessonCount !== 1 ? 's' : ''}</span>
					</div>
				</div>
				<div class="offer-footer">
					<div class="offer-pricing">
						<p class="offer-price">${offer.credits || 5} credits</p>
						<span class="offer-lessons">~/lesson</span>
					</div>
					<div class="offer-actions">
						<button class="offer-btn offer-btn-primary" onclick="enrollInOffer(${offer.offer_id})">Enroll</button>
					</div>
				</div>
			</div>
		`;
	}).join('');
}

async function enrollInOffer(offer_id) {
	const user = getUser();
	if (!user) {
		alert("Please login first");
		return;
	}
	const result = await apiInstance.enrollInOffer(offer_id);
	if (result.success) {
		alert("Enrolled successfully!");
		loadOffersPage();
	} else {
		alert(result.data.error || "Failed to enroll");
	}
}

// Teaching offer creation is handled via the standalone Teaching Offers page.

async function loadAddOfferPage() {
	// no dynamic data to pre-load for the manual-entry form
}

async function handleAddOffer(e) {
	try {
		e.preventDefault();
		const user = getUser();
		if (!user) {
			alert("Please login first");
			showPage("login");
			return;
		}

		const form = e.target;
		const skill_name        = form.skill_name.value.trim();
		const skill_description = form.skill_description?.value.trim() || null;
		const skill_level       = form.skill_level?.value || null;
		const lesson_format     = form.lesson_format?.value.trim() || null;
		const learner_gains     = form.learner_gains?.value.trim() || null;
		const credits           = parseInt(form.credits.value);
		const lesson_count      = parseInt(form.lesson_count.value);

		if (!skill_name || !credits || credits < 1) {
			alert("Please enter a skill name and valid credits.");
			return;
		}

		if (!lesson_count || lesson_count < 1) {
			alert("Please enter a valid number of lessons.");
			return;
		}

		const payload = {
			user_id: user.id,
			skill_name,
			skill_description,
			skill_level,
			lesson_format,
			learner_gains,
			credits,
			lessons_count: lesson_count
		};
		console.log('[AddOffer] submitting payload:', JSON.stringify(payload));

		const result = await apiInstance.addTeachingOffer(payload);
		console.log('[AddOffer] response:', JSON.stringify(result));

		if (result.success) {
			alert(result.data.message || "Offer created successfully!");
			form.reset();
			showPage("offers");
		} else {
			alert(result.data.error || "Failed to create offer");
		}
	} catch (err) {
		console.error('[AddOffer] unexpected error:', err);
		alert('An unexpected error occurred: ' + err.message);
	}
}

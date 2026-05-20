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

	updateNav();
	if (page === "dashboard") loadDashboard();
	if (page === "profile") loadProfile();
	if (page === "offers") loadOffersPage();
	if (page === "add-offer") loadAddOfferPage();
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
    if (!result.success) {
        document.getElementById('teacher-enrollments-list').innerHTML =
            '<p class="no-offers-msg">No students yet.</p>';
        return;
    }
    const list = result.data.enrollments || [];
    const container = document.getElementById('teacher-enrollments-list');
    if (!container) return;

    if (list.length === 0) {
        container.innerHTML = '<p class="no-offers-msg">No students yet. Share your offers to attract learners!</p>';
        return;
    }

    container.innerHTML = list.map(
        (e) => {
            const lc   = (e.lessons_count      ?? 0);
            const done = (e.completed_lessons  ?? 0);
            const rem  = (e.remaining_lessons  ?? 0);
            const pct  = lc > 0 ? Math.round((done / lc) * 100) : 0;
            return `
            <div class="teacher-enrollment-row">
                <div class="teacher-enrollment-info">
                    <div class="teacher-enrollment-title">${e.skill_name || 'Untitled'}</div>
                    <div class="teacher-enrollment-sub">${e.learner_username || 'Unknown student'}</div>
                    <div style="margin-top:6px;">
                        <div class="progress-label">
                            <span>Progress</span>
                            <span class="progress-text">${done}/${lc} lessons · ${rem} left</span>
                        </div>
                        <div class="progress-bar">
                            <div class="progress-fill" style="width:${pct}%"></div>
                        </div>
                    </div>
                </div>
                <button class="btn btn-primary" onclick="openTeacherEnrollmentModal(${e.id}, '${(e.learner_username || '').replace(/'/g, "\\'")}', '${(e.skill_name || '').replace(/'/g, "\\'")}', ${(e.credits ?? 0)})">Open</button>
            </div>`;
        }
    ).join('');
}

let _currentTeacherEnrollment = null;

async function openTeacherEnrollmentModal(enrollment_id, student_name, course_title, credits_per_session) {
    _currentTeacherEnrollment = enrollment_id;
    const result = await apiInstance.getEnrollmentLessons(enrollment_id, student_name);
    if (!result.success) {
        alert('Failed to load lesson details: ' + (result.data.error || 'Unknown error'));
        return;
    }
    const d = result.data;

    document.getElementById('modal-course-title').textContent =
        `${d.course_title || course_title || 'Course'}${credits_per_session > 0 ? '  —  ' + credits_per_session + ' credits / lesson' : ''}`;
    document.getElementById('modal-student-info').textContent = 'Student: ' + (d.student_name || 'Unknown');
    document.getElementById('mark-complete-comment').value = '';

    const lessonsList = document.getElementById('teacher-lessons-list');
    const lessons     = d.lessons  || [];
    const total       = (d.lessons_count || lessons.length || 1);
    const done        = lessons.filter(l => l.status === 'completed').length;
    const pct         = Math.round((done / total) * 100);

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
                const completed = l.status === 'completed';
                const completedAt = l.completed_at
                    ? '<small style="color:var(--color-text-muted);margin-left:8px;">' +
                      new Date(l.completed_at).toLocaleDateString() + '</small>'
                    : '';
                const summary = l.lesson_summary ? `<div class="lesson-summary" style="margin-top:6px;">${l.lesson_summary}</div>` : '';
                const comment = l.teacher_comment ? `<div class="teacher-comment" style="margin-top:6px;">${l.teacher_comment}</div>` : '';
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
                        ${comment}
                    </div>
                    <button class="${btnClass}" ${btnDisabled}
                        onclick="markLessonComplete(${enrollment_id}, ${l.lesson_number})">
                        ${btnLabel}
                    </button>
                </div>`;
            }
        ).join('');
    }

    document.getElementById('teacher-enrollment-modal').style.display = 'flex';
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

async function loadDashboard() {
	const user = getUser();
	if (!user) return;
	const amountEl = document.getElementById("header-credits-amount");
	if (amountEl) {
		amountEl.textContent = user.credits || 0;
		console.debug('[loadDashboard] Set credits to:', user.credits || 0);
	}
	loadEnrollmentsTab("all");
	loadTeacherStats();
	loadTeacherEnrollments();
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
            ${e.status === 'pending' ? `<button class="btn btn-secondary" style="width:100%;margin-top:8px;" onclick="cancelEnrollment(${e.enrollment_id})">Cancel Enrollment</button>` : ''}
            ${e.remaining_lessons > 0 && e.status === 'confirmed' ? `<button class="btn btn-info" style="width:100%;margin-top:8px;" onclick="viewLessonDetails(${e.enrollment_id})">View Lessons</button>` : ''}
        </div>
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
	const container = document.getElementById("teaching-offers-list");
	if (!offers || offers.length === 0) {
		container.innerHTML = '<p class="no-offers-msg">No teaching offers found.</p>';
		return;
	}
	container.innerHTML = offers
		.map(
			(offer) => `
        <div class="skill-card offer-card">
            <h4>${offer.name || 'Untitled Skill'}</h4>
            <p class="offer-teacher">
                <span class="offer-teacher-avatar">${(offer.full_name || offer.username || '?')[0].toUpperCase()}</span>
                ${offer.full_name || offer.username}
            </p>
            ${offer.description ? `<p class="offer-desc">${offer.description}</p>` : ''}
            ${offer.skill_level ? `<p class="offer-level">Level: ${offer.skill_level}</p>` : ''}
            ${offer.lesson_format ? `<p class="offer-format"><b>How it works:</b> ${offer.lesson_format}</p>` : ''}
            ${offer.learner_gains ? `<p class="offer-gains"><b>What you'll get:</b> ${offer.learner_gains}</p>` : ''}
            <p class="offer-credits">${offer.credits} credits / lesson</p>
            <p class="offer-lessons">${offer.lessons_count || 1} lesson${offer.lessons_count !== 1 ? 's' : ''}</p>
            <button class="btn btn-primary enroll-btn" onclick="enrollInOffer(${offer.offer_id})">Enroll</button>
        </div>
    `,
		)
		.join("");
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

// Premium dashboard UI layer (no backend changes)
const DashboardUI = {
    AVATAR_COLORS: ['#4F46E5', '#6366F1', '#7C3AED', '#2563EB', '#0891B2', '#059669'],

    init() {
        this.bindShellEvents();
        this.syncProfileSidebar();
    },

    bindShellEvents() {
        const app = document.getElementById('dash-app');
        const toggle = document.getElementById('dash-menu-toggle');
        const overlay = document.getElementById('dash-sidebar-overlay');

        toggle?.addEventListener('click', () => app?.classList.toggle('sidebar-open'));
        overlay?.addEventListener('click', () => app?.classList.remove('sidebar-open'));

        document.querySelectorAll('.dash-nav-link[data-page]').forEach(link => {
            link.addEventListener('click', () => app?.classList.remove('sidebar-open'));
        });

        document.querySelectorAll('.dash-nav-link[data-dash-scroll]').forEach(link => {
            link.addEventListener('click', (e) => {
                e.preventDefault();
                const id = link.getAttribute('data-dash-scroll');
                document.getElementById(id)?.scrollIntoView({ behavior: 'smooth', block: 'start' });
                app?.classList.remove('sidebar-open');
            });
        });

        document.getElementById('dash-global-search')?.addEventListener('input', (e) => {
            this.filterDashboardContent(e.target.value);
        });

        document.querySelectorAll('[data-dash-nav]').forEach(el => {
            el.addEventListener('click', () => {
                setTimeout(() => this.setActiveNav(el.getAttribute('data-dash-nav')), 50);
            });
        });
    },

    setActiveNav(page) {
        document.querySelectorAll('.dash-nav-link').forEach(l => {
            const nav = l.getAttribute('data-dash-nav');
            const dataPage = l.getAttribute('data-page');
            l.classList.toggle('active', nav === page || dataPage === page);
        });
    },

    syncProfileSidebar() {
        const user = typeof getUser === 'function' ? getUser() : null;
        if (!user) return;
        const name = user.full_name || user.username || 'User';
        const elName = document.getElementById('global-sidebar-name');
        const elCredits = document.getElementById('global-sidebar-credits');
        const elAvatar = document.getElementById('global-sidebar-avatar');
        if (elName) elName.textContent = name;
        if (elCredits) elCredits.textContent = user.credits ?? 0;
        if (elAvatar) {
            elAvatar.textContent = this.initials(name);
            elAvatar.style.background = this.avatarColor(user.id);
        }
    },

    initials(name) {
        const parts = (name || 'U').trim().split(/\s+/);
        if (parts.length >= 2) return (parts[0][0] + parts[parts.length - 1][0]).toUpperCase();
        return (name || 'U').substring(0, 2).toUpperCase();
    },

    avatarColor(id) {
        return this.AVATAR_COLORS[Math.abs(Number(id) || 0) % this.AVATAR_COLORS.length];
    },

    filterDashboardContent(query) {
        const q = (query || '').trim().toLowerCase();
        document.querySelectorAll('.dash-lesson-card, .dash-msg-item').forEach(el => {
            const text = el.textContent.toLowerCase();
            el.style.display = !q || text.includes(q) ? '' : 'none';
        });
    },

    updateHero(user, studentStats, teacherStats, isTeacher) {
        const name = user?.full_name || user?.username || 'there';
        const title = document.getElementById('dash-welcome-title');
        const sub = document.getElementById('dash-welcome-sub');
        if (title) title.textContent = `Welcome back, ${name.split(' ')[0]}`;
        const completed = (studentStats?.total_completed_lessons || 0) +
            (isTeacher ? (teacherStats?.completed_lessons || 0) : 0);
        if (sub) {
            sub.textContent = completed > 0
                ? `You completed ${completed} lesson${completed !== 1 ? 's' : ''} across your courses.`
                : 'Start a new lesson or connect with your learning community.';
        }
        const heroLessons = document.getElementById('dash-hero-lessons');
        if (heroLessons) heroLessons.textContent = studentStats?.total_completed_lessons || 0;

        const total = (studentStats?.total_enrollments || 0) * 3 || 1;
        const done = studentStats?.total_completed_lessons || 0;
        const pct = Math.min(100, Math.round((done / Math.max(total, 1)) * 100));
        const ring = document.getElementById('dash-ring-progress');
        const circumference = 2 * Math.PI * 52;
        if (ring) {
            ring.style.strokeDasharray = circumference;
            ring.style.strokeDashoffset = circumference - (pct / 100) * circumference;
        }
        const pctEl = document.getElementById('dash-ring-pct');
        if (pctEl) pctEl.textContent = `${pct}%`;
    },

    updateStatCards(user, studentStats, teacherStats, teachingCount, isTeacher) {
        const set = (id, val) => {
            const el = document.getElementById(id);
            if (el) el.textContent = val;
        };
        const activeLessons = (studentStats?.active_enrollments || 0) + (isTeacher ? (teacherStats?.active_enrollments || 0) : 0);
        set('dash-stat-active-lessons', activeLessons);
        set('dash-stat-teaching', teachingCount);
        set('dash-stat-learning', studentStats?.total_enrollments || 0);
        set('dash-stat-credits', user?.credits ?? 0);

        const hiddenStudents = document.getElementById('stat-students');
        const hiddenActive = document.getElementById('stat-active');
        const hiddenCompleted = document.getElementById('stat-completed');
        const hiddenEnroll = document.getElementById('stat-enrollments');
        const hiddenActiveEnroll = document.getElementById('stat-active-enrollments');
        const hiddenLessons = document.getElementById('stat-lessons-completed');

        if (hiddenStudents) hiddenStudents.textContent = teacherStats?.total_students ?? 0;
        if (hiddenActive) hiddenActive.textContent = teacherStats?.active_enrollments ?? 0;
        if (hiddenCompleted) hiddenCompleted.textContent = teacherStats?.completed_lessons ?? 0;
        if (hiddenEnroll) hiddenEnroll.textContent = studentStats?.total_enrollments ?? 0;
        if (hiddenActiveEnroll) hiddenActiveEnroll.textContent = studentStats?.active_enrollments ?? 0;
        if (hiddenLessons) hiddenLessons.textContent = studentStats?.total_completed_lessons ?? 0;

        set('dash-trend-lessons', activeLessons > 0 ? 'Active' : '—');
        set('dash-trend-teaching', teachingCount > 0 ? `${teachingCount} offers` : '—');
        set('dash-trend-learning', (studentStats?.total_enrollments || 0) > 0 ? 'Enrolled' : '—');

        const goalLessons = document.getElementById('dash-goal-lessons');
        const goalBar = document.getElementById('dash-goal-lessons-bar');
        const doneToday = (studentStats?.total_completed_lessons || 0) > 0 ? 1 : 0;
        if (goalLessons) goalLessons.textContent = `${doneToday}/1`;
        if (goalBar) goalBar.style.width = `${doneToday * 100}%`;
    },

    async loadMessagesPreview() {
        const container = document.getElementById('dash-messages-preview');
        if (!container || typeof apiInstance === 'undefined') return;

        const result = await apiInstance.getConversations();
        if (!result.success || !result.data.conversations?.length) {
            container.innerHTML = '<p class="dash-empty">No messages yet</p>';
            return;
        }

        const list = result.data.conversations.slice(0, 4);
        container.innerHTML = list.map(c => {
            const name = c.other_full_name || c.other_username || 'User';
            const unread = parseInt(c.unread_count, 10) || 0;
            const preview = c.last_message ? String(c.last_message).substring(0, 40) : 'No messages yet';
            const time = c.message_time ? new Date(c.message_time).toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' }) : '';
            const uid = c.other_user_id;
            return `
                <div class="dash-msg-item" role="button" tabindex="0"
                    onclick="openMessagesWithUser(${uid}, '${name.replace(/'/g, "\\'")}')">
                    <div class="dash-msg-avatar" style="background:${this.avatarColor(uid)}">
                        ${this.initials(name)}
                        <span class="online-dot"></span>
                    </div>
                    <div class="dash-msg-body">
                        <div class="dash-msg-name">${this.escape(name)}</div>
                        <div class="dash-msg-preview">${this.escape(preview)}</div>
                    </div>
                    <div class="dash-msg-meta">
                        ${time ? `<div class="dash-msg-time">${time}</div>` : ''}
                        ${unread > 0 ? `<span class="dash-msg-unread">${unread}</span>` : ''}
                    </div>
                </div>`;
        }).join('');

        const goalMsg = document.getElementById('dash-goal-msg');
        const goalMsgBar = document.getElementById('dash-goal-msg-bar');
        const hasMsg = list.some(c => c.last_message);
        if (goalMsg) goalMsg.textContent = hasMsg ? '1/1' : '0/1';
        if (goalMsgBar) goalMsgBar.style.width = hasMsg ? '100%' : '0%';
    },

    renderActivityFeed(studentEnrollments, teacherEnrollments, user) {
        const feed = document.getElementById('dash-activity-feed');
        if (!feed) return;

        const items = [];
        const credits = user?.credits ?? 0;
        if (credits > 0) {
            items.push({ icon: '💎', bg: 'rgba(79,70,229,0.12)', text: `You have <strong>${credits} credits</strong> available`, time: 'Wallet' });
        }
        (studentEnrollments || []).slice(0, 3).forEach(e => {
            if (e.status === 'completed') {
                items.push({ icon: '✅', bg: 'rgba(34,197,94,0.12)', text: `Completed <strong>${e.skill_name || 'a course'}</strong>`, time: 'Recently' });
            } else if ((e.completed_lessons || 0) > 0) {
                items.push({ icon: '📖', bg: 'rgba(99,102,241,0.12)', text: `Progress on <strong>${e.skill_name || 'course'}</strong> — ${e.completed_lessons}/${e.lessons_count || '?'} lessons`, time: 'Learning' });
            }
        });
        (teacherEnrollments || []).slice(0, 2).forEach(e => {
            items.push({ icon: '👤', bg: 'rgba(124,58,237,0.12)', text: `Teaching <strong>${e.learner_username || 'a student'}</strong> — ${e.skill_name || 'course'}`, time: 'Teaching' });
        });

        if (items.length === 0) {
            feed.innerHTML = '<p class="dash-empty">Your activity will appear here as you learn and teach.</p>';
            return;
        }

        feed.innerHTML = items.map((item, i) => `
            <div class="dash-timeline-item" style="animation-delay:${i * 0.05}s">
                <div class="dash-timeline-icon" style="background:${item.bg}">${item.icon}</div>
                <div>
                    <div class="dash-timeline-text">${item.text}</div>
                    <div class="dash-timeline-time">${item.time}</div>
                </div>
            </div>`).join('');
    },

    renderCalendarPreview(enrollments) {
        const el = document.getElementById('dash-calendar-list');
        if (!el) return;
        const list = enrollments || [];
        if (list.length === 0) {
            el.innerHTML = '<p class="dash-empty">No upcoming sessions scheduled</p>';
            return;
        }
        el.innerHTML = list.slice(0, 4).map((e, i) => {
            const d = new Date();
            d.setDate(d.getDate() + i + 1);
            const day = d.getDate();
            const month = d.toLocaleDateString([], { month: 'short' });
            const title = e.skill_name || 'Lesson session';
            const sub = e.teacher_full_name || e.learner_username || e.learner_name || 'Session';
            return `
                <div class="dash-cal-day">
                    <div class="dash-cal-date"><strong>${day}</strong><span>${month}</span></div>
                    <div>
                        <div style="font-weight:600;color:#1f2937;font-size:0.875rem">${this.escape(title)}</div>
                        <div style="font-size:0.75rem;color:#6b7280">${this.escape(sub)}</div>
                    </div>
                </div>`;
        }).join('');
    },

    escape(text) {
        const d = document.createElement('div');
        d.textContent = text ?? '';
        return d.innerHTML;
    },

    lessonCardHtml(opts) {
        const { title, meta, pct, done, total, status, statusClass, avatarName, avatarId, btnPrimary, btnGhost } = opts;
        return `
            <div class="dash-lesson-card">
                <span class="dash-status ${statusClass}">${status}</span>
                <div class="dash-lesson-card-top">
                    <div class="dash-lesson-avatar" style="background:${this.avatarColor(avatarId)}">${this.initials(avatarName)}</div>
                    <div>
                        <h4 class="dash-lesson-title">${this.escape(title)}</h4>
                        <p class="dash-lesson-meta">${this.escape(meta)}</p>
                    </div>
                </div>
                <div class="dash-lesson-progress">
                    <div class="dash-progress-label"><span>Progress</span><span>${done}/${total} lessons</span></div>
                    <div class="dash-progress-track"><div class="dash-progress-fill" style="width:${pct}%"></div></div>
                </div>
                <div class="dash-lesson-actions">${btnGhost}${btnPrimary}</div>
            </div>`;
    }
};

document.addEventListener('DOMContentLoaded', () => DashboardUI.init());

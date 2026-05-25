// Shared app shell — sidebar + nav sync (Dashboard design language)
const AppShell = {
    APP_PAGES: ['dashboard', 'offers', 'messages', 'profile', 'add-offer', 'video'],

    sidebarHtml() {
        return `
        <div class="dash-sidebar-overlay app-shell-overlay"></div>
        <aside class="dash-sidebar">
            <div class="dash-sidebar-brand">
                <div class="dash-brand-icon">SE</div>
                <span class="dash-brand-text">Skills Exchange</span>
            </div>
            <nav class="dash-nav" aria-label="Main">
                <a href="#" class="dash-nav-link" data-page="dashboard" data-dash-nav="dashboard">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="7" height="9" rx="1"/><rect x="14" y="3" width="7" height="5" rx="1"/><rect x="14" y="12" width="7" height="9" rx="1"/><rect x="3" y="16" width="7" height="5" rx="1"/></svg>
                    Dashboard
                </a>
                <a href="#" class="dash-nav-link" data-page="offers" data-dash-nav="offers">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 2L2 7l10 5 10-5-10-5z"/><path d="M2 17l10 5 10-5"/><path d="M2 12l10 5 10-5"/></svg>
                    My Skills
                </a>
                <a href="#" class="dash-nav-link" data-page="messages" data-dash-nav="messages">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg>
                    Messages
                </a>
                <a href="#" class="dash-nav-link" data-page="profile" data-dash-nav="profile">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1-2.83 2.83l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-4 0v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83-2.83l.06-.06A1.65 1.65 0 0 0 4.68 15a1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1 0-4h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 2.83-2.83l.06.06A1.65 1.65 0 0 0 9 4.68a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 4 0v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 2.83l-.06.06A1.65 1.65 0 0 0 19.4 9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 0 4h-.09a1.65 1.65 0 0 0-1.51 1z"/></svg>
                    Profile
                </a>
                <a href="#" class="dash-nav-link" data-page="profile" data-dash-nav="settings">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 15a3 3 0 1 0 0-6 3 3 0 0 0 0 6z"/><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1 0 2.83 2 2 0 0 1-2.83 0l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-2 2 2 2 0 0 1-2-2v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83 0 2 2 0 0 1 0-2.83l.06-.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1-2-2 2 2 0 0 1 2-2h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 0-2.83 2 2 0 0 1 2.83 0l.06.06a1.65 1.65 0 0 0 1.82.33H9a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 2-2 2 2 0 0 1 2 2v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 0 2 2 0 0 1 0 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82V9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 2 2 2 2 0 0 1-2 2h-.09a1.65 1.65 0 0 0-1.51 1z"/></svg>
                    Settings
                </a>
            </nav>
            <div class="dash-sidebar-foot">
                <div class="dash-profile-mini">
                    <div class="dash-profile-avatar app-shell-avatar">?</div>
                    <div class="dash-profile-info">
                        <div class="dash-profile-name app-shell-name">User</div>
                        <div class="dash-profile-credits"><span class="app-shell-credits">0</span> credits</div>
                    </div>
                </div>
                <button type="button" class="dash-upgrade-btn" data-page="offers">Explore Skills</button>
            </div>
        </aside>`;
    },

    topbarHtml(title, showNewOffer = true) {
        return `
        <header class="dash-topbar">
            <button type="button" class="dash-menu-toggle app-shell-menu-toggle" aria-label="Menu">
                <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 6h16M4 12h16M4 18h16"/></svg>
            </button>
            <div class="dash-topbar-title">${title}</div>
            <div class="dash-topbar-actions">
                <button type="button" class="dash-icon-btn" data-page="messages" aria-label="Messages">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg>
                </button>
                ${showNewOffer ? '<button type="button" class="dash-quick-btn" data-page="add-offer"><span>+ New Offer</span></button>' : ''}
            </div>
        </header>`;
    },

    init() {
        document.querySelectorAll('[data-app-shell]').forEach((app) => {
            if (app.dataset.shellMounted === '1') return;
            const sidebarSlot = app.querySelector('.app-shell-sidebar-slot');
            if (sidebarSlot) {
                sidebarSlot.innerHTML = this.sidebarHtml();
            }
            app.dataset.shellMounted = '1';
            this.bindApp(app);
        });
        this.syncAllProfiles();
        this.syncTopbarAuth();
    },

    bindApp(app) {
        if (app.dataset.shellBound === '1') return;
        app.dataset.shellBound = '1';
        const toggle = app.querySelector('.app-shell-menu-toggle');
        const overlay = app.querySelector('.app-shell-overlay');
        toggle?.addEventListener('click', () => app.classList.toggle('sidebar-open'));
        overlay?.addEventListener('click', () => app.classList.remove('sidebar-open'));
        app.querySelectorAll('.dash-nav-link[data-page], .dash-upgrade-btn[data-page]').forEach((link) => {
            link.addEventListener('click', () => app.classList.remove('sidebar-open'));
        });
    },

    topbarAuthHtml() {
        return `
        <div class="dash-topbar-auth" data-topbar-auth>
            <div class="dash-topbar-credits" title="Credits">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z"/></svg>
                <span class="dash-topbar-credits-amt">0</span>
            </div>
            <a href="#" class="dash-topbar-avatar" data-page="profile" aria-label="Profile"></a>
            <button type="button" class="btn-nav-logout logout-btn dash-topbar-logout" aria-label="Logout">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg>
                <span>Logout</span>
            </button>
        </div>`;
    },

    syncTopbarAuth() {
        const user = typeof getUser === 'function' ? getUser() : null;
        document.querySelectorAll('.dash-topbar-actions').forEach((actions) => {
            if (!actions.querySelector('[data-topbar-auth]')) {
                actions.insertAdjacentHTML('beforeend', this.topbarAuthHtml());
            }
        });
        if (!user) {
            document.querySelectorAll('[data-topbar-auth]').forEach((el) => {
                el.style.display = 'none';
            });
            return;
        }
        const name = user.full_name || user.username || 'User';
        const initials = typeof DashboardUI !== 'undefined'
            ? DashboardUI.initials(name)
            : name.substring(0, 2).toUpperCase();
        const color = typeof DashboardUI !== 'undefined'
            ? DashboardUI.avatarColor(user.id)
            : '#4f46e5';
        const credits = user.credits ?? 0;
        document.querySelectorAll('[data-topbar-auth]').forEach((el) => {
            el.style.display = 'flex';
        });
        document.querySelectorAll('.dash-topbar-credits-amt').forEach((el) => {
            el.textContent = credits;
        });
        document.querySelectorAll('.dash-topbar-avatar').forEach((el) => {
            el.textContent = initials;
            el.style.background = color;
        });
    },

    onNavigate(page) {
        this.init();
        this.setActiveNav(page);
        this.syncAllProfiles();
        this.syncTopbarAuth();
        document.querySelectorAll('[data-app-shell]').forEach((app) => {
            app.classList.remove('sidebar-open');
        });
    },

    setActiveNav(page) {
        const map = { settings: 'profile', 'add-offer': 'offers' };
        const active = map[page] || page;
        document.querySelectorAll('.dash-nav-link').forEach((l) => {
            const nav = l.getAttribute('data-dash-nav');
            const dataPage = l.getAttribute('data-page');
            const match =
                nav === active ||
                dataPage === page ||
                (page === 'profile' && nav === 'settings') ||
                (page === 'add-offer' && dataPage === 'offers');
            l.classList.toggle('active', match);
        });
    },

    syncAllProfiles() {
        const user = typeof getUser === 'function' ? getUser() : null;
        if (!user) return;
        const name = user.full_name || user.username || 'User';
        const initials = typeof DashboardUI !== 'undefined'
            ? DashboardUI.initials(name)
            : name.substring(0, 2).toUpperCase();
        const color =
            typeof DashboardUI !== 'undefined'
                ? DashboardUI.avatarColor(user.id)
                : '#4f46e5';
        document.querySelectorAll('.app-shell-avatar, #dash-sidebar-avatar').forEach((el) => {
            el.textContent = initials;
            el.style.background = color;
        });
        document.querySelectorAll('.app-shell-name, #dash-sidebar-name').forEach((el) => {
            el.textContent = name;
        });
        document.querySelectorAll('.app-shell-credits, #dash-sidebar-credits').forEach((el) => {
            el.textContent = user.credits ?? 0;
        });
    },
};

document.addEventListener('DOMContentLoaded', () => AppShell.init());

// Landing page — scroll, animations, mobile menu
const LandingUI = {
    init() {
        this.bindMobileMenu();
        this.bindSmoothScroll();
        this.bindReveal();
        this.bindCounters();
        this.bindNavScroll();
    },

    bindMobileMenu() {
        const nav = document.getElementById('landing-nav');
        const toggle = document.getElementById('landing-menu-toggle');
        const menu = document.getElementById('landing-nav-menu');
        if (!nav || !toggle || !menu) return;

        toggle.addEventListener('click', () => {
            const open = nav.classList.toggle('menu-open');
            toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
        });

        menu.querySelectorAll('a, button').forEach((el) => {
            el.addEventListener('click', () => {
                nav.classList.remove('menu-open');
                toggle.setAttribute('aria-expanded', 'false');
            });
        });

        document.addEventListener('click', (e) => {
            if (!nav.contains(e.target)) {
                nav.classList.remove('menu-open');
                toggle.setAttribute('aria-expanded', 'false');
            }
        });
    },

    bindSmoothScroll() {
        document.addEventListener('click', (e) => {
            const link = e.target.closest('[data-scroll]');
            if (!link) return;
            const href = link.getAttribute('href');
            if (!href || !href.startsWith('#')) return;
            const target = document.querySelector(href);
            if (!target || !document.getElementById('page-home')?.classList.contains('active')) return;
            e.preventDefault();
            const offset = 80;
            const top = target.getBoundingClientRect().top + window.scrollY - offset;
            window.scrollTo({ top, behavior: 'smooth' });
        });
    },

    bindNavScroll() {
        window.addEventListener('scroll', () => {
            const nav = document.getElementById('landing-nav');
            if (nav) nav.classList.toggle('scrolled', window.scrollY > 12);
        }, { passive: true });
    },

    bindReveal() {
        const els = document.querySelectorAll('.reveal');
        if (!els.length || !('IntersectionObserver' in window)) {
            els.forEach((el) => el.classList.add('is-visible'));
            return;
        }
        const io = new IntersectionObserver(
            (entries) => {
                entries.forEach((entry) => {
                    if (entry.isIntersecting) {
                        entry.target.classList.add('is-visible');
                        io.unobserve(entry.target);
                    }
                });
            },
            { threshold: 0.12, rootMargin: '0px 0px -40px 0px' }
        );
        els.forEach((el) => io.observe(el));
    },

    bindCounters() {
        const stats = document.querySelectorAll('[data-count]');
        if (!stats.length || !('IntersectionObserver' in window)) return;

        const animate = (el) => {
            const end = parseInt(el.dataset.count, 10) || 0;
            const suffix = el.dataset.suffix || '';
            const duration = 1600;
            const start = performance.now();

            const tick = (now) => {
                const p = Math.min((now - start) / duration, 1);
                const eased = 1 - Math.pow(1 - p, 3);
                const val = Math.floor(eased * end);
                if (end >= 10000) {
                    const k = Math.floor(val / 1000);
                    el.textContent = k + 'k' + suffix;
                } else {
                    el.textContent = val.toLocaleString() + suffix;
                }
                if (p < 1) requestAnimationFrame(tick);
            };
            requestAnimationFrame(tick);
        };

        const io = new IntersectionObserver(
            (entries) => {
                entries.forEach((entry) => {
                    if (entry.isIntersecting) {
                        animate(entry.target);
                        io.unobserve(entry.target);
                    }
                });
            },
            { threshold: 0.4 }
        );
        stats.forEach((el) => io.observe(el));
    },

    refreshAvatar() {
        const el = document.getElementById('landing-nav-avatar');
        if (!el || typeof getUser !== 'function') return;
        const user = getUser();
        if (!user) {
            el.style.display = 'none';
            return;
        }
        const name = user.full_name || user.username || 'U';
        const initials = typeof DashboardUI !== 'undefined'
            ? DashboardUI.initials(name)
            : name.substring(0, 2).toUpperCase();
        const color = typeof DashboardUI !== 'undefined'
            ? DashboardUI.avatarColor(user.id)
            : '#4f46e5';
        el.textContent = initials;
        el.style.background = color;
        el.style.display = 'flex';
    },

    refreshCredits(amount) {
        const el = document.getElementById('landing-nav-credits-amount');
        if (el) el.textContent = amount ?? 0;
    },
};

document.addEventListener('DOMContentLoaded', () => LandingUI.init());

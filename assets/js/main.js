// ===========================
// DTunnel Panel - Main JS
// ===========================

// Theme Management
const ThemeManager = {
    init() {
        const saved = localStorage.getItem('dtunnel-theme') || 'light';
        this.apply(saved);
    },
    apply(theme) {
        const html = document.documentElement;
        if (theme === 'dark') {
            html.classList.add('dark');
        } else {
            html.classList.remove('dark');
        }
        localStorage.setItem('dtunnel-theme', theme);
        this.updateToggleBtn(theme);
    },
    toggle() {
        const current = document.documentElement.classList.contains('dark') ? 'dark' : 'light';
        this.apply(current === 'dark' ? 'light' : 'dark');
    },
    updateToggleBtn(theme) {
        const btn = document.getElementById('theme-toggle');
        if (!btn) return;
        const sunIcon = btn.querySelector('.icon-sun');
        const moonIcon = btn.querySelector('.icon-moon');
        if (sunIcon && moonIcon) {
            if (theme === 'dark') {
                sunIcon.style.display = 'block';
                moonIcon.style.display = 'none';
            } else {
                sunIcon.style.display = 'none';
                moonIcon.style.display = 'block';
            }
        }
    }
};

// Toast Notifications
const Toast = {
    container: null,
    init() {
        this.container = document.getElementById('toast-container');
        if (!this.container) {
            this.container = document.createElement('div');
            this.container.id = 'toast-container';
            this.container.className = 'toast-container';
            document.body.appendChild(this.container);
        }
    },
    show(type, title, message, duration = 4000) {
        if (!this.container) this.init();
        const icons = {
            success: `<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>`,
            error: `<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>`,
            warning: `<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>`,
            info: `<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="12" y1="16" x2="12" y2="12"/><line x1="12" y1="8" x2="12.01" y2="8"/></svg>`
        };
        const toast = document.createElement('div');
        toast.className = `toast toast-${type}`;
        toast.innerHTML = `
            <div class="toast-icon">${icons[type] || icons.info}</div>
            <div class="toast-content">
                <div class="toast-title">${title}</div>
                ${message ? `<div class="toast-message">${message}</div>` : ''}
            </div>
        `;
        this.container.appendChild(toast);
        setTimeout(() => {
            toast.classList.add('hiding');
            setTimeout(() => toast.remove(), 300);
        }, duration);
    },
    success(title, message) { this.show('success', title, message); },
    error(title, message) { this.show('error', title, message); },
    warning(title, message) { this.show('warning', title, message); },
    info(title, message) { this.show('info', title, message); }
};

// Modal Management
const Modal = {
    open(id) {
        const overlay = document.getElementById(id);
        if (overlay) {
            overlay.classList.add('open');
            document.body.style.overflow = 'hidden';
        }
    },
    close(id) {
        const overlay = document.getElementById(id);
        if (overlay) {
            overlay.classList.remove('open');
            document.body.style.overflow = '';
        }
    },
    closeAll() {
        document.querySelectorAll('.modal-overlay.open').forEach(m => {
            m.classList.remove('open');
        });
        document.body.style.overflow = '';
    }
};

// Sidebar Management
const Sidebar = {
    init() {
        const toggle = document.getElementById('sidebar-toggle');
        const overlay = document.getElementById('sidebar-overlay');
        const sidebar = document.getElementById('sidebar');

        if (toggle) {
            toggle.addEventListener('click', () => this.toggleMobile());
        }
        if (overlay) {
            overlay.addEventListener('click', () => this.closeMobile());
        }

        // Accordion nav items
        document.querySelectorAll('.nav-item[data-submenu]').forEach(item => {
            item.addEventListener('click', (e) => {
                e.preventDefault();
                const submenuId = item.dataset.submenu;
                const submenu = document.getElementById(submenuId);
                if (submenu) {
                    const isOpen = submenu.classList.contains('open');
                    // Close all submenus
                    document.querySelectorAll('.nav-submenu.open').forEach(s => s.classList.remove('open'));
                    document.querySelectorAll('.nav-item.open').forEach(i => i.classList.remove('open'));
                    if (!isOpen) {
                        submenu.classList.add('open');
                        item.classList.add('open');
                    }
                }
            });
        });

        // Auto-open active submenu
        const activeItem = document.querySelector('.nav-submenu .nav-item.active');
        if (activeItem) {
            const submenu = activeItem.closest('.nav-submenu');
            if (submenu) {
                submenu.classList.add('open');
                const parentItem = document.querySelector(`[data-submenu="${submenu.id}"]`);
                if (parentItem) parentItem.classList.add('open');
            }
        }
    },
    toggleMobile() {
        const sidebar = document.getElementById('sidebar');
        const overlay = document.getElementById('sidebar-overlay');
        if (sidebar) sidebar.classList.toggle('open');
        if (overlay) overlay.classList.toggle('active');
    },
    closeMobile() {
        const sidebar = document.getElementById('sidebar');
        const overlay = document.getElementById('sidebar-overlay');
        if (sidebar) sidebar.classList.remove('open');
        if (overlay) overlay.classList.remove('active');
    }
};

// Dropdown Management
const Dropdown = {
    init() {
        document.querySelectorAll('[data-dropdown]').forEach(trigger => {
            trigger.addEventListener('click', (e) => {
                e.stopPropagation();
                const menuId = trigger.dataset.dropdown;
                const menu = document.getElementById(menuId);
                if (menu) {
                    const isOpen = menu.classList.contains('open');
                    this.closeAll();
                    if (!isOpen) menu.classList.add('open');
                }
            });
        });
        document.addEventListener('click', () => this.closeAll());
    },
    closeAll() {
        document.querySelectorAll('.dropdown-menu.open').forEach(m => m.classList.remove('open'));
    }
};

// Language Management
const LangManager = {
    translations: {
        'pt': {},
        'en': {},
        'es': {}
    },
    current: 'pt',
    init() {
        this.current = localStorage.getItem('dtunnel-lang') || 'pt';
        this.apply(this.current);
        document.querySelectorAll('.lang-btn').forEach(btn => {
            btn.addEventListener('click', () => {
                const lang = btn.dataset.lang;
                this.apply(lang);
            });
        });
    },
    apply(lang) {
        this.current = lang;
        localStorage.setItem('dtunnel-lang', lang);
        document.querySelectorAll('.lang-btn').forEach(btn => {
            btn.classList.toggle('active', btn.dataset.lang === lang);
        });
        document.documentElement.lang = lang;
    }
};

// Confirm Dialog
const Confirm = {
    show(title, message, onConfirm, type = 'danger') {
        const modal = document.getElementById('confirm-modal');
        if (!modal) return;
        document.getElementById('confirm-title').textContent = title;
        document.getElementById('confirm-message').textContent = message;
        const btn = document.getElementById('confirm-btn');
        btn.className = `btn btn-${type}`;
        btn.onclick = () => {
            Modal.close('confirm-modal');
            if (onConfirm) onConfirm();
        };
        Modal.open('confirm-modal');
    }
};

// API Helper
const API = {
    async request(url, method = 'GET', data = null) {
        const options = {
            method,
            headers: {
                'Content-Type': 'application/json',
                'X-Requested-With': 'XMLHttpRequest'
            }
        };
        if (data) options.body = JSON.stringify(data);
        const res = await fetch(url, options);
        return res.json();
    },
    get(url) { return this.request(url, 'GET'); },
    post(url, data) { return this.request(url, 'POST', data); },
    put(url, data) { return this.request(url, 'PUT', data); },
    delete(url, data) { return this.request(url, 'DELETE', data); }
};

// Close modal on overlay click
document.addEventListener('click', (e) => {
    if (e.target.classList.contains('modal-overlay')) {
        Modal.closeAll();
    }
});

// Close modal on Escape key
document.addEventListener('keydown', (e) => {
    if (e.key === 'Escape') Modal.closeAll();
});

// Initialize on DOM ready
document.addEventListener('DOMContentLoaded', () => {
    ThemeManager.init();
    Sidebar.init();
    Dropdown.init();
    Toast.init();
    LangManager.init();
});

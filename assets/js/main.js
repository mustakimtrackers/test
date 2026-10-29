/**
 * Main JavaScript - Premium Edition
 * Global functionality with enhanced UI/UX, smooth interactions, and modern features
 */

// ==================== Theme Management ====================
class ThemeManager {
    constructor() {
        this.html = document.documentElement;
        this.themeToggle = document.getElementById('themeToggle');
        this.init();
    }

    init() {
        // Load saved theme or default to light
        const savedTheme = localStorage.getItem('theme') || 'light';
        this.setTheme(savedTheme);

        if (this.themeToggle) {
            this.themeToggle.addEventListener('click', () => this.toggleTheme());
        }
    }

    setTheme(theme) {
        this.html.setAttribute('data-theme', theme);
        localStorage.setItem('theme', theme);
        this.updateIcon(theme);

        // Add smooth transition
        this.html.style.transition = 'background-color 0.3s ease, color 0.3s ease';
    }

    toggleTheme() {
        const currentTheme = this.html.getAttribute('data-theme');
        const newTheme = currentTheme === 'light' ? 'dark' : 'light';
        this.setTheme(newTheme);

        // Show toast notification
        showToast('success', `Switched to ${newTheme} mode`);
    }

    updateIcon(theme) {
        if (this.themeToggle) {
            const icon = this.themeToggle.querySelector('i');
            if (icon) {
                icon.className = theme === 'dark' ? 'bi bi-sun' : 'bi bi-moon-stars';
            }
        }
    }
}

// ==================== Sidebar Management ====================
class SidebarManager {
    constructor() {
        this.sidebar = document.querySelector('.sidebar');
        this.sidebarToggle = document.getElementById('sidebarToggle');
        this.sidebarOverlay = document.getElementById('sidebarOverlay');
        this.init();
    }

    init() {
        if (this.sidebarToggle && this.sidebar) {
            this.sidebarToggle.addEventListener('click', () => this.toggle());
        }

        if (this.sidebarOverlay) {
            this.sidebarOverlay.addEventListener('click', () => this.close());
        }

        // Close on escape key
        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape' && this.isOpen()) {
                this.close();
            }
        });
    }

    toggle() {
        this.sidebar.classList.toggle('mobile-open');
        this.sidebarOverlay.classList.toggle('active');
        document.body.style.overflow = this.isOpen() ? 'hidden' : '';
    }

    close() {
        this.sidebar.classList.remove('mobile-open');
        this.sidebarOverlay.classList.remove('active');
        document.body.style.overflow = '';
    }

    isOpen() {
        return this.sidebar.classList.contains('mobile-open');
    }
}

// ==================== Toast Notifications ====================
class ToastManager {
    constructor() {
        this.container = this.getOrCreateContainer();
    }

    getOrCreateContainer() {
        let container = document.querySelector('.toast-container');
        if (!container) {
            container = document.createElement('div');
            container.className = 'toast-container';
            document.body.appendChild(container);
        }
        return container;
    }

    show(type = 'info', message, duration = 5000) {
        const icons = {
            success: 'bi-check-circle-fill',
            error: 'bi-x-circle-fill',
            warning: 'bi-exclamation-triangle-fill',
            info: 'bi-info-circle-fill'
        };

        const toast = document.createElement('div');
        toast.className = `toast toast-${type}`;
        toast.innerHTML = `
            <div class="d-flex align-items-center gap-3">
                <i class="bi ${icons[type]} fs-5"></i>
                <div class="flex-grow-1">${message}</div>
                <button type="button" class="btn-close btn-sm" onclick="this.parentElement.parentElement.remove()"></button>
            </div>
        `;

        this.container.appendChild(toast);

        // Auto remove
        if (duration > 0) {
            setTimeout(() => {
                toast.style.animation = 'slideOutRight 0.3s ease-out forwards';
                setTimeout(() => toast.remove(), 300);
            }, duration);
        }
    }

    success(message, duration) {
        this.show('success', message, duration);
    }

    error(message, duration) {
        this.show('error', message, duration);
    }

    warning(message, duration) {
        this.show('warning', message, duration);
    }

    info(message, duration) {
        this.show('info', message, duration);
    }
}

// Slide out animation
const style = document.createElement('style');
style.textContent = `
    @keyframes slideOutRight {
        to {
            transform: translateX(400px);
            opacity: 0;
        }
    }
`;
document.head.appendChild(style);

// Global toast instance
const toast = new ToastManager();

// Legacy function for compatibility
function showToast(type, message, duration) {
    toast.show(type, message, duration);
}

// ==================== Loading States ====================
class LoadingManager {
    showOverlay(message = 'Loading...') {
        let overlay = document.querySelector('.loading-overlay');
        if (!overlay) {
            overlay = document.createElement('div');
            overlay.className = 'loading-overlay';
            overlay.innerHTML = `
                <div class="text-center">
                    <div class="spinner mb-3"></div>
                    <div class="text-white fw-semibold">${message}</div>
                </div>
            `;
            document.body.appendChild(overlay);
        }
        overlay.style.display = 'flex';
    }

    hideOverlay() {
        const overlay = document.querySelector('.loading-overlay');
        if (overlay) {
            overlay.style.display = 'none';
        }
    }

    showButton(button, text = 'Loading...') {
        if (!button) return;

        button.dataset.originalText = button.innerHTML;
        button.disabled = true;
        button.innerHTML = `
            <span class="spinner-border spinner-border-sm me-2"></span>
            ${text}
        `;
    }

    hideButton(button) {
        if (!button) return;

        button.disabled = false;
        button.innerHTML = button.dataset.originalText || 'Submit';
    }
}

const loading = new LoadingManager();

// ==================== AJAX Helper ====================
async function fetchJSON(url, options = {}) {
    const defaults = {
        headers: {
            'Content-Type': 'application/json',
            'X-Requested-With': 'XMLHttpRequest'
        }
    };

    const config = {
        ...defaults,
        ...options,
        headers: {
            ...defaults.headers,
            ...options.headers
        }
    };

    try {
        const response = await fetch(url, config);

        if (!response.ok) {
            throw new Error(`HTTP error! status: ${response.status}`);
        }

        const data = await response.json();
        return data;
    } catch (error) {
        console.error('Fetch error:', error);
        toast.error('An error occurred. Please try again.');
        throw error;
    }
}

// ==================== Utility Functions ====================

// Format currency
function formatCurrency(amount, symbol = '৳') {
    const formatted = parseFloat(amount)
        .toFixed(2)
        .replace(/\d(?=(\d{3})+\.)/g, '$&,');
    return symbol + formatted;
}

// Format number
function formatNumber(num) {
    return num.toString().replace(/\B(?=(\d{3})+(?!\d))/g, ',');
}

// Debounce function
function debounce(func, wait = 300) {
    let timeout;
    return function executedFunction(...args) {
        clearTimeout(timeout);
        timeout = setTimeout(() => func(...args), wait);
    };
}

// Throttle function
function throttle(func, limit = 300) {
    let inThrottle;
    return function (...args) {
        if (!inThrottle) {
            func.apply(this, args);
            inThrottle = true;
            setTimeout(() => inThrottle = false, limit);
        }
    };
}

// Confirm dialog
function confirmAction(message = 'Are you sure?') {
    return confirm(message);
}

// Copy to clipboard
async function copyToClipboard(text) {
    try {
        await navigator.clipboard.writeText(text);
        toast.success('Copied to clipboard!');
        return true;
    } catch (err) {
        console.error('Failed to copy:', err);
        toast.error('Failed to copy to clipboard');
        return false;
    }
}

// Format date
function formatDate(date, format = 'Y-m-d') {
    const d = new Date(date);
    const year = d.getFullYear();
    const month = String(d.getMonth() + 1).padStart(2, '0');
    const day = String(d.getDate()).padStart(2, '0');

    return format
        .replace('Y', year)
        .replace('m', month)
        .replace('d', day);
}

// Smooth scroll to element
function scrollToElement(element, offset = 0) {
    if (typeof element === 'string') {
        element = document.querySelector(element);
    }

    if (element) {
        const top = element.getBoundingClientRect().top + window.pageYOffset - offset;
        window.scrollTo({
            top: top,
            behavior: 'smooth'
        });
    }
}

// ==================== Form Validation ====================
class FormValidator {
    constructor(form) {
        this.form = form;
        this.errors = {};
    }

    required(field, message = 'This field is required') {
        const value = this.form[field].value.trim();
        if (!value) {
            this.errors[field] = message;
            return false;
        }
        return true;
    }

    email(field, message = 'Please enter a valid email') {
        const value = this.form[field].value.trim();
        const regex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
        if (!regex.test(value)) {
            this.errors[field] = message;
            return false;
        }
        return true;
    }

    min(field, minValue, message) {
        const value = parseFloat(this.form[field].value);
        if (value < minValue) {
            this.errors[field] = message || `Value must be at least ${minValue}`;
            return false;
        }
        return true;
    }

    max(field, maxValue, message) {
        const value = parseFloat(this.form[field].value);
        if (value > maxValue) {
            this.errors[field] = message || `Value must not exceed ${maxValue}`;
            return false;
        }
        return true;
    }

    getErrors() {
        return this.errors;
    }

    hasErrors() {
        return Object.keys(this.errors).length > 0;
    }

    showErrors() {
        for (const [field, message] of Object.entries(this.errors)) {
            toast.error(message);
        }
    }
}

// ==================== PWA Print Helper ====================
function openPrintWindow(url) {
    // Determine if we are running as a PWA
    const isPWA = window.matchMedia('(display-mode: standalone)').matches || window.navigator.standalone;

    if (isPWA) {
        // PWA Mode: Open in same window to keep session, then print
        // To avoid losing history, we can use an iframe or let the print page go back
        // For simplicity and to avoid cross-origin/iframe blocking:
        window.location.href = url;
    } else {
        // Browser Mode: safely open in new tab
        window.open(url, '_blank');
    }
}

// ==================== Initialize on DOM Ready ====================
document.addEventListener('DOMContentLoaded', function () {
    // Initialize managers
    const themeManager = new ThemeManager();
    const sidebarManager = new SidebarManager();

    // Add smooth scroll to all anchor links
    document.querySelectorAll('a[href^="#"]').forEach(anchor => {
        anchor.addEventListener('click', function (e) {
            e.preventDefault();
            const target = document.querySelector(this.getAttribute('href'));
            if (target) {
                scrollToElement(target, 80);
            }
        });
    });

    // Auto-focus first input in modals
    document.querySelectorAll('.modal').forEach(modal => {
        modal.addEventListener('shown.bs.modal', function () {
            const firstInput = modal.querySelector('input, textarea, select');
            if (firstInput) {
                firstInput.focus();
            }
        });
    });

    // Add loading state to forms
    document.querySelectorAll('form[data-ajax]').forEach(form => {
        form.addEventListener('submit', function (e) {
            const submitBtn = form.querySelector('[type="submit"]');
            loading.showButton(submitBtn);
        });
    });

    // Format all currency displays
    document.querySelectorAll('[data-currency]').forEach(el => {
        const amount = parseFloat(el.dataset.currency);
        el.textContent = formatCurrency(amount);
    });

    // Add ripple effect to buttons
    document.querySelectorAll('.btn').forEach(button => {
        button.addEventListener('click', function (e) {
            const ripple = document.createElement('span');
            const rect = this.getBoundingClientRect();
            const size = Math.max(rect.width, rect.height);
            const x = e.clientX - rect.left - size / 2;
            const y = e.clientY - rect.top - size / 2;

            ripple.style.cssText = `
                position: absolute;
                border-radius: 50%;
                background: rgba(255, 255, 255, 0.6);
                width: ${size}px;
                height: ${size}px;
                left: ${x}px;
                top: ${y}px;
                pointer-events: none;
                animation: ripple 0.6s ease-out;
            `;

            this.style.position = 'relative';
            this.style.overflow = 'hidden';
            this.appendChild(ripple);

            setTimeout(() => ripple.remove(), 600);
        });
    });

    // Add table row hover effect enhancement
    document.querySelectorAll('.table tbody tr').forEach(row => {
        row.addEventListener('mouseenter', function () {
            this.style.transform = 'scale(1.005)';
        });
        row.addEventListener('mouseleave', function () {
            this.style.transform = 'scale(1)';
        });
    });

    // FAB Toggle
    const fabToggleBtn = document.getElementById('fabToggleBtn');
    const fabContainer = document.querySelector('.fab-container');
    
    if (fabToggleBtn && fabContainer) {
        fabToggleBtn.addEventListener('click', function(e) {
            e.stopPropagation();
            fabContainer.classList.toggle('active');
        });
        
        // Close FAB when clicking outside
        document.addEventListener('click', function(e) {
            if (!fabContainer.contains(e.target)) {
                fabContainer.classList.remove('active');
            }
        });
    }

    console.log('✨ Premium UI/UX initialized successfully');
});

// Add ripple animation
const rippleStyle = document.createElement('style');
rippleStyle.textContent = `
    @keyframes ripple {
        to {
            transform: scale(4);
            opacity: 0;
        }
    }
`;
document.head.appendChild(rippleStyle);

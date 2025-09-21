/**
 * Manufacturing Management System - Main JavaScript
 */

// Global variables
let currentUser = null;
let sidebarCollapsed = false;

// Initialize application when DOM is loaded
document.addEventListener('DOMContentLoaded', function() {
    initializeApp();
});

// Initialize application
function initializeApp() {
    initializeSidebar();
    initializeUserMenu();
    initializeModals();
    initializeForms();
    initializeTables();
    loadUserData();
    setActiveNavigation();
}

// Set active navigation item based on current page
function setActiveNavigation() {
    const currentPath = window.location.pathname;
    const navLinks = document.querySelectorAll('.nav-link');
    
    navLinks.forEach(link => {
        link.classList.remove('active');
        
        const href = link.getAttribute('href');
        if (href && currentPath.includes(href.replace('../', '/'))) {
            link.classList.add('active');
        }
    });
}

// Sidebar functionality
function initializeSidebar() {
    const sidebarToggle = document.querySelector('.sidebar-toggle');
    const sidebar = document.querySelector('.sidebar');
    const mainContent = document.querySelector('.main-content');

    if (sidebarToggle) {
        sidebarToggle.addEventListener('click', function() {
            toggleSidebar();
        });
    }

    // Set active menu item
    const currentPath = window.location.pathname;
    const menuLinks = document.querySelectorAll('.sidebar-menu a');
    
    menuLinks.forEach(link => {
        if (link.getAttribute('href') && currentPath.includes(link.getAttribute('href'))) {
            link.classList.add('active');
        }
    });
}

function toggleSidebar() {
    const sidebar = document.querySelector('.sidebar');
    const mainContent = document.querySelector('.main-content');
    
    if (window.innerWidth <= 768) {
        sidebar.classList.toggle('show');
    } else {
        sidebar.classList.toggle('collapsed');
        mainContent.classList.toggle('expanded');
    }
    
    sidebarCollapsed = !sidebarCollapsed;
}

// User menu functionality
function initializeUserMenu() {
    const userMenuToggle = document.getElementById('userMenuToggle');
    const userDropdown = document.getElementById('userDropdown');
    const notificationToggle = document.getElementById('notificationToggle');
    const notificationDropdown = document.getElementById('notificationDropdown');

    if (userMenuToggle && userDropdown) {
        userMenuToggle.addEventListener('click', function(e) {
            e.stopPropagation();
            userDropdown.classList.toggle('show');
            // Close notification dropdown if open
            if (notificationDropdown) {
                notificationDropdown.classList.remove('show');
            }
        });
    }

    if (notificationToggle && notificationDropdown) {
        notificationToggle.addEventListener('click', function(e) {
            e.stopPropagation();
            notificationDropdown.classList.toggle('show');
            // Close user dropdown if open
            if (userDropdown) {
                userDropdown.classList.remove('show');
            }
        });
    }

    // Close dropdowns when clicking outside
    document.addEventListener('click', function() {
        if (userDropdown) {
            userDropdown.classList.remove('show');
        }
        if (notificationDropdown) {
            notificationDropdown.classList.remove('show');
        }
    });
}

// Modal functionality
function initializeModals() {
    const modals = document.querySelectorAll('.modal');
    
    modals.forEach(modal => {
        const closeBtn = modal.querySelector('.modal-close');
        
        if (closeBtn) {
            closeBtn.addEventListener('click', function() {
                closeModal(modal.id);
            });
        }

        // Close modal when clicking outside
        modal.addEventListener('click', function(e) {
            if (e.target === modal) {
                closeModal(modal.id);
            }
        });
    });
}

function showModal(modalId) {
    const modal = document.getElementById(modalId);
    if (modal) {
        modal.classList.add('show');
        document.body.style.overflow = 'hidden';
    }
}

function closeModal(modalId) {
    const modal = document.getElementById(modalId);
    if (modal) {
        modal.classList.remove('show');
        document.body.style.overflow = 'auto';
    }
}

// Form functionality
function initializeForms() {
    const forms = document.querySelectorAll('form[data-ajax="true"]');
    
    forms.forEach(form => {
        form.addEventListener('submit', function(e) {
            e.preventDefault();
            submitAjaxForm(form);
        });
    });
}

function submitAjaxForm(form) {
    const formData = new FormData(form);
    const submitBtn = form.querySelector('button[type="submit"]');
    const originalText = submitBtn.textContent;
    
    // Show loading state
    submitBtn.disabled = true;
    submitBtn.textContent = 'Processing...';
    
    fetch(form.action, {
        method: form.method || 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showAlert('success', data.message || 'Operation completed successfully');
            
            // Close modal if form is in modal
            const modal = form.closest('.modal');
            if (modal) {
                closeModal(modal.id);
            }
            
            // Reload page or update content
            if (data.reload) {
                setTimeout(() => location.reload(), 1000);
            }
        } else {
            showAlert('danger', data.message || 'An error occurred');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        showAlert('danger', 'An unexpected error occurred');
    })
    .finally(() => {
        // Reset button state
        submitBtn.disabled = false;
        submitBtn.textContent = originalText;
    });
}

// Table functionality
function initializeTables() {
    const tables = document.querySelectorAll('.table[data-sortable="true"]');
    
    tables.forEach(table => {
        const headers = table.querySelectorAll('th[data-sort]');
        
        headers.forEach(header => {
            header.style.cursor = 'pointer';
            header.addEventListener('click', function() {
                sortTable(table, header.dataset.sort);
            });
        });
    });
}

function sortTable(table, column) {
    const tbody = table.querySelector('tbody');
    const rows = Array.from(tbody.querySelectorAll('tr'));
    const isAscending = table.dataset.sortOrder !== 'asc';
    
    rows.sort((a, b) => {
        const aValue = a.querySelector(`td[data-sort="${column}"]`)?.textContent || '';
        const bValue = b.querySelector(`td[data-sort="${column}"]`)?.textContent || '';
        
        if (isAscending) {
            return aValue.localeCompare(bValue, undefined, { numeric: true });
        } else {
            return bValue.localeCompare(aValue, undefined, { numeric: true });
        }
    });
    
    // Update table
    tbody.innerHTML = '';
    rows.forEach(row => tbody.appendChild(row));
    
    // Update sort order
    table.dataset.sortOrder = isAscending ? 'asc' : 'desc';
    
    // Update header indicators
    const headers = table.querySelectorAll('th[data-sort]');
    headers.forEach(h => h.classList.remove('sort-asc', 'sort-desc'));
    
    const currentHeader = table.querySelector(`th[data-sort="${column}"]`);
    currentHeader.classList.add(isAscending ? 'sort-asc' : 'sort-desc');
}

// Alert functionality
function showAlert(type, message, duration = 5000) {
    const alertContainer = document.getElementById('alert-container') || createAlertContainer();
    
    const alert = document.createElement('div');
    alert.className = `alert alert-${type}`;
    alert.innerHTML = `
        <span>${message}</span>
        <button type="button" class="alert-close" onclick="this.parentElement.remove()">×</button>
    `;
    
    alertContainer.appendChild(alert);
    
    // Auto remove after duration
    if (duration > 0) {
        setTimeout(() => {
            if (alert.parentElement) {
                alert.remove();
            }
        }, duration);
    }
}

function createAlertContainer() {
    const container = document.createElement('div');
    container.id = 'alert-container';
    container.style.cssText = `
        position: fixed;
        top: 20px;
        right: 20px;
        z-index: 9999;
        max-width: 400px;
    `;
    document.body.appendChild(container);
    return container;
}

// User data functionality
function loadUserData() {
    // This would typically load user data from the server
    // For now, we'll use session data if available
    if (typeof userSession !== 'undefined') {
        currentUser = userSession;
        updateUserInterface();
    }
}

function updateUserInterface() {
    if (currentUser) {
        const userAvatar = document.querySelector('.user-avatar');
        if (userAvatar) {
            userAvatar.textContent = currentUser.full_name.charAt(0).toUpperCase();
        }
        
        const userNameElements = document.querySelectorAll('.user-name');
        userNameElements.forEach(element => {
            element.textContent = currentUser.full_name;
        });
    }
}

// Utility functions
function formatCurrency(amount) {
    return new Intl.NumberFormat('en-US', {
        style: 'currency',
        currency: 'USD'
    }).format(amount);
}

function formatDate(date, options = {}) {
    const defaultOptions = {
        year: 'numeric',
        month: 'short',
        day: 'numeric'
    };
    
    return new Intl.DateTimeFormat('en-US', { ...defaultOptions, ...options })
        .format(new Date(date));
}

function formatDateTime(datetime, options = {}) {
    const defaultOptions = {
        year: 'numeric',
        month: 'short',
        day: 'numeric',
        hour: '2-digit',
        minute: '2-digit'
    };
    
    return new Intl.DateTimeFormat('en-US', { ...defaultOptions, ...options })
        .format(new Date(datetime));
}

function debounce(func, wait) {
    let timeout;
    return function executedFunction(...args) {
        const later = () => {
            clearTimeout(timeout);
            func(...args);
        };
        clearTimeout(timeout);
        timeout = setTimeout(later, wait);
    };
}

// API helper functions
function apiRequest(url, options = {}) {
    const defaultOptions = {
        headers: {
            'Content-Type': 'application/json',
            'X-Requested-With': 'XMLHttpRequest'
        }
    };
    
    return fetch(url, { ...defaultOptions, ...options })
        .then(response => {
            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }
            return response.json();
        });
}

function confirmAction(message, callback) {
    if (confirm(message)) {
        callback();
    }
}

// Export functions for global use
window.Manufacturing = {
    showModal,
    closeModal,
    showAlert,
    formatCurrency,
    formatDate,
    formatDateTime,
    apiRequest,
    confirmAction,
    toggleSidebar
};

// Handle responsive behavior
window.addEventListener('resize', function() {
    const sidebar = document.querySelector('.sidebar');
    const mainContent = document.querySelector('.main-content');
    
    if (window.innerWidth > 768) {
        sidebar.classList.remove('show');
        if (sidebarCollapsed) {
            sidebar.classList.add('collapsed');
            mainContent.classList.add('expanded');
        }
    } else {
        sidebar.classList.remove('collapsed');
        mainContent.classList.remove('expanded');
    }
});
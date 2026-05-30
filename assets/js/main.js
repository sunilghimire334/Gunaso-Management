// Main JavaScript functionality
document.addEventListener('DOMContentLoaded', function() {
    initializeMobileNavigation();
    initializeEventListeners();
});

// Initialize mobile navigation
function initializeMobileNavigation() {
    const navItems = document.querySelectorAll('.mobile-bottom-nav .nav-item');
    
    navItems.forEach(item => {
        item.addEventListener('click', function() {
            // Remove active class from all items
            navItems.forEach(nav => nav.classList.remove('active'));
            // Add active class to clicked item
            this.classList.add('active');
        });
    });
}

// Initialize event listeners
function initializeEventListeners() {
    // Window resize handler
    window.addEventListener('resize', function() {
        if (typeof handleResize === 'function') {
            handleResize();
        }
    });

    // Add any other global event listeners here
}

// Utility functions
const utils = {
    // Debounce function for performance
    debounce: function(func, wait) {
        let timeout;
        return function executedFunction(...args) {
            const later = () => {
                clearTimeout(timeout);
                func(...args);
            };
            clearTimeout(timeout);
            timeout = setTimeout(later, wait);
        };
    },

    // Format numbers
    formatNumber: function(num) {
        return new Intl.NumberFormat().format(num);
    },

    // Check if element is in viewport
    isInViewport: function(element) {
        const rect = element.getBoundingClientRect();
        return (
            rect.top >= 0 &&
            rect.left >= 0 &&
            rect.bottom <= (window.innerHeight || document.documentElement.clientHeight) &&
            rect.right <= (window.innerWidth || document.documentElement.clientWidth)
        );
    }
};

// Export for global access
window.utils = utils;
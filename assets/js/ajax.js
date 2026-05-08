(function() {
    'use strict';

    let AJAX_BASE = '../../ajax/';
    let refreshInterval = null;
    let currentStatus = 'all';
    let onUpdateCallback = null;
    let requestContext = 'public';

    function detectAjaxBase() {
        const path = window.location.pathname;
        if (path.includes('/public/') || path.includes('\\public\\')) {
            if (path.includes('/public/kitchen/') || path.includes('\\kitchen\\') || 
                path.includes('/public/customer/') || path.includes('\\customer\\')) {
                AJAX_BASE = '../../ajax/';
            } else if (path.includes('/public/index') || path.includes('\\index')) {
                AJAX_BASE = '../ajax/';
            } else if (path.includes('/public/') && !path.includes('/admin/')) {
                AJAX_BASE = '../ajax/';
            } else {
                AJAX_BASE = '../../ajax/';
            }
        } else {
            AJAX_BASE = './ajax/';
        }
    }
    detectAjaxBase();

    function detectRequestContext() {
        const path = window.location.pathname.toLowerCase();

        if (path.includes('/public/customer/')) {
            requestContext = 'customer';
        } else if (path.includes('/public/kitchen/')) {
            requestContext = 'staff';
        } else if (path.includes('/public/admin/')) {
            requestContext = 'admin';
        } else {
            requestContext = 'public';
        }
    }
    detectRequestContext();

    let isPublicMode = false;
    function getOrders(status = 'all') {
        currentStatus = status;
        const publicParam = isPublicMode ? '&public=1' : '';
        const contextParam = !isPublicMode && requestContext !== 'public'
            ? `&context=${encodeURIComponent(requestContext)}`
            : '';
        return api(`get_orders.php?status=${encodeURIComponent(status)}${publicParam}${contextParam}`);
    }

    function api(endpoint, options = {}) {
        const url = AJAX_BASE + endpoint;
        const isFormData = options.body instanceof FormData;
        
        const config = {
            method: options.method || 'GET',
        };

        if (options.body) {
            if (isFormData) {
                config.body = options.body;
            } else {
                config.headers = {
                    'Content-Type': 'application/json',
                };
                config.body = JSON.stringify(options.body);
            }
        }

        return fetch(url, config)
            .then(response => {
                if (!response.ok) {
                    return response.text().then(text => {
                        throw new Error(`HTTP error ${response.status}: ${text}`);
                    });
                }
                return response.text();
            })
            .then(text => {
                try {
                    return JSON.parse(text);
                } catch (e) {
                    throw new Error('Invalid JSON response: ' + text.substring(0, 100));
                }
            })
            .catch(error => {
                console.error('API Error:', error);
                return { success: false, error: error.message };
            });
    }

    function setPublicMode(enabled) {
        isPublicMode = enabled;
        if (enabled) {
            requestContext = 'public';
        } else {
            detectRequestContext();
        }
    }

    function getStats() {
        return api('get_stats.php');
    }

    function updateOrderStatus(orderId, newStatus) {
        return api('update_status.php', {
            method: 'POST',
            body: { order_id: orderId, new_status: newStatus }
        });
    }

    function deleteOrder(orderId) {
        return api('delete_order.php', {
            method: 'POST',
            body: { id: orderId }
        });
    }

    function deleteItem(itemId) {
        return api('delete_item.php', {
            method: 'POST',
            body: { id: itemId }
        });
    }

    function startAutoRefresh(callback, interval = 5000, options = {}) {
        stopAutoRefresh();
        onUpdateCallback = callback;
        const includeStats =
            options.includeStats !== undefined ? options.includeStats : callback.length >= 2;

        refreshInterval = setInterval(async () => {
            try {
                const orders = await getOrders(currentStatus);
                const stats = includeStats ? await getStats() : null;
                if (onUpdateCallback) {
                    onUpdateCallback(orders, stats);
                }
            } catch (e) {
                console.error('Auto-refresh error:', e);
            }
        }, interval);
    }

    function stopAutoRefresh() {
        if (refreshInterval) {
            clearInterval(refreshInterval);
            refreshInterval = null;
        }
    }

    function formatCurrency(amount) {
        return '₱' + parseFloat(amount).toFixed(2).replace(/\d(?=(\d{3})+\.)/g, '$&,');
    }

    function formatTime(dateString) {
        const date = new Date(dateString);
        return date.toLocaleTimeString('en-US', { hour: '2-digit', minute: '2-digit', hour12: true });
    }

    function formatDateTime(dateString) {
        const date = new Date(dateString);
        return date.toLocaleDateString('en-US', { month: 'short', day: 'numeric' }) + ', ' + formatTime(dateString);
    }

    function getStatusClass(status) {
        const classes = {
            'pending': 'status-pending',
            'preparing': 'status-processing',
            'processing': 'status-processing',
            'ready': 'status-processing',
            'completed': 'status-completed',
            'cancelled': 'status-cancelled'
        };
        return classes[status] || 'status-pending';
    }

    function getNextStatus(currentStatus) {
        const transitions = {
            'pending': { next: 'preparing', label: 'Start' },
            'preparing': { next: 'ready', label: 'Ready' },
            'ready': { next: 'completed', label: 'Done' }
        };
        return transitions[currentStatus];
    }

    function formatText(text) {
        if (text === null || text === undefined) return '';
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }

    function showToast(message, type = 'success') {
        const toast = document.createElement('div');
        toast.className = `toast toast-${type}`;
        toast.innerHTML = `<i class="fas fa-${type === 'success' ? 'check-circle' : type === 'error' ? 'exclamation-circle' : 'info-circle'}"></i> ${message}`;
        
        let container = document.querySelector('.toast-container');
        if (!container) {
            container = document.createElement('div');
            container.className = 'toast-container';
            document.body.appendChild(container);
        }
        
        container.appendChild(toast);
        
        requestAnimationFrame(() => {
            toast.classList.add('show');
        });
        
        setTimeout(() => {
            toast.classList.remove('show');
            setTimeout(() => toast.remove(), 300);
        }, 3000);
    }

    window.AJAX = {
        api,
        getOrders,
        getStats,
        updateOrderStatus,
        deleteOrder,
        deleteItem,
        startAutoRefresh,
        stopAutoRefresh,
        setPublicMode,
        formatCurrency,
        formatTime,
        formatDateTime,
        formatText,
        getStatusClass,
        getNextStatus,
        showToast
    };
})();

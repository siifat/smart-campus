/**
 * Admin Panel Management Scripts
 */

// Utility Functions
const Utils = {
    showNotification: function(message, type = 'success') {
        const notification = document.createElement('div');
        notification.className = `notification notification-${type}`;
        notification.innerHTML = `
            <i class="fas fa-${type === 'success' ? 'check-circle' : 'exclamation-circle'}"></i>
            <span>${message}</span>
        `;
        
        document.body.appendChild(notification);
        
        setTimeout(() => {
            notification.classList.add('show');
        }, 100);
        
        setTimeout(() => {
            notification.classList.remove('show');
            setTimeout(() => notification.remove(), 300);
        }, 3000);
    },
    
    confirmAction: function(message, callback) {
        if (confirm(message)) {
            callback();
        }
    },
    
    formatNumber: function(num) {
        return new Intl.NumberFormat().format(num);
    },
    
    formatDate: function(dateString) {
        const date = new Date(dateString);
        return date.toLocaleDateString('en-US', { 
            year: 'numeric', 
            month: 'short', 
            day: 'numeric' 
        });
    },
    
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
    }
};

// Table Management
const TableManager = {
    init: function() {
        this.setupSearch();
        this.setupSorting();
        this.setupFilters();
    },
    
    setupSearch: function() {
        // Server-side search is handled by manage.php
        // This function is kept for compatibility but does nothing
        // Search functionality is controlled by performSearch() in manage.php inline script
    },
    
    toggleEmptyState: function(table, show) {
        const tbody = table.querySelector('tbody');
        let emptyRow = tbody.querySelector('.empty-state-row');
        
        if (show && !emptyRow) {
            const colCount = table.querySelectorAll('thead th').length;
            emptyRow = document.createElement('tr');
            emptyRow.className = 'empty-state-row';
            emptyRow.innerHTML = `
                <td colspan="${colCount}" style="text-align: center; padding: 40px; color: #999;">
                    <i class="fas fa-search" style="font-size: 48px; margin-bottom: 15px; opacity: 0.5;"></i>
                    <p style="font-size: 16px; margin: 0;">No matching records found</p>
                </td>
            `;
            tbody.appendChild(emptyRow);
        } else if (!show && emptyRow) {
            emptyRow.remove();
        }
    },
    
    setupSorting: function() {
        const headers = document.querySelectorAll('#dataTable th');
        headers.forEach((header, index) => {
            if (!header.classList.contains('no-sort')) {
                header.style.cursor = 'pointer';
                header.addEventListener('click', () => this.sortTable(index));
            }
        });
    },
    
    sortTable: function(columnIndex) {
        const table = document.getElementById('dataTable');
        const tbody = table.querySelector('tbody');
        const rows = Array.from(tbody.querySelectorAll('tr'));
        
        const direction = table.dataset.sortDir === 'asc' ? 'desc' : 'asc';
        table.dataset.sortDir = direction;
        
        rows.sort((a, b) => {
            const aVal = a.cells[columnIndex].textContent.trim();
            const bVal = b.cells[columnIndex].textContent.trim();
            
            const aNum = parseFloat(aVal.replace(/[^0-9.-]/g, ''));
            const bNum = parseFloat(bVal.replace(/[^0-9.-]/g, ''));
            
            if (!isNaN(aNum) && !isNaN(bNum)) {
                return direction === 'asc' ? aNum - bNum : bNum - aNum;
            }
            
            return direction === 'asc' 
                ? aVal.localeCompare(bVal)
                : bVal.localeCompare(aVal);
        });
        
        rows.forEach(row => tbody.appendChild(row));
    },
    
    setupFilters: function() {
        // Setup any filter dropdowns
        const filters = document.querySelectorAll('.filter-select');
        filters.forEach(filter => {
            filter.addEventListener('change', function() {
                const filterValue = this.value.toLowerCase();
                const rows = document.querySelectorAll('#dataTable tbody tr');
                
                rows.forEach(row => {
                    if (filterValue === 'all' || filterValue === '') {
                        row.style.display = '';
                    } else {
                        const text = row.textContent.toLowerCase();
                        row.style.display = text.includes(filterValue) ? '' : 'none';
                    }
                });
            });
        });
    }
};

// Modal Management
const ModalManager = {
    open: function(modalId) {
        const modal = document.getElementById(modalId);
        if (modal) {
            modal.style.display = 'flex';
            document.body.style.overflow = 'hidden';
        }
    },
    
    close: function(modalId) {
        const modal = document.getElementById(modalId);
        if (modal) {
            modal.style.display = 'none';
            document.body.style.overflow = '';
        }
    },
    
    closeOnOutsideClick: function() {
        document.querySelectorAll('.modal').forEach(modal => {
            modal.addEventListener('click', function(e) {
                if (e.target === this) {
                    this.style.display = 'none';
                    document.body.style.overflow = '';
                }
            });
        });
    }
};

// Form Validation
const FormValidator = {
    validate: function(formId) {
        const form = document.getElementById(formId);
        if (!form) return false;
        
        let isValid = true;
        const requiredFields = form.querySelectorAll('[required]');
        
        requiredFields.forEach(field => {
            if (!field.value.trim()) {
                isValid = false;
                field.classList.add('error');
                this.showFieldError(field, 'This field is required');
            } else {
                field.classList.remove('error');
                this.hideFieldError(field);
            }
        });
        
        return isValid;
    },
    
    showFieldError: function(field, message) {
        let errorDiv = field.nextElementSibling;
        if (!errorDiv || !errorDiv.classList.contains('field-error')) {
            errorDiv = document.createElement('div');
            errorDiv.className = 'field-error';
            field.parentNode.insertBefore(errorDiv, field.nextSibling);
        }
        errorDiv.textContent = message;
    },
    
    hideFieldError: function(field) {
        const errorDiv = field.nextElementSibling;
        if (errorDiv && errorDiv.classList.contains('field-error')) {
            errorDiv.remove();
        }
    }
};

// Export Functionality
function exportData() {
    const table = document.getElementById('dataTable');
    if (!table) return;
    
    let csv = [];
    const rows = table.querySelectorAll('tr');
    
    rows.forEach(row => {
        const cols = row.querySelectorAll('td, th');
        const csvRow = [];
        
        cols.forEach((col, index) => {
            // Skip action column
            if (index === cols.length - 1 && col.classList.contains('action-buttons')) {
                return;
            }
            csvRow.push('"' + col.textContent.trim().replace(/"/g, '""') + '"');
        });
        
        if (csvRow.length > 0) {
            csv.push(csvRow.join(','));
        }
    });
    
    const csvContent = csv.join('\n');
    const blob = new Blob([csvContent], { type: 'text/csv' });
    const url = window.URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url;
    a.download = 'export_' + new Date().toISOString().split('T')[0] + '.csv';
    document.body.appendChild(a);
    a.click();
    document.body.removeChild(a);
    window.URL.revokeObjectURL(url);
    
    Utils.showNotification('Data exported successfully!', 'success');
}

// Bulk Actions
const BulkActions = {
    selectedIds: new Set(),
    
    toggleSelectAll: function(checkbox) {
        const checkboxes = document.querySelectorAll('.row-checkbox');
        checkboxes.forEach(cb => {
            cb.checked = checkbox.checked;
            this.toggleSelection(cb.value, checkbox.checked);
        });
        this.updateBulkActions();
    },
    
    toggleSelection: function(id, isSelected) {
        if (isSelected) {
            this.selectedIds.add(id);
        } else {
            this.selectedIds.delete(id);
        }
        this.updateBulkActions();
    },
    
    updateBulkActions: function() {
        const bulkActionsBar = document.getElementById('bulkActionsBar');
        const selectedCount = document.getElementById('selectedCount');
        
        if (bulkActionsBar && selectedCount) {
            if (this.selectedIds.size > 0) {
                bulkActionsBar.style.display = 'flex';
                selectedCount.textContent = this.selectedIds.size;
            } else {
                bulkActionsBar.style.display = 'none';
            }
        }
    },
    
    deleteSelected: function() {
        if (this.selectedIds.size === 0) return;
        
        const confirmed = confirm(`Are you sure you want to delete ${this.selectedIds.size} selected items?`);
        if (confirmed) {
            // Perform bulk delete
            console.log('Deleting:', Array.from(this.selectedIds));
            Utils.showNotification(`Deleted ${this.selectedIds.size} items`, 'success');
            this.selectedIds.clear();
            this.updateBulkActions();
        }
    }
};

// Auto-refresh functionality
let autoRefreshInterval = null;

function toggleAutoRefresh(enabled, interval = 30000) {
    if (enabled) {
        autoRefreshInterval = setInterval(() => {
            location.reload();
        }, interval);
        Utils.showNotification('Auto-refresh enabled', 'info');
    } else {
        if (autoRefreshInterval) {
            clearInterval(autoRefreshInterval);
            autoRefreshInterval = null;
        }
        Utils.showNotification('Auto-refresh disabled', 'info');
    }
}

// Initialize on page load
document.addEventListener('DOMContentLoaded', function() {
    TableManager.init();
    ModalManager.closeOnOutsideClick();
    
    // Add notification container
    const notificationContainer = document.createElement('div');
    notificationContainer.id = 'notificationContainer';
    notificationContainer.style.cssText = `
        position: fixed;
        top: 20px;
        right: 20px;
        z-index: 10001;
    `;
    document.body.appendChild(notificationContainer);
});

// Add notification styles
const notificationStyles = `
<style>
.notification {
    background: white;
    padding: 15px 20px;
    border-radius: 10px;
    box-shadow: 0 5px 20px rgba(0,0,0,0.2);
    display: flex;
    align-items: center;
    gap: 12px;
    margin-bottom: 10px;
    transform: translateX(400px);
    opacity: 0;
    transition: all 0.3s ease;
}

.notification.show {
    transform: translateX(0);
    opacity: 1;
}

.notification-success {
    border-left: 4px solid #2ed573;
}

.notification-success i {
    color: #2ed573;
}

.notification-error {
    border-left: 4px solid #ff4757;
}

.notification-error i {
    color: #ff4757;
}

.field-error {
    color: #ff4757;
    font-size: 0.85em;
    margin-top: 5px;
}

input.error,
select.error,
textarea.error {
    border-color: #ff4757 !important;
}
</style>
`;

document.head.insertAdjacentHTML('beforeend', notificationStyles);

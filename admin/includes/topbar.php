<?php
// Get notification counts
$pending_notes_count = $conn->query("SELECT COUNT(*) as count FROM notes WHERE status = 'pending'")->fetch_assoc()['count'];
$pending_solutions_count = $conn->query("SELECT COUNT(*) as count FROM question_solutions WHERE status = 'pending'")->fetch_assoc()['count'];
$total_notifications = $pending_notes_count + $pending_solutions_count;
?>
<div class="topbar">
    <div class="topbar-left">
        <button class="topbar-icon" id="sidebarToggle" onclick="toggleSidebar()">
            <i class="fas fa-bars"></i>
        </button>
        
        <div class="topbar-search">
            <i class="fas fa-search"></i>
            <input type="text" placeholder="Search students, courses, or records..." id="globalSearch">
            <button type="button" onclick="performGlobalSearchNow()" class="btn btn-primary" style="position: absolute; right: 8px; top: 50%; transform: translateY(-50%); padding: 6px 16px; border-radius: 6px; font-size: 0.9em;">
                Search
            </button>
        </div>
    </div>
    
    <div class="topbar-right">
        <!-- Theme Toggle -->
        <div class="topbar-icon" title="Toggle Theme" onclick="toggleTheme()" id="themeToggleBtn">
            <i id="themeIcon" class="fas fa-moon"></i>
        </div>
        
        <div class="topbar-icon" title="Notifications" onclick="toggleNotifications()">
            <i class="fas fa-bell"></i>
            <?php if ($total_notifications > 0): ?>
                <span class="notification-badge"><?php echo $total_notifications; ?></span>
            <?php endif; ?>
        </div>
        
        <div class="topbar-icon" title="Messages">
            <i class="fas fa-envelope"></i>
        </div>
        
        <div class="topbar-icon" title="Quick Settings" onclick="location.href='settings.php'">
            <i class="fas fa-cog"></i>
        </div>
        
        <div class="topbar-user" onclick="toggleUserMenu()">
            <div class="user-avatar">A</div>
            <div class="user-info">
                <div class="user-name">Admin</div>
                <div class="user-role">System Administrator</div>
            </div>
            <i class="fas fa-chevron-down"></i>
        </div>
    </div>
</div>

<!-- Notification Dropdown -->
<div id="notificationDropdown" class="dropdown-menu" style="display: none;">
    <div class="dropdown-header">
        <h4>Notifications</h4>
        <span class="badge badge-primary"><?php echo $total_notifications; ?> New</span>
    </div>
    <div class="dropdown-body">
        <?php if ($pending_notes_count > 0): ?>
            <a href="manage.php?table=notes&filter=pending" class="notification-item">
                <i class="fas fa-file-alt text-info"></i>
                <div>
                    <strong><?php echo $pending_notes_count; ?> Pending Notes</strong>
                    <p>Review and approve student notes</p>
                </div>
            </a>
        <?php endif; ?>
        
        <?php if ($pending_solutions_count > 0): ?>
            <a href="manage.php?table=question_solutions&filter=pending" class="notification-item">
                <i class="fas fa-question-circle text-warning"></i>
                <div>
                    <strong><?php echo $pending_solutions_count; ?> Pending Solutions</strong>
                    <p>Review and approve question solutions</p>
                </div>
            </a>
        <?php endif; ?>
        
        <?php if ($total_notifications == 0): ?>
            <div class="notification-item">
                <p class="text-muted">No new notifications</p>
            </div>
        <?php endif; ?>
    </div>
    <div class="dropdown-footer">
        <a href="notifications.php" style="text-decoration: none;">View All Notifications</a>
    </div>
</div>

<!-- User Menu Dropdown -->
<div id="userMenuDropdown" class="dropdown-menu" style="display: none; right: 20px; width: 250px;">
    <div class="dropdown-body" style="padding: 0;">
        <a href="profile.php" class="notification-item" style="border-radius: 12px 12px 0 0;">
            <i class="fas fa-user"></i>
            <div>
                <strong>My Profile</strong>
                <p>View and edit your profile</p>
            </div>
        </a>
        <a href="settings.php" class="notification-item">
            <i class="fas fa-cog"></i>
            <div>
                <strong>Settings</strong>
                <p>System configuration</p>
            </div>
        </a>
        <a href="notifications.php" class="notification-item">
            <i class="fas fa-bell"></i>
            <div>
                <strong>Notifications</strong>
                <p>View all notifications</p>
            </div>
        </a>
        <hr style="margin: 5px 0; border: none; border-top: 1px solid #f0f0f0;">
        <a href="logout.php" class="notification-item" style="color: #ff4757; border-radius: 0 0 12px 12px;">
            <i class="fas fa-sign-out-alt"></i>
            <div>
                <strong>Logout</strong>
                <p>Sign out from admin panel</p>
            </div>
        </a>
    </div>
</div>

<!-- Overlay for mobile sidebar -->
<div id="sidebarOverlay" style="display: none; position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0,0,0,0.5); z-index: 999;" onclick="toggleSidebar()"></div>

<style>
.dropdown-menu {
    position: fixed;
    top: calc(var(--topbar-height) + 10px);
    right: 30px;
    width: 350px;
    background: white;
    border-radius: 12px;
    box-shadow: 0 10px 40px rgba(0,0,0,0.2);
    z-index: 1000;
    animation: slideDown 0.3s ease;
}

@keyframes slideDown {
    from {
        opacity: 0;
        transform: translateY(-10px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

.dropdown-header {
    padding: 20px;
    border-bottom: 2px solid #f1f2f6;
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.dropdown-header h4 {
    margin: 0;
    font-size: 1.1em;
}

.dropdown-body {
    max-height: 400px;
    overflow-y: auto;
}

.notification-item {
    display: flex;
    align-items: start;
    gap: 15px;
    padding: 15px 20px;
    border-bottom: 1px solid #f1f2f6;
    text-decoration: none;
    color: inherit;
    transition: background 0.2s;
}

.notification-item:hover {
    background: #f8f9fa;
}

.notification-item i {
    font-size: 1.5em;
    margin-top: 3px;
}

.notification-item strong {
    display: block;
    margin-bottom: 3px;
}

.notification-item p {
    margin: 0;
    font-size: 0.85em;
    color: #666;
}

.dropdown-footer {
    padding: 15px;
    text-align: center;
    border-top: 2px solid #f1f2f6;
}

.dropdown-footer a {
    color: var(--primary);
    text-decoration: none;
    font-weight: 600;
}

.badge-primary {
    background: var(--primary);
    color: white;
    padding: 4px 10px;
    border-radius: 12px;
    font-size: 0.8em;
}

/* Enhanced Search Result Styles */
.search-result-item.selected {
    background: #eff6ff !important;
    border-left: 3px solid #3b82f6 !important;
    transform: translateX(4px) !important;
}

#globalSearchResults::-webkit-scrollbar {
    width: 8px;
}

#globalSearchResults::-webkit-scrollbar-track {
    background: #f1f1f1;
    border-radius: 10px;
}

#globalSearchResults::-webkit-scrollbar-thumb {
    background: #888;
    border-radius: 10px;
}

#globalSearchResults::-webkit-scrollbar-thumb:hover {
    background: #555;
}

mark {
    background: #fff59d;
    padding: 1px 3px;
    border-radius: 3px;
    font-weight: 600;
}
</style>

<script>
function toggleSidebar() {
    const sidebar = document.querySelector('.sidebar');
    const overlay = document.getElementById('sidebarOverlay');
    
    if (sidebar && overlay) {
        sidebar.classList.toggle('active');
        overlay.style.display = sidebar.classList.contains('active') ? 'block' : 'none';
    }
}

function toggleNotifications() {
    const dropdown = document.getElementById('notificationDropdown');
    const userMenu = document.getElementById('userMenuDropdown');
    
    // Hide user menu if open
    if (userMenu) userMenu.style.display = 'none';
    
    // Toggle notifications
    dropdown.style.display = dropdown.style.display === 'none' ? 'block' : 'none';
}

function toggleUserMenu() {
    const dropdown = document.getElementById('userMenuDropdown');
    const notifications = document.getElementById('notificationDropdown');
    
    // Hide notifications dropdown
    if (notifications) notifications.style.display = 'none';
    
    // Toggle user menu
    dropdown.style.display = dropdown.style.display === 'none' ? 'block' : 'none';
}

// Enhanced Global Search with Fuzzy Finding & Auto-suggestions
let searchTimeout = null;
let searchResults = null;
let selectedIndex = -1;
let currentResults = [];

const globalSearchInput = document.getElementById('globalSearch');
if (globalSearchInput) {
    // Search on Enter key
    globalSearchInput.addEventListener('keydown', function(e) {
        if (e.key === 'Enter') {
            e.preventDefault();
            if (selectedIndex >= 0 && currentResults[selectedIndex]) {
                // Navigate to selected result
                window.location.href = currentResults[selectedIndex].url;
            } else {
                performGlobalSearchNow();
            }
        } else if (e.key === 'ArrowDown') {
            e.preventDefault();
            navigateResults('down');
        } else if (e.key === 'ArrowUp') {
            e.preventDefault();
            navigateResults('up');
        } else if (e.key === 'Escape') {
            hideSearchResults();
            globalSearchInput.blur();
        }
    });
    
    // Auto-search while typing (debounced) - FASTER for better UX
    globalSearchInput.addEventListener('input', function(e) {
        const searchTerm = e.target.value.trim();
        selectedIndex = -1; // Reset selection
        
        // Clear previous timeout
        clearTimeout(searchTimeout);
        
        // Hide results if search is too short
        if (searchTerm.length < 2) {
            hideSearchResults();
            return;
        }
        
        // Show immediate loading indicator
        showSearchLoading();
        
        // Debounce search - wait 300ms after user stops typing (reduced from 500ms)
        searchTimeout = setTimeout(() => {
            performGlobalSearch(searchTerm);
        }, 300);
    });
    
    // Show placeholder suggestions on focus
    globalSearchInput.addEventListener('focus', function() {
        if (this.value.trim().length >= 2) {
            performGlobalSearch(this.value.trim());
        }
    });
}

function navigateResults(direction) {
    if (!currentResults || currentResults.length === 0) return;
    
    if (direction === 'down') {
        selectedIndex = (selectedIndex + 1) % currentResults.length;
    } else if (direction === 'up') {
        selectedIndex = selectedIndex <= 0 ? currentResults.length - 1 : selectedIndex - 1;
    }
    
    highlightSelectedResult();
}

function highlightSelectedResult() {
    const resultLinks = searchResults.querySelectorAll('.search-result-item');
    resultLinks.forEach((link, index) => {
        if (index === selectedIndex) {
            link.classList.add('selected');
            link.scrollIntoView({ block: 'nearest', behavior: 'smooth' });
        } else {
            link.classList.remove('selected');
        }
    });
}

function performGlobalSearchNow() {
    const query = document.getElementById('globalSearch').value.trim();
    if (query.length < 2) {
        alert('Please enter at least 2 characters to search');
        return;
    }
    clearTimeout(searchTimeout);
    performGlobalSearch(query);
}

function performGlobalSearch(query) {
    if (!query || query.length < 2) {
        hideSearchResults();
        return;
    }
    
    // Show loading indicator
    showSearchLoading();
    
    const startTime = performance.now();
    
    fetch(`api/search.php?q=${encodeURIComponent(query)}`)
        .then(response => {
            if (!response.ok) {
                throw new Error('HTTP error ' + response.status);
            }
            return response.json();
        })
        .then(data => {
            const searchTime = (performance.now() - startTime).toFixed(0);
            
            if (data.error) {
                console.error('API error:', data.error);
                displaySearchError(data.error);
            } else {
                displaySearchResults(data.results || [], data.suggestions || [], query, searchTime);
            }
        })
        .catch(error => {
            console.error('Search error:', error);
            displaySearchError(error.message);
        });
}

function showSearchLoading() {
    if (!searchResults) {
        searchResults = document.createElement('div');
        searchResults.id = 'globalSearchResults';
        searchResults.style.cssText = `
            position: absolute;
            top: 100%;
            left: 0;
            right: 0;
            background: white;
            border-radius: 8px;
            box-shadow: 0 5px 20px rgba(0,0,0,0.15);
            max-height: 400px;
            overflow-y: auto;
            z-index: 1001;
            margin-top: 5px;
        `;
        const searchContainer = document.querySelector('.topbar-search');
        if (searchContainer) {
            searchContainer.style.position = 'relative';
            searchContainer.appendChild(searchResults);
        }
    }
    
    searchResults.innerHTML = `
        <div style="padding: 20px; text-align: center; color: #999;">
            <i class="fas fa-spinner fa-spin" style="font-size: 24px; margin-bottom: 10px;"></i>
            <p>Searching...</p>
        </div>
    `;
    searchResults.style.display = 'block';
}

function displaySearchError(message) {
    if (searchResults) {
        searchResults.innerHTML = `
            <div style="padding: 20px; text-align: center; color: #ff4757;">
                <i class="fas fa-exclamation-triangle" style="font-size: 24px; margin-bottom: 10px;"></i>
                <p>${message || 'Search failed. Please try again.'}</p>
                <small style="color: #999; display: block; margin-top: 10px;">Check browser console for details (F12)</small>
            </div>
        `;
    }
}

function highlightMatch(text, query) {
    if (!query) return text;
    
    const regex = new RegExp(`(${query.split(' ').filter(w => w.length > 1).join('|')})`, 'gi');
    return text.replace(regex, '<mark style="background: #fff59d; padding: 1px 3px; border-radius: 3px; font-weight: 600;">$1</mark>');
}

function getRelevanceBadge(score) {
    if (score >= 90) return '<span style="background: #10b981; color: white; padding: 2px 8px; border-radius: 12px; font-size: 0.7em; font-weight: 600;">EXACT</span>';
    if (score >= 70) return '<span style="background: #3b82f6; color: white; padding: 2px 8px; border-radius: 12px; font-size: 0.7em; font-weight: 600;">HIGH</span>';
    if (score >= 50) return '<span style="background: #f59e0b; color: white; padding: 2px 8px; border-radius: 12px; font-size: 0.7em; font-weight: 600;">MATCH</span>';
    return '';
}

function displaySearchResults(results, suggestions, query, searchTime) {
    // Create or get results container
    if (!searchResults) {
        searchResults = document.createElement('div');
        searchResults.id = 'globalSearchResults';
        searchResults.style.cssText = `
            position: absolute;
            top: 100%;
            left: 0;
            right: 0;
            background: white;
            border-radius: 12px;
            box-shadow: 0 8px 32px rgba(0,0,0,0.12);
            max-height: 500px;
            overflow-y: auto;
            z-index: 1001;
            margin-top: 8px;
            border: 1px solid #e5e7eb;
        `;
        document.querySelector('.topbar-search').style.position = 'relative';
        document.querySelector('.topbar-search').appendChild(searchResults);
    }
    
    currentResults = results; // Store for keyboard navigation
    selectedIndex = -1; // Reset selection
    
    if (results.length === 0) {
        searchResults.innerHTML = `
            <div style="padding: 30px; text-align: center; color: #999;">
                <i class="fas fa-search" style="font-size: 32px; margin-bottom: 15px; opacity: 0.5;"></i>
                <p style="font-size: 1.1em; margin: 10px 0 5px 0; font-weight: 600;">No results found</p>
                <p style="font-size: 0.9em; color: #bbb;">Try different keywords or check spelling</p>
            </div>
        `;
    } else {
        let html = '';
        
        // Search header with stats
        html += `
            <div style="padding: 12px 15px; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; border-radius: 12px 12px 0 0; display: flex; justify-content: space-between; align-items: center;">
                <div style="display: flex; align-items: center; gap: 8px;">
                    <i class="fas fa-search" style="font-size: 14px;"></i>
                    <span style="font-weight: 600; font-size: 0.9em;">Search Results</span>
                </div>
                <div style="font-size: 0.85em; opacity: 0.9;">
                    <i class="fas fa-bolt" style="font-size: 10px;"></i> ${results.length} found in ${searchTime}ms
                </div>
            </div>
        `;
        
        // Suggestions (if any)
        if (suggestions && suggestions.length > 0) {
            html += `
                <div style="padding: 10px 15px; background: #f8f9fa; border-bottom: 1px solid #e5e7eb;">
                    <div style="font-size: 0.75em; color: #666; font-weight: 600; text-transform: uppercase; margin-bottom: 8px;">
                        <i class="fas fa-lightbulb" style="color: #f59e0b;"></i> Suggestions
                    </div>
                    <div style="display: flex; gap: 8px; flex-wrap: wrap;">
                        ${suggestions.map(s => `
                            <span style="background: white; padding: 5px 12px; border-radius: 16px; font-size: 0.85em; border: 1px solid #e5e7eb; cursor: pointer;" 
                                  onclick="alert('Feature coming soon!')">
                                ${s.text} ${s.count ? '(' + s.count + ')' : ''}
                            </span>
                        `).join('')}
                    </div>
                </div>
            `;
        }
        
        // Results
        html += results.map((result, index) => `
            <a href="${result.url}" 
               class="search-result-item"
               data-index="${index}"
               style="display: flex; align-items: center; gap: 12px; padding: 12px 15px; text-decoration: none; color: inherit; border-bottom: 1px solid #f0f0f0; transition: all 0.2s; position: relative;">
                <div style="width: 36px; height: 36px; display: flex; align-items: center; justify-content: center; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); border-radius: 8px; color: white; font-size: 16px; flex-shrink: 0;">
                    <i class="${result.icon}"></i>
                </div>
                <div style="flex: 1; min-width: 0;">
                    <div style="font-weight: 600; margin-bottom: 3px; font-size: 0.95em;">
                        ${highlightMatch(result.name, query)}
                    </div>
                    <div style="font-size: 0.8em; color: #666; display: flex; align-items: center; gap: 8px;">
                        ${highlightMatch(result.detail || result.type, query)}
                        ${result.score ? getRelevanceBadge(result.score) : ''}
                    </div>
                </div>
                <div style="display: flex; align-items: center; gap: 8px; flex-shrink: 0;">
                    <span style="font-size: 0.75em; color: #999; text-transform: uppercase; background: #f3f4f6; padding: 3px 8px; border-radius: 6px;">${result.type}</span>
                    <i class="fas fa-chevron-right" style="font-size: 12px; color: #ccc;"></i>
                </div>
            </a>
        `).join('');
        
        // Footer with keyboard shortcuts
        html += `
            <div style="padding: 10px 15px; background: #f8f9fa; border-top: 2px solid #e5e7eb; font-size: 0.75em; color: #666; display: flex; justify-content: space-between; align-items: center; border-radius: 0 0 12px 12px;">
                <div>
                    <i class="fas fa-keyboard"></i> 
                    <kbd style="background: white; padding: 2px 6px; border: 1px solid #ddd; border-radius: 3px; font-size: 0.9em;">↑</kbd>
                    <kbd style="background: white; padding: 2px 6px; border: 1px solid #ddd; border-radius: 3px; font-size: 0.9em;">↓</kbd> Navigate
                    <kbd style="background: white; padding: 2px 6px; border: 1px solid #ddd; border-radius: 3px; font-size: 0.9em;">Enter</kbd> Select
                </div>
                <div>Powered by Fuzzy Search 🚀</div>
            </div>
        `;
        
        searchResults.innerHTML = html;
        
        // Add hover and keyboard effects
        const resultItems = searchResults.querySelectorAll('.search-result-item');
        resultItems.forEach((link, index) => {
            link.addEventListener('mouseenter', function() {
                this.style.background = '#f8f9fa';
                this.style.transform = 'translateX(4px)';
                selectedIndex = index;
                highlightSelectedResult();
            });
            link.addEventListener('mouseleave', function() {
                if (!this.classList.contains('selected')) {
                    this.style.background = '';
                    this.style.transform = '';
                }
            });
        });
    }
    
    searchResults.style.display = 'block';
}

function hideSearchResults() {
    if (searchResults) {
        searchResults.style.display = 'none';
    }
}

// Hide search results when clicking outside
document.addEventListener('click', function(e) {
    if (!e.target.closest('.topbar-search')) {
        hideSearchResults();
    }
});

// Close dropdowns when clicking outside
document.addEventListener('click', function(e) {
    const notificationDropdown = document.getElementById('notificationDropdown');
    const userMenuDropdown = document.getElementById('userMenuDropdown');
    
    // Close notification dropdown if clicking outside
    if (!e.target.closest('.topbar-icon[title="Notifications"]') && 
        !e.target.closest('#notificationDropdown')) {
        if (notificationDropdown) notificationDropdown.style.display = 'none';
    }
    
    // Close user menu if clicking outside
    if (!e.target.closest('.topbar-user') && 
        !e.target.closest('#userMenuDropdown')) {
        if (userMenuDropdown) userMenuDropdown.style.display = 'none';
    }
    
    // Close search results if clicking outside
    if (!e.target.closest('.topbar-search')) {
        hideSearchResults();
    }
});

// Prevent clicks inside dropdowns from closing them
document.addEventListener('click', function(e) {
    if (e.target.closest('.dropdown-menu')) {
        e.stopPropagation();
    }
}, true);

// Theme Toggle Functionality
function toggleTheme() {
    const html = document.documentElement;
    const currentTheme = html.getAttribute('data-theme');
    const newTheme = currentTheme === 'dark' ? 'light' : 'dark';
    const themeIcon = document.getElementById('themeIcon');
    
    // Set new theme
    html.setAttribute('data-theme', newTheme);
    localStorage.setItem('theme', newTheme);
    
    // Update icon with smooth transition
    themeIcon.style.transform = 'rotate(360deg)';
    setTimeout(() => {
        themeIcon.className = newTheme === 'dark' ? 'fas fa-sun' : 'fas fa-moon';
        themeIcon.style.transform = 'rotate(0deg)';
    }, 150);
}

// Initialize theme from localStorage on page load
(function initTheme() {
    const savedTheme = localStorage.getItem('theme') || 'light';
    const html = document.documentElement;
    const themeIcon = document.getElementById('themeIcon');
    
    html.setAttribute('data-theme', savedTheme);
    
    if (themeIcon) {
        themeIcon.className = savedTheme === 'dark' ? 'fas fa-sun' : 'fas fa-moon';
    }
})();
</script>

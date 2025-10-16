// Merged admin JS - generated automatically
// Source files: kanriDashboardScript.js, adminDashboardScript.js, kanriMore.js, adminPagination.js, adminManagementScript.js


// Top-level declarations (from source files)
// Enhanced Pagination Module for Admin Dashboard

// Pagination state tracking
let paginationState = {
    users: { currentPage: 1, totalPages: 1 },
    lessons: { currentPage: 1, totalPages: 1 },
    quizzes: { currentPage: 1, totalPages: 1 }
};

// Enhanced pagination functions with anchoring and state management









// Update pagination controls with proper previous/next button states



// Enhanced search function that resets pagination



// Initialize pagination on page load
document.addEventListener('DOMContentLoaded', function() {
    // Initialize pagination for all tables
    setTimeout(() => {
        if (document.getElementById('userTableBody')) {
            loadUserPage(1);
        }
        if (document.getElementById('lessonTableBody')) {
            loadLessonPage(1);
        }
        if (document.getElementById('quizTableBody')) {
            loadQuizPage(1);
        }
    }, 500);
});


function isMobileDevice() {
    return /Android|webOS|iPhone|iPad|iPod|BlackBerry|IEMobile|Opera Mini/i.test(navigator.userAgent);
}


function redirectToIndex() {
    window.location.href = 'index.php';
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


function showNotification(message, type = 'info') {
    const notification = document.createElement('div');
    notification.className = `notification notification-${type}`;
    notification.innerHTML = `
        <div class="notification-content">
            <span class="notification-message">${message}</span>
            <button class="notification-close" onclick="this.parentElement.parentElement.remove()">&times;</button>
        </div>
    `;
    
    document.body.appendChild(notification);
    
    // Auto remove after 5 seconds
    setTimeout(() => {
        if (notification.parentElement) {
            notification.remove();
        }
    }, 5000);
}


function showModal(title, content, saveCallback) {
    // Create modal if it doesn't exist
    let modal = document.getElementById('dynamicModal');
    if (!modal) {
        modal = document.createElement('div');
        modal.id = 'dynamicModal';
        modal.className = 'modal';
        modal.innerHTML = `
            <div class="modal-content">
                <div class="modal-header">
                    <h3 id="modalTitle"></h3>
                    <span class="close" onclick="closeModal()">&times;</span>
                </div>
                <div class="modal-body" id="modalBody">
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" onclick="closeModal()">Cancel</button>
                    <button type="button" class="btn btn-primary" id="modalSaveBtn">Save</button>
                </div>
            </div>
        `;
        document.body.appendChild(modal);
    }
    
    // Set modal content
    document.getElementById('modalTitle').textContent = title;
    document.getElementById('modalBody').innerHTML = content;
    
    // Set save callback
    const saveBtn = document.getElementById('modalSaveBtn');
    if (saveCallback) {
        saveBtn.onclick = saveCallback;
        saveBtn.style.display = 'block';
    } else {
        saveBtn.style.display = 'none';
    }
    
    // Show modal
    modal.style.display = 'block';
    
    // Close modal when clicking outside
    modal.onclick = function(event) {
        if (event.target === modal) {
            closeModal();
        }
    };
}


function closeModal() {
    const modal = document.getElementById('dynamicModal');
    if (modal) {
        modal.style.display = 'none';
    }
}


function initializeDropdowns() {
    const accountBtn = document.getElementById('accountDropdownBtn');
    const accountDropdown = document.getElementById('accountDropdown');
    
    if (accountBtn && accountDropdown) {
        accountBtn.addEventListener('click', function(e) {
            e.stopPropagation();
            accountDropdown.classList.toggle('show');
        });
        
        // Close dropdown when clicking outside
        document.addEventListener('click', function() {
            accountDropdown.classList.remove('show');
        });
    }
}


function setupSearchFilters() {
    // User search
    document.getElementById('userSearch')?.addEventListener('input', debounce(function() {
        filterTable('userTable', this.value);
    }, 300));

    // Lesson search
    document.getElementById('lessonSearch')?.addEventListener('input', debounce(function() {
        filterTable('lessonTable', this.value);
    }, 300));

    // Quiz search
    document.getElementById('quizSearch')?.addEventListener('input', debounce(function() {
        filterTable('quizTable', this.value);
    }, 300));

    // Inventory search
    document.getElementById('inventorySearch')?.addEventListener('input', debounce(function() {
        filterTable('inventoryTable', this.value);
    }, 300));

    // Audit search
    document.getElementById('auditSearch')?.addEventListener('input', debounce(function() {
        filterTable('auditTable', this.value);
    }, 300));
}


function setupEventListeners() {
    // Analytics period filter
    document.getElementById('analyticsPeriod').addEventListener('change', function() {
        updateAnalyticsData(this.value);
    });
    
    // Search functionality
    document.querySelectorAll('.search-input').forEach(input => {
        input.addEventListener('input', function() {
            filterTable(this);
        });
    });
}


function initializeCharts() {
    // Destroy existing charts if they exist
    if (window.userGrowthChart) {
        window.userGrowthChart.destroy();
    }
    if (window.topLessonsChart) {
        window.topLessonsChart.destroy();
    }
    
    // User Growth Chart
    const userGrowthCtx = document.getElementById('userGrowthChart').getContext('2d');
    window.userGrowthChart = new Chart(userGrowthCtx, {
        type: 'line',
        data: {
            labels: [], // Will be populated from PHP data
            datasets: [{
                label: 'New Users',
                data: [],
                borderColor: '#811212',
                backgroundColor: 'rgba(129, 18, 18, 0.1)',
                tension: 0.4,
                fill: true
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    display: false
                }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    grid: {
                        drawBorder: false
                    }
                },
                x: {
                    grid: {
                        display: false
                    }
                }
            }
        }
    });

    // Top Lessons Chart
    const topLessonsCtx = document.getElementById('topLessonsChart').getContext('2d');
    window.topLessonsChart = new Chart(topLessonsCtx, {
        type: 'bar',
        data: {
            labels: [], // Will be populated from PHP data
            datasets: [{
                label: 'Enrollments',
                data: [],
                backgroundColor: '#4299e1',
                borderRadius: 6
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            indexAxis: 'y',
            plugins: {
                legend: {
                    display: false
                }
            },
            scales: {
                x: {
                    beginAtZero: true,
                    grid: {
                        drawBorder: false
                    }
                },
                y: {
                    grid: {
                        display: false
                    }
                }
            }
        }
    });

    // Load chart data via AJAX
    loadChartData(window.userGrowthChart, window.topLessonsChart);
}


function loadChartData(userGrowthChart, topLessonsChart) {
    fetch('admin_ajax.php?action=get_chart_data')
        .then(response => response.json())
        .then(data => {
            // Update User Growth Chart
            userGrowthChart.data.labels = data.user_growth.labels;
            userGrowthChart.data.datasets[0].data = data.user_growth.data;
            userGrowthChart.update();
            
            // Update Top Lessons Chart
            topLessonsChart.data.labels = data.top_lessons.labels;
            topLessonsChart.data.datasets[0].data = data.top_lessons.data;
            topLessonsChart.update();
        })
        .catch(error => console.error('Error loading chart data:', error));
}


function initializeManagementSections() {
    // Load initial data for each management section
    loadUserManagementData();
    loadLessonManagementData();
    loadQuizManagementData();
}


function loadUserManagementData() {
    fetch('admin_ajax.php?action=get_users')
        .then(response => response.json())
        .then(data => {
            updateUserTable(data);
        })
        .catch(error => console.error('Error loading user data:', error));
}


function updateUserTable(users) {
    const tbody = document.querySelector('#userTable tbody');
    tbody.innerHTML = users.map(user => `
        <tr>
            <td>${user.user_id}</td>
            <td>${user.username}</td>
            <td>${user.email_address}</td>
            <td>${user.role}</td>
            <td>${user.subscription_type}</td>
            <td>${user.gakucoins}</td>
            <td>
                <button class="btn-action btn-edit" onclick="editUser(${user.user_id})">Edit</button>
                <button class="btn-action btn-delete" onclick="deleteUser(${user.user_id})">Delete</button>
            </td>
        </tr>
    `).join('');
}


function loadLessonManagementData() {
    fetch('admin_ajax.php?action=get_lessons')
        .then(response => response.json())
        .then(data => {
            updateLessonTable(data);
        })
        .catch(error => console.error('Error loading lesson data:', error));
}


function updateLessonTable(lessons) {
    const tbody = document.querySelector('#lessonTable tbody');
    tbody.innerHTML = lessons.map(lesson => `
        <tr>
            <td>${lesson.lesson_id}</td>
            <td>${lesson.title}</td>
            <td>${lesson.topic_name}</td>
            <td>${lesson.difficulty_level}</td>
            <td>${lesson.is_private ? 'Private' : 'Public'}</td>
            <td>
                <button class="btn-action btn-edit" onclick="editLesson(${lesson.lesson_id})">Edit</button>
                <button class="btn-action btn-delete" onclick="deleteLesson(${lesson.lesson_id})">Delete</button>
            </td>
        </tr>
    `).join('');
}


function editUser(userId) {
    fetch(`admin_ajax.php?action=get_user&user_id=${userId}`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                document.getElementById('userModalTitle').textContent = 'Edit User';
                document.getElementById('user_id').value = data.user.user_id;
                document.getElementById('first_name').value = data.user.first_name;
                document.getElementById('last_name').value = data.user.last_name;
                document.getElementById('username').value = data.user.username;
                document.getElementById('email_address').value = data.user.email_address;
                document.getElementById('role').value = data.user.role;
                document.getElementById('subscription_type').value = data.user.subscription_type;
                document.getElementById('gakucoins').value = data.user.gakucoins;
                document.getElementById('is_verified').checked = data.user.is_verified;
                openModal('userModal');
            } else {
                showNotification('Error loading user data', 'error');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            showNotification('Error loading user data', 'error');
        });
}


function saveUser() {
    const form = document.getElementById('userForm');
    const formData = new FormData(form);
    const userId = document.getElementById('user_id').value;
    const url = userId ? 'admin_ajax.php?action=update_user' : 'admin_ajax.php?action=create_user';

    fetch(url, {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            closeModal();
            showNotification('User saved successfully', 'success');
            setTimeout(() => location.reload(), 1000);
        } else {
            showNotification('Error saving user: ' + data.message, 'error');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        showNotification('Error saving user', 'error');
    });
}


function deleteUser(userId) {
    if (confirm('Are you sure you want to delete this user? This action cannot be undone.')) {
        fetch('admin_ajax.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: `action=delete_user&user_id=${userId}`
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                showNotification('User deleted successfully', 'success');
                setTimeout(() => location.reload(), 1000);
            } else {
                showNotification('Error deleting user: ' + data.message, 'error');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            showNotification('Error deleting user', 'error');
        });
    }
}


function editLesson(lessonId) {
    fetch(`admin_ajax.php?action=get_lesson&lesson_id=${lessonId}`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                document.getElementById('lessonModalTitle').textContent = 'Edit Lesson';
                document.getElementById('lesson_id').value = data.lesson.lesson_id;
                document.getElementById('lesson_title').value = data.lesson.title;
                document.getElementById('short_desc').value = data.lesson.short_desc;
                document.getElementById('long_desc').value = data.lesson.long_desc;
                document.getElementById('topic_id').value = data.lesson.topic_id;
                document.getElementById('difficulty_level').value = data.lesson.difficulty_level;
                document.getElementById('duration').value = data.lesson.duration;
                document.getElementById('is_private').checked = data.lesson.is_private;
                document.getElementById('author_id').value = data.lesson.author_id || '';
                openModal('lessonModal');
            } else {
                showNotification('Error loading lesson data', 'error');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            showNotification('Error loading lesson data', 'error');
        });
}


function saveLesson() {
    const form = document.getElementById('lessonForm');
    const formData = new FormData(form);
    const lessonId = document.getElementById('lesson_id').value;
    const url = lessonId ? 'admin_ajax.php?action=update_lesson' : 'admin_ajax.php?action=create_lesson';

    fetch(url, {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            closeModal();
            showNotification('Lesson saved successfully', 'success');
            setTimeout(() => location.reload(), 1000);
        } else {
            showNotification('Error saving lesson: ' + data.message, 'error');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        showNotification('Error saving lesson', 'error');
    });
}


function deleteLesson(lessonId) {
    if (confirm('Are you sure you want to delete this lesson? All associated files and quizzes will also be deleted.')) {
        fetch('admin_ajax.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: `action=delete_lesson&lesson_id=${lessonId}`
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                showNotification('Lesson deleted successfully', 'success');
                setTimeout(() => location.reload(), 1000);
            } else {
                showNotification('Error deleting lesson: ' + data.message, 'error');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            showNotification('Error deleting lesson', 'error');
        });
    }
}


function editQuiz(quizId) {
    fetch(`admin_ajax.php?action=get_quiz&quiz_id=${quizId}`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                document.getElementById('quizModalTitle').textContent = 'Edit Quiz';
                document.getElementById('quiz_id').value = data.quiz.quiz_id;
                document.getElementById('quiz_title').value = data.quiz.title || '';
                document.getElementById('lesson_id').value = data.quiz.lesson_id || '';
                document.getElementById('is_ai_generated').checked = data.quiz.is_ai_generated;
                document.getElementById('author_id').value = data.quiz.author_id || '';
                openModal('quizModal');
            } else {
                showNotification('Error loading quiz data', 'error');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            showNotification('Error loading quiz data', 'error');
        });
}


function saveQuiz() {
    const form = document.getElementById('quizForm');
    const formData = new FormData(form);
    const quizId = document.getElementById('quiz_id').value;
    const url = quizId ? 'admin_ajax.php?action=update_quiz' : 'admin_ajax.php?action=create_quiz';

    fetch(url, {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            closeModal();
            showNotification('Quiz saved successfully', 'success');
            setTimeout(() => location.reload(), 1000);
        } else {
            showNotification('Error saving quiz: ' + data.message, 'error');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        showNotification('Error saving quiz', 'error');
    });
}


function deleteQuiz(quizId) {
    if (confirm('Are you sure you want to delete this quiz? All associated questions and attempts will also be deleted.')) {
        fetch('admin_ajax.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: `action=delete_quiz&quiz_id=${quizId}`
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                showNotification('Quiz deleted successfully', 'success');
                setTimeout(() => location.reload(), 1000);
            } else {
                showNotification('Error deleting quiz: ' + data.message, 'error');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            showNotification('Error deleting quiz', 'error');
        });
    }
}


function openCreatorTab(tabName) {
    // Hide all tab contents
    document.querySelectorAll('#creator-management .tab-content').forEach(tab => {
        tab.classList.remove('active');
    });
    
    // Remove active class from all buttons
    document.querySelectorAll('#creator-management .tab-button').forEach(button => {
        button.classList.remove('active');
    });
    
    // Show the selected tab and activate the button
    document.getElementById(tabName + '-tab').classList.add('active');
    event.currentTarget.classList.add('active');
}


function openShopTab(tabName) {
    document.querySelectorAll('#shop-management .tab-content').forEach(tab => {
        tab.classList.remove('active');
    });
    
    document.querySelectorAll('#shop-management .tab-button').forEach(button => {
        button.classList.remove('active');
    });
    
    document.getElementById(tabName + '-tab').classList.add('active');
    event.currentTarget.classList.add('active');
}


function openSystemTab(tabName) {
    document.querySelectorAll('#system-management .tab-content').forEach(tab => {
        tab.classList.remove('active');
    });
    
    document.querySelectorAll('#system-management .tab-button').forEach(button => {
        button.classList.remove('active');
    });
    
    document.getElementById(tabName + '-tab').classList.add('active');
    event.currentTarget.classList.add('active');
}


function filterTable(tableId, searchText) {
    const table = document.getElementById(tableId);
    if (!table) return;
    
    const rows = table.querySelectorAll('tbody tr');
    const searchLower = searchText.toLowerCase();

    rows.forEach(row => {
        const text = row.textContent.toLowerCase();
        row.style.display = text.includes(searchLower) ? '' : 'none';
    });
}


function filterTableByColumn(tableId, columnIndex, filterValue) {
    const table = document.getElementById(tableId);
    if (!table) return;
    
    const rows = table.querySelectorAll('tbody tr');

    rows.forEach(row => {
        const cell = row.cells[columnIndex];
        if (cell) {
            const cellText = cell.textContent.toLowerCase();
            const shouldShow = !filterValue || cellText.includes(filterValue.toLowerCase());
            row.style.display = shouldShow ? '' : 'none';
        }
    });
}


function exportTableData(tableId, filename) {
    const table = document.getElementById(tableId);
    const rows = table.querySelectorAll('tr');
    let csv = [];
    
    for (let i = 0; i < rows.length; i++) {
        let row = [], cols = rows[i].querySelectorAll('td, th');
        
        for (let j = 0; j < cols.length; j++) {
            // Remove action buttons from export
            if (!cols[j].querySelector('.action-buttons')) {
                row.push('"' + cols[j].innerText.replace(/"/g, '""') + '"');
            }
        }
        
        csv.push(row.join(','));
    }
    
    // Download CSV file
    const csvFile = new Blob([csv.join('\n')], {type: 'text/csv'});
    const downloadLink = document.createElement('a');
    downloadLink.download = filename;
    downloadLink.href = window.URL.createObjectURL(csvFile);
    downloadLink.style.display = 'none';
    document.body.appendChild(downloadLink);
    downloadLink.click();
    document.body.removeChild(downloadLink);
}



function updatePaginationControls(type, currentPage, totalPages) {
    const paginationContainer = document.getElementById(`${type}Pagination`);
    if (!paginationContainer) return;
    
    let html = '<div class="pagination-controls">';
    
    // Previous button
    if (currentPage > 1) {
        const funcName = type === 'users' ? 'loadUserPage' : type === 'lessons' ? 'loadLessonPage' : 'loadQuizPage';
        html += `<button class="btn btn-secondary" onclick="${funcName}(${currentPage - 1})">Previous</button>`;
    } else {
        html += '<button class="btn btn-secondary" disabled>Previous</button>';
    }
    
    // Page info
    html += `<span class="page-info">Page ${currentPage} of ${totalPages}</span>`;
    
    // Next button
    if (currentPage < totalPages) {
        const funcName = type === 'users' ? 'loadUserPage' : type === 'lessons' ? 'loadLessonPage' : 'loadQuizPage';
        html += `<button class="btn btn-primary" onclick="${funcName}(${currentPage + 1})">Next</button>`;
    } else {
        html += '<button class="btn btn-primary" disabled>Next</button>';
    }
    
    html += '</div>';
    paginationContainer.innerHTML = html;
}


function performSearch(type, query) {
    // Reset to page 1 for new searches and update pagination
    if (type === 'user') {
        loadUserPage(1);
    } else if (type === 'lesson') {
        loadLessonPage(1);
    } else if (type === 'quiz') {
        loadQuizPage(1);
    }
}


function grantItemToUser(itemId) {
    const userId = prompt('Enter the User ID to grant this item to:');
    if (userId) {
        fetch('admin_ajax.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: `action=grant_item&item_id=${itemId}&user_id=${userId}`
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                showNotification('Item granted successfully', 'success');
            } else {
                showNotification('Error granting item: ' + data.message, 'error');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            showNotification('Error granting item', 'error');
        });
    }
}


function adjustCoins(userId) {
    const amount = prompt('Enter the amount of Gakucoins to add/subtract (use negative for subtraction):');
    if (amount !== null && !isNaN(amount)) {
        showNotification('Coin adjustment functionality coming soon', 'info');
    }
}


function deleteFeedback(feedbackId) {
    if (confirm('Are you sure you want to delete this feedback?')) {
        fetch('admin_ajax.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: `action=delete_feedback&feedback_id=${feedbackId}`
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                showNotification('Feedback deleted successfully', 'success');
                setTimeout(() => location.reload(), 1000);
            } else {
                showNotification('Error deleting feedback: ' + data.message, 'error');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            showNotification('Error deleting feedback', 'error');
        });
    }
}


function approveVerification(indexId) {
    if (confirm('Are you sure you want to approve this user verification?')) {
        fetch('admin_ajax.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: `action=approve_verification&index_id=${indexId}`
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                showNotification('Verification approved successfully', 'success');
                setTimeout(() => location.reload(), 1000);
            } else {
                showNotification('Error approving verification: ' + data.message, 'error');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            showNotification('Error approving verification', 'error');
        });
    }
}


function rejectVerification(indexId) {
    if (confirm('Are you sure you want to reject this user verification?')) {
        fetch('admin_ajax.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: `action=reject_verification&index_id=${indexId}`
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                showNotification('Verification rejected successfully', 'success');
                setTimeout(() => location.reload(), 1000);
            } else {
                showNotification('Error rejecting verification: ' + data.message, 'error');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            showNotification('Error rejecting verification', 'error');
        });
    }
}


function viewUserDetails(userId) {
    // Show user details in modal instead of opening new window
    fetch(`admin_ajax.php?action=get_user&user_id=${userId}`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                const user = data.user;
                const content = `
                    <div class="user-details">
                        <h4>User Details</h4>
                        <p><strong>ID:</strong> ${user.user_id}</p>
                        <p><strong>Name:</strong> ${user.first_name} ${user.last_name}</p>
                        <p><strong>Username:</strong> ${user.username}</p>
                        <p><strong>Email:</strong> ${user.email_address}</p>
                        <p><strong>Role:</strong> ${user.role}</p>
                        <p><strong>Subscription:</strong> ${user.subscription_type}</p>
                        <p><strong>Gakucoins:</strong> ${user.gakucoins}</p>
                        <p><strong>Verified:</strong> ${user.is_verified ? 'Yes' : 'No'}</p>
                        <p><strong>Created:</strong> ${new Date(user.created_at).toLocaleDateString()}</p>
                    </div>
                `;
                showModal('User Details', content, null);
                document.getElementById('modalSaveBtn').style.display = 'none';
            } else {
                showNotification('Error loading user details', 'error');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            showNotification('Error loading user details', 'error');
        });
}


function viewLessonDetails(lessonId) {
    window.open(`lesson_view.php?lesson_id=${lessonId}`, '_blank');
}


function approveApplication(applicationId) {
    if (confirm('Are you sure you want to approve this creator application?')) {
        fetch('admin_ajax.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: `action=approve_application&application_id=${applicationId}`
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                showNotification('Application approved successfully', 'success');
                setTimeout(() => location.reload(), 1000);
            } else {
                showNotification('Error approving application: ' + data.message, 'error');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            showNotification('Error approving application', 'error');
        });
    }
}


function closeLessonModal() {
    closeModal('lessonModal');
}


function closeQuizModal() {
    closeModal('quizModal');
}


function closeShopItemModal() {
    closeModal('shopItemModal');
}


function closeTopicModal() {
    closeModal('topicModal');
}


function closeUserModal() {
    closeModal('userModal');
}


function deleteShopItem(itemId) {
    if (confirm('Are you sure you want to delete this shop item? This will remove it from the shop but existing user purchases will remain.')) {
        fetch('admin_ajax.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: `action=delete_shop_item&item_id=${itemId}`
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                showNotification('Shop item deleted successfully', 'success');
                setTimeout(() => location.reload(), 1000);
            } else {
                showNotification('Error deleting shop item: ' + data.message, 'error');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            showNotification('Error deleting shop item', 'error');
        });
    }
}


function deleteTopic(topicId) {
    if (confirm('Are you sure you want to delete this topic? This will affect all lessons associated with this topic.')) {
        fetch('admin_ajax.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: `action=delete_topic&topic_id=${topicId}`
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                showNotification('Topic deleted successfully', 'success');
                setTimeout(() => location.reload(), 1000);
            } else {
                showNotification('Error deleting topic: ' + data.message, 'error');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            showNotification('Error deleting topic', 'error');
        });
    }
}


function editPet(petId) {
    showNotification('Pet editing functionality coming soon', 'info');
}


function editShopItem(itemId) {
    fetch(`admin_ajax.php?action=get_shop_item&item_id=${itemId}`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                document.getElementById('shopItemModalTitle').textContent = 'Edit Shop Item';
                document.getElementById('item_id').value = data.item.item_id;
                document.getElementById('item_type').value = data.item.item_type;
                document.getElementById('item_name').value = data.item.item_name;
                document.getElementById('description').value = data.item.description;
                document.getElementById('price').value = data.item.price;
                document.getElementById('energy_restore').value = data.item.energy_restore || '';
                document.getElementById('image_url').value = data.item.image_url;
                openModal('shopItemModal');
            } else {
                showNotification('Error loading item data', 'error');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            showNotification('Error loading item data', 'error');
        });
}


function editTopic(topicId) {
    fetch(`admin_ajax.php?action=get_topic&topic_id=${topicId}`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                document.getElementById('topicModalTitle').textContent = 'Edit Topic';
                document.getElementById('topic_id').value = data.topic.topic_id;
                document.getElementById('topic_name').value = data.topic.topic_name;
                document.getElementById('topic_icon').value = data.topic.topic_icon || '';
                openModal('topicModal');
            } else {
                showNotification('Error loading topic data', 'error');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            showNotification('Error loading topic data', 'error');
        });
}


function getLessonModalContent(lesson = null) {
    return `
        <form id="lessonForm">
            <input type="hidden" id="lesson_id" name="lesson_id" value="${lesson ? lesson.lesson_id : ''}">
            <div class="form-group">
                <label for="title">Lesson Title</label>
                <input type="text" id="title" name="title" class="form-control" value="${lesson ? lesson.title : ''}" required>
            </div>
            <div class="form-group">
                <label for="short_desc">Short Description</label>
                <textarea id="short_desc" name="short_desc" class="form-control" rows="2" required>${lesson ? lesson.short_desc : ''}</textarea>
            </div>
            <div class="form-group">
                <label for="long_desc">Long Description</label>
                <textarea id="long_desc" name="long_desc" class="form-control" rows="4" required>${lesson ? lesson.long_desc : ''}</textarea>
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label for="duration">Duration (HH:MM:SS)</label>
                    <input type="time" id="duration" name="duration" class="form-control" value="${lesson ? lesson.duration : ''}" required>
                </div>
                <div class="form-group">
                    <label for="difficulty_level">Difficulty Level</label>
                    <select id="difficulty_level" name="difficulty_level" class="form-control" required>
                        <option value="Beginner" ${lesson && lesson.difficulty_level === 'Beginner' ? 'selected' : ''}>Beginner</option>
                        <option value="Intermediate" ${lesson && lesson.difficulty_level === 'Intermediate' ? 'selected' : ''}>Intermediate</option>
                        <option value="Professional" ${lesson && lesson.difficulty_level === 'Professional' ? 'selected' : ''}>Professional</option>
                    </select>
                </div>
            </div>
            <div class="form-group">
                <label for="topic_id">Topic</label>
                <select id="topic_id" name="topic_id" class="form-control" required>
                    <!-- Topics will be loaded dynamically -->
                </select>
            </div>
            <div class="form-group">
                <label class="checkbox-label">
                    <input type="checkbox" id="is_private" name="is_private" value="1" ${lesson && lesson.is_private ? 'checked' : ''}>
                    <span class="checkmark"></span>
                    Private Lesson
                </label>
            </div>
        </form>
    `;
}


function getQuizModalContent(quiz = null) {
    return `
        <form id="quizForm">
            <input type="hidden" id="quiz_id" name="quiz_id" value="${quiz ? quiz.quiz_id : ''}">
            <div class="form-group">
                <label for="quiz_title">Quiz Title</label>
                <input type="text" id="quiz_title" name="title" class="form-control" value="${quiz ? quiz.title || '' : ''}" required>
            </div>
            <div class="form-group">
                <label for="lesson_id">Associated Lesson (Optional)</label>
                <select id="lesson_id" name="lesson_id" class="form-control">
                    <option value="">Standalone Quiz</option>
                    <!-- Lessons will be loaded dynamically -->
                </select>
            </div>
            <div class="form-group">
                <label class="checkbox-label">
                    <input type="checkbox" id="is_ai_generated" name="is_ai_generated" value="1" ${quiz && quiz.is_ai_generated ? 'checked' : ''}>
                    <span class="checkmark"></span>
                    AI Generated Content
                </label>
            </div>
        </form>
    `;
}


function getShopItemModalContent(item = null) {
    return `
        <form id="shopItemForm">
            <input type="hidden" id="item_id" name="item_id" value="${item ? item.item_id : ''}">
            <div class="form-group">
                <label for="item_type">Item Type</label>
                <select id="item_type" name="item_type" class="form-control" required>
                    <option value="food" ${item && item.item_type === 'food' ? 'selected' : ''}>Food</option>
                    <option value="accessory" ${item && item.item_type === 'accessory' ? 'selected' : ''}>Accessory</option>
                    <option value="wallpaper" ${item && item.item_type === 'wallpaper' ? 'selected' : ''}>Wallpaper</option>
                </select>
            </div>
            <div class="form-group">
                <label for="item_name">Item Name</label>
                <input type="text" id="item_name" name="item_name" class="form-control" value="${item ? item.item_name : ''}" required>
            </div>
            <div class="form-group">
                <label for="description">Description</label>
                <textarea id="description" name="description" class="form-control" rows="3" required>${item ? item.description : ''}</textarea>
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label for="price">Price (coins)</label>
                    <input type="number" id="price" name="price" class="form-control" value="${item ? item.price : ''}" required min="0">
                </div>
                <div class="form-group">
                    <label for="energy_restore">Energy Restore (food only)</label>
                    <input type="number" id="energy_restore" name="energy_restore" class="form-control" value="${item ? item.energy_restore || '' : ''}" min="0">
                </div>
            </div>
            <div class="form-group">
                <label for="image_url">Image (Emoji or URL)</label>
                <input type="text" id="image_url" name="image_url" class="form-control" value="${item ? item.image_url : ''}" required>
                <small>Use emoji (🍲) or image URL</small>
            </div>
        </form>
    `;
}


function getTopicModalContent(topic = null) {
    return `
        <form id="topicForm">
            <input type="hidden" id="topic_id" name="topic_id" value="${topic ? topic.topic_id : ''}">
            <div class="form-group">
                <label for="topic_name">Topic Name</label>
                <input type="text" id="topic_name" name="topic_name" class="form-control" value="${topic ? topic.topic_name : ''}" required>
            </div>
            <div class="form-group">
                <label for="topic_icon">Topic Icon (HTML)</label>
                <textarea id="topic_icon" name="topic_icon" class="form-control" rows="3" placeholder='<i class="bi bi-icon-name"></i>'>${topic ? topic.topic_icon || '' : ''}</textarea>
                <small>Use Bootstrap Icons HTML code</small>
            </div>
        </form>
    `;
}


function getUserModalContent(user = null) {
    return `
        <form id="userForm">
            <input type="hidden" id="user_id" name="user_id" value="${user ? user.user_id : ''}">
            <div class="form-row">
                <div class="form-group">
                    <label for="first_name">First Name</label>
                    <input type="text" id="first_name" name="first_name" class="form-control" value="${user ? user.first_name : ''}" required>
                </div>
                <div class="form-group">
                    <label for="last_name">Last Name</label>
                    <input type="text" id="last_name" name="last_name" class="form-control" value="${user ? user.last_name : ''}" required>
                </div>
            </div>
            <div class="form-group">
                <label for="username">Username</label>
                <input type="text" id="username" name="username" class="form-control" value="${user ? user.username : ''}" required>
            </div>
            <div class="form-group">
                <label for="email_address">Email Address</label>
                <input type="email" id="email_address" name="email_address" class="form-control" value="${user ? user.email_address : ''}" required>
            </div>
            <div class="form-group">
                <label for="password">Password</label>
                <input type="password" id="password" name="password" class="form-control">
                <small>Leave blank to keep current password</small>
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label for="role">Role</label>
                    <select id="role" name="role" class="form-control" required>
                        <option value="Gakusei" ${user && user.role === 'Gakusei' ? 'selected' : ''}>Gakusei</option>
                        <option value="Gakusensei" ${user && user.role === 'Gakusensei' ? 'selected' : ''}>Gakusensei</option>
                        <option value="Kanri" ${user && user.role === 'Kanri' ? 'selected' : ''}>Kanri</option>
                    </select>
                </div>
                <div class="form-group">
                    <label for="subscription_type">Subscription Type</label>
                    <select id="subscription_type" name="subscription_type" class="form-control" required>
                        <option value="Free" ${user && user.subscription_type === 'Free' ? 'selected' : ''}>Free</option>
                        <option value="Premium" ${user && user.subscription_type === 'Premium' ? 'selected' : ''}>Premium</option>
                    </select>
                </div>
            </div>
            <div class="form-group">
                <label for="gakucoins">Gakucoins</label>
                <input type="number" id="gakucoins" name="gakucoins" class="form-control" value="${user ? user.gakucoins : 0}" required min="0">
            </div>
            <div class="form-group">
                <label class="checkbox-label">
                    <input type="checkbox" id="is_verified" name="is_verified" value="1" ${user && user.is_verified ? 'checked' : ''}>
                    <span class="checkmark"></span>
                    User Verified
                </label>
            </div>
        </form>
    `;
}


function grantItemsToUser(userId) {
    showNotification('Bulk item granting functionality coming soon', 'info');
}


function manageQuestions(quizId) {
    window.open(`quiz_questions.php?quiz_id=${quizId}`, '_blank');
}


function manageUserPet(userId) {
    console.log('Manage user pet:', userId);
}


function openModal(modalId) {
    document.getElementById(modalId).style.display = 'block';
}


function processPayout(payoutId) {
    if (confirm('Are you sure you want to process this payout?')) {
        fetch('admin_ajax.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: `action=process_payout&payout_id=${payoutId}`
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                showNotification('Payout processed successfully', 'success');
                setTimeout(() => location.reload(), 1000);
            } else {
                showNotification('Error processing payout: ' + data.message, 'error');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            showNotification('Error processing payout', 'error');
        });
    }
}


function rejectApplication(applicationId) {
    if (confirm('Are you sure you want to reject this creator application?')) {
        fetch('admin_ajax.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: `action=reject_application&application_id=${applicationId}`
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                showNotification('Application rejected successfully', 'success');
                setTimeout(() => location.reload(), 1000);
            } else {
                showNotification('Error rejecting application: ' + data.message, 'error');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            showNotification('Error rejecting application', 'error');
        });
    }
}


function saveShopItem() {
    const form = document.getElementById('shopItemForm');
    const formData = new FormData(form);
    const itemId = document.getElementById('item_id').value;
    const url = itemId ? 'admin_ajax.php?action=update_shop_item' : 'admin_ajax.php?action=create_shop_item';

    fetch(url, {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            closeModal();
            showNotification('Shop item saved successfully', 'success');
            setTimeout(() => location.reload(), 1000);
        } else {
            showNotification('Error saving shop item: ' + data.message, 'error');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        showNotification('Error saving shop item', 'error');
    });
}


function saveTopic() {
    const form = document.getElementById('topicForm');
    const formData = new FormData(form);
    const topicId = document.getElementById('topic_id').value;
    const url = topicId ? 'admin_ajax.php?action=update_topic' : 'admin_ajax.php?action=create_topic';

    fetch(url, {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            closeModal();
            showNotification('Topic saved successfully', 'success');
            setTimeout(() => location.reload(), 1000);
        } else {
            showNotification('Error saving topic: ' + data.message, 'error');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        showNotification('Error saving topic', 'error');
    });
}


function showAddItemModal() {
    document.getElementById('shopItemModalTitle').textContent = 'Add Shop Item';
    document.getElementById('shopItemForm').reset();
    document.getElementById('item_id').value = '';
    openModal('shopItemModal');
}


function showAddLessonModal() {
    document.getElementById('lessonModalTitle').textContent = 'Add Lesson';
    document.getElementById('lessonForm').reset();
    document.getElementById('lesson_id').value = '';
    openModal('lessonModal');
}


function showAddQuizModal() {
    document.getElementById('quizModalTitle').textContent = 'Add Quiz';
    document.getElementById('quizForm').reset();
    document.getElementById('quiz_id').value = '';
    openModal('quizModal');
}


function showAddTopicModal() {
    document.getElementById('topicModalTitle').textContent = 'Add Topic';
    document.getElementById('topicForm').reset();
    document.getElementById('topic_id').value = '';
    openModal('topicModal');
}


function showAddUserModal() {
    document.getElementById('userModalTitle').textContent = 'Add User';
    document.getElementById('userForm').reset();
    document.getElementById('user_id').value = '';
    openModal('userModal');
}


function updateAnalyticsData(period) {
    // Show loading state
    document.querySelectorAll('.chart-content').forEach(chart => {
        chart.innerHTML = '<div class="loading">Loading data...</div>';
    });
    
    // Reload charts with new period
    setTimeout(() => {
        initializeCharts();
    }, 500);
}


function viewApplication(applicationId) {
    // Open application details in modal or new page
    console.log('View application:', applicationId);
}


function viewFeedback(feedbackId) {
    console.log('View feedback:', feedbackId);
}


function viewPayoutDetails(payoutId) {
    console.log('View payout details:', payoutId);
}


function viewPendingDetails(indexId) {
    console.log('View pending details:', indexId);
}


function viewPetUsers(petId) {
    console.log('View pet users:', petId);
}


function viewQuizDetails(quizId) {
    window.open(`quiz_view.php?quiz_id=${quizId}`, '_blank');
}


function viewUserInventory(userId) {
    console.log('View user inventory:', userId);
}



document.addEventListener('DOMContentLoaded', function() {
    try {
        // mobile warning
        if (typeof isMobileDevice === 'function' && isMobileDevice()) {
            const modal = document.querySelector('.mobile-warning-modal');
            if (modal) modal.style.display = 'flex';
        }
    } catch (e) { console.warn('isMobileDevice check failed', e); }

    try { if (typeof setupSearchFilters === 'function') setupSearchFilters(); } catch(e){}
    try { if (typeof setupEventListeners === 'function') setupEventListeners(); } catch(e){}
    try { if (typeof initializeDropdowns === 'function') initializeDropdowns(); } catch(e){}
    try { if (typeof initializeCharts === 'function') initializeCharts(); } catch(e){}
    try { if (typeof initializeManagementSections === 'function') initializeManagementSections(); } catch(e){}
    try {
        // initialize pagination if available
        if (typeof loadUserPage === 'function' && document.getElementById('userTableBody')) {
            loadUserPage(1);
        }
        if (typeof loadLessonPage === 'function' && document.getElementById('lessonTableBody')) {
            loadLessonPage(1);
        }
        if (typeof loadQuizPage === 'function' && document.getElementById('quizTableBody')) {
            loadQuizPage(1);
        }
    } catch(e){}
});

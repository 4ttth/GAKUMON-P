// Enhanced Admin Dashboard JavaScript with Full CRUD Operations

// Mobile device detection and redirection
function isMobileDevice() {
    return /Android|webOS|iPhone|iPad|iPod|BlackBerry|IEMobile|Opera Mini/i.test(navigator.userAgent);
}

function redirectToIndex() {
    window.location.href = 'index.php';
}

// Initialize dashboard when DOM is loaded
document.addEventListener('DOMContentLoaded', function() {
    // Show mobile warning if on mobile device
    if (isMobileDevice()) {
        document.querySelector('.mobile-warning-modal').style.display = 'flex';
    }
    
    // Initialize search filters
    setupSearchFilters();
    
    // Setup event listeners
    setupEventListeners();
    
    // Initialize dropdown functionality
    initializeDropdowns();
});

// Setup search and filter functionality
function setupSearchFilters() {
    // User search
    const userSearch = document.getElementById('userSearch');
    if (userSearch) {
        userSearch.addEventListener('input', debounce(function() {
            filterTable('userTable', this.value);
        }, 300));
    }

    // Role filter
    const roleFilter = document.getElementById('roleFilter');
    if (roleFilter) {
        roleFilter.addEventListener('change', function() {
            filterTableByColumn('userTable', 4, this.value); // Role column
        });
    }

    // Subscription filter
    const subscriptionFilter = document.getElementById('subscriptionFilter');
    if (subscriptionFilter) {
        subscriptionFilter.addEventListener('change', function() {
            filterTableByColumn('userTable', 5, this.value); // Subscription column
        });
    }
}

// Setup event listeners
function setupEventListeners() {
    // Analytics period filter
    const analyticsPeriod = document.getElementById('analyticsPeriod');
    if (analyticsPeriod) {
        analyticsPeriod.addEventListener('change', function() {
            updateAnalyticsData(this.value);
        });
    }
}

// Initialize dropdown functionality
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

// Table filtering functions
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

// Tab Management Functions
function openCreatorTab(tabName) {
    document.querySelectorAll('#creator-management .tab-content').forEach(tab => {
        tab.classList.remove('active');
    });
    document.querySelectorAll('#creator-management .tab-button').forEach(button => {
        button.classList.remove('active');
    });
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

// User Management Functions
function showAddUserModal() {
    showModal('Add User', getUserModalContent(), saveUser);
}

function editUser(userId) {
    fetch(`admin_ajax.php?action=get_user&user_id=${userId}`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                showModal('Edit User', getUserModalContent(data.user), saveUser);
            } else {
                showNotification('Error loading user data', 'error');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            showNotification('Error loading user data', 'error');
        });
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

function viewUserDetails(userId) {
    window.open(`user_profile.php?user_id=${userId}`, '_blank');
}

// Lesson Management Functions
function showAddLessonModal() {
    showModal('Add Lesson', getLessonModalContent(), saveLesson);
}

function editLesson(lessonId) {
    fetch(`admin_ajax.php?action=get_lesson&lesson_id=${lessonId}`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                showModal('Edit Lesson', getLessonModalContent(data.lesson), saveLesson);
            } else {
                showNotification('Error loading lesson data', 'error');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            showNotification('Error loading lesson data', 'error');
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

function viewLessonDetails(lessonId) {
    window.open(`lesson_view.php?lesson_id=${lessonId}`, '_blank');
}

// Quiz Management Functions
function showAddQuizModal() {
    showModal('Add Quiz', getQuizModalContent(), saveQuiz);
}

function editQuiz(quizId) {
    fetch(`admin_ajax.php?action=get_quiz&quiz_id=${quizId}`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                showModal('Edit Quiz', getQuizModalContent(data.quiz), saveQuiz);
            } else {
                showNotification('Error loading quiz data', 'error');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            showNotification('Error loading quiz data', 'error');
        });
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

function manageQuestions(quizId) {
    window.open(`quiz_questions.php?quiz_id=${quizId}`, '_blank');
}

// Creator Management Functions
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

// Shop Management Functions
function showAddItemModal() {
    showModal('Add Shop Item', getShopItemModalContent(), saveShopItem);
}

function editShopItem(itemId) {
    fetch(`admin_ajax.php?action=get_shop_item&item_id=${itemId}`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                showModal('Edit Shop Item', getShopItemModalContent(data.item), saveShopItem);
            } else {
                showNotification('Error loading item data', 'error');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            showNotification('Error loading item data', 'error');
        });
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

function deleteShopItem(itemId) {
    if (confirm('Are you sure you want to delete this shop item?')) {
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

function editPet(petId) {
    showNotification('Pet editing functionality coming soon', 'info');
}

function grantItemsToUser(userId) {
    showNotification('Bulk item granting functionality coming soon', 'info');
}

function adjustCoins(userId) {
    const amount = prompt('Enter the amount of Gakucoins to add/subtract (use negative for subtraction):');
    if (amount !== null && !isNaN(amount)) {
        showNotification('Coin adjustment functionality coming soon', 'info');
    }
}

// System Management Functions
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

function editTopic(topicId) {
    fetch(`admin_ajax.php?action=get_topic&topic_id=${topicId}`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                showModal('Edit Topic', getTopicModalContent(data.topic), saveTopic);
            } else {
                showNotification('Error loading topic data', 'error');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            showNotification('Error loading topic data', 'error');
        });
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

// Modal Management
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
    saveBtn.onclick = saveCallback;
    
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

// Notification System
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

// Export functionality
function exportTableData(tableId, filename) {
    const table = document.getElementById(tableId);
    if (!table) return;
    
    const rows = table.querySelectorAll('tr');
    let csv = [];
    
    for (let i = 0; i < rows.length; i++) {
        let row = [], cols = rows[i].querySelectorAll('td, th');
        
        for (let j = 0; j < cols.length - 1; j++) { // Exclude actions column
            row.push('"' + cols[j].innerText.replace(/"/g, '""') + '"');
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

// Analytics update
function updateAnalyticsData(period) {
    // Show loading state
    document.querySelectorAll('.chart-content').forEach(chart => {
        chart.innerHTML = '<div class="loading">Loading data...</div>';
    });
    
    // Reload charts with new period
    setTimeout(() => {
        location.reload();
    }, 500);
}

// ---- Account dropdown compat shim (no HTML edits needed) ----
document.addEventListener('DOMContentLoaded', () => {
  // Try the IDs your script expects first
  let btn  = document.getElementById('accountDropdownBtn');
  let menu = document.getElementById('accountDropdown');

  // If not found, fall back to common patterns inside the desktop nav
  if (!btn) {
    btn =
      document.querySelector('[data-account-btn]') ||
      document.querySelector('.kanri-account-btn') ||
      document.querySelector('.account-btn') ||
      document.querySelector('.user-menu-toggle') ||
      document.querySelector('.profile-toggle') ||
      document.querySelector('.dropdown-toggle.account') ||
      document.querySelector('.desktopKanriNav .dropdown-toggle');
  }
  if (!menu && btn) {
    // Prefer the next dropdown-ish sibling, else any dropdown within the same container
    const sib = btn.nextElementSibling;
    if (sib && (sib.classList.contains('dropdown-menu') || sib.classList.contains('account-dropdown'))) {
      menu = sib;
    } else {
      menu = btn.parentElement?.querySelector('.dropdown-menu, .account-dropdown');
    }
  }

  // If we still don't have both, nothing to wire up
  if (!btn || !menu) return;

  // Prevent double binding if your original init already ran
  if (!btn.__accountWired) {
    btn.__accountWired = true;

    btn.addEventListener('click', (e) => {
      e.preventDefault();
      e.stopPropagation();
      menu.classList.toggle('show');   // Bootstrap-style
      menu.classList.toggle('open');   // in case your CSS uses 'open'
    });

    document.addEventListener('click', (e) => {
      if (!menu.contains(e.target) && e.target !== btn) {
        menu.classList.remove('show');
        menu.classList.remove('open');
      }
    });

    document.addEventListener('keydown', (e) => {
      if (e.key === 'Escape') {
        menu.classList.remove('show');
        menu.classList.remove('open');
      }
    });
  }
});

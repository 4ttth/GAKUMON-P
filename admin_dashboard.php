<?php
session_start();

$pageTitle = 'GAKUMON — Admin Dashboard';
$pageCSS = 'CSS/desktop/kanri-merged.css';
$pageJS = 'JS/desktop/merged-admin_all.js';

include 'include/header.php';
require_once 'config/config.php';

// Check if user is logged in and has Kanri role
if (!isset($_SESSION['sUser'])) {
    header("Location: login.php");
    exit;
}

$username = $_SESSION['sUser'];
$stmt = $connection->prepare("SELECT user_id, role FROM tbl_user WHERE username = ?");
$stmt->bind_param("s", $username);
$stmt->execute();
$result = $stmt->get_result();

if ($row = $result->fetch_assoc()) {
    $userID = $row['user_id'];
    if ($row['role'] !== 'Kanri') {
        header("Location: index.php");
        exit;
    }
} else {
    header("Location: login.php");
    exit;
}

// Get comprehensive statistics
$stats = [];

// Total Users with growth
$result = $connection->query("
    SELECT COUNT(*) as total_users, 
           SUM(CASE WHEN created_at >= DATE_SUB(CURDATE(), INTERVAL 30 DAY) THEN 1 ELSE 0 END) as new_users_30d,
           SUM(CASE WHEN last_login >= DATE_SUB(NOW(), INTERVAL 1 DAY) THEN 1 ELSE 0 END) as dau
    FROM tbl_user
");
$stats['users'] = $result->fetch_assoc();

// Total Lessons with growth
$result = $connection->query("
    SELECT COUNT(*) as total_lessons, 
           SUM(CASE WHEN created_at >= DATE_SUB(CURDATE(), INTERVAL 30 DAY) THEN 1 ELSE 0 END) as new_lessons_30d
    FROM tbl_lesson
");
$stats['lessons'] = $result->fetch_assoc();

// Revenue/Payouts
$result = $connection->query("
    SELECT COALESCE(SUM(earned_amount), 0) as total_earnings,
           COALESCE(SUM(CASE WHEN recorded_at >= DATE_SUB(CURDATE(), INTERVAL 30 DAY) THEN earned_amount ELSE 0 END), 0) as earnings_30d
    FROM tbl_creator_earnings
");
$stats['revenue'] = $result->fetch_assoc();

// Gakucoins Economy
$result = $connection->query("SELECT COALESCE(SUM(gakucoins), 0) as total_coins FROM tbl_user");
$stats['coins'] = $result->fetch_assoc();

// User Growth Chart Data
$result = $connection->query("
    SELECT DATE(created_at) as date, COUNT(*) as count 
    FROM tbl_user 
    WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 30 DAY) 
    GROUP BY DATE(created_at) 
    ORDER BY date
");
$user_growth_data = $result->fetch_all(MYSQLI_ASSOC);

// Top Lessons by Enrollment
$result = $connection->query("
    SELECT l.lesson_id, l.title, COUNT(e.user_id) as enrollment_count 
    FROM tbl_lesson l 
    LEFT JOIN tbl_user_enrollments e ON l.lesson_id = e.lesson_id 
    GROUP BY l.lesson_id, l.title 
    ORDER BY enrollment_count DESC 
    LIMIT 10
");
$top_lessons = $result->fetch_all(MYSQLI_ASSOC);

// Quiz Analytics
$result = $connection->query("
    SELECT q.quiz_id, q.title, 
           COUNT(qa.attempt_id) as attempts,
           AVG(qa.score) as avg_score,
           SUM(CASE WHEN qa.score >= 3 THEN 1 ELSE 0 END) / COUNT(qa.attempt_id) * 100 as pass_rate
    FROM tbl_quizzes q
    LEFT JOIN tbl_user_quiz_attempts qa ON q.quiz_id = qa.quiz_id
    GROUP BY q.quiz_id
    HAVING attempts > 0
    ORDER BY attempts DESC
    LIMIT 10
");
$quiz_analytics = $result->fetch_all(MYSQLI_ASSOC);

// Shop Analytics
$result = $connection->query("
    SELECT si.item_name, si.price, COUNT(ui.user_item_id) as sales_count,
           SUM(si.price) as total_revenue
    FROM tbl_shop_items si
    LEFT JOIN tbl_user_items ui ON si.item_id = ui.item_id
    GROUP BY si.item_id
    ORDER BY sales_count DESC
    LIMIT 10
");
$shop_analytics = $result->fetch_all(MYSQLI_ASSOC);

// Recent Activity
$result = $connection->query("
    SELECT al.*, u.username as admin_username 
    FROM tbl_admin_audit_logs al
    JOIN tbl_user u ON al.user_id = u.user_id
    ORDER BY al.created_at DESC 
    LIMIT 10
");
$recent_activity = $result->fetch_all(MYSQLI_ASSOC);

include 'include/desktopKanriNav.php';
?>


<!-- Main Layout -->
<style>
.content-area { z-index: 1 !important; position: relative !important; }
.side-navbar { z-index: 10000 !important; pointer-events: auto !important; }
.nav-item { pointer-events: auto !important; }
</style>
<div class="main-layout">
    <div class="content-area">
        <div class="container-fluid page-content">
            
            <!-- Dashboard Header -->
            <div class="dashboard-header">
                <h1>Admin Dashboard</h1>
                <p>Welcome back, <?php echo htmlspecialchars($username); ?>! Comprehensive platform management and analytics.</p>
            </div>

            <!-- KPI Statistics Grid -->
            <div class="stats-grid" id="stats-grid">
                <div class="stat-card">
                    <div class="stat-icon users">
                        <i class="fas fa-users"></i>
                    </div>
                    <div class="stat-info">
                        <h3><?php echo number_format($stats['users']['total_users']); ?></h3>
                        <p>Total Users</p>
                        <small>+<?php echo $stats['users']['new_users_30d']; ?> this month | DAU: <?php echo $stats['users']['dau']; ?></small>
                    </div>
                    <a href="#user-management" class="stat-action"><i class="fas fa-info-circle"></i></a>
                </div>

                <div class="stat-card">
                    <div class="stat-icon lessons">
                        <i class="fas fa-book"></i>
                    </div>
                    <div class="stat-info">
                        <h3><?php echo number_format($stats['lessons']['total_lessons']); ?></h3>
                        <p>Total Lessons</p>
                        <small>+<?php echo $stats['lessons']['new_lessons_30d']; ?> this month</small>
                    </div>
                    <a href="#lesson-management" class="stat-action"><i class="fa-info-circle"></i></a>
                </div>

                <div class="stat-card">
                    <div class="stat-icon revenue">
                        <i class="fas fa-dollar-sign"></i>
                    </div>
                    <div class="stat-info">
                        <h3>₱<?php echo number_format($stats['revenue']['total_earnings'], 2); ?></h3>
                        <p>Total Revenue</p>
                        <small>₱<?php echo number_format($stats['revenue']['earnings_30d'], 2); ?> this month</small>
                    </div>
                    <a href="#creator-management" class="stat-action"><i class="fa-info-circle"></i></a>
                </div>

                <div class="stat-card">
                    <div class="stat-icon quizzes">
                        <i class="fas fa-coins"></i>
                    </div>
                    <div class="stat-info">
                        <h3><?php echo number_format($stats['coins']['total_coins']); ?></h3>
                        <p>Gakucoins Supply</p>
                        <small>In circulation</small>
                    </div>
                    <a href="#shop-management" class="stat-action"><i class="fa-info-circle"></i></a>
                </div>
            </div>

            <!-- Analytics Section -->
            <div class="analytics-section">
                <div class="section-header">
                    <h2>Platform Analytics</h2>
                    <div class="time-filter">
                        <select id="analyticsPeriod">
                            <option value="7">Last 7 Days</option>
                            <option value="30" selected>Last 30 Days</option>
                            <option value="90">Last 90 Days</option>
                        </select>
                    </div>
                </div>

                <div class="charts-container">
                    <div class="chart-card">
                        <div class="chart-header">
                            <h3>User Growth Trend</h3>
                        </div>
                        <div class="chart-content">
                            <canvas id="userGrowthChart"></canvas>
                        </div>
                    </div>

                    <div class="chart-card">
                        <div class="chart-header">
                            <h3>Top Lessons by Enrollment</h3>
                        </div>
                        <div class="chart-content">
                            <canvas id="topLessonsChart"></canvas>
                        </div>
                    </div>

                    <div class="chart-card">
                        <div class="chart-header">
                            <h3>Quiz Performance</h3>
                        </div>
                        <div class="chart-content">
                            <canvas id="quizPerformanceChart"></canvas>
                        </div>
                    </div>

                    <div class="chart-card">
                        <div class="chart-header">
                            <h3>Shop Revenue</h3>
                        </div>
                        <div class="chart-content">
                            <canvas id="shopRevenueChart"></canvas>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Recent Activity -->
            <div class="recent-activity">
                <div class="activity-header">
                    <h3>Recent Admin Activity</h3>
                    <a href="#system-management" class="view-all">View All Logs</a>
                </div>
                <div class="activity-list">
                    <?php if (empty($recent_activity)): ?>
                        <div class="activity-item">
                            <div class="activity-details">
                                <p>No recent activity</p>
                            </div>
                        </div>
                    <?php else: ?>
                        <?php foreach ($recent_activity as $activity): ?>
                            <div class="activity-item">
                                <div class="activity-icon">
                                    <i class="fas fa-history"></i>
                                </div>
                                <div class="activity-details">
                                    <p><strong><?php echo htmlspecialchars($activity['admin_username']); ?></strong></p>
                                    <p><?php echo htmlspecialchars($activity['action']); ?></p>
                                    <span class="activity-time"><?php echo date('M j, Y g:i A', strtotime($activity['created_at'])); ?></span>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>    
<!-- Include Chart.js for analytics -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script src="JS/desktop/adminManagementScript.js"></script>
<script src="JS/desktop/adminCrudTest.js"></script>
<script src="JS/desktop/adminPagination.js"></script>


<script>
// Initialize charts with data
document.addEventListener('DOMContentLoaded', function() {
    // User Growth Chart
    const userGrowthData = <?php echo json_encode($user_growth_data); ?>;
    const userGrowthCtx = document.getElementById('userGrowthChart').getContext('2d');
    new Chart(userGrowthCtx, {
        type: 'line',
        data: {
            labels: userGrowthData.map(d => d.date),
            datasets: [{
                label: 'New Users',
                data: userGrowthData.map(d => d.count),
                borderColor: '#811212',
                backgroundColor: 'rgba(129, 18, 18, 0.1)',
                tension: 0.4,
                fill: true
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: { legend: { display: false } },
            scales: {
                y: { beginAtZero: true },
                x: { display: false }
            }
        }
    });

    // Top Lessons Chart
    const topLessonsData = <?php echo json_encode($top_lessons); ?>;
    const topLessonsCtx = document.getElementById('topLessonsChart').getContext('2d');
    new Chart(topLessonsCtx, {
        type: 'bar',
        data: {
            labels: topLessonsData.map(d => d.title.substring(0, 20) + '...'),
            datasets: [{
                label: 'Enrollments',
                data: topLessonsData.map(d => d.enrollment_count),
                backgroundColor: '#4299e1'
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            indexAxis: 'y',
            plugins: { legend: { display: false } }
        }
    });

    // Quiz Performance Chart
    const quizData = <?php echo json_encode($quiz_analytics); ?>;
    const quizCtx = document.getElementById('quizPerformanceChart').getContext('2d');
    new Chart(quizCtx, {
        type: 'scatter',
        data: {
            datasets: [{
                label: 'Quiz Performance',
                data: quizData.map(d => ({x: d.attempts, y: d.avg_score})),
                backgroundColor: '#ed8936'
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: { legend: { display: false } },
            scales: {
                x: { title: { display: true, text: 'Attempts' } },
                y: { title: { display: true, text: 'Avg Score' } }
            }
        }
    });

    // Shop Revenue Chart
    const shopData = <?php echo json_encode($shop_analytics); ?>;
    const shopCtx = document.getElementById('shopRevenueChart').getContext('2d');
    new Chart(shopCtx, {
        type: 'doughnut',
        data: {
            labels: shopData.map(d => d.item_name),
            datasets: [{
                data: shopData.map(d => d.total_revenue),
                backgroundColor: ['#48bb78', '#4299e1', '#ed8936', '#9f7aea', '#f56565']
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: { legend: { position: 'bottom' } }
        }
    });
});

// Tab management functions
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

// Mobile detection and redirect
function isMobileDevice() {
    return /Android|webOS|iPhone|iPad|iPod|BlackBerry|IEMobile|Opera Mini/i.test(navigator.userAgent);
}

function redirectToIndex() {
    window.location.href = 'index.php';
}

if (isMobileDevice()) {
    document.querySelector('.mobile-warning-modal').style.display = 'flex';
}

// Modal functions
function closeModal(modalId) {
    document.getElementById(modalId).style.display = 'none';
}

// CRUD operation functions
function showAddUserModal() {
    document.getElementById('userModalTitle').textContent = 'Add User';
    document.getElementById('userForm').reset();
    document.getElementById('userId').value = '';
    document.getElementById('userModal').style.display = 'block';
}

function editUser(id) {
    fetch(`admin_ajax.php?action=get_user&user_id=${id}`)
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            const u = data.user;
            document.getElementById('userModalTitle').textContent = 'Edit User';
            document.getElementById('userId').value = u.user_id;
            document.getElementById('firstName').value = u.first_name;
            document.getElementById('lastName').value = u.last_name;
            document.getElementById('username').value = u.username;
            document.getElementById('email').value = u.email_address;
            document.getElementById('password').value = '';
            document.getElementById('role').value = u.role;
            document.getElementById('subscription').value = u.subscription_type;
            document.getElementById('gakucoins').value = u.gakucoins;
            document.getElementById('isVerified').checked = u.is_verified == 1;
            document.getElementById('userModal').style.display = 'block';
        }
    });
}

function deleteUser(id) {
    if (confirm('Delete this user?')) {
        const formData = new FormData();
        formData.append('action', 'delete_user');
        formData.append('user_id', id);
        
        fetch('admin_ajax.php', { method: 'POST', body: formData })
        .then(r => r.json())
        .then(data => {
            alert(data.message);
            if (data.success) location.reload();
        });
    }
}

function viewUserDetails(id) {
    fetch(`admin_ajax.php?action=get_user&user_id=${id}`)
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            const u = data.user;
            document.getElementById('viewModalTitle').textContent = 'User Details';
            document.getElementById('viewModalContent').innerHTML = `
                <div class="form-group"><strong>ID:</strong> ${u.user_id}</div>
                <div class="form-group"><strong>Name:</strong> ${u.first_name} ${u.last_name}</div>
                <div class="form-group"><strong>Username:</strong> ${u.username}</div>
                <div class="form-group"><strong>Email:</strong> ${u.email_address}</div>
                <div class="form-group"><strong>Role:</strong> ${u.role}</div>
                <div class="form-group"><strong>Subscription:</strong> ${u.subscription_type}</div>
                <div class="form-group"><strong>Gakucoins:</strong> ${u.gakucoins}</div>
                <div class="form-group"><strong>Verified:</strong> ${u.is_verified ? 'Yes' : 'No'}</div>
                <div class="form-group"><strong>Created:</strong> ${u.created_at}</div>
            `;
            document.getElementById('viewModal').style.display = 'block';
        }
    });
}

function showAddLessonModal() {
    document.getElementById('lessonModalTitle').textContent = 'Add Lesson';
    document.getElementById('lessonForm').reset();
    document.getElementById('lessonId').value = '';
    document.getElementById('duration').value = '00:30:00';
    document.getElementById('topicId').value = '1';
    document.getElementById('lessonModal').style.display = 'block';
}

function editLesson(id) {
    console.log('editLesson called with ID:', id);
    fetch(`admin_ajax.php?action=get_lesson&lesson_id=${id}`)
    .then(r => {
        console.log('Response status:', r.status);
        return r.json();
    })
    .then(data => {
        console.log('Response data:', data);
        if (data.success) {
            const l = data.lesson;
            console.log('Lesson data:', l);
            document.getElementById('lessonModalTitle').textContent = 'Edit Lesson';
            document.getElementById('lessonId').value = l.lesson_id;
            document.getElementById('lessonTitle').value = l.title;
            document.getElementById('shortDesc').value = l.short_desc;
            document.getElementById('longDesc').value = l.long_desc;
            document.getElementById('duration').value = l.duration;
            document.getElementById('topicId').value = l.topic_id;
            document.getElementById('difficulty').value = l.difficulty_level;
            document.getElementById('isPrivate').checked = l.is_private == 1;
            document.getElementById('lessonModal').style.display = 'block';
            console.log('Modal should be visible now');
        } else {
            console.error('Failed to get lesson:', data.message);
            alert('Error: ' + data.message);
        }
    })
    .catch(err => {
        console.error('Fetch error:', err);
        alert('Network error: ' + err.message);
    });
}
function deleteLesson(id) {
    if (confirm('Delete this lesson?')) {
        const formData = new FormData();
        formData.append('action', 'delete_lesson');
        formData.append('lesson_id', id);
        
        fetch('admin_ajax.php', { method: 'POST', body: formData })
        .then(r => r.json())
        .then(data => {
            alert(data.message);
            if (data.success) location.reload();
        });
    }
}

function viewLessonDetails(id) {
    fetch(`admin_ajax.php?action=get_lesson&lesson_id=${id}`)
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            const l = data.lesson;
            document.getElementById('viewModalTitle').textContent = 'Lesson Details';
            document.getElementById('viewModalContent').innerHTML = `
                <div class="form-group"><strong>ID:</strong> ${l.lesson_id}</div>
                <div class="form-group"><strong>Title:</strong> ${l.title}</div>
                <div class="form-group"><strong>Short Description:</strong> ${l.short_desc}</div>
                <div class="form-group"><strong>Duration:</strong> ${l.duration}</div>
                <div class="form-group"><strong>Topic ID:</strong> ${l.topic_id}</div>
                <div class="form-group"><strong>Difficulty:</strong> ${l.difficulty_level}</div>
                <div class="form-group"><strong>Private:</strong> ${l.is_private ? 'Yes' : 'No'}</div>
                <div class="form-group"><strong>Author ID:</strong> ${l.author_id || 'None'}</div>
            `;
            document.getElementById('viewModal').style.display = 'block';
        }
    });
}
function showAddQuizModal() {
    document.getElementById('quizModalTitle').textContent = 'Add Quiz';
    document.getElementById('quizForm').reset();
    document.getElementById('quizId').value = '';
    document.getElementById('quizModal').style.display = 'block';
}

function editQuiz(id) {
    fetch(`admin_ajax.php?action=get_quiz&quiz_id=${id}`)
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            const q = data.quiz;
            document.getElementById('quizModalTitle').textContent = 'Edit Quiz';
            document.getElementById('quizId').value = q.quiz_id;
            document.getElementById('quizTitle').value = q.title;
            document.getElementById('quizLessonId').value = q.lesson_id || '';
            document.getElementById('isAiGenerated').checked = q.is_ai_generated == 1;
            document.getElementById('quizModal').style.display = 'block';
        }
    });
}
function deleteQuiz(id) {
    if (confirm('Delete this quiz?')) {
        const formData = new FormData();
        formData.append('action', 'delete_quiz');
        formData.append('quiz_id', id);
        
        fetch('admin_ajax.php', { method: 'POST', body: formData })
        .then(r => r.json())
        .then(data => {
            alert(data.message);
            if (data.success) location.reload();
        });
    }
}

function manageQuestions(id) { alert('Manage questions functionality - coming soon'); }
function approveApplication(id) {
    if (confirm('Approve this application?')) {
        const formData = new FormData();
        formData.append('action', 'approve_application');
        formData.append('application_id', id);
        
        fetch('admin_ajax.php', { method: 'POST', body: formData })
        .then(r => r.json())
        .then(data => {
            alert(data.message);
            if (data.success) location.reload();
        });
    }
}

function rejectApplication(id) {
    if (confirm('Reject this application?')) {
        const formData = new FormData();
        formData.append('action', 'reject_application');
        formData.append('application_id', id);
        
        fetch('admin_ajax.php', { method: 'POST', body: formData })
        .then(r => r.json())
        .then(data => {
            alert(data.message);
            if (data.success) location.reload();
        });
    }
}

function processPayout(id) { alert('Process payout - use admin_ajax.php endpoint'); }
function showAddItemModal() {
    document.getElementById('shopItemModalTitle').textContent = 'Add Shop Item';
    document.getElementById('shopItemForm').reset();
    document.getElementById('shopItemId').value = '';
    document.getElementById('shopItemModal').style.display = 'block';
}
function editShopItem(id) {
    fetch(`admin_ajax.php?action=get_shop_item&item_id=${id}`)
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            const item = data.item;
            document.getElementById('shopItemModalTitle').textContent = 'Edit Shop Item';
            document.getElementById('shopItemId').value = item.item_id;
            document.getElementById('itemType').value = item.item_type;
            document.getElementById('itemName').value = item.item_name;
            document.getElementById('itemDescription').value = item.description;
            document.getElementById('itemPrice').value = item.price;
            document.getElementById('energyRestore').value = item.energy_restore || '';
            document.getElementById('imageUrl').value = item.image_url;
            document.getElementById('shopItemModal').style.display = 'block';
        }
    });
}
function deleteShopItem(id) {
    if (confirm('Delete this shop item?')) {
        const formData = new FormData();
        formData.append('action', 'delete_shop_item');
        formData.append('item_id', id);
        
        fetch('admin_ajax.php', { method: 'POST', body: formData })
        .then(r => r.json())
        .then(data => {
            alert(data.message);
            if (data.success) location.reload();
        });
    }
}

function grantItemToUser(id) {
    document.getElementById('grantItemId').value = id;
    // Load users for dropdown with proper format
    fetch('admin_ajax.php?action=get_users_for_dropdown')
    .then(r => r.json())
    .then(data => {
        const userSelect = document.getElementById('grantUserId');
        userSelect.innerHTML = '<option value="">Select a user...</option>';
        
        if (data.success && data.users) {
            data.users.forEach(user => {
                userSelect.innerHTML += `<option value="${user.user_id}">${user.username} (Email: ${user.email_address})</option>`;
            });
        }
        
        document.getElementById('grantItemModal').style.display = 'block';
    })
    .catch(() => {
        // Fallback
        const userSelect = document.getElementById('grantUserId');
        userSelect.innerHTML = '<option value="">Error loading users</option>';
        document.getElementById('grantItemModal').style.display = 'block';
    });
}
function editPet(id) { alert('Edit pet functionality - coming soon'); }
function grantItemsToUser(id) {
    // This opens the grant item modal for a specific user (select item from shop)
    document.getElementById('grantToUserId').value = id;
    
    // Load shop items for dropdown
    fetch('admin_ajax.php?action=get_shop_items')
    .then(r => r.json())
    .then(data => {
        const itemSelect = document.getElementById('grantToUserItemId');
        itemSelect.innerHTML = '<option value="">Select an item...</option>';
        
        if (data.success && data.items) {
            data.items.forEach(item => {
                itemSelect.innerHTML += `<option value="${item.item_id}">${item.item_name} (${item.price} coins)</option>`;
            });
        }
        
        document.getElementById('grantToUserModal').style.display = 'block';
    })
    .catch(() => {
        // Fallback: simple prompt
        const itemId = prompt('Enter item ID to grant:');
        if (itemId) {
            const formData = new FormData();
            formData.append('action', 'grant_item');
            formData.append('user_id', id);
            formData.append('item_id', itemId);
            
            fetch('admin_ajax.php', { method: 'POST', body: formData })
            .then(r => r.json())
            .then(data => {
                alert(data.message);
                if (data.success) location.reload();
            });
        }
    });
}
function showGakucoinModal(userId, username, currentCoins) {
    document.getElementById('gakucoinUserId').value = userId;
    document.getElementById('gakucoinUsername').textContent = username;
    document.getElementById('gakucoinCurrent').textContent = currentCoins.toLocaleString();
    document.getElementById('gakucoinAmount').value = '';
    document.getElementById('gakucoinAction').value = 'set';
    document.getElementById('gakucoinModal').style.display = 'block';
}

function setGakucoinAmount(amount) {
    document.getElementById('gakucoinAmount').value = amount;
}

function processGakucoinChange() {
    const userId = document.getElementById('gakucoinUserId').value;
    const action = document.getElementById('gakucoinAction').value;
    const amount = parseInt(document.getElementById('gakucoinAmount').value);
    
    if (!amount || amount < 0) {
        alert('Please enter a valid amount');
        return;
    }
    
    const formData = new FormData();
    formData.append('action', 'adjust_gakucoins');
    formData.append('user_id', userId);
    formData.append('coin_action', action);
    formData.append('amount', amount);
    
    fetch('admin_ajax.php', { method: 'POST', body: formData })
    .then(r => r.json())
    .then(data => {
        alert(data.message);
        if (data.success) {
            closeModal('gakucoinModal');
            location.reload();
        }
    })
    .catch(err => alert('Error: ' + err.message));
}

function deleteFeedback(id) {
    if (confirm('Delete this feedback?')) {
        const formData = new FormData();
        formData.append('action', 'delete_feedback');
        formData.append('feedback_id', id);
        
        fetch('admin_ajax.php', { method: 'POST', body: formData })
        .then(r => r.json())
        .then(data => {
            alert(data.message);
            if (data.success) location.reload();
        });
    }
}

function approveVerification(id) {
    if (confirm('Approve this verification?')) {
        const formData = new FormData();
        formData.append('action', 'approve_verification');
        formData.append('index_id', id);
        
        fetch('admin_ajax.php', { method: 'POST', body: formData })
        .then(r => r.json())
        .then(data => {
            alert(data.message);
            if (data.success) location.reload();
        });
    }
}

function rejectVerification(id) {
    if (confirm('Reject this verification?')) {
        const formData = new FormData();
        formData.append('action', 'reject_verification');
        formData.append('index_id', id);
        
        fetch('admin_ajax.php', { method: 'POST', body: formData })
        .then(r => r.json())
        .then(data => {
            alert(data.message);
            if (data.success) location.reload();
        });
    }
}

function showAddTopicModal() {
    document.getElementById('topicModalTitle').textContent = 'Add Topic';
    document.getElementById('topicForm').reset();
    document.getElementById('topicIdField').value = '';
    document.getElementById('topicModal').style.display = 'block';
}

function editTopic(id) {
    fetch(`admin_ajax.php?action=get_topic&topic_id=${id}`)
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            const t = data.topic;
            document.getElementById('topicModalTitle').textContent = 'Edit Topic';
            document.getElementById('topicIdField').value = t.topic_id;
            document.getElementById('topicName').value = t.topic_name;
            document.getElementById('topicIcon').value = t.topic_icon;
            document.getElementById('topicModal').style.display = 'block';
        }
    });
}
function deleteTopic(id) {
    if (confirm('Delete this topic?')) {
        const formData = new FormData();
        formData.append('action', 'delete_topic');
        formData.append('topic_id', id);
        
        fetch('admin_ajax.php', { method: 'POST', body: formData })
        .then(r => r.json())
        .then(data => {
            alert(data.message);
            if (data.success) location.reload();
        });
    }
}

function exportTableData(tableId, filename) { alert('Export functionality - coming soon'); }

// Debounced search functionality
let searchTimeouts = {};
function debounceSearch(type, query) {
    clearTimeout(searchTimeouts[type]);
    searchTimeouts[type] = setTimeout(() => {
        performSearch(type, query);
    }, 1000);
}

function performSearch(type, query) {
    const params = new URLSearchParams({
        action: `search_${type}s`,
        query: query,
        page: 1,
        limit: 10
    });
    
    fetch(`admin_ajax.php?${params}`)
    .then(r => r.text())
    .then(html => {
        const tableBody = document.querySelector(`#${type}TableBody`) || 
                         document.querySelector(`#${type}Table tbody`);
        if (tableBody) {
            tableBody.innerHTML = html;
        }
    })
    .catch(err => console.error('Search failed:', err));
}

// Pagination functions
function loadUserPage(page) {
    const params = new URLSearchParams({
        action: 'search_users',
        query: document.getElementById('userSearch')?.value || '',
        page: page,
        limit: 10
    });
    
    fetch(`admin_ajax.php?${params}`)
    .then(r => r.text())
    .then(html => {
        document.getElementById('userTableBody').innerHTML = html;
    })
    .catch(err => console.error('Pagination failed:', err));
}

function loadLessonPage(page) {
    const params = new URLSearchParams({
        action: 'search_lessons',
        query: document.getElementById('lessonSearch')?.value || '',
        page: page,
        limit: 10
    });
    
    fetch(`admin_ajax.php?${params}`)
    .then(r => r.text())
    .then(html => {
        document.getElementById('lessonTableBody').innerHTML = html;
    })
    .catch(err => console.error('Pagination failed:', err));
}

function loadInventoryPage(page) {
    const params = new URLSearchParams({
        action: 'search_inventory',
        query: '',
        page: page,
        limit: 10
    });
    
    fetch(`admin_ajax.php?${params}`)
    .then(r => r.text())
    .then(html => {
        document.getElementById('inventoryTableBody').innerHTML = html;
    })
    .catch(err => console.error('Pagination failed:', err));
}

// Form submission handlers
document.addEventListener('DOMContentLoaded', function() {
    // User form submission
    document.getElementById('userForm').addEventListener('submit', function(e) {
        e.preventDefault();
        const formData = new FormData(this);
        const isEdit = document.getElementById('userId').value !== '';
        formData.append('action', isEdit ? 'update_user' : 'create_user');
        
        fetch('admin_ajax.php', { method: 'POST', body: formData })
        .then(r => r.json())
        .then(data => {
            alert(data.message);
            if (data.success) {
                closeModal('userModal');
                location.reload();
            }
        })
        .catch(err => alert('Error: ' + err.message));
    });
    
    // Lesson form submission
    document.getElementById('lessonForm').addEventListener('submit', function(e) {
        e.preventDefault();
        const formData = new FormData(this);
        const isEdit = document.getElementById('lessonId').value !== '';
        formData.append('action', isEdit ? 'update_lesson' : 'create_lesson');
        
        fetch('admin_ajax.php', { method: 'POST', body: formData })
        .then(r => r.json())
        .then(data => {
            alert(data.message);
            if (data.success) {
                closeModal('lessonModal');
                location.reload();
            }
        })
        .catch(err => alert('Error: ' + err.message));
    });
    
    // Quiz form submission
    document.getElementById('quizForm').addEventListener('submit', function(e) {
        e.preventDefault();
        const formData = new FormData(this);
        const isEdit = document.getElementById('quizId').value !== '';
        formData.append('action', isEdit ? 'update_quiz' : 'create_quiz');
        
        fetch('admin_ajax.php', { method: 'POST', body: formData })
        .then(r => r.json())
        .then(data => {
            alert(data.message);
            if (data.success) {
                closeModal('quizModal');
                location.reload();
            }
        })
        .catch(err => alert('Error: ' + err.message));
    });
    
    // Shop item form submission
    document.getElementById('shopItemForm').addEventListener('submit', function(e) {
        e.preventDefault();
        const formData = new FormData(this);
        const isEdit = document.getElementById('shopItemId').value !== '';
        formData.append('action', isEdit ? 'update_shop_item' : 'create_shop_item');
        
        fetch('admin_ajax.php', { method: 'POST', body: formData })
        .then(r => r.json())
        .then(data => {
            alert(data.message);
            if (data.success) {
                closeModal('shopItemModal');
                location.reload();
            }
        })
        .catch(err => alert('Error: ' + err.message));
    });
    
    // Topic form submission
    document.getElementById('topicForm').addEventListener('submit', function(e) {
        e.preventDefault();
        const formData = new FormData(this);
        const isEdit = document.getElementById('topicIdField').value !== '';
        formData.append('action', isEdit ? 'update_topic' : 'create_topic');
        
        fetch('admin_ajax.php', { method: 'POST', body: formData })
        .then(r => r.json())
        .then(data => {
            alert(data.message);
            if (data.success) {
                closeModal('topicModal');
                location.reload();
            }
        })
        .catch(err => alert('Error: ' + err.message));
    });
    
    // Grant item form submission
    document.getElementById('grantItemForm').addEventListener('submit', function(e) {
        e.preventDefault();
        const formData = new FormData(this);
        formData.append('action', 'grant_item');
        
        fetch('admin_ajax.php', { method: 'POST', body: formData })
        .then(r => r.json())
        .then(data => {
            alert(data.message);
            if (data.success) {
                closeModal('grantItemModal');
                location.reload();
            }
        })
        .catch(err => alert('Error: ' + err.message));
    });
    
    // Grant to user form submission
    document.getElementById('grantToUserForm').addEventListener('submit', function(e) {
        e.preventDefault();
        const formData = new FormData(this);
        formData.append('action', 'grant_item');
        
        fetch('admin_ajax.php', { method: 'POST', body: formData })
        .then(r => r.json())
        .then(data => {
            alert(data.message);
            if (data.success) {
                closeModal('grantToUserModal');
                location.reload();
            }
        })
        .catch(err => alert('Error: ' + err.message));
    });
});


</script>



<?php include 'include/footer.php'; ?>
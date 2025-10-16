// Toggle dropdown visibility
document.addEventListener('DOMContentLoaded', function() {
    const accountDropdownBtn = document.getElementById('accountDropdownBtn');
    if (accountDropdownBtn) {
        accountDropdownBtn.addEventListener('click', function(e) {
            e.stopPropagation();
            const dropdown = document.getElementById('accountDropdown');
            dropdown.classList.toggle('show');
        });
    }

    // Close dropdown when clicking outside
    document.addEventListener('click', function(e) {
        const dropdown = document.getElementById('accountDropdown');
        const accountBtn = document.getElementById('accountDropdownBtn');
        if (dropdown && accountBtn && !dropdown.contains(e.target) && e.target !== accountBtn && !accountBtn.contains(e.target)) {
            dropdown.classList.remove('show');
        }
    });

    // Enable horizontal scrolling with mouse wheel for tabs
    const tabsScroll = document.querySelector('.tabs-scroll');
    
    if (tabsScroll) {
        tabsScroll.addEventListener('wheel', function(e) {
            if (e.deltaY !== 0) {
                if (Math.abs(e.deltaX) < Math.abs(e.deltaY)) {
                    e.preventDefault();
                }
                this.scrollLeft += e.deltaY;
            }
        });
    }
    
    // Initialize the page
    init();
    
    // Make sure the cards container doesn't create a scrollbar
    const cardsContainer = document.querySelector('.cards-container');
    if (cardsContainer) {
        cardsContainer.style.overflowY = 'scroll';
    }

    // Search functionality
    const searchForm = document.getElementById('lessonSearchForm');
    const searchInput = document.getElementById('lessonSearchInput');

    if (searchForm && searchInput) {
        searchForm.addEventListener('submit', function(e) {
            e.preventDefault();
            const query = searchInput.value.trim().toLowerCase();
            searchLessons(query);
        });

        searchInput.addEventListener('input', function() {
            const query = searchInput.value.trim().toLowerCase();
            searchLessons(query);
        });
    }

    // Restore exact state after returning from quiz
    const raw = sessionStorage.getItem('quizRestore');
    if (raw) {
        sessionStorage.removeItem('quizRestore');
        try {
            const r = JSON.parse(raw);
            if (r.path === window.location.pathname) {
                window.scrollTo(0, r.scrollY || 0);
                if (r.reopen?.type === 'materials' && r.reopen.lessonId) {
                    const lesson = (lessons?.all || []).find(L => String(L.id) === String(r.reopen.lessonId));
                    if (lesson) {
                        openMaterialsModal(lesson, r.reopen.materialType || 'video');
                    }
                } else if (r.reopen?.type === 'lesson' && r.reopen.lessonId) {
                    const lesson = (lessons?.all || []).find(L => String(L.id) === String(r.reopen.lessonId));
                    if (lesson) openLessonModal(lesson);
                }
            }
        } catch {}
    }
});

// ---- shared helpers for quiz return ----
function absUrl(path) {
    return window.location.origin + path.replace(/^\//, '');
}

function saveQuizReturnState(reopen) {
    const state = {
        path: window.location.pathname,
        scrollY: window.scrollY,
        reopen,
        ts: Date.now()
    };
    sessionStorage.setItem('quizReturn', JSON.stringify(state));
}

// Persisted enrolled lessons for the current user
const enrolledSet = new Set();

// --- NEW: Restore cached enrollments immediately on page load ---
(function preloadCachedEnrollments() {
  const uid = Number(window.currentUserId);
  const cached = uid ? localStorage.getItem(`enrolled_user_${uid}`) : null;
  if (cached) {
    try {
      JSON.parse(cached).forEach(id => enrolledSet.add(Number(id)));
      console.log('[CACHE] Preloaded enrollments:', [...enrolledSet]);
    } catch (e) {
      console.warn('Invalid cached enrollments:', e);
    }
  }
})();

async function loadEnrollments() {
    try {
        const uid = Number(window.currentUserId);
        const cached = uid ? localStorage.getItem(`enrolled_user_${uid}`) : null;
        if (cached) {
            JSON.parse(cached).forEach(id => enrolledSet.add(Number(id)));
        }

        const res = await fetch('/include/getEnrollments.inc.php', {
        credentials: 'include'
        });
        const data = await res.json();
        if (res.ok && data.ok && Array.isArray(data.lesson_ids)) {
            enrolledSet.clear();
            data.lesson_ids.forEach(id => enrolledSet.add(Number(id)));
            if (uid) localStorage.setItem(`enrolled_user_${uid}`, JSON.stringify([...enrolledSet]));
        }
    } catch (e) {
        console.warn('Could not load enrollments; using cached only.', e);
    }
}

// Search lessons and display results
function searchLessons(query) {
    let filteredLessons;
    if (!query) {
        showLessons(currentCategory);
        return;
    }
    
    filteredLessons = lessons.all.filter(lesson =>
        lesson.title.toLowerCase().includes(query) ||
        (lesson.short_desc && lesson.short_desc.toLowerCase().includes(query)) ||
        (lesson.topic && lesson.topic.toLowerCase().includes(query))
    );

    showFilteredLessons(filteredLessons);
}

// Show filtered lessons (no pagination for search results)
function showFilteredLessons(filteredLessons) {
    const cardsGrid = document.querySelector('.cards-grid');
    if (!cardsGrid) return;
    
    cardsGrid.innerHTML = '';
    if (filteredLessons.length === 0) {
        cardsGrid.innerHTML = '<div class="no-results">No lessons found</div>';
        return;
    }
    filteredLessons.forEach(lesson => {
        const card = document.createElement('div');
        card.className = 'lesson-card';
        card.innerHTML = `
            <div class="card-img">
                ${lesson.icon.startsWith('<') ? lesson.icon : `<i class="${lesson.icon}"></i>`}
            </div>
            <div class="card-content">
                <div class="lesson-title">${lesson.title}</div>
                <div class="labels">
                    <div class="label label-gaku">@${lesson.author_name || 'GakuLesson'}</div>
                    <div class="label label-topic">${lesson.topic}</div>
                </div>
                <div class="lesson-description">${lesson.short_desc}</div>
                <div class="card-meta">
                    <span>${lesson.duration}</span>
                    <span>${lesson.difficulty}</span>
                </div>
            </div>
        `;
        card.addEventListener('click', () => {
            openLessonModal(lesson);
        });
        cardsGrid.appendChild(card);
    });
}

// DOM elements
let tabs, cardsGrid, paginationLinks;

// Current state
let currentCategory = 'all';
let currentPage = 1;
const itemsPerPage = 10;

// Enrollment variables
let currentLesson = null;
let currentMaterialType = null;

// Initialize the page
async function init() {
    // Get DOM elements
    tabs = document.querySelectorAll('.tab');
    cardsGrid = document.querySelector('.cards-grid');
    paginationLinks = document.querySelectorAll('.page-link');
    
    // Add event listeners to tabs
    tabs.forEach(tab => {
        tab.addEventListener('click', () => {
            const category = tab.getAttribute('data-category');
            setActiveTab(tab);
            showLessons(category);
        });
    });
    
    // Add event listeners to pagination
    paginationLinks.forEach(link => {
        link.addEventListener('click', (e) => {
            const action = link.classList.contains('prev') ? 'prev' : 
                          link.classList.contains('next') ? 'next' : 'page';
            
            if (action === 'prev' && currentPage > 1) {
                currentPage--;
                showLessons(currentCategory);
            } else if (action === 'next' && currentPage < getTotalPages()) {
                currentPage++;
                showLessons(currentCategory);
            } else if (action === 'page') {
                currentPage = parseInt(link.textContent);
                showLessons(currentCategory);
            }
            
            updatePagination();
        });
    });
    
    // Load initial lessons
    await loadEnrollments();
    showLessons(currentCategory);
}

// Set active tab
function setActiveTab(activeTab) {
    tabs.forEach(tab => tab.classList.remove('active'));
    activeTab.classList.add('active');
    currentCategory = activeTab.getAttribute('data-category');
    currentPage = 1;
}

// Show lessons for the selected category
function showLessons(category) {
    let categoryLessons;
    if (category === 'all') {
        categoryLessons = lessons.all;
    } else {
        categoryLessons = lessons.all.filter(lesson => {
            const slug = String(lesson.topic || '').toLowerCase().replace(/\s/g, '');
            return slug === category;
        });
    }

    const startIndex = (currentPage - 1) * itemsPerPage;
    const paginatedLessons = categoryLessons.slice(startIndex, startIndex + itemsPerPage);
    
    // Clear the grid
    if (!cardsGrid) return;
    cardsGrid.innerHTML = '';
    
    // Add lessons to the grid
    paginatedLessons.forEach(lesson => {
        const card = document.createElement('div');
        card.className = 'lesson-card';
        card.innerHTML = `
            <div class="card-img">
                ${lesson.icon.startsWith('<') ? lesson.icon : `<i class="${lesson.icon}"></i>`}
            </div>
            <div class="card-content">
                <div class="lesson-title">${lesson.title}</div>
                <div class="labels">
                    <div class="label label-gaku">@${lesson.author_name || 'GakuLesson'}</div>
                    <div class="label label-topic">${lesson.topic}</div>
                </div>
                <div class="lesson-description">${lesson.short_desc}</div>
                <div class="card-meta">
                    <span>${lesson.duration}</span>
                    <span>${lesson.difficulty}</span>
                </div>
            </div>
        `;
        
        // Add click event to open modal
        card.addEventListener('click', () => {
            openLessonModal(lesson);
        });
        
        cardsGrid.appendChild(card);
    });
    
    // Update pagination
    updatePagination();
}

// Get total pages for current category
function getTotalPages() {
    let categoryLessons;
    if (currentCategory === 'all') {
        categoryLessons = lessons.all;
    } else {
        categoryLessons = lessons.all.filter(lesson => {
            const slug = String(lesson.topic || '').toLowerCase().replace(/\s/g, '');
            return slug === currentCategory;
        });
    }
    return Math.ceil(categoryLessons.length / itemsPerPage);
}

// Update pagination UI
function updatePagination() {
    const totalPages = getTotalPages();
    const pageItems = document.querySelectorAll('.page-item');
    
    if (pageItems.length === 0) return;
    
    // Hide all page number items first
    pageItems.forEach(item => {
        if (!item.querySelector('.prev') && !item.querySelector('.next')) {
            item.style.display = 'none';
        }
    });
    
    // Show only necessary pages
    let startPage = Math.max(1, currentPage - 1);
    let endPage = Math.min(totalPages, startPage + 2);
    
    if (endPage - startPage < 2) {
        startPage = Math.max(1, endPage - 2);
    }
    
    let pageIndex = 0;
    for (let i = startPage; i <= endPage; i++) {
        if (pageItems[pageIndex + 1]) {
            pageItems[pageIndex + 1].style.display = 'block';
            const pageLink = pageItems[pageIndex + 1].querySelector('.page-link');
            pageLink.textContent = i;
            pageLink.classList.toggle('active', i === currentPage);
            pageIndex++;
        }
    }
    
    // Update prev/next buttons state
    const prevBtn = document.querySelector('.prev');
    const nextBtn = document.querySelector('.next');
    
    if (prevBtn) {
        prevBtn.parentElement.style.display = 'block';
        prevBtn.style.opacity = currentPage > 1 ? '1' : '0.5';
    }
    
    if (nextBtn) {
        nextBtn.parentElement.style.display = 'block';
        nextBtn.style.opacity = currentPage < totalPages ? '1' : '0.5';
    }
}

// --- Objectives renderer ---
function renderObjectives(list) {
    if (!Array.isArray(list) || list.length === 0) return '<em>No objectives provided yet.</em>';
    return '<ul>' + list.map(t => `<li>${t}</li>`).join('') + '</ul>';
}

// Function to open lesson modal
function openLessonModal(lesson) {
    const modal = document.getElementById('lessonModal');
    const modalBody = document.querySelector('.modal-lesson-content');
    
    if (!modal || !modalBody) return;
    
    // Populate modal content
    modalBody.innerHTML = `
        <div class="modal-lesson-header">
            <div class="cardLesson-title">${lesson.title}</div>
            <div class="labels">
                <div class="cardLabel cardLabel-gaku">GakuLesson</div>
                <div class="cardLabel cardLabel-topic">${lesson.topic}</div>
            </div>
        
            <div class="modal-meta">
                <span><i class="fas fa-clock"></i> ${lesson.duration}</span>
                <span><i class="fas fa-signal"></i> ${lesson.difficulty}</span>
            </div>
        </div>
        <div class="modal-lesson-description">
            <div class="cardLesson-description">${lesson.long_desc}</div>
        </div>
        <div class="modal-lesson-objectives">
            <div class="cardObjectives">Learning Objectives</div>
            ${renderObjectives(lesson.objectives || [])}
        </div>

        
        <div class="folders-container">
            <div class="folder-wrapper" data-type="video">
                <div class="folder">
                    <div class="folder-back"></div>
                    <div class="folder-front"><i class="fas fa-video"></i></div>
                    <div class="folder-tab"></div>
                </div>
                <div class="folder-name">Video Lectures</div>
            </div>
            
            <div class="folder-wrapper" data-type="notes">
                <div class="folder">
                    <div class="folder-back"></div>
                    <div class="folder-front"><i class="fas fa-file-alt"></i></div>
                    <div class="folder-tab"></div>
                </div>
                <div class="folder-name">Notes Lecture</div>
            </div>
        </div>
    `;
    
    // Show the modal with animation
    modal.classList.add('active');
    document.body.style.overflow = 'hidden';
    
    // Add event listeners to close buttons
    const closeButtons = document.querySelectorAll('.custom-modal-close, .custom-modal-close-btn');
    closeButtons.forEach(button => {
        button.addEventListener('click', closeLessonModal);
    });
    
    // Add event listener to the Start Lesson button
    const startLessonBtn = document.querySelector('.start-lesson-btn');
    if (startLessonBtn) {
        startLessonBtn.onclick = async function() {
            const ok = await saveEnrollment(lesson.id);
            if (ok) {
                showEnrollmentSuccess();
            }
        };
    }

    // Fetch objectives
    const lessonId = Number(lesson.id ?? lesson.lesson_id);
    if (!Number.isInteger(lessonId) || lessonId <= 0) {
        console.error('Missing/invalid lesson_id for objectives:', lesson);
    } else {
        fetch(`/include/lessonObjectives.inc.php?lesson_id=${lessonId}`)
            .then(r => r.ok ? r.json() : Promise.reject(r))
            .then(data => {
                if (data?.ok) {
                    lesson.objectives = data.objectives.map(o => o.objective_text);
                    const box = document.querySelector('#lessonModal .modal-lesson-objectives');
                    if (box) {
                        box.innerHTML = `
                            <div class="cardObjectives">Learning Objectives</div>
                            ${renderObjectives(lesson.objectives)}
                        `;
                    }
                } else {
                    console.warn('Objectives fetch failed:', data);
                }
            })
            .catch(err => console.error('Objectives fetch error', err));
    }
    
    // Close modal when clicking on backdrop
    const backdrop = document.querySelector('.custom-modal-backdrop');
    if (backdrop) {
        backdrop.addEventListener('click', closeLessonModal);
    }
    
    // Add click events to folders
    const videoFolder = document.querySelector('.folder-wrapper[data-type="video"]');
    const notesFolder = document.querySelector('.folder-wrapper[data-type="notes"]');
    
    if (videoFolder) {
        videoFolder.addEventListener('click', () => {
            checkEnrollmentBeforeAccess(lesson, 'video');
        });
    }
    
    if (notesFolder) {
        notesFolder.addEventListener('click', () => {
            checkEnrollmentBeforeAccess(lesson, 'notes');
        });
    }
}

// Function to close the modal
function closeLessonModal() {
    const modal = document.getElementById('lessonModal');
    if (modal) {
        modal.classList.remove('active');
        document.body.style.overflow = '';
    }
    
    // Remove event listeners
    const closeButtons = document.querySelectorAll('.custom-modal-close, .custom-modal-close-btn');
    closeButtons.forEach(button => {
        button.removeEventListener('click', closeLessonModal);
    });
    
    const backdrop = document.querySelector('.custom-modal-backdrop');
    if (backdrop) {
        backdrop.removeEventListener('click', closeLessonModal);
    }
}

// ENROLLMENT FUNCTIONS
function checkEnrollmentBeforeAccess(lesson, materialType) {
    currentLesson = lesson;
    currentMaterialType = materialType;

    // Handle both array and object lesson structures
    let lessonKey = null;

    if (Array.isArray(lesson)) {
    // assume the first index is lesson_id
    lessonKey = Number(lesson[0]);
    } else {
    lessonKey = Number(lesson.lesson_id ?? lesson.id);
    }

    const isEnrolled = enrolledSet.has(lessonKey);

    console.log('[CHECK] lessonKey:', lessonKey, 'enrolledSet:', [...enrolledSet], '=>', isEnrolled);

    if (isEnrolled) {
        openMaterialsModal(lesson, materialType);
    } else {
        showEnrollmentPrompt();
    }
}

function showEnrollmentPrompt() {
    const modal = document.getElementById('enrollmentModal');
    if (!modal) return;
    
    modal.classList.add('active');
    document.body.style.overflow = 'hidden';
    
    // Add event listeners to buttons
    const confirmBtn = document.querySelector('.enroll-confirm-btn');
    const cancelBtn = document.querySelector('.enroll-cancel-btn');
    
    if (confirmBtn) confirmBtn.onclick = confirmEnrollment;
    if (cancelBtn) cancelBtn.onclick = closeEnrollmentModal;
    
    // Close modal when clicking on backdrop
    const backdrop = modal.querySelector('.custom-modal-backdrop');
    if (backdrop) {
        backdrop.addEventListener('click', closeEnrollmentModal);
    }
}

function closeEnrollmentModal() {
    const modal = document.getElementById('enrollmentModal');
    if (modal) {
        modal.classList.remove('active');
        document.body.style.overflow = '';
    }
    
    // Remove event listeners
    const confirmBtn = document.querySelector('.enroll-confirm-btn');
    const cancelBtn = document.querySelector('.enroll-cancel-btn');
    
    if (confirmBtn) confirmBtn.onclick = null;
    if (cancelBtn) cancelBtn.onclick = null;
    
    const backdrop = document.querySelector('.custom-modal-backdrop');
    if (backdrop) {
        backdrop.removeEventListener('click', closeEnrollmentModal);
    }
}

async function confirmEnrollment() {
    closeEnrollmentModal();

    const ok = await saveEnrollment(currentLesson.id);
    if (!ok) return;

    // Mark enrolled locally
    enrolledSet.add(Number(currentLesson.id));

    const uid = Number(window.currentUserId);
    if (uid) {
        localStorage.setItem(`enrolled_user_${uid}`, JSON.stringify([...enrolledSet]));
    }

    // Success UX
    showEnrollmentSuccess();
}

function showEnrollmentSuccess() {
    const modal = document.getElementById('enrollmentSuccessModal');
    if (!modal) return;
    
    modal.classList.add('active');
    document.body.style.overflow = 'hidden';
    
    // Add event listener to OK button
    const okBtn = document.querySelector('.success-ok-btn');
    if (okBtn) {
        okBtn.onclick = function() {
            closeEnrollmentSuccessModal();
            // Now open the materials modal since user is enrolled
            openMaterialsModal(currentLesson, currentMaterialType);
        };
    }
    
    // Close modal when clicking on backdrop
    const backdrop = modal.querySelector('.custom-modal-backdrop');
    if (backdrop) {
        backdrop.addEventListener('click', closeEnrollmentSuccessModal);
    }
}

function closeEnrollmentSuccessModal() {
    const modal = document.getElementById('enrollmentSuccessModal');
    if (modal) {
        modal.classList.remove('active');
        document.body.style.overflow = '';
    }
    
    // Remove event listeners
    const okBtn = document.querySelector('.success-ok-btn');
    if (okBtn) okBtn.onclick = null;
    
    const backdrop = document.querySelector('.custom-modal-backdrop');
    if (backdrop) {
        backdrop.removeEventListener('click', closeEnrollmentSuccessModal);
    }
}

// === Enrollment save helper (patched to open Subscribe Modal on premium-required) ===
async function saveEnrollment(lessonId) {
  const form = new FormData();
  form.append('lesson_id', lessonId);

  try {
    const res = await fetch('include/enrollLesson.inc.php', {
      method: 'POST',
      body: form,
      credentials: 'same-origin'
    });

    // Try parsing JSON, but don't crash if server sent no/invalid JSON
    let data = null;
    try { data = await res.json(); } catch {}

    // Blocked by server (e.g., Free user trying to enroll in Gakusensei lesson)
    if (!res.ok || !data?.ok) {
      const apiMsg = (data && (data.error || data.message)) ? String(data.error || data.message) : '';

      // If server says premium/subscription required (or 403), show Subscribe Modal instead of alert
      if (res.status === 403 || /premium|subscribe/i.test(apiMsg)) {
        if (typeof window.openSubscribeModal === 'function') {
          window.openSubscribeModal();
        } else {
          // Fallback if modal helpers didn’t load for any reason
          alert(apiMsg || 'Premium subscription required for this lesson.');
        }
        return false;
      }

      // Other kinds of errors: fall back to the generic error flow
      throw new Error(apiMsg || 'Enroll failed');
    }

    // Success
    console.log('Enrollment saved:', lessonId);
    return true;

  } catch (err) {
    console.error('Enrollment error:', err);
    const msg = (err && err.message) ? String(err.message) : '';

    // If the thrown error mentions premium/subscribe, route to modal as well
    if (/premium|subscribe/i.test(msg)) {
      if (typeof window.openSubscribeModal === 'function') {
        window.openSubscribeModal();
      } else {
        alert(msg);
      }
      return false;
    }

    // Generic fallback
    alert('Could not enroll. Please try again.');
    return false;
  }
}


// Function to open materials modal
function openMaterialsModal(lesson, materialType) {
    // Close the lesson modal first
    closeLessonModal();
    
    // Populate the materials modal with lesson info
    const materialsIcon = document.getElementById('materialsIcon');
    const materialsTitle = document.getElementById('materialsTitle');
    const materialsTopic = document.getElementById('materialsTopic');
    const materialsDuration = document.getElementById('materialsDuration');
    const materialsDifficulty = document.getElementById('materialsDifficulty');
    const materialsTypeHeader = document.getElementById('materialsTypeHeader');
    
    if (materialsIcon) materialsIcon.className = lesson.icon;
    if (materialsTitle) materialsTitle.textContent = lesson.title;
    if (materialsTopic) materialsTopic.textContent = lesson.topic;
    if (materialsDuration) materialsDuration.textContent = lesson.duration;
    if (materialsDifficulty) materialsDifficulty.textContent = lesson.difficulty;
    if (materialsTypeHeader) materialsTypeHeader.textContent = 
        materialType === 'video' ? 'Video Lectures' : 'Notes Lecture';
    
    // Check if lesson.files exists and filter files by type
    const files = lesson.files ? lesson.files.filter(file => 
        materialType === 'video' ? file.file_type === 'Video' : file.file_type === 'Notes'
    ) : [];

    // Populate files list
    const materialsList = document.getElementById('materialsList');
    if (materialsList) {
        materialsList.innerHTML = '';
        
        if (!files || files.length === 0) {
            materialsList.innerHTML = '<div class="no-materials">No materials available</div>';
        } else {
            files.forEach(file => {
                const fileExt = file.file_url.split('.').pop().toLowerCase();
                let iconClass = 'fa-file';
                
                // Set icon based on file type
                if (fileExt === 'pdf') iconClass = 'fa-file-pdf';
                else if (['mp4', 'mov', 'avi', 'wmv'].includes(fileExt)) iconClass = 'fa-file-video';
                else if (['doc', 'docx'].includes(fileExt)) iconClass = 'fa-file-word';
                
                const materialItem = document.createElement('div');
                materialItem.className = 'material-item';
                materialItem.innerHTML = `
                    <div class="material-icon"><i class="fas ${iconClass}"></i></div>
                    <div class="material-name">${file.file_url.split('/').pop()}</div>
                    <button class="material-action" onclick="viewMaterial('${file.file_url}', '${fileExt}')">View</button>
                `;
                materialsList.appendChild(materialItem);
            });
        }
    }

    // Add event listeners
    const backButton = document.getElementById('backToLessonModal');
    if (backButton) {
        backButton.addEventListener('click', () => {
            closeMaterialsModal();
            setTimeout(() => {
                openLessonModal(lesson);
            }, 50);
        });
    }

    // Set quiz link
    const quizLink = document.getElementById('take-quiz-link');
    if (quizLink) {
        const origin = window.location.pathname;
        quizLink.setAttribute(
            'href',
            absUrl(`quiz.php?lesson_id=${lesson.id}&from=${encodeURIComponent(origin)}`)
        );

        // Save state before navigating so we can restore the page exactly
        quizLink.addEventListener('click', () => {
            saveQuizReturnState({ type: 'materials', lessonId: lesson.id, materialType });
        }, { once: true });
    }

    // Show the materials modal
    const materialsModal = document.getElementById('materialsModal');
    if (materialsModal) {
        materialsModal.classList.add('active');
    }
}

// Function to close materials modal
function closeMaterialsModal() {
    const materialsModal = document.getElementById('materialsModal');
    if (materialsModal) {
        materialsModal.classList.remove('active');
    }
    
    // Remove event listeners
    const backButton = document.getElementById('backToLessonModal');
    if (backButton) {
        backButton.removeEventListener('click', () => {});
    }
}

// Function to view material
function viewMaterial(url, fileType) {
    // Construct the full URL if it's a relative path
    const baseUrl = window.location.origin;
    const fullUrl = url.startsWith('http') ? url : baseUrl + url;
    
    console.log('Opening file:', fullUrl);
    
    if (['mp4', 'mov', 'avi', 'wmv'].includes(fileType)) {
        window.open(fullUrl, '_blank');
    } else if (fileType === 'pdf') {
        window.open(fullUrl, '_blank');
    } else {
        window.open(fullUrl, '_blank');
    }
}

// Close modal with Escape key
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        if (document.getElementById('enrollmentModal')?.classList.contains('active')) {
            closeEnrollmentModal();
        } else if (document.getElementById('enrollmentSuccessModal')?.classList.contains('active')) {
            closeEnrollmentSuccessModal();
        } else if (document.getElementById('materialsModal')?.classList.contains('active')) {
            closeMaterialsModal();
        } else if (document.getElementById('lessonModal')?.classList.contains('active')) {
            closeLessonModal();
        }
    }
});

// Gakusensei Bank Modal functionality
document.addEventListener('DOMContentLoaded', function() {
    const gakusenseiModal = document.getElementById('gakusenseiBankModal');
    
    if (gakusenseiModal) {
        // Show modal INSTANTLY without delay
        gakusenseiModal.style.display = 'block';
        
        // Add backdrop effect
        const backdrop = document.createElement('div');
        backdrop.className = 'gakusensei-modal-backdrop';
        backdrop.style.cssText = `
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.5);
            z-index: 1040;
            pointer-events: none;
        `;
        document.body.appendChild(backdrop);
        
        // Close modal functionality
        const closeModal = function() {
            gakusenseiModal.style.display = 'none';
            const backdrop = document.querySelector('.gakusensei-modal-backdrop');
            if (backdrop) {
                backdrop.remove();
            }
        };
        
        // Save bank info
        const saveBankInfoBtn = document.getElementById('save-bank-info-btn');
        if (saveBankInfoBtn) {
            saveBankInfoBtn.addEventListener('click', function() {
                const form = document.forms['gakusenseiBankForm'];
                if (form.checkValidity()) {
                    form.submit();
                    closeModal();
                } else {
                    form.reportValidity();
                }
            });
        }
        
        // Remind later button
        const remindLaterBtn = document.getElementById('remind-later-btn');
        if (remindLaterBtn) {
            remindLaterBtn.addEventListener('click', closeModal);
        }
        
        // Close button
        const closeButton = document.querySelector('.gakusensei-modal-close');
        if (closeButton) {
            closeButton.addEventListener('click', closeModal);
        }
    }
});

/* ============================================================
   [APPEND-ONLY] Redirect specific alert(...) messages to
   the Subscribe Modal on mobile.
   Matches your markup (#subscribeModal + .custom-modal-backdrop).
   ============================================================ */

/* Modal helpers aligned to your HTML (only define if missing) */
if (typeof window.openSubscribeModal !== 'function') {
  window.openSubscribeModal = function () {
    const modal = document.getElementById('subscribeModal');
    if (!modal) { console.warn('[subscribe] #subscribeModal not found'); return; }
    // Support both class- and style-based show logic
    modal.classList.add('active', 'show');
    modal.style.display = 'block';
    document.body.style.overflow = 'hidden';

    const backdrop = modal.querySelector('.custom-modal-backdrop');
    if (backdrop) {
      const onBackdrop = (e) => { if (e.target === e.currentTarget) window.closeSubscribeModal(); };
      backdrop.addEventListener('click', onBackdrop, { once: true });
    }
  };
}
if (typeof window.closeSubscribeModal !== 'function') {
  window.closeSubscribeModal = function (/*btn*/) {
    const modal = document.getElementById('subscribeModal');
    if (!modal) return;
    modal.classList.remove('active', 'show');
    modal.style.display = 'none';
    document.body.style.overflow = '';
  };
}

/* Intercept alert(...) and show the modal for premium/subscribe messages */
(function () {
  const nativeAlert = window.alert;
  window.alert = function (...args) {
    const msg = (args[0] ?? '').toString();

    // Match the messages you showed in the logs / common variants.
    const shouldOpenModal =
      /premium subscription required/i.test(msg) ||
      /subscribe/i.test(msg) ||
      /limit.*(reached|exceeded)/i.test(msg) ||
      /gakusensei/i.test(msg);

    if (shouldOpenModal && document.getElementById('subscribeModal')) {
      // Show your Subscribe Modal instead of the browser alert
      try { window.openSubscribeModal(); return; } catch (_) {}
    }

    // Fallback to the original alert for other messages
    return nativeAlert.apply(window, args);
  };
})();

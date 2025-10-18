// Toggle dropdown visibility
document.getElementById('accountDropdownBtn').addEventListener('click', function(e) {
    e.stopPropagation();
    const dropdown = document.getElementById('accountDropdown');
    dropdown.classList.toggle('show');
});

// Close dropdown when clicking outside
document.addEventListener('click', function(e) {
    const dropdown = document.getElementById('accountDropdown');
    const accountBtn = document.getElementById('accountDropdownBtn');
    if (!dropdown.contains(e.target) && e.target !== accountBtn && !accountBtn.contains(e.target)) {
        dropdown.classList.remove('show');
    }
});

// ---- shared helpers for quiz return ----
function absUrl(path) {
  return window.location.origin + (path.startsWith('/') ? path : '/' + path);
}
function saveQuizReturnState(reopen) {
  const state = {
    path: window.location.pathname,
    scrollY: window.scrollY,
    reopen,                // { type: 'materials'|'lesson', lessonId, materialType? }
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
    // If you already expose the user id in a global, you can also prime from localStorage:
    const uid = Number(window.currentUserId);
    const cached = uid ? localStorage.getItem(`enrolled_user_${uid}`) : null;
    if (cached) {
      JSON.parse(cached).forEach(id => enrolledSet.add(Number(id)));
    }

    // Then confirm with the server (source of truth)
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


// Enable horizontal scrolling with mouse wheel for tabs
document.addEventListener('DOMContentLoaded', function() {
    const tabsScroll = document.querySelector('.tabs-scroll');
    
    // Enable horizontal scrolling with mouse wheel
    if (tabsScroll) {
        tabsScroll.addEventListener('wheel', function(e) {
            if (e.deltaY !== 0) {
                // Prevent vertical scrolling only if we're scrolling horizontally
                if (Math.abs(e.deltaX) < Math.abs(e.deltaY)) {
                    e.preventDefault();
                }
                // Scroll horizontally
                this.scrollLeft += e.deltaY;
            }
        });
    }
    
    // Initialize the page
    init();
    
    // Make sure the cards container doesn't create a scrollbar
    const cardsContainer = document.querySelector('.cards-container');
    if (cardsContainer) {
        cardsContainer.style.overflowY = 'hidden';
    }

    // Search functionality
    const searchForm = document.getElementById('lessonSearchForm');
    const searchInput = document.getElementById('lessonSearchInput');

    if (searchForm && searchInput) {
        // Search and filter lessons even when entering or clicking the search button WITHOUT redirecting
        searchForm.addEventListener('submit', function(e) {
        e.preventDefault();
        const query = searchInput.value.trim().toLowerCase();
        searchLessons(query); // Filter and show lessons without redirect
        });

        // Search function with redirection (CAN REMOVE IF NOT NEEDED ^_^)
        // searchForm.addEventListener('submit', function(e) {
        //     e.preventDefault();
        //     const query = searchInput.value.trim();
        //     if (query) {
        //         // Redirect to search results page with query parameter
        //         window.location.href = `searchResults.php?query=${encodeURIComponent(query)}`;
        //     }
        // });

        // Optional
        searchInput.addEventListener('input', function() {
            const query = searchInput.value.trim().toLowerCase();
            searchLessons(query);
        });
    }

    // Restore exact state after returning from quiz
    const raw = sessionStorage.getItem('quizRestore');
    if (raw) {
        sessionStorage.removeItem('quizRestore'); // consume once
        try {
        const r = JSON.parse(raw);
        if (r.path === window.location.pathname) {
            // Scroll first
            window.scrollTo(0, r.scrollY || 0);
            // Reopen the exact modal
            if (r.reopen?.type === 'materials' && r.reopen.lessonId) {
            const lesson = (lessons?.all || []).find(L => String(L.id) === String(r.reopen.lessonId));
            if (lesson) {
                openMaterialsModal(lesson, r.reopen.materialType || 'video'); // uses your existing function
            }
            } else if (r.reopen?.type === 'lesson' && r.reopen.lessonId) {
            const lesson = (lessons?.all || []).find(L => String(L.id) === String(r.reopen.lessonId));
            if (lesson) openLessonModal(lesson); // existing function
            }
        }
        } catch {}
    }
});

// Search lessons and display results
function searchLessons(query) {
    let filteredLessons;
    if (!query) {
        // If search is empty, show all lessons in current category
        showLessons(currentCategory);
        return;
    }
    // Search in all lessons (title + description)
    filteredLessons = lessons.all.filter(lesson =>
        lesson.title.toLowerCase().includes(query) ||
        (lesson.short_desc && lesson.short_desc.toLowerCase().includes(query)) ||
        (lesson.topic && lesson.topic.toLowerCase().includes(query))
    );

    // Show filtered lessons
    showFilteredLessons(filteredLessons);
}

// Show filtered lessons (no pagination for search results)
function showFilteredLessons(filteredLessons) {
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
                ${lesson.icon}
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
const tabs = document.querySelectorAll('.tab');
const cardsGrid = document.querySelector('.cards-grid');
const paginationLinks = document.querySelectorAll('.page-link');

// Current state
let currentCategory = 'all';
let currentPage = 1;
const itemsPerPage = 9;

// Enrollment variables
let currentLesson = null;
let currentMaterialType = null;

// Initialize the page
async function init() {
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
    await loadEnrollments();           // ⬅️ add this
    showLessons(currentCategory);      // already present
}

// Set active tab
function setActiveTab(activeTab) {
    tabs.forEach(tab => tab.classList.remove('active'));
    activeTab.classList.add('active');
    currentCategory = activeTab.getAttribute('data-category');
    currentPage = 1; // Reset to first page when changing category
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
    cardsGrid.innerHTML = '';
    
    // Add lessons to the grid
    paginatedLessons.forEach(lesson => {
        const card = document.createElement('div');
        card.className = 'lesson-card';
        const author = lesson.author_name || 'GakuLesson';
        const hasAuthor = lesson.author_name && lesson.author_name !== 'GakuLesson'; // Check if real author exists
       
        card.innerHTML = `
            <div class="card-img">
                ${lesson.icon}
            </div>
            <div class="card-content">
                <div class="lesson-title-container">
                    <div class="lesson-title">${lesson.title}</div>
                    ${hasAuthor ? '<i class="bi bi-star-fill card-star"></i>' : ''} <!-- Star beside title -->
                </div>
                <div class="labels">
                    <div class="cardLabel cardLabel-gaku">@${author}</div>
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
    document.querySelector('.prev').parentElement.style.display = 'block';
    document.querySelector('.next').parentElement.style.display = 'block';
    
    document.querySelector('.prev').style.opacity = currentPage > 1 ? '1' : '0.5';
    document.querySelector('.next').style.opacity = currentPage < totalPages ? '1' : '0.5';
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
            ${renderObjectives(lesson.objectives)}
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
        if (requiresPremiumSubscription(lesson)) {
            showPremiumRequiredModal();
            return;
        }
        const ok = await saveEnrollment(lesson.id);
        if (ok) {
            // Close modal when clicking on backdrop
            const backdrop = modal.querySelector('.custom-modal-backdrop');
            showEnrollmentSuccess();
            
            backdrop.addEventListener('click', closeEnrollmentModal);
        }
        };
    }

    // For objectives
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

    // after a successful enrollment:
    // enrolledSet.add(Number(lesson.id));
    // const uid = Number(window.currentUserId);
    // if (uid) localStorage.setItem(`enrolled_user_${uid}`, JSON.stringify([...enrolledSet]));
    
    // Close modal when clicking on backdrop
    const backdrop = document.querySelector('.custom-modal-backdrop');
    backdrop.addEventListener('click', closeLessonModal);
    
    // Add click events to folders - MODIFIED FOR ENROLLMENT CHECK
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
    modal.classList.remove('active');
    document.body.style.overflow = ''; // Re-enable scrolling
    
    // Remove event listeners to prevent memory leaks
    const closeButtons = document.querySelectorAll('.custom-modal-close, .custom-modal-close-btn');
    closeButtons.forEach(button => {
        button.removeEventListener('click', closeLessonModal);
    });
    
    const backdrop = document.querySelector('.custom-modal-backdrop');
    backdrop.removeEventListener('click', closeLessonModal);
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
    modal.classList.add('active');
    document.body.style.overflow = 'hidden';
    
    // Add event listeners to buttons
    document.querySelector('.enroll-confirm-btn').onclick = confirmEnrollment;
    document.querySelector('.enroll-cancel-btn').onclick = closeEnrollmentModal;
    
    // Close modal when clicking on backdrop
    const backdrop = modal.querySelector('.custom-modal-backdrop');
    backdrop.addEventListener('click', closeEnrollmentModal);
}

function closeEnrollmentModal() {
    const modal = document.getElementById('enrollmentModal');
    modal.classList.remove('active');
    document.body.style.overflow = '';
    
    // Remove event listeners
    document.querySelector('.enroll-confirm-btn').onclick = null;
    document.querySelector('.enroll-cancel-btn').onclick = null;
    
    const backdrop = modal.querySelector('.custom-modal-backdrop');
    backdrop.removeEventListener('click', closeEnrollmentModal);
}

async function confirmEnrollment() {
  closeEnrollmentModal();

  const ok = await saveEnrollment(currentLesson.id); // calls your PHP to persist
  if (!ok) return; // bail on error

  // Mark enrolled locally
  enrolledSet.add(Number(currentLesson.id));

  const uid = Number(window.currentUserId);
  if (uid) {
    localStorage.setItem(`enrolled_user_${uid}`, JSON.stringify([...enrolledSet]));
  }

  // Success UX (unchanged)
  showEnrollmentSuccess();
}

function showEnrollmentSuccess() {
    const modal = document.getElementById('enrollmentSuccessModal');
    modal.classList.add('active');
    document.body.style.overflow = 'hidden';
    
    // Add event listener to OK button
    document.querySelector('.success-ok-btn').onclick = function() {
        closeEnrollmentSuccessModal();
        // Now open the materials modal since user is enrolled
        openMaterialsModal(currentLesson, currentMaterialType);
    };
    
    // Close modal when clicking on backdrop
    const backdrop = modal.querySelector('.custom-modal-backdrop');
    backdrop.addEventListener('click', closeEnrollmentSuccessModal);
}

function closeEnrollmentSuccessModal() {
    const modal = document.getElementById('enrollmentSuccessModal');
    modal.classList.remove('active');
    document.body.style.overflow = '';
    
    // Remove event listeners
    document.querySelector('.success-ok-btn').onclick = null;
    
    const backdrop = modal.querySelector('.custom-modal-backdrop');
    backdrop.removeEventListener('click', closeEnrollmentSuccessModal);
}
function requiresPremiumSubscription(lesson) {
    if (currentUserRole === 'Gakusei' && !isUserPremium) {
        return lesson.author_id !== null && lesson.author_id !== undefined;
    }
    return false;
}

function showPremiumRequiredModal() {
    const modal = document.getElementById('premiumRequiredModal');
    modal.classList.add('active');
    document.body.style.overflow = 'hidden';
    
    document.getElementById('avail-premium-btn').onclick = function() {
        window.location.href = 'premium_subscription.php';
    };
    
    document.getElementById('premium-cancel-btn').onclick = closePremiumRequiredModal;
    
    const backdrop = modal.querySelector('.custom-modal-backdrop');
    backdrop.addEventListener('click', closePremiumRequiredModal);
}

function closePremiumRequiredModal() {
    const modal = document.getElementById('premiumRequiredModal');
    modal.classList.remove('active');
    document.body.style.overflow = '';
}

// === Enrollment save helper ===
async function saveEnrollment(lessonId) {
  const form = new FormData();
  form.append('lesson_id', lessonId);

  try {
    const res = await fetch('include/enrollLesson.inc.php', {
      method: 'POST',
      body: form,
      credentials: 'same-origin'
    });
    const data = await res.json();
    
    if (!res.ok || !data.ok) {
      // Check if it's a premium error
      if (data.error && data.error.includes('Premium')) {
        showPremiumRequiredModal();
        return false;
      }
      // For other errors, still throw but we'll handle differently
      throw new Error(data.error || 'Enroll failed');
    }
    
    console.log('Enrollment saved:', lessonId);
    return true;
  } catch (err) {
    console.error('Enrollment error:', err);
    // If we get here with a premium error, show modal instead of alert
    if (err.message && err.message.includes('Premium')) {
      showPremiumRequiredModal();
      return false;
    }
    // REPLACED ALERT WITH MODAL - Show modal for all other enrollment errors
    showPremiumRequiredModal();
    return false;
  }
}

// Function to open materials modal
function openMaterialsModal(lesson, materialType) {
    // Close the lesson modal first
    closeLessonModal();
    
    // Populate the materials modal with lesson info
    document.getElementById('materialsIcon').className = lesson.icon;
    document.getElementById('materialsTitle').textContent = lesson.title;
    document.getElementById('materialsTopic').textContent = lesson.topic;
    document.getElementById('materialsDuration').textContent = lesson.duration;
    document.getElementById('materialsDifficulty').textContent = lesson.difficulty;
    document.getElementById('materialsTypeHeader').textContent = 
        materialType === 'video' ? 'Video Lectures' : 'Notes Lecture';
    
    // Check if lesson.files exists and filter files by type
    const files = lesson.files ? lesson.files.filter(file => 
        materialType === 'video' ? file.file_type === 'Video' : file.file_type === 'Notes'
    ) : [];

    // Populate files list
    const materialsList = document.getElementById('materialsList');
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

    // Add event listeners
    document.getElementById('backToLessonModal').addEventListener('click', () => {
        closeMaterialsModal();
        setTimeout(() => {
            openLessonModal(lesson); // Reopen the first modal after a brief delay
        }, 50);
    });

    // Try quizLink
    const quizLink = document.getElementById('take-quiz-link');
    if (quizLink) {
    const origin = window.location.pathname; //
    quizLink.setAttribute(
        'href',
        absUrl(`/quiz.php?lesson_id=${lesson.id}&from=${encodeURIComponent(origin)}`)
    );

    // Save state before navigating so we can restore the page exactly
    quizLink.addEventListener('click', () => {
        saveQuizReturnState({ type: 'materials', lessonId: lesson.id, materialType });
    }, { once: true });
    }


    // // ✅ set the quiz link for this lesson (the anchor is in the materials modal footer)
    // const quizLink = document.getElementById('take-quiz-link');
    // if (quizLink) {
    // quizLink.setAttribute('href', `/quiz.php?lesson_id=${lesson.id}`);

    // // If any global handler might block anchors, force navigation:
    // quizLink.addEventListener('click', function (e) {
    //     // If you suspect preventDefault elsewhere, uncomment the next two lines:
    //     // e.preventDefault();
    //     // window.location.href = `/quiz.php?lesson_id=${lesson.id}`;
    // }, { once: true });
    // }


    // Show the materials modal
    document.getElementById('materialsModal').classList.add('active');
}

// Function to close materials modal
function closeMaterialsModal() {
    document.getElementById('materialsModal').classList.remove('active');
    
    // Remove event listeners
    document.getElementById('backToLessonModal').removeEventListener('click', () => {});
}

// Function to view material
function viewMaterial(url, fileType) {
    // Construct the full URL if it's a relative path
    const baseUrl = window.location.origin;
    const fullUrl = url.startsWith('http') ? url : baseUrl + url;
    
    console.log('Opening file:', fullUrl); // Debug log
    
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
        if (document.getElementById('enrollmentModal').classList.contains('active')) {
            closeEnrollmentModal();
        } else if (document.getElementById('enrollmentSuccessModal').classList.contains('active')) {
            closeEnrollmentSuccessModal();
        } else if (document.getElementById('materialsModal').classList.contains('active')) {
            closeMaterialsModal();
        } else if (document.getElementById('lessonModal').classList.contains('active')) {
            closeLessonModal();
        }
    }
});

// Gakusensei Bank Modal functionality — show ONCE per user after approval
document.addEventListener('DOMContentLoaded', function () {
  const gakusenseiModal = document.getElementById('gakusenseiBankModal');
  if (!gakusenseiModal) return; // not a Gakusensei or no modal on this page

  // Per-user "done" flag (no backend change needed)
  const uid = Number(window.currentUserId || 0);
  const DONE_KEY = uid ? `gaku_bank_done_${uid}` : 'gaku_bank_done';
  if (localStorage.getItem(DONE_KEY) === '1') return; // already completed -> never show again

  // Ensure the modal sits ABOVE the backdrop (Bootstrap modals use ~1050+)
  gakusenseiModal.style.display = 'block';
  gakusenseiModal.style.position = 'fixed';
  gakusenseiModal.style.zIndex = '1055'; // above backdrop
  // Optional: center if your CSS doesn't already do it
  gakusenseiModal.style.left = gakusenseiModal.style.left || '50%';
  gakusenseiModal.style.top  = gakusenseiModal.style.top  || '50%';
  gakusenseiModal.style.transform = gakusenseiModal.style.transform || 'translate(-50%, -50%)';

  // Backdrop (kept non-clickable so outside click won't close it)
  const backdrop = document.createElement('div');
  backdrop.className = 'gakusensei-modal-backdrop';
  backdrop.style.cssText = `
    position: fixed; inset: 0; background: rgba(0,0,0,0.5);
    z-index: 1050; pointer-events: none;
  `;
  document.body.appendChild(backdrop);

  function closeModal() {
    gakusenseiModal.style.display = 'none';
    const b = document.querySelector('.gakusensei-modal-backdrop');
    if (b) b.remove();
    document.body.style.overflow = ''; // in case your CSS locks scroll
  }

  // Save bank info (ONLY set the "done" flag when the form validates & submits)
  const saveBankInfoBtn = document.getElementById('save-bank-info-btn');
  const form = document.forms['gakusenseiBankForm'];
  if (saveBankInfoBtn && form) {
    saveBankInfoBtn.addEventListener('click', function () {
      if (form.checkValidity()) {
        // mark as done so the modal never shows again for this user
        localStorage.setItem(DONE_KEY, '1');
        form.submit(); // preserve your current submit behavior
        closeModal();
      } else {
        form.reportValidity();
      }
    });
  }

  // “Remind later” just closes; DO NOT set the done flag here
  const remindLaterBtn = document.getElementById('remind-later-btn');
  if (remindLaterBtn) remindLaterBtn.addEventListener('click', closeModal);

  // Close (X) button, if present
  const closeButton = document.querySelector('.gakusensei-modal-close');
  if (closeButton) closeButton.addEventListener('click', closeModal);
});


/* ============================================================
   ENROLLMENT GUARD (Homepage) — Free Gakusei vs Gakusensei lessons
   Append-only. Does not modify existing lines; overrides by re-assigning.
   ============================================================ */

/* ——— Safe Subscribe Modal helpers (no-ops if already defined) ——— */
if (typeof openSubscribeModal !== 'function') {
  function openSubscribeModal() {
    const m = document.getElementById('subscribeModal');
    if (!m) { console.warn('subscribeModal not found'); return; }
    m.classList.add('active');
    document.body.style.overflow = 'hidden';
    const backdrop = m.querySelector('.custom-modal-backdrop');
    if (backdrop) {
      const onClick = (e) => { if (e.target === backdrop) closeSubscribeModal(); };
      backdrop.addEventListener('click', onClick, { once: true });
    }
  }
}
if (typeof closeSubscribeModal !== 'function') {
  function closeSubscribeModal() {
    const m = document.getElementById('subscribeModal');
    if (!m) return;
    m.classList.remove('active');
    document.body.style.overflow = '';
  }
}

/* Wire X and Cancel if present (id-based, safe if missing) */
document.addEventListener('DOMContentLoaded', () => {
  const x = document.getElementById('subscribeXBtn');
  const c = document.getElementById('subscribeCancelBtn');
  if (x) x.addEventListener('click', closeSubscribeModal);
  if (c) c.addEventListener('click', closeSubscribeModal);
});

/* ——— Utils to detect roles robustly ——— */
function userIsFreeGakusei() {
  const role = (window.currentUserRole || '').trim();
  const v = window.isUserPremium;
  const isPremium = (v === true) || (v === 1) || (v === '1') || (String(v).toLowerCase() === 'true');
  return role === 'Gakusei' && !isPremium;
}

function lessonCreatedByGakusensei(lesson) {
  if (!lesson || typeof lesson !== 'object') return false;

  // Accept multiple possible field names to avoid touching PHP
  const probe = v => (typeof v === 'string' ? v.trim().toLowerCase() : null);
  const roles = [
    probe(lesson.author_role),
    probe(lesson.authorRole),
    probe(lesson.author_type),
    probe(lesson.authorType),
    probe(lesson.created_by_role),
    probe(lesson.creator_role),
    probe(lesson.creatorRole),
  ];

  if (lesson.author_is_gakusensei === true) return true;
  if (lesson.author_is_gakusensei === false) return false;

  return roles.includes('gakusensei');
}

function mustSubscribeForLesson(lesson) {
  try {
    return userIsFreeGakusei() && lessonCreatedByGakusensei(lesson);
  } catch {
    return false;
  }
}

/* ——— Override the gate used by the Start Lesson button ———
   Your existing code calls requiresPremiumSubscription(lesson)
   and then showPremiumRequiredModal(). We reuse those hooks.
*/
if (typeof window.requiresPremiumSubscription === 'function') {
  window.requiresPremiumSubscription = function(lesson) {
    return mustSubscribeForLesson(lesson);
  };
}

/* ——— Ensure the "premium" modal call actually opens Subscribe modal ——— */
(function () {
  const origShowPremium = (typeof window.showPremiumRequiredModal === 'function')
    ? window.showPremiumRequiredModal
    : null;

  window.showPremiumRequiredModal = function() {
    // Prefer Subscribe modal if present, fallback to original else
    if (document.getElementById('subscribeModal')) {
      openSubscribeModal();
    } else if (origShowPremium) {
      origShowPremium();
    } else {
      console.warn('No subscribe/premium modal available.');
    }
  };
})();

/* ——— Intercept actual enrollment confirm (enroll button in your prompt) ———
   If the lesson requires subscription, show the Subscribe Modal and stop.
*/
(function () {
  const origConfirm = (typeof window.confirmEnrollment === 'function')
    ? window.confirmEnrollment
    : null;

  if (origConfirm) {
    window.confirmEnrollment = async function () {
      const lesson = window.currentLesson || null; // set by your code when opening prompt
      if (lesson && mustSubscribeForLesson(lesson)) {
        openSubscribeModal();
        return; // block enrollment
      }
      return await origConfirm.apply(this, arguments);
    };
  }
})();

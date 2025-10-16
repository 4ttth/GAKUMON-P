// Toggle dropdown visibility
document.getElementById('accountDropdownBtn').addEventListener('click', function(e) {
    e.stopPropagation();
    const dropdown = document.getElementById('accountDropdown');
    dropdown.classList.toggle('show');
});

// For Take Quiz Button
let selectedLessonId = null;
const lessons = window.lessons || { all: [], enrolled: [], my: [] };
const progressByLesson = window.progressByLesson || {};
// Optional: if you later expose IDs of orphans you created, they can be used to filter
const myOrphanQuizIds = (window.myOrphanQuizIds || []).map(String);

// All items marked as orphan (lesson_id is NULL) from the master list
const orphanItems = (lessons.all || []).filter(item => {
  if (!item) return false;
  const isOrphan = item.is_orphan === true || item.is_orphan === 1;
  if (!isOrphan) return false;
  // If you don't have myOrphanQuizIds, include all orphans; if you do, include only yours
  if (!myOrphanQuizIds.length) return true;
  return myOrphanQuizIds.includes(String(item.id));
});

// Merge authored lessons + orphan quizzes
const lessonsMyPlusOrphans = (() => {
  const merged = [...(lessons.my || []), ...orphanItems];
  const seen = new Set();
  return merged.filter(it => {
    const id = String(it?.id ?? '');
    if (!id) return true;            // keep if no id (shouldn't happen, but safe)
    if (seen.has(id)) return false;
    seen.add(id);
    return true;
  });
})();

function wireQuizLinks(lesson) {
  let href;
  if (lesson.is_orphan) {
    // Standalone quiz: no lesson_id — link directly to quiz by quiz_id
    href = `/quiz.php?quiz_id=${encodeURIComponent(lesson.id)}`;
  } else {
    // Normal lesson-linked quiz
    href = `/quiz.php?lesson_id=${encodeURIComponent(lesson.id)}`;
  }

  // apply to all known "Take Quiz" buttons in the modal
  const ids = ['take-quiz-link', 'take-quiz-link-2', 'take-quiz-btn'];
  ids.forEach(id => {
    const a = document.getElementById(id);
    if (a) a.setAttribute('href', href);
  });
}

// For Back Button
function absUrl(path) {
  return path.replace(/^\//, '');
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

// Function to check if there are enrolled lessons and toggle visibility
function checkEnrolledLessons() {
    const noLessonsContainer = document.querySelector('.no-lessons-container');
    const enrolledLessons = document.querySelectorAll('.lesson-card');
    
    if (enrolledLessons.length === 0) {
        noLessonsContainer.style.display = 'block';
    } else {
        noLessonsContainer.style.display = 'none';
    }
}

// Close dropdown when clicking outside
document.addEventListener('click', function(e) {
    const dropdown = document.getElementById('accountDropdown');
    const accountBtn = document.getElementById('accountDropdownBtn');
    if (!dropdown.contains(e.target) && e.target !== accountBtn && !accountBtn.contains(e.target)) {
        dropdown.classList.remove('show');
    }
});

// ---------- Subscribe modal helpers (safe if already defined elsewhere) ----------
if (typeof openSubscribeModal !== 'function') {
  function openSubscribeModal() {
    const modal = document.getElementById('subscribeModal');
    if (!modal) return;
    modal.classList.add('active');
    document.body.style.overflow = 'hidden';

    const backdrop = modal.querySelector('.custom-modal-backdrop');
    if (backdrop) {
      const onClick = (e) => { if (e.target === backdrop) closeSubscribeModal(); };
      backdrop.addEventListener('click', onClick, { once: true });
    }
  }
}
if (typeof closeSubscribeModal !== 'function') {
  function closeSubscribeModal() {
    const modal = document.getElementById('subscribeModal');
    if (!modal) return;
    modal.classList.remove('active');
    document.body.style.overflow = '';
  }
}

// Wire X/Cancel buttons if present
document.addEventListener('DOMContentLoaded', () => {
  const x = document.getElementById('subscribeXBtn');
  const c = document.getElementById('subscribeCancelBtn');
  if (x) x.addEventListener('click', closeSubscribeModal);
  if (c) c.addEventListener('click', closeSubscribeModal);
});

// ---------- Limit precheck for quiz creation ----------
async function canCreateAnotherQuiz() {
  const url = (typeof window.LIMITS_ENDPOINT === 'string' && window.LIMITS_ENDPOINT)
    ? window.LIMITS_ENDPOINT
    : '/include/creationLimits.inc.php';

  try {
    const res = await fetch(`${url}?check=quiz`, { credentials: 'same-origin', cache: 'no-store' });
    if (!res.ok) {
      console.warn('Limits check HTTP not ok:', res.status);
      return false; // FAIL-CLOSED
    }
    const data = await res.json();
    if (!data || data.ok === false) {
      console.warn('Limits check bad payload:', data);
      return false; // FAIL-CLOSED
    }
    return data.allowed !== false; // only deny when explicitly false
  } catch (err) {
    console.warn('Quiz limit check failed:', err);
    return false; // FAIL-CLOSED
  }
}


// ---------- Hook Add Quiz trigger(s) ----------
document.addEventListener('DOMContentLoaded', () => {
  // Support both an ID or a class, depending on your markup
  const triggers = [
    ...document.querySelectorAll('#addQuizBtn, .addQuizBtn')
  ];

  triggers.forEach(btn => {
    btn.addEventListener('click', async (e) => {
      e.preventDefault();
      const allowed = await canCreateAnotherQuiz();
      if (allowed) {
        window.location.href = 'createQuiz.php';
      } else {
        openSubscribeModal();
      }
    });
  });
});



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
        sessionStorage.removeItem('quizRestore');
        try {
        const r = JSON.parse(raw);
        if (r.path === window.location.pathname) {
            window.scrollTo(0, r.scrollY || 0);
            if (r.reopen?.type === 'materials' && r.reopen.lessonId) {
            const lesson = (lessons?.all || []).find(L => String(L.id) === String(r.reopen.lessonId));
            if (lesson) openMaterialsModal(lesson, r.reopen.materialType || 'video');
            } else if (r.reopen?.type === 'lesson' && r.reopen.lessonId) {
            const lesson = (lessons?.all || []).find(L => String(L.id) === String(r.reopen.lessonId));
            if (lesson) openLessonModal(lesson);
            }
        }
        } catch {}
    }
    
    // Check for enrolled lessons on page load
    checkEnrolledLessons();

    // For add quiz btn (limit-aware)
    const addQuizBtn = document.getElementById('addQuizBtn');
    if (addQuizBtn) {
    addQuizBtn.addEventListener('click', async (e) => {
        e.preventDefault();
        const allowed = await canCreateAnotherQuiz();
        if (allowed) {
        window.location.href = 'createQuiz.php'; // unchanged path
        } else {
        openSubscribeModal(); // show the same Subscribe modal
        }
    });
    }

});



// Generate random progress for demonstration (0-100%)
function getRandomProgress() {
    return Math.floor(Math.random() * 101);
}

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
        const raw = lesson.progress ?? progressByLesson[String(lesson.id)];
        const progress = Math.max(0, Math.min(100, Number(raw) || 0));
        const card = createLessonCard(lesson, progress);
        cardsGrid.appendChild(card);
    });
    checkEnrolledLessons();
}

// Create a lesson card with progress bar
function createLessonCard(lesson, progress) {
  const card = document.createElement('div');
  card.className = 'lesson-card';

  // Safe defaults
  const isOrphan   = lesson.is_orphan === true || lesson.is_orphan === 1;
  const author     = (lesson.author_name || 'GakuLesson');
  const title = lesson.title || lesson.quiz_title || lesson.lesson_title || (isOrphan ? `Quiz #${lesson.id}` : 'Untitled');
  const topicLabel = isOrphan ? 'Standalone Quiz' : (lesson.topic || '');
  const duration   = lesson.duration || '';
  const difficulty = lesson.difficulty || '';
  const iconHTML   = lesson.icon || '<i class="bi bi-question-circle"></i>';

  // Clamp progress 0..100
  const pct = Math.max(0, Math.min(100, Number(progress) || 0));
  const progressClass = pct === 100 ? 'progress-container shining' : 'progress-container';

  card.innerHTML = `
    <div class="card-img">
      ${iconHTML}
    </div>
    <div class="card-content">
      <div class="lesson-title">${title}</div>
      <div class="labels">
        <div class="label label-gaku">@${author}</div>
        <div class="label label-topic">${topicLabel}</div>
      </div>
      <div class="lesson-description">${lesson.short_desc || ''}</div>
      <div class="card-meta">
        <span>${duration}</span>
        <span>${difficulty}</span>
      </div>
    </div>
    <div class="${progressClass}">
      <div class="progress-bar">
        <div class="progress-fill" style="width: ${pct}%;"></div>
      </div>
      <div class="progress-text">${pct}% Complete</div>
    </div>
  `;

    // Click behavior:
    // - Orphan/standalone quizzes: redirect immediately (skip modal completely)
    // - Normal lessons: open modal normally
    if (isOrphan) {
    card.addEventListener('click', (e) => {
        e.preventDefault();
        e.stopImmediatePropagation();
        // Show the standard modal, but for a standalone quiz
        openLessonModal({
        ...lesson,
        is_orphan: true,
        topic: lesson.topic || 'Standalone Quiz',
        long_desc: lesson.long_desc || '',
        objectives: lesson.objectives || []
        });
    }, true);
    card.style.cursor = 'pointer';
    } else {
    card.addEventListener('click', (e) => {
        e.preventDefault();
        if (typeof openLessonModal === 'function') {
        openLessonModal(lesson);
        }
    });
    }

  // (Optional) keyboard accessibility
  card.tabIndex = 0;
  card.addEventListener('keydown', (e) => {
    if (e.key === 'Enter' || e.key === ' ') {
      e.preventDefault();
      card.click();
    }
  });

  return card;
}

// DOM elements
const tabs = document.querySelectorAll('.tab');
const cardsGrid = document.querySelector('.cards-grid');
const paginationLinks = document.querySelectorAll('.page-link');

// Current state
let currentCategory = 'gakulessons';
let currentPage = 1;
const itemsPerPage = 9;

// Initialize the page
function init() {
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
    showLessons(currentCategory);
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

  if (category === 'gakulessons') {
    // Match Lessons behavior: show pre-made lessons (author_name empty or 'GakuLesson')
    const all = lessons.enrolled || [];
    categoryLessons = all; // keep in sync with render logic
  } else if (category === 'mylessons') {
    // Lessons created by the logged-in user
    categoryLessons = lessonsMyPlusOrphans;
  } else if (category === 'enrolled') {
    // Lessons the user enrolled in (if you expose this tab)
    categoryLessons = lessons.enrolled || [];
  } else {
    // Fallback: everything
    categoryLessons = lessons.all || [];
  }

  const startIndex = (currentPage - 1) * itemsPerPage;
  const paginatedLessons = categoryLessons.slice(startIndex, startIndex + itemsPerPage);

  cardsGrid.innerHTML = '';
  paginatedLessons.forEach(lesson => {
    const raw = lesson.progress ?? progressByLesson[String(lesson.id)];
    const progress = Math.max(0, Math.min(100, Number(raw) || 0));
    const card = createLessonCard(lesson, progress);
    cardsGrid.appendChild(card);
  });

  updatePagination();
  checkEnrolledLessons();
}

// Get total pages for current category
function getTotalPages() {
  let categoryLessons;

  if (currentCategory === 'gakulessons') {
    const all = lessons.enrolled || [];
    categoryLessons = all; // keep in sync with render logic
  } else if (currentCategory === 'mylessons') {
    categoryLessons = lessonsMyPlusOrphans;
  } else if (currentCategory === 'enrolled') {
    categoryLessons = lessons.enrolled || [];
  } else {
    categoryLessons = lessons.all || [];
  }
  return Math.ceil(categoryLessons.length / itemsPerPage);
}


// Update pagination UI
function updatePagination() {
    const totalPages = getTotalPages();
    const pageItems = document.querySelectorAll('.page-item');
    
    if (currentPage > totalPages) currentPage = Math.max(1, totalPages);
    
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

// Add these modal functions to your existing JavaScript
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
            <div class="cardLesson-title">${lesson.title || lesson.quiz_title || lesson.lesson_title || `Quiz #${lesson.id}`}</div>
            <div class="labels">
                <div class="cardLabel cardLabel-gaku">
                    ${lesson.is_orphan ? window.loggedInUsername : (lesson.author_name || 'GakuLesson')}
                </div>
                <div class="cardLabel cardLabel-topic">${lesson.topic || ''}</div>
            </div>

            ${
                !lesson.is_orphan
                ? `
                <div class="modal-meta">
                    <span><i class="fas fa-clock"></i> ${lesson.duration || ''}</span>
                    <span><i class="fas fa-signal"></i> ${lesson.difficulty || ''}</span>
                </div>
                `
                : ''
            }
        </div>
        <div class="modal-lesson-description">
            <div class="cardLesson-description">${lesson.long_desc || ''}</div>
        </div>

        ${
            // ✅ Only show Learning Objectives + Folders if NOT a Standalone Quiz
            !lesson.is_orphan
            ? `
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
            `
            : ''
        }
    `;

    wireQuizLinks(lesson); // <- set both Take Quiz buttons for this lesson
    // ✅ Automatically link the Take Quiz button inside the modal
    const takeQuizBtn = document.getElementById('take-quiz-btn');
    if (takeQuizBtn) {
        // Remove any old listeners first (avoid stacking)
        const newBtn = takeQuizBtn.cloneNode(true);
        takeQuizBtn.parentNode.replaceChild(newBtn, takeQuizBtn);

        // Then attach the correct redirect based on quiz type
        if (lesson.is_orphan) {
            // 🔹 Standalone quiz
            newBtn.addEventListener('click', (e) => {
            e.preventDefault();
            e.stopImmediatePropagation();
            window.location.href = `/quiz.php?quiz_id=${encodeURIComponent(lesson.id)}`;
            });
        } else {
            // 🔹 Normal lesson-linked quiz
            newBtn.addEventListener('click', (e) => {
            e.preventDefault();
            e.stopImmediatePropagation();
            window.location.href = `/quiz.php?lesson_id=${encodeURIComponent(lesson.id)}`;
            });
        }
    }

    // For objectives
    const lessonId = Number(lesson.id ?? lesson.lesson_id);
    if (!Number.isInteger(lessonId) || lessonId <= 0) {
    console.error('Missing/invalid lesson_id for objectives:', lesson);
    } else {
    fetch(`include/lessonObjectives.inc.php?lesson_id=${lessonId}`)
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
        startLessonBtn.onclick = function() {
            window.location.href = `lesson.php?id=${lesson.id}`;
        };
    }
    
    
    // Close modal when clicking on backdrop
    const backdrop = document.querySelector('.custom-modal-backdrop');
    backdrop.addEventListener('click', closeLessonModal);
    
    // Add click events to folders
    const videoFolder = document.querySelector('.folder-wrapper[data-type="video"]');
    const notesFolder = document.querySelector('.folder-wrapper[data-type="notes"]');
    
    if (videoFolder) {
        videoFolder.addEventListener('click', () => {
            openMaterialsModal(lesson, 'video');
        });
    }
    
    if (notesFolder) {
        notesFolder.addEventListener('click', () => {
            openMaterialsModal(lesson, 'notes');
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
    
    // TRY CODE
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
            // Add debugging to check file object
            console.log('File object:', file);
            
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
                <button class="material-action" onclick="viewMaterial('${file.file_url}', '${fileExt}')">View</button>`;
            materialsList.appendChild(materialItem);
        });
    }

    wireQuizLinks(lesson); // <- set both Take Quiz buttons for this lesson

    // Add event listeners
    document.getElementById('backToLessonModal').addEventListener('click', () => {
        closeMaterialsModal();
        setTimeout(() => {
            openLessonModal(lesson); // Reopen the first modal after a brief delay
        }, 50);
    });

    // TRY quizLink
    const quizLink = document.getElementById('take-quiz-link');
    if (quizLink) {
    const origin = window.location.pathname;
    quizLink.setAttribute(
        'href',
        absUrl(`/quiz.php?lesson_id=${lesson.id}&from=${encodeURIComponent(origin)}`)
    );
    quizLink.addEventListener('click', () => {
        saveQuizReturnState({ type: 'materials', lessonId: lesson.id, materialType });
    }, { once: true });
    }


    document.getElementById('materialsModal').classList.add('active');
}


// Function to close materials modal
function closeMaterialsModal() {
    document.getElementById('materialsModal').classList.remove('active');
    
    // Remove event listeners
    document.getElementById('backToLessonModal').removeEventListener('click', () => {});
}

// TRY VIEW MATERIAL
// Update the viewMaterial function to handle the file paths
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
        if (document.getElementById('materialsModal').classList.contains('active')) {
            closeMaterialsModal();
        } else if (document.getElementById('lessonModal').classList.contains('active')) {
            closeLessonModal();
        }
    }
});

// Put this ONCE at the bottom of quizzesScript.js (outside of any function)
document.addEventListener('click', function (e) {
  const a = e.target.closest('#take-quiz-link');
  if (!a) return;

  // If href already has lesson_id, let the browser navigate naturally
  const href = a.getAttribute('href') || '';
    // allow either lesson-linked or standalone (quiz_id) targets
  if (!href.includes('lesson_id=') && !href.includes('quiz_id=')) {
    e.preventDefault();
  }
  // nothing else to do – the link was set in openMaterialsModal for the current lesson
});

document.addEventListener('click', function (e) {
  const t = e.target.closest('#addQuizBtn, .addQuizBtn, a[href*="createQuiz.php"]');
  if (!t) return;

  // Block default nav EARLY (capture handled below)
  e.preventDefault();
  e.stopPropagation();

  (async () => {
    const allowed = await canCreateAnotherQuiz();
    if (allowed) {
      window.location.href = 'createQuiz.php';
    } else {
      if (typeof openSubscribeModal === 'function') {
        openSubscribeModal();
      } else {
        console.warn('openSubscribeModal() missing; check modal markup/JS load order.');
      }
    }
  })();
}, true); // <-- capture = true so we beat other handlers


// 🧩 FINAL OVERRIDE: force orphan quizzes to redirect instead of showing modal
document.addEventListener("DOMContentLoaded", () => {
  // Wait a bit to ensure all cards are rendered and event listeners attached
  setTimeout(() => {
    const cards = document.querySelectorAll(".lesson-card");

    cards.forEach(card => {
      // Detect orphan quiz card reliably
      const hasStandalone = card.textContent.includes("Standalone quiz") ||
                            card.innerHTML.includes("bi-question-circle") ||
                            card.querySelector(".lesson-title")?.textContent?.match(/^Quiz #/i);

      if (hasStandalone) {
        // Clone node to strip all old modal listeners
        const clone = card.cloneNode(true);
        card.parentNode.replaceChild(clone, card);

        // Add fresh redirect-only handler
        clone.style.cursor = "pointer";
        clone.addEventListener("click", (e) => {
          e.preventDefault();
          e.stopImmediatePropagation();

          const title = clone.querySelector(".lesson-title")?.textContent || "";
          const match = title.match(/Quiz\s?#?(\d+)/i);
          const quizId = match ? match[1] : clone.dataset.id;

          // Let the lesson modal handle the routing
          let lessonObj = (lessons?.all || []).find(L => String(L.id) === String(quizId));
          if (!lessonObj) {
              lessonObj = {
                  id: quizId,
                  is_orphan: true,
                  title: title || `Quiz #${quizId}`,
                  topic: 'Standalone Quiz',
                  duration: '',
                  difficulty: '',
                  long_desc: '',
                  objectives: []
              };
          } else {
              lessonObj = {
                  ...lessonObj,
                  is_orphan: true,
                  topic: lessonObj.topic || 'Standalone Quiz',
                  objectives: lessonObj.objectives || []
              };
          }

          openLessonModal(lessonObj);
      });
      }
    });
  }, 500); // allow DOM + events to finish attaching first
});

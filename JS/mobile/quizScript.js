// Mobile detection
const isMobile = window.innerWidth <= 768;

// Toggle dropdown visibility
document.getElementById('accountDropdownBtn')?.addEventListener('click', function(e) {
    e.stopPropagation();
    const dropdown = document.getElementById('accountDropdown');
    dropdown.classList.toggle('show');
});

// Close dropdown when clicking outside
document.addEventListener('click', function(e) {
    const dropdown = document.getElementById('accountDropdown');
    const accountBtn = document.getElementById('accountDropdownBtn');
    if (dropdown && accountBtn && !dropdown.contains(e.target) && e.target !== accountBtn && !accountBtn.contains(e.target)) {
        dropdown.classList.remove('show');
    }
});

// TRY CLOSE DROPDOWN WHEN CLICKING OUTSIDE
const accountBtn = document.getElementById('accountDropdownBtn');
const accountDropdown = document.getElementById('accountDropdown');

if (accountBtn && accountDropdown) {
  accountBtn.addEventListener('click', (e) => {
    e.stopPropagation();
    accountDropdown.classList.toggle('show');
  });

  document.addEventListener('click', (e) => {
    if (!accountDropdown.contains(e.target) && e.target !== accountBtn) {
      accountDropdown.classList.remove('show');
    }
  });
}

// --- return helpers ---
function getQueryParam(name) {
  const p = new URLSearchParams(window.location.search);
  return p.get(name);
}
function sameOrigin(url) {
  try {
    const u = new URL(url, window.location.href);
    return u.origin === window.location.origin;
  } catch { return false; }
}
function computeReturnUrl() {
  // Prefer ?from= if present & safe
  const from = getQueryParam('from');
  if (from && sameOrigin(from)) return from;

  // Else use same-origin referrer (avoid looping to quiz.php)
  if (document.referrer && sameOrigin(document.referrer)) {
    const ref = new URL(document.referrer, window.location.href);
    if (!/\/quiz\.php$/i.test(ref.pathname)) return ref.pathname + ref.search + ref.hash;
  }

  // Fallback
  return '/homepage.php';
}
const RETURN_URL = computeReturnUrl();

// For Back Button
document.addEventListener('click', (e) => {
  const backBtn = e.target.closest('.backLessons');
  if (!backBtn) return;
  e.preventDefault();
  window.location.href = '/lessons.php';
}, { passive: false });

// Quiz state
let currentQuestionIndex = 0;
let userAnswers = [];
let score = 0;

// DOM Elements
const welcomePage = document.getElementById('welcome-page');
const quizInProgress = document.getElementById('quiz-in-progress');
const resultsPage = document.getElementById('results-page');
const questionNumber = document.getElementById('question-number');
const questionText = document.getElementById('question-text');
const optionsContainer = document.getElementById('options-container');
const quizForm = document.getElementById('quiz-form');
const progressBar = document.getElementById('progress');
const scoreDisplay = document.getElementById('score-display');
const resultsContainer = document.getElementById('results-container');

// Works on welcome page, during quiz, and on results page
document.addEventListener('click', (e) => {
  const exitBtn = e.target.closest('.exitButton');
  if (!exitBtn) return;

  e.preventDefault();

  // Preserve prior page state if we saved it before starting quiz
  const raw = sessionStorage.getItem('quizReturn');
  if (raw) {
    sessionStorage.setItem('quizRestore', raw);
    sessionStorage.removeItem('quizReturn');
  }

  // Prefer a true browser back (restores exact scroll + modal state)
  const canGoBack =
    window.history.length > 1 &&
    document.referrer &&
    sameOrigin(document.referrer) &&
    !/\/quiz\.php$/i.test(new URL(document.referrer, window.location.href).pathname);

  if (canGoBack) {
    window.history.back();
  } else {
    window.location.href = RETURN_URL;
  }
}, { passive: false });

// Replace the whole dummy object with:
const quizData = (window.serverQuizData && Array.isArray(window.serverQuizData.questions))
  ? window.serverQuizData
  : { lesson_title: 'Quiz', questions: [] };

// Reflect the lesson title in all visible places
document.querySelectorAll('#lesson-title, .headerLesson-title').forEach(el => {
  if (el) el.textContent = quizData.lesson_title;
});

// Timer variables
let quizStartTime;
let quizEndTime;

// Update the start quiz event to set the start time
document.getElementById('start-quiz').addEventListener('click', () => {
    welcomePage.style.display = 'none';
    quizInProgress.style.display = 'block';
    quizStartTime = new Date(); // Set start time
    loadQuestion();
});

// Load a question
function loadQuestion() {
    const question = quizData.questions[currentQuestionIndex];
    const progress = ((currentQuestionIndex) / quizData.questions.length) * 100;
    
    // Update progress bar
    progressBar.style.width = `${progress}%`;
    
    // Update question number
    questionNumber.textContent = `Question ${currentQuestionIndex + 1} of ${quizData.questions.length}`;
    
    // Set question text
    questionText.textContent = question.question_text;
    
    // Clear previous options
    optionsContainer.innerHTML = '';
    
    // Add options based on question type
    if (question.question_type === 'multiple_choice' || question.question_type === 'true_false') {
        question.options.forEach(option => {
            const label = document.createElement('label');
            label.className = 'option';
            
            const input = document.createElement('input');
            input.type = 'radio';
            input.name = 'answer';
            input.value = option.option_text;
            input.required = true;
            
            // Mobile optimization: larger touch targets
            if (isMobile) {
                label.style.padding = '16px 20px';
                label.style.margin = '10px 0';
                label.style.fontSize = '18px';
            }
            
            label.appendChild(input);
            label.appendChild(document.createTextNode(option.option_text));
            
            // Add click event to make the whole label clickable
            label.addEventListener('click', () => {
                document.querySelectorAll('.option').forEach(opt => opt.classList.remove('selected'));
                label.classList.add('selected');
                input.checked = true;
            });

            // Mobile: Add touch feedback
            if (isMobile) {
                label.addEventListener('touchstart', () => {
                    label.style.transform = 'scale(0.98)';
                });
                label.addEventListener('touchend', () => {
                    label.style.transform = 'scale(1)';
                });
            }
            
            optionsContainer.appendChild(label);
        });
    } else if (question.question_type === 'fill_blank' || question.question_type === 'identification') {
        const input = document.createElement('input');
        input.type = 'text';
        input.name = 'answer';
        input.className = 'text-input';
        input.required = true;
        input.placeholder = 'Type your answer here';
        
        // Mobile optimization: larger input for touch
        if (isMobile) {
            input.style.padding = '16px 20px';
            input.style.fontSize = '18px';
            input.style.height = '60px';
        }
        
        optionsContainer.appendChild(input);
    }

    // Mobile: Auto-focus on first input for better UX
    if (isMobile) {
        setTimeout(() => {
            const firstInput = optionsContainer.querySelector('input');
            if (firstInput && firstInput.type !== 'radio') {
                firstInput.focus();
            }
        }, 300);
    }
}

// Handle form submission
quizForm.addEventListener('submit', (e) => {
    e.preventDefault();
    
    const formData = new FormData(quizForm);
    const userAnswer = formData.get('answer');
    const currentQuestion = quizData.questions[currentQuestionIndex];
    
    // Check if answer is correct
    let isCorrect = false;
    let correctAnswer = '';
    
    if (currentQuestion.question_type === 'multiple_choice' || currentQuestion.question_type === 'true_false') {
        const correctOption = currentQuestion.options.find(option => option.is_correct);
        isCorrect = userAnswer === correctOption.option_text;
        correctAnswer = correctOption.option_text;
    } else if (currentQuestion.question_type === 'fill_blank' || currentQuestion.question_type === 'identification') {
        // Case-insensitive comparison for text answers
        isCorrect = userAnswer.toLowerCase().trim() === currentQuestion.correct_answer.toLowerCase().trim();
        correctAnswer = currentQuestion.correct_answer;
    }
    
    // Store user's answer
    userAnswers.push({
        question: currentQuestion.question_text,
        userAnswer: userAnswer,
        isCorrect: isCorrect,
        correctAnswer: correctAnswer
    });
    
    // Update score if correct
    if (isCorrect) {
        score++;
    }
    
    // Move to next question or show results
    if (currentQuestionIndex < quizData.questions.length - 1) {
        currentQuestionIndex++;
        loadQuestion();
    } else {
        // Quiz completed - show coins modal first, then results
        showCoinsEarned(score).then(() => {
            showResults();
        });
    }
});

// Show results
function showResults() {
    quizEndTime = new Date(); // Set end time
    const timeTaken = Math.round((quizEndTime - quizStartTime) / 1000); // in seconds
    
    quizInProgress.style.display = 'none';
    resultsPage.style.display = 'block';

    // For Back Buttons
    const exitBtns = document.querySelectorAll('.exitButton');
    exitBtns.forEach(btn => {
    btn.addEventListener('click', () => {
        // Pull the saved state and pass it back for restoration
        const raw = sessionStorage.getItem('quizReturn');
        if (raw) {
        // Move it under a different key so the origin page consumes it once
        sessionStorage.setItem('quizRestore', raw);
        sessionStorage.removeItem('quizReturn');
        }
        // Go back to the page we came from (exact path)
        window.location.href = originPage;
    }, { once: true });
    });

    const backLessonBtns = document.querySelectorAll('.backLessons');
    backLessonBtns.forEach(btn => {
    btn.addEventListener('click', () => {
        window.location.href = '/lessons.php';
    }, { once: true });
    });
    
    // Update score visualization
    const percentage = (score / quizData.questions.length) * 100;
    const circle = document.querySelector('.circle-progress');
    const circumference = 2 * Math.PI * 90;
    const offset = circumference - (percentage / 100) * circumference;
    
    circle.style.strokeDashoffset = offset;
    
    // Update score text
    document.querySelector('.score-text').textContent = `${score}/${quizData.questions.length}`;
    
    // Update stats
    document.getElementById('correct-count').textContent = score;
    document.getElementById('total-count').textContent = quizData.questions.length;
    document.getElementById('percentage').textContent = `${percentage.toFixed(1)}%`;
    document.getElementById('time-taken').textContent = `${timeTaken}s`;
    document.getElementById('coins-earned').textContent = score;
    
    // Display results for each question
    resultsContainer.innerHTML = '';
    
    userAnswers.forEach((answer, index) => {
        const resultItem = document.createElement('div');
        resultItem.className = `result-item ${answer.isCorrect ? 'result-correct' : 'result-incorrect'}`;
        
        // Mobile optimization: better spacing and font sizes
        if (isMobile) {
            resultItem.style.padding = '15px';
            resultItem.style.marginBottom = '12px';
            resultItem.style.fontSize = '16px';
        }
        
        resultItem.innerHTML = `
            <p class="result-question"><strong>Question ${index + 1}:</strong> ${answer.question}</p>
            <p class="result-answer">Your answer: ${answer.userAnswer}</p>
            ${!answer.isCorrect ? `<p class="result-answer">Correct answer: ${answer.correctAnswer}</p>` : ''}
        `;
        
        resultsContainer.appendChild(resultItem);
    });

    // Mobile: Adjust results layout for smaller screens
    if (isMobile) {
        const resultsContent = document.querySelector('.results-content');
        if (resultsContent) {
            resultsContent.style.flexDirection = 'column';
        }
        
        const scoreViz = document.querySelector('.score-visualization');
        const questionResults = document.querySelector('.question-results');
        
        if (scoreViz && questionResults) {
            scoreViz.style.marginBottom = '30px';
            questionResults.style.width = '100%';
        }
    }

    // --- Send result to server ---
    (async () => {
    try {
        const payload = {
        quiz_id: (window.serverQuizData && window.serverQuizData.quiz_id) ? window.serverQuizData.quiz_id : 0,
        score: score,
        total: Array.isArray(quizData?.questions) ? quizData.questions.length : 0
        // you can also send time_taken_seconds if you want
        };

        if (!payload.quiz_id) {
        console.warn('No quiz_id found; not saving attempt.');
        return;
        }

        const res = await fetch('include/saveQuizAttempt.inc.php', { // << NEW filename here
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        credentials: 'same-origin',
        body: JSON.stringify(payload)
        });

        const text = await res.text();
        let json = null;
        try { json = JSON.parse(text); } catch {}
        if (!res.ok || !json || !json.ok) {
        console.error('Save failed:', { status: res.status, body: text });
        alert(`Could not save your attempt.\nStatus: ${res.status}\nDetails: ${text}`);
        return;
        }

        console.log('Attempt saved:', json);
        // Example: reflect coins in UI if you have an element for it
        // const coinsEl = document.getElementById('gakucoins');
        // if (coinsEl && typeof json.new_gakucoins === 'number') coinsEl.textContent = json.new_gakucoins;

    } catch (err) {
        console.error('Network/JS error while saving attempt:', err);
        alert('Network error while saving your attempt. Check Console for details.');
    }
    })();

    // Wire the buttons each time results are shown
    (function wireResultButtonsOnce() {
    // Exit Quiz → prefer history.back() to fully restore the previous page state
    document.querySelectorAll('.exitButton').forEach((btn) => {
        btn.addEventListener('click', (e) => {
        e.preventDefault();

        // If we have a trustworthy same-origin referrer AND history can go back, use it.
        const canGoBack = window.history.length > 1 && document.referrer && sameOrigin(document.referrer);

        if (canGoBack) {
            // Restores scroll position, filters, and any open modal on the previous page
            window.history.back();
        } else {
            // Fallback to computed URL (from=?, referrer, or homepage)
            window.location.href = RETURN_URL;
        }
        }, { once: true });
    });

    // Back to Lesson → always go to lessons.php
    document.querySelectorAll('.backLessons').forEach((btn) => {
        btn.addEventListener('click', (e) => {
        e.preventDefault();
        window.location.href = '/lessons.php';
        }, { once: true });
    });
    })();
}

// Show Gakucoins earned modal
function showCoinsEarned(coinsEarned) {
    return new Promise((resolve) => {
        // Create modal overlay
        const modalOverlay = document.createElement('div');
        modalOverlay.className = 'coins-modal-overlay';
        modalOverlay.style.cssText = `
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.7);
            display: flex;
            justify-content: center;
            align-items: center;
            z-index: 9999;
        `;

        // Create modal content
        const modalContent = document.createElement('div');
        modalContent.className = 'coins-modal-content';
        
        // Mobile-responsive modal sizing
        const modalStyle = isMobile ? `
            background: white;
            padding: 30px 20px;
            border-radius: 20px;
            text-align: center;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.3);
            max-width: 90%;
            width: 90%;
            margin: 20px;
        ` : `
            background: white;
            padding: 40px;
            border-radius: 20px;
            text-align: center;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.3);
            max-width: 400px;
            width: 90%;
        `;
        
        modalContent.style.cssText = modalStyle;

        // Create coins icon
        const coinsIcon = document.createElement('div');
        coinsIcon.innerHTML = '🪙';
        coinsIcon.style.cssText = `
            font-size: ${isMobile ? '50px' : '60px'};
            margin-bottom: 20px;
        `;

        // Create title
        const title = document.createElement('div');
        title.className = 'coins-title';
        title.textContent = 'Quiz Completed!';
        title.style.cssText = `
            font-family: 'SFpro_bold', sans-serif;
            font-size: ${isMobile ? '24px' : '28px'};
            color: #811212;
            margin-bottom: 10px;
            text-transform: uppercase;
        `;

        // Create coins earned text
        const coinsText = document.createElement('div');
        coinsText.className = 'coins-earned';
        coinsText.innerHTML = `You earned <span style="color: #811212; font-weight: bold;">${coinsEarned} Gakucoins</span>!`;
        coinsText.style.cssText = `
            font-family: 'SFpro_regular', sans-serif;
            font-size: ${isMobile ? '18px' : '20px'};
            margin-bottom: 30px;
            color: #333;
        `;

        // Create exit button
        const exitButton = document.createElement('button');
        exitButton.className = 'btnSubmit coins-exit-btn';
        exitButton.textContent = 'Continue to Results';
        exitButton.style.cssText = `
            width: ${isMobile ? '100%' : '200px'} !important;
            margin: 0 auto;
            padding: ${isMobile ? '15px' : '12px'} !important;
            font-size: ${isMobile ? '18px' : '16px'} !important;
        `;

        // Mobile: Add touch feedback
        if (isMobile) {
            exitButton.addEventListener('touchstart', () => {
                exitButton.style.transform = 'scale(0.98)';
            });
            exitButton.addEventListener('touchend', () => {
                exitButton.style.transform = 'scale(1)';
            });
        }

        // Assemble modal
        modalContent.appendChild(coinsIcon);
        modalContent.appendChild(title);
        modalContent.appendChild(coinsText);
        modalContent.appendChild(exitButton);
        modalOverlay.appendChild(modalContent);
        document.body.appendChild(modalOverlay);

        // Handle exit
        const exitModal = () => {
            modalOverlay.remove();
            resolve();
        };

        exitButton.addEventListener('click', exitModal);
        
        // Mobile: Also close on overlay tap
        if (isMobile) {
            modalOverlay.addEventListener('click', (e) => {
                if (e.target === modalOverlay) {
                    exitModal();
                }
            });
        }
        
        // Auto exit after 5 seconds
        setTimeout(exitModal, 5000);
    });
}

// Mobile-specific optimizations
if (isMobile) {
    // Prevent zoom on input focus
    document.addEventListener('touchstart', function() {}, {passive: true});
    
    // Better touch handling for buttons
    document.addEventListener('DOMContentLoaded', function() {
        const buttons = document.querySelectorAll('button');
        buttons.forEach(button => {
            button.addEventListener('touchstart', function() {
                this.style.transform = 'scale(0.98)';
            });
            button.addEventListener('touchend', function() {
                this.style.transform = 'scale(1)';
            });
        });
    });
    
    // Adjust viewport for mobile
    const viewport = document.querySelector('meta[name="viewport"]');
    if (viewport) {
        viewport.setAttribute('content', 'width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no');
    }
}

// Handle orientation changes
window.addEventListener('orientationchange', function() {
    setTimeout(() => {
        window.scrollTo(0, 0);
    }, 100);
});

// Prevent accidental page refresh/swipe
window.addEventListener('beforeunload', function(e) {
    if (quizInProgress.style.display !== 'none') {
        e.preventDefault();
        e.returnValue = 'Are you sure you want to leave? Your quiz progress will be lost.';
    }
});
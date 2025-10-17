document.addEventListener('DOMContentLoaded', function() {
    const accountDropdownBtn = document.getElementById("accountDropdownBtn");
    const accountDropdown = document.getElementById("accountDropdown");
    
    if (accountDropdownBtn && accountDropdown) {
        accountDropdownBtn.addEventListener("click", function (e) {
            e.stopPropagation();
            accountDropdown.classList.toggle("show");
        });

        // Close dropdown when clicking outside
        document.addEventListener("click", function (e) {
            if (!accountDropdown.contains(e.target) && e.target !== accountDropdownBtn && !accountDropdownBtn.contains(e.target)) {
                accountDropdown.classList.remove("show");
            }
        });
    }
    // Initialize tooltips
    const tooltipTriggerList = [].slice.call(document.querySelectorAll('[title]'));
    const tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
        return new bootstrap.Tooltip(tooltipTriggerEl);
    });

    // Handle delete content button clicks
    const deleteButtons = document.querySelectorAll('.delete-content-btn');
    
    deleteButtons.forEach(button => {
        button.addEventListener('click', function() {
            const lessonId = this.getAttribute('data-lesson-id');
            const modal = this.closest('.modal');
            const title = modal.querySelector('.modal-title').textContent.replace('View Content: ', '');
            
            if (confirm(`Are you sure you want to delete the content "${title}"? This action cannot be undone.`)) {
                deleteContent(lessonId, modal);
            }
        });
    });

    // Function to handle content deletion
    function deleteContent(lessonId, modal) {
        // Show loading state
        const deleteBtn = modal.querySelector('.delete-content-btn');
        const originalText = deleteBtn.innerHTML;
        deleteBtn.innerHTML = '<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Deleting...';
        deleteBtn.disabled = true;

        // Create form data
        const formData = new FormData();
        formData.append('lesson_id', lessonId);
        formData.append('action', 'delete_content');

        // Send AJAX request to delete content (updated path to include folder)
        fetch('/include/handle_materials_moderation.php', {
            method: 'POST',
            body: formData,
            headers: {
                'Accept': 'application/json',
                'X-Requested-With': 'XMLHttpRequest'
            },
            credentials: 'same-origin'
        })
        .then(async (res) => {
            const ct  = res.headers.get('content-type') || '';
            const txt = await res.text();

            // 204 (no content) but OK → treat as success
            if (res.ok && !txt.trim()) return { success: true, message: 'No content' };

            // If JSON, parse safely
            if (ct.includes('application/json')) {
                try { return JSON.parse(txt); }
                catch { throw new Error('Server returned invalid JSON.'); }
            }

            // Non-JSON (likely HTML): detect common cases for a clearer error
            if (res.redirected || /<html|<br|<b>/i.test(txt)) {
                if (/login/i.test(txt) || /session/i.test(txt) || res.url.includes('login')) {
                    throw new Error('Your session expired. Please sign in again.');
                }
                // show first part of the HTML to help you debug on DevTools
                console.warn('Server HTML response (truncated):\n', txt.slice(0, 400));
                throw new Error(`Server returned HTML instead of JSON (HTTP ${res.status}).`);
            }

            // Last attempt: try JSON anyway
            try { return JSON.parse(txt); }
            catch { throw new Error(`Unexpected response (HTTP ${res.status}): ${txt.slice(0,120)}`); }
        })
        .then((data) => {
            if (!data || !data.success) {
                throw new Error((data && data.message) || 'Failed to delete content');
            }

            showNotification('Content deleted successfully!', 'success');

            const modalInstance = bootstrap.Modal.getInstance(modal);
            if (modalInstance) modalInstance.hide();

            setTimeout(() => window.location.reload(), 1000);
        })
        .catch((error) => {
            console.error('Error:', error);
            showNotification('Error deleting content: ' + error.message, 'error');

            // Reset button state
            deleteBtn.innerHTML = originalText;
            deleteBtn.disabled = false;
        });

    }

    // Handle modal show event to pause videos when modal closes
    const modals = document.querySelectorAll('.modal');
    modals.forEach(modal => {
        modal.addEventListener('hidden.bs.modal', function() {
            // Pause all videos in the modal when it closes
            const videos = this.querySelectorAll('video');
            videos.forEach(video => {
                video.pause();
                video.currentTime = 0;
            });
        });
        
        modal.addEventListener('show.bs.modal', function() {
            // Reset iframe sources to ensure proper loading
            const iframes = this.querySelectorAll('iframe');
            iframes.forEach(iframe => {
                const src = iframe.src;
                iframe.src = '';
                setTimeout(() => {
                    iframe.src = src;
                }, 100);
            });
        });
    });

    // Handle view button clicks for additional functionality
    const viewButtons = document.querySelectorAll('.view-btn');
    viewButtons.forEach(button => {
        button.addEventListener('click', function() {
            const lessonId = this.getAttribute('data-lesson-id');
            const videoFile = this.getAttribute('data-video-file');
            const documentFile = this.getAttribute('data-document-file');
            
            console.log(`Viewing content ${lessonId}:`, {
                video: videoFile,
                document: documentFile
            });
        });
    });

    // Notification function
    function showNotification(message, type = 'info') {
        // Remove existing notifications
        const existingNotifications = document.querySelectorAll('.custom-notification');
        existingNotifications.forEach(notification => notification.remove());

        // Create notification element
        const notification = document.createElement('div');
        notification.className = `custom-notification alert alert-${type === 'error' ? 'danger' : type} alert-dismissible fade show`;
        notification.style.cssText = `
            position: fixed;
            top: 20px;
            right: 20px;
            z-index: 9999;
            min-width: 300px;
        `;
        
        notification.innerHTML = `
            ${message}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        `;
        
        document.body.appendChild(notification);
        
        // Auto remove after 5 seconds
        setTimeout(() => {
            if (notification.parentNode) {
                notification.remove();
            }
        }, 5000);
    }

    // Handle file preview errors
    document.addEventListener('error', function(e) {
        if (e.target.tagName === 'VIDEO' || e.target.tagName === 'IFRAME') {
            const container = e.target.closest('.video-preview, .document-preview');
            if (container) {
                container.innerHTML = `
                    <div class="alert alert-warning">
                        <i class="bi bi-exclamation-triangle"></i>
                        Unable to preview this file. The file format may not be supported or the file may be corrupted.
                    </div>
                `;
            }
        }
    }, true);

    // Keyboard shortcuts
    document.addEventListener('keydown', function(e) {
        // ESC key to close modal
        if (e.key === 'Escape') {
            const openModal = document.querySelector('.modal.show');
            if (openModal) {
                const modalInstance = bootstrap.Modal.getInstance(openModal);
                modalInstance.hide();
            }
        }
        
        // Ctrl+D to trigger delete when modal is open (for power users)
        if (e.ctrlKey && e.key === 'd') {
            e.preventDefault();
            const openModal = document.querySelector('.modal.show');
            if (openModal) {
                const deleteBtn = openModal.querySelector('.delete-content-btn');
                if (deleteBtn) {
                    deleteBtn.click();
                }
            }
        }
    });

    // Add loading state to pagination links
    const paginationLinks = document.querySelectorAll('.pagination a');
    paginationLinks.forEach(link => {
        link.addEventListener('click', function(e) {
            // Only show loading for non-disabled links
            if (!this.closest('.page-item').classList.contains('disabled')) {
                this.innerHTML = '<span class="spinner-border spinner-border-sm" role="status"></span>';
            }
        });
    });
});

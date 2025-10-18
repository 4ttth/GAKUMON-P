// Gakusensei Verification Script with Pagination
document.addEventListener('DOMContentLoaded', function() {
    // Pagination variables
    const itemsPerPage = 15;
    let currentPage = 1;
    let allApplications = [];

    // Initialize the page
    initPage();

    function initPage() {
        // Get all application rows from the table
        const tableBody = document.querySelector('.account-table tbody');
        allApplications = Array.from(tableBody.querySelectorAll('tr')).filter(row => 
            !row.querySelector('td')?.textContent?.includes('No verification applications found')
        );

        // Initialize pagination
        setupPagination();
        
        // Show first page
        showPage(1);
    }

    function setupPagination() {
        const totalPages = Math.ceil(allApplications.length / itemsPerPage);
        
        // Remove existing pagination if any
        const existingPagination = document.querySelector('.pagination-container');
        if (existingPagination) {
            existingPagination.remove();
        }

        // Create pagination container
        const paginationContainer = document.createElement('div');
        paginationContainer.className = 'pagination-container';
        
        // Create pagination info
        const paginationInfo = document.createElement('div');
        paginationInfo.className = 'pagination-info';
        paginationContainer.appendChild(paginationInfo);

        // Create pagination navigation
        const paginationNav = document.createElement('nav');
        const paginationList = document.createElement('ul');
        paginationList.className = 'pagination';
        paginationNav.appendChild(paginationList);
        paginationContainer.appendChild(paginationNav);

        // Insert pagination after the table
        const cardBody = document.querySelector('.card-body');
        cardBody.appendChild(paginationContainer);

        // Update pagination display
        updatePagination(totalPages, paginationInfo, paginationList);
    }

    function updatePagination(totalPages, paginationInfo, paginationList) {
        // Clear existing pagination
        paginationList.innerHTML = '';

        // Update pagination info
        const startItem = (currentPage - 1) * itemsPerPage + 1;
        const endItem = Math.min(currentPage * itemsPerPage, allApplications.length);
        paginationInfo.textContent = `Showing ${startItem}-${endItem} of ${allApplications.length} applications`;

        // Previous button
        const prevLi = document.createElement('li');
        prevLi.className = `page-item ${currentPage === 1 ? 'disabled' : ''}`;
        prevLi.innerHTML = `
            <a class="page-link" href="#" aria-label="Previous" ${currentPage === 1 ? 'tabindex="-1"' : ''}>
                <span aria-hidden="true">&laquo;</span>
            </a>
        `;
        prevLi.querySelector('.page-link').addEventListener('click', (e) => {
            e.preventDefault();
            if (currentPage > 1) {
                showPage(currentPage - 1);
            }
        });
        paginationList.appendChild(prevLi);

        // Page numbers
        for (let i = 1; i <= totalPages; i++) {
            const pageLi = document.createElement('li');
            pageLi.className = `page-item ${i === currentPage ? 'active' : ''}`;
            pageLi.innerHTML = `<a class="page-link" href="#">${i}</a>`;
            
            pageLi.querySelector('.page-link').addEventListener('click', (e) => {
                e.preventDefault();
                showPage(i);
            });
            
            paginationList.appendChild(pageLi);
        }

        // Next button
        const nextLi = document.createElement('li');
        nextLi.className = `page-item ${currentPage === totalPages ? 'disabled' : ''}`;
        nextLi.innerHTML = `
            <a class="page-link" href="#" aria-label="Next" ${currentPage === totalPages ? 'tabindex="-1"' : ''}>
                <span aria-hidden="true">&raquo;</span>
            </a>
        `;
        nextLi.querySelector('.page-link').addEventListener('click', (e) => {
            e.preventDefault();
            if (currentPage < totalPages) {
                showPage(currentPage + 1);
            }
        });
        paginationList.appendChild(nextLi);
    }

    function showPage(page) {
        currentPage = page;
        
        // Hide all applications
        allApplications.forEach(application => {
            application.style.display = 'none';
        });

        // Show applications for current page
        const startIndex = (page - 1) * itemsPerPage;
        const endIndex = startIndex + itemsPerPage;
        
        for (let i = startIndex; i < endIndex && i < allApplications.length; i++) {
            allApplications[i].style.display = '';
        }

        // Update pagination
        const totalPages = Math.ceil(allApplications.length / itemsPerPage);
        const paginationInfo = document.querySelector('.pagination-info');
        const paginationList = document.querySelector('.pagination');
        updatePagination(totalPages, paginationInfo, paginationList);
    }

    // Account dropdown functionality
    const accountBtn = document.querySelector('.account-btn');
    const accountDropdown = document.querySelector('.account-dropdown');

    if (accountBtn && accountDropdown) {
        accountBtn.addEventListener('click', function(e) {
            e.stopPropagation();
            accountDropdown.classList.toggle('show');
        });

        // Close dropdown when clicking outside
        document.addEventListener('click', function() {
            accountDropdown.classList.remove('show');
        });

        // Prevent dropdown from closing when clicking inside
        accountDropdown.addEventListener('click', function(e) {
            e.stopPropagation();
        });
    }

    // Logout functionality
    // const logoutBtn = document.querySelector('.dropdown-logout');
    // if (logoutBtn) {
    //     logoutBtn.addEventListener('click', function(e) {
    //         e.preventDefault();
    //         if (confirm('Are you sure you want to logout?')) {
    //             window.location.href = 'logout.php';
    //         }
    //     });
    // }
});

// Safely parse JSON; tolerate empty or non-JSON responses
async function safeJson(response) {
  const ct = response.headers.get('content-type') || '';
  const text = await response.text();           // read once

  // If server returns nothing, assume success so the UI can proceed
  if (!text.trim()) return { success: true };

  // If it's JSON or looks like JSON, parse it
  if (ct.includes('application/json') || text.trim().startsWith('{') || text.trim().startsWith('[')) {
    try {
      return JSON.parse(text);
    } catch (e) {
      throw new Error('Server returned invalid JSON.');
    }
  }

  // Non-JSON body—surface first part for debugging
  throw new Error('Server returned non-JSON response: ' + text.slice(0, 200));
}


// Approve application function
function approveApplication(applicationId, userId) {
    if (confirm('Are you sure you want to approve this application?')) {
        // Show loading state
        const approveBtn = document.querySelector(`#approveModal${applicationId} .btn-primary`);
        const originalText = approveBtn.innerHTML;
        approveBtn.innerHTML = '<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Approving...';
        approveBtn.disabled = true;

        // Use FormData instead of JSON for POST request
        const formData = new FormData();
        formData.append('action', 'approve');
        formData.append('application_id', applicationId);
        formData.append('user_id', userId);

        fetch('gakusensei_verification.php', {
        method: 'POST',
        body: formData,
        headers: { 'Accept': 'application/json' } // hint server to send JSON
        })
        .then(safeJson)
        .then(data => {
        if (data.success) {
            const modal = bootstrap.Modal.getInstance(document.getElementById(`approveModal${applicationId}`));
            if (modal) modal.hide();
            setTimeout(() => location.reload(), 300);   // snappier refresh
        } else {
            alert('Error approving application' + (data.message ? `: ${data.message}` : '.'));
            approveBtn.innerHTML = originalText;
            approveBtn.disabled = false;
        }
        })
        .catch(error => {
        console.error('Error:', error);
        alert(error.message || 'Error approving application.');
        approveBtn.innerHTML = originalText;
        approveBtn.disabled = false;
        });

    }
}

// Safely parse JSON; tolerate empty or non-JSON responses
async function safeJson(response) {
  const ct = response.headers.get('content-type') || '';
  const text = await response.text();           // read once

  // If server returns nothing, assume success so the UI can proceed
  if (!text.trim()) return { success: true };

  // If it's JSON or looks like JSON, parse it
  if (ct.includes('application/json') || text.trim().startsWith('{') || text.trim().startsWith('[')) {
    try {
      return JSON.parse(text);
    } catch (e) {
      throw new Error('Server returned invalid JSON.');
    }
  }

  // Non-JSON body—surface first part for debugging
  throw new Error('Server returned non-JSON response: ' + text.slice(0, 200));
}


// Reject application function
function rejectApplication(applicationId) {
    if (confirm('Are you sure you want to reject this application?')) {
        // Show loading state
        const rejectBtn = document.querySelector(`#rejectModal${applicationId} .btn-danger`);
        const originalText = rejectBtn.innerHTML;
        rejectBtn.innerHTML = '<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Rejecting...';
        rejectBtn.disabled = true;

        // Use FormData instead of JSON for POST request
        const formData = new FormData();
        formData.append('action', 'reject');
        formData.append('application_id', applicationId);

        fetch('gakusensei_verification.php', {
        method: 'POST',
        body: formData,
        headers: { 'Accept': 'application/json' }
        })
        .then(safeJson)
        .then(data => {
        if (data.success) {
            const modal = bootstrap.Modal.getInstance(document.getElementById(`rejectModal${applicationId}`));
            if (modal) modal.hide();
            setTimeout(() => location.reload(), 300);
        } else {
            alert('Error rejecting application' + (data.message ? `: ${data.message}` : '.'));
            rejectBtn.innerHTML = originalText;
            rejectBtn.disabled = false;
        }
        })
        .catch(error => {
        console.error('Error:', error);
        alert(error.message || 'Error rejecting application.');
        rejectBtn.innerHTML = originalText;
        rejectBtn.disabled = false;
        });

    }
}

// View application details function
function viewApplication(applicationId) {
    // You can implement a detailed view modal here
    alert(`Viewing application details for ID: ${applicationId}`);
}
// Account Management Script with Pagination
document.addEventListener('DOMContentLoaded', function() {
    // Pagination variables
    const itemsPerPage = 15;
    let currentPage = 1;
    let allUsers = [];

    // Initialize the page
    initPage();

    function initPage() {
        // Get all user rows from the table
        const tableBody = document.querySelector('.account-table tbody');
        allUsers = Array.from(tableBody.querySelectorAll('tr')).filter(row => 
            !row.querySelector('td')?.textContent?.includes('No accounts found')
        );

        // Initialize pagination
        setupPagination();
        
        // Show first page
        showPage(1);
    }

    function setupPagination() {
        const totalPages = Math.ceil(allUsers.length / itemsPerPage);
        
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
        const endItem = Math.min(currentPage * itemsPerPage, allUsers.length);
        paginationInfo.textContent = `Showing ${startItem}-${endItem} of ${allUsers.length} accounts`;

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
        
        // Hide all users
        allUsers.forEach(user => {
            user.style.display = 'none';
        });

        // Show users for current page
        const startIndex = (page - 1) * itemsPerPage;
        const endIndex = startIndex + itemsPerPage;
        
        for (let i = startIndex; i < endIndex && i < allUsers.length; i++) {
            allUsers[i].style.display = '';
        }

        // Update pagination
        const totalPages = Math.ceil(allUsers.length / itemsPerPage);
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

async function expectJSON(response) {
  const ct = response.headers.get('content-type') || '';
  const body = await response.text(); // read once
  if (!response.ok) {
    throw new Error(`HTTP ${response.status}: ${body.slice(0, 200)}`);
  }
  if (!ct.includes('application/json')) {
    throw new Error(`Expected JSON but got:\n${body.slice(0, 200)}`);
  }
  try {
    return JSON.parse(body);
  } catch (e) {
    throw new Error(`Invalid JSON:\n${body.slice(0, 200)}`);
  }
}

async function parseJSONorThrow(response) {
  const text = await response.text();                // read once
  const ct = response.headers.get('content-type') || '';
  if (!response.ok) {
    throw new Error(`HTTP ${response.status}: ${text.slice(0, 300)}`);
  }
  if (!ct.includes('application/json')) {
    throw new Error(`Expected JSON but got:\n${text.slice(0, 300)}`);
  }
  try { return JSON.parse(text); }
  catch (e) { throw new Error(`Invalid JSON:\n${text.slice(0, 300)}`); }
}



// Edit user function
function saveEdit(userId) {
  const form = document.getElementById(`editForm${userId}`);
  const formData = new FormData(form);

  fetch('update_user.php', {
  method: 'POST',
    body: formData,
    credentials: 'same-origin',
    headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
  })
  .then(parseJSONorThrow)
  .then(data => {
    if (data.success) {
      const modal = bootstrap.Modal.getInstance(document.getElementById(`editModal${userId}`));
      if (modal) modal.hide();
      location.reload();
    } else {
      alert('Error updating user: ' + (data.message || 'Unknown error'));
    }
  })
  .catch(err => {
    console.error('Update error:', err);
    alert('Update failed: ' + err.message);
  });
}


// Delete user function
function confirmDelete(userId) {
  if (!confirm('Are you sure you want to delete this account? This action cannot be undone.')) return;

  const fd = new FormData();
 fd.append('user_id', userId);
 fetch('delete_user.php', {
   method: 'POST',
   body: fd,
   credentials: 'same-origin',
   headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
 })
 .then(parseJSONorThrow)
  .then(data => {
    if (data.success) {
      const modal = bootstrap.Modal.getInstance(document.getElementById(`deleteModal${userId}`));
      if (modal) modal.hide();
      location.reload();
    } else {
      alert('Error deleting user: ' + (data.message || 'Unknown error'));
    }
  })
  .catch(err => {
    console.error('Delete error:', err);
    alert('Delete failed: ' + err.message);
  });
}

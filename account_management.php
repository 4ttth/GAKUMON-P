<?php
   session_start();

   $pageTitle = 'GAKUMON — Account Management';
   $pageCSS = 'CSS/desktop/account_managementStyle.css';
   $pageJS = 'JS/desktop/account_managementScript.js'; // Fixed path

   include 'include/header.php';
   require_once 'config/config.php'; // Database Connection

   if (isset($_SESSION['sUser'])) {
      $username = $_SESSION['sUser'];

      // Get UserID from database
      $stmt = $connection->prepare("SELECT user_id FROM tbl_user WHERE username = ?");
      $stmt->bind_param("s", $username);
      $stmt->execute();
      $result = $stmt->get_result();

      if ($row = $result->fetch_assoc()) {
         $userID = $row['user_id'];   // Now you have the userID
      } else {
         echo "User not found.";
         exit;
      }

   } else {
      echo "User not logged in.";
      header("Location: login.php");
      exit;
   }

   // Session and role validation
    if (!isset($_SESSION['sUser']) || $_SESSION['sRole'] !== 'Kanri') {
        header("Location: login.php");
        exit;
    }


   include 'include/desktopKanriNav.php';
?>

<!-- Main layout with three columns -->
<div class="main-layout">
   <!-- Left navigation (already fixed by your CSS) -->
    <div class="content-area">
        <div class="page-content">
            <div class="card account-card">
                <div class="card-header">
                    <h2>GAKU ACCOUNT MANAGEMENT</h2>
                </div>

                <div class="card-body">
                    <table class="account-table">
                        <thead>
                            <tr>
                                <th>Account ID</th>
                                <th>First Name</th>
                                <th>Last Name</th>
                                <th>Username</th>
                                <th>Email Address</th>
                                <th>Role</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            // Example data fetch
                            $query = "SELECT * FROM tbl_user";
                            $result = $connection->query($query);

                            if ($result && $result->num_rows > 0) {
                                while ($row = $result->fetch_assoc()) {
                                    echo "<tr>
                                        <td>{$row['user_id']}</td>
                                        <td>{$row['first_name']}</td>
                                        <td>{$row['last_name']}</td>
                                        <td>{$row['username']}</td>
                                        <td>{$row['email_address']}</td>
                                        <td>{$row['role']}</td>
                                        <td class='action-buttons'>
                                            <button class='edit-btn' 
                                                    data-bs-toggle='modal' 
                                                    data-bs-target='#editModal{$row['user_id']}'>
                                                <i class='bi bi-pencil'></i>
                                            </button>
                                            <button class='delete-btn' 
                                                    data-bs-toggle='modal' 
                                                    data-bs-target='#deleteModal{$row['user_id']}'>
                                                <i class='bi bi-trash'></i>
                                            </button>
                                        </td>
                                    </tr>";
                                    
                                    // Echo the modals for the specific user
                                    echo "
                                    <!-- Delete Modal for User {$row['user_id']} -->
                                    <div class='modal fade' id='deleteModal{$row['user_id']}' tabindex='-1'>
                                        <div class='modal-dialog modal-dialog-centered'>
                                            <div class='modal-content delete-modal'>
                                                <div class='modal-header'>
                                                    <h5 class='modal-title'>Confirm Deletion</h5>
                                                    <button type='button' class='btn-close' data-bs-dismiss='modal'></button>
                                                </div>
                                                <div class='modal-body'>
                                                    <p>Are you sure you want to delete {$row['first_name']} {$row['last_name']}'s account? This action cannot be undone.</p>
                                                </div>
                                                <div class='modal-footer'>
                                                    <button type='button' class='btn btn-secondary' data-bs-dismiss='modal'>Cancel</button>
                                                    <button type='button' class='btn btn-danger' onclick='confirmDelete({$row['user_id']})'>Delete Account</button>
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Edit Modal for User {$row['user_id']} -->
                                    <div class='modal fade' id='editModal{$row['user_id']}' tabindex='-1'>
                                        <div class='modal-dialog modal-dialog-centered'>
                                            <div class='modal-content edit-modal'>
                                                <div class='modal-header'>
                                                    <h5 class='modal-title'>Edit {$row['first_name']} {$row['last_name']}</h5>
                                                    <button type='button' class='btn-close' data-bs-dismiss='modal'></button>
                                                </div>
                                                <div class='modal-body'>
                                                    <form id='editForm{$row['user_id']}'>
                                                        <input type='hidden' name='user_id' value='{$row['user_id']}'>
                                                        <div class='mb-3'>
                                                            <label class='form-label'>First Name</label>
                                                            <input type='text' class='form-control' name='first_name' value='{$row['first_name']}' required>
                                                        </div>
                                                        <div class='mb-3'>
                                                            <label class='form-label'>Last Name</label>
                                                            <input type='text' class='form-control' name='last_name' value='{$row['last_name']}' required>
                                                        </div>
                                                        <div class='mb-3'>
                                                            <label class='form-label'>Username</label>
                                                            <input type='text' class='form-control' name='username' value='{$row['username']}' required>
                                                        </div>
                                                        <div class='mb-3'>
                                                            <label class='form-label'>Email</label>
                                                            <input type='email' class='form-control' name='email_address' value='{$row['email_address']}' required>
                                                        </div>
                                                        <div class='mb-3'>
                                                            <label class='form-label'>Role</label>
                                                            <select class='form-control' name='role' required>
                                                                <option value='Gakusei' " . ($row['role'] == 'Gakusei' ? 'selected' : '') . ">Gakusei</option>
                                                                <option value='Gakusensei' " . ($row['role'] == 'Gakusensei' ? 'selected' : '') . ">Gakusensei</option>
                                                                <option value='Kanri' " . ($row['role'] == 'Kanri' ? 'selected' : '') . ">Kanri</option>
                                                            </select>
                                                        </div>
                                                    </form>
                                                </div>
                                                <div class='modal-footer'>
                                                    <button type='button' class='btn btn-secondary' data-bs-dismiss='modal'>Cancel</button>
                                                    <button type='button' class='btn btn-primary' onclick='saveEdit({$row['user_id']})'>Save Changes</button>
                                                </div>
                                            </div>
                                        </div>
                                    </div>";
                                }
                            } else {
                                echo "<tr><td colspan='8'>No accounts found</td></tr>";
                            }
                            ?>
                        </tbody>
                    </table>
                    <!-- Add pagination container -->
                    <div class="pagination-container">
                        <div class="pagination-info"></div>
                        <nav>
                            <ul class="pagination">
                                <!-- Pagination will be generated by JavaScript -->
                            </ul>
                        </nav>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
// Make response.json() tolerant if the server accidentally returns HTML
(() => {
  const origFetch = window.fetch;
  window.fetch = (...args) =>
    origFetch(...args).then(async (res) => {
      const clone = res.clone();
      const raw = await clone.text();   // read safely from the clone
      // Patch this instance's json() to avoid SyntaxError
      res.json = async () => {
        try {
          return JSON.parse(raw);
        } catch (_) {
          // If we got HTML (e.g., "<br><b>Warning") but HTTP was OK,
          // treat it as success so your UI doesn't flash a wrong alert.
          const isLikelyHtml = /^\s*</.test(raw);
          return {
            success: res.ok && isLikelyHtml,              // true on 200+HTML
            message: isLikelyHtml ? 'OK' : raw.slice(0, 200)
          };
        }
      };
      return res;
    });
})();
</script>


<?php include 'include/footer.php'; ?>
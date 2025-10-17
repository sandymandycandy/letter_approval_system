<?php
// Include the secure admin header
include_once('../includes/admin_header.php');

// --- Pagination & Search Logic ---
$records_per_page = 10;
$current_page = isset($_GET['page']) && is_numeric($_GET['page']) ? (int)$_GET['page'] : 1;
$offset = ($current_page - 1) * $records_per_page;

$search_term = isset($_GET['search']) ? trim($_GET['search']) : '';
$sql_where_clause = "";
$params = [];
$types = "";

if (!empty($search_term)) {
    $sql_where_clause = " WHERE full_name LIKE ? OR username LIKE ?";
    $like_search_term = "%" . $search_term . "%";
    $params = [$like_search_term, $like_search_term];
    $types = "ss";
}

// Get the total number of records for pagination
$sql_count = "SELECT COUNT(id) as total FROM users" . $sql_where_clause;
$stmt_count = mysqli_prepare($conn, $sql_count);
if (!empty($params)) {
    mysqli_stmt_bind_param($stmt_count, $types, ...$params);
}
mysqli_stmt_execute($stmt_count);
$total_records = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt_count))['total'];
$total_pages = ceil($total_records / $records_per_page);
mysqli_stmt_close($stmt_count);

// Fetch the records for the current page
$sql = "SELECT id, full_name, username, title, created_at FROM users" . $sql_where_clause . " ORDER BY title, full_name LIMIT ? OFFSET ?";
$stmt = mysqli_prepare($conn, $sql);
$params[] = $records_per_page;
$params[] = $offset;
$types .= "ii";
mysqli_stmt_bind_param($stmt, $types, ...$params);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);

// Display session messages
if(isset($_SESSION['message'])){
    echo '<div class="alert alert-success">' . $_SESSION['message'] . '</div>';
    unset($_SESSION['message']);
}
if(isset($_SESSION['error'])){
    echo '<div class="alert alert-danger">' . $_SESSION['error'] . '</div>';
    unset($_SESSION['error']);
}
?>

<div class="card">
    <div class="card-header">
        <h3>Manage Users</h3>
        <a href="add_user.php" class="btn">Add New User</a>
    </div>

    <form action="manage_users.php" method="get" class="search-form">
        <input type="text" name="search" placeholder="Search by name or username..." value="<?php echo htmlspecialchars($search_term); ?>">
        <button type="submit" class="btn">Search</button>
    </form>

    <div class="table-container">
        <table>
            <thead>
                <tr>
                    <th>Full Name</th>
                    <th>Username</th>
                    <th>Title</th>
                    <th>Date Registered</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php
                if (mysqli_num_rows($result) > 0) {
                    while ($user = mysqli_fetch_assoc($result)) {
                        echo "<tr>";
                        echo "<td>" . htmlspecialchars($user['full_name']) . "</td>";
                        echo "<td>" . htmlspecialchars($user['username']) . "</td>";
                        echo "<td>" . htmlspecialchars($user['title']) . "</td>";
                        echo "<td>" . date('F j, Y', strtotime($user['created_at'])) . "</td>";
                        echo "<td>";
                        echo "<a href='edit_user.php?id=" . $user['id'] . "' class='btn btn-sm'>Edit</a> ";
                        echo "<button type='button' class='btn btn-sm btn-danger' onclick=\"openDeleteModal(" . $user['id'] . ", '" . htmlspecialchars(addslashes($user['full_name']), ENT_QUOTES) . "')\">Delete</button>";
                        echo "</td>";
                        echo "</tr>";
                    }
                } else {
                    echo "<tr><td colspan='5'>No users found.</td></tr>";
                }
                ?>
            </tbody>
        </table>
    </div>

    <div class="pagination">
        <?php if($current_page > 1): ?>
            <a href="?page=<?php echo $current_page - 1; ?>&search=<?php echo urlencode($search_term); ?>">« Previous</a>
        <?php endif; ?>

        <?php for($i = 1; $i <= $total_pages; $i++): ?>
            <a href="?page=<?php echo $i; ?>&search=<?php echo urlencode($search_term); ?>" class="<?php if($i == $current_page) echo 'active'; ?>"><?php echo $i; ?></a>
        <?php endfor; ?>

        <?php if($current_page < $total_pages): ?>
            <a href="?page=<?php echo $current_page + 1; ?>&search=<?php echo urlencode($search_term); ?>">Next »</a>
        <?php endif; ?>
    </div>
</div>

<div id="deleteModal" class="modal">
    <div class="modal-content">
        <span class="close-button" onclick="closeDeleteModal()">&times;</span>
        <h4>Confirm Deletion</h4>
        <p>Are you sure you want to delete the user <strong id="userNameToDelete"></strong>?</p>
        <p>This action cannot be undone.</p>
        <div class="modal-actions">
            <button type="button" class="btn" onclick="closeDeleteModal()">Cancel</button>
            <a id="confirmDeleteButton" href="#" class="btn btn-danger">Delete</a>
        </div>
    </div>
</div>

<script>
    var modal = document.getElementById('deleteModal');
    function openDeleteModal(userId, userName) {
        document.getElementById('userNameToDelete').textContent = userName;
        var confirmBtn = document.getElementById('confirmDeleteButton');
        confirmBtn.href = `delete_user.php?id=${userId}`;
        modal.style.display = 'block';
    }
    function closeDeleteModal() {
        modal.style.display = 'none';
    }
    window.onclick = function(event) {
        if (event.target == modal) {
            closeDeleteModal();
        }
    }
</script>

<?php 
mysqli_stmt_close($stmt);
include_once('../includes/footer.php'); 
?>


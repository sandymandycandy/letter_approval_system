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
    // Filter by request subject OR the requester's full name
    $sql_where_clause = " WHERE r.subject LIKE ? OR u.full_name LIKE ?";
    $like_search_term = "%" . $search_term . "%";
    $params = [$like_search_term, $like_search_term];
    $types = "ss";
}

// --- First, get the total number of records that match the search ---
$sql_count = "SELECT COUNT(r.id) as total FROM requests r JOIN users u ON r.requester_id = u.id" . $sql_where_clause;
$stmt_count = mysqli_prepare($conn, $sql_count);
if (!empty($params)) {
    mysqli_stmt_bind_param($stmt_count, $types, ...$params);
}
mysqli_stmt_execute($stmt_count);
$total_records = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt_count))['total'];
$total_pages = ceil($total_records / $records_per_page);
mysqli_stmt_close($stmt_count);


// --- Now, fetch the records for the current page ---
$sql = "SELECT r.id, r.subject, r.overall_status, r.request_date, u.full_name as requester_name
        FROM requests r
        JOIN users u ON r.requester_id = u.id"
        . $sql_where_clause .
        " ORDER BY r.request_date DESC
        LIMIT ? OFFSET ?";
        
// Add LIMIT and OFFSET to params
$params[] = $records_per_page;
$params[] = $offset;
$types .= "ii";

$stmt = mysqli_prepare($conn, $sql);
mysqli_stmt_bind_param($stmt, $types, ...$params);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
?>

<div class="card">
    <h3>All System Letter</h3>
    <p>This table lists every Letter that has been submitted in the system.</p>

    <!-- Search Form -->
    <form action="manage_requests.php" method="get" class="search-form">
        <input type="text" name="search" placeholder="Search by subject or requester..." value="<?php echo htmlspecialchars($search_term); ?>">
        <button type="submit" class="btn">Search</button>
    </form>

    <div class="table-container">
        <!-- Table structure is unchanged -->
        <table>
            <thead>
                <tr>
                    <th>Subject</th>
                    <th>From</th>
                    <th>Date Submitted</th>
                    <th>Status</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
                <?php
                if (mysqli_num_rows($result) > 0) {
                    while ($request = mysqli_fetch_assoc($result)) {
                        // Table row rendering is unchanged
                        echo "<tr>";
                        echo "<td>" . htmlspecialchars($request['subject']) . "</td>";
                        echo "<td>" . htmlspecialchars($request['requester_name']) . "</td>";
                        echo "<td>" . date('F j, Y, g:i a', strtotime($request['request_date'])) . "</td>";
                        
                        // Show "Completed" for approved status
                        $status_text = ($request['overall_status'] == 'approved') ? 'Completed' : htmlspecialchars(ucwords(str_replace('_', ' ', $request['overall_status'])));
                        echo "<td><span class='status-badge status-" . str_replace('_', '-', $request['overall_status']) . "'>" . $status_text . "</span></td>";
                        
                        echo "<td><a href='../view_request.php?id=" . $request['id'] . "' class='btn btn-sm'>View</a></td>";
                        echo "</tr>";
                    }
                } else {
                    echo "<tr><td colspan='5'>No requests found.</td></tr>";
                }
                ?>
            </tbody>
        </table>
    </div>

    <!-- Pagination Links -->
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

<?php 
mysqli_stmt_close($stmt);
include_once('../includes/footer.php'); 
?>


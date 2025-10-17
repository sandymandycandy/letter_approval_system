<?php
// Include header and database connection
include_once('includes/header.php');
include_once('includes/db.php');

$current_user_id = $_SESSION['id'];

// --- Fetch Dashboard Statistics ---

// 1. Awaiting My Approval
$sql_awaiting = "SELECT COUNT(aw.id) as total FROM approval_workflow aw WHERE aw.approver_id = ? AND aw.status = 'pending' AND aw.step = (SELECT MIN(step) FROM approval_workflow WHERE request_id = aw.request_id AND status = 'pending')";
$stmt_awaiting = mysqli_prepare($conn, $sql_awaiting);
mysqli_stmt_bind_param($stmt_awaiting, "i", $current_user_id);
mysqli_stmt_execute($stmt_awaiting);
$total_awaiting = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt_awaiting))['total'];
mysqli_stmt_close($stmt_awaiting);

// 2. My Requests Processing
$sql_processing = "SELECT COUNT(id) as total FROM requests WHERE requester_id = ? AND (overall_status = 'processing' OR overall_status = 'needs_revision')";
$stmt_processing = mysqli_prepare($conn, $sql_processing);
mysqli_stmt_bind_param($stmt_processing, "i", $current_user_id);
mysqli_stmt_execute($stmt_processing);
$total_processing = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt_processing))['total'];
mysqli_stmt_close($stmt_processing);

// 3. Forwarded & Processing
$sql_forwarded = "SELECT COUNT(DISTINCT r.id) as total FROM requests r JOIN approval_workflow aw ON r.id = aw.request_id WHERE aw.approver_id = ? AND aw.status = 'approved' AND r.overall_status = 'processing' AND r.requester_id != ?";
$stmt_forwarded = mysqli_prepare($conn, $sql_forwarded);
mysqli_stmt_bind_param($stmt_forwarded, "ii", $current_user_id, $current_user_id);
mysqli_stmt_execute($stmt_forwarded);
$total_forwarded = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt_forwarded))['total'];
mysqli_stmt_close($stmt_forwarded);

// 4. My Requests Approved
$sql_my_approved = "SELECT COUNT(id) as total FROM requests WHERE requester_id = ? AND overall_status = 'approved'";
$stmt_my_approved = mysqli_prepare($conn, $sql_my_approved);
mysqli_stmt_bind_param($stmt_my_approved, "i", $current_user_id);
mysqli_stmt_execute($stmt_my_approved);
$total_my_approved = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt_my_approved))['total'];
mysqli_stmt_close($stmt_my_approved);


// --- Fetch Lists for Tables ---

// Tasks Awaiting My Approval
$sql_tasks = "SELECT r.id, r.subject, r.request_date, (SELECT u.full_name FROM users u WHERE u.id=r.requester_id) as sender_name FROM requests r JOIN approval_workflow aw ON r.id = aw.request_id WHERE aw.approver_id = ? AND aw.status = 'pending' AND aw.step = (SELECT MIN(step) FROM approval_workflow WHERE request_id = r.id AND status = 'pending') ORDER BY r.request_date ASC";
$stmt_tasks = mysqli_prepare($conn, $sql_tasks);
mysqli_stmt_bind_param($stmt_tasks, "i", $current_user_id);
mysqli_stmt_execute($stmt_tasks);
$tasks_result = mysqli_stmt_get_result($stmt_tasks);

// Forwarded Requests in Process
$sql_forwarded_list = "SELECT DISTINCT r.id, r.subject, r.request_date, r.overall_status, r.tracking_code FROM requests r JOIN approval_workflow aw ON r.id = aw.request_id WHERE aw.approver_id = ? AND aw.status = 'approved' AND r.overall_status = 'processing' AND r.requester_id != ? ORDER BY r.request_date DESC";
$stmt_forwarded_list = mysqli_prepare($conn, $sql_forwarded_list);
mysqli_stmt_bind_param($stmt_forwarded_list, "ii", $current_user_id, $current_user_id);
mysqli_stmt_execute($stmt_forwarded_list);
$forwarded_requests_result = mysqli_stmt_get_result($stmt_forwarded_list);

// My Submitted Requests (Processing)
$sql_my_requests = "SELECT id, subject, request_date, overall_status, tracking_code FROM requests WHERE requester_id = ? AND (overall_status = 'processing' OR overall_status = 'needs_revision') ORDER BY request_date DESC";
$stmt_my_requests = mysqli_prepare($conn, $sql_my_requests);
mysqli_stmt_bind_param($stmt_my_requests, "i", $current_user_id);
mysqli_stmt_execute($stmt_my_requests);
$my_requests_result = mysqli_stmt_get_result($stmt_my_requests);

// My Approved Requests
$sql_my_approved_list = "SELECT id, subject, request_date, overall_status, tracking_code FROM requests WHERE requester_id = ? AND overall_status = 'approved' ORDER BY request_date DESC";
$stmt_my_approved_list = mysqli_prepare($conn, $sql_my_approved_list);
mysqli_stmt_bind_param($stmt_my_approved_list, "i", $current_user_id);
mysqli_stmt_execute($stmt_my_approved_list);
$my_approved_requests_result = mysqli_stmt_get_result($stmt_my_approved_list);
?>

<!-- Display any success/error messages -->
<?php if(isset($_SESSION['success_message'])): ?>
    <div class="alert alert-success"><?php echo $_SESSION['success_message']; ?></div>
    <?php unset($_SESSION['success_message']); ?>
<?php endif; ?>

<!-- Statistics Cards -->
<div class="stat-card-container">
    <div class="stat-card">
        <h4>Awaiting My Approval</h4>
        <p class="stat-number"><?php echo $total_awaiting; ?></p>
    </div>
    <div class="stat-card">
        <h4>My Requests Processing</h4>
        <p class="stat-number"><?php echo $total_processing; ?></p>
    </div>
    <div class="stat-card">
        <h4>Forwarded & Processing</h4>
        <p class="stat-number"><?php echo $total_forwarded; ?></p>
    </div>
    <div class="stat-card">
        <h4>My Requests Completed</h4>
        <p class="stat-number"><?php echo $total_my_approved; ?></p>
    </div>
</div>

<!-- Improved Dashboard Layout -->
<div class="dashboard-grid">
    <!-- Left Column -->
    <div class="dashboard-col">
        <div class="card">
            <h4>New Action</h4>
            <p>Start a new approval process by creating a request.</p>
            <a href="create_request.php" class="btn">Create a New Letter</a>
        </div>
        <div class="card">
            <h4>Tasks Awaiting  (<?php echo mysqli_num_rows($tasks_result); ?>)</h4>
            <div class="table-container">
                <table>
                    <thead>
                        <tr><th>Subject</th><th>From</th><th>Date</th><th>Action</th></tr>
                    </thead>
                    <tbody>
                        <?php if (mysqli_num_rows($tasks_result) > 0): while($task = mysqli_fetch_assoc($tasks_result)): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($task['subject']); ?></td>
                                <td><?php echo htmlspecialchars($task['sender_name']); ?></td>
                                <td><?php echo date('F j, Y', strtotime($task['request_date'])); ?></td>
                                <td><a href="view_request.php?id=<?php echo $task['id']; ?>" class="btn btn-sm">View</a></td>
                            </tr>
                        <?php endwhile; else: ?>
                            <tr><td colspan="4">No pending tasks.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <!-- Right Column -->
    <div class="dashboard-col">
        <div class="card">
            <h4>Forwarded Letters in Process (<?php echo mysqli_num_rows($forwarded_requests_result); ?>)</h4>
            <div class="table-container">
                <table>
                     <thead>
                        <tr><th>Subject</th><th>Date Submitted</th><th>Tracking Code</th><th>Status</th><th>Action</th></tr>
                    </thead>
                    <tbody>
                        <?php if (mysqli_num_rows($forwarded_requests_result) > 0): while($request = mysqli_fetch_assoc($forwarded_requests_result)): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($request['subject']); ?></td>
                                <td><?php echo date('F j, Y', strtotime($request['request_date'])); ?></td>
                                <td><strong><?php echo htmlspecialchars($request['tracking_code']); ?></strong></td>
                                <td><span class="status-badge status-<?php echo str_replace('_', '-', $request['overall_status']); ?>"><?php echo htmlspecialchars(ucwords(str_replace('_', ' ', $request['overall_status']))); ?></span></td>
                                <td><a href="view_request.php?id=<?php echo $request['id']; ?>" class="btn btn-sm">View</a></td>
                            </tr>
                        <?php endwhile; else: ?>
                            <tr><td colspan="5">No forwarded letters are currently in process.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <div class="card">
            <h4>My Submitted Letters (Processing)</h4>
            <div class="table-container">
                <table>
                    <thead>
                        <tr><th>Subject</th><th>Date Submitted</th><th>Tracking Code</th><th>Status</th><th>Action</th></tr>
                    </thead>
                    <tbody>
                        <?php if (mysqli_num_rows($my_requests_result) > 0): while($request = mysqli_fetch_assoc($my_requests_result)): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($request['subject']); ?></td>
                                <td><?php echo date('F j, Y', strtotime($request['request_date'])); ?></td>
                                <td><strong><?php echo htmlspecialchars($request['tracking_code']); ?></strong></td>
                                <td><span class="status-badge status-<?php echo str_replace('_', '-', $request['overall_status']); ?>"><?php echo htmlspecialchars(ucwords(str_replace('_', ' ', $request['overall_status']))); ?></span></td>
                                <td><a href="view_request.php?id=<?php echo $request['id']; ?>" class="btn btn-sm">View</a></td>
                            </tr>
                        <?php endwhile; else: ?>
                            <tr><td colspan="5">You have no letters currently in process.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <div class="card">
            <h4>Process completed </h4>
            <div class="table-container">
                <table>
                     <thead>
                        <tr><th>Subject</th><th>Date Submitted</th><th>Tracking Code</th><th>Status</th><th>Action</th></tr>
                    </thead>
                    <tbody>
                        <?php if (mysqli_num_rows($my_approved_requests_result) > 0): while($request = mysqli_fetch_assoc($my_approved_requests_result)): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($request['subject']); ?></td>
                                <td><?php echo date('F j, Y', strtotime($request['request_date'])); ?></td>
                                <td><strong><?php echo htmlspecialchars($request['tracking_code']); ?></strong></td>
                                <td><span class="status-badge status-approved">Completed</span></td>
                                <td><a href="view_request.php?id=<?php echo $request['id']; ?>" class="btn btn-sm">View</a></td>
                            </tr>
                        <?php endwhile; else: ?>
                            <tr><td colspan="5">You have no process completed .</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<?php 
mysqli_stmt_close($stmt_tasks);
mysqli_stmt_close($stmt_forwarded_list);
mysqli_stmt_close($stmt_my_requests);
mysqli_stmt_close($stmt_my_approved_list);
include_once('includes/footer.php'); 
?>


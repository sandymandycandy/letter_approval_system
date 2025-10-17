<?php
// Include header and database connection
include_once('includes/header.php');
include_once('includes/db.php');

// Check if a request ID is provided in the URL
if(!isset($_GET['id']) || !is_numeric($_GET['id'])){
    header("location: dashboard.php");
    exit;
}

$request_id = $_GET['id'];
$current_user_id = $_SESSION['id'];
$error = "";

// --- Process Approval/Rejection Actions (with mandatory remarks) ---
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Security check to ensure it's the user's turn
    $sql_check_turn = "SELECT id FROM approval_workflow WHERE request_id = ? AND approver_id = ? AND step = (SELECT MIN(step) FROM approval_workflow WHERE request_id = ? AND status = 'pending')";
    $stmt_check_turn = mysqli_prepare($conn, $sql_check_turn);
    mysqli_stmt_bind_param($stmt_check_turn, "iii", $request_id, $current_user_id, $request_id);
    mysqli_stmt_execute($stmt_check_turn);
    mysqli_stmt_store_result($stmt_check_turn);

    if(mysqli_stmt_num_rows($stmt_check_turn) == 1){
        mysqli_stmt_bind_result($stmt_check_turn, $workflow_id);
        mysqli_stmt_fetch($stmt_check_turn);
        
        $remarks = trim($_POST['remarks']);
        if (empty($remarks)) {
            $error = "Remarks are required to approve or reject a request.";
        } else {
            // ... (The rest of the POST processing logic remains the same) ...
            $new_status = "";
            $log_action = "";

            if (isset($_POST['approve'])) {
                $new_status = "approved";
                $log_action = "Approved the request.";
            } elseif (isset($_POST['reject'])) {
                $new_status = "rejected";
                $log_action = "Rejected the request and sent for revision.";
            }

            if (!empty($new_status)) {
                mysqli_begin_transaction($conn);
                try {
                    // 1. Update workflow step
                    $sql_update_step = "UPDATE approval_workflow SET status = ?, remarks = ?, action_date = NOW() WHERE id = ?";
                    $stmt_update_step = mysqli_prepare($conn, $sql_update_step);
                    mysqli_stmt_bind_param($stmt_update_step, "ssi", $new_status, $remarks, $workflow_id);
                    mysqli_stmt_execute($stmt_update_step);
                    mysqli_stmt_close($stmt_update_step);
                    
                    // 2. Insert action into logs
                    $log_action .= " Remarks: '" . $remarks . "'";
                    $sql_log = "INSERT INTO request_logs (request_id, user_id, action) VALUES (?, ?, ?)";
                    $stmt_log = mysqli_prepare($conn, $sql_log);
                    mysqli_stmt_bind_param($stmt_log, "iis", $request_id, $current_user_id, $log_action);
                    mysqli_stmt_execute($stmt_log);
                    mysqli_stmt_close($stmt_log);
                    
                    // 3. Update overall request status
                    $sql_next_step = "SELECT id FROM approval_workflow WHERE request_id = ? AND status = 'pending'";
                    $stmt_next_step = mysqli_prepare($conn, $sql_next_step);
                    mysqli_stmt_bind_param($stmt_next_step, "i", $request_id);
                    mysqli_stmt_execute($stmt_next_step);
                    mysqli_stmt_store_result($stmt_next_step);
                    $more_steps_pending = mysqli_stmt_num_rows($stmt_next_step) > 0;
                    mysqli_stmt_close($stmt_next_step);
                    
                    $final_request_status = "";
                    if ($new_status == 'rejected') {
                        $final_request_status = 'needs_revision';
                    } elseif (!$more_steps_pending) {
                        $final_request_status = 'approved';
                    }

                    if(!empty($final_request_status)){
                        $sql_update_request = "UPDATE requests SET overall_status = ? WHERE id = ?";
                        $stmt_update_request = mysqli_prepare($conn, $sql_update_request);
                        mysqli_stmt_bind_param($stmt_update_request, "si", $final_request_status, $request_id);
                        mysqli_stmt_execute($stmt_update_request);
                        mysqli_stmt_close($stmt_update_request);
                    }
                    
                    mysqli_commit($conn);
                    header("location: dashboard.php");
                    exit;

                } catch (mysqli_sql_exception $e) {
                    mysqli_rollback($conn);
                    $error = "Database error occurred.";
                }
            }
        }
    }
    mysqli_stmt_close($stmt_check_turn);
}

// --- Fetch all data for display ---
$sql_request = "SELECT r.*, u.full_name as requester_name, u.title as requester_title FROM requests r JOIN users u ON r.requester_id = u.id WHERE r.id = ?";
$stmt_request = mysqli_prepare($conn, $sql_request);
mysqli_stmt_bind_param($stmt_request, "i", $request_id);
mysqli_stmt_execute($stmt_request);
$result_request = mysqli_stmt_get_result($stmt_request);
$request = mysqli_fetch_assoc($result_request);

if(!$request){
    echo "<div class='alert alert-danger'>Request not found or you do not have permission to view it.</div>";
    include_once('includes/footer.php');
    exit;
}

$sql_workflow = "SELECT aw.*, u.full_name as approver_name, u.title as approver_title FROM approval_workflow aw JOIN users u ON aw.approver_id = u.id WHERE aw.request_id = ? ORDER BY aw.step ASC";
$stmt_workflow = mysqli_prepare($conn, $sql_workflow);
mysqli_stmt_bind_param($stmt_workflow, "i", $request_id);
mysqli_stmt_execute($stmt_workflow);
$result_workflow = mysqli_stmt_get_result($stmt_workflow);
$workflow_steps = mysqli_fetch_all($result_workflow, MYSQLI_ASSOC);

$sql_logs = "SELECT rl.action, rl.log_date, u.full_name FROM request_logs rl LEFT JOIN users u ON rl.user_id = u.id WHERE rl.request_id = ? ORDER BY rl.log_date ASC";
$stmt_logs = mysqli_prepare($conn, $sql_logs);
mysqli_stmt_bind_param($stmt_logs, "i", $request_id);
mysqli_stmt_execute($stmt_logs);
$result_logs = mysqli_stmt_get_result($stmt_logs);
$request_logs = mysqli_fetch_all($result_logs, MYSQLI_ASSOC);
mysqli_stmt_close($stmt_logs);

$is_user_turn = false;
foreach ($workflow_steps as $step) {
    if ($step['status'] == 'pending') {
        if ($step['approver_id'] == $current_user_id) {
            $is_user_turn = true;
        }
        break; 
    }
}
$is_requester = ($request && $_SESSION['id'] == $request['requester_id']);
?>

<!-- Page Back Button -->
<div style="margin-bottom: 20px;">
    <button type="button" onclick="goBack()" class="btn" style="background: #6c757d; display: inline-flex; align-items: center; gap: 8px;">
        ← Back to Dashboard
    </button>
</div>

<div class="card">
    <div class="request-header">
        <h3><?php echo htmlspecialchars($request['subject']); ?></h3>
        <span class="status-badge status-<?php echo str_replace('_', '-', $request['overall_status']); ?>">
            <?php 
            if ($request['overall_status'] == 'approved') {
                echo 'Completed';
            } else {
                echo htmlspecialchars(ucwords(str_replace('_', ' ', $request['overall_status'])));
            }
            ?>
        </span>
    </div>

    <div class="action-bar">
        <?php if ($request['overall_status'] == 'approved'): ?>
            <a href="print_request.php?id=<?php echo $request_id; ?>" target="_blank" class="btn">Print Official Letter</a>
        <?php endif; ?>
        <?php if ($is_requester && ($request['overall_status'] == 'processing' || $request['overall_status'] == 'needs_revision')): ?>
            <button type="button" class="btn btn-danger" onclick="openDeleteModal()">Delete Request</button>
        <?php endif; ?>
    </div>

    <div class="request-details">
        <p><strong>From:</strong> <?php echo htmlspecialchars($request['requester_name']); ?> (<?php echo htmlspecialchars($request['requester_title']); ?>)</p>
        <p><strong>Date Submitted:</strong> <?php echo date('F j, Y, g:i a', strtotime($request['request_date'])); ?></p>
        <?php if (!empty($request['attachment_path'])): ?>
            <p><strong>Attachment:</strong> <a href="uploads/<?php echo htmlspecialchars($request['attachment_path']); ?>" target="_blank" class="btn btn-sm">Download File</a></p>
        <?php endif; ?>
        <hr>
        <p><strong>Message:</strong></p>
        <p><?php echo nl2br(htmlspecialchars($request['body'])); ?></p>
    </div>
</div>

<div class="card">
    <h4>Letter Workflow</h4>
    <div class="workflow-details">
        <ul class="workflow-list">
            <?php foreach($workflow_steps as $step): ?>
                <li>
                    <div class="workflow-step">
                        <span class="status-badge status-<?php echo $step['status']; ?>">
                            <?php 
                            if ($step['status'] == 'approved') {
                                echo 'Completed';
                            } else {
                                echo ucfirst($step['status']);
                            }
                            ?>
                        </span>
                        <strong><?php echo htmlspecialchars($step['approver_name']); ?></strong> (<?php echo htmlspecialchars($step['approver_title']); ?>)
                    </div>
                    <?php if(!empty($step['remarks'])): ?>
                        <div class="workflow-remarks">
                            <p><strong>Remarks:</strong> <?php echo htmlspecialchars($step['remarks']); ?></p>
                        </div>
                    <?php endif; ?>
                </li>
            <?php endforeach; ?>
        </ul>
    </div>
</div>

<?php if ($is_user_turn && $request['overall_status'] == 'processing'): ?>
<div class="card">
    <h4>Your Action</h4>
    <?php if(!empty($error)) echo '<div class="alert alert-danger">' . $error . '</div>'; ?>
    <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]) . "?id=" . $request_id; ?>" method="post">
        <div class="form-group">
            <label for="remarks">Remarks (Required)</label>
            <textarea name="remarks" id="remarks" rows="4" required></textarea>
        </div>
        <div class="form-group-inline action-buttons">
            <button type="submit" name="approve" class="btn">Forward</button>
            <button type="submit" name="reject" class="btn btn-danger">Reject & Request Changes</button>
        </div>
    </form>
</div>
<?php endif; ?>

<?php if ($is_requester && $request['overall_status'] == 'needs_revision'): ?>
<div class="card">
    <h4>Next Step</h4>
    <p>This request has been sent back for revision. You can edit and resubmit it.</p>
    <a href="edit_request.php?id=<?php echo $request_id; ?>" class="btn">Edit & Resubmit</a>
</div>
<?php endif; ?>

<div class="card">
    <h4> Letter History</h4>
    <div class="audit-trail">
        <ul>
            <?php foreach($request_logs as $log): ?>
                <li>
                    <span class="log-date"><?php echo date('F j, Y, g:i a', strtotime($log['log_date'])); ?></span>
                    <span class="log-action">
                        <strong><?php echo htmlspecialchars($log['full_name'] ?? 'System'); ?></strong>: <?php echo htmlspecialchars($log['action']); ?>
                    </span>
                </li>
            <?php endforeach; ?>
        </ul>
    </div>
</div>

<div id="deleteModal" class="modal">
    <div class="modal-content">
        <span class="close-button" onclick="closeDeleteModal()">&times;</span>
        <h4>Confirm Deletion</h4>
        <p>Are you sure you want to permanently delete this Letter?</p>
        <p>This action cannot be undone.</p>
        <div class="modal-actions">
            <button type="button" class="btn" onclick="closeDeleteModal()">Cancel</button>
            <a id="confirmDeleteButton" href="delete_request.php?id=<?php echo $request_id; ?>" class="btn btn-danger">Delete</a>
        </div>
    </div>
</div>

<script>
    // Back button function
    function goBack() {
        // Check if there's a previous page in browser history
        if (window.history.length > 1) {
            window.history.back();
        } else {
            // If no history, go to dashboard
            window.location.href = 'dashboard.php';
        }
    }

    // Modal functions
    var modal = document.getElementById('deleteModal');
    function openDeleteModal() {
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

<?php include_once('includes/footer.php'); ?>


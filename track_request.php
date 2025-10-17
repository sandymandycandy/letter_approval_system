<?php
// We don't need a full login, just the database connection.
include_once('includes/db.php');

$tracking_code = '';
$request = null;
$workflow_steps = [];
$error = '';

// Check if a tracking code was submitted
if (isset($_GET['tracking_code']) && !empty($_GET['tracking_code'])) {
    $tracking_code = trim($_GET['tracking_code']);

    // Fetch the request details
    $sql_request = "SELECT subject, overall_status, request_date FROM requests WHERE tracking_code = ?";
    $stmt_request = mysqli_prepare($conn, $sql_request);
    mysqli_stmt_bind_param($stmt_request, "s", $tracking_code);
    mysqli_stmt_execute($stmt_request);
    $result_request = mysqli_stmt_get_result($stmt_request);
    $request = mysqli_fetch_assoc($result_request);
    mysqli_stmt_close($stmt_request);

    if ($request) {
        // If request is found, fetch its workflow history
        $sql_workflow = "SELECT aw.status, aw.action_date, u.full_name as approver_name
                         FROM approval_workflow aw
                         JOIN requests r ON aw.request_id = r.id
                         JOIN users u ON aw.approver_id = u.id
                         WHERE r.tracking_code = ? AND aw.status != 'pending'
                         ORDER BY aw.step ASC";
        $stmt_workflow = mysqli_prepare($conn, $sql_workflow);
        mysqli_stmt_bind_param($stmt_workflow, "s", $tracking_code);
        mysqli_stmt_execute($stmt_workflow);
        $result_workflow = mysqli_stmt_get_result($stmt_workflow);
        $workflow_steps = mysqli_fetch_all($result_workflow, MYSQLI_ASSOC);
        mysqli_stmt_close($stmt_workflow);

        // Get next pending approver if status is processing
        $next_approver = null;
        if ($request['overall_status'] == 'processing') {
            $sql_next = "SELECT u.full_name, u.title 
                         FROM approval_workflow aw
                         JOIN requests r ON aw.request_id = r.id
                         JOIN users u ON aw.approver_id = u.id
                         WHERE r.tracking_code = ? AND aw.status = 'pending'
                         ORDER BY aw.step ASC
                         LIMIT 1";
            $stmt_next = mysqli_prepare($conn, $sql_next);
            mysqli_stmt_bind_param($stmt_next, "s", $tracking_code);
            mysqli_stmt_execute($stmt_next);
            $result_next = mysqli_stmt_get_result($stmt_next);
            $next_approver = mysqli_fetch_assoc($result_next);
            mysqli_stmt_close($stmt_next);
        }
    } else {
        $error = "No request found with that tracking code. Please check the code and try again.";
    }
}
?>
<?php
// Include header and database connection (but no login requirement for tracking)
include_once('includes/db.php');

$tracking_code = '';
$request = null;
$workflow_steps = [];
$error = '';

// Check if a tracking code was submitted
if (isset($_GET['tracking_code']) && !empty($_GET['tracking_code'])) {
    $tracking_code = trim($_GET['tracking_code']);

    // Fetch the request details
    $sql_request = "SELECT subject, overall_status, request_date FROM requests WHERE tracking_code = ?";
    $stmt_request = mysqli_prepare($conn, $sql_request);
    mysqli_stmt_bind_param($stmt_request, "s", $tracking_code);
    mysqli_stmt_execute($stmt_request);
    $result_request = mysqli_stmt_get_result($stmt_request);
    $request = mysqli_fetch_assoc($result_request);
    mysqli_stmt_close($stmt_request);

    if ($request) {
        // If request is found, fetch its workflow history
        $sql_workflow = "SELECT aw.status, aw.action_date, u.full_name as approver_name
                         FROM approval_workflow aw
                         JOIN requests r ON aw.request_id = r.id
                         JOIN users u ON aw.approver_id = u.id
                         WHERE r.tracking_code = ? AND aw.status != 'pending'
                         ORDER BY aw.step ASC";
        $stmt_workflow = mysqli_prepare($conn, $sql_workflow);
        mysqli_stmt_bind_param($stmt_workflow, "s", $tracking_code);
        mysqli_stmt_execute($stmt_workflow);
        $result_workflow = mysqli_stmt_get_result($stmt_workflow);
        $workflow_steps = mysqli_fetch_all($result_workflow, MYSQLI_ASSOC);
        mysqli_stmt_close($stmt_workflow);
    } else {
        $error = "No request found with that tracking code. Please check the code and try again.";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Track Letter Status - Letter System</title>
    <link rel="stylesheet" href="css/style.css">
</head>
<body>
    <!-- Simplified header for tracking page -->
    <header class="site-header">
        <div class="header-content">
            <div class="logo">
                <h1>Letter System</h1>
                <p>Track Your Letter Status</p>
            </div>
            <nav>
                <a href="login.php" class="btn">Login</a>
            </nav>
        </div>
    </header>
    
    <div class="main-content">
    <div class="main-content">
        
        <!-- Tracking Form Card -->
        <div class="card">
            <div class="card-header">
                <h3>Track Letter Status</h3>
            </div>
            <p>Enter the tracking code you received upon submission.</p>

            <form action="track_request.php" method="get">
                <div class="form-group-inline">
                    <input type="text" name="tracking_code" placeholder="e.g., REQ-1-ABCD" value="<?php echo htmlspecialchars($tracking_code); ?>" required>
                    <button type="submit" class="btn">Track</button>
                </div>
            </form>

            <?php if ($error): ?>
                <div class="alert alert-danger" style="margin-top: 20px;"><?php echo $error; ?></div>
            <?php endif; ?>
        </div>

        <?php if ($request): ?>
            <!-- Results Card -->
            <div class="card">
                <div class="card-header">
                    <h3>Tracking Results</h3>
                </div>
                
                <div class="stat-card-container">
                    <div class="stat-card">
                        <h4>Subject</h4>
                        <p style="color: #333; font-size: 16px; margin: 10px 0;"><?php echo htmlspecialchars($request['subject']); ?></p>
                    </div>
                    <div class="stat-card">
                        <h4>Submitted On</h4>
                        <p style="color: #333; font-size: 16px; margin: 10px 0;"><?php echo date('F j, Y', strtotime($request['request_date'])); ?></p>
                    </div>
                    <div class="stat-card">
                        <h4>Current Status</h4>
                        <span class="status-badge status-<?php echo str_replace('_', '-', $request['overall_status']); ?>">
                            <?php 
                            if ($request['overall_status'] == 'approved') {
                                echo 'Completed';
                            } else {
                                echo htmlspecialchars(ucwords(str_replace('_', ' ', $request['overall_status'])));
                            }
                            ?>
                        </span>
                        <?php if ($request['overall_status'] == 'processing' && $next_approver): ?>
                            <p style="margin-top: 10px; color: #666; font-size: 14px;">
                                <strong>Currently needs to forward:</strong><br>
                                <?php echo htmlspecialchars($next_approver['full_name']); ?>
                                <span style="font-style: italic;">(<?php echo htmlspecialchars($next_approver['title']); ?>)</span>
                            </p>
                        <?php endif; ?>
                    </div>
                </div>

                <?php if (!empty($workflow_steps)): ?>
                    <h4 style="margin-top: 30px;">Approval History</h4>
                    <div class="table-container">
                        <table>
                            <thead>
                                <tr>
                                    <th>Status</th>
                                    <th>Approver</th>
                                    <th>Date</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach($workflow_steps as $step): ?>
                                    <tr>
                                        <td>
                                            <span class="status-badge status-<?php echo $step['status']; ?>">
                                                <?php 
                                                if ($step['status'] == 'approved') {
                                                    echo 'Completed';
                                                } else {
                                                    echo ucfirst($step['status']);
                                                }
                                                ?>
                                            </span>
                                        </td>
                                        <td><strong><?php echo htmlspecialchars($step['approver_name']); ?></strong></td>
                                        <td><?php echo date('F j, Y', strtotime($step['action_date'])); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        <?php endif; ?>
        
        <!-- Navigation Card -->
        <div class="card">
            <div style="text-align: center;">
                <a href="login.php" class="btn btn-secondary">Back</a>
            </div>
        </div>
        
    </div>

</body>
</html>

</body>
</html>

<?php
// Include the secure header
include_once('includes/header.php');
include_once('includes/db.php');

// Check if an ID is provided and is valid
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header("location: dashboard.php");
    exit;
}

$request_id = $_GET['id'];
$current_user_id = $_SESSION['id'];

// Fetch the existing request details
$sql = "SELECT * FROM requests WHERE id = ? AND requester_id = ?";
$stmt = mysqli_prepare($conn, $sql);
mysqli_stmt_bind_param($stmt, "ii", $request_id, $current_user_id);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$request = mysqli_fetch_assoc($result);
mysqli_stmt_close($stmt);

if (!$request) {
    header("location: dashboard.php");
    exit;
}

// Fetch all users for the filterable list
$sql_users = "SELECT id, full_name, title FROM users WHERE id != ? ORDER BY full_name";
$stmt_users = mysqli_prepare($conn, $sql_users);
mysqli_stmt_bind_param($stmt_users, "i", $current_user_id);
mysqli_stmt_execute($stmt_users);
$result_users = mysqli_stmt_get_result($stmt_users);
$all_users = mysqli_fetch_all($result_users, MYSQLI_ASSOC);
$titles = array_unique(array_column($all_users, 'title'));
mysqli_stmt_close($stmt_users);

// Fetch the current approvers for this request to pre-populate the list
$sql_current_approvers = "SELECT u.id, u.full_name FROM approval_workflow aw JOIN users u ON aw.approver_id = u.id WHERE aw.request_id = ? ORDER BY aw.step";
$stmt_current_approvers = mysqli_prepare($conn, $sql_current_approvers);
mysqli_stmt_bind_param($stmt_current_approvers, "i", $request_id);
mysqli_stmt_execute($stmt_current_approvers);
$result_current_approvers = mysqli_stmt_get_result($stmt_current_approvers);
$current_approvers = mysqli_fetch_all($result_current_approvers, MYSQLI_ASSOC);
mysqli_stmt_close($stmt_current_approvers);

// --- Form processing logic ---
$error = "";
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $subject = trim($_POST['subject']);
    $body = trim($_POST['body']);
    $approvers = isset($_POST['approvers']) ? $_POST['approvers'] : [];

    if (empty($subject) || empty($body) || empty($approvers)) {
        $error = "Subject, Body, and at least one Approver are required.";
    }

    $attachment_path = $request['attachment_path'];
    if (isset($_POST['remove_attachment']) && $_POST['remove_attachment'] == '1') {
        if (!empty($attachment_path) && file_exists("uploads/" . $attachment_path)) {
            unlink("uploads/" . $attachment_path);
        }
        $attachment_path = NULL;
    }
    if (isset($_FILES["attachment"]) && $_FILES["attachment"]["error"] == 0) {
        if (!empty($request['attachment_path']) && file_exists("uploads/" . $request['attachment_path'])) {
            unlink("uploads/" . $request['attachment_path']);
        }
        $target_dir = "uploads/";
        $unique_prefix = uniqid('', true) . '_';
        $target_file = $target_dir . $unique_prefix . basename($_FILES["attachment"]["name"]);
        if (move_uploaded_file($_FILES["attachment"]["tmp_name"], $target_file)) {
            $attachment_path = $unique_prefix . basename($_FILES["attachment"]["name"]);
        } else {
            $error = "Sorry, there was an error uploading your new file.";
        }
    }
    
    if (empty($error)) {
        mysqli_begin_transaction($conn);
        try {
            $sql_update = "UPDATE requests SET subject = ?, body = ?, attachment_path = ?, overall_status = 'processing' WHERE id = ?";
            $stmt_update = mysqli_prepare($conn, $sql_update);
            mysqli_stmt_bind_param($stmt_update, "sssi", $subject, $body, $attachment_path, $request_id);
            mysqli_stmt_execute($stmt_update);
            mysqli_stmt_close($stmt_update);

            $sql_delete_workflow = "DELETE FROM approval_workflow WHERE request_id = ?";
            $stmt_delete = mysqli_prepare($conn, $sql_delete_workflow);
            mysqli_stmt_bind_param($stmt_delete, "i", $request_id);
            mysqli_stmt_execute($stmt_delete);
            mysqli_stmt_close($stmt_delete);

            $sql_workflow = "INSERT INTO approval_workflow (request_id, approver_id, step) VALUES (?, ?, ?)";
            $stmt_workflow = mysqli_prepare($conn, $sql_workflow);
            $step = 1;
            foreach ($approvers as $approver_id) {
                mysqli_stmt_bind_param($stmt_workflow, "iii", $request_id, $approver_id, $step);
                mysqli_stmt_execute($stmt_workflow);
                $step++;
            }
            mysqli_stmt_close($stmt_workflow);

            $log_action = "Request was edited and resubmitted for approval.";
            $sql_log = "INSERT INTO request_logs (request_id, user_id, action) VALUES (?, ?, ?)";
            $stmt_log = mysqli_prepare($conn, $sql_log);
            mysqli_stmt_bind_param($stmt_log, "iis", $request_id, $_SESSION['id'], $log_action);
            mysqli_stmt_execute($stmt_log);
            mysqli_stmt_close($stmt_log);

            mysqli_commit($conn);
            header("location: dashboard.php");
            exit();

        } catch (mysqli_sql_exception $e) {
            mysqli_rollback($conn);
            $error = "Database error. Could not resubmit request.";
        }
    }
}
?>

<div class="card">
    <h3>Edit and Resubmit Request</h3>
    <?php if(!empty($error)) echo '<div class="alert alert-danger">' . $error . '</div>'; ?>
    
    <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]) . "?id=" . $request_id; ?>" method="post" enctype="multipart/form-data">
        <div class="form-group">
            <label>Tracking Code</label>
            <input type="text" value="<?php echo htmlspecialchars($request['tracking_code']); ?>" disabled>
        </div>
        <div class="form-group">
            <label>Subject</label>
            <input type="text" name="subject" value="<?php echo htmlspecialchars($request['subject']); ?>">
        </div>
        <div class="form-group">
            <label>Body / Message</label>
            <textarea name="body" rows="6"><?php echo htmlspecialchars($request['body']); ?></textarea>
        </div>
        <div class="form-group">
            <label>Attachment</label>
            <?php if (!empty($request['attachment_path'])): ?>
                <div class="current-attachment">
                    <span>Current file: <?php echo htmlspecialchars(substr($request['attachment_path'], 14)); ?></span>
                    <label style="display: inline-block; margin-left: 15px;">
                        <input type="checkbox" name="remove_attachment" value="1"> Remove
                    </label>
                </div>
            <?php endif; ?>
            <input type="file" name="attachment" style="margin-top: 10px;">
            <small>To replace the current file, just upload a new one.</small>
        </div>

        <!-- NEW UI for Approval Chain Builder -->
        <div class="workflow-builder">
            <h4>Rebuild Approval Chain</h4>
            <div class="approver-selection-area">
                <div class="approver-pool">
                    <h5>Available Approvers</h5>
                    <div class="approver-filters">
                        <input type="text" id="approver-search" placeholder="Search by name..." onkeyup="filterUsers()">
                        <div class="filter-buttons">
                            <button type="button" class="filter-btn active" onclick="setFilter('all', this)">All</button>
                            <?php foreach ($titles as $title): ?>
                                <button type="button" class="filter-btn" onclick="setFilter('<?php echo htmlspecialchars($title); ?>', this)"><?php echo htmlspecialchars($title); ?></button>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <ul id="available-users-list" class="approver-pool-list">
                         <?php foreach ($all_users as $user): ?>
                            <li data-id="<?php echo $user['id']; ?>" data-title="<?php echo htmlspecialchars($user['title']); ?>" onclick="addApprover(this)">
                                <?php echo htmlspecialchars($user['full_name']); ?> 
                                <span class="user-title">(<?php echo htmlspecialchars($user['title']); ?>)</span>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </div>
                <div class="selected-approvers">
                    <h5>Selected Chain (In Order)</h5>
                    <ul id="approver-list" class="approver-list">
                        <!-- Pre-populated by JavaScript -->
                    </ul>
                </div>
            </div>
        </div>
        
        <div id="approver-inputs-container" style="display: none;"></div>

        <div class="form-group" style="margin-top: 20px;">
            <input type="submit" class="btn" value="Update & Resubmit">
        </div>
    </form>
</div>

<script>
    let currentFilter = 'all';

    function setFilter(title, btnElement) {
        currentFilter = title;
        document.querySelectorAll('.filter-btn').forEach(btn => btn.classList.remove('active'));
        btnElement.classList.add('active');
        filterUsers();
    }

    function filterUsers() {
        const filterText = document.getElementById('approver-search').value.toUpperCase();
        const li = document.getElementById('available-users-list').getElementsByTagName('li');

        for (let i = 0; i < li.length; i++) {
            const title = li[i].dataset.title;
            const name = li[i].textContent || li[i].innerText;
            const titleMatch = (currentFilter === 'all' || title === currentFilter);
            const nameMatch = (name.toUpperCase().indexOf(filterText) > -1);
            li[i].style.display = (titleMatch && nameMatch) ? "" : "none";
        }
    }

    function addApprover(userElement) {
        const list = document.getElementById('approver-list');
        const hiddenContainer = document.getElementById('approver-inputs-container');
        const approverId = userElement.dataset.id;
        const approverName = userElement.childNodes[0].nodeValue.trim();

        if (document.querySelector(`input[name="approvers[]"][value="${approverId}"]`)) {
            alert('This user is already in the approval chain.');
            return;
        }

        const listItem = document.createElement('li');
        listItem.dataset.id = approverId;
        listItem.innerHTML = `<span>${list.children.length + 1}. ${approverName}</span><button type="button" onclick="removeApprover(this)">Remove</button>`;
        list.appendChild(listItem);

        const hiddenInput = document.createElement('input');
        hiddenInput.type = 'hidden';
        hiddenInput.name = 'approvers[]';
        hiddenInput.value = approverId;
        hiddenContainer.appendChild(hiddenInput);
    }

    function removeApprover(button) {
        const listItem = button.parentElement;
        const approverId = listItem.dataset.id;
        const hiddenContainer = document.getElementById('approver-inputs-container');
        
        listItem.remove();
        
        const inputToRemove = hiddenContainer.querySelector(`input[value="${approverId}"]`);
        if (inputToRemove) inputToRemove.remove();
        
        const list = document.getElementById('approver-list');
        Array.from(list.children).forEach((item, index) => {
            item.querySelector('span').textContent = `${index + 1}. ${item.querySelector('span').textContent.split('. ')[1]}`;
        });
    }

    // Pre-populate the list on page load
    document.addEventListener('DOMContentLoaded', function() {
        const currentApprovers = <?php echo json_encode($current_approvers); ?>;
        
        currentApprovers.forEach(function(approver) {
            const tempElement = document.createElement('div');
            tempElement.dataset.id = approver.id;
            tempElement.textContent = approver.full_name;
            addApprover(tempElement);
        });
    });
</script>

<?php include_once('includes/footer.php'); ?>


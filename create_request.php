<?php
// Include header and database connection
include_once('includes/header.php');
include_once('includes/db.php');

// Fetch all users to populate the single, filterable list
$sql_users = "SELECT id, full_name, title FROM users WHERE id != ? ORDER BY full_name";
$stmt_users = mysqli_prepare($conn, $sql_users);
mysqli_stmt_bind_param($stmt_users, "i", $_SESSION['id']);
mysqli_stmt_execute($stmt_users);
$result_users = mysqli_stmt_get_result($stmt_users);
$all_users = mysqli_fetch_all($result_users, MYSQLI_ASSOC);
mysqli_stmt_close($stmt_users);

// Get a unique list of titles for the filter buttons
$titles = array_unique(array_column($all_users, 'title'));

// --- The rest of the PHP for form processing remains the same ---
$subject = $body = "";
$error = "";
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $subject = trim($_POST['subject']);
    $body = trim($_POST['body']);
    $approvers = isset($_POST['approvers']) ? $_POST['approvers'] : [];

    if (empty($subject) || empty($body) || empty($approvers)) {
        $error = "Subject, Body, and at least one Approver are required.";
    }

    // Handle multiple file uploads
    $attachment_paths = [];
    if (isset($_FILES["attachment"]) && is_array($_FILES["attachment"]["name"])) {
        $target_dir = "uploads/";
        
        // Create uploads directory if it doesn't exist
        if (!is_dir($target_dir)) {
            mkdir($target_dir, 0777, true);
        }
        
        $file_count = count($_FILES["attachment"]["name"]);
        
        for ($i = 0; $i < $file_count; $i++) {
            if ($_FILES["attachment"]["error"][$i] == UPLOAD_ERR_OK) {
                $file_name = $_FILES["attachment"]["name"][$i];
                $file_tmp = $_FILES["attachment"]["tmp_name"][$i];
                $file_size = $_FILES["attachment"]["size"][$i];
                
                // Check file size (10MB limit)
                if ($file_size > 10 * 1024 * 1024) {
                    $error = "File '{$file_name}' is too large. Maximum size is 10MB.";
                    break;
                }
                
                $unique_prefix = uniqid('', true) . '_';
                $target_file = $target_dir . $unique_prefix . basename($file_name);
                
                if (move_uploaded_file($file_tmp, $target_file)) {
                    $attachment_paths[] = $unique_prefix . basename($file_name);
                } else {
                    $error = "Sorry, there was an error uploading file '{$file_name}'.";
                    break;
                }
            }
        }
    }
    
    // Convert array to comma-separated string for database storage
    $attachment_path = !empty($attachment_paths) ? implode(',', $attachment_paths) : NULL;

    if (empty($error)) {
        mysqli_begin_transaction($conn);
        try {
            // Database insertion logic (unchanged)
            $sql_request = "INSERT INTO requests (requester_id, subject, body, attachment_path) VALUES (?, ?, ?, ?)";
            $stmt_request = mysqli_prepare($conn, $sql_request);
            mysqli_stmt_bind_param($stmt_request, "isss", $_SESSION['id'], $subject, $body, $attachment_path);
            mysqli_stmt_execute($stmt_request);
            $request_id = mysqli_insert_id($conn);
            mysqli_stmt_close($stmt_request);
            $tracking_code = 'REQ-' . $request_id . '-' . strtoupper(substr(uniqid(), -4));
            $sql_update_code = "UPDATE requests SET tracking_code = ? WHERE id = ?";
            $stmt_update_code = mysqli_prepare($conn, $sql_update_code);
            mysqli_stmt_bind_param($stmt_update_code, "si", $tracking_code, $request_id);
            mysqli_stmt_execute($stmt_update_code);
            mysqli_stmt_close($stmt_update_code);
            $sql_workflow = "INSERT INTO approval_workflow (request_id, approver_id, step) VALUES (?, ?, ?)";
            $stmt_workflow = mysqli_prepare($conn, $sql_workflow);
            $step = 1;
            foreach ($approvers as $approver_id) {
                mysqli_stmt_bind_param($stmt_workflow, "iii", $request_id, $approver_id, $step);
                mysqli_stmt_execute($stmt_workflow);
                $step++;
            }
            mysqli_stmt_close($stmt_workflow);
            $log_action = "Request created and submitted for approval.";
            $sql_log = "INSERT INTO request_logs (request_id, user_id, action) VALUES (?, ?, ?)";
            $stmt_log = mysqli_prepare($conn, $sql_log);
            mysqli_stmt_bind_param($stmt_log, "iis", $request_id, $_SESSION['id'], $log_action);
            mysqli_stmt_execute($stmt_log);
            mysqli_stmt_close($stmt_log);
            mysqli_commit($conn);
            $_SESSION['success_message'] = "Request submitted successfully! Your tracking code is: <strong>" . $tracking_code . "</strong>";
            header("location: dashboard.php");
            exit();
        } catch (mysqli_sql_exception $exception) {
            mysqli_rollback($conn);
            $error = "An error occurred. Please try again.";
        }
    }
}
?>

<div class="card">
    <h3>Create a New Letter</h3>
    <?php if(!empty($error)) echo '<div class="alert alert-danger">' . $error . '</div>'; ?>

    <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="post" enctype="multipart/form-data">
        <div class="form-group">
            <label>Subject</label>
            <input type="text" name="subject" value="<?php echo htmlspecialchars($subject); ?>">
        </div>
        <div class="form-group">
            <label>Body / Message</label>
            <textarea name="body" rows="6"><?php echo htmlspecialchars($body); ?></textarea>
        </div>
        <div class="form-group">
            <label>Attach Files (Multiple allowed)</label>
            <input type="file" name="attachment[]" multiple id="file-input" accept=".pdf,.doc,.docx,.jpg,.jpeg,.png,.txt,.xls,.xlsx">
            
            <!-- File display area -->
            <div id="file-preview" style="margin-top: 15px; display: none;">
                <div style="background: #f8f9fa; padding: 15px; border-radius: 8px; border: 1px solid #dee2e6;">
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 10px;">
                        <strong style="color: #495057;">📎 Selected Files:</strong>
                        <button type="button" onclick="clearAllFiles()" style="background: #dc3545; color: white; border: none; padding: 4px 8px; border-radius: 4px; font-size: 12px; cursor: pointer;">Clear All</button>
                    </div>
                    <div id="file-list"></div>
                </div>
            </div>
            
            <small style="color: #6c757d; margin-top: 8px; display: block;">
                • Supported: PDF, Word, Excel, Images, Text files<br>
                • Maximum size: 10MB per file
            </small>
        </div>

        <!-- NEW UI for Approval Chain Builder -->
        <div class="workflow-builder">
            <h4>Build Letter Forwarding Chain</h4>
            <p>Click on a user from the left list to add them to the approval chain on the right.</p>
            <div class="approver-selection-area">
                <!-- Left side: ONE consolidated, filterable pool -->
                <div class="approver-pool">
                    <h5>Available Destinations </h5>
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

                <!-- Right side: The final selected chain -->
                <div class="selected-approvers">
                    <h5>Selected Chain (In Order)</h5>
                    <ul id="approver-list" class="approver-list"></ul>
                </div>
            </div>
        </div>
        
        <div id="approver-inputs-container" style="display: none;"></div>
        <div class="form-group" style="margin-top: 20px;">
            <input type="submit" class="btn" value="Submit Letter">
        </div>
    </form>
</div>

<script>
    let currentFilter = 'all';

    // File handling
    document.addEventListener('DOMContentLoaded', function() {
        const fileInput = document.getElementById('file-input');
        const filePreview = document.getElementById('file-preview');
        const fileList = document.getElementById('file-list');
        
        let selectedFilesArray = []; // Store accumulated files

        if (fileInput) {
            fileInput.addEventListener('change', function() {
                addNewFiles(this.files);
            });
        }

        function addNewFiles(newFiles) {
            // Add new files to existing array
            for (let i = 0; i < newFiles.length; i++) {
                const file = newFiles[i];
                
                // Check if file already exists (by name and size)
                const isDuplicate = selectedFilesArray.some(existingFile => 
                    existingFile.name === file.name && existingFile.size === file.size
                );
                
                if (!isDuplicate) {
                    selectedFilesArray.push(file);
                } else {
                    alert(`File "${file.name}" is already selected.`);
                }
            }
            
            updateFileDisplay();
            updateFileInput();
        }

        function updateFileDisplay() {
            if (selectedFilesArray.length > 0) {
                filePreview.style.display = 'block';
                fileList.innerHTML = '';

                selectedFilesArray.forEach((file, index) => {
                    const fileItem = createFileItem(file, index);
                    fileList.appendChild(fileItem);
                });
            } else {
                filePreview.style.display = 'none';
            }
        }

        function updateFileInput() {
            // Update the actual file input with accumulated files
            const dt = new DataTransfer();
            selectedFilesArray.forEach(file => dt.items.add(file));
            fileInput.files = dt.files;
        }

        function createFileItem(file, index) {
            const fileItem = document.createElement('div');
            fileItem.style.cssText = `
                display: flex; 
                align-items: center; 
                justify-content: space-between; 
                padding: 8px 12px; 
                margin: 4px 0; 
                background: white; 
                border: 1px solid #e9ecef; 
                border-radius: 6px;
                transition: background 0.2s ease;
            `;
            
            fileItem.innerHTML = `
                <div style="display: flex; align-items: center; gap: 10px;">
                    <span style="font-size: 20px;">${getFileIcon(file.name)}</span>
                    <div>
                        <div style="font-weight: 600; color: #2c3e50; font-size: 14px;">${file.name}</div>
                        <div style="color: #6c757d; font-size: 12px;">${formatFileSize(file.size)}</div>
                    </div>
                </div>
                <button type="button" onclick="removeFile(${index})" style="
                    width: 24px; 
                    height: 24px; 
                    border-radius: 50%; 
                    background: #dc3545; 
                    color: white; 
                    border: none; 
                    cursor: pointer; 
                    font-size: 14px;
                    display: flex;
                    align-items: center;
                    justify-content: center;
                ">×</button>
            `;
            
            // Add hover effect
            fileItem.addEventListener('mouseenter', function() {
                this.style.background = '#f1f3f4';
            });
            fileItem.addEventListener('mouseleave', function() {
                this.style.background = 'white';
            });
            
            return fileItem;
        }

        function getFileIcon(filename) {
            const extension = filename.split('.').pop().toLowerCase();
            switch (extension) {
                case 'pdf': return '📄';
                case 'doc':
                case 'docx': return '📝';
                case 'xls':
                case 'xlsx': return '📊';
                case 'jpg':
                case 'jpeg':
                case 'png':
                case 'gif': return '🖼️';
                case 'txt': return '📃';
                default: return '📎';
            }
        }

        function formatFileSize(bytes) {
            if (bytes === 0) return '0 Bytes';
            const k = 1024;
            const sizes = ['Bytes', 'KB', 'MB', 'GB'];
            const i = Math.floor(Math.log(bytes) / Math.log(k));
            return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
        }

        // Make functions global for onclick handlers
        window.removeFile = function(index) {
            selectedFilesArray.splice(index, 1);
            updateFileDisplay();
            updateFileInput();
        };

        window.clearAllFiles = function() {
            selectedFilesArray = [];
            fileInput.value = '';
            filePreview.style.display = 'none';
        };
    });

    function setFilter(title, btnElement) {
        currentFilter = title;
        const buttons = document.querySelectorAll('.filter-btn');
        buttons.forEach(btn => btn.classList.remove('active'));
        btnElement.classList.add('active');
        filterUsers();
    }

    function filterUsers() {
        const searchInput = document.getElementById('approver-search');
        const filterText = searchInput.value.toUpperCase();
        const ul = document.getElementById('available-users-list');
        const li = ul.getElementsByTagName('li');

        for (let i = 0; i < li.length; i++) {
            const title = li[i].dataset.title;
            const name = li[i].textContent || li[i].innerText;
            
            const titleMatch = (currentFilter === 'all' || title === currentFilter);
            const nameMatch = (name.toUpperCase().indexOf(filterText) > -1);

            if (titleMatch && nameMatch) {
                li[i].style.display = "";
            } else {
                li[i].style.display = "none";
            }
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
</script>

<?php include_once('includes/footer.php'); ?>


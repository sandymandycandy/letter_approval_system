<?php
// Include the header
include_once('includes/header.php');
include_once('includes/db.php');

$current_user_id = $_SESSION['id'];
$full_name = $_SESSION['full_name']; // Get current name from session
$error = $success_message = "";

// --- Process Form Submission ---
if ($_SERVER["REQUEST_METHOD"] == "POST") {

    // --- Update Full Name ---
    $new_full_name = trim($_POST['full_name']);
    if (empty($new_full_name)) {
        $error = "Full name cannot be empty.";
    } elseif ($new_full_name !== $full_name) {
        $sql_update_name = "UPDATE users SET full_name = ? WHERE id = ?";
        $stmt_name = mysqli_prepare($conn, $sql_update_name);
        mysqli_stmt_bind_param($stmt_name, "si", $new_full_name, $current_user_id);
        if (mysqli_stmt_execute($stmt_name)) {
            $_SESSION['full_name'] = $new_full_name; // Update session variable
            $full_name = $new_full_name; // Update for current page view
            $success_message = "Your name has been updated successfully.";
        } else {
            $error = "Failed to update name.";
        }
        mysqli_stmt_close($stmt_name);
    }

    // --- Update Password ---
    $new_password = $_POST['new_password'];
    $confirm_password = $_POST['confirm_password'];

    if (!empty($new_password)) {
        if (strlen($new_password) < 6) {
            $error = "Password must be at least 6 characters long.";
        } elseif ($new_password !== $confirm_password) {
            $error = "Passwords do not match.";
        } else {
            // Hash the new password
            $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
            $sql_update_pass = "UPDATE users SET password = ? WHERE id = ?";
            $stmt_pass = mysqli_prepare($conn, $sql_update_pass);
            mysqli_stmt_bind_param($stmt_pass, "si", $hashed_password, $current_user_id);
            if (mysqli_stmt_execute($stmt_pass)) {
                // Combine success messages
                $success_message .= " Your password has been updated successfully.";
            } else {
                $error = "Failed to update password.";
            }
            mysqli_stmt_close($stmt_pass);
        }
    }
}
?>

<div class="card">
    <h3>My Profile</h3>
    <p>Update your personal information below.</p>

    <?php 
    if(!empty($error)) echo '<div class="alert alert-danger">' . $error . '</div>';
    if(!empty($success_message)) echo '<div class="alert alert-success">' . trim($success_message) . '</div>';
    ?>

    <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="post">
        <div class="form-group">
            <label>Full Name</label>
            <input type="text" name="full_name" value="<?php echo htmlspecialchars($full_name); ?>" required>
        </div>
        <div class="form-group">
            <label>Username</label>
            <input type="text" name="username" value="<?php echo htmlspecialchars($_SESSION['username']); ?>" disabled>
            <small>Your username cannot be changed.</small>
        </div>
        <hr>
        <h4>Change Password</h4>
        <p>Leave the fields below blank if you do not wish to change your password.</p>
        <div class="form-group">
            <label>New Password</label>
            <input type="password" name="new_password">
        </div>
        <div class="form-group">
            <label>Confirm New Password</label>
            <input type="password" name="confirm_password">
        </div>

        <div class="form-group">
            <input type="submit" class="btn" value="Update Profile">
        </div>
    </form>
</div>

<?php include_once('includes/footer.php'); ?>

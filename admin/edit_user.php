<?php
// Include the secure admin header
include_once('../includes/admin_header.php');

// Initialize variables
$full_name = $username = $title = "";
$user_id = 0;
$error = $success_message = "";

// Check if a user ID is provided in the URL
if(isset($_GET["id"]) && !empty(trim($_GET["id"]))){
    $user_id = trim($_GET["id"]);

    // Fetch user data from the database
    $sql = "SELECT full_name, username, title FROM users WHERE id = ?";
    if($stmt = mysqli_prepare($conn, $sql)){
        mysqli_stmt_bind_param($stmt, "i", $user_id);
        if(mysqli_stmt_execute($stmt)){
            $result = mysqli_stmt_get_result($stmt);
            if(mysqli_num_rows($result) == 1){
                $user = mysqli_fetch_assoc($result);
                $full_name = $user['full_name'];
                $username = $user['username'];
                $title = $user['title'];
            } else {
                // Redirect if user not found
                header("location: manage_users.php");
                exit();
            }
        }
        mysqli_stmt_close($stmt);
    }
} else {
    // Redirect if no ID is provided
    header("location: manage_users.php");
    exit();
}

// Process the form data when the form is submitted
if($_SERVER["REQUEST_METHOD"] == "POST"){
    // Validate form inputs
    $full_name = trim($_POST["full_name"]);
    $username = trim($_POST["username"]);
    $title = trim($_POST["title"]);
    $password = trim($_POST["password"]);

    if(empty($full_name) || empty($username) || empty($title)){
        $error = "Please fill in all required fields.";
    }

    // If there are no errors, update the user in the database
    if(empty($error)){
        // Check if a new password was entered
        if(!empty($password)){
            // Update with new password
            $sql = "UPDATE users SET full_name = ?, username = ?, title = ?, password = ? WHERE id = ?";
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
        } else {
            // Update without changing the password
            $sql = "UPDATE users SET full_name = ?, username = ?, title = ? WHERE id = ?";
        }
        
        if($stmt = mysqli_prepare($conn, $sql)){
            if(!empty($password)){
                mysqli_stmt_bind_param($stmt, "ssssi", $full_name, $username, $title, $hashed_password, $user_id);
            } else {
                mysqli_stmt_bind_param($stmt, "sssi", $full_name, $username, $title, $user_id);
            }

            if(mysqli_stmt_execute($stmt)){
                $_SESSION['success_message'] = "User details updated successfully.";
                header("location: manage_users.php");
                exit();
            } else {
                $error = "Something went wrong. Please try again later.";
            }
            mysqli_stmt_close($stmt);
        }
    }
}
?>

<div class="card">
    <h3>Edit User Details</h3>
    <p>Use this form to update the user's account information.</p>

    <?php 
    if(!empty($error)){
        echo '<div class="alert alert-danger">' . $error . '</div>';
    }
    ?>

    <form action="<?php echo htmlspecialchars($_SERVER["REQUEST_URI"]); ?>" method="post">
        <div class="form-group">
            <label>Full Name</label>
            <input type="text" name="full_name" value="<?php echo htmlspecialchars($full_name); ?>">
        </div>    
        <div class="form-group">
            <label>Username</label>
            <input type="text" name="username" value="<?php echo htmlspecialchars($username); ?>">
        </div>
        <div class="form-group">
            <label>Title</label>
            <select name="title">
                <option value="Admin" <?php echo ($title == 'Admin') ? 'selected' : ''; ?>>Admin</option>
                <option value="Faculty" <?php echo ($title == 'Faculty') ? 'selected' : ''; ?>>Faculty</option>
                <option value="Dean" <?php echo ($title == 'Dean') ? 'selected' : ''; ?>>Dean</option>
                <option value="Vice-Chancellor (VC)" <?php echo ($title == 'Vice-Chancellor (VC)') ? 'selected' : ''; ?>>Vice-Chancellor (VC)</option>
            </select>
        </div>
        <hr>
        <div class="form-group">
            <label>New Password</label>
            <input type="password" name="password" placeholder="Leave blank to keep current password">
        </div>
        <div class="form-group">
            <input type="submit" class="btn" value="Update User">
            <a href="manage_users.php" class="btn" style="background-color: #6c757d; margin-top: 10px;">Cancel</a>
        </div>
    </form>
</div>

<?php 
// Include the standard footer
include_once('../includes/footer.php'); 
?>

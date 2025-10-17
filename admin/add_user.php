<?php
// Include the secure admin header, which handles all security checks
include_once('../includes/admin_header.php');

// Initialize variables
$full_name = $username = $password = $title = "";
$error = $success_message = "";

// Process form data when form is submitted
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Validate form inputs
    $full_name = trim($_POST["full_name"]);
    $username = trim($_POST["username"]);
    $password = trim($_POST["password"]);
    $title = trim($_POST["title"]);

    if (empty($full_name) || empty($username) || empty($password) || empty($title)) {
        $error = "All fields are required.";
    } elseif (strlen($password) < 6) {
        $error = "Password must have at least 6 characters.";
    } else {
        // Check if username already exists
        $sql = "SELECT id FROM users WHERE username = ?";
        if ($stmt = mysqli_prepare($conn, $sql)) {
            mysqli_stmt_bind_param($stmt, "s", $param_username);
            $param_username = $username;
            if (mysqli_stmt_execute($stmt)) {
                mysqli_stmt_store_result($stmt);
                if (mysqli_stmt_num_rows($stmt) == 1) {
                    $error = "This username is already taken.";
                }
            } else {
                $error = "Oops! Something went wrong.";
            }
            mysqli_stmt_close($stmt);
        }
    }

    // If there are no errors, insert the new user
    if (empty($error)) {
        $sql = "INSERT INTO users (full_name, username, password, title) VALUES (?, ?, ?, ?)";
        if ($stmt = mysqli_prepare($conn, $sql)) {
            mysqli_stmt_bind_param($stmt, "ssss", $param_full_name, $param_username, $param_password, $param_title);
            
            $param_full_name = $full_name;
            $param_username = $username;
            $param_password = password_hash($password, PASSWORD_DEFAULT); 
            $param_title = $title;
            
            if (mysqli_stmt_execute($stmt)) {
                // Redirect to user list with a success message
                $_SESSION['message'] = "User '" . htmlspecialchars($full_name) . "' created successfully.";
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
    <h3>Add New User</h3>
    <p>Fill out the form below to create a new user account.</p>

    <?php 
    if(!empty($error)){
        echo '<div class="alert alert-danger">' . $error . '</div>';
    }
    ?>

    <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="post">
        <div class="form-group">
            <label>Full Name</label>
            <input type="text" name="full_name" value="<?php echo htmlspecialchars($full_name); ?>">
        </div>
        <div class="form-group">
            <label>Username</label>
            <input type="text" name="username" value="<?php echo htmlspecialchars($username); ?>">
        </div>
        <div class="form-group">
            <label>Password</label>
            <input type="password" name="password">
        </div>
        <div class="form-group">
            <label>Title</label>
            <select name="title">
                <option value="">-- Select a Title --</option>
                <option value="Admin" <?php if($title == 'Admin') echo 'selected'; ?>>Admin</option>
                <option value="Faculty" <?php if($title == 'Faculty') echo 'selected'; ?>>Faculty</option>
                <option value="Dean" <?php if($title == 'Dean') echo 'selected'; ?>>Dean</option>
                <option value="Vice-Chancellor (VC)" <?php if($title == 'Vice-Chancellor (VC)') echo 'selected'; ?>>Vice-Chancellor (VC)</option>
            </select>
        </div>
        <div class="form-group">
            <input type="submit" class="btn" value="Create User">
            <a href="manage_users.php" class="btn" style="background-color: #6c757d;">Cancel</a>
        </div>
    </form>
</div>

<?php include_once('../includes/footer.php'); ?>

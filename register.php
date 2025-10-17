<?php
// --- 1. Include the database connection file ---
require_once "includes/db.php";

// --- 2. Initialize variables to store form data and errors ---
$full_name = $username = $password = $title = "";
$error = "";
$success_message = "";

// --- 3. Process the form data when the form is submitted ---
if ($_SERVER["REQUEST_METHOD"] == "POST") {

    // --- 4. Validate form inputs ---
    if (empty(trim($_POST["full_name"]))) {
        $error = "Please enter your full name.";
    } else {
        $full_name = trim($_POST["full_name"]);
    }

    if (empty(trim($_POST["username"]))) {
        $error = "Please enter a username.";
    } else {
        // Check if username already exists
        $sql = "SELECT id FROM users WHERE username = ?";
        if ($stmt = mysqli_prepare($conn, $sql)) {
            mysqli_stmt_bind_param($stmt, "s", $param_username);
            $param_username = trim($_POST["username"]);
            if (mysqli_stmt_execute($stmt)) {
                mysqli_stmt_store_result($stmt);
                if (mysqli_stmt_num_rows($stmt) == 1) {
                    $error = "This username is already taken.";
                } else {
                    $username = trim($_POST["username"]);
                }
            } else {
                $error = "Oops! Something went wrong. Please try again later.";
            }
            mysqli_stmt_close($stmt);
        }
    }
    
    if (empty(trim($_POST["password"]))) {
        $error = "Please enter a password.";
    } elseif (strlen(trim($_POST["password"])) < 6) {
        $error = "Password must have at least 6 characters.";
    } else {
        $password = trim($_POST["password"]);
    }
    
    if (empty(trim($_POST["title"]))) {
        $error = "Please select a title.";
    } else {
        $title = trim($_POST["title"]);
    }

    // --- 5. If there are no errors, insert the new user into the database ---
    if (empty($error)) {
        $sql = "INSERT INTO users (full_name, username, password, title) VALUES (?, ?, ?, ?)";
        
        if ($stmt = mysqli_prepare($conn, $sql)) {
            // Bind variables to the prepared statement as parameters
            mysqli_stmt_bind_param($stmt, "ssss", $param_full_name, $param_username, $param_password, $param_title);
            
            // Set parameters
            $param_full_name = $full_name;
            $param_username = $username;
            // Hash the password for security
            $param_password = password_hash($password, PASSWORD_DEFAULT); 
            $param_title = $title;
            
            // Attempt to execute the prepared statement
            if (mysqli_stmt_execute($stmt)) {
                $success_message = "Registration successful! You can now log in.";
                 // Clear form fields after successful registration
                $_POST = array();
                $full_name = $username = $password = $title = "";
            } else {
                $error = "Something went wrong. Please try again later.";
            }
            mysqli_stmt_close($stmt);
        }
    }
    
    // Close the database connection in case of an error
    if(mysqli_ping($conn)){
      mysqli_close($conn);
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>User Registration</title>
    <link rel="stylesheet" href="css/style.css"> 
</head>
<body class="form-page">
    <div class="form-container">
        <h2>Create Account</h2>
        <p>Please fill this form to create an account.</p>

        <?php 
        if(!empty($error)){
            echo '<div class="alert alert-danger">' . $error . '</div>';
        }
        if(!empty($success_message)){
            echo '<div class="alert alert-success">' . $success_message . '</div>';
        }
        ?>

        <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="post">
            <div class="form-group">
                <label>Full Name</label>
                <input type="text" name="full_name" value="<?php echo $full_name; ?>">
            </div>    
            <div class="form-group">
                <label>Username</label>
                <input type="text" name="username" value="<?php echo $username; ?>">
            </div>    
            <div class="form-group">
                <label>Password</label>
                <input type="password" name="password">
            </div>
            <div class="form-group">
                <label>Title</label>
                <select name="title">
                    <option value="">-- Select a Title --</option>
                    <option value="Admin" <?php if ($title == "Admin") echo "selected"; ?>>Admin</option>
                    <option value="Faculty" <?php if ($title == "Faculty") echo "selected"; ?>>Faculty</option>
                    <option value="Dean" <?php if ($title == "Dean") echo "selected"; ?>>Dean</option>
                    <option value="Vice-Chancellor (VC)" <?php if ($title == "Vice-Chancellor (VC)") echo "selected"; ?>>Vice-Chancellor (VC)</option>
                </select>
            </div>
            <div class="form-group">
                <input type="submit" class="btn" value="Register">
            </div>
            <p>Already have an account? <a href="login.php">Login here</a>.</p>
        </form>
    </div>    
</body>
</html>


<?php
// Include the database connection and start session
session_start();
require_once "includes/db.php";

// Redirect user to dashboard if they are already logged in
if(isset($_SESSION["loggedin"]) && $_SESSION["loggedin"] === true){
    header("location: dashboard.php");
    exit;
}

// Initialize variables
$username = $password = "";
$error = "";

// Process form data when form is submitted
if($_SERVER["REQUEST_METHOD"] == "POST"){

    // Validate username
    if(empty(trim($_POST["username"]))){
        $error = "Please enter username.";
    } else{
        $username = trim($_POST["username"]);
    }
    
    // Validate password
    if(empty(trim($_POST["password"]))){
        $error = "Please enter your password.";
    } else{
        $password = trim($_POST["password"]);
    }
    
    // If no validation errors, check credentials
    if(empty($error)){
        $sql = "SELECT id, username, password, full_name, title FROM users WHERE username = ?";
        
        if($stmt = mysqli_prepare($conn, $sql)){
            mysqli_stmt_bind_param($stmt, "s", $param_username);
            $param_username = $username;
            
            if(mysqli_stmt_execute($stmt)){
                mysqli_stmt_store_result($stmt);
                
                if(mysqli_stmt_num_rows($stmt) == 1){                    
                    mysqli_stmt_bind_result($stmt, $id, $username, $hashed_password, $full_name, $title);
                    if(mysqli_stmt_fetch($stmt)){
                        if(password_verify($password, $hashed_password)){
                            // Password is correct, so start a new session
                            session_start();
                            
                            // Store data in session variables
                            $_SESSION["loggedin"] = true;
                            $_SESSION["id"] = $id;
                            $_SESSION["username"] = $username;
                            $_SESSION["full_name"] = $full_name;
                            $_SESSION["title"] = $title;                            
                            
                            // Redirect user to dashboard
                            header("location: dashboard.php");
                        } else{
                            $error = "The password you entered was not valid.";
                        }
                    }
                } else{
                    $error = "No account found with that username.";
                }
            } else{
                $error = "Oops! Something went wrong. Please try again later.";
            }
            mysqli_stmt_close($stmt);
        }
    }
    mysqli_close($conn);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login</title>
    <link rel="stylesheet" href="css/style.css">
</head>
<body class="form-page">
    <div class="form-container">
        <h2>Login</h2>
        <p>Please fill in your credentials to login.</p>

        <?php 
        if(!empty($error)){
            echo '<div class="alert alert-danger">' . $error . '</div>';
        }        
        ?>

        <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="post">
            <div class="form-group">
                <label>Username</label>
                <input type="text" name="username" value="<?php echo $username; ?>">
            </div>    
            <div class="form-group">
                <label>Password</label>
                <input type="password" name="password">
            </div>
            <div class="form-group" style="display: flex; gap: 10px;">
                <input type="submit" class="btn" value="Login" style="flex: 1;">
                <a href="track_request.php" class="btn btn-secondary" style="flex: 1;">Track Letter</a>
            </div>
        </form>
    </div>    
</body>
</html>


<?php
// Include the secure admin header to ensure only admins can run this script
include_once('../includes/admin_header.php');

// Check if user ID is provided, is not empty, and is a numeric value
if(isset($_GET["id"]) && !empty(trim($_GET["id"])) && is_numeric($_GET['id'])){
    $user_id_to_delete = trim($_GET["id"]);

    // --- CRITICAL SAFETY CHECK 1 ---
    // Prevent an admin from deleting their own account
    if($user_id_to_delete == $_SESSION["id"]){
        $_SESSION['error'] = "Error: You cannot delete your own account.";
        header("location: manage_users.php");
        exit();
    }

    // --- CRITICAL SAFETY CHECK 2 ---
    // Prevent deleting a user who has created requests to maintain data integrity
    $sql_check = "SELECT COUNT(id) as request_count FROM requests WHERE requester_id = ?";
    $stmt_check = mysqli_prepare($conn, $sql_check);
    mysqli_stmt_bind_param($stmt_check, "i", $user_id_to_delete);
    mysqli_stmt_execute($stmt_check);
    $result_check = mysqli_stmt_get_result($stmt_check);
    $request_count = mysqli_fetch_assoc($result_check)['request_count'];
    mysqli_stmt_close($stmt_check);

    if ($request_count > 0) {
        // If the user has associated requests, block the deletion and set an error message
        $_SESSION['error'] = "Error: This user cannot be deleted because they have " . $request_count . " associated request(s).";
        header("location: manage_users.php");
        exit();
    }


    // --- If all safety checks pass, proceed with the deletion ---
    $sql_delete = "DELETE FROM users WHERE id = ?";
    if($stmt_delete = mysqli_prepare($conn, $sql_delete)){
        // Bind the user ID to the statement
        mysqli_stmt_bind_param($stmt_delete, "i", $user_id_to_delete);
        
        // Execute the statement and set a success or error message
        if(mysqli_stmt_execute($stmt_delete)){
            $_SESSION['message'] = "User deleted successfully.";
        } else {
            $_SESSION['error'] = "Oops! Something went wrong. Please try again later.";
        }
        mysqli_stmt_close($stmt_delete);
    }
    // After attempting deletion, redirect back
    header("location: manage_users.php");
    exit();

} else {
    // If the ID is invalid or not provided
    $_SESSION['error'] = "Invalid request.";
    header("location: manage_users.php");
    exit();
}
?>

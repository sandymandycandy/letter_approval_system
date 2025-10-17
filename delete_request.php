<?php
// Start the session and include the database connection
session_start();
include_once('includes/db.php');

// Security: Ensure user is logged in
if(!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true){
    header("location: login.php");
    exit;
}

// Check if a request ID is provided and is numeric
if(isset($_GET['id']) && is_numeric($_GET['id'])){
    $request_id = $_GET['id'];
    $current_user_id = $_SESSION['id'];

    // --- CRITICAL SAFETY CHECK ---
    // Fetch the request to verify the current user is the owner before deleting
    $sql_check = "SELECT requester_id, overall_status FROM requests WHERE id = ?";
    $stmt_check = mysqli_prepare($conn, $sql_check);
    mysqli_stmt_bind_param($stmt_check, "i", $request_id);
    mysqli_stmt_execute($stmt_check);
    $result = mysqli_stmt_get_result($stmt_check);
    $request = mysqli_fetch_assoc($result);
    mysqli_stmt_close($stmt_check);

    if ($request && $request['requester_id'] == $current_user_id) {
        // Additional check: prevent deletion of already approved requests
        if ($request['overall_status'] == 'approved') {
            $_SESSION['error_message'] = "Error: You cannot delete a request that has already been approved.";
        } else {
            // If all checks pass, proceed with deletion
            $sql_delete = "DELETE FROM requests WHERE id = ?";
            $stmt_delete = mysqli_prepare($conn, $sql_delete);
            mysqli_stmt_bind_param($stmt_delete, "i", $request_id);
            if (mysqli_stmt_execute($stmt_delete)) {
                $_SESSION['success_message'] = "Request has been successfully deleted.";
            } else {
                $_SESSION['error_message'] = "Failed to delete the request.";
            }
            mysqli_stmt_close($stmt_delete);
        }
    } else {
        // User is not the owner or request doesn't exist
        $_SESSION['error_message'] = "Error: You do not have permission to perform this action.";
    }
} else {
    $_SESSION['error_message'] = "Invalid request.";
}

// Redirect back to the dashboard to show the result
header("location: dashboard.php");
exit();
?>

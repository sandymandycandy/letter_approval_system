<?php
// Start the session
session_start();

// --- Security Check ---
// Check if user is logged in and if they are an admin.
if(!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true || $_SESSION["title"] !== 'Admin'){
    // If they are not an admin, send them to the main dashboard with an error message.
    $_SESSION['error_message'] = "Access Denied: You do not have permission to view this page.";
    header("location: ../dashboard.php");
    exit;
}

// Include the database connection
require_once "db.php";
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Panel - Approval System</title>
    <!-- We use the same stylesheet -->
    <link rel="stylesheet" href="../css/style.css">
</head>
<body>
    <header class="site-header admin-header">
        <div class="header-content">
            <div class="logo">
                <h1>Admin Panel</h1>
                <p>Logged in as: <b><?php echo htmlspecialchars($_SESSION["full_name"]); ?></b></p>
            </div>
            <nav>
                <a href="index.php">Dashboard</a>
                <a href="manage_users.php">Manage Users</a>
                <a href="manage_requests.php">All Requests</a>
                <a href="../dashboard.php">Exit Admin View</a>
                <a href="../logout.php" class="btn btn-danger">Logout</a>
            </nav>
        </div>
    </header>
    <div class="main-content">

    <script>
        function goBack() {
            // Check if there's a previous page in browser history
            if (window.history.length > 1) {
                window.history.back();
            } else {
                // If no history, go to dashboard
                window.location.href = '../dashboard.php';
            }
        }
    </script>


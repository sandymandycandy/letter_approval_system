<?php
// --- Start the session ---
session_start();

// --- Security Check ---
if(!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true){
    header("location: login.php");
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>College Letter System</title>
    <link rel="stylesheet" href="css/style.css">
</head>
<body>
    <header class="site-header">
        <div class="header-content">
            <!-- Back Button -->
            <div class="header-back-btn">
                <button type="button" onclick="goBack()" class="back-button" title="Go Back">
                    ← Back
                </button>
            </div>
            
            <div class="logo">
                <h1>Letter System</h1>
                <p>Welcome, <b><?php echo htmlspecialchars($_SESSION["full_name"]); ?></b> (<?php echo htmlspecialchars($_SESSION["title"]); ?>)</p>
            </div>
            <nav>
                <!-- NEW: Letter Tracking Form -->
                <form action="track_request.php" method="get" class="header-track-form" target="_blank">
                    <input type="text" name="tracking_code" placeholder="Track by code..." required>
                    <button type="submit">Track</button>
                </form>

                <a href="dashboard.php">Dashboard</a>
                <a href="profile.php">My Profile</a>
                
                <?php 
                // --- Conditional Admin Link ---
                if(isset($_SESSION['title']) && $_SESSION['title'] === 'Admin'): 
                ?>
                    <a href="admin/index.php" class="btn">Admin Panel</a>
                <?php endif; ?>

                <a href="logout.php" class="btn btn-danger">Logout</a>
            </nav>
        </div>
    </header>
    <div class="main-content">

    <!-- Display error messages passed via session -->
    <?php if(isset($_SESSION['error_message'])): ?>
        <div class="alert alert-danger"><?php echo $_SESSION['error_message']; ?></div>
        <?php unset($_SESSION['error_message']); // Clear message after displaying ?>
    <?php endif; ?>

    <script>
        function goBack() {
            // Check if there's a previous page in browser history
            if (window.history.length > 1) {
                window.history.back();
            } else {
                // If no history, go to dashboard
                window.location.href = 'dashboard.php';
            }
        }
    </script>


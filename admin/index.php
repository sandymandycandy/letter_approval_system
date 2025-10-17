<?php
// Include the secure admin header
include_once('../includes/admin_header.php');

// --- Fetch Dashboard Statistics ---
// (The PHP logic to fetch stats is unchanged)
$sql_users = "SELECT COUNT(id) as total_users FROM users";
$result_users = mysqli_query($conn, $sql_users);
$total_users = mysqli_fetch_assoc($result_users)['total_users'];

$sql_pending = "SELECT COUNT(id) as total_pending FROM requests WHERE overall_status = 'processing'";
$result_pending = mysqli_query($conn, $sql_pending);
$total_pending = mysqli_fetch_assoc($result_pending)['total_pending'];

$sql_approved = "SELECT COUNT(id) as total_approved FROM requests WHERE overall_status = 'approved'";
$result_approved = mysqli_query($conn, $sql_approved);
$total_approved = mysqli_fetch_assoc($result_approved)['total_approved'];

?>

<div class="card">
    <h3>Admin Dashboard</h3>
    <p>Here is a summary of the system's activity.</p>

    <!-- Statistics Cards are now nested inside the main card for better alignment -->
    <div class="stat-card-container" style="margin-top: 25px;">
        <div class="stat-card">
            <h4>Total Users</h4>
            <p class="stat-number"><?php echo $total_users; ?></p>
        </div>
        <div class="stat-card">
            <h4>Pending Letters</h4>
            <p class="stat-number"><?php echo $total_pending; ?></p>
        </div>
        <div class="stat-card">
            <h4>Approved Letters</h4>
            <p class="stat-number"><?php echo $total_approved; ?></p>
        </div>
    </div>
</div>

<?php 
// Include the standard footer
include_once('../includes/footer.php'); 
?>


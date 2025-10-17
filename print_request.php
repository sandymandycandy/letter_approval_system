<?php
// Start session to check for login
session_start();
include_once('includes/db.php');

// Security: Ensure user is logged in
if(!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true){
    header("location: login.php");
    exit;
}

// Check for request ID
if(!isset($_GET['id']) || empty($_GET['id'])){
    echo "Invalid Request.";
    exit;
}

$request_id = $_GET['id'];

// Fetch the approved request details
$sql = "SELECT r.subject, r.body, r.request_date, r.attachment_path, u.full_name as requester_name, u.title as requester_title
        FROM requests r
        JOIN users u ON r.requester_id = u.id
        WHERE r.id = ? AND r.overall_status = 'approved'";

$stmt = mysqli_prepare($conn, $sql);
mysqli_stmt_bind_param($stmt, "i", $request_id);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$request = mysqli_fetch_assoc($result);

if(!$request){
    echo "Approved request not found or you do not have permission to view it.";
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Print Request - <?php echo htmlspecialchars($request['subject']); ?></title>
    <style>
        body {
            font-family: 'Times New Roman', Times, serif;
            line-height: 1.6;
            margin: 40px;
        }
        .header, .footer {
            text-align: center;
        }
        .header h1 {
            margin: 0;
            font-size: 24px;
        }
        .header p {
            margin: 0;
            font-size: 16px;
        }
        .content {
            margin-top: 50px;
        }
        .date {
            text-align: right;
        }
        .signature {
            margin-top: 80px;
            text-align: right;
        }
        .print-button {
            position: fixed;
            top: 20px;
            right: 20px;
            padding: 10px 20px;
            background-color: #007bff;
            color: white;
            border: none;
            border-radius: 4px;
            cursor: pointer;
        }
        /* Hides the button when printing */
        @media print {
            .print-button {
                display: none;
            }
        }
    </style>
</head>
<body>

    <button onclick="window.print()" class="print-button">Print</button>

    <div class="header">
        <h1>College Official Letter</h1>
        <p>Office of the Administration</p>
    </div>

    <div class="content">
        <p class="date">Date: <?php echo date('F j, Y'); ?></p>
        
        <p><strong>To Whom It May Concern,</strong></p>

        <p><strong>Subject: <?php echo htmlspecialchars($request['subject']); ?></strong></p>

        <p>This letter is to certify the following request made by <strong><?php echo htmlspecialchars($request['requester_name']); ?> (<?php echo htmlspecialchars($request['requester_title']); ?>)</strong> on <?php echo date('F j, Y', strtotime($request['request_date'])); ?>.</p>

        <p>The details of the request are as follows:</p>
        
        <div style="border: 1px solid #ccc; padding: 15px; background-color: #f9f9f9;">
            <?php echo nl2br(htmlspecialchars($request['body'])); ?>
        </div>

        <p>This request has gone through the necessary approval process and has been officially approved by the relevant authorities.</p>

        <div class="signature">
            <p>_________________________</p>
            <p>Authorised Signatory</p>
        </div>
    </div>

    <div class="footer">
        <p>This is a system-generated document.</p>
    </div>

</body>
</html>

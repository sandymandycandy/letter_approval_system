<?php
/*
This file contains the database configuration and establishes the connection.
*/

// --- Database Credentials ---
// These are the default credentials for a standard XAMPP installation.
define('DB_SERVER', 'localhost');
define('DB_USERNAME', 'root');
define('DB_PASSWORD', '');
define('DB_NAME', 'college_letters');

// --- Attempt to connect to the MySQL database ---
$conn = mysqli_connect(DB_SERVER, DB_USERNAME, DB_PASSWORD, DB_NAME);

// --- Check the connection ---
if($conn === false){
    // If the connection fails, stop the script and display a detailed error message.
    // This is crucial for debugging during development.
    die("ERROR: Could not connect. " . mysqli_connect_error());
}
?>
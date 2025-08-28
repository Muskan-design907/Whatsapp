<?php
// db.php
// Database connection for WhatsApp clone
// DEV MODE: show errors (remove or set to 0 in production)
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
 
$DB_HOST = 'localhost';          // change if your DB host differs
$DB_USER = 'ur9iyguafpilu';
$DB_PASS = '51gssrtsv3ei';
$DB_NAME = 'db5jyqxlte0cmt';
 
$conn = new mysqli($DB_HOST, $DB_USER, $DB_PASS, $DB_NAME);
 
if ($conn->connect_errno) {
    // fail loudly for debugging
    http_response_code(500);
    echo "DB Connect Error: " . $conn->connect_error;
    exit;
}
 
$conn->set_charset('utf8mb4');
?>
 

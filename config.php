<?php
// Start the Discord bot if it's not running
//require_once __DIR__ . '/bot_manager.php';
//if (!is_bot_running()) {
//    start_bot();
//}

/* Database credentials. Assuming you are running MySQL
server with default setting (user 'root' with no password) */
define('DB_SERVER', '127.0.0.1');
define('DB_USERNAME', 'mxnticek');
define('DB_PASSWORD', '+(!SWCIR%p_@');
define('DB_NAME', 'transport_admin');
 
/* Attempt to connect to MySQL database */
$link = mysqli_connect(DB_SERVER, DB_USERNAME, DB_PASSWORD, DB_NAME);
 
// Check connection
if($link === false){
    die("ERROR: Could not connect. " . mysqli_connect_error());
}
?>
<?php
// account/logout.php - SECURE LOGOUT
require_once '../includes/config.php';

logout(); // Use the secure logout function
header('Location: login.php');
exit;
?>
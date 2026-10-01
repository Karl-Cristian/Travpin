<?php
session_start();

// Clear remember token from database if exists
if (isset($_SESSION["user_id"])) {
    include "db.php";
    $stmt = $conn->prepare("UPDATE users SET remember_token = NULL WHERE id = ?");
    $stmt->execute([$_SESSION["user_id"]]);
}

// Clear remember cookie
setcookie('remember_token', '', time() - 3600, "/");

session_destroy();
header("Location: Login.php");
exit();
?>
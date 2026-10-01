<?php
session_start();
include "db.php";

if (!isset($_SESSION["user_id"]) || !isset($_FILES['banner_image'])) {
    header("Location: Profile.php");
    exit();
}

$user_id = $_SESSION["user_id"];
$allowed_types = ['image/jpeg', 'image/png', 'image/gif'];
$file_type = $_FILES['banner_image']['type'];

if (in_array($file_type, $allowed_types)) {
    $upload_dir = 'uploads/banners/';
    if (!is_dir($upload_dir)) {
        mkdir($upload_dir, 0777, true);
    }
    
    $file_extension = pathinfo($_FILES['banner_image']['name'], PATHINFO_EXTENSION);
    $filename = 'banner_' . $user_id . '_' . time() . '.' . $file_extension;
    $file_path = $upload_dir . $filename;
    
    if (move_uploaded_file($_FILES['banner_image']['tmp_name'], $file_path)) {
        $sql = "UPDATE users SET banner_image = ? WHERE id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->execute([$file_path, $user_id]);
    }
}

header("Location: Profile.php");
exit();
?>
<?php
session_start();
include "db.php";

if (!isset($_SESSION["user_id"])) {
    echo json_encode([]);
    exit();
}

$user_id = $_SESSION["user_id"];

// Get unread notifications count
$stmt = $conn->prepare("SELECT COUNT(*) as unread_count FROM notifications WHERE user_id = ? AND is_read = FALSE");
$stmt->execute([$user_id]);
$unread_count = $stmt->fetch(PDO::FETCH_ASSOC)['unread_count'];

// Get recent notifications
$stmt = $conn->prepare("
    SELECT n.*, u.username as source_username 
    FROM notifications n 
    LEFT JOIN users u ON n.source_user_id = u.id 
    WHERE n.user_id = ? 
    ORDER BY n.created_at DESC 
    LIMIT 10
");
$stmt->execute([$user_id]);
$notifications = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo json_encode([
    'unread_count' => $unread_count,
    'notifications' => $notifications
]);
?>
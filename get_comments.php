<?php
session_start();
include "db.php";

if (!isset($_SESSION["user_id"])) {
    echo json_encode([]);
    exit();
}

$post_id = $_GET['post_id'];

$stmt = $conn->prepare("
    SELECT c.*, u.username 
    FROM comments c 
    JOIN users u ON c.user_id = u.id 
    WHERE c.post_id = ? 
    ORDER BY c.created_at ASC
");
$stmt->execute([$post_id]);
$comments = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo json_encode($comments);
?>
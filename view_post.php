<?php
session_start();
include "db.php";

if (!isset($_SESSION["user_id"])) {
    header("Location: Login.php");
    exit();
}

$post_id = $_GET['post_id'] ?? null;
$current_user_id = $_SESSION["user_id"];

if (!$post_id) {
    header("Location: Explore.php");
    exit();
}

// Get post data
$stmt = $conn->prepare("
    SELECT p.*, u.username, u.profile_pic,
           COUNT(DISTINCT l.id) as like_count,
           COUNT(DISTINCT c.id) as comment_count,
           EXISTS(SELECT 1 FROM likes WHERE user_id = ? AND post_id = p.id) as user_liked
    FROM posts p 
    JOIN users u ON p.user_id = u.id 
    LEFT JOIN likes l ON p.id = l.post_id 
    LEFT JOIN comments c ON p.id = c.post_id 
    WHERE p.id = ?
    GROUP BY p.id
");
$stmt->execute([$current_user_id, $post_id]);
$post = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$post) {
    header("Location: Explore.php");
    exit();
}

// Get current user info for header
$stmt = $conn->prepare("SELECT username, profile_pic FROM users WHERE id = ?");
$stmt->execute([$current_user_id]);
$current_user = $stmt->fetch(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>TravPin - Post</title>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  <style>
    /* Copy the same styles from Explore.php for consistency */
    * {
      margin: 0;
      padding: 0;
      box-sizing: border-box;
      font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
    }

    :root {
      --primary: #FF6B6B;
      --secondary: #4ECDC4;
      --accent: #45B7D1;
      --dark: #2C3E50;
      --light: #F7F9FC;
      --gray: #95A5A6;
    }

    body {
      background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
      color: var(--dark);
      min-height: 100vh;
    }

    .navbar {
      display: flex;
      justify-content: space-between;
      align-items: center;
      padding: 1rem 2rem;
      background: rgba(255, 255, 255, 0.95);
      backdrop-filter: blur(10px);
      box-shadow: 0 4px 20px rgba(0, 0, 0, 0.1);
      position: sticky;
      top: 0;
      z-index: 1000;
    }

    .logo {
      display: flex;
      align-items: center;
      gap: 0.5rem;
      font-weight: 700;
      font-size: 1.5rem;
      color: var(--primary);
    }

    .nav-links {
      display: flex;
      list-style: none;
      gap: 2rem;
    }

    .nav-links a {
      text-decoration: none;
      color: var(--dark);
      font-weight: 600;
      transition: color 0.3s;
      display: flex;
      align-items: center;
      gap: 0.5rem;
    }

    .nav-links a:hover {
      color: var(--primary);
    }

    .post-container {
      max-width: 600px;
      margin: 2rem auto;
      background: white;
      border-radius: 20px;
      overflow: hidden;
      box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
    }

    .post-image {
      width: 100%;
      max-height: 500px;
      object-fit: cover;
    }

    .post-content {
      padding: 1.5rem;
    }

    .back-btn {
      background: var(--primary);
      color: white;
      border: none;
      padding: 10px 20px;
      border-radius: 10px;
      cursor: pointer;
      margin: 1rem 2rem;
    }
  </style>
</head>
<body>
  <nav class="navbar">
    <div class="logo">
      <i class="fas fa-map-marked-alt"></i>
      <span>TravPin</span>
    </div>
    
    <ul class="nav-links">
      <li><a href="Home.php"><i class="fas fa-home"></i> Home</a></li>
      <li><a href="map_nav.php"><i class="fas fa-map"></i> Map</a></li>
      <li><a href="Explore.php"><i class="fas fa-compass"></i> Explore</a></li>
      <li><a href="Profile.php"><i class="fas fa-user"></i> Profile</a></li>
    </ul>
    
    <div class="nav-user">
      <img src="<?php echo $current_user['profile_pic'] ?: 'assets/default-avatar.png'; ?>" 
           alt="Profile" class="user-avatar">
      <span><?php echo htmlspecialchars($current_user['username']); ?></span>
    </div>
  </nav>

  <button class="back-btn" onclick="window.history.back()">
    <i class="fas fa-arrow-left"></i> Back
  </button>

  <div class="post-container">
    <img src="<?php echo htmlspecialchars($post['image_url']); ?>" alt="Post image" class="post-image">
    <div class="post-content">
      <h2><?php echo htmlspecialchars($post['location']); ?></h2>
      <p><?php echo htmlspecialchars($post['caption']); ?></p>
      <div style="display: flex; gap: 1rem; margin-top: 1rem; color: var(--gray);">
        <span><i class="fas fa-heart"></i> <?php echo $post['like_count']; ?> likes</span>
        <span><i class="fas fa-comment"></i> <?php echo $post['comment_count']; ?> comments</span>
      </div>
    </div>
  </div>
</body>
</html>
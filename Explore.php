<?php
session_start();
if (!isset($_SESSION["user_id"])) {
    header("Location: Login.php");
    exit();
}

include "db.php";

$user_id = $_SESSION["user_id"];

// Handle post creation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_post'])) {
    $location = trim($_POST['location']);
    $caption = trim($_POST['caption']);
    
    if (isset($_FILES['post_image']) && $_FILES['post_image']['error'] === 0) {
        $allowed_types = ['image/jpeg', 'image/png', 'image/gif'];
        $file_type = $_FILES['post_image']['type'];
        
        if (in_array($file_type, $allowed_types)) {
            $upload_dir = 'uploads/posts/';
            if (!is_dir($upload_dir)) {
                mkdir($upload_dir, 0777, true);
            }
            
            $file_extension = pathinfo($_FILES['post_image']['name'], PATHINFO_EXTENSION);
            $filename = 'post_' . $user_id . '_' . time() . '.' . $file_extension;
            $file_path = $upload_dir . $filename;
            
            if (move_uploaded_file($_FILES['post_image']['tmp_name'], $file_path)) {
                $sql = "INSERT INTO posts (user_id, location, image_url, caption) VALUES (?, ?, ?, ?)";
                $stmt = $conn->prepare($sql);
                $stmt->execute([$user_id, $location, $file_path, $caption]);
                
                // Create notification for followers (simplified)
                $stmt = $conn->prepare("INSERT INTO notifications (user_id, type, source_user_id, message) 
                                      VALUES (?, 'post', ?, ?)");
                $stmt->execute([$user_id, $user_id, "You shared a new post from $location"]);
                
                $success = "Post created successfully!";
            }
        }
    }
}
// Handle post updates
if (isset($_POST['update_post'])) {
    $post_id = $_POST['post_id'];
    $location = trim($_POST['location']);
    $caption = trim($_POST['caption']);
    
    // Verify post ownership
    $stmt = $conn->prepare("SELECT user_id FROM posts WHERE id = ?");
    $stmt->execute([$post_id]);
    $post = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($post && $post['user_id'] == $user_id) {
        $update_sql = "UPDATE posts SET location = ?, caption = ?";
        $params = [$location, $caption];
        
        // Handle image update if provided
        if (isset($_FILES['post_image']) && $_FILES['post_image']['error'] === 0) {
            $allowed_types = ['image/jpeg', 'image/png', 'image/gif'];
            $file_type = $_FILES['post_image']['type'];
            
            if (in_array($file_type, $allowed_types)) {
                $upload_dir = 'uploads/posts/';
                if (!is_dir($upload_dir)) {
                    mkdir($upload_dir, 0777, true);
                }
                
                $file_extension = pathinfo($_FILES['post_image']['name'], PATHINFO_EXTENSION);
                $filename = 'post_' . $user_id . '_' . time() . '.' . $file_extension;
                $file_path = $upload_dir . $filename;
                
                if (move_uploaded_file($_FILES['post_image']['tmp_name'], $file_path)) {
                    // Delete old image
                    $stmt = $conn->prepare("SELECT image_url FROM posts WHERE id = ?");
                    $stmt->execute([$post_id]);
                    $old_image = $stmt->fetch(PDO::FETCH_ASSOC);
                    if ($old_image['image_url'] && file_exists($old_image['image_url'])) {
                        unlink($old_image['image_url']);
                    }
                    
                    $update_sql .= ", image_url = ?";
                    $params[] = $file_path;
                }
            }
        }
        
        $update_sql .= " WHERE id = ?";
        $params[] = $post_id;
        
        $stmt = $conn->prepare($update_sql);
        if ($stmt->execute($params)) {
            $success = "Post updated successfully!";
            header("Location: Explore.php");
            exit();
        }
    }
}

// Handle likes
if (isset($_POST['like_post'])) {
    $post_id = $_POST['post_id'];
    
    // Check if already liked
    $stmt = $conn->prepare("SELECT id FROM likes WHERE user_id = ? AND post_id = ?");
    $stmt->execute([$user_id, $post_id]);
    
    if ($stmt->fetch()) {
        // Unlike
        $stmt = $conn->prepare("DELETE FROM likes WHERE user_id = ? AND post_id = ?");
        $stmt->execute([$user_id, $post_id]);
    } else {
        // Like
        $stmt = $conn->prepare("INSERT INTO likes (user_id, post_id) VALUES (?, ?)");
        $stmt->execute([$user_id, $post_id]);
        
        // Get post owner for notification
        $stmt = $conn->prepare("SELECT user_id FROM posts WHERE id = ?");
        $stmt->execute([$post_id]);
        $post_owner = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($post_owner && $post_owner['user_id'] != $user_id) {
            $stmt = $conn->prepare("INSERT INTO notifications (user_id, type, source_user_id, post_id, message) 
                                  VALUES (?, 'like', ?, ?, ?)");
            $stmt->execute([$post_owner['user_id'], $user_id, $post_id, "liked your post"]);
        }
    }
}

// Handle comments
if (isset($_POST['add_comment'])) {
    $post_id = $_POST['post_id'];
    $comment_text = trim($_POST['comment_text']);
    
    if (!empty($comment_text)) {
        $stmt = $conn->prepare("INSERT INTO comments (user_id, post_id, comment_text) VALUES (?, ?, ?)");
        $stmt->execute([$user_id, $post_id, $comment_text]);
        
        // Get post owner for notification
        $stmt = $conn->prepare("SELECT user_id FROM posts WHERE id = ?");
        $stmt->execute([$post_id]);
        $post_owner = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($post_owner && $post_owner['user_id'] != $user_id) {
            $stmt = $conn->prepare("INSERT INTO notifications (user_id, type, source_user_id, post_id, message) 
                                  VALUES (?, 'comment', ?, ?, ?)");
            $stmt->execute([$post_owner['user_id'], $user_id, $post_id, "commented on your post"]);
        }
    }
}
// Handle post deletion
if (isset($_POST['delete_post'])) {
    $post_id = $_POST['post_id'];
    
    // Verify post ownership
    $stmt = $conn->prepare("SELECT user_id FROM posts WHERE id = ?");
    $stmt->execute([$post_id]);
    $post = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($post && $post['user_id'] == $user_id) {
        // Delete associated data first
        $stmt = $conn->prepare("DELETE FROM likes WHERE post_id = ?");
        $stmt->execute([$post_id]);
        
        $stmt = $conn->prepare("DELETE FROM comments WHERE post_id = ?");
        $stmt->execute([$post_id]);
        
        $stmt = $conn->prepare("DELETE FROM notifications WHERE post_id = ?");
        $stmt->execute([$post_id]);
        
        // Get image path before deleting post
        $stmt = $conn->prepare("SELECT image_url FROM posts WHERE id = ?");
        $stmt->execute([$post_id]);
        $post_data = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // Delete the post
        $stmt = $conn->prepare("DELETE FROM posts WHERE id = ?");
        if ($stmt->execute([$post_id])) {
            // Delete the image file
            if ($post_data['image_url'] && file_exists($post_data['image_url'])) {
                unlink($post_data['image_url']);
            }
            $success = "Post deleted successfully!";
        }
    }
    
    // Refresh the page
    header("Location: Explore.php");
    exit();
}

// Get all posts with user info and engagement data
$stmt = $conn->prepare("
    SELECT p.*, u.username, u.profile_pic,
           COUNT(DISTINCT l.id) as like_count,
           COUNT(DISTINCT c.id) as comment_count,
           EXISTS(SELECT 1 FROM likes WHERE user_id = ? AND post_id = p.id) as user_liked
    FROM posts p 
    JOIN users u ON p.user_id = u.id 
    LEFT JOIN likes l ON p.id = l.post_id 
    LEFT JOIN comments c ON p.id = c.post_id 
    GROUP BY p.id 
    ORDER BY p.created_at DESC
");
$stmt->execute([$user_id]);
$posts = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get user info for header
$stmt = $conn->prepare("SELECT username, profile_pic FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>TravPin - Explore</title>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  <style>
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

    .nav-links a:hover,
    .nav-links a.active {
      color: var(--primary);
    }

    .nav-user {
      display: flex;
      align-items: center;
      gap: 1rem;
    }

    .user-avatar {
      width: 40px;
      height: 40px;
      border-radius: 50%;
      object-fit: cover;
      border: 2px solid var(--primary);
    }

    .notification-bell {
      position: relative;
      cursor: pointer;
      font-size: 1.2rem;
      color: var(--dark);
    }

    .notification-count {
      position: absolute;
      top: -8px;
      right: -8px;
      background: var(--primary);
      color: white;
      border-radius: 50%;
      width: 18px;
      height: 18px;
      font-size: 0.7rem;
      display: flex;
      align-items: center;
      justify-content: center;
    }

    .explore-container {
      max-width: 800px;
      margin: 2rem auto;
      padding: 0 2rem;
    }

    .create-post-card {
      background: white;
      border-radius: 20px;
      padding: 2rem;
      margin-bottom: 2rem;
      box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
      transition: transform 0.3s ease, box-shadow 0.3s ease;
    }

    .create-post-card:hover {
      transform: translateY(-5px);
      box-shadow: 0 15px 40px rgba(0, 0, 0, 0.15);
    }

    .create-post-btn {
      width: 100%;
      padding: 1rem;
      background: var(--light);
      border: 2px dashed var(--gray);
      border-radius: 15px;
      font-size: 1rem;
      color: var(--gray);
      cursor: pointer;
      text-align: left;
      transition: all 0.3s ease;
    }

    .create-post-btn:hover {
      border-color: var(--primary);
      color: var(--primary);
      background: rgba(255, 107, 107, 0.05);
    }

    .posts-container {
      display: flex;
      flex-direction: column;
      gap: 2rem;
    }

    .post-card {
      background: white;
      border-radius: 20px;
      box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
      overflow: hidden;
      transition: transform 0.3s ease, box-shadow 0.3s ease;
      opacity: 0;
      transform: translateY(20px);
      animation: fadeInUp 0.5s ease forwards;
    }

    @keyframes fadeInUp {
      to {
        opacity: 1;
        transform: translateY(0);
      }
    }

    .post-card:hover {
      transform: translateY(-5px);
      box-shadow: 0 15px 40px rgba(0, 0, 0, 0.15);
    }

    .post-header {
      padding: 1.5rem;
      display: flex;
      align-items: center;
      gap: 1rem;
    }

    .post-user-avatar {
      width: 50px;
      height: 50px;
      border-radius: 50%;
      object-fit: cover;
      border: 2px solid var(--primary);
      transition: transform 0.3s ease;
    }

    .post-user-avatar:hover {
      transform: scale(1.1);
    }

    .post-user-info h3 {
      margin: 0;
      color: var(--dark);
    }

    .post-user-info .location {
      color: var(--gray);
      font-size: 0.9rem;
      display: flex;
      align-items: center;
      gap: 0.3rem;
    }

    .post-image-container {
      position: relative;
      overflow: hidden;
    }

    .post-image {
      width: 100%;
      max-height: 500px;
      object-fit: cover;
      transition: transform 0.5s ease;
    }

    .post-image:hover {
      transform: scale(1.03);
    }

    .post-actions {
      padding: 1rem 1.5rem;
      display: flex;
      gap: 1rem;
      border-bottom: 1px solid #f0f0f0;
    }

    .action-btn {
      background: none;
      border: none;
      font-size: 1.2rem;
      cursor: pointer;
      color: var(--gray);
      transition: all 0.3s ease;
      position: relative;
      padding: 5px;
    }

    .action-btn.liked {
      color: var(--primary);
      animation: heartBeat 0.5s ease;
    }

    @keyframes heartBeat {
      0% { transform: scale(1); }
      50% { transform: scale(1.3); }
      100% { transform: scale(1); }
    }

    .action-btn:hover {
      color: var(--primary);
      transform: translateY(-2px);
    }

    .post-stats {
      padding: 1rem 1.5rem;
      color: var(--gray);
      font-size: 0.9rem;
    }

    .post-caption {
      padding: 0 1.5rem 1rem;
      line-height: 1.5;
    }

    .comments-section {
      padding: 0 1.5rem 1.5rem;
    }

    .comment {
      display: flex;
      gap: 0.5rem;
      margin-bottom: 0.5rem;
      padding: 0.5rem;
      border-radius: 10px;
      transition: background-color 0.2s ease;
    }

    .comment:hover {
      background-color: rgba(0, 0, 0, 0.02);
    }

    .comment-user {
      font-weight: bold;
      color: var(--dark);
    }

    .add-comment {
      display: flex;
      gap: 0.5rem;
      margin-top: 1rem;
    }

    .comment-input {
      flex: 1;
      padding: 0.5rem 1rem;
      border: 1px solid #e1e8ed;
      border-radius: 20px;
      font-size: 0.9rem;
      transition: border-color 0.3s ease, box-shadow 0.3s ease;
    }

    .comment-input:focus {
      outline: none;
      border-color: var(--primary);
      box-shadow: 0 0 0 2px rgba(255, 107, 107, 0.2);
    }

    .comment-btn {
      background: var(--primary);
      color: white;
      border: none;
      padding: 0.5rem 1rem;
      border-radius: 20px;
      cursor: pointer;
      transition: all 0.3s ease;
    }

    .comment-btn:hover {
      background: #ff5252;
      transform: translateY(-2px);
      box-shadow: 0 4px 8px rgba(255, 107, 107, 0.3);
    }

    .modal {
      display: none;
      position: fixed;
      top: 0;
      left: 0;
      width: 100%;
      height: 100%;
      background: rgba(0, 0, 0, 0.5);
      z-index: 2000;
      align-items: center;
      justify-content: center;
      animation: fadeIn 0.3s ease;
    }

    @keyframes fadeIn {
      from { opacity: 0; }
      to { opacity: 1; }
    }

    .modal-content {
      background: white;
      padding: 2rem;
      border-radius: 20px;
      width: 90%;
      max-width: 500px;
      animation: slideUp 0.3s ease;
      box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
    }

    @keyframes slideUp {
      from { 
        opacity: 0;
        transform: translateY(50px);
      }
      to { 
        opacity: 1;
        transform: translateY(0);
      }
    }

    .form-group {
      margin-bottom: 1rem;
    }

    .form-group label {
      display: block;
      margin-bottom: 0.5rem;
      font-weight: 600;
    }

    .form-control {
      width: 100%;
      padding: 10px;
      border: 2px solid #e1e8ed;
      border-radius: 10px;
      font-size: 1rem;
      transition: border-color 0.3s ease, box-shadow 0.3s ease;
    }

    .form-control:focus {
      outline: none;
      border-color: var(--primary);
      box-shadow: 0 0 0 3px rgba(255, 107, 107, 0.2);
    }

    .image-preview {
      width: 100%;
      max-height: 200px;
      object-fit: cover;
      border-radius: 10px;
      margin-top: 1rem;
      display: none;
      animation: fadeIn 0.5s ease;
    }

    .success-message {
      background: var(--secondary);
      color: white;
      padding: 1rem;
      border-radius: 10px;
      margin-bottom: 1rem;
      animation: slideIn 0.5s ease;
      box-shadow: 0 5px 15px rgba(78, 205, 196, 0.3);
    }

    @keyframes slideIn {
      from {
        opacity: 0;
        transform: translateX(-20px);
      }
      to {
        opacity: 1;
        transform: translateX(0);
      }
    }

    .empty-state {
      text-align: center;
      padding: 3rem;
      color: var(--gray);
    }

    .empty-state i {
      font-size: 3rem;
      margin-bottom: 1rem;
      color: var(--gray);
    }

    .floating-action-btn {
      position: fixed;
      bottom: 2rem;
      right: 2rem;
      width: 60px;
      height: 60px;
      background: var(--primary);
      color: white;
      border-radius: 50%;
      display: flex;
      align-items: center;
      justify-content: center;
      font-size: 1.5rem;
      box-shadow: 0 5px 20px rgba(255, 107, 107, 0.4);
      cursor: pointer;
      z-index: 100;
      transition: all 0.3s ease;
      animation: pulse 2s infinite;
    }

    @keyframes pulse {
      0% {
        box-shadow: 0 0 0 0 rgba(255, 107, 107, 0.7);
      }
      70% {
        box-shadow: 0 0 0 10px rgba(255, 107, 107, 0);
      }
      100% {
        box-shadow: 0 0 0 0 rgba(255, 107, 107, 0);
      }
    }

    .floating-action-btn:hover {
      transform: scale(1.1);
      box-shadow: 0 8px 25px rgba(255, 107, 107, 0.5);
    }

    .notification-panel {
      display: none;
      position: fixed;
      top: 80px;
      right: 20px;
      width: 300px;
      background: white;
      border-radius: 15px;
      box-shadow: 0 10px 30px rgba(0,0,0,0.2);
      z-index: 1000;
      max-height: 400px;
      overflow-y: auto;
      animation: slideDown 0.3s ease;
    }

    @keyframes slideDown {
      from {
        opacity: 0;
        transform: translateY(-10px);
      }
      to {
        opacity: 1;
        transform: translateY(0);
      }
    }

    .notification-item {
      padding: 1rem;
      border-bottom: 1px solid #f0f0f0;
      transition: background-color 0.2s ease;
    }

    .notification-item:hover {
      background-color: rgba(0, 0, 0, 0.03);
    }

    .notification-item.unread {
      background: #f8f9fa;
    }

    .notification-item:last-child {
      border-bottom: none;
    }

    @media (max-width: 768px) {
      .navbar {
        flex-direction: column;
        gap: 1rem;
      }
      
      .explore-container {
        padding: 0 1rem;
      }

      .floating-action-btn {
        bottom: 1rem;
        right: 1rem;
      }
    }

    .stagger-delay-1 { animation-delay: 0.1s; }
    .stagger-delay-2 { animation-delay: 0.2s; }
    .stagger-delay-3 { animation-delay: 0.3s; }
    .stagger-delay-4 { animation-delay: 0.4s; }
    .stagger-delay-5 { animation-delay: 0.5s; }
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
      <li><a href="Explore.php" class="active"><i class="fas fa-compass"></i> Explore</a></li>
      <li><a href="Profile.php"><i class="fas fa-user"></i> Profile</a></li>
    </ul>
    
    <div class="nav-user">
      <div class="notification-bell" onclick="toggleNotifications()">
        <i class="fas fa-bell"></i>
        <span class="notification-count" id="notificationCount">0</span>
      </div>
      <img src="<?php echo $user['profile_pic'] ?: 'assets/default-avatar.png'; ?>" 
           alt="Profile" class="user-avatar">
      <span><?php echo htmlspecialchars($user['username']); ?></span>
      <a href="logout.php" style="color: var(--primary); text-decoration: none; margin-left: 1rem;">
        <i class="fas fa-sign-out-alt"></i>
      </a>
    </div>
  </nav>

  <div class="explore-container">
    <?php if(isset($success)): ?>
      <div class="success-message"><?php echo $success; ?></div>
    <?php endif; ?>

    <div class="create-post-card">
      <button class="create-post-btn" onclick="openCreateModal()">
        <i class="fas fa-plus"></i> Share your travel experience...
      </button>
    </div>

    <div class="posts-container" id="postsContainer">
      <?php if(empty($posts)): ?>
        <div class="empty-state">
          <i class="fas fa-compass"></i>
          <h3>No posts yet</h3>
          <p>Be the first to share your travel experience!</p>
        </div>
      <?php else: ?>
        <?php foreach($posts as $index => $post): ?>
        <div class="post-card stagger-delay-<?php echo ($index % 5) + 1; ?>" id="post-<?php echo $post['id']; ?>">
<div class="post-header">
  <img src="<?php echo $post['profile_pic'] ?: 'assets/default-avatar.png'; ?>" 
       alt="User" class="post-user-avatar" 
       style="cursor: pointer;" 
       onclick="viewProfile(<?php echo $post['user_id']; ?>)">
  <div class="post-user-info">
    <h3 style="cursor: pointer;" onclick="viewProfile(<?php echo $post['user_id']; ?>)">
      <?php echo htmlspecialchars($post['username']); ?>
    </h3>
    <div class="location" style="cursor: pointer;" onclick="viewOnMap('<?php echo urlencode($post['location']); ?>')">
      <i class="fas fa-map-marker-alt"></i> 
      <?php echo htmlspecialchars($post['location']); ?>
    </div>
  </div>
  
  <?php if ($post['user_id'] == $user_id): ?>
  <div style="margin-left: auto; display: flex; gap: 0.5rem;">
    <button class="action-btn" onclick="openEditModal(<?php echo $post['id']; ?>, '<?php echo addslashes($post['location']); ?>', '<?php echo addslashes($post['caption']); ?>', '<?php echo $post['image_url']; ?>')"
            style="color: var(--gray);">
      <i class="fas fa-edit"></i>
    </button>
    <form method="POST" style="display: inline;">
      <input type="hidden" name="post_id" value="<?php echo $post['id']; ?>">
      <button type="submit" name="delete_post" class="action-btn" 
              onclick="return confirm('Are you sure you want to delete this post?')"
              style="color: var(--gray);">
        <i class="fas fa-trash"></i>
      </button>
    </form>
  </div>
  <?php endif; ?>
</div>
          <div class="post-image-container">
            <img src="<?php echo htmlspecialchars($post['image_url']); ?>" 
                 alt="Post image" class="post-image">
          </div>

          <div class="post-actions">
            <form method="POST" style="display: inline;">
              <input type="hidden" name="post_id" value="<?php echo $post['id']; ?>">
              <button type="submit" name="like_post" class="action-btn <?php echo $post['user_liked'] ? 'liked' : ''; ?>">
                <i class="fas fa-heart"></i>
              </button>
            </form>
            <button class="action-btn" onclick="focusComment(<?php echo $post['id']; ?>)">
              <i class="fas fa-comment"></i>
            </button>
          </div>

          <div class="post-stats">
            <?php echo $post['like_count']; ?> likes • <?php echo $post['comment_count']; ?> comments
          </div>

          <div class="post-caption">
            <strong><?php echo htmlspecialchars($post['username']); ?></strong> 
            <?php echo htmlspecialchars($post['caption']); ?>
          </div>

          <div class="comments-section">
            <div id="comments-<?php echo $post['id']; ?>">
              <!-- Comments loaded via AJAX -->
            </div>
            
            <form method="POST" class="add-comment">
              <input type="hidden" name="post_id" value="<?php echo $post['id']; ?>">
              <input type="text" name="comment_text" placeholder="Add a comment..." 
                     class="comment-input" id="comment-input-<?php echo $post['id']; ?>">
              <button type="submit" name="add_comment" class="comment-btn">Post</button>
            </form>
          </div>
        </div>
        <?php endforeach; ?>
      <?php endif; ?>
    </div>
  </div>

  <!-- Floating Action Button -->
  <div class="floating-action-btn" onclick="openCreateModal()">
    <i class="fas fa-plus"></i>
  </div>

  <!-- Create Post Modal -->
  <div id="createModal" class="modal">
    <div class="modal-content">
      <h2 style="margin-bottom: 1rem;">Create Post</h2>
      <form method="POST" enctype="multipart/form-data" id="createPostForm">
        <div class="form-group">
          <label>Location</label>
          <input type="text" name="location" class="form-control" placeholder="Where was this taken?" required>
        </div>
        
        <div class="form-group">
          <label>Image</label>
          <input type="file" name="post_image" class="form-control" accept="image/*" required onchange="previewImage(this)">
          <img id="imagePreview" class="image-preview">
        </div>
        
        <div class="form-group">
          <label>Caption</label>
          <textarea name="caption" class="form-control" rows="3" placeholder="Share your experience..." required></textarea>
        </div>
        
        <div style="display: flex; gap: 1rem; margin-top: 1rem;">
          <button type="button" onclick="closeCreateModal()" style="flex: 1; padding: 10px; border: 2px solid var(--gray); background: white; border-radius: 10px; cursor: pointer; transition: all 0.3s ease;">
            Cancel
          </button>
          <button type="submit" name="create_post" style="flex: 1; padding: 10px; background: var(--primary); color: white; border: none; border-radius: 10px; cursor: pointer; transition: all 0.3s ease;">
            Share Post
          </button>
        </div>
      </form>
    </div>
  </div>
<!-- Edit Post Modal -->
  <div id="editPostModal" class="modal">
    <div class="modal-content">
      <h2 style="margin-bottom: 1rem;">Edit Post</h2>
      <form method="POST" enctype="multipart/form-data" id="editPostForm">
        <input type="hidden" name="post_id" id="edit_post_id">
      
        <div class="form-group">
          <label>Location</label>
          <input type="text" name="location" id="edit_location" class="form-control" placeholder="Where was this taken?" required>
        </div>
      
        <div class="form-group">
          <label>Image</label>
          <input type="file" name="post_image" class="form-control" accept="image/*" onchange="previewEditImage(this)">
          <img id="editImagePreview" class="image-preview">
          <small>Leave empty to keep current image</small>
        </div>
      
        <div class="form-group">
          <label>Caption</label>
          <textarea name="caption" id="edit_caption" class="form-control" rows="3" placeholder="Share your experience..." required></textarea>
        </div>
      
        <div style="display: flex; gap: 1rem; margin-top: 1rem;">
          <button type="button" onclick="closeEditModal()" style="flex: 1; padding: 10px; border: 2px solid var(--gray); background: white; border-radius: 10px; cursor: pointer;">
            Cancel
          </button>
          <button type="submit" name="update_post" style="flex: 1; padding: 10px; background: var(--primary); color: white; border: none; border-radius: 10px; cursor: pointer;">
            Update Post
          </button>
        </div>
      </form>
    </div>
  </div>

  <!-- Notifications Panel -->
  <div id="notificationsPanel" class="notification-panel">
    <div style="padding: 1rem; border-bottom: 1px solid #eee; display: flex; justify-content: space-between; align-items: center;">
      <h3 style="margin: 0;">Notifications</h3>
      <button onclick="markAllAsRead()" style="background: none; border: none; color: var(--primary); cursor: pointer; font-size: 0.8rem;">
        Mark all as read
      </button>
    </div>
    <div id="notificationsList">
      <!-- Notifications will be loaded here -->
    </div>
  </div>

  <script>
    function viewOnMap(location) {
      window.location.href = `map_nav.php?search=${location}`;
    }

    function openEditModal(postId, location, caption, imageUrl) {
      document.getElementById('edit_post_id').value = postId;
      document.getElementById('edit_location').value = location;
      document.getElementById('edit_caption').value = caption;
      document.getElementById('editImagePreview').src = imageUrl;
      document.getElementById('editImagePreview').style.display = 'block';
      document.getElementById('editPostModal').style.display = 'flex';
    }

    function closeEditModal() {
      document.getElementById('editPostModal').style.display = 'none';
    }

    function previewEditImage(input) {
      const preview = document.getElementById('editImagePreview');
      if (input.files && input.files[0]) {
        const reader = new FileReader();
        reader.onload = function(e) {
        preview.src = e.target.result;
        preview.style.display = 'block';
      }
      reader.readAsDataURL(input.files[0]);
    }
  }
      function viewProfile(userId) {
      window.location.href = `view_profile.php?user_id=${userId}`;
    }
    function toggleNotifications() {
      const panel = document.getElementById('notificationsPanel');
      panel.style.display = panel.style.display === 'none' ? 'block' : 'none';
      if (panel.style.display === 'block') {
        loadNotifications();
      }
    }

    function loadNotifications() {
      fetch('get_notifications.php')
        .then(response => response.json())
        .then(data => {
          document.getElementById('notificationCount').textContent = data.unread_count;
          const list = document.getElementById('notificationsList');
          list.innerHTML = data.notifications.map(notif => `
            <div class="notification-item ${notif.is_read ? '' : 'unread'}" onclick="markAsRead(${notif.id})">
              <div style="font-size: 0.9rem;">${notif.message}</div>
              <div style="font-size: 0.7rem; color: #666; margin-top: 0.5rem;">
                ${new Date(notif.created_at).toLocaleDateString()}
              </div>
            </div>
          `).join('');
        });
    }

    function markAsRead(notificationId) {
      // Implement marking notification as read
      console.log('Mark notification as read:', notificationId);
    }

    function markAllAsRead() {
      // Implement marking all notifications as read
      console.log('Mark all notifications as read');
    }

    function openCreateModal() {
      document.getElementById('createModal').style.display = 'flex';
    }

    function closeCreateModal() {
      document.getElementById('createModal').style.display = 'none';
      document.getElementById('imagePreview').style.display = 'none';
      document.getElementById('createPostForm').reset();
    }

    function previewImage(input) {
      const preview = document.getElementById('imagePreview');
      if (input.files && input.files[0]) {
        const reader = new FileReader();
        reader.onload = function(e) {
          preview.src = e.target.result;
          preview.style.display = 'block';
        }
        reader.readAsDataURL(input.files[0]);
      }
    }

    function focusComment(postId) {
      const input = document.getElementById('comment-input-' + postId);
      input.focus();
    }

    // Load comments for each post
    document.addEventListener('DOMContentLoaded', function() {
      <?php foreach($posts as $post): ?>
        loadComments(<?php echo $post['id']; ?>);
      <?php endforeach; ?>
    });

    function loadComments(postId) {
      fetch('get_comments.php?post_id=' + postId)
        .then(response => response.json())
        .then(comments => {
          const container = document.getElementById('comments-' + postId);
          container.innerHTML = comments.map(comment => `
            <div class="comment">
              <strong>${comment.username}</strong>
              <span>${comment.comment_text}</span>
            </div>
          `).join('');
        });
    }

    // Close modal when clicking outside
    window.onclick = function(event) {
      const modal = document.getElementById('createModal');
      const notificationsPanel = document.getElementById('notificationsPanel');
      
      if (event.target === modal) {
        closeCreateModal();
      }
      
      if (!event.target.closest('.notification-bell') && !event.target.closest('#notificationsPanel')) {
        notificationsPanel.style.display = 'none';
      }
    }

    // Load notifications every 30 seconds
    setInterval(loadNotifications, 30000);
    loadNotifications();
  </script>
</body>
</html>
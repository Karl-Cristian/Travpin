<?php
session_start();
include "db.php";

if (!isset($_SESSION["user_id"])) {
    header("Location: Login.php");
    exit();
}

$viewed_user_id = $_GET['user_id'] ?? null;
$current_user_id = $_SESSION["user_id"];

if (!$viewed_user_id) {
    header("Location: Explore.php");
    exit();
}

// Get viewed user data
$stmt = $conn->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$viewed_user_id]);
$viewed_user = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$viewed_user) {
    header("Location: Explore.php");
    exit();
}

// Get current user info for header
$stmt = $conn->prepare("SELECT username, profile_pic FROM users WHERE id = ?");
$stmt->execute([$current_user_id]);
$current_user = $stmt->fetch(PDO::FETCH_ASSOC);

// Get viewed user's posts
$stmt = $conn->prepare("
    SELECT p.*, COUNT(l.id) as like_count, 
           COUNT(DISTINCT c.id) as comment_count
    FROM posts p 
    LEFT JOIN likes l ON p.id = l.post_id 
    LEFT JOIN comments c ON p.id = c.post_id 
    WHERE p.user_id = ? 
    GROUP BY p.id 
    ORDER BY p.created_at DESC
");
$stmt->execute([$viewed_user_id]);
$user_posts = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get user stats
$stmt = $conn->prepare("
    SELECT 
        (SELECT COUNT(*) FROM posts WHERE user_id = ?) as post_count,
        (SELECT COUNT(*) FROM likes WHERE user_id = ?) as like_count,
        (SELECT COUNT(*) FROM comments WHERE user_id = ?) as comment_count
");
$stmt->execute([$viewed_user_id, $viewed_user_id, $viewed_user_id]);
$user_stats = $stmt->fetch(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>TravPin - <?php echo htmlspecialchars($viewed_user['username']); ?></title>
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

    .profile-container {
      max-width: 1200px;
      margin: 2rem auto;
      padding: 0 2rem;
    }

    .banner {
      height: 300px;
      background: linear-gradient(135deg, var(--secondary), var(--accent));
      border-radius: 20px;
      position: relative;
      margin-bottom: 100px;
      overflow: hidden;
    }

    .banner-image {
      width: 100%;
      height: 100%;
      object-fit: cover;
    }

    .profile-header {
      background: white;
      border-radius: 20px;
      padding: 2rem;
      margin-top: -80px;
      position: relative;
      box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
    }

    .profile-info {
      display: flex;
      gap: 2rem;
      align-items: flex-start;
    }

    .profile-avatar {
      width: 150px;
      height: 150px;
      border-radius: 50%;
      object-fit: cover;
      border: 5px solid white;
      box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
      margin-top: -100px;
    }

    .profile-details {
      flex: 1;
    }

    .profile-stats {
      display: grid;
      grid-template-columns: repeat(3, 1fr);
      gap: 1rem;
      margin: 2rem 0;
    }

    .stat-card {
      background: var(--light);
      padding: 1.5rem;
      border-radius: 15px;
      text-align: center;
    }

    .stat-number {
      font-size: 2rem;
      font-weight: bold;
      color: var(--primary);
    }

    .stat-label {
      color: var(--gray);
      font-size: 0.9rem;
    }

    .posts-grid {
      display: grid;
      grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
      gap: 2rem;
      margin-top: 2rem;
    }

    .post-card {
      background: white;
      border-radius: 15px;
      overflow: hidden;
      box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
    }

    .post-card img {
      width: 100%;
      height: 200px;
      object-fit: cover;
    }

    .post-content {
      padding: 1rem;
    }

    .back-btn {
      background: var(--primary);
      color: white;
      border: none;
      padding: 10px 20px;
      border-radius: 10px;
      cursor: pointer;
      font-weight: 600;
      margin-bottom: 1rem;
    }

    @media (max-width: 768px) {
      .profile-info {
        flex-direction: column;
        text-align: center;
      }
      
      .profile-stats {
        grid-template-columns: 1fr;
      }
      
      .navbar {
        flex-direction: column;
        gap: 1rem;
      }
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
      <li><a href="Profile.php"><i class="fas fa-user"></i> My Profile</a></li>
    </ul>
    
    <div class="nav-user">
      <div class="notification-bell" onclick="toggleNotifications()">
        <i class="fas fa-bell"></i>
        <span class="notification-count" id="notificationCount">0</span>
      </div>
      <img src="<?php echo $current_user['profile_pic'] ?: 'assets/default-avatar.png'; ?>" 
           alt="Profile" class="user-avatar">
      <span><?php echo htmlspecialchars($current_user['username']); ?></span>
      <a href="logout.php" style="color: var(--primary); text-decoration: none; margin-left: 1rem;">
        <i class="fas fa-sign-out-alt"></i>
      </a>
    </div>
  </nav>

  <div class="profile-container">
    <button class="back-btn" onclick="window.history.back()">
      <i class="fas fa-arrow-left"></i> Back
    </button>

    <div class="banner">
      <?php if($viewed_user['banner_image']): ?>
        <img src="<?php echo $viewed_user['banner_image']; ?>" alt="Banner" class="banner-image">
      <?php endif; ?>
    </div>

    <div class="profile-header">
      <div class="profile-info">
        <div>
          <img src="<?php echo $viewed_user['profile_pic'] ?: 'assets/default-avatar.png'; ?>" 
               alt="Profile" class="profile-avatar">
        </div>
        
        <div class="profile-details">
          <h1 style="font-size: 2rem; margin-bottom: 0.5rem;"><?php echo htmlspecialchars($viewed_user['username']); ?></h1>
          <p style="color: var(--gray); margin-bottom: 1rem;"><?php echo htmlspecialchars($viewed_user['bio'] ?: 'No bio yet'); ?></p>
          
          <div class="profile-stats">
            <div class="stat-card">
              <div class="stat-number"><?php echo $user_stats['post_count']; ?></div>
              <div class="stat-label">Posts</div>
            </div>
            <div class="stat-card">
              <div class="stat-number"><?php echo $user_stats['like_count']; ?></div>
              <div class="stat-label">Likes</div>
            </div>
            <div class="stat-card">
              <div class="stat-number"><?php echo $user_stats['comment_count']; ?></div>
              <div class="stat-label">Comments</div>
            </div>
          </div>
          
          <div style="display: flex; gap: 1rem; flex-wrap: wrap;">
            <div><strong>Hometown:</strong> <?php echo htmlspecialchars($viewed_user['hometown'] ?: 'Not set'); ?></div>
            <div><strong>Education:</strong> <?php echo htmlspecialchars($viewed_user['education'] ?: 'Not set'); ?></div>
          </div>
        </div>
      </div>
    </div>

    <h2 style="color: white; margin: 2rem 0 1rem 0;"><?php echo htmlspecialchars($viewed_user['username']); ?>'s Posts</h2>
    <div class="posts-grid">
      <?php foreach($user_posts as $post): ?>
      <div class="post-card">
        <img src="<?php echo htmlspecialchars($post['image_url']); ?>" alt="Post image">
        <div class="post-content">
          <h3><?php echo htmlspecialchars($post['location']); ?></h3>
          <p><?php echo htmlspecialchars($post['caption']); ?></p>
          <div style="display: flex; gap: 1rem; margin-top: 1rem; color: var(--gray); font-size: 0.9rem;">
            <span><i class="fas fa-heart"></i> <?php echo $post['like_count']; ?> likes</span>
            <span><i class="fas fa-comment"></i> <?php echo $post['comment_count']; ?> comments</span>
          </div>
        </div>
      </div>
      <?php endforeach; ?>
      
      <?php if(empty($user_posts)): ?>
        <div style="grid-column: 1 / -1; text-align: center; color: white; padding: 2rem;">
          <i class="fas fa-camera" style="font-size: 3rem; margin-bottom: 1rem; opacity: 0.5;"></i>
          <h3>No posts yet</h3>
          <p>This user hasn't shared any travel experiences.</p>
        </div>
      <?php endif; ?>
    </div>
  </div>

  <!-- Notifications Panel -->
  <div id="notificationsPanel" style="display: none; position: fixed; top: 80px; right: 20px; 
       width: 300px; background: white; border-radius: 15px; box-shadow: 0 10px 30px rgba(0,0,0,0.2); 
       z-index: 1000; max-height: 400px; overflow-y: auto;">
    <div style="padding: 1rem; border-bottom: 1px solid #eee;">
      <h3 style="margin: 0;">Notifications</h3>
    </div>
    <div id="notificationsList">
      <!-- Notifications will be loaded here -->
    </div>
  </div>

  <script>
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
            <div style="padding: 1rem; border-bottom: 1px solid #f0f0f0; ${notif.is_read ? '' : 'background: #f8f9fa;'}">
              <div style="font-size: 0.9rem;">${notif.message}</div>
              <div style="font-size: 0.7rem; color: #666; margin-top: 0.5rem;">
                ${new Date(notif.created_at).toLocaleDateString()}
              </div>
            </div>
          `).join('');
        });
    }

    // Load notifications every 30 seconds
    setInterval(loadNotifications, 30000);
    loadNotifications();
  </script>
</body>
</html>
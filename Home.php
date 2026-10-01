<?php
session_start();
if (!isset($_SESSION["user_id"])) {
    header("Location: Login.php");
    exit();
}

include "db.php";

// Get user info
$user_id = $_SESSION["user_id"];
$stmt = $conn->prepare("SELECT username, profile_pic FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

// Get recent posts
$stmt = $conn->prepare("
    SELECT p.*, u.username, u.profile_pic, 
           COUNT(l.id) as like_count,
           EXISTS(SELECT 1 FROM likes WHERE user_id = ? AND post_id = p.id) as user_liked
    FROM posts p 
    JOIN users u ON p.user_id = u.id 
    LEFT JOIN likes l ON p.id = l.post_id 
    GROUP BY p.id 
    ORDER BY p.created_at DESC 
    LIMIT 3
");
$stmt->execute([$user_id]);
$posts = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>TravPin - Home</title>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
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

    .logo i {
      font-size: 1.8rem;
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

    .hero {
      padding: 4rem 2rem;
      text-align: center;
      color: white;
    }

    .hero h1 {
      font-size: 3.5rem;
      margin-bottom: 1rem;
      background: linear-gradient(45deg, #fff, #f0f0f0);
      -webkit-background-clip: text;
      background-clip: text;
      -webkit-text-fill-color: transparent;
    }

    .hero p {
      font-size: 1.2rem;
      margin-bottom: 2rem;
      opacity: 0.9;
    }

    .top-posts-grid,
    .featured-cards {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
      gap: 2rem;
      padding: 2rem;
      max-width: 1200px;
      margin: 0 auto;
    }

    .card {
      background: rgba(255, 255, 255, 0.95);
      border-radius: 20px;
      overflow: hidden;
      box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
      transition: transform 0.3s, box-shadow 0.3s;
      cursor: pointer;
    }

    .card:hover {
      transform: translateY(-10px);
      box-shadow: 0 20px 40px rgba(0, 0, 0, 0.2);
    }

    .card img {
      width: 100%;
      height: 200px;
      object-fit: cover;
    }

    .card-content {
      padding: 1.5rem;
    }

    .card h3 {
      color: var(--dark);
      margin-bottom: 0.5rem;
    }

    .card p {
      color: var(--gray);
      margin-bottom: 1rem;
    }

    .card-meta {
      display: flex;
      justify-content: space-between;
      align-items: center;
      font-size: 0.9rem;
      color: var(--gray);
    }

    .user-info {
      display: flex;
      align-items: center;
      gap: 0.5rem;
    }

    .user-avatar-small {
      width: 30px;
      height: 30px;
      border-radius: 50%;
      object-fit: cover;
    }

    /* Footer Styles */
    .footer {
      background: rgba(255, 255, 255, 0.95);
      padding: 3rem 2rem;
      margin-top: auto;
      text-align: center;
      backdrop-filter: blur(10px);
      box-shadow: 0 -4px 20px rgba(0, 0, 0, 0.1);
    }

    .footer-content {
      max-width: 1200px;
      margin: 0 auto;
    }

    .footer h3 {
      color: var(--primary);
      margin-bottom: 2rem;
      font-size: 1.8rem;
    }

    .social-links {
      display: flex;
      justify-content: center;
      gap: 2rem;
      margin-bottom: 3rem;
      flex-wrap: wrap;
    }

    .social-links a {
      color: var(--dark);
      font-size: 1.5rem;
      transition: color 0.3s, transform 0.3s;
      display: flex;
      align-items: center;
      justify-content: center;
      width: 50px;
      height: 50px;
      border-radius: 50%;
      background: var(--light);
    }

    .social-links a:hover {
      color: white;
      background: var(--primary);
      transform: translateY(-3px);
    }

    .footer p {
      color: var(--gray);
      font-size: 0.9rem;
    }

    .notifications-panel {
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
    }

    .notifications-header {
      padding: 1rem;
      border-bottom: 1px solid #eee;
    }

    .notifications-header h3 {
      margin: 0;
      color: var(--dark);
    }

    .notification-item {
      padding: 1rem;
      border-bottom: 1px solid #f0f0f0;
    }

    .notification-item.unread {
      background: #f8f9fa;
    }

    .notification-message {
      font-size: 0.9rem;
      color: var(--dark);
    }

    .notification-date {
      font-size: 0.7rem;
      color: #666;
      margin-top: 0.5rem;
    }

    @media (max-width: 768px) {
      .navbar {
        flex-direction: column;
        gap: 1rem;
      }

      .nav-links {
        gap: 1rem;
      }

      .hero h1 {
        font-size: 2.5rem;
      }

      .top-posts-grid,
      .featured-cards {
        grid-template-columns: 1fr;
        padding: 1rem;
      }

      .social-links {
        gap: 1.5rem;
      }

      .social-links a {
        width: 45px;
        height: 45px;
        font-size: 1.3rem;
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
      <li><a href="Home.php" class="active"><i class="fas fa-home"></i> Home</a></li>
      <li><a href="map_nav.php"><i class="fas fa-map"></i> Map</a></li>
      <li><a href="Explore.php"><i class="fas fa-compass"></i> Explore</a></li>
      <li><a href="Profile.php"><i class="fas fa-user"></i> Profile</a></li>
    </ul>
    
    <div class="nav-user">
      <div class="notification-bell" id="notificationBell">
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

  <section class="hero">
    <h1>Discover Your Next Adventure</h1>
    <p>Share your travel experiences and get inspired by fellow explorers</p>
    <a href="Explore.php" style="background: white; color: var(--primary); padding: 12px 30px; 
       border-radius: 25px; text-decoration: none; font-weight: bold; display: inline-block;">
      Start Exploring
    </a>
  </section>

  <!-- Top Posts Section -->
  <div class="top-posts-grid">
    <?php
    // Get top liked post
    $stmt = $conn->prepare("
      SELECT p.*, u.username, COUNT(l.id) as like_count
      FROM posts p 
      JOIN users u ON p.user_id = u.id 
      LEFT JOIN likes l ON p.id = l.post_id 
      GROUP BY p.id 
      ORDER BY like_count DESC 
      LIMIT 1
    ");
    $stmt->execute();
    $top_liked = $stmt->fetch(PDO::FETCH_ASSOC);

    // Get top commented post
    $stmt = $conn->prepare("
      SELECT p.*, u.username, COUNT(c.id) as comment_count
      FROM posts p 
      JOIN users u ON p.user_id = u.id 
      LEFT JOIN comments c ON p.id = c.post_id 
      GROUP BY p.id 
      ORDER BY comment_count DESC 
      LIMIT 1
    ");
    $stmt->execute();
    $top_commented = $stmt->fetch(PDO::FETCH_ASSOC);
    ?>

    <!-- Top Liked Post -->
    <?php if($top_liked): ?>
    <div class="card" data-post-id="<?php echo $top_liked['id']; ?>">
      <img src="<?php echo htmlspecialchars($top_liked['image_url']); ?>" alt="Top Liked Post">
      <div class="card-content">
        <h3>🔥 Most Liked Post</h3>
        <p><?php echo htmlspecialchars($top_liked['caption']); ?></p>
        <div class="card-meta">
          <span>@<?php echo htmlspecialchars($top_liked['username']); ?></span>
          <span><i class="fas fa-heart"></i> <?php echo $top_liked['like_count']; ?> likes</span>
        </div>
      </div>
    </div>
    <?php endif; ?>

    <!-- Top Commented Post -->
    <?php if($top_commented): ?>
    <div class="card" data-post-id="<?php echo $top_commented['id']; ?>">
      <img src="<?php echo htmlspecialchars($top_commented['image_url']); ?>" alt="Top Commented Post">
      <div class="card-content">
        <h3>💬 Most Discussed</h3>
        <p><?php echo htmlspecialchars($top_commented['caption']); ?></p>
        <div class="card-meta">
          <span>@<?php echo htmlspecialchars($top_commented['username']); ?></span>
          <span><i class="fas fa-comment"></i> <?php echo $top_commented['comment_count']; ?> comments</span>
        </div>
      </div>
    </div>
    <?php endif; ?>
  </div>

  <div class="featured-cards">
    <?php foreach($posts as $post): ?>
    <div class="card" data-post-id="<?php echo $post['id']; ?>">
      <img src="<?php echo htmlspecialchars($post['image_url']); ?>" alt="Travel destination">
      <div class="card-content">
        <h3><?php echo htmlspecialchars($post['location']); ?></h3>
        <p><?php echo htmlspecialchars($post['caption']); ?></p>
        <div class="card-meta">
          <div class="user-info">
            <img src="<?php echo htmlspecialchars($post['profile_pic'] ?: 'assets/default-avatar.png'); ?>" 
                 alt="User" class="user-avatar-small">
            <span>@<?php echo htmlspecialchars($post['username']); ?></span>
          </div>
          <div>
            <i class="fas fa-heart" style="color: <?php echo $post['user_liked'] ? 'var(--primary)' : 'var(--gray)'; ?>"></i>
            <?php echo $post['like_count']; ?>
          </div>
        </div>
      </div>
    </div>
    <?php endforeach; ?>
  </div>

  <!-- Notifications Panel -->
  <div id="notificationsPanel" class="notifications-panel">
    <div class="notifications-header">
      <h3>Notifications</h3>
    </div>
    <div id="notificationsList">
      <!-- Notifications will be loaded here -->
    </div>
  </div>

  <!-- Footer Section -->
  <footer class="footer">
    <div class="footer-content">
      <h3>Connect With Us</h3>
      
      <!-- Social Media Links -->
      <div class="social-links">
        <a href="#" aria-label="Facebook"><i class="fab fa-facebook"></i></a>
        <a href="#" aria-label="Twitter"><i class="fab fa-twitter"></i></a>
        <a href="#" aria-label="Instagram"><i class="fab fa-instagram"></i></a>
        <a href="#" aria-label="LinkedIn"><i class="fab fa-linkedin"></i></a>
      </div>

      <p>&copy; 2025 TravPin. All rights reserved.</p>
    </div>
  </footer>

  <script src="jquery-3.7.1.min.js"></script>
  <script>
    $(document).ready(function() {
      // View Post Function (attached via jQuery for better practice)
      function viewPost(postId) {
        window.location.href = 'view_post.php?post_id=' + postId;
      }

      // Attach click handlers to cards
      $('.card').on('click', function() {
        var postId = $(this).data('post-id');
        if (postId) {
          viewPost(postId);
        }
      });

      // Toggle Notifications
      function toggleNotifications() {
        var $panel = $('#notificationsPanel');
        $panel.toggle();
        if ($panel.is(':visible')) {
          loadNotifications();
        }
      }

      $('#notificationBell').on('click', toggleNotifications);

      // Load Notifications
      function loadNotifications() {
        $.getJSON('get_notifications.php')
          .done(function(data) {
            $('#notificationCount').text(data.unread_count);
            var $list = $('#notificationsList');
            $list.empty();
            $.each(data.notifications, function(index, notif) {
              var $item = $('<div class="notification-item"></div>');
              if (!notif.is_read) {
                $item.addClass('unread');
              }
              $item.append('<div class="notification-message">' + notif.message + '</div>');
              $item.append('<div class="notification-date">' + new Date(notif.created_at).toLocaleDateString() + '</div>');
              $list.append($item);
            });
          })
          .fail(function() {
            console.error('Failed to load notifications');
          });
      }

      // Load notifications every 30 seconds
      setInterval(loadNotifications, 30000);
      loadNotifications(); // Initial load
    });
  </script>
</body>
</html>

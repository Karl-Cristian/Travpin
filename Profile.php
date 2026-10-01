<?php
session_start();
if (!isset($_SESSION["user_id"])) {
    header("Location: Login.php");
    exit();
}

include "db.php";

$user_id = $_SESSION["user_id"];

// Handle profile updates
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['update_profile'])) {
        $username = trim($_POST['username']);
        $bio = trim($_POST['bio']);
        $hometown = trim($_POST['hometown']);
        $education = trim($_POST['education']);
        
        $sql = "UPDATE users SET username = ?, bio = ?, hometown = ?, education = ? WHERE id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->execute([$username, $bio, $hometown, $education, $user_id]);
        
        $_SESSION['username'] = $username;
        $success = "Profile updated successfully!";
    }
    
    // Handle profile picture upload
    if (isset($_FILES['profile_pic']) && $_FILES['profile_pic']['error'] === 0) {
        $allowed_types = ['image/jpeg', 'image/png', 'image/gif'];
        $file_type = $_FILES['profile_pic']['type'];
        
        if (in_array($file_type, $allowed_types)) {
            $upload_dir = 'uploads/profiles/';
            if (!is_dir($upload_dir)) {
                mkdir($upload_dir, 0777, true);
            }
            
            $file_extension = pathinfo($_FILES['profile_pic']['name'], PATHINFO_EXTENSION);
            $filename = 'profile_' . $user_id . '_' . time() . '.' . $file_extension;
            $file_path = $upload_dir . $filename;
            
            if (move_uploaded_file($_FILES['profile_pic']['tmp_name'], $file_path)) {
                $sql = "UPDATE users SET profile_pic = ? WHERE id = ?";
                $stmt = $conn->prepare($sql);
                $stmt->execute([$file_path, $user_id]);
                $success = "Profile picture updated successfully!";
            }
        }
    }
    
    // Handle banner image upload
    if (isset($_FILES['banner_image']) && $_FILES['banner_image']['error'] === 0) {
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
                $success = "Banner image updated successfully!";
            }
        }
    }
}
// Handle post updates (add this with the other POST handlers)
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
            // Refresh the page to show updated posts
            header("Location: Profile.php");
            exit();
        }
    }
}

// Get user data
$stmt = $conn->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

// Get user's posts
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
$stmt->execute([$user_id]);
$user_posts = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get user stats
$stmt = $conn->prepare("
    SELECT 
        (SELECT COUNT(*) FROM posts WHERE user_id = ?) as post_count,
        (SELECT COUNT(*) FROM likes WHERE user_id = ?) as like_count,
        (SELECT COUNT(*) FROM comments WHERE user_id = ?) as comment_count
");
$stmt->execute([$user_id, $user_id, $user_id]);
$user_stats = $stmt->fetch(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>TravPin - Profile</title>
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

    .banner-upload {
      position: absolute;
      top: 1rem;
      right: 1rem;
      background: rgba(255, 255, 255, 0.9);
      padding: 0.5rem 1rem;
      border-radius: 20px;
      cursor: pointer;
      font-size: 0.9rem;
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

    .edit-profile-btn {
      background: var(--primary);
      color: white;
      border: none;
      padding: 10px 20px;
      border-radius: 10px;
      cursor: pointer;
      font-weight: 600;
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
    }

    .modal-content {
      background: white;
      padding: 2rem;
      border-radius: 20px;
      width: 90%;
      max-width: 500px;
      max-height: 90vh;
      overflow-y: auto;
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

    .success-message {
      background: var(--secondary);
      color: white;
      padding: 1rem;
      border-radius: 10px;
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
    .image-preview {
      width: 100%;
      max-height: 200px;
      object-fit: cover;
      border-radius: 10px;
      margin-top: 1rem;
      display: none;
      animation: fadeIn 0.5s ease;
    }

    @keyframes fadeIn {
      from { opacity: 0; }
      to { opacity: 1; }
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
      <li><a href="Profile.php" class="active"><i class="fas fa-user"></i> Profile</a></li>
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

  <div class="profile-container">
    <?php if(isset($success)): ?>
      <div class="success-message"><?php echo $success; ?></div>
    <?php endif; ?>

    <div class="banner">
      <?php if($user['banner_image']): ?>
        <img src="<?php echo $user['banner_image']; ?>" alt="Banner" class="banner-image">
      <?php endif; ?>
      <label class="banner-upload">
        <i class="fas fa-camera"></i> Change Banner
        <input type="file" id="bannerInput" style="display: none;" onchange="uploadBanner()">
      </label>
    </div>

    <div class="profile-header">
      <div class="profile-info">
        <div>
          <img src="<?php echo $user['profile_pic'] ?: 'assets/default-avatar.png'; ?>" 
               alt="Profile" class="profile-avatar">
          <br><br>
          <label style="cursor: pointer; color: var(--primary);">
            <i class="fas fa-camera"></i> Change Photo
            <input type="file" id="avatarInput" style="display: none;" onchange="uploadAvatar()">
          </label>
        </div>
        
        <div class="profile-details">
          <h1 style="font-size: 2rem; margin-bottom: 0.5rem;"><?php echo htmlspecialchars($user['username']); ?></h1>
          <p style="color: var(--gray); margin-bottom: 1rem;"><?php echo htmlspecialchars($user['bio'] ?: 'No bio yet'); ?></p>
          
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
            <div><strong>Hometown:</strong> <?php echo htmlspecialchars($user['hometown'] ?: 'Not set'); ?></div>
            <div><strong>Education:</strong> <?php echo htmlspecialchars($user['education'] ?: 'Not set'); ?></div>
          </div>
          
          <button class="edit-profile-btn" onclick="openEditModal()" style="margin-top: 1rem;">
            <i class="fas fa-edit"></i> Edit Profile
          </button>
        </div>
      </div>
    </div>

    <h2 style="color: white; margin: 2rem 0 1rem 0;">My Posts</h2>
<div class="posts-grid">
  <?php foreach($user_posts as $post): ?>
  <div class="post-card" onclick="viewPost(<?php echo $post['id']; ?>)" style="cursor: pointer; position: relative;">
    <!-- Post Actions Overlay -->
    <div class="post-actions-overlay" style="position: absolute; top: 10px; right: 10px; display: flex; gap: 0.5rem; opacity: 0; transition: opacity 0.3s;">
      <button onclick="event.stopPropagation(); openEditModal(<?php echo $post['id']; ?>, '<?php echo addslashes($post['location']); ?>', '<?php echo addslashes($post['caption']); ?>', '<?php echo $post['image_url']; ?>')"
              style="background: white; border: none; border-radius: 50%; width: 40px; height: 40px; display: flex; align-items: center; justify-content: center; cursor: pointer; box-shadow: 0 2px 10px rgba(0,0,0,0.2);">
        <i class="fas fa-edit" style="color: var(--primary);"></i>
      </button>
      <form method="POST" style="display: inline;" onsubmit="return confirm('Are you sure you want to delete this post?')">
        <input type="hidden" name="post_id" value="<?php echo $post['id']; ?>">
        <button type="submit" name="delete_post" 
                onclick="event.stopPropagation()"
                style="background: white; border: none; border-radius: 50%; width: 40px; height: 40px; display: flex; align-items: center; justify-content: center; cursor: pointer; box-shadow: 0 2px 10px rgba(0,0,0,0.2);">
          <i class="fas fa-trash" style="color: var(--primary);"></i>
        </button>
      </form>
    </div>
    
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
</div>

<style>
.post-card:hover .post-actions-overlay {
  opacity: 1;
}
</style>
  <!-- Edit Profile Modal -->
  <div id="editModal" class="modal">
    <div class="modal-content">
      <h2 style="margin-bottom: 1rem;">Edit Profile</h2>
      <form method="POST" enctype="multipart/form-data">
        <div class="form-group">
          <label>Username</label>
          <input type="text" name="username" class="form-control" value="<?php echo htmlspecialchars($user['username']); ?>" required>
        </div>
        
        <div class="form-group">
          <label>Bio</label>
          <textarea name="bio" class="form-control" rows="3" placeholder="Tell us about yourself"><?php echo htmlspecialchars($user['bio']); ?></textarea>
        </div>
        
        <div class="form-group">
          <label>Hometown</label>
          <input type="text" name="hometown" class="form-control" value="<?php echo htmlspecialchars($user['hometown']); ?>" placeholder="Where are you from?">
        </div>
        
        <div class="form-group">
          <label>Education</label>
          <input type="text" name="education" class="form-control" value="<?php echo htmlspecialchars($user['education']); ?>" placeholder="Your education background">
        </div>
        
        <div style="display: flex; gap: 1rem; margin-top: 1rem;">
          <button type="button" onclick="closeEditModal()" style="flex: 1; padding: 10px; border: 2px solid var(--gray); background: white; border-radius: 10px; cursor: pointer;">
            Cancel
          </button>
          <button type="submit" name="update_profile" style="flex: 1; padding: 10px; background: var(--primary); color: white; border: none; border-radius: 10px; cursor: pointer;">
            Save Changes
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
        <small style="color: var(--gray);">Leave empty to keep current image</small>
      </div>
      
      <div class="form-group">
        <label>Caption</label>
        <textarea name="caption" id="edit_caption" class="form-control" rows="3" placeholder="Share your experience..." required></textarea>
      </div>
      
      <div style="display: flex; gap: 1rem; margin-top: 1rem;">
        <button type="button" onclick="closeEditPostModal()" style="flex: 1; padding: 10px; border: 2px solid var(--gray); background: white; border-radius: 10px; cursor: pointer;">
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
    function viewPost(postId) {
  window.location.href = `view_post.php?post_id=${postId}`;
}

// Add the same edit modal and functions from Explore.php to Profile.php
// (Copy the edit modal HTML and JavaScript functions from above)
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

    function openEditModal() {
      document.getElementById('editModal').style.display = 'flex';
    }

    function closeEditModal() {
      document.getElementById('editModal').style.display = 'none';
    }

    function uploadAvatar() {
      const fileInput = document.getElementById('avatarInput');
      const formData = new FormData();
      formData.append('profile_pic', fileInput.files[0]);
      
      fetch('update_profile_pic.php', {
        method: 'POST',
        body: formData
      }).then(() => {
        location.reload();
      });
    }

    function uploadBanner() {
      const fileInput = document.getElementById('bannerInput');
      const formData = new FormData();
      formData.append('banner_image', fileInput.files[0]);
      
      fetch('update_banner.php', {
        method: 'POST',
        body: formData
      }).then(() => {
        location.reload();
      });
    }

    // Close modal when clicking outside
    window.onclick = function(event) {
      const modal = document.getElementById('editModal');
      if (event.target === modal) {
        closeEditModal();
      }
    }

    // Load notifications every 30 seconds
    setInterval(loadNotifications, 30000);
    loadNotifications();
  </script>
</body>
<script>
function viewPost(postId) {
  window.location.href = `view_post.php?post_id=${postId}`;
}

function openEditModal(postId, location, caption, imageUrl) {
  document.getElementById('edit_post_id').value = postId;
  document.getElementById('edit_location').value = location;
  document.getElementById('edit_caption').value = caption;
  document.getElementById('editImagePreview').src = imageUrl;
  document.getElementById('editImagePreview').style.display = 'block';
  document.getElementById('editPostModal').style.display = 'flex';
}

function closeEditPostModal() {
  document.getElementById('editPostModal').style.display = 'none';
  document.getElementById('editImagePreview').style.display = 'none';
  document.getElementById('editPostForm').reset();
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

// Close modal when clicking outside
window.onclick = function(event) {
  const modal = document.getElementById('editPostModal');
  if (event.target === modal) {
    closeEditPostModal();
  }
}
</script>
</html>
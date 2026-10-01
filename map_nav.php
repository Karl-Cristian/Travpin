<?php
session_start();
if (!isset($_SESSION["user_id"])) {
    header("Location: Login.php");
    exit();
}

include "db.php";

$user_id = $_SESSION["user_id"];

// Handle location pinning
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['pin_location'])) {
    $location_name = trim($_POST['location_name']);
    $latitude = $_POST['latitude'];
    $longitude = $_POST['longitude'];
    $description = trim($_POST['description']);
    
    $sql = "INSERT INTO pinned_locations (user_id, location_name, latitude, longitude, description) VALUES (?, ?, ?, ?, ?)";
    $stmt = $conn->prepare($sql);
    $stmt->execute([$user_id, $location_name, $latitude, $longitude, $description]);
    
    $success = "Location pinned successfully!";
}

// Get user's pinned locations
$stmt = $conn->prepare("SELECT * FROM pinned_locations WHERE user_id = ? ORDER BY created_at DESC");
$stmt->execute([$user_id]);
$pinned_locations = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get user info
$stmt = $conn->prepare("SELECT username, profile_pic FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>TravPin - Map</title>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  <link rel="stylesheet" href="https://unpkg.com/leaflet@1.7.1/dist/leaflet.css" />
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

    .map-container {
      display: flex;
      height: calc(100vh - 80px);
    }

    .map-sidebar {
      width: 350px;
      background: rgba(255, 255, 255, 0.95);
      padding: 2rem;
      overflow-y: auto;
      box-shadow: 5px 0 15px rgba(0, 0, 0, 0.1);
    }

    .map-content {
      flex: 1;
      position: relative;
    }

    #map {
      height: 100%;
      width: 100%;
    }

    .pin-form {
      background: white;
      border-radius: 15px;
      padding: 1.5rem;
      margin-bottom: 2rem;
      box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
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

    .btn-primary {
      background: var(--primary);
      color: white;
      border: none;
      padding: 10px 20px;
      border-radius: 10px;
      cursor: pointer;
      font-weight: 600;
      width: 100%;
    }

    .location-card {
      background: white;
      border-radius: 15px;
      padding: 1.5rem;
      margin-bottom: 1rem;
      box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
      transition: transform 0.3s;
    }

    .location-card:hover {
      transform: translateY(-5px);
    }

    .location-name {
      font-weight: bold;
      color: var(--dark);
      margin-bottom: 0.5rem;
    }

    .location-coords {
      color: var(--gray);
      font-size: 0.9rem;
      margin-bottom: 0.5rem;
    }

    .location-description {
      color: var(--dark);
    }

    .success-message {
      background: var(--secondary);
      color: white;
      padding: 1rem;
      border-radius: 10px;
      margin-bottom: 1rem;
    }

    @media (max-width: 768px) {
      .map-container {
        flex-direction: column;
        height: auto;
      }
      
      .map-sidebar {
        width: 100%;
        height: 300px;
      }
      
      .map-content {
        height: 400px;
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
      <li><a href="map_nav.php" class="active"><i class="fas fa-map"></i> Map</a></li>
      <li><a href="Explore.php"><i class="fas fa-compass"></i> Explore</a></li>
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

  <div class="map-container">
    <div class="map-sidebar">
      <?php if(isset($success)): ?>
        <div class="success-message"><?php echo $success; ?></div>
      <?php endif; ?>

      <div class="pin-form">
        <h3 style="margin-bottom: 1rem;">Pin a Location</h3>
        <form method="POST" id="pinForm">
          <div class="form-group">
            <label>Location Name</label>
            <input type="text" name="location_name" class="form-control" placeholder="Enter location name" required>
          </div>
          
          <div class="form-group">
            <label>Description</label>
            <textarea name="description" class="form-control" rows="3" placeholder="Add a description"></textarea>
          </div>
          
          <div class="form-group">
            <label>Coordinates</label>
            <div style="display: flex; gap: 0.5rem;">
              <input type="text" id="latitude" name="latitude" class="form-control" placeholder="Latitude" readonly required>
              <input type="text" id="longitude" name="longitude" class="form-control" placeholder="Longitude" readonly required>
            </div>
            <small style="color: var(--gray);">Click on the map to set coordinates</small>
          </div>
          
          <button type="submit" name="pin_location" class="btn-primary">Pin Location</button>
        </form>
      </div>

      <h3 style="margin-bottom: 1rem;">My Pinned Locations</h3>
      <div id="locationsList">
        <?php foreach($pinned_locations as $location): ?>
        <div class="location-card">
          <div class="location-name"><?php echo htmlspecialchars($location['location_name']); ?></div>
          <div class="location-coords">
            <?php echo htmlspecialchars($location['latitude']); ?>, <?php echo htmlspecialchars($location['longitude']); ?>
          </div>
          <div class="location-description"><?php echo htmlspecialchars($location['description']); ?></div>
        </div>
        <?php endforeach; ?>
        
        <?php if(empty($pinned_locations)): ?>
          <div style="text-align: center; color: var(--gray); padding: 2rem;">
            <i class="fas fa-map-pin" style="font-size: 3rem; margin-bottom: 1rem; opacity: 0.5;"></i>
            <p>No locations pinned yet</p>
            <p>Click on the map to pin your first location!</p>
          </div>
        <?php endif; ?>
      </div>
    </div>
    
    <div class="map-content">
      <div id="map"></div>
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

  <script src="https://unpkg.com/leaflet@1.7.1/dist/leaflet.js"></script>
  <script>
    // Initialize map
  // In the map_nav.php script section, replace the map initialization:
  const map = L.map('map', {
    worldCopyJump: false
  }).setView([20, 0], 2);

  L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
    attribution: '© OpenStreetMap contributors',
    noWrap: true  // This prevents the map from wrapping
  }).addTo(map);
    
    let marker;
    
    // Add click event to map
    map.on('click', function(e) {
      const { lat, lng } = e.latlng;
      
      // Update form fields
      document.getElementById('latitude').value = lat.toFixed(6);
      document.getElementById('longitude').value = lng.toFixed(6);
      
      // Remove existing marker
      if (marker) {
        map.removeLayer(marker);
      }
      
      // Add new marker
      marker = L.marker([lat, lng]).addTo(map)
        .bindPopup('Selected Location')
        .openPopup();
    });
    
    // Add search functionality
    const searchBox = L.control({position: 'topright'});
    searchBox.onAdd = function(map) {
        const div = L.DomUtil.create('div', 'search-box');
        div.innerHTML = `
            <input type="text" id="searchInput" placeholder="Search location..." 
                   style="padding: 10px; width: 250px; border: none; border-radius: 5px; box-shadow: 0 2px 10px rgba(0,0,0,0.2);">
            <button onclick="searchLocation()" 
                    style="margin-left: 5px; padding: 10px; background: var(--primary); color: white; border: none; border-radius: 5px; cursor: pointer;">
                <i class="fas fa-search"></i>
            </button>
        `;
        return div;
    };
    searchBox.addTo(map);

    // Search function
    function searchLocation() {
        const query = document.getElementById('searchInput').value;
        if (!query) return;

        // Use OpenStreetMap Nominatim API for geocoding
        fetch(`https://nominatim.openstreetmap.org/search?format=json&q=${encodeURIComponent(query)}`)
            .then(response => response.json())
            .then(data => {
                if (data && data.length > 0) {
                    const result = data[0];
                    const lat = parseFloat(result.lat);
                    const lon = parseFloat(result.lon);
                    
                    // Update map view
                    map.setView([lat, lon], 13);
                    
                    // Remove existing marker
                    if (marker) {
                        map.removeLayer(marker);
                    }
                    
                    // Add new marker
                    marker = L.marker([lat, lon]).addTo(map)
                        .bindPopup(`Searched: ${result.display_name}`)
                        .openPopup();
                    
                    // Update form fields
                    document.getElementById('latitude').value = lat.toFixed(6);
                    document.getElementById('longitude').value = lon.toFixed(6);
                    
                    // Auto-fill location name
                    document.querySelector('input[name="location_name"]').value = result.display_name.split(',')[0];
                } else {
                    alert('Location not found!');
                }
            })
            .catch(error => {
                console.error('Error searching location:', error);
                alert('Error searching location. Please try again.');
            });
    }

    // Allow pressing Enter to search
    document.getElementById('searchInput').addEventListener('keypress', function(e) {
        if (e.key === 'Enter') {
            searchLocation();
        }
    });
    
    // Add existing pinned locations to map
    <?php foreach($pinned_locations as $location): ?>
      L.marker([<?php echo $location['latitude']; ?>, <?php echo $location['longitude']; ?>])
        .addTo(map)
        .bindPopup('<?php echo addslashes($location['location_name']); ?>');
    <?php endforeach; ?>

    // Load notifications every 30 seconds
    setInterval(loadNotifications, 30000);
    loadNotifications();
  </script>
</body>
</html>
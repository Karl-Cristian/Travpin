<?php
session_start();
include "db.php";

// Handle auto-login with remember token
if (!isset($_SESSION["user_id"]) && isset($_COOKIE['remember_token'])) {
    $token = $_COOKIE['remember_token'];
    
    $stmt = $conn->prepare("SELECT id, username FROM users WHERE remember_token = ?");
    $stmt->execute([$token]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($user) {
        $_SESSION["user_id"] = $user["id"];
        $_SESSION["username"] = $user["username"];
        header("Location: Home.php");
        exit();
    }
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $login = trim($_POST["login"]); 
    $password = $_POST["password"];
    $remember_me = isset($_POST["remember_me"]);

    $sql = "SELECT * FROM users WHERE username = ? OR email = ?";
    $stmt = $conn->prepare($sql);
    $stmt->execute([$login, $login]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($user && password_verify($password, $user["password"])) {
        $_SESSION["user_id"] = $user["id"];
        $_SESSION["username"] = $user["username"];
        
        // Handle remember me
        if ($remember_me) {
            $token = bin2hex(random_bytes(32));
            $expiry = time() + (30 * 24 * 60 * 60); // 30 days
            
            $stmt = $conn->prepare("UPDATE users SET remember_token = ? WHERE id = ?");
            $stmt->execute([$token, $user["id"]]);
            
            setcookie('remember_token', $token, $expiry, "/", "", false, true);
        }
        
        // Create welcome notification
        $stmt = $conn->prepare("INSERT INTO notifications (user_id, type, message) VALUES (?, 'system', ?)");
        $stmt->execute([$user["id"], "Welcome back to TravPin! Start exploring amazing travel destinations."]);
        
        header("Location: Home.php");
        exit();
    } else {
        $error = "Invalid login credentials";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>TravPin - Login</title>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
  <style>
    * {
      margin: 0;
      padding: 0;
      box-sizing: border-box;
      font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
    }

    body {
      background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
      min-height: 100vh;
      display: flex;
      align-items: center;
      justify-content: center;
    }

    .login-container {
      background: rgba(255, 255, 255, 0.95);
      padding: 3rem;
      border-radius: 20px;
      box-shadow: 0 20px 40px rgba(0, 0, 0, 0.1);
      width: 100%;
      max-width: 400px;
      backdrop-filter: blur(10px);
      animation: fadeIn 0.8s ease-out;
    }

    @keyframes fadeIn {
      from { opacity: 0; transform: translateY(20px); }
      to { opacity: 1; transform: translateY(0); }
    }

    .logo {
      text-align: center;
      margin-bottom: 2rem;
      color: #667eea;
      font-size: 2rem;
      font-weight: bold;
    }

    .logo i {
      margin-right: 0.5rem;
    }

    .form-group {
      margin-bottom: 1.5rem;
    }

    .form-group label {
      display: block;
      margin-bottom: 0.5rem;
      color: #2C3E50;
      font-weight: 600;
    }

    .form-control {
      width: 100%;
      padding: 12px 16px;
      border: 2px solid #e1e8ed;
      border-radius: 10px;
      font-size: 1rem;
      transition: border-color 0.3s;
    }

    .form-control:focus {
      outline: none;
      border-color: #667eea;
    }

    .remember-forgot {
      display: flex;
      justify-content: space-between;
      align-items: center;
      margin-bottom: 1.5rem;
    }

    .remember-me {
      display: flex;
      align-items: center;
      gap: 0.5rem;
    }

    .remember-me input {
      width: auto;
    }

    .forgot-password {
      color: #667eea;
      text-decoration: none;
      font-size: 0.9rem;
    }

    .forgot-password:hover {
      text-decoration: underline;
    }

    .btn-primary {
      width: 100%;
      padding: 12px;
      background: linear-gradient(135deg, #667eea, #764ba2);
      color: white;
      border: none;
      border-radius: 10px;
      font-size: 1rem;
      font-weight: 600;
      cursor: pointer;
      transition: transform 0.2s;
    }

    .btn-primary:hover {
      transform: translateY(-2px);
    }

    .links {
      text-align: center;
      margin-top: 1.5rem;
    }

    .links a {
      color: #667eea;
      text-decoration: none;
    }
  </style>
</head>
<body>
  <div class="login-container">
    <div class="logo">
      <i class="fas fa-map-marked-alt"></i>
      TravPin
    </div>

    <form method="POST" id="loginForm">
      <div class="form-group">
        <label>Email or Username</label>
        <input type="text" name="login" class="form-control" placeholder="Enter your email or username" required>
      </div>

      <div class="form-group">
        <label>Password</label>
        <input type="password" name="password" class="form-control" placeholder="Enter your password" required>
      </div>

      <div class="remember-forgot">
        <label class="remember-me">
          <input type="checkbox" name="remember_me">
          Remember me
        </label>
        <a href="forgot_password.php" class="forgot-password">Forgot Password?</a>
      </div>

      <button type="submit" class="btn-primary">Login</button>
    </form>

    <div class="links">
      <p>Don't have an account? <a href="Register.php">Register now</a></p>
    </div>
  </div>

  <script src="jquery-3.7.1.min.js"></script>
  <script>
    $(document).ready(function() {
      $('#loginForm').on('submit', function(e) {
        e.preventDefault();
        
        var formData = new FormData(this);
        
        $.ajax({
          url: 'Login.php',
          type: 'POST',
          data: formData,
          processData: false,
          contentType: false,
          success: function(data) {
            if (data.includes('Invalid login credentials')) {
              Swal.fire({
                title: 'Login Failed',
                text: 'Invalid email/username or password.',
                icon: 'error',
                confirmButtonColor: '#667eea'
              });
            } else {
              Swal.fire({
                title: 'Welcome Back!',
                text: 'You have successfully logged in.',
                icon: 'success',
                confirmButtonColor: '#667eea',
                timer: 1500,
                showConfirmButton: false
              }).then(function() {
                window.location.href = 'Home.php';
              });
            }
          },
          error: function() {
            Swal.fire({
              title: 'Error',
              text: 'An error occurred during login.',
              icon: 'error',
              confirmButtonColor: '#667eea'
            });
          }
        });
      });
    });
  </script>
</body>
</html>
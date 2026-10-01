<?php
session_start();
include "db.php";

if($_SERVER['REQUEST_METHOD'] === "POST"){
    $username = trim($_POST["username"]);
    $email = trim($_POST["email"]);
    $password = $_POST["password"];
    $pass_rep = $_POST["pass_rep"];

    // Validation
    if ($password !== $pass_rep) {
        $error = "Passwords do not match!";
    } elseif (strlen($password) < 6) {
        $error = "Password must be at least 6 characters long!";
    } else {
        // Check if username or email already exists
        $stmt = $conn->prepare("SELECT id FROM users WHERE username = ? OR email = ?");
        $stmt->execute([$username, $email]);
        if ($stmt->fetch()) {
            $error = "Username or email already exists!";
        } else {
            $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
            
            $sql = "INSERT INTO users (username, email, password) VALUES (?, ?, ?)";
            $stmt = $conn->prepare($sql);
            
            if($stmt->execute([$username, $email, $hashedPassword])){
                $_SESSION["user_id"] = $conn->lastInsertId();
                $_SESSION["username"] = $username;
                
                // Create welcome notification
                $stmt = $conn->prepare("INSERT INTO notifications (user_id, type, message) VALUES (?, 'system', ?)");
                $stmt->execute([$_SESSION["user_id"], "Welcome to TravPin! Start sharing your travel experiences."]);
                
                header("Location: Home.php");
                exit();
            } else {
                $error = "Registration failed. Please try again.";
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>TravPin - Register</title>
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
      background: linear-gradient(135deg, #4ECDC4 0%, #44A08D 100%);
      min-height: 100vh;
      display: flex;
      align-items: center;
      justify-content: center;
    }

    .register-container {
      background: rgba(255, 255, 255, 0.95);
      padding: 3rem;
      border-radius: 20px;
      box-shadow: 0 20px 40px rgba(0, 0, 0, 0.1);
      width: 100%;
      max-width: 450px;
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
      color: #4ECDC4;
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
      border-color: #4ECDC4;
    }

    .btn-primary {
      width: 100%;
      padding: 12px;
      background: linear-gradient(135deg, #4ECDC4, #44A08D);
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
      color: #4ECDC4;
      text-decoration: none;
    }
  </style>
</head>
<body>
  <div class="register-container">
    <div class="logo">
      <i class="fas fa-map-marked-alt"></i>
      TravPin
    </div>

    <form method="POST" id="registerForm">
      <div class="form-group">
        <label>Username</label>
        <input type="text" name="username" class="form-control" placeholder="Choose a username" required>
      </div>

      <div class="form-group">
        <label>Email</label>
        <input type="email" name="email" class="form-control" placeholder="Enter your email" required>
      </div>

      <div class="form-group">
        <label>Password</label>
        <input type="password" name="password" class="form-control" placeholder="Create a password" required minlength="6">
      </div>

      <div class="form-group">
        <label>Repeat Password</label>
        <input type="password" name="pass_rep" class="form-control" placeholder="Repeat your password" required>
      </div>

      <button type="submit" class="btn-primary">Create Account</button>
    </form>

    <div class="links">
      <p>Already have an account? <a href="Login.php">Login here</a></p>
    </div>
  </div>

  <script src="jquery-3.7.1.min.js"></script>
  <script>
    $(document).ready(function() {
      $('#registerForm').on('submit', function(e) {
        e.preventDefault();
        
        var password = $('input[name="password"]').val();
        var passRep = $('input[name="pass_rep"]').val();
        
        if (password !== passRep) {
          Swal.fire({
            title: 'Password Mismatch',
            text: 'Passwords do not match!',
            icon: 'error',
            confirmButtonColor: '#4ECDC4'
          });
          return;
        }
        
        var formData = new FormData(this);
        
        $.ajax({
          url: 'Register.php',
          type: 'POST',
          data: formData,
          processData: false,
          contentType: false,
          success: function(data) {
            // Check if there's an error in the response (assuming error messages would be echoed in HTML if present)
            if (data.includes('Username or email already exists')) {
              Swal.fire({
                title: 'Registration Failed',
                text: 'Username or email already exists!',
                icon: 'error',
                confirmButtonColor: '#4ECDC4'
              });
            } else if (data.includes('Registration failed')) {
              Swal.fire({
                title: 'Registration Failed',
                text: 'Registration failed. Please try again.',
                icon: 'error',
                confirmButtonColor: '#4ECDC4'
              });
            } else {
              // Assume success (e.g., empty response or redirect content)
              Swal.fire({
                title: 'Welcome to TravPin!',
                text: 'Your account has been created successfully.',
                icon: 'success',
                confirmButtonColor: '#4ECDC4',
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
              text: 'An error occurred during registration.',
              icon: 'error',
              confirmButtonColor: '#4ECDC4'
            });
          }
        });
      });
    });
  </script>
</body>
</html>
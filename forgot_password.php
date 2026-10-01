<?php
session_start();
include "db.php";

$message = '';
$error = '';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $email = trim($_POST["email"]);
    
    // Find the user by email
    $stmt = $conn->prepare("SELECT id, username FROM users WHERE email = ?");
    $stmt->execute([$email]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($user) {
        // Generate reset token & expiration (1 hour)
        $token = bin2hex(random_bytes(32));
        $expires = date('Y-m-d H:i:s', time() + (60 * 60));

        // Save token in database
        $stmt = $conn->prepare("UPDATE users SET reset_token = ?, reset_token_expires = ? WHERE id = ?");
        $stmt->execute([$token, $expires, $user['id']]);

        if ($stmt->rowCount() === 0) {
            $error = "Failed to store reset token. Please try again.";
        } else {
            // Build dynamic reset link (no hardcoded folder)
            $reset_link = "http://" . $_SERVER['HTTP_HOST'] . dirname($_SERVER['PHP_SELF']) . "/reset_password.php?token=" . $token;

            $message = "
            Password reset link has been generated. <br><br>
            <a href='$reset_link' style='color: #667eea;'>$reset_link</a><br><br>
            <small><em>Note: In a real system, this link would be emailed to you.</em></small>";
        }
    } else {
        $error = "No account found with that email address.";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>TravPin - Forgot Password</title>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  <style>
    * { margin: 0; padding: 0; box-sizing: border-box; font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; }
    body { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); min-height: 100vh; display: flex; align-items: center; justify-content: center; }
    .forgot-container { background: rgba(255, 255, 255, 0.95); padding: 3rem; border-radius: 20px; box-shadow: 0 20px 40px rgba(0, 0, 0, 0.1); width: 100%; max-width: 450px; backdrop-filter: blur(10px); }
    .logo { text-align: center; margin-bottom: 2rem; color: #667eea; font-size: 2rem; font-weight: bold; }
    .form-group { margin-bottom: 1.5rem; }
    .form-group label { display: block; margin-bottom: 0.5rem; color: #2C3E50; font-weight: 600; }
    .form-control { width: 100%; padding: 12px 16px; border: 2px solid #e1e8ed; border-radius: 10px; font-size: 1rem; }
    .btn-primary { width: 100%; padding: 12px; background: linear-gradient(135deg, #667eea, #764ba2); color: white; border: none; border-radius: 10px; font-size: 1rem; font-weight: 600; cursor: pointer; }
    .links { text-align: center; margin-top: 1.5rem; }
    .message { background: #d4edda; color: #155724; padding: 1rem; border-radius: 10px; margin-bottom: 1rem; }
    .error { background: #f8d7da; color: #721c24; padding: 1rem; border-radius: 10px; margin-bottom: 1rem; }
  </style>
</head>
<body>
  <div class="forgot-container">
    <div class="logo">
      <i class="fas fa-map-marked-alt"></i> TravPin
    </div>
    <h2 style="text-align:center; margin-bottom:1.5rem; color:#2C3E50;">Reset Your Password</h2>
    
    <?php if ($message): ?><div class="message"><?php echo $message; ?></div><?php endif; ?>
    <?php if ($error): ?><div class="error"><?php echo $error; ?></div><?php endif; ?>

    <form method="POST">
      <div class="form-group">
        <label>Email Address</label>
        <input type="email" name="email" class="form-control" placeholder="Enter your email address" required>
      </div>
      <button type="submit" class="btn-primary">Send Reset Link</button>
    </form>
    <div class="links">
      <p>Remember your password? <a href="Login.php">Back to Login</a></p>
    </div>
  </div>
</body>
</html>

<?php
session_start();
include "db.php";

$error = '';
$success = '';
$valid_token = false;

// Ensure timezone consistency with database
$conn->exec("SET time_zone = '+08:00'");

if (isset($_GET['token'])) {
    $token = $_GET['token'];
    
    // Verify token validity
    $stmt = $conn->prepare("SELECT id FROM users WHERE reset_token = ? AND reset_token_expires > NOW()");
    $stmt->execute([$token]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($user) {
        $valid_token = true;
        $user_id = $user['id'];
        
        if ($_SERVER["REQUEST_METHOD"] == "POST") {
            $password = $_POST["password"];
            $confirm_password = $_POST["confirm_password"];
            
            if ($password !== $confirm_password) {
                $error = "Passwords do not match!";
            } elseif (strlen($password) < 6) {
                $error = "Password must be at least 6 characters long!";
            } else {
                $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
                $stmt = $conn->prepare("UPDATE users SET password = ?, reset_token = NULL, reset_token_expires = NULL WHERE id = ?");
                
                if ($stmt->execute([$hashedPassword, $user_id])) {
                    echo "
                    <script src='https://cdn.jsdelivr.net/npm/sweetalert2@11'></script>
                    <script>
                    Swal.fire({
                      title: 'Success!',
                      text: 'Your password has been reset successfully.',
                      icon: 'success',
                      confirmButtonColor: '#667eea'
                    }).then(() => {
                      window.location.href = 'Login.php';
                    });
                    </script>";
                    exit;
                } else {
                    $error = "Failed to reset password. Please try again.";
                }
            }
        }
    } else {
        $error = "Invalid or expired reset token.";
    }
} else {
    $error = "No reset token provided.";
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>TravPin - Reset Password</title>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  <style>
    * { margin: 0; padding: 0; box-sizing: border-box; font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; }
    body { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); min-height: 100vh; display: flex; align-items: center; justify-content: center; }
    .reset-container { background: rgba(255, 255, 255, 0.95); padding: 3rem; border-radius: 20px; box-shadow: 0 20px 40px rgba(0,0,0,0.1); width: 100%; max-width: 450px; backdrop-filter: blur(10px); }
    .logo { text-align: center; margin-bottom: 2rem; color: #667eea; font-size: 2rem; font-weight: bold; }
    .form-group { margin-bottom: 1.5rem; }
    .form-group label { display: block; margin-bottom: 0.5rem; color: #2C3E50; font-weight: 600; }
    .form-control { width: 100%; padding: 12px 16px; border: 2px solid #e1e8ed; border-radius: 10px; font-size: 1rem; }
    .btn-primary { width: 100%; padding: 12px; background: linear-gradient(135deg, #667eea, #764ba2); color: white; border: none; border-radius: 10px; font-size: 1rem; font-weight: 600; cursor: pointer; }
    .links { text-align: center; margin-top: 1.5rem; }
    .message, .error { padding: 1rem; border-radius: 10px; margin-bottom: 1rem; }
    .message { background: #d4edda; color: #155724; }
    .error { background: #f8d7da; color: #721c24; }
  </style>
</head>
<body>
  <div class="reset-container">
    <div class="logo"><i class="fas fa-map-marked-alt"></i> TravPin</div>
    <h2 style="text-align:center; margin-bottom:1.5rem; color:#2C3E50;">Set New Password</h2>

    <?php if ($error && !$valid_token): ?>
      <div class="error"><?php echo $error; ?></div>
      <div class="links"><a href="forgot_password.php" class="btn-primary" style="display:block; text-align:center; text-decoration:none;">Request New Reset Link</a></div>
    <?php elseif ($valid_token): ?>
      <?php if ($error): ?><div class="error"><?php echo $error; ?></div><?php endif; ?>
      <form method="POST">
        <div class="form-group">
          <label>New Password</label>
          <input type="password" name="password" class="form-control" placeholder="Enter new password" required minlength="6">
        </div>
        <div class="form-group">
          <label>Confirm New Password</label>
          <input type="password" name="confirm_password" class="form-control" placeholder="Confirm new password" required>
        </div>
        <button type="submit" class="btn-primary">Reset Password</button>
      </form>
    <?php endif; ?>

    <div class="links"><p><a href="Login.php">Back to Login</a></p></div>
  </div>
</body>
</html>

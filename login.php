<?php
// login.php
session_start();
require_once 'db.php';
if (isset($_SESSION['user_id'])) { header('Location: index.php'); exit; }
 
$err = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    if (!$username || !$password) {
        $err = "Fill both fields.";
    } else {
        $stmt = $conn->prepare("SELECT id, password FROM users WHERE username = ?");
        $stmt->bind_param('s', $username);
        $stmt->execute();
        $stmt->bind_result($id, $hash);
        if ($stmt->fetch() && password_verify($password, $hash)) {
            $_SESSION['user_id'] = $id;
            header('Location: index.php'); exit;
        } else {
            $err = "Invalid credentials.";
        }
        $stmt->close();
    }
}
?>
<!doctype html>
<html>
<head>
<meta charset="utf-8">
<title>Login — ChatClone</title>
<meta name="viewport" content="width=device-width,initial-scale=1">
<style>
body{font-family:Arial,Helvetica,sans-serif;background:#f0f2f5;padding:20px}
.container{max-width:420px;margin:40px auto;background:#fff;padding:20px;border-radius:8px;box-shadow:0 4px 12px rgba(0,0,0,.08)}
h2{margin:0 0 12px}
label{display:block;margin-top:8px;font-size:13px}
input{width:100%;padding:10px;margin-top:6px;border:1px solid #ddd;border-radius:6px}
button{margin-top:12px;padding:10px 14px;border:none;background:#25D366;color:#fff;border-radius:8px;cursor:pointer}
.small{font-size:13px;color:#666;margin-top:8px}
.err{color:#b00020;margin-top:8px}
</style>
</head>
<body>
<div class="container">
  <h2>Login</h2>
  <?php if ($err): ?><div class="err"><?=htmlspecialchars($err)?></div><?php endif; ?>
  <form method="post">
    <label>Username</label>
    <input name="username" required value="<?=htmlspecialchars($_POST['username'] ?? '')?>">
    <label>Password</label>
    <input type="password" name="password" required>
    <button type="submit">Log in</button>
    <div class="small">Don't have an account? <a href="signup.php">Sign up</a></div>
  </form>
</div>
</body>
</html>
 

<?php
// signup.php
session_start();
require_once 'db.php';
 
$err = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    $phone = trim($_POST['phone'] ?? '');
 
    if (!$name || !$username || !$password) {
        $err = "Please fill required fields.";
    } else {
        // check username exists
        $stmt = $conn->prepare("SELECT id FROM users WHERE username = ?");
        $stmt->bind_param('s', $username);
        $stmt->execute();
        $stmt->store_result();
        if ($stmt->num_rows > 0) {
            $err = "Username already taken.";
            $stmt->close();
        } else {
            $stmt->close();
            // handle avatar upload
            $avatarPath = 'uploads/default.png';
            if (!empty($_FILES['avatar']['name'])) {
                $f = $_FILES['avatar'];
                $ext = strtolower(pathinfo($f['name'], PATHINFO_EXTENSION));
                $allowed = ['png','jpg','jpeg','gif','webp'];
                if (!in_array($ext, $allowed)) {
                    $err = "Invalid avatar file type.";
                } elseif ($f['error'] !== 0) {
                    $err = "Avatar upload error.";
                } else {
                    if (!is_dir('uploads')) {
                        if (!mkdir('uploads', 0755, true)) {
                            $err = "Could not create uploads folder.";
                        }
                    }
                    if (!$err) {
                        $newname = 'uploads/avatar_' . uniqid() . '.' . $ext;
                        if (!move_uploaded_file($f['tmp_name'], $newname)) {
                            $err = "Failed to upload avatar.";
                        } else {
                            $avatarPath = $newname;
                        }
                    }
                }
            }
 
            if (!$err) {
                $hash = password_hash($password, PASSWORD_DEFAULT);
                $ins = $conn->prepare("INSERT INTO users (username, password, name, phone, avatar) VALUES (?, ?, ?, ?, ?)");
                $ins->bind_param('sssss', $username, $hash, $name, $phone, $avatarPath);
                if ($ins->execute()) {
                    $_SESSION['user_id'] = $ins->insert_id;
                    header('Location: index.php');
                    exit;
                } else {
                    $err = "Signup failed: " . $conn->error;
                }
                $ins->close();
            }
        }
    }
}
?>
<!doctype html>
<html>
<head>
<meta charset="utf-8">
<title>Sign up — ChatClone</title>
<meta name="viewport" content="width=device-width,initial-scale=1">
<style>
body{font-family:Arial,Helvetica,sans-serif;background:#f0f2f5;padding:20px}
.container{max-width:420px;margin:40px auto;background:#fff;padding:20px;border-radius:8px;box-shadow:0 4px 12px rgba(0,0,0,.08)}
h2{margin:0 0 12px}
label{display:block;margin-top:8px;font-size:13px}
input[type=text],input[type=password],input[type=file]{width:100%;padding:10px;margin-top:6px;border:1px solid #ddd;border-radius:6px}
button{margin-top:12px;padding:10px 14px;border:none;background:#25D366;color:#fff;border-radius:8px;cursor:pointer}
.small{font-size:13px;color:#666;margin-top:8px}
.err{color:#b00020;margin-top:8px}
.preview{width:80px;height:80px;border-radius:50%;object-fit:cover;border:1px solid #eee;margin-top:8px}
</style>
</head>
<body>
<div class="container">
  <h2>Create account</h2>
  <?php if ($err): ?><div class="err"><?=htmlspecialchars($err)?></div><?php endif; ?>
  <form method="post" enctype="multipart/form-data" id="signupForm">
    <label>Name</label>
    <input name="name" required value="<?=htmlspecialchars($_POST['name'] ?? '')?>">
    <label>Username</label>
    <input name="username" required value="<?=htmlspecialchars($_POST['username'] ?? '')?>">
    <label>Password</label>
    <input type="password" name="password" required>
    <label>Phone (optional)</label>
    <input name="phone" value="<?=htmlspecialchars($_POST['phone'] ?? '')?>">
    <label>Avatar (optional)</label>
    <input type="file" name="avatar" accept="image/*" id="avatarInput">
    <img src="uploads/default.png" alt="preview" class="preview" id="avatarPreview">
    <button type="submit">Sign up</button>
    <div class="small">Already have an account? <a href="login.php">Log in</a></div>
  </form>
</div>
 
<script>
document.getElementById('avatarInput').addEventListener('change', function(e){
  const file = this.files[0];
  if (!file) return;
  const reader = new FileReader();
  reader.onload = function(){ document.getElementById('avatarPreview').src = reader.result; }
  reader.readAsDataURL(file);
});
</script>
</body>
</html>
 

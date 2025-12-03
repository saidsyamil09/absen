<?php
// Halaman login public
require_once __DIR__ . '/../db.php';

$err = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username']);
    $password = $_POST['password'];
    $stmt = $pdo->prepare("SELECT * FROM users WHERE username = ?");
    $stmt->execute([$username]);
    $user = $stmt->fetch();
    if ($user && password_verify($password, $user['password'])) {
        // simpan session
        $_SESSION['user'] = [
            'id' => $user['id'],
            'username' => $user['username'],
            'role' => $user['role']
        ];
        header('Location: ?page=dashboard');
        exit;
    } else {
        $err = 'Username atau password salah';
    }
}
?>
<!doctype html>
<html lang="id">
<head>
  <meta charset="utf-8">
  <title>Login - E-Absensi</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
  <style>body{background:#f7f8fa;height:100vh;display:flex;align-items:center;justify-content:center}</style>
</head>
<body>
  <div class="card p-4" style="width:420px;">
    <h4 class="mb-3">Login E-Absensi</h4>
    <?php if($err): ?><div class="alert alert-danger"><?= htmlspecialchars($err) ?></div><?php endif; ?>
    <form method="post">
      <div class="form-group">
        <label>Username</label>
        <input required name="username" class="form-control" />
      </div>
      <div class="form-group">
        <label>Password</label>
        <input required name="password" type="password" class="form-control" />
      </div>
      <button class="btn btn-primary">Login</button>
      <a href="?page=create_admin" class="btn btn-link">Buat akun admin/guru</a>
    </form>
  </div>
</body>
</html>
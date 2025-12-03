<?php
// Halaman public untuk membuat akun admin/guru (satu kali atau dipakai admin)
require_once __DIR__ . '/../db.php';
$err = $msg = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username']);
    $password = $_POST['password'];
    $role = $_POST['role'];
    if (!$username || !$password) {
        $err = 'Isi semua data';
    } else {
        $check = $pdo->prepare("SELECT id FROM users WHERE username = ?");
        $check->execute([$username]);
        if ($check->fetch()) {
            $err = 'Username sudah ada';
        } else {
            $hash = password_hash($password, PASSWORD_DEFAULT);
            $ins = $pdo->prepare("INSERT INTO users (username, password, role) VALUES (?,?,?)");
            $ins->execute([$username, $hash, $role]);
            $msg = 'Akun berhasil dibuat. Silakan login.';
        }
    }
}
?>
<!doctype html>
<html lang="id">
<head><meta charset="utf-8"><title>Buat Akun</title><link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css"></head>
<body style="background:#f7f8fa;display:flex;align-items:center;justify-content:center;height:100vh;">
  <div class="card p-4" style="width:520px;">
    <h4>Buat Akun Admin / Guru</h4>
    <?php if($err): ?><div class="alert alert-danger"><?= htmlspecialchars($err) ?></div><?php endif; ?>
    <?php if($msg): ?><div class="alert alert-success"><?= htmlspecialchars($msg) ?></div><?php endif; ?>
    <form method="post">
      <div class="form-row">
        <div class="form-group col-md-6">
          <label>Username</label>
          <input name="username" class="form-control" required />
        </div>
        <div class="form-group col-md-6">
          <label>Role</label>
          <select name="role" class="form-control">
            <option value="admin">Admin</option>
            <option value="guru">Guru</option>
          </select>
        </div>
      </div>
      <div class="form-group">
        <label>Password</label>
        <input name="password" type="password" class="form-control" required />
      </div>
      <button class="btn btn-success">Buat Akun</button>
      <a href="?page=login" class="btn btn-link">Kembali ke Login</a>
    </form>
  </div>
</body>
</html>
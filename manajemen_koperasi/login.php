<?php
error_reporting(0);
ini_set('display_errors', 0);

session_start();
include __DIR__ . '/conf/conf.php';
include __DIR__ . '/conf/conf_koperasi.php';

// Ambil data setting instansi
$q = mysqli_query($conn, "SELECT * FROM setting LIMIT 1");
$setting = mysqli_fetch_assoc($q);

$nama_instansi = $setting['nama_instansi'] ?? '';
$alamat        = $setting['alamat_instansi'] ?? '';
$kabupaten     = $setting['kabupaten'] ?? '';
$propinsi      = $setting['propinsi'] ?? '';
$kontak        = $setting['kontak'] ?? '';
$email         = $setting['email'] ?? '';
$wallpaper     = !empty($setting['wallpaper']) 
    ? 'data:image/jpeg;base64,' . base64_encode($setting['wallpaper']) 
    : null;
?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <title>Login Koperasi</title>
  <!-- AdminLTE CSS -->
  <link rel="stylesheet" href="vendor/adminlte/plugins/fontawesome-free/css/all.min.css">
  <link rel="stylesheet" href="vendor/adminlte/dist/css/adminlte.min.css">
</head>

<body class="hold-transition login-page" 
      style="background-image:url('vendor/adminlte/dist/img/7879549.jpg'); 
             background-size:cover; 
             background-position:center;">

<div class="login-box">
  <div class="login-logo" 
       style="font-size:1.2rem; 
              font-weight:bold; 
              color:#FFD700; 
              text-shadow:1px 1px 3px rgba(0,0,0,0.7);">
    <b><?= htmlspecialchars($nama_instansi) ?></b>
  </div>

  <div class="card" style="background:rgba(255,255,255,0.2); backdrop-filter:blur(6px);">
    <div class="card-body login-card-body" style="background:rgba(255,255,255,0.3); border-radius:8px;">
      <p class="login-box-msg"><b>Silakan login untuk masuk ke MANAJEMEN KOPERASI</b></p>

      <form action="cek_login.php" method="post">
        <!-- Role -->
        <div class="input-group mb-3">
          <select name="role" class="form-control" required>
            <option value="">-- Pilih Role --</option>
            <option value="admin">Administrator</option>
            <option value="user">Anggota Aktif</option>
            <option value="calon">Calon Anggota</option>
          </select>
          <div class="input-group-append">
            <div class="input-group-text"><i class="fas fa-key"></i></div>
          </div>
        </div>

        <!-- Username -->
        <div class="input-group mb-3">
          <input type="text" name="usere" class="form-control" placeholder="Username" required>
          <div class="input-group-append">
            <div class="input-group-text"><i class="fas fa-user"></i></div>
          </div>
        </div>

        <!-- Password -->
        <div class="input-group mb-3">
          <input type="password" name="passworde" class="form-control" placeholder="Password" required>
          <div class="input-group-append">
            <div class="input-group-text"><i class="fas fa-lock"></i></div>
          </div>
        </div>

        <!-- Captcha Tanggal Lahir -->
        <div class="input-group mb-3" id="captchaBox">
          <input type="text" name="tgl_lahir" id="tgl_lahir" class="form-control" placeholder="Tanggal Lahir (dd-mm-yyyy)">
          <div class="input-group-append">
            <div class="input-group-text"><i class="fas fa-calendar"></i></div>
          </div>
        </div>

        <div class="row">
          <div class="col-12">
            <button type="submit" class="btn btn-primary btn-block">Login</button>
          </div>
        </div>
      </form>

      <?php if(isset($_GET['error']) && $_GET['error']!='unauthorized'): ?>
        <div class="alert alert-danger mt-3">
          <?php if($_GET['error']==1): ?>
            ❌ Username atau password salah.
          <?php elseif($_GET['error']==2): ?>
            ❌ Verifikasi tanggal lahir tidak sesuai.
          <?php elseif($_GET['error']==3): ?>
            ⚠️ Anda sudah terdaftar sebagai anggota aktif. Silakan login dengan role <b>Anggota</b>.
          <?php elseif($_GET['error']==4): ?>
            ⚠️ Status Anda masih calon anggota. Silakan login dengan role <b>Calon Anggota</b>.
          <?php endif; ?>
        </div>
      <?php endif; ?>

      <!-- Info Instansi -->
      <div class="mt-3 text-center" style="color:red; font-weight:bold; text-shadow:1px 1px 3px rgba(0,0,0,0.7);">
        <small>
          <?= htmlspecialchars($alamat) ?> - <?= htmlspecialchars($kabupaten) ?> - <?= htmlspecialchars($propinsi) ?><br>
          <?= htmlspecialchars($kontak) ?> | <?= htmlspecialchars($email) ?>
        </small>
      </div>
    </div>
  </div>
</div>

<!-- AdminLTE JS -->
<script src="vendor/adminlte/plugins/jquery/jquery.min.js"></script>
<script src="vendor/adminlte/plugins/bootstrap/js/bootstrap.bundle.min.js"></script>
<script src="vendor/adminlte/dist/js/adminlte.min.js"></script>
<script>
  const roleSelect = document.querySelector('select[name="role"]');
  const captchaBox = document.getElementById('captchaBox');
  const tglInput   = document.getElementById('tgl_lahir');

  roleSelect.addEventListener('change', function() {
    if (this.value === 'admin') {
      captchaBox.style.display = 'none';
      tglInput.value = '';
    } else {
      captchaBox.style.display = 'flex';
    }
  });

  // autoformat tanggal lahir
  tglInput.addEventListener('input', function(e) {
    let val = e.target.value.replace(/\D/g, '');
    if (val.length >= 8) {
      let dd = val.substring(0,2);
      let mm = val.substring(2,4);
      let yyyy = val.substring(4,8);
      e.target.value = dd + '-' + mm + '-' + yyyy;
    }
  });
</script>
</body>
</html>

<?php
session_start();
session_unset();     // hapus semua variabel session
session_destroy();   // hancurkan session
?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <title>Logout Koperasi</title>
  <meta http-equiv="refresh" content="3;url=login.php"> 
  <!-- AdminLTE CSS -->
  <link rel="stylesheet" href="vendor/adminlte/plugins/fontawesome-free/css/all.min.css">
  <link rel="stylesheet" href="vendor/adminlte/dist/css/adminlte.min.css">
</head>
<body class="hold-transition login-page" 
      style="background-image:url('vendor/adminlte/dist/img/photo1.png'); 
             background-size:cover; 
             background-position:center;">
<div class="login-box">
  <div class="card">
    <div class="card-body login-card-body text-center">
      <h4 class="mb-3 text-success">
        <i class="fas fa-check-circle"></i> Anda berhasil logout
      </h4>
      <p class="text-muted">Anda akan diarahkan ke halaman login dalam 3 detik...</p>
      <a href="login.php" class="btn btn-primary btn-block mt-3">
        <i class="fas fa-sign-in-alt"></i> Klik di sini jika tidak otomatis
      </a>
    </div>
  </div>
</div>

<!-- AdminLTE JS -->
<script src="vendor/adminlte/plugins/jquery/jquery.min.js"></script>
<script src="vendor/adminlte/plugins/bootstrap/js/bootstrap.bundle.min.js"></script>
<script src="vendor/adminlte/dist/js/adminlte.min.js"></script>
</body>
</html>

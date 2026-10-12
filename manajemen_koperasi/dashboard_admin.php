<?php
session_start();
include __DIR__ . '/conf/auth.php'; // proteksi login
include __DIR__ . '/conf/conf_koperasi.php';
include __DIR__ . '/conf/conf.php';

error_reporting(0);
ini_set('display_errors', 0);

// pastikan hanya administrator yang bisa masuk
if ($_SESSION['role'] !== 'administrator') {
    header("Location: login.php?error=unauthorized");
    exit;
}

$conn     = bukakoneksikoperasi();
$db_induk = $db_name;

$totalUser = 0;
$totalAnggota = 0;
$periodeAktif = 'Belum Ditentukan';
$statusPeriode = 'Belum Ada';

$q = $conn->query("
      SELECT COUNT(*) total
      FROM daftar_anggota
      WHERE status_keanggotaan='Aktif'");

if($q){
    $totalAnggota = $q->fetch_assoc()['total'] ?? 0;
}

$q = $conn->query("
      SELECT tahun, is_final
      FROM periode_hitung
      WHERE status='Aktif'
      LIMIT 1
");

if ($q && $q->num_rows > 0) {
      $row = $q->fetch_assoc();
      $periodeAktif = $row['tahun'];
      $statusPeriode =
          ($row['is_final'] == 1)
          ? 'Final'
          : 'Proses';
}

$q = $conn->query("
      SELECT COUNT(*) total
      FROM pengajuan_anggota
      WHERE status IN ('Diteruskan','Diproses')
");

if($q){
    $totalPengajuan = $q->fetch_assoc()['total'] ?? 0;
}

?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <title>Dashboard Administrator</title>
  <!-- Font Awesome -->
  <link rel="stylesheet" href="vendor/adminlte/plugins/fontawesome-free/css/all.min.css">
  <!-- overlayScrollbars -->
  <link rel="stylesheet" href="vendor/adminlte/plugins/overlayScrollbars/css/OverlayScrollbars.min.css">
  <!-- AdminLTE CSS -->
  <link rel="stylesheet" href="vendor/adminlte/dist/css/adminlte.min.css">
</head>
<body class="hold-transition sidebar-mini layout-fixed">
  
<div class="wrapper">

  <!-- Navbar -->
  <?php include 'layout/header.php'; ?>

  <!-- Sidebar -->
  <?php include 'layout/sidebar.php'; ?>

  <!-- Content Wrapper -->
  <div class="content-wrapper">
    <!-- Content Header -->
    <section class="content-header">
      <div class="container-fluid">
        <h1>Dashboard Administrator</h1>
      </div>
    </section>

    <!-- Main content -->
    <section class="content">
      <div class="container-fluid">
        <div class="card shadow mb-4">
          <div class="card-body">
            Selamat datang, <strong><?= htmlspecialchars($_SESSION['nama_pegawai'] ?? 'Administrator') ?> </strong>👋  
            <p>Anda memiliki akses penuh ke seluruh <strong>MODUL KOPERASI</strong>.</p>
          </div>
        </div>

        <div class="row">
          <div class="col-md-6">
           <div class="info-box bg-primary">
              <span class="info-box-icon">
                <i class="fas fa-users"></i>
              </span>
              <div class="info-box-content">
                <span class="info-box-text">Total User Login</span>
                <span class="info-box-number"><?= $totalUser ?></span>
              </div>
            </div>
          </div>

          <div class="col-md-6">
            <div class="info-box bg-success">
              <span class="info-box-icon">
                <i class="fas fa-user-friends"></i>
              </span>
              <div class="info-box-content">
                <span class="info-box-text">Anggota Aktif</span>
                <span class="info-box-number"><?= $totalAnggota ?></span>
              </div>
            </div>
          </div>

          <div class="col-md-6">
            <div class="info-box bg-danger">
              <span class="info-box-icon">
                <i class="fas fa-calendar-alt"></i>
              </span>
              <div class="info-box-content">
                <span class="info-box-text">Periode Aktif</span>
                <span class="info-box-number"><?= $periodeAktif ?></span>
              </div>
            </div>
          </div>

          <div class="col-md-6">
            <div class="info-box bg-warning">
              <span class="info-box-icon">
                <i class="fas fa-cogs"></i>
              </span>
              <div class="info-box-content">
                <span class="info-box-text">Status SHU</span>
                <span class="info-box-number"><?= $statusPeriode ?></span>
              </div>
            </div>
          </div>
      </div>
    </section>
  </div>

  <!-- Footer -->
  <?php include 'layout/footer.php'; ?>

</div>
<!-- jQuery -->
<script src="vendor/adminlte/plugins/jquery/jquery.min.js"></script>
<!-- Bootstrap -->
<script src="vendor/adminlte/plugins/bootstrap/js/bootstrap.bundle.min.js"></script>
<!-- overlayScrollbars -->
<script src="vendor/adminlte/plugins/overlayScrollbars/js/jquery.overlayScrollbars.min.js"></script>
<!-- AdminLTE App -->
<script src="vendor/adminlte/dist/js/adminlte.min.js"></script>
</body>
</html>

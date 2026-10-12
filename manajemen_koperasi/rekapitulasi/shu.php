<?php
session_start();
include __DIR__ . '/../conf/auth.php';
include __DIR__ . '/../conf/conf_koperasi.php';
include __DIR__ . '/../conf/conf.php';

error_reporting(0);
ini_set('display_errors', 0);

// proteksi login
if (!isset($_SESSION['id_user'])) {
    header("Location: ../login.php?error=unauthorized");
    exit;
}

// role yang boleh masuk
$allowedRoles = ['administrator','ketua','sekretaris','bendahara','anggota'];
if (!in_array($_SESSION['role'], $allowedRoles)) {
    header("Location: ../login.php?error=unauthorized");
    exit;
}

$conn     = bukakoneksikoperasi();
$db_induk = $db_name;
$role     = $_SESSION['role']     ?? '';
$kode_jab = $_SESSION['kode_jab'] ?? '';

if ($role !== 'administrator' && $kode_jab !== 'kop_003') {
    die("Anda tidak memiliki hak akses ke halaman ini.");
}

// ambil rumus shu aktif
$sqlRumus = "SELECT *
             FROM rumus_shu
             ORDER BY id_rumus DESC
             LIMIT 1";
$resRumus = $conn->query($sqlRumus);
$rumusShu = $resRumus ? $resRumus->fetch_assoc() : null;

// ambil jasa aktif
$sqlJasa = "SELECT *
            FROM jasa_pinjam
            ORDER BY kode_jasa ASC";
$resJasa = $conn->query($sqlJasa);

// ambil seluruh periode hitung
$sqlPeriode = "SELECT id_periode, nama_periode, tahun, awal, akhir
               FROM periode_hitung
               ORDER BY tahun DESC, id_periode DESC";
$resPeriode = $conn->query($sqlPeriode);
$dataPeriode = [];
$totalData = $resPeriode ? mysqli_num_rows($resPeriode) : 0;

if ($resPeriode && $resPeriode->num_rows > 0) {
    while ($periode = $resPeriode->fetch_assoc()) {
        $awal  = date('Y-m', strtotime($periode['awal']));
        $akhir = date('Y-m', strtotime($periode['akhir']));
        $sqlTotal = "SELECT COALESCE(SUM(tabungan),0) AS total_tabungan,
                            COALESCE(SUM(angsuran_pinjaman),0) AS total_pinjaman,
                            COALESCE(SUM(angsuran_pinjaman_dua),0) AS total_pinjaman_dua
                     FROM detail_bulanan_koperasi
                     WHERE periode_bulan
                     BETWEEN '{$awal}'
                     AND '{$akhir}'";
        $resTotal = $conn->query($sqlTotal);
        $total = $resTotal
            ? $resTotal->fetch_assoc()
            : [ 'total_tabungan'     => 0,
                'total_pinjaman'     => 0,
                'total_pinjaman_dua' => 0];

        $periode['total_tabungan']     = $total['total_tabungan'];
        $periode['total_pinjaman']     = $total['total_pinjaman'];
        $periode['total_pinjaman_dua'] = $total['total_pinjaman_dua'];

        $dataPeriode[] = $periode;
    }
}

?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <title>Data Induk Koperasi</title>
  <!-- AdminLTE & Bootstrap 5 -->
  <link rel="stylesheet" href="../vendor/adminlte/plugins/fontawesome-free/css/all.min.css">
  <link rel="stylesheet" href="../vendor/adminlte/dist/css/adminlte.min.css">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.1/dist/css/bootstrap.min.css">
  <style>
    .table-responsive { overflow-x: auto; }
    table th, table td { white-space: nowrap; }
  </style>
</head>
<body class="hold-transition sidebar-mini layout-fixed">
<div class="wrapper">

  <!-- Navbar -->
  <?php include __DIR__ . '/../layout/header.php'; ?>

  <!-- Sidebar -->
  <?php include __DIR__ . '/../layout/sidebar.php'; ?>

  <!-- Content Wrapper -->
  <div class="content-wrapper">
    <section class="content-header">
      <div class="container-fluid">
        <h1 class="text-uppercase">SHU Koperasi</h1>
      </div>
    </section>

    <section class="content">
      <div class="container-fluid">
        <div class="card shadow">
          <div class="card-header bg-primary text-white d-flex justify-content-between align-items-center">
            <h5 class="mb-0 text-uppercase flex-grow-1 text-center">Rekap SHU Koperasi</h5>
            <div class="d-flex gap-2">
              <a href="../index.php" class="btn btn-secondary btn-sm">⬅️ Kembali</a>
            </div>
          </div>

          <div class="table-responsive">
            <table class="table table-striped table-bordered table-sm align-middle">
              <thead class="table-dark text-center">
                <tr>
                  <th>Periode</th>
                  <th>Total Tabungan</th>
                  <th>Total Angsuran Pinjaman</th>
                  <th>Total Angsuran Pinjaman 2</th>
                  <th>Aksi</th>
                </tr>
              </thead>
              <tbody>
                <?php if (!empty($dataPeriode)): ?>
                  <?php foreach ($dataPeriode as $periode): ?>
                    <tr>
                        <td><strong><?= htmlspecialchars($periode['nama_periode']) ?></strong><br>
                            <small><?= date('d-m-Y', strtotime($periode['awal'])) ?>
                            s/d
                            <?= date('d-m-Y', strtotime($periode['akhir'])) ?></small>
                        </td>
                        <td class="text-end"><?= number_format($periode['total_tabungan'] ?? 0, 2) ?></td>
                        <td class="text-end"><?= number_format($periode['total_pinjaman'] ?? 0, 2) ?></td>
                        <td class="text-end"><?= number_format($periode['total_pinjaman_dua'] ?? 0, 2) ?></td>
                        <td class="text-center">
                          <a href="detail_shu.php?id_periode=<?= $periode['id_periode'] ?>"
                            class="btn btn-info btn-sm">💰 Detail SHU Koperasi</a>
                        </td>
                    </tr>
                  <?php endforeach; ?>
                <?php else: ?>
                  <tr>
                    <td colspan="5" class="text-center text-muted">Belum terdapat data periode SHU.</td>
                  </tr>
                <?php endif; ?>
              </tbody>
            </table>
            <div class="mt-2 small text-start text-muted">Total Data : <?= $totalData ?></div>
          </div>
        </div>
      </div>
    </section>
  </div>

  <!-- Footer -->
  <?php include __DIR__ . '/../layout/footer.php'; ?>

</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.1/dist/js/bootstrap.bundle.min.js"></script>
<script src="../vendor/adminlte/plugins/jquery/jquery.min.js"></script>
<script src="../vendor/adminlte/plugins/bootstrap/js/bootstrap.bundle.min.js"></script>
<script src="../vendor/adminlte/dist/js/adminlte.min.js"></script>
</body>
</html>

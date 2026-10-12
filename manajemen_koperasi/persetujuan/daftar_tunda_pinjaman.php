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

// ambil daftar tunda pinjaman yang sudah dicatat
$sql = "SELECT dt.no_daftar, pg.nama, dt.bulan_tunda, dt.tunda_ke, dt.status, dt.tgl_setor
        FROM daftar_tunda_pinjaman dt
        INNER JOIN {$db_induk}.pegawai pg ON dt.id_user = pg.nik
        INNER JOIN daftar_anggota da ON da.id_user = dt.id_user
        WHERE da.status_keanggotaan = 'Aktif'
          AND da.kode_jab <> 'kop_006'
        ORDER BY dt.id_daftar DESC";
$result = $conn->query($sql);
$totalData = $result ? mysqli_num_rows($result) : 0;

?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <title>Daftar Tunda Pinjaman</title>
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
        <h1 class="text-uppercase">Daftar Tunda Pinjaman</h1>
      </div>
    </section>

    <section class="content">
      <div class="container-fluid">
        <div class="card shadow">
          <div class="card-header bg-primary text-white d-flex justify-content-between align-items-center">
            <h5 class="mb-0 text-uppercase flex-grow-1 text-center">Daftar Tunda Pinjaman</h5>
            <div class="d-flex gap-2">
              <a href="../index.php" class="btn btn-secondary btn-sm">⬅️ Kembali</a>
            </div>
          </div>

          <div class="table-responsive">
            <table class="table table-striped table-bordered table-sm align-middle">
              <thead class="table-dark text-center">
                <tr>
                  <th>No</th>
                  <th>No. Pengajuan</th>
                  <th>Nama Anggota</th>
                  <th>Bulan Tunda</th>
                  <th>Tunda Ke</th>
                  <th>Status</th>
                  <th>Tanggal Setor</th>
                </tr>
              </thead>
              <tbody>
                <?php 
                $no = 1;
                while ($row = $result->fetch_assoc()):
                ?>
                <tr>
                  <td class="text-center"><?= $no++ ?></td>
                  <td><?= htmlspecialchars($row['no_daftar']) ?></td>
                  <td><?= htmlspecialchars($row['nama']) ?></td>
                  <td class="text-center"><?= htmlspecialchars(date('F Y', strtotime($row['bulan_tunda']))) ?></td>
                  <td class="text-center"><?= htmlspecialchars($row['tunda_ke']) ?></td>
                  <td class="text-center">
                    <?php 
                      if ($row['status'] === 'Disetujui') {
                        echo "<span class='text-success fw-bold'>Disetujui</span>";
                      } elseif ($row['status'] === 'Ditolak') {
                        echo "<span class='text-danger fw-bold'>Ditolak</span>";
                      } else {
                        echo "<span class='text-warning fw-bold'>Diajukan</span>";
                      }
                    ?>
                  </td>
                  <td class="text-center"><?= htmlspecialchars($row['tgl_setor'] ?? '-') ?></td>
                </tr>
                <?php endwhile; ?>
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

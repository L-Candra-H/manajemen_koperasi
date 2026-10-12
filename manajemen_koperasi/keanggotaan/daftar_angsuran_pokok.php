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

// ambil daftar angsuran simpanan pokok dari tabel daftar_angsuran_pokok
$sql = "SELECT dap.id_angsuran, dap.no_daftar, pg.nama,
               dap.simp_pokok,
               dap.jumlah_dibayar,
               p.angsur_pokok AS jumlah_angsuran,
               (p.angsur_pokok - COALESCE(COUNT(dapok.id_detail),0)) AS sisa_angsuran,
               CASE 
                 WHEN (p.angsur_pokok - COALESCE(COUNT(dapok.id_detail),0)) = 0 
                 THEN 'Lunas' 
                 ELSE 'Belum Lunas' 
               END AS status_angsuran
        FROM daftar_angsuran_pokok dap
        INNER JOIN {$db_induk}.pegawai pg ON dap.id_user = pg.nik
        INNER JOIN daftar_anggota da ON da.id_user = dap.id_user
        INNER JOIN pengajuan_anggota p ON dap.no_daftar = p.no_daftar
        LEFT JOIN detail_angsuran_pokok dapok 
               ON dap.no_daftar = dapok.no_daftar 
              AND dapok.status='Sudah Bayar'
        WHERE da.status_keanggotaan = 'Aktif'
          AND da.kode_jab <> 'kop_006'
        GROUP BY dap.id_angsuran, dap.no_daftar, pg.nama, dap.simp_pokok, dap.jumlah_dibayar, p.angsur_pokok
        ORDER BY dap.no_daftar DESC";
$result = $conn->query($sql);
$totalData = $result ? mysqli_num_rows($result) : 0;

?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <title>Daftar Angsuran Simpanan Pokok</title>
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
        <h1 class="text-uppercase">Daftar Angsuran Simpanan Pokok</h1>
      </div>
    </section>

    <section class="content">
      <div class="container-fluid">
        <?php if(isset($_SESSION['success_migrasi_pokok'])): ?>
        <div class="alert alert-success alert-dismissible fade show shadow-sm">
          <strong>✅ Migrasi Berhasil!</strong><br>
          <?= $_SESSION['success_migrasi_pokok']; ?>
          <button type="button"
                  class="btn-close"
                  data-bs-dismiss="alert"
                  aria-label="Close">
          </button>
        </div>
        <?php unset($_SESSION['success_migrasi_pokok']); ?>
        <?php endif; ?>
        <?php if(isset($_SESSION['error_migrasi_pokok'])): ?>
        <div class="alert alert-danger alert-dismissible fade show shadow-sm">
          <strong>❌ Migrasi Gagal!</strong><br>
          <?= $_SESSION['error_migrasi_pokok']; ?>
          <button type="button"
                  class="btn-close"
                  data-bs-dismiss="alert"
                  aria-label="Close">
          </button>
        </div>
        <?php unset($_SESSION['error_migrasi_pokok']); ?>
        <?php endif; ?>
        <div class="card shadow">
          <div class="card-header bg-primary text-white d-flex justify-content-between align-items-center">
            <h5 class="mb-0 text-uppercase flex-grow-1 text-center">Data Angsuran</h5>
            <div class="d-flex gap-2">
              <?php if (in_array($kode_jab, ['kop_003']) || $role==='administrator'): ?>
                <button class="btn btn-success"
                        data-bs-toggle="modal"
                        data-bs-target="#modalMigrasi">
                      <i class="fas fa-file-import"></i>
                      Migrasi Angsuran Pokok Lama
                </button>
              <?php endif; ?>
              <a href="../index.php" class="btn btn-secondary btn-sm">⬅️ Kembali</a>
            </div>
          </div>

          <div class="table-responsive">
            <table class="table table-striped table-bordered table-sm align-middle">
              <thead class="table-dark text-center">
                <tr>
                  <th>No</th>
                  <th>ID Angsuran</th>
                  <th>No. Pengajuan</th>
                  <th>Nama Anggota</th>
                  <th>Simpanan Pokok</th>
                  <th>Jumlah Dibayar</th>
                  <th>Jumlah Angsuran</th>
                  <th>Sisa Angsuran</th>
                  <th>Status Angsuran</th>
                  <?php if (in_array($kode_jab, ['kop_003']) || $role==='administrator'): ?>
                    <th>Aksi</th>
                  <?php endif; ?>
                </tr>
              </thead>
              <tbody>
                <?php 
                $no=1;
                while($row = $result->fetch_assoc()):
                ?>
                <tr>
                  <td class="text-center"><?= $no++ ?></td>
                  <td class="text-center"><?= htmlspecialchars($row['id_angsuran']) ?></td>
                  <td><?= htmlspecialchars($row['no_daftar']) ?></td>
                  <td><?= htmlspecialchars($row['nama']) ?></td>
                  <td class="text-end"><?= number_format($row['simp_pokok'],0,',','.') ?></td>
                  <td class="text-end"><?= number_format($row['jumlah_dibayar'],0,',','.') ?></td>
                  <td class="text-center"><?= $row['jumlah_angsuran'] ?> x</td>
                  <td class="text-center"><?= $row['sisa_angsuran'] ?> x</td>
                  <td class="text-center"><?= $row['status_angsuran'] ?></td>
                  <?php if (in_array($kode_jab, ['kop_003']) || $role==='administrator'): ?>
                    <td class="text-center">
                      <a href="detail_angsuran_pokok.php?no_daftar=<?= urlencode($row['no_daftar']) ?>" 
                         class="btn btn-info btn-sm">💰 Detail Angsuran</a>
                    </td>
                  <?php endif; ?>
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

<?php include '../keanggotaan/pengajuan_angsuran_pokok_migrasi.php'; ?>

</body>
</html>

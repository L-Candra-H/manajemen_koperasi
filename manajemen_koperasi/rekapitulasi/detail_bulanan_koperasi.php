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

// hanya administrator & bendahara
if ($role !== 'administrator' && $kode_jab !== 'kop_003') {
    header("Location: ../index.php?notif=forbidden");
    exit;
}

// filter periode & anggota
$periode = $_GET['periode'] ?? null;
$anggota = $_GET['anggota'] ?? null;

// dropdown anggota
$qAnggota = $conn->query("SELECT di.no_anggota, pg.nama 
                          FROM data_induk_koperasi di 
                          LEFT JOIN {$db_induk}.pegawai pg ON di.id_user = pg.nik 
                          ORDER BY di.no_anggota ASC");

// query utama
$sql = "SELECT di.no_anggota, pg.nama, di.status_keanggotaan, di.tgl_terdaftar,
               jk.jab AS jabatan_koperasi,
               db.periode_bulan,
               db.angsuran_ke_pokok, db.angsuran_pokok,
               db.simpanan_wajib, db.tabungan,
               db.pinjaman_ke, db.angsuran_pinjaman,
               db.pinjaman_dua_ke, db.angsuran_pinjaman_dua,
               db.total_potongan
        FROM detail_bulanan_koperasi db
        JOIN data_induk_koperasi di ON db.no_anggota = di.no_anggota
        LEFT JOIN {$db_induk}.pegawai pg ON di.id_user = pg.nik
        LEFT JOIN jabatan_koperasi jk ON di.kode_jab = jk.kode_jab
        WHERE 1=1";

if ($periode) $sql .= " AND db.periode_bulan='$periode'";
if ($anggota) $sql .= " AND di.no_anggota='$anggota'";

$sql .= " ORDER BY di.no_anggota ASC";
$res = $conn->query($sql);
$totalData = $res ? mysqli_num_rows($res) : 0;
?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <title>Detail Bulanan Koperasi</title>
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
        <h1 class="text-uppercase">Detail Bulanan Koperasi</h1>
      </div>
    </section>

    <section class="content">
      <div class="container-fluid">
        <div class="card shadow">
          <div class="card-header bg-primary text-white d-flex justify-content-between align-items-center">
            <h5 class="mb-0 text-uppercase flex-grow-1 text-center">Rincian Data Bulanan Koperasi</h5>
            <div class="d-flex gap-2">
              <a href="../index.php" class="btn btn-secondary btn-sm">⬅️ Kembali</a>
            </div>
          </div>

          <div class="card-body p-3">
            <div class="d-flex gap-3 mb-3">
              <!-- Form generate sinkronisasi bulanan -->
              <form method="post" action="induk_sync_update.php?action=sinkronisasi_bulanan" class="d-flex">
                <div class="input-group" style="max-width:300px;">
                  <span class="input-group-text">Periode Bulan</span>
                  <input type="month" name="periode_bulan" class="form-control" required>
                  <button type="submit" class="btn btn-primary">⚡ Generate</button>
                </div>
              </form>

              <!-- Dropdown filter anggota -->
              <form method="get" class="d-flex">
                <input type="hidden" name="periode" value="<?= htmlspecialchars($periode ?? '') ?>">
                <div class="input-group" style="max-width:300px;">
                  <span class="input-group-text">Anggota</span>
                  <select name="anggota" class="form-select" onchange="this.form.submit()">
                    <option value="">-- Semua Anggota --</option>
                    <?php while ($opt = $qAnggota->fetch_assoc()): ?>
                      <option value="<?= $opt['no_anggota'] ?>" <?= ($anggota === $opt['no_anggota']) ? 'selected' : '' ?>>
                        <?= $opt['no_anggota'] ?> - <?= $opt['nama'] ?>
                      </option>
                    <?php endwhile; ?>
                  </select>
                </div>
              </form>
            </div>

            <div class="table-responsive">
              <table class="table table-striped table-bordered table-sm align-middle">
                <thead class="table-dark text-center">
                  <tr>
                    <th>No Anggota</th>
                    <th>Nama</th>
                    <th>Status</th>
                    <th>Tgl Terdaftar</th>
                    <th>Jabatan Koperasi</th>
                    <th>Periode Bulan</th>
                    <th>Angsuran Pokok Ke</th>
                    <th>Angsuran Pokok</th>
                    <th>Simpanan Wajib</th>
                    <th>Tabungan</th>
                    <th>Angsuran Pinjaman Ke</th>
                    <th>Angsuran Pinjaman</th>
                    <th>Angsuran Pinjaman 2 Ke</th>
                    <th>Angsuran Pinjaman 2</th>
                    <th>Total Potongan</th>
                  </tr>
                </thead>
                <tbody>
                  <?php if ($res && $res->num_rows > 0): ?>
                    <?php while ($row = $res->fetch_assoc()): ?>
                      <tr>
                        <td><?= htmlspecialchars($row['no_anggota']) ?></td>
                        <td><?= htmlspecialchars($row['nama']) ?></td>
                        <td><?= htmlspecialchars($row['status_keanggotaan']) ?></td>
                        <td><?= htmlspecialchars($row['tgl_terdaftar']) ?></td>
                        <td><?= htmlspecialchars($row['jabatan_koperasi']) ?></td>
                        <td><?= htmlspecialchars($row['periode_bulan']) ?></td>
                        <td class="text-center"><?= $row['angsuran_ke_pokok'] ?? '-' ?></td>
                        <td class="text-end"><?= number_format($row['angsuran_pokok'] ?? 0, 2) ?></td>
                        <td class="text-end"><?= number_format($row['simpanan_wajib'] ?? 0, 2) ?></td>
                        <td class="text-end"><?= number_format($row['tabungan'] ?? 0, 2) ?></td>
                        <td class="text-center"><?= $row['pinjaman_ke'] ?? '-' ?></td>
                        <td class="text-end"><?= number_format($row['angsuran_pinjaman'] ?? 0, 2) ?></td>
                        <td class="text-center"><?= $row['pinjaman_dua_ke'] ?? '-' ?></td>
                        <td class="text-end"><?= number_format($row['angsuran_pinjaman_dua'] ?? 0, 2) ?></td>
                        <td class="text-end fw-bold"><?= number_format($row['total_potongan'] ?? 0, 2) ?></td>
                      </tr>
                    <?php endwhile; ?>
                  <?php else: ?>
                    <tr>
                      <td colspan="15" class="text-center text-muted">Tidak ada data</td>
                    </tr>
                  <?php endif; ?>
                </tbody>
              </table>
              <div class="mt-2 small text-start text-muted">Total Data : <?= $totalData ?></div>
            </div>
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

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

// ambil daftar tahun unik dari periode_bulan
$sqlTahun = "SELECT DISTINCT YEAR(STR_TO_DATE(periode_bulan,'%Y-%m')) AS tahun
             FROM detail_bulanan_koperasi
             ORDER BY tahun DESC";
$qTahun = $conn->query($sqlTahun);

$tahunDipilih = $_GET['tahun'] ?? null;
$bulanDipilih = $_GET['bulan'] ?? null;
$periode = null;

// kalau tahun dan bulan dipilih, bentuk periode YYYY-MM
if ($tahunDipilih && $bulanDipilih) {
    $periode = sprintf("%04d-%02d", $tahunDipilih, $bulanDipilih);
}

// ambil daftar bulan sesuai tahun yang dipilih
$qBulan = null;
if ($tahunDipilih) {
    $sqlBulan = "SELECT DISTINCT MONTH(STR_TO_DATE(periode_bulan,'%Y-%m')) AS bulan
                 FROM detail_bulanan_koperasi
                 WHERE YEAR(STR_TO_DATE(periode_bulan,'%Y-%m'))=".$conn->real_escape_string($tahunDipilih)."
                 ORDER BY bulan ASC";
    $qBulan = $conn->query($sqlBulan);
}

$where = "1=1";
if ($role !== 'administrator' && $kode_jab !== 'kop_003') {
    if (!empty($_SESSION['id_user'])) {
        $where .= " AND di.id_user='".$conn->real_escape_string($_SESSION['id_user'])."'";
    } else {
        $where .= " AND 1=0"; // cegah tampil data kalau id_user kosong
    }
}
if ($periode) {
    $where .= " AND db.periode_bulan='".$conn->real_escape_string($periode)."'";
}

// query utama (hanya jalan kalau periode dipilih)
$res = null;
if ($periode) {
    $sql = "SELECT
                   di.no_anggota, pg.nama, di.status_keanggotaan, di.tgl_terdaftar,
                   jk.jab AS jabatan_koperasi,
                   di.dana_pensiun, 
                   CASE
                      WHEN COALESCE(di.simpanan_pokok,0) > 0
                      THEN di.simpanan_pokok
                      ELSE
                      COALESCE(
                          (
                          SELECT SUM(x.angsuran_pokok)
                          FROM detail_bulanan_koperasi x
                          WHERE x.no_anggota = di.no_anggota
                          AND x.periode_bulan <= db.periode_bulan
                          ),
                          0
                      )
                   END AS total_simpanan_pokok, 
                   di.simpanan_wajib_lama,
                   (
                       di.simpanan_wajib_lama +
                       COALESCE(
                           (
                               SELECT SUM(x.simpanan_wajib)
                               FROM detail_bulanan_koperasi x
                               WHERE x.no_anggota = di.no_anggota
                               AND x.periode_bulan <= db.periode_bulan
                           ),
                           0
                       )
                   ) AS total_simpanan_wajib,

                   COALESCE(
                       (
                           SELECT SUM(x.tabungan)
                           FROM detail_bulanan_koperasi x
                           WHERE x.no_anggota = di.no_anggota
                           AND x.periode_bulan <= db.periode_bulan
                       ),
                       0
                   ) AS total_tabungan,

                   db.periode_bulan, db.angsuran_ke_pokok,
                   db.angsuran_pokok, db.simpanan_wajib,
                   db.tabungan, db.pinjaman_ke,
                   db.angsuran_pinjaman, db.pinjaman_dua_ke,
                   db.angsuran_pinjaman_dua, db.total_potongan

            FROM detail_bulanan_koperasi db
            JOIN data_induk_koperasi di
                ON db.no_anggota = di.no_anggota
            LEFT JOIN {$db_induk}.pegawai pg
                ON di.id_user = pg.nik
            LEFT JOIN jabatan_koperasi jk
                ON di.kode_jab = jk.kode_jab
            WHERE $where
            ORDER BY di.no_anggota ASC";

    $res = $conn->query($sql);
    $totalData = $res ? mysqli_num_rows($res) : 0;
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <title>Riwayat Bulanan Koperasi</title>
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
        <h1 class="text-uppercase">Riwayat Bulanan Koperasi</h1>
      </div>
    </section>

    <section class="content">
      <div class="container-fluid">
        <div class="card shadow">
          <div class="card-header bg-primary text-white d-flex justify-content-between align-items-center">
            <h5 class="mb-0 text-uppercase flex-grow-1 text-center">Riwayat Koperasi</h5>
            <div class="d-flex gap-2">
              <a href="../index.php" class="btn btn-secondary btn-sm">⬅️ Kembali</a>
            </div>
          </div>

          <div class="card-body p-3">
            <form method="get" class="d-flex mb-3 gap-3 align-items-end">
              <!-- Dropdown Tahun -->
              <div class="input-group" style="max-width:250px;">
                <span class="input-group-text">Tahun</span>
                <select name="tahun" class="form-select" onchange="this.form.submit()">
                  <option value="">-- Pilih Tahun --</option>
                  <?php while ($opt = $qTahun->fetch_assoc()): ?>
                    <option value="<?= $opt['tahun'] ?>" <?= ($tahunDipilih==$opt['tahun'])?'selected':'' ?>>
                      <?= $opt['tahun'] ?>
                    </option>
                  <?php endwhile; ?>
                </select>
              </div>

              <!-- Dropdown Bulan -->
              <?php if ($qBulan): ?>
              <div class="input-group" style="max-width:250px;">
                <span class="input-group-text">Bulan</span>
                <select name="bulan" class="form-select" onchange="this.form.submit()">
                  <option value="">-- Pilih Bulan --</option>
                  <?php 
                    $namaBulan = [
                      1=>'Januari',2=>'Februari',3=>'Maret',4=>'April',
                      5=>'Mei',6=>'Juni',7=>'Juli',8=>'Agustus',
                      9=>'September',10=>'Oktober',11=>'November',12=>'Desember'
                    ];
                  ?>
                  <?php while ($opt = $qBulan->fetch_assoc()): ?>
                    <option value="<?= $opt['bulan'] ?>" <?= ($bulanDipilih==$opt['bulan'])?'selected':'' ?>>
                      <?= $namaBulan[$opt['bulan']] ?>
                    </option>
                  <?php endwhile; ?>
                </select>
              </div>
              <?php endif; ?>

              <!-- Tombol Cetak Semua -->
              <?php if ($role==='administrator' || $kode_jab==='kop_003'): ?>
                <div>
                  <a href="cetak_bulanan.php?periode=<?= urlencode($periode) ?>&anggota=all"
                     target="_blank"
                     class="btn btn-primary btn-sm <?= (empty($tahunDipilih) || empty($bulanDipilih)) ? 'disabled btn-secondary' : '' ?>"
                     <?= (empty($tahunDipilih) || empty($bulanDipilih)) ? 'tabindex="-1" aria-disabled="true"' : '' ?>>
                     Cetak Semua Anggota
                  </a>
                </div>
              <?php endif; ?>

              <!-- Tombol Cetak Laporan -->
              <?php if ($role==='administrator' || $kode_jab==='kop_003'): ?>
                <div>
                  <a href="cetak_laporan.php?periode=<?= urlencode($periode) ?>&anggota=all"
                     target="_blank"
                     class="btn btn-primary btn-sm <?= (empty($tahunDipilih) || empty($bulanDipilih)) ? 'disabled btn-secondary' : '' ?>"
                     <?= (empty($tahunDipilih) || empty($bulanDipilih)) ? 'tabindex="-1" aria-disabled="true"' : '' ?>>
                     Cetak Laporan Bulanan
                  </a>
                </div>
              <?php endif; ?>
            </form>

            <?php if ($periode && $res && $res->num_rows > 0): ?>
            <div class="table-responsive">
              <table class="table table-striped table-bordered table-sm align-middle">
                <thead class="table-dark text-center">
                  <tr>
                    <th>No Anggota</th>
                    <th>Nama</th>
                    <th>Status</th>
                    <th>Tgl Terdaftar</th>
                    <th>Jabatan</th>
                    <th>Periode Bulan</th>
                    <?php if ($role === 'administrator' || $kode_jab === 'kop_003'): ?>
                        <th>Dana Pensiun</th>
                        <th>Simpanan Pokok</th>
                        <th>Total Simpanan Wajib</th>
                        <th>Total Tabungan</th>
                    <?php endif; ?>
                    <th>Total Potongan</th><th>Aksi</th>
                  </tr>
                </thead>
                <tbody>
                  <?php while ($row = $res->fetch_assoc()): ?>
                    <tr>
                      <td><?= htmlspecialchars($row['no_anggota']) ?></td>
                      <td><?= htmlspecialchars($row['nama']) ?></td>
                      <td><?= htmlspecialchars($row['status_keanggotaan']) ?></td>
                      <td><?= htmlspecialchars($row['tgl_terdaftar']) ?></td>
                      <td><?= htmlspecialchars($row['jabatan_koperasi']) ?></td>
                      <td><?= htmlspecialchars($row['periode_bulan']) ?></td>
                      <?php if ($role === 'administrator' || $kode_jab === 'kop_003'): ?>
                          <td class="text-end"><?= number_format($row['dana_pensiun'], 2) ?></td>
                          <td class="text-end"><?= number_format($row['total_simpanan_pokok'], 2) ?></td>
                          <td class="text-end fw-bold text-primary"><?= number_format($row['total_simpanan_wajib'], 2) ?></td>
                          <td class="text-end fw-bold text-success"><?= number_format($row['total_tabungan'], 2) ?></td>
                      <?php endif; ?>
                      <td class="text-end fw-bold"><?= number_format($row['total_potongan'] ?? 0, 2) ?></td>
                      <td class="text-center">
                        <a href="cetak_bulanan.php?periode=<?= urlencode($row['periode_bulan']) ?>&anggota=<?= $row['no_anggota'] ?>"
                           target="_blank"
                           class="btn btn-sm btn-success">Cetak</a>
                      </td>
                    </tr>
                  <?php endwhile; ?>
                </tbody>
              </table>
              <div class="mt-2 small text-start text-muted">Total Data : <?= $totalData ?></div>
            </div>
            <?php elseif ($periode): ?>
              <div class="alert alert-warning">Tidak ada data untuk periode <?= htmlspecialchars($periode) ?>.</div>
            <?php else: ?>
              <div class="alert alert-info">Silakan pilih periode bulan terlebih dahulu.</div>
            <?php endif; ?>
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

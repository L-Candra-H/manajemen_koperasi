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

// cek role & jabatan
$role     = $_SESSION['role']     ?? '';
$kode_jab = $_SESSION['kode_jab'] ?? '';

// hanya administrator dan bendahara
if ($role !== 'administrator' && $kode_jab !== 'kop_003') {
    die("Anda tidak memiliki hak akses ke halaman ini.");
}

$skipRekap = false;
$no_daftar = $_GET['no_daftar'] ?? null;
if (!$no_daftar) {
    ?>
    <!DOCTYPE html>
    <html lang="id">
    <head>
      <meta charset="UTF-8">
      <title>Error</title>
      <link rel="stylesheet" href="../vendor/adminlte/dist/css/adminlte.min.css">
      <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
      <style>
        body { 
          background-color: #f4f6f9; 
          display: flex;
          justify-content: center;
          align-items: center;
          height: 100vh;
        }
        .error-box { 
          max-width: 500px;
          width: 100%;
        }
      </style>
    </head>
    <body>
      <div class="card error-box shadow">
        <div class="card-header bg-danger text-white text-center">
          <h5 class="mb-0"><i class="fas fa-exclamation-triangle"></i> Nomor Pengajuan Tidak Ditemukan</h5>
        </div>
        <div class="card-body text-center">
          <p class="mb-3">Nomor pengajuan yang Anda cari tidak tersedia atau sudah dihapus.</p>
          <a href="daftar_angsuran_pokok.php" class="btn btn-primary">⬅️ Kembali</a>
        </div>
      </div>
    </body>
    </html>
    <?php
    exit;
}

// ambil data anggota dari pengajuan
$sqlAnggota = "SELECT pg.nama, p.simp_pokok, p.angsur_pokok, 
                      p.id_user, p.tgl_pembuatan
               FROM pengajuan_anggota p
               INNER JOIN {$db_induk}.pegawai pg ON p.id_user = pg.nik
               WHERE p.no_daftar = ?";
$stmtAnggota = $conn->prepare($sqlAnggota);
$stmtAnggota->bind_param("s", $no_daftar);
$stmtAnggota->execute();
$resAnggota = $stmtAnggota->get_result();
$anggota    = $resAnggota ? $resAnggota->fetch_assoc() : null;

// ambil detail angsuran
$sqlDetail = "SELECT * FROM detail_angsuran_pokok 
              WHERE no_daftar = ? 
              ORDER BY bulan ASC";
$stmtDetail = $conn->prepare($sqlDetail);
$stmtDetail->bind_param("s", $no_daftar);
$stmtDetail->execute();
$resDetail = $stmtDetail->get_result();
$totalData = $resDetail ? mysqli_num_rows($resDetail) : 0;

// ambil bulan terakhir
$sqlLast = "SELECT MAX(bulan) AS last_bulan
            FROM detail_angsuran_pokok
            WHERE no_daftar = ?";
$stmtLast = $conn->prepare($sqlLast);
$stmtLast->bind_param("s", $no_daftar);
$stmtLast->execute();
$rowLast = $stmtLast->get_result()->fetch_assoc();
$lastBulan = $rowLast['last_bulan'] ?? null;

// hitung jumlah angsuran yang sudah terbentuk
$sqlCount = "SELECT COUNT(*) AS total
             FROM detail_angsuran_pokok
             WHERE no_daftar = ?";

$stmtCount = $conn->prepare($sqlCount);
$stmtCount->bind_param("s", $no_daftar);
$stmtCount->execute();

$rowCount = $stmtCount->get_result()->fetch_assoc();

$angsuranKe = ((int)$rowCount['total']) + 1;

// tentukan bulan pertama
$tgl_mulai  = $anggota['tgl_pembuatan'] ?? date("Y-m-d");
$firstMonth = date("Y-m-01", strtotime("+1 month", strtotime($tgl_mulai)));

// tentukan bulan berikutnya
if ($lastBulan) {
    $nextBulan = date(
        "Y-m-01",
        strtotime("+1 month", strtotime($lastBulan))
    );
} else {
    $nextBulan = $firstMonth;
}

// cek apakah masih ada angsuran belum bayar
$sqlBelumBayar = "
    SELECT COUNT(*) AS jumlah
    FROM detail_angsuran_pokok
    WHERE no_daftar = ?
    AND status = 'Belum Bayar'
";

$stmtBelumBayar = $conn->prepare($sqlBelumBayar);
$stmtBelumBayar->bind_param("s", $no_daftar);
$stmtBelumBayar->execute();

$rowBelumBayar =
    $stmtBelumBayar
        ->get_result()
        ->fetch_assoc();

$adaBelumBayar =
    ((int)$rowBelumBayar['jumlah']) > 0;

$stmtBelumBayar->close();

// =====================================================
// BOLEH GENERATE
// =====================================================
$bulanSekarang = date('Y-m');
$bulanTerakhir =
    $lastBulan
    ? date('Y-m', strtotime($lastBulan))
    : null;

$bolehGenerate =
(
    $anggota
    && $angsuranKe <= $anggota['angsur_pokok']
    && !$adaBelumBayar
    && $bulanTerakhir < $bulanSekarang
    && (
        $role === 'administrator'
        || $kode_jab === 'kop_003'
    )
);

// proses generate
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['generate'])) {
    if ($bolehGenerate) {
        $simp_pokok     = (float)$anggota['simp_pokok'];
        $angsur_pokok   = (int)$anggota['angsur_pokok'];
        $idUser         = $anggota['id_user'];
        $jumlah_cicilan = $simp_pokok / $angsur_pokok;

        $stmtIns = $conn->prepare("INSERT INTO detail_angsuran_pokok 
            (no_daftar, id_user, simp_pokok, jumlah_angsuran, angsuran_ke, bulan, jumlah, status) 
            VALUES (?, ?, ?, ?, ?, ?, ?, 'Belum Bayar')");
        $stmtIns->bind_param("ssdiisd",
            $no_daftar, $idUser, $simp_pokok, $angsur_pokok, $angsuranKe, $nextBulan, $jumlah_cicilan
        );
        $stmtIns->execute();
        $stmtIns->close();

        header("Location: detail_angsuran_pokok.php?no_daftar=" . urlencode($no_daftar));
        exit;
    }
}

// proses bayar
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['bayar_id'])) {
    if ($role === 'administrator' || $kode_jab === 'kop_003') {
        $idDetail = (int)$_POST['bayar_id'];
        $tglBayar = date("Y-m-d");

        $stmtBayar = $conn->prepare("UPDATE detail_angsuran_pokok 
                                     SET status='Sudah Bayar', tgl_bayar=? 
                                     WHERE id_detail=?");
        $stmtBayar->bind_param("si", $tglBayar, $idDetail);
        $stmtBayar->execute();
        $stmtBayar->close();

        // rekap
        $sqlRekap = "UPDATE daftar_angsuran_pokok dap
                     JOIN (
                       SELECT no_daftar,
                              COALESCE(SUM(jumlah),0) AS total_bayar,
                              COUNT(*) AS total_bayar_count
                       FROM detail_angsuran_pokok
                       WHERE status='Sudah Bayar'
                       GROUP BY no_daftar
                     ) da ON dap.no_daftar = da.no_daftar
                     SET dap.jumlah_dibayar = da.total_bayar,
                         dap.sisa_angsuran = dap.jumlah_angsuran - da.total_bayar_count,
                         dap.status_angsuran = CASE 
                              WHEN dap.jumlah_angsuran - da.total_bayar_count = 0 THEN 'Lunas' 
                              ELSE 'Belum Lunas' END";
        $conn->query($sqlRekap);
        
        header("Location: detail_angsuran_pokok.php?no_daftar=" . urlencode($no_daftar));
        exit;

    }
}

?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <title>Detail Angsuran Simpanan Pokok</title>
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
        <h1 class="text-uppercase">Detail Angsuran Simpanan Pokok</h1>
      </div>
    </section>

    <section class="content">
      <div class="container-fluid">
        <div class="card shadow">
          <div class="card-header bg-primary text-white d-flex justify-content-between align-items-center">
            <h5 class="mb-0 text-uppercase flex-grow-1 text-center">Detail Angsuran Simpanan Pokok</h5>
            <?php if ($anggota): ?>
              <span class="ms-3">
                | <?= htmlspecialchars($anggota['nama'] ?? '') ?> 
                | Rp <?= number_format($anggota['simp_pokok'] ?? 0,0,',','.') ?> 
                | <?= $anggota['angsur_pokok'] ?? 0 ?> x
              </span>
            <?php endif; ?>
            <div class="d-flex gap-2 ms-auto">
              <?php if ($role === 'administrator' || $kode_jab === 'kop_003'): ?>
                <?php if ($bolehGenerate): ?>
                  <form method="post" action="">
                    <button type="submit" name="generate" class="btn btn-success btn-sm">📝 Tambah Angsuran</button>
                  </form>
                <?php else: ?>
                  <button class="btn btn-secondary btn-sm" disabled>📝 Tambah Angsuran</button>
                <?php endif; ?>
              <?php endif; ?>
              <a href="daftar_angsuran_pokok.php" class="btn btn-secondary btn-sm">⬅️ Kembali</a>
            </div>
          </div>

          <div class="table-responsive">
            <table class="table table-striped table-bordered table-sm align-middle">
              <thead class="table-dark text-center">
                <tr>
                  <th>Angsuran Ke</th>
                  <th>Bulan</th>
                  <th>Jumlah</th>
                  <th>Status</th>
                  <th>Tanggal Bayar</th>
                  <th>Aksi</th>
                </tr>
              </thead>
              <tbody>
                <?php if ($resDetail->num_rows > 0): ?>
                  <?php while($row = $resDetail->fetch_assoc()): ?>
                    <tr>
                      <td class="text-center"><?= $row['angsuran_ke'] ?></td>
                      <td class="text-center"><?= date('F Y', strtotime($row['bulan'])) ?></td>
                      <td class="text-end"><?= number_format($row['jumlah'],0,',','.') ?></td>
                      <td class="text-center"><?= $row['status'] ?></td>
                      <td class="text-center"><?= $row['tgl_bayar'] ?: '-' ?></td>
                      <td class="text-center">
                        <?php if ($role === 'administrator' || $kode_jab === 'kop_003'): ?>
                          <?php if($row['status']=='Belum Bayar'): ?>
                            <form method="post" action="">
                              <input type="hidden" name="bayar_id" value="<?= $row['id_detail'] ?>">
                              <button type="submit" class="btn btn-success btn-sm">💵 Bayar</button>
                            </form>
                          <?php else: ?>
                            <span class="text-muted">✔</span>
                          <?php endif; ?>
                        <?php else: ?>
                          <span class="text-muted">-</span>
                        <?php endif; ?>
                      </td>
                    </tr>
                  <?php endwhile; ?>
                <?php else: ?>
                    <tr>
                      <td colspan="6" class="text-center text-muted">
                        ⚠️ Belum ada data angsuran simpanan pokok untuk pengajuan ini.
                      </td>
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

<!-- Scripts -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.1/dist/js/bootstrap.bundle.min.js"></script>
<script src="../vendor/adminlte/plugins/jquery/jquery.min.js"></script>
<script src="../vendor/adminlte/plugins/bootstrap/js/bootstrap.bundle.min.js"></script>
<script src="../vendor/adminlte/dist/js/adminlte.min.js"></script>

</body>
</html>

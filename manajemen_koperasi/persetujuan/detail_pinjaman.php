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
          <a href="persetujuan_pinjaman.php" class="btn btn-primary">⬅️ Kembali</a>
        </div>
      </div>
    </body>
    </html>
    <?php
    exit;
}

// 1. Ambil data pinjaman
$sqlPinjaman = "SELECT pg.nama, p.pinjaman, p.angsur_pinjaman,
                       p.angsuran_per_bulan, p.id_user, p.tgl_pembuatan, p.kode_jangka,
                       dp.status_angsuran, dp.sisa_angsuran
                FROM pengajuan_pinjaman p
                INNER JOIN {$db_induk}.pegawai pg ON p.id_user = pg.nik
                LEFT JOIN daftar_pinjaman dp ON p.no_daftar = dp.no_daftar
                WHERE p.no_daftar = ?";
$stmtPinjaman = $conn->prepare($sqlPinjaman);
$stmtPinjaman->bind_param("s", $no_daftar);
$stmtPinjaman->execute();
$resPinjaman = $stmtPinjaman->get_result();
$pinjaman = $resPinjaman ? $resPinjaman->fetch_assoc() : null;
$totalData = $resPinjaman ? mysqli_num_rows($resPinjaman) : 0;

// 2. Ambil detail pinjaman
$sqlDetail = "SELECT * FROM detail_pinjaman WHERE no_daftar = ? ORDER BY bulan ASC";
$stmtDetail = $conn->prepare($sqlDetail);
$stmtDetail->bind_param("s", $no_daftar);
$stmtDetail->execute();
$resDetail = $stmtDetail->get_result();

// 3. Ambil bulan terakhir
$sqlLast = "SELECT MAX(bulan) AS last_bulan 
            FROM detail_pinjaman 
            WHERE no_daftar = ?";
$stmtLast = $conn->prepare($sqlLast);
$stmtLast->bind_param("s", $no_daftar);
$stmtLast->execute();
$rowLast = $stmtLast->get_result()->fetch_assoc();
$lastBulan = $rowLast['last_bulan'] ?? null;

// 4. Ambil daftar bulan tunda disetujui
$sqlPersetujuan = "SELECT bulan_tunda, tunda_ke
                   FROM pengajuan_tunda_pinjaman
                   WHERE no_daftar = ? AND status='Disetujui'";
$stmtPersetujuan = $conn->prepare($sqlPersetujuan);
$stmtPersetujuan->bind_param("s", $no_daftar);
$stmtPersetujuan->execute();
$resPersetujuan = $stmtPersetujuan->get_result();
$mapTunda = [];
while($rowP = $resPersetujuan->fetch_assoc()) {
    $mapTunda[date('Y-m', strtotime($rowP['bulan_tunda']))] = $rowP['tunda_ke'];
}

// 5. Ambil angsuran terakhir yang sudah dibayar
if (strtolower($pinjaman['kode_jangka'] ?? '') === 'migrasi') {
    $lastPaid = (int)$pinjaman['angsur_pinjaman'] - (int)$pinjaman['sisa_angsuran'];
} else {
    $sqlLastPaid = "SELECT MAX(angsuran_ke) AS last_paid
                    FROM detail_pinjaman
                    WHERE no_daftar = ? AND status='Sudah Bayar'";
    $stmtLastPaid = $conn->prepare($sqlLastPaid);
    $stmtLastPaid->bind_param("s", $no_daftar);
    $stmtLastPaid->execute();
    $resLastPaid = $stmtLastPaid->get_result()->fetch_assoc();
    $lastPaid = $resLastPaid['last_paid'] ?? 0;
}

// 6. Default angsuran ke berikutnya
$angsuranke = $lastPaid + 1;

// 7. Tentukan bulan berikutnya
$tgl_mulai = $pinjaman['tgl_pembuatan'] ?? date("Y-m-d");
if ($lastBulan) {
    $tsLast = strtotime($lastBulan);
    $tsNext = strtotime("+1 month", $tsLast);
    $nextBulan = date("Y-m-01", $tsNext);
} else {
    $tsMulai = strtotime($tgl_mulai);
    $tsNext = strtotime("+1 month", $tsMulai);
    $nextBulan = date("Y-m-01", $tsNext);
}

// 8. Hitung bolehGenerate (setelah nextSudahAda dihitung)
// Cek apakah bulan+1 sudah ada
$bulanSekarang = date('Y-m');
$bulanTerakhir =
    $lastBulan
    ? date('Y-m', strtotime($lastBulan))
    : null;

// Hitung bolehGenerate
$bolehGenerate = ($pinjaman
    && $angsuranke <= $pinjaman['angsur_pinjaman']
    && ($bulanTerakhir === null || $bulanTerakhir < $bulanSekarang)
    && ($role === 'administrator' || $kode_jab === 'kop_003'));

// 9. Jika tombol Generate ditekan
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['generate'])) {
    if ($bolehGenerate) {
        $jumlah_cicilan = $pinjaman['angsuran_per_bulan'];
        $bulanNext = $nextBulan; // hanya bulan +1
        $statusAngsuran = 'Belum Bayar';
        $tundaKe = 0;
        $angsuranke = $lastPaid + 1;

        $stmtIns = $conn->prepare("INSERT INTO detail_pinjaman
            (no_daftar, id_user, pinjaman, jumlah_angsuran, angsuran_ke, bulan,
             jumlah_cicilan, tunda_ke, status)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmtIns->bind_param("ssdiisdis",
            $no_daftar, $pinjaman['id_user'], $pinjaman['pinjaman'],
            $pinjaman['angsur_pinjaman'], $angsuranke, $bulanNext,
            $jumlah_cicilan, $tundaKe, $statusAngsuran
        );
        $stmtIns->execute();
        $stmtIns->close();

        header("Location: detail_pinjaman.php?no_daftar=" . urlencode($no_daftar));
        exit;
    }
}

// 10. Jika tombol Bayar ditekan
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['bayar_id'])) {
    if ($role === 'administrator' || $kode_jab === 'kop_003') {
        $idDetail = (int)$_POST['bayar_id'];
        $tglBayar = date("Y-m-d");
        $stmtBayar = $conn->prepare("UPDATE detail_pinjaman
            SET status='Sudah Bayar', tgl_bayar=?
            WHERE id_detail=? AND status!='Ditunda'");
        $stmtBayar->bind_param("si", $tglBayar, $idDetail);
        $stmtBayar->execute();
    }
}

// 11. Jika tombol Pelunasan ditekan
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['pelunasan'])) {
    if ($role === 'administrator' || $kode_jab === 'kop_003') {
        $pokok = $pinjaman['pinjaman'] / $pinjaman['angsur_pinjaman'];
        $jasa  = $pinjaman['angsuran_per_bulan'] - $pokok;
        $sisa  = $pinjaman['angsur_pinjaman'] - $lastPaid;
        if ($sisa > 0) {
            $totalPelunasan = ($sisa - 1) * $pokok + ($pokok + $jasa);
            $tglBayar = date("Y-m-d");
            $angsuranke = $pinjaman['angsur_pinjaman'];
            $bulanPelunasan = $lastBulan
                ? date("Y-m-d", strtotime("+1 month", strtotime($lastBulan)))
                : $pinjaman['tgl_pembuatan'];

            $stmtPelunasan = $conn->prepare("INSERT INTO detail_pinjaman
                (no_daftar, id_user, pinjaman, jumlah_angsuran, angsuran_ke, bulan,
                 jumlah_cicilan, tunda_ke, status, tgl_bayar)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $status = 'Sudah Bayar';
            $tundaKe = 0;
            $stmtPelunasan->bind_param("ssdiisdiss",
                $no_daftar, $pinjaman['id_user'], $pinjaman['pinjaman'],
                $pinjaman['angsur_pinjaman'], $angsuranke, $bulanPelunasan,
                $totalPelunasan, $tundaKe, $status, $tglBayar
            );
            $stmtPelunasan->execute();

            // hitung total bayar dari detail_pinjaman
            $sqlTotal = "SELECT COALESCE(SUM(jumlah_cicilan),0) AS total_bayar
                         FROM detail_pinjaman WHERE no_daftar=?";
            $stmtTotal = $conn->prepare($sqlTotal);
            $stmtTotal->bind_param("s", $no_daftar);
            $stmtTotal->execute();
            $resTotal = $stmtTotal->get_result()->fetch_assoc();
            $totalBayar = $resTotal['total_bayar'] ?? 0;
            $stmtTotal->close();

            // update status pinjaman jadi Lunas
            $sqlUpdate = "UPDATE daftar_pinjaman
                          SET jumlah_dibayar=?, sisa_angsuran=0, status_angsuran='Lunas'
                          WHERE no_daftar=?";
            $stmtUpdate = $conn->prepare($sqlUpdate);
            $stmtUpdate->bind_param("is", $totalBayar, $no_daftar);
            $stmtUpdate->execute();
            $stmtUpdate->close();

            $skipRekap = true;
        }
    }
}

// 12. Hitung ulang ringkasan dari detail_pinjaman
if (empty($skipRekap)) {
    $sqlRekap = "UPDATE daftar_pinjaman dp
                 JOIN (
                   SELECT no_daftar,
                          COALESCE(SUM(jumlah_cicilan),0) AS total_bayar,
                          COALESCE(MAX(angsuran_ke),0) AS last_angsuran
                   FROM detail_pinjaman
                   WHERE status='Sudah Bayar'
                   GROUP BY no_daftar
                 ) da ON dp.no_daftar = da.no_daftar
                 SET dp.jumlah_dibayar = da.total_bayar,
                     dp.sisa_angsuran = dp.angsur_pinjaman - da.last_angsuran,
                     dp.status_angsuran = CASE
                          WHEN dp.angsur_pinjaman - da.last_angsuran = 0
                          THEN 'Lunas' ELSE 'Belum Lunas'
                     END";
    $conn->query($sqlRekap);
}

?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <title>Detail Pinjaman</title>
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
        <h1 class="text-uppercase">Detail Pinjaman</h1>
      </div>
    </section>

    <section class="content">
      <div class="container-fluid">
        <div class="card shadow">
          <div class="card-header bg-primary text-white d-flex justify-content-between align-items-center">
            <h5 class="mb-0 text-uppercase flex-grow-1 text-center">Detail Pinjaman</h5>
            <?php if ($pinjaman): ?>
              <span class="ms-3">
                <?= htmlspecialchars($pinjaman['nama']) ?> |
                Rp <?= number_format($pinjaman['pinjaman'], 0, ',', '.') ?> |
                <?= $pinjaman['angsur_pinjaman'] ?> x
              </span>
            <?php endif; ?>
            <div class="d-flex gap-2 ms-auto">
              <?php if ($bolehGenerate): ?>
                <form method="post" action="">
                  <button type="submit" name="generate" class="btn btn-success btn-sm">➕ Tambah Angsuran</button>
                </form>
              <?php else: ?>
                <button class="btn btn-secondary btn-sm" disabled>➕ Tambah Angsuran</button>
              <?php endif; ?>

              <?php if ($pinjaman['status_angsuran'] !== 'Lunas' 
                        && ($role === 'administrator' || $kode_jab === 'kop_003')): ?>
                <button type="button" class="btn btn-warning btn-sm" data-bs-toggle="modal" data-bs-target="#modalPelunasan">💵 Pelunasan</button>
              <?php else: ?>
                <button class="btn btn-secondary btn-sm" disabled>💵 Pelunasan</button>
              <?php endif; ?>
              <a href="../keanggotaan/daftar_pinjaman.php" class="btn btn-secondary btn-sm">⬅️ Kembali</a>
            </div>
          </div>

          <div class="table-responsive">
            <table class="table table-striped table-bordered table-sm align-middle">
              <thead class="table-dark text-center">
                <tr>
                  <th>Angsuran Ke</th>
                  <th>Bulan</th>
                  <th>Jumlah Cicilan</th>
                  <th>Tunda Ke</th>
                  <th>Status</th>
                  <th>Tanggal Bayar</th>
                  <th>Aksi</th>
                </tr>
              </thead>
              <tbody>
                <?php while($row = $resDetail->fetch_assoc()): ?>
                  <tr>
                    <td class="text-center"><?= $row['angsuran_ke'] ?></td>
                    <td class="text-center"><?= date('F Y', strtotime($row['bulan'])) ?></td>
                    <td class="text-end"><?= number_format($row['jumlah_cicilan'],0,',','.') ?></td>
                    <td class="text-center"><?= $row['tunda_ke'] ?: 0 ?></td>
                    <td class="text-center">
                      <?php
                        if ($row['status'] === 'Ditunda') {
                          echo '<span class="text-warning">Ditunda</span>';
                        } elseif ($row['status'] === 'Sudah Bayar') {
                          echo '<span class="text-success">Sudah Bayar</span>';
                        } else {
                          echo '<span class="text-danger">Belum Bayar</span>';
                        }
                      ?>
                    </td>
                    <td class="text-center"><?= $row['tgl_bayar'] ?: '-' ?></td>
                    <td class="text-center">
                      <?php if($row['status'] == 'Belum Bayar' 
                                && ($role === 'administrator' || $kode_jab === 'kop_003')): ?>
                        <form method="post" action="">
                          <input type="hidden" name="bayar_id" value="<?= $row['id_detail'] ?>">
                          <button type="submit" class="btn btn-success btn-sm">Bayar</button>
                        </form>
                      <?php elseif($row['status'] == 'Ditunda'): ?>
                        <button class="btn btn-warning btn-sm" disabled>Ditunda</button>
                      <?php else: ?>
                        <span class="text-muted">-</span>
                      <?php endif; ?>
                    </td>
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
</div>

<!-- Modal Pelunasan -->
<div class="modal fade" id="modalPelunasan" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content">
      <form method="post" action="">
        <div class="modal-header bg-warning">
          <h5 class="modal-title">Rincian Pelunasan</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body">
          <?php
            $pokok = $pinjaman['pinjaman'] / $pinjaman['angsur_pinjaman'];
            $jasa  = $pinjaman['angsuran_per_bulan'] - $pokok;
            $sisa  = $pinjaman['angsur_pinjaman'] - $lastPaid;
            $totalPelunasan = ($sisa - 1) * $pokok + ($pokok + $jasa);
          ?>
          <p>Sisa Angsuran: <?= $sisa ?> kali</p>
          <p>Pokok per bulan: Rp <?= number_format($pokok, 0, ',', '.') ?></p>
          <p>Jasa sekali: Rp <?= number_format($jasa, 0, ',', '.') ?></p>
          <hr>
          <h5>Total Pelunasan: Rp <?= number_format($totalPelunasan, 0, ',', '.') ?></h5>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
          <button type="submit" name="pelunasan" class="btn btn-warning">Konfirmasi Pelunasan</button>
        </div>
      </form>
    </div>
  </div>
</div>

  <!-- Footer -->
  <?php include __DIR__ . '/../layout/footer.php'; ?>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.1/dist/js/bootstrap.bundle.min.js"></script>
<script src="../vendor/adminlte/plugins/jquery/jquery.min.js"></script>
<script src="../vendor/adminlte/plugins/bootstrap/js/bootstrap.bundle.min.js"></script>
<script src="../vendor/adminlte/dist/js/adminlte.min.js"></script>

</body>
</html>

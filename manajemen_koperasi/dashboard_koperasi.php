<?php
session_start();
include __DIR__ . '/auth.php'; // proteksi login
include __DIR__ . '/conf/conf_koperasi.php'; // koneksi DB koperasi

error_reporting(0);
ini_set('display_errors', 0);

// pastikan hanya anggota aktif yang bisa masuk
if ($_SESSION['role'] !== 'anggota') {
    header("Location: login.php?error=unauthorized");
    exit;
}

// ambil nik dari session
$nik       = $_SESSION['id_user'] ?? ($_SESSION['user_login'] ?? '');
$jabatan   = $_SESSION['jabatan'] ?? 'Anggota';
$kode_jab  = $_SESSION['kode_jab'] ?? '';
$isPengurus = in_array($jabatan, ['Ketua','Sekretaris','Bendahara']);

// inisialisasi variabel default
$countAktif         = 0;
$countBaru          = 0;
$countPinjaman      = 0;
$countPinjamanDua   = 0;
$countTundaPinjaman = 0;
$totalSimpanan      = 0;
$totalPinjamanAktif = 0;
$angsuranBulanIni   = 0;
$statusPengajuan    = 'Tidak ada';
$rewardBenefit      = 'Belum tersedia';
$shuInfo            = 'Periode belum ditentukan';
$shuTabunganAnggota = 0;

// hitung jumlah anggota aktif
$resCount = $conn_koperasi->query("SELECT COUNT(*) AS total 
                                   FROM daftar_anggota 
                                   WHERE status_keanggotaan='Aktif'");
if ($resCount) {
    $rowCount   = $resCount->fetch_assoc();
    $countAktif = $rowCount['total'] ?? 0;
}

// hitung anggota baru tahun ini
$tahunSekarang = date('Y');
$resBaru = $conn_koperasi->query("SELECT COUNT(*) AS total 
                                  FROM pengajuan_anggota 
                                  WHERE status='Disetujui' 
                                  AND YEAR(tgl_pembuatan) = '{$tahunSekarang}'
                                  AND no_daftar LIKE 'PAB%'");
if ($resBaru) {
    $rowBaru   = $resBaru->fetch_assoc();
    $countBaru = $rowBaru['total'] ?? 0;
}

// pengajuan anggota baru
$resAnggota = $conn_koperasi->query("SELECT COUNT(*) AS total 
                                      FROM pengajuan_anggota 
                                      WHERE status IN ('Diteruskan','Diproses')");
if ($resAnggota) {
    $rowAnggota   = $resAnggota->fetch_assoc();
    $countAnggota = $rowAnggota['total'] ?? 0;
}

// pengajuan pinjaman baru
$resPinjaman = $conn_koperasi->query("SELECT COUNT(*) AS total 
                                      FROM pengajuan_pinjaman 
                                      WHERE status IN ('Diteruskan','Diproses')");
if ($resPinjaman) {
    $rowPinjaman   = $resPinjaman->fetch_assoc();
    $countPinjaman = $rowPinjaman['total'] ?? 0;
}

// pengajuan pinjaman dua
$resCheckDua = $conn_koperasi->query("SHOW TABLES LIKE 'pengajuan_pinjaman_dua'");
if ($resCheckDua && $resCheckDua->num_rows > 0) {
    $resPinjamanDua = $conn_koperasi->query("SELECT COUNT(*) AS total 
                                             FROM pengajuan_pinjaman_dua 
                                             WHERE status IN ('Diteruskan','Diproses')");
    if ($resPinjamanDua) {
        $rowPinjamanDua   = $resPinjamanDua->fetch_assoc();
        $countPinjamanDua = $rowPinjamanDua['total'] ?? 0;
    }
}

// pengajuan tunda pinjaman
$resCheckTunda = $conn_koperasi->query("SHOW TABLES LIKE 'pengajuan_tunda_pinjaman'");
if ($resCheckTunda && $resCheckTunda->num_rows > 0) {
    $resTunda = $conn_koperasi->query("SELECT COUNT(*) AS total 
                                       FROM pengajuan_tunda_pinjaman 
                                       WHERE status IN ('Diteruskan','Diproses')");
    if ($resTunda) {
        $rowTunda          = $resTunda->fetch_assoc();
        $countTundaPinjaman = $rowTunda['total'] ?? 0;
    }
}

// ==============================
// Ambil periode aktif dari periode_hitung
// ==============================
$periodeAktif = $conn_koperasi->query("SELECT id_periode, tahun, awal, akhir, is_final 
                                       FROM periode_hitung 
                                       WHERE status='Aktif' 
                                       ORDER BY id_periode DESC LIMIT 1");
$rowPeriode   = $periodeAktif ? $periodeAktif->fetch_assoc() : null;

// ==============================
// Data pribadi anggota dari detail_bulanan_koperasi
// ==============================
if (!$rowPeriode) {
    // Jika periode belum ditentukan → semua default 0
    $totalSimpanan     = 0;
    $totalPinjamanAktif= 0;
    $angsuranBulanIni  = 0;
    $statusPengajuan   = 'Tidak ada';

    $shuInfo            = 'Periode belum ditentukan';
    $rewardBenefit      = '';
    $shuTabunganAnggota = 0;

} else {
    $periodeAwal  = $rowPeriode['awal'];
    $periodeAkhir = $rowPeriode['akhir'];
    $periodeTahun = $rowPeriode['tahun'];

    // Total simpanan pribadi
    $resSimpanan = $conn_koperasi->query("SELECT SUM(tabungan) AS total 
                                          FROM detail_bulanan_koperasi 
                                          WHERE id_user = '".$conn_koperasi->real_escape_string($nik)."' 
                                          AND periode_bulan BETWEEN DATE_FORMAT('$periodeAwal','%Y-%m') 
                                                                AND DATE_FORMAT('$periodeAkhir','%Y-%m')");
    if ($resSimpanan) {
        $rowSimpanan   = $resSimpanan->fetch_assoc();
        $totalSimpanan = $rowSimpanan['total'] ?? 0;
    }

    // Pinjaman aktif pribadi
    $resPinjamanAktif = $conn_koperasi->query("SELECT 
                                                SUM(CASE WHEN angsuran_pinjaman > 0 THEN 1 ELSE 0 END) 
                                                + SUM(CASE WHEN angsuran_pinjaman_dua > 0 THEN 1 ELSE 0 END) AS total
                                              FROM detail_bulanan_koperasi
                                              WHERE id_user = '".$conn_koperasi->real_escape_string($nik)."' 
                                              AND periode_bulan BETWEEN DATE_FORMAT('$periodeAwal','%Y-%m') 
                                                                    AND DATE_FORMAT('$periodeAkhir','%Y-%m')");
    if ($resPinjamanAktif) {
        $rowPinjamanAktif   = $resPinjamanAktif->fetch_assoc();
        $totalPinjamanAktif = $rowPinjamanAktif['total'] ?? 0;
    }

    // Angsuran bulan ini (detail angsuran ke dan jumlah cicilan)
    $resAngsuran = $conn_koperasi->query("SELECT angsuran_ke, jumlah_cicilan
                                          FROM detail_pinjaman
                                          WHERE id_user = '".$conn_koperasi->real_escape_string($nik)."' 
                                            AND MONTH(tgl_bayar) = MONTH(CURDATE())
                                            AND YEAR(tgl_bayar) = YEAR(CURDATE())
                                            AND status='Sudah Bayar'
                                          ORDER BY angsuran_ke ASC");

    $angsuranBulanIni = [];
    if ($resAngsuran) {
        while ($row = $resAngsuran->fetch_assoc()) {
            $angsuranBulanIni[] = "Ke-".$row['angsuran_ke']." : Rp ".number_format($row['jumlah_cicilan'],0,',','.');
        }
    }

    // Status pengajuan terakhir
    $resPengajuan = $conn_koperasi->query("SELECT status 
                                           FROM pengajuan_pinjaman 
                                           WHERE id_user='".$conn_koperasi->real_escape_string($nik)."' 
                                           ORDER BY tgl_pembuatan DESC LIMIT 1");
    if ($resPengajuan) {
        $rowPengajuan    = $resPengajuan->fetch_assoc();
        $statusPengajuan = $rowPengajuan['status'] ?? 'Tidak ada';
    }

    // Reward/Benefit Koperasi (dasar dari periode_hitung)
    $fmtTanggal = new IntlDateFormatter(
        'id_ID',
        IntlDateFormatter::LONG,
        IntlDateFormatter::NONE,
        'Asia/Jakarta',
        IntlDateFormatter::GREGORIAN
    );

    $tglAwal = $fmtTanggal->format(new DateTime($rowPeriode['awal']));
    $tglAkhir = $fmtTanggal->format(new DateTime($rowPeriode['akhir']));

    $rewardBenefit = "{$tglAwal} s/d {$tglAkhir}";

    $shuTabunganAnggota = 0;
    $shuInfo = 'Perhitungan SHU belum difinalisasi';
    if (($rowPeriode['is_final'] ?? 0) == 1) {
        $shuInfo = 'SHU Periode Berjalan';
        $resShu = $conn_koperasi->query("
              SELECT shu_tabungan
              FROM detail_shu
              WHERE id_user = '".$conn_koperasi->real_escape_string($nik)."'
              AND id_periode = '".$rowPeriode['id_periode']."'
              LIMIT 1
        ");
        if ($resShu && $resShu->num_rows > 0) {
            $rowShu = $resShu->fetch_assoc();
            $shuTabunganAnggota = $rowShu['shu_tabungan'] ?? 0;
        }
    }
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <title>Dashboard Koperasi</title>
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
    <section class="content-header">
      <div class="container-fluid">
        <h1>Dashboard Koperasi - <?= htmlspecialchars($jabatan) ?></h1>
      </div>
    </section>

    <section class="content">
      <div class="container-fluid">
        <!-- Card sambutan -->
        <div class="card shadow mb-4">
          <div class="card-body">
            Selamat datang, <strong><?= htmlspecialchars($_SESSION['nama_pegawai'] ?? 'Anggota') ?> </strong>👋  
            <p>
              <?php if ($isPengurus): ?>
                Anda adalah <strong><?= htmlspecialchars($jabatan) ?></strong>, 
                berhak mengelola <strong>TRANSAKSI</strong> dan <strong>LAPORAN KOPERASI</strong>.
              <?php else: ?>
                Anda adalah <strong><?= htmlspecialchars($jabatan) ?></strong>, 
                hanya dapat melihat <strong>TRANSAKSI</strong> dan <strong>PINJAMAN PRIBADI</strong>.
              <?php endif; ?>
            </p>
          </div>
        </div>

        <!-- Deretan kotak statistik -->
        <div class="row">
          <!-- Anggota Aktif -->
          <div class="col-md-6">
            <div class="info-box bg-success shadow">
              <span class="info-box-icon"><i class="fas fa-users"></i></span>
              <div class="info-box-content">
                <span class="info-box-text">Jumlah Anggota Aktif Tahun : <?= $tahunSekarang ?></span>
                <span class="info-box-number"><?= $countAktif ?></span>
              </div>
            </div>
          </div>

          <!-- Anggota Baru Tahun Ini -->
          <div class="col-md-6">
            <div class="info-box bg-primary shadow">
              <span class="info-box-icon"><i class="fas fa-user-plus"></i></span>
              <div class="info-box-content">
                <span class="info-box-text">Anggota Baru Tahun : <?= $tahunSekarang ?></span>
                <span class="info-box-number"><?= $countBaru ?></span>
              </div>
            </div>
          </div>

          <?php if (in_array($_SESSION['kode_jab'] ?? '', ['kop_001','kop_002','kop_003'])): ?>
          <!-- Pengajuan Pinjaman -->
          <div class="col-md-6">
            <div class="info-box bg-warning shadow">
              <span class="info-box-icon"><i class="fas fa-hand-holding-usd"></i></span>
              <div class="info-box-content">
                <span class="info-box-text">Pengajuan Pinjaman Belum Diverifikasi</span>
                <span class="info-box-number"><?= $countPinjaman ?></span>
              </div>
            </div>
          </div>

          <!-- Pengajuan Pinjaman Dua -->
          <div class="col-md-6">
            <div class="info-box bg-danger shadow">
              <span class="info-box-icon"><i class="fas fa-redo"></i></span>
              <div class="info-box-content">
                <span class="info-box-text">Pengajuan Pinjaman Lanjutan Belum Diverifikasi</span>
                <span class="info-box-number"><?= $countPinjamanDua ?></span>
              </div>
            </div>
          </div>

          <!-- Pengajuan Tunda Pinjaman -->
          <div class="col-md-6">
            <div class="info-box bg-info shadow">
              <span class="info-box-icon"><i class="fas fa-clock"></i></span>
              <div class="info-box-content">
                <span class="info-box-text">Tunda Pinjaman Belum Diverifikasi</span>
                <span class="info-box-number"><?= $countTundaPinjaman ?></span>
              </div>
            </div>
          </div>

          <!-- Pendaftaran Anggota Baru -->
          <div class="col-md-6">
            <div class="info-box bg-primary shadow">
              <span class="info-box-icon"><i class="fas fa-user-plus"></i></span>
              <div class="info-box-content">
                <span class="info-box-text">Pendaftaran Anggota Baru Belum Diverifikasi</span>
                <span class="info-box-number"><?= $countAnggota ?></span>
              </div>
            </div>
          </div>
          <?php endif; ?>

          <?php if (in_array($kode_jab, ['kop_001','kop_002','kop_003','kop_004','kop_005'])): ?>
          <!-- Total Tabungan -->
          <div class="col-md-3">
            <div class="info-box bg-success shadow">
              <span class="info-box-icon"><i class="fas fa-piggy-bank"></i></span>
              <div class="info-box-content">
                <span class="info-box-text">Total Tabungan</span>
                <span class="info-box-number"><?= number_format($totalSimpanan,0,',','.') ?></span>
              </div>
            </div>
          </div>

          <!-- Pinjaman Aktif -->
          <div class="col-md-3">
            <div class="info-box bg-warning shadow">
              <span class="info-box-icon"><i class="fas fa-hand-holding-usd"></i></span>
              <div class="info-box-content">
                <span class="info-box-text">Pinjaman Aktif</span>
                <span class="info-box-number"><?= $totalPinjamanAktif ?></span>
              </div>
            </div>
          </div>

          <!-- Riwayat Angsuran Bulan Ini -->
          <div class="col-md-3">
            <div class="info-box bg-info shadow">
              <span class="info-box-icon"><i class="fas fa-calendar-check"></i></span>
              <div class="info-box-content">
                <span class="info-box-text">Angsuran Bulan Ini</span>
                <span class="info-box-number">
                  <?= !empty($angsuranBulanIni) ? implode('<br>', $angsuranBulanIni) : 'Belum ada' ?>
                </span>
              </div>
            </div>
          </div>

          <!-- Status Pengajuan Terakhir -->
          <div class="col-md-3">
            <div class="info-box bg-primary shadow">
              <span class="info-box-icon"><i class="fas fa-file-alt"></i></span>
              <div class="info-box-content">
                <span class="info-box-text">Status Pengajuan Terakhir</span>
                <span class="info-box-number"><?= htmlspecialchars($statusPengajuan) ?></span>
              </div>
            </div>
          </div>

          <!-- Reward/SHU Koperasi -->
          <div class="col-md-12">
            <div class="info-box bg-secondary shadow">
              <span class="info-box-icon"><i class="fas fa-gift"></i></span>
              <div class="info-box-content">
                <span class="info-box-text">
                  <strong>Reward / SHU Koperasi : </strong><br>
                  <?= htmlspecialchars((string)($shuInfo ?? '')) ?><br>
                  <?= $rewardBenefit ?>
                </span>
                <span class="info-box-number">
                <?php if (($rowPeriode['is_final'] ?? 0) == 1): ?>
                  Rp <?= number_format($shuTabunganAnggota,0,',','.') ?>
                <?php else: ?>
                  Menunggu Finalisasi
                <?php endif; ?>
                </span>
              </div>
            </div>
            </div>
          </div>
          <?php endif; ?>

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
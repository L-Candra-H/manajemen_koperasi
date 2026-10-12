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

$id_periode = (int)($_GET['id_periode'] ?? 0);

if ($id_periode <= 0) {
    die("Periode tidak valid");
}

// PERIODE
$qPeriode = $conn->query("SELECT *
                          FROM periode_hitung
                          WHERE id_periode = '{$id_periode}'
                          LIMIT 1");
$periode = $qPeriode->fetch_assoc();

if (!$periode) {
    die("Periode tidak ditemukan");
}

// RUMUS SHU
$qRumus = $conn->query("SELECT * 
                        FROM rumus_shu
                        ORDER BY id_rumus DESC
                        LIMIT 1");
$rumus = $qRumus->fetch_assoc();

$persenAdministrasi = (float)$rumus['persen_administrasi'];
$persenDibagi       = (float)$rumus['persen_dibagi'];
$persenPinjaman     = (float)$rumus['persen_pinjaman'];
$persenAnggota      = (float)$rumus['persen_anggota'];

$aktifPinjamanDua = $rumus['aktif_pinjaman_dua'] ?? 'T';

// JASA
$qJasa = $conn->query("SELECT *
                       FROM jasa_pinjam
                       ORDER BY kode_jasa ASC
                       LIMIT 1");
$jasaRow = $qJasa->fetch_assoc();
$persenJasa = (float)$jasaRow['jasa'];

// BULAN DINAMIS
$bulanList = [];
$mulai = new DateTime($periode['awal']);
for ($i = 0; $i < 12; $i++) {
    $bulanList[] = ['ym' => $mulai->format('Y-m'),
                    'label' => $mulai->format('M Y')];
    $mulai->modify('+1 month');
}

$awalPeriode  = $bulanList[0]['ym'];
$akhirPeriode = $bulanList[11]['ym'];

// ANGGOTA AKTIF
$sqlAnggota = "SELECT di.no_anggota, di.id_user, di.tabungan, pg.nama, jk.jab AS jabatan_koperasi
               FROM data_induk_koperasi di
               LEFT JOIN {$db_induk}.pegawai pg
                    ON di.id_user = pg.nik
               LEFT JOIN jabatan_koperasi jk
                    ON di.kode_jab = jk.kode_jab
               WHERE di.status_keanggotaan='Aktif'
               ORDER BY di.no_anggota ASC";

$resAnggota = $conn->query($sqlAnggota);
$dataAnggota = [];
$grandTotalJasa = 0;
$totalData = $resAnggota ? mysqli_num_rows($resAnggota) : 0;

while ($agt = $resAnggota->fetch_assoc()) {
    $idUser = $agt['id_user'];
    $qPinj = $conn->query("SELECT *
                           FROM daftar_pinjaman
                           WHERE id_user='$idUser'
                           ORDER BY no_daftar DESC
                           LIMIT 1");
    $pinj = $qPinj->fetch_assoc();
    $qPinj2 = $conn->query("SELECT *
                            FROM daftar_pinjaman_dua
                            WHERE id_user='$idUser'
                            ORDER BY no_daftar DESC
                            LIMIT 1");
    $pinj2 = $qPinj2->fetch_assoc();
    $detailBulanan = [];
    $totalJasaPinjaman  = 0;
    $totalJasaPinjaman2 = 0;
    $qBulanan = $conn->query("SELECT *
                              FROM detail_bulanan_koperasi
                              WHERE id_user='$idUser'
                              AND periode_bulan BETWEEN '$awalPeriode'
                              AND '$akhirPeriode'
                              ORDER BY periode_bulan ASC");
    while ($d = $qBulanan->fetch_assoc()) {
        $jasaBulanan = 0;
        $jasaBulanan2 = 0;
        if (!empty($pinj)) {$jasaBulanan = $pinj['pinjaman'] * ($persenJasa / 100);}
        if ($aktifPinjamanDua == 'Y' && !empty($pinj2) ) {$jasaBulanan2 = $pinj2['pinjaman'] * ($persenJasa / 100); }

        $totalJasaPinjaman += $jasaBulanan;
        $totalJasaPinjaman2 += $jasaBulanan2;

        $detailBulanan[$d['periode_bulan']] = [
                          'jasa'       => $jasaBulanan,
                          'jasa2'      => $jasaBulanan2 ];
    }

    $totalJasaShu = $totalJasaPinjaman;

    if ($aktifPinjamanDua == 'Y') {$totalJasaShu += $totalJasaPinjaman2;}

    $grandTotalJasa += $totalJasaShu;

    $agt['bulan_detail']        = $detailBulanan;
    $agt['total_jasa']          = $totalJasaShu;
    $agt['total_jasa_1']        = $totalJasaPinjaman;
    $agt['total_jasa_2']        = $totalJasaPinjaman2;
    $agt['punya_pinjaman']      = ($totalJasaPinjaman > 0);

    $dataAnggota[] = $agt;
}

$totalAnggotaAktif = count($dataAnggota);

$totalJasaPeminjam = 0;

foreach ($dataAnggota as $row) {
    if ($row['punya_pinjaman']) {$totalJasaPeminjam += $row['total_jasa'];}
}

$shuAdministrasiPool  = $grandTotalJasa * ($persenAdministrasi / 100);
$shuDibagiPool        = $grandTotalJasa * ($persenDibagi / 100);
$poolShuPinjaman      = $shuDibagiPool * ($persenPinjaman / 100);
$poolShuAnggota       = $shuDibagiPool * ($persenAnggota / 100);

$shuAnggotaPerOrang = 0;

if ($totalAnggotaAktif > 0) {$shuAnggotaPerOrang = $poolShuAnggota / $totalAnggotaAktif;

}

// FINALISASI DATA PER ANGGOTA
foreach ($dataAnggota as $key => $row) {
    $shuPinjaman = 0;
    if ($row['punya_pinjaman'] && $totalJasaPeminjam > 0 ) {
        $shuPinjaman = ($row['total_jasa']/ $totalJasaPeminjam ) * $poolShuPinjaman;
    }

    $shuAnggota                             = $shuAnggotaPerOrang;
    $totalShu                               = $shuPinjaman + $shuAnggota;
    $shuTabungan                            = $totalShu + $row['tabungan'];
    $dataAnggota[$key]['shu_pinjaman']      = $shuPinjaman;
    $dataAnggota[$key]['shu_anggota']       = $shuAnggota;
    $dataAnggota[$key]['total_shu']         = $totalShu;
    $dataAnggota[$key]['shu_tabungan']      = $shuTabungan;
}

$totalJasaBulanan  = [];
$totalJasaBulanan2 = [];

foreach($bulanList as $b){
    $totalJasaBulanan[$b['ym']]   = 0;
    $totalJasaBulanan2[$b['ym']]  = 0;
}

$totalTabungan      = 0;
$totalShuPinjaman   = 0;
$totalShuAnggota    = 0;
$totalShuKoperasi = 0;
$totalShuTabungan   = 0;

foreach($dataAnggota as $row){
    $totalTabungan      += $row['tabungan'];
    $totalShuPinjaman   += $row['shu_pinjaman'];
    $totalShuAnggota    += $row['shu_anggota'];
    $totalShuKoperasi   += $row['total_shu'];
    $totalShuTabungan   += $row['shu_tabungan'];
    foreach($bulanList as $b){
        $ym                     = $b['ym'];
        $totalJasaBulanan[$ym]  += ($row['bulan_detail'][$ym]['jasa'] ?? 0);
        $totalJasaBulanan2[$ym] += ($row['bulan_detail'][$ym]['jasa2'] ?? 0);
    }
}

// audit
$cekTotalShu = $poolShuPinjaman + $poolShuAnggota;
number_format($cekTotalShu,0,',','.');
number_format($shuDibagiPool,0,',','.');

$fmtTanggal = new IntlDateFormatter(
      'id_ID',
      IntlDateFormatter::LONG,
      IntlDateFormatter::NONE,
      'Asia/Jakarta',
      IntlDateFormatter::GREGORIAN
);

// simpan ke DB detail_shu
foreach ($dataAnggota as $row) {
    $noAnggota   = $conn->real_escape_string($row['no_anggota']);
    $idUser      = $conn->real_escape_string($row['id_user']);
    $tabungan    = $row['tabungan'];
    $totalJasa   = $row['total_jasa'];
    $shuPinjaman = $row['shu_pinjaman'];
    $shuAnggota  = $row['shu_anggota'];
    $totalShu    = $row['total_shu'];
    $shuTabungan = $row['shu_tabungan'];
    $conn->query("INSERT INTO detail_shu (id_periode, no_anggota, id_user, tabungan,
                                           total_jasa, shu_pinjaman, shu_anggota, total_shu, shu_tabungan)
                  VALUES ('{$id_periode}', '{$noAnggota}', '{$idUser}', '{$tabungan}', '{$totalJasa}',
                          '{$shuPinjaman}', '{$shuAnggota}', '{$totalShu}', '{$shuTabungan}')
                  ON DUPLICATE KEY UPDATE 
                          tabungan      = VALUES(tabungan),
                          total_jasa    = VALUES(total_jasa),
                          shu_pinjaman  = VALUES(shu_pinjaman),
                          shu_anggota   = VALUES(shu_anggota),
                          total_shu     = VALUES(total_shu),
                          shu_tabungan  = VALUES(shu_tabungan)
    ");
}

?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <title>Detail SHU Koperasi</title>
  <!-- AdminLTE & Bootstrap 5 -->
  <link rel="stylesheet" href="../vendor/adminlte/plugins/fontawesome-free/css/all.min.css">
  <link rel="stylesheet" href="../vendor/adminlte/dist/css/adminlte.min.css">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.1/dist/css/bootstrap.min.css">
  <style>
    .table-responsive { overflow-x: auto; }
    table th, table td { white-space: nowrap; }
    .head-jasa1{
      background:#198754 !important;
      color:#fff;
    }
    .head-jasa2{
      background:#fd7e14 !important;
      color:#fff;
    }
    .head-shu{
      background:#0d6efd !important;
      color:#fff;
    }
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
        <h1 class="text-uppercase">Detail SHU Koperasi</h1>
      </div>
    </section>

    <section class="content">
      <div class="container-fluid">
        <div class="card shadow">
          <div class="card-header bg-primary text-white d-flex justify-content-between align-items-center">
            <h5 class="mb-0 text-uppercase flex-grow-1 text-center">Detail SHU Koperasi<br>
                <small class="text-light"><strong><?= strtoupper($periode['nama_periode']) ?></strong></small>
                <span class="badge bg-warning text-dark">Periode : 
                              <?= $fmtTanggal->format(new DateTime($periode['awal'])) ?> s/d 
                              <?= $fmtTanggal->format(new DateTime($periode['akhir'])) ?>
                </small>
            </h5>
            <div class="d-flex gap-2">
              <a href="cetak_laporan_shu.php?id_periode=<?= $id_periode?>"
            		 target="_blank" 
                 class="btn btn-info btn-sm"> 
            		 🖨 Cetak Laporan SHU 
              </a>
              <a href="shu.php" class="btn btn-secondary btn-sm">⬅️ Kembali</a>
            </div>
          </div>

          <div class="table-responsive">
            <table class="table table-striped table-bordered table-sm align-middle">
              <thead class="table-dark text-center">
                <tr>
                    <th rowspan="2">No Anggota</th>
                    <th rowspan="2">Nama</th>
                    <th rowspan="2">Jabatan</th>
                    <th rowspan="2">Tabungan</th>
                    <th colspan="12" class="head-jasa1">Jasa Pinjaman</th>
                    <?php if($aktifPinjamanDua=='Y'): ?>
                      <th colspan="12" class="head-jasa2">Jasa Pinjaman 2</th>
                    <?php else: ?>
                      <th colspan="12" style="background:#6c757d">Jasa Pinjaman 2 (Non AKtif)</th>
                    <?php endif; ?>
                    <th rowspan="2">Total Jasa</th>
                    <th colspan="4" class="head-shu">Total SHU Jasa Dibagi</th>
                    <th rowspan="2">Total SHU</th>
                    <th rowspan="2">SHU + Tabungan</th>
                </tr>
                <tr>
                <?php foreach($bulanList as $b): ?>
                  <th class="head-jasa1"><?= $b['label'] ?></th>
                <?php endforeach; ?>
                <?php foreach($bulanList as $b): ?>
                  <th class="head-jasa2"><?= $b['label'] ?></th>
                <?php endforeach; ?>
                <th class="head-shu">ADMIN</th>
                <th class="head-shu">DIBAGI</th>
                <th class="head-shu">SHU PEMINJAM</th>
                <th class="head-shu">SHU ANGGOTA</th>
                </tr>
              </thead>
              <tbody>
                <?php foreach($dataAnggota as $row): ?>
                    <tr>
                        <td><?= $row['no_anggota'] ?></td>
                        <td><?= $row['nama'] ?></td>
                        <td><?= $row['jabatan_koperasi'] ?></td>
                        <td class="text-end"><?= number_format($row['tabungan'],0,',','.') ?></td>
                        <?php foreach($bulanList as $b): ?>
                          <td class="text-end"><?= number_format($row['bulan_detail'][$b['ym']]['jasa']?? 0,0,',','.') ?></td>
                        <?php endforeach; ?>
                        <?php foreach($bulanList as $b): ?>
                          <td class="text-end"><?php if($aktifPinjamanDua=='Y'){
                            echo number_format($row['bulan_detail'][$b['ym']]['jasa2']?? 0,0,',','.');
                          }else{
                            echo '-';}
                          ?>
                          </td>
                        <?php endforeach; ?>
                        <td class="text-end"><?= number_format($row['total_jasa'],0,',','.') ?></td>
                        <td class="text-end"></td>
                        <td class="text-end"></td>
                        <td class="text-end"><?= number_format($row['shu_pinjaman'],0,',','.') ?></td>
                        <td class="text-end"><?= number_format($row['shu_anggota'],0,',','.') ?></td>
                        <td class="text-end"><?= number_format($row['total_shu'],0,',','.') ?></td>
                        <td class="text-end"><?= number_format($row['shu_tabungan'],0,',','.') ?></td>
                    </tr>
                  <?php endforeach; ?>
              </tbody>
              <tfoot>
                <tr class="table-warning fw-bold">
                  <td class="text-center">TOTAL</td>
                  <td></td>
                  <td></td>
                  <td class="text-end"><?= number_format($totalTabungan,0,',','.') ?></td>
                  <?php foreach($bulanList as $b): ?>
                    <td class="text-end"><?= number_format($totalJasaBulanan[$b['ym']],0,',','.') ?></td>
                  <?php endforeach; ?>
                  <?php foreach($bulanList as $b): ?>
                    <td class="text-end"><?php if($aktifPinjamanDua=='Y'){echo number_format($totalJasaBulanan2[$b['ym']],0,',','.');
                        }else{echo '-';}?></td>
                  <?php endforeach; ?>
                  <td class="text-end"><?= number_format($grandTotalJasa,0,',','.') ?></td>
                  <td class="text-end"><?= number_format($shuAdministrasiPool,0,',','.') ?></td>
                  <td class="text-end"><?= number_format($shuDibagiPool,0,',','.') ?></td>
                  <td class="text-end"><?= number_format($poolShuPinjaman,0,',','.') ?></td>
                  <td class="text-end"><?= number_format($poolShuAnggota,0,',','.') ?></td>
                  <td class="text-end"><?= number_format($totalShuKoperasi,0,',','.') ?></td>
                  <td class="text-end"><?= number_format($totalShuTabungan,0,',','.') ?></td>
                </tr>
              </tfoot>
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

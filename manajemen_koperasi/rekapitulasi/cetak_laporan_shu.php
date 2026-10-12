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

// Batasi akses hanya administrator dan bendahara
$role     = $_SESSION['role']     ?? '';
$kode_jab = $_SESSION['kode_jab'] ?? '';

if ($role !== 'administrator' && $kode_jab !== 'kop_003') {
    die("<p>Akses ditolak. Hanya Administrator dan Bendahara yang dapat mencetak laporan.</p>");
}

$conn     = bukakoneksikoperasi();
$db_induk = $db_name;

$id_periode = (int)($_GET['id_periode'] ?? 0);

if ($id_periode <= 0) {
    die("<p>Periode tidak ditentukan.</p>");
}

$qPeriode = $conn->query("SELECT *
                          FROM periode_hitung
                          WHERE id_periode='{$id_periode}'
                          LIMIT 1
");
$periode = $qPeriode->fetch_assoc();

// Ambil data setting instansi
$q = $conn->query("SELECT * FROM {$db_induk}.setting LIMIT 1");
$setting = $q->fetch_assoc();

$nama_instansi = $setting['nama_instansi'] ?? '';
$alamat        = $setting['alamat_instansi'] ?? '';
$kabupaten     = $setting['kabupaten'] ?? '';
$propinsi      = $setting['propinsi'] ?? '';
$kontak        = $setting['kontak'] ?? '';
$email         = $setting['email'] ?? '';

// Query data anggota + detail bulanan
$sql = "SELECT ds.no_anggota, pg.nama, d.nama AS departemen,
               pg.rekening,
               ds.shu_tabungan
        FROM detail_shu ds
        LEFT JOIN {$db_induk}.pegawai pg ON ds.id_user = pg.nik
        LEFT JOIN {$db_induk}.departemen d ON pg.departemen = d.dep_id
        WHERE ds.id_periode = '{$id_periode}'
        ORDER BY ds.no_anggota ASC";
$res = $conn->query($sql);

if (!$res || $res->num_rows === 0) {
    die("<p>Data tidak ditemukan untuk periode ini.</p>");
}

// ambil bendahara (kode_jab = kop_003)
$qBendahara = $conn->query("SELECT pg.nama 
                            FROM data_induk_koperasi di
                            LEFT JOIN {$db_induk}.pegawai pg ON di.id_user = pg.nik
                            WHERE di.kode_jab='kop_003' LIMIT 1");
$bendahara = $qBendahara->fetch_assoc();
$bendaharaNama = $bendahara['nama'] ?? '-';

// ambil ketua (kode_jab = kop_001)
$qKetua = $conn->query("SELECT pg.nama 
                            FROM data_induk_koperasi di
                            LEFT JOIN {$db_induk}.pegawai pg ON di.id_user = pg.nik
                            WHERE di.kode_jab='kop_001' LIMIT 1");
$ketua = $qKetua->fetch_assoc();
$ketuaNama = $ketua['nama'] ?? '-';

$totalKeseluruhan = 0;
?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <title>Cetak Laporan SHU Koperasi</title>
  <style>
    body { font-family: monospace; }
    hr { border: 1px solid #000; }
@media print {
  @page { size: A4 portrait; margin: 15mm; }
}
table {
  border-collapse: collapse;
  width: 100%;
}
table th, table td {
  border: 1px solid #000;
  padding: 4px 8px;
}
table th {
  background: #eee;
}
.text-right { text-align: right; }
.text-center { text-align: center; }
</style>
</head>
<body onload="window.print()">  <style>
    body { font-family: monospace; }
    hr { border: 1px solid #000; }
    @media print {
      @page { size: A4 portrait; margin: 15mm; }
    }
    table {
      border-collapse: collapse;
      width: 100%;
    }
    table th, table td {
      border: 1px solid #000;
      padding: 4px 8px;
    }
    table th {
      background: #eee;
    }
    .text-right { text-align: right; }
    .text-center { text-align: center; }
  </style>
</head>
<body onload="window.print()">

<div style="text-align:center;">
  <strong><?= htmlspecialchars($nama_instansi) ?></strong><br>
  <?= htmlspecialchars($alamat) ?> - <?= htmlspecialchars($kabupaten) ?> - <?= htmlspecialchars($propinsi) ?><br>
  <?= htmlspecialchars($kontak) ?> - <?= htmlspecialchars($email) ?>
</div>
<hr>
<div style="text-align:center;">
  <strong style="font-size:1.4em;">LAPORAN SHU KOPERASI</strong><br>
  Periode: <?= strtoupper($periode['nama_periode']) ?><br>
  <?= date('d-m-Y',strtotime($periode['awal'])) ?> s/d <?= date('d-m-Y',strtotime($periode['akhir'])) ?>
</div>
<br>

<table>
  <thead>
    <tr>
      <th class="text-center">NO. ANGGOTA</th>
      <th class="text-center">NAMA LENGKAP</th>
      <th class="text-center">DEPARTEMEN</th>
      <th class="text-center">REKENING</th>
      <th class="text-center">TOTAL</th>
    </tr>
  </thead>
  <tbody>
    <?php while ($row = $res->fetch_assoc()): ?>
    <tr>
      <td class="text-center"><?= htmlspecialchars($row['no_anggota']) ?></td>
      <td><?= htmlspecialchars($row['nama']) ?></td>
      <td><?= htmlspecialchars($row['departemen']) ?></td>
      <td><?= htmlspecialchars($row['rekening']) ?></td>
      <td class="text-right"><?= number_format($row['shu_tabungan'] ?? 0,2) ?></td>
    </tr>
    <?php $totalKeseluruhan += ($row['shu_tabungan'] ?? 0); ?>
    <?php endwhile; ?>
    <tr>
      <td colspan="4" class="text-right"><strong>TOTAL SHU + TABUNGAN</strong></td>
      <td class="text-right"><strong><?= number_format($totalKeseluruhan,0,',','.') ?></strong></td>
    </tr>
  </tbody>
</table>

<table style="width:100%; border:none; margin-top:40px;">
    <tr>
        <td style="border:none; width:50%; text-align:left;">
            Malang, <?= date('d F Y') ?><br>
            Bendahara
            <br><br><br>ttd<br><br><br>
            <strong>(<?= htmlspecialchars($bendaharaNama) ?>)</strong>
        </td>

        <td style="border:none; width:50%; text-align:left;">
            Mengetahui,<br>
            Ketua
            <br><br><br>ttd<br><br><br>
            <strong>(<?= htmlspecialchars($ketuaNama) ?>)</strong>
        </td>
    </tr>
</table>

</body>
</html>

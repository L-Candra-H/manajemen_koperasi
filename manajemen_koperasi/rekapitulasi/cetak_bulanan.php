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

$conn = bukakoneksikoperasi();
$db_induk = $db_name;

$periode = $_GET['periode'] ?? null;
$anggota = $_GET['anggota'] ?? null;

// Ambil data setting instansi
$q = $conn->query("SELECT * FROM {$db_induk}.setting LIMIT 1");
$setting = $q->fetch_assoc();

$nama_instansi = $setting['nama_instansi'] ?? '';
$alamat        = $setting['alamat_instansi'] ?? '';
$kabupaten     = $setting['kabupaten'] ?? '';
$propinsi      = $setting['propinsi'] ?? '';
$kontak        = $setting['kontak'] ?? '';
$email         = $setting['email'] ?? '';

// ambil data anggota + detail bulanan
$sql = "SELECT
               di.no_anggota, pg.nama, d.nama AS departemen, pg.bpd, pg.rekening,
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

               db.periode_bulan, db.simpanan_wajib, db.tabungan, db.angsuran_pokok,
               db.angsuran_ke_pokok, db.angsuran_pinjaman, db.pinjaman_ke, 
               db.angsuran_pinjaman_dua, db.pinjaman_dua_ke, db.total_potongan
        FROM detail_bulanan_koperasi db
        JOIN data_induk_koperasi di
            ON db.no_anggota = di.no_anggota
        LEFT JOIN {$db_induk}.pegawai pg
            ON di.id_user = pg.nik
        LEFT JOIN {$db_induk}.departemen d
            ON pg.departemen = d.dep_id
        WHERE db.periode_bulan = '".$conn->real_escape_string($periode)."'";

if ($anggota !== 'all') {
    $sql .= " AND di.no_anggota='".$conn->real_escape_string($anggota)."'";
}
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
$bendaharaNama = $bendahara['nama'] ?? '';
?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <title>Cetak Bulanan Koperasi</title>
  <style>
    body { font-family: monospace; }
    hr { border: 1px solid #000; }
    @media print {
      @page { size: A5 portrait; margin: 15mm; }
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

    .label,
    .sub-label {
      display: inline-block;
      width: 170px;
    }

    .colon {
      display: inline-block;
      width: 15px;
      text-align: center;
    }

    .value {
      display: inline-block;
    }

    .value-text {
      display: inline-block;
    }

    .value-num {
      display: inline-block;
      min-width: 150px;
      text-align: right;
    }

    .with-line {
      border-top: 1px solid #000;
      padding-top: 2px;
    }
  </style>
</head>
<body onload="window.print()">

<?php
// Jika cetak semua anggota
if ($anggota === 'all') {
    while ($row = $res->fetch_assoc()) {
        $periodeFormat = !empty($row['periode_bulan'])
            ? strtoupper(date('F Y', strtotime($row['periode_bulan'].'-01')))
            : '';

        echo "<div style='page-break-after:always'>";

        echo "<div style='text-align:center;'>
                <strong>".htmlspecialchars($nama_instansi)."</strong><br>
                ".htmlspecialchars($alamat)." - ".htmlspecialchars($kabupaten)." - ".htmlspecialchars($propinsi)."<br>
                ".htmlspecialchars($kontak)." - ".htmlspecialchars($email)."
              </div><hr>";

        echo "<div style='text-align:center;'>
                <strong style='font-size:1.4em;'>POTONGAN KOPERASI</strong><br>
                Periode: ".$periodeFormat."
              </div><br>";

        echo "<span class='label'>No. ANGGOTA</span><span class='colon'>:</span><span class='value'>".htmlspecialchars($row['no_anggota'])."</span><br>
              <span class='label'>NAMA LENGKAP</span><span class='colon'>:</span><span class='value'>".htmlspecialchars($row['nama'])."</span><br>
              <span class='label'>DEPARTEMEN</span><span class='colon'>:</span><span class='value'>".htmlspecialchars($row['departemen'])."</span><br>
              <span class='label'>REKENING</span><span class='colon'>:</span><span class='value'>".htmlspecialchars($row['bpd'])." - ".htmlspecialchars($row['rekening'])."</span><br><br>";

        echo "<strong>SALDO SIMPANAN ANGGOTA:</strong><br>
              <span class='label'>Dana Pensiun</span><span class='colon'>:</span><span class='value-num'>".number_format($row['dana_pensiun'] ?? 0,2)."</span><br>
              <span class='label'>Simpanan Pokok</span><span class='colon'>:</span><span class='value-num'>".number_format($row['total_simpanan_pokok'] ?? 0,2)."</span><br>
              <span class='label'>Simpanan Wajib</span><span class='colon'>:</span><span class='value-num'>".number_format($row['total_simpanan_wajib'] ?? 0,2)."</span><br>
              <span class='label'>Tabungan</span><span class='colon'>:</span><span class='value-num'>".number_format($row['total_tabungan'] ?? 0,2)."</span><br>";

        echo "<div style='height:18px;'></div>";
        echo "<hr style='margin:0;'>";
        echo "<div style='height:12px;'></div>";

        echo "<strong>RINCIAN POTONGAN:</strong><br>
              <span class='label'>1. Simpanan Wajib</span><span class='colon'>:</span><span class='value-num'>".number_format($row['simpanan_wajib'] ?? 0,2)."</span><br>
              <span class='label'>2. Tabungan</span><span class='colon'>:</span><span class='value-num'>".number_format($row['tabungan'] ?? 0,2)."</span><br>
              <span class='label'>3. Simpanan Pokok</span><span class='colon'>:</span><span class='value-num'>".number_format($row['angsuran_pokok'] ?? 0,2)."</span><br>
              <span class='label'>&nbsp;&nbsp;&nbsp;angsuran ke</span><span class='colon'>:</span><span class='value-text'>".($row['angsuran_ke_pokok'] ?? 0)."</span><br>
              <span class='label'>4. Pinjaman</span><span class='colon'>:</span><span class='value-num'>".number_format($row['angsuran_pinjaman'] ?? 0,2)."</span><br>
              <span class='label'>&nbsp;&nbsp;&nbsp;angsuran ke</span><span class='colon'>:</span><span class='value-text'>".($row['pinjaman_ke'] ?? 0)."</span><br>
              <span class='label'>5. Pinjaman 2</span><span class='colon'>:</span><span class='value-num'>".number_format($row['angsuran_pinjaman_dua'] ?? 0,2)."</span><br>
              <span class='label'>&nbsp;&nbsp;&nbsp;angsuran ke</span><span class='colon'>:</span><span class='value-text'>".($row['pinjaman_dua_ke'] ?? 0)."</span><br>
              <span class='label'><strong>TOTAL</span><span class='colon'>:</span>
              <span class='value-num with-line'>".number_format($row['total_potongan'] ?? 0,2)."</strong></span><br><br>";

        echo "Malang, ".date('d F Y')."<br>
              Bendahara<br><br><br>ttd<br><br>
              <strong>(".htmlspecialchars($bendaharaNama).")</strong>";

        echo "</div>";
    }
} else {
    // Cetak satu anggota
    $data = $res->fetch_assoc();
    $periodeFormat = !empty($data['periode_bulan'])
        ? strtoupper(date('F Y', strtotime($data['periode_bulan'].'-01')))
        : '';

    echo "<div style='text-align:center;'>
            <strong>".htmlspecialchars($nama_instansi)."</strong><br>
            ".htmlspecialchars($alamat)." - ".htmlspecialchars($kabupaten)." - ".htmlspecialchars($propinsi)."<br>
            ".htmlspecialchars($kontak)." - ".htmlspecialchars($email)."
          </div><hr>";

    echo "<div style='text-align:center;'>
            <strong style='font-size:1.4em;'>POTONGAN KOPERASI</strong><br>
            Periode: ".$periodeFormat."
          </div><br>";

    echo "<span class='label'>No. ANGGOTA</span><span class='colon'>:</span><span class='value'>".htmlspecialchars($data['no_anggota'])."</span><br>
          <span class='label'>NAMA LENGKAP</span><span class='colon'>:</span><span class='value'>".htmlspecialchars($data['nama'])."</span><br>
          <span class='label'>DEPARTEMEN</span><span class='colon'>:</span><span class='value'>".htmlspecialchars($data['departemen'])."</span><br>
          <span class='label'>REKENING</span><span class='colon'>:</span><span class='value'>".htmlspecialchars($data['bpd'])." - ".htmlspecialchars($data['rekening'])."</span><br><br>";

    echo "<strong>SALDO SIMPANAN ANGGOTA:</strong><br>
          <span class='label'>Dana Pensiun</span><span class='colon'>:</span><span class='value-num'>".number_format($data['dana_pensiun'] ?? 0,2)."</span><br>
          <span class='label'>Simpanan Pokok</span><span class='colon'>:</span><span class='value-num'>".number_format($data['total_simpanan_pokok'] ?? 0,2)."</span><br>
          <span class='label'>Simpanan Wajib</span><span class='colon'>:</span><span class='value-num'>".number_format($data['total_simpanan_wajib'] ?? 0,2)."</span><br>
          <span class='label'>Tabungan</span><span class='colon'>:</span><span class='value-num'>".number_format($data['total_tabungan'] ?? 0,2)."</span><br>";

    echo "<div style='height:18px;'></div>";
    echo "<hr style='margin:0;'>";
    echo "<div style='height:12px;'></div>";

    echo "<strong>RINCIAN POTONGAN:</strong><br>
          <span class='label'>1. Simpanan Wajib</span><span class='colon'>:</span><span class='value-num'>".number_format($data['simpanan_wajib'] ?? 0,2)."</span><br>
          <span class='label'>2. Tabungan</span><span class='colon'>:</span><span class='value-num'>".number_format($data['tabungan'] ?? 0,2)."</span><br>
          <span class='label'>3. Simpanan Pokok</span><span class='colon'>:</span><span class='value-num'>".number_format($data['angsuran_pokok'] ?? 0,2)."</span><br>
          <span class='label'>&nbsp;&nbsp;&nbsp;angsuran ke</span><span class='colon'>:</span><span class='value-text'>".($data['angsuran_ke_pokok'] ?? 0)."</span><br>
          <span class='label'>4. Pinjaman</span><span class='colon'>:</span><span class='value-num'>".number_format($data['angsuran_pinjaman'] ?? 0,2)."</span><br>
          <span class='label'>&nbsp;&nbsp;&nbsp;angsuran ke</span><span class='colon'>:</span><span class='value-text'>".($data['pinjaman_ke'] ?? 0)."</span><br>
          <span class='label'>5. Pinjaman 2</span><span class='colon'>:</span><span class='value-num'>".number_format($data['angsuran_pinjaman_dua'] ?? 0,2)."</span><br>
          <span class='label'>&nbsp;&nbsp;&nbsp;angsuran ke</span><span class='colon'>:</span><span class='value-text'>".($data['pinjaman_dua_ke'] ?? 0)."</span><br>
          <span class='label'><strong>TOTAL</span><span class='colon'>:</span>
          <span class='value-num with-line'>".number_format($data['total_potongan'] ?? 0,2)."</strong></span><br><br>";

    echo "Malang, ".date('d F Y')."<br>
          Bendahara<br><br><br>ttd<br><br><br>
          <strong>(".htmlspecialchars($bendaharaNama).")</strong>";
}
?>
</body>
</html>

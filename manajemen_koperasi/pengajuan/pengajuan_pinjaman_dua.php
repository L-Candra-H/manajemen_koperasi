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
$id_user = $_SESSION['id_user'];

// cek pengajuan terakhir di pengajuan_pinjaman_dua
$cek = $conn->prepare("SELECT status, no_daftar 
                       FROM pengajuan_pinjaman_dua 
                       WHERE id_user=? 
                       ORDER BY tgl_pembuatan DESC 
                       LIMIT 1");
$cek->bind_param("s", $id_user);
$cek->execute();
$hasil = $cek->get_result()->fetch_assoc();
$cek->close();

$blokirPengajuan = false;
if ($hasil && in_array($hasil['status'], ['Diteruskan','Diproses','Direkap','Disetujui'])) {
    $blokirPengajuan = true;

    $pesan = "<div class='alert alert-warning text-center'>
                ⚠️ Pengajuan pinjaman dua dengan nomor<b>{$hasil['no_daftar']}</b>
                sedang dalam status<b>{$hasil['status']}</b>.
                <br>Anda tidak dapat mengajukan kembali.
              </div>";
}

// cek periode aktif
$today = date("Y-m-d");
$sqlPeriode = "SELECT * FROM periode_pengaturan
               WHERE nama_fitur='pengajuan_pinjaman_dua'
               AND status='Aktif'
               AND tanggal_awal <= '$today'
               AND tanggal_akhir >= '$today'";
$resPeriode = $conn->query($sqlPeriode);
$periodeAktif = $resPeriode->num_rows > 0;

// generate nomor pendaftaran
$tgl = date("Ymd");
$q = $conn->query("SELECT COUNT(*) AS jml 
                   FROM pengajuan_pinjaman_dua 
                   WHERE DATE(tgl_pembuatan)=CURDATE()");
$row = $q->fetch_assoc();
$urut = str_pad($row['jml']+1, 4, "0", STR_PAD_LEFT);
$no_daftar = "PJD" . $tgl . $urut;

// proses simpan
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$blokirPengajuan && $periodeAktif) {
    $pinjaman          = $_POST['pinjaman'];
    $angsur_pinjaman   = 10; // fixed
    $angsuran_per_bulan= $_POST['angsuran_per_bulan'];
    $keterangan        = $_POST['keterangan'] ?? null;

    $cekFinal = $conn->prepare("SELECT no_daftar 
                                FROM pengajuan_pinjaman_dua
                                WHERE id_user = ?
                                    AND status IN ('Diteruskan', 'Diproses', 'Direkap', 'Disetujui')
                                LIMIT 1");
    $cekFinal->bind_param("s", $id_user);
    $cekFinal->execute();
    if ($cekFinal->get_result()->num_rows > 0) {
        die("Masih terdapat pengajuan pinjaman dua yang aktif.");
    } 

    $stmt = $conn->prepare("INSERT INTO pengajuan_pinjaman_dua 
        (no_daftar, id_user, pinjaman, angsur_pinjaman, angsuran_per_bulan, tgl_pembuatan, keterangan, status) 
        VALUES (?, ?, ?, ?, ?, CURDATE(), ?, 'Diteruskan')");
    $stmt->bind_param("ssdids", $no_daftar, $id_user, $pinjaman, $angsur_pinjaman, $angsuran_per_bulan, $keterangan);

    if ($stmt->execute()) {
        header("Location: ../index.php");
        exit;
    } else {
        $pesan = "❌ Gagal menyimpan pengajuan pinjaman: " . $stmt->error;
    }
    $stmt->close();
}

// ambil data pegawai
$sql = "SELECT p.nik, p.nama, d.nama AS departemen
        FROM {$db_name}.pegawai p
        JOIN {$db_name}.departemen d ON p.departemen = d.dep_id
        WHERE p.nik = ? LIMIT 1";
$stmt = $conn->prepare($sql);
$stmt->bind_param("s", $id_user);
$stmt->execute();
$data = $stmt->get_result()->fetch_assoc();
$stmt->close();

// ambil jasa pinjam
$qJasa = $conn->query("SELECT kode_jasa, jasa FROM jasa_pinjam ORDER BY kode_jasa ASC");
$jasaOptions = [];
while($row = $qJasa->fetch_assoc()) {
    $jasaOptions[$row['kode_jasa']] = $row['jasa'];
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <title>Pengajuan Pinjaman Dua</title>
  <link rel="stylesheet" href="../vendor/adminlte/dist/css/adminlte.min.css">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <style>
    .register-box { width:480px; margin:2% auto; }
    .form-control[readonly] { background-color:#e9ecef; color:#6c757d; }
  </style>
</head>
<body class="hold-transition register-page" 
      style="background-image:url('../vendor/adminlte/dist/img/9429.png'); 
             background-size:cover; 
             background-position:center;">

<div class="register-box">
  <div class="card card-outline card-primary">
    <div class="card-header text-center">
      <h5 class="mb-0">FORMULIR PENGAJUAN PINJAMAN DUA</h5>
    </div>
    <div class="card-body">
      <p class="login-box-msg">
        <strong><?= htmlspecialchars($data['nama'] ?? ($_SESSION['role']==='administrator' ? 'Administrator' : '')) ?></strong> 👋 
        <br>Silakan lengkapi data untuk<br><strong>PENGAJUAN PINJAMAN</strong>.
      </p>

      <?php if (!$periodeAktif || !empty($hasil) || !empty($pesan)): ?>
        <div class="alert 
            <?= !$periodeAktif ? 'alert-danger' : (!empty($hasil) ? 'alert-warning' : 'alert-danger') ?>">
          <?php if (!$periodeAktif): ?>
            ❌ Periode pengajuan pinjaman dua belum aktif atau sudah berakhir.
          <?php elseif (!empty($hasil)): ?>
            ⚠️ Anda sudah mengajukan pinjaman dengan nomor
            <b><?= htmlspecialchars($hasil['no_daftar']) ?></b> dan statusnya 
            <b><?= htmlspecialchars($hasil['status']) ?></b>.
          <?php elseif (!empty($pesan)): ?>
            <?= htmlspecialchars($pesan) ?>
          <?php endif; ?>
        </div>
        <div class="text-center mt-3">
          <a href="../index.php" class="btn btn-danger">⬅️ Kembali</a>
        </div>
      <?php else: ?>
        <form method="post" action="">
          <input type="hidden" name="no_daftar" value="<?= $no_daftar; ?>">
          <input type="hidden" name="id_user" value="<?= $id_user; ?>">

          <!-- Jumlah Pinjaman -->
          <div class="mb-3">
            <label class="form-label">Jumlah Pinjaman</label>
            <input type="number" name="pinjaman" id="pinjaman" class="form-control" required>
          </div>

          <!-- Angsuran (fixed 10 bulan) -->
          <div class="mb-3">
            <label class="form-label">Angsuran</label>
            <p class="fw-bold">10 Bulan</p>
            <input type="hidden" name="angsur_pinjaman" value="10">
          </div>

          <!-- Angsuran per Bulan -->
          <div class="mb-3">
            <label class="form-label">Angsuran per Bulan</label>
            <input type="text" id="angsuran_bulan" class="form-control" readonly>
            <input type="hidden" name="angsuran_per_bulan" id="angsuran_per_bulan_hidden">
          </div>

          <!-- Keterangan -->
          <div class="mb-3">
            <label class="form-label">Keterangan</label>
            <textarea name="keterangan" class="form-control" rows="2"></textarea>
          </div>

          <div class="text-center">
            <button type="submit" class="btn btn-success">📤 Ajukan Pinjaman</button>
            <a href="../index.php" class="btn btn-danger">❌ Batal</a>
          </div>
        </form>
      <?php endif; ?>
    </div>
  </div>
</div>

<script>
const jasaPinjamMap = <?= json_encode($jasaOptions, JSON_NUMERIC_CHECK) ?>;
const pinjamanInput = document.getElementById('pinjaman');
const angsuranField = document.getElementById('angsuran_bulan');
const angsuranHidden= document.getElementById('angsuran_per_bulan_hidden');

function hitungAngsuran() {
  const pinjaman = parseFloat(pinjamanInput.value) || 0;
  const lama = 10; // fixed
  const rawJasa = jasaPinjamMap["js_001"] || 0;

  if (pinjaman > 0) {
    const pokokPerBulan = pinjaman / lama;
    const jasaPerBulan  = pinjaman * (rawJasa / 100);
    const perBulan      = Math.round(pokokPerBulan + jasaPerBulan);

    // tampilkan hasil
    angsuranField.value  = perBulan.toLocaleString('id-ID');
    angsuranHidden.value = perBulan;
  } else {
    // reset jika tidak valid
    angsuranField.value  = '';
    angsuranHidden.value = '';
  }
}

// event listener
pinjamanInput.addEventListener('input', hitungAngsuran);
</script>
</body>
</html>

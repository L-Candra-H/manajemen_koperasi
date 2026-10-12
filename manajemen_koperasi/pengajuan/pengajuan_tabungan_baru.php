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
$id_user = $_SESSION['id_user'] ?? null;

// cek pengajuan tabungan terakhir
$cek = $conn->prepare("SELECT status, no_daftar 
                       FROM pengajuan_tabungan_baru 
                       WHERE id_user=? 
                       ORDER BY tgl_pembuatan DESC 
                       LIMIT 1");
$cek->bind_param("s", $id_user);
$cek->execute();
$hasil = $cek->get_result()->fetch_assoc();
$cek->close();

// ambil tabungan lama dari data_induk_koperasi
$tabungan_lama = 0;
$sqlTabunganLama = "SELECT tabungan 
                    FROM data_induk_koperasi 
                    WHERE id_user = ? 
                    LIMIT 1";
$stmt = $conn->prepare($sqlTabunganLama);
$stmt->bind_param("s", $id_user);
$stmt->execute();
$stmt->bind_result($tabungan_lama);
$stmt->fetch();
$stmt->close();

// cek periode aktif
$today = date("Y-m-d");
$sqlPeriode = "SELECT * FROM periode_pengaturan
               WHERE nama_fitur='pengajuan_tabungan_baru'
               AND status='Aktif'
               AND tanggal_awal <= '$today'
               AND tanggal_akhir >= '$today'";
$resPeriode = $conn->query($sqlPeriode);
$periodeAktif = $resPeriode->num_rows > 0;

// generate nomor pendaftaran
$tgl = date("Ymd");
$q = $conn->query("SELECT COUNT(*) AS jml 
                   FROM pengajuan_tabungan_baru 
                   WHERE DATE(tgl_pembuatan)=CURDATE()");
$row = $q->fetch_assoc();
$urut = str_pad($row['jml']+1, 4, "0", STR_PAD_LEFT);
$no_daftar = "PTB" . $tgl . $urut;

// ambil nomor anggota dari tabel data_induk_koperasi
$sqlAnggota = "SELECT no_anggota FROM daftar_anggota WHERE id_user=? LIMIT 1";
$stmtAnggota = $conn->prepare($sqlAnggota);
$stmtAnggota->bind_param("s", $id_user);
$stmtAnggota->execute();
$stmtAnggota->bind_result($noAnggota);
$stmtAnggota->fetch();
$stmtAnggota->close();

// cek apakah anggota baru (nomor anggota diawali tahun sekarang)
$tahunSekarang = date("Y");
$isAnggotaBaru = false;
if (!empty($noAnggota)) {
    // cek apakah no_anggota diawali tahun sekarang
    $isAnggotaBaru = (strpos($noAnggota, $tahunSekarang . '-') === 0);
}

// proses simpan
if ($_SERVER['REQUEST_METHOD'] === 'POST' && empty($hasil) && $periodeAktif && !$isAnggotaBaru) {
    $tabungan_lama = $_POST['tabungan_lama'];
    $tabungan_baru = $_POST['tabungan_baru'];
    $keterangan    = $_POST['keterangan'];

    $stmt = $conn->prepare("INSERT INTO pengajuan_tabungan_baru 
        (no_daftar, id_user, tabungan_lama, tabungan_baru, tgl_pembuatan, keterangan, status) 
        VALUES (?, ?, ?, ?, CURDATE(), ?, 'Diteruskan')");
    $stmt->bind_param("ssdds", $no_daftar, $id_user, $tabungan_lama, $tabungan_baru, $keterangan);

    if ($stmt->execute()) {
        header("Location: ../index.php?notif=sukses");
        exit;
    } else {
        $pesan = "<div class='alert alert-danger'>❌ Gagal menyimpan pengajuan tabungan: " . $stmt->error . "</div>";
    }
    $stmt->close();
} elseif ($isAnggotaBaru) {
    $pesan = "<div class='alert alert-warning text-center'>
                ⚠️ Anggota baru dengan nomor anggota diawali tahun $tahunSekarang tidak dapat mengajukan tabungan baru.
              </div>";
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

if (!$data) {
    $data = ['nama' => '', 'nik' => '', 'departemen' => ''];
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <title>Pengajuan Tabungan Baru</title>
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
      <h5 class="mb-0">FORMULIR PENGAJUAN TABUNGAN BARU</h5>
    </div>
    <div class="card-body">
      <p class="login-box-msg">
        <strong><?= htmlspecialchars($data['nama'] ?? ($_SESSION['role']==='administrator' ? 'Administrator' : '')) ?></strong> 👋 
        <br>Silakan lengkapi data untuk<br><strong>PENGAJUAN TABUNGAN BARU</strong>.
      </p>

      <?php if (!empty($hasil)): ?>
        <div class="alert alert-warning">⚠️ Anda sudah mengajukan tabungan baru dengan nomor
          <b><?= htmlspecialchars($hasil['no_daftar'] ?? '') ?></b> dan statusnya 
          <b><?= htmlspecialchars($hasil['status'] ?? '') ?></b>.
        </div>
        <div class="text-center mt-3">
          <a href="../index.php" class="btn btn-danger">⬅️ Kembali</a>
        </div>
      <?php elseif (!$periodeAktif): ?>
        <div class="alert alert-warning text-center">
          ⚠️ Periode pengajuan tabungan baru belum aktif atau sudah berakhir.
        </div>
        <div class="text-center mt-3">
          <a href="../index.php" class="btn btn-danger">⬅️ Kembali</a>
        </div>
      <?php elseif ($isAnggotaBaru): ?>
        <div class="alert alert-warning text-center">
          ⚠️ Anggota baru dengan nomor anggota diawali tahun <?= $tahunSekarang ?> tidak dapat mengajukan tabungan baru.
        </div>
        <div class="text-center mt-3">
          <a href="../index.php" class="btn btn-danger">⬅️ Kembali</a>
        </div>
      <?php else: ?>
        <?php if (!empty($pesan)) echo $pesan; ?>

        <form method="post" action="">
          <input type="hidden" name="no_daftar" value="<?= htmlspecialchars($no_daftar) ?>">
          <input type="hidden" name="id_user" value="<?= htmlspecialchars($id_user) ?>">

          <!-- Tabungan Lama -->
          <div class="mb-3">
            <label class="form-label">Tabungan Lama</label>
            <input type="number" name="tabungan_lama" 
                   class="form-control" 
                   value="<?= htmlspecialchars($tabungan_lama) ?>" 
                   readonly>
          </div>

          <!-- Tabungan Baru -->
          <div class="mb-3">
            <label class="form-label">Tabungan Baru</label>
            <input type="number" name="tabungan_baru" class="form-control" required>
          </div>

          <!-- Keterangan -->
          <div class="mb-3">
            <label class="form-label">Keterangan (opsional)</label>
            <textarea name="keterangan" class="form-control"></textarea>
          </div>

          <div class="text-center">
            <button type="submit" class="btn btn-success">📤 Ajukan Tabungan</button>
            <a href="../index.php" class="btn btn-danger">❌ Batal</a>
          </div>
        </form>
      <?php endif; ?>
    </div>
  </div>
</div>

</body>
</html>

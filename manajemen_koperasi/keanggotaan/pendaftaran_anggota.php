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
$allowedRoles = ['administrator','ketua','sekretaris','bendahara','anggota','calon'];
if (!in_array($_SESSION['role'], $allowedRoles)) {
    header("Location: ../login.php?error=unauthorized");
    exit;
}

$conn = bukakoneksikoperasi();
$id_user = $_SESSION['id_user'];

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

// ambil nominal simpanan
$qMaster = $conn->query("SELECT simp, nominal FROM simpanan");
$master = [];
while($row = $qMaster->fetch_assoc()) {
    $master[$row['simp']] = $row['nominal'];
}

// cek apakah sudah ada pengajuan terakhir
$cekPengajuan = $conn->prepare("
    SELECT status, no_daftar 
    FROM pengajuan_anggota 
    WHERE id_user=? 
    ORDER BY tgl_pembuatan DESC 
    LIMIT 1
");
$cekPengajuan->bind_param("s", $id_user);
$cekPengajuan->execute();
$resPengajuan = $cekPengajuan->get_result();
$pengajuan = $resPengajuan->fetch_assoc();
$cekPengajuan->close();

// cek apakah sudah anggota aktif
$cekAnggota = $conn->prepare("SELECT id_user 
                              FROM daftar_anggota 
                              WHERE id_user=? AND status_keanggotaan='Aktif'");
$cekAnggota->bind_param("s", $id_user);
$cekAnggota->execute();
$resAnggota = $cekAnggota->get_result();
$isAnggotaAktif = $resAnggota->num_rows > 0;
$cekAnggota->close();

// generate nomor pendaftaran (PAB + YYYYMMDD + 4 digit urut)
$tgl = date("Ymd");
$q = $conn->query("SELECT COUNT(*) AS jml 
                   FROM pengajuan_anggota 
                   WHERE DATE(tgl_pembuatan)=CURDATE()");
$row = $q->fetch_assoc();
$urut = str_pad($row['jml']+1, 4, "0", STR_PAD_LEFT);
$no_daftar = "PAB" . $tgl . $urut;

// === proses simpan ketika klik Daftar ===
if ($_SERVER['REQUEST_METHOD'] === 'POST' && empty($pengajuan) && !$isAnggotaAktif) {
    $simp_pokok   = $_POST['simp_pokok'];
    $angsur_pokok = $_POST['angsur_pokok'];
    $simp_wajib   = $_POST['simp_wajib'];
    $tabungan     = $_POST['tabungan'];

    $stmt = $conn->prepare("INSERT INTO pengajuan_anggota 
        (no_daftar, id_user, simp_pokok, angsur_pokok, simp_wajib, tabungan, tgl_pembuatan, status) 
        VALUES (?, ?, ?, ?, ?, ?, CURDATE(), 'Diteruskan')");
    $stmt->bind_param("ssdidd", $no_daftar, $id_user, $simp_pokok, $angsur_pokok, $simp_wajib, $tabungan);

    if ($stmt->execute()) {
        header("Location: ../login.php");
        exit;
    } else {
        $pesan = "<div class='alert alert-danger'>❌ Gagal menyimpan: " . $stmt->error . "</div>";
    }
    $stmt->close();
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <title>Pendaftaran Anggota Koperasi</title>
  <link rel="stylesheet" href="../vendor/adminlte/dist/css/adminlte.min.css">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <style>
    .register-box { width:480px; margin:2% auto; }
    .form-control[readonly] { background-color:#e9ecef; color:#6c757d; }
  </style>
</head>
<body class="hold-transition register-page" 
      style="background-image:url('../vendor/adminlte/dist/img/21199.jpg'); 
             background-size:cover; 
             background-position:center;">

<div class="register-box">
  <div class="card card-outline card-primary">
    <div class="card-header text-center">
      <h5 class="mb-0">FORMULIR PENDAFTARAN ANGGOTA BARU</h5>
    </div>
    <div class="card-body">
      <p class="login-box-msg">
        Selamat datang, <strong><?= htmlspecialchars($data['nama'] ?? 'Calon Anggota') ?></strong> 👋 
        <br>Silakan lengkapi dan isi data berikut untuk<br><strong>PENGAJUAN ANGGOTA BARU</strong>.
      </p>
      </p>

      <?php if ($isAnggotaAktif): ?>
        <div class="alert alert-success">✅ Anda sudah terdaftar sebagai anggota koperasi.</div>
        <div class="text-center mt-3">
          <a href="../logout.php" class="btn btn-danger">🚪 Logout</a>
        </div>
      <?php elseif (!empty($pengajuan)): ?>
        <div class="alert alert-warning">⚠️ Anda sudah mengajukan pendaftaran dengan nomor
          <b><?= htmlspecialchars($pengajuan['no_daftar']) ?></b> dan statusnya 
          <b><?= htmlspecialchars($pengajuan['status']) ?></b>. <br>Mohon tunggu verifikasi Pengurus Koperasi.
        </div>
        <div class="text-center mt-3">
          <a href="../logout.php" class="btn btn-danger">🚪 Logout</a>
        </div>
      <?php else: ?>
        <?php if (!empty($pesan)) echo $pesan; ?>

        <form method="post" action="">
          <input type="hidden" name="no_daftar" value="<?= $no_daftar; ?>">
          <input type="hidden" name="id_user" value="<?= $id_user; ?>">

          <!-- Simpanan Pokok -->
          <div class="mb-3">
            <label class="form-label">Simpanan Pokok :</label>
            <div class="input-group">
              <span class="input-group-text">Rp</span>
              <input type="number" name="simp_pokok" class="form-control"
                     value="<?= $master['Pokok'] ?? '' ?>" readonly>
              <span class="input-group-text">Angsuran</span>
              <select name="angsur_pokok" class="form-select" required>
                <option value="">--pilih--</option>
                <?php for($i=1;$i<=10;$i++): ?>
                  <option value="<?= $i; ?>"><?= $i; ?> kali</option>
                <?php endfor; ?>
              </select>
            </div>
          </div>

          <!-- Simpanan Wajib -->
          <div class="mb-3">
            <label class="form-label">Simpanan Wajib :</label>
            <div class="input-group">
              <span class="input-group-text">Rp</span>
              <input type="number" name="simp_wajib" class="form-control"
                     value="<?= $master['Wajib'] ?? '' ?>" readonly>
            </div>
          </div>

          <!-- Tabungan -->
          <div class="mb-3">
            <label class="form-label">Tabungan :</label>
            <div class="input-group">
              <span class="input-group-text">Rp</span>
              <input type="number" name="tabungan" class="form-control">
            </div>
          </div>

          <p class="text-muted">Data akan diverifikasi oleh <b>PENGURUS KOPERASI</b>.</p>

          <div class="text-center">
            <button type="submit" class="btn btn-success">📤 Daftar</button>
            <a href="../logout.php" class="btn btn-danger">❌ Batal</a>
          </div>
        </form>
      <?php endif; ?>
    </div>
  </div>
</div>
</body>
</html>

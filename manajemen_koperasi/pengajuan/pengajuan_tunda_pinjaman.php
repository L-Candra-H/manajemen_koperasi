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

// ambil no_daftar dari URL
$no_pengajuan = $_GET['no_daftar'] ?? null;

// cek apakah pengajuan pinjaman sudah disetujui
$resPinjaman = null;
if ($no_pengajuan) {
    $sqlPinjaman = "SELECT no_daftar, kode_jangka 
                    FROM pengajuan_pinjaman 
                    WHERE no_daftar=? AND id_user=? AND status='Disetujui'";
    $stmt = $conn->prepare($sqlPinjaman);
    $stmt->bind_param("ss", $no_pengajuan, $id_user);
    $stmt->execute();
    $resPinjaman = $stmt->get_result()->fetch_assoc();
    $stmt->close();
}

$kode_jangka = $resPinjaman['kode_jangka'] ?? null;

// ambil limit tunda dari jangka waktu
$limitTunda = 0;
if ($kode_jangka) {
    $qTunda = $conn->prepare("SELECT kali FROM jangka_waktu_tunda WHERE kode_jangka=?");
    $qTunda->bind_param("s", $kode_jangka);
    $qTunda->execute();
    $limitTunda = $qTunda->get_result()->fetch_assoc()['kali'] ?? 0;
    $qTunda->close();
}

// hitung jumlah tunda yang sudah disetujui
$jumlahTunda = 0;
if ($no_pengajuan) {
    $qCount = $conn->prepare("SELECT COUNT(*) AS jml 
                              FROM pengajuan_tunda_pinjaman 
                              WHERE no_daftar=? AND status='Disetujui'");
    $qCount->bind_param("s", $no_pengajuan);
    $qCount->execute();
    $jumlahTunda = $qCount->get_result()->fetch_assoc()['jml'] ?? 0;
    $qCount->close();
}

// proses simpan hanya jika ada pinjaman
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $resPinjaman) {
    $bulan_tunda = $_POST['bulan_tunda'] . "-01";
    $ktg         = $_POST['ktg'];

    $cekAktif = $conn->prepare("SELECT id 
                                FROM pengajuan_tunda_pinjaman 
                                WHERE no_daftar = ? 
                                  AND status IN ('Diajukan', 'Diproses', 'Direkap')
                                LIMIT 1");
    $cekAktif->bind_param("s", $no_pengajuan);
    $cekAktif->execute();
    $errors = [];
    if ($cekAktif->get_result()->num_rows > 0) {
        $errors[] ="Masih ada pengajuan tunda yang sedang diproses.";
    }

    $cekBulan = $conn->prepare("SELECT id
                                FROM pengajuan_tunda_pinjaman
                                WHERE no_daftar = ?
                                  AND bulan_tunda = ?
                                LIMIT 1");
    $cekBulan->bind_param("ss", $no_pengajuan, $bulan_tunda);
    $cekBulan->execute();
    if ($cekBulan->get_result()->num_rows > 0) {
        $errors[] = "Bulan tunda tersebut sudah pernah diajukan.";
    }

    $bulanSekarang = date('Y-m-01');
    if ($bulan_tunda < $bulanSekarang) {
        $errors[] = "Bulan tunda tidak boleh lebih kecil dari bulan berjalan.";
    }

    if ($jumlahTunda >= $limitTunda) {
        $errors[] = "Batas maksimal tunda sudah tercapai.";
    }

    if (!empty($errors)) {
        $pesan = "<div class='alert alert-danger'><ul>";
        foreach ($errors as $err) {
            $pesan .= "<li>{$err}</li>";
        }
        $pesan .= "</ul></div>";

    } else {
        $tundaKe = $jumlahTunda + 1;
        $stmt = $conn->prepare("INSERT INTO pengajuan_tunda_pinjaman
            (no_daftar, id_user, tgl_pengajuan, bulan_tunda, ktg, tunda_ke, status)
            VALUES (?, ?, CURDATE(), ?, ?, ?, 'Diajukan')");
        $stmt->bind_param("ssssi", $no_pengajuan, $id_user, $bulan_tunda, $ktg, $tundaKe);

        if ($stmt->execute()) {
            header("Location: ../keanggotaan/daftar_pinjaman.php?notif=sukses");
            exit;
        } else {
            $pesan = "<div class='alert alert-danger'>❌ Gagal menyimpan pengajuan tunda: " . $stmt->error . "</div>";
        }
        $stmt->close();
    }
}

// ambil data pegawai
$sql = "SELECT p.nik, p.nama, d.nama AS departemen
        FROM {$db_name}.pegawai p
        JOIN {$db_name}.departemen d ON p.departemen = d.dep_id
        WHERE p.nik=? LIMIT 1";
$stmt = $conn->prepare($sql);
$stmt->bind_param("s", $id_user);
$stmt->execute();
$data = $stmt->get_result()->fetch_assoc();
$stmt->close();

// fallback jika data kosong
if (!$data) {
    $data = ['nama' => '', 'nik' => '', 'departemen' => ''];
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <title>Pengajuan Tunda Pinjaman</title>
  <link rel="stylesheet" href="../vendor/adminlte/dist/css/adminlte.min.css">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <style>
    .register-box { width:520px; margin:2% auto; }
    .form-control[readonly] { background-color:#e9ecef; color:#6c757d; }
  </style>
</head>
<body class="hold-transition register-page"
      style="background-image:url('../vendor/adminlte/dist/img/9429.png');
             background-size:cover; background-position:center;">

<div class="register-box">
  <div class="card card-outline card-primary">
    <div class="card-header text-center">
      <h5 class="mb-0">FORMULIR PENGAJUAN TUNDA PINJAMAN</h5>
    </div>
    <div class="card-body">
      <p class="login-box-msg">
        <strong><?= htmlspecialchars($data['nama'] ?? ($_SESSION['role']==='administrator' ? 'Administrator' : '')) ?></strong> 👋 
        <br>Silakan lengkapi data untuk<br><strong>PENGAJUAN TUNDA PINJAMAN</strong>.
      </p>

      <?php if (!empty($pesan)) echo $pesan; ?>

      <?php if (!$resPinjaman): ?>
        <div class="alert alert-warning text-center">
          ⚠️ Anda belum memiliki pinjaman yang disetujui, sehingga tidak dapat mengajukan penundaan.
        </div>
        <div class="text-center mt-3">
          <a href="../index.php" class="btn btn-danger">⬅️ Kembali</a>
        </div>
      <?php else: ?>
        <form method="post" action="">
          <input type="hidden" name="no_daftar" value="<?= htmlspecialchars($no_pengajuan ?? '') ?>">
          <input type="hidden" name="id_user" value="<?= htmlspecialchars($id_user ?? '') ?>">

          <!-- Limit Penundaan -->
          <div class="mb-3">
            <label class="form-label">Limit Penundaan</label>
            <input type="text" class="form-control" value="<?= $limitTunda; ?> kali" readonly>
          </div>

          <!-- Bulan Tunda -->
          <div class="mb-3">
            <label class="form-label">Bulan Tunda</label>
            <input type="month" name="bulan_tunda" class="form-control" required>
          </div>

          <!-- Keterangan -->
          <div class="mb-3">
            <label class="form-label">Keterangan</label>
            <textarea name="ktg" class="form-control" rows="3" required></textarea>
          </div>

          <!-- Sisa Limit -->
          <div class="mb-3">
            <label class="form-label">Sisa Limit Tunda</label>
            <input type="text" class="form-control" value="<?= max(0, $limitTunda - $jumlahTunda); ?> kali" readonly>
          </div>

          <div class="text-center">
            <button type="submit" class="btn btn-success">📤 Ajukan Tunda Pinjaman</button>
            <a href="../index.php" class="btn btn-danger">❌ Batal</a>
          </div>
        </form>
      <?php endif; ?>
    </div>
  </div>
</div>

<script>
document.addEventListener("DOMContentLoaded", function() {
  const bulanTundaInput = document.querySelector("input[name='bulan_tunda']");
  const ktgInput = document.querySelector("textarea[name='ktg']");

  if (bulanTundaInput) {
    bulanTundaInput.addEventListener("change", function() {
      const selected = new Date(this.value + "-01");
      const today = new Date();
      if (selected < today) {
        alert("❌ Bulan tunda tidak boleh mundur dari bulan berjalan.");
        this.value = "";
      }
    });
  }

  if (ktgInput) {
    ktgInput.addEventListener("blur", function() {
      if (this.value.length < 4) {
        alert("❌ Keterangan/Alasan harus minimal 4 karakter.");
        this.focus();
      }
    });
  }
});
</script>
</body>
</html>

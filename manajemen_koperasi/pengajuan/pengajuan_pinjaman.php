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

// cek pengajuan terakhir
$cek = $conn->prepare("SELECT status, no_daftar 
                       FROM pengajuan_pinjaman 
                       WHERE id_user=? 
                       ORDER BY tgl_pembuatan DESC 
                       LIMIT 1");
$cek->bind_param("s", $id_user);
$cek->execute();
$hasil = $cek->get_result()->fetch_assoc();
$cek->close();
if ($hasil && in_array($hasil['status'], ['Diteruskan','Diproses','Direkap','Disetujui'])) {
    $pesan = "<div class='alert alert-warning text-center'>
                ⚠️ Pengajuan pinjaman dengan nomor <b>{$hasil['no_daftar']}</b> 
                sedang dalam status <b>{$hasil['status']}</b>. 
                Anda tidak dapat mengajukan pinjaman baru sampai proses selesai.
              </div>";
}

// cek pengajuan yang sudah ada
$cekAktif = $conn->prepare("SELECT no_daftar, sisa_angsuran, status_angsuran
                            FROM daftar_pinjaman
                            WHERE id_user = ?
                              AND status_angsuran = 'Belum Lunas'
                            LIMIT 1");

$cekAktif->bind_param("s", $id_user);
$cekAktif->execute();
$pinjamanAktif =
    $cekAktif->get_result()->fetch_assoc();
$cekAktif->close();

$blokirPengajuan = false;

if ($hasil && in_array($hasil['status'],['Diteruskan','Diproses','Direkap','Disetujui'])) {
    $blokirPengajuan = true;
    $pesan = "<div class='alert alert-warning text-center'>
                ⚠️ Pengajuan pinjaman dengan nomor<b>{$hasil['no_daftar']}</b>
                sedang dalam status<b>{$hasil['status']}</b>.
                <br>Anda tidak dapat mengajukan pinjaman baru.
              </div>";
}

if ($pinjamanAktif) {
    $blokirPengajuan = true;
    $pesan = "<div class='alert alert-danger text-center'>
                ⚠️ Anda masih memiliki pinjaman yang belum lunas.
                <br>Nomor pinjaman : <b>{$pinjamanAktif['no_daftar']}</b>
                <br>Sisa angsuran : <b>{$pinjamanAktif['sisa_angsuran']}</b> kali.
                <br>Lunasi pinjaman terlebih dahulu sebelum mengajukan pinjaman baru.
              </div>";
}

// generate nomor pendaftaran
$tgl = date("Ymd");
$q = $conn->query("SELECT COUNT(*) AS jml 
                   FROM pengajuan_pinjaman 
                   WHERE DATE(tgl_pembuatan)=CURDATE()");
$row = $q->fetch_assoc();
$urut = str_pad($row['jml']+1, 4, "0", STR_PAD_LEFT);
$no_daftar = "PJP" . $tgl . $urut;

// proses simpan
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$blokirPengajuan) {
    $pinjaman          = $_POST['pinjaman'];
    $angsur_pinjaman   = $_POST['angsur_pinjaman'];
    $angsuran_per_bulan= $_POST['angsuran_per_bulan'];
    $kode_jangka       = $_POST['kode_jangka'];

    $cekFinal = $conn->prepare("SELECT no_daftar
                                FROM daftar_pinjaman
                                WHERE id_user=?
                                    AND status_angsuran='Belum Lunas'
                                LIMIT 1");
    $cekFinal->bind_param("s", $id_user);
    $cekFinal->execute();
    if($cekFinal->get_result()->num_rows > 0){
        die("Anggota masih memiliki pinjaman aktif.");
    }

    $stmt = $conn->prepare("INSERT INTO pengajuan_pinjaman 
        (no_daftar, id_user, pinjaman, angsur_pinjaman, angsuran_per_bulan, tgl_pembuatan, kode_jangka, status) 
        VALUES (?, ?, ?, ?, ?, CURDATE(), ?, 'Diteruskan')");
    $stmt->bind_param("ssdids", $no_daftar, $id_user, $pinjaman, $angsur_pinjaman, $angsuran_per_bulan, $kode_jangka);

    if ($stmt->execute()) {
        header("Location: ../index.php");
        exit;
    } else {
        $pesan = "<div class='alert alert-danger'>❌ Gagal menyimpan pengajuan pinjaman: " . $stmt->error . "</div>";
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

// ambil jangka waktu tunda
$qTunda = $conn->query("SELECT kode_jangka, bln, kali FROM jangka_waktu_tunda ORDER BY bln ASC");
$jangkaOptions = [];
while($row = $qTunda->fetch_assoc()) {
    $jangkaOptions[] = $row;
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <title>Pengajuan Pinjaman Koperasi</title>
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
      <h5 class="mb-0">FORMULIR PENGAJUAN PINJAMAN</h5>
    </div>
    <div class="card-body">
      <p class="login-box-msg">
        <strong><?= htmlspecialchars($data['nama'] ?? ($_SESSION['role']==='administrator' ? 'Administrator' : '')) ?></strong> 👋 
        <br>Silakan lengkapi data untuk<br><strong>PENGAJUAN PINJAMAN</strong>.
      </p>

      <?php if ($blokirPengajuan): ?>
        <?= $pesan ?>
        <div class="text-center mt-3">
          <a href="../index.php" class="btn btn-danger">⬅️ Kembali</a>
        </div>
      <?php else: ?>
        <?php if (!empty($pesan)) echo $pesan; ?>

        <form method="post" action="">
          <input type="hidden" name="no_daftar" value="<?= $no_daftar; ?>">
          <input type="hidden" name="id_user" value="<?= $id_user; ?>">
          <input type="hidden" name="kode_jangka" id="kode_jangka">

          <!-- Jumlah Pinjaman -->
          <div class="mb-3">
            <label class="form-label">Jumlah Pinjaman</label>
            <select name="pinjaman" id="pinjaman" class="form-select" required>
              <option value="">--pilih--</option>
              <?php for($i=1000000;$i<=10000000;$i+=500000): ?>
                <option value="<?= $i; ?>"><?= number_format($i,0,',','.'); ?></option>
              <?php endfor; ?>
            </select>
          </div>

          <!-- Angsuran -->
          <div class="mb-3">
            <label class="form-label">Angsuran</label>
            <select name="angsur_pinjaman" id="angsur_pinjaman" class="form-select" required>
              <option value="">--pilih--</option>
            </select>
          </div>

          <!-- Angsuran per Bulan -->
          <div class="mb-3">
            <label class="form-label">Angsuran per Bulan</label>
            <input type="text" id="angsuran_bulan" class="form-control" readonly>
            <input type="hidden" name="angsuran_per_bulan" id="angsuran_per_bulan_hidden">
          </div>

          <!-- Jangka Waktu Tunda -->
          <div class="mb-3">
            <label class="form-label">Jangka Waktu Tunda</label>
            <input type="text" id="tunda_label" class="form-control" readonly>
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
const jangkaOptions  = <?= json_encode($jangkaOptions, JSON_NUMERIC_CHECK) ?>;
const jasaPinjamMap = <?= json_encode($jasaOptions, JSON_NUMERIC_CHECK) ?>;
const pinjamanSelect = document.getElementById('pinjaman');
const angsurSelect   = document.getElementById('angsur_pinjaman');
const angsuranField  = document.getElementById('angsuran_bulan');
const angsuranHidden = document.getElementById('angsuran_per_bulan_hidden');
const tundaLabel     = document.getElementById('tunda_label');
const kodeJangka     = document.getElementById('kode_jangka');

function updateTenor() {
    const pinjaman  = parseInt(pinjamanSelect.value) || 0;
    angsurSelect.innerHTML = '<option value="">--pilih--</option>';
    let maxBulan = 12;

    if (pinjaman <= 1000000) {maxBulan = 12;}
    else if (pinjaman <= 2000000) {maxBulan = 18;}
    else if (pinjaman <= 3000000) {maxBulan = 24;}
    else {maxBulan = 36;    }

    jangkaOptions.forEach(function(item){
        if(item.bln <= maxBulan){
            const opt = document.createElement('option');
            opt.value = item.bln;
            opt.text  = item.bln + ' Bulan';
            opt.dataset.kode = item.kode_jangka;
            opt.dataset.kali = item.kali;
            angsurSelect.appendChild(opt);
        }
    });

    kodeJangka.value = '';
    tundaLabel.value = '';
    angsuranField.value = '';
    angsuranHidden.value = '';
}

function hitungAngsuran() {
  const pinjaman = parseInt(pinjamanSelect.value) || 0;
  const lama     = parseInt(angsurSelect.value) || 0;
  const selected = angsurSelect.options[angsurSelect.selectedIndex];

  if (pinjaman > 0 && lama > 0 && selected) {
    const kode = selected.dataset.kode;
    const kali = selected.dataset.kali;
    const rawJasa = jasaPinjamMap["js_001"] || 0; // ambil dari DB

    // hitung pokok + jasa
    const pokokPerBulan = pinjaman / lama;
    const jasaPerBulan  = pinjaman * (rawJasa / 100);
    const perBulan      = Math.round(pokokPerBulan + jasaPerBulan);

    // tampilkan hasil
    angsuranField.value  = perBulan.toLocaleString('id-ID');
    angsuranHidden.value = perBulan;
    tundaLabel.value     = kali + " kali";
    kodeJangka.value     = kode;
  } else {
    // reset jika tidak valid
    angsuranField.value  = '';
    angsuranHidden.value = '';
    tundaLabel.value     = '';
    kodeJangka.value     = '';
  }
}

// event listener
pinjamanSelect.addEventListener('change', updateTenor);
angsurSelect.addEventListener('change', hitungAngsuran);
</script>

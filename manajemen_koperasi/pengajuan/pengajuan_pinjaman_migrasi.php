<?php
require_once __DIR__ . '/../conf/auth.php';
require_once __DIR__ . '/../conf/conf_koperasi.php';
require_once __DIR__ . '/../conf/conf.php';

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
$nik      = $_SESSION['id_user']  ?? '';

if ($kode_jab !== 'kop_003' && $role !== 'administrator') {
    die("❌ Akses ditolak. Hanya bendahara atau administrator yang bisa melakukan migrasi pinjaman.");
}

// generate nomor migrasi
$tgl = date("Ymd");
$q = $conn->query("SELECT COUNT(*) AS jml 
                   FROM pengajuan_pinjaman 
                   WHERE DATE(tgl_pembuatan)=CURDATE() 
                     AND source='Migrasi'");
$row = $q->fetch_assoc();
$urut = str_pad($row['jml']+1, 4, "0", STR_PAD_LEFT);
$no_daftar = "MJP" . $tgl . $urut;

// ambil daftar anggota aktif
$sqlAnggota = "
  SELECT da.no_anggota, da.id_user, pg.nama, d.nama AS departemen
  FROM daftar_anggota da
  INNER JOIN {$db_induk}.pegawai pg ON pg.nik = da.id_user
  INNER JOIN {$db_induk}.departemen d ON pg.departemen = d.dep_id
  WHERE da.status_keanggotaan = 'Aktif'
  ORDER BY da.no_anggota ASC
";
$resAnggota = $conn->query($sqlAnggota);

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

// proses simpan
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // validasi input
    $errors = [];
    $id_user            = $_POST['id_user'] ?? '';
    $pinjaman           = (int)($_POST['pinjaman'] ?? 0);
    $angsur_pinjaman    = (int)($_POST['angsur_pinjaman'] ?? 0);
    $angsuran_per_bulan = (int)($_POST['angsuran_per_bulan'] ?? 0);
    $jumlah_dibayar     = (int)($_POST['jumlah_dibayar'] ?? 0); // jumlah angsuran yang sudah dibayar
    $sisa_angsuran      = (int)($_POST['sisa_angsuran'] ?? 0);
    $keterangan         = trim($_POST['keterangan'] ?? '');
    $kode_jangka        = $_POST['kode_jangka'] ?? '';
  
    if (empty($id_user)) $errors[] = "Anggota harus dipilih.";
    if ($pinjaman <= 0) $errors[] = "Jumlah pinjaman tidak valid.";
    if ($angsur_pinjaman <= 0) $errors[] = "Tenor harus diisi.";
    if ($angsuran_per_bulan <= 0) $errors[] = "Angsuran per bulan belum dihitung.";

    if (!empty($errors)) {
        echo "<div class='alert alert-danger'><ul>";
        foreach ($errors as $err) echo "<li>$err</li>";
        echo "</ul></div>";
    } else {
        // hitung nominal jumlah dibayar (rupiah)
        $jumlah_dibayar_nominal = $jumlah_dibayar * $angsuran_per_bulan;

        $cekFinal = $conn->prepare("SELECT no_daftar FROM daftar_pinjaman WHERE id_user = ? LIMIT 1");
        $cekFinal->bind_param("s", $id_user);
        $cekFinal->execute();
        if ($cekFinal->get_result()->num_rows > 0) {
            $_SESSION['error_migrasi'] =
                "Anggota yang dipilih sudah memiliki data pinjaman aktif sehingga tidak dapat dilakukan migrasi kembali.";
            header("Location: ../keanggotaan/daftar_pinjaman.php");
            exit;
        }

        // simpan ke pengajuan_pinjaman
        $stmt = $conn->prepare("INSERT INTO pengajuan_pinjaman 
            (no_daftar, id_user, pinjaman, angsur_pinjaman, angsuran_per_bulan, 
             tgl_pembuatan, kode_jangka, status, source, keterangan) 
            VALUES (?, ?, ?, ?, ?, CURDATE(), ?, 'Disetujui', 'Migrasi', ?)");
        $stmt->bind_param("ssdidss", $no_daftar, $id_user, $pinjaman, $angsur_pinjaman, $angsuran_per_bulan, $kode_jangka, $keterangan);
        $stmt->execute();
        $stmt->close();

        // simpan ke daftar_pinjaman
        $status_angsuran = ($sisa_angsuran == 0) ? 'Lunas' : 'Belum Lunas';
        $stmt2 = $conn->prepare("INSERT INTO daftar_pinjaman 
            (no_daftar, id_user, pinjaman, jumlah_dibayar, angsur_pinjaman, 
             sisa_angsuran, status_angsuran, source, keterangan) 
            VALUES (?, ?, ?, ?, ?, ?, ?, 'Migrasi', ?)");
        $stmt2->bind_param("ssdiiiss", $no_daftar, $id_user, $pinjaman, $jumlah_dibayar_nominal, 
                           $angsur_pinjaman, $sisa_angsuran, $status_angsuran, $keterangan);
        $stmt2->execute();
        $stmt2->close();

        // ✅ langsung generate detail_pinjaman bulan H
        $bulanH       = $_POST['bulan_angsuran_terakhir']. '-01';
        $angsuranKe   = $jumlah_dibayar; // sesuai jumlah dibayar (kali)
        $statusDetail = 'Sudah Bayar';
        $tundaKe      = 0;
        $tglBayar     = date("Y-m-d");

        $stmt3 = $conn->prepare("INSERT INTO detail_pinjaman 
            (no_daftar, id_user, pinjaman, jumlah_angsuran, angsuran_ke, bulan, 
             jumlah_cicilan, tunda_ke, status, tgl_bayar)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt3->bind_param("ssdiisdiss",
            $no_daftar, $id_user, $pinjaman, $angsur_pinjaman,
            $angsuranKe, $bulanH, $angsuran_per_bulan,
            $tundaKe, $statusDetail, $tglBayar
        );
        $stmt3->execute();
        $stmt3->close();

        // redirect ke daftar_pinjaman setelah berhasil
        $_SESSION['success_migrasi'] =
            "Data pinjaman lama berhasil dimigrasikan.";
        header("Location: ../keanggotaan/daftar_pinjaman.php");
        exit;
    }
}
?>

<!-- Modal Migrasi Pinjaman -->
<div class="modal fade" id="modalMigrasi" tabindex="-1">
  <div class="modal-dialog modal-lg">
    <div class="modal-content">
      <form method="post" action="../pengajuan/pengajuan_pinjaman_migrasi.php">
        <div class="modal-header bg-success text-white">
          <h5 class="modal-title">Migrasi Pinjaman Lama</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body">
          <!-- Dropdown Anggota -->
          <div class="mb-3">
            <label>Pilih Anggota</label>
            <select name="id_user" class="form-select" required>
              <option value="">-- Pilih Anggota --</option>
              <?php
              if ($resAnggota) {
                  while ($row = $resAnggota->fetch_assoc()) {
                      echo "<option value='{$row['id_user']}'>
                              {$row['no_anggota']} - {$row['nama']} ({$row['departemen']})
                            </option>";
                  }
              }
              ?>
            </select>
          </div>

          <!-- Grid 2 Kolom -->
          <div class="row">
            <!-- Kolom kiri -->
            <div class="col-md-6">
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

              <!-- Tenor -->
              <div class="mb-3">
                <label class="form-label">Angsuran</label>
                <select name="angsur_pinjaman" id="angsur_pinjaman" class="form-select" required>
                  <option value="">--pilih--</option>
                </select>
                <input type="hidden" name="kode_jangka" id="kode_jangka">
              </div>

              <!-- Angsuran per Bulan -->
              <div class="mb-3">
                <label class="form-label">Angsuran per Bulan</label>
                <input type="text" id="angsuran_bulan" class="form-control" readonly>
                <input type="hidden" name="angsuran_per_bulan" id="angsuran_per_bulan_hidden">
              </div>
            </div>

            <!-- Kolom kanan -->
            <div class="col-md-6">
              <!-- Jumlah Dibayar (kali) -->
              <div class="mb-3">
                <label class="form-label">Jumlah Dibayar</label>
                <input type="number" id="jumlah_dibayar" name="jumlah_dibayar" class="form-control" required>
                <small class="text-muted">Isi berapa kali angsuran sudah dibayar</small>
              </div>

              <!-- Sisa Angsuran (otomatis) -->
              <div class="mb-3">
                <label class="form-label">Sisa Angsuran</label>
                <input type="text" id="sisa_angsuran" name="sisa_angsuran" class="form-control" readonly>
              </div>

              <!-- Bulan Angsuran Terakhir -->
              <div class="mb-3">
                  <label class="form-label">Bulan Angsuran Terakhir</label>
                  <input type="month" name="bulan_angsuran_terakhir" class="form-control" required>
              </div>
            </div>
          </div> <!-- end row -->

          <!-- Kolom bawah -->
          <div class="col-md-12">
            <!-- Keterangan -->
            <div class="mb-3">
              <label class="form-label">Keterangan</label>
              <input type="text" name="keterangan" class="form-control">
            </div>
          </div>
        </div>
        <div class="modal-footer">
          <button type="submit" class="btn btn-success">💾 Simpan Migrasi</button>
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">❌ Batal</button>
        </div>
      </form>
    </div>
  </div>
</div>

<script>
const jangkaOptions = <?= json_encode($jangkaOptions, JSON_NUMERIC_CHECK) ?>;
const jasaPinjamMap = <?= json_encode($jasaOptions, JSON_NUMERIC_CHECK) ?>;
const pinjamanSelect = document.getElementById('pinjaman');
const angsurSelect   = document.getElementById('angsur_pinjaman');
const angsuranField  = document.getElementById('angsuran_bulan');
const angsuranHidden = document.getElementById('angsuran_per_bulan_hidden');
const jumlahDibayarInput = document.getElementById('jumlah_dibayar');
const sisaAngsuranInput  = document.getElementById('sisa_angsuran');
const kodeJangka = document.getElementById('kode_jangka');

function updateTenorByPinjaman() {
    const pinjaman = parseInt(pinjamanSelect.value) || 0;
    angsurSelect.innerHTML = '<option value="">--pilih--</option>';
    let maxBulan = 12;
    if (pinjaman <= 1000000) {maxBulan = 12;
    } else if (pinjaman <= 2000000) {maxBulan = 18;
    } else if (pinjaman <= 3000000) {maxBulan = 24;
    } else {maxBulan = 36;}

    jangkaOptions.forEach(function(item){
        if(item.bln <= maxBulan){
            let opt = document.createElement('option');
            opt.value = item.bln;
            opt.text  = item.bln + ' Bulan';
            opt.dataset.kode = item.kode_jangka;
            angsurSelect.appendChild(opt);
        }
    });

    kodeJangka.value = '';
    angsuranField.value = '';
    angsuranHidden.value = '';
}

function hitungAngsuran() {
  const pinjaman = parseInt(pinjamanSelect.value) || 0;
  const lama     = parseInt(angsurSelect.value) || 0;
  const selected = angsurSelect.options[angsurSelect.selectedIndex];

  if (selected && selected.dataset.kode) {
    kodeJangka.value = selected.dataset.kode;
  }
  
  if (pinjaman > 0 && lama > 0 && selected) {
    const rawJasa = jasaPinjamMap["js_001"] || 0; // ambil jasa default

    // hitung pokok + jasa
    const pokokPerBulan = pinjaman / lama;
    const jasaPerBulan  = pinjaman * (rawJasa / 100);
    const perBulan      = Math.round(pokokPerBulan + jasaPerBulan);

    // tampilkan hasil
    angsuranField.value  = perBulan.toLocaleString('id-ID');
    angsuranHidden.value = perBulan;

    // update sisa angsuran jika jumlah dibayar sudah diisi
    updateSisaAngsuran();

  } else {
    // reset jika tidak valid
    angsuranField.value  = '';
    angsuranHidden.value = '';
    sisaAngsuranInput.value = '';
  }
}

function updateSisaAngsuran() {
  const tenor  = parseInt(angsurSelect.value) || 0;
  const dibayar= parseInt(jumlahDibayarInput.value) || 0;
  const sisa   = tenor > 0 ? Math.max(tenor - dibayar, 0) : '';
  sisaAngsuranInput.value = sisa ? sisa + " kali" : '';
}

// event listener
pinjamanSelect.addEventListener('change', updateTenorByPinjaman);
angsurSelect.addEventListener('change', hitungAngsuran);
jumlahDibayarInput.addEventListener('input', updateSisaAngsuran);
</script>

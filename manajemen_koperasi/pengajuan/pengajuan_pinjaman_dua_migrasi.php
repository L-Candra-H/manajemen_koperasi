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
                   FROM pengajuan_pinjaman_dua 
                   WHERE DATE(tgl_pembuatan)=CURDATE() 
                     AND source='Migrasi'");
$row = $q->fetch_assoc();
$urut = str_pad($row['jml']+1, 4, "0", STR_PAD_LEFT);
$no_daftar = "MJD" . $tgl . $urut;

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

    $cekFinal = $conn->prepare("SELECT no_daftar FROM daftar_pinjaman_dua WHERE id_user = ? LIMIT 1");
    $cekFinal->bind_param("s", $id_user);
    $cekFinal->execute();
    $dataLama = $cekFinal->get_result()->fetch_assoc();
    $cekFinal->close();
    if ($dataLama) {
    $errors[] = "Anggota sudah memiliki data Pinjaman Dua dengan nomor "
                . $dataLama['no_daftar'] .
                " dan tidak dapat dimigrasikan kembali.";
    }

    if (empty($id_user)) $errors[] = "Anggota harus dipilih.";
    if ($pinjaman <= 0) $errors[] = "Jumlah pinjaman tidak valid.";
    if ($angsur_pinjaman <= 0) $errors[] = "Tenor harus diisi.";
    if ($angsuran_per_bulan <= 0) $errors[] = "Angsuran per bulan belum dihitung.";

    if (!empty($errors)) {
        $_SESSION['error_migrasi_dua'] = implode("<br>", $errors);
        header("Location: ../keanggotaan/daftar_pinjaman_dua.php");
        exit;
    
    } else {
        // hitung nominal jumlah dibayar (rupiah)
        $jumlah_dibayar_nominal = $jumlah_dibayar * $angsuran_per_bulan;

        // simpan ke pengajuan_pinjaman_dua
        $stmt = $conn->prepare("INSERT INTO pengajuan_pinjaman_dua 
            (no_daftar, id_user, pinjaman, angsur_pinjaman, angsuran_per_bulan, 
             tgl_pembuatan, kode_jangka, status, source, keterangan) 
            VALUES (?, ?, ?, ?, ?, CURDATE(), NULL, 'Disetujui', 'Migrasi', ?)");
        $stmt->bind_param("ssdids", $no_daftar, $id_user, $pinjaman, $angsur_pinjaman, $angsuran_per_bulan, $keterangan);
        $stmt->execute();
        $stmt->close();

        // simpan ke daftar_pinjaman_dua
        $status_angsuran = ($sisa_angsuran == 0) ? 'Lunas' : 'Belum Lunas';
        $stmt2 = $conn->prepare("INSERT INTO daftar_pinjaman_dua 
            (no_daftar, id_user, pinjaman, jumlah_dibayar, angsur_pinjaman, 
             sisa_angsuran, status_angsuran, source, keterangan) 
            VALUES (?, ?, ?, ?, ?, ?, ?, 'Migrasi', ?)");
        $stmt2->bind_param("ssdiiiss", $no_daftar, $id_user, $pinjaman, $jumlah_dibayar_nominal, 
                           $angsur_pinjaman, $sisa_angsuran, $status_angsuran, $keterangan);
        $stmt2->execute();
        $stmt2->close();

        // ✅ langsung generate detail_pinjaman_dua bulan H
        $bulanH = ($_POST['bulan_angsuran_terakhir'] ?? date('Y-m')). '-01';
        $angsuranKe   = $jumlah_dibayar; // sesuai jumlah dibayar (kali)
        $statusDetail = 'Sudah Bayar';
        $tundaKe      = 0;
        $tglBayar     = date("Y-m-d");

        $stmt3 = $conn->prepare("INSERT INTO detail_pinjaman_dua 
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

        // redirect ke daftar_pinjaman_dua setelah berhasil
        $_SESSION['success_migrasi_dua'] =
            "Data Pinjaman Dua berhasil dimigrasikan.";
        header("Location: ../keanggotaan/daftar_pinjaman_dua.php");
        exit;
    }
}
?>

<!-- Modal Migrasi Pinjaman Dua -->
<div class="modal fade" id="modalMigrasi" tabindex="-1">
  <div class="modal-dialog modal-lg">
    <div class="modal-content">
      <form method="post" action="../pengajuan/pengajuan_pinjaman_dua_migrasi.php">
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
                <input type="number" name="pinjaman" id="pinjaman" 
                       class="form-control" required>
              </div>

              <!-- Angsuran (fixed 10 bulan) -->
              <div class="mb-3">
                <label class="form-label">Angsuran</label>
                <input type="text" class="form-control" value="10 Bulan" readonly>
                <input type="hidden" name="angsur_pinjaman" value="10">
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
                <input type="text" id="sisa_angsuran_tampil" class="form-control" readonly>
                <input type="hidden" id="sisa_angsuran" name="sisa_angsuran">
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
const jasaPinjamMap       = <?= json_encode($jasaOptions, JSON_NUMERIC_CHECK) ?>;
const pinjamanSelect      = document.getElementById('pinjaman');
const angsuranField       = document.getElementById('angsuran_bulan');
const angsuranHidden      = document.getElementById('angsuran_per_bulan_hidden');
const jumlahDibayarInput  = document.getElementById('jumlah_dibayar');
const sisaAngsuranInput   = document.getElementById('sisa_angsuran');

function hitungAngsuran() {
  const pinjaman = parseInt(pinjamanSelect.value) || 0;
  const lama     = 10; // fixed tenor 10 bulan
  const rawJasa  = jasaPinjamMap["js_001"] || 0; // ambil jasa default

  if (pinjaman > 0) {
    const pokokPerBulan = pinjaman / lama;
    const jasaPerBulan  = pinjaman * (rawJasa / 100);
    const perBulan      = Math.round(pokokPerBulan + jasaPerBulan);

    // tampilkan hasil
    angsuranField.value  = perBulan.toLocaleString('id-ID');
    angsuranHidden.value = perBulan;

    // update sisa angsuran jika jumlah dibayar sudah diisi
    updateSisaAngsuran();
  } else {
    angsuranField.value  = '';
    angsuranHidden.value = '';
    sisaAngsuranInput.value = '';
  }
}

function updateSisaAngsuran() {
  const tenor   = 10; // fixed
  const dibayar = parseInt(jumlahDibayarInput.value) || 0;
  const sisa    = Math.max(tenor - dibayar, 0);

  // tampilkan dalam format "x kali"
  document.getElementById('sisa_angsuran_tampil').value =
        sisa + " kali";
  document.getElementById('sisa_angsuran').value =
        sisa;
}

// event listener
pinjamanSelect.addEventListener('change', hitungAngsuran);
jumlahDibayarInput.addEventListener('input', updateSisaAngsuran);
</script>

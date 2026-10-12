<?php
require_once __DIR__ . '/../conf/auth.php';
require_once __DIR__ . '/../conf/conf_koperasi.php';
require_once __DIR__ . '/../conf/conf.php';

error_reporting(0);
ini_set('display_errors', 0);

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
$conn     = bukakoneksikoperasi();
$db_induk = $db_name;

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
                   FROM pengajuan_anggota
                   WHERE DATE(tgl_pembuatan)=CURDATE()
                     AND source='Migrasi'");

if (!$q) {    die("Gagal generate nomor migrasi: " . $conn->error);
}

$row = $q->fetch_assoc();
$urut = str_pad($row['jml'] + 1, 4, "0", STR_PAD_LEFT);

$no_daftar = "MAP" . $tgl . $urut;

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

// ======================================================================
// PROSES SIMPAN MIGRASI ANGSURAN POKOK
// ======================================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // ==================================================================
    // AMBIL DATA FORM
    // ==================================================================
    $errors            = [];
    $id_user           = $_POST['id_user'] ?? '';
    $simp_pokok        = (float) ($_POST['simp_pokok'] ?? 0);
    $angsur_pokok      = (int) ($_POST['angsur_pokok'] ?? 0);
    $nominal_angsuran  = (float) ($_POST['nominal_angsuran'] ?? 0);
    $jumlah_dibayar    = (int) ($_POST['jumlah_dibayar'] ?? 0);
    $sisa_angsuran     = (int) ($_POST['sisa_angsuran'] ?? 0);
    $keterangan        = trim($_POST['keterangan'] ?? '');

    // ==================================================================
    // VALIDASI INPUT
    // ==================================================================
    if (empty($id_user)) {
        $errors[] = "Anggota harus dipilih.";
    }

    if ($simp_pokok <= 0) {
        $errors[] = "Jumlah simpanan pokok tidak valid.";
    }

    if ($angsur_pokok <= 0) {
        $errors[] = "Angsuran harus diisi.";
    }

    if ($nominal_angsuran <= 0) {
        $errors[] = "Angsuran per bulan belum dihitung.";
    }

    if ($jumlah_dibayar > $angsur_pokok) {
        $errors[] = "Jumlah dibayar tidak boleh melebihi jumlah angsuran.";
    }

    if ($jumlah_dibayar < 1) {
        $errors[] = "Jumlah dibayar minimal 1 kali.";
    }

    // ==================================================================
    // CEK DUPLIKAT DATA ANGGOTA
    // ==================================================================
    $cek = $conn->prepare("
        SELECT no_daftar
        FROM daftar_angsuran_pokok
        WHERE id_user = ?
        LIMIT 1
    ");

    $cek->bind_param("s", $id_user);
    $cek->execute();

    if ($cek->get_result()->num_rows > 0) {
        $errors[] = "Anggota sudah memiliki data angsuran pokok.";
    }

    $cek->close();

    // ==================================================================
    // VALIDASI SISA ANGSURAN
    // ==================================================================
    if ($sisa_angsuran != ($angsur_pokok - $jumlah_dibayar)) {
        $errors[] = "Perhitungan sisa angsuran tidak valid.";
    }

    // ==================================================================
    // TAMPILKAN ERROR JIKA ADA
    // ==================================================================
    if (!empty($errors)) {

        $_SESSION['error_migrasi_pokok'] =
            implode("<br>", $errors);
        header("Location: ../keanggotaan/daftar_angsuran_pokok.php");
        exit;
        
    } else {

        // ==============================================================
        // MULAI TRANSACTION
        // ==============================================================
        $conn->begin_transaction();

        try {

            // ==========================================================
            // HITUNG TOTAL SUDAH DIBAYAR
            // ==========================================================
            $jumlah_dibayar_nominal =
                $jumlah_dibayar * $nominal_angsuran;

            // ==========================================================
            // SIMPAN KE PENGAJUAN_ANGGOTA
            // ==========================================================
            $stmt = $conn->prepare("
                INSERT INTO pengajuan_anggota
                (
                    no_daftar, id_user, simp_pokok, angsur_pokok, simp_wajib, 
                    tabungan, tgl_pembuatan, status, source, keterangan
                )
                VALUES
                (
                    ?, ?, ?, ?, 0, 0, CURDATE(), 'Disetujui', 'Migrasi',
                    ?
                )
            ");

            $stmt->bind_param(
                "ssdis", $no_daftar, $id_user, $simp_pokok, $angsur_pokok, $keterangan
            );

            if (!$stmt->execute()) {
                throw new Exception(
                    "Gagal simpan pengajuan anggota : " .
                    $stmt->error
                );
            }

            $stmt->close();

            // ==========================================================
            // SIMPAN KE DAFTAR_ANGSURAN_POKOK
            // ==========================================================
            $status_angsuran =
                ($sisa_angsuran == 0)
                ? 'Lunas'
                : 'Belum Lunas';

            $stmt2 = $conn->prepare("
                INSERT INTO daftar_angsuran_pokok
                (
                    no_daftar, id_user, simp_pokok, jumlah_dibayar, jumlah_angsuran,
                    sisa_angsuran, status_angsuran, source, keterangan
                )
                VALUES
                (
                    ?, ?, ?, ?, ?, ?, ?, 'Migrasi', ?
                )
            ");

            $stmt2->bind_param(
                "ssdiiiss", $no_daftar, $id_user, $simp_pokok, $jumlah_dibayar_nominal, $angsur_pokok,
                $sisa_angsuran, $status_angsuran, $keterangan
            );

            if (!$stmt2->execute()) {
                throw new Exception(
                    "Gagal simpan daftar angsuran pokok : " .
                    $stmt2->error
                );
            }

            $stmt2->close();

            // ==========================================================
            // SIMPAN DETAIL POSISI TERAKHIR
            // ==========================================================
            $bulanH       = ($_POST['bulan_angsuran_terakhir'] ?? date('Y-m')). '-01';
            $angsuranKe   = $jumlah_dibayar;
            $statusDetail = 'Sudah Bayar';
            $tglBayar     = date("Y-m-d");

            $stmt3 = $conn->prepare("
                INSERT INTO detail_angsuran_pokok
                (
                    no_daftar, id_user, simp_pokok, jumlah_angsuran, angsuran_ke,
                    bulan, jumlah, status, tgl_bayar
                )
                VALUES
                (
                    ?, ?, ?, ?, ?, ?, ?, ?, ?
                )
            ");

            $stmt3->bind_param(
                "ssdiisdss", $no_daftar, $id_user, $simp_pokok, $angsur_pokok, $angsuranKe,
                $bulanH, $nominal_angsuran, $statusDetail, $tglBayar
            );

            if (!$stmt3->execute()) {
                throw new Exception(
                    "Gagal simpan detail angsuran pokok : " .
                    $stmt3->error
                );
            }

            $stmt3->close();

            // ==========================================================
            // COMMIT
            // ==========================================================
            $conn->commit();

            $_SESSION['success_migrasi_pokok'] =
                    "Data angsuran pokok berhasil dimigrasikan.";
            header("Location: ../keanggotaan/daftar_angsuran_pokok.php");
            exit;

        } catch (Exception $e) {

            // ==========================================================
            // ROLLBACK
            // ==========================================================
            $conn->rollback();

            $_SESSION['error_migrasi_pokok'] =
                "Gagal migrasi : " .
                $e->getMessage();
            header("Location: ../keanggotaan/daftar_angsuran_pokok.php");
            exit;
        }
    }
}
?>

<!-- Modal Migrasi Angsuran Pokok -->
<div class="modal fade" id="modalMigrasi" tabindex="-1">
  <div class="modal-dialog modal-lg">
    <div class="modal-content">
      <form method="post" action="../keanggotaan/pengajuan_angsuran_pokok_migrasi.php">
        <div class="modal-header bg-success text-white">
          <h5 class="modal-title">Migrasi Simpanan Pokok Lama</h5>
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
                <label class="form-label">Simpanan Pokok</label>
                <input type="text" class="form-control" value="1.000.000" readonly>
                <input type="hidden" name="simp_pokok" id="simp_pokok" value="1000000">
              </div>

              <!-- Tenor -->
              <div class="mb-3">
                <label class="form-label">Angsuran</label>
                <select name="angsur_pokok" id="angsur_pokok" class="form-select" required>
                  <option value="">--pilih--</option>
                  <?php for($i=1;$i<=10;$i++): ?>
                    <option value="<?= $i ?>"> 
                        <?= $i ?> kali
                    </option>
                  <?php endfor; ?>
                </select>
              </div>

              <!-- Angsuran per Bulan -->
              <div class="mb-3">
                <label class="form-label">Angsuran per Bulan</label>
                <input type="text" id="angsuran_bulan" class="form-control" readonly>
                <input type="hidden" name="nominal_angsuran" id="angsuran_per_bulan_hidden">
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
                <input type="number" id="sisa_angsuran" name="sisa_angsuran" class="form-control" readonly>
                <small class="text-muted">Sisa angsuran dalam kali pembayaran</small>
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
const simpPokok      = document.getElementById('simp_pokok');
const angsurPokok    = document.getElementById('angsur_pokok');
const nominalField   = document.getElementById('angsuran_bulan');
const nominalHidden  = document.getElementById('angsuran_per_bulan_hidden');
const jumlahDibayar  = document.getElementById('jumlah_dibayar');
const sisaAngsuran   = document.getElementById('sisa_angsuran');

function hitungAngsuran() {

    const pokok  = parseFloat(simpPokok.value) || 0;
    const angsur = parseInt(angsurPokok.value) || 0;

    if (pokok > 0 && angsur > 0) {
        const nominal =
            Math.round(pokok / angsur);
        nominalField.value =
            nominal.toLocaleString('id-ID');
        nominalHidden.value =
            nominal;
        updateSisaAngsuran();

    } else {
        nominalField.value = '';
        nominalHidden.value = '';
        sisaAngsuran.value = '';
    }
}

function updateSisaAngsuran() {

    const total = parseInt(angsurPokok.value) || 0;
    let dibayar = parseInt(jumlahDibayar.value) || 0;
    if (dibayar > total) {
        dibayar = total;
        jumlahDibayar.value = total;
    }
    const sisa = Math.max(total - dibayar, 0);
    sisaAngsuran.value = sisa;
}


simpPokok.addEventListener('input', hitungAngsuran);
angsurPokok.addEventListener('change', hitungAngsuran);
jumlahDibayar.addEventListener('input', updateSisaAngsuran);

</script>

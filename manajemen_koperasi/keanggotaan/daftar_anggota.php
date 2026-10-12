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

$conn     = bukakoneksikoperasi();
$db_induk = $db_name;

// === PROSES UPDATE JABATAN hanya untuk administrator ===
if (($_SESSION['role'] ?? '') === 'administrator'
    && $_SERVER['REQUEST_METHOD'] === 'POST'
    && isset($_POST['no_anggota'], $_POST['kode_jab'])) {

    $no_anggota = trim($_POST['no_anggota']);
    $kode_jab   = trim($_POST['kode_jab']);

    if ($no_anggota && $kode_jab !== '') {
        $stmt = $conn->prepare("UPDATE daftar_anggota SET kode_jab=? WHERE no_anggota=?");
        $stmt->bind_param("ss", $kode_jab, $no_anggota);

        if ($stmt->execute()) {
            header("Location: daftar_anggota.php");
            exit;
        } else {
            echo "<div class='alert alert-danger text-center mt-3'>❌ DB ERROR: ".$stmt->error."</div>";
        }
        $stmt->close();
    }
}

// === SINKRONISASI DATA DARI TABEL pengajuan_anggota ===
$sqlAll = "
SELECT
CASE
  WHEN pa.no_daftar IS NULL AND kg.koperasi = 'Y'
    THEN CONCAT('0000-', pg.nik)              -- anggota lama dari DB induk
  WHEN pa.status = 'Disetujui'
    THEN CONCAT(YEAR(pa.tgl_pembuatan), '-', pg.nik) -- anggota baru
  ELSE NULL                                   -- calon/ditolak → tidak masuk
END AS no_anggota,
pg.nik AS id_user,
pg.nama,
d.nama AS departemen,
pa.status AS status_pengajuan,
COALESCE(pa.tgl_pembuatan, CURDATE()) AS tgl_terdaftar,
pa.simp_pokok,
kg.koperasi
FROM {$db_induk}.pegawai pg
INNER JOIN {$db_induk}.departemen d ON pg.departemen = d.dep_id
INNER JOIN {$db_induk}.keanggotaan kg ON kg.id = pg.id
LEFT JOIN pengajuan_anggota pa ON pa.id_user = pg.nik
WHERE (pa.status = 'Disetujui' OR kg.koperasi = 'Y')
ORDER BY no_anggota ASC;
";

$resSync = $conn->query($sqlAll);
if ($resSync) {
    while ($row = $resSync->fetch_assoc()) {
        $status_pengajuan = $row['status_pengajuan'] ?? null;

        // Anggota lama dari DB induk
        if ($status_pengajuan === null && $row['koperasi'] === 'Y') {
            $status_keanggotaan = 'Aktif';
            $kode_jab = 'kop_005'; // Anggota lama
        }
        // Anggota baru yang sudah disetujui
        elseif ($status_pengajuan === 'Disetujui') {
            $status_keanggotaan = 'Aktif';
            $kode_jab = 'kop_005'; // Anggota baru
        }
        // selain itu (calon, ditolak, tidak aktif) → skip
        else {
            continue;
        }

        $stmt = $conn->prepare("
            INSERT INTO daftar_anggota 
            (no_anggota, id_user, status_keanggotaan, tgl_terdaftar, kode_jab) 
            VALUES (?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE 
                status_keanggotaan = VALUES(status_keanggotaan),
                tgl_terdaftar = IF(daftar_anggota.tgl_terdaftar IS NULL, VALUES(tgl_terdaftar), daftar_anggota.tgl_terdaftar)
        ");
        $stmt->bind_param(
            "sssss",
            $row['no_anggota'],
            $row['id_user'],
            $status_keanggotaan,
            $row['tgl_terdaftar'],
            $kode_jab
        );
        $stmt->execute();
        $stmt->close();
    }
}

// === AMBIL DATA UNTUK TAMPILAN ===
$sqlView = "
SELECT 
    da.no_anggota,
    da.id_user,
    pg.nama,
    d.nama AS departemen,
    da.status_keanggotaan,
    da.tgl_terdaftar,
    da.kode_jab,
    jk.jab AS jabatan
FROM daftar_anggota da
INNER JOIN {$db_induk}.pegawai pg ON pg.nik = da.id_user
INNER JOIN {$db_induk}.departemen d ON pg.departemen = d.dep_id
LEFT JOIN jabatan_koperasi jk ON da.kode_jab = jk.kode_jab
WHERE da.status_keanggotaan = 'Aktif'
ORDER BY da.no_anggota ASC
";
$resultView = $conn->query($sqlView);
$totalData = $resultView ? mysqli_num_rows($resultView) : 0;

// Ambil daftar jabatan untuk dropdown
$listJabatan = [];
$resJab = $conn->query("SELECT kode_jab, jab FROM jabatan_koperasi ORDER BY kode_jab ASC");
if ($resJab) {
    while ($rowJab = $resJab->fetch_assoc()) {
        $listJabatan[] = $rowJab;
    }
}

?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <title>Daftar Anggota Koperasi</title>
  <!-- AdminLTE & Bootstrap 5 -->
  <link rel="stylesheet" href="../vendor/adminlte/plugins/fontawesome-free/css/all.min.css">
  <link rel="stylesheet" href="../vendor/adminlte/dist/css/adminlte.min.css">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.1/dist/css/bootstrap.min.css">
  <style>
    .table-responsive { overflow-x: auto; }
    table th, table td { white-space: nowrap; }
  </style>
</head>
<body class="hold-transition sidebar-mini layout-fixed">
<div class="wrapper">

  <!-- Navbar -->
  <?php include __DIR__ . '/../layout/header.php'; ?>

  <!-- Sidebar -->
  <?php include __DIR__ . '/../layout/sidebar.php'; ?>

  <!-- Content Wrapper -->
  <div class="content-wrapper">
    <section class="content-header">
      <div class="container-fluid">
        <h1 class="text-uppercase">Daftar Anggota Koperasi</h1>
      </div>
    </section>

    <section class="content">
      <div class="container-fluid">
        <div class="card shadow">
          <div class="card-header bg-primary text-white d-flex justify-content-between align-items-center">
            <h5 class="mb-0 text-uppercase flex-grow-1 text-center">Daftar Anggota</h5>
            <div class="d-flex gap-2">
              <a href="../index.php" class="btn btn-secondary btn-sm">⬅️ Kembali</a>
            </div>
          </div>

          <div class="table-responsive">
            <table class="table table-striped table-bordered table-sm align-middle">
              <thead class="table-dark text-center">
                <tr>
                  <th>No</th>
                  <th>No. Anggota</th>
                  <th>Nama</th>
                  <th>Departemen</th>
                  <th>Status</th>
                  <th>Tanggal Terdaftar</th>
                  <th>Jabatan</th>
                  <?php if (($_SESSION['role'] ?? '') === 'administrator'): ?>
                    <th>Aksi</th>
                  <?php endif; ?>
                </tr>
              </thead>
              <tbody>
                <?php
                $no = 1;
                if ($resultView && $resultView->num_rows > 0) {
                    while ($row = $resultView->fetch_assoc()) {
                        echo "<tr>
                                <td class='text-center'>{$no}</td>
                                <td>".htmlspecialchars($row['no_anggota'])."</td>
                                <td>".htmlspecialchars($row['nama'])."</td>
                                <td>".htmlspecialchars($row['departemen'])."</td>
                                <td class='text-center'>".htmlspecialchars($row['status_keanggotaan'])."</td>
                                <td class='text-center'>".htmlspecialchars($row['tgl_terdaftar'])."</td>
                                <td class='text-center'>".htmlspecialchars($row['jabatan'])."</td>";
                        if (($_SESSION['role'] ?? '') === 'administrator') {
                            echo "<td class='text-center'>
                                    <button class='btn btn-warning btn-sm editBtn'
                                      data-no='".htmlspecialchars($row['no_anggota'] ?? '')."'
                                      data-kode='".htmlspecialchars($row['kode_jab'] ?? '')."'>
                                      👔 Ubah Jabatan
                                    </button>
                                  </td>";
                        }
                        echo "</tr>";
                        $no++;
                    }
                }
                ?>
              </tbody>
            </table>
            <div class="mt-2 small text-start text-muted">Total Data : <?= $totalData ?></div>
          </div>
        </div>
      </div>
    </section>
  </div>

  <!-- Footer -->
  <?php include __DIR__ . '/../layout/footer.php'; ?>

</div>

<?php if (($_SESSION['role'] ?? '') === 'administrator'): ?>
<!-- Modal Edit Jabatan -->
  <div class="modal fade" id="editModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
      <form method="post" class="modal-content">
        
        <div class="modal-header bg-warning">
          <h5 class="modal-title">Ubah Jabatan Anggota</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body">
          <input type="hidden" name="no_anggota" id="editNo">
          <div class="mb-3">
            <label class="form-label">Jabatan</label>
            <select class="form-select" name="kode_jab" id="editJab" required>
              <?php foreach ($listJabatan as $jab): ?>
                <?php if (!in_array($jab['kode_jab'], ['kop_006','kop_007'])): ?>
                  <option value="<?= htmlspecialchars($jab['kode_jab']) ?>">
                    <?= htmlspecialchars($jab['jab']) ?>
                  </option>
                <?php endif; ?>
              <?php endforeach; ?>
            </select>
          </div>
        </div>
        <div class="modal-footer">
          <button type="submit" class="btn btn-primary">💾 Simpan</button>
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">❌ Batal</button>
        </div>
      </form>
    </div>
  </div>
<?php endif; ?>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.1/dist/js/bootstrap.bundle.min.js"></script>
<script src="../vendor/adminlte/plugins/jquery/jquery.min.js"></script>
<script src="../vendor/adminlte/plugins/bootstrap/js/bootstrap.bundle.min.js"></script>
<script src="../vendor/adminlte/dist/js/adminlte.min.js"></script>

<?php if (($_SESSION['role'] ?? '') === 'administrator'): ?>
<script>
  document.querySelectorAll('.editBtn').forEach(btn => {
    btn.addEventListener('click', function() {
      document.getElementById('editNo').value = this.dataset.no;
      document.getElementById('editJab').value = this.dataset.kode;
      new bootstrap.Modal(document.getElementById('editModal')).show();
    });
  });
</script>
<?php endif; ?>
</body>
</html>

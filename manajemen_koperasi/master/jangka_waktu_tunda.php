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

// === PROSES TAMBAH / EDIT / HAPUS hanya untuk administrator ===
if ($_SESSION['role'] === 'administrator' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    if ($_POST['aksi'] === 'tambah') {
        $kode_jangka = mysqli_real_escape_string($conn, $_POST['kode_jangka']);
        $bln         = mysqli_real_escape_string($conn, $_POST['bln']);
        $kali        = mysqli_real_escape_string($conn, $_POST['kali']);
        mysqli_query($conn, "INSERT INTO jangka_waktu_tunda (kode_jangka, bln, kali) VALUES ('$kode_jangka','$bln','$kali')");
    }
    if ($_POST['aksi'] === 'edit') {
        $kode_jangka = mysqli_real_escape_string($conn, $_POST['kode_jangka']);
        $bln         = mysqli_real_escape_string($conn, $_POST['bln']);
        $kali        = mysqli_real_escape_string($conn, $_POST['kali']);
        mysqli_query($conn, "UPDATE jangka_waktu_tunda SET bln='$bln', kali='$kali' WHERE kode_jangka='$kode_jangka'");
    }
    if ($_POST['aksi'] === 'hapus') {
        $kode_jangka = mysqli_real_escape_string($conn, $_POST['kode_jangka']);
        mysqli_query($conn, "DELETE FROM jangka_waktu_tunda WHERE kode_jangka='$kode_jangka'");
    }
}

// ambil data dari DB
$sql = "SELECT * FROM jangka_waktu_tunda ORDER BY kode_jangka ASC";
$result = mysqli_query($conn, $sql);
$totalData = $result ? mysqli_num_rows($result) : 0;

// generate kode baru otomatis
$sqlLast = "SELECT MAX(kode_jangka) AS lastKode FROM jangka_waktu_tunda";
$resLast = mysqli_query($conn, $sqlLast);
$rowLast = mysqli_fetch_assoc($resLast);
$lastKode = $rowLast['lastKode'] ?? 'jt_000';
$num = (int)substr($lastKode, 3);
$newKode = 'jt_' . str_pad($num + 1, 3, '0', STR_PAD_LEFT);
?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <title>Master Jangka Waktu Tunda</title>
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
        <h1 class="text-uppercase">Master Jangka Waktu Tunda</h1>
      </div>
    </section>

    <section class="content">
      <div class="container-fluid">
        <div class="card shadow">
          <div class="card-header bg-primary text-white d-flex justify-content-between align-items-center">
            <h5 class="mb-0 text-uppercase flex-grow-1 text-center">Daftar Jangka Waktu Tunda</h5>
            <div class="d-flex gap-2">
              <?php if ($_SESSION['role'] === 'administrator'): ?>
                <button class="btn btn-success btn-sm" data-bs-toggle="modal" data-bs-target="#modalTambah">➕ Tambah Data</button>
              <?php endif; ?>
              <a href="../index.php" class="btn btn-secondary btn-sm">⬅️ Kembali</a>
            </div>
          </div>

          <div class="table-responsive">
            <table class="table table-striped table-bordered table-sm align-middle">
              <thead class="table-dark text-center">
                <tr>
                  <th>Kode</th>
                  <th>Lama (bulan)</th>
                  <th>Kali</th>
                  <?php if ($_SESSION['role'] === 'administrator'): ?>
                    <th>Aksi</th>
                  <?php endif; ?>
                </tr>
              </thead>
              <tbody>
                <?php if ($result && $totalData > 0): ?>
                  <?php while($row = mysqli_fetch_assoc($result)): ?>
                  <tr>
                    <td><?= htmlspecialchars($row['kode_jangka']) ?></td>
                    <td><?= htmlspecialchars($row['bln']) ?> bulan</td>
                    <td><?= htmlspecialchars($row['kali']) ?> kali</td>
                    <?php if ($_SESSION['role'] === 'administrator'): ?>
                    <td class="text-center">
                      <button class="btn btn-warning btn-sm" 
                              data-bs-toggle="modal" 
                              data-bs-target="#modalEdit" 
                              data-id="<?= $row['kode_jangka'] ?>" 
                              data-bln="<?= htmlspecialchars($row['bln']) ?>"
                              data-kali="<?= htmlspecialchars($row['kali']) ?>">
                        ✏️ Edit
                      </button>
                      <form method="post" style="display:inline;" onsubmit="return confirm('Yakin hapus data ini?');">
                        <input type="hidden" name="aksi" value="hapus">
                        <input type="hidden" name="kode_jangka" value="<?= $row['kode_jangka'] ?>">
                        <button type="submit" class="btn btn-danger btn-sm">🗑️ Hapus</button>
                      </form>
                    </td>
                    <?php endif; ?>
                  </tr>
                  <?php endwhile; ?>
                <?php else: ?>
                  <tr><td colspan="<?= ($_SESSION['role']==='administrator'?4:3) ?>" class="text-center text-muted">Tidak ada data</td></tr>
                <?php endif; ?>
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

<!-- Modal Tambah -->
<div class="modal fade" id="modalTambah" tabindex="-1" aria-labelledby="modalTambahLabel" aria-hidden="true">
  <div class="modal-dialog">
    <form method="post">
      <div class="modal-content">
        <div class="modal-header bg-success text-white">
          <h5 class="modal-title" id="modalTambahLabel">Tambah Data</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body">
          <input type="hidden" name="aksi" value="tambah">
          <div class="mb-3">
            <label class="form-label">Kode</label>
            <input type="text" class="form-control" name="kode_jangka" value="<?= $newKode ?>" readonly>
          </div>
          <div class="mb-3">
            <label class="form-label">Lama (bulan)</label>
            <input type="number" class="form-control" name="bln" min="1" step="1" required>
          </div>
          <div class="mb-3">
            <label class="form-label">Kali</label>
            <input type="number" class="form-control" name="kali" min="1" step="1" required>
          </div>
        </div>
        <div class="modal-footer">
          <button type="submit" class="btn btn-success">💾 Simpan</button>
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">❌ Batal</button>
        </div>
      </div>
    </form>
  </div>
</div>

<!-- Modal Edit -->
<div class="modal fade" id="modalEdit" tabindex="-1" aria-labelledby="modalEditLabel" aria-hidden="true">
  <div class="modal-dialog">
    <form method="post">
      <div class="modal-content">
        <div class="modal-header bg-warning">
          <h5 class="modal-title" id="modalEditLabel">Edit Data</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body">
          <input type="hidden" name="aksi" value="edit">
          <div class="mb-3">
            <label for="editKodeJangkaDisplay" class="form-label">Kode</label>
            <input type="text" class="form-control" id="editKodeJangkaDisplay" readonly>
            <input type="hidden" id="editKodeJangka" name="kode_jangka">
          </div>
          <div class="mb-3">
            <label for="editBln" class="form-label">Lama (bulan)</label>
            <input type="number" class="form-control" id="editBln" name="bln" min="1" step="1" required>
          </div>
          <div class="mb-3">
            <label for="editKali" class="form-label">Kali</label>
            <input type="number" class="form-control" id="editKali" name="kali" min="1" step="1" required>
          </div>
        </div>
        <div class="modal-footer">
          <button type="submit" class="btn btn-warning">🔄 Update</button>
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">❌ Batal</button>
        </div>
      </div> <!-- /.modal-content -->
    </form>
  </div> <!-- /.modal-dialog -->
</div> <!-- /.modal -->

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.1/dist/js/bootstrap.bundle.min.js"></script>
<script src="../vendor/adminlte/plugins/jquery/jquery.min.js"></script>
<script src="../vendor/adminlte/plugins/bootstrap/js/bootstrap.bundle.min.js"></script>
<script src="../vendor/adminlte/dist/js/adminlte.min.js"></script>

<script>
  var modalEdit = document.getElementById('modalEdit');
  modalEdit.addEventListener('show.bs.modal', function (event) {
    var button = event.relatedTarget;
    var id   = button.getAttribute('data-id');
    var bln  = button.getAttribute('data-bln');
    var kali = button.getAttribute('data-kali');
    modalEdit.querySelector('#editKodeJangka').value = id;
    modalEdit.querySelector('#editKodeJangkaDisplay').value = id;
    modalEdit.querySelector('#editBln').value = bln;
    modalEdit.querySelector('#editKali').value = kali;
  });
</script>
</body>
</html>


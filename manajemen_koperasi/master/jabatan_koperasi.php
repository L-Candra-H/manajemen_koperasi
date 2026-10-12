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
        $kode_jab = mysqli_real_escape_string($conn, $_POST['kode_jab']);
        $jab      = mysqli_real_escape_string($conn, $_POST['jab']);
        mysqli_query($conn, "INSERT INTO jabatan_koperasi (kode_jab, jab) VALUES ('$kode_jab','$jab')");
    }
    if ($_POST['aksi'] === 'edit') {
        $kode_jab = mysqli_real_escape_string($conn, $_POST['kode_jab']);
        $jab      = mysqli_real_escape_string($conn, $_POST['jab']);
        mysqli_query($conn, "UPDATE jabatan_koperasi SET jab='$jab' WHERE kode_jab='$kode_jab'");
    }
    if ($_POST['aksi'] === 'hapus') {
        $kode_jab = mysqli_real_escape_string($conn, $_POST['kode_jab']);
        mysqli_query($conn, "DELETE FROM jabatan_koperasi WHERE kode_jab='$kode_jab'");
    }
}

// ambil data jabatan dari DB
$sql = "SELECT * FROM jabatan_koperasi ORDER BY kode_jab ASC";
$result = mysqli_query($conn, $sql);
$totalData = $result ? mysqli_num_rows($result) : 0;

// generate kode baru otomatis
$sqlLast = "SELECT MAX(kode_jab) AS lastKode FROM jabatan_koperasi";
$resLast = mysqli_query($conn, $sqlLast);
$rowLast = mysqli_fetch_assoc($resLast);
$lastKode = $rowLast['lastKode'] ?? 'kop_000';
$num = (int)substr($lastKode, 4);
$newKode = 'kop_' . str_pad($num + 1, 3, '0', STR_PAD_LEFT);
?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <title>Master Jabatan Koperasi</title>
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
        <h1 class="text-uppercase">Master Jabatan Koperasi</h1>
      </div>
    </section>

    <section class="content">
      <div class="container-fluid">
        <div class="card shadow">
          <div class="card-header bg-primary text-white d-flex justify-content-between align-items-center">
            <h5 class="mb-0 text-uppercase flex-grow-1 text-center">Daftar Jabatan</h5>
            <div class="d-flex gap-2">
              <?php if ($_SESSION['role'] === 'administrator'): ?>
                <button class="btn btn-success btn-sm" data-bs-toggle="modal" data-bs-target="#modalTambah">➕ Tambah Jabatan</button>
              <?php endif; ?>
              <a href="../index.php" class="btn btn-secondary btn-sm">⬅️ Kembali</a>
            </div>
          </div>

          <div class="table-responsive">
             <table class="table table-striped table-bordered table-sm align-middle">
              <thead class="table-dark text-center">
                <tr>
                  <th>Kode</th>
                  <th>Nama Jabatan</th>
                  <?php if ($_SESSION['role'] === 'administrator'): ?>
                    <th>Aksi</th>
                  <?php endif; ?>
                </tr>
              </thead>
              <tbody>
                <?php if ($result && $totalData > 0): ?>
                  <?php while($row = mysqli_fetch_assoc($result)): ?>
                  <tr>
                    <td><?= htmlspecialchars($row['kode_jab']) ?></td>
                    <td><?= htmlspecialchars($row['jab']) ?></td>
                    <?php if ($_SESSION['role'] === 'administrator'): ?>
                    <td class="text-center">
                      <button class="btn btn-warning btn-sm" 
                              data-bs-toggle="modal" 
                              data-bs-target="#modalEdit" 
                              data-id="<?= $row['kode_jab'] ?>" 
                              data-nama="<?= htmlspecialchars($row['jab']) ?>">
                        ✏️ Edit
                      </button>
                      <form method="post" style="display:inline;" onsubmit="return confirm('Yakin hapus jabatan ini?');">
                        <input type="hidden" name="aksi" value="hapus">
                        <input type="hidden" name="kode_jab" value="<?= $row['kode_jab'] ?>">
                        <button type="submit" class="btn btn-danger btn-sm">🗑️ Hapus</button>
                      </form>
                    </td>
                    <?php endif; ?>
                  </tr>
                  <?php endwhile; ?>
                <?php else: ?>
                  <tr><td colspan="<?= ($_SESSION['role']==='administrator'?3:2) ?>" class="text-center text-muted">Tidak ada data</td></tr>
                <?php endif; ?>
              </tbody>
            </table>
            <div class="mt-2 small text-start text-muted">Total Jabatan : <?= $totalData ?></div>
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
          <h5 class="modal-title" id="modalTambahLabel">Tambah Jabatan</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body">
          <input type="hidden" name="aksi" value="tambah">
          <div class="mb-3">
            <label for="kodeJab" class="form-label">Kode Jabatan</label>
            <input type="text" class="form-control" id="kodeJab" name="kode_jab" value="<?= $newKode ?>" readonly>
          </div>
          <div class="mb-3">
            <label for="namaJab" class="form-label">Nama Jabatan</label>
            <input type="text" class="form-control" id="namaJab" name="jab" required>
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
          <h5 class="modal-title" id="modalEditLabel">Edit Jabatan</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body">
          <input type="hidden" name="aksi" value="edit">
          <div class="mb-3">
            <label for="editKodeJabDisplay" class="form-label">Kode Jabatan</label>
            <!-- tampilkan kode jabatan readonly -->
            <input type="text" class="form-control" id="editKodeJabDisplay" readonly>
            <!-- hidden untuk dikirim ke server -->
            <input type="hidden" id="editKodeJab" name="kode_jab">
          </div>
          <div class="mb-3">
            <label for="editNamaJab" class="form-label">Nama Jabatan</label>
            <input type="text" class="form-control" id="editNamaJab" name="jab" required>
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
    var id = button.getAttribute('data-id');
    var nama = button.getAttribute('data-nama');
    modalEdit.querySelector('#editKodeJab').value = id;
    modalEdit.querySelector('#editKodeJabDisplay').value = id;
    modalEdit.querySelector('#editNamaJab').value = nama;
  });
</script>
</body>
</html>


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
        $kode_simp = mysqli_real_escape_string($conn, $_POST['kode_simp']);
        $simp      = mysqli_real_escape_string($conn, $_POST['simp']);
        $nominal   = mysqli_real_escape_string($conn, $_POST['nominal']);
        mysqli_query($conn, "INSERT INTO simpanan (kode_simp, simp, nominal) VALUES ('$kode_simp','$simp','$nominal')");
    }
    if ($_POST['aksi'] === 'edit') {
        $kode_simp = mysqli_real_escape_string($conn, $_POST['kode_simp']);
        $simp      = mysqli_real_escape_string($conn, $_POST['simp']);
        $nominal   = mysqli_real_escape_string($conn, $_POST['nominal']);
        mysqli_query($conn, "UPDATE simpanan SET simp='$simp', nominal='$nominal' WHERE kode_simp='$kode_simp'");
    }
    if ($_POST['aksi'] === 'hapus') {
        $kode_simp = mysqli_real_escape_string($conn, $_POST['kode_simp']);
        mysqli_query($conn, "DELETE FROM simpanan WHERE kode_simp='$kode_simp'");
    }
}

// ambil data simpanan dari DB
$sql = "SELECT * FROM simpanan ORDER BY kode_simp ASC";
$result = mysqli_query($conn, $sql);
$totalData = $result ? mysqli_num_rows($result) : 0;

// generate kode baru otomatis
$sqlLast = "SELECT MAX(kode_simp) AS lastKode FROM simpanan";
$resLast = mysqli_query($conn, $sqlLast);
$rowLast = mysqli_fetch_assoc($resLast);
$lastKode = $rowLast['lastKode'] ?? 'sim_000';
$num = (int)substr($lastKode, 4);
$newKode = 'sim_' . str_pad($num + 1, 3, '0', STR_PAD_LEFT);
?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <title>Master Simpanan</title>
  <!-- AdminLTE & Bootstrap -->
  <link rel="stylesheet" href="../vendor/adminlte/plugins/fontawesome-free/css/all.min.css">
  <link rel="stylesheet" href="../vendor/adminlte/dist/css/adminlte.min.css">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
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
        <h1 class="text-uppercase">Master Simpanan</h1>
      </div>
    </section>

    <section class="content">
      <div class="container-fluid">
        <div class="card shadow">
          <div class="card-header bg-primary text-white d-flex justify-content-between align-items-center">
            <h5 class="mb-0 text-uppercase flex-grow-1 text-center">Daftar Simpanan</h5>
            <div class="d-flex gap-2">
              <?php if ($_SESSION['role'] === 'administrator'): ?>
                <button class="btn btn-success btn-sm" data-bs-toggle="modal" data-bs-target="#modalTambah">➕ Tambah Simpanan</button>
              <?php endif; ?>
              <a href="../index.php" class="btn btn-secondary btn-sm">⬅️ Kembali</a>
            </div>
          </div>

          <div class="table-responsive">
            <table class="table table-striped table-bordered table-sm align-middle">
              <thead class="table-dark text-center">
                <tr>
                  <th>Kode Simpanan</th>
                  <th>Nama Simpanan</th>
                  <th>Nominal (Rp)</th>
                  <?php if ($_SESSION['role'] === 'administrator'): ?>
                    <th>Aksi</th>
                  <?php endif; ?>
                </tr>
              </thead>
              <tbody>
                <?php if ($result && $totalData > 0): ?>
                  <?php while($row = mysqli_fetch_assoc($result)): ?>
                  <tr>
                    <td><?= htmlspecialchars($row['kode_simp']) ?></td>
                    <td><?= htmlspecialchars($row['simp']) ?></td>
                    <td>Rp <?= number_format($row['nominal'],0,',','.') ?></td>
                    <?php if ($_SESSION['role'] === 'administrator'): ?>
                    <td class="text-center">
                      <button class="btn btn-warning btn-sm" 
                              data-bs-toggle="modal" 
                              data-bs-target="#modalEdit" 
                              data-id="<?= $row['kode_simp'] ?>" 
                              data-simp="<?= htmlspecialchars($row['simp']) ?>"
                              data-nominal="<?= htmlspecialchars($row['nominal']) ?>">
                        ✏️ Edit
                      </button>
                      <form method="post" style="display:inline;" onsubmit="return confirm('Yakin hapus simpanan ini?');">
                        <input type="hidden" name="aksi" value="hapus">
                        <input type="hidden" name="kode_simp" value="<?= $row['kode_simp'] ?>">
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
            <div class="mt-2 small text-start text-muted">Total Simpanan : <?= $totalData ?></div>
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
          <h5 class="modal-title" id="modalTambahLabel">Tambah Simpanan</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body">
          <input type="hidden" name="aksi" value="tambah">
          <div class="mb-3">
            <label class="form-label">Kode Simpanan</label>
            <input type="text" class="form-control" name="kode_simp" value="<?= $newKode ?>" readonly>
          </div>
          <div class="mb-3">
            <label class="form-label">Nama Simpanan</label>
            <input type="text" class="form-control" name="simp" required>
          </div>
          <div class="mb-3">
            <label class="form-label">Nominal (Rp)</label>
            <input type="number" class="form-control" name="nominal" min="0" step="1000" required>
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
          <h5 class="modal-title" id="modalEditLabel">Edit Simpanan</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body">
          <input type="hidden" name="aksi" value="edit">
          <div class="mb-3">
            <label for="editKodeSimpDisplay" class="form-label">Kode Simpanan</label>
            <input type="text" class="form-control" id="editKodeSimpDisplay" readonly>
            <input type="hidden" id="editKodeSimp" name="kode_simp">
          </div>
          <div class="mb-3">
            <label for="editSimp" class="form-label">Nama Simpanan</label>
            <input type="text" class="form-control" id="editSimp" name="simp" required>
          </div>
          <div class="mb-3">
            <label for="editNominal" class="form-label">Nominal (Rp)</label>
            <input type="number" class="form-control" id="editNominal" name="nominal" min="0" step="1000" required>
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
    var button   = event.relatedTarget;
    var id       = button.getAttribute('data-id');
    var simp     = button.getAttribute('data-simp');
    var nominal  = button.getAttribute('data-nominal');
    modalEdit.querySelector('#editKodeSimp').value = id;
    modalEdit.querySelector('#editKodeSimpDisplay').value = id;
    modalEdit.querySelector('#editSimp').value = simp;
    modalEdit.querySelector('#editNominal').value = nominal;
  });
</script>
</body>
</html>


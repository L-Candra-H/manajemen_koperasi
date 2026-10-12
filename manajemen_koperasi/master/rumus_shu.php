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

// === PROSES EDIT hanya untuk administrator ===
if ($_SESSION['role'] === 'administrator' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    if ($_POST['aksi'] === 'edit') {
        $id     = (int)$_POST['id_rumus'];
        $nama   = mysqli_real_escape_string($conn, $_POST['nama_rumus']);
        $adm    = (float)$_POST['persen_administrasi'];
        $dibagi = (float)$_POST['persen_dibagi'];
        $pinjam = (float)$_POST['persen_pinjaman'];
        $angg   = (float)$_POST['persen_anggota'];
        $aktif = mysqli_real_escape_string($conn,$_POST['aktif_pinjaman_dua']);

        mysqli_query($conn, "UPDATE rumus_shu 
                             SET nama_rumus='$nama',
                                 persen_administrasi=$adm,
                                 persen_dibagi=$dibagi,
                                 persen_pinjaman=$pinjam,
                                 persen_anggota=$angg,
                                 aktif_pinjaman_dua='$aktif'
                             WHERE id_rumus=$id");
    }
}

// ambil data rumus dari DB
$sql = "SELECT * FROM rumus_shu ORDER BY id_rumus ASC";
$result = mysqli_query($conn, $sql);
$totalData = $result ? mysqli_num_rows($result) : 0;

?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <title>Master Rumus SHU</title>
  <link rel="stylesheet" href="../vendor/adminlte/plugins/fontawesome-free/css/all.min.css">
  <link rel="stylesheet" href="../vendor/adminlte/dist/css/adminlte.min.css">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="hold-transition sidebar-mini layout-fixed">
<div class="wrapper">

  <?php include __DIR__ . '/../layout/header.php'; ?>
  <?php include __DIR__ . '/../layout/sidebar.php'; ?>

  <div class="content-wrapper">
    <section class="content-header">
      <div class="container-fluid">
        <h1 class="text-uppercase">Master Rumus SHU</h1>
      </div>
    </section>

    <section class="content">
      <div class="container-fluid">
        <div class="card shadow">
          <div class="card-header bg-primary text-white d-flex justify-content-between align-items-center">
            <h5 class="mb-0 text-uppercase flex-grow-1 text-center">Master Rumus SHU</h5>
            <div class="d-flex gap-2">
              <a href="../index.php" class="btn btn-secondary btn-sm">⬅️ Kembali</a>
            </div>
          </div>

          <div class="table-responsive">
            <table class="table table-striped table-bordered table-sm align-middle">
              <thead class="table-dark text-center">
                <tr>
                  <th>ID</th>
                  <th>Nama Rumus</th>
                  <th>Administrasi (%)</th>
                  <th>Dibagi (%)</th>
                  <th>Pinjaman (%)</th>
                  <th>Anggota Aktif (%)</th>
                  <th>Hitung Jasa Pinjaman 2</th>
                  <?php if ($_SESSION['role'] === 'administrator'): ?>
                    <th>Aksi</th>
                  <?php endif; ?>
                </tr>
              </thead>
              <tbody>
                <?php if ($result && $totalData > 0): ?>
                  <?php while($row = mysqli_fetch_assoc($result)): ?>
                  <tr>
                    <td><?= $row['id_rumus'] ?></td>
                    <td><?= htmlspecialchars($row['nama_rumus']) ?></td>
                    <td><?= $row['persen_administrasi'] ?></td>
                    <td><?= $row['persen_dibagi'] ?></td>
                    <td><?= $row['persen_pinjaman'] ?></td>
                    <td><?= $row['persen_anggota'] ?></td>
                    <td class="text-center">
                    <?php if ($row['aktif_pinjaman_dua'] == 'Y'): ?>
                        <span class="badge bg-success">Aktif</span>
                    <?php else: ?>
                        <span class="badge bg-secondary">Tidak Aktif</span>
                    <?php endif; ?>
                    </td>
                    <?php if ($_SESSION['role'] === 'administrator'): ?>
                    <td class="text-center">
                      <button class="btn btn-warning btn-sm" 
                              data-bs-toggle="modal" 
                              data-bs-target="#modalEdit" 
                              data-id="<?= $row['id_rumus'] ?>" 
                              data-nama="<?= htmlspecialchars($row['nama_rumus']) ?>"
                              data-adm="<?= $row['persen_administrasi'] ?>"
                              data-dibagi="<?= $row['persen_dibagi'] ?>"
                              data-pinjaman="<?= $row['persen_pinjaman'] ?>"
                              data-anggota="<?= $row['persen_anggota'] ?>"
                              data-aktif="<?= $row['aktif_pinjaman_dua'] ?>">
                        ✏️ Edit
                      </button>
                    </td>
                    <?php endif; ?>
                  </tr>
                  <?php endwhile; ?>
                <?php else: ?>
                  <tr><td colspan="<?= ($_SESSION['role']==='administrator'?7:6) ?>" class="text-center text-muted">Tidak ada data</td></tr>
                <?php endif; ?>
              </tbody>
            </table>
            <div class="mt-2 small text-start text-muted">Total Data : <?= $totalData ?></div>
          </div>
        </div>
      </div>
    </section>
  </div>

  <?php include __DIR__ . '/../layout/footer.php'; ?>

</div>

<!-- Modal Edit -->
<div class="modal fade" id="modalEdit" tabindex="-1" aria-labelledby="modalEditLabel" aria-hidden="true">
  <div class="modal-dialog">
    <form method="post">
      <div class="modal-content">
        <div class="modal-header bg-warning">
          <h5 class="modal-title" id="modalEditLabel">Edit Rumus SHU</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body">
          <input type="hidden" name="aksi" value="edit">
          <input type="hidden" id="editIdRumus" name="id_rumus">
          <div class="mb-3">
            <label class="form-label">Nama Rumus</label>
            <input type="text" class="form-control" id="editNamaRumus" name="nama_rumus" required>
          </div>
          <div class="mb-3">
            <label class="form-label">Administrasi (%)</label>
            <input type="number" class="form-control" id="editAdm" name="persen_administrasi" step="0.01" required>
          </div>
          <div class="mb-3">
            <label class="form-label">Dibagi (%)</label>
            <input type="number" class="form-control" id="editDibagi" name="persen_dibagi" step="0.01" readonly>
          </div>
          <div class="mb-3">
            <label class="form-label">Pinjaman (%)</label>
            <input type="number" class="form-control" id="editPinjaman" name="persen_pinjaman" step="0.01" required>
          </div>
          <div class="mb-3">
            <label class="form-label">Anggota Aktif (%)</label>
            <input type="number" class="form-control" id="editAnggota" name="persen_anggota" step="0.01" readonly>
          </div>
          <div class="mb-3">
            <label class="form-label">Hitung Jasa Pinjaman Dua</label>
            <select class="form-select" id="editAktif" name="aktif_pinjaman_dua" required>
                <option value="Y">Aktif (Ikut Perhitungan SHU)</option>
                <option value="T">Tidak Aktif (Tampil Tapi Tidak Dihitung)</option>
            </select>
          </div>
        </div>
        <div class="modal-footer">
          <button type="submit" class="btn btn-warning">🔄 Update</button>
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">❌ Batal</button>
        </div>
      </div>
    </form>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.1/dist/js/bootstrap.bundle.min.js"></script>
<script src="../vendor/adminlte/plugins/jquery/jquery.min.js"></script>
<script src="../vendor/adminlte/plugins/bootstrap/js/bootstrap.bundle.min.js"></script>
<script src="../vendor/adminlte/dist/js/adminlte.min.js"></script>

<script>
  var modalEdit = document.getElementById('modalEdit');
  modalEdit.addEventListener('show.bs.modal', function (event) {
    var button = event.relatedTarget;
    var id     = button.getAttribute('data-id');
    var nama   = button.getAttribute('data-nama');
    var adm    = button.getAttribute('data-adm');
    var dibagi = button.getAttribute('data-dibagi');
    var pinjam = button.getAttribute('data-pinjaman');
    var anggota= button.getAttribute('data-anggota');
    var aktif = button.getAttribute('data-aktif');

    modalEdit.querySelector('#editIdRumus').value = id;
    modalEdit.querySelector('#editNamaRumus').value = nama;
    modalEdit.querySelector('#editAdm').value = adm;
    modalEdit.querySelector('#editDibagi').value = dibagi;
    modalEdit.querySelector('#editPinjaman').value = pinjam;
    modalEdit.querySelector('#editAnggota').value = anggota;
    modalEdit.querySelector('#editAktif').value = aktif;
  });

  // otomatis isi Dibagi ketika Administrasi diubah (level 1)
  document.getElementById('editAdm').addEventListener('input', function() {
    let adm = parseFloat(this.value) || 0;
    let dibagi = 100 - adm;
    document.getElementById('editDibagi').value = dibagi.toFixed(2);
  });

  // otomatis isi Anggota Aktif ketika Pinjaman diubah (level 2)
  document.getElementById('editPinjaman').addEventListener('input', function() {
    let pinjam = parseFloat(this.value) || 0;
    let anggota = 100 - pinjam; // pecahan dari 90% yang dianggap 100%
    document.getElementById('editAnggota').value = anggota.toFixed(2);
  });
</script>
</body>
</html>

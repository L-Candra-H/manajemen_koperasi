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
$role     = $_SESSION['role']     ?? '';
$kode_jab = $_SESSION['kode_jab'] ?? '';

// Ambil data induk koperasi
$sql = "SELECT di.no_anggota, di.id_user, di.status_keanggotaan, di.tgl_terdaftar,
               jk.jab AS jabatan_koperasi,
               di.dana_pensiun, di.simpanan_wajib_lama, di.simpanan_pokok, di.simpanan_wajib, di.tabungan,
               pg.nama
        FROM data_induk_koperasi di
        LEFT JOIN jabatan_koperasi jk ON di.kode_jab = jk.kode_jab
        LEFT JOIN {$db_induk}.pegawai pg ON di.id_user = pg.nik
        ORDER BY di.no_anggota ASC";
$result = $conn->query($sql);
$totalData = $result ? mysqli_num_rows($result) : 0;
?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <title>Data Induk Koperasi</title>
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
            <h5 class="mb-0 text-uppercase flex-grow-1 text-center">Data Induk Koperasi</h5>
            <div class="d-flex gap-2">
              <?php if ($role === 'administrator' || $kode_jab === 'kop_003'): ?>
                <a href="induk_sync_update.php?action=sinkronisasi" class="btn btn-info btn-sm">🔄 Sinkronisasi Data Induk</a>
              <?php endif; ?>
              <a href="../index.php" class="btn btn-secondary btn-sm">⬅️ Kembali</a>
            </div>
          </div>

          <div class="table-responsive">
            <table class="table table-striped table-bordered table-sm align-middle">
              <thead class="table-dark text-center">
                <tr>
                  <th>No Anggota</th>
                  <th>Nama</th>
                  <th>Status</th>
                  <th>Tgl Terdaftar</th>
                  <th>Jabatan Koperasi</th>
                  <th>Dana Pensiun</th>
                  <th>Simpanan Wajib Lama</th>
                  <th>Simpanan Pokok</th>
                  <th>Simpanan Wajib</th>
                  <th>Tabungan</th>
                  <?php if ($role === 'administrator' || $kode_jab === 'kop_003'): ?>
                    <th>Aksi</th>
                  <?php endif; ?>
                </tr>
              </thead>
              <tbody>
                <?php if ($result && $result->num_rows > 0): ?>
                  <?php while ($row = $result->fetch_assoc()): ?>
                    <tr>
                      <td><?= htmlspecialchars($row['no_anggota']) ?></td>
                      <td><?= htmlspecialchars($row['nama']) ?></td>
                      <td><?= htmlspecialchars($row['status_keanggotaan']) ?></td>
                      <td><?= htmlspecialchars($row['tgl_terdaftar']) ?></td>
                      <td><?= htmlspecialchars($row['jabatan_koperasi']) ?></td>
                      <td class="text-end"><?= number_format($row['dana_pensiun'], 2) ?></td>
                      <td class="text-end"><?= number_format($row['simpanan_wajib_lama'], 2) ?></td>
                      <td class="text-end"><?= number_format($row['simpanan_pokok'], 2) ?></td>
                      <td class="text-end"><?= number_format($row['simpanan_wajib'], 2) ?></td>
                      <td class="text-end"><?= number_format($row['tabungan'], 2) ?></td>
                      <?php if ($role === 'administrator' || $kode_jab === 'kop_003'): ?>
                        <td class="text-center">
                          <button class="btn btn-sm btn-warning"
                                  data-bs-toggle="modal"
                                  data-bs-target="#editModal"
                                  data-id="<?= $row['id_user'] ?>"
                                  data-no="<?= $row['no_anggota'] ?>"
                                  data-nama="<?= $row['nama'] ?>"
                                  data-status="<?= $row['status_keanggotaan'] ?>"
                                  data-dana="<?= $row['dana_pensiun'] ?>"
                                  data-wajib-lama="<?= $row['simpanan_wajib_lama'] ?>"
                                  data-simpanan="<?= $row['simpanan_pokok'] ?>"
                                  data-wajib="<?= $row['simpanan_wajib'] ?>"
                                  data-tabungan="<?= $row['tabungan'] ?>">
                            ✏️ Edit
                          </button>
                        </td>
                      <?php endif; ?>
                    </tr>
                  <?php endwhile; ?>
                <?php else: ?>
                  <tr>
                    <td colspan="10" class="text-center text-muted">Belum ada data induk koperasi</td>
                  </tr>
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

<!-- Modal Edit Data Induk -->
<div class="modal fade" id="editModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-centered">
    <div class="modal-content">
      <form method="post" action="induk_sync_update.php?action=update">
        <div class="modal-header bg-warning">
          <h5 class="modal-title">Edit Data Induk Koperasi</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body">
          <input type="hidden" name="id_user" id="edit_id_user">
          <input type="hidden" name="no_anggota" id="edit_no_anggota">

          <!-- Identitas Anggota -->
          <div class="card mb-3 border-secondary">
            <div class="card-header bg-light fw-bold">Identitas Anggota</div>
            <div class="card-body">
              <div class="row">
                <div class="col-md-6 mb-3">
                  <label class="form-label">No Anggota</label>
                  <input type="text" class="form-control bg-secondary text-white" id="edit_no_display" readonly>
                </div>
                <div class="col-md-6 mb-3">
                  <label class="form-label">ID User (NIK)</label>
                  <input type="text" class="form-control bg-secondary text-white" id="edit_id_display" readonly>
                </div>
              </div>
              <div class="row">
                <div class="col-md-6 mb-3">
                  <label class="form-label">Nama</label>
                  <input type="text" class="form-control bg-secondary text-white" id="edit_nama" readonly>
                </div>
                <div class="col-md-6 mb-3">
                  <label class="form-label">Status Keanggotaan</label>
                  <select class="form-select" name="status_keanggotaan" id="edit_status">
                    <option value="Aktif">Aktif</option>
                    <option value="Tidak Aktif">Tidak Aktif</option>
                  </select>
                </div>
              </div>
            </div>
          </div>

          <!-- Data Keuangan -->
          <div class="card border-secondary">
            <div class="card-header bg-light fw-bold">Data Keuangan</div>
            <div class="card-body">
              <div class="row">
                <div class="col-md-6 mb-3">
                  <label class="form-label">Dana Pensiun</label>
                  <input type="number" step="0.01" class="form-control"
                         name="dana_pensiun" id="edit_dana">
                </div>
                <div class="col-md-6 mb-3">
                  <label class="form-label">Simpanan Wajib Lama (Input Awal)</label>
                  <input type="number" step="0.01" class="form-control"
                         name="simpanan_wajib_lama" id="edit_wajib_lama">
                </div>
              </div>
              <div class="row">
                <div class="col-md-6 mb-3">
                  <label class="form-label">Simpanan Pokok</label>
                  <input type="number" step="0.01" class="form-control"
                         name="simpanan_pokok" id="edit_simpanan">
                </div>
                <div class="col-md-6 mb-3">
                  <label class="form-label">Simpanan Wajib</label>
                  <input type="number" step="0.01" class="form-control bg-secondary text-white"
                         name="simpanan_wajib" id="edit_wajib" readonly>
                </div>
              </div>

              <div class="row">
                <div class="col-md-12 mb-3">
                  <label class="form-label">Tabungan</label>
                  <input type="number" step="0.01" class="form-control"
                         name="tabungan" id="edit_tabungan">
                </div>
              </div>
            </div>
          </div>
        </div>

        <div class="modal-footer">
          <button type="submit" class="btn btn-success">💾 Simpan</button>
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">❌ Batal</button>
        </div>
      </form>
    </div>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.1/dist/js/bootstrap.bundle.min.js"></script>
<script src="../vendor/adminlte/plugins/jquery/jquery.min.js"></script>
<script src="../vendor/adminlte/plugins/bootstrap/js/bootstrap.bundle.min.js"></script>
<script src="../vendor/adminlte/dist/js/adminlte.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function () {
    var editModal = document.getElementById('editModal');
    editModal.addEventListener('show.bs.modal', function (event) {
        var button = event.relatedTarget;
        document.getElementById('edit_id_user').value    = button.getAttribute('data-id');
        document.getElementById('edit_no_anggota').value = button.getAttribute('data-no');
        document.getElementById('edit_id_display').value = button.getAttribute('data-id');
        document.getElementById('edit_no_display').value = button.getAttribute('data-no');
        document.getElementById('edit_nama').value       = button.getAttribute('data-nama');
        document.getElementById('edit_status').value     = button.getAttribute('data-status');
        document.getElementById('edit_dana').value       = button.getAttribute('data-dana');
        let wajibLama = button.getAttribute('data-wajib-lama');
        document.getElementById('edit_wajib_lama').value = wajibLama;
        document.getElementById('edit_simpanan').value   = button.getAttribute('data-simpanan');
        document.getElementById('edit_wajib').value      = button.getAttribute('data-wajib');
        document.getElementById('edit_tabungan').value   = button.getAttribute('data-tabungan');
        let fieldWajibLama = document.getElementById('edit_wajib_lama');
        if (parseFloat(wajibLama) > 0) {
            fieldWajibLama.readOnly = true;
            fieldWajibLama.classList.add('bg-secondary', 'text-white');
        } else {
            fieldWajibLama.readOnly = false;
            fieldWajibLama.classList.remove('bg-secondary', 'text-white');
        }
    });
});

</script>
</body>
</html>

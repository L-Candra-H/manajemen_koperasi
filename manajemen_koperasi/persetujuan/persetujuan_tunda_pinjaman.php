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
$id_user  = $_SESSION['id_user'];
$kode_jab = $_SESSION['kode_jab'] ?? null;
$role     = $_SESSION['role'] ?? null;

// ambil data pengajuan tunda pinjaman
$sql = "SELECT ptp.no_daftar, pg.nama, ptp.status, ptp.tgl_pengajuan
        FROM pengajuan_tunda_pinjaman ptp
        JOIN {$db_induk}.pegawai pg ON ptp.id_user = pg.nik
        ORDER BY ptp.tgl_pengajuan DESC";
$res = $conn->query($sql);
$totalData = $res ? mysqli_num_rows($res) : 0;

// ambil nama pengurus sesuai jabatan login
if ($role === 'administrator') {
    $namaPengurus = 'Administrator';
} else {
    $sqlPengurus = "SELECT pg.nama
                    FROM daftar_anggota da
                    JOIN {$db_induk}.pegawai pg ON pg.nik = da.id_user
                    WHERE da.kode_jab = ?";
    $stmt = $conn->prepare($sqlPengurus);
    $stmt->bind_param("s", $kode_jab);
    $stmt->execute();
    $resPengurus = $stmt->get_result();
    $namaPengurus = ($resPengurus && $resPengurus->num_rows > 0)
        ? $resPengurus->fetch_assoc()['nama']
        : '-';
    $stmt->close();
}

// === Handler aksi persetujuan tunda ===
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['aksi'])) {
    $no_daftar   = $_POST['no_daftar'];
    $aksi        = $_POST['aksi']; // 'Disetujui' atau 'Ditolak'
    $idPengurus  = $id_user;

    // ambil nomor anggota dari pengajuan tunda
    $resAnggota = $conn->query("SELECT da.no_anggota 
                                FROM pengajuan_tunda_pinjaman ptp
                                JOIN daftar_anggota da ON ptp.id_user = da.id_user
                                WHERE ptp.no_daftar='$no_daftar'");
    $noAnggota = ($resAnggota && $resAnggota->num_rows > 0) ? $resAnggota->fetch_assoc()['no_anggota'] : null;

    // update status pengajuan tunda
    $conn->query("UPDATE pengajuan_tunda_pinjaman 
                  SET status='$aksi' 
                  WHERE no_daftar='$no_daftar'");

    // simpan persetujuan tunda pinjaman
    $stmt = $conn->prepare("INSERT INTO persetujuan_tunda_pinjaman 
        (no_daftar, no_anggota, tgl_pengajuan, id_pengurus, tgl_persetujuan, status) 
        VALUES (?, ?, CURDATE(), ?, CURDATE(), ?)
        ON DUPLICATE KEY UPDATE 
            id_pengurus=VALUES(id_pengurus),
            tgl_persetujuan=VALUES(tgl_persetujuan),
            status=VALUES(status)");
    $stmt->bind_param("ssss", $no_daftar, $noAnggota, $idPengurus, $aksi);
    $stmt->execute();
    $stmt->close();
}

$conn->close();
?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <title>Persetujuan Tunda Pinjaman</title>
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
        <h1 class="text-uppercase">Persetujuan Pinjaman</h1>
      </div>
    </section>

    <section class="content">
      <div class="container-fluid">
        <div class="card shadow">
          <div class="card-header bg-primary text-white d-flex justify-content-between align-items-center">
            <h5 class="mb-0 text-uppercase flex-grow-1 text-center">Persetujuan Tunda Pinjaman</h5>
            <div class="d-flex gap-2">
              <a href="daftar_tunda_pinjaman.php" class="btn btn-secondary btn-sm">⬅️ Kembali</a>
            </div>
          </div>

          <div class="table-responsive">
            <table class="table table-striped table-bordered table-sm align-middle">
              <thead class="table-dark text-center">
                <tr>
                  <th>No</th>
                  <th>No. Pengajuan</th>
                  <th>Nama</th>
                  <th>Status</th>
                  <th>Tanggal Pengajuan</th>
                  <th>Aksi</th>
                </tr>
              </thead>
              <tbody>
                <?php
                $no=1;
                if ($res && $res->num_rows>0) {
                  while ($row=$res->fetch_assoc()) {
                    echo "<tr>
                            <td class='text-center'>{$no}</td>
                            <td>{$row['no_daftar']}</td>
                            <td>{$row['nama']}</td>
                            <td class='text-center'>{$row['status']}</td>
                            <td class='text-center'>{$row['tgl_pengajuan']}</td>";
                    echo "<td class='text-center'>";
                    if ($row['status']==='Diajukan') {
                        echo "<button type='button' class='btn btn-success btn-sm prosesBtn'
                                data-no='".htmlspecialchars($row['no_daftar'])."'
                                data-nama='".htmlspecialchars($row['nama'])."'
                                data-status='".htmlspecialchars($row['status'])."'>✔️ Proses Persetujuan</button>";
                    } else {
                        echo "<span class='text-muted'>-</span>";
                    }
                    echo "</td></tr>";
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

  <!-- Modal Persetujuan -->
  <div class="modal fade" id="prosesModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
      <form method="post" class="modal-content">
        <div class="modal-header bg-success text-white">
          <h5 class="modal-title">Persetujuan Tunda Pinjaman</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body">
          <input type="hidden" name="no_daftar" id="modalNoDaftar">
          <div class="mb-2">
            <label class="form-label">No. Pengajuan</label>
            <input type="text" class="form-control" id="modalNo" readonly>
          </div>
          <div class="mb-2">
            <label class="form-label">Nama Anggota</label>
            <input type="text" class="form-control" id="modalNama" readonly>
          </div>
          <div class="mb-2">
            <label class="form-label">Status Pengajuan</label>
            <input type="text" class="form-control" id="modalStatus" readonly>
          </div>
          <div class="mb-2">
            <label class="form-label">Pengurus</label>
            <input type="text" class="form-control" value="<?= htmlspecialchars($namaPengurus) ?>" readonly>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">❌ Batal</button>
          <button type="submit" name="aksi" value="Disetujui" class="btn btn-success">✔️ Disetujui</button>
          <button type="submit" name="aksi" value="Ditolak" class="btn btn-danger">❌ Ditolak</button>
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
  $(document).on('click', '.prosesBtn', function() {
    $('#modalNoDaftar').val($(this).data('no'));
    $('#modalNo').val($(this).data('no'));
    $('#modalNama').val($(this).data('nama'));
    $('#modalStatus').val($(this).data('status'));
    new bootstrap.Modal(document.getElementById('prosesModal')).show();
  });
</script>
</body>
</html>

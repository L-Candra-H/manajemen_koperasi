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
$role = $_SESSION['role'] ?? '';
if (!in_array($role, $allowedRoles)) {
    header("Location: ../login.php?error=unauthorized");
    exit;
}

$conn = bukakoneksikoperasi();
$db_induk = $db_name;

// update otomatis status jadi Nonaktif jika tanggal akhir sudah lewat
$today = date("Y-m-d");
$conn->query("UPDATE periode_hitung 
              SET status='Nonaktif' 
              WHERE akhir < '$today' 
              AND status='Aktif'");

// proses simpan periode baru
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['simpan'])) {
    $nama_periode = $_POST['nama_periode'];
    $tahun        = $_POST['tahun'];
    $awal         = $_POST['awal'];
    $status       = $_POST['status'];
    $dibuat_oleh  = $_SESSION['user_login'] ?? $_SESSION['id_user'] ?? 'system';

    // pastikan awal tanggal 1
    $awalDate = new DateTime($awal);
    $awalDate->modify('first day of this month');

    // hitung akhir = akhir bulan ke-12
    $akhirDate = clone $awalDate;
    $akhirDate->modify('+11 months'); // bulan ke-12
    $akhirDate->modify('last day of this month');
    $akhir = $akhirDate->format('Y-m-d');

    // 🔍 validasi overlap dengan periode aktif lain
    $cek = $conn->prepare("
      SELECT COUNT(*) AS total 
      FROM periode_hitung 
      WHERE status='Aktif'
        AND (
          (awal <= ? AND akhir >= ?) OR
          (awal <= ? AND akhir >= ?)
        )
    ");
    $awalStr = $awalDate->format('Y-m-d');
    $cek->bind_param("ssss", $awalStr, $awalStr, $akhir, $akhir);
    $cek->execute();
    $hasil = $cek->get_result()->fetch_assoc();
    $cek->close();

    if ($hasil['total'] > 0) {
        echo "<!DOCTYPE html><html><head>
          <meta charset='UTF-8'>
          <script src='https://cdn.jsdelivr.net/npm/sweetalert2@11'></script>
        </head><body>
          <script>
            Swal.fire({
              icon: 'error',
              title: 'Periode Bertabrakan!',
              text: 'Periode baru bertabrakan dengan periode aktif yang sudah ada.',
              confirmButtonText: '⬅️ Kembali',
              confirmButtonColor: '#d33',
              allowOutsideClick: false,
              allowEscapeKey: false
            }).then(() => {
              window.location.href = 'periode_hitung.php';
            });
          </script>
        </body></html>";
        exit;
    }

    // simpan jika lolos validasi
    $stmt = $conn->prepare("INSERT INTO periode_hitung 
        (nama_periode, tahun, awal, akhir, status, dibuat_oleh) 
        VALUES (?, ?, ?, ?, ?, ?)");
    $stmt->bind_param("ssssss", $nama_periode, $tahun, $awalStr, $akhir, $status, $dibuat_oleh);
    $stmt->execute();
    $stmt->close();
}

// proses ubah status manual (khusus administrator)
if (isset($_GET['aksi']) && $_GET['aksi'] === 'ubah' && $role === 'administrator') {
    $id     = $_GET['id'] ?? null;
    $status = $_GET['status'] ?? null;

    if ($id && in_array($status, ['Aktif','Nonaktif'])) {
        $stmt = $conn->prepare("UPDATE periode_hitung SET status=? WHERE id_periode=?");
        $stmt->bind_param("si", $status, $id);
        $stmt->execute();
        $stmt->close();
    }

    header("Location: periode_hitung.php");
    exit;
}

$res = $conn->query("
  SELECT ph.*, pg.nama AS nama_pembuat
  FROM periode_hitung ph
  LEFT JOIN ".$db_induk.".pegawai pg 
         ON ph.dibuat_oleh = pg.nik
  ORDER BY ph.id_periode DESC
");

// ambil data periode hitung dari DB
$sql = "SELECT * FROM periode_hitung ORDER BY id_periode ASC";
$result = mysqli_query($conn, $sql);
$totalData = $result ? mysqli_num_rows($result) : 0;

?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <title>Master Periode Hitung</title>
  <!-- AdminLTE & Bootstrap 5 -->
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
        <h1 class="text-uppercase">Periode Hitung</h1>
      </div>
    </section>

    <section class="content">
      <div class="container-fluid">
        <div class="card shadow">
          <div class="card-header bg-primary text-white d-flex justify-content-between align-items-center">
            <h5 class="mb-0 text-uppercase flex-grow-1 text-center">Periode Hitung</h5>
            <div class="d-flex gap-2">
              <a href="../index.php" class="btn btn-secondary btn-sm">⬅️ Kembali</a>
            </div>
          </div>

          <div class="card-body p-3">
            <!-- Form input periode -->
            <form method="post" class="row g-3 mb-4">
              <div class="col-md-2">
                <input type="text" name="nama_periode" class="form-control" placeholder="Nama Periode" required>
              </div>
              <div class="col-md-2">
                <select name="tahun" id="tahun" class="form-select" required>
                  <option value="">-- Pilih Tahun --</option>
                  <?php
                    $tahunSekarang = date('Y');
                    for ($tahun = $tahunSekarang; $tahun >= 2024; $tahun--) {
                        echo "<option value='{$tahun}'>{$tahun}</option>";
                    }
                  ?>
                </select>
              </div>

              <div class="col-md-2">
                <input type="month" name="awal" id="awal" class="form-control" required disabled>
              </div>
              <div class="col-md-2">
                <input type="month" name="akhir" id="akhir" class="form-control" readonly>
              </div>
              <div class="col-md-2">
                <select name="status" class="form-select" required>
                  <option value="Aktif">Aktif</option>
                  <option value="Nonaktif">Nonaktif</option>
                </select>
              </div>
              <div class="col-md-2">
                <button type="submit" name="simpan" class="btn btn-success">💾 Simpan</button>
              </div>
            </form>

            <!-- Tabel daftar periode -->
            <div class="table-responsive">
              <table class="table table-striped table-bordered align-middle">
                <thead class="table-dark text-center">
                  <tr>
                    <th>ID</th>
                    <th>Nama Periode</th>
                    <th>Tahun</th>
                    <th>Awal</th>
                    <th>Akhir</th>
                    <th>Status</th>
                    <th>Dibuat Oleh</th>
                    <th>Dibuat Pada</th>
                    <?php if ($role === 'administrator'): ?>
                      <th>Aksi</th>
                    <?php endif; ?>
                  </tr>
                </thead>
                <tbody>
                  <?php while($row = $res->fetch_assoc()): ?>
                  <tr>
                    <td class="text-center"><?= $row['id_periode'] ?></td>
                    <td><?= htmlspecialchars($row['nama_periode']) ?></td>
                    <td class="text-center"><?= $row['tahun'] ?></td>
                    <td class="text-center"><?= $row['awal'] ?></td>
                    <td class="text-center"><?= $row['akhir'] ?></td>
                    <td class="text-center"><?= $row['status'] ?></td>
                    <td><?= htmlspecialchars($row['nama_pembuat'] ?? $row['dibuat_oleh']) ?></td>
                    <td><?= $row['dibuat_pada'] ?></td>
                    <?php if ($role === 'administrator'): ?>
                      <td class="text-center">
                        <?php if ($row['status'] === 'Aktif'): ?>
                          <a href="periode_hitung.php?aksi=ubah&id=<?= $row['id_periode'] ?>&status=Nonaktif" 
                             class="btn btn-sm btn-danger">Nonaktifkan</a>
                        <?php else: ?>
                          <a href="periode_hitung.php?aksi=ubah&id=<?= $row['id_periode'] ?>&status=Aktif" 
                             class="btn btn-sm btn-success">Aktifkan</a>
                        <?php endif; ?>
                      </td>
                    <?php endif; ?>
                  </tr>
                  <?php endwhile; ?>
                </tbody>
              </table>
              <div class="mt-2 small text-start text-muted">Total Periode : <?= $totalData ?></div>
            </div>
          </div>
        </div>
      </div>
    </section>
  </div>

  <!-- Footer -->
  <?php include __DIR__ . '/../layout/footer.php'; ?>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.1/dist/js/bootstrap.bundle.min.js"></script>
<script src="../vendor/adminlte/plugins/jquery/jquery.min.js"></script>
<script src="../vendor/adminlte/plugins/bootstrap/js/bootstrap.bundle.min.js"></script>
<script src="../vendor/adminlte/dist/js/adminlte.min.js"></script>

<script>
document.addEventListener('DOMContentLoaded', function() {
  const tahunSelect = document.getElementById('tahun');
  const awalInput   = document.getElementById('awal');
  const akhirInput  = document.getElementById('akhir');

  // saat tahun dipilih, aktifkan input awal
  tahunSelect.addEventListener('change', function() {
    if (!tahunSelect.value) {
      awalInput.disabled = true;
      awalInput.value = '';
      akhirInput.value = '';
      return;
    }

    awalInput.disabled = false;
    // batasi agar awal hanya bisa di tahun terpilih
    awalInput.min = `${tahunSelect.value}-01`;
    awalInput.max = `${tahunSelect.value}-12`;
    awalInput.value = '';
    akhirInput.value = '';
  });

  // saat awal dipilih, hitung akhir otomatis
  awalInput.addEventListener('change', function() {
    if (!awalInput.value) return;

    let awalDate = new Date(awalInput.value + '-01');
    awalDate.setDate(1);

    let akhirDate = new Date(awalDate);
    akhirDate.setMonth(akhirDate.getMonth() + 11);

    let yyyy = akhirDate.getFullYear();
    let mm = String(akhirDate.getMonth() + 1).padStart(2, '0');
    akhirInput.value = `${yyyy}-${mm}`;
  });
});
</script>

</body>
</html>

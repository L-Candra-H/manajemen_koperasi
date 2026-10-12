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
$conn->query("UPDATE periode_pengaturan 
              SET status='Nonaktif' 
              WHERE tanggal_akhir < '$today' 
              AND status='Aktif'");

// proses simpan periode baru
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['simpan'])) {
    $nama_fitur   = $_POST['nama_fitur'];
    $tanggal_awal = $_POST['tanggal_awal'];
    $tanggal_akhir= $_POST['tanggal_akhir'];
    $status       = $_POST['status'];

    // fallback aman untuk dibuat_oleh
    $dibuat_oleh  = $_SESSION['user_login'] ?? $_SESSION['id_user'] ?? 'system';

    // 🔍 validasi: ambil nama fitur yang sedang Aktif
    $cekAktif = $conn->prepare("
        SELECT nama_fitur 
        FROM periode_pengaturan 
        WHERE status = 'Aktif'
    ");
    $cekAktif->execute();
    $hasilAktif = $cekAktif->get_result();
    $fiturAktif = [];
    while ($row = $hasilAktif->fetch_assoc()) {
        $fiturAktif[] = $row['nama_fitur'];
    }
    $cekAktif->close();

    if (in_array($nama_fitur, $fiturAktif)) {
        echo "<!DOCTYPE html><html><head>
          <meta charset='UTF-8'>
          <script src='https://cdn.jsdelivr.net/npm/sweetalert2@11'></script>
        </head><body>
          <script>
            Swal.fire({
              icon: 'error',
              title: 'Duplikat Fitur!',
              text: 'Pengajuan fitur \"$nama_fitur\" sudah ada yang Aktif. Tidak boleh duplikat.',
              confirmButtonText: '⬅️ Kembali',
              confirmButtonColor: '#d33',
              allowOutsideClick: false,
              allowEscapeKey: false
            }).then(() => {
              window.location.href = 'periode_pengaturan.php';
            });
          </script>
        </body></html>";
        exit;
    }

    // simpan jika lolos validasi
    $stmt = $conn->prepare("INSERT INTO periode_pengaturan 
        (nama_fitur, tanggal_awal, tanggal_akhir, status, dibuat_oleh) 
        VALUES (?, ?, ?, ?, ?)");
    $stmt->bind_param("sssss", $nama_fitur, $tanggal_awal, $tanggal_akhir, $status, $dibuat_oleh);
    $stmt->execute();
    $stmt->close();

    // notif sukses
    echo "<!DOCTYPE html><html><head>
      <meta charset='UTF-8'>
      <script src='https://cdn.jsdelivr.net/npm/sweetalert2@11'></script>
    </head><body>
      <script>
        Swal.fire({
          icon: 'success',
          title: 'Berhasil!',
          text: 'Periode pengaturan baru berhasil disimpan.',
          confirmButtonText: 'OK',
          confirmButtonColor: '#3085d6'
        }).then(() => {
          window.location.href = 'periode_pengaturan.php';
        });
      </script>
    </body></html>";
    exit;
}

// proses ubah status manual (khusus administrator)
if (isset($_GET['aksi']) && $_GET['aksi'] === 'ubah' && $role === 'administrator') {
    $id     = $_GET['id'] ?? null;
    $status = $_GET['status'] ?? null;

    if ($id && in_array($status, ['Aktif','Nonaktif'])) {
        $stmt = $conn->prepare("UPDATE periode_pengaturan SET status=? WHERE id_periode=?");
        $stmt->bind_param("si", $status, $id);
        $stmt->execute();
        $stmt->close();
    }

    header("Location: periode_pengaturan.php");
    exit;
}

// ambil daftar periode + nama pembuat dari DB induk
$res = $conn->query("
  SELECT ph.*, pg.nama AS nama_pembuat
  FROM periode_pengaturan ph
  LEFT JOIN ".$db_induk.".pegawai pg 
         ON ph.dibuat_oleh = pg.nik
  ORDER BY ph.id_periode DESC
");

// mapping nama fitur ke label yang lebih enak dibaca
$labelFitur = [
    'pengajuan_pinjaman_dua'   => 'Pengajuan Pinjaman 2',
    'pengajuan_tabungan_baru'  => 'Pengajuan Tabungan Baru',
];

// ambil data periode pengaturan dari DB
$sql = "SELECT * FROM periode_pengaturan ORDER BY id_periode ASC";
$result = mysqli_query($conn, $sql);
$totalData = $result ? mysqli_num_rows($result) : 0;

?>

<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <title>Periode Pengaturan</title>
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
        <h1 class="text-uppercase">Periode Pengaturan</h1>
      </div>
    </section>

    <section class="content">
      <div class="container-fluid">
        <div class="card shadow">
          <div class="card-header bg-primary text-white d-flex justify-content-between align-items-center">
            <h5 class="mb-0 text-uppercase flex-grow-1 text-center">Periode Pengaturan</h5>
            <div class="d-flex gap-2">
              <a href="../index.php" class="btn btn-secondary btn-sm">⬅️ Kembali</a>
            </div>
          </div>

          <div class="card-body p-3">
            <!-- Form input periode -->
            <form method="post" class="row g-3 mb-4">
              <div class="col-md-3">
                <select name="nama_fitur" class="form-select" required>
                  <option value="">-- Pilih Fitur --</option>
                  <option value="pengajuan_pinjaman_dua">Pengajuan Pinjaman 2</option>
                  <option value="pengajuan_tabungan_baru">Pengajuan Tabungan Baru</option>
                </select>
              </div>
              <div class="col-md-2">
                <input type="date" name="tanggal_awal" class="form-control" required>
              </div>
              <div class="col-md-2">
                <input type="date" name="tanggal_akhir" class="form-control" required>
              </div>
              <div class="col-md-3">
                <select name="status" class="form-select" required>
                  <option value="">-- Pilih --</option>
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
                    <th>Nama Fitur</th>
                    <th>Tanggal Awal</th>
                    <th>Tanggal Akhir</th>
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
                    <td><?= htmlspecialchars($labelFitur[$row['nama_fitur']] ?? $row['nama_fitur']) ?></td>
                    <td class="text-center"><?= $row['tanggal_awal'] ?></td>
                    <td class="text-center"><?= $row['tanggal_akhir'] ?></td>
                    <td class="text-center"><?= $row['status'] ?></td>
                    <td><?= htmlspecialchars($row['nama_pembuat'] ?? $row['dibuat_oleh']) ?></td>
                    <td><?= $row['dibuat_pada'] ?></td>
                    <?php if ($role === 'administrator'): ?>
                      <td class="text-center">
                        <?php if ($row['status'] === 'Aktif'): ?>
                          <a href="periode_pengaturan.php?aksi=ubah&id=<?= $row['id_periode'] ?>&status=Nonaktif" 
                             class="btn btn-sm btn-danger">Nonaktifkan</a>
                        <?php else: ?>
                          <a href="periode_pengaturan.php?aksi=ubah&id=<?= $row['id_periode'] ?>&status=Aktif" 
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
</body>
</html>

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
$id_user  = $_SESSION['id_user'];
$role     = $_SESSION['role'] ?? '';
$kode_jab = $_SESSION['kode_jab'] ?? '';

// ambil daftar pengajuan anggota
$sql = "SELECT p.no_daftar, p.id_user, pg.nama, p.simp_pokok, p.angsur_pokok, 
               p.simp_wajib, p.tabungan, p.tgl_pembuatan, p.status
        FROM pengajuan_anggota p
        JOIN {$db_name}.pegawai pg ON p.id_user = pg.nik
        ORDER BY p.tgl_pembuatan DESC, p.no_daftar ASC";
$res = $conn->query($sql);

$result = mysqli_query($conn, $sql);
$totalData = $result ? mysqli_num_rows($result) : 0;

?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <title>Daftar Pengajuan Anggota Koperasi</title>
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
        <h1 class="text-uppercase">Daftar Pengajuan Anggota Koperasi</h1>
      </div>
    </section>

    <section class="content">
      <div class="container-fluid">
        <div class="card shadow">
          <div class="card-header bg-primary text-white d-flex justify-content-between align-items-center">
            <h5 class="mb-0 text-uppercase flex-grow-1 text-center">Daftar Pengajuan Anggota Koperasi</h5>
            <div class="d-flex gap-2">
              <a href="../index.php" class="btn btn-secondary btn-sm">⬅️ Kembali</a>
            </div>
          </div>

          <div class="table-responsive">
            <table class="table table-striped table-bordered table-sm align-middle">
              <thead class="table-dark text-center">
                <tr>
                  <th>No</th>
                  <th>No. Daftar</th>
                  <th>Nama</th>
                  <th>Simpanan Pokok</th>
                  <th>Angsur</th>
                  <th>Simpanan Wajib</th>
                  <th>Tabungan</th>
                  <th>Tanggal</th>
                  <th>Status</th>
                  <?php if (in_array($kode_jab, ['kop_001','kop_002','kop_003']) || $role==='administrator'): ?>
                  <th>Aksi</th>
                  <?php endif; ?>
                </tr>
              </thead>
              <tbody>
                <?php
                $no=1;
                if ($res && $res->num_rows>0) {
                  while($row=$res->fetch_assoc()) {
                    $status = $row['status'];
                    echo "<tr>
                            <td class='text-center'>{$no}</td>
                            <td>{$row['no_daftar']}</td>
                            <td>{$row['nama']}</td>
                            <td class='text-end'>".number_format($row['simp_pokok'])."</td>
                            <td class='text-center'>{$row['angsur_pokok']} kali</td>
                            <td class='text-end'>".number_format($row['simp_wajib'])."</td>
                            <td class='text-end'>".number_format($row['tabungan'])."</td>
                            <td class='text-center'>{$row['tgl_pembuatan']}</td>
                            <td class='text-center'>{$status}</td>";

                    if ($role==='administrator') {
                      echo "<td class='text-center'>
                              <a href='persetujuan_pengajuan_anggota.php?no_daftar={$row['no_daftar']}&aksi=Disetujui' 
                                 class='btn btn-sm btn-success'>✔ Persetujuan</a>
                            </td>";
                    } elseif ($kode_jab==='kop_001' && in_array($status, ['Diteruskan','Dibatalkan'])) {
                      echo "<td class='text-center'>
                              <a href='persetujuan_pengajuan_anggota.php?no_daftar={$row['no_daftar']}&aksi=Diproses' 
                                 class='btn btn-sm btn-success ".($status!=='Diteruskan'?'disabled':'')."'>✔ Proses Ketua</a>
                            </td>";
                    } elseif ($kode_jab==='kop_002' && $status==='Diproses') {
                      echo "<td class='text-center'>
                              <a href='persetujuan_pengajuan_anggota.php?no_daftar={$row['no_daftar']}&aksi=Direkap' 
                                 class='btn btn-sm btn-info'>📑 Proses Sekretaris</a>
                            </td>";
                    } elseif ($kode_jab==='kop_003' && $status==='Direkap') {
                      echo "<td class='text-center'>
                              <a href='persetujuan_pengajuan_anggota.php?no_daftar={$row['no_daftar']}&aksi=Disetujui' 
                                 class='btn btn-sm btn-warning'>💰 Proses Bendahara</a>
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

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.1/dist/js/bootstrap.bundle.min.js"></script>
<script src="../vendor/adminlte/plugins/jquery/jquery.min.js"></script>
<script src="../vendor/adminlte/plugins/bootstrap/js/bootstrap.bundle.min.js"></script>
<script src="../vendor/adminlte/dist/js/adminlte.min.js"></script>

</body>
</html>

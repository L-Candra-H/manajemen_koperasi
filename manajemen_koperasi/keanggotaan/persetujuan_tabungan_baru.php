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

// ambil session aman
$kode_jab   = $_SESSION['kode_jab'] ?? null;
$role  = $_SESSION['role'] ?? null;
$id_user    = $_SESSION['id_user'];

// ambil data pengajuan tabungan baru
$sql = "SELECT p.no_daftar, pg.nama, p.status, p.tgl_pembuatan,
               p.tabungan_baru AS tabungan, p.keterangan, p.id_user
        FROM pengajuan_tabungan_baru p
        JOIN {$db_induk}.pegawai pg ON p.id_user = pg.nik
        ORDER BY p.tgl_pembuatan DESC";
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

// === Handler aksi persetujuan ===
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['aksi'])) {
    $no_daftar = $_POST['no_daftar'];
    $aksi      = $_POST['aksi'];

    // ambil data pengajuan tabungan baru
    $resPengajuan = $conn->query("SELECT id_user, tgl_pembuatan, tabungan_baru AS tabungan, keterangan
                                  FROM pengajuan_tabungan_baru
                                  WHERE no_daftar='$no_daftar'");
    $row      = $resPengajuan ? $resPengajuan->fetch_assoc() : null;
    $idUser   = $row['id_user'] ?? null;
    $tabungan = $row['tabungan'] ?? 0;

    // ambil nomor anggota
    $resAnggota = $conn->query("SELECT no_anggota FROM daftar_anggota WHERE id_user='$idUser'");
    $noAnggota  = ($resAnggota && $resAnggota->num_rows > 0)
        ? $resAnggota->fetch_assoc()['no_anggota']
        : null;

    // === Administrator bypass ===
    if ($aksi === 'Disetujui' && $idUser && $role === 'administrator') {
        $conn->query("UPDATE pengajuan_tabungan_baru SET status='Disetujui' WHERE no_daftar='$no_daftar'");

        $conn->query("INSERT INTO persetujuan_tabungan_baru
            (no_daftar, tgl_pengajuan, id_ketua, id_sekretaris, id_bendahara,
             tgl_persetujuan, tgl_dicatat, tgl_direkap, no_anggota)
            VALUES ('$no_daftar', CURDATE(), '$id_user', '$id_user', '$id_user',
                    CURDATE(), CURDATE(), CURDATE(), '$noAnggota')
            ON DUPLICATE KEY UPDATE
            id_ketua='$id_user', id_sekretaris='$id_user', id_bendahara='$id_user',
            tgl_persetujuan=CURDATE(), tgl_dicatat=CURDATE(), tgl_direkap=CURDATE(),
            no_anggota='$noAnggota'");

        // ringkasan tabungan
        $conn->query("INSERT INTO daftar_tabungan
            (no_daftar, id_user, tabungan)
            VALUES ('$no_daftar', '$idUser', '$tabungan')
            ON DUPLICATE KEY UPDATE tabungan='$tabungan'");

        // update data_induk_koperasi
        $conn->query("UPDATE data_induk_koperasi SET tabungan='$tabungan' WHERE id_user='$idUser'");
    }

    // === Ketua ===
    elseif ($aksi === 'Diproses' && $kode_jab === 'kop_001') {
        $conn->query("UPDATE pengajuan_tabungan_baru SET status='Diproses' WHERE no_daftar='$no_daftar'");
        $conn->query("INSERT INTO persetujuan_tabungan_baru
            (no_daftar, tgl_pengajuan, id_ketua, no_anggota)
            VALUES ('$no_daftar', CURDATE(), '$id_user', '$noAnggota')
            ON DUPLICATE KEY UPDATE id_ketua='$id_user', tgl_persetujuan=CURDATE(), no_anggota='$noAnggota'");
    }

    // === Sekretaris ===
    elseif ($aksi === 'Direkap' && $kode_jab === 'kop_002') {
        $conn->query("UPDATE pengajuan_tabungan_baru SET status='Direkap' WHERE no_daftar='$no_daftar'");
        $conn->query("UPDATE persetujuan_tabungan_baru
                      SET id_sekretaris='$id_user', tgl_dicatat=CURDATE(), no_anggota='$noAnggota'
                      WHERE no_daftar='$no_daftar'");
    }

    // === Bendahara ===
    elseif ($aksi === 'Disetujui' && $kode_jab === 'kop_003') {
        $conn->query("UPDATE pengajuan_tabungan_baru SET status='Disetujui' WHERE no_daftar='$no_daftar'");
        $conn->query("UPDATE persetujuan_tabungan_baru
                      SET id_bendahara='$id_user', tgl_direkap=CURDATE(), no_anggota='$noAnggota'
                      WHERE no_daftar='$no_daftar'");

        // ringkasan tabungan
        $conn->query("INSERT INTO daftar_tabungan
            (no_daftar, id_user, tabungan)
            VALUES ('$no_daftar', '$idUser', '$tabungan')
            ON DUPLICATE KEY UPDATE tabungan='$tabungan'");

        // update data_induk_koperasi
        $conn->query("UPDATE data_induk_koperasi SET tabungan='$tabungan' WHERE id_user='$idUser'");
    }
}
$conn->close();
?>

<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <title>Persetujuan Tabungan Baru</title>
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
        <h1 class="text-uppercase">Persetujuan Tabungan Baru</h1>
      </div>
    </section>

    <section class="content">
      <div class="container-fluid">
        <div class="card shadow">
          <div class="card-header bg-primary text-white d-flex justify-content-between align-items-center">
            <h5 class="mb-0 flex-grow-1 text-center">Daftar Persetujuan Anggota Baru</h5>
            <div class="d-flex gap-2">
              <a href="daftar_tabungan_baru.php" class="btn btn-secondary btn-sm">⬅️ Kembali</a>
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
                  <th>Tanggal Pembuatan</th>
                  <th>Aksi</th>
                </tr>
              </thead>
              <tbody>
                <?php
                $no = 1;
                if ($res && $res->num_rows > 0) {
                  while ($row = $res->fetch_assoc()) {
                    echo "<tr>
                            <td class='text-center'>{$no}</td>
                            <td>{$row['no_daftar']}</td>
                            <td>{$row['nama']}</td>
                            <td class='text-center'>{$row['status']}</td>
                            <td class='text-center'>{$row['tgl_pembuatan']}</td>
                            <td class='text-center'>";

                    $status   = $row['status'];
                    $kodeJab  = $_SESSION['kode_jab'] ?? null;
                    $role = $_SESSION['role'] ?? null;

                    if ($role === 'administrator') {
                      echo "<button type='button' class='btn btn-success btn-sm prosesBtn'
                                data-no='{$row['no_daftar']}'
                                data-nama='{$row['nama']}'
                                data-status='{$status}'>
                                Persetujuan (Admin)
                            </button>";
                    } elseif ($kodeJab === 'kop_001' && $status === 'Diteruskan') {
                      echo "<button type='button' class='btn btn-success btn-sm prosesBtn'
                                data-no='{$row['no_daftar']}'
                                data-nama='{$row['nama']}'
                                data-status='{$status}'>
                                Persetujuan
                            </button>";
                    } elseif ($kodeJab === 'kop_002' && $status === 'Diproses') {
                      echo "<button type='button' class='btn btn-success btn-sm prosesBtn'
                                data-no='{$row['no_daftar']}'
                                data-nama='{$row['nama']}'
                                data-status='{$status}'>
                                Persetujuan
                            </button>";
                    } elseif ($kodeJab === 'kop_003' && $status === 'Direkap') {
                      echo "<button type='button' class='btn btn-success btn-sm prosesBtn'
                                data-no='{$row['no_daftar']}'
                                data-nama='{$row['nama']}'
                                data-status='{$status}'>
                                Persetujuan
                            </button>";
                    } else {
                      echo "<button class='btn btn-secondary btn-sm' disabled>Persetujuan</button>";
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

</div>

<!-- Modal Persetujuan -->
<div class="modal fade" id="prosesModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog">
    <form method="post" class="modal-content">
      <div class="modal-header bg-success text-white">
        <h5 class="mb-0 text-uppercase flex-grow-1 text-center">Persetujuan Pengajuan Tabungan Baru</h5>
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
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
        <?php if ($role === 'administrator'): ?>
          <button type="submit" name="aksi" value="Disetujui" class="btn btn-success">✅ Disetujui</button>
          <button type="submit" name="aksi" value="Ditolak" class="btn btn-danger">❌ Ditolak</button>
        <?php elseif ($kode_jab === 'kop_001'): ?>
          <button type="submit" name="aksi" value="Diproses" class="btn btn-primary">⚙️ Diproses</button>
          <button type="submit" name="aksi" value="Ditolak" class="btn btn-danger">❌ Ditolak</button>
        <?php elseif ($kode_jab === 'kop_002'): ?>
          <button type="submit" name="aksi" value="Direkap" class="btn btn-info">📊 Dicatat</button>
        <?php elseif ($kode_jab === 'kop_003'): ?>
          <button type="submit" name="aksi" value="Disetujui" class="btn btn-success">✅ Disetujui</button>
        <?php endif; ?>
      </div>
    </form>
  </div>
</div>

<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
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

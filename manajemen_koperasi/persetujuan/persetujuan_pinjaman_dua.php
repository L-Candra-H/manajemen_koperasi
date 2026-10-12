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

// ambil daftar pengajuan + persetujuan
$sql = "SELECT p.no_daftar, pg.nama AS nama_anggota, p.status, p.tgl_pembuatan,
               ps.id_ketua, ps.tgl_persetujuan, ps.id_sekretaris, ps.tgl_dicatat,
               ps.id_bendahara, ps.tgl_direkap, ps.no_anggota
        FROM pengajuan_pinjaman_dua p
        JOIN {$db_induk}.pegawai pg ON p.id_user = pg.nik
        LEFT JOIN persetujuan_pinjaman_dua ps ON p.no_daftar = ps.no_daftar
        WHERE p.no_daftar LIKE 'PJD%'
        ORDER BY p.tgl_pembuatan DESC";
$res = $conn->query($sql);
$totalData = $res ? mysqli_num_rows($res) : 0;

// handler aksi persetujuan
if ($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['aksi'])) {
    $no_daftar = $_POST['no_daftar'];
    $aksi      = $_POST['aksi'];

    // ambil detail pengajuan
    $qPengajuan = $conn->query("SELECT id_user, pinjaman, angsur_pinjaman 
                                FROM pengajuan_pinjaman_dua WHERE no_daftar='$no_daftar'");
    $rowPengajuan    = $qPengajuan->fetch_assoc();
    $idUserPengajuan = $rowPengajuan['id_user'] ?? null;
    $pinjaman        = $rowPengajuan['pinjaman'] ?? 0;
    $angsuran        = $rowPengajuan['angsur_pinjaman'] ?? 0;

    // ambil no_anggota
    $qAnggota = $conn->query("SELECT no_anggota FROM daftar_anggota WHERE id_user='$idUserPengajuan'");
    $rowAnggota = $qAnggota->fetch_assoc();
    $noAnggota  = $rowAnggota['no_anggota'] ?? null;

    // === Administrator bypass ===
    if ($role==='administrator' && $aksi==='Disetujui') {
        $conn->query("UPDATE pengajuan_pinjaman_dua SET status='Disetujui' WHERE no_daftar='$no_daftar'");
        $conn->query("INSERT INTO persetujuan_pinjaman_dua(no_daftar,tgl_pengajuan,id_ketua,id_sekretaris,id_bendahara,
                      tgl_persetujuan,tgl_dicatat,tgl_direkap,no_anggota)
                      VALUES('$no_daftar',CURDATE(),'$id_user','$id_user','$id_user',CURDATE(),CURDATE(),CURDATE(),'$noAnggota')
                      ON DUPLICATE KEY UPDATE id_ketua='$id_user',id_sekretaris='$id_user',id_bendahara='$id_user',
                      tgl_persetujuan=CURDATE(),tgl_dicatat=CURDATE(),tgl_direkap=CURDATE(),no_anggota='$noAnggota'");

        // insert ke daftar_pinjaman_dua
        $stmt = $conn->prepare("INSERT INTO daftar_pinjaman_dua
            (no_daftar, id_user, pinjaman, jumlah_dibayar, angsur_pinjaman, sisa_angsuran, status_angsuran)
            VALUES (?, ?, ?, 0, ?, ?, 'Belum Lunas')
            ON DUPLICATE KEY UPDATE pinjaman=VALUES(pinjaman),
                                    angsur_pinjaman=VALUES(angsur_pinjaman),
                                    sisa_angsuran=VALUES(sisa_angsuran),
                                    status_angsuran='Belum Lunas'");
        $stmt->bind_param("ssiii", $no_daftar, $idUserPengajuan, $pinjaman, $angsuran, $angsuran);
        $stmt->execute();
        $stmt->close();
    }
    elseif ($role==='administrator' && $aksi==='Ditolak') {
        $conn->query("UPDATE pengajuan_pinjaman_dua SET status='Ditolak' WHERE no_daftar='$no_daftar'");
    }
    elseif ($kode_jab==='kop_001' && $aksi==='Diproses') {
        $conn->query("UPDATE pengajuan_pinjaman_dua SET status='Diproses' WHERE no_daftar='$no_daftar'");
        $conn->query("INSERT INTO persetujuan_pinjaman_dua(no_daftar,tgl_pengajuan,id_ketua,tgl_persetujuan,no_anggota)
                      VALUES('$no_daftar',CURDATE(),'$id_user',CURDATE(),'$noAnggota')
                      ON DUPLICATE KEY UPDATE id_ketua='$id_user',tgl_persetujuan=CURDATE(),no_anggota='$noAnggota'");
    }
    elseif ($kode_jab==='kop_001' && $aksi==='Ditolak') {
        $conn->query("UPDATE pengajuan_pinjaman_dua SET status='Ditolak' WHERE no_daftar='$no_daftar'");
    }
    elseif ($kode_jab==='kop_002' && $aksi==='Direkap') {
        $conn->query("UPDATE pengajuan_pinjaman_dua SET status='Direkap' WHERE no_daftar='$no_daftar'");
        $conn->query("UPDATE persetujuan_pinjaman_dua SET id_sekretaris='$id_user',tgl_dicatat=CURDATE(),no_anggota='$noAnggota'
                      WHERE no_daftar='$no_daftar'");
    }
    // === Bendahara ===
    elseif ($kode_jab==='kop_003' && $aksi==='Disetujui') {
        $conn->query("UPDATE pengajuan_pinjaman_dua SET status='Disetujui' WHERE no_daftar='$no_daftar'");
        $conn->query("UPDATE persetujuan_pinjaman_dua SET id_bendahara='$id_user',tgl_direkap=CURDATE(),no_anggota='$noAnggota'
                      WHERE no_daftar='$no_daftar'");

        // insert ke daftar_pinjaman_dua
        $stmt = $conn->prepare("INSERT INTO daftar_pinjaman_dua
            (no_daftar, id_user, pinjaman, jumlah_dibayar, angsur_pinjaman, sisa_angsuran, status_angsuran)
            VALUES (?, ?, ?, 0, ?, ?, 'Belum Lunas')
            ON DUPLICATE KEY UPDATE pinjaman=VALUES(pinjaman),
                                    angsur_pinjaman=VALUES(angsur_pinjaman),
                                    sisa_angsuran=VALUES(sisa_angsuran),
                                    status_angsuran='Belum Lunas'");
        $stmt->bind_param("ssiii", $no_daftar, $idUserPengajuan, $pinjaman, $angsuran, $angsuran);
        $stmt->execute();
        $stmt->close();
    }

    header("Location: persetujuan_pinjaman_dua.php");
    exit;
}

?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <title>Persetujuan Pinjaman Lanjutan</title>
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
        <h1 class="text-uppercase">Persetujuan Pinjaman Lanjutan</h1>
      </div>
    </section>

    <section class="content">
      <div class="container-fluid">
        <div class="card shadow">
          <div class="card-header bg-primary text-white d-flex justify-content-between align-items-center">
            <h5 class="mb-0 text-uppercase flex-grow-1 text-center">Daftar Persetujuan Pinjaman Lanjutan</h5>
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
                  <th>No. Anggota</th>
                  <th>Nama Anggota</th>
                  <th>Status</th>
                  <th>Tgl Pengajuan</th>
                  <th>Ketua</th>
                  <th>Tgl Persetujuan</th>
                  <th>Sekretaris</th>
                  <th>Tgl Catat</th>
                  <th>Bendahara</th>
                  <th>Tgl Rekap</th>
                  <th>Aksi</th>
                </tr>
              </thead>
              <tbody>
                <?php
                $no=1;
                if($res && $res->num_rows>0){
                  while($row=$res->fetch_assoc()){
                    echo "<tr>
                            <td class='text-center'>{$no}</td>
                            <td>{$row['no_daftar']}</td>
                            <td class='text-center'>".($row['no_anggota'] ?? '-')."</td>
                            <td>{$row['nama_anggota']}</td>
                            <td class='text-center'>{$row['status']}</td>
                            <td class='text-center'>{$row['tgl_pembuatan']}</td>
                            <td>{$row['id_ketua']}</td>
                            <td class='text-center'>".($row['tgl_persetujuan'] ?? '-')."</td>
                            <td>{$row['id_sekretaris']}</td>
                            <td class='text-center'>".($row['tgl_dicatat'] ?? '-')."</td>
                            <td>{$row['id_bendahara']}</td>
                            <td class='text-center'>".($row['tgl_direkap'] ?? '-')."</td>
                            <td class='text-center'>
                              <form method='post'>
                                <input type='hidden' name='no_daftar' value='{$row['no_daftar']}'>";
                    if($role==='administrator'){
                      echo "<button type='submit' name='aksi' value='Disetujui' class='btn btn-success btn-sm'>✅ Disetujui</button>
                            <button type='submit' name='aksi' value='Ditolak' class='btn btn-danger btn-sm'>❌ Ditolak</button>";
                    } elseif($kode_jab==='kop_001' && $row['status']==='Diteruskan'){
                      echo "<button type='submit' name='aksi' value='Diproses' class='btn btn-primary btn-sm'>⚙️ Diproses</button>
                            <button type='submit' name='aksi' value='Ditolak' class='btn btn-danger btn-sm'>❌ Ditolak</button>";
                    } elseif($kode_jab==='kop_002' && $row['status']==='Diproses'){
                      echo "<button type='submit' name='aksi' value='Direkap' class='btn btn-info btn-sm'>📊 Dicatat</button>";
                    } elseif($kode_jab==='kop_003' && $row['status']==='Direkap'){
                      echo "<button type='submit' name='aksi' value='Disetujui' class='btn btn-success btn-sm'>✅ Disetujui</button>";
                    }
                    echo "</form></td></tr>";
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

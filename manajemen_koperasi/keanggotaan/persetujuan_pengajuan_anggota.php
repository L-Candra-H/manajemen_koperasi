<?php
session_start();
include __DIR__ . '/../conf/auth.php';
include __DIR__ . '/../conf/conf_koperasi.php';
include __DIR__ . '/../conf/conf.php';

error_reporting(0);
ini_set('display_errors', 0);

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
$role= $_SESSION['role'] ?? null;

// === Handler aksi persetujuan ===
if ($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['aksi'])) {
    $no_daftar = $_POST['no_daftar'];
    $aksi      = $_POST['aksi'];

    $resPengajuan = $conn->query("SELECT * FROM pengajuan_anggota WHERE no_daftar='$no_daftar'");
    $row = $resPengajuan ? $resPengajuan->fetch_assoc() : null;
    $idUser   = $row['id_user'] ?? null;
    $tglMulai = $row['tgl_pembuatan'] ?? date('Y-m-d');
    $simpPokok   = $row['simp_pokok'] ?? 0;
    $jmlAngsuran = $row['jumlah_angsuran'] ?? 1;

    if ($role==='administrator') {
        $conn->query("UPDATE pengajuan_anggota SET status='$aksi' WHERE no_daftar='$no_daftar'");
        if ($aksi==='Disetujui' && $idUser) {
            $tahun = date('Y', strtotime($tglMulai));
            $noAnggota = $tahun.'-'.$idUser;

            // simpan persetujuan
            $conn->query("INSERT INTO persetujuan_pengajuan(no_daftar,tgl_pengajuan,id_ketua,id_sekretaris,id_bendahara,
                          tgl_persetujuan,tgl_dicatat,tgl_direkap,no_anggota)
                          VALUES('$no_daftar',CURDATE(),'$id_user','$id_user','$id_user',CURDATE(),CURDATE(),CURDATE(),'$noAnggota')
                          ON DUPLICATE KEY UPDATE no_anggota='$noAnggota'");

            // update daftar anggota
            $conn->query("INSERT INTO daftar_anggota(id_user,no_anggota,status_keanggotaan,kode_jab,tgl_terdaftar)
                          VALUES('$idUser','$noAnggota','Aktif','kop_005',CURDATE())
                          ON DUPLICATE KEY UPDATE no_anggota='$noAnggota',status_keanggotaan='Aktif',kode_jab='kop_005',tgl_terdaftar=CURDATE()");

            // update DB induk
            $conn->query("UPDATE {$db_induk}.keanggotaan SET koperasi='Y' 
                          WHERE id=(SELECT id FROM {$db_induk}.pegawai WHERE nik='$idUser')");

            // insert daftar angsuran pokok
            $conn->query("INSERT INTO daftar_angsuran_pokok(no_daftar,id_user,simp_pokok,jumlah_dibayar,jumlah_angsuran,sisa_angsuran,status_angsuran)
                          VALUES('$no_daftar','$idUser','$simpPokok',0,'$jmlAngsuran','$jmlAngsuran','Belum Lunas')
                          ON DUPLICATE KEY UPDATE simp_pokok='$simpPokok',jumlah_angsuran='$jmlAngsuran',sisa_angsuran='$jmlAngsuran',status_angsuran='Belum Lunas'");
        } elseif ($aksi==='Ditolak' && $idUser) {
            $conn->query("UPDATE pengajuan_anggota SET status='Ditolak' WHERE no_daftar='$no_daftar'");
            $conn->query("UPDATE daftar_anggota SET no_anggota=NULL,status_keanggotaan='Ditolak',kode_jab='kop_007' WHERE id_user='$idUser'");
            $conn->query("UPDATE {$db_induk}.keanggotaan SET koperasi='T' WHERE id=(SELECT id FROM {$db_induk}.pegawai WHERE nik='$idUser')");
        }
    }
    elseif ($kode_jab==='kop_001' && $aksi==='Diproses') {
        $conn->query("UPDATE pengajuan_anggota SET status='Diproses' WHERE no_daftar='$no_daftar'");
        $conn->query("INSERT INTO persetujuan_pengajuan(no_daftar,tgl_pengajuan,id_ketua,tgl_persetujuan)
                      VALUES('$no_daftar',CURDATE(),'$id_user',CURDATE())
                      ON DUPLICATE KEY UPDATE id_ketua='$id_user',tgl_persetujuan=CURDATE()");
    }
    elseif ($kode_jab==='kop_001' && $aksi==='Ditolak') {
        $conn->query("UPDATE pengajuan_anggota SET status='Ditolak' WHERE no_daftar='$no_daftar'");
        $conn->query("UPDATE daftar_anggota SET no_anggota=NULL,status_keanggotaan='Ditolak',kode_jab='kop_007' WHERE id_user='$idUser'");
        $conn->query("UPDATE {$db_induk}.keanggotaan SET koperasi='T' WHERE id=(SELECT id FROM {$db_induk}.pegawai WHERE nik='$idUser')");
    }
    elseif ($kode_jab==='kop_002' && $aksi==='Direkap') {
        $conn->query("UPDATE pengajuan_anggota SET status='Direkap' WHERE no_daftar='$no_daftar'");
        $conn->query("UPDATE persetujuan_pengajuan SET id_sekretaris='$id_user',tgl_dicatat=CURDATE() WHERE no_daftar='$no_daftar'");
    }
    elseif ($kode_jab==='kop_003' && $aksi==='Disetujui') {
        $conn->query("UPDATE pengajuan_anggota SET status='Disetujui' WHERE no_daftar='$no_daftar'");
        $tahun = date('Y', strtotime($tglMulai));
        $noAnggota = $tahun.'-'.$idUser;

        $conn->query("UPDATE persetujuan_pengajuan SET id_bendahara='$id_user',tgl_direkap=CURDATE(),no_anggota='$noAnggota' WHERE no_daftar='$no_daftar'");
        $conn->query("INSERT INTO daftar_anggota(id_user,no_anggota,status_keanggotaan,kode_jab,tgl_terdaftar)
                      VALUES('$idUser','$noAnggota','Aktif','kop_005',CURDATE())
                      ON DUPLICATE KEY UPDATE no_anggota='$noAnggota',status_keanggotaan='Aktif',kode_jab='kop_005',tgl_terdaftar=CURDATE()");
        $conn->query("UPDATE {$db_induk}.keanggotaan SET koperasi='Y' WHERE id=(SELECT id FROM {$db_induk}.pegawai WHERE nik='$idUser')");

        // insert daftar angsuran pokok
        $conn->query("INSERT INTO daftar_angsuran_pokok(no_daftar,id_user,simp_pokok,jumlah_dibayar,jumlah_angsuran,sisa_angsuran,status_angsuran)
                      VALUES('$no_daftar','$idUser','$simpPokok',0,'$jmlAngsuran','$jmlAngsuran','Belum Lunas')
                      ON DUPLICATE KEY UPDATE simp_pokok='$simpPokok',jumlah_angsuran='$jmlAngsuran',sisa_angsuran='$jmlAngsuran',status_angsuran='Belum Lunas'");
    }
}

// === Query data untuk tabel ===
$sql="SELECT p.no_daftar,pg.nama AS nama_anggota,p.status,p.tgl_pembuatan,
             k.nama AS ketua,pp.tgl_persetujuan,
             s.nama AS sekretaris,pp.tgl_dicatat,
             b.nama AS bendahara,pp.tgl_direkap,
             pp.no_anggota
      FROM pengajuan_anggota p
      JOIN {$db_induk}.pegawai pg ON p.id_user=pg.nik
      LEFT JOIN persetujuan_pengajuan pp ON p.no_daftar=pp.no_daftar
      LEFT JOIN {$db_induk}.pegawai k ON pp.id_ketua=k.nik
      LEFT JOIN {$db_induk}.pegawai s ON pp.id_sekretaris=s.nik
      LEFT JOIN {$db_induk}.pegawai b ON pp.id_bendahara=b.nik
      ORDER BY p.tgl_pembuatan DESC";
$res=$conn->query($sql);
$totalData = $res ? mysqli_num_rows($res) : 0;
?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <title>Persetujuan Pengajuan Anggota</title>
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
        <h1 class="text-uppercase">Persetujuan Pengajuan Anggota</h1>
      </div>
    </section>

    <section class="content">
      <div class="container-fluid">
        <div class="card shadow">
          <div class="card-header bg-primary text-white d-flex justify-content-between align-items-center">
            <h5 class="mb-0 text-uppercase flex-grow-1 text-center">Daftar Persetujuan Anggota Baru</h5>
            <div class="d-flex gap-2">
              <a href="daftar_pengajuan_anggota.php" class="btn btn-secondary btn-sm">⬅️ Kembali</a>
            </div>
          </div>

          <div class="table-responsive">
            <table class="table table-striped table-bordered table-sm align-middle">
              <thead class="table-dark text-center">
                <tr>
                  <th>No</th>
                  <th>No. Daftar</th>
                  <th>Nama Anggota</th>
                  <th>Status</th>
                  <th>Tgl Pengajuan</th>
                  <th>Ketua (Persetujuan)</th>
                  <th>Tgl Persetujuan</th>
                  <th>Sekretaris (Catat)</th>
                  <th>Tgl Catat</th>
                  <th>Bendahara (Rekap)</th>
                  <th>Tgl Rekap</th>
                  <th>No. Anggota (Baru)</th>
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
                            <td>{$row['nama_anggota']}</td>
                            <td class='text-center'>{$row['status']}</td>
                            <td class='text-center'>{$row['tgl_pembuatan']}</td>
                            <td>{$row['ketua']}</td>
                            <td class='text-center'>{$row['tgl_persetujuan']}</td>
                            <td>{$row['sekretaris']}</td>
                            <td class='text-center'>{$row['tgl_dicatat']}</td>
                            <td>{$row['bendahara']}</td>
                            <td class='text-center'>{$row['tgl_direkap']}</td>
                            <td class='text-center'>".($row['no_anggota'] ?? '-')."</td>
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

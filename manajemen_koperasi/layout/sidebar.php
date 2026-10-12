<?php
require_once __DIR__ . '/../conf/conf.php';

$db_induk = $db_name;
$q = $conn->query("SELECT * FROM {$db_induk}.setting LIMIT 1");
$setting = $q->fetch_assoc();

$nama_instansi = $setting['nama_instansi'] ?? '';
$line1 = substr($nama_instansi, 0, 18);
$line2 = substr($nama_instansi, 18);

// Inisialisasi variabel umum untuk sidebar
$current  = basename($_SERVER['PHP_SELF']);
$role     = $_SESSION['role']     ?? '';
$kode_jab = $_SESSION['kode_jab'] ?? '';

// Helper fungsi cek akses
function canAccess($role,$kode_jab,$allowed){
  return $role==='administrator' || in_array($kode_jab,$allowed);
}
?>

<!-- Main Sidebar Container -->
<aside class="main-sidebar sidebar-dark-primary elevation-4">
  <!-- Brand Logo -->
  <a href="<?= $base_url ?>index.php" class="brand-link text-left">
    <span class="brand-text font-weight-bold d-block" style="color:#FFD700; font-size:0.9rem;">
      <?= htmlspecialchars($line1) ?>
    </span>
    <span class="brand-text font-weight-bold d-block" style="color:#FFD700; font-size:0.8rem;">
      <?= htmlspecialchars($line2) ?>
    </span>
  </a>

  <!-- Sidebar -->
  <div class="sidebar">
    <!-- Sidebar user panel -->
    <div class="user-panel mt-3 pb-3 mb-3 d-flex">
      <div class="info">
        <a href="#" class="d-block"><?= htmlspecialchars($_SESSION['nama_pegawai'] ?? 'User') ?></a>
      </div>
    </div>

    <!-- Sidebar Menu -->
    <nav class="mt-2">
      <ul class="nav nav-pills nav-sidebar flex-column" data-widget="treeview" role="menu">

        <!-- MASTER DATA -->
        <?php 
          $isMasterActive = in_array($current,[
            'jabatan_koperasi.php','jangka_waktu_tunda.php',
            'lama_peminjaman.php','periode_pengaturan.php',
            'jasa_pinjam.php','simpanan.php',
            'rumus_shu.php','periode_hitung.php'
          ]);
          $showMaster = (
            canAccess($role,$kode_jab,['kop_001','kop_002','kop_003','kop_004','kop_005','kop_006']) ||
            $role==='administrator'
          );
        ?>
        <?php if ($showMaster): ?>
        <li class="nav-item has-treeview <?= $isMasterActive ? 'menu-open' : '' ?>">
          <a href="#" class="nav-link <?= $isMasterActive ? 'active' : '' ?>">
            <i class="nav-icon fas fa-database"></i>
            <p>Master Data<i class="right fas fa-angle-left"></i></p>
          </a>
          <ul class="nav nav-treeview">
            <?php if (canAccess($role,$kode_jab,['kop_001','kop_002','kop_003','kop_004','kop_005'])): ?>
              <li class="nav-item"><a href="<?= $base_url ?>master/jabatan_koperasi.php" class="nav-link <?= $current=='jabatan_koperasi.php'?'active':'' ?>"><p>Jabatan Koperasi</p></a></li>
              <li class="nav-item"><a href="<?= $base_url ?>master/jangka_waktu_tunda.php" class="nav-link <?= $current=='jangka_waktu_tunda.php'?'active':'' ?>"><p>Jangka Waktu Tunda</p></a></li>
              <li class="nav-item"><a href="<?= $base_url ?>master/lama_peminjaman.php" class="nav-link <?= $current=='lama_peminjaman.php'?'active':'' ?>"><p>Lama Peminjaman</p></a></li>
            <?php endif; ?>
            <?php if ($role==='administrator'): ?>
              <li class="nav-item"><a href="<?= $base_url ?>master/jasa_pinjam.php" class="nav-link <?= $current=='jasa_pinjam.php'?'active':'' ?>"><p>Jasa Pinjam</p></a></li>
              <li class="nav-item"><a href="<?= $base_url ?>master/simpanan.php" class="nav-link <?= $current=='simpanan.php'?'active':'' ?>"><p>Simpanan</p></a></li>
            <?php endif; ?>
            <?php if (canAccess($role,$kode_jab,['kop_001','kop_002','kop_003','kop_004'])): ?>
              <li class="nav-item"><a href="<?= $base_url ?>master/periode_pengaturan.php" class="nav-link <?= $current=='periode_pengaturan.php'?'active':'' ?>"><p>Periode Pengaturan</p></a></li>
            <?php endif; ?>
            <?php if (canAccess($role,$kode_jab,['kop_003'])): ?>
              <li class="nav-item"><a href="<?= $base_url ?>master/periode_hitung.php" class="nav-link <?= $current=='periode_hitung.php'?'active':'' ?>"><p>Periode Hitung</p></a></li>
            <?php endif; ?>
            <?php if ($role==='administrator'): ?>
              <li class="nav-item"><a href="<?= $base_url ?>master/rumus_shu.php" class="nav-link <?= $current=='rumus_shu.php'?'active':'' ?>"><p>Rumus SHU</p></a></li>
            <?php endif; ?>
          </ul>
        </li>
        <?php endif; ?>

        <!-- KEANGGOTAAN -->
        <?php 
          $isKeanggotaanActive = in_array($current,[
            'daftar_anggota.php','daftar_pengajuan_anggota.php',
            'persetujuan_pengajuan_anggota.php','daftar_angsuran_pokok.php',
            'detail_angsuran_pokok.php','daftar_pinjaman.php',
            'daftar_pinjaman_dua.php','daftar_tabungan_baru.php',
            'persetujuan_tabungan_baru.php','pendaftaran_anggota.php'
          ]);
          $showKeanggotaan = (
            canAccess($role,$kode_jab,['kop_001','kop_002','kop_003','kop_004','kop_005']) ||
            $role==='administrator'
          );
        ?>
        <?php if ($showKeanggotaan): ?>
        <li class="nav-item has-treeview <?= $isKeanggotaanActive ? 'menu-open' : '' ?>">
          <a href="#" class="nav-link <?= $isKeanggotaanActive ? 'active' : '' ?>">
            <i class="nav-icon fas fa-users"></i>
            <p>Keanggotaan<i class="right fas fa-angle-left"></i></p>
          </a>
          <ul class="nav nav-treeview">
            <li class="nav-item"><a href="<?= $base_url ?>keanggotaan/daftar_anggota.php" class="nav-link <?= $current=='daftar_anggota.php'?'active':'' ?>"><p>Daftar Anggota</p></a></li>
            <?php if (canAccess($role,$kode_jab,['kop_001','kop_002','kop_003','kop_004'])): ?>
              <li class="nav-item"><a href="<?= $base_url ?>keanggotaan/daftar_pengajuan_anggota.php" class="nav-link <?= $current=='daftar_pengajuan_anggota.php'?'active':'' ?>"><p>Daftar Pengajuan Anggota</p></a></li>
              <li class="nav-item"><a href="<?= $base_url ?>keanggotaan/daftar_angsuran_pokok.php" class="nav-link <?= $current=='daftar_angsuran_pokok.php'?'active':'' ?>"><p>Daftar Angsuran Pokok</p></a></li>
            <?php endif; ?>
            <?php if (canAccess($role,$kode_jab,['kop_001','kop_002','kop_003','kop_004','kop_005'])): ?>
              <li class="nav-item"><a href="<?= $base_url ?>keanggotaan/daftar_pinjaman.php" class="nav-link <?= $current=='daftar_pinjaman.php'?'active':'' ?>"><p>Daftar Pinjaman</p></a></li>
              <li class="nav-item"><a href="<?= $base_url ?>keanggotaan/daftar_pinjaman_dua.php" class="nav-link <?= $current=='daftar_pinjaman_dua.php'?'active':'' ?>"><p>Daftar Pinjaman Lanjutan</p></a></li>
            <?php endif; ?>
            <?php if (canAccess($role,$kode_jab,['kop_001','kop_002','kop_003','kop_004'])): ?>
              <li class="nav-item"><a href="<?= $base_url ?>keanggotaan/daftar_tabungan_baru.php" class="nav-link <?= $current=='daftar_tabungan_baru.php'?'active':'' ?>"><p>Daftar Tabungan Baru</p></a></li>
            <?php endif; ?>
          </ul>
        </li>
        <?php endif; ?>

        <!-- PENGAJUAN -->
        <?php 
          $isPengajuanActive = in_array($current,[
            'pengajuan_pinjaman.php','pengajuan_pinjaman_dua.php',
            'pengajuan_tabungan_baru.php','pengajuan_tunda_pinjaman.php'
          ]);
          $showPengajuan = canAccess($role,$kode_jab,['kop_001','kop_002','kop_003','kop_004','kop_005']) || $role==='administrator';
        ?>
        <?php if ($showPengajuan): ?>
        <li class="nav-item has-treeview <?= $isPengajuanActive ? 'menu-open' : '' ?>">
          <a href="#" class="nav-link <?= $isPengajuanActive ? 'active' : '' ?>">
            <i class="nav-icon fas fa-edit"></i>
            <p>Pengajuan<i class="right fas fa-angle-left"></i></p>
          </a>
          <ul class="nav nav-treeview">
            <li class="nav-item"><a href="<?= $base_url ?>pengajuan/pengajuan_pinjaman.php" class="nav-link <?= $current=='pengajuan_pinjaman.php'?'active':'' ?>"><p>Pengajuan Pinjaman</p></a></li>
            <li class="nav-item"><a href="<?= $base_url ?>pengajuan/pengajuan_pinjaman_dua.php" class="nav-link <?= $current=='pengajuan_pinjaman_dua.php'?'active':'' ?>"><p>Pengajuan Pinjaman Lanjutan</p></a></li>
            <li class="nav-item"><a href="<?= $base_url ?>pengajuan/pengajuan_tabungan_baru.php" class="nav-link <?= $current=='pengajuan_tabungan_baru.php'?'active':'' ?>"><p>Pengajuan Tabungan Baru</p></a></li>
            <li class="nav-item"><a href="<?= $base_url ?>pengajuan/pengajuan_tunda_pinjaman.php" class="nav-link <?= $current=='pengajuan_tunda_pinjaman.php'?'active':'' ?>"><p>Pengajuan Penundaan Pinjaman</p></a></li>
          </ul>
        </li>
        <?php endif; ?>

        <!-- PERSETUJUAN -->
        <?php 
          $isPersetujuanActive = in_array($current,[
            'persetujuan_pinjaman.php','detail_pinjaman.php',
            'persetujuan_pinjaman_dua.php','detail_pinjaman_dua.php',
            'daftar_tunda_pinjaman.php','persetujuan_tunda_pinjaman.php'
          ]);
          // hanya administrator + pengurus (kop_001..kop_004)
          $showPersetujuan = ($role==='administrator' || in_array($kode_jab,['kop_001','kop_002','kop_003','kop_004']));
        ?>
        <?php if ($showPersetujuan): ?>
        <li class="nav-item has-treeview <?= $isPersetujuanActive ? 'menu-open' : '' ?>">
          <a href="#" class="nav-link <?= $isPersetujuanActive ? 'active' : '' ?>">
            <i class="nav-icon fas fa-check"></i>
            <p>Persetujuan<i class="right fas fa-angle-left"></i></p>
          </a>
          <ul class="nav nav-treeview">
            <li class="nav-item"><a href="<?= $base_url ?>persetujuan/persetujuan_pinjaman.php" class="nav-link <?= $current=='persetujuan_pinjaman.php'?'active':'' ?>"><p>Persetujuan Pinjaman</p></a></li>
            <li class="nav-item"><a href="<?= $base_url ?>persetujuan/persetujuan_pinjaman_dua.php" class="nav-link <?= $current=='persetujuan_pinjaman_dua.php'?'active':'' ?>"><p>Persetujuan Pinjaman Lanjutan</p></a></li>
            <li class="nav-item"><a href="<?= $base_url ?>persetujuan/daftar_tunda_pinjaman.php" class="nav-link <?= $current=='daftar_tunda_pinjaman.php'?'active':'' ?>"><p>Daftar Penundaan Pinjaman</p></a></li>
          </ul>
        </li>
        <?php endif; ?>

        <!-- REKAPITULASI -->
        <?php 
          $isRekapActive = in_array($current,[
            'data_induk_koperasi.php','detail_bulanan_koperasi.php',
            'riwayat_bulanan_koperasi.php','shu.php'
          ]);
          $showRekap = (
            canAccess($role,$kode_jab,['kop_001','kop_002','kop_003','kop_004','kop_005']) ||
            $role==='administrator'
          );
        ?>
        <?php if ($showRekap): ?>
        <li class="nav-item has-treeview <?= $isRekapActive ? 'menu-open' : '' ?>">
          <a href="#" class="nav-link <?= $isRekapActive ? 'active' : '' ?>">
            <i class="nav-icon fas fa-users"></i>
            <p>Rekapitulasi<i class="right fas fa-angle-left"></i></p>
          </a>
          <ul class="nav nav-treeview">
            <?php if (canAccess($role,$kode_jab,['kop_003'])): ?>
              <li class="nav-item"><a href="<?= $base_url ?>rekapitulasi/data_induk_koperasi.php" class="nav-link <?= $current=='data_induk_koperasi.php'?'active':'' ?>"><p>Data Induk Koperasi</p></a></li>
	            <li class="nav-item"><a href="<?= $base_url ?>rekapitulasi/detail_bulanan_koperasi.php" class="nav-link <?= $current=='detail_bulanan_koperasi.php'?'active':'' ?>"><p>Detail Bulanan Koperasi</p></a></li>
            <?php endif; ?>
            <?php if (canAccess($role,$kode_jab,['kop_001','kop_002','kop_003','kop_004','kop_005'])): ?>
              <li class="nav-item"><a href="<?= $base_url ?>rekapitulasi/riwayat_bulanan_koperasi.php" class="nav-link <?= $current=='riwayat_bulanan_koperasi.php'?'active':'' ?>"><p>Riwayat Bulanan Koperasi</p></a></li>
            <?php endif; ?>
            <?php if (canAccess($role,$kode_jab,['kop_003'])): ?>
              <li class="nav-item"><a href="<?= $base_url ?>rekapitulasi/shu.php" class="nav-link <?= $current=='shu.php'?'active':'' ?>"><p>SHU Koperasi</p></a></li>
            <?php endif; ?>
          </ul>
        </li>
        <?php endif; ?>

      </ul>
    </nav>
  </div>
</aside>

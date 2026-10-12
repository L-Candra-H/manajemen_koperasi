<!-- Navbar -->
<nav class="main-header navbar navbar-expand navbar-white navbar-light">
  <!-- Left navbar links -->
  <ul class="navbar-nav">
    <li class="nav-item">
      <a class="nav-link" data-widget="pushmenu" href="#" role="button">
        <i class="fas fa-bars"></i>
      </a>
    </li>
    <li class="nav-item d-none d-sm-inline-block">
      <a href="<?= $base_url ?>index.php" class="nav-link">Home</a>
    </li>
  </ul>

  <!-- Right navbar links -->
  <ul class="navbar-nav ml-auto">
    <!-- User info -->
    <li class="nav-item">
      <span class="nav-link">
        👤 <strong><?= htmlspecialchars($_SESSION['nama_pegawai'] ?? 'User') ?></strong>
      </span>
    </li>
    <!-- Logout -->
    <li class="nav-item">
      <a href="<?= $base_url ?>logout.php" class="nav-link text-danger">
        <i class="fas fa-sign-out-alt"></i> Logout
      </a>
    </li>
  </ul>
</nav>

<!-- Main Footer -->
<footer class="main-footer d-flex justify-content-between align-items-center">
  <div class="footer-left">
    <span class="footer-user">
      User: <strong><?= htmlspecialchars($_SESSION['nama_pegawai'] ?? 'Guest') ?> 
      (<?= htmlspecialchars($_SESSION['id_user'] ?? '-') ?>)</strong>
    </span>
    <span class="footer-ip ml-3">
      IP: <strong><?= htmlspecialchars($_SERVER['REMOTE_ADDR']) ?></strong>
    </span>
  </div>
  <div class="footer-right" id="clock">
    <?php
    // Render awal dengan PHP (locale Indonesia)
    $fmt = new IntlDateFormatter(
        'id_ID',
        IntlDateFormatter::FULL,
        IntlDateFormatter::MEDIUM,
        'Asia/Jakarta',
        IntlDateFormatter::GREGORIAN
    );
    echo $fmt->format(new DateTime());
    ?>
  </div>
</footer>

<script>
function updateClock() {
  const now = new Date();
  const options = { 
    weekday: 'long', 
    year: 'numeric', 
    month: 'long', 
    day: 'numeric', 
    hour: '2-digit', 
    minute: '2-digit', 
    second: '2-digit' 
  };
  document.getElementById('clock').textContent = 
    now.toLocaleDateString('id-ID', options);
}
setInterval(updateClock, 1000); // update tiap detik
updateClock(); // panggil sekali di awal
</script>

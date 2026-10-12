<?php
session_start();
if (!isset($_SESSION['id_user'])) {
    header("Location: login.php?error=unauthorized");
    exit;
}

// cek role valid
$role = $_SESSION['role'] ?? '';
if (!in_array($role, ['administrator','anggota','calon'])) {
    header("Location: login.php?error=unauthorized");
    exit;
}
?>

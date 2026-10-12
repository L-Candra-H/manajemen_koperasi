<?php
session_start();
include __DIR__ . '/conf/auth.php'; // proteksi login

// Jika belum login, arahkan ke halaman login
if (!isset($_SESSION['role'])) {
    header("Location: login.php");
    exit;
}

// Redirect sesuai role
switch ($_SESSION['role']) {
    case 'administrator':
        header("Location: dashboard_admin.php");
        exit;
    case 'anggota':
        header("Location: dashboard_koperasi.php");
        exit;
    case 'calon':
        header("Location: daftar/pendaftaran_anggota.php");
        exit;
    default:
        // fallback jika role tidak dikenali
        header("Location: login.php");
        exit;
}

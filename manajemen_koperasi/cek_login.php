<?php
session_start();
include __DIR__ . '/conf/conf.php';          // DB induk (pegawai, admin)
include __DIR__ . '/conf/conf_koperasi.php'; // DB koperasi
include __DIR__ . '/conf/command.php';       // helper validTeks4

error_reporting(0);
ini_set('display_errors', 0);

if (isset($_POST['usere']) && isset($_POST['passworde'])) {
    $usere     = validTeks4($_POST['usere'],30);
    $passworde = validTeks4($_POST['passworde'],30);
    $role      = $_POST['role'] ?? '';

    $connInduk    = bukakoneksi();         // koneksi DB induk
    $connKoperasi = bukakoneksikoperasi(); // koneksi DB koperasi

    // cek admin
    $sqlAdmin = "SELECT CAST(AES_DECRYPT(usere,'nur') AS CHAR) AS usere
                 FROM admin
                 WHERE usere=AES_ENCRYPT('$usere','nur')
                   AND passworde=AES_ENCRYPT('$passworde','windi')
                 LIMIT 1";
    $rowAdmin = mysqli_fetch_assoc(mysqli_query($connInduk, $sqlAdmin));

    // cek user + join pegawai
    $sqlUser = "SELECT 
                    CAST(AES_DECRYPT(u.id_user,'nur') AS CHAR) AS id_user,
                    p.nama AS nama_pegawai
                FROM user u
                INNER JOIN pegawai p 
                        ON p.nik = TRIM(CAST(AES_DECRYPT(u.id_user,'nur') AS CHAR))
                WHERE u.id_user = AES_ENCRYPT('$usere','nur')
                  AND u.password = AES_ENCRYPT('$passworde','windi')
                LIMIT 1";
    $rowUser = mysqli_fetch_assoc(mysqli_query($connInduk, $sqlUser));

    if ($rowAdmin && $role === 'admin') {
        // ADMINISTRATOR → bypass tanggal lahir
        $_SESSION["id_user"]      = $rowAdmin['usere'];
        $_SESSION["nama_pegawai"] = "Administrator";
        $_SESSION["role"]         = "administrator";
        $_SESSION["jabatan"]      = "Administrator";

        header("Location: dashboard_admin.php");
        exit;
    } elseif ($rowUser && ($role === 'user' || $role === 'calon')) {
        $nikLogin = $rowUser['id_user'];

        // ambil tanggal lahir dari tabel pegawai
        $sqlPegawai = "SELECT tgl_lahir FROM pegawai 
                       WHERE nik='".mysqli_real_escape_string($connInduk,$nikLogin)."' 
                       LIMIT 1";
        $rowPegawai = mysqli_fetch_assoc(mysqli_query($connInduk, $sqlPegawai));

        // input dari form login
        $tglInput = $_POST['tgl_lahir'] ?? '';
        $tglInputFormatted = !empty($tglInput) 
            ? date('Y-m-d', strtotime(str_replace('/', '-', $tglInput))) 
            : '';

        if ($rowPegawai && $tglInputFormatted === $rowPegawai['tgl_lahir']) {
            // cek status anggota di koperasi
            $sqlStatus = "SELECT status_keanggotaan, kode_jab 
                          FROM daftar_anggota 
                          WHERE id_user='".mysqli_real_escape_string($connKoperasi,$nikLogin)."' 
                          LIMIT 1";
            $rowStatus = mysqli_fetch_assoc(mysqli_query($connKoperasi, $sqlStatus));

            if ($rowStatus && $rowStatus['status_keanggotaan'] === 'Aktif') {
                if ($role !== 'user') {
                    // salah pilih role → arahkan balik ke login dengan notif
                    header("Location: login.php?error=3");
                    exit;
                }
                // role benar → anggota aktif
                $_SESSION["id_user"]      = $rowUser['id_user'];
                $_SESSION["nama_pegawai"] = $rowUser['nama_pegawai'];
                $_SESSION["role"]         = "anggota";
                $_SESSION["kode_jab"]     = $rowStatus['kode_jab'];

                // ambil jabatan dari tabel jabatan_koperasi
                $sqlJabatan = "SELECT jab 
                               FROM jabatan_koperasi 
                               WHERE kode_jab='".mysqli_real_escape_string($connKoperasi,$rowStatus['kode_jab'])."' 
                               LIMIT 1";
                $rowJabatan = mysqli_fetch_assoc(mysqli_query($connKoperasi, $sqlJabatan));
                $_SESSION["jabatan"] = $rowJabatan ? $rowJabatan['jab'] : "Anggota";

                header("Location: dashboard_koperasi.php");
                exit;
            } else {
                if ($role !== 'calon') {
                    // salah pilih role → arahkan balik ke login dengan notif
                    header("Location: login.php?error=4");
                    exit;
                }
                // CALON anggota (belum aktif)
                $_SESSION["id_user"]      = $rowUser['id_user'];
                $_SESSION["nama_pegawai"] = $rowUser['nama_pegawai'];
                $_SESSION["role"]         = "calon";
                $_SESSION["jabatan"]      = "Calon Anggota";

                header("Location: ./keanggotaan/pendaftaran_anggota.php");
                exit;
            }
        } else {
            header("Location: login.php?error=2"); // verifikasi tanggal lahir salah
            exit;
        }
    } else {
        header("Location: login.php?error=1"); // username/password salah
        exit;
    }

    mysqli_close($connInduk);
    mysqli_close($connKoperasi);
}
?>

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

/* === Fungsi Sinkronisasi Data Induk === */
function sinkronInduk($conn, $db_induk) {
    // 1. Insert identitas anggota dari daftar_anggota
    $conn->query("INSERT INTO data_induk_koperasi (no_anggota, id_user, status_keanggotaan, tgl_terdaftar, kode_jab)
                  SELECT no_anggota, id_user, status_keanggotaan, tgl_terdaftar, kode_jab
                  FROM daftar_anggota
                  ON DUPLICATE KEY UPDATE 
                      status_keanggotaan=VALUES(status_keanggotaan),
                      kode_jab=VALUES(kode_jab)");

    // 2. Update simpanan pokok dari angsuran_pokok
    $conn->query("UPDATE data_induk_koperasi di
                  JOIN (
                      SELECT no_daftar, SUM(jumlah_dibayar) AS total_pokok
                      FROM daftar_angsuran_pokok
                      GROUP BY no_daftar
                  ) ap ON di.no_anggota = ap.no_daftar
                  SET di.simpanan_pokok = IFNULL(ap.total_pokok,0)");

    // 3. Update simpanan wajib dari pengajuan_anggota
    $conn->query("UPDATE data_induk_koperasi di
                  JOIN pengajuan_anggota pa ON di.id_user = pa.id_user
                  SET di.simpanan_wajib = IFNULL(pa.simp_wajib,0)");

    // 4a. Tabungan awal dari pengajuan_anggota
    $conn->query("UPDATE data_induk_koperasi di
                  JOIN pengajuan_anggota pa ON di.id_user = pa.id_user
                  SET di.tabungan = IFNULL(pa.tabungan,0)");

    // 4b. Tabungan berjalan dari daftar_tabungan
    $conn->query("UPDATE data_induk_koperasi di
                  JOIN (
                      SELECT no_daftar, SUM(tabungan) AS total_tabungan
                      FROM daftar_tabungan_baru
                      GROUP BY no_daftar
                  ) dt ON di.no_anggota = dt.no_daftar
                  SET di.tabungan = IFNULL(dt.total_tabungan,0)");

    // 4c. Tabungan dari pengajuan_tabungan terbaru yang sudah ada persetujuan
    $conn->query("UPDATE data_induk_koperasi di
                  JOIN (
                      SELECT pt.id_user, pt.tabungan_baru
                      FROM pengajuan_tabungan_baru pt
                      INNER JOIN persetujuan_tabungan_baru ps ON pt.no_daftar = ps.no_daftar
                      INNER JOIN (
                          SELECT ps.no_daftar, MAX(ps.tgl_persetujuan) AS last_approve
                          FROM persetujuan_tabungan_baru ps
                          GROUP BY ps.no_anggota
                      ) latest ON ps.no_daftar = latest.no_daftar
                  ) ptt ON di.id_user = ptt.id_user
                  SET di.tabungan = ptt.tabungan_baru");
}

/* === Handler Aksi === */
$action = $_GET['action'] ?? '';

if ($action === 'sinkronisasi') {
    // 1. Insert identitas anggota dari daftar_anggota
    $conn->query("INSERT INTO data_induk_koperasi (no_anggota, id_user, status_keanggotaan, tgl_terdaftar, kode_jab)
                  SELECT no_anggota, id_user, status_keanggotaan, tgl_terdaftar, kode_jab
                  FROM daftar_anggota
                  ON DUPLICATE KEY UPDATE 
                      status_keanggotaan=VALUES(status_keanggotaan),
                      kode_jab=VALUES(kode_jab)");

    // 2. Update simpanan pokok dari angsuran_pokok
    $conn->query("UPDATE data_induk_koperasi di
                  JOIN (
                      SELECT no_daftar, SUM(jumlah_dibayar) AS total_pokok
                      FROM daftar_angsuran_pokok
                      GROUP BY no_daftar
                  ) ap ON di.no_anggota = ap.no_daftar
                  SET di.simpanan_pokok = IFNULL(ap.total_pokok,0)");

    // 3a. Update simpanan wajib dari pengajuan_anggota
    $conn->query("UPDATE data_induk_koperasi di
                  JOIN pengajuan_anggota pa ON di.id_user = pa.id_user
                  SET di.simpanan_wajib = IFNULL(pa.simp_wajib,0)");

    // 3b. Isi default dari tabel simpanan (untuk anggota lama)
    $res = $conn->query("SELECT nominal FROM simpanan WHERE kode_simp='sim_001' LIMIT 1");
    if ($res && $row = $res->fetch_assoc()) {
        $default_wajib = $row['nominal'];
        $conn->query("UPDATE data_induk_koperasi
                      SET simpanan_wajib = $default_wajib
                      WHERE simpanan_wajib IS NULL OR simpanan_wajib = 0");
    }

    // 4a. Tabungan awal dari pengajuan_anggota
    $conn->query("UPDATE data_induk_koperasi di
                  JOIN pengajuan_anggota pa ON di.id_user = pa.id_user
                  SET di.tabungan = IFNULL(pa.tabungan,0)");

    // 4b. Tabungan berjalan dari daftar_tabungan
    $conn->query("UPDATE data_induk_koperasi di
                  JOIN (
                      SELECT no_daftar, SUM(tabungan) AS total_tabungan
                      FROM daftar_tabungan_baru
                      GROUP BY no_daftar
                  ) dt ON di.no_anggota = dt.no_daftar
                  SET di.tabungan = IFNULL(dt.total_tabungan,0)");

    // 4c. Tabungan dari pengajuan_tabungan terbaru yang sudah ada persetujuan
    $conn->query("UPDATE data_induk_koperasi di
                  JOIN (
                      SELECT pt.id_user, pt.tabungan_baru
                      FROM pengajuan_tabungan_baru pt
                      INNER JOIN persetujuan_tabungan_baru ps ON pt.no_daftar = ps.no_daftar
                      INNER JOIN (
                          SELECT ps.no_daftar, MAX(ps.tgl_persetujuan) AS last_approve
                          FROM persetujuan_tabungan_baru ps
                          GROUP BY ps.no_anggota
                      ) latest ON ps.no_daftar = latest.no_daftar
                  ) ptt ON di.id_user = ptt.id_user
                  SET di.tabungan = ptt.tabungan_baru");

    // Alihkan kembali ke halaman data_induk_koperasi.php dengan notifikasi
    header("Location: data_induk_koperasi.php?notif=sukses");
    exit;
}

// fungsi bantu untuk validasi/normalisasi tanggal
function normalizeDate($val) {
    if (empty($val)) return null;
    // format YYYY-MM-DD sudah valid
    if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $val)) {
        return $val;
    }
    // format YYYY-MM → tambahkan hari default
    if (preg_match('/^\d{4}-\d{2}$/', $val)) {
        return $val . "-01";
    }
    // format YYYY → tambahkan bulan dan hari default
    if (preg_match('/^\d{4}$/', $val)) {
        return $val . "-01-01";
    }
    return null;
}

/* === Sinkronisasi Detail Bulanan === */
if ($action === 'sinkronisasi_bulanan' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $periode = $_POST['periode_bulan'] ?? null;
    if ($periode) {
        // ambil semua anggota aktif
        $sql = "SELECT no_anggota, id_user, status_keanggotaan, tgl_terdaftar, kode_jab, simpanan_wajib, tabungan 
                FROM data_induk_koperasi 
                WHERE status_keanggotaan='Aktif'";
        $res = $conn->query($sql);

        while ($row = $res->fetch_assoc()) {
            // skip anggota yang belum terdaftar di periode ini
            // Anggota yang daftar pada bulan yang sama
            // belum ikut potongan.
            // Potongan mulai bulan berikutnya.
            $periodeDaftar = date('Y-m', strtotime($row['tgl_terdaftar']));

            if ($periodeDaftar >= $periode) {
                continue;
            }

            $no_anggota = $row['no_anggota'];
            $id_user    = $row['id_user'];

            // ambil data angsuran pokok bulan ini
            $angsuran = $conn->query("SELECT jumlah, angsuran_ke, bulan 
                                      FROM detail_angsuran_pokok
                                      WHERE id_user='$id_user' AND DATE_FORMAT(bulan,'%Y-%m')='$periode'")
                             ->fetch_assoc();

            // ambil pinjaman 1 bulan ini
            $pinjaman = $conn->query("SELECT jumlah_cicilan, angsuran_ke, bulan 
                                      FROM detail_pinjaman 
                                      WHERE id_user='$id_user' AND DATE_FORMAT(bulan,'%Y-%m')='$periode'")
                             ->fetch_assoc();

            // ambil pinjaman 2 bulan ini
            $pinjaman2 = $conn->query("SELECT jumlah_cicilan, angsuran_ke, bulan 
                                       FROM detail_pinjaman_dua 
                                       WHERE id_user='$id_user' AND DATE_FORMAT(bulan,'%Y-%m')='$periode'")
                              ->fetch_assoc();

            // normalisasi tanggal
            $tglBayarAngsuran  = normalizeDate($angsuran['bulan'] ?? null);
            $tglBayarPinjaman  = normalizeDate($pinjaman['bulan'] ?? null);
            $tglBayarPinjaman2 = normalizeDate($pinjaman2['bulan'] ?? null);

            // siapkan variabel agar bisa di-bind by reference
            $angsuranJumlah    = $angsuran['jumlah'] ?? 0;
            $angsuranKe        = $angsuran['angsuran_ke'] ?? null;

            $simpananWajib     = $row['simpanan_wajib'] ?? 0;
            $tabungan          = $row['tabungan'] ?? 0;

            $pinjamanJumlah    = $pinjaman['jumlah_cicilan'] ?? 0;
            $pinjamanKe        = $pinjaman['angsuran_ke'] ?? null;

            $pinjaman2Jumlah   = $pinjaman2['jumlah_cicilan'] ?? 0;
            $pinjaman2Ke       = $pinjaman2['angsuran_ke'] ?? null;

            // hitung total potongan
            $totalPotongan = $angsuranJumlah + $simpananWajib + $tabungan + $pinjamanJumlah + $pinjaman2Jumlah;

            // insert ke detail_bulanan_koperasi
            $stmt = $conn->prepare("INSERT INTO detail_bulanan_koperasi 
                (no_anggota,id_user,status_keanggotaan,tgl_terdaftar,kode_jab,periode_bulan,
                 angsuran_pokok,angsuran_ke_pokok,tgl_bayar_angsuran,
                 simpanan_wajib,tabungan,
                 angsuran_pinjaman,pinjaman_ke,tgl_bayar_pinjaman,
                 angsuran_pinjaman_dua,pinjaman_dua_ke,tgl_bayar_pinjaman_dua,
                 total_potongan)
                VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)
                ON DUPLICATE KEY UPDATE 
                 angsuran_pokok=VALUES(angsuran_pokok),
                 simpanan_wajib=VALUES(simpanan_wajib),
                 tabungan=VALUES(tabungan),
                 angsuran_pinjaman=VALUES(angsuran_pinjaman),
                 angsuran_pinjaman_dua=VALUES(angsuran_pinjaman_dua),
                 total_potongan=VALUES(total_potongan)");

            // 18 kolom → 18 variabel → 18 huruf tipe
            $stmt->bind_param("ssssssdisdddisdisd",
                $no_anggota,          // s
                $id_user,             // s
                $row['status_keanggotaan'], // s
                $row['tgl_terdaftar'],      // s
                $row['kode_jab'],     // s
                $periode,             // s
                $angsuranJumlah,      // d
                $angsuranKe,          // i
                $tglBayarAngsuran,    // s
                $simpananWajib,       // d
                $tabungan,            // d
                $pinjamanJumlah,      // d
                $pinjamanKe,          // i
                $tglBayarPinjaman,    // s
                $pinjaman2Jumlah,     // d
                $pinjaman2Ke,         // i
                $tglBayarPinjaman2,   // s
                $totalPotongan        // d
            );

            $stmt->execute();
        }
        header("Location: detail_bulanan_koperasi.php?periode=$periode&notif=sukses");
        exit;
    }
}

// Ambil nominal default simpanan wajib
$default_wajib = 0;
$res = $conn->query("SELECT nominal FROM simpanan WHERE kode_simp='sim_001' LIMIT 1");
if ($res && $row = $res->fetch_assoc()) {
    $default_wajib = $row['nominal'];
}

// Update simpanan wajib default jika kosong
$conn->query("UPDATE data_induk_koperasi
              SET simpanan_wajib = $default_wajib
              WHERE simpanan_wajib IS NULL OR simpanan_wajib = 0");

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'update') {
    $id_user         = $_POST['id_user'];
    $no_anggota      = $_POST['no_anggota'];
    $status          = $_POST['status_keanggotaan'];
    $dana_pensiun    = $_POST['dana_pensiun'];
    $wajib_lama      = $_POST['simpanan_wajib_lama'];
    $simpanan_pokok  = $_POST['simpanan_pokok'];
    $simpanan_wajib  = $_POST['simpanan_wajib'];
    $tabungan        = $_POST['tabungan'];


    // Cari id pegawai dari DB induk berdasarkan nik (id_user)
    $stmtGetId = $conn->prepare("SELECT id FROM {$db_induk}.pegawai WHERE nik=? LIMIT 1");
    $stmtGetId->bind_param("s", $id_user);
    $stmtGetId->execute();
    $stmtGetId->bind_result($idPegawai);
    $stmtGetId->fetch();
    $stmtGetId->close();

    // CEK SALDO AWAL YANG SUDAH TERSIMPAN
    $stmtCek = $conn->prepare("
        SELECT simpanan_wajib_lama
        FROM data_induk_koperasi
        WHERE id_user=? AND no_anggota=?
        LIMIT 1
    ");

    $stmtCek->bind_param("ss", $id_user, $no_anggota);
    $stmtCek->execute();
    $resultCek = $stmtCek->get_result();
    $dataCek = $resultCek->fetch_assoc();
    $stmtCek->close();

    // JIKA SUDAH PERNAH DIISI, KUNCI NILAINYA
    if ($dataCek && $dataCek['simpanan_wajib_lama'] > 0) {
        $wajib_lama = $dataCek['simpanan_wajib_lama'];
    }

    if ($idPegawai) {
        // Update keanggotaan di DB induk
        $stmtInduk = $conn->prepare("UPDATE {$db_induk}.keanggotaan SET koperasi=? WHERE id=?");
        $koperasiVal = ($status === 'Tidak Aktif') ? 'T' : 'Y';
        $stmtInduk->bind_param("si", $koperasiVal, $idPegawai); 
        $stmtInduk->execute();
        $stmtInduk->close();
    }

    // Update ke data_induk_koperasi
    $sql_update = "UPDATE data_induk_koperasi 
                   SET status_keanggotaan=?, dana_pensiun=?, simpanan_wajib_lama=?, simpanan_pokok=?, simpanan_wajib=?, tabungan=? 
                   WHERE id_user=? AND no_anggota=?";
    $stmt = $conn->prepare($sql_update);
    $stmt->bind_param("sdddddss", $status, $dana_pensiun, $wajib_lama, $simpanan_pokok, $simpanan_wajib, $tabungan, $id_user, $no_anggota);
    $stmt->execute();
    $stmt->close();

    // Sinkronisasi ke daftar_anggota (langsung ikut berubah)
    $stmtSync = $conn->prepare("UPDATE daftar_anggota 
                                SET status_keanggotaan=? 
                                WHERE id_user=?");
    $stmtSync->bind_param("ss", $status, $id_user);
    $stmtSync->execute();
    $stmtSync->close();

    // Opsional: hapus anggota Tidak Aktif dari daftar_anggota
    if ($status === 'Tidak Aktif') {
        $stmtDel = $conn->prepare("DELETE FROM daftar_anggota WHERE id_user=? AND no_anggota=?");
        $stmtDel->bind_param("ss", $id_user, $no_anggota);
        $stmtDel->execute();
        $stmtDel->close();
    }

    header("Location: data_induk_koperasi.php?notif=update");
    exit;
}

?>

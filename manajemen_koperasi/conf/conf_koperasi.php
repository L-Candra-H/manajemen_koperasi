<?php
//$requestUri = $_SERVER['REQUEST_URI'] ?? '';
//if (strpos($requestUri, "conf")) {
//    exit(header("Location: ../index.php"));
//}

$base_url = "/manajemen_koperasi/";

$db_hostname_koperasi = "localhost";
$db_username_koperasi = "root";
$db_password_koperasi = "xxx";   
$db_name_koperasi     = "manajemen_koperasi"; 

function hostkoperasi() {
    global $db_hostname_koperasi;
    return $db_hostname_koperasi;
}

function bukakoneksikoperasi() {
    global $db_hostname_koperasi, $db_username_koperasi, $db_password_koperasi, $db_name_koperasi;
    $konektor = mysqli_connect($db_hostname_koperasi, $db_username_koperasi, $db_password_koperasi)
        or die ("<font color=red><h3>Not Connected to koperasi DB .. !!</h3></font>");
    mysqli_select_db($konektor, $db_name_koperasi)
        or die("<font color=red><h3>Cannot choose koperasi database .. !!</h3></font>" . mysqli_error($konektor));
    return $konektor;
}

// koneksi global koperasi
$conn_koperasi = bukakoneksikoperasi();
mysqli_set_charset($conn_koperasi, 'utf8mb4');

function tutupkoneksikoperasi() {
    global $conn_koperasi;
    mysqli_close($conn_koperasi);
}

function bukaquerykoperasi($sql) {
    $konektor = bukakoneksikoperasi();
    $result = mysqli_query($konektor, $sql)
        or die(mysqli_error($konektor) . " Silahkan hubungi administrator .. !!");
    mysqli_close($konektor);
    return $result;
}

function fetch_assockoperasi($sql) {
    $result = mysqli_fetch_assoc(bukaquerykoperasi($sql));
    return $result;
}
?>

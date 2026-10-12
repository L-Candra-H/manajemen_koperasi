<?php
// Membersihkan input teks dengan panjang maksimal (SIMRS)
if (!function_exists('validTeks4')) {
    function validTeks4($str, $length){
        $str = substr(trim($str),0,$length);
        $conn = bukakoneksi(); // koneksi SIMRS dari conf.php
        return mysqli_real_escape_string($conn,$str);
    }
}

// Eksekusi query dan ambil satu nilai (SIMRS)
function getOne2($sql){
    $hasil = bukaquery($sql); // bukaquery dari conf.php (SIMRS)
    if($row = mysqli_fetch_array($hasil)){
        return $row[0];
    } else {
        return "";
    }
}

// Eksekusi query dan ambil satu nilai (Koperasi)
function getOneKoperasi($sql){
    $hasil = bukaquerykoperasi($sql); // bukaquerykoperasi dari conf_koperasi.php
    if($row = mysqli_fetch_array($hasil)){
        return $row[0];
    } else {
        return "";
    }
}
?>

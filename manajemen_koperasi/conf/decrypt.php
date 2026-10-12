<?php
// conf/decrypt.php

function decryptBlob($blobData) {
    if (empty($blobData)) {
        return null;
    }

    // Jika disimpan dengan base64:
    return base64_decode($blobData);

    // Jika disimpan dengan AES, gunakan:
    /*
    $key = 'kunci_rahasia'; // ambil dari conf.php
    $iv = '1234567890123456'; // harus sama dengan saat enkripsi
    return openssl_decrypt($blobData, 'AES-256-CBC', $key, 0, $iv);
    */
}

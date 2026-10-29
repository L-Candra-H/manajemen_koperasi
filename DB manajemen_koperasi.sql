-- ==============================
-- DATABASE MANAJEMEN KOPERASI
-- ==============================
CREATE DATABASE manajemen_koperasi;
USE manajemen_koperasi;

-- ==============================
-- JABATAN KOPERASI
-- ==============================
CREATE TABLE jabatan_koperasi (
    kode_jab VARCHAR(10) PRIMARY KEY,
    jab VARCHAR(50) NOT NULL
);

INSERT INTO jabatan_koperasi (kode_jab, jab) VALUES
('kop_001','Ketua'),
('kop_002','Sekretaris'),
('kop_003','Bendahara'),
('kop_004','Pembantu Umum'),
('kop_005','Anggota'),
('kop_006','Calon Anggota'),
('kop_007','Bukan Anggota');

-- ==============================
-- JASA PINJAM
-- ==============================
CREATE TABLE jasa_pinjam (
    kode_jasa VARCHAR(10) PRIMARY KEY,
    jasa DECIMAL(5,2) NOT NULL
);

INSERT INTO jasa_pinjam (kode_jasa, jasa) VALUES
('js_001',1.25);

-- ==============================
-- LAMA PEMINJAMAN
-- ==============================
CREATE TABLE lama_peminjaman (
    kode_lama VARCHAR(10) PRIMARY KEY,
    lama INT NOT NULL
);

INSERT INTO lama_peminjaman (kode_lama, lama) VALUES
('lp_001',12),
('lp_002',18),
('lp_003',24),
('lp_004',36);

-- ==============================
-- SIMPANAN
-- ==============================
CREATE TABLE simpanan (
    kode_simp VARCHAR(10) PRIMARY KEY,
    simp VARCHAR(50) NOT NULL,
    nominal DECIMAL(15,2) NOT NULL
);

INSERT INTO simpanan (kode_simp, simp, nominal) VALUES
('sim_001','Wajib',5000),
('sim_002','Pokok',1000000);

-- ==============================
-- JANGKA WAKTU TUNDA
-- ==============================
CREATE TABLE jangka_waktu_tunda (
    kode_jangka VARCHAR(10) PRIMARY KEY,
    bln INT NOT NULL,
    kali INT NOT NULL
);

INSERT INTO jangka_waktu_tunda (kode_jangka, bln, kali) VALUES
('jt_001',12,1),
('jt_002',18,1),
('jt_003',24,2),
('jt_004',36,3);

-- ==============================
-- PERIODE PENGATURAN
-- ==============================
CREATE TABLE periode_pengaturan (
    id_periode     INT AUTO_INCREMENT PRIMARY KEY,
    nama_fitur     VARCHAR(50) NOT NULL,   -- contoh: 'registrasi_tabungan'
    tanggal_awal   DATE NOT NULL,
    tanggal_akhir  DATE NOT NULL,
    status         ENUM('Aktif','Nonaktif') DEFAULT 'Nonaktif',
    dibuat_oleh    VARCHAR(50) NOT NULL,   -- admin/kepengurusan
    dibuat_pada    TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- ==============================
-- RUMUS SHU
-- ==============================
CREATE TABLE rumus_shu (
    id_rumus INT AUTO_INCREMENT PRIMARY KEY,
    nama_rumus VARCHAR(100) NOT NULL,
    persen_administrasi DECIMAL(5,2) NOT NULL DEFAULT 10.00,
    persen_dibagi DECIMAL(5,2) NOT NULL DEFAULT 90.00,
    persen_pinjaman DECIMAL(5,2) NOT NULL DEFAULT 60.00,
    persen_anggota DECIMAL(5,2) NOT NULL DEFAULT 40.00,
    aktif_pinjaman_dua ENUM('Y','T') DEFAULT 'T',
    dibuat TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

INSERT INTO rumus_shu 
(nama_rumus, persen_administrasi, persen_dibagi, persen_pinjaman, persen_anggota,aktif_pinjaman_dua)
VALUES 
('SHU Tahunan Default', 10.00, 90.00, 60.00, 40.00, 'T');

-- ==============================
-- PERIODE HITUNG
-- ==============================
CREATE TABLE periode_hitung (
    id_periode   INT AUTO_INCREMENT PRIMARY KEY,
    nama_periode VARCHAR(50) NOT NULL,   -- contoh: 'Hitung 2026'
    tahun        CHAR(4) NOT NULL,       -- contoh: '2026'
    awal         DATE NOT NULL,          -- selalu tanggal 1 bulan awal
    akhir        DATE NOT NULL,          -- selalu tanggal terakhir bulan akhir
    status       ENUM('Aktif','Nonaktif') DEFAULT 'Aktif',
    dibuat_oleh  VARCHAR(50),
    dibuat_pada  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    is_final     TINYINT(1) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ==============================
-- DAFTAR ANGGOTA KOPERASI
-- ==============================
CREATE TABLE daftar_anggota (
    no_anggota        VARCHAR(30) UNIQUE,   -- boleh NULL, unik kalau sudah terbentuk
    id_user           VARCHAR(50) PRIMARY KEY,
    status_keanggotaan ENUM('Aktif','Tidak Aktif','Diteruskan','Diproses','Direkap','Disetujui','Ditolak') DEFAULT 'Tidak Aktif',
    tgl_terdaftar     DATE,
    kode_jab          VARCHAR(10),
    CONSTRAINT fk_jabatan FOREIGN KEY (kode_jab) REFERENCES jabatan_koperasi(kode_jab)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ==============================
-- PENGAJUAN ANGGOTA BARU
-- ==============================
CREATE TABLE pengajuan_anggota (
    no_daftar     VARCHAR(20)  NOT NULL PRIMARY KEY,
    id_user       VARCHAR(50)  NOT NULL,
    simp_pokok    DECIMAL(12,2) NOT NULL,
    angsur_pokok  INT          NOT NULL,
    simp_wajib    DECIMAL(12,2) NOT NULL,
    tabungan      DECIMAL(12,2) DEFAULT 0,
    tgl_pembuatan DATE         NOT NULL,
    status        ENUM('Diteruskan','Diproses','Direkap','Disetujui','Ditolak') DEFAULT 'Diteruskan',
    source	  ENUM('Normal','Migrasi') DEFAULT 'Normal',
    keterangan    VARCHAR(255) NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ==============================
-- PERSETUJUAN PENGAJUAN
-- ==============================
CREATE TABLE persetujuan_pengajuan (
  no_daftar       VARCHAR(20) PRIMARY KEY,
  tgl_pengajuan   DATE DEFAULT NULL,
  id_ketua        VARCHAR(50) DEFAULT NULL,
  tgl_persetujuan DATE DEFAULT NULL,
  id_sekretaris   VARCHAR(50) DEFAULT NULL,
  tgl_dicatat     DATE DEFAULT NULL,
  id_bendahara    VARCHAR(50) DEFAULT NULL,
  tgl_direkap     DATE DEFAULT NULL,
  no_anggota      VARCHAR(20) DEFAULT NULL
);

-- ==============================
-- DAFTAR ANGSURAN SIMPANAN POKOK
-- ==============================
CREATE TABLE daftar_angsuran_pokok (
  id_angsuran INT AUTO_INCREMENT PRIMARY KEY,
  no_daftar   VARCHAR(30) NOT NULL,
  id_user     VARCHAR(50) NOT NULL,
  simp_pokok  DECIMAL(12,2) NOT NULL,
  jumlah_dibayar DECIMAL(12,2) DEFAULT 0,
  jumlah_angsuran INT NOT NULL,
  sisa_angsuran   INT NOT NULL,
  status_angsuran ENUM('Belum Lunas','Lunas') DEFAULT 'Belum Lunas',
  source          ENUM('Normal','Migrasi') DEFAULT 'Normal',
  keterangan VARCHAR(255) NULL,
  CONSTRAINT fk_ap_pengajuan FOREIGN KEY (no_daftar)
    REFERENCES pengajuan_anggota(no_daftar)
    ON DELETE CASCADE
);

-- ==============================
-- DETAIL ANGSURAN SIMPANAN POKOK
-- ==============================
CREATE TABLE detail_angsuran_pokok (
  id_detail    INT AUTO_INCREMENT PRIMARY KEY,
  no_daftar    VARCHAR(30) NOT NULL,
  id_user      VARCHAR(50) NOT NULL,
  simp_pokok   DECIMAL(12,2) NOT NULL,
  jumlah_angsuran INT NOT NULL,
  angsuran_ke  INT NOT NULL,
  bulan        DATE NOT NULL,
  jumlah       DECIMAL(12,2) NOT NULL,
  status       ENUM('Belum Bayar','Sudah Bayar') DEFAULT 'Belum Bayar',
  tgl_bayar    DATE NULL,
  CONSTRAINT fk_detail_pengajuan FOREIGN KEY (no_daftar)
    REFERENCES pengajuan_anggota(no_daftar)
    ON DELETE CASCADE
);

-- ==============================
-- PENGAJUAN PINJAMAN
-- ==============================
CREATE TABLE pengajuan_pinjaman (
    no_daftar          VARCHAR(20)  NOT NULL PRIMARY KEY,
    id_user            VARCHAR(50)  NOT NULL,
    pinjaman           DECIMAL(12,2) NOT NULL,
    angsur_pinjaman    INT          NOT NULL,
    angsuran_per_bulan DECIMAL(12,2) DEFAULT 0,
    tgl_pembuatan      DATE         NOT NULL,
    kode_jangka        VARCHAR(10)  NULL,
    status             ENUM('Diteruskan','Diproses','Direkap','Disetujui','Ditolak') DEFAULT 'Diteruskan',
    source             ENUM('Normal','Migrasi') DEFAULT 'Normal',   -- tambahan flag
    keterangan         VARCHAR(255) DEFAULT NULL,                   -- catatan migrasi
    CONSTRAINT fk_jangka FOREIGN KEY (kode_jangka) 
        REFERENCES jangka_waktu_tunda(kode_jangka)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ==============================
-- DAFTAR PINJAMAN
-- ==============================
CREATE TABLE daftar_pinjaman (
    no_daftar       VARCHAR(20) PRIMARY KEY,
    id_user         VARCHAR(50) NOT NULL,
    pinjaman        DECIMAL(12,2) NOT NULL,
    jumlah_dibayar  DECIMAL(12,2) DEFAULT 0,
    angsur_pinjaman INT NOT NULL,
    sisa_angsuran   INT DEFAULT 0,
    status_angsuran ENUM('Belum Lunas','Lunas') DEFAULT 'Belum Lunas',
    source          ENUM('Normal','Migrasi') DEFAULT 'Normal',   -- tambahan flag
    keterangan      VARCHAR(255) DEFAULT NULL,                   -- catatan migrasi
    CONSTRAINT fk_daftar_pinjaman FOREIGN KEY (no_daftar) 
        REFERENCES pengajuan_pinjaman(no_daftar)
        ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ==============================
-- PERSETUJUAN PINJAMAN
-- ==============================
CREATE TABLE persetujuan_pinjaman (
  no_daftar       VARCHAR(20) PRIMARY KEY,
  no_anggota      VARCHAR(20) DEFAULT NULL,
  tgl_pengajuan   DATE DEFAULT NULL,
  id_ketua        VARCHAR(50) DEFAULT NULL,
  tgl_persetujuan DATE DEFAULT NULL,
  id_sekretaris   VARCHAR(50) DEFAULT NULL,
  tgl_dicatat     DATE DEFAULT NULL,
  id_bendahara    VARCHAR(50) DEFAULT NULL,
  tgl_direkap     DATE DEFAULT NULL
);

-- ==============================
-- DETAIL PINJAMAN
-- ==============================
CREATE TABLE detail_pinjaman (
    id_detail       INT AUTO_INCREMENT PRIMARY KEY,
    no_daftar       VARCHAR(20) NOT NULL,
    id_user         VARCHAR(50) NOT NULL,
    pinjaman        DECIMAL(12,2) NOT NULL,
    jumlah_angsuran INT NOT NULL,
    angsuran_ke     INT NOT NULL,
    bulan           DATE NOT NULL,
    jumlah_cicilan  DECIMAL(12,2) NOT NULL,
    tunda_ke        INT DEFAULT 0,
    status ENUM('Belum Bayar','Sudah Bayar','Ditunda') DEFAULT 'Belum Bayar',
    tgl_bayar       DATE DEFAULT NULL,
    CONSTRAINT fk_detail_pinjaman FOREIGN KEY (no_daftar) 
        REFERENCES pengajuan_pinjaman(no_daftar)
        ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ==============================
-- PENGAJUAN PINJAMAN DUA
-- ==============================
CREATE TABLE pengajuan_pinjaman_dua (
    no_daftar        VARCHAR(20)  NOT NULL PRIMARY KEY,
    id_user          VARCHAR(50)  NOT NULL,
    pinjaman         DECIMAL(12,2) NOT NULL,
    angsur_pinjaman  INT          NOT NULL DEFAULT 10, -- selalu 10x
    angsuran_per_bulan DECIMAL(12,2) DEFAULT 0,
    tgl_pembuatan    DATE         NOT NULL,
    keterangan       VARCHAR(255) DEFAULT NULL,
    status           ENUM('Diteruskan','Diproses','Direkap','Disetujui','Ditolak') DEFAULT 'Diteruskan',
    source             ENUM('Normal','Migrasi') DEFAULT 'Normal'   -- tambahan flag
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ==============================
-- DAFTAR PINJAMAN DUA
-- ==============================
CREATE TABLE daftar_pinjaman_dua (
    no_daftar       VARCHAR(20) PRIMARY KEY,
    id_user         VARCHAR(50) NOT NULL,
    pinjaman        DECIMAL(12,2) NOT NULL,
    jumlah_dibayar  DECIMAL(12,2) DEFAULT 0,
    angsur_pinjaman INT NOT NULL,
    sisa_angsuran   INT DEFAULT 0,
    status_angsuran ENUM('Belum Lunas','Lunas') DEFAULT 'Belum Lunas',
    source          ENUM('Normal','Migrasi') DEFAULT 'Normal',   -- tambahan flag
    keterangan      VARCHAR(255) DEFAULT NULL,                   -- catatan migrasi
    CONSTRAINT fk_daftar_pinjaman_dua FOREIGN KEY (no_daftar) 
        REFERENCES pengajuan_pinjaman_dua(no_daftar)
        ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ==============================
-- PERSETUJUAN PINJAMAN DUA
-- ==============================
CREATE TABLE persetujuan_pinjaman_dua (
  no_daftar       VARCHAR(20) PRIMARY KEY,
  no_anggota      VARCHAR(20) DEFAULT NULL,
  tgl_pengajuan   DATE DEFAULT NULL,
  id_ketua        VARCHAR(50) DEFAULT NULL,
  tgl_persetujuan DATE DEFAULT NULL,
  id_sekretaris   VARCHAR(50) DEFAULT NULL,
  tgl_dicatat     DATE DEFAULT NULL,
  id_bendahara    VARCHAR(50) DEFAULT NULL,
  tgl_direkap     DATE DEFAULT NULL,
  CONSTRAINT fk_persetujuan_dua FOREIGN KEY (no_daftar)
    REFERENCES pengajuan_pinjaman_dua(no_daftar)
    ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ==============================
-- DETAIL PINJAMAN DUA
-- ==============================
CREATE TABLE detail_pinjaman_dua (
    id_detail       INT AUTO_INCREMENT PRIMARY KEY,
    no_daftar       VARCHAR(20) NOT NULL,
    id_user         VARCHAR(50) NOT NULL,
    pinjaman        DECIMAL(12,2) NOT NULL,
    jumlah_angsuran INT NOT NULL,
    angsuran_ke     INT NOT NULL,
    bulan           DATE NOT NULL,
    jumlah_cicilan  DECIMAL(12,2) NOT NULL,
    status ENUM('Belum Bayar','Sudah Bayar') DEFAULT 'Belum Bayar',
    tgl_bayar       DATE DEFAULT NULL,
    CONSTRAINT fk_detail_pinjaman_dua FOREIGN KEY (no_daftar) 
        REFERENCES pengajuan_pinjaman_dua(no_daftar)
        ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ==============================
-- PENGAJUAN TABUNGAN BARU
-- ==============================
CREATE TABLE pengajuan_tabungan_baru (
    no_daftar        VARCHAR(20)  NOT NULL PRIMARY KEY,
    id_user          VARCHAR(50)  NOT NULL,
    tabungan_lama    DECIMAL(12,2) NOT NULL,
    tabungan_baru    DECIMAL(12,2) NOT NULL,
    tgl_pembuatan    DATE         NOT NULL,
    keterangan       TEXT DEFAULT NULL, -- opsional: catatan tambahan
    status           ENUM('Diteruskan','Diproses','Direkap','Disetujui','Ditolak') DEFAULT 'Diteruskan',
    CONSTRAINT fk_pengajuan_tabungan FOREIGN KEY (id_user)
        REFERENCES daftar_anggota(id_user)
        ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ==============================
-- DAFTAR TABUNGAN
-- ==============================
CREATE TABLE daftar_tabungan_baru (
    no_daftar   VARCHAR(20) PRIMARY KEY,
    id_user     VARCHAR(50) NOT NULL,
    tabungan    DECIMAL(12,2) NOT NULL,   -- nominal tabungan yang disetorkan sekali
    tgl_setor   DATE NOT NULL DEFAULT (CURDATE()), -- tanggal setor
    CONSTRAINT fk_daftar_tabungan FOREIGN KEY (no_daftar) 
        REFERENCES pengajuan_tabungan_baru(no_daftar)
        ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ==============================
-- PERSETUJUAN TABUNGAN
-- ==============================
CREATE TABLE persetujuan_tabungan_baru (
  no_daftar       VARCHAR(20) PRIMARY KEY,
  no_anggota      VARCHAR(20) DEFAULT NULL,
  tgl_pengajuan   DATE DEFAULT NULL,
  id_ketua        VARCHAR(50) DEFAULT NULL,
  tgl_persetujuan DATE DEFAULT NULL,
  id_sekretaris   VARCHAR(50) DEFAULT NULL,
  tgl_dicatat     DATE DEFAULT NULL,
  id_bendahara    VARCHAR(50) DEFAULT NULL,
  tgl_direkap     DATE DEFAULT NULL,
  CONSTRAINT fk_persetujuan_tabungan_baru FOREIGN KEY (no_daftar)
    REFERENCES pengajuan_tabungan_baru(no_daftar)
    ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ==============================
-- PENGAJUAN TUNDA PINJAMAN
-- ==============================
CREATE TABLE pengajuan_tunda_pinjaman (
    id_tunda      INT AUTO_INCREMENT PRIMARY KEY,
    no_daftar     VARCHAR(20) NOT NULL,
    id_user       VARCHAR(50) NOT NULL,
    tgl_pengajuan DATE DEFAULT NULL,
    bulan_tunda   DATE DEFAULT NULL,
    ktg           TEXT, -- alasan atau keterangan
    tunda_ke      INT DEFAULT 0, -- urutan tunda ke berapa
    status        ENUM('Diajukan','Disetujui','Ditolak') DEFAULT 'Diajukan',
    CONSTRAINT fk_tunda FOREIGN KEY (no_daftar)
        REFERENCES pengajuan_pinjaman(no_daftar)
        ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ==============================
-- DAFTAR TUNDA PINJAMAN
-- ==============================
CREATE TABLE daftar_tunda_pinjaman (
    id_daftar     INT AUTO_INCREMENT PRIMARY KEY,
    no_daftar     VARCHAR(20) NOT NULL,   -- relasi ke pengajuan_tunda_pinjaman
    id_user       VARCHAR(50) NOT NULL,   -- NIK/ID anggota
    no_anggota    VARCHAR(20) DEFAULT NULL,
    bulan_tunda   DATE NOT NULL,          -- bulan yang ditunda
    tunda_ke      INT DEFAULT 0,          -- urutan tunda ke berapa
    status        ENUM('Diajukan','Disetujui','Ditolak') DEFAULT 'Diajukan',
    tgl_setor     DATE DEFAULT NULL,      -- tanggal dicatat/setor
    CONSTRAINT fk_daftar_tunda FOREIGN KEY (no_daftar)
        REFERENCES pengajuan_tunda_pinjaman(no_daftar)
        ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ==============================
-- PERSETUJUAN TUNDA PINJAMAN
-- ==============================
CREATE TABLE persetujuan_tunda_pinjaman (
  id_persetujuan  INT AUTO_INCREMENT PRIMARY KEY,
  no_daftar       VARCHAR(20) NOT NULL,
  no_anggota      VARCHAR(20) DEFAULT NULL,
  tgl_pengajuan   DATE DEFAULT NULL,
  id_pengurus     VARCHAR(50) NOT NULL, -- NIK/ID pengurus yang menyetujui
  tgl_persetujuan DATE DEFAULT NULL,
  status          ENUM('Disetujui','Ditolak') NOT NULL,
  CONSTRAINT fk_tunda_persetujuan FOREIGN KEY (no_daftar)
      REFERENCES pengajuan_tunda_pinjaman(no_daftar)
      ON DELETE CASCADE,
  CONSTRAINT uq_no_daftar UNIQUE (no_daftar)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ==============================
-- DATA INDUK KOPERASI
-- ==============================
CREATE TABLE data_induk_koperasi (
    -- Identitas Anggota
    no_anggota          VARCHAR(30) NOT NULL,
    id_user             VARCHAR(50) NOT NULL,
    status_keanggotaan  ENUM('Aktif','Tidak Aktif') DEFAULT 'Aktif',
    tgl_terdaftar       DATE NOT NULL,
    kode_jab            VARCHAR(10) DEFAULT NULL,

    -- Dana Pensiun
    dana_pensiun        DECIMAL(12,2) DEFAULT 0,

    -- Simpanan Wajib Lama
    simpanan_wajib_lama        DECIMAL(12,2) DEFAULT 0,

    -- Rekap Simpanan
    simpanan_pokok      DECIMAL(12,2) DEFAULT 0,
    simpanan_wajib      DECIMAL(12,2) DEFAULT 0,
    tabungan            DECIMAL(12,2) DEFAULT 0,

    PRIMARY KEY (no_anggota, id_user)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ==============================
-- DETAIL BULANAN KOPERASI
-- ==============================
CREATE TABLE detail_bulanan_koperasi (
    no_anggota          VARCHAR(30) NOT NULL,   -- relasi ke data_induk_koperasi
    id_user             VARCHAR(50) NOT NULL,   -- relasi ke data_induk_koperasi
    status_keanggotaan  ENUM('Aktif','Tidak Aktif') DEFAULT 'Aktif',
    tgl_terdaftar       DATE NOT NULL,
    kode_jab            VARCHAR(10) DEFAULT NULL,

    periode_bulan       CHAR(7) NOT NULL,       -- format YYYY-MM

    -- Simpanan Pokok
    angsuran_pokok      DECIMAL(12,2) DEFAULT 0,
    angsuran_ke_pokok   INT DEFAULT NULL,
    tgl_bayar_angsuran  DATE DEFAULT NULL,

    -- Simpanan Wajib
    simpanan_wajib      DECIMAL(12,2) DEFAULT 0,

    -- Tabungan
    tabungan            DECIMAL(12,2) DEFAULT 0,

    -- Pinjaman 1
    angsuran_pinjaman   DECIMAL(12,2) DEFAULT 0,
    pinjaman_ke         INT DEFAULT NULL,
    tgl_bayar_pinjaman  DATE DEFAULT NULL,

    -- Pinjaman 2
    angsuran_pinjaman_dua DECIMAL(12,2) DEFAULT 0,
    pinjaman_dua_ke     INT DEFAULT NULL,
    tgl_bayar_pinjaman_dua DATE DEFAULT NULL,

    -- Total potongan bulan ini
    total_potongan DECIMAL(12,2) DEFAULT 0.00,

    PRIMARY KEY (no_anggota, periode_bulan)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ==============================
-- DETAIL SHU KOPERASI
-- ==============================
CREATE TABLE detail_shu (
    id_detail_shu BIGINT AUTO_INCREMENT PRIMARY KEY,
    id_periode INT NOT NULL,
    no_anggota VARCHAR(30) NOT NULL,
    id_user VARCHAR(50) NOT NULL,
    tabungan DECIMAL(18,2) DEFAULT 0,
    total_jasa DECIMAL(18,2) DEFAULT 0,
    shu_pinjaman DECIMAL(18,2) DEFAULT 0,
    shu_anggota DECIMAL(18,2) DEFAULT 0,
    total_shu DECIMAL(18,2) DEFAULT 0,
    shu_tabungan DECIMAL(18,2) DEFAULT 0,
    dibuat_pada TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uniq_shu (id_periode,id_user),
    KEY idx_periode (id_periode),
    KEY idx_user (id_user)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
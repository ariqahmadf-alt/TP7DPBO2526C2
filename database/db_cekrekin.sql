-- =====================================================================
-- Cekrek.in Booking Manager
-- Struktur database + data dummy (seluruh data bersifat fiktif)
-- Engine: InnoDB | Charset: utf8mb4
-- =====================================================================

CREATE DATABASE IF NOT EXISTS db_cekrekin
    CHARACTER SET utf8mb4
    COLLATE utf8mb4_unicode_ci;

USE db_cekrekin;

-- Hapus tabel lama (urutan terbalik dari ketergantungan FK) agar file ini
-- bisa diimpor ulang kapan saja.
DROP TABLE IF EXISTS payments;
DROP TABLE IF EXISTS booking_frames;
DROP TABLE IF EXISTS bookings;
DROP TABLE IF EXISTS booth_units;
DROP TABLE IF EXISTS frames;
DROP TABLE IF EXISTS packages;
DROP TABLE IF EXISTS customers;

-- ---------------------------------------------------------------------
-- customers: pemesan jasa photobooth
-- ---------------------------------------------------------------------
CREATE TABLE customers (
    id           INT AUTO_INCREMENT PRIMARY KEY,
    name         VARCHAR(100) NOT NULL,
    phone        VARCHAR(20)  NOT NULL,
    email        VARCHAR(100) NULL UNIQUE,
    organization VARCHAR(100) NULL,
    created_at   TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------------------------------------------------------------------
-- packages: paket sewa berdasarkan durasi
-- ---------------------------------------------------------------------
CREATE TABLE packages (
    id             INT AUTO_INCREMENT PRIMARY KEY,
    name           VARCHAR(100)  NOT NULL,
    price          DECIMAL(12,2) NOT NULL,
    duration_hours INT           NOT NULL,
    description    TEXT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------------------------------------------------------------------
-- frames: desain frame cetak (bisa menambah biaya)
-- ---------------------------------------------------------------------
CREATE TABLE frames (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    name        VARCHAR(100)  NOT NULL,
    theme       VARCHAR(50),
    extra_price DECIMAL(12,2) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------------------------------------------------------------------
-- booth_units: unit fisik photobooth
-- ---------------------------------------------------------------------
CREATE TABLE booth_units (
    id     INT AUTO_INCREMENT PRIMARY KEY,
    code   VARCHAR(20) NOT NULL UNIQUE,
    type   VARCHAR(50) NOT NULL,
    status ENUM('available','maintenance') NOT NULL DEFAULT 'available'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------------------------------------------------------------------
-- bookings: pemesanan acara
-- ---------------------------------------------------------------------
CREATE TABLE bookings (
    id             INT AUTO_INCREMENT PRIMARY KEY,
    customer_id    INT NOT NULL,
    package_id     INT NOT NULL,
    booth_unit_id  INT NOT NULL,
    event_name     VARCHAR(150) NOT NULL,
    venue          VARCHAR(200),
    event_date     DATE NOT NULL,
    start_time     TIME NOT NULL,
    end_time       TIME NOT NULL,
    overtime_hours INT NOT NULL DEFAULT 0,
    discount       DECIMAL(12,2) NOT NULL DEFAULT 0,
    total_price    DECIMAL(12,2) NOT NULL DEFAULT 0,
    status         ENUM('pending','confirmed','done','cancelled') NOT NULL DEFAULT 'pending',
    notes          TEXT,
    created_at     TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_bookings_customer FOREIGN KEY (customer_id)   REFERENCES customers(id)   ON DELETE RESTRICT,
    CONSTRAINT fk_bookings_package  FOREIGN KEY (package_id)    REFERENCES packages(id)    ON DELETE RESTRICT,
    CONSTRAINT fk_bookings_booth    FOREIGN KEY (booth_unit_id) REFERENCES booth_units(id) ON DELETE RESTRICT,
    -- Mempercepat pengecekan bentrok jadwal (unit + tanggal).
    INDEX idx_bookings_booth_date (booth_unit_id, event_date),
    INDEX idx_bookings_date (event_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------------------------------------------------------------------
-- booking_frames (pivot): frame yang dipilih pada sebuah booking
-- Hapus booking -> relasi ikut terhapus (CASCADE).
-- Frame yang dipakai booking tidak bisa dihapus (RESTRICT).
-- ---------------------------------------------------------------------
CREATE TABLE booking_frames (
    booking_id INT NOT NULL,
    frame_id   INT NOT NULL,
    PRIMARY KEY (booking_id, frame_id),
    CONSTRAINT fk_bf_booking FOREIGN KEY (booking_id) REFERENCES bookings(id) ON DELETE CASCADE,
    CONSTRAINT fk_bf_frame   FOREIGN KEY (frame_id)   REFERENCES frames(id)   ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------------------------------------------------------------------
-- payments: pembayaran (DP / pelunasan). Status bayar TIDAK disimpan,
-- melainkan dihitung dari total pembayaran vs total_price booking.
-- ---------------------------------------------------------------------
CREATE TABLE payments (
    id         INT AUTO_INCREMENT PRIMARY KEY,
    booking_id INT NOT NULL,
    amount     DECIMAL(12,2) NOT NULL,
    type       ENUM('dp','pelunasan') NOT NULL,
    method     ENUM('transfer','cash','qris') NOT NULL,
    paid_at    DATETIME NOT NULL,
    note       VARCHAR(200),
    CONSTRAINT fk_payments_booking FOREIGN KEY (booking_id) REFERENCES bookings(id) ON DELETE RESTRICT,
    INDEX idx_payments_paid_at (paid_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- =====================================================================
-- DATA DUMMY (fiktif)
-- =====================================================================

INSERT INTO customers (name, phone, email, organization) VALUES
('Rina Maharani',   '081200000001', 'rina.maharani@example.com', NULL),
('Dimas Prakoso',   '081200000002', 'dimas.prakoso@example.com', 'Himpunan Mahasiswa Teknik Contoh'),
('Salsa Nurhaliza', '081200000003', 'salsa.n@example.com',       'SMA Negeri Contoh 99'),
('Bagas Wiratama',  '081200000004', 'bagas.w@example.com',       'PT Maju Bersama Contoh'),
('Citra Anggraeni', '081200000005', NULL,                        NULL);

INSERT INTO packages (name, price, duration_hours, description) VALUES
('Basic',    1000000.00, 2, 'Sewa photobooth 2 jam, unlimited retake, soft file via QR Code.'),
('Standard', 1500000.00, 4, 'Sewa photobooth 4 jam, unlimited retake, filter foto, soft file HD dan GIF.'),
('Premium',  2000000.00, 6, 'Sewa photobooth 6 jam dengan fitur lengkap dan soft file HD dan GIF.'),
('Wedding',  2500000.00, 8, 'Sewa photobooth 8 jam untuk resepsi, termasuk satu desain frame bertema acara.');

INSERT INTO frames (name, theme, extra_price) VALUES
('Save The Moment',    'Minimalis',  0.00),
('Cekrek Pop',         'Pop Art',    50000.00),
('Newspaper Classic',  'Vintage',    75000.00),
('Pink Collage',       'Scrapbook',  75000.00),
('Custom Event Frame', 'Custom',     100000.00);

INSERT INTO booth_units (code, type, status) VALUES
('BTH-001', 'Portable Standard', 'available'),
('BTH-002', 'Portable Standard', 'available'),
('BTH-003', 'Portable Pro',      'maintenance');

-- Rumus total = harga paket + total extra frame + (overtime x 250.000) - diskon
INSERT INTO bookings
(id, customer_id, package_id, booth_unit_id, event_name, venue, event_date, start_time, end_time, overtime_hours, discount, total_price, status, notes) VALUES
-- 1) Wedding: 2.500.000 + 75.000 + 75.000 - 100.000 = 2.550.000 (selesai, lunas)
(1, 1, 4, 1, 'Resepsi Pernikahan Rina dan Fajar', 'Gedung Serbaguna Contoh, Bandung', '2026-10-17', '10:00:00', '18:00:00', 0, 100000.00, 2550000.00, 'done', 'Frame bertema bunga.'),
-- 2) Standard: 1.500.000 + 0 + 50.000 + (1 x 250.000) = 1.800.000 (selesai, lunas)
(2, 2, 2, 2, 'Dies Natalis Fakultas Contoh', 'Aula Kampus Contoh', '2026-11-02', '09:00:00', '13:00:00', 1, 0.00, 1800000.00, 'done', 'Tambah durasi 1 jam di hari-H.'),
-- 3) Basic: 1.000.000 + 0 = 1.000.000 (terkonfirmasi, baru DP)
(3, 3, 1, 1, 'Wisuda SMA Negeri Contoh 99', 'Aula SMA Contoh, Bandung', '2026-11-14', '08:00:00', '10:00:00', 0, 0.00, 1000000.00, 'confirmed', NULL),
-- 4) Premium: 2.000.000 + 50.000 + 100.000 + (2 x 250.000) - 200.000 = 2.450.000 (menunggu, belum bayar)
(4, 4, 3, 2, 'Gathering Tahunan PT Maju Bersama', 'Ballroom Hotel Contoh, Bandung', '2026-11-21', '13:00:00', '19:00:00', 2, 200000.00, 2450000.00, 'pending', 'Diskon klien korporat.'),
-- 5) Basic: 1.000.000 + 75.000 = 1.075.000 (terkonfirmasi, baru DP)
(5, 5, 1, 1, 'Ulang Tahun ke-17 Citra', 'Kafe Contoh, Bandung', '2026-11-28', '18:00:00', '20:00:00', 0, 0.00, 1075000.00, 'confirmed', NULL),
-- 6) Standard: 1.500.000 + 75.000 = 1.575.000 (BATAL). Sengaja memakai unit dan jam yang
--    beririsan dengan booking #3 untuk menunjukkan booking batal tidak memblokir jadwal.
(6, 1, 2, 1, 'Gathering Keluarga Besar', 'Villa Contoh, Lembang', '2026-11-14', '09:00:00', '13:00:00', 0, 0.00, 1575000.00, 'cancelled', 'Dibatalkan oleh pemesan.');

INSERT INTO booking_frames (booking_id, frame_id) VALUES
(1, 3), (1, 4),
(2, 1), (2, 2),
(3, 1),
(4, 2), (4, 5),
(5, 1), (5, 3),
(6, 4);

INSERT INTO payments (booking_id, amount, type, method, paid_at, note) VALUES
-- Booking 1: lunas (1.000.000 + 1.550.000 = 2.550.000)
(1, 1000000.00, 'dp',        'transfer', '2026-09-20 14:30:00', 'DP awal'),
(1, 1550000.00, 'pelunasan', 'cash',     '2026-10-17 09:45:00', 'Pelunasan di lokasi'),
-- Booking 2: lunas (900.000 + 900.000 = 1.800.000)
(2,  900000.00, 'dp',        'transfer', '2026-10-20 10:15:00', 'DP 50%'),
(2,  900000.00, 'pelunasan', 'qris',     '2026-11-02 12:50:00', 'Pelunasan termasuk overtime'),
-- Booking 3: baru DP (sisa 500.000)
(3,  500000.00, 'dp',        'transfer', '2026-11-03 16:00:00', 'DP 50%'),
-- Booking 4: belum ada pembayaran (sisa 2.450.000)
-- Booking 5: baru DP (sisa 775.000)
(5,  300000.00, 'dp',        'qris',     '2026-11-04 19:20:00', 'DP awal');

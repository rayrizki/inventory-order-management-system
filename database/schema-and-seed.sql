-- Inventory & Order Management System
-- Schema + seed. Idempotent dari kondisi database kosong (dijalankan otomatis
-- oleh image MySQL resmi lewat docker-entrypoint-initdb.d saat volume masih kosong).
--
-- Seluruh tabel ditulis sekaligus mengikuti entity di docs/planning/class-diagram-initial.md,
-- meski baru tabel `users` yang dipakai oleh kode PHP saat ini (slice login) - supaya
-- slice berikutnya (master data, PO, SO) tidak perlu migrasi ulang.

SET NAMES utf8mb4;
SET time_zone = '+00:00';

-- =========================================================
-- Auth & User (AUTH-01, USR-01)
-- =========================================================

CREATE TABLE IF NOT EXISTS users (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(150) NOT NULL,
    email VARCHAR(190) NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    role ENUM('Admin', 'Sales', 'WarehouseStaff') NOT NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_users_email (email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- =========================================================
-- Master Data (PRD-01, WH-01)
-- =========================================================

CREATE TABLE IF NOT EXISTS categories (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(150) NOT NULL,
    description TEXT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS warehouses (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(150) NOT NULL,
    location VARCHAR(255) NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS products (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    sku VARCHAR(50) NOT NULL,
    name VARCHAR(200) NOT NULL,
    category_id INT UNSIGNED NOT NULL,
    unit VARCHAR(30) NOT NULL,
    buy_price DECIMAL(14, 2) NOT NULL DEFAULT 0 CHECK (buy_price >= 0),
    sell_price DECIMAL(14, 2) NOT NULL DEFAULT 0 CHECK (sell_price >= 0),
    reorder_point INT UNSIGNED NOT NULL DEFAULT 0,
    image_path VARCHAR(255) NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    UNIQUE KEY uq_products_sku (sku),
    KEY idx_products_category (category_id),
    CONSTRAINT fk_products_category FOREIGN KEY (category_id) REFERENCES categories (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS product_stock (
    product_id INT UNSIGNED NOT NULL,
    warehouse_id INT UNSIGNED NOT NULL,
    quantity INT NOT NULL DEFAULT 0 CHECK (quantity >= 0),
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (product_id, warehouse_id),
    CONSTRAINT fk_stock_product FOREIGN KEY (product_id) REFERENCES products (id),
    CONSTRAINT fk_stock_warehouse FOREIGN KEY (warehouse_id) REFERENCES warehouses (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS suppliers (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(150) NOT NULL,
    contact VARCHAR(150) NULL,
    address VARCHAR(255) NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS customers (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(150) NOT NULL,
    contact VARCHAR(150) NULL,
    address VARCHAR(255) NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- =========================================================
-- Purchase Order & Goods Receipt (PO-01)
-- =========================================================

CREATE TABLE IF NOT EXISTS purchase_orders (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    supplier_id INT UNSIGNED NOT NULL,
    warehouse_id INT UNSIGNED NOT NULL,
    status ENUM('Draft', 'Ordered', 'PartiallyReceived', 'Received', 'Cancelled') NOT NULL DEFAULT 'Draft',
    order_date DATE NOT NULL,
    created_by INT UNSIGNED NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_po_supplier (supplier_id),
    KEY idx_po_warehouse (warehouse_id),
    KEY idx_po_status_date (status, order_date),
    CONSTRAINT fk_po_supplier FOREIGN KEY (supplier_id) REFERENCES suppliers (id),
    CONSTRAINT fk_po_warehouse FOREIGN KEY (warehouse_id) REFERENCES warehouses (id),
    CONSTRAINT fk_po_created_by FOREIGN KEY (created_by) REFERENCES users (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS purchase_order_items (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    purchase_order_id INT UNSIGNED NOT NULL,
    product_id INT UNSIGNED NOT NULL,
    qty INT UNSIGNED NOT NULL CHECK (qty > 0),
    buy_price DECIMAL(14, 2) NOT NULL CHECK (buy_price >= 0),
    received_qty INT UNSIGNED NOT NULL DEFAULT 0 CHECK (received_qty >= 0),
    -- ARCH-02: pagar terakhir di level database, sejajar dengan
    -- product_stock.CHECK (quantity >= 0). Guard utama ada di WHERE milik
    -- incrementItemReceivedQtyIfWithinOrdered(), tapi constraint ini
    -- memastikan tidak ada jalur lain (query manual, migrasi, perbaikan
    -- data) yang bisa membuat barang diterima melebihi yang dipesan.
    CONSTRAINT chk_poi_received_not_over_ordered CHECK (received_qty <= qty),
    KEY idx_poi_po (purchase_order_id),
    KEY idx_poi_product (product_id),
    CONSTRAINT fk_poi_po FOREIGN KEY (purchase_order_id) REFERENCES purchase_orders (id),
    CONSTRAINT fk_poi_product FOREIGN KEY (product_id) REFERENCES products (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- =========================================================
-- Sales Order & Goods Issue (SO-01)
-- =========================================================

CREATE TABLE IF NOT EXISTS sales_orders (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    customer_id INT UNSIGNED NOT NULL,
    warehouse_id INT UNSIGNED NOT NULL,
    status ENUM('Draft', 'PendingApproval', 'Approved', 'Fulfilled', 'Cancelled') NOT NULL DEFAULT 'Draft',
    created_by INT UNSIGNED NOT NULL,
    approved_by INT UNSIGNED NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_so_customer (customer_id),
    KEY idx_so_warehouse (warehouse_id),
    KEY idx_so_status_date (status, created_at),
    CONSTRAINT fk_so_customer FOREIGN KEY (customer_id) REFERENCES customers (id),
    CONSTRAINT fk_so_warehouse FOREIGN KEY (warehouse_id) REFERENCES warehouses (id),
    CONSTRAINT fk_so_created_by FOREIGN KEY (created_by) REFERENCES users (id),
    CONSTRAINT fk_so_approved_by FOREIGN KEY (approved_by) REFERENCES users (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS sales_order_items (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    sales_order_id INT UNSIGNED NOT NULL,
    product_id INT UNSIGNED NOT NULL,
    qty INT UNSIGNED NOT NULL CHECK (qty > 0),
    sell_price DECIMAL(14, 2) NOT NULL CHECK (sell_price >= 0),
    KEY idx_soi_so (sales_order_id),
    KEY idx_soi_product (product_id),
    CONSTRAINT fk_soi_so FOREIGN KEY (sales_order_id) REFERENCES sales_orders (id),
    CONSTRAINT fk_soi_product FOREIGN KEY (product_id) REFERENCES products (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- =========================================================
-- Stock Ledger (shared core - ARCH-02)
-- =========================================================

CREATE TABLE IF NOT EXISTS stock_ledger (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    product_id INT UNSIGNED NOT NULL,
    warehouse_id INT UNSIGNED NOT NULL,
    movement_type ENUM('Receipt', 'Issue', 'Adjustment') NOT NULL,
    quantity INT NOT NULL,
    reference_type VARCHAR(30) NOT NULL,
    reference_id INT UNSIGNED NOT NULL,
    performed_by INT UNSIGNED NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    -- Kolom equality (product_id, warehouse_id) di depan, kolom range/sort
    -- (created_at) di belakang - urutan composite index mengikuti kaidah
    -- left-prefix dari modul SQL, dipakai laporan pergerakan stok per tanggal.
    KEY idx_ledger_product_warehouse_date (product_id, warehouse_id, created_at),
    KEY idx_ledger_reference (reference_type, reference_id),
    CONSTRAINT fk_ledger_product FOREIGN KEY (product_id) REFERENCES products (id),
    CONSTRAINT fk_ledger_warehouse FOREIGN KEY (warehouse_id) REFERENCES warehouses (id),
    CONSTRAINT fk_ledger_performed_by FOREIGN KEY (performed_by) REFERENCES users (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- =========================================================
-- Seed - akun demo (§7.1: 1 Admin, minimal 2 Sales, minimal 2 Warehouse Staff)
-- Password untuk semua akun demo: Password123!
-- Hash dihasilkan dari password_hash('Password123!', PASSWORD_DEFAULT) - lihat README.
-- =========================================================

INSERT INTO users (name, email, password_hash, role, is_active) VALUES
    ('Admin Utama', 'admin@iom.test', '$2y$10$fVuPvBwczTZ6KsML63AXNOBZuqn1YtxNZOfo047As/68lhE9aTDuu', 'Admin', 1),
    ('Sales Satu', 'sales1@iom.test', '$2y$10$fVuPvBwczTZ6KsML63AXNOBZuqn1YtxNZOfo047As/68lhE9aTDuu', 'Sales', 1),
    ('Sales Dua', 'sales2@iom.test', '$2y$10$fVuPvBwczTZ6KsML63AXNOBZuqn1YtxNZOfo047As/68lhE9aTDuu', 'Sales', 1),
    ('Gudang Satu', 'warehouse1@iom.test', '$2y$10$fVuPvBwczTZ6KsML63AXNOBZuqn1YtxNZOfo047As/68lhE9aTDuu', 'WarehouseStaff', 1),
    ('Gudang Dua', 'warehouse2@iom.test', '$2y$10$fVuPvBwczTZ6KsML63AXNOBZuqn1YtxNZOfo047As/68lhE9aTDuu', 'WarehouseStaff', 1);

-- =========================================================
-- Seed - master data awal (§7.1: minimal dua gudang)
-- =========================================================

INSERT INTO categories (name, description) VALUES
    ('Elektronik', 'Perangkat elektronik dan aksesorisnya'),
    ('Alat Tulis Kantor', 'Kebutuhan tulis dan administrasi kantor'),
    ('Makanan & Minuman', 'Produk konsumsi habis pakai'),
    ('Perlengkapan Rumah Tangga', 'Peralatan dan perlengkapan rumah tangga'),
    ('Pakaian', 'Pakaian dan aksesoris fashion'),
    ('Kesehatan & Kecantikan', 'Produk perawatan tubuh, kosmetik, dan kesehatan'),
    ('Otomotif', 'Suku cadang dan aksesoris kendaraan'),
    ('Olahraga & Outdoor', 'Perlengkapan olahraga dan kegiatan luar ruangan'),
    ('Mainan & Hobi', 'Mainan anak dan barang hobi'),
    ('Perkakas & Konstruksi', 'Alat pertukangan dan bahan bangunan'),
    ('Furniture', 'Perabot rumah dan kantor'),
    ('Peralatan Dapur', 'Perlengkapan memasak dan dapur'),
    ('Bayi & Anak', 'Kebutuhan bayi dan anak-anak'),
    ('Pertanian & Peternakan', 'Kebutuhan pertanian dan peternakan'),
    ('Buku & Alat Peraga', 'Buku, media edukasi, dan alat peraga');

INSERT INTO warehouses (name, location, is_active) VALUES
    ('Gudang Pusat Jakarta', 'Jakarta', 1),
    ('Gudang Cabang Surabaya', 'Surabaya', 1);

-- 30 produk, 2 per kategori (§7.1: minimal 30 produk dengan variasi reorder
-- point). category_id dicari lewat subquery nama (bukan angka id langsung)
-- supaya seed ini tetap benar walau AUTO_INCREMENT sudah bergeser (mis. di
-- database dev yang pernah dipakai untuk uji create/delete kategori).
INSERT INTO products (sku, name, category_id, unit, buy_price, sell_price, reorder_point, is_active) VALUES
    ('ELK-001', 'Kabel HDMI 2 Meter', (SELECT id FROM categories WHERE name = 'Elektronik'), 'pcs', 25000, 40000, 20, 1),
    ('ELK-002', 'Charger USB-C 20W', (SELECT id FROM categories WHERE name = 'Elektronik'), 'pcs', 45000, 75000, 15, 1),
    ('ATK-001', 'Pulpen Gel 0.5mm (Box isi 12)', (SELECT id FROM categories WHERE name = 'Alat Tulis Kantor'), 'box', 18000, 28000, 30, 1),
    ('ATK-002', 'Kertas HVS A4 80gsm', (SELECT id FROM categories WHERE name = 'Alat Tulis Kantor'), 'rim', 42000, 55000, 25, 1),
    ('MMN-001', 'Kopi Sachet 3in1 (Box isi 30)', (SELECT id FROM categories WHERE name = 'Makanan & Minuman'), 'box', 22000, 32000, 40, 1),
    ('MMN-002', 'Teh Celup Premium (Box isi 25)', (SELECT id FROM categories WHERE name = 'Makanan & Minuman'), 'box', 15000, 24000, 35, 1),
    ('PRT-001', 'Sapu Lantai Serat Nilon', (SELECT id FROM categories WHERE name = 'Perlengkapan Rumah Tangga'), 'pcs', 20000, 35000, 15, 1),
    ('PRT-002', 'Ember Plastik 15 Liter', (SELECT id FROM categories WHERE name = 'Perlengkapan Rumah Tangga'), 'pcs', 18000, 30000, 20, 1),
    ('PKN-001', 'Kaos Polos Cotton Combed', (SELECT id FROM categories WHERE name = 'Pakaian'), 'pcs', 35000, 60000, 25, 1),
    ('PKN-002', 'Celana Jeans Slim Fit', (SELECT id FROM categories WHERE name = 'Pakaian'), 'pcs', 90000, 150000, 10, 1),
    ('KSC-001', 'Hand Sanitizer 100ml', (SELECT id FROM categories WHERE name = 'Kesehatan & Kecantikan'), 'pcs', 8000, 15000, 50, 1),
    ('KSC-002', 'Masker Medis (Box isi 50)', (SELECT id FROM categories WHERE name = 'Kesehatan & Kecantikan'), 'box', 25000, 40000, 30, 1),
    ('OTM-001', 'Oli Mesin Motor 1 Liter', (SELECT id FROM categories WHERE name = 'Otomotif'), 'botol', 35000, 55000, 20, 1),
    ('OTM-002', 'Aki Motor Kering', (SELECT id FROM categories WHERE name = 'Otomotif'), 'pcs', 180000, 250000, 5, 1),
    ('OOR-001', 'Matras Yoga 6mm', (SELECT id FROM categories WHERE name = 'Olahraga & Outdoor'), 'pcs', 45000, 75000, 10, 1),
    ('OOR-002', 'Botol Minum Olahraga 1L', (SELECT id FROM categories WHERE name = 'Olahraga & Outdoor'), 'pcs', 20000, 35000, 15, 1),
    ('MHB-001', 'Puzzle Kayu Edukasi', (SELECT id FROM categories WHERE name = 'Mainan & Hobi'), 'pcs', 25000, 45000, 15, 1),
    ('MHB-002', 'Mobil Remote Control', (SELECT id FROM categories WHERE name = 'Mainan & Hobi'), 'pcs', 120000, 200000, 8, 1),
    ('PKK-001', 'Bor Listrik 10mm', (SELECT id FROM categories WHERE name = 'Perkakas & Konstruksi'), 'pcs', 250000, 380000, 5, 1),
    ('PKK-002', 'Palu Konstruksi 500gr', (SELECT id FROM categories WHERE name = 'Perkakas & Konstruksi'), 'pcs', 35000, 55000, 12, 1),
    ('FRN-001', 'Rak Buku Minimalis 3 Tingkat', (SELECT id FROM categories WHERE name = 'Furniture'), 'pcs', 150000, 250000, 8, 1),
    ('FRN-002', 'Kursi Lipat Portable', (SELECT id FROM categories WHERE name = 'Furniture'), 'pcs', 90000, 140000, 12, 1),
    ('PDP-001', 'Panci Set Anti Lengket', (SELECT id FROM categories WHERE name = 'Peralatan Dapur'), 'set', 120000, 195000, 10, 1),
    ('PDP-002', 'Talenan Kayu Jati', (SELECT id FROM categories WHERE name = 'Peralatan Dapur'), 'pcs', 25000, 40000, 20, 1),
    ('BAP-001', 'Buku Tulis 38 Lembar (Pack isi 10)', (SELECT id FROM categories WHERE name = 'Buku & Alat Peraga'), 'pack', 22000, 35000, 25, 1),
    ('BAP-002', 'Globe Dunia Edukasi 25cm', (SELECT id FROM categories WHERE name = 'Buku & Alat Peraga'), 'pcs', 65000, 110000, 8, 1),
    ('PTP-001', 'Pupuk NPK 1kg', (SELECT id FROM categories WHERE name = 'Pertanian & Peternakan'), 'kg', 12000, 20000, 30, 1),
    ('PTP-002', 'Pakan Ternak Ayam 5kg', (SELECT id FROM categories WHERE name = 'Pertanian & Peternakan'), 'sak', 45000, 65000, 20, 1),
    ('BYA-001', 'Popok Bayi Ukuran M (Pack isi 40)', (SELECT id FROM categories WHERE name = 'Bayi & Anak'), 'pack', 65000, 95000, 25, 1),
    ('BYA-002', 'Botol Susu Bayi 250ml', (SELECT id FROM categories WHERE name = 'Bayi & Anak'), 'pcs', 25000, 42000, 15, 1);

-- Baris stok per gudang (WH-01). Angka DI BAWAH ini adalah stok AWAL sebelum
-- order diterapkan; goods receipt (PO) dan goods issue (SO) yang di-seed di
-- bagian bawah file ini masih akan menambah/mengurangi sebagian baris, jadi
-- stok akhir yang dilihat aplikasi bukan angka di blok ini.
--
-- Kondisi AKHIR yang disengaja (setelah seluruh receipt & issue diterapkan):
-- 3 produk berada di bawah reorder point - PKN-002 (4 dari rp 10), OOR-001
-- (8 dari rp 10), dan PKK-002 (8 dari rp 12) - supaya "produk di bawah
-- reorder point" (DASH-01, FIND-01 status stok, JOB-01 check-low-stock)
-- punya data nyata untuk didemokan, bukan skenario kosong/hipotetis.
INSERT INTO product_stock (product_id, warehouse_id, quantity) VALUES
    ((SELECT id FROM products WHERE sku = 'ELK-001'), (SELECT id FROM warehouses WHERE name = 'Gudang Pusat Jakarta'), 30),
    ((SELECT id FROM products WHERE sku = 'ELK-001'), (SELECT id FROM warehouses WHERE name = 'Gudang Cabang Surabaya'), 15),
    ((SELECT id FROM products WHERE sku = 'ELK-002'), (SELECT id FROM warehouses WHERE name = 'Gudang Pusat Jakarta'), 5),
    ((SELECT id FROM products WHERE sku = 'ELK-002'), (SELECT id FROM warehouses WHERE name = 'Gudang Cabang Surabaya'), 3),
    ((SELECT id FROM products WHERE sku = 'ATK-001'), (SELECT id FROM warehouses WHERE name = 'Gudang Pusat Jakarta'), 40),
    ((SELECT id FROM products WHERE sku = 'ATK-001'), (SELECT id FROM warehouses WHERE name = 'Gudang Cabang Surabaya'), 25),
    ((SELECT id FROM products WHERE sku = 'ATK-002'), (SELECT id FROM warehouses WHERE name = 'Gudang Pusat Jakarta'), 35),
    ((SELECT id FROM products WHERE sku = 'ATK-002'), (SELECT id FROM warehouses WHERE name = 'Gudang Cabang Surabaya'), 20),
    ((SELECT id FROM products WHERE sku = 'MMN-001'), (SELECT id FROM warehouses WHERE name = 'Gudang Pusat Jakarta'), 50),
    ((SELECT id FROM products WHERE sku = 'MMN-001'), (SELECT id FROM warehouses WHERE name = 'Gudang Cabang Surabaya'), 30),
    ((SELECT id FROM products WHERE sku = 'MMN-002'), (SELECT id FROM warehouses WHERE name = 'Gudang Pusat Jakarta'), 40),
    ((SELECT id FROM products WHERE sku = 'MMN-002'), (SELECT id FROM warehouses WHERE name = 'Gudang Cabang Surabaya'), 20),
    ((SELECT id FROM products WHERE sku = 'PRT-001'), (SELECT id FROM warehouses WHERE name = 'Gudang Pusat Jakarta'), 20),
    ((SELECT id FROM products WHERE sku = 'PRT-001'), (SELECT id FROM warehouses WHERE name = 'Gudang Cabang Surabaya'), 10),
    ((SELECT id FROM products WHERE sku = 'PRT-002'), (SELECT id FROM warehouses WHERE name = 'Gudang Pusat Jakarta'), 25),
    ((SELECT id FROM products WHERE sku = 'PRT-002'), (SELECT id FROM warehouses WHERE name = 'Gudang Cabang Surabaya'), 15),
    ((SELECT id FROM products WHERE sku = 'PKN-001'), (SELECT id FROM warehouses WHERE name = 'Gudang Pusat Jakarta'), 30),
    ((SELECT id FROM products WHERE sku = 'PKN-001'), (SELECT id FROM warehouses WHERE name = 'Gudang Cabang Surabaya'), 20),
    ((SELECT id FROM products WHERE sku = 'PKN-002'), (SELECT id FROM warehouses WHERE name = 'Gudang Pusat Jakarta'), 2),
    ((SELECT id FROM products WHERE sku = 'PKN-002'), (SELECT id FROM warehouses WHERE name = 'Gudang Cabang Surabaya'), 2),
    ((SELECT id FROM products WHERE sku = 'KSC-001'), (SELECT id FROM warehouses WHERE name = 'Gudang Pusat Jakarta'), 60),
    ((SELECT id FROM products WHERE sku = 'KSC-001'), (SELECT id FROM warehouses WHERE name = 'Gudang Cabang Surabaya'), 40),
    ((SELECT id FROM products WHERE sku = 'KSC-002'), (SELECT id FROM warehouses WHERE name = 'Gudang Pusat Jakarta'), 35),
    ((SELECT id FROM products WHERE sku = 'KSC-002'), (SELECT id FROM warehouses WHERE name = 'Gudang Cabang Surabaya'), 25),
    ((SELECT id FROM products WHERE sku = 'OTM-001'), (SELECT id FROM warehouses WHERE name = 'Gudang Pusat Jakarta'), 25),
    ((SELECT id FROM products WHERE sku = 'OTM-001'), (SELECT id FROM warehouses WHERE name = 'Gudang Cabang Surabaya'), 15),
    ((SELECT id FROM products WHERE sku = 'OTM-002'), (SELECT id FROM warehouses WHERE name = 'Gudang Pusat Jakarta'), 1),
    ((SELECT id FROM products WHERE sku = 'OTM-002'), (SELECT id FROM warehouses WHERE name = 'Gudang Cabang Surabaya'), 1),
    ((SELECT id FROM products WHERE sku = 'OOR-001'), (SELECT id FROM warehouses WHERE name = 'Gudang Pusat Jakarta'), 15),
    ((SELECT id FROM products WHERE sku = 'OOR-001'), (SELECT id FROM warehouses WHERE name = 'Gudang Cabang Surabaya'), 8),
    ((SELECT id FROM products WHERE sku = 'OOR-002'), (SELECT id FROM warehouses WHERE name = 'Gudang Pusat Jakarta'), 20),
    ((SELECT id FROM products WHERE sku = 'OOR-002'), (SELECT id FROM warehouses WHERE name = 'Gudang Cabang Surabaya'), 10),
    ((SELECT id FROM products WHERE sku = 'MHB-001'), (SELECT id FROM warehouses WHERE name = 'Gudang Pusat Jakarta'), 18),
    ((SELECT id FROM products WHERE sku = 'MHB-001'), (SELECT id FROM warehouses WHERE name = 'Gudang Cabang Surabaya'), 12),
    ((SELECT id FROM products WHERE sku = 'MHB-002'), (SELECT id FROM warehouses WHERE name = 'Gudang Pusat Jakarta'), 2),
    ((SELECT id FROM products WHERE sku = 'MHB-002'), (SELECT id FROM warehouses WHERE name = 'Gudang Cabang Surabaya'), 1),
    ((SELECT id FROM products WHERE sku = 'PKK-001'), (SELECT id FROM warehouses WHERE name = 'Gudang Pusat Jakarta'), 1),
    ((SELECT id FROM products WHERE sku = 'PKK-001'), (SELECT id FROM warehouses WHERE name = 'Gudang Cabang Surabaya'), 0),
    ((SELECT id FROM products WHERE sku = 'PKK-002'), (SELECT id FROM warehouses WHERE name = 'Gudang Pusat Jakarta'), 15),
    ((SELECT id FROM products WHERE sku = 'PKK-002'), (SELECT id FROM warehouses WHERE name = 'Gudang Cabang Surabaya'), 8),
    ((SELECT id FROM products WHERE sku = 'FRN-001'), (SELECT id FROM warehouses WHERE name = 'Gudang Pusat Jakarta'), 3),
    ((SELECT id FROM products WHERE sku = 'FRN-001'), (SELECT id FROM warehouses WHERE name = 'Gudang Cabang Surabaya'), 2),
    ((SELECT id FROM products WHERE sku = 'FRN-002'), (SELECT id FROM warehouses WHERE name = 'Gudang Pusat Jakarta'), 15),
    ((SELECT id FROM products WHERE sku = 'FRN-002'), (SELECT id FROM warehouses WHERE name = 'Gudang Cabang Surabaya'), 10),
    ((SELECT id FROM products WHERE sku = 'PDP-001'), (SELECT id FROM warehouses WHERE name = 'Gudang Pusat Jakarta'), 12),
    ((SELECT id FROM products WHERE sku = 'PDP-001'), (SELECT id FROM warehouses WHERE name = 'Gudang Cabang Surabaya'), 8),
    ((SELECT id FROM products WHERE sku = 'PDP-002'), (SELECT id FROM warehouses WHERE name = 'Gudang Pusat Jakarta'), 25),
    ((SELECT id FROM products WHERE sku = 'PDP-002'), (SELECT id FROM warehouses WHERE name = 'Gudang Cabang Surabaya'), 15),
    ((SELECT id FROM products WHERE sku = 'BAP-001'), (SELECT id FROM warehouses WHERE name = 'Gudang Pusat Jakarta'), 30),
    ((SELECT id FROM products WHERE sku = 'BAP-001'), (SELECT id FROM warehouses WHERE name = 'Gudang Cabang Surabaya'), 20),
    ((SELECT id FROM products WHERE sku = 'BAP-002'), (SELECT id FROM warehouses WHERE name = 'Gudang Pusat Jakarta'), 0),
    ((SELECT id FROM products WHERE sku = 'BAP-002'), (SELECT id FROM warehouses WHERE name = 'Gudang Cabang Surabaya'), 0),
    ((SELECT id FROM products WHERE sku = 'PTP-001'), (SELECT id FROM warehouses WHERE name = 'Gudang Pusat Jakarta'), 40),
    ((SELECT id FROM products WHERE sku = 'PTP-001'), (SELECT id FROM warehouses WHERE name = 'Gudang Cabang Surabaya'), 25),
    ((SELECT id FROM products WHERE sku = 'PTP-002'), (SELECT id FROM warehouses WHERE name = 'Gudang Pusat Jakarta'), 25),
    ((SELECT id FROM products WHERE sku = 'PTP-002'), (SELECT id FROM warehouses WHERE name = 'Gudang Cabang Surabaya'), 15),
    ((SELECT id FROM products WHERE sku = 'BYA-001'), (SELECT id FROM warehouses WHERE name = 'Gudang Pusat Jakarta'), 30),
    ((SELECT id FROM products WHERE sku = 'BYA-001'), (SELECT id FROM warehouses WHERE name = 'Gudang Cabang Surabaya'), 20),
    ((SELECT id FROM products WHERE sku = 'BYA-002'), (SELECT id FROM warehouses WHERE name = 'Gudang Pusat Jakarta'), 20),
    ((SELECT id FROM products WHERE sku = 'BYA-002'), (SELECT id FROM warehouses WHERE name = 'Gudang Cabang Surabaya'), 12);

-- =========================================================
-- Seed - Supplier & Customer (§1.3), dipakai referensi 25 order di bawah
-- =========================================================

INSERT INTO suppliers (name, contact, address, is_active) VALUES
    ('CV Sumber Makmur Elektronik', 'Budi Santoso - 081234567801', 'Jakarta', 1),
    ('PT Grosir Alat Tulis Nusantara', 'Siti Aminah - 081234567802', 'Bandung', 1),
    ('CV Mitra Perkakas Jaya', 'Agus Wijaya - 081234567803', 'Surabaya', 1),
    ('UD Sumber Pangan Sejahtera', 'Dewi Lestari - 081234567804', 'Semarang', 1);

INSERT INTO customers (name, contact, address, is_active) VALUES
    ('Toko Berkah Jaya', 'Hendra Kusuma - 081298765401', 'Jakarta', 1),
    ('CV Anugerah Sejahtera', 'Rina Wulandari - 081298765402', 'Bekasi', 1),
    ('Toko Makmur Abadi', 'Joko Purnomo - 081298765403', 'Surabaya', 1),
    ('PT Cahaya Nusantara Retail', 'Maya Sari - 081298765404', 'Tangerang', 1);

-- =========================================================
-- Seed - 25 order gabungan PO+SO (§7.1 "minimal 25 order gabungan (PO+SO)
-- dengan variasi status, termasuk contoh yang PendingApproval dan
-- Cancelled") - dibutuhkan FIND-01 (pagination order teruji dengan data
-- nyata, bukan cuma skenario kosong) dan DASH-01/REPORT-01 berikutnya
-- (butuh data order sungguhan supaya agregasinya bermakna).
--
-- Ditulis sebagai urutan INSERT tunggal (bukan lewat Service/Controller
-- PHP - seed dijalankan murni oleh MySQL saat container init, sebelum
-- aplikasi PHP ada koneksi) menggunakan @po_id/@so_id (LAST_INSERT_ID())
-- untuk menghubungkan header ke item/ledger tanpa menebak id secara
-- hardcode. Untuk PO berstatus PartiallyReceived/Received dan SO berstatus
-- Fulfilled - yaitu status yang di aplikasi nyata SELALU dihasilkan lewat
-- transaksi GoodsReceiptService/GoodsIssueService yang menulis
-- product_stock+stock_ledger sekaligus (ARCH-02) - baris stock_ledger dan
-- penyesuaian product_stock ditulis di sini juga, supaya seed data ini
-- tetap konsisten dengan invarian yang sama (StockLedger tidak pernah lepas
-- dari ProductStock) meski ditulis lewat SQL langsung, bukan lewat service.
-- Status lain (Draft/Ordered/Cancelled/PendingApproval/Approved) belum
-- pernah menyentuh stok sama sekali di aplikasi nyata, jadi sengaja tidak
-- diberi baris ledger atau perubahan stok di sini juga.
-- =========================================================

-- --- Purchase Order 1-3: Draft (belum diajukan, belum menyentuh stok) ---

INSERT INTO purchase_orders (supplier_id, warehouse_id, status, order_date, created_by) VALUES
    ((SELECT id FROM suppliers WHERE name = 'CV Sumber Makmur Elektronik'), (SELECT id FROM warehouses WHERE name = 'Gudang Pusat Jakarta'), 'Draft', '2026-09-14', (SELECT id FROM users WHERE email = 'admin@iom.test'));
SET @po_id = LAST_INSERT_ID();
INSERT INTO purchase_order_items (purchase_order_id, product_id, qty, buy_price) VALUES
    (@po_id, (SELECT id FROM products WHERE sku = 'ELK-002'), 10, 45000);

INSERT INTO purchase_orders (supplier_id, warehouse_id, status, order_date, created_by) VALUES
    ((SELECT id FROM suppliers WHERE name = 'CV Mitra Perkakas Jaya'), (SELECT id FROM warehouses WHERE name = 'Gudang Cabang Surabaya'), 'Draft', '2026-09-15', (SELECT id FROM users WHERE email = 'warehouse2@iom.test'));
SET @po_id = LAST_INSERT_ID();
INSERT INTO purchase_order_items (purchase_order_id, product_id, qty, buy_price) VALUES
    (@po_id, (SELECT id FROM products WHERE sku = 'PKK-001'), 8, 250000);

INSERT INTO purchase_orders (supplier_id, warehouse_id, status, order_date, created_by) VALUES
    ((SELECT id FROM suppliers WHERE name = 'PT Grosir Alat Tulis Nusantara'), (SELECT id FROM warehouses WHERE name = 'Gudang Pusat Jakarta'), 'Draft', '2026-09-16', (SELECT id FROM users WHERE email = 'admin@iom.test'));
SET @po_id = LAST_INSERT_ID();
INSERT INTO purchase_order_items (purchase_order_id, product_id, qty, buy_price) VALUES
    (@po_id, (SELECT id FROM products WHERE sku = 'ATK-002'), 20, 42000);

-- --- Purchase Order 4-7: Ordered (sudah diajukan ke supplier, belum ada barang datang) ---

INSERT INTO purchase_orders (supplier_id, warehouse_id, status, order_date, created_by) VALUES
    ((SELECT id FROM suppliers WHERE name = 'CV Sumber Makmur Elektronik'), (SELECT id FROM warehouses WHERE name = 'Gudang Cabang Surabaya'), 'Ordered', '2026-09-05', (SELECT id FROM users WHERE email = 'warehouse2@iom.test'));
SET @po_id = LAST_INSERT_ID();
INSERT INTO purchase_order_items (purchase_order_id, product_id, qty, buy_price) VALUES
    (@po_id, (SELECT id FROM products WHERE sku = 'ELK-001'), 15, 25000);

INSERT INTO purchase_orders (supplier_id, warehouse_id, status, order_date, created_by) VALUES
    ((SELECT id FROM suppliers WHERE name = 'CV Mitra Perkakas Jaya'), (SELECT id FROM warehouses WHERE name = 'Gudang Pusat Jakarta'), 'Ordered', '2026-09-06', (SELECT id FROM users WHERE email = 'admin@iom.test'));
SET @po_id = LAST_INSERT_ID();
INSERT INTO purchase_order_items (purchase_order_id, product_id, qty, buy_price) VALUES
    (@po_id, (SELECT id FROM products WHERE sku = 'FRN-002'), 10, 90000);

INSERT INTO purchase_orders (supplier_id, warehouse_id, status, order_date, created_by) VALUES
    ((SELECT id FROM suppliers WHERE name = 'UD Sumber Pangan Sejahtera'), (SELECT id FROM warehouses WHERE name = 'Gudang Cabang Surabaya'), 'Ordered', '2026-09-07', (SELECT id FROM users WHERE email = 'warehouse2@iom.test'));
SET @po_id = LAST_INSERT_ID();
INSERT INTO purchase_order_items (purchase_order_id, product_id, qty, buy_price) VALUES
    (@po_id, (SELECT id FROM products WHERE sku = 'PTP-002'), 15, 45000);

INSERT INTO purchase_orders (supplier_id, warehouse_id, status, order_date, created_by) VALUES
    ((SELECT id FROM suppliers WHERE name = 'PT Grosir Alat Tulis Nusantara'), (SELECT id FROM warehouses WHERE name = 'Gudang Pusat Jakarta'), 'Ordered', '2026-09-08', (SELECT id FROM users WHERE email = 'admin@iom.test'));
SET @po_id = LAST_INSERT_ID();
INSERT INTO purchase_order_items (purchase_order_id, product_id, qty, buy_price) VALUES
    (@po_id, (SELECT id FROM products WHERE sku = 'BAP-001'), 20, 22000);

-- --- Purchase Order 8-10: PartiallyReceived (sebagian barang sudah datang) ---

INSERT INTO purchase_orders (supplier_id, warehouse_id, status, order_date, created_by) VALUES
    ((SELECT id FROM suppliers WHERE name = 'CV Sumber Makmur Elektronik'), (SELECT id FROM warehouses WHERE name = 'Gudang Pusat Jakarta'), 'PartiallyReceived', '2026-08-20', (SELECT id FROM users WHERE email = 'warehouse1@iom.test'));
SET @po_id = LAST_INSERT_ID();
INSERT INTO purchase_order_items (purchase_order_id, product_id, qty, buy_price, received_qty) VALUES
    (@po_id, (SELECT id FROM products WHERE sku = 'ELK-002'), 20, 45000, 10);
INSERT INTO stock_ledger (product_id, warehouse_id, movement_type, quantity, reference_type, reference_id, performed_by, created_at) VALUES
    ((SELECT id FROM products WHERE sku = 'ELK-002'), (SELECT id FROM warehouses WHERE name = 'Gudang Pusat Jakarta'), 'Receipt', 10, 'purchase_order', @po_id, (SELECT id FROM users WHERE email = 'warehouse1@iom.test'), '2026-08-25 09:00:00');
INSERT INTO product_stock (product_id, warehouse_id, quantity) VALUES
    ((SELECT id FROM products WHERE sku = 'ELK-002'), (SELECT id FROM warehouses WHERE name = 'Gudang Pusat Jakarta'), 10)
    ON DUPLICATE KEY UPDATE quantity = quantity + VALUES(quantity);

INSERT INTO purchase_orders (supplier_id, warehouse_id, status, order_date, created_by) VALUES
    ((SELECT id FROM suppliers WHERE name = 'CV Mitra Perkakas Jaya'), (SELECT id FROM warehouses WHERE name = 'Gudang Cabang Surabaya'), 'PartiallyReceived', '2026-08-22', (SELECT id FROM users WHERE email = 'warehouse2@iom.test'));
SET @po_id = LAST_INSERT_ID();
INSERT INTO purchase_order_items (purchase_order_id, product_id, qty, buy_price, received_qty) VALUES
    (@po_id, (SELECT id FROM products WHERE sku = 'FRN-001'), 10, 150000, 4);
INSERT INTO stock_ledger (product_id, warehouse_id, movement_type, quantity, reference_type, reference_id, performed_by, created_at) VALUES
    ((SELECT id FROM products WHERE sku = 'FRN-001'), (SELECT id FROM warehouses WHERE name = 'Gudang Cabang Surabaya'), 'Receipt', 4, 'purchase_order', @po_id, (SELECT id FROM users WHERE email = 'warehouse2@iom.test'), '2026-08-27 09:00:00');
INSERT INTO product_stock (product_id, warehouse_id, quantity) VALUES
    ((SELECT id FROM products WHERE sku = 'FRN-001'), (SELECT id FROM warehouses WHERE name = 'Gudang Cabang Surabaya'), 4)
    ON DUPLICATE KEY UPDATE quantity = quantity + VALUES(quantity);

INSERT INTO purchase_orders (supplier_id, warehouse_id, status, order_date, created_by) VALUES
    ((SELECT id FROM suppliers WHERE name = 'CV Mitra Perkakas Jaya'), (SELECT id FROM warehouses WHERE name = 'Gudang Pusat Jakarta'), 'PartiallyReceived', '2026-08-25', (SELECT id FROM users WHERE email = 'warehouse1@iom.test'));
SET @po_id = LAST_INSERT_ID();
INSERT INTO purchase_order_items (purchase_order_id, product_id, qty, buy_price, received_qty) VALUES
    (@po_id, (SELECT id FROM products WHERE sku = 'PKK-001'), 12, 250000, 5);
INSERT INTO stock_ledger (product_id, warehouse_id, movement_type, quantity, reference_type, reference_id, performed_by, created_at) VALUES
    ((SELECT id FROM products WHERE sku = 'PKK-001'), (SELECT id FROM warehouses WHERE name = 'Gudang Pusat Jakarta'), 'Receipt', 5, 'purchase_order', @po_id, (SELECT id FROM users WHERE email = 'warehouse1@iom.test'), '2026-08-29 09:00:00');
INSERT INTO product_stock (product_id, warehouse_id, quantity) VALUES
    ((SELECT id FROM products WHERE sku = 'PKK-001'), (SELECT id FROM warehouses WHERE name = 'Gudang Pusat Jakarta'), 5)
    ON DUPLICATE KEY UPDATE quantity = quantity + VALUES(quantity);

-- --- Purchase Order 11-14: Received (seluruh barang sudah diterima) ---

INSERT INTO purchase_orders (supplier_id, warehouse_id, status, order_date, created_by) VALUES
    ((SELECT id FROM suppliers WHERE name = 'CV Sumber Makmur Elektronik'), (SELECT id FROM warehouses WHERE name = 'Gudang Cabang Surabaya'), 'Received', '2026-08-01', (SELECT id FROM users WHERE email = 'warehouse2@iom.test'));
SET @po_id = LAST_INSERT_ID();
INSERT INTO purchase_order_items (purchase_order_id, product_id, qty, buy_price, received_qty) VALUES
    (@po_id, (SELECT id FROM products WHERE sku = 'ELK-002'), 15, 45000, 15);
INSERT INTO stock_ledger (product_id, warehouse_id, movement_type, quantity, reference_type, reference_id, performed_by, created_at) VALUES
    ((SELECT id FROM products WHERE sku = 'ELK-002'), (SELECT id FROM warehouses WHERE name = 'Gudang Cabang Surabaya'), 'Receipt', 15, 'purchase_order', @po_id, (SELECT id FROM users WHERE email = 'warehouse2@iom.test'), '2026-08-06 09:00:00');
INSERT INTO product_stock (product_id, warehouse_id, quantity) VALUES
    ((SELECT id FROM products WHERE sku = 'ELK-002'), (SELECT id FROM warehouses WHERE name = 'Gudang Cabang Surabaya'), 15)
    ON DUPLICATE KEY UPDATE quantity = quantity + VALUES(quantity);

INSERT INTO purchase_orders (supplier_id, warehouse_id, status, order_date, created_by) VALUES
    ((SELECT id FROM suppliers WHERE name = 'CV Mitra Perkakas Jaya'), (SELECT id FROM warehouses WHERE name = 'Gudang Pusat Jakarta'), 'Received', '2026-08-03', (SELECT id FROM users WHERE email = 'warehouse1@iom.test'));
SET @po_id = LAST_INSERT_ID();
INSERT INTO purchase_order_items (purchase_order_id, product_id, qty, buy_price, received_qty) VALUES
    (@po_id, (SELECT id FROM products WHERE sku = 'OTM-002'), 10, 180000, 10);
INSERT INTO stock_ledger (product_id, warehouse_id, movement_type, quantity, reference_type, reference_id, performed_by, created_at) VALUES
    ((SELECT id FROM products WHERE sku = 'OTM-002'), (SELECT id FROM warehouses WHERE name = 'Gudang Pusat Jakarta'), 'Receipt', 10, 'purchase_order', @po_id, (SELECT id FROM users WHERE email = 'warehouse1@iom.test'), '2026-08-08 09:00:00');
INSERT INTO product_stock (product_id, warehouse_id, quantity) VALUES
    ((SELECT id FROM products WHERE sku = 'OTM-002'), (SELECT id FROM warehouses WHERE name = 'Gudang Pusat Jakarta'), 10)
    ON DUPLICATE KEY UPDATE quantity = quantity + VALUES(quantity);

INSERT INTO purchase_orders (supplier_id, warehouse_id, status, order_date, created_by) VALUES
    ((SELECT id FROM suppliers WHERE name = 'CV Mitra Perkakas Jaya'), (SELECT id FROM warehouses WHERE name = 'Gudang Cabang Surabaya'), 'Received', '2026-08-05', (SELECT id FROM users WHERE email = 'warehouse2@iom.test'));
SET @po_id = LAST_INSERT_ID();
INSERT INTO purchase_order_items (purchase_order_id, product_id, qty, buy_price, received_qty) VALUES
    (@po_id, (SELECT id FROM products WHERE sku = 'MHB-002'), 12, 120000, 12);
INSERT INTO stock_ledger (product_id, warehouse_id, movement_type, quantity, reference_type, reference_id, performed_by, created_at) VALUES
    ((SELECT id FROM products WHERE sku = 'MHB-002'), (SELECT id FROM warehouses WHERE name = 'Gudang Cabang Surabaya'), 'Receipt', 12, 'purchase_order', @po_id, (SELECT id FROM users WHERE email = 'warehouse2@iom.test'), '2026-08-10 09:00:00');
INSERT INTO product_stock (product_id, warehouse_id, quantity) VALUES
    ((SELECT id FROM products WHERE sku = 'MHB-002'), (SELECT id FROM warehouses WHERE name = 'Gudang Cabang Surabaya'), 12)
    ON DUPLICATE KEY UPDATE quantity = quantity + VALUES(quantity);

INSERT INTO purchase_orders (supplier_id, warehouse_id, status, order_date, created_by) VALUES
    ((SELECT id FROM suppliers WHERE name = 'CV Mitra Perkakas Jaya'), (SELECT id FROM warehouses WHERE name = 'Gudang Pusat Jakarta'), 'Received', '2026-08-07', (SELECT id FROM users WHERE email = 'warehouse1@iom.test'));
SET @po_id = LAST_INSERT_ID();
INSERT INTO purchase_order_items (purchase_order_id, product_id, qty, buy_price, received_qty) VALUES
    (@po_id, (SELECT id FROM products WHERE sku = 'BAP-002'), 15, 65000, 15);
INSERT INTO stock_ledger (product_id, warehouse_id, movement_type, quantity, reference_type, reference_id, performed_by, created_at) VALUES
    ((SELECT id FROM products WHERE sku = 'BAP-002'), (SELECT id FROM warehouses WHERE name = 'Gudang Pusat Jakarta'), 'Receipt', 15, 'purchase_order', @po_id, (SELECT id FROM users WHERE email = 'warehouse1@iom.test'), '2026-08-12 09:00:00');
INSERT INTO product_stock (product_id, warehouse_id, quantity) VALUES
    ((SELECT id FROM products WHERE sku = 'BAP-002'), (SELECT id FROM warehouses WHERE name = 'Gudang Pusat Jakarta'), 15)
    ON DUPLICATE KEY UPDATE quantity = quantity + VALUES(quantity);

-- --- Purchase Order 15: Cancelled ---

INSERT INTO purchase_orders (supplier_id, warehouse_id, status, order_date, created_by) VALUES
    ((SELECT id FROM suppliers WHERE name = 'UD Sumber Pangan Sejahtera'), (SELECT id FROM warehouses WHERE name = 'Gudang Cabang Surabaya'), 'Cancelled', '2026-09-01', (SELECT id FROM users WHERE email = 'admin@iom.test'));
SET @po_id = LAST_INSERT_ID();
INSERT INTO purchase_order_items (purchase_order_id, product_id, qty, buy_price) VALUES
    (@po_id, (SELECT id FROM products WHERE sku = 'PTP-001'), 20, 12000);

-- --- Sales Order 1-2: Draft (belum diajukan, belum menyentuh stok) ---

INSERT INTO sales_orders (customer_id, warehouse_id, status, created_by, created_at) VALUES
    ((SELECT id FROM customers WHERE name = 'Toko Berkah Jaya'), (SELECT id FROM warehouses WHERE name = 'Gudang Pusat Jakarta'), 'Draft', (SELECT id FROM users WHERE email = 'sales1@iom.test'), '2026-09-14 10:00:00');
SET @so_id = LAST_INSERT_ID();
INSERT INTO sales_order_items (sales_order_id, product_id, qty, sell_price) VALUES
    (@so_id, (SELECT id FROM products WHERE sku = 'KSC-001'), 10, 15000);

INSERT INTO sales_orders (customer_id, warehouse_id, status, created_by, created_at) VALUES
    ((SELECT id FROM customers WHERE name = 'CV Anugerah Sejahtera'), (SELECT id FROM warehouses WHERE name = 'Gudang Cabang Surabaya'), 'Draft', (SELECT id FROM users WHERE email = 'sales2@iom.test'), '2026-09-15 10:00:00');
SET @so_id = LAST_INSERT_ID();
INSERT INTO sales_order_items (sales_order_id, product_id, qty, sell_price) VALUES
    (@so_id, (SELECT id FROM products WHERE sku = 'PTP-001'), 15, 20000);

-- --- Sales Order 3-4: PendingApproval (sudah diajukan, menunggu Admin) ---

INSERT INTO sales_orders (customer_id, warehouse_id, status, created_by, created_at) VALUES
    ((SELECT id FROM customers WHERE name = 'Toko Makmur Abadi'), (SELECT id FROM warehouses WHERE name = 'Gudang Pusat Jakarta'), 'PendingApproval', (SELECT id FROM users WHERE email = 'sales1@iom.test'), '2026-09-12 11:00:00');
SET @so_id = LAST_INSERT_ID();
INSERT INTO sales_order_items (sales_order_id, product_id, qty, sell_price) VALUES
    (@so_id, (SELECT id FROM products WHERE sku = 'BYA-001'), 10, 95000);

INSERT INTO sales_orders (customer_id, warehouse_id, status, created_by, created_at) VALUES
    ((SELECT id FROM customers WHERE name = 'PT Cahaya Nusantara Retail'), (SELECT id FROM warehouses WHERE name = 'Gudang Cabang Surabaya'), 'PendingApproval', (SELECT id FROM users WHERE email = 'sales2@iom.test'), '2026-09-13 11:00:00');
SET @so_id = LAST_INSERT_ID();
INSERT INTO sales_order_items (sales_order_id, product_id, qty, sell_price) VALUES
    (@so_id, (SELECT id FROM products WHERE sku = 'MMN-001'), 20, 32000);

-- --- Sales Order 5-6: Approved (sudah disetujui Admin, menunggu goods issue) ---

INSERT INTO sales_orders (customer_id, warehouse_id, status, created_by, approved_by, created_at) VALUES
    ((SELECT id FROM customers WHERE name = 'Toko Berkah Jaya'), (SELECT id FROM warehouses WHERE name = 'Gudang Pusat Jakarta'), 'Approved', (SELECT id FROM users WHERE email = 'sales1@iom.test'), (SELECT id FROM users WHERE email = 'admin@iom.test'), '2026-09-08 09:30:00');
SET @so_id = LAST_INSERT_ID();
INSERT INTO sales_order_items (sales_order_id, product_id, qty, sell_price) VALUES
    (@so_id, (SELECT id FROM products WHERE sku = 'ATK-001'), 15, 28000);

INSERT INTO sales_orders (customer_id, warehouse_id, status, created_by, approved_by, created_at) VALUES
    ((SELECT id FROM customers WHERE name = 'CV Anugerah Sejahtera'), (SELECT id FROM warehouses WHERE name = 'Gudang Cabang Surabaya'), 'Approved', (SELECT id FROM users WHERE email = 'sales2@iom.test'), (SELECT id FROM users WHERE email = 'admin@iom.test'), '2026-09-09 09:30:00');
SET @so_id = LAST_INSERT_ID();
INSERT INTO sales_order_items (sales_order_id, product_id, qty, sell_price) VALUES
    (@so_id, (SELECT id FROM products WHERE sku = 'PDP-002'), 10, 40000);

-- --- Sales Order 7-9: Fulfilled (goods issue sudah diproses, stok berkurang) ---

INSERT INTO sales_orders (customer_id, warehouse_id, status, created_by, approved_by, created_at) VALUES
    ((SELECT id FROM customers WHERE name = 'Toko Makmur Abadi'), (SELECT id FROM warehouses WHERE name = 'Gudang Pusat Jakarta'), 'Fulfilled', (SELECT id FROM users WHERE email = 'sales1@iom.test'), (SELECT id FROM users WHERE email = 'admin@iom.test'), '2026-08-15 09:00:00');
SET @so_id = LAST_INSERT_ID();
INSERT INTO sales_order_items (sales_order_id, product_id, qty, sell_price) VALUES
    (@so_id, (SELECT id FROM products WHERE sku = 'OOR-001'), 15, 75000);
INSERT INTO stock_ledger (product_id, warehouse_id, movement_type, quantity, reference_type, reference_id, performed_by, created_at) VALUES
    ((SELECT id FROM products WHERE sku = 'OOR-001'), (SELECT id FROM warehouses WHERE name = 'Gudang Pusat Jakarta'), 'Issue', 15, 'sales_order', @so_id, (SELECT id FROM users WHERE email = 'warehouse1@iom.test'), '2026-08-16 14:00:00');
UPDATE product_stock SET quantity = quantity - 15
    WHERE product_id = (SELECT id FROM products WHERE sku = 'OOR-001') AND warehouse_id = (SELECT id FROM warehouses WHERE name = 'Gudang Pusat Jakarta');

INSERT INTO sales_orders (customer_id, warehouse_id, status, created_by, approved_by, created_at) VALUES
    ((SELECT id FROM customers WHERE name = 'PT Cahaya Nusantara Retail'), (SELECT id FROM warehouses WHERE name = 'Gudang Pusat Jakarta'), 'Fulfilled', (SELECT id FROM users WHERE email = 'sales2@iom.test'), (SELECT id FROM users WHERE email = 'admin@iom.test'), '2026-08-18 09:00:00');
SET @so_id = LAST_INSERT_ID();
INSERT INTO sales_order_items (sales_order_id, product_id, qty, sell_price) VALUES
    (@so_id, (SELECT id FROM products WHERE sku = 'PKK-002'), 15, 55000);
INSERT INTO stock_ledger (product_id, warehouse_id, movement_type, quantity, reference_type, reference_id, performed_by, created_at) VALUES
    ((SELECT id FROM products WHERE sku = 'PKK-002'), (SELECT id FROM warehouses WHERE name = 'Gudang Pusat Jakarta'), 'Issue', 15, 'sales_order', @so_id, (SELECT id FROM users WHERE email = 'warehouse1@iom.test'), '2026-08-19 14:00:00');
UPDATE product_stock SET quantity = quantity - 15
    WHERE product_id = (SELECT id FROM products WHERE sku = 'PKK-002') AND warehouse_id = (SELECT id FROM warehouses WHERE name = 'Gudang Pusat Jakarta');

INSERT INTO sales_orders (customer_id, warehouse_id, status, created_by, approved_by, created_at) VALUES
    ((SELECT id FROM customers WHERE name = 'Toko Berkah Jaya'), (SELECT id FROM warehouses WHERE name = 'Gudang Cabang Surabaya'), 'Fulfilled', (SELECT id FROM users WHERE email = 'sales1@iom.test'), (SELECT id FROM users WHERE email = 'admin@iom.test'), '2026-08-20 09:00:00');
SET @so_id = LAST_INSERT_ID();
INSERT INTO sales_order_items (sales_order_id, product_id, qty, sell_price) VALUES
    (@so_id, (SELECT id FROM products WHERE sku = 'PDP-001'), 8, 195000);
INSERT INTO stock_ledger (product_id, warehouse_id, movement_type, quantity, reference_type, reference_id, performed_by, created_at) VALUES
    ((SELECT id FROM products WHERE sku = 'PDP-001'), (SELECT id FROM warehouses WHERE name = 'Gudang Cabang Surabaya'), 'Issue', 8, 'sales_order', @so_id, (SELECT id FROM users WHERE email = 'warehouse2@iom.test'), '2026-08-21 14:00:00');
UPDATE product_stock SET quantity = quantity - 8
    WHERE product_id = (SELECT id FROM products WHERE sku = 'PDP-001') AND warehouse_id = (SELECT id FROM warehouses WHERE name = 'Gudang Cabang Surabaya');

-- --- Sales Order 10: Cancelled ---

INSERT INTO sales_orders (customer_id, warehouse_id, status, created_by, created_at) VALUES
    ((SELECT id FROM customers WHERE name = 'CV Anugerah Sejahtera'), (SELECT id FROM warehouses WHERE name = 'Gudang Pusat Jakarta'), 'Cancelled', (SELECT id FROM users WHERE email = 'sales2@iom.test'), '2026-09-01 10:00:00');
SET @so_id = LAST_INSERT_ID();
INSERT INTO sales_order_items (sales_order_id, product_id, qty, sell_price) VALUES
    (@so_id, (SELECT id FROM products WHERE sku = 'BAP-001'), 10, 35000);

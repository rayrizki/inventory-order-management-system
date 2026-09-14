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

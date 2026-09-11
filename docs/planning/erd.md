# ERD (DB-01)

Mengikuti entity di [class-diagram-initial.md](class-diagram-initial.md). Skema fisik lengkap ada di `database/schema-and-seed.sql`; diagram ini versi visualnya.

```mermaid
erDiagram
    USERS ||--o{ PURCHASE_ORDERS : "created_by"
    USERS ||--o{ SALES_ORDERS : "created_by / approved_by"
    USERS ||--o{ STOCK_LEDGER : "performed_by"

    CATEGORIES ||--o{ PRODUCTS : "category_id"
    PRODUCTS ||--o{ PRODUCT_STOCK : "product_id"
    WAREHOUSES ||--o{ PRODUCT_STOCK : "warehouse_id"

    SUPPLIERS ||--o{ PURCHASE_ORDERS : "supplier_id"
    WAREHOUSES ||--o{ PURCHASE_ORDERS : "warehouse_id"
    PURCHASE_ORDERS ||--o{ PURCHASE_ORDER_ITEMS : "purchase_order_id"
    PRODUCTS ||--o{ PURCHASE_ORDER_ITEMS : "product_id"

    CUSTOMERS ||--o{ SALES_ORDERS : "customer_id"
    WAREHOUSES ||--o{ SALES_ORDERS : "warehouse_id"
    SALES_ORDERS ||--o{ SALES_ORDER_ITEMS : "sales_order_id"
    PRODUCTS ||--o{ SALES_ORDER_ITEMS : "product_id"

    PRODUCTS ||--o{ STOCK_LEDGER : "product_id"
    WAREHOUSES ||--o{ STOCK_LEDGER : "warehouse_id"

    USERS {
        int id PK
        varchar name
        varchar email UK
        varchar password_hash
        enum role
        tinyint is_active
    }
    CATEGORIES {
        int id PK
        varchar name
    }
    WAREHOUSES {
        int id PK
        varchar name
        tinyint is_active
    }
    PRODUCTS {
        int id PK
        varchar sku UK
        int category_id FK
        decimal buy_price
        decimal sell_price
        int reorder_point
    }
    PRODUCT_STOCK {
        int product_id PK_FK
        int warehouse_id PK_FK
        int quantity "CHECK >= 0"
    }
    SUPPLIERS {
        int id PK
        varchar name
    }
    CUSTOMERS {
        int id PK
        varchar name
    }
    PURCHASE_ORDERS {
        int id PK
        int supplier_id FK
        int warehouse_id FK
        int created_by FK
        enum status
    }
    PURCHASE_ORDER_ITEMS {
        int id PK
        int purchase_order_id FK
        int product_id FK
        int qty
        int received_qty
    }
    SALES_ORDERS {
        int id PK
        int customer_id FK
        int warehouse_id FK
        int created_by FK
        int approved_by FK
        enum status
    }
    SALES_ORDER_ITEMS {
        int id PK
        int sales_order_id FK
        int product_id FK
        int qty
    }
    STOCK_LEDGER {
        bigint id PK
        int product_id FK
        int warehouse_id FK
        enum movement_type
        int quantity
        varchar reference_type
        int reference_id
        int performed_by FK
    }
```

## Catatan desain (dari modul SQL)

- `product_stock` pakai composite primary key `(product_id, warehouse_id)` - satu baris per kombinasi produk+gudang, bukan `id` auto-increment sendiri, karena kombinasi itu memang unik secara alami.
- `stock_ledger` punya index composite `(product_id, warehouse_id, created_at)` - kolom equality (`product_id`, `warehouse_id`) di depan, kolom range/sort (`created_at`) di belakang, mengikuti kaidah *left-prefix* supaya laporan pergerakan stok per produk+gudang+rentang tanggal (REPORT-01) bisa pakai index ini secara efisien.
- `quantity >= 0` di-enforce lewat `CHECK` constraint di level database, bukan cuma divalidasi di PHP - lapisan pertahanan tambahan untuk ARCH-02.
- Stok tidak pernah diubah tanpa baris `stock_ledger` yang menyertainya (ditegakkan di application layer lewat transaksi, bukan trigger - lihat `docs/quality/tech-debt.md` dan alasan di modul SQL Bab 9 soal risiko trigger untuk audit log).

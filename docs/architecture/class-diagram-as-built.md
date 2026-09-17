# Class Diagram - As-Built (DESIGN-01)

Dibuat setelah slice Auth (AUTH-01/AUTH-02) dan empat slice pertama Master
Data (Kategori, Gudang, Supplier, Customer) stabil. Hanya memuat kelas yang
**benar-benar ada di kode** saat ini - modul yang belum dikerjakan (Produk,
Purchase Order, Sales Order, Stock Ledger, Dashboard, Laporan) sengaja tidak
digambar di sini supaya diagram ini tidak berbohong soal apa yang sudah
selesai; diagram initial (`docs/planning/class-diagram-initial.md`) tetap
jadi acuan rencana untuk modul-modul itu.

**Cara baca penanda dependency** (wajib DESIGN-01: dependency ke interface vs ke kelas konkret harus beda tanda):
- `X ..|> Y` (panah putus-putus, kepala berongga) = **realisasi interface** - `Y` adalah interface, `X` salah satu implementasinya.
- `X --> Y : constructor injection (interface)` = `X` bergantung ke **abstraksi** `Y` (bisa diganti implementasinya tanpa mengubah `X`).
- `X --> Y : constructor injection (concrete)` = `X` bergantung langsung ke **kelas konkret** `Y` (belum/tidak perlu diabstraksi - cuma ada satu kemungkinan implementasi).
- `X ..> Y : throws` / `X ..> Y : catches` = dependency ke exception, bukan constructor injection.

## Diagram A - Cross-cutting: Session & Routing

```mermaid
classDiagram
    direction LR

    class SessionInterface {
        <<interface>>
        +get(string key) mixed
        +set(string key, mixed value) void
        +remove(string key) void
        +destroy() void
        +regenerateId() void
    }

    class PhpSessionAdapter {
        +get(string key) mixed
        +set(string key, mixed value) void
        +remove(string key) void
        +destroy() void
        +regenerateId() void
    }

    class Role {
        <<enumeration>>
        Admin
        Sales
        WarehouseStaff
    }

    class CurrentUser {
        +int id
        +string name
        +Role role
    }

    class AuthGuard {
        -SessionInterface session
        +requireLogin() CurrentUser
        +requireRole(CurrentUser user, Role[] allowed) void
    }

    class Router {
        -array routes
        -CsrfToken csrf
        +get(string pattern, callable handler) void
        +post(string pattern, callable handler) void
        +dispatch(string method, string path) void
    }

    class CsrfToken {
        -SessionInterface session
        +get() string
        +isValid(string? submitted) bool
    }

    SessionInterface <|.. PhpSessionAdapter : implements
    AuthGuard --> SessionInterface : constructor injection (interface)
    AuthGuard --> CurrentUser : creates
    CurrentUser --> Role
    Router --> CsrfToken : constructor injection (concrete)
    CsrfToken --> SessionInterface : constructor injection (interface)

    note for CurrentUser "BEDA dari initial: ada properti `name`\n(initial cuma id+role) - dibutuhkan\nsupaya sidebar bisa menampilkan\nnama user yang login, bukan cuma role"
    note for Router "TIDAK ADA di diagram initial sama sekali.\nDitambahkan karena PHP tidak ada routing\nbawaan dan Category/Warehouse/Product\nsemua butuh URL /resource/{id}/edit -\nregex sederhana, tanpa dependency."
    note for CsrfToken "TIDAK ADA di diagram initial (initial dibuat\nsebelum CSRF disadari sebagai celah - lihat\ndocs/quality/tech-debt.md #4). Dicek satu kali\ndi Router::dispatch() untuk semua route POST,\nbukan diulang manual di tiap Controller."
```

## Diagram B - Auth (AUTH-01, AUTH-02)

```mermaid
classDiagram
    direction LR

    class User {
        +int? id
        +string name
        +string email
        +string passwordHash
        +Role role
        +bool isActive
        +verifyPassword(string plain) bool
    }

    class UserRepositoryInterface {
        <<interface>>
        +findById(int id) User?
        +findByEmail(string email) User?
    }
    class MySqlUserRepository {
        -PDO pdo
    }

    class AuthService {
        -UserRepositoryInterface users
        +authenticate(string email, string password) User?
    }

    class AuthController {
        -AuthService authService
        -SessionInterface session
        +showLoginForm() void
        +login() void
        +logout() void
    }

    User --> Role
    UserRepositoryInterface <|.. MySqlUserRepository : implements
    AuthService --> UserRepositoryInterface : constructor injection (interface)
    AuthController --> AuthService : constructor injection (concrete)
    AuthController --> SessionInterface : constructor injection (interface)

    note for User "BEDA dari initial: tidak ada properti\ncreatedAt/updatedAt di Entity (kolomnya\nada di tabel `users`, tapi belum ada\nalur yang butuh baca nilainya di PHP -\nYAGNI, sama alasannya dengan\nUserRepositoryInterface di ADR-0001)"
    note for UserRepositoryInterface "Sesuai ADR-0001: sengaja BELUM\npunya save()/listPaginated() -\nditunda sampai USR-01 (manajemen\nuser) benar-benar dikerjakan"
    note for AuthController "BEDA dari initial: method tidak menerima\nRequest / mengembalikan Response - initial\ndiagram mengasumsikan abstraksi itu, tapi\nkodenya baca $_POST/$_GET langsung dan\npanggil header()/require view langsung.\nBerlaku utk semua Controller di as-built ini,\nbukan cuma AuthController."
```

## Diagram C - Master Data: Kategori (PRD-01 sebagian - baru Category yang dibangun)

```mermaid
classDiagram
    direction LR

    class Category {
        +int? id
        +string name
        +string? description
    }

    class CategoryRepositoryInterface {
        <<interface>>
        +findById(int id) Category?
        +save(Category category) Category
        +listAll(string? search, int limit, int offset, string sortBy, string sortDir) Category[]
        +countAll(string? search) int
        +isInUse(int id) bool
        +delete(int id) void
    }
    class MySqlCategoryRepository {
        -PDO pdo
    }
    class InMemoryCategoryRepository {
        -Category[] categories
        -array inUseIds
    }

    class CategoryService {
        +const PER_PAGE = 10
        -CategoryRepositoryInterface categories
        +listCategories(string? search, int page, int perPage, string sortBy, string sortDir) Category[]
        +countCategories(string? search) int
        +getCategoryById(int id) Category
        +createCategory(string name, string? description) Category
        +updateCategory(int id, string name, string? description) Category
        +deleteCategory(int id) void
    }

    class CategoryController {
        +const ALLOWED_PER_PAGE
        +const ALLOWED_SORT_COLUMNS
        +const STATUS_MESSAGES
        -CategoryService categoryService
        -AuthGuard guard
        +index() void
        +showCreateForm() void
        +create() void
        +showEditForm(string id) void
        +update(string id) void
        +delete(string id) void
    }

    class NotFoundException {
        <<exception>>
    }
    class ValidationException {
        <<exception>>
        -array errors
        +errors() array
    }
    class ConflictException {
        <<exception>>
    }

    CategoryRepositoryInterface <|.. MySqlCategoryRepository : implements
    CategoryRepositoryInterface <|.. InMemoryCategoryRepository : implements
    CategoryService --> CategoryRepositoryInterface : constructor injection (interface)
    CategoryController --> CategoryService : constructor injection (concrete)
    CategoryController --> AuthGuard : constructor injection (concrete)
    CategoryService ..> NotFoundException : throws
    CategoryService ..> ValidationException : throws
    CategoryService ..> ConflictException : throws
    CategoryController ..> ConflictException : catches
    CategoryController ..> ValidationException : catches

    note for CategoryRepositoryInterface "BEDA besar dari initial: initial cuma\nsketsa findById/save (pola generik lewat\nProduct di Diagram 0). Method search/sort/\npagination/isInUse/delete ditambahkan karena\nKategori sengaja dijadikan template FIND-01\nlebih awal dari Produk (permintaan user),\ndan karena Kategori (beda dari Produk/\nSupplier/Customer) di-hard-delete, bukan\ndinonaktifkan - schema-nya memang tanpa\nkolom is_active."
    note for ConflictException "TIDAK ADA di diagram initial sama sekali.\nDitambahkan untuk kasus 'aksi ditolak\naturan bisnis' (kategori masih dipakai\nproduk) - beda dari ValidationException\n(kesalahan input form) dan NotFoundException\n(data tidak ada)."
```

## Diagram D - Master Data: Gudang (WH-01)

```mermaid
classDiagram
    direction LR

    class Warehouse {
        +int? id
        +string name
        +string? location
        +bool isActive
    }

    class WarehouseRepositoryInterface {
        <<interface>>
        +findById(int id) Warehouse?
        +save(Warehouse warehouse) Warehouse
        +listAll(string? search, bool? isActive, int limit, int offset, string sortBy, string sortDir) Warehouse[]
        +countAll(string? search, bool? isActive) int
        +setActive(int id, bool isActive) void
    }
    class MySqlWarehouseRepository {
        -PDO pdo
    }
    class InMemoryWarehouseRepository {
        -Warehouse[] warehouses
    }

    class WarehouseService {
        +const PER_PAGE = 10
        -WarehouseRepositoryInterface warehouses
        +listWarehouses(string? search, bool? isActive, int page, int perPage, string sortBy, string sortDir) Warehouse[]
        +countWarehouses(string? search, bool? isActive) int
        +getWarehouseById(int id) Warehouse
        +createWarehouse(string name, string? location) Warehouse
        +updateWarehouse(int id, string name, string? location) Warehouse
        +setActive(int id, bool isActive) void
    }

    class WarehouseController {
        +const ALLOWED_PER_PAGE
        +const ALLOWED_SORT_COLUMNS
        +const STATUS_FILTERS
        +const STATUS_MESSAGES
        -WarehouseService warehouseService
        -AuthGuard guard
        +index() void
        +showCreateForm() void
        +create() void
        +showEditForm(string id) void
        +update(string id) void
        +toggleActive(string id) void
    }

    WarehouseRepositoryInterface <|.. MySqlWarehouseRepository : implements
    WarehouseRepositoryInterface <|.. InMemoryWarehouseRepository : implements
    WarehouseService --> WarehouseRepositoryInterface : constructor injection (interface)
    WarehouseController --> WarehouseService : constructor injection (concrete)
    WarehouseController --> AuthGuard : constructor injection (concrete)
    WarehouseService ..> NotFoundException : throws
    WarehouseService ..> ValidationException : throws
    WarehouseController ..> ValidationException : catches

    note for WarehouseRepositoryInterface "BEDA sengaja dari CategoryRepositoryInterface\n(Diagram C): tidak ada isInUse()/delete() -\nada dimensi filter `isActive` di listAll/countAll\ndan method setActive() sebagai gantinya. Gudang\nmemang punya kolom is_active di schema (Kategori\ntidak) - lihat ADR-0004."
    note for WarehouseService "setActive() TIDAK melempar ConflictException\nseperti CategoryService::deleteCategory() -\ntoggle status tidak pernah berisiko merusak\nreferensi data lain (baris tetap ada), beda\ndari hard-delete yang butuh pengecekan pemakaian."
```

## Diagram E - Master Data: Supplier & Customer (§1.3, keduanya identik strukturnya)

Didemonstrasikan sekali pakai `Supplier` sebagai contoh, mengikuti gaya yang
sama dengan Diagram 0 di diagram initial. `Customer` (`CustomerEntity`,
`CustomerRepositoryInterface`, `MySqlCustomerRepository`,
`InMemoryCustomerRepository`, `CustomerService`, `CustomerController`)
adalah kelas-kelas terpisah dengan nama tabel (`customers`) dan pesan
Indonesia yang berbeda ("Customer" bukan "Supplier"), tapi bentuk method,
constant, dan relasinya identik satu-satu dengan yang digambar di sini -
tidak digambar ulang di sini supaya tidak duplikatif.

```mermaid
classDiagram
    direction LR

    class Supplier {
        +int? id
        +string name
        +string? contact
        +string? address
        +bool isActive
    }

    class SupplierRepositoryInterface {
        <<interface>>
        +findById(int id) Supplier?
        +save(Supplier supplier) Supplier
        +listAll(string? search, bool? isActive, int limit, int offset, string sortBy, string sortDir) Supplier[]
        +countAll(string? search, bool? isActive) int
        +setActive(int id, bool isActive) void
    }
    class MySqlSupplierRepository {
        -PDO pdo
    }
    class InMemorySupplierRepository {
        -Supplier[] suppliers
    }

    class SupplierService {
        +const PER_PAGE = 10
        -SupplierRepositoryInterface suppliers
        +listSuppliers(string? search, bool? isActive, int page, int perPage, string sortBy, string sortDir) Supplier[]
        +countSuppliers(string? search, bool? isActive) int
        +getSupplierById(int id) Supplier
        +createSupplier(string name, string? contact, string? address) Supplier
        +updateSupplier(int id, string name, string? contact, string? address) Supplier
        +setActive(int id, bool isActive) void
    }

    class SupplierController {
        +const ALLOWED_PER_PAGE
        +const ALLOWED_SORT_COLUMNS
        +const STATUS_FILTERS
        +const STATUS_MESSAGES
        -SupplierService supplierService
        -AuthGuard guard
        +index() void
        +showCreateForm() void
        +create() void
        +showEditForm(string id) void
        +update(string id) void
        +toggleActive(string id) void
    }

    SupplierRepositoryInterface <|.. MySqlSupplierRepository : implements
    SupplierRepositoryInterface <|.. InMemorySupplierRepository : implements
    SupplierService --> SupplierRepositoryInterface : constructor injection (interface)
    SupplierController --> SupplierService : constructor injection (concrete)
    SupplierController --> AuthGuard : constructor injection (concrete)
    SupplierService ..> NotFoundException : throws
    SupplierService ..> ValidationException : throws
    SupplierController ..> ValidationException : catches

    note for Supplier "Sama bentuknya dengan Warehouse (Diagram D):\nnonaktifkan bukan hapus, karena §1.3 eksplisit\nmenyebut Supplier (dan Customer) dinonaktifkan -\nlebih eksplisit daripada Warehouse yang cuma\ntersirat dari kolom is_active-nya."
    note for SupplierRepositoryInterface "Field tambahan `address` (dan search 3 kolom:\nname/contact/address) dibanding Warehouse yang\ncuma 2 kolom (name/location) - satu-satunya\nperbedaan struktural nyata dari Diagram D."
```

## Diagram F - Master Data: Produk & Stok (PRD-01 CRUD dasar, WH-01)

```mermaid
classDiagram
    direction LR

    class Product {
        +int? id
        +string sku
        +string name
        +int categoryId
        +string unit
        +float buyPrice
        +float sellPrice
        +int reorderPoint
        +string? imagePath
        +bool isActive
    }

    class ProductRepositoryInterface {
        <<interface>>
        +findById(int id) Product?
        +findBySku(string sku) Product?
        +save(Product product) Product
        +listAll(string? search, int? categoryId, bool? isActive, int limit, int offset, string sortBy, string sortDir) Product[]
        +countAll(string? search, int? categoryId, bool? isActive) int
        +setActive(int id, bool isActive) void
    }
    class MySqlProductRepository {
        -PDO pdo
    }
    class InMemoryProductRepository {
        -Product[] products
    }

    class ProductService {
        +const PER_PAGE = 10
        -ProductRepositoryInterface products
        -CategoryRepositoryInterface categories
        +listProducts(string? search, int? categoryId, bool? isActive, int page, int perPage, string sortBy, string sortDir) Product[]
        +countProducts(string? search, int? categoryId, bool? isActive) int
        +getProductById(int id) Product
        +createProduct(array input) Product
        +updateProduct(int id, array input) Product
        +setActive(int id, bool isActive) void
        -validate(array input, int? excludeId) array
    }

    class ProductController {
        +const CATEGORY_DROPDOWN_LIMIT
        +const ALLOWED_PER_PAGE
        +const ALLOWED_SORT_COLUMNS
        +const STATUS_FILTERS
        +const STATUS_MESSAGES
        -ProductService productService
        -CategoryService categoryService
        -StockService stockService
        -AuthGuard guard
        +index() void
        +show(string id) void
        +showCreateForm() void
        +create() void
        +showEditForm(string id) void
        +update(string id) void
        +toggleActive(string id) void
    }

    class ProductStock {
        +int productId
        +int warehouseId
        +int quantity
    }
    class ProductStockRepositoryInterface {
        <<interface>>
        +findByProduct(int productId) ProductStock[]
    }
    class MySqlProductStockRepository {
        -PDO pdo
    }
    class InMemoryProductStockRepository {
        -ProductStock[] rows
    }
    class StockService {
        +const WAREHOUSE_LIMIT
        -ProductStockRepositoryInterface stockRepository
        -WarehouseRepositoryInterface warehouseRepository
        +getStockSummary(int productId) array
    }

    ProductRepositoryInterface <|.. MySqlProductRepository : implements
    ProductRepositoryInterface <|.. InMemoryProductRepository : implements
    ProductService --> ProductRepositoryInterface : constructor injection (interface)
    ProductService --> CategoryRepositoryInterface : constructor injection (interface)
    ProductController --> ProductService : constructor injection (concrete)
    ProductController --> CategoryService : constructor injection (concrete)
    ProductController --> StockService : constructor injection (concrete)
    ProductController --> AuthGuard : constructor injection (concrete)
    ProductService ..> NotFoundException : throws
    ProductService ..> ValidationException : throws
    ProductController ..> ValidationException : catches

    ProductStockRepositoryInterface <|.. MySqlProductStockRepository : implements
    ProductStockRepositoryInterface <|.. InMemoryProductStockRepository : implements
    StockService --> ProductStockRepositoryInterface : constructor injection (interface)
    StockService --> WarehouseRepositoryInterface : constructor injection (interface)

    note for ProductService "BEDA dari semua Service Master Data sebelumnya:\nmenerima DUA repository interface lewat constructor,\nbukan satu. CategoryRepositoryInterface dipakai untuk\nmemvalidasi category_id benar-benar ada (FK) sebelum\nsimpan - bukan cuma format angka. validate() juga\nmengumpulkan SEMUA error field sekaligus ke satu array\n(pola baru - Service Master Data sebelumnya cuma\nvalidasi satu field 'name')."
    note for ProductStockRepositoryInterface "Baca-saja untuk saat ini (WH-01) - findByProduct()\nsaja, tidak ada save(). Baris product_stock nanti\nditulis StockService lewat alur goods receipt (PO-01)/\ngoods issue (SO-01) dalam satu transaksi bersama\nStockLedger (ARCH-02), bukan lewat repository ini\nsecara langsung - method tulis ditambahkan begitu\nPO/SO dikerjakan, bukan diprediksi sekarang (YAGNI)."
    note for StockService "getStockSummary() gabungkan seluruh gudang AKTIF\n(WarehouseRepositoryInterface::listAll) dengan baris\nproduct_stock yang ada (left-join di memori, bukan\nSQL) - gudang tanpa baris dianggap quantity 0. Karena\nPO-01 belum dibangun saat modul ini ditulis, seluruh\nproduk otomatis quantity 0 - membuktikan alur BACA\nbenar dulu, sebelum jalur TULIS (goods receipt) ada."
```

Catatan tambahan (di luar diagram): brief §2 eksplisit meminta upload gambar (`imagePath`) dan filter status stok (FIND-01) ditunda sampai "alur transaksi inti stabil" - `Product.imagePath` sudah ada di entity/schema tapi belum ada jalur upload/validasi file, dan `ProductController::index()` belum punya filter `status_stok`. Dicatat sebagai keterbatasan disengaja di `docs/quality/tech-debt.md` #5, bukan celah yang terlewat.

## Apa yang berubah dari initial ke as-built, dan kenapa

1. **`CurrentUser` bertambah properti `name`.** Initial hanya menyiapkan `id`+`role` untuk kebutuhan otorisasi (`requireRole()`); kebutuhan menampilkan *siapa* yang login (bukan cuma perannya) di sidebar baru muncul belakangan, jadi properti ini ditambah begitu use case-nya nyata - bukan diprediksi di awal.
2. **`Router` (class konkret baru) tidak pernah digambar di initial.** Diagram initial mengasumsikan Controller menerima objek `Request` generik tanpa menjelaskan bagaimana request itu sampai ke method yang tepat. Begitu coding dimulai, dibutuhkan mekanisme routing nyata (terutama untuk URL dengan parameter seperti `/categories/{id}/edit`) - ditambahkan sebagai kelas manual sederhana (regex, tanpa dependency), bukan lewat framework routing.
3. **Semua Controller ternyata tidak menerima `Request` atau mengembalikan `Response`.** Ini penyederhanaan yang baru terlihat perlu saat coding: PHP native sudah punya `$_GET`/`$_POST`/`header()` sebagai boundary HTTP, jadi menambah lapisan `Request`/`Response` buatan sendiri tidak menyelesaikan masalah nyata (melanggar semangat "jangan over-engineer" di brief) - Controller cukup baca superglobal langsung dan panggil `header()`/`require` view.
4. **`CategoryRepositoryInterface` jauh lebih kaya dari sketsa initial.** Initial hanya mencontohkan pola generik (findById/save/searchPaginated lewat `Product`). Begitu Kategori benar-benar dikerjakan, method search/sort/pagination (`listAll`, `countAll`) dan `isInUse`/`delete` ditambahkan - dua yang terakhir karena Kategori ternyata satu-satunya master data yang di-*hard delete* (bukan dinonaktifkan seperti Produk/Supplier/Customer di §1.3), sehingga butuh pengecekan pemakaian sebelum dihapus.
5. **`ConflictException` (exception baru) tidak ada di initial.** Diagram initial hanya menyiapkan `ValidationException` (kesalahan input) dan `NotFoundException` (data tidak ada). Kasus "aksi ditolak karena aturan bisnis" (kategori masih dipakai produk lain) adalah kondisi ketiga yang berbeda dari keduanya, jadi ditambahkan sebagai exception tersendiri mengikuti pola yang sama.
6. **`CsrfToken` (class baru) tidak ada di initial maupun di draf as-built pertama.** CSRF baru disadari sebagai celah nyata setelah slice Kategori selesai (tercatat di `docs/quality/tech-debt.md` #4) - ditambahkan sebagai dependency `Router`, bukan diperiksa manual di tiap Controller, supaya tidak ada endpoint POST yang lolos karena lupa ditambahkan satu per satu.
7. **`WarehouseRepositoryInterface` sengaja punya bentuk berbeda dari `CategoryRepositoryInterface`**, bukan cuma disalin lalu di-rename. Gudang punya kolom `is_active` di schema (Kategori tidak), jadi repository-nya punya dimensi filter status + `setActive()`, menggantikan `isInUse()`/`delete()` milik Kategori - lihat ADR-0004 untuk alasan lengkap kenapa dua entity Master Data yang polanya mirip ini justru sengaja dibuat tidak seragam.
8. **`Supplier`/`Customer` mengikuti pola `Warehouse`, bukan `Category`** - konsisten dengan poin 7, karena §1.3 eksplisit menyebut "Produk, supplier, dan customer dinonaktifkan, bukan dihapus permanen". Satu-satunya beda struktural dari `Warehouse`: ada field ketiga (`address`) dan search mencakup 3 kolom (name/contact/address) bukan 2 - tidak digambar sebagai diagram terpisah untuk Customer karena bentuknya identik satu-satu dengan Supplier (lihat Diagram E).
9. **`ProductService` (Diagram F) adalah Service Master Data pertama dengan dua dependency repository.** Semua Service sebelumnya (Category/Warehouse/Supplier/Customer) hanya menerima satu repository. Produk butuh `CategoryRepositoryInterface` tambahan untuk memvalidasi `category_id` sungguhan ada (FK) sebelum simpan - initial hanya mensketsa `Product` sebagai contoh pola generik (Diagram 0), tidak menunjukkan dependency silang ini karena validasi FK-nya baru terlihat perlu saat coding.
10. **`ProductStock`/`ProductStockRepositoryInterface`/`StockService` (semuanya baru) tidak digambar sama sekali di initial**, meski `ProductStock` ada di tabel field-minimum §1.3. Initial fokus ke CRUD Produk (PRD-01); kebutuhan WH-01 (tampilan stok per gudang) baru dibangun belakangan sebagai halaman detail Produk (VIEW-01) yang sekaligus jadi bukti alur baca sebelum PO-01 menulis ke tabel yang sama. `ProductStockRepositoryInterface` sengaja cuma `findByProduct()` (baca), bukan `save()` - method tulis ditunda ke goods receipt/issue (YAGNI, lihat catatan di Diagram F).
11. **Akses baca Produk (`index()`/`show()`) dilonggarkan dari Admin-only menjadi seluruh role yang login**, berbeda dari Kategori/Gudang/Supplier/Customer yang tetap Admin-only. Ini koreksi, bukan fitur baru: draf awal salah menyamaratakan seluruh grup sidebar "Master Data" (termasuk Produk) sebagai admin-only (tech-debt #2), padahal §1.2 eksplisit memberi Sales "hanya melihat katalog" dan Warehouse Staff "hanya melihat produk & stok" - keduanya butuh baca Produk untuk modul Sales Order/Purchase Order berikutnya. Mutasi (create/update/toggle-active) tetap `requireRole([Admin])` di server.

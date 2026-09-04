# Class Diagram - Initial (DESIGN-01)

Dibuat sebelum coding, sebagai acuan struktur Controller/Service/Repository/Entity dan relasinya. Tool: Mermaid (markdown, render otomatis di GitHub/VS Code).

Diagram dipecah per modul mengikuti urutan vertical slice di §2 project brief, supaya tetap terbaca. Modul lain mengikuti pola repository yang sama seperti didemonstrasikan pada Diagram 0 — tidak digambar ulang di tiap modul agar tidak duplikatif.

Catatan umum:
- Tipe seperti `Filter`, `ProductData`, `UserData`, `ReceiveItem`, `PaginatedResult` adalah placeholder untuk data masukan/keluaran (bisa jadi array asosiatif sederhana atau value object kecil) — bentuk final ditentukan saat coding, tidak dikunci di tahap desain ini.
- Mekanisme konkret pencegahan oversell pada `GoodsIssueService`/`ProductStockRepositoryInterface` (ARCH-02) belum difinalkan di sini — akan diputuskan dan dicatat sebagai ADR saat slice Sales Order dikerjakan.

## Diagram 0 - Pola Arsitektur & Cross-Cutting (ARCH-01)

Didemonstrasikan sekali pakai `Product` sebagai contoh. Repository lain (User, Warehouse, Category, Supplier, Customer, PurchaseOrder, SalesOrder, StockLedger, ProductStock) mengikuti pola identik: satu interface, implementasi `MySql*` untuk production/integration test, implementasi `InMemory*` untuk unit test.

```mermaid
classDiagram
    direction LR

    class ProductController {
        -ProductService productService
        +index(Request) Response
        +show(Request) Response
        +create(Request) Response
    }

    class ProductService {
        -ProductRepositoryInterface productRepository
        +searchProducts(Filter f) PaginatedResult
        +getProductById(int id) Product
        +createProduct(ProductData d) Product
    }

    class ProductRepositoryInterface {
        <<interface>>
        +findById(int id) Product
        +findBySku(string sku) Product
        +save(Product product) void
        +searchPaginated(Filter f) PaginatedResult
    }

    class MySqlProductRepository {
        -PDO pdo
    }

    class InMemoryProductRepository {
        -Product[] products
    }

    ProductController --> ProductService : constructor injection
    ProductService --> ProductRepositoryInterface : constructor injection
    ProductRepositoryInterface <|.. MySqlProductRepository : implements
    ProductRepositoryInterface <|.. InMemoryProductRepository : implements

    note for InMemoryProductRepository "Dipakai di tests/Unit\n(tanpa koneksi MySQL nyata)"
    note for MySqlProductRepository "Dipakai di production\n & tests/Integration"
```

Pemetaan method Controller -> Service (setiap action Controller harus punya pasangan jelas di Service, dan tidak ada method Service yang "nganggur" tanpa pemanggil — keduanya wajib 1:1 dalam satu diagram):
- `index()` -> `searchProducts(Filter f)` (filter kosong = list semua, dipaginasi)
- `show()` -> `getProductById(int id)`
- `create()` -> `createProduct(ProductData d)`

Diagram ini sengaja hanya memuat 3 action sebagai contoh pola, bukan CRUD `Product` lengkap. `update()` dan `deactivate()` (PRD-01) digambar penuh di Diagram 2 (Master Data) — tidak diulang di sini supaya tidak ada dua sumber kebenaran untuk class yang sama.

Cross-cutting: session tidak pernah diakses langsung oleh Service (ARCH-01), hanya oleh Controller lewat `AuthGuard`.

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
        ADMIN
        SALES
        WAREHOUSE_STAFF
    }

    class CurrentUser {
        +int id
        +Role role
    }

    class AuthGuard {
        -SessionInterface session
        +requireLogin() CurrentUser
        +requireRole(CurrentUser user, Role[] allowed) void
    }

    SessionInterface <|.. PhpSessionAdapter : implements
    AuthGuard --> SessionInterface : constructor injection
    AuthGuard --> CurrentUser : creates
    CurrentUser --> Role

    note for AuthGuard "Dipanggil di awal Controller action yang\nbutuh login/role tertentu.\nMelempar UnauthenticatedException / ForbiddenException\n-> dipetakan ke 401/403 (ERR-01)"
```

## Diagram 1 - Auth & User Management (AUTH-01, AUTH-02, USR-01)

```mermaid
classDiagram
    direction LR

    class User {
        +int id
        +string name
        +string email
        +string passwordHash
        +Role role
        +bool isActive
        +DateTime createdAt
        +DateTime updatedAt
        +verifyPassword(string plain) bool
    }

    class Role {
        <<enumeration>>
        ADMIN
        SALES
        WAREHOUSE_STAFF
    }

    class UserRepositoryInterface {
        <<interface>>
        +findById(int id) User
        +findByEmail(string email) User
        +save(User user) void
        +listPaginated(Filter f) PaginatedResult
    }
    class MySqlUserRepository
    class InMemoryUserRepository

    class AuthService {
        -UserRepositoryInterface users
        +authenticate(string email, string password) User
    }

    class UserService {
        -UserRepositoryInterface users
        +createUser(UserData d) User
        +updateUser(int id, UserData d) User
        +setActive(int id, bool active) void
        +listUsers(Filter f) PaginatedResult
    }

    class AuthController {
        -AuthService authService
        -SessionInterface session
        +login(Request) Response
        +logout(Request) Response
    }

    class UserController {
        -UserService userService
        -AuthGuard guard
        +index(Request) Response
        +create(Request) Response
        +update(Request) Response
        +toggleActive(Request) Response
    }

    User --> Role
    UserRepositoryInterface <|.. MySqlUserRepository
    UserRepositoryInterface <|.. InMemoryUserRepository
    AuthService --> UserRepositoryInterface
    UserService --> UserRepositoryInterface
    AuthController --> AuthService
    UserController --> UserService
    UserController --> AuthGuard
```

Catatan: `AuthService.authenticate()` murni logic (cek `isActive` + `password_verify`), tidak menyentuh session -> testable dengan `InMemoryUserRepository`. Penulisan session (login) dan penghapusan session (logout) dilakukan di `AuthController` lewat `SessionInterface`.

## Diagram 2 - Master Data (PRD-01, WH-01, API-01)

```mermaid
classDiagram
    direction LR

    class Category {
        +int id
        +string name
        +string description
    }

    class Warehouse {
        +int id
        +string name
        +string location
        +bool isActive
    }

    class Product {
        +int id
        +string sku
        +string name
        +int categoryId
        +string unit
        +float buyPrice
        +float sellPrice
        +int reorderPoint
        +string imagePath
        +bool isActive
        +isLowStock(int currentQty) bool
    }

    class ProductStock {
        +int productId
        +int warehouseId
        +int quantity
        +DateTime updatedAt
    }

    class Supplier {
        +int id
        +string name
        +string contact
        +string address
        +bool isActive
    }

    class Customer {
        +int id
        +string name
        +string contact
        +string address
        +bool isActive
    }

    Product "many" --> "1" Category
    ProductStock "many" --> "1" Product
    ProductStock "many" --> "1" Warehouse

    class ProductRepositoryInterface {
        <<interface>>
        +listBelowReorderPoint() Product[]
    }
    note for ProductRepositoryInterface "Method dasar (findById, findBySku,\nsave, searchPaginated) sudah\ndidefinisikan di Diagram 0,\ntidak diulang di sini"
    class ProductStockRepositoryInterface {
        <<interface>>
        +findByProductAndWarehouse(int productId, int warehouseId) ProductStock
        +listByProduct(int productId) ProductStock[]
        +incrementQuantity(int productId, int warehouseId, int qty) void
        +decrementQuantityIfSufficient(int productId, int warehouseId, int qty) bool
    }
    class WarehouseRepositoryInterface { <<interface>> }
    class SupplierRepositoryInterface { <<interface>> }
    class CustomerRepositoryInterface { <<interface>> }

    class ProductService {
        -ProductRepositoryInterface products
        -CategoryRepositoryInterface categories
        +createProduct(ProductData d) Product
        +updateProduct(int id, ProductData d) Product
        +deactivateProduct(int id) void
        +searchProducts(Filter f) PaginatedResult
        +getProductBySku(string sku) Product
    }
    class StockService {
        -ProductStockRepositoryInterface stocks
        +getStockPerWarehouse(int productId) ProductStock[]
        +getTotalStock(int productId) int
        +listLowStock() Product[]
    }
    class WarehouseService { -WarehouseRepositoryInterface warehouses }
    class SupplierService { -SupplierRepositoryInterface suppliers }
    class CustomerService { -CustomerRepositoryInterface customers }

    class ProductController {
        -ProductService productService
        -StockService stockService
        +index(Request) Response
        +show(Request) Response
        +create(Request) Response
        +update(Request) Response
        +deactivate(Request) Response
    }
    class ProductAvailabilityApiController {
        -ProductService productService
        -StockService stockService
        +availability(Request) JsonResponse
    }
    class WarehouseController
    class SupplierController
    class CustomerController

    ProductService --> ProductRepositoryInterface
    ProductService --> CategoryRepositoryInterface
    StockService --> ProductStockRepositoryInterface
    WarehouseService --> WarehouseRepositoryInterface
    SupplierService --> SupplierRepositoryInterface
    CustomerService --> CustomerRepositoryInterface

    ProductController --> ProductService
    ProductController --> StockService
    ProductAvailabilityApiController --> ProductService
    ProductAvailabilityApiController --> StockService
    WarehouseController --> WarehouseService
    SupplierController --> SupplierService
    CustomerController --> CustomerService
```

Catatan:
- `ProductAvailabilityApiController` adalah controller JSON API terpisah (API-01, `GET /api/products/{sku}/availability`), memakai `StockService` yang sama dengan halaman HTML biasa — bukan logic duplikat. Alurnya: resolve SKU -> Product lewat `ProductService.getProductBySku()` (lempar `NotFoundException` -> 404 kalau SKU tidak ada), baru panggil `StockService.getStockPerWarehouse(product.id)`.
- `ProductService.createProduct()` memakai `findBySku()` di repository secara internal untuk validasi keunikan SKU (PRD-01) sebelum menyimpan — bukan method terpisah, cukup bagian dari alur `createProduct()`.
- `ProductService.getProductBySku()` juga memakai `findBySku()` di repository — dua pemanggil ini yang membuat method tersebut benar-benar terpakai (sebelumnya sempat tidak tersambung ke alur mana pun).

## Diagram 3 - Purchase Order & Goods Receipt (PO-01)

```mermaid
classDiagram
    direction LR

    class PurchaseOrderStatus {
        <<enumeration>>
        DRAFT
        ORDERED
        PARTIALLY_RECEIVED
        RECEIVED
        CANCELLED
    }

    class PurchaseOrder {
        +int id
        +int supplierId
        +int warehouseId
        +PurchaseOrderStatus status
        +DateTime orderDate
        +int createdBy
        +PurchaseOrderItem[] items
        +canTransitionTo(PurchaseOrderStatus next) bool
    }

    class PurchaseOrderItem {
        +int id
        +int purchaseOrderId
        +int productId
        +int qty
        +float buyPrice
        +int receivedQty
        +remainingQty() int
    }

    class PurchaseOrderRepositoryInterface {
        <<interface>>
        +findById(int id) PurchaseOrder
        +save(PurchaseOrder po) void
        +searchPaginated(Filter f) PaginatedResult
    }
    class MySqlPurchaseOrderRepository
    class InMemoryPurchaseOrderRepository

    class PurchaseOrderService {
        -PurchaseOrderRepositoryInterface purchaseOrders
        -ProductRepositoryInterface products
        +createDraft(PurchaseOrderData d) PurchaseOrder
        +markOrdered(int id) void
        +cancel(int id) void
    }

    class GoodsReceiptService {
        -PurchaseOrderRepositoryInterface purchaseOrders
        -ProductStockRepositoryInterface stocks
        -StockLedgerRepositoryInterface ledger
        +receive(int poId, ReceiveItem[] items, int performedBy) void
    }

    class PurchaseOrderController {
        -PurchaseOrderService poService
        +index(Request) Response
        +show(Request) Response
        +create(Request) Response
    }

    class GoodsReceiptController {
        -GoodsReceiptService receiptService
        +receive(Request) Response
    }

    PurchaseOrder "1" *-- "many" PurchaseOrderItem
    PurchaseOrder --> PurchaseOrderStatus
    PurchaseOrderRepositoryInterface <|.. MySqlPurchaseOrderRepository
    PurchaseOrderRepositoryInterface <|.. InMemoryPurchaseOrderRepository
    PurchaseOrderService --> PurchaseOrderRepositoryInterface
    GoodsReceiptService --> PurchaseOrderRepositoryInterface
    GoodsReceiptService --> ProductStockRepositoryInterface
    GoodsReceiptService --> StockLedgerRepositoryInterface
    PurchaseOrderController --> PurchaseOrderService
    GoodsReceiptController --> GoodsReceiptService

    note for GoodsReceiptService "receive() dibungkus 1 DB transaction:\nupdate PurchaseOrderItem.receivedQty,\nupdate status PO, tambah ProductStock,\ntulis StockLedger(Receipt) - lihat ARCH-02"
```

## Diagram 4 - Sales Order & Goods Issue (SO-01)

```mermaid
classDiagram
    direction LR

    class SalesOrderStatus {
        <<enumeration>>
        DRAFT
        PENDING_APPROVAL
        APPROVED
        FULFILLED
        CANCELLED
    }

    class SalesOrder {
        +int id
        +int customerId
        +int warehouseId
        +SalesOrderStatus status
        +int createdBy
        +int approvedBy
        +SalesOrderItem[] items
        +canTransitionTo(SalesOrderStatus next) bool
    }

    class SalesOrderItem {
        +int id
        +int salesOrderId
        +int productId
        +int qty
        +float sellPrice
    }

    class SalesOrderRepositoryInterface {
        <<interface>>
        +findById(int id) SalesOrder
        +save(SalesOrder so) void
        +searchPaginated(Filter f) PaginatedResult
    }
    class MySqlSalesOrderRepository
    class InMemorySalesOrderRepository

    class SalesOrderService {
        -SalesOrderRepositoryInterface salesOrders
        +createDraft(SalesOrderData d, int createdBy) SalesOrder
        +submitForApproval(int id) void
        +approve(int id, CurrentUser actingUser) void
        +reject(int id, CurrentUser actingUser) void
        +cancel(int id) void
    }

    class GoodsIssueService {
        -SalesOrderRepositoryInterface salesOrders
        -ProductStockRepositoryInterface stocks
        -StockLedgerRepositoryInterface ledger
        +issue(int soId, int performedBy) void
    }

    class SalesOrderController {
        -SalesOrderService soService
        -AuthGuard guard
        +index(Request) Response
        +show(Request) Response
        +create(Request) Response
        +submit(Request) Response
        +approve(Request) Response
        +reject(Request) Response
    }

    class GoodsIssueController {
        -GoodsIssueService issueService
        +issue(Request) Response
    }

    SalesOrder "1" *-- "many" SalesOrderItem
    SalesOrder --> SalesOrderStatus
    SalesOrderRepositoryInterface <|.. MySqlSalesOrderRepository
    SalesOrderRepositoryInterface <|.. InMemorySalesOrderRepository
    SalesOrderService --> SalesOrderRepositoryInterface
    GoodsIssueService --> SalesOrderRepositoryInterface
    GoodsIssueService --> ProductStockRepositoryInterface
    GoodsIssueService --> StockLedgerRepositoryInterface
    SalesOrderController --> SalesOrderService
    SalesOrderController --> AuthGuard
    GoodsIssueController --> GoodsIssueService

    note for SalesOrderService "approve()/reject() memvalidasi\nactingUser.role === ADMIN\n(SO-01 segregation of duties,\nditegakkan di server, bukan hanya UI)"
    note for GoodsIssueService "issue() dibungkus 1 DB transaction\n+ mekanisme concurrency-safe\n(cegah oversell) - mekanisme final\ndiputuskan via ADR saat slice ini dikerjakan"
```

## Diagram 5 - Stock Ledger (shared core)

Dipakai bersama oleh `GoodsReceiptService` (Diagram 3) dan `GoodsIssueService` (Diagram 4).

```mermaid
classDiagram
    direction LR

    class StockMovementType {
        <<enumeration>>
        RECEIPT
        ISSUE
        ADJUSTMENT
    }

    class StockLedger {
        +int id
        +int productId
        +int warehouseId
        +StockMovementType movementType
        +int quantity
        +string referenceType
        +int referenceId
        +int performedBy
        +DateTime createdAt
    }

    class StockLedgerRepositoryInterface {
        <<interface>>
        +append(StockLedger entry) void
        +queryByDateRange(DateTime from, DateTime to) StockLedger[]
    }
    class MySqlStockLedgerRepository
    class InMemoryStockLedgerRepository

    StockLedger --> StockMovementType
    StockLedgerRepositoryInterface <|.. MySqlStockLedgerRepository
    StockLedgerRepositoryInterface <|.. InMemoryStockLedgerRepository

    note for StockLedger "Append-only - hanya ditulis lewat service.\nProductStock tidak pernah diubah langsung oleh UI."
```

## Diagram 6 - Dashboard & Report (DASH-01, REPORT-01)

```mermaid
classDiagram
    direction LR

    class DashboardService {
        -ProductRepositoryInterface products
        -SalesOrderRepositoryInterface salesOrders
        -PurchaseOrderRepositoryInterface purchaseOrders
        +getAdminDashboard() AdminDashboardData
        +getSalesDashboard(int userId) SalesDashboardData
        +getWarehouseDashboard() WarehouseDashboardData
    }

    class ReportService {
        -StockLedgerRepositoryInterface ledger
        -SalesOrderRepositoryInterface salesOrders
        -PurchaseOrderRepositoryInterface purchaseOrders
        +exportStockLedgerCsv(DateTime from, DateTime to) string
        +exportOrderStatusCsv(DateTime from, DateTime to) string
    }

    class DashboardController {
        -DashboardService dashboardService
        -AuthGuard guard
        +index(Request) Response
    }

    class ReportController {
        -ReportService reportService
        +downloadStockCsv(Request) Response
        +downloadOrderCsv(Request) Response
    }

    DashboardController --> DashboardService
    ReportController --> ReportService

    note for DashboardService "Angka dihitung dari query agregasi\nrepository, bukan angka statis - DASH-01"
```

## Di Luar Diagram (JOB-01)

`scripts/check-low-stock.php` adalah entry point CLI mandiri (bukan Controller), memanggil `StockService`/`ProductService` yang sama seperti web — membuktikan reuse business logic lintas request-cycle dan CLI.

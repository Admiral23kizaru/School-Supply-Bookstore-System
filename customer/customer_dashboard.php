<?php
session_start();
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'customer') {
    // header('Location: ../index.php');
    // exit;
}
require_once __DIR__ . '/../db/db.php';

$email = $_SESSION['email'] ?? 'customer@mail.com';
$customer_id = $_SESSION['user_id'] ?? 1;

// Fetch products from database
$productsData = [];
$res = $conn->query("SELECT id, name, category, description AS desc_text, price, stock, status FROM products");
if ($res) {
    while ($row = $res->fetch_assoc()) {
        $productsData[] = [
            'id' => (int)$row['id'],
            'name' => $row['name'],
            'category' => $row['category'],
            'price' => (float)$row['price'],
            'stock' => (int)$row['stock'],
            'status' => $row['status'],
            'desc' => $row['desc_text']
        ];
    }
}

// Fetch orders from database
$ordersData = [];
$stmt = $conn->prepare("
    SELECT o.id, DATE_FORMAT(o.created_at, '%b %d, %Y') as date, o.total_amount as total, o.status,
           (SELECT GROUP_CONCAT(CONCAT(oi.quantity, 'x ', p.name) SEPARATOR ', ')
            FROM order_items oi
            JOIN products p ON oi.product_id = p.id
            WHERE oi.order_id = o.id) as items
    FROM orders o
    WHERE o.customer_id = ?
    ORDER BY o.created_at DESC
");
if ($stmt) {
    $stmt->bind_param("i", $customer_id);
    $stmt->execute();
    $resOrder = $stmt->get_result();
    while ($row = $resOrder->fetch_assoc()) {
        $ordersData[] = [
            'id' => $row['id'],
            'date' => $row['date'],
            'items' => $row['items'] ? $row['items'] : 'No items',
            'total' => (float)$row['total'],
            'status' => $row['status']
        ];
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Customer Dashboard - School Supply</title>
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Bootstrap Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <!-- Alpine.js -->
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&display=swap');
        [x-cloak] { display: none !important; }
        
        body { 
            font-family: 'Inter', sans-serif; 
            background-color: #f8f9fa; 
        }
        
        .sidebar { width: 260px; flex-shrink: 0; background-color: #1a1a1a; }
        .bg-dark-custom { background-color: #1a1a1a !important; color: white !important;}
        .bg-dark-custom:hover { background-color: #000 !important; color: white !important;}
        .text-dark-custom { color: #1a1a1a !important; }
        .text-gray-400 { color: #9ca3af !important; }
        .text-gray-500 { color: #6b7280 !important; }
        
        .nav-btn {
            width: 100%; text-align: left; background: none; border: none;
            color: #d1d5db; padding: 10px 16px; border-radius: 0.75rem;
            display: flex; align-items: center; gap: 12px; font-weight: 500;
            transition: all 0.2s; font-size: 14px; margin-bottom: 4px;
        }
        .nav-btn:hover { background-color: rgba(255,255,255,0.1); color: white; }
        .nav-btn.active { background-color: white; color: #1a1a1a; box-shadow: 0 1px 2px rgba(0,0,0,0.05); }
        .nav-btn i { font-size: 18px; }

        .card-custom { border-radius: 1rem; border: 1px solid #f3f4f6; box-shadow: 0 1px 2px rgba(0,0,0,0.05); }
        .btn-custom { border-radius: 0.75rem; padding: 10px 20px; font-size: 14px; font-weight: 600; }
        
        /* Custom scrollbar */
        ::-webkit-scrollbar { width: 6px; }
        ::-webkit-scrollbar-track { background: transparent; }
        ::-webkit-scrollbar-thumb { background: #d1d5db; border-radius: 4px; }
        ::-webkit-scrollbar-thumb:hover { background: #9ca3af; }
    </style>
</head>
<body class="d-flex vh-100 overflow-hidden" x-data="dashboardData()">

    <!-- Sidebar -->
    <aside class="sidebar text-white d-flex flex-column justify-content-between h-100">
        <div>
            <!-- Header -->
            <div class="d-flex align-items-center px-4 border-bottom" style="height: 80px; border-color: #374151 !important;">
                <div class="d-flex align-items-center gap-3">
                    <div class="bg-white text-dark rounded-3 d-flex align-items-center justify-content-center" style="width: 40px; height: 40px;">
                        <i class="bi bi-journal-bookmark-fill fs-5"></i>
                    </div>
                    <div>
                        <h1 class="h6 mb-0 fw-bold">School Supply</h1>
                        <p class="mb-0 text-gray-400 fw-medium" style="font-size: 11px;">Customer Portal</p>
                    </div>
                </div>
            </div>

            <!-- Navigation -->
            <div class="px-3 py-4">
                <p class="text-gray-500 fw-bold text-uppercase px-3 mb-3" style="font-size: 11px; letter-spacing: 0.5px;">Menu</p>
                <nav>
                    <button @click="tab = 'shop'" :class="tab === 'shop' ? 'active' : ''" class="nav-btn">
                        <i class="bi bi-cart3"></i> Shop
                    </button>
                    <button @click="tab = 'orders'" :class="tab === 'orders' ? 'active' : ''" class="nav-btn">
                        <i class="bi bi-receipt"></i> My Orders
                    </button>
                    <button @click="tab = 'cart'" :class="tab === 'cart' ? 'active' : ''" class="nav-btn justify-content-between">
                        <div class="d-flex align-items-center gap-3">
                            <i class="bi bi-bag"></i> Cart
                        </div>
                        <span :class="[cartCount > 0 ? 'd-flex' : 'd-none', tab === 'cart' ? 'bg-dark text-white' : 'bg-secondary']" class="badge rounded-circle align-items-center justify-content-center" 
                              style="width:20px; height:20px; font-size:10px; padding:0;" x-text="cartCount"></span>
                    </button>
                </nav>
            </div>
        </div>

        <!-- Footer / Account -->
        <div class="px-3 pb-4">
            <div class="d-flex align-items-center gap-3 px-3 py-2 rounded-3 mb-2" style="cursor: pointer;">
                <div class="rounded-circle d-flex align-items-center justify-content-center text-gray-400" style="width: 36px; height: 36px; background-color: #374151;">
                    <i class="bi bi-person"></i>
                </div>
                <div class="overflow-hidden flex-grow-1">
                    <h4 class="mb-0 text-white fw-semibold text-truncate" style="font-size: 13px;">Customer Account</h4>
                    <p class="mb-0 text-gray-400 text-truncate" style="font-size: 11px;"><?= htmlspecialchars($email) ?></p>
                </div>
            </div>
            <a href="../logout.php" class="d-flex align-items-center gap-3 px-3 py-2 text-gray-400 text-decoration-none fw-medium" style="font-size: 13px; transition: color 0.2s;" onmouseover="this.style.color='white'" onmouseout="this.style.color='#9ca3af'">
                <i class="bi bi-box-arrow-right"></i> Logout
            </a>
        </div>
    </aside>

    <!-- Main Content Area -->
    <main class="flex-grow-1 d-flex flex-column h-100 position-relative bg-white" style="background-color: rgba(255,255,255,0.5) !important;">
        <div class="flex-grow-1 overflow-auto w-100 p-4 p-md-5">
            <?php include __DIR__ . '/shop.php'; ?>
            <?php include __DIR__ . '/my_orders.php'; ?>
            <?php include __DIR__ . '/carts.php'; ?>
        </div>
    </main>

    <!-- Product Modal -->
    <div class="modal fade" id="productModal" tabindex="-1" aria-hidden="true" x-ref="bsModal">
      <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 card-custom overflow-hidden" style="border-radius: 1rem;">
            <template x-if="selectedProduct">
                <div class="modal-body p-4 p-sm-4">
                    <!-- Modal Header -->
                    <div class="d-flex align-items-center justify-content-between mb-4">
                        <h6 class="mb-0 fw-bold fs-6">Product Details</h6>
                        <button type="button" class="btn-close shadow-none" @click="closeModal()"></button>
                    </div>

                    <!-- Image placeholder -->
                    <div class="bg-light rounded-4 d-flex align-items-center justify-content-center mb-4 border" style="height: 160px; border-radius:0.75rem;">
                        <i class="bi bi-journal-text" style="font-size: 40px; color: #dee2e6;"></i>
                    </div>

                    <!-- Info -->
                    <div class="mb-4">
                        <span class="text-gray-400 fw-bold text-uppercase mb-1 d-block" style="font-size: 10px; letter-spacing: 1px;" x-text="selectedProduct.category"></span>
                        <h4 class="fw-bold text-dark lh-sm mb-2 fs-5" x-text="selectedProduct.name"></h4>
                        <p class="text-secondary small mb-0" style="line-height: 1.6;" x-text="selectedProduct.desc"></p>
                    </div>

                    <!-- Pricing box -->
                    <div class="bg-light border rounded-3 p-3 d-flex align-items-center justify-content-between mb-4" style="border-radius:0.75rem;">
                        <div>
                            <p class="text-gray-400 fw-bold text-uppercase mb-0" style="font-size: 10px;">Price</p>
                            <p class="text-dark mb-0 fs-5" x-text="'₱' + selectedProduct.price.toFixed(2)" style="font-weight: 900;"></p>
                        </div>
                        <div class="text-end">
                            <p class="text-gray-400 fw-bold text-uppercase mb-0" style="font-size: 10px;">Stock</p>
                            <p class="fw-bold text-dark mb-0 mt-0" style="font-size: 13px;" x-text="selectedProduct.stock + ' units'"></p>
                        </div>
                    </div>

                    <!-- Actions -->
                    <div class="d-flex gap-2">
                        <button type="button" @click="closeModal()" class="btn btn-light border fw-semibold flex-fill btn-custom text-secondary shadow-sm">Close</button>
                        <button type="button" @click="addToCart(selectedProduct)" class="btn bg-dark-custom btn-custom flex-fill text-white d-flex align-items-center justify-content-center gap-2 shadow-sm">
                            <i class="bi bi-cart-plus"></i> Add to Cart
                        </button>
                    </div>
                </div>
            </template>
        </div>
      </div>
    </div>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

    <!-- Alpine.js Application Logic -->
    <script>
        document.addEventListener('alpine:init', () => {
             // Let Alpine initialize naturally before mapping the modal
        });
        
        function dashboardData() {
            return {
                tab: 'shop',
                search: '',
                cart: [],
                modalInstance: null,
                selectedProduct: null,
                isLoading: false,
                
                products: <?php echo json_encode($productsData); ?>,
                orders: <?php echo json_encode($ordersData); ?>,

                init() {
                    // Initialize Bootstrap Modal
                    this.$nextTick(() => {
                        this.modalInstance = new bootstrap.Modal(this.$refs.bsModal, {
                            backdrop: 'static'
                        });
                    });
                },

                get filteredProducts() {
                    if(!this.search.trim()) return this.products;
                    const q = this.search.toLowerCase();
                    return this.products.filter(p => p.name.toLowerCase().includes(q) || p.category.toLowerCase().includes(q));
                },

                get cartTotal() {
                    return this.cart.reduce((sum, item) => sum + (item.price * item.qty), 0);
                },

                get cartCount() {
                    return this.cart.reduce((sum, item) => sum + item.qty, 0);
                },

                openModal(product) {
                    this.selectedProduct = product;
                    if(this.modalInstance) {
                        this.modalInstance.show();
                    }
                },

                closeModal() {
                    if(this.modalInstance) {
                        this.modalInstance.hide();
                    }
                    setTimeout(() => { this.selectedProduct = null; }, 300);
                },

                addToCart(product) {
                    const existing = this.cart.find(i => i.id === product.id);
                    if(existing) {
                        existing.qty++;
                    } else {
                        // Create a reactive clone
                        this.cart.push(JSON.parse(JSON.stringify({...product, qty: 1})));
                    }
                    this.closeModal();
                },

                updateQty(id, change) {
                    const index = this.cart.findIndex(i => i.id === id);
                    if(index > -1) {
                        this.cart[index].qty += change;
                        if(this.cart[index].qty <= 0) {
                            this.cart.splice(index, 1);
                        }
                    }
                },

                async checkout() {
                    if (this.cart.length === 0) return;
                    this.isLoading = true;
                    
                    try {
                        const response = await fetch('process_checkout.php', {
                            method: 'POST',
                            headers: { 'Content-Type': 'application/json' },
                            body: JSON.stringify({ cart: this.cart })
                        });
                        
                        const result = await response.json();
                        
                        if (result.success) {
                            alert('Order placed successfully! Check your My Orders tab.');
                            window.location.reload();
                        } else {
                            alert(result.error || 'Failed to process checkout.');
                        }
                    } catch (error) {
                        console.error(error);
                        alert('A network error occurred.');
                    } finally {
                        this.isLoading = false;
                    }
                }
            }
        }
    </script>
</body>
</html>

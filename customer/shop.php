<!-- ===================== SHOP VIEW ===================== -->
<div :class="tab === 'shop' ? 'd-flex' : 'd-none'" x-cloak class="container-fluid px-0 h-100 flex-column">
    <header class="d-flex flex-column flex-sm-row align-items-sm-center justify-content-between gap-3 mb-4">
        <div>
            <h2 class="fs-4 fw-bold text-dark mb-1 tracking-tight">Products</h2>
            <p class="text-secondary small fw-medium mb-0">Browse and prepare your supplies cart.</p>
        </div>
        <div class="position-relative" style="max-width:320px; width: 100%;">
            <i class="bi bi-search position-absolute top-50 translate-middle-y text-secondary" style="left: 14px;"></i>
            <input type="text" x-model="search" placeholder="Search products..." class="form-control bg-white shadow-sm border-0" style="padding-left: 40px; padding-top:10px; padding-bottom:10px; border-radius:1rem;">
        </div>
    </header>

    <div class="row g-4 pb-4">
        <template x-for="product in filteredProducts" :key="product.id">
            <div class="col-12 col-sm-6 col-lg-4 col-xl-3">
                <div class="card h-100 card-custom border-0 bg-white" style="cursor: pointer; transition: transform 0.2s, box-shadow 0.2s;" onmouseover="this.style.transform='translateY(-4px)'; this.classList.add('shadow');" onmouseout="this.style.transform='none'; this.classList.remove('shadow');">
                    <!-- Image -->
                    <div class="bg-light d-flex align-items-center justify-content-center p-4 border-bottom" style="height: 180px; border-top-left-radius: 1rem; border-top-right-radius: 1rem; overflow:hidden;" @click="openModal(product)">
                        <template x-if="product.image_url">
                            <img :src="product.image_url" alt="" style="max-width:100%; max-height:100%; object-fit:contain;">
                        </template>
                        <template x-if="!product.image_url">
                            <i class="bi bi-journal-text text-secondary opacity-50" style="font-size: 48px;"></i>
                        </template>
                    </div>
                    <!-- Body -->
                    <div class="card-body p-4 d-flex flex-column">
                        <span class="text-gray-400 fw-bold text-uppercase mb-2 d-inline-block" style="font-size: 10px; letter-spacing: 1px;" x-text="product.category"></span>
                        <h5 class="card-title fw-bold text-dark lh-sm mb-3" style="font-size: 15px; min-height: 42px;" x-text="product.name"></h5>
                        
                        <div class="mb-3">
                            <div class="text-dark fs-4" style="font-weight: 900; line-height: 1;" x-text="'₱' + product.price.toFixed(2)"></div>
                        </div>

                        <div class="d-flex align-items-center justify-content-between mb-4 mt-auto">
                            <span class="text-gray-400 fw-medium" style="font-size: 11px;" x-text="product.stock + ' units left'"></span>
                            <span class="badge rounded-2 fw-bold text-dark border px-2 py-1" 
                                  :style="product.status === 'In Stock' ? 'background-color: #fff; border-color: #374151 !important; color: #1a1a1a;' : 'background-color: #f8f9fa; border-color: #e5e7eb; color: #9ca3af;'"
                                  style="font-size: 10px;"
                                  x-text="product.status"></span>
                        </div>

                        <div class="d-flex align-items-center gap-2">
                            <button @click="openModal(product)" class="btn btn-light border bg-white flex-fill d-flex align-items-center justify-content-center gap-2 shadow-sm text-secondary" style="border-radius: 0.5rem; font-size: 12px; font-weight: 600; padding-top: 8px; padding-bottom: 8px;">
                                <i class="bi bi-eye"></i> Details
                            </button>
                            <button @click="addToCart(product)" class="btn bg-dark-custom flex-fill d-flex align-items-center justify-content-center gap-2 shadow-sm text-white" style="border-radius: 0.5rem; font-size: 12px; font-weight: 600; padding-top: 8px; padding-bottom: 8px;">
                                <i class="bi bi-cart3"></i> Add to Cart
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </template>
        
        <div :class="filteredProducts.length === 0 ? 'd-block' : 'd-none'" class="col-12 text-center py-5">
            <div class="rounded-circle bg-light d-inline-flex align-items-center justify-content-center mb-3" style="width: 64px; height: 64px;">
                <i class="bi bi-search text-secondary fs-3"></i>
            </div>
            <h5 class="fw-bold text-dark">No products found</h5>
            <p class="text-secondary small">We couldn't find anything matching your search term.</p>
        </div>
    </div>
</div>

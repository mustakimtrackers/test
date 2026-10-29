    <!-- FOrbit Footer Credit (Minimal & Removable) -->
    <footer class="mt-auto py-3 text-center text-muted border-top text-small mt-5" style="margin-left: -24px; margin-right: -24px; margin-bottom: 0;">
        <div class="container">
            <span class="d-block mb-1">
                Powered by <a href="#" target="_blank" class="text-decoration-none fw-bold text-primary">FBM</a>
            </span>
            <small>&copy; <?= date('Y') ?> All Rights Reserved</small>
        </div>
    </footer>
    <!-- End Footer -->
    </main>
    
    <?php if (function_exists('isLoggedIn') && isLoggedIn()): ?>
    <!-- Floating Action Button (FAB) -->
    <div class="fab-container">
        <div class="fab-menu">
            <a href="<?= url('/modules/sales/pos.php') ?>" class="fab-item text-decoration-none bg-primary text-white" title="New Sale" data-bs-toggle="tooltip" data-bs-placement="left">
                <i class="bi bi-cart-plus"></i>
                <span class="fab-label">New Sale</span>
            </a>
            <a href="<?= url('/modules/finance/quick_adjustment.php') ?>" class="fab-item text-decoration-none bg-success text-white" title="Quick Txn" data-bs-toggle="tooltip" data-bs-placement="left">
                <i class="bi bi-arrow-left-right"></i>
                <span class="fab-label">Quick Txn</span>
            </a>
            <a href="<?= url('/modules/expenses/create.php') ?>" class="fab-item text-decoration-none bg-danger text-white" title="Add Expense" data-bs-toggle="tooltip" data-bs-placement="left">
                <i class="bi bi-receipt"></i>
                <span class="fab-label">Add Expense</span>
            </a>
        </div>
        <button class="fab-main shadow-lg" id="fabToggleBtn">
            <i class="bi bi-lightning-fill fs-4"></i>
        </button>
    </div>
    <?php endif; ?>
    
    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    
    <!-- Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
    
    <!-- Select2 JS -->
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
    <script>
        $(document).ready(function() {
            // Global initialization for any dropdown with 'select2' class
            $('.select2').select2({
                width: '100%',
                placeholder: '-- Please Select --',
                allowClear: true
            });
            
            // Fix Select2 search focus inside Bootstrap Modals
            $(document).on('shown.bs.modal', function (e) {
                $(this).find('.select2').each(function() {
                    $(this).select2({
                        dropdownParent: $(this).closest('.modal-content'),
                        width: '100%'
                    });
                });
            });
        });
    </script>
    
    <!-- Custom Scripts -->
    <script src="<?= url('/assets/js/main.js?v=' . time()) ?>"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Mobile Sidebar Toggle
            const sidebarToggle = document.getElementById('sidebarToggle');
            const sidebar = document.querySelector('.sidebar');
            const sidebarOverlay = document.getElementById('sidebarOverlay');
            
            if (sidebarToggle && sidebar && sidebarOverlay) {
                sidebarToggle.addEventListener('click', () => {
                    sidebar.classList.add('mobile-open');
                    sidebarOverlay.classList.add('active');
                });
                
                sidebarOverlay.addEventListener('click', () => {
                    sidebar.classList.remove('mobile-open');
                    sidebarOverlay.classList.remove('active');
                });
            }

            // Desktop Mini Sidebar Toggle
            const pcToggle = document.getElementById('desktopSidebarToggle');
            const logoToggle = document.querySelector('.sidebar-header .logo');

            const toggleMini = () => {
                document.body.classList.toggle('sidebar-mini');
                localStorage.setItem('sidebar-mini', document.body.classList.contains('sidebar-mini'));
            };

            if(pcToggle) {
                pcToggle.addEventListener('click', toggleMini);
            }
            if(logoToggle) {
                logoToggle.style.cursor = 'pointer';
                logoToggle.addEventListener('click', () => {
                    if (document.body.classList.contains('sidebar-mini') || window.innerWidth <= 992) {
                        toggleMini();
                    }
                });
            }

            // Scroll Hide Logic for Both FABs (Idea 5 Concept)
            let lastScrollY = window.scrollY;
            const fabContainer = document.querySelector('.fab-container');
            const pageFab = document.getElementById('mobileFabBtn');
            const scanBtn = document.getElementById('mobileScanBtn');
            
            const handleScroll = (currentScrollY) => {
                if (currentScrollY > lastScrollY && currentScrollY > 100) {
                    // Scrolling down - Hide FABs
                    if (fabContainer) {
                        fabContainer.classList.add('fab-hidden');
                        fabContainer.classList.remove('active'); // Close menu if open
                    }
                    if (pageFab) pageFab.classList.add('fab-hidden');
                    if (scanBtn) scanBtn.classList.add('fab-hidden');
                } else {
                    // Scrolling up - Show FABs
                    if (fabContainer) fabContainer.classList.remove('fab-hidden');
                    if (pageFab) pageFab.classList.remove('fab-hidden');
                    if (scanBtn) scanBtn.classList.remove('fab-hidden');
                }
                lastScrollY = currentScrollY;
            };

            // Main window scroll
            if (fabContainer || pageFab || scanBtn) {
                window.addEventListener('scroll', () => handleScroll(window.scrollY));
            }

            // POS specific inner container scroll
            const posProducts = document.querySelector('.pos-products-section');
            if (posProducts) {
                posProducts.addEventListener('scroll', (e) => handleScroll(e.target.scrollTop));
            }
            const posCart = document.querySelector('.cart-scroll-content');
            if (posCart) {
                posCart.addEventListener('scroll', (e) => handleScroll(e.target.scrollTop));
            }
        });
        
        // Initialize Desktop Sidebar State immediately to prevent FOUC
        if (localStorage.getItem('sidebar-mini') === 'true' && window.innerWidth > 992) {
            document.body.classList.add('sidebar-mini');
        }

        // Global handler to convert GET delete links to POST requests for security (CSRF protection)
        document.addEventListener('click', function(e) {
            let target = e.target.closest('a[href*="delete.php"]');
            if (target) {
                e.preventDefault();
                e.stopPropagation(); // Stop inline onclick from firing
                
                let confirmMsg = 'Are you sure you want to delete this item?';
                let onclickAttr = target.getAttribute('onclick');
                if (onclickAttr && onclickAttr.includes('confirm(')) {
                    let match = onclickAttr.match(/confirm\(['"](.*?)['"]\)/);
                    if (match && match[1]) {
                        confirmMsg = match[1];
                    }
                }
                
                if (confirm(confirmMsg)) {
                    let url = new URL(target.href, window.location.origin);
                    let form = document.createElement('form');
                    form.method = 'POST';
                    form.action = url.origin + url.pathname;
                    
                    let idInput = document.createElement('input');
                    idInput.type = 'hidden';
                    idInput.name = 'id';
                    idInput.value = url.searchParams.get('id');
                    form.appendChild(idInput);
                    
                    let csrfInput = document.createElement('input');
                    csrfInput.type = 'hidden';
                    csrfInput.name = 'csrf_token';
                    csrfInput.value = '<?= $_SESSION["csrf_token"] ?? "" ?>';
                    form.appendChild(csrfInput);
                    
                    document.body.appendChild(form);
                    form.submit();
                }
            }
        }, true); // Use capture phase to intercept before inline onclick
    </script>
    <?php if (isset($customJS)): ?>
        <script src="<?= $customJS ?>?v=<?= time() ?>"></script>
    <?php endif; ?>
</body>
</html>

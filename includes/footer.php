    <!-- Footer -->
    <footer class="bg-dark text-light py-5 mt-5 site-footer">
        <div class="container">
            <div class="row">
                <div class="col-lg-4 mb-4">
                    <h5 class="luxury-header text-warning mb-3"><?php echo APP_NAME; ?></h5>
                    <p class="text-light">Experience luxury and comfort at its finest. Your perfect stay awaits at The Heartland Abode.</p>
                    <div class="d-flex gap-3">
                        <a href="#" class="text-warning"><i class="fab fa-facebook-f"></i></a>
                        <a href="#" class="text-warning"><i class="fab fa-twitter"></i></a>
                        <a href="#" class="text-warning"><i class="fab fa-instagram"></i></a>
                        <a href="#" class="text-warning"><i class="fab fa-linkedin-in"></i></a>
                    </div>
                </div>
                <div class="col-lg-2 col-md-6 mb-4">
                    <h6 class="text-warning mb-3">Quick Links</h6>
                    <ul class="list-unstyled">
                        <li><a href="<?php echo appPath('/'); ?>" class="text-light text-decoration-none">Home</a></li>
                        <li><a href="<?php echo appPath('/user/rooms.php'); ?>" class="text-light text-decoration-none">Rooms</a></li>
                        <li><a href="<?php echo appPath('/user/dining.php'); ?>" class="text-light text-decoration-none">Dining</a></li>
                        <li><a href="<?php echo appPath('/user/contact.php'); ?>" class="text-light text-decoration-none">Contact</a></li>
                    </ul>
                </div>
                <div class="col-lg-3 col-md-6 mb-4">
                    <h6 class="text-warning mb-3">Services</h6>
                    <ul class="list-unstyled">
                        <li><a href="#" class="text-light text-decoration-none">Room Service</a></li>
                        <li><a href="#" class="text-light text-decoration-none">Concierge</a></li>
                        <li><a href="#" class="text-light text-decoration-none">Spa & Wellness</a></li>
                        <li><a href="#" class="text-light text-decoration-none">Business Center</a></li>
                    </ul>
                </div>
                <div class="col-lg-3 mb-4">
                    <h6 class="text-warning mb-3">Contact Info</h6>
                    <p class="text-light mb-2"><i class="fas fa-map-marker-alt me-2"></i>New Delhi, India</p>
                    <p class="text-light mb-2"><i class="fas fa-phone me-2"></i>+91-11-12345678</p>
                    <p class="text-light mb-2"><i class="fas fa-envelope me-2"></i>info@heartlandabode.com</p>
                </div>
            </div>
            <hr class="my-4">
            <div class="row align-items-center">
                <div class="col-md-6">
                    <p class="mb-0 text-light">&copy; <?php echo date('Y'); ?> <?php echo APP_NAME; ?>. All rights reserved.</p>
                </div>
                <div class="col-md-6 text-md-end">
                    <a href="#" class="text-light text-decoration-none me-3">Privacy Policy</a>
                    <a href="#" class="text-light text-decoration-none">Terms of Service</a>
                </div>
            </div>
        </div>
    </footer>

    <!-- MDBootstrap JS -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/mdb-ui-kit/6.4.2/mdb.min.js"></script>
    <!-- Chart.js for admin dashboard -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    
    <!-- Common JavaScript -->
    <script>
        window.CSRF_TOKEN = <?php echo json_encode(getCsrfToken()); ?>;

        // Show/Hide loading overlay
        function showLoading() {
            const overlay = document.getElementById('loadingOverlay');
            if (overlay) {
                overlay.style.cssText = `
                    position: fixed;
                    top: 0;
                    left: 0;
                    width: 100%;
                    height: 100%;
                    background: rgba(7, 12, 20, 0.55);
                    display: flex;
                    justify-content: center;
                    align-items: center;
                    z-index: 9999;
                `;
            } else {
                // Create loading overlay if it doesn't exist
                const loadingDiv = document.createElement('div');
                loadingDiv.id = 'loadingOverlay';
                loadingDiv.className = 'loading-spinner';
                loadingDiv.innerHTML = `
                    <div class="spinner-border text-warning" role="status">
                        <span class="visually-hidden">Loading...</span>
                    </div>
                `;
                loadingDiv.style.cssText = `
                    position: fixed;
                    top: 0;
                    left: 0;
                    width: 100%;
                    height: 100%;
                    background: rgba(0,0,0,0.5);
                    display: flex;
                    justify-content: center;
                    align-items: center;
                    z-index: 9999;
                `;
                document.body.appendChild(loadingDiv);
            }
        }
        
        function hideLoading() {
            const overlay = document.getElementById('loadingOverlay');
            if (overlay) {
                overlay.style.display = 'none';
            }
        }
        
        // Toast notifications
        function showToast(message, type = 'info') {
            const toastContainer = document.getElementById('toast-container') || createToastContainer();
            const toast = document.createElement('div');
            toast.className = `toast align-items-center text-white bg-${type} border-0`;
            toast.setAttribute('role', 'alert');
            toast.innerHTML = `
                <div class="d-flex">
                    <div class="toast-body">${message}</div>
                    <button type="button" class="btn-close btn-close-white me-2 m-auto" data-mdb-dismiss="toast"></button>
                </div>
            `;
            toastContainer.appendChild(toast);
            
            const bsToast = new mdb.Toast(toast);
            bsToast.show();
            
            // Remove toast after it's hidden
            toast.addEventListener('hidden.mdb.toast', () => {
                toast.remove();
            });
        }
        
        function createToastContainer() {
            const container = document.createElement('div');
            container.id = 'toast-container';
            container.className = 'toast-container position-fixed top-0 end-0 p-3';
            container.style.zIndex = '9999';
            document.body.appendChild(container);
            return container;
        }
        
        // API helper functions
        async function apiRequest(url, options = {}) {
            showLoading();
            try {
                const response = await fetch(url, {
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-Token': window.CSRF_TOKEN || '',
                        ...options.headers
                    },
                    ...options
                });
                
                let data;
                try {
                    data = await response.json();
                } catch (parseError) {
                    throw new Error('Invalid response format from server');
                }
                
                if (!response.ok) {
                    throw new Error(data.error || `Request failed with status ${response.status}`);
                }
                
                return data;
            } catch (error) {
                showToast(error.message, 'danger');
                throw error;
            } finally {
                hideLoading();
            }
        }
        
        // Form validation
        function validateForm(formElement) {
            const inputs = formElement.querySelectorAll('input[required], select[required], textarea[required]');
            let isValid = true;
            
            inputs.forEach(input => {
                if (!input.value.trim()) {
                    input.classList.add('is-invalid');
                    isValid = false;
                } else {
                    input.classList.remove('is-invalid');
                }
            });
            
            return isValid;
        }
        
        // Image preview
        function previewImage(input, previewElement) {
            if (input.files && input.files[0]) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    previewElement.src = e.target.result;
                    previewElement.style.display = 'block';
                };
                reader.readAsDataURL(input.files[0]);
            }
        }
        
        // Date formatting
        function formatDate(dateString) {
            const options = { year: 'numeric', month: 'long', day: 'numeric' };
            return new Date(dateString).toLocaleDateString('en-US', options);
        }
        
        // Currency formatting
        function formatCurrency(amount) {
            return new Intl.NumberFormat('en-IN', {
                style: 'currency',
                currency: 'INR'
            }).format(amount);
        }

        function initRevealAnimations() {
            if (window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
                return;
            }

            const revealTargets = Array.from(
                document.querySelectorAll('[data-reveal], .card-luxury, .stats-card')
            ).filter(el => !el.classList.contains('no-reveal'));

            if (revealTargets.length === 0) {
                return;
            }

            revealTargets.forEach((el, index) => {
                el.classList.add('reveal-on-scroll');
                el.style.transitionDelay = `${Math.min(index * 20, 180)}ms`;
            });

            if (!('IntersectionObserver' in window)) {
                revealTargets.forEach(el => el.classList.add('is-visible'));
                return;
            }

            const observer = new IntersectionObserver((entries, obs) => {
                entries.forEach((entry) => {
                    if (entry.isIntersecting) {
                        entry.target.classList.add('is-visible');
                        obs.unobserve(entry.target);
                    }
                });
            }, {
                threshold: 0.18,
                rootMargin: '0px 0px -5% 0px'
            });

            revealTargets.forEach(el => observer.observe(el));
        }
        
        // Initialize tooltips and popovers
        document.addEventListener('DOMContentLoaded', function() {
            // Initialize MDB components
            const tooltips = document.querySelectorAll('[data-mdb-toggle="tooltip"]');
            tooltips.forEach(tooltip => new mdb.Tooltip(tooltip));
            
            const popovers = document.querySelectorAll('[data-mdb-toggle="popover"]');
            popovers.forEach(popover => new mdb.Popover(popover));

            initRevealAnimations();
        });
    </script>
    
    <?php if (isset($additional_js)): ?>
        <?php echo $additional_js; ?>
    <?php endif; ?>
</body>
</html>

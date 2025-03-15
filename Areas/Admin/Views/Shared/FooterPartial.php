<footer class="footer mt-4">
    <div class="container-fluid">
        <!-- Thông tin chính -->
        <div class="row py-4">
            <div class="col-lg-9 col-md-8 text-center text-md-start">
                <h5 class="footer-title">TDMU Admin</h5>
                <p class="mb-1">©<?php echo date('Y'); ?> - TDMU Admin. Đã đăng ký bản quyền.</p>
                <p class="mb-1">Website được phát triển để sử dụng trong dữ án PHP</p>
                <p class="mb-1"><i class="fas fa-envelope me-2"></i>Email: abc@gmail.com</p>
            </div>
            <div class="col-lg-3 col-md-4 mt-3 mt-md-0">
                <div class="footer-stats p-3">
                    <p class="mb-2"><i class="fas fa-chart-line me-2"></i>Số lượt truy cập: 2</p>
                    <p class="mb-0"><i class="fas fa-users me-2"></i>Số người online: 800</p>
                </div>
            </div>
        </div>
        
        <!-- Đường phân cách -->
        <hr class="footer-divider">
        
        <!-- Liên kết xã hội -->
        <div class="row py-3">
            <div class="col-12">
                <div class="social-links">
                    <a href="#" class="social-link">
                        <i class="fab fa-facebook-f"></i>
                        <span>Facebook</span>
                    </a>
                    <a href="#" class="social-link">
                        <i class="fab fa-youtube"></i>
                        <span>Youtube</span>
                    </a>
                    <a href="mailto:abc@gmail.com" class="social-link">
                        <i class="fas fa-envelope"></i>
                        <span>Email</span>
                    </a>
                    <a href="/TDMUAdmin/PaymentWithPayPal" class="social-link donate">
                        <i class="fas fa-mug-hot"></i>
                        <span>Donate me a cup of tea</span>
                    </a>
                </div>
            </div>
        </div>
    </div>
</footer>

<style>
    .footer {
        background-color: #f8f9fa;
        color: #495057;
        border-top: 1px solid #dee2e6;
    }
    
    .footer-title {
        font-weight: 600;
        color: #212529;
        margin-bottom: 1rem;
    }
    
    .footer-stats {
        background-color: #fff;
        border-radius: 8px;
        box-shadow: 0 0.125rem 0.25rem rgba(0, 0, 0, 0.075);
    }
    
    .footer-divider {
        margin: 0.5rem 0;
        border-color: rgba(0, 0, 0, 0.1);
    }
    
    .social-links {
        display: flex;
        flex-wrap: wrap;
        justify-content: center;
        gap: 1rem;
    }
    
    .social-link {
        display: flex;
        align-items: center;
        padding: 0.75rem 1.25rem;
        background-color: #fff;
        border-radius: 8px;
        color: #495057;
        text-decoration: none;
        transition: all 0.3s ease;
        box-shadow: 0 0.125rem 0.25rem rgba(0, 0, 0, 0.075);
    }
    
    .social-link i {
        font-size: 1.25rem;
        margin-right: 0.5rem;
    }
    
    .social-link:hover {
        transform: translateY(-3px);
        color: #0d6efd;
        box-shadow: 0 0.5rem 1rem rgba(0, 0, 0, 0.1);
    }
    
    .social-link.donate {
        background-color: #fef2d9;
        color: #d35400;
    }
    
    .social-link.donate:hover {
        background-color: #feebc1;
        color: #e67e22;
    }
    
    @media (max-width: 768px) {
        .social-links {
            flex-direction: column;
            align-items: center;
        }
        
        .social-link {
            width: 80%;
            justify-content: center;
        }
    }
</style>
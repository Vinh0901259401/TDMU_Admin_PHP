<!-- filepath: c:\xampp\htdocs\TDMU_website\Areas\Admin\Views\Shared\SideBarPartial.php -->
<style>
    .sidebar-nav {
        background: #fff;
        border-radius: 8px;
        box-shadow: 0 0.5rem 1rem rgba(0, 0, 0, 0.05);
        max-height: 80vh;
        overflow-y: auto;
    }
    
    .sidebar-nav ul {
        padding: 0;
        margin: 0;
        list-style: none;
    }
    
    .sidebar-nav li {
        position: relative;
        margin-bottom: 2px;
    }
    
    .sidebar-nav li a {
        display: flex;
        align-items: center;
        padding: 12px 15px;
        color: #495057;
        text-decoration: none;
        transition: all 0.3s ease;
        border-radius: 6px;
    }
    
    .sidebar-nav li a i {
        margin-right: 10px;
        min-width: 20px;
        text-align: center;
        font-size: 1rem;
        transition: all 0.3s ease;
    }
    
    .sidebar-nav li a:hover {
        background-color: #f8f9fa;
        color: #0d6efd;
    }
    
    .sidebar-nav li.active > a {
        background-color: #e9f0ff;
        color: #0d6efd;
        font-weight: 500;
    }
    
    .sidebar-nav li.active > a i {
        color: #0d6efd;
    }
    
    .sidebar-nav .sub-menu > a {
        justify-content: space-between;
    }
    
    .sidebar-nav .sub-menu > a .caret {
        transition: transform 0.3s ease;
    }
    
    .sidebar-nav .sub-menu.open > a .caret {
        transform: rotate(-180deg);
    }
    
    .sidebar-nav .left-menu-dp {
        max-height: 0;
        overflow: hidden;
        transition: max-height 0.3s ease-out;
        padding-left: 1rem;
    }
    
    .sidebar-nav .sub-menu.open .left-menu-dp {
        max-height: 500px;
        transition: max-height 0.5s ease-in;
    }
    
    .sidebar-nav .left-menu-dp li a {
        padding: 8px 15px;
    }

    /* Modern thin scrollbar */
    .sidebar {
        scrollbar-width: thin;
        scrollbar-color: rgba(0, 0, 0, .2) transparent;
    }

    .sidebar::-webkit-scrollbar {
        width: 5px;
    }

    .sidebar::-webkit-scrollbar-track {
        background: transparent;
    }

    .sidebar::-webkit-scrollbar-thumb {
        background-color: rgba(0, 0, 0, .2);
        border-radius: 20px;
    }
</style>

<div class="sidebar-nav">
    <ul id="sidebar" class="nav flex-column">
        <li class="nav-item active">
            <a class="nav-link" href="/TDMU_website/Areas/Admin/Views/TDMUAdmin/Index.php">
                <i class="fas fa-home"></i>
                <span>Trang chủ</span>
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link" href="/TDMU_website/Areas/Admin/Views/Article/Index.php">
                <i class="fab fa-telegram-plane"></i>
                <span>Bài viết</span>
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link" href="/TDMU_website/Areas/Admin/Views/Class/Index.php">
                <i class="fas fa-chalkboard-teacher"></i>
                <span>Quản lý lớp học</span>
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link" href="#">
                <i class="fas fa-comment-alt"></i>
                <span>Phản hồi</span>
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link" href="/TDMU_website/Forum/Index">
                <i class="fas fa-comments"></i>
                <span>Forums giải đáp</span>
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link" href="/TDMU_website/Areas/Admin/Views/Notify/Index.php">
                <i class="fas fa-bell"></i>
                <span>Thông báo</span>
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link" href="#">
                <i class="fas fa-map-marked-alt"></i>
                <span>Bản đồ</span>
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link" href="/TDMU_website/Schedule/Index">
                <i class="fas fa-calendar-alt"></i>
                <span>Thời khóa biểu</span>
            </a>
        </li>

        <!-- element has sub menu -->
        <li class="nav-item sub-menu">
            <a class="nav-link" href="#">
                <i class="fas fa-cogs"></i>
                <span>Settings</span>
                <i class="fas fa-chevron-down caret ms-auto"></i>
            </a>
            <ul class="left-menu-dp">
                <li class="nav-item">
                    <a class="nav-link" href="#">
                        <i class="fas fa-user-circle"></i>
                        <span>Account</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="#">
                        <i class="fas fa-id-card"></i>
                        <span>Profile</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="#">
                        <i class="fas fa-fingerprint"></i>
                        <span>Security & Privacy</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="#">
                        <i class="fas fa-key"></i>
                        <span>Password</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="#">
                        <i class="fas fa-bell"></i>
                        <span>Notification</span>
                    </a>
                </li>
            </ul>
        </li>

        <li class="nav-item">
            <a class="nav-link" href="#">
                <i class="fas fa-book"></i>
                <span>Documentation</span>
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link" href="#">
                <i class="fas fa-sign-out-alt"></i>
                <span>Đăng xuất</span>
            </a>
        </li>
    </ul>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        // Get current page URL
        const currentUrl = window.location.pathname;
        
        // Remove active class from all items
        document.querySelectorAll('#sidebar .nav-item').forEach(item => {
            item.classList.remove('active');
        });
        
        // Add active class to current page
        document.querySelectorAll('#sidebar .nav-link').forEach(link => {
            const href = link.getAttribute('href');
            if (href !== '#' && currentUrl.includes(href)) {
                link.closest('.nav-item').classList.add('active');
            }
        });
        
        // Toggle submenu on click
        document.querySelectorAll('.sub-menu > a').forEach(item => {
            item.addEventListener('click', function(e) {
                e.preventDefault();
                const parent = this.parentElement;
                parent.classList.toggle('open');
            });
        });
    });
</script>
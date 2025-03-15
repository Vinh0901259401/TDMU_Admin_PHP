<!-- filepath: c:\xampp\htdocs\TDMU_website\Areas\Admin\Views\Shared\NavPartial.php -->
<?php
$tk = isset($_SESSION['TaiKhoan']) ? $_SESSION['TaiKhoan'] : null;
?>

<style>
    .header-container {
        box-shadow: 0 2px 10px rgba(0,0,0,0.1);
    }
    .logo {
        font-size: 1.25rem;
        font-weight: bold;
        color: #3a3a3a;
        margin-right: 1rem;
        margin-left: 6rem;
        display: flex;
        align-items: center;
    }
    .sidebarCollapse {
        color: #555;
        font-size: 1.2rem;
        display: flex;
        align-items: center;
    }
    .navbar-nav .icon {
        width: 24px;
        height: 24px;
        object-fit: contain;
    }
    .user-profile-dropdown .count {
        position: absolute;
        top: 2px;
        right: 2px;
        width: 18px;
        height: 18px;
        border-radius: 50%;
        font-size: 10px;
        display: flex;
        align-items: center;
        justify-content: center;
        color: white;
    }
    .purple-gradient {
        background: linear-gradient(45deg, #7367F0, #9e88f5);
    }
    .bg-clc {
        background-color: #4CAF50;
    }
    .dropdown-menu {
        padding: 0.5rem 0;
        border-radius: 0.5rem;
        box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        min-width: 280px;
    }
    .user-note, .sms-user {
        width: 40px;
        height: 40px;
        border-radius: 50%;
        margin-right: 10px;
    }
    .message-item {
        display: flex;
        align-items: center;
        padding: 10px 15px;
        border-bottom: 1px solid #f1f1f1;
    }
    .message-item:last-child {
        border-bottom: none;
    }
    .media {
        display: flex;
        align-items: center;
        padding: 15px;
    }
    .media img {
        width: 45px;
        height: 45px;
        border-radius: 50%;
    }
    .media-body {
        margin-left: 15px;
    }
    .media-body h5 {
        font-size: 1rem;
        margin: 0;
    }
    .media-body p {
        font-size: 0.8rem;
        color: #6c757d;
        margin: 0;
    }
    .dp-main-menu a {
        padding: 8px 15px;
        display: flex;
        align-items: center;
    }
    .dp-main-menu a span {
        margin-right: 10px;
        width: 18px;
        text-align: center;
    }
    .user-profile-section {
        background-color: #f8f9fa;
        border-bottom: 1px solid #e9ecef;
        border-radius: 0.5rem 0.5rem 0 0;
    }
    .nav-item {
        position: relative;
    }
    .nav-link.user {
        display: flex;
        align-items: center;
        justify-content: center;
        height: 40px;
        width: 40px;
        position: relative;
    }
    .user-message-info p {
    margin-bottom: 0;
    }
    .m-user-name {
        font-weight: bold;
    }
</style>
<div class="header-container fixed-top">
    <header class="header navbar navbar-expand-sm navbar-light bg-light py-2">
        <!-- LEFT LOGO -->
        <div class="header-left d-flex">
            <div class="logo">
                TDMUAdmin
            </div>
            <a href="#" class="btn btn-primary sidebar-toggler" id="toggleSideBar" data-bs-toggle="tooltip" data-bs-placement="bottom" title="Toggle Sidebar">
                <span class="fas fa-bars"></span>
            </a>
        </div>

        <!-- RIGHT DROP DOWN MENU -->
        <ul class="navbar-nav ms-auto d-flex align-items-center">
            <!-- Nav notify -->
            <?php include __DIR__ . '/LoadChildNotify.php'; ?>
            
            <!-- Nav message -->
            
            <?php include __DIR__ . '/LoadChildMessage.php'; ?>
         

            <!-- Nav profile item -->
            <li class="nav-item dropdown user-profile-dropdown mx-1">
                <?php if ($tk == null): ?>
                    <a href="#" class="nav-link user" id="Profile" data-bs-toggle="dropdown">
                        <img src="/TDMU_website/Areas/Admin/Assets/img/profile.png" class="icon" />
                    </a>
                    <div class="dropdown-menu dropdown-menu-end">
                        <div class="user-profile-section">
                            <div class="media mx-auto">
                                <img src="/TDMU_website/Areas/Admin/Assets/img/profile.png" alt="" class="img-fluid" />
                                <div class="media-body">
                                    <h5>Bạn chưa đăng nhập</h5>
                                    <p>Khách</p>
                                </div>
                            </div>
                        </div>
                        <div class="dp-main-menu">
                            <a href="<?php echo '/TDMU_website/User/Login.php?url=' . urlencode($_SERVER['REQUEST_URI']); ?>" class="dropdown-item">
                                <span class="fa-solid fa-right-to-bracket"></span>Đăng nhập
                            </a>
                            <a href="#" class="dropdown-item">
                                <span class="fa-solid fa-registered"></span>Đăng ký
                            </a>
                        </div>
                    </div>
                <?php else: ?>
                    <a href="#" class="nav-link user" id="Profile" data-bs-toggle="dropdown">
                        <img src="/TDMU_website/Areas/Admin/Assets/img/user-image/<?php echo $tk['ImagePath']; ?>" class="icon" />
                    </a>
                    <div class="dropdown-menu dropdown-menu-end">
                        <div class="user-profile-section">
                            <div class="media mx-auto">
                                <img src="/TDMU_website/Areas/Admin/Assets/img/user-image/<?php echo $tk['ImagePath']; ?>" alt="" class="img-fluid" />
                                <div class="media-body">
                                    <h5><?php echo $tk['HoTen']; ?></h5>
                                    <p><?php echo $tk['ChucVu']; ?></p>
                                </div>
                            </div>
                        </div>
                        <div class="dp-main-menu">
                            <a href="#" class="dropdown-item"><span class="fas fa-user"></span>Profile</a>
                            <a href="#" class="dropdown-item"><span class="fas fa-inbox"></span>Inbox</a>
                            <a href="#" class="dropdown-item"><span class="fas fa-lock-open"></span>Lock Screen</a>
                            <a href="/TDMU_website/Areas/Admin/Views/User/Logout.php" class="dropdown-item">
                                <span class="fas fa-sign-out-alt"></span>Đăng xuất
                            </a>
                        </div>
                    </div>
                <?php endif; ?>
            </li>

            <!-- Nav setting item -->
            <li class="nav-item dropdown user-profile-dropdown mx-1">
                <a href="#" class="nav-link user" id="Settings" data-bs-toggle="dropdown">
                    <img src="/TDMU_website/Areas/Admin/Assets/img/settings.png" class="icon" />
                </a>
                <div class="dropdown-menu dropdown-menu-end">
                    <div class="dp-main-menu">
                        <a href="#" class="dropdown-item"><span class="fas fa-plug"></span>Permissions</a>
                        <a href="#" class="dropdown-item"><span class="fas fa-user-shield"></span>Admin</a>
                        <a href="#" class="dropdown-item"><span class="fas fa-object-ungroup"></span>Design Type</a>
                        <a href="#" class="dropdown-item"><span class="fas fa-palette"></span>Color</a>
                    </div>
                </div>
            </li>
        </ul>
    </header>
</div>

<!-- filepath: c:\xampp\htdocs\TDMU_website\Areas\Admin\Views\Shared\NavPartial.php -->
<!-- Thêm đoạn mã này vào cuối file NavPartial.php -->
<?php
    // Lấy trạng thái sidebar từ session nếu có
    $sidebarCollapsed = isset($_SESSION['sidebarCollapsed']) ? $_SESSION['sidebarCollapsed'] : false;

    // Tạo class dựa trên trạng thái
    $sidebarClass = $sidebarCollapsed ? 'collapsed' : '';
    $mainContentClass = $sidebarCollapsed ? 'expanded' : '';

    // Lưu class vào biến JavaScript
    echo "<script>
        var sidebarCollapsed = " . ($sidebarCollapsed ? 'true' : 'false') . ";
    </script>";
?>

<style>
    .sidebar {
        transition: all 0.3s ease;
        width: 250px; /* Điều chỉnh theo chiều rộng thực tế của sidebar */
        height: 100%;
        z-index: 1050;
    }
    
    .sidebar.collapsed {
        transform: translateX(-250px);
    }
    
    .main-content {
        transition: all 0.3s ease;
        margin-left: 250px; /* Phải khớp với chiều rộng sidebar */
    }
    
    .main-content.expanded {
        margin-left: 0;
    }

    .sidebar-container {
        transition: width 0.3s ease;
    }

    .sidebar-container.d-none {
        width: 0;
        padding: 0;
        overflow: hidden;
    }
    
    /* Đảm bảo main-content có transition mượt mà */
    .main-content {
        transition: all 0.3s ease;
        margin-left: 250px; /* Khoảng cách mặc định */
    }
    
    .sidebar-container.d-none + div .main-content.expanded {
        margin-left: 0; /* Khi sidebar ẩn */
    }
</style>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        // Áp dụng class ban đầu
        var sidebarContainer = document.querySelector('.sidebar-container');
        var sidebar = document.querySelector('.sidebar');
        var mainContent = document.querySelector('.main-content');
        var toggleBtn = document.getElementById('toggleSideBar');
        
        if (!sidebar || !mainContent || !toggleBtn || !sidebarContainer) {
            console.error('Không tìm thấy phần tử cần thiết');
            return;
        }
        
        // Xử lý sự kiện click
        toggleBtn.addEventListener('click', function(e) {
            e.preventDefault();
            
            // Cập nhật biểu tượng
            var toggleIcon = this.querySelector('.fas');
            var isCollapsed = !sidebar.classList.contains('collapsed');  // Đảo ngược logic
            
            if (isCollapsed) {
                toggleIcon.classList.replace('fa-bars', 'fa-bars-staggered');
            } else {
                toggleIcon.classList.replace('fa-bars-staggered', 'fa-bars');
            }
            
            // Lưu trạng thái và reload để áp dụng thay đổi layout
            var xhr = new XMLHttpRequest();
            xhr.open('POST', '/TDMU_website/Areas/Admin/Views/Shared/SaveSidebarState.php', true);
            xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
            xhr.onload = function() {
                if (xhr.status === 200) {
                    console.log('Đã lưu trạng thái sidebar:', xhr.responseText);
                    window.location.reload();
                }
            };
            xhr.send('collapsed=' + (isCollapsed ? '1' : '0'));
        });
    });
</script>
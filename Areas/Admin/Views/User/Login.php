<?php
include __DIR__ . '/../Shared/connect.inc';
ob_start(); // Bắt đầu output buffering

// Khai báo biến mặc định
$title = "Đăng nhập";
$thongBao = "";
$err1 = "";
$err2 = "";

// Lấy URL chuyển hướng sau khi đăng nhập (nếu có)
$url = isset($_GET['url']) ? $_GET['url'] : '/TDMU_website/Areas/Admin/Views/TDMUAdmin/Index.php';

// Biến này có sẵn từ _LayoutAdmin.php (đã include connect.inc)
global $conn;
// Kiểm tra cookie để đăng nhập tự động
if (!isset($_SESSION['TaiKhoan']) && isset($_COOKIE['TenDN']) && isset($_COOKIE['MatKhau'])) {
    $cookie_username = $_COOKIE['TenDN'];
    $cookie_password = $_COOKIE['MatKhau']; // Đã được lưu dưới dạng MD5
    
    try {
        $stmt = $conn->prepare("SELECT * FROM taikhoan WHERE TenTaiKhoan = :username");
        $stmt->bindParam(':username', $cookie_username);
        $stmt->execute();
        
        if ($stmt->rowCount() > 0) {
            $user = $stmt->fetch();
            
            // Kiểm tra mật khẩu từ cookie (đã mã hóa MD5)
            if (strlen($user['MatKhau']) == 32 && ctype_xdigit($user['MatKhau'])) {
                // Mật khẩu trong DB đã là MD5
                $cookie_login_success = ($cookie_password === $user['MatKhau']);
            } else {
                // Mật khẩu trong DB là plain text, so sánh với MD5 của nó
                $cookie_login_success = ($cookie_password === md5($user['MatKhau']));
            }
            
            if ($cookie_login_success) {
                // Đăng nhập thành công từ cookie
                $_SESSION['TaiKhoan'] = [
                    'ID' => $user['ID'],
                    'HoTen' => $user['HoTen'],
                    'Email' => $user['Email'],
                    'ChucVu' => $user['ChucVu'],
                    'IDQuyenTruyCap' => $user['IDQuyenTruyCap'],
                    'ImagePath' => !empty($user['ImagePath']) ? $user['ImagePath'] : 'default-avatar.png'
                ];
                
                // Chuyển hướng đến trang đích
                header('Location: ' . $url);
                exit();
            }
        }
    } catch (PDOException $e) {
        // Xử lý lỗi nếu cần
    }
}
// Xử lý form đăng nhập khi bấm nút submit
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Lấy dữ liệu từ form
    $username = isset($_POST['TenDN']) ? trim($_POST['TenDN']) : '';
    $password = isset($_POST['MatKhau']) ? trim($_POST['MatKhau']) : '';
    $remember = isset($_POST['remember']) ? true : false;
    $url = isset($_POST['url']) ? $_POST['url'] : '/TDMU_website/Areas/Admin/Views/TDMUAdmin/Index.php';
    
    // Validate input
    if (empty($username)) {
        $err1 = 'Vui lòng nhập tên đăng nhập';
    }
    
    if (empty($password)) {
        $err2 = 'Vui lòng nhập mật khẩu';
    }
    
    // Nếu không có lỗi validation, kiểm tra đăng nhập
    if (empty($err1) && empty($err2)) {
        try {
            $stmt = $conn->prepare("SELECT * FROM taikhoan WHERE TenTaiKhoan = :username");
            $stmt->bindParam(':username', $username);
            $stmt->execute();
            
            if ($stmt->rowCount() > 0) {
                $user = $stmt->fetch();
                
                // Kiểm tra mật khẩu - trong thực tế nên dùng password_verify()
                if (strlen($user['MatKhau']) == 32 && ctype_xdigit($user['MatKhau'])) {
                    // Mật khẩu trong DB đã là MD5
                    $login_success = (md5($password) === $user['MatKhau']);
                } else {
                    // Mật khẩu trong DB là plain text
                    $login_success = ($password === $user['MatKhau']);
                }
                
                if (!session_id()) {
                    session_start();
                }
                
                // Thêm debug để kiểm tra session
                $sessionStatus = session_status() === PHP_SESSION_ACTIVE ? 'Hoạt động' : 'Không hoạt động';
                error_log("Trạng thái session: " . $sessionStatus);
                error_log("Session ID: " . session_id());
                
                if ($login_success) {
                    // Đảm bảo session được khởi tạo
                    if (session_status() === PHP_SESSION_NONE) {
                        session_start();
                    }
                    
                    // Lưu thông tin tài khoản vào biến $tk
                    $tk = [
                        'ID' => $user['ID'],
                        'HoTen' => $user['HoTen'],
                        'Email' => $user['Email'],
                        'ChucVu' => $user['ChucVu'],
                        'IDQuyenTruyCap' => $user['IDQuyenTruyCap'],
                        'ImagePath' => !empty($user['ImagePath']) ? $user['ImagePath'] : 'default-avatar.png'
                    ];
                    
                    // Lưu biến $tk vào session
                    $_SESSION['TaiKhoan'] = $tk;
                    
                    // Debug để kiểm tra session
                    error_log("Login - Session ID: " . session_id());
                    error_log("Login - TaiKhoan: " . json_encode($_SESSION['TaiKhoan']));
                    
                    // Đảm bảo session được lưu
                    session_write_close();
                    
                    // Xử lý "Nhớ thông tin đăng nhập"
                    if ($remember) {
                        setcookie('TenDN', $username, time() + (86400 * 30), '/'); // 30 ngày
                        setcookie('MatKhau', $password, time() + (86400 * 30), '/');
                    } else {
                        // Xóa cookie nếu có
                        if (isset($_COOKIE['TenDN'])) {
                            setcookie('TenDN', '', time() - 3600, '/');
                        }
                        if (isset($_COOKIE['MatKhau'])) {
                            setcookie('MatKhau', '', time() - 3600, '/');
                        }
                    }
                    $index_url = '/TDMU_website/Areas/Admin/Views/TDMUAdmin/Index.php';
                    // Chuyển hướng đến trang đích
                    header('Location: ' . $index_url);
                    exit();
                } else {
                    $thongBao = 'Tên đăng nhập hoặc mật khẩu không đúng';
                }
            } else {
                $thongBao = 'Tên đăng nhập hoặc mật khẩu không đúng';
            }
        } catch (PDOException $e) {
            $thongBao = 'Lỗi hệ thống, vui lòng thử lại sau';
        }
    }
}

// Kiểm tra cookie
$savedUsername = isset($_COOKIE["TenDN"]) ? $_COOKIE["TenDN"] : "";
$savedPassword = isset($_COOKIE["MatKhau"]) ? $_COOKIE["MatKhau"] : "";
$rememberedLogin = !empty($savedUsername);
?>

<div class="login-container my-5">
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-md-6 col-lg-5">
                <div class="card login-card">
                    <div class="card-header text-center bg-primary text-white">
                        <h4 class="mb-0"><i class="fas fa-user-lock me-2"></i>ĐĂNG NHẬP</h4>
                    </div>
                    <div class="card-body">
                        <?php if (!empty($thongBao)): ?>
                        <div class="alert alert-danger alert-dismissible fade show" role="alert">
                            <?php echo $thongBao; ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                        </div>
                        <?php endif; ?>
                        
                        <form action="<?php echo htmlspecialchars($_SERVER['PHP_SELF'] . '?' . http_build_query($_GET)); ?>" method="post">
                            <div class="mb-3">
                                <label for="TenDN" class="form-label">Tên đăng nhập</label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="fas fa-user"></i></span>
                                    <input type="text" class="form-control" id="TenDN" name="TenDN" 
                                           placeholder="Nhập tên đăng nhập" 
                                           value="<?php echo htmlspecialchars($savedUsername); ?>">
                                </div>
                                <?php if (!empty($err1)): ?>
                                <div class="text-danger mt-1"><?php echo $err1; ?></div>
                                <?php endif; ?>
                            </div>
                            
                            <div class="mb-3">
                                <label for="MatKhau" class="form-label">Mật khẩu</label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="fas fa-lock"></i></span>
                                    <input type="password" class="form-control" id="MatKhau" name="MatKhau" 
                                           placeholder="Nhập mật khẩu"
                                           value="<?php echo htmlspecialchars($savedPassword); ?>">
                                    <button class="btn btn-outline-secondary" type="button" id="togglePassword">
                                        <i class="fas fa-eye"></i>
                                    </button>
                                </div>
                                <?php if (!empty($err2)): ?>
                                <div class="text-danger mt-1"><?php echo $err2; ?></div>
                                <?php endif; ?>
                            </div>
                            
                            <div class="d-flex justify-content-between align-items-center mb-3">
                                <div class="form-check">
                                    <input type="checkbox" class="form-check-input" id="remember" name="remember" 
                                           <?php echo $rememberedLogin ? 'checked' : ''; ?>>
                                    <label class="form-check-label" for="remember">Nhớ thông tin</label>
                                </div>
                                <a href="#" class="text-primary">Quên mật khẩu?</a>
                            </div>
                            
                            <!-- Lưu URL chuyển hướng -->
                            <input type="hidden" name="url" value="<?php echo htmlspecialchars($url); ?>">
                            
                            <div class="d-grid gap-2">
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-sign-in-alt me-2"></i>Đăng nhập
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Hiệu ứng hiển thị/ẩn mật khẩu
    const togglePassword = document.getElementById('togglePassword');
    const password = document.getElementById('MatKhau');
    
    if (togglePassword && password) {
        togglePassword.addEventListener('click', function() {
            const type = password.getAttribute('type') === 'password' ? 'text' : 'password';
            password.setAttribute('type', type);
            
            // Thay đổi icon
            const icon = this.querySelector('i');
            icon.classList.toggle('fa-eye');
            icon.classList.toggle('fa-eye-slash');
        });
    }
});
</script>

<?php
// Lấy nội dung đã buffer để đưa vào layout
$contentForLayout = ob_get_clean();
// Thêm dòng này để load layout
require_once __DIR__ . '/../Shared/_LayoutAdmin.php';
?>
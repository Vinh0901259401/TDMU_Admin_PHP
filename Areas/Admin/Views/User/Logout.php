<?php
// filepath: c:\xampp\htdocs\TDMU_website\Areas\Admin\Views\User\Logout.php
// Bắt đầu phiên làm việc để có thể truy cập session
session_start();

// Xoá toàn bộ session
unset($_SESSION['TaiKhoan']);

// Huỷ toàn bộ session
session_destroy();

// Log quá trình đăng xuất
error_log("Đã đăng xuất và xoá session");

// Chuyển hướng về trang đăng nhập
header('Location: /TDMU_website/Areas/Admin/Views/User/Login.php');
exit();
?>
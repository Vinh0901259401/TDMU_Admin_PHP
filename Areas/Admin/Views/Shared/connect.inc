<?php
// Thông số kết nối database
$host = "localhost";  // Máy chủ MySQL
$dbname = "tdmu_admin"; // Tên database
$username = "root";   // Username mặc định của XAMPP
$password = "";       // Password mặc định của XAMPP thường là rỗng

try {
    // Tạo kết nối PDO
    $conn = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password);
    
    // Thiết lập chế độ báo lỗi
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Thiết lập chế độ fetch mặc định (array associative)
    $conn->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    
} catch(PDOException $e) {
    die("Kết nối đến cơ sở dữ liệu thất bại: " . $e->getMessage());
}
?>
<?php
// Kết nối database và bắt đầu session
require_once("../Shared/connect.inc");
session_start();

// Set content type to JSON
header('Content-Type: application/json');

// Kiểm tra người dùng đã đăng nhập chưa
if (!isset($_SESSION['TaiKhoan'])) {
    echo json_encode(['success' => false, 'message' => 'Bạn cần đăng nhập để trả lời bình luận.']);
    exit;
}

// Kiểm tra dữ liệu POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_POST['parentCommentId']) || 
    !isset($_POST['content']) || !isset($_POST['questionId'])) {
    echo json_encode(['success' => false, 'message' => 'Dữ liệu không hợp lệ.']);
    exit;
}

$parentCommentId = $_POST['parentCommentId'];
$content = trim($_POST['content']);
$questionId = $_POST['questionId'];
$userId = $_SESSION['TaiKhoan']['ID'];

// Validate dữ liệu
if (empty($content)) {
    echo json_encode(['success' => false, 'message' => 'Nội dung phản hồi không được để trống.']);
    exit;
}

try {
    // Tạo ID mới cho phản hồi
    $stmtNewId = $conn->prepare("SELECT MAX(CAST(SUBSTRING(ID, 3) AS UNSIGNED)) as max_id FROM cauhoi_nguoitraloi_nguoitraloi");
    $stmtNewId->execute();
    $result = $stmtNewId->fetch(PDO::FETCH_ASSOC);
    $nextId = (int)($result['max_id'] ?? 0) + 1;
    $newReplyId = 'PH' . str_pad($nextId, 8, '0', STR_PAD_LEFT);
    
    // Thêm phản hồi mới
    $stmt = $conn->prepare("
        INSERT INTO cauhoi_nguoitraloi_nguoitraloi 
        (ID, IDCauHoi_NguoiTraLoi, IDNguoiTraLoi, NoiDung, NgayTraLoi)
        VALUES (?, ?, ?, ?, NOW())
    ");
    $stmt->execute([$newReplyId, $parentCommentId, $userId, $content]);
    
    echo json_encode(['success' => true, 'message' => 'Phản hồi đã được thêm thành công.']);
    
} catch (PDOException $e) {
    error_log("Lỗi khi thêm phản hồi: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Đã xảy ra lỗi khi thêm phản hồi: ' . $e->getMessage()]);
}
?>
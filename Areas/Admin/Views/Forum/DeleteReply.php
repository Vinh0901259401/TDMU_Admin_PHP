<?php
// Kết nối database và bắt đầu session
require_once("../Shared/connect.inc");
session_start();

// Set content type to JSON
header('Content-Type: application/json');

// Kiểm tra người dùng đã đăng nhập chưa
if (!isset($_SESSION['TaiKhoan'])) {
    echo json_encode(['success' => false, 'message' => 'Bạn cần đăng nhập để thực hiện thao tác này.']);
    exit;
}

// Kiểm tra quyền người dùng
$currentUser = $_SESSION['TaiKhoan'];
$canModerate = false;

if (isset($currentUser['IDQuyenTruyCap'])) {
    try {
        $stmt = $conn->prepare("SELECT CapDo FROM quyentruycap WHERE ID = ?");
        $stmt->execute([$currentUser['IDQuyenTruyCap']]);
        
        if ($stmt->rowCount() > 0) {
            $quyen = $stmt->fetch(PDO::FETCH_ASSOC);
            // Admin hoặc moderator có thể xóa phản hồi (cấp độ 1, 2)
            $canModerate = ($quyen['CapDo'] <= 2);
        }
    } catch (PDOException $e) {
        error_log("Lỗi kiểm tra quyền: " . $e->getMessage());
    }
}

// Kiểm tra dữ liệu POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_POST['replyId'])) {
    echo json_encode(['success' => false, 'message' => 'Dữ liệu không hợp lệ.']);
    exit;
}

$replyId = $_POST['replyId'];
$userId = $currentUser['ID'];

try {
    // Kiểm tra xem phản hồi có tồn tại không và thuộc về người dùng hoặc người có quyền xóa không
    $stmtCheck = $conn->prepare("SELECT IDNguoiTraLoi FROM cauhoi_nguoitraloi_nguoitraloi WHERE ID = ?");
    $stmtCheck->execute([$replyId]);
    
    if ($stmtCheck->rowCount() == 0) {
        echo json_encode(['success' => false, 'message' => 'Không tìm thấy phản hồi.']);
        exit;
    }
    
    $reply = $stmtCheck->fetch(PDO::FETCH_ASSOC);
    
    // Kiểm tra quyền xóa
    if (!$canModerate && $reply['IDNguoiTraLoi'] != $userId) {
        echo json_encode(['success' => false, 'message' => 'Bạn không có quyền xóa phản hồi này.']);
        exit;
    }
    
    // Thực hiện xóa phản hồi
    $stmtDelete = $conn->prepare("DELETE FROM cauhoi_nguoitraloi_nguoitraloi WHERE ID = ?");
    $stmtDelete->execute([$replyId]);
    
    echo json_encode(['success' => true, 'message' => 'Phản hồi đã được xóa thành công.']);
    
} catch (PDOException $e) {
    error_log("Lỗi khi xóa phản hồi: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Đã xảy ra lỗi khi xóa phản hồi.']);
}
?>
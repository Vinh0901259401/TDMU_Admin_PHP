<?php
// Kết nối database và bắt đầu session
require_once("../Shared/connect.inc");
session_start();

// XÓA TẤT CẢ OUTPUT BUFFER HIỆN TẠI
if (ob_get_level()) ob_end_clean();

// Set content type to JSON
header('Content-Type: application/json');

// Kiểm tra người dùng đã đăng nhập chưa
if (!isset($_SESSION['TaiKhoan'])) {
    echo json_encode(['success' => false, 'message' => 'Bạn cần đăng nhập để bình luận.']);
    exit;
}

// Kiểm tra dữ liệu POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_POST['questionId']) || !isset($_POST['commentContent'])) {
    echo json_encode(['success' => false, 'message' => 'Dữ liệu không hợp lệ.']);
    exit;
}

$questionId = $_POST['questionId'];
$commentContent = trim($_POST['commentContent']);
$userId = $_SESSION['TaiKhoan']['ID'];

// Validate dữ liệu
if (empty($commentContent)) {
    echo json_encode(['success' => false, 'message' => 'Nội dung bình luận không được để trống.']);
    exit;
}

try {
    // Tạo ID mới cho bình luận
    $stmtNewId = $conn->prepare("SELECT MAX(CAST(SUBSTRING(ID, 3) AS UNSIGNED)) as max_id FROM cauhoi_nguoitraloi");
    $stmtNewId->execute();
    $result = $stmtNewId->fetch(PDO::FETCH_ASSOC);
    $nextId = (int)($result['max_id'] ?? 0) + 1;
    $newCommentId = 'TL' . str_pad($nextId, 8, '0', STR_PAD_LEFT);
    
    // Thêm bình luận mới
    $stmt = $conn->prepare("
        INSERT INTO cauhoi_nguoitraloi (ID, IDCauHoi, IDNguoiTraLoi, NoiDung, NgayTraLoi)
        VALUES (?, ?, ?, ?, NOW())
    ");
    $stmt->execute([$newCommentId, $questionId, $userId, $commentContent]);
    
    echo json_encode(['success' => true, 'message' => 'Bình luận đã được thêm thành công.']);
    
} catch (PDOException $e) {
    error_log("Lỗi khi thêm bình luận: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Đã xảy ra lỗi khi thêm bình luận: ' . $e->getMessage()]);
}
exit; // Đảm bảo không có output nào thêm sau này
?>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/toastr.js/latest/toastr.min.js"></script>

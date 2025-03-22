<?php
// Kết nối đến database
require_once("../Shared/connect.inc");
session_start();

// Set header để trả về JSON
header('Content-Type: application/json');

// Kiểm tra đăng nhập
if (!isset($_SESSION['TaiKhoan'])) {
    echo json_encode([
        'code' => 403,
        'msg' => 'Bạn chưa đăng nhập!'
    ]);
    exit;
}

// Kiểm tra quyền truy cập
$tk = $_SESSION['TaiKhoan'];
$accessLevel = 0;

if (isset($tk['IDQuyenTruyCap'])) {
    try {
        $stmt = $conn->prepare("SELECT CapDo FROM quyentruycap WHERE ID = ?");
        $stmt->execute([$tk['IDQuyenTruyCap']]);
        
        if ($stmt->rowCount() > 0) {
            $quyen = $stmt->fetch(PDO::FETCH_ASSOC);
            $accessLevel = $quyen['CapDo'];
        }
    } catch (PDOException $e) {
        error_log("Lỗi truy vấn quyền truy cập: " . $e->getMessage());
    }
}

// Kiểm tra quyền admin/quản trị (cấp độ 1 hoặc 2)
if ($accessLevel > 2) {
    echo json_encode([
        'code' => 403,
        'msg' => 'Bạn không có quyền thực hiện thao tác này!'
    ]);
    exit;
}

// Kiểm tra request
if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_POST['maCH'])) {
    echo json_encode([
        'code' => 400,
        'msg' => 'Dữ liệu không hợp lệ!'
    ]);
    exit;
}

$maCH = $_POST['maCH'];

try {
    // Bắt đầu transaction
    $conn->beginTransaction();
    
    // Lấy thông tin câu hỏi và người đăng
    $stmtInfo = $conn->prepare("
        SELECT ch.IDNguoiGui, ch.TieuDe, tk.HoTen 
        FROM cauhoi ch
        JOIN taikhoan tk ON ch.IDNguoiGui = tk.ID
        WHERE ch.ID = ?
    ");
    $stmtInfo->execute([$maCH]);
    
    if ($stmtInfo->rowCount() == 0) {
        $conn->rollBack();
        echo json_encode([
            'code' => 404,
            'msg' => 'Không tìm thấy câu hỏi!'
        ]);
        exit;
    }
    
    $questionInfo = $stmtInfo->fetch(PDO::FETCH_ASSOC);
    $nguoiGui = $questionInfo['IDNguoiGui'];
    $tieuDeCH = $questionInfo['TieuDe'];
    $tenNguoiGui = $questionInfo['HoTen'];
    
    // Cập nhật trạng thái duyệt
    $stmtUpdate = $conn->prepare("UPDATE cauhoi SET DuocDuyet = 1 WHERE ID = ?");
    $stmtUpdate->execute([$maCH]);
    
    // Tạo thông báo
    // 1. Tạo ID mới cho thông báo
    $stmtNewId = $conn->prepare("SELECT MAX(CAST(SUBSTRING(ID, 3) AS UNSIGNED)) as max_id FROM thongbao");
    $stmtNewId->execute();
    $result = $stmtNewId->fetch(PDO::FETCH_ASSOC);
    $nextId = (int)($result['max_id'] ?? 0) + 1;
    $newNotificationId = 'TB' . str_pad($nextId, 8, '0', STR_PAD_LEFT);
    
    // 2. Thêm thông báo mới
    $tieuDe = "Câu hỏi của bạn đã được phê duyệt";
    $noiDung = "Câu hỏi '{$tieuDeCH}' của bạn đã được phê duyệt và hiển thị trên diễn đàn.";
    
    $stmtInsertNotification = $conn->prepare("
        INSERT INTO thongbao 
        (ID, IDLoaiThongBao, TieuDe, NoiDung, GhiChu, IDNguoiTao, NgayTao)
        VALUES (?, ?, ?, ?, NULL, ?, NOW())
    ");
    $stmtInsertNotification->execute([
        $newNotificationId,
        'LTB0000000003', // Loại thông báo từ diễn đàn
        $tieuDe,
        $noiDung,
        $tk['ID'] // ID của người quản trị đang phê duyệt
    ]);
    
    // 3. Tạo ID mới cho liên kết người nhận
    $stmtNewRecipientId = $conn->prepare("SELECT MAX(CAST(SUBSTRING(ID, 5) AS UNSIGNED)) as max_id FROM thongbao_nguoinhan");
    $stmtNewRecipientId->execute();
    $result = $stmtNewRecipientId->fetch(PDO::FETCH_ASSOC);
    $nextRecipientId = (int)($result['max_id'] ?? 0) + 1;
    $newRecipientLinkId = 'TBNN' . str_pad($nextRecipientId, 6, '0', STR_PAD_LEFT);
    
    // 4. Thêm liên kết người nhận
    $stmtInsertRecipient = $conn->prepare("
        INSERT INTO thongbao_nguoinhan
        (ID, IDThongBao, IDNguoiNhan, NgayNhan, GhiChu)
        VALUES (?, ?, ?, NULL, NULL)
    ");
    $stmtInsertRecipient->execute([
        $newRecipientLinkId,
        $newNotificationId,
        $nguoiGui
    ]);
    
    // Hoàn thành transaction
    $conn->commit();
    
    echo json_encode([
        'code' => 200,
        'msg' => 'Câu hỏi đã được phê duyệt thành công và đã gửi thông báo cho ' . $tenNguoiGui
    ]);
    
} catch (PDOException $e) {
    // Rollback nếu có lỗi
    if ($conn->inTransaction()) {
        $conn->rollBack();
    }
    
    error_log("Lỗi phê duyệt câu hỏi: " . $e->getMessage());
    
    echo json_encode([
        'code' => 500,
        'msg' => 'Đã xảy ra lỗi khi phê duyệt câu hỏi: ' . $e->getMessage()
    ]);
}
?>
<?php
require_once("../Shared/connect.inc");

// Đảm bảo phản hồi luôn là JSON
header('Content-Type: application/json');

// Kiểm tra phiên đăng nhập
session_start();
$tk = isset($_SESSION['TaiKhoan']) ? $_SESSION['TaiKhoan'] : null;
$isLecturer = false;

// Kiểm tra quyền truy cập giảng viên
if ($tk && isset($tk['IDQuyenTruyCap'])) {
    $stmt = $conn->prepare("SELECT CapDo FROM quyentruycap WHERE ID = ?");
    $stmt->execute([$tk['IDQuyenTruyCap']]);
    if ($stmt->rowCount() > 0) {
        $quyen = $stmt->fetch(PDO::FETCH_ASSOC);
        $isLecturer = ($quyen['CapDo'] >= 1 && $quyen['CapDo'] <= 3);
    }
}

// Chỉ xử lý nếu là giảng viên
if (!$isLecturer) {
    echo json_encode(['hasGrade' => false, 'error' => 'Không có quyền truy cập']);
    exit;
}

// Lấy dữ liệu từ request
$idSinhVien = $_GET['idSinhVien'] ?? '';
$idNhomMonHoc = $_GET['idNhomMonHoc'] ?? '';

// Kiểm tra tham số đầu vào
if (empty($idSinhVien)) {
    echo json_encode(['hasGrade' => false, 'error' => 'Thiếu thông tin sinh viên']);
    exit;
}

try {
    // Truy vấn điểm sinh viên theo idSinhVien và idNhomMonHoc hoặc idMonHoc
    $whereClause = "";
    $params = [$idSinhVien];
    
    if (!empty($idNhomMonHoc)) {
        $whereClause = "bd.IDNhomMonHoc = ?";
        $params[] = $idNhomMonHoc;
    } else {
        // Fallback nếu không có idNhomMonHoc
        $idMonHoc = $_GET['idMonHoc'] ?? '';
        if (empty($idMonHoc)) {
            echo json_encode(['hasGrade' => false, 'error' => 'Thiếu thông tin nhóm môn học và môn học']);
            exit;
        }
        
        $whereClause = "bd.IDMonHoc = ?";
        $params[] = $idMonHoc;
    }
    
    $query = "
        SELECT 
            bd.ID,
            bd.DiemChuyenCan,
            bd.DiemKiemTra,
            bd.DiemThi,
            bd.DiemTongKet,
            bd.DiemChu,
            bd.DiemHe4,
            bd.KetQua,
            bd.GhiChu,
            bd.IDMonHoc,
            bd.IDNhomMonHoc
        FROM bangdiem bd
        WHERE bd.IDSinhVien = ? AND $whereClause
        LIMIT 1
    ";
    
    $stmt = $conn->prepare($query);
    $stmt->execute($params);
    
    if ($stmt->rowCount() > 0) {
        // Đã có điểm
        $diem = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // Log để debug
        error_log("CheckStudentGrade: Tìm thấy điểm cho SV: $idSinhVien, NhomMH: " . ($idNhomMonHoc ?: 'N/A'));
        
        echo json_encode([
            'hasGrade' => true,
            'diem' => $diem
        ]);
    } else {
        // Chưa có điểm
        error_log("CheckStudentGrade: Không tìm thấy điểm cho SV: $idSinhVien, NhomMH: " . ($idNhomMonHoc ?: 'N/A'));
        
        echo json_encode([
            'hasGrade' => false,
            'message' => 'Sinh viên chưa có điểm cho môn học này'
        ]);
    }
} catch (Exception $e) {
    error_log("Lỗi kiểm tra điểm: " . $e->getMessage());
    echo json_encode([
        'hasGrade' => false,
        'error' => 'Đã xảy ra lỗi khi truy vấn điểm',
        'details' => $e->getMessage()
    ]);
}
?>
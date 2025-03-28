<?php
// filepath: c:\xampp\htdocs\TDMU_website\Areas\Admin\Views\GroupSubject\UpdateStudentStatus.php
require_once("../Shared/connect.inc");

session_start();
$isLecturer = false;
$tk = isset($_SESSION['TaiKhoan']) ? $_SESSION['TaiKhoan'] : null;

// Kiểm tra quyền truy cập
if ($tk && isset($tk['IDQuyenTruyCap'])) {
    $stmt = $conn->prepare("SELECT CapDo FROM quyentruycap WHERE ID = ?");
    $stmt->execute([$tk['IDQuyenTruyCap']]);
    
    if ($stmt->rowCount() > 0) {
        $quyen = $stmt->fetch(PDO::FETCH_ASSOC);
        $isLecturer = ($quyen['CapDo'] == 3);
    }
}

$response = ['success' => false, 'message' => 'Không có quyền truy cập'];

if ($isLecturer && isset($_POST['id']) && isset($_POST['status'])) {
    $id = $_POST['id'];
    $status = $_POST['status'];
    
    try {
        // Kiểm tra sinh viên thuộc nhóm của giảng viên
        $stmt = $conn->prepare("
            SELECT nmsv.ID FROM nhommonhoc_sinhvien nmsv
            JOIN nhommonhoc nmh ON nmsv.IDNhomMonHoc = nmh.ID
            WHERE nmsv.ID = ? AND nmh.IDGiangVien = ?
        ");
        $stmt->execute([$id, $tk['ID']]);
        
        if ($stmt->rowCount() > 0) {
            // Cập nhật trạng thái
            $updateStmt = $conn->prepare("UPDATE nhommonhoc_sinhvien SET TrangThai = ? WHERE ID = ?");
            $updateStmt->execute([$status, $id]);
            
            $response = ['success' => true, 'message' => 'Cập nhật thành công'];
        } else {
            $response = ['success' => false, 'message' => 'Sinh viên không thuộc nhóm môn học của bạn'];
        }
    } catch (PDOException $e) {
        $response = ['success' => false, 'message' => 'Lỗi: ' . $e->getMessage()];
    }
}

// Trả về kết quả dạng JSON
header('Content-Type: application/json');
echo json_encode($response);
?>
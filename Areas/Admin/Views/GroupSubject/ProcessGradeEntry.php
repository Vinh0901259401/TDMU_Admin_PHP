<?php
// Đầu file ProcessGradeEntry.php
ini_set('display_errors', 0); // Tắt hiển thị lỗi trong output
error_reporting(E_ALL); // Vẫn ghi log tất cả lỗi

/**
 * Xử lý nhập điểm sinh viên
 */
require_once("../Shared/connect.inc");

session_start();
$response = ['success' => false, 'message' => 'Không có quyền truy cập'];

// Kiểm tra đăng nhập và quyền giảng viên
$tk = isset($_SESSION['TaiKhoan']) ? $_SESSION['TaiKhoan'] : null;
$isLecturer = false;

if ($tk && isset($tk['IDQuyenTruyCap'])) {
    $stmt = $conn->prepare("SELECT CapDo FROM quyentruycap WHERE ID = ?");
    $stmt->execute([$tk['IDQuyenTruyCap']]);
    if ($stmt->rowCount() > 0) {
        $quyen = $stmt->fetch(PDO::FETCH_ASSOC);
        $isLecturer = ($quyen['CapDo'] >= 1 && $quyen['CapDo'] <= 3);
    }
}

if ($isLecturer && $_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // Lấy dữ liệu từ form
        $idSinhVien = $_POST['idSinhVien'] ?? '';
        $idMonHoc = $_POST['idMonHoc'] ?? '';
        $idHocKy = $_POST['idHocKy'] ?? '';
        $idNamHoc = $_POST['idNamHoc'] ?? '';
        
        // Cho phép các trường điểm là NULL nếu không có dữ liệu
        $diemChuyenCan = isset($_POST['diemChuyenCan']) && $_POST['diemChuyenCan'] !== '' ? floatval($_POST['diemChuyenCan']) : null;
        $diemKiemTra = isset($_POST['diemKiemTra']) && $_POST['diemKiemTra'] !== '' ? floatval($_POST['diemKiemTra']) : null;
        $diemThi = isset($_POST['diemThi']) && $_POST['diemThi'] !== '' ? floatval($_POST['diemThi']) : null;
        $ghiChu = $_POST['ghiChu'] ?? '';
        
        // Lấy ID nhóm môn học từ request trước tiên
        $idNhomMonHoc = $_POST['idNhomMonHoc'] ?? '';

        // Nếu không có idNhomMonHoc, thử tìm từ bảng nhommonhoc_sinhvien
        if (empty($idNhomMonHoc)) {
            $findGroup = $conn->prepare("
                SELECT IDNhomMonHoc 
                FROM nhommonhoc_sinhvien 
                WHERE IDSinhVien = ? AND IDNhomMonHoc IN (
                    SELECT ID FROM nhommonhoc WHERE IDMonHoc = ?
                )
                ORDER BY NgayDangKy DESC
                LIMIT 1
            ");
            $findGroup->execute([$idSinhVien, $idMonHoc]);
            if ($findGroup->rowCount() > 0) {
                $idNhomMonHoc = $findGroup->fetchColumn();
            } else {
                throw new Exception("Không tìm thấy thông tin đăng ký nhóm môn học của sinh viên");
            }
        }
        
        // Kiểm tra dữ liệu đầu vào 
        if (empty($idSinhVien) || empty($idMonHoc) || empty($idNhomMonHoc)) {
            throw new Exception("Thiếu thông tin sinh viên, môn học hoặc nhóm môn học");
        }

        // Kiểm tra xem đã có đủ 3 loại điểm hay chưa để tính điểm tổng kết
        $hasAllScores = ($diemChuyenCan !== null && $diemKiemTra !== null && $diemThi !== null);
        $diemTongKet = null;
        $diemChu = null;
        $diemHe4 = null;
        $ketQua = null;
        
        // Chỉ tính điểm tổng kết và các giá trị liên quan nếu đã có đủ 3 loại điểm
        if ($hasAllScores) {
            $diemTongKet = $diemChuyenCan * 0.1 + $diemKiemTra * 0.3 + $diemThi * 0.6;
            $diemTongKet = round($diemTongKet, 1); // Làm tròn 1 chữ số thập phân
            
            // Tính điểm chữ và điểm hệ 4 dựa trên điểm tổng kết
            if ($diemTongKet >= 8.5) {
                $diemChu = 'A';
                $diemHe4 = 4.0;
            } elseif ($diemTongKet >= 8.0) {
                $diemChu = 'B+';
                $diemHe4 = 3.5;
            } elseif ($diemTongKet >= 7.0) {
                $diemChu = 'B';
                $diemHe4 = 3.0;
            } elseif ($diemTongKet >= 6.5) {
                $diemChu = 'C+';
                $diemHe4 = 2.5;
            } elseif ($diemTongKet >= 5.5) {
                $diemChu = 'C';
                $diemHe4 = 2.0;
            } elseif ($diemTongKet >= 5.0) {
                $diemChu = 'D+';
                $diemHe4 = 1.5;
            } elseif ($diemTongKet >= 4.0) {
                $diemChu = 'D';
                $diemHe4 = 1.0;
            } else {
                $diemChu = 'F';
                $diemHe4 = 0.0;
            }
            
            // Tính kết quả (đậu/rớt)
            $ketQua = ($diemHe4 >= 1.0) ? 1 : 2; // 1: Đậu, 2: Rớt
        }
        
        // Debug log
        error_log("Nhập điểm cho SV: $idSinhVien, Nhóm: $idNhomMonHoc, Điểm: CC=" . 
                 ($diemChuyenCan ?? 'NULL') . ", KT=" . ($diemKiemTra ?? 'NULL') . 
                 ", Thi=" . ($diemThi ?? 'NULL') . ", Tổng kết=" . ($diemTongKet ?? 'NULL'));
        
        // Kiểm tra xem đã có bảng điểm chưa - tìm theo IDSinhVien và IDNhomMonHoc
        $checkQuery = "SELECT ID FROM bangdiem WHERE IDSinhVien = ? AND IDNhomMonHoc = ?";
        $checkStmt = $conn->prepare($checkQuery);
        $checkStmt->execute([$idSinhVien, $idNhomMonHoc]);
        
        if ($checkStmt->rowCount() > 0) {
            // Nếu đã có điểm, cập nhật
            $diemID = $checkStmt->fetchColumn();
            
            $updateQuery = "
                UPDATE bangdiem SET 
                    DiemChuyenCan = ?,
                    DiemKiemTra = ?,
                    DiemThi = ?,
                    DiemTongKet = ?,
                    GhiChu = ?,
                    KetQua = ?,
                    DiemChu = ?,
                    DiemHe4 = ?,
                    NgayNhap = NOW()
                WHERE ID = ?
            ";
            
            $updateStmt = $conn->prepare($updateQuery);
            $updateStmt->execute([
                $diemChuyenCan,
                $diemKiemTra,
                $diemThi,
                $diemTongKet,
                $ghiChu,
                $ketQua,
                $diemChu,
                $diemHe4,
                $diemID
            ]);
            
            $response = ['success' => true, 'message' => 'Cập nhật điểm thành công'];
        } else {
            // Nếu không tìm thấy theo IDNhomMonHoc, thử tìm theo IDMonHoc
            $checkQuery = "SELECT ID FROM bangdiem WHERE IDSinhVien = ? AND IDMonHoc = ?";
            $checkStmt = $conn->prepare($checkQuery);
            $checkStmt->execute([$idSinhVien, $idMonHoc]);
            
            if ($checkStmt->rowCount() > 0) {
                // Cập nhật cả IDNhomMonHoc nếu tìm thấy theo IDMonHoc
                $diemID = $checkStmt->fetchColumn();
                
                $updateQuery = "
                    UPDATE bangdiem SET 
                        DiemChuyenCan = ?,
                        DiemKiemTra = ?,
                        DiemThi = ?,
                        DiemTongKet = ?,
                        GhiChu = ?,
                        KetQua = ?,
                        DiemChu = ?,
                        DiemHe4 = ?,
                        IDNhomMonHoc = ?,
                        NgayNhap = NOW()
                    WHERE ID = ?
                ";
                
                $updateStmt = $conn->prepare($updateQuery);
                $updateStmt->execute([
                    $diemChuyenCan,
                    $diemKiemTra,
                    $diemThi,
                    $diemTongKet,
                    $ghiChu,
                    $ketQua,
                    $diemChu,
                    $diemHe4,
                    $idNhomMonHoc,
                    $diemID
                ]);
                
                $response = ['success' => true, 'message' => 'Cập nhật điểm thành công'];
            } else {
                // Không tìm thấy bảng điểm - báo lỗi thay vì tạo mới
                throw new Exception("Không tìm thấy thông tin bảng điểm. Vui lòng kiểm tra lại thông tin đăng ký môn học.");
            }
        }

        // Cập nhật trạng thái sinh viên trong nhóm môn học nếu đã nhập đủ 3 loại điểm
        // Đánh dấu hoàn thành bất kể đậu hay rớt
        if ($hasAllScores && !empty($idNhomMonHoc)) {
            $updateNMHSVQuery = "UPDATE nhommonhoc_sinhvien SET TrangThai = 2 WHERE IDSinhVien = ? AND IDNhomMonHoc = ?";
            $updateNMHSVStmt = $conn->prepare($updateNMHSVQuery);
            $updateNMHSVStmt->execute([$idSinhVien, $idNhomMonHoc]);
            error_log("Cập nhật trạng thái SV: $idSinhVien, Nhóm: $idNhomMonHoc, Trạng thái: 2 (Hoàn thành)");
        } else {
            error_log("Không cập nhật trạng thái SV: hasAllScores=$hasAllScores, idNhomMonHoc=$idNhomMonHoc");
        }
        
    } catch (Exception $e) {
        $response = ['success' => false, 'message' => $e->getMessage()];
    }
}

// Trả về phản hồi dạng JSON
header('Content-Type: application/json');
ob_clean(); // Xóa bất kỳ output nào trước đó
echo json_encode($response);
?>
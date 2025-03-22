<?php
ob_start();
session_start();
require_once("../Shared/connect.inc");

// Debug mode - bật lên khi cần debug
$debugMode = false;
if ($debugMode) {
    ini_set('display_errors', 1);
    ini_set('display_startup_errors', 1);
    error_reporting(E_ALL);
}

// Kiểm tra đăng nhập
if (!isset($_SESSION['TaiKhoan'])) {
    header("Location: ../../Auth/login.php");
    exit;
}

$tk = $_SESSION['TaiKhoan'];

// Kiểm tra xem có phải là sinh viên
if ($tk['IDQuyenTruyCap'] != 'QTC0000000005') {
    header("Location: ../Dashboard/index.php");
    exit;
}

// Kiểm tra ID chi tiết đăng ký
if (!isset($_GET['id']) || empty($_GET['id'])) {
    $_SESSION['error_message'] = "Không tìm thấy thông tin đăng ký cần hủy.";
    header("Location: MyRegistrations.php");
    exit;
}

$chiTietId = $_GET['id'];
$redirectUrl = isset($_GET['redirect']) ? $_GET['redirect'] : 'MyRegistrations.php';

// Kiểm tra xem đây có phải chi tiết đăng ký của sinh viên này không
try {
    $stmtCheckOwner = $conn->prepare("
        SELECT ctdk.ID, ctdk.IDDangKy, ctdk.IDNhomMonHoc, dk.IDSinhVien, nmh.IDMonHoc,
               mh.TenMonHoc, nmh.MaNhom
        FROM chitiet_dangkymonhoc ctdk
        JOIN dangkymonhoc dk ON ctdk.IDDangKy = dk.ID
        JOIN nhommonhoc nmh ON ctdk.IDNhomMonHoc = nmh.ID
        JOIN monhoc mh ON nmh.IDMonHoc = mh.ID
        WHERE ctdk.ID = ? AND dk.IDSinhVien = ? AND ctdk.TrangThai = 1
    ");
    $stmtCheckOwner->execute([$chiTietId, $tk['ID']]);
    $dangKyInfo = $stmtCheckOwner->fetch(PDO::FETCH_ASSOC);
    
    if (!$dangKyInfo) {
        $_SESSION['error_message'] = "Không tìm thấy thông tin đăng ký hoặc bạn không có quyền hủy đăng ký này.";
        header("Location: " . $redirectUrl);
        exit;
    }
} catch (PDOException $e) {
    $_SESSION['error_message'] = "Lỗi khi kiểm tra thông tin đăng ký: " . $e->getMessage();
    header("Location: " . $redirectUrl);
    exit;
}

// Kiểm tra xem có trong thời gian đăng ký không
try {
    // Đơn giản hóa điều kiện - chỉ cần có bất kỳ đợt đăng ký nào đang mở
    $stmtCheckDot = $conn->prepare("
        SELECT ID
        FROM dotdangky
        WHERE TrangThai = 1 
        AND NOW() BETWEEN ThoiGianBatDau AND ThoiGianKetThuc
        LIMIT 1
    ");
    $stmtCheckDot->execute();
    $dotInfo = $stmtCheckDot->fetch(PDO::FETCH_ASSOC);
    
    if (!$dotInfo) {
        $_SESSION['error_message'] = "Hiện tại không trong thời gian cho phép đăng ký hoặc hủy đăng ký môn học.";
        header("Location: " . $redirectUrl);
        exit;
    }
} catch (PDOException $e) {
    $_SESSION['error_message'] = "Lỗi khi kiểm tra thời gian đăng ký: " . $e->getMessage();
    header("Location: " . $redirectUrl);
    exit;
}

// Xử lý hủy đăng ký ngay lập tức nếu từ trang Index.php chuyển đến
$fromIndex = (strpos($redirectUrl, 'Index.php') !== false);

// Xác nhận hủy đăng ký
if ($_SERVER['REQUEST_METHOD'] === 'POST' || $fromIndex) {
    if ((isset($_POST['confirm_delete']) && $_POST['confirm_delete'] === 'yes') || $fromIndex) {
        try {
            // Bắt đầu transaction
            $conn->beginTransaction();
            
            // Lấy thông tin cần thiết trước khi xóa
            $stmtInfo = $conn->prepare("
                SELECT ctdk.SoTinChiDangKy, ctdk.IDNhomMonHoc, dk.ID as DangKyID, nmh.IDMonHoc
                FROM chitiet_dangkymonhoc ctdk
                JOIN dangkymonhoc dk ON ctdk.IDDangKy = dk.ID
                JOIN nhommonhoc nmh ON ctdk.IDNhomMonHoc = nmh.ID
                WHERE ctdk.ID = ?
            ");
            $stmtInfo->execute([$chiTietId]);
            $info = $stmtInfo->fetch(PDO::FETCH_ASSOC);
            
            // 1. Cập nhật trạng thái chi tiết đăng ký thành đã hủy
            $stmtUpdateCTDK = $conn->prepare("
                UPDATE chitiet_dangkymonhoc
                SET TrangThai = 0
                WHERE ID = ?
            ");
            $stmtUpdateCTDK->execute([$chiTietId]);
            
            // 2. Cập nhật tổng số tín chỉ trong bản ghi đăng ký
            $stmtUpdateDK = $conn->prepare("
                UPDATE dangkymonhoc
                SET TongTinChi = IF(TongTinChi >= ?, TongTinChi - ?, TongTinChi)
                WHERE ID = ?
            ");
            $stmtUpdateDK->execute([$info['SoTinChiDangKy'], $info['SoTinChiDangKy'], $info['DangKyID']]);
            
            // 3. Xử lý bảng nhommonhoc_sinhvien
            try {
                // Thử xóa bản ghi trước
                $stmtDeleteNMS = $conn->prepare("
                    DELETE FROM nhommonhoc_sinhvien
                    WHERE IDNhomMonHoc = ? AND IDSinhVien = ?
                ");
                $stmtDeleteNMS->execute([$info['IDNhomMonHoc'], $tk['ID']]);
            } catch (PDOException $e) {
                // Nếu không xóa được, thử cập nhật trạng thái
                try {
                    $stmtCheckNMS = $conn->prepare("
                        SELECT COUNT(*) 
                        FROM INFORMATION_SCHEMA.COLUMNS 
                        WHERE TABLE_SCHEMA = DATABASE() 
                        AND TABLE_NAME = 'nhommonhoc_sinhvien' 
                        AND COLUMN_NAME = 'TrangThai'
                    ");
                    $stmtCheckNMS->execute();
                    $hasTrangThaiColumn = $stmtCheckNMS->fetchColumn() > 0;
                    
                    if ($hasTrangThaiColumn) {
                        $stmtUpdateNMS = $conn->prepare("
                            UPDATE nhommonhoc_sinhvien
                            SET TrangThai = 0
                            WHERE IDNhomMonHoc = ? AND IDSinhVien = ?
                        ");
                        $stmtUpdateNMS->execute([$info['IDNhomMonHoc'], $tk['ID']]);
                    }
                } catch (PDOException $e2) {
                    // Bỏ qua lỗi này để tiếp tục xử lý
                    error_log("Không thể cập nhật nhommonhoc_sinhvien: " . $e2->getMessage());
                }
            }
            
            // 4. Cập nhật số lượng đã đăng ký trong nhóm môn học
            try {
                $stmtUpdateSoLuong = $conn->prepare("
                    UPDATE nhommonhoc 
                    SET SoLuongDaDangKy = IF(SoLuongDaDangKy > 0, SoLuongDaDangKy - 1, 0)
                    WHERE ID = ?
                ");
                $stmtUpdateSoLuong->execute([$info['IDNhomMonHoc']]);
            } catch (PDOException $e) {
                // Bỏ qua lỗi này để tiếp tục xử lý
                error_log("Không thể cập nhật SoLuongDaDangKy: " . $e->getMessage());
            }
            
            // 5. XÓA bảng điểm thay vì cập nhật ghi chú
            try {
                // Tìm ID của bảng điểm cần xóa
                $stmtFindBangDiem = $conn->prepare("
                    SELECT ID
                    FROM bangdiem
                    WHERE IDSinhVien = ? AND IDMonHoc = ? AND KetQua = 0
                    ORDER BY NgayNhap DESC, LanHoc DESC
                    LIMIT 1
                ");
                $stmtFindBangDiem->execute([$tk['ID'], $info['IDMonHoc']]);
                $bangDiemID = $stmtFindBangDiem->fetchColumn();
                
                // Nếu tìm thấy bảng điểm, tiến hành xóa
                if ($bangDiemID) {
                    $stmtDeleteBangDiem = $conn->prepare("
                        DELETE FROM bangdiem
                        WHERE ID = ?
                    ");
                    $stmtDeleteBangDiem->execute([$bangDiemID]);
                }
            } catch (PDOException $e) {
                // Bỏ qua lỗi này để tiếp tục xử lý
                error_log("Không thể xóa bangdiem: " . $e->getMessage());
            }
            
            // Commit transaction
            $conn->commit();
            
            $_SESSION['success_message'] = "Hủy đăng ký môn học thành công!";
            header("Location: " . $redirectUrl);
            exit;
            
        } catch (PDOException $e) {
            // Rollback nếu có lỗi
            if ($conn->inTransaction()) {
                $conn->rollBack();
            }
            
            if ($debugMode) {
                error_log("Lỗi khi hủy đăng ký: " . $e->getMessage());
                error_log("Stack trace: " . $e->getTraceAsString());
            }
            
            $_SESSION['error_message'] = "Lỗi khi hủy đăng ký: " . $e->getMessage();
            header("Location: " . $redirectUrl);
            exit;
        }
    } else {
        // Người dùng không xác nhận xóa
        header("Location: " . $redirectUrl);
        exit;
    }
}

// Nếu từ trang Index.php gọi đến, xử lý luôn không cần hiển thị trang xác nhận
if ($fromIndex) {
    // Đã xử lý ở trên, không cần làm gì thêm
    exit;
}

// Hiển thị trang xác nhận xóa
?>

<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Xác nhận hủy đăng ký</title>
    <style>
        .confirmation-box {
            max-width: 600px;
            margin: 30px auto;
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0 0.125rem 0.25rem rgba(0, 0, 0, 0.075);
            background-color: #fff;
        }
        .course-info {
            background-color: #f8f9fa;
            padding: 15px;
            margin-bottom: 20px;
            border-radius: 5px;
            border-left: 4px solid #dc3545;
        }
        .btn-container {
            display: flex;
            justify-content: space-between;
            margin-top: 20px;
        }
    </style>
</head>
<body>
    <div class="container-fluid py-4">
        <div class="confirmation-box">
            <h3 class="mb-4 text-center">Xác nhận hủy đăng ký môn học</h3>
            
            <div class="alert alert-warning">
                <i class="fas fa-exclamation-triangle me-2"></i>
                Bạn có chắc chắn muốn hủy đăng ký môn học này không?
            </div>
            
            <div class="course-info">
                <h5><?php echo htmlspecialchars($dangKyInfo['TenMonHoc']); ?></h5>
                <p class="mb-1">Nhóm: <strong><?php echo htmlspecialchars($dangKyInfo['MaNhom']); ?></strong></p>
                <p class="text-danger mb-0">
                    <i class="fas fa-exclamation-circle me-2"></i>
                    Lưu ý: Sau khi hủy đăng ký, bạn có thể đăng ký lại môn học này nếu còn trong thời gian đăng ký!
                </p>
            </div>
            
            <form method="post">
                <div class="btn-container">
                    <a href="<?php echo $redirectUrl; ?>" class="btn btn-secondary">
                        <i class="fas fa-times me-1"></i> Hủy bỏ
                    </a>
                    <button type="submit" name="confirm_delete" value="yes" class="btn btn-danger">
                        <i class="fas fa-trash-alt me-1"></i> Xác nhận hủy đăng ký
                    </button>
                </div>
            </form>
        </div>
    </div>
</body>
</html>

<?php
$contentForLayout = ob_get_clean();

// Include layout
include("../Shared/_LayoutAdmin.php");
?>
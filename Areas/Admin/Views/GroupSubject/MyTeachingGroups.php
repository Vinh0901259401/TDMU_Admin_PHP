<?php
/**
 * Trang Quản Lý Nhóm Môn Học Giảng Dạy
 * Chức năng: Hiển thị, tìm kiếm và lọc các nhóm môn học do giảng viên đang đăng nhập phụ trách
 */

// Kết nối database và các hàm tiện ích
require_once("../Shared/connect.inc");

// Tiêu đề trang
$pageTitle = "Nhóm môn học giảng dạy";

// Khởi tạo session và kiểm tra đăng nhập
session_start();
$accessLevel = 0;
$isLecturer = false;
$tk = isset($_SESSION['TaiKhoan']) ? $_SESSION['TaiKhoan'] : null;

// Kiểm tra quyền truy cập người dùng (Cấp độ 3-4 - Giảng viên/Trợ giảng mới được truy cập)
if ($tk && isset($tk['IDQuyenTruyCap'])) {
    try {
        $stmt = $conn->prepare("SELECT CapDo FROM quyentruycap WHERE ID = ?");
        $stmt->execute([$tk['IDQuyenTruyCap']]);
        
        if ($stmt->rowCount() > 0) {
            $quyen = $stmt->fetch(PDO::FETCH_ASSOC);
            $accessLevel = $quyen['CapDo'];
            
            // Kiểm tra nếu là giảng viên (Cấp độ 3)
            if ($accessLevel == 3) {
                $isLecturer = true;
            }
            if ($accessLevel == 1) {
                $isLecturer = true;
            }
        }
    } catch (PDOException $e) {
        error_log("Lỗi truy vấn quyền truy cập: " . $e->getMessage());
    }
}

// Xử lý tham số tìm kiếm và lọc
$maHocKy = isset($_GET['MaHocKy']) ? $_GET['MaHocKy'] : '';
$maNamHoc = isset($_GET['MaNamHoc']) ? $_GET['MaNamHoc'] : '';
$search = isset($_GET['strSearch']) ? trim($_GET['strSearch']) : '';

// Cấu hình phân trang
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$recordsPerPage = 8; // Hiển thị 8 nhóm môn học trên mỗi trang
$start = ($page - 1) * $recordsPerPage;

// Lấy danh sách học kỳ
$dsHocKy = [];
try {
    $stmt = $conn->query("SELECT ID, Ten FROM hocky ORDER BY ID");
    $dsHocKy = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Lỗi truy vấn danh sách học kỳ: " . $e->getMessage());
}

// Lấy danh sách năm học
$dsNamHoc = [];
try {
    $stmt = $conn->query("SELECT ID, Ten FROM namhoc ORDER BY NgayBatDau DESC");
    $dsNamHoc = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Lỗi truy vấn danh sách năm học: " . $e->getMessage());
}

// Truy vấn danh sách nhóm môn học
$dsNhomMonHoc = [];
$totalRecords = 0;

if ($isLecturer && $tk) {
    try {
        // Chuẩn bị điều kiện WHERE
        $whereConditions = ["nmh.IDGiangVien = ?"]; // Điều kiện bắt buộc: nhóm thuộc giảng viên đang đăng nhập
        $params = [$tk['ID']];
        
        // Lọc theo học kỳ
        if (!empty($maHocKy)) {
            $whereConditions[] = "nmh.IDHocKy = ?";
            $params[] = $maHocKy;
        }
        
        // Lọc theo năm học
        if (!empty($maNamHoc)) {
            $whereConditions[] = "mh.IDNamHoc = ?";
            $params[] = $maNamHoc;
        }
        
        // Tìm kiếm theo tên môn học hoặc mã nhóm
        if (!empty($search)) {
            $whereConditions[] = "(mh.TenMonHoc LIKE ? OR nmh.MaNhom LIKE ?)";
            $params[] = "%$search%";
            $params[] = "%$search%";
        }
        
        $whereClause = implode(' AND ', $whereConditions);
        
        // Đếm tổng số bản ghi cho phân trang
        $countQuery = "
            SELECT COUNT(nmh.ID) as total 
            FROM nhommonhoc nmh
            JOIN monhoc mh ON nmh.IDMonHoc = mh.ID
            WHERE $whereClause
        ";
        
        $countStmt = $conn->prepare($countQuery);
        $countStmt->execute($params);
        $totalRecords = $countStmt->fetch(PDO::FETCH_ASSOC)['total'];
        
        // Truy vấn danh sách nhóm môn học với phân trang
        $query = "
            SELECT 
                nmh.ID, 
                nmh.MaNhom,
                nmh.SoLuongDaDangKy,
                nmh.SoLuongToiDa,
                mh.ID as IDMonHoc,
                mh.TenMonHoc as TenMonHoc,
                mh.MaMonHoc,
                mh.SoTinChi as SoTC,
                hk.Ten as TenHocKy,
                nh.Ten as TenNamHoc
            FROM nhommonhoc nmh
            JOIN monhoc mh ON nmh.IDMonHoc = mh.ID
            JOIN hocky hk ON nmh.IDHocKy = hk.ID
            JOIN namhoc nh ON mh.IDNamHoc = nh.ID
            WHERE $whereClause
            ORDER BY nh.NgayBatDau DESC, hk.ID, mh.TenMonHoc
            LIMIT ?, ?
        ";
        
        $stmt = $conn->prepare($query);
        
        // Bind tham số động
        $paramIndex = 1;
        foreach ($params as $param) {
            $stmt->bindValue($paramIndex++, $param);
        }
        
        // Bind tham số phân trang
        $stmt->bindValue($paramIndex++, $start, PDO::PARAM_INT);
        $stmt->bindValue($paramIndex, $recordsPerPage, PDO::PARAM_INT);
        
        $stmt->execute();
        $dsNhomMonHoc = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Lấy số lượng sinh viên đã đăng ký cho mỗi nhóm
        foreach ($dsNhomMonHoc as &$nhom) {
            try {
                // Lấy số sinh viên đang học (TrangThai = 1)
                $stmtCountSV = $conn->prepare("
                    SELECT COUNT(*) as SoSV 
                    FROM nhommonhoc_sinhvien
                    WHERE IDNhomMonHoc = ?
                ");
                $stmtCountSV->execute([$nhom['ID']]);
                $result = $stmtCountSV->fetch(PDO::FETCH_ASSOC);
                $nhom['SoSinhVienDangKy'] = $result['SoSV'];
                
                // Lấy tổng sĩ số (tổng số chỗ có thể đăng ký)
                $stmtSiSo = $conn->prepare("
                    SELECT COUNT(*) as SiSo 
                    FROM nhommonhoc_sinhvien
                    WHERE IDNhomMonHoc = ?
                ");
                $stmtSiSo->execute([$nhom['ID']]);
                $resultSiSo = $stmtSiSo->fetch(PDO::FETCH_ASSOC);
                $nhom['SiSo'] = $resultSiSo['SiSo'];
                
                // Debug
                error_log("Nhóm {$nhom['MaNhom']} - Môn {$nhom['TenMonHoc']} có {$nhom['SoSinhVienDangKy']}/{$nhom['SiSo']} sinh viên");
            } catch (PDOException $e) {
                $nhom['SoSinhVienDangKy'] = 0;
                $nhom['SiSo'] = 0;
                error_log("Lỗi đếm số sinh viên cho nhóm {$nhom['ID']}: " . $e->getMessage());
            }
        }
        
    } catch (Exception $e) {
        error_log("Lỗi truy vấn danh sách nhóm môn học: " . $e->getMessage());
        $_SESSION['message'] = "Có lỗi xảy ra khi tải dữ liệu: " . $e->getMessage();
        $_SESSION['messageType'] = "danger";
    }
}

// Bắt đầu output buffer để tích hợp với layout
ob_start();
?>

<!-- Kiểm tra quyền truy cập -->
<?php if (!$isLecturer): ?>
    <div class="container-fluid px-4">
        <div class="row">
            <div class="col-12">
                <div class="alert alert-danger my-4 p-5 text-center">
                    <div class="mb-3">
                        <i class="fas fa-exclamation-triangle fa-5x text-danger"></i>
                    </div>
                    <h2 class="text-danger">RẤT TIẾC, BẠN KHÔNG CÓ QUYỀN XEM THÔNG TIN TRÊN TRANG NÀY!</h2>
                    <h4>Trang này chỉ dành cho giảng viên. Liên hệ người quản trị để được giải đáp thắc mắc.</h4>
                </div>
            </div>
        </div>
    </div>
<?php else: ?>
    <div class="container-fluid px-4">
        <div class="row">
            <div class="col-12">
                <div class="card shadow-sm my-4">
                    <!-- Tiêu đề -->
                    <div class="card-header bg-primary text-white py-3">
                        <div class="row align-items-center">
                            <div class="col-md-6">
                                <h4 class="mb-0"><i class="fas fa-chalkboard-teacher me-2"></i>DANH SÁCH NHÓM MÔN HỌC GIẢNG DẠY</h4>
                            </div>
                        </div>
                    </div>
                    
                    <div class="card-body">
                        <!-- Hiển thị thông báo -->
                        <?php if (isset($_SESSION['message'])): ?>
                            <div class="alert alert-<?php echo $_SESSION['messageType'] ?? 'info'; ?> alert-dismissible fade show" role="alert">
                                <?php echo $_SESSION['message']; ?>
                                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                            </div>
                            <?php unset($_SESSION['message'], $_SESSION['messageType']); ?>
                        <?php endif; ?>
                        
                        <!-- Bộ lọc -->
                        <div class="row mb-4">
                            <div class="col-12">
                                <form method="GET" action="" class="row g-3">
                                    <div class="col-md-3">
                                        <label for="MaHocKy" class="form-label">Học kỳ</label>
                                        <select class="form-select" id="MaHocKy" name="MaHocKy">
                                            <option value="">-- Tất cả học kỳ --</option>
                                            <?php foreach ($dsHocKy as $hocKy): ?>
                                            <option value="<?php echo $hocKy['ID']; ?>" <?php echo $hocKy['ID'] == $maHocKy ? 'selected' : ''; ?>>
                                                <?php echo htmlspecialchars($hocKy['Ten']); ?>
                                            </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <div class="col-md-3">
                                        <label for="MaNamHoc" class="form-label">Năm học</label>
                                        <select class="form-select" id="MaNamHoc" name="MaNamHoc">
                                            <option value="">-- Tất cả năm học --</option>
                                            <?php foreach ($dsNamHoc as $namHoc): ?>
                                            <option value="<?php echo $namHoc['ID']; ?>" <?php echo $namHoc['ID'] == $maNamHoc ? 'selected' : ''; ?>>
                                                <?php echo htmlspecialchars($namHoc['Ten']); ?>
                                            </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <div class="col-md-4">
                                        <label for="strSearch" class="form-label">Tìm kiếm</label>
                                        <div class="input-group">
                                            <input type="text" class="form-control" id="strSearch" name="strSearch" 
                                                placeholder="Tìm theo tên môn học, mã nhóm..." 
                                                value="<?php echo htmlspecialchars($search); ?>">
                                            <button class="btn btn-outline-primary" type="submit">
                                                <i class="fas fa-search"></i> Tìm
                                            </button>
                                        </div>
                                    </div>
                                    <?php if (!empty($maHocKy) || !empty($maNamHoc) || !empty($search)): ?>
                                    <div class="col-md-2">
                                        <label class="form-label d-block">&nbsp;</label>
                                        <a href="MyTeachingGroups.php" class="btn btn-outline-secondary w-100">
                                            <i class="fas fa-sync"></i> Đặt lại
                                        </a>
                                    </div>
                                    <?php endif; ?>
                                </form>
                            </div>
                        </div>
                        
                        <!-- Hiển thị danh sách nhóm môn học dạng bảng -->
<?php if (count($dsNhomMonHoc) > 0): ?>
    <div class="table-responsive">
        <table class="table table-hover table-bordered table-striped">
            <thead class="table-primary">
                <tr>
                    <th style="width: 5%">STT</th>
                    <th style="width: 8%">Mã nhóm</th>
                    <th style="width: 25%">Tên môn học</th>
                    <th style="width: 10%">Mã môn học</th>
                    <th style="width: 8%">Số TC</th>
                    <th style="width: 10%">Học kỳ</th>
                    <th style="width: 12%">Năm học</th>
                    <th style="width: 12%">Sinh viên</th>
                    <th style="width: 10%" class="text-center">Thao tác</th>
                </tr>
            </thead>
            <tbody>
                <?php 
                $stt = $start + 1;
                foreach ($dsNhomMonHoc as $nhom): 
                ?>
                <tr>
                    <td class="text-center"><?php echo $stt++; ?></td>
                    <td class="text-center">
                        <span class="badge bg-primary"><?php echo htmlspecialchars($nhom['MaNhom']); ?></span>
                    </td>
                    <td><?php echo htmlspecialchars($nhom['TenMonHoc']); ?></td>
                    <td class="text-center"><?php echo htmlspecialchars($nhom['MaMonHoc']); ?></td>
                    <td class="text-center"><?php echo $nhom['SoTC']; ?></td>
                    <td class="text-center"><?php echo htmlspecialchars($nhom['TenHocKy']); ?></td>
                    <td><?php echo htmlspecialchars($nhom['TenNamHoc']); ?></td>
                    <!-- Sửa đoạn hiển thị số sinh viên (dòng 307-318) -->
                    <td class="text-center">
                        <?php if (isset($nhom['SoLuongToiDa']) && $nhom['SoLuongToiDa'] > 0): ?>
                            <span class="badge bg-<?php 
                                echo ($nhom['SoSinhVienDangKy'] >= $nhom['SoLuongToiDa']) ? 'danger' : 
                                     (($nhom['SoSinhVienDangKy'] >= $nhom['SoLuongToiDa'] * 0.8) ? 'warning text-dark' : 'success'); 
                            ?> p-2">
                                <?php echo $nhom['SoSinhVienDangKy']; ?>/<?php echo $nhom['SoLuongToiDa']; ?>
                            </span>
                        <?php else: ?>
                            <span class="badge bg-secondary p-2">
                                <?php echo $nhom['SoSinhVienDangKy']; ?>/∞
                            </span>
                        <?php endif; ?>
                    </td>
                    <td class="text-center">
                        <a href="Details.php?id=<?php echo $nhom['ID']; ?>" class="btn btn-primary btn-sm">
                            <i class="fas fa-users me-1"></i> DS Sinh viên
                        </a>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <!-- Thêm tổng kết thông tin -->
    <div class="d-flex justify-content-between align-items-center mt-3 mb-3">
        <div class="text-muted">
            <small>Hiển thị <?php echo count($dsNhomMonHoc); ?> trên tổng số <?php echo $totalRecords; ?> nhóm môn học</small>
        </div>
        <div>
            <button type="button" class="btn btn-outline-primary btn-sm" id="btnExportExcel">
                <i class="fas fa-file-excel me-1"></i> Xuất Excel
            </button>
        </div>
    </div>
<?php else: ?>
                            <div class="alert alert-info text-center py-5">
                                <i class="fas fa-info-circle fa-3x mb-3"></i>
                                <h4>Không tìm thấy nhóm môn học nào</h4>
                                <p class="mb-0">
                                    <?php if (!empty($maHocKy) || !empty($maNamHoc) || !empty($search)): ?>
                                        Không có nhóm môn học nào phù hợp với điều kiện tìm kiếm.
                                    <?php else: ?>
                                        Bạn chưa được phân công giảng dạy nhóm môn học nào.
                                    <?php endif; ?>
                                </p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
<?php endif; ?>

<?php
// Lấy nội dung đã buffer và đưa vào layout
$contentForLayout = ob_get_clean();

// Kết nối với layout chung
include_once('../Shared/_LayoutAdmin.php');
?>
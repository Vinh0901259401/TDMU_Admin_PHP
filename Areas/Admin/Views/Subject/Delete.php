<?php
// filepath: c:\xampp\htdocs\TDMU_website\Areas\Admin\Views\Subject\Delete.php
// Kết nối database
require_once("../Shared/connect.inc");

// Tiêu đề trang
$pageTitle = "Xóa môn học";

// Lấy thông tin người dùng từ session
session_start();
$accessLevel = 0;
$tk = isset($_SESSION['TaiKhoan']) ? $_SESSION['TaiKhoan'] : null;

// Kiểm tra quyền truy cập
if ($tk && isset($tk['IDQuyenTruyCap'])) {
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

// Lấy ID môn học từ URL
$id = isset($_GET['id']) ? $_GET['id'] : null;

// Lấy thông tin môn học cần xóa
$monHoc = null;
$existingNhomMonHoc = false;

if ($id) {
    try {
        // Kiểm tra xem bảng nhommonhoc có tồn tại không trước khi query
        $tableCheckStmt = $conn->query("SHOW TABLES LIKE 'nhommonhoc'");
        if ($tableCheckStmt->rowCount() > 0) {
            // Bảng nhommonhoc tồn tại, kiểm tra xem môn học có liên kết không
            $stmtNhom = $conn->prepare("SELECT COUNT(*) FROM nhommonhoc WHERE IDMonHoc = ?");
            $stmtNhom->execute([$id]);
            $existingNhomMonHoc = ($stmtNhom->fetchColumn() > 0);
        }
        
        // Lấy thông tin môn học
        $stmt = $conn->prepare("
            SELECT mh.*, hk.Ten as TenHocKy, nh.Ten as TenNamHoc, k.Ten as TenKhoa,
            CASE 
                WHEN mh.LoaiMonHoc = 0 THEN 'Bắt buộc'
                WHEN mh.LoaiMonHoc = 1 THEN 'Tự chọn'
                WHEN mh.LoaiMonHoc = 2 THEN 'Điều kiện'
                ELSE 'Không xác định'
            END as LoaiMonHocText
            FROM monhoc mh
            LEFT JOIN hocky hk ON mh.IDHocKy = hk.ID
            LEFT JOIN namhoc nh ON mh.IDNamHoc = nh.ID
            LEFT JOIN khoa k ON mh.IDKhoa = k.ID
            WHERE mh.ID = ?
        ");
        $stmt->execute([$id]);
        $monHoc = $stmt->fetch(PDO::FETCH_ASSOC);
        
    } catch (PDOException $e) {
        error_log("Lỗi truy vấn thông tin môn học: " . $e->getMessage());
    }
}

// Xử lý form submit
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['confirm_delete']) && $id && $accessLevel <= 2) {
    try {
        // Xóa trực tiếp môn học nếu không có liên kết
        if (!$existingNhomMonHoc) {
            // Bắt đầu transaction
            $conn->beginTransaction();
            
            // Kiểm tra và xóa lịch sử chỉnh sửa nếu có
            $tableCheckStmt = $conn->query("SHOW TABLES LIKE 'monhoc_nguoichinhsua'");
            if ($tableCheckStmt->rowCount() > 0) {
                $stmtXoaLichSu = $conn->prepare("DELETE FROM monhoc_nguoichinhsua WHERE IDMonHoc = ?");
                $stmtXoaLichSu->execute([$id]);
            }
            
            // Xóa môn học
            $stmtXoa = $conn->prepare("DELETE FROM monhoc WHERE ID = ?");
            $stmtXoa->execute([$id]);
            
            // Hoàn tất transaction
            $conn->commit();
            
            $_SESSION['message'] = "Đã xóa môn học thành công!";
            $_SESSION['messageType'] = "success";
            header("Location: index.php");
            exit;
        } else {
            $_SESSION['message'] = "Không thể xóa môn học vì có nhóm môn học liên kết!";
            $_SESSION['messageType'] = "danger";
        }
    } catch (PDOException $e) {
        // Rollback nếu có lỗi
        if ($conn->inTransaction()) {
            $conn->rollBack();
        }
        error_log("Lỗi khi xóa môn học: " . $e->getMessage());
        $_SESSION['message'] = "Đã xảy ra lỗi khi xóa: " . $e->getMessage();
        $_SESSION['messageType'] = "danger";
    }
}

// Bắt đầu output buffer
ob_start();
?>

<?php if ($accessLevel > 2 || $accessLevel < 1): ?>
    <div class="container-fluid px-4">
        <div class="row">
            <div class="col-12">
                <div class="alert alert-danger my-4 p-5 text-center">
                    <div class="mb-3">
                        <i class="fas fa-exclamation-triangle fa-5x text-danger"></i>
                    </div>
                    <h2 class="text-danger">RẤT TIẾC, BẠN KHÔNG CÓ QUYỀN XEM THÔNG TIN TRÊN TRANG NÀY!</h2>
                    <h4>Liên hệ người quản trị để được phân quyền hoặc giải đáp các thắc mắc, xin cảm ơn!</h4>
                </div>
            </div>
        </div>
    </div>
<?php elseif (!$monHoc): ?>
    <div class="container-fluid px-4">
        <div class="row">
            <div class="col-12">
                <div class="alert alert-warning my-4 p-5 text-center">
                    <div class="mb-3">
                        <i class="fas fa-search fa-5x text-warning"></i>
                    </div>
                    <h2 class="text-warning">KHÔNG TÌM THẤY MÔN HỌC!</h2>
                    <h4>Môn học bạn đang tìm kiếm không tồn tại hoặc đã bị xóa.</h4>
                    <div class="mt-4">
                        <a href="index.php" class="btn btn-primary">
                            <i class="fas fa-arrow-left me-2"></i>Quay lại danh sách môn học
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
<?php else: ?>
    <div class="container-fluid px-4">
        <!-- Breadcrumb -->
        <div class="row mb-2">
            <div class="col-12">
                <nav aria-label="breadcrumb">
                    <ol class="breadcrumb">
                        <li class="breadcrumb-item"><a href="../Dashboard/Index.php">Trang chủ</a></li>
                        <li class="breadcrumb-item"><a href="index.php">Quản lý môn học</a></li>
                        <li class="breadcrumb-item active" aria-current="page">Xóa môn học</li>
                    </ol>
                </nav>
            </div>
        </div>
        
        <?php if (isset($_SESSION['message'])): ?>
            <div class="alert alert-<?php echo $_SESSION['messageType']; ?> alert-dismissible fade show" role="alert">
                <?php echo $_SESSION['message']; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
            <?php unset($_SESSION['message']); ?>
            <?php unset($_SESSION['messageType']); ?>
        <?php endif; ?>
        
        <div class="row">
            <div class="col-12">
                <div class="card shadow-sm border-danger mb-4">
                    <div class="card-header bg-danger text-white">
                        <h5 class="mb-0">
                            <i class="fas fa-trash-alt me-2"></i>Xác nhận xóa môn học
                        </h5>
                    </div>
                    <div class="card-body">
                        <div class="alert alert-warning">
                            <i class="fas fa-exclamation-triangle me-2"></i>
                            <strong>Cảnh báo:</strong> Bạn đang chuẩn bị xóa môn học này. Hành động này không thể hoàn tác!
                        </div>
                        
                        <?php if ($existingNhomMonHoc): ?>
                            <div class="alert alert-danger">
                                <i class="fas fa-ban me-2"></i>
                                <strong>Không thể xóa!</strong> Môn học này đang được sử dụng trong các nhóm môn học.
                                <div class="mt-2">
                                    Bạn cần xóa các nhóm môn học liên kết trước khi xóa môn học này.
                                </div>
                            </div>
                        <?php endif; ?>
                        
                        <div class="row mb-4">
                            <div class="col-md-8 offset-md-2">
                                <div class="card bg-light">
                                    <div class="card-header text-center">
                                        <h5 class="text-danger mb-0">Thông tin môn học sẽ bị xóa</h5>
                                    </div>
                                    <div class="card-body">
                                        <table class="table table-bordered">
                                            <tbody>
                                                <tr>
                                                    <th style="width: 30%">ID môn học:</th>
                                                    <td><?php echo htmlspecialchars($monHoc['ID']); ?></td>
                                                </tr>
                                                <tr>
                                                    <th>Mã môn học:</th>
                                                    <td><?php echo htmlspecialchars($monHoc['MaMonHoc']); ?></td>
                                                </tr>
                                                <tr>
                                                    <th>Tên môn học:</th>
                                                    <td><?php echo htmlspecialchars($monHoc['TenMonHoc']); ?></td>
                                                </tr>
                                                <tr>
                                                    <th>Số tín chỉ:</th>
                                                    <td><?php echo $monHoc['SoTinChi']; ?></td>
                                                </tr>
                                                <tr>
                                                    <th>Loại môn học:</th>
                                                    <td>
                                                        <?php
                                                            $loaiClass = 'bg-primary';
                                                            if ($monHoc['LoaiMonHoc'] == 1) $loaiClass = 'bg-success';
                                                            if ($monHoc['LoaiMonHoc'] == 2) $loaiClass = 'bg-info';
                                                        ?>
                                                        <span class="badge <?php echo $loaiClass; ?>">
                                                            <?php echo htmlspecialchars($monHoc['LoaiMonHocText']); ?>
                                                        </span>
                                                    </td>
                                                </tr>
                                                <tr>
                                                    <th>Học kỳ - Năm học:</th>
                                                    <td>
                                                        <?php if ($monHoc['TenHocKy'] && $monHoc['TenNamHoc']): ?>
                                                            <?php echo htmlspecialchars($monHoc['TenHocKy']); ?> - 
                                                            <?php echo htmlspecialchars($monHoc['TenNamHoc']); ?>
                                                        <?php else: ?>
                                                            <span class="text-muted">Chưa phân công</span>
                                                        <?php endif; ?>
                                                    </td>
                                                </tr>
                                                <tr>
                                                    <th>Khoa phụ trách:</th>
                                                    <td>
                                                        <?php if ($monHoc['TenKhoa']): ?>
                                                            <span class="badge bg-secondary">
                                                                <?php echo htmlspecialchars($monHoc['TenKhoa']); ?>
                                                            </span>
                                                        <?php else: ?>
                                                            <span class="badge bg-secondary">Chưa xác định</span>
                                                        <?php endif; ?>
                                                    </td>
                                                </tr>
                                                <?php if (!empty($monHoc['GhiChu'])): ?>
                                                <tr>
                                                    <th>Ghi chú:</th>
                                                    <td><?php echo htmlspecialchars($monHoc['GhiChu']); ?></td>
                                                </tr>
                                                <?php endif; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <form method="post" class="text-center">
                            <input type="hidden" name="confirm_delete" value="1">
                            <div class="d-flex justify-content-center gap-3">
                                <a href="index.php" class="btn btn-secondary">
                                    <i class="fas fa-arrow-left me-2"></i>Hủy & Quay lại
                                </a>
                                <button type="submit" class="btn btn-danger" <?php echo $existingNhomMonHoc ? 'disabled' : ''; ?>>
                                    <i class="fas fa-trash-alt me-2"></i>Xác nhận xóa
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
<?php endif; ?>

<?php
$contentForLayout = ob_get_clean();
require_once('../Shared/_LayoutAdmin.php');
?>
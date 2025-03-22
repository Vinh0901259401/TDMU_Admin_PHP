<?php
// filepath: c:\xampp\htdocs\TDMU_website\Areas\Admin\Views\Subject\Edit.php
// Kết nối database
require_once("../Shared/connect.inc");

// Tiêu đề trang
$pageTitle = "Chỉnh sửa môn học";

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

// Kiểm tra quyền chỉnh sửa (cấp độ 2 trở xuống)
if ($accessLevel > 2 || $accessLevel < 1) {
    $_SESSION['message'] = "Bạn không có quyền chỉnh sửa môn học!";
    $_SESSION['messageType'] = "danger";
    header('Location: index.php');
    exit;
}

// Kiểm tra ID môn học
if (!isset($_GET['id']) || empty($_GET['id'])) {
    $_SESSION['message'] = "Không tìm thấy môn học cần chỉnh sửa!";
    $_SESSION['messageType'] = "danger";
    header('Location: index.php');
    exit;
}

$idMonHoc = $_GET['id'];
$errors = [];
$success = false;
$monHoc = null;

// Lấy thông tin môn học cần chỉnh sửa
try {
    // Lấy danh sách học kỳ, năm học, khoa cho dropdown
    $stmtHocKy = $conn->query("SELECT ID, Ten as TenHocKy FROM hocky ORDER BY ID ASC");
    $dsHocKy = $stmtHocKy->fetchAll(PDO::FETCH_ASSOC);
    
    $stmtNamHoc = $conn->query("SELECT ID, Ten as TenNamHoc FROM namhoc ORDER BY ID DESC");
    $dsNamHoc = $stmtNamHoc->fetchAll(PDO::FETCH_ASSOC);
    
    $stmtKhoa = $conn->query("SELECT ID, Ten as TenKhoa FROM khoa ORDER BY ID ASC");
    $dsKhoa = $stmtKhoa->fetchAll(PDO::FETCH_ASSOC);
    
    // Lấy thông tin môn học
    $stmtMonHoc = $conn->prepare("
        SELECT mh.*, hk.Ten as TenHocKy, nh.Ten as TenNamHoc, k.Ten as TenKhoa
        FROM monhoc mh
        LEFT JOIN hocky hk ON mh.IDHocKy = hk.ID
        LEFT JOIN namhoc nh ON mh.IDNamHoc = nh.ID
        LEFT JOIN khoa k ON mh.IDKhoa = k.ID
        WHERE mh.ID = ?
    ");
    $stmtMonHoc->execute([$idMonHoc]);
    $monHoc = $stmtMonHoc->fetch(PDO::FETCH_ASSOC);
    
    if (!$monHoc) {
        $_SESSION['message'] = "Không tìm thấy môn học với ID: $idMonHoc";
        $_SESSION['messageType'] = "danger";
        header('Location: index.php');
        exit;
    }
} catch (PDOException $e) {
    error_log("Lỗi truy vấn thông tin môn học: " . $e->getMessage());
    $_SESSION['message'] = "Có lỗi xảy ra khi tải dữ liệu: " . $e->getMessage();
    $_SESSION['messageType'] = "danger";
    header('Location: index.php');
    exit;
}

// Xử lý form khi submit
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Lấy dữ liệu từ form
    $formData = [
        'maMonHoc' => trim($_POST['maMonHoc'] ?? ''),
        'tenMonHoc' => trim($_POST['tenMonHoc'] ?? ''),
        'soTinChi' => (int)($_POST['soTinChi'] ?? 0),
        'loaiMonHoc' => (int)($_POST['loaiMonHoc'] ?? 0),
        'idHocKy' => trim($_POST['idHocKy'] ?? ''),
        'idNamHoc' => trim($_POST['idNamHoc'] ?? ''),
        'idKhoa' => trim($_POST['idKhoa'] ?? ''),
        'ghiChu' => trim($_POST['ghiChu'] ?? '')
    ];
    
    // Validate dữ liệu đầu vào
    if (empty($formData['maMonHoc'])) {
        $errors[] = "Vui lòng nhập mã môn học";
    }
    
    if (empty($formData['tenMonHoc'])) {
        $errors[] = "Vui lòng nhập tên môn học";
    }
    
    if ($formData['soTinChi'] <= 0) {
        $errors[] = "Số tín chỉ phải lớn hơn 0";
    }
    
    if (empty($formData['idHocKy'])) {
        $errors[] = "Vui lòng chọn học kỳ";
    }
    
    if (empty($formData['idNamHoc'])) {
        $errors[] = "Vui lòng chọn năm học";
    }
    
    if (empty($formData['idKhoa'])) {
        $errors[] = "Vui lòng chọn khoa phụ trách";
    }
    
    // Kiểm tra mã môn học đã tồn tại chưa (nếu thay đổi)
    if ($formData['maMonHoc'] !== $monHoc['MaMonHoc']) {
        try {
            $stmtCheck = $conn->prepare("SELECT COUNT(*) FROM monhoc WHERE MaMonHoc = ? AND ID != ?");
            $stmtCheck->execute([$formData['maMonHoc'], $idMonHoc]);
            
            if ($stmtCheck->fetchColumn() > 0) {
                $errors[] = "Mã môn học đã tồn tại trong hệ thống";
            }
        } catch (PDOException $e) {
            error_log("Lỗi kiểm tra mã môn học: " . $e->getMessage());
            $errors[] = "Đã xảy ra lỗi khi kiểm tra dữ liệu";
        }
    }
    
    // Nếu không có lỗi, tiến hành cập nhật
    if (empty($errors)) {
        try {
            // Bắt đầu transaction
            $conn->beginTransaction();
            
            // Cập nhật thông tin môn học
            $stmtUpdate = $conn->prepare("
                UPDATE monhoc 
                SET MaMonHoc = ?, TenMonHoc = ?, SoTinChi = ?, LoaiMonHoc = ?, 
                    IDHocKy = ?, IDNamHoc = ?, IDKhoa = ?, GhiChu = ?
                WHERE ID = ?
            ");
            $stmtUpdate->execute([
                $formData['maMonHoc'],
                $formData['tenMonHoc'],
                $formData['soTinChi'],
                $formData['loaiMonHoc'],
                $formData['idHocKy'],
                $formData['idNamHoc'],
                $formData['idKhoa'],
                $formData['ghiChu'],
                $idMonHoc
            ]);
            
            // Ghi log chỉnh sửa nếu cần
            if ($tk && isset($tk['ID'])) {
                try {
                    // Kiểm tra bảng tồn tại
                    $tableExists = $conn->query("SHOW TABLES LIKE 'monhoc_nguoichinhsua'")->rowCount() > 0;
                    
                    if ($tableExists) {
                        $stmtAddHistory = $conn->prepare("
                            INSERT INTO monhoc_nguoichinhsua (ID, IDMonHoc, IDNguoiThucHien, HanhDong, GhiChu)
                            VALUES (NULL, ?, ?, ?, ?)
                        ");
                        
                        $stmtAddHistory->execute([
                            $idMonHoc,
                            $tk['ID'],
                            'EDIT',
                            "Cập nhật thông tin môn học"
                        ]);
                    }
                } catch (PDOException $historyEx) {
                    // Chỉ ghi log lỗi, không dừng xử lý
                    error_log("Lỗi khi ghi nhật ký chỉnh sửa: " . $historyEx->getMessage());
                }
            }
            
            // Commit transaction
            $conn->commit();
            
            // Đánh dấu thành công và lấy lại dữ liệu
            $success = true;
            
            // Lấy lại thông tin môn học sau khi cập nhật
            $stmtMonHoc->execute([$idMonHoc]);
            $monHoc = $stmtMonHoc->fetch(PDO::FETCH_ASSOC);
            
            $_SESSION['message'] = "Đã cập nhật môn học thành công!";
            $_SESSION['messageType'] = "success";
        } catch (PDOException $e) {
            // Rollback nếu có lỗi
            $conn->rollBack();
            error_log("Lỗi khi cập nhật môn học: " . $e->getMessage());
            $errors[] = "Đã xảy ra lỗi khi cập nhật môn học: " . $e->getMessage();
        }
    }
}

// Bắt đầu output buffer
ob_start();
?>

<!-- Phần nội dung trang -->
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
<?php else: ?>
    <div class="container-fluid px-4">
        <div class="row">
            <div class="col-12">
                <div class="card shadow-sm my-4">
                    <div class="card-header bg-primary text-white py-3">
                        <div class="row align-items-center">
                            <div class="col">
                                <h4 class="mb-0"><i class="fas fa-edit me-2"></i>CHỈNH SỬA MÔN HỌC</h4>
                            </div>
                            <div class="col-auto">
                                <a href="index.php" class="btn btn-light btn-sm">
                                    <i class="fas fa-arrow-left me-1"></i> Quay lại danh sách
                                </a>
                            </div>
                        </div>
                    </div>
                    <div class="card-body">
                        <?php if ($success): ?>
                            <div class="alert alert-success alert-dismissible fade show" role="alert">
                                <i class="fas fa-check-circle me-2"></i>
                                Môn học đã được cập nhật thành công!
                                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                            </div>
                        <?php endif; ?>
                        
                        <?php if (!empty($errors)): ?>
                            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                                <ul class="mb-0">
                                    <?php foreach ($errors as $error): ?>
                                        <li><?php echo $error; ?></li>
                                    <?php endforeach; ?>
                                </ul>
                                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                            </div>
                        <?php endif; ?>
                        
                        <form method="post" class="needs-validation" id="editForm" novalidate>
                            <!-- Thông tin cơ bản -->
                            <div class="card mb-4 border-primary">
                                <div class="card-header bg-primary bg-opacity-10 fw-bold">
                                    <i class="fas fa-info-circle me-2"></i>Thông tin cơ bản
                                </div>
                                <div class="card-body pt-3">
                                    <div class="mb-3 row">
                                        <label for="id" class="col-sm-2 col-form-label">ID môn học:</label>
                                        <div class="col-sm-10">
                                            <input type="text" class="form-control-plaintext" id="id" name="id" 
                                                value="<?php echo htmlspecialchars($monHoc['ID']); ?>" readonly>
                                        </div>
                                    </div>
                                    
                                    <div class="mb-3 row">
                                        <label for="maMonHoc" class="col-sm-2 col-form-label">Mã môn học: <span class="text-danger">*</span></label>
                                        <div class="col-sm-10">
                                            <input type="text" class="form-control" id="maMonHoc" name="maMonHoc" 
                                                value="<?php echo htmlspecialchars($monHoc['MaMonHoc']); ?>" required>
                                            <div class="invalid-feedback">
                                                Vui lòng nhập mã môn học
                                            </div>
                                            <div class="form-text">Ví dụ: MATH101, CNTT203...</div>
                                        </div>
                                    </div>
                                    
                                    <div class="mb-3 row">
                                        <label for="tenMonHoc" class="col-sm-2 col-form-label">Tên môn học: <span class="text-danger">*</span></label>
                                        <div class="col-sm-10">
                                            <input type="text" class="form-control" id="tenMonHoc" name="tenMonHoc" 
                                                value="<?php echo htmlspecialchars($monHoc['TenMonHoc']); ?>" required>
                                            <div class="invalid-feedback">
                                                Vui lòng nhập tên môn học
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <div class="mb-3 row">
                                        <label for="soTinChi" class="col-sm-2 col-form-label">Số tín chỉ: <span class="text-danger">*</span></label>
                                        <div class="col-sm-4">
                                            <input type="number" class="form-control" id="soTinChi" name="soTinChi" 
                                                value="<?php echo htmlspecialchars($monHoc['SoTinChi']); ?>" min="1" max="10" required>
                                            <div class="invalid-feedback">
                                                Vui lòng nhập số tín chỉ hợp lệ
                                            </div>
                                        </div>
                                        
                                        <label for="loaiMonHoc" class="col-sm-2 col-form-label">Loại môn học:</label>
                                        <div class="col-sm-4">
                                            <select class="form-select" id="loaiMonHoc" name="loaiMonHoc">
                                                <option value="0" <?php echo $monHoc['LoaiMonHoc'] == 0 ? 'selected' : ''; ?>>Bắt buộc</option>
                                                <option value="1" <?php echo $monHoc['LoaiMonHoc'] == 1 ? 'selected' : ''; ?>>Tự chọn</option>
                                                <option value="2" <?php echo $monHoc['LoaiMonHoc'] == 2 ? 'selected' : ''; ?>>Điều kiện</option>
                                            </select>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Thông tin phân loại -->
                            <div class="card mb-4 border-primary">
                                <div class="card-header bg-primary bg-opacity-10 fw-bold">
                                    <i class="fas fa-tags me-2"></i>Phân loại môn học
                                </div>
                                <div class="card-body pt-3">
                                    <div class="mb-3 row">
                                        <label for="idHocKy" class="col-sm-2 col-form-label">Học kỳ: <span class="text-danger">*</span></label>
                                        <div class="col-sm-4">
                                            <select class="form-select" id="idHocKy" name="idHocKy" required>
                                                <option value="">-- Chọn học kỳ --</option>
                                                <?php foreach ($dsHocKy as $hk): ?>
                                                    <option value="<?php echo htmlspecialchars($hk['ID']); ?>" 
                                                        <?php echo $monHoc['IDHocKy'] == $hk['ID'] ? 'selected' : ''; ?>>
                                                        <?php echo htmlspecialchars($hk['TenHocKy']); ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                            <div class="invalid-feedback">
                                                Vui lòng chọn học kỳ
                                            </div>
                                        </div>
                                        
                                        <label for="idNamHoc" class="col-sm-2 col-form-label">Năm học: <span class="text-danger">*</span></label>
                                        <div class="col-sm-4">
                                            <select class="form-select" id="idNamHoc" name="idNamHoc" required>
                                                <option value="">-- Chọn năm học --</option>
                                                <?php foreach ($dsNamHoc as $nh): ?>
                                                    <option value="<?php echo htmlspecialchars($nh['ID']); ?>"
                                                        <?php echo $monHoc['IDNamHoc'] == $nh['ID'] ? 'selected' : ''; ?>>
                                                        <?php echo htmlspecialchars($nh['TenNamHoc']); ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                            <div class="invalid-feedback">
                                                Vui lòng chọn năm học
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <div class="mb-3 row">
                                        <label for="idKhoa" class="col-sm-2 col-form-label">Khoa phụ trách: <span class="text-danger">*</span></label>
                                        <div class="col-sm-10">
                                            <select class="form-select" id="idKhoa" name="idKhoa" required>
                                                <option value="">-- Chọn khoa --</option>
                                                <?php foreach ($dsKhoa as $k): ?>
                                                    <option value="<?php echo htmlspecialchars($k['ID']); ?>"
                                                        <?php echo $monHoc['IDKhoa'] == $k['ID'] ? 'selected' : ''; ?>>
                                                        <?php echo htmlspecialchars($k['TenKhoa']); ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                            <div class="invalid-feedback">
                                                Vui lòng chọn khoa phụ trách
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Thông tin bổ sung -->
                            <div class="card mb-4 border-primary">
                                <div class="card-header bg-primary bg-opacity-10 fw-bold">
                                    <i class="fas fa-file-alt me-2"></i>Thông tin bổ sung
                                </div>
                                <div class="card-body pt-3">
                                    <div class="mb-3 row">
                                        <label for="ghiChu" class="col-sm-2 col-form-label">Ghi chú:</label>
                                        <div class="col-sm-10">
                                            <textarea class="form-control" id="ghiChu" name="ghiChu" rows="3"><?php echo htmlspecialchars($monHoc['GhiChu'] ?? ''); ?></textarea>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="border-top pt-3 d-flex justify-content-end">
                                <a href="index.php" class="btn btn-secondary me-2">
                                    <i class="fas fa-arrow-left me-1"></i> Quay lại
                                </a>
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-save me-1"></i> Lưu thay đổi
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
<?php endif; ?>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        // Validate form khi submit
        const form = document.getElementById('editForm');
        form.addEventListener('submit', function(event) {
            if (!this.checkValidity()) {
                event.preventDefault();
                event.stopPropagation();
            }
            this.classList.add('was-validated');
        });
    });
</script>

<?php
// Lấy nội dung đã buffer và đưa vào layout
$contentForLayout = ob_get_clean();

// Kết nối với layout
include_once('../Shared/_LayoutAdmin.php');
?>
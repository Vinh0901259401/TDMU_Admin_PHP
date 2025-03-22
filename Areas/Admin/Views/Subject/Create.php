<?php
// filepath: c:\xampp\htdocs\TDMU_website\Areas\Admin\Views\Subject\Create.php
// Kết nối database
require_once("../Shared/connect.inc");

// Tiêu đề trang
$pageTitle = "Thêm môn học mới";

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

// Lấy danh sách học kỳ, năm học, khoa cho form
$dsHocKy = [];
$dsNamHoc = [];
$dsKhoa = [];

try {
    // Lấy danh sách học kỳ
    $stmtHocKy = $conn->query("SELECT ID, Ten as TenHocKy FROM hocky ORDER BY ID ASC");
    $dsHocKy = $stmtHocKy->fetchAll(PDO::FETCH_ASSOC);
    
    // Lấy danh sách năm học
    $stmtNamHoc = $conn->query("SELECT ID, Ten as TenNamHoc FROM namhoc ORDER BY ID DESC");
    $dsNamHoc = $stmtNamHoc->fetchAll(PDO::FETCH_ASSOC);
    
    // Lấy danh sách khoa
    $stmtKhoa = $conn->query("SELECT ID, Ten as TenKhoa FROM khoa ORDER BY ID ASC");
    $dsKhoa = $stmtKhoa->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Lỗi truy vấn danh sách: " . $e->getMessage());
}

// Khởi tạo biến lỗi và dữ liệu
$errors = [];
$success = false;
$formData = [
    'maMonHoc' => '',
    'tenMonHoc' => '',
    'soTinChi' => '',
    'loaiMonHoc' => 0,
    'idHocKy' => '',
    'idNamHoc' => '',
    'idKhoa' => '',
    'ghiChu' => ''
];

// Xử lý khi form được gửi đi
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
    
    // Kiểm tra dữ liệu
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
    
    // Kiểm tra mã môn học đã tồn tại chưa
    try {
        $stmtCheck = $conn->prepare("SELECT COUNT(*) FROM monhoc WHERE MaMonHoc = ?");
        $stmtCheck->execute([$formData['maMonHoc']]);
        
        if ($stmtCheck->fetchColumn() > 0) {
            $errors[] = "Mã môn học đã tồn tại trong hệ thống";
        }
    } catch (PDOException $e) {
        error_log("Lỗi kiểm tra mã môn học: " . $e->getMessage());
        $errors[] = "Đã xảy ra lỗi khi kiểm tra dữ liệu";
    }
    
    // Nếu không có lỗi, thêm môn học vào CSDL
    if (empty($errors)) {
        try {
            // Bắt đầu transaction
            $conn->beginTransaction();
            
            // Thêm môn học mới - ID được sinh tự động bởi trigger
            $sql = "INSERT INTO monhoc (MaMonHoc, TenMonHoc, SoTinChi, LoaiMonHoc, IDHocKy, IDNamHoc, IDKhoa, GhiChu) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
            $params = [
                $formData['maMonHoc'],
                $formData['tenMonHoc'],
                $formData['soTinChi'],
                $formData['loaiMonHoc'],
                $formData['idHocKy'],
                $formData['idNamHoc'],
                $formData['idKhoa'],
                $formData['ghiChu']
            ];
            
            $stmt = $conn->prepare($sql);
            $stmt->execute($params);
            
            // Lấy ID của môn học vừa thêm
            $newSubjectId = $conn->lastInsertId();
            
            // Nếu không lấy được ID qua lastInsertId (do trigger hoặc stored procedure)
            if (!$newSubjectId) {
                $getIdStmt = $conn->prepare("SELECT ID FROM monhoc WHERE MaMonHoc = ? LIMIT 1");
                $getIdStmt->execute([$formData['maMonHoc']]);
                $newSubjectId = $getIdStmt->fetchColumn();
            }

            // Ghi log
            error_log("Đã thêm môn học mới: {$formData['maMonHoc']} - {$formData['tenMonHoc']} với ID: {$newSubjectId}");
            
            // Commit transaction
            $conn->commit();
            
            // Thông báo thành công
            $success = true;
            $_SESSION['message'] = "Thêm môn học thành công! 
                <div class='mt-2'>
                    <a href='index.php' class='btn btn-sm btn-primary me-2'>
                        <i class='fas fa-list me-1'></i>Xem danh sách môn học
                    </a>
                    <a href='../Group/Create.php?subject_id=" . $newSubjectId . "' class='btn btn-sm btn-success'>
                        <i class='fas fa-calendar-plus me-1'></i>Tạo nhóm môn học ngay
                    </a>
                </div>";
            $_SESSION['messageType'] = "success";
            
            // Chuyển hướng về trang Index
            header("Location: index.php");
            exit();
            
        } catch (PDOException $e) {
            // Rollback nếu có lỗi
            $conn->rollBack();
            error_log("Lỗi thêm môn học: " . $e->getMessage());
            $errors[] = "Đã xảy ra lỗi khi thêm môn học: " . $e->getMessage();
        }
    }
}

// Bắt đầu buffer
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
<?php else: ?>
    <div class="container-fluid px-4">
        <div class="row">
            <div class="col-12">
                <div class="card shadow-sm my-4">
                    <div class="card-header bg-primary text-white py-3">
                        <div class="d-flex justify-content-between align-items-center">
                            <h4 class="mb-0"><i class="fas fa-plus-circle me-2"></i>THÊM MÔN HỌC MỚI</h4>
                            <a href="index.php" class="btn btn-light btn-sm">
                                <i class="fas fa-arrow-left me-1"></i> Quay lại danh sách
                            </a>
                        </div>
                    </div>
                    <div class="card-body">
                        <?php if (!empty($errors)): ?>
                            <div class="alert alert-danger">
                                <ul class="mb-0">
                                    <?php foreach ($errors as $error): ?>
                                        <li><?php echo $error; ?></li>
                                    <?php endforeach; ?>
                                </ul>
                            </div>
                        <?php endif; ?>

                        <form method="post" class="needs-validation" novalidate>
                            <!-- Thông tin cơ bản -->
                            <div class="card mb-4 border-primary">
                                <div class="card-header bg-primary bg-opacity-10 fw-bold">
                                    <i class="fas fa-info-circle me-2"></i>Thông tin cơ bản
                                </div>
                                <div class="card-body pt-3">
                                    <div class="row mb-3">
                                        <div class="col-md-6">
                                            <label for="maMonHoc" class="form-label">Mã môn học <span class="text-danger">*</span></label>
                                            <input type="text" class="form-control" id="maMonHoc" name="maMonHoc" 
                                                   value="<?php echo htmlspecialchars($formData['maMonHoc']); ?>" required>
                                            <div class="invalid-feedback">Vui lòng nhập mã môn học</div>
                                            <div class="form-text">Ví dụ: MATH101, CNTT203, ECO305...</div>
                                        </div>
                                        <div class="col-md-6">
                                            <label for="tenMonHoc" class="form-label">Tên môn học <span class="text-danger">*</span></label>
                                            <input type="text" class="form-control" id="tenMonHoc" name="tenMonHoc" 
                                                   value="<?php echo htmlspecialchars($formData['tenMonHoc']); ?>" required>
                                            <div class="invalid-feedback">Vui lòng nhập tên môn học</div>
                                        </div>
                                    </div>
                                    
                                    <div class="row mb-3">
                                        <div class="col-md-3">
                                            <label for="soTinChi" class="form-label">Số tín chỉ <span class="text-danger">*</span></label>
                                            <input type="number" class="form-control" id="soTinChi" name="soTinChi" 
                                                   value="<?php echo htmlspecialchars($formData['soTinChi']); ?>" min="1" max="10" required>
                                            <div class="invalid-feedback">Vui lòng nhập số tín chỉ hợp lệ</div>
                                        </div>
                                        <div class="col-md-3">
                                            <label for="loaiMonHoc" class="form-label">Loại môn học</label>
                                            <select class="form-select" id="loaiMonHoc" name="loaiMonHoc">
                                                <option value="0" <?php echo $formData['loaiMonHoc'] == 0 ? 'selected' : ''; ?>>Bắt buộc</option>
                                                <option value="1" <?php echo $formData['loaiMonHoc'] == 1 ? 'selected' : ''; ?>>Tự chọn</option>
                                                <option value="2" <?php echo $formData['loaiMonHoc'] == 2 ? 'selected' : ''; ?>>Điều kiện</option>
                                            </select>
                                            <div class="form-text">Xác định loại môn học trong chương trình đào tạo</div>
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
                                    <div class="row mb-3">
                                        <div class="col-md-4">
                                            <label for="idHocKy" class="form-label">Học kỳ <span class="text-danger">*</span></label>
                                            <select class="form-select" id="idHocKy" name="idHocKy" required>
                                                <option value="">-- Chọn học kỳ --</option>
                                                <?php foreach ($dsHocKy as $hk): ?>
                                                    <option value="<?php echo htmlspecialchars($hk['ID']); ?>" 
                                                            <?php echo $formData['idHocKy'] == $hk['ID'] ? 'selected' : ''; ?>>
                                                        <?php echo htmlspecialchars($hk['TenHocKy']); ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                            <div class="invalid-feedback">Vui lòng chọn học kỳ</div>
                                        </div>
                                        <div class="col-md-4">
                                            <label for="idNamHoc" class="form-label">Năm học <span class="text-danger">*</span></label>
                                            <select class="form-select" id="idNamHoc" name="idNamHoc" required>
                                                <option value="">-- Chọn năm học --</option>
                                                <?php foreach ($dsNamHoc as $nh): ?>
                                                    <option value="<?php echo htmlspecialchars($nh['ID']); ?>"
                                                            <?php echo $formData['idNamHoc'] == $nh['ID'] ? 'selected' : ''; ?>>
                                                        <?php echo htmlspecialchars($nh['TenNamHoc']); ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                            <div class="invalid-feedback">Vui lòng chọn năm học</div>
                                        </div>
                                        <div class="col-md-4">
                                            <label for="idKhoa" class="form-label">Khoa phụ trách <span class="text-danger">*</span></label>
                                            <select class="form-select" id="idKhoa" name="idKhoa" required>
                                                <option value="">-- Chọn khoa --</option>
                                                <?php foreach ($dsKhoa as $k): ?>
                                                    <option value="<?php echo htmlspecialchars($k['ID']); ?>"
                                                            <?php echo $formData['idKhoa'] == $k['ID'] ? 'selected' : ''; ?>>
                                                        <?php echo htmlspecialchars($k['TenKhoa']); ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                            <div class="invalid-feedback">Vui lòng chọn khoa phụ trách</div>
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
                                    <div class="mb-0">
                                        <label for="ghiChu" class="form-label">Ghi chú:</label>
                                        <textarea class="form-control" id="ghiChu" name="ghiChu" rows="3"><?php echo htmlspecialchars($formData['ghiChu']); ?></textarea>
                                        <div class="form-text">Ghi chú thêm về môn học (không bắt buộc)</div>
                                    </div>
                                </div>
                            </div>

                            <!-- Nút lưu và hủy -->
                            <div class="d-flex justify-content-end">
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-save me-2"></i>Lưu môn học
                                </button>
                                <a href="index.php" class="btn btn-secondary ms-2">
                                    <i class="fas fa-times me-2"></i>Hủy bỏ
                                </a>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Form validation
        (function () {
            'use strict'
            
            // Lấy tất cả form cần validate
            var forms = document.querySelectorAll('.needs-validation')
            
            // Lặp qua và ngăn submit với validate
            Array.prototype.slice.call(forms)
                .forEach(function (form) {
                    form.addEventListener('submit', function (event) {
                        if (!form.checkValidity()) {
                            event.preventDefault();
                            event.stopPropagation();
                        }
                        
                        form.classList.add('was-validated');
                    }, false);
                });
        })();
    </script>
<?php endif; ?>

<?php
// Lấy nội dung đã buffer và đưa vào layout
$contentForLayout = ob_get_clean();

// Kết nối với layout
include_once('../Shared/_LayoutAdmin.php');
?>
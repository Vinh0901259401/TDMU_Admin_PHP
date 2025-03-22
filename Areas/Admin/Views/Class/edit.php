<?php
// filepath: c:\xampp\htdocs\TDMU_website\Areas\Admin\Views\Class\edit.php
// Kết nối database
require_once("../Shared/connect.inc");

// Tiêu đề trang
$pageTitle = "Chỉnh sửa thông tin lớp";

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

// Kiểm tra xem người dùng có quyền chỉnh sửa không
if ($accessLevel > 3) {
    $_SESSION['message'] = "Bạn không có quyền chỉnh sửa thông tin lớp!";
    $_SESSION['messageType'] = "danger";
    header("Location: Index.php");
    exit;
}

// Lấy ID lớp từ tham số URL
$maLop = isset($_GET['MaLop']) ? $_GET['MaLop'] : null;
$lop = null;
$message = '';
$messageType = '';

// Lấy danh sách nhóm ngành cho dropdown
$dsNhomNganh = [];
try {
    $stmt = $conn->query("SELECT ID, Ten FROM nhomnganh ORDER BY Ten");
    $dsNhomNganh = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Lỗi truy vấn danh sách nhóm ngành: " . $e->getMessage());
}

// Lấy danh sách giảng viên cho dropdown
$dsGiangVien = [];
try {
    $stmt = $conn->query("SELECT ID, HoTen FROM taikhoan WHERE chucvu = 'Giảng Viên' ORDER BY HoTen");
    $dsGiangVien = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Lỗi truy vấn danh sách giảng viên: " . $e->getMessage());
}

// Lấy thông tin lớp
if ($maLop) {
    try {
        $stmt = $conn->prepare("
            SELECT 
                l.*,
                nn.Ten AS TenNhomNganh,
                tk.HoTen AS TenGiangVien,
                k.Ten AS TenKhoa
            FROM lop l
            LEFT JOIN nhomnganh nn ON l.IDNhomNganh = nn.ID
            LEFT JOIN taikhoan tk ON l.IDGiangVien = tk.ID
            LEFT JOIN khoa k ON nn.IDKhoa = k.ID
            WHERE l.ID = ?
        ");
        $stmt->execute([$maLop]);
        
        if ($stmt->rowCount() > 0) {
            $lop = $stmt->fetch(PDO::FETCH_ASSOC);
        } else {
            // Lớp không tồn tại, chuyển về trang Index
            $_SESSION['message'] = "Không tìm thấy lớp với mã " . htmlspecialchars($maLop);
            $_SESSION['messageType'] = "danger";
            header("Location: Index.php");
            exit;
        }
    } catch (PDOException $e) {
        error_log("Lỗi truy vấn thông tin lớp: " . $e->getMessage());
    }
}

// Xử lý form submit
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // Lấy dữ liệu từ form
        $idNhomNganh = $_POST['IDNhomNganh'] ?? '';
        $idGiangVien = $_POST['IDGiangVien'] ?? null;
        $ghiChu = $_POST['GhiChu'] ?? '';
        
        // Validate dữ liệu
        if (empty($idNhomNganh)) {
            throw new Exception("Vui lòng chọn nhóm ngành cho lớp");
        }
        
        // Cập nhật thông tin lớp
        $stmt = $conn->prepare("
            UPDATE lop 
            SET IDNhomNganh = ?, IDGiangVien = ?, GhiChu = ?
            WHERE ID = ?
        ");
        $stmt->execute([$idNhomNganh, $idGiangVien, $ghiChu, $maLop]);
        
        // Thêm lịch sử chỉnh sửa (nếu cần)
        $idNguoiDung = $tk['ID'] ?? null;
        if ($idNguoiDung) {
            $stmt = $conn->prepare("
                INSERT INTO lop_nguoichinhsua (IDLop, IDNguoiChinhSua, NgayChinhSua, GhiChu)
                VALUES (?, ?, NOW(), ?)
            ");
            $stmt->execute([$maLop, $idNguoiDung, "Cập nhật thông tin lớp"]);
        }
        
        $message = "Cập nhật thông tin lớp thành công!";
        $messageType = "success";
        
        // Refresh lại thông tin lớp sau khi cập nhật
        $stmt = $conn->prepare("
            SELECT 
                l.*,
                nn.Ten AS TenNhomNganh,
                tk.HoTen AS TenGiangVien,
                k.Ten AS TenKhoa
            FROM lop l
            LEFT JOIN nhomnganh nn ON l.IDNhomNganh = nn.ID
            LEFT JOIN taikhoan tk ON l.IDGiangVien = tk.ID
            LEFT JOIN khoa k ON nn.IDKhoa = k.ID
            WHERE l.ID = ?
        ");
        $stmt->execute([$maLop]);
        $lop = $stmt->fetch(PDO::FETCH_ASSOC);
        
    } catch (Exception $e) {
        $message = $e->getMessage();
        $messageType = "danger";
    }
}

// Bắt đầu output buffer
ob_start();
?>

<!-- Phần nội dung trang -->
<?php if ($accessLevel > 4 || $accessLevel < 1): ?>
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
        <div class="row justify-content-center">
            <div class="col-12">
                <div class="card shadow-sm my-4">
                    <div class="card-header bg-primary text-white py-3 d-flex justify-content-between align-items-center">
                        <a href="Index.php" class="btn btn-light">
                            <i class="fas fa-arrow-left me-1"></i> Quay lại
                        </a>
                        <h2 class="text-center mb-0 flex-grow-1">CHỈNH SỬA THÔNG TIN LỚP</h2>
                        <div style="width: 100px;"></div><!-- Phần tử ẩn để cân bằng layout -->
                    </div>
                    
                    <div class="card-body">
                        <?php if (!empty($message)): ?>
                            <div class="alert alert-<?php echo $messageType; ?> alert-dismissible fade show" role="alert">
                                <?php echo $message; ?>
                                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                            </div>
                        <?php endif; ?>
                        
                        <form method="post" id="editForm" class="needs-validation" novalidate>
                            <div class="row mb-4">
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="MaLop" class="form-label fw-bold">Mã lớp:</label>
                                        <input type="text" class="form-control" id="MaLop" value="<?php echo htmlspecialchars($lop['ID'] ?? ''); ?>" readonly>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label for="TenKhoa" class="form-label fw-bold">Khoa/Viện:</label>
                                        <input type="text" class="form-control" id="TenKhoa" value="<?php echo htmlspecialchars($lop['TenKhoa'] ?? ''); ?>" readonly>
                                        <small class="form-text text-muted">Khoa/Viện được xác định theo nhóm ngành</small>
                                    </div>
                                </div>
                                
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="IDNhomNganh" class="form-label fw-bold">Nhóm ngành <span class="text-danger">*</span></label>
                                        <select class="form-select" id="IDNhomNganh" name="IDNhomNganh" required>
                                            <option value="">-- Chọn nhóm ngành --</option>
                                            <?php foreach ($dsNhomNganh as $nhomNganh): ?>
                                                <option value="<?php echo htmlspecialchars($nhomNganh['ID']); ?>" 
                                                    <?php echo ($lop['IDNhomNganh'] ?? '') == $nhomNganh['ID'] ? 'selected' : ''; ?>>
                                                    <?php echo htmlspecialchars($nhomNganh['Ten']); ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                        <div class="invalid-feedback">Vui lòng chọn nhóm ngành</div>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label for="IDGiangVien" class="form-label fw-bold">Giảng viên chủ nhiệm</label>
                                        <select class="form-select" id="IDGiangVien" name="IDGiangVien">
                                            <option value="">-- Chọn giảng viên --</option>
                                            <?php foreach ($dsGiangVien as $giangVien): ?>
                                                <option value="<?php echo htmlspecialchars($giangVien['ID']); ?>" 
                                                    <?php echo ($lop['IDGiangVien'] ?? '') == $giangVien['ID'] ? 'selected' : ''; ?>>
                                                    <?php echo htmlspecialchars($giangVien['HoTen']); ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                        <div class="invalid-feedback">Vui lòng chọn giảng viên</div>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="mb-4">
                                <label for="GhiChu" class="form-label fw-bold">Ghi chú</label>
                                <textarea class="form-control" id="GhiChu" name="GhiChu" rows="4"><?php echo htmlspecialchars($lop['GhiChu'] ?? ''); ?></textarea>
                                <small class="form-text text-muted">Thông tin thêm về lớp (không bắt buộc)</small>
                            </div>
                            
                            <div class="mb-4">
                                <label class="form-label fw-bold">Thông tin thêm</label>
                                <div class="card">
                                    <div class="card-body bg-light">
                                        <div class="row mb-2">
                                            <div class="col-md-4 fw-bold">Số sinh viên:</div>
                                            <div class="col-md-8">
                                                <?php
                                                    $svStmt = $conn->prepare("SELECT COUNT(*) AS SoSV FROM taikhoan WHERE IDLop = ?");
                                                    $svStmt->execute([$maLop]);
                                                    $sv = $svStmt->fetch(PDO::FETCH_ASSOC);
                                                    echo $sv ? $sv['SoSV'] : 0;
                                                ?>
                                                <a href="Students.php?MaLop=<?php echo $maLop; ?>" class="btn btn-sm btn-outline-primary ms-2">
                                                    <i class="fas fa-user-graduate me-1"></i> Xem danh sách
                                                </a>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="d-flex justify-content-between mt-4">
                                <a href="Index.php" class="btn btn-secondary">
                                    <i class="fas fa-times me-1"></i> Hủy
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
    
    <!-- Scripts -->
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
            
            // Cập nhật thông tin Khoa khi chọn Nhóm ngành
            const nhomNganhSelect = document.getElementById('IDNhomNganh');
            nhomNganhSelect.addEventListener('change', function() {
                const nhomNganhId = this.value;
                if (nhomNganhId) {
                    fetch('get_khoa.php?nganh=' + nhomNganhId)
                        .then(response => response.json())
                        .then(data => {
                            if (data.tenKhoa) {
                                document.getElementById('TenKhoa').value = data.tenKhoa;
                            }
                        })
                        .catch(error => console.error('Error:', error));
                }
            });
        });
    </script>
<?php endif; ?>

<?php
// Lấy nội dung đã buffer và đưa vào layout
$contentForLayout = ob_get_clean();

// Kết nối với layout
include_once('../Shared/_LayoutAdmin.php');
?>
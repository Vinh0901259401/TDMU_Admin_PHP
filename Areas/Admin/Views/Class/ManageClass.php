<?php
// Kết nối database
require_once("../Shared/connect.inc");

// Tiêu đề trang
$pageTitle = "QUẢN LÝ LỚP HỌC";

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

// Lấy tham số tìm kiếm
$strSearch = isset($_GET['strSearch']) ? $_GET['strSearch'] : '';
$genderFilter = isset($_GET['GenderList']) ? $_GET['GenderList'] : '';

// Kiểm tra cả hai định dạng tham số URL
$maLop = isset($_GET['maLop']) ? $_GET['maLop'] : (isset($_GET['MaLop']) ? $_GET['MaLop'] : '');
$maNH = isset($_GET['maNH']) ? $_GET['maNH'] : date('Y');

// Thêm mã đầu file để lấy thông tin lớp (thêm sau dòng khai báo $debugInfo)
// Debug thông tin lớp đã nhận
$debugInfo = [];
$debugInfo[] = "Mã lớp: " . ($maLop ?: 'Không có');

// Thêm biến lưu thông tin lớp
$lopInfo = null;

// Nếu có mã lớp, lấy thông tin lớp
if (!empty($maLop)) {
    try {
        $lop_stmt = $conn->prepare("SELECT * FROM lop WHERE ID = ?");
        $lop_stmt->execute([$maLop]);
        if ($lop_stmt->rowCount() > 0) {
            $lopInfo = $lop_stmt->fetch(PDO::FETCH_ASSOC);
            
            // Lấy thêm thông tin giảng viên nếu có
            if (!empty($lopInfo['IDGiangVien'])) {
                $gv_stmt = $conn->prepare("SELECT HoTen FROM taikhoan WHERE ID = ?");
                $gv_stmt->execute([$lopInfo['IDGiangVien']]);
                if ($gv_stmt->rowCount() > 0) {
                    $gv = $gv_stmt->fetch(PDO::FETCH_ASSOC);
                    $lopInfo['TenGiangVien'] = $gv['HoTen'];
                }
            }
            
            // Lấy thêm thông tin nhóm ngành nếu có
            if (!empty($lopInfo['IDNhomNganh'])) {
                $nganh_stmt = $conn->prepare("SELECT Ten FROM nhomnganh WHERE ID = ?");
                $nganh_stmt->execute([$lopInfo['IDNhomNganh']]);
                if ($nganh_stmt->rowCount() > 0) {
                    $nganh = $nganh_stmt->fetch(PDO::FETCH_ASSOC);
                    $lopInfo['TenNhomNganh'] = $nganh['Ten'];
                }
            }
        }
    } catch (PDOException $e) {
        $debugInfo[] = "Lỗi khi lấy thông tin lớp: " . $e->getMessage();
    }
}

// Danh sách giới tính để dropdown
$genderOptions = [
    '' => 'Tất cả',
    'Nam' => 'Nam',
    'Nữ' => 'Nữ'
];

if ($accessLevel >= 1 && $accessLevel <= 4) {
    try {
        // Đơn giản hóa truy vấn - không JOIN với các bảng khác
        $sql = "SELECT tk.ID, tk.HoTen, tk.GioiTinh, tk.NgaySinh, tk.SDT, tk.DiaChi, tk.CCCD, 
                tk.ImagePath as HinhAnh, tk.IDLop
                FROM taikhoan tk
                WHERE tk.ChucVu = 'Sinh Viên'";
        
        $params = [];
        
        // Điều kiện lớp học
        if (!empty($maLop)) {
            $sql .= " AND tk.IDLop = ?";
            $params[] = $maLop;
        }
        
        // Thêm điều kiện tìm kiếm và lọc giới tính
        if (!empty($strSearch)) {
            $sql .= " AND tk.HoTen LIKE ?";
            $params[] = "%$strSearch%";
        }
        
        if (!empty($genderFilter)) {
            $sql .= " AND tk.GioiTinh = ?";
            $params[] = $genderFilter;
        }
        
        // Sắp xếp kết quả
        $sql .= " ORDER BY tk.HoTen ASC";
        
        // Lưu câu truy vấn cho debug
        $query_debug = $sql;
        $debugInfo[] = "Sử dụng query đơn giản: $sql";
        
        // Thực thi truy vấn
        $stmt = $conn->prepare($sql);
        $stmt->execute($params);
        
        $debugInfo[] = "Số sinh viên tìm thấy: " . $stmt->rowCount();
        
        if ($stmt->rowCount() > 0) {
            $students = $stmt->fetchAll(PDO::FETCH_ASSOC);
            $classCount = count($students);
            
            // Không còn lấy Institute từ kết quả truy vấn
            $institute = ""; // Để trống vì không có thông tin khoa/viện
        } else {
            // Kiểm tra lớp có tồn tại không - sửa lại truy vấn kiểm tra lớp
            $lop_stmt = $conn->query("SHOW TABLES LIKE 'lop'");
            if ($lop_stmt->rowCount() > 0) {
                // Bảng lớp tồn tại, kiểm tra ID lớp
                $lop_stmt = $conn->prepare("SELECT * FROM lop WHERE ID = ?");
                $lop_stmt->execute([$maLop]);
                if ($lop_stmt->rowCount() > 0) {
                    $lop_info = $lop_stmt->fetch(PDO::FETCH_ASSOC);
                    $debugInfo[] = "Lớp tồn tại, nhưng không có sinh viên";
                    
                    // Thêm thông tin về các cột của bảng lớp
                    $columns_debug = [];
                    foreach ($lop_info as $key => $value) {
                        $columns_debug[] = "$key: $value";
                    }
                    $debugInfo[] = "Thông tin lớp: " . implode(", ", $columns_debug);
                } else {
                    $debugInfo[] = "Không tìm thấy lớp với mã: " . $maLop;
                }
            } else {
                $debugInfo[] = "Bảng lớp không tồn tại trong database";
            }
        }
    } catch (PDOException $e) {
        error_log("Lỗi truy vấn danh sách sinh viên: " . $e->getMessage());
        $debugInfo[] = "Lỗi truy vấn: " . $e->getMessage();
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
        <div class="row mt-4">
            <div class="col-12">
                <div class="card shadow-sm">
                    <div class="card-header bg-primary text-white py-3">
                        <div class="d-flex justify-content-between align-items-center">
                            <h2 class="mb-0">QUẢN LÝ LỚP HỌC</h2>
                            <?php if ($accessLevel <= 3): ?>
                                <div>
                                    <a href="index.php" class="btn btn-light me-2">
                                        <i class="fas fa-list me-1"></i> Danh sách lớp
                                    </a>
                                    <a href="Create.php" class="btn btn-success">
                                        <i class="fas fa-plus me-1"></i> Tạo lớp mới
                                    </a>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <div class="card-body">
                        <?php if ($lopInfo): ?>
                            <div class="mb-4">
                                <div class="row g-3">
                                    <div class="col-md-6">
                                        <div class="card bg-light h-100 border-0 shadow-sm">
                                            <div class="card-body">
                                                <h5 class="card-title text-primary mb-3 d-flex align-items-center">
                                                    <i class="fas fa-info-circle me-2"></i>
                                                    Thông tin lớp học
                                                </h5>
                                                <div class="row g-3">
                                                    <div class="col-md-6">
                                                        <p class="mb-1 text-muted small">Mã lớp:</p>
                                                        <p class="fw-bold"><?php echo htmlspecialchars($lopInfo['ID']); ?></p>
                                                    </div>
                                                    <div class="col-md-6">
                                                        <p class="mb-1 text-muted small">Nhóm ngành:</p>
                                                        <p class="fw-bold"><?php echo htmlspecialchars($lopInfo['TenNhomNganh'] ?? 'Chưa phân loại'); ?></p>
                                                    </div>
                                                    <div class="col-12">
                                                        <p class="mb-1 text-muted small">Giảng viên phụ trách:</p>
                                                        <p class="fw-bold"><?php echo htmlspecialchars($lopInfo['TenGiangVien'] ?? 'Chưa phân công'); ?></p>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="card bg-light h-100 border-0 shadow-sm">
                                            <div class="card-body">
                                                <h5 class="card-title text-success mb-3 d-flex align-items-center">
                                                    <i class="fas fa-users me-2"></i>
                                                    Danh sách sinh viên
                                                </h5>
                                                <div class="d-flex flex-column justify-content-between h-75">
                                                    <div>
                                                        <div class="d-flex align-items-center mb-3">
                                                            <div class="bg-info text-white rounded-circle p-2 me-2 d-flex align-items-center justify-content-center" style="width: 40px; height: 40px;">
                                                                <i class="fas fa-user-graduate"></i>
                                                            </div>
                                                            <div>
                                                                <p class="mb-0 small text-muted">Sĩ số hiện tại</p>
                                                                <h4 class="mb-0 fw-bold"><?php echo isset($classCount) ? $classCount : 0; ?> sinh viên</h4>
                                                            </div>
                                                        </div>
                                                    </div>
                                                    <?php if ($accessLevel <= 3): ?>
                                                    <div class="mt-3">
                                                        <a href="AddStudent.php?MaLop=<?php echo urlencode($maLop); ?>" class="btn btn-success">
                                                            <i class="fas fa-user-plus me-2"></i> Thêm sinh viên vào lớp
                                                        </a>
                                                    </div>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <?php if (!empty($lopInfo['GhiChu'])): ?>
                                    <div class="alert alert-info mt-3">
                                        <i class="fas fa-sticky-note me-2"></i>
                                        <strong>Ghi chú:</strong> <?php echo htmlspecialchars($lopInfo['GhiChu']); ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>
                        
                        <form method="GET" action="ManageClass.php" class="mb-4">
                            <!-- Chấp nhận cả 2 định dạng để đảm bảo tương thích -->
                            <input type="hidden" name="maLop" value="<?php echo htmlspecialchars($maLop); ?>" />
                            <input type="hidden" name="MaLop" value="<?php echo htmlspecialchars($maLop); ?>" />
                            <input type="hidden" name="maNH" value="<?php echo htmlspecialchars($maNH); ?>" />
                            
                            <div class="card border-0 shadow-sm">
                                <div class="card-header bg-light py-3">
                                    <h5 class="mb-0 text-primary">
                                        <i class="fas fa-filter me-2"></i> Bộ lọc tìm kiếm
                                    </h5>
                                </div>
                                <div class="card-body">
                                    <div class="row g-3 align-items-end">
                                        <div class="col-md-3">
                                            <label for="GenderList" class="form-label">Giới tính:</label>
                                            <select name="GenderList" id="GenderList" class="form-select">
                                                <?php foreach ($genderOptions as $value => $label): ?>
                                                    <option value="<?php echo htmlspecialchars($value); ?>" <?php echo $genderFilter === $value ? 'selected' : ''; ?>>
                                                        <?php echo htmlspecialchars($label); ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                        
                                        <div class="col-md-6">
                                            <label for="strSearch" class="form-label">Tìm theo tên sinh viên:</label>
                                            <div class="input-group">
                                                <span class="input-group-text bg-white">
                                                    <i class="fas fa-search"></i>
                                                </span>
                                                <input type="text" name="strSearch" id="strSearch" value="<?php echo htmlspecialchars($strSearch); ?>" placeholder="Nhập tên cần tìm..." class="form-control">
                                            </div>
                                        </div>
                                        
                                        <div class="col-md-3">
                                            <button type="submit" class="btn btn-primary w-100">
                                                <i class="fas fa-search me-2"></i> Tìm kiếm
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </form>
                        
                        <div class="card border-0 shadow-sm">
                            <div class="card-header bg-light py-3">
                                <div class="d-flex justify-content-between align-items-center">
                                    <h5 class="mb-0 text-primary">
                                        <i class="fas fa-user-graduate me-2"></i> Danh sách sinh viên
                                    </h5>
                                    <div>
                                        <span class="badge bg-info">
                                            Tổng: <?php echo isset($classCount) ? $classCount : 0; ?>
                                        </span>
                                    </div>
                                </div>
                            </div>
                            <div class="card-body p-0">
                                <div class="table-responsive">
                                    <table class="table table-hover table-striped mb-0">
                                        <thead class="bg-light">
                                            <tr>
                                                <th class="text-center">MSSV</th>
                                                <th>Họ tên</th>
                                                <th class="text-center">Ngày sinh</th>
                                                <th class="text-center">SĐT</th>
                                                <th>Địa chỉ</th>
                                                <th class="text-center">Giới tính</th>
                                                <th class="text-center">CCCD</th>
                                                <th class="text-center">Hình ảnh</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php if (empty($students)): ?>
                                                <tr>
                                                    <td colspan="8" class="text-center py-4">
                                                        <div class="my-3">
                                                            <i class="far fa-folder-open fa-3x text-muted mb-3"></i>
                                                            <h5>
                                                                <?php if (!empty($genderFilter)): ?>
                                                                    <?php if ($genderFilter === 'Nam'): ?>
                                                                        Không có bạn nam nào trong danh sách
                                                                    <?php elseif ($genderFilter === 'Nữ'): ?>
                                                                        Không có bạn nữ nào trong danh sách
                                                                    <?php endif; ?>
                                                                <?php elseif (!empty($strSearch)): ?>
                                                                    Không tìm thấy sinh viên nào có tên chứa "<?php echo htmlspecialchars($strSearch); ?>"
                                                                <?php else: ?>
                                                                    Không tìm thấy sinh viên nào phù hợp với điều kiện tìm kiếm
                                                                <?php endif; ?>
                                                            </h5>
                                                            <div class="small text-muted mt-2">
                                                                (Mã lớp: <?php echo htmlspecialchars($maLop); ?>)
                                                            </div>
                                                            <?php if ($accessLevel <= 3): ?>
                                                                <div class="mt-3">
                                                                    <a href="AddStudent.php?MaLop=<?php echo urlencode($maLop); ?>" class="btn btn-outline-success">
                                                                        <i class="fas fa-user-plus me-2"></i> Thêm sinh viên vào lớp
                                                                    </a>
                                                                </div>
                                                            <?php endif; ?>
                                                        </div>
                                                    </td>
                                                </tr>
                                            <?php else: ?>
                                                <?php foreach ($students as $student): ?>
                                                    <tr>
                                                        <td class="text-center mssv" role="button" title="Xem điểm">
                                                            <span class="fw-bold"><?php echo htmlspecialchars($student['ID']); ?></span>
                                                        </td>
                                                        <td class="fw-medium"><?php echo htmlspecialchars($student['HoTen']); ?></td>
                                                        <td class="text-center"><?php echo htmlspecialchars($student['NgaySinh']); ?></td>
                                                        <td class="text-center"><?php echo htmlspecialchars($student['SDT'] ?? 'Chưa cập nhật'); ?></td>
                                                        <td><?php echo htmlspecialchars($student['DiaChi'] ?? 'Chưa cập nhật'); ?></td>
                                                        <td class="text-center">
                                                            <?php if ($student['GioiTinh'] === 'Nam'): ?>
                                                                <span class="badge bg-primary rounded-pill">Nam</span>
                                                            <?php elseif ($student['GioiTinh'] === 'Nữ'): ?>
                                                                <span class="badge bg-danger rounded-pill">Nữ</span>
                                                            <?php else: ?>
                                                                <span class="badge bg-secondary rounded-pill"><?php echo htmlspecialchars($student['GioiTinh']); ?></span>
                                                            <?php endif; ?>
                                                        </td>
                                                        <td class="text-center"><?php echo htmlspecialchars($student['CCCD'] ?? 'Chưa cập nhật'); ?></td>
                                                        <td class="text-center">
                                                            <?php if (!empty($student['HinhAnh'])): ?>
                                                                <img src="/TDMU_website/Areas/Admin/Assets/img/user-image/<?php echo htmlspecialchars($student['HinhAnh']); ?>" class="mc-student-img" alt="Ảnh sinh viên" />
                                                            <?php else: ?>
                                                                <img src="/TDMU_website/Areas/Admin/Assets/img/user-image/default-user.png" class="mc-student-img" alt="Ảnh mặc định" />
                                                            <?php endif; ?>
                                                        </td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            <?php endif; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                        <div id="result-container"></div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <style>
        .mc-student-img {
            width: 50px;
            height: 50px;
            object-fit: cover;
            border-radius: 50%;
            border: 2px solid #f8f9fa;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
        }
        
        .mssv {
            font-weight: bold;
            color: #0d6efd;
            cursor: pointer;
        }
        
        .mssv:hover {
            text-decoration: underline;
        }
        
        .table th {
            font-weight: 600;
            background-color: rgba(13, 110, 253, 0.05);
        }
        
        /* Card styling */
        .card {
            border-radius: 0.5rem;
            overflow: hidden;
            margin-bottom: 1.5rem;
        }
        
        .card-header {
            border-bottom: none;
        }
        
        /* Table styling */
        .table-striped > tbody > tr:nth-of-type(odd) > * {
            background-color: rgba(0, 0, 0, 0.01);
        }
        
        /* Responsive adjustments */
        @media (max-width: 767px) {
            .table-responsive {
                font-size: 0.85rem;
            }
            
            .mc-student-img {
                width: 40px;
                height: 40px;
            }
            
            .card-title {
                font-size: 1.1rem;
            }
        }
    </style>
    
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        // Xử lý sự kiện khi người dùng nhấp vào MSSV
        document.querySelectorAll('.mssv').forEach(function(element) {
            element.addEventListener('click', function() {
                var mssv = this.textContent;
                var url = 'GradeTable.php?mssv=' + encodeURIComponent(mssv) + '&maNH=<?php echo urlencode($maNH); ?>';
                window.location.href = url;
            });
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
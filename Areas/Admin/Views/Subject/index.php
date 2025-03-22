<?php
// filepath: c:\xampp\htdocs\TDMU_website\Areas\Admin\Views\subject\index.php
// Kết nối database
require_once("../Shared/connect.inc");

// Tiêu đề trang
$pageTitle = "Quản lý Môn học";

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

// Chỉ cho phép người dùng có quyền quản lý (cấp độ 1, 2)
if ($accessLevel > 2 || $accessLevel < 1) {
    $_SESSION['message'] = "Bạn không có quyền truy cập trang này!";
    $_SESSION['messageType'] = "danger";
    header("Location: ../Dashboard/Index.php");
    exit;
}

// Xác định trang hiện tại và số lượng hiển thị
$currentPage = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$itemsPerPage = 10;
$offset = ($currentPage - 1) * $itemsPerPage;

// Các tham số tìm kiếm và lọc
$searchTerm = isset($_GET['strSearch']) ? trim($_GET['strSearch']) : '';
$filterHocKy = isset($_GET['hocky']) ? $_GET['hocky'] : '';
$filterNamHoc = isset($_GET['namhoc']) ? $_GET['namhoc'] : '';
$filterKhoa = isset($_GET['khoa']) ? $_GET['khoa'] : '';

// Hàm cắt chuỗi nếu quá dài
function truncate($string, $length) {
    if (strlen($string) <= $length) {
        return $string;
    }
    return substr($string, 0, $length) . '...';
}

// Debug - Kiểm tra cấu trúc bảng
try {
    $tableQuery = $conn->query("SHOW COLUMNS FROM monhoc");
    $tableColumns = $tableQuery->fetchAll(PDO::FETCH_COLUMN);
} catch (PDOException $e) {
    $tableColumns = [];
    error_log("Lỗi kiểm tra cấu trúc bảng: " . $e->getMessage());
}

// Truy vấn lấy danh sách môn học
try {
    // Xây dựng câu truy vấn với điều kiện
    $query = "
        SELECT 
            mh.*,
            hk.Ten as TenHocKy, 
            nh.Ten as TenNamHoc, 
            k.Ten as TenKhoa
        FROM monhoc mh
        LEFT JOIN hocky hk ON mh.IDHocKy = hk.ID
        LEFT JOIN namhoc nh ON mh.IDNamHoc = nh.ID
        LEFT JOIN khoa k ON mh.IDKhoa = k.ID
        WHERE 1=1
    ";
    
    $params = [];
    
    // Thêm điều kiện tìm kiếm
    if (!empty($searchTerm)) {
        $query .= " AND (mh.ID LIKE ? OR mh.TenMonHoc LIKE ?)";
        $params[] = "%$searchTerm%";
        $params[] = "%$searchTerm%";
    }
    
    // Thêm điều kiện lọc
    if (!empty($filterHocKy)) {
        $query .= " AND mh.IDHocKy = ?";
        $params[] = $filterHocKy;
    }
    
    if (!empty($filterNamHoc)) {
        $query .= " AND mh.IDNamHoc = ?";
        $params[] = $filterNamHoc;
    }
    
    if (!empty($filterKhoa)) {
        $query .= " AND mh.IDKhoa = ?";
        $params[] = $filterKhoa;
    }
    
    // Đếm tổng số bản ghi thỏa điều kiện
    $stmtCount = $conn->prepare("SELECT COUNT(*) FROM (" . $query . ") as counted");
    $stmtCount->execute($params);
    $totalItems = $stmtCount->fetchColumn();
    $totalPages = ceil($totalItems / $itemsPerPage);
    
    // Thêm phân trang
    $query .= " ORDER BY mh.ID DESC LIMIT $offset, $itemsPerPage";
    
    // Thực thi truy vấn
    $stmt = $conn->prepare($query);
    $stmt->execute($params);
    $danhSachMonHoc = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Lấy danh sách học kỳ cho filter
    $stmtHocKy = $conn->query("SELECT ID, Ten as TenHocKy FROM hocky ORDER BY ID ASC");
    $dsHocKy = $stmtHocKy->fetchAll(PDO::FETCH_ASSOC);
    
    // Lấy danh sách năm học cho filter
    $stmtNamHoc = $conn->query("SELECT ID, Ten as TenNamHoc FROM namhoc ORDER BY ID DESC");
    $dsNamHoc = $stmtNamHoc->fetchAll(PDO::FETCH_ASSOC);
    
    // Lấy danh sách khoa cho filter
    $stmtKhoa = $conn->query("SELECT ID, Ten as TenKhoa FROM khoa ORDER BY ID ASC");
    $dsKhoa = $stmtKhoa->fetchAll(PDO::FETCH_ASSOC);
    
} catch (PDOException $e) {
    error_log("Lỗi truy vấn danh sách môn học: " . $e->getMessage());
    $danhSachMonHoc = [];
    $totalItems = 0;
    $totalPages = 0;
    
    // Set thông báo lỗi nếu có
    if ($accessLevel == 1) { // Admin level
        $_SESSION['message'] = "Lỗi truy vấn: " . $e->getMessage();
        $_SESSION['messageType'] = "danger";
    }
}

// Hàm tạo URL phân trang giữ nguyên các tham số lọc
function getPaginationUrl($page) {
    $params = $_GET;
    $params['page'] = $page;
    return 'index.php?' . http_build_query($params);
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
                            <div class="col-md-6">
                                <h4 class="mb-0"><i class="fas fa-book me-2"></i>QUẢN LÝ MÔN HỌC</h4>
                            </div>
                            <div class="col-md-6 text-md-end mt-2 mt-md-0">
                                <a href="create.php" class="btn btn-light">
                                    <i class="fas fa-plus-circle me-1"></i> Thêm môn học mới
                                </a>
                            </div>
                        </div>
                    </div>
                    
                    <div class="card-body">
                        <!-- Form tìm kiếm và lọc -->
                        <form method="get" class="mb-4 row g-3 align-items-end">
                            <div class="col-md-3">
                                <label for="hocky" class="form-label">Học kỳ</label>
                                <select class="form-select" id="hocky" name="hocky">
                                    <option value="">-- Tất cả học kỳ --</option>
                                    <?php foreach ($dsHocKy as $hk): ?>
                                        <option value="<?php echo htmlspecialchars($hk['ID']); ?>" 
                                            <?php echo $filterHocKy == $hk['ID'] ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($hk['TenHocKy']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            
                            <div class="col-md-3">
                                <label for="namhoc" class="form-label">Năm học</label>
                                <select class="form-select" id="namhoc" name="namhoc">
                                    <option value="">-- Tất cả năm học --</option>
                                    <?php foreach ($dsNamHoc as $nh): ?>
                                        <option value="<?php echo htmlspecialchars($nh['ID']); ?>" 
                                            <?php echo $filterNamHoc == $nh['ID'] ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($nh['TenNamHoc']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="col-md-3">
                                <label for="khoa" class="form-label">Khoa</label>
                                <select class="form-select" id="khoa" name="khoa">
                                    <option value="">-- Tất cả khoa --</option>
                                    <?php foreach ($dsKhoa as $k): ?>
                                        <option value="<?php echo htmlspecialchars($k['ID']); ?>" 
                                            <?php echo $filterKhoa == $k['ID'] ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($k['TenKhoa']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            
                            <div class="col-md-3">
                                <label for="strSearch" class="form-label">Tìm kiếm</label>
                                <div class="input-group">
                                    <input type="text" class="form-control" id="strSearch" name="strSearch" 
                                           placeholder="Nhập mã hoặc tên môn học..." 
                                           value="<?php echo htmlspecialchars($searchTerm); ?>">
                                    <button class="btn btn-primary" type="submit">
                                        <i class="fas fa-search me-1"></i> Tìm
                                    </button>
                                </div>
                            </div>
                            
                            <div class="col-md-12 text-end mt-3">
                                <a href="index.php" class="btn btn-secondary">
                                    <i class="fas fa-sync-alt me-1"></i> Làm mới bộ lọc
                                </a>
                            </div>
                        </form>
                        
                        <!-- Thông báo -->
                        <?php if (isset($_SESSION['message'])): ?>
                            <div class="alert alert-<?php echo $_SESSION['messageType'] ?? 'info'; ?> alert-dismissible fade show" role="alert">
                                <?php echo $_SESSION['message']; ?>
                                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                            </div>
                            <?php 
                                unset($_SESSION['message']); 
                                unset($_SESSION['messageType']);
                            ?>
                        <?php endif; ?>
                        
                        <!-- Bảng danh sách môn học -->
                        <div class="table-responsive">
                            <table class="table table-hover table-bordered table-striped">
                                <thead class="table-dark">
                                    <tr>
                                        <th style="width: 5%">STT</th>
                                        <th style="width: 10%">Mã môn học</th>
                                        <th style="width: 25%">Tên môn học</th>
                                        <th style="width: 5%">TC</th>
                                        <th style="width: 15%">Học kỳ - Năm học</th>
                                        <th style="width: 15%">Khoa</th>
                                        <th style="width: 15%">Thao tác</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (count($danhSachMonHoc) > 0): ?>
                                        <?php foreach ($danhSachMonHoc as $index => $monHoc): ?>
                                            <tr>
                                                <td class="text-center"><?php echo $offset + $index + 1; ?></td>
                                                <td><?php echo htmlspecialchars($monHoc['MaMonHoc']); ?></td>
                                                <td><?php echo htmlspecialchars($monHoc['TenMonHoc'] ?? 'Chưa cập nhật'); ?></td>
                                                <td class="text-center"><?php echo $monHoc['SoTinChi'] ?? '0'; ?></td>
                                                <td>
                                                    <?php if ($monHoc['TenHocKy'] && $monHoc['TenNamHoc']): ?>
                                                        <?php echo htmlspecialchars($monHoc['TenHocKy']); ?> - 
                                                        <?php echo htmlspecialchars($monHoc['TenNamHoc']); ?>
                                                    <?php else: ?>
                                                        <span class="text-muted">Chưa phân công</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <?php if ($monHoc['TenKhoa']): ?>
                                                        <span class="badge bg-primary"><?php echo htmlspecialchars($monHoc['TenKhoa']); ?></span>
                                                    <?php else: ?>
                                                        <span class="badge bg-secondary">Chưa xác định</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td class="text-center">
                                                    <a href="details.php?id=<?php echo urlencode($monHoc['ID']); ?>" class="btn btn-sm btn-info mb-1" title="Chi tiết">
                                                        <i class="fas fa-eye"></i>
                                                    </a>
                                                    
                                                    <a href="edit.php?id=<?php echo urlencode($monHoc['ID']); ?>" class="btn btn-sm btn-warning mb-1" title="Chỉnh sửa">
                                                        <i class="fas fa-edit"></i>
                                                    </a>
                                                    
                                                    <button type="button" class="btn btn-sm btn-danger mb-1" 
                                                            data-bs-toggle="modal" 
                                                            data-bs-target="#deleteModal<?php echo str_replace(['.', ' ', '-'], '_', $monHoc['ID']); ?>" 
                                                            title="Xóa">
                                                        <i class="fas fa-trash"></i>
                                                    </button>
                                                    
                                                    <!-- Modal Xác nhận xóa -->
                                                    <div class="modal fade" id="deleteModal<?php echo str_replace(['.', ' ', '-'], '_', $monHoc['ID']); ?>" tabindex="-1" aria-hidden="true">
                                                        <div class="modal-dialog">
                                                            <div class="modal-content">
                                                                <div class="modal-header bg-danger text-white">
                                                                    <h5 class="modal-title">Xác nhận xóa môn học</h5>
                                                                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                                                </div>
                                                                <div class="modal-body">
                                                                    <p>Bạn có chắc chắn muốn xóa môn học này?</p>
                                                                    <p><strong>Mã môn học:</strong> <?php echo htmlspecialchars($monHoc['MaMonHoc']); ?></p>
                                                                    <p><strong>Tên môn học:</strong> <?php echo htmlspecialchars($monHoc['TenMonHoc'] ?? 'Chưa cập nhật'); ?></p>
                                                                    <p><strong>Số tín chỉ:</strong> <?php echo $monHoc['SoTinChi'] ?? '0'; ?></p>
                                                                    <p class="text-danger"><strong>Lưu ý:</strong> Hành động này không thể hoàn tác!</p>
                                                                </div>
                                                                <div class="modal-footer">
                                                                    <form action="delete.php" method="get">
                                                                        <input type="hidden" name="id" value="<?php echo $monHoc['ID']; ?>">
                                                                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Hủy</button>
                                                                        <button type="submit" class="btn btn-danger">Xác nhận xóa</button>
                                                                    </form>
                                                                </div>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <tr>
                                            <td colspan="7" class="text-center py-3">
                                                <?php if (!empty($searchTerm) || !empty($filterHocKy) || !empty($filterNamHoc) || !empty($filterKhoa)): ?>
                                                    <div class="alert alert-info mb-0">
                                                        <i class="fas fa-info-circle me-2"></i>
                                                        Không tìm thấy môn học nào phù hợp với điều kiện tìm kiếm
                                                    </div>
                                                <?php else: ?>
                                                    <div class="alert alert-info mb-0">
                                                        <i class="fas fa-info-circle me-2"></i>
                                                        Chưa có môn học nào trong hệ thống
                                                    </div>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                        
                        <!-- Phân trang -->
                        <?php if ($totalItems > 0): ?>
                            <div class="d-flex justify-content-between align-items-center mt-4">
                                <div>
                                    Hiển thị <?php echo count($danhSachMonHoc); ?> trên <?php echo $totalItems; ?> môn học
                                </div>
                                
                                <nav>
                                    <?php if ($totalPages > 1): ?>
                                        <ul class="pagination mb-0">
                                            <?php if ($currentPage > 1): ?>
                                                <li class="page-item">
                                                    <a class="page-link" href="<?php echo getPaginationUrl(1); ?>">&laquo;</a>
                                                </li>
                                            <?php endif; ?>
                                            
                                            <?php
                                                $startPage = max(1, $currentPage - 2);
                                                $endPage = min($totalPages, $currentPage + 2);
                                                
                                                for ($i = $startPage; $i <= $endPage; $i++):
                                            ?>
                                                <li class="page-item <?php echo $i == $currentPage ? 'active' : ''; ?>">
                                                    <a class="page-link" href="<?php echo getPaginationUrl($i); ?>"><?php echo $i; ?></a>
                                                </li>
                                            <?php endfor; ?>
                                            
                                            <?php if ($currentPage < $totalPages): ?>
                                                <li class="page-item">
                                                    <a class="page-link" href="<?php echo getPaginationUrl($totalPages); ?>">&raquo;</a>
                                                </li>
                                            <?php endif; ?>
                                        </ul>
                                    <?php endif; ?>
                                </nav>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <style>
        .table th, .table td {
            vertical-align: middle;
        }
        .pagination .page-link {
            color: #4e73df;
        }
        .pagination .active .page-link {
            background-color: #4e73df;
            border-color: #4e73df;
            color: white;
        }
        .badge {
            font-size: 85%;
        }
    </style>
<?php endif; ?>

<?php
// Lấy nội dung đã buffer và đưa vào layout
$contentForLayout = ob_get_clean();

// Kết nối với layout
include_once('../Shared/_LayoutAdmin.php');
?>
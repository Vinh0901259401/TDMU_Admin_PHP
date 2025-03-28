<?php
/**
 * Trang Quản Lý Bài Viết
 * Chức năng: Hiển thị danh sách, tìm kiếm, lọc, phân trang và các thao tác CRUD với bài viết
 */


// Kết nối database và các hàm tiện ích
require_once("../Shared/connect.inc");
$pageTitle = "Quản lý bài viết";

/**
 * Hàm cắt chuỗi giới hạn độ dài
 * @param string $string 
 * @param int $length 
 * @return string 
 */
function truncate($string, $length) {
    if (strlen($string) <= $length) {
        return $string;
    }
    return substr($string, 0, $length) . '...';
}

// Khởi tạo session và kiểm tra đăng nhập
session_start();
$accessLevel = 0;
$tk = isset($_SESSION['TaiKhoan']) ? $_SESSION['TaiKhoan'] : null;

// Kiểm tra quyền truy cập người dùng (Cấp độ 1-4 mới được phép truy cập)
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

// Xử lý tham số tìm kiếm và lọc
$maTheLoai = isset($_GET['MaTheLoai']) ? $_GET['MaTheLoai'] : '';
$search = isset($_GET['strSearch']) ? trim($_GET['strSearch']) : '';

// Cấu hình phân trang
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$recordsPerPage = 10; 
$start = ($page - 1) * $recordsPerPage;

// Lấy danh sách thể loại cho dropdown
$dsTheLoai = [];
try {
    $stmt = $conn->query("SELECT ID, Ten FROM tlbaiviet ORDER BY Ten");
    $dsTheLoai = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Lỗi truy vấn danh sách thể loại: " . $e->getMessage());
}

// Truy vấn danh sách bài viết với điều kiện lọc và phân trang
$dsBaiViet = [];
$totalRecords = 0;

try {
    // Chuẩn bị điều kiện WHERE cho truy vấn
    $whereConditions = [];
    $params = [];
    
    // Lọc theo thể loại
    if (!empty($maTheLoai)) {
        $whereConditions[] = "bv.IDTLBaiViet = ?";
        $params[] = $maTheLoai;
    }
    
    // Tìm kiếm theo tiêu đề hoặc người đăng
    if (!empty($search)) {
        $whereConditions[] = "(bv.TieuDe LIKE ? OR tk.HoTen LIKE ?)";
        $params[] = "%$search%";
        $params[] = "%$search%";
    }
    
    $whereClause = !empty($whereConditions) ? "WHERE " . implode(' AND ', $whereConditions) : "";
    
    // Đếm tổng số bản ghi cho phân trang
    $countQuery = "
        SELECT COUNT(bv.ID) as total 
        FROM baiviet bv
        LEFT JOIN tlbaiviet tlbv ON bv.IDTLBaiViet = tlbv.ID
        LEFT JOIN taikhoan tk ON bv.IDNguoiDang = tk.ID
        $whereClause
    ";
    
    $countStmt = $conn->prepare($countQuery);
    $countStmt->execute($params);
    $totalRecords = $countStmt->fetch(PDO::FETCH_ASSOC)['total'];
    
    // Truy vấn danh sách bài viết với phân trang
    $query = "
        SELECT 
            bv.ID, 
            bv.TieuDe, 
            bv.NoiDung, 
            tlbv.Ten AS TenTheLoai,
            bv.IDNguoiDang,
            tk.HoTen AS NguoiDang,
            bv.NgayTao
        FROM baiviet bv
        LEFT JOIN tlbaiviet tlbv ON bv.IDTLBaiViet = tlbv.ID
        LEFT JOIN taikhoan tk ON bv.IDNguoiDang = tk.ID
        $whereClause
        ORDER BY bv.ID DESC
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
    $dsBaiViet = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    error_log("Lỗi truy vấn danh sách bài viết: " . $e->getMessage());
    $_SESSION['message'] = "Có lỗi xảy ra khi tải dữ liệu: " . $e->getMessage();
    $_SESSION['messageType'] = "danger";
}

// Bắt đầu output buffer để tích hợp với layout
ob_start();
?>

<!-- Kiểm tra quyền truy cập và hiển thị thông báo nếu không đủ quyền -->
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
    <!-- Hiển thị giao diện quản lý bài viết -->
    <div class="container-fluid px-4">
        <div class="row">
            <div class="col-12">
                <div class="card shadow-sm my-4">
                    <!-- Tiêu đề và nút thêm mới -->
                    <div class="card-header bg-primary text-white py-3">
                        <div class="row align-items-center">
                            <div class="col-md-6">
                                <h4 class="mb-0"><i class="fas fa-newspaper me-2"></i>DANH SÁCH BÀI VIẾT</h4>
                            </div>
                            <div class="col-md-6 text-md-end mt-2 mt-md-0">
                                <?php if ($accessLevel <= 2): // Chỉ quản trị viên và biên tập viên được thêm mới ?>
                                    <a href="Create.php" class="btn btn-light">
                                        <i class="fas fa-plus-circle me-1"></i> Thêm bài viết mới
                                    </a>
                                <?php endif; ?>
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
                        
                        <!-- Bộ lọc và tìm kiếm -->
                        <div class="row mb-4">
                            <div class="col-12">
                                <form method="GET" action="" class="row g-3">
                                    <div class="col-md-4">
                                        <label for="MaTheLoai" class="form-label">Thể loại</label>
                                        <select class="form-select" id="MaTheLoai" name="MaTheLoai" onchange="this.form.submit()">
                                            <option value="">-- Tất cả thể loại --</option>
                                            <?php foreach ($dsTheLoai as $theLoai): ?>
                                            <option value="<?php echo $theLoai['ID']; ?>" <?php echo $theLoai['ID'] == $maTheLoai ? 'selected' : ''; ?>>
                                                <?php echo htmlspecialchars($theLoai['Ten']); ?>
                                            </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <div class="col-md-6">
                                        <label for="strSearch" class="form-label">Tìm kiếm</label>
                                        <div class="input-group">
                                            <input type="text" class="form-control" id="strSearch" name="strSearch" 
                                                placeholder="Tìm kiếm theo tiêu đề, người đăng..." 
                                                value="<?php echo htmlspecialchars($search); ?>">
                                            <button class="btn btn-outline-primary" type="submit">
                                                <i class="fas fa-search"></i> Tìm
                                            </button>
                                        </div>
                                    </div>
                                    <?php if (!empty($maTheLoai) || !empty($search)): ?>
                                    <div class="col-md-2">
                                        <label class="form-label d-block">&nbsp;</label>
                                        <a href="Index.php" class="btn btn-outline-secondary w-100">
                                            <i class="fas fa-sync"></i> Đặt lại
                                        </a>
                                    </div>
                                    <?php endif; ?>
                                </form>
                            </div>
                        </div>
                        
                        <!-- Bảng dữ liệu bài viết -->
                        <div class="table-responsive mt-3">
                            <table class="table table-bordered table-hover table-striped">
                                <thead class="table-dark">
                                    <tr>
                                        <th style="width: 15%">Thể loại</th>
                                        <th style="width: 30%">Tiêu đề</th>
                                        <th style="width: 20%">Người đăng</th>
                                        <th style="width: 10%">Ngày đăng</th>
                                        <th style="width: 25%" class="text-center">Thao tác</th>
                                    </tr>
                                </thead>
                                <tbody>
                                <?php if (count($dsBaiViet) > 0): ?>
                                    <?php foreach ($dsBaiViet as $baiViet): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($baiViet['TenTheLoai'] ?? 'Chưa phân loại'); ?></td>
                                        <td><?php echo truncate(htmlspecialchars($baiViet['TieuDe'] ?? ''), 50); ?></td>
                                        <td><?php echo htmlspecialchars($baiViet['NguoiDang'] ?? 'Không xác định'); ?></td>
                                        <td><?php echo isset($baiViet['NgayTao']) && $baiViet['NgayTao'] ? date('d/m/Y', strtotime($baiViet['NgayTao'])) : ''; ?></td>
                                        <td class="text-center">
                                            <div class="btn-group">
                                                <?php if ($accessLevel <= 2): // Chỉ quản trị viên và biên tập viên được sửa ?>
                                                <a href="Edit.php?MaBV=<?php echo $baiViet['ID']; ?>" class="btn btn-warning btn-sm">
                                                    <i class="fas fa-edit"></i>
                                                </a>
                                                <?php endif; ?>
                                                
                                                <a href="Details.php?MaBV=<?php echo $baiViet['ID']; ?>" class="btn btn-primary btn-sm mx-1">
                                                    <i class="fas fa-eye"></i>
                                                </a>
                                                
                                                <?php if ($accessLevel <= 2): // Chỉ quản trị viên và biên tập viên được xóa ?>
                                                <a href="Delete.php?MaBV=<?php echo $baiViet['ID']; ?>" class="btn btn-danger btn-sm">
                                                    <i class="fas fa-trash"></i>
                                                </a>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="5" class="text-center py-4">
                                            <div class="alert alert-info mb-0">
                                                <i class="fas fa-info-circle me-2"></i>
                                                <?php if (!empty($maTheLoai) || !empty($search)): ?>
                                                    Không tìm thấy bài viết nào phù hợp với điều kiện tìm kiếm
                                                <?php else: ?>
                                                    Chưa có bài viết nào trong hệ thống
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                        
                        <!-- Hiển thị phân trang -->
                        <?php if ($totalRecords > 0): ?>
                        <div class="d-flex justify-content-between align-items-center mt-4">
                            <div class="text-muted">
                                Hiển thị <?php echo count($dsBaiViet); ?> / <?php echo $totalRecords; ?> bài viết
                                <?php if(!empty($search) || !empty($maTheLoai)): ?>
                                    <span class="text-primary">(Kết quả lọc)</span>
                                <?php endif; ?>
                            </div>
                            
                            <nav aria-label="Page navigation">
                                <ul class="pagination mb-0">
                                    <?php
                                    // Tính toán phân trang
                                    $totalPages = ceil($totalRecords / $recordsPerPage);
                                    
                                    // Xây dựng URL cơ bản cho phân trang
                                    $basePageUrl = '?';
                                    if (!empty($maTheLoai)) {
                                        $basePageUrl .= 'MaTheLoai=' . urlencode($maTheLoai) . '&';
                                    }
                                    if (!empty($search)) {
                                        $basePageUrl .= 'strSearch=' . urlencode($search) . '&';
                                    }
                                    
                                    // Nút Previous
                                    if ($page > 1) {
                                        echo "<li class='page-item'><a class='page-link' href='{$basePageUrl}page=" . ($page - 1) . "'>«</a></li>";
                                    } else {
                                        echo "<li class='page-item disabled'><a class='page-link'>«</a></li>";
                                    }
                                    
                                    // Hiển thị số trang
                                    $startPage = max(1, $page - 2);
                                    $endPage = min($startPage + 4, $totalPages);
                                    
                                    if ($startPage > 1) {
                                        echo "<li class='page-item'><a class='page-link' href='{$basePageUrl}page=1'>1</a></li>";
                                        if ($startPage > 2) {
                                            echo "<li class='page-item disabled'><a class='page-link'>...</a></li>";
                                        }
                                    }
                                    
                                    for ($i = $startPage; $i <= $endPage; $i++) {
                                        if ($i == $page) {
                                            echo "<li class='page-item active'><a class='page-link'>" . $i . "</a></li>";
                                        } else {
                                            echo "<li class='page-item'><a class='page-link' href='{$basePageUrl}page=" . $i . "'>" . $i . "</a></li>";
                                        }
                                    }
                                    
                                    if ($endPage < $totalPages) {
                                        if ($endPage < $totalPages - 1) {
                                            echo "<li class='page-item disabled'><a class='page-link'>...</a></li>";
                                        }
                                        echo "<li class='page-item'><a class='page-link' href='{$basePageUrl}page=" . $totalPages . "'>" . $totalPages . "</a></li>";
                                    }
                                    
                                    // Nút Next
                                    if ($page < $totalPages) {
                                        echo "<li class='page-item'><a class='page-link' href='{$basePageUrl}page=" . ($page + 1) . "'>»</a></li>";
                                    } else {
                                        echo "<li class='page-item disabled'><a class='page-link'>»</a></li>";
                                    }
                                    ?>
                                </ul>
                            </nav>
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

// Kết nối với layout admin
include_once('../Shared/_LayoutAdmin.php');
?>
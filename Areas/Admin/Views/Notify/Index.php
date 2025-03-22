<?php
// filepath: c:\xampp\htdocs\TDMU_website\Areas\Admin\Views\Notify\Index.php
// Kết nối database
require_once("../Shared/connect.inc");

// Tiêu đề trang
$pageTitle = "Quản lý thông báo";

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

// Hàm cắt chuỗi nếu quá dài
function truncate($string, $length) {
    if (strlen($string) <= $length) {
        return $string;
    }
    return substr($string, 0, $length) . '...';
}

// Xử lý tham số tìm kiếm và lọc từ URL
$loaiTB = isset($_GET['loaiTB']) ? $_GET['loaiTB'] : '';
$search = isset($_GET['strSearch']) ? trim($_GET['strSearch']) : '';

// Phân trang
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$recordsPerPage = 10;
$offset = ($page - 1) * $recordsPerPage;

// Lấy danh sách loại thông báo cho dropdown
$dsLoaiTB = [];
try {
    $stmt = $conn->query("SELECT ID, Ten FROM loaithongbao ORDER BY Ten");
    $dsLoaiTB = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Lỗi truy vấn danh sách loại thông báo: " . $e->getMessage());
}

// Truy vấn số lượng thông báo
$whereConditions = [];
$params = [];

if (!empty($loaiTB)) {
    $whereConditions[] = "tb.IDLoaiThongBao = ?";
    $params[] = $loaiTB;
}

if (!empty($search)) {
    $whereConditions[] = "(tb.TieuDe LIKE ? OR tb.NoiDung LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

$whereClause = !empty($whereConditions) ? "WHERE " . implode(' AND ', $whereConditions) : "";

// Đếm tổng số bản ghi cho phân trang
$countQuery = "
    SELECT COUNT(*) as total 
    FROM thongbao tb
    $whereClause
";

try {
    $countStmt = $conn->prepare($countQuery);
    $countStmt->execute($params);
    $totalRecords = $countStmt->fetch(PDO::FETCH_ASSOC)['total'];
} catch (PDOException $e) {
    error_log("Lỗi đếm tổng số thông báo: " . $e->getMessage());
    $totalRecords = 0;
}

// Truy vấn danh sách thông báo
$thongBaoList = [];
try {
    $query = "
    SELECT 
        tb.ID,
        tb.TieuDe,
        tb.NoiDung,
        tb.IDNguoiTao,
        tb.NgayTao,
        tknguoitao.HoTen as TenNguoiTao, 
        ltb.Ten as TenLoaiThongBao,
        ltb.CapDo as CapDoThongBao
    FROM thongbao tb
    LEFT JOIN loaithongbao ltb ON tb.IDLoaiThongBao = ltb.ID
    LEFT JOIN taikhoan tknguoitao ON tb.IDNguoiTao = tknguoitao.ID  
    $whereClause
    ORDER BY tb.ID DESC
    LIMIT ?, ?
";
    
    $stmt = $conn->prepare($query);
    
    // Bind các tham số WHERE
    $paramIndex = 1;
    foreach ($params as $param) {
        $stmt->bindValue($paramIndex++, $param);
    }
    
    // Bind các tham số LIMIT
    $stmt->bindValue($paramIndex++, $offset, PDO::PARAM_INT);
    $stmt->bindValue($paramIndex, $recordsPerPage, PDO::PARAM_INT);
    
    $stmt->execute();
    $thongBaoList = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Lấy thông tin người gửi và người chỉnh sửa cho mỗi thông báo
    foreach ($thongBaoList as &$thongBao) {
        // Người gửi - lấy bản ghi đầu tiên trong bảng thongbao_nguoichinhsua
        $stmtNguoiGui = $conn->prepare("
            SELECT tbncs.*, tk.HoTen as TenNguoiGui, tbncs.NgayChinhSua as NgayGui
            FROM thongbao_nguoichinhsua tbncs
            LEFT JOIN taikhoan tk ON tbncs.IDNguoiChinhSua = tk.ID
            WHERE tbncs.IDThongBao = ?
            ORDER BY tbncs.NgayChinhSua ASC
            LIMIT 1
        ");
        $stmtNguoiGui->execute([$thongBao['ID']]);
        $nguoiGui = $stmtNguoiGui->fetch(PDO::FETCH_ASSOC);
        
        // Người chỉnh sửa gần nhất
        $stmtChinhSua = $conn->prepare("
            SELECT tbncs.*, tk.HoTen as TenNguoiChinhSua, tbncs.NgayChinhSua
            FROM thongbao_nguoichinhsua tbncs
            LEFT JOIN taikhoan tk ON tbncs.IDNguoiChinhSua = tk.ID
            WHERE tbncs.IDThongBao = ?
            ORDER BY tbncs.NgayChinhSua DESC
            LIMIT 1
        ");
        $stmtChinhSua->execute([$thongBao['ID']]);
        $nguoiChinhSua = $stmtChinhSua->fetch(PDO::FETCH_ASSOC);
        
        // Thêm thông tin vào mảng thông báo
        if ($nguoiGui) {
            $thongBao['TenNguoiGui'] = $nguoiGui['TenNguoiGui'];
            $thongBao['NgayGui'] = $nguoiGui['NgayGui'];
        } else {
            $thongBao['TenNguoiGui'] = 'Không xác định';
            $thongBao['NgayGui'] = null;
        }
        
        if ($nguoiChinhSua) {
            $thongBao['TenNguoiChinhSua'] = $nguoiChinhSua['TenNguoiChinhSua'];
            $thongBao['NgayChinhSua'] = $nguoiChinhSua['NgayChinhSua'];
        } else {
            $thongBao['TenNguoiChinhSua'] = 'Không xác định';
            $thongBao['NgayChinhSua'] = null;
        }
        
        // Đếm số người nhận
        $stmtNguoiNhan = $conn->prepare("
            SELECT COUNT(*) as SoNguoiNhan
            FROM thongbao_nguoinhan
            WHERE IDThongBao = ?
        ");
        $stmtNguoiNhan->execute([$thongBao['ID']]);
        $soNguoiNhan = $stmtNguoiNhan->fetch(PDO::FETCH_ASSOC);
        $thongBao['SoNguoiNhan'] = $soNguoiNhan ? $soNguoiNhan['SoNguoiNhan'] : 0;
    }
} catch (PDOException $e) {
    error_log("Lỗi truy vấn danh sách thông báo: " . $e->getMessage());
    $_SESSION['message'] = "Có lỗi xảy ra khi tải dữ liệu thông báo: " . $e->getMessage();
    $_SESSION['messageType'] = "danger";
}

// Tính số trang
$totalPages = ceil($totalRecords / $recordsPerPage);

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
        <div class="row">
            <div class="col-12">
                <div class="card shadow-sm my-4">
                    <div class="card-header bg-primary text-white py-3">
                        <div class="row align-items-center">
                            <div class="col-md-6">
                                <h4 class="mb-0"><i class="fas fa-bell me-2"></i>QUẢN LÝ THÔNG BÁO</h4>
                            </div>
                            <div class="col-md-6 text-md-end mt-2 mt-md-0">
                                <?php if ($accessLevel <= 2): ?>
                                    <a href="Create.php" class="btn btn-light">
                                        <i class="fas fa-plus-circle me-1"></i> Thêm thông báo mới
                                    </a>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    
                    <div class="card-body">
                        <!-- Form tìm kiếm và lọc -->
                        <form method="get" class="mb-4 row g-3 align-items-end">
                            <div class="col-md-4">
                                <label for="loaiTB" class="form-label">Loại thông báo</label>
                                <select class="form-select" id="loaiTB" name="loaiTB">
                                    <option value="">-- Tất cả loại thông báo --</option>
                                    <?php foreach ($dsLoaiTB as $loai): ?>
                                        <option value="<?php echo htmlspecialchars($loai['ID']); ?>" 
                                            <?php echo $loaiTB == $loai['ID'] ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($loai['Ten']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            
                            <div class="col-md-6">
                                <label for="strSearch" class="form-label">Tìm kiếm</label>
                                <div class="input-group">
                                    <input type="text" class="form-control" id="strSearch" name="strSearch" 
                                           placeholder="Nhập tiêu đề hoặc nội dung..." 
                                           value="<?php echo htmlspecialchars($search); ?>">
                                    <button class="btn btn-primary" type="submit">
                                        <i class="fas fa-search me-1"></i> Tìm
                                    </button>
                                </div>
                            </div>
                            
                            <div class="col-md-2">
                                <a href="Index.php" class="btn btn-secondary w-100">
                                    <i class="fas fa-sync-alt me-1"></i> Làm mới
                                </a>
                            </div>
                        </form>
                        
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
                        
                        <!-- Bảng danh sách thông báo -->
                        <div class="table-responsive">
                            <table class="table table-hover table-bordered table-striped">
                                <thead class="table-dark">
                                    <tr>
                                        <th style="width: 15%">Loại thông báo</th>
                                        <th style="width: 25%">Tiêu đề</th>
                                        <th style="width: 20%">Người gửi</th>
                                        <th style="width: 15%">Ngày gửi</th>
                                        <th style="width: 8%">Số người nhận</th>
                                        <th style="width: 20%">Thao tác</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (count($thongBaoList) > 0): ?>
                                        <?php foreach ($thongBaoList as $item): ?>
                                            <tr>
                                                <td>
                                                    <?php if ($item['TenLoaiThongBao']): ?>
                                                        <span class="badge bg-primary"><?php echo htmlspecialchars($item['TenLoaiThongBao']); ?></span>
                                                    <?php else: ?>
                                                        <span class="badge bg-secondary">Không phân loại</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td><?php echo htmlspecialchars(truncate($item['TieuDe'], 50)); ?></td>
                                                <td><?php echo htmlspecialchars($item['TenNguoiTao']); ?></td>
                                                <td class="text-center">
                                                    <?php echo $item['NgayTao'] ? date('d/m/Y', strtotime($item['NgayTao'])) : '-'; ?>
                                                </td>
                                                <td class="text-center"><?php echo $item['SoNguoiNhan']; ?></td>
                                                <td class="text-center">
                                                    <a href="Details.php?id=<?php echo urlencode($item['ID']); ?>" class="btn btn-sm btn-info mb-1" title="Chi tiết">
                                                        <i class="fas fa-eye"></i>
                                                    </a>
                                                    
                                                    <?php if ($accessLevel <= 3): ?>
                                                        <a href="Edit.php?id=<?php echo urlencode($item['ID']); ?>" class="btn btn-sm btn-warning mb-1" title="Chỉnh sửa">
                                                            <i class="fas fa-edit"></i>
                                                        </a>
                                                    <?php endif; ?>
                                                    
                                                    <?php if ($accessLevel <= 2): ?>
                                                        <button type="button" class="btn btn-sm btn-danger mb-1" 
                                                                data-bs-toggle="modal" 
                                                                data-bs-target="#deleteModal<?php echo $item['ID']; ?>" 
                                                                title="Xóa">
                                                            <i class="fas fa-trash"></i>
                                                        </button>
                                                        
                                                        <!-- Modal Xác nhận xóa -->
                                                        <div class="modal fade" id="deleteModal<?php echo $item['ID']; ?>" tabindex="-1" aria-hidden="true">
                                                            <div class="modal-dialog">
                                                                <div class="modal-content">
                                                                    <div class="modal-header bg-danger text-white">
                                                                        <h5 class="modal-title">Xác nhận xóa thông báo</h5>
                                                                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                                                    </div>
                                                                    <div class="modal-body">
                                                                        <p>Bạn có chắc chắn muốn xóa thông báo này?</p>
                                                                        <p><strong>Tiêu đề:</strong> <?php echo htmlspecialchars($item['TieuDe']); ?></p>
                                                                        <p class="text-danger"><strong>Lưu ý:</strong> Hành động này không thể hoàn tác!</p>
                                                                    </div>
                                                                    <div class="modal-footer">
                                                                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Hủy</button>
                                                                        <a href="Delete.php?id=<?php echo urlencode($item['ID']); ?>" class="btn btn-danger">
                                                                            Xác nhận xóa
                                                                        </a>
                                                                    </div>
                                                                </div>
                                                            </div>
                                                        </div>
                                                    <?php endif; ?>
                                                    
                                                    <a href="Send.php?id=<?php echo urlencode($item['ID']); ?>" class="btn btn-sm btn-success mb-1" title="Gửi thông báo">
                                                        <i class="fas fa-paper-plane"></i>
                                                    </a>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <tr>
                                            <td colspan="7" class="text-center py-3">
                                                <?php if (!empty($search) || !empty($loaiTB)): ?>
                                                    <div class="alert alert-info mb-0">
                                                        <i class="fas fa-info-circle me-2"></i>
                                                        Không tìm thấy thông báo nào phù hợp với điều kiện tìm kiếm
                                                    </div>
                                                <?php else: ?>
                                                    <div class="alert alert-info mb-0">
                                                        <i class="fas fa-info-circle me-2"></i>
                                                        Chưa có thông báo nào trong hệ thống
                                                    </div>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                        
                        <!-- Phân trang -->
                        <?php if ($totalRecords > 0): ?>
                            <div class="d-flex justify-content-between align-items-center mt-4">
                                <div>
                                    Hiển thị <?php echo count($thongBaoList); ?> trên <?php echo $totalRecords; ?> thông báo
                                </div>
                                
                                <nav>
                                    <?php if ($totalPages > 1): ?>
                                        <ul class="pagination mb-0">
                                            <?php if ($page > 1): ?>
                                                <li class="page-item">
                                                    <a class="page-link" href="?page=1<?php echo !empty($loaiTB) ? '&loaiTB=' . urlencode($loaiTB) : ''; ?><?php echo !empty($search) ? '&strSearch=' . urlencode($search) : ''; ?>">&laquo;</a>
                                                </li>
                                            <?php endif; ?>
                                            
                                            <?php
                                                $startPage = max(1, $page - 2);
                                                $endPage = min($totalPages, $page + 2);
                                                
                                                for ($i = $startPage; $i <= $endPage; $i++):
                                            ?>
                                                <li class="page-item <?php echo $i == $page ? 'active' : ''; ?>">
                                                    <a class="page-link" href="?page=<?php echo $i; ?><?php echo !empty($loaiTB) ? '&loaiTB=' . urlencode($loaiTB) : ''; ?><?php echo !empty($search) ? '&strSearch=' . urlencode($search) : ''; ?>"><?php echo $i; ?></a>
                                                </li>
                                            <?php endfor; ?>
                                            
                                            <?php if ($page < $totalPages): ?>
                                                <li class="page-item">
                                                    <a class="page-link" href="?page=<?php echo $totalPages; ?><?php echo !empty($loaiTB) ? '&loaiTB=' . urlencode($loaiTB) : ''; ?><?php echo !empty($search) ? '&strSearch=' . urlencode($search) : ''; ?>">&raquo;</a>
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
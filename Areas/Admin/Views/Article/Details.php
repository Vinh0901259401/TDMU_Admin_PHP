<?php
// filepath: c:\xampp\htdocs\TDMU_website\Areas\Admin\Views\Article\Details.php
// Kết nối database
require_once("../Shared/connect.inc");

// Tiêu đề trang
$pageTitle = "Chi tiết bài viết";

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

// Lấy ID bài viết từ tham số URL
$maBV = isset($_GET['MaBV']) ? $_GET['MaBV'] : null;
$baiViet = null;
$lichSuChinhSua = [];

// Lấy thông tin bài viết
if ($maBV) {
    try {
        // Truy vấn thông tin bài viết
        $stmt = $conn->prepare("
            SELECT 
                bv.*,
                tlbv.Ten as TenTheLoai,
                tk.HoTen as NguoiDang,
                (SELECT MAX(bvncs.NgayChinhSua) 
                 FROM baiviet_nguoichinhsua bvncs 
                 WHERE bvncs.IDBaiViet = bv.ID) AS NgayChinhSua,
                (SELECT HoTen FROM taikhoan tk2 
                 JOIN baiviet_nguoichinhsua bvncs ON tk2.ID = bvncs.IDNguoiChinhSua
                 WHERE bvncs.IDBaiViet = bv.ID
                 ORDER BY bvncs.NgayChinhSua DESC LIMIT 1) AS NguoiChinhSuaCuoi
            FROM baiviet bv
            LEFT JOIN tlbaiviet tlbv ON bv.IDTLBaiViet = tlbv.ID
            LEFT JOIN taikhoan tk ON bv.IDNguoiDang = tk.ID
            WHERE bv.ID = ?
        ");
        $stmt->execute([$maBV]);
        
        if ($stmt->rowCount() > 0) {
            $baiViet = $stmt->fetch(PDO::FETCH_ASSOC);
            
            // Lấy lịch sử chỉnh sửa
            $stmtHistory = $conn->prepare("
                SELECT 
                    bvncs.*,
                    tk.HoTen as NguoiChinhSua
                FROM baiviet_nguoichinhsua bvncs
                JOIN taikhoan tk ON bvncs.IDNguoiChinhSua = tk.ID
                WHERE bvncs.IDBaiViet = ?
                ORDER BY bvncs.NgayChinhSua DESC
            ");
            $stmtHistory->execute([$maBV]);
            $lichSuChinhSua = $stmtHistory->fetchAll(PDO::FETCH_ASSOC);
            
        } else {
            // Bài viết không tồn tại
            header("Location: Index.php");
            exit;
        }
    } catch (PDOException $e) {
        error_log("Lỗi truy vấn bài viết: " . $e->getMessage());
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
        <div class="row">
            <div class="col-12">
                <div class="card shadow-sm my-4">
                    <div class="card-header bg-primary text-white py-3 d-flex justify-content-between align-items-center">
                        <a href="Index.php" class="btn btn-light">
                            <i class="fas fa-arrow-left me-1"></i> Quay lại
                        </a>
                        <h2 class="text-center mb-0 flex-grow-1">CHI TIẾT BÀI VIẾT</h2>
                        <div style="width: 100px;"></div><!-- Phần tử ẩn để cân bằng layout -->
                    </div>
                    
                    <div class="card-body">
                        <div class="row">
                            <!-- Thông tin bài viết -->
                            <div class="col-md-8">
                                <h3 class="text-primary mb-3"><?php echo htmlspecialchars($baiViet['TieuDe'] ?? ''); ?></h3>
                                
                                <div class="bg-light p-3 mb-4 rounded border">
                                    <div class="d-flex align-items-center mb-3">
                                        <div class="badge bg-success me-2">
                                            <i class="fas fa-folder me-1"></i> 
                                            <?php echo htmlspecialchars($baiViet['TenTheLoai'] ?? ''); ?>
                                        </div>
                                        
                                        <span class="badge bg-secondary ms-2">
                                            <i class="fas fa-clock me-1"></i>
                                            <?php echo isset($baiViet['NgayTao']) ? date('d/m/Y H:i', strtotime($baiViet['NgayTao'])) : ''; ?>
                                        </span>
                                    </div>
                                    
                                    <div class="d-flex mb-3">
                                        <div class="me-4">
                                            <i class="fas fa-user text-primary me-2"></i>
                                            <strong>Người đăng:</strong> 
                                            <?php echo htmlspecialchars($baiViet['NguoiDang'] ?? ''); ?>
                                        </div>
                                        
                                        <?php if (!empty($baiViet['NguoiChinhSuaCuoi'])): ?>
                                        <div>
                                            <i class="fas fa-edit text-warning me-2"></i>
                                            <strong>Chỉnh sửa cuối:</strong> 
                                            <?php echo htmlspecialchars($baiViet['NguoiChinhSuaCuoi']); ?> 
                                            (<?php echo isset($baiViet['NgayChinhSua']) ? date('d/m/Y H:i', strtotime($baiViet['NgayChinhSua'])) : ''; ?>)
                                        </div>
                                        <?php endif; ?>
                                    </div>
                                    
                                    <?php if (!empty($baiViet['GhiChu'])): ?>
                                    <div class="mb-3">
                                        <i class="fas fa-sticky-note text-info me-2"></i>
                                        <strong>Ghi chú:</strong> 
                                        <?php echo htmlspecialchars($baiViet['GhiChu']); ?>
                                    </div>
                                    <?php endif; ?>
                                </div>
                                
                                <div class="content-area">
                                    <h4 class="border-bottom pb-2 mb-3">Nội dung bài viết</h4>
                                    <div class="article-content">
                                        <?php echo $baiViet['NoiDung'] ?? ''; ?>
                                    </div>
                                </div>
                                
                                <div class="mt-4">
                                    <a href="Edit.php?MaBV=<?php echo $baiViet['ID']; ?>" class="btn btn-warning">
                                        <i class="fas fa-edit me-1"></i> Chỉnh sửa
                                    </a>
                                    <a href="Index.php" class="btn btn-secondary ms-2">
                                        <i class="fas fa-list me-1"></i> Quay lại danh sách
                                    </a>
                                </div>
                            </div>
                            
                            <!-- Lịch sử chỉnh sửa -->
                            <div class="col-md-4">
                                <div class="history-panel sticky-top" style="top: 10px;">
                                    <h4 class="mb-3 border-bottom pb-2">
                                        <i class="fas fa-history me-2"></i> Lịch sử chỉnh sửa
                                    </h4>
                                    
                                    <?php if (count($lichSuChinhSua) > 0): ?>
                                        <div class="list-group">
                                            <?php foreach ($lichSuChinhSua as $ls): ?>
                                                <div class="list-group-item list-group-item-action flex-column align-items-start">
                                                    <div class="d-flex w-100 justify-content-between">
                                                        <h6 class="mb-1"><?php echo htmlspecialchars($ls['NguoiChinhSua']); ?></h6>
                                                        <small class="text-muted">
                                                            <?php echo date('d/m/Y H:i', strtotime($ls['NgayChinhSua'])); ?>
                                                        </small>
                                                    </div>
                                                    <p class="mb-1"><?php echo htmlspecialchars($ls['GhiChu']); ?></p>
                                                    <small class="text-muted">ID: <?php echo htmlspecialchars($ls['ID']); ?></small>
                                                </div>
                                            <?php endforeach; ?>
                                        </div>
                                    <?php else: ?>
                                        <div class="alert alert-info">
                                            <i class="fas fa-info-circle me-2"></i> 
                                            Bài viết chưa có lịch sử chỉnh sửa
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <style>
        /* Định dạng nội dung bài viết */
        .article-content {
            line-height: 1.6;
            font-size: 1rem;
        }
        
        .article-content img {
            max-width: 100%;
            height: auto;
        }
        
        .list-group-item {
            transition: all 0.2s;
        }
        
        .list-group-item:hover {
            background-color: #f8f9fa;
            transform: translateY(-2px);
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
        }
        
        .history-panel {
            border: 1px solid #e0e0e0;
            border-radius: 0.25rem;
            padding: 1rem;
            background-color: #f9f9f9;
        }
        
        @media (max-width: 767.98px) {
            .history-panel {
                margin-top: 2rem;
                position: static !important;
            }
        }
    </style>
<?php endif; ?>

<?php
// Lấy nội dung đã buffer và đưa vào layout
$contentForLayout = ob_get_clean();

// Kết nối với layout
include_once('../Shared/_LayoutAdmin.php');
?>
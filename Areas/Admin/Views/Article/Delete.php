<?php
// filepath: c:\xampp\htdocs\TDMU_website\Areas\Admin\Views\Article\Delete.php
// Kết nối database
require_once("../Shared/connect.inc");

// Tiêu đề trang
$pageTitle = "Xóa bài viết";

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
$message = '';
$messageType = '';

// Lấy thông tin bài viết
if ($maBV) {
    try {
        $stmt = $conn->prepare("
            SELECT 
                bv.*, 
                tlbv.Ten as TenTheLoai,
                tk.HoTen as NguoiDang
            FROM baiviet bv
            LEFT JOIN tlbaiviet tlbv ON bv.IDTLBaiViet = tlbv.ID
            LEFT JOIN taikhoan tk ON bv.IDNguoiDang = tk.ID
            WHERE bv.ID = ?
        ");
        $stmt->execute([$maBV]);
        
        if ($stmt->rowCount() > 0) {
            $baiViet = $stmt->fetch(PDO::FETCH_ASSOC);
        } else {
            // Bài viết không tồn tại
            header("Location: Index.php");
            exit;
        }
    } catch (PDOException $e) {
        error_log("Lỗi truy vấn bài viết: " . $e->getMessage());
    }
}

// Xử lý xóa bài viết
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['confirm_delete']) && $_POST['confirm_delete'] === 'yes') {
    try {
        // Bắt đầu transaction
        $conn->beginTransaction();
        
        // Xóa các bản ghi lịch sử chỉnh sửa
        $stmt = $conn->prepare("DELETE FROM baiviet_nguoichinhsua WHERE IDBaiViet = ?");
        $stmt->execute([$maBV]);
        
        // Xóa bài viết
        $stmt = $conn->prepare("DELETE FROM baiviet WHERE ID = ?");
        $stmt->execute([$maBV]);
        
        // Commit transaction
        $conn->commit();
        
        // Thông báo thành công và chuyển hướng về trang danh sách
        $_SESSION['message'] = "Đã xóa bài viết thành công!";
        $_SESSION['messageType'] = "success";
        header("Location: Index.php");
        exit;
        
    } catch (Exception $e) {
        // Rollback transaction nếu có lỗi
        $conn->rollBack();
        
        $message = "Lỗi khi xóa bài viết: " . $e->getMessage();
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
        <div class="row">
            <div class="col-12">
                <div class="card shadow-sm my-4">
                    <div class="card-header bg-danger text-white py-3 d-flex justify-content-between align-items-center">
                        <a href="Index.php" class="btn btn-light">
                            <i class="fas fa-arrow-left me-1"></i> Quay lại
                        </a>
                        <h2 class="text-center mb-0 flex-grow-1">XÁC NHẬN XÓA BÀI VIẾT</h2>
                        <div style="width: 100px;"></div><!-- Phần tử ẩn để cân bằng layout -->
                    </div>
                    
                    <div class="card-body">
                        <?php if (!empty($message)): ?>
                            <div class="alert alert-<?php echo $messageType; ?> alert-dismissible fade show" role="alert">
                                <?php echo $message; ?>
                                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                            </div>
                        <?php endif; ?>
                        
                        <div class="alert alert-warning">
                            <div class="d-flex align-items-center mb-3">
                                <i class="fas fa-exclamation-triangle fa-2x text-warning me-3"></i>
                                <div>
                                    <h4 class="mb-1">Cảnh báo</h4>
                                    <p class="mb-0">Hành động này sẽ xóa vĩnh viễn bài viết và không thể khôi phục!</p>
                                </div>
                            </div>
                        </div>
                        
                        <div class="card mb-4">
                            <div class="card-header bg-light">
                                <h4 class="mb-0">Thông tin bài viết</h4>
                            </div>
                            <div class="card-body">
                                <div class="row mb-3">
                                    <div class="col-md-4 fw-bold">ID bài viết:</div>
                                    <div class="col-md-8"><?php echo htmlspecialchars($baiViet['ID'] ?? ''); ?></div>
                                </div>
                                
                                <div class="row mb-3">
                                    <div class="col-md-4 fw-bold">Thể loại:</div>
                                    <div class="col-md-8"><?php echo htmlspecialchars($baiViet['TenTheLoai'] ?? ''); ?></div>
                                </div>
                                
                                <div class="row mb-3">
                                    <div class="col-md-4 fw-bold">Tiêu đề:</div>
                                    <div class="col-md-8"><?php echo htmlspecialchars($baiViet['TieuDe'] ?? ''); ?></div>
                                </div>
                                
                                <div class="row mb-3">
                                    <div class="col-md-4 fw-bold">Người đăng:</div>
                                    <div class="col-md-8"><?php echo htmlspecialchars($baiViet['NguoiDang'] ?? ''); ?></div>
                                </div>
                                
                                <div class="row mb-3">
                                    <div class="col-md-4 fw-bold">Ngày tạo:</div>
                                    <div class="col-md-8">
                                        <?php echo isset($baiViet['NgayTao']) ? date('d/m/Y H:i', strtotime($baiViet['NgayTao'])) : ''; ?>
                                    </div>
                                </div>
                                
                                <div class="row mb-3">
                                    <div class="col-md-4 fw-bold">Ghi chú:</div>
                                    <div class="col-md-8"><?php echo htmlspecialchars($baiViet['GhiChu'] ?? ''); ?></div>
                                </div>
                                
                                <div class="row">
                                    <div class="col-12">
                                        <div class="fw-bold mb-2">Nội dung bài viết:</div>
                                        <div class="border p-3 bg-light rounded">
                                            <?php echo $baiViet['NoiDung'] ?? ''; ?>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <form method="post" id="deleteForm" class="mt-4">
                            <input type="hidden" name="confirm_delete" value="yes">
                            
                            <div class="d-flex justify-content-between">
                                <a href="Index.php" class="btn btn-secondary">
                                    <i class="fas fa-times me-1"></i> Hủy
                                </a>
                                <button type="submit" class="btn btn-danger" onclick="return confirmDelete()">
                                    <i class="fas fa-trash-alt me-1"></i> Xóa bài viết
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
        function confirmDelete() {
            return confirm('Bạn có chắc chắn muốn xóa bài viết này không?');
        }
    </script>
<?php endif; ?>

<?php
// Lấy nội dung đã buffer và đưa vào layout
$contentForLayout = ob_get_clean();

// Kết nối với layout
include_once('../Shared/_LayoutAdmin.php');
?>
<?php
// filepath: c:\xampp\htdocs\TDMU_website\Areas\Admin\Views\Notify\Delete.php
// Kết nối database
require_once("../Shared/connect.inc");

// Tiêu đề trang
$pageTitle = "Xóa thông báo";

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

// Lấy ID thông báo từ URL
$id = isset($_GET['id']) ? $_GET['id'] : null;

// Lấy thông tin thông báo cần xóa
$thongBao = null;
if ($id) {
    try {
        $stmt = $conn->prepare("
            SELECT tb.*, ltb.Ten as LoaiThongBao,
            tk.HoTen as TenNguoiTao
            FROM thongbao tb
            LEFT JOIN loaithongbao ltb ON tb.IDLoaiThongBao = ltb.ID
            LEFT JOIN taikhoan tk ON tb.IDNguoiTao = tk.ID
            WHERE tb.ID = ?
        ");
        $stmt->execute([$id]);
        $thongBao = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // Nếu không có người tạo trong bảng thongbao, tìm trong bảng chỉnh sửa
        if ($thongBao && empty($thongBao['TenNguoiTao'])) {
            $stmtNguoiTao = $conn->prepare("
                SELECT tbncs.*, tk.HoTen as TenNguoiChinhSua
                FROM thongbao_nguoichinhsua tbncs
                LEFT JOIN taikhoan tk ON tbncs.IDNguoiChinhSua = tk.ID
                WHERE tbncs.IDThongBao = ?
                ORDER BY tbncs.NgayChinhSua ASC
                LIMIT 1
            ");
            $stmtNguoiTao->execute([$id]);
            $nguoiTao = $stmtNguoiTao->fetch(PDO::FETCH_ASSOC);
            if ($nguoiTao) {
                $thongBao['TenNguoiTao'] = $nguoiTao['TenNguoiChinhSua'];
                $thongBao['NgayTao'] = $nguoiTao['NgayChinhSua'];
            }
        }
    } catch (PDOException $e) {
        error_log("Lỗi truy vấn thông tin thông báo: " . $e->getMessage());
    }
}

// Xử lý form submit
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $id && $accessLevel <= 2) {
    try {
        // Bắt đầu transaction
        $conn->beginTransaction();
        
        // Xóa người nhận trước
        $stmtXoaNguoiNhan = $conn->prepare("DELETE FROM thongbao_nguoinhan WHERE IDThongBao = ?");
        $stmtXoaNguoiNhan->execute([$id]);
        
        // Xóa người chỉnh sửa
        $stmtXoaNguoiChinhSua = $conn->prepare("DELETE FROM thongbao_nguoichinhsua WHERE IDThongBao = ?");
        $stmtXoaNguoiChinhSua->execute([$id]);
        
        // Cuối cùng xóa thông báo
        $stmtXoaThongBao = $conn->prepare("DELETE FROM thongbao WHERE ID = ?");
        $stmtXoaThongBao->execute([$id]);
        
        // Hoàn tất transaction
        $conn->commit();
        
        // Thông báo và chuyển hướng
        $_SESSION['message'] = "Xóa thông báo thành công!";
        $_SESSION['messageType'] = "success";
        header("Location: Index.php");
        exit;
    } catch (PDOException $e) {
        // Rollback nếu có lỗi
        $conn->rollBack();
        error_log("Lỗi khi xóa thông báo: " . $e->getMessage());
        $_SESSION['message'] = "Đã xảy ra lỗi khi xóa thông báo: " . $e->getMessage();
        $_SESSION['messageType'] = "danger";
    }
}

// Bắt đầu output buffer
ob_start();
?>

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
<?php elseif ($accessLevel > 2): ?>
    <div class="container-fluid px-4">
        <div class="row">
            <div class="col-12">
                <div class="alert alert-danger my-4 p-5 text-center">
                    <div class="mb-3">
                        <i class="fas fa-lock fa-5x text-danger"></i>
                    </div>
                    <h2 class="text-danger">BẠN KHÔNG CÓ QUYỀN XÓA THÔNG BÁO!</h2>
                    <h4>Chỉ người dùng có cấp độ quyền 2 trở xuống mới có thể xóa thông báo.</h4>
                    <div class="mt-4">
                        <a href="Index.php" class="btn btn-primary">
                            <i class="fas fa-arrow-left me-2"></i>Quay lại danh sách thông báo
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
<?php elseif (!$thongBao): ?>
    <div class="container-fluid px-4">
        <div class="row">
            <div class="col-12">
                <div class="alert alert-warning my-4 p-5 text-center">
                    <div class="mb-3">
                        <i class="fas fa-search fa-5x text-warning"></i>
                    </div>
                    <h2 class="text-warning">KHÔNG TÌM THẤY THÔNG BÁO!</h2>
                    <h4>Thông báo bạn đang tìm kiếm không tồn tại hoặc đã bị xóa.</h4>
                    <div class="mt-4">
                        <a href="index.php" class="btn btn-primary">
                            <i class="fas fa-arrow-left me-2"></i>Quay lại danh sách thông báo
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
                        <li class="breadcrumb-item"><a href="index.php">Quản lý thông báo</a></li>
                        <li class="breadcrumb-item active" aria-current="page">Xóa thông báo</li>
                    </ol>
                </nav>
            </div>
        </div>
        
        <div class="row">
            <div class="col-12">
                <div class="card shadow-sm border-danger mb-4">
                    <div class="card-header bg-danger text-white">
                        <h5 class="mb-0">
                            <i class="fas fa-trash-alt me-2"></i>Xác nhận xóa thông báo
                        </h5>
                    </div>
                    <div class="card-body">
                        <div class="alert alert-warning">
                            <i class="fas fa-exclamation-triangle me-2"></i>
                            <strong>Cảnh báo:</strong> Bạn đang chuẩn bị xóa thông báo này. Hành động này không thể hoàn tác!
                        </div>
                        
                        <div class="row mb-4">
                            <div class="col-md-8 offset-md-2">
                                <div class="card bg-light">
                                    <div class="card-header text-center">
                                        <h5 class="text-danger mb-0">Thông tin thông báo sẽ bị xóa</h5>
                                    </div>
                                    <div class="card-body">
                                        <table class="table table-bordered">
                                            <tbody>
                                                <tr>
                                                    <th style="width: 30%">ID thông báo:</th>
                                                    <td><?php echo htmlspecialchars($thongBao['ID']); ?></td>
                                                </tr>
                                                <tr>
                                                    <th>Loại thông báo:</th>
                                                    <td>
                                                        <?php if ($thongBao['LoaiThongBao']): ?>
                                                            <span class="badge bg-primary"><?php echo htmlspecialchars($thongBao['LoaiThongBao']); ?></span>
                                                        <?php else: ?>
                                                            <span class="badge bg-secondary">Không phân loại</span>
                                                        <?php endif; ?>
                                                    </td>
                                                </tr>
                                                <tr>
                                                    <th>Tiêu đề:</th>
                                                    <td><?php echo htmlspecialchars($thongBao['TieuDe']); ?></td>
                                                </tr>
                                                <tr>
                                                    <th>Người gửi:</th>
                                                    <td><?php echo htmlspecialchars($thongBao['TenNguoiTao'] ?? 'Không xác định'); ?></td>
                                                </tr>
                                                <tr>
                                                    <th>Ngày gửi:</th>
                                                    <td>
                                                        <?php echo $thongBao['NgayTao'] ? date('d/m/Y H:i', strtotime($thongBao['NgayTao'])) : 'Không xác định'; ?>
                                                    </td>
                                                </tr>
                                                <tr>
                                                    <th>Nội dung:</th>
                                                    <td>
                                                        <div class="border p-3 rounded bg-white overflow-auto" style="max-height: 200px;">
                                                            <?php echo $thongBao['NoiDung']; ?>
                                                        </div>
                                                    </td>
                                                </tr>
                                                <?php if (!empty($thongBao['GhiChu'])): ?>
                                                <tr>
                                                    <th>Ghi chú:</th>
                                                    <td><?php echo htmlspecialchars($thongBao['GhiChu']); ?></td>
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
                                <a href="Index.php" class="btn btn-secondary">
                                    <i class="fas fa-arrow-left me-2"></i>Hủy & Quay lại
                                </a>
                                <button type="submit" class="btn btn-danger">
                                    <i class="fas fa-trash-alt me-2"></i>Xác nhận xóa
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <style>
        .overflow-auto::-webkit-scrollbar {
            width: 6px;
            height: 6px;
        }
        .overflow-auto::-webkit-scrollbar-thumb {
            background-color: rgba(0, 0, 0, 0.2);
            border-radius: 3px;
        }
        .overflow-auto::-webkit-scrollbar-track {
            background: rgba(0, 0, 0, 0.05);
        }
    </style>
<?php endif; ?>

<?php
$contentForLayout = ob_get_clean();
require_once('../Shared/_LayoutAdmin.php');
?>
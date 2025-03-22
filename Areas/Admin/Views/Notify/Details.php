<?php
// filepath: c:\xampp\htdocs\TDMU_website\Areas\Admin\Views\Notify\Details.php
// Kết nối database
require_once("../Shared/connect.inc");

// Tiêu đề trang
$pageTitle = "Chi tiết thông báo";

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

// Truy vấn thông tin thông báo
$thongBao = null;
$nguoiGui = null;
$loaiThongBao = null;
$nguoiChinhSua = [];
$nguoiNhan = [];

if ($id) {
    try {
        // Truy vấn thông tin chính
        $stmtThongBao = $conn->prepare("
            SELECT tb.*, ltb.Ten as LoaiThongBao, ltb.CapDo as CapDoThongBao,
            tk.HoTen as TenNguoiTao
            FROM thongbao tb
            LEFT JOIN loaithongbao ltb ON tb.IDLoaiThongBao = ltb.ID
            LEFT JOIN taikhoan tk ON tb.IDNguoiTao = tk.ID
            WHERE tb.ID = ?
        ");
        $stmtThongBao->execute([$id]);
        $thongBao = $stmtThongBao->fetch(PDO::FETCH_ASSOC);
        
        // Lấy thông tin người gửi (từ thongbao_nguoichinhsua - người tạo đầu tiên)
        if ($thongBao) {
            // Nếu không có TenNguoiTao, lấy từ bảng chỉnh sửa
            if (empty($thongBao['TenNguoiTao'])) {
                $stmtNguoiGui = $conn->prepare("
                    SELECT tbncs.*, tk.HoTen as TenNguoiGui
                    FROM thongbao_nguoichinhsua tbncs
                    LEFT JOIN taikhoan tk ON tbncs.IDNguoiChinhSua = tk.ID
                    WHERE tbncs.IDThongBao = ?
                    ORDER BY tbncs.NgayChinhSua ASC
                    LIMIT 1
                ");
                $stmtNguoiGui->execute([$id]);
                $nguoiGui = $stmtNguoiGui->fetch(PDO::FETCH_ASSOC);
            }
            
            // Lấy danh sách người chỉnh sửa
            $stmtChinhSua = $conn->prepare("
                SELECT tbncs.*, tk.HoTen as TenNguoiChinhSua
                FROM thongbao_nguoichinhsua tbncs
                LEFT JOIN taikhoan tk ON tbncs.IDNguoiChinhSua = tk.ID
                WHERE tbncs.IDThongBao = ?
                ORDER BY tbncs.NgayChinhSua DESC
            ");
            $stmtChinhSua->execute([$id]);
            $nguoiChinhSua = $stmtChinhSua->fetchAll(PDO::FETCH_ASSOC);
            
            // Lấy danh sách người nhận
            $stmtNguoiNhan = $conn->prepare("
                SELECT tbnn.*, tk.HoTen as TenNguoiNhan
                FROM thongbao_nguoinhan tbnn
                LEFT JOIN taikhoan tk ON tbnn.IDNguoiNhan = tk.ID
                WHERE tbnn.IDThongBao = ?
                ORDER BY tbnn.NgayNhan DESC
            ");
            $stmtNguoiNhan->execute([$id]);
            $nguoiNhan = $stmtNguoiNhan->fetchAll(PDO::FETCH_ASSOC);
        }
    } catch (PDOException $e) {
        error_log("Lỗi truy vấn thông báo: " . $e->getMessage());
    }
}

// Bắt đầu buffer đầu ra
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
                        <li class="breadcrumb-item active" aria-current="page">Chi tiết thông báo</li>
                    </ol>
                </nav>
            </div>
        </div>
        
        <div class="row">
            <div class="col-lg-9">
                <!-- Thông tin chính của thông báo -->
                <div class="card shadow-sm mb-4">
                    <div class="card-header bg-primary text-white d-flex justify-content-between align-items-center">
                        <h5 class="mb-0">
                            <i class="fas fa-bell me-2"></i>Chi tiết thông báo
                        </h5>
                        <?php if ($thongBao['IDLoaiThongBao']): ?>
                            <span class="badge bg-light text-primary fw-bold">
                                <?php echo htmlspecialchars($thongBao['LoaiThongBao'] ?? 'Không phân loại'); ?>
                            </span>
                        <?php endif; ?>
                    </div>
                    <div class="card-body">
                        <!-- Tiêu đề và thông tin cơ bản -->
                        <div class="mb-4 pb-3 border-bottom">
                            <h3 class="text-primary mb-3"><?php echo htmlspecialchars($thongBao['TieuDe']); ?></h3>
                            
                            <div class="d-flex flex-wrap text-muted small mb-3">
                                <!-- Hiển thị thông tin người gửi từ IDNguoiTao hoặc từ bảng chỉnh sửa -->
                                <div class="me-4 mb-2">
                                    <i class="fas fa-user me-1"></i> Người gửi: 
                                    <strong>
                                        <?php if (!empty($thongBao['TenNguoiTao'])): ?>
                                            <?php echo htmlspecialchars($thongBao['TenNguoiTao']); ?>
                                        <?php elseif ($nguoiGui): ?>
                                            <?php echo htmlspecialchars($nguoiGui['TenNguoiGui']); ?>
                                        <?php else: ?>
                                            Không xác định
                                        <?php endif; ?>
                                    </strong>
                                </div>
                                <div class="me-4 mb-2">
                                    <i class="fas fa-calendar-alt me-1"></i> Ngày gửi: 
                                    <strong>
                                        <?php if (!empty($thongBao['NgayTao'])): ?>
                                            <?php echo date('d/m/Y H:i', strtotime($thongBao['NgayTao'])); ?>
                                        <?php elseif ($nguoiGui): ?>
                                            <?php echo date('d/m/Y H:i', strtotime($nguoiGui['NgayChinhSua'])); ?>
                                        <?php else: ?>
                                            Không xác định
                                        <?php endif; ?>
                                    </strong>
                                </div>
                                <div class="mb-2">
                                    <i class="fas fa-hashtag me-1"></i> Mã thông báo: 
                                    <strong><?php echo htmlspecialchars($thongBao['ID']); ?></strong>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Nội dung thông báo -->
                        <div class="mb-4">
                            <h5 class="text-primary mb-3">Nội dung thông báo</h5>
                            <div class="bg-light p-4 rounded mb-3 content-area border">
                                <?php echo $thongBao['NoiDung']; ?>
                            </div>
                            
                            <?php if ($thongBao['GhiChu']): ?>
                            <div class="mt-3">
                                <h5 class="text-primary mb-2">Ghi chú</h5>
                                <div class="bg-light p-3 rounded border">
                                    <?php echo htmlspecialchars($thongBao['GhiChu']); ?>
                                </div>
                            </div>
                            <?php endif; ?>
                        </div>
                        
                        <div class="d-flex justify-content-between mt-4">
                            <a href="index.php" class="btn btn-secondary">
                                <i class="fas fa-arrow-left me-2"></i>Quay lại danh sách
                            </a>
                            
                            <div>
                                <?php if ($accessLevel <= 3): ?>
                                <a href="edit.php?id=<?php echo urlencode($thongBao['ID']); ?>" class="btn btn-primary me-2">
                                    <i class="fas fa-edit me-2"></i>Chỉnh sửa
                                </a>
                                <?php endif; ?>
                                
                                <?php if ($accessLevel <= 2): ?>
                                <button type="button" class="btn btn-danger" data-bs-toggle="modal" data-bs-target="#deleteModal">
                                    <i class="fas fa-trash-alt me-2"></i>Xóa
                                </button>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="col-lg-3">
                <!-- Thông tin người nhận và lịch sử chỉnh sửa -->
                <!-- Card: Thống kê -->
                <div class="card shadow-sm mb-4">
                    <div class="card-header bg-info text-white">
                        <h6 class="mb-0"><i class="fas fa-chart-pie me-2"></i>Thống kê</h6>
                    </div>
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <span>Số người nhận:</span>
                            <span class="badge bg-primary"><?php echo count($nguoiNhan); ?></span>
                        </div>
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <span>Số lần chỉnh sửa:</span>
                            <span class="badge bg-secondary"><?php echo count($nguoiChinhSua); ?></span>
                        </div>
                        <div class="d-flex justify-content-between align-items-center">
                            <span>Độ ưu tiên:</span>
                            <?php if ($thongBao['CapDoThongBao'] == 1): ?>
                                <span class="badge bg-danger">Quan trọng</span>
                            <?php elseif ($thongBao['CapDoThongBao'] == 2): ?>
                                <span class="badge bg-warning">Trung bình</span>
                            <?php else: ?>
                                <span class="badge bg-info">Thông thường</span>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                
                <!-- Card: Người nhận (hiển thị 5 người đầu) -->
                <?php if (!empty($nguoiNhan)): ?>
                <div class="card shadow-sm mb-4">
                    <div class="card-header bg-success text-white">
                        <h6 class="mb-0"><i class="fas fa-users me-2"></i>Người nhận</h6>
                    </div>
                    <div class="list-group list-group-flush">
                        <?php 
                        $displayLimit = min(5, count($nguoiNhan));
                        for ($i = 0; $i < $displayLimit; $i++): ?>
                            <div class="list-group-item">
                                <div class="d-flex justify-content-between">
                                    <div>
                                        <i class="fas fa-user-circle me-2 text-muted"></i>
                                        <?php echo htmlspecialchars($nguoiNhan[$i]['TenNguoiNhan'] ?? 'Không xác định'); ?>
                                    </div>
                                    <small class="text-muted">
                                        <?php echo date('d/m/Y', strtotime($nguoiNhan[$i]['NgayNhan'])); ?>
                                    </small>
                                </div>
                            </div>
                        <?php endfor; ?>
                        
                        <!--"Xem tất cả" trong card người nhận -->
                        <?php if (count($nguoiNhan) > 5): ?>
                            <div class="list-group-item text-center">
                                <button class="btn btn-sm btn-link" type="button" data-bs-toggle="modal" data-bs-target="#modalDanhSachNguoiNhan">
                                    <i class="fas fa-external-link-alt me-1"></i> Xem tất cả (<?php echo count($nguoiNhan); ?>)
                                </button>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endif; ?>
                
                <!-- Card: Lịch sử chỉnh sửa -->
                <?php if (!empty($nguoiChinhSua) && count($nguoiChinhSua) > 1): ?>
                <div class="card shadow-sm">
                    <div class="card-header bg-warning text-dark">
                        <h6 class="mb-0"><i class="fas fa-history me-2"></i>Lịch sử chỉnh sửa</h6>
                    </div>
                    <div class="list-group list-group-flush">
                        <?php 
                        $displayLimit = min(3, count($nguoiChinhSua));
                        for ($i = 0; $i < $displayLimit; $i++): ?>
                            <div class="list-group-item">
                                <div>
                                    <strong><?php echo htmlspecialchars($nguoiChinhSua[$i]['TenNguoiChinhSua'] ?? 'Không xác định'); ?></strong>
                                </div>
                                <small class="text-muted">
                                    <i class="fas fa-clock me-1"></i>
                                    <?php echo date('d/m/Y H:i', strtotime($nguoiChinhSua[$i]['NgayChinhSua'])); ?>
                                    <br>
                                    <i class="fas fa-comment-alt me-1"></i>
                                    <?php echo htmlspecialchars($nguoiChinhSua[$i]['GhiChu'] ?: 'Không có ghi chú'); ?>
                                </small>
                            </div>
                        <?php endfor; ?>
                        
                        <!-- Sửa nút "Xem tất cả" trong card lịch sử chỉnh sửa -->
                        <?php if (count($nguoiChinhSua) > 3): ?>
                            <div class="list-group-item text-center">
                                <button class="btn btn-sm btn-link" type="button" data-bs-toggle="modal" data-bs-target="#modalDanhSachChinhSua">
                                    <i class="fas fa-external-link-alt me-1"></i> Xem tất cả (<?php echo count($nguoiChinhSua); ?>)
                                </button>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Phần mở rộng: Danh sách đầy đủ người nhận -->
        <?php if (!empty($nguoiNhan) && count($nguoiNhan) > 5): ?>
        <div class="modal fade" id="modalDanhSachNguoiNhan" tabindex="-1" aria-labelledby="modalNguoiNhanLabel" aria-hidden="true">
            <div class="modal-dialog modal-lg">
                <div class="modal-content">
                    <div class="modal-header bg-success text-white">
                        <h5 class="modal-title" id="modalNguoiNhanLabel">
                            <i class="fas fa-users me-2"></i>Danh sách đầy đủ người nhận
                        </h5>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body p-0">
                        <div class="table-responsive">
                            <table class="table table-hover table-bordered mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th style="width: 60px">#</th>
                                        <th>Người nhận</th>
                                        <th style="width: 180px">Thời gian nhận</th>
                                        <th style="width: 30%">Ghi chú</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($nguoiNhan as $index => $nn): ?>
                                    <tr>
                                        <td class="text-center"><?php echo $index + 1; ?></td>
                                        <td><?php echo htmlspecialchars($nn['TenNguoiNhan'] ?? 'Không xác định'); ?></td>
                                        <td class="text-center"><?php echo date('d/m/Y H:i', strtotime($nn['NgayNhan'])); ?></td>
                                        <td><?php echo htmlspecialchars($nn['GhiChu'] ?: 'Không có'); ?></td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Đóng</button>
                    </div>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <!-- Modal Lịch sử đầy đủ chỉnh sửa -->
        <?php if (!empty($nguoiChinhSua) && count($nguoiChinhSua) > 3): ?>
        <div class="modal fade" id="modalDanhSachChinhSua" tabindex="-1" aria-labelledby="modalChinhSuaLabel" aria-hidden="true">
            <div class="modal-dialog modal-lg">
                <div class="modal-content">
                    <div class="modal-header bg-warning text-dark">
                        <h5 class="modal-title" id="modalChinhSuaLabel">
                            <i class="fas fa-history me-2"></i>Lịch sử đầy đủ chỉnh sửa
                        </h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body p-0">
                        <div class="table-responsive">
                            <table class="table table-hover table-bordered mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th style="width: 60px">#</th>
                                        <th>Người chỉnh sửa</th>
                                        <th style="width: 180px">Thời gian</th>
                                        <th style="width: 30%">Ghi chú</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($nguoiChinhSua as $index => $ncs): ?>
                                    <tr>
                                        <td class="text-center"><?php echo $index + 1; ?></td>
                                        <td><?php echo htmlspecialchars($ncs['TenNguoiChinhSua'] ?? 'Không xác định'); ?></td>
                                        <td class="text-center"><?php echo date('d/m/Y H:i', strtotime($ncs['NgayChinhSua'])); ?></td>
                                        <td><?php echo htmlspecialchars($ncs['GhiChu'] ?: 'Không có'); ?></td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Đóng</button>
                    </div>
                </div>
            </div>
        </div>
        <?php endif; ?>
    </div>
    
    <?php if ($accessLevel <= 2): ?>
    <!-- Modal Xác nhận xóa -->
    <div class="modal fade" id="deleteModal" tabindex="-1" aria-labelledby="deleteModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header bg-danger text-white">
                    <h5 class="modal-title" id="deleteModalLabel">Xác nhận xóa thông báo</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <p>Bạn có chắc chắn muốn xóa thông báo này?</p>
                    <p><strong>Tiêu đề:</strong> <?php echo htmlspecialchars($thongBao['TieuDe']); ?></p>
                    <p class="text-danger"><strong>Lưu ý:</strong> Hành động này không thể hoàn tác!</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Hủy</button>
                    <a href="delete.php?id=<?php echo urlencode($thongBao['ID']); ?>" class="btn btn-danger">Xác nhận xóa</a>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <style>
        .content-area {
            min-height: 200px;
        }
        .content-area img {
            max-width: 100%;
            height: auto;
        }
        .content-area table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 1rem;
        }
        .content-area table, .content-area th, .content-area td {
            border: 1px solid #dee2e6;
        }
        .content-area th, .content-area td {
            padding: 0.5rem;
        }
        .content-area th {
            background-color: #f8f9fa;
        }
        /* Thêm media query để cải thiện hiển thị trên mobile */
        @media (max-width: 767px) {
            .card-body {
                padding: 1rem;
            }
            .content-area {
                padding: 1rem !important;
            }
        }
    </style>
<?php endif; ?>

<?php
$contentForLayout = ob_get_clean();
require_once('../Shared/_LayoutAdmin.php');
?>
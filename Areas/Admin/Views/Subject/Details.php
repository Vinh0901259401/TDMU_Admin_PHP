<?php
// filepath: c:\xampp\htdocs\TDMU_website\Areas\Admin\Views\Subject\Details.php
// Kết nối database
require_once("../Shared/connect.inc");

// Tiêu đề trang
$pageTitle = "Chi tiết môn học";

// Khởi tạo session và lấy thông tin người dùng
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

// Lấy ID môn học từ URL
$id = isset($_GET['id']) ? $_GET['id'] : null;
$monHoc = null;
$nhomMonHoc = [];
$nguoiChinhSua = [];

// Định nghĩa các hằng số và mảng hỗ trợ
$thuTrongTuan = [
    1 => 'Thứ Hai', 2 => 'Thứ Ba', 3 => 'Thứ Tư', 4 => 'Thứ Năm',
    5 => 'Thứ Sáu', 6 => 'Thứ Bảy', 7 => 'Chủ Nhật'
];

// Truy vấn dữ liệu nếu có ID
if ($id) {
    try {
        // 1. Lấy thông tin môn học
        $stmtMonHoc = $conn->prepare("
            SELECT mh.*, hk.Ten as TenHocKy, nh.Ten as TenNamHoc, k.Ten as TenKhoa,
            CASE 
                WHEN mh.LoaiMonHoc = 0 THEN 'Bắt buộc'
                WHEN mh.LoaiMonHoc = 1 THEN 'Tự chọn'
                WHEN mh.LoaiMonHoc = 2 THEN 'Điều kiện'
                ELSE 'Không xác định'
            END as LoaiMonHocText
            FROM monhoc mh
            LEFT JOIN hocky hk ON mh.IDHocKy = hk.ID
            LEFT JOIN namhoc nh ON mh.IDNamHoc = nh.ID
            LEFT JOIN khoa k ON mh.IDKhoa = k.ID
            WHERE mh.ID = ?
        ");
        $stmtMonHoc->execute([$id]);
        $monHoc = $stmtMonHoc->fetch(PDO::FETCH_ASSOC);
        
        // 2. Nếu tìm thấy môn học, tiếp tục lấy các thông tin khác
        if ($monHoc) {
            // a. Lấy danh sách nhóm môn học và lọc trùng lặp
            try {
                $stmtNhomMH = $conn->prepare("
                    SELECT 
                        nmh.ID, nmh.MaNhom, nmh.TenNhom, nmh.SoLuongToiDa, 
                        nmh.IDGiangVien, nmh.IDPhongHoc, nmh.IDMonHoc, 
                        nmh.NgayBatDau, nmh.NgayKetThuc, nmh.GhiChu,
                        tk.HoTen as TenGiangVien, 
                        tk.TenTaiKhoan as MaGiangVien,
                        ph.TenPhong, 
                        ph.MaPhong,
                        IFNULL((SELECT COUNT(*) FROM nhommonhoc_sinhvien WHERE IDNhomMonHoc = nmh.ID), 0) as SoLuongSV
                    FROM nhommonhoc nmh
                    LEFT JOIN taikhoan tk ON nmh.IDGiangVien = tk.ID
                    LEFT JOIN phonghoc ph ON nmh.IDPhongHoc = ph.ID
                    WHERE nmh.IDMonHoc = ?
                    GROUP BY nmh.ID
                    ORDER BY nmh.MaNhom ASC
                ");
                $stmtNhomMH->execute([$id]);
                $nhomMonHoc = $stmtNhomMH->fetchAll(PDO::FETCH_ASSOC);
                
                // b. Lấy và xử lý thông tin buổi học cho mỗi nhóm
                foreach ($nhomMonHoc as &$nhom) {
                    // Lấy các buổi học của nhóm
                    $stmtBuoiHoc = $conn->prepare("
                        SELECT 
                            bh.*, 
                            ph.TenPhong, 
                            ph.MaPhong
                        FROM buoihoc bh
                        LEFT JOIN phonghoc ph ON bh.IDPhongHoc = ph.ID
                        WHERE bh.IDNhomMonHoc = ?
                        ORDER BY bh.ThuHoc, bh.TietBatDau
                    ");
                    $stmtBuoiHoc->execute([$nhom['ID']]);
                    $dsBuoiHoc = $stmtBuoiHoc->fetchAll(PDO::FETCH_ASSOC);

                    // Xử lý và lọc buổi học trùng lặp
                    $nhom['DanhSachBuoiHoc'] = [];
                    $uniqueKeys = [];

                    foreach ($dsBuoiHoc as $buoi) {
                        // Tạo khóa duy nhất
                        $key = $buoi['ThuHoc'] . '_' . $buoi['TietBatDau'] . '_' . $buoi['TietKetThuc'] . '_' . $buoi['IDPhongHoc'];
                        
                        if (!in_array($key, $uniqueKeys)) {
                            // Thêm mô tả thời gian
                            $buoi['MoTaThoiGian'] = $thuTrongTuan[$buoi['ThuHoc']] ?? 'Không xác định';
                            $buoi['MoTaThoiGian'] .= ' (Tiết ' . $buoi['TietBatDau'] . '-' . $buoi['TietKetThuc'] . ')';
                            
                            $uniqueKeys[] = $key;
                            $nhom['DanhSachBuoiHoc'][] = $buoi;
                        }
                    }
                    
                    // Đảm bảo SoLuongSV luôn có giá trị
                    $nhom['SoLuongSV'] = $nhom['SoLuongSV'] ?? 0;
                }
            } catch (PDOException $e) {
                error_log("Lỗi truy vấn nhóm môn học: " . $e->getMessage());
            }
            
            // c. Lấy lịch sử chỉnh sửa môn học
            try {
                $stmtHistory = $conn->prepare("
                    SELECT mhncs.*, tk.HoTen as TenNguoiChinhSua
                    FROM monhoc_nguoichinhsua mhncs
                    LEFT JOIN taikhoan tk ON mhncs.IDNguoiThucHien = tk.ID
                    WHERE mhncs.IDMonHoc = ?
                    ORDER BY mhncs.NgayThucHien DESC
                    LIMIT 5
                ");
                $stmtHistory->execute([$id]);
                $nguoiChinhSua = $stmtHistory->fetchAll(PDO::FETCH_ASSOC);
            } catch (PDOException $e) {
                error_log("Lỗi truy vấn lịch sử chỉnh sửa: " . $e->getMessage());
            }
        }
    } catch (PDOException $e) {
        error_log("Lỗi truy vấn thông tin môn học: " . $e->getMessage());
    }
}

// Bắt đầu buffer đầu ra
ob_start();
?>

<?php if ($accessLevel > 4 || $accessLevel < 1): ?>
    <div class="container-fluid px-4">
        <div class="alert alert-danger my-4 text-center">
            <i class="fas fa-exclamation-triangle fa-3x mb-3"></i>
            <h4>BẠN KHÔNG CÓ QUYỀN XEM THÔNG TIN NÀY!</h4>
        </div>
    </div>
<?php elseif (!$monHoc): ?>
    <div class="container-fluid px-4">
        <div class="alert alert-warning my-4 text-center">
            <i class="fas fa-search fa-3x mb-3"></i>
            <h4>KHÔNG TÌM THẤY MÔN HỌC!</h4>
            <a href="index.php" class="btn btn-primary mt-3"><i class="fas fa-arrow-left me-2"></i>Quay lại</a>
        </div>
    </div>
<?php else: ?>
    <div class="container-fluid px-4">
        <!-- Breadcrumb -->
        <nav aria-label="breadcrumb" class="mb-3">
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="../Dashboard/Index.php">Trang chủ</a></li>
                <li class="breadcrumb-item"><a href="index.php">Quản lý môn học</a></li>
                <li class="breadcrumb-item active">Chi tiết môn học</li>
            </ol>
        </nav>
        
        <div class="row">
            <!-- Cột chính: Thông tin môn học -->
            <div class="col-lg-8">
                <div class="card shadow-sm mb-4">
                    <div class="card-header bg-primary text-white d-flex justify-content-between align-items-center">
                        <h5 class="mb-0"><i class="fas fa-book me-2"></i>Thông tin môn học</h5>
                        <?php 
                            $badgeClass = 'bg-primary';
                            if ($monHoc['LoaiMonHoc'] == 1) $badgeClass = 'bg-success';
                            if ($monHoc['LoaiMonHoc'] == 2) $badgeClass = 'bg-info';
                        ?>
                        <span class="badge <?php echo $badgeClass; ?>"><?php echo $monHoc['LoaiMonHocText']; ?></span>
                    </div>
                    <div class="card-body">
                        <h3 class="text-primary mb-3"><?php echo htmlspecialchars($monHoc['TenMonHoc']); ?></h3>
                        
                        <div class="row mb-4">
                            <div class="col-md-4 mb-2">
                                <span class="fw-bold">Mã môn học:</span> <?php echo htmlspecialchars($monHoc['MaMonHoc']); ?>
                            </div>
                            <div class="col-md-4 mb-2">
                                <span class="fw-bold">Số tín chỉ:</span> <?php echo $monHoc['SoTinChi']; ?>
                            </div>
                            <div class="col-md-4 mb-2">
                                <span class="fw-bold">Khoa:</span> <?php echo htmlspecialchars($monHoc['TenKhoa'] ?? 'N/A'); ?>
                            </div>
                            <div class="col-md-4 mb-2">
                                <span class="fw-bold">Học kỳ:</span> <?php echo htmlspecialchars($monHoc['TenHocKy'] ?? 'N/A'); ?>
                            </div>
                            <div class="col-md-4 mb-2">
                                <span class="fw-bold">Năm học:</span> <?php echo htmlspecialchars($monHoc['TenNamHoc'] ?? 'N/A'); ?>
                            </div>
                            <div class="col-md-4 mb-2">
                                <span class="fw-bold">Loại môn học:</span> 
                                <span class="badge <?php echo $badgeClass; ?>"><?php echo $monHoc['LoaiMonHocText']; ?></span>
                            </div>
                        </div>
                        
                        <?php if (!empty($monHoc['GhiChu'])): ?>
                        <div class="alert alert-light">
                            <span class="fw-bold">Ghi chú:</span> <?php echo htmlspecialchars($monHoc['GhiChu']); ?>
                        </div>
                        <?php endif; ?>
                        
                        <!-- Danh sách nhóm môn học -->
                        <h5 class="border-bottom pb-2 mt-4 mb-3 text-primary">
                            <i class="fas fa-layer-group me-2"></i>Nhóm môn học
                            <?php if (!empty($nhomMonHoc)): ?>
                                <span class="badge bg-primary ms-2"><?php echo count($nhomMonHoc); ?></span>
                            <?php endif; ?>
                        </h5>

                        <?php if (!empty($nhomMonHoc)): ?>
                            <div class="table-responsive">
                                <!-- Debug info -->
                                <!-- Số nhóm: <?php echo count($nhomMonHoc); ?> -->
                                <table class="table table-hover table-bordered">
                                    <thead class="table-light">
                                        <tr>
                                            <th style="width: 80px;">Mã nhóm</th>
                                            <th>Giảng viên</th>
                                            <th>Thời gian - Phòng học</th>
                                            <th style="width: 70px;">Sĩ số</th>
                                            <th style="width: 100px;">Thao tác</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                    <?php 
                                        // Force rebuild array to ensure no duplicates
                                        $processedIds = [];
                                        $uniqueGroups = [];
                                        foreach ($nhomMonHoc as $group) {
                                            if (!in_array($group['ID'], $processedIds)) {
                                                $processedIds[] = $group['ID'];
                                                $uniqueGroups[] = $group;
                                            }
                                        }
                                        
                                        // Use the clean array for display
                                        foreach ($uniqueGroups as $index => $nhom): 
                                    ?>
                                        <tr>
                                            <td class="fw-bold"><?php echo htmlspecialchars($nhom['MaNhom'] ?? 'N/A'); ?></td>
                                            <td>
                                                <?php if (!empty($nhom['TenGiangVien'])): ?>
                                                    <?php echo htmlspecialchars($nhom['TenGiangVien']); ?>
                                                    <div class="small text-muted"><?php echo htmlspecialchars($nhom['MaGiangVien'] ?? ''); ?></div>
                                                <?php else: ?>
                                                    <span class="text-muted">Chưa phân công</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <?php if (!empty($nhom['TenPhong'])): ?>
                                                    <div class="small text-muted">
                                                        Phòng: <?php echo htmlspecialchars($nhom['TenPhong'] . ' (' . $nhom['MaPhong'] . ')'); ?>
                                                    </div>
                                                <?php endif; ?>
                                                <?php if (!empty($nhom['DanhSachBuoiHoc'])): ?>
                                                    <ul class="list-unstyled mt-2">
                                                        <?php foreach ($nhom['DanhSachBuoiHoc'] as $buoiHoc): ?>
                                                            <li>
                                                                <i class="fas fa-calendar-alt me-1"></i>
                                                                <?php echo htmlspecialchars($buoiHoc['MoTaThoiGian']); ?>
                                                                <?php if (!empty($buoiHoc['TenPhong'])): ?>
                                                                    <span class="small text-muted">
                                                                        - Phòng: <?php echo htmlspecialchars($buoiHoc['TenPhong'] . ' (' . $buoiHoc['MaPhong'] . ')'); ?>
                                                                    </span>
                                                                <?php endif; ?>
                                                            </li>
                                                        <?php endforeach; ?>
                                                    </ul>
                                                <?php endif; ?>
                                                <?php if (!empty($nhom['NgayBatDau']) || !empty($nhom['NgayKetThuc'])): ?>
                                                    <div class="mt-1">
                                                        <strong>Thời gian diễn ra:</strong> 
                                                        <?php if (!empty($nhom['NgayBatDau']) && !empty($nhom['NgayKetThuc'])): ?>
                                                            <?php echo date('d/m/Y', strtotime($nhom['NgayBatDau'])); ?> - 
                                                            <?php echo date('d/m/Y', strtotime($nhom['NgayKetThuc'])); ?>
                                                        <?php elseif (!empty($nhom['NgayBatDau'])): ?>
                                                            Từ <?php echo date('d/m/Y', strtotime($nhom['NgayBatDau'])); ?>
                                                        <?php elseif (!empty($nhom['NgayKetThuc'])): ?>
                                                            Đến <?php echo date('d/m/Y', strtotime($nhom['NgayKetThuc'])); ?>
                                                        <?php endif; ?>
                                                    </div>
                                                <?php endif; ?>
                                            </td>
                                            <td class="text-center">
                                                <?php 
                                                    $soSV = $nhom['SoLuongSV'] ?? 0;
                                                    $maxSV = $nhom['SoLuongToiDa'] ?? 50;
                                                    $phanTram = $maxSV > 0 ? min(100, round(($soSV / $maxSV) * 100)) : 0;
                                                    
                                                    $badgeClass = 'bg-success';
                                                    if ($phanTram >= 80) $badgeClass = 'bg-danger';
                                                    elseif ($phanTram >= 50) $badgeClass = 'bg-warning text-dark';
                                                ?>
                                                <div class="badge <?php echo $badgeClass; ?> mb-1">
                                                    <?php echo $soSV; ?>/<?php echo $maxSV; ?>
                                                </div>
                                                <div class="progress" style="height: 5px;">
                                                    <div class="progress-bar <?php echo str_replace('text-dark', '', $badgeClass); ?>" 
                                                         style="width: <?php echo $phanTram; ?>%"></div>
                                                </div>
                                            </td>
                                            <td class="text-center">
                                                <a href="../GroupSubject/details.php?id=<?php echo urlencode($nhom['ID']); ?>" class="btn btn-sm btn-info mb-1">
                                                    <i class="fas fa-eye"></i>
                                                </a>
                                                <?php if ($accessLevel <= 2): ?>
                                                    <a href="../GroupSubject/edit.php?id=<?php echo urlencode($nhom['ID']); ?>" class="btn btn-sm btn-warning mb-1">
                                                        <i class="fas fa-edit"></i>
                                                    </a>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php else: ?>
                            <div class="alert alert-info">
                                <i class="fas fa-info-circle me-2"></i>Chưa có nhóm môn học nào được tạo.
                                <?php if ($accessLevel <= 2): ?>
                                    <a href="../GroupSubject/create.php?idMonHoc=<?php echo urlencode($monHoc['ID']); ?>" class="alert-link ms-2">
                                        Tạo nhóm môn học mới
                                    </a>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>
                        
                        <!-- Nút thao tác chính -->
                        <div class="d-flex justify-content-between mt-4">
                            <a href="index.php" class="btn btn-secondary">
                                <i class="fas fa-arrow-left me-2"></i>Quay lại
                            </a>
                            
                            <?php if ($accessLevel <= 2): ?>
                            <div>
                                <a href="edit.php?id=<?php echo urlencode($monHoc['ID']); ?>" class="btn btn-primary me-2">
                                    <i class="fas fa-edit me-2"></i>Chỉnh sửa
                                </a>
                                <button type="button" class="btn btn-danger" data-bs-toggle="modal" data-bs-target="#deleteModal" <?php echo !empty($nhomMonHoc) ? 'disabled' : ''; ?>>
                                    <i class="fas fa-trash-alt me-2"></i>Xóa
                                </button>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Cột phụ: Thống kê và hành động nhanh -->
            <div class="col-lg-4">
                <!-- Thống kê -->
                <div class="card shadow-sm mb-4">
                    <div class="card-header bg-info text-white">
                        <h6 class="mb-0"><i class="fas fa-chart-pie me-2"></i>Thống kê</h6>
                    </div>
                    <div class="card-body">
                        <div class="d-flex justify-content-between mb-2">
                            <span>Nhóm môn học:</span>
                            <span class="badge bg-primary"><?php echo count($nhomMonHoc); ?></span>
                        </div>
                        <div class="d-flex justify-content-between mb-2">
                            <span>Tổng sinh viên:</span>
                            <?php 
                                $tongSV = 0;
                                foreach ($nhomMonHoc as $nhom) {
                                    $tongSV += isset($nhom['SoLuongSV']) ? (int)$nhom['SoLuongSV'] : 0;
                                }
                            ?>
                            <span class="badge bg-success"><?php echo $tongSV; ?></span>
                        </div>
                        <div class="d-flex justify-content-between">
                            <span>Số tín chỉ:</span>
                            <span class="badge bg-warning text-dark"><?php echo $monHoc['SoTinChi']; ?></span>
                        </div>
                    </div>
                </div>
                
                <!-- Lịch sử chỉnh sửa -->
                <?php if (!empty($nguoiChinhSua)): ?>
                <div class="card shadow-sm mb-4">
                    <div class="card-header bg-secondary text-white">
                        <h6 class="mb-0"><i class="fas fa-history me-2"></i>Lịch sử gần đây</h6>
                    </div>
                    <div class="card-body p-0">
                        <ul class="list-group list-group-flush">
                            <?php foreach ($nguoiChinhSua as $history): ?>
                                <li class="list-group-item px-3 py-2">
                                    <div class="d-flex align-items-center">
                                        <span class="badge <?php echo $history['HanhDong'] == 'EDIT' ? 'bg-warning text-dark' : ($history['HanhDong'] == 'ADD' ? 'bg-success' : 'bg-danger'); ?> me-2">
                                            <?php 
                                                if ($history['HanhDong'] == 'EDIT') echo 'Sửa';
                                                elseif ($history['HanhDong'] == 'ADD') echo 'Thêm';
                                                else echo 'Xóa';
                                            ?>
                                        </span>
                                        <small class="text-muted ms-auto">
                                            <?php 
                                                $date = new DateTime($history['NgayThucHien']);
                                                echo $date->format('d/m/Y H:i');
                                            ?>
                                        </small>
                                    </div>
                                    <div class="small mt-1">
                                        <i class="fas fa-user-edit me-1"></i>
                                        <?php echo htmlspecialchars($history['TenNguoiChinhSua'] ?? 'N/A'); ?>
                                    </div>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                </div>
                <?php endif; ?>
                
                <!-- Hành động nhanh -->
                <?php if ($accessLevel <= 2): ?>
                <div class="card shadow-sm mb-4">
                    <div class="card-header bg-primary text-white">
                        <h6 class="mb-0"><i class="fas fa-bolt me-2"></i>Hành động nhanh</h6>
                    </div>
                    <div class="card-body">
                        <div class="d-grid gap-2">
                            <a href="../GroupSubject/create.php?idMonHoc=<?php echo urlencode($monHoc['ID']); ?>" class="btn btn-success">
                                <i class="fas fa-plus-circle me-2"></i>Thêm nhóm môn học
                            </a>
                            
                            <?php if (!empty($nhomMonHoc)): ?>
                                <a href="../GroupSubject/index.php?monhoc=<?php echo urlencode($monHoc['ID']); ?>" class="btn btn-outline-primary">
                                    <i class="fas fa-list me-2"></i>Xem tất cả nhóm môn học
                                </a>
                            <?php endif; ?>
                            
                            <a href="edit.php?id=<?php echo urlencode($monHoc['ID']); ?>" class="btn btn-outline-secondary">
                                <i class="fas fa-edit me-2"></i>Chỉnh sửa môn học
                            </a>
                        </div>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <!-- Modal xác nhận xóa -->
    <div class="modal fade" id="deleteModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header bg-danger text-white">
                    <h5 class="modal-title">Xác nhận xóa môn học</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <p><strong><?php echo htmlspecialchars($monHoc['TenMonHoc']); ?></strong></p>
                    <p>Mã môn học: <?php echo htmlspecialchars($monHoc['MaMonHoc']); ?></p>
                    
                    <?php if (!empty($nhomMonHoc)): ?>
                        <div class="alert alert-warning">
                            <i class="fas fa-exclamation-triangle me-2"></i>
                            Không thể xóa vì môn học đang có <?php echo count($nhomMonHoc); ?> nhóm liên kết.
                        </div>
                    <?php else: ?>
                        <div class="alert alert-danger">
                            <i class="fas fa-exclamation-triangle me-2"></i>
                            Hành động này không thể hoàn tác!
                        </div>
                    <?php endif; ?>
                </div>
                <div class="modal-footer">
                    <form action="delete.php" method="get">
                        <input type="hidden" name="id" value="<?php echo $monHoc['ID']; ?>">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Hủy</button>
                        <button type="submit" class="btn btn-danger" <?php echo !empty($nhomMonHoc) ? 'disabled' : ''; ?>>
                            Xác nhận xóa
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>
<?php endif; ?>

<?php
// Lấy nội dung buffer và hiển thị với layout
$contentForLayout = ob_get_clean();
include_once('../Shared/_LayoutAdmin.php');
?>
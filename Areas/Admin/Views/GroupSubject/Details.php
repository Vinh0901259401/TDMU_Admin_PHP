<?php
/**
 * Chi tiết nhóm môn học và danh sách sinh viên đăng ký
 */
require_once("../Shared/connect.inc");
$pageTitle = "Chi tiết nhóm môn học";

// Kiểm tra phiên và quyền truy cập
session_start();
$accessLevel = 0;
$isLecturer = false;
$maNamHocHienTai = "NH0000000001";
$tk = isset($_SESSION['TaiKhoan']) ? $_SESSION['TaiKhoan'] : null;

// Kiểm tra quyền truy cập giảng viên (cấp độ 3)
if ($tk && isset($tk['IDQuyenTruyCap'])) {
    $stmt = $conn->prepare("SELECT CapDo FROM quyentruycap WHERE ID = ?");
    $stmt->execute([$tk['IDQuyenTruyCap']]);
    if ($stmt->rowCount() > 0) {
        $quyen = $stmt->fetch(PDO::FETCH_ASSOC);
        $accessLevel = $quyen['CapDo'];
        $isLecturer = ($accessLevel == 3);
    }
}

// Lấy thông tin nhóm môn học và danh sách sinh viên
$idNhomMonHoc = isset($_GET['id']) ? $_GET['id'] : null;
$nhomMonHoc = null;
$danhSachSinhVien = [];

// Tìm kiếm và phân trang
$search = isset($_GET['strSearch']) ? trim($_GET['strSearch']) : '';
$trangThai = isset($_GET['trangThai']) ? $_GET['trangThai'] : '';
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$recordsPerPage = 10;
$start = ($page - 1) * $recordsPerPage;

// Truy vấn thông tin nếu có ID và là giảng viên
if ($idNhomMonHoc && $isLecturer) {
    try {
        // 1. Lấy thông tin chi tiết nhóm môn học
        $query = "
            SELECT 
                nmh.ID, nmh.MaNhom, nmh.SoLuongToiDa, nmh.IDMonHoc,
                mh.TenMonHoc, mh.MaMonHoc, mh.SoTinChi,
                hk.Ten as TenHocKy, nh.Ten as TenNamHoc, hk.ID as IDHocKy, nh.ID as IDNamHoc,
                tk.HoTen as TenGiangVien
            FROM nhommonhoc nmh
            JOIN monhoc mh ON nmh.IDMonHoc = mh.ID
            JOIN hocky hk ON nmh.IDHocKy = hk.ID
            JOIN namhoc nh ON mh.IDNamHoc = nh.ID
            LEFT JOIN taikhoan tk ON nmh.IDGiangVien = tk.ID
            WHERE nmh.ID = ? AND nmh.IDGiangVien = ?
        ";
        $stmt = $conn->prepare($query);
        $stmt->execute([$idNhomMonHoc, $tk['ID']]);
        if ($stmt->rowCount() > 0) {
            $nhomMonHoc = $stmt->fetch(PDO::FETCH_ASSOC);
            
            // 2. Đếm số sinh viên đăng ký
            $stmt = $conn->prepare("SELECT COUNT(*) FROM nhommonhoc_sinhvien WHERE IDNhomMonHoc = ? AND TrangThai = 1");
            $stmt->execute([$idNhomMonHoc]);
            $nhomMonHoc['SoSVDangKy'] = $stmt->fetchColumn();
            
            // 3. Truy vấn danh sách sinh viên
            $whereConditions = ["nmsv.IDNhomMonHoc = ?"];
            $params = [$idNhomMonHoc];
            
            if ($trangThai !== '') {
                $whereConditions[] = "nmsv.TrangThai = ?";
                $params[] = $trangThai;
            }
            
            if (!empty($search)) {
                $whereConditions[] = "(tk.HoTen LIKE ? OR tk.MaSo LIKE ?)";
                $params[] = "%$search%";
                $params[] = "%$search%";
            }
            
            $whereClause = implode(' AND ', $whereConditions);
            
            $querySV = "
                SELECT 
                    nmsv.ID as IDDangKy,
                    nmsv.TrangThai, 
                    nmsv.NgayDangKy,
                    tk.ID as IDSinhVien,   
                    tk.HoTen, 
                    l.ID as IDLop, 
                    kn.TenNganh as TenNganh
                FROM nhommonhoc_sinhvien nmsv
                JOIN taikhoan tk ON nmsv.IDSinhVien = tk.ID
                LEFT JOIN lop l ON tk.IDLop = l.ID
                LEFT JOIN khoa_nganh kn ON tk.IDKhoaNganh = kn.ID
                WHERE $whereClause
                ORDER BY tk.HoTen
                LIMIT ?, ?
            ";
            
            $stmtSV = $conn->prepare($querySV);
            
            // Bind tham số
            $paramIndex = 1;
            foreach ($params as $param) {
                $stmtSV->bindValue($paramIndex++, $param);
            }
            $stmtSV->bindValue($paramIndex++, $start, PDO::PARAM_INT);
            $stmtSV->bindValue($paramIndex, $recordsPerPage, PDO::PARAM_INT);
            
            $stmtSV->execute();
            $danhSachSinhVien = $stmtSV->fetchAll(PDO::FETCH_ASSOC);
            
            // Đếm tổng số sinh viên để phân trang
            $countQuery = "SELECT COUNT(*) FROM nhommonhoc_sinhvien nmsv 
                           JOIN taikhoan tk ON nmsv.IDSinhVien = tk.ID 
                           WHERE $whereClause";
            $countStmt = $conn->prepare($countQuery);
            $countStmt->execute($params);
            $totalSinhVien = $countStmt->fetchColumn();
        }
    } catch (PDOException $e) {
        error_log("Lỗi truy vấn: " . $e->getMessage());
    }
}

// Bắt đầu output buffer để tích hợp với layout
ob_start();
?>

<!-- CSS tùy chỉnh cho trang này -->
<style>
    .card {
        box-shadow: 0 0.125rem 0.25rem rgba(0, 0, 0, 0.075);
        border-radius: 0.5rem;
        border: none;
        transition: all 0.3s ease;
    }
    .card:hover {
        box-shadow: 0 0.5rem 1rem rgba(0, 0, 0, 0.15);
    }
    .card-header {
        border-top-left-radius: 0.5rem !important;
        border-top-right-radius: 0.5rem !important;
        font-weight: 600;
    }
    .info-box {
        background-color: #f8f9fa;
        border-radius: 0.5rem;
        padding: 1.5rem;
        margin-bottom: 1rem;
        border-left: 5px solid #0d6efd;
    }
    .table th {
        background-color: #f8f9fa;
    }
    .status-badge {
        padding: 0.4rem 0.6rem;
        font-size: 0.8rem;
        font-weight: 500;
    }
    .action-btn {
        width: 36px;
        height: 36px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        border-radius: 0.25rem;
        transition: all 0.2s;
        margin: 0 2px;
    }
    .action-btn:hover {
        transform: translateY(-2px);
    }
    .breadcrumb {
        background-color: #f8f9fa;
        padding: 0.75rem 1rem;
        border-radius: 0.25rem;
        margin-bottom: 1.5rem;
    }
    .search-form {
        background-color: #f8f9fa;
        padding: 1rem;
        border-radius: 0.5rem;
        margin-bottom: 1.5rem;
    }
    .class-label {
        font-size: 0.85rem;
        font-weight: 600;
        color: #0d6efd;
    }
    .department-label {
        font-size: 0.8rem;
        color: #6c757d;
    }
    .highlight-card {
        position: relative;
        overflow: hidden;
    }
    .highlight-card::before {
        content: "";
        position: absolute;
        top: 0;
        left: 0;
        width: 100%;
        height: 5px;
        background: linear-gradient(90deg, #0d6efd, #0dcaf0);
    }
    .pagination .page-link {
        color: #0d6efd;
        border-radius: 0.25rem;
        margin: 0 0.15rem;
    }
    .pagination .page-item.active .page-link {
        background-color: #0d6efd;
        border-color: #0d6efd;
    }
    .alert {
        border: none;
        border-radius: 0.5rem;
        padding: 1.5rem;
    }
    .alert i {
        margin-right: 0.5rem;
    }
</style>

<!-- Kiểm tra quyền truy cập -->
<?php if (!$isLecturer): ?>
    <div class="alert alert-danger text-center p-5 my-4">
        <i class="fas fa-exclamation-triangle fa-3x mb-3"></i>
        <h4 class="fw-bold">BẠN KHÔNG CÓ QUYỀN XEM TRANG NÀY!</h4>
        <p class="mb-0">Trang này chỉ dành cho giảng viên.</p>
        <a href="../Dashboard/Index.php" class="btn btn-outline-danger mt-3">
            <i class="fas fa-home me-2"></i>Về trang chủ
        </a>
    </div>
<?php elseif (!$nhomMonHoc): ?>
    <div class="alert alert-warning text-center p-5 my-4">
        <i class="fas fa-exclamation-circle fa-3x mb-3"></i>
        <h4 class="fw-bold">KHÔNG TÌM THẤY NHÓM MÔN HỌC!</h4>
        <p class="mb-0">Nhóm môn học không tồn tại hoặc bạn không phải là giảng viên phụ trách.</p>
        <a href="MyTeachingGroups.php" class="btn btn-warning mt-3">
            <i class="fas fa-arrow-left me-2"></i>Quay lại danh sách
        </a>
    </div>
<?php else: ?>
    <div class="container-fluid px-4">
        <!-- Breadcrumb -->
        <nav aria-label="breadcrumb" class="mt-3">
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="MyTeachingGroups.php"><i class="fas fa-chalkboard-teacher me-1"></i>Nhóm môn học giảng dạy</a></li>
                <li class="breadcrumb-item active"><i class="fas fa-info-circle me-1"></i>Chi tiết nhóm môn học</li>
            </ol>
        </nav>
        
        <div class="row">
            <!-- Thông tin nhóm môn học -->
            <div class="col-md-4 mb-4">
                <div class="card highlight-card mb-4">
                    <div class="card-header bg-primary text-white d-flex justify-content-between align-items-center">
                        <h5 class="mb-0"><i class="fas fa-info-circle me-2"></i>Thông tin nhóm</h5>
                        <a href="MyTeachingGroups.php" class="btn btn-sm btn-light rounded-pill px-3">
                            <i class="fas fa-arrow-left me-1"></i> Quay lại
                        </a>
                    </div>
                    <div class="card-body p-0">
                        <div class="p-4 text-center bg-light">
                            <h4 class="fw-bold text-primary mb-2"><?php echo htmlspecialchars($nhomMonHoc['TenMonHoc']); ?></h4>
                            <div class="d-flex justify-content-center mb-2">
                                <span class="badge bg-primary rounded-pill me-2 px-3 py-2"><?php echo htmlspecialchars($nhomMonHoc['MaNhom']); ?></span>
                                <span class="badge bg-info rounded-pill px-3 py-2"><?php echo $nhomMonHoc['SoTinChi']; ?> tín chỉ</span>
                            </div>
                            <p class="text-muted">
                                <i class="fas fa-calendar-alt me-1"></i> 
                                <?php echo htmlspecialchars($nhomMonHoc['TenHocKy'] . ' - ' . $nhomMonHoc['TenNamHoc']); ?>
                            </p>
                        </div>
                        <div class="list-group list-group-flush">
                            <div class="list-group-item d-flex justify-content-between align-items-center">
                                <strong><i class="fas fa-hashtag me-2 text-primary"></i>Mã môn học:</strong>
                                <span class="badge bg-secondary"><?php echo htmlspecialchars($nhomMonHoc['MaMonHoc']); ?></span>
                            </div>
                            <div class="list-group-item d-flex justify-content-between align-items-center">
                                <strong><i class="fas fa-award me-2 text-primary"></i>Số tín chỉ:</strong>
                                <span><?php echo $nhomMonHoc['SoTinChi']; ?></span>
                            </div>
                            <div class="list-group-item d-flex justify-content-between align-items-center">
                                <strong><i class="fas fa-user-graduate me-2 text-primary"></i>Sinh viên đăng ký:</strong>
                                <span class="badge rounded-pill bg-<?php 
                                    echo ($nhomMonHoc['SoSVDangKy'] >= $nhomMonHoc['SoLuongToiDa']) ? 'danger' : 
                                        (($nhomMonHoc['SoSVDangKy'] >= $nhomMonHoc['SoLuongToiDa'] * 0.8) ? 'warning text-dark' : 'success'); 
                                ?> px-3 py-2">
                                    <?php echo $nhomMonHoc['SoSVDangKy']; ?>/<?php echo $nhomMonHoc['SoLuongToiDa']; ?>
                                </span>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Thống kê trạng thái sinh viên -->
                <div class="card mb-4">
                    <div class="card-header bg-info text-white">
                        <i class="fas fa-chart-pie me-2"></i> Thống kê trạng thái
                    </div>
                    <div class="card-body p-0">
                        <div class="list-group list-group-flush">
                            <?php
                            // Đếm số lượng sinh viên theo trạng thái
                            $totalCount = count($danhSachSinhVien);
                            $statusCounts = [0 => 0, 1 => 0, 2 => 0, 3 => 0];
                            
                            foreach ($danhSachSinhVien as $sv) {
                                if (isset($statusCounts[$sv['TrangThai']])) {
                                    $statusCounts[$sv['TrangThai']]++;
                                }
                            }
                            
                            $statusLabels = [
                                0 => ['Chờ duyệt', 'warning text-dark', 'clock'],
                                1 => ['Đang học', 'success', 'user-graduate'],
                                2 => ['Hoàn thành', 'info', 'check-circle'],
                                3 => ['Đã hủy', 'danger', 'ban']
                            ];
                            
                            foreach ($statusLabels as $status => $info) {
                                $count = $statusCounts[$status] ?? 0;
                                $percent = $totalCount > 0 ? round(($count / $totalCount) * 100) : 0;
                                echo '<div class="list-group-item d-flex justify-content-between align-items-center">';
                                echo '<span><i class="fas fa-'.$info[2].' me-2"></i>'.$info[0].':</span>';
                                echo '<span class="badge bg-'.$info[1].' rounded-pill">'.$count.'</span>';
                                echo '</div>';
                                
                                // Thêm thanh tiến trình
                                if ($count > 0) {
                                    echo '<div class="list-group-item p-2">';
                                    echo '<div class="progress" style="height: 8px;">';
                                    echo '<div class="progress-bar bg-'.$info[1].'" role="progressbar" style="width: '.$percent.'%"></div>';
                                    echo '</div>';
                                    echo '</div>';
                                }
                            }
                            ?>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Danh sách sinh viên -->
            <div class="col-md-8">
                <div class="card mb-4">
                    <div class="card-header bg-primary text-white d-flex justify-content-between align-items-center">
                        <h5 class="mb-0"><i class="fas fa-users me-2"></i>Danh sách sinh viên</h5>
                        <div>
                            <button class="btn btn-sm btn-light rounded-pill">
                                <i class="fas fa-file-excel me-1"></i> Xuất Excel
                            </button>
                        </div>
                    </div>
                    <div class="card-body">
                        <!-- Form tìm kiếm đơn giản -->
                        <form method="GET" action="" class="row g-3 mb-4 search-form">
                            <input type="hidden" name="id" value="<?php echo $idNhomMonHoc; ?>">
                            <div class="col-md-3">
                                <label for="trangThai" class="form-label small fw-bold">Trạng thái</label>
                                <select class="form-select form-select-sm" id="trangThai" name="trangThai" onchange="this.form.submit()">
                                    <option value="">Tất cả trạng thái</option>
                                    <option value="0" <?php echo $trangThai === '0' ? 'selected' : ''; ?>>Chờ duyệt</option>
                                    <option value="1" <?php echo $trangThai === '1' ? 'selected' : ''; ?>>Đang học</option>
                                    <option value="2" <?php echo $trangThai === '2' ? 'selected' : ''; ?>>Đã hoàn thành</option>
                                    <option value="3" <?php echo $trangThai === '3' ? 'selected' : ''; ?>>Đã hủy</option>
                                </select>
                            </div>
                            <div class="col-md-7">
                                <label for="strSearch" class="form-label small fw-bold">Tìm kiếm</label>
                                <div class="input-group input-group-sm">
                                    <input type="text" class="form-control" id="strSearch" name="strSearch" 
                                        placeholder="Nhập tên hoặc MSSV..." 
                                        value="<?php echo htmlspecialchars($search); ?>">
                                    <button class="btn btn-primary" type="submit">
                                        <i class="fas fa-search"></i> Tìm
                                    </button>
                                </div>
                            </div>
                            <?php if (!empty($search) || $trangThai !== ''): ?>
                            <div class="col-md-2">
                                <label class="form-label small fw-bold">&nbsp;</label>
                                <a href="Details.php?id=<?php echo $idNhomMonHoc; ?>" class="btn btn-outline-secondary btn-sm w-100">
                                    <i class="fas fa-sync"></i> Đặt lại
                                </a>
                            </div>
                            <?php endif; ?>
                        </form>
                        
                        <!-- Bảng danh sách sinh viên -->
                        <?php if (count($danhSachSinhVien) > 0): ?>
                        <div class="table-responsive">
                            <table class="table table-hover table-striped border">
                                <thead>
                                    <tr class="table-light">
                                        <th class="fw-bold">MSSV</th>
                                        <th class="fw-bold">Họ và tên</th>
                                        <th class="fw-bold">Thông tin</th>
                                        <th class="fw-bold">Trạng thái</th>
                                        <th class="fw-bold text-center">Thao tác</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($danhSachSinhVien as $sv): ?>
                                    <tr>
                                        <td class="align-middle">
                                            <span class="badge bg-secondary"><?php echo htmlspecialchars($sv['IDSinhVien']); ?></span>
                                        </td>
                                        <td class="align-middle fw-bold"><?php echo htmlspecialchars($sv['HoTen']); ?></td>
                                        <td class="align-middle">
                                            <?php if (!empty($sv['IDLop'])): ?>
                                            <div class="class-label"><?php echo htmlspecialchars($sv['IDLop']); ?></div>
                                            <?php endif; ?>
                                            <?php if (!empty($sv['TenNganh'])): ?>
                                            <div class="department-label"><?php echo htmlspecialchars($sv['TenNganh']); ?></div>
                                            <?php endif; ?>
                                            <div class="small text-muted">
                                                <i class="far fa-calendar-alt me-1"></i>
                                                <?php echo date('d/m/Y', strtotime($sv['NgayDangKy'])); ?>
                                            </div>
                                        </td>
                                        <td class="align-middle">
                                            <?php
                                            $statusClasses = [
                                                0 => ['bg-warning text-dark', 'Chờ duyệt', 'clock'],
                                                1 => ['bg-success', 'Đang học', 'user-graduate'],
                                                2 => ['bg-info', 'Hoàn thành', 'check-circle'],
                                                3 => ['bg-danger', 'Đã hủy', 'ban']
                                            ];
                                            
                                            $statusInfo = $statusClasses[$sv['TrangThai']] ?? ['bg-secondary', 'Không xác định', 'question-circle'];
                                            echo '<span class="badge '.$statusInfo[0].' status-badge">';
                                            echo '<i class="fas fa-'.$statusInfo[2].' me-1"></i> '.$statusInfo[1];
                                            echo '</span>';
                                            ?>
                                        </td>
                                        <td class="align-middle text-center">
                                            <div class="btn-group">
                                                <?php if ($sv['TrangThai'] == 0): ?>
                                                <button type="button" class="btn btn-success action-btn" 
                                                    onclick="updateStatus('<?php echo $sv['IDDangKy']; ?>', 1)" 
                                                    data-bs-toggle="tooltip" title="Duyệt đăng ký">
                                                    <i class="fas fa-check"></i>
                                                </button>
                                                <?php endif; ?>
                                                
                                                <?php if ($sv['TrangThai'] == 1 || $sv['TrangThai'] == 2): ?>
                                                    <button type="button" class="btn btn-<?php echo ($sv['TrangThai'] == 1) ? 'warning' : 'info'; ?> action-btn" 
                                                        onclick="openGradeModal('<?php echo $sv['IDSinhVien']; ?>', '<?php echo htmlspecialchars($sv['HoTen']); ?>')"
                                                        data-bs-toggle="tooltip" title="<?php echo ($sv['TrangThai'] == 1) ? 'Nhập điểm' : 'Sửa điểm'; ?>">
                                                        <i class="fas fa-<?php echo ($sv['TrangThai'] == 1) ? 'edit' : 'pencil-alt'; ?>"></i>
                                                    </button>
                                                <?php endif; ?>
                                                
                                                <a href="../Class/GradeTable.php?mssv=<?php echo $sv['IDSinhVien']; ?>&maNH=<?php echo $maNamHocHienTai; ?>" 
                                                    class="btn btn-primary action-btn" data-bs-toggle="tooltip" title="Xem bảng điểm sinh viên">
                                                    <i class="fas fa-graduation-cap"></i>
                                                </a>
                                            </div>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        
                        <!-- Phân trang đơn giản -->
                        <?php if ($totalSinhVien > $recordsPerPage): ?>
                        <nav aria-label="Page navigation" class="mt-4">
                            <ul class="pagination pagination-sm justify-content-center">
                                <?php
                                $totalPages = ceil($totalSinhVien / $recordsPerPage);
                                $baseUrl = "?id=$idNhomMonHoc" . 
                                        ((!empty($search)) ? "&strSearch=" . urlencode($search) : "") . 
                                        (($trangThai !== '') ? "&trangThai=$trangThai" : "");
                                
                                // Nút Previous
                                echo ($page > 1) ? 
                                    "<li class='page-item'><a class='page-link' href='{$baseUrl}&page=" . ($page - 1) . "'><i class='fas fa-chevron-left'></i></a></li>" : 
                                    "<li class='page-item disabled'><a class='page-link'><i class='fas fa-chevron-left'></i></a></li>";
                                
                                // Hiển thị số trang
                                for ($i = max(1, $page - 2); $i <= min($page + 2, $totalPages); $i++) {
                                    echo ($i == $page) ? 
                                        "<li class='page-item active'><a class='page-link'>$i</a></li>" : 
                                        "<li class='page-item'><a class='page-link' href='{$baseUrl}&page=$i'>$i</a></li>";
                                }
                                
                                // Nút Next
                                echo ($page < $totalPages) ? 
                                    "<li class='page-item'><a class='page-link' href='{$baseUrl}&page=" . ($page + 1) . "'><i class='fas fa-chevron-right'></i></a></li>" : 
                                    "<li class='page-item disabled'><a class='page-link'><i class='fas fa-chevron-right'></i></a></li>";
                                ?>
                            </ul>
                        </nav>
                        <?php endif; ?>
                        
                        <?php else: ?>
                        <div class="alert alert-info text-center p-4 shadow-sm">
                            <i class="fas fa-info-circle fa-3x mb-3 text-info"></i>
                            <h5 class="fw-bold">Không tìm thấy sinh viên nào</h5>
                            <p>
                                <?php echo (!empty($search) || $trangThai !== '') ? 
                                    'Không có sinh viên nào phù hợp với điều kiện tìm kiếm.' : 
                                    'Chưa có sinh viên nào đăng ký nhóm môn học này.'; ?>
                            </p>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Script xử lý cập nhật trạng thái sinh viên -->
    <script>
    // Khởi tạo tooltip
    document.addEventListener('DOMContentLoaded', function() {
        var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
        var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
            return new bootstrap.Tooltip(tooltipTriggerEl);
        });
    });
    
    function updateStatus(id, status) {
        if (confirm('Bạn có chắc chắn muốn thay đổi trạng thái của sinh viên này?')) {
            fetch('UpdateStudentStatus.php', {
                method: 'POST',
                headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                body: 'id=' + id + '&status=' + status
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Hiển thị thông báo thành công
                    const alertElement = document.createElement('div');
                    alertElement.className = 'alert alert-success alert-dismissible fade show position-fixed top-0 start-50 translate-middle-x mt-3';
                    alertElement.style.zIndex = '9999';
                    alertElement.innerHTML = `
                        <i class="fas fa-check-circle me-2"></i> Cập nhật trạng thái sinh viên thành công
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    `;
                    document.body.appendChild(alertElement);
                    
                    // Tự động xóa thông báo sau 3 giây
                    setTimeout(() => {
                        alertElement.remove();
                        location.reload();
                    }, 1500);
                } else {
                    alert('Lỗi: ' + data.message);
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Đã xảy ra lỗi khi xử lý yêu cầu. Vui lòng thử lại sau.');
            });
        }
    }
    
    function openGradeModal(idSinhVien, tenSinhVien) {
        document.getElementById('idSinhVien').value = idSinhVien;
        document.getElementById('studentInfo').innerHTML = '<div class="fw-bold">' + tenSinhVien + '</div><div class="badge bg-secondary">' + idSinhVien + '</div>';
        
        // Reset form và xóa các thông báo cũ
        document.getElementById('diemChuyenCan').value = '';
        document.getElementById('diemKiemTra').value = '';
        document.getElementById('diemThi').value = '';
        document.getElementById('ghiChu').value = '';
        
        // Xóa thông báo cũ nếu có
        const oldAlerts = document.querySelectorAll('#gradeModal .alert');
        oldAlerts.forEach(alert => alert.remove());
        
        // Thêm loading indicator
        const loadingIndicator = document.createElement('div');
        loadingIndicator.id = 'loadingGrades';
        loadingIndicator.className = 'text-center py-3';
        loadingIndicator.innerHTML = '<div class="spinner-border text-primary" role="status"></div><p class="mt-2 text-muted">Đang tải điểm...</p>';
        
        const modalBody = document.querySelector('#gradeModal .modal-body');
        const insertPosition = document.querySelector('#gradeModal .modal-body .row.mb-3').previousElementSibling;
        insertPosition.parentNode.insertBefore(loadingIndicator, insertPosition.nextSibling);
        
        // Hiển thị modal trước
        const gradeModal = new bootstrap.Modal(document.getElementById('gradeModal'));
        gradeModal.show();
        
        // Đường dẫn tới file xử lý - Sử dụng idNhomMonHoc thay vì idMonHoc
        const url = 'CheckStudentGrade.php?idSinhVien=' + idSinhVien + 
                    '&idNhomMonHoc=<?php echo $idNhomMonHoc; ?>' + 
                    '&t=' + new Date().getTime();
        
        // Sau đó tải dữ liệu điểm với xử lý lỗi tốt hơn
        fetch(url)
        .then(response => {
            if (!response.ok) {
                throw new Error('HTTP status ' + response.status);
            }
            return response.json();
        })
        .then(data => {
            // Xóa loading indicator
            const loadingElement = document.getElementById('loadingGrades');
            if (loadingElement) loadingElement.remove();
            
            // Log để debug
            console.log('Grade data received:', data);
            
            if (data.hasGrade) {
                // Hiển thị điểm hiện tại, xử lý cẩn thận với giá trị 0
                document.getElementById('diemChuyenCan').value = data.diem.DiemChuyenCan !== null ? data.diem.DiemChuyenCan : '';
                document.getElementById('diemKiemTra').value = data.diem.DiemKiemTra !== null ? data.diem.DiemKiemTra : '';
                document.getElementById('diemThi').value = data.diem.DiemThi !== null ? data.diem.DiemThi : '';
                document.getElementById('ghiChu').value = data.diem.GhiChu || '';
                
                // Hiển thị thông tin điểm đã có
                const currentScoreInfo = document.createElement('div');
                currentScoreInfo.className = 'alert alert-info mb-3';
                currentScoreInfo.innerHTML = `
                    <h6 class="mb-2"><i class="fas fa-info-circle me-2"></i>Điểm hiện tại của sinh viên</h6>
                    <div class="row text-center">
                        <div class="col-4">
                            <span class="d-block fw-bold">Chuyên cần</span>
                            <span class="badge bg-primary">${data.diem.DiemChuyenCan !== null ? data.diem.DiemChuyenCan : 'Chưa có'}</span>
                        </div>
                        <div class="col-4">
                            <span class="d-block fw-bold">Kiểm tra</span>
                            <span class="badge bg-primary">${data.diem.DiemKiemTra !== null ? data.diem.DiemKiemTra : 'Chưa có'}</span>
                        </div>
                        <div class="col-4">
                            <span class="d-block fw-bold">Thi</span>
                            <span class="badge bg-primary">${data.diem.DiemThi !== null ? data.diem.DiemThi : 'Chưa có'}</span>
                        </div>
                    </div>
                    ${data.diem.DiemTongKet !== null ? 
                    `<div class="text-center mt-2">
                        <span class="d-block fw-bold">Điểm tổng kết</span>
                        <span class="badge bg-success">${data.diem.DiemTongKet}</span>
                        ${data.diem.DiemChu ? `<span class="badge bg-primary ms-2">${data.diem.DiemChu}</span>` : ''}
                        ${data.diem.KetQua !== null ? `<span class="badge bg-${data.diem.KetQua == 1 ? 'success' : 'danger'} ms-2">${data.diem.KetQua == 1 ? 'Đạt' : 'Chưa đạt'}</span>` : ''}
                    </div>` : ''}
                `;
                
                // Thêm thông tin vào form
                const formControls = document.querySelector('#gradeModal .modal-body .row.mb-3').previousElementSibling;
                formControls.parentNode.insertBefore(currentScoreInfo, formControls.nextSibling);
            } else {
                // Hiển thị thông báo chưa có điểm
                const noScoreInfo = document.createElement('div');
                noScoreInfo.className = 'alert alert-warning mb-3';
                noScoreInfo.innerHTML = `
                    <div class="text-center">
                        <i class="fas fa-exclamation-circle fa-2x mb-2"></i>
                        <p class="mb-0">Sinh viên chưa có điểm cho môn học này</p>
                    </div>
                `;
                
                const formControls = document.querySelector('#gradeModal .modal-body .row.mb-3').previousElementSibling;
                formControls.parentNode.insertBefore(noScoreInfo, formControls.nextSibling);
            }
        })
        .catch(error => {
            console.error('Error loading grades:', error);
            
            // Xóa loading indicator
            const loadingElement = document.getElementById('loadingGrades');
            if (loadingElement) {
                // Thông báo lỗi chi tiết hơn
                loadingElement.innerHTML = `
                    <div class="alert alert-danger">
                        <i class="fas fa-exclamation-triangle me-2"></i>
                        <span>Không thể tải dữ liệu điểm. Vui lòng thử lại.</span>
                        <div class="small mt-2">Chi tiết lỗi: ${error.message}</div>
                        <div class="d-flex justify-content-end mt-2">
                            <button type="button" class="btn btn-sm btn-outline-danger" onclick="retryLoadGrades('${idSinhVien}')">
                                <i class="fas fa-sync-alt me-1"></i>Thử lại
                            </button>
                        </div>
                    </div>
                `;
            }
        });
    }

    // Hàm thử lại tải điểm
    function retryLoadGrades(idSinhVien) {
        const studentInfo = document.querySelector('#studentInfo');
        const tenSinhVien = studentInfo.querySelector('.fw-bold').textContent;
        
        // Xóa thông báo lỗi cũ
        const loadingElement = document.getElementById('loadingGrades');
        if (loadingElement) loadingElement.remove();
        
        // Tải lại
        openGradeModal(idSinhVien, tenSinhVien);
    }
    </script>
    
    <!-- Modal Nhập Điểm -->
    <div class="modal fade" id="gradeModal" tabindex="-1" aria-labelledby="gradeModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title" id="gradeModalLabel"><i class="fas fa-edit me-2"></i>Nhập điểm sinh viên</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form id="gradeForm" method="POST" onsubmit="submitGradeForm(event)">
                    <div class="modal-body">
                        <input type="hidden" name="idSinhVien" id="idSinhVien">
                        <input type="hidden" name="idMonHoc" value="<?php echo $nhomMonHoc['IDMonHoc'] ?? ''; ?>">
                        <input type="hidden" name="idGiangVien" value="<?php echo $tk['ID']; ?>">
                        <input type="hidden" name="idHocKy" value="<?php echo $nhomMonHoc['IDHocKy'] ?? ''; ?>">
                        <input type="hidden" name="idNamHoc" value="<?php echo $nhomMonHoc['IDNamHoc'] ?? ''; ?>">
                        <input type="hidden" name="idNhomMonHoc" value="<?php echo $idNhomMonHoc; ?>">                        
                        <div class="mb-3">
                            <label class="form-label fw-bold">Sinh viên:</label>
                            <div class="info-box bg-light rounded p-3" id="studentInfo"></div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label fw-bold">Môn học:</label>
                            <div class="info-box bg-light rounded p-3">
                                <div class="fw-bold text-primary"><?php echo htmlspecialchars($nhomMonHoc['TenMonHoc']); ?></div>
                                <div class="small">
                                    <span class="badge bg-secondary me-1"><?php echo htmlspecialchars($nhomMonHoc['MaMonHoc']); ?></span>
                                    <span class="badge bg-info"><?php echo $nhomMonHoc['SoTinChi']; ?> tín chỉ</span>
                                </div>
                            </div>
                        </div>
                        
                        <div class="row mb-3">
                            <div class="col-4">
                                <label for="diemChuyenCan" class="form-label">Điểm chuyên cần:</label>
                                <input type="number" class="form-control" id="diemChuyenCan" name="diemChuyenCan" min="0" max="10" step="0.1">
                            </div>
                            <div class="col-4">
                                <label for="diemKiemTra" class="form-label">Điểm kiểm tra:</label>
                                <input type="number" class="form-control" id="diemKiemTra" name="diemKiemTra" min="0" max="10" step="0.1">
                            </div>
                            <div class="col-4">
                                <label for="diemThi" class="form-label">Điểm thi:</label>
                                <input type="number" class="form-control" id="diemThi" name="diemThi" min="0" max="10" step="0.1">
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label for="ghiChu" class="form-label">Ghi chú:</label>
                            <textarea class="form-control" id="ghiChu" name="ghiChu" rows="2"></textarea>
                        </div>
                    </div>
                    <div class="modal-footer bg-light">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Hủy</button>
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save me-1"></i> Lưu điểm
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
<?php endif; ?>

<!-- Thêm script dưới các script khác trong file Details.php -->
<script>
// Kiểm tra thông báo từ URL khi tải trang
document.addEventListener('DOMContentLoaded', function() {
    const urlParams = new URLSearchParams(window.location.search);
    const gradeSuccess = urlParams.get('gradeSuccess');
    const message = urlParams.get('message');
    
    if (gradeSuccess === 'true') {
        // Hiển thị thông báo thành công
        showNotification(message || 'Nhập điểm thành công!', 'success');
        
        // Xóa tham số khỏi URL để tránh hiển thị lại khi refresh
        window.history.replaceState({}, document.title, window.location.pathname + '?id=' + urlParams.get('id'));
    }
});

// Hàm hiển thị thông báo
function showNotification(message, type = 'info') {
    const alertElement = document.createElement('div');
    alertElement.className = `alert alert-${type} alert-dismissible fade show position-fixed top-0 start-50 translate-middle-x mt-3`;
    alertElement.style.zIndex = '9999';
    alertElement.innerHTML = `
        <i class="fas fa-${type === 'success' ? 'check-circle' : 'info-circle'} me-2"></i> ${message}
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    `;
    document.body.appendChild(alertElement);
    
    // Tự động xóa thông báo sau 3 giây
    setTimeout(() => {
        alertElement.remove();
    }, 3000);
}

// Hàm xử lý form nhập điểm
function submitGradeForm(event) {
    event.preventDefault();
    
    // Kiểm tra xem có ít nhất một điểm được nhập
    const diemChuyenCan = document.getElementById('diemChuyenCan').value.trim();
    const diemKiemTra = document.getElementById('diemKiemTra').value.trim();
    const diemThi = document.getElementById('diemThi').value.trim();
    
    if (diemChuyenCan === '' && diemKiemTra === '' && diemThi === '') {
        showNotification('Vui lòng nhập ít nhất một loại điểm', 'warning');
        return;
    }
    
    // Lấy form và dữ liệu
    const form = document.getElementById('gradeForm');
    const formData = new FormData(form);
    
    // idNhomMonHoc đã được thêm như một hidden field trong form
    
    // Kiểm tra nếu đủ cả 3 điểm mới tính điểm tổng kết
    const hasAllScores = diemChuyenCan !== '' && diemKiemTra !== '' && diemThi !== '';
    
    if (hasAllScores) {
        // Đã có đủ 3 điểm, đánh dấu cập nhật trạng thái
        formData.append('updateStatus', 2); // 2 = Hoàn thành
    }
    
    // Hiển thị loading
    const loadingAlert = document.createElement('div');
    loadingAlert.className = 'alert alert-info position-fixed top-0 start-50 translate-middle-x mt-3';
    loadingAlert.style.zIndex = '9999';
    loadingAlert.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i> Đang xử lý...';
    document.body.appendChild(loadingAlert);
    
    // Gửi request
    fetch('ProcessGradeEntry.php', {
        method: 'POST',
        body: formData
    })
    .then(response => {
        if (!response.ok) {
            throw new Error('Network response was not ok: ' + response.statusText);
        }
        return response.json();
    })
    .then(data => {
        // Xóa thông báo loading
        loadingAlert.remove();
        
        if (data.success) {
            // Đóng modal
            const modal = bootstrap.Modal.getInstance(document.getElementById('gradeModal'));
            modal.hide();
            
            // Hiển thị thông báo thành công
            showNotification(data.message, 'success');
            
            // Chờ 1 giây rồi reload trang
            setTimeout(() => {
                window.location.reload();
            }, 1000);
        } else {
            // Hiển thị thông báo lỗi
            showNotification(data.message || 'Có lỗi xảy ra', 'danger');
        }
    })
    .catch(error => {
        // Xóa thông báo loading
        loadingAlert.remove();
        console.error('Error detail:', error);
        showNotification('Đã xảy ra lỗi khi lưu điểm. Vui lòng thử lại.', 'danger');
    });
}
</script>

<?php
// Lấy nội dung đã buffer và đưa vào layout
$contentForLayout = ob_get_clean();
include_once('../Shared/_LayoutAdmin.php');
?>
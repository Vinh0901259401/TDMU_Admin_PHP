<?php
ob_start();
session_start();
require_once("../Shared/connect.inc");

// Kiểm tra đăng nhập
if (!isset($_SESSION['TaiKhoan'])) {
    header("Location: ../../Auth/login.php");
    exit;
}

$tk = $_SESSION['TaiKhoan'];

// Kiểm tra xem có phải là sinh viên
if ($tk['IDQuyenTruyCap'] != 'QTC0000000005') {
    header("Location: ../Dashboard/index.php");
    exit;
}

// Lấy thông tin học kỳ hiện tại và đợt đăng ký
try {
    $stmtDotDangKy = $conn->prepare("
        SELECT ddk.*, hk.Ten as TenHocKy, nh.Ten as TenNamHoc 
        FROM dotdangky ddk
        JOIN hocky hk ON ddk.IDHocKy = hk.ID
        JOIN namhoc nh ON ddk.IDNamHoc = nh.ID
        WHERE ddk.TrangThai = 1
        LIMIT 1
    ");
    $stmtDotDangKy->execute();
    $dotDangKy = $stmtDotDangKy->fetch(PDO::FETCH_ASSOC);

    // Nếu không có đợt đăng ký nào đang diễn ra
    if (!$dotDangKy) {
        $dotDangKyActive = false;
    } else {
        $dotDangKyActive = true;
        $idDotDangKy = $dotDangKy['ID'];
        $hanMucTinChi = $dotDangKy['HanMucTinChi'];
    }
} catch (PDOException $e) {
    echo "Lỗi truy vấn: " . $e->getMessage();
    exit;
}

// Lọc theo khoa
$selectedKhoa = isset($_GET['khoa']) ? $_GET['khoa'] : '';
$whereClause = "WHERE 1=1";
$params = [];

if (!empty($selectedKhoa)) {
    $whereClause .= " AND mh.IDKhoa = ?";
    $params[] = $selectedKhoa;
}

// Lọc theo học kỳ
$selectedHocKy = isset($_GET['hocky']) ? $_GET['hocky'] : '';
if (!empty($selectedHocKy)) {
    $whereClause .= " AND mh.IDHocKy = ?";
    $params[] = $selectedHocKy;
}

// Lọc theo năm học
$selectedNamHoc = isset($_GET['namhoc']) ? $_GET['namhoc'] : '';
if (!empty($selectedNamHoc)) {
    $whereClause .= " AND mh.IDNamHoc = ?";
    $params[] = $selectedNamHoc;
}

// Tìm kiếm theo tên hoặc mã môn học
$searchTerm = isset($_GET['search']) ? $_GET['search'] : '';
if (!empty($searchTerm)) {
    $whereClause .= " AND (mh.TenMonHoc LIKE ? OR mh.MaMonHoc LIKE ?)";
    $params[] = "%{$searchTerm}%";
    $params[] = "%{$searchTerm}%";
}

// Phân trang
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$perPage = 10;
$offset = ($page - 1) * $perPage;

// Lấy danh sách khoa
try {
    $stmtKhoa = $conn->prepare("SELECT ID, Ten FROM khoa ORDER BY Ten");
    $stmtKhoa->execute();
    $khoaList = $stmtKhoa->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    echo "Lỗi truy vấn khoa: " . $e->getMessage();
    exit;
}

// Lấy danh sách học kỳ
try {
    $stmtHocKy = $conn->prepare("SELECT ID, Ten FROM hocky ORDER BY Ten");
    $stmtHocKy->execute();
    $hocKyList = $stmtHocKy->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    echo "Lỗi truy vấn học kỳ: " . $e->getMessage();
    exit;
}

// Lấy danh sách năm học
try {
    $stmtNamHoc = $conn->prepare("SELECT ID, Ten FROM namhoc ORDER BY Ten");
    $stmtNamHoc->execute();
    $namHocList = $stmtNamHoc->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    echo "Lỗi truy vấn năm học: " . $e->getMessage();
    exit;
}

// Lấy tổng số môn học
try {
    $stmtCount = $conn->prepare("
        SELECT COUNT(DISTINCT mh.ID) as total
        FROM monhoc mh
        JOIN nhommonhoc nmh ON mh.ID = nmh.IDMonHoc
        $whereClause
    ");
    $stmtCount->execute($params);
    $totalRecords = $stmtCount->fetchColumn();
    $totalPages = ceil($totalRecords / $perPage);
} catch (PDOException $e) {
    echo "Lỗi truy vấn đếm môn học: " . $e->getMessage();
    exit;
}

// Lấy danh sách môn học
try {
    $stmtMonHoc = $conn->prepare("
        SELECT DISTINCT mh.ID, mh.MaMonHoc, mh.TenMonHoc, mh.SoTinChi, mh.LoaiMonHoc,
               k.ID as IDKhoa, k.Ten as TenKhoa,
               hk.ID as IDHocKy, hk.Ten as TenHocKy,
               nh.ID as IDNamHoc, nh.Ten as TenNamHoc
        FROM monhoc mh
        JOIN khoa k ON mh.IDKhoa = k.ID
        JOIN hocky hk ON mh.IDHocKy = hk.ID
        JOIN namhoc nh ON mh.IDNamHoc = nh.ID
        JOIN nhommonhoc nmh ON mh.ID = nmh.IDMonHoc
        $whereClause
        ORDER BY mh.TenMonHoc
        LIMIT $offset, $perPage
    ");
    $stmtMonHoc->execute($params);
    $monHocList = $stmtMonHoc->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    echo "Lỗi truy vấn môn học: " . $e->getMessage();
    exit;
}

// Lấy thông tin lịch học đã đăng ký của sinh viên
try {
    $stmtLichHoc = $conn->prepare("
        SELECT bh.ThuHoc, bh.TietBatDau, bh.TietKetThuc
        FROM dangkymonhoc dk
        JOIN chitiet_dangkymonhoc ctdk ON dk.ID = ctdk.IDDangKy
        JOIN nhommonhoc nmh ON ctdk.IDNhomMonHoc = nmh.ID
        JOIN buoihoc bh ON nmh.ID = bh.IDNhomMonHoc
        WHERE dk.IDSinhVien = ? AND dk.TrangThaiDuyet = 1 AND ctdk.TrangThai = 1
    ");
    $stmtLichHoc->execute([$tk['ID']]);
    $registeredSchedule = [];
    
    while ($row = $stmtLichHoc->fetch(PDO::FETCH_ASSOC)) {
        $thuHoc = $row['ThuHoc'];
        for ($tiet = $row['TietBatDau']; $tiet <= $row['TietKetThuc']; $tiet++) {
            $registeredSchedule[$thuHoc][$tiet] = true;
        }
    }
} catch (PDOException $e) {
    echo "Lỗi truy vấn lịch học: " . $e->getMessage();
    exit;
}

// Lấy tổng số tín chỉ đã đăng ký
try {
    $stmtTinChi = $conn->prepare("
        SELECT SUM(mh.SoTinChi) as TongTinChi
        FROM dangkymonhoc dk
        JOIN chitiet_dangkymonhoc ctdk ON dk.ID = ctdk.IDDangKy
        JOIN nhommonhoc nmh ON ctdk.IDNhomMonHoc = nmh.ID
        JOIN monhoc mh ON nmh.IDMonHoc = mh.ID
        WHERE dk.IDSinhVien = ? AND dk.TrangThaiDuyet = 1 AND ctdk.TrangThai = 1
    ");
    $stmtTinChi->execute([$tk['ID']]);
    $tongTinChi = $stmtTinChi->fetchColumn() ?: 0;
} catch (PDOException $e) {
    echo "Lỗi truy vấn tín chỉ: " . $e->getMessage();
    exit;
}

// Định nghĩa giới hạn tín chỉ
$maxTinChi = isset($hanMucTinChi) ? $hanMucTinChi : 25; // Mặc định là 25 nếu không có đợt đăng ký

// Lấy thông tin các môn học đã đăng ký
try {
    $stmtRegistered = $conn->prepare("
        SELECT mh.ID, mh.MaMonHoc, mh.TenMonHoc, mh.SoTinChi, nmh.MaNhom, nmh.TenNhom, ctdk.ID as IDChiTiet
        FROM dangkymonhoc dk
        JOIN chitiet_dangkymonhoc ctdk ON dk.ID = ctdk.IDDangKy
        JOIN nhommonhoc nmh ON ctdk.IDNhomMonHoc = nmh.ID
        JOIN monhoc mh ON nmh.IDMonHoc = mh.ID
        WHERE dk.IDSinhVien = ? AND ctdk.TrangThai = 1
        ORDER BY mh.TenMonHoc
    ");
    $stmtRegistered->execute([$tk['ID']]);
    $registeredCourses = $stmtRegistered->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    echo "Lỗi truy vấn môn học đã đăng ký: " . $e->getMessage();
    exit;
}

// Định nghĩa CSS cho trang
$pageStyles = '
<style>
    .course-card {
        transition: transform 0.2s ease;
        border-left: 3px solid transparent;
    }
    .course-card:hover {
        transform: translateY(-3px);
        border-left: 3px solid #007bff;
        box-shadow: 0 .5rem 1rem rgba(0,0,0,.15)!important;
    }
    .badge-mandatory {
        background-color: #dc3545;
    }
    .badge-optional {
        background-color: #198754;
    }
    .sidebar {
        position: sticky;
        top: 20px;
    }
    .credit-info {
        border-radius: 10px;
        overflow: hidden;
    }
    .schedule-table {
        font-size: 0.8rem;
    }
    .schedule-table th,
    .schedule-table td {
        width: 35px;
        height: 30px;
        text-align: center;
        padding: 3px;
        border: 1px solid #dee2e6;
    }
    .schedule-occupied {
        background-color: #f8d7da;
    }
    .schedule-free {
        background-color: #d1e7dd;
    }
    .filters {
        background-color: #f8f9fa;
        padding: 15px;
        border-radius: 10px;
        margin-bottom: 20px;
    }
    @media (max-width: 768px) {
        .filters .form-group {
            margin-bottom: 10px;
        }
    }
</style>
';
?>

<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Đăng Ký Học Phần</title>
    <?php echo $pageStyles; ?>
</head>
<body>
    <div class="container-fluid py-4">
        <h2 class="mb-4 text-center">Đăng Ký Học Phần</h2>
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2 class="mb-0">Đăng Ký Học Phần</h2>
            
            <?php 
            // Đếm số môn học đã đăng ký
            try {
                $stmtCountRegistered = $conn->prepare("
                    SELECT COUNT(*) as count
                    FROM chitiet_dangkymonhoc ctdk
                    JOIN dangkymonhoc dk ON ctdk.IDDangKy = dk.ID
                    WHERE dk.IDSinhVien = ? AND ctdk.TrangThai = 1
                ");
                $stmtCountRegistered->execute([$tk['ID']]);
                $countRegistered = $stmtCountRegistered->fetchColumn();
                
                echo '<div>';
                echo '<a href="MyRegistrations.php" class="btn btn-primary">';
                echo '<i class="fas fa-clipboard-list me-1"></i> Môn Học Đã Đăng Ký';
                if ($countRegistered > 0) {
                    echo ' <span class="badge bg-light text-dark">' . $countRegistered . '</span>';
                }
                echo '</a>';
                echo '</div>';
            } catch (PDOException $e) {
                // Bỏ qua lỗi nếu có
            }
            ?>
        </div>
        
        <?php if (!$dotDangKyActive): ?>
            <div class="alert alert-warning text-center">
                <i class="fas fa-exclamation-triangle me-2"></i>
                Hiện tại không có đợt đăng ký môn học nào đang diễn ra. Vui lòng quay lại sau.
            </div>
        <?php else: ?>
            <div class="alert alert-info mb-4">
                <div class="d-flex justify-content-between align-items-center flex-wrap">
                    <div>
                        <h5 class="mb-1"><i class="fas fa-calendar-alt me-2"></i><?php echo htmlspecialchars($dotDangKy['Ten']); ?></h5>
                        <p class="mb-0"><?php echo htmlspecialchars($dotDangKy['TenHocKy'] . ' - ' . $dotDangKy['TenNamHoc']); ?></p>
                    </div>
                    <div class="mt-2 mt-sm-0">
                        <p class="mb-0"><strong>Thời gian bắt đầu:</strong> <?php echo date('d/m/Y H:i', strtotime($dotDangKy['ThoiGianBatDau'])); ?></p>
                        <p class="mb-0"><strong>Thời gian kết thúc:</strong> <?php echo date('d/m/Y H:i', strtotime($dotDangKy['ThoiGianKetThuc'])); ?></p>
                    </div>
                </div>
            </div>
            
            <div class="row">
                <!-- Main Content -->
                <div class="col-lg-8">
                    <!-- Filters -->
                    <div class="filters mb-4">
                        <form method="get" action="" class="row g-3">
                            <div class="col-md-3">
                                <label for="khoa" class="form-label">Khoa</label>
                                <select class="form-select" id="khoa" name="khoa">
                                    <option value="">Tất cả khoa</option>
                                    <?php foreach ($khoaList as $khoa): ?>
                                        <option value="<?php echo $khoa['ID']; ?>" <?php echo ($selectedKhoa == $khoa['ID']) ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($khoa['Ten']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            
                            <div class="col-md-3">
                                <label for="hocky" class="form-label">Học kỳ</label>
                                <select class="form-select" id="hocky" name="hocky">
                                    <option value="">Tất cả học kỳ</option>
                                    <?php foreach ($hocKyList as $hocky): ?>
                                        <option value="<?php echo $hocky['ID']; ?>" <?php echo ($selectedHocKy == $hocky['ID']) ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($hocky['Ten']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            
                            <div class="col-md-3">
                                <label for="namhoc" class="form-label">Năm học</label>
                                <select class="form-select" id="namhoc" name="namhoc">
                                    <option value="">Tất cả năm học</option>
                                    <?php foreach ($namHocList as $namhoc): ?>
                                        <option value="<?php echo $namhoc['ID']; ?>" <?php echo ($selectedNamHoc == $namhoc['ID']) ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($namhoc['Ten']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            
                            <div class="col-md-3">
                                <label for="search" class="form-label">Tìm kiếm</label>
                                <div class="input-group">
                                    <input type="text" class="form-control" id="search" name="search" placeholder="Tên hoặc mã môn học" value="<?php echo htmlspecialchars($searchTerm); ?>">
                                    <button class="btn btn-primary" type="submit">
                                        <i class="fas fa-search"></i>
                                    </button>
                                </div>
                            </div>
                        </form>
                    </div>
                    
                    <!-- Courses List -->
                    <?php if (count($monHocList) > 0): ?>
                        <div class="row row-cols-1 row-cols-md-2 g-4 mb-4">
                            <?php foreach ($monHocList as $monHoc): ?>
                                <div class="col">
                                    <div class="card h-100 course-card">
                                        <div class="card-header d-flex justify-content-between align-items-center">
                                            <h5 class="card-title mb-0"><?php echo htmlspecialchars($monHoc['MaMonHoc']); ?></h5>
                                            <span class="badge <?php echo $monHoc['LoaiMonHoc'] == 0 ? 'badge-mandatory' : 'badge-optional'; ?> text-white">
                                                <?php echo $monHoc['LoaiMonHoc'] == 0 ? 'Bắt buộc' : 'Tự chọn'; ?>
                                            </span>
                                        </div>
                                        <div class="card-body">
                                            <h6 class="card-subtitle mb-2 text-muted"><?php echo htmlspecialchars($monHoc['TenMonHoc']); ?></h6>
                                            <p class="card-text">
                                                <strong>Số tín chỉ:</strong> <?php echo $monHoc['SoTinChi']; ?><br>
                                                <strong>Khoa:</strong> <?php echo htmlspecialchars($monHoc['TenKhoa']); ?><br>
                                                <strong>Học kỳ:</strong> <?php echo htmlspecialchars($monHoc['TenHocKy']); ?><br>
                                                <strong>Năm học:</strong> <?php echo htmlspecialchars($monHoc['TenNamHoc']); ?>
                                            </p>
                                        </div>
                                        <?php
                                        // Kiểm tra xem sinh viên đã đăng ký môn học này chưa
                                        $daDangKy = false;
                                        $chiTietDangKyId = null;
                                        try {
                                            $stmtCheckDaDangKy = $conn->prepare("
                                                SELECT ctdk.ID
                                                FROM chitiet_dangkymonhoc ctdk
                                                JOIN dangkymonhoc dk ON ctdk.IDDangKy = dk.ID
                                                JOIN nhommonhoc nmh ON ctdk.IDNhomMonHoc = nmh.ID
                                                WHERE dk.IDSinhVien = ? AND nmh.IDMonHoc = ? AND ctdk.TrangThai = 1
                                            ");
                                            $stmtCheckDaDangKy->execute([$tk['ID'], $monHoc['ID']]);
                                            $result = $stmtCheckDaDangKy->fetch(PDO::FETCH_ASSOC);
                                            if ($result) {
                                                $daDangKy = true;
                                                $chiTietDangKyId = $result['ID'];
                                            }
                                        } catch (PDOException $e) {
                                            // Bỏ qua lỗi
                                        }
                                        ?>
                                        <div class="card-footer bg-light d-flex justify-content-between align-items-center">
                                            <?php if ($daDangKy): ?>
                                                <span class="badge bg-success me-2">Đã đăng ký</span>
                                                <div>
                                                    <a href="Register.php?id=<?php echo $monHoc['ID']; ?>" class="btn btn-sm btn-info me-2">
                                                        <i class="fas fa-info-circle me-1"></i> Chi tiết
                                                    </a>
                                                </div>
                                            <?php else: ?>
                                                <span class="text-muted">Chưa đăng ký</span>
                                                <a href="Register.php?id=<?php echo $monHoc['ID']; ?>" class="btn btn-sm btn-primary">
                                                    <i class="fas fa-plus me-1"></i> Đăng ký
                                                </a>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                        
                        <!-- Pagination -->
                        <?php if ($totalPages > 1): ?>
                            <nav aria-label="Page navigation">
                                <ul class="pagination justify-content-center">
                                    <?php if ($page > 1): ?>
                                        <li class="page-item">
                                            <a class="page-link" href="?page=<?php echo ($page - 1); ?>&khoa=<?php echo urlencode($selectedKhoa); ?>&hocky=<?php echo urlencode($selectedHocKy); ?>&namhoc=<?php echo urlencode($selectedNamHoc); ?>&search=<?php echo urlencode($searchTerm); ?>" aria-label="Previous">
                                                <span aria-hidden="true">&laquo;</span>
                                            </a>
                                        </li>
                                    <?php endif; ?>
                                    
                                    <?php
                                        // Hiển thị các nút trang
                                        $start = max(1, $page - 2);
                                        $end = min($totalPages, $page + 2);
                                        
                                        // Hiển thị nút trang đầu nếu cần
                                        if ($start > 1) {
                                            echo '<li class="page-item"><a class="page-link" href="?page=1&khoa=' . urlencode($selectedKhoa) . '&hocky=' . urlencode($selectedHocKy) . '&namhoc=' . urlencode($selectedNamHoc) . '&search=' . urlencode($searchTerm) . '">1</a></li>';
                                            
                                            if ($start > 2) {
                                                echo '<li class="page-item disabled"><span class="page-link">...</span></li>';
                                            }
                                        }
                                        
                                        // Hiển thị các trang
                                        for ($i = $start; $i <= $end; $i++) {
                                            echo '<li class="page-item ' . ($i == $page ? 'active' : '') . '">';
                                            echo '<a class="page-link" href="?page=' . $i . '&khoa=' . urlencode($selectedKhoa) . '&hocky=' . urlencode($selectedHocKy) . '&namhoc=' . urlencode($selectedNamHoc) . '&search=' . urlencode($searchTerm) . '">' . $i . '</a>';
                                            echo '</li>';
                                        }
                                        
                                        // Hiển thị nút trang cuối nếu cần
                                        if ($end < $totalPages) {
                                            if ($end < $totalPages - 1) {
                                                echo '<li class="page-item disabled"><span class="page-link">...</span></li>';
                                            }
                                            
                                            echo '<li class="page-item"><a class="page-link" href="?page=' . $totalPages . '&khoa=' . urlencode($selectedKhoa) . '&hocky=' . urlencode($selectedHocKy) . '&namhoc=' . urlencode($selectedNamHoc) . '&search=' . urlencode($searchTerm) . '">' . $totalPages . '</a></li>';
                                        }
                                    ?>
                                    
                                    <?php if ($page < $totalPages): ?>
                                        <li class="page-item">
                                            <a class="page-link" href="?page=<?php echo ($page + 1); ?>&khoa=<?php echo urlencode($selectedKhoa); ?>&hocky=<?php echo urlencode($selectedHocKy); ?>&namhoc=<?php echo urlencode($selectedNamHoc); ?>&search=<?php echo urlencode($searchTerm); ?>" aria-label="Next">
                                                <span aria-hidden="true">&raquo;</span>
                                            </a>
                                        </li>
                                    <?php endif; ?>
                                </ul>
                            </nav>
                            <div class="text-center text-muted small mb-4">
                                Trang <?php echo $page; ?> / <?php echo $totalPages; ?> (<?php echo $totalRecords; ?> môn học)
                            </div>
                        <?php endif; ?>
                    <?php else: ?>
                        <div class="alert alert-info">
                            <i class="fas fa-info-circle me-2"></i> Không tìm thấy môn học nào phù hợp với tiêu chí tìm kiếm.
                        </div>
                    <?php endif; ?>
                </div>
                
                <!-- Sidebar -->
                <div class="col-lg-4">
                    <div class="sidebar">
                        <!-- Credit Info -->
                        <div class="card mb-4 credit-info">
                            <div class="card-header bg-primary text-white">
                                <h5 class="card-title mb-0"><i class="fas fa-graduation-cap me-2"></i>Thông tin tín chỉ</h5>
                            </div>
                            <div class="card-body">
                                <div class="progress mb-3" style="height: 25px;">
                                    <div class="progress-bar <?php echo ($tongTinChi > $maxTinChi * 0.8) ? 'bg-warning' : 'bg-success'; ?>" 
                                         role="progressbar" 
                                         style="width: <?php echo min(100, ($tongTinChi / $maxTinChi) * 100); ?>%;" 
                                         aria-valuenow="<?php echo $tongTinChi; ?>" 
                                         aria-valuemin="0" 
                                         aria-valuemax="<?php echo $maxTinChi; ?>">
                                        <?php echo $tongTinChi; ?>/<?php echo $maxTinChi; ?>
                                    </div>
                                </div>
                                <div class="row text-center">
                                    <div class="col-4">
                                        <div class="border rounded p-2">
                                            <div class="small text-muted">Đã đăng ký</div>
                                            <div class="fw-bold text-success"><?php echo $tongTinChi; ?></div>
                                        </div>
                                    </div>
                                    <div class="col-4">
                                        <div class="border rounded p-2">
                                            <div class="small text-muted">Tối đa</div>
                                            <div class="fw-bold"><?php echo $maxTinChi; ?></div>
                                        </div>
                                    </div>
                                    <div class="col-4">
                                        <div class="border rounded p-2">
                                            <div class="small text-muted">Còn lại</div>
                                            <div class="fw-bold text-primary"><?php echo max(0, $maxTinChi - $tongTinChi); ?></div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Schedule -->
                        <div class="card mb-4">
                            <div class="card-header bg-info text-white">
                                <h5 class="card-title mb-0"><i class="fas fa-calendar-check me-2"></i>Lịch học hiện tại</h5>
                            </div>
                            <div class="card-body p-2">
                                <table class="table table-sm schedule-table mb-0">
                                    <thead>
                                        <tr>
                                            <th></th>
                                            <th>2</th>
                                            <th>3</th>
                                            <th>4</th>
                                            <th>5</th>
                                            <th>6</th>
                                            <th>7</th>
                                            <th>CN</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php for ($tiet = 1; $tiet <= 10; $tiet++): ?>
                                            <tr>
                                                <td><?php echo $tiet; ?></td>
                                                <?php for ($thu = 2; $thu <= 8; $thu++): ?>
                                                    <td class="<?php echo isset($registeredSchedule[$thu][$tiet]) ? 'schedule-occupied' : 'schedule-free'; ?>">
                                                        <?php if (isset($registeredSchedule[$thu][$tiet])): ?>
                                                            <i class="fas fa-check"></i>
                                                        <?php endif; ?>
                                                    </td>
                                                <?php endfor; ?>
                                            </tr>
                                        <?php endfor; ?>
                                    </tbody>
                                </table>
                                <div class="d-flex justify-content-center mt-2">
                                    <span class="badge bg-danger me-2">Đã đăng ký</span>
                                    <span class="badge bg-success">Còn trống</span>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Registered Courses -->
                        <div class="card">
                            <div class="card-header bg-success text-white">
                                <h5 class="card-title mb-0"><i class="fas fa-list-check me-2"></i>Môn học đã đăng ký</h5>
                            </div>
                            <div class="card-body p-0">
                                <?php if (count($registeredCourses) > 0): ?>
                                    <div class="list-group list-group-flush">
                                        <?php foreach ($registeredCourses as $course): ?>
                                            <div class="list-group-item d-flex justify-content-between align-items-center">
                                                <div>
                                                    <div class="fw-bold"><?php echo htmlspecialchars($course['MaMonHoc']); ?> - <?php echo htmlspecialchars($course['MaNhom']); ?></div>
                                                    <div><?php echo htmlspecialchars($course['TenMonHoc']); ?></div>
                                                    <div class="text-muted small">Số tín chỉ: <?php echo $course['SoTinChi']; ?></div>
                                                </div>
                                                <a href="DeleteRegistration.php?id=<?php echo $course['IDChiTiet']; ?>&redirect=Index.php" 
                                                   class="btn btn-sm btn-outline-danger">
                                                    <i class="fas fa-times"></i> Hủy
                                                </a>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                <?php else: ?>
                                    <div class="p-4 text-center">
                                        <i class="fas fa-book-open fa-3x mb-3 text-muted"></i>
                                        <p>Bạn chưa đăng ký môn học nào.</p>
                                    </div>
                                <?php endif; ?>
                            </div>
                            <div class="card-footer text-center">
                                <a href="MySchedule.php" class="btn btn-outline-primary">
                                    <i class="fas fa-eye me-1"></i> Xem thời khóa biểu
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <script>
        // Auto-submit form when filters change
        document.querySelectorAll('#khoa, #hocky, #namhoc').forEach(function(element) {
            element.addEventListener('change', function() {
                this.form.submit();
            });
        });
    </script>
</body>
</html>

<?php
$contentForLayout = ob_get_clean();

// Include layout
include("../Shared/_LayoutAdmin.php");
?>
<?php
ob_start();
session_start();
require_once("../Shared/connect.inc");

// Kiểm tra đăng nhập
if (!isset($_SESSION['TaiKhoan'])) {
    header("Location: ../../Auth/login.php");
    exit;
}

// Khởi tạo giá trị mặc định cho học kỳ và năm học
$idHocKy = null;
$idNamHoc = null;

// Lấy học kỳ và năm học hiện tại từ tham số hoặc sử dụng mặc định
if (isset($_GET['hocky'])) {
    $idHocKy = $_GET['hocky'];
}

if (isset($_GET['namhoc'])) {
    $idNamHoc = $_GET['namhoc'];
}

// Nếu không có tham số, lấy học kỳ và năm học hiện tại từ CSDL
if (!$idHocKy || !$idNamHoc) {
    try {
        $stmtHocKyHienTai = $conn->prepare("
            SELECT HK.ID as IDHocKy, NH.ID as IDNamHoc
            FROM hocky HK
            JOIN namhoc NH ON 1=1
            WHERE NOW() BETWEEN NH.NgayBatDau AND NH.NgayKetThuc
            ORDER BY NH.NgayBatDau DESC, HK.ID ASC
            LIMIT 1
        ");
        $stmtHocKyHienTai->execute();
        $hocKyHienTai = $stmtHocKyHienTai->fetch(PDO::FETCH_ASSOC);
        
        if ($hocKyHienTai) {
            $idHocKy = $idHocKy ?: $hocKyHienTai['IDHocKy'];
            $idNamHoc = $idNamHoc ?: $hocKyHienTai['IDNamHoc'];
        }
    } catch (PDOException $e) {
        // Bỏ qua lỗi
    }
}

$tk = $_SESSION['TaiKhoan'];

// Kiểm tra xem có phải là sinh viên
if ($tk['IDQuyenTruyCap'] != 'QTC0000000005') {
    header("Location: ../Dashboard/index.php");
    exit;
}

// Cập nhật trạng thái các đợt đăng ký
try {
    $stmtUpdate = $conn->prepare("CALL sp_CapNhatTrangThaiDotDangKy()");
    $stmtUpdate->execute();
} catch (PDOException $e) {
    // Bỏ qua lỗi nếu có
}

// Kiểm tra đợt đăng ký đang mở
try {
    $stmtDotDangKy = $conn->prepare("
        SELECT ddk.*, hk.Ten as TenHocKy, nh.Ten as TenNamHoc 
        FROM dotdangky ddk
        JOIN hocky hk ON ddk.IDHocKy = hk.ID
        JOIN namhoc nh ON ddk.IDNamHoc = nh.ID
        WHERE ddk.TrangThai = 1 
        AND (
            ddk.LoaiDangKy = 3 -- Đợt hủy đăng ký
            OR ddk.LoaiDangKy IN (1, 2) -- Đợt đăng ký chính thức hoặc bổ sung
        )
        AND NOW() BETWEEN ddk.ThoiGianBatDau AND ddk.ThoiGianKetThuc
        ORDER BY ddk.LoaiDangKy
    ");
    $stmtDotDangKy->execute();
    $dotDangKy = $stmtDotDangKy->fetchAll(PDO::FETCH_ASSOC);
    
    $coTheDangKy = false;
    $coTheHuy = false;
    
    foreach ($dotDangKy as $dot) {
        if ($dot['LoaiDangKy'] == 3) {
            $coTheHuy = true;
        } else {
            $coTheDangKy = true;
        }
    }
} catch (PDOException $e) {
    echo "Lỗi truy vấn: " . $e->getMessage();
    exit;
}

// Lấy danh sách môn học đã đăng ký
try {
    $stmtMonHocDaDangKy = $conn->prepare("
        SELECT 
            ctdk.ID as ChiTietID,
            ctdk.TrangThai,
            dk.TrangThaiDuyet,
            mh.ID as MonHocID,
            mh.MaMonHoc,
            mh.TenMonHoc,
            mh.SoTinChi,
            nmh.ID as NhomMonHocID,
            nmh.MaNhom,
            tk.HoTen as TenGiangVien,
            hk.Ten as TenHocKy,
            nh.Ten as TenNamHoc,
            ddk.LoaiDangKy,
            ddk.TrangThai as TrangThaiDot
        FROM dangkymonhoc dk
        JOIN chitiet_dangkymonhoc ctdk ON dk.ID = ctdk.IDDangKy
        JOIN nhommonhoc nmh ON ctdk.IDNhomMonHoc = nmh.ID
        JOIN monhoc mh ON nmh.IDMonHoc = mh.ID
        LEFT JOIN taikhoan tk ON nmh.IDGiangVien = tk.ID
        JOIN dotdangky ddk ON dk.IDDotDangKy = ddk.ID
        JOIN hocky hk ON ddk.IDHocKy = hk.ID
        JOIN namhoc nh ON ddk.IDNamHoc = nh.ID
        WHERE dk.IDSinhVien = ? AND ctdk.TrangThai = 1
        ORDER BY ddk.IDNamHoc DESC, ddk.IDHocKy DESC, mh.TenMonHoc
    ");
    $stmtMonHocDaDangKy->execute([$tk['ID']]);
    $monHocDaDangKy = $stmtMonHocDaDangKy->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    echo "Lỗi truy vấn: " . $e->getMessage();
    exit;
}

// Lấy thông tin lịch học của từng nhóm môn học
$lichHocByNhom = [];
try {
    foreach ($monHocDaDangKy as $monHoc) {
        $stmtLichHoc = $conn->prepare("
            SELECT bh.*, ph.MaPhong, ph.TenPhong
            FROM buoihoc bh
            LEFT JOIN phonghoc ph ON bh.IDPhongHoc = ph.ID
            WHERE bh.IDNhomMonHoc = ?
            ORDER BY bh.ThuHoc, bh.TietBatDau
        ");
        $stmtLichHoc->execute([$monHoc['NhomMonHocID']]);
        $lichHocByNhom[$monHoc['NhomMonHocID']] = $stmtLichHoc->fetchAll(PDO::FETCH_ASSOC);
    }
} catch (PDOException $e) {
    echo "Lỗi truy vấn lịch học: " . $e->getMessage();
    exit;
}

// Định nghĩa tên các ngày trong tuần
$tenThu = [
    1 => 'Thứ Hai',
    2 => 'Thứ Ba',
    3 => 'Thứ Tư',
    4 => 'Thứ Năm',
    5 => 'Thứ Sáu',
    6 => 'Thứ Bảy',
    7 => 'Chủ Nhật'
];

// Định nghĩa CSS cho trang
$pageStyles = '
<style>
    .registration-card {
        margin-bottom: 20px;
        transition: transform 0.2s;
    }
    .registration-card:hover {
        transform: translateY(-5px);
        box-shadow: 0 .5rem 1rem rgba(0,0,0,.15)!important;
    }
    .registration-card .card-header {
        background-color: #f8f9fa;
        border-bottom: 1px solid #dee2e6;
    }
    .registration-card.approved {
        border-left: 4px solid #198754;
    }
    .registration-card.pending {
        border-left: 4px solid #ffc107;
    }
    .registration-card.canceled {
        border-left: 4px solid #dc3545;
    }
    .semester-header {
        background-color: #e9ecef;
        padding: 10px 15px;
        margin-bottom: 20px;
        border-radius: 5px;
        font-weight: bold;
    }
    .schedule-badge {
        font-size: 0.8rem;
        margin-right: 5px;
        margin-bottom: 5px;
        white-space: normal;
        text-align: left;
        display: inline-block;
    }
</style>
';
?>

<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Môn Học Đã Đăng Ký</title>
    <?php echo $pageStyles; ?>
</head>
<body>
    <div class="container-fluid py-4">
        <nav aria-label="breadcrumb" class="mb-4">
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="Index.php">Đăng Ký Học Phần</a></li>
                <li class="breadcrumb-item active">Môn Học Đã Đăng Ký</li>
            </ol>
        </nav>
        
        <h2 class="mb-4"><i class="fas fa-clipboard-list me-2"></i>Môn Học Đã Đăng Ký</h2>
        
        <?php if (isset($_SESSION['success_message'])): ?>
            <div class="alert alert-success">
                <i class="fas fa-check-circle me-2"></i><?php echo $_SESSION['success_message']; ?>
            </div>
            <?php unset($_SESSION['success_message']); ?>
        <?php endif; ?>
        
        <?php if (isset($_SESSION['error_message'])): ?>
            <div class="alert alert-danger">
                <i class="fas fa-exclamation-circle me-2"></i><?php echo $_SESSION['error_message']; ?>
            </div>
            <?php unset($_SESSION['error_message']); ?>
        <?php endif; ?>
        
        <div class="row mb-4">
            <div class="col-md-12">
                <div class="card">
                    <div class="card-header bg-primary text-white">
                        <h5 class="card-title mb-0"><i class="fas fa-info-circle me-2"></i>Thông tin đợt đăng ký</h5>
                    </div>
                    <div class="card-body">
                        <?php if (count($dotDangKy) > 0): ?>
                            <div class="row">
                                <?php foreach ($dotDangKy as $dot): ?>
                                    <div class="col-md-6 mb-3">
                                        <div class="border rounded p-3 h-100">
                                            <h6 class="text-primary mb-2">
                                                <?php echo htmlspecialchars($dot['Ten']); ?>
                                            </h6>
                                            <p class="mb-1">
                                                <i class="fas fa-calendar-alt me-1"></i> 
                                                <?php echo date('d/m/Y', strtotime($dot['ThoiGianBatDau'])); ?> - 
                                                <?php echo date('d/m/Y', strtotime($dot['ThoiGianKetThuc'])); ?>
                                            </p>
                                            <p class="mb-1">
                                                <i class="fas fa-graduation-cap me-1"></i> 
                                                <?php echo htmlspecialchars($dot['TenHocKy'] . ' - ' . $dot['TenNamHoc']); ?>
                                            </p>
                                            <p class="mb-0">
                                                <span class="badge bg-<?php echo $dot['LoaiDangKy'] == 3 ? 'warning' : 'success'; ?> text-white">
                                                    <?php 
                                                    if ($dot['LoaiDangKy'] == 1) echo 'Đăng ký chính thức';
                                                    else if ($dot['LoaiDangKy'] == 2) echo 'Đăng ký bổ sung';
                                                    else if ($dot['LoaiDangKy'] == 3) echo 'Hủy đăng ký';
                                                    ?>
                                                </span>
                                            </p>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php else: ?>
                            <div class="alert alert-info mb-0">
                                <i class="fas fa-info-circle me-2"></i>Hiện không có đợt đăng ký nào đang mở.
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="row">
            <div class="col-md-12">
                <?php if (count($monHocDaDangKy) > 0): ?>
                    <div class="mb-4">
                        <div class="d-flex justify-content-between mb-3">
                            <h4><i class="fas fa-book me-2"></i>Danh sách môn học đã đăng ký</h4>
                            <div>
                                <a href="MySchedule.php" class="btn btn-primary">
                                    <i class="fas fa-calendar-alt me-1"></i> Xem thời khóa biểu
                                </a>
                                <a href="Index.php" class="btn btn-success ms-2">
                                    <i class="fas fa-plus me-1"></i> Đăng ký thêm môn học
                                </a>
                            </div>
                        </div>
                        
                        <?php
                        $currentHocKy = '';
                        $currentNamHoc = '';
                        $tongTinChi = 0;
                        ?>
                        
                        <?php foreach ($monHocDaDangKy as $monHoc): ?>
                            <?php
                            $hocKyNamHoc = $monHoc['TenHocKy'] . ' - ' . $monHoc['TenNamHoc'];
                            if ($currentHocKy != $monHoc['TenHocKy'] || $currentNamHoc != $monHoc['TenNamHoc']) {
                                if ($currentHocKy != '') {
                                    // Hiển thị tổng tín chỉ cho học kỳ trước
                                    echo '<div class="text-end mb-4"><strong>Tổng số tín chỉ: ' . $tongTinChi . '</strong></div>';
                                    $tongTinChi = 0;
                                }
                                $currentHocKy = $monHoc['TenHocKy'];
                                $currentNamHoc = $monHoc['TenNamHoc'];
                                echo '<div class="semester-header mb-3">' . htmlspecialchars($hocKyNamHoc) . '</div>';
                            }
                            $tongTinChi += $monHoc['SoTinChi'];
                            
                            $trangThaiClass = '';
                            $trangThaiText = '';
                            if ($monHoc['TrangThaiDuyet'] == 1) {
                                $trangThaiClass = 'approved';
                                $trangThaiText = '<span class="badge bg-success">Đã duyệt</span>';
                            } elseif ($monHoc['TrangThaiDuyet'] == 0) {
                                $trangThaiClass = 'pending';
                                $trangThaiText = '<span class="badge bg-warning text-dark">Chờ duyệt</span>';
                            } else {
                                $trangThaiClass = 'canceled';
                                $trangThaiText = '<span class="badge bg-danger">Đã hủy</span>';
                            }
                            
                            // Kiểm tra có thể hủy đăng ký không
                            $coTheHuyMonNay = $coTheHuy || ($coTheDangKy && $monHoc['TrangThaiDot'] == 1);
                            ?>
                            
                            <div class="card registration-card <?php echo $trangThaiClass; ?> mb-3">
                                <div class="card-header d-flex justify-content-between align-items-center">
                                    <h5 class="card-title mb-0">
                                        <?php echo htmlspecialchars($monHoc['MaMonHoc']); ?> - <?php echo htmlspecialchars($monHoc['TenMonHoc']); ?>
                                    </h5>
                                    <div>
                                        <?php echo $trangThaiText; ?>
                                    </div>
                                </div>
                                <div class="card-body">
                                    <div class="row">
                                        <div class="col-md-6">
                                            <p><i class="fas fa-users me-1"></i> <strong>Nhóm:</strong> <?php echo htmlspecialchars($monHoc['MaNhom']); ?></p>
                                            <p><i class="fas fa-user-tie me-1"></i> <strong>Giảng viên:</strong> <?php echo htmlspecialchars($monHoc['TenGiangVien'] ?: 'Chưa phân công'); ?></p>
                                            <p><i class="fas fa-graduation-cap me-1"></i> <strong>Số tín chỉ:</strong> <?php echo $monHoc['SoTinChi']; ?></p>
                                        </div>
                                        <div class="col-md-6">
                                            <p><i class="fas fa-calendar-alt me-1"></i> <strong>Lịch học:</strong></p>
                                            <div>
                                                <?php if (isset($lichHocByNhom[$monHoc['NhomMonHocID']]) && count($lichHocByNhom[$monHoc['NhomMonHocID']]) > 0): ?>
                                                    <?php foreach ($lichHocByNhom[$monHoc['NhomMonHocID']] as $lichHoc): ?>
                                                        <span class="badge bg-light text-dark schedule-badge">
                                                            <?php echo $tenThu[$lichHoc['ThuHoc']]; ?> (<?php echo $lichHoc['TietBatDau']; ?>-<?php echo $lichHoc['TietKetThuc']; ?>)
                                                            <?php if ($lichHoc['MaPhong']): ?>
                                                                - <?php echo htmlspecialchars($lichHoc['MaPhong']); ?>
                                                            <?php endif; ?>
                                                        </span>
                                                    <?php endforeach; ?>
                                                <?php else: ?>
                                                    <span class="text-muted">Chưa có thông tin lịch học</span>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <div class="d-flex justify-content-end mt-3">
                                        <a href="Register.php?id=<?php echo $monHoc['MonHocID']; ?>" class="btn btn-sm btn-outline-info me-2">
                                            <i class="fas fa-info-circle me-1"></i> Chi tiết môn học
                                        </a>
                                        <?php if ($coTheHuyMonNay && $monHoc['TrangThaiDuyet'] != 2): ?>
                                            <a href="DeleteRegistration.php?id=<?php echo $monHoc['ChiTietID']; ?>&redirect=MyRegistrations.php" class="btn btn-sm btn-outline-danger">
                                                <i class="fas fa-trash-alt me-1"></i> Hủy đăng ký
                                            </a>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                        
                        <!-- Hiển thị tổng tín chỉ cho học kỳ cuối cùng -->
                        <div class="text-end mb-4"><strong>Tổng số tín chỉ: <?php echo $tongTinChi; ?></strong></div>
                    </div>
                <?php else: ?>
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle me-2"></i> Bạn chưa đăng ký môn học nào.
                    </div>
                <?php endif; ?>
            </div>
        </div>
        
        <div class="mt-4">
            <a href="Index.php" class="btn btn-primary">
                <i class="fas fa-arrow-left me-1"></i> Quay lại trang đăng ký
            </a>
        </div>
    </div>
</body>
</html>

<?php
$contentForLayout = ob_get_clean();

// Include layout
include("../Shared/_LayoutAdmin.php");
?>
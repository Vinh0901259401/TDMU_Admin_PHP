<?php
// Kết nối database
require_once("../Shared/connect.inc");

// Lấy mã nhóm từ query string
$maNhom = isset($_GET['ma']) ? trim($_GET['ma']) : '';

// Kết quả mặc định
$result = ['exists' => false];

if (!empty($maNhom)) {
    try {
        $stmt = $conn->prepare("SELECT COUNT(*) FROM nhommonhoc WHERE MaNhom = ?");
        $stmt->execute([$maNhom]);
        
        // Nếu count > 0 tức là đã tồn tại
        $result['exists'] = ($stmt->fetchColumn() > 0);
    } catch (PDOException $e) {
        error_log("Lỗi kiểm tra mã nhóm AJAX: " . $e->getMessage());
    }
}

// Tiêu đề trang
$pageTitle = "Thêm nhóm môn học";

// Lấy thông tin người dùng từ session
session_start();
$accessLevel = 0;
$tk = isset($_SESSION['TaiKhoan']) ? $_SESSION['TaiKhoan'] : null;

// Khởi tạo biến $success và $idMonHoc để tránh lỗi undefined
$success = false;
$idMonHoc = isset($_GET['idMonHoc']) ? $_GET['idMonHoc'] : null;
$monHoc = null;

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

// Kiểm tra quyền tạo nhóm môn học (cấp độ 2 trở xuống)
if ($accessLevel > 2 || $accessLevel < 1) {
    $_SESSION['message'] = "Bạn không có quyền tạo nhóm môn học!";
    $_SESSION['messageType'] = "danger";
    header('Location: index.php');
    exit;
}

// Nếu có ID môn học, lấy thông tin môn học
if ($idMonHoc) {
    try {
        $stmt = $conn->prepare("
            SELECT mh.*, hk.Ten as TenHocKy, nh.Ten as TenNamHoc, k.Ten as TenKhoa
            FROM monhoc mh
            LEFT JOIN hocky hk ON mh.IDHocKy = hk.ID
            LEFT JOIN namhoc nh ON mh.IDNamHoc = nh.ID
            LEFT JOIN khoa k ON mh.IDKhoa = k.ID
            WHERE mh.ID = ?
        ");
        $stmt->execute([$idMonHoc]);
        
        if ($stmt->rowCount() > 0) {
            $monHoc = $stmt->fetch(PDO::FETCH_ASSOC);
        } else {
            $_SESSION['message'] = "Không tìm thấy môn học!";
            $_SESSION['messageType'] = "warning";
            header('Location: index.php');
            exit;
        }
    } catch (PDOException $e) {
        error_log("Lỗi truy vấn thông tin môn học: " . $e->getMessage());
    }
}

// Lấy danh sách giảng viên
$danhSachGiangVien = [];
try {
    $stmt = $conn->prepare("
        SELECT ID, TenTaiKhoan, HoTen
        FROM taikhoan
        WHERE ChucVu = 'Giảng viên'
        ORDER BY HoTen
    ");
    $stmt->execute();
    $danhSachGiangVien = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Lỗi truy vấn danh sách giảng viên: " . $e->getMessage());
}

// Lấy danh sách phòng học
$danhSachPhongHoc = [];
try {
    $stmt = $conn->prepare("
        SELECT ID, MaPhong, TenPhong, SucChua
        FROM phonghoc
        ORDER BY TenPhong
    ");
    $stmt->execute();
    $danhSachPhongHoc = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Lỗi truy vấn danh sách phòng học: " . $e->getMessage());
}

// Bạn có thể hoàn toàn loại bỏ phần này nếu không cần dùng đến

/*
// Lấy danh sách thời gian học
$danhSachThoiGianHoc = [];
try {
    $stmt = $conn->prepare("
        SELECT ID, ThuHoc, TietBatDau, TietKetThuc
        FROM thoigianhoc
        ORDER BY ThuHoc, TietBatDau
    ");
    $stmt->execute();
    $danhSachThoiGianHoc = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Chuyển đổi số thứ tự ngày thành tên ngày
    $thuTrongTuan = [
        1 => 'Thứ Hai',
        2 => 'Thứ Ba',
        3 => 'Thứ Tư',
        4 => 'Thứ Năm',
        5 => 'Thứ Sáu',
        6 => 'Thứ Bảy',
        7 => 'Chủ Nhật'
    ];
    
    foreach ($danhSachThoiGianHoc as &$thoiGian) {
        $thoiGian['TenThu'] = isset($thuTrongTuan[$thoiGian['ThuHoc']]) ? $thuTrongTuan[$thoiGian['ThuHoc']] : 'Không xác định';
        $thoiGian['MoTa'] = $thoiGian['TenThu'] . ' (Tiết ' . $thoiGian['TietBatDau'] . '-' . $thoiGian['TietKetThuc'] . ')';
    }
} catch (PDOException $e) {
    error_log("Lỗi truy vấn danh sách thời gian học: " . $e->getMessage());
}
*/

// Khởi tạo biến lỗi và dữ liệu
$errors = [];
$formData = [
    'maNhom' => '',
    'tenNhom' => '',
    'soLuongToiDa' => 50,
    'idGiangVien' => '',
    'idPhongHoc' => '',
    'idMonHoc' => $idMonHoc,
    'ngayBatDau' => '', // Thêm ngày bắt đầu
    'ngayKetThuc' => '', // Thêm ngày kết thúc
    'ghiChu' => ''
];

// Xử lý khi form được gửi đi
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Lấy dữ liệu từ form
    $formData = [
        'maNhom' => trim($_POST['maNhom'] ?? ''),
        'tenNhom' => trim($_POST['tenNhom'] ?? ''),
        'soLuongToiDa' => (int)($_POST['soLuongToiDa'] ?? 50),
        'idGiangVien' => $_POST['idGiangVien'] ?? '',
        'idPhongHoc' => $_POST['idPhongHoc'] ?? '',
        'idMonHoc' => $_POST['idMonHoc'] ?? $idMonHoc,
        'ngayBatDau' => $_POST['ngayBatDau'] ?? '', // Thêm ngày bắt đầu
        'ngayKetThuc' => $_POST['ngayKetThuc'] ?? '', // Thêm ngày kết thúc
        'ghiChu' => trim($_POST['ghiChu'] ?? '')
    ];
    
    // Kiểm tra dữ liệu
    if (empty($formData['maNhom'])) {
        $errors[] = "Vui lòng nhập mã nhóm";
    }
    
    if (empty($formData['tenNhom'])) {
        $errors[] = "Vui lòng nhập tên nhóm";
    }
    
    if (empty($formData['idMonHoc'])) {
        $errors[] = "Vui lòng chọn môn học";
    }
    
    if ($formData['soLuongToiDa'] < 1) {
        $errors[] = "Số lượng tối đa phải lớn hơn 0";
    }
    
    // Kiểm tra mã nhóm đã tồn tại chưa
    if (!empty($formData['maNhom'])) {
        try {
            $stmt = $conn->prepare("SELECT COUNT(*) FROM nhommonhoc WHERE MaNhom = ?");
            $stmt->execute([$formData['maNhom']]);
            
            if ($stmt->fetchColumn() > 0) {
                $errors[] = "Mã nhóm đã tồn tại trong hệ thống";
            }
        } catch (PDOException $e) {
            error_log("Lỗi kiểm tra mã nhóm: " . $e->getMessage());
            $errors[] = "Đã xảy ra lỗi khi kiểm tra dữ liệu";
        }
    }
    
    // Kiểm tra logic ngày bắt đầu và kết thúc
    if (!empty($formData['ngayBatDau']) && !empty($formData['ngayKetThuc'])) {
        $startDate = new DateTime($formData['ngayBatDau']);
        $endDate = new DateTime($formData['ngayKetThuc']);
        
        if ($startDate > $endDate) {
            $errors[] = "Ngày bắt đầu không thể sau ngày kết thúc";
        }
    }
    
    // Nếu không có lỗi, tiến hành lưu dữ liệu
    if (empty($errors)) {
        try {
            // Bắt đầu transaction
            $conn->beginTransaction();
            
            // Tạo ID cho nhóm môn học
            $stmtMaxID = $conn->query("SELECT MAX(CAST(SUBSTRING(ID, 7) AS UNSIGNED)) AS max_id FROM nhommonhoc WHERE ID LIKE 'NMH%'");
            $maxID = $stmtMaxID->fetch(PDO::FETCH_ASSOC)['max_id'] ?? 0;
            $newID = 'NMH' . str_pad($maxID + 1, 7, '0', STR_PAD_LEFT);
            
            // Thêm nhóm môn học
            $stmt = $conn->prepare("
                INSERT INTO nhommonhoc (
                    ID, MaNhom, TenNhom, SoLuongToiDa, 
                    IDGiangVien, IDPhongHoc, 
                    IDMonHoc, NgayBatDau, NgayKetThuc, GhiChu
                ) VALUES (
                    ?, ?, ?, ?, 
                    ?, ?, 
                    ?, ?, ?, ?
                )
            ");
            
            $stmt->execute([
                $newID,
                $formData['maNhom'],
                $formData['tenNhom'],
                $formData['soLuongToiDa'],
                $formData['idGiangVien'] ?: null,
                $formData['idPhongHoc'] ?: null,
                $formData['idMonHoc'],
                !empty($formData['ngayBatDau']) ? $formData['ngayBatDau'] : null,
                !empty($formData['ngayKetThuc']) ? $formData['ngayKetThuc'] : null,
                $formData['ghiChu']
            ]);
            
            // Thêm các buổi học nếu có
            if (isset($_POST['buoiHoc']) && is_array($_POST['buoiHoc'])) {
                $stmtBuoiHoc = $conn->prepare("
                    INSERT INTO buoihoc (
                        ID, IDNhomMonHoc, ThuHoc, 
                        TietBatDau, TietKetThuc, 
                        IDPhongHoc, GhiChu
                    ) VALUES (?, ?, ?, ?, ?, ?, ?)
                ");
                
                foreach ($_POST['buoiHoc'] as $index => $buoiHoc) {
                    // Kiểm tra nếu các trường bắt buộc đã được điền
                    if (!empty($buoiHoc['thu']) && !empty($buoiHoc['tietBatDau']) && !empty($buoiHoc['tietKetThuc'])) {
                        // Tạo ID cho buổi học
                        $stmtMaxIDBuoi = $conn->query("SELECT MAX(CAST(SUBSTRING(ID, 3) AS UNSIGNED)) AS max_id FROM buoihoc WHERE ID LIKE 'BH%'");
                        $maxIDBuoi = $stmtMaxIDBuoi->fetch(PDO::FETCH_ASSOC)['max_id'] ?? 0;
                        $newIDBuoi = 'BH' . str_pad($maxIDBuoi + 1, 7, '0', STR_PAD_LEFT);
                        
                        $stmtBuoiHoc->execute([
                            $newIDBuoi,
                            $newID,
                            $buoiHoc['thu'],
                            $buoiHoc['tietBatDau'],
                            $buoiHoc['tietKetThuc'],
                            !empty($buoiHoc['idPhong']) ? $buoiHoc['idPhong'] : null,
                            $buoiHoc['ghiChu'] ?? null
                        ]);
                    }
                }
            }
            
            // Ghi log chỉnh sửa nếu bảng tồn tại
            $tableCheckStmt = $conn->query("SHOW TABLES LIKE 'monhoc_nguoichinhsua'");
            if ($tableCheckStmt->rowCount() > 0 && isset($tk['ID'])) {
                $stmtAddHistory = $conn->prepare("
                    INSERT INTO monhoc_nguoichinhsua (ID, IDMonHoc, IDNguoiThucHien, HanhDong, GhiChu)
                    VALUES (NULL, ?, ?, ?, ?)
                ");
                
                $stmtAddHistory->execute([
                    $formData['idMonHoc'],
                    $tk['ID'],
                    'ADD',
                    "Thêm nhóm môn học: " . $formData['maNhom'] . " - " . $formData['tenNhom']
                ]);
            }
            
            // Hoàn tất transaction
            $conn->commit();
            
            // Đánh dấu thành công
            $success = true;
            
            // Thông báo và chuyển hướng
            $_SESSION['message'] = "Thêm nhóm môn học thành công!";
            $_SESSION['messageType'] = "success";
            
            // Chuyển hướng đến trang index hoặc chi tiết môn học
            if ($idMonHoc) {
                header("Location: ../Subject/details.php?id=" . urlencode($idMonHoc));
            } else {
                header("Location: index.php");
            }
            exit;
            
        } catch (PDOException $e) {
            // Rollback transaction nếu có lỗi
            if ($conn->inTransaction()) {
                $conn->rollBack();
            }
            
            error_log("Lỗi khi thêm nhóm môn học: " . $e->getMessage());
            $errors[] = "Đã xảy ra lỗi khi lưu dữ liệu: " . $e->getMessage();
        }
    }
}

// Bắt đầu output buffer
ob_start();
?>

<div class="container-fluid px-4">
    <!-- Breadcrumb -->
    <div class="row mb-2">
        <div class="col-12">
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb">
                    <li class="breadcrumb-item"><a href="../Dashboard/Index.php">Trang chủ</a></li>
                    <li class="breadcrumb-item"><a href="index.php">Quản lý nhóm môn học</a></li>
                    <li class="breadcrumb-item active" aria-current="page">Thêm nhóm môn học</li>
                </ol>
            </nav>
        </div>
    </div>
    
    <div class="row">
        <div class="col-12">
            <div class="card shadow-sm mb-4">
                <div class="card-header py-3 d-flex flex-row align-items-center justify-content-between">
                    <h6 class="m-0 font-weight-bold text-primary">
                        <i class="fas fa-plus-circle me-1"></i>Thêm nhóm môn học mới
                    </h6>
                </div>
                <div class="card-body">
                    <?php if (!empty($errors)): ?>
                        <div class="alert alert-danger" role="alert">
                            <ul class="mb-0">
                                <?php foreach ($errors as $error): ?>
                                    <li><?php echo $error; ?></li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                    <?php endif; ?>
                    
                    <?php if ($success): ?>
                        <div class="alert alert-success" role="alert">
                            Thêm nhóm môn học thành công!
                        </div>
                    <?php endif; ?>
                    
                    <?php if ($monHoc): ?>
                        <div class="alert alert-info mb-4">
                            <h6 class="mb-2"><i class="fas fa-info-circle me-2"></i>Thông tin môn học</h6>
                            <div class="row">
                                <div class="col-md-4">
                                    <strong>Mã môn học:</strong> <?php echo htmlspecialchars($monHoc['MaMonHoc']); ?>
                                </div>
                                <div class="col-md-8">
                                    <strong>Tên môn học:</strong> <?php echo htmlspecialchars($monHoc['TenMonHoc']); ?>
                                </div>
                                <div class="col-md-4">
                                    <strong>Số tín chỉ:</strong> <?php echo $monHoc['SoTinChi']; ?>
                                </div>
                                <div class="col-md-8">
                                    <strong>Khoa:</strong> <?php echo htmlspecialchars($monHoc['TenKhoa'] ?? 'Chưa xác định'); ?>
                                </div>
                            </div>
                        </div>
                    <?php endif; ?>
                    
                    <form action="" method="post" class="needs-validation" novalidate>
                        <?php if (!$monHoc): ?>
                            <div class="mb-3">
                                <label for="idMonHoc" class="form-label">Môn học <span class="text-danger">*</span></label>
                                <select class="form-select" id="idMonHoc" name="idMonHoc" required>
                                    <option value="">-- Chọn môn học --</option>
                                    <?php
                                    try {
                                        $stmtMonHoc = $conn->prepare("
                                            SELECT ID, MaMonHoc, TenMonHoc
                                            FROM monhoc
                                            ORDER BY TenMonHoc
                                        ");
                                        $stmtMonHoc->execute();
                                        $danhSachMonHoc = $stmtMonHoc->fetchAll(PDO::FETCH_ASSOC);
                                        
                                        foreach ($danhSachMonHoc as $mh) {
                                            $selected = ($formData['idMonHoc'] == $mh['ID']) ? 'selected' : '';
                                            echo '<option value="' . $mh['ID'] . '" ' . $selected . '>' 
                                                . htmlspecialchars($mh['MaMonHoc'] . ' - ' . $mh['TenMonHoc']) 
                                                . '</option>';
                                        }
                                    } catch (PDOException $e) {
                                        error_log("Lỗi truy vấn danh sách môn học: " . $e->getMessage());
                                    }
                                    ?>
                                </select>
                                <div class="invalid-feedback">Vui lòng chọn môn học</div>
                            </div>
                        <?php else: ?>
                            <input type="hidden" name="idMonHoc" value="<?php echo htmlspecialchars($monHoc['ID']); ?>">
                        <?php endif; ?>
                        
                        <!-- Thêm vào đây, sau phần chọn môn học -->
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="ngayBatDau" class="form-label">Ngày bắt đầu môn học</label>
                                    <input type="date" class="form-control" id="ngayBatDau" name="ngayBatDau" 
                                           value="<?php echo htmlspecialchars($formData['ngayBatDau']); ?>">
                                    <div class="form-text">Để trống nếu không có giới hạn thời gian bắt đầu</div>
                                </div>
                            </div>
                            
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="ngayKetThuc" class="form-label">Ngày kết thúc môn học</label>
                                    <input type="date" class="form-control" id="ngayKetThuc" name="ngayKetThuc" 
                                           value="<?php echo htmlspecialchars($formData['ngayKetThuc']); ?>">
                                    <div class="form-text">Để trống nếu không có giới hạn thời gian kết thúc</div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="maNhom" class="form-label">Mã nhóm <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control" id="maNhom" name="maNhom" 
                                           value="<?php echo htmlspecialchars($formData['maNhom']); ?>" 
                                           required maxlength="20">
                                    <div class="invalid-feedback">Vui lòng nhập mã nhóm</div>
                                    <div class="form-text">Mã nhóm không được trùng với mã nhóm đã tồn tại</div>
                                </div>
                            </div>
                            
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="tenNhom" class="form-label">Tên nhóm <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control" id="tenNhom" name="tenNhom" 
                                           value="<?php echo htmlspecialchars($formData['tenNhom']); ?>" 
                                           required maxlength="100">
                                    <div class="invalid-feedback">Vui lòng nhập tên nhóm</div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="soLuongToiDa" class="form-label">Số lượng sinh viên tối đa</label>
                                    <input type="number" class="form-control" id="soLuongToiDa" name="soLuongToiDa" 
                                           value="<?php echo $formData['soLuongToiDa']; ?>" 
                                           min="1" max="200">
                                    <div class="invalid-feedback">Số lượng tối đa phải lớn hơn 0</div>
                                </div>
                            </div>
                            
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="idGiangVien" class="form-label">Giảng viên phụ trách</label>
                                    <select class="form-select" id="idGiangVien" name="idGiangVien">
                                        <option value="">-- Chọn giảng viên --</option>
                                        <?php foreach ($danhSachGiangVien as $giangVien): ?>
                                            <?php $selected = ($formData['idGiangVien'] == $giangVien['ID']) ? 'selected' : ''; ?>
                                            <option value="<?php echo $giangVien['ID']; ?>" <?php echo $selected; ?>>
                                                <?php echo htmlspecialchars($giangVien['HoTen']); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="idPhongHoc" class="form-label">Phòng học</label>
                                    <select class="form-select" id="idPhongHoc" name="idPhongHoc">
                                        <option value="">-- Chọn phòng học --</option>
                                        <?php foreach ($danhSachPhongHoc as $phongHoc): ?>
                                            <?php $selected = ($formData['idPhongHoc'] == $phongHoc['ID']) ? 'selected' : ''; ?>
                                            <option value="<?php echo $phongHoc['ID']; ?>" <?php echo $selected; ?>>
                                                <?php echo htmlspecialchars($phongHoc['MaPhong'] . ' - ' . $phongHoc['TenPhong'] 
                                                    . ' (Sức chứa: ' . $phongHoc['SucChua'] . ')'); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                            
                        </div>
                        
                        
                        <div class="mb-3">
                            <label for="ghiChu" class="form-label">Ghi chú</label>
                            <textarea class="form-control" id="ghiChu" name="ghiChu" rows="3"><?php echo htmlspecialchars($formData['ghiChu']); ?></textarea>
                        </div>
                        
                        <!-- Card: Thêm buổi học -->
                        <div class="card mb-4 mt-4 border border-primary">
                            <div class="card-header bg-primary text-white">
                                <h6 class="mb-0"><i class="fas fa-calendar-alt me-2"></i>Lịch học chi tiết</h6>
                            </div>
                            <div class="card-body">
                                <p class="text-muted small mb-3">
                                    <i class="fas fa-info-circle me-1"></i>
                                    Bạn có thể thêm nhiều buổi học cho nhóm môn học này. Nếu không thêm buổi học, hệ thống sẽ sử dụng thời gian học chính đã chọn bên trên.
                                </p>
                                
                                <div id="buoiHocContainer">
                                    <!-- Các buổi học sẽ được thêm vào đây -->
                                    <div class="buoi-hoc-item card mb-3">
                                        <div class="card-body pb-0">
                                            <div class="d-flex justify-content-between mb-2">
                                                <h6 class="card-title">Buổi học #1</h6>
                                                <button type="button" class="btn btn-sm btn-outline-danger delete-buoi" style="display:none">
                                                    <i class="fas fa-trash-alt"></i>
                                                </button>
                                            </div>
                                            <div class="row">
                                                <div class="col-md-4">
                                                    <div class="mb-3">
                                                        <label class="form-label">Thứ trong tuần</label>
                                                        <select class="form-select buoi-thu" name="buoiHoc[0][thu]">
                                                            <option value="">-- Chọn thứ --</option>
                                                            <option value="1">Thứ Hai</option>
                                                            <option value="2">Thứ Ba</option>
                                                            <option value="3">Thứ Tư</option>
                                                            <option value="4">Thứ Năm</option>
                                                            <option value="5">Thứ Sáu</option>
                                                            <option value="6">Thứ Bảy</option>
                                                            <option value="7">Chủ Nhật</option>
                                                        </select>
                                                    </div>
                                                </div>
                                                <div class="col-md-4">
                                                    <div class="mb-3">
                                                        <label class="form-label">Tiết bắt đầu</label>
                                                        <select class="form-select buoi-tiet-batdau" name="buoiHoc[0][tietBatDau]">
                                                            <option value="">-- Chọn tiết --</option>
                                                            <?php for($i = 1; $i <= 10; $i++): ?>
                                                                <option value="<?php echo $i; ?>">Tiết <?php echo $i; ?></option>
                                                            <?php endfor; ?>
                                                        </select>
                                                    </div>
                                                </div>
                                                <div class="col-md-4">
                                                    <div class="mb-3">
                                                        <label class="form-label">Tiết kết thúc</label>
                                                        <select class="form-select buoi-tiet-ketthuc" name="buoiHoc[0][tietKetThuc]">
                                                            <option value="">-- Chọn tiết --</option>
                                                            <?php for($i = 1; $i <= 10; $i++): ?>
                                                                <option value="<?php echo $i; ?>">Tiết <?php echo $i; ?></option>
                                                            <?php endfor; ?>
                                                        </select>
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="row">
                                                <div class="col-md-6">
                                                    <div class="mb-3">
                                                        <label class="form-label">Phòng học</label>
                                                        <select class="form-select buoi-phong" name="buoiHoc[0][idPhong]">
                                                            <option value="">-- Chọn phòng học --</option>
                                                            <?php foreach ($danhSachPhongHoc as $phongHoc): ?>
                                                                <option value="<?php echo $phongHoc['ID']; ?>">
                                                                    <?php echo htmlspecialchars($phongHoc['MaPhong'] . ' - ' . $phongHoc['TenPhong']); ?>
                                                                </option>
                                                            <?php endforeach; ?>
                                                        </select>
                                                    </div>
                                                </div>
                                                <div class="col-md-6">
                                                    <div class="mb-3">
                                                        <label class="form-label">Ghi chú buổi học</label>
                                                        <input type="text" class="form-control buoi-ghichu" name="buoiHoc[0][ghiChu]" placeholder="Ví dụ: Thực hành, Lý thuyết...">
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="text-center mt-3">
                                    <button type="button" id="themBuoiHoc" class="btn btn-outline-primary">
                                        <i class="fas fa-plus-circle me-2"></i>Thêm buổi học
                                    </button>
                                </div>
                            </div>
                        </div>
                        
                        <div class="mb-0 d-flex justify-content-between">
                            <?php if ($idMonHoc): ?>
                                <a href="../Subject/details.php?id=<?php echo urlencode($idMonHoc); ?>" class="btn btn-secondary">
                                    <i class="fas fa-arrow-left me-2"></i>Quay lại chi tiết môn học
                                </a>
                            <?php else: ?>
                                <a href="index.php" class="btn btn-secondary">
                                    <i class="fas fa-arrow-left me-2"></i>Quay lại danh sách
                                </a>
                            <?php endif; ?>
                            
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-save me-2"></i>Lưu nhóm môn học
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- JavaScript khởi tạo validation -->
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Validation form
    var forms = document.querySelectorAll('.needs-validation');
    
    Array.prototype.slice.call(forms).forEach(function(form) {
        form.addEventListener('submit', function(event) {
            if (!form.checkValidity()) {
                event.preventDefault();
                event.stopPropagation();
            }
            
            form.classList.add('was-validated');
        }, false);
    });
    
    // Kiểm tra mã nhóm
    var maNhomInput = document.getElementById('maNhom');
    if (maNhomInput) {
        maNhomInput.addEventListener('blur', function() {
            var maNhom = this.value.trim();
            if (!maNhom) return;
            
            // Gửi request kiểm tra mã nhóm
            fetch(`check-manhom.php?ma=${encodeURIComponent(maNhom)}`)
                .then(response => response.json())
                .then(data => {
                    var feedback = document.createElement('div');
                    feedback.id = 'maNhom-feedback';
                    
                    // Xóa phản hồi cũ nếu có
                    var oldFeedback = document.getElementById('maNhom-feedback');
                    if (oldFeedback) oldFeedback.remove();
                    
                    if (data.exists) {
                        feedback.className = 'text-danger small mt-1';
                        feedback.innerHTML = '<i class="fas fa-exclamation-circle me-1"></i> Mã nhóm đã tồn tại!';
                        maNhomInput.setCustomValidity('Mã nhóm đã tồn tại!');
                    } else {
                        feedback.className = 'text-success small mt-1';
                        feedback.innerHTML = '<i class="fas fa-check-circle me-1"></i> Mã nhóm hợp lệ';
                        maNhomInput.setCustomValidity('');
                    }
                    
                    maNhomInput.parentNode.appendChild(feedback);
                })
                .catch(error => console.error('Error:', error));
        });
    }
    
    // Quản lý thêm/xóa buổi học
    let buoiCounter = 1;

    document.getElementById('themBuoiHoc').addEventListener('click', function() {
        const container = document.getElementById('buoiHocContainer');
        const template = document.querySelector('.buoi-hoc-item').cloneNode(true);
        
        // Cập nhật tiêu đề và các thuộc tính
        template.querySelector('.card-title').textContent = `Buổi học #${buoiCounter + 1}`;
        
        // Cập nhật name cho các select
        template.querySelector('.buoi-thu').name = `buoiHoc[${buoiCounter}][thu]`;
        template.querySelector('.buoi-tiet-batdau').name = `buoiHoc[${buoiCounter}][tietBatDau]`;
        template.querySelector('.buoi-tiet-ketthuc').name = `buoiHoc[${buoiCounter}][tietKetThuc]`;
        template.querySelector('.buoi-phong').name = `buoiHoc[${buoiCounter}][idPhong]`;
        template.querySelector('.buoi-ghichu').name = `buoiHoc[${buoiCounter}][ghiChu]`;
        
        // Reset giá trị
        template.querySelectorAll('select').forEach(select => select.selectedIndex = 0);
        template.querySelector('input').value = '';
        
        // Hiện nút xóa
        const deleteButton = template.querySelector('.delete-buoi');
        deleteButton.style.display = 'block';
        deleteButton.addEventListener('click', function() {
            template.remove();
            reindexBuoiHoc();
        });
        
        // Thêm vào container
        container.appendChild(template);
        buoiCounter++;
    });

    // Đánh lại chỉ số cho các buổi học
    function reindexBuoiHoc() {
        const items = document.querySelectorAll('.buoi-hoc-item');
        items.forEach((item, index) => {
            item.querySelector('.card-title').textContent = `Buổi học #${index + 1}`;
            
            // Cập nhật chỉ số trong name
            item.querySelector('.buoi-thu').name = `buoiHoc[${index}][thu]`;
            item.querySelector('.buoi-tiet-batdau').name = `buoiHoc[${index}][tietBatDau]`;
            item.querySelector('.buoi-tiet-ketthuc').name = `buoiHoc[${index}][tietKetThuc]`;
            item.querySelector('.buoi-phong').name = `buoiHoc[${index}][idPhong]`;
            item.querySelector('.buoi-ghichu').name = `buoiHoc[${index}][ghiChu]`;
        });
        
        buoiCounter = items.length;
    }

    // Validate tiết bắt đầu và kết thúc
    document.addEventListener('change', function(event) {
        if (event.target.classList.contains('buoi-tiet-batdau') || 
            event.target.classList.contains('buoi-tiet-ketthuc')) {
            
            const parent = event.target.closest('.buoi-hoc-item');
            const tietBatDau = parseInt(parent.querySelector('.buoi-tiet-batdau').value) || 0;
            const tietKetThuc = parseInt(parent.querySelector('.buoi-tiet-ketthuc').value) || 0;
            
            if (tietBatDau > 0 && tietKetThuc > 0 && tietBatDau > tietKetThuc) {
                alert('Tiết bắt đầu không thể lớn hơn tiết kết thúc');
                event.target.selectedIndex = 0;
            }
        }
    });
});

// Validate ngày bắt đầu và kết thúc
document.addEventListener('change', function(event) {
    if (event.target.id === 'ngayBatDau' || event.target.id === 'ngayKetThuc') {
        const ngayBatDau = document.getElementById('ngayBatDau').value;
        const ngayKetThuc = document.getElementById('ngayKetThuc').value;
        
        if (ngayBatDau && ngayKetThuc && new Date(ngayBatDau) > new Date(ngayKetThuc)) {
            alert('Ngày bắt đầu không thể sau ngày kết thúc');
            event.target.value = '';
        }
    }
});
</script>

<?php
// Lấy nội dung buffer và hiển thị với layout
$contentForLayout = ob_get_clean();
include_once('../Shared/_LayoutAdmin.php');
?>
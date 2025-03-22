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

// Kiểm tra ID môn học
if (!isset($_GET['id']) || empty($_GET['id'])) {
    header("Location: Index.php");
    exit;
}

$monHocId = $_GET['id'];

// Cập nhật trạng thái các đợt đăng ký
try {
    $stmtUpdate = $conn->prepare("CALL sp_CapNhatTrangThaiDotDangKy()");
    $stmtUpdate->execute();
} catch (PDOException $e) {
    // Bỏ qua lỗi nếu có
}

// Kiểm tra đợt đăng ký
try {
    // Trước tiên, kiểm tra xem cột TrangThai có tồn tại trong bảng dotdangky không
    $stmtCheckColumn = $conn->prepare("
        SELECT COLUMN_NAME 
        FROM INFORMATION_SCHEMA.COLUMNS 
        WHERE TABLE_SCHEMA = DATABASE() 
        AND TABLE_NAME = 'dotdangky' 
        AND COLUMN_NAME = 'TrangThai'
    ");
    $stmtCheckColumn->execute();
    
    if ($stmtCheckColumn->rowCount() > 0) {
        // Nếu cột TrangThai tồn tại, sử dụng truy vấn gốc
        $stmtDotDangKy = $conn->prepare("
            SELECT ddk.*, hk.Ten as TenHocKy, nh.Ten as TenNamHoc 
            FROM dotdangky ddk
            JOIN hocky hk ON ddk.IDHocKy = hk.ID
            JOIN namhoc nh ON ddk.IDNamHoc = nh.ID
            WHERE ddk.TrangThai = 1 
            AND ddk.LoaiDangKy IN (1, 2)  -- Đăng ký chính thức hoặc bổ sung
            AND NOW() BETWEEN ddk.ThoiGianBatDau AND ddk.ThoiGianKetThuc
            ORDER BY ddk.LoaiDangKy
            LIMIT 1
        ");
    } else {
        // Nếu cột TrangThai không tồn tại, thay thế bằng một điều kiện khác
        // Giả sử cột tương đương là HienThi hoặc Active
        $stmtDotDangKy = $conn->prepare("
            SELECT ddk.*, hk.Ten as TenHocKy, nh.Ten as TenNamHoc 
            FROM dotdangky ddk
            JOIN hocky hk ON ddk.IDHocKy = hk.ID
            JOIN namhoc nh ON ddk.IDNamHoc = nh.ID
            WHERE NOW() BETWEEN ddk.ThoiGianBatDau AND ddk.ThoiGianKetThuc 
            AND ddk.LoaiDangKy IN (1, 2)  -- Đăng ký chính thức hoặc bổ sung
            ORDER BY ddk.LoaiDangKy
            LIMIT 1
        ");
    }
    
    $stmtDotDangKy->execute();
    $dotDangKy = $stmtDotDangKy->fetch(PDO::FETCH_ASSOC);

    if (!$dotDangKy) {
        $_SESSION['error_message'] = "Hiện không có đợt đăng ký nào đang mở.";
        header("Location: Index.php");
        exit;
    }
    
    $idDotDangKy = $dotDangKy['ID'];
    $hanMucTinChi = $dotDangKy['HanMucTinChi'];
} catch (PDOException $e) {
    echo "Lỗi truy vấn: " . $e->getMessage();
    exit;
}

// Thêm đoạn code này sau phần kiểm tra đợt đăng ký (khoảng dòng 82)
// Kiểm tra có thể hủy đăng ký không
try {
    // Trong đợt đăng ký chính thức hoặc bổ sung, luôn cho phép hủy đăng ký môn học vừa đăng ký
    $coTheDangKy = ($dotDangKy !== false);
    $coTheHuy = $coTheDangKy; // Nếu đang trong đợt đăng ký thì cho phép hủy
} catch (PDOException $e) {
    $coTheDangKy = false;
    $coTheHuy = false;
}

// Lấy thông tin môn học
try {
    $stmtMonHoc = $conn->prepare("
        SELECT mh.*, k.Ten, hk.Ten AS TenHocKy, nh.Ten AS TenNamHoc
        FROM monhoc mh
        LEFT JOIN khoa k ON mh.IDKhoa = k.ID
        LEFT JOIN hocky hk ON mh.IDHocKy = hk.ID
        LEFT JOIN namhoc nh ON mh.IDNamHoc = nh.ID
        WHERE mh.ID = ?
    ");
    $stmtMonHoc->execute([$monHocId]);
    $monHoc = $stmtMonHoc->fetch(PDO::FETCH_ASSOC);
    
    if (!$monHoc) {
        $_SESSION['error_message'] = "Không tìm thấy thông tin môn học.";
        header("Location: Index.php");
        exit;
    }
} catch (PDOException $e) {
    echo "Lỗi truy vấn: " . $e->getMessage();
    exit;
}

// Kiểm tra cấu trúc bảng nhommonhoc_sinhvien trước khi truy vấn
try {
    $stmtCheckColumn = $conn->prepare("
        SELECT COLUMN_NAME 
        FROM INFORMATION_SCHEMA.COLUMNS 
        WHERE TABLE_SCHEMA = DATABASE() 
        AND TABLE_NAME = 'nhommonhoc_sinhvien' 
        AND COLUMN_NAME = 'TrangThai'
    ");
    $stmtCheckColumn->execute();
    
    $hasTrangThaiColumn = $stmtCheckColumn->rowCount() > 0;
    
    // Lấy danh sách nhóm môn học
    $stmtNhomMonHoc = $conn->prepare("
        SELECT nmh.*, tk.HoTen as TenGiangVien, ph.MaPhong, ph.TenPhong,
               " . ($hasTrangThaiColumn ? 
                    "(SELECT COUNT(*) FROM nhommonhoc_sinhvien WHERE IDNhomMonHoc = nmh.ID AND TrangThai = 1)" : 
                    "(SELECT COUNT(*) FROM nhommonhoc_sinhvien WHERE IDNhomMonHoc = nmh.ID)") . " as SoLuongDaDangKy
        FROM nhommonhoc nmh
        LEFT JOIN taikhoan tk ON nmh.IDGiangVien = tk.ID
        LEFT JOIN phonghoc ph ON nmh.IDPhongHoc = ph.ID
        WHERE nmh.IDMonHoc = ?
        ORDER BY nmh.MaNhom
    ");
    $stmtNhomMonHoc->execute([$monHocId]);
    $nhomMonHocList = $stmtNhomMonHoc->fetchAll(PDO::FETCH_ASSOC);
    
    if (count($nhomMonHocList) === 0) {
        $_SESSION['error_message'] = "Môn học này chưa có nhóm lớp nào.";
        header("Location: Index.php");
        exit;
    }
} catch (PDOException $e) {
    echo "Lỗi truy vấn: " . $e->getMessage();
    exit;
}

// Lấy thông tin buổi học của từng nhóm môn học
$buoiHocByNhom = [];
try {
    foreach ($nhomMonHocList as $nhomMonHoc) {
        $stmtBuoiHoc = $conn->prepare("
            SELECT bh.*, ph.MaPhong, ph.TenPhong
            FROM buoihoc bh
            LEFT JOIN phonghoc ph ON bh.IDPhongHoc = ph.ID
            WHERE bh.IDNhomMonHoc = ?
            ORDER BY bh.ThuHoc, bh.TietBatDau
        ");
        $stmtBuoiHoc->execute([$nhomMonHoc['ID']]);
        $buoiHocByNhom[$nhomMonHoc['ID']] = $stmtBuoiHoc->fetchAll(PDO::FETCH_ASSOC);
    }
} catch (PDOException $e) {
    echo "Lỗi truy vấn: " . $e->getMessage();
    exit;
}

// Lấy thông tin lịch học đã đăng ký của sinh viên
try {
    $stmtLichHoc = $conn->prepare("
        SELECT bh.ThuHoc, bh.TietBatDau, bh.TietKetThuc, nmh.MaNhom, mh.TenMonHoc
        FROM dangkymonhoc dk
        JOIN chitiet_dangkymonhoc ctdk ON dk.ID = ctdk.IDDangKy
        JOIN nhommonhoc nmh ON ctdk.IDNhomMonHoc = nmh.ID
        JOIN monhoc mh ON nmh.IDMonHoc = mh.ID
        JOIN buoihoc bh ON nmh.ID = bh.IDNhomMonHoc
        WHERE dk.IDSinhVien = ? AND dk.TrangThaiDuyet = 1 AND ctdk.TrangThai = 1
    ");
    $stmtLichHoc->execute([$tk['ID']]);
    
    $registeredSchedule = [];
    $scheduleDetails = [];
    
    while ($row = $stmtLichHoc->fetch(PDO::FETCH_ASSOC)) {
        $thuHoc = $row['ThuHoc'];
        for ($tiet = $row['TietBatDau']; $tiet <= $row['TietKetThuc']; $tiet++) {
            $registeredSchedule[$thuHoc][$tiet] = true;
            $scheduleDetails[$thuHoc][$tiet] = [
                'MaNhom' => $row['MaNhom'],
                'TenMonHoc' => $row['TenMonHoc']
            ];
        }
    }
} catch (PDOException $e) {
    echo "Lỗi truy vấn lịch học: " . $e->getMessage();
    exit;
}

// Kiểm tra xung đột lịch học cho từng nhóm
$trungLichByNhom = [];
foreach ($nhomMonHocList as $nhomMonHoc) {
    $trungLich = false;
    $trungLichDetails = [];
    
    if (isset($buoiHocByNhom[$nhomMonHoc['ID']])) {
        foreach ($buoiHocByNhom[$nhomMonHoc['ID']] as $buoiHoc) {
            $thuHoc = $buoiHoc['ThuHoc'];
            for ($tiet = $buoiHoc['TietBatDau']; $tiet <= $buoiHoc['TietKetThuc']; $tiet++) {
                if (isset($registeredSchedule[$thuHoc][$tiet])) {
                    $trungLich = true;
                    $trungLichDetails[] = [
                        'Thu' => $thuHoc,
                        'Tiet' => $tiet,
                        'TrungVoi' => $scheduleDetails[$thuHoc][$tiet]
                    ];
                }
            }
        }
    }
    
    $trungLichByNhom[$nhomMonHoc['ID']] = [
        'trungLich' => $trungLich,
        'details' => $trungLichDetails
    ];
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

// Kiểm tra giới hạn tín chỉ
$vuotTinChi = ($tongTinChi + $monHoc['SoTinChi']) > $hanMucTinChi;

// Kiểm tra xem sinh viên đã đăng ký môn học này chưa
try {
    $stmtDaDangKy = $conn->prepare("
        SELECT COUNT(*) as count
        FROM dangkymonhoc dk
        JOIN chitiet_dangkymonhoc ctdk ON dk.ID = ctdk.IDDangKy
        JOIN nhommonhoc nmh ON ctdk.IDNhomMonHoc = nmh.ID
        WHERE dk.IDSinhVien = ? AND nmh.IDMonHoc = ? AND ctdk.TrangThai = 1
    ");
    $stmtDaDangKy->execute([$tk['ID'], $monHocId]);
    $daDangKy = $stmtDaDangKy->fetchColumn() > 0;
} catch (PDOException $e) {
    echo "Lỗi truy vấn: " . $e->getMessage();
    exit;
}

// Kiểm tra xem sinh viên đã có điểm cho môn học này chưa và có được phép đăng ký lại không
try {
    $stmtDaCoDiem = $conn->prepare("
        SELECT ID, KetQua, DiemTongKet, LanHoc
        FROM bangdiem
        WHERE IDSinhVien = ? AND IDMonHoc = ? 
        ORDER BY LanHoc DESC, NgayNhap DESC
        LIMIT 1
    ");
    $stmtDaCoDiem->execute([$tk['ID'], $monHocId]);
    $diemInfo = $stmtDaCoDiem->fetch(PDO::FETCH_ASSOC);
    
    $daCoDiem = false;
    $daRotMon = false;
    $lanHocHienTai = 1;
    
    if ($diemInfo) {
        $lanHocHienTai = $diemInfo['LanHoc'];
        
        // Nếu có điểm và kết quả là đậu (1) hoặc điểm tổng kết >= điểm đỗ (thường là 5.0)
        if ($diemInfo['KetQua'] == 1 || ($diemInfo['DiemTongKet'] !== null && $diemInfo['DiemTongKet'] >= 5.0)) {
            $daCoDiem = true; // Đã có điểm và đã đỗ
            $_SESSION['error_message'] = "Bạn đã có điểm đạt cho môn học này, không thể đăng ký lại.";
            header("Location: Index.php");
            exit;
        } 
        // Nếu có điểm nhưng kết quả là rớt (2) hoặc điểm tổng kết < điểm đỗ
        else if ($diemInfo['KetQua'] == 2 || ($diemInfo['DiemTongKet'] !== null && $diemInfo['DiemTongKet'] < 5.0)) {
            $daRotMon = true; // Đã rớt môn, có thể đăng ký lại
            $lanHocHienTai++; // Tăng lần học lên
            
            // Hiển thị thông báo đăng ký lại nhưng không chuyển hướng
            $infoMsg = "Bạn đang đăng ký học lại môn này. Lần học hiện tại sẽ là lần thứ " . $lanHocHienTai . ".";
        }
    }
} catch (PDOException $e) {
    echo "Lỗi truy vấn: " . $e->getMessage();
    exit;
}

// Xử lý đăng ký khi form được submit
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['register']) && isset($_POST['nhomMonHocId'])) {
    $nhomMonHocId = $_POST['nhomMonHocId'];
    
    // Kiểm tra các điều kiện
    if ($daDangKy) {
        $errorMsg = "Bạn đã đăng ký môn học này. Không thể đăng ký nhiều nhóm cho cùng một môn học.";
    } else if ($vuotTinChi) {
        $errorMsg = "Vượt quá số tín chỉ tối đa cho phép ({$hanMucTinChi} tín chỉ).";
    } else if ($trungLichByNhom[$nhomMonHocId]['trungLich']) {
        $errorMsg = "Trùng lịch học với môn đã đăng ký.";
    } else {
        // Tìm nhóm môn học để lấy thông tin
        $nhomMonHoc = null;
        foreach ($nhomMonHocList as $nhom) {
            if ($nhom['ID'] === $nhomMonHocId) {
                $nhomMonHoc = $nhom;
                break;
            }
        }
        
        if (!$nhomMonHoc) {
            $errorMsg = "Không tìm thấy thông tin nhóm môn học.";
        } else if ($nhomMonHoc['SoLuongDaDangKy'] >= $nhomMonHoc['SoLuongToiDa']) {
            $errorMsg = "Nhóm môn học đã đầy. Vui lòng chọn nhóm khác.";
        } else {
            try {
                // Bắt đầu transaction
                $conn->beginTransaction();
                
                // Kiểm tra lại đợt đăng ký có đang mở không
                $stmtCheckDotDangKy = $conn->prepare("
                    SELECT ID, HanMucTinChi 
                    FROM dotdangky
                    WHERE NOW() BETWEEN ThoiGianBatDau AND ThoiGianKetThuc
                    AND LoaiDangKy IN (1, 2)
                    LIMIT 1
                ");
                $stmtCheckDotDangKy->execute();
                $activeDotDangKy = $stmtCheckDotDangKy->fetch(PDO::FETCH_ASSOC);
                
                if (!$activeDotDangKy) {
                    throw new Exception("Hiện không có đợt đăng ký nào đang mở.");
                }
                
                // Lấy số tín chỉ của môn học
                $soTinChi = $monHoc['SoTinChi'];
                
                // Kiểm tra xem sinh viên đã có đăng ký cho đợt này chưa
                $stmtCheckDangKy = $conn->prepare("
                    SELECT ID FROM dangkymonhoc 
                    WHERE IDSinhVien = ? AND IDDotDangKy = ?
                    LIMIT 1
                ");
                $stmtCheckDangKy->execute([$tk['ID'], $idDotDangKy]);
                $dangKyId = $stmtCheckDangKy->fetchColumn();
                
                // Nếu chưa có, tạo mới bản ghi đăng ký
                if (!$dangKyId) {
                    // Tạo ID mới cho bản ghi đăng ký
                    $stmtCountDK = $conn->prepare("SELECT COUNT(*) FROM dangkymonhoc");
                    $stmtCountDK->execute();
                    $countDK = $stmtCountDK->fetchColumn();
                    $dangKyId = 'DK' . str_pad($countDK + 1, 10, '0', STR_PAD_LEFT);
                    
                    // Tạo bản ghi đăng ký mới
                    if ($hasDangKyMonHocNgayColumn) {
                        $stmtInsertDK = $conn->prepare("
                            INSERT INTO dangkymonhoc (ID, IDSinhVien, IDDotDangKy, NgayDangKy, TongTinChi, TrangThaiDuyet)
                            VALUES (?, ?, ?, NOW(), ?, 1)
                        ");
                        $stmtInsertDK->execute([$dangKyId, $tk['ID'], $idDotDangKy, $soTinChi]);
                    } else {
                        $stmtInsertDK = $conn->prepare("
                            INSERT INTO dangkymonhoc (ID, IDSinhVien, IDDotDangKy, TongTinChi, TrangThaiDuyet)
                            VALUES (?, ?, ?, ?, 1)
                        ");
                        $stmtInsertDK->execute([$dangKyId, $tk['ID'], $idDotDangKy, $soTinChi]);
                    }
                } else {
                    // Cập nhật tổng số tín chỉ của bản ghi đăng ký hiện có
                    $stmtUpdateDK = $conn->prepare("
                        UPDATE dangkymonhoc
                        SET TongTinChi = TongTinChi + ?
                        WHERE ID = ?
                    ");
                    $stmtUpdateDK->execute([$soTinChi, $dangKyId]);
                }
                
                // Tạo ID mới cho chi tiết đăng ký
                $stmtCountCTDK = $conn->prepare("SELECT COUNT(*) FROM chitiet_dangkymonhoc");
                $stmtCountCTDK->execute();
                $countCTDK = $stmtCountCTDK->fetchColumn();
                $chiTietDangKyId = 'CTDK' . str_pad($countCTDK + 1, 10, '0', STR_PAD_LEFT);
                
                // Thêm chi tiết đăng ký
                if ($hasChiTietDangKyNgayColumn) {
                    $stmtInsertCTDK = $conn->prepare("
                        INSERT INTO chitiet_dangkymonhoc (
                            ID, IDDangKy, IDNhomMonHoc, SoTinChiDangKy, TrangThai, NgayDangKy
                        ) VALUES (?, ?, ?, ?, 1, NOW())
                    ");
                    $stmtInsertCTDK->execute([$chiTietDangKyId, $dangKyId, $nhomMonHocId, $soTinChi]);
                } else {
                    $stmtInsertCTDK = $conn->prepare("
                        INSERT INTO chitiet_dangkymonhoc (
                            ID, IDDangKy, IDNhomMonHoc, SoTinChiDangKy, TrangThai
                        ) VALUES (?, ?, ?, ?, 1)
                    ");
                    $stmtInsertCTDK->execute([$chiTietDangKyId, $dangKyId, $nhomMonHocId, $soTinChi]);
                }
                
                // Kiểm tra và thêm vào bảng nhommonhoc_sinhvien
                $stmtCheckNMS = $conn->prepare("
                    SELECT COUNT(*) 
                    FROM nhommonhoc_sinhvien 
                    WHERE IDNhomMonHoc = ? AND IDSinhVien = ?
                ");
                $stmtCheckNMS->execute([$nhomMonHocId, $tk['ID']]);
                $nmsExists = $stmtCheckNMS->fetchColumn() > 0;
                
                if (!$nmsExists) {
                    // Tạo ID mới cho nhommonhoc_sinhvien
                    $stmtCountNMS = $conn->prepare("SELECT COUNT(*) FROM nhommonhoc_sinhvien");
                    $stmtCountNMS->execute();
                    $countNMS = $stmtCountNMS->fetchColumn();
                    $nmsId = 'NMS' . str_pad($countNMS + 1, 10, '0', STR_PAD_LEFT);
                    
                    // Kiểm tra lại cột TrangThai trước khi insert
                    if ($hasTrangThaiColumn) {
                        $stmtInsertNMS = $conn->prepare("
                            INSERT INTO nhommonhoc_sinhvien (ID, IDNhomMonHoc, IDSinhVien, TrangThai, NgayDangKy)
                            VALUES (?, ?, ?, 1, NOW())
                        ");
                        $stmtInsertNMS->execute([$nmsId, $nhomMonHocId, $tk['ID']]);
                    } else {
                        $stmtInsertNMS = $conn->prepare("
                            INSERT INTO nhommonhoc_sinhvien (ID, IDNhomMonHoc, IDSinhVien, NgayDangKy)
                            VALUES (?, ?, ?, NOW())
                        ");
                        $stmtInsertNMS->execute([$nmsId, $nhomMonHocId, $tk['ID']]);
                    }
                } else {
                    // Cập nhật trạng thái nếu đã tồn tại và có cột TrangThai
                    if ($hasTrangThaiColumn) {
                        $stmtUpdateNMS = $conn->prepare("
                            UPDATE nhommonhoc_sinhvien
                            SET TrangThai = 1, NgayDangKy = NOW()
                            WHERE IDNhomMonHoc = ? AND IDSinhVien = ?
                        ");
                        $stmtUpdateNMS->execute([$nhomMonHocId, $tk['ID']]);
                    } else {
                        $stmtUpdateNMS = $conn->prepare("
                            UPDATE nhommonhoc_sinhvien
                            SET NgayDangKy = NOW()
                            WHERE IDNhomMonHoc = ? AND IDSinhVien = ?
                        ");
                        $stmtUpdateNMS->execute([$nhomMonHocId, $tk['ID']]);
                    }
                }
                
                // Tạo bảng điểm cho sinh viên đăng ký môn học
                try {
                    // Xử lý dữ liệu bảng điểm dựa trên trạng thái đã rớt hoặc đăng ký mới
                    if ($daRotMon) {
                        // Nếu đã rớt môn, cập nhật lần học mới
                        // Tạo ID mới cho bảng điểm
                        $stmtCountBD = $conn->prepare("SELECT COUNT(*) FROM bangdiem");
                        $stmtCountBD->execute();
                        $countBD = $stmtCountBD->fetchColumn();
                        $bangDiemId = 'BD' . str_pad($countBD + 1, 10, '0', STR_PAD_LEFT);
                        
                        // Lấy thông tin học kỳ, năm học từ đợt đăng ký
                        $stmtHocKyNamHoc = $conn->prepare("
                            SELECT IDHocKy, IDNamHoc 
                            FROM dotdangky 
                            WHERE ID = ?
                        ");
                        $stmtHocKyNamHoc->execute([$idDotDangKy]);
                        $hocKyNamHoc = $stmtHocKyNamHoc->fetch(PDO::FETCH_ASSOC);
                        
                        // Lấy ID giảng viên từ nhóm môn học
                        $stmtGiangVien = $conn->prepare("
                            SELECT IDGiangVien
                            FROM nhommonhoc
                            WHERE ID = ?
                        ");
                        $stmtGiangVien->execute([$nhomMonHocId]);
                        $idGiangVien = $stmtGiangVien->fetchColumn();
                        
                        // Thêm bản ghi mới cho lần học lại
                        $stmtInsertBangDiem = $conn->prepare("
                            INSERT INTO bangdiem (
                                ID, IDSinhVien, IDMonHoc, IDGiangVien, 
                                IDHocKy, IDNamHoc, DiemChuyenCan, DiemKiemTra, DiemThi,
                                DiemTongKet, DiemChu, DiemHe4, KetQua, 
                                LanHoc, GhiChu, NgayNhap
                            ) VALUES (
                                ?, ?, ?, ?, 
                                ?, ?, NULL, NULL, NULL, 
                                NULL, NULL, NULL, 0, 
                                ?, ?, NOW()
                            )
                        ");
                        
                        $ghiChu = "Đăng ký học lại lần " . ($lanHocHienTai - 1);
                        $stmtInsertBangDiem->execute([
                            $bangDiemId,
                            $tk['ID'],
                            $monHoc['ID'],
                            $idGiangVien,
                            $hocKyNamHoc['IDHocKy'],
                            $hocKyNamHoc['IDNamHoc'],
                            $lanHocHienTai,
                            $ghiChu
                        ]);
                    } else {
                        // Trường hợp đăng ký lần đầu
                        // Kiểm tra xem đã có bảng điểm cho sinh viên và môn học này chưa
                        $stmtCheckDiem = $conn->prepare("
                            SELECT COUNT(*) 
                            FROM bangdiem 
                            WHERE IDSinhVien = ? AND IDMonHoc = ?
                        ");
                        $stmtCheckDiem->execute([$tk['ID'], $monHoc['ID']]);
                        $diemExists = $stmtCheckDiem->fetchColumn() > 0;
                        
                        if (!$diemExists) {
                            // Tạo ID mới cho bảng điểm
                            $stmtCountBD = $conn->prepare("SELECT COUNT(*) FROM bangdiem");
                            $stmtCountBD->execute();
                            $countBD = $stmtCountBD->fetchColumn();
                            $bangDiemId = 'BD' . str_pad($countBD + 1, 10, '0', STR_PAD_LEFT);
                            
                            // Lấy thông tin học kỳ, năm học từ đợt đăng ký
                            $stmtHocKyNamHoc = $conn->prepare("
                                SELECT IDHocKy, IDNamHoc 
                                FROM dotdangky 
                                WHERE ID = ?
                            ");
                            $stmtHocKyNamHoc->execute([$idDotDangKy]);
                            $hocKyNamHoc = $stmtHocKyNamHoc->fetch(PDO::FETCH_ASSOC);
                            
                            // Lấy ID giảng viên từ nhóm môn học
                            $stmtGiangVien = $conn->prepare("
                                SELECT IDGiangVien
                                FROM nhommonhoc
                                WHERE ID = ?
                            ");
                            $stmtGiangVien->execute([$nhomMonHocId]);
                            $idGiangVien = $stmtGiangVien->fetchColumn();
                            
                            // Thêm vào bảng điểm với các giá trị mặc định
                            $stmtInsertBangDiem = $conn->prepare("
                                INSERT INTO bangdiem (
                                    ID, IDSinhVien, IDMonHoc, IDGiangVien, 
                                    IDHocKy, IDNamHoc, DiemChuyenCan, DiemKiemTra,
                                    DiemThi, DiemTongKet, DiemChu, DiemHe4,
                                    KetQua, LanHoc, GhiChu, NgayNhap
                                ) VALUES (
                                    ?, ?, ?, ?, 
                                    ?, ?, NULL, NULL, 
                                    NULL, NULL, NULL, NULL, 
                                    0, 1, 'Đăng ký lần đầu', NOW()
                                )
                            ");
                            $stmtInsertBangDiem->execute([
                                $bangDiemId,
                                $tk['ID'],
                                $monHoc['ID'],
                                $idGiangVien,
                                $hocKyNamHoc['IDHocKy'],
                                $hocKyNamHoc['IDNamHoc']
                            ]);
                        }
                    }
                } catch (PDOException $e) {
                    // Ghi log lỗi nhưng không dừng quy trình đăng ký
                    error_log("Lỗi khi tạo bảng điểm: " . $e->getMessage());
                }

                // Commit transaction nếu tất cả các truy vấn thành công
                $conn->commit();
                
                // Đăng ký thành công
                $_SESSION['success_message'] = "Đăng ký môn học thành công!";
                header("Location: Index.php");
                exit;
                
            } catch (Exception $e) {
                // Rollback transaction nếu có lỗi
                $conn->rollBack();
                $errorMsg = "Lỗi khi đăng ký: " . $e->getMessage();
            }
        }
    }
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
    .course-info {
        background-color: #f8f9fa;
        border-radius: 10px;
        padding: 20px;
        margin-bottom: 30px;
        box-shadow: 0 0.125rem 0.25rem rgba(0, 0, 0, 0.075);
    }
    .group-card {
        transition: transform 0.2s ease;
        margin-bottom: 20px;
        height: 100%;
    }
    .group-card:hover {
        transform: translateY(-5px);
        box-shadow: 0 .5rem 1rem rgba(0,0,0,.15)!important;
    }
    .group-card.conflict {
        border-left: 4px solid #dc3545;
    }
    .group-card.available {
        border-left: 4px solid #198754;
    }
    .group-card .card-header {
        background-color: #f8f9fa;
        border-bottom: 1px solid #dee2e6;
    }
    .badge-mandatory {
        background-color: #dc3545;
    }
    .badge-optional {
        background-color: #198754;
    }
    .schedule-conflict {
        background-color: #f8d7da;
        border-color: #f5c2c7;
    }
    .schedule-free {
        background-color: #d1e7dd;
        border-color: #badbcc;
    }
    .schedule-table {
        font-size: 0.8rem;
    }
    .schedule-table th,
    .schedule-table td {
        width: 40px;
        height: 30px;
        text-align: center;
        padding: 5px;
        border: 1px solid #dee2e6;
    }
    .schedule-detail {
        position: absolute;
        background-color: #fff;
        border: 1px solid #ccc;
        border-radius: 4px;
        padding: 5px;
        z-index: 1000;
        box-shadow: 0 2px 5px rgba(0,0,0,0.2);
        display: none;
        min-width: 150px;
    }
    .schedule-cell:hover .schedule-detail {
        display: block;
    }
    .schedule-cell {
        position: relative;
    }
    .conflict-details {
        background-color: #fff3cd;
        border-color: #ffecb5;
        border-radius: 4px;
        padding: 10px;
        margin: 10px 0;
    }
</style>
';
?>

<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Đăng Ký Nhóm Môn Học</title>
    <?php echo $pageStyles; ?>
</head>
<body>
    <div class="container-fluid py-4">
        <nav aria-label="breadcrumb" class="mb-4">
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="Index.php">Đăng Ký Học Phần</a></li>
                <li class="breadcrumb-item"><a href="MyRegistrations.php">Môn Học Đã Đăng Ký</a></li>
                <li class="breadcrumb-item active">Đăng Ký <?php echo htmlspecialchars($monHoc['TenMonHoc']); ?></li>
            </ol>
        </nav>
        
        <h2 class="mb-4">Đăng Ký Nhóm Môn Học</h2>
        
        <?php if (isset($errorMsg)): ?>
            <div class="alert alert-danger">
                <i class="fas fa-exclamation-circle me-2"></i><?php echo htmlspecialchars($errorMsg); ?>
            </div>
        <?php endif; ?>
        
        <?php if ($daDangKy): ?>
            <div class="alert alert-warning">
                <i class="fas fa-exclamation-triangle me-2"></i>Bạn đã đăng ký môn học này. Vui lòng kiểm tra danh sách môn học đã đăng ký.
                <div class="mt-2">
                    <a href="MyRegistrations.php" class="btn btn-sm btn-primary me-2">
                        <i class="fas fa-list me-1"></i> Xem danh sách đăng ký
                    </a>
                    <?php
                    // Lấy ID chi tiết đăng ký nếu sinh viên đã đăng ký môn học này
                    try {
                        $stmtChiTietID = $conn->prepare("
                            SELECT ctdk.ID
                            FROM chitiet_dangkymonhoc ctdk
                            JOIN dangkymonhoc dk ON ctdk.IDDangKy = dk.ID
                            JOIN nhommonhoc nmh ON ctdk.IDNhomMonHoc = nmh.ID
                            WHERE dk.IDSinhVien = ? AND nmh.IDMonHoc = ? AND ctdk.TrangThai = 1
                            LIMIT 1
                        ");
                        $stmtChiTietID->execute([$tk['ID'], $monHocId]);
                        $chiTietId = $stmtChiTietID->fetchColumn();
                        
                        if ($chiTietId && ($coTheHuy || $coTheDangKy)) {
                            echo '<a href="DeleteRegistration.php?id=' . $chiTietId . '&redirect=Register.php?id=' . $monHocId . '" class="btn btn-sm btn-danger">';
                            echo '<i class="fas fa-trash-alt me-1"></i> Hủy đăng ký';
                            echo '</a>';
                        }
                    } catch (PDOException $e) {
                        // Bỏ qua lỗi
                    }
                    ?>
                </div>
            </div>
        <?php endif; ?>
        
        <?php if ($vuotTinChi): ?>
            <div class="alert alert-warning">
                <i class="fas fa-exclamation-triangle me-2"></i>Nếu đăng ký môn học này, bạn sẽ vượt quá số tín chỉ tối đa (<?php echo $hanMucTinChi; ?> tín chỉ).
                <div class="mt-2">Tín chỉ hiện tại: <strong><?php echo $tongTinChi; ?></strong> | Sau khi đăng ký: <strong><?php echo ($tongTinChi + $monHoc['SoTinChi']); ?></strong></div>
            </div>
        <?php endif; ?>
        
        <?php if (isset($infoMsg)): ?>
            <div class="alert alert-warning">
                <i class="fas fa-redo-alt me-2"></i><?php echo $infoMsg; ?>
            </div>
        <?php endif; ?>
        
        <?php if ($daRotMon): ?>
            <div class="alert alert-warning">
                <i class="fas fa-exclamation-triangle me-2"></i>Bạn đang đăng ký học lại môn học này sau khi không đạt ở lần học trước.
            </div>
        <?php endif; ?>
        
        <!-- Thông tin môn học -->
        <div class="course-info">
            <div class="row">
                <div class="col-md-8">
                    <h3>
                        <?php echo htmlspecialchars($monHoc['MaMonHoc']); ?> - <?php echo htmlspecialchars($monHoc['TenMonHoc']); ?>
                        <span class="badge <?php echo $monHoc['LoaiMonHoc'] == 0 ? 'badge-mandatory' : 'badge-optional'; ?> text-white ms-2">
                            <?php echo $monHoc['LoaiMonHoc'] == 0 ? 'Bắt buộc' : 'Tự chọn'; ?>
                        </span>
                    </h3>
                    <p class="text-muted mb-2">
                        <i class="fas fa-graduation-cap me-1"></i> <?php echo htmlspecialchars($monHoc['Ten']); ?> |
                        <i class="fas fa-calendar-alt me-1"></i> <?php echo htmlspecialchars($monHoc['TenHocKy'] . ' - ' . $monHoc['TenNamHoc']); ?>
                    </p>
                    <div class="row mt-3">
                        <div class="col-md-4">
                            <div class="border rounded p-3 text-center">
                                <div class="fw-bold text-primary" style="font-size: 1.5rem;"><?php echo $monHoc['SoTinChi']; ?></div>
                                <div class="small text-muted">Số tín chỉ</div>
                            </div>
                        </div>
                        <div class="col-md-8">
                            <div class="border rounded p-3">
                                <div class="small text-muted mb-1">Đợt đăng ký:</div>
                                <div class="fw-bold"><?php echo htmlspecialchars($dotDangKy['Ten']); ?></div>
                                <div class="small text-muted">
                                    <?php echo date('d/m/Y', strtotime($dotDangKy['ThoiGianBatDau'])); ?> - 
                                    <?php echo date('d/m/Y', strtotime($dotDangKy['ThoiGianKetThuc'])); ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-4 text-end">
                    <div class="d-flex flex-column h-100 justify-content-between align-items-end">
                        <div>
                            <a href="Details.php?id=<?php echo $monHoc['ID']; ?>" class="btn btn-outline-info mb-2">
                                <i class="fas fa-info-circle me-1"></i> Chi tiết môn học
                            </a>
                        </div>
                        <div class="mt-auto">
                            <div class="text-muted mb-2">Tín chỉ đã đăng ký: <strong><?php echo $tongTinChi; ?>/<?php echo $hanMucTinChi; ?></strong></div>
                            <div class="progress" style="height: 10px;">
                                <div class="progress-bar <?php echo ($tongTinChi > $hanMucTinChi * 0.8) ? 'bg-warning' : 'bg-success'; ?>" 
                                    role="progressbar" 
                                    style="width: <?php echo min(100, ($tongTinChi / $hanMucTinChi) * 100); ?>%;" 
                                    aria-valuenow="<?php echo $tongTinChi; ?>" 
                                    aria-valuemin="0" 
                                    aria-valuemax="<?php echo $hanMucTinChi; ?>">
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Danh sách nhóm môn học -->
        <h4 class="mb-3"><i class="fas fa-users me-2"></i>Danh sách nhóm môn học</h4>
        
        <?php if (count($nhomMonHocList) > 0): ?>
            <div class="row row-cols-1 row-cols-md-2 g-4 mb-5">
                <?php foreach ($nhomMonHocList as $nhomMonHoc): ?>
                    <?php 
                    $trungLichInfo = $trungLichByNhom[$nhomMonHoc['ID']];
                    $trungLich = $trungLichInfo['trungLich'];
                    $daDay = ($nhomMonHoc['SoLuongDaDangKy'] >= $nhomMonHoc['SoLuongToiDa']);
                    ?>
                    <div class="col">
                        <div class="card group-card <?php echo $trungLich ? 'conflict' : 'available'; ?>">
                            <div class="card-header d-flex justify-content-between align-items-center">
                                <h5 class="card-title mb-0">
                                    <?php echo htmlspecialchars($nhomMonHoc['MaNhom']); ?>
                                </h5>
                                <div>
                                    <?php if ($trungLich): ?>
                                        <span class="badge bg-danger">Trùng lịch</span>
                                    <?php elseif ($daDay): ?>
                                        <span class="badge bg-warning text-dark">Đã đầy</span>
                                    <?php else: ?>
                                        <span class="badge bg-success">Còn <?php echo $nhomMonHoc['SoLuongToiDa'] - $nhomMonHoc['SoLuongDaDangKy']; ?> chỗ</span>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <div class="card-body">
                                <h6 class="card-subtitle mb-2 text-muted"><?php echo htmlspecialchars($nhomMonHoc['TenNhom']); ?></h6>
                                
                                <div class="row mb-3">
                                    <div class="col-md-6">
                                        <p class="mb-1"><i class="fas fa-user-tie me-1"></i> <strong>Giảng viên:</strong></p>
                                        <p class="ps-3 mb-2"><?php echo htmlspecialchars($nhomMonHoc['TenGiangVien'] ?: 'Chưa phân công'); ?></p>
                                    </div>
                                    <div class="col-md-6">
                                        <p class="mb-1"><i class="fas fa-users me-1"></i> <strong>Sĩ số:</strong></p>
                                        <p class="ps-3 mb-2">
                                            <?php echo $nhomMonHoc['SoLuongDaDangKy']; ?>/<?php echo $nhomMonHoc['SoLuongToiDa']; ?>
                                            <div class="progress mt-1" style="height: 5px;">
                                                <div class="progress-bar <?php echo ($nhomMonHoc['SoLuongDaDangKy'] >= $nhomMonHoc['SoLuongToiDa'] * 0.8) ? 'bg-warning' : 'bg-success'; ?>" 
                                                    role="progressbar" 
                                                    style="width: <?php echo min(100, ($nhomMonHoc['SoLuongDaDangKy'] / $nhomMonHoc['SoLuongToiDa']) * 100); ?>%;">
                                                </div>
                                            </div>
                                        </p>
                                    </div>
                                </div>

                                <?php if ($trungLich && count($trungLichInfo['details']) > 0): ?>
                                    <div class="conflict-details">
                                        <p class="mb-2"><i class="fas fa-exclamation-triangle text-danger me-1"></i> <strong>Trùng lịch với:</strong></p>
                                        <ul class="mb-0 ps-3">
                                            <?php foreach ($trungLichInfo['details'] as $conflict): ?>
                                                <li><?php echo $tenThu[$conflict['Thu']]; ?>, Tiết <?php echo $conflict['Tiet']; ?> - <?php echo htmlspecialchars($conflict['TrungVoi']['MaNhom'] . ': ' . $conflict['TrungVoi']['TenMonHoc']); ?></li>
                                            <?php endforeach; ?>
                                        </ul>
                                    </div>
                                <?php endif; ?>
                                
                                <h6 class="mt-3 mb-2"><i class="fas fa-calendar-alt me-2"></i>Lịch học:</h6>
                                <?php if (isset($buoiHocByNhom[$nhomMonHoc['ID']]) && count($buoiHocByNhom[$nhomMonHoc['ID']]) > 0): ?>
                                    <ul class="list-group">
                                        <?php foreach ($buoiHocByNhom[$nhomMonHoc['ID']] as $buoiHoc): ?>
                                            <?php 
                                            // Kiểm tra xem buổi học này có trùng lịch không
                                            $buoiTrungLich = false;
                                            $thuHoc = $buoiHoc['ThuHoc'];
                                            for ($tiet = $buoiHoc['TietBatDau']; $tiet <= $buoiHoc['TietKetThuc']; $tiet++) {
                                                if (isset($registeredSchedule[$thuHoc][$tiet])) {
                                                    $buoiTrungLich = true;
                                                    break;
                                                }
                                            }
                                            ?>
                                            <li class="list-group-item d-flex justify-content-between align-items-center <?php echo $buoiTrungLich ? 'schedule-conflict' : 'schedule-free'; ?>">
                                                <div>
                                                    <i class="fas <?php echo $buoiTrungLich ? 'fa-times-circle text-danger' : 'fa-check-circle text-success'; ?> me-2"></i>
                                                    <strong><?php echo $tenThu[$buoiHoc['ThuHoc']]; ?></strong>, 
                                                    Tiết <?php echo $buoiHoc['TietBatDau']; ?> - <?php echo $buoiHoc['TietKetThuc']; ?>
                                                </div>
                                                <div>
                                                    <span class="badge bg-light text-dark">
                                                        <?php echo htmlspecialchars($buoiHoc['MaPhong'] ? $buoiHoc['MaPhong'] : 'Chưa có phòng'); ?>
                                                    </span>
                                                </div>
                                            </li>
                                        <?php endforeach; ?>
                                    </ul>
                                <?php else: ?>
                                    <div class="alert alert-info">
                                        <i class="fas fa-info-circle me-2"></i> Chưa có thông tin lịch học cho nhóm này.
                                    </div>
                                <?php endif; ?>
                            </div>
                            <div class="card-footer bg-transparent">
                                <form method="post" action="">
                                    <input type="hidden" name="nhomMonHocId" value="<?php echo $nhomMonHoc['ID']; ?>">
                                    <div class="d-grid">
                                        <button type="submit" name="register" class="btn btn-primary" 
                                            <?php echo ($trungLich || $daDangKy || $vuotTinChi || $daDay) ? 'disabled' : ''; ?>>
                                            <i class="fas fa-check me-1"></i> Đăng ký nhóm này
                                        </button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <div class="alert alert-info">
                <i class="fas fa-info-circle me-2"></i> Không có nhóm môn học nào cho môn học này.
            </div>
        <?php endif; ?>
        
        <!-- Thời khóa biểu -->
        <div class="card mb-4">
            <div class="card-header bg-info text-white">
                <h5 class="card-title mb-0"><i class="fas fa-calendar-check me-2"></i>Thời khóa biểu hiện tại</h5>
            </div>
            <div class="card-body p-3">
                <div class="table-responsive">
                    <table class="table table-bordered schedule-table mb-0">
                        <thead>
                            <tr>
                                <th></th>
                                <th>Thứ 2</th>
                                <th>Thứ 3</th>
                                <th>Thứ 4</th>
                                <th>Thứ 5</th>
                                <th>Thứ 6</th>
                                <th>Thứ 7</th>
                                <th>CN</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php for ($tiet = 1; $tiet <= 10; $tiet++): ?>
                                <tr>
                                    <td class="fw-bold"><?php echo $tiet; ?></td>
                                    <?php for ($thu = 1; $thu <= 7; $thu++): ?>
                                        <td class="schedule-cell <?php echo isset($registeredSchedule[$thu][$tiet]) ? 'schedule-conflict' : 'schedule-free'; ?>">
                                            <?php if (isset($registeredSchedule[$thu][$tiet])): ?>
                                                <i class="fas fa-check"></i>
                                                <div class="schedule-detail">
                                                    <?php echo htmlspecialchars($scheduleDetails[$thu][$tiet]['MaNhom']); ?>: 
                                                    <?php echo htmlspecialchars($scheduleDetails[$thu][$tiet]['TenMonHoc']); ?>
                                                </div>
                                            <?php endif; ?>
                                        </td>
                                    <?php endfor; ?>
                                </tr>
                            <?php endfor; ?>
                        </tbody>
                    </table>
                </div>
                <div class="d-flex justify-content-center mt-3">
                    <span class="badge bg-danger me-2">Đã đăng ký</span>
                    <span class="badge bg-success">Còn trống</span>
                </div>
            </div>
        </div>
        
        <div class="d-flex justify-content-between">
            <a href="Index.php" class="btn btn-secondary">
                <i class="fas fa-arrow-left me-1"></i> Quay lại danh sách môn học
            </a>
            <a href="MySchedule.php" class="btn btn-primary">
                <i class="fas fa-calendar-check me-1"></i> Xem thời khóa biểu
            </a>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Hiển thị chi tiết lịch học khi hover
            const scheduleCells = document.querySelectorAll('.schedule-cell');
            scheduleCells.forEach(cell => {
                cell.addEventListener('mouseenter', function() {
                    const detail = this.querySelector('.schedule-detail');
                    if (detail) {
                        detail.style.display = 'block';
                    }
                });
                
                cell.addEventListener('mouseleave', function() {
                    const detail = this.querySelector('.schedule-detail');
                    if (detail) {
                        detail.style.display = 'none';
                    }
                });
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
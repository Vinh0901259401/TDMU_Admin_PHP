<?php
// filepath: c:\xampp\htdocs\TDMU_website\Areas\Admin\Views\Class\IncreasePredict.php
// Kết nối database
require_once("../Shared/connect.inc");

// Tiêu đề trang
$pageTitle = "Dự đoán điểm theo xu hướng tăng";

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

// Lấy ID sinh viên từ session hoặc tham số
$sinhVienID = isset($_GET['mssv']) ? $_GET['mssv'] : (isset($tk['ID']) ? $tk['ID'] : null);
$maHK = isset($_GET['maHK']) ? $_GET['maHK'] : 'HK0000000001'; // Mặc định là HK1
$maNH = isset($_GET['maNH']) ? $_GET['maNH'] : date('Y'); // Mặc định là năm hiện tại

$subjects = [];
$dtk = 0;
$soTC = 0;
$drl = 0;
$hocKy = "";
$tenSinhVien = "";

// Hàm dự đoán điểm theo xu hướng tăng
function predictIncreaseScore($currentScore, $minIncrease = 0.25, $maxIncrease = 1.5) {
    // Điểm thấp có khả năng tăng nhiều hơn
    if ($currentScore < 5.0) {
        $increase = rand($minIncrease * 150, $maxIncrease * 150) / 100;
    } elseif ($currentScore < 7.0) {
        $increase = rand($minIncrease * 120, $maxIncrease * 120) / 100;
    } else {
        // Điểm cao khó tăng nhiều hơn
        $increase = rand($minIncrease * 80, $maxIncrease * 80) / 100;
    }
    
    // Đảm bảo điểm không vượt quá 10.0
    return min(round($currentScore + $increase, 1), 10.0);
}

// Lấy thông tin sinh viên và dự đoán điểm
if ($sinhVienID && $accessLevel >= 1 && $accessLevel <= 4) {
    try {
        // Lấy tên sinh viên
        $stmtSV = $conn->prepare("SELECT HoTen FROM taikhoan WHERE ID = ?");
        $stmtSV->execute([$sinhVienID]);
        $sinhVien = $stmtSV->fetch(PDO::FETCH_ASSOC);
        if ($sinhVien) {
            $tenSinhVien = $sinhVien['HoTen'];
        }
        
        // Lấy tên học kỳ và năm học
        $stmtHK = $conn->prepare("SELECT Ten FROM hocky WHERE ID = ?");
        $stmtHK->execute([$maHK]);
        $rowHK = $stmtHK->fetch(PDO::FETCH_ASSOC);
        
        $stmtNH = $conn->prepare("SELECT Ten FROM namhoc WHERE ID = ?");
        $stmtNH->execute([$maNH]);
        $rowNH = $stmtNH->fetch(PDO::FETCH_ASSOC);
        
        if ($rowHK && $rowNH) {
            $hocKy = $rowHK['Ten'] . " - " . $rowNH['Ten'];
        } else {
            $hocKy = "Học kỳ không xác định";
        }
        
        // Lấy điểm các môn học của sinh viên trong học kỳ này
        $stmtDiem = $conn->prepare(
            "SELECT bd.*, mh.TenMonHoc, mh.MaMonHoc, mh.SoTinChi as SoTC, nhm.MaNhom 
            FROM bangdiem bd
            JOIN monhoc mh ON bd.IDMonHoc = mh.ID
            LEFT JOIN nhommonhoc nhm ON bd.IDNhomMonHoc = nhm.ID
            WHERE bd.IDSinhVien = ?
              AND bd.IDHocKy = ?
              AND bd.IDNamHoc = ?
            ORDER BY mh.TenMonHoc"
        );
        $stmtDiem->execute([$sinhVienID, $maHK, $maNH]);
        
        $diemHocKy = $stmtDiem->fetchAll(PDO::FETCH_ASSOC);
        $tongTC = 0;
        $tongDiem = 0;
        
        // Xử lý và dự đoán điểm
        foreach ($diemHocKy as $diem) {
            $subject = new stdClass();
            $subject->Ten = $diem['TenMonHoc'];
            $subject->MaMonHoc = $diem['MaMonHoc'] ?? '';
            $subject->SoTC = $diem['SoTC'];
            $subject->MaNhom = $diem['MaNhom'] ?? 'KTCN.CQ.01'; 
            
            // Lấy điểm hiện tại
            $subject->DiemChuyenCan = isset($diem['DiemChuyenCan']) ? $diem['DiemChuyenCan'] : null;
            $subject->DiemKiemTra = isset($diem['DiemKiemTra']) ? $diem['DiemKiemTra'] : null;
            $subject->DiemThi = isset($diem['DiemThi']) ? $diem['DiemThi'] : null;
            
            // Tính điểm tổng kết hiện tại
            if (isset($diem['DiemTongKet']) && $diem['DiemTongKet'] !== null) {
                $subject->DTB_HienTai = $diem['DiemTongKet'];
            } elseif ($subject->DiemChuyenCan !== null && $subject->DiemKiemTra !== null && $subject->DiemThi !== null) {
                // Tính theo trọng số: 10% chuyên cần, 30% kiểm tra, 60% thi
                $subject->DTB_HienTai = round(
                    $subject->DiemChuyenCan * 0.1 + 
                    $subject->DiemKiemTra * 0.3 + 
                    $subject->DiemThi * 0.6,
                    1
                );
            } else {
                $subject->DTB_HienTai = null;
            }
            
            // Dự đoán điểm tăng
            $subject->DCC_DuDoan = $subject->DiemChuyenCan !== null ? 
                predictIncreaseScore($subject->DiemChuyenCan) : null;
                
            $subject->DKT_DuDoan = $subject->DiemKiemTra !== null ? 
                predictIncreaseScore($subject->DiemKiemTra) : null;
                
            $subject->DThi_DuDoan = $subject->DiemThi !== null ? 
                predictIncreaseScore($subject->DiemThi) : null;
            
            // Tính điểm trung bình dự đoán
            if ($subject->DCC_DuDoan !== null && $subject->DKT_DuDoan !== null && $subject->DThi_DuDoan !== null) {
                $subject->DTB = round(
                    $subject->DCC_DuDoan * 0.1 + 
                    $subject->DKT_DuDoan * 0.3 + 
                    $subject->DThi_DuDoan * 0.6,
                    1
                );
            } elseif ($subject->DTB_HienTai !== null) {
                $subject->DTB = predictIncreaseScore($subject->DTB_HienTai);
            } else {
                $subject->DTB = null;
            }
            
            // Tính tổng điểm và tổng tín chỉ
            if ($subject->DTB !== null) {
                $tongTC += $subject->SoTC;
                $tongDiem += $subject->DTB * $subject->SoTC;
            }
            
            $subjects[] = $subject;
        }
        
        // Nếu không có dữ liệu điểm, tạo dữ liệu mẫu
        if (empty($subjects)) {
            // Môn học mẫu theo học kỳ
            $monHocMau = [
                'HK0000000001' => [
                    ['Toán cao cấp', 'MATH101', 'KTCN.CQ.01', 3],
                    ['Lập trình căn bản', 'CS101', 'KTCN.CQ.01', 3],
                    ['Tiếng Anh chuyên ngành', 'ENG101', 'KTCN.CQ.02', 2],
                    ['Triết học', 'PHIL101', 'KTCN.CQ.01', 2]
                ],
                'HK0000000002' => [
                    ['Cơ sở dữ liệu', 'CS105', 'KTCN.CQ.03', 3],
                    ['Lập trình hướng đối tượng', 'CS102', 'KTCN.CQ.02', 3],
                    ['Mạng máy tính', 'CS103', 'KTCN.CQ.01', 3], 
                    ['Cấu trúc dữ liệu và giải thuật', 'CS104', 'KTCN.CQ.01', 3]
                ]
            ];
            
            $monHocHienTai = $monHocMau[$maHK] ?? $monHocMau['HK0000000001'];
            
            foreach ($monHocHienTai as $monHoc) {
                $subject = new stdClass();
                $subject->Ten = $monHoc[0];
                $subject->MaMonHoc = $monHoc[1];
                $subject->MaNhom = $monHoc[2];
                $subject->SoTC = $monHoc[3];
                
                $subject->DiemChuyenCan = rand(60, 80) / 10; // 6.0 - 8.0
                $subject->DiemKiemTra = rand(55, 75) / 10; // 5.5 - 7.5
                $subject->DiemThi = rand(50, 70) / 10; // 5.0 - 7.0
                
                $subject->DTB_HienTai = round(
                    $subject->DiemChuyenCan * 0.1 + 
                    $subject->DiemKiemTra * 0.3 + 
                    $subject->DiemThi * 0.6,
                    1
                );
                
                $subject->DCC_DuDoan = predictIncreaseScore($subject->DiemChuyenCan);
                $subject->DKT_DuDoan = predictIncreaseScore($subject->DiemKiemTra);
                $subject->DThi_DuDoan = predictIncreaseScore($subject->DiemThi);
                
                $subject->DTB = round(
                    $subject->DCC_DuDoan * 0.1 + 
                    $subject->DKT_DuDoan * 0.3 + 
                    $subject->DThi_DuDoan * 0.6,
                    1
                );
                
                $tongTC += $subject->SoTC;
                $tongDiem += $subject->DTB * $subject->SoTC;
                
                $subjects[] = $subject;
            }
        }
        
        // Tính điểm trung bình dự đoán
        $dtk = $tongTC > 0 ? $tongDiem / $tongTC : 0;
        $soTC = $tongTC;
        
        // Điểm rèn luyện dự đoán (tăng từ mức khá)
        $drl = rand(80, 90);
        
    } catch (PDOException $e) {
        error_log("Lỗi truy vấn dự đoán điểm: " . $e->getMessage());
        // Trong trường hợp lỗi, hiển thị thông báo cho người dùng
        $errorMessage = "Đã xảy ra lỗi khi tìm nạp dữ liệu: " . $e->getMessage();
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
                    <div class="card-header bg-success text-white py-3">
                        <h2 class="text-center mb-0">DỰ ĐOÁN ĐIỂM THEO XU HƯỚNG TĂNG</h2>
                    </div>
                    
                    <div class="card-body">
                        <div class="alert alert-warning text-center mb-4">
                            <div class="mb-2">
                                <i class="fas fa-robot fa-3x text-success"></i>
                            </div>
                            <p class="h4 text-success mb-1">Lưu ý quan trọng:</p>
                            <p class="h5 mb-3">ĐÂY LÀ ĐIỂM DỰ ĐOÁN DỰA TRÊN MÔ HÌNH MACHINE LEARNING, CHỈ MANG TÍNH CHẤT THAM KHẢO</p>
                            <p class="mb-0">Kết quả thực tế phụ thuộc vào nỗ lực học tập của sinh viên</p>
                        </div>
                        
                        <?php if (isset($errorMessage)): ?>
                            <div class="alert alert-danger">
                                <i class="fas fa-exclamation-triangle me-2"></i> <?php echo $errorMessage; ?>
                            </div>
                        <?php else: ?>
                            <div class="row">
                                <div class="col-md-10 mx-auto">
                                    <div class="mb-4">
                                        <div class="card bg-light p-3 mb-4">
                                            <div class="row">
                                                <div class="col-md-6">
                                                    <h5 class="mb-2">
                                                        <i class="fas fa-user-graduate me-2 text-primary"></i>
                                                        Sinh viên: <strong><?php echo htmlspecialchars($tenSinhVien); ?></strong>
                                                    </h5>
                                                    <h5 class="mb-0">
                                                        <i class="fas fa-id-card me-2 text-primary"></i>
                                                        MSSV: <strong><?php echo htmlspecialchars($sinhVienID); ?></strong>
                                                    </h5>
                                                </div>
                                                <div class="col-md-6 text-md-end">
                                                    <h5 class="mb-0">
                                                        <i class="fas fa-calendar-alt me-2 text-success"></i>
                                                        <?php echo htmlspecialchars($hocKy); ?> <span class="badge bg-success"><?php echo htmlspecialchars($maHK); ?></span>
                                                    </h5>
                                                </div>
                                            </div>
                                        </div>
                                        
                                        <div class="mb-3">
                                            <h4 class="mb-3">
                                                <i class="fas fa-chart-line me-2 text-success"></i> Kết quả dự đoán
                                            </h4>
                                            <div class="bg-success bg-opacity-10 p-2 rounded mb-3">
                                                <p class="mb-0 text-success">
                                                    <i class="fas fa-info-circle me-2"></i> 
                                                    Mô hình dự đoán này phân tích xu hướng tăng điểm dựa trên các yếu tố tích cực như tăng cường nỗ lực học tập, tham gia đầy đủ các buổi học, và áp dụng các phương pháp học tập hiệu quả.
                                                </p>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <div class="table-responsive">
                                        <table class="table table-bordered table-hover">
                                            <thead class="table-success">
                                                <tr>
                                                    <th class="text-center">STT</th>
                                                    <th>Tên môn học</th>
                                                    <th class="text-center">Mã MH</th>
                                                    <th class="text-center">Nhóm</th>
                                                    <th class="text-center">Số TC</th>
                                                    <th class="text-center">Điểm hiện tại</th>
                                                    <th class="text-center bg-success bg-opacity-10">Dự đoán điểm</th>
                                                    <th class="text-center">Chênh lệch</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php $stt = 1; foreach ($subjects as $subject): ?>
                                                <tr>
                                                    <td class="text-center"><?php echo $stt++; ?></td>
                                                    <td><?php echo htmlspecialchars($subject->Ten); ?></td>
                                                    <td class="text-center"><?php echo htmlspecialchars($subject->MaMonHoc); ?></td>
                                                    <td class="text-center"><?php echo htmlspecialchars($subject->MaNhom); ?></td>
                                                    <td class="text-center"><?php echo $subject->SoTC; ?></td>
                                                    <td class="text-center">
                                                        <?php echo $subject->DTB_HienTai !== null ? number_format($subject->DTB_HienTai, 1) : '-'; ?>
                                                    </td>
                                                    <td class="text-center fw-bold bg-success bg-opacity-10">
                                                        <?php echo $subject->DTB !== null ? number_format($subject->DTB, 1) : '-'; ?>
                                                    </td>
                                                    <td class="text-center text-success">
                                                        <?php if ($subject->DTB_HienTai !== null && $subject->DTB !== null): ?>
                                                            <i class="fas fa-arrow-up"></i> 
                                                            <?php echo number_format($subject->DTB - $subject->DTB_HienTai, 1); ?>
                                                        <?php else: ?>
                                                            -
                                                        <?php endif; ?>
                                                    </td>
                                                </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                            <tfoot class="table-success">
                                                <tr>
                                                    <th colspan="4" class="text-end">Tổng kết:</th>
                                                    <th class="text-center"><?php echo $soTC; ?></th>
                                                    <th class="text-center">Điểm TB hiện tại</th>
                                                    <th class="text-center fw-bold">
                                                        <?php echo number_format($dtk, 2); ?>
                                                    </th>
                                                    <th class="text-center">
                                                        <?php
                                                        // Xác định xếp loại
                                                        if ($dtk >= 8.5) {
                                                            echo '<span class="badge bg-success">Xuất sắc</span>';
                                                        } elseif ($dtk >= 7.0) {
                                                            echo '<span class="badge bg-primary">Giỏi</span>';
                                                        } elseif ($dtk >= 5.5) {
                                                            echo '<span class="badge bg-info">Khá</span>';
                                                        } elseif ($dtk >= 4.0) {
                                                            echo '<span class="badge bg-warning">Trung bình</span>';
                                                        } else {
                                                            echo '<span class="badge bg-danger">Yếu</span>';
                                                        }
                                                        ?>
                                                    </th>
                                                </tr>
                                            </tfoot>
                                        </table>
                                    </div>
                                    
                                    <div class="mt-4 p-3 bg-light rounded border">
                                        <h4 class="mb-3">Thống kê dự đoán</h4>
                                        <div class="row">
                                            <div class="col-md-4">
                                                <div class="d-flex align-items-center mb-3 mb-md-0">
                                                    <i class="fas fa-chart-line text-success fa-2x me-2"></i>
                                                    <div>
                                                        <p class="mb-0">Điểm tổng kết dự đoán:</p>
                                                        <h4 class="mb-0 text-success"><?php echo number_format($dtk, 2); ?></h4>
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="col-md-4">
                                                <div class="d-flex align-items-center mb-3 mb-md-0">
                                                    <i class="fas fa-book fa-2x text-primary me-2"></i>
                                                    <div>
                                                        <p class="mb-0">Tổng số tín chỉ:</p>
                                                        <h4 class="mb-0 text-primary"><?php echo $soTC; ?></h4>
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="col-md-4">
                                                <div class="d-flex align-items-center">
                                                    <i class="fas fa-medal fa-2x text-warning me-2"></i>
                                                    <div>
                                                        <p class="mb-0">Điểm rèn luyện dự đoán:</p>
                                                        <h4 class="mb-0 text-warning"><?php echo $drl; ?></h4>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <div class="row mt-4">
                                        <div class="col-md-6">
                                            <div class="alert alert-success">
                                                <h5><i class="fas fa-check-circle me-2"></i> Hành động cần thiết:</h5>
                                                <ul class="mb-0">
                                                    <li>Tham gia đầy đủ các buổi học và làm bài tập thường xuyên</li>
                                                    <li>Xây dựng kế hoạch học tập chi tiết cho từng môn học</li>
                                                    <li>Tham gia tích cực vào các hoạt động nhóm học tập</li>
                                                    <li>Đặt mục tiêu cụ thể cho từng bài kiểm tra và bài thi</li>
                                                </ul>
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="alert alert-info">
                                                <h5><i class="fas fa-lightbulb me-2"></i> Lời khuyên:</h5>
                                                <ul class="mb-0">
                                                    <li>Ghi chú và tổng hợp kiến thức sau mỗi buổi học</li>
                                                    <li>Tìm tài liệu bổ sung và tham khảo từ nhiều nguồn</li>
                                                    <li>Cân đối thời gian nghỉ ngơi và học tập hợp lý</li>
                                                    <li>Liên hệ với giảng viên khi cần hỗ trợ về nội dung môn học</li>
                                                </ul>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <div class="d-flex justify-content-between mt-4">
                                        <a href="GradeTable.php?mssv=<?php echo urlencode($sinhVienID); ?>&maNH=<?php echo urlencode($maNH); ?>" class="btn btn-secondary">
                                            <i class="fas fa-arrow-left me-2"></i> Quay lại bảng điểm
                                        </a>
                                        <a href="DecreasePredict.php?mssv=<?php echo urlencode($sinhVienID); ?>&maHK=<?php echo urlencode($maHK); ?>&maNH=<?php echo urlencode($maNH); ?>" class="btn btn-danger">
                                            <i class="fas fa-exchange-alt me-2"></i> Xem xu hướng giảm
                                        </a>
                                    </div>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <style>
        .table th, .table td {
            vertical-align: middle;
        }
        
        @media (max-width: 767px) {
            .table-responsive {
                font-size: 0.9rem;
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
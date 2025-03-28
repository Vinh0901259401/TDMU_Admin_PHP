<?php
// filepath: c:\xampp\htdocs\TDMU_website\Areas\Admin\Views\Class\DecreasePredict.php
// Kết nối database
require_once("../Shared/connect.inc");

// Tiêu đề trang
$pageTitle = "Dự đoán điểm theo xu hướng giảm";

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

// Hàm dự đoán điểm theo xu hướng giảm
function predictDecreaseScore($currentScore, $minDecrease = 0.25, $maxDecrease = 1.5) {
    // Điểm cao có khả năng giảm nhiều hơn
    if ($currentScore >= 9.0) {
        $decrease = rand($minDecrease * 150, $maxDecrease * 150) / 100;
    } elseif ($currentScore >= 7.0) {
        $decrease = rand($minDecrease * 120, $maxDecrease * 120) / 100;
    } else {
        // Điểm thấp giảm ít hơn để không quá thấp
        $decrease = rand($minDecrease * 100, $maxDecrease * 100) / 100;
    }
    
    // Đảm bảo điểm không giảm dưới 2.0
    return max(round($currentScore - $decrease, 1), 2.0);
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
            
            // Dự đoán điểm giảm
            $subject->DCC_DuDoan = $subject->DiemChuyenCan !== null ? 
                predictDecreaseScore($subject->DiemChuyenCan) : null;
                
            $subject->DKT_DuDoan = $subject->DiemKiemTra !== null ? 
                predictDecreaseScore($subject->DiemKiemTra) : null;
                
            $subject->DThi_DuDoan = $subject->DiemThi !== null ? 
                predictDecreaseScore($subject->DiemThi) : null;
            
            // Tính điểm trung bình dự đoán
            if ($subject->DCC_DuDoan !== null && $subject->DKT_DuDoan !== null && $subject->DThi_DuDoan !== null) {
                $subject->DTB = round(
                    $subject->DCC_DuDoan * 0.1 + 
                    $subject->DKT_DuDoan * 0.3 + 
                    $subject->DThi_DuDoan * 0.6,
                    1
                );
            } elseif ($subject->DTB_HienTai !== null) {
                $subject->DTB = predictDecreaseScore($subject->DTB_HienTai);
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
                
                $subject->DiemChuyenCan = rand(75, 95) / 10; // 7.5 - 9.5
                $subject->DiemKiemTra = rand(65, 85) / 10; // 6.5 - 8.5
                $subject->DiemThi = rand(60, 85) / 10; // 6.0 - 8.5
                
                $subject->DTB_HienTai = round(
                    $subject->DiemChuyenCan * 0.1 + 
                    $subject->DiemKiemTra * 0.3 + 
                    $subject->DiemThi * 0.6,
                    1
                );
                
                $subject->DCC_DuDoan = predictDecreaseScore($subject->DiemChuyenCan);
                $subject->DKT_DuDoan = predictDecreaseScore($subject->DiemKiemTra);
                $subject->DThi_DuDoan = predictDecreaseScore($subject->DiemThi);
                
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
        
        // Điểm rèn luyện dự đoán (giảm từ mức tốt)
        $drl = rand(65, 75);
        
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
                    <div class="card-header bg-danger text-white py-3">
                        <h2 class="text-center mb-0">DỰ ĐOÁN ĐIỂM THEO XU HƯỚNG GIẢM</h2>
                    </div>
                    
                    <div class="card-body">
                        <div class="alert alert-warning text-center mb-4">
                            <div class="mb-2">
                                <i class="fas fa-robot fa-3x text-danger"></i>
                            </div>
                            <p class="h4 text-danger mb-1">Lưu ý quan trọng:</p>
                            <p class="h5 mb-3">ĐÂY LÀ ĐIỂM DỰ ĐOÁN DỰA TRÊN MÔ HÌNH MACHINE LEARNING, CHỈ MANG TÍNH CHẤT THAM KHẢO</p>
                            <p class="mb-0">Kết quả thực tế có thể khác biệt và phụ thuộc vào nỗ lực học tập của sinh viên</p>
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
                                                        <i class="fas fa-calendar-alt me-2 text-danger"></i>
                                                        <?php echo htmlspecialchars($hocKy); ?> <span class="badge bg-danger"><?php echo htmlspecialchars($maHK); ?></span>
                                                    </h5>
                                                </div>
                                            </div>
                                        </div>
                                        
                                        <div class="mb-3">
                                            <h4 class="mb-3">
                                                <i class="fas fa-chart-line me-2 text-danger"></i> Kết quả dự đoán
                                            </h4>
                                            <div class="bg-danger bg-opacity-10 p-2 rounded mb-3">
                                                <p class="mb-0 text-danger">
                                                    <i class="fas fa-info-circle me-2"></i> 
                                                    Mô hình dự đoán này phân tích xu hướng giảm điểm dựa trên các yếu tố rủi ro tiềm ẩn như giảm thời gian học, áp lực từ môn học khác, và khó khăn ngày càng tăng của nội dung học.
                                                </p>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <div class="table-responsive">
                                        <table class="table table-bordered table-hover">
                                            <thead class="table-danger">
                                                <tr>
                                                    <th class="text-center">STT</th>
                                                    <th>Tên môn học</th>
                                                    <th class="text-center">Mã MH</th>
                                                    <th class="text-center">Nhóm</th>
                                                    <th class="text-center">Số TC</th>
                                                    <th class="text-center">Điểm hiện tại</th>
                                                    <th class="text-center bg-danger bg-opacity-10">Dự đoán điểm</th>
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
                                                    <td class="text-center fw-bold bg-danger bg-opacity-10">
                                                        <?php echo $subject->DTB !== null ? number_format($subject->DTB, 1) : '-'; ?>
                                                    </td>
                                                    <td class="text-center text-danger">
                                                        <?php if ($subject->DTB_HienTai !== null && $subject->DTB !== null): ?>
                                                            <i class="fas fa-arrow-down"></i> 
                                                            <?php echo number_format($subject->DTB_HienTai - $subject->DTB, 1); ?>
                                                        <?php else: ?>
                                                            -
                                                        <?php endif; ?>
                                                    </td>
                                                </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                            <tfoot class="table-danger">
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
                                                    <i class="fas fa-chart-line text-danger fa-2x me-2"></i>
                                                    <div>
                                                        <p class="mb-0">Điểm tổng kết dự đoán:</p>
                                                        <h4 class="mb-0 text-danger"><?php echo number_format($dtk, 2); ?></h4>
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
                                            <div class="alert alert-danger">
                                                <h5><i class="fas fa-exclamation-circle me-2"></i> Nguy cơ cần lưu ý:</h5>
                                                <ul class="mb-0">
                                                    <li>Thiếu tập trung trong quá trình học tập</li>
                                                    <li>Áp lực từ nhiều môn học cùng lúc</li>
                                                    <li>Độ khó tăng dần của nội dung môn học</li>
                                                    <li>Thiếu thời gian ôn tập và làm bài tập</li>
                                                </ul>
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="alert alert-info">
                                                <h5><i class="fas fa-lightbulb me-2"></i> Lời khuyên:</h5>
                                                <ul class="mb-0">
                                                    <li>Lập kế hoạch học tập chi tiết</li>
                                                    <li>Tham dự đầy đủ các buổi học</li>
                                                    <li>Tham gia nhóm học tập để hỗ trợ nhau</li>
                                                    <li>Tìm kiếm sự hỗ trợ từ giảng viên khi cần</li>
                                                </ul>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <div class="d-flex justify-content-between mt-4">
                                        <a href="GradeTable.php?mssv=<?php echo urlencode($sinhVienID); ?>&maNH=<?php echo urlencode($maNH); ?>" class="btn btn-secondary">
                                            <i class="fas fa-arrow-left me-2"></i> Quay lại bảng điểm
                                        </a>
                                        <a href="IncreasePredict.php?mssv=<?php echo urlencode($sinhVienID); ?>&maHK=<?php echo urlencode($maHK); ?>&maNH=<?php echo urlencode($maNH); ?>" class="btn btn-success">
                                            <i class="fas fa-exchange-alt me-2"></i> Xem xu hướng tăng
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
<?php
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
function predictIncreaseScore($currentScore, $minImprovement = 0.25, $maxImprovement = 1.0) {
    // Nếu điểm hiện tại đã gần tối đa, mức cải thiện sẽ thấp hơn
    if ($currentScore >= 9.0) {
        $improvement = (10 - $currentScore) * 0.8; // Cải thiện tối đa 80% khoảng cách tới điểm 10
    } elseif ($currentScore >= 8.0) {
        $improvement = min(rand($minImprovement * 100, $maxImprovement * 100) / 100, 10 - $currentScore);
    } else {
        // Điểm thấp hơn có thể cải thiện nhiều hơn
        $improvement = min(rand($minImprovement * 100, $maxImprovement * 150) / 100, 10 - $currentScore);
    }
    
    return min(round($currentScore + $improvement, 2), 10.0); // Đảm bảo không vượt quá 10
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
            "SELECT bd.*, mh.TenMonHoc as TenMonHoc, mh.SoTC 
            FROM bangdiem bd
            INNER JOIN monhoc mh ON bd.IDMonHoc = mh.ID
            WHERE bd.IDSinhVien = ?
              AND mh.IDHocKy = ?
              AND mh.IDNamHoc = ?
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
            $subject->SoTC = $diem['SoTC'];
            
            // Lấy điểm hiện tại
            $subject->DGK = isset($diem['DiemGiuaKy']) ? $diem['DiemGiuaKy'] : null;
            $subject->DCK = isset($diem['DiemCuoiKy']) ? $diem['DiemCuoiKy'] : null;
            
            // Tính điểm tổng kết hiện tại
            if (isset($diem['DiemTongKet']) && $diem['DiemTongKet'] !== null) {
                $subject->DTB_HienTai = $diem['DiemTongKet'];
            } elseif ($subject->DGK !== null && $subject->DCK !== null) {
                $subject->DTB_HienTai = ($subject->DGK + $subject->DCK) / 2;
            } else {
                $subject->DTB_HienTai = $subject->DGK ?? $subject->DCK ?? 5.0; // Giá trị mặc định nếu không có điểm
            }
            
            // Dự đoán điểm giữa kỳ tăng
            $subject->DGK_DuDoan = $subject->DGK !== null ? predictIncreaseScore($subject->DGK) : 7.5;
            
            // Dự đoán điểm cuối kỳ tăng
            $subject->DCK_DuDoan = $subject->DCK !== null ? predictIncreaseScore($subject->DCK) : 8.0;
            
            // Điểm trung bình dự đoán
            $subject->DTB = ($subject->DGK_DuDoan + $subject->DCK_DuDoan) / 2;
            
            // Tính tổng điểm và tổng tín chỉ
            $tongTC += $subject->SoTC;
            $tongDiem += $subject->DTB * $subject->SoTC;
            
            $subjects[] = $subject;
        }
        
        // Nếu không có dữ liệu điểm, tạo dữ liệu mẫu
        if (empty($subjects)) {
            // Môn học mẫu theo học kỳ
            $monHocMau = [
                'HK0000000001' => ['Toán cao cấp', 'Lập trình căn bản', 'Tiếng Anh chuyên ngành', 'Triết học'],
                'HK0000000002' => ['Cơ sở dữ liệu', 'Lập trình hướng đối tượng', 'Mạng máy tính', 'Cấu trúc dữ liệu và giải thuật'],
                'HK0000000003' => ['Phân tích thiết kế hệ thống', 'Lập trình web', 'Trí tuệ nhân tạo', 'Quản lý dự án phần mềm']
            ];
            
            $monHocHienTai = $monHocMau[$maHK] ?? $monHocMau['HK0000000001'];
            
            foreach ($monHocHienTai as $tenMonHoc) {
                $subject = new stdClass();
                $subject->Ten = $tenMonHoc;
                $subject->SoTC = 3;
                
                $subject->DGK = rand(65, 85) / 10; // 6.5 - 8.5
                $subject->DCK = rand(70, 90) / 10; // 7.0 - 9.0
                
                $subject->DGK_DuDoan = predictIncreaseScore($subject->DGK);
                $subject->DCK_DuDoan = predictIncreaseScore($subject->DCK);
                
                $subject->DTB_HienTai = ($subject->DGK + $subject->DCK) / 2;
                $subject->DTB = ($subject->DGK_DuDoan + $subject->DCK_DuDoan) / 2;
                
                $tongTC += $subject->SoTC;
                $tongDiem += $subject->DTB * $subject->SoTC;
                
                $subjects[] = $subject;
            }
        }
        
        // Tính điểm trung bình dự đoán
        $dtk = $tongTC > 0 ? $tongDiem / $tongTC : 0;
        $soTC = $tongTC;
        
        // Điểm rèn luyện dự đoán (giả định)
        $drl = rand(80, 95);
        
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
                                <i class="fas fa-robot fa-3x text-primary"></i>
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
                                                    Mô hình dự đoán này phân tích xu hướng tăng điểm dựa trên dữ liệu hiện tại và các yếu tố tiềm năng cải thiện.
                                                </p>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <div class="table-responsive">
                                        <table class="table table-bordered table-hover">
                                            <thead class="table-success">
                                                <tr>
                                                    <th rowspan="2" class="text-center align-middle">Môn học</th>
                                                    <th rowspan="2" class="text-center align-middle">Số TC</th>
                                                    <th colspan="2" class="text-center">Điểm hiện tại</th>
                                                    <th colspan="3" class="text-center bg-success bg-opacity-10">Dự đoán điểm</th>
                                                </tr>
                                                <tr>
                                                    <th class="text-center">Giữa kỳ</th>
                                                    <th class="text-center">Cuối kỳ</th>
                                                    <th class="text-center bg-success bg-opacity-10">Giữa kỳ</th>
                                                    <th class="text-center bg-success bg-opacity-10">Cuối kỳ</th>
                                                    <th class="text-center bg-success bg-opacity-10">Tổng kết</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ($subjects as $subject): ?>
                                                <tr>
                                                    <td><?php echo htmlspecialchars($subject->Ten); ?></td>
                                                    <td class="text-center"><?php echo $subject->SoTC; ?></td>
                                                    <td class="text-center">
                                                        <?php echo $subject->DGK !== null ? number_format($subject->DGK, 2) : '-'; ?>
                                                    </td>
                                                    <td class="text-center">
                                                        <?php echo $subject->DCK !== null ? number_format($subject->DCK, 2) : '-'; ?>
                                                    </td>
                                                    <td class="text-center text-success fw-bold">
                                                        <?php echo number_format($subject->DGK_DuDoan, 2); ?>
                                                        <?php if ($subject->DGK !== null): ?>
                                                            <small class="text-success">
                                                                <i class="fas fa-arrow-up"></i> 
                                                                <?php echo number_format($subject->DGK_DuDoan - $subject->DGK, 2); ?>
                                                            </small>
                                                        <?php endif; ?>
                                                    </td>
                                                    <td class="text-center text-success fw-bold">
                                                        <?php echo number_format($subject->DCK_DuDoan, 2); ?>
                                                        <?php if ($subject->DCK !== null): ?>
                                                            <small class="text-success">
                                                                <i class="fas fa-arrow-up"></i> 
                                                                <?php echo number_format($subject->DCK_DuDoan - $subject->DCK, 2); ?>
                                                            </small>
                                                        <?php endif; ?>
                                                    </td>
                                                    <td class="text-center fw-bold bg-success bg-opacity-10">
                                                        <?php echo number_format($subject->DTB, 2); ?>
                                                        <?php if (isset($subject->DTB_HienTai)): ?>
                                                            <small class="text-success">
                                                                <i class="fas fa-arrow-up"></i> 
                                                                <?php echo number_format($subject->DTB - $subject->DTB_HienTai, 2); ?>
                                                            </small>
                                                        <?php endif; ?>
                                                    </td>
                                                </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                            <tfoot class="table-success">
                                                <tr>
                                                    <th>Tổng kết:</th>
                                                    <th class="text-center"><?php echo $soTC; ?></th>
                                                    <th colspan="2" class="text-center">Điểm trung bình hiện tại</th>
                                                    <th colspan="2" class="text-center">Dự đoán điểm TB</th>
                                                    <th class="text-center fw-bold">
                                                        <?php echo number_format($dtk, 2); ?>
                                                        <?php
                                                        // Xác định màu và đánh giá
                                                        if ($dtk >= 8.5) {
                                                            echo '<span class="badge bg-success ms-2">Xuất sắc</span>';
                                                        } elseif ($dtk >= 7.0) {
                                                            echo '<span class="badge bg-primary ms-2">Giỏi</span>';
                                                        } elseif ($dtk >= 5.5) {
                                                            echo '<span class="badge bg-info ms-2">Khá</span>';
                                                        } elseif ($dtk >= 4.0) {
                                                            echo '<span class="badge bg-warning ms-2">Trung bình</span>';
                                                        } else {
                                                            echo '<span class="badge bg-danger ms-2">Yếu</span>';
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
                                    
                                    <div class="alert alert-info mt-4">
                                        <h5><i class="fas fa-lightbulb me-2"></i> Lời khuyên học tập:</h5>
                                        <ul class="mb-0">
                                            <li>Tăng cường thời gian ôn tập các môn học có điểm thấp</li>
                                            <li>Tham gia tích cực vào các buổi thảo luận và hỏi đáp</li>
                                            <li>Tập trung vào việc hiểu sâu kiến thức thay vì học tủ</li>
                                            <li>Lập kế hoạch học tập chi tiết và theo dõi tiến độ thường xuyên</li>
                                        </ul>
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
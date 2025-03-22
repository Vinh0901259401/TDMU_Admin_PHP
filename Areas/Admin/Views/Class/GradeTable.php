<?php
// Kết nối database
require_once("../Shared/connect.inc");

// Tiêu đề trang
$pageTitle = "Bảng điểm";

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

// Sửa phần lấy MSSV để thêm debug và làm sạch giá trị
$mssv = isset($_GET['mssv']) ? trim($_GET['mssv']) : (isset($tk['ID']) ? trim($tk['ID']) : null);

// Thêm debug chi tiết
error_log("MSSV được truyền vào: " . ($mssv ? $mssv : "không có"));
error_log("Session TaiKhoan: " . (isset($_SESSION['TaiKhoan']) ? json_encode($_SESSION['TaiKhoan']) : "không có"));

// Sửa phần lấy thông tin sinh viên
$sinhVien = null;
if ($mssv) {
    try {
        // Hiển thị SQL để debug
        $sql = "SELECT * FROM taikhoan WHERE ID = ?";
        error_log("SQL query: " . $sql . " với tham số: " . $mssv);
        
        $stmt = $conn->prepare($sql);
        $stmt->execute([$mssv]);
        
        // Kiểm tra số lượng bản ghi trả về
        $count = $stmt->rowCount();
        error_log("Số bản ghi tìm thấy: " . $count);
        
        if ($count > 0) {
            $sinhVien = $stmt->fetch(PDO::FETCH_ASSOC);
            error_log("Đã tìm thấy sinh viên: " . $sinhVien['HoTen'] . " (ID: " . $sinhVien['ID'] . ")");
            
            // Debug thêm tất cả thông tin của sinh viên
            error_log("Thông tin sinh viên: " . json_encode($sinhVien));
        } else {
            // Thử một truy vấn khác để kiểm tra dữ liệu
            $stmtTest = $conn->prepare("SELECT COUNT(*) as total FROM taikhoan");
            $stmtTest->execute();
            $result = $stmtTest->fetch(PDO::FETCH_ASSOC);
            error_log("Tổng số tài khoản trong bảng: " . $result['total']);
            
            error_log("Không tìm thấy sinh viên với MSSV: " . $mssv);
        }
    } catch (PDOException $e) {
        error_log("Lỗi truy vấn thông tin sinh viên: " . $e->getMessage());
        error_log("Stack trace: " . $e->getTraceAsString());
    }
}

// Lấy danh sách năm học
$namHocList = [];
try {
    // Lấy từ bảng namhoc
    $stmt = $conn->prepare("SELECT ID, Ten FROM namhoc ORDER BY ID");
    $stmt->execute();
    $namHocList = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Lỗi truy vấn danh sách năm học: " . $e->getMessage());
}

// Mặc định mã năm học nếu không có trong GET
if (!isset($_GET['maNH'])) {
    // Nếu có namHocList, sử dụng năm học đầu tiên
    if (!empty($namHocList)) {
        $maNH = $namHocList[0]['ID'];
    } else {
        $maNH = "NH0000000001"; // Mặc định năm nhất nếu không có dữ liệu
    }
} else {
    $maNH = $_GET['maNH'];
}

// Lấy danh sách học kỳ
$hocKyList = [];
try {
    $stmt = $conn->prepare("SELECT ID, Ten FROM hocky ORDER BY ID");
    $stmt->execute();
    $hocKyList = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Lỗi truy vấn danh sách học kỳ: " . $e->getMessage());
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
<?php elseif (!$sinhVien): ?>
    <div class="container-fluid px-4">
            <div class="row">
                <div class="col-12">
                    <div class="alert alert-warning my-4 p-5 text-center">
                        <div class="mb-3">
                            <i class="fas fa-user-slash fa-5x text-warning"></i>
                        </div>
                        <h2 class="text-warning">KHÔNG TÌM THẤY THÔNG TIN SINH VIÊN!</h2>
                        <h4>Không tìm thấy thông tin sinh viên với mã số: <?php echo htmlspecialchars($mssv ?? ''); ?></h4>
                        <div class="mt-3 mb-3">
                            <p>Có thể do một trong các nguyên nhân sau:</p>
                            <ul class="list-unstyled">
                                <li>• Mã số sinh viên không chính xác</li>
                                <li>• Sinh viên chưa được thêm vào hệ thống</li>
                                <li>• Lỗi kết nối cơ sở dữ liệu</li>
                            </ul>
                        </div>
                        <a href="javascript:history.back()" class="btn btn-primary mt-3">
                            <i class="fas fa-arrow-left me-2"></i> Quay lại
                        </a>
                        <?php if ($accessLevel >= 3): ?>
                            <a href="/TDMU_website/Areas/Admin/Views/Class/StudentList.php" class="btn btn-success mt-3 ms-2">
                                <i class="fas fa-list me-2"></i> Danh sách sinh viên
                            </a>
                        <?php endif; ?>
                    </div>
                    
                    <!-- Add debug info for admin -->
                    <?php if ($accessLevel >= 4): ?>
                    <div class="card mt-3">
                        <div class="card-header bg-secondary text-white">
                            <h6 class="mb-0"><i class="fas fa-bug me-2"></i>Thông tin gỡ lỗi (Chỉ dành cho quản trị viên)</h6>
                        </div>
                        <div class="card-body">
                            <p><strong>MSSV từ request:</strong> <?php echo isset($_GET['mssv']) ? htmlspecialchars($_GET['mssv']) : 'Không có'; ?></p>
                            <p><strong>MSSV sử dụng:</strong> <?php echo $mssv ? htmlspecialchars($mssv) : 'Không có'; ?></p>
                            <p><strong>Session ID user:</strong> <?php echo isset($tk['ID']) ? htmlspecialchars($tk['ID']) : 'Không có'; ?></p>
                            <p><strong>Query:</strong> SELECT * FROM taikhoan WHERE ID = '<?php echo htmlspecialchars($mssv); ?>'</p>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
<?php else: ?>
    <div class="container-fluid px-4">
        <!-- Thông tin sinh viên -->
        <div class="row mt-4">
            <div class="col-md-4">
                <div class="card shadow-sm">
                    <div class="card-header bg-primary text-white py-3 d-flex justify-content-between align-items-center">
                        <h5 class="mb-0"><i class="fas fa-user-graduate me-2"></i> Thông tin sinh viên</h5>
                        <a href="javascript:history.back()" class="btn btn-sm btn-light">
                            <i class="fas fa-arrow-left me-1"></i> Quay lại
                        </a>
                    </div>
                    <div class="card-body d-flex flex-column align-items-center p-4">
                        <?php if (isset($sinhVien['ImagePath']) && !empty($sinhVien['ImagePath'])): ?>
                            <img src="/TDMU_website/Areas/Admin/Assets/img/user-image/<?php echo htmlspecialchars($sinhVien['ImagePath']); ?>" class="mc-user-image mb-3" alt="Ảnh đại diện">
                        <?php else: ?>
                            <img src="/TDMU_website/Areas/Admin/Assets/img/user-image/default-user.png" class="mc-user-image mb-3" alt="Ảnh mặc định">
                        <?php endif; ?>
                        
                        <h4 class="mb-3 text-center fw-bold">
                            <?php echo htmlspecialchars($sinhVien['HoTen']); ?>
                        </h4>
                        
                        <div class="w-100">
                            <p class="d-flex justify-content-between mb-2">
                                <span><i class="fas fa-id-card me-2 text-primary"></i> MSSV:</span>
                                <span class="fw-bold"><?php echo htmlspecialchars($sinhVien['ID']); ?></span>
                            </p>
                            <p class="d-flex justify-content-between mb-2">
                                <span><i class="fas fa-venus-mars me-2 text-primary"></i> Giới tính:</span>
                                <span>
                                    <?php 
                                    if (!empty($sinhVien['GioiTinh'])) {
                                        if ($sinhVien['GioiTinh'] == 'Nam') {
                                            echo '<span class="badge bg-primary">Nam</span>';
                                        } elseif ($sinhVien['GioiTinh'] == 'Nữ') {
                                            echo '<span class="badge bg-danger">Nữ</span>';
                                        } else {
                                            echo htmlspecialchars($sinhVien['GioiTinh']);
                                        }
                                    } else {
                                        echo 'Chưa cập nhật';
                                    }
                                    ?>
                                </span>
                            </p>
                            <p class="d-flex justify-content-between mb-2">
                                <span><i class="fas fa-birthday-cake me-2 text-primary"></i> Ngày sinh:</span>
                                <span>
                                    <?php 
                                    if (!empty($sinhVien['NgaySinh'])) {
                                        $ngaySinh = new DateTime($sinhVien['NgaySinh']);
                                        echo htmlspecialchars($ngaySinh->format('d/m/Y'));
                                    } else {
                                        echo 'Chưa cập nhật';
                                    }
                                    ?>
                                </span>
                            </p>
                            <?php if (!empty($sinhVien['Email'])): ?>
                            <p class="d-flex justify-content-between mb-2">
                                <span><i class="fas fa-envelope me-2 text-primary"></i> Email:</span>
                                <span><?php echo htmlspecialchars($sinhVien['Email']); ?></span>
                            </p>
                            <?php endif; ?>
                            <?php if (!empty($sinhVien['SDT'])): ?>
                            <p class="d-flex justify-content-between mb-2">
                                <span><i class="fas fa-phone me-2 text-primary"></i> SĐT:</span>
                                <span><?php echo htmlspecialchars($sinhVien['SDT']); ?></span>
                            </p>
                            <?php endif; ?>
                            <?php if (!empty($sinhVien['CCCD'])): ?>
                            <p class="d-flex justify-content-between mb-2">
                                <span><i class="fas fa-id-badge me-2 text-primary"></i> CCCD:</span>
                                <span><?php echo htmlspecialchars($sinhVien['CCCD']); ?></span>
                            </p>
                            <?php endif; ?>
                            <?php if (!empty($sinhVien['DiaChi'])): ?>
                            <p class="d-flex justify-content-between mb-2">
                                <span><i class="fas fa-map-marker-alt me-2 text-primary"></i> Địa chỉ:</span>
                                <span><?php echo htmlspecialchars($sinhVien['DiaChi']); ?></span>
                            </p>
                            <?php endif; ?>
                            <?php if (!empty($sinhVien['IDLop'])): ?>
                            <p class="d-flex justify-content-between mb-2">
                                <span><i class="fas fa-users me-2 text-primary"></i> Lớp:</span>
                                <span class="badge bg-info"><?php echo htmlspecialchars($sinhVien['IDLop']); ?></span>
                            </p>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="col-md-8">
                <div class="card shadow-sm h-100">
                    <div class="card-header bg-primary text-white py-3">
                        <h5 class="mb-0">Bảng điểm sinh viên</h5>
                    </div>
                    <div class="card-body">
                        <!-- Chọn năm học - sử dụng dữ liệu từ bảng namhoc -->
                        <div class="mb-4">
                            <label for="dropdownMaNH" class="form-label fw-bold">Chọn năm học:</label>
                            <div class="input-group">
                                <span class="input-group-text bg-primary text-white">
                                    <i class="fas fa-calendar-alt"></i>
                                </span>
                                <select id="dropdownMaNH" class="form-select">
                                    <?php foreach($namHocList as $namHoc): ?>
                                        <option value="<?php echo htmlspecialchars($namHoc['ID']); ?>" 
                                                <?php echo ($namHoc['ID'] == $maNH) ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($namHoc['Ten']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                    <?php if(empty($namHocList)): ?>
                                        <option value="NH0000000001">Năm nhất</option>
                                    <?php endif; ?>
                                </select>
                            </div>
                        </div>
                        
                        <div class="alert alert-info">
                            <i class="fas fa-info-circle me-2"></i>
                            Chọn năm học để xem điểm các học kỳ
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <?php 
        // Lấy tên năm học hiện tại
        $namHocTen = "Năm nhất";
        foreach ($namHocList as $nh) {
            if ($nh['ID'] == $maNH) {
                $namHocTen = $nh['Ten'];
                break;
            }
        }
        
        // Xử lý cho từng học kỳ
        foreach ($hocKyList as $index => $hocKy):
            $maHocKy = $hocKy['ID'];
            $tenHocKy = $hocKy['Ten'];
        ?>
        <!-- Học kỳ <?php echo $index + 1; ?> -->
        <div class="row mt-4 <?php echo ($index == count($hocKyList) - 1) ? 'mb-4' : ''; ?>">
            <div class="col-12">
                <div class="card shadow-sm">
                    <div class="card-header bg-primary text-white py-3 d-flex justify-content-between align-items-center">
                        <h5 class="mb-0">
                            <i class="fas fa-book me-2"></i>
                            <?php echo htmlspecialchars($tenHocKy); ?> - <?php echo htmlspecialchars($namHocTen); ?>
                        </h5>
                        <span class="badge bg-light text-primary">HK<?php echo $index + 1; ?></span>
                    </div>
                    <div class="card-body">
                        <?php
                        // Truy vấn điểm học kỳ theo IDSinhVien, IDMonHoc liên kết với IDHocKy và IDNamHoc
                        $diemHocKy = [];
                        try {
                            // Thêm debug cho câu truy vấn
                            error_log("Bắt đầu truy vấn điểm cho sinh viên: " . $mssv . ", học kỳ: " . $maHocKy . ", năm học: " . $maNH);
                            
                            // Sửa câu truy vấn SQL - thêm check cho NULL và thêm debug
                            $sqlDiem = "
                                SELECT bd.*, mh.Ten as TenMonHoc, mh.SoTC
                                FROM bangdiem bd
                                INNER JOIN monhoc mh ON bd.IDMonHoc = mh.ID
                                WHERE bd.IDSinhVien = ?
                                  AND mh.IDHocKy = ?
                                  AND mh.IDNamHoc = ?
                                ORDER BY mh.Ten
                            ";
                            error_log("SQL Điểm: " . $sqlDiem);
                            error_log("Tham số: MSSV=" . $mssv . ", IDHocKy=" . $maHocKy . ", IDNamHoc=" . $maNH);
                            
                            $stmtDiem = $conn->prepare($sqlDiem);
                            $stmtDiem->execute([$mssv, $maHocKy, $maNH]);
                            
                            // Kiểm tra số lượng bản ghi trả về
                            $countDiem = $stmtDiem->rowCount();
                            error_log("Số bản ghi điểm tìm thấy: " . $countDiem);
                            
                            $diemHocKy = $stmtDiem->fetchAll(PDO::FETCH_ASSOC);
                            
                            // Debug dữ liệu điểm tìm được
                            if (!empty($diemHocKy)) {
                                error_log("Tìm thấy " . count($diemHocKy) . " môn học có điểm");
                                // Debug chi tiết môn đầu tiên
                                if (isset($diemHocKy[0])) {
                                    error_log("Môn đầu tiên: " . json_encode($diemHocKy[0]));
                                }
                            } else {
                                // Truy vấn để kiểm tra có môn học nào trong học kỳ này không
                                $sqlMonHoc = "
                                    SELECT COUNT(*) as total
                                    FROM monhoc
                                    WHERE IDHocKy = ? AND IDNamHoc = ?
                                ";
                                $stmtMonHoc = $conn->prepare($sqlMonHoc);
                                $stmtMonHoc->execute([$maHocKy, $maNH]);
                                $totalMonHoc = $stmtMonHoc->fetch(PDO::FETCH_ASSOC)['total'];
                                
                                error_log("Không tìm thấy điểm nào. Tổng số môn học trong học kỳ này: " . $totalMonHoc);
                                
                                // Kiểm tra xem có bản ghi nào trong bảng điểm cho sinh viên này không
                                $sqlCheckDiem = "
                                    SELECT COUNT(*) as total 
                                    FROM bangdiem 
                                    WHERE IDSinhVien = ?
                                ";
                                $stmtCheckDiem = $conn->prepare($sqlCheckDiem);
                                $stmtCheckDiem->execute([$mssv]);
                                $totalDiemSV = $stmtCheckDiem->fetch(PDO::FETCH_ASSOC)['total'];
                                
                                error_log("Tổng số bản ghi điểm của sinh viên này: " . $totalDiemSV);
                            }
                        } catch (PDOException $e) {
                            error_log("Lỗi truy vấn điểm học kỳ " . $maHocKy . ": " . $e->getMessage());
                            error_log("Stack trace: " . $e->getTraceAsString());
                        }
                        
                        if (!empty($diemHocKy)):
                        ?>
                        <div class="table-responsive">
                            <table class="table table-hover table-bordered">
                                <thead class="table-light">
                                    <tr>
                                        <th width="5%" class="text-center">STT</th>
                                        <th width="45%">Tên môn học</th>
                                        <th width="10%" class="text-center">Số TC</th>
                                        <th width="10%" class="text-center">Giữa kỳ</th>
                                        <th width="10%" class="text-center">Cuối kỳ</th>
                                        <th width="10%" class="text-center">Tổng kết</th>
                                        <th width="10%" class="text-center">Đánh giá</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php 
                                    $stt = 1;
                                    $tongTC = 0;
                                    $tongDiem = 0;
                                    
                                    foreach ($diemHocKy as $diem):
                                        // Tính điểm trung bình
                                        if (isset($diem['DiemTongKet']) && isset($diem['SoTC']) && $diem['SoTC'] > 0) {
                                            $tongTC += $diem['SoTC'];
                                            $tongDiem += $diem['DiemTongKet'] * $diem['SoTC'];
                                        }
                                    ?>
                                        <!-- Phần trong vòng lặp hiển thị từng môn học -->
                                        <tr>
                                            <td class="text-center"><?php echo $stt++; ?></td>
                                            <td><?php echo htmlspecialchars($diem['TenMonHoc']); ?></td>
                                            <td class="text-center"><?php echo $diem['SoTC']; ?></td>
                                            <td class="text-center"><?php echo isset($diem['DiemGiuaKy']) && $diem['DiemGiuaKy'] !== null ? number_format($diem['DiemGiuaKy'], 1) : '-'; ?></td>
                                            <td class="text-center"><?php echo isset($diem['DiemCuoiKy']) && $diem['DiemCuoiKy'] !== null ? number_format($diem['DiemCuoiKy'], 1) : '-'; ?></td>
                                            <td class="text-center fw-bold">
                                                <?php
                                                // Tính điểm tổng kết nếu chưa có
                                                $diemTongKet = null;
                                                if (isset($diem['DiemTongKet']) && $diem['DiemTongKet'] !== null) {
                                                    $diemTongKet = $diem['DiemTongKet'];
                                                } elseif (isset($diem['DiemGiuaKy']) && $diem['DiemGiuaKy'] !== null && 
                                                         isset($diem['DiemCuoiKy']) && $diem['DiemCuoiKy'] !== null) {
                                                    // Tính điểm tổng kết là trung bình của điểm giữa kỳ và cuối kỳ
                                                    $diemTongKet = ($diem['DiemGiuaKy'] + $diem['DiemCuoiKy']) / 2;
                                                }
                                                
                                                // Hiển thị điểm tổng kết
                                                if ($diemTongKet !== null) {
                                                    echo number_format($diemTongKet, 1);
                                                    
                                                    // Cập nhật biến để tính điểm trung bình học kỳ
                                                    if (isset($diem['SoTC']) && $diem['SoTC'] > 0) {
                                                        $tongTC += $diem['SoTC'];
                                                        $tongDiem += $diemTongKet * $diem['SoTC'];
                                                    }
                                                } else {
                                                    echo '-';
                                                }
                                                ?>
                                            </td>
                                            <td class="text-center">
                                                <?php
                                                if ($diemTongKet !== null) {
                                                    if ($diemTongKet >= 8.5) {
                                                        echo '<span class="badge bg-success">Xuất sắc</span>';
                                                    } elseif ($diemTongKet >= 7.0) {
                                                        echo '<span class="badge bg-primary">Giỏi</span>';
                                                    } elseif ($diemTongKet >= 5.5) {
                                                        echo '<span class="badge bg-info">Khá</span>';
                                                    } elseif ($diemTongKet >= 4.0) {
                                                        echo '<span class="badge bg-warning">Trung bình</span>';
                                                    } else {
                                                        echo '<span class="badge bg-danger">Không đạt</span>';
                                                    }
                                                } else {
                                                    echo '-';
                                                }
                                                ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                                <tfoot class="table-light">
                                    <tr>
                                        <td colspan="2" class="fw-bold">Điểm trung bình học kỳ</td>
                                        <td class="text-center fw-bold"><?php echo $tongTC; ?></td>
                                        <td colspan="2"></td>
                                        <td class="text-center fw-bold"><?php echo $tongTC > 0 ? number_format($tongDiem / $tongTC, 2) : '-'; ?></td>
                                        <td class="text-center">
                                            <?php
                                            $diemTB = $tongTC > 0 ? $tongDiem / $tongTC : 0;
                                            if ($diemTB >= 8.5) {
                                                echo '<span class="badge bg-success">Xuất sắc</span>';
                                            } elseif ($diemTB >= 7.0) {
                                                echo '<span class="badge bg-primary">Giỏi</span>';
                                            } elseif ($diemTB >= 5.5) {
                                                echo '<span class="badge bg-info">Khá</span>';
                                            } elseif ($diemTB >= 4.0) {
                                                echo '<span class="badge bg-warning">Trung bình</span>';
                                            } else {
                                                echo '<span class="badge bg-danger">Không đạt</span>';
                                            }
                                            ?>
                                        </td>
                                    </tr>
                                </tfoot>
                            </table>
                        </div>
                        <?php else: ?>
                            <div class="alert alert-info">
                                <i class="fas fa-info-circle me-2"></i> Chưa có dữ liệu điểm cho học kỳ này
                            </div>
                        <?php endif; ?>
                        
                        <div class="mt-4">
                            <h5 class="mb-3"><i class="fas fa-chart-line me-2"></i> Dự đoán theo xu hướng:</h5>
                            <div class="d-flex gap-3">
                                <a href="IncreasePredict.php?mssv=<?php echo urlencode($mssv); ?>&maHK=<?php echo urlencode($maHocKy); ?>&maNH=<?php echo urlencode($maNH); ?>" class="btn btn-success">
                                    <i class="fas fa-arrow-up me-2"></i> Tăng
                                </a>
                                <a href="DecreasePredict.php?mssv=<?php echo urlencode($mssv); ?>&maHK=<?php echo urlencode($maHocKy); ?>&maNH=<?php echo urlencode($maNH); ?>" class="btn btn-danger">
                                    <i class="fas fa-arrow-down me-2"></i> Giảm
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
    
    <style>
        .mc-user-image {
            width: 150px;
            height: 150px;
            border-radius: 50%;
            object-fit: cover;
            border: 4px solid #eee;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        
        .btn:hover {
            opacity: 0.9;
            transform: translateY(-1px);
            transition: all 0.2s;
        }
        
        .card {
            border-radius: 0.5rem;
            border: none;
            box-shadow: 0 0.125rem 0.25rem rgba(0, 0, 0, 0.075);
        }
        
        .card-header {
            border-top-left-radius: 0.5rem !important;
            border-top-right-radius: 0.5rem !important;
        }
        
        .table {
            vertical-align: middle;
        }
    </style>
    
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        // Xử lý sự kiện khi người dùng chọn năm học
        document.getElementById('dropdownMaNH').addEventListener('change', function() {
            // Lấy giá trị năm học đã chọn
            var selectedMaNH = this.value;
            
            // Chuyển hướng tới trang với năm học đã chọn
            window.location.href = 'GradeTable.php?mssv=<?php echo urlencode($mssv); ?>&maNH=' + selectedMaNH;
        });
    });
    </script>
<?php endif; ?>

<?php
// Lấy nội dung đã buffer và đưa vào layout
$contentForLayout = ob_get_clean();

// Kết nối với layout
include_once('../Shared/_LayoutAdmin.php');
?>
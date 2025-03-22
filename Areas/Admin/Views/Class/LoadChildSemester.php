<?php
/**
 * Tệp component hiển thị điểm học kỳ
 * 
 * Tệp này nhận các biến sau từ trang cha:
 * $semesterSubjects - Mảng các đối tượng môn học từ bảng bangdiem
 * $semesterDTK - Điểm tổng kết học kỳ (điểm trung bình học kỳ)
 * $semesterSoTC - Tổng số tín chỉ học kỳ
 * $semesterDiemChu - Điểm chữ học kỳ
 * $semesterName - Tên học kỳ
 */

// Function để lấy xếp loại dựa trên điểm hệ 4
function getXepLoai($diemHe4) {
    if ($diemHe4 >= 3.6) return '<span class="badge bg-danger">Xuất sắc</span>';
    if ($diemHe4 >= 3.2) return '<span class="badge bg-primary">Giỏi</span>';
    if ($diemHe4 >= 2.5) return '<span class="badge bg-info">Khá</span>';
    if ($diemHe4 >= 2.0) return '<span class="badge bg-success">Trung bình</span>';
    if ($diemHe4 >= 1.0) return '<span class="badge bg-warning text-dark">Trung bình yếu</span>';
    return '<span class="badge bg-secondary">Kém</span>';
}

// Nếu không có dữ liệu hoặc chưa được truyền vào, hiển thị thông báo
if (!isset($semesterSubjects) || empty($semesterSubjects)) {
    echo '<div class="alert alert-info mb-4">';
    echo '<i class="fas fa-info-circle me-2"></i>';
    echo 'Chưa có dữ liệu điểm cho học kỳ này';
    echo '</div>';
    return; // Dừng việc thực thi code khi không có dữ liệu
}
?>

<div class="semester-data">
    <?php if (isset($semesterName)): ?>
    <div class="d-flex align-items-center mb-3">
        <i class="fas fa-graduation-cap text-primary me-2"></i>
        <h5 class="mb-0">Học kỳ: <span class="badge bg-primary"><?php echo htmlspecialchars($semesterName); ?></span></h5>
    </div>
    <?php endif; ?>
    
    <div class="table-responsive">
        <table class="table table-bordered table-hover">
            <thead class="table-light">
                <tr>
                    <th width="5%" class="text-center">STT</th>
                    <th width="35%">Tên môn học</th>
                    <th width="7%" class="text-center">Số TC</th>
                    <th width="8%" class="text-center">Chuyên cần</th>
                    <th width="8%" class="text-center">Kiểm tra</th>
                    <th width="8%" class="text-center">Thi</th>
                    <th width="8%" class="text-center">Tổng kết</th>
                    <th width="7%" class="text-center">Điểm chữ</th>
                    <th width="7%" class="text-center">Hệ 4</th>
                    <th width="7%" class="text-center">Kết quả</th>
                </tr>
            </thead>
            <tbody>
                <?php 
                $stt = 1; 
                $tongTC = 0;
                $tongDiem = 0;
                ?>
                <?php foreach ($semesterSubjects as $subject): ?>
                    <tr>
                        <td class="text-center"><?php echo $stt++; ?></td>
                        <td><?php echo htmlspecialchars($subject->TenMonHoc); ?></td>
                        <td class="text-center"><?php echo $subject->SoTC; ?></td>
                        <td class="text-center">
                            <?php echo isset($subject->DiemChuyenCan) && $subject->DiemChuyenCan !== null 
                                ? number_format($subject->DiemChuyenCan, 1) : '-'; ?>
                        </td>
                        <td class="text-center">
                            <?php echo isset($subject->DiemKiemTra) && $subject->DiemKiemTra !== null 
                                ? number_format($subject->DiemKiemTra, 1) : '-'; ?>
                        </td>
                        <td class="text-center">
                            <?php echo isset($subject->DiemThi) && $subject->DiemThi !== null 
                                ? number_format($subject->DiemThi, 1) : '-'; ?>
                        </td>
                        <td class="text-center fw-bold">
                            <?php echo isset($subject->DiemTongKet) && $subject->DiemTongKet !== null 
                                ? number_format($subject->DiemTongKet, 1) : '-'; ?>
                        </td>
                        <td class="text-center">
                            <?php echo isset($subject->DiemChu) && $subject->DiemChu !== null 
                                ? $subject->DiemChu : '-'; ?>
                        </td>
                        <td class="text-center">
                            <?php echo isset($subject->DiemHe4) && $subject->DiemHe4 !== null 
                                ? number_format($subject->DiemHe4, 1) : '-'; ?>
                        </td>
                        <td class="text-center">
                            <?php
                            if (isset($subject->KetQua)) {
                                if ($subject->KetQua == 1) {
                                    echo '<span class="badge bg-success">Đạt</span>';
                                } elseif ($subject->KetQua == 2) {
                                    echo '<span class="badge bg-danger">Không đạt</span>';
                                } else {
                                    echo '<span class="badge bg-warning text-dark">Chờ kết quả</span>';
                                }
                            } else {
                                echo '-';
                            }
                            
                            // Tính điểm trung bình nếu đạt
                            if (isset($subject->DiemHe4) && isset($subject->KetQua) && $subject->KetQua == 1) {
                                $tongTC += $subject->SoTC;
                                $tongDiem += $subject->DiemHe4 * $subject->SoTC;
                            }
                            ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
            <tfoot class="table-light">
                <tr>
                    <th colspan="2" class="fw-bold">Điểm trung bình học kỳ</th>
                    <th class="text-center fw-bold"><?php echo $tongTC; ?></th>
                    <th colspan="4"></th>
                    <th class="text-center fw-bold">
                        <?php 
                        // Tính điểm trung bình
                        $diemTB = $tongTC > 0 ? $tongDiem / $tongTC : 0;
                        $semesterDiemChu = '';
                        
                        // Chuyển đổi điểm hệ 4 sang điểm chữ
                        if ($diemTB >= 4.0) $semesterDiemChu = 'A+';
                        elseif ($diemTB >= 3.7) $semesterDiemChu = 'A';
                        elseif ($diemTB >= 3.5) $semesterDiemChu = 'B+';
                        elseif ($diemTB >= 3.0) $semesterDiemChu = 'B';
                        elseif ($diemTB >= 2.5) $semesterDiemChu = 'C+';
                        elseif ($diemTB >= 2.0) $semesterDiemChu = 'C';
                        elseif ($diemTB >= 1.5) $semesterDiemChu = 'D+';
                        elseif ($diemTB >= 1.0) $semesterDiemChu = 'D';
                        else $semesterDiemChu = 'F';
                        
                        echo $semesterDiemChu;
                        ?>
                    </th>
                    <th class="text-center fw-bold">
                        <?php echo $tongTC > 0 ? number_format($diemTB, 2) : '-'; ?>
                    </th>
                    <th class="text-center">
                        <?php if ($tongTC > 0): ?>
                            <?php if ($diemTB >= 1.0): ?>
                                <span class="badge bg-success">Đạt</span>
                            <?php else: ?>
                                <span class="badge bg-danger">Không đạt</span>
                            <?php endif; ?>
                        <?php else: ?>
                            -
                        <?php endif; ?>
                    </th>
                </tr>
            </tfoot>
        </table>
    </div>
    
    <div class="row mt-4">
        <div class="col-md-4 mb-3">
            <div class="d-flex align-items-center">
                <i class="fas fa-chart-line text-success fa-2x me-2"></i>
                <div>
                    <p class="mb-0">Điểm trung bình:</p>
                    <h4 class="mb-0 text-success">
                        <?php echo $tongTC > 0 ? number_format($diemTB, 2) : '0.00'; ?>
                        <small class="ms-2 text-muted">(<?php echo $semesterDiemChu; ?>)</small>
                    </h4>
                </div>
            </div>
        </div>
        <div class="col-md-4 mb-3">
            <div class="d-flex align-items-center">
                <i class="fas fa-book fa-2x text-primary me-2"></i>
                <div>
                    <p class="mb-0">Tổng số tín chỉ:</p>
                    <h4 class="mb-0 text-primary"><?php echo $tongTC; ?></h4>
                </div>
            </div>
        </div>
        <div class="col-md-4 mb-3">
            <div class="d-flex align-items-center">
                <i class="fas fa-medal fa-2x text-warning me-2"></i>
                <div>
                    <p class="mb-0">Xếp loại:</p>
                    <h4 class="mb-0 text-warning">
                        <?php echo getXepLoai($diemTB); ?>
                    </h4>
                </div>
            </div>
        </div>
    </div>
</div>
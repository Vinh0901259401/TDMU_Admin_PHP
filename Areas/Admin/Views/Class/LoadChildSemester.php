<?php
/**
 * Tệp component hiển thị điểm học kỳ
 * 
 * Tệp này nhận các biến sau từ trang cha:
 * $semesterSubjects - Mảng các đối tượng môn học
 * $semesterDTK - Điểm tổng kết học kỳ
 * $semesterSoTC - Tổng số tín chỉ học kỳ
 * $semesterDRL - Điểm rèn luyện học kỳ
 * $semesterName - Tên học kỳ (tùy chọn)
 */

// Nếu không có dữ liệu hoặc chưa được truyền vào, hiển thị thông báo
if (!isset($semesterSubjects) || empty($semesterSubjects)) {
    // Data mẫu để hiển thị khi không có dữ liệu thực
    $semesterSubjects = [
        (object)[
            'Ten' => 'Toán cao cấp',
            'DGK' => 8.5,
            'DCK' => 7.75,
            'DTB' => 8.0
        ],
        (object)[
            'Ten' => 'Lập trình căn bản',
            'DGK' => 7.25,
            'DCK' => 6.5,
            'DTB' => 6.8
        ]
    ];
    
    $semesterDTK = isset($semesterDTK) ? $semesterDTK : 3.25;
    $semesterSoTC = isset($semesterSoTC) ? $semesterSoTC : 16;
    $semesterDRL = isset($semesterDRL) ? $semesterDRL : 85;
    $semesterName = isset($semesterName) ? $semesterName : 'HK1-2024';
    
    // Thông báo chưa có dữ liệu
    echo '<div class="alert alert-info mb-4">';
    echo '<i class="fas fa-info-circle me-2"></i>';
    echo 'Chưa có dữ liệu điểm cho học kỳ này';
    echo '</div>';
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
                    <th class="bg-light text-center">Môn học</th>
                    <?php foreach ($semesterSubjects as $subject): ?>
                        <th class="text-center"><?php echo htmlspecialchars($subject->Ten); ?></th>
                    <?php endforeach; ?>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <th class="bg-light text-center">Điểm giữa kỳ</th>
                    <?php foreach ($semesterSubjects as $subject): ?>
                        <td class="text-center"><?php echo number_format($subject->DGK, 2); ?></td>
                    <?php endforeach; ?>
                </tr>
                <tr>
                    <th class="bg-light text-center">Điểm cuối kỳ</th>
                    <?php foreach ($semesterSubjects as $subject): ?>
                        <td class="text-center"><?php echo number_format($subject->DCK, 2); ?></td>
                    <?php endforeach; ?>
                </tr>
                <tr class="table-info">
                    <th class="bg-light text-center">Điểm trung bình</th>
                    <?php foreach ($semesterSubjects as $subject): ?>
                        <td class="text-center fw-bold"><?php echo number_format($subject->DTB, 2); ?></td>
                    <?php endforeach; ?>
                </tr>
            </tbody>
        </table>
    </div>
    
    <div class="row mt-4">
        <div class="col-md-4 mb-3">
            <div class="d-flex align-items-center">
                <i class="fas fa-chart-line text-success fa-2x me-2"></i>
                <div>
                    <p class="mb-0">Điểm tổng kết:</p>
                    <h4 class="mb-0 text-success"><?php echo number_format($semesterDTK, 2); ?></h4>
                </div>
            </div>
        </div>
        <div class="col-md-4 mb-3">
            <div class="d-flex align-items-center">
                <i class="fas fa-book fa-2x text-primary me-2"></i>
                <div>
                    <p class="mb-0">Tổng số tín chỉ:</p>
                    <h4 class="mb-0 text-primary"><?php echo $semesterSoTC; ?></h4>
                </div>
            </div>
        </div>
        <div class="col-md-4 mb-3">
            <div class="d-flex align-items-center">
                <i class="fas fa-medal fa-2x text-warning me-2"></i>
                <div>
                    <p class="mb-0">Điểm rèn luyện:</p>
                    <h4 class="mb-0 text-warning"><?php echo $semesterDRL; ?></h4>
                </div>
            </div>
        </div>
    </div>
</div>
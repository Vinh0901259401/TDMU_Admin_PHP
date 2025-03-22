<?php
// filepath: c:\xampp\htdocs\TDMU_website\Areas\Admin\Views\Forum\Sidebar.php

// Kiểm tra kết nối database đã được thiết lập
if (!isset($conn)) {
    require_once("../Shared/connect.inc");
}

// Kiểm tra phiên đăng nhập
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Lấy thông tin người dùng hiện tại
$currentUser = isset($_SESSION['TaiKhoan']) ? $_SESSION['TaiKhoan'] : null;

// Lấy thông tin tóm tắt cho sidebar
try {
    // Tổng số câu hỏi
    $stmtPosts = $conn->prepare("SELECT COUNT(*) as total FROM cauhoi WHERE DuocDuyet = 1");
    $stmtPosts->execute();
    $totalPosts = $stmtPosts->fetch(PDO::FETCH_ASSOC)['total'];
    
    // Tổng số chủ đề
    $stmtTopics = $conn->prepare("SELECT COUNT(*) as total FROM chudecauhoi");
    $stmtTopics->execute();
    $totalTopics = $stmtTopics->fetch(PDO::FETCH_ASSOC)['total'];
    
    // Tổng số thành viên
    $stmtMembers = $conn->prepare("SELECT COUNT(*) as total FROM taikhoan");
    $stmtMembers->execute();
    $totalMembers = $stmtMembers->fetch(PDO::FETCH_ASSOC)['total'];
    
    // Số người online (giả lập hoặc lấy từ biến session)
    $onlineUsers = isset($_SESSION['OnlineUsers']) ? $_SESSION['OnlineUsers'] : rand(1, 50);
    
    // Lấy các câu hỏi gần đây
    $stmtRecentQuestions = $conn->prepare("
        SELECT 
            ch.ID as IDCauHoi,
            ch.TieuDe,
            DATE_FORMAT(ch.NgayGui, '%d/%m/%Y') as NgayDang,
            tk.HoTen as NguoiDang
        FROM cauhoi ch
        LEFT JOIN taikhoan tk ON ch.IDNguoiGui = tk.ID
        WHERE ch.DuocDuyet = 1
        ORDER BY ch.NgayGui DESC
        LIMIT 5
    ");
    $stmtRecentQuestions->execute();
    $recentQuestions = $stmtRecentQuestions->fetchAll(PDO::FETCH_ASSOC);
    
} catch (PDOException $e) {
    error_log("Lỗi truy vấn sidebar: " . $e->getMessage());
    $totalPosts = 0;
    $totalTopics = 0;
    $totalMembers = 0;
    $onlineUsers = 1;
    $recentQuestions = [];
}
?>

<div class="sticky-sidebar">
    <div class="card shadow-sm mb-4">
        <div class="card-body p-0">
            <!-- Nút đăng câu hỏi -->
            <a href="Create.php" class="btn btn-success btn-lg btn-block py-3 rounded-0 d-flex align-items-center justify-content-center fw-bold">
                <i class="fas fa-plus-circle me-2"></i> Đăng câu hỏi
            </a>
            
            <?php if ($accessLevel > 0 && $accessLevel < 5): ?>
                <!-- Nút duyệt câu hỏi cho admin -->
                <a href="ApprovalForQuestion.php" class="btn btn-warning btn-lg btn-block py-3 rounded-0 d-flex align-items-center justify-content-center fw-bold">
                    <i class="fas fa-tasks me-2"></i> Duyệt câu hỏi
                </a>
            <?php endif; ?>
        </div>
    </div>
    
    <!-- Câu hỏi gần đây -->
    <div class="card shadow-sm mb-4">
        <div class="card-header bg-primary text-white">
            <h5 class="mb-0"><i class="fas fa-history me-2"></i>Câu hỏi gần đây</h5>
        </div>
        <div class="card-body p-0">
            <?php if (count($recentQuestions) > 0): ?>
                <ul class="list-group list-group-flush">
                    <?php foreach ($recentQuestions as $question): ?>
                        <li class="list-group-item p-3">
                            <h6 class="mb-1">
                                <a href="Details.php?id=<?php echo $question['IDCauHoi']; ?>" class="text-primary text-decoration-none">
                                    <?php echo htmlspecialchars($question['TieuDe']); ?>
                                </a>
                            </h6>
                            <p class="mb-0 small text-muted">
                                <i class="far fa-calendar-alt me-1"></i> <?php echo $question['NgayDang']; ?> 
                                <span class="mx-1">•</span>
                                <i class="far fa-user me-1"></i> <?php echo htmlspecialchars($question['NguoiDang']); ?>
                            </p>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php else: ?>
                <div class="text-center p-3">
                    <p class="text-muted mb-0">Chưa có câu hỏi nào được đăng</p>
                </div>
            <?php endif; ?>
        </div>
    </div>
    
    <!-- Thống kê diễn đàn -->
    <div class="card shadow-sm mb-4">
        <div class="card-header bg-primary text-white">
            <h5 class="mb-0"><i class="fas fa-chart-bar me-2"></i>Thống kê diễn đàn</h5>
        </div>
        <div class="card-body">
            <?php
            // Truy vấn số liệu thống kê
            try {
                // Tổng số câu hỏi
                $stmtQuestions = $conn->query("SELECT COUNT(*) as total FROM cauhoi WHERE DuocDuyet = 1");
                $totalQuestions = $stmtQuestions->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;
                
                // Tổng số câu trả lời
                $stmtAnswers = $conn->query("SELECT COUNT(*) as total FROM cauhoi_nguoitraloi");
                $totalAnswers = $stmtAnswers->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;
                
                // Tổng số người dùng
                $stmtUsers = $conn->query("SELECT COUNT(*) as total FROM taikhoan");
                $totalUsers = $stmtUsers->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;
            } catch (PDOException $e) {
                error_log("Lỗi truy vấn thống kê: " . $e->getMessage());
                $totalQuestions = $totalAnswers = $totalUsers = 0;
            }
            ?>
            <ul class="list-group list-group-flush">
                <li class="list-group-item d-flex justify-content-between align-items-center">
                    <span><i class="fas fa-question-circle text-primary me-2"></i>Câu hỏi</span>
                    <span class="badge bg-primary rounded-pill"><?php echo number_format($totalQuestions); ?></span>
                </li>
                <li class="list-group-item d-flex justify-content-between align-items-center">
                    <span><i class="fas fa-comments text-success me-2"></i>Trả lời</span>
                    <span class="badge bg-success rounded-pill"><?php echo number_format($totalAnswers); ?></span>
                </li>
                <li class="list-group-item d-flex justify-content-between align-items-center">
                    <span><i class="fas fa-users text-info me-2"></i>Thành viên</span>
                    <span class="badge bg-info rounded-pill"><?php echo number_format($totalUsers); ?></span>
                </li>
            </ul>
        </div>
    </div>
    
    <!-- Thao tác nhanh -->
    <div class="card shadow-sm mb-4">
        <div class="card-header bg-info text-white">
            <h5 class="mb-0"><i class="fas fa-bolt me-2"></i>Thao tác nhanh</h5>
        </div>
        <div class="card-body">
            <div class="d-grid gap-2">
                <a href="index.php" class="btn btn-outline-primary">
                    <i class="fas fa-home me-2"></i>Quay lại diễn đàn
                </a>
                
                <a href="Create.php" class="btn btn-primary">
                    <i class="fas fa-plus-circle me-2"></i>Đăng câu hỏi mới
                </a>
                
                <?php if ($currentUser): ?>
                <a href="MyQuestions.php" class="btn btn-outline-primary">
                    <i class="fas fa-list-alt me-2"></i>Câu hỏi của tôi
                </a>
                <?php endif; ?>
                
                <?php if ($currentUser && isset($currentUser['IDQuyenTruyCap']) && in_array($currentUser['IDQuyenTruyCap'], ['Q01', 'Q02'])): ?>
                <a href="ManageTopics.php" class="btn btn-outline-info">
                    <i class="fas fa-cog me-2"></i>Quản lý chủ đề
                </a>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <!-- Tags / Chủ đề phổ biến -->
    <?php
    try {
        $stmtPopularTopics = $conn->prepare("
            SELECT 
                cd.ID,
                cd.Ten,
                COUNT(ch_cd.IDCauHoi) as SoLuongCauHoi
            FROM chudecauhoi cd
            LEFT JOIN cauhoi_chudecauhoi ch_cd ON cd.ID = ch_cd.IDChuDeCauHoi
            GROUP BY cd.ID
            ORDER BY SoLuongCauHoi DESC
            LIMIT 10
        ");
        $stmtPopularTopics->execute();
        $popularTopics = $stmtPopularTopics->fetchAll(PDO::FETCH_ASSOC);
        
        if (count($popularTopics) > 0):
    ?>
        <div class="card shadow-sm mb-4">
            <div class="card-header bg-info text-white">
                <h5 class="mb-0"><i class="fas fa-tags me-2"></i>Chủ đề phổ biến</h5>
            </div>
            <div class="card-body">
                <div class="d-flex flex-wrap gap-2">
                    <?php foreach ($popularTopics as $topic): ?>
                        <a href="Index.php?topic=<?php echo urlencode($topic['ID']); ?>" class="btn btn-outline-info btn-sm mb-2">
                            <?php echo htmlspecialchars($topic['Ten']); ?>
                            <span class="badge bg-info text-white ms-1"><?php echo $topic['SoLuongCauHoi']; ?></span>
                        </a>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    <?php 
        endif;
    } catch (PDOException $e) {
        error_log("Lỗi truy vấn chủ đề phổ biến: " . $e->getMessage());
    }
    ?>
    
    <!-- Liên kết hữu ích -->
    <div class="card shadow-sm mb-4">
        <div class="card-header bg-secondary text-white">
            <h5 class="mb-0"><i class="fas fa-link me-2"></i>Liên kết hữu ích</h5>
        </div>
        <div class="card-body p-0">
            <div class="list-group list-group-flush">
                <a href="index.php" class="list-group-item list-group-item-action d-flex align-items-center">
                    <i class="fas fa-home me-3 text-primary"></i> Trang chủ diễn đàn
                </a>
                <a href="MyQuestions.php" class="list-group-item list-group-item-action d-flex align-items-center">
                    <i class="fas fa-question-circle me-3 text-info"></i> Câu hỏi của tôi
                </a>
                <a href="../Dashboard/Index.php" class="list-group-item list-group-item-action d-flex align-items-center">
                    <i class="fas fa-tachometer-alt me-3 text-success"></i> Quay lại Dashboard
                </a>
                <a href="../../Home/Index.php" class="list-group-item list-group-item-action d-flex align-items-center">
                    <i class="fas fa-university me-3 text-danger"></i> Trang chủ trường
                </a>
            </div>
        </div>
    </div>
</div>

<style>
/* CSS để làm sidebar cố định khi cuộn trang */
.sticky-sidebar {
    position: sticky;
    top: 20px;
}

.stat-box {
    transition: all 0.3s ease;
}

.stat-box:hover {
    transform: translateY(-3px);
}

.list-group-item-action {
    transition: all 0.2s ease;
}

.list-group-item-action:hover {
    transform: translateX(5px);
}
</style>

<script>
// JavaScript để xử lý sticky sidebar nếu cần
$(document).ready(function() {
    // Nếu cần thêm xử lý JavaScript cho sidebar
});
</script>
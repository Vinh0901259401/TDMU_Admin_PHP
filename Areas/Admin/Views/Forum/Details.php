<?php
// filepath: c:\xampp\htdocs\TDMU_website\Areas\Admin\Views\Forum\Details.php

// Kết nối database và khởi tạo session
require_once("../Shared/connect.inc");
session_start();

// Tiêu đề trang
$pageTitle = "Chi tiết câu hỏi";

// Kiểm tra quyền truy cập
$accessLevel = 0;
$tk = isset($_SESSION['TaiKhoan']) ? $_SESSION['TaiKhoan'] : null;

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

// Lấy ID câu hỏi từ tham số
$questionId = isset($_GET['id']) ? $_GET['id'] : null;
$question = null;

// Xử lý khi có ID câu hỏi
if ($questionId) {
    try {
        // Kiểm tra tồn tại
        $checkStmt = $conn->prepare("SELECT ID FROM cauhoi WHERE ID = ?");
        $checkStmt->execute([$questionId]);
        
        if ($checkStmt->rowCount() == 0) {
            $question = null;
        } else {
            // Truy vấn chi tiết câu hỏi
            $stmt = $conn->prepare("
                SELECT 
                    ch.ID as IDCauHoi, 
                    ch.TieuDe,
                    ch.NoiDung, 
                    ch.NgayGui as NgayDang, 
                    ch.DuocDuyet,  
                    cd.Ten as ChuDe,
                    tk.HoTen as NguoiDang, 
                    tk.ID as IDNguoiDang,
                    tk.ImagePath as AnhDaiDien
                FROM cauhoi ch
                LEFT JOIN taikhoan tk ON ch.IDNguoiGui = tk.ID
                LEFT JOIN cauhoi_chudecauhoi ch_cd ON ch.ID = ch_cd.IDCauHoi
                LEFT JOIN chudecauhoi cd ON ch_cd.IDChuDeCauHoi = cd.ID
                WHERE ch.ID = ?
            ");
            $stmt->execute([$questionId]);
            
            if ($stmt->rowCount() > 0) {
                $question = $stmt->fetch(PDO::FETCH_ASSOC);
                
                // Thiết lập các giá trị mặc định cho tất cả các khóa cần thiết
                if (!isset($question['ChuDe']) || empty($question['ChuDe'])) $question['ChuDe'] = 'Chưa phân loại';
                if (!isset($question['NguoiDang']) || empty($question['NguoiDang'])) $question['NguoiDang'] = 'Người dùng không xác định';
                if (!isset($question['DuocDuyet'])) $question['DuocDuyet'] = 0; // Thêm dòng này
                $question['LuotThich'] = 0;
                $question['LuotKhongThich'] = 0;
                $question['LuotTraLoi'] = 0;
                $question['UserRating'] = -1;
                
                // Lấy số lượt thích/không thích
                $stmtLikes = $conn->prepare("
                    SELECT 
                        SUM(CASE WHEN HuuIch = 1 THEN 1 ELSE 0 END) as LuotThich,
                        SUM(CASE WHEN HuuIch = 0 THEN 1 ELSE 0 END) as LuotKhongThich
                    FROM cauhoi_nguoidanhgia
                    WHERE IDCauHoi = ?
                ");
                $stmtLikes->execute([$questionId]);
                $likes = $stmtLikes->fetch(PDO::FETCH_ASSOC);
                
                $question['LuotThich'] = $likes['LuotThich'] ?? 0;
                $question['LuotKhongThich'] = $likes['LuotKhongThich'] ?? 0;
                
                // Lấy số lượng trả lời
                $stmtComments = $conn->prepare("
                    SELECT COUNT(*) as LuotTraLoi
                    FROM cauhoi_nguoitraloi
                    WHERE IDCauHoi = ?
                ");
                $stmtComments->execute([$questionId]);
                $comments = $stmtComments->fetch(PDO::FETCH_ASSOC);
                
                $question['LuotTraLoi'] = $comments['LuotTraLoi'] ?? 0;
                
                // Kiểm tra người xem đã đánh giá câu hỏi này chưa
                if ($tk) {
                    $stmtUserRating = $conn->prepare("
                        SELECT HuuIch
                        FROM cauhoi_nguoidanhgia
                        WHERE IDCauHoi = ? AND IDNguoiDanhGia = ?
                    ");
                    $stmtUserRating->execute([$questionId, $tk['ID']]);
                    
                    if ($stmtUserRating->rowCount() > 0) {
                        $rating = $stmtUserRating->fetch(PDO::FETCH_ASSOC);
                        $question['UserRating'] = (int)$rating['HuuIch'];
                    }
                }
                
                // Lấy câu hỏi liên quan
                $stmtRelated = $conn->prepare("
                    SELECT 
                        ch.ID, 
                        ch.TieuDe, 
                        ch.NgayGui as NgayDang,
                        COUNT(ctl.ID) AS SoTraLoi
                    FROM cauhoi ch
                    LEFT JOIN cauhoi_nguoitraloi ctl ON ch.ID = ctl.IDCauHoi
                    WHERE ch.ID != ? AND ch.DuocDuyet = 1
                    GROUP BY ch.ID
                    ORDER BY ch.NgayGui DESC
                    LIMIT 5
                ");
                $stmtRelated->execute([$questionId]);
                $relatedQuestions = $stmtRelated->fetchAll(PDO::FETCH_ASSOC);
            } else {
                $question = null;
            }
        }
    } catch (PDOException $e) {
        error_log("Lỗi truy vấn chi tiết câu hỏi: " . $e->getMessage());
        $question = null;
    }
}

// Xử lý đánh giá câu hỏi (like/dislike)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $tk) {
    $action = $_POST['action'];
    $questionIdToRate = $_POST['questionId'] ?? null;
    
    if ($questionIdToRate && ($action === 'like' || $action === 'dislike')) {
        try {
            // Kiểm tra xem người dùng đã đánh giá câu hỏi này chưa
            $stmtCheck = $conn->prepare("
                SELECT ID FROM cauhoi_nguoidanhgia 
                WHERE IDCauHoi = ? AND IDNguoiDanhGia = ?
            ");
            $stmtCheck->execute([$questionIdToRate, $tk['ID']]);
            
            $isHelpful = ($action === 'like') ? 1 : 0;
            
            if ($stmtCheck->rowCount() > 0) {
                // Đã đánh giá rồi, cập nhật đánh giá
                $ratingId = $stmtCheck->fetch(PDO::FETCH_ASSOC)['ID'];
                $stmt = $conn->prepare("
                    UPDATE cauhoi_nguoidanhgia 
                    SET HuuIch = ?, NgayDanhGia = NOW()
                    WHERE ID = ?
                ");
                $stmt->execute([$isHelpful, $ratingId]);
            } else {
                // Chưa đánh giá, tạo mới
                // Tạo ID mới cho đánh giá
                $stmtNewId = $conn->prepare("SELECT MAX(CAST(SUBSTRING(ID, 4) AS UNSIGNED)) as max_id FROM cauhoi_nguoidanhgia");
                $stmtNewId->execute();
                $result = $stmtNewId->fetch(PDO::FETCH_ASSOC);
                $nextId = (int)($result['max_id'] ?? 0) + 1;
                $newRatingId = 'DG' . str_pad($nextId, 8, '0', STR_PAD_LEFT);
                
                $stmt = $conn->prepare("
                    INSERT INTO cauhoi_nguoidanhgia 
                    (ID, IDCauHoi, IDNguoiDanhGia, HuuIch, NgayDanhGia)
                    VALUES (?, ?, ?, ?, NOW())
                ");
                $stmt->execute([$newRatingId, $questionIdToRate, $tk['ID'], $isHelpful]);
            }
            
            // Redirect để tránh gửi lại form khi refresh
            header('Location: ' . $_SERVER['REQUEST_URI']);
            exit;
        } catch (PDOException $e) {
            error_log("Lỗi khi đánh giá câu hỏi: " . $e->getMessage());
        }
    }
}

// Bắt đầu buffer đầu ra
ob_start();
?>

<!-- CSS custom cho trang chi tiết -->
<link rel="stylesheet" href="../../Assets/package/Forum/css/forum.css" />
<link href="https://cdnjs.cloudflare.com/ajax/libs/toastr.js/latest/toastr.min.css" rel="stylesheet">

<!-- Styles cho chi tiết câu hỏi -->
<style>
.question-content {
    font-size: 1.05rem;
    line-height: 1.6;
    white-space: pre-line;
    min-height: 150px;
}

.comment-item {
    border-left: 3px solid #007bff;
    margin-bottom: 15px;
    transition: all 0.3s ease;
}

.comment-item:hover {
    background-color: #f8f9fa;
}

.reply-item {
    position: relative;
    padding-left: 15px;
    margin-left: 20px;
    border-left: 2px solid #dee2e6;
}

.avatar img {
    border: 2px solid #e9ecef;
    object-fit: cover;
}

/* Rating buttons */
.btn-outline-success:hover, .btn-outline-danger:hover {
    transform: scale(1.05);
}

/* Animation cho các hành động */
.btn, .card {
    transition: all 0.3s ease;
}

.btn:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 8px rgba(0,0,0,0.1);
}

.card {
    box-shadow: 0 4px 6px rgba(0,0,0,0.05);
}

.card:hover {
    box-shadow: 0 8px 15px rgba(0,0,0,0.1);
}

/* Panel for questions */
.panel-shadow {
    box-shadow: 0 8px 16px rgba(0,0,0,.1);
}

/* Badge styling */
.badge {
    font-weight: 500;
    padding: 0.5em 0.75em;
}

/* List group item hover effect */
.list-group-item-action:hover {
    transform: translateY(-2px);
    z-index: 1;
    border-color: #007bff;
}

/* Responsive adjustments */
@media (max-width: 768px) {
    .question-content {
        font-size: 1rem;
    }
    
    .card-header h5 {
        font-size: 1.1rem;
    }
}

/* Styling for code blocks if present in questions */
pre {
    background: #f8f9fa;
    border: 1px solid #e9ecef;
    border-radius: 4px;
    padding: 10px;
    overflow-x: auto;
}

/* Quote styling */
blockquote {
    border-left: 3px solid #6c757d;
    padding-left: 1rem;
    color: #6c757d;
    font-style: italic;
}

/* Content area */
.content-area img {
    max-width: 100%;
    height: auto;
}

.content-area table {
    width: 100%;
    border-collapse: collapse;
    margin-bottom: 1rem;
}

.content-area table, .content-area th, .content-area td {
    border: 1px solid #dee2e6;
}

.content-area th, .content-area td {
    padding: 0.5rem;
}

.content-area th {
    background-color: #f8f9fa;
}
</style>

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
<?php elseif (!$question): ?>
    <div class="container-fluid px-4">
        <div class="row">
            <div class="col-12">
                <div class="alert alert-warning my-4 p-5 text-center">
                    <div class="mb-3">
                        <i class="fas fa-search fa-5x text-warning"></i>
                    </div>
                    <h2 class="text-warning">KHÔNG TÌM THẤY CÂU HỎI!</h2>
                    <h4>Câu hỏi bạn đang tìm kiếm không tồn tại hoặc đã bị xóa.</h4>
                    <div class="mt-4">
                        <a href="index.php" class="btn btn-primary">
                            <i class="fas fa-arrow-left me-2"></i>Quay lại danh sách câu hỏi
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
<?php else: ?>
    <div class="container-fluid px-4">
        <div class="row">
            <!-- Main content -->
            <div class="col-lg-8">
                <div class="card shadow-sm mb-4">
                    <div class="card-header bg-primary text-white d-flex justify-content-between align-items-center">
                        <h5 class="mb-0"><?php echo htmlspecialchars($question['TieuDe']); ?></h5>
                        <span class="badge bg-info"><?php echo htmlspecialchars($question['ChuDe']); ?></span>
                    </div>
                    <div class="card-body">
                        <div class="d-flex mb-3">
                            <div class="flex-shrink-0">
                                <?php
                                    $avatarUrl = '../../Assets/img/user-image/default-avatar.png';
                                    if (!empty($question['AnhDaiDien'])) {
                                        $avatarUrl = $question['AnhDaiDien'];
                                        // Kiểm tra xem đường dẫn đã đầy đủ chưa
                                        if (!preg_match('/^(http|https):\/\//', $avatarUrl) && !preg_match('/^\.\.\//', $avatarUrl)) {
                                            $avatarUrl = '../../Assets/img/user-image/' . $avatarUrl;
                                        }
                                    }
                                ?>
                                <img src="<?php echo $avatarUrl; ?>" class="rounded-circle" width="50" height="50" alt="Avatar">
                            </div>
                            <div class="ms-3">
                                <h6 class="mb-0"><?php echo htmlspecialchars($question['NguoiDang']); ?></h6>
                                <small class="text-muted">
                                    <i class="far fa-clock me-1"></i>
                                    <?php echo date('d/m/Y H:i', strtotime($question['NgayDang'])); ?>
                                </small>
                            </div>
                        </div>
                        
                        <div class="question-content content-area mb-4">
                            <?php echo nl2br(htmlspecialchars($question['NoiDung'])); ?>
                        </div>
                        
                        <!-- Action buttons -->
                        <div class="d-flex justify-content-between align-items-center border-top pt-3">
                            <div class="d-flex">
                                <form method="post" class="me-2">
                                    <input type="hidden" name="questionId" value="<?php echo $question['IDCauHoi']; ?>">
                                    <input type="hidden" name="action" value="like">
                                    <button type="submit" class="btn <?php echo $question['UserRating'] === 1 ? 'btn-success' : 'btn-outline-success'; ?>">
                                        <i class="far fa-thumbs-up me-1"></i> Hữu ích 
                                        <span class="badge bg-light text-dark ms-1"><?php echo $question['LuotThich']; ?></span>
                                    </button>
                                </form>
                                <form method="post">
                                    <input type="hidden" name="questionId" value="<?php echo $question['IDCauHoi']; ?>">
                                    <input type="hidden" name="action" value="dislike">
                                    <button type="submit" class="btn <?php echo $question['UserRating'] === 0 ? 'btn-danger' : 'btn-outline-danger'; ?>">
                                        <i class="far fa-thumbs-down me-1"></i> 
                                        <span class="badge bg-light text-dark ms-1"><?php echo $question['LuotKhongThich']; ?></span>
                                    </button>
                                </form>
                            </div>
                            
                            <div>
                                <span class="text-muted">
                                    <i class="far fa-comment-alt me-1"></i> 
                                    <span id="comments-count"><?php echo $question['LuotTraLoi']; ?></span> trả lời
                                </span>
                                <?php if ($accessLevel <= 2): ?>
                                    <a href="edit.php?id=<?php echo urlencode($question['IDCauHoi']); ?>" class="btn btn-sm btn-primary ms-2">
                                        <i class="fas fa-edit"></i>
                                    </a>
                                    <button type="button" class="btn btn-sm btn-danger ms-1" data-bs-toggle="modal" data-bs-target="#deleteModal">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Comments section -->
                <div class="card shadow-sm mb-4">
                    <div class="card-header bg-light d-flex justify-content-between align-items-center">
                        <h5 class="mb-0">
                            <i class="far fa-comments me-2"></i>Bình luận
                        </h5>
                    </div>
                    <div class="card-body">
                        <?php if ($tk): ?>
                            <form id="comment-form" action="add_comment.php" method="post" class="mb-4">
                                <input type="hidden" name="questionId" value="<?php echo htmlspecialchars($question['IDCauHoi']); ?>">
                                <div class="form-group">
                                    <label for="comment-content" class="fw-bold mb-2">Thêm bình luận của bạn:</label>
                                    <textarea id="comment-content" name="commentContent" class="form-control" rows="3" required></textarea>
                                </div>
                                <div class="mt-3 d-flex justify-content-end">
                                    <button type="submit" class="btn btn-primary">
                                        <i class="fas fa-paper-plane me-1"></i> Gửi bình luận
                                    </button>
                                </div>
                            </form>
                        <?php else: ?>
                            <div class="alert alert-info">
                                <i class="fas fa-info-circle me-2"></i>
                                Bạn cần <a href="../Auth/Login.php">đăng nhập</a> để có thể bình luận.
                            </div>
                        <?php endif; ?>
                        
                        <div id="comments-container" class="mt-4">
                            <div class="text-center py-4">
                                <div class="spinner-border text-primary" role="status">
                                    <span class="visually-hidden">Đang tải...</span>
                                </div>
                                <div class="mt-3">Đang tải bình luận...</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Sidebar -->
            <div class="col-lg-4">
                <!-- Card: Thống kê -->
                <div class="card shadow-sm mb-4">
                    <div class="card-header bg-info text-white">
                        <h6 class="mb-0"><i class="fas fa-chart-pie me-2"></i>Thống kê</h6>
                    </div>
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <span>Lượt đánh giá tích cực:</span>
                            <span class="badge bg-success"><?php echo $question['LuotThich']; ?></span>
                        </div>
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <span>Lượt đánh giá tiêu cực:</span>
                            <span class="badge bg-danger"><?php echo $question['LuotKhongThich']; ?></span>
                        </div>
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <span>Số lượng bình luận:</span>
                            <span class="badge bg-primary"><?php echo $question['LuotTraLoi']; ?></span>
                        </div>
                        <div class="d-flex justify-content-between align-items-center">
                            <span>Trạng thái:</span>
                            <?php if (isset($question['DuocDuyet']) && $question['DuocDuyet'] == 1): ?>
                                <span class="badge bg-success">Đã duyệt</span>
                            <?php else: ?>
                                <span class="badge bg-warning text-dark">Chờ duyệt</span>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                
                <!-- Card: Tác giả -->
                <div class="card shadow-sm mb-4">
                    <div class="card-header bg-success text-white">
                        <h6 class="mb-0"><i class="fas fa-user me-2"></i>Thông tin tác giả</h6>
                    </div>
                    <div class="card-body">
                        <div class="d-flex align-items-center">
                            <div class="flex-shrink-0">
                                <img src="<?php echo $avatarUrl; ?>" class="rounded-circle" width="60" height="60" alt="Avatar">
                            </div>
                            <div class="ms-3">
                                <h6 class="mb-1"><?php echo htmlspecialchars($question['NguoiDang']); ?></h6>
                                <?php if (!empty($question['IDNguoiDang'])): ?>
                                <a href="../User/Details.php?id=<?php echo urlencode($question['IDNguoiDang']); ?>" class="btn btn-sm btn-outline-primary mt-1">
                                    <i class="fas fa-external-link-alt me-1"></i> Xem hồ sơ
                                </a>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Câu hỏi liên quan -->
                <?php if (!empty($relatedQuestions)): ?>
                <div class="card shadow-sm">
                    <div class="card-header bg-warning text-dark">
                        <h6 class="mb-0"><i class="fas fa-question-circle me-2"></i>Câu hỏi liên quan</h6>
                    </div>
                    <ul class="list-group list-group-flush">
                        <?php foreach ($relatedQuestions as $relatedQuestion): ?>
                            <li class="list-group-item list-group-item-action">
                                <a href="Details.php?id=<?php echo urlencode($relatedQuestion['ID']); ?>" class="text-decoration-none text-dark">
                                    <div class="d-flex justify-content-between align-items-center">
                                        <div class="text-truncate">
                                            <?php echo htmlspecialchars($relatedQuestion['TieuDe']); ?>
                                        </div>
                                        <span class="badge bg-light text-dark ms-2"><?php echo $relatedQuestion['SoTraLoi']; ?></span>
                                    </div>
                                    <small class="text-muted">
                                        <i class="far fa-calendar-alt me-1"></i>
                                        <?php echo date('d/m/Y', strtotime($relatedQuestion['NgayDang'])); ?>
                                    </small>
                                </a>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <?php if ($accessLevel <= 2): ?>
    <!-- Modal Xác nhận xóa -->
    <div class="modal fade" id="deleteModal" tabindex="-1" aria-labelledby="deleteModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header bg-danger text-white">
                    <h5 class="modal-title" id="deleteModalLabel">Xác nhận xóa câu hỏi</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <p>Bạn có chắc chắn muốn xóa câu hỏi này?</p>
                    <p><strong>Tiêu đề:</strong> <?php echo htmlspecialchars($question['TieuDe']); ?></p>
                    <p class="text-danger"><strong>Lưu ý:</strong> Hành động này không thể hoàn tác!</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Hủy</button>
                    <a href="delete.php?id=<?php echo urlencode($question['IDCauHoi']); ?>" class="btn btn-danger">Xác nhận xóa</a>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>
<?php endif; ?>

<!-- Script cho trang chi tiết -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/toastr.js/latest/toastr.min.js"></script>
<script>
// Cấu hình Toastr
toastr.options = {
    "closeButton": true,
    "progressBar": true,
    "positionClass": "toast-top-right",
    "showDuration": "300",
    "hideDuration": "1000",
    "timeOut": "5000"
};

$(document).ready(function() {
    // Tải bình luận khi trang được load
    loadComments();
    
    // Xử lý form thêm bình luận
    $("#comment-form").on("submit", function(e) {
        e.preventDefault();
        var form = $(this);
        var content = $("#comment-content").val().trim();
        
        if (content === "") {
            toastr.warning("Vui lòng nhập nội dung bình luận.");
            return false;
        }
        
        // Vô hiệu hóa nút gửi để tránh gửi nhiều lần
        var submitBtn = form.find("button[type='submit']");
        var originalBtnText = submitBtn.html();
        submitBtn.prop("disabled", true).html('<i class="fas fa-spinner fa-spin me-1"></i> Đang gửi...');
        
        // Thu thập dữ liệu form
        var formData = {
            questionId: form.find("input[name='questionId']").val(),
            commentContent: content
        };
        
        // Gửi request bằng AJAX
        $.ajax({
            url: "add_comment.php",
            type: "POST",
            data: formData,
            dataType: "json",
            success: function(response) {
                console.log("Success response:", response); // Debug
                if (response && response.success) {
                    // Reset form
                    $("#comment-content").val("");
                    // Tải lại bình luận
                    loadComments();
                    // Thông báo thành công
                    toastr.success(response.message || "Bình luận đã được gửi thành công!");
                } else {
                    toastr.error(response.message || "Đã xảy ra lỗi khi gửi bình luận.");
                }
            },
            error: function(xhr, status, error) {
                console.error("AJAX Error:", status, error);
                console.log("Response text:", xhr.responseText); // Debug
                toastr.error("Đã xảy ra lỗi khi gửi bình luận. Vui lòng thử lại sau.");
            },
            complete: function() {
                // Kích hoạt lại nút gửi
                submitBtn.prop("disabled", false).html(originalBtnText);
            }
        });
        
        return false;
    });
    
    // Hàm tải bình luận
    function loadComments() {
        var questionId = '<?php echo $questionId; ?>';
        
        $.ajax({
            url: "LoadComment.php",
            type: "get",
            data: { questionId: questionId },
            success: function(response) {
                $("#comments-container").html(response);
                // Cập nhật số lượng bình luận
                var count = $("#comments-container .comment-item").length;
                $("#comments-count").text(count);
                
                // Kích hoạt các sự kiện cho phản hồi
                activateReplyEvents();
            },
            error: function() {
                $("#comments-container").html('<div class="alert alert-danger"><i class="fas fa-exclamation-circle me-2"></i>Đã xảy ra lỗi khi tải bình luận.</div>');
            }
        });
    }
    
    // Kích hoạt sự kiện cho các phần tử phản hồi
    function activateReplyEvents() {
        // Hiển thị form phản hồi khi nhấn nút "Trả lời"
        $('.reply-btn').off('click').on('click', function() {
            var commentId = $(this).data('comment-id');
            $('#reply-form-' + commentId).toggle();
        });
        
        // Xử lý nút gửi phản hồi
        $('.send-reply-btn').off('click').on('click', function() {
            var commentId = $(this).data('comment-id');
            var content = $('#reply-form-' + commentId + ' .reply-content').val().trim();
            
            if (content === '') return;
            
            $.ajax({
                url: 'add_reply.php',
                type: 'POST',
                data: {
                    parentCommentId: commentId,
                    content: content,
                    questionId: '<?php echo $questionId; ?>'
                },
                dataType: 'json',
                success: function(response) {
                    if (response.success) {
                        // Ẩn form trả lời
                        $('#reply-form-' + commentId).hide();
                        // Xóa nội dung đã nhập
                        $('#reply-form-' + commentId + ' .reply-content').val('');
                        
                        // Tải lại phản hồi nếu đã hiển thị
                        if ($('#replies-' + commentId + ' .reply-item').length > 0) {
                            loadReplies(commentId);
                        } else {
                            // Tải phản hồi mới
                            loadReplies(commentId);
                        }
                        
                        // Hiển thị thông báo thành công
                        toastr.success("Phản hồi đã được gửi thành công!");
                    } else {
                        toastr.error(response.message || "Đã xảy ra lỗi khi gửi phản hồi.");
                    }
                },
                error: function() {
                    toastr.error("Đã xảy ra lỗi khi gửi phản hồi. Vui lòng thử lại sau.");
                }
            });
        });
        
        // Xử lý nút "Xem phản hồi"
        $('.load-replies-btn').off('click').on('click', function() {
            var commentId = $(this).data('comment-id');
            loadReplies(commentId);
        });
    }
    
    // Hàm tải phản hồi cho một bình luận
    function loadReplies(commentId) {
        var repliesContainer = $('#replies-' + commentId);
        repliesContainer.html('<div class="text-center py-2"><div class="spinner-border spinner-border-sm text-primary" role="status"></div> <span>Đang tải phản hồi...</span></div>');
        
        $.ajax({
            url: 'LoadReplies.php',
            type: 'GET',
            data: {
                commentId: commentId
            },
            success: function(response) {
                repliesContainer.html(response);
            },
            error: function() {
                repliesContainer.html('<div class="alert alert-danger p-2">Đã xảy ra lỗi khi tải phản hồi.</div>');
            }
        });
    }
});
</script>

<?php
// Lấy nội dung đã buffer
$contentForLayout = ob_get_clean();

// Include template chung
include("../Shared/_LayoutAdmin.php");
?>
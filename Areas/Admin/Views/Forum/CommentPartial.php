<?php
// filepath: c:\xampp\htdocs\TDMU_website\Areas\Admin\Views\Forum\CommentPartial.php

// Kết nối database và khởi tạo session nếu cần
require_once("../Shared/connect.inc");
session_start();

// Lấy ID câu hỏi từ tham số truyền vào
$questionId = isset($_GET['maCH']) ? $_GET['maCH'] : null;

// Kiểm tra người dùng đã đăng nhập chưa
$isLoggedIn = isset($_SESSION['TaiKhoan']) && !empty($_SESSION['TaiKhoan']);
$currentUser = $isLoggedIn ? $_SESSION['TaiKhoan'] : null;

// Xử lý khi người dùng đăng bình luận
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $isLoggedIn) {
    $comment = isset($_POST['sBinhLuan']) ? trim($_POST['sBinhLuan']) : '';
    $maCH = isset($_POST['sMaCH']) ? trim($_POST['sMaCH']) : '';
    
    if (!empty($comment) && !empty($maCH)) {
        try {
            // Tạo ID mới cho bình luận
            $stmt = $conn->prepare("SELECT MAX(CAST(SUBSTRING(ID, 4) AS UNSIGNED)) as max_id FROM cauhoi_nguoitraloi");
            $stmt->execute();
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            $nextId = (int)$result['max_id'] + 1;
            $newCommentId = 'CTL' . str_pad($nextId, 7, '0', STR_PAD_LEFT);
            
            // Thêm bình luận mới
            $stmt = $conn->prepare("
                INSERT INTO cauhoi_nguoitraloi 
                (ID, IDCauHoi, IDNguoiTraLoi, NoiDung, NgayTraLoi) 
                VALUES (?, ?, ?, ?, NOW())
            ");
            
            $stmt->execute([
                $newCommentId,
                $maCH,
                $currentUser['ID'],
                $comment
            ]);
            
            // Chuyển hướng để tránh gửi lại form khi refresh
            header('Location: ' . $_SERVER['REQUEST_URI']);
            exit;
            
        } catch (PDOException $e) {
            error_log("Lỗi thêm bình luận: " . $e->getMessage());
            $errorMessage = "Đã xảy ra lỗi khi thêm bình luận. Vui lòng thử lại sau.";
        }
    }
}
?>

<!-- Form thêm bình luận -->
<div class="card mt-4 mb-4">
    <div class="card-header bg-light">
        <h5 class="mb-0"><i class="fas fa-comment-dots me-2"></i>Thêm bình luận</h5>
    </div>
    <div class="card-body">
        <?php if ($isLoggedIn): ?>
            <form action="" method="post" class="comment-form">
                <div class="form-group mb-3">
                    <div class="input-group">
                        <input type="text" name="sBinhLuan" class="form-control" placeholder="Nhập bình luận của bạn..." required>
                        <input type="hidden" name="sMaCH" value="<?php echo htmlspecialchars($questionId); ?>">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-paper-plane me-1"></i> Đăng
                        </button>
                    </div>
                </div>
                <?php if (isset($errorMessage)): ?>
                    <div class="alert alert-danger mt-2">
                        <i class="fas fa-exclamation-circle me-2"></i><?php echo $errorMessage; ?>
                    </div>
                <?php endif; ?>
            </form>
        <?php else: ?>
            <div class="alert alert-warning">
                <i class="fas fa-exclamation-triangle me-2"></i>
                Bạn cần <a href="../../Login/Index" class="alert-link">đăng nhập</a> để có thể bình luận!
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Hiển thị danh sách bình luận hiện có -->
<div class="comments-section">
    <h5 class="mb-3"><i class="fas fa-comments me-2"></i>Các bình luận</h5>
    
    <?php
    // Lấy danh sách bình luận cho câu hỏi này
    if ($questionId) {
        try {
            $stmt = $conn->prepare("
                SELECT 
                    ctl.ID, 
                    ctl.NoiDung, 
                    ctl.NgayTraLoi,
                    tk.HoTen as TenNguoiTraLoi,
                    tk.ImagePath as AnhDaiDien
                FROM cauhoi_nguoitraloi ctl
                LEFT JOIN taikhoan tk ON ctl.IDNguoiTraLoi = tk.ID
                WHERE ctl.IDCauHoi = ?
                ORDER BY ctl.NgayTraLoi DESC
            ");
            
            $stmt->execute([$questionId]);
            $comments = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            if (count($comments) > 0) {
                foreach ($comments as $comment) {
                    $avatarUrl = !empty($comment['AnhDaiDien']) ? $comment['AnhDaiDien'] : '../../Assets/images/default-avatar.png';
                    $formattedDate = date('d/m/Y H:i', strtotime($comment['NgayTraLoi']));
                    ?>
                    <div class="comment-item card mb-2">
                        <div class="card-body">
                            <div class="d-flex">
                                <div class="avatar me-3">
                                    <img src="<?php echo $avatarUrl; ?>" class="rounded-circle" width="40" height="40" alt="Avatar">
                                </div>
                                <div class="comment-content flex-grow-1">
                                    <div class="d-flex justify-content-between align-items-center mb-2">
                                        <h6 class="mb-0"><?php echo htmlspecialchars($comment['TenNguoiTraLoi']); ?></h6>
                                        <small class="text-muted"><i class="far fa-clock me-1"></i><?php echo $formattedDate; ?></small>
                                    </div>
                                    <p class="mb-0"><?php echo nl2br(htmlspecialchars($comment['NoiDung'])); ?></p>
                                    
                                    <?php if ($isLoggedIn): ?>
                                    <div class="mt-2">
                                        <button class="btn btn-sm btn-outline-primary reply-btn" data-comment-id="<?php echo $comment['ID']; ?>">
                                            <i class="fas fa-reply me-1"></i>Phản hồi
                                        </button>
                                    </div>
                                    
                                    <!-- Form phản hồi cho bình luận này, mặc định ẩn -->
                                    <div class="reply-form mt-3" id="reply-form-<?php echo $comment['ID']; ?>" style="display: none;">
                                        <form action="add_reply.php" method="post">
                                            <div class="input-group">
                                                <input type="text" name="replyContent" class="form-control form-control-sm" placeholder="Phản hồi..." required>
                                                <input type="hidden" name="commentId" value="<?php echo $comment['ID']; ?>">
                                                <input type="hidden" name="questionId" value="<?php echo htmlspecialchars($questionId); ?>">
                                                <button type="submit" class="btn btn-sm btn-primary">Gửi</button>
                                            </div>
                                        </form>
                                    </div>
                                    <?php endif; ?>
                                    
                                    <!-- Hiển thị các phản hồi cho bình luận này -->
                                    <?php
                                    try {
                                        $stmtReplies = $conn->prepare("
                                            SELECT 
                                                r.ID,
                                                r.NoiDung,
                                                r.NgayTraLoi,
                                                tk.HoTen as TenNguoiTraLoi,
                                                tk.ImagePath as AnhDaiDien
                                            FROM cauhoi_nguoitraloi_nguoitraloi r
                                            LEFT JOIN taikhoan tk ON r.IDNguoiTraLoi = tk.ID
                                            WHERE r.IDCauHoi_NguoiTraLoi = ?
                                            ORDER BY r.NgayTraLoi ASC
                                        ");
                                        
                                        $stmtReplies->execute([$comment['ID']]);
                                        $replies = $stmtReplies->fetchAll(PDO::FETCH_ASSOC);
                                        
                                        if (count($replies) > 0) {
                                            echo '<div class="replies mt-3 ps-4 border-start">';
                                            
                                            foreach ($replies as $reply) {
                                                $replyAvatarUrl = !empty($reply['AnhDaiDien']) ? $reply['AnhDaiDien'] : '../../Assets/images/default-avatar.png';
                                                $replyFormattedDate = date('d/m/Y H:i', strtotime($reply['NgayTraLoi']));
                                                ?>
                                                <div class="reply-item mb-2">
                                                    <div class="d-flex">
                                                        <div class="avatar me-2">
                                                            <img src="<?php echo $replyAvatarUrl; ?>" class="rounded-circle" width="30" height="30" alt="Avatar">
                                                        </div>
                                                        <div class="reply-content flex-grow-1">
                                                            <div class="d-flex justify-content-between align-items-center">
                                                                <h6 class="mb-0 small"><?php echo htmlspecialchars($reply['TenNguoiTraLoi']); ?></h6>
                                                                <small class="text-muted"><i class="far fa-clock me-1"></i><?php echo $replyFormattedDate; ?></small>
                                                            </div>
                                                            <p class="mb-0 small"><?php echo nl2br(htmlspecialchars($reply['NoiDung'])); ?></p>
                                                        </div>
                                                    </div>
                                                </div>
                                                <?php
                                            }
                                            
                                            echo '</div>';
                                        }
                                    } catch (PDOException $e) {
                                        error_log("Lỗi lấy phản hồi: " . $e->getMessage());
                                    }
                                    ?>
                                </div>
                            </div>
                        </div>
                    </div>
                    <?php
                }
            } else {
                echo '<div class="alert alert-info"><i class="fas fa-info-circle me-2"></i>Chưa có bình luận nào cho câu hỏi này.</div>';
            }
        } catch (PDOException $e) {
            error_log("Lỗi lấy bình luận: " . $e->getMessage());
            echo '<div class="alert alert-danger"><i class="fas fa-exclamation-triangle me-2"></i>Đã xảy ra lỗi khi tải bình luận.</div>';
        }
    } else {
        echo '<div class="alert alert-warning"><i class="fas fa-exclamation-triangle me-2"></i>Không tìm thấy câu hỏi.</div>';
    }
    ?>
</div>

<!-- JavaScript để xử lý ẩn/hiện form phản hồi -->
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Xử lý sự kiện click vào nút phản hồi
    const replyButtons = document.querySelectorAll('.reply-btn');
    
    replyButtons.forEach(button => {
        button.addEventListener('click', function() {
            const commentId = this.getAttribute('data-comment-id');
            const replyForm = document.getElementById('reply-form-' + commentId);
            
            // Toggle hiển thị form phản hồi
            if (replyForm.style.display === 'none') {
                replyForm.style.display = 'block';
            } else {
                replyForm.style.display = 'none';
            }
        });
    });
});
</script>
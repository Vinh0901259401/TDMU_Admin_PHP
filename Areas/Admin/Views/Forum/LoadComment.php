<?php
// Kết nối database
require_once("../Shared/connect.inc");

// Bắt đầu session nếu chưa có
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Lấy ID câu hỏi từ tham số
$questionId = isset($_GET['questionId']) ? $_GET['questionId'] : null;

// Kiểm tra người dùng hiện tại
$currentUser = isset($_SESSION['TaiKhoan']) ? $_SESSION['TaiKhoan'] : null;


// Kiểm tra ID câu hỏi có hợp lệ không
if (!$questionId) {
    echo '<div class="alert alert-warning">Không tìm thấy ID câu hỏi.</div>';
    exit;
}

try {
    // Truy vấn danh sách bình luận cho câu hỏi
    $stmtComments = $conn->prepare("
        SELECT 
            c.ID as CommentID,
            c.NoiDung as CommentContent,
            c.NgayTraLoi as CommentDate,
            c.IDNguoiTraLoi as UserID,
            u.HoTen as UserName,
            u.ImagePath as UserAvatar,
            COUNT(r.ID) as ReplyCount
        FROM cauhoi_nguoitraloi c
        LEFT JOIN taikhoan u ON c.IDNguoiTraLoi = u.ID
        LEFT JOIN cauhoi_nguoitraloi_nguoitraloi r ON c.ID = r.IDCauHoi_NguoiTraLoi
        WHERE c.IDCauHoi = ?
        GROUP BY c.ID
        ORDER BY c.NgayTraLoi DESC
    ");
    $stmtComments->execute([$questionId]);
    
    // Kiểm tra có bình luận không
    if ($stmtComments->rowCount() == 0) {
        echo '<div class="alert alert-info">
                <i class="fas fa-info-circle me-2"></i>Chưa có bình luận nào cho câu hỏi này.
                '.($currentUser ? 'Hãy là người đầu tiên bình luận!' : '').'
              </div>';
    } else {
        // Hiển thị danh sách bình luận
        while ($comment = $stmtComments->fetch(PDO::FETCH_ASSOC)) {
            // Xử lý ảnh đại diện
            $avatarUrl = '../../Assets/img/user-image/default-avatar.png';
            if (!empty($comment['UserAvatar'])) {
                $avatarUrl = $comment['UserAvatar'];
                if (!preg_match('/^(http|https):\/\//', $avatarUrl) && !preg_match('/^\.\.\//', $avatarUrl)) {
                    $avatarUrl = '../../Assets/img/user-image/' . $avatarUrl;
                }
            }
            
            // Định dạng thời gian đăng bình luận
            $commentDate = date('d/m/Y H:i', strtotime($comment['CommentDate']));
            
            // Bắt đầu hiển thị bình luận
            ?>
            <div class="comment-item bg-light p-3 rounded" id="comment-<?php echo $comment['CommentID']; ?>">
                <div class="d-flex">
                    <!-- Avatar người dùng -->
                    <div class="flex-shrink-0 avatar me-3">
                        <img src="<?php echo htmlspecialchars($avatarUrl); ?>" alt="Avatar" class="rounded-circle" width="40" height="40">
                    </div>
                    
                    <!-- Nội dung bình luận -->
                    <div class="flex-grow-1">
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <h6 class="mb-0"><?php echo htmlspecialchars($comment['UserName']); ?></h6>
                            <small class="text-muted"><?php echo $commentDate; ?></small>
                        </div>
                        
                        <div class="comment-text mb-2">
                            <?php echo nl2br(htmlspecialchars($comment['CommentContent'])); ?>
                        </div>
                        
                        <!-- Nút tương tác -->
                        <div class="comment-actions d-flex justify-content-between">
                            <div>
                                <?php if ($currentUser): ?>
                                <button type="button" class="btn btn-sm btn-outline-primary reply-btn" 
                                        data-comment-id="<?php echo $comment['CommentID']; ?>">
                                    <i class="fas fa-reply me-1"></i> Trả lời
                                </button>
                                <?php endif; ?>
                                
                                <?php if ((int)$comment['ReplyCount'] > 0): ?>
                                <button type="button" class="btn btn-sm btn-link load-replies-btn" 
                                        data-comment-id="<?php echo $comment['CommentID']; ?>">
                                    <i class="fas fa-comments me-1"></i> Xem <?php echo $comment['ReplyCount']; ?> phản hồi
                                </button>
                                <?php endif; ?>
                            </div>
                            
                            <!-- Nút xóa bình luận - CHỈ HIỂN THỊ KHI LÀ TÁC GIẢ CỦA BÌNH LUẬN -->
                            <?php if ($currentUser && $currentUser['ID'] == $comment['UserID']): ?>
                            <div>
                                <button type="button" class="btn btn-sm btn-link text-danger delete-comment-btn" 
                                        data-comment-id="<?php echo $comment['CommentID']; ?>">
                                    <i class="fas fa-trash-alt me-1"></i> Xóa
                                </button>
                            </div>
                            <?php endif; ?>
                        </div>
                        
                        <!-- Form trả lời (ẩn mặc định) -->
                        <?php if ($currentUser): ?>
                        <div id="reply-form-<?php echo $comment['CommentID']; ?>" class="reply-form mt-3" style="display: none;">
                            <div class="input-group">
                                <textarea class="form-control reply-content" rows="2" placeholder="Nhập phản hồi của bạn..."></textarea>
                                <button type="button" class="btn btn-primary send-reply-btn" data-comment-id="<?php echo $comment['CommentID']; ?>">
                                    <i class="fas fa-paper-plane"></i>
                                </button>
                            </div>
                        </div>
                        <?php endif; ?>
                        
                        <!-- Container chứa phản hồi -->
                        <div id="replies-<?php echo $comment['CommentID']; ?>" class="replies-container mt-3">
                            <!-- Phản hồi sẽ được tải bằng AJAX khi người dùng nhấp vào "Xem phản hồi" -->
                        </div>
                    </div>
                </div>
            </div>
            <hr class="my-3">
            <?php
        }
        
        // Script xử lý xóa bình luận
        if ($currentUser): ?>
        <script>
        $(document).ready(function() {
            // Xử lý nút xóa bình luận
            $('.delete-comment-btn').off('click').on('click', function() {
                var commentId = $(this).data('comment-id');
                
                if (confirm('Bạn có chắc chắn muốn xóa bình luận này không? Tất cả phản hồi cũng sẽ bị xóa.')) {
                    $.ajax({
                        url: 'DeleteComment.php',
                        type: 'POST',
                        data: {
                            commentId: commentId
                        },
                        dataType: 'json',
                        success: function(response) {
                            if (response.success) {
                                // Xóa bình luận khỏi DOM
                                $('#comment-' + commentId).next('hr').remove();
                                $('#comment-' + commentId).fadeOut(300, function() {
                                    $(this).remove();
                                    
                                    // Cập nhật số lượng bình luận
                                    var count = $('.comment-item').length;
                                    $('#comments-count').text(count);
                                    
                                    // Hiển thị thông báo nếu không còn bình luận nào
                                    if (count === 0) {
                                        $('#comments-container').html('<div class="alert alert-info"><i class="fas fa-info-circle me-2"></i>Chưa có bình luận nào cho câu hỏi này.</div>');
                                    }
                                });
                                
                                // Hiển thị thông báo thành công
                                toastr.success("Đã xóa bình luận thành công!");
                            } else {
                                // Hiển thị lỗi
                                toastr.error(response.message || "Đã xảy ra lỗi khi xóa bình luận.");
                            }
                        },
                        error: function() {
                            toastr.error("Đã xảy ra lỗi kết nối khi xóa bình luận.");
                        }
                    });
                }
            });
        });
        </script>
        <?php endif;
    }
} catch (PDOException $e) {
    // Ghi log lỗi và hiển thị thông báo
    error_log("Lỗi truy vấn bình luận: " . $e->getMessage());
    echo '<div class="alert alert-danger">
            <i class="fas fa-exclamation-circle me-2"></i>Đã xảy ra lỗi khi tải bình luận. Vui lòng thử lại sau.
          </div>';
}
?>
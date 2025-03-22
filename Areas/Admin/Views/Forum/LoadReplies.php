<?php
// Kết nối database
require_once("../Shared/connect.inc");

// Bắt đầu session nếu chưa có
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Kiểm tra và lấy ID bình luận
$commentId = isset($_GET['commentId']) ? $_GET['commentId'] : null;

// Kiểm tra người dùng hiện tại
$currentUser = isset($_SESSION['TaiKhoan']) ? $_SESSION['TaiKhoan'] : null;


// Kiểm tra ID bình luận có hợp lệ không
if (!$commentId) {
    echo '<div class="alert alert-warning p-2">Không tìm thấy ID bình luận.</div>';
    exit;
}

try {
    // Truy vấn danh sách phản hồi cho bình luận
    $stmtReplies = $conn->prepare("
        SELECT 
            r.ID as ReplyID,
            r.NoiDung as ReplyContent,
            r.NgayTraLoi as ReplyDate,
            r.IDNguoiTraLoi as UserID, 
            u.HoTen as UserName,
            u.ImagePath as UserAvatar
        FROM cauhoi_nguoitraloi_nguoitraloi r
        LEFT JOIN taikhoan u ON r.IDNguoiTraLoi = u.ID
        WHERE r.IDCauHoi_NguoiTraLoi = ?
        ORDER BY r.NgayTraLoi ASC
    ");
    $stmtReplies->execute([$commentId]);
    
    // Kiểm tra có phản hồi không
    if ($stmtReplies->rowCount() == 0) {
        echo '<div class="small text-muted p-2">Chưa có phản hồi nào cho bình luận này.</div>';
    } else {
        // Hiển thị danh sách phản hồi
        while ($reply = $stmtReplies->fetch(PDO::FETCH_ASSOC)) {
            // Xử lý ảnh đại diện
            $avatarUrl = '../../Assets/img/user-image/default-avatar.png';
            if (!empty($reply['UserAvatar'])) {
                $avatarUrl = $reply['UserAvatar'];
                if (!preg_match('/^(http|https):\/\//', $avatarUrl) && !preg_match('/^\.\.\//', $avatarUrl)) {
                    $avatarUrl = '../../Assets/img/user-image/' . $avatarUrl;
                }
            }
            
            // Định dạng thời gian đăng phản hồi
            $replyDate = date('d/m/Y H:i', strtotime($reply['ReplyDate']));
            
            // Bắt đầu hiển thị phản hồi
            ?>
            <div class="reply-item p-2 mb-2 bg-white rounded border-start border-primary" id="reply-<?php echo $reply['ReplyID']; ?>">
                <div class="d-flex">
                    <!-- Avatar người dùng -->
                    <div class="flex-shrink-0 avatar me-2">
                        <img src="<?php echo htmlspecialchars($avatarUrl); ?>" alt="Avatar" class="rounded-circle" width="30" height="30">
                    </div>
                    
                    <!-- Nội dung phản hồi -->
                    <div class="flex-grow-1">
                        <div class="d-flex justify-content-between align-items-center mb-1">
                            <h6 class="mb-0 small fw-bold"><?php echo htmlspecialchars($reply['UserName']); ?></h6>
                            <small class="text-muted" style="font-size: 0.75rem;"><?php echo $replyDate; ?></small>
                        </div>
                        
                        <div class="reply-text small">
                            <?php echo nl2br(htmlspecialchars($reply['ReplyContent'])); ?>
                        </div>
                        
                        <!-- Nút tương tác (xóa) - CHỈ HIỂN THỊ KHI LÀ TÁC GIẢ CỦA PHẢN HỒI -->
                        <?php if ($currentUser && $currentUser['ID'] == $reply['UserID']): ?>
                        <div class="reply-actions mt-1">
                            <button type="button" class="btn btn-sm btn-link text-danger p-0 delete-reply-btn" 
                                    data-reply-id="<?php echo $reply['ReplyID']; ?>"
                                    data-comment-id="<?php echo $commentId; ?>">
                                <i class="fas fa-trash-alt me-1"></i> Xóa
                            </button>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            <?php
        }
        
        // Script xử lý xóa phản hồi
        if ($currentUser): ?>
        <script>
        $(document).ready(function() {
            // Xử lý nút xóa phản hồi
            $('.delete-reply-btn').off('click').on('click', function() {
                var replyId = $(this).data('reply-id');
                var commentId = $(this).data('comment-id');
                
                if (confirm('Bạn có chắc chắn muốn xóa phản hồi này không?')) {
                    $.ajax({
                        url: 'DeleteReply.php',
                        type: 'POST',
                        data: {
                            replyId: replyId,
                            commentId: commentId
                        },
                        dataType: 'json',
                        success: function(response) {
                            if (response.success) {
                                // Xóa phản hồi khỏi DOM
                                $('#reply-' + replyId).fadeOut(300, function() {
                                    $(this).remove();
                                    
                                    // Kiểm tra nếu không còn phản hồi nào
                                    if ($('#replies-' + commentId + ' .reply-item').length === 0) {
                                        $('#replies-' + commentId).html('<div class="small text-muted p-2">Chưa có phản hồi nào cho bình luận này.</div>');
                                    }
                                });
                                
                                // Hiển thị thông báo thành công
                                toastr.success("Đã xóa phản hồi thành công!");
                            } else {
                                // Hiển thị lỗi
                                toastr.error(response.message || "Đã xảy ra lỗi khi xóa phản hồi.");
                            }
                        },
                        error: function() {
                            toastr.error("Đã xảy ra lỗi kết nối khi xóa phản hồi.");
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
    error_log("Lỗi truy vấn phản hồi: " . $e->getMessage());
    echo '<div class="alert alert-danger p-2">
            <i class="fas fa-exclamation-circle me-2"></i>Đã xảy ra lỗi khi tải phản hồi. Vui lòng thử lại sau.
          </div>';
}
?>
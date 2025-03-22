<?php
// filepath: c:\xampp\htdocs\TDMU_website\Areas\Admin\Views\Forum\LoadChildComment.php

// Kết nối database và khởi tạo session nếu cần
require_once("../Shared/connect.inc");

// Lấy ID của bình luận cha từ tham số
$parentCommentId = isset($_GET['commentId']) ? $_GET['commentId'] : null;

// Biến lưu trữ HTML kết quả
$html = '';

if ($parentCommentId) {
    try {
        // Truy vấn lấy danh sách phản hồi cho bình luận
        $stmt = $conn->prepare("
            SELECT 
                r.ID,
                r.NoiDung,
                r.NgayTraLoi,
                tk.HoTen as TenNguoiTraLoi,
                tk.ImagePath as AnhNguoiTraLoi
            FROM cauhoi_nguoitraloi_nguoitraloi r
            LEFT JOIN taikhoan tk ON r.IDNguoiTraLoi = tk.ID
            WHERE r.IDCauHoi_NguoiTraLoi = ?
            ORDER BY r.NgayTraLoi ASC
        ");
        
        $stmt->execute([$parentCommentId]);
        $replies = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Tạo HTML cho mỗi phản hồi
        foreach ($replies as $reply) {
            // Xử lý ảnh đại diện
            $avatar = !empty($reply['AnhNguoiTraLoi']) 
                ? '../../Assets/img/user-image/' . htmlspecialchars($reply['AnhNguoiTraLoi']) 
                : '../../Assets/img/user-image/default-avatar.png';
            
            // Định dạng ngày tháng
            $replyDate = date('d/m/Y H:i', strtotime($reply['NgayTraLoi']));
            
            // Thêm HTML cho phản hồi này
            $html .= '
            <li class="comment reply-item mb-2">
                <div class="d-flex">
                    <div class="flex-shrink-0 me-2">
                        <img class="avatar rounded-circle" src="' . $avatar . '" alt="avatar" width="32" height="32">
                    </div>
                    <div class="flex-grow-1">
                        <div class="d-flex justify-content-between align-items-center">
                            <h6 class="mb-0 small fw-bold">' . htmlspecialchars($reply['TenNguoiTraLoi']) . '</h6>
                            <span class="text-muted small"><i class="far fa-clock me-1"></i>' . $replyDate . '</span>
                        </div>
                        <div class="mt-1 reply-content">' . nl2br(htmlspecialchars($reply['NoiDung'])) . '</div>
                    </div>
                </div>
            </li>';
        }
        
        if (empty($html)) {
            $html = '<li class="text-center text-muted py-2">Chưa có phản hồi</li>';
        }
        
    } catch (PDOException $e) {
        error_log("Lỗi truy vấn phản hồi: " . $e->getMessage());
        $html = '<li class="text-danger">Đã xảy ra lỗi khi tải phản hồi</li>';
    }
} else {
    $html = '<li class="text-warning">Không tìm thấy thông tin bình luận</li>';
}

// Trả về kết quả dưới dạng HTML
header('Content-Type: text/html; charset=utf-8');
echo '<ul class="comment-replies list-unstyled ps-3 border-start">' . $html . '</ul>';
?>

<style>
        /* Style cho các phản hồi bình luận trong LoadChildComment.php */
    .comment-replies {
        margin-top: 10px;
        margin-bottom: 10px;
        border-left-color: #dee2e6 !important;
        padding-left: 15px !important;
    }
    
    .reply-item {
        position: relative;
        padding: 10px 0;
        transition: all 0.2s ease;
    }
    
    .reply-item:not(:last-child) {
        border-bottom: 1px solid rgba(0,0,0,.05);
    }
    
    .reply-item:hover {
        background-color: rgba(0,0,0,.01);
    }
    
    .reply-item .avatar {
        border: 1px solid #e9ecef;
        object-fit: cover;
    }
    
    .reply-content {
        font-size: 0.9rem;
        color: #555;
        white-space: pre-line;
    }
    
    /* Animation khi tải phản hồi */
    @keyframes fadeIn {
        from { opacity: 0; transform: translateY(10px); }
        to { opacity: 1; transform: translateY(0); }
    }
    
    .comment-replies {
        animation: fadeIn 0.3s ease-out forwards;
    }
</style>
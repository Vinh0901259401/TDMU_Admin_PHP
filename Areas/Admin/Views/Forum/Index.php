<?php
// filepath: c:\xampp\htdocs\TDMU_website\Areas\Admin\Views\Forum\Index.php

// Kết nối database và khởi tạo session
require_once("../Shared/connect.inc");
session_start();

// Tiêu đề trang
$pageTitle = "Diễn đàn";

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

// Thiết lập phân trang
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$perPage = 10; // Số mục trên mỗi trang
$offset = ($page - 1) * $perPage;

// Lấy tham số lọc
$topic = isset($_GET['topic']) ? $_GET['topic'] : '';
$filter = isset($_GET['filter']) ? $_GET['filter'] : '';

// Lấy danh sách chủ đề cho dropdown
try {
    $stmtTopics = $conn->prepare("SELECT ID, Ten FROM chudecauhoi ORDER BY Ten");
    $stmtTopics->execute();
    $topicList = $stmtTopics->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Lỗi truy vấn chủ đề: " . $e->getMessage());
    $topicList = [];
}

// Xây dựng câu truy vấn với điều kiện lọc
$whereClause = "ch.DuocDuyet = 1"; // Chỉ lấy câu hỏi đã được duyệt
$params = [];

if (!empty($topic)) {
    $whereClause .= " AND ch_cd.IDChuDeCauHoi = ?";
    $params[] = $topic;
}

// Thiết lập sắp xếp theo bộ lọc
$orderClause = "ch.NgayGui DESC"; // Mặc định sắp xếp theo ngày giảm dần
if (!empty($filter)) {
    switch($filter) {
        case 'date_asc':
            $orderClause = "ch.NgayGui ASC";
            break;
        case 'date_desc':
            $orderClause = "ch.NgayGui DESC";
            break;
        case 'replies':
            $orderClause = "LuotTraLoi DESC, ch.NgayGui DESC";
            break;
        case 'likes':
            $orderClause = "LuotThich DESC, ch.NgayGui DESC";
            break;
    }
}

// Lấy tổng số câu hỏi (để phân trang)
try {
    $countQuery = "
        SELECT COUNT(DISTINCT ch.ID) as total 
        FROM cauhoi ch
        LEFT JOIN cauhoi_chudecauhoi ch_cd ON ch.ID = ch_cd.IDCauHoi
        WHERE $whereClause";
    
    $stmtCount = $conn->prepare($countQuery);
    $stmtCount->execute($params);
    $totalItems = $stmtCount->fetch(PDO::FETCH_ASSOC)['total'];
    $totalPages = ceil($totalItems / $perPage);
} catch (PDOException $e) {
    error_log("Lỗi truy vấn tổng số câu hỏi: " . $e->getMessage());
    $totalItems = 0;
    $totalPages = 1;
}

// Lấy danh sách câu hỏi với các tùy chọn lọc và phân trang
try {
    $query = "
        SELECT 
            ch.ID as IDCauHoi,
            ch.TieuDe,
            ch.NoiDung,
            ch.NgayGui as NgayDang,
            tk.HoTen as NguoiDang,
            cd.Ten as ChuDe,
            (SELECT COUNT(*) FROM cauhoi_nguoitraloi WHERE IDCauHoi = ch.ID) as LuotTraLoi,
            (SELECT COUNT(*) FROM cauhoi_nguoidanhgia WHERE IDCauHoi = ch.ID AND HuuIch = 1) as LuotThich,
            (SELECT COUNT(*) FROM cauhoi_nguoidanhgia WHERE IDCauHoi = ch.ID AND HuuIch = 0) as LuotKhongThich
        FROM cauhoi ch
        LEFT JOIN taikhoan tk ON ch.IDNguoiGui = tk.ID
        LEFT JOIN cauhoi_chudecauhoi ch_cd ON ch.ID = ch_cd.IDCauHoi
        LEFT JOIN chudecauhoi cd ON ch_cd.IDChuDeCauHoi = cd.ID
        WHERE $whereClause
        GROUP BY ch.ID
        ORDER BY $orderClause
        LIMIT $perPage OFFSET $offset
    ";
    
    $stmt = $conn->prepare($query);
    $stmt->execute($params);
    $questions = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Lỗi truy vấn danh sách câu hỏi: " . $e->getMessage());
    $questions = [];
}

// Danh sách lựa chọn lọc
$filterOptions = [
    ['value' => 'date_desc', 'text' => 'Mới nhất'],
    ['value' => 'date_asc', 'text' => 'Cũ nhất'],
    ['value' => 'replies', 'text' => 'Nhiều trả lời nhất'],
    ['value' => 'likes', 'text' => 'Nhiều lượt thích nhất']
];

// Định nghĩa CSS cho trang
$pageStyles = '
<link rel="stylesheet" href="../../Assets/package/Forum/css/forum.css" />
<link href="https://code.ionicframework.com/ionicons/2.0.1/css/ionicons.min.css" rel="stylesheet">
<style>
/* Styles cho trang chủ forum */
.hover-card {
    cursor: pointer;
    transition: all 0.2s ease;
    border-left: 3px solid transparent;
}

.hover-card:hover {
    transform: translateY(-3px);
    border-left: 3px solid #007bff;
    box-shadow: 0 .5rem 1rem rgba(0,0,0,.15)!important;
}

.stat-box {
    padding: 10px;
    border-radius: 5px;
    transition: all 0.3s ease;
}

.stat-box:hover {
    background-color: #f8f9fa;
}

.stat-box i {
    font-size: 1.5rem;
}

/* Pagination styling */
.pagination .page-link {
    color: #007bff;
    border-radius: 3px;
    margin: 0 2px;
}

.pagination .page-item.active .page-link {
    background-color: #007bff;
    border-color: #007bff;
}

/* Sidebar styling */
.badge {
    font-weight: 500;
    padding: 0.5em 0.75em;
}

/* Responsive adjustments */
@media (max-width: 768px) {
    .stat-box {
        padding: 5px;
    }
    
    .stat-box i {
        font-size: 1.2rem;
    }
}
</style>
';

// Bắt đầu buffer đầu ra
ob_start();
?>

<?php if ($accessLevel > 4 || $accessLevel < 1): ?>
    <div class="container-fluid px-4">
        <div class="alert alert-danger my-4 text-center">
            <i class="fas fa-exclamation-triangle fa-3x mb-3"></i>
            <h4>RẤT TIẾC, BẠN KHÔNG CÓ QUYỀN XEM THÔNG TIN TRÊN TRANG NÀY!</h4>
            <h5>Liên hệ người quản trị để được phân quyền hoặc giải đáp các thắc mắc, xin cảm ơn!</h5>
        </div>
    </div>
<?php else: ?>
    <div class="container-fluid px-4">
        <div class="row">
            <!-- Main content -->
            <div class="col-lg-8 mb-4">
                <!-- Header with filter options -->
                <div class="card shadow-sm mb-4">
                    <div class="card-header bg-primary text-white">
                        <h5 class="mb-0"><i class="fas fa-comments me-2"></i>Diễn đàn hỏi đáp</h5>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-6 mb-3 mb-md-0">
                                <label class="form-label fw-bold">Chủ đề:</label>
                                <select id="TopicDropDown" class="form-select">
                                    <option value="">-- Tất cả chủ đề --</option>
                                    <?php foreach ($topicList as $topicItem): ?>
                                        <option value="<?php echo $topicItem['ID']; ?>" <?php echo ($topic == $topicItem['ID']) ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($topicItem['Ten']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-bold">Lọc theo:</label>
                                <select id="FilterDropDown" class="form-select">
                                    <option value="">-- Sắp xếp --</option>
                                    <?php foreach ($filterOptions as $option): ?>
                                        <option value="<?php echo $option['value']; ?>" <?php echo ($filter == $option['value']) ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($option['text']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Questions list -->
                <?php if (count($questions) > 0): ?>
                    <?php foreach ($questions as $item): ?>
                        <div class="card shadow-sm hover-card mb-3 question-item" data-question-id="<?php echo $item['IDCauHoi']; ?>">
                            <div class="card-body">
                                <div class="row">
                                    <div class="col-md-8 mb-3 mb-md-0">
                                        <h5 class="card-title">
                                            <a href="Details.php?id=<?php echo $item['IDCauHoi']; ?>" class="text-decoration-none text-primary">
                                                <?php echo htmlspecialchars($item['TieuDe']); ?>
                                            </a>
                                        </h5>
                                        <div class="text-muted small mb-2">
                                            <i class="far fa-calendar-alt me-1"></i> 
                                            <?php echo date('d/m/Y H:i', strtotime($item['NgayDang'])); ?> 
                                            <span class="mx-1">•</span>
                                            <i class="far fa-user me-1"></i> 
                                            <?php echo htmlspecialchars($item['NguoiDang']); ?>
                                        </div>
                                        <div class="card-text mb-2">
                                            <?php 
                                                // Hiển thị một phần nội dung (giới hạn 150 ký tự)
                                                $contentExcerpt = strip_tags($item['NoiDung']);
                                                echo htmlspecialchars(mb_substr($contentExcerpt, 0, 150, 'UTF-8')) . 
                                                     (mb_strlen($contentExcerpt, 'UTF-8') > 150 ? '...' : '');
                                            ?>
                                        </div>
                                        <div class="text-muted small">
                                            <span class="badge bg-secondary me-1">
                                                <i class="fas fa-tag me-1"></i><?php echo htmlspecialchars($item['ChuDe']); ?>
                                            </span>
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="row text-center">
                                            <div class="col-4">
                                                <div class="stat-box">
                                                    <i class="fas fa-thumbs-up text-success"></i>
                                                    <div class="small mt-1"><?php echo $item['LuotThich']; ?> thích</div>
                                                </div>
                                            </div>
                                            <div class="col-4">
                                                <div class="stat-box">
                                                    <i class="fas fa-thumbs-down text-danger"></i>
                                                    <div class="small mt-1"><?php echo $item['LuotKhongThich']; ?> không thích</div>
                                                </div>
                                            </div>
                                            <div class="col-4">
                                                <div class="stat-box">
                                                    <i class="fas fa-comments text-info"></i>
                                                    <div class="small mt-1"><?php echo $item['LuotTraLoi']; ?> trả lời</div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                    
                    <!-- Pagination -->
                    <div class="mt-4">
                        <nav aria-label="Phân trang">
                            <ul class="pagination justify-content-center">
                                <?php if ($page > 1): ?>
                                    <li class="page-item">
                                        <a class="page-link" href="?page=<?php echo ($page - 1); ?><?php echo !empty($topic) ? '&topic=' . urlencode($topic) : ''; ?><?php echo !empty($filter) ? '&filter=' . urlencode($filter) : ''; ?>">
                                            <i class="fas fa-chevron-left"></i>
                                        </a>
                                    </li>
                                <?php endif; ?>
                                
                                <?php
                                    // Hiển thị các nút trang
                                    $start = max(1, $page - 2);
                                    $end = min($totalPages, $page + 2);
                                    
                                    // Hiển thị nút trang đầu nếu cần
                                    if ($start > 1) {
                                        echo '<li class="page-item"><a class="page-link" href="?page=1' . 
                                             (!empty($topic) ? '&topic=' . urlencode($topic) : '') . 
                                             (!empty($filter) ? '&filter=' . urlencode($filter) : '') . 
                                             '">1</a></li>';
                                        
                                        if ($start > 2) {
                                            echo '<li class="page-item disabled"><span class="page-link">...</span></li>';
                                        }
                                    }
                                    
                                    // Hiển thị các trang
                                    for ($i = $start; $i <= $end; $i++) {
                                        echo '<li class="page-item ' . ($i == $page ? 'active' : '') . '">';
                                        echo '<a class="page-link" href="?page=' . $i . 
                                             (!empty($topic) ? '&topic=' . urlencode($topic) : '') . 
                                             (!empty($filter) ? '&filter=' . urlencode($filter) : '') . 
                                             '">' . $i . '</a>';
                                        echo '</li>';
                                    }
                                    
                                    // Hiển thị nút trang cuối nếu cần
                                    if ($end < $totalPages) {
                                        if ($end < $totalPages - 1) {
                                            echo '<li class="page-item disabled"><span class="page-link">...</span></li>';
                                        }
                                        
                                        echo '<li class="page-item"><a class="page-link" href="?page=' . $totalPages . 
                                             (!empty($topic) ? '&topic=' . urlencode($topic) : '') . 
                                             (!empty($filter) ? '&filter=' . urlencode($filter) : '') . 
                                             '">' . $totalPages . '</a></li>';
                                    }
                                ?>
                                
                                <?php if ($page < $totalPages): ?>
                                    <li class="page-item">
                                        <a class="page-link" href="?page=<?php echo ($page + 1); ?><?php echo !empty($topic) ? '&topic=' . urlencode($topic) : ''; ?><?php echo !empty($filter) ? '&filter=' . urlencode($filter) : ''; ?>">
                                            <i class="fas fa-chevron-right"></i>
                                        </a>
                                    </li>
                                <?php endif; ?>
                            </ul>
                        </nav>
                        <div class="text-center text-muted small">
                            Trang <?php echo $page; ?> / <?php echo $totalPages; ?> (<?php echo $totalItems; ?> câu hỏi)
                        </div>
                    </div>
                <?php else: ?>
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle me-2"></i> Không tìm thấy câu hỏi nào.
                        <?php if (!empty($topic) || !empty($filter)): ?>
                            <a href="index.php" class="alert-link">Xóa bộ lọc</a> để xem tất cả câu hỏi.
                        <?php else: ?>
                            Hãy <a href="Create.php" class="alert-link">đặt câu hỏi đầu tiên</a>.
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            </div>
            
            <!-- Sidebar content -->
            <div class="col-lg-4">
                <?php include('sidebar.php'); ?>
                
                <!-- New Question Button (Floating) -->
                <div class="position-sticky mb-4" style="top: 80px">
                    <div class="d-grid gap-2">
                       
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <script>
    $(document).ready(function() {
        // Xử lý thay đổi dropdown filter và topic
        $('#TopicDropDown, #FilterDropDown').on('change', function() {
            var topic = $('#TopicDropDown').val();
            var filter = $('#FilterDropDown').val();
            
            var url = 'Index.php';
            var params = [];
            
            if (topic) {
                params.push('topic=' + topic);
            }
            
            if (filter) {
                params.push('filter=' + filter);
            }
            
            if (params.length > 0) {
                url += '?' + params.join('&');
            }
            
            window.location.href = url;
        });
        
        // Xử lý click vào câu hỏi để chuyển đến trang chi tiết
        $('.question-item').on('click', function(e) {
            if (!$(e.target).is('a')) { // Nếu không nhấp vào thẻ a
                var id = $(this).data('question-id');
                window.location.href = 'Details.php?id=' + id;
            }
        });
    });
    </script>
<?php endif; ?>

<?php
// Lấy nội dung đã buffer
$contentForLayout = ob_get_clean();

// Include template chung
include("../Shared/_LayoutAdmin.php");
?>
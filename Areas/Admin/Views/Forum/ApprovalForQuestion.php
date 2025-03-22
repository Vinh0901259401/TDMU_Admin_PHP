<?php
// filepath: c:\xampp\htdocs\TDMU_website\Areas\Admin\Views\Forum\ApprovalForQuestion.php

// Kết nối đến database
require_once("../Shared/connect.inc");
session_start();

// Tiêu đề trang
$pageTitle = "Phê duyệt câu hỏi";

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
$itemsPerPage = 10;
$offset = ($page - 1) * $itemsPerPage;

// Lấy tham số lọc
$topic = isset($_GET['topic']) ? $_GET['topic'] : '';
$filter = isset($_GET['filter']) ? $_GET['filter'] : '';

// Xây dựng câu truy vấn với điều kiện lọc
$whereClause = "ch.DuocDuyet = 0"; // Lấy các câu hỏi chưa được duyệt
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
        // Thêm các trường hợp lọc khác nếu cần
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
    $totalPages = ceil($totalItems / $itemsPerPage);
} catch (PDOException $e) {
    error_log("Lỗi đếm số câu hỏi: " . $e->getMessage());
    $totalItems = 0;
    $totalPages = 0;
}

// Lấy danh sách câu hỏi
$questions = [];
try {
    $query = "
        SELECT 
            ch.ID as IDCauHoi,
            ch.TieuDe,
            ch.NoiDung,
            ch.NgayGui as NgayDang,
            tk.HoTen as NguoiDang,
            GROUP_CONCAT(cd.Ten SEPARATOR ', ') as ChuDe
        FROM cauhoi ch
        LEFT JOIN taikhoan tk ON ch.IDNguoiGui = tk.ID
        LEFT JOIN cauhoi_chudecauhoi ch_cd ON ch.ID = ch_cd.IDCauHoi
        LEFT JOIN chudecauhoi cd ON ch_cd.IDChuDeCauHoi = cd.ID
        WHERE $whereClause
        GROUP BY ch.ID
        ORDER BY $orderClause
        LIMIT $itemsPerPage OFFSET $offset";
    
    $stmt = $conn->prepare($query);
    $stmt->execute($params);
    $questions = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Lỗi truy vấn câu hỏi: " . $e->getMessage());
}

// Lấy danh sách chủ đề cho dropdown
try {
    $stmtTopics = $conn->prepare("SELECT ID, Ten FROM chudecauhoi ORDER BY Ten");
    $stmtTopics->execute();
    $topicList = $stmtTopics->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Lỗi truy vấn danh sách chủ đề: " . $e->getMessage());
    $topicList = [];
}

// Thiết lập danh sách bộ lọc
$filterList = [
    ['value' => 'date_desc', 'text' => 'Mới nhất'],
    ['value' => 'date_asc', 'text' => 'Cũ nhất']
];

// Bắt đầu buffer đầu ra
ob_start();
?>

<!-- CSS -->
<link rel="stylesheet" href="../../Assets/package/Forum/css/forum.css" />
<link href="https://code.ionicframework.com/ionicons/2.0.1/css/ionicons.min.css" rel="stylesheet">
<link href="https://maxcdn.bootstrapcdn.com/font-awesome/4.2.0/css/font-awesome.min.css" rel="stylesheet">

<?php if ($accessLevel > 4 || $accessLevel < 1): ?>
    <div class="container-fluid px-4">
        <div class="alert alert-danger my-4 text-center">
            <i class="fas fa-exclamation-triangle fa-3x mb-3"></i>
            <h2 style="color: red;">RẤT TIẾC, BẠN KHÔNG CÓ QUYỀN XEM THÔNG TIN TRÊN TRANG NÀY!</h2>
            <h4>Liên hệ người quản trị để được phân quyền hoặc giải đáp các thắc mắc, xin cảm ơn!</h4>
        </div>
    </div>
<?php else: ?>
    <div class="container-fluid px-4">
        <section>
            <div class="row">
                <div class="col-lg m-4">
                    <h4>PHÊ DUYỆT CÂU HỎI CỦA SINH VIÊN</h4>
                </div>
            </div>
            <div class="row">
                <!-- Main content -->
                <div class="col-lg-8 m-4">
                    <div class="row text-left mb-5">
                        <div class="col-lg-6 mb-3 mb-sm-0">
                            <h4>Chủ đề:</h4>
                            <select class="form-control" id="TopicDropDown" name="topic">
                                <option value="">-- Tất cả chủ đề --</option>
                                <?php foreach ($topicList as $topicItem): ?>
                                    <option value="<?php echo $topicItem['ID']; ?>" <?php echo ($topic == $topicItem['ID']) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($topicItem['Ten']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-lg-6 text-lg-right">
                            <h4>Lọc theo:</h4>
                            <select class="form-control" id="FilterDropDown" name="filter">
                                <option value="">-- Chọn bộ lọc --</option>
                                <?php foreach ($filterList as $filterItem): ?>
                                    <option value="<?php echo $filterItem['value']; ?>" <?php echo ($filter == $filterItem['value']) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($filterItem['text']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>

                    <!-- Hiển thị danh sách câu hỏi -->
                    <?php if (count($questions) > 0): ?>
                        <?php foreach ($questions as $item): ?>
                            <div class="card row-hover pos-relative py-3 px-3 mb-3 border-warning border-top-0 border-right-0 border-bottom-0 rounded-0 mc-question-item">
                                <div class="row align-items-center">
                                    <div class="col-md-8 mb-3 mb-sm-0">
                                        <p hidden class="mc-question-key"><?php echo $item['IDCauHoi']; ?></p>
                                        <h5>
                                            <a href="#" class="text-primary"><?php echo htmlspecialchars($item['TieuDe']); ?></a>
                                        </h5>
                                        <p class="text-sm">
                                            <span class="op-6">Ngày đăng: </span>
                                            <a class="text-black" href="#"><?php echo date('d/m/Y H:i', strtotime($item['NgayDang'])); ?></a>
                                            <span class="op-6">bởi: </span>
                                            <a class="text-black" href="#"><?php echo htmlspecialchars($item['NguoiDang']); ?></a>
                                        </p>
                                        <p><?php echo $item['NoiDung']; ?></p>
                                        <div class="text-sm op-5">
                                            <a class="text-black mr-2" href="#">Chủ đề: <?php echo htmlspecialchars($item['ChuDe'] ?? 'Chưa phân loại'); ?></a>
                                        </div>
                                    </div>
                                    <div class="col-md-4 op-7">
                                        <div class="row text-center op-7">
                                            <!-- Nút phê duyệt -->
                                            <button type="button" class="btn btn-primary mb-2 approve-btn" 
                                                    data-question-id="<?php echo $item['IDCauHoi']; ?>">
                                                <i class="fas fa-check me-2"></i>Phê duyệt
                                            </button>
                                            <!-- Nút xóa -->
                                            <button type="button" class="btn btn-warning mt-2 deny-btn"
                                                    data-question-id="<?php echo $item['IDCauHoi']; ?>">
                                                <i class="fas fa-trash me-2"></i>Xóa
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                        
                        <!-- Phân trang -->
                        <div class="mt-4 d-flex justify-content-between align-items-center">
                            <div>Trang <?php echo $page; ?>/<?php echo $totalPages; ?></div>
                            <nav aria-label="Page navigation">
                                <ul class="pagination">
                                    <?php if ($page > 1): ?>
                                        <li class="page-item">
                                            <a class="page-link" href="?page=<?php echo ($page - 1); ?><?php echo !empty($topic) ? '&topic=' . urlencode($topic) : ''; ?><?php echo !empty($filter) ? '&filter=' . urlencode($filter) : ''; ?>">
                                                &laquo;
                                            </a>
                                        </li>
                                    <?php endif; ?>
                                    
                                    <?php for ($i = max(1, $page - 2); $i <= min($totalPages, $page + 2); $i++): ?>
                                        <li class="page-item <?php echo ($i == $page) ? 'active' : ''; ?>">
                                            <a class="page-link" href="?page=<?php echo $i; ?><?php echo !empty($topic) ? '&topic=' . urlencode($topic) : ''; ?><?php echo !empty($filter) ? '&filter=' . urlencode($filter) : ''; ?>">
                                                <?php echo $i; ?>
                                            </a>
                                        </li>
                                    <?php endfor; ?>
                                    
                                    <?php if ($page < $totalPages): ?>
                                        <li class="page-item">
                                            <a class="page-link" href="?page=<?php echo ($page + 1); ?><?php echo !empty($topic) ? '&topic=' . urlencode($topic) : ''; ?><?php echo !empty($filter) ? '&filter=' . urlencode($filter) : ''; ?>">
                                                &raquo;
                                            </a>
                                        </li>
                                    <?php endif; ?>
                                </ul>
                            </nav>
                        </div>
                    <?php else: ?>
                        <div class="alert alert-info">
                            <i class="fas fa-info-circle me-2"></i>Không có câu hỏi nào đang chờ duyệt.
                        </div>
                    <?php endif; ?>
                </div>
                
                <!-- Sidebar content -->
                <div class="col-lg-3 mb-4 mb-lg-0 px-lg-0 mt-lg-0">
                    <?php include('Sidebar.php'); ?>
                </div>
            </div>
        </section>
    </div>

    <!-- Modal xác nhận phê duyệt -->
    <div class="modal fade" id="approveModal" tabindex="-1" aria-labelledby="approveModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="approveModalLabel">Xác nhận phê duyệt</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    Bạn đồng ý phê duyệt câu hỏi này?
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-primary" id="confirmApprove">Đồng ý</button>
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Hủy</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal xác nhận xóa -->
    <div class="modal fade" id="denyModal" tabindex="-1" aria-labelledby="denyModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="denyModalLabel">Xác nhận xóa câu hỏi</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    Bạn muốn xóa câu hỏi này khỏi danh sách phê duyệt?
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-danger" id="confirmDeny">Xóa</button>
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Hủy</button>
                </div>
            </div>
        </div>
    </div>
    
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        // Xử lý thay đổi dropdown filter và topic
        document.getElementById('TopicDropDown').addEventListener('change', function() {
            updateFilters();
        });
        
        document.getElementById('FilterDropDown').addEventListener('change', function() {
            updateFilters();
        });
        
        function updateFilters() {
            var topic = document.getElementById('TopicDropDown').value;
            var filter = document.getElementById('FilterDropDown').value;
            
            var url = 'ApprovalForQuestion.php';
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
        }
        
        // Xử lý phê duyệt câu hỏi
        var approveButtons = document.querySelectorAll(".approve-btn");
        approveButtons.forEach(function(button) {
            button.addEventListener('click', function() {
                var id = this.getAttribute('data-question-id');
                
                // Lưu ID câu hỏi để sử dụng khi xác nhận
                document.getElementById('approveModal').setAttribute('data-question-id', id);
                
                // Hiển thị modal xác nhận
                var approveModal = new bootstrap.Modal(document.getElementById('approveModal'));
                approveModal.show();
            });
        });
        
        // Xử lý xác nhận phê duyệt
        document.getElementById('confirmApprove').addEventListener('click', function() {
            var modal = document.getElementById('approveModal');
            var id = modal.getAttribute('data-question-id');
            
            // Gửi AJAX request để phê duyệt
            var xhr = new XMLHttpRequest();
            xhr.open('POST', 'ApproveQuestion.php', true);
            xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
            xhr.onload = function() {
                if (xhr.status === 200) {
                    var response = JSON.parse(xhr.responseText);
                    alert(response.msg);
                    if (response.code === 200) {
                        window.location.reload();
                    }
                } else {
                    alert('Đã xảy ra lỗi trong quá trình xử lý');
                }
            };
            xhr.send('maCH=' + encodeURIComponent(id));
            
            // Đóng modal
            var bsModal = bootstrap.Modal.getInstance(modal);
            bsModal.hide();
        });
        
        // Xử lý xóa câu hỏi
        var denyButtons = document.querySelectorAll(".deny-btn");
        denyButtons.forEach(function(button) {
            button.addEventListener('click', function() {
                var id = this.getAttribute('data-question-id');
                
                // Lưu ID câu hỏi để sử dụng khi xác nhận
                document.getElementById('denyModal').setAttribute('data-question-id', id);
                
                // Hiển thị modal xác nhận
                var denyModal = new bootstrap.Modal(document.getElementById('denyModal'));
                denyModal.show();
            });
        });
        
        // Xử lý xác nhận xóa
        document.getElementById('confirmDeny').addEventListener('click', function() {
            var modal = document.getElementById('denyModal');
            var id = modal.getAttribute('data-question-id');
            
            // Gửi AJAX request để xóa
            var xhr = new XMLHttpRequest();
            xhr.open('POST', 'DenyQuestion.php', true);
            xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
            xhr.onload = function() {
                if (xhr.status === 200) {
                    var response = JSON.parse(xhr.responseText);
                    alert(response.msg);
                    if (response.code === 200) {
                        window.location.reload();
                    }
                } else {
                    alert('Đã xảy ra lỗi trong quá trình xử lý');
                }
            };
            xhr.send('maCH=' + encodeURIComponent(id));
            
            // Đóng modal
            var bsModal = bootstrap.Modal.getInstance(modal);
            bsModal.hide();
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
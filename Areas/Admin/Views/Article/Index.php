<?php
// Kết nối database
require_once("../Shared/connect.inc");

// Tiêu đề trang
$pageTitle = "Quản lý bài viết";

// Hàm để cắt chuỗi giới hạn độ dài
function truncate($string, $length) {
    if (strlen($string) <= $length) {
        return $string;
    } else {
        return substr($string, 0, $length) . '...';
    }
}

// Lấy thông tin người dùng từ session
session_start();
$accessLevel = 0;
$tk = isset($_SESSION['TaiKhoan']) ? $_SESSION['TaiKhoan'] : null;

// Kiểm tra quyền truy cập
if ($tk && isset($tk['IDQuyenTruyCap'])) {
    try {
        $stmt = $conn->prepare("SELECT CapDo FROM quyentruycap WHERE ID = ?");
        $stmt->execute([$tk['IDQuyenTruyCap']]);
        
        if ($stmt->rowCount() > 0) {
            $quyen = $stmt->fetch(PDO::FETCH_ASSOC);
            $accessLevel = $quyen['CapDo'];
        }
    } catch (PDOException $e) {
        // Xử lý lỗi nếu cần
        error_log("Lỗi truy vấn quyền truy cập: " . $e->getMessage());
    }
}

// Xử lý tham số tìm kiếm từ URL
$search = isset($_GET['search']) ? trim($_GET['search']) : '';

// Xử lý phân trang
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$recordsPerPage = 10; // Số bài viết trên mỗi trang
$start = ($page - 1) * $recordsPerPage;

// Bắt đầu output buffer
ob_start();
?>

<!-- Phần nội dung trang -->
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
<?php else: ?>
    <div class="container-fluid px-4">
        <div class="row">
            <div class="col-12">
                <div class="card shadow-sm my-4">
                    <div class="card-header bg-primary text-white py-3">
                        <h2 class="text-center mb-0">QUẢN LÝ THÔNG TIN BÀI VIẾT</h2>
                    </div>
                    
                    <div class="card-body">
                        <!-- Thanh công cụ -->
                        <div class="d-flex justify-content-between align-items-center mb-4">
                            <a href="/TDMU_website/Areas/Admin/Views/Article/Create.php" class="btn btn-success">
                                <i class="fas fa-plus me-2"></i>Thêm mới bài viết
                            </a>
                            
                            <!-- Form tìm kiếm -->
                            <div class="d-none d-md-block">
                                <form class="d-flex" method="GET">
                                    <input class="form-control me-2" type="search" placeholder="Tìm kiếm bài viết..." 
                                           name="search" value="<?php echo htmlspecialchars($search); ?>">
                                    <button class="btn btn-outline-primary" type="submit">Tìm</button>
                                </form>
                            </div>
                        </div>
                        
                        <?php if (!empty($search)): ?>
                            <div class="alert alert-info mb-4">
                                <i class="fas fa-search me-2"></i>Kết quả tìm kiếm cho: <strong>"<?php echo htmlspecialchars($search); ?>"</strong>
                            </div>
                        <?php endif; ?>
                        
                        <!-- Bảng dữ liệu -->
                        <div class="table-responsive">
                            <table class="table table-bordered table-hover table-striped">
                                <thead class="table-dark">
                                    <tr>
                                        <th style="text-align:center; width:10%">Thể loại</th>
                                        <th style="text-align:center; width:20%">Tiêu đề</th>
                                        <th style="text-align:center; width:20%">Nội dung</th>
                                        <th style="text-align:center; width:20%">Người đăng</th>
                                        <th style="text-align:center; width:10%">Ngày cập nhật</th>
                                        <th style="text-align:center; width:20%">Thao tác</th>
                                    </tr>
                                </thead>
                                <tbody>
                                <?php
                                    try {
                                        // Chuẩn bị câu truy vấn cơ sở và tham số
                                        $params = [];
                                        $baseQuery = "
                                            FROM 
                                                baiviet bv
                                                LEFT JOIN tlbaiviet tlbv ON bv.IDTLBaiViet = tlbv.ID
                                                LEFT JOIN taikhoan tk ON bv.IDNguoiDang = tk.ID
                                            WHERE 1=1";
                                        
                                        // Thêm điều kiện tìm kiếm nếu có
                                        if (!empty($search)) {
                                            $baseQuery .= " AND (bv.TieuDe LIKE ? OR bv.NoiDung LIKE ? OR tlbv.Ten LIKE ? OR tk.HoTen LIKE ?)";
                                            $searchParam = "%{$search}%";
                                            $params[] = $searchParam; // Tìm theo tiêu đề
                                            $params[] = $searchParam; // Tìm theo nội dung
                                            $params[] = $searchParam; // Tìm theo thể loại
                                            $params[] = $searchParam; // Tìm theo người đăng
                                        }
                                        
                                        // Đếm tổng số bản ghi cho phân trang
                                        $countQuery = "SELECT COUNT(*) as total " . $baseQuery;
                                        $countStmt = $conn->prepare($countQuery);
                                        
                                        // Bind tham số cho câu truy vấn đếm
                                        if (!empty($search)) {
                                            for ($i = 0; $i < count($params); $i++) {
                                                $countStmt->bindValue($i + 1, $params[$i], PDO::PARAM_STR);
                                            }
                                        }
                                        
                                        $countStmt->execute();
                                        $totalRecords = $countStmt->fetch(PDO::FETCH_ASSOC)['total'];
                                        
                                        // Truy vấn danh sách bài viết với phân trang
                                        $query = "
                                            SELECT 
                                                bv.ID, 
                                                bv.TieuDe, 
                                                bv.NoiDung, 
                                                tlbv.Ten AS TenTheLoai,
                                                bv.IDNguoiDang,
                                                tk.HoTen AS NguoiDang,
                                                (SELECT MAX(bvncs.NgayChinhSua) 
                                                FROM baiviet_nguoichinhsua bvncs 
                                                WHERE bvncs.IDBaiViet = bv.ID) AS NgayChinhSua
                                            " . $baseQuery . "
                                            ORDER BY bv.ID DESC
                                            LIMIT ?, ?
                                            ";
                                        
                                        $stmt = $conn->prepare($query);
                                        
                                        // Bind tham số cho câu truy vấn chính
                                        $paramIndex = 1;
                                        
                                        // Bind tham số tìm kiếm (nếu có)
                                        if (!empty($search)) {
                                            for ($i = 0; $i < count($params); $i++) {
                                                $stmt->bindValue($paramIndex++, $params[$i], PDO::PARAM_STR);
                                            }
                                        }
                                        
                                        // Bind tham số phân trang
                                        $stmt->bindValue($paramIndex++, $start, PDO::PARAM_INT);
                                        $stmt->bindValue($paramIndex++, $recordsPerPage, PDO::PARAM_INT);
                                        
                                        $stmt->execute();
                                        $articles = $stmt->fetchAll(PDO::FETCH_ASSOC);
                                        
                                        if (count($articles) > 0) {
                                            foreach ($articles as $article) {
                                                echo "<tr>";
                                                echo "<td>" . htmlspecialchars($article['TenTheLoai'] ?? '') . "</td>";
                                                echo "<td>" . truncate(htmlspecialchars($article['TieuDe'] ?? ''), 50) . "</td>";
                                                echo "<td>" . truncate(strip_tags($article['NoiDung'] ?? ''), 70) . "</td>";
                                                echo "<td>" . htmlspecialchars($article['NguoiDang'] ?? '') . "</td>";
                                                echo "<td>" . (isset($article['NgayChinhSua']) && $article['NgayChinhSua'] ? date('d/m/Y', strtotime($article['NgayChinhSua'])) : '') . "</td>";
                                                echo "<td class='text-center'>";
                                                echo "<div class='btn-group'>";
                                                echo "<a href='/TDMU_website/Areas/Admin/Views/Article/Edit.php?MaBV=" . $article['ID'] . "' class='btn btn-warning btn-sm'>Sửa</a>";
                                                echo "<a href='/TDMU_website/Areas/Admin/Views/Article/Details.php?MaBV=" . $article['ID'] . "' class='btn btn-primary btn-sm mx-1'>Chi tiết</a>";
                                                echo "<a href='/TDMU_website/Areas/Admin/Views/Article/Delete.php?MaBV=" . $article['ID'] . "' class='btn btn-danger btn-sm' onclick=\"return confirm('Bạn có chắc muốn xóa bài viết này?');\">Xóa</a>";
                                                echo "</div>";
                                                echo "</td>";
                                                echo "</tr>";
                                            }
                                        } else {
                                            $colSpan = 6;
                                            if (!empty($search)) {
                                                echo "<tr><td colspan='{$colSpan}' class='text-center py-3'>Không tìm thấy bài viết nào với từ khóa <strong>\"{$search}\"</strong></td></tr>";
                                            } else {
                                                echo "<tr><td colspan='{$colSpan}' class='text-center py-3'>Không có bài viết nào</td></tr>";
                                            }
                                        }
                                    } catch (PDOException $e) {
                                        echo "<tr><td colspan='6' class='text-danger text-center py-3'>Lỗi truy vấn dữ liệu: " . $e->getMessage() . "</td></tr>";
                                    }
                                ?>
                                </tbody>
                            </table>
                        </div>
                        
                        <!-- Phân trang -->
                        <?php if ($totalRecords > 0): ?>
                        <div class="d-flex justify-content-between align-items-center mt-4">
                            <div class="text-muted">
                                Hiển thị <?php echo count($articles); ?> / <?php echo $totalRecords; ?> bài viết
                                <?php if(!empty($search)): ?>
                                    <span class="text-primary">(Kết quả tìm kiếm)</span>
                                <?php endif; ?>
                                (Trang <?php echo $page; ?> / <?php echo ceil($totalRecords / $recordsPerPage); ?>)
                            </div>
                            <nav aria-label="Page navigation">
                                <ul class="pagination mb-0">
                                    <?php
                                    $totalPages = ceil($totalRecords / $recordsPerPage);
                                    
                                    // Xây dựng URL cơ bản cho phân trang, bao gồm tham số tìm kiếm
                                    $basePageUrl = '?';
                                    if (!empty($search)) {
                                        $basePageUrl .= 'search=' . urlencode($search) . '&';
                                    }
                                    
                                    // Previous button
                                    if ($page > 1) {
                                        echo "<li class='page-item'><a class='page-link' href='{$basePageUrl}page=" . ($page - 1) . "'>«</a></li>";
                                    } else {
                                        echo "<li class='page-item disabled'><a class='page-link'>«</a></li>";
                                    }
                                    
                                    // Page numbers
                                    for ($i = max(1, $page - 2); $i <= min($page + 2, $totalPages); $i++) {
                                        if ($i == $page) {
                                            echo "<li class='page-item active'><a class='page-link'>" . $i . "</a></li>";
                                        } else {
                                            echo "<li class='page-item'><a class='page-link' href='{$basePageUrl}page=" . $i . "'>" . $i . "</a></li>";
                                        }
                                    }
                                    
                                    // Next button
                                    if ($page < $totalPages) {
                                        echo "<li class='page-item'><a class='page-link' href='{$basePageUrl}page=" . ($page + 1) . "'>»</a></li>";
                                    } else {
                                        echo "<li class='page-item disabled'><a class='page-link'>»</a></li>";
                                    }
                                    ?>
                                </ul>
                            </nav>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <style>
        /* Tùy chỉnh style để bảng rộng hơn và dễ đọc */
        .table-responsive {
            overflow-x: auto;
            -webkit-overflow-scrolling: touch;
        }
        
        .table {
            width: 100%;
            margin-bottom: 0;
        }
        
        .table th, .table td {
            vertical-align: middle;
            padding: 0.75rem;
        }
        
        .btn-group .btn {
            border-radius: 0.25rem;
        }
        
        .pagination {
            margin-bottom: 0;
        }
        
        .pagination .page-link {
            color: #007bff;
            background-color: #fff;
            border: 1px solid #dee2e6;
        }
        
        .pagination .page-item.active .page-link {
            color: #fff;
            background-color: #007bff;
            border-color: #007bff;
        }
        
        /* Responsive styling */
        @media (max-width: 992px) {
            .btn-group {
                display: flex;
                flex-direction: column;
            }
            
            .btn-group .btn {
                margin: 2px 0;
                width: 100%;
            }
            
            .btn-group .mx-1 {
                margin-left: 0 !important;
                margin-right: 0 !important;
            }
        }
        
        @media (max-width: 768px) {
            .table th, .table td {
                padding: 0.5rem;
            }
            
            .btn-sm {
                padding: 0.25rem 0.5rem;
                font-size: 0.75rem;
            }
        }
    </style>
<?php endif; ?>

<?php
// Lấy nội dung đã buffer và đưa vào layout
$contentForLayout = ob_get_clean();

// Kết nối với layout
include_once('../Shared/_LayoutAdmin.php');
?>
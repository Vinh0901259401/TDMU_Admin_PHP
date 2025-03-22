<?php
// Kết nối database
require_once("../Shared/connect.inc");

// Tiêu đề trang
$pageTitle = "Danh sách các lớp";

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
        error_log("Lỗi truy vấn quyền truy cập: " . $e->getMessage());
    }
}

// Xử lý tham số tìm kiếm và lọc từ URL
$maKhoa = isset($_GET['MaKhoa']) ? $_GET['MaKhoa'] : '';
$maNganh = isset($_GET['MaNganh']) ? $_GET['MaNganh'] : '';
$search = isset($_GET['strSearch']) ? trim($_GET['strSearch']) : '';

// Xử lý phân trang
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$recordsPerPage = 10; // Số lớp trên mỗi trang
$start = ($page - 1) * $recordsPerPage;

// Lấy danh sách khoa cho dropdown
$dsKhoa = [];
try {
    $stmt = $conn->query("SELECT ID, Ten FROM khoa ORDER BY Ten");
    $dsKhoa = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Lỗi truy vấn danh sách khoa: " . $e->getMessage());
}

// Lấy danh sách nhóm ngành cho dropdown
$dsNhomNganh = [];
try {
    $stmt = $conn->query("SELECT ID, Ten FROM nhomnganh ORDER BY Ten");
    $dsNhomNganh = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Lỗi truy vấn danh sách nhóm ngành: " . $e->getMessage());
}

// Truy vấn danh sách lớp với điều kiện lọc
$dsLop = [];
$totalRecords = 0;
try {
    // Kiểm tra xem bảng lớp có tồn tại không
    $checkTable = $conn->query("SHOW TABLES LIKE 'lop'");
    if ($checkTable->rowCount() == 0) {
        throw new Exception("Bảng lớp không tồn tại trong database!");
    }
    
    // Kiểm tra cấu trúc bảng lớp - loại bỏ IDKhoa khỏi required columns
    $checkColumns = $conn->query("DESCRIBE lop");
    $columns = $checkColumns->fetchAll(PDO::FETCH_COLUMN);
    $requiredColumns = ['ID', 'IDNhomNganh', 'IDGiangVien', 'GhiChu'];
    $missingColumns = array_diff($requiredColumns, $columns);
    
    if (!empty($missingColumns)) {
        throw new Exception("Bảng lớp thiếu các cột: " . implode(', ', $missingColumns));
    }
    
    // Xây dựng câu truy vấn với các điều kiện lọc
    $whereConditions = [];
    $params = [];
    
    // Lọc theo khoa (thông qua nhóm ngành)
    if (!empty($maKhoa)) {
        $whereConditions[] = "k.ID = ?";
        $params[] = $maKhoa;
    }
    
    // Lọc theo nhóm ngành
    if (!empty($maNganh)) {
        $whereConditions[] = "nn.ID = ?";
        $params[] = $maNganh;
    }
    
    // Tìm kiếm theo mã lớp hoặc tên giáo viên
    if (!empty($search)) {
        $whereConditions[] = "(l.ID LIKE ? OR tk.HoTen LIKE ?)";
        $params[] = "%$search%";
        $params[] = "%$search%";
    }
    
    $whereClause = !empty($whereConditions) ? "WHERE " . implode(' AND ', $whereConditions) : "";
    
    // Đếm tổng số bản ghi cho phân trang
    $countQuery = "
        SELECT COUNT(l.ID) as total 
        FROM lop l
        LEFT JOIN nhomnganh nn ON l.IDNhomNganh = nn.ID
        LEFT JOIN khoa k ON nn.IDKhoa = k.ID  -- Sửa join khoa thông qua nhóm ngành
        LEFT JOIN taikhoan tk ON l.IDGiangVien = tk.ID
        $whereClause
    ";
    
    $countStmt = $conn->prepare($countQuery);
    $countStmt->execute($params);
    $totalRecords = $countStmt->fetch(PDO::FETCH_ASSOC)['total'];
    
    // Truy vấn danh sách lớp với phân trang
    $query = "
        SELECT 
            l.ID, 
            l.IDGiangVien,
            tk.HoTen AS TenGiangVien, 
            nn.Ten AS NhomNganh,
            k.Ten AS KhoaVien,
            l.GhiChu,
            (SELECT COUNT(*) FROM taikhoan WHERE IDLop = l.ID AND ChucVu = 'Sinh Viên') AS SoSinhVien
        FROM lop l
        LEFT JOIN nhomnganh nn ON l.IDNhomNganh = nn.ID
        LEFT JOIN khoa k ON nn.IDKhoa = k.ID  -- Sửa join khoa thông qua nhóm ngành
        LEFT JOIN taikhoan tk ON l.IDGiangVien = tk.ID
        $whereClause
        ORDER BY l.ID
        LIMIT ?, ?
    ";
    
    $stmt = $conn->prepare($query);
    
    // Bind tất cả các tham số
    $paramIndex = 1;
    foreach ($params as $param) {
        $stmt->bindValue($paramIndex++, $param);
    }
    $stmt->bindValue($paramIndex++, $start, PDO::PARAM_INT);
    $stmt->bindValue($paramIndex, $recordsPerPage, PDO::PARAM_INT);
    
    $stmt->execute();
    $dsLop = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Debug nếu không có dữ liệu
    if (empty($dsLop) && $totalRecords > 0) {
        error_log("Có " . $totalRecords . " bản ghi nhưng không lấy được dữ liệu. Params: " . json_encode(array_merge($params, [$start, $recordsPerPage])));
    }
    
} catch (Exception $e) {
    error_log("Lỗi truy vấn danh sách lớp: " . $e->getMessage());
    $_SESSION['message'] = "Có lỗi xảy ra khi tải dữ liệu: " . $e->getMessage();
    $_SESSION['messageType'] = "danger";
}

// Kiểm tra cơ bản nếu vẫn chưa lấy được dữ liệu
if (empty($dsLop) && $totalRecords == 0) {
    try {
        // Thử một câu truy vấn đơn giản
        $simpleQuery = "SELECT * FROM lop LIMIT 5";
        $simpleStmt = $conn->query($simpleQuery);
        $simpleLop = $simpleStmt->fetchAll(PDO::FETCH_ASSOC);
        
        if (!empty($simpleLop)) {
            error_log("Truy vấn đơn giản thành công, tìm thấy " . count($simpleLop) . " lớp.");
            
            // Thêm các thông tin còn thiếu
            foreach ($simpleLop as &$lop) {
                try {
                    // Lấy thông tin giảng viên
                    if (!empty($lop['IDGiangVien'])) {
                        $gvStmt = $conn->prepare("SELECT HoTen FROM taikhoan WHERE ID = ?");
                        $gvStmt->execute([$lop['IDGiangVien']]);
                        $gv = $gvStmt->fetch(PDO::FETCH_ASSOC);
                        $lop['TenGiangVien'] = $gv ? $gv['HoTen'] : 'Chưa phân công';
                    } else {
                        $lop['TenGiangVien'] = 'Chưa phân công';
                    }
                    
                    // Lấy thông tin nhóm ngành và khoa
                    if (!empty($lop['IDNhomNganh'])) {
                        $nganhStmt = $conn->prepare("
                            SELECT nn.Ten AS NhomNganh, k.Ten AS KhoaVien 
                            FROM nhomnganh nn 
                            LEFT JOIN khoa k ON nn.IDKhoa = k.ID 
                            WHERE nn.ID = ?
                        ");
                        $nganhStmt->execute([$lop['IDNhomNganh']]);
                        $nganh = $nganhStmt->fetch(PDO::FETCH_ASSOC);
                        if ($nganh) {
                            $lop['NhomNganh'] = $nganh['NhomNganh'];
                            $lop['KhoaVien'] = $nganh['KhoaVien'];
                        }
                    }
                    
                    // Đếm số sinh viên
                    $svStmt = $conn->prepare("SELECT COUNT(*) AS SoSV FROM taikhoan WHERE IDLop = ?");
                    $svStmt->execute([$lop['ID']]);
                    $sv = $svStmt->fetch(PDO::FETCH_ASSOC);
                    $lop['SoSinhVien'] = $sv ? $sv['SoSV'] : 0;
                } catch (Exception $ex) {
                    // Bỏ qua lỗi và tiếp tục với lớp tiếp theo
                }
            }
            
            // Sử dụng kết quả từ truy vấn đơn giản
            $dsLop = $simpleLop;
            $totalRecords = count($simpleLop);
        }
    } catch (Exception $e) {
        error_log("Lỗi truy vấn đơn giản: " . $e->getMessage());
    }
}

// Phần HTML vẫn giữ nguyên không thay đổi
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
                        <div class="row align-items-center">
                            <div class="col-md-6">
                                <h4 class="mb-0"><i class="fas fa-users-class me-2"></i>DANH SÁCH LỚP</h4>
                            </div>
                            <div class="col-md-6 text-md-end mt-2 mt-md-0">
                                <?php if ($accessLevel <= 2): ?>
                                    <a href="Create.php" class="btn btn-light">
                                        <i class="fas fa-plus-circle me-1"></i> Thêm lớp mới
                                    </a>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    
                    <div class="card-body">
                        <!-- Form tìm kiếm và lọc -->
                        <form method="get" class="mb-4 row g-3 align-items-end">
                            <div class="col-md-3">
                                <label for="MaKhoa" class="form-label">Khoa/Viện</label>
                                <select class="form-select" id="MaKhoa" name="MaKhoa">
                                    <option value="">-- Tất cả Khoa/Viện --</option>
                                    <?php foreach ($dsKhoa as $khoa): ?>
                                        <option value="<?php echo htmlspecialchars($khoa['ID']); ?>" 
                                            <?php echo $maKhoa == $khoa['ID'] ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($khoa['Ten']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            
                            <div class="col-md-3">
                                <label for="MaNganh" class="form-label">Nhóm ngành</label>
                                <select class="form-select" id="MaNganh" name="MaNganh">
                                    <option value="">-- Tất cả nhóm ngành --</option>
                                    <?php foreach ($dsNhomNganh as $nganh): ?>
                                        <option value="<?php echo htmlspecialchars($nganh['ID']); ?>" 
                                            <?php echo $maNganh == $nganh['ID'] ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($nganh['Ten']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            
                            <div class="col-md-4">
                                <label for="strSearch" class="form-label">Tìm kiếm</label>
                                <div class="input-group">
                                    <input type="text" class="form-control" id="strSearch" name="strSearch" 
                                           placeholder="Nhập mã lớp hoặc tên giảng viên..." 
                                           value="<?php echo htmlspecialchars($search); ?>">
                                    <button class="btn btn-primary" type="submit">
                                        <i class="fas fa-search me-1"></i> Tìm
                                    </button>
                                </div>
                            </div>
                            
                            <div class="col-md-2">
                                <a href="Index.php" class="btn btn-secondary w-100">
                                    <i class="fas fa-sync-alt me-1"></i> Làm mới
                                </a>
                            </div>
                        </form>
                        
                        <?php if (isset($_SESSION['message'])): ?>
                            <div class="alert alert-<?php echo $_SESSION['messageType'] ?? 'info'; ?> alert-dismissible fade show" role="alert">
                                <?php echo $_SESSION['message']; ?>
                                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                            </div>
                            <?php 
                                unset($_SESSION['message']); 
                                unset($_SESSION['messageType']);
                            ?>
                        <?php endif; ?>
                        
                        <!-- Bảng danh sách lớp -->
                        <div class="table-responsive">
                            <table class="table table-hover table-bordered table-striped">
                                <thead class="table-dark">
                                    <tr>
                                        <th style="width: 15%">Mã lớp</th>
                                        <th style="width: 20%">Giảng viên chủ nhiệm</th>
                                        <th style="width: 20%">Nhóm ngành</th>
                                        <th style="width: 15%">Khoa/Viện</th>
                                        <th style="width: 10%">Số SV</th>
                                        <th style="width: 20%">Thao tác</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (count($dsLop) > 0): ?>
                                        <?php foreach ($dsLop as $lop): ?>
                                            <tr>
                                                <td><?php echo htmlspecialchars($lop['ID']); ?></td>
                                                <td><?php echo htmlspecialchars($lop['TenGiangVien'] ?? 'Chưa phân công'); ?></td>
                                                <td><?php echo htmlspecialchars($lop['NhomNganh'] ?? ''); ?></td>
                                                <td><?php echo htmlspecialchars($lop['KhoaVien'] ?? ''); ?></td>
                                                <td class="text-center"><?php echo $lop['SoSinhVien'] ?? 0; ?></td>
                                                <td class="text-center">
                                                    <a href="ManageClass.php?MaLop=<?php echo $lop['ID']; ?>" class="btn btn-sm btn-info mb-1" title="Chi tiết">
                                                        <i class="fas fa-eye"></i>
                                                    </a>
                                                    
                                                    <?php if ($accessLevel <= 3): ?>
                                                        <a href="Edit.php?MaLop=<?php echo $lop['ID']; ?>" class="btn btn-sm btn-warning mb-1" title="Chỉnh sửa">
                                                            <i class="fas fa-edit"></i>
                                                        </a>
                                                    <?php endif; ?>
                                                    
                                                    <?php if ($accessLevel <= 2): ?>
                                                        <a href="Delete.php?MaLop=<?php echo $lop['ID']; ?>" class="btn btn-sm btn-danger mb-1" title="Xóa">
                                                            <i class="fas fa-trash"></i>
                                                        </a>
                                                    <?php endif; ?>
                                                    
                                                    <a href="Students.php?MaLop=<?php echo $lop['ID']; ?>" class="btn btn-sm btn-success mb-1" title="Danh sách sinh viên">
                                                        <i class="fas fa-user-graduate"></i>
                                                    </a>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <tr>
                                            <td colspan="6" class="text-center py-3">
                                                <?php if (!empty($search) || !empty($maKhoa) || !empty($maNganh)): ?>
                                                    <div class="alert alert-info mb-0">
                                                        <i class="fas fa-info-circle me-2"></i>
                                                        Không tìm thấy lớp nào phù hợp với điều kiện tìm kiếm
                                                    </div>
                                                <?php else: ?>
                                                    <div class="alert alert-info mb-0">
                                                        <i class="fas fa-info-circle me-2"></i>
                                                        Chưa có lớp nào trong hệ thống
                                                    </div>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                        
                        <!-- Phân trang -->
                        <?php if ($totalRecords > 0): ?>
                            <div class="d-flex justify-content-between align-items-center mt-4">
                                <div>
                                    Hiển thị <?php echo count($dsLop); ?> trên <?php echo $totalRecords; ?> lớp
                                </div>
                                
                                <nav>
                                    <?php
                                        $totalPages = ceil($totalRecords / $recordsPerPage);
                                        if ($totalPages > 1):
                                    ?>
                                        <ul class="pagination mb-0">
                                            <?php if ($page > 1): ?>
                                                <li class="page-item">
                                                    <a class="page-link" href="?page=1<?php echo !empty($maKhoa) ? '&MaKhoa=' . urlencode($maKhoa) : ''; ?><?php echo !empty($maNganh) ? '&MaNganh=' . urlencode($maNganh) : ''; ?><?php echo !empty($search) ? '&strSearch=' . urlencode($search) : ''; ?>">&laquo;</a>
                                                </li>
                                            <?php endif; ?>
                                            
                                            <?php
                                                $startPage = max(1, $page - 2);
                                                $endPage = min($totalPages, $page + 2);
                                                
                                                for ($i = $startPage; $i <= $endPage; $i++):
                                            ?>
                                                <li class="page-item <?php echo $i == $page ? 'active' : ''; ?>">
                                                    <a class="page-link" href="?page=<?php echo $i; ?><?php echo !empty($maKhoa) ? '&MaKhoa=' . urlencode($maKhoa) : ''; ?><?php echo !empty($maNganh) ? '&MaNganh=' . urlencode($maNganh) : ''; ?><?php echo !empty($search) ? '&strSearch=' . urlencode($search) : ''; ?>"><?php echo $i; ?></a>
                                                </li>
                                            <?php endfor; ?>
                                            
                                            <?php if ($page < $totalPages): ?>
                                                <li class="page-item">
                                                    <a class="page-link" href="?page=<?php echo $totalPages; ?><?php echo !empty($maKhoa) ? '&MaKhoa=' . urlencode($maKhoa) : ''; ?><?php echo !empty($maNganh) ? '&MaNganh=' . urlencode($maNganh) : ''; ?><?php echo !empty($search) ? '&strSearch=' . urlencode($search) : ''; ?>">&raquo;</a>
                                                </li>
                                            <?php endif; ?>
                                        </ul>
                                    <?php endif; ?>
                                </nav>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
<?php endif; ?>

<?php
// Lấy nội dung đã buffer và đưa vào layout
$contentForLayout = ob_get_clean();

// Kết nối với layout
include_once('../Shared/_LayoutAdmin.php');
?>
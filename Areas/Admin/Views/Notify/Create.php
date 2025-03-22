<?php
// filepath: c:\xampp\htdocs\TDMU_website\Areas\Admin\Views\Notify\Create.php
// Kết nối database
require_once("../Shared/connect.inc");

// Tiêu đề trang
$pageTitle = "Thêm thông báo mới";

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

// Lấy danh sách khoa, loại thông báo, và các loại đối tượng
$dsKhoa = [];
$dsLoaiTB = [];
$dsQuyenTruyCap = [];
$dsNguoiDung = [];

try {
    // Lấy danh sách khoa
    $stmtKhoa = $conn->query("SELECT ID, Ten FROM khoa ORDER BY Ten");
    $dsKhoa = $stmtKhoa->fetchAll(PDO::FETCH_ASSOC);
    
    // Lấy danh sách loại thông báo
    $stmtLoaiTB = $conn->query("SELECT ID, Ten FROM loaithongbao ORDER BY Ten");
    $dsLoaiTB = $stmtLoaiTB->fetchAll(PDO::FETCH_ASSOC);
    
    // Lấy danh sách quyền truy cập (vai trò)
    $stmtQuyen = $conn->query("SELECT ID, Ten, CapDo FROM quyentruycap ORDER BY CapDo");
    $dsQuyenTruyCap = $stmtQuyen->fetchAll(PDO::FETCH_ASSOC);
    
    // Lấy danh sách người dùng (giới hạn 100 người để tránh quá tải)
    $stmtUsers = $conn->query("
        SELECT 
            tk.ID, 
            tk.HoTen, 
            tk.Email, 
            tk.TenTaiKhoan, 
            qtc.Ten as VaiTro, 
            tk.ChucVu       
        FROM taikhoan tk
        LEFT JOIN quyentruycap qtc ON tk.IDQuyenTruyCap = qtc.ID  
        LEFT JOIN lop lh ON tk.IDLop = lh.ID
        ORDER BY tk.HoTen
        LIMIT 100
    ");
$dsNguoiDung = $stmtUsers->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Lỗi truy vấn danh sách: " . $e->getMessage());
}

// Xử lý form khi submit
$errors = [];
$success = false;
$formData = [
    'phanLoaiNguoiNhan' => 'all',
    'MaKhoa' => '',
    'MaQuyen' => '',
    'dsNguoiNhan' => [],
    'MaLTB' => '',
    'sTieuDe' => '',
    'sNoiDung' => '',
    'sGhiChu' => ''
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Lấy dữ liệu từ form
    $formData = [
        'phanLoaiNguoiNhan' => $_POST['phanLoaiNguoiNhan'] ?? 'all',
        'MaKhoa' => $_POST['MaKhoa'] ?? '',
        'MaQuyen' => $_POST['MaQuyen'] ?? '',
        'dsNguoiNhan' => isset($_POST['dsNguoiNhan']) ? (array)$_POST['dsNguoiNhan'] : [],
        'MaLTB' => $_POST['MaLTB'] ?? '',
        'sTieuDe' => $_POST['sTieuDe'] ?? '',
        'sNoiDung' => $_POST['sNoiDung'] ?? '',
        'sGhiChu' => $_POST['sGhiChu'] ?? ''
    ];
    
    // Kiểm tra dữ liệu
    if (empty($formData['sTieuDe'])) {
        $errors[] = "Tiêu đề không được để trống";
    }
    
    if (empty($formData['sNoiDung'])) {
        $errors[] = "Nội dung không được để trống";
    }
    
    if ($formData['phanLoaiNguoiNhan'] == 'specific' && empty($formData['dsNguoiNhan'])) {
        $errors[] = "Vui lòng chọn ít nhất một người nhận thông báo";
    }
    
    if ($formData['phanLoaiNguoiNhan'] == 'khoa' && empty($formData['MaKhoa'])) {
        $errors[] = "Vui lòng chọn khoa";
    }
    
    if ($formData['phanLoaiNguoiNhan'] == 'quyen' && empty($formData['MaQuyen'])) {
        $errors[] = "Vui lòng chọn vai trò người dùng";
    }
    
    // Nếu không có lỗi, thêm thông báo vào database
    if (empty($errors)) {
        try {
            // Bắt đầu transaction
            $conn->beginTransaction();
            
            // Thêm thông báo - bao gồm cả IDNguoiTao và NgayTao
            $stmtInsert = $conn->prepare("
                INSERT INTO thongbao (ID, IDLoaiThongBao, IDNguoiTao, TieuDe, NoiDung, GhiChu, NgayTao)
                VALUES (NULL, ?, ?, ?, ?, ?, NOW())
            ");
            $stmtInsert->execute([
                $formData['MaLTB'] ?: null,
                $tk['ID'],  
                $formData['sTieuDe'],
                $formData['sNoiDung'],
                $formData['sGhiChu'] ?: null
            ]);
            
            // Lấy ID thông báo vừa thêm 
            $lastID = $conn->lastInsertId();
            try {
                $stmtGetID = $conn->prepare("SELECT ID FROM thongbao WHERE id = ?");
                $stmtGetID->execute([$lastID]);
                $result = $stmtGetID->fetch(PDO::FETCH_ASSOC);
                
                if (!$result || empty($result['ID'])) {
                    // Nếu không tìm thấy ID, thử truy vấn mới nhất
                    $stmtNewestID = $conn->query("SELECT ID FROM thongbao ORDER BY NgayTao DESC LIMIT 1");
                    $result = $stmtNewestID->fetch(PDO::FETCH_ASSOC);
                    
                    if (!$result) {
                        throw new Exception("Không thể xác định ID thông báo vừa tạo");
                    }
                }
                
                $newID = $result['ID'];
                // Ghi log xác nhận ID thông báo
                error_log("ID thông báo xác định được: " . $newID);
            } catch (Exception $e) {
                error_log("Lỗi khi xác định ID thông báo: " . $e->getMessage());
                throw $e;
            }
            
            // Kiểm tra xem ID có hợp lệ không
            if (empty($newID)) {
                throw new Exception("ID thông báo không hợp lệ");
            }
            
            // Ghi lại người tạo thông báo
            $stmtNguoiTao = $conn->prepare("
                INSERT INTO thongbao_nguoichinhsua (IDThongBao, IDNguoiChinhSua, NgayChinhSua, GhiChu)
                VALUES (?, ?, NOW(), ?)
            ");
            $stmtNguoiTao->execute([
                $newID,
                $tk['ID'],
                "Tạo thông báo mới"
            ]);
            
            // Xử lý danh sách người nhận dựa theo phân loại
            switch ($formData['phanLoaiNguoiNhan']) {
                case 'all':
                    // Lấy tất cả người dùng
                    $stmtAllUsers = $conn->query("SELECT ID FROM taikhoan");
                    while ($user = $stmtAllUsers->fetch(PDO::FETCH_ASSOC)) {
                        $stmtThemNguoiNhan = $conn->prepare("
                            INSERT INTO thongbao_nguoinhan (IDThongBao, IDNguoiNhan, NgayNhan, GhiChu)
                            VALUES (?, ?, NOW(), ?)
                        ");
                        $stmtThemNguoiNhan->execute([
                            $newID,
                            $user['ID'],
                            "Tự động thêm (Tất cả)"
                        ]);
                    }
                    break;
                    
                case 'khoa':
                    // Bổ sung log để debug trước khi gọi truy vấn
                    error_log("Đang gửi thông báo cho khoa: " . $formData['MaKhoa']);
                    
                    // BƯỚC 1: Lấy danh sách các lớp thuộc khoa đã chọn
                    $stmtLopThuocKhoa = $conn->prepare("
                        SELECT l.ID 
                        FROM lop l
                        JOIN nhomnganh ng ON l.IDNhomNganh = ng.ID
                        WHERE ng.IDKhoa = ?
                    ");
                    $stmtLopThuocKhoa->execute([$formData['MaKhoa']]);
                    $dsLop = $stmtLopThuocKhoa->fetchAll(PDO::FETCH_ASSOC);
                    
                    // Debug số lượng lớp tìm được
                    $lopCount = count($dsLop);
                    error_log("Tìm thấy $lopCount lớp thuộc khoa " . $formData['MaKhoa']);
                    
                    // Tạo mảng chứa ID của các lớp
                    $dsIDLop = array();
                    foreach ($dsLop as $lop) {
                        $dsIDLop[] = $lop['ID'];
                    }
                    
                    // Nếu không tìm thấy lớp nào, thêm giá trị giả để tránh lỗi SQL
                    if (empty($dsIDLop)) {
                        error_log("Không tìm thấy lớp nào thuộc khoa " . $formData['MaKhoa']);
                        $dsIDLop[] = 'no_class_found';
                    }
                    
                    // BƯỚC 2: Lấy danh sách sinh viên thuộc các lớp đó
                    $placeholders = str_repeat('?,', count($dsIDLop) - 1) . '?';
                    $sqlSinhVien = "
                        SELECT ID 
                        FROM taikhoan
                        WHERE IDLop IN ($placeholders)
                    ";
                    $stmtSinhVien = $conn->prepare($sqlSinhVien);
                    $stmtSinhVien->execute($dsIDLop);
                    $dsSinhVien = $stmtSinhVien->fetchAll(PDO::FETCH_ASSOC);
                    
                    // Debug số lượng sinh viên tìm được
                    $sinhVienCount = count($dsSinhVien);
                    error_log("Tìm thấy $sinhVienCount sinh viên thuộc $lopCount lớp của khoa " . $formData['MaKhoa']);
                    
                    // BƯỚC 3: Tạo thông báo người nhận cho từng sinh viên
                    if ($sinhVienCount > 0) {
                        foreach ($dsSinhVien as $sinhVien) {
                            try {
                                $stmtThemNguoiNhan = $conn->prepare("
                                    INSERT INTO thongbao_nguoinhan (IDThongBao, IDNguoiNhan, NgayNhan, GhiChu)
                                    VALUES (?, ?, NOW(), ?)
                                ");
                                $stmtThemNguoiNhan->execute([
                                    $newID,
                                    $sinhVien['ID'],
                                    "Tự động thêm từ Khoa (Lớp)"
                                ]);
                                error_log("Đã thêm sinh viên: {$sinhVien['ID']} vào danh sách người nhận");
                            } catch (PDOException $e) {
                                error_log("Lỗi khi thêm người nhận theo khoa: " . $e->getMessage());
                                // Không throw exception để tiếp tục xử lý các sinh viên khác
                            }
                        }
                    } else {
                        error_log("CẢNH BÁO: Không tìm thấy sinh viên nào thuộc khoa " . $formData['MaKhoa']);
                    }
                    break;
            }
            
            // Commit transaction
            $conn->commit();
            
            // Thông báo thành công
            $success = true;
            $_SESSION['message'] = "Thêm thông báo mới thành công!";
            $_SESSION['messageType'] = "success";
            
            // Chuyển hướng về trang Index
            header("Location: Index.php");
            exit();
            
        } catch (PDOException $e) {
            // Rollback nếu có lỗi
            $conn->rollBack();
            error_log("Lỗi thêm thông báo: " . $e->getMessage());
            $errors[] = "Đã xảy ra lỗi khi thêm thông báo: " . $e->getMessage();
        }
    }
}

// Bắt đầu buffer
ob_start();
?>

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
                        <h4 class="mb-0"><i class="fas fa-plus-circle me-2"></i>THÊM THÔNG BÁO MỚI</h4>
                    </div>
                    <div class="card-body">
                        <?php if (!empty($errors)): ?>
                            <div class="alert alert-danger">
                                <ul class="mb-0">
                                    <?php foreach ($errors as $error): ?>
                                        <li><?php echo $error; ?></li>
                                    <?php endforeach; ?>
                                </ul>
                            </div>
                        <?php endif; ?>

                        <form method="post" enctype="multipart/form-data" class="needs-validation" novalidate>
                            <!-- Đối tượng nhận thông báo -->
                            <div class="card mb-4 border-primary">
                                <div class="card-header bg-primary bg-opacity-10 fw-bold">
                                    <i class="fas fa-users me-2"></i>Đối tượng nhận thông báo
                                </div>
                                <div class="card-body pt-3">
                                    <div class="mb-3">
                                        <div class="form-check form-check-inline">
                                            <input class="form-check-input" type="radio" name="phanLoaiNguoiNhan" id="phanLoaiAll" 
                                                value="all" <?php echo $formData['phanLoaiNguoiNhan'] == 'all' ? 'checked' : ''; ?>>
                                            <label class="form-check-label" for="phanLoaiAll">
                                                <i class="fas fa-globe me-1"></i> Tất cả người dùng
                                            </label>
                                        </div>
                                        <div class="form-check form-check-inline">
                                            <input class="form-check-input" type="radio" name="phanLoaiNguoiNhan" id="phanLoaiKhoa" 
                                                value="khoa" <?php echo $formData['phanLoaiNguoiNhan'] == 'khoa' ? 'checked' : ''; ?>>
                                            <label class="form-check-label" for="phanLoaiKhoa">
                                                <i class="fas fa-building me-1"></i> Theo khoa
                                            </label>
                                        </div>
                                        <div class="form-check form-check-inline">
                                            <input class="form-check-input" type="radio" name="phanLoaiNguoiNhan" id="phanLoaiSpecific" 
                                                value="specific" <?php echo $formData['phanLoaiNguoiNhan'] == 'specific' ? 'checked' : ''; ?>>
                                            <label class="form-check-label" for="phanLoaiSpecific">
                                                <i class="fas fa-user-check me-1"></i> Chỉ định người nhận
                                            </label>
                                        </div>
                                    </div>
                                    
                                    <!-- Chọn khoa -->
                                    <div id="khoa-selection" class="mb-3 mt-4 ps-4 border-start border-3 <?php echo $formData['phanLoaiNguoiNhan'] != 'khoa' ? 'd-none' : ''; ?>">
                                        <label for="MaKhoa" class="form-label fw-bold">Chọn khoa:</label>
                                        <select name="MaKhoa" id="MaKhoa" class="form-select">
                                            <option value="">-- Chọn khoa --</option>
                                            <?php foreach ($dsKhoa as $khoa): ?>
                                                <option value="<?php echo htmlspecialchars($khoa['ID']); ?>" <?php echo $formData['MaKhoa'] == $khoa['ID'] ? 'selected' : ''; ?>>
                                                    <?php echo htmlspecialchars($khoa['Ten']); ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                        <div class="form-text">
                                            Thông báo sẽ được gửi đến tất cả người dùng thuộc khoa này
                                        </div>
                                    </div>
                                    
                                    
                                    <!-- Chọn người nhận cụ thể -->
                                    <div id="specific-selection" class="mb-3 mt-4 ps-4 border-start border-3 <?php echo $formData['phanLoaiNguoiNhan'] != 'specific' ? 'd-none' : ''; ?>">
                                        <label class="form-label fw-bold">Chọn người nhận cụ thể:</label>
                                        <div class="input-group mb-2">
                                            <input type="text" id="searchUsers" class="form-control" placeholder="Tìm kiếm người dùng...">
                                            <button type="button" class="btn btn-outline-secondary" disabled>
                                                <i class="fas fa-search"></i>
                                            </button>
                                        </div>
                                        
                                        <div class="form-text mb-3">
                                            Chỉ hiển thị 100 người dùng đầu tiên. Sử dụng ô tìm kiếm để lọc danh sách.
                                        </div>
                                      <!-- Cập nhật bảng danh sách người dùng -->
                                        <div class="table-responsive" style="max-height: 300px; overflow-y: auto;">
                                            <table class="table table-sm table-hover">
                                                <thead class="table-light sticky-top">
                                                    <tr>
                                                        <th style="width: 50px;"></th>
                                                        <th>Mã</th>
                                                        <th>Họ tên</th>
                                                        <th>Email</th>
                                                        <th>Chức vụ/Vai trò</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    <?php if (isset($dsNguoiDung) && count($dsNguoiDung) > 0): ?>
                                                        <?php foreach ($dsNguoiDung as $user): ?>
                                                        <tr class="user-row">
                                                            <td>
                                                                <div class="form-check">
                                                                    <input class="form-check-input" type="checkbox" name="dsNguoiNhan[]" 
                                                                        value="<?php echo htmlspecialchars($user['ID']); ?>" 
                                                                        id="user<?php echo htmlspecialchars($user['ID']); ?>"
                                                                        <?php echo in_array($user['ID'], $formData['dsNguoiNhan']) ? 'checked' : ''; ?>>
                                                                </div>
                                                            </td>
                                                            <td>
                                                                <?php echo htmlspecialchars( $user['ID']); ?>
                                                            </td>
                                                            <td>
                                                                <label for="user<?php echo htmlspecialchars($user['ID']); ?>" class="form-check-label">
                                                                    <?php echo htmlspecialchars($user['HoTen']); ?>
                                                                </label>
                                                            </td>
                                                            <td><?php echo htmlspecialchars($user['Email'] ?: 'Không có'); ?></td>
                                                            <td>
                                                                <?php 
                                                                    $role = '';
                                                                    if (!empty($user['ChucVu'])) {
                                                                        $role = $user['ChucVu'];
                                                                    } elseif (!empty($user['VaiTro'])) {
                                                                        $role = $user['VaiTro'];
                                                                    } else {
                                                                        $role = 'Không xác định';
                                                                    }
                                                                    echo htmlspecialchars($role);
                                                                ?>
                                                            </td>
                                                        </tr>
                                                        <?php endforeach; ?>
                                                    <?php else: ?>
                                                        <tr>
                                                            <td colspan="5" class="text-center py-3">
                                                                <div class="alert alert-warning mb-0">
                                                                    <i class="fas fa-exclamation-circle me-2"></i>
                                                                    Không tìm thấy dữ liệu người dùng. Vui lòng kiểm tra kết nối cơ sở dữ liệu hoặc thêm người dùng vào hệ thống.
                                                                </div>
                                                            </td>
                                                        </tr>
                                                    <?php endif; ?>
                                                </tbody>
                                            </table>
                                        </div>
                                    </div>
                                </div>
                            </div>
                                    
                            <!-- Thông tin thông báo -->
                            <div class="card mb-4 border-primary">
                                <div class="card-header bg-primary bg-opacity-10 fw-bold">
                                    <i class="fas fa-bell me-2"></i>Thông tin thông báo
                                </div>
                                <div class="card-body">
                                    <div class="mb-3">
                                        <label for="MaLTB" class="form-label">Loại thông báo:</label>
                                        <select name="MaLTB" id="MaLTB" class="form-select">
                                            <option value="">-- Chọn loại thông báo --</option>
                                            <?php foreach ($dsLoaiTB as $loai): ?>
                                                <option value="<?php echo htmlspecialchars($loai['ID']); ?>" <?php echo $formData['MaLTB'] == $loai['ID'] ? 'selected' : ''; ?>>
                                                    <?php echo htmlspecialchars($loai['Ten']); ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>

                                    <div class="mb-3">
                                        <label for="sTieuDe" class="form-label">Tiêu đề: <span class="text-danger">*</span></label>
                                        <input type="text" name="sTieuDe" id="sTieuDe" class="form-control" value="<?php echo htmlspecialchars($formData['sTieuDe']); ?>" required>
                                        <div class="invalid-feedback">
                                            Vui lòng nhập tiêu đề thông báo
                                        </div>
                                    </div>

                                    <div class="mb-3">
                                        <label for="editor" class="form-label">Nội dung: <span class="text-danger">*</span></label>
                                        <textarea name="sNoiDung" id="editor" class="form-control" rows="10" required><?php echo htmlspecialchars($formData['sNoiDung']); ?></textarea>
                                        <div class="invalid-feedback">
                                            Vui lòng nhập nội dung thông báo
                                        </div>
                                    </div>

                                    <div class="mb-0">
                                        <label for="sGhiChu" class="form-label">Ghi chú:</label>
                                        <input type="text" name="sGhiChu" id="sGhiChu" class="form-control" value="<?php echo htmlspecialchars($formData['sGhiChu']); ?>">
                                        <div class="form-text">Ghi chú thêm về thông báo (không bắt buộc)</div>
                                    </div>
                                </div>
                            </div>

                            <div class="d-flex justify-content-end">
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-save me-2"></i>Lưu và gửi thông báo
                                </button>
                                <a href="Index.php" class="btn btn-secondary ms-2">
                                    <i class="fas fa-arrow-left me-2"></i>Quay lại danh sách
                                </a>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- JavaScript cho TinyMCE và interactivity -->
    <script src="https://cdn.tiny.cloud/1/no-api-key/tinymce/6/tinymce.min.js" referrerpolicy="origin"></script>
    <script>
        // Khởi tạo TinyMCE
        tinymce.init({
            selector: '#editor',
            plugins: 'anchor autolink charmap codesample emoticons image link lists media searchreplace table visualblocks wordcount',
            toolbar: 'undo redo | blocks fontfamily fontsize | bold italic underline strikethrough | link image media table | align lineheight | numlist bullist indent outdent | emoticons charmap | removeformat',
            height: 400,
            language: 'vi',
            promotion: false,
            branding: false
        });
        
        // Xử lý hiển thị/ẩn các phần tùy thuộc vào loại người nhận được chọn
        document.addEventListener('DOMContentLoaded', function() {
            const radioButtons = document.querySelectorAll('input[name="phanLoaiNguoiNhan"]');
            const khoaSelection = document.getElementById('khoa-selection');
            const specificSelection = document.getElementById('specific-selection');
            
            // Xử lý khi thay đổi loại người nhận
            radioButtons.forEach(function(radio) {
                radio.addEventListener('change', function() {
                    // Ẩn tất cả trước
                    khoaSelection.classList.add('d-none');
                    specificSelection.classList.add('d-none');
                    
                    // Hiện phần tương ứng với lựa chọn
                    switch(this.value) {
                        case 'khoa':
                            khoaSelection.classList.remove('d-none');
                            break;
                        case 'specific':
                            specificSelection.classList.remove('d-none');
                            break;
                    }
                });
            });
            
            // Tìm kiếm người dùng
            const searchInput = document.getElementById('searchUsers');
            const userRows = document.querySelectorAll('.user-row');
            
            if (searchInput) {
                // Cập nhật phần JavaScript tìm kiếm người dùng để tìm thêm trong các trường mới
                searchInput.addEventListener('input', function() {
                    const searchTerm = this.value.toLowerCase().trim();
                    
                    userRows.forEach(function(row) {
                        const userId = row.querySelectorAll('td')[1] ? row.querySelectorAll('td')[1].textContent.toLowerCase() : '';
                        const userName = row.querySelector('label').textContent.toLowerCase();
                        const userClass = row.querySelectorAll('td')[3] ? row.querySelectorAll('td')[3].textContent.toLowerCase() : '';
                        const userRole = row.querySelectorAll('td')[4] ? row.querySelectorAll('td')[4].textContent.toLowerCase() : '';
                        const userDept = row.querySelectorAll('td')[5] ? row.querySelectorAll('td')[5].textContent.toLowerCase() : '';
                        
                        // Tìm kiếm trong tất cả các trường
                        if (
                            userId.includes(searchTerm) || 
                            userName.includes(searchTerm) ||
                            userClass.includes(searchTerm) ||
                            userRole.includes(searchTerm) ||
                            userDept.includes(searchTerm)
                        ) {
                            row.style.display = '';
                        } else {
                            row.style.display = 'none';
                        }
                    });
                });
            }
            
            // Thêm nút chọn/bỏ chọn tất cả
            const specificSelectionDiv = document.getElementById('specific-selection');
            if (specificSelectionDiv) {
                // Kiểm tra xem đã có nút chọn/bỏ chọn tất cả chưa
                if (!document.getElementById('selectAllUsers')) {
                    const selectAllWrapper = document.createElement('div');
                    selectAllWrapper.className = 'mb-3';
                    selectAllWrapper.innerHTML = `
                        <button type="button" id="selectAllUsers" class="btn btn-sm btn-outline-primary me-2">
                            <i class="fas fa-check-square me-1"></i> Chọn tất cả
                        </button>
                        <button type="button" id="deselectAllUsers" class="btn btn-sm btn-outline-secondary">
                            <i class="fas fa-square me-1"></i> Bỏ chọn tất cả
                        </button>
                    `;
                    
                    // Chèn vào trước bảng
                    const tableResponsive = specificSelectionDiv.querySelector('.table-responsive');
                    if (tableResponsive) {
                        specificSelectionDiv.insertBefore(selectAllWrapper, tableResponsive);
                    }
                }
                
                // Xử lý sự kiện chọn/bỏ chọn tất cả
                document.getElementById('selectAllUsers')?.addEventListener('click', function() {
                    const checkboxes = document.querySelectorAll('input[name="dsNguoiNhan[]"]:not(:disabled)');
                    checkboxes.forEach(function(checkbox) {
                        if (checkbox.closest('tr').style.display !== 'none') {
                            checkbox.checked = true;
                        }
                    });
                });
                
                document.getElementById('deselectAllUsers')?.addEventListener('click', function() {
                    const checkboxes = document.querySelectorAll('input[name="dsNguoiNhan[]"]:not(:disabled)');
                    checkboxes.forEach(function(checkbox) {
                        checkbox.checked = false;
                    });
                });
            }
        });
        
        // Form validation
        (function () {
            'use strict'
            
            // Lấy tất cả form cần validate
            var forms = document.querySelectorAll('.needs-validation')
            
            // Lặp qua và ngăn submit với validate
            Array.prototype.slice.call(forms)
                .forEach(function (form) {
                    form.addEventListener('submit', function (event) {
                        // Kiểm tra loại người nhận và validate tương ứng
                        const selectedType = document.querySelector('input[name="phanLoaiNguoiNhan"]:checked')?.value;
                        let valid = true;
                        
                        // Cập nhật nội dung từ TinyMCE trước khi validate
                        if (tinymce.get('editor')) {
                            tinymce.get('editor').save();
                        }
                        
                        // Validation tùy thuộc vào loại người nhận
                        if (selectedType === 'khoa') {
                            const khoaSelect = document.getElementById('MaKhoa');
                            if (khoaSelect && !khoaSelect.value) {
                                valid = false;
                                // Tạo feedback nếu chưa có
                                if (!khoaSelect.nextElementSibling || !khoaSelect.nextElementSibling.classList.contains('invalid-feedback')) {
                                    const feedback = document.createElement('div');
                                    feedback.className = 'invalid-feedback';
                                    feedback.textContent = 'Vui lòng chọn khoa';
                                    khoaSelect.parentNode.insertBefore(feedback, khoaSelect.nextSibling);
                                }
                                khoaSelect.classList.add('is-invalid');
                            } else if (khoaSelect) {
                                khoaSelect.classList.remove('is-invalid');
                            }
                        }
                        
                        if (selectedType === 'quyen') {
                            const quyenSelect = document.getElementById('MaQuyen');
                            if (quyenSelect && !quyenSelect.value) {
                                valid = false;
                                // Tạo feedback nếu chưa có
                                if (!quyenSelect.nextElementSibling || !quyenSelect.nextElementSibling.classList.contains('invalid-feedback')) {
                                    const feedback = document.createElement('div');
                                    feedback.className = 'invalid-feedback';
                                    feedback.textContent = 'Vui lòng chọn vai trò';
                                    quyenSelect.parentNode.insertBefore(feedback, quyenSelect.nextSibling);
                                }
                                quyenSelect.classList.add('is-invalid');
                            } else if (quyenSelect) {
                                quyenSelect.classList.remove('is-invalid');
                            }
                        }
                        
                        if (selectedType === 'specific') {
                            const checkboxes = document.querySelectorAll('input[name="dsNguoiNhan[]"]:checked');
                            if (checkboxes.length === 0) {
                                valid = false;
                                // Hiển thị thông báo lỗi
                                const errorMsg = document.createElement('div');
                                errorMsg.className = 'alert alert-danger mt-2';
                                errorMsg.innerHTML = '<i class="fas fa-exclamation-circle me-2"></i> Vui lòng chọn ít nhất một người nhận';
                                
                                // Xóa thông báo lỗi cũ nếu có
                                const oldError = document.querySelector('#specific-selection .alert-danger');
                                if (oldError) {
                                    oldError.remove();
                                }
                                
                                // Thêm thông báo lỗi mới
                                const tableContainer = document.querySelector('#specific-selection .table-responsive');
                                if (tableContainer) {
                                    tableContainer.parentNode.insertBefore(errorMsg, tableContainer);
                                }
                            } else {
                                // Xóa thông báo lỗi nếu đã chọn người nhận
                                const oldError = document.querySelector('#specific-selection .alert-danger');
                                if (oldError) {
                                    oldError.remove();
                                }
                            }
                        }
                        
                        if (!form.checkValidity() || !valid) {
                            event.preventDefault();
                            event.stopPropagation();
                        }
                        
                        form.classList.add('was-validated');
                    }, false);
                });
        })();
    </script>
<?php endif; ?>

<?php
// Lấy nội dung đã buffer và đưa vào layout
$contentForLayout = ob_get_clean();

// Kết nối với layout
include_once('../Shared/_LayoutAdmin.php');
?>
<?php
// Kết nối database
require_once("../Shared/connect.inc");

// Tiêu đề trang
$pageTitle = "Chỉnh sửa thông báo";

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

// Kiểm tra quyền chỉnh sửa (cấp độ 3 trở xuống)
if ($accessLevel > 3 || $accessLevel < 1) {
    $_SESSION['message'] = "Bạn không có quyền chỉnh sửa thông báo!";
    $_SESSION['messageType'] = "danger";
    header('Location: Index.php');
    exit;
}

// Kiểm tra ID thông báo
if (!isset($_GET['id']) || empty($_GET['id'])) {
    $_SESSION['message'] = "Không tìm thấy thông báo cần chỉnh sửa!";
    $_SESSION['messageType'] = "danger";
    header('Location: Index.php');
    exit;
}

$idThongBao = $_GET['id'];
$errors = [];
$success = false;
$thongBao = null;

// Lấy thông tin thông báo cần chỉnh sửa
try {
    // Lấy danh sách loại thông báo cho dropdown
    $stmtLoaiTB = $conn->query("SELECT ID, Ten FROM loaithongbao ORDER BY Ten");
    $dsLoaiTB = $stmtLoaiTB->fetchAll(PDO::FETCH_ASSOC);
    
    // Lấy thông tin thông báo
    $stmtThongBao = $conn->prepare("
        SELECT tb.*, ltb.Ten as TenLoaiThongBao
        FROM thongbao tb
        LEFT JOIN loaithongbao ltb ON tb.IDLoaiThongBao = ltb.ID
        WHERE tb.ID = ?
    ");
    $stmtThongBao->execute([$idThongBao]);
    $thongBao = $stmtThongBao->fetch(PDO::FETCH_ASSOC);
    
    if (!$thongBao) {
        $_SESSION['message'] = "Không tìm thấy thông báo với ID: $idThongBao";
        $_SESSION['messageType'] = "danger";
        header('Location: Index.php');
        exit;
    }
} catch (PDOException $e) {
    error_log("Lỗi truy vấn thông tin thông báo: " . $e->getMessage());
    $_SESSION['message'] = "Có lỗi xảy ra khi tải dữ liệu: " . $e->getMessage();
    $_SESSION['messageType'] = "danger";
    header('Location: Index.php');
    exit;
}

// Xử lý form khi submit
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validate dữ liệu đầu vào
    if (empty($_POST['sTieuDe'])) {
        $errors[] = "Vui lòng nhập tiêu đề thông báo";
    }
    
    if (empty($_POST['sNoiDung'])) {
        $errors[] = "Vui lòng nhập nội dung thông báo";
    }
    
    // Nếu không có lỗi, tiến hành cập nhật
    if (empty($errors)) {
        try {
            // Bắt đầu transaction
            $conn->beginTransaction();
            
            // Cập nhật thông tin thông báo
            $stmtUpdate = $conn->prepare("
                UPDATE thongbao 
                SET IDLoaiThongBao = ?, TieuDe = ?, NoiDung = ?, GhiChu = ?
                WHERE ID = ?
            ");
            $stmtUpdate->execute([
                !empty($_POST['MaLTB']) ? $_POST['MaLTB'] : null,
                $_POST['sTieuDe'],
                $_POST['sNoiDung'],
                !empty($_POST['sGhiChu']) ? $_POST['sGhiChu'] : null,
                $idThongBao
            ]);
            
            // Thêm lịch sử chỉnh sửa
            $stmtAddHistory = $conn->prepare("
                INSERT INTO thongbao_nguoichinhsua (IDThongBao, IDNguoiChinhSua, NgayChinhSua, GhiChu)
                VALUES (?, ?, NOW(), ?)
            ");
            $stmtAddHistory->execute([
                $idThongBao,
                $tk['ID'],
                "Chỉnh sửa thông báo"
            ]);
            
            // Commit transaction
            $conn->commit();
            
            // Đánh dấu thành công và lấy lại dữ liệu
            $success = true;
            
            // Lấy lại thông tin thông báo sau khi cập nhật
            $stmtThongBao->execute([$idThongBao]);
            $thongBao = $stmtThongBao->fetch(PDO::FETCH_ASSOC);
            
            $_SESSION['message'] = "Đã cập nhật thông báo thành công!";
            $_SESSION['messageType'] = "success";
        } catch (PDOException $e) {
            // Rollback nếu có lỗi
            $conn->rollBack();
            error_log("Lỗi khi cập nhật thông báo: " . $e->getMessage());
            $errors[] = "Đã xảy ra lỗi khi cập nhật thông báo: " . $e->getMessage();
        }
    }
}

// Bắt đầu output buffer
ob_start();
?>

<!-- Phần nội dung trang -->
<div class="container-fluid px-4">
    <div class="row">
        <div class="col-12">
            <div class="card shadow-sm my-4">
                <div class="card-header bg-primary text-white py-3">
                    <div class="row align-items-center">
                        <div class="col">
                            <h4 class="mb-0"><i class="fas fa-edit me-2"></i>CHỈNH SỬA THÔNG BÁO</h4>
                        </div>
                    </div>
                </div>
                <div class="card-body">
                    <?php if ($success): ?>
                        <div class="alert alert-success alert-dismissible fade show" role="alert">
                            <i class="fas fa-check-circle me-2"></i>
                            Thông báo đã được cập nhật thành công!
                            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                        </div>
                    <?php endif; ?>
                    
                    <?php if (!empty($errors)): ?>
                        <div class="alert alert-danger alert-dismissible fade show" role="alert">
                            <ul class="mb-0">
                                <?php foreach ($errors as $error): ?>
                                    <li><?php echo $error; ?></li>
                                <?php endforeach; ?>
                            </ul>
                            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                        </div>
                    <?php endif; ?>
                    
                    <form method="post" class="needs-validation" id="editForm" novalidate>
                        <div class="mb-3 row">
                            <label for="sMaTB" class="col-sm-2 col-form-label">Mã thông báo:</label>
                            <div class="col-sm-10">
                                <input type="text" class="form-control-plaintext" id="sMaTB" name="sMaTB" 
                                       value="<?php echo htmlspecialchars($thongBao['ID']); ?>" readonly>
                            </div>
                        </div>
                        
                        <div class="mb-3 row">
                            <label for="MaLTB" class="col-sm-2 col-form-label">Loại thông báo:</label>
                            <div class="col-sm-10">
                                <select class="form-select" id="MaLTB" name="MaLTB">
                                    <option value="">-- Chọn loại thông báo --</option>
                                    <?php foreach ($dsLoaiTB as $loai): ?>
                                        <option value="<?php echo htmlspecialchars($loai['ID']); ?>" 
                                            <?php echo $thongBao['IDLoaiThongBao'] == $loai['ID'] ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($loai['Ten']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        
                        <div class="mb-3 row">
                            <label for="sTieuDe" class="col-sm-2 col-form-label">Tiêu đề: <span class="text-danger">*</span></label>
                            <div class="col-sm-10">
                                <input type="text" class="form-control" id="sTieuDe" name="sTieuDe" 
                                       value="<?php echo htmlspecialchars($thongBao['TieuDe']); ?>" required>
                                <div class="invalid-feedback">
                                    Vui lòng nhập tiêu đề thông báo
                                </div>
                            </div>
                        </div>
                        
                        <div class="mb-4 row">
                            <label for="sNoiDung" class="col-sm-2 col-form-label">Nội dung: <span class="text-danger">*</span></label>
                            <div class="col-sm-10">
                                <textarea id="sNoiDung" name="sNoiDung" class="form-control"><?php echo htmlspecialchars($thongBao['NoiDung']); ?></textarea>
                                <div class="invalid-feedback">
                                    Vui lòng nhập nội dung thông báo
                                </div>
                            </div>
                        </div>
                        
                        <div class="mb-4 row">
                            <label for="sGhiChu" class="col-sm-2 col-form-label">Ghi chú:</label>
                            <div class="col-sm-10">
                                <input type="text" class="form-control" id="sGhiChu" name="sGhiChu" 
                                       value="<?php echo htmlspecialchars($thongBao['GhiChu'] ?? ''); ?>">
                            </div>
                        </div>
                        
                        <div class="border-top pt-3 d-flex justify-content-end">
                            <a href="Index.php" class="btn btn-secondary me-2">
                                <i class="fas fa-arrow-left me-1"></i> Quay lại
                            </a>
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-save me-1"></i> Lưu thay đổi
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Thêm các file CSS và JS cho trình soạn thảo -->
<link href="https://cdn.jsdelivr.net/npm/summernote@0.8.18/dist/summernote-bs4.min.css" rel="stylesheet">
<script src="https://cdn.jsdelivr.net/npm/summernote@0.8.18/dist/summernote-bs4.min.js"></script>

<!-- Scripts cho chức năng editor -->
<script>
    document.addEventListener('DOMContentLoaded', function() {
        // Validate form khi submit
        const form = document.getElementById('editForm');
        form.addEventListener('submit', function(event) {
            if (!this.checkValidity()) {
                event.preventDefault();
                event.stopPropagation();
            }
            this.classList.add('was-validated');
        });
        
        // Khởi tạo trình soạn thảo văn bản
        if (typeof $.fn.summernote === 'function') {
            $('#sNoiDung').summernote({
                height: 400,
                toolbar: [
                    ['style', ['style']],
                    ['font', ['bold', 'underline', 'italic', 'clear', 'strikethrough', 'superscript', 'subscript']],
                    ['fontname', ['fontname']],
                    ['fontsize', ['fontsize']],
                    ['color', ['color']],
                    ['para', ['ul', 'ol', 'paragraph']],
                    ['table', ['table']],
                    ['insert', ['link', 'picture', 'video']],
                    ['view', ['fullscreen', 'codeview', 'help']],
                ],
                callbacks: {
                    onImageUpload: function(files) {
                        // Xử lý upload ảnh
                        for (let i = 0; i < files.length; i++) {
                            uploadImage(files[i], this);
                        }
                    }
                },
                placeholder: 'Nhập nội dung thông báo...',
                styleTags: ['p', 'h1', 'h2', 'h3', 'h4', 'h5', 'h6'],
                fontNames: ['Arial', 'Times New Roman', 'Courier New', 'Verdana', 'Roboto', 'Open Sans'],
                lang: 'vi-VN'
            });
        } else if (typeof CKEDITOR !== 'undefined') {
            // Sử dụng CKEditor nếu có sẵn
            CKEDITOR.replace('sNoiDung', {
                height: 400,
                filebrowserUploadUrl: '/TDMU_website/upload.php',
                toolbarGroups: [
                    { name: 'document', groups: [ 'mode', 'document', 'doctools' ] },
                    { name: 'clipboard', groups: [ 'clipboard', 'undo' ] },
                    { name: 'editing', groups: [ 'find', 'selection', 'spellchecker' ] },
                    { name: 'forms' },
                    '/',
                    { name: 'basicstyles', groups: [ 'basicstyles', 'cleanup' ] },
                    { name: 'paragraph', groups: [ 'list', 'indent', 'blocks', 'align', 'bidi' ] },
                    { name: 'links' },
                    { name: 'insert' },
                    '/',
                    { name: 'styles' },
                    { name: 'colors' },
                    { name: 'tools' },
                    { name: 'others' }
                ],
                removeButtons: 'Save,NewPage,Preview,Print,Templates',
                extraPlugins: 'justify,font,colorbutton,uploadimage,uploadfile',
                language: 'vi'
            });
        }
        
        // Hàm upload ảnh cho Summernote
        function uploadImage(file, editor) {
            const formData = new FormData();
            formData.append('file', file);
            
            $.ajax({
                url: '/TDMU_website/upload.php',
                method: 'POST',
                data: formData,
                contentType: false,
                processData: false,
                success: function(url) {
                    $(editor).summernote('insertImage', url);
                },
                error: function(jqXHR, textStatus, errorThrown) {
                    console.error(textStatus + ": " + errorThrown);
                    alert('Không thể tải lên hình ảnh. Vui lòng thử lại sau.');
                }
            });
        }
    });
</script>

<?php
// Lấy nội dung đã buffer và đưa vào layout
$contentForLayout = ob_get_clean();

// Kết nối với layout
include_once('../Shared/_LayoutAdmin.php');
?>
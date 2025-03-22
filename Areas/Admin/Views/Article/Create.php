<?php
// filepath: c:\xampp\htdocs\TDMU_website\Areas\Admin\Views\Article\Create.php
// Kết nối database
require_once("../Shared/connect.inc");

// Tiêu đề trang
$pageTitle = "Thêm mới bài viết";

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

// Lấy danh sách thể loại
$dsTheLoai = [];
try {
    $stmt = $conn->query("SELECT ID, Ten FROM tlbaiviet ORDER BY Ten");
    $dsTheLoai = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Lỗi truy vấn thể loại: " . $e->getMessage());
}

// Khởi tạo biến
$message = '';
$messageType = '';
$formData = [
    'MaTL' => '',
    'TieuDe' => '',
    'NoiDung' => '',
    'GhiChu' => ''
];

// Xử lý form submit
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // Lấy dữ liệu từ form
        $maTL = $_POST['MaTL'] ?? '';
        $tieuDe = $_POST['sTieuDe'] ?? '';
        $noiDung = $_POST['sNoiDung'] ?? '';
        $ghiChu = $_POST['sGhiChu'] ?? '';
        
        // Validate dữ liệu
        if (empty($tieuDe)) {
            throw new Exception("Vui lòng nhập tiêu đề bài viết");
        }
        
        if (empty($maTL)) {
            throw new Exception("Vui lòng chọn thể loại bài viết");
        }
        
        // Lưu dữ liệu đã nhập vào biến form để hiển thị lại nếu có lỗi
        $formData['MaTL'] = $maTL;
        $formData['TieuDe'] = $tieuDe;
        $formData['NoiDung'] = $noiDung;
        $formData['GhiChu'] = $ghiChu;
        
        // Tạo ID mới cho bài viết
        $stmt = $conn->query("SELECT MAX(CAST(SUBSTRING(ID, 3) AS UNSIGNED)) as max_id FROM baiviet WHERE ID LIKE 'BV%'");
        $maxId = $stmt->fetch(PDO::FETCH_ASSOC)['max_id'] ?? 0;
        $nextId = $maxId + 1;
        $newId = 'BV' . str_pad($nextId, 10, '0', STR_PAD_LEFT);
        
        // Thêm bài viết mới
        $stmt = $conn->prepare("
            INSERT INTO baiviet (ID, IDTLBaiViet, TieuDe, NoiDung, IDNguoiDang, NgayTao, GhiChu)
            VALUES (?, ?, ?, ?, ?, NOW(), ?)
        ");
        
        $idNguoiDung = $tk['ID'] ?? null;
        $stmt->execute([$newId, $maTL, $tieuDe, $noiDung, $idNguoiDung, $ghiChu]);
        
        $message = "Thêm bài viết thành công!";
        $messageType = "success";
        
        // Xóa dữ liệu form sau khi thêm thành công
        $formData = [
            'MaTL' => '',
            'TieuDe' => '',
            'NoiDung' => '',
            'GhiChu' => ''
        ];
        
    } catch (Exception $e) {
        $message = $e->getMessage();
        $messageType = "danger";
    }
}

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
                    <div class="card-header bg-primary text-white py-3 d-flex justify-content-between align-items-center">
                        <a href="Index.php" class="btn btn-light">
                            <i class="fas fa-arrow-left me-1"></i> Quay lại
                        </a>
                        <h2 class="text-center mb-0 flex-grow-1">THÊM MỚI BÀI VIẾT</h2>
                        <div style="width: 100px;"></div><!-- Phần tử ẩn để cân bằng layout -->
                    </div>
                    
                    <div class="card-body">
                        <?php if (!empty($message)): ?>
                            <div class="alert alert-<?php echo $messageType; ?> alert-dismissible fade show" role="alert">
                                <?php echo $message; ?>
                                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                            </div>
                        <?php endif; ?>
                        
                        <form method="post" id="createForm" class="needs-validation" novalidate>
                            <div class="row mb-4">
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="MaTL" class="form-label fw-bold">Thể loại bài viết <span class="text-danger">*</span></label>
                                        <select class="form-select" id="MaTL" name="MaTL" required>
                                            <option value="">-- Chọn thể loại --</option>
                                            <?php foreach ($dsTheLoai as $theLoai): ?>
                                                <option value="<?php echo htmlspecialchars($theLoai['ID']); ?>" 
                                                    <?php echo $formData['MaTL'] == $theLoai['ID'] ? 'selected' : ''; ?>>
                                                    <?php echo htmlspecialchars($theLoai['Ten']); ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                        <div class="invalid-feedback">Vui lòng chọn thể loại</div>
                                    </div>
                                </div>
                                
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="sTieuDe" class="form-label fw-bold">Tiêu đề bài viết <span class="text-danger">*</span></label>
                                        <input type="text" class="form-control" id="sTieuDe" name="sTieuDe" 
                                               value="<?php echo htmlspecialchars($formData['TieuDe']); ?>" required>
                                        <div class="invalid-feedback">Vui lòng nhập tiêu đề bài viết</div>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="mb-4">
                                <label for="sNoiDung" class="form-label fw-bold">Nội dung bài viết <span class="text-danger">*</span></label>
                                <textarea class="form-control" id="sNoiDung" name="sNoiDung" rows="10"><?php echo htmlspecialchars($formData['NoiDung']); ?></textarea>
                                <div class="invalid-feedback">Vui lòng nhập nội dung bài viết</div>
                            </div>
                            
                            <div class="mb-4">
                                <label for="sGhiChu" class="form-label fw-bold">Ghi chú</label>
                                <textarea class="form-control" id="sGhiChu" name="sGhiChu" rows="4"><?php echo htmlspecialchars($formData['GhiChu']); ?></textarea>
                                <small class="form-text text-muted">Thông tin thêm về bài viết (không bắt buộc)</small>
                            </div>
                            
                            <div class="d-flex justify-content-between mt-4">
                                <a href="Index.php" class="btn btn-secondary">
                                    <i class="fas fa-times me-1"></i> Hủy
                                </a>
                                <button type="submit" class="btn btn-success">
                                    <i class="fas fa-plus me-1"></i> Thêm bài viết
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
            const form = document.getElementById('createForm');
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
                    placeholder: 'Nhập nội dung bài viết...',
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
<?php endif; ?>

<?php
// Lấy nội dung đã buffer và đưa vào layout
$contentForLayout = ob_get_clean();

// Kết nối với layout
include_once('../Shared/_LayoutAdmin.php');
?>
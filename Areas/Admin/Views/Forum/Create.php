<?php
// filepath: c:\xampp\htdocs\TDMU_website\Areas\Admin\Views\Forum\Create.php

// Kết nối database và khởi tạo session
require_once("../Shared/connect.inc");
session_start();

// Tiêu đề trang
$pageTitle = "Đăng câu hỏi mới";

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

// Khởi tạo biến thông báo và giá trị mặc định cho form
$success = false;
$error = '';
$formData = [
    'MaCDCH' => '',
    'sTieuDe' => '',
    'sNoiDung' => '',
    'sGhiChu' => ''
];

// Lấy danh sách chủ đề câu hỏi
try {
    $stmtTopics = $conn->prepare("SELECT ID, Ten FROM chudecauhoi ORDER BY Ten");
    $stmtTopics->execute();
    $topics = $stmtTopics->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Lỗi truy vấn chủ đề câu hỏi: " . $e->getMessage());
    $topics = [];
}

// Xử lý khi form được gửi đi
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Lấy dữ liệu từ form
    $formData = [
        'MaCDCH' => $_POST['MaCDCH'] ?? '',
        'sTieuDe' => trim($_POST['sTieuDe'] ?? ''),
        'sNoiDung' => trim($_POST['sNoiDung'] ?? ''),
        'sGhiChu' => trim($_POST['sGhiChu'] ?? '')
    ];
    
    // Kiểm tra dữ liệu
    if (empty($formData['MaCDCH'])) {
        $error = "Vui lòng chọn chủ đề câu hỏi";
    } elseif (empty($formData['sTieuDe'])) {
        $error = "Vui lòng nhập tiêu đề câu hỏi";
    } elseif (empty($formData['sNoiDung'])) {
        $error = "Vui lòng nhập nội dung câu hỏi";
    } else {
        try {
            // Tạo ID mới cho câu hỏi
            $stmt = $conn->prepare("SELECT MAX(CAST(SUBSTRING(ID, 3) AS UNSIGNED)) as max_id FROM cauhoi");
            $stmt->execute();
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            $nextId = (int)($result['max_id'] ?? 0) + 1;
            $newQuestionId = 'CH' . str_pad($nextId, 8, '0', STR_PAD_LEFT);
            
            // Bắt đầu transaction
            $conn->beginTransaction();
            
            // Thêm câu hỏi mới
            $stmt = $conn->prepare("
                INSERT INTO cauhoi 
                (ID, IDNguoiGui, TieuDe, NoiDung, NgayGui, DuocDuyet, GhiChu) 
                VALUES (?, ?, ?, ?, NOW(), ?, ?)
            ");
            
            $duocDuyet = 0; // Mặc định chưa duyệt
            
            $stmt->execute([
                $newQuestionId,
                $tk['ID'],
                $formData['sTieuDe'],
                $formData['sNoiDung'],
                $duocDuyet,
                $formData['sGhiChu']
            ]);
            
            // Thêm liên kết với chủ đề
            $stmt = $conn->prepare("
                INSERT INTO cauhoi_chudecauhoi 
                (ID, IDCauHoi, IDChuDeCauHoi) 
                VALUES (?, ?, ?)
            ");
            
            $linkId = 'CHCD' . str_pad($nextId, 6, '0', STR_PAD_LEFT);
            $stmt->execute([
                $linkId,
                $newQuestionId,
                $formData['MaCDCH']
            ]);
            
            // Commit transaction
            $conn->commit();
            
            $success = true;
            
            // Reset form sau khi thêm thành công
            $formData = [
                'MaCDCH' => '',
                'sTieuDe' => '',
                'sNoiDung' => '',
                'sGhiChu' => ''
            ];
            
        } catch (PDOException $e) {
            // Rollback nếu có lỗi
            $conn->rollBack();
            error_log("Lỗi thêm câu hỏi: " . $e->getMessage());
            $error = "Đã xảy ra lỗi khi đăng câu hỏi. Vui lòng thử lại sau.";
        }
    }
}

// Bắt đầu buffer đầu ra
ob_start();
?>

<!-- CSS bổ sung cho trang -->
<link href="https://cdn.jsdelivr.net/npm/summernote@0.8.18/dist/summernote-bs4.min.css" rel="stylesheet">

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
        <!-- Breadcrumb -->
        <nav aria-label="breadcrumb" class="mb-3">
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="../Dashboard/Index.php">Trang chủ</a></li>
                <li class="breadcrumb-item"><a href="index.php">Diễn đàn</a></li>
                <li class="breadcrumb-item active">Đăng câu hỏi</li>
            </ol>
        </nav>
        
        <div class="row">
            <!-- Main content -->
            <div class="col-lg-8">
                <div class="card shadow-sm mb-4">
                    <div class="card-header bg-primary text-white d-flex justify-content-between align-items-center">
                        <h5 class="mb-0"><i class="fas fa-question-circle me-2"></i>Đăng câu hỏi mới</h5>
                    </div>
                    <div class="card-body">
                        <?php if ($success): ?>
                            <div class="alert alert-success">
                                <i class="fas fa-check-circle me-2"></i>
                                Câu hỏi của bạn đã được đăng thành công!
                                <div>Câu hỏi sẽ được hiển thị sau khi được phê duyệt bởi quản trị viên.</div>
                                <div class="mt-2">
                                    <a href="index.php" class="btn btn-sm btn-outline-success">
                                        <i class="fas fa-arrow-left me-1"></i> Quay lại diễn đàn
                                    </a>
                                </div>
                            </div>
                        <?php endif; ?>
                        
                        <?php if (!empty($error)): ?>
                            <div class="alert alert-danger">
                                <i class="fas fa-exclamation-circle me-2"></i><?php echo $error; ?>
                            </div>
                        <?php endif; ?>
                        
                        <?php if (!$success): ?>
                            <form method="post" enctype="multipart/form-data" class="needs-validation" novalidate>
                                <div class="mb-3">
                                    <label for="MaCDCH" class="form-label">Chủ đề câu hỏi <span class="text-danger">*</span></label>
                                    <select name="MaCDCH" id="MaCDCH" class="form-select" required>
                                        <option value="">-- Chọn chủ đề --</option>
                                        <?php foreach ($topics as $topic): ?>
                                            <option value="<?php echo $topic['ID']; ?>" <?php echo ($formData['MaCDCH'] == $topic['ID']) ? 'selected' : ''; ?>>
                                                <?php echo htmlspecialchars($topic['Ten']); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                    <div class="invalid-feedback">Vui lòng chọn chủ đề câu hỏi</div>
                                </div>
                                
                                <div class="mb-3">
                                    <label for="sTieuDe" class="form-label">Tiêu đề <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control" id="sTieuDe" name="sTieuDe" 
                                           value="<?php echo htmlspecialchars($formData['sTieuDe']); ?>" maxlength="200" required>
                                    <div class="invalid-feedback">Vui lòng nhập tiêu đề câu hỏi</div>
                                    <div class="form-text">Tiêu đề ngắn gọn, súc tích về vấn đề bạn đang gặp</div>
                                </div>
                                
                                <div class="mb-3">
                                    <label for="sNoiDung" class="form-label">Nội dung <span class="text-danger">*</span></label>
                                    <textarea class="form-control" id="sNoiDung" name="sNoiDung" rows="5" required><?php echo htmlspecialchars($formData['sNoiDung']); ?></textarea>
                                    <div class="invalid-feedback">Vui lòng nhập nội dung câu hỏi</div>
                                    <div class="form-text">Mô tả chi tiết vấn đề của bạn để nhận được câu trả lời chính xác</div>
                                </div>
                                
                                <div class="mb-4">
                                    <label for="sGhiChu" class="form-label">Ghi chú</label>
                                    <input type="text" class="form-control" id="sGhiChu" name="sGhiChu" 
                                           value="<?php echo htmlspecialchars($formData['sGhiChu']); ?>">
                                    <div class="form-text">Thông tin bổ sung (nếu có)</div>
                                </div>
                                
                                <div class="d-flex justify-content-between">
                                    <a href="index.php" class="btn btn-secondary">
                                        <i class="fas fa-arrow-left me-2"></i>Quay lại
                                    </a>
                                    <button type="submit" class="btn btn-primary">
                                        <i class="fas fa-paper-plane me-2"></i>Đăng câu hỏi
                                    </button>
                                </div>
                            </form>
                        <?php endif; ?>
                    </div>
                </div>
                
                <!-- Tips panel -->
                <div class="card shadow-sm mb-4">
                    <div class="card-header bg-info text-white">
                        <h5 class="mb-0"><i class="fas fa-lightbulb me-2"></i>Mẹo đăng câu hỏi hiệu quả</h5>
                    </div>
                    <div class="card-body">
                        <ul class="mb-0">
                            <li>Tìm kiếm trước để đảm bảo câu hỏi của bạn chưa được trả lời</li>
                            <li>Đặt tiêu đề ngắn gọn, rõ ràng và cụ thể</li>
                            <li>Mô tả chi tiết vấn đề bạn đang gặp phải</li>
                            <li>Nếu có thể, đính kèm hình ảnh minh họa cho vấn đề</li>
                            <li>Sử dụng định dạng văn bản để làm nổi bật các phần quan trọng</li>
                            <li>Kiểm tra lỗi chính tả trước khi đăng</li>
                        </ul>
                    </div>
                </div>
            </div>
            
            <!-- Sidebar content -->
            <div class="col-lg-4">
                <?php include('sidebar.php'); ?>
            </div>
        </div>
    </div>
    
    <!-- Scripts for rich text editor -->
    <script src="https://cdn.jsdelivr.net/npm/summernote@0.8.18/dist/summernote-bs4.min.js"></script>
    <script>
    $(document).ready(function() {
        // Initialize Summernote rich text editor
        $('#sNoiDung').summernote({
            placeholder: 'Nhập nội dung câu hỏi của bạn...',
            height: 200,
            toolbar: [
                ['style', ['bold', 'italic', 'underline', 'clear']],
                ['font', ['strikethrough']],
                ['para', ['ul', 'ol']],
                ['insert', ['link']],
                ['view', ['fullscreen', 'codeview']]
            ]
        });
        
        // Form validation
        (function() {
            'use strict';
            
            // Fetch all forms we want to apply custom validation
            var forms = document.querySelectorAll('.needs-validation');
            
            // Loop over and prevent submission
            Array.prototype.slice.call(forms).forEach(function(form) {
                form.addEventListener('submit', function(event) {
                    if (!form.checkValidity()) {
                        event.preventDefault();
                        event.stopPropagation();
                    }
                    
                    form.classList.add('was-validated');
                }, false);
            });
        })();
    });
    </script>
<?php endif; ?>

<?php
// Lấy nội dung đã buffer
$contentForLayout = ob_get_clean();

// Include template chung
include("../Shared/_LayoutAdmin.php");
?>
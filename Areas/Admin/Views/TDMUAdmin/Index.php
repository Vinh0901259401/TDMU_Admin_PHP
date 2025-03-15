<?php
    // Đảm bảo session được khởi tạo TRƯỚC KHI include bất cứ file nào khác
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    // Debug session để xem nó có tồn tại không
    error_log("Index.php - Session ID trước khi include: " . session_id());
    error_log("Index.php - TaiKhoan tồn tại: " . (isset($_SESSION['TaiKhoan']) ? "CÓ" : "KHÔNG"));
    if (isset($_SESSION['TaiKhoan'])) {
        error_log("Index.php - TaiKhoan data: " . json_encode($_SESSION['TaiKhoan'], JSON_UNESCAPED_UNICODE));
    }

    // Sau đó mới include các file khác
    include __DIR__ . '/../Shared/connect.inc';

    // Kiểm tra lại session sau khi include
    $tk = $_SESSION['TaiKhoan'] ?? null;
    error_log("Index.php - TaiKhoan sau khi include: " . ($tk ? "CÓ" : "KHÔNG"));

    $title = "Dashboard TDMU Admin";
    // Kiểm tra đăng nhập trước khi hiển thị nội dung
    if (!$tk) {
        // Sử dụng đường dẫn tuyệt đối thay vì đường dẫn hiện tại
        header('Location: /TDMU_website/Areas/Admin/Views/User/Login.php');
        exit();
    }
    // Kiểm tra quyền truy cập (giả sử có biến session hoặc từ DB)
    $accessLevel = 1; // Mặc định là 0 nếu không tìm thấy quyền
    if (isset($_SESSION['TaiKhoan']['IDQuyenTruyCap'])) {
        try {
            $quyenID = $_SESSION['TaiKhoan']['IDQuyenTruyCap'];
            $stmt = $conn->prepare("SELECT CapDo FROM quyentruycap WHERE ID = :id");
            $stmt->bindParam(':id', $quyenID);
            $stmt->execute();
            
            if ($stmt->rowCount() > 0) {
                $quyen = $stmt->fetch();
                $accessLevel = $quyen['CapDo'];
            }
        } catch (PDOException $e) {
            // Xử lý lỗi nếu cần
            error_log("Lỗi truy vấn quyền truy cập: " . $e->getMessage());
        }
    }
    // Dữ liệu thống kê (nên lấy từ database)
    $sinhVienNam1 = 7;
    $sinhVienNam2 = 5; 
    $sinhVienNam3 = 6;
    $sinhVienNam4 = 2;
    $tongSinhVien = $sinhVienNam1 + $sinhVienNam2 + $sinhVienNam3 + $sinhVienNam4;

    // Bắt đầu buffer để đưa nội dung vào layout
    ob_start();
?>

<?php if ($accessLevel > 4 || $accessLevel < 1): ?>
    <div class="alert alert-danger p-5 text-center">
        <div class="mb-3">
            <i class="fas fa-exclamation-triangle fa-5x text-danger"></i>
        </div>
        <h2 class="text-danger">RẤT TIẾC, BẠN KHÔNG CÓ QUYỀN XEM THÔNG TIN TRÊN TRANG NÀY!</h2>
        <h4>Liên hệ người quản trị để được phân quyền hoặc giải đáp các thắc mắc, xin cảm ơn!</h4>
    </div>
<?php else: ?>

<!-- Dashboard Cards -->
<section>
    <div class="sm-chart-sec my-3">
        <div class="container-fluid">
            <div class="row">
                <!-- Card 1: Tạo bài viết -->
                <div class="col-lg-3 col-md-6 col-sm-6 my-2">
                    <div class="card dashboard-card shadow-sm h-100" id="create-article">
                        <div class="card-body d-flex flex-column">
                            <div class="d-flex align-items-center mb-3">
                                <div class="icon-box bg-primary text-white rounded-circle p-3 me-3">
                                    <i class="fas fa-file-alt fa-fw"></i>
                                </div>
                                <div>
                                    <h6 class="text-muted mb-1">Bài viết</h6>
                                    <h5 class="card-title mb-0">Tạo bài viết</h5>
                                </div>
                            </div>
                            <div class="mt-auto text-end">
                                <a href="/TDMU_website/Admin/Article/Create" class="btn btn-sm btn-outline-primary">
                                    <i class="fas fa-plus me-1"></i> Tạo mới
                                </a>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Card 2: Gửi thông báo -->
                <div class="col-lg-3 col-md-6 col-sm-6 my-2">
                    <div class="card dashboard-card shadow-sm h-100" id="create-notify">
                        <div class="card-body d-flex flex-column">
                            <div class="d-flex align-items-center mb-3">
                                <div class="icon-box bg-warning text-white rounded-circle p-3 me-3">
                                    <i class="fas fa-bell fa-fw"></i>
                                </div>
                                <div>
                                    <h6 class="text-muted mb-1">Thông báo</h6>
                                    <h5 class="card-title mb-0">Gửi thông báo</h5>
                                </div>
                            </div>
                            <div class="mt-auto text-end">
                                <a href="/TDMU_website/Admin/Notify/Create" class="btn btn-sm btn-outline-warning">
                                    <i class="fas fa-paper-plane me-1"></i> Tạo mới
                                </a>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Card 3: Quản lý lớp học -->
                <div class="col-lg-3 col-md-6 col-sm-6 my-2">
                    <div class="card dashboard-card shadow-sm h-100" id="manage-class">
                        <div class="card-body d-flex flex-column">
                            <div class="d-flex align-items-center mb-3">
                                <div class="icon-box bg-success text-white rounded-circle p-3 me-3">
                                    <i class="fas fa-users fa-fw"></i>
                                </div>
                                <div>
                                    <h6 class="text-muted mb-1">Sinh viên</h6>
                                    <h5 class="card-title mb-0">Quản lý lớp học</h5>
                                </div>
                            </div>
                            <div class="mt-auto text-end">
                                <a href="/TDMU_website/Admin/Class/Index" class="btn btn-sm btn-outline-success">
                                    <i class="fas fa-cog me-1"></i> Quản lý
                                </a>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Card 4: Quản lý giảng viên -->
                <div class="col-lg-3 col-md-6 col-sm-6 my-2">
                    <div class="card dashboard-card shadow-sm h-100">
                        <div class="card-body d-flex flex-column">
                            <div class="d-flex align-items-center mb-3">
                                <div class="icon-box bg-info text-white rounded-circle p-3 me-3">
                                    <i class="fas fa-chalkboard-teacher fa-fw"></i>
                                </div>
                                <div>
                                    <h6 class="text-muted mb-1">Giảng viên</h6>
                                    <h5 class="card-title mb-0">Quản lý giảng viên</h5>
                                </div>
                            </div>
                            <div class="mt-auto text-end">
                                <a href="/TDMU_website/Admin/Teacher/Index" class="btn btn-sm btn-outline-info">
                                    <i class="fas fa-cog me-1"></i> Quản lý
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- Main Dashboard -->
<section class="dashboard-top-sec">
    <div class="container-fluid">
        <div class="row mb-3">
            <!-- Thống kê sinh viên -->
            <div class="col-lg-8">
                <div class="card shadow-sm">
                    <div class="card-header bg-white">
                        <h5 class="card-title mb-0">Thống kê sinh viên theo năm học</h5>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <!-- Thống kê tổng số sinh viên -->
                            <div class="col-md-4 border-end">
                                <div class="text-center py-3">
                                    <h6 class="text-muted">Tổng số sinh viên</h6>
                                    <div class="my-3">
                                        <span class="display-4 text-primary fw-bold"><?php echo $tongSinhVien; ?></span>
                                    </div>
                                    <p class="text-muted small">Cập nhật đến: <?php echo date('d/m/Y'); ?></p>
                                    <a href="#" class="btn btn-sm btn-primary">Xem chi tiết</a>
                                </div>
                            </div>
                            
                            <!-- Biểu đồ -->
                            <div class="col-md-8">
                                <ul class="nav nav-tabs" id="yearTabs" role="tablist">
                                    <li class="nav-item" role="presentation">
                                        <button class="nav-link active" id="y2022-tab" data-bs-toggle="tab" data-bs-target="#y2022" type="button" role="tab">Năm 2022</button>
                                    </li>
                                    <li class="nav-item" role="presentation">
                                        <button class="nav-link" id="y2023-tab" data-bs-toggle="tab" data-bs-target="#y2023" type="button" role="tab">Năm 2023</button>
                                    </li>
                                    <li class="nav-item" role="presentation">
                                        <button class="nav-link" id="y2024-tab" data-bs-toggle="tab" data-bs-target="#y2024" type="button" role="tab">Năm 2024</button>
                                    </li>
                                </ul>
                                <div class="tab-content p-3" id="yearTabContent">
                                    <div class="tab-pane fade show active" id="y2022" role="tabpanel">
                                        <canvas id="areaChart" height="250"></canvas>
                                    </div>
                                    <div class="tab-pane fade" id="y2023" role="tabpanel">
                                        <div class="text-center py-5 text-muted">
                                            <i class="fas fa-chart-bar fa-3x mb-3"></i>
                                            <p>Dữ liệu năm 2023 đang được cập nhật</p>
                                        </div>
                                    </div>
                                    <div class="tab-pane fade" id="y2024" role="tabpanel">
                                        <div class="text-center py-5 text-muted">
                                            <i class="fas fa-chart-bar fa-3x mb-3"></i>
                                            <p>Dữ liệu năm 2024 đang được cập nhật</p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <hr>

                        <!-- Thông tin chi tiết từng năm -->
                        <div class="row text-center">
                            <div class="col-md-3 col-6 py-2">
                                <div class="d-flex align-items-center justify-content-center">
                                    <div class="icon-box-sm bg-warning text-white rounded-circle me-2 d-flex align-items-center justify-content-center">
                                        <i class="fas fa-user-graduate"></i>
                                    </div>
                                    <div class="text-start">
                                        <div class="small text-muted">Năm nhất</div>
                                        <div class="fw-bold"><?php echo $sinhVienNam1; ?> sinh viên</div>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="col-md-3 col-6 py-2">
                                <div class="d-flex align-items-center justify-content-center">
                                    <div class="icon-box-sm bg-info text-white rounded-circle me-2 d-flex align-items-center justify-content-center">
                                        <i class="fas fa-user-graduate"></i>
                                    </div>
                                    <div class="text-start">
                                        <div class="small text-muted">Năm hai</div>
                                        <div class="fw-bold"><?php echo $sinhVienNam2; ?> sinh viên</div>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="col-md-3 col-6 py-2">
                                <div class="d-flex align-items-center justify-content-center">
                                    <div class="icon-box-sm bg-primary text-white rounded-circle me-2 d-flex align-items-center justify-content-center">
                                        <i class="fas fa-user-graduate"></i>
                                    </div>
                                    <div class="text-start">
                                        <div class="small text-muted">Năm ba</div>
                                        <div class="fw-bold"><?php echo $sinhVienNam3; ?> sinh viên</div>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="col-md-3 col-6 py-2">
                                <div class="d-flex align-items-center justify-content-center">
                                    <div class="icon-box-sm bg-success text-white rounded-circle me-2 d-flex align-items-center justify-content-center">
                                        <i class="fas fa-user-graduate"></i>
                                    </div>
                                    <div class="text-start">
                                        <div class="small text-muted">Năm tư</div>
                                        <div class="fw-bold"><?php echo $sinhVienNam4; ?> sinh viên</div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- To-do list -->
            <div class="col-lg-4">
                <div class="card shadow-sm">
                    <div class="card-header bg-white d-flex justify-content-between align-items-center">
                        <h5 class="card-title mb-0">
                            <i class="fas fa-tasks me-2"></i>
                            Việc cần làm
                        </h5>
                        <button type="button" class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#addTaskModal">
                            <i class="fas fa-plus"></i>
                        </button>
                    </div>
                    <div class="card-body p-0">
                        <ul class="list-group list-group-flush todo-list">
                            <li class="list-group-item py-3">
                                <div class="d-flex align-items-center">
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" id="task1">
                                        <label class="form-check-label" for="task1"></label>
                                    </div>
                                    <div class="ms-2 flex-grow-1">
                                        <div>Giải đáp thắc mắc cho sinh viên</div>
                                        <small class="text-danger">
                                            <i class="far fa-clock me-1"></i>Còn 2 phút
                                        </small>
                                    </div>
                                    <div class="task-actions">
                                        <button class="btn btn-sm text-primary"><i class="fas fa-edit"></i></button>
                                        <button class="btn btn-sm text-danger"><i class="fas fa-trash"></i></button>
                                    </div>
                                </div>
                            </li>
                            
                            <li class="list-group-item py-3">
                                <div class="d-flex align-items-center">
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" id="task2" checked>
                                        <label class="form-check-label" for="task2"></label>
                                    </div>
                                    <div class="ms-2 flex-grow-1">
                                        <div class="text-decoration-line-through">Gửi thông báo nhắc nợ học phí</div>
                                        <small class="text-info">
                                            <i class="far fa-clock me-1"></i>4 giờ trước
                                        </small>
                                    </div>
                                    <div class="task-actions">
                                        <button class="btn btn-sm text-primary"><i class="fas fa-edit"></i></button>
                                        <button class="btn btn-sm text-danger"><i class="fas fa-trash"></i></button>
                                    </div>
                                </div>
                            </li>
                            
                            <li class="list-group-item py-3">
                                <div class="d-flex align-items-center">
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" id="task3">
                                        <label class="form-check-label" for="task3"></label>
                                    </div>
                                    <div class="ms-2 flex-grow-1">
                                        <div>Đón con</div>
                                        <small class="text-warning">
                                            <i class="far fa-clock me-1"></i>Trong 1 ngày
                                        </small>
                                    </div>
                                    <div class="task-actions">
                                        <button class="btn btn-sm text-primary"><i class="fas fa-edit"></i></button>
                                        <button class="btn btn-sm text-danger"><i class="fas fa-trash"></i></button>
                                    </div>
                                </div>
                            </li>
                            
                            <li class="list-group-item py-3">
                                <div class="d-flex align-items-center">
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" id="task4">
                                        <label class="form-check-label" for="task4"></label>
                                    </div>
                                    <div class="ms-2 flex-grow-1">
                                        <div>Soạn đề thi HK1</div>
                                        <small class="text-success">
                                            <i class="far fa-clock me-1"></i>Trong 3 ngày
                                        </small>
                                    </div>
                                    <div class="task-actions">
                                        <button class="btn btn-sm text-primary"><i class="fas fa-edit"></i></button>
                                        <button class="btn btn-sm text-danger"><i class="fas fa-trash"></i></button>
                                    </div>
                                </div>
                            </li>
                            
                            <li class="list-group-item py-3">
                                <div class="d-flex align-items-center">
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" id="task5">
                                        <label class="form-check-label" for="task5"></label>
                                    </div>
                                    <div class="ms-2 flex-grow-1">
                                        <div>Kiểm tra và trả lời email</div>
                                        <small class="text-primary">
                                            <i class="far fa-clock me-1"></i>Trong 1 tuần
                                        </small>
                                    </div>
                                    <div class="task-actions">
                                        <button class="btn btn-sm text-primary"><i class="fas fa-edit"></i></button>
                                        <button class="btn btn-sm text-danger"><i class="fas fa-trash"></i></button>
                                    </div>
                                </div>
                            </li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- Modal thêm công việc mới -->
<div class="modal fade" id="addTaskModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Thêm công việc mới</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form id="taskForm">
                    <div class="mb-3">
                        <label for="taskName" class="form-label">Tên công việc</label>
                        <input type="text" class="form-control" id="taskName" required>
                    </div>
                    <div class="mb-3">
                        <label for="taskDueDate" class="form-label">Hạn chót</label>
                        <input type="datetime-local" class="form-control" id="taskDueDate">
                    </div>
                    <div class="mb-3">
                        <label for="taskPriority" class="form-label">Mức độ ưu tiên</label>
                        <select class="form-select" id="taskPriority">
                            <option value="low">Thấp</option>
                            <option value="medium" selected>Trung bình</option>
                            <option value="high">Cao</option>
                        </select>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Đóng</button>
                <button type="button" class="btn btn-primary">Lưu</button>
            </div>
        </div>
    </div>
</div>

<!-- CSS tùy chỉnh -->
<style>
    .dashboard-card {
        transition: all 0.3s ease;
        cursor: pointer;
    }
    
    .dashboard-card:hover {
        transform: translateY(-5px);
        box-shadow: 0 .5rem 1rem rgba(0,0,0,.15)!important;
    }
    
    .icon-box {
        width: 50px;
        height: 50px;
        display: flex;
        align-items: center;
        justify-content: center;
    }
    
    .icon-box-sm {
        width: 36px;
        height: 36px;
    }
    
    .todo-list li:hover .task-actions {
        opacity: 1;
    }
    
    .task-actions {
        opacity: 0.2;
        transition: all 0.3s ease;
    }
    
    @media (max-width: 768px) {
        .task-actions {
            opacity: 1;
        }
    }
</style>

<!-- JavaScript -->
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Chuyển hướng khi click vào card
    document.getElementById('create-article')?.addEventListener('click', function() {
        window.location.href = '/TDMU_website/Admin/Article/Create';
    });
    
    document.getElementById('create-notify')?.addEventListener('click', function() {
        window.location.href = '/TDMU_website/Admin/Notify/Create';
    });
    
    document.getElementById('manage-class')?.addEventListener('click', function() {
        window.location.href = '/TDMU_website/Admin/Class/Index';
    });
    
    // Khởi tạo biểu đồ
    if (document.getElementById('areaChart')) {
        const ctx = document.getElementById('areaChart').getContext('2d');
        new Chart(ctx, {
            type: 'line',
            data: {
                labels: ['Năm 1', 'Năm 2', 'Năm 3', 'Năm 4'],
                datasets: [{
                    label: 'Số lượng sinh viên',
                    data: [<?php echo $sinhVienNam1; ?>, <?php echo $sinhVienNam2; ?>, 
                           <?php echo $sinhVienNam3; ?>, <?php echo $sinhVienNam4; ?>],
                    backgroundColor: 'rgba(60,141,188,0.2)',
                    borderColor: 'rgba(60,141,188,1)',
                    borderWidth: 2,
                    pointRadius: 4,
                    pointBackgroundColor: '#fff',
                    pointBorderColor: 'rgba(60,141,188,1)',
                    tension: 0.3
                }]
            },
            options: {
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        display: false
                    }
                },
                scales: {
                    x: {
                        grid: {
                            display: false
                        }
                    },
                    y: {
                        beginAtZero: true,
                        grid: {
                            color: '#f0f0f0'
                        }
                    }
                }
            }
        });
    }
});
</script>
<?php endif; ?>

<?php
$contentForLayout = ob_get_clean();
require_once __DIR__ . '/../Shared/_LayoutAdmin.php';
?>
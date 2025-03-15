<?php
    #kết nối database connect.inc
    include __DIR__ . '/../Shared/connect.inc';
?>

<!-- filepath: c:\xampp\htdocs\TDMU_website\Areas\Admin\Views\Shared\_LayoutAdmin.php -->
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="utf-8" />
    <meta http-equiv="X-UA-Compatible" content="IE=edge" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title><?php echo isset($title) ? $title : 'TDMU Admin'; ?></title>

    <!-- FontAwesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" integrity="sha512-..." crossorigin="anonymous" referrerpolicy="no-referrer" />

    <!-- Bootstrap 5 CSS -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" integrity="sha384-..." crossorigin="anonymous">
    <!-- Custom PHP -->
 
    <!-- Custom CSS -->
    <style>
        body {
            padding-top: 0px;
            min-height: 100vh;
            display: flex;
            flex-direction: column;
        }
        .main-wrapper {
            flex: 1;
            display: flex;
            flex-direction: column;
        }
        .content-wrapper {
            flex: 1;
            padding: 20px 0;
        }
        .sidebar {
            position: sticky;
            height: calc(100vh - 70px);
            overflow-y: auto;
            padding: 15px 0;
            border-right: 1px solid #dee2e6;
        }
        .main-content {
            padding: 20px;
            min-height: calc(100vh - 120px); /* Account for navbar and footer */
        }
        footer {
            margin-top: auto;
            background-color: #f8f9fa;
            border-top: 1px solid #dee2e6;
            padding: 15px 0;
        }
    </style>

    <!-- jQuery -->
    <script src="https://code.jquery.com/jquery-3.7.0.min.js" integrity="sha384-..." crossorigin="anonymous"></script>

    <!-- Bootstrap JS Bundle -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" integrity="sha384-..." crossorigin="anonymous"></script>

    <!-- TinyMCE -->
    <script src="https://cdn.tiny.cloud/1/no-api-key/tinymce/6/tinymce.min.js" referrerpolicy="origin"></script>
    <script>
        tinymce.init({ selector: 'textarea.mc-tinymce' });
    </script>

    <!-- Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.0.1/dist/chart.min.js" integrity="sha384-..." crossorigin="anonymous"></script>
</head>


<body>
    <!-- Navigation -->
    <?php include __DIR__ . '/NavPartial.php'; ?>

    <!-- Main Content -->
    <div class="main-wrapper">
        <div class="content-wrapper">
            <div class="container-fluid">
                <div class="row">
                    <?php $sidebarCollapsed = isset($_SESSION['sidebarCollapsed']) ? $_SESSION['sidebarCollapsed'] : false; ?>
                    
                    <!-- Sidebar luôn tồn tại trong DOM, chỉ thay đổi class -->
                    <div class="col-md-2 sidebar-container <?php echo $sidebarCollapsed ? 'd-none' : ''; ?>">
                        <div class="sidebar <?php echo $sidebarCollapsed ? 'collapsed' : ''; ?>">
                            <?php include __DIR__ . '/SideBarPartial.php'; ?>
                        </div>
                    </div>
                    
                    <!-- Main content thay đổi class và kích thước -->
                    <div class="<?php echo $sidebarCollapsed ? 'col-md-12' : 'col-md-10'; ?>">
                        <div class="main-content expanded <?php echo $sidebarCollapsed?>">
                            <div class="content-container" style="max-width: 1000px;">
                                <?php echo $contentForLayout; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Footer -->
        <footer>
            <div class="container-fluid">
                <?php include __DIR__ . '/FooterPartial.php'; ?>
            </div>
        </footer>
    </div>

    <style>
        /* Thêm style cho container nội dung cố định */
        .content-container {
            max-width: 800px; /* Kích thước cố định cho nội dung */
            margin: 0 auto; /* Căn giữa container */
        }
        
        /* Điều chỉnh cho trang đăng nhập */
        .login-container .content-container {
            max-width: 100%; /* Cho phép form login sử dụng toàn bộ không gian */
        }
    </style>

    <!-- Additional Scripts -->
    <script src="/Areas/Admin/Assets/js/jquery.min.js"></script>
    <script src="/Areas/Admin/Assets/js/bootstrap/bootstrap.bundle.min.js"></script>
    <script src="/Areas/Admin/Assets/js/main.js"></script>
    <script src="/Areas/Admin/Scripts/chart.js/Chart.min.js"></script>
</body>
</html>
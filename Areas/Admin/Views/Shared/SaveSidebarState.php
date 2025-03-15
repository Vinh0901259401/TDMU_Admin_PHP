<?php
session_start();

if (isset($_POST['collapsed'])) {
    // Lưu trạng thái sidebar vào session
    $_SESSION['sidebarCollapsed'] = ($_POST['collapsed'] === '1');
    echo json_encode(['success' => true]);
} else {
    echo json_encode(['success' => false, 'message' => 'Dữ liệu không hợp lệ']);
}
?>
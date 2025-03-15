<!-- Nav notify -->
<li class="nav-item dropdown user-profile-dropdown mx-1">
    <?php if ($tk == null): ?>
        <a href="#" class="nav-link user" id="Notify" data-bs-toggle="dropdown">
            <img src="/TDMU_website/Areas/Admin/Assets/img/bell.png" class="icon" />
            <p class="count purple-gradient">1</p>
        </a>
        <div class="dropdown-menu dropdown-menu-end" style="width: 400px; max-height: 500px; overflow-y: auto;">
            <div class="dropdown-header border-bottom">
                <p class="note-title fw-bold mb-0 py-2">Thông báo hệ thống</p>
            </div>
            <div class="dp-main-menu">
                <a href="#" class="dropdown-item message-item">
                    <img src="/TDMU_website/Areas/Admin/Assets/img/robot.png" alt="" class="user-note" />
                    <div class="note-info-desmis">
                        <div class="user-notify-info">
                            <p class="note-time">Vui lòng đăng nhập để xem thông báo</p>
                        </div>
                    </div>
                </a>
            </div>
        </div>
    <?php else: ?>
        <?php
        // Lấy ID của người dùng hiện tại
        $currentUserID = $tk['ID'] ?? null;
        
        // Lấy thông báo mới nhất cho người dùng
        $notifications = [];
        $totalNotifications = 0;
        
        if ($currentUserID) {
            try {
                // Đếm tổng số thông báo
                $countStmt = $conn->prepare("
                    SELECT COUNT(*) as total
                    FROM thongbao_nguoinhan tbnn
                    WHERE tbnn.IDNguoiNhan = ?
                ");
                $countStmt->execute([$currentUserID]);
                $totalNotifications = $countStmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;
                
                // Lấy 5 thông báo gần nhất
                $stmt = $conn->prepare("
                    SELECT 
                        tb.ID, tb.TieuDe, tb.NoiDung, 
                        tbnn.ID as IDThongBaoNguoiNhan, tbnn.NgayNhan,
                        ltb.Ten as LoaiThongBao, ltb.CapDo
                    FROM thongbao_nguoinhan tbnn
                    JOIN thongbao tb ON tbnn.IDThongBao = tb.ID
                    LEFT JOIN loaithongbao ltb ON tb.IDLoaiThongBao = ltb.ID
                    WHERE tbnn.IDNguoiNhan = ?
                    ORDER BY ltb.CapDo ASC, tbnn.NgayNhan DESC
                    LIMIT 5
                ");
                $stmt->execute([$currentUserID]);
                $notifications = $stmt->fetchAll(PDO::FETCH_ASSOC);
                
            } catch (PDOException $e) {
                error_log("Lỗi truy vấn thông báo: " . $e->getMessage());
            }
        }
        
        // Đếm số thông báo hiển thị
        $notifyCount = count($notifications);
        ?>
        
        <a href="#" class="nav-link user" id="Notify" data-bs-toggle="dropdown">
            <img src="/TDMU_website/Areas/Admin/Assets/img/bell.png" class="icon" />
            <p class="count purple-gradient"><?php echo $notifyCount > 0 ? $notifyCount : '0'; ?></p>
        </a>
        <div class="dropdown-menu dropdown-menu-end" style="width: 400px; max-height: 500px; overflow-y: auto;">
            <div class="dropdown-header border-bottom">
                <p class="note-title fw-bold mb-0 py-2">Thông báo hệ thống</p>
            </div>
            <div class="dp-main-menu">
                <?php if (!empty($notifications)): ?>
                    <?php foreach ($notifications as $notify): ?>
                        <a href="/TDMU_website/Areas/Admin/Views/Notify/Details.php?maTB=<?php echo $notify['ID']; ?>" class="dropdown-item message-item">
                            <?php
                            // Chọn biểu tượng dựa trên cấp độ thông báo
                            $iconSrc = '/TDMU_website/Areas/Admin/Assets/img/';
                            switch ($notify['CapDo']) {
                                case 1: $iconSrc .= 'server.png'; break;  // Cao nhất
                                case 2: $iconSrc .= 'error.png'; break;
                                case 3: $iconSrc .= 'notes.png'; break;
                                default: $iconSrc .= 'bell.png'; break;
                            }
                            ?>
                            <img src="<?php echo $iconSrc; ?>" alt="" class="user-note" />
                            <div class="note-info-desmis" style="width: 320px; overflow: hidden;">
                                <div class="user-notify-info">
                                    <p class="note-name" style="white-space: nowrap; overflow: hidden; text-overflow: ellipsis; margin-bottom: 3px;"><?php echo $notify['TieuDe']; ?></p>
                                    <p class="note-time" style="white-space: nowrap; overflow: hidden; text-overflow: ellipsis;"><?php echo $notify['NoiDung']; ?></p>
                                </div>
                            </div>
                        </a>
                    <?php endforeach; ?>
                    
                    <?php if ($totalNotifications > 5): ?>
                        <div class="dropdown-divider"></div>
                        <a href="/TDMU_website/Areas/Admin/Views/Notify/Index.php" class="dropdown-item text-center">
                            <small class="text-primary">Xem thêm thông báo</small>
                        </a>
                    <?php endif; ?>
                <?php else: ?>
                    <div class="dropdown-item message-item">
                        <img src="/TDMU_website/Areas/Admin/Assets/img/robot.png" alt="" class="user-note" />
                        <div class="note-info-desmis" style="width: 320px; overflow: hidden;">
                            <div class="user-notify-info">
                                <p class="note-time" style="white-space: nowrap; overflow: hidden; text-overflow: ellipsis;">Không có thông báo mới</p>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    <?php endif; ?>
</li>
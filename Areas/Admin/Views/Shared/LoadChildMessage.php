<!-- Nav message -->
<li class="nav-item dropdown user-profile-dropdown mx-1">
    <?php if ($tk == null): ?>
        <a href="#" class="nav-link user" id="Message" data-bs-toggle="dropdown">
            <img src="/TDMU_website/Areas/Admin/Assets/img/message.png" class="icon" />
            <p class="count bg-clc">1</p>
        </a>
        <div class="dropdown-menu dropdown-menu-end" style="width: 300px; max-height: 500px; overflow-y: auto;">
            <div class="dropdown-header border-bottom">
                <p class="note-title fw-bold mb-0 py-2">Tin nhắn</p>
            </div>
            <div class="dp-main-menu">
                <a href="#" class="dropdown-item message-item">
                    <img src="/TDMU_website/Areas/Admin/Assets/img/robot.png" alt="" class="sms-user" />
                    <div class="user-message-info">
                        <p class="m-user-name mb-0 fw-bold">Hệ Thống</p>
                        <p class="user-role mb-0 text-muted">Chào bạn, vui lòng đăng nhập để sử dụng đầy đủ tính năng!</p>
                    </div>
                </a>
            </div>
        </div>
    <?php else: ?>
        <?php
        // Debug để kiểm tra ID người dùng
        error_log("LoadChildMessage - ID người dùng: " . ($tk['ID'] ?? 'không có ID'));

        // Lấy ID của người dùng hiện tại
        $currentUserID = $tk['ID'] ?? null;
        
        // Lấy tin nhắn mới nhất gửi đến người dùng hiện tại
        $messages = [];
        $totalMessages = 0;
        
        if ($currentUserID) {
            try {
                // Đếm tổng số tin nhắn
                $countStmt = $conn->prepare("
                    SELECT COUNT(*) as total
                    FROM tinnhan_guiden 
                    WHERE IDNguoiNhan = ?
                ");
                $countStmt->execute([$currentUserID]);
                $totalMessages = $countStmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;
                
                // Bước 1: Lấy thông tin từ bảng tinnhan_guiden
                $stmt = $conn->prepare("
                    SELECT * FROM tinnhan_guiden 
                    WHERE IDNguoiNhan = ?
                    ORDER BY ID DESC
                    LIMIT 5
                ");
                $stmt->execute([$currentUserID]);
                $tinNhanGuiDen = $stmt->fetchAll(PDO::FETCH_ASSOC);
                
                // Debug
                error_log("Tin nhắn gửi đến: " . json_encode($tinNhanGuiDen));
                
                // Bước 2: Lấy thông tin chi tiết của tin nhắn
                if (!empty($tinNhanGuiDen)) {
                    foreach ($tinNhanGuiDen as $tnGD) {
                        $stmt = $conn->prepare("
                            SELECT tn.*, tk.HoTen as TenNG, tk.ImagePath
                            FROM tinnhan tn
                            JOIN taikhoan tk ON tn.IDTaiKhoan = tk.ID
                            WHERE tn.ID = ?
                        ");
                        $stmt->execute([$tnGD['IDTinNhan']]);
                        $message = $stmt->fetch(PDO::FETCH_ASSOC);
                        
                        if ($message) {
                            $messages[] = array_merge($message, ['IDTinNhanGuiDen' => $tnGD['ID']]);
                        }
                    }
                }
                
                error_log("Chi tiết tin nhắn: " . json_encode($messages));
            } catch (PDOException $e) {
                error_log("Lỗi truy vấn tin nhắn: " . $e->getMessage());
            }
        }
        
        // Đếm tổng số tin nhắn chưa đọc của người dùng
        $unreadCount = count($messages);
        ?>
        
        <a href="#" class="nav-link user" id="Message" data-bs-toggle="dropdown">
            <img src="/TDMU_website/Areas/Admin/Assets/img/message.png" class="icon" />
            <p class="count bg-clc"><?php echo $totalMessages > 0 ? $totalMessages : '0'; ?></p>
        </a>
        <div class="dropdown-menu dropdown-menu-end" style="width: 300px; max-height: 500px; overflow-y: auto;">
            <div class="dropdown-header border-bottom">
                <p class="note-title fw-bold mb-0 py-2">Tin nhắn</p>
            </div>
            <div class="dp-main-menu">
                <?php if (!empty($messages)): ?>
                    <?php foreach ($messages as $message): ?>
                        <a href="/TDMU_website/Areas/Admin/Views/Message/Index.php" class="dropdown-item message-item">
                            <img src="/TDMU_website/Areas/Admin/Assets/img/user-image/<?php echo htmlspecialchars($message['ImagePath'] ?? 'default-avatar.png'); ?>" alt="" class="sms-user" />
                            <div class="user-message-info">
                                <p class="m-user-name mb-0 fw-bold" style="white-space: nowrap; overflow: hidden; text-overflow: ellipsis;"><?php echo htmlspecialchars($message['TenNG']); ?></p>
                                <p class="user-role mb-0 text-muted" style="white-space: nowrap; overflow: hidden; text-overflow: ellipsis;"><?php echo htmlspecialchars(substr($message['NoiDung'], 0, 40) . (strlen($message['NoiDung']) > 40 ? '...' : '')); ?></p>
                            </div>
                        </a>
                    <?php endforeach; ?>
                    
                    <?php if ($totalMessages > 5): ?>
                        <div class="dropdown-divider"></div>
                        <a href="/TDMU_website/Areas/Admin/Views/Message/Index.php" class="dropdown-item text-center">
                            <small class="text-primary">Xem thêm tin nhắn</small>
                        </a>
                    <?php endif; ?>
                <?php else: ?>
                    <a href="#" class="dropdown-item message-item">
                        <img src="/TDMU_website/Areas/Admin/Assets/img/robot.png" alt="" class="sms-user" />
                        <div class="user-message-info">
                            <p class="m-user-name mb-0 fw-bold">Hệ Thống</p>
                            <p class="user-role mb-0 text-muted">Không có tin nhắn mới</p>
                        </div>
                    </a>
                <?php endif; ?>
            </div>
        </div>
    <?php endif; ?>
</li>
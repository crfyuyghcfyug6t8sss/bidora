<?php
require_once 'config/database.php';
require_once 'includes/functions.php';

if (!isLoggedIn()) {
    redirect('auth/login.php');
}

$user_id = $_SESSION['user_id'];

// تحديث الإشعارات كمقروءة إذا تم الضغط
if (isset($_GET['mark_read']) && $_GET['mark_read'] == 'all') {
    $stmt = $conn->prepare("UPDATE notifications SET is_read = 1 WHERE user_id = ?");
    $stmt->execute([$user_id]);
    redirect('notifications.php');
}

if (isset($_GET['mark_read']) && is_numeric($_GET['mark_read'])) {
    $stmt = $conn->prepare("UPDATE notifications SET is_read = 1 WHERE id = ? AND user_id = ?");
    $stmt->execute([$_GET['mark_read'], $user_id]);
    redirect('notifications.php');
}

// جلب الإشعارات
$stmt = $conn->prepare("
    SELECT * FROM notifications 
    WHERE user_id = ? 
    ORDER BY created_at DESC 
    LIMIT 50
");
$stmt->execute([$user_id]);
$notifications = $stmt->fetchAll();

// تصنيف الإشعارات
$unread = array_filter($notifications, fn($n) => !$n['is_read']);
$read = array_filter($notifications, fn($n) => $n['is_read']);
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>الإشعارات - مزادات السيارات</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background: #f5f5f5; direction: rtl; }
        .header { background: #2563eb; color: white; padding: 20px 0; box-shadow: 0 2px 5px rgba(0,0,0,0.1); }
        .container { max-width: 900px; margin: 0 auto; padding: 0 20px; }
        .header h1 { font-size: 1.5rem; }
        .content { padding: 40px 20px; }
        .page-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 30px; }
        .page-title h2 { font-size: 2rem; color: #1e293b; }
        .mark-all-read { background: #10b981; color: white; padding: 10px 20px; border-radius: 8px; text-decoration: none; font-weight: 600; transition: 0.3s; }
        .mark-all-read:hover { background: #059669; }
        .notifications-list { display: flex; flex-direction: column; gap: 15px; }
        .notification { background: white; border-radius: 12px; padding: 20px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); display: flex; gap: 15px; align-items: start; transition: 0.3s; position: relative; }
        .notification:hover { box-shadow: 0 5px 15px rgba(0,0,0,0.15); }
        .notification.unread { border-right: 4px solid #2563eb; background: #f0f9ff; }
        .notification-icon { width: 50px; height: 50px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 1.5rem; flex-shrink: 0; }
        .icon-bid { background: #dbeafe; color: #2563eb; }
        .icon-outbid { background: #fee2e2; color: #dc2626; }
        .icon-won { background: #d1fae5; color: #059669; }
        .icon-auction_end { background: #fef3c7; color: #d97706; }
        .icon-system { background: #e9d5ff; color: #9333ea; }
        .notification-content { flex: 1; }
        .notification-title { font-weight: 600; color: #1e293b; margin-bottom: 5px; font-size: 1.1rem; }
        .notification-message { color: #64748b; line-height: 1.6; margin-bottom: 8px; }
        .notification-time { font-size: 0.85rem; color: #94a3b8; }
        .notification-actions { display: flex; gap: 10px; margin-top: 10px; }
        .btn-small { padding: 6px 12px; background: #2563eb; color: white; text-decoration: none; border-radius: 6px; font-size: 0.85rem; transition: 0.3s; border: none; cursor: pointer; }
        .btn-small:hover { background: #1d4ed8; }
        .btn-outline-small { background: transparent; border: 1px solid #cbd5e1; color: #64748b; }
        .btn-outline-small:hover { background: #f8fafc; }
        .empty-state { text-align: center; padding: 60px 20px; background: white; border-radius: 15px; }
        .empty-state-icon { font-size: 5rem; margin-bottom: 20px; opacity: 0.5; }
        .tabs { display: flex; gap: 10px; margin-bottom: 20px; border-bottom: 2px solid #e2e8f0; }
        .tab { padding: 12px 20px; background: none; border: none; font-size: 0.95rem; font-weight: 600; color: #64748b; cursor: pointer; border-bottom: 3px solid transparent; transition: 0.3s; }
        .tab.active { color: #2563eb; border-bottom-color: #2563eb; }
        .tab-content { display: none; }
        .tab-content.active { display: block; }
    </style>
</head>
<body>
    <div class="header">
        <div class="container">
            <h1>الإشعارات</h1>
        </div>
    </div>
                    <div style="position: fixed; top: 20px; left: 20px; z-index: 1000;">
        <?php include 'includes/lang-switcher.php'; ?>
    </div>
    <script src="js/auto-translate.js"></script>

    <div class="content">
        <div class="container">
            <div class="page-header">
                <div class="page-title">
                    <h2>الإشعارات</h2>
                    <?php if (count($unread) > 0): ?>
                        <p style="color: #64748b; margin-top: 5px;">لديك <?php echo count($unread); ?> إشعار جديد</p>
                    <?php endif; ?>
                </div>
                <?php if (count($unread) > 0): ?>
                    <a href="?mark_read=all" class="mark-all-read">تحديد الكل كمقروء</a>
                <?php endif; ?>
            </div>

            <div class="tabs">
                <button class="tab active" onclick="switchTab('all')">
                    الكل (<?php echo count($notifications); ?>)
                </button>
                <button class="tab" onclick="switchTab('unread')">
                    غير مقروءة (<?php echo count($unread); ?>)
                </button>
            </div>

            <!-- All Notifications -->
            <div id="all" class="tab-content active">
                <?php if (empty($notifications)): ?>
                    <div class="empty-state">
                        <div class="empty-state-icon">🔔</div>
                        <h3>لا توجد إشعارات</h3>
                        <p>سنرسل لك إشعارات عن نشاطاتك في المزادات</p>
                    </div>
                <?php else: ?>
                    <div class="notifications-list">
                        <?php foreach ($notifications as $notif): ?>
                            <div class="notification <?php echo !$notif['is_read'] ? 'unread' : ''; ?>">
                                <div class="notification-icon icon-<?php echo $notif['type']; ?>">
                                    <?php
                                    $icons = [
                                        'bid' => '🔨',
                                        'outbid' => '⚠️',
                                        'won' => '🏆',
                                        'auction_end' => '⏰',
                                        'system' => '📢'
                                    ];
                                    echo $icons[$notif['type']] ?? '📬';
                                    ?>
                                </div>
                                <div class="notification-content">
                                    <div class="notification-title"><?php echo htmlspecialchars($notif['title']); ?></div>
                                    <div class="notification-message"><?php echo htmlspecialchars($notif['message']); ?></div>
                                    <div class="notification-time">
                                        <?php
                                        $time_diff = time() - strtotime($notif['created_at']);
                                        if ($time_diff < 60) echo 'الآن';
                                        elseif ($time_diff < 3600) echo floor($time_diff / 60) . ' دقيقة';
                                        elseif ($time_diff < 86400) echo floor($time_diff / 3600) . ' ساعة';
                                        else echo floor($time_diff / 86400) . ' يوم';
                                        ?>
                                    </div>
                                    <div class="notification-actions">
                                        <?php if ($notif['reference_id']): ?>
                                            <a href="auction-details.php?id=<?php echo $notif['reference_id']; ?>" class="btn-small">
                                                عرض المزاد
                                            </a>
                                        <?php endif; ?>
                                        <?php if (!$notif['is_read']): ?>
                                            <a href="?mark_read=<?php echo $notif['id']; ?>" class="btn-small btn-outline-small">
                                                تحديد كمقروء
                                            </a>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Unread Notifications -->
            <div id="unread" class="tab-content">
                <?php if (empty($unread)): ?>
                    <div class="empty-state">
                        <div class="empty-state-icon">✅</div>
                        <h3>لا توجد إشعارات جديدة</h3>
                        <p>جميع إشعاراتك مقروءة</p>
                    </div>
                <?php else: ?>
                    <div class="notifications-list">
                        <?php foreach ($unread as $notif): ?>
                            <div class="notification unread">
                                <div class="notification-icon icon-<?php echo $notif['type']; ?>">
                                    <?php
                                    $icons = [
                                        'bid' => '🔨',
                                        'outbid' => '⚠️',
                                        'won' => '🏆',
                                        'auction_end' => '⏰',
                                        'system' => '📢'
                                    ];
                                    echo $icons[$notif['type']] ?? '📬';
                                    ?>
                                </div>
                                <div class="notification-content">
                                    <div class="notification-title"><?php echo htmlspecialchars($notif['title']); ?></div>
                                    <div class="notification-message"><?php echo htmlspecialchars($notif['message']); ?></div>
                                    <div class="notification-time">
                                        <?php
                                        $time_diff = time() - strtotime($notif['created_at']);
                                        if ($time_diff < 60) echo 'الآن';
                                        elseif ($time_diff < 3600) echo floor($time_diff / 60) . ' دقيقة';
                                        elseif ($time_diff < 86400) echo floor($time_diff / 3600) . ' ساعة';
                                        else echo floor($time_diff / 86400) . ' يوم';
                                        ?>
                                    </div>
                                    <div class="notification-actions">
                                        <?php if ($notif['reference_id']): ?>
                                            <a href="auction-details.php?id=<?php echo $notif['reference_id']; ?>" class="btn-small">
                                                عرض المزاد
                                            </a>
                                        <?php endif; ?>
                                        <a href="?mark_read=<?php echo $notif['id']; ?>" class="btn-small btn-outline-small">
                                            تحديد كمقروء
                                        </a>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>

            <div style="text-align: center; margin-top: 30px;">
                <a href="dashboard.php" style="color: #64748b; text-decoration: none;">← العودة للوحة التحكم</a>
            </div>
        </div>
    </div>

    <script>
        function switchTab(tabName) {
            document.querySelectorAll('.tab-content').forEach(content => {
                content.classList.remove('active');
            });
            document.querySelectorAll('.tab').forEach(tab => {
                tab.classList.remove('active');
            });
            document.getElementById(tabName).classList.add('active');
            event.target.classList.add('active');
        }
    </script>
</body>
</html>
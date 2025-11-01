<?php
require_once 'config/database.php';
require_once 'includes/functions.php';

if (!isLoggedIn()) {
    redirect('auth/login.php');
}

$user_id = $_SESSION['user_id'];

// جلب جميع المحادثات
$stmt = $conn->prepare("
    SELECT 
        ch.*,
        sc.status as sale_status,
        sc.sale_amount,
        v.brand, v.model, v.year,
        CASE 
            WHEN ch.seller_id = ? THEN buyer.username 
            ELSE seller.username 
        END as other_user,
        CASE 
            WHEN ch.seller_id = ? THEN 'buyer' 
            ELSE 'seller' 
        END as my_role,
        (SELECT COUNT(*) FROM sale_messages WHERE chat_id = ch.id AND sender_id != ? AND is_read = FALSE) as unread_count,
        (SELECT message FROM sale_messages WHERE chat_id = ch.id ORDER BY created_at DESC LIMIT 1) as last_message,
        (SELECT created_at FROM sale_messages WHERE chat_id = ch.id ORDER BY created_at DESC LIMIT 1) as last_message_time
    FROM sale_chats ch
    JOIN sales_confirmations sc ON ch.sale_confirmation_id = sc.id
    JOIN auctions a ON ch.auction_id = a.id
    JOIN vehicles v ON a.vehicle_id = v.id
    JOIN users seller ON ch.seller_id = seller.id
    JOIN users buyer ON ch.buyer_id = buyer.id
    WHERE ch.seller_id = ? OR ch.buyer_id = ?
ORDER BY 
    CASE WHEN last_message_time IS NULL THEN 1 ELSE 0 END,
    last_message_time DESC,
    ch.created_at DESC");
$stmt->execute([$user_id, $user_id, $user_id, $user_id, $user_id]);
$chats = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>محادثاتي</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Segoe UI', sans-serif; background: #f8fafc; direction: rtl; }
        .header { background: linear-gradient(135deg, #06b6d4 0%, #0891b2 100%); color: white; padding: 20px 0; box-shadow: 0 4px 15px rgba(6, 182, 212, 0.2); }
        .container { max-width: 1200px; margin: 0 auto; padding: 0 30px; }
        .header h1 { display: flex; align-items: center; gap: 15px; font-size: 1.8rem; }
        .content { padding: 40px 0; }
        .back-link { display: inline-flex; align-items: center; gap: 8px; color: #06b6d4; text-decoration: none; font-weight: 600; margin-bottom: 25px; transition: 0.3s; }
        .back-link:hover { gap: 12px; }
        .chats-list { display: grid; gap: 20px; }
        .chat-item { background: white; border-radius: 15px; padding: 25px; box-shadow: 0 4px 15px rgba(0,0,0,0.08); display: flex; gap: 20px; transition: 0.3s; text-decoration: none; color: inherit; position: relative; }
        .chat-item:hover { transform: translateY(-3px); box-shadow: 0 8px 25px rgba(0,0,0,0.12); }
        .chat-item.has-unread { border-right: 4px solid #06b6d4; }
        .chat-avatar { width: 60px; height: 60px; border-radius: 50%; background: linear-gradient(135deg, #06b6d4 0%, #0891b2 100%); display: flex; align-items: center; justify-content: center; color: white; font-size: 1.5rem; font-weight: bold; flex-shrink: 0; }
        .chat-info { flex: 1; }
        .chat-header { display: flex; justify-content: space-between; margin-bottom: 10px; }
        .chat-title { font-size: 1.2rem; font-weight: 600; color: #1e293b; margin-bottom: 5px; }
        .chat-subtitle { font-size: 0.9rem; color: #64748b; }
        .chat-time { font-size: 0.85rem; color: #94a3b8; }
        .chat-preview { color: #64748b; font-size: 0.95rem; margin-bottom: 12px; display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden; }
        .meta-badge { display: inline-flex; align-items: center; gap: 5px; padding: 5px 12px; border-radius: 15px; font-size: 0.85rem; font-weight: 600; margin-left: 10px; }
        .meta-badge.pending { background: #fef3c7; color: #92400e; }
        .meta-badge.confirmed { background: #d1fae5; color: #065f46; }
        .unread-badge { position: absolute; top: 15px; left: 15px; background: #ef4444; color: white; padding: 5px 12px; border-radius: 15px; font-size: 0.8rem; font-weight: bold; }
        .empty-state { text-align: center; padding: 80px 20px; background: white; border-radius: 15px; }
        .empty-state i { font-size: 5rem; color: #cbd5e1; margin-bottom: 20px; }
    </style>
</head>
<body>
    <div class="header">
        <div class="container">
            <h1><i class="fas fa-comments"></i> محادثاتي</h1>
        </div>
    </div>
                    <div style="position: fixed; top: 20px; left: 20px; z-index: 1000;">
        <?php include 'includes/lang-switcher.php'; ?>
    </div>
    <script src="js/auto-translate.js"></script>

    <div class="content">
        <div class="container">
            <a href="dashboard.php" class="back-link">
                <i class="fas fa-arrow-right"></i> العودة
            </a>

            <?php if (empty($chats)): ?>
                <div class="empty-state">
                    <i class="fas fa-comment-slash"></i>
                    <h3>لا توجد محادثات</h3>
                </div>
            <?php else: ?>
                <div class="chats-list">
                    <?php foreach ($chats as $chat): ?>
                        <a href="sale-chat.php?id=<?php echo $chat['auction_id']; ?>" 
                           class="chat-item <?php echo $chat['unread_count'] > 0 ? 'has-unread' : ''; ?>">
                            <div class="chat-avatar">
                                <?php echo strtoupper(substr($chat['other_user'], 0, 1)); ?>
                            </div>
                            <div class="chat-info">
                                <div class="chat-header">
                                    <div>
                                        <div class="chat-title">
                                            <?php echo htmlspecialchars($chat['brand'] . ' ' . $chat['model'] . ' ' . $chat['year']); ?>
                                        </div>
                                        <div class="chat-subtitle">
                                            <i class="fas fa-user"></i>
                                            <?php echo $chat['my_role'] == 'seller' ? 'المشتري' : 'البائع'; ?>:
                                            <?php echo htmlspecialchars($chat['other_user']); ?>
                                        </div>
                                    </div>
                                    <?php if ($chat['last_message_time']): ?>
                                        <div class="chat-time">
                                            <?php 
                                            $diff = time() - strtotime($chat['last_message_time']);
                                            echo $diff < 60 ? 'الآن' : ($diff < 3600 ? floor($diff/60) . ' د' : ($diff < 86400 ? floor($diff/3600) . ' س' : date('Y/m/d', strtotime($chat['last_message_time']))));
                                            ?>
                                        </div>
                                    <?php endif; ?>
                                </div>
                                <?php if ($chat['last_message']): ?>
                                    <div class="chat-preview"><?php echo htmlspecialchars($chat['last_message']); ?></div>
                                <?php endif; ?>
                                <div>
                                    <span class="meta-badge <?php echo $chat['sale_status']; ?>">
                                        <i class="fas fa-<?php echo $chat['sale_status'] == 'confirmed' ? 'check-circle' : 'clock'; ?>"></i>
                                        <?php echo $chat['sale_status'] == 'confirmed' ? 'مكتمل' : 'جاري'; ?>
                                    </span>
                                    <span class="meta-badge" style="background: #e0f2fe; color: #0369a1;">
                                        <i class="fas fa-dollar-sign"></i>
                                        <?php echo number_format($chat['sale_amount'], 2); ?>$
                                    </span>
                                </div>
                            </div>
                            <?php if ($chat['unread_count'] > 0): ?>
                                <div class="unread-badge"><?php echo $chat['unread_count']; ?></div>
                            <?php endif; ?>
                        </a>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>
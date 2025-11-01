<?php
require_once 'config/database.php';
require_once 'includes/functions.php';

if (!isLoggedIn()) {
    redirect('auth/login.php');
}

$user_id = $_SESSION['user_id'];
$order_id = isset($_GET['order_id']) ? (int)$_GET['order_id'] : 0;

if (!$order_id) {
    redirect('dashboard.php');
}

// جلب معلومات الطلب
$stmt = $conn->prepare("
    SELECT 
        o.*,
        p.title as product_title,
        buyer.username as buyer_name,
        buyer.id as buyer_id,
        seller.username as seller_name,
        seller.id as seller_id
    FROM store_orders o
    JOIN store_products p ON o.product_id = p.id
    JOIN users buyer ON o.buyer_id = buyer.id
    JOIN users seller ON o.seller_id = seller.id
    WHERE o.id = ? AND (o.buyer_id = ? OR o.seller_id = ?)
");
$stmt->execute([$order_id, $user_id, $user_id]);
$order = $stmt->fetch();

if (!$order) {
    redirect('dashboard.php');
}

$is_seller = ($order['seller_id'] == $user_id);
$other_user = $is_seller ? $order['buyer_name'] : $order['seller_name'];

// معالجة إرسال رسالة
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['send_message'])) {
    $message = clean($_POST['message']);
    
    if (!empty($message)) {
        $stmt = $conn->prepare("INSERT INTO store_order_messages (order_id, sender_id, message) VALUES (?, ?, ?)");
        $stmt->execute([$order_id, $user_id, $message]);
        
        $receiver_id = $is_seller ? $order['buyer_id'] : $order['seller_id'];
        sendNotification($receiver_id, 'رسالة جديدة', substr($message, 0, 50), 'message', $order_id);
        
        header("Location: store-order-chat.php?order_id=$order_id");
        exit;
    }
}

// جلب الرسائل
$stmt = $conn->prepare("
    SELECT m.*, u.username 
    FROM store_order_messages m
    JOIN users u ON m.sender_id = u.id
    WHERE m.order_id = ?
    ORDER BY m.created_at ASC
");
$stmt->execute([$order_id]);
$messages = $stmt->fetchAll();

// تحديث كمقروء
$stmt = $conn->prepare("UPDATE store_order_messages SET is_read = TRUE WHERE order_id = ? AND sender_id != ?");
$stmt->execute([$order_id, $user_id]);
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <title>محادثة الطلب</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Segoe UI', sans-serif; background: #f8fafc; direction: rtl; height: 100vh; display: flex; flex-direction: column; }
        .header {
            background: linear-gradient(135deg, #10b981 0%, #059669 100%);
            color: white;
            padding: 15px 0;
        }
        .container { max-width: 1000px; margin: 0 auto; padding: 0 20px; }
        .chat-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .chat-info h2 { font-size: 1.3rem; margin-bottom: 5px; }
        .back-btn {
            background: rgba(255,255,255,0.2);
            padding: 10px 20px;
            border-radius: 8px;
            color: white;
            text-decoration: none;
        }
        .chat-container {
            flex: 1;
            display: flex;
            flex-direction: column;
            max-width: 1000px;
            margin: 0 auto;
            width: 100%;
            background: white;
        }
        .messages-area {
            flex: 1;
            overflow-y: auto;
            padding: 30px;
            background: #f8fafc;
        }
        .message {
            margin-bottom: 20px;
            display: flex;
            gap: 12px;
        }
        .message.sent { flex-direction: row-reverse; }
        .message-avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: linear-gradient(135deg, #10b981 0%, #059669 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: bold;
            flex-shrink: 0;
        }
        .message-content { max-width: 70%; }
        .message-bubble {
            background: white;
            padding: 12px 18px;
            border-radius: 18px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.08);
        }
        .message.sent .message-bubble {
            background: linear-gradient(135deg, #10b981 0%, #059669 100%);
            color: white;
        }
        .message-time {
            font-size: 0.75rem;
            color: #94a3b8;
            margin-top: 5px;
        }
        .message-form {
            background: white;
            padding: 20px 30px;
            border-top: 2px solid #f1f5f9;
            display: flex;
            gap: 15px;
        }
        .message-input {
            flex: 1;
            padding: 15px 20px;
            border: 2px solid #e2e8f0;
            border-radius: 25px;
            font-size: 1rem;
        }
        .send-btn {
            padding: 15px 30px;
            background: linear-gradient(135deg, #10b981 0%, #059669 100%);
            color: white;
            border: none;
            border-radius: 25px;
            font-weight: 600;
            cursor: pointer;
        }
    </style>
</head>
<body>
    <div class="header">
        <div class="container">
                                <div style="position: fixed; top: 20px; left: 20px; z-index: 1000;">
        <?php include 'includes/lang-switcher.php'; ?>
    </div>
    <script src="js/auto-translate.js"></script>

            <div class="chat-header">
                <div class="chat-info">
                    <h2><i class="fas fa-comments"></i> <?php echo htmlspecialchars($order['product_title']); ?></h2>
                    <p><i class="fas fa-user"></i> <?php echo htmlspecialchars($other_user); ?></p>
                </div>
                <a href="<?php echo $is_seller ? 'my-store-sales.php' : 'my-purchases.php'; ?>" class="back-btn">
                    <i class="fas fa-arrow-right"></i> رجوع
                </a>
            </div>
        </div>
    </div>

    <div class="chat-container">
        <div class="messages-area" id="messagesArea">
            <?php foreach ($messages as $msg): ?>
                <div class="message <?php echo $msg['sender_id'] == $user_id ? 'sent' : 'received'; ?>">
                    <div class="message-avatar"><?php echo strtoupper(substr($msg['username'], 0, 1)); ?></div>
                    <div class="message-content">
                        <div class="message-bubble"><?php echo nl2br(htmlspecialchars($msg['message'])); ?></div>
                        <div class="message-time"><?php echo date('H:i', strtotime($msg['created_at'])); ?></div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>

        <form method="POST" class="message-form">
            <input type="text" name="message" class="message-input" placeholder="اكتب رسالتك..." required autocomplete="off">
            <button type="submit" name="send_message" class="send-btn">
                <i class="fas fa-paper-plane"></i> إرسال
            </button>
        </form>
    </div>

    <script>
        document.getElementById('messagesArea').scrollTop = document.getElementById('messagesArea').scrollHeight;
    </script>
</body>
</html>
<?php
require_once 'config/database.php';
require_once 'includes/functions.php';
require_once 'includes/ai-chatbot.php';

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

getChatbotWidget();
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>💬 محادثاتي - التواصل الفوري</title>

    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@200;300;400;500;600;700;800;900&family=Tajawal:wght@300;400;500;700;900&display=swap" rel="stylesheet">

    <!-- Icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">

    <!-- AOS Animation -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/aos/2.3.4/aos.css">

    <style>
        :root {
            --royal-gradient: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            --sunset-gradient: linear-gradient(135deg, #fa709a 0%, #fee140 100%);
            --ocean-gradient: linear-gradient(135deg, #89f7fe 0%, #66a6ff 100%);
            --emerald-gradient: linear-gradient(135deg, #13f1fc 0%, #0470dc 100%);
            --fire-gradient: linear-gradient(135deg, #f9d423 0%, #ff4e50 100%);
            --mystic-gradient: linear-gradient(135deg, #ec77ab 0%, #7873f5 100%);
            --glass-white: rgba(255, 255, 255, 0.1);
            --glass-border: rgba(255, 255, 255, 0.18);
            --transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Cairo', 'Tajawal', sans-serif;
            background: #0f0c29;
            min-height: 100vh;
            direction: rtl;
            overflow-x: hidden;
        }

        .dynamic-background {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            z-index: -2;
            background: linear-gradient(125deg, #0f0c29, #302b63, #24243e, #0f0c29);
            background-size: 400% 400%;
            animation: gradientFlow 20s ease infinite;
        }

        @keyframes gradientFlow {
            0% { background-position: 0% 0%; }
            25% { background-position: 100% 0%; }
            50% { background-position: 100% 100%; }
            75% { background-position: 0% 100%; }
            100% { background-position: 0% 0%; }
        }

        .grid-3d {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            z-index: -1;
            opacity: 0.1;
            background-image:
                linear-gradient(rgba(102, 126, 234, 0.2) 2px, transparent 2px),
                linear-gradient(90deg, rgba(102, 126, 234, 0.2) 2px, transparent 2px);
            background-size: 50px 50px;
            animation: grid3d 10s linear infinite;
            transform: perspective(1000px) rotateX(60deg);
        }

        @keyframes grid3d {
            0% { transform: perspective(1000px) rotateX(60deg) translateY(0); }
            100% { transform: perspective(1000px) rotateX(60deg) translateY(50px); }
        }

        .glowing-particles {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            z-index: -1;
            pointer-events: none;
        }

        .particle {
            position: absolute;
            width: 4px;
            height: 4px;
            background: radial-gradient(circle, rgba(102, 126, 234, 1) 0%, transparent 70%);
            border-radius: 50%;
            animation: particleFloat 25s infinite linear;
            box-shadow: 0 0 10px rgba(102, 126, 234, 0.8);
        }

        @keyframes particleFloat {
            0% { transform: translateY(100vh) translateX(0) scale(0); opacity: 0; }
            10% { transform: translateY(80vh) translateX(10px) scale(1); opacity: 1; }
            90% { transform: translateY(10vh) translateX(-10px) scale(1); opacity: 1; }
            100% { transform: translateY(-100vh) translateX(0) scale(0); opacity: 0; }
        }

        .luxury-header {
            background: var(--glass-white);
            backdrop-filter: blur(20px);
            -webkit-backdrop-filter: blur(20px);
            border-bottom: 1px solid var(--glass-border);
            position: sticky;
            top: 0;
            z-index: 1000;
            animation: slideDownHeader 0.8s ease-out;
        }

        @keyframes slideDownHeader {
            from { transform: translateY(-100%); opacity: 0; }
            to { transform: translateY(0); opacity: 1; }
        }

        .container {
            max-width: 1400px;
            margin: 0 auto;
            padding: 0 20px;
        }

        .header-content {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 20px 0;
        }

        .animated-logo {
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .logo-text {
            font-size: 1.8rem;
            font-weight: 900;
            background: linear-gradient(135deg, #fff, #e0e7ff, #c7d2fe);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            animation: logoShimmer 3s ease-in-out infinite;
        }

        @keyframes logoShimmer {
            0%, 100% { filter: brightness(1); }
            50% { filter: brightness(1.2); }
        }

        .luxury-nav {
            display: flex;
            gap: 10px;
            align-items: center;
        }

        .nav-item {
            position: relative;
            padding: 12px 18px;
            background: var(--glass-white);
            backdrop-filter: blur(10px);
            border: 1px solid var(--glass-border);
            border-radius: 15px;
            color: rgba(255, 255, 255, 0.9);
            text-decoration: none;
            font-weight: 600;
            font-size: 0.95rem;
            display: flex;
            align-items: center;
            gap: 8px;
            transition: var(--transition);
            overflow: hidden;
        }

        .nav-item::before {
            content: '';
            position: absolute;
            top: 50%;
            left: 50%;
            width: 0;
            height: 0;
            background: var(--royal-gradient);
            border-radius: 50%;
            transform: translate(-50%, -50%);
            transition: width 0.6s ease, height 0.6s ease;
        }

        .nav-item:hover {
            color: white;
            transform: translateY(-3px);
            box-shadow: 0 10px 30px rgba(102, 126, 234, 0.3);
            border-color: rgba(102, 126, 234, 0.5);
        }

        .nav-item:hover::before {
            width: 150%;
            height: 150%;
        }

        .nav-item i, .nav-item span {
            position: relative;
            z-index: 1;
        }

        .main-content {
            padding: 40px 0;
        }

        .page-banner {
            background: var(--glass-white);
            backdrop-filter: blur(30px);
            border: 1px solid var(--glass-border);
            border-radius: 30px;
            padding: 40px;
            margin-bottom: 40px;
            animation: bannerSlideIn 1s ease-out;
        }

        @keyframes bannerSlideIn {
            from { opacity: 0; transform: translateY(30px) scale(0.95); }
            to { opacity: 1; transform: translateY(0) scale(1); }
        }

        .page-title {
            font-size: 2.5rem;
            font-weight: 900;
            margin-bottom: 10px;
            background: linear-gradient(135deg, #667eea, #764ba2, #f093fb);
            background-size: 200% 200%;
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            animation: titleGradient 3s ease infinite;
        }

        @keyframes titleGradient {
            0% { background-position: 0% 50%; }
            50% { background-position: 100% 50%; }
            100% { background-position: 0% 50%; }
        }

        .page-subtitle {
            color: rgba(255, 255, 255, 0.7);
            font-size: 1.1rem;
        }

        .chats-list {
            display: grid;
            gap: 20px;
        }

        .chat-item {
            background: var(--glass-white);
            backdrop-filter: blur(20px);
            border: 1px solid var(--glass-border);
            border-radius: 25px;
            padding: 25px;
            display: flex;
            gap: 20px;
            transition: var(--transition);
            text-decoration: none;
            color: inherit;
            position: relative;
            overflow: hidden;
        }

        .chat-item::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.1), transparent);
            transition: left 0.8s;
        }

        .chat-item:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 25px rgba(0,0,0,0.12);
        }

        .chat-item:hover::before {
            left: 100%;
        }

        .chat-item.has-unread {
            border-right: 4px solid #06b6d4;
        }

        .chat-avatar {
            width: 60px;
            height: 60px;
            border-radius: 50%;
            background: linear-gradient(135deg, #06b6d4 0%, #0891b2 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 1.5rem;
            font-weight: bold;
            flex-shrink: 0;
        }

        .chat-info {
            flex: 1;
        }

        .chat-header {
            display: flex;
            justify-content: space-between;
            margin-bottom: 10px;
        }

        .chat-title {
            font-size: 1.2rem;
            font-weight: 600;
            color: white;
            margin-bottom: 5px;
        }

        .chat-subtitle {
            font-size: 0.9rem;
            color: rgba(255, 255, 255, 0.6);
        }

        .chat-time {
            font-size: 0.85rem;
            color: rgba(255, 255, 255, 0.5);
        }

        .chat-preview {
            color: rgba(255, 255, 255, 0.6);
            font-size: 0.95rem;
            margin-bottom: 12px;
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
        }

        .meta-badge {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            padding: 5px 12px;
            border-radius: 15px;
            font-size: 0.85rem;
            font-weight: 600;
            margin-left: 10px;
        }

        .meta-badge.pending { background: rgba(249, 212, 35, 0.2); color: #f9d423; border: 1px solid rgba(249, 212, 35, 0.3); }
        .meta-badge.confirmed { background: rgba(16, 185, 129, 0.2); color: #10b981; border: 1px solid rgba(16, 185, 129, 0.3); }

        .unread-badge {
            position: absolute;
            top: 15px;
            left: 15px;
            background: #ef4444;
            color: white;
            padding: 5px 12px;
            border-radius: 15px;
            font-size: 0.8rem;
            font-weight: bold;
        }

        .empty-state {
            text-align: center;
            padding: 80px 20px;
            background: var(--glass-white);
            backdrop-filter: blur(20px);
            border: 1px solid var(--glass-border);
            border-radius: 30px;
        }

        .empty-state i {
            font-size: 5rem;
            color: rgba(255, 255, 255, 0.3);
            margin-bottom: 20px;
        }

        .empty-state h3 {
            color: white;
            font-size: 1.5rem;
            margin-bottom: 10px;
        }

        .empty-state p {
            color: rgba(255, 255, 255, 0.6);
        }

        @media (max-width: 768px) {
            .header-content {
                flex-direction: column;
                gap: 20px;
            }

            .luxury-nav {
                display: grid;
                grid-template-columns: repeat(3, 1fr);
                width: 100%;
            }

            .page-title {
                font-size: 2rem;
            }

            .chat-item {
                flex-direction: column;
            }
        }
    </style>
</head>
<body>
    <div class="dynamic-background"></div>
    <div class="grid-3d"></div>
    <div class="glowing-particles" id="particles"></div>

    <header class="luxury-header">
        <div class="container">
            <div class="header-content">
                <div class="animated-logo">
                    <div class="logo-text">Bidora</div>
                </div>

                <nav class="luxury-nav">
                    <a href="dashboard.php" class="nav-item">
                        <i class="fas fa-home"></i>
                        <span>الرئيسية</span>
                    </a>
                    <a href="auctions.php" class="nav-item">
                        <i class="fas fa-gavel"></i>
                        <span>المزادات</span>
                    </a>
                    <a href="wallet.php" class="nav-item">
                        <i class="fas fa-wallet"></i>
                        <span>المحفظة</span>
                    </a>
                    <a href="profile.php" class="nav-item">
                        <i class="fas fa-user"></i>
                        <span>الملف</span>
                    </a>
                </nav>
            </div>
        </div>
    </header>

    <main class="main-content">
        <div class="container">
            <div class="page-banner" data-aos="fade-up">
                <h1 class="page-title">💬 محادثاتي</h1>
                <p class="page-subtitle">تواصل مباشر مع المشترين والبائعين</p>
            </div>

            <?php if (empty($chats)): ?>
                <div class="empty-state" data-aos="fade-up">
                    <i class="fas fa-comment-slash"></i>
                    <h3>لا توجد محادثات</h3>
                    <p>ستظهر محادثاتك هنا بعد إتمام صفقة</p>
                </div>
            <?php else: ?>
                <div class="chats-list">
                    <?php foreach ($chats as $chat): ?>
                        <a href="sale-chat.php?id=<?php echo $chat['auction_id']; ?>"
                           class="chat-item <?php echo $chat['unread_count'] > 0 ? 'has-unread' : ''; ?>"
                           data-aos="fade-up">
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
                                    <span class="meta-badge" style="background: rgba(6, 182, 212, 0.2); color: #06b6d4; border: 1px solid rgba(6, 182, 212, 0.3);">
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
    </main>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/aos/2.3.4/aos.js"></script>
    <script>
        AOS.init({
            duration: 1200,
            once: false,
            offset: 100
        });

        function createParticles() {
            const container = document.getElementById('particles');
            const particleCount = 40;

            for (let i = 0; i < particleCount; i++) {
                const particle = document.createElement('div');
                particle.className = 'particle';
                particle.style.left = Math.random() * 100 + '%';
                particle.style.animationDelay = Math.random() * 25 + 's';
                particle.style.animationDuration = (20 + Math.random() * 10) + 's';
                container.appendChild(particle);
            }
        }

        document.addEventListener('DOMContentLoaded', function() {
            createParticles();
        });
    </script>
</body>
</html>

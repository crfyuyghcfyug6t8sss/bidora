<?php
require_once 'config/database.php';
require_once 'includes/functions.php';
require_once 'includes/ai-chatbot.php';

// التحقق من تسجيل الدخول
if (!isLoggedIn()) {
    setMessage('يجب تسجيل الدخول أولاً', 'danger');
    redirect('auth/login.php');
}

$user_id = $_SESSION['user_id'];

// جلب جميع مزايدات المستخدم
$stmt = $conn->prepare("
    SELECT
        b.*,
        a.id as auction_id,
        a.status as auction_status,
        a.current_price,
        a.highest_bidder_id,
        a.end_time,
        v.brand,
        v.model,
        v.year,
        seller.username as seller_name,
        (SELECT image_path FROM vehicle_images WHERE vehicle_id = v.id AND is_primary = TRUE LIMIT 1) as main_image
    FROM bids b
    JOIN auctions a ON b.auction_id = a.id
    JOIN vehicles v ON a.vehicle_id = v.id
    JOIN users seller ON a.seller_id = seller.id
    WHERE b.user_id = ?
    ORDER BY b.created_at DESC
");
$stmt->execute([$user_id]);
$bids = $stmt->fetchAll();

// تصنيف المزايدات
$active_bids = [];
$winning_bids = [];
$outbid_bids = [];
$completed_bids = [];

foreach ($bids as $bid) {
    $is_highest = ($bid['highest_bidder_id'] == $user_id);
    $is_ended = (strtotime($bid['end_time']) < time() || $bid['auction_status'] == 'completed');

    if ($bid['status'] == 'won' || ($is_ended && $is_highest)) {
        $completed_bids[] = $bid;
    } elseif ($is_highest && !$is_ended) {
        $winning_bids[] = $bid;
    } elseif ($bid['status'] == 'outbid' || !$is_highest) {
        $outbid_bids[] = $bid;
    } else {
        $active_bids[] = $bid;
    }
}

getChatbotWidget();
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>🎯 مزايداتي - تتبع احترافي</title>

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
            /* Gradient Palettes */
            --royal-gradient: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            --sunset-gradient: linear-gradient(135deg, #fa709a 0%, #fee140 100%);
            --ocean-gradient: linear-gradient(135deg, #89f7fe 0%, #66a6ff 100%);
            --emerald-gradient: linear-gradient(135deg, #13f1fc 0%, #0470dc 100%);
            --fire-gradient: linear-gradient(135deg, #f9d423 0%, #ff4e50 100%);
            --mystic-gradient: linear-gradient(135deg, #ec77ab 0%, #7873f5 100%);

            /* Glass Effects */
            --glass-white: rgba(255, 255, 255, 0.1);
            --glass-border: rgba(255, 255, 255, 0.18);

            /* Animations */
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

        /* خلفية ديناميكية متحركة */
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

        /* شبكة ثلاثية الأبعاد */
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

        /* جسيمات متوهجة */
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
            0% {
                transform: translateY(100vh) translateX(0) scale(0);
                opacity: 0;
            }
            10% {
                transform: translateY(80vh) translateX(10px) scale(1);
                opacity: 1;
            }
            90% {
                transform: translateY(10vh) translateX(-10px) scale(1);
                opacity: 1;
            }
            100% {
                transform: translateY(-100vh) translateX(0) scale(0);
                opacity: 0;
            }
        }

        /* Header الفاخر */
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
            from {
                transform: translateY(-100%);
                opacity: 0;
            }
            to {
                transform: translateY(0);
                opacity: 1;
            }
        }

        .container {
            max-width: 1400px;
            margin: 0 auto;
            padding: 0 20px;
            position: relative;
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

        .nav-item i {
            font-size: 1.1rem;
            position: relative;
            z-index: 1;
        }

        .nav-item span {
            position: relative;
            z-index: 1;
        }

        /* المحتوى الرئيسي */
        .main-content {
            padding: 40px 0;
            position: relative;
        }

        /* بانر الصفحة */
        .page-banner {
            background: var(--glass-white);
            backdrop-filter: blur(30px);
            border: 1px solid var(--glass-border);
            border-radius: 30px;
            padding: 40px;
            margin-bottom: 40px;
            position: relative;
            overflow: hidden;
            animation: bannerSlideIn 1s ease-out;
        }

        @keyframes bannerSlideIn {
            from {
                opacity: 0;
                transform: translateY(30px) scale(0.95);
            }
            to {
                opacity: 1;
                transform: translateY(0) scale(1);
            }
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

        /* Tabs */
        .tabs-premium {
            display: flex;
            gap: 10px;
            margin-bottom: 30px;
            flex-wrap: wrap;
        }

        .tab-premium {
            padding: 15px 30px;
            background: var(--glass-white);
            backdrop-filter: blur(10px);
            border: 1px solid var(--glass-border);
            border-radius: 15px;
            color: rgba(255, 255, 255, 0.7);
            font-weight: 600;
            cursor: pointer;
            transition: var(--transition);
            position: relative;
            overflow: hidden;
        }

        .tab-premium::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.2), transparent);
            transition: left 0.8s;
        }

        .tab-premium:hover::before {
            left: 100%;
        }

        .tab-premium.active {
            background: var(--royal-gradient);
            color: white;
            border-color: transparent;
            box-shadow: 0 10px 30px rgba(102, 126, 234, 0.4);
        }

        .tab-content {
            display: none;
        }

        .tab-content.active {
            display: block;
            animation: fadeIn 0.5s ease;
        }

        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }

        /* Bid Cards */
        .bids-grid {
            display: grid;
            gap: 25px;
        }

        .bid-card-premium {
            background: var(--glass-white);
            backdrop-filter: blur(20px);
            border: 1px solid var(--glass-border);
            border-radius: 25px;
            padding: 25px;
            display: grid;
            grid-template-columns: 120px 1fr auto;
            gap: 25px;
            align-items: center;
            transition: var(--transition);
            position: relative;
            overflow: hidden;
        }

        .bid-card-premium::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.1), transparent);
            transition: left 0.8s;
        }

        .bid-card-premium:hover {
            transform: translateY(-5px);
            box-shadow: 0 15px 50px rgba(0, 0, 0, 0.3);
        }

        .bid-card-premium:hover::before {
            left: 100%;
        }

        .bid-image {
            width: 120px;
            height: 120px;
            border-radius: 20px;
            overflow: hidden;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 2.5rem;
        }

        .bid-image img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .bid-info h3 {
            color: white;
            font-size: 1.3rem;
            font-weight: 700;
            margin-bottom: 10px;
        }

        .bid-meta {
            display: flex;
            gap: 15px;
            flex-wrap: wrap;
            margin: 10px 0;
        }

        .meta-item {
            display: flex;
            align-items: center;
            gap: 5px;
            color: rgba(255, 255, 255, 0.7);
            font-size: 0.9rem;
        }

        .bid-details {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 15px;
            min-width: 250px;
        }

        .detail-box {
            background: rgba(255, 255, 255, 0.05);
            padding: 12px;
            border-radius: 12px;
            text-align: center;
            border: 1px solid rgba(255, 255, 255, 0.1);
        }

        .detail-label {
            font-size: 0.8rem;
            color: rgba(255, 255, 255, 0.6);
            margin-bottom: 5px;
        }

        .detail-value {
            font-size: 1.2rem;
            font-weight: 700;
            color: white;
        }

        .status-badge {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            padding: 8px 16px;
            border-radius: 20px;
            font-size: 0.85rem;
            font-weight: 600;
            margin-bottom: 10px;
        }

        .status-winning { background: rgba(16, 185, 129, 0.2); color: #10b981; border: 1px solid rgba(16, 185, 129, 0.3); }
        .status-outbid { background: rgba(239, 68, 68, 0.2); color: #ef4444; border: 1px solid rgba(239, 68, 68, 0.3); }
        .status-won { background: rgba(59, 130, 246, 0.2); color: #3b82f6; border: 1px solid rgba(59, 130, 246, 0.3); }

        .btn-premium {
            padding: 12px 25px;
            background: var(--royal-gradient);
            color: white;
            text-decoration: none;
            border-radius: 15px;
            font-weight: 700;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            transition: var(--transition);
            border: none;
            cursor: pointer;
            box-shadow: 0 10px 25px rgba(102, 126, 234, 0.3);
        }

        .btn-premium:hover {
            transform: translateY(-3px);
            box-shadow: 0 15px 35px rgba(102, 126, 234, 0.4);
        }

        .btn-outline {
            background: transparent;
            border: 2px solid rgba(255, 255, 255, 0.3);
            color: white;
        }

        .btn-outline:hover {
            background: rgba(255, 255, 255, 0.1);
            border-color: rgba(255, 255, 255, 0.5);
        }

        .btn-warning {
            background: var(--fire-gradient);
        }

        /* Empty State */
        .empty-state {
            text-align: center;
            padding: 80px 20px;
            background: var(--glass-white);
            backdrop-filter: blur(20px);
            border: 1px solid var(--glass-border);
            border-radius: 30px;
        }

        .empty-state-icon {
            font-size: 5rem;
            margin-bottom: 20px;
            opacity: 0.5;
        }

        .empty-state h3 {
            color: white;
            font-size: 1.5rem;
            margin-bottom: 10px;
        }

        .empty-state p {
            color: rgba(255, 255, 255, 0.7);
            margin-bottom: 20px;
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

            .bid-card-premium {
                grid-template-columns: 1fr;
            }

            .bid-details {
                grid-template-columns: 1fr;
                min-width: auto;
            }

            .page-title {
                font-size: 2rem;
            }
        }
    </style>
</head>
<body>
    <!-- الخلفية الديناميكية -->
    <div class="dynamic-background"></div>
    <div class="grid-3d"></div>

    <!-- الجسيمات المتوهجة -->
    <div class="glowing-particles" id="particles"></div>

    <!-- Header الفاخر -->
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

    <!-- المحتوى الرئيسي -->
    <main class="main-content">
        <div class="container">
            <!-- بانر الصفحة -->
            <div class="page-banner" data-aos="fade-up">
                <h1 class="page-title">🎯 مزايداتي</h1>
                <p class="page-subtitle">تتبع جميع مزايداتك وحالتها بشكل احترافي</p>
            </div>

            <!-- Tabs -->
            <div class="tabs-premium" data-aos="fade-up">
                <button class="tab-premium active" onclick="switchTab('winning')">
                    🏆 الفوز حالياً (<?php echo count($winning_bids); ?>)
                </button>
                <button class="tab-premium" onclick="switchTab('active')">
                    ⚡ نشطة (<?php echo count($active_bids); ?>)
                </button>
                <button class="tab-premium" onclick="switchTab('outbid')">
                    ⏰ تم تجاوزها (<?php echo count($outbid_bids); ?>)
                </button>
                <button class="tab-premium" onclick="switchTab('completed')">
                    ✅ مكتملة (<?php echo count($completed_bids); ?>)
                </button>
            </div>

            <!-- Winning Bids -->
            <div id="winning" class="tab-content active">
                <?php if (empty($winning_bids)): ?>
                    <div class="empty-state" data-aos="fade-up">
                        <div class="empty-state-icon">🏆</div>
                        <h3>لا توجد مزايدات رابحة حالياً</h3>
                        <p>ابحث عن مزادات جديدة وزايد لتكون الأعلى</p>
                        <a href="auctions.php" class="btn-premium">تصفح المزادات</a>
                    </div>
                <?php else: ?>
                    <div class="bids-grid">
                        <?php foreach ($winning_bids as $bid): ?>
                            <div class="bid-card-premium" data-aos="fade-up">
                                <div class="bid-image">
                                    <?php if ($bid['main_image']): ?>
                                        <img src="<?php echo htmlspecialchars($bid['main_image']); ?>" alt="">
                                    <?php else: ?>
                                        <i class="fas fa-car"></i>
                                    <?php endif; ?>
                                </div>

                                <div class="bid-info">
                                    <h3><?php echo htmlspecialchars($bid['brand'] . ' ' . $bid['model']); ?></h3>
                                    <span class="status-badge status-winning">
                                        <i class="fas fa-trophy"></i> أنت الأعلى
                                    </span>
                                    <div class="bid-meta">
                                        <span class="meta-item">
                                            <i class="fas fa-calendar"></i> <?php echo $bid['year']; ?>
                                        </span>
                                        <span class="meta-item">
                                            <i class="fas fa-user"></i> البائع: <?php echo htmlspecialchars($bid['seller_name']); ?>
                                        </span>
                                        <span class="meta-item">
                                            <i class="fas fa-clock"></i>
                                            <?php
                                            $remaining = strtotime($bid['end_time']) - time();
                                            $hours = floor($remaining / 3600);
                                            echo $hours > 0 ? 'ينتهي في ' . $hours . ' ساعة' : 'ينتهي قريباً';
                                            ?>
                                        </span>
                                    </div>
                                </div>

                                <div class="bid-details">
                                    <div class="detail-box">
                                        <div class="detail-label">مزايدتك</div>
                                        <div class="detail-value">$<?php echo number_format($bid['bid_amount'], 0); ?></div>
                                    </div>
                                    <div class="detail-box">
                                        <div class="detail-label">السعر الحالي</div>
                                        <div class="detail-value" style="color: #10b981;">$<?php echo number_format($bid['current_price'], 0); ?></div>
                                    </div>
                                    <div class="detail-box">
                                        <div class="detail-label">رصيد محجوز</div>
                                        <div class="detail-value" style="color: #f9d423;">$<?php echo number_format($bid['frozen_amount'], 0); ?></div>
                                    </div>
                                    <div class="detail-box">
                                        <div class="detail-label">الحالة</div>
                                        <div class="detail-value" style="font-size: 0.9rem; color: #10b981;">نشط</div>
                                    </div>
                                </div>
                            </div>
                            <div style="text-align: left; margin-top: -15px; margin-bottom: 20px;" data-aos="fade-up">
                                <a href="auction-details.php?id=<?php echo $bid['auction_id']; ?>" class="btn-premium">
                                    <i class="fas fa-eye"></i> عرض المزاد
                                </a>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Active Bids -->
            <div id="active" class="tab-content">
                <?php if (empty($active_bids)): ?>
                    <div class="empty-state" data-aos="fade-up">
                        <div class="empty-state-icon">📋</div>
                        <h3>لا توجد مزايدات نشطة</h3>
                        <p>ابدأ بالمزايدة على سيارة تعجبك</p>
                        <a href="auctions.php" class="btn-premium">تصفح المزادات</a>
                    </div>
                <?php else: ?>
                    <div class="bids-grid">
                        <?php foreach ($active_bids as $bid): ?>
                            <div class="bid-card-premium" data-aos="fade-up">
                                <div class="bid-image">
                                    <?php if ($bid['main_image']): ?>
                                        <img src="<?php echo htmlspecialchars($bid['main_image']); ?>" alt="">
                                    <?php else: ?>
                                        <i class="fas fa-car"></i>
                                    <?php endif; ?>
                                </div>

                                <div class="bid-info">
                                    <h3><?php echo htmlspecialchars($bid['brand'] . ' ' . $bid['model']); ?></h3>
                                    <div class="bid-meta">
                                        <span class="meta-item">
                                            <i class="fas fa-calendar"></i> <?php echo $bid['year']; ?>
                                        </span>
                                    </div>
                                </div>

                                <div class="bid-details">
                                    <div class="detail-box">
                                        <div class="detail-label">مزايدتك</div>
                                        <div class="detail-value">$<?php echo number_format($bid['bid_amount'], 0); ?></div>
                                    </div>
                                    <div class="detail-box">
                                        <div class="detail-label">السعر الحالي</div>
                                        <div class="detail-value">$<?php echo number_format($bid['current_price'], 0); ?></div>
                                    </div>
                                </div>
                            </div>
                            <div style="text-align: left; margin-top: -15px; margin-bottom: 20px;" data-aos="fade-up">
                                <a href="auction-details.php?id=<?php echo $bid['auction_id']; ?>" class="btn-premium btn-outline">
                                    <i class="fas fa-eye"></i> عرض المزاد
                                </a>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Outbid -->
            <div id="outbid" class="tab-content">
                <?php if (empty($outbid_bids)): ?>
                    <div class="empty-state" data-aos="fade-up">
                        <div class="empty-state-icon">⏰</div>
                        <h3>رائع! لم يتم تجاوز أي مزايدة</h3>
                    </div>
                <?php else: ?>
                    <div class="bids-grid">
                        <?php foreach ($outbid_bids as $bid): ?>
                            <div class="bid-card-premium" data-aos="fade-up">
                                <div class="bid-image">
                                    <?php if ($bid['main_image']): ?>
                                        <img src="<?php echo htmlspecialchars($bid['main_image']); ?>" alt="">
                                    <?php else: ?>
                                        <i class="fas fa-car"></i>
                                    <?php endif; ?>
                                </div>

                                <div class="bid-info">
                                    <h3><?php echo htmlspecialchars($bid['brand'] . ' ' . $bid['model']); ?></h3>
                                    <span class="status-badge status-outbid">
                                        <i class="fas fa-exclamation-circle"></i> تم تجاوزك
                                    </span>
                                    <div class="bid-meta">
                                        <span class="meta-item">
                                            <i class="fas fa-calendar"></i> <?php echo $bid['year']; ?>
                                        </span>
                                    </div>
                                </div>

                                <div class="bid-details">
                                    <div class="detail-box">
                                        <div class="detail-label">مزايدتك</div>
                                        <div class="detail-value">$<?php echo number_format($bid['bid_amount'], 0); ?></div>
                                    </div>
                                    <div class="detail-box">
                                        <div class="detail-label">السعر الحالي</div>
                                        <div class="detail-value" style="color: #ef4444;">$<?php echo number_format($bid['current_price'], 0); ?></div>
                                    </div>
                                </div>
                            </div>
                            <div style="text-align: left; margin-top: -15px; margin-bottom: 20px;" data-aos="fade-up">
                                <a href="auction-details.php?id=<?php echo $bid['auction_id']; ?>" class="btn-premium btn-warning">
                                    <i class="fas fa-redo"></i> زايد مرة أخرى
                                </a>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Completed -->
            <div id="completed" class="tab-content">
                <?php if (empty($completed_bids)): ?>
                    <div class="empty-state" data-aos="fade-up">
                        <div class="empty-state-icon">✅</div>
                        <h3>لا توجد مزايدات مكتملة بعد</h3>
                    </div>
                <?php else: ?>
                    <div class="bids-grid">
                        <?php foreach ($completed_bids as $bid): ?>
                            <div class="bid-card-premium" data-aos="fade-up">
                                <div class="bid-image">
                                    <?php if ($bid['main_image']): ?>
                                        <img src="<?php echo htmlspecialchars($bid['main_image']); ?>" alt="">
                                    <?php else: ?>
                                        <i class="fas fa-car"></i>
                                    <?php endif; ?>
                                </div>

                                <div class="bid-info">
                                    <h3><?php echo htmlspecialchars($bid['brand'] . ' ' . $bid['model']); ?></h3>
                                    <span class="status-badge status-won">
                                        <i class="fas fa-trophy"></i> فزت بالمزاد
                                    </span>
                                    <div class="bid-meta">
                                        <span class="meta-item">
                                            <i class="fas fa-calendar"></i> <?php echo $bid['year']; ?>
                                        </span>
                                    </div>
                                </div>

                                <div class="bid-details">
                                    <div class="detail-box">
                                        <div class="detail-label">السعر النهائي</div>
                                        <div class="detail-value" style="color: #10b981;">$<?php echo number_format($bid['bid_amount'], 0); ?></div>
                                    </div>
                                    <div class="detail-box">
                                        <div class="detail-label">تاريخ الفوز</div>
                                        <div class="detail-value" style="font-size: 0.9rem;"><?php echo date('Y-m-d', strtotime($bid['created_at'])); ?></div>
                                    </div>
                                </div>
                            </div>
                            <div style="text-align: left; margin-top: -15px; margin-bottom: 20px; display: flex; gap: 10px; justify-content: flex-start;" data-aos="fade-up">
                                <a href="auction-details.php?id=<?php echo $bid['auction_id']; ?>" class="btn-premium btn-outline">
                                    <i class="fas fa-eye"></i> التفاصيل
                                </a>
                                <?php
                                // التحقق من وجود تقييم معلق
                                $stmt_rating = $conn->prepare("SELECT id FROM ratings WHERE auction_id = ? AND from_user_id = ? AND rating IS NULL");
                                $stmt_rating->execute([$bid['auction_id'], $_SESSION['user_id']]);
                                $pending_rating = $stmt_rating->fetch();
                                ?>
                                <?php if ($pending_rating): ?>
                                    <a href="rate-user.php?id=<?php echo $pending_rating['id']; ?>" class="btn-premium btn-warning">
                                        <i class="fas fa-star"></i> قيّم البائع
                                    </a>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </main>

    <!-- Scripts -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/aos/2.3.4/aos.js"></script>

    <script>
        // تهيئة AOS
        AOS.init({
            duration: 1200,
            once: false,
            offset: 100
        });

        // إنشاء الجسيمات المتوهجة
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

        // تبديل التبويبات
        function switchTab(tabName) {
            document.querySelectorAll('.tab-content').forEach(content => {
                content.classList.remove('active');
            });
            document.querySelectorAll('.tab-premium').forEach(tab => {
                tab.classList.remove('active');
            });
            document.getElementById(tabName).classList.add('active');
            event.target.classList.add('active');
        }

        // تفعيل كل شيء عند تحميل الصفحة
        document.addEventListener('DOMContentLoaded', function() {
            createParticles();
        });
    </script>
</body>
</html>

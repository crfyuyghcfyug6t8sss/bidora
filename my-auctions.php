<?php
require_once 'config/database.php';
require_once 'includes/functions.php';
require_once 'includes/ai-chatbot.php';

if (!isLoggedIn()) {
    setMessage('يجب تسجيل الدخول أولاً', 'danger');
    redirect('auth/login.php');
}

$user_id = $_SESSION['user_id'];

// جلب معلومات المستخدم
$stmt = $conn->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$user = $stmt->fetch();

// جلب جميع مزادات البائع
$stmt = $conn->prepare("
    SELECT
        a.*,
        v.*,
        winner.username as winner_name,
        (SELECT COUNT(*) FROM bids WHERE auction_id = a.id) as total_bids_count,
        (SELECT image_path FROM vehicle_images WHERE vehicle_id = v.id AND is_primary = TRUE LIMIT 1) as main_image
    FROM auctions a
    JOIN vehicles v ON a.vehicle_id = v.id
    LEFT JOIN users winner ON a.highest_bidder_id = winner.id
    WHERE a.seller_id = ?
    ORDER BY a.created_at DESC
");
$stmt->execute([$user_id]);
$auctions = $stmt->fetchAll();

// تصنيف المزادات
$active_auctions = [];
$completed_auctions = [];
$upcoming_auctions = [];
$expired_auctions = [];

foreach ($auctions as $auction) {
    $now = time();
    $start = strtotime($auction['start_time']);
    $end = strtotime($auction['end_time']);

    if ($auction['status'] == 'completed') {
        $completed_auctions[] = $auction;
    } elseif ($auction['status'] == 'expired') {
        $expired_auctions[] = $auction;
    } elseif ($now >= $start && $now < $end && $auction['status'] == 'active') {
        $active_auctions[] = $auction;
    } elseif ($now < $start) {
        $upcoming_auctions[] = $auction;
    }
}

getChatbotWidget();
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>🏆 مزاداتي - إدارة احترافية</title>

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

            /* Colors */
            --primary: #667eea;
            --secondary: #764ba2;
            --success: #13f1fc;
            --warning: #f9d423;
            --danger: #ff4e50;

            /* Shadows */
            --shadow-sm: 0 2px 20px rgba(0, 0, 0, 0.1);
            --shadow-md: 0 10px 40px rgba(0, 0, 0, 0.15);
            --shadow-lg: 0 20px 60px rgba(0, 0, 0, 0.2);

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
            position: relative;
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
            position: relative;
        }

        /* شعار متحرك */
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

        /* قائمة التنقل الفاخرة */
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

        /* شبكة الإحصائيات */
        .stats-grid-premium {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 25px;
            margin-bottom: 40px;
        }

        .stat-card-3d {
            background: var(--glass-white);
            backdrop-filter: blur(20px);
            border: 1px solid var(--glass-border);
            border-radius: 25px;
            padding: 30px;
            position: relative;
            overflow: hidden;
            transition: var(--transition);
            animation: cardFadeIn 0.8s ease-out backwards;
            transform-style: preserve-3d;
        }

        .stat-card-3d:nth-child(1) { animation-delay: 0.1s; }
        .stat-card-3d:nth-child(2) { animation-delay: 0.2s; }
        .stat-card-3d:nth-child(3) { animation-delay: 0.3s; }
        .stat-card-3d:nth-child(4) { animation-delay: 0.4s; }

        @keyframes cardFadeIn {
            from {
                opacity: 0;
                transform: translateY(30px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .stat-card-3d:hover {
            transform: translateY(-10px);
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
        }

        .stat-card-3d::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 5px;
            background: var(--gradient);
            transform: scaleX(0);
            transform-origin: left;
            transition: transform 0.5s ease;
        }

        .stat-card-3d:hover::before {
            transform: scaleX(1);
        }

        .stat-value-premium {
            font-size: 2.5rem;
            font-weight: 900;
            color: white;
            margin-bottom: 10px;
        }

        .stat-label-premium {
            color: rgba(255, 255, 255, 0.7);
            font-size: 1rem;
            font-weight: 600;
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

        /* Auction Cards */
        .auctions-grid {
            display: grid;
            gap: 25px;
        }

        .auction-card-premium {
            background: var(--glass-white);
            backdrop-filter: blur(20px);
            border: 1px solid var(--glass-border);
            border-radius: 25px;
            padding: 25px;
            display: grid;
            grid-template-columns: 150px 1fr auto;
            gap: 25px;
            align-items: center;
            transition: var(--transition);
            position: relative;
            overflow: hidden;
        }

        .auction-card-premium::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.1), transparent);
            transition: left 0.8s;
        }

        .auction-card-premium:hover {
            transform: translateY(-5px);
            box-shadow: 0 15px 50px rgba(0, 0, 0, 0.3);
        }

        .auction-card-premium:hover::before {
            left: 100%;
        }

        .auction-image {
            width: 150px;
            height: 150px;
            border-radius: 20px;
            overflow: hidden;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 3rem;
            position: relative;
        }

        .auction-image img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .auction-info h3 {
            color: white;
            font-size: 1.5rem;
            font-weight: 700;
            margin-bottom: 10px;
        }

        .auction-meta {
            display: flex;
            gap: 20px;
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

        .auction-stats {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 15px;
            min-width: 250px;
        }

        .stat-box {
            background: rgba(255, 255, 255, 0.05);
            padding: 15px;
            border-radius: 15px;
            text-align: center;
            border: 1px solid rgba(255, 255, 255, 0.1);
        }

        .stat-box-label {
            font-size: 0.85rem;
            color: rgba(255, 255, 255, 0.6);
            margin-bottom: 5px;
        }

        .stat-box-value {
            font-size: 1.3rem;
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
            margin-left: 10px;
        }

        .status-active { background: rgba(16, 185, 129, 0.2); color: #10b981; border: 1px solid rgba(16, 185, 129, 0.3); }
        .status-completed { background: rgba(59, 130, 246, 0.2); color: #3b82f6; border: 1px solid rgba(59, 130, 246, 0.3); }
        .status-expired { background: rgba(239, 68, 68, 0.2); color: #ef4444; border: 1px solid rgba(239, 68, 68, 0.3); }

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

        .btn-success {
            background: var(--emerald-gradient);
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

            .auction-card-premium {
                grid-template-columns: 1fr;
            }

            .auction-stats {
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
                <h1 class="page-title">🏆 مزاداتي</h1>
                <p class="page-subtitle">إدارة احترافية لجميع السيارات التي أضفتها للمزاد</p>
            </div>

            <!-- الإحصائيات -->
            <div class="stats-grid-premium">
                <div class="stat-card-3d" style="--gradient: var(--emerald-gradient);" data-aos="zoom-in">
                    <div class="stat-value-premium"><?php echo count($active_auctions); ?></div>
                    <div class="stat-label-premium">مزادات نشطة</div>
                </div>
                <div class="stat-card-3d" style="--gradient: var(--ocean-gradient);" data-aos="zoom-in">
                    <div class="stat-value-premium"><?php echo count($completed_auctions); ?></div>
                    <div class="stat-label-premium">مزادات مكتملة</div>
                </div>
                <div class="stat-card-3d" style="--gradient: var(--fire-gradient);" data-aos="zoom-in">
                    <div class="stat-value-premium">
                        $<?php
                        $total_revenue = 0;
                        foreach ($completed_auctions as $a) {
                            $total_revenue += $a['current_price'] * 0.95;
                        }
                        echo number_format($total_revenue, 0);
                        ?>
                    </div>
                    <div class="stat-label-premium">إجمالي الإيرادات</div>
                </div>
                <div class="stat-card-3d" style="--gradient: var(--mystic-gradient);" data-aos="zoom-in">
                    <div class="stat-value-premium"><?php echo count($auctions); ?></div>
                    <div class="stat-label-premium">إجمالي المزادات</div>
                </div>
            </div>

            <div style="margin-bottom: 30px; text-align: center;" data-aos="fade-up">
                <a href="add-vehicle.php" class="btn-premium btn-success">
                    <i class="fas fa-plus"></i>
                    إضافة سيارة جديدة
                </a>
            </div>

            <!-- Tabs -->
            <div class="tabs-premium" data-aos="fade-up">
                <button class="tab-premium active" onclick="switchTab('active')">
                    نشطة (<?php echo count($active_auctions); ?>)
                </button>
                <button class="tab-premium" onclick="switchTab('completed')">
                    مكتملة (<?php echo count($completed_auctions); ?>)
                </button>
                <button class="tab-premium" onclick="switchTab('expired')">
                    منتهية (<?php echo count($expired_auctions); ?>)
                </button>
            </div>

            <!-- Active Auctions -->
            <div id="active" class="tab-content active">
                <?php if (empty($active_auctions)): ?>
                    <div class="empty-state" data-aos="fade-up">
                        <div class="empty-state-icon">📦</div>
                        <h3>لا توجد مزادات نشطة</h3>
                        <p>ابدأ بإضافة سيارة للمزاد</p>
                        <a href="add-vehicle.php" class="btn-premium">إضافة سيارة</a>
                    </div>
                <?php else: ?>
                    <div class="auctions-grid">
                        <?php foreach ($active_auctions as $auction): ?>
                            <div class="auction-card-premium" data-aos="fade-up">
                                <div class="auction-image">
                                    <?php if ($auction['main_image']): ?>
                                        <img src="<?php echo htmlspecialchars($auction['main_image']); ?>" alt="">
                                    <?php else: ?>
                                        <i class="fas fa-car"></i>
                                    <?php endif; ?>
                                </div>

                                <div class="auction-info">
                                    <h3><?php echo htmlspecialchars($auction['brand'] . ' ' . $auction['model']); ?></h3>
                                    <span class="status-badge status-active">
                                        <i class="fas fa-circle"></i> نشط
                                    </span>
                                    <div class="auction-meta">
                                        <span class="meta-item">
                                            <i class="fas fa-calendar"></i> <?php echo $auction['year']; ?>
                                        </span>
                                        <span class="meta-item">
                                            <i class="fas fa-clock"></i> ينتهي: <?php echo date('Y-m-d H:i', strtotime($auction['end_time'])); ?>
                                        </span>
                                        <span class="meta-item">
                                            <i class="fas fa-gavel"></i> <?php echo $auction['total_bids']; ?> مزايدة
                                        </span>
                                    </div>
                                </div>

                                <div class="auction-stats">
                                    <div class="stat-box">
                                        <div class="stat-box-label">السعر الابتدائي</div>
                                        <div class="stat-box-value">$<?php echo number_format($auction['starting_price'], 0); ?></div>
                                    </div>
                                    <div class="stat-box">
                                        <div class="stat-box-label">السعر الحالي</div>
                                        <div class="stat-box-value" style="color: #10b981;">$<?php echo number_format($auction['current_price'], 0); ?></div>
                                    </div>
                                    <div class="stat-box" style="grid-column: 1 / -1;">
                                        <div class="stat-box-label">أعلى مزايد</div>
                                        <div class="stat-box-value" style="font-size: 1rem;">
                                            <?php echo $auction['winner_name'] ? htmlspecialchars($auction['winner_name']) : 'لا يوجد'; ?>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div style="text-align: left; margin-top: -15px; margin-bottom: 20px;" data-aos="fade-up">
                                <a href="auction-details.php?id=<?php echo $auction['id']; ?>" class="btn-premium btn-outline">
                                    <i class="fas fa-eye"></i> عرض التفاصيل
                                </a>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Completed Auctions -->
            <div id="completed" class="tab-content">
                <?php if (empty($completed_auctions)): ?>
                    <div class="empty-state" data-aos="fade-up">
                        <div class="empty-state-icon">✅</div>
                        <h3>لا توجد مزادات مكتملة بعد</h3>
                    </div>
                <?php else: ?>
                    <div class="auctions-grid">
                        <?php foreach ($completed_auctions as $auction): ?>
                            <div class="auction-card-premium" data-aos="fade-up">
                                <div class="auction-image">
                                    <?php if ($auction['main_image']): ?>
                                        <img src="<?php echo htmlspecialchars($auction['main_image']); ?>" alt="">
                                    <?php else: ?>
                                        <i class="fas fa-car"></i>
                                    <?php endif; ?>
                                </div>

                                <div class="auction-info">
                                    <h3><?php echo htmlspecialchars($auction['brand'] . ' ' . $auction['model']); ?></h3>
                                    <span class="status-badge status-completed">
                                        <i class="fas fa-check-circle"></i> مكتمل
                                    </span>
                                    <div class="auction-meta">
                                        <span class="meta-item">
                                            <i class="fas fa-user"></i> الفائز: <?php echo htmlspecialchars($auction['winner_name'] ?? 'غير متاح'); ?>
                                        </span>
                                        <span class="meta-item">
                                            <i class="fas fa-calendar"></i> <?php echo date('Y-m-d', strtotime($auction['end_time'])); ?>
                                        </span>
                                    </div>
                                </div>

                                <div class="auction-stats">
                                    <div class="stat-box">
                                        <div class="stat-box-label">السعر النهائي</div>
                                        <div class="stat-box-value">$<?php echo number_format($auction['current_price'], 0); ?></div>
                                    </div>
                                    <div class="stat-box">
                                        <div class="stat-box-label">صافي الربح</div>
                                        <div class="stat-box-value" style="color: #10b981;">
                                            $<?php echo number_format($auction['current_price'] * 0.95, 0); ?>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div style="text-align: left; margin-top: -15px; margin-bottom: 20px;" data-aos="fade-up">
                                <a href="auction-details.php?id=<?php echo $auction['id']; ?>" class="btn-premium btn-outline">
                                    <i class="fas fa-eye"></i> عرض التفاصيل
                                </a>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Expired Auctions -->
            <div id="expired" class="tab-content">
                <?php if (empty($expired_auctions)): ?>
                    <div class="empty-state" data-aos="fade-up">
                        <div class="empty-state-icon">⏰</div>
                        <h3>لا توجد مزادات منتهية</h3>
                    </div>
                <?php else: ?>
                    <div class="auctions-grid">
                        <?php foreach ($expired_auctions as $auction): ?>
                            <div class="auction-card-premium" data-aos="fade-up">
                                <div class="auction-image" style="background: #94a3b8;">
                                    <?php if ($auction['main_image']): ?>
                                        <img src="<?php echo htmlspecialchars($auction['main_image']); ?>" alt="" style="opacity: 0.5;">
                                    <?php else: ?>
                                        <i class="fas fa-car"></i>
                                    <?php endif; ?>
                                </div>

                                <div class="auction-info">
                                    <h3><?php echo htmlspecialchars($auction['brand'] . ' ' . $auction['model']); ?></h3>
                                    <span class="status-badge status-expired">
                                        <i class="fas fa-times-circle"></i> منتهي
                                    </span>
                                    <div class="auction-meta">
                                        <span class="meta-item">
                                            <i class="fas fa-calendar"></i> انتهى: <?php echo date('Y-m-d', strtotime($auction['end_time'])); ?>
                                        </span>
                                        <span class="meta-item">
                                            <i class="fas fa-gavel"></i> <?php echo $auction['total_bids']; ?> مزايدة
                                        </span>
                                    </div>
                                </div>

                                <div class="auction-stats">
                                    <div class="stat-box">
                                        <div class="stat-box-label">آخر سعر</div>
                                        <div class="stat-box-value">$<?php echo number_format($auction['current_price'], 0); ?></div>
                                    </div>
                                </div>
                            </div>
                            <div style="text-align: left; margin-top: -15px; margin-bottom: 20px;" data-aos="fade-up">
                                <a href="auction-details.php?id=<?php echo $auction['id']; ?>" class="btn-premium btn-outline">
                                    <i class="fas fa-eye"></i> عرض التفاصيل
                                </a>
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

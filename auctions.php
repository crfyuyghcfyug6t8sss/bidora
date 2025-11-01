<?php
require_once 'config/database.php';
require_once 'includes/functions.php';

// جلب المزادات النشطة
$stmt = $conn->query("
    SELECT 
        a.*,
        v.brand,
        v.model,
        v.year,
        v.mileage,
        v.color,
        v.transmission,
        u.username as seller_name
    FROM auctions a
    JOIN vehicles v ON a.vehicle_id = v.id
    JOIN users u ON a.seller_id = u.id
    WHERE a.status = 'active' AND a.end_time > NOW()
    ORDER BY a.end_time ASC
");
$auctions = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>المزادات الحصرية - سيارات الأحلام</title>
    
    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@200;300;400;500;600;700;800;900&family=Bebas+Neue&family=Oswald:wght@400;500;600;700&display=swap" rel="stylesheet">
    
    <!-- Icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.2/font/bootstrap-icons.min.css">
    
    <!-- AOS Animation -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/aos/2.3.4/aos.css">
    
    <!-- Swiper -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/swiper@11/swiper-bundle.min.css">
    
    <style>
        :root {
            /* ألوان احترافية */
            --primary-color: #2563eb;
            --primary-dark: #1e40af;
            --primary-light: #3b82f6;
            --secondary-color: #8b5cf6;
            --accent-color: #f59e0b;
            --success-color: #10b981;
            --danger-color: #ef4444;
            
            /* خلفيات */
            --dark-bg: #0f172a;
            --dark-card: #1e293b;
            --dark-hover: #334155;
            --text-primary: #f8fafc;
            --text-secondary: #cbd5e1;
            --text-muted: #94a3b8;
            --border-color: #334155;
            
            /* تدرجات */
            --gradient-primary: linear-gradient(135deg, #2563eb 0%, #7c3aed 100%);
            --gradient-success: linear-gradient(135deg, #10b981 0%, #3b82f6 100%);
            --gradient-warm: linear-gradient(135deg, #f59e0b 0%, #ef4444 100%);
            
            /* ظلال */
            --shadow-sm: 0 2px 8px rgba(0, 0, 0, 0.1);
            --shadow-md: 0 4px 16px rgba(0, 0, 0, 0.15);
            --shadow-lg: 0 8px 32px rgba(0, 0, 0, 0.2);
            --shadow-xl: 0 16px 48px rgba(0, 0, 0, 0.25);
            
            /* انتقالات */
            --transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }
        
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Cairo', sans-serif;
            background: #0a0a0a;
            min-height: 100vh;
            direction: rtl;
            overflow-x: hidden;
            position: relative;
        }
        
        /* الخلفية المتحركة */
        .cosmic-background {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            z-index: -3;
            background: linear-gradient(125deg, #0f0c29, #302b63, #24243e);
            animation: cosmicShift 30s ease infinite;
        }
        
        @keyframes cosmicShift {
            0%, 100% { background: linear-gradient(125deg, #0f0c29, #302b63, #24243e); }
            33% { background: linear-gradient(125deg, #24243e, #0f0c29, #302b63); }
            66% { background: linear-gradient(125deg, #302b63, #24243e, #0f0c29); }
        }
        
        /* خطوط الطاقة */
        .energy-lines {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            z-index: -2;
            overflow: hidden;
            opacity: 0.4;
        }
        
        .energy-line {
            position: absolute;
            width: 2px;
            height: 100vh;
            background: linear-gradient(to bottom, transparent, var(--primary-light), transparent);
            animation: energyFlow 8s linear infinite;
        }
        
        @keyframes energyFlow {
            0% { transform: translateY(-100vh); }
            100% { transform: translateY(100vh); }
        }
        
        .energy-line:nth-child(1) { left: 10%; animation-delay: 0s; }
        .energy-line:nth-child(2) { left: 30%; animation-delay: 2s; }
        .energy-line:nth-child(3) { left: 50%; animation-delay: 4s; }
        .energy-line:nth-child(4) { left: 70%; animation-delay: 6s; }
        .energy-line:nth-child(5) { left: 90%; animation-delay: 8s; }
        
        /* جسيمات عائمة */
        .floating-particles {
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
            background: radial-gradient(circle, rgba(255, 255, 255, 0.8) 0%, transparent 70%);
            border-radius: 50%;
            animation: particleDrift 20s infinite linear;
        }
        
        @keyframes particleDrift {
            0% { transform: translate(0, 100vh) scale(0); opacity: 0; }
            10% { transform: translate(10vw, 80vh) scale(1); opacity: 1; }
            90% { transform: translate(-10vw, -80vh) scale(1); opacity: 1; }
            100% { transform: translate(0, -100vh) scale(0); opacity: 0; }
        }
        
        /* الهيدر */
        .hero-section {
            position: relative;
            padding: 120px 0 80px;
            text-align: center;
            overflow: hidden;
            background: linear-gradient(135deg, rgba(15, 12, 41, 0.9) 0%, rgba(48, 43, 99, 0.8) 100%);
            border-bottom: 2px solid rgba(124, 58, 237, 0.3);
        }
        
        .hero-section::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 200%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(124, 58, 237, 0.1), transparent);
            animation: shimmer 3s infinite;
        }
        
        @keyframes shimmer {
            0% { left: -100%; }
            100% { left: 100%; }
        }
        
        .hero-title {
            font-size: 4rem;
            font-weight: 900;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            margin-bottom: 20px;
            text-shadow: 0 0 80px rgba(102, 126, 234, 0.5);
            animation: titlePulse 3s ease-in-out infinite;
            position: relative;
            z-index: 1;
        }
        
        @keyframes titlePulse {
            0%, 100% { transform: scale(1); }
            50% { transform: scale(1.02); }
        }
        
        .hero-subtitle {
            font-size: 1.4rem;
            color: rgba(255, 255, 255, 0.8);
            font-weight: 300;
            letter-spacing: 2px;
            position: relative;
            z-index: 1;
        }
        
        /* الحاوية الرئيسية */
        .container {
            max-width: 1400px;
            margin: 0 auto;
            padding: 60px 20px;
        }
        
        /* شبكة المزادات */
        .auctions-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(380px, 1fr));
            gap: 30px;
            margin-top: 40px;
        }
        
        /* بطاقة المزاد المحسنة */
        .auction-card-premium {
            background: rgba(30, 41, 59, 0.95);
            border-radius: 20px;
            overflow: hidden;
            transition: var(--transition);
            border: 1px solid rgba(124, 58, 237, 0.2);
            position: relative;
            backdrop-filter: blur(10px);
        }
        
        .auction-card-premium:hover {
            transform: translateY(-10px) scale(1.02);
            box-shadow: 0 20px 60px rgba(124, 58, 237, 0.4);
            border-color: var(--primary-light);
        }
        
        /* شريط الحالة */
        .premium-badge {
            position: absolute;
            top: 20px;
            right: 20px;
            background: var(--gradient-success);
            color: white;
            padding: 8px 20px;
            border-radius: 25px;
            font-size: 0.9rem;
            font-weight: 600;
            z-index: 10;
            display: flex;
            align-items: center;
            gap: 8px;
            box-shadow: var(--shadow-lg);
        }
        
        .premium-badge i {
            font-size: 0.8rem;
            animation: pulse 2s infinite;
        }
        
        @keyframes pulse {
            0%, 100% { opacity: 1; }
            50% { opacity: 0.5; }
        }
        
        /* صورة السيارة */
        .card-image-premium {
            position: relative;
            width: 100%;
            height: 280px;
            overflow: hidden;
            background: linear-gradient(135deg, #334155 0%, #1e293b 100%);
        }
        
        .card-image-premium img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            transition: var(--transition);
        }
        
        .auction-card-premium:hover .card-image-premium img {
            transform: scale(1.15);
        }
        
        /* محتوى البطاقة */
        .card-content-premium {
            padding: 25px;
        }
        
        /* عنوان السيارة */
        .vehicle-title {
            font-size: 1.8rem;
            font-weight: 700;
            color: var(--text-primary);
            margin-bottom: 10px;
            line-height: 1.3;
        }
        
        .auction-id {
            color: var(--text-muted);
            font-size: 0.95rem;
            display: flex;
            align-items: center;
            gap: 8px;
            margin-bottom: 20px;
        }
        
        /* شبكة المواصفات */
        .specs-grid-premium {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 12px;
            margin-bottom: 20px;
        }
        
        .spec-item {
            background: rgba(51, 65, 85, 0.5);
            padding: 12px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            gap: 12px;
            border: 1px solid rgba(124, 58, 237, 0.1);
            transition: var(--transition);
        }
        
        .spec-item:hover {
            background: rgba(37, 99, 235, 0.15);
            border-color: var(--primary-light);
            transform: translateX(-3px);
        }
        
        .spec-icon {
            width: 40px;
            height: 40px;
            border-radius: 10px;
            background: var(--gradient-primary);
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
        }
        
        .spec-icon i {
            color: white;
            font-size: 1.1rem;
        }
        
        .spec-text {
            flex: 1;
            min-width: 0;
        }
        
        .spec-label {
            display: block;
            font-size: 0.8rem;
            color: var(--text-muted);
            margin-bottom: 2px;
        }
        
        .spec-value {
            display: block;
            font-weight: 600;
            color: var(--text-primary);
            font-size: 1rem;
        }
        
        /* قسم السعر */
        .price-section {
            background: linear-gradient(135deg, rgba(37, 99, 235, 0.2), rgba(124, 58, 237, 0.2));
            padding: 20px;
            border-radius: 15px;
            text-align: center;
            margin-bottom: 20px;
            border: 1px solid rgba(37, 99, 235, 0.3);
        }
        
        .price-label {
            font-size: 0.9rem;
            color: var(--text-secondary);
            margin-bottom: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
        }
        
        .price-label i {
            color: var(--accent-color);
        }
        
        .price-value {
            font-size: 2.2rem;
            font-weight: 800;
            color: var(--text-primary);
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 5px;
        }
        
        .price-currency {
            font-size: 1.6rem;
            color: var(--accent-color);
        }
        
        /* العداد التنازلي */
        .countdown-section {
            background: linear-gradient(135deg, rgba(16, 185, 129, 0.2), rgba(59, 130, 246, 0.2));
            padding: 15px;
            border-radius: 15px;
            text-align: center;
            margin-bottom: 20px;
            border: 1px solid rgba(16, 185, 129, 0.3);
        }
        
        .countdown-section.urgent {
            background: linear-gradient(135deg, rgba(239, 68, 68, 0.2), rgba(245, 158, 11, 0.2));
            border-color: rgba(239, 68, 68, 0.4);
            animation: urgentPulse 2s infinite;
        }
        
        @keyframes urgentPulse {
            0%, 100% { box-shadow: 0 0 0 0 rgba(239, 68, 68, 0.5); }
            50% { box-shadow: 0 0 0 15px rgba(239, 68, 68, 0); }
        }
        
        .countdown-label {
            font-size: 0.9rem;
            color: var(--text-secondary);
            margin-bottom: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
        }
        
        .countdown-label i {
            color: var(--success-color);
        }
        
        .countdown-section.urgent .countdown-label i {
            color: var(--danger-color);
        }
        
        .countdown-timer {
            font-size: 1.6rem;
            font-weight: 700;
            color: var(--text-primary);
            font-family: 'Courier New', monospace;
            letter-spacing: 2px;
        }
        
        /* معلومات إضافية */
        .auction-meta {
            display: flex;
            gap: 15px;
            padding-top: 15px;
            border-top: 1px solid rgba(124, 58, 237, 0.2);
            margin-bottom: 20px;
        }
        
        .meta-item {
            flex: 1;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .meta-icon {
            width: 40px;
            height: 40px;
            border-radius: 10px;
            background: rgba(51, 65, 85, 0.5);
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
        }
        
        .meta-icon i {
            color: var(--primary-light);
            font-size: 1rem;
        }
        
        .meta-text {
            flex: 1;
            min-width: 0;
        }
        
        .meta-label {
            display: block;
            font-size: 0.8rem;
            color: var(--text-muted);
            margin-bottom: 2px;
        }
        
        .meta-value {
            display: block;
            font-weight: 600;
            color: var(--text-primary);
            font-size: 0.95rem;
        }
        
        /* زر الإجراءات */
        .btn-3d {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 12px;
            width: 100%;
            padding: 16px;
            background: var(--gradient-primary);
            color: white;
            text-decoration: none;
            border-radius: 15px;
            font-weight: 600;
            font-size: 1.1rem;
            transition: var(--transition);
            border: none;
            cursor: pointer;
            position: relative;
            overflow: hidden;
            box-shadow: 0 10px 30px rgba(37, 99, 235, 0.3);
        }
        
        .btn-3d::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.3), transparent);
            transition: left 0.6s;
        }
        
        .btn-3d:hover {
            transform: translateY(-3px);
            box-shadow: 0 15px 40px rgba(37, 99, 235, 0.5);
        }
        
        .btn-3d:hover::before {
            left: 100%;
        }
        
        /* رسالة فارغة */
        .empty-state {
            text-align: center;
            padding: 80px 40px;
            background: rgba(30, 41, 59, 0.6);
            border-radius: 20px;
            border: 2px dashed rgba(124, 58, 237, 0.3);
            backdrop-filter: blur(10px);
        }
        
        .empty-icon {
            width: 120px;
            height: 120px;
            margin: 0 auto 30px;
            background: var(--gradient-primary);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            box-shadow: 0 20px 60px rgba(37, 99, 235, 0.4);
        }
        
        .empty-icon i {
            font-size: 4rem;
            color: white;
        }
        
        .empty-title {
            font-size: 2rem;
            font-weight: 700;
            color: var(--text-primary);
            margin-bottom: 15px;
        }
        
        .empty-text {
            color: var(--text-secondary);
            font-size: 1.2rem;
        }
        
        /* التجاوب */
        @media (max-width: 1200px) {
            .auctions-grid {
                grid-template-columns: repeat(auto-fill, minmax(340px, 1fr));
            }
        }
        
        @media (max-width: 768px) {
            .hero-title {
                font-size: 2.5rem;
            }
            
            .hero-subtitle {
                font-size: 1.1rem;
            }
            
            .auctions-grid {
                grid-template-columns: 1fr;
                gap: 25px;
            }
            
            .specs-grid-premium {
                grid-template-columns: 1fr;
            }
            
            .vehicle-title {
                font-size: 1.5rem;
            }
        }
    </style>
</head>
<body>
    <!-- الخلفية -->
    <div class="cosmic-background"></div>
    <div class="energy-lines">
        <div class="energy-line"></div>
        <div class="energy-line"></div>
        <div class="energy-line"></div>
        <div class="energy-line"></div>
        <div class="energy-line"></div>
    </div>
    <div class="floating-particles" id="particles"></div>
    
    <!-- قسم البطل -->
    <section class="hero-section">
        <div class="container">
            <h1 class="hero-title" data-translate="page_title">المزادات الحصرية</h1>
            <p class="hero-subtitle" data-translate="page_subtitle">اكتشف أفضل العروض على سيارات الأحلام</p>
        </div>
    </section>
    
    <!-- المحتوى الرئيسي -->
    <main class="container">
        <?php if (empty($auctions)): ?>
            <div class="empty-state" data-aos="fade-up">
                <div class="empty-icon">
                    <i class="fas fa-gavel"></i>
                </div>
                <h2 class="empty-title" data-translate="no_auctions">لا توجد مزادات نشطة</h2>
                <p class="empty-text" data-translate="check_later">تحقق مرة أخرى قريباً للحصول على أفضل العروض</p>
            </div>
        <?php else: ?>
            <div class="auctions-grid">
                <?php foreach ($auctions as $auction): ?>
                    <div class="auction-card-premium" data-aos="fade-up">
                        <!-- شريط الحالة -->
                        <div class="premium-badge">
                            <i class="fas fa-circle"></i>
                            <span data-translate="active">نشط</span>
                        </div>
                        
                        <!-- صورة السيارة -->
                        <div class="card-image-premium">
                            <?php if (!empty($auction['image'])): ?>
                                <img src="<?php echo htmlspecialchars($auction['image']); ?>" 
                                     alt="<?php echo htmlspecialchars($auction['brand'] . ' ' . $auction['model']); ?>">
                            <?php else: ?>
                                <img src="assets/images/default-car.jpg" alt="Car">
                            <?php endif; ?>
                        </div>
                        
                        <!-- محتوى البطاقة -->
                        <div class="card-content-premium">
                            <!-- عنوان السيارة -->
                            <h3 class="vehicle-title">
                                <?php echo htmlspecialchars($auction['brand'] . ' ' . $auction['model']); ?>
                            </h3>
                            <div class="auction-id">
                                <i class="fas fa-hashtag"></i>
                                <span>رقم المزاد: <?php echo $auction['id']; ?></span>
                            </div>
                            
                            <!-- المواصفات -->
                            <div class="specs-grid-premium">
                                <div class="spec-item">
                                    <div class="spec-icon">
                                        <i class="fas fa-calendar-alt"></i>
                                    </div>
                                    <div class="spec-text">
                                        <span class="spec-label" data-translate="year">السنة</span>
                                        <span class="spec-value"><?php echo $auction['year']; ?></span>
                                    </div>
                                </div>
                                
                                <div class="spec-item">
                                    <div class="spec-icon">
                                        <i class="fas fa-road"></i>
                                    </div>
                                    <div class="spec-text">
                                        <span class="spec-label" data-translate="mileage">الكيلومترات</span>
                                        <span class="spec-value"><?php echo number_format($auction['mileage']); ?></span>
                                    </div>
                                </div>
                                
                                <?php if (!empty($auction['color'])): ?>
                                    <div class="spec-item">
                                        <div class="spec-icon">
                                            <i class="fas fa-palette"></i>
                                        </div>
                                        <div class="spec-text">
                                            <span class="spec-label" data-translate="color">اللون</span>
                                            <span class="spec-value"><?php echo htmlspecialchars($auction['color']); ?></span>
                                        </div>
                                    </div>
                                <?php endif; ?>
                                
                                <div class="spec-item">
                                    <div class="spec-icon">
                                        <i class="fas fa-cogs"></i>
                                    </div>
                                    <div class="spec-text">
                                        <span class="spec-label" data-translate="transmission">الناقل</span>
                                        <span class="spec-value">
                                            <?php echo $auction['transmission'] == 'automatic' ? 'أوتوماتيك' : 'مانيوال'; ?>
                                        </span>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- السعر -->
                            <div class="price-section">
                                <div class="price-label">
                                    <i class="fas fa-tag"></i>
                                    <span data-translate="current_price">السعر الحالي</span>
                                </div>
                                <div class="price-value">
                                    <span class="price-currency">$</span>
                                    <?php echo number_format($auction['current_price'], 0); ?>
                                </div>
                            </div>
                            
                            <!-- العد التنازلي -->
                            <?php
                            $now = time();
                            $end = strtotime($auction['end_time']);
                            $remaining = $end - $now;
                            $urgent = $remaining < 86400;
                            ?>
                            <div class="countdown-section <?php echo $urgent ? 'urgent' : ''; ?>" 
                                 data-endtime="<?php echo $auction['end_time']; ?>">
                                <div class="countdown-label">
                                    <i class="fas fa-clock"></i>
                                    <span data-translate="time_remaining">الوقت المتبقي</span>
                                </div>
                                <div class="countdown-timer">
                                    <?php
                                    if ($remaining <= 0) {
                                        echo 'انتهى المزاد';
                                    } else {
                                        $days = floor($remaining / 86400);
                                        $hours = floor(($remaining % 86400) / 3600);
                                        $minutes = floor(($remaining % 3600) / 60);
                                        
                                        if ($days > 0) {
                                            echo $days . ' يوم ';
                                        }
                                        echo sprintf("%02d:%02d", $hours, $minutes);
                                    }
                                    ?>
                                </div>
                            </div>
                            
                            <!-- معلومات إضافية -->
                            <div class="auction-meta">
                                <div class="meta-item">
                                    <div class="meta-icon">
                                        <i class="fas fa-user"></i>
                                    </div>
                                    <div class="meta-text">
                                        <span class="meta-label" data-translate="seller">البائع</span>
                                        <span class="meta-value"><?php echo htmlspecialchars($auction['seller_name']); ?></span>
                                    </div>
                                </div>
                                <div class="meta-item">
                                    <div class="meta-icon">
                                        <i class="fas fa-gavel"></i>
                                    </div>
                                    <div class="meta-text">
                                        <span class="meta-label" data-translate="bids">المزايدات</span>
                                        <span class="meta-value"><?php echo $auction['total_bids']; ?></span>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- أزرار الإجراءات -->
                            <div class="auction-actions">
                                <a href="auction-details.php?id=<?php echo $auction['id']; ?>" class="btn-3d btn-primary shimmer">
                                    <i class="fas fa-eye"></i>
                                    <span data-translate="view_details">عرض التفاصيل</span>
                                </a>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </main>
    
    <!-- Scripts -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/aos/2.3.4/aos.js"></script>
    <script src="js/auto-translate.js"></script>
    
    <script>
        // تهيئة AOS
        AOS.init({
            duration: 1000,
            once: false,
            offset: 100
        });
        
        // إنشاء الجسيمات العائمة
        function createParticles() {
            const container = document.getElementById('particles');
            const particleCount = 80;
            
            for (let i = 0; i < particleCount; i++) {
                const particle = document.createElement('div');
                particle.className = 'particle';
                particle.style.left = Math.random() * 100 + '%';
                particle.style.animationDelay = Math.random() * 20 + 's';
                particle.style.animationDuration = (15 + Math.random() * 10) + 's';
                container.appendChild(particle);
            }
        }
        
        // تحديث العدادات التنازلية
        function updateCountdowns() {
            const countdowns = document.querySelectorAll('.countdown-section');
            
            countdowns.forEach(countdown => {
                const endTime = new Date(countdown.dataset.endtime).getTime();
                const now = new Date().getTime();
                const distance = endTime - now;
                
                const timer = countdown.querySelector('.countdown-timer');
                
                if (distance < 0) {
                    timer.innerHTML = 'انتهى المزاد';
                    countdown.classList.remove('urgent');
                    return;
                }
                
                const days = Math.floor(distance / (1000 * 60 * 60 * 24));
                const hours = Math.floor((distance % (1000 * 60 * 60 * 24)) / (1000 * 60 * 60));
                const minutes = Math.floor((distance % (1000 * 60 * 60)) / (1000 * 60));
                const seconds = Math.floor((distance % (1000 * 60)) / 1000);
                
                let timeString = '';
                if (days > 0) {
                    timeString += days + ' يوم ';
                }
                timeString += `${hours.toString().padStart(2, '0')}:${minutes.toString().padStart(2, '0')}:${seconds.toString().padStart(2, '0')}`;
                
                timer.innerHTML = timeString;
                
                if (distance < 86400000) {
                    countdown.classList.add('urgent');
                }
            });
        }
        
        // تفعيل كل شيء عند التحميل
        document.addEventListener('DOMContentLoaded', function() {
            createParticles();
            setInterval(updateCountdowns, 1000);
            updateCountdowns();
            
            // تأثير Parallax للبطاقات عند حركة الماوس
            document.addEventListener('mousemove', (e) => {
                const cards = document.querySelectorAll('.auction-card-premium');
                const x = e.clientX / window.innerWidth;
                const y = e.clientY / window.innerHeight;
                
                cards.forEach(card => {
                    const rect = card.getBoundingClientRect();
                    const cardX = rect.left + rect.width / 2;
                    const cardY = rect.top + rect.height / 2;
                    
                    const angleX = (y - 0.5) * 10;
                    const angleY = (x - 0.5) * 10;
                    
                    if (card.matches(':hover')) {
                        card.style.transform = `perspective(1000px) rotateX(${-angleX}deg) rotateY(${angleY}deg) translateY(-10px) scale(1.02)`;
                    }
                });
            });
        });
    </script>
</body>
</html>
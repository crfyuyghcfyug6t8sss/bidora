<?php
require_once 'config/database.php';
require_once 'includes/functions.php';
// ✅ تحويل تلقائي للداشبورد إذا كان المستخدم مسجل دخول
if (isLoggedIn()) {
    header("Location: dashboard.php");
    exit;
}

// جلب المزادات النشطة
$stmt = $conn->query("
    SELECT 
        a.*,
        v.*,
        u.username as seller_name,
        (SELECT image_path FROM vehicle_images WHERE vehicle_id = v.id AND is_primary = TRUE LIMIT 1) as main_image,
        (SELECT COUNT(*) FROM vehicle_images WHERE vehicle_id = v.id) as images_count
    FROM auctions a
    JOIN vehicles v ON a.vehicle_id = v.id
    JOIN users u ON a.seller_id = u.id
    WHERE a.status = 'active' AND a.end_time > NOW()
    ORDER BY a.created_at DESC
    LIMIT 6
");
$auctions = $stmt->fetchAll();

// إحصائيات الموقع
$stmt = $conn->query("SELECT COUNT(*) as total FROM users");
$total_users = $stmt->fetch()['total'];

$stmt = $conn->query("SELECT COUNT(*) as total FROM auctions WHERE status = 'completed'");
$total_completed = $stmt->fetch()['total'];

$stmt = $conn->query("SELECT COUNT(*) as total FROM auctions WHERE status = 'active'");
$total_active = $stmt->fetch()['total'];

$stmt = $conn->query("SELECT COUNT(DISTINCT seller_id) as total FROM auctions");
$total_sellers = $stmt->fetch()['total'];
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>كار أوكشن - أفضل منصة مزادات السيارات</title>
    
    <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@300;400;600;700;900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link href="https://unpkg.com/aos@2.3.1/dist/aos.css" rel="stylesheet">
    
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }

        :root {
            --primary-color: #FF6B35;
            --secondary-color: #1A1A2E;
            --accent-color: #16213E;
            --text-light: #FFFFFF;
            --text-dark: #2C2C2C;
            --gold: #FFD700;
            --gradient-primary: linear-gradient(135deg, #FF6B35 0%, #FF8E53 100%);
            --gradient-dark: linear-gradient(135deg, #1A1A2E 0%, #16213E 100%);
            --shadow: 0 20px 60px rgba(0, 0, 0, 0.1);
            --shadow-hover: 0 30px 80px rgba(0, 0, 0, 0.15);
        }

        body {
            font-family: 'Cairo', sans-serif;
            line-height: 1.6;
            color: var(--text-dark);
            overflow-x: hidden;
        }

        html { scroll-behavior: smooth; }

        /* Header */
        header {
            position: fixed;
            top: 0;
            width: 100%;
            background: rgba(26, 26, 46, 0.95);
            backdrop-filter: blur(20px);
            z-index: 1000;
            transition: all 0.3s ease;
            padding: 1rem 0;
        }

        .header-scrolled {
            background: rgba(26, 26, 46, 0.98);
            padding: 0.5rem 0;
        }

        .navbar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 2rem;
        }

        .logo {
            font-size: 2rem;
            font-weight: 900;
            color: var(--primary-color);
            text-decoration: none;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .logo i {
            font-size: 2.5rem;
            animation: pulse 2s infinite;
        }

        @keyframes pulse {
            0%, 100% { transform: scale(1); }
            50% { transform: scale(1.1); }
        }

        .nav-links {
            display: flex;
            list-style: none;
            gap: 2rem;
        }

        .nav-links a {
            color: var(--text-light);
            text-decoration: none;
            font-weight: 600;
            transition: all 0.3s ease;
            position: relative;
        }

        .nav-links a::after {
            content: '';
            position: absolute;
            bottom: -5px;
            left: 0;
            width: 0;
            height: 2px;
            background: var(--primary-color);
            transition: width 0.3s ease;
        }

        .nav-links a:hover::after { width: 100%; }

        .cta-button {
            background: var(--gradient-primary);
            color: var(--text-light);
            padding: 0.8rem 2rem;
            border: none;
            border-radius: 50px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            text-decoration: none;
            display: inline-block;
        }

        .cta-button:hover {
            transform: translateY(-2px);
            box-shadow: var(--shadow);
        }

        /* Hero */
        .hero {
            height: 100vh;
            background: linear-gradient(135deg, rgba(26, 26, 46, 0.8), rgba(255, 107, 53, 0.2)), url('https://images.unsplash.com/photo-1449824913935-59a10b8d2000?w=1920&h=1080&fit=crop');
            background-size: cover;
            background-position: center;
            background-attachment: fixed;
            display: flex;
            align-items: center;
            justify-content: center;
            text-align: center;
            position: relative;
            overflow: hidden;
        }

        .hero::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: linear-gradient(45deg, transparent 30%, rgba(255, 107, 53, 0.1) 50%, transparent 70%);
            animation: shimmer 3s infinite;
        }

        @keyframes shimmer {
            0% { transform: translateX(-100%); }
            100% { transform: translateX(100%); }
        }

        .hero-content {
            max-width: 800px;
            color: var(--text-light);
            z-index: 2;
            position: relative;
        }

        .hero h1 {
            font-size: 4rem;
            font-weight: 900;
            margin-bottom: 1rem;
            background: linear-gradient(135deg, #FFFFFF, #FFD700);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            animation: fadeInUp 1s ease;
        }

        .hero p {
            font-size: 1.3rem;
            margin-bottom: 2rem;
            opacity: 0.9;
            animation: fadeInUp 1s ease 0.3s both;
        }

        .hero-buttons {
            display: flex;
            gap: 1rem;
            justify-content: center;
            flex-wrap: wrap;
            animation: fadeInUp 1s ease 0.6s both;
        }

        .hero-buttons .btn {
            padding: 1rem 2.5rem;
            border-radius: 50px;
            font-weight: 600;
            text-decoration: none;
            transition: all 0.3s ease;
            border: 2px solid transparent;
        }

        .btn-primary {
            background: var(--gradient-primary);
            color: var(--text-light);
        }

        .btn-secondary {
            background: transparent;
            color: var(--text-light);
            border: 2px solid var(--text-light);
        }

        .btn:hover {
            transform: translateY(-3px);
            box-shadow: var(--shadow-hover);
        }

        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(50px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        /* Features */
        .features {
            padding: 5rem 0;
            background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
        }

        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 2rem;
        }

        .section-title {
            text-align: center;
            margin-bottom: 4rem;
        }

        .section-title h2 {
            font-size: 3rem;
            font-weight: 700;
            color: var(--secondary-color);
            margin-bottom: 1rem;
        }

        .section-title p {
            font-size: 1.2rem;
            color: #666;
            max-width: 600px;
            margin: 0 auto;
        }

        .features-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 2rem;
        }

        .feature-card {
            background: white;
            padding: 3rem 2rem;
            border-radius: 20px;
            text-align: center;
            box-shadow: var(--shadow);
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
        }

        .feature-card:hover {
            transform: translateY(-10px);
            box-shadow: var(--shadow-hover);
        }

        .feature-icon {
            width: 80px;
            height: 80px;
            background: var(--gradient-primary);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 1.5rem;
            font-size: 2rem;
            color: white;
            transition: all 0.3s ease;
        }

        .feature-card:hover .feature-icon {
            transform: rotateY(360deg);
        }

        .feature-card h3 {
            font-size: 1.5rem;
            font-weight: 600;
            margin-bottom: 1rem;
            color: var(--secondary-color);
        }

        /* Cars Section */
        .cars-section {
            padding: 5rem 0;
            background: var(--secondary-color);
        }

        .cars-section .section-title h2,
        .cars-section .section-title p {
            color: var(--text-light);
        }

        .cars-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(350px, 1fr));
            gap: 2rem;
        }

        .car-card {
            background: white;
            border-radius: 20px;
            overflow: hidden;
            box-shadow: var(--shadow);
            transition: all 0.3s ease;
        }

        .car-card:hover {
            transform: translateY(-10px);
            box-shadow: var(--shadow-hover);
        }

        .car-image {
            position: relative;
            overflow: hidden;
            height: 250px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 4rem;
        }

        .car-image img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            transition: all 0.3s ease;
        }

        .car-card:hover .car-image img {
            transform: scale(1.1);
        }

        .car-badge {
            position: absolute;
            top: 1rem;
            right: 1rem;
            background: var(--gradient-primary);
            color: white;
            padding: 0.5rem 1rem;
            border-radius: 20px;
            font-weight: 600;
            font-size: 0.9rem;
        }

        .countdown {
            position: absolute;
            bottom: 1rem;
            left: 1rem;
            background: rgba(0, 0, 0, 0.8);
            color: white;
            padding: 0.5rem 1rem;
            border-radius: 10px;
            font-weight: 600;
            font-size: 0.9rem;
        }

        .car-content {
            padding: 2rem;
        }

        .car-title {
            font-size: 1.3rem;
            font-weight: 600;
            margin-bottom: 1rem;
            color: var(--secondary-color);
        }

        .car-specs {
            display: flex;
            justify-content: space-between;
            margin-bottom: 1.5rem;
            color: #666;
            font-size: 0.9rem;
        }

        .car-price {
            font-size: 1.5rem;
            font-weight: 700;
            color: var(--primary-color);
            margin-bottom: 1.5rem;
        }

        .car-actions {
            display: flex;
            gap: 1rem;
        }

        .btn-small {
            padding: 0.8rem 1.5rem;
            border-radius: 25px;
            font-weight: 600;
            text-decoration: none;
            text-align: center;
            transition: all 0.3s ease;
            flex: 1;
        }

        /* Stats */
        .stats {
            padding: 5rem 0;
            background: var(--gradient-primary);
            color: white;
            text-align: center;
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 2rem;
        }

        .stat-number {
            font-size: 3rem;
            font-weight: 900;
            display: block;
            margin-bottom: 0.5rem;
        }

        .stat-label {
            font-size: 1.1rem;
            opacity: 0.9;
        }

        /* Footer */
        .footer {
            background: #0a0a0a;
            color: white;
            padding: 3rem 0 1rem;
        }

        .footer-content {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 2rem;
            margin-bottom: 2rem;
        }

        .footer-section h3 {
            font-size: 1.3rem;
            margin-bottom: 1rem;
            color: var(--primary-color);
        }

        .footer-section a {
            color: #ccc;
            text-decoration: none;
            display: block;
            margin-bottom: 0.5rem;
            transition: color 0.3s ease;
        }

        .footer-section a:hover {
            color: var(--primary-color);
        }

        .social-links {
            display: flex;
            gap: 1rem;
            margin-top: 1rem;
        }

        .social-links a {
            width: 40px;
            height: 40px;
            background: var(--primary-color);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.2rem;
            transition: all 0.3s ease;
        }

        .social-links a:hover {
            transform: translateY(-3px);
            background: #e55a2b;
        }

        .footer-bottom {
            border-top: 1px solid #333;
            padding-top: 2rem;
            text-align: center;
            color: #999;
        }

        .scroll-top {
            position: fixed;
            bottom: 2rem;
            right: 2rem;
            width: 50px;
            height: 50px;
            background: var(--gradient-primary);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 1.2rem;
            cursor: pointer;
            opacity: 0;
            visibility: hidden;
            transition: all 0.3s ease;
            z-index: 1000;
        }

        .scroll-top.visible {
            opacity: 1;
            visibility: visible;
        }

        @media (max-width: 768px) {
            .hero h1 { font-size: 2.5rem; }
            .hero p { font-size: 1.1rem; }
            .features-grid, .cars-grid { grid-template-columns: 1fr; }
        }
    </style>
</head>
<body>
    <header id="header">
        <nav class="navbar">
            <a href="index.php" class="logo">
                <i class="fas fa-car"></i>
                كار أوكشن
            </a>
                                <div style="position: fixed; top: 20px; left: 20px; z-index: 1000;">
        <?php include 'includes/lang-switcher.php'; ?>
    </div>
    <script src="js/auto-translate.js"></script>

            <ul class="nav-links">
                <li><a href="#home">الرئيسية</a></li>
                <li><a href="auctions.php">المزادات</a></li>
                <li><a href="store.php">المتجر</a></li>
                <?php if (isLoggedIn()): ?>
                    <li><a href="dashboard.php">لوحة التحكم</a></li>
                    <li><a href="auth/logout.php">خروج</a></li>
                <?php else: ?>
                    <li><a href="auth/login.php">دخول</a></li>
                <?php endif; ?>
            </ul>
            
            <a href="<?php echo isLoggedIn() ? 'add-vehicle.php' : 'auth/login.php'; ?>" class="cta-button">
                ابدأ المزايدة
            </a>
        </nav>
    </header>

    <section class="hero" id="home">
        <div class="hero-content">
            <h1 data-aos="fade-up">منصة مزادات السيارات الأولى</h1>
            <p data-aos="fade-up" data-aos-delay="300">اكتشف عالم السيارات الاستثنائية واحصل على سيارة أحلامك بأفضل الأسعار</p>
            <div class="hero-buttons">
                <a href="auth/register.php" class="btn btn-primary" data-aos="fade-up" data-aos-delay="600">ابدأ الان</a>
                <a href="auctions.php" class="btn btn-secondary" data-aos="fade-up" data-aos-delay="800">جميع المزادات</a>
            </div>
        </div>
    </section>

    <section class="features" id="features">
        <div class="container">
            <div class="section-title" data-aos="fade-up">
                <h2>لماذا تختار كار أوكشن؟</h2>
                <p>نحن نقدم تجربة مزادات فريدة ومبتكرة مع أعلى معايير الأمان والشفافية</p>
            </div>
            
            <div class="features-grid">
                <div class="feature-card" data-aos="fade-up" data-aos-delay="100">
                    <div class="feature-icon"><i class="fas fa-shield-alt"></i></div>
                    <h3>أمان وثقة</h3>
                    <p>جميع المعاملات محمية بأحدث تقنيات الأمان مع ضمان شامل</p>
                </div>
                
                <div class="feature-card" data-aos="fade-up" data-aos-delay="200">
                    <div class="feature-icon"><i class="fas fa-clock"></i></div>
                    <h3>مزادات مباشرة</h3>
                    <p>مزايدة حية ومباشرة مع عدادات زمنية دقيقة وتحديثات فورية</p>
                </div>
                
                <div class="feature-card" data-aos="fade-up" data-aos-delay="300">
                    <div class="feature-icon"><i class="fas fa-car"></i></div>
                    <h3>تنوع هائل</h3>
                    <p>آلاف السيارات من جميع الماركات والفئات</p>
                </div>
            </div>
        </div>
    </section>

    <section class="cars-section" id="cars">
        <div class="container">
            <div class="section-title" data-aos="fade-up">
                <h2>المزادات الحية</h2>
                <p>اكتشف أحدث السيارات المعروضة في المزادات الحية الآن</p>
            </div>
            
            <?php if (empty($auctions)): ?>
                <p style="text-align: center; color: white; font-size: 1.2rem;">لا توجد مزادات نشطة حالياً</p>
            <?php else: ?>
            <div class="cars-grid">
                <?php foreach ($auctions as $auction): ?>
                <div class="car-card" data-aos="fade-up">
                    <div class="car-image">
                        <?php if ($auction['main_image']): ?>
                            <img src="<?php echo htmlspecialchars($auction['main_image']); ?>" alt="">
                        <?php else: ?>
                            <i class="fas fa-car"></i>
                        <?php endif; ?>
                        <div class="car-badge">مزاد حي</div>
                        <div class="countdown" data-endtime="<?php echo strtotime($auction['end_time']); ?>">جاري الحساب...</div>
                    </div>
                    <div class="car-content">
                        <h3 class="car-title"><?php echo htmlspecialchars($auction['brand'] . ' ' . $auction['model'] . ' ' . $auction['year']); ?></h3>
                        <div class="car-specs">
                            <span><i class="fas fa-calendar"></i> <?php echo $auction['year']; ?></span>
                            <span><i class="fas fa-road"></i> <?php echo number_format($auction['mileage']); ?> كم</span>
                            <span><i class="fas fa-cog"></i> <?php echo $auction['transmission'] == 'automatic' ? 'أوتوماتيك' : 'مانيوال'; ?></span>
                        </div>
                        <div class="car-price"><?php echo number_format($auction['current_price'], 2); ?> $</div>
                        <div class="car-actions">
                            <a href="auction-details.php?id=<?php echo $auction['id']; ?>" class="btn-small btn-primary">شارك في المزايدة</a>
                            <a href="auction-details.php?id=<?php echo $auction['id']; ?>" class="btn-small btn-secondary">عرض التفاصيل</a>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </div>
    </section>

    <section class="stats">
        <div class="container">
            <div class="stats-grid">
                <div class="stat-item">
                    <span class="stat-number"><?php echo number_format($total_users); ?></span>
                    <span class="stat-label">عميل مسجل</span>
                </div>
                <div class="stat-item">
                    <span class="stat-number"><?php echo number_format($total_completed); ?></span>
                    <span class="stat-label">مزاد مكتمل</span>
                </div>
                <div class="stat-item">
                    <span class="stat-number"><?php echo number_format($total_active); ?></span>
                    <span class="stat-label">مزاد نشط</span>
                </div>
                <div class="stat-item">
                    <span class="stat-number"><?php echo number_format($total_sellers); ?></span>
                    <span class="stat-label">بائع نشط</span>
                </div>
            </div>
        </div>
    </section>

    <footer class="footer">
        <div class="container">
            <div class="footer-content">
                <div class="footer-section">
                    <h3>كار أوكشن</h3>
                    <p>منصة مزادات السيارات الرائدة في المنطقة</p>
                    <div class="social-links">
                        <a href="#"><i class="fab fa-facebook-f"></i></a>
                        <a href="#"><i class="fab fa-twitter"></i></a>
                        <a href="#"><i class="fab fa-instagram"></i></a>
                    </div>
                </div>
                
                <div class="footer-section">
                    <h3>روابط سريعة</h3>
                    <a href="auctions.php">المزادات</a>
                    <a href="store.php">المتجر</a>
                    <a href="dashboard.php">لوحة التحكم</a>
                </div>
                
                <div class="footer-section">
                    <h3>اتصل بنا</h3>
                    <p><i class="fas fa-envelope"></i> info@carauction.com</p>
                    <p><i class="fas fa-phone"></i> +966 50 123 4567</p>
                </div>
            </div>
            
            <div class="footer-bottom">
                <p>&copy; 2025 كار أوكشن. جميع الحقوق محفوظة.</p>
            </div>
        </div>
    </footer>

    <div class="scroll-top" id="scrollTop">
        <i class="fas fa-arrow-up"></i>
    </div>

    <script src="https://unpkg.com/aos@2.3.1/dist/aos.js"></script>
    <script>
        AOS.init({ duration: 1000, once: true });

        // Header scroll
        window.addEventListener('scroll', () => {
            document.getElementById('header').classList.toggle('header-scrolled', window.scrollY > 100);
            document.getElementById('scrollTop').classList.toggle('visible', window.scrollY > 300);
        });

        document.getElementById('scrollTop').addEventListener('click', () => {
            window.scrollTo({ top: 0, behavior: 'smooth' });
        });

        // Countdown timers
        function updateCountdowns() {
            document.querySelectorAll('.countdown').forEach(el => {
                const endTime = parseInt(el.dataset.endtime) * 1000;
                const now = Date.now();
                const remaining = endTime - now;

                if (remaining <= 0) {
                    el.textContent = 'انتهى المزاد';
                    el.style.background = 'rgba(255, 0, 0, 0.8)';
                } else {
                    const days = Math.floor(remaining / 86400000);
                    const hours = Math.floor((remaining % 86400000) / 3600000);
                    const mins = Math.floor((remaining % 3600000) / 60000);
                    el.textContent = `${days}ي ${hours}س ${mins}د`;
                }
            });
        }

        setInterval(updateCountdowns, 1000);
        updateCountdowns();
    </script>
</body>
</html>
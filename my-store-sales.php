<?php
require_once 'config/database.php';
require_once 'includes/functions.php';
require_once 'includes/ai-chatbot.php';

if (!isLoggedIn()) {
    redirect('auth/login.php');
}

$user_id = $_SESSION['user_id'];

// جلب مبيعات المستخدم
$stmt = $conn->prepare("
    SELECT
        o.*,
        p.title as product_title,
        p.id as product_id,
        buyer.username as buyer_name,
        buyer.id as buyer_id,
        (SELECT image_path FROM store_product_images WHERE product_id = p.id AND is_primary = TRUE LIMIT 1) as product_image
    FROM store_orders o
    JOIN store_products p ON o.product_id = p.id
    JOIN users buyer ON o.buyer_id = buyer.id
    WHERE o.seller_id = ?
    ORDER BY o.created_at DESC
");
$stmt->execute([$user_id]);
$sales = $stmt->fetchAll();

getChatbotWidget();
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <title>💰 مبيعاتي - المتجر</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@200;300;400;500;600;700;800;900&family=Tajawal:wght@300;400;500;700;900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/aos/2.3.4/aos.css">

    <style>
        :root {
            --royal-gradient: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            --emerald-gradient: linear-gradient(135deg, #13f1fc 0%, #0470dc 100%);
            --fire-gradient: linear-gradient(135deg, #f9d423 0%, #ff4e50 100%);
            --glass-white: rgba(255, 255, 255, 0.1);
            --glass-border: rgba(255, 255, 255, 0.18);
            --transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
        }

        * { margin: 0; padding: 0; box-sizing: border-box; }

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

        .logo-text {
            font-size: 1.8rem;
            font-weight: 900;
            background: linear-gradient(135deg, #fff, #e0e7ff, #c7d2fe);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }

        .luxury-nav {
            display: flex;
            gap: 10px;
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

        .sale-card {
            background: var(--glass-white);
            backdrop-filter: blur(20px);
            border: 1px solid var(--glass-border);
            border-radius: 25px;
            padding: 25px;
            margin-bottom: 20px;
            display: grid;
            grid-template-columns: 120px 1fr auto;
            gap: 20px;
            transition: var(--transition);
            position: relative;
            overflow: hidden;
        }

        .sale-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.1), transparent);
            transition: left 0.8s;
        }

        .sale-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 15px 50px rgba(0, 0, 0, 0.3);
        }

        .sale-card:hover::before {
            left: 100%;
        }

        .product-img {
            width: 120px;
            height: 120px;
            border-radius: 15px;
            overflow: hidden;
            background: var(--emerald-gradient);
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .product-img img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .sale-info h3 {
            color: white;
            font-size: 1.3rem;
            margin-bottom: 10px;
        }

        .sale-meta {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 15px;
            margin: 15px 0;
        }

        .meta-item {
            background: rgba(255, 255, 255, 0.05);
            padding: 10px;
            border-radius: 12px;
            border: 1px solid rgba(255, 255, 255, 0.1);
        }

        .meta-label {
            font-size: 0.75rem;
            color: rgba(255, 255, 255, 0.6);
            margin-bottom: 5px;
        }

        .meta-value {
            font-weight: 600;
            color: white;
        }

        .btn-premium {
            padding: 12px 25px;
            background: var(--emerald-gradient);
            color: white;
            text-decoration: none;
            border-radius: 15px;
            font-weight: 700;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            transition: var(--transition);
            box-shadow: 0 10px 25px rgba(19, 241, 252, 0.3);
        }

        .btn-premium:hover {
            transform: translateY(-3px);
            box-shadow: 0 15px 35px rgba(19, 241, 252, 0.4);
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
        }

        @media (max-width: 768px) {
            .sale-card {
                grid-template-columns: 1fr;
            }

            .sale-meta {
                grid-template-columns: repeat(2, 1fr);
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
                <div class="logo-text">Bidora</div>
                <nav class="luxury-nav">
                    <a href="dashboard.php" class="nav-item">
                        <i class="fas fa-home"></i>
                        <span>الرئيسية</span>
                    </a>
                    <a href="store.php" class="nav-item">
                        <i class="fas fa-store"></i>
                        <span>المتجر</span>
                    </a>
                    <a href="wallet.php" class="nav-item">
                        <i class="fas fa-wallet"></i>
                        <span>المحفظة</span>
                    </a>
                </nav>
            </div>
        </div>
    </header>

    <main class="main-content">
        <div class="container">
            <div class="page-banner" data-aos="fade-up">
                <h1 class="page-title">💰 مبيعاتي من المتجر</h1>
                <p class="page-subtitle">إدارة مبيعاتك وأرباحك من المنتجات</p>
            </div>

            <?php if (empty($sales)): ?>
                <div class="empty-state" data-aos="fade-up">
                    <i class="fas fa-box-open"></i>
                    <h3>لا توجد مبيعات</h3>
                </div>
            <?php else: ?>
                <?php foreach ($sales as $sale): ?>
                    <div class="sale-card" data-aos="fade-up">
                        <div class="product-img">
                            <?php if ($sale['product_image']): ?>
                                <img src="<?php echo htmlspecialchars($sale['product_image']); ?>" alt="">
                            <?php else: ?>
                                <i class="fas fa-shopping-bag" style="font-size: 2rem; color: white;"></i>
                            <?php endif; ?>
                        </div>
                        <div class="sale-info">
                            <h3><?php echo htmlspecialchars($sale['product_title']); ?></h3>
                            <div class="sale-meta">
                                <div class="meta-item">
                                    <div class="meta-label">المشتري</div>
                                    <div class="meta-value"><?php echo htmlspecialchars($sale['buyer_name']); ?></div>
                                </div>
                                <div class="meta-item">
                                    <div class="meta-label">السعر</div>
                                    <div class="meta-value" style="color: #10b981;"><?php echo number_format($sale['price'], 2); ?>$</div>
                                </div>
                                <div class="meta-item">
                                    <div class="meta-label">العمولة</div>
                                    <div class="meta-value" style="color: #dc2626;">-<?php echo number_format($sale['commission_amount'], 2); ?>$</div>
                                </div>
                                <div class="meta-item">
                                    <div class="meta-label">صافي الربح</div>
                                    <div class="meta-value" style="color: #13f1fc;"><?php echo number_format($sale['seller_net_amount'], 2); ?>$</div>
                                </div>
                            </div>
                        </div>
                        <div>
                            <a href="store-order-chat.php?order_id=<?php echo $sale['id']; ?>" class="btn-premium">
                                <i class="fas fa-comments"></i>
                                المحادثة
                            </a>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </main>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/aos/2.3.4/aos.js"></script>
    <script>
        AOS.init({ duration: 1200, once: false, offset: 100 });

        function createParticles() {
            const container = document.getElementById('particles');
            for (let i = 0; i < 40; i++) {
                const particle = document.createElement('div');
                particle.className = 'particle';
                particle.style.left = Math.random() * 100 + '%';
                particle.style.animationDelay = Math.random() * 25 + 's';
                particle.style.animationDuration = (20 + Math.random() * 10) + 's';
                container.appendChild(particle);
            }
        }

        document.addEventListener('DOMContentLoaded', createParticles);
    </script>
</body>
</html>

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

// التحقق من تأكيد البريد الإلكتروني
if (!$user['email_verified']) {
    redirect('verify-pending.php');
}

// عدد الإشعارات غير المقروءة
$stmt = $conn->prepare("SELECT COUNT(*) as unread FROM notifications WHERE user_id = ? AND is_read = 0");
$stmt->execute([$user_id]);
$unread_count = $stmt->fetch()['unread'];

// جلب معلومات المحفظة
$stmt = $conn->prepare("SELECT * FROM wallet WHERE user_id = ?");
$stmt->execute([$user_id]);
$wallet = $stmt->fetch();

// إحصائيات المستخدم
$stmt = $conn->prepare("SELECT COUNT(*) as count FROM auctions WHERE seller_id = ? AND status = 'active'");
$stmt->execute([$user_id]);
$active_auctions = $stmt->fetch()['count'];

$stmt = $conn->prepare("SELECT COUNT(*) as count FROM bids WHERE user_id = ? AND status = 'active'");
$stmt->execute([$user_id]);
$active_bids = $stmt->fetch()['count'];

$stmt = $conn->prepare("SELECT AVG(rating) as avg, COUNT(*) as count FROM ratings WHERE to_user_id = ? AND rating IS NOT NULL");
$stmt->execute([$user_id]);
$rating_data = $stmt->fetch();

// جلب عدد المشتريات والمبيعات من المتجر
$stmt = $conn->prepare("SELECT COUNT(*) as total FROM store_orders WHERE buyer_id = ?");
$stmt->execute([$user_id]);
$store_purchases = $stmt->fetch()['total'];

$stmt = $conn->prepare("SELECT COUNT(*) as total FROM store_orders WHERE seller_id = ?");
$stmt->execute([$user_id]);
$store_sales = $stmt->fetch()['total'];

// عدد المبيعات المعلقة
$stmt = $conn->prepare("SELECT COUNT(*) as count FROM sales_confirmations WHERE seller_id = ? AND status = 'pending'");
$stmt->execute([$user_id]);
$pending_sales = $stmt->fetch()['count'];

// جلب المحادثات
$stmt = $conn->prepare("
    SELECT 
        COUNT(DISTINCT ch.id) as total_chats,
        SUM(CASE WHEN m.is_read = FALSE AND m.sender_id != ? THEN 1 ELSE 0 END) as unread_messages
    FROM sale_chats ch
    LEFT JOIN sale_messages m ON ch.id = m.chat_id
    WHERE ch.seller_id = ? OR ch.buyer_id = ?
");
$stmt->execute([$user_id, $user_id, $user_id]);
$chat_stats = $stmt->fetch();

// التسليمات المعلقة
$stmt = $conn->prepare("
    SELECT 
        sc.*,
        a.id as auction_id,
        v.brand, v.model, v.year
    FROM sales_confirmations sc
    JOIN auctions a ON sc.auction_id = a.id
    JOIN vehicles v ON a.vehicle_id = v.id
    WHERE sc.buyer_id = ? 
    AND sc.status = 'pending' 
    AND sc.seller_shipped = TRUE 
    AND sc.buyer_confirmed = FALSE
");
$stmt->execute([$user_id]);
$pending_deliveries = $stmt->fetchAll();

getChatbotWidget();
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>🌟 الداشبورد الفاخر - مزادات السيارات الحصرية</title>
    
    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@200;300;400;500;600;700;800;900&family=Tajawal:wght@300;400;500;700;900&display=swap" rel="stylesheet">
    
    <!-- Icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.2/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css">
    
    <!-- AOS Animation -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/aos/2.3.4/aos.css">
    
    <!-- Swiper -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/swiper@11/swiper-bundle.min.css">
    
    <style>
        :root {
            /* Gradient Palettes */
            --royal-gradient: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            --sunset-gradient: linear-gradient(135deg, #fa709a 0%, #fee140 100%);
            --ocean-gradient: linear-gradient(135deg, #89f7fe 0%, #66a6ff 100%);
            --emerald-gradient: linear-gradient(135deg, #13f1fc 0%, #0470dc 100%);
            --fire-gradient: linear-gradient(135deg, #f9d423 0%, #ff4e50 100%);
            --mystic-gradient: linear-gradient(135deg, #ec77ab 0%, #7873f5 100%);
            --cosmic-gradient: linear-gradient(135deg, #7F00FF 0%, #E100FF 100%);
            --aurora-gradient: linear-gradient(135deg, #00c9ff 0%, #92fe9d 100%);
            
            /* Glass Effects */
            --glass-white: rgba(255, 255, 255, 0.1);
            --glass-border: rgba(255, 255, 255, 0.18);
            --glass-shadow: 0 8px 32px 0 rgba(31, 38, 135, 0.37);
            
            /* Colors */
            --primary: #667eea;
            --secondary: #764ba2;
            --success: #13f1fc;
            --warning: #f9d423;
            --danger: #ff4e50;
            --info: #00c9ff;
            --dark: #0f0c29;
            --light: #f7fafc;
            
            /* Neon Colors */
            --neon-pink: #ff006e;
            --neon-blue: #00d4ff;
            --neon-green: #39ff14;
            --neon-purple: #bc13fe;
            
            /* Shadows */
            --shadow-sm: 0 2px 20px rgba(0, 0, 0, 0.1);
            --shadow-md: 0 10px 40px rgba(0, 0, 0, 0.15);
            --shadow-lg: 0 20px 60px rgba(0, 0, 0, 0.2);
            --shadow-xl: 0 40px 80px rgba(0, 0, 0, 0.25);
            --shadow-neon: 0 0 50px rgba(102, 126, 234, 0.5);
            
            /* Animations */
            --transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
            --bounce: cubic-bezier(0.68, -0.55, 0.265, 1.55);
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
        
        /*   خلفية ديناميكية متحركة */
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
        
        /* 🌌 شبكة ثلاثية الأبعاد */
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
        
        /* 🎆 جسيمات متوهجة */
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
            width: 6px;
            height: 6px;
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
        
        /* 🌟 نجوم متلألئة */
        .stars {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            z-index: -1;
        }
        
        .star {
            position: absolute;
            width: 2px;
            height: 2px;
            background: white;
            border-radius: 50%;
            animation: twinkle 5s infinite;
        }
        
        @keyframes twinkle {
            0%, 100% { opacity: 0; transform: scale(0.5); }
            50% { opacity: 1; transform: scale(1.5); }
        }
        
        /* 🎭 Header الفاخر */
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
        
        .header-glow {
            position: absolute;
            top: 0;
            left: 50%;
            transform: translateX(-50%);
            width: 80%;
            height: 100%;
            background: radial-gradient(ellipse at center, rgba(102, 126, 234, 0.3) 0%, transparent 70%);
            filter: blur(40px);
            pointer-events: none;
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
        
        /* 🎨 شعار متحرك */
        .animated-logo {
            display: flex;
            align-items: center;
            gap: 15px;
            position: relative;
        }
        
        .logo-icon {
            width: 55px;
            height: 55px;
            position: relative;
            animation: logoFloat 3s ease-in-out infinite;
        }
        
        @keyframes logoFloat {
            0%, 100% { transform: translateY(0) rotate(0deg); }
            50% { transform: translateY(-5px) rotate(5deg); }
        }
        
        .logo-icon img {
            width: 100%;
            height: 100%;
            border-radius: 15px;
            box-shadow: 0 10px 30px rgba(102, 126, 234, 0.4);
        }
        
        .logo-text {
            font-size: 1.8rem;
            font-weight: 900;
            background: linear-gradient(135deg, #fff, #e0e7ff, #c7d2fe);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            animation: logoShimmer 3s ease-in-out infinite;
            text-shadow: 0 0 30px rgba(102, 126, 234, 0.5);
        }
        
        @keyframes logoShimmer {
            0%, 100% { filter: brightness(1); }
            50% { filter: brightness(1.2); }
        }
        
        /* 🎭 معلومات المستخدم الفاخرة */
        .user-section {
            display: flex;
            align-items: center;
            gap: 20px;
        }
        
        .user-profile {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 10px 20px;
            background: var(--glass-white);
            backdrop-filter: blur(10px);
            border: 1px solid var(--glass-border);
            border-radius: 50px;
            position: relative;
            overflow: hidden;
        }
        
        .user-profile::before {
            content: '';
            position: absolute;
            top: -2px;
            left: -2px;
            right: -2px;
            bottom: -2px;
            background: var(--royal-gradient);
            border-radius: 50px;
            opacity: 0;
            transition: opacity 0.3s;
            z-index: -1;
        }
        
        .user-profile:hover::before {
            opacity: 1;
        }
        
        .user-avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: var(--royal-gradient);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.3rem;
            color: white;
            box-shadow: 0 0 20px rgba(102, 126, 234, 0.5);
            animation: avatarPulse 2s ease-in-out infinite;
        }
        
        @keyframes avatarPulse {
            0%, 100% { box-shadow: 0 0 20px rgba(102, 126, 234, 0.5); }
            50% { box-shadow: 0 0 30px rgba(102, 126, 234, 0.8); }
        }
        
        .user-name {
            color: white;
            font-weight: 700;
            font-size: 1.05rem;
        }
        
        /* 🏆 شارات التوثيق */
        .premium-badge {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            padding: 5px 12px;
            background: linear-gradient(135deg, #ffd700, #ffed4e);
            color: #7c3f00;
            border-radius: 20px;
            font-size: 0.75rem;
            font-weight: 800;
            box-shadow: 0 0 15px rgba(255, 215, 0, 0.5);
            animation: badgeGlow 2s ease-in-out infinite;
        }
        
        @keyframes badgeGlow {
            0%, 100% { 
                box-shadow: 0 0 15px rgba(255, 215, 0, 0.5);
                transform: scale(1);
            }
            50% { 
                box-shadow: 0 0 25px rgba(255, 215, 0, 0.8);
                transform: scale(1.05);
            }
        }
        
        .verified-badge-premium {
            background: var(--emerald-gradient);
            color: white;
            animation: verifiedPulse 3s ease-in-out infinite;
        }
        
        @keyframes verifiedPulse {
            0%, 100% { 
                box-shadow: 0 0 15px rgba(19, 241, 252, 0.5);
            }
            50% { 
                box-shadow: 0 0 25px rgba(19, 241, 252, 0.8);
            }
        }
        
        /* 🎯 قائمة التنقل الفاخرة */
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
        
        .nav-item.active {
            background: var(--royal-gradient);
            color: white;
            border: none;
            box-shadow: 0 10px 30px rgba(102, 126, 234, 0.4);
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
        
        /* 🔔 إشعارات متحركة */
        .notification-btn {
            position: relative;
        }
        
        .notification-dot {
            position: absolute;
            top: -5px;
            right: -5px;
            min-width: 22px;
            height: 22px;
            background: var(--fire-gradient);
            color: white;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 0.7rem;
            font-weight: 800;
            box-shadow: 0 0 10px rgba(255, 78, 80, 0.5);
            animation: notificationPulse 1s ease-in-out infinite;
        }
        
        @keyframes notificationPulse {
            0%, 100% {
                transform: scale(1);
                box-shadow: 0 0 10px rgba(255, 78, 80, 0.5);
            }
            50% {
                transform: scale(1.1);
                box-shadow: 0 0 20px rgba(255, 78, 80, 0.8);
            }
        }
        
        .logout-btn {
            background: linear-gradient(135deg, rgba(239, 68, 68, 0.2), rgba(220, 38, 38, 0.2));
            border: 1px solid rgba(239, 68, 68, 0.3);
        }
        
        .logout-btn:hover {
            background: var(--fire-gradient);
            border: none;
        }
        
        /* 📊 المحتوى الرئيسي */
        .main-content {
            padding: 40px 0;
            position: relative;
        }
        
        /* 🎉 بانر الترحيب الفاخر */
        .welcome-banner-premium {
            background: var(--glass-white);
            backdrop-filter: blur(30px);
            -webkit-backdrop-filter: blur(30px);
            border: 1px solid var(--glass-border);
            border-radius: 30px;
            padding: 50px;
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
        
        .welcome-bg-animation {
            position: absolute;
            top: -50%;
            left: -50%;
            width: 200%;
            height: 200%;
            background: radial-gradient(circle at center, rgba(102, 126, 234, 0.1) 0%, transparent 50%);
            animation: welcomeRotate 30s linear infinite;
        }
        
        @keyframes welcomeRotate {
            from { transform: rotate(0deg) scale(1); }
            to { transform: rotate(360deg) scale(1.1); }
        }
        
        .welcome-content-premium {
            position: relative;
            z-index: 1;
        }
        
        .welcome-title {
            font-size: 3rem;
            font-weight: 900;
            margin-bottom: 15px;
            background: linear-gradient(135deg, #667eea, #764ba2, #f093fb, #f5576c);
            background-size: 300% 300%;
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            animation: welcomeGradient 5s ease infinite;
            line-height: 1.2;
        }
        
        @keyframes welcomeGradient {
            0% { background-position: 0% 50%; }
            50% { background-position: 100% 50%; }
            100% { background-position: 0% 50%; }
        }
        
        .welcome-subtitle {
            color: rgba(255, 255, 255, 0.8);
            font-size: 1.3rem;
            font-weight: 500;
            margin-bottom: 25px;
        }
        
        .welcome-stats {
            display: flex;
            gap: 30px;
            flex-wrap: wrap;
        }
        
        .welcome-stat {
            display: flex;
            align-items: center;
            gap: 12px;
        }
        
        .welcome-stat-icon {
            width: 45px;
            height: 45px;
            border-radius: 12px;
            background: var(--royal-gradient);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 1.3rem;
            box-shadow: 0 5px 15px rgba(102, 126, 234, 0.3);
        }
        
        .welcome-stat-text {
            color: rgba(255, 255, 255, 0.9);
        }
        
        .welcome-stat-value {
            font-size: 1.5rem;
            font-weight: 800;
            color: white;
        }
        
        .welcome-stat-label {
            font-size: 0.9rem;
            color: rgba(255, 255, 255, 0.6);
        }
        
        /* 🚨 التنبيهات الفاخرة */
        .premium-alert {
            background: var(--glass-white);
            backdrop-filter: blur(20px);
            border: 2px solid;
            border-radius: 25px;
            padding: 35px;
            margin-bottom: 35px;
            position: relative;
            overflow: hidden;
            animation: alertSlide 0.8s ease-out;
        }
        
        @keyframes alertSlide {
            from {
                opacity: 0;
                transform: translateX(50px);
            }
            to {
                opacity: 1;
                transform: translateX(0);
            }
        }
        
        .premium-alert.warning {
            border-color: rgba(249, 212, 35, 0.5);
            background: linear-gradient(135deg, rgba(249, 212, 35, 0.1), rgba(255, 78, 80, 0.1));
        }
        
        .premium-alert.danger {
            border-color: rgba(239, 68, 68, 0.5);
            background: linear-gradient(135deg, rgba(239, 68, 68, 0.1), rgba(220, 38, 38, 0.1));
        }
        
        .alert-content {
            display: flex;
            gap: 25px;
            align-items: center;
        }
        
        .alert-icon-3d {
            width: 80px;
            height: 80px;
            border-radius: 20px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 2.5rem;
            color: white;
            position: relative;
            animation: iconFloat 3s ease-in-out infinite;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.2);
        }
        
        .alert-icon-3d.warning {
            background: var(--fire-gradient);
        }
        
        .alert-icon-3d.danger {
            background: linear-gradient(135deg, #ef4444, #dc2626);
        }
        
        @keyframes iconFloat {
            0%, 100% { 
                transform: translateY(0) rotateZ(0deg);
            }
            25% { 
                transform: translateY(-5px) rotateZ(-5deg);
            }
            75% { 
                transform: translateY(5px) rotateZ(5deg);
            }
        }
        
        .alert-details h3 {
            color: white;
            font-size: 1.6rem;
            font-weight: 800;
            margin-bottom: 10px;
        }
        
        .alert-details p {
            color: rgba(255, 255, 255, 0.8);
            font-size: 1.05rem;
            line-height: 1.6;
        }
        
        .alert-actions {
            display: flex;
            gap: 15px;
            margin-top: 20px;
        }
        
        .alert-action-btn {
            padding: 14px 28px;
            border-radius: 15px;
            font-weight: 700;
            font-size: 1rem;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 10px;
            transition: var(--transition);
            position: relative;
            overflow: hidden;
        }
        
        .alert-action-btn::before {
            content: '';
            position: absolute;
            top: 50%;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.3), transparent);
            transform: translateY(-50%);
            transition: left 0.6s;
        }
        
        .alert-action-btn:hover::before {
            left: 100%;
        }
        
        .alert-action-btn.primary {
            background: var(--fire-gradient);
            color: white;
            box-shadow: 0 10px 30px rgba(249, 212, 35, 0.3);
        }
        
        .alert-action-btn.primary:hover {
            transform: translateY(-3px);
            box-shadow: 0 15px 40px rgba(249, 212, 35, 0.4);
        }
        
        .alert-action-btn.secondary {
            background: var(--glass-white);
            color: white;
            border: 2px solid rgba(255, 255, 255, 0.3);
        }
        
        .alert-action-btn.secondary:hover {
            background: rgba(255, 255, 255, 0.2);
            transform: translateY(-3px);
        }
        
        /* 📈 شبكة الإحصائيات الفاخرة */
        .stats-grid-premium {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 25px;
            margin-bottom: 40px;
        }
        
        .stat-card-3d {
            background: var(--glass-white);
            backdrop-filter: blur(20px);
            -webkit-backdrop-filter: blur(20px);
            border: 1px solid var(--glass-border);
            border-radius: 25px;
            padding: 30px;
            position: relative;
            overflow: hidden;
            transition: var(--transition);
            animation: cardFadeIn 0.8s ease-out backwards;
            transform-style: preserve-3d;
            transform: perspective(1000px);
        }
        
        .stat-card-3d:nth-child(1) { animation-delay: 0.1s; }
        .stat-card-3d:nth-child(2) { animation-delay: 0.2s; }
        .stat-card-3d:nth-child(3) { animation-delay: 0.3s; }
        .stat-card-3d:nth-child(4) { animation-delay: 0.4s; }
        .stat-card-3d:nth-child(5) { animation-delay: 0.5s; }
        .stat-card-3d:nth-child(6) { animation-delay: 0.6s; }
        .stat-card-3d:nth-child(7) { animation-delay: 0.7s; }
        .stat-card-3d:nth-child(8) { animation-delay: 0.8s; }
        
        @keyframes cardFadeIn {
            from {
                opacity: 0;
                transform: perspective(1000px) rotateY(-30deg) translateY(30px);
            }
            to {
                opacity: 1;
                transform: perspective(1000px) rotateY(0) translateY(0);
            }
        }
        
        .stat-card-3d:hover {
            transform: perspective(1000px) rotateY(5deg) translateY(-10px) scale(1.05);
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
        
        .stat-card-3d::after {
            content: '';
            position: absolute;
            top: -50%;
            left: -50%;
            width: 200%;
            height: 200%;
            background: radial-gradient(circle, var(--glow-color) 0%, transparent 70%);
            opacity: 0;
            transition: opacity 0.3s;
            pointer-events: none;
        }
        
        .stat-card-3d:hover::after {
            opacity: 0.1;
        }
        
        .stat-header-premium {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 20px;
        }
        
        .stat-icon-3d {
            width: 70px;
            height: 70px;
            border-radius: 20px;
            display: flex;
            align-items: center;
            justify-content: center;
            position: relative;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.2);
            animation: iconRotate 10s linear infinite;
        }
        
        @keyframes iconRotate {
            from { transform: rotateY(0deg); }
            to { transform: rotateY(360deg); }
        }
        
        .stat-icon-3d img {
            width: 40px;
            height: 40px;
            filter: drop-shadow(0 0 10px rgba(255, 255, 255, 0.5));
        }
        
        .stat-value-premium {
            font-size: 2.5rem;
            font-weight: 900;
            color: white;
            margin-bottom: 5px;
            text-shadow: 0 0 20px rgba(255, 255, 255, 0.3);
            display: flex;
            align-items: baseline;
            gap: 8px;
        }
        
        .stat-value-premium .currency {
            font-size: 1.5rem;
            color: rgba(255, 255, 255, 0.7);
        }
        
        .stat-label-premium {
            color: rgba(255, 255, 255, 0.7);
            font-size: 1rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 1px;
        }
        
        .stat-trend {
            display: flex;
            align-items: center;
            gap: 8px;
            margin-top: 15px;
            padding-top: 15px;
            border-top: 1px solid rgba(255, 255, 255, 0.1);
        }
        
        .trend-icon {
            width: 35px;
            height: 35px;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.2rem;
        }
        
        .trend-up {
            background: linear-gradient(135deg, rgba(16, 185, 129, 0.2), rgba(5, 150, 105, 0.2));
            color: #10b981;
        }
        
        .trend-down {
            background: linear-gradient(135deg, rgba(239, 68, 68, 0.2), rgba(220, 38, 38, 0.2));
            color: #ef4444;
        }
        
        .trend-text {
            color: rgba(255, 255, 255, 0.8);
            font-size: 0.95rem;
            font-weight: 600;
        }
        
        .stat-footer-action {
            margin-top: 20px;
            display: block;
            text-align: center;
            padding: 12px;
            background: rgba(255, 255, 255, 0.1);
            border-radius: 12px;
            color: white;
            text-decoration: none;
            font-weight: 600;
            transition: var(--transition);
        }
        
        .stat-footer-action:hover {
            background: var(--gradient);
            transform: translateY(-2px);
            box-shadow: 0 10px 20px rgba(0, 0, 0, 0.2);
        }
        
        /* 🎯 الإجراءات السريعة الفاخرة */
        .quick-actions-premium {
            background: var(--glass-white);
            backdrop-filter: blur(20px);
            border: 1px solid var(--glass-border);
            border-radius: 30px;
            padding: 40px;
            margin-bottom: 40px;
            position: relative;
            overflow: hidden;
        }
        
        .section-header-premium {
            display: flex;
            align-items: center;
            gap: 15px;
            margin-bottom: 35px;
            position: relative;
        }
        
        .section-icon {
            width: 60px;
            height: 60px;
            border-radius: 18px;
            background: var(--royal-gradient);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 2rem;
            color: white;
            box-shadow: 0 10px 30px rgba(102, 126, 234, 0.3);
            animation: sectionIconPulse 3s ease-in-out infinite;
        }
        
        @keyframes sectionIconPulse {
            0%, 100% { 
                transform: scale(1) rotate(0deg);
            }
            50% { 
                transform: scale(1.05) rotate(5deg);
            }
        }
        
        .section-title {
            font-size: 2rem;
            font-weight: 800;
            color: white;
            text-shadow: 0 0 20px rgba(255, 255, 255, 0.2);
        }
        
        .actions-grid-premium {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
        }
        
        .action-card-3d {
            display: flex;
            align-items: center;
            gap: 18px;
            padding: 25px;
            background: var(--glass-white);
            backdrop-filter: blur(10px);
            border: 1px solid var(--glass-border);
            border-radius: 20px;
            text-decoration: none;
            transition: var(--transition);
            position: relative;
            overflow: hidden;
            animation: actionCardSlide 0.8s ease-out backwards;
        }
        
        .action-card-3d:nth-child(odd) {
            animation-name: actionCardSlideLeft;
        }
        
        .action-card-3d:nth-child(even) {
            animation-name: actionCardSlideRight;
        }
        
        @keyframes actionCardSlideLeft {
            from {
                opacity: 0;
                transform: translateX(-30px);
            }
            to {
                opacity: 1;
                transform: translateX(0);
            }
        }
        
        @keyframes actionCardSlideRight {
            from {
                opacity: 0;
                transform: translateX(30px);
            }
            to {
                opacity: 1;
                transform: translateX(0);
            }
        }
        
        .action-card-3d::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.2), transparent);
            transition: left 0.8s;
        }
        
        .action-card-3d:hover {
            transform: translateX(-5px) translateY(-5px);
            box-shadow: 0 15px 40px rgba(0, 0, 0, 0.2);
            background: var(--gradient);
        }
        
        .action-card-3d:hover::before {
            left: 100%;
        }
        
        .action-icon-3d {
            width: 65px;
            height: 65px;
            border-radius: 18px;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
            position: relative;
            box-shadow: 0 8px 20px rgba(0, 0, 0, 0.15);
        }
        
        .action-icon-3d img {
            width: 35px;
            height: 35px;
            filter: drop-shadow(0 0 5px rgba(255, 255, 255, 0.5));
        }
        
        .action-content h4 {
            color: white;
            font-size: 1.15rem;
            font-weight: 700;
            margin-bottom: 5px;
        }
        
        .action-content p {
            color: rgba(255, 255, 255, 0.7);
            font-size: 0.9rem;
        }
        
        /* 🎪 تسليمات معلقة */
        .deliveries-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(350px, 1fr));
            gap: 20px;
            margin-top: 25px;
        }
        
        .delivery-card-premium {
            background: var(--glass-white);
            backdrop-filter: blur(15px);
            border: 2px solid rgba(249, 212, 35, 0.3);
            border-radius: 20px;
            padding: 25px;
            position: relative;
            overflow: hidden;
            animation: deliveryBounce 0.8s var(--bounce) backwards;
        }
        
        @keyframes deliveryBounce {
            from {
                opacity: 0;
                transform: scale(0.8) translateY(20px);
            }
            to {
                opacity: 1;
                transform: scale(1) translateY(0);
            }
        }
        
        .delivery-card-premium::before {
            content: '';
            position: absolute;
            top: -2px;
            left: -2px;
            right: -2px;
            bottom: -2px;
            background: var(--fire-gradient);
            border-radius: 20px;
            opacity: 0.5;
            z-index: -1;
            animation: deliveryGlow 2s ease-in-out infinite;
        }
        
        @keyframes deliveryGlow {
            0%, 100% { opacity: 0.3; }
            50% { opacity: 0.6; }
        }
        
        .delivery-header {
            display: flex;
            align-items: center;
            gap: 15px;
            margin-bottom: 20px;
        }
        
        .delivery-icon {
            width: 50px;
            height: 50px;
            border-radius: 15px;
            background: var(--fire-gradient);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 1.5rem;
            box-shadow: 0 5px 15px rgba(249, 212, 35, 0.3);
        }
        
        .delivery-title {
            color: white;
            font-size: 1.2rem;
            font-weight: 700;
        }
        
        .delivery-details {
            color: rgba(255, 255, 255, 0.8);
            margin-bottom: 20px;
            line-height: 1.6;
        }
        
        .delivery-amount {
            font-size: 1.8rem;
            font-weight: 900;
            color: #f9d423;
            text-shadow: 0 0 20px rgba(249, 212, 35, 0.5);
            margin-bottom: 20px;
        }
        
        .delivery-btn {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            width: 100%;
            padding: 15px;
            background: var(--fire-gradient);
            color: white;
            text-decoration: none;
            border-radius: 15px;
            font-weight: 700;
            font-size: 1.05rem;
            transition: var(--transition);
            box-shadow: 0 10px 25px rgba(249, 212, 35, 0.3);
        }
        
        .delivery-btn:hover {
            transform: translateY(-3px);
            box-shadow: 0 15px 35px rgba(249, 212, 35, 0.4);
        }
        
        /* 📱 استجابة الجوال */
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
            
            .welcome-title {
                font-size: 2rem;
            }
            
            .stats-grid-premium {
                grid-template-columns: 1fr;
            }
            
            .actions-grid-premium {
                grid-template-columns: 1fr;
            }
            
            .deliveries-grid {
                grid-template-columns: 1fr;
            }
        }
        
        /* 🎆 تأثيرات إضافية */
        .shimmer-effect {
            position: relative;
            overflow: hidden;
        }
        
        .shimmer-effect::after {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.4), transparent);
            animation: shimmer 3s infinite;
        }
        
        @keyframes shimmer {
            0% { left: -100%; }
            100% { left: 100%; }
        }
        
        /* Loading Spinner */
        .luxury-spinner {
            width: 60px;
            height: 60px;
            border: 3px solid rgba(255, 255, 255, 0.1);
            border-top-color: var(--primary);
            border-radius: 50%;
            animation: luxurySpin 1s linear infinite;
        }
        
        @keyframes luxurySpin {
            to { transform: rotate(360deg); }
        }
    </style>
</head>
<body>
    <!-- الخلفية الديناميكية -->
    <div class="dynamic-background"></div>
    <div class="grid-3d"></div>
    
    <!-- الجسيمات المتوهجة -->
    <div class="glowing-particles" id="particles"></div>
    
    <!-- النجوم المتلألئة -->
    <div class="stars" id="stars"></div>
    
    <!-- Header الفاخر -->
    <header class="luxury-header">
        <div class="header-glow"></div>
        <div class="container">
            <div class="header-content">
                <!-- الشعار المتحرك -->
                <div class="animated-logo">
                    <div class="logo-icon">
                        <img src="https://img.icons8.com/fluency/96/tesla-model-x.png" alt="Logo">
                    </div>
                    <div class="logo-text">Bidora</div>
                </div>
                
                <!-- معلومات المستخدم -->
                <div class="user-section">
                    <div class="user-profile">
                        <div class="user-avatar">
                            <i class="fas fa-user"></i>
                        </div>
                        <div class="user-name"><?php echo htmlspecialchars($user['username']); ?></div>
                        <?php if ($user['kyc_status'] == 'verified'): ?>
                            <span class="premium-badge verified-badge-premium">
                                <i class="fas fa-check-circle"></i>
                                موثق
                            </span>
                        <?php else: ?>
                            <span class="premium-badge">
                                <i class="fas fa-exclamation-circle"></i>
                                غير موثق
                            </span>
                        <?php endif; ?>
                    </div>
                </div>
                
                <!-- قائمة التنقل الفاخرة -->
                <nav class="luxury-nav">
                    <a href="index.php" class="nav-item">
                        <i class="fas fa-home"></i>
                        <span>الرئيسية</span>
                    </a>
                    <a href="auctions.php" class="nav-item">
                        <i class="fas fa-gavel"></i>
                        <span>المزادات</span>
                    </a>
                    <a href="store.php" class="nav-item">
                        <i class="fas fa-store"></i>
                        <span>المتجر</span>
                    </a>
                    <a href="wallet.php" class="nav-item">
                        <i class="fas fa-wallet"></i>
                        <span>المحفظة</span>
                    </a>
                    <a href="notifications.php" class="nav-item notification-btn">
                        <i class="fas fa-bell"></i>
                        <span>الإشعارات</span>
                        <?php if ($unread_count > 0): ?>
                            <span class="notification-dot"><?php echo $unread_count; ?></span>
                        <?php endif; ?>
                    </a>
                    <a href="profile.php" class="nav-item">
                        <i class="fas fa-user-cog"></i>
                        <span>الملف</span>
                    </a>
                    <a href="auth/logout.php" class="nav-item logout-btn">
                        <i class="fas fa-power-off"></i>
                        <span>خروج</span>
                    </a>
                </nav>
            </div>
        </div>
    </header>
    
    <!-- المحتوى الرئيسي -->
    <main class="main-content">
        <div class="container">
            <!-- بانر الترحيب الفاخر -->
            <div class="welcome-banner-premium" data-aos="fade-up">
                <div class="welcome-bg-animation"></div>
                <div class="welcome-content-premium">
                    <h1 class="welcome-title">
                        مرحباً بك في عالم الرفاهية، <?php echo htmlspecialchars($user['username']); ?>!  
                    </h1>
                    <p class="welcome-subtitle">
                        استمتع بتجربة مزادات فريدة من نوعها مع أفخم السيارات وأفضل الخدمات
                    </p>
                    <div class="welcome-stats">
                        <div class="welcome-stat">
                            <div class="welcome-stat-icon">
                                <i class="fas fa-trophy"></i>
                            </div>
                            <div class="welcome-stat-text">
                                <div class="welcome-stat-value"><?php echo $active_auctions; ?></div>
                                <div class="welcome-stat-label">مزاد نشط</div>
                            </div>
                        </div>
                        <div class="welcome-stat">
                            <div class="welcome-stat-icon">
                                <i class="fas fa-star"></i>
                            </div>
                            <div class="welcome-stat-text">
                                <div class="welcome-stat-value">
                                    <?php echo $rating_data['count'] > 0 ? number_format($rating_data['avg'], 1) : '5.0'; ?>
                                </div>
                                <div class="welcome-stat-label">تقييمك</div>
                            </div>
                        </div>
                        <div class="welcome-stat">
                            <div class="welcome-stat-icon">
                                <i class="fas fa-coins"></i>
                            </div>
                            <div class="welcome-stat-text">
                                <div class="welcome-stat-value">$<?php echo number_format($wallet['available_balance'], 0); ?></div>
                                <div class="welcome-stat-label">رصيدك</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- التسليمات المعلقة -->
            <?php if (!empty($pending_deliveries)): ?>
                <div class="premium-alert warning" data-aos="slide-right">
                    <div class="alert-content">
                        <div class="alert-icon-3d warning">
                            <i class="fas fa-truck"></i>
                        </div>
                        <div class="alert-details">
                            <h3>🚚 لديك تسليمات تحتاج لتأكيد!</h3>
                            <p>هناك <?php echo count($pending_deliveries); ?> سيارة تم شحنها وبانتظار تأكيد الاستلام منك</p>
                        </div>
                    </div>
                    <div class="deliveries-grid">
                        <?php foreach ($pending_deliveries as $delivery): ?>
                            <div class="delivery-card-premium">
                                <div class="delivery-header">
                                    <div class="delivery-icon">
                                        <i class="fas fa-car"></i>
                                    </div>
                                    <div class="delivery-title">
                                        <?php echo htmlspecialchars($delivery['brand'] . ' ' . $delivery['model'] . ' ' . $delivery['year']); ?>
                                    </div>
                                </div>
                                <div class="delivery-details">
                                    <i class="fas fa-info-circle"></i>
                                    السيارة في الطريق إليك، يرجى تأكيد الاستلام عند وصولها
                                </div>
                                <div class="delivery-amount">
                                    $<?php echo number_format($delivery['sale_amount'], 2); ?>
                                </div>
                                <a href="confirm-delivery.php?id=<?php echo $delivery['auction_id']; ?>" class="delivery-btn">
                                    <i class="fas fa-check-double"></i>
                                    تأكيد الاستلام الآن
                                </a>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endif; ?>
            
            <!-- تنبيه التوثيق -->
            <?php if ($user['kyc_status'] != 'verified'): ?>
                <div class="premium-alert danger" data-aos="slide-left">
                    <div class="alert-content">
                        <div class="alert-icon-3d danger">
                            <i class="fas fa-shield-alt"></i>
                        </div>
                        <div class="alert-details">
                            <h3>🔐 حسابك يحتاج للتوثيق</h3>
                            <p>
                                <?php if ($user['kyc_status'] == 'pending'): ?>
                                    طلب التوثيق الخاص بك قيد المراجعة حالياً
                                <?php elseif ($user['kyc_status'] == 'rejected'): ?>
                                    للأسف تم رفض طلب التوثيق، يمكنك إعادة المحاولة
                                <?php else: ?>
                                    وثق حسابك الآن للاستمتاع بجميع المزايا الحصرية
                                <?php endif; ?>
                            </p>
                            <div class="alert-actions">
                                <a href="kyc/submit.php" class="alert-action-btn primary">
                                    <i class="fas fa-<?php echo $user['kyc_status'] == 'pending' ? 'search' : 'id-card'; ?>"></i>
                                    <?php echo $user['kyc_status'] == 'pending' ? 'متابعة الطلب' : 'ابدأ التوثيق'; ?>
                                </a>
                                <a href="#" class="alert-action-btn secondary">
                                    <i class="fas fa-question-circle"></i>
                                    المساعدة
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
            
            <!-- شبكة الإحصائيات الفاخرة -->
            <div class="stats-grid-premium">
                <!-- رصيد المحفظة -->
                <div class="stat-card-3d" style="--gradient: var(--ocean-gradient); --glow-color: rgba(137, 247, 254, 0.3);" data-aos="zoom-in">
                    <div class="stat-header-premium">
                        <div>
                            <div class="stat-value-premium">
                                <span class="currency">$</span>
                                <span><?php echo number_format($wallet['available_balance'], 2); ?></span>
                            </div>
                            <div class="stat-label-premium">الرصيد المتاح</div>
                        </div>
                        <div class="stat-icon-3d" style="background: var(--ocean-gradient);">
                            <img src="https://img.icons8.com/fluency/96/wallet.png" alt="Wallet">
                        </div>
                    </div>
                    <div class="stat-trend">
                        <div class="trend-icon trend-up">
                            <i class="fas fa-arrow-up"></i>
                        </div>
                        <div class="trend-text">+15% هذا الشهر</div>
                    </div>
                    <a href="wallet.php" class="stat-footer-action shimmer-effect">
                        شحن الرصيد <i class="fas fa-plus-circle"></i>
                    </a>
                </div>
                
                <!-- الرصيد المحجوز -->
                <div class="stat-card-3d" style="--gradient: var(--emerald-gradient); --glow-color: rgba(19, 241, 252, 0.3);" data-aos="zoom-in">
                    <div class="stat-header-premium">
                        <div>
                            <div class="stat-value-premium">
                                <span class="currency">$</span>
                                <span><?php echo number_format($wallet['frozen_balance'], 2); ?></span>
                            </div>
                            <div class="stat-label-premium">رصيد محجوز</div>
                        </div>
                        <div class="stat-icon-3d" style="background: var(--emerald-gradient);">
                            <img src="https://img.icons8.com/fluency/96/lock.png" alt="Frozen">
                        </div>
                    </div>
                    <div class="stat-trend">
                        <div class="trend-icon trend-up">
                            <i class="fas fa-shield-alt"></i>
                        </div>
                        <div class="trend-text">في مزايدات نشطة</div>
                    </div>
                </div>
                
                <!-- المبيعات المعلقة -->
                <div class="stat-card-3d" style="--gradient: var(--mystic-gradient); --glow-color: rgba(236, 119, 171, 0.3);" data-aos="zoom-in">
                    <div class="stat-header-premium">
                        <div>
                            <div class="stat-value-premium">
                                <span class="currency">$</span>
                                <span><?php echo number_format($wallet['sales_hold_balance'], 2); ?></span>
                            </div>
                            <div class="stat-label-premium">مبيعات معلقة</div>
                        </div>
                        <div class="stat-icon-3d" style="background: var(--mystic-gradient);">
                            <img src="https://img.icons8.com/fluency/96/hourglass.png" alt="Hold">
                        </div>
                    </div>
                    <div class="stat-trend">
                        <?php if ($pending_sales > 0): ?>
                            <div class="trend-icon" style="background: rgba(249, 212, 35, 0.2); color: #f9d423;">
                                <i class="fas fa-clock"></i>
                            </div>
                            <div class="trend-text"><?php echo $pending_sales; ?> عملية معلقة</div>
                        <?php else: ?>
                            <div class="trend-icon trend-up">
                                <i class="fas fa-check"></i>
                            </div>
                            <div class="trend-text">لا توجد معلقات</div>
                        <?php endif; ?>
                    </div>
                </div>
                
                <!-- المزادات النشطة -->
                <div class="stat-card-3d" style="--gradient: var(--fire-gradient); --glow-color: rgba(249, 212, 35, 0.3);" data-aos="zoom-in">
                    <div class="stat-header-premium">
                        <div>
                            <div class="stat-value-premium">
                                <?php echo $active_auctions; ?>
                            </div>
                            <div class="stat-label-premium">مزاداتي النشطة</div>
                        </div>
                        <div class="stat-icon-3d" style="background: var(--fire-gradient);">
                            <img src="https://img.icons8.com/fluency/96/auction.png" alt="Auctions">
                        </div>
                    </div>
                    <a href="my-auctions.php" class="stat-footer-action shimmer-effect">
                        عرض المزادات <i class="fas fa-arrow-left"></i>
                    </a>
                </div>
                
                <!-- المشتريات -->
                <div class="stat-card-3d" style="--gradient: var(--aurora-gradient); --glow-color: rgba(0, 201, 255, 0.3);" data-aos="zoom-in">
                    <div class="stat-header-premium">
                        <div>
                            <div class="stat-value-premium">
                                <?php echo $store_purchases; ?>
                            </div>
                            <div class="stat-label-premium">مشترياتي</div>
                        </div>
                        <div class="stat-icon-3d" style="background: var(--aurora-gradient);">
                            <img src="https://img.icons8.com/fluency/96/shopping-bag.png" alt="Purchases">
                        </div>
                    </div>
                    <a href="my-purchases.php" class="stat-footer-action shimmer-effect">
                        عرض المشتريات <i class="fas fa-shopping-cart"></i>
                    </a>
                </div>
                
                <!-- مبيعات المتجر -->
                <div class="stat-card-3d" style="--gradient: var(--sunset-gradient); --glow-color: rgba(250, 112, 154, 0.3);" data-aos="zoom-in">
                    <div class="stat-header-premium">
                        <div>
                            <div class="stat-value-premium">
                                <?php echo $store_sales; ?>
                            </div>
                            <div class="stat-label-premium">مبيعات المتجر</div>
                        </div>
                        <div class="stat-icon-3d" style="background: var(--sunset-gradient);">
                            <img src="https://img.icons8.com/fluency/96/cash.png" alt="Sales">
                        </div>
                    </div>
                    <a href="my-store-sales.php" class="stat-footer-action shimmer-effect">
                        إدارة المبيعات <i class="fas fa-chart-line"></i>
                    </a>
                </div>
                
                <!-- المحادثات -->
                <div class="stat-card-3d" style="--gradient: var(--cosmic-gradient); --glow-color: rgba(127, 0, 255, 0.3);" data-aos="zoom-in">
                    <div class="stat-header-premium">
                        <div>
                            <div class="stat-value-premium">
                                <?php echo $chat_stats['total_chats'] ?? 0; ?>
                            </div>
                            <div class="stat-label-premium">محادثاتي</div>
                        </div>
                        <div class="stat-icon-3d" style="background: var(--cosmic-gradient);">
                            <img src="https://img.icons8.com/fluency/96/chat.png" alt="Chats">
                        </div>
                    </div>
                    <div class="stat-trend">
                        <?php if ($chat_stats['unread_messages'] > 0): ?>
                            <div class="trend-icon" style="background: rgba(239, 68, 68, 0.2); color: #ef4444;">
                                <i class="fas fa-envelope"></i>
                            </div>
                            <div class="trend-text"><?php echo $chat_stats['unread_messages']; ?> رسالة جديدة</div>
                        <?php else: ?>
                            <div class="trend-icon trend-up">
                                <i class="fas fa-comment"></i>
                            </div>
                            <div class="trend-text">لا توجد رسائل جديدة</div>
                        <?php endif; ?>
                    </div>
                    <a href="my-chats.php" class="stat-footer-action shimmer-effect">
                        فتح المحادثات <i class="fas fa-comments"></i>
                    </a>
                </div>
                
                <!-- التقييم -->
                <div class="stat-card-3d" style="--gradient: linear-gradient(135deg, #ffd700, #ffed4e); --glow-color: rgba(255, 215, 0, 0.3);" data-aos="zoom-in">
                    <div class="stat-header-premium">
                        <div>
                            <div class="stat-value-premium">
                                <?php echo $rating_data['count'] > 0 ? number_format($rating_data['avg'], 1) : '5.0'; ?>
                                <i class="fas fa-star" style="color: #ffd700; font-size: 1.5rem;"></i>
                            </div>
                            <div class="stat-label-premium">تقييمك</div>
                        </div>
                        <div class="stat-icon-3d" style="background: linear-gradient(135deg, #ffd700, #ffed4e);">
                            <img src="https://img.icons8.com/fluency/96/star.png" alt="Rating">
                        </div>
                    </div>
                    <div class="stat-trend">
                        <div class="trend-text">
                            <?php echo $rating_data['count'] > 0 ? $rating_data['count'] . ' تقييم' : 'لم يتم التقييم بعد'; ?>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- الإجراءات السريعة الفاخرة -->
            <div class="quick-actions-premium" data-aos="fade-up">
                <div class="section-header-premium">
                    <div class="section-icon">
                        <i class="fas fa-rocket"></i>
                    </div>
                    <h2 class="section-title">إجراءات سريعة</h2>
                </div>
                
                <div class="actions-grid-premium">
                    <a href="auctions.php" class="action-card-3d" style="--gradient: var(--ocean-gradient);">
                        <div class="action-icon-3d" style="background: var(--ocean-gradient);">
                            <img src="https://img.icons8.com/fluency/96/search.png" alt="Browse">
                        </div>
                        <div class="action-content">
                            <h4>تصفح المزادات</h4>
                            <p>اكتشف أحدث العروض</p>
                        </div>
                    </a>
                    
                    <a href="add-vehicle.php" class="action-card-3d" style="--gradient: var(--emerald-gradient);">
                        <div class="action-icon-3d" style="background: var(--emerald-gradient);">
                            <img src="https://img.icons8.com/fluency/96/add.png" alt="Add">
                        </div>
                        <div class="action-content">
                            <h4>أضف سيارة</h4>
                            <p>ابدأ البيع الآن</p>
                        </div>
                    </a>
                    
                    <a href="my-bids.php" class="action-card-3d" style="--gradient: var(--fire-gradient);">
                        <div class="action-icon-3d" style="background: var(--fire-gradient);">
                            <img src="https://img.icons8.com/fluency/96/auction.png" alt="Bids">
                        </div>
                        <div class="action-content">
                            <h4>مزايداتي</h4>
                            <p>تابع عروضك</p>
                        </div>
                    </a>
                    
                    <a href="my-auctions.php" class="action-card-3d" style="--gradient: var(--mystic-gradient);">
                        <div class="action-icon-3d" style="background: var(--mystic-gradient);">
                            <img src="https://img.icons8.com/fluency/96/car-service.png" alt="Manage">
                        </div>
                        <div class="action-content">
                            <h4>إدارة مزاداتي</h4>
                            <p>سياراتك المعروضة</p>
                        </div>
                    </a>
                    
                    <a href="store.php" class="action-card-3d" style="--gradient: var(--aurora-gradient);">
                        <div class="action-icon-3d" style="background: var(--aurora-gradient);">
                            <img src="https://img.icons8.com/fluency/96/shop.png" alt="Store">
                        </div>
                        <div class="action-content">
                            <h4>المتجر الفوري</h4>
                            <p>تسوق الآن</p>
                        </div>
                    </a>
                    
                    <a href="wallet.php" class="action-card-3d" style="--gradient: var(--sunset-gradient);">
                        <div class="action-icon-3d" style="background: var(--sunset-gradient);">
                            <img src="https://img.icons8.com/fluency/96/money.png" alt="Wallet">
                        </div>
                        <div class="action-content">
                            <h4>إدارة المحفظة</h4>
                            <p>شحن وتحويل</p>
                        </div>
                    </a>
                    
                    <a href="my-addresses.php" class="action-card-3d" style="--gradient: var(--cosmic-gradient);">
                        <div class="action-icon-3d" style="background: var(--cosmic-gradient);">
                            <img src="https://img.icons8.com/fluency/96/address.png" alt="Addresses">
                        </div>
                        <div class="action-content">
                            <h4>عناويني</h4>
                            <p>إدارة التسليم</p>
                        </div>
                    </a>
                    
                    <a href="profile.php" class="action-card-3d" style="--gradient: linear-gradient(135deg, #667eea, #764ba2);">
                        <div class="action-icon-3d" style="background: linear-gradient(135deg, #667eea, #764ba2);">
                            <img src="https://img.icons8.com/fluency/96/user-settings.png" alt="Profile">
                        </div>
                        <div class="action-content">
                            <h4>الملف الشخصي</h4>
                            <p>إعدادات الحساب</p>
                        </div>
                    </a>
                </div>
            </div>
        </div>
    </main>
    
    <!-- Scripts -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/aos/2.3.4/aos.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/swiper@11/swiper-bundle.min.js"></script>
    
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
            const particleCount = 60;
            
            for (let i = 0; i < particleCount; i++) {
                const particle = document.createElement('div');
                particle.className = 'particle';
                particle.style.left = Math.random() * 100 + '%';
                particle.style.animationDelay = Math.random() * 25 + 's';
                particle.style.animationDuration = (20 + Math.random() * 10) + 's';
                container.appendChild(particle);
            }
        }
        
        // إنشاء النجوم المتلألئة
        function createStars() {
            const container = document.getElementById('stars');
            const starCount = 100;
            
            for (let i = 0; i < starCount; i++) {
                const star = document.createElement('div');
                star.className = 'star';
                star.style.left = Math.random() * 100 + '%';
                star.style.top = Math.random() * 100 + '%';
                star.style.animationDelay = Math.random() * 5 + 's';
                container.appendChild(star);
            }
        }
        
        // عداد الأرقام المتحرك
        function animateNumbers() {
            const numbers = document.querySelectorAll('.stat-value-premium');
            
            const observerOptions = {
                threshold: 0.5
            };
            
            const observer = new IntersectionObserver((entries) => {
                entries.forEach(entry => {
                    if (entry.isIntersecting) {
                        const element = entry.target;
                        const text = element.textContent;
                        const hasDecimal = text.includes('.');
                        const hasCurrency = text.includes('$');
                        
                        let targetValue = parseFloat(text.replace(/[$,]/g, ''));
                        if (isNaN(targetValue)) return;
                        
                        let currentValue = 0;
                        const increment = targetValue / 100;
                        const decimals = hasDecimal ? 2 : 0;
                        
                        const updateNumber = () => {
                            currentValue += increment;
                            if (currentValue < targetValue) {
                                let formattedValue = currentValue.toFixed(decimals);
                                if (!hasDecimal) formattedValue = Math.floor(currentValue).toString();
                                
                                if (hasCurrency) {
                                    element.innerHTML = `<span class="currency">$</span><span>${parseFloat(formattedValue).toLocaleString()}</span>`;
                                } else {
                                    element.textContent = parseFloat(formattedValue).toLocaleString();
                                }
                                requestAnimationFrame(updateNumber);
                            } else {
                                if (hasCurrency) {
                                    element.innerHTML = `<span class="currency">$</span><span>${targetValue.toLocaleString(undefined, {minimumFractionDigits: decimals, maximumFractionDigits: decimals})}</span>`;
                                } else {
                                    element.textContent = targetValue.toLocaleString(undefined, {minimumFractionDigits: decimals, maximumFractionDigits: decimals});
                                }
                            }
                        };
                        
                        updateNumber();
                        observer.unobserve(element);
                    }
                });
            }, observerOptions);
            
            numbers.forEach(number => observer.observe(number));
        }
        
        // تفعيل كل شيء عند تحميل الصفحة
        document.addEventListener('DOMContentLoaded', function() {
            createParticles();
            createStars();
            animateNumbers();
            
            // إضافة تأثير parallax للماوس
            document.addEventListener('mousemove', (e) => {
                const x = e.clientX / window.innerWidth;
                const y = e.clientY / window.innerHeight;
                
                document.querySelectorAll('.stat-card-3d').forEach(card => {
                    const speed = 5;
                    const xOffset = (x - 0.5) * speed;
                    const yOffset = (y - 0.5) * speed;
                    
                    card.style.transform = `perspective(1000px) rotateY(${xOffset}deg) rotateX(${-yOffset}deg) translateY(-10px) scale(1.05)`;
                });
            });
        });
    </script>
</body>
</html>
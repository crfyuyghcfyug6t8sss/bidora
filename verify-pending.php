<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once 'config/database.php';
require_once 'includes/functions.php';

// التحقق من تسجيل الدخول
if (!isLoggedIn()) {
    redirect('auth/login.php');
}

$user_id = $_SESSION['user_id'];

// جلب معلومات المستخدم
$stmt = $conn->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

// إذا كان البريد محققاً بالفعل، إعادة توجيه للـ Dashboard
if ($user && $user['email_verified']) {
    redirect('dashboard.php');
}

// التحقق من وجود توكن فعّال
$stmt = $conn->prepare("
    SELECT token, expires_at, created_at 
    FROM email_verifications 
    WHERE user_id = ? AND verified_at IS NULL 
    ORDER BY created_at DESC 
    LIMIT 1
");
$stmt->execute([$user_id]);
$verification = $stmt->fetch(PDO::FETCH_ASSOC);

$token_expired = false;
if ($verification) {
    $expires = new DateTime($verification['expires_at']);
    $now = new DateTime();
    if ($now > $expires) {
        $token_expired = true;
    }
}
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>تحقق من بريدك الإلكتروني - مزادات السيارات</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Cairo:wght@400;600;700&display=swap');
        
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Cairo', sans-serif;
            background: #0f172a;
            min-height: 100vh;
            display: grid;
            place-items: center;
            padding: 20px;
            direction: rtl;
            overflow-x: hidden;
        }
        
        /* الجزيئات المتحركة */
        .particles {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            overflow: hidden;
            pointer-events: none;
            z-index: 0;
        }
        
        .particle {
            position: absolute;
            width: 3px;
            height: 3px;
            background: rgba(99, 102, 241, 0.5);
            border-radius: 50%;
            animation: float linear infinite;
        }
        
        @keyframes float {
            0% {
                transform: translateY(100vh) rotate(0deg);
                opacity: 0;
            }
            10% {
                opacity: 1;
            }
            90% {
                opacity: 1;
            }
            100% {
                transform: translateY(-100vh) rotate(360deg);
                opacity: 0;
            }
        }
        
        .container {
            position: relative;
            z-index: 1;
            width: 100%;
            max-width: 600px;
        }
        
        .card {
            background: rgba(30, 41, 59, 0.95);
            backdrop-filter: blur(20px);
            border: 1px solid rgba(148, 163, 184, 0.1);
            border-radius: 24px;
            padding: 50px 40px;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.5);
            animation: slideUp 0.6s cubic-bezier(0.16, 1, 0.3, 1);
        }
        
        @keyframes slideUp {
            from {
                opacity: 0;
                transform: translateY(40px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        /* الأيقونة */
        .icon-wrapper {
            text-align: center;
            margin-bottom: 30px;
            position: relative;
        }
        
        .icon-circle {
            width: 120px;
            height: 120px;
            background: linear-gradient(135deg, #6366f1 0%, #8b5cf6 100%);
            border-radius: 50%;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            box-shadow: 0 15px 40px rgba(99, 102, 241, 0.4);
            animation: pulse 2s ease-in-out infinite;
            position: relative;
        }
        
        .icon-circle::before {
            content: '';
            position: absolute;
            top: -10px;
            left: -10px;
            right: -10px;
            bottom: -10px;
            border: 3px dashed rgba(99, 102, 241, 0.3);
            border-radius: 50%;
            animation: rotate 20s linear infinite;
        }
        
        @keyframes pulse {
            0%, 100% {
                transform: scale(1);
                box-shadow: 0 15px 40px rgba(99, 102, 241, 0.4);
            }
            50% {
                transform: scale(1.05);
                box-shadow: 0 20px 50px rgba(99, 102, 241, 0.6);
            }
        }
        
        @keyframes rotate {
            from { transform: rotate(0deg); }
            to { transform: rotate(360deg); }
        }
        
        .icon-circle i {
            font-size: 3.5rem;
            color: white;
            z-index: 1;
        }
        
        /* العنوان */
        .header {
            text-align: center;
            margin-bottom: 30px;
        }
        
        .header h1 {
            font-size: 2rem;
            font-weight: 700;
            color: #f1f5f9;
            margin-bottom: 10px;
        }
        
        .header p {
            color: #94a3b8;
            font-size: 1rem;
            line-height: 1.6;
        }
        
        /* صندوق البريد */
        .email-box {
            background: rgba(15, 23, 42, 0.5);
            border: 2px solid rgba(99, 102, 241, 0.3);
            border-radius: 15px;
            padding: 20px;
            margin: 25px 0;
            text-align: center;
        }
        
        .email-label {
            color: #cbd5e1;
            font-size: 0.9rem;
            margin-bottom: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
        }
        
        .email-address {
            color: #6366f1;
            font-size: 1.1rem;
            font-weight: 700;
            word-break: break-all;
        }
        
        /* صندوق المعلومات */
        .info-box {
            background: rgba(245, 158, 11, 0.15);
            border: 2px solid rgba(245, 158, 11, 0.3);
            border-radius: 15px;
            padding: 20px;
            margin: 25px 0;
        }
        
        .info-box-header {
            display: flex;
            align-items: center;
            gap: 12px;
            margin-bottom: 10px;
        }
        
        .info-box-header i {
            font-size: 1.5rem;
            color: #fbbf24;
        }
        
        .info-box-header strong {
            color: #fbbf24;
            font-size: 1.1rem;
        }
        
        .info-box p {
            color: #cbd5e1;
            line-height: 1.8;
            margin: 0;
        }
        
        /* صندوق انتهاء الصلاحية */
        .expired-box {
            background: rgba(239, 68, 68, 0.15);
            border: 2px solid rgba(239, 68, 68, 0.3);
            border-radius: 15px;
            padding: 20px;
            margin: 25px 0;
        }
        
        .expired-box-header {
            display: flex;
            align-items: center;
            gap: 12px;
            margin-bottom: 10px;
        }
        
        .expired-box-header i {
            font-size: 1.5rem;
            color: #ef4444;
        }
        
        .expired-box-header strong {
            color: #fca5a5;
            font-size: 1.1rem;
        }
        
        .expired-box p {
            color: #fca5a5;
            line-height: 1.8;
            margin: 0;
        }
        
        /* الخطوات */
        .steps {
            margin: 30px 0;
            background: rgba(15, 23, 42, 0.5);
            border-radius: 15px;
            padding: 25px;
            border: 1px solid rgba(148, 163, 184, 0.1);
        }
        
        .steps h3 {
            color: #f1f5f9;
            margin-bottom: 20px;
            font-size: 1.2rem;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .steps h3 i {
            color: #6366f1;
        }
        
        .step {
            display: flex;
            align-items: start;
            gap: 15px;
            margin-bottom: 15px;
            padding: 15px;
            background: rgba(30, 41, 59, 0.5);
            border-radius: 12px;
            border: 1px solid rgba(148, 163, 184, 0.1);
            transition: all 0.3s;
        }
        
        .step:hover {
            background: rgba(30, 41, 59, 0.8);
            transform: translateX(-5px);
        }
        
        .step:last-child {
            margin-bottom: 0;
        }
        
        .step-number {
            width: 35px;
            height: 35px;
            background: linear-gradient(135deg, #6366f1 0%, #8b5cf6 100%);
            color: white;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 700;
            flex-shrink: 0;
            font-size: 1.1rem;
        }
        
        .step-text {
            color: #cbd5e1;
            line-height: 1.6;
            flex: 1;
        }
        
        /* الأزرار */
        .actions {
            display: flex;
            flex-direction: column;
            gap: 12px;
            margin-top: 30px;
        }
        
        .btn {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            padding: 16px 30px;
            border-radius: 12px;
            text-decoration: none;
            font-weight: 600;
            font-size: 1.05rem;
            transition: all 0.3s;
            border: none;
            cursor: pointer;
            font-family: 'Cairo', sans-serif;
        }
        
        .btn-primary {
            background: linear-gradient(135deg, #6366f1 0%, #8b5cf6 100%);
            color: white;
            box-shadow: 0 8px 20px rgba(99, 102, 241, 0.3);
        }
        
        .btn-primary:hover:not(:disabled) {
            transform: translateY(-3px);
            box-shadow: 0 12px 30px rgba(99, 102, 241, 0.4);
        }
        
        .btn-secondary {
            background: rgba(15, 23, 42, 0.5);
            color: #94a3b8;
            border: 2px solid rgba(148, 163, 184, 0.2);
        }
        
        .btn-secondary:hover {
            background: rgba(15, 23, 42, 0.8);
            border-color: rgba(148, 163, 184, 0.3);
            transform: translateY(-2px);
        }
        
        .btn-danger {
            background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%);
            color: white;
            box-shadow: 0 8px 20px rgba(239, 68, 68, 0.3);
        }
        
        .btn-danger:hover:not(:disabled) {
            transform: translateY(-3px);
            box-shadow: 0 12px 30px rgba(239, 68, 68, 0.4);
        }
        
        .btn:disabled {
            opacity: 0.6;
            cursor: not-allowed;
            transform: none !important;
        }
        
        .spinner {
            width: 20px;
            height: 20px;
            border: 3px solid rgba(255, 255, 255, 0.3);
            border-top-color: white;
            border-radius: 50%;
            animation: spin 0.8s linear infinite;
        }
        
        @keyframes spin {
            to { transform: rotate(360deg); }
        }
        
        /* التنبيهات */
        .alert {
            padding: 16px 20px;
            border-radius: 12px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 12px;
            animation: slideDown 0.5s ease-out;
        }
        
        @keyframes slideDown {
            from {
                opacity: 0;
                transform: translateY(-10px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        .alert-success {
            background: rgba(34, 197, 94, 0.15);
            border: 2px solid rgba(34, 197, 94, 0.3);
            color: #86efac;
        }
        
        .alert-error {
            background: rgba(239, 68, 68, 0.15);
            border: 2px solid rgba(239, 68, 68, 0.3);
            color: #fca5a5;
        }
        
        .alert i {
            font-size: 1.2rem;
            flex-shrink: 0;
        }
        
        /* التذييل */
        .footer {
            margin-top: 30px;
            padding-top: 25px;
            border-top: 2px solid rgba(148, 163, 184, 0.1);
            text-align: center;
        }
        
        .footer p {
            color: #94a3b8;
            font-size: 0.9rem;
            margin-bottom: 10px;
        }
        
        .footer a {
            color: #6366f1;
            text-decoration: none;
            font-weight: 600;
            transition: color 0.3s;
        }
        
        .footer a:hover {
            color: #8b5cf6;
        }
        
        /* الاستجابة للشاشات الصغيرة */
        @media (max-width: 768px) {
            .card {
                padding: 40px 25px;
            }
            
            .header h1 {
                font-size: 1.6rem;
            }
            
            .icon-circle {
                width: 100px;
                height: 100px;
            }
            
            .icon-circle i {
                font-size: 3rem;
            }
            
            .email-address {
                font-size: 1rem;
            }
            
            .steps {
                padding: 20px;
            }
            
            .step {
                padding: 12px;
            }
            
            .btn {
                padding: 14px 20px;
                font-size: 1rem;
            }
        }
        
        @media (max-width: 480px) {
            body {
                padding: 15px;
            }
            
            .card {
                padding: 30px 20px;
            }
            
            .header h1 {
                font-size: 1.4rem;
            }
            
            .header p {
                font-size: 0.9rem;
            }
            
            .icon-circle {
                width: 90px;
                height: 90px;
            }
            
            .icon-circle i {
                font-size: 2.5rem;
            }
            
            .email-address {
                font-size: 0.9rem;
            }
            
            .step {
                flex-direction: column;
                text-align: center;
            }
            
            .step-number {
                margin: 0 auto 10px;
            }
            
            .btn {
                padding: 12px 16px;
                font-size: 0.95rem;
            }
        }
    </style>
</head>
<body>
                        <div style="position: fixed; top: 20px; left: 20px; z-index: 1000;">
        <?php include 'includes/lang-switcher.php'; ?>
    </div>
    <script src="js/auto-translate.js"></script>

    <div class="particles" id="particles"></div>
    
    <div class="container">
        <div class="card">
            <!-- الأيقونة -->
            <div class="icon-wrapper">
                <div class="icon-circle">
                    <i class="fas fa-envelope-open-text"></i>
                </div>
            </div>
            
            <!-- العنوان -->
            <div class="header">
                <h1>تحقق من بريدك الإلكتروني</h1>
                <p>لقد أرسلنا رابط التحقق إلى بريدك الإلكتروني</p>
            </div>
            
            <!-- صندوق البريد -->
            <div class="email-box">
                <div class="email-label">
                    <i class="fas fa-envelope"></i>
                    البريد المرسل إليه
                </div>
                <div class="email-address"><?php echo htmlspecialchars($user['email']); ?></div>
            </div>
            
            <!-- حالة التوكن -->
            <?php if ($token_expired): ?>
                <div class="expired-box">
                    <div class="expired-box-header">
                        <i class="fas fa-exclamation-triangle"></i>
                        <strong>انتهت صلاحية الرابط!</strong>
                    </div>
                    <p>يرجى إعادة إرسال رابط تحقق جديد للمتابعة</p>
                </div>
            <?php else: ?>
                <div class="info-box">
                    <div class="info-box-header">
                        <i class="fas fa-clock"></i>
                        <strong>صلاحية الرابط: 24 ساعة</strong>
                    </div>
                    <p>يرجى فتح بريدك الإلكتروني والنقر على رابط التحقق خلال 24 ساعة من استلامه</p>
                </div>
            <?php endif; ?>
            
            <!-- الخطوات -->
            <div class="steps">
                <h3>
                    <i class="fas fa-list-check"></i>
                    خطوات التحقق
                </h3>
                
                <div class="step">
                    <div class="step-number">1</div>
                    <div class="step-text">افتح بريدك الإلكتروني (<?php echo htmlspecialchars($user['email']); ?>)</div>
                </div>
                
                <div class="step">
                    <div class="step-number">2</div>
                    <div class="step-text">ابحث عن رسالة من "مزادات السيارات" (تحقق من البريد المزعج إذا لم تجدها)</div>
                </div>
                
                <div class="step">
                    <div class="step-number">3</div>
                    <div class="step-text">اضغط على زر "تحقق من البريد الإلكتروني" في الرسالة</div>
                </div>
                
                <div class="step">
                    <div class="step-number">4</div>
                    <div class="step-text">سيتم تفعيل حسابك تلقائياً وتوجيهك للوحة التحكم</div>
                </div>
            </div>
            
            <!-- منطقة التنبيهات -->
            <div id="alertContainer"></div>
            
            <!-- الأزرار -->
            <div class="actions">
                <?php if ($token_expired): ?>
                    <button onclick="resendVerification()" class="btn btn-danger" id="resendBtn">
                        <i class="fas fa-redo"></i>
                        إعادة إرسال رابط التحقق
                    </button>
                <?php else: ?>
                    <button onclick="resendVerification()" class="btn btn-primary" id="resendBtn">
                        <i class="fas fa-paper-plane"></i>
                        لم تستلم البريد؟ إعادة الإرسال
                    </button>
                <?php endif; ?>
                
                <a href="dashboard.php" class="btn btn-secondary">
                    <i class="fas fa-home"></i>
                    الذهاب للوحة التحكم
                </a>
            </div>
            
            <!-- التذييل -->
            <div class="footer">
                <p>
                    <i class="fas fa-question-circle"></i>
                    هل تواجه مشكلة؟ 
                    <a href="mailto:support@bidora.de">تواصل مع الدعم</a>
                </p>
                <p>
                    <a href="auth/logout.php">
                        <i class="fas fa-sign-out-alt"></i>
                        تسجيل الخروج
                    </a>
                </p>
            </div>
        </div>
    </div>

    <script>
        // إنشاء الجزيئات المتحركة
        function createParticles() {
            const container = document.getElementById('particles');
            for (let i = 0; i < 50; i++) {
                const particle = document.createElement('div');
                particle.className = 'particle';
                particle.style.left = Math.random() * 100 + '%';
                particle.style.animationDuration = (Math.random() * 10 + 10) + 's';
                particle.style.animationDelay = Math.random() * 5 + 's';
                container.appendChild(particle);
            }
        }
        createParticles();
        
        // إعادة إرسال التحقق
        async function resendVerification() {
            const btn = document.getElementById('resendBtn');
            const alertContainer = document.getElementById('alertContainer');
            
            btn.disabled = true;
            const originalContent = btn.innerHTML;
            btn.innerHTML = '<div class="spinner"></div> جاري الإرسال...';
            alertContainer.innerHTML = '';
            
            try {
                const response = await fetch('auth/resend-verification.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    }
                });
                
                const data = await response.json();
                
                if (data.success) {
                    alertContainer.innerHTML = `
                        <div class="alert alert-success">
                            <i class="fas fa-check-circle"></i>
                            <div>${data.message}</div>
                        </div>
                    `;
                    
                    setTimeout(() => {
                        location.reload();
                    }, 3000);
                } else {
                    alertContainer.innerHTML = `
                        <div class="alert alert-error">
                            <i class="fas fa-exclamation-circle"></i>
                            <div>${data.message || 'حدث خطأ في إعادة الإرسال'}</div>
                        </div>
                    `;
                    
                    btn.disabled = false;
                    btn.innerHTML = originalContent;
                }
            } catch (error) {
                console.error('Error:', error);
                alertContainer.innerHTML = `
                    <div class="alert alert-error">
                        <i class="fas fa-exclamation-circle"></i>
                        <div>حدث خطأ في الاتصال. يرجى المحاولة لاحقاً</div>
                    </div>
                `;
                
                btn.disabled = false;
                btn.innerHTML = originalContent;
            }
        }
        
        // فحص حالة التحقق كل 5 ثوانٍ
        setInterval(async () => {
            try {
                const response = await fetch('auth/check-verification-status.php');
                const data = await response.json();
                
                if (data.verified) {
                    window.location.href = 'dashboard.php';
                }
            } catch (error) {
                console.error('Check status error:', error);
            }
        }, 5000);
    </script>
</body>
</html>
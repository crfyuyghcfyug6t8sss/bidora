<?php
require_once 'config/database.php';
require_once 'includes/functions.php';

if (!isLoggedIn()) {
    redirect('auth/login.php');
}

$user_id = $_SESSION['user_id'];
$success = '';
$errors = [];

// معالجة تغيير كلمة السر
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['change_password'])) {
    $current_password = clean($_POST['current_password']);
    $new_password = clean($_POST['new_password']);
    $confirm_password = clean($_POST['confirm_password']);
    
    if (empty($current_password)) {
        $errors[] = 'يرجى إدخال كلمة السر الحالية';
    }
    
    if (empty($new_password)) {
        $errors[] = 'يرجى إدخال كلمة السر الجديدة';
    } elseif (strlen($new_password) < 6) {
        $errors[] = 'كلمة السر يجب أن تكون 6 أحرف على الأقل';
    }
    
    if ($new_password !== $confirm_password) {
        $errors[] = 'كلمة السر الجديدة غير متطابقة';
    }
    
    if (empty($errors)) {
        // التحقق من كلمة السر الحالية
        $stmt = $conn->prepare("SELECT password FROM users WHERE id = ?");
        $stmt->execute([$user_id]);
        $user = $stmt->fetch();
        
        if (password_verify($current_password, $user['password'])) {
            // تحديث كلمة السر
            $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
            $stmt = $conn->prepare("UPDATE users SET password = ? WHERE id = ?");
            $stmt->execute([$hashed_password, $user_id]);
            
            $_SESSION['success'] = 'تم تغيير كلمة السر بنجاح!';
            header('Location: profile.php');
            exit;
        } else {
            $errors[] = 'كلمة السر الحالية غير صحيحة';
        }
    }
}

// عرض الرسائل
$success = isset($_SESSION['success']) ? $_SESSION['success'] : '';
unset($_SESSION['success']);

// جلب معلومات المستخدم
$stmt = $conn->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$user = $stmt->fetch();

// جلب معلومات المحفظة
$stmt = $conn->prepare("SELECT * FROM wallet WHERE user_id = ?");
$stmt->execute([$user_id]);
$wallet = $stmt->fetch();

// جلب معلومات KYC
$stmt = $conn->prepare("SELECT * FROM kyc_verifications WHERE user_id = ?");
$stmt->execute([$user_id]);
$kyc = $stmt->fetch();

// إحصائيات
$stmt = $conn->prepare("SELECT COUNT(*) as count FROM auctions WHERE seller_id = ?");
$stmt->execute([$user_id]);
$total_auctions = $stmt->fetch()['count'];

$stmt = $conn->prepare("SELECT COUNT(*) as count FROM bids WHERE user_id = ?");
$stmt->execute([$user_id]);
$total_bids = $stmt->fetch()['count'];

$stmt = $conn->prepare("SELECT AVG(rating) as avg, COUNT(*) as count FROM ratings WHERE to_user_id = ? AND rating IS NOT NULL");
$stmt->execute([$user_id]);
$rating_data = $stmt->fetch();
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>الملف الشخصي</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            direction: rtl;
            padding: 20px;
        }
        
        .container {
            max-width: 1200px;
            margin: 0 auto;
        }
        
        .back-link {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            color: white;
            text-decoration: none;
            margin-bottom: 20px;
            padding: 10px 20px;
            background: rgba(255, 255, 255, 0.2);
            backdrop-filter: blur(10px);
            border-radius: 10px;
            transition: 0.3s;
        }
        
        .back-link:hover {
            background: rgba(255, 255, 255, 0.3);
            transform: translateX(5px);
        }
        
        .profile-header {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            border-radius: 20px;
            padding: 40px;
            margin-bottom: 30px;
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.1);
            text-align: center;
        }
        
        .profile-avatar {
            width: 120px;
            height: 120px;
            border-radius: 50%;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 3rem;
            color: white;
            margin: 0 auto 20px;
            box-shadow: 0 8px 25px rgba(102, 126, 234, 0.4);
        }
        
        .profile-name {
            font-size: 2rem;
            color: #1e293b;
            margin-bottom: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
        }
        
        .verified-badge {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            background: linear-gradient(135deg, #10b981 0%, #059669 100%);
            color: white;
            padding: 6px 15px;
            border-radius: 20px;
            font-size: 0.9rem;
            font-weight: 600;
        }
        
        .profile-email {
            color: #64748b;
            font-size: 1.1rem;
        }
        
        .content-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 30px;
            margin-bottom: 30px;
        }
        
        .card {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            border-radius: 20px;
            padding: 30px;
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.1);
        }
        
        .card-header {
            display: flex;
            align-items: center;
            gap: 12px;
            margin-bottom: 25px;
            padding-bottom: 15px;
            border-bottom: 2px solid #e2e8f0;
        }
        
        .card-header h2 {
            color: #1e293b;
            font-size: 1.4rem;
        }
        
        .card-header i {
            font-size: 1.6rem;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }
        
        .info-grid {
            display: grid;
            gap: 20px;
        }
        
        .info-item {
            display: flex;
            flex-direction: column;
            gap: 8px;
        }
        
        .info-label {
            color: #64748b;
            font-size: 0.9rem;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .info-value {
            color: #1e293b;
            font-size: 1.1rem;
            font-weight: 600;
            padding: 12px 15px;
            background: #f8fafc;
            border-radius: 10px;
            border: 2px solid #e2e8f0;
        }
        
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 15px;
        }
        
        .stat-box {
            text-align: center;
            padding: 20px;
            background: #f8fafc;
            border-radius: 12px;
            border: 2px solid #e2e8f0;
        }
        
        .stat-value {
            font-size: 2rem;
            font-weight: bold;
            color: #1e293b;
            margin-bottom: 5px;
        }
        
        .stat-label {
            color: #64748b;
            font-size: 0.9rem;
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        .form-group label {
            display: block;
            color: #1e293b;
            font-weight: 600;
            margin-bottom: 8px;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .form-group input {
            width: 100%;
            padding: 12px 15px;
            border: 2px solid #e2e8f0;
            border-radius: 10px;
            font-size: 1rem;
            transition: 0.3s;
        }
        
        .form-group input:focus {
            outline: none;
            border-color: #667eea;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        }
        
        .btn {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            width: 100%;
            padding: 14px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            border-radius: 10px;
            font-size: 1.1rem;
            font-weight: 600;
            cursor: pointer;
            transition: 0.3s;
        }
        
        .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(102, 126, 234, 0.4);
        }
        
        .alert {
            padding: 15px 20px;
            border-radius: 10px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
            animation: slideDown 0.3s ease-out;
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
            background: #d1fae5;
            color: #065f46;
            border: 2px solid #10b981;
        }
        
        .alert-danger {
            background: #fee2e2;
            color: #991b1b;
            border: 2px solid #dc2626;
        }
        
        .badge {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 0.85rem;
            font-weight: 600;
        }
        
        .badge-success {
            background: #d1fae5;
            color: #065f46;
        }
        
        .badge-warning {
            background: #fef3c7;
            color: #92400e;
        }
        
        .badge-danger {
            background: #fee2e2;
            color: #991b1b;
        }
        
        .full-width {
            grid-column: 1 / -1;
        }
        
        @media (max-width: 768px) {
            .content-grid {
                grid-template-columns: 1fr;
            }
            
            .stats-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <a href="dashboard.php" class="back-link">
            <i class="fas fa-arrow-right"></i>
            العودة للوحة التحكم
        </a>
                            <div style="position: fixed; top: 20px; left: 20px; z-index: 1000;">
        <?php include 'includes/lang-switcher.php'; ?>
    </div>
    <script src="js/auto-translate.js"></script>

        <!-- Profile Header -->
        <div class="profile-header">
            <div class="profile-avatar">
                <i class="fas fa-user"></i>
            </div>
            <div class="profile-name">
                <?php echo htmlspecialchars($user['username']); ?>
                <?php if ($user['kyc_status'] == 'verified'): ?>
                    <span class="verified-badge">
                        <i class="fas fa-check-circle"></i>
                        موثق
                    </span>
                <?php endif; ?>
            </div>
            <div class="profile-email"><?php echo htmlspecialchars($user['email']); ?></div>
        </div>

        <?php if ($success): ?>
            <div class="alert alert-success">
                <i class="fas fa-check-circle"></i>
                <strong><?php echo $success; ?></strong>
            </div>
        <?php endif; ?>

        <?php if (!empty($errors)): ?>
            <div class="alert alert-danger">
                <i class="fas fa-exclamation-circle"></i>
                <div>
                    <?php foreach ($errors as $error): ?>
                        <div><?php echo $error; ?></div>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endif; ?>

        <div class="content-grid">
            <!-- Personal Information -->
            <div class="card">
                <div class="card-header">
                    <i class="fas fa-user-circle"></i>
                    <h2>المعلومات الشخصية</h2>
                </div>
                <div class="info-grid">
                    <div class="info-item">
                        <div class="info-label">
                            <i class="fas fa-user"></i>
                            اسم المستخدم
                        </div>
                        <div class="info-value"><?php echo htmlspecialchars($user['username']); ?></div>
                    </div>
                    
                    <div class="info-item">
                        <div class="info-label">
                            <i class="fas fa-envelope"></i>
                            البريد الإلكتروني
                        </div>
                        <div class="info-value"><?php echo htmlspecialchars($user['email']); ?></div>
                    </div>
                    
                    <div class="info-item">
                        <div class="info-label">
                            <i class="fas fa-phone"></i>
                            رقم الهاتف
                        </div>
                        <div class="info-value"><?php echo htmlspecialchars($user['phone']); ?></div>
                    </div>
                    
                    <div class="info-item">
                        <div class="info-label">
                            <i class="fas fa-calendar-alt"></i>
                            تاريخ التسجيل
                        </div>
                        <div class="info-value"><?php echo date('Y-m-d', strtotime($user['created_at'])); ?></div>
                    </div>
                </div>
            </div>

            <!-- Account Status -->
            <div class="card">
                <div class="card-header">
                    <i class="fas fa-shield-alt"></i>
                    <h2>حالة الحساب</h2>
                </div>
                <div class="info-grid">
                    <div class="info-item">
                        <div class="info-label">
                            <i class="fas fa-check-circle"></i>
                            حالة الحساب
                        </div>
                        <div class="info-value">
                            <?php if ($user['status'] == 'active'): ?>
                                <span class="badge badge-success">
                                    <i class="fas fa-check"></i>
                                    نشط
                                </span>
                            <?php elseif ($user['status'] == 'suspended'): ?>
                                <span class="badge badge-danger">
                                    <i class="fas fa-ban"></i>
                                    موقوف
                                </span>
                            <?php else: ?>
                                <span class="badge badge-warning">
                                    <i class="fas fa-clock"></i>
                                    معلق
                                </span>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <div class="info-item">
                        <div class="info-label">
                            <i class="fas fa-id-card"></i>
                            حالة التوثيق
                        </div>
                        <div class="info-value">
                            <?php if ($user['kyc_status'] == 'verified'): ?>
                                <span class="badge badge-success">
                                    <i class="fas fa-check-circle"></i>
                                    موثق
                                </span>
                            <?php elseif ($user['kyc_status'] == 'pending'): ?>
                                <span class="badge badge-warning">
                                    <i class="fas fa-hourglass-half"></i>
                                    قيد المراجعة
                                </span>
                            <?php elseif ($user['kyc_status'] == 'rejected'): ?>
                                <span class="badge badge-danger">
                                    <i class="fas fa-times-circle"></i>
                                    مرفوض
                                </span>
                            <?php else: ?>
                                <span class="badge badge-danger">
                                    <i class="fas fa-exclamation-triangle"></i>
                                    غير موثق
                                </span>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <div class="info-item">
                        <div class="info-label">
                            <i class="fas fa-user-tag"></i>
                            نوع الحساب
                        </div>
                        <div class="info-value">
                            <?php echo $user['role'] === 'admin' ? 'مدير' : 'مستخدم'; ?>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Wallet Information -->
            <div class="card">
                <div class="card-header">
                    <i class="fas fa-wallet"></i>
                    <h2>معلومات المحفظة</h2>
                </div>
                <div class="info-grid">
                    <div class="info-item">
                        <div class="info-label">
                            <i class="fas fa-dollar-sign"></i>
                            الرصيد المتاح
                        </div>
                        <div class="info-value" style="color: #10b981;">
                            $<?php echo number_format($wallet['available_balance'], 2); ?>
                        </div>
                    </div>
                    
                    <div class="info-item">
                        <div class="info-label">
                            <i class="fas fa-lock"></i>
                            الرصيد المحجوز
                        </div>
                        <div class="info-value" style="color: #f59e0b;">
                            $<?php echo number_format($wallet['frozen_balance'], 2); ?>
                        </div>
                    </div>
                    
                    <div class="info-item">
                        <div class="info-label">
                            <i class="fas fa-hourglass-half"></i>
                            رصيد المبيعات المعلق
                        </div>
                        <div class="info-value" style="color: #8b5cf6;">
                            $<?php echo number_format($wallet['sales_hold_balance'], 2); ?>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Statistics -->
            <div class="card">
                <div class="card-header">
                    <i class="fas fa-chart-bar"></i>
                    <h2>الإحصائيات</h2>
                </div>
                <div class="stats-grid">
                    <div class="stat-box">
                        <div class="stat-value"><?php echo $total_auctions; ?></div>
                        <div class="stat-label">إجمالي المزادات</div>
                    </div>
                    
                    <div class="stat-box">
                        <div class="stat-value"><?php echo $total_bids; ?></div>
                        <div class="stat-label">إجمالي المزايدات</div>
                    </div>
                    
                    <div class="stat-box">
                        <div class="stat-value">
                            <?php echo $rating_data['count'] > 0 ? number_format($rating_data['avg'], 1) : '-'; ?>
                        </div>
                        <div class="stat-label">
                            التقييم (<?php echo $rating_data['count']; ?>)
                        </div>
                    </div>
                </div>
            </div>

            <!-- Change Password -->
            <div class="card full-width">
                <div class="card-header">
                    <i class="fas fa-key"></i>
                    <h2>تغيير كلمة السر</h2>
                </div>
                <form method="POST" action="">
                    <div class="form-group">
                        <label>
                            <i class="fas fa-lock"></i>
                            كلمة السر الحالية
                        </label>
                        <input 
                            type="password" 
                            name="current_password" 
                            placeholder="أدخل كلمة السر الحالية"
                            required
                        >
                    </div>
                    
                    <div class="form-group">
                        <label>
                            <i class="fas fa-lock"></i>
                            كلمة السر الجديدة
                        </label>
                        <input 
                            type="password" 
                            name="new_password" 
                            placeholder="أدخل كلمة السر الجديدة"
                            required
                        >
                    </div>
                    
                    <div class="form-group">
                        <label>
                            <i class="fas fa-lock"></i>
                            تأكيد كلمة السر الجديدة
                        </label>
                        <input 
                            type="password" 
                            name="confirm_password" 
                            placeholder="أعد إدخال كلمة السر الجديدة"
                            required
                        >
                    </div>
                    
                    <button type="submit" name="change_password" class="btn">
                        <i class="fas fa-save"></i>
                        تحديث كلمة السر
                    </button>
                </form>
            </div>
        </div>
    </div>

    <script>
        // إخفاء الرسائل تلقائياً بعد 5 ثوان
        document.addEventListener('DOMContentLoaded', function() {
            const alerts = document.querySelectorAll('.alert');
            alerts.forEach(alert => {
                setTimeout(() => {
                    alert.style.transition = 'opacity 0.5s';
                    alert.style.opacity = '0';
                    setTimeout(() => alert.remove(), 500);
                }, 5000);
            });
        });
    </script>
</body>
</html>
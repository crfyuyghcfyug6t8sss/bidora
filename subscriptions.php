<?php
require_once 'config/database.php';
require_once 'includes/functions.php';

if (!isLoggedIn()) {
    redirect('auth/login.php');
}

$user_id = $_SESSION['user_id'];

// معالجة الشراء
if (isset($_POST['purchase'])) {
    $plan_id = clean($_POST['plan_id']);
    $duration = clean($_POST['duration']);
    
    $result = purchaseSubscription($user_id, $plan_id, $duration);
    
    if ($result === true) {
        setMessage('تم الاشتراك بنجاح! 🎉', 'success');
        redirect('subscriptions.php');
    } else {
        setMessage($result, 'danger');
    }
}

$current_subscription = getUserSubscription($user_id);
$current_plan_id = getUserPlan($user_id);
$all_plans = getAllPlans();

// الحصول على معلومات الخطة الحالية
$stmt = $conn->prepare("SELECT * FROM subscription_plans WHERE id = ?");
$stmt->execute([$current_plan_id]);
$current_plan = $stmt->fetch();

// حساب الأيام المتبقية
$days_left = 0;
if ($current_subscription) {
    $days_left = ceil((strtotime($current_subscription['expires_at']) - time()) / 86400);
}

$lang_info = getLangInfo();
?>
<!DOCTYPE html>
<html lang="<?php echo $lang_info['code']; ?>" dir="<?php echo $lang_info['dir']; ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>خطط الاشتراكات - BIDORA</title>
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
            padding: 20px;
        }
        
        .container {
            max-width: 1400px;
            margin: 0 auto;
        }
        
        /* Header */
        .page-header {
            background: white;
            padding: 40px;
            border-radius: 20px;
            margin-bottom: 30px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.15);
            text-align: center;
        }
        
        .page-header h1 {
            color: #1e293b;
            margin-bottom: 15px;
            font-size: 3rem;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 20px;
        }
        
        .page-header .subtitle {
            color: #64748b;
            font-size: 1.2rem;
            max-width: 600px;
            margin: 0 auto;
        }
        
        /* Current Plan Card */
        .current-plan-card {
            background: white;
            border-radius: 20px;
            padding: 35px;
            margin-bottom: 40px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.15);
            position: relative;
            overflow: hidden;
        }
        
        .current-plan-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 6px;
            background: var(--plan-color);
        }
        
        .plan-status {
            display: grid;
            grid-template-columns: auto 1fr auto;
            gap: 30px;
            align-items: center;
        }
        
        .plan-icon-large {
            width: 100px;
            height: 100px;
            border-radius: 50%;
            background: var(--plan-color);
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 3rem;
            box-shadow: 0 8px 25px rgba(0,0,0,0.2);
        }
        
        .plan-info h2 {
            color: #1e293b;
            margin-bottom: 10px;
            font-size: 2rem;
        }
        
        .plan-info p {
            color: #64748b;
            font-size: 1.1rem;
            margin-bottom: 8px;
        }
        
        .plan-info .highlight {
            color: var(--plan-color);
            font-weight: bold;
        }
        
        .upgrade-btn {
            background: linear-gradient(135deg, var(--plan-color) 0%, var(--plan-color-dark) 100%);
            color: white;
            padding: 18px 40px;
            border-radius: 15px;
            text-decoration: none;
            font-weight: bold;
            font-size: 1.2rem;
            box-shadow: 0 8px 25px rgba(0,0,0,0.2);
            transition: all 0.3s;
            display: inline-flex;
            align-items: center;
            gap: 10px;
            white-space: nowrap;
        }
        
        .upgrade-btn:hover {
            transform: translateY(-3px);
            box-shadow: 0 12px 35px rgba(0,0,0,0.3);
        }
        
        /* Progress Bar */
        .progress-section {
            margin-top: 25px;
            padding: 20px;
            background: #f8fafc;
            border-radius: 15px;
        }
        
        .progress-info {
            display: flex;
            justify-content: space-between;
            margin-bottom: 12px;
            font-size: 0.95rem;
            color: #475569;
        }
        
        .progress-bar {
            background: #e2e8f0;
            height: 12px;
            border-radius: 10px;
            overflow: hidden;
        }
        
        .progress-fill {
            height: 100%;
            background: linear-gradient(90deg, var(--plan-color) 0%, var(--plan-color-dark) 100%);
            transition: width 1s ease;
            border-radius: 10px;
        }
        
        /* Features Grid */
        .features-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 15px;
            margin-top: 25px;
        }
        
        .feature-item {
            background: #f8fafc;
            padding: 18px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            gap: 15px;
            border-right: 4px solid var(--plan-color);
        }
        
        .feature-item i {
            color: #10b981;
            font-size: 1.5rem;
        }
        
        .feature-name {
            font-size: 0.9rem;
            color: #64748b;
        }
        
        .feature-value {
            font-size: 1.2rem;
            font-weight: bold;
            color: #1e293b;
        }
        
        /* Alert */
        .alert {
            padding: 20px 25px;
            border-radius: 15px;
            margin-bottom: 30px;
            display: flex;
            align-items: center;
            gap: 15px;
            font-size: 1.1rem;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
        }
        
        .alert i {
            font-size: 1.8rem;
        }
        
        .alert-success { 
            background: #d1fae5; 
            color: #065f46; 
            border-right: 5px solid #10b981;
        }
        
        .alert-danger { 
            background: #fee2e2; 
            color: #991b1b; 
            border-right: 5px solid #ef4444;
        }
        
        .alert-warning {
            background: #fef3c7;
            color: #92400e;
            border-right: 5px solid #f59e0b;
        }
        
        /* Plans Section */
        .plans-header {
            text-align: center;
            margin-bottom: 40px;
        }
        
        .plans-header h2 {
            color: white;
            font-size: 2.5rem;
            margin-bottom: 10px;
            text-shadow: 0 2px 10px rgba(0,0,0,0.2);
        }
        
        .plans-header p {
            color: rgba(255,255,255,0.9);
            font-size: 1.2rem;
        }
        
        /* Plans Grid */
        .plans-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(320px, 1fr));
            gap: 30px;
            margin-bottom: 50px;
        }
        
        .plan-card {
            background: white;
            border-radius: 20px;
            padding: 35px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.15);
            transition: all 0.4s;
            position: relative;
            overflow: hidden;
        }
        
        .plan-card:hover {
            transform: translateY(-15px);
            box-shadow: 0 20px 60px rgba(0,0,0,0.25);
        }
        
        .plan-card.featured {
            border: 4px solid #f59e0b;
            transform: scale(1.05);
        }
        
        .plan-card.featured::after {
            content: 'الأكثر شعبية';
            position: absolute;
            top: 20px;
            left: -35px;
            background: #f59e0b;
            color: white;
            padding: 8px 50px;
            transform: rotate(-45deg);
            font-weight: bold;
            font-size: 0.85rem;
            box-shadow: 0 4px 10px rgba(0,0,0,0.2);
        }
        
        .plan-card.current-plan {
            border: 4px solid var(--plan-color);
        }
        
        .plan-card.current-plan::after {
            content: 'خطتك الحالية';
            position: absolute;
            top: 20px;
            left: -35px;
            background: var(--plan-color);
            color: white;
            padding: 8px 50px;
            transform: rotate(-45deg);
            font-weight: bold;
            font-size: 0.85rem;
            box-shadow: 0 4px 10px rgba(0,0,0,0.2);
        }
        
        .plan-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 6px;
            background: var(--plan-color);
        }
        
        .plan-icon {
            width: 80px;
            height: 80px;
            border-radius: 50%;
            background: var(--plan-color);
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 2.5rem;
            margin: 0 auto 25px;
            box-shadow: 0 8px 20px rgba(0,0,0,0.15);
        }
        
        .plan-name {
            text-align: center;
            font-size: 2rem;
            color: #1e293b;
            margin-bottom: 15px;
            font-weight: bold;
        }
        
        .plan-description {
            text-align: center;
            color: #64748b;
            margin-bottom: 25px;
            font-size: 1rem;
        }
        
        .plan-price {
            text-align: center;
            margin-bottom: 25px;
        }
        
        .price-amount {
            font-size: 3.5rem;
            font-weight: bold;
            color: #1e293b;
            line-height: 1;
        }
        
        .price-currency {
            font-size: 2rem;
            vertical-align: super;
        }
        
        .price-period {
            color: #64748b;
            font-size: 1.1rem;
            margin-top: 8px;
        }
        
        /* Duration Toggle */
        .duration-toggle {
            display: flex;
            gap: 10px;
            margin-bottom: 30px;
            background: #f8fafc;
            padding: 8px;
            border-radius: 15px;
        }
        
        .toggle-btn {
            flex: 1;
            padding: 15px;
            border: none;
            background: transparent;
            border-radius: 12px;
            cursor: pointer;
            font-weight: 600;
            font-size: 1rem;
            transition: all 0.3s;
            color: #64748b;
        }
        
        .toggle-btn.active {
            background: var(--plan-color);
            color: white;
            box-shadow: 0 4px 15px rgba(0,0,0,0.2);
        }
        
        .toggle-btn small {
            display: block;
            font-size: 0.8rem;
            margin-top: 3px;
            font-weight: normal;
        }
        
        /* Features List */
        .plan-features {
            list-style: none;
            margin-bottom: 30px;
        }
        
        .plan-features li {
            padding: 15px 0;
            border-bottom: 1px solid #f1f5f9;
            display: flex;
            align-items: center;
            gap: 12px;
            color: #475569;
            font-size: 1rem;
        }
        
        .plan-features li:last-child {
            border-bottom: none;
        }
        
        .plan-features i {
            color: #10b981;
            font-size: 1.3rem;
        }
        
        /* Subscribe Button */
        .subscribe-btn {
            width: 100%;
            padding: 18px;
            background: var(--plan-color);
            color: white;
            border: none;
            border-radius: 15px;
            font-size: 1.2rem;
            font-weight: bold;
            cursor: pointer;
            transition: all 0.3s;
            box-shadow: 0 8px 20px rgba(0,0,0,0.15);
        }
        
        .subscribe-btn:hover:not(:disabled) {
            transform: translateY(-3px);
            box-shadow: 0 12px 30px rgba(0,0,0,0.25);
        }
        
        .subscribe-btn:disabled {
            opacity: 0.6;
            cursor: not-allowed;
        }
        
        .subscribe-btn i {
            margin-left: 8px;
        }
        
        /* Back Button */
        .back-btn {
            display: inline-flex;
            align-items: center;
            gap: 10px;
            background: white;
            color: #667eea;
            padding: 12px 25px;
            border-radius: 12px;
            text-decoration: none;
            font-weight: 600;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
            margin-bottom: 20px;
            transition: all 0.3s;
        }
        
        .back-btn:hover {
            transform: translateX(-5px);
            box-shadow: 0 6px 20px rgba(0,0,0,0.15);
        }
        
        /* Responsive */
        @media (max-width: 768px) {
            .page-header h1 {
                font-size: 2rem;
            }
            
            .plan-status {
                grid-template-columns: 1fr;
                text-align: center;
            }
            
            .plan-icon-large {
                margin: 0 auto;
            }
            
            .upgrade-btn {
                width: 100%;
                justify-content: center;
            }
            
            .plans-grid {
                grid-template-columns: 1fr;
            }
            
            .plan-card.featured {
                transform: scale(1);
            }
        }
    </style>
</head>
<body>
    <!-- Language Switcher -->
    <div style="position: fixed; top: 20px; left: 20px; z-index: 1000;">
        <?php include 'includes/lang-switcher.php'; ?>
    </div>

    <div class="container">
        <!-- Back Button -->
        <a href="dashboard.php" class="back-btn">
            <i class="fas fa-arrow-right"></i>
            <span>العودة للوحة التحكم</span>
        </a>

        <!-- Page Header -->
        <div class="page-header">
            <h1>
                <i class="fas fa-crown" style="color: #f59e0b;"></i>
                <span>خطط الاشتراكات</span>
            </h1>
            <p class="subtitle">
                اختر الخطة المناسبة لك واحصل على المزيد من المزايا والإمكانيات
            </p>
        </div>

        <!-- Alerts -->
        <?php if ($msg = getMessage()): ?>
            <div class="alert alert-<?php echo $msg['type']; ?>">
                <i class="fas fa-<?php echo $msg['type'] == 'success' ? 'check-circle' : 'exclamation-circle'; ?>"></i>
                <span><?php echo $msg['text']; ?></span>
            </div>
        <?php endif; ?>

        <!-- Warning if subscription ending soon -->
        <?php if ($current_subscription && $days_left > 0 && $days_left <= 7): ?>
            <div class="alert alert-warning">
                <i class="fas fa-exclamation-triangle"></i>
                <div style="flex: 1;">
                    <strong>تحذير:</strong> اشتراكك سينتهي خلال <?php echo $days_left; ?> أيام!
                    قم بالتجديد الآن لتجنب فقدان المزايا.
                </div>
            </div>
        <?php endif; ?>

        <!-- Current Plan Card -->
        <div class="current-plan-card" style="--plan-color: <?php echo $current_plan['color']; ?>; --plan-color-dark: <?php echo adjustBrightness($current_plan['color'], -30); ?>;">
            <div class="plan-status">
                <div class="plan-icon-large">
                    <i class="fas <?php echo $current_plan['icon']; ?>"></i>
                </div>
                
                <div class="plan-info">
                    <h2>خطتك الحالية: <span class="highlight"><?php echo $current_plan['name']; ?></span></h2>
                    
                    <?php if ($current_subscription): ?>
                        <p>
                            <i class="fas fa-calendar-alt"></i>
                            بدأت في: <strong><?php echo date('Y-m-d', strtotime($current_subscription['starts_at'])); ?></strong>
                        </p>
                        <p>
                            <i class="fas fa-calendar-check"></i>
                            تنتهي في: <strong><?php echo date('Y-m-d', strtotime($current_subscription['expires_at'])); ?></strong>
                            (<?php echo $days_left; ?> يوم متبقي)
                        </p>
                        <p>
                            <i class="fas fa-dollar-sign"></i>
                            المبلغ المدفوع: <strong>$<?php echo number_format($current_subscription['amount_paid'], 2); ?></strong>
                        </p>
                    <?php else: ?>
                        <p>
                            <i class="fas fa-info-circle"></i>
                            أنت حالياً على الخطة المجانية. قم بالترقية للحصول على مزايا أكثر!
                        </p>
                    <?php endif; ?>
                </div>
                
                <?php if ($current_plan_id == 1 || ($current_subscription && $days_left <= 7)): ?>
                    <a href="#plans" class="upgrade-btn">
                        <i class="fas fa-arrow-up"></i>
                        <span><?php echo $current_plan_id == 1 ? 'ترقية الخطة' : 'تجديد الاشتراك'; ?></span>
                    </a>
                <?php endif; ?>
            </div>

            <!-- Progress Bar -->
            <?php if ($current_subscription): ?>
                <?php 
                $total_days = (strtotime($current_subscription['expires_at']) - strtotime($current_subscription['starts_at'])) / 86400;
                $elapsed_days = (time() - strtotime($current_subscription['starts_at'])) / 86400;
                $progress = min(100, max(0, ($elapsed_days / $total_days) * 100));
                ?>
                <div class="progress-section">
                    <div class="progress-info">
                        <span><strong><?php echo round($progress); ?>%</strong> من مدة الاشتراك</span>
                        <span><strong><?php echo $days_left; ?></strong> يوم متبقي</span>
                    </div>
                    <div class="progress-bar">
                        <div class="progress-fill" style="width: <?php echo $progress; ?>%;"></div>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Current Features -->
            <div class="features-grid">
                <?php
                $current_features = getPlanFeatures($current_plan_id);
                foreach ($current_features as $feature):
                ?>
                    <div class="feature-item">
                        <i class="fas fa-check-circle"></i>
                        <div>
                            <div class="feature-name"><?php echo $feature['feature_name']; ?></div>
                            <div class="feature-value"><?php echo $feature['feature_value']; ?></div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- Plans Header -->
        <div class="plans-header" id="plans">
            <h2>اختر خطتك المثالية</h2>
            <p>جميع الخطط تشمل الميزات الأساسية مع إمكانية الترقية في أي وقت</p>
        </div>

        <!-- Plans Grid -->
        <div class="plans-grid">
            <?php foreach ($all_plans as $plan): ?>
                <?php 
                $features = getPlanFeatures($plan['id']); 
                $is_current = ($plan['id'] == $current_plan_id);
                $is_upgrade = ($plan['id'] > $current_plan_id);
                ?>
                <div class="plan-card <?php echo ($plan['display_order'] == 3 && !$is_current) ? 'featured' : ''; ?> <?php echo $is_current ? 'current-plan' : ''; ?>" 
                     style="--plan-color: <?php echo $plan['color']; ?>; --plan-color-dark: <?php echo adjustBrightness($plan['color'], -30); ?>;">
                    
                    <div class="plan-icon">
                        <i class="fas <?php echo $plan['icon']; ?>"></i>
                    </div>
                    
                    <h3 class="plan-name"><?php echo $plan['name']; ?></h3>
                    <p class="plan-description"><?php echo $plan['description']; ?></p>
                    
                    <div class="plan-price" id="price_<?php echo $plan['id']; ?>">
                        <div class="price-amount">
                            <span class="price-currency">$</span><span class="price-value"><?php echo number_format($plan['price_monthly'], 0); ?></span>
                        </div>
                        <div class="price-period">
                            <span class="period-text">شهرياً</span>
                        </div>
                    </div>
                    
                    <?php if ($plan['price_monthly'] > 0): ?>
                        <?php $savings = round((1 - ($plan['price_yearly'] / ($plan['price_monthly'] * 12))) * 100); ?>
                        <div class="duration-toggle">
                            <button class="toggle-btn active" 
                                    onclick="togglePrice(<?php echo $plan['id']; ?>, 'monthly', <?php echo $plan['price_monthly']; ?>, this)">
                                شهري
                            </button>
                            <button class="toggle-btn" 
                                    onclick="togglePrice(<?php echo $plan['id']; ?>, 'yearly', <?php echo $plan['price_yearly']; ?>, this)">
                                سنوي
                                <?php if ($savings > 0): ?>
                                    <small>وفّر <?php echo $savings; ?>%</small>
                                <?php endif; ?>
                            </button>
                        </div>
                    <?php endif; ?>
                    
                    <ul class="plan-features">
                        <?php foreach ($features as $feature): ?>
                            <li>
                                <i class="fas fa-check-circle"></i>
                                <span><strong><?php echo $feature['feature_name']; ?>:</strong> <?php echo $feature['feature_value']; ?></span>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                    
                    <form method="POST">
                        <input type="hidden" name="plan_id" value="<?php echo $plan['id']; ?>">
                        <input type="hidden" name="duration" value="monthly" id="duration_<?php echo $plan['id']; ?>">
                        
                        <?php if ($is_current): ?>
                            <button type="button" class="subscribe-btn" disabled>
                                <i class="fas fa-check"></i>
                                خطتك الحالية
                            </button>
                        <?php elseif ($plan['price_monthly'] == 0): ?>
                            <button type="submit" name="purchase" class="subscribe-btn">
                                <i class="fas fa-gift"></i>
                                مجاني - ابدأ الآن
                            </button>
                        <?php elseif ($is_upgrade): ?>
                            <button type="submit" name="purchase" class="subscribe-btn">
                                <i class="fas fa-arrow-up"></i>
                                ترقية للخطة - $<span id="btn_price_<?php echo $plan['id']; ?>"><?php echo number_format($plan['price_monthly'], 2); ?></span>
                            </button>
                        <?php else: ?>
                            <button type="submit" name="purchase" class="subscribe-btn">
                                <i class="fas fa-shopping-cart"></i>
                                اشترك الآن - $<span id="btn_price_<?php echo $plan['id']; ?>"><?php echo number_format($plan['price_monthly'], 2); ?></span>
                            </button>
                        <?php endif; ?>
                    </form>
                </div>
            <?php endforeach; ?>
        </div>
    </div>

    <script src="js/auto-translate.js"></script>
    <script>
    function togglePrice(planId, duration, price, button) {
        // Update displayed price
        const priceElement = document.querySelector(`#price_${planId} .price-value`);
        const periodElement = document.querySelector(`#price_${planId} .period-text`);
        const durationInput = document.getElementById(`duration_${planId}`);
        const buttonPrice = document.getElementById(`btn_price_${planId}`);
        
        // Calculate display price
        const displayPrice = duration === 'monthly' ? price : (price / 12);
        
        priceElement.textContent = Math.round(displayPrice);
        periodElement.textContent = duration === 'monthly' ? 'شهرياً' : 'سنوياً';
        durationInput.value = duration;
        
        if (buttonPrice) {
            buttonPrice.textContent = parseFloat(price).toFixed(2);
        }
        
        // Update button states
        const parent = button.parentElement;
        parent.querySelectorAll('.toggle-btn').forEach(btn => {
            btn.classList.remove('active');
        });
        button.classList.add('active');
    }
    
    // Animate progress bar on load
    window.addEventListener('load', function() {
        const progressBar = document.querySelector('.progress-fill');
        if (progressBar) {
            const width = progressBar.style.width;
            progressBar.style.width = '0%';
            setTimeout(() => {
                progressBar.style.width = width;
            }, 100);
        }
    });
    </script>
</body>
</html>

<?php
function adjustBrightness($hex, $steps) {
    $hex = str_replace('#', '', $hex);
    $r = hexdec(substr($hex, 0, 2));
    $g = hexdec(substr($hex, 2, 2));
    $b = hexdec(substr($hex, 4, 2));
    
    $r = max(0, min(255, $r + $steps));
    $g = max(0, min(255, $g + $steps));
    $b = max(0, min(255, $b + $steps));
    
    return '#' . str_pad(dechex($r), 2, '0', STR_PAD_LEFT) 
              . str_pad(dechex($g), 2, '0', STR_PAD_LEFT) 
              . str_pad(dechex($b), 2, '0', STR_PAD_LEFT);
}
?>
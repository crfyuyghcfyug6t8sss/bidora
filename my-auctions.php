<?php
require_once 'config/database.php';
require_once 'includes/functions.php';

if (!isLoggedIn()) {
    setMessage('يجب تسجيل الدخول أولاً', 'danger');
    redirect('auth/login.php');
}

$user_id = $_SESSION['user_id'];

// جلب جميع مزادات البائع
$stmt = $conn->prepare("
    SELECT 
        a.*,
        v.*,
        winner.username as winner_name,
        (SELECT COUNT(*) FROM bids WHERE auction_id = a.id) as total_bids_count
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
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>مزاداتي - مزادات السيارات</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background: #f5f5f5; direction: rtl; }
        .header { background: #2563eb; color: white; padding: 20px 0; box-shadow: 0 2px 5px rgba(0,0,0,0.1); }
        .container { max-width: 1200px; margin: 0 auto; padding: 0 20px; }
        .header-content { display: flex; justify-content: space-between; align-items: center; }
        .header h1 { font-size: 1.5rem; }
        .nav a { color: white; text-decoration: none; margin-left: 20px; padding: 8px 15px; border-radius: 5px; transition: 0.3s; }
        .nav a:hover { background: rgba(255,255,255,0.2); }
        .content { padding: 40px 20px; }
        .page-title { text-align: center; margin-bottom: 40px; }
        .page-title h2 { font-size: 2.5rem; color: #1e293b; margin-bottom: 10px; }
        .page-title p { color: #64748b; font-size: 1.1rem; }
        .stats-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px; margin-bottom: 40px; }
        .stat-card { background: white; padding: 25px; border-radius: 12px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); text-align: center; }
        .stat-number { font-size: 2.5rem; font-weight: bold; color: #2563eb; margin-bottom: 5px; }
        .stat-label { color: #64748b; font-size: 0.9rem; }
        .tabs { display: flex; gap: 10px; margin-bottom: 30px; border-bottom: 2px solid #e2e8f0; flex-wrap: wrap; }
        .tab { padding: 15px 25px; background: none; border: none; font-size: 1rem; font-weight: 600; color: #64748b; cursor: pointer; border-bottom: 3px solid transparent; transition: 0.3s; }
        .tab.active { color: #2563eb; border-bottom-color: #2563eb; }
        .tab:hover { color: #2563eb; }
        .tab-content { display: none; }
        .tab-content.active { display: block; }
        .auctions-grid { display: grid; gap: 20px; }
        .auction-card { background: white; border-radius: 12px; padding: 25px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); display: grid; grid-template-columns: auto 1fr auto; gap: 25px; align-items: center; transition: 0.3s; }
        .auction-card:hover { transform: translateY(-2px); box-shadow: 0 5px 15px rgba(0,0,0,0.15); }
        .auction-icon { width: 80px; height: 80px; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); border-radius: 12px; display: flex; align-items: center; justify-content: center; font-size: 2.5rem; color: white; }
        .auction-info h3 { color: #1e293b; margin-bottom: 8px; font-size: 1.3rem; }
        .auction-meta { display: flex; gap: 20px; flex-wrap: wrap; margin-top: 10px; }
        .meta-item { display: flex; align-items: center; gap: 5px; color: #64748b; font-size: 0.9rem; }
        .auction-stats { display: grid; grid-template-columns: repeat(3, 1fr); gap: 15px; min-width: 300px; }
        .stat-box { background: #f8fafc; padding: 12px; border-radius: 8px; text-align: center; }
        .stat-box-label { font-size: 0.85rem; color: #64748b; margin-bottom: 5px; }
        .stat-box-value { font-size: 1.3rem; font-weight: bold; color: #1e293b; }
        .status-badge { display: inline-block; padding: 6px 14px; border-radius: 20px; font-size: 0.85rem; font-weight: 600; margin-left: 10px; }
        .status-active { background: #d1fae5; color: #065f46; }
        .status-completed { background: #dbeafe; color: #1e40af; }
        .status-expired { background: #fee2e2; color: #991b1b; }
        .status-upcoming { background: #fef3c7; color: #92400e; }
        .btn { display: inline-block; padding: 10px 20px; background: #2563eb; color: white; text-decoration: none; border-radius: 8px; font-weight: 600; transition: 0.3s; border: none; cursor: pointer; font-size: 0.9rem; }
        .btn:hover { background: #1d4ed8; transform: translateY(-2px); }
        .btn-outline { background: transparent; border: 2px solid #2563eb; color: #2563eb; }
        .btn-outline:hover { background: #2563eb; color: white; }
        .btn-success { background: #10b981; }
        .btn-success:hover { background: #059669; }
        .empty-state { text-align: center; padding: 60px 20px; background: white; border-radius: 15px; }
        .empty-state-icon { font-size: 5rem; margin-bottom: 20px; opacity: 0.5; }
        .empty-state h3 { color: #1e293b; margin-bottom: 10px; }
        .empty-state p { color: #64748b; margin-bottom: 20px; }
        @media (max-width: 768px) { 
            .auction-card { grid-template-columns: 1fr; }
            .auction-stats { grid-template-columns: 1fr; min-width: auto; }
        }
    </style>
</head>
<body>
    <div class="header">
        <div class="container">
            <div class="header-content">
                <h1>مزادات السيارات</h1>
                                    <div style="position: fixed; top: 20px; left: 20px; z-index: 1000;">
        <?php include 'includes/lang-switcher.php'; ?>
    </div>
    <script src="js/auto-translate.js"></script>

                <div class="nav">
                    <a href="index.php">الرئيسية</a>
                    <a href="auctions.php">المزادات</a>
                    <a href="dashboard.php">لوحة التحكم</a>
                    <a href="auth/logout.php">تسجيل الخروج</a>
                </div>
            </div>
        </div>
    </div>

    <div class="content">
        <div class="container">
            <div class="page-title">
                <h2>مزاداتي</h2>
                <p>إدارة جميع السيارات التي أضفتها للمزاد</p>
            </div>

            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-number"><?php echo count($active_auctions); ?></div>
                    <div class="stat-label">مزادات نشطة</div>
                </div>
                <div class="stat-card">
                    <div class="stat-number"><?php echo count($completed_auctions); ?></div>
                    <div class="stat-label">مزادات مكتملة</div>
                </div>
                <div class="stat-card">
                    <div class="stat-number">
                        <?php 
                        $total_revenue = 0;
                        foreach ($completed_auctions as $a) {
                            $total_revenue += $a['current_price'] * 0.95; // بعد خصم 5% عمولة
                        }
                        echo number_format($total_revenue, 0);
                        ?>$
                    </div>
                    <div class="stat-label">إجمالي الإيرادات</div>
                </div>
                <div class="stat-card">
                    <div class="stat-number"><?php echo count($auctions); ?></div>
                    <div class="stat-label">إجمالي المزادات</div>
                </div>
            </div>

            <div style="margin-bottom: 30px; text-align: center;">
                <a href="add-vehicle.php" class="btn btn-success">+ إضافة سيارة جديدة</a>
            </div>

            <div class="tabs">
                <button class="tab active" onclick="switchTab('active')">
                    نشطة (<?php echo count($active_auctions); ?>)
                </button>
                <button class="tab" onclick="switchTab('completed')">
                    مكتملة (<?php echo count($completed_auctions); ?>)
                </button>
                <button class="tab" onclick="switchTab('expired')">
                    منتهية بدون بيع (<?php echo count($expired_auctions); ?>)
                </button>
            </div>

            <!-- Active Auctions -->
            <div id="active" class="tab-content active">
                <?php if (empty($active_auctions)): ?>
                    <div class="empty-state">
                        <div class="empty-state-icon">📦</div>
                        <h3>لا توجد مزادات نشطة</h3>
                        <p>ابدأ بإضافة سيارة للمزاد</p>
                        <a href="add-vehicle.php" class="btn">إضافة سيارة</a>
                    </div>
                <?php else: ?>
                    <div class="auctions-grid">
                        <?php foreach ($active_auctions as $auction): ?>
                            <div class="auction-card">
                                <div class="auction-icon">🚗</div>
                                
                                <div class="auction-info">
                                    <h3><?php echo htmlspecialchars($auction['brand'] . ' ' . $auction['model']); ?></h3>
                                    <span class="status-badge status-active">نشط</span>
                                    <div class="auction-meta">
                                        <span class="meta-item">📅 <?php echo $auction['year']; ?></span>
                                        <span class="meta-item">⏰ ينتهي: <?php echo date('Y-m-d H:i', strtotime($auction['end_time'])); ?></span>
                                        <span class="meta-item">🔨 <?php echo $auction['total_bids']; ?> مزايدة</span>
                                    </div>
                                </div>
                                
                                <div class="auction-stats">
                                    <div class="stat-box">
                                        <div class="stat-box-label">السعر الابتدائي</div>
                                        <div class="stat-box-value"><?php echo number_format($auction['starting_price'], 0); ?>$</div>
                                    </div>
                                    <div class="stat-box">
                                        <div class="stat-box-label">السعر الحالي</div>
                                        <div class="stat-box-value" style="color: #10b981;"><?php echo number_format($auction['current_price'], 0); ?>$</div>
                                    </div>
                                    <div class="stat-box">
                                        <div class="stat-box-label">أعلى مزايد</div>
                                        <div class="stat-box-value" style="font-size: 1rem;">
                                            <?php echo $auction['winner_name'] ? htmlspecialchars($auction['winner_name']) : 'لا يوجد'; ?>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div style="text-align: left; margin-top: -15px; margin-bottom: 20px;">
                                <a href="auction-details.php?id=<?php echo $auction['id']; ?>" class="btn btn-outline">عرض التفاصيل</a>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Completed Auctions -->
            <div id="completed" class="tab-content">
                <?php if (empty($completed_auctions)): ?>
                    <div class="empty-state">
                        <div class="empty-state-icon">✅</div>
                        <h3>لا توجد مزادات مكتملة بعد</h3>
                    </div>
                <?php else: ?>
                    <div class="auctions-grid">
                        <?php foreach ($completed_auctions as $auction): ?>
                            <div class="auction-card">
                                <div class="auction-icon">🚗</div>
                                
                                <div class="auction-info">
                                    <h3><?php echo htmlspecialchars($auction['brand'] . ' ' . $auction['model']); ?></h3>
                                    <span class="status-badge status-completed">مكتمل</span>
                                    <div class="auction-meta">
                                        <span class="meta-item">👤 الفائز: <?php echo htmlspecialchars($auction['winner_name'] ?? 'غير متاح'); ?></span>
                                        <span class="meta-item">📅 <?php echo date('Y-m-d', strtotime($auction['end_time'])); ?></span>
                                    </div>
                                </div>
                                
                                <div class="auction-stats">
                                    <div class="stat-box">
                                        <div class="stat-box-label">السعر النهائي</div>
                                        <div class="stat-box-value"><?php echo number_format($auction['current_price'], 0); ?>$</div>
                                    </div>
                                    <div class="stat-box">
                                        <div class="stat-box-label">صافي الربح</div>
                                        <div class="stat-box-value" style="color: #10b981;">
                                            <?php echo number_format($auction['current_price'] * 0.95, 0); ?>$
                                        </div>
                                        <div style="font-size: 0.75rem; color: #64748b; margin-top: 3px;">بعد عمولة 5%</div>
                                    </div>
                                    <div class="stat-box">
                                        <div class="stat-box-label">المزايدات</div>
                                        <div class="stat-box-value"><?php echo $auction['total_bids']; ?></div>
                                    </div>
                                </div>
                            </div>
                            <div style="text-align: left; margin-top: -15px; margin-bottom: 20px;">
                                <a href="auction-details.php?id=<?php echo $auction['id']; ?>" class="btn btn-outline">عرض التفاصيل</a>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Expired Auctions -->
            <div id="expired" class="tab-content">
                <?php if (empty($expired_auctions)): ?>
                    <div class="empty-state">
                        <div class="empty-state-icon">⏰</div>
                        <h3>لا توجد مزادات منتهية</h3>
                    </div>
                <?php else: ?>
                    <div class="auctions-grid">
                        <?php foreach ($expired_auctions as $auction): ?>
                            <div class="auction-card">
                                <div class="auction-icon" style="background: #94a3b8;">🚗</div>
                                
                                <div class="auction-info">
                                    <h3><?php echo htmlspecialchars($auction['brand'] . ' ' . $auction['model']); ?></h3>
                                    <span class="status-badge status-expired">منتهي بدون بيع</span>
                                    <div class="auction-meta">
                                        <span class="meta-item">📅 انتهى: <?php echo date('Y-m-d', strtotime($auction['end_time'])); ?></span>
                                        <span class="meta-item">🔨 <?php echo $auction['total_bids']; ?> مزايدة</span>
                                    </div>
                                </div>
                                
                                <div class="auction-stats">
                                    <div class="stat-box">
                                        <div class="stat-box-label">آخر سعر</div>
                                        <div class="stat-box-value"><?php echo number_format($auction['current_price'], 0); ?>$</div>
                                    </div>
                                </div>
                            </div>
                            <div style="text-align: left; margin-top: -15px; margin-bottom: 20px;">
                                <a href="auction-details.php?id=<?php echo $auction['id']; ?>" class="btn btn-outline">عرض التفاصيل</a>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script>
        function switchTab(tabName) {
            document.querySelectorAll('.tab-content').forEach(content => {
                content.classList.remove('active');
            });
            document.querySelectorAll('.tab').forEach(tab => {
                tab.classList.remove('active');
            });
            document.getElementById(tabName).classList.add('active');
            event.target.classList.add('active');
        }
    </script>
</body>
</html>
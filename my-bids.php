<?php
require_once 'config/database.php';
require_once 'includes/functions.php';

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
        seller.username as seller_name
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
?>

<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>مزايداتي - مزادات السيارات</title>
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
        .tabs { display: flex; gap: 10px; margin-bottom: 30px; border-bottom: 2px solid #e2e8f0; }
        .tab { padding: 15px 25px; background: none; border: none; font-size: 1rem; font-weight: 600; color: #64748b; cursor: pointer; border-bottom: 3px solid transparent; transition: 0.3s; }
        .tab.active { color: #2563eb; border-bottom-color: #2563eb; }
        .tab:hover { color: #2563eb; }
        .tab-content { display: none; }
        .tab-content.active { display: block; }
        .bids-grid { display: grid; gap: 20px; }
        .bid-card { background: white; border-radius: 12px; padding: 20px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); display: grid; grid-template-columns: 1fr 2fr 1fr; gap: 20px; align-items: center; transition: 0.3s; }
        .bid-card:hover { transform: translateY(-2px); box-shadow: 0 5px 15px rgba(0,0,0,0.15); }
        .bid-car-info h3 { color: #1e293b; margin-bottom: 5px; }
        .bid-car-info p { color: #64748b; font-size: 0.9rem; }
        .bid-details { display: grid; grid-template-columns: repeat(2, 1fr); gap: 15px; }
        .bid-detail { background: #f8fafc; padding: 10px; border-radius: 8px; }
        .bid-detail-label { font-size: 0.85rem; color: #64748b; margin-bottom: 3px; }
        .bid-detail-value { font-size: 1.1rem; font-weight: 600; color: #1e293b; }
        .bid-actions { text-align: center; }
        .status-badge { display: inline-block; padding: 8px 16px; border-radius: 20px; font-size: 0.9rem; font-weight: 600; margin-bottom: 15px; }
        .status-winning { background: #d1fae5; color: #065f46; }
        .status-outbid { background: #fee2e2; color: #991b1b; }
        .status-won { background: #dbeafe; color: #1e40af; }
        .btn { display: inline-block; padding: 10px 20px; background: #2563eb; color: white; text-decoration: none; border-radius: 8px; font-weight: 600; transition: 0.3s; border: none; cursor: pointer; }
        .btn:hover { background: #1d4ed8; transform: translateY(-2px); }
        .btn-outline { background: transparent; border: 2px solid #2563eb; color: #2563eb; }
        .btn-outline:hover { background: #2563eb; color: white; }
        .empty-state { text-align: center; padding: 60px 20px; background: white; border-radius: 15px; }
        .empty-state-icon { font-size: 5rem; margin-bottom: 20px; opacity: 0.5; }
        .empty-state h3 { color: #1e293b; margin-bottom: 10px; }
        .empty-state p { color: #64748b; margin-bottom: 20px; }
        .timer { font-size: 0.9rem; color: #64748b; margin-top: 5px; }
        @media (max-width: 768px) { .bid-card { grid-template-columns: 1fr; } .bid-details { grid-template-columns: 1fr; } }
    </style>
</head>
<body>
    <div class="header">
        <div class="container">
            <div class="header-content">
                <h1>مزادات السيارات</h1>
                <div class="nav">
                                        <div style="position: fixed; top: 20px; left: 20px; z-index: 1000;">
        <?php include 'includes/lang-switcher.php'; ?>
    </div>
    <script src="js/auto-translate.js"></script>

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
                <h2>مزايداتي</h2>
                <p>تتبع جميع مزايداتك وحالتها</p>
            </div>

            <div class="tabs">
                <button class="tab active" onclick="switchTab('winning')">
                    الفوز حالياً (<?php echo count($winning_bids); ?>)
                </button>
                <button class="tab" onclick="switchTab('active')">
                    نشطة (<?php echo count($active_bids); ?>)
                </button>
                <button class="tab" onclick="switchTab('outbid')">
                    تم تجاوزها (<?php echo count($outbid_bids); ?>)
                </button>
                <button class="tab" onclick="switchTab('completed')">
                    مكتملة (<?php echo count($completed_bids); ?>)
                </button>
            </div>

            <!-- Winning Bids -->
            <div id="winning" class="tab-content active">
                <?php if (empty($winning_bids)): ?>
                    <div class="empty-state">
                        <div class="empty-state-icon">🏆</div>
                        <h3>لا توجد مزايدات رابحة حالياً</h3>
                        <p>ابحث عن مزادات جديدة وزايد لتكون الأعلى</p>
                        <a href="auctions.php" class="btn">تصفح المزادات</a>
                    </div>
                <?php else: ?>
                    <div class="bids-grid">
                        <?php foreach ($winning_bids as $bid): ?>
                            <div class="bid-card">
                                <div class="bid-car-info">
                                    <h3><?php echo htmlspecialchars($bid['brand'] . ' ' . $bid['model']); ?></h3>
                                    <p>سنة الصنع: <?php echo $bid['year']; ?></p>
                                    <p>البائع: <?php echo htmlspecialchars($bid['seller_name']); ?></p>
                                </div>
                                
                                <div class="bid-details">
                                    <div class="bid-detail">
                                        <div class="bid-detail-label">مزايدتك</div>
                                        <div class="bid-detail-value"><?php echo number_format($bid['bid_amount'], 2); ?>$</div>
                                    </div>
                                    <div class="bid-detail">
                                        <div class="bid-detail-label">السعر الحالي</div>
                                        <div class="bid-detail-value"><?php echo number_format($bid['current_price'], 2); ?>$</div>
                                    </div>
                                    <div class="bid-detail">
                                        <div class="bid-detail-label">رصيد محجوز</div>
                                        <div class="bid-detail-value"><?php echo number_format($bid['frozen_amount'], 2); ?>$</div>
                                    </div>
                                    <div class="bid-detail">
                                        <div class="bid-detail-label">ينتهي في</div>
                                        <div class="bid-detail-value">
                                            <?php 
                                            $remaining = strtotime($bid['end_time']) - time();
                                            $hours = floor($remaining / 3600);
                                            echo $hours > 0 ? $hours . ' ساعة' : 'قريباً';
                                            ?>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="bid-actions">
                                    <span class="status-badge status-winning">أنت الأعلى</span>
                                    <a href="auction-details.php?id=<?php echo $bid['auction_id']; ?>" class="btn">عرض المزاد</a>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Active Bids -->
            <div id="active" class="tab-content">
                <?php if (empty($active_bids)): ?>
                    <div class="empty-state">
                        <div class="empty-state-icon">📋</div>
                        <h3>لا توجد مزايدات نشطة</h3>
                        <p>ابدأ بالمزايدة على سيارة تعجبك</p>
                        <a href="auctions.php" class="btn">تصفح المزادات</a>
                    </div>
                <?php else: ?>
                    <div class="bids-grid">
                        <?php foreach ($active_bids as $bid): ?>
                            <div class="bid-card">
                                <div class="bid-car-info">
                                    <h3><?php echo htmlspecialchars($bid['brand'] . ' ' . $bid['model']); ?></h3>
                                    <p>سنة الصنع: <?php echo $bid['year']; ?></p>
                                </div>
                                <div class="bid-details">
                                    <div class="bid-detail">
                                        <div class="bid-detail-label">مزايدتك</div>
                                        <div class="bid-detail-value"><?php echo number_format($bid['bid_amount'], 2); ?>$</div>
                                    </div>
                                    <div class="bid-detail">
                                        <div class="bid-detail-label">السعر الحالي</div>
                                        <div class="bid-detail-value"><?php echo number_format($bid['current_price'], 2); ?>$</div>
                                    </div>
                                </div>
                                <div class="bid-actions">
                                    <a href="auction-details.php?id=<?php echo $bid['auction_id']; ?>" class="btn">عرض المزاد</a>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Outbid -->
            <div id="outbid" class="tab-content">
                <?php if (empty($outbid_bids)): ?>
                    <div class="empty-state">
                        <div class="empty-state-icon">⏰</div>
                        <h3>رائع! لم يتم تجاوز أي مزايدة</h3>
                    </div>
                <?php else: ?>
                    <div class="bids-grid">
                        <?php foreach ($outbid_bids as $bid): ?>
                            <div class="bid-card">
                                <div class="bid-car-info">
                                    <h3><?php echo htmlspecialchars($bid['brand'] . ' ' . $bid['model']); ?></h3>
                                    <p>سنة الصنع: <?php echo $bid['year']; ?></p>
                                </div>
                                <div class="bid-details">
                                    <div class="bid-detail">
                                        <div class="bid-detail-label">مزايدتك</div>
                                        <div class="bid-detail-value"><?php echo number_format($bid['bid_amount'], 2); ?>$</div>
                                    </div>
                                    <div class="bid-detail">
                                        <div class="bid-detail-label">السعر الحالي</div>
                                        <div class="bid-detail-value"><?php echo number_format($bid['current_price'], 2); ?>$</div>
                                    </div>
                                </div>
                                <div class="bid-actions">
                                    <span class="status-badge status-outbid">تم تجاوزك</span>
                                    <a href="auction-details.php?id=<?php echo $bid['auction_id']; ?>" class="btn">زايد مرة أخرى</a>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Completed -->
            <div id="completed" class="tab-content">
                <?php if (empty($completed_bids)): ?>
                    <div class="empty-state">
                        <div class="empty-state-icon">✅</div>
                        <h3>لا توجد مزايدات مكتملة بعد</h3>
                    </div>
                <?php else: ?>
                    <div class="bids-grid">
                        <?php foreach ($completed_bids as $bid): ?>
                            <div class="bid-card">
                                <div class="bid-car-info">
                                    <h3><?php echo htmlspecialchars($bid['brand'] . ' ' . $bid['model']); ?></h3>
                                    <p>سنة الصنع: <?php echo $bid['year']; ?></p>
                                </div>
                                <div class="bid-details">
                                    <div class="bid-detail">
                                        <div class="bid-detail-label">السعر النهائي</div>
                                        <div class="bid-detail-value"><?php echo number_format($bid['bid_amount'], 2); ?>$</div>
                                    </div>
                                    <div class="bid-detail">
                                        <div class="bid-detail-label">تاريخ الفوز</div>
                                        <div class="bid-detail-value"><?php echo date('Y-m-d', strtotime($bid['created_at'])); ?></div>
                                    </div>
                                </div>
                                <div class="bid-actions">
    <span class="status-badge status-won">فزت</span>
    
    <?php
    // التحقق من وجود تقييم معلق
    $stmt_rating = $conn->prepare("SELECT id FROM ratings WHERE auction_id = ? AND from_user_id = ? AND rating IS NULL");
    $stmt_rating->execute([$bid['auction_id'], $_SESSION['user_id']]);
    $pending_rating = $stmt_rating->fetch();
    ?>
    
    <a href="auction-details.php?id=<?php echo $bid['auction_id']; ?>" class="btn btn-outline">التفاصيل</a>
    
    <?php if ($pending_rating): ?>
        <a href="rate-user.php?id=<?php echo $pending_rating['id']; ?>" class="btn" style="background: #f59e0b; margin-top: 10px;">
            ⭐ قيّم البائع
        </a>
    <?php endif; ?>
</div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script>
        function switchTab(tabName) {
            // إخفاء جميع المحتويات
            document.querySelectorAll('.tab-content').forEach(content => {
                content.classList.remove('active');
            });
            
            // إلغاء تفعيل جميع الأزرار
            document.querySelectorAll('.tab').forEach(tab => {
                tab.classList.remove('active');
            });
            
            // تفعيل المحتوى والزر المطلوب
            document.getElementById(tabName).classList.add('active');
            event.target.classList.add('active');
        }
    </script>
</body>
</html>
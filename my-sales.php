<?php
require_once 'config/database.php';
require_once 'includes/functions.php';

if (!isLoggedIn()) {
    redirect('auth/login.php');
}

$user_id = $_SESSION['user_id'];

// جلب المبيعات
$stmt = $conn->prepare("
    SELECT 
        sc.*,
        a.id as auction_id,
        v.brand, v.model, v.year,
        buyer.username as buyer_name,
        buyer.email as buyer_email
    FROM sales_confirmations sc
    JOIN auctions a ON sc.auction_id = a.id
    JOIN vehicles v ON a.vehicle_id = v.id
    JOIN users buyer ON sc.buyer_id = buyer.id
    WHERE sc.seller_id = ?
    ORDER BY sc.created_at DESC
");
$stmt->execute([$user_id]);
$sales = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>مبيعاتي - مزادات السيارات</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: #f8fafc;
            direction: rtl;
        }
        .header {
            background: linear-gradient(135deg, #2563eb 0%, #1e40af 100%);
            color: white;
            padding: 20px 0;
            box-shadow: 0 4px 15px rgba(37, 99, 235, 0.2);
        }
        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 30px;
        }
        .header h1 {
            display: flex;
            align-items: center;
            gap: 15px;
            font-size: 1.8rem;
        }
        .content {
            padding: 40px 0;
        }
        .back-link {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            color: #2563eb;
            text-decoration: none;
            font-weight: 600;
            margin-bottom: 25px;
            transition: 0.3s;
        }
        .back-link:hover {
            gap: 12px;
        }
        .sales-grid {
            display: grid;
            gap: 25px;
        }
        .sale-card {
            background: white;
            border-radius: 15px;
            padding: 30px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.08);
            border-right: 4px solid;
        }
        .sale-card.pending { border-color: #f59e0b; }
        .sale-card.confirmed { border-color: #10b981; }
        .sale-header {
            display: flex;
            justify-content: space-between;
            align-items: start;
            margin-bottom: 20px;
            padding-bottom: 20px;
            border-bottom: 2px solid #f1f5f9;
        }
        .sale-info h3 {
            color: #1e293b;
            margin-bottom: 8px;
            font-size: 1.3rem;
        }
        .sale-info p {
            color: #64748b;
            font-size: 0.95rem;
        }
        .status-badge {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 8px 16px;
            border-radius: 20px;
            font-weight: 600;
            font-size: 0.9rem;
        }
        .status-badge.pending {
            background: #fef3c7;
            color: #92400e;
        }
        .status-badge.confirmed {
            background: #d1fae5;
            color: #065f46;
        }
        .sale-details {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 20px;
        }
        .detail-item {
            background: #f8fafc;
            padding: 15px;
            border-radius: 10px;
        }
        .detail-label {
            color: #64748b;
            font-size: 0.85rem;
            margin-bottom: 5px;
        }
        .detail-value {
            color: #1e293b;
            font-size: 1.1rem;
            font-weight: 600;
        }
        .btn {
            display: inline-flex;
            align-items: center;
            gap: 10px;
            padding: 12px 24px;
            background: #2563eb;
            color: white;
            text-decoration: none;
            border-radius: 10px;
            font-weight: 600;
            transition: 0.3s;
        }
        .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(37, 99, 235, 0.3);
        }
        .btn-warning {
            background: #f59e0b;
        }
        .btn-success {
            background: #10b981;
        }
        .empty-state {
            text-align: center;
            padding: 80px 20px;
            background: white;
            border-radius: 15px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.08);
        }
        .empty-state i {
            font-size: 5rem;
            color: #cbd5e1;
            margin-bottom: 20px;
        }
        .empty-state h3 {
            color: #64748b;
            margin-bottom: 10px;
        }
    </style>
</head>
<body>
    <div class="header">
        <div class="container">
            <h1>
                <i class="fas fa-hand-holding-usd"></i>
                مبيعاتي
            </h1>
        </div>
    </div>
                    <div style="position: fixed; top: 20px; left: 20px; z-index: 1000;">
        <?php include 'includes/lang-switcher.php'; ?>
    </div>
    <script src="js/auto-translate.js"></script>

    <div class="content">
        <div class="container">
            <a href="dashboard.php" class="back-link">
                <i class="fas fa-arrow-right"></i>
                العودة للوحة التحكم
            </a>

            <?php if (empty($sales)): ?>
                <div class="empty-state">
                    <i class="fas fa-shopping-bag"></i>
                    <h3>لا توجد مبيعات</h3>
                    <p style="color: #94a3b8;">لم تقم ببيع أي سيارة بعد</p>
                </div>
            <?php else: ?>
                <div class="sales-grid">
                    <?php foreach ($sales as $sale): ?>
                        <div class="sale-card <?php echo $sale['status']; ?>">
                            <div class="sale-header">
                                <div class="sale-info">
                                    <h3><?php echo htmlspecialchars($sale['brand'] . ' ' . $sale['model'] . ' ' . $sale['year']); ?></h3>
                                    <p>
                                        <i class="fas fa-user"></i>
                                        المشتري: <?php echo htmlspecialchars($sale['buyer_name']); ?>
                                    </p>
                                </div>
                                <span class="status-badge <?php echo $sale['status']; ?>">
                                    <i class="fas fa-<?php echo $sale['status'] == 'confirmed' ? 'check-circle' : 'clock'; ?>"></i>
                                    <?php echo $sale['status'] == 'confirmed' ? 'مكتمل' : 'قيد الانتظار'; ?>
                                </span>
                            </div>

                            <div class="sale-details">
                                <div class="detail-item">
                                    <div class="detail-label">مبلغ البيع</div>
                                    <div class="detail-value"><?php echo number_format($sale['sale_amount'], 2); ?>$</div>
                                </div>
                                <div class="detail-item">
                                    <div class="detail-label">العمولة</div>
                                    <div class="detail-value" style="color: #dc2626;">-<?php echo number_format($sale['commission_amount'], 2); ?>$</div>
                                </div>
                                <div class="detail-item">
                                    <div class="detail-label">صافي الربح</div>
                                    <div class="detail-value" style="color: #10b981;"><?php echo number_format($sale['seller_net_amount'], 2); ?>$</div>
                                </div>
                                <div class="detail-item">
                                    <div class="detail-label">تاريخ البيع</div>
                                    <div class="detail-value" style="font-size: 0.95rem;"><?php echo date('Y-m-d', strtotime($sale['created_at'])); ?></div>
                                </div>
                            </div>

                            <?php if ($sale['status'] == 'pending'): ?>
                                <div style="background: #fef3c7; padding: 15px; border-radius: 10px; margin-bottom: 15px;">
                                    <p style="color: #92400e; font-size: 0.9rem; margin-bottom: 10px;">
                                        <i class="fas fa-info-circle"></i>
                                        <?php if (!$sale['seller_shipped']): ?>
                                            يرجى تأكيد شحن السيارة للمشتري
                                        <?php elseif (!$sale['buyer_confirmed']): ?>
                                            بانتظار تأكيد المشتري لاستلام السيارة
                                        <?php endif; ?>
                                    </p>
                                </div>
                                <a href="confirm-delivery.php?id=<?php echo $sale['auction_id']; ?>" class="btn btn-warning">
                                    <i class="fas fa-<?php echo $sale['seller_shipped'] ? 'eye' : 'shipping-fast'; ?>"></i>
                                    <?php echo $sale['seller_shipped'] ? 'متابعة الحالة' : 'تأكيد الشحن'; ?>
                                </a>
                            <?php else: ?>
                                <div style="background: #d1fae5; padding: 15px; border-radius: 10px; margin-bottom: 15px;">
                                    <p style="color: #065f46; font-size: 0.9rem;">
                                        <i class="fas fa-check-circle"></i>
                                        تم تأكيد الاستلام وتحرير الرصيد في <?php echo date('Y-m-d', strtotime($sale['confirmation_date'])); ?>
                                    </p>
                                </div>
                                <a href="confirm-delivery.php?id=<?php echo $sale['auction_id']; ?>" class="btn btn-success">
                                    <i class="fas fa-eye"></i>
                                    عرض التفاصيل
                                </a>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>
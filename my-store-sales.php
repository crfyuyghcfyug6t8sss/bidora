<?php
require_once 'config/database.php';
require_once 'includes/functions.php';

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
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <title>مبيعاتي</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Segoe UI', sans-serif; background: #f8fafc; direction: rtl; }
        .header {
            background: linear-gradient(135deg, #10b981 0%, #059669 100%);
            color: white;
            padding: 20px 0;
        }
        .container { max-width: 1200px; margin: 0 auto; padding: 0 30px; }
        .header h1 { font-size: 1.8rem; }
        .content { padding: 40px 0; }
        .back-link {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            color: #10b981;
            text-decoration: none;
            font-weight: 600;
            margin-bottom: 25px;
        }
        .sale-card {
            background: white;
            border-radius: 15px;
            padding: 25px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.08);
            margin-bottom: 20px;
            display: grid;
            grid-template-columns: 120px 1fr auto;
            gap: 20px;
        }
        .product-img {
            width: 120px;
            height: 120px;
            border-radius: 12px;
            overflow: hidden;
            background: #f1f5f9;
        }
        .product-img img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        .sale-info h3 {
            color: #1e293b;
            margin-bottom: 10px;
        }
        .sale-meta {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 15px;
            margin: 15px 0;
        }
        .meta-item {
            background: #f8fafc;
            padding: 10px;
            border-radius: 8px;
        }
        .meta-label {
            font-size: 0.75rem;
            color: #64748b;
        }
        .meta-value {
            font-weight: 600;
            color: #1e293b;
        }
        .btn {
            padding: 10px 20px;
            background: #10b981;
            color: white;
            text-decoration: none;
            border-radius: 8px;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }
    </style>
</head>
<body>
    <div class="header">
        <div class="container">
            <h1><i class="fas fa-hand-holding-usd"></i> مبيعاتي من المتجر</h1>
        </div>
    </div>

    <div class="content">
        <div class="container">
            <a href="dashboard.php" class="back-link">
                <i class="fas fa-arrow-right"></i>
                العودة
            </a>
                    <div style="position: fixed; top: 20px; left: 20px; z-index: 1000;">
        <?php include 'includes/lang-switcher.php'; ?>
    </div>
    <script src="js/auto-translate.js"></script>

            <?php if (empty($sales)): ?>
                <div style="text-align: center; padding: 80px; background: white; border-radius: 15px;">
                    <i class="fas fa-box-open" style="font-size: 5rem; color: #cbd5e1; margin-bottom: 20px;"></i>
                    <h3>لا توجد مبيعات</h3>
                </div>
            <?php else: ?>
                <?php foreach ($sales as $sale): ?>
                    <div class="sale-card">
                        <div class="product-img">
                            <?php if ($sale['product_image']): ?>
                                <img src="<?php echo $sale['product_image']; ?>" alt="">
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
                                    <div class="meta-value" style="color: #059669;"><?php echo number_format($sale['seller_net_amount'], 2); ?>$</div>
                                </div>
                            </div>
                        </div>
                        <div>
                            <a href="store-order-chat.php?order_id=<?php echo $sale['id']; ?>" class="btn">
                                <i class="fas fa-comments"></i>
                                المحادثة
                            </a>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>
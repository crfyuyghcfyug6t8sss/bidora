<?php
require_once 'config/database.php';
require_once 'includes/functions.php';

if (!isLoggedIn()) {
    redirect('auth/login.php');
}

$user_id = $_SESSION['user_id'];

// جلب مشتريات المستخدم
$stmt = $conn->prepare("
    SELECT 
        o.*,
        p.title as product_title,
        p.id as product_id,
        seller.username as seller_name,
        seller.id as seller_id,
        (SELECT image_path FROM store_product_images WHERE product_id = p.id AND is_primary = TRUE LIMIT 1) as product_image
    FROM store_orders o
    JOIN store_products p ON o.product_id = p.id
    JOIN users seller ON o.seller_id = seller.id
    WHERE o.buyer_id = ?
    ORDER BY o.created_at DESC
");
$stmt->execute([$user_id]);
$purchases = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <title>مشترياتي</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Segoe UI', sans-serif; background: #f8fafc; direction: rtl; }
        .header {
            background: linear-gradient(135deg, #10b981 0%, #059669 100%);
            color: white;
            padding: 20px 0;
            box-shadow: 0 4px 15px rgba(16, 185, 129, 0.2);
        }
        .container { max-width: 1200px; margin: 0 auto; padding: 0 30px; }
        .header h1 { display: flex; align-items: center; gap: 15px; font-size: 1.8rem; }
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
        .purchase-card {
            background: white;
            border-radius: 15px;
            padding: 25px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.08);
            margin-bottom: 20px;
            display: grid;
            grid-template-columns: 120px 1fr auto;
            gap: 20px;
            align-items: center;
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
        .purchase-info h3 {
            color: #1e293b;
            margin-bottom: 10px;
        }
        .purchase-meta {
            display: flex;
            gap: 20px;
            color: #64748b;
            font-size: 0.9rem;
            margin-bottom: 10px;
        }
        .status-badge {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 6px 14px;
            border-radius: 20px;
            font-size: 0.85rem;
            font-weight: 600;
        }
        .status-pending { background: #fef3c7; color: #92400e; }
        .status-completed { background: #d1fae5; color: #065f46; }
        .btn {
            padding: 10px 20px;
            background: #10b981;
            color: white;
            text-decoration: none;
            border-radius: 8px;
            font-weight: 600;
            transition: 0.3s;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }
        .empty-state {
            text-align: center;
            padding: 80px 20px;
            background: white;
            border-radius: 15px;
        }
        .empty-state i {
            font-size: 5rem;
            color: #cbd5e1;
            margin-bottom: 20px;
        }
    </style>
</head>
<body>
    <div class="header">
        <div class="container">
            <h1><i class="fas fa-shopping-bag"></i> مشترياتي</h1>
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

            <?php if (empty($purchases)): ?>
                <div class="empty-state">
                    <i class="fas fa-shopping-cart"></i>
                    <h3>لا توجد مشتريات</h3>
                </div>
            <?php else: ?>
                <?php foreach ($purchases as $purchase): ?>
                    <div class="purchase-card">
                        <div class="product-img">
                            <?php if ($purchase['product_image']): ?>
                                <img src="<?php echo htmlspecialchars($purchase['product_image']); ?>" alt="">
                            <?php else: ?>
                                <div style="display: flex; align-items: center; justify-content: center; height: 100%; color: #cbd5e1; font-size: 2rem;">
                                    <i class="fas fa-image"></i>
                                </div>
                            <?php endif; ?>
                        </div>
                        <div class="purchase-info">
                            <h3><?php echo htmlspecialchars($purchase['product_title']); ?></h3>
                            <div class="purchase-meta">
                                <span><i class="fas fa-user"></i> البائع: <?php echo htmlspecialchars($purchase['seller_name']); ?></span>
                                <span><i class="fas fa-dollar-sign"></i> <?php echo number_format($purchase['price'], 2); ?>$</span>
                                <span><i class="fas fa-calendar"></i> <?php echo date('Y-m-d', strtotime($purchase['created_at'])); ?></span>
                            </div>
                            <span class="status-badge <?php echo $purchase['status'] == 'completed' ? 'status-completed' : 'status-pending'; ?>">
                                <i class="fas fa-<?php echo $purchase['status'] == 'completed' ? 'check-circle' : 'clock'; ?>"></i>
                                <?php echo $purchase['status'] == 'completed' ? 'مكتمل' : 'جاري التنفيذ'; ?>
                            </span>
                        </div>
                        <div>
                            <a href="store-order-chat.php?order_id=<?php echo $purchase['id']; ?>" class="btn">
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
<?php
require_once 'config/database.php';
require_once 'includes/functions.php';

$product_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if (!$product_id) {
    redirect('store.php');
}

// جلب تفاصيل المنتج
$stmt = $conn->prepare("
    SELECT 
        p.*,
        c.name as category_name,
        c.icon as category_icon,
        u.username as seller_name,
        u.id as seller_id,
        u.phone as seller_phone,
        u.email as seller_email
    FROM store_products p
    JOIN store_categories c ON p.category_id = c.id
    JOIN users u ON p.seller_id = u.id
    WHERE p.id = ?
");
$stmt->execute([$product_id]);
$product = $stmt->fetch();

if (!$product) {
    redirect('store.php');
}

// تحديث المشاهدات
$stmt = $conn->prepare("UPDATE store_products SET views = views + 1 WHERE id = ?");
$stmt->execute([$product_id]);

// جلب الصور
$stmt = $conn->prepare("SELECT * FROM store_product_images WHERE product_id = ? ORDER BY is_primary DESC, display_order");
$stmt->execute([$product_id]);
$images = $stmt->fetchAll();

// جلب تقييم البائع
$stmt = $conn->prepare("
    SELECT AVG(rating) as avg_rating, COUNT(*) as total_ratings 
    FROM ratings 
    WHERE to_user_id = ? AND rating IS NOT NULL
");
$stmt->execute([$product['seller_id']]);
$seller_rating = $stmt->fetch();

// معالجة الشراء
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['buy_now'])) {
    if (!isLoggedIn()) {
        setMessage('يجب تسجيل الدخول للشراء', 'danger');
        redirect('auth/login.php');
    }
    
    $buyer_id = $_SESSION['user_id'];
    
    if ($buyer_id == $product['seller_id']) {
        setMessage('لا يمكنك شراء منتجك الخاص', 'danger');
    } else {
        // التحقق من الرصيد
        $stmt = $conn->prepare("SELECT * FROM wallet WHERE user_id = ?");
        $stmt->execute([$buyer_id]);
        $wallet = $stmt->fetch();
        
        if ($wallet['available_balance'] < $product['price']) {
            setMessage('رصيدك غير كافٍ. رصيدك المتاح: ' . number_format($wallet['available_balance'], 2) . '$', 'danger');
        } else {
            try {
                $conn->beginTransaction();
                
                // حساب العمولة
// حساب العمولة من جدول عمولات المتجر
$stmt = $conn->prepare("
    SELECT commission_rate 
    FROM store_commission_tiers 
    WHERE ? BETWEEN min_price AND max_price 
    LIMIT 1
");
$stmt->execute([$product['price']]);
$tier = $stmt->fetch();

if ($tier) {
    $commission_rate = $tier['commission_rate'] / 100;
} else {
    $commission_rate = 0.05; // نسبة افتراضية 5%
}                $commission = $product['price'] * $commission_rate;
                $seller_net = $product['price'] - $commission;
                
                // خصم من المشتري
                $stmt = $conn->prepare("UPDATE wallet SET available_balance = available_balance - ? WHERE user_id = ?");
                $stmt->execute([$product['price'], $buyer_id]);
                
                // حجز للبائع
                $stmt = $conn->prepare("UPDATE wallet SET sales_hold_balance = sales_hold_balance + ? WHERE user_id = ?");
                $stmt->execute([$seller_net, $product['seller_id']]);
                
                // تسجيل الطلب
                $stmt = $conn->prepare("
                    INSERT INTO store_orders 
                    (product_id, buyer_id, seller_id, price, commission_amount, seller_net_amount, status) 
                    VALUES (?, ?, ?, ?, ?, ?, 'paid')
                ");
                $stmt->execute([$product_id, $buyer_id, $product['seller_id'], $product['price'], $commission, $seller_net]);
                
                // تحديث المنتج
                $stmt = $conn->prepare("UPDATE store_products SET status = 'sold', quantity = quantity - 1 WHERE id = ?");
                $stmt->execute([$product_id]);
                
                // تسجيل المعاملات
                logTransaction($buyer_id, 'purchase', $product['price'], $product_id, 'store', 'شراء منتج من المتجر');
                logTransaction($product['seller_id'], 'sales_hold', $seller_net, $product_id, 'store', 'رصيد محجوز - بانتظار التأكيد');
                
                // إشعارات
                sendNotification($product['seller_id'], 'طلب شراء جديد', 'قام أحد المشترين بشراء منتجك', 'system', $product_id);
                
                $conn->commit();
                
                setMessage('تم الشراء بنجاح! سيتم تحرير الرصيد للبائع عند تأكيد الاستلام', 'success');
                redirect('my-purchases.php');
                
            } catch(Exception $e) {
                $conn->rollBack();
                setMessage('حدث خطأ: ' . $e->getMessage(), 'danger');
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($product['title']); ?></title>
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
        .header h1 { font-size: 1.5rem; }
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
        
        .product-layout {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 40px;
            background: white;
            border-radius: 15px;
            padding: 40px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.08);
        }
        
        .gallery {
            position: sticky;
            top: 20px;
        }
        .main-image {
            width: 100%;
            height: 450px;
            border-radius: 12px;
            overflow: hidden;
            margin-bottom: 20px;
        }
        .main-image img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        .thumbnails {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(80px, 1fr));
            gap: 10px;
        }
        .thumbnail {
            width: 100%;
            height: 80px;
            border-radius: 8px;
            overflow: hidden;
            cursor: pointer;
            border: 2px solid #e2e8f0;
            transition: 0.3s;
        }
        .thumbnail:hover,
        .thumbnail.active {
            border-color: #10b981;
        }
        .thumbnail img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        
        .product-info h1 {
            font-size: 2rem;
            color: #1e293b;
            margin-bottom: 15px;
        }
        .category-badge {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 6px 14px;
            background: #e0f2fe;
            color: #0369a1;
            border-radius: 20px;
            font-size: 0.9rem;
            font-weight: 600;
            margin-bottom: 20px;
        }
        .price-box {
            background: linear-gradient(135deg, #10b981 0%, #059669 100%);
            color: white;
            padding: 25px;
            border-radius: 12px;
            margin: 25px 0;
        }
        .price {
            font-size: 2.5rem;
            font-weight: bold;
        }
        .stock-info {
            display: flex;
            gap: 20px;
            margin: 20px 0;
            padding: 20px;
            background: #f8fafc;
            border-radius: 10px;
        }
        .stock-item {
            flex: 1;
        }
        .stock-item label {
            display: block;
            color: #64748b;
            font-size: 0.85rem;
            margin-bottom: 5px;
        }
        .stock-item .value {
            color: #1e293b;
            font-weight: 600;
            font-size: 1.1rem;
        }
        .description {
            margin: 30px 0;
            padding: 25px;
            background: #f8fafc;
            border-radius: 12px;
        }
        .description h3 {
            color: #1e293b;
            margin-bottom: 15px;
        }
        .description p {
            color: #475569;
            line-height: 1.8;
        }
        
        .seller-box {
            padding: 25px;
            background: #f8fafc;
            border-radius: 12px;
            margin: 25px 0;
        }
        .seller-box h4 {
            color: #1e293b;
            margin-bottom: 15px;
        }
        .seller-info {
            display: flex;
            align-items: center;
            gap: 15px;
            margin-bottom: 15px;
        }
        .seller-avatar {
            width: 60px;
            height: 60px;
            background: linear-gradient(135deg, #10b981 0%, #059669 100%);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 1.5rem;
            font-weight: bold;
        }
        .rating {
            color: #fbbf24;
            font-size: 1.1rem;
        }
        
        .btn {
            width: 100%;
            padding: 18px;
            background: linear-gradient(135deg, #10b981 0%, #059669 100%);
            color: white;
            border: none;
            border-radius: 12px;
            font-size: 1.2rem;
            font-weight: bold;
            cursor: pointer;
            transition: 0.3s;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
        }
        .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(16, 185, 129, 0.3);
        }
        .btn:disabled {
            background: #cbd5e1;
            cursor: not-allowed;
        }
        
        @media (max-width: 768px) {
            .product-layout {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <div class="header">
        <div class="container">
            <h1><i class="fas fa-store"></i> المتجر</h1>
        </div>
    </div>
                    <div style="position: fixed; top: 20px; left: 20px; z-index: 1000;">
        <?php include 'includes/lang-switcher.php'; ?>
    </div>
    <script src="js/auto-translate.js"></script>

    <div class="content">
        <div class="container">
            <a href="store.php" class="back-link">
                <i class="fas fa-arrow-right"></i>
                العودة للمتجر
            </a>

            <div class="product-layout">
                <div class="gallery">
                    <div class="main-image" id="mainImage">
                        <?php if (!empty($images)): ?>
                            <img src="<?php echo htmlspecialchars($images[0]['image_path']); ?>" alt="">
                        <?php else: ?>
                            <img src="https://via.placeholder.com/600x450?text=No+Image" alt="">
                        <?php endif; ?>
                    </div>
                    <?php if (count($images) > 1): ?>
                    <div class="thumbnails">
                        <?php foreach ($images as $index => $img): ?>
                        <div class="thumbnail <?php echo $index == 0 ? 'active' : ''; ?>" 
                             onclick="changeImage('<?php echo htmlspecialchars($img['image_path']); ?>', this)">
                            <img src="<?php echo htmlspecialchars($img['image_path']); ?>" alt="">
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <?php endif; ?>
                </div>

                <div class="product-info">
                    <h1><?php echo htmlspecialchars($product['title']); ?></h1>
                    
                    <span class="category-badge">
                        <i class="fas <?php echo $product['category_icon']; ?>"></i>
                        <?php echo htmlspecialchars($product['category_name']); ?>
                    </span>

                    <div class="price-box">
                        <div style="opacity: 0.9; margin-bottom: 5px;">السعر</div>
                        <div class="price"><?php echo number_format($product['price'], 2); ?> $</div>
                    </div>

                    <div class="stock-info">
                        <div class="stock-item">
                            <label>الحالة</label>
                            <div class="value">
                                <?php 
                                $conditions = ['new' => 'جديد', 'used' => 'مستعمل', 'refurbished' => 'مجدد'];
                                echo $conditions[$product['condition_type']];
                                ?>
                            </div>
                        </div>
                        <div class="stock-item">
                            <label>الكمية المتاحة</label>
                            <div class="value"><?php echo $product['quantity']; ?></div>
                        </div>
                        <div class="stock-item">
                            <label>المشاهدات</label>
                            <div class="value"><?php echo $product['views']; ?></div>
                        </div>
                    </div>

                    <?php if ($product['description']): ?>
                    <div class="description">
                        <h3>الوصف</h3>
                        <p><?php echo nl2br(htmlspecialchars($product['description'])); ?></p>
                    </div>
                    <?php endif; ?>

                    <div class="seller-box">
                        <h4>معلومات البائع</h4>
                        <div class="seller-info">
                            <div class="seller-avatar">
                                <?php echo strtoupper(substr($product['seller_name'], 0, 1)); ?>
                            </div>
                            <div>
                                <div style="font-weight: 600; color: #1e293b; margin-bottom: 5px;">
                                    <?php echo htmlspecialchars($product['seller_name']); ?>
                                </div>
                                <?php if ($seller_rating['total_ratings'] > 0): ?>
                                <div class="rating">
                                    <?php 
                                    $rating = round($seller_rating['avg_rating'], 1);
                                    echo str_repeat('★', floor($rating));
                                    echo str_repeat('☆', 5 - floor($rating));
                                    ?>
                                    <span style="color: #64748b; font-size: 0.9rem;">
                                        (<?php echo $rating; ?>/5)
                                    </span>
                                </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>

                    <form method="POST">
                        <?php if ($product['status'] == 'sold'): ?>
                            <button type="button" class="btn" disabled>
                                <i class="fas fa-times-circle"></i>
                                تم البيع
                            </button>
                        <?php elseif (isLoggedIn() && $_SESSION['user_id'] == $product['seller_id']): ?>
                            <button type="button" class="btn" disabled>
                                <i class="fas fa-info-circle"></i>
                                هذا منتجك
                            </button>
                        <?php elseif (isLoggedIn()): ?>
                            <button type="submit" name="buy_now" class="btn">
                                <i class="fas fa-shopping-cart"></i>
                                اشتري الآن
                            </button>
                        <?php else: ?>
                            <a href="auth/login.php" class="btn" style="text-decoration: none;">
                                <i class="fas fa-sign-in-alt"></i>
                                سجل دخول للشراء
                            </a>
                        <?php endif; ?>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <script>
        function changeImage(src, thumb) {
            document.querySelector('#mainImage img').src = src;
            document.querySelectorAll('.thumbnail').forEach(t => t.classList.remove('active'));
            thumb.classList.add('active');
        }
    </script>
</body>
</html>
<?php
require_once 'config/database.php';
require_once 'includes/functions.php';

// جلب التصنيفات
$stmt = $conn->query("SELECT * FROM store_categories WHERE is_active = TRUE ORDER BY display_order");
$categories = $stmt->fetchAll();

// الفلترة
$category_id = isset($_GET['category']) ? (int)$_GET['category'] : 0;
$search = isset($_GET['search']) ? clean($_GET['search']) : '';
$condition = isset($_GET['condition']) ? clean($_GET['condition']) : '';
$min_price = isset($_GET['min_price']) ? (float)$_GET['min_price'] : 0;
$max_price = isset($_GET['max_price']) ? (float)$_GET['max_price'] : 999999;

// بناء الاستعلام
$sql = "
    SELECT 
        p.*,
        c.name as category_name,
        c.icon as category_icon,
        u.username as seller_name,
        (SELECT image_path FROM store_product_images WHERE product_id = p.id AND is_primary = TRUE LIMIT 1) as main_image,
        (SELECT COUNT(*) FROM store_product_images WHERE product_id = p.id) as images_count
    FROM store_products p
    JOIN store_categories c ON p.category_id = c.id
    JOIN users u ON p.seller_id = u.id
    WHERE p.status = 'active'
";

$params = [];

if ($category_id > 0) {
    $sql .= " AND p.category_id = ?";
    $params[] = $category_id;
}

if ($search) {
    $sql .= " AND (p.title LIKE ? OR p.description LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

if ($condition) {
    $sql .= " AND p.condition_type = ?";
    $params[] = $condition;
}

$sql .= " AND p.price BETWEEN ? AND ?";
$params[] = $min_price;
$params[] = $max_price;

$sql .= " ORDER BY p.featured DESC, p.created_at DESC";

$stmt = $conn->prepare($sql);
$stmt->execute($params);
$products = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>المتجر - البيع المباشر</title>
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
        .container { max-width: 1400px; margin: 0 auto; padding: 0 30px; }
        .header h1 { display: flex; align-items: center; gap: 15px; font-size: 1.8rem; }
        .header-nav { display: flex; gap: 20px; margin-top: 15px; }
        .header-nav a { color: white; text-decoration: none; padding: 8px 15px; border-radius: 8px; transition: 0.3s; }
        .header-nav a:hover { background: rgba(255,255,255,0.2); }
        
        .content { padding: 40px 0; }
        
        .categories-bar {
            background: white;
            padding: 20px 0;
            margin-bottom: 30px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
        }
        .categories-scroll {
            display: flex;
            gap: 15px;
            overflow-x: auto;
            padding: 10px 0;
        }
        .category-chip {
            display: flex;
            align-items: center;
            gap: 8px;
            padding: 12px 20px;
            background: #f1f5f9;
            border: 2px solid #e2e8f0;
            border-radius: 25px;
            text-decoration: none;
            color: #475569;
            white-space: nowrap;
            transition: 0.3s;
            cursor: pointer;
        }
        .category-chip:hover { background: #e2e8f0; }
        .category-chip.active { background: #10b981; color: white; border-color: #10b981; }
        
        .filters-bar {
            background: white;
            padding: 20px;
            border-radius: 12px;
            margin-bottom: 30px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
        }
        .filters-grid { display: grid; grid-template-columns: 2fr 1fr 1fr 1fr auto; gap: 15px; align-items: end; }
        .filter-group label { display: block; margin-bottom: 8px; font-weight: 600; color: #475569; font-size: 0.9rem; }
        .filter-group input, .filter-group select {
            width: 100%;
            padding: 12px;
            border: 2px solid #e2e8f0;
            border-radius: 8px;
            font-size: 1rem;
        }
        .btn-filter {
            padding: 12px 30px;
            background: #10b981;
            color: white;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-weight: 600;
            transition: 0.3s;
        }
        .btn-filter:hover { background: #059669; }
        
        .products-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
            gap: 25px;
        }
        .product-card {
            background: white;
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 2px 10px rgba(0,0,0,0.08);
            transition: 0.3s;
            text-decoration: none;
            color: inherit;
            display: block;
        }
        .product-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 25px rgba(0,0,0,0.15);
        }
        .product-image {
            width: 100%;
            height: 200px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 4rem;
            color: white;
            position: relative;
        }
        .product-image img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        .product-badge {
            position: absolute;
            top: 10px;
            right: 10px;
            padding: 5px 12px;
            border-radius: 15px;
            font-size: 0.8rem;
            font-weight: 600;
        }
        .badge-featured { background: #f59e0b; color: white; }
        .badge-new { background: #10b981; color: white; }
        .product-content { padding: 20px; }
        .product-category {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            font-size: 0.85rem;
            color: #10b981;
            margin-bottom: 10px;
        }
        .product-title {
            font-size: 1.1rem;
            font-weight: 600;
            color: #1e293b;
            margin-bottom: 10px;
        }
        .product-price {
            font-size: 1.5rem;
            font-weight: bold;
            color: #10b981;
            margin-bottom: 15px;
        }
        .product-meta {
            display: flex;
            justify-content: space-between;
            font-size: 0.85rem;
            color: #64748b;
            padding-top: 15px;
            border-top: 1px solid #f1f5f9;
        }
        .empty-state {
            text-align: center;
            padding: 80px 20px;
            background: white;
            border-radius: 12px;
        }
        .empty-state i { font-size: 5rem; color: #cbd5e1; margin-bottom: 20px; }
        
        @media (max-width: 768px) {
            .filters-grid { grid-template-columns: 1fr; }
            .products-grid { grid-template-columns: repeat(auto-fill, minmax(250px, 1fr)); }
        }
    </style>
</head>
<body>
    <div class="header">
        <div class="container">
                                <div style="position: fixed; top: 20px; left: 20px; z-index: 1000;">
        <?php include 'includes/lang-switcher.php'; ?>
    </div>
    <script src="js/auto-translate.js"></script>

            <h1><i class="fas fa-store"></i> المتجر - البيع المباشر</h1>
            <div class="header-nav">
                <a href="index.php"><i class="fas fa-home"></i> الرئيسية</a>
                <a href="auctions.php"><i class="fas fa-gavel"></i> المزادات</a>
                <a href="add-product.php"><i class="fas fa-plus-circle"></i> أضف منتج</a>
                <?php if (isLoggedIn()): ?>
                    <a href="dashboard.php"><i class="fas fa-tachometer-alt"></i> لوحة التحكم</a>
                <?php else: ?>
                    <a href="auth/login.php"><i class="fas fa-sign-in-alt"></i> تسجيل الدخول</a>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div class="categories-bar">
        <div class="container">
            <div class="categories-scroll">
                <a href="store.php" class="category-chip <?php echo $category_id == 0 ? 'active' : ''; ?>">
                    <i class="fas fa-th"></i> الكل
                </a>
                <?php foreach ($categories as $cat): ?>
                    <a href="store.php?category=<?php echo $cat['id']; ?>" 
                       class="category-chip <?php echo $category_id == $cat['id'] ? 'active' : ''; ?>">
                        <i class="fas <?php echo $cat['icon']; ?>"></i>
                        <?php echo htmlspecialchars($cat['name']); ?>
                    </a>
                <?php endforeach; ?>
            </div>
        </div>
    </div>

    <div class="content">
        <div class="container">
            <form method="GET" class="filters-bar">
                <input type="hidden" name="category" value="<?php echo $category_id; ?>">
                <div class="filters-grid">
                    <div class="filter-group">
                        <label>البحث</label>
                        <input type="text" name="search" placeholder="ابحث عن منتج..." value="<?php echo htmlspecialchars($search); ?>">
                    </div>
                    <div class="filter-group">
                        <label>الحالة</label>
                        <select name="condition">
                            <option value="">الكل</option>
                            <option value="new" <?php echo $condition == 'new' ? 'selected' : ''; ?>>جديد</option>
                            <option value="used" <?php echo $condition == 'used' ? 'selected' : ''; ?>>مستعمل</option>
                            <option value="refurbished" <?php echo $condition == 'refurbished' ? 'selected' : ''; ?>>مجدد</option>
                        </select>
                    </div>
                    <div class="filter-group">
                        <label>من</label>
                        <input type="number" name="min_price" placeholder="0" value="<?php echo $min_price > 0 ? $min_price : ''; ?>">
                    </div>
                    <div class="filter-group">
                        <label>إلى</label>
                        <input type="number" name="max_price" placeholder="9999" value="<?php echo $max_price < 999999 ? $max_price : ''; ?>">
                    </div>
                    <button type="submit" class="btn-filter">
                        <i class="fas fa-search"></i> بحث
                    </button>
                </div>
            </form>

            <?php if (empty($products)): ?>
                <div class="empty-state">
                    <i class="fas fa-box-open"></i>
                    <h3>لا توجد منتجات</h3>
                    <p style="color: #94a3b8;">جرب تغيير الفلاتر</p>
                </div>
            <?php else: ?>
                <div class="products-grid">
                    <?php foreach ($products as $product): ?>
                        <a href="product-details.php?id=<?php echo $product['id']; ?>" class="product-card">
                            <div class="product-image">
                                <?php if ($product['main_image']): ?>
                                    <img src="<?php echo htmlspecialchars($product['main_image']); ?>" alt="">
                                <?php else: ?>
                                    <i class="fas fa-image"></i>
                                <?php endif; ?>
                                <?php if ($product['featured']): ?>
                                    <span class="product-badge badge-featured">مميز</span>
                                <?php elseif ($product['condition_type'] == 'new'): ?>
                                    <span class="product-badge badge-new">جديد</span>
                                <?php endif; ?>
                            </div>
                            <div class="product-content">
                                <div class="product-category">
                                    <i class="fas <?php echo $product['category_icon']; ?>"></i>
                                    <?php echo htmlspecialchars($product['category_name']); ?>
                                </div>
                                <div class="product-title"><?php echo htmlspecialchars($product['title']); ?></div>
                                <div class="product-price"><?php echo number_format($product['price'], 2); ?> $</div>
                                <div class="product-meta">
                                    <span><i class="fas fa-user"></i> <?php echo htmlspecialchars($product['seller_name']); ?></span>
                                    <span><i class="fas fa-eye"></i> <?php echo $product['views']; ?></span>
                                </div>
                            </div>
                        </a>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>
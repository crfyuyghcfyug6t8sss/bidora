<?php
require_once 'config/database.php';
require_once 'includes/functions.php';

if (!isLoggedIn()) {
    redirect('auth/login.php');
}

$user_id = $_SESSION['user_id'];

// التحقق من KYC
$stmt = $conn->prepare("SELECT kyc_status FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$user = $stmt->fetch();

if ($user['kyc_status'] != 'verified') {
    setMessage('يجب التحقق من هويتك أولاً لإضافة منتجات', 'warning');
    redirect('kyc/submit.php');
}

// جلب التصنيفات
$stmt = $conn->query("SELECT * FROM store_categories WHERE is_active = TRUE ORDER BY display_order");
$categories = $stmt->fetchAll();

$errors = [];
$success = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $category_id = clean($_POST['category_id']);
    $title = clean($_POST['title']);
    $description = clean($_POST['description']);
    $price = clean($_POST['price']);
    $quantity = clean($_POST['quantity']);
    $condition_type = clean($_POST['condition_type']);
    
    // التحقق
    if (empty($category_id) || $category_id < 1) {
        $errors[] = 'يجب اختيار التصنيف';
    }
    if (empty($title) || strlen($title) < 5) {
        $errors[] = 'العنوان يجب أن يكون 5 أحرف على الأقل';
    }
    if (empty($price) || $price <= 0) {
        $errors[] = 'السعر يجب أن يكون أكبر من صفر';
    }
    if (empty($quantity) || $quantity < 1) {
        $errors[] = 'الكمية يجب أن تكون 1 على الأقل';
    }
    
    // الصور
    $uploaded_images = [];
    if (isset($_FILES['images']['name'])) {
        $total_images = count($_FILES['images']['name']);
        
        if ($total_images < 1) {
            $errors[] = 'يجب رفع صورة واحدة على الأقل';
        }
        
        if ($total_images > 10) {
            $errors[] = 'الحد الأقصى 10 صور';
        }
        
        if (empty($errors)) {
            $upload_dir = 'uploads/products/';
            if (!is_dir($upload_dir)) {
                mkdir($upload_dir, 0755, true);
            }
            
            for ($i = 0; $i < $total_images; $i++) {
                if ($_FILES['images']['error'][$i] == 0) {
                    $file_tmp = $_FILES['images']['tmp_name'][$i];
                    $file_name = $_FILES['images']['name'][$i];
                    $file_size = $_FILES['images']['size'][$i];
                    
                    $ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
                    $allowed = ['jpg', 'jpeg', 'png', 'webp'];
                    
                    if (!in_array($ext, $allowed)) {
                        $errors[] = 'نوع الملف غير مسموح (فقط: jpg, png, webp)';
                        break;
                    }
                    
                    if ($file_size > 5000000) {
                        $errors[] = 'حجم الصورة كبير جداً (الحد الأقصى: 5MB)';
                        break;
                    }
                    
                    $new_name = uniqid() . '_' . time() . '.' . $ext;
                    $destination = $upload_dir . $new_name;
                    
                    if (move_uploaded_file($file_tmp, $destination)) {
                        $uploaded_images[] = [
                            'path' => $destination,
                            'is_primary' => ($i == 0)
                        ];
                    }
                }
            }
        }
    } else {
        $errors[] = 'يجب رفع صورة واحدة على الأقل';
    }
    
    if (empty($errors)) {
        try {
            $conn->beginTransaction();
            
            // إضافة المنتج
            $stmt = $conn->prepare("
                INSERT INTO store_products 
                (category_id, seller_id, title, description, price, quantity, condition_type, status) 
                VALUES (?, ?, ?, ?, ?, ?, ?, 'active')
            ");
            $stmt->execute([$category_id, $user_id, $title, $description, $price, $quantity, $condition_type]);
            $product_id = $conn->lastInsertId();
            
            // إضافة الصور
            foreach ($uploaded_images as $index => $image) {
                $stmt = $conn->prepare("
                    INSERT INTO store_product_images 
                    (product_id, image_path, is_primary, display_order) 
                    VALUES (?, ?, ?, ?)
                ");
                $stmt->execute([$product_id, $image['path'], $image['is_primary'], $index]);
            }
            
            $conn->commit();
            
            setMessage('تم إضافة المنتج بنجاح!', 'success');
            redirect('product-details.php?id=' . $product_id);
            
        } catch(Exception $e) {
            $conn->rollBack();
            $errors[] = 'حدث خطأ: ' . $e->getMessage();
            
            // حذف الصور المرفوعة
            foreach ($uploaded_images as $img) {
                if (file_exists($img['path'])) {
                    unlink($img['path']);
                }
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
    <title>إضافة منتج جديد</title>
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
        .container { max-width: 900px; margin: 0 auto; padding: 0 30px; }
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
            transition: 0.3s;
        }
        .back-link:hover { gap: 12px; }
        
        .form-card {
            background: white;
            border-radius: 15px;
            padding: 40px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.08);
        }
        .form-group {
            margin-bottom: 25px;
        }
        .form-group label {
            display: block;
            margin-bottom: 10px;
            font-weight: 600;
            color: #1e293b;
            font-size: 1rem;
        }
        .form-group input,
        .form-group select,
        .form-group textarea {
            width: 100%;
            padding: 14px;
            border: 2px solid #e2e8f0;
            border-radius: 10px;
            font-size: 1rem;
            transition: 0.3s;
        }
        .form-group input:focus,
        .form-group select:focus,
        .form-group textarea:focus {
            outline: none;
            border-color: #10b981;
            box-shadow: 0 0 0 3px rgba(16, 185, 129, 0.1);
        }
        .form-group textarea {
            min-height: 120px;
            resize: vertical;
        }
        .form-row {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
        }
        
        .image-upload {
            border: 3px dashed #cbd5e1;
            border-radius: 12px;
            padding: 40px;
            text-align: center;
            cursor: pointer;
            transition: 0.3s;
            background: #f8fafc;
        }
        .image-upload:hover {
            border-color: #10b981;
            background: #f1f5f9;
        }
        .image-upload input {
            display: none;
        }
        .upload-icon {
            font-size: 3rem;
            color: #94a3b8;
            margin-bottom: 15px;
        }
        .image-preview {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(120px, 1fr));
            gap: 15px;
            margin-top: 20px;
        }
        .preview-item {
            position: relative;
            border-radius: 10px;
            overflow: hidden;
        }
        .preview-item img {
            width: 100%;
            height: 120px;
            object-fit: cover;
        }
        .remove-image {
            position: absolute;
            top: 5px;
            right: 5px;
            background: #ef4444;
            color: white;
            border: none;
            width: 30px;
            height: 30px;
            border-radius: 50%;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .btn {
            width: 100%;
            padding: 16px;
            background: linear-gradient(135deg, #10b981 0%, #059669 100%);
            color: white;
            border: none;
            border-radius: 12px;
            font-size: 1.1rem;
            font-weight: bold;
            cursor: pointer;
            transition: 0.3s;
        }
        .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(16, 185, 129, 0.3);
        }
        
        .alert {
            padding: 15px 20px;
            border-radius: 12px;
            margin-bottom: 25px;
        }
        .alert-danger {
            background: #fee2e2;
            color: #991b1b;
            border-right: 4px solid #dc2626;
        }
    </style>
</head>
<body>
    
    <div class="header">
        <div class="container">
            <h1><i class="fas fa-plus-circle"></i> إضافة منتج جديد</h1>
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

            <?php if (!empty($errors)): ?>
                <div class="alert alert-danger">
                    <ul style="margin: 0; padding-right: 20px;">
                        <?php foreach ($errors as $error): ?>
                            <li><?php echo $error; ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>

            <form method="POST" enctype="multipart/form-data" class="form-card">
                <div class="form-group">
                    <label><i class="fas fa-tag"></i> التصنيف *</label>
                    <select name="category_id" required>
                        <option value="">اختر التصنيف</option>
                        <?php foreach ($categories as $cat): ?>
                            <option value="<?php echo $cat['id']; ?>">
                                <?php echo htmlspecialchars($cat['name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label><i class="fas fa-heading"></i> عنوان المنتج *</label>
                    <input type="text" name="title" required placeholder="مثال: قطعة غيار BMW X5">
                </div>

                <div class="form-group">
                    <label><i class="fas fa-align-right"></i> الوصف</label>
                    <textarea name="description" placeholder="اكتب وصف تفصيلي للمنتج..."></textarea>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label><i class="fas fa-dollar-sign"></i> السعر ($) *</label>
                        <input type="number" name="price" step="0.01" min="0" required placeholder="0.00">
                    </div>

                    <div class="form-group">
                        <label><i class="fas fa-boxes"></i> الكمية *</label>
                        <input type="number" name="quantity" min="1" value="1" required>
                    </div>

                    <div class="form-group">
                        <label><i class="fas fa-info-circle"></i> الحالة *</label>
                        <select name="condition_type" required>
                            <option value="new">جديد</option>
                            <option value="used" selected>مستعمل</option>
                            <option value="refurbished">مجدد</option>
                        </select>
                    </div>
                </div>

                <div class="form-group">
                    <label><i class="fas fa-images"></i> الصور (10 صور كحد أقصى) *</label>
                    <div class="image-upload" onclick="document.getElementById('images').click()">
                        <div class="upload-icon"><i class="fas fa-cloud-upload-alt"></i></div>
                        <p>انقر لرفع الصور أو اسحبها هنا</p>
                        <small style="color: #64748b;">الصورة الأولى ستكون الصورة الرئيسية</small>
                        <input type="file" id="images" name="images[]" multiple accept="image/*" onchange="previewImages(this)">
                    </div>
                    <div id="imagePreview" class="image-preview"></div>
                </div>

                <button type="submit" class="btn">
                    <i class="fas fa-check-circle"></i>
                    إضافة المنتج
                </button>
            </form>
        </div>
    </div>

    <script>
        function previewImages(input) {
            const preview = document.getElementById('imagePreview');
            preview.innerHTML = '';
            
            if (input.files) {
                Array.from(input.files).forEach((file, index) => {
                    const reader = new FileReader();
                    reader.onload = (e) => {
                        const div = document.createElement('div');
                        div.className = 'preview-item';
                        div.innerHTML = `
                            <img src="${e.target.result}" alt="Preview">
                            ${index === 0 ? '<div style="position: absolute; bottom: 5px; left: 5px; background: #10b981; color: white; padding: 3px 8px; border-radius: 5px; font-size: 0.75rem;">رئيسية</div>' : ''}
                        `;
                        preview.appendChild(div);
                    };
                    reader.readAsDataURL(file);
                });
            }
        }
    </script>
</body>
</html>
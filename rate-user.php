<?php
require_once 'config/database.php';
require_once 'includes/functions.php';

if (!isLoggedIn()) {
    redirect('auth/login.php');
}

$user_id = $_SESSION['user_id'];
$rating_id = isset($_GET['id']) ? clean($_GET['id']) : null;

if (!$rating_id) {
    redirect('dashboard.php');
}

// جلب معلومات التقييم
$stmt = $conn->prepare("
    SELECT r.*, 
           a.id as auction_id,
           v.brand, v.model, v.year,
           u.username as target_username
    FROM ratings r
    JOIN auctions a ON r.auction_id = a.id
    JOIN vehicles v ON a.vehicle_id = v.id
    JOIN users u ON r.to_user_id = u.id
    WHERE r.id = ? AND r.from_user_id = ?
");
$stmt->execute([$rating_id, $user_id]);
$rating_data = $stmt->fetch();

if (!$rating_data) {
    setMessage('تقييم غير موجود', 'danger');
    redirect('dashboard.php');
}

if ($rating_data['rating'] !== null) {
    setMessage('تم إرسال هذا التقييم مسبقاً', 'warning');
    redirect('auction-details.php?id=' . $rating_data['auction_id']);
}

$errors = [];
$success = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $rating = clean($_POST['rating']);
    $comment = clean($_POST['comment']);
    
    if (empty($rating) || $rating < 1 || $rating > 5) {
        $errors[] = 'يرجى اختيار تقييم من 1 إلى 5';
    }
    
    if (empty($errors)) {
        $stmt = $conn->prepare("UPDATE ratings SET rating = ?, comment = ? WHERE id = ?");
        $stmt->execute([$rating, $comment, $rating_id]);
        
        $success = 'تم إرسال التقييم بنجاح!';
        header("refresh:2;url=auction-details.php?id=" . $rating_data['auction_id']);
    }
}
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>تقييم المستخدم</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); min-height: 100vh; display: flex; align-items: center; justify-content: center; direction: rtl; padding: 20px; }
        .rating-container { background: white; border-radius: 15px; padding: 40px; max-width: 600px; width: 100%; box-shadow: 0 10px 40px rgba(0,0,0,0.2); }
        .rating-header { text-align: center; margin-bottom: 30px; }
        .rating-header h2 { color: #1e293b; margin-bottom: 10px; }
        .auction-info { background: #f8fafc; padding: 20px; border-radius: 12px; margin-bottom: 30px; text-align: center; }
        .auction-info h3 { color: #2563eb; margin-bottom: 5px; }
        .stars { display: flex; justify-content: center; gap: 10px; margin: 30px 0; }
        .star { font-size: 3rem; cursor: pointer; color: #cbd5e1; transition: 0.3s; }
        .star:hover, .star.active { color: #fbbf24; }
        .form-group { margin-bottom: 20px; }
        .form-group label { display: block; margin-bottom: 8px; color: #1e293b; font-weight: 600; }
        .form-group textarea { width: 100%; padding: 12px; border: 2px solid #e2e8f0; border-radius: 8px; font-size: 1rem; min-height: 120px; resize: vertical; }
        .form-group textarea:focus { outline: none; border-color: #2563eb; }
        .btn { width: 100%; padding: 14px; background: #2563eb; color: white; border: none; border-radius: 8px; font-size: 1.1rem; font-weight: bold; cursor: pointer; transition: 0.3s; }
        .btn:hover { background: #1d4ed8; }
        .alert { padding: 12px 15px; border-radius: 8px; margin-bottom: 20px; }
        .alert-danger { background: #fee2e2; color: #991b1b; }
        .alert-success { background: #d1fae5; color: #065f46; }
    </style>
</head>
<body>
    <div class="rating-container">
        <div class="rating-header">
            <h2>تقييم المستخدم</h2>
            <p style="color: #64748b;">شارك تجربتك مع المستخدم</p>
        </div>
                    <div style="position: fixed; top: 20px; left: 20px; z-index: 1000;">
        <?php include 'includes/lang-switcher.php'; ?>
    </div>
    <script src="js/auto-translate.js"></script>

        <div class="auction-info">
            <h3><?php echo htmlspecialchars($rating_data['brand'] . ' ' . $rating_data['model'] . ' ' . $rating_data['year']); ?></h3>
            <p style="color: #64748b; margin-top: 5px;">تقييم: <?php echo htmlspecialchars($rating_data['target_username']); ?></p>
        </div>

        <?php if (!empty($errors)): ?>
            <div class="alert alert-danger">
                <ul style="margin: 0; padding-right: 20px;">
                    <?php foreach ($errors as $error): ?>
                        <li><?php echo $error; ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <?php if ($success): ?>
            <div class="alert alert-success"><?php echo $success; ?></div>
        <?php endif; ?>

        <form method="POST" action="">
            <input type="hidden" name="rating" id="rating-value" value="">
            
            <div style="text-align: center; margin-bottom: 30px;">
                <label style="font-weight: 600; color: #1e293b; margin-bottom: 15px; display: block;">كيف كانت تجربتك؟</label>
                <div class="stars" id="stars">
                    <span class="star" data-rating="1">★</span>
                    <span class="star" data-rating="2">★</span>
                    <span class="star" data-rating="3">★</span>
                    <span class="star" data-rating="4">★</span>
                    <span class="star" data-rating="5">★</span>
                </div>
                <div id="rating-text" style="color: #64748b; margin-top: 10px;">اختر تقييمك</div>
            </div>

            <div class="form-group">
                <label>تعليقك (اختياري)</label>
                <textarea name="comment" placeholder="اكتب تعليقك عن تجربتك مع هذا المستخدم..."><?php echo isset($_POST['comment']) ? htmlspecialchars($_POST['comment']) : ''; ?></textarea>
            </div>

            <button type="submit" class="btn">إرسال التقييم</button>
        </form>

        <div style="text-align: center; margin-top: 20px;">
            <a href="auction-details.php?id=<?php echo $rating_data['auction_id']; ?>" style="color: #64748b; text-decoration: none;">تخطي</a>
        </div>
    </div>

    <script>
        const stars = document.querySelectorAll('.star');
        const ratingValue = document.getElementById('rating-value');
        const ratingText = document.getElementById('rating-text');
        
        const texts = {
            1: 'سيء جداً',
            2: 'سيء',
            3: 'مقبول',
            4: 'جيد',
            5: 'ممتاز'
        };

        stars.forEach(star => {
            star.addEventListener('click', function() {
                const rating = this.dataset.rating;
                ratingValue.value = rating;
                ratingText.textContent = texts[rating];
                
                stars.forEach((s, index) => {
                    if (index < rating) {
                        s.classList.add('active');
                    } else {
                        s.classList.remove('active');
                    }
                });
            });

            star.addEventListener('mouseenter', function() {
                const rating = this.dataset.rating;
                stars.forEach((s, index) => {
                    if (index < rating) {
                        s.style.color = '#fbbf24';
                    } else {
                        s.style.color = '#cbd5e1';
                    }
                });
            });
        });

        document.getElementById('stars').addEventListener('mouseleave', function() {
            const currentRating = ratingValue.value;
            stars.forEach((s, index) => {
                if (index < currentRating) {
                    s.style.color = '#fbbf24';
                } else {
                    s.style.color = '#cbd5e1';
                }
            });
        });
    </script>
</body>
</html>
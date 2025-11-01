<?php
require_once 'config/database.php';
require_once 'includes/functions.php';

if (!isLoggedIn()) {
    redirect('auth/login.php');
}

$user_id = $_SESSION['user_id'];
$auction_id = isset($_GET['id']) ? clean($_GET['id']) : null;

if (!$auction_id) {
    redirect('dashboard.php');
}

// جلب معلومات البيع
$stmt = $conn->prepare("
    SELECT 
        sc.*,
        a.id as auction_id,
        v.brand, v.model, v.year,
        seller.username as seller_name,
        buyer.username as buyer_name
    FROM sales_confirmations sc
    JOIN auctions a ON sc.auction_id = a.id
    JOIN vehicles v ON a.vehicle_id = v.id
    JOIN users seller ON sc.seller_id = seller.id
    JOIN users buyer ON sc.buyer_id = buyer.id
    WHERE sc.auction_id = ? AND (sc.seller_id = ? OR sc.buyer_id = ?)
");
$stmt->execute([$auction_id, $user_id, $user_id]);
$sale = $stmt->fetch();

if (!$sale) {
    setMessage('عملية بيع غير موجودة', 'danger');
    redirect('dashboard.php');
}

$is_seller = ($sale['seller_id'] == $user_id);
$is_buyer = ($sale['buyer_id'] == $user_id);

$errors = [];
$success = '';

// معالجة تأكيد الشحن من البائع
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['confirm_shipment']) && $is_seller) {
    try {
        $stmt = $conn->prepare("UPDATE sales_confirmations SET seller_shipped = TRUE WHERE id = ?");
        $stmt->execute([$sale['id']]);
        
        sendNotification($sale['buyer_id'], 'تم شحن السيارة', 'قام البائع بشحن السيارة. يرجى تأكيد الاستلام عند الوصول', 'system', $auction_id);
        
        $success = 'تم تأكيد الشحن بنجاح';
        header("refresh:1;url=confirm-delivery.php?id=$auction_id");
    } catch(Exception $e) {
        $errors[] = 'خطأ: ' . $e->getMessage();
    }
}
// إشعار خاص للتنبيه في الصفحة الرئيسية
sendNotification($sale['buyer_id'], 'إجراء مطلوب: تأكيد الاستلام', 'تم شحن سيارتك من قبل البائع. يرجى فحصها وتأكيد الاستلام', 'urgent', $auction_id);

// معالجة تأكيد الاستلام من المشتري
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['confirm_receipt']) && $is_buyer) {
    try {
        $conn->beginTransaction();
        
        // تأكيد الاستلام
        $stmt = $conn->prepare("
            UPDATE sales_confirmations 
            SET buyer_confirmed = TRUE, status = 'confirmed', confirmation_date = NOW() 
            WHERE id = ?
        ");
        $stmt->execute([$sale['id']]);
        
        // تحرير الرصيد المحجوز للبائع
        $stmt = $conn->prepare("
            UPDATE wallet 
            SET available_balance = available_balance + ?,
                sales_hold_balance = sales_hold_balance - ?
            WHERE user_id = ?
        ");
        $stmt->execute([$sale['seller_net_amount'], $sale['seller_net_amount'], $sale['seller_id']]);
        
        // تسجيل المعاملة
        logTransaction($sale['seller_id'], 'sales_released', $sale['seller_net_amount'], $auction_id, 'auction', 'تحرير رصيد مبيعات - تأكيد استلام المشتري');
        
        // إشعار البائع
        sendNotification($sale['seller_id'], 'تم تأكيد الاستلام', 'قام المشتري بتأكيد استلام السيارة. تم إضافة المبلغ لرصيدك', 'system', $auction_id);
        
        $conn->commit();
        
        $success = 'تم تأكيد الاستلام بنجاح! شكراً لك';
        header("refresh:2;url=dashboard.php");
        
    } catch(Exception $e) {
        $conn->rollBack();
        $errors[] = 'خطأ: ' . $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>تأكيد استلام السيارة</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { 
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; 
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            padding: 40px 20px;
            direction: rtl;
        }
        .container { max-width: 800px; margin: 0 auto; }
        .card { 
            background: white; 
            border-radius: 20px; 
            padding: 40px; 
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
        }
        .header {
            text-align: center;
            padding-bottom: 30px;
            border-bottom: 2px solid #f1f5f9;
            margin-bottom: 30px;
        }
        .header-icon {
            width: 80px;
            height: 80px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 20px;
            font-size: 2.5rem;
            color: white;
        }
        .header h1 { color: #1e293b; margin-bottom: 10px; }
        .header p { color: #64748b; }
        .info-box {
            background: #f8fafc;
            border-radius: 12px;
            padding: 25px;
            margin-bottom: 25px;
        }
        .info-row {
            display: flex;
            justify-content: space-between;
            padding: 12px 0;
            border-bottom: 1px solid #e2e8f0;
        }
        .info-row:last-child { border-bottom: none; }
        .info-label { color: #64748b; font-weight: 500; }
        .info-value { color: #1e293b; font-weight: 600; }
        .status-timeline {
            margin: 30px 0;
        }
        .timeline-item {
            display: flex;
            gap: 20px;
            margin-bottom: 20px;
            position: relative;
        }
        .timeline-item::before {
            content: '';
            position: absolute;
            left: 20px;
            top: 40px;
            bottom: -20px;
            width: 2px;
            background: #e2e8f0;
        }
        .timeline-item:last-child::before { display: none; }
        .timeline-icon {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
            font-size: 1.2rem;
        }
        .timeline-icon.completed { background: #d1fae5; color: #10b981; }
        .timeline-icon.pending { background: #fef3c7; color: #f59e0b; }
        .timeline-content h4 { color: #1e293b; margin-bottom: 5px; }
        .timeline-content p { color: #64748b; font-size: 0.9rem; }
        .btn {
            width: 100%;
            padding: 16px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            border-radius: 12px;
            font-size: 1.1rem;
            font-weight: bold;
            cursor: pointer;
            transition: 0.3s;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
        }
        .btn:hover { transform: translateY(-2px); box-shadow: 0 10px 30px rgba(102, 126, 234, 0.4); }
        .btn:disabled { background: #cbd5e1; cursor: not-allowed; }
        .alert {
            padding: 15px 20px;
            border-radius: 12px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .alert-success { background: #d1fae5; color: #065f46; border-right: 4px solid #10b981; }
        .alert-danger { background: #fee2e2; color: #991b1b; border-right: 4px solid #dc2626; }
        .alert-warning { background: #fef3c7; color: #92400e; border-right: 4px solid #f59e0b; }
    </style>
</head>
<body>
    <div class="container">
        <div class="card">
            <div class="header">
                <div class="header-icon">
                    <i class="fas fa-truck"></i>
                </div>
                <h1>تأكيد استلام السيارة</h1>
                <p><?php echo htmlspecialchars($sale['brand'] . ' ' . $sale['model'] . ' ' . $sale['year']); ?></p>
            </div>

            <?php if (!empty($errors)): ?>
                <div class="alert alert-danger">
                    <i class="fas fa-exclamation-circle"></i>
                    <?php echo $errors[0]; ?>
                </div>
            <?php endif; ?>

            <?php if ($success): ?>
                <div class="alert alert-success">
                    <i class="fas fa-check-circle"></i>
                    <?php echo $success; ?>
                </div>
            <?php endif; ?>

            <div class="info-box">
                <div class="info-row">
                    <span class="info-label">البائع:</span>
                    <span class="info-value"><?php echo htmlspecialchars($sale['seller_name']); ?></span>
                </div>
                <div class="info-row">
                    <span class="info-label">المشتري:</span>
                    <span class="info-value"><?php echo htmlspecialchars($sale['buyer_name']); ?></span>
                </div>
                <div class="info-row">
                    <span class="info-label">مبلغ البيع:</span>
                    <span class="info-value"><?php echo number_format($sale['sale_amount'], 2); ?>$</span>
                </div>
                <?php if ($is_seller): ?>
                <div class="info-row">
                    <span class="info-label">صافي المبلغ (بعد العمولة):</span>
                    <span class="info-value" style="color: #10b981;"><?php echo number_format($sale['seller_net_amount'], 2); ?>$</span>
                </div>
                <?php endif; ?>
            </div>

            <div class="status-timeline">
                <div class="timeline-item">
                    <div class="timeline-icon completed">
                        <i class="fas fa-check"></i>
                    </div>
                    <div class="timeline-content">
                        <h4>تم إتمام المزاد</h4>
                        <p>تم إغلاق المزاد وتحديد الفائز</p>
                    </div>
                </div>

                <div class="timeline-item">
                    <div class="timeline-icon <?php echo $sale['seller_shipped'] ? 'completed' : 'pending'; ?>">
                        <i class="fas fa-<?php echo $sale['seller_shipped'] ? 'check' : 'clock'; ?>"></i>
                    </div>
                    <div class="timeline-content">
                        <h4>شحن السيارة</h4>
                        <p><?php echo $sale['seller_shipped'] ? 'تم تأكيد الشحن من البائع' : 'بانتظار تأكيد الشحن من البائع'; ?></p>
                    </div>
                </div>

                <div class="timeline-item">
                    <div class="timeline-icon <?php echo $sale['buyer_confirmed'] ? 'completed' : 'pending'; ?>">
                        <i class="fas fa-<?php echo $sale['buyer_confirmed'] ? 'check' : 'clock'; ?>"></i>
                    </div>
                    <div class="timeline-content">
                        <h4>تأكيد الاستلام</h4>
                        <p><?php echo $sale['buyer_confirmed'] ? 'تم تأكيد الاستلام من المشتري' : 'بانتظار تأكيد الاستلام من المشتري'; ?></p>
                    </div>
                </div>

                <div class="timeline-item">
                    <div class="timeline-icon <?php echo $sale['status'] == 'confirmed' ? 'completed' : 'pending'; ?>">
                        <i class="fas fa-<?php echo $sale['status'] == 'confirmed' ? 'check' : 'clock'; ?>"></i>
                    </div>
                    <div class="timeline-content">
                        <h4>تحرير الرصيد</h4>
                        <p><?php echo $sale['status'] == 'confirmed' ? 'تم تحرير رصيد البائع' : 'بانتظار تأكيد الاستلام لتحرير الرصيد'; ?></p>
                    </div>
                </div>
            </div>

            <?php if ($is_seller && !$sale['seller_shipped'] && $sale['status'] == 'pending'): ?>
                <div class="alert alert-warning">
                    <i class="fas fa-info-circle"></i>
                    يرجى تأكيد شحن السيارة للمشتري
                </div>
                <form method="POST">
                    <button type="submit" name="confirm_shipment" class="btn">
                        <i class="fas fa-shipping-fast"></i>
                        تأكيد شحن السيارة
                    </button>
                </form>
            <?php elseif ($is_buyer && $sale['seller_shipped'] && !$sale['buyer_confirmed']): ?>
                <div class="alert alert-warning">
                    <i class="fas fa-exclamation-triangle"></i>
                    يرجى تأكيد استلام السيارة بعد الفحص والتأكد منها
                </div>
                <form method="POST">
                    <button type="submit" name="confirm_receipt" class="btn" style="background: #10b981;">
                        <i class="fas fa-check-circle"></i>
                        تأكيد استلام السيارة
                    </button>
                </form>
            <?php elseif ($sale['status'] == 'confirmed'): ?>
                <div class="alert alert-success">
                    <i class="fas fa-check-circle"></i>
                    تمت العملية بنجاح! شكراً لاستخدامكم منصتنا
                </div>
            <?php endif; ?>

            <div style="text-align: center; margin-top: 25px;">
                <a href="dashboard.php" style="color: #667eea; text-decoration: none; font-weight: 600;">
                    <i class="fas fa-arrow-right"></i> العودة للوحة التحكم
                </a>
            </div>
        </div>
    </div>
</body>
</html>
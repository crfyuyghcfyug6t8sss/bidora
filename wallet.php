<?php
require_once 'config/database.php';
require_once 'includes/functions.php';

if (!isLoggedIn()) {
    redirect('auth/login.php');
}

// معالجة طلبات AJAX
if (isset($_GET['ajax']) && $_GET['ajax'] == '1') {
    header('Content-Type: application/json');
    
    $filter_type = isset($_GET['filter']) ? $_GET['filter'] : 'all';
    $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
    $limit = 10;
    $offset = ($page - 1) * $limit;

    $where = "user_id = ?";
    $params = [$_SESSION['user_id']];

    if ($filter_type != 'all') {
        $where .= " AND type = ?";
        $params[] = $filter_type;
    }

    $stmt = $conn->prepare("SELECT * FROM transactions WHERE $where ORDER BY created_at DESC LIMIT ? OFFSET ?");
    $params[] = $limit;
    $params[] = $offset;
    $stmt->execute($params);
    $transactions = $stmt->fetchAll();

    $stmt = $conn->prepare("SELECT COUNT(*) FROM transactions WHERE $where");
    $stmt->execute(array_slice($params, 0, -2));
    $total_transactions = $stmt->fetchColumn();
    $total_pages = ceil($total_transactions / $limit);

    echo json_encode([
        'success' => true,
        'transactions' => $transactions,
        'current_page' => $page,
        'total_pages' => $total_pages,
        'total_transactions' => $total_transactions
    ]);
    exit;
}

// معالجة العمليات POST
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action'])) {
    
    if ($_POST['action'] == 'deposit') {
        $amount = clean($_POST['amount']);
        
        if (empty($amount) || $amount <= 0) {
            $_SESSION['error'] = 'المبلغ غير صحيح';
        } else {
            try {
                $stmt = $conn->prepare("UPDATE wallet SET available_balance = available_balance + ? WHERE user_id = ?");
                $stmt->execute([$amount, $_SESSION['user_id']]);
                
                logTransaction($_SESSION['user_id'], 'deposit', $amount, null, null, 'شحن رصيد');
                
                $_SESSION['success'] = 'تم شحن المحفظة بنجاح! 🎉';
            } catch(PDOException $e) {
                $_SESSION['error'] = 'حدث خطأ في عملية الشحن';
            }
        }
        
        header('Location: wallet.php?tab=deposit');
        exit;
    }
    
    elseif ($_POST['action'] == 'transfer') {
        $amount = clean($_POST['transfer_amount']);
        $recipient_username = clean($_POST['recipient_username']);
        
        if (empty($amount) || $amount <= 0) {
            $_SESSION['error'] = 'المبلغ غير صحيح';
        } elseif (empty($recipient_username)) {
            $_SESSION['error'] = 'يرجى إدخال اسم المستخدم';
        } else {
            try {
                $stmt = $conn->prepare("SELECT id FROM users WHERE username = ? AND id != ?");
                $stmt->execute([$recipient_username, $_SESSION['user_id']]);
                $recipient = $stmt->fetch();
                
                if (!$recipient) {
                    $_SESSION['error'] = 'المستخدم غير موجود';
                } else {
                    $wallet = getUserWallet($_SESSION['user_id']);
                    
                    if ($wallet['available_balance'] < $amount) {
                        $_SESSION['error'] = 'رصيد غير كافي';
                    } else {
                        $conn->beginTransaction();
                        
                        $stmt = $conn->prepare("UPDATE wallet SET available_balance = available_balance - ? WHERE user_id = ?");
                        $stmt->execute([$amount, $_SESSION['user_id']]);
                        
                        $stmt = $conn->prepare("UPDATE wallet SET available_balance = available_balance + ? WHERE user_id = ?");
                        $stmt->execute([$amount, $recipient['id']]);
                        
                        logTransaction($_SESSION['user_id'], 'withdraw', $amount, null, 'transfer', "تحويل إلى @$recipient_username");
                        logTransaction($recipient['id'], 'deposit', $amount, null, 'transfer', "تحويل من مستخدم");
                        
                        $conn->commit();
                        $_SESSION['success'] = 'تم التحويل بنجاح!  ';
                    }
                }
            } catch(PDOException $e) {
                $conn->rollBack();
                $_SESSION['error'] = 'حدث خطأ في عملية التحويل';
            }
        }
        
        header('Location: wallet.php?tab=transfer');
        exit;
    }
}

$success = isset($_SESSION['success']) ? $_SESSION['success'] : '';
$error = isset($_SESSION['error']) ? $_SESSION['error'] : '';
unset($_SESSION['success'], $_SESSION['error']);

$active_tab = isset($_GET['tab']) ? $_GET['tab'] : 'deposit';

$wallet = getUserWallet($_SESSION['user_id']);

$stmt = $conn->prepare("
    SELECT 
        SUM(CASE WHEN type = 'deposit' THEN amount ELSE 0 END) as total_deposits,
        SUM(CASE WHEN type = 'withdraw' THEN amount ELSE 0 END) as total_withdrawals,
        SUM(CASE WHEN type = 'commission' THEN amount ELSE 0 END) as total_commissions,
        COUNT(CASE WHEN type = 'bid_deduct' AND status = 'completed' THEN 1 END) as total_purchases
    FROM transactions 
    WHERE user_id = ?
");
$stmt->execute([$_SESSION['user_id']]);
$stats = $stmt->fetch();

$stmt = $conn->prepare("
    SELECT COUNT(*) as total_sales, 
           COALESCE(SUM(seller_net_amount), 0) as total_sales_amount
    FROM sales_confirmations 
    WHERE seller_id = ? AND status = 'confirmed'
");
$stmt->execute([$_SESSION['user_id']]);
$sales_stats = $stmt->fetch();

?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>   المحفظة الرقمية - لوحة التحكم الفاخرة</title>
    
    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
    
    <!-- Icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.2/font/bootstrap-icons.min.css">
    
    <!-- AOS Animation -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/aos/2.3.4/aos.css">
    
    <style>
        :root {
            --primary-gradient: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            --secondary-gradient: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
            --success-gradient: linear-gradient(135deg, #13f1fc 0%, #0470dc 100%);
            --warning-gradient: linear-gradient(135deg, #f9d423 0%, #ff4e50 100%);
            --dark-gradient: linear-gradient(135deg, #1a1c20 0%, #2d3748 100%);
            
            --glass-bg: rgba(255, 255, 255, 0.1);
            --glass-border: rgba(255, 255, 255, 0.2);
            
            --primary: #667eea;
            --secondary: #764ba2;
            --success: #13f1fc;
            --warning: #f9d423;
            --danger: #ff4e50;
            --dark: #1a1c20;
            --light: #f7fafc;
            
            --shadow-sm: 0 2px 15px rgba(0, 0, 0, 0.08);
            --shadow-md: 0 5px 25px rgba(0, 0, 0, 0.15);
            --shadow-lg: 0 10px 50px rgba(0, 0, 0, 0.2);
            --shadow-xl: 0 20px 100px rgba(0, 0, 0, 0.25);
            
            --transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
        }
        
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Cairo', sans-serif;
            background: linear-gradient(135deg, #0f0c29 0%, #302b63 50%, #24243e 100%);
            min-height: 100vh;
            direction: rtl;
            position: relative;
            overflow-x: hidden;
        }
        
        /* خلفية متحركة */
        .animated-background {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            z-index: -1;
            background: linear-gradient(270deg, #0f0c29, #302b63, #24243e, #0f0c29);
            background-size: 800% 800%;
            animation: gradientShift 30s ease infinite;
        }
        
        @keyframes gradientShift {
            0% { background-position: 0% 50%; }
            50% { background-position: 100% 50%; }
            100% { background-position: 0% 50%; }
        }
        
        /* جسيمات متحركة */
        .particles {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            z-index: -1;
            pointer-events: none;
        }
        
        .particle {
            position: absolute;
            width: 4px;
            height: 4px;
            background: linear-gradient(135deg, #667eea, #764ba2);
            border-radius: 50%;
            animation: float 20s infinite linear;
            opacity: 0.5;
        }
        
        @keyframes float {
            0% {
                transform: translateY(100vh) translateX(0) rotate(0deg);
                opacity: 0;
            }
            10% {
                opacity: 0.5;
            }
            90% {
                opacity: 0.5;
            }
            100% {
                transform: translateY(-100vh) translateX(100px) rotate(360deg);
                opacity: 0;
            }
        }
        
        /* الحاوية الرئيسية */
        .container {
            max-width: 1400px;
            margin: 0 auto;
            padding: 20px;
            position: relative;
            z-index: 1;
        }
        
        /* رأس الصفحة */
        .page-header {
            background: var(--glass-bg);
            backdrop-filter: blur(20px);
            -webkit-backdrop-filter: blur(20px);
            border: 1px solid var(--glass-border);
            padding: 30px;
            border-radius: 24px;
            margin-bottom: 30px;
            position: relative;
            overflow: hidden;
            animation: slideDown 0.8s ease-out;
        }
        
        @keyframes slideDown {
            from {
                opacity: 0;
                transform: translateY(-30px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        .page-header::before {
            content: '';
            position: absolute;
            top: -50%;
            right: -50%;
            width: 200%;
            height: 200%;
            background: radial-gradient(circle, rgba(102, 126, 234, 0.1) 0%, transparent 70%);
            animation: rotate 30s linear infinite;
        }
        
        @keyframes rotate {
            from { transform: rotate(0deg); }
            to { transform: rotate(360deg); }
        }
        
        .page-header h1 {
            color: #fff;
            font-size: 2.5rem;
            font-weight: 800;
            margin-bottom: 10px;
            position: relative;
            z-index: 1;
            text-shadow: 2px 2px 4px rgba(0, 0, 0, 0.3);
            background: linear-gradient(135deg, #fff, #e0e7ff);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            animation: shimmer 3s ease-in-out infinite;
        }
        
        @keyframes shimmer {
            0%, 100% { opacity: 1; }
            50% { opacity: 0.8; }
        }
        
        .page-header p {
            color: rgba(255, 255, 255, 0.8);
            font-size: 1.1rem;
            position: relative;
            z-index: 1;
        }
        
        /* شبكة الإحصائيات */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 25px;
            margin-bottom: 30px;
        }
        
        .stat-card {
            background: var(--glass-bg);
            backdrop-filter: blur(20px);
            -webkit-backdrop-filter: blur(20px);
            border: 1px solid var(--glass-border);
            padding: 25px;
            border-radius: 20px;
            position: relative;
            overflow: hidden;
            transition: var(--transition);
            animation: fadeInUp 0.8s ease-out;
            animation-fill-mode: both;
        }
        
        .stat-card:nth-child(1) { animation-delay: 0.1s; }
        .stat-card:nth-child(2) { animation-delay: 0.2s; }
        .stat-card:nth-child(3) { animation-delay: 0.3s; }
        .stat-card:nth-child(4) { animation-delay: 0.4s; }
        .stat-card:nth-child(5) { animation-delay: 0.5s; }
        .stat-card:nth-child(6) { animation-delay: 0.6s; }
        
        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(30px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        .stat-card:hover {
            transform: translateY(-5px) scale(1.02);
            box-shadow: 0 15px 40px rgba(102, 126, 234, 0.3);
            border-color: rgba(102, 126, 234, 0.5);
        }
        
        .stat-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: var(--primary-gradient);
            transform: scaleX(0);
            transition: transform 0.4s ease;
        }
        
        .stat-card:hover::before {
            transform: scaleX(1);
        }
        
        .stat-card .icon {
            width: 60px;
            height: 60px;
            border-radius: 16px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.8rem;
            margin-bottom: 15px;
            position: relative;
            animation: pulse 2s ease-in-out infinite;
        }
        
        @keyframes pulse {
            0%, 100% { transform: scale(1); }
            50% { transform: scale(1.05); }
        }
        
        .stat-card.primary .icon {
            background: var(--primary-gradient);
            color: white;
            box-shadow: 0 10px 30px rgba(102, 126, 234, 0.4);
        }
        
        .stat-card.success .icon {
            background: var(--success-gradient);
            color: white;
            box-shadow: 0 10px 30px rgba(19, 241, 252, 0.4);
        }
        
        .stat-card.danger .icon {
            background: var(--warning-gradient);
            color: white;
            box-shadow: 0 10px 30px rgba(249, 212, 35, 0.4);
        }
        
        .stat-card.warning .icon {
            background: var(--secondary-gradient);
            color: white;
            box-shadow: 0 10px 30px rgba(240, 147, 251, 0.4);
        }
        
        .stat-card.info .icon {
            background: linear-gradient(135deg, #56CCF2, #2F80ED);
            color: white;
            box-shadow: 0 10px 30px rgba(86, 204, 242, 0.4);
        }
        
        .stat-card.dark .icon {
            background: var(--dark-gradient);
            color: white;
            box-shadow: 0 10px 30px rgba(26, 28, 32, 0.4);
        }
        
        .stat-card h3 {
            color: rgba(255, 255, 255, 0.7);
            font-size: 0.95rem;
            font-weight: 500;
            margin-bottom: 8px;
            text-transform: uppercase;
            letter-spacing: 1px;
        }
        
        .stat-card .value {
            color: #fff;
            font-size: 2rem;
            font-weight: 800;
            margin-bottom: 5px;
            display: flex;
            align-items: baseline;
            gap: 5px;
        }
        
        .stat-card .value .currency {
            font-size: 1.2rem;
            color: rgba(255, 255, 255, 0.8);
        }
        
        .stat-card .trend {
            display: flex;
            align-items: center;
            gap: 5px;
            color: #13f1fc;
            font-size: 0.9rem;
            font-weight: 600;
        }
        
        .stat-card .trend.down {
            color: #ff4e50;
        }
        
        .stat-card .trend i {
            animation: bounce 1s ease-in-out infinite;
        }
        
        @keyframes bounce {
            0%, 100% { transform: translateY(0); }
            50% { transform: translateY(-3px); }
        }
        
        /* منطقة المحتوى الرئيسي */
        .main-content {
            background: var(--glass-bg);
            backdrop-filter: blur(20px);
            -webkit-backdrop-filter: blur(20px);
            border: 1px solid var(--glass-border);
            border-radius: 24px;
            overflow: hidden;
            animation: fadeIn 1s ease-out;
        }
        
        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }
        
        /* التبويبات */
        .tabs {
            display: flex;
            background: rgba(255, 255, 255, 0.05);
            padding: 10px;
            gap: 10px;
            border-bottom: 1px solid var(--glass-border);
        }
        
        .tab-button {
            flex: 1;
            padding: 15px 25px;
            background: transparent;
            border: 2px solid transparent;
            border-radius: 14px;
            color: rgba(255, 255, 255, 0.7);
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            transition: var(--transition);
            position: relative;
            overflow: hidden;
        }
        
        .tab-button::before {
            content: '';
            position: absolute;
            top: 50%;
            left: 50%;
            width: 0;
            height: 0;
            background: var(--primary-gradient);
            border-radius: 50%;
            transform: translate(-50%, -50%);
            transition: width 0.6s ease, height 0.6s ease;
        }
        
        .tab-button:hover {
            color: #fff;
            transform: translateY(-2px);
        }
        
        .tab-button.active {
            background: var(--primary-gradient);
            color: white;
            border-color: transparent;
            box-shadow: 0 8px 25px rgba(102, 126, 234, 0.4);
        }
        
        .tab-button.active::before {
            width: 100%;
            height: 100%;
            border-radius: 14px;
        }
        
        .tab-button i {
            margin-left: 8px;
            font-size: 1.1rem;
        }
        
        /* محتوى التبويبات */
        .tab-content {
            display: none;
            padding: 30px;
            animation: slideIn 0.5s ease-out;
        }
        
        .tab-content.active {
            display: block;
        }
        
        @keyframes slideIn {
            from {
                opacity: 0;
                transform: translateX(20px);
            }
            to {
                opacity: 1;
                transform: translateX(0);
            }
        }
        
        /* النماذج */
        .form-section {
            max-width: 600px;
            margin: 0 auto;
        }
        
        .form-group {
            margin-bottom: 25px;
            position: relative;
        }
        
        .form-group label {
            display: block;
            color: rgba(255, 255, 255, 0.9);
            font-weight: 600;
            margin-bottom: 10px;
            font-size: 1rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .input-wrapper {
            position: relative;
        }
        
        .input-wrapper i {
            position: absolute;
            right: 20px;
            top: 50%;
            transform: translateY(-50%);
            color: rgba(255, 255, 255, 0.5);
            font-size: 1.2rem;
            pointer-events: none;
            transition: var(--transition);
        }
        
        .form-control {
            width: 100%;
            padding: 15px 50px 15px 20px;
            background: rgba(255, 255, 255, 0.1);
            border: 2px solid rgba(255, 255, 255, 0.2);
            border-radius: 14px;
            color: white;
            font-size: 1.1rem;
            font-weight: 500;
            transition: var(--transition);
        }
        
        .form-control:focus {
            outline: none;
            background: rgba(255, 255, 255, 0.15);
            border-color: var(--primary);
            box-shadow: 0 0 0 4px rgba(102, 126, 234, 0.1),
                        0 10px 30px rgba(102, 126, 234, 0.2);
            transform: translateY(-2px);
        }
        
        .form-control:focus + i {
            color: var(--primary);
            transform: translateY(-50%) scale(1.1);
        }
        
        .form-control::placeholder {
            color: rgba(255, 255, 255, 0.4);
        }
        
        /* الأزرار */
        .btn {
            padding: 15px 40px;
            border: none;
            border-radius: 14px;
            font-size: 1.1rem;
            font-weight: 700;
            cursor: pointer;
            transition: var(--transition);
            position: relative;
            overflow: hidden;
            text-transform: uppercase;
            letter-spacing: 1px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
        }
        
        .btn::before {
            content: '';
            position: absolute;
            top: 50%;
            left: 50%;
            width: 0;
            height: 0;
            background: rgba(255, 255, 255, 0.2);
            border-radius: 50%;
            transform: translate(-50%, -50%);
            transition: width 0.6s ease, height 0.6s ease;
        }
        
        .btn:hover::before {
            width: 300%;
            height: 300%;
        }
        
        .btn-primary {
            background: var(--primary-gradient);
            color: white;
            box-shadow: 0 10px 30px rgba(102, 126, 234, 0.3);
        }
        
        .btn-primary:hover {
            transform: translateY(-3px);
            box-shadow: 0 15px 40px rgba(102, 126, 234, 0.4);
        }
        
        .btn-success {
            background: var(--success-gradient);
            color: white;
            box-shadow: 0 10px 30px rgba(19, 241, 252, 0.3);
        }
        
        .btn-success:hover {
            transform: translateY(-3px);
            box-shadow: 0 15px 40px rgba(19, 241, 252, 0.4);
        }
        
        .btn-block {
            width: 100%;
        }
        
        .btn i {
            font-size: 1.2rem;
            animation: pulse 2s ease-in-out infinite;
        }
        
        /* جدول المعاملات */
        .table-container {
            overflow-x: auto;
            border-radius: 16px;
            background: rgba(255, 255, 255, 0.05);
            padding: 20px;
            margin-top: 20px;
        }
        
        .transactions-table {
            width: 100%;
            border-collapse: separate;
            border-spacing: 0 10px;
        }
        
        .transactions-table thead th {
            background: var(--primary-gradient);
            color: white;
            padding: 18px;
            text-align: right;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 1px;
            font-size: 0.9rem;
            position: sticky;
            top: 0;
            z-index: 10;
        }
        
        .transactions-table thead th:first-child {
            border-radius: 14px 0 0 14px;
        }
        
        .transactions-table thead th:last-child {
            border-radius: 0 14px 14px 0;
        }
        
        .transactions-table tbody tr {
            background: rgba(255, 255, 255, 0.08);
            backdrop-filter: blur(10px);
            transition: var(--transition);
            animation: fadeInRow 0.5s ease-out;
            animation-fill-mode: both;
        }
        
        @keyframes fadeInRow {
            from {
                opacity: 0;
                transform: translateX(-20px);
            }
            to {
                opacity: 1;
                transform: translateX(0);
            }
        }
        
        .transactions-table tbody tr:hover {
            background: rgba(255, 255, 255, 0.12);
            transform: scale(1.01);
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.15);
        }
        
        .transactions-table tbody td {
            padding: 18px;
            color: rgba(255, 255, 255, 0.9);
            border: none;
            font-weight: 500;
        }
        
        .transactions-table tbody td:first-child {
            border-radius: 14px 0 0 14px;
        }
        
        .transactions-table tbody td:last-child {
            border-radius: 0 14px 14px 0;
        }
        
        /* الشارات */
        .badge {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 8px 16px;
            border-radius: 20px;
            font-size: 0.85rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            animation: glow 2s ease-in-out infinite;
        }
        
        @keyframes glow {
            0%, 100% { box-shadow: 0 0 10px currentColor; }
            50% { box-shadow: 0 0 20px currentColor; }
        }
        
        .badge-success {
            background: linear-gradient(135deg, #13f1fc, #0470dc);
            color: white;
        }
        
        .badge-danger {
            background: linear-gradient(135deg, #ff4e50, #f9423a);
            color: white;
        }
        
        .badge-warning {
            background: linear-gradient(135deg, #f9d423, #ff8c00);
            color: white;
        }
        
        .badge-info {
            background: linear-gradient(135deg, #56CCF2, #2F80ED);
            color: white;
        }
        
        /* المبالغ */
        .amount-positive {
            color: #13f1fc;
            font-weight: 700;
            font-size: 1.1rem;
            text-shadow: 0 0 10px rgba(19, 241, 252, 0.5);
        }
        
        .amount-negative {
            color: #ff4e50;
            font-weight: 700;
            font-size: 1.1rem;
            text-shadow: 0 0 10px rgba(255, 78, 80, 0.5);
        }
        
        /* الترقيم */
        .pagination {
            display: flex;
            justify-content: center;
            align-items: center;
            gap: 10px;
            margin-top: 30px;
            padding: 20px;
        }
        
        .pagination span {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-width: 45px;
            height: 45px;
            padding: 0 15px;
            background: rgba(255, 255, 255, 0.1);
            border: 2px solid rgba(255, 255, 255, 0.2);
            border-radius: 12px;
            color: rgba(255, 255, 255, 0.8);
            font-weight: 600;
            cursor: pointer;
            transition: var(--transition);
        }
        
        .pagination span:hover {
            background: var(--primary-gradient);
            border-color: transparent;
            color: white;
            transform: translateY(-3px);
            box-shadow: 0 8px 20px rgba(102, 126, 234, 0.3);
        }
        
        .pagination span.active {
            background: var(--primary-gradient);
            border-color: transparent;
            color: white;
            box-shadow: 0 8px 20px rgba(102, 126, 234, 0.3);
        }
        
        .pagination span.disabled {
            opacity: 0.4;
            cursor: not-allowed;
        }
        
        .pagination span.disabled:hover {
            background: rgba(255, 255, 255, 0.1);
            transform: none;
            box-shadow: none;
        }
        
        /* الرسائل */
        .alert {
            padding: 18px 25px;
            border-radius: 16px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 15px;
            font-weight: 600;
            animation: slideInAlert 0.5s ease-out;
            position: relative;
            overflow: hidden;
        }
        
        @keyframes slideInAlert {
            from {
                opacity: 0;
                transform: translateX(50px);
            }
            to {
                opacity: 1;
                transform: translateX(0);
            }
        }
        
        .alert::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.2), transparent);
            animation: shimmerAlert 3s ease-in-out infinite;
        }
        
        @keyframes shimmerAlert {
            0% { transform: translateX(-100%); }
            100% { transform: translateX(100%); }
        }
        
        .alert-success {
            background: linear-gradient(135deg, rgba(19, 241, 252, 0.2), rgba(4, 112, 220, 0.2));
            border: 2px solid rgba(19, 241, 252, 0.5);
            color: #13f1fc;
        }
        
        .alert-danger {
            background: linear-gradient(135deg, rgba(255, 78, 80, 0.2), rgba(249, 66, 58, 0.2));
            border: 2px solid rgba(255, 78, 80, 0.5);
            color: #ff4e50;
        }
        
        .alert i {
            font-size: 1.5rem;
        }
        
        /* الفلاتر */
        .filters-container {
            display: flex;
            gap: 15px;
            margin-bottom: 20px;
            flex-wrap: wrap;
        }
        
        .filter-btn {
            padding: 12px 24px;
            background: rgba(255, 255, 255, 0.1);
            border: 2px solid rgba(255, 255, 255, 0.2);
            border-radius: 12px;
            color: rgba(255, 255, 255, 0.8);
            font-weight: 600;
            cursor: pointer;
            transition: var(--transition);
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }
        
        .filter-btn:hover {
            background: rgba(255, 255, 255, 0.15);
            transform: translateY(-2px);
            border-color: var(--primary);
            color: white;
        }
        
        .filter-btn.active {
            background: var(--primary-gradient);
            border-color: transparent;
            color: white;
            box-shadow: 0 8px 20px rgba(102, 126, 234, 0.3);
        }
        
        .filter-btn i {
            font-size: 1rem;
        }
        
        /* الحالة الفارغة */
        .empty-state {
            text-align: center;
            padding: 60px 20px;
            color: rgba(255, 255, 255, 0.6);
        }
        
        .empty-state i {
            font-size: 4rem;
            margin-bottom: 20px;
            color: rgba(255, 255, 255, 0.3);
            animation: float 3s ease-in-out infinite;
        }
        
        @keyframes float {
            0%, 100% { transform: translateY(0); }
            50% { transform: translateY(-10px); }
        }
        
        .empty-state p {
            font-size: 1.2rem;
            font-weight: 500;
        }
        
        /* تحميل */
        .loading {
            display: inline-block;
            width: 50px;
            height: 50px;
            border: 3px solid rgba(255, 255, 255, 0.2);
            border-top: 3px solid var(--primary);
            border-radius: 50%;
            animation: spin 1s linear infinite;
        }
        
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
        
        /* تأثيرات إضافية */
        .shimmer {
            position: relative;
            overflow: hidden;
        }
        
        .shimmer::after {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.3), transparent);
            animation: shimmerEffect 2s infinite;
        }
        
        @keyframes shimmerEffect {
            0% { left: -100%; }
            100% { left: 100%; }
        }
        
        /* التنسيق المتجاوب */
        @media (max-width: 768px) {
            .stats-grid {
                grid-template-columns: 1fr;
            }
            
            .tabs {
                flex-direction: column;
            }
            
            .tab-button {
                width: 100%;
            }
            
            .table-container {
                padding: 10px;
            }
            
            .transactions-table {
                font-size: 0.9rem;
            }
            
            .page-header h1 {
                font-size: 1.8rem;
            }
        }
        
        /* تأثيرات ثلاثية الأبعاد */
        .card-3d {
            transform-style: preserve-3d;
            transition: transform 0.6s;
        }
        
        .card-3d:hover {
            transform: rotateY(5deg) rotateX(5deg);
        }
    </style>
</head>
<body>
    <!-- الخلفية المتحركة -->
    <div class="animated-background"></div>
    
    <!-- الجسيمات المتحركة -->
    <div class="particles" id="particles"></div>
    
    <div class="container">
        <!-- رأس الصفحة -->
        <div class="page-header" data-aos="fade-down">
            <h1>
                <i class="fas fa-wallet"></i> المحفظة الرقمية الفاخرة
            </h1>
            <p>إدارة أموالك بكل أناقة وسهولة  </p>
        </div>
        
        <!-- عرض الرسائل -->
        <?php if($success): ?>
        <div class="alert alert-success" data-aos="slide-right">
            <i class="fas fa-check-circle"></i>
            <?php echo $success; ?>
        </div>
        <?php endif; ?>
        
        <?php if($error): ?>
        <div class="alert alert-danger" data-aos="slide-right">
            <i class="fas fa-exclamation-circle"></i>
            <?php echo $error; ?>
        </div>
        <?php endif; ?>
        
        <!-- شبكة الإحصائيات -->
        <div class="stats-grid">
            <div class="stat-card primary card-3d" data-aos="fade-up">
                <div class="icon">
                    <i class="fas fa-coins"></i>
                </div>
                <h3>الرصيد المتاح</h3>
                <div class="value">
                    <span class="currency">$</span>
                    <span class="counter" data-target="<?php echo $wallet['available_balance']; ?>">0</span>
                </div>
                <div class="trend">
                    <i class="fas fa-arrow-up"></i>
                    <span>+12% هذا الشهر</span>
                </div>
            </div>
            
            <div class="stat-card success card-3d" data-aos="fade-up">
                <div class="icon">
                    <i class="fas fa-lock"></i>
                </div>
                <h3>الرصيد المحجوز</h3>
                <div class="value">
                    <span class="currency">$</span>
                    <span class="counter" data-target="<?php echo $wallet['frozen_balance']; ?>">0</span>
                </div>
                <div class="trend">
                    <i class="fas fa-arrow-right"></i>
                    <span>في المزادات النشطة</span>
                </div>
            </div>
            
            <div class="stat-card warning card-3d" data-aos="fade-up">
                <div class="icon">
                    <i class="fas fa-chart-line"></i>
                </div>
                <h3>إجمالي الإيداعات</h3>
                <div class="value">
                    <span class="currency">$</span>
                    <span class="counter" data-target="<?php echo $stats['total_deposits'] ?? 0; ?>">0</span>
                </div>
                <div class="trend">
                    <i class="fas fa-arrow-up"></i>
                    <span>+25% نمو</span>
                </div>
            </div>
            
            <div class="stat-card danger card-3d" data-aos="fade-up">
                <div class="icon">
                    <i class="fas fa-shopping-cart"></i>
                </div>
                <h3>المشتريات</h3>
                <div class="value">
                    <span class="counter" data-target="<?php echo $stats['total_purchases'] ?? 0; ?>">0</span>
                    <span style="font-size: 1rem;">عملية</span>
                </div>
                <div class="trend down">
                    <i class="fas fa-arrow-down"></i>
                    <span>-5% هذا الأسبوع</span>
                </div>
            </div>
            
            <div class="stat-card info card-3d" data-aos="fade-up">
                <div class="icon">
                    <i class="fas fa-handshake"></i>
                </div>
                <h3>المبيعات المؤكدة</h3>
                <div class="value">
                    <span class="counter" data-target="<?php echo $sales_stats['total_sales'] ?? 0; ?>">0</span>
                    <span style="font-size: 1rem;">صفقة</span>
                </div>
                <div class="trend">
                    <i class="fas fa-arrow-up"></i>
                    <span>رائع!</span>
                </div>
            </div>
            
            <div class="stat-card dark card-3d" data-aos="fade-up">
                <div class="icon">
                    <i class="fas fa-percentage"></i>
                </div>
                <h3>العمولات المدفوعة</h3>
                <div class="value">
                    <span class="currency">$</span>
                    <span class="counter" data-target="<?php echo $stats['total_commissions'] ?? 0; ?>">0</span>
                </div>
                <div class="trend">
                    <i class="fas fa-info-circle"></i>
                    <span>عمولة منخفضة</span>
                </div>
            </div>
        </div>
        
        <!-- المحتوى الرئيسي -->
        <div class="main-content" data-aos="fade-up">
            <!-- التبويبات -->
            <div class="tabs">
                <button class="tab-button <?php echo $active_tab == 'deposit' ? 'active' : ''; ?>" 
                        onclick="openTab('deposit')">
                    <i class="fas fa-plus-circle"></i> شحن المحفظة
                </button>
                <button class="tab-button <?php echo $active_tab == 'transfer' ? 'active' : ''; ?>" 
                        onclick="openTab('transfer')">
                    <i class="fas fa-exchange-alt"></i> التحويل
                </button>
                <button class="tab-button <?php echo $active_tab == 'transactions' ? 'active' : ''; ?>" 
                        onclick="openTab('transactions')">
                    <i class="fas fa-history"></i> سجل المعاملات
                </button>
            </div>
            
            <!-- محتوى شحن المحفظة -->
            <div id="deposit" class="tab-content <?php echo $active_tab == 'deposit' ? 'active' : ''; ?>">
                <div class="form-section">
                    <form method="POST" action="">
                        <input type="hidden" name="action" value="deposit">
                        
                        <div class="form-group">
                            <label for="amount">
                                <i class="fas fa-dollar-sign"></i> المبلغ المراد شحنه
                            </label>
                            <div class="input-wrapper">
                                <input type="number" 
                                       id="amount" 
                                       name="amount" 
                                       class="form-control" 
                                       placeholder="أدخل المبلغ بالدولار"
                                       min="10" 
                                       step="0.01" 
                                       required>
                                <i class="fas fa-money-bill-wave"></i>
                            </div>
                        </div>
                        
                        <button type="submit" class="btn btn-primary btn-block shimmer">
                            <i class="fas fa-rocket"></i>
                            شحن المحفظة الآن
                        </button>
                    </form>
                </div>
            </div>
            
            <!-- محتوى التحويل -->
            <div id="transfer" class="tab-content <?php echo $active_tab == 'transfer' ? 'active' : ''; ?>">
                <div class="form-section">
                    <form method="POST" action="">
                        <input type="hidden" name="action" value="transfer">
                        
                        <div class="form-group">
                            <label for="recipient_username">
                                <i class="fas fa-user"></i> اسم المستخدم المستلم
                            </label>
                            <div class="input-wrapper">
                                <input type="text" 
                                       id="recipient_username" 
                                       name="recipient_username" 
                                       class="form-control" 
                                       placeholder="@username"
                                       required>
                                <i class="fas fa-at"></i>
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label for="transfer_amount">
                                <i class="fas fa-coins"></i> المبلغ المراد تحويله
                            </label>
                            <div class="input-wrapper">
                                <input type="number" 
                                       id="transfer_amount" 
                                       name="transfer_amount" 
                                       class="form-control" 
                                       placeholder="0.00"
                                       min="1" 
                                       step="0.01" 
                                       required>
                                <i class="fas fa-exchange-alt"></i>
                            </div>
                        </div>
                        
                        <button type="submit" class="btn btn-success btn-block shimmer">
                            <i class="fas fa-paper-plane"></i>
                            إرسال التحويل
                        </button>
                    </form>
                </div>
            </div>
            
            <!-- محتوى سجل المعاملات -->
            <div id="transactions" class="tab-content <?php echo $active_tab == 'transactions' ? 'active' : ''; ?>">
                <div class="filters-container">
                    <button class="filter-btn active" onclick="filterTransactions('all')">
                        <i class="fas fa-globe"></i> جميع المعاملات
                    </button>
                    <button class="filter-btn" onclick="filterTransactions('deposit')">
                        <i class="fas fa-plus"></i> الإيداعات
                    </button>
                    <button class="filter-btn" onclick="filterTransactions('withdraw')">
                        <i class="fas fa-minus"></i> السحوبات
                    </button>
                    <button class="filter-btn" onclick="filterTransactions('bid_freeze')">
                        <i class="fas fa-lock"></i> الحجوزات
                    </button>
                    <button class="filter-btn" onclick="filterTransactions('bid_deduct')">
                        <i class="fas fa-shopping-cart"></i> المشتريات
                    </button>
                </div>
                
                <div class="table-container">
                    <table class="transactions-table">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>النوع</th>
                                <th>المبلغ</th>
                                <th>الرصيد السابق</th>
                                <th>الرصيد الحالي</th>
                                <th>الوصف</th>
                                <th>الحالة</th>
                                <th>التاريخ</th>
                            </tr>
                        </thead>
                        <tbody id="transactionsTableBody">
                            <tr>
                                <td colspan="8" class="empty-state">
                                    <div class="loading"></div>
                                    <p>جاري تحميل المعاملات...</p>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                
                <div class="pagination" id="paginationContainer"></div>
            </div>
        </div>
    </div>
    
    <!-- مكتبات JavaScript -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/aos/2.3.4/aos.js"></script>
    
    <script>
        // تهيئة AOS
        AOS.init({
            duration: 1000,
            once: true,
            offset: 100
        });
        
        // إنشاء الجسيمات المتحركة
        function createParticles() {
            const particlesContainer = document.getElementById('particles');
            const particleCount = 50;
            
            for (let i = 0; i < particleCount; i++) {
                const particle = document.createElement('div');
                particle.className = 'particle';
                particle.style.left = Math.random() * 100 + '%';
                particle.style.animationDelay = Math.random() * 20 + 's';
                particle.style.animationDuration = (Math.random() * 20 + 20) + 's';
                particlesContainer.appendChild(particle);
            }
        }
        
        // عداد الأرقام المتحرك
        function animateCounters() {
            const counters = document.querySelectorAll('.counter');
            
            counters.forEach(counter => {
                const target = parseFloat(counter.getAttribute('data-target'));
                const duration = 2000;
                const increment = target / (duration / 16);
                let current = 0;
                
                const updateCounter = () => {
                    current += increment;
                    if (current < target) {
                        counter.textContent = current.toFixed(2);
                        requestAnimationFrame(updateCounter);
                    } else {
                        counter.textContent = target.toFixed(2);
                    }
                };
                
                updateCounter();
            });
        }
        
        // تبديل التبويبات
        function openTab(tabName) {
            const tabs = document.querySelectorAll('.tab-content');
            const buttons = document.querySelectorAll('.tab-button');
            
            tabs.forEach(tab => tab.classList.remove('active'));
            buttons.forEach(btn => btn.classList.remove('active'));
            
            document.getElementById(tabName).classList.add('active');
            event.target.classList.add('active');
            
            // تحديث URL
            const url = new URL(window.location);
            url.searchParams.set('tab', tabName);
            window.history.pushState({}, '', url);
            
            // تحميل المعاملات إذا كان التبويب المفتوح هو المعاملات
            if (tabName === 'transactions') {
                loadTransactions('all', 1);
            }
        }
        
        let currentPage = 1;
        let currentFilter = 'all';
        
        // تصفية المعاملات
        function filterTransactions(type) {
            currentFilter = type;
            currentPage = 1;
            
            // تحديث الأزرار النشطة
            document.querySelectorAll('.filter-btn').forEach(btn => {
                btn.classList.remove('active');
            });
            event.target.classList.add('active');
            
            loadTransactions(type, 1);
        }
        
        // تحميل المعاملات
        function loadTransactions(filter, page) {
            const tbody = document.getElementById('transactionsTableBody');
            tbody.innerHTML = `
                <tr>
                    <td colspan="8" class="empty-state">
                        <div class="loading"></div>
                        <p>جاري تحميل المعاملات...</p>
                    </td>
                </tr>
            `;
            
            fetch(`wallet.php?ajax=1&filter=${filter}&page=${page}`)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        renderTransactions(data.transactions, page);
                        renderPagination(data.current_page, data.total_pages, filter);
                    } else {
                        tbody.innerHTML = `
                            <tr>
                                <td colspan="8" class="empty-state">
                                    <i class="fas fa-exclamation-triangle"></i>
                                    <p>حدث خطأ في تحميل البيانات</p>
                                </td>
                            </tr>
                        `;
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    tbody.innerHTML = `
                        <tr>
                            <td colspan="8" class="empty-state">
                                <i class="fas fa-exclamation-triangle"></i>
                                <p>حدث خطأ في الاتصال بالخادم</p>
                            </td>
                        </tr>
                    `;
                });
        }
        
        // عرض المعاملات
        function renderTransactions(transactions, page) {
            const tbody = document.getElementById('transactionsTableBody');
            
            if (transactions.length === 0) {
                tbody.innerHTML = `
                    <tr>
                        <td colspan="8" class="empty-state">
                            <i class="fas fa-inbox"></i>
                            <p>لا توجد معاملات بعد</p>
                        </td>
                    </tr>
                `;
                return;
            }
            
            const typeLabels = {
                'deposit': '<span class="badge badge-success"><i class="fas fa-plus"></i> شحن</span>',
                'withdraw': '<span class="badge badge-danger"><i class="fas fa-minus"></i> سحب</span>',
                'bid_freeze': '<span class="badge badge-warning"><i class="fas fa-lock"></i> حجز</span>',
                'bid_release': '<span class="badge badge-info"><i class="fas fa-unlock"></i> إلغاء حجز</span>',
                'bid_deduct': '<span class="badge badge-danger"><i class="fas fa-shopping-cart"></i> شراء</span>',
                'commission': '<span class="badge badge-warning"><i class="fas fa-percentage"></i> عمولة</span>',
                'refund': '<span class="badge badge-success"><i class="fas fa-undo"></i> استرداد</span>'
            };
            
            const statusLabels = {
                'completed': '<span class="badge badge-success">مكتمل</span>',
                'pending': '<span class="badge badge-warning">معلق</span>',
                'failed': '<span class="badge badge-danger">فشل</span>'
            };
            
            let html = '';
            const offset = (page - 1) * 10;
            
            transactions.forEach((transaction, index) => {
                const isPositive = ['deposit', 'bid_release', 'refund'].includes(transaction.type);
                const amountClass = isPositive ? 'amount-positive' : 'amount-negative';
                
                html += `
                    <tr style="animation-delay: ${index * 0.05}s">
                        <td>${offset + index + 1}</td>
                        <td>${typeLabels[transaction.type] || transaction.type}</td>
                        <td>
                            <span class="${amountClass}">
                                $${parseFloat(transaction.amount).toFixed(2)}
                            </span>
                        </td>
                        <td>$${parseFloat(transaction.balance_before).toFixed(2)}</td>
                        <td>$${parseFloat(transaction.balance_after).toFixed(2)}</td>
                        <td>${escapeHtml(transaction.description)}</td>
                        <td>${statusLabels[transaction.status]}</td>
                        <td style="font-size: 0.9rem; color: rgba(255, 255, 255, 0.7);">
                            ${formatDate(transaction.created_at)}
                        </td>
                    </tr>
                `;
            });
            
            tbody.innerHTML = html;
        }
        
        // عرض أزرار الترقيم
        function renderPagination(currentPage, totalPages, filter) {
            const container = document.getElementById('paginationContainer');
            
            if (totalPages <= 1) {
                container.innerHTML = '';
                return;
            }
            
            let html = '';
            
            // زر السابق
            if (currentPage > 1) {
                html += `
                    <span onclick="changePage(${currentPage - 1}, '${filter}')">
                        <i class="fas fa-chevron-right"></i> السابق
                    </span>
                `;
            } else {
                html += `
                    <span class="disabled">
                        <i class="fas fa-chevron-right"></i> السابق
                    </span>
                `;
            }
            
            // أرقام الصفحات
            const maxButtons = 5;
            let startPage = Math.max(1, currentPage - Math.floor(maxButtons / 2));
            let endPage = Math.min(totalPages, startPage + maxButtons - 1);
            
            if (endPage - startPage < maxButtons - 1) {
                startPage = Math.max(1, endPage - maxButtons + 1);
            }
            
            for (let i = startPage; i <= endPage; i++) {
                const activeClass = i === currentPage ? 'active' : '';
                html += `
                    <span class="${activeClass}" onclick="changePage(${i}, '${filter}')">
                        ${i}
                    </span>
                `;
            }
            
            // زر التالي
            if (currentPage < totalPages) {
                html += `
                    <span onclick="changePage(${currentPage + 1}, '${filter}')">
                        التالي <i class="fas fa-chevron-left"></i>
                    </span>
                `;
            } else {
                html += `
                    <span class="disabled">
                        التالي <i class="fas fa-chevron-left"></i>
                    </span>
                `;
            }
            
            container.innerHTML = html;
        }
        
        // تغيير الصفحة
        function changePage(page, filter) {
            currentPage = page;
            loadTransactions(filter, page);
            
            // التمرير لأعلى الجدول
            document.querySelector('.table-container').scrollIntoView({ 
                behavior: 'smooth',
                block: 'start'
            });
        }
        
        // دوال مساعدة
        function escapeHtml(text) {
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }
        
        function formatDate(dateString) {
            const date = new Date(dateString);
            const options = { 
                year: 'numeric', 
                month: 'short', 
                day: 'numeric',
                hour: '2-digit',
                minute: '2-digit'
            };
            return date.toLocaleDateString('ar-SA', options);
        }
        
        // التهيئة عند تحميل الصفحة
        document.addEventListener('DOMContentLoaded', function() {
            // إنشاء الجسيمات
            createParticles();
            
            // تشغيل العدادات
            setTimeout(animateCounters, 500);
            
            // إخفاء الرسائل تلقائياً
            const alerts = document.querySelectorAll('.alert');
            alerts.forEach(alert => {
                setTimeout(() => {
                    alert.style.transition = 'opacity 0.5s';
                    alert.style.opacity = '0';
                    setTimeout(() => alert.remove(), 500);
                }, 5000);
            });
            
            // تحميل المعاملات إذا كان التبويب نشطاً
            const transactionsTab = document.getElementById('transactions');
            if (transactionsTab && transactionsTab.classList.contains('active')) {
                loadTransactions('all', 1);
            }
        });
    </script>
</body>
</html>
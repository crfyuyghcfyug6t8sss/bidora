<?php
session_start(); 

require_once 'config/database.php'; // تأكد من أن هذا المسار صحيح

if (!isset($_SESSION['user_id'])) {
    echo json_encode([]);
    exit;
}

$user_id = $_SESSION['user_id'];

// جلب الإشعارات التي لم تُقرأ بعد
$stmt = $conn->prepare("SELECT id, title, message FROM notifications WHERE user_id = ? AND is_read = FALSE");
$stmt->execute([$user_id]);
$notifications = $stmt->fetchAll(PDO::FETCH_ASSOC);

if ($notifications) {
    // تحديث الإشعارات لجعلها مقروءة حتى لا يتم جلبها مرة أخرى
    $ids = array_column($notifications, 'id');
    $placeholders = implode(',', array_fill(0, count($ids), '?'));

    $updateStmt = $conn->prepare("UPDATE notifications SET is_read = TRUE WHERE id IN ($placeholders)");
    $updateStmt->execute($ids);

    // إرجاع الإشعارات الجديدة كـ JSON
    header('Content-Type: application/json');
    echo json_encode($notifications);

} else {
    // إرجاع مصفوفة فارغة إذا لم يكن هناك جديد
    echo json_encode([]);
}
?>
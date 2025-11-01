<?php
require_once 'config/database.php';

header('Content-Type: application/json; charset=UTF-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'error' => 'Invalid method']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
$userMessage = $input['message'] ?? '';

if (empty($userMessage)) {
    echo json_encode(['success' => false, 'error' => 'Empty message']);
    exit;
}

// جمع البيانات من قاعدة البيانات
$contextData = getContextData($conn);

// بناء الـ Prompt
$systemPrompt = buildSystemPrompt($contextData);
$fullPrompt = $systemPrompt . "\n\nسؤال المستخدم: " . $userMessage;

// إرسال لـ API
$apiResponse = callGeminiAPI($fullPrompt);

echo json_encode([
    'success' => true,
    'response' => $apiResponse
]);

// جمع بيانات السياق من قاعدة البيانات
function getContextData($conn) {
    $data = [];
    
    // السيارات النشطة
    try {
        $stmt = $conn->query("
            SELECT v.*, a.starting_price, a.current_price, a.end_time, a.status
            FROM vehicles v
            JOIN auctions a ON v.id = a.vehicle_id
            WHERE a.status = 'active'
            ORDER BY a.created_at DESC
            LIMIT 20
        ");
        $data['active_vehicles'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch(Exception $e) {
        $data['active_vehicles'] = [];
    }
    
    // إحصائيات
    try {
        $stmt = $conn->query("SELECT COUNT(*) as total FROM auctions WHERE status = 'active'");
        $data['total_active'] = $stmt->fetch()['total'];
        
        $stmt = $conn->query("SELECT AVG(current_price) as avg_price FROM auctions WHERE status = 'active'");
        $data['avg_price'] = $stmt->fetch()['avg_price'];
    } catch(Exception $e) {
        $data['total_active'] = 0;
        $data['avg_price'] = 0;
    }
    
    return $data;
}

// بناء الـ System Prompt
function buildSystemPrompt($contextData) {
    $prompt = "أنت مساعد ذكي لموقع مزادات السيارات. مهمتك مساعدة المستخدمين والإجابة على أسئلتهم بناءً على البيانات التالية:\n\n";
    
    // معلومات عامة
    $prompt .= "=== معلومات عامة ===\n";
    $prompt .= "- عدد المزادات النشطة: " . $contextData['total_active'] . "\n";
    $prompt .= "- متوسط الأسعار: $" . number_format($contextData['avg_price'], 2) . "\n\n";
    
    // السيارات المتاحة
    $prompt .= "=== السيارات المتاحة حالياً ===\n";
    if (!empty($contextData['active_vehicles'])) {
        foreach ($contextData['active_vehicles'] as $vehicle) {
            $prompt .= sprintf(
                "- %s %s %d: السعر الحالي $%s، السعر الابتدائي $%s، الحالة: %s\n",
                $vehicle['brand'],
                $vehicle['model'],
                $vehicle['year'],
                number_format($vehicle['current_price'], 2),
                number_format($vehicle['starting_price'], 2),
                $vehicle['status']
            );
        }
    } else {
        $prompt .= "لا توجد سيارات متاحة حالياً.\n";
    }
    
    $prompt .= "\n=== تعليمات الإجابة ===\n";
    $prompt .= "- كن ودوداً ومفيداً\n";
    $prompt .= "- استخدم اللغة العربية الفصحى البسيطة\n";
    $prompt .= "- إذا سأل المستخدم عن سيارة معينة، أعطه التفاصيل الكاملة\n";
    $prompt .= "- إذا سأل عن الأسعار، استخدم البيانات المذكورة أعلاه\n";
    $prompt .= "- إذا كان السؤال خارج الموضوع، أعد توجيهه بلطف لموضوع المزادات\n";
    $prompt .= "- اجعل الإجابات قصيرة ومباشرة (100-200 كلمة)\n\n";
    
    return $prompt;
}

// استدعاء Gemini API
function callGeminiAPI($prompt) {
    $proxyUrl = 'https://bidora.de/api-proxy.php'; // ضع رابط الـ proxy الخاص بك
    
    $requestData = [
        'prompt' => $prompt,
        'temperature' => 0.7
    ];
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $proxyUrl);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($requestData));
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($httpCode !== 200 || !$response) {
        return 'عذراً، لا أستطيع الإجابة الآن. يرجى المحاولة لاحقاً.';
    }
    
    $data = json_decode($response, true);
    
    if (isset($data['candidates'][0]['content']['parts'][0]['text'])) {
        return $data['candidates'][0]['content']['parts'][0]['text'];
    }
    
    return 'عذراً، حدث خطأ في معالجة الطلب.';
}
?>
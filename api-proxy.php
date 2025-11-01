<?php
// api-proxy.php - ضع هذا الملف على سيرفر Hostinger
header('Content-Type: application/json; charset=UTF-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Accept');

// معالجة OPTIONS request للـ CORS
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// التحقق من نوع الطلب
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed', 'message' => 'يجب استخدام POST']);
    exit();
}

// قراءة البيانات المرسلة
$input = json_decode(file_get_contents('php://input'), true);

// التحقق من البيانات
if (!$input || !isset($input['prompt'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid request', 'message' => 'البيانات المرسلة غير صحيحة']);
    exit();
}

// إعدادات Gemini API
$apiKey = 'AIzaSyAK05_FCw31v6DZJNPx-YA28q6EKNOcfO0';
$model = 'gemini-2.0-flash-exp';
$apiUrl = "https://generativelanguage.googleapis.com/v1beta/models/{$model}:generateContent?key={$apiKey}";

// بناء طلب API
$requestData = [
    'contents' => [
        [
            'parts' => [
                ['text' => $input['prompt']]
            ]
        ]
    ],
    'generationConfig' => [
        'temperature' => isset($input['temperature']) ? $input['temperature'] : 0.7,
        'topK' => 40,
        'topP' => 0.95,
        'maxOutputTokens' => 2048
    ]
];

// إضافة الصورة إذا وجدت
if (isset($input['image']) && isset($input['mimeType'])) {
    $requestData['contents'][0]['parts'][] = [
        'inline_data' => [
            'mime_type' => $input['mimeType'],
            'data' => $input['image']
        ]
    ];
}

/* === إضافة الصوت إذا وجد (الإضافة الوحيدة المطلوبة) === */
if (isset($input['audio'])) {
    $requestData['contents'][0]['parts'][] = [
        'inline_data' => [
            'mime_type' => isset($input['audio_mime']) ? $input['audio_mime'] : 'audio/webm',
            'data' => $input['audio'] // Base64 بدون data:prefix
        ]
    ];
}
/* === نهاية إضافة الصوت === */

// إعداد cURL
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $apiUrl);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($requestData));
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Content-Type: application/json',
    'Accept: application/json'
]);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
curl_setopt($ch, CURLOPT_TIMEOUT, 30);
curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);

// تنفيذ الطلب
$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curlError = curl_error($ch);
curl_close($ch);

// معالجة الأخطاء
if ($curlError) {
    // في حالة فشل cURL، جرب proxy بديل
    $alternativeResponse = tryAlternativeMethod($requestData);
    if ($alternativeResponse) {
        echo $alternativeResponse;
    } else {
        http_response_code(500);
        echo json_encode([
            'error' => 'Connection Error',
            'message' => 'فشل الاتصال بالخادم',
            'details' => $curlError
        ]);
    }
    exit();
}

// التحقق من كود الاستجابة
if ($httpCode !== 200) {
    // محاولة بديلة
    $alternativeResponse = tryAlternativeMethod($requestData);
    if ($alternativeResponse) {
        echo $alternativeResponse;
    } else {
        http_response_code($httpCode);
        $errorDetails = json_decode($response, true);
        echo json_encode([
            'error' => 'API Error',
            'httpCode' => $httpCode,
            'message' => 'خطأ من الخادم',
            'details' => $errorDetails
        ]);
    }
    exit();
}

// إرسال الرد الناجح
echo $response;

// دالة للمحاولة البديلة
function tryAlternativeMethod($requestData) {
    // قائمة بخوادم proxy مجانية
    $proxyServers = [
        'https://api.allorigins.win/raw?url=',
        'https://corsproxy.io/?',
        'https://api.codetabs.com/v1/proxy/?quest='
    ];
    
    $apiKey = 'AIzaSyAK05_FCw31v6DZJNPx-YA28q6EKNOcfO0';
    $baseUrl = "https://generativelanguage.googleapis.com/v1beta/models/gemini-2.0-flash-exp:generateContent?key={$apiKey}";
    
    foreach ($proxyServers as $proxy) {
        $proxyUrl = $proxy . urlencode($baseUrl);
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $proxyUrl);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($requestData));
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'Accept: application/json'
        ]);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_TIMEOUT, 20);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($httpCode === 200 && $response) {
            return $response;
        }
    }
    
    return false;
}
?>

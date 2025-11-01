<?php
ob_start();

require_once 'config/database.php';
require_once 'includes/functions.php';

ob_clean();

header('Content-Type: application/json; charset=utf-8');

try {
    $input = json_decode(file_get_contents('php://input'), true);
    $texts = $input['texts'] ?? [];
    $target_lang = $input['target_lang'] ?? 'en';
    
    if (empty($texts)) {
        echo json_encode(['success' => false, 'error' => 'No texts']);
        exit;
    }
    
    // مسار ملف الكاش
    $cache_dir = __DIR__ . '/cache/translations';
    $cache_file = $cache_dir . "/{$target_lang}.json";
    
    // إنشاء المجلد إذا مو موجود
    if (!is_dir($cache_dir)) {
        mkdir($cache_dir, 0755, true);
    }
    
    // تحميل الكاش الموجود
    $cache = [];
    if (file_exists($cache_file)) {
        $cache = json_decode(file_get_contents($cache_file), true) ?? [];
    }
    
    $translations = [];
    $new_count = 0;
    $cached_count = 0;
    
    foreach ($texts as $text) {
        $text = trim($text);
        if (strlen($text) < 2) continue;
        
        // التحقق من الكاش أولاً
        if (isset($cache[$text])) {
            $translations[$text] = $cache[$text];
            $cached_count++;
        } else {
            // ترجمة جديدة
            $translated = translateNow($text, $target_lang);
            $translations[$text] = $translated;
            
            // حفظ في الكاش
            $cache[$text] = $translated;
            $new_count++;
        }
    }
    
    // حفظ الكاش المحدث
    if ($new_count > 0) {
        file_put_contents($cache_file, json_encode($cache, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
    }
    
    echo json_encode([
        'success' => true,
        'translations' => $translations,
        'stats' => [
            'total' => count($texts),
            'cached' => $cached_count,
            'new' => $new_count,
            'cache_file' => $cache_file
        ]
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}

function translateNow($text, $lang) {
    // Google Translate
    $url = "https://translate.googleapis.com/translate_a/single?client=gtx&sl=ar&tl={$lang}&dt=t&q=" . urlencode($text);
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_TIMEOUT, 5);
    curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0');
    
    $response = curl_exec($ch);
    curl_close($ch);
    
    if ($response) {
        $json = json_decode($response, true);
        if (isset($json[0][0][0])) {
            return $json[0][0][0];
        }
    }
    
    // MyMemory كـ backup
    $url2 = "https://api.mymemory.translated.net/get?q=" . urlencode($text) . "&langpair=ar|{$lang}";
    $response2 = @file_get_contents($url2);
    
    if ($response2) {
        $json2 = json_decode($response2, true);
        if (isset($json2['responseData']['translatedText'])) {
            return $json2['responseData']['translatedText'];
        }
    }
    
    return $text;
}
?>

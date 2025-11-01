<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once 'config/database.php';
require_once 'includes/functions.php';

echo "<h1>فحص نظام الترجمة</h1>";

// 1. فحص الكوكيز
echo "<h3>1. اللغة الحالية:</h3>";
echo "اللغة: " . getCurrentLanguage() . "<br>";

// 2. فحص مجلد الكاش
echo "<h3>2. فحص مجلد الكاش:</h3>";
$cache_dir = __DIR__ . '/cache';
if (is_dir($cache_dir)) {
    echo "✅ مجلد الكاش موجود<br>";
    if (is_writable($cache_dir)) {
        echo "✅ المجلد قابل للكتابة<br>";
    } else {
        echo "❌ المجلد غير قابل للكتابة - قم بتغيير الصلاحيات<br>";
    }
} else {
    echo "❌ مجلد الكاش غير موجود - سيتم إنشاؤه تلقائياً<br>";
}

// 3. اختبار الترجمة
echo "<h3>3. اختبار الترجمة:</h3>";
echo "النص الأصلي: مرحباً<br>";
$translated = t('مرحباً');
echo "الترجمة: " . $translated . "<br>";

if ($translated === 'مرحباً' && getCurrentLanguage() !== 'ar') {
    echo "⚠️ الترجمة لم تعمل - جاري التحقق من الاتصال...<br>";
    
    // اختبار الاتصال بالإنترنت
    $test = @file_get_contents("https://www.google.com");
    if ($test) {
        echo "✅ الاتصال بالإنترنت يعمل<br>";
    } else {
        echo "❌ لا يوجد اتصال بالإنترنت<br>";
    }
} else {
    echo "✅ الترجمة تعمل!<br>";
}

// 4. عرض محتوى الكاش
echo "<h3>4. محتوى الكاش:</h3>";
$lang = getCurrentLanguage();
$cache_file = $cache_dir . "/translations_{$lang}.json";
if (file_exists($cache_file)) {
    echo "<pre>" . file_get_contents($cache_file) . "</pre>";
} else {
    echo "لا توجد ترجمات محفوظة بعد<br>";
}
?>
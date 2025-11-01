<?php
$lang = $_GET['lang'] ?? 'all';
$redirect = $_GET['redirect'] ?? null;
$cache_dir = __DIR__ . '/cache/translations';

if ($lang === 'all') {
    foreach (['en', 'tr', 'de'] as $l) {
        $file = $cache_dir . "/{$l}.json";
        if (file_exists($file)) {
            unlink($file);
        }
    }
    $message = "تم مسح جميع الترجمات!";
} else {
    $file = $cache_dir . "/{$lang}.json";
    if (file_exists($file)) {
        unlink($file);
        $message = "تم مسح ترجمات {$lang}!";
    } else {
        $message = "الملف غير موجود!";
    }
}

if ($redirect === 'admin') {
    header("Location: admin/dashboard.php?view=translations_cache&msg=" . urlencode($message));
} else {
    echo $message;
    echo "<br><br><a href='view-cache.php'>رجوع</a>";
}
?>
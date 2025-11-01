<?php
header('Content-Type: application/json');

$input = json_decode(file_get_contents('php://input'), true);
$lang = $input['lang'] ?? null;
$text = $input['text'] ?? null;

if (!$lang || !$text) {
    echo json_encode(['success' => false, 'error' => 'Missing parameters']);
    exit;
}

$cache_dir = __DIR__ . '/cache/translations';
$file = $cache_dir . "/{$lang}.json";

if (!file_exists($file)) {
    echo json_encode(['success' => false, 'error' => 'File not found']);
    exit;
}

$cache = json_decode(file_get_contents($file), true);

if (isset($cache[$text])) {
    unset($cache[$text]);
    file_put_contents($file, json_encode($cache, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
    echo json_encode(['success' => true]);
} else {
    echo json_encode(['success' => false, 'error' => 'Translation not found']);
}
?>
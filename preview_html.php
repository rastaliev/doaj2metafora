<?php
require_once __DIR__ . '/functions.php';
ensure_session();

header('Content-Type: text/html; charset=utf-8');

$html = $_SESSION['issue_html'] ?? '';

if ($html === '') {
    echo '<!doctype html><html><head><meta charset="utf-8"><title>Нет HTML</title></head><body>';
    echo '<p>HTML-файл выпуска не загружен.</p>';
    echo '</body></html>';
    exit;
}

// выводим как есть, без экранирования
echo $html;

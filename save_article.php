<?php
require_once __DIR__.'/functions.php';
ensure_session();

$id = (int)($_POST['id'] ?? 0);
if (!isset($_SESSION['articles'][$id])) {
    header('Location: dashboard.php');
    exit;
}

$art = $_SESSION['articles'][$id];

// Простые текстовые поля
$fields = [
    'doi',
    'edn',
    'title_ru',
    'title_en',
    'abstract_ru',
    'abstract_en',
    'fpage',
    'lpage',
    'type',
    'pub_date',
];

foreach ($fields as $f) {
    $art[$f] = trim((string)($_POST[$f] ?? ($art[$f] ?? '')));
}

// Ключевые слова (RU/EN) — по одному на строке
$art['keywords_ru'] = array_values(
    array_filter(
        array_map(
            'trim',
            preg_split('/\R/u', (string)($_POST['keywords_ru'] ?? ''), -1, PREG_SPLIT_NO_EMPTY)
        ),
        'strlen'
    )
);

$art['keywords_en'] = array_values(
    array_filter(
        array_map(
            'trim',
            preg_split('/\R/u', (string)($_POST['keywords_en'] ?? ''), -1, PREG_SPLIT_NO_EMPTY)
        ),
        'strlen'
    )
);

// Список литературы RU/EN — по одному источнику на строке
$art['refs_ru'] = array_values(
    array_filter(
        array_map(
            'trim',
            preg_split('/\R/u', (string)($_POST['refs_ru'] ?? ''), -1, PREG_SPLIT_NO_EMPTY)
        ),
        'strlen'
    )
);

$art['refs_en'] = array_values(
    array_filter(
        array_map(
            'trim',
            preg_split('/\R/u', (string)($_POST['refs_en'] ?? ''), -1, PREG_SPLIT_NO_EMPTY)
        ),
        'strlen'
    )
);

// Финансирование RU/EN — по одному проекту/гранту на строке
$art['funding_ru'] = array_values(
    array_filter(
        array_map(
            'trim',
            preg_split('/\R/u', (string)($_POST['funding_ru'] ?? ''), -1, PREG_SPLIT_NO_EMPTY)
        ),
        'strlen'
    )
);

$art['funding_en'] = array_values(
    array_filter(
        array_map(
            'trim',
            preg_split('/\R/u', (string)($_POST['funding_en'] ?? ''), -1, PREG_SPLIT_NO_EMPTY)
        ),
        'strlen'
    )
);

// Авторы (без автотранслитерации)
$authors = $_POST['authors'] ?? [];
$normAuthors = [];

if (is_array($authors)) {
    foreach ($authors as $a) {
        $normAuthors[] = [
            'surname_ru' => trim((string)($a['surname_ru'] ?? '')),
            'given_ru'   => trim((string)($a['given_ru']   ?? '')),
            'surname_en' => trim((string)($a['surname_en'] ?? '')),
            'given_en'   => trim((string)($a['given_en']   ?? '')),
            'email'      => trim((string)($a['email']      ?? '')),
            'orcid'      => trim((string)($a['orcid']      ?? '')),
            'aff_ru'     => trim((string)($a['aff_ru']     ?? '')),
            'aff_en'     => trim((string)($a['aff_en']     ?? '')),
        ];
    }
}

$art['authors'] = $normAuthors;

$_SESSION['articles'][$id] = $art;

header("Location: edit.php?id=$id");
exit;

<?php
require_once __DIR__.'/functions.php';
ensure_session();

$issue = $_SESSION['issue'];
$arts  = $_SESSION['articles'];

$xml   = metafora_xml_all($issue, $arts);
$slug  = slugify($issue['journal_title_en'] ?: ($issue['journal_title_ru'] ?: 'journal'));
$name  = $slug . '_all.xml';

header('Content-Type: application/xml; charset=utf-8');
header('Content-Disposition: attachment; filename="'.$name.'"');
echo $xml;

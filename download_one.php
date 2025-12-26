<?php
require_once __DIR__.'/functions.php';
ensure_session();

$id = (int)($_GET['id'] ?? 0);
if (!isset($_SESSION['articles'][$id])) { header('Location: dashboard.php'); exit; }

$issue = $_SESSION['issue'];
$art   = $_SESSION['articles'][$id];
$slug  = slugify($issue['journal_title_en'] ?: ($issue['journal_title_ru'] ?: 'journal'));
$name  = sprintf('%s_%03d.xml', $slug, $id+1);
$xml   = metafora_xml($issue, $art);

header('Content-Type: application/xml; charset=utf-8');
header('Content-Disposition: attachment; filename="'.$name.'"');
echo $xml;

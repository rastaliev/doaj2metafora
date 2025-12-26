<?php
require_once __DIR__.'/functions.php';
ensure_session();

$issue = $_SESSION['issue'];
$arts  = $_SESSION['articles'];

$slug = slugify($issue['journal_title_en'] ?: ($issue['journal_title_ru'] ?: 'journal'));

$tmp = tempnam(sys_get_temp_dir(), 'zip');
$zip = new ZipArchive();
$zip->open($tmp, ZipArchive::OVERWRITE);

foreach ($arts as $i=>$a) {
    $xml = metafora_xml($issue, $a);
    $name = sprintf('%s_%03d.xml', $slug, $i+1);
    $zip->addFromString($name, $xml);
}
$zip->close();

header('Content-Type: application/zip');
header('Content-Disposition: attachment; filename="'.$slug.'_xml.zip"');
header('Content-Length: '.filesize($tmp));
readfile($tmp);
unlink($tmp);

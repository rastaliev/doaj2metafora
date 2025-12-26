<?php
require_once __DIR__.'/functions.php';
ensure_session();

$fields = ['journal_title_ru','journal_title_en','issn','eissn','volume','issue','year','publisher','place'];
foreach ($fields as $f) { $_SESSION['issue'][$f] = trim($_POST[$f] ?? ''); }

header('Location: dashboard.php');

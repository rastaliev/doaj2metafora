<?php
declare(strict_types=1);

session_start();

/* ================== Утилиты ================== */

function h(string $s): string {
    return htmlspecialchars($s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function ensure_session(): void {
    if (!isset($_SESSION['articles'])) {
        header("Location: index.php");
        exit;
    }
}

function xpath_arr(SimpleXMLElement $node, string $path): array {
    $res = $node->xpath($path);
    return is_array($res) ? $res : [];
}

/** есть ли кириллица */
function has_cyrillic(string $s): bool {
    return (bool)preg_match('/\p{Cyrillic}/u', $s);
}

/** эвристика языка по алфавиту: кириллица -> RU, латиница -> EN, иначе EN */
function detect_lang_ru_en(string $s): string {
    if (has_cyrillic($s)) return 'RU';
    if (preg_match('/[A-Za-z]/', $s)) return 'EN';
    return 'EN';
}

/** RU -> EN (для slugify и прочих утилит; НЕ используется для ФИО/аффилиаций) */
function translit(string $text): string {
    $map = [
        'А'=>'A','Б'=>'B','В'=>'V','Г'=>'G','Д'=>'D','Е'=>'E','Ё'=>'E','Ж'=>'Zh','З'=>'Z','И'=>'I','Й'=>'Y',
        'К'=>'K','Л'=>'L','М'=>'M','Н'=>'N','О'=>'O','П'=>'P','Р'=>'R','С'=>'S','Т'=>'T','У'=>'U','Ф'=>'F',
        'Х'=>'Kh','Ц'=>'Ts','Ч'=>'Ch','Ш'=>'Sh','Щ'=>'Sch','Ы'=>'Y','Э'=>'E','Ю'=>'Yu','Я'=>'Ya','Ь'=>'','Ъ'=>''
    ];
    foreach ($map as $k => $v) {
        $map[mb_strtolower($k, 'UTF-8')] = strtolower($v);
    }
    $out = '';
    $len = mb_strlen($text, 'UTF-8');
    for ($i = 0; $i < $len; $i++) {
        $ch   = mb_substr($text, $i, 1, 'UTF-8');
        $out .= $map[$ch] ?? $ch;
    }
    return $out;
}

/** slug для имён файлов (используется в download_*.php) */
function slugify(string $value): string {
    $value = trim($value);
    if ($value === '') return 'file';
    $first = preg_split('/\s+/', $value)[0];
    $first = translit($first);
    $first = preg_replace('/[^a-zA-Z0-9_-]+/', '', $first);
    $first = strtolower($first);
    return $first ?: 'file';
}

/** безопасно добавляет элемент с текстом */
function addTextEl(DOMDocument $doc, DOMNode $parent, string $name, ?string $text): DOMElement {
    $el = $doc->createElement($name);
    if ($text !== null && $text !== '') {
        $el->appendChild($doc->createTextNode($text));
    }
    $parent->appendChild($el);
    return $el;
}

/** формат «YYYY[-MM[-DD]]» -> «ДД.ММ.ГГГГ»; недостающие месяц/день = 01 */
function to_dd_mm_yyyy(?string $iso): string {
    $iso = trim((string)$iso);
    if ($iso === '') return '';
    if (!preg_match('/^\d{4}(-\d{2})?(-\d{2})?$/', $iso)) {
        if (preg_match('/^\d{4}$/', $iso)) return '01.01.'.$iso;
        return '';
    }
    $parts = explode('-', $iso);
    $y = (int)$parts[0];
    $m = isset($parts[1]) ? (int)$parts[1] : 1;
    $d = isset($parts[2]) ? (int)$parts[2] : 1;
    return sprintf('%02d.%02d.%04d', $d, $m, $y);
}

/** мягко делим ФИО */
function split_name(string $name): array {
    $name = trim(preg_replace('/\s+/u', ' ', $name));
    if ($name === '') return ['surname' => '', 'given' => ''];
    $parts = explode(' ', $name);
    if (count($parts) === 1) {
        return ['surname' => $parts[0], 'given' => ''];
    }
    $surname = array_pop($parts);
    return ['surname' => $surname, 'given' => implode(' ', $parts)];
}

/** нормализуем список строк (массив или многострочная строка) */
function normalize_string_list($value): array {
    if (is_array($value)) {
        $items = $value;
    } else {
        $value = trim((string)$value);
        if ($value === '') return [];
        $items = preg_split('/\R+/u', $value);
    }
    $out = [];
    foreach ($items as $item) {
        $item = trim((string)$item);
        if ($item !== '') {
            $out[] = $item;
        }
    }
    return $out;
}

/* ================== Парсинг DOAJ ================== */

function parse_doaj_xml(string $xmlBytes): array {
    libxml_use_internal_errors(true);
    $sx = simplexml_load_string($xmlBytes);
    if ($sx === false) {
        $errs = array_map(fn($e) => trim($e->message), libxml_get_errors());
        throw new Exception('Невалидный XML: '.implode('; ', $errs));
    }

    $records = xpath_arr($sx, './/record');
    if (!$records) {
        $records = [$sx];
    }

    $first = $records[0];
    $issue = [
        'journal_title_ru' => trim((string)$first->journalTitle),
        'journal_title_en' => trim((string)$first->journalTitle),
        'issn'             => trim((string)$first->issn),
        'eissn'            => trim((string)$first->eissn),
        'volume'           => trim((string)$first->volume),
        'issue'            => trim((string)$first->issue),
        'publisher'        => trim((string)$first->publisher),
        'place'            => '',
        'year'             => substr((string)$first->publicationDate, 0, 4),
    ];

    $arts = [];
    foreach ($records as $rec) {
        // словарь аффилиаций по id
        $affMap = [];
        foreach (xpath_arr($rec, './affiliationsList/affiliationName') as $a) {
            $affMap[(string)$a['affiliationId']] = trim((string)$a);
        }

        $pubDate = trim((string)$rec->publicationDate); // YYYY-MM-DD

        $a = [
            'doi'          => trim((string)$rec->doi),
            'edn'          => '',
            'title_ru'     => '',
            'title_en'     => '',
            'abstract_ru'  => '',
            'abstract_en'  => '',
            'keywords_ru'  => [],
            'keywords_en'  => [],
            'authors'      => [],
            'fpage'        => trim((string)$rec->startPage),
            'lpage'        => trim((string)$rec->endPage),
            'refs_ru'      => [],
            'refs_en'      => [],
            'funding_ru'   => [],
            'funding_en'   => [],
            // тип статьи по словарю: по умолчанию RAR (Research Article)
            'type'         => 'RAR',
            'pub_date'     => $pubDate,
        ];

        // заголовки
        foreach (xpath_arr($rec, './title') as $t) {
            $lang = strtolower((string)$t['language']);
            $val  = trim((string)$t);
            if (str_starts_with($lang, 'ru')) {
                $a['title_ru'] = $val;
            }
            if (str_starts_with($lang, 'en') || $lang === 'eng' || $lang === '') {
                $a['title_en'] = $val;
            }
        }

        // аннотации
        foreach (xpath_arr($rec, './abstract') as $t) {
            $lang = strtolower((string)$t['language']);
            $val  = trim((string)$t);
            if (str_starts_with($lang, 'ru')) {
                $a['abstract_ru'] = $val;
            }
            if (str_starts_with($lang, 'en') || $lang === 'eng' || $lang === '') {
                $a['abstract_en'] = $val;
            }
        }

        // ключевые слова
        foreach (xpath_arr($rec, './keywords') as $kset) {
            $lang = strtolower((string)$kset['language']);
            $vals = array_values(
                array_filter(
                    array_map('trim', array_map('strval', xpath_arr($kset, './keyword'))),
                    'strlen'
                )
            );
            if (str_starts_with($lang, 'ru')) {
                $a['keywords_ru'] = $vals;
            }
            if (str_starts_with($lang, 'en') || $lang === 'eng' || $lang === '') {
                $a['keywords_en'] = $vals;
            }
        }

        // авторы — без какой-либо автоматической транслитерации
        foreach (xpath_arr($rec, './authors/author') as $au) {
            $nameRaw = trim((string)$au->name);
            $email   = trim((string)$au->email);
            $orcid   = trim((string)$au->orcid);
            $affId   = (string)$au->affiliationId;
            $affRaw  = $affMap[$affId] ?? '';

            $nameLang = detect_lang_ru_en($nameRaw);
            $affLang  = detect_lang_ru_en($affRaw);

            $sn = split_name($nameRaw);

            $surname_ru = $nameLang === 'RU' ? $sn['surname'] : '';
            $given_ru   = $nameLang === 'RU' ? $sn['given']   : '';
            $surname_en = $nameLang === 'EN' ? $sn['surname'] : '';
            $given_en   = $nameLang === 'EN' ? $sn['given']   : '';

            $aff_ru = $affLang === 'RU' ? $affRaw : '';
            $aff_en = $affLang === 'EN' ? $affRaw : '';

            $a['authors'][] = [
                'surname_ru' => $surname_ru,
                'given_ru'   => $given_ru,
                'surname_en' => $surname_en,
                'given_en'   => $given_en,
                'email'      => $email,
                'orcid'      => $orcid,
                'aff_ru'     => $aff_ru,
                'aff_en'     => $aff_en,
            ];
        }

        // Список литературы и финансирование из DOAJ не тянем.
        // refs_*/funding_* заполняются вручную в интерфейсе.

        $arts[] = $a;
    }

    return ['issue' => $issue, 'articles' => $arts];
}

/* ================== Генерация XML под «Метафору» ================== */

function add_article_xml(DOMDocument $doc, DOMElement $articlesParent, array $issue, array $art): void {
    $artEl = $articlesParent->appendChild($doc->createElement('article'));

    if (!empty($art['fpage']) && !empty($art['lpage'])) {
        addTextEl($doc, $artEl, 'pages', $art['fpage'].'-'.$art['lpage']);
    }

    // тип статьи: в XML пишем код из словаря (RAR, REV, BRV, CNF, EDI, SCO, MIS и т.п.)
    $typeCode = isset($art['type']) ? trim((string)$art['type']) : '';
    $allowedTypes = ['ABS','BRV','CNF','COR','EDI','MIS','PER','RAR','REP','REV','RPR','SCO','UNK'];
    if ($typeCode === '' || !in_array($typeCode, $allowedTypes, true)) {
        $typeCode = 'RAR'; // по умолчанию — научная статья
    }
    addTextEl($doc, $artEl, 'artType', $typeCode);

    // коды
    $codes = $artEl->appendChild($doc->createElement('codes'));
    if (!empty($art['doi'])) {
        addTextEl($doc, $codes, 'doi', $art['doi']);
    }
    if (!empty($art['edn'])) {
        addTextEl($doc, $codes, 'edn', $art['edn']);
    }

    // заголовки
    $titles = $artEl->appendChild($doc->createElement('artTitles'));
    $tRu = addTextEl($doc, $titles, 'artTitle', $art['title_ru'] ?? '');
    $tRu->setAttribute('lang', 'RU');
    $tEn = addTextEl($doc, $titles, 'artTitle', $art['title_en'] ?? '');
    $tEn->setAttribute('lang', 'EN');

    // даты статьи (полная дата)
    $dates = $artEl->appendChild($doc->createElement('dates'));
    $dp = to_dd_mm_yyyy($art['pub_date'] ?? '');
    if ($dp !== '') {
        addTextEl($doc, $dates, 'datePublication', $dp);
    }

    // аннотации
    $abs = $artEl->appendChild($doc->createElement('abstracts'));
    if (!empty($art['abstract_en'])) {
        $aEn = addTextEl($doc, $abs, 'abstract', $art['abstract_en']);
        $aEn->setAttribute('lang', 'EN');
    }
    if (!empty($art['abstract_ru'])) {
        $aRu = addTextEl($doc, $abs, 'abstract', $art['abstract_ru']);
        $aRu->setAttribute('lang', 'RU');
    }

    // ключевые слова
    $kw = $artEl->appendChild($doc->createElement('keywords'));
    if (!empty($art['keywords_en'])) {
        $gEn = $kw->appendChild($doc->createElement('kwdGroup'));
        $gEn->setAttribute('lang', 'EN');
        foreach ($art['keywords_en'] as $word) {
            addTextEl($doc, $gEn, 'keyword', $word);
        }
    }
    if (!empty($art['keywords_ru'])) {
        $gRu = $kw->appendChild($doc->createElement('kwdGroup'));
        $gRu->setAttribute('lang', 'RU');
        foreach ($art['keywords_ru'] as $word) {
            addTextEl($doc, $gRu, 'keyword', $word);
        }
    }

    // авторы (двуязычно) + ORCID
    $auths = $artEl->appendChild($doc->createElement('authors'));
    foreach ($art['authors'] as $au) {
        $a = $auths->appendChild($doc->createElement('author'));

        // RU блок
        $ru = $a->appendChild($doc->createElement('individInfo'));
        $ru->setAttribute('lang', 'RU');
        addTextEl($doc, $ru, 'surname',  $au['surname_ru'] ?? '');
        addTextEl($doc, $ru, 'initials', $au['given_ru']   ?? '');
        if (!empty($au['email'])) {
            addTextEl($doc, $ru, 'email', $au['email']);
        }
        if (!empty($au['aff_ru'])) {
            addTextEl($doc, $ru, 'orgName', $au['aff_ru']);
        }

        // EN блок
        $en = $a->appendChild($doc->createElement('individInfo'));
        $en->setAttribute('lang', 'EN');
        addTextEl($doc, $en, 'surname',  $au['surname_en'] ?? '');
        addTextEl($doc, $en, 'initials', $au['given_en']   ?? '');
        if (!empty($au['email'])) {
            addTextEl($doc, $en, 'email', $au['email']);
        }
        if (!empty($au['aff_en'])) {
            addTextEl($doc, $en, 'orgName', $au['aff_en']);
        }

        if (!empty($au['orcid'])) {
            $ac = $a->appendChild($doc->createElement('authorCodes'));
            addTextEl($doc, $ac, 'orcid', $au['orcid']);
        }
    }

    // список литературы (References) RU/EN по новой схеме XSD
    $refsRu = normalize_string_list($art['refs_ru'] ?? []);
    $refsEn = normalize_string_list($art['refs_en'] ?? []);

    if ($refsRu || $refsEn) {
        $refs = $artEl->appendChild($doc->createElement('references'));

        foreach ($refsRu as $r) {
            $ref = $refs->appendChild($doc->createElement('reference'));
            $info = $ref->appendChild($doc->createElement('refInfo'));
            $info->setAttribute('lang', 'RU');
            addTextEl($doc, $info, 'text', $r);
        }

        foreach ($refsEn as $r) {
            $ref = $refs->appendChild($doc->createElement('reference'));
            $info = $ref->appendChild($doc->createElement('refInfo'));
            $info->setAttribute('lang', 'EN');
            addTextEl($doc, $info, 'text', $r);
        }
    }

    // Финансирование / Funding (RU/EN) — пишем и в <fundings>, и в <artFunding>
    $fundRu = normalize_string_list($art['funding_ru'] ?? []);
    $fundEn = normalize_string_list($art['funding_en'] ?? []);

    if ($fundRu || $fundEn) {
        $fundingsEl   = $artEl->appendChild($doc->createElement('fundings'));
        $artFundingEl = $artEl->appendChild($doc->createElement('artFunding'));

        $langLists = [
            'RU' => $fundRu,
            'EN' => $fundEn,
        ];

        foreach ($langLists as $lang => $list) {
            foreach ($list as $txt) {
                $f1 = addTextEl($doc, $fundingsEl, 'funding', $txt);
                $f1->setAttribute('lang', $lang);
                $f2 = addTextEl($doc, $artFundingEl, 'funding', $txt);
                $f2->setAttribute('lang', $lang);
            }
        }
    }
}

/** одна статья = один файл */
function metafora_xml(array $issue, array $art): string {
    $doc = new DOMDocument('1.0', 'utf-8');
    $doc->formatOutput = true;

    $journal = $doc->appendChild($doc->createElement('journal'));
    if (!empty($issue['issn'])) {
        addTextEl($doc, $journal, 'issn', $issue['issn']);
    }
    if (!empty($issue['eissn'])) {
        addTextEl($doc, $journal, 'eissn', $issue['eissn']);
    }

    $infoRU = $journal->appendChild($doc->createElement('journalInfo'));
    $infoRU->setAttribute('lang', 'RU');
    addTextEl($doc, $infoRU, 'title',     $issue['journal_title_ru'] ?? '');
    addTextEl($doc, $infoRU, 'Title',     $issue['journal_title_en'] ?? '');
    addTextEl($doc, $infoRU, 'publ',      $issue['publisher']        ?? '');
    addTextEl($doc, $infoRU, 'placePubl', $issue['place']            ?? '');

    $infoEN = $journal->appendChild($doc->createElement('journalInfo'));
    $infoEN->setAttribute('lang', 'EN');
    addTextEl($doc, $infoEN, 'title',     $issue['journal_title_en'] ?? '');
    addTextEl($doc, $infoEN, 'Title',     $issue['journal_title_en'] ?? '');
    addTextEl($doc, $infoEN, 'publ',      $issue['publisher']        ?? '');
    addTextEl($doc, $infoEN, 'placePubl', $issue['place']            ?? '');

    $issueEl = $journal->appendChild($doc->createElement('issue'));
    addTextEl($doc, $issueEl, 'volume', $issue['volume'] ?? '');
    addTextEl($doc, $issueEl, 'number', $issue['issue']  ?? '');

    // ====== ДЛЯ ВЫПУСКА: ТОЛЬКО ГОД ======
    $year = '';
    if (!empty($issue['year'])) {
        $year = preg_replace('/\D/', '', (string)$issue['year']);
    } else {
        $src = trim((string)($art['pub_date'] ?? ''));
        if ($src !== '' && preg_match('/^(\d{4})/', $src, $m)) {
            $year = $m[1];
        }
    }
    addTextEl($doc, $issueEl, 'dateUni', $year);

    $artsEl = $issueEl->appendChild($doc->createElement('articles'));
    add_article_xml($doc, $artsEl, $issue, $art);

    return $doc->saveXML();
}

/** общий файл со всеми статьями выпуска */
function metafora_xml_all(array $issue, array $articles): string {
    $doc = new DOMDocument('1.0', 'utf-8');
    $doc->formatOutput = true;

    // ====== ДЛЯ ВЫПУСКА: ТОЛЬКО ГОД ======
    $issueYear = preg_replace('/\D/', '', (string)($issue['year'] ?? ''));
    if ($issueYear === '') {
        // если в метаданных выпуска год не задан — берём из первой попавшейся статьи
        foreach ($articles as $a) {
            $pd = trim((string)($a['pub_date'] ?? ''));
            if ($pd !== '' && preg_match('/^(\d{4})/', $pd, $m)) {
                $issueYear = $m[1];
                break;
            }
        }
    }
    $dateForIssue = $issueYear; // может быть пустой, если нигде нет года

    $journal = $doc->appendChild($doc->createElement('journal'));
    if (!empty($issue['issn'])) {
        addTextEl($doc, $journal, 'issn', $issue['issn']);
    }
    if (!empty($issue['eissn'])) {
        addTextEl($doc, $journal, 'eissn', $issue['eissn']);
    }

    $infoRU = $journal->appendChild($doc->createElement('journalInfo'));
    $infoRU->setAttribute('lang', 'RU');
    addTextEl($doc, $infoRU, 'title',     $issue['journal_title_ru'] ?? '');
    addTextEl($doc, $infoRU, 'Title',     $issue['journal_title_en'] ?? '');
    addTextEl($doc, $infoRU, 'publ',      $issue['publisher']        ?? '');
    addTextEl($doc, $infoRU, 'placePubl', $issue['place']            ?? '');

    $infoEN = $journal->appendChild($doc->createElement('journalInfo'));
    $infoEN->setAttribute('lang', 'EN');
    addTextEl($doc, $infoEN, 'title',     $issue['journal_title_en'] ?? '');
    addTextEl($doc, $infoEN, 'Title',     $issue['journal_title_en'] ?? '');
    addTextEl($doc, $infoEN, 'publ',      $issue['publisher']        ?? '');
    addTextEl($doc, $infoEN, 'placePubl', $issue['place']            ?? '');

    $issueEl = $journal->appendChild($doc->createElement('issue'));
    addTextEl($doc, $issueEl, 'volume',  $issue['volume'] ?? '');
    addTextEl($doc, $issueEl, 'number',  $issue['issue']  ?? '');
    addTextEl($doc, $issueEl, 'dateUni', $dateForIssue);

    $artsEl = $issueEl->appendChild($doc->createElement('articles'));
    foreach ($articles as $art) {
        add_article_xml($doc, $artsEl, $issue, $art);
    }

    return $doc->saveXML();
}

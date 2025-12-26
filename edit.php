<?php
require_once __DIR__ . '/functions.php';
ensure_session();

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if (!isset($_SESSION['articles'][$id])) {
    header('Location: dashboard.php');
    exit;
}
$art = $_SESSION['articles'][$id];

// гарантируем наличие массивов, чтобы не ловить notice
$art['keywords_ru']  = $art['keywords_ru']  ?? [];
$art['keywords_en']  = $art['keywords_en']  ?? [];
$art['authors']      = $art['authors']      ?? [];
$art['refs_ru']      = $art['refs_ru']      ?? [];
$art['refs_en']      = $art['refs_en']      ?? [];
$art['funding_ru']   = $art['funding_ru']   ?? [];
$art['funding_en']   = $art['funding_en']   ?? [];
$art['type']         = $art['type']         ?? '';

if (isset($_GET['add_author'])) {
    $art['authors'][] = [
        'surname_ru' => '',
        'given_ru'   => '',
        'surname_en' => '',
        'given_en'   => '',
        'email'      => '',
        'orcid'      => '',
        'aff_ru'     => '',
        'aff_en'     => '',
    ];
    $_SESSION['articles'][$id] = $art;
    header("Location: edit.php?id=$id");
    exit;
}

$hasHtmlPreview = !empty($_SESSION['issue_html']);
?>
<!doctype html>
<html lang="ru">
<head>
  <meta charset="utf-8">
  <title>Редактировать статью #<?= ($id + 1) ?></title>
  <link rel="stylesheet" href="style.css">
</head>
<body>
<div class="wrap wrap-wide">
  <div class="topbar">
    <a class="btn light" href="dashboard.php">← К списку</a>
    <a class="btn" href="download_one.php?id=<?= $id ?>">Скачать XML этой статьи</a>
  </div>

  <h2>Статья #<?= ($id + 1) ?></h2>

  <div class="edit-layout">
    <!-- Левая колонка: форма -->
    <div class="edit-left">
      <form method="post" action="save_article.php">
        <input type="hidden" name="id" value="<?= $id ?>">

        <h3>Основные данные</h3>
        <div class="grid">
          <label>DOI
            <div class="field-row">
              <input id="doi" name="doi" value="<?= h($art['doi']) ?>">
              <button type="button" class="btn small light" data-fill-from-preview="doi">Спарсить</button>
            </div>
          </label>

          <label>EDN
            <div class="field-row">
              <input id="edn" name="edn" value="<?= h($art['edn'] ?? '') ?>">
              <button type="button" class="btn small light" data-fill-from-preview="edn">Спарсить</button>
            </div>
          </label>

          <label>Название (RU)
            <div class="field-row">
              <input id="title_ru" name="title_ru" value="<?= h($art['title_ru']) ?>">
              <button type="button" class="btn small light" data-fill-from-preview="title_ru">Спарсить</button>
            </div>
          </label>

          <label>Название (EN)
            <div class="field-row">
              <input id="title_en" name="title_en" value="<?= h($art['title_en']) ?>">
              <button type="button" class="btn small light" data-fill-from-preview="title_en">Спарсить</button>
            </div>
          </label>

          <label>Аннотация (RU)
            <div class="field-row field-row-textarea">
              <textarea id="abstract_ru" name="abstract_ru" rows="6"><?= h($art['abstract_ru']) ?></textarea>
              <button type="button" class="btn small light" data-fill-from-preview="abstract_ru">Спарсить</button>
            </div>
          </label>

          <label>Аннотация (EN)
            <div class="field-row field-row-textarea">
              <textarea id="abstract_en" name="abstract_en" rows="6"><?= h($art['abstract_en']) ?></textarea>
              <button type="button" class="btn small light" data-fill-from-preview="abstract_en">Спарсить</button>
            </div>
          </label>

          <label>Ключевые слова (RU) — по одному на строке
            <div class="field-row field-row-textarea">
              <textarea id="keywords_ru" name="keywords_ru" rows="6"><?= h(implode("\n", $art['keywords_ru'])) ?></textarea>
              <button type="button" class="btn small light" data-fill-from-preview="keywords_ru">Спарсить</button>
            </div>
          </label>

          <label>Ключевые слова (EN) — по одному на строке
            <div class="field-row field-row-textarea">
              <textarea id="keywords_en" name="keywords_en" rows="6"><?= h(implode("\n", $art['keywords_en'])) ?></textarea>
              <button type="button" class="btn small light" data-fill-from-preview="keywords_en">Спарсить</button>
            </div>
          </label>

          <label>Первая страница
            <div class="field-row">
              <input id="fpage" name="fpage" value="<?= h($art['fpage']) ?>">
              <button type="button" class="btn small light" data-fill-from-preview="fpage">Спарсить</button>
            </div>
          </label>

          <label>Последняя страница
            <div class="field-row">
              <input id="lpage" name="lpage" value="<?= h($art['lpage']) ?>">
              <button type="button" class="btn small light" data-fill-from-preview="lpage">Спарсить</button>
            </div>
          </label>

          <label>Дата публикации статьи (YYYY-MM-DD)
            <div class="field-row">
              <input id="pub_date" name="pub_date" value="<?= h($art['pub_date'] ?? '') ?>">
              <button type="button" class="btn small light" data-fill-from-preview="pub_date">Спарсить</button>
            </div>
          </label>

          <label>Тип статьи
            <select name="type">
              <?php $type = $art['type']; ?>
              <option value="">–</option>
              <option value="RAR" <?= $type === 'RAR' ? 'selected' : '' ?>>Научная статья</option>
              <option value="REV" <?= $type === 'REV' ? 'selected' : '' ?>>Обзорная статья</option>
              <option value="EDI" <?= $type === 'EDI' ? 'selected' : '' ?>>От редакции</option>
              <option value="BRV" <?= $type === 'BRV' ? 'selected' : '' ?>>Рецензия</option>
              <option value="SCO" <?= $type === 'SCO' ? 'selected' : '' ?>>Краткое сообщение</option>
              <option value="CNF" <?= $type === 'CNF' ? 'selected' : '' ?>>Материалы конференции</option>
              <option value="MIS" <?= $type === 'MIS' ? 'selected' : '' ?>>Сообщение о ретракции</option>
              <option value="MIS" <?= $type === 'MIS' && false ? 'selected' : '' ?>>Другое</option>
            </select>
          </label>
        </div>

        <h3>Авторы</h3>
        <?php foreach ($art['authors'] as $i => $au): ?>
          <fieldset class="author">
            <legend>Автор <?= ($i + 1) ?></legend>
            <div class="grid">
              <label>Фамилия (RU)
                <div class="field-row">
                  <input id="author_<?= $i ?>_surname_ru"
                         name="authors[<?= $i ?>][surname_ru]"
                         value="<?= h($au['surname_ru']) ?>">
                  <button type="button" class="btn small light"
                          data-fill-from-preview="author_<?= $i ?>_surname_ru">Спарсить</button>
                </div>
              </label>

              <label>Имя, Отчество / Initials (RU)
                <div class="field-row">
                  <input id="author_<?= $i ?>_given_ru"
                         name="authors[<?= $i ?>][given_ru]"
                         value="<?= h($au['given_ru']) ?>">
                  <button type="button" class="btn small light"
                          data-fill-from-preview="author_<?= $i ?>_given_ru">Спарсить</button>
                </div>
              </label>

              <label>Surname (EN)
                <div class="field-row">
                  <input id="author_<?= $i ?>_surname_en"
                         name="authors[<?= $i ?>][surname_en]"
                         value="<?= h($au['surname_en']) ?>">
                  <button type="button" class="btn small light"
                          data-fill-from-preview="author_<?= $i ?>_surname_en">Спарсить</button>
                </div>
              </label>

              <label>Initials (EN)
                <div class="field-row">
                  <input id="author_<?= $i ?>_given_en"
                         name="authors[<?= $i ?>][given_en]"
                         value="<?= h($au['given_en']) ?>">
                  <button type="button" class="btn small light"
                          data-fill-from-preview="author_<?= $i ?>_given_en">Спарсить</button>
                </div>
              </label>

              <label>Email
                <div class="field-row">
                  <input id="author_<?= $i ?>_email"
                         name="authors[<?= $i ?>][email]"
                         value="<?= h($au['email']) ?>">
                  <button type="button" class="btn small light"
                          data-fill-from-preview="author_<?= $i ?>_email">Спарсить</button>
                </div>
              </label>

              <label>ORCID
                <div class="field-row">
                  <input id="author_<?= $i ?>_orcid"
                         name="authors[<?= $i ?>][orcid]"
                         value="<?= h($au['orcid']) ?>">
                  <button type="button" class="btn small light"
                          data-fill-from-preview="author_<?= $i ?>_orcid">Спарсить</button>
                </div>
              </label>

              <label>Аффилиация (RU)
                <div class="field-row">
                  <input id="author_<?= $i ?>_aff_ru"
                         name="authors[<?= $i ?>][aff_ru]"
                         value="<?= h($au['aff_ru']) ?>">
                  <button type="button" class="btn small light"
                          data-fill-from-preview="author_<?= $i ?>_aff_ru">Спарсить</button>
                </div>
              </label>

              <label>Affiliation (EN)
                <div class="field-row">
                  <input id="author_<?= $i ?>_aff_en"
                         name="authors[<?= $i ?>][aff_en]"
                         value="<?= h($au['aff_en']) ?>">
                  <button type="button" class="btn small light"
                          data-fill-from-preview="author_<?= $i ?>_aff_en">Спарсить</button>
                </div>
              </label>
            </div>
          </fieldset>
        <?php endforeach; ?>

        <div class="row">
          <button type="submit">Сохранить статью</button>
          <a class="btn light" href="edit.php?id=<?= $id ?>&add_author=1">+ Автор</a>
        </div>

        <h3>Список литературы</h3>
        <div class="grid">
          <label>Русский — по одному источнику на строке
            <div class="field-row field-row-textarea">
              <textarea id="refs_ru" name="refs_ru" rows="6"><?= h(implode("\n", $art['refs_ru'])) ?></textarea>
              <button type="button" class="btn small light" data-fill-from-preview="refs_ru">Спарсить</button>
            </div>
          </label>

          <label>English — one reference per line
            <div class="field-row field-row-textarea">
              <textarea id="refs_en" name="refs_en" rows="6"><?= h(implode("\n", $art['refs_en'])) ?></textarea>
              <button type="button" class="btn small light" data-fill-from-preview="refs_en">Спарсить</button>
            </div>
          </label>
        </div>

        <h3>Финансирование / Funding</h3>
        <div class="grid">
          <label>Финансирование (RU) — по одному проекту/гранту на строке
            <div class="field-row field-row-textarea">
              <textarea id="funding_ru" name="funding_ru" rows="4"><?= h(implode("\n", $art['funding_ru'])) ?></textarea>
              <button type="button" class="btn small light" data-fill-from-preview="funding_ru">Спарсить</button>
            </div>
          </label>

          <label>Funding (EN) — one item per line
            <div class="field-row field-row-textarea">
              <textarea id="funding_en" name="funding_en" rows="4"><?= h(implode("\n", $art['funding_en'])) ?></textarea>
              <button type="button" class="btn small light" data-fill-from-preview="funding_en">Спарсить</button>
            </div>
          </label>
        </div>

      </form>
    </div>

    <!-- Правая колонка: фиксированный предпросмотр HTML выпуска -->
    <aside class="edit-preview">
      <h3>Просмотр выпуска</h3>
      <?php if ($hasHtmlPreview): ?>
        <iframe id="issue-preview" src="preview_html.php"></iframe>
      <?php else: ?>
        <div class="preview-placeholder">
          HTML-файл выпуска не загружен.
        </div>
      <?php endif; ?>
      <p class="preview-hint">
        Выделите фрагмент текста в этом окне, затем нажмите кнопку
        «Спарсить» рядом с нужным полем.
      </p>
    </aside>
  </div>
</div>

<script>
(function () {
  function getPreviewSelection() {
    var frame = document.getElementById('issue-preview');
    if (!frame || !frame.contentWindow) return '';
    try {
      var sel = frame.contentWindow.getSelection();
      return sel ? sel.toString().trim() : '';
    } catch (e) {
      return '';
    }
  }

  document.querySelectorAll('[data-fill-from-preview]').forEach(function (btn) {
    btn.addEventListener('click', function (e) {
      e.preventDefault();
      var targetId = this.getAttribute('data-fill-from-preview');
      var target = document.getElementById(targetId);
      if (!target) return;

      var text = getPreviewSelection();
      if (!text) return;

      if (target.tagName === 'TEXTAREA') {
        // Для многострочных полей добавляем строку
        if (target.value.trim() === '') {
          target.value = text;
        } else {
          target.value = target.value.replace(/\s*$/, '') + "\n" + text;
        }
      } else {
        // Для одиночных полей просто заменяем содержимое
        target.value = text;
      }
    });
  });
})();
</script>
</body>
</html>

<?php
require_once __DIR__ . '/functions.php';
ensure_session();

$issue = $_SESSION['issue'];
$arts  = $_SESSION['articles'];

// Отображение кодов типов в человекочитаемом виде
$typeLabels = [
    'RAR' => 'Научная статья',
    'REV' => 'Обзорная статья',
    'EDI' => 'От редакции',
    'BRV' => 'Рецензия',
    'SCO' => 'Краткое сообщение',
    'CNF' => 'Материалы конференции',
    'MIS' => 'Разное / прочее',
    // Остальные коды (если вдруг появятся) будут показаны как есть
];
?>
<!doctype html>
<html lang="ru">
<head>
  <meta charset="utf-8">
  <title>Выпуск и статьи</title>
  <link rel="stylesheet" href="style.css">
</head>
<body>
<div class="wrap">
  <div class="topbar">
    <a class="btn light" href="reset.php">Сбросить сессию</a>
  </div>

  <h2>Метаданные выпуска</h2>
  <form class="grid" method="post" action="save_issue.php">
    <label>Название журнала (RU)
      <input name="journal_title_ru" value="<?= h($issue['journal_title_ru']) ?>">
    </label>
    <label>Название журнала (EN)
      <input name="journal_title_en" value="<?= h($issue['journal_title_en']) ?>">
    </label>
    <label>ISSN
      <input name="issn" value="<?= h($issue['issn'] ?? '') ?>">
    </label>
    <label>eISSN
      <input name="eissn" value="<?= h($issue['eissn'] ?? '') ?>">
    </label>
    <label>Том
      <input name="volume" value="<?= h($issue['volume']) ?>">
    </label>
    <label>Номер
      <input name="issue" value="<?= h($issue['issue']) ?>">
    </label>
    <label>Год
      <input name="year" value="<?= h($issue['year']) ?>">
    </label>
    <label>Издатель
      <input name="publisher" value="<?= h($issue['publisher']) ?>">
    </label>
    <label>Место издания
      <input name="place" value="<?= h($issue['place']) ?>">
    </label>
    <div class="row">
      <button type="submit">Сохранить выпуск</button>
      <a class="btn" href="download_all.php" style="text-align: center;">Скачать общий XML</a>
      <a class="btn light" href="download_zip.php" style="text-align: center;">Скачать все статьи (ZIP)</a>
    </div>
  </form>

  <h2>Статьи</h2>

  <form id="order-form" method="post" action="reorder_articles.php">
    <input type="hidden" name="order" id="order-input">

    <table class="list reorder-table">
      <thead>
      <tr>
        <th style="width:40px;">#</th>
        <th style="width:40px;">⇅</th>
        <th>DOI</th>
        <th>Название (RU)</th>
        <th>Название (EN)</th>
        <th>Тип</th>
        <th>Страницы</th>
        <th></th>
      </tr>
      </thead>
      <tbody id="articles-tbody">
      <?php foreach ($arts as $i => $a): ?>
        <tr data-index="<?= $i ?>" draggable="true">
          <td class="num"><?= ($i + 1) ?></td>
          <td class="drag-handle">⇅</td>
          <td><?= h($a['doi']) ?></td>
          <td><?= h(mb_strimwidth(($a['title_ru'] ?? '—') ?: '—', 0, 80, '…', 'UTF-8')) ?></td>
          <td><?= h(mb_strimwidth(($a['title_en'] ?? '—') ?: '—', 0, 80, '…', 'UTF-8')) ?></td>
          <td>
            <?php
              $code = trim((string)($a['type'] ?? ''));
              echo h($typeLabels[$code] ?? $code);
            ?>
          </td>
          <td><?= h(($a['fpage'] ?? '') . '–' . ($a['lpage'] ?? '')) ?></td>
          <td><a class="btn small" href="edit.php?id=<?= $i ?>">Редактировать</a></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>

    <div class="row reorder-actions">
      <button type="submit" class="btn small">Сохранить порядок статей</button>
      <span class="muted">Подсказка: перетащите строки таблицы мышью, затем сохраните порядок.</span>
    </div>
  </form>
</div>

<script>
(function () {
  var tbody = document.getElementById('articles-tbody');
  if (!tbody) return;

  var draggedRow = null;

  tbody.addEventListener('dragstart', function (e) {
    var tr = e.target.closest('tr[data-index]');
    if (!tr) return;
    draggedRow = tr;
    tr.classList.add('dragging');
    e.dataTransfer.effectAllowed = 'move';
    // нужно что-то положить в dataTransfer, иначе drag на некоторых браузерах не сработает
    e.dataTransfer.setData('text/plain', tr.dataset.index);
  });

  tbody.addEventListener('dragend', function () {
    if (draggedRow) {
      draggedRow.classList.remove('dragging');
      draggedRow = null;
    }
  });

  tbody.addEventListener('dragover', function (e) {
    e.preventDefault();
    var tr = e.target.closest('tr[data-index]');
    if (!tr || tr === draggedRow) return;

    var rect = tr.getBoundingClientRect();
    var offset = e.clientY - rect.top;
    var halfway = rect.height / 2;

    if (offset > halfway) {
      tr.after(draggedRow);
    } else {
      tr.before(draggedRow);
    }
  });

  var form = document.getElementById('order-form');
  var orderInput = document.getElementById('order-input');

  if (form && orderInput) {
    form.addEventListener('submit', function () {
      var order = [];
      tbody.querySelectorAll('tr[data-index]').forEach(function (tr) {
        order.push(tr.dataset.index);
      });
      orderInput.value = order.join(',');
    });
  }
})();
</script>
</body>
</html>

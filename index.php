<?php
require_once __DIR__ . '/functions.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['xml'])) {
    $bytes = file_get_contents($_FILES['xml']['tmp_name']);

    try {
        $data = parse_doaj_xml($bytes);

        // основные данные выпуска и статей
        $_SESSION['issue']    = $data['issue'];
        $_SESSION['articles'] = $data['articles'];

        // сбрасываем старый HTML выпуска
        unset($_SESSION['issue_html']);

        // опционально сохраняем HTML-вёрстку выпуска, если она загружена
        if (
            isset($_FILES['html']) &&
            is_uploaded_file($_FILES['html']['tmp_name']) &&
            $_FILES['html']['error'] === UPLOAD_ERR_OK &&
            filesize($_FILES['html']['tmp_name']) > 0
        ) {
            $html = file_get_contents($_FILES['html']['tmp_name']);
            // сохраняем "как есть" — это будет показано в iframe preview_html.php
            $_SESSION['issue_html'] = $html;
        }

        // перенаправление до любого вывода
        header('Location: dashboard.php');
        exit;
    } catch (Throwable $e) {
        $error = $e->getMessage();
    }
}
?>
<!doctype html>
<html lang="ru">
<head>
    <meta charset="utf-8">
    <title>Метафора-конвертер (из DOAJ XML)</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
<div class="wrap">
    <h1>Метафора-конвертер (из DOAJ XML)</h1>
    <p>Данный инструмент — это онлайн-редактор и конвертёр, который преобразует выгрузку журнала в формате <strong>DOAJ XML</strong> в XML-файл для ИС «Метафора» по схеме выпусков <strong>journal3</strong>.</p>
    <p>Инструмент разбирает исходный DOAJ XML, даёт отредактировать структуру выпусков и метаданные статей в удобной форме, а затем формирует готовый архив XML для загрузки в «Метафору».</p>

    <form method="post" enctype="multipart/form-data">
        <div class="field-group">
            <label>
                Файл DOAJ XML (обязательно)
                <input type="file" name="xml" accept=".xml" required>
            </label>
        </div>

        <div class="field-group">
            <label>
                HTML-вёрстка выпуска (необязательно, для предпросмотра/парсинга)
                <input type="file" name="html" accept=".html,.htm,text/html">
            </label>
        </div>

        <button type="submit">Загрузить</button>
    </form>
    
<h3>Как работать с инструментом</h3>

<ol>
  <li>
    <strong>Загрузите файл DOAJ XML</strong><br>
    Выберите файл выгрузки журнала в формате DOAJ XML и отправьте его через форму загрузки. 
    Инструмент автоматически прочитает файл и разложит данные по выпускам и статьям в соответствии со схемой <strong>journal3</strong>.
    Также можно загрузить HTML-файл выпуска для ручного парсинга недостающих данных статей.
    Для этого в программе предусмотрена кнопка парсинг (в окне с HTML-файлом необходимо выделить нужные данные и нажать на кнопку «Спарсить» напротив требуемого поля метаданных, а 
    программа сама скопирует выделенную информацию — принцип работы схож с Articulus от РИНЦ).
  </li>
<p></p>
  <li>
    <strong>Проверьте структуру выпусков</strong><br>
    После разбора вы увидите выпуск (том, номер, год, дата выхода) и входящие в него статьи. 
    При необходимости скорректируйте:
    <ul>
      <li>данные журнала (название, ISSN, язык и т.п.);</li>
      <li>параметры выпуска (том, номер, год, заголовок выпуска);</li>
      <li>порядок статей внутри выпуска.</li>
    </ul>
  </li>
  <p></p>
  <li>
    <strong>Отредактируйте метаданные статей</strong><br>
    Для каждой статьи вы можете привести в порядок:
    <ul>
      <li>названия (на русском и английском языках);</li>
      <li>фамилии и инициалы авторов, их аффилиации и e-mail;</li>
      <li>аннотации и ключевые слова;</li>
      <li>страницы, DOI и другие идентификаторы;</li>
      <li>библиографические ссылки.</li>
    </ul>
    <p></p>
    Все изменения сразу учитываются при последующем формировании XML для «Метафоры».
  </li>
  <p></p>
  <li>
    <strong>Проверьте данные перед экспортом</strong><br>
    Убедитесь, что обязательные поля (журнал, выпуск, статьи, авторы) заполнены. 
    При необходимости вернитесь к нужной статье и исправьте данные.
  </li>
  <p></p>
  <li>
    <strong>Сформируйте файл XML для ИС «Метафора»</strong><br>
    После проверки нажмите кнопку формирования XML-файла. 
    Инструмент создаст XML-файл по схеме <strong>journal3</strong>, который можно загрузить в ИС «Метафора».
  </li>
</ol>

<p>Инструмент предназначен для редакций и технических специалистов, которые работают с DOAJ XML и ИС «Метафора» и хотят ускорить перенос и вычитку метаданных между системами.</p>
<p><strong>Разработчик</strong>: к.и.н. Растям Туктарович Алиев.</p>


    <?php if (!empty($error)): ?>
        <div class="error">
            <?= htmlspecialchars($error, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>
        </div>
    <?php endif; ?>
</div>
</body>
</html>

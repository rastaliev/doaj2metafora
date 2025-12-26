<?php
require_once __DIR__ . '/functions.php';
ensure_session();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['order'])) {
    $orderStr = trim((string)$_POST['order']);
    if ($orderStr !== '') {
        $indexes = explode(',', $orderStr);
        $indexes = array_map('intval', $indexes);

        $old = $_SESSION['articles'] ?? [];
        $new = [];

        foreach ($indexes as $idx) {
            if (isset($old[$idx])) {
                $new[] = $old[$idx];
            }
        }

        // если по каким-то причинам что-то потеряли — добавим «хвостом»
        foreach ($old as $idx => $art) {
            if (!in_array($idx, $indexes, true)) {
                $new[] = $art;
            }
        }

        $_SESSION['articles'] = $new;
    }
}

header('Location: dashboard.php');
exit;

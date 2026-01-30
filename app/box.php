<?php
// box.php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/db.php';

$pdo = getPDO();
$table = 'boxid';

/**
 * カラム名やテーブル名をクォート
 */
function quoteIdentifier(string $name): string
{
    return '`' . str_replace('`', '``', $name) . '`';
}

/**
 * テーブル構成を取得
 */
function fetchTableColumns(PDO $pdo, string $table): array
{
    $stmt = $pdo->query('DESCRIBE ' . quoteIdentifier($table));
    return $stmt->fetchAll(PDO::FETCH_COLUMN) ?: [];
}

/**
 * HTML エスケープ
 */
function escapeHtml($value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

/**
 * CSV をストリーム出力
 */
function outputCsv(array $columns, array $rows, string $filename = 'export.csv'): void
{
    $encode = static function ($value) {
        $str = (string)($value ?? '');
        return mb_convert_encoding($str, 'SJIS-win', 'UTF-8');
    };

    header('Content-Type: text/csv; charset=Shift_JIS');
    header('Content-Disposition: attachment; filename="' . str_replace('"', '', $filename) . '"');
    $out = fopen('php://output', 'w');
    if ($out === false) {
        exit;
    }
    // ヘッダー行
    fputcsv($out, array_map($encode, $columns));
    foreach ($rows as $row) {
        $line = [];
        foreach ($columns as $col) {
            $line[] = $encode($row[$col] ?? '');
        }
        fputcsv($out, $line);
    }
    fclose($out);
    exit;
}

$columns = fetchTableColumns($pdo, $table);
if (empty($columns)) {
    die('boxid の構成を取得できませんでした。');
}

// Primary Key を特定（通常は 'id'）
$primaryKey = null;
foreach ($columns as $col) {
    if (strcasecmp($col, 'id') === 0) {
        $primaryKey = $col;
        break;
    }
}
if ($primaryKey === null) {
    $primaryKey = $columns[0];
}

$serialColumn = null;
foreach ($columns as $col) {
    if (strcasecmp($col, 'serial') === 0) {
        $serialColumn = $col;
        break;
    }
}

$boxColumn = null;
foreach ($columns as $col) {
    if (strcasecmp($col, 'box') === 0) {
        $boxColumn = $col;
        break;
    }
}

$quotedTable      = quoteIdentifier($table);
$primaryKeyQuoted = quoteIdentifier($primaryKey);

$messages      = [];
$errors        = [];
$logs          = [];
$waitingSearch = false;
$exportType    = (string)($_GET['export'] ?? '');
$exportCsv     = ($exportType === 'csv');
$exportTotal   = ($exportType === 'total');
$exportAllSerial = ($exportType === 'all_serial');
$dateFrom      = trim((string)($_GET['date_from'] ?? ''));
$dateTo        = trim((string)($_GET['date_to'] ?? ''));

// --- まず GET パラメータから検索条件を初期化 ---------------------------------
$searchSerial = trim((string)($_GET['serial_scan'] ?? ''));
$searchBox    = trim((string)($_GET['box_scan'] ?? ''));
$boxOnlyMode  = isset($_GET['box_only']) && $_GET['box_only'] === '1';
$limitStr     = trim((string)($_GET['limit'] ?? 'all'));
$limit        = null;
if ($limitStr !== '' && strtolower($limitStr) !== 'all') {
    $limit = (ctype_digit($limitStr) && (int)$limitStr > 0) ? (int)$limitStr : 50;
}

// --- POST（削除）時は、POST 側の条件を優先して上書き ---------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $postSerial = trim((string)($_POST['serial_scan'] ?? ''));
    if ($postSerial !== '') {
        $searchSerial = $postSerial;
    }
    $postBox = trim((string)($_POST['box_scan'] ?? ''));
    if ($postBox !== '') {
        $searchBox = $postBox;
    }

    $postLimit = trim((string)($_POST['limit'] ?? ''));
    if ($postLimit !== '' && strtolower($postLimit) === 'all') {
        $limit = null;
    } elseif (ctype_digit($postLimit) && (int)$postLimit > 0) {
        $limit = (int)$postLimit;
    }
    $postDateFrom = trim((string)($_POST['date_from'] ?? ''));
    $postDateTo = trim((string)($_POST['date_to'] ?? ''));
    if ($postDateFrom !== '') $dateFrom = $postDateFrom;
    if ($postDateTo !== '') $dateTo = $postDateTo;
    $boxOnlyMode = isset($_POST['box_only']) && $_POST['box_only'] === '1';

    // 削除アクション処理
    $action = $_POST['action'] ?? '';
    if ($action === 'delete_selected') {
        $selected = $_POST['selected'] ?? [];
        if (!is_array($selected) || count($selected) === 0) {
            $errors[] = '削除対象のレコードを選択してください。';
        } else {
            $ids = [];
            foreach ($selected as $raw) {
                if (ctype_digit((string)$raw)) {
                    $ids[] = (int)$raw;
                }
            }
            if (empty($ids)) {
                $errors[] = '削除対象のIDが正しくありません。';
            } else {
                $ph = [];
                $params = [];
                foreach ($ids as $i => $idVal) {
                    $key = ':id' . $i;
                    $ph[] = $key;
                    $params[$key] = $idVal;
                }
                try {
                    $sql = "DELETE FROM {$quotedTable} WHERE {$primaryKeyQuoted} IN (" . implode(',', $ph) . ")";
                    $stmt = $pdo->prepare($sql);
                    $stmt->execute($params);
                    $messages[] = $stmt->rowCount() . ' 件削除しました。';
                } catch (Throwable $e) {
                    $errors[] = "削除に失敗しました: " . $e->getMessage();
                }
            }
        }
    } elseif ($action === 'delete' || $action === 'delete_single') {
        $idRaw = trim((string)($_POST['id'] ?? ''));
        if (!ctype_digit($idRaw) || (int)$idRaw <= 0) {
            $errors[] = '有効なIDを指定してください。';
        } else {
            $idValue = (int)$idRaw;
            try {
                $sql  = "DELETE FROM {$quotedTable} WHERE {$primaryKeyQuoted} = :id";
                $stmt = $pdo->prepare($sql);
                $stmt->bindValue(':id', $idValue, PDO::PARAM_INT);
                $stmt->execute();

                if ($stmt->rowCount() > 0) {
                    $messages[] = "ID #{$idValue} を削除しました。";
                } else {
                    $messages[] = "ID #{$idValue} のレコードは既に削除済みか存在しません。";
                }
            } catch (Throwable $e) {
                $errors[] = "削除に失敗しました: " . $e->getMessage();
            }
        }
    } elseif ($action !== '') {
        // action が空以外で delete でもなければ不正
        $errors[] = '不正なアクションです。';
    }
}

// --- 検索実行 -----------------------------------------------------------
$filters = [];
$params  = [];
if ($searchSerial !== '' && $serialColumn !== null) {
    $filters[] = quoteIdentifier($serialColumn) . ' = :serial';
    $params[':serial'] = $searchSerial;
}
if ($searchBox !== '' && $boxColumn !== null) {
    $filters[] = quoteIdentifier($boxColumn) . ' = :box';
    $params[':box'] = $searchBox;
}
if ($dateFrom !== '') {
    $filters[] = "regtime >= :date_from";
    $params[':date_from'] = $dateFrom . ' 00:00:00';
}
if ($dateTo !== '') {
    $filters[] = "regtime <= :date_to";
    $params[':date_to'] = $dateTo . ' 23:59:59';
}

// --- TOTAL CSV 出力（boxid + fail_log を1行集計） ---------------------------
if ($exportTotal) {
    $totalFilters = [];
    $totalParams  = [];
    if ($searchSerial !== '' && $serialColumn !== null) {
        $totalFilters[] = 'b.serial = :serial';
        $totalParams[':serial'] = $searchSerial;
    }
    if ($searchBox !== '' && $boxColumn !== null) {
        $totalFilters[] = 'b.box = :box';
        $totalParams[':box'] = $searchBox;
    }
    if ($dateFrom !== '') {
        $totalFilters[] = 'b.regtime >= :date_from';
        $totalParams[':date_from'] = $dateFrom . ' 00:00:00';
    }
    if ($dateTo !== '') {
        $totalFilters[] = 'b.regtime <= :date_to';
        $totalParams[':date_to'] = $dateTo . ' 23:59:59';
    }

    $sql = "SELECT b.`regtime` AS regtime, b.`serial` AS serial, b.`result` AS result, b.`box` AS box
            FROM {$quotedTable} b";
    if (!empty($totalFilters)) {
        $sql .= ' WHERE ' . implode(' AND ', $totalFilters);
    }
    $sql .= ' ORDER BY b.`regtime` DESC';

    $stmt = $pdo->prepare($sql);
    foreach ($totalParams as $key => $value) {
        $stmt->bindValue($key, $value, PDO::PARAM_STR);
    }
    $stmt->execute();
    $boxRows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

    $serials = [];
    foreach ($boxRows as $row) {
        $serial = trim((string)($row['serial'] ?? ''));
        if ($serial !== '') $serials[] = $serial;
    }
    $serials = array_values(array_unique($serials));

    $defectsBySerial = [];
    $maxDefects = 0;
    if (!empty($serials)) {
        $failTable = quoteIdentifier('fail_log');
        foreach (array_chunk($serials, 900) as $chunk) {
            $placeholders = [];
            $binds = [];
            foreach ($chunk as $i => $serial) {
                $ph = ':s' . $i;
                $placeholders[] = $ph;
                $binds[$ph] = $serial;
            }
            $defSql = "SELECT `serial`, `cate`, `parts`, `symptom`, `position`
                       FROM {$failTable}
                       WHERE `serial` IN (" . implode(',', $placeholders) . ")
                       ORDER BY `serial`, `id`";
            $defStmt = $pdo->prepare($defSql);
            foreach ($binds as $key => $value) {
                $defStmt->bindValue($key, $value, PDO::PARAM_STR);
            }
            $defStmt->execute();
            while ($row = $defStmt->fetch(PDO::FETCH_ASSOC)) {
                $s = trim((string)($row['serial'] ?? ''));
                if ($s === '') continue;
                $defectsBySerial[$s][] = [
                    'cate' => $row['cate'] ?? '',
                    'parts' => $row['parts'] ?? '',
                    'symptom' => $row['symptom'] ?? '',
                    'position' => $row['position'] ?? '',
                ];
            }
        }
    }

    foreach ($defectsBySerial as $list) {
        $maxDefects = max($maxDefects, count($list));
    }

    $columns = ['日付', 'シリアル', '判定', 'BOXID'];
    for ($i = 1; $i <= $maxDefects; $i++) {
        $columns[] = "不良{$i}_カテゴリ";
        $columns[] = "不良{$i}_部品";
        $columns[] = "不良{$i}_症状";
        $columns[] = "不良{$i}_位置";
    }

    $totalRows = [];
    foreach ($boxRows as $row) {
        $serial = trim((string)($row['serial'] ?? ''));
        $defects = $serial !== '' ? ($defectsBySerial[$serial] ?? []) : [];
        $line = [
            '日付' => $row['regtime'] ?? '',
            'シリアル' => $serial,
            '判定' => $row['result'] ?? '',
            'BOXID' => $row['box'] ?? '',
        ];
        for ($i = 0; $i < $maxDefects; $i++) {
            $def = $defects[$i] ?? null;
            $idx = $i + 1;
            $line["不良{$idx}_カテゴリ"] = $def['cate'] ?? '';
            $line["不良{$idx}_部品"] = $def['parts'] ?? '';
            $line["不良{$idx}_症状"] = $def['symptom'] ?? '';
            $line["不良{$idx}_位置"] = $def['position'] ?? '';
        }
        $totalRows[] = $line;
    }

    $filename = 'boxid_total_' . date('Ymd_His') . '.csv';
    outputCsv($columns, $totalRows, $filename);
}

// --- 全シリアル CSV 出力（cate_end からシリアル取得 + fail_log を横並び） ---------------------------
if ($exportAllSerial) {
    $cateEndTable = quoteIdentifier('cate_end');
    $failLogTable = quoteIdentifier('fail_log');

    // cate_end からシリアルを重複なしで取得（日付フィルターあれば適用）
    $serialFilters = [];
    $serialParams = [];
    if ($dateFrom !== '') {
        $serialFilters[] = 'regtime >= :date_from';
        $serialParams[':date_from'] = $dateFrom . ' 00:00:00';
    }
    if ($dateTo !== '') {
        $serialFilters[] = 'regtime <= :date_to';
        $serialParams[':date_to'] = $dateTo . ' 23:59:59';
    }

    $serialSql = "SELECT DISTINCT serial FROM {$cateEndTable}";
    if (!empty($serialFilters)) {
        $serialSql .= ' WHERE ' . implode(' AND ', $serialFilters);
    }
    $serialSql .= ' ORDER BY serial';

    $serialStmt = $pdo->prepare($serialSql);
    foreach ($serialParams as $key => $value) {
        $serialStmt->bindValue($key, $value, PDO::PARAM_STR);
    }
    $serialStmt->execute();
    $allSerials = $serialStmt->fetchAll(PDO::FETCH_COLUMN) ?: [];

    // カテゴリの優先順序を定義（機能検査 > A面 > B面 > C面 > D面）
    $categoryPriority = ['機能検査' => 1, 'A面' => 2, 'B面' => 3, 'C面' => 4, 'D面' => 5];

    // fail_log から各シリアルの不良データを取得
    $failDataBySerial = [];
    $maxDefectsPerSerial = 0;

    if (!empty($allSerials)) {
        foreach (array_chunk($allSerials, 900) as $chunk) {
            $placeholders = [];
            $binds = [];
            foreach ($chunk as $i => $serial) {
                $ph = ':s' . $i;
                $placeholders[] = $ph;
                $binds[$ph] = $serial;
            }
            $failSql = "SELECT `serial`, `cate`, `parts`, `symptom`, `position`
                        FROM {$failLogTable}
                        WHERE `serial` IN (" . implode(',', $placeholders) . ")
                        ORDER BY `serial`, `id`";
            $failStmt = $pdo->prepare($failSql);
            foreach ($binds as $key => $value) {
                $failStmt->bindValue($key, $value, PDO::PARAM_STR);
            }
            $failStmt->execute();
            while ($row = $failStmt->fetch(PDO::FETCH_ASSOC)) {
                $s = trim((string)($row['serial'] ?? ''));
                if ($s === '') continue;
                $failDataBySerial[$s][] = [
                    'cate' => $row['cate'] ?? '',
                    'parts' => $row['parts'] ?? '',
                    'symptom' => $row['symptom'] ?? '',
                    'position' => $row['position'] ?? '',
                ];
            }
        }
    }

    // 各シリアルの不良データをカテゴリ優先順でソート
    foreach ($failDataBySerial as $serial => &$defects) {
        usort($defects, function($a, $b) use ($categoryPriority) {
            $prioA = $categoryPriority[$a['cate']] ?? 99;
            $prioB = $categoryPriority[$b['cate']] ?? 99;
            return $prioA - $prioB;
        });
        $maxDefectsPerSerial = max($maxDefectsPerSerial, count($defects));
    }
    unset($defects);

    // boxid テーブルから判定とBOXID情報を取得
    $boxidTable = quoteIdentifier('boxid');
    $boxDataBySerial = [];
    if (!empty($allSerials)) {
        foreach (array_chunk($allSerials, 900) as $chunk) {
            $placeholders = [];
            $binds = [];
            foreach ($chunk as $i => $serial) {
                $ph = ':bs' . $i;
                $placeholders[] = $ph;
                $binds[$ph] = $serial;
            }
            $boxSql = "SELECT `serial`, `result`, `box`, `regtime`
                       FROM {$boxidTable}
                       WHERE `serial` IN (" . implode(',', $placeholders) . ")";
            $boxStmt = $pdo->prepare($boxSql);
            foreach ($binds as $key => $value) {
                $boxStmt->bindValue($key, $value, PDO::PARAM_STR);
            }
            $boxStmt->execute();
            while ($row = $boxStmt->fetch(PDO::FETCH_ASSOC)) {
                $s = trim((string)($row['serial'] ?? ''));
                if ($s === '') continue;
                $boxDataBySerial[$s] = [
                    'result' => $row['result'] ?? '',
                    'box' => $row['box'] ?? '',
                    'regtime' => $row['regtime'] ?? '',
                ];
            }
        }
    }

    // カラムヘッダーの作成
    $allSerialColumns = ['シリアル', '判定', 'BOXID'];
    for ($i = 1; $i <= $maxDefectsPerSerial; $i++) {
        $allSerialColumns[] = "不良{$i}_カテゴリ";
        $allSerialColumns[] = "不良{$i}_部品";
        $allSerialColumns[] = "不良{$i}_症状";
        $allSerialColumns[] = "不良{$i}_位置";
    }

    // 各シリアルのデータ行を作成
    $allSerialRows = [];
    foreach ($allSerials as $serial) {
        $serial = trim((string)$serial);
        if ($serial === '') continue;

        $boxInfo = $boxDataBySerial[$serial] ?? [];
        $defects = $failDataBySerial[$serial] ?? [];

        $line = [
            'シリアル' => $serial,
            '判定' => $boxInfo['result'] ?? '',
            'BOXID' => $boxInfo['box'] ?? '',
        ];

        for ($i = 0; $i < $maxDefectsPerSerial; $i++) {
            $def = $defects[$i] ?? null;
            $idx = $i + 1;
            $line["不良{$idx}_カテゴリ"] = $def['cate'] ?? '';
            $line["不良{$idx}_部品"] = $def['parts'] ?? '';
            $line["不良{$idx}_症状"] = $def['symptom'] ?? '';
            $line["不良{$idx}_位置"] = $def['position'] ?? '';
        }
        $allSerialRows[] = $line;
    }

    $filename = 'all_serial_' . date('Ymd_His') . '.csv';
    outputCsv($allSerialColumns, $allSerialRows, $filename);
}

// 検索が実行されたかどうか（GET/POSTがあれば実行）
$isSearchExecuted = ($_SERVER['REQUEST_METHOD'] === 'POST') || isset($_GET['limit']) || isset($_GET['serial_scan']) || isset($_GET['box_scan']) || isset($_GET['date_from']) || isset($_GET['date_to']) || isset($_GET['box_only']);

if (!$isSearchExecuted) {
    // まだ検索が実行されていない状態
    $waitingSearch = true;
} else {
    try {
        // limit の上限を一応決めておく（必要に応じて調整）
        if ($limit !== null) {
            $limit = max(1, min($limit, 1000));
        }

        if ($boxOnlyMode) {
            // BOXIDのみ表示モード（ユニークなBOXIDと件数）
            $sql = "SELECT `box`, COUNT(*) as cnt, MAX(`regtime`) as last_regtime FROM {$quotedTable}";
            if (!empty($filters)) {
                $sql .= ' WHERE ' . implode(' AND ', $filters);
            }
            $sql .= " GROUP BY `box` ORDER BY last_regtime DESC";
            if ($limit !== null) {
                $sql .= " LIMIT {$limit}";
            }
            $stmt = $pdo->prepare($sql);
            foreach ($params as $key => $value) {
                $stmt->bindValue($key, $value, PDO::PARAM_STR);
            }
            $stmt->execute();
            $logs = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        } else {
            // 通常モード
            $sql = "SELECT * FROM {$quotedTable}";
            if (!empty($filters)) {
                $sql .= ' WHERE ' . implode(' AND ', $filters);
            }
            $sql .= " ORDER BY regtime DESC";
            if ($limit !== null) {
                $sql .= " LIMIT {$limit}";
            }
            $stmt = $pdo->prepare($sql);
            foreach ($params as $key => $value) {
                $stmt->bindValue($key, $value, PDO::PARAM_STR);
            }
            $stmt->execute();
            $logs = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        }

        if (empty($logs)) {
            if ($searchSerial !== '') {
                $errors[] = "シリアル「{$searchSerial}」に一致するレコードがありません。";
            } elseif ($searchBox !== '') {
                $errors[] = "BOXID「{$searchBox}」に一致するレコードがありません。";
            }
        }
    } catch (Throwable $e) {
        $errors[] = "検索に失敗しました: " . $e->getMessage();
    }
}

// --- CSV 出力（検索済みの結果をそのまま出力） -------------------------------
if ($exportCsv) {
    $filename = 'boxid_' . date('Ymd_His') . '.csv';
    outputCsv($columns, $logs, $filename);
}
?>
<!doctype html>
<html lang="ja">

<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <link rel="icon" href="judge-icon2.png" sizes="32x32" type="image/png">
    <title>boxid 管理</title>
    <style>
        body {
            margin: 0;
            padding: 20px;
            font-family: system-ui, -apple-system, "Segoe UI", "Noto Sans JP", sans-serif;
            background: #f8fafc;
            color: #0f172a;
        }
        .cornerThumb {
            position: fixed;
            top: 12px;
            right: 12px;
            width: 48px;
            height: 48px;
            border-radius: 8px;
            overflow: hidden;
            box-shadow: 0 6px 14px rgba(0, 0, 0, 0.12);
            background: #e5e7eb;
            z-index: 10;
        }

        .cornerThumb img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            display: block;
        }

        h1 {
            margin: 0 0 16px;
            font-size: 28px;
        }

        .toolbar {
            display: flex;
            gap: 12px;
            flex-wrap: wrap;
            margin-bottom: 16px;
            align-items: center;
        }

        .toolbar a.buttonLink {
            padding: 10px 18px;
            border-radius: 999px;
            background: #111827;
            color: #fff;
            text-decoration: none;
            font-weight: 700;
        }

        .status {
            margin-bottom: 16px;
            padding: 12px 16px;
            border-radius: 8px;
        }

        .status.ok {
            background: #dcfce7;
            color: #166534;
        }

        .status.err {
            background: #fee2e2;
            color: #991b1b;
        }

        .hint {
            margin: 0 0 12px;
            color: #475569;
            font-size: 0.95em;
        }

        .filterForm {
            display: flex;
            flex-wrap: wrap;
            gap: 12px;
            padding: 16px;
            background: #fff;
            border-radius: 12px;
            border: 1px solid #e2e8f0;
            margin-bottom: 20px;
        }

        .filterForm label {
            display: flex;
            flex-direction: column;
            font-size: 0.9em;
            color: #475569;
        }

        .filterForm input,
        .filterForm select {
            min-width: 160px;
            padding: 6px 10px;
            border-radius: 6px;
            border: 1px solid #cbd5f5;
            font-size: 1em;
        }

        .filterForm .checkboxLabel {
            flex-direction: row;
            align-items: center;
            gap: 6px;
            font-weight: 600;
            cursor: pointer;
        }
        .filterForm .checkboxLabel input[type="checkbox"] {
            min-width: auto;
            width: 18px;
            height: 18px;
            cursor: pointer;
        }

        .filterForm button {
            padding: 10px 16px;
            border-radius: 8px;
            border: none;
            background: #2563eb;
            color: #fff;
            font-weight: 700;
            cursor: pointer;
        }
        .dateFilterSection {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
            align-items: center;
            padding: 12px;
            background: #f1f5f9;
            border-radius: 8px;
            width: 100%;
            margin-top: 8px;
        }
        .dateFilterSection label {
            display: flex;
            flex-direction: column;
            font-size: 0.85em;
            color: #475569;
        }
        .dateFilterSection input[type="date"] {
            padding: 6px 8px;
            border: 1px solid #cbd5e1;
            border-radius: 6px;
            font-size: 0.95em;
            min-width: 140px;
        }
        .quickDateBtns {
            display: flex;
            flex-wrap: wrap;
            gap: 4px;
            margin-left: 8px;
        }
        .quickDateBtn {
            padding: 3px 8px;
            border-radius: 4px;
            border: 1px solid #cbd5e1;
            background: #fff;
            color: #334155;
            font-size: 0.75em;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.15s;
        }
        .quickDateBtn:hover {
            background: #e2e8f0;
            border-color: #94a3b8;
        }
        .quickDateBtn.active {
            background: #2563eb;
            color: #fff;
            border-color: #2563eb;
        }
        .dateSummary {
            font-size: 0.9em;
            color: #475569;
            margin-left: auto;
        }

        .logTableWrap {
            background: #fff;
            border-radius: 12px;
            border: 1px solid #e2e8f0;
            padding: 12px;
            overflow-x: auto;
        }

        table.logTable {
            width: 100%;
            border-collapse: collapse;
            min-width: 520px;
            table-layout: fixed;
        }

        table.logTable th,
        table.logTable td {
            border: 1px solid #e2e8f0;
            padding: 5px;
            text-align: left;
        }

        table.logTable th {
            background: #f8fafc;
            font-size: 0.95em;
            color: #475569;
            font-weight: 700;
        }

        .cellText {
            display: block;
            padding: 4px 2px;
            font-size: 0.95em;
            word-break: break-word;
        }

        .actionsCell {
            white-space: nowrap;
        }

        .rowButton {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            padding: 6px 12px;
            margin-right: 6px;
            border-radius: 6px;
            border: none;
            font-weight: 700;
            cursor: pointer;
            color: #fff;
            font-size: 0.9em;
        }

        .rowButton.delete {
            background: #dc2626;
        }
        .serialLink {
            color: #2563eb;
            font-weight: 700;
        }
        .serialLink:hover {
            text-decoration: underline;
        }
        .boxLink {
            color: #059669;
            font-weight: 700;
        }
        .boxLink:hover {
            text-decoration: underline;
        }

        .rowButton.delete:hover {
            background: #b91c1c;
        }

        .noRows {
            text-align: center;
            padding: 40px;
            background: #fff;
            border-radius: 12px;
            border: 1px dashed #cbd5f5;
            color: #94a3b8;
        }

        .recordCount {
            font-size: 0.9em;
            color: #475569;
            margin-top: 8px;
        }
        .selectCell {
            width: 50px;
            text-align: center;
        }
        .bulkActions {
            margin-top: 10px;
            display: flex;
            gap: 8px;
            align-items: center;
        }
    </style>
</head>

<body>
    <a class="cornerThumb" href="home.php" title="HOMEへ" aria-label="HOMEへ">
        <img src="judge-icon2.png" alt="ホームロゴ">
    </a>
    <div class="toolbar">
        <h1>boxid 管理</h1>
        <a class="buttonLink" href="home.php" target="_blank" rel="noopener">HOME</a>
    </div>

    <?php foreach ($messages as $msg): ?>
        <div class="status ok"><?= escapeHtml($msg) ?></div>
    <?php endforeach; ?>
    <?php foreach ($errors as $err): ?>
        <div class="status err"><?= escapeHtml($err) ?></div>
    <?php endforeach; ?>

    <form method="get" class="filterForm">
        <label>
            シリアルスキャン
            <input type="text" name="serial_scan" value="<?= escapeHtml($searchSerial) ?>" autofocus />
        </label>
        <label>
            BOXID
            <input type="text" name="box_scan" value="<?= escapeHtml($searchBox) ?>" />
        </label>
        <label>
            表示件数
            <select name="limit">
                <option value="all" <?= $limit === null ? 'selected' : '' ?>>全件</option>
                <?php foreach ([20, 50, 100, 150, 200] as $opt): ?>
                    <option value="<?= $opt ?>" <?= $limit === $opt ? 'selected' : '' ?>><?= $opt ?></option>
                <?php endforeach; ?>
            </select>
        </label>
        <label class="checkboxLabel">
            <input type="checkbox" name="box_only" value="1" <?= $boxOnlyMode ? 'checked' : '' ?> />
            BOXIDのみ表示
        </label>
        <button type="submit">検索</button>
        <div class="dateFilterSection">
            <label>
                開始日
                <input type="date" name="date_from" id="fieldDateFrom" value="<?= escapeHtml($dateFrom) ?>" />
            </label>
            <label>
                終了日
                <input type="date" name="date_to" id="fieldDateTo" value="<?= escapeHtml($dateTo) ?>" />
            </label>
            <div class="quickDateBtns">
                <button type="button" class="quickDateBtn" data-range="today">今日</button>
                <button type="button" class="quickDateBtn" data-range="week">今週</button>
                <button type="button" class="quickDateBtn" data-range="all">全期間</button>
            </div>
            <span class="dateSummary" id="dateSummary">
                <?= ($dateFrom || $dateTo) ? ('期間: ' . escapeHtml($dateFrom ?: '指定なし') . ' 〜 ' . escapeHtml($dateTo ?: '指定なし')) : '期間: 指定なし' ?>
            </span>
        </div>
        <button type="submit" name="export" value="csv">CSV出力</button>
        <button type="submit" name="export" value="total">TOTAL出力</button>
        <button type="submit" name="export" value="all_serial">全シリアル</button>
        <button type="button" id="btnClearFilters">クリア</button>
    </form>

    <?php if ($waitingSearch): ?>
        <div class="noRows">検索ボタンを押すと全件表示されます。シリアルやBOXIDで絞り込むこともできます。</div>
    <?php elseif (empty($logs)): ?>
        <div class="noRows">レコードが見つかりません。</div>
    <?php elseif ($boxOnlyMode): ?>
        <p class="hint">BOXIDをクリックすると、そのBOXIDに登録されたシリアル一覧が表示されます。</p>
        <div class="logTableWrap">
            <div class="bulkActions">
                <span class="recordCount">
                    BOXID数: <?= count($logs) ?> 件
                </span>
            </div>
            <table class="logTable">
                <thead>
                    <tr>
                        <th>BOXID</th>
                        <th>シリアル数</th>
                        <th>最終登録日時</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($logs as $row): ?>
                        <?php $boxValue = $row['box'] ?? ''; ?>
                        <tr>
                            <td>
                                <?php if ($boxValue !== ''): ?>
                                    <?php $boxDetailLink = 'box.php?box_scan=' . rawurlencode((string)$boxValue) . '&limit=' . urlencode($limit === null ? 'all' : (string)$limit); ?>
                                    <a class="cellText boxLink" href="<?= escapeHtml($boxDetailLink) ?>">
                                        <?= escapeHtml($boxValue) ?>
                                    </a>
                                <?php else: ?>
                                    <span class="cellText"><?= escapeHtml($boxValue) ?></span>
                                <?php endif; ?>
                            </td>
                            <td><span class="cellText"><?= escapeHtml($row['cnt'] ?? '') ?></span></td>
                            <td><span class="cellText"><?= escapeHtml($row['last_regtime'] ?? '') ?></span></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php else: ?>
        <p class="hint">チェックした行をまとめて削除できます。個別削除ボタンも利用可能です。</p>
        <form method="post">
            <input type="hidden" name="serial_scan" value="<?= escapeHtml($searchSerial) ?>" />
            <input type="hidden" name="box_scan" value="<?= escapeHtml($searchBox) ?>" />
            <input type="hidden" name="box_only" value="<?= $boxOnlyMode ? '1' : '0' ?>" />
            <input type="hidden" name="limit" value="<?= escapeHtml($limit === null ? 'all' : (string)$limit) ?>" />
            <input type="hidden" name="date_from" value="<?= escapeHtml($dateFrom) ?>" />
            <input type="hidden" name="date_to" value="<?= escapeHtml($dateTo) ?>" />
            <input type="hidden" name="id" id="singleIdField" value="">
            <div class="logTableWrap">
                <div class="bulkActions">
                <button class="rowButton delete" type="submit" name="action" value="delete_selected" onclick="return confirm('選択した行を削除します。よろしいですか？');">選択行を削除</button>
                <span class="recordCount">
                    合計: <?= count($logs) ?> 件
                    <?= $limit === null ? '(全件表示)' : '' ?>
                </span>
            </div>
                <table class="logTable">
                    <thead>
                        <tr>
                            <th class="selectCell"><input type="checkbox" id="chkAll" onclick="toggleAll(this)"></th>
                            <?php foreach ($columns as $col): ?>
                                <th><?= escapeHtml($col) ?></th>
                            <?php endforeach; ?>
                            <th>操作</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($logs as $row): ?>
                            <?php $rowId = $row[$primaryKey] ?? ''; ?>
                            <tr>
                                <td class="selectCell">
                                    <?php if ($rowId !== ''): ?>
                                        <input type="checkbox" name="selected[]" value="<?= escapeHtml($rowId) ?>">
                                    <?php endif; ?>
                                </td>
                                <?php foreach ($columns as $col): ?>
                                    <?php $value = $row[$col] ?? ''; ?>
                                    <td>
                                        <?php if ($serialColumn !== null && strcasecmp($col, $serialColumn) === 0 && $value !== ''): ?>
                                            <?php $serialLink = 'edit.php?serial_scan=' . rawurlencode((string)$value) . '&limit=' . urlencode((string)$limit); ?>
                                            <a class="cellText serialLink" href="<?= escapeHtml($serialLink) ?>" target="_blank" rel="noopener">
                                                <?= escapeHtml($value) ?>
                                            </a>
                                        <?php elseif ($boxColumn !== null && strcasecmp($col, $boxColumn) === 0 && $value !== ''): ?>
                                            <?php $boxDetailLink = 'box.php?box_scan=' . rawurlencode((string)$value) . '&limit=' . urlencode($limit === null ? 'all' : (string)$limit); ?>
                                            <a class="cellText boxLink" href="<?= escapeHtml($boxDetailLink) ?>">
                                                <?= escapeHtml($value) ?>
                                            </a>
                                        <?php else: ?>
                                            <span class="cellText"><?= escapeHtml($value) ?></span>
                                        <?php endif; ?>
                                    </td>
                                <?php endforeach; ?>
                                <td class="actionsCell">
                                    <button
                                        class="rowButton delete"
                                        type="submit"
                                        name="action"
                                        value="delete_single"
                                        onclick="return setSingleDeleteId('<?= escapeHtml($rowId) ?>');"
                                    >削除</button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            
        </form>
    <?php endif; ?>
    <script>
        function toggleAll(master) {
            const checked = master.checked;
            document.querySelectorAll('input[name="selected[]"]').forEach(cb => {
                cb.checked = checked;
            });
        }
        function setSingleDeleteId(id) {
            const field = document.getElementById('singleIdField');
            if (field) field.value = id;
            return confirm(`ID #${id} を削除しますか？`);
        }
        // 入力を半角に統一（機能は維持）
        (function () {
            const toHalfWidth = (value = '') => {
                const converted = value.replace(/[！-～]/g, ch => String.fromCharCode(ch.charCodeAt(0) - 0xFEE0)).replace(/　/g, ' ');
                return converted.replace(/[^\x20-\x7E]/g, '');
            };
            const enforceHalfwidthInput = (input) => {
                if (!input) return;
                let composing = false;
                const normalize = () => {
                    const orig = input.value;
                    const converted = toHalfWidth(orig);
                    if (converted === orig) return;
                    const start = input.selectionStart;
                    const delta = orig.length - converted.length;
                    input.value = converted;
                    if (typeof start === 'number') {
                        const pos = Math.max(0, start - delta);
                        input.setSelectionRange(pos, pos);
                    }
                };
                input.addEventListener('compositionstart', () => composing = true);
                input.addEventListener('compositionend', () => {
                    composing = false;
                    normalize();
                });
                input.addEventListener('input', (e) => {
                    if (composing || e.isComposing) return;
                    normalize();
                });
            };
            enforceHalfwidthInput(document.querySelector('input[name="serial_scan"]'));
            enforceHalfwidthInput(document.querySelector('input[name="box_scan"]'));

            const clearBtn = document.getElementById('btnClearFilters');
            const filterForm = document.querySelector('.filterForm');
            if (clearBtn && filterForm) {
                clearBtn.addEventListener('click', () => {
                    const serialInput = filterForm.querySelector('input[name="serial_scan"]');
                    const boxInput = filterForm.querySelector('input[name="box_scan"]');
                    const limitSelect = filterForm.querySelector('select[name="limit"]');
                    const dateFrom = filterForm.querySelector('#fieldDateFrom');
                    const dateTo = filterForm.querySelector('#fieldDateTo');
                    if (serialInput) serialInput.value = '';
                    if (boxInput) boxInput.value = '';
                    if (limitSelect) limitSelect.selectedIndex = 0;
                    if (dateFrom) dateFrom.value = '';
                    if (dateTo) dateTo.value = '';
                    filterForm.submit();
                });
            }

            // 日付クイックボタン
            const fieldDateFrom = document.getElementById('fieldDateFrom');
            const fieldDateTo = document.getElementById('fieldDateTo');
            const dateSummary = document.getElementById('dateSummary');
            const quickDateBtns = document.querySelectorAll('.quickDateBtn');

            const formatDate = (date) => {
                const y = date.getFullYear();
                const m = String(date.getMonth() + 1).padStart(2, '0');
                const d = String(date.getDate()).padStart(2, '0');
                return `${y}-${m}-${d}`;
            };

            const updateSummary = () => {
                if (!dateSummary || !fieldDateFrom || !fieldDateTo) return;
                const from = fieldDateFrom.value || '指定なし';
                const to = fieldDateTo.value || '指定なし';
                dateSummary.textContent = `期間: ${from} 〜 ${to}`;
            };

            const setDateRange = (from, to) => {
                if (fieldDateFrom) fieldDateFrom.value = from;
                if (fieldDateTo) fieldDateTo.value = to;
                updateSummary();
            };

            quickDateBtns.forEach(btn => {
                btn.addEventListener('click', () => {
                    const range = btn.dataset.range;
                    const today = new Date();
                    let from = '', to = '';

                    switch (range) {
                        case 'today':
                            from = to = formatDate(today);
                            break;
                        case 'week':
                            const weekStart = new Date(today);
                            const dayOfWeek = weekStart.getDay();
                            const diff = dayOfWeek === 0 ? 6 : dayOfWeek - 1; // 月曜始まり
                            weekStart.setDate(weekStart.getDate() - diff);
                            from = formatDate(weekStart);
                            to = formatDate(today);
                            break;
                        case 'all':
                            from = '';
                            to = '';
                            break;
                    }

                    setDateRange(from, to);

                    // アクティブ状態の更新
                    quickDateBtns.forEach(b => b.classList.remove('active'));
                    if (range !== 'all' || (from === '' && to === '')) {
                        btn.classList.add('active');
                    }

                    // 自動でフォーム送信
                    filterForm?.submit();
                });
            });

            // 日付入力変更時にサマリー更新
            fieldDateFrom?.addEventListener('change', updateSummary);
            fieldDateTo?.addEventListener('change', updateSummary);

            updateSummary();
        })();
    </script>
</body>

</html>

<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/db.php';

$pdo = getPDO();
$tableMaster = defined('TABLE_MASTER') ? constant('TABLE_MASTER') : 'fail_master';
$tableMasterQuoted = quoteIdentifier($tableMaster);

function escapeHtml($v): string
{
    return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
}

function quoteIdentifier(string $name): string
{
    return '`' . str_replace('`', '``', $name) . '`';
}

function fetchModels(PDO $pdo, string $table): array
{
    $tableQuoted = quoteIdentifier($table);
    $sql = "SELECT DISTINCT model FROM {$tableQuoted} WHERE model IS NOT NULL AND model <> '' ORDER BY model";
    $stmt = $pdo->query($sql);
    return $stmt->fetchAll(PDO::FETCH_COLUMN) ?: [];
}

function fetchTableColumns(PDO $pdo, string $table): array
{
    $stmt = $pdo->query('DESCRIBE ' . quoteIdentifier($table));
    return $stmt->fetchAll(PDO::FETCH_COLUMN) ?: [];
}

$messages = [];
$errors = [];

$models = fetchModels($pdo, $tableMaster);
$selectedModel = trim((string)($_POST['model'] ?? $_GET['model'] ?? ''));

$columns = fetchTableColumns($pdo, $tableMaster);
$primaryKey = null;
foreach ($columns as $col) {
    if (strcasecmp($col, 'id') === 0) {
        $primaryKey = $col;
        break;
    }
}
if ($primaryKey === null && !empty($columns)) {
    $primaryKey = $columns[0]; // 最初のカラムを仮のキーとして利用
}
$orderColumns = [];
$orderSql = '';
if (!empty($columns)) {
    $orderColumns = array_values(array_filter(['cate', 'parts', 'symptom', $primaryKey], function ($c) use ($columns) {
        return in_array($c, $columns, true);
    }));
    $orderSql = implode(', ', array_map('quoteIdentifier', $orderColumns));
} else {
    $errors[] = 'fail_master の列情報を取得できませんでした。';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'delete_selected') {
    if ($selectedModel === '') {
        $errors[] = 'model を選択してください。';
    } elseif ($primaryKey === null) {
        $errors[] = 'fail_master の主キーが特定できません。削除できませんでした。';
    } else {
        $selectedIds = $_POST['selected'] ?? [];
        if (!is_array($selectedIds) || count($selectedIds) === 0) {
            $errors[] = '削除対象の行を選択してください。';
        } else {
            $placeholders = [];
            $params = [':model' => $selectedModel];
            foreach ($selectedIds as $idx => $raw) {
                $ph = ':id' . $idx;
                $placeholders[] = $ph;
                $params[$ph] = ctype_digit((string)$raw) ? (int)$raw : (string)$raw;
            }
            $sql = "DELETE FROM {$tableMasterQuoted} WHERE model = :model AND " . quoteIdentifier($primaryKey) . " IN (" . implode(',', $placeholders) . ")";
            try {
                $stmt = $pdo->prepare($sql);
                $stmt->execute($params);
                $count = $stmt->rowCount();
                $messages[] = "{$count} 行削除しました。";
            } catch (Throwable $e) {
                $errors[] = '削除に失敗しました: ' . $e->getMessage();
            }
        }
    }
}

$rows = [];
if ($selectedModel !== '' && $primaryKey !== null && $orderSql !== '') {
    $stmtRows = $pdo->prepare("SELECT * FROM {$tableMasterQuoted} WHERE model = :model ORDER BY {$orderSql}");
    $stmtRows->execute([':model' => $selectedModel]);
    $rows = $stmtRows->fetchAll(PDO::FETCH_ASSOC) ?: [];
}
?>
<!doctype html>
<html lang="ja">
<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <link rel="icon" href="judge-icon2.png" sizes="32x32" type="image/png">
    <title>fail_master 削除</title>
    <style>
        body {
            margin: 0;
            padding: 20px;
            font-family: system-ui, -apple-system, "Segoe UI", "Noto Sans JP", sans-serif;
            background: #f8fafc;
            color: #0f172a;
        }
        h1 {
            margin: 0 0 12px;
            font-size: 28px;
        }
        .toolbar {
            display: flex;
            gap: 8px;
            align-items: center;
            margin-bottom: 12px;
        }
        .buttonLink {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            padding: 10px 16px;
            border-radius: 10px;
            background: #111827;
            color: #fff;
            text-decoration: none;
            font-weight: 700;
            border: none;
            cursor: pointer;
        }
        .panel {
            background: #fff;
            border: 1px solid #e2e8f0;
            border-radius: 12px;
            padding: 16px;
            margin-bottom: 16px;
        }
        .formRow {
            display: flex;
            flex-wrap: wrap;
            gap: 12px;
            align-items: flex-end;
        }
        .formRow label {
            display: flex;
            flex-direction: column;
            font-size: 0.95em;
            color: #475569;
            min-width: 220px;
        }
        .formRow select {
            padding: 10px 12px;
            border-radius: 8px;
            border: 1px solid #cbd5f5;
            font-size: 1em;
        }
        .status {
            margin: 12px 0;
            padding: 12px 14px;
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
        .tableWrap {
            overflow-x: auto;
            border: 1px solid #e2e8f0;
            border-radius: 12px;
            background: #fff;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            min-width: 720px;
        }
        th, td {
            border: 1px solid #e2e8f0;
            padding: 6px;
            text-align: left;
            font-size: 0.95em;
        }
        th {
            background: #f8fafc;
            color: #475569;
        }
        .actions {
            display: flex;
            gap: 10px;
            margin-top: 10px;
            flex-wrap: wrap;
        }
        .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            padding: 10px 16px;
            border-radius: 10px;
            border: none;
            font-weight: 800;
            cursor: pointer;
            color: #fff;
            text-decoration: none;
        }
        .btn.red {
            background: #dc2626;
        }
        .btn.red:hover {
            background: #b91c1c;
        }
        .btn.gray {
            background: #111827;
        }
    </style>
</head>
<body>
    <h1>fail_master 削除</h1>
    <div class="toolbar">
        <a class="buttonLink" href="edit.php">編集ページへ戻る</a>
        <a class="buttonLink" href="master_insert.php" target="_blank" rel="noopener">登録ページ</a>
    </div>

    <?php foreach ($messages as $msg): ?>
        <div class="status ok"><?= escapeHtml($msg) ?></div>
    <?php endforeach; ?>
    <?php foreach ($errors as $err): ?>
        <div class="status err"><?= escapeHtml($err) ?></div>
    <?php endforeach; ?>

    <div class="panel">
        <form method="get" class="formRow">
            <label>
                model を選択
                <select name="model" onchange="this.form.submit()">
                    <option value="">-- 選択してください --</option>
                    <?php foreach ($models as $m): ?>
                        <option value="<?= escapeHtml($m) ?>" <?= ($m === $selectedModel) ? 'selected' : '' ?>><?= escapeHtml($m) ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
            <div class="actions">
                <button class="btn gray" type="submit">表示</button>
            </div>
        </form>
    </div>

    <?php if ($selectedModel === ''): ?>
        <div class="status err">model を選択してください。</div>
    <?php elseif (empty($rows)): ?>
        <div class="status err">該当データがありません。</div>
    <?php else: ?>
        <form method="post" onsubmit="return confirm('選択した行を削除します。よろしいですか？');">
            <input type="hidden" name="action" value="delete_selected">
            <input type="hidden" name="model" value="<?= escapeHtml($selectedModel) ?>">
               <div class="actions">
                <button class="btn red" type="submit">選択行を削除</button>
               </div>
            <div class="tableWrap">
                <table>
                    <thead>
                        <tr>
                            <th><input type="checkbox" id="chkAll" onclick="toggleAll(this)"></th>
                            <?php foreach ($columns as $col): ?>
                                <th><?= escapeHtml($col) ?></th>
                            <?php endforeach; ?>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($rows as $row): ?>
                            <tr>
                                <td>
                                    <?php if ($primaryKey !== null && isset($row[$primaryKey])): ?>
                                        <input type="checkbox" name="selected[]" value="<?= escapeHtml($row[$primaryKey]) ?>">
                                    <?php else: ?>
                                        -
                                    <?php endif; ?>
                                </td>
                                <?php foreach ($columns as $col): ?>
                                    <td><?= escapeHtml($row[$col] ?? '') ?></td>
                                <?php endforeach; ?>
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
            document.querySelectorAll('input[name="selected[]"]').forEach(cb => cb.checked = checked);
        }
    </script>
</body>
</html>

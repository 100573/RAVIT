<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/db.php';

$pdo = getPDO();
$table = 'fail_log';
$backupTable = 'fail_log_backup';
$tableMaster = defined('TABLE_MASTER') ? constant('TABLE_MASTER') : 'fail_master';

function quoteIdentifier(string $name): string
{
    return '`' . str_replace('`', '``', $name) . '`';
}

function fetchTableColumns(PDO $pdo, string $table): array
{
    $stmt = $pdo->query('DESCRIBE ' . quoteIdentifier($table));
    return $stmt->fetchAll(PDO::FETCH_COLUMN) ?: [];
}

function escapeHtml($value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function trim_str($v): string
{
    return trim((string)$v);
}

function outputCsv(array $columns, array $rows, string $filename = 'export.csv'): void
{
    // ANSI想定で Shift_JIS (Windows-31J) へ変換して出力
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

function findColumn(array $columns, string $target): ?string
{
    foreach ($columns as $col) {
        if (strcasecmp($col, $target) === 0) {
            return $col;
        }
    }
    return null;
}

$columns = fetchTableColumns($pdo, $table);
if (empty($columns)) {
    die('fail_log の構成を取得できませんでした。');
}

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

$serialColumn = findColumn($columns, 'serial');
if ($serialColumn === null) {
    die('fail_log に serial カラムが見つかりません。');
}
$regtimeColumn = findColumn($columns, 'regtime');

$backupColumns = fetchTableColumns($pdo, $backupTable);
if (empty($backupColumns)) {
    die('fail_log_backup の構成を取得できませんでした。');
}
$backupColumnLookup = [];
foreach ($backupColumns as $bkCol) {
    $backupColumnLookup[strtolower($bkCol)] = $bkCol;
}
$missingColumns = [];
foreach ($columns as $col) {
    if (!isset($backupColumnLookup[strtolower($col)])) {
        $missingColumns[] = $col;
    }
}
if ($missingColumns) {
    die('fail_log_backup に存在しないカラムがあります: ' . implode(', ', $missingColumns));
}
$deleteTimeColumn = findColumn($backupColumns, 'delete_time');
$deleteTimeColumnQuoted = $deleteTimeColumn !== null ? quoteIdentifier($deleteTimeColumn) : null;

$quotedTable = quoteIdentifier($table);
$quotedBackupTable = quoteIdentifier($backupTable);
$primaryKeyQuoted = quoteIdentifier($primaryKey);
$serialColumnQuoted = quoteIdentifier($serialColumn);
$regtimeColumnQuoted = $regtimeColumn ? quoteIdentifier($regtimeColumn) : null;

$messages = [];
$errors = [];
$exportCsv = (($_GET['export'] ?? '') === 'csv');
$dateFrom = trim((string)($_GET['date_from'] ?? ''));
$dateTo   = trim((string)($_GET['date_to'] ?? ''));
function sanitizeFields(array $fields): array
{
    $result = [];
    foreach ($fields as $key => $value) {
        if (is_array($value)) continue;
        $result[$key] = trim((string)$value);
    }
    return $result;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? 'update';
    $idRaw = trim((string)($_POST['id'] ?? ''));
    $idValue = ctype_digit($idRaw) ? (int)$idRaw : 0;
    if ($idValue <= 0) {
        $errors[] = '有効なIDを指定してください。';
    } else {
        if ($action === 'delete') {
            try {
                $pdo->beginTransaction();
                $backupInsertColumns = [];
                $backupSelectColumns = [];
                foreach ($columns as $col) {
                    $quotedCol = quoteIdentifier($col);
                    $backupInsertColumns[] = $quotedCol;
                    $backupSelectColumns[] = $quotedCol;
                }
                if ($deleteTimeColumnQuoted !== null) {
                    $backupInsertColumns[] = $deleteTimeColumnQuoted;
                    $backupSelectColumns[] = ':delete_time_value';
                }
                $sqlBackup = "INSERT INTO {$quotedBackupTable} (" . implode(', ', $backupInsertColumns) . ') ' .
                    "SELECT " . implode(', ', $backupSelectColumns) . " FROM {$quotedTable} WHERE {$primaryKeyQuoted} = :id";
                $stmtBackup = $pdo->prepare($sqlBackup);
                $paramsBackup = [':id' => $idValue];
                if ($deleteTimeColumnQuoted !== null) {
                    $paramsBackup[':delete_time_value'] = date('Y-m-d H:i:s');
                }
                $stmtBackup->execute($paramsBackup);
                if ($stmtBackup->rowCount() === 0) {
                    throw new RuntimeException('対象の行が見つかりません。');
                }
                $stmtDelete = $pdo->prepare("DELETE FROM {$quotedTable} WHERE {$primaryKeyQuoted} = :id");
                $stmtDelete->execute([':id' => $idValue]);
                $pdo->commit();
                $messages[] = "ID #{$idValue} を削除し、バックアップしました。";
            } catch (Throwable $e) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                $errors[] = '削除に失敗しました: ' . $e->getMessage();
            }
        } else {
            $messages[] = '現在、編集機能は停止中です。';
        }
    }
}

$searchSerial = trim((string)($_GET['serial_scan'] ?? ''));
$limitStr = trim((string)($_GET['limit'] ?? 'all'));
$limit = null;
if ($limitStr !== '' && strtolower($limitStr) !== 'all') {
    $limit = (ctype_digit($limitStr) && (int)$limitStr > 0) ? (int)$limitStr : 50;
    if ($limit > 200) $limit = 200;
}

$where = [];
$params = [];
$hasDateFilter = $regtimeColumnQuoted && ($dateFrom !== '' || $dateTo !== '');
$hasSerialFilter = ($searchSerial !== '');
$hasFilter = $hasDateFilter || $hasSerialFilter;

// 日付フィルターを優先して適用（シリアルは任意で追加）
if ($hasDateFilter && $regtimeColumnQuoted) {
    if ($dateFrom !== '') {
        $where[] = "{$regtimeColumnQuoted} >= :date_from";
        $params[':date_from'] = $dateFrom . ' 00:00:00';
    }
    if ($dateTo !== '') {
        $where[] = "{$regtimeColumnQuoted} <= :date_to";
        $params[':date_to'] = $dateTo . ' 23:59:59';
    }
}
if ($hasSerialFilter) {
    $where[] = "{$serialColumnQuoted} = :serial_value";
    $params[':serial_value'] = $searchSerial;
}

$waitingSearch = !$hasFilter;
if ($hasFilter) {
    $sqlList = 'SELECT * FROM ' . $quotedTable;
    if ($where) {
        $sqlList .= ' WHERE ' . implode(' AND ', $where);
    }
    $sqlList .= " ORDER BY {$primaryKeyQuoted} DESC";
    if ($limit !== null) {
        $sqlList .= " LIMIT :limit";
    }
    $stmtList = $pdo->prepare($sqlList);
    foreach ($params as $key => $value) {
        $stmtList->bindValue($key, $value);
    }
    if ($limit !== null) {
        $stmtList->bindValue(':limit', $limit, PDO::PARAM_INT);
    }
    $stmtList->execute();
    $logs = $stmtList->fetchAll(PDO::FETCH_ASSOC);
} else {
    $logs = [];
}

if ($exportCsv && !$waitingSearch) {
    $filename = 'fail_log_' . date('Ymd_His') . '.csv';
    outputCsv($columns, $logs, $filename);
}

?>
<!doctype html>
<html lang="ja">

<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <link rel="icon" href="judge-icon2.png" sizes="32x32" type="image/png">
    <title>不良ログ 編集ページ</title>
  
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
        }

        .buttonLink {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            padding: 10px 18px;
            border-radius: 999px;
            background: #000000ff;
            color: #fff;
            text-decoration: none;
            font-weight: 700;
            border: none;
            cursor: pointer;
        }
        .buttonLink.small {
            padding: 8px 14px;
            font-size: 0.95em;
        }
        .buttonLink.blue {
            background: #2563eb;
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

        .filterForm button {
            padding: 10px 16px;
            border-radius: 8px;
            border: none;
            background: #2563eb;
            color: #fff;
            font-weight: 700;
            cursor: pointer;
        }
        .filterForm .rightLinks {
            margin-left: auto;
            display: inline-flex;
            gap: 8px;
            align-items: center;
        }
        .dateSummary {
            font-size: 0.9em;
            color: #475569;
        }
        .modalOverlay {
            position: fixed;
            inset: 0;
            background: rgba(15, 23, 42, 0.35);
            display: none;
            align-items: center;
            justify-content: center;
            z-index: 50;
        }
        .modalOverlay.show {
            display: flex;
        }
        .modal {
            background: #fff;
            padding: 20px;
            border-radius: 12px;
            min-width: 320px;
            box-shadow: 0 12px 36px rgba(0, 0, 0, 0.2);
        }
        .modal h2 {
            margin: 0 0 12px;
            font-size: 20px;
        }
        .modal form {
            display: flex;
            flex-direction: column;
            gap: 12px;
        }
        .modal .actions {
            display: flex;
            gap: 8px;
            justify-content: flex-end;
            margin-top: 8px;
        }
        .modal input[type="date"] {
            padding: 8px 10px;
            border: 1px solid #cbd5f5;
            border-radius: 8px;
            font-size: 1em;
        }
        .modal button {
            padding: 8px 14px;
            border-radius: 8px;
            border: none;
            font-weight: 700;
            cursor: pointer;
        }
        .modal .primary {
            background: #2563eb;
            color: #fff;
        }
        .modal .ghost {
            background: #e2e8f0;
            color: #1e293b;
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
            min-width: 720px;
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
        }

        table.logTable input[type="text"],
        table.logTable textarea {
            width: 100%;
            border-radius: 6px;
            border: 1px solid #cbd5f5;
            padding: 6px 8px;
            font-size: 0.95em;
        }

        .cellText {
            display: block;
            padding: 4px 2px;
            font-size: 0.95em;
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
        }

        .rowButton.save {
            background: #16a34a;
        }

        .rowButton.delete {
            background: #dc2626;
        }

        .noRows {
            text-align: center;
            padding: 40px;
            background: #fff;
            border-radius: 12px;
            border: 1px dashed #cbd5f5;
            color: #94a3b8;
        }
    </style>
</head>

<body>
    <a class="cornerThumb" href="home.php" title="HOMEへ" aria-label="HOMEへ">
        <img src="judge-icon2.png" alt="ホームロゴ">
    </a>
    <div class="toolbar">
        <h1>不良ログ編集</h1>
        
        
    </div>

    <?php foreach ($messages as $msg): ?>
        <div class="status ok"><?= escapeHtml($msg) ?></div>
    <?php endforeach; ?>
    <?php foreach ($errors as $err): ?>
        <div class="status err"><?= escapeHtml($err) ?></div>
    <?php endforeach; ?>

    <form method="get" class="filterForm">
        <label>
            シリアル
            <input type="text" name="serial_scan" value="<?= escapeHtml($searchSerial) ?>" autofocus />
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
        <input type="hidden" name="date_from" id="fieldDateFrom" value="<?= escapeHtml($dateFrom) ?>" />
        <input type="hidden" name="date_to" id="fieldDateTo" value="<?= escapeHtml($dateTo) ?>" />
        <span class="dateSummary" id="dateSummary">
            <?= ($dateFrom || $dateTo) ? ('期間: ' . escapeHtml($dateFrom ?: '指定なし') . ' 〜 ' . escapeHtml($dateTo ?: '指定なし')) : '期間: 指定なし' ?>
        </span>
        <button type="button" id="btnDateFilter">日付選択</button>
        <button type="submit">絞り込み</button>
        <button type="button" id="btnClearFilters">クリア</button>
        <button type="submit" name="export" value="csv">CSV出力</button>
        <span class="rightLinks">
            <a class="buttonLink small blue" href="depete.php" target="_blank" rel="noopener">fail_master削除</a>
            <a class="buttonLink" href="master_insert.php" target="_blank" rel="noopener">fail_master登録</a>
        </span>
    </form>

    <?php if ($waitingSearch): ?>
        <div class="noRows">シリアルをスキャンしてください。</div>
    <?php elseif (empty($logs)): ?>
        <div class="noRows">一致するログがありませんでした。</div>
    <?php else: ?>
        <p class="hint">保存すると fail_log の該当IDを上書きします（実行前に必ず内容を確認してください）。</p>
        <div class="logTableWrap">
            <table class="logTable">
                <thead>
                    <tr>
                        <?php foreach ($columns as $col): ?>
                            <th><?= escapeHtml($col) ?></th>
                        <?php endforeach; ?>
                        <th>操作</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($logs as $row): ?>
                        <?php $rowId = $row[$primaryKey] ?? '';
                        $formId = 'logForm_' . $rowId; ?>
                        <tr>
                            <?php foreach ($columns as $col): ?>
                                <?php $value = $row[$col] ?? ''; ?>
                                <td>
                                    <?php if (strcasecmp($col, $primaryKey) === 0): ?>
                                        <span class="cellText"><?= escapeHtml($value) ?></span>
                                    <?php else: ?>
                                        <!-- <input type="text" name="fields[<?= escapeHtml($col) ?>]" value="<?= escapeHtml($value) ?>" form="<?= $formId ?>" /> -->
                                        <span class="cellText"><?= escapeHtml($value) ?></span>
                                    <?php endif; ?>
                                </td>
                            <?php endforeach; ?>
                            <td class="actionsCell">
                                <form id="<?= $formId ?>" method="post">
                                    <input type="hidden" name="id" value="<?= escapeHtml($rowId) ?>" />
                                </form>
                                <!--
                                <button class="rowButton save" type="submit" form="<?= $formId ?>" name="action" value="update"
                                    onclick="return confirm('ID #<?= escapeHtml($rowId) ?> を上書き保存します。よろしいですか？');">保存</button>
                                -->
                                <button class="rowButton delete" type="submit" form="<?= $formId ?>" name="action" value="delete"
                                    onclick="return confirm('ID #<?= escapeHtml($rowId) ?> を削除しますか？ バックアップに保存されます。');">削除</button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
    <div class="modalOverlay" id="dateModal">
        <div class="modal" role="dialog" aria-modal="true" aria-labelledby="dateModalTitle">
            <h2 id="dateModalTitle">日付で絞り込む</h2>
            <form id="dateForm">
                <label>
                    開始日
                    <input type="date" id="inputDateFrom" value="<?= escapeHtml($dateFrom) ?>">
                </label>
                <label>
                    終了日
                    <input type="date" id="inputDateTo" value="<?= escapeHtml($dateTo) ?>">
                </label>
                <div class="actions">
                    <button type="button" class="ghost" id="dateCancel">キャンセル</button>
                    <button type="submit" class="primary">適用</button>
                </div>
            </form>
        </div>
    </div>
    <script>
        // 入力値を半角に統一（機能は変更しない）
        (function() {
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

            const clearBtn = document.getElementById('btnClearFilters');
            const filterForm = document.querySelector('.filterForm');
            if (clearBtn && filterForm) {
                clearBtn.addEventListener('click', () => {
                    const serialInput = filterForm.querySelector('input[name="serial_scan"]');
                    const limitSelect = filterForm.querySelector('select[name="limit"]');
                    const dateFrom = filterForm.querySelector('#fieldDateFrom');
                    const dateTo = filterForm.querySelector('#fieldDateTo');
                    if (serialInput) serialInput.value = '';
                    if (limitSelect) limitSelect.selectedIndex = 0;
                    if (dateFrom) dateFrom.value = '';
                    if (dateTo) dateTo.value = '';
                    filterForm.submit();
                });
            }

            const dateModal = document.getElementById('dateModal');
            const dateForm = document.getElementById('dateForm');
            const btnDateFilter = document.getElementById('btnDateFilter');
            const btnCancel = document.getElementById('dateCancel');
            const inputDateFrom = document.getElementById('inputDateFrom');
            const inputDateTo = document.getElementById('inputDateTo');
            const fieldDateFrom = document.getElementById('fieldDateFrom');
            const fieldDateTo = document.getElementById('fieldDateTo');
            const dateSummary = document.getElementById('dateSummary');

            const closeModal = () => dateModal?.classList.remove('show');
            const openModal = () => dateModal?.classList.add('show');

            const updateSummary = () => {
                if (!dateSummary || !fieldDateFrom || !fieldDateTo) return;
                const from = fieldDateFrom.value || '指定なし';
                const to = fieldDateTo.value || '指定なし';
                dateSummary.textContent = `期間: ${from} 〜 ${to}`;
            };

            btnDateFilter?.addEventListener('click', () => {
                if (inputDateFrom && fieldDateFrom) inputDateFrom.value = fieldDateFrom.value;
                if (inputDateTo && fieldDateTo) inputDateTo.value = fieldDateTo.value;
                openModal();
            });

            btnCancel?.addEventListener('click', () => {
                closeModal();
            });

            dateForm?.addEventListener('submit', (e) => {
                e.preventDefault();
                if (fieldDateFrom && inputDateFrom) fieldDateFrom.value = inputDateFrom.value;
                if (fieldDateTo && inputDateTo) fieldDateTo.value = inputDateTo.value;
                updateSummary();
                filterForm?.submit();
            });

            updateSummary();
        })();
    </script>
</body>

</html>

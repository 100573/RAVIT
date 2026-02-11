<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/db.php';

header('Content-Type: application/json; charset=utf-8');

function json_response(array $payload, int $status = 200): void
{
    http_response_code($status);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function getOracleConfig(): array
{
    $user = defined('ORACLE_USER') ? (string)ORACLE_USER : '';
    $pass = defined('ORACLE_PASS') ? (string)ORACLE_PASS : '';
    $dsn  = defined('ORACLE_DSN') ? (string)ORACLE_DSN : '';

    $envUser = getenv('ORACLE_USER');
    $envPass = getenv('ORACLE_PASS');
    $envDsn  = getenv('ORACLE_DSN');

    if ($envUser !== false && $envUser !== '') $user = $envUser;
    if ($envPass !== false && $envPass !== '') $pass = $envPass;
    if ($envDsn !== false && $envDsn !== '') $dsn = $envDsn;

    return [$user, $pass, $dsn];
}

function getOracleConnection(?string &$error = null)
{
    if (!extension_loaded('oci8')) {
        $error = 'Oracle拡張(oci8)が未有効です';
        return null;
    }
    [$user, $pass, $dsn] = getOracleConfig();
    if ($user === '' || $pass === '' || $dsn === '') {
        $error = 'Oracle接続情報が未設定です';
        return null;
    }
    $conn = @oci_connect($user, $pass, $dsn, 'AL32UTF8');
    if (!$conn) {
        $e = oci_error();
        $error = $e['message'] ?? 'Oracle接続に失敗しました';
        return null;
    }
    return $conn;
}

function normalizeSerialList(array $serials): array
{
    $filtered = array_filter(array_map('strval', $serials), static function ($v) {
        return $v !== '';
    });
    $unique = array_values(array_unique($filtered));
    return $unique;
}

function normalizeDate(?string $value): string
{
    $v = trim((string)$value);
    if ($v === '') return '';
    return preg_match('/^\d{4}-\d{2}-\d{2}$/', $v) ? $v : '';
}

function fetchOracleUsedCount($conn, array $serials): int
{
    $serials = normalizeSerialList($serials);
    if (empty($serials)) return 0;

    $total = 0;
    $chunks = array_chunk($serials, 900);
    foreach ($chunks as $chunk) {
        $placeholders = [];
        $binds = [];
        foreach ($chunk as $i => $val) {
            $key = ':s' . $i;
            $placeholders[] = $key;
            $binds[$key] = $val;
        }
        $sql = 'SELECT COUNT(DISTINCT ID) AS CNT FROM I_PARTS_CB WHERE ID IN (' . implode(',', $placeholders) . ') AND UPPER(CATE_NAME) LIKE \'%USY%\'';
        $stmt = oci_parse($conn, $sql);
        foreach ($binds as $key => &$val) {
            oci_bind_by_name($stmt, $key, $val);
        }
        unset($val);
        if (!oci_execute($stmt)) {
            $err = oci_error($stmt);
            throw new RuntimeException($err['message'] ?? 'Oracleクエリに失敗しました');
        }
        $row = oci_fetch_assoc($stmt) ?: [];
        $total += (int)($row['CNT'] ?? 0);
        oci_free_statement($stmt);
    }
    return $total;
}

function fetchOracleUsedSet($conn, array $serials): array
{
    $serials = normalizeSerialList($serials);
    if (empty($serials)) return [];

    $used = [];
    $chunks = array_chunk($serials, 900);
    foreach ($chunks as $chunk) {
        $placeholders = [];
        $binds = [];
        foreach ($chunk as $i => $val) {
            $key = ':s' . $i;
            $placeholders[] = $key;
            $binds[$key] = $val;
        }
        $sql = 'SELECT ID FROM I_PARTS_CB WHERE ID IN (' . implode(',', $placeholders) . ') AND UPPER(CATE_NAME) LIKE \'%USY%\'';
        $stmt = oci_parse($conn, $sql);
        foreach ($binds as $key => &$val) {
            oci_bind_by_name($stmt, $key, $val);
        }
        unset($val);
        if (!oci_execute($stmt)) {
            $err = oci_error($stmt);
            throw new RuntimeException($err['message'] ?? 'Oracleクエリに失敗しました');
        }
        while (($row = oci_fetch_assoc($stmt)) !== false) {
            $id = (string)($row['ID'] ?? '');
            if ($id !== '') $used[$id] = true;
        }
        oci_free_statement($stmt);
    }
    return $used;
}

try {
    $pdo = getPDO();

    $limitRaw = strtolower(trim((string)($_GET['limit'] ?? '100000')));
    $limit = null;
    if ($limitRaw === 'all') {
        $limit = null;
    } elseif (ctype_digit($limitRaw)) {
        $limit = max(1, min((int)$limitRaw, 1000000));
    } else {
        $limit = 200;
    }

    $dateFrom = normalizeDate($_GET['date_from'] ?? '');
    $dateTo = normalizeDate($_GET['date_to'] ?? '');
    $listLimitRaw = strtolower(trim((string)($_GET['list_limit'] ?? '100000')));
    $listLimit = null;
    if ($listLimitRaw === 'all') {
        $listLimit = null;
    } elseif (ctype_digit($listLimitRaw)) {
        $listLimit = max(1, min((int)$listLimitRaw, 100000));
    } else {
        $listLimit = 200;
    }

    $resultFilter = strtoupper(trim((string)($_GET['result'] ?? 'ALL')));
    $serialFilter = trim((string)($_GET['serial'] ?? ''));
    $stockFilter = strtoupper(trim((string)($_GET['stock'] ?? 'ALL')));
    $modelFilter = trim((string)($_GET['model'] ?? ''));

    // モデル（partsno）一覧を取得
    $modelListSql = "SELECT DISTINCT COALESCE(NULLIF(partsno, ''), '未設定') AS model FROM boxid ORDER BY model";
    $modelList = $pdo->query($modelListSql)->fetchAll(PDO::FETCH_COLUMN) ?: [];

    $summarySql = "SELECT
        COUNT(*) AS total_count,
        SUM(CASE WHEN result = 'OK' THEN 1 ELSE 0 END) AS ok_count,
        SUM(CASE WHEN result = 'NG' THEN 1 ELSE 0 END) AS ng_count
        FROM boxid";
    $summaryRow = $pdo->query($summarySql)->fetch(PDO::FETCH_ASSOC) ?: [];
    $totalCount = (int)($summaryRow['total_count'] ?? 0);
    $okCount = (int)($summaryRow['ok_count'] ?? 0);
    $ngCount = (int)($summaryRow['ng_count'] ?? 0);
    $otherCount = max(0, $totalCount - $okCount - $ngCount);

    // モデル（partsno）別集計
    $modelSummarySql = "SELECT
        COALESCE(NULLIF(partsno, ''), '未設定') AS model,
        COUNT(*) AS total_count,
        SUM(CASE WHEN result = 'OK' THEN 1 ELSE 0 END) AS ok_count,
        SUM(CASE WHEN result = 'NG' THEN 1 ELSE 0 END) AS ng_count
        FROM boxid
        GROUP BY COALESCE(NULLIF(partsno, ''), '未設定')
        ORDER BY total_count DESC";
    $modelSummaryRows = $pdo->query($modelSummarySql)->fetchAll(PDO::FETCH_ASSOC) ?: [];

    $rows = [];
    $filters = [];
    $params = [];
    if ($resultFilter === 'OK' || $resultFilter === 'NG') {
        $filters[] = 'result = :result';
        $params[':result'] = $resultFilter;
    } elseif ($resultFilter === 'OTHER') {
        $filters[] = "(result IS NULL OR result NOT IN ('OK','NG'))";
    }
    if ($serialFilter !== '') {
        $filters[] = 'serial LIKE :serial';
        $params[':serial'] = '%' . $serialFilter . '%';
    }
    if ($modelFilter !== '' && $modelFilter !== 'ALL') {
        if ($modelFilter === '未設定') {
            $filters[] = "(partsno IS NULL OR partsno = '')";
        } else {
            $filters[] = 'partsno = :model';
            $params[':model'] = $modelFilter;
        }
    }
    if ($dateFrom !== '') {
        $filters[] = 'regtime >= :date_from';
        $params[':date_from'] = $dateFrom . ' 00:00:00';
    }
    if ($dateTo !== '') {
        $filters[] = 'regtime <= :date_to';
        $params[':date_to'] = $dateTo . ' 23:59:59';
    }

    $sql = 'SELECT serial, box, partsno, result, regtime FROM boxid';
    if (!empty($filters)) {
        $sql .= ' WHERE ' . implode(' AND ', $filters);
    }
    $sql .= ' ORDER BY regtime DESC';
    if ($limit !== null) {
        $sql .= ' LIMIT ' . (int)$limit;
    }
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

    $oracle = [
        'available' => false,
        'message' => '',
        'used_count' => null,
    ];

    $oracleError = null;
    $oracleConn = getOracleConnection($oracleError);
    $usedCount = null;
    $usedSetAll = [];
    $lists = [
        'used' => ['total' => 0, 'displayed' => 0, 'rows' => []],
        'stock' => ['total' => 0, 'displayed' => 0, 'rows' => []],
        'message' => '',
    ];
    if ($oracleConn) {
        try {
            $oracle['available'] = true;
            $okSerials = $pdo->query("SELECT serial FROM boxid WHERE result = 'OK'")->fetchAll(PDO::FETCH_COLUMN) ?: [];
            $okSerials = normalizeSerialList($okSerials);
            $usedSetAll = fetchOracleUsedSet($oracleConn, $okSerials);
            $usedCount = count($usedSetAll);
            $oracle['used_count'] = $usedCount;

            $okFilters = ["result = 'OK'"];
            $okParams = [];
            if ($dateFrom !== '') {
                $okFilters[] = 'regtime >= :ok_from';
                $okParams[':ok_from'] = $dateFrom . ' 00:00:00';
            }
            if ($dateTo !== '') {
                $okFilters[] = 'regtime <= :ok_to';
                $okParams[':ok_to'] = $dateTo . ' 23:59:59';
            }
            $okSql = 'SELECT serial, box, regtime FROM boxid WHERE ' . implode(' AND ', $okFilters) . ' ORDER BY regtime DESC';
            $okStmt = $pdo->prepare($okSql);
            $okStmt->execute($okParams);
            $okRows = $okStmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

            $usedAll = [];
            $stockAll = [];
            if (!empty($okRows)) {
                foreach ($okRows as $row) {
                    $serial = trim((string)($row['serial'] ?? ''));
                    if ($serial === '') continue;
                    if (isset($usedSetAll[$serial])) {
                        $usedAll[] = $row;
                    } else {
                        $stockAll[] = $row;
                    }
                }
            }
            $usedRows = $listLimit === null ? $usedAll : array_slice($usedAll, 0, $listLimit);
            $stockRows = $listLimit === null ? $stockAll : array_slice($stockAll, 0, $listLimit);
            $lists = [
                'used' => ['total' => count($usedAll), 'displayed' => count($usedRows), 'rows' => $usedRows],
                'stock' => ['total' => count($stockAll), 'displayed' => count($stockRows), 'rows' => $stockRows],
                'message' => '',
            ];
        } catch (Throwable $e) {
            $oracle['available'] = false;
            $oracle['message'] = $e->getMessage();
            $lists['message'] = $oracle['message'];
        } finally {
            oci_close($oracleConn);
        }
    } else {
        $oracle['message'] = $oracleError ?? 'Oracle接続情報が未設定です';
        $lists['message'] = $oracle['message'];
    }

    $oracleAvailable = $oracle['available'] === true;
    foreach ($rows as &$row) {
        if (!$oracleAvailable) {
            $row['used'] = null;
            continue;
        }
        $serialKey = (string)($row['serial'] ?? '');
        $row['used'] = ($serialKey !== '' && isset($usedSetAll[$serialKey])) ? 1 : 0;
    }
    unset($row);
    if ($oracleAvailable && ($stockFilter === 'USED' || $stockFilter === 'STOCK')) {
        $rows = array_values(array_filter($rows, function ($row) use ($stockFilter) {
            $result = strtoupper((string)($row['result'] ?? ''));
            $used = (int)($row['used'] ?? 0);
            if ($result !== 'OK') return false;
            return $stockFilter === 'USED' ? $used === 1 : $used === 0;
        }));
    }

    $okRate = $totalCount > 0 ? round(($okCount / $totalCount) * 100, 1) : 0.0;
    $ngRate = $totalCount > 0 ? round(($ngCount / $totalCount) * 100, 1) : 0.0;

    $inventory = null;
    if ($oracle['available'] && $usedCount !== null) {
        $inventory = max(0, $okCount - $usedCount);
    }

    $summary = [
        'total' => $totalCount,
        'ok' => $okCount,
        'ng' => $ngCount,
        'other' => $otherCount,
        'ok_rate' => $okRate,
        'ng_rate' => $ngRate,
        'inventory' => $inventory,
    ];

    // モデル別の在庫計算（Oracle使用済み情報を考慮）
    $modelStats = [];
    foreach ($modelSummaryRows as $mRow) {
        $model = $mRow['model'];
        $mTotal = (int)($mRow['total_count'] ?? 0);
        $mOk = (int)($mRow['ok_count'] ?? 0);
        $mNg = (int)($mRow['ng_count'] ?? 0);
        $mOther = max(0, $mTotal - $mOk - $mNg);
        $mOkRate = $mTotal > 0 ? round(($mOk / $mTotal) * 100, 1) : 0.0;
        $mNgRate = $mTotal > 0 ? round(($mNg / $mTotal) * 100, 1) : 0.0;
        
        // モデルごとの使用済み数を計算
        $mUsed = null;
        $mInventory = null;
        if ($oracle['available'] && !empty($usedSetAll)) {
            // このモデルのOKシリアルを取得して使用済みをカウント
            $modelOkStmt = $pdo->prepare("SELECT serial FROM boxid WHERE result = 'OK' AND COALESCE(NULLIF(partsno, ''), '未設定') = :model");
            $modelOkStmt->execute([':model' => $model]);
            $modelOkSerials = $modelOkStmt->fetchAll(PDO::FETCH_COLUMN) ?: [];
            $mUsed = 0;
            foreach ($modelOkSerials as $ser) {
                if (isset($usedSetAll[$ser])) {
                    $mUsed++;
                }
            }
            $mInventory = max(0, $mOk - $mUsed);
        }
        
        $modelStats[] = [
            'model' => $model,
            'total' => $mTotal,
            'ok' => $mOk,
            'ng' => $mNg,
            'other' => $mOther,
            'ok_rate' => $mOkRate,
            'ng_rate' => $mNgRate,
            'used' => $mUsed,
            'inventory' => $mInventory,
        ];
    }

    json_response([
        'ok' => true,
        'summary' => $summary,
        'oracle' => $oracle,
        'rows' => $rows,
        'lists' => $lists,
        'model_stats' => $modelStats,
        'model_list' => $modelList,
        'filters' => [
            'date_from' => $dateFrom,
            'date_to' => $dateTo,
            'list_limit' => $listLimit,
            'stock' => $stockFilter,
            'model' => $modelFilter,
        ],
    ]);
} catch (Throwable $e) {
    json_response([
        'ok' => false,
        'error' => $e->getMessage(),
    ], 500);
}

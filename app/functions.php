<?php


/*
🐰
RRR      　A     V     V   III   TTTTTTT
R  R    　A A     V   V     I       T
RRR    　AAAAA     V V      I       T 
R  R 　 A     A     V      III      T        
version 1.0.0

🐰ttt
*/ //////////////////////////////////////////////////////////////
/*◆ 統一して使う名前（外部POST/GET/JS/SESSIONで同じキーにする）
 *   表示名    → DB列名 / 意味
 *   - carriro → cate       / 運用カテゴリ（DIAG など）
 *   - monbell → model      / モデル名（flag 解決・登録に必須）
 *   - sierra  → serial     / シリアル
 *   - papa    → parts      / 部品コード
 *   - yankee  → symptom    / 症状コード
 *   - location→ position   / 位置。未指定時は '-' を想定
 *   - fox     → flag       / fail_master.flag
 *   - identity→ ID         / fail_log.ID（削除API等で使用）
 *   これらの名前は iframe 外で定義するときも統一しておくと、呼び出し元・サーバどちらも変更が容易。
 *
 * ◆ このファイルで提供している主なサーバ関数（仕様順）
 *   - set_category (action)                     : read_category_param→check_master_min_rows経由で$_SESSION['carriro']更新
 *   - check_master_min_rows($carriro, $minRows=2, $monbell=null) : (model指定時は cate+model の存在確認)
 *   - h_get_serial($sierra)                     : シリアル英数字チェック＋一時保存
 *   - qr_to_text($qrText)                       : QR 文字列を parts/symptom に分解
 *   - get_flag($papa, $yankee) / h_get_flag     : flag・position をマスタから取得
 *   - get_partslist()                           : 現カテゴリの parts リスト
 *   - get_manual_parts_daig()                   : DIAG手動用（category=daig_human）のpartsリスト
 *   - get_symptomlist($papa)                    : parts に紐づく symptom リスト
 *   - get_positionlist_by_part($papa)           : parts に紐づく position リスト（'-' は除外）
 *   - get_categorylist($monbell=null)           : モデル別カテゴリ一覧
 *   - get_positionlist($papa, $yankee)          : position 候補（'-' は除外）
 *   - get_total_logs()                          : 現在シリアルのログ一覧
 *   - delete_one_log($identity) / admin_Delete_show_log($identity, $isAdmin)
 *   - register_log($papa, $yankee, $location)   : flag 参照のうえログ登録
 *   - register_qr($qr, ...)                     : QR 入力の登録エイリアス
 *   - register_manual($papa, $yankee, ...)      : 手動入力の登録エイリアス
 *   - set_model                                 : モデル設定（action=set_model）
 *   - save_end($carriro, $sierra=null)          : 終了ログを cat_end へ記録（serial省略時はnodata処理）
 *   - validate_parts_symptom($papa, $yankee)    : 自由入力チェック＋候補返却
 *   - register_manual_typed(...)                : serial を明示した手動登録
 *   - get_model_fromdb / get_cate_fromdb        : fail_master から model/cate 一覧取得
 *   - reset_workflow_state()                    : serial/QR の作業ループ用セッション初期化
 *
定義するときも統一しておくと、呼び出し元・サーバどちらも変更が容易。
 *
 * ◆ 主なセッション変数
 *   - $_SESSION['carriro'] : 現在のカテゴリ（POST/GET から受取）
 *   - $_SESSION['monbell'] : モデル名。flag 判定・ログ挿入に必須
 *   - $_SESSION['sierra']  : 作業中シリアル。ログ抽出や終了記録のキー
 *   - $_SESSION['papa'] / $_SESSION['yankee'] / $_SESSION['qr_location']
 *         : 最後に読み取った QR の parts/symptom/position
 */

//別に用意
//今はreborn/app/config.php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/db.php'; // getPDO() を提供

/* ===== database name 設定（config で未定義なら定義） ===== 
table名に対応して　defineの後ろ変えること　*/
if (!defined('TABLE_MASTER')) define('TABLE_MASTER', 'fail_master');
if (!defined('TABLE_LOG'))    define('TABLE_LOG',    'fail_log');
if (!defined('TABLE_LOG_BACKUP')) define('TABLE_LOG_BACKUP', 'fail_log_backup');
if (!defined('TABLE_END'))    define('TABLE_END',    'cate_end');
if (!defined('QR_DELIM'))     define('QR_DELIM',     '_');
if (!defined('DEBUG_MODE'))   define('DEBUG_MODE',    false);
if (!defined('TAbLE_BOXID'))  define('TABLE_BOXID',  'boxid');
if (!defined('JUDGE_REQUIRED_CATEGORIES')) define('JUDGE_REQUIRED_CATEGORIES', []);

/* ===== DB関連利用変数 ===== */
$TABLE_MASTER = constant('TABLE_MASTER');
$TABLE_LOG    = constant('TABLE_LOG');
$TABLE_END    = constant('TABLE_END');
$TABLE_BOXID  = constant('TABLE_BOXID');
$QR_DELIM     = constant('QR_DELIM');


//serialを読み込んで終了を押すまではそのserialをsessionで保持
//最上部に置いとくこと
session_start();

// エラーハンドリング
set_error_handler(function($errno, $errstr, $errfile, $errline) {
    error_log("[ERROR] {$errno}: {$errstr} in {$errfile}:{$errline}");
    return false;
});

ini_set('error_log', '/tmp/php-reborn-error.log');

//======================================================================
//~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~汎用関数たち(byAI)~全部必要かは知らん~~~~~~~~~~~~~~~~~~~~~~~
//=========================================================================
/** PDO を取得 */
function db(): PDO
{
    return getPDO();
}

/** デバッグログ（DEBUG_MODE=trueのときのみ出力） */
function dbg(string $label, $payload = null): void
{
    if (!DEBUG_MODE) return;
    $msg = is_scalar($payload) || $payload === null
        ? (string)$payload
        : json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    error_log("[DBG functions.php] {$label}: {$msg}");
}

function diag_trace(string $label, $payload = null): void
{
    $msg = is_scalar($payload) || $payload === null
        ? (string)$payload
        : json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    error_log("[REBORN] {$label}: {$msg}");
}

/** JSON レスポンスの共通出力 */
function json_response($data, int $status = 200): void
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

/** 必須パラメータの検査（なければ 400 を返して終了） */
function require_param(string $name, array $src)
{
    if (!isset($src[$name]) || $src[$name] === '') {
        json_response(['ok' => false, 'error' => "missing parameter: {$name}", 'error_code' => 'missing_parameter', 'param' => $name], 400);
    }
    return $src[$name];
}

function read_category_param(array $src): string
{
    $val = $src['carriro'] ?? $src['category'] ?? $src['cate'] ?? '';
    $val = is_string($val) ? trim($val) : '';
    if ($val === '') json_response(['ok' => false, 'error' => 'category-empty', 'message' => 'カテゴリが空です'], 400);
    return $val;
}
/** 文字列が英数字のみか確認する
 * シリアル入力のときとか（A–Z, a–z, 0–9） */
function is_alnum_ascii(string $s): bool
{
    return (bool)preg_match('/\A[0-9A-Za-z]+\z/', $s);
}

function set_monbell_value(?string $monbell): array
{
    $value = trim((string)$monbell);
    if ($value === '') {
        unset($_SESSION['monbell']);
        diag_trace('set_monbell_value cleared');
        return ['ok' => true, 'monbell' => null];
    }
    if (!is_alnum_ascii($value)) {
        diag_trace('set_monbell_value invalid', $value);
        return ['ok' => false, 'error' => 'model-invalid', 'warn' => 'modelは英数字のみです'];
    }
    $_SESSION['monbell'] = $value;
    diag_trace('set_monbell_value set', $value);
    return ['ok' => true, 'monbell' => $value];
}

/**
 * ログ登録時にカテゴリ名を正規化する（diag_sens → DIAG など）
 */
function normalize_carriro_label(?string $carriro): ?string
{
    if ($carriro === null) return null;
    $value = trim($carriro);
    if ($value === '') return null;
    $lower = strtolower($value);
    if ($lower === 'diag_sens' || $lower === 'daig_sens' || $value === '機能検査') {
        return 'DIAG';
    }
    if ($lower === 'diag' || $lower === 'daig') {
        return 'DIAG';
    }
    return $value;
}

/**
 * judge 用: カテゴリ一覧入力を配列へ正規化する
 * - 文字列の場合は JSON or カンマ/改行区切りを想定
 */
function normalize_category_list_input($source): array
{
    if ($source === null) return [];
    if (is_string($source)) {
        $dec = json_decode($source, true);
        if (json_last_error() === JSON_ERROR_NONE && is_array($dec)) {
            $source = $dec;
        } else {
            $source = preg_split('/[,\r\n]+/', $source);
        }
    }
    if (!is_array($source)) return [];

    $normalized = [];
    foreach ($source as $value) {
        if (is_array($value)) continue;
        $label = trim((string)$value);
        if ($label === '') continue;
        $upper = mb_strtoupper($label, 'UTF-8');
        if (!array_key_exists($upper, $normalized)) {
            $normalized[$upper] = $label;
        }
    }
    return array_values($normalized);
}



//======================================================================
//~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~仕様関数ども🎈🎈🎈🎈🎈~~~~~~~~~~~~~~
//=========================================================================
/* ============= 0. diagのマスタ存在確認 （ここモデル名cateだけ）== */
/* ============= 0. マスタ存在確認：カテゴリの行数が5件以上か ============= */
/**！！！！！！！！！！！！！モデル名の確認も追加　column model 
 * 指定 category のマスタ件数が $minRows 件以上かを確認する。
 * - OK のときだけ $_SESSION['carriro'] を設定
 
////////保険だけど、masterがあるかdiagがちゃんとあるか確認する関数.あればカテゴリとしてdaigを登録
 */
function check_master_min_rows(string $carriro, int $minRows = 2, ?string $monbell = null): array
{
    global $TABLE_MASTER;
    $pdo = db();

    $carriro = trim($carriro);
    if ($carriro === '') {
        json_response([
            'ok' => false,
            'error' => 'category-empty',
            'message' => 'カテゴリが空です'
        ], 400);
    }

    $monbell = $monbell ?? ($_SESSION['monbell'] ?? null);
    $useModel = is_string($monbell) ? trim($monbell) : '';
    $params = [':category' => $carriro];

    $sql = "SELECT COUNT(*) AS cnt FROM {$TABLE_MASTER} WHERE cate = :category";
    if ($useModel !== '') {
        $sql .= " AND model = :model";
        $params[':model'] = $useModel;
    }

    $st  = $pdo->prepare($sql);
    $st->execute($params);
    $cnt = (int)$st->fetchColumn();

    $threshold = $useModel !== '' ? 1 : $minRows;
    if ($cnt < $threshold) {
        $error = $useModel !== '' ? 'model-category-not-found' : 'masterinfo-insufficient';
        $message = $useModel !== ''
            ? '選択したmodelとcategoryの組み合わせがマスタに存在しません'
            : 'masterinfoが不十分です';
        json_response([
            'ok' => false,
            'error' => $error,
            'message' => $message,
            'count' => $cnt,
            'required' => $threshold,
            'category' => $carriro,
            'model' => $useModel !== '' ? $useModel : null,
        ], 400);
    }

    // 十分：セッションに保存
    diag_trace('check_master_min_rows ok', ['carriro' => $carriro, 'count' => $cnt, 'model' => $useModel !== '' ? $useModel : null]);
    $_SESSION['carriro'] = $carriro;

    return ['ok' => true, 'count' => $cnt, 'category' => $carriro];
}

/* ============= 1. シリアル一時保存 ============= */
//serialを読み込んで終了を押すまではそのserialをsessionで保持
function h_get_serial(string $sierra): array
{
    $sierra = trim($sierra);

    if ($sierra === '') {
        return ['ok' => false, 'error' => 'serial-empty', 'warn' => 'シリアルが空です'];
    }

    if (!is_alnum_ascii($sierra)) {
        return [
            'ok'        => false,
            'error'     => 'serial-invalid',
            'warn'      => 'シリアルでないものが入力されました（指定文字以外が混入してます）',
            'value'     => $sierra
        ];
    }

    $_SESSION['sierra'] = $sierra;
    dbg('h_get_serial', $sierra);
    diag_trace('h_get_serial', ['sierra' => $sierra, 'carriro' => $_SESSION['carriro'] ?? null]);

    return [
        'ok' => true,
        'sierra'  => $sierra,
        'carriro' => ($_SESSION['carriro'] ?? null),
        'monbell' => ($_SESSION['monbell'] ?? null)
    ];
}



//👽👽👽👽DIAG ページ専用関数（汎用NNNNNGGGGG）👽
/* ============= 2. QR を分解（英数字チェック） ============= */
function qr_to_text(string $qrText): array
{
    global $QR_DELIM;
    $t = trim($qrText);
    if ($t === '') {
        return ['ok' => false, 'error' => 'qr-empty', 'warn' => 'QRが空です'];
    }
    $_SESSION['raw_qr'] = $t;

    if (strpos($t, $QR_DELIM) === false) {
        return ['ok' => false, 'error' => 'qr-missing-delimiter', 'warn' => 'QRに区切り記号（' . $QR_DELIM . '）が含まれていません'];
    }

    $chunks  = explode($QR_DELIM, $t, 3);
    $papa   = $chunks[0] ?? '';
    $yankee = $chunks[1] ?? '';

    if ($papa === '' || !is_alnum_ascii($papa)) {
        return ['ok' => false, 'error' => 'invalid-parts', 'warn' => 'QRのpartsは英数字のみ許可', 'value' => $papa];
    }
    if ($yankee === '' || !is_alnum_ascii($yankee)) {
        return ['ok' => false, 'error' => 'invalid-symptom', 'warn' => 'QRのsymptomは英数字のみ許可', 'value' => $yankee];
    }

    $_SESSION['papa']   = $papa;
    $_SESSION['yankee'] = $yankee;

    dbg('qr_to_text', ['raw' => $qrText, 'papa' => $papa, 'yankee' => $yankee]);
    diag_trace('qr_to_text', ['raw' => $qrText, 'papa' => $papa, 'yankee' => $yankee]);
    return ['ok' => true, 'papa' => $papa, 'yankee' => $yankee];
}



//👌👌👌👌👌👌👌👌👌マスターから引いてくる関数ども（汎用OK）
/* ============= 3/8. flag 取得（完全一致） ============= */
/* get_flag */
function get_flag(string $papa, string $yankee, ?string $location = null): array
{
    global $TABLE_MASTER;
    $pdo = db();
    $carriro = $_SESSION['carriro'] ?? null;
    $monbell = $_SESSION['monbell'] ?? null;


    if (!$carriro) return ['ok' => false, 'error' => 'category-not-set', 'warn' => 'カテゴリが未設定です'];
    if ($monbell === null || trim($monbell) === '') {
        return ['ok' => false, 'error' => 'model-not-set', 'warn' => 'modelが未設定です'];
    }
    if ($papa === '' || $yankee === '') {
        return ['ok' => false, 'error' => 'parts-or-symptom-empty', 'warn' => 'parts/symptomが空です'];
    }

    $location = $location !== null ? trim($location) : null;
    if ($location === '') $location = null;

    // location が null のときは position 条件を無視して flag のみ取得する
    if ($location === null) {
        $sql = "SELECT flag, position
                FROM {$TABLE_MASTER}
                WHERE cate = :category
                  AND model = :model
                  AND parts = :parts
                  AND symptom = :symptom";
        $params = [
            ':category' => $carriro,
            ':model'    => $monbell,
            ':parts'    => $papa,
            ':symptom'  => $yankee,
        ];
    } else {
        $sql = "SELECT flag, position
                FROM {$TABLE_MASTER}
                WHERE cate = :category
                  AND model = :model
                  AND parts = :parts
                  AND symptom = :symptom
                  AND position = :position";
        $params = [
            ':category' => $carriro,
            ':model'    => $monbell,
            ':parts'    => $papa,
            ':symptom'  => $yankee,
            ':position' => $location
        ];
    }
    $st = $pdo->prepare($sql);
    $st->execute($params);
    $rows = $st->fetchAll(PDO::FETCH_ASSOC);
    $count = count($rows);

    if ($count === 0) {
        diag_trace('get_flag not-found', [
            'carriro' => $carriro,
            'model' => $monbell,
            'papa' => $papa,
            'yankee' => $yankee,
            'position' => $location
        ]);
        return ['ok' => false, 'error' => 'flag-not-found', 'warn' => 'model/cate/parts/symptom/positionに一致するマスタがありません'];
    }
    if ($count > 1) {
        diag_trace('get_flag duplicate', [
            'carriro' => $carriro,
            'model' => $monbell,
            'papa' => $papa,
            'yankee' => $yankee,
            'position' => $location,
            'rows' => $rows
        ]);
        return ['ok' => false, 'error' => 'flag-duplicate', 'warn' => 'マスタに同一キーの行が複数存在します。flag取得不可'];
    }

    $row = $rows[0];
    $flag = $row['flag'] !== null ? (int)$row['flag'] : null;
    if ($flag === null) {
        return ['ok' => false, 'error' => 'flag-null', 'warn' => 'flagが未設定です'];
    }

    dbg('get_flag ok', ['flag' => $flag, 'position' => $row['position']]);
    diag_trace('get_flag ok', [
        'carriro' => $carriro,
        'model' => $monbell,
        'papa' => $papa,
        'yankee' => $yankee,
        'position' => $row['position'],
        'flag' => $flag
    ]);
    return ['ok' => true, 'flag' => $flag, 'position' => $row['position']];
}

function h_get_flag(string $papa, string $yankee, ?string $location = null): array
{
    return get_flag($papa, $yankee, $location);
}
/* ===== 4) parts リスト ===== */
function get_partslist(): array
{
    global $TABLE_MASTER;
    $pdo = db();
    $carriro = $_SESSION['carriro'] ?? null;
    $monbell = $_SESSION['monbell'] ?? null;
    if (!$carriro) return ['ok' => false, 'error' => 'category-not-set'];

    $sql = "SELECT DISTINCT parts FROM {$TABLE_MASTER} WHERE cate = :category";
    $params = [':category' => $carriro];
    if ($monbell) {
        $sql .= " AND model = :model";
        $params[':model'] = $monbell;
    }
    $sql .= " ORDER BY parts";
    $st = $pdo->prepare($sql);
    $st->execute($params);
    return ['ok' => true, 'partslist' => array_column($st->fetchAll(PDO::FETCH_ASSOC), 'parts')];
}

function get_manual_parts_daig(): array
{
    global $TABLE_MASTER;
    $pdo = db();
    $carriro = $_SESSION['carriro'] ?? null;
    $monbell = $_SESSION['monbell'] ?? null;
    if (!$carriro) return ['ok' => false, 'error' => 'category-not-set'];

    $sql = "SELECT DISTINCT parts FROM {$TABLE_MASTER} WHERE cate = :category";
    $params = [':category' => $carriro];
    if ($monbell) {
        $sql .= " AND model = :model";
        $params[':model'] = $monbell;
    }
    $sql .= " ORDER BY parts";
    $st = $pdo->prepare($sql);
    $st->execute($params);
    return ['ok' => true, 'partslist' => array_column($st->fetchAll(PDO::FETCH_ASSOC), 'parts')];
}

/* ===== 7) symptom リスト ===== */
function get_symptomlist(string $papa): array
{
    global $TABLE_MASTER;
    $pdo = db();
    $carriro = $_SESSION['carriro'] ?? null;
    $monbell = $_SESSION['monbell'] ?? null;
    if (!$carriro) return ['ok' => false, 'error' => 'category-not-set'];
    if ($papa === '')   return ['ok' => false, 'error' => 'parts-empty'];

    $sql = "SELECT symptom FROM {$TABLE_MASTER} WHERE cate = :category AND parts = :parts";
    $params = [':category' => $carriro, ':parts' => $papa];
    if ($monbell) {
        $sql .= " AND model = :model";
        $params[':model'] = $monbell;
    }
    $sql .= " ORDER BY symptom";
    $st = $pdo->prepare($sql);
    $st->execute($params);
    return ['ok' => true, 'symptomlist' => array_column($st->fetchAll(PDO::FETCH_ASSOC), 'symptom')];
}

/* =====  8) position リスト（parts 単位。"-" は非表示） ===== */
function get_positionlist_by_part(string $papa): array
{
    global $TABLE_MASTER;
    $pdo = db();
    $carriro = $_SESSION['carriro'] ?? null;
    $monbell = $_SESSION['monbell'] ?? null;
    if (!$carriro) return ['ok' => false, 'error' => 'category-not-set'];
    if ($papa === '') return ['ok' => false, 'error' => 'parts-empty'];

    $sql = "SELECT DISTINCT position
            FROM {$TABLE_MASTER}
            WHERE cate = :category AND parts = :parts
              AND position IS NOT NULL AND position <> '-'";
    $params = [':category' => $carriro, ':parts' => $papa];
    if ($monbell) {
        $sql .= " AND model = :model";
        $params[':model'] = $monbell;
    }
    $sql .= " ORDER BY position";
    $st = $pdo->prepare($sql);
    $st->execute($params);
    $list = array_column($st->fetchAll(PDO::FETCH_ASSOC), 'position');

    return ['ok' => true, 'positionlist' => $list];
}

/**
 * get_categorylist
 * - model カラムが存在する前提で、cate のユニークリストを返す
 * - オプションで model を渡すとその model にマッチする category のみ返す
 */
function get_categorylist(?string $monbell = null): array
{
    global $TABLE_MASTER;
    $pdo = db();
    if ($monbell === null || $monbell === '') {
        $st = $pdo->prepare("SELECT DISTINCT cate AS category FROM {$TABLE_MASTER} ORDER BY category");
        $st->execute();
    } else {
        $st = $pdo->prepare("SELECT DISTINCT cate AS category FROM {$TABLE_MASTER} WHERE model = :model ORDER BY category");
        $st->execute([':model' => $monbell]);
    }
    $rows = $st->fetchAll(PDO::FETCH_ASSOC);
    return ['ok' => true, 'categorylist' => array_column($rows, 'category')];
}

/* =====  8) position リスト（parts > symptom に対する候補。"-" は非表示） ===== */
function get_positionlist(string $papa, string $yankee): array
{
    global $TABLE_MASTER;
    $pdo = db();
    $carriro = $_SESSION['carriro'] ?? null;
    $monbell = $_SESSION['monbell'] ?? null;
    if (!$carriro) return ['ok' => false, 'error' => 'category-not-set'];
    if ($papa === '' || $yankee === '') return ['ok' => false, 'error' => 'parts-or-symptom-empty'];

    $sql = "SELECT DISTINCT position
            FROM {$TABLE_MASTER}
            WHERE cate = :category AND parts = :parts AND symptom = :symptom
              AND position IS NOT NULL AND position <> '-'";
    $params = [':category' => $carriro, ':parts' => $papa, ':symptom' => $yankee];
    if ($monbell) {
        $sql .= " AND model = :model";
        $params[':model'] = $monbell;
    }
    $sql .= " ORDER BY position";
    $st = $pdo->prepare($sql);
    $st->execute($params);
    $list = array_column($st->fetchAll(PDO::FETCH_ASSOC), 'position');

    return ['ok' => true, 'positionlist' => $list]; // 空配列なら「候補なし」→ サーバ側は '-' を使う
}

/* ===== 9) ログ一覧（現在シリアル） ===== */
function get_total_logs(): array
{
    global $TABLE_LOG;
    $pdo = db();
    $sierra = $_SESSION['sierra'] ?? null;
    if (!$sierra) return ['ok' => true, 'showlogs' => []];
    // include model column in logs (model was added to table)
    $st = $pdo->prepare("SELECT ID, serial, cate, parts, symptom, position, flag, model, regtime AS gettime
                         FROM {$TABLE_LOG} WHERE serial = :serial
                         ORDER BY regtime DESC, ID DESC");
    $st->execute([':serial' => $sierra]);
    return ['ok' => true, 'showlogs' => $st->fetchAll(PDO::FETCH_ASSOC)];
}

function reset_workflow_state(bool $keepSerial = false): array
{
    $keys = ['papa', 'yankee', 'qr_location'];
    if (!$keepSerial) {
        $keys[] = 'sierra';
    }
    foreach ($keys as $key) {
        if (array_key_exists($key, $_SESSION)) {
            unset($_SESSION[$key]);
        }
    }
    return ['ok' => true, 'keep_serial' => $keepSerial];
}

/* ===== 10/11) 管理者か確認がとれたら削除　DBをいじるから危険 ===== */
function delete_log_with_backup(int $identity): array
{
    global $TABLE_LOG;
    $backupTable = defined('TABLE_LOG_BACKUP') ? constant('TABLE_LOG_BACKUP') : 'fail_log_backup';
    $pdo = db();
    $quote = function (string $name): string {
        return '`' . str_replace('`', '``', $name) . '`';
    };
    try {
        $pdo->beginTransaction();
        $srcCols = $pdo->query('DESCRIBE ' . $quote($TABLE_LOG))->fetchAll(PDO::FETCH_COLUMN);
        $bkRows = $pdo->query('DESCRIBE ' . $quote($backupTable))->fetchAll(PDO::FETCH_ASSOC);
        $bkLookup = [];
        foreach ($bkRows as $row) {
            if (!isset($row['Field'])) continue;
            $bkLookup[strtolower((string)$row['Field'])] = (string)$row['Field'];
        }
        $deleteTimeCol = $bkLookup['delete_time'] ?? null;
        $insertCols = [];
        $selectCols = [];
        foreach ($srcCols as $col) {
            $bkCol = $bkLookup[strtolower((string)$col)] ?? null;
            if (!$bkCol) {
                throw new RuntimeException("backup table missing column: {$col}");
            }
            $insertCols[] = $quote($bkCol);
            $selectCols[] = $quote((string)$col);
        }
        if ($deleteTimeCol) {
            $insertCols[] = $quote($deleteTimeCol);
            $selectCols[] = ':delete_time_value';
        }
        $sqlBackup = "INSERT INTO {$quote($backupTable)} (" . implode(', ', $insertCols) . ') ' .
            "SELECT " . implode(', ', $selectCols) . " FROM {$quote($TABLE_LOG)} WHERE {$quote('ID')} = :id";
        $stmtBackup = $pdo->prepare($sqlBackup);
        $params = [':id' => $identity];
        if ($deleteTimeCol) {
            $params[':delete_time_value'] = date('Y-m-d H:i:s');
        }
        $stmtBackup->execute($params);
        if ($stmtBackup->rowCount() === 0) {
            throw new RuntimeException('対象の行が見つかりません。');
        }
        $stmtDelete = $pdo->prepare("DELETE FROM {$quote($TABLE_LOG)} WHERE {$quote('ID')} = :id");
        $stmtDelete->execute([':id' => $identity]);
        $pdo->commit();
        dbg('delete_log_with_backup', $identity);
        return ['ok' => true, 'deleted' => $stmtDelete->rowCount()];
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        return ['ok' => false, 'error' => $e->getMessage()];
    }
}

function delete_one_log(int $identity): array
{
    return delete_log_with_backup($identity);
}

function admin_Delete_show_log(int $identity, bool $isAdmin): array
{
    if (!$isAdmin) return ['ok' => false, 'error' => 'not-admin'];
    return delete_one_log($identity);
}

/* ===== 登録（QR/手入力 共通） ===== */
function register_log(string $papa, string $yankee, ?string $location = null): array
{
    global $TABLE_LOG;
    $pdo = db();
    $sierra     = $_SESSION['sierra']  ?? null;
    $carriroRaw = $_SESSION['carriro'] ?? null;
    $carriroKey = strtolower((string)($carriroRaw ?? ''));

    if (!$sierra || !$carriroRaw) {
        diag_trace('register_log missing serial/cate', ['sierra' => $sierra, 'carriro' => $carriroRaw]);
        return ['ok' => false, 'error' => 'serial-or-category-not-set', 'warn' => 'シリアル／カテゴリが未設定です'];
    }
    if ($papa === '' || $yankee === '') {
        diag_trace('register_log missing parts/symptom', ['papa' => $papa, 'yankee' => $yankee]);
        return ['ok' => false, 'error' => 'parts-or-symptom-empty', 'warn' => 'parts/symptomが空です'];
    }
    $carriroForLog = trim((string)$carriroRaw);
    if ($carriroForLog === '') {
        diag_trace('register_log invalid category', $carriroRaw);
        return ['ok' => false, 'error' => 'category-invalid', 'warn' => 'カテゴリ名が不正です'];
    }
    $carriroLabel = normalize_carriro_label($carriroRaw) ?? $carriroForLog;
    diag_trace('register_log start', [
        'sierra' => $sierra,
        'carriro' => $carriroForLog,
        'model' => $_SESSION['monbell'] ?? null,
        'papa' => $papa,
        'yankee' => $yankee,
        'location_raw' => $location
    ]);

    $normalizeLocation = function (?string $value): string {
        $value = $value ?? '';
        $value = trim($value);
        return $value === '' ? '-' : $value;
    };

    // diag_soft のときは位置はすべて '-' を保存し、flag 取得も位置非依存で行う
    if ($carriroKey === 'diag_soft') {
        $resolvedLocation = '-';
        $foxInfo = get_flag($papa, $yankee, null);
    } else {
        // それ以外（例: diag_sens 手動）のときは、従来どおり位置候補を考慮する
        $rawLocation = $location !== null ? trim($location) : '';
        $candidates  = get_positionlist($papa, $yankee);
        if (!$candidates['ok']) {
            diag_trace('register_log positionlist error', $candidates);
            return $candidates;
        }
        $candidateList = $candidates['positionlist'] ?? [];
        $hasCandidates = count($candidateList) > 0;

        if ($hasCandidates) {
            if ($rawLocation === '') {
                diag_trace('register_log location required', ['papa' => $papa, 'yankee' => $yankee]);
                return ['ok' => false, 'error' => 'location-required', 'warn' => 'locationを選択してください'];
            }
            if (!in_array($rawLocation, $candidateList, true)) {
                diag_trace('register_log location not in candidates', ['raw' => $rawLocation, 'candidates' => $candidateList]);
                return ['ok' => false, 'error' => 'location-not-in-candidates', 'warn' => '指定の location は候補にありません'];
            }
            $resolvedLocation = $rawLocation;
        } else {
            if ($rawLocation !== '' && $rawLocation !== '-') {
                diag_trace('register_log invalid location manual', ['raw' => $rawLocation]);
                return ['ok' => false, 'error' => 'location-invalid', 'warn' => 'location候補が無い場合は\"-\"のみ指定できます'];
            }
            $resolvedLocation = $normalizeLocation($rawLocation);
        }

        $foxInfo = get_flag($papa, $yankee, $resolvedLocation);
    }
    if (!($foxInfo['ok'] ?? false)) {
        diag_trace('register_log flag error', $foxInfo);
        return $foxInfo;
    }

    $fox = (int)$foxInfo['flag'];

    $monbellParam = $_SESSION['monbell'] ?? null;
    if ($monbellParam !== null) {
        $monbellParam = trim($monbellParam);
        if ($monbellParam === '') $monbellParam = null;
    }

    try {
        $st = $pdo->prepare("INSERT INTO {$TABLE_LOG}
            (serial, cate, model, parts, symptom, flag, position, regtime)
            VALUES(:serial, :category, :model, :parts, :symptom, :flag, :position, NOW())");
        $st->execute([
            ':serial'   => $sierra,
            ':category' => $carriroForLog,
            ':model'    => $monbellParam,
            ':parts'    => $papa,
            ':symptom'  => $yankee,
            ':flag'     => $fox,
            ':position' => $resolvedLocation
        ]);
    } catch (Throwable $e) {
        diag_trace('register_log insert exception', [
            'error' => $e->getMessage(),
            'serial' => $sierra,
            'category' => $carriroForLog,
            'model' => $monbellParam,
            'parts' => $papa,
            'symptom' => $yankee,
            'flag' => $fox,
            'position' => $resolvedLocation
        ]);
        throw $e;
    }

    dbg('register_log', [
        'serial' => $sierra,
        'category' => $carriroForLog,
        'model' => $monbellParam,
        'parts' => $papa,
        'symptom' => $yankee,
        'flag' => $fox,
        'position' => $resolvedLocation
    ]);
    diag_trace('register_log success', [
        'serial' => $sierra,
        'category' => $carriroForLog,
        'model' => $monbellParam,
        'parts' => $papa,
        'symptom' => $yankee,
        'flag' => $fox,
        'position' => $resolvedLocation
    ]);

    return [
        'ok' => true,
        'inserted_id' => (int)$pdo->lastInsertId(),
        'message' => '登録が完了しました',
        'category' => $carriroForLog,
        'category_alias' => $carriroLabel
    ];
}

/* ===== 終了記録（cat_end） ===== */
function save_end(string $carriro, ?string $sierra = null, ?array $additionalCategories = null): array
{
    global $TABLE_END, $TABLE_LOG, $TABLE_MASTER;
    $pdo = db();
    $rawCarriro = trim($carriro);
    $sierra = trim((string)$sierra);
    if ($rawCarriro === '') return ['ok' => false, 'error' => 'category-empty', 'warn' => 'categoryが空です'];

    $aliasCarriro = normalize_carriro_label($rawCarriro) ?? $rawCarriro;
    diag_trace('save_end start', ['carriro' => $rawCarriro, 'sierra' => $sierra]);

    $noDataMode = false;
    if ($sierra === '') {
        $sql = "SELECT COUNT(*) FROM {$TABLE_LOG} WHERE cate = :category AND DATE(gettime) = CURDATE()";
        $stCheck = $pdo->prepare($sql);
        $stCheck->execute([':category' => $rawCarriro]);
        $cnt = (int)$stCheck->fetchColumn();
        if ($cnt > 0) {
            diag_trace('save_end serial required', ['carriro' => $rawCarriro, 'count_today' => $cnt]);
            return ['ok' => false, 'error' => 'serial-required', 'warn' => 'serialを読み取ってから終了してください'];
        }
        $sierra = 'NO_DATA_' . date('YmdHis');
        $noDataMode = true;
    }

    $insertOne = function(string $category) use ($pdo, $sierra, $TABLE_END) {
        $st = $pdo->prepare("INSERT INTO {$TABLE_END}(cate, serial, regtime) VALUES(:category, :serial, NOW())");
        $st->execute([':category' => $category, ':serial' => $sierra]);
    };
    $categoriesToInsert = [];
    $addCategory = function($name) use (&$categoriesToInsert) {
        $trim = trim((string)$name);
        if ($trim === '') return;
        $key = mb_strtolower($trim, 'UTF-8');
        if (!array_key_exists($key, $categoriesToInsert)) {
            $categoriesToInsert[$key] = $trim;
        }
    };
    $addCategory($rawCarriro);
    $prefix = mb_substr($rawCarriro, 0, 4, 'UTF-8');
    if ($prefix !== '') {
        $stRelated = $pdo->prepare("SELECT DISTINCT cate FROM {$TABLE_MASTER} WHERE cate LIKE :prefix");
        $stRelated->execute([':prefix' => $prefix . '%']);
        $relatedList = $stRelated->fetchAll(PDO::FETCH_COLUMN) ?: [];
        foreach ($relatedList as $relatedCate) {
            $addCategory($relatedCate);
        }
    }
    if (!empty($additionalCategories)) {
        foreach ($additionalCategories as $extraCate) {
            $addCategory($extraCate);
        }
    }
    foreach ($categoriesToInsert as $categoryName) {
        $insertOne($categoryName);
    }

    dbg('save_end', ['category' => $rawCarriro, 'serial' => $sierra, 'nodata' => $noDataMode]);
    diag_trace('save_end success', ['category' => $rawCarriro, 'serial' => $sierra, 'nodata' => $noDataMode]);
    return [
        'ok' => true,
        'message' => $noDataMode ? '終了フラグのみ登録しました' : '終了しました次のセットのserialを入力してください',
        'category' => $rawCarriro,
        'category_alias' => $aliasCarriro,
        'serial' => $sierra,
        'nodata' => $noDataMode
    ];
}



/* ===== 追加：自由入力の事前検証＆登録 ===== */
function validate_parts_symptom(string $papa, string $yankee, ?string $location = null): array
{
    if ($papa === '' || $yankee === '') return ['ok' => false, 'error' => 'parts-or-symptom-empty', 'warn' => 'parts/symptomが空です'];
    if (!is_alnum_ascii($papa))   return ['ok' => false, 'error' => 'invalid-parts', 'warn' => 'partsは英数字のみ'];
    if (!is_alnum_ascii($yankee)) return ['ok' => false, 'error' => 'invalid-symptom', 'warn' => 'symptomは英数字のみ'];
    $f = get_flag($papa, $yankee, $location);
    if (!$f['ok']) return $f;

    // 位置候補も返しておくとUI側で即表示できる
    $pos = get_positionlist($papa, $yankee);
    return ['ok' => true, 'flag' => $f['flag'], 'position' => $f['position'], 'candidates' => $pos['positionlist'] ?? []];
}
function register_manual_typed(string $sierra, string $papa, string $yankee, ?string $location = null): array
{
    $sierra = trim($sierra);
    if ($sierra === '' || !is_alnum_ascii($sierra)) return ['ok' => false, 'error' => 'serial-invalid', 'warn' => 'serialは英数字のみ'];
    $_SESSION['sierra'] = $sierra; // 一覧更新のため反映
    return register_log($papa, $yankee, $location);
}

function get_model_fromdb(): array
{
    global $TABLE_MASTER;
    $pdo = db();
    $sql = "SELECT DISTINCT model FROM {$TABLE_MASTER} WHERE model IS NOT NULL AND model <> '' ORDER BY model";
    $stmt = $pdo->query($sql);
    $models = [];
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $value = trim((string)$row['model']);
        if ($value === '') continue;
        $models[] = $value;
    }
    return ['models' => $models];
}

function check_cate_end(string $sierra, $carriro = null): array
{
    global $TABLE_END;
    $pdo = db();

    $sierra = trim($sierra);
    if ($sierra === '') {
        return ['ok' => false, 'error' => 'serial-empty', 'warn' => 'serialが空です'];
    }

    $required = normalize_category_list_input($carriro);
    if (empty($required) && defined('JUDGE_REQUIRED_CATEGORIES')) {
        $required = normalize_category_list_input(JUDGE_REQUIRED_CATEGORIES);
    }

    if (empty($required)) {
        return ['ok' => true, 'has_all' => true, 'required' => [], 'missing' => []];
    }

    $st = $pdo->prepare("SELECT cate FROM {$TABLE_END} WHERE serial = :serial");
    $st->execute([':serial' => $sierra]);
    $rows = $st->fetchAll(PDO::FETCH_COLUMN);
    $existing = [];
    foreach ($rows as $cate) {
        $label = trim((string)$cate);
        if ($label === '') continue;
        $existing[mb_strtoupper($label, 'UTF-8')] = true;
    }

    $missing = [];
    foreach ($required as $cate) {
        $key = mb_strtoupper($cate, 'UTF-8');
        if (!isset($existing[$key])) {
            $missing[] = $cate;
        }
    }

    return [
        'ok' => true,
        'has_all' => count($missing) === 0,
        'required' => $required,
        'missing' => $missing
    ];
}

function get_cate_fromdb(?string $monbell = null): array
{
    global $TABLE_MASTER;
    $pdo = db();
    $sql = "SELECT DISTINCT cate FROM {$TABLE_MASTER} WHERE cate IS NOT NULL AND cate <> ''";
    $params = [];
    if ($monbell !== null && $monbell !== '') {
        $sql .= " AND model = :model";
        $params[':model'] = $monbell;
    }
    $sql .= " ORDER BY cate";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $categories = [];
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $value = trim((string)$row['cate']);
        if ($value === '') continue;
        $categories[] = $value;
    }
    return ['categories' => $categories];
}

function judge_get_required_cates(?string $monbell = null): array
{
    $result = get_cate_fromdb($monbell);
    $list = $result['categories'] ?? [];
    return [
        'categories' => $list,
        'monbell' => $monbell
    ];
}

function next_missing_category(string $sierra, ?string $monbell = null, ?array $categories = null): array
{
    $sierra = trim($sierra);
    if ($sierra === '') {
        return ['ok' => false, 'error' => 'serial-empty'];
    }
    $useCategories = $categories;
    if (!is_array($useCategories) || count($useCategories) === 0) {
        $res = get_cate_fromdb($monbell);
        $useCategories = $res['categories'] ?? [];
    }
    $useCategories = array_values(array_filter(array_map(function ($v) {
        $t = trim((string)$v);
        return $t === '' ? null : $t;
    }, $useCategories), fn($v) => $v !== null));
    // DIAG 系はスキップ
    $useCategories = array_values(array_filter($useCategories, function ($cate) {
        $lower = strtolower((string)$cate);
        return $lower !== 'diag' && !str_starts_with($lower, 'diag_');
    }));
    if (count($useCategories) === 0) {
        return ['ok' => true, 'next' => null, 'missing' => []];
    }
    $check = check_cate_end($sierra, $useCategories);
    if (!($check['ok'] ?? false)) {
        return ['ok' => false, 'error' => $check['error'] ?? 'check-failed'];
    }
    $missing = $check['missing'] ?? [];
    if (empty($missing)) {
        return ['ok' => true, 'next' => null, 'missing' => []];
    }
    $missingSet = [];
    foreach ($missing as $m) {
        $missingSet[strtoupper((string)$m)] = true;
    }
    $currentCate = $_SESSION['carriro'] ?? null;
    $startIdx = -1;
    foreach ($useCategories as $idx => $cate) {
        if (strcasecmp((string)$cate, (string)$currentCate) === 0) {
            $startIdx = $idx;
            break;
        }
    }
    $total = count($useCategories);
    for ($i = 1; $i <= $total; $i++) {
        $idx = ($startIdx + $i) % $total;
        $candidate = $useCategories[$idx];
        if (isset($missingSet[strtoupper((string)$candidate)])) {
            return ['ok' => true, 'next' => $candidate, 'missing' => $missing];
        }
    }
    return ['ok' => true, 'next' => null, 'missing' => $missing];
}
/* ===== ルーター ===== */
try {
    $a = $_GET['action'] ?? $_POST['action'] ?? '';
    if ($a !== '') {
        diag_trace('router action', ['action' => $a]);
    }

    switch ($a) {
        case 'set_category': {
                // 受取: carriro（旧パラメータ名でも可）
                // 処理: read_category_param→check_master_min_rowsで存在確認し、セッションに保存
                // 返却: { ok, result:{count,category,model?} }／不備時は400
                $carriro = read_category_param($_POST + $_GET);
                $monbell = $_SESSION['monbell'] ?? null;
                $result = check_master_min_rows($carriro, 5, $monbell);
                json_response(['ok' => true, 'result' => $result]);
                break;
            }
        case 'h_get_serial': {
                // 受取: sierra
                // 処理: 英数字チェック後にセッションへ保存
                // 返却: { ok, result:{serial,...} }
                $sierra = require_param('sierra', $_POST + $_GET);
                json_response(['ok' => true, 'result' => h_get_serial($sierra)]);
                break;
            }
        case 'set_model': {
                // 受取: monbell
                // 処理: set_monbell_valueで英数字チェックしセッション更新
                // 返却: { ok, result:{monbell:null|value} }／不正時は400
                if (!array_key_exists('monbell', $_POST) && !array_key_exists('monbell', $_GET)) {
                    json_response(['ok' => false, 'error' => 'model-missing', 'message' => 'model parameter is required'], 400);
                }
                $raw = $_POST['monbell'] ?? $_GET['monbell'] ?? '';
                $res = set_monbell_value($raw);
                if (!($res['ok'] ?? false)) {
                    json_response(['ok' => false, 'error' => $res['warn'] ?? $res['error'] ?? 'model-invalid'], 400);
                }
                json_response(['ok' => true, 'result' => $res]);
                break;
            }
        case 'get_total_logs': {
                // 受取: なし（セッションのcarriro/sierraを利用）
                // 処理: 現在のシリアルで登録済みログを取得
                // 返却: { ok, result:{showlogs:[...]}}
                json_response(['ok' => true, 'result' => get_total_logs()]);
                break;
            }
        case 'qr_to_text': {
                // 受取: qr（"papa_yankee"形式文字列）
                // 処理: qr_to_textで分割し値を検証
                // 返却: { ok, result:{papa,yankee} }
                $qr = require_param('qr', $_POST + $_GET);
                json_response(['ok' => true, 'result' => qr_to_text($qr)]);
                break;
            }
        case 'get_flag': {
                // 受取: papa／yankee／任意location（model/cateはセッションから）
                // 処理: マスタで一致行を検索しflag/positionを返す（複数or0件は警告）
                // 返却: { ok, result:{flag,position} } ※warn付きの可能性あり
                $papa = require_param('papa', $_POST + $_GET);
                $yankee   = require_param('yankee', $_POST + $_GET);
                $location = $_POST['location'] ?? $_GET['location'] ?? null;
                json_response(['ok' => true, 'result' => get_flag($papa, $yankee, $location)]);
                break;
            }
        case 'get_partslist': {
                // 受取: なし（セッションcarriro使用）
                // 処理: 指定カテゴリのparts一覧
                // 返却: { ok, result:{partslist:[...]}}
                json_response(['ok' => true, 'result' => get_partslist()]);
                break;
            }
        case 'get_manual_parts_daig': {
                // 受取: なし（DIAG手動用・category=daig_humanのparts）
                // 処理: daig_humanタグが付いたparts一覧
                // 返却: { ok, result:{partslist:[...]}}
                json_response(['ok' => true, 'result' => get_manual_parts_daig()]);
                break;
            }
        case 'get_symptomlist': {
                // 受取: papa
                // 処理: 現カテゴリ＋partsでsymptom一覧
                // 返却: { ok, result:{symptomlist:[...]}}
                $papa = require_param('papa', $_POST + $_GET);
                json_response(['ok' => true, 'result' => get_symptomlist($papa)]);
                break;
            }
        case 'get_positionlist_by_part': {
                // 受取: papa
                // 処理: 現カテゴリ＋partsでposition一覧（symptomは無視）
                // 返却: { ok, result:{positionlist:[...]}}
                $papa = require_param('papa', $_POST + $_GET);
                json_response(['ok' => true, 'result' => get_positionlist_by_part($papa)]);
                break;
            }
        case 'get_categorylist': {
                // 受取: monbell（任意。未指定時はセッション値）
                // 処理: モデル別カテゴリを返却
                // 返却: { ok, result:{categorylist:[...]}}
                $monbell = $_POST['monbell'] ?? $_GET['monbell'] ?? null;
                $res = get_categorylist($monbell);
                json_response(['ok' => $res['ok'], 'result' => $res]);
                break;
            }
        case 'get_positionlist': {
                // 受取: papa／yankee
                // 処理: position候補（'-'除外）
                // 返却: { ok, result:{positionlist:[...]}}
                $papa = require_param('papa', $_POST + $_GET);
                $yankee   = require_param('yankee', $_POST + $_GET);
                json_response(['ok' => true, 'result' => get_positionlist($papa, $yankee)]);
                break;
            }
        case 'get_model_fromdb': {
                // 受取: なし
                // 処理: fail_master から model 一覧を取得
                // 返却: { ok, result:{models:[...]}}
                json_response(['ok' => true, 'result' => get_model_fromdb()]);
                break;
            }
        case 'get_cate_fromdb': {
                // 受取: monbell（任意）
                // 処理: fail_master から cate 一覧取得（model指定時は絞り込み）
                // 返却: { ok, result:{categories:[...]}}
                $monbell = $_POST['monbell'] ?? $_GET['monbell'] ?? null;
                json_response(['ok' => true, 'result' => get_cate_fromdb($monbell)]);
                break;
            }
        case 'judge_get_required_cates': {
                $monbell = $_POST['monbell'] ?? $_GET['monbell'] ?? ($_SESSION['monbell'] ?? null);
                json_response(['ok' => true, 'result' => judge_get_required_cates($monbell)]);
                break;
            }
        case 'next_missing_category': {
                $sierra = require_param('sierra', $_POST + $_GET);
                $monbell = $_POST['monbell'] ?? $_GET['monbell'] ?? ($_SESSION['monbell'] ?? null);
                $categoriesRaw = $_POST['categories'] ?? $_GET['categories'] ?? null;
                $categories = null;
                if ($categoriesRaw !== null) {
                    $dec = json_decode((string)$categoriesRaw, true);
                    if (is_array($dec)) {
                        $categories = $dec;
                    }
                }
                $res = next_missing_category($sierra, $monbell, $categories);
                if (!($res['ok'] ?? false)) {
                    json_response(['ok' => false, 'error' => $res['error'] ?? 'next-category-failed'], 400);
                }
                json_response(['ok' => true, 'result' => $res]);
                break;
            }
        case 'check_cate_end': {
                $sierra = require_param('sierra', $_POST + $_GET);
                $rawCategories = $_POST['categories'] ?? $_GET['categories'] ?? $_POST['carriro'] ?? $_GET['carriro'] ?? null;
                $res = check_cate_end($sierra, $rawCategories);
                if (!($res['ok'] ?? true)) {
                    json_response(['ok' => false, 'error' => $res['error'] ?? 'cate-end-check-failed', 'warn' => $res['warn'] ?? null], 400);
                }
                json_response(['ok' => true, 'result' => $res]);
                break;
            }
        case 'judge_register_boxid': {
                $serial  = require_param('serial', $_POST + $_GET);
                $box     = require_param('box', $_POST + $_GET);
                $partsno = $_POST['partsno'] ?? $_GET['partsno'] ?? ($_SESSION['judge_partsno'] ?? '');
                $result  = require_param('result', $_POST + $_GET);
                $res = register_boxid($serial, $box, $partsno, $result);
                json_response(['ok' => $res['ok'], 'result' => $res]);
                break;
            }
        case 'judge_recent_boxid': {
                $limit = isset($_POST['limit']) ? (int)$_POST['limit'] : (isset($_GET['limit']) ? (int)$_GET['limit'] : 5);
                $res = get_recent_boxid($limit);
                json_response(['ok' => true, 'result' => $res]);
                break;
            }
        case 'judge_boxid_stats': {
                $res = get_boxid_stats_today();
                json_response(['ok' => true, 'result' => $res]);
                break;
            }
        case 'judge_boxid_overview': {
                $limit = isset($_POST['limit']) ? (int)$_POST['limit'] : (isset($_GET['limit']) ? (int)$_GET['limit'] : 200);
                $res = get_boxid_overview($limit);
                json_response(['ok' => true, 'result' => $res]);
                break;
            }
        case 'reset_workflow_state': {
                // 受取: keep_serial（任意）
                // 処理: serial/papa などQR関連セッションの初期化（keep指定時はserialを保持）
                // 返却: { ok, result:{...} }
                $keep = filter_var($_POST['keep_serial'] ?? $_GET['keep_serial'] ?? false, FILTER_VALIDATE_BOOLEAN);
                json_response(['ok' => true, 'result' => reset_workflow_state($keep)]);
                break;
            }
        case 'register_qr': {
                // 受取: qr（papa_yankee文字列）／任意location
                // 処理: qr_to_text→register_logで自動登録
                // 返却: register_logの結果（warn含む）
                $qr   = require_param('qr', $_POST + $_GET);
                $pt   = qr_to_text($qr);
                if (empty($pt['ok'])) json_response(['ok' => false, 'error' => $pt['error'] ?? 'qr-parse-failed', 'warn' => $pt['warn'] ?? null], 400);

                // position は任意（UIで選択されていれば渡す）
                $location = $_POST['location'] ?? $_GET['location'] ?? null;
                $res  = register_log($pt['papa'], $pt['yankee'], $location);
                json_response(['ok' => $res['ok'], 'result' => $res]);
                break;
            }
        case 'register_manual': {
                // 受取: papa／yankee／任意location（手動選択）
                // 処理: register_logへ委譲
                // 返却: register_logの結果
                $papa = require_param('papa', $_POST + $_GET);
                $yankee   = require_param('yankee', $_POST + $_GET);
                $location = $_POST['location'] ?? $_GET['location'] ?? null;
                $res   = register_log($papa, $yankee, $location);
                json_response(['ok' => $res['ok'], 'result' => $res]);
                break;
            }
        case 'delete_one_log': {
                // 受取: identity（fail_log.ID）
                // 処理: 1件削除（バックアップへ退避後に削除）
                // 返却: { ok, result:{...} }
                $identity = (int)require_param('identity', $_POST + $_GET);
                $res = delete_one_log($identity);
                if (!($res['ok'] ?? false)) {
                    json_response(['ok' => false, 'error' => $res['error'] ?? 'delete-failed'], 500);
                }
                json_response(['ok' => true, 'result' => $res]);
                break;
            }
        case 'admin_Delete_show_log': {
                // 受取: identity／isAdmin
                // 処理: 管理者向け削除処理
                // 返却: { ok, result:{...} }
                $identity      = (int)require_param('identity', $_POST + $_GET);
                $isAdmin = filter_var(require_param('isAdmin', $_POST + $_GET), FILTER_VALIDATE_BOOLEAN);
                json_response(['ok' => true, 'result' => admin_Delete_show_log($identity, $isAdmin)]);
                break;
            }
        case 'save_end': {
                $carriro = read_category_param($_POST + $_GET);
                $sierra   = $_POST['sierra'] ?? $_GET['sierra'] ?? '';
                $extraCategories = [];
                $extrasRaw = $_POST['extra_categories'] ?? $_GET['extra_categories'] ?? '';
                if ($extrasRaw !== '') {
                    $decoded = json_decode((string)$extrasRaw, true);
                    if (is_array($decoded)) {
                        foreach ($decoded as $val) {
                            $trim = trim((string)$val);
                            if ($trim !== '') {
                                $extraCategories[] = $trim;
                            }
                        }
                    }
                }
                $res = save_end($carriro, $sierra, $extraCategories);
                json_response(['ok' => $res['ok'], 'result' => $res], $res['ok'] ? 200 : 400);
                break;
            }
        case 'validate_parts_symptom': {
                // 受取: papa／yankee／任意location
                // 処理: 入力バリデーション＆候補返却
                // 返却: { ok, result:{...} }
                $papa = require_param('papa', $_POST + $_GET);
                $yankee   = require_param('yankee', $_POST + $_GET);
                $location = $_POST['location'] ?? $_GET['location'] ?? null;
                json_response(['ok' => true, 'result' => validate_parts_symptom($papa, $yankee, $location)]);
                break;
            }
        case 'register_manual_typed': {
                // 受取: sierra（指定）＋papa／yankee／任意location
                // 処理: register_manual_typedでセッションserial不要の登録
                // 返却: register_manual_typedの結果
                $sierra = require_param('sierra', $_POST + $_GET);
                $papa  = require_param('papa',  $_POST + $_GET);
                $yankee    = require_param('yankee', $_POST + $_GET);
                $location = $_POST['location'] ?? $_GET['location'] ?? null;
                $res    = register_manual_typed($sierra, $papa, $yankee, $location);
                json_response(['ok' => $res['ok'], 'result' => $res]);
                break;
            }
        case 'get_model_categories': {
                $monbell = trim((string)($_POST['monbell'] ?? $_GET['monbell'] ?? ''));
                $categories = [];
                if ($monbell !== '') {
                    global $TABLE_MASTER;
                    $pdo = db();
                    // DB列名は model 想定（旧変数名monbell）
                    $st = $pdo->prepare("SELECT DISTINCT cate FROM {$TABLE_MASTER} WHERE model = :monbell");
                    $st->execute([':monbell' => $monbell]);
                    $categories = $st->fetchAll(PDO::FETCH_COLUMN) ?: [];
                }
                json_response(['ok' => true, 'result' => ['categories' => $categories]]);
                break;
            }
        default:
            json_response(['ok' => false, 'error' => 'unknown-action', 'hint' => $a], 400);
    }
} catch (Throwable $e) {
    $errMsg = $e->getMessage();
    $errFile = $e->getFile();
    $errLine = $e->getLine();
    $errTrace = $e->getTraceAsString();
    error_log("[EXCEPTION] {$errMsg} in {$errFile}:{$errLine}");
    error_log("[TRACE] {$errTrace}");
    dbg('exception', $e->getMessage());
    json_response(['ok' => false, 'error' => 'exception', 'message' => $errMsg, 'file' => $errFile, 'line' => $errLine], 500);
}

/* =========================================================
 *  判定画面用ヘルパ関数群
 * =======================================================*/

/** 指定シリアル＋model の不良一覧（右側表示用） */
function get_fail_logs_by_serial(string $serial, ?string $monbell = null): array
{
    global $TABLE_LOG;
    $pdo = db();
    $serial = trim($serial);
    if ($serial === '') return ['ok' => false, 'error' => 'serial-empty'];
    $monbell = $monbell ?? ($_SESSION['monbell'] ?? '');
    $monbell = trim((string)$monbell);

    $sql = "SELECT cate, parts, symptom, position, flag, model, regtime
            FROM {$TABLE_LOG}
            WHERE serial = :serial";
    $params = [':serial' => $serial];
    if ($monbell !== '') {
        $sql .= " AND model = :model";
        $params[':model'] = $monbell;
    }
    $sql .= " ORDER BY regtime DESC, id DESC";

    $st = $pdo->prepare($sql);
    $st->execute($params);
    return ['ok' => true, 'rows' => $st->fetchAll(PDO::FETCH_ASSOC), 'monbell' => $monbell];
}

/** BOXID 登録 */
function register_boxid(string $serial, string $box, ?string $partsno = null, ?string $result = null): array
{
    global $TABLE_BOXID;
    $pdo = db();
    $serial = trim($serial);
    $box = trim($box);
    if ($serial === '' || $box === '') return ['ok' => false, 'error' => 'serial-or-box-empty'];
    $pnSource = ($partsno !== null) ? (string)$partsno : (string)($_SESSION['judge_partsno'] ?? '');
    $pn = trim($pnSource);
    $result = ($result !== null) ? trim($result) : null;
    if ($result === '' || $result === null) {
        return ['ok' => false, 'error' => 'result-empty', 'message' => '判定結果(result)が空です'];
    }
    if ($result !== 'OK' && $result !== 'NG') {
        return ['ok' => false, 'error' => 'result-invalid', 'message' => '判定結果(result)はOKかNGのみ許可'];
    }
    $dupStmt = $pdo->prepare("SELECT COUNT(*) FROM {$TABLE_BOXID} WHERE serial = :serial");
    $dupStmt->execute([':serial' => $serial]);
    $exists = (int)$dupStmt->fetchColumn();
    if ($exists > 0) {
        return [
            'ok' => false,
            'error' => 'duplicate_serial',
            'error_code' => 'duplicate_serial',
            'message' => "エラー: このシリアルは既にBOXID登録済みです",
        ];
    }
    $st = $pdo->prepare("INSERT INTO {$TABLE_BOXID}(serial, box, partsno, result, regtime)
                         VALUES(:serial, :box, :partsno, :result, NOW())");
    $st->execute([':serial' => $serial, ':box' => $box, ':partsno' => $pn, ':result' => $result]);
    return ['ok' => true, 'message' => "{$serial}_登録_{$box} 完了", 'partsno' => $pn, 'result' => $result];
}

function get_recent_boxid(int $limit = 5): array
{
    global $TABLE_BOXID;
    $pdo = db();
    $limit = max(1, min($limit, 50));
    $st = $pdo->query("SELECT serial, box, partsno, result, regtime FROM {$TABLE_BOXID} ORDER BY regtime DESC LIMIT {$limit}");
    $rows = $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
    return ['ok' => true, 'rows' => $rows];
}

function get_boxid_stats_today(): array
{
    global $TABLE_BOXID;
    $pdo = db();
    $sql = "SELECT
                COUNT(*) AS total_count,
                SUM(CASE WHEN result = 'OK' THEN 1 ELSE 0 END) AS ok_count,
                SUM(CASE WHEN result = 'NG' THEN 1 ELSE 0 END) AS ng_count
            FROM {$TABLE_BOXID}
            WHERE DATE(regtime) = CURDATE()";
    $st = $pdo->query($sql);
    $row = $st->fetch(PDO::FETCH_ASSOC) ?: [];
    $total = (int)($row['total_count'] ?? 0);
    $ok = (int)($row['ok_count'] ?? 0);
    $ng = (int)($row['ng_count'] ?? 0);
    return [
        'ok' => true,
        'total' => $total,
        'ok_count' => $ok,
        'ng_count' => $ng,
    ];
}

function get_boxid_overview(int $recentLimit = 200): array
{
    global $TABLE_BOXID;
    $pdo = db();
    $recentLimit = max(1, min($recentLimit, 500));
    $summarySql = "SELECT
                        COUNT(*) AS total_count,
                        SUM(CASE WHEN result = 'OK' THEN 1 ELSE 0 END) AS ok_count,
                        SUM(CASE WHEN result = 'NG' THEN 1 ELSE 0 END) AS ng_count
                   FROM {$TABLE_BOXID}";
    $summary = $pdo->query($summarySql)->fetch(PDO::FETCH_ASSOC) ?: [];
    $rows = [];
    $st = $pdo->prepare("SELECT serial, box, partsno, result, regtime FROM {$TABLE_BOXID} ORDER BY regtime DESC LIMIT {$recentLimit}");
    $st->execute();
    $rows = $st->fetchAll(PDO::FETCH_ASSOC) ?: [];

    return [
        'ok' => true,
        'total' => (int)($summary['total_count'] ?? 0),
        'ok_count' => (int)($summary['ok_count'] ?? 0),
        'ng_count' => (int)($summary['ng_count'] ?? 0),
        'rows' => $rows,
    ];
}

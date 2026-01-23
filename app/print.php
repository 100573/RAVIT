<?php
declare(strict_types=1);

// 出力・内部エンコーディングをUTF-8に統一
mb_internal_encoding('UTF-8');
ini_set('default_charset', 'UTF-8');
header("Content-Type: application/json; charset=utf-8");

// ======== ここだけあなたの環境に合わせて変更 ========
$dbHost = "10.1.2.193";
$dbName = "ravit";
$dbUser = "vaio";
$dbPass = "vaio";

$table = "fail_log";   // テーブル名
$serialCol = "SERIAL";   // serial列名
$timeCol = "regtime";    // 最新時刻を取得する列名

// 印刷したい列（IDは出さない）
$cols = ["cate", "parts", "symptom", "position", "flag"];
$orderBy = "id ASC";
// ======================================================

function json_error(int $code, string $msg): void {
  http_response_code($code);
  echo json_encode(["ok"=>false, "error"=>$msg], JSON_UNESCAPED_UNICODE);
  exit;
}

$serial = trim((string)($_GET["serial"] ?? ""));
if ($serial === "") json_error(400, "serial empty");

// 許可する文字種は運用に合わせて調整
if (!preg_match('/^[0-9A-Za-z_\-\.]{1,80}$/', $serial)) {
  json_error(400, "invalid serial");
}

// マルチバイト対応の左寄せパディング
function mb_pad(string $text, int $width): string {
  $len = mb_strwidth($text, "UTF-8");
  if ($len >= $width) return $text;
  return $text . str_repeat(" ", $width - $len);
}

// 幅を超える場合は "..." を付けて丸める
function mb_limit(string $text, int $maxWidth): string {
  if ($maxWidth <= 0) return "";
  if (mb_strwidth($text, "UTF-8") <= $maxWidth) return $text;
  // 先頭文字は必ず残す（幅が小さい場合は "." の数を減らす）
  if ($maxWidth === 1) return mb_strimwidth($text, 0, 1, "", "UTF-8");
  if ($maxWidth === 2) return mb_strimwidth($text, 0, 1, "", "UTF-8") . ".";
  if ($maxWidth === 3) return mb_strimwidth($text, 0, 1, "", "UTF-8") . "..";

  $trimWidth = $maxWidth - 3; // 末尾 "..." 分を確保
  $head = mb_strimwidth($text, 0, $trimWidth, "", "UTF-8");
  return rtrim($head) . "...";
}

try {
  $dsn = "mysql:host={$dbHost};dbname={$dbName};charset=utf8mb4";
  $pdo = new PDO($dsn, $dbUser, $dbPass, [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4",
    PDO::ATTR_EMULATE_PREPARES => false,
  ]);

  // カラム名等はホワイトリストで組み立て（安全）
  $safeCols = array_map(fn($c) => "`" . str_replace("`","",$c) . "`", $cols);
  $safeTable = "`" . str_replace("`","",$table) . "`";
  $safeSerialCol = "`" . str_replace("`","",$serialCol) . "`";
  $safeTimeCol = "`" . str_replace("`","",$timeCol) . "`";

  $sql = "SELECT " . implode(", ", $safeCols) . "
          FROM {$safeTable}
          WHERE {$safeSerialCol} = ?
          ORDER BY {$orderBy}";
  $stmt = $pdo->prepare($sql);
  $stmt->execute([$serial]);
  $rows = $stmt->fetchAll();

  if (!$rows) {
    echo json_encode([
      "ok" => true,
      "serial" => $serial,
      "text" => "NOT FOUND\n",
      "count" => 0
    ], JSON_UNESCAPED_UNICODE);
    exit;
  }

  // 最新時刻（regtime）の取得
  $timeSql = "SELECT MAX({$safeTimeCol}) AS latest_time
              FROM {$safeTable}
              WHERE {$safeSerialCol} = ?";
  $timeStmt = $pdo->prepare($timeSql);
  $timeStmt->execute([$serial]);
  $timeVal = $timeStmt->fetchColumn();
  $time = ($timeVal !== false && $timeVal !== null) ? (string)$timeVal : "";

  // ===== 印刷本文（テーブル方式） =====
  $out = [];
  $count = count($rows);
  $out[] = "COUNT : " . $count;
  $out[] = "SERIAL: " . $serial;
  $out[] = "TIME: " . $time;

  // 表示名（短くして横幅を抑える）
  $labels = [
    "cate"     => "CATE",
    "parts"    => "PART",
    "symptom"  => "SYM",
    "position" => "POS",
    "flag"     => "F",
   
  ];

  // 列ごとの最大幅（必要に応じて調整）
  $caps = [
    "cate"     => 4,
    // partsが「...」になるのを避けるため幅を広げる
    "parts"    => 10,
    "symptom"  => 6,
    "position" => 6,
    "flag"     => 2,
    "regtime"  => 19,
  ];

  // 幅算出と値の丸め
  $widths = [];
  $trimmedRows = [];

  foreach ($cols as $c) {
    $label = $labels[$c] ?? strtoupper($c);
    $cap = $caps[$c] ?? 12;
    $widths[$c] = min(mb_strwidth($label, "UTF-8"), $cap);
  }

  foreach ($rows as $r) {
    $trimmed = [];
    foreach ($cols as $c) {
      $cap = $caps[$c] ?? 12;
      $val = (string)($r[$c] ?? "");
      $limited = mb_limit($val, $cap);
      $trimmed[$c] = $limited;
      $widths[$c] = max($widths[$c], mb_strwidth($limited, "UTF-8"));
    }
    $trimmedRows[] = $trimmed;
  }

  // ヘッダー
  $headerCells = [];
  foreach ($cols as $c) {
    $label = $labels[$c] ?? strtoupper($c);
    $headerCells[] = mb_pad($label, $widths[$c]);
  }
  $headerLine = implode(" | ", $headerCells);

  // 区切り線
  $separator = str_repeat("-", mb_strwidth($headerLine, "UTF-8"));

  // データ行
  $dataLines = [];
  foreach ($trimmedRows as $row) {
    $cells = [];
    foreach ($cols as $c) {
      $cells[] = mb_pad($row[$c], $widths[$c]);
    }
    $dataLines[] = implode(" | ", $cells);
  }

  array_push($out, $headerLine, $separator, ...$dataLines);

  echo json_encode([
    "ok" => true,
    "serial" => $serial,            // ← HTML側で大きく表示
    "count" => count($rows),
    "text" => implode("\n", $out),  // ← 旧レイアウト互換
    "rows" => $rows,                // ← 生データ（cate/parts/symptom/position/flag）
    "regtime" => $time              // ← 最新時刻（nowtime表示用）
  ], JSON_UNESCAPED_UNICODE);

} catch (Throwable $e) {
  json_error(500, "server error: " . $e->getMessage());
}

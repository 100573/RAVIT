<?php
// test_session_flow.php
// Simple HTTP client using file_get_contents to test session behavior
$host = 'http://127.0.0.1:8000';

function http_request($method, $path, $data = null, &$cookies = []) {
    $url = $GLOBALS['host'] . $path;
    $opts = [
        'http' => [
            'method' => $method,
            'ignore_errors' => true,
            'timeout' => 5,
            'header' => []
        ]
    ];
    if (!empty($cookies)) {
        $pairs = [];
        foreach ($cookies as $k => $v) $pairs[] = "$k=$v";
        $opts['http']['header'][] = 'Cookie: ' . implode('; ', $pairs);
    }
    if ($method === 'POST' && $data !== null) {
        $body = is_array($data) ? http_build_query($data) : (string)$data;
        $opts['http']['header'][] = 'Content-type: application/x-www-form-urlencoded';
        $opts['http']['content'] = $body;
    }
    if (!empty($opts['http']['header'])) {
        $opts['http']['header'] = implode("\r\n", $opts['http']['header']);
    }
    $ctx = stream_context_create($opts);
    $res = @file_get_contents($url, false, $ctx);
    $hdrs = isset($http_response_header) ? $http_response_header : [];
    // parse Set-Cookie
    foreach ($hdrs as $h) {
        if (stripos($h, 'Set-Cookie:') === 0) {
            $cookie = trim(substr($h, strlen('Set-Cookie:')));
            $parts = explode(';', $cookie);
            $kv = explode('=', trim($parts[0]), 2);
            if (count($kv) === 2) {
                $cookies[$kv[0]] = $kv[1];
            }
        }
    }
    return ['body' => $res, 'headers' => $hdrs];
}

function pretty($s) {
    if ($s === false) return "(no response)";
    $trim = trim($s);
    if ($trim === '') return '(empty)';
    // try json prettify
    $j = json_decode($trim, true);
    if (json_last_error() === JSON_ERROR_NONE) return json_encode($j, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    return $trim;
}

echo "Testing server at $host\n\n";

// SONOTA flow
$cookies = [];
echo "=== SONOTA: h_get_serial(TESTSON1) ===\n";
$r = http_request('GET', '/app/functions.php?action=h_get_serial&sierra=TESTSON1', null, $cookies);
echo pretty($r['body']), "\n\n";

echo "=== SONOTA: get_total_logs ===\n";
$r = http_request('GET', '/app/functions.php?action=get_total_logs', null, $cookies);
echo pretty($r['body']), "\n\n";

echo "=== SONOTA: save_end (OTHER1) ===\n";
$r = http_request('POST', '/app/functions.php', ['action'=>'save_end','carriro'=>'OTHER1','sierra'=>'TESTSON1'], $cookies);
echo pretty($r['body']), "\n\n";

echo "=== SONOTA: get_total_logs after end ===\n";
$r = http_request('GET', '/app/functions.php?action=get_total_logs', null, $cookies);
echo pretty($r['body']), "\n\n";

// INDEX flow
$cookies2 = [];
echo "=== INDEX: h_get_serial(TESTIDX1) ===\n";
$r = http_request('GET', '/app/functions.php?action=h_get_serial&sierra=TESTIDX1', null, $cookies2);
echo pretty($r['body']), "\n\n";

echo "=== INDEX: save_end (DIAG_SENS) ===\n";
$r = http_request('POST', '/app/functions.php', ['action'=>'save_end','carriro'=>'DIAG_SENS','sierra'=>'TESTIDX1'], $cookies2);
echo pretty($r['body']), "\n\n";

echo "=== INDEX: reset_workflow_state (clear session) ===\n";
$r = http_request('POST', '/app/functions.php', ['action'=>'reset_workflow_state'], $cookies2);
echo pretty($r['body']), "\n\n";

echo "=== INDEX: get_total_logs after clear ===\n";
$r = http_request('GET', '/app/functions.php?action=get_total_logs', null, $cookies2);
echo pretty($r['body']), "\n\n";

echo "Cookies sonota: "; print_r($cookies);
echo "Cookies index: "; print_r($cookies2);

// Done

<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/db.php';

$pdo = getPDO();
$messages = [];
$errors = [];

function trim_str($v): string
{
    return trim((string)$v);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $model    = trim_str($_POST['model'] ?? '');
    $cate     = trim_str($_POST['cate'] ?? '');
    $parts    = trim_str($_POST['parts'] ?? '');
    $symptom  = trim_str($_POST['symptom'] ?? '');
    $position = trim_str($_POST['position'] ?? '');
    $flagRaw  = trim_str($_POST['flag'] ?? '');

    if ($model === '' || $cate === '' || $parts === '' || $symptom === '') {
        $errors[] = 'model / cate / parts / symptom は必須です。';
    }
    $flag = ($flagRaw === '' || !is_numeric($flagRaw)) ? 1 : (int)$flagRaw;

    if (!$errors) {
        try {
            $stmt = $pdo->prepare(
                "INSERT INTO fail_master (model, cate, parts, symptom, position, flag)
                 VALUES (:model, :cate, :parts, :symptom, :position, :flag)"
            );
            $stmt->execute([
                ':model'    => $model,
                ':cate'     => $cate,
                ':parts'    => $parts,
                ':symptom'  => $symptom,
                ':position' => $position === '' ? null : $position,
                ':flag'     => $flag
            ]);
            $messages[] = '登録しました。ID: ' . $pdo->lastInsertId();
        } catch (Throwable $e) {
            $errors[] = '挿入に失敗しました: ' . $e->getMessage();
        }
    }
}
?>
<!doctype html>
<html lang="ja">

<head>
    <meta charset="utf-8" />
    <title>fail_master 手動登録</title>
    <style>
        body {
            margin: 0;
            padding: 20px;
            font-family: system-ui, -apple-system, "Segoe UI", "Noto Sans JP", sans-serif;
            background: #f8fafc;
            color: #0f172a;
        }

        h1 {
            margin: 0 0 16px;
            font-size: 26px;
        }

        form {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
            gap: 12px;
            align-items: end;
            margin-bottom: 16px;
            background: #fff;
            border: 1px solid #e5e7eb;
            padding: 16px;
        }

        label {
            display: flex;
            flex-direction: column;
            gap: 6px;
            font-weight: 700;
            color: #111827;
            font-size: 0.95em;
        }

        input[type="text"],
        input[type="number"] {
            padding: 10px 12px;
            border: 2px solid #0f172a;
            border-radius: 0;
            font-size: 1em;
            background: #fff;
        }

        .submitImage {
            grid-column: 1 / -1;
            display: flex;
            justify-content: flex-start;
            padding: 10px 0;
        }

        .submitImageBtn {
            border: none;
            background: transparent;
            padding: 0;
            cursor: pointer;
        }

        .submitImageBtn img {
            width: 45px;
            height: 45px;
            object-fit: contain;
            display: block;
            filter: drop-shadow(0 6px 16px rgba(0, 0, 0, 0.12));
        }

        .status {
            margin: 4px 0;
            padding: 10px 12px;
            border-radius: 0;
        }

        .status.ok {
            background: #ecfdf3;
            color: #166534;
            border: 1px solid #bbf7d0;
        }

        .status.err {
            background: #fef2f2;
            color: #b91c1c;
            border: 1px solid #fecaca;
        }

        .note {
            font-size: 0.9em;
            color: #475569;
        }
    </style>
</head>

<body>
    <h1>fail_master 手動登録</h1>
    <div class="note">model / cate / parts / symptom は必須。flag は省略時 1。</div>

    <?php foreach ($messages as $msg): ?>
        <div class="status ok"><?= htmlspecialchars($msg, ENT_QUOTES, 'UTF-8') ?></div>
    <?php endforeach; ?>
    <?php foreach ($errors as $err): ?>
        <div class="status err"><?= htmlspecialchars($err, ENT_QUOTES, 'UTF-8') ?></div>
    <?php endforeach; ?>

    <form method="post" id="insertForm">
        <label>
            model*
            <input type="text" name="model" required>
        </label>
        <label>
            cate*
            <input type="text" name="cate" required>
        </label>
        <label>
            parts*
            <input type="text" name="parts" required>
        </label>
        <label>
            symptom*
            <input type="text" name="symptom" required>
        </label>
        <label>
            position
            <input type="text" name="position" placeholder="任意">
        </label>
        <label>
            flag
            <input type="number" name="flag" step="1" value="1">
        </label>
        <div class="submitImage">
            <button type="button" class="submitImageBtn" id="insertTrigger" title="挿入する">
                <img src="1.png" alt="挿入">
            </button>
        </div>
    </form>

    <script>
        const insertForm = document.getElementById('insertForm');
        const insertTrigger = document.getElementById('insertTrigger');
        insertTrigger?.addEventListener('click', () => {
            if (!insertForm) return;
            if (confirm('fail_master に挿入しますか？')) {
                insertForm.submit();
            }
        });
    </script>
</body>

</html>

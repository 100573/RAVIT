<?php
require_once __DIR__ . '/../config/config.php';

function judge_required_categories_from_config(): array
{
    if (!defined('JUDGE_REQUIRED_CATEGORIES')) return [];
    $raw = JUDGE_REQUIRED_CATEGORIES;
    if (is_string($raw)) {
        $dec = json_decode($raw, true);
        if (json_last_error() === JSON_ERROR_NONE && is_array($dec)) {
            $raw = $dec;
        } else {
            $raw = preg_split('/[,\r\n]+/', $raw);
        }
    }
    if (!is_array($raw)) return [];
    $normalized = [];
    foreach ($raw as $value) {
        if (is_array($value)) continue;
        $label = trim((string)$value);
        if ($label === '') continue;
        $normalized[$label] = true;
    }
    return array_values(array_keys($normalized));
}

$judgeRequiredCategories = judge_required_categories_from_config();
?>
<!doctype html>
<html lang="ja">

<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <link rel="icon" href="judge-icon2.png" sizes="32x32" type="image/png">
    <title>最終判定</title>

    <style>
        /* =========================
   ■ テーマ変数（ここを調整すると全体の雰囲気が変わります）
   ========================= */
        :root {
            --fs: 20px;
            /* ベース文字サイズ */
            --h1: 26px;
            /* 見出しサイズ */
            --gap: 14px;
            /* 余白の基本単位 */
            --pad: 12px;
            /* パネル内パディング */
            --radius: 12px;
            /* 角丸 */
            --touch: 48px;
            /* タッチ最適高さ */

            --bg: #f7f7fb;
            /* 画面背景 */
            --card: #fff;
            /* パネル背景 */
            --txt: #111827;
            /* 文字色 */
            --muted: #6b7280;
            /* 補助文字色 */

            --ok: #059669;
            /* OK色 */
            --ng: #dc2626;
            /* NG色 */

            --bd: #e5e7eb;
            /* ボーダー */
            --shadow: 0 6px 18px rgba(0, 0, 0, .06);
            /* パネル影 */
        }

        /* =========================
   ■ ベース
   ========================= */
        * {
            box-sizing: border-box
        }

        html,
        body {
            height: 100%
        }

        body {
            margin: 0;
            background: var(--bg);
            color: var(--txt);
            font: 400 var(--fs)/1.6 system-ui, -apple-system, "Segoe UI", Roboto, "Noto Sans JP", sans-serif;
            padding: var(--gap);
        }
        .cornerActions {
            position: fixed;
            top: 12px;
            right: 12px;
            display: flex;
            align-items: center;
            gap: 10px;
            z-index: 10;
        }

        .cornerThumb {
            width: 48px;
            height: 48px;
            border-radius: 8px;
            overflow: hidden;
            box-shadow: 0 6px 14px rgba(0, 0, 0, 0.12);
            background: #e5e7eb;
            display: inline-flex;
        }

        .cornerThumb img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            display: block;
        }

        .cornerBtn {
            min-height: 48px;
            padding: 0 14px;
            border-radius: 10px;
            border: 2px solid #0f172a;
            background: #2563eb;
            color: #fff;
            font-weight: 800;
            cursor: pointer;
        }
        .cornerBtn:hover {
            background: #1d4ed8;
        }

        /* =========================
   ■ 見出し・ピル
   ========================= */
        h1 {
            margin: 0 0 2px;
            font-size: var(--h1);
            display: flex;
            align-items: center;
            gap: 2px;
        }

        h2 {
            margin: 0;
            font-size: 1.05em;
            display: flex;
            align-items: center;
            gap: 2px;
        }

        .headRow {
            display: flex;
            align-items: center;
            gap: 12px;
            margin: 0 0 6px;
        }

        .boxStats {
            margin-left: auto;
            font-size: 0.95em;
            font-weight: 700;
            color: #0f172a;
        }

        .boxStats span {
            margin-left: 8px;
        }

        #monbell-pill {
            display: none;
        }

        .dragonCard {
            position: fixed;
            top: 6px;
            left: 60%;
            transform: translateX(-50%);
            background: rgba(255, 255, 255, 0.92);
           
            
            padding: 10px 16px;
            border-radius: 10px;
            display: flex;
            align-items: center;
            gap: 14px;
            z-index: 12;
        }

        .dragonIcon {
            width: 68px;
            height: 68px;
            text-align: center;
        }

        .dragonIcon img {
            width: 100%;
            height: 100%;
            object-fit: contain;
            display: block;
        }

        .dragonBody {
            display: flex;
            flex-direction: column;
            gap: 4px;
        }

        .dragonName {
            font-weight: 800;
            color: #0f172a;
        }

        .dragonFeed {
            font-size: 0.9em;
            color: #1f2937;
        }

        .dragonMeta {
            display: flex;
            gap: 10px;
            align-items: center;
            font-size: 0.9em;
            color: #0f172a;
            font-weight: 700;
        }

        .dragonHideBtn {
            margin-left: 8px;
            border: none;
            background: transparent;
            cursor: pointer;
            font-weight: 700;
            color: #6b7280;
        }

        .dragonToggleBtn {
            position: fixed;
            top: 12px;
            right: 150px;
            padding: 6px 10px;
            border: 2px solid #94a3b8;
            background: #fff;
            color: #0f172a;
            font-weight: 700;
            cursor: pointer;
            border-radius: 8px;
            z-index: 13;
        }

        .pill {
            display: inline-flex;
            align-items: center;
            gap: 2px;
            padding: 4px 4px;
            border-radius: 0;
            background: #eef2ff;
            color: #3730a3;
            font-weight: 700;
            font-size: 1em;
            letter-spacing: 0.02em;
            min-height: 22px;
        }

        .pill+.pill {
            margin-left: 8px;
        }

        /* =========================
   ■ モデル選択ボタン（上部チップ）
   - 配色や形はここで調整
   ========================= */
        .monBtns {
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
            margin-bottom: var(--gap);
            justify-content: flex-end;
      
        }

        .chip {
            min-height: var(--touch);
            padding: 8px 14px;
            border: 1px solid var(--bd);
            border-radius: 0;
            background: #fff;
            cursor: pointer;
            font-size: 0.95em;
        }

        .chip.active {
            background: #e8f0ff;
            border-color: #082863ff;
            color: #0844a4;
            font-weight: 800;
        }

        .chip.toggle {
            border-style: dashed;
            font-weight: 600;
        }

        /* =========================
   ■ レイアウト
   - 右側の不良一覧を「半分」より少し狭くしたい場合は grid-template-columns を調整
   ========================= */
        .wrap {
            display: grid;
            gap: var(--gap);
            grid-template-columns: 1fr 1fr;
            /* 左右1:1（15インチ想定で見やすい比率） */
            /* 現状の高さを約90%に圧縮 */
            height: calc((100vh - 2*var(--gap)) * 0.9);
        }

        .panel {
            background: var(--card);
            border-radius: 0;
            box-shadow: var(--shadow);
            padding: var(--pad);
            display: flex;
            flex-direction: column;
            gap: var(--gap);
            min-height: 0;
        }

        .sideStack {
            display: flex;
            flex-direction: column;
            gap: var(--gap);
            height: 100%;
        }

        .logsPanel {
            flex: 1 1 auto;
            min-height: 0;
            overflow: hidden;
        }

        .boxPanel {
            flex: 0 0 260px;
            overflow: hidden;
        }

        .boxTableWrap {
            overflow: auto;
            max-height: 200px;
        }

        .boxTable {
            width: 100%;
            border-collapse: collapse;
            font-size: 0.95em;
        }

        .boxTable th,
        .boxTable td {
            padding: 6px 8px;
            border-bottom: 1px solid #e5e7eb;
            text-align: left;
        }

        .row {
            display: flex;
            gap: var(--gap);
            align-items: center;
            flex-wrap: wrap
        }

        /* =========================
   ■ 入力
   ========================= */
        input[type="text"] {
            min-height: var(--touch);
            padding: 12px 14px;
            border: 2px solid var(--bd);
            border-radius: 0;
            font-size: 1em;
            background: #fff;
            min-width: 260px;
            /* タッチでも押しやすい幅 */
        }

        .btnClear {
            min-height: var(--touch);
            padding: 10px 12px;
            border: 1px solid var(--bd);
            border-radius: 0;
            background: #fff;
            cursor: pointer;
            color: var(--muted);
        }
        .btnPrint {
            min-height: var(--touch);
            padding: 10px 14px;
            border-radius: 8px;
            border: 2px solid #0f172a;
            background: #2563eb;
            color: #fff;
            font-weight: 800;
            cursor: pointer;
        }
        .btnPrint:hover {
            background: #1d4ed8;
        }

        .box-disabled {
            opacity: 0.5;
            pointer-events: none;
        }

        /* =========================
   ■ 判定ボックス（左の大きなOK/NG表示）
   ========================= */
        .boxJudge {
            flex: 1;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            border-radius: 0;
            border: 3px solid #565656ff;
            font-weight: 900;
            font-size: 160px;
            letter-spacing: .08em;
            transition: background .2s, color .2s, border-color .2s;
        }

        .boxJudge .judgeSub {
            font-size: 34px;
            font-weight: 700;
            line-height: 1.2;
            margin-top: 2px;
        }

        .boxJudge.wait {
            background: #4b4f58;
            color: #fff;
            border-color: #5e626d;
        }

        .boxJudge.ok {
            background: #16a34a;
            color: #fff;
            border-color: #14532d;
            font-size: 320px;
        }

        .boxJudge.success {
            background: #dbeafe;
            color: #1d4ed8;
            border-color: #93c5fd;
        }

        .boxJudge.ng {
            background: #dc2626;
            color: #fff;
            border-color: #7f1d1d;
            font-size: 320px;
        }

        /* =========================
   ■ 不良一覧（右パネル）
   - 行間や線などはここで調整
   ========================= 
        .list {
            flex: 1;
            display: flex;
            flex-direction: column;
            border: 2px dashed var(--bd);
            border-radius: 0;
            padding: 8px;
            overflow-y: auto;
            min-height: 0;
        }
       */


        .list {
            flex: 1;
            min-height: 0;
            overflow: auto;
        }

        /* ←ここで完結 */


        .listTableWrap {
            flex: 1;
            overflow: auto;
        }

        .list table {
            width: 100%;
            border-collapse: collapse;
            font-size: .95em;
        }

        .list th,
        .list td {
            padding: 8px;
            border-bottom: 1px solid #eee;
            text-align: left;
        }

        .err {
            color: #000;
            font-size: 1.5em;
        }
        .err.is-error {
            color: #b91c1c;
        }
    </style>
</head>

<body>
    <div class="cornerActions">
        <button type="button" class="cornerBtn" id="btnKanri">在庫管理</button>
        <a class="cornerThumb" href="home.php" title="HOMEへ" aria-label="HOMEへ">
            <img src="judge-icon2.png" alt="ホームロゴ">
        </a>
    </div>
    <h1>
        最終判定


        <!-- ■ モデル（=monbell）選択ボタン群 -->
        <div class="monBtns" id="monBtns"></div>
    </h1>
    <div class="headRow">
        <h2>
            <span class="pill" id="monbell-pill">-</span>
        </h2>
        <div class="boxStats" id="boxStats"></div>
    </div>
    <div class="dragonCard" id="dragonCard">
        <div class="dragonIcon" id="dragonIcon"><img src="images/egg.jpeg" alt="dragon"></div>
        <div class="dragonBody">
            <div class="dragonName" id="dragonStage">卵</div>
            <div class="dragonMeta">
                <div class="dragonFeed" id="dragonFeed">餌: 0</div>
                <div class="dragonAscend" id="dragonAscend">昇天: 0匹</div>
            </div>
        </div>
        <button class="dragonHideBtn" id="dragonHideBtn" aria-label="閉じる">×</button>
    </div>
    <button class="dragonToggleBtn" id="dragonToggleBtn" type="button">🐉 非表示</button>

    <div class="wrap">
        <!-- ■ 左：判定＆入力 -->
        <section class="panel">
            <!-- シリアル/BOXID 行 -->
            <div class="row">
                <input type="text" id="serial" placeholder="シリアル読込（英数字）" inputmode="latin" autocomplete="off" />
                <button type="button" class="btnClear" id="btnSerialReset">クリア</button>
                <button type="button" class="btnPrint" id="btnPrint">不良印刷</button>
                
                <input type="text" id="boxid" placeholder="BOXID（良品/超良品/NG など）" inputmode="latin" autocomplete="off" />

                <input type="text" id="partsno" list="partsHistory" placeholder="partsNO（任意・保持）" inputmode="latin" style="min-width:140px; width:140px" autocomplete="off" />
                <datalist id="partsHistory"></datalist>
            </div>
            <!-- メッセージ行 -->
            <div class="row">
                <span id="errmsg" class="err"></span>
            </div>

            <!-- OK/NG 大表示 -->
            <div id="judgeBox" class="boxJudge wait">
                <div>待機</div>
                <div class="judgeSub">シリアルを入力してください</div>
            </div>


        </section>

        <!-- ■ 右：不良一覧 + BOXID 最新 -->
        <div class="sideStack">
            <aside class="panel logsPanel">
                <div class="list">
                    <div class="listTableWrap">
                        <table>
                            <thead>
                                <tr>
                                    <th>cate</th>
                                    <th>parts</th>
                                    <th>symptom</th>
                                    <th>position</th>
                                    <th>flag</th>
                                </tr>
                            </thead>
                            <tbody id="fails"></tbody>
                        </table>
                    </div>
                </div>
            </aside>
            <section class="panel boxPanel">
                <h2>BOXID 最新5件</h2>
                <div class="boxTableWrap">
                    <table class="boxTable">
                        <thead>
                            <tr>
                                <th>regtime</th>
                                <th>serial</th>
                                <th>box</th>
                                <th>result</th>
                            </tr>
                        </thead>
                        <tbody id="boxHistoryBody"></tbody>
                    </table>
                </div>
            </section>
        </div>
    </div>

    <script>
        /* ===========================================================
   フロント基盤ユーティリティ
   - API呼び出し、DOMヘルパ、メッセージ表示など
=========================================================== */
        const el = id => document.getElementById(id);
        const setErr = (text = '', isError = false) => {
            const target = el('errmsg');
            if (!target) return;
            const msg = (text ?? '').toString();
            if (msg === '') {
                target.textContent = '';
                target.classList.remove('is-error');
                return;
            }
            if (isError) {
                target.textContent = `【ERROR】：${msg}`;
                target.classList.add('is-error');
            } else {
                target.textContent = msg;
                target.classList.remove('is-error');
            }
        };
        const PRINT_PAGE_URL = 'scan.html';
        const toHalfWidth = (value = '') => {
            // 全角英数・記号を半角へ、全角スペースは半角スペースへ
            const converted = value.replace(/[！-～]/g, ch => String.fromCharCode(ch.charCodeAt(0) - 0xFEE0)).replace(/　/g, ' ');
            // ASCII以外を除去（制御文字は除外）
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
            input.addEventListener('compositionstart', () => {
                composing = true;
            });
            input.addEventListener('compositionend', () => {
                composing = false;
                normalize();
            });
            input.addEventListener('input', (e) => {
                if (composing || e.isComposing) return;
                normalize();
            });
        };
        const focusSerialField = () => {
            const field = el('serial');
            if (field) {
                field.focus();
                field.select?.();
            }
        };
        const getSerialFromUrl = () => {
            const params = new URLSearchParams(window.location.search);
            return (params.get('serial') || '').trim();
        };
        const applySerialFromUrl = async () => {
            const serial = getSerialFromUrl();
            if (!serial) return;
            const field = el('serial');
            if (!field || field.value.trim() !== '') return;
            field.value = serial;
            await runJudge();
        };
        // 入力は半角のみ許容
        enforceHalfwidthInput(el('serial'));
        enforceHalfwidthInput(el('boxid'));
        enforceHalfwidthInput(el('partsno'));
        const openPrintPage = () => {
            const serialField = el('serial');
            const serial = (serialField?.value || '').trim();
            if (!serial) {
                setErr('印刷するシリアルを入力してください', true);
                focusSerialField();
                return;
            }
            const url = `${PRINT_PAGE_URL}?serial=${encodeURIComponent(serial)}&ts=${Date.now()}`;
            window.open(url, '_blank');
        };
        const normalizeModel = (value) => {
            if (typeof value !== 'string') return '';
            return value.trim().toUpperCase();
        };
        let currentMonbell = ''; // 選択中モデル（表示用）
        let serialTimer = null; // シリアル自動判定デバウンサ
        const HIST_KEY = 'partsno_history'; // partsNO 履歴localStorageキー
        const defaultRequiredCateEnd = <?php echo json_encode($judgeRequiredCategories, JSON_UNESCAPED_UNICODE); ?>;
        let allModels = [];
        let showAllModels = false;
        let requiredCateEnd = Array.isArray(defaultRequiredCateEnd) ? [...defaultRequiredCateEnd] : [];
        let currentSerialValue = '';
        let judgeStatusTimer = null;
        let judgeRunToken = 0; // 実行中判定のキャンセル用
        let lastSuccessSerial = '';
        let lastSuccessBoxid = '';

        /** functions.php へのPOST */
        async function api(action, params = {}) {
            const body = new URLSearchParams({
                action,
                ...params
            });
            const res = await fetch('functions.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded'
                },
                body
            });
            return res.json();
        }

        function applyDefaultCateList() {
            requiredCateEnd = Array.isArray(defaultRequiredCateEnd) ? [...defaultRequiredCateEnd] : [];
        }

        function setBoxInputEnabled(canInput) {
            const boxField = el('boxid');
            boxField.disabled = !canInput;
            if (canInput) {
                boxField.classList.remove('box-disabled');
            } else {
                boxField.classList.add('box-disabled');
            }
        }

        function focusBoxInput(simulateEnter = false) {
            const boxField = el('boxid');
            if (!boxField || boxField.disabled) return;
            const focusNow = () => {
                boxField.focus();
                boxField.select?.();
                if (!simulateEnter) return;
                const evOpts = {
                    key: 'Enter',
                    code: 'Enter',
                    keyCode: 13,
                    which: 13,
                    bubbles: true
                };
                boxField.dispatchEvent(new KeyboardEvent('keydown', evOpts));
                boxField.dispatchEvent(new KeyboardEvent('keyup', evOpts));
            };
            if (typeof requestAnimationFrame === 'function') {
                requestAnimationFrame(focusNow);
            } else {
                setTimeout(focusNow, 0);
            }
        }

        function resetJudgeUI() {
            const serialField = el('serial');
            const boxField = el('boxid');
            const partsField = el('partsno');
            currentSerialValue = '';
            judgeRunToken++; // 進行中のrunJudgeを無効化
            if (serialTimer) {
                clearTimeout(serialTimer);
                serialTimer = null;
            }
            if (judgeStatusTimer) {
                clearTimeout(judgeStatusTimer);
                judgeStatusTimer = null;
            }
            if (serialField) serialField.value = '';
            if (boxField) boxField.value = '';
            if (partsField) partsField.value = '';
            setErr('');
            renderJudge('待機');
            renderFails([]);
            setBoxInputEnabled(false);
            serialField?.focus();
        }

        async function refreshRequiredCateEnd(monbell) {
            if (!monbell) {
                applyDefaultCateList();
                return;
            }
            try {
                const res = await api('judge_get_required_cates', {
                    monbell
                });
                if (res?.ok) {
                    const list = res.result?.categories || [];
                    if (Array.isArray(list) && list.length > 0) {
                        requiredCateEnd = list;
                    } else {
                        applyDefaultCateList();
                    }
                } else {
                    applyDefaultCateList();
                }
            } catch (err) {
                console.error('refreshRequiredCateEnd failed', err);
                applyDefaultCateList();
            }
        }

        /* ===========================================================
           モデル（=monbell）選択
           - get_model_fromdb で候補取得（fail_master起点）
           - get_current_monbell で前回選択を復元
           - set_monbell で選択保存
        =========================================================== */
        function renderModelButtons() {
            const box = el('monBtns');
            if (!box) return;
            box.innerHTML = '';
            if (!allModels.length) return;
            const activeName = (currentMonbell && allModels.includes(currentMonbell)) ? currentMonbell : allModels[0];
            const visibleModels = showAllModels ? allModels : [activeName];
            visibleModels.forEach(name => {
                const b = document.createElement('button');
                b.className = 'chip';
                b.textContent = name;
                if (name === activeName) b.classList.add('active');
                b.addEventListener('click', () => {
                    selectMonbell(name);
                });
                box.appendChild(b);
            });
            const toggle = document.createElement('button');
            toggle.type = 'button';
            toggle.className = 'chip toggle';
            toggle.textContent = showAllModels ? 'モデル一覧を閉じる' : '他モデルを表示';
            toggle.addEventListener('click', () => {
                showAllModels = !showAllModels;
                renderModelButtons();
            });
            box.appendChild(toggle);
        }

        async function loadMonbellButtons() {
            const [modelsRes, currentRes] = await Promise.all([
                api('get_model_fromdb'),
                api('get_current_monbell')
            ]);
            allModels = modelsRes?.result?.models || [];
            const stored = currentRes?.monbell || '';
            showAllModels = false;

            if (!allModels.length) {
                currentMonbell = '';
                el('monbell-pill').textContent = '-';
                applyDefaultCateList();
                renderModelButtons();
                return;
            }

            if (stored && allModels.includes(stored)) {
                currentMonbell = stored;
                el('monbell-pill').textContent = currentMonbell;
                await refreshRequiredCateEnd(currentMonbell);
                renderModelButtons();
            } else {
                currentMonbell = allModels[0];
                renderModelButtons();
                await selectMonbell(currentMonbell);
            }
        }

        async function selectMonbell(name) {
            if (!name) return;
            await api('set_monbell', {
                monbell: name
            });
            currentMonbell = name;
            await refreshRequiredCateEnd(name);

            el('monbell-pill').textContent = name;

            showAllModels = false;
            renderModelButtons();
            resetJudgeUI();
        }

        /* ===========================================================
           シリアル → 自動判定
           - h_get_serial へ sierra で保存
           - cate_end の必要カテゴリを確認
           - get_total_logs で fail_log を取得
           - 保存できたらBOXIDにフォーカス
        =========================================================== */
        async function runJudge() {
            const runToken = ++judgeRunToken;
            const canceled = () => runToken !== judgeRunToken;

            setErr('');
            if (!currentMonbell) {
                setErr('modelを先に設定してください', true);
                return;
            }

            const serial = el('serial').value.trim();
            if (!serial) return;

            // 1) セッション保存（※パラメータ名は sierra）
            const saved = await api('h_get_serial', {
                sierra: serial
            });
            if (canceled()) return;
            if (!saved.ok || saved.result?.ok === false) {
                setErr(saved.error || saved.result?.warn || 'serialエラー', true);
                el('serial').focus();
                return;
            }
            currentSerialValue = serial;

            let forcedCateNg = false;
            let forcedCateMsg = '';
            const mismatchMessage = 'シリアルとSKUが違います。';
            if (requiredCateEnd.length > 0) {
                const categoriesForCheck = requiredCateEnd.filter(c => typeof c === 'string' && c.trim() !== '');
                const cateRes = await api('check_cate_end', {
                    sierra: serial,
                    categories: JSON.stringify(categoriesForCheck)
                });
                if (!cateRes.ok) {
                    setErr(cateRes.error || '全検査確認でエラーが発生しました', true);
                    renderJudge('NG');
                    renderFails([]);
                    return;
                }
                if (canceled()) return;
                const cateInfo = cateRes.result || {};
                const missingLabel = (cateInfo.missing || []).join(', ') || '不足カテゴリ';
                const cateStatusLabel = cateInfo.has_all ? '全検査完了' : `外観検査が完了していません。${missingLabel})`;
                if (!cateInfo.has_all) {
                    forcedCateNg = true;
                    forcedCateMsg = cateStatusLabel;
                }
                setErr(cateStatusLabel, !cateInfo.has_all);
                await new Promise(resolve => setTimeout(resolve, 1000));
                if (forcedCateNg) {
                    renderJudge('待機');
                    setBoxInputEnabled(false);
                    return;
                }
                setErr('');
                // cate OK になったタイミングで入力許可
                setBoxInputEnabled(true);
            }

            // 2) 判定＆不良一覧の取得（functions.php の get_total_logs を利用）
            const res = await api('get_total_logs');
            console.debug('get_total_logs response', res);
            if (canceled()) return;

            if (!res.ok) {
                setErr(res.error || '判定エラー', true);
                renderJudge('NG');
                renderFails([]);
                return;
            }

            const rows = res.result?.showlogs || [];
            renderFails(rows);
            const normalizedCurrentModel = normalizeModel(currentMonbell);
            const mismatchLog = rows.find(row => {
                const logModel = normalizeModel(row.model ?? row.monbell ?? '');
                return logModel && normalizedCurrentModel && logModel !== normalizedCurrentModel;
            });
            if (mismatchLog) {
                setErr(mismatchMessage, true);
                renderJudge('待機');
                setBoxInputEnabled(false);
                return;
            }
            const hasNg = rows.some(r => {
                const flagVal = Number(r.flag);
                return Number.isFinite(flagVal) && flagVal === 0;
            });
            renderJudge(hasNg ? 'NG' : 'OK');

            setBoxInputEnabled(true);
            if (!rows.length && !hasNg) {
                setErr('OK');
            } else {
                setErr(hasNg ? '' : 'カテゴリOK');
            }
            focusBoxInput(true);
        }

        /* Enter不要：入力が止まって150ms & 英数字っぽければ自動判定 */
        el('serial').addEventListener('input', () => {
            clearTimeout(serialTimer);
            const v = el('serial').value.trim();
            serialTimer = setTimeout(() => {
                if (/^[0-9A-Za-z\-]+$/.test(v) && v.length >= 4) runJudge();
            }, 150);
        });
        el('serial').addEventListener('keydown', e => {
            if (e.key === 'Enter') {
                e.preventDefault();
                runJudge();
            }
        });
        el('serial').addEventListener('change', runJudge);
        const btnPrint = el('btnPrint');
        if (btnPrint) {
            btnPrint.addEventListener('click', openPrintPage);
        }
        const btnKanri = el('btnKanri');
        if (btnKanri) {
            btnKanri.addEventListener('click', () => {
                window.open('kanri.html', '_blank');
            });
        }

        /* シリアルクリア（作業リセット用） */
        el('btnSerialReset').addEventListener('click', () => {
            resetJudgeUI();
        });

        /* ===========================================================
           判定結果・不良一覧の描画
        =========================================================== */
        function renderJudge(result) {
            const box = el('judgeBox');
            if (!box) return;
            if (judgeStatusTimer) {
                clearTimeout(judgeStatusTimer);
                judgeStatusTimer = null;
            }
            box.classList.remove('ok', 'ng', 'success', 'wait');
            if (result === 'OK') {
                box.innerHTML = '<div>OK</div><div class="judgeSub"></div>';
                box.classList.add('ok');
            } else if (result === 'NG') {
                box.innerHTML = '<div>NG</div><div class="judgeSub"></div>';
                box.classList.add('ng');
            } else if (result === 'SUCCESS') {
                const serial = lastSuccessSerial || '-';
                const boxid = lastSuccessBoxid || '-';
                box.innerHTML = `<div>完了</div><div class="judgeSub">"${serial}　→ ${boxid}"</div>`;
                box.classList.add('success');
                judgeStatusTimer = setTimeout(() => renderJudge('待機'), 4000);
            } else {
                box.innerHTML = '<div>待機</div><div class="judgeSub">シリアルを入力してください</div>';
                box.classList.add('wait');
            }
        }

        function renderFails(rows) {
            const tb = el('fails');
            tb.innerHTML = '';
            rows.forEach(r => {
                const tr = document.createElement('tr');
                tr.innerHTML = `<td>${r.cate ?? '-'}</td>
                    <td>${r.parts ?? '-'}</td>
                    <td>${r.symptom ?? '-'}</td>
                    <td>${r.position ?? '-'}</td>
                    <td>${r.flag ?? '-'}</td>`;
                tb.appendChild(tr);
            });
        }

        async function refreshBoxHistory() {
            try {
                const res = await api('judge_recent_boxid', { limit: 5 });
                const rows = res.result?.rows || [];
                renderBoxHistory(rows);
            } catch (err) {
                console.warn('judge_recent_boxid failed', err);
            }
        }

        function renderBoxHistory(rows) {
            const tb = el('boxHistoryBody');
            if (!tb) return;
            tb.innerHTML = '';
            if (!rows || !rows.length) {
                const tr = document.createElement('tr');
                const td = document.createElement('td');
                td.colSpan = 4;
                td.textContent = 'データなし';
                tr.appendChild(td);
                tb.appendChild(tr);
                return;
            }
            rows.forEach(r => {
                const tr = document.createElement('tr');
                tr.innerHTML = `<td>${r.regtime ?? '-'}</td>
                    <td>${r.serial ?? '-'}</td>
                    <td>${r.box ?? '-'}</td>
                    <td>${r.result ?? '-'}</td>`;
                tb.appendChild(tr);
            });
        }

        async function refreshBoxStats() {
            try {
                const [todayRes, overviewRes] = await Promise.all([
                    api('judge_boxid_stats'),
                    api('judge_boxid_overview')
                ]);
                const result = todayRes.result || {};
                const total = result.total ?? 0;
                const okCount = result.ok_count ?? 0;
                const ngCount = result.ng_count ?? 0;
                const rate = total > 0 ? ((okCount / total) * 100).toFixed(1) : '0.0';
                const elStats = document.getElementById('boxStats');
                if (elStats) {
                    elStats.innerHTML = `集計: <span>投入 ${total}台</span><span>OK ${okCount}台</span><span>NG ${ngCount}台</span><span>救出率 ${rate}%</span>`;
                }
                updateDragon(overviewRes.result || {}, total);
            } catch (err) {
                console.warn('judge_boxid_stats failed', err);
            }
        }

        /* ===========================================================
           partsNO 履歴（datalist + localStorage）
           - pushPartsnoHistory(): 新規値を履歴に追加（最大20件）
           - loadPartsnoHistory(): datalist を更新
        =========================================================== */
        function loadPartsnoHistory() {
            const raw = localStorage.getItem(HIST_KEY);
            const list = raw ? JSON.parse(raw) : [];
            const dl = el('partsHistory');
            dl.innerHTML = '';
            list.forEach(v => {
                const opt = document.createElement('option');
                opt.value = v;
                dl.appendChild(opt);
            });
        }

        function pushPartsnoHistory(v) {
            let list = [];
            try {
                const raw = localStorage.getItem(HIST_KEY);
                list = raw ? JSON.parse(raw) : [];
            } catch (e) {
                list = [];
            }

            v = String(v || '').trim();
            if (!v) return;

            // 先頭に追加→重複削除→最大20件
            list = [v, ...list.filter(x => x !== v)].slice(0, 20);
            localStorage.setItem(HIST_KEY, JSON.stringify(list));
            loadPartsnoHistory();
        }

        /* 入力変化で即セーブ（空でもOK。ただし履歴には空は入れない） */
        el('partsno').addEventListener('change', async () => {
            const val = el('partsno').value;
            await api('judge_set_partsno', {
                partsno: val
            });
            if (val.trim() !== '') pushPartsnoHistory(val);
        });

        /* ===========================================================
           BOXID 登録
           - partsno が空でもそのまま登録OK
           - 成功メッセージは1秒だけ表示 → ループに戻る
        =========================================================== */

        function updateDragon(overview, todayTotal = 0) {
            const card = document.getElementById('dragonCard');
            const icon = document.getElementById('dragonIcon');
            const stageEl = document.getElementById('dragonStage');
            const feedEl = document.getElementById('dragonFeed');
            const ascendEl = document.getElementById('dragonAscend');
            const toggleBtn = document.getElementById('dragonToggleBtn');
            if (!card || !icon || !stageEl || !feedEl || !ascendEl) return;

            const total = overview.total ?? 0;
            const okCount = overview.ok_count ?? 0;
            const ngCount = overview.ng_count ?? 0;
            const rows = overview.rows || [];

            // 直近のOK/NG連続数
            let streak = 0;
            let ngStreak = 0;
            for (const row of rows) {
                const resVal = (row.result || '').toUpperCase();
                if (resVal === 'OK') {
                    if (ngStreak === 0) streak++;
                    else break;
                } else if (resVal === 'NG') {
                    if (streak === 0) ngStreak++;
                    else break;
                } else {
                    break;
                }
            }

            const todayCount = todayTotal ?? 0;
            const totalFeed = Math.floor(todayCount / 5) * 2;
            let streakFeed = 0;
            if (streak > 0) {
                streakFeed += Math.floor(streak / 5) * 3;
                const rem = streak % 5;
                if (rem >= 3) streakFeed += 2;
            }
            const rate = total > 0 ? (okCount / total) * 100 : 0;
            const thresholds = [50, 60, 70, 80];
            const rateFeed = thresholds.reduce((cnt, th) => cnt + (rate >= th ? 1 : 0), 0);
            const feed = Math.max(0, totalFeed + streakFeed + rateFeed);
            const ascCount = Math.floor(feed / 50);
            const stageFeed = feed % 50;

            let stage = '卵';
            let img = 'images/egg.jpeg';
            if (feed >= 40) {
                stage = 'MAX';
                img = 'images/max.jpeg';
            } else if (stageFeed >= 30) {
                stage = '大人';
                img = 'images/adult.jpeg';
            } else if (stageFeed >= 15) {
                stage = '子供';
                img = 'images/child.jpeg';
            } else if (stageFeed >= 5) {
                stage = '赤ちゃん';
                img = 'images/egg2.jpeg';
            }

            const imgEl = icon.querySelector('img');
            if (imgEl) {
                imgEl.src = img;
                imgEl.alt = stage;
            }
            stageEl.textContent = stage;
            feedEl.textContent = `餌: ${feed}`;
            ascendEl.textContent = `昇天: ${ascCount}匹`;

            const hideKey = 'dragon_hidden';
            const hideBtn = document.getElementById('dragonHideBtn');
            const applyHidden = () => {
                const hidden = localStorage.getItem(hideKey) === '1';
                card.style.display = hidden ? 'none' : 'flex';
                if (toggleBtn) toggleBtn.textContent = hidden ? '🐉 表示' : '🐉 非表示';
            };
            const bindToggle = (btn) => {
                if (!btn || btn.dataset.bound) return;
                btn.dataset.bound = '1';
                btn.addEventListener('click', () => {
                    const nowHidden = localStorage.getItem(hideKey) === '1';
                    localStorage.setItem(hideKey, nowHidden ? '0' : '1');
                    applyHidden();
                });
            };
            bindToggle(hideBtn);
            bindToggle(toggleBtn);
            applyHidden();
        }
        async function submitBox() {
            setErr('');
            const serial = (currentSerialValue || el('serial').value || '').trim();
            const box = el('boxid').value.trim();
            const partsno = el('partsno').value; // 空文字OK
            if (el('boxid').disabled) {
                setErr('現在はBOXIDを入力できません（OK判定後に入力してください）', true);
                return;
            }
            if (!serial) {
                setErr('シリアルを読んでください', true);
                el('serial').focus();
                return;
            }
            if (!box) {
                setErr('BOXIDを入力してください', false);
                el('boxid').focus();
                return;
            }
            if (!/^[0-9A-Za-z_-]+$/.test(box)) {
                setErr('BOXIDは英数字と - _ のみ入力してください', true);
                el('boxid').focus();
                el('boxid').select();
                return;
            }
            const normalizedPartsno = (partsno && partsno.trim() !== '') ? partsno.trim() : '';
            let result;
            try {
                result = await getJudgeResult(serial);
            } catch (e) {
                setErr(e.message || '判定エラー', true);
                return;
            }
            const r = await api('judge_register_boxid', {
                serial,
                box,
                partsno: normalizedPartsno,
                result
            });
            console.debug('judge_register_boxid response', r);
            if (!r.ok || r.result?.ok === false) {
                const apiMessage = r.result?.message || r.error || 'BOX登録エラー';
                setErr(apiMessage, true);
                const isDuplicate = (r.result?.error_code === 'duplicate_serial') || (r.error === 'duplicate_serial');
                if (isDuplicate) {
                    el('boxid').value = '';
                    el('serial').focus();
                    el('serial').select();
                }
                return;
            }

            // 成功メッセージを明示（他メッセージより優先表示）
            const beforeStatus = el('judgeBox').textContent || '';
            setErr(`${serial}_${beforeStatus}_${box} 完了`);
            lastSuccessSerial = serial;
            lastSuccessBoxid = box;
            renderJudge('SUCCESS');
            await refreshBoxHistory();
            await refreshBoxStats();
            await refreshBoxStats();

            // 履歴に追加（空は入れない）
            if (normalizedPartsno !== '') pushPartsnoHistory(normalizedPartsno);

            // ループ：シリアルをクリア→フォーカス→表示リセット
            el('serial').value = '';
            currentSerialValue = '';
            el('serial').focus();
            el('boxid').value = '';
            renderFails([]);
            setBoxInputEnabled(false);
        }
        el('boxid').addEventListener('keydown', e => {
            if (e.key === 'Enter') {
                e.preventDefault();
                submitBox();
            }
        });

        // 初期表示時にシリアルへフォーカス
        document.addEventListener('DOMContentLoaded', focusSerialField);
        el('boxid').addEventListener('change', submitBox);

        /* ===========================================================
           初期化
        =========================================================== */
        (async function boot() {
            loadPartsnoHistory(); // partsNOの過去履歴を反映
            await loadMonbellButtons(); // モデルボタン生成（自動で先頭を選択）
            setBoxInputEnabled(false);
            await applySerialFromUrl();
            if (!getSerialFromUrl()) {
                el('serial').focus(); // すぐスキャンできるようフォーカス
            }
            refreshBoxHistory();
            refreshBoxStats();
        })();

        /**
         * 指定serialのfail_logから判定結果を返す
         * flag=0が1つでもあれば"NG"、なければ"OK"
         * 判定不能ならthrow
         */
        async function getJudgeResult(serial) {
            const res = await api('get_total_logs');
            if (!res.ok || !res.result || !Array.isArray(res.result.showlogs)) {
                throw new Error('判定データ取得失敗');
            }
            const logs = res.result.showlogs;
            if (!logs.length) return 'OK';
            const hasNg = logs.some(r => Number(r.flag) === 0);
            return hasNg ? 'NG' : 'OK';
        }
    </script>
</body>

</html>

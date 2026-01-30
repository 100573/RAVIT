<?php
require_once __DIR__ . '/../config/config.php';
session_start();
$initialCarriro = trim((string)(
    $_POST['carriro'] ?? $_GET['carriro'] ??
    $_POST['category'] ?? $_GET['category'] ??
    ''
));
$initialMonbell = trim((string)(
    $_POST['monbell'] ?? $_GET['monbell'] ??
    $_POST['model'] ?? $_GET['model'] ??
    ''
));
$initialSerial = isset($_SESSION['sierra']) ? trim((string)$_SESSION['sierra']) : '';
?>
<!doctype html>
<html lang="ja">

<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>その他カテゴリ</title>
    <style>
        :root {
            --fs: 20px;
            --h1: 24px;
            --gap: 14px;
            --pad: 12px;
            --radius: 12px;
            --font-scale: 1.3;
            --bg: #f4f5fb;
            --card: #fff;
            --txt: #111827;
            --muted: #6b7280;
            --accent: #0e7afe;
            --good: #059669;
            --danger: #b91c1c;
            --bd: #e5e7eb;
            --shadow: 0 6px 18px rgba(0, 0, 0, .06);
        }

        html {
            font-size: calc(16px * var(--font-scale));
        }

        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            background: #eef1f5;
            color: var(--txt);
            font-family: system-ui, -apple-system, "Segoe UI", Roboto, "Noto Sans JP", sans-serif;
            font-weight: 400;
            font-size: calc(var(--fs) * var(--font-scale));
            line-height: 1.6;
        }

        .page {
            width: 100%;
            margin: 12px 0;
            padding: 0;
            display: flex;
            flex-direction: column;
            gap: 8px;
        }

        h1 {
            margin: 0;
            font-size: calc(28px * var(--font-scale));
            font-weight: 800;
            letter-spacing: 0.04em;
            color: #111;
        }

        .sr-only {
            position: absolute;
            width: 1px;
            height: 1px;
            margin: -1px;
            padding: 0;
            overflow: hidden;
            clip: rect(0 0 0 0);
            border: 0;
        }

        .wrap {
            display: grid;
            grid-template-columns: minmax(0, 8fr) minmax(280px, 3fr);
            gap: 0;
            min-height: 0;
            align-items: stretch;
        }

        .logsColumn {
            display: flex;
            flex-direction: column;
            gap: 12px;
            height: 100%;
        }

        .manualStack {
            display: flex;
            flex-direction: column;
            gap: 0;
            height: 100%;
        }

        .manualPanel {
            flex: 1 1 auto;
        }

        .actionPanel {
            padding: 14px;
            background: #fff;
            margin-top: auto;
        }

        .panel {
            background: #fff;
            border-radius: 0;
            border: none;
            padding: 14px;
            display: flex;
            flex-direction: column;
            gap: 12px;
        }

        .wrap>.panel {
            height: 100%;
        }

        .logsPanel {
            flex: 1 1 auto;
        }

        .title {
            font-weight: 800;
            color: var(--muted);
        }

        .row {
            display: flex;
            gap: var(--gap);
            align-items: center;
            flex-wrap: wrap;
        }

        input[type="text"] {
            min-height: 48px;
            padding: 10px 12px;
            border: 2px solid #0f172a;
            border-radius: 0;
            font-size: 1em;
            background: #fff;
            min-width: 260px;
        }

        .btn {
            min-height: 48px;
            padding: 12px 18px;
            border-radius: 0;
            border: 2px solid #0f172a;
            background: #fff;
            color: #0f172a;
            font-weight: 700;
            cursor: pointer;
            font-size: 1em;
        }

        .btn.tiny {
            min-height: 40px;
            padding: 8px 10px;
            font-size: .9em;
            background: #fff;
        }
        #btnEnd {
            min-height: 160px;
            padding: 16px 12px;
            font-size: 1.9em;
            letter-spacing: 0.08em;
            border: 4px solid #169d26ff;
            background: #319929ff;
            color: #ffffffff;
        }

        .btn.ghost {
            background: #22872cff;
            min-height: 60px;
            padding: 8px 10px;
            font-size: .9em;
            border-color: #ffffffff;
            color: #fff;
        }

        /* クリアボタン専用（ghost + tiny）: 背景は白、文字は黒 */
        .btn.ghost.tiny {
            background: #fff;
            color: #0f172a;
            border-color: #0f172a;
        }

        .btn.good {
            background: #111827;
            border-color: #111827;
            color: #fff;
        }

        .btn.danger {
            background: #f97316;
            border-color: #c2410c;
            color: #fff;
        }

        .serialChip {
            padding: 10px 16px;
            border-radius: 0;
            background: #1d4ed8;
            color: #fff;
            font-weight: 700;
            min-width: 240px;
            text-align: center;
            margin-bottom: 12px;
            letter-spacing: 0.04em;
        }

        .serialHint {
            font-size: .9em;
            color: #475569;
        }

        .hide {
            display: none !important;
        }

        .scanFields {
            display: flex;
            flex-wrap: wrap;
            gap: 12px;
            margin-top: 8px;
        }


        .scanField {
            display: flex;
            flex-direction: column;
            font-size: .85em;
            color: #475569;
            gap: 4px;
            flex: 1 1 260px;
        }

        .scanField label {
            font-weight: 700;
        }

        .scanField input {
            width: 100%;
            min-width: 0;
            padding: 11px 13px;
            border: 2px solid #0f172a;
            border-radius: 0;
            background: #f1f5f9;
            font-size: 1.3em;
        }

        .scanInputRow {
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .scanInputRow input {
            flex: 1 1 auto;
        }

        .scanInputRow .btn.tiny {
            flex: 0 0 auto;
            padding: 8px 10px;
            min-width: auto;
        }

        .heroNotice {
            border-radius: 0;
            padding: 12px;
            background: linear-gradient(135deg, #0ea5e9, #0284c7);
            color: #fff;
            font-size: 1.7em;
            font-weight: 600;
            text-align: center;
            letter-spacing: 0.02em;
            box-shadow: 0 10px 24px rgba(0, 0, 0, 0.16);
        }

        .heroNotice.state-manual {
            background: linear-gradient(135deg, #20096bff, #3a36afff);
        }

        .msgArea {
            display: flex;
            gap: 12px;
            color: var(--muted);
            min-height: 18px;
        }

        .msg {
            color: #111827;
            font-size: 1.5em;
        }

        .err {
            color: #ff0000ff;
            font-size: 1.5em;
            font-weight: 700;
        }

        .manualGrid {
            display: grid;
            /* 部品/症状/位置を3カラムで並べる */
            grid-template-columns: repeat(3, 1fr);
            gap: 12px;
        }

        .manualGrid .box {
            /* 高さをそろえてスクロール領域を確保 */
            min-height: 420px;
            height: 420px;
            font-size: 1.1em;
        }

        .box {
            border: 2px solid #1f2937;
            border-radius: 0;
            padding: 14px;
            min-height: 120px;
            display: flex;
            flex-direction: column;
            gap: 10px;
            background: #fdfdfd;
        }

        .chipList {
            display: grid;
            /* 1列固定 */
            grid-template-columns: repeat(1, minmax(0, 1fr));
            gap: 8px;
            /* ボタン間の余白 */
            align-content: flex-start;
            /* 上詰めで配置 */
            flex: 1 1 auto;
            /* 親内で伸縮 */
            overflow-y: auto;
            /* 縦方向スクロール */
        }

        .chipList.dense {
            grid-template-columns: repeat(1, minmax(0, 1fr));
        }

        .chipList.compact {
            grid-template-columns: repeat(1, minmax(0, 1fr));
        }

        .chip.block {
            width: 100%;
            display: flex;
            align-items: center;
            justify-content: left;
            text-align: left;
            border: 0;
            border-radius: 0;
            padding: 8px 12px;
            background: #1f2937;
            color: #fff;
            font-weight: 580;
            font-size: 1em;
        }

        .chip.block.active {
            background: #0ea5e9;
        }

        .chipClear {
            margin-left: 8px;
            padding: 4px 10px;
            border: 1px solid #0f172a;
            background: #fff;
            color: #0f172a;
            cursor: pointer;
            font-weight: 700;
        }

        .selectionInfo {
            margin-top: 8px;
            font-size: .9em;
            color: #475569;
        }

        .selectionInfo strong {
            color: #111827;
        }

        .logs {
            list-style: none;
            margin: 0;
            padding: 0;
            display: flex;
            flex-direction: column;
            gap: 8px;
            flex: 1 1 auto;
            overflow-y: auto;
            counter-reset: log-counter;
        }

        .logs li {
            border-bottom: 1px dashed #fdbbbcff;
            padding: 6px 8px 6px 40px;
            font-weight: 400;
            color: #0f172a;
            position: relative;
            border-radius: 0;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .logs li::before {
            content: counter(log-counter) '. ';
            counter-increment: log-counter;
            position: absolute;
            left: 10px;
            top: 50%;
            transform: translateY(-50%);
            font-weight: 700;
            color: #1d4ed8;
        }

        .logs li.latest {
            background: rgba(14, 122, 254, .12);
            border-color: rgba(14, 122, 254, .4);
        }

        .logText {
            flex: 1 1 auto;
            min-width: 0;
        }

        .logDeleteBtn {
            border: 1px solid #dc2626;
            background: #ef4444;
            color: #fff;
            padding: 4px 8px;
            border-radius: 6px;
            cursor: pointer;
            font-weight: 700;
            font-size: 0.75em;
            white-space: nowrap;
            flex-shrink: 0;
            min-width: 40px;
            text-align: center;
        }

        @media (max-width: 1024px) {
            .wrap {
                grid-template-columns: 1fr;
            }
        }

        .logsPanel {
            padding-left: 0;
            min-height: 620px;
            height: 620px;
            gap: 0;
            background: #eef2ff;
            display: flex;
            flex-direction: column;
            overflow: hidden;
        }

        .logHeader {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
        }

        .logHeader h2 {
            margin: 0;
            font-size: 1.2em;
        }

        .logHint {
            margin: 4px 0 0;
            font-size: .85em;
            color: var(--muted);
        }

        .logsEmpty {
            text-align: center;
            color: #475569;
            padding: 16px;
            border: 1px dashed rgba(15, 23, 42, 0.2);
            border-radius: 0;
            background: rgba(255, 255, 255, 0.4);
        }

        /* ===== キーボードピッカー ===== */
        .keyboard-picker-row {
            display: flex;
            align-items: center;
            gap: 8px;
            margin-bottom: 6px;
        }

        .keyboard-picker-btn {
            border-style: dashed;
            padding: 6px 8px;
            font-size: 0.9em;
        }

        .keypicker-backdrop {
            position: fixed;
            inset: 0;
            background: rgba(0, 0, 0, 0.45);
            display: flex;
            align-items: center;
            justify-content: center;
            z-index: 9999;
        }

        .keypicker-backdrop.hidden {
            display: none;
        }

        .keypicker-dialog {
            background: #fff;
            border-radius: 0;
            padding: 16px;
            width: 90vw;
            min-width: 1100px;
            max-width: 1600px;
            max-height: 90vh;
            overflow: auto;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.3);
            display: flex;
            flex-direction: column;
            gap: 8px;
        }

        .keypicker-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            font-weight: bold;
            gap: 8px;
        }

        .keypicker-close {
            border: none;
            background: transparent;
            font-size: 20px;
            cursor: pointer;
        }

        .keyboard {
            display: flex;
            flex-direction: column;
            gap: 4px;
            margin-top: 4px;
            align-items: flex-start;
            width: 100%;
        }

        .keyboard-row {
            display: flex;
            gap: 4px;
            flex-wrap: wrap;
            justify-content: flex-start;
            width: 100%;
        }

        .key-button {
            flex: 0 0 auto;
            min-width: 40px;
            padding: 6px 4px;
            border-radius: 0;
            border: 1px solid #ccc;
            font-size: 11px;
            text-align: center;
            cursor: pointer;
            user-select: none;
            background: #f7f7f7;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            white-space: nowrap;
        }

        .key-button:hover {
            background: #e0ebff;
            border-color: #5b8def;
        }

        .key-button.active {
            background: #5b8def;
            color: #fff;
            border-color: #2b5bd8;
        }

        .key-wide-1_5 {
            min-width: 70px;
        }

        .key-wide-2 {
            min-width: 63px;
        }

        .key-wide-2_5 {
            min-width: 115px;
        }

        .key-wide-3 {
            min-width: 98px;
        }

        .key-wide-4 {
            min-width: 140px;
        }

        .key-space {
            min-width: 146px;
        }

        .key-spacer {
            flex: 1 1 auto;
        }

        .keyboard-row.arrows {
            justify-content: flex-end;
        }

        .keyboard-row.arrow-row {
            display: flex;
            align-items: flex-start;
            gap: 4px;
            margin-top: 4px;
        }

        .arrow-filler {
            flex: 0 0 65%;
        }

        .arrow-group {
            display: grid;
            grid-template-columns: repeat(3, auto);
            grid-template-rows: repeat(2, auto);
            gap: 4px;
        }

        .arrow-group .key-button {
            min-width: 32px;
            padding: 4px 2px;
            font-size: 10px;
        }

        .arrow-group .empty {
            visibility: hidden;
        }

        .ok {
            color: var(--good);
        }

        .errMsg {
            color: var(--danger);
        }

        .rightButtons {
            display: flex;
            flex-direction: row;
            gap: 12px;
            justify-content: flex-end;
        }

        .actionBtn {
            min-height: 120px; /* 2倍以上に拡大 */
            font-size: 1.6em; /* 1.5倍に拡大 (1.05 → 1.6) */
            flex: 1 1 0;
            border-radius: 8px;
            border: 2px solid #ea580c;
            background: linear-gradient(180deg, #f97316 0%, #ea580c 100%);
            color: #fff;
            font-weight: 800;
            box-shadow: 0 8px 16px rgba(0, 0, 0, 0.18);
        }
        /* 登録ボタン用の色を優先適用（.btn.good の黒を上書き） */
        .btn.good.actionBtn {
            background: linear-gradient(180deg, #f97316 0%, #ea580c 100%);
            border-color: #ea580c;
            color: #fff;
        }

        @media (max-width: 1024px) {
            .rightButtons {
                flex-direction: column;
            }
        }
    </style>
</head>

<body data-init-carriro="<?= htmlspecialchars($initialCarriro, ENT_QUOTES, 'UTF-8') ?>"
    data-init-monbell="<?= htmlspecialchars($initialMonbell, ENT_QUOTES, 'UTF-8') ?>"
    data-init-serial="<?= htmlspecialchars($initialSerial, ENT_QUOTES, 'UTF-8') ?>">
    <div class="page">
        <span id="cat-pill" class="sr-only">未選択</span>

        <div class="wrap">
            <div class="manualStack">
                <section class="panel manualPanel">
                    <div id="serialPane">

                        <div class="scanFields" id="scanFieldsWrap">
                            <div class="scanField">

                                <div class="scanInputRow">
                                    <input type="text" id="serialScanInput" placeholder="Serial" autocomplete="off" inputmode="latin" />
                                    <button type="button" class="btn ghost tiny" id="btnSerialReset">クリア</button>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div id="workflowNotice" class="heroNotice state-serial">
                        <span id="workflowNoticeText">Serialを読んでください</span>
                    </div>
                    <div class="msgArea">
                        <span id="msg" class="msg"></span>
                        <span id="errmsg" class="err"></span>
                    </div>

                    <div class="manualGrid" id="manualArea">
                        <div class="box">
                            <div class="title">部品 <button type="button" class="chipClear" id="btnClearSelections">クリア</button></div>
                            <div class="chipList" id="partsList"></div>
                        </div>
                        <div class="box">
                            <div class="title">不良位置</div>
                            <div class="chipList" id="locationList"></div>
                        </div>
                        <div class="box">
                            <div class="title">症状</div>
                            <div class="chipList" id="symptomList"></div>
                        </div>
                    </div>
                </section>

                <section class="panel actionPanel" id="manualActionsPanel">
                    <div class="rightButtons">
                        <button class="btn good actionBtn" id="btnRegister">登録</button>
                      <!--  <button class="btn ghost actionBtn" id="btnEnd">終了</button>  -->
                    </div>
                </section>
            </div>

            <div class="logsColumn">
                <aside class="panel logsPanel">
                    <div class="logHeader">
                        <div>
                            <h2>不良一覧</h2>
                            <p class="logHint">最新順</p>
                        </div>
                    </div>
                    <ul class="logs" id="logs"></ul>
                </aside>
                 <section class="panel actionPanel" id="manualActionsPanel">
                    <div class="rightButtons">
                      <!--  <button class="btn good actionBtn" id="btnRegister">登録</button> -->
                      <button class="btn ghost actionBtn" id="btnEnd">終了</button> 
                    </div>
                </section>
            </div>
        </div>
    </div>

    <!-- キーボード選択モーダル（parts=キーボード時は一旦停止）
    <div id="keyPickerModal" class="keypicker-backdrop hidden">
        <div class="keypicker-dialog">
            <div class="keypicker-header">
                <span>キーを選択</span>
                <button type="button" id="closeKeyPicker" class="keypicker-close">&times;</button>
            </div>
            <div id="keyboardContainer" class="keyboard"></div>
        </div>
    </div>
    -->

    <script>
            // PHPから初期serial値を取得
            const initialSerial = document.body.dataset.initSerial || '';
        const el = id => document.getElementById(id);
            // カテゴリ完了色付けUI更新関数（グローバル定義）
            function updateCategoryCompletionUI() {
                // 例: カテゴリ選択UIがel('cat-pill')で表示されている場合
                const pill = el('cat-pill');
                if (!pill || !window.completedCategories) return;
                const currentCat = window.currentCategory || '';
                if (window.completedCategories.includes(currentCat)) {
                    pill.textContent = currentCat + '（完了）';
                    pill.style.background = '#059669';
                    pill.style.color = '#fff';
                } else {
                    pill.textContent = currentCat || '未選択';
                    pill.style.background = '';
                    pill.style.color = '';
                }
            }
        const setMsg = t => {
            el('msg').textContent = t || '';
            el('errmsg').textContent = '';
        };
        const setErr = t => {
            el('errmsg').textContent = t || '';
            el('msg').textContent = '';
        };
        const toHalfWidth = (value = '') => {
            const converted = value.replace(/[！-～]/g, ch => String.fromCharCode(ch.charCodeAt(0) - 0xFEE0)).replace(/　/g, ' ');
            return converted.replace(/[^\x20-\x7E]/g, '');
        };
        const enforceHalfwidthInput = (input, onNormalized) => {
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
                if (typeof onNormalized === 'function' && converted.trim() !== '') {
                    // 変換後に追加入力なくても即処理できるよう少し遅延
                    setTimeout(() => onNormalized(converted), 0);
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
        const isAlnum = s => /^[0-9A-Za-z]+$/.test(s || '');
        const normalizeLogField = (value) => {
            if (value === null || value === undefined) return '-';
            const str = String(value).trim();
            return str === '' ? '-' : str;
        };
        const normalizeKey = (value) => normalizeLogField(value).toUpperCase();
        const uniqueOptions = (list = []) => {
            const seen = new Set();
            const result = [];
            (list || []).forEach(item => {
                const normalized = normalizeLogField(item);
                const key = normalized.toUpperCase();
                if (!seen.has(key)) {
                    seen.add(key);
                    result.push(normalized);
                }
            });
            return result;
        };
        let cachedLogs = [];
        const notifyParent = (payload) => {
            if (!window.parent || window.parent === window) return;
            try {
                window.parent.postMessage(payload, '*');
            } catch (err) {
                console.warn('postMessage failed', err);
            }
        };
        const notifySerialUpdate = (serial) => {
            const value = (serial || '').trim();
            if (!value) return;
            notifyParent({
                type: 'serial-updated',
                serial: value
            });
        };
        const notifyCategoryChanged = (category) => {
            const value = (category || '').trim();
            if (!value) return;
            notifyParent({
                type: 'child-category-changed',
                category: value
            });
        };
        const notifyCateEndUpdate = (serial) => {
            const value = (serial || '').trim();
            if (!value) return;
            notifyParent({
                type: 'cate-end-updated',
                serial: value
            });
        };
        const notifySerialCleared = () => {
            notifyParent({
                type: 'serial-cleared'
            });
        };
        const hasDuplicateLog = (parts, symptom, position = '-') => {
            const partKey = normalizeKey(parts);
            const symptomKey = normalizeKey(symptom);
            const positionKey = normalizeKey(position);
            return cachedLogs.some(row =>
                normalizeKey(row.parts) === partKey &&
                normalizeKey(row.symptom) === symptomKey &&
                normalizeKey(row.position) === positionKey
            );
        };
        const KEYBOARD_SYMPTOM = 'キーボード';
        const keyPickerModal = document.getElementById('keyPickerModal');
        const keyboardContainer = document.getElementById('keyboardContainer');
        const closeKeyPickerBtn = document.getElementById('closeKeyPicker');
        let keyboardRendered = false;
        let keyboardTriggerBtn = null;
        let keyboardSelectionLabel = null;

        function clearLogsUI(message = 'serial未入力') {
            cachedLogs = [];
            const ul = el('logs');
            if (!ul) return;
            ul.innerHTML = '';
            const li = document.createElement('li');
            li.className = 'logsEmpty';
            li.textContent = message;
            ul.appendChild(li);
        }

        /* =========================
           キーボードピッカー
        ========================= */
        const keyboardRows = [{
                type: 'normal',
                keys: [{
                        label: 'Esc'
                    },
                    {
                        label: 'F1'
                    }, {
                        label: 'F2'
                    }, {
                        label: 'F3'
                    }, {
                        label: 'F4'
                    }, {
                        label: 'F5'
                    }, {
                        label: 'F6'
                    }, {
                        label: 'F7'
                    }, {
                        label: 'F8'
                    },
                    {
                        label: 'F9'
                    }, {
                        label: 'F10'
                    }, {
                        label: 'F11'
                    }, {
                        label: 'F12'
                    },
                    {
                        label: 'PrtSc'
                    }, {
                        label: 'Pause'
                    }, {
                        label: 'Insert'
                    }, {
                        label: 'Delete'
                    },
                ]
            },
            {
                type: 'normal',
                keys: [{
                        label: '半角/全角',
                        widthClass: 'key-wide-1_5'
                    },
                    {
                        label: '1'
                    }, {
                        label: '2'
                    }, {
                        label: '3'
                    }, {
                        label: '4'
                    }, {
                        label: '5'
                    }, {
                        label: '6'
                    }, {
                        label: '7'
                    }, {
                        label: '8'
                    }, {
                        label: '9'
                    }, {
                        label: '0'
                    },
                    {
                        label: '-'
                    }, {
                        label: '^'
                    }, {
                        label: '¥'
                    },
                    {
                        label: 'Backspace',
                        widthClass: 'key-wide-2_5'
                    },
                ]
            },
            {
                type: 'normal',
                keys: [{
                        label: 'Tab',
                        widthClass: 'key-wide-2'
                    },
                    {
                        label: 'Q'
                    }, {
                        label: 'W'
                    }, {
                        label: 'E'
                    }, {
                        label: 'R'
                    }, {
                        label: 'T'
                    }, {
                        label: 'Y'
                    }, {
                        label: 'U'
                    }, {
                        label: 'I'
                    }, {
                        label: 'O'
                    }, {
                        label: 'P'
                    },
                    {
                        label: '@'
                    }, {
                        label: '['
                    },
                    {
                        label: 'Enter',
                        widthClass: 'key-wide-3'
                    },
                ]
            },
            {
                type: 'normal',
                keys: [{
                        label: 'CapsLock',
                        widthClass: 'key-wide-3'
                    },
                    {
                        label: 'A'
                    }, {
                        label: 'S'
                    }, {
                        label: 'D'
                    }, {
                        label: 'F'
                    }, {
                        label: 'G'
                    }, {
                        label: 'H'
                    }, {
                        label: 'J'
                    }, {
                        label: 'K'
                    }, {
                        label: 'L'
                    },
                    {
                        label: ';'
                    }, {
                        label: ':'
                    }, {
                        label: ']'
                    },
                ]
            },
            {
                type: 'normal',
                keys: [{
                        label: 'Shift',
                        widthClass: 'key-wide-4'
                    },
                    {
                        label: 'Z'
                    }, {
                        label: 'X'
                    }, {
                        label: 'C'
                    }, {
                        label: 'V'
                    }, {
                        label: 'B'
                    }, {
                        label: 'N'
                    }, {
                        label: 'M'
                    },
                    {
                        label: ','
                    }, {
                        label: '.'
                    }, {
                        label: '/'
                    }, {
                        label: '_'
                    },
                    {
                        label: 'Shift',
                        widthClass: 'key-wide-3'
                    }, {
                        label: 'PgUp'
                    },
                ]
            },
            {
                type: 'normal',
                keys: [{
                        label: 'Ctrl'
                    },
                    {
                        label: 'Fn'
                    }, {
                        label: 'Win'
                    }, {
                        label: 'Alt'
                    },
                    {
                        label: '無変換',
                        widthClass: 'key-wide-2'
                    },
                    {
                        label: 'Space',
                        widthClass: 'key-space'
                    },
                    {
                        label: '変換',
                        widthClass: 'key-wide-2'
                    },
                    {
                        label: 'カタカナ/ひらがな',
                        widthClass: 'key-wide-2'
                    },
                    {
                        label: 'Alt'
                    }, {
                        label: 'Menu'
                    }, {
                        label: 'Ctrl'
                    },
                    {
                        label: 'Home'
                    }, {
                        label: 'PgDn'
                    }, {
                        label: 'End'
                    },
                ]
            },
        ];

        function renderKeyboard() {
            if (!keyboardContainer) return;
            keyboardContainer.innerHTML = '';
            let codeCounter = 1;
            keyboardRows.forEach(row => {
                if (row.type !== 'normal') return;
                const rowDiv = document.createElement('div');
                rowDiv.className = 'keyboard-row';
                if (row.rowClass) rowDiv.classList.add(row.rowClass);
                row.keys.forEach(key => {
                    const btn = document.createElement('button');
                    btn.type = 'button';
                    btn.className = 'key-button';
                    if (key.widthClass) btn.classList.add(key.widthClass);
                    btn.textContent = key.label;
                    const code = codeCounter++;
                    btn.dataset.code = String(code);
                    btn.addEventListener('click', () => selectKeyboardKey(code, key.label));
                    rowDiv.appendChild(btn);
                });
                keyboardContainer.appendChild(rowDiv);
            });
            keyboardRendered = true;
        }

        function updateKeyboardSelectionLabel(labelText = 'キー未選択', code = null) {
            if (!keyboardSelectionLabel) return;
            keyboardSelectionLabel.textContent = labelText || 'キー未選択';
            if (keyboardTriggerBtn) {
                keyboardTriggerBtn.classList.toggle('active', !!code);
            }
        }

        function selectKeyboardKey(code, label) {
            current.location = String(code);
            updateKeyboardSelectionLabel(`${label} (No.${code})`, code);
            if (keyPickerModal) keyPickerModal.classList.add('hidden');
            const wrap = el('locationList');
            if (wrap) {
                wrap.querySelectorAll('.chip').forEach(c => c.classList.remove('active'));
            }
        }

        function openKeyPicker() {
            if (!keyboardRendered) renderKeyboard();
            if (keyPickerModal) keyPickerModal.classList.remove('hidden');
        }

        function closeKeyPicker() {
            if (keyPickerModal) keyPickerModal.classList.add('hidden');
        }

        // parts=キーボード時のモーダル表示は停止中
        function injectKeyboardPicker(wrap) {
            return;
        }

        if (closeKeyPickerBtn) closeKeyPickerBtn.addEventListener('click', closeKeyPicker);
        if (keyPickerModal) {
            keyPickerModal.addEventListener('click', (e) => {
                if (e.target === keyPickerModal) closeKeyPicker();
            });
        }

        function adjustChipDensity(container) {
            if (!container) return;
            container.classList.remove('dense', 'compact');
            const count = container.querySelectorAll('.chip').length;
            if (count >= 24) {
                container.classList.add('compact');
            } else if (count >= 12) {
                container.classList.add('dense');
            }
        }

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
            const text = await res.text();
            try {
                const parsed = JSON.parse(text);
                if (parsed && parsed.ok === false) {
                    console.error('[api:fail]', action, parsed);
                }
                return parsed;
            } catch (err) {
                const message = text ? text.slice(0, 200) : 'empty response';
                throw new Error(`APIレスポンスの解析に失敗しました: ${message}`);
            }
        }

        const current = {
            mode: null,
            papa: null,
            yankee: null,
            location: null,
            sierra: null
        };
        let endLockBySelection = false;
        const clearSelectionsBtn = el('btnClearSelections');
        clearSelectionsBtn?.addEventListener('click', async () => {
            resetManualSelections();
            await loadPartsList();
        });
        // 外部パネル（mod/biz.html）からの選択結果を受け取る
        window.addEventListener('message', async (e) => {
            try {
                const data = e.data || {};
                if (!data || !data.type) return;

                // ビス選択（biz.html）
                       // ビス選択（biz.html）
        if (data.type === 'biz-selected') {
            const value = data.value ?? data.position ?? data.code ?? '';
            const sval = String(value).trim();
            if (!sval) return;

            // 現在の選択値を保持
            current.location = sval;

            // ラベルに選択番号を反映
            const label = el('bizSelectionLabel');
            if (label) label.textContent = sval;

            const wrap = el('locationList');
            if (wrap) {
                // ★ポイント1: 既存のビス用チップは全部削除（「ビス選択」ボタン行は残す）
                wrap.querySelectorAll('.chip').forEach(c => c.remove());

                // ★ポイント2: 新しいチップを1個だけ作る
                const btn = document.createElement('button');
                btn.type = 'button';
                btn.className = 'chip block active';
                btn.textContent = sval;

           
               // wrap.appendChild(btn);
                adjustChipDensity(wrap);
            }
            if ((current.papa || '').trim() === '底ビス') {
                if (!current.yankee) {
                    await loadSymptoms(current.papa);
                } else {
                    await validateLocationForSymptom(current.papa, current.yankee);
                }
            }
        }


                // キー選択（keyboard.html）
                if (data.type === 'keyboard-selected') {
                    const value = data.value ?? data.code ?? '';
                    const sval = String(value).trim();
                    if (!sval) return;
                    current.location = sval;
                    // UI 更新: ラベルに選択内容を反映
                    const label = el('keyboardPickerLabel');
                    if (label) label.textContent = sval;
                    if ((current.papa || '').trim() === 'キーボード') {
                        if (!current.yankee) {
                            await loadSymptoms(current.papa);
                        } else {
                            await validateLocationForSymptom(current.papa, current.yankee);
                        }
                    }
                }
            } catch (err) {
                console.warn('message handler error', err);
            }
        });
        let currentCategory = document.body.dataset.initCarriro || '';

        const serialScanInput = el('serialScanInput');
        const qrScanInput = null;
        enforceHalfwidthInput(serialScanInput, () => {
            commitSerialInput();
        });

        const workflowNotice = (() => {
            const states = {
                serial: {
                    className: 'state-serial',
                    text: 'Serialを読んでください'
                },
                manual: {
                    className: 'state-manual',
                    text: '部品・位置・症状を選択してください'
                }
            };
            const wrap = el('workflowNotice');
            const textEl = el('workflowNoticeText');
            let currentState = 'serial';

            function set(stateKey) {
                if (!wrap || !textEl) return;
                Object.values(states).forEach(cfg => wrap.classList.remove(cfg.className));
                const state = states[stateKey] || states.serial;
                wrap.classList.add(state.className);
                textEl.textContent = state.text;
                currentState = stateKey;
            }
            return {
                set
            };
        })();

        function focusSerialField() {
            if (serialScanInput) {
                serialScanInput.focus();
                serialScanInput.select?.();
            }
        }

        async function resetForInputError() {
            await resetWorkflowForNextSerial();
            if (serialScanInput) serialScanInput.value = '';
            focusSerialField();
        }

        function updateSerialChip(value) {
            const chip = document.querySelector('.serialChip');
            if (chip) chip.textContent = value ? value : 'Serial未設定';
        }

        function updateManualUI() {
            const manualArea = el('manualArea');
            const actions = el('manualActionsPanel');
            const endBtn = el('btnEnd');
            const visible = Boolean(current.sierra);
            if (manualArea) manualArea.classList.toggle('hide', !visible);
            if (actions) actions.classList.toggle('hide', !visible);
            const registerBtn = el('btnRegister');
            const showRegister = visible && !!current.papa && !!current.yankee;
            if (registerBtn) registerBtn.classList.toggle('hide', !showRegister);
            const showEnd = visible && !current.papa;
            if (endBtn) endBtn.classList.toggle('hide', !showEnd);
            workflowNotice.set(visible ? 'manual' : 'serial');
        }

        async function persistSerialValue(sierra) {
            try {
                const r = await api('h_get_serial', {
                    sierra
                });
                if (!r.ok) {
                    setErr(r.error || 'シリアル保存エラー');
                    return false;
                }
            } catch (e) {
                setErr('シリアル保存エラー: ' + e.message);
                return false;
            }
            current.sierra = sierra;
            notifySerialUpdate(sierra);
            resetManualSelections();
            endLockBySelection = false;
            updateSerialChip(sierra);
            updateManualUI();
            if (serialScanInput) serialScanInput.value = sierra;
            await refreshLogs();
            return true;
        }

        function resetManualSelections() {
            current.mode = null;
            current.papa = null;
            current.yankee = null;
            current.location = null;
            const symptomList = el('symptomList');
            const locationList = el('locationList');
            const partsList = el('partsList');
            [symptomList, locationList, partsList].forEach(list => list && (list.innerHTML = ''));
            adjustChipDensity(partsList);
            adjustChipDensity(symptomList);
            adjustChipDensity(locationList);
            endLockBySelection = false;
            updateManualUI();
        }

        function hardResetSerialState(keepSerial = false) {
            if (!keepSerial) {
                current.sierra = null;
                if (serialScanInput) serialScanInput.value = '';
            } else if (serialScanInput && current.sierra) {
                serialScanInput.value = current.sierra;
            }
            resetManualSelections();
            if (keepSerial) {
                updateSerialChip(current.sierra || '');
            } else {
                updateSerialChip('');
                clearLogsUI('serial未入力');
            }
            updateManualUI();
            focusSerialField();
        }

        async function resetWorkflowForNextSerial(options = {}) {
            const {
                keepSerial = false
            } = options;
            try {
                const params = keepSerial ? {
                    keep_serial: '1'
                } : {};
                await api('reset_workflow_state', params);
            } catch (err) {
                console.warn(err);
            }
            hardResetSerialState(keepSerial);
            if (!keepSerial) {
                notifySerialCleared();
                clearLogsUI('serial未入力');
            }
        }

        async function commitSerialInput() {
            const raw = serialScanInput ? serialScanInput.value.trim() : '';
            if (!raw) {
                setErr('serialを入力してください');
                return;
            }
            if (!isAlnum(raw)) {
                setErr('serialは英数字のみです');
                await resetForInputError();
                return;
            }
            const ok = await persistSerialValue(raw);
            if (!ok) return;
            await loadPartsList();
        }

        if (serialScanInput) {
            serialScanInput.addEventListener('keydown', (ev) => {
                if (ev.key !== 'Enter') return;
                ev.preventDefault();
                commitSerialInput();
            });
        }

        const btnSerialReset = document.getElementById('btnSerialReset');
        if (btnSerialReset) {
            btnSerialReset.addEventListener('click', async () => {
                await resetWorkflowForNextSerial();
                setMsg('シリアルをリセットしました');
            });
        }

        // 外部ボタン（例: F9）でシリアルをリセット
        window.addEventListener('keydown', async (e) => {
            if (e.code !== 'F9') return;
            e.preventDefault();
            await resetWorkflowForNextSerial();
            setMsg('シリアルをリセットしました（外部ボタン/F9）');
        });

        async function setCarriro(carriro) {
            if (!carriro) return;
            try {
                const r = await api('set_category', {
                    carriro
                });
                if (!r.ok) {
                    setErr(r.message || r.error || 'カテゴリ設定エラー');
                    return;
                }
                currentCategory = carriro;
                resetManualSelections();
                updateManualUI();
                await loadPartsList();
                updateCategoryCompletionUI(); // カテゴリ切り替え時に完了色反映
                notifyCategoryChanged(carriro);
            } catch (err) {
                setErr('カテゴリ設定エラー: ' + err.message);
            }
        }

        async function loadPartsList() {
            try {
                const r = await api('get_partslist');
                const list = r.result?.partslist || r.partslist || [];
                const container = el('partsList');
                if (!container) return;
                container.innerHTML = '';
                adjustChipDensity(container);
                if (!list.length) {
                    container.textContent = '部品マスタがありません';
                    return;
                }
                list.forEach(part => {
                    const btn = document.createElement('button');
                    btn.type = 'button';
                    btn.className = 'chip block' + (current.papa === part ? ' active' : '');
                    btn.textContent = part;
                    btn.addEventListener('click', () => selectPart(part, btn));
                    container.appendChild(btn);
                });
                adjustChipDensity(container);
            } catch (err) {
                setErr('部品取得エラー: ' + err.message);
            }
        }

        async function renderPositionsByPart(part) {
            const wrap = el('locationList');
            if (!wrap) return;
            wrap.innerHTML = '';
            adjustChipDensity(wrap);
            try {
                const r = await api('get_positionlist_by_part', { papa: part });
                if (!r.ok) {
                    setErr(r.error || '位置取得エラー');
                    return;
                }
                const list = uniqueOptions(r.result?.positionlist || r.positionlist || []);
                if (!list.length) {
                    const note = document.createElement('div');
                    note.className = 'selectionInfo';
                    note.textContent = '位置指定は不要です';
                    wrap.appendChild(note);
                    current.location = '-';
                    updateKeyboardSelectionLabel('キー未選択');
                    await loadSymptoms(part);
                    return;
                }
                list.forEach(pos => {
                    const btn = document.createElement('button');
                    btn.type = 'button';
                    btn.className = 'chip block';
                    btn.textContent = pos;
                    btn.addEventListener('click', () => selectLocation(pos, btn));
                    wrap.appendChild(btn);
                });
                adjustChipDensity(wrap);
            } catch (err) {
                setErr('位置取得エラー: ' + err.message);
            }
        }

        function renderSpecialLocationPicker(part) {
            const wrap = el('locationList');
            if (!wrap) return;
            wrap.innerHTML = '';
            adjustChipDensity(wrap);
            const trimmedPart = String(part || '').trim();
            const row = document.createElement('div');
            row.className = 'keyboard-picker-row';
            const btn = document.createElement('button');
            btn.type = 'button';
            btn.className = 'chip block keyboard-picker-btn';
            const label = document.createElement('span');
            label.className = 'selectionInfo';
            label.textContent = '未選択';
            if (trimmedPart === '底ビス') {
                btn.id = 'openBizButton';
                btn.textContent = 'ビス選択';
                label.id = 'bizSelectionLabel';
                btn.addEventListener('click', () => {
                    try {
                        const win = window.open('../mod/biz.html', 'bizpicker', 'width=700,height=780');
                        if (!win) setErr('ポップアップをブロックしないでください');
                    } catch (e) {
                        console.error(e);
                        setErr('ビス選択ポップアップを開けませんでした');
                    }
                });
            } else if (trimmedPart === 'キーボード') {
                btn.id = 'openKeyboardPickerButton';
                btn.textContent = 'Key選択';
                label.id = 'keyboardPickerLabel';
                btn.addEventListener('click', () => {
                    try {
                        const win = window.open('../mod/keyboard.html', 'kbpicker', 'width=1100,height=900');
                        if (!win) setErr('ポップアップをブロックしないでください');
                    } catch (e) {
                        console.error(e);
                        setErr('キー選択ポップアップを開けませんでした');
                    }
                });
            }
            row.appendChild(btn);
            row.appendChild(label);
            wrap.appendChild(row);
            adjustChipDensity(wrap);
        }

        async function validateLocationForSymptom(part, symptom) {
            const trimmedPart = String(part || '').trim();
            if (!current.location || current.location === '-') {
                setErr('位置を選択してください');
                return false;
            }
            try {
                const r = await api('get_positionlist', { papa: part, yankee: symptom });
                if (!r.ok) {
                    setErr(r.error || '位置取得エラー');
                    return false;
                }
                const list = uniqueOptions(r.result?.positionlist || r.positionlist || []);
                const labelId = trimmedPart === '底ビス' ? 'bizSelectionLabel' : 'keyboardPickerLabel';
                const label = el(labelId);
                if (!list.length) {
                    current.location = '-';
                    if (label) label.textContent = '未選択';
                    return true;
                }
                if (!list.includes(current.location)) {
                    setErr('位置が候補にありません。再選択してください');
                    current.location = null;
                    if (label) label.textContent = '未選択';
                    return false;
                }
                return true;
            } catch (err) {
                setErr('位置取得エラー: ' + err.message);
                return false;
            }
        }

        async function loadSymptoms(part) {
            try {
                const r = await api('get_symptomlist', {
                    papa: part
                });
                if (!r.ok) {
                    setErr(r.error || '症状取得エラー');
                    return;
                }
                const list = uniqueOptions(r.result?.symptomlist || r.symptomlist || []);
                const container = el('symptomList');
                if (!container) return;
                container.innerHTML = '';
                if (!list.length) {
                    container.textContent = '症状候補がありません';
                    return;
                }
                list.forEach(symptom => {
                    const btn = document.createElement('button');
                    btn.type = 'button';
                    btn.className = 'chip block';
                    btn.textContent = symptom;
                    btn.addEventListener('click', () => selectSymptom(symptom, btn));
                    container.appendChild(btn);
                });
                adjustChipDensity(container);
            } catch (err) {
                setErr('症状取得エラー: ' + err.message);
            }
        }

        async function renderPositions(part, symptom, keepValue = null) {
            const wrap = el('locationList');
            if (!wrap) return;
            wrap.innerHTML = '';
            adjustChipDensity(wrap);
            const preferred = keepValue ?? current.location ?? null;
            const isKeyboard = (current.papa || '').trim() === 'キーボード';
            if (!isKeyboard && wrap.querySelector('.keyboard-picker-row')) {
                wrap.querySelector('.keyboard-picker-row').remove();
            }
            try {
                const r = await api('get_positionlist', {
                    papa: part,
                    yankee: symptom
                });
                if (!r.ok) {
                    setErr(r.error || '位置取得エラー');
                    return;
                }
                const list = uniqueOptions(r.result?.positionlist || r.positionlist || []);
                // 特別処理: 部品が「底ビス」かつ症状が「欠損」のときは
                // DBの position ボタンは表示せず、代わりに外部パネル（biz.html）を開く
                const trimmedPart = String(part).trim();
                const trimmedSymptom = String(symptom).trim();
                const isBizSelectorCase = (trimmedPart === '底ビス' && trimmedSymptom === '欠損');
                // 特別処理: cate=C面 かつ parts=キーボード かつ symptom=印字剥がれ のときは
                // keyboard.html をポップアップで開く（マスタの position は非表示）
                const currentCate = currentCategory ? String(currentCategory).trim() : '';
                const keyboardPickerSymptoms = ['印字剥がれ', 'キー外れ'];
                const isKeyboardPickerCase = (
                    currentCate === 'C面' &&
                    trimmedPart === 'キーボード' &&
                    keyboardPickerSymptoms.includes(trimmedSymptom)
                );
                if (isBizSelectorCase) {
                    current.location = null;
                    updateKeyboardSelectionLabel('キー未選択');
                    // 表示: ビス選択ボタン + 選択ラベル
                    const row = document.createElement('div');
                    row.className = 'keyboard-picker-row';
                    const btn = document.createElement('button');
                    btn.type = 'button';
                    btn.className = 'chip block keyboard-picker-btn';
                    btn.id = 'openBizButton';
                    btn.textContent = 'ビス選択';
                    const label = document.createElement('span');
                    label.className = 'selectionInfo';
                    label.id = 'bizSelectionLabel';
                    label.textContent = '未選択';
                    row.appendChild(btn);
                    row.appendChild(label);
                    wrap.appendChild(row);

                    // 開く処理
                    let bizWindow = null;
                    btn.addEventListener('click', () => {
                        try {
                            // mod/biz.html は app から見て ../mod/biz.html
                            bizWindow = window.open('../mod/biz.html', 'bizpicker', 'width=700,height=780');
                            if (!bizWindow) {
                                setErr('ポップアップをブロックしないでください');
                            }
                        } catch (e) {
                            console.error(e);
                            setErr('ビス選択ポップアップを開けませんでした');
                        }
                    });

                    // 既に選択値があれば表示
                    if (current.location) {
                        label.textContent = String(current.location);
                    }

                    // 追加密度調整
                    adjustChipDensity(wrap);
                    return;
                }
                if (isKeyboardPickerCase) {
                    current.location = null;
                    updateKeyboardSelectionLabel('キー未選択');
                    // 表示: Key選択ボタン + 選択ラベル
                    const row = document.createElement('div');
                    row.className = 'keyboard-picker-row';
                    const btn = document.createElement('button');
                    btn.type = 'button';
                    btn.className = 'chip block keyboard-picker-btn';
                    btn.id = 'openKeyboardPickerButton';
                    btn.textContent = 'Key選択';
                    const label = document.createElement('span');
                    label.className = 'selectionInfo';
                    label.id = 'keyboardPickerLabel';
                    label.textContent = '未選択';
                    row.appendChild(btn);
                    row.appendChild(label);
                    wrap.appendChild(row);

                    // 開く処理
                    let kbWindow = null;
                    btn.addEventListener('click', () => {
                        try {
                            // mod/keyboard.html は app から見て ../mod/keyboard.html
                            kbWindow = window.open('../mod/keyboard.html', 'kbpicker', 'width=1100,height=900');
                            if (!kbWindow) {
                                setErr('ポップアップをブロックしないでください');
                            }
                        } catch (e) {
                            console.error(e);
                            setErr('キー選択ポップアップを開けませんでした');
                        }
                    });

                    // 既に選択値があれば表示
                    if (current.location) {
                        label.textContent = String(current.location);
                    }

                    // 追加密度調整
                    adjustChipDensity(wrap);
                    return;
                }
                if (!list.length) {
                    const note = document.createElement('div');
                    note.className = 'selectionInfo';
                    note.textContent = '位置指定は不要です';
                    wrap.appendChild(note);
                    current.location = '-';
                    updateKeyboardSelectionLabel('キー未選択');
                    if (isKeyboard) injectKeyboardPicker(wrap);
                    return;
                }
                if (isKeyboard) injectKeyboardPicker(wrap);
                let matched = false;
                list.forEach(pos => {
                    const btn = document.createElement('button');
                    btn.type = 'button';
                    btn.className = 'chip block';
                    btn.textContent = pos;
                    btn.addEventListener('click', () => {
                        wrap.querySelectorAll('.chip').forEach(c => c.classList.remove('active'));
                        btn.classList.add('active');
                        current.location = pos;
                        updateKeyboardSelectionLabel(null, pos);
                    });
                    if (preferred && pos === preferred) {
                        btn.classList.add('active');
                        current.location = pos;
                        matched = true;
                        updateKeyboardSelectionLabel(null, pos);
                    }
                    wrap.appendChild(btn);
                });
                if (!matched && preferred && preferred !== '-') {
                    current.location = null;
                    updateKeyboardSelectionLabel('キー未選択');
                }
                adjustChipDensity(wrap);
            } catch (err) {
                setErr('位置取得エラー: ' + err.message);
            }
        }

        async function selectPart(part, btn) {
            el('partsList')?.querySelectorAll('.chip').forEach(c => c.classList.remove('active'));
            btn.classList.add('active');
            current.papa = part;
            current.yankee = null;
            current.location = null;
            el('symptomList').innerHTML = '';
            el('locationList').innerHTML = '';
            updateKeyboardSelectionLabel('キー未選択');
            endLockBySelection = true;
            updateManualUI();
            const trimmedPart = String(part || '').trim();
            if (trimmedPart === '底ビス' || trimmedPart === 'キーボード') {
                renderSpecialLocationPicker(trimmedPart);
                return;
            }
            await renderPositionsByPart(part);
        }

        async function selectLocation(pos, btn) {
            const wrap = el('locationList');
            wrap?.querySelectorAll('.chip').forEach(c => c.classList.remove('active'));
            btn.classList.add('active');
            current.location = pos;
            current.yankee = null;
            el('symptomList').innerHTML = '';
            updateManualUI();
            await loadSymptoms(current.papa);
        }

        async function selectSymptom(symptom, btn) {
            el('symptomList')?.querySelectorAll('.chip').forEach(c => c.classList.remove('active'));
            btn.classList.add('active');
            current.yankee = symptom;
            const trimmedPart = String(current.papa || '').trim();
            if (trimmedPart === '底ビス' || trimmedPart === 'キーボード') {
                await validateLocationForSymptom(current.papa, symptom);
                updateManualUI();
                return;
            }
            await renderPositions(current.papa, symptom, current.location);
            updateManualUI();
        }

        async function handleRegister() {
            setMsg('');
            setErr('');
            if (!current.sierra) {
                setErr('serialを先に読み込んでください');
                focusSerialField();
                return;
            }
            if (!current.papa || !current.yankee) {
                setErr('手動選択が未完了');
                return;
            }
            try {
                const duplicateLocation = current.location || '-';
                if (hasDuplicateLog(current.papa, current.yankee || '-', duplicateLocation)) {
                    setErr('同じ不良内容が既に登録されています');
                    return;
                }
                const r = await api('register_manual', {
                    papa: current.papa,
                    yankee: current.yankee || '-',
                    location: current.location || '-'
                });
                if (!r.ok) {
                    setErr(r.message || r.result?.warn || r.result?.error || r.error || '保存に失敗しました');
                    return;
                }
                const partLabel = current.papa || '-';
                const symptomLabel = current.yankee || '-';
                const serverMsg = r.result?.message || r.message || `${partLabel} / ${symptomLabel} を登録しました`;
                setMsg(serverMsg);
                await refreshLogs();
                await resetWorkflowForNextSerial({
                    keepSerial: true
                });
                await loadPartsList();
                updateKeyboardSelectionLabel('キー未選択');
                endLockBySelection = false;
                updateManualUI();
            } catch (e) {
                setErr('登録エラー: ' + e.message);
            }
        }

        async function handleEnd() {
            if (!current.sierra) {
                setErr('serial未入力です');
                focusSerialField();
                return;
            }
            const cat = currentCategory || 'UNKNOWN';
            try {
                const r = await api('save_end', {
                    carriro: cat,
                    sierra: current.sierra
                });
                if (!r.ok) {
                    setErr(r.error || '終了処理に失敗しました');
                    return;
                }
                setMsg(r.result?.message || '終了を記録しました');
                notifyCateEndUpdate(current.sierra || '');
                // 終了ボタンではシリアルをクリアしない（クリアはSKU切替・クリアボタン・全非DIAG完了時のみ）
                await resetWorkflowForNextSerial({
                    keepSerial: true
                });
                await navigateNextCategoryIfAny(current.sierra || '', currentCategory);
                // 親(home)へカテゴリ選択を伝える
                notifyCategoryChanged(currentCategory);
            } catch (err) {
                setErr('終了エラー: ' + err.message);
            }
        }

        async function refreshLogs() {
            if (!current.sierra) {
                cachedLogs = [];
                clearLogsUI('serial未入力');
                return;
            }
            try {
                const r = await api('get_total_logs');
                if (!r.ok) {
                    cachedLogs = [];
                    setErr(r.message || r.error || '一覧取得に失敗しました');
                    return;
                }
                const ul = el('logs');
                ul.innerHTML = '';
                const rawRows = r.result?.showlogs || r.showlogs || [];
                const rows = Array.isArray(rawRows) ? rawRows : [];
                cachedLogs = rows.slice();
                if (rows.length === 0) {
                    const empty = document.createElement('li');
                    empty.className = 'logsEmpty';
                    empty.textContent = 'まだ登録がありません';
                    ul.appendChild(empty);
                    return;
                }
                rows.forEach((row, index) => {
                    const li = document.createElement('li');
                    if (index === 0) li.classList.add('latest');
                    const values = [row.parts ?? '-', row.symptom ?? '-', row.position ?? '-'];
                    const text = document.createElement('span');
                    text.className = 'logText';
                    text.textContent = values.join(' / ');
                    li.appendChild(text);
                    const idVal = row.ID ?? row.id ?? null;
                    if (idVal !== null) {
                        const delBtn = document.createElement('button');
                        delBtn.className = 'logDeleteBtn';
                        delBtn.textContent = '削除';
                        delBtn.addEventListener('click', async () => {
                            const ok = confirm(`ID #${idVal} を削除しますか？`);
                            if (!ok) return;
                            await deleteLog(idVal);
                        });
                        li.appendChild(delBtn);
                    }
                    ul.appendChild(li);
                });
            } catch (e) {
                cachedLogs = [];
                setErr('一覧取得エラー: ' + e.message);
                console.error('refreshLogs error', e);
            }
        }

        async function navigateNextCategoryIfAny(serial, currentCate) {
            if (!serial) return;
            try {
                const r = await api('next_missing_category', {
                    sierra: serial,
                    monbell: initialMonbell || '',
                    categories: JSON.stringify([]) // use server-side defaults
                });
                const next = r.result?.next ?? null;
                if (next && next !== currentCate) {
                    await setCarriro(next);
                    notifyCategoryChanged(next);
                }
            } catch (e) {
                console.warn('navigateNextCategoryIfAny failed', e);
            }
        }

        async function deleteLog(identity) {
            try {
                const r = await api('delete_one_log', { identity });
                if (!r.ok) {
                    setErr(r.error || '削除に失敗しました');
                    return;
                }
                await refreshLogs();
            } catch (e) {
                setErr('削除エラー: ' + e.message);
            }
        }

        // 自動カテゴリ遷移・親連動機能は無効化

        async function boot() {
            if (initialMonbell) {
                try {
                    await api('set_model', {
                        monbell: initialMonbell
                    });
                } catch (e) {
                    console.warn(e);
                }
            }
            if (initialCarriro) {
                await setCarriro(initialCarriro);
            } else {
                await loadPartsList();
            }
            // 初期serial値があれば復元
            if (initialSerial) {
                current.sierra = initialSerial;
                updateSerialChip(initialSerial);
                if (serialScanInput) serialScanInput.value = initialSerial;
                // 親フレームに初期シリアルがあることを通知
                try {
                    notifySerialUpdate(initialSerial);
                } catch (e) {
                    console.warn('notifySerialUpdate failed', e);
                }
            }
            updateManualUI();
            await refreshLogs();
            focusSerialField();
        }

        const initialCarriro = document.body.dataset.initCarriro || '';
        const initialMonbell = document.body.dataset.initMonbell || '';

        el('btnRegister').addEventListener('click', handleRegister);
        el('btnEnd').addEventListener('click', handleEnd);

        boot();
    </script>
</body>

</html>

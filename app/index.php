<?php
require_once __DIR__ . '/../config/config.php';
$initialCarriro = trim((string)(
    $_POST['carriro'] ?? $_GET['carriro'] ??
    $_POST['category'] ?? $_GET['category'] ??
    ''
));
// これはoutframe.php側でも同様に取得しているので、両方を一致させること。
$initialMonbell = trim((string)(
    $_POST['monbell'] ?? $_GET['monbell'] ??
    $_POST['model'] ?? $_GET['model'] ??
    ''
));
$qrDelimiter = defined('QR_DELIM') ? constant('QR_DELIM') : '_';
?>
<!doctype html>
<html lang="ja">

<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>カテゴリ：機能検査</title>
    <style>
        :root {
            /* ルート変数: フォントサイズ・色などのテーマ変数 */
            --fs: 20px;
            /* 基本フォントサイズ */
            --h1: 24px;
            /* 見出しサイズ */
            --gap: 14px;
            /* レイアウト間隔 */
            --pad: 12px;
            /* コンテナ内パディング */
            --radius: 12px;
            /* 角丸共通 */
            --radius-sm: 8px;
            --radius-lg: 16px;
            --radius-xl: 20px;
            --font-scale: 1.3;
            --bg: #ffffffff;
            /* ページ背景色 */
            --card: #fff;
            /* カード背景色（パネル） */
            --txt: #111827;
            /* 標準テキスト色 */
            --muted: #6b7280;
            /* 補助テキスト色 */
            --accent: #0e7afe;
            /* アクション系ボタン色 */
            --good: #059669;
            /* 成功色（OK） */
            --danger: #b91c1c;
            /* エラー色 */
            --bd: #e5e7eb;
            /* 境界線色 */
            --shadow: 0 6px 18px rgba(0, 0, 0, .06);
            /* カード影 */
            --shadow-lg: 0 10px 30px rgba(0, 0, 0, .1);
            --touch: 48px;
            /* タッチ時の最小高さ */
            /* タッチ最適サイズ */
        }

        html {
            font-size: calc(16px * var(--font-scale));
        }

        * {
            /* 全要素: ボックスサイズの基準を border-box に */
            box-sizing: border-box
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
            gap: 0px;
            min-height: 0;
            align-items: stretch;
            /* 左カラム／右カラムの横幅は上記 grid-template-columns で決定。調整する場合はここを変更 */
        }

        .logsColumn {
            display: block;

            width: 100%;
        }

        .manualStack {
            display: flex;
            flex-direction: column;
            gap: 12px;
            height: 100%;
        }

        .manualPanel {
            flex: 1 1 auto;
            /* 左カラムのメイン領域：高さ可変、横幅は .wrap のグリッド幅に従う */
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

        .logsColumn {
            display: flex;
            flex-direction: column;
            gap: 12px;
            height: 100%;
        }

        .logsPanel {
            flex: 1 1 auto;
        }

        .title {
            /* 各ブロックの小見出し */
            font-weight: 800;
            /* 太字 */
            color: var(--muted);
            /* 補助色 */
        }

        .row {
            /* 横並び行ブロック（フォーム等） */
            display: flex;
            /* 横並び */
            gap: var(--gap);
            /* 要素間 */
            align-items: center;
            /* 垂直中央 */
            flex-wrap: wrap;
            /* 小さい画面で折り返し */
        }

        input[type="text"] {
            min-height: 48px;
            padding: 10px 14px;
            border: 2px solid #cbd5e1;
            border-radius: var(--radius-sm);
            font-size: 1em;
            background: #fff;
            min-width: 260px;
            transition: border-color 0.2s, box-shadow 0.2s;
        }

        input[type="text"]:focus {
            outline: none;
            border-color: var(--accent);
            box-shadow: 0 0 0 3px rgba(14, 122, 254, 0.15);
        }

        .btn {
            min-height: 48px;
            padding: 12px 20px;
            border-radius: var(--radius-sm);
            border: 2px solid #e2e8f0;
            background: #fff;
            color: #0f172a;
            font-weight: 700;
            cursor: pointer;
            font-size: 1.5em;
            transition: all 0.2s ease;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.05);
        }

        .btn:hover {
            transform: translateY(-1px);
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
        }

        .btn:active {
            transform: translateY(0);
        }

        .btn.tiny {
            min-height: 40px;
            padding: 6px 14px;
            font-size: .85em;
            border-radius: var(--radius-sm);
        }
        /* クリアボタン専用（ghost + tiny）: 背景は白、文字は黒 */
        .btn.ghost.tiny {
            background: #fff;
            color: #0f172a;
            border: 2px solid #e2e8f0;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.05);
        }
        .btn.ghost {
            background: linear-gradient(135deg, #22c55e 0%, #16a34a 100%);
            min-height: 60px;
            padding: 8px 12px;
            font-size: .9em;
            border: none;
            color: #fff;
            border-radius: var(--radius-sm);
            box-shadow: 0 4px 12px rgba(22, 163, 74, 0.25);
        }

        .btn.ghost:hover {
            box-shadow: 0 6px 16px rgba(22, 163, 74, 0.35);
        }
        /* 終了ボタンだけ縦長・大きな文字で表示 */
        #btnEnd {
            min-height: 150px;
            padding: 16px 12px;
            font-size: 1.9em;
            letter-spacing: 0.08em;
            border: none;
            background: linear-gradient(135deg, #22c55e 0%, #16a34a 100%);
            color: #fff;
            border-radius: var(--radius-lg);
            box-shadow: 0 8px 20px rgba(22, 163, 74, 0.35);
            transition: all 0.25s ease;
        }

        #btnEnd:hover {
            transform: translateY(-2px);
            box-shadow: 0 12px 28px rgba(22, 163, 74, 0.45);
        }

        .btn.good {
            background: linear-gradient(135deg, #1f2937 0%, #111827 100%);
            border: none;
            color: #fff;
            box-shadow: 0 4px 12px rgba(17, 24, 39, 0.25);
        }

        .btn.good:hover {
            box-shadow: 0 6px 16px rgba(17, 24, 39, 0.35);
        }

        .btn.danger {
            background: linear-gradient(135deg, #f97316 0%, #ea580c 100%);
            border: none;
            color: #fff;
            box-shadow: 0 4px 12px rgba(234, 88, 12, 0.3);
        }

        .btn.danger:hover {
            box-shadow: 0 6px 16px rgba(234, 88, 12, 0.4);
        }

        .serialChip {
            padding: 12px 20px;
            border-radius: var(--radius-lg);
            background: linear-gradient(135deg, #3b82f6 0%, #1d4ed8 100%);
            color: #fff;
            font-weight: 700;
            min-width: 240px;
            text-align: center;
            margin-bottom: 12px;
            letter-spacing: 0.04em;
            box-shadow: 0 4px 12px rgba(29, 78, 216, 0.3);
        }

        .serialHint {
            font-size: .9em;
            color: #475569;
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
            border: 2px solid #cbd5e1;
            border-radius: var(--radius-sm);
            background: #f8fafc;
            font-size: 1.4em;
            transition: border-color 0.2s, box-shadow 0.2s;
        }

        .scanField input:focus {
            outline: none;
            border-color: var(--accent);
            box-shadow: 0 0 0 3px rgba(14, 122, 254, 0.15);
            background: #fff;
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
            border-radius: var(--radius-lg);
            padding: 14px 16px;
            background: linear-gradient(135deg, #0ea5e9 0%, #0284c7 100%);
            color: #fff;
            font-size: 1.7em;
            font-weight: 600;
            text-align: center;
            letter-spacing: 0.02em;
            box-shadow: 0 10px 24px rgba(2, 132, 199, 0.25);
        }

        .heroNotice.state-qr {
            background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%);
            box-shadow: 0 10px 24px rgba(220, 38, 38, 0.25);
        }

        .heroNotice.state-manual {
            background: linear-gradient(135deg, #f97316 0%, #ea580c 100%);
            box-shadow: 0 10px 24px rgba(234, 88, 12, 0.25);
        }

        .saveToast {
            position: fixed;
            top: 12px;
            left: 50%;
            transform: translateX(-50%);
            background: linear-gradient(135deg, #f97316 0%, #ea580c 100%);
            color: #fff;
            padding: 10px 20px;
            border-radius: var(--radius);
            font-weight: 700;
            font-size: .9em;
            box-shadow: 0 8px 24px rgba(234, 88, 12, 0.3);
            z-index: 50;
        }

        .manualGrid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 12px;
        }

        .manualGrid .box {
            min-height: 420px;
            height: 420px;
            font-size: 1.3em;
        }

        .chipList {
            display: grid;
            /* ボタンは常に2列固定 */
            grid-template-columns: repeat(1, minmax(0, 1fr));
            gap: 8px;
            /* ボタン間の余白 */
            align-content: flex;
            /* 中央寄せで配置 */
            align-content: center;
            flex: 1 1 auto;
            /* 親内で伸縮 */
            overflow-y: auto;
            /* 縦方向スクロール */
            padding: 8px 12px;
        }

        .chipList.dense {
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 6px;
        }

        .chipList.compact {
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 4px;
        }

        .chip.block {
            width: 100%;
            display: flex;
            align-items: center;
            justify-content: flex-start;
            text-align: left;
            border: none;
            border-radius: var(--radius-sm);
            padding: 10px 14px;
            background: linear-gradient(135deg, #374151 0%, #1f2937 100%);
            color: #fff;
            font-weight: 580;
            font-size: 1em;
            cursor: pointer;
            transition: all 0.2s ease;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
        }

        .chip.block:hover {
            transform: translateY(-1px);
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.15);
        }

        .chip.block.active {
            background: linear-gradient(135deg, #0ea5e9 0%, #0284c7 100%);
            box-shadow: 0 4px 12px rgba(14, 165, 233, 0.35);
        }

        .selectionInfo {
            margin-top: 8px;
            font-size: .9em;
            color: #475569;
        }

        .selectionInfo strong {
            color: #111827;
        }

        .box {
            border: 2px solid #e2e8f0;
            border-radius: var(--radius);
            padding: 14px;
            min-height: 120px;
            display: flex;
            flex-direction: column;
            gap: 10px;
            background: #fff;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.04);
        }

        .btns {
            /* チップやボタン群のコンテナ */
            display: flex;
            /* 横並び */
            gap: 10px;
            /* 間隔 */
            flex-wrap: wrap;
            /* 折り返し */
        }

        .chip {
            min-height: 38px;
            display: flex;
            align-items: center;
            justify-content: flex-start;
            border: 2px solid #e2e8f0;
            border-radius: var(--radius-sm);
            padding: 8px 12px;
            background: #fff;
            cursor: pointer;
            font-size: clamp(.82rem, .8vw, 1rem);
            font-weight: 700;
            word-break: break-word;
            line-height: 1.3;
            transition: all 0.2s ease;
        }

        .chip:hover {
            border-color: #94a3b8;
            box-shadow: 0 2px 6px rgba(0, 0, 0, 0.08);
        }

        .chipList.dense .chip {
            padding: 6px 8px;
        }

        .chipList.compact .chip {
            padding: 4px 6px;
            font-size: clamp(.7rem, .7vw, .9rem);
        }

        .chip.active {
            background: linear-gradient(135deg, #1f2937 0%, #111827 100%);
            color: #fff;
            border-color: #111827;
        }

        .chipClear {
            margin-left: 8px;
            padding: 5px 10px;
            border: 2px solid #dc2626;
            border-radius: var(--radius-sm);
            background: linear-gradient(135deg, #fee2e2 0%, #fecaca 100%);
            color: #dc2626;
            cursor: pointer;
            font-weight: 800;
            font-size: 0.85em;
            transition: all 0.2s ease;
            box-shadow: 0 2px 6px rgba(220, 38, 38, 0.15);
        }

        .chipClear:hover {
            background: linear-gradient(135deg, #fecaca 0%, #fca5a5 100%);
            border-color: #b91c1c;
            transform: translateY(-1px);
            box-shadow: 0 4px 10px rgba(220, 38, 38, 0.25);
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
            border-bottom: 1px solid #fecaca;
            padding: 8px 10px 8px 40px;
            font-weight: 400;
            color: #0f172a;
            position: relative;
            border-radius: var(--radius-sm);
            display: flex;
            align-items: center;
            gap: 8px;
            background: #fff;
            margin-bottom: 4px;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.05);
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
            border: none;
            background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%);
            color: #fff;
            padding: 4px 10px;
            border-radius: var(--radius-sm);
            cursor: pointer;
            font-weight: 700;
            font-size: 0.75em;
            white-space: nowrap;
            flex-shrink: 0;
            min-width: 40px;
            text-align: center;
            transition: all 0.2s ease;
            box-shadow: 0 2px 4px rgba(220, 38, 38, 0.25);
        }

        .logDeleteBtn:hover {
            box-shadow: 0 4px 8px rgba(220, 38, 38, 0.35);
        }

        @media (max-width: 1024px) {
            .wrap {
                grid-template-columns: 1fr;
            }
        }

        .spacer {
            /* フレックスで右寄せにするための空き */
            flex: 1 1 auto;
        }

        .logsPanel {

            padding-left: 0;
            min-height: 620px;
            height: 620px;
            gap: 0px;
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
            border: 2px dashed #e2e8f0;
            border-radius: var(--radius);
            background: rgba(255, 255, 255, 0.6);
        }

        .ok {
            /* 成功ラベル色 */
            color: var(--good);
        }

        .msg {
            /* 補助メッセージ色 */
            color: #111827;
            font-size: 1.5em;
        }

        .err {
            /* エラーメッセージ色 */
            color: #ff0000ff;
            font-size: 1.5em;
            font-weight: 700;
        }

        .msgArea {
            display: flex;
            gap: 12px;
            color: var(--muted);
            min-height: 18px;
        }

        .msgArea .err {
            color: var(--danger);
            font-weight: 700;
        }

        .rightButtons {
            display: flex;
            flex-direction: row;
            gap: 12px;
            justify-content: flex-end;
        }

        .actionBtn {
            min-height: 120px; /* 2倍以上に拡大 */
            font-size: 1.15em;
            flex: 1 1 0;
            border-radius: var(--radius-lg);
            border: none;
            background: linear-gradient(135deg, #f97316 0%, #ea580c 100%);
            color: #fff;
            font-weight: 800;
            box-shadow: 0 8px 20px rgba(234, 88, 12, 0.35);
            transition: all 0.25s ease;
        }

        .actionBtn:hover {
            transform: translateY(-2px);
            box-shadow: 0 12px 28px rgba(234, 88, 12, 0.45);
        }

        /* 登録ボタン用の色を優先適用（.btn.good の黒を上書き） */
        .btn.good.actionBtn {
            background: linear-gradient(135deg, #f97316 0%, #ea580c 100%);
            border: none;
            color: #fff;
            box-shadow: 0 8px 20px rgba(234, 88, 12, 0.35);
        }

        @media (max-width: 1024px) {
            .rightButtons {
                flex-direction: column;
            }
        }

        .hide {
            /* 非表示ユーティリティ */
            display: none !important;
        }
    </style>
</head>

<body data-init-carriro="<?= htmlspecialchars($initialCarriro, ENT_QUOTES, 'UTF-8') ?>"
    data-init-monbell="<?= htmlspecialchars($initialMonbell, ENT_QUOTES, 'UTF-8') ?>"
    data-qr-delim="<?= htmlspecialchars($qrDelimiter, ENT_QUOTES, 'UTF-8') ?>">
    <div class="page">
        <span id="cat-pill" class="sr-only">未選択</span>

        <!-- カテゴリ未選択時のプレースホルダー -->
        <div id="noCategoryPlaceholder" class="noCategoryPlaceholder" style="display: none;">
            <div class="noCategoryMessage">
                <svg width="48" height="48" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/></svg>
                <p>カテゴリを選択してください</p>
            </div>
        </div>

        <div class="wrap" id="mainContentWrap">
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
                            <div class="scanField">

                                <input type="text" id="qrScanInput" placeholder="不良QR" autocomplete="off" />
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
                        
                        <button class="btn ghost actionBtn" id="btnEnd">終了</button>
                    </div>
                </section>
            </div>
        </div>
    </div>

    <script>
        const el = id => document.getElementById(id);
        const MONBELL_STORAGE_KEY = 'home_last_monbell';
        const isEmbeddedFrame = (() => {
            try {
                return window.self !== window.top;
            } catch (err) {
                return true;
            }
        })();
        const readStoredMonbell = () => {
            try {
                const stored = localStorage.getItem(MONBELL_STORAGE_KEY);
                return typeof stored === 'string' ? stored.trim() : '';
            } catch (e) {
                console.warn('read stored monbell failed', e);
                return '';
            }
        };
        const persistMonbellValue = (value) => {
            try {
                localStorage.setItem(MONBELL_STORAGE_KEY, value || '');
            } catch (e) {
                console.warn('persist monbell failed', e);
            }
        };
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
        const hasFullWidth = (value = '') => /[！-～]|　/.test(value);
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
                    setTimeout(() => onNormalized(), 0);
                }
            };
            input.addEventListener('beforeinput', (e) => {
                if (typeof e.data !== 'string') return;
                if (!hasFullWidth(e.data)) return;
                e.preventDefault();
                const converted = toHalfWidth(e.data);
                const start = input.selectionStart ?? input.value.length;
                const end = input.selectionEnd ?? start;
                input.setRangeText(converted, start, end, 'end');
                if (typeof onNormalized === 'function' && converted.trim() !== '') {
                    setTimeout(() => onNormalized(), 0);
                }
            });
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
        const qrDelimiter = document.body.dataset.qrDelim || '_';
        const qrDelimiters = Array.from(new Set([qrDelimiter, '__']));
        const hasQrDelimiter = (value = '') => qrDelimiters.some(del => del && value.includes(del));
        const normalizeQrDelimiter = (value = '') => {
            let out = value;
            qrDelimiters.forEach(del => {
                if (!del || del === qrDelimiter) return;
                if (out.includes(del)) {
                    out = out.split(del).join(qrDelimiter);
                }
            });
            return out;
        };
        const serialScanInput = el('serialScanInput');
        const qrScanInput = el('qrScanInput');
        const saveToast = el('saveToast');
        enforceHalfwidthInput(serialScanInput, () => commitScanField(serialScanInput, 'serial'));
        enforceHalfwidthInput(qrScanInput, () => commitScanField(qrScanInput, 'qr'));
        const SCAN_AUTO_DELAY = 0; // 遅延なしで即確定
        const scanAutoTimers = {
            serial: null,
            qr: null
        };
        const SCAN_DEBUG = true;
        const scanDebug = (...args) => {
            if (!SCAN_DEBUG) return;
            console.debug('[scan]', ...args);
        };

        let saveToastTimer = null;
        const parentOrigin = window.location.origin;
        const notifyParent = (payload) => {
            if (!window.parent || window.parent === window) return;
            try {
                window.parent.postMessage(payload, parentOrigin);
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

        function showSaveToast(message, duration = 1000) {
            if (!saveToast) return;
            if (saveToastTimer) {
                clearTimeout(saveToastTimer);
                saveToastTimer = null;
            }
            saveToast.textContent = message || '登録しました';
            saveToast.classList.remove('hide');
            saveToastTimer = setTimeout(() => {
                saveToast.classList.add('hide');
                saveToastTimer = null;
            }, duration);
        }

        function focusSerialField() {
            if (serialScanInput) {
                serialScanInput.focus();
            }
        }

        function focusQrField() {
            if (qrScanInput) qrScanInput.focus();
        }

        async function resetForInputError(target = 'serial') {
            // serial エラー時: workflow全体リセット
            await resetWorkflowForNextSerial();
            if (serialScanInput) serialScanInput.value = '';
            focusSerialField();
        }

        function clearQrInput() {
            if (qrScanInput) qrScanInput.value = '';
            focusQrField();
        }

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

        let current = {
            mode: null,
            papa: null,
            yankee: null,
            location: null,
            sierra: null
        };
        let currentCategory = document.body.dataset.initCarriro || '';
        let currentMonbell = document.body.dataset.initMonbell || '';

        const workflowNotice = (() => {
            const states = {
                serial: {
                    className: 'state-serial',
                    text: 'Serialを読んでください'
                },
                qr: {
                    className: 'state-qr',
                    text: 'errorQRを読み込んでください'
                },
                manual: {
                    className: 'state-manual',
                    text: 'エラー情報を入力してください'
                }
            };
            const wrap = el('workflowNotice');
            const textEl = el('workflowNoticeText');
            let currentState = 'serial';
            let flashTimer = null;

            function set(stateKey) {
                if (!wrap || !textEl) return;
                Object.values(states).forEach(cfg => wrap.classList.remove(cfg.className));
                const state = states[stateKey] || states.serial;
                wrap.classList.add(state.className);
                textEl.textContent = state.text;
                currentState = stateKey;
            }

            function flash(message, duration = 3000, highlightState = 'serial') {
                if (!wrap || !textEl) return;
                clearTimeout(flashTimer);
                Object.values(states).forEach(cfg => wrap.classList.remove(cfg.className));
                const state = states[highlightState] || states.serial;
                wrap.classList.add(state.className);
                const prevState = currentState;
                const prevText = states[prevState]?.text || textEl.textContent;
                textEl.textContent = message;
                flashTimer = setTimeout(() => {
                    set(prevState);
                    textEl.textContent = prevText;
                }, duration);
            }
            return {
                set,
                //   flash
            };
        })();

        function isManualCategory() {
            return (currentCategory || '').toLowerCase() === 'diag_sens';
        }

        function updateManualUI() {
            const manual = isManualCategory();
            const hasSerial = Boolean(current.sierra);
            const manualVisible = manual && hasSerial;
            const manualArea = el('manualArea');
            if (manualArea) manualArea.classList.toggle('hide', !manualVisible);
            const registerBtn = el('btnRegister');
            const registerVisible = manualVisible && !!current.papa && !!current.yankee;
            if (registerBtn) registerBtn.classList.toggle('hide', !registerVisible);
            const endBtn = el('btnEnd');
            const showEnd = hasSerial && !current.papa;
            if (endBtn) endBtn.classList.toggle('hide', !showEnd);
        }
        updateManualUI();

        async function ensureManualMode(options = {}) {
            if (isManualCategory()) return true;
            return await setCarriro('diag_sens', {
                auto: true,
                preserveSelections: !!options.preserveSelections
            });
        }

        function renderManualPlaceholder(message = '') {
            const partsBox = el('partsList');
            const symptomBox = el('symptomList');
            const locationBox = el('locationList');
            if (partsBox) {
                partsBox.innerHTML = message ? `<div class="selectionInfo">${message}</div>` : '';
                adjustChipDensity(partsBox);
            }
            if (symptomBox) {
                symptomBox.innerHTML = '';
                adjustChipDensity(symptomBox);
            }
            if (locationBox) {
                locationBox.innerHTML = '';
                adjustChipDensity(locationBox);
            }
        }

        const serialChipEl = el('serialChip');
        const selectionEls = {
            part: el('selectedPart'),
            symptom: el('selectedSymptom'),
            location: el('selectedLocation')
        };
        let endLockBySelection = false;
        const clearSelectionsBtn = el('btnClearSelections');
        clearSelectionsBtn?.addEventListener('click', () => {
            clearManualSelections();
            endLockBySelection = false;
            updateManualUI();
        });

        let activePartBtn = null;
        let activeSymptomBtn = null;
        let activeLocationBtn = null;

        function updateSerialChip(value) {
            if (!serialChipEl) return;
            serialChipEl.textContent = value ? value : '（読み込まれたserial）';
        }

        function updateSelectionInfo() {
            if (selectionEls.part) selectionEls.part.textContent = current.papa || '-';
            if (selectionEls.symptom) selectionEls.symptom.textContent = current.yankee || '-';
            if (selectionEls.location) selectionEls.location.textContent = current.location || '-';
        }

        function clearManualSelections() {
            if (activePartBtn) activePartBtn.classList.remove('active');
            if (activeSymptomBtn) activeSymptomBtn.classList.remove('active');
            if (activeLocationBtn) activeLocationBtn.classList.remove('active');
            activePartBtn = null;
            activeSymptomBtn = null;
            activeLocationBtn = null;
            current.papa = null;
            current.yankee = null;
            current.location = null;
            const symptomList = el('symptomList');
            const locationList = el('locationList');
            if (symptomList) {
                symptomList.innerHTML = '';
                adjustChipDensity(symptomList);
            }
            if (locationList) {
                locationList.innerHTML = '';
                adjustChipDensity(locationList);
            }
            updateSelectionInfo();
            endLockBySelection = false;
            updateManualUI();
        }

        function promptSerialScan() {
            workflowNotice.set('serial');
            updateSerialChip('');
        }

        function promptNextAction() {
            workflowNotice.set('qr');
        }

        function refreshWorkflowNotice() {
            if (current.sierra) {
                promptNextAction();
            } else {
                workflowNotice.set('serial');
            }
        }

        async function loadPartsList() {
            if (!isManualCategory()) {
                const ok = await ensureManualMode({
                    preserveSelections: true
                });
                if (!ok) {
                    renderManualPlaceholder('カテゴリ切替に失敗しました');
                    return;
                }
            }
            if (!current.sierra) {
                renderManualPlaceholder('Serialを保存すると候補が表示されます');
                return;
            }
            try {
                const res = await api('get_manual_parts_daig');
                if (!res.ok) {
                    setErr(res.error || '部品一覧取得エラー');
                    return;
                }
                const list = res.result?.partslist || res.partslist || [];
                const container = el('partsList');
                if (!container) return;
                container.innerHTML = '';
                adjustChipDensity(container);
                activePartBtn = null;
                activeSymptomBtn = null;
                activeLocationBtn = null;
                const symptomBox = el('symptomList');
                const locationBox = el('locationList');
                if (symptomBox) symptomBox.innerHTML = '';
                if (locationBox) locationBox.innerHTML = '';
                if (list.length === 0) {
                    const note = document.createElement('div');
                    note.className = 'selectionInfo';
                    note.textContent = '部品マスタがありません';
                    container.appendChild(note);
                    adjustChipDensity(container);
                    return;
                }
                list.forEach(part => {
                    const btn = document.createElement('button');
                    btn.type = 'button';
                    btn.className = 'chip block';
                    btn.textContent = part;
                    btn.addEventListener('click', () => selectPart(part, btn));
                    container.appendChild(btn);
                });
                adjustChipDensity(container);
                updateSelectionInfo();
            } catch (e) {
                setErr('部品一覧取得エラー: ' + e.message);
            }
        }

        async function selectPart(part, btn) {
            await ensureManualMode({
                preserveSelections: true
            });
            current.mode = 'manual';
            current.papa = part;
            current.yankee = null;
            current.location = null;
            if (activePartBtn) activePartBtn.classList.remove('active');
            activePartBtn = btn;
            btn.classList.add('active');
            if (activeSymptomBtn) activeSymptomBtn.classList.remove('active');
            if (activeLocationBtn) activeLocationBtn.classList.remove('active');
            activeSymptomBtn = null;
            activeLocationBtn = null;
            const symptomList = el('symptomList');
            const locationList = el('locationList');
            if (symptomList) symptomList.innerHTML = '';
            if (locationList) locationList.innerHTML = '';
            updateSelectionInfo();
            endLockBySelection = true;
            updateManualUI();
            await renderPositionsByPart(part);
        }

        async function selectLocation(location, btn) {
            if (activeLocationBtn) activeLocationBtn.classList.remove('active');
            activeLocationBtn = btn;
            btn.classList.add('active');
            current.location = location;
            current.yankee = null;
            if (activeSymptomBtn) activeSymptomBtn.classList.remove('active');
            activeSymptomBtn = null;
            const symptomList = el('symptomList');
            if (symptomList) {
                symptomList.innerHTML = '';
                adjustChipDensity(symptomList);
            }
            updateSelectionInfo();
            updateManualUI();
            await loadSymptoms(current.papa);
        }

        async function loadSymptoms(part) {
            try {
                const r = await api('get_symptomlist', {
                    papa: part
                });
                if (!r.ok) {
                    setErr(r.error || 'symptom取得エラー');
                    return;
                }
                const box = el('symptomList');
                if (!box) return;
                box.innerHTML = '';
                adjustChipDensity(box);
                const list = uniqueOptions(r.result?.symptomlist || r.symptomlist || []);
                if (list.length === 0) {
                    const note = document.createElement('div');
                    note.className = 'selectionInfo';
                    note.textContent = '症状の候補がありません (- で登録)';
                    box.appendChild(note);
                    adjustChipDensity(box);
                    current.mode = 'manual';
                    current.yankee = '-';
                    current.location = '-';
                    updateSelectionInfo();
                    updateManualUI();
                    return;
                }
                if (list.length === 1 && list[0] === '-') {
                    const auto = document.createElement('div');
                    auto.className = 'selectionInfo';
                    auto.textContent = '症状入力不要 (-)';
                    box.appendChild(auto);
                    adjustChipDensity(box);
                    current.mode = 'manual';
                    current.yankee = '-';
                    current.location = '-';
                    updateSelectionInfo();
                    updateManualUI();
                    const locWrap = el('locationList');
                    if (locWrap) {
                        locWrap.innerHTML = '';
                        const locNote = document.createElement('div');
                        locNote.className = 'selectionInfo';
                        locNote.textContent = '位置指定は不要です';
                        locWrap.appendChild(locNote);
                        adjustChipDensity(locWrap);
                    }
                    return;
                }
                list.forEach(symptom => {
                    const btn = document.createElement('button');
                    btn.type = 'button';
                    btn.className = 'chip block';
                    btn.textContent = symptom;
                    btn.addEventListener('click', async () => {
                        if (activeSymptomBtn) activeSymptomBtn.classList.remove('active');
                        activeSymptomBtn = btn;
                        btn.classList.add('active');
                        current.yankee = symptom;
                        updateSelectionInfo();
                        await renderPositions('locationList', part, symptom, (loc) => {
                            current.location = loc;
                            updateSelectionInfo();
                        }, true, current.location);
                        updateManualUI();
                    });
                    box.appendChild(btn);
                });
                adjustChipDensity(box);
            } catch (e) {
                setErr('symptom取得エラー: ' + e.message);
            }
        }

        async function renderPositionsByPart(part) {
            const wrap = el('locationList');
            if (!wrap) return;
            wrap.innerHTML = '';
            adjustChipDensity(wrap);
            try {
                const r = await api('get_positionlist_by_part', {
                    papa: part
                });
                if (!r.ok) {
                    setErr(r.error || 'position取得エラー');
                    return;
                }
                const list = uniqueOptions(r.result?.positionlist || r.positionlist || []);
                if (list.length === 0) {
                    const note = document.createElement('div');
                    note.className = 'selectionInfo';
                    note.textContent = '位置指定は不要です';
                    wrap.appendChild(note);
                    adjustChipDensity(wrap);
                    current.location = '-';
                    updateSelectionInfo();
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
            } catch (e) {
                setErr('position取得エラー: ' + e.message);
            }
        }

        function hardResetSerialState(keepSerial = false) {
            if (!keepSerial) {
                current.sierra = null;
                if (serialScanInput) serialScanInput.value = '';
            }
            current.mode = null;
            current.papa = null;
            current.yankee = null;
            current.location = null;
            clearManualSelections();
            if (keepSerial && current.sierra) {
                updateSerialChip(current.sierra);
                promptNextAction();
            } else {
                updateSerialChip('');
                promptSerialScan();
            }
            updateManualUI();
            updateSelectionInfo();
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
                const r = await api('reset_workflow_state', params);
                if (!r.ok) {
                    console.warn('reset_workflow_state failed', r.error || r.message);
                }
            } catch (err) {
                console.warn('reset_workflow_state error', err);
            }
            hardResetSerialState(keepSerial);
            if (!keepSerial) {
                notifySerialCleared();
                clearLogsUI('serialを読み込むと不良が表示されます');
            }
            if (isManualCategory()) {
                if (keepSerial && current.sierra) {
                    await loadPartsList();
                } else {
                    renderManualPlaceholder('Serialを保存すると候補が表示されます');
                }
            }
        }

        updateSelectionInfo();
        hardResetSerialState();

        async function ensureInitialMonbell(datasetValue) {
            const initial = typeof datasetValue === 'string' ? datasetValue.trim() : '';
            let candidate = initial;
            if (!candidate && isEmbeddedFrame) {
                candidate = readStoredMonbell();
            }
            if (candidate) {
                const ok = await setMonbellValue(candidate, true);
                if (ok) return true;
                persistMonbellValue('');
            }
            currentMonbell = '';
            if (isEmbeddedFrame) {
                setErr('modelを選択してください');
            }
            return false;
        }

        async function setCarriro(carriro, opts = {}) {
            if (!carriro) return false;
            const options = typeof opts === 'boolean' ? {
                auto: opts
            } : (opts || {});
            const auto = !!options.auto;
            const preserveSelections = !!options.preserveSelections;
            try {
                const r = await api('set_category', {
                    carriro
                });
                if (!r.ok) {
                    setErr(r.message || r.error || 'カテゴリエラー');
                    return false;
                }
                currentCategory = carriro;
                updateManualUI();
                refreshWorkflowNotice();
                const catPill = el('cat-pill');
                if (catPill) catPill.textContent = carriro;
                if (!preserveSelections) {
                    clearManualSelections();
                    current.mode = null;
                    current.papa = null;
                    current.yankee = null;
                    current.location = null;
                }
                updateSelectionInfo();
                const manualNow = isManualCategory();
                if (manualNow) {
                    if (current.sierra) {
                        await loadPartsList();
                    } else {
                        renderManualPlaceholder('Serialを保存すると候補が表示されます');
                    }
                } else {
                    renderManualPlaceholder('');
                }
                if (!auto) setMsg(`${carriro} を選択しました`);
                return true;
            } catch (e) {
                setErr('カテゴリ設定エラー: ' + e.message);
                return false;
            }
        }

        async function persistSerialValue(sierra) {
            scanDebug('persist-serial-start', sierra);
            try {
                const r = await api('h_get_serial', {
                    sierra
                });
                if (!r.ok && r.error) {
                    scanDebug('persist-serial-fail', r);
                    setErr(r.error || 'シリアル保存エラー');
                    return false;
                }
            } catch (e) {
                scanDebug('persist-serial-error', e);
                setErr('シリアル保存エラー: ' + e.message);
                return false;
            }
            promptNextAction();
            current.sierra = sierra;
            notifySerialUpdate(sierra);
            clearManualSelections();
            endLockBySelection = false;
            updateSerialChip(sierra);
            updateManualUI();
            await refreshLogs();
            scanDebug('persist-serial-done', sierra);
            return true;
        }

        const scanQueue = [];
        let scanProcessing = false;

        async function processScanValue(entry) {
            if (!entry) return;
            const type = entry.type || 'serial';
            const payload = (entry.value || '').trim();
            if (!payload) return;
            scanDebug('process', {
                type,
                payload
            });
            const isQrLike = hasQrDelimiter(payload);
            if (type === 'qr') {
                if (/[^0-9A-Za-z_]/.test(payload)) {
                    setErr('QRの形式が正しくありません（英数字と区切りのみ）');
                    clearQrInput();
                    return;
                }
                scanDebug('treat-as-qr', {
                    payload,
                    via: type
                });
                await processQrPayload(payload, {
                    strictDelimiter: true
                });
                return;
            }

            // serialフィールドでQRらしき入力が来た場合も、まず英数字チェックを優先
            if (!isAlnum(payload)) {
                scanDebug('serial-invalid', {
                    payload
                });
                cancelAutoCommit('serial');
                setErr('serialは英数字のみです。読み直してください');
                await resetForInputError('serial');
                return;
            }

            const stored = await persistSerialValue(payload);
            scanDebug('serial-persisted', {
                payload,
                stored
            });
            if (!stored) return;
            if (current.papa && current.yankee) {
                scanDebug('auto-register-after-serial', {
                    papa: current.papa,
                    yankee: current.yankee
                });
                await doAutoRegister();
            } else {
                if (isManualCategory()) {
                    await loadPartsList();
                }
                focusQrField();
            }
        }

        async function drainScanQueue() {
            if (scanProcessing) return;
            scanProcessing = true;
            try {
                while (scanQueue.length) {
                    const next = scanQueue.shift();
                    scanDebug('drain-next', next);
                    await processScanValue(next);
                }
            } finally {
                scanProcessing = false;
            }
        }

        function enqueueScanValue(value, type = 'serial') {
            const trimmed = (value || '').trim();
            if (!trimmed) return;
            scanDebug('enqueue', {
                type,
                value: trimmed
            });
            scanQueue.push({
                value: trimmed,
                type
            });
            drainScanQueue();
        }

        function cancelAutoCommit(type) {
            if (scanAutoTimers[type]) {
                clearTimeout(scanAutoTimers[type]);
                scanAutoTimers[type] = null;
            }
        }

        function scheduleAutoCommit(type) {
            // 自動遅延コミットは使用しない（Enterまたは即時コミット）
            return;
        }

        function commitScanField(input, type) {
            if (!input) return;
            cancelAutoCommit(type);
            const value = input.value.trim();
            if (type !== 'serial') {
                input.value = '';
            }
            if (!value) return;
            scanDebug('field-commit', {
                type,
                value
            });
            enqueueScanValue(value, type);
            if (type === 'serial') {
                if (serialScanInput) serialScanInput.value = value;
                // 次アクションのフォーカスは processScanValue に任せる
            } else {
                focusQrField();
            }
        }

        function setupScanFields() {
            if (serialScanInput) {
                serialScanInput.addEventListener('keydown', (ev) => {
                    if (ev.key !== 'Enter') return;
                    ev.preventDefault();
                    commitScanField(serialScanInput, 'serial');
                });
            }
            if (qrScanInput) {
                qrScanInput.addEventListener('keydown', (ev) => {
                    if (ev.key !== 'Enter') return;
                    ev.preventDefault();
                    commitScanField(qrScanInput, 'qr');
                });
            }
        }

        const btnSerialReset = document.getElementById('btnSerialReset');
        if (btnSerialReset) {
            btnSerialReset.addEventListener('click', async () => {
                await resetWorkflowForNextSerial();
                if (serialScanInput) serialScanInput.value = '';
                setMsg('シリアルをリセットしました');
                focusSerialField();
            });
        }

        async function setMonbellValue(monbell, silent = false) {
            try {
                const r = await api('set_model', {
                    monbell
                });
                if (!r.ok) {
                    if (!silent) setErr(r.error || 'model設定エラー');
                    return false;
                }
                const value = r.result?.monbell ?? '';
                currentMonbell = value;
                persistMonbellValue(value);
                const modelEl = el('model');
                if (modelEl) modelEl.value = value;
                if (!silent) {
                    setMsg(value ? `model ${value} を設定` : 'modelをクリアしました');
                }
                return true;
            } catch (e) {
                if (!silent) setErr('model設定エラー: ' + e.message);
                return false;
            }
        }

        function handleModelSubmit() {
            const input = el('model');
            if (!input) return;
            const value = input.value.trim();
            setMonbellValue(value);
        }

        const btnModel = el('btnModel');
        if (btnModel) {
            btnModel.addEventListener('click', handleModelSubmit);
        }
        const modelInput = el('model');
        if (modelInput) {
            modelInput.addEventListener('keydown', (ev) => {
                if (ev.key === 'Enter') {
                    ev.preventDefault();
                    handleModelSubmit();
                }
            });
        }

        async function processQrPayload(rawInput, options = {}) {
            const strictDelimiter = !!options.strictDelimiter;
            setMsg('');
            setErr('');
            let qr = (rawInput || '').trim();
            if (!qr) {
                setErr('QRが空');
                return;
            }
            try {
                const hasDelim = hasQrDelimiter(qr);
                if (!hasDelim) {
                    if (strictDelimiter) {
                        const fallback = ['_', '=', ',', '1'].find(del => qr.includes(del));
                        if (fallback) {
                            const normalized = qr.split(fallback).join(qrDelimiter);
                            scanDebug('qr-delimiter-normalized', {
                                from: fallback,
                                raw: qr,
                                normalized
                            });
                            await processQrPayload(normalized, {
                                strictDelimiter: false
                            });
                            return;
                        }
                        setErr(`QRの形式が正しくありません（区切り記号 ${qrDelimiter} または __ が含まれていません）`);
                        clearQrInput();
                        console.error('QR delimiter missing', {
                            qr,
                            expected: qrDelimiters
                        });
                        return;
                    }
                    const stored = await persistSerialValue(qr);
                    if (!stored) return;
                    if (current.papa && current.yankee) {
                        await doAutoRegister();
                    } else {
                        if (isManualCategory()) {
                            await loadPartsList();
                        }
                    }
                    return;
                }
                const normalizedQr = normalizeQrDelimiter(qr);
                if (normalizedQr !== qr) {
                    scanDebug('qr-delimiter-normalized2', {
                        raw: qr,
                        normalized: normalizedQr
                    });
                    qr = normalizedQr;
                }

                const r = await api('qr_to_text', {
                    qr
                });
                if (!r.ok) {
                    setErr(r.warn || r.error || 'QRエラー');
                    clearQrInput();
                    return;
                }
                const papa = r.result?.papa ?? r.papa;
                const yankee = r.result?.yankee ?? r.yankee;
                const validate = await api('validate_parts_symptom', {
                    papa,
                    yankee
                });
                if (!validate.ok) {
                    setErr(validate.warn || validate.error || 'マスタに存在しません');
                    clearQrInput();
                    return;
                }
                current.mode = 'qr';
                current.papa = papa;
                current.yankee = yankee;
                current.location = null;
                const diagOk = await setCarriro('diag_soft', {
                    auto: true,
                    preserveSelections: true
                });
                if (!diagOk) {
                    setErr('diag_softカテゴリを設定できません');
                    clearQrInput();
                    return;
                }

                if (current.sierra) {
                    await doAutoRegister();
                } else {
                    setMsg('QR解析完了 - Serialを読み取ると自動登録されます');
                }
            } catch (e) {
                setErr('QR分解エラー: ' + e.message);
                console.error('processQrPayload error', e);
                clearQrInput();
            }
        }

        // 外部iframe等から window.handleQrText('PART_SYM') でQRデータを投入できる
        window.handleQrText = function(qrText) {
            enqueueScanValue(qrText, 'qr');
        };

        async function doAutoRegister() {
            if (!current.papa || !current.yankee) return;
            const duplicateLocation = current.location ?? '-';
            if (hasDuplicateLog(current.papa, current.yankee, duplicateLocation)) {
                setErr('同じ不良内容が既に登録されています');
                return;
            }
            setMsg('自動登録中...');
            setErr('');
            try {
                const r = await api('register_qr', {
                    qr: `${current.papa}_${current.yankee}`,
                    location: current.location ?? ''
                });
                if (!r.ok) {
                    setErr(r.message || r.result?.warn || r.result?.error || r.error || '保存に失敗しました');
                    return;
                }
                const partLabel = current.papa || '-';
                const symptomLabel = current.yankee || '-';
                const serverMsg = r.result?.message || r.message;
                const msgText = serverMsg || `${partLabel} / ${symptomLabel} を登録しました`;
                setMsg(msgText);
                showSaveToast(msgText);
                await refreshLogs();
                await resetWorkflowForNextSerial({
                    keepSerial: true
                });
                await ensureManualMode();
                focusQrField();
            } catch (e) {
                setErr('登録エラー: ' + e.message);
                console.error('register_qr error', e);
            }
        }

        async function renderPositions(containerId, papa, yankee, onPick, isManual = false, keepValue = null) {
            const wrap = el(containerId);
            if (!wrap) return;
            wrap.innerHTML = '';
            adjustChipDensity(wrap);
            try {
                const r = await api('get_positionlist', {
                    papa,
                    yankee
                });
                if (!r.ok) {
                    setErr(r.error || 'position取得エラー');
                    return;
                }
                const list = uniqueOptions(r.result?.positionlist || r.positionlist || []);
                if (list.length === 0) {
                    if (isManual) {
                        const note = document.createElement('div');
                        note.className = 'selectionInfo';
                        note.textContent = 'location候補なし（\"-\"で登録可能）';
                        wrap.appendChild(note);
                        adjustChipDensity(wrap);
                        if (activeLocationBtn) activeLocationBtn.classList.remove('active');
                        activeLocationBtn = null;
                        onPick('-');
                    }
                    return;
                }
                const preferred = keepValue ?? null;
                let matched = false;
                list.forEach(pos => {
                    const btn = document.createElement('button');
                    btn.className = isManual ? 'chip block' : 'chip';
                    btn.textContent = pos;
                    btn.dataset.pos = pos;
                    btn.addEventListener('click', () => {
                        [...wrap.querySelectorAll('button')].forEach(c => c.classList.remove('active'));
                        btn.classList.add('active');
                        if (isManual) {
                            if (activeLocationBtn && activeLocationBtn !== btn) {
                                activeLocationBtn.classList.remove('active');
                            }
                            activeLocationBtn = btn;
                        }
                        onPick(pos);
                    });
                    if (preferred && pos === preferred) {
                        btn.classList.add('active');
                        if (isManual) {
                            if (activeLocationBtn && activeLocationBtn !== btn) {
                                activeLocationBtn.classList.remove('active');
                            }
                            activeLocationBtn = btn;
                        }
                        onPick(pos);
                        matched = true;
                    }
                    wrap.appendChild(btn);
                });
                adjustChipDensity(wrap);
                if (!matched && preferred) {
                    if (activeLocationBtn) activeLocationBtn.classList.remove('active');
                    activeLocationBtn = null;
                    onPick(null);
                }
            } catch (e) {
                setErr('position取得エラー: ' + e.message);
            }
        }

        el('btnRegister').addEventListener('click', async () => {
            setMsg('');
            setErr('');
            try {
                if (!isManualCategory()) {
                    setErr('このカテゴリは自動登録のみです');
                    return;
                }
                if (!current.sierra) {
                    setErr('serialを先に読み込んでください');
                    return;
                }
                if (!current.papa || !current.yankee) {
                    setErr('手動選択が未完了');
                    return;
                }
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
                showSaveToast(serverMsg);
                await refreshLogs();
                await resetWorkflowForNextSerial({
                    keepSerial: true
                });
                await ensureManualMode();
                focusQrField();
                endLockBySelection = false;
                updateManualUI();
            } catch (e) {
                setErr('登録エラー: ' + e.message);
                console.error('register_manual error', e);
            }
        });

        async function fetchDiagCategories() {
            const diagMap = new Map();
            const addDiag = (name) => {
                const trimmed = (name || '').trim();
                if (!trimmed) return;
                diagMap.set(trimmed.toLowerCase(), trimmed);
            };
            try {
                const res = await api('get_model_categories', {
                    monbell: currentMonbell || ''
                });
                if (res.ok && Array.isArray(res.result?.categories)) {
                    res.result.categories.forEach(name => {
                        if (typeof name === 'string' && name.toLowerCase().startsWith('diag')) {
                            addDiag(name);
                        }
                    });
                }
            } catch (err) {
                console.warn('get_model_categories failed', err);
            }
            addDiag('diag_soft');
            addDiag('diag_sens');
            return Array.from(diagMap.values());
        }

        el('btnEnd').addEventListener('click', async () => {
            try {
                const diagCategories = await fetchDiagCategories();
                const diagSoftCarriro = 'diag_soft';
                const carriro = diagSoftCarriro;
                const sierra = current.sierra || '';
                const params = {
                    carriro,
                    extra_categories: JSON.stringify(diagCategories)
                };
                if (sierra !== '') params.sierra = sierra;
                const r = await api('save_end', params);
                if (!r.ok) {
                    setErr(r.error || '終了失敗');
                    return;
                }
                const msg = r.result?.message || '終了を記録';
                setMsg(msg);
                notifyCateEndUpdate(sierra);
                await navigateNextCategoryIfAny(sierra, carriro);
                current.sierra = null;
                await resetWorkflowForNextSerial();
                workflowNotice.set('serial');
                focusSerialField();
            } catch (e) {
                setErr('終了エラー: ' + e.message);
            }
        });

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
                    const values = [
                        row.parts ?? '-',
                        row.symptom ?? '-',
                        row.position ?? '-'
                    ];
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
                    monbell: currentMonbell || ''
                });
                const next = r.result?.next ?? null;
                if (next && next !== currentCate) {
                    await setCarriro(next, { auto: true });
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

        setupScanFields();

        // カテゴリ未選択時の表示制御
        const noCategoryPlaceholder = el('noCategoryPlaceholder');
        const mainContentWrap = el('mainContentWrap');
        function updateCategoryVisibility(hasCategory) {
            if (noCategoryPlaceholder && mainContentWrap) {
                if (hasCategory) {
                    noCategoryPlaceholder.style.display = 'none';
                    mainContentWrap.style.display = '';
                } else {
                    noCategoryPlaceholder.style.display = 'flex';
                    mainContentWrap.style.display = 'none';
                }
            }
        }

        (async function boot() {
            const initCarriro = document.body.dataset.initCarriro || '';
            const initMonbell = document.body.dataset.initMonbell || '';

            // カテゴリが未選択の場合は入力エリアを非表示にしてフォーカスしない
            if (!initCarriro) {
                updateCategoryVisibility(false);
                return; // カテゴリ未選択なので初期化を中断
            }

            updateCategoryVisibility(true);
            await ensureInitialMonbell(initMonbell);

            const bootCarriro = initCarriro !== '' ? initCarriro : 'DIAG';
            await setCarriro(bootCarriro, {
                auto: true
            });
            await refreshLogs();
            focusSerialField();
        })();
    </script>

    <div id="saveToast" class="saveToast hide"></div>

</body>

</html>

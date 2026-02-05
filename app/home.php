<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/db.php';

function fetchModelCate(): array
{
    $pdo = getPDO();
    $models = [];
    $stmt = $pdo->query("SELECT DISTINCT model FROM fail_master WHERE model IS NOT NULL AND model <> '' ORDER BY model");
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $value = trim((string)$row['model']);
        if ($value === '') continue;
        $models[] = $value;
    }

    $stmt = $pdo->query("SELECT model, cate FROM fail_master WHERE model IS NOT NULL AND model <> '' AND cate IS NOT NULL AND cate <> '' ORDER BY model, cate");
    $categories = [];
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $model = trim((string)$row['model']);
        $cate = trim((string)$row['cate']);
        if ($model === '' || $cate === '') continue;
        if (!isset($categories[$model])) $categories[$model] = [];
        if (!in_array($cate, $categories[$model], true)) {
            $categories[$model][] = $cate;
        }
    }

    return [$models, $categories];
}

[$modelList, $categoryMap] = fetchModelCate();
$forcedModel = '';
$pathInfo = $_SERVER['PATH_INFO'] ?? '';
if (is_string($pathInfo) && trim($pathInfo, '/') !== '') {
    $segments = explode('/', trim($pathInfo, '/'));
    if (!empty($segments[0])) {
        $forcedModel = trim((string)$segments[0]);
    }
}
$scriptDir = rtrim(dirname($_SERVER['SCRIPT_NAME']), '/');
if ($scriptDir === '') {
    $scriptDir = '/';
}
$baseHref = ($scriptDir === '/' ? '/' : $scriptDir . '/');
?>
<!doctype html>
<html lang="ja">

<head>
    <meta charset="utf-8" />
    <link rel="icon" href="judge-icon2.png" sizes="32x32" type="image/png">
    <base href="<?= htmlspecialchars($baseHref, ENT_QUOTES, 'UTF-8') ?>">
    <title>RAVIT_dev</title>
    <style>
        body {
            margin: 0;
            font: 15px/1.2 system-ui, -apple-system, "Segoe UI", sans-serif;
            background: linear-gradient(135deg, #f0f4f8 0%, #e2e8f0 100%);
            color: #1f2933;
            height: 100vh;
            display: flex;
            flex-direction: column;
        }

        .controlBar {
            padding: 14px 18px;
            background: rgba(255, 255, 255, 0.95);
            border-bottom: 1px solid #e2e8f0;
            display: flex;
            flex-direction: column;
            gap: 12px;
            position: relative;
            backdrop-filter: blur(10px);
        }

        .statsRow {
            position: fixed;
            top: 12px;
            right: 190px;
            display: flex;
            align-items: center;
            gap: 10px;
            font-weight: 700;
            color: #0f172a;
            background: rgba(255, 255, 255, 0.98);
            padding: 8px 14px;
            border-radius: 12px;
            border: 1px solid #e2e8f0;
            box-shadow: 0 4px 16px rgba(0, 0, 0, 0.08);
            z-index: 15;
        }

        .boxStats span {
            margin-left: 8px;
            font-size: 2em;
        }

        .cornerThumb {
            position: absolute;
            top: 10px;
            right: 10px;
            width: 48px;
            height: 48px;
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 8px 20px rgba(0, 0, 0, 0.15);
            background: #e5e7eb;
            transition: transform 0.2s ease;
        }

        .cornerThumb:hover {
            transform: scale(1.05);
        }

        .cornerThumb img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            display: block;
        }

        .controlGroup {
            display: flex;
            align-items: center;
            flex-wrap: wrap;
            gap: 8px;
            width: 100%;
            margin: 0;
        }

        .controlGroup span {
            font-weight: 700;
            color: #374151;
            min-width: 70px;
        }

        .modelAlert {
            font-weight: 800;
            color: #b91c1c;
            margin-top: 4px;
        }

        .btns {
            display: flex;
            flex-wrap: wrap;
            gap: 4px;
        }

        .controlGroup.adminLink {
            align-self: flex-end;
            justify-content: flex-end;
            width: auto;
        }

        .ctrlLink {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 10px 18px;
            border-radius: 10px;
            border: none;
            background: linear-gradient(135deg, #f1f5f9 0%, #e2e8f0 100%);
            color: #1f2933;
            font-weight: 700;
            text-decoration: none;
            box-shadow: 0 2px 6px rgba(0, 0, 0, 0.06);
            transition: all 0.2s ease;
        }

        .ctrlLink:hover {
            background: linear-gradient(135deg, #e2e8f0 0%, #cbd5e1 100%);
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
        }

        button.ctrl {
            padding: 10px 16px;
            border-radius: 10px;
            border: 2px solid #e2e8f0;
            background: #fff;
            cursor: pointer;
            font-weight: 600;
            transition: all 0.2s ease;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.04);
        }

        button.ctrl:hover {
            transform: translateY(-1px);
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.08);
        }
        .adminLinks {
            margin-left: auto;
            display: flex;
            align-items: center;
            gap: 6px;
            flex-wrap: wrap;
        }

        #modelBtns .ctrl {
            padding: 12px 18px;
            font-size: 20px;
            border-radius: 10px;
            min-width: 100px;
        }

        #catBtns .ctrl {
            padding: 12px 18px;
            min-width: 110px;
            border-radius: 10px;
            font-size: 24px;
        }

        button.ctrl.active {
            background: linear-gradient(135deg, #dbeafe 0%, #bfdbfe 100%);
            border: 3px solid #3b82f6;
            box-shadow: 0 4px 12px rgba(59, 130, 246, 0.25);
            color: #1e40af;
        }
        button.ctrl.completed {
            background: linear-gradient(135deg, #81868e 0%, #6b7280 100%);
            border-color: #4b5563;
            color: #fff;
        }
        button.ctrl.completed.active {
            background: linear-gradient(135deg, #768fad 0%, #7492b7 100%);
            border: 3px solid #2563eb;
            color: #000000;
        }

        iframe {
            flex: 1 1 auto;
            width: 100%;
            border: 0;
        }

        .frameArea {
            flex: 1 1 auto;
            position: relative;
            overflow: hidden;
            background: linear-gradient(135deg, #f8fafc 0%, #f1f5f9 100%);
            display: flex;
        }

        .framePlaceholder {
            position: absolute;
            inset: 0;
            width: 100%;
            height: 100%;
            object-fit: contain;
            background: linear-gradient(135deg, #f8fafc 0%, #f1f5f9 100%);
            pointer-events: none;
        }

        .error {
            color: #dc2626;
            font-weight: 600;
        }

        .hiddenInsertBtn {
            position: fixed;
            bottom: 100px;
            right: 280px;
            padding: 14px 18px;
            background: linear-gradient(135deg, #10b981 0%, #059669 100%);
            color: #fff;
            font-weight: 800;
            border: none;
            border-radius: 12px;
            text-decoration: none;
            opacity: 0.08;
            transition: all 0.3s ease;
            z-index: 20;
            box-shadow: 0 4px 12px rgba(16, 185, 129, 0.3);
        }
            font-weight: 800;
            border: 2px solid #0f5132;
            border-radius: 10px;
            text-decoration: none;
            opacity: 0.08;
            transition: opacity 0.3s ease;
            z-index: 20;
        }

        .hiddenInsertBtn.fade {
            opacity: 0.08;
        }

        .dragonCard {
            position: fixed;
            top: 8px;
            left: 58%;
            transform: translateX(-50%);
            background: rgba(255, 255, 255, 0.98);
            padding: 12px 16px;
            border-radius: 16px;
            display: flex;
            align-items: center;
            gap: 12px;
            z-index: 15;
            box-shadow: 0 8px 24px rgba(0, 0, 0, 0.1);
            backdrop-filter: blur(10px);
        }

        .dragonIcon {
            width: 60px;
            height: 60px;
            text-align: center;
        }

        .dragonIcon img {
            width: 100%;
            height: 100%;
            object-fit: contain;
            display: block;
            border-radius: 8px;
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
            right: 74px;
            padding: 8px 14px;
            border: none;
            background: linear-gradient(135deg, #f1f5f9 0%, #e2e8f0 100%);
            color: #0f172a;
            font-weight: 700;
            cursor: pointer;
            border-radius: 10px;
            z-index: 16;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.08);
            transition: all 0.2s ease;
        }

        .dragonToggleBtn:hover {
            background: linear-gradient(135deg, #e2e8f0 0%, #cbd5e1 100%);
            transform: translateY(-1px);
        }
    </style>
</head>

<body>
    <div class="dragonCard" id="dragonCard">
        <div class="dragonIcon" id="dragonIcon"><img src="egg.jpeg" alt="dragon"></div>
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
    <div class="statsRow">
        <div class="boxStats" id="boxStats"></div>
    </div>

    <div class="controlBar">
        <div class="cornerThumb">
            <img src="judge-icon2.png" alt="ホームロゴ">
        </div>
        <div class="controlGroup">
            <span>SKU</span>
            <div class="btns" id="modelBtns"></div>
            <div class="modelAlert" id="modelAlert" hidden>modelを再度選択してください</div>
        </div>
        <div class="controlGroup">
            <span>カテゴリ</span>
            <div class="btns" id="catBtns"></div>
            <div class="adminLinks">
                <a class="ctrlLink" href="judge.php" target="_blank" rel="noopener">Judge</a>
                <a class="ctrlLink" href="edit.php" target="_blank" rel="noopener">ログ編集</a>
                <a class="ctrlLink" href="box.php" target="_blank" rel="noopener">BoxID管理</a>
            </div>
        </div>
    </div>

    <form id="relayForm" action="index.php" method="post" target="workframe">
        <input type="hidden" name="monbell" id="fieldMonbell" value="">
        <input type="hidden" name="carriro" id="fieldCarriro" value="">
        <input type="hidden" name="sierra" id="fieldSierra" value="">
    </form>

    <div class="frameArea">
        <img src="moji3.png" alt="待機イメージ" class="framePlaceholder" id="framePlaceholder">
       <!-- <img src="mojidake.png" alt="待機イメージ" class="framePlaceholder" id="framePlaceholder"> -->
        <iframe src="about:blank" name="workframe" id="workframe" title="検査UI"></iframe>
    </div>
    <!-- <a href="master_insert.php" class="hiddenInsertBtn" id="hiddenInsertBtn" title="fail_master登録">ADD</a> -->

    <script>
        const fieldMonbell = document.getElementById('fieldMonbell');
        const fieldCarriro = document.getElementById('fieldCarriro');
        const relayForm = document.getElementById('relayForm');
        const iframeEl = document.getElementById('workframe');
        const placeholderEl = document.getElementById('framePlaceholder');
        const hiddenInsertBtn = document.getElementById('hiddenInsertBtn');
        const modelAlertEl = document.getElementById('modelAlert');
        const modelList = <?php echo json_encode($modelList, JSON_UNESCAPED_UNICODE); ?>;
        const categoryMap = <?php echo json_encode($categoryMap, JSON_UNESCAPED_UNICODE); ?>;
        const LAST_MODEL_KEY = 'home_last_monbell';
        const appBasePath = <?php echo json_encode($scriptDir, JSON_UNESCAPED_SLASHES); ?>;
        const resolveAppPath = (relativePath = '') => {
            const trimmed = (relativePath || '').replace(/^\/+/, '');
            if (!trimmed) return appBasePath;
            return (appBasePath.endsWith('/') ? appBasePath : (appBasePath + '/')) + trimmed;
        };
        const postJudgeApi = async (action, params = {}) => {
            const body = new URLSearchParams({ action, ...params });
            const res = await fetch(resolveAppPath('functions.php'), {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body
            });
            return res.json();
        };
        const normalizeModelKey = (value) => (typeof value === 'string' ? value.trim().toLowerCase() : '');
        const findModelFromList = (rawValue) => {
            const normalized = normalizeModelKey(rawValue);
            if (!normalized) return '';
            return modelList.find(model => normalizeModelKey(model) === normalized) || '';
        };
        const storedModel = (() => {
            try {
                const stored = localStorage.getItem(LAST_MODEL_KEY);
                return findModelFromList(stored);
            } catch (e) {
                console.warn('load stored model failed', e);
                return '';
            }
        })();
        const requestedModel = (() => {
            try {
                const params = new URLSearchParams(window.location.search);
                const keys = ['model', 'monbell', 'sku'];
                for (const key of keys) {
                    const candidate = params.get(key);
                    const resolved = findModelFromList(candidate);
                    if (resolved) {
                        console.log('[home.php] URL parameter matched model:', { key, value: resolved });
                        return resolved;
                    }
                }
                console.log('[home.php] URL parameter has no model match.');
                return '';
            } catch (e) {
                console.warn('parse model param failed', e);
                return '';
            }
        })();
        const forcedModelInfo = (() => {
            const raw = <?php echo json_encode($forcedModel, JSON_UNESCAPED_UNICODE); ?>;
            const resolved = findModelFromList(raw);
            return {
                raw: typeof raw === 'string' ? raw : '',
                value: resolved || ''
            };
        })();
        const forcedModel = forcedModelInfo.value;
        const forcedModelLabel = forcedModelInfo.value || forcedModelInfo.raw || '';
        const normalizedForcedModel = normalizeModelKey(forcedModel);
        const isModelLocked = normalizedForcedModel !== '';
        let currentMonbell = '';
        let currentCarriro = '';
        let hasFrameLoadedOnce = false;
        let waitingFirstLoad = false;
        let categoryList = [];
        const completedCategories = new Set();
        let currentSerial = '';
        let completionRequestId = 0;
        let lastEndedSerial = '';
        let currentCompletedSnapshot = new Set();
        // 常時薄く表示（フェードクラスも同一透明度で固定）

        function showPlaceholder() {
            placeholderEl?.removeAttribute('hidden');
        }

        function hidePlaceholder() {
            if (!placeholderEl) return;
            placeholderEl.setAttribute('hidden', 'hidden');
        }

        function persistSessionMonbell(value) {
            try {
                const body = new URLSearchParams({
                    action: 'set_model',
                    monbell: value || ''
                });
                fetch(resolveAppPath('functions.php'), {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body
                }).catch(err => console.warn('persist monbell failed', err));
            } catch (err) {
                console.warn('persist monbell request failed', err);
            }
        }

        async function refreshBoxStats() {
            try {
                const [todayRes, overviewRes] = await Promise.all([
                    postJudgeApi('judge_boxid_stats'),
                    postJudgeApi('judge_boxid_overview')
                ]);
                const result = todayRes?.result || {};
                const total = result.total ?? 0;
                const okCount = result.ok_count ?? 0;
                const ngCount = result.ng_count ?? 0;
                const rate = total > 0 ? ((okCount / total) * 100).toFixed(1) : '0.0';
                const elStats = document.getElementById('boxStats');
                if (elStats) {
                    elStats.innerHTML = `<span>本日投入 ${total}台</span><span>OK ${okCount}台</span><span>NG ${ngCount}台</span><span>救出率 ${rate}%</span>`;
                }
                updateDragon(overviewRes?.result || {}, total);
            } catch (err) {
                console.warn('refreshBoxStats failed', err);
            }
        }

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
            let img = 'egg.jpeg';
            if (feed >= 40) {
                stage = 'MAX';
                img = 'max.jpeg';
            } else if (stageFeed >= 30) {
                stage = '大人';
                img = 'adult.jpeg';
            } else if (stageFeed >= 15) {
                stage = '子供';
                img = 'child.jpeg';
            } else if (stageFeed >= 5) {
                stage = '赤ちゃん';
                img = 'egg2.jpeg';
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

        function focusWorkframe() {
            if (!iframeEl) return;
            try {
                iframeEl.contentWindow?.focus();
                iframeEl.contentDocument?.body?.focus();
            } catch (err) {
                console.warn('iframe focus failed', err);
            }
        }
        iframeEl?.addEventListener('load', () => {
            if (waitingFirstLoad) {
                hidePlaceholder();
                waitingFirstLoad = false;
                hasFrameLoadedOnce = true;
            }
            setTimeout(focusWorkframe, 50);
        });

        function clearWorkframe() {
            // iframeをクリアしてabout:blankに戻す
            if (iframeEl) {
                iframeEl.src = 'about:blank';
            }
        }

        function loadFrameForSelection() {
            if (!currentMonbell || !currentCarriro) {
                clearWorkframe();
                return;
            }
            waitingFirstLoad = true;
            showPlaceholder();
            const upperCate = currentCarriro.toUpperCase();
            const action = (upperCate.includes('DIAG')) ? 'index.php' : 'sonota.php';
            relayForm.action = resolveAppPath(action);
            fieldMonbell.value = currentMonbell;
            fieldCarriro.value = currentCarriro;
            // sonota ではシリアルを保持したまま渡す（DIAG側ではクリア扱い）
            const isSonota = action === 'sonota.php';
            fieldSierra.value = isSonota ? (currentSerial || '') : '';
            if (isSonota) {
                // loadイベントでserialを渡す
                function sendSerialOnLoad() {
                    if (iframeEl && iframeEl.contentWindow && currentSerial) {
                        iframeEl.contentWindow.postMessage({ type: 'restore-serial', serial: currentSerial }, '*');
                    }
                    iframeEl.removeEventListener('load', sendSerialOnLoad);
                }
                iframeEl.addEventListener('load', sendSerialOnLoad);
            }
            relayForm.submit();
            setTimeout(focusWorkframe, 200);
        }

        const normalizeCateKey = (value) => (typeof value === 'string' ? value.trim().toUpperCase() : '');

        function updateModelAlert() {
            if (!modelAlertEl) return;
            if (!currentMonbell) {
                modelAlertEl.textContent = 'modelを再度選択してください';
                modelAlertEl.removeAttribute('hidden');
            } else {
                modelAlertEl.setAttribute('hidden', 'hidden');
            }
        }

        function renderButtons(list, wrapId, attr, currentValue, handler) {
            const wrap = document.getElementById(wrapId);
            wrap.innerHTML = '';
            if (!list || list.length === 0) {
                wrap.innerHTML = '<span class="error">候補がありません</span>';
                return;
            }
            list.forEach(item => {
                const value = typeof item === 'string' ? item : item.value;
                const label = typeof item === 'string' ? item : (item.label ?? item.value);
                const isCompleted = attr === 'carriro' && completedCategories.has(normalizeCateKey(value));
                const btn = document.createElement('button');
                btn.type = 'button';
                btn.className = 'ctrl' + (value === currentValue ? ' active' : '') + (isCompleted ? ' completed' : '');
                btn.dataset[attr] = value;
                btn.textContent = label;
                btn.addEventListener('click', () => handler(value));
                wrap.appendChild(btn);
            });
        }

        function refreshCategoryButtons() {
            const wrap = document.getElementById('catBtns');
            if (!wrap) return;
            if (!currentMonbell) {
                wrap.innerHTML = '<span class="error">model未選択</span>';
                return;
            }
            renderButtons(categoryList, 'catBtns', 'carriro', currentCarriro, selectCategory);
        }

        function clearCompletedMarks() {
            completedCategories.clear();
            refreshCategoryButtons();
        }

        function buildCategoryEntries(rawList) {
            const entries = [];
            if (!rawList || !rawList.length) return entries;
            const diagCandidates = rawList.filter(c => c.toLowerCase().includes('diag'));
            if (diagCandidates.length) {
                const diagSens = diagCandidates.find(c => c.toLowerCase() === 'diag_sens');
                if (diagSens) {
                    entries.push({
                        value: diagSens,
                        label: '機能検査'
                    });
                } else {
                    const fallback = diagCandidates[0];
                    entries.push({
                        value: fallback,
                        label: '機能検査'
                    });
                }
            }
            rawList.forEach(c => {
                const lower = c.toLowerCase();
                if (lower.includes('diag')) {
                    if (lower === 'diag_sens' && entries.some(e => e.value === c)) return;
                    if (lower !== 'diag_sens') return;
                }
                entries.push({
                    value: c,
                    label: c
                });
            });
            return entries;
        }

        async function fetchCompletedCategories(serial) {
            const targetSerial = (serial || '').trim();
            currentSerial = targetSerial;
            const requestId = ++completionRequestId;
            completedCategories.clear();
            if (!targetSerial || !Array.isArray(categoryList) || categoryList.length === 0) {
                refreshCategoryButtons();
                return;
            }
            const categoriesToCheck = categoryList
                .map(item => {
                    if (typeof item === 'string') return item;
                    return item.value ?? item.label ?? '';
                })
                .filter(val => typeof val === 'string' && val.trim() !== '');
            if (categoriesToCheck.length === 0) {
                refreshCategoryButtons();
                return;
            }
            try {
                const body = new URLSearchParams({
                    action: 'check_cate_end',
                    sierra: targetSerial,
                    categories: JSON.stringify(categoriesToCheck)
                });
                const res = await fetch(resolveAppPath('functions.php'), {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded'
                    },
                    body
                });
                const text = await res.text();
                if (requestId !== completionRequestId) return;
                let completedList = [];
                try {
                    const parsed = JSON.parse(text);
                    if (parsed && parsed.ok && parsed.result) {
                        const missingKeys = new Set((parsed.result.missing || []).map(normalizeCateKey));
                        const requiredList = (Array.isArray(parsed.result.required) && parsed.result.required.length)
                            ? parsed.result.required
                            : categoriesToCheck;
                        currentCompletedSnapshot = new Set();
                        requiredList.forEach(cate => {
                            const key = normalizeCateKey(cate);
                            if (key && !missingKeys.has(key)) {
                                completedCategories.add(key);
                                currentCompletedSnapshot.add(key);
                                completedList.push(key);
                            }
                        });
                    }
                } catch (err) {
                    console.warn('check_cate_end parse failed', err, text ? String(text).slice(0, 120) : '');
                }
                // sonota.php(iframe)に完了カテゴリリストを送信
                if (iframeEl && iframeEl.contentWindow) {
                    iframeEl.contentWindow.postMessage({ type: 'cate-completed-list', completed: completedList }, '*');
                }
                // 非DIAGカテゴリのみを抽出して、すべて完了しているか判定
                try {
                    // parsedは上のパース成功ブロックで定義されているため再parseして参照
                    const parsed2 = JSON.parse(text);
                    const requiredList2 = (Array.isArray(parsed2?.result?.required) && parsed2.result.required.length)
                        ? parsed2.result.required
                        : categoriesToCheck;
                    const nonDiagKeys = requiredList2
                        .map(normalizeCateKey)
                        .filter(k => !k.includes('DIAG'));
                    const completedKeys = (completedList || []).map(k => ('' + k).toUpperCase());
                    if (nonDiagKeys.length > 0 && nonDiagKeys.every(k => completedKeys.includes(k))) {
                        // 非DIAGカテゴリがすべて完了 → sonotaに全完了通知
                        if (iframeEl && iframeEl.contentWindow) {
                            iframeEl.contentWindow.postMessage({ type: 'all-cate-end', serial: targetSerial }, '*');
                        }
                    }
                } catch (e) {
                    console.warn('all-cate-end check failed', e);
                }
            } catch (err) {
                console.warn('check_cate_end failed', err);
            }
            refreshCategoryButtons();
        }

        function selectModel(value) {
            const normalized = normalizeModelKey(value);
            if (isModelLocked && normalized !== normalizedForcedModel) {
                console.warn('model is locked', forcedModelLabel);
                return;
            }
            if (!value) return;
            currentMonbell = value;
            updateModelAlert();
            try {
                localStorage.setItem(LAST_MODEL_KEY, value || '');
            } catch (e) {
                console.warn('persist model failed', e);
            }
            persistSessionMonbell(value);
            currentCarriro = '';
            // モデル切り替え時はiframeをクリア（カテゴリ未選択状態にする）
            clearWorkframe();
            renderButtons(modelList, 'modelBtns', 'monbell', currentMonbell, selectModel);
            updateModelAlert();
            categoryList = buildCategoryEntries(categoryMap[value] || []);
            // SKU切り替え時のみserialクリア（クライアントとサーバ両方）
            currentSerial = '';
            // サーバ側のセッションもクリアしておく（非同期、fire-and-forget）
            try {
                const body = new URLSearchParams({ action: 'reset_workflow_state' });
                fetch(resolveAppPath('functions.php'), { method: 'POST', headers: { 'Content-Type': 'application/x-www-form-urlencoded' }, body })
                    .catch(err => console.warn('reset_workflow_state failed', err));
            } catch (e) {
                console.warn('reset workflow request failed', e);
            }
            clearCompletedMarks();
            fetchCompletedCategories(currentSerial);
        }

        function selectCategory(value, options = {}) {
            const force = !!(options && options.force);
            if (!currentMonbell) return;
            const upper = (value || '').toString().toUpperCase();
            const isDiag = upper.includes('DIAG');
            if (!force && currentSerial && currentSerial !== lastEndedSerial && currentCompletedSnapshot.size === 0) {
                const proceed = confirm('終了ボタンを押していません。カテゴリを切り替えますか？');
                if (!proceed) return;
            }
            currentCarriro = value;
            if (isDiag) {
                currentSerial = '';
                lastEndedSerial = '';
                try {
                    const body = new URLSearchParams({ action: 'reset_workflow_state' });
                    fetch(resolveAppPath('functions.php'), {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                        body
                    }).catch(err => console.warn('reset_workflow_state (diag switch) failed', err));
                } catch (e) {
                    console.warn('reset workflow request failed (diag switch)', e);
                }
            }
            clearCompletedMarks();
            refreshCategoryButtons(); // 選択に合わせてボタンのactive状態を更新
            loadFrameForSelection();
        }

        function handleSerialMessage(serial) {
            fetchCompletedCategories(serial);
        }

        function handleCateEndMessage(serial) {
            const useSerial = (serial || currentSerial || '').trim();
            if (!useSerial) return;
            const currentUpper = (currentCarriro || '').toUpperCase();
            const isDiagCurrent = currentUpper.startsWith('DIAG_');
            if (isDiagCurrent) {
                currentSerial = '';
                lastEndedSerial = '';
                clearCompletedMarks();
            }
            fetchCompletedCategories(useSerial);
        }

        function handleSerialCleared() {
            currentSerial = '';
            clearCompletedMarks();
        }

        window.addEventListener('message', (event) => {
            // origin チェックを緩め、子iframeからの通知を確実に受け取る
            const data = event.data || {};
            if (data.type === 'serial-updated') {
                handleSerialMessage(data.serial);
            } else if (data.type === 'cate-end-updated') {
                handleCateEndMessage(data.serial);
            } else if (data.type === 'serial-cleared') {
                handleSerialCleared();
            } else if (data.type === 'child-category-changed') {
                const nextCate = typeof data.category === 'string' ? data.category : '';
                if (!nextCate) return;
                const currentUpper = (currentCarriro || '').toUpperCase();
                if (currentUpper === nextCate.toUpperCase()) return;
                selectCategory(nextCate, { force: true });
            }
        });

        (function init() {
            if (!modelList.length) {
                document.getElementById('modelBtns').innerHTML = '<span class="error">modelなし</span>';
                return;
            }
            renderButtons(modelList, 'modelBtns', 'monbell', currentMonbell, selectModel);
            const initialModel = forcedModel || requestedModel || storedModel || '';
            if (initialModel) {
                selectModel(initialModel);
            }
            focusWorkframe();
            refreshBoxStats();
        })();
    </script>
</body>

</html>

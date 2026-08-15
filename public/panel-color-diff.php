<?php
// ④ パネル色差判定（再塗装検出）
// Step 1: Canvas上での矩形選択UI（PHP/GDでのΔE2000判定は次のステップで実装）
?>
<!DOCTYPE html>
<html lang="ja">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>パネル色差判定 | usedcar-checker</title>
<style>
  :root {
    --color-a: #ff3b30;
    --color-b: #0a7cff;
  }
  * { box-sizing: border-box; }
  body {
    margin: 0;
    padding: 16px;
    font-family: -apple-system, BlinkMacSystemFont, "Hiragino Sans", "Yu Gothic", sans-serif;
    background: #f5f5f7;
    color: #1c1c1e;
  }
  .wrap { max-width: 900px; margin: 0 auto; }
  h1 { font-size: 1.25rem; margin: 0 0 8px; }
  .desc { color: #555; font-size: 0.9rem; line-height: 1.5; margin: 0 0 16px; }
  .step { margin-bottom: 16px; }
  .file-btn {
    display: inline-block;
    background: #1c1c1e;
    color: #fff;
    padding: 10px 20px;
    border-radius: 8px;
    font-size: 0.95rem;
    cursor: pointer;
  }
  .file-btn input { display: none; }
  .canvas-wrap {
    background: #fff;
    border-radius: 12px;
    padding: 12px;
    box-shadow: 0 1px 4px rgba(0,0,0,0.08);
  }
  .selection-guidance {
    background: #eef6ff;
    border: 1px solid #b8dcff;
    color: #14507a;
    border-radius: 8px;
    padding: 10px 12px;
    font-size: 0.85rem;
    line-height: 1.5;
    margin: 0 0 12px;
  }
  .reflection-warning {
    margin-top: 12px;
    padding: 12px;
    border-radius: 8px;
    background: #fff8e5;
    border: 1px solid #e8a400;
    color: #6b4e00;
    font-size: 0.85rem;
    line-height: 1.5;
  }
  .reflection-warning p {
    margin: 0 0 4px;
  }
  .reflection-warning p:last-child {
    margin-bottom: 0;
  }
  .mode-buttons {
    display: flex;
    gap: 8px;
    margin-bottom: 10px;
    flex-wrap: wrap;
  }
  .mode-btn {
    flex: 1;
    min-width: 120px;
    padding: 10px 8px;
    border: 2px solid #ddd;
    border-radius: 8px;
    background: #fff;
    font-size: 0.85rem;
    cursor: pointer;
  }
  #modeA.active { border-color: var(--color-a); color: var(--color-a); font-weight: bold; }
  #modeB.active { border-color: var(--color-b); color: var(--color-b); font-weight: bold; }
  .reset-btn {
    padding: 10px 14px;
    border: none;
    border-radius: 8px;
    background: #eee;
    font-size: 0.85rem;
    cursor: pointer;
  }
  canvas {
    display: block;
    width: 100%;
    height: auto;
    touch-action: none;
    border-radius: 8px;
    background: #eee;
    cursor: crosshair;
  }
  .swatches {
    display: flex;
    gap: 16px;
    margin-top: 12px;
    flex-wrap: wrap;
    font-size: 0.85rem;
  }
  .swatch-item { display: flex; align-items: center; gap: 6px; }
  .swatch {
    width: 20px;
    height: 20px;
    border-radius: 4px;
    border: 1px solid #ccc;
    display: inline-block;
  }
  #submitBtn {
    margin-top: 16px;
    width: 100%;
    padding: 14px;
    border: none;
    border-radius: 8px;
    background: #0a7cff;
    color: #fff;
    font-size: 1rem;
    font-weight: bold;
    cursor: pointer;
  }
  #submitBtn:disabled {
    background: #ccc;
    cursor: not-allowed;
  }
  #result {
    margin-top: 8px;
    background: #1c1c1e;
    color: #d4ffb2;
    padding: 12px;
    border-radius: 8px;
    font-size: 0.75rem;
    overflow-x: auto;
  }
  #resultSummary {
    margin-top: 16px;
    padding: 16px;
    border-radius: 10px;
    border: 2px solid transparent;
  }
  #resultSummary.verdict-repaint_suspected {
    background: #fdecea;
    border-color: #ff3b30;
    color: #86211a;
  }
  #resultSummary.verdict-caution {
    background: #fff8e5;
    border-color: #e8a400;
    color: #6b4e00;
  }
  #resultSummary.verdict-ok {
    background: #eafaf0;
    border-color: #34a853;
    color: #1c6b3a;
  }
  #resultSummary .verdict-title {
    font-size: 1.05rem;
    font-weight: bold;
    margin: 0 0 4px;
  }
  #resultSummary .verdict-de {
    font-size: 0.85rem;
    margin: 0 0 8px;
    opacity: 0.85;
  }
  #resultSummary .panel-values {
    display: flex;
    gap: 16px;
    font-size: 0.8rem;
    flex-wrap: wrap;
  }
  details#rawResult {
    margin-top: 8px;
  }
  details#rawResult summary {
    cursor: pointer;
    font-size: 0.8rem;
    color: #555;
  }
</style>
</head>
<body>
<div class="wrap">
  <h1>④ パネル色差判定（再塗装検出）</h1>
  <p class="desc">
    ドアとフェンダーなど隣接する2つのパネルが1枚に写った写真をアップロードし、
    それぞれの範囲を矩形で囲んでください。フラッシュはOFF、直射日光の反射を避けて撮影したものを使ってください。
  </p>

  <div class="step">
    <label class="file-btn">
      写真を選択
      <input type="file" id="photoInput" accept="image/*" capture="environment">
    </label>
  </div>

  <div class="canvas-wrap" id="canvasWrap" hidden>
    <p class="selection-guidance">
      空や周囲の景色が映り込んでいない、フラットに見える塗装面を選んでください（ボンネットの端やドアの中央付近がおすすめです）。
    </p>

    <div class="mode-buttons">
      <button type="button" id="modeA" class="mode-btn active" data-mode="A">① パネルAを選択</button>
      <button type="button" id="modeB" class="mode-btn" data-mode="B">② パネルBを選択</button>
      <button type="button" id="resetBtn" class="reset-btn">リセット</button>
    </div>

    <canvas id="canvas"></canvas>

    <div class="swatches">
      <div class="swatch-item"><span class="swatch" id="swatchA"></span>パネルA: <span id="rgbA">未選択</span></div>
      <div class="swatch-item"><span class="swatch" id="swatchB"></span>パネルB: <span id="rgbB">未選択</span></div>
    </div>

    <button type="button" id="submitBtn" disabled>判定する</button>

    <div id="reflectionWarning" class="reflection-warning" hidden></div>

    <div id="resultSummary" hidden>
      <p class="verdict-title" id="verdictTitle"></p>
      <p class="verdict-de" id="verdictDe"></p>
      <div class="panel-values">
        <div>パネルA: <span id="valA"></span></div>
        <div>パネルB: <span id="valB"></span></div>
      </div>
    </div>
    <details id="rawResult" hidden>
      <summary>詳細データ(JSON)を表示</summary>
      <pre id="result"></pre>
    </details>
  </div>
</div>

<script>
(function () {
  const MAX_CANVAS_WIDTH = 900;

  const photoInput = document.getElementById('photoInput');
  const canvasWrap = document.getElementById('canvasWrap');
  const canvas = document.getElementById('canvas');
  const ctx = canvas.getContext('2d');
  const modeAbtn = document.getElementById('modeA');
  const modeBbtn = document.getElementById('modeB');
  const resetBtn = document.getElementById('resetBtn');
  const submitBtn = document.getElementById('submitBtn');
  const resultEl = document.getElementById('result');
  const rawResultEl = document.getElementById('rawResult');
  const reflectionWarningEl = document.getElementById('reflectionWarning');
  const resultSummaryEl = document.getElementById('resultSummary');
  const verdictTitleEl = document.getElementById('verdictTitle');
  const verdictDeEl = document.getElementById('verdictDe');
  const valAEl = document.getElementById('valA');
  const valBEl = document.getElementById('valB');
  const swatchA = document.getElementById('swatchA');
  const swatchB = document.getElementById('swatchB');
  const rgbAEl = document.getElementById('rgbA');
  const rgbBEl = document.getElementById('rgbB');

  // 元画像をオフスクリーンで保持し、オーバーレイ(枠線)に汚染されない平均RGB計算に使う
  const imgCanvas = document.createElement('canvas');
  const imgCtx = imgCanvas.getContext('2d');

  let img = new Image();
  let currentFile = null; // サーバーへ送る元ファイル(File)
  let scale = 1; // 元画像px * scale = canvas表示px
  let mode = 'A';
  let rects = { A: null, B: null }; // 元画像ピクセル座標 {x,y,w,h}
  let drawing = false;
  let dragStart = null;
  let currentDragRectCanvas = null; // ドラッグ中プレビュー(canvas座標系)

  photoInput.addEventListener('change', function (e) {
    const file = e.target.files[0];
    if (!file) return;
    currentFile = file;
    const reader = new FileReader();
    reader.onload = function (ev) {
      img = new Image();
      img.onload = setupCanvas;
      img.src = ev.target.result;
    };
    reader.readAsDataURL(file);
  });

  function setupCanvas() {
    const displayWidth = Math.min(img.naturalWidth, MAX_CANVAS_WIDTH);
    const displayHeight = Math.round(img.naturalHeight * (displayWidth / img.naturalWidth));
    scale = displayWidth / img.naturalWidth;

    canvas.width = displayWidth;
    canvas.height = displayHeight;
    imgCanvas.width = displayWidth;
    imgCanvas.height = displayHeight;
    imgCtx.drawImage(img, 0, 0, displayWidth, displayHeight);

    rects = { A: null, B: null };
    mode = 'A';
    currentDragRectCanvas = null;
    updateModeButtons();
    updateSwatches();
    submitBtn.disabled = true;
    resultSummaryEl.hidden = true;
    rawResultEl.hidden = true;
    reflectionWarningEl.hidden = true;
    canvasWrap.hidden = false;
    redraw();
  }

  function redraw() {
    ctx.clearRect(0, 0, canvas.width, canvas.height);
    ctx.drawImage(imgCanvas, 0, 0);
    drawRectOverlay(rects.A, getCssColor('--color-a'), 'A', false);
    drawRectOverlay(rects.B, getCssColor('--color-b'), 'B', false);
    if (currentDragRectCanvas) {
      drawRectOverlay(currentDragRectCanvas, getCssColor(mode === 'A' ? '--color-a' : '--color-b'), mode, true);
    }
  }

  function getCssColor(varName) {
    return getComputedStyle(document.documentElement).getPropertyValue(varName).trim();
  }

  function drawRectOverlay(rectData, color, label, isCanvasSpace) {
    if (!rectData) return;
    const r = isCanvasSpace
      ? rectData
      : { x: rectData.x * scale, y: rectData.y * scale, w: rectData.w * scale, h: rectData.h * scale };
    ctx.save();
    ctx.strokeStyle = color;
    ctx.lineWidth = 2;
    ctx.setLineDash(isCanvasSpace ? [6, 4] : []);
    ctx.strokeRect(r.x, r.y, r.w, r.h);
    ctx.fillStyle = color;
    ctx.font = 'bold 14px sans-serif';
    ctx.fillText(label, r.x + 4, r.y + 16);
    ctx.restore();
  }

  function getCanvasPos(evt) {
    const rect = canvas.getBoundingClientRect();
    const scaleX = canvas.width / rect.width;
    const scaleY = canvas.height / rect.height;
    const point = evt.touches ? evt.touches[0] : evt;
    return {
      x: (point.clientX - rect.left) * scaleX,
      y: (point.clientY - rect.top) * scaleY
    };
  }

  function clamp(v, min, max) { return Math.max(min, Math.min(max, v)); }

  function normalizeRect(p1, p2) {
    // 先に両端点をcanvas範囲内へクランプしてから矩形を求める。
    // (幅・高さを未クランプの差分から出すと、範囲外へのドラッグで意図せず矩形が膨張する)
    const c1 = { x: clamp(p1.x, 0, canvas.width), y: clamp(p1.y, 0, canvas.height) };
    const c2 = { x: clamp(p2.x, 0, canvas.width), y: clamp(p2.y, 0, canvas.height) };
    const x = Math.min(c1.x, c2.x);
    const y = Math.min(c1.y, c2.y);
    const w = Math.abs(c2.x - c1.x);
    const h = Math.abs(c2.y - c1.y);
    return { x, y, w, h };
  }

  function startDrag(evt) {
    if (!img.naturalWidth) return;
    evt.preventDefault();
    drawing = true;
    dragStart = getCanvasPos(evt);
  }

  function moveDrag(evt) {
    if (!drawing) return;
    evt.preventDefault();
    currentDragRectCanvas = normalizeRect(dragStart, getCanvasPos(evt));
    redraw();
  }

  function endDrag(evt) {
    if (!drawing) return;
    drawing = false;
    if (evt) evt.preventDefault();

    if (!currentDragRectCanvas || currentDragRectCanvas.w < 8 || currentDragRectCanvas.h < 8) {
      currentDragRectCanvas = null;
      redraw();
      return;
    }

    const r = currentDragRectCanvas;
    rects[mode] = {
      x: Math.round(r.x / scale),
      y: Math.round(r.y / scale),
      w: Math.round(r.w / scale),
      h: Math.round(r.h / scale)
    };
    currentDragRectCanvas = null;
    updateSwatches();

    // Aを選び終えたら自動でBの選択モードへ
    if (mode === 'A' && !rects.B) {
      mode = 'B';
      updateModeButtons();
    }
    submitBtn.disabled = !(rects.A && rects.B);
    redraw();
  }

  canvas.addEventListener('mousedown', startDrag);
  canvas.addEventListener('mousemove', moveDrag);
  window.addEventListener('mouseup', endDrag);
  canvas.addEventListener('touchstart', startDrag, { passive: false });
  canvas.addEventListener('touchmove', moveDrag, { passive: false });
  canvas.addEventListener('touchend', endDrag);
  canvas.addEventListener('touchcancel', endDrag);

  modeAbtn.addEventListener('click', function () { mode = 'A'; updateModeButtons(); });
  modeBbtn.addEventListener('click', function () { mode = 'B'; updateModeButtons(); });

  resetBtn.addEventListener('click', function () {
    rects = { A: null, B: null };
    mode = 'A';
    currentDragRectCanvas = null;
    updateModeButtons();
    updateSwatches();
    submitBtn.disabled = true;
    resultSummaryEl.hidden = true;
    rawResultEl.hidden = true;
    reflectionWarningEl.hidden = true;
    redraw();
  });

  function updateModeButtons() {
    modeAbtn.classList.toggle('active', mode === 'A');
    modeBbtn.classList.toggle('active', mode === 'B');
  }

  function averageRgbOfRect(rectData) {
    // プレビュー用の簡易平均値（表示解像度ベース）。
    // 実際の判定に使う平均値は、元画像フル解像度でサーバー側(PHP/GD)にて算出する。
    if (!rectData) return null;
    const cx = Math.round(rectData.x * scale);
    const cy = Math.round(rectData.y * scale);
    const cw = Math.max(1, Math.round(rectData.w * scale));
    const ch = Math.max(1, Math.round(rectData.h * scale));
    const data = imgCtx.getImageData(cx, cy, cw, ch).data;
    let r = 0, g = 0, b = 0, n = 0;
    for (let i = 0; i < data.length; i += 4) {
      r += data[i]; g += data[i + 1]; b += data[i + 2]; n++;
    }
    return { r: Math.round(r / n), g: Math.round(g / n), b: Math.round(b / n) };
  }

  function updateSwatches() {
    const a = averageRgbOfRect(rects.A);
    const b = averageRgbOfRect(rects.B);
    if (a) {
      swatchA.style.background = 'rgb(' + a.r + ',' + a.g + ',' + a.b + ')';
      rgbAEl.textContent = 'RGB(' + a.r + ', ' + a.g + ', ' + a.b + ') / ' + rects.A.w + '×' + rects.A.h + 'px';
    } else {
      swatchA.style.background = 'transparent';
      rgbAEl.textContent = '未選択';
    }
    if (b) {
      swatchB.style.background = 'rgb(' + b.r + ',' + b.g + ',' + b.b + ')';
      rgbBEl.textContent = 'RGB(' + b.r + ', ' + b.g + ', ' + b.b + ') / ' + rects.B.w + '×' + rects.B.h + 'px';
    } else {
      swatchB.style.background = 'transparent';
      rgbBEl.textContent = '未選択';
    }
  }

  const VERDICT_LABELS = {
    repaint_suspected: '再塗装の可能性あり',
    caution: '要注意（わずかな色差）',
    ok: '明確な色差は検出されませんでした'
  };

  submitBtn.addEventListener('click', async function () {
    if (!currentFile || !rects.A || !rects.B) return;

    submitBtn.disabled = true;
    submitBtn.textContent = '判定中...';
    resultSummaryEl.hidden = true;
    rawResultEl.hidden = true;
    reflectionWarningEl.hidden = true;

    const formData = new FormData();
    formData.append('photo', currentFile);
    formData.append('rectA', JSON.stringify(rects.A));
    formData.append('rectB', JSON.stringify(rects.B));

    try {
      const res = await fetch('api/panel-color-diff-analyze.php', { method: 'POST', body: formData });
      const data = await res.json();

      if (!res.ok || !data.ok) {
        resultSummaryEl.hidden = false;
        resultSummaryEl.className = 'verdict-caution';
        verdictTitleEl.textContent = 'エラー';
        verdictDeEl.textContent = data.error || ('HTTPエラー: ' + res.status);
        valAEl.textContent = '';
        valBEl.textContent = '';
      } else {
        renderResult(data);
      }

      rawResultEl.hidden = false;
      resultEl.textContent = JSON.stringify(data, null, 2);
    } catch (err) {
      resultSummaryEl.hidden = false;
      resultSummaryEl.className = 'verdict-caution';
      verdictTitleEl.textContent = '通信エラー';
      verdictDeEl.textContent = String(err);
    } finally {
      submitBtn.disabled = false;
      submitBtn.textContent = '判定する';
    }
  });

  function renderResult(data) {
    if (Array.isArray(data.warnings) && data.warnings.length > 0) {
      reflectionWarningEl.hidden = false;
      reflectionWarningEl.innerHTML = '';
      data.warnings.forEach(function (w) {
        const p = document.createElement('p');
        p.textContent = '⚠ ' + w;
        reflectionWarningEl.appendChild(p);
      });
    } else {
      reflectionWarningEl.hidden = true;
    }

    resultSummaryEl.hidden = false;
    resultSummaryEl.className = 'verdict-' + data.verdict;
    verdictTitleEl.textContent = VERDICT_LABELS[data.verdict] || data.message;
    verdictDeEl.textContent = 'Δab(色相・彩度差) = ' + data.deltaAb.toFixed(2) + '（閾値: ' + data.threshold.toFixed(1) + '）'
      + ' / 参考ΔE2000 = ' + data.deltaE.toFixed(2) + ' / ' + data.message;
    valAEl.textContent = formatPanelValue(data.panelA);
    valBEl.textContent = formatPanelValue(data.panelB);
  }

  function formatPanelValue(panel) {
    const [r, g, b] = panel.rgb;
    const [L, a, bLab] = panel.lab;
    return 'RGB(' + r + ',' + g + ',' + b + ') / Lab(' + L + ',' + a + ',' + bLab + ')';
  }
})();
</script>
</body>
</html>

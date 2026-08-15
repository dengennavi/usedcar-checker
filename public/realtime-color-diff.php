<?php
// ④パネル色差判定 - リアルタイム試作
// カメラ映像に対してタイルごとのΔab判定を継続的に重ね描きする。
// 精度検証より「動くかどうか」の確認を優先した試作段階の実装。
// (中央値サンプリング・Lab変換・Δabの考え方はpanel-color-diff.php/backend/ColorDiff.phpと共通)
?>
<!DOCTYPE html>
<html lang="ja">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>リアルタイム色差検知（試作） | usedcar-checker</title>
<style>
  * { box-sizing: border-box; }
  body {
    margin: 0;
    padding: 16px;
    font-family: -apple-system, BlinkMacSystemFont, "Hiragino Sans", "Yu Gothic", sans-serif;
    background: #f5f5f7;
    color: #1c1c1e;
  }
  .wrap { max-width: 700px; margin: 0 auto; }
  h1 { font-size: 1.1rem; margin: 0 0 4px; }
  .back-link { font-size: 0.8rem; color: #0a7cff; margin: 0 0 12px; display: inline-block; }
  .prototype-note {
    background: #fff4e0;
    border: 1px solid #f0c060;
    color: #6b4e00;
    border-radius: 8px;
    padding: 8px 10px;
    font-size: 0.75rem;
    margin: 0 0 12px;
  }
  .guidance-banner {
    background: #eef6ff;
    border: 1px solid #b8dcff;
    color: #14507a;
    border-radius: 8px;
    padding: 10px 12px;
    font-size: 0.85rem;
    line-height: 1.5;
    margin: 0 0 12px;
  }
  #startCameraBtn {
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
  .status-text {
    font-size: 0.8rem;
    color: #a00;
    margin: 8px 0 0;
  }
  .video-wrap {
    position: relative;
    width: 100%;
    background: #000;
    border-radius: 12px;
    overflow: hidden;
    line-height: 0;
  }
  .video-wrap video {
    display: block;
    width: 100%;
    height: auto;
  }
  .video-wrap canvas {
    position: absolute;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    touch-action: none;
  }
  .controls {
    margin-top: 12px;
    background: #fff;
    border-radius: 12px;
    padding: 12px;
    box-shadow: 0 1px 4px rgba(0,0,0,0.08);
  }
  .hint-text {
    font-size: 0.85rem;
    color: #555;
    margin: 0 0 8px;
  }
  .ref-info {
    display: flex;
    align-items: center;
    gap: 8px;
    flex-wrap: wrap;
    font-size: 0.8rem;
    margin: 0 0 10px;
  }
  .ref-swatch {
    width: 22px;
    height: 22px;
    border-radius: 4px;
    border: 1px solid #ccc;
    display: inline-block;
  }
  .ref-info button, #stopCameraBtn {
    padding: 8px 14px;
    border: 1px solid #ccc;
    border-radius: 8px;
    background: #f2f2f4;
    font-size: 0.8rem;
    cursor: pointer;
  }
  #stopCameraBtn { width: 100%; margin-top: 4px; }
  .legend {
    display: flex;
    gap: 14px;
    font-size: 0.75rem;
    color: #555;
    margin-top: 10px;
  }
  .legend span.dot {
    display: inline-block;
    width: 10px;
    height: 10px;
    border-radius: 2px;
    margin-right: 4px;
    vertical-align: middle;
  }
</style>
</head>
<body>
<div class="wrap">
  <a class="back-link" href="panel-color-diff.php">← 写真アップロードでの色差判定はこちら</a>
  <h1>リアルタイム色差検知（試作）</h1>
  <p class="prototype-note">
    試作段階の機能です。精度は未検証で、閾値も暫定値です。「動作するかどうか」の確認用としてお使いください。
  </p>
  <p class="guidance-banner">直射日光を避け、日陰で撮影してください</p>

  <div class="step" id="startStep">
    <button type="button" id="startCameraBtn">カメラを起動</button>
    <p class="status-text" id="cameraStatus"></p>
  </div>

  <div class="video-wrap" id="videoWrap" hidden>
    <video id="video" autoplay playsinline muted></video>
    <canvas id="overlayCanvas"></canvas>
  </div>

  <div class="controls" id="controls" hidden>
    <p class="hint-text" id="refHint">中央のマーカーに塗装面を合わせ、画面をタップして基準色を登録してください。</p>
    <div class="ref-info" id="refInfo" hidden>
      <span>基準色:</span>
      <span class="ref-swatch" id="refSwatch"></span>
      <span id="refValues"></span>
      <button type="button" id="clearRefBtn">基準色をクリア</button>
    </div>
    <div class="legend">
      <span><span class="dot" style="background:rgba(40,200,80,0.9)"></span>基準色に近い</span>
      <span><span class="dot" style="background:rgba(255,50,40,0.9)"></span>差が大きい</span>
    </div>
    <button type="button" id="stopCameraBtn">カメラを停止</button>
  </div>
</div>

<script>
(function () {
  const PROC_MAX_WIDTH = 240;   // 実処理用に縮小するcanvasの最大幅(px)。負荷対策。
  const TILE_SIZE = 20;         // procCanvas座標系でのタイルサイズ(px)
  const SAMPLE_STRIDE = 2;      // タイル内サンプリング間隔(px)
  const PROCESS_INTERVAL_MS = 250; // 0.2〜0.3秒ごとに再判定
  const DELTA_AB_THRESHOLD = 2.0;  // panel-color-diff.phpのバックエンドと同じ暫定閾値
  const REF_BOX_SIZE = 30;      // 基準色サンプリング範囲(procCanvas座標系, px)

  const videoEl = document.getElementById('video');
  const overlayCanvas = document.getElementById('overlayCanvas');
  const overlayCtx = overlayCanvas.getContext('2d');
  const startCameraBtn = document.getElementById('startCameraBtn');
  const stopCameraBtn = document.getElementById('stopCameraBtn');
  const cameraStatusEl = document.getElementById('cameraStatus');
  const startStepEl = document.getElementById('startStep');
  const videoWrapEl = document.getElementById('videoWrap');
  const controlsEl = document.getElementById('controls');
  const refHintEl = document.getElementById('refHint');
  const refInfoEl = document.getElementById('refInfo');
  const refSwatchEl = document.getElementById('refSwatch');
  const refValuesEl = document.getElementById('refValues');
  const clearRefBtn = document.getElementById('clearRefBtn');

  // 実処理(中央値サンプリング等)はオフスクリーンの縮小canvas上で行う
  const procCanvas = document.createElement('canvas');
  const procCtx = procCanvas.getContext('2d', { willReadFrequently: true });

  let stream = null;
  let referenceLab = null; // [L, a, b]
  let processTimer = null;

  startCameraBtn.addEventListener('click', startCamera);
  stopCameraBtn.addEventListener('click', stopCamera);
  clearRefBtn.addEventListener('click', function () {
    referenceLab = null;
    refInfoEl.hidden = true;
    refHintEl.hidden = false;
  });

  async function startCamera() {
    if (!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) {
      cameraStatusEl.textContent = 'このブラウザはカメラ機能に対応していません（HTTPS接続か確認してください）。';
      return;
    }
    cameraStatusEl.textContent = 'カメラを起動しています...';
    try {
      stream = await navigator.mediaDevices.getUserMedia({
        video: { facingMode: { ideal: 'environment' } },
        audio: false
      });
    } catch (err) {
      cameraStatusEl.textContent = 'カメラを起動できませんでした: ' + err.message;
      return;
    }

    videoEl.srcObject = stream;
    try {
      await videoEl.play();
    } catch (err) {
      cameraStatusEl.textContent = '映像の再生に失敗しました: ' + err.message;
      return;
    }

    if (videoEl.videoWidth > 0) {
      setupCanvases();
    } else {
      videoEl.addEventListener('loadedmetadata', setupCanvases, { once: true });
    }

    startStepEl.hidden = true;
    videoWrapEl.hidden = false;
    controlsEl.hidden = false;
    cameraStatusEl.textContent = '';

    if (processTimer) clearInterval(processTimer);
    processTimer = setInterval(processFrame, PROCESS_INTERVAL_MS);
  }

  function stopCamera() {
    if (processTimer) {
      clearInterval(processTimer);
      processTimer = null;
    }
    if (stream) {
      stream.getTracks().forEach(function (t) { t.stop(); });
      stream = null;
    }
    videoEl.srcObject = null;
    referenceLab = null;
    refInfoEl.hidden = true;
    refHintEl.hidden = false;
    videoWrapEl.hidden = true;
    controlsEl.hidden = true;
    startStepEl.hidden = false;
  }

  function setupCanvases() {
    const vw = videoEl.videoWidth;
    const vh = videoEl.videoHeight;
    if (!vw || !vh) return;

    const procWidth = Math.min(PROC_MAX_WIDTH, vw);
    const procHeight = Math.round(vh * (procWidth / vw));
    procCanvas.width = procWidth;
    procCanvas.height = procHeight;

    syncOverlaySize();
  }

  function syncOverlaySize() {
    // 表示中のvideo要素の実寸(CSSピクセル)にoverlayCanvasのバッファサイズを合わせる
    const rect = videoEl.getBoundingClientRect();
    if (rect.width > 0 && rect.height > 0) {
      overlayCanvas.width = Math.round(rect.width);
      overlayCanvas.height = Math.round(rect.height);
    }
  }

  window.addEventListener('resize', function () {
    if (!videoWrapEl.hidden) syncOverlaySize();
  });

  overlayCanvas.addEventListener('click', onTap);
  overlayCanvas.addEventListener('touchend', function (e) {
    e.preventDefault();
    onTap();
  });

  function onTap() {
    if (!procCanvas.width || videoEl.readyState < 2) return;

    procCtx.drawImage(videoEl, 0, 0, procCanvas.width, procCanvas.height);
    const boxX = Math.round(procCanvas.width / 2 - REF_BOX_SIZE / 2);
    const boxY = Math.round(procCanvas.height / 2 - REF_BOX_SIZE / 2);
    const boxData = procCtx.getImageData(boxX, boxY, REF_BOX_SIZE, REF_BOX_SIZE).data;
    const rgb = medianRgbOfTile(boxData, REF_BOX_SIZE, 0, 0, REF_BOX_SIZE, REF_BOX_SIZE);
    referenceLab = srgbToLab(rgb[0], rgb[1], rgb[2]);

    refInfoEl.hidden = false;
    refHintEl.hidden = true;
    const rr = Math.round(rgb[0]), rg = Math.round(rgb[1]), rb = Math.round(rgb[2]);
    refSwatchEl.style.background = 'rgb(' + rr + ',' + rg + ',' + rb + ')';
    refValuesEl.textContent = 'RGB(' + rr + ',' + rg + ',' + rb + ') Lab(' +
      referenceLab[0].toFixed(1) + ',' + referenceLab[1].toFixed(1) + ',' + referenceLab[2].toFixed(1) + ')';
  }

  function processFrame() {
    if (!procCanvas.width || videoEl.readyState < 2) return;

    procCtx.drawImage(videoEl, 0, 0, procCanvas.width, procCanvas.height);
    overlayCtx.clearRect(0, 0, overlayCanvas.width, overlayCanvas.height);

    if (referenceLab) {
      drawTileGrid();
    }
    drawCrosshair();
  }

  function drawTileGrid() {
    const pw = procCanvas.width;
    const ph = procCanvas.height;
    const data = procCtx.getImageData(0, 0, pw, ph).data;
    const scaleX = overlayCanvas.width / pw;
    const scaleY = overlayCanvas.height / ph;

    overlayCtx.save();
    for (let ty = 0; ty < ph; ty += TILE_SIZE) {
      const tileH = Math.min(TILE_SIZE, ph - ty);
      for (let tx = 0; tx < pw; tx += TILE_SIZE) {
        const tileW = Math.min(TILE_SIZE, pw - tx);
        const rgb = medianRgbOfTile(data, pw, tx, ty, tileW, tileH);
        const lab = srgbToLab(rgb[0], rgb[1], rgb[2]);
        const dAb = deltaAb(referenceLab, lab);
        const isDiff = dAb >= DELTA_AB_THRESHOLD;
        overlayCtx.fillStyle = isDiff ? 'rgba(255,50,40,0.4)' : 'rgba(40,200,80,0.28)';
        overlayCtx.fillRect(tx * scaleX, ty * scaleY, tileW * scaleX, tileH * scaleY);
      }
    }
    overlayCtx.restore();
  }

  function drawCrosshair() {
    const cx = overlayCanvas.width / 2;
    const cy = overlayCanvas.height / 2;
    const size = 26;
    overlayCtx.save();
    overlayCtx.strokeStyle = '#fff';
    overlayCtx.lineWidth = 2;
    overlayCtx.shadowColor = 'rgba(0,0,0,0.7)';
    overlayCtx.shadowBlur = 3;
    overlayCtx.strokeRect(cx - size / 2, cy - size / 2, size, size);
    overlayCtx.beginPath();
    overlayCtx.moveTo(cx - size / 2 - 6, cy);
    overlayCtx.lineTo(cx - size / 2, cy);
    overlayCtx.moveTo(cx + size / 2, cy);
    overlayCtx.lineTo(cx + size / 2 + 6, cy);
    overlayCtx.moveTo(cx, cy - size / 2 - 6);
    overlayCtx.lineTo(cx, cy - size / 2);
    overlayCtx.moveTo(cx, cy + size / 2);
    overlayCtx.lineTo(cx, cy + size / 2 + 6);
    overlayCtx.stroke();
    overlayCtx.restore();
  }

  // --- 既存ロジック(panel-color-diff.php/backend/ColorDiff.php)の考え方を流用 ---
  // 中央値サンプリング・sRGB→Lab変換・Δab(a,bのみのユークリッド距離)

  function medianRgbOfTile(data, rowWidth, tx, ty, tileW, tileH) {
    const rs = [], gs = [], bs = [];
    for (let py = 0; py < tileH; py += SAMPLE_STRIDE) {
      for (let px = 0; px < tileW; px += SAMPLE_STRIDE) {
        const idx = ((ty + py) * rowWidth + (tx + px)) * 4;
        rs.push(data[idx]);
        gs.push(data[idx + 1]);
        bs.push(data[idx + 2]);
      }
    }
    return [median(rs), median(gs), median(bs)];
  }

  function median(values) {
    if (values.length === 0) return 0;
    const sorted = values.slice().sort(function (a, b) { return a - b; });
    const n = sorted.length;
    const mid = Math.floor(n / 2);
    return n % 2 === 0 ? (sorted[mid - 1] + sorted[mid]) / 2 : sorted[mid];
  }

  function srgbToLab(r, g, b) {
    const linearize = function (c) {
      c = c / 255;
      return c <= 0.04045 ? c / 12.92 : Math.pow((c + 0.055) / 1.055, 2.4);
    };
    const rl = linearize(r), gl = linearize(g), bl = linearize(b);

    const x = rl * 0.4124564 + gl * 0.3575761 + bl * 0.1804375;
    const y = rl * 0.2126729 + gl * 0.7151522 + bl * 0.0721750;
    const z = rl * 0.0193339 + gl * 0.1191920 + bl * 0.9503041;

    const xn = 0.95047, yn = 1.0, zn = 1.08883;
    const f = function (t) {
      const delta = 6 / 29;
      return t > Math.pow(delta, 3) ? Math.pow(t, 1 / 3) : (t / (3 * delta * delta) + 4 / 29);
    };
    const fx = f(x / xn), fy = f(y / yn), fz = f(z / zn);

    const L = 116 * fy - 16;
    const a = 500 * (fx - fy);
    const bLab = 200 * (fy - fz);
    return [L, a, bLab];
  }

  function deltaAb(lab1, lab2) {
    const da = lab2[1] - lab1[1];
    const db = lab2[2] - lab1[2];
    return Math.sqrt(da * da + db * db);
  }
})();
</script>
</body>
</html>

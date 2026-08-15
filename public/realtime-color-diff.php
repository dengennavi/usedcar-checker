<?php
// ④パネル色差判定 - リアルタイム試作(自動基準色版)
// タップで基準色を指定するのではなく、画面内タイルの色の中央値(=多数派の色)を
// フレームごとに動的に算出して基準色とし、そこから大きく外れたタイルだけを
// ハイライトする「懐中電灯」的な使い方を想定。
// 気になる箇所は静止画として撮影し、panel-color-diff.php(2点比較機能)へ引き継ぐ。
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
  }
  /* ガイド枠の位置(left/top/right/bottom)はJS側のGUIDE_X_RATIO/GUIDE_Y_RATIOと
     一致させること。ここは表示専用、タイル計算の対象範囲はJS側で制御している。 */
  .guide-frame {
    position: absolute;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    pointer-events: none;
  }
  .guide-box {
    position: absolute;
    left: 10%;
    right: 10%;
    top: 18%;
    bottom: 18%;
    border: 2px solid rgba(255,255,255,0.9);
    border-radius: 8px;
    /* 枠の外側全体を暗くする(枠内に注目を集める) */
    box-shadow: 0 0 0 9999px rgba(0,0,0,0.55);
  }
  .guide-label {
    position: absolute;
    left: 50%;
    top: calc(18% - 24px);
    transform: translateX(-50%);
    margin: 0;
    color: #fff;
    font-size: 0.75rem;
    font-weight: bold;
    white-space: nowrap;
    text-shadow: 0 1px 3px rgba(0,0,0,0.9);
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
    margin: 0 0 10px;
    line-height: 1.5;
  }
  .legend {
    display: flex;
    gap: 14px;
    font-size: 0.75rem;
    color: #555;
    margin-bottom: 12px;
  }
  .legend span.dot {
    display: inline-block;
    width: 10px;
    height: 10px;
    border-radius: 2px;
    margin-right: 4px;
    vertical-align: middle;
  }
  #investigateBtn {
    width: 100%;
    padding: 14px;
    border: none;
    border-radius: 8px;
    background: #ff3b30;
    color: #fff;
    font-size: 1rem;
    font-weight: bold;
    cursor: pointer;
    margin-bottom: 8px;
  }
  #investigateBtn:disabled {
    background: #ccc;
    cursor: not-allowed;
  }
  #stopCameraBtn {
    width: 100%;
    padding: 10px;
    border: 1px solid #ccc;
    border-radius: 8px;
    background: #f2f2f4;
    font-size: 0.85rem;
    cursor: pointer;
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
    <div class="guide-frame">
      <p class="guide-label">車体をこの枠内に収めてください</p>
      <div class="guide-box"></div>
    </div>
  </div>

  <div class="controls" id="controls" hidden>
    <p class="hint-text">
      画面に映る範囲の中で最も多い色を自動的に基準色とし、そこから大きく外れた場所だけを赤くハイライトします。
      車体に沿ってゆっくりカメラを動かしてください。気になる箇所が見つかったら撮影して詳しく調べられます。
    </p>
    <div class="legend">
      <span><span class="dot" style="background:rgba(255,40,30,0.9)"></span>周囲と異なる色（要確認）</span>
    </div>
    <button type="button" id="investigateBtn" disabled>この場所を詳しく調べる（撮影する）</button>
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
  const HANDOFF_STORAGE_KEY = 'usedcarChecker.realtimeHandoffPhoto';

  // ガイド枠(車体をこの中に収めてもらう範囲)。CSSの.guide-box(left/right:10%, top/bottom:18%)と
  // 必ず一致させること。枠外は生垣・地面等の背景が映り込みやすく、面積が広いと背景色が
  // 「多数派の色」として誤って基準色に選ばれてしまう(実機で車体全体が誤ハイライトされる不具合が発生)。
  // 基準色の算出・ハイライト判定とも、この枠内のタイルだけを対象にすることで防ぐ。
  const GUIDE_X_RATIO = 0.10; // 左右の除外幅の割合
  const GUIDE_Y_RATIO = 0.18; // 上下の除外幅の割合

  const videoEl = document.getElementById('video');
  const overlayCanvas = document.getElementById('overlayCanvas');
  const overlayCtx = overlayCanvas.getContext('2d');
  const startCameraBtn = document.getElementById('startCameraBtn');
  const stopCameraBtn = document.getElementById('stopCameraBtn');
  const investigateBtn = document.getElementById('investigateBtn');
  const cameraStatusEl = document.getElementById('cameraStatus');
  const startStepEl = document.getElementById('startStep');
  const videoWrapEl = document.getElementById('videoWrap');
  const controlsEl = document.getElementById('controls');

  // 実処理(中央値サンプリング等)はオフスクリーンの縮小canvas上で行う
  const procCanvas = document.createElement('canvas');
  const procCtx = procCanvas.getContext('2d', { willReadFrequently: true });

  let stream = null;
  let processTimer = null;

  startCameraBtn.addEventListener('click', startCamera);
  stopCameraBtn.addEventListener('click', stopCamera);
  investigateBtn.addEventListener('click', captureAndHandoff);

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
    investigateBtn.disabled = false;
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
    investigateBtn.disabled = true;
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

  function processFrame() {
    if (!procCanvas.width || videoEl.readyState < 2) return;

    procCtx.drawImage(videoEl, 0, 0, procCanvas.width, procCanvas.height);
    overlayCtx.clearRect(0, 0, overlayCanvas.width, overlayCanvas.height);

    const tiles = computeTiles();
    if (tiles.length === 0) return;

    // その瞬間の画面内タイルの色の中央値(=多数派の色)を動的な基準色とする
    const refA = median(tiles.map(function (t) { return t.lab[1]; }));
    const refB = median(tiles.map(function (t) { return t.lab[2]; }));
    const referenceLab = [0, refA, refB]; // Lは判定に使わないのでダミー

    highlightOutlierTiles(tiles, referenceLab);
  }

  function computeTiles() {
    const pw = procCanvas.width;
    const ph = procCanvas.height;
    const data = procCtx.getImageData(0, 0, pw, ph).data;

    // ガイド枠内(procCanvas座標系)のみを対象にする。枠外の背景タイルはそもそも
    // 生成しないため、基準色の算出にもハイライト判定にも一切影響しない。
    const guideX0 = Math.round(pw * GUIDE_X_RATIO);
    const guideY0 = Math.round(ph * GUIDE_Y_RATIO);
    const guideX1 = Math.round(pw * (1 - GUIDE_X_RATIO));
    const guideY1 = Math.round(ph * (1 - GUIDE_Y_RATIO));

    const tiles = [];
    for (let ty = guideY0; ty < guideY1; ty += TILE_SIZE) {
      const tileH = Math.min(TILE_SIZE, guideY1 - ty);
      for (let tx = guideX0; tx < guideX1; tx += TILE_SIZE) {
        const tileW = Math.min(TILE_SIZE, guideX1 - tx);
        const rgb = medianRgbOfTile(data, pw, tx, ty, tileW, tileH);
        const lab = srgbToLab(rgb[0], rgb[1], rgb[2]);
        tiles.push({ x: tx, y: ty, w: tileW, h: tileH, lab: lab });
      }
    }
    return tiles;
  }

  function highlightOutlierTiles(tiles, referenceLab) {
    const pw = procCanvas.width;
    const ph = procCanvas.height;
    const scaleX = overlayCanvas.width / pw;
    const scaleY = overlayCanvas.height / ph;

    overlayCtx.save();
    tiles.forEach(function (tile) {
      const dAb = deltaAb(referenceLab, tile.lab);
      if (dAb < DELTA_AB_THRESHOLD) return; // 基準色に近いタイルは何も描かない(見た目そのまま)

      const rx = tile.x * scaleX;
      const ry = tile.y * scaleY;
      const rw = tile.w * scaleX;
      const rh = tile.h * scaleY;
      overlayCtx.fillStyle = 'rgba(255,40,30,0.45)';
      overlayCtx.fillRect(rx, ry, rw, rh);
      overlayCtx.strokeStyle = 'rgba(255,255,255,0.8)';
      overlayCtx.lineWidth = 1;
      overlayCtx.strokeRect(rx, ry, rw, rh);
    });
    overlayCtx.restore();
  }

  function captureAndHandoff() {
    if (!stream || videoEl.readyState < 2) return;

    const captureCanvas = document.createElement('canvas');
    captureCanvas.width = videoEl.videoWidth;
    captureCanvas.height = videoEl.videoHeight;
    captureCanvas.getContext('2d').drawImage(videoEl, 0, 0, captureCanvas.width, captureCanvas.height);
    const dataUrl = captureCanvas.toDataURL('image/jpeg', 0.92);

    try {
      sessionStorage.setItem(HANDOFF_STORAGE_KEY, dataUrl);
    } catch (err) {
      cameraStatusEl.textContent = '写真の受け渡しに失敗しました: ' + err.message;
      return;
    }

    window.location.href = 'panel-color-diff.php?from=realtime';
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

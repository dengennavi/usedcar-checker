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
  .low-reliability-warning {
    margin-top: 12px;
    padding: 12px 14px;
    border-radius: 8px;
    background: #f3ecff;
    border: 2px solid #7a3ff0;
    color: #4a1f99;
    font-size: 0.88rem;
    font-weight: bold;
    line-height: 1.5;
  }
  .heatmap-bar {
    display: flex;
    align-items: center;
    justify-content: space-between;
    flex-wrap: wrap;
    gap: 8px;
    margin-bottom: 10px;
    font-size: 0.8rem;
    color: #444;
  }
  .heatmap-toggle {
    display: flex;
    align-items: center;
    gap: 6px;
  }
  .heatmap-legend {
    display: flex;
    align-items: center;
    gap: 6px;
  }
  .heatmap-legend .bar {
    width: 90px;
    height: 10px;
    border-radius: 5px;
    background: linear-gradient(to right, hsl(120,80%,50%), hsl(60,80%,50%), hsl(0,80%,50%));
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
  .consent-gate {
    background: #fff;
    border-radius: 12px;
    padding: 20px;
    box-shadow: 0 1px 4px rgba(0,0,0,0.08);
  }
  .consent-text {
    font-size: 0.9rem;
    line-height: 1.6;
    color: #333;
    margin: 0 0 16px;
  }
  .consent-checkbox {
    display: flex;
    align-items: flex-start;
    gap: 8px;
    font-size: 0.9rem;
    margin-bottom: 16px;
    cursor: pointer;
  }
  .consent-checkbox input {
    margin-top: 3px;
  }
  #consentAgreeBtn {
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
  #consentAgreeBtn:disabled {
    background: #ccc;
    cursor: not-allowed;
  }
  .product-recommendation {
    margin-top: 12px;
    padding: 10px 12px;
    border-radius: 8px;
    background: #f2f2f4;
    border: 1px solid #ddd;
    color: #333;
    font-size: 0.8rem;
    line-height: 1.5;
  }
  .product-recommendation a {
    color: #0a7cff;
  }
  .feedback-section {
    margin-top: 16px;
    padding-top: 14px;
    border-top: 1px solid #e5e5ea;
  }
  .feedback-question {
    font-size: 0.85rem;
    color: #444;
    margin: 0 0 8px;
  }
  .feedback-buttons {
    display: flex;
    gap: 8px;
    margin-bottom: 6px;
  }
  .feedback-buttons button {
    flex: 1;
    padding: 9px 8px;
    border: 1px solid #ccc;
    border-radius: 8px;
    background: #fff;
    font-size: 0.8rem;
    cursor: pointer;
  }
  .feedback-buttons button.selected {
    background: #0a7cff;
    border-color: #0a7cff;
    color: #fff;
  }
  .feedback-buttons button:disabled {
    cursor: not-allowed;
    opacity: 0.7;
  }
  .feedback-thanks {
    font-size: 0.78rem;
    color: #1c6b3a;
    margin: 4px 0 0;
  }
  .feedback-freetext {
    margin-top: 14px;
  }
  .freetext-label {
    display: block;
    font-size: 0.78rem;
    color: #555;
    line-height: 1.5;
    margin: 0 0 6px;
  }
  #feedbackFreeText {
    width: 100%;
    resize: vertical;
    padding: 8px;
    border: 1px solid #ccc;
    border-radius: 8px;
    font-size: 0.85rem;
    font-family: inherit;
    margin-bottom: 6px;
  }
  #feedbackFreeTextSubmitBtn {
    padding: 8px 16px;
    border: 1px solid #ccc;
    border-radius: 8px;
    background: #f2f2f4;
    font-size: 0.8rem;
    cursor: pointer;
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

  <div class="consent-gate" id="consentGate">
    <p class="consent-text">
      このアプリの判定結果は参考情報です。最終的な購入判断は、専門家による点検やご自身の目視確認と合わせて行ってください。
    </p>
    <label class="consent-checkbox">
      <input type="checkbox" id="consentCheckbox">
      内容を理解し、同意します
    </label>
    <button type="button" id="consentAgreeBtn" disabled>同意して利用を開始する</button>
  </div>

  <div id="appContent" hidden>
  <div class="step">
    <label class="file-btn">
      写真を選択
      <input type="file" id="photoInput" accept="image/*" capture="environment">
    </label>
  </div>

  <div class="canvas-wrap" id="canvasWrap" hidden>
    <p class="selection-guidance" id="handoffNotice" hidden>
      リアルタイム検知から引き継いだ写真です。気になる箇所と、比較用のフラットな箇所の2点を矩形で選択してください。
    </p>
    <p class="selection-guidance">
      空や周囲の景色が映り込んでいない、フラットに見える塗装面を選んでください（ボンネットの端やドアの中央付近がおすすめです）。
    </p>
    <p class="selection-guidance">
      同じ材質のパネル同士（ドアとフェンダー、ボンネットとルーフなど）を比較してください。バンパーは樹脂製で、ボディの金属パネルとは元々塗装が異なるため、比較には適しません。
    </p>

    <div class="heatmap-bar">
      <label class="heatmap-toggle">
        <input type="checkbox" id="heatmapToggle" checked>
        映り込みガイドを表示
      </label>
      <div class="heatmap-legend">
        <span>フラット</span>
        <span class="bar"></span>
        <span>映り込みの可能性</span>
      </div>
    </div>
    <p class="selection-guidance" id="heatmapStatus" hidden>ヒートマップを計算中...</p>

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
    <div id="lowReliabilityWarning" class="low-reliability-warning" hidden></div>

    <div id="resultSummary" hidden>
      <p class="verdict-title" id="verdictTitle"></p>
      <p class="verdict-de" id="verdictDe"></p>
      <div class="panel-values">
        <div>パネルA: <span id="valA"></span></div>
        <div>パネルB: <span id="valB"></span></div>
      </div>
      <p class="product-recommendation" id="productRecommendation" hidden>
        より確実に判断したい場合は、市販の<a href="#" id="productRecommendationLink">磁石式膜厚計・チップテスター</a>で直接触って確認することをおすすめします。
      </p>

      <div class="feedback-section" id="feedbackSection" hidden>
        <p class="feedback-question">この判定は参考になりましたか？（任意）</p>
        <div class="feedback-buttons">
          <button type="button" id="feedbackHelpfulBtn" data-value="helpful">参考になった</button>
          <button type="button" id="feedbackNotHelpfulBtn" data-value="not_helpful">あまり参考にならなかった</button>
        </div>
        <p class="feedback-thanks" id="feedbackThanks" hidden>フィードバックありがとうございました。</p>

        <div class="feedback-freetext">
          <label class="freetext-label" for="feedbackFreeText">
            もし後日、修復歴の有無が判明しましたら（販売店の書面、膜厚計での確認など）、下記にご記入いただけると精度向上の参考になります。（任意）
          </label>
          <textarea id="feedbackFreeText" rows="3" placeholder="例: 販売店の説明書に「右フロントフェンダー修復歴あり」と記載されていた、など"></textarea>
          <div>
            <button type="button" id="feedbackFreeTextSubmitBtn">送信</button>
          </div>
          <p class="feedback-thanks" id="feedbackFreeTextThanks" hidden>ご記入ありがとうございました。</p>
        </div>
      </div>
    </div>
    <details id="rawResult" hidden>
      <summary>詳細データ(JSON)を表示</summary>
      <pre id="result"></pre>
    </details>
  </div>
  </div>
</div>

<script>
(function () {
  const MAX_CANVAS_WIDTH = 900;

  // 初回利用時の同意ゲート。同意するまで機能(写真アップロード以降)を使わせない。
  // 一度同意すればlocalStorageに記録し、次回以降は省略する。
  // 文言を変更した場合は再同意を求めたいのでキーにバージョンを付けている。
  const CONSENT_STORAGE_KEY = 'usedcarChecker.panelColorDiff.consent.v1';
  const consentGateEl = document.getElementById('consentGate');
  const appContentEl = document.getElementById('appContent');
  const consentCheckboxEl = document.getElementById('consentCheckbox');
  const consentAgreeBtnEl = document.getElementById('consentAgreeBtn');

  function hasConsent() {
    try {
      return localStorage.getItem(CONSENT_STORAGE_KEY) === '1';
    } catch (e) {
      // プライベートブラウジング等でlocalStorageが使えない場合は毎回同意を求める
      return false;
    }
  }

  function unlockApp() {
    consentGateEl.hidden = true;
    appContentEl.hidden = false;
    tryLoadHandoffPhoto();
  }

  consentCheckboxEl.addEventListener('change', function () {
    consentAgreeBtnEl.disabled = !consentCheckboxEl.checked;
  });

  consentAgreeBtnEl.addEventListener('click', function () {
    try {
      localStorage.setItem(CONSENT_STORAGE_KEY, '1');
    } catch (e) {
      // 保存に失敗しても今回の利用自体は継続させる(次回また同意を求めるだけ)
    }
    unlockApp();
  });

  const photoInput = document.getElementById('photoInput');
  const canvasWrap = document.getElementById('canvasWrap');
  const handoffNoticeEl = document.getElementById('handoffNotice');
  const canvas = document.getElementById('canvas');
  const ctx = canvas.getContext('2d');
  const modeAbtn = document.getElementById('modeA');
  const modeBbtn = document.getElementById('modeB');
  const resetBtn = document.getElementById('resetBtn');
  const submitBtn = document.getElementById('submitBtn');
  const resultEl = document.getElementById('result');
  const rawResultEl = document.getElementById('rawResult');
  const reflectionWarningEl = document.getElementById('reflectionWarning');
  const lowReliabilityWarningEl = document.getElementById('lowReliabilityWarning');
  const resultSummaryEl = document.getElementById('resultSummary');
  const verdictTitleEl = document.getElementById('verdictTitle');
  const verdictDeEl = document.getElementById('verdictDe');
  const valAEl = document.getElementById('valA');
  const valBEl = document.getElementById('valB');
  const productRecommendationEl = document.getElementById('productRecommendation');
  const productRecommendationLinkEl = document.getElementById('productRecommendationLink');
  // TODO: 推奨商品(磁石式膜厚計・チップテスター)のリンク先が決まったらhrefを設定する
  productRecommendationLinkEl.addEventListener('click', function (e) {
    e.preventDefault();
  });
  const swatchA = document.getElementById('swatchA');
  const swatchB = document.getElementById('swatchB');
  const rgbAEl = document.getElementById('rgbA');
  const rgbBEl = document.getElementById('rgbB');

  // 匿名フィードバック(判定のたびに自動ログ + 任意の主観フィードバック/自由記述)
  const feedbackSectionEl = document.getElementById('feedbackSection');
  const feedbackHelpfulBtn = document.getElementById('feedbackHelpfulBtn');
  const feedbackNotHelpfulBtn = document.getElementById('feedbackNotHelpfulBtn');
  const feedbackThanksEl = document.getElementById('feedbackThanks');
  const feedbackFreeTextEl = document.getElementById('feedbackFreeText');
  const feedbackFreeTextSubmitBtn = document.getElementById('feedbackFreeTextSubmitBtn');
  const feedbackFreeTextThanksEl = document.getElementById('feedbackFreeTextThanks');
  let currentFeedbackToken = null; // log-result.phpが発行する、このログ行だけを指す使い捨てトークン

  function resetFeedbackUi() {
    currentFeedbackToken = null;
    feedbackSectionEl.hidden = true;
    feedbackThanksEl.hidden = true;
    feedbackFreeTextThanksEl.hidden = true;
    feedbackFreeTextEl.value = '';
    feedbackHelpfulBtn.disabled = false;
    feedbackNotHelpfulBtn.disabled = false;
    feedbackHelpfulBtn.classList.remove('selected');
    feedbackNotHelpfulBtn.classList.remove('selected');
  }

  async function logResultAndShowFeedback(data) {
    try {
      const res = await fetch('api/log-result.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
          verdict: data.verdict,
          deltaAb: data.deltaAb,
          deltaE: data.deltaE,
          lowReliabilityWarning: !!data.lowReliabilityWarning
        })
      });
      const logData = await res.json();
      if (logData.ok) {
        currentFeedbackToken = logData.token;
        feedbackSectionEl.hidden = false;
      }
    } catch (e) {
      // 匿名ログの送信に失敗しても、判定結果の表示自体には影響させない
    }
  }

  function submitSubjectiveFeedback(value, clickedBtn) {
    if (!currentFeedbackToken) return;
    feedbackHelpfulBtn.disabled = true;
    feedbackNotHelpfulBtn.disabled = true;
    clickedBtn.classList.add('selected');
    feedbackThanksEl.hidden = false;

    fetch('api/submit-feedback.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ token: currentFeedbackToken, subjectiveFeedback: value })
    }).catch(function () {
      // 送信に失敗してもUI上は完了扱いのままにする(再送の仕組みは試作段階では省略)
    });
  }

  feedbackHelpfulBtn.addEventListener('click', function () {
    submitSubjectiveFeedback('helpful', feedbackHelpfulBtn);
  });
  feedbackNotHelpfulBtn.addEventListener('click', function () {
    submitSubjectiveFeedback('not_helpful', feedbackNotHelpfulBtn);
  });

  feedbackFreeTextSubmitBtn.addEventListener('click', function () {
    if (!currentFeedbackToken) return;
    const text = feedbackFreeTextEl.value.trim();
    if (!text) return;

    feedbackFreeTextThanksEl.hidden = false;
    fetch('api/submit-feedback.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ token: currentFeedbackToken, freeText: text })
    }).catch(function () {
      // 送信に失敗してもUI上は完了扱いのままにする(再送の仕組みは試作段階では省略)
    });
  });
  const heatmapToggleEl = document.getElementById('heatmapToggle');
  const heatmapStatusEl = document.getElementById('heatmapStatus');

  // 元画像をオフスクリーンで保持し、オーバーレイ(枠線)に汚染されない平均RGB計算に使う
  const imgCanvas = document.createElement('canvas');
  const imgCtx = imgCanvas.getContext('2d');

  // 映り込みガイド(ヒートマップ)の設定。矩形選択前の段階で、画像をタイルに分割し
  // タイルごとの明度(L*)標準偏差を計算して色付け表示する(緑=フラット、赤=映り込みの可能性)。
  // 事後の警告(サーバー側、選択範囲全体のL*標準偏差)とは別の、選択前ガイド用の指標。
  const HEATMAP_TILE_SIZE = 30; // px (表示解像度のcanvas上でのタイルサイズ)
  const HEATMAP_SAMPLE_STRIDE = 3; // タイル内はこの間隔でサンプリング(スマホの負荷対策)
  const HEATMAP_MAX_STDDEV = 10; // これ以上で最も赤色。タイルは小さく分散が出やすいためやや高め

  let img = new Image();
  let currentFile = null; // サーバーへ送る元ファイル(File)
  let scale = 1; // 元画像px * scale = canvas表示px
  let mode = 'A';
  let rects = { A: null, B: null }; // 元画像ピクセル座標 {x,y,w,h}
  let heatmapTiles = []; // [{x,y,w,h,stdDev}, ...] (canvas座標系)
  let heatmapEnabled = true;
  let drawing = false;
  let dragStart = null;
  let currentDragRectCanvas = null; // ドラッグ中プレビュー(canvas座標系)

  photoInput.addEventListener('change', function (e) {
    const file = e.target.files[0];
    if (!file) return;
    currentFile = file;
    handoffNoticeEl.hidden = true;
    const reader = new FileReader();
    reader.onload = function (ev) {
      img = new Image();
      img.onload = setupCanvas;
      img.src = ev.target.result;
    };
    reader.readAsDataURL(file);
  });

  // リアルタイム検知(realtime-color-diff.php)から撮影写真を引き継いだ場合、
  // sessionStorageに置かれたdataURLを読み込んでファイル選択済みの状態にする。
  const HANDOFF_STORAGE_KEY = 'usedcarChecker.realtimeHandoffPhoto';

  function tryLoadHandoffPhoto() {
    let dataUrl = null;
    try {
      dataUrl = sessionStorage.getItem(HANDOFF_STORAGE_KEY);
    } catch (e) {
      return;
    }
    if (!dataUrl) return;

    try {
      sessionStorage.removeItem(HANDOFF_STORAGE_KEY);
    } catch (e) {
      // 削除に失敗しても致命的ではないため続行
    }

    handoffNoticeEl.hidden = false;

    dataUrlToFile(dataUrl, 'realtime-capture.jpg').then(function (file) {
      currentFile = file;
    }).catch(function () {
      // Fileへの変換に失敗しても、画像表示自体はdataURLで進められるので握りつぶす
    });

    img = new Image();
    img.onload = setupCanvas;
    img.src = dataUrl;
  }

  function dataUrlToFile(dataUrl, filename) {
    return fetch(dataUrl)
      .then(function (res) { return res.blob(); })
      .then(function (blob) { return new File([blob], filename, { type: blob.type || 'image/jpeg' }); });
  }

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
    lowReliabilityWarningEl.hidden = true;
    productRecommendationEl.hidden = true;
    resetFeedbackUi();
    canvasWrap.hidden = false;

    heatmapTiles = [];
    redraw(); // まず画像だけ即座に表示

    // ヒートマップ計算は画像の初期描画後に回す(体感速度優先)。縮小済みのimgCanvas上で行うため、
    // 元写真が高解像度でも計算量は画面表示サイズ相当に収まる。
    heatmapStatusEl.hidden = false;
    requestAnimationFrame(function () {
      heatmapTiles = computeHeatmapTiles();
      heatmapStatusEl.hidden = true;
      redraw();
    });
  }

  function computeHeatmapTiles() {
    const w = imgCanvas.width;
    const h = imgCanvas.height;
    const data = imgCtx.getImageData(0, 0, w, h).data;

    const tiles = [];
    for (let ty = 0; ty < h; ty += HEATMAP_TILE_SIZE) {
      const tileH = Math.min(HEATMAP_TILE_SIZE, h - ty);
      for (let tx = 0; tx < w; tx += HEATMAP_TILE_SIZE) {
        const tileW = Math.min(HEATMAP_TILE_SIZE, w - tx);

        const lValues = [];
        for (let py = 0; py < tileH; py += HEATMAP_SAMPLE_STRIDE) {
          for (let px = 0; px < tileW; px += HEATMAP_SAMPLE_STRIDE) {
            const idx = ((ty + py) * w + (tx + px)) * 4;
            lValues.push(srgbToL(data[idx], data[idx + 1], data[idx + 2]));
          }
        }

        let stdDev = 0;
        if (lValues.length > 1) {
          const mean = lValues.reduce(function (a, v) { return a + v; }, 0) / lValues.length;
          const variance = lValues.reduce(function (a, v) { return a + (v - mean) * (v - mean); }, 0) / lValues.length;
          stdDev = Math.sqrt(variance);
        }
        tiles.push({ x: tx, y: ty, w: tileW, h: tileH, stdDev: stdDev });
      }
    }
    return tiles;
  }

  // sRGB(0-255) -> CIE L*のみを計算する軽量版(a,bは映り込みガイドには不要なので省略)
  function srgbToL(r, g, b) {
    const linearize = function (c) {
      c = c / 255;
      return c <= 0.04045 ? c / 12.92 : Math.pow((c + 0.055) / 1.055, 2.4);
    };
    const y = linearize(r) * 0.2126729 + linearize(g) * 0.7151522 + linearize(b) * 0.0721750;
    const delta = 6 / 29;
    const fy = y > delta * delta * delta ? Math.pow(y, 1 / 3) : (y / (3 * delta * delta) + 4 / 29);
    return 116 * fy - 16;
  }

  function drawHeatmapTiles() {
    ctx.save();
    heatmapTiles.forEach(function (tile) {
      const t = Math.max(0, Math.min(1, tile.stdDev / HEATMAP_MAX_STDDEV));
      const hue = 120 - t * 120; // 緑(120=フラット) -> 赤(0=映り込みの可能性)
      ctx.fillStyle = 'hsla(' + hue.toFixed(0) + ', 80%, 50%, 0.35)';
      ctx.fillRect(tile.x, tile.y, tile.w, tile.h);
    });
    ctx.restore();
  }

  heatmapToggleEl.addEventListener('change', function () {
    heatmapEnabled = heatmapToggleEl.checked;
    redraw();
  });

  function redraw() {
    ctx.clearRect(0, 0, canvas.width, canvas.height);
    ctx.drawImage(imgCanvas, 0, 0);
    if (heatmapEnabled && heatmapTiles.length > 0) {
      drawHeatmapTiles();
    }
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
    lowReliabilityWarningEl.hidden = true;
    productRecommendationEl.hidden = true;
    resetFeedbackUi();
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
    lowReliabilityWarningEl.hidden = true;
    productRecommendationEl.hidden = true;
    resetFeedbackUi();

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
        lowReliabilityWarningEl.hidden = true;
        productRecommendationEl.hidden = true;
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
      lowReliabilityWarningEl.hidden = true;
      productRecommendationEl.hidden = true;
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

    // 白・シルバー・パール系の判定信頼性低下警告(判定結果=verdictとは別枠、強調表示)
    if (data.lowReliabilityWarning) {
      lowReliabilityWarningEl.hidden = false;
      lowReliabilityWarningEl.textContent = '⚠ ' + data.lowReliabilityWarning;
    } else {
      lowReliabilityWarningEl.hidden = true;
    }

    resultSummaryEl.hidden = false;
    resultSummaryEl.className = 'verdict-' + data.verdict;
    verdictTitleEl.textContent = VERDICT_LABELS[data.verdict] || data.message;
    verdictDeEl.textContent = 'Δab(色相・彩度差) = ' + data.deltaAb.toFixed(2) + '（閾値: ' + data.threshold.toFixed(1) + '）'
      + ' / 参考ΔE2000 = ' + data.deltaE.toFixed(2) + ' / ' + data.message;
    // 「要注意」「再塗装の可能性あり」の場合は、より確実な確認手段(膜厚計等)を案内する
    productRecommendationEl.hidden = !(data.verdict === 'caution' || data.verdict === 'repaint_suspected');
    valAEl.textContent = formatPanelValue(data.panelA);
    valBEl.textContent = formatPanelValue(data.panelB);

    // 判定が出るたびに匿名の利用ログを自動送信する(ユーザーへの質問なし)。
    // 成功すると主観フィードバック/自由記述の入力欄が使えるようになる。
    resetFeedbackUi();
    logResultAndShowFeedback(data);
  }

  function formatPanelValue(panel) {
    const [r, g, b] = panel.rgb;
    const [L, a, bLab] = panel.lab;
    return 'RGB(' + r + ',' + g + ',' + b + ') / Lab(' + L + ',' + a + ',' + bLab + ')';
  }

  // 同意済みなら即座にアプリを開放する。unlockApp()がtryLoadHandoffPhoto()経由で
  // img/currentFile/setupCanvasを参照するため、それらの宣言より後(スクリプト末尾)で呼ぶ必要がある。
  if (hasConsent()) {
    unlockApp();
  }
})();
</script>
</body>
</html>

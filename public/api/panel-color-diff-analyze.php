<?php
declare(strict_types=1);

// ④パネル色差判定（再塗装検出）
// 写真 + パネルA/Bの矩形範囲を受け取り、中央値RGB→Lab変換→Δab(a*b*平面距離)を計算して判定結果を返す。
// 平均ではなく中央値を使うのは、光沢塗装が空・建物を映り込ませた際の局所的な
// ハイライト(外れ値)に引っ張られにくくするため。
//
// 判定にΔE2000ではなくΔab(L成分を含まないa,bのみのユークリッド距離)を使う理由:
// 実車テストで、湾曲したパネルは太陽光の当たる角度差により同一塗装でも明度(L)だけが
// 大きくズレることがあり、ΔE2000だと誤判定（再塗装の可能性ありと表示）してしまうケースが
// 確認されたため。ΔE2000は参考値としてレスポンスに含める。

require __DIR__ . '/../../backend/ColorDiff.php';

header('Content-Type: application/json; charset=utf-8');

// 閾値はΔE2000運用時の"2.0"を暫定値として引き継いだもの。
// L成分を除いたことで数値の意味合いが変わるため、実車データが増え次第キャリブレーションが必要。
const DELTA_AB_THRESHOLD = 2.0; // これ以上で「再塗装の可能性あり」
const DELTA_AB_CAUTION = 1.0;   // これ以上で「要注意」
// 矩形内のL*標準偏差がこれを超えたら「映り込みが混ざっているかも」と警告する。
// 実車データが少ないため暫定値。フラットな塗装面なら数ポイント程度、
// 空や建物の映り込みが混ざると大きく跳ね上がる想定。
const L_STDDEV_WARNING_THRESHOLD = 8.0;
// 白・シルバー・パール系の判定信頼性低下の目安。実車テストで、白/シルバー系の車の
// 同一材質パネル同士でもΔab=11.94という誤判定が出たケースがあり、局所的には
// ヒートマップ上「フラット」判定でも、パネルごとに違う空の映り込み色を拾っていた。
// 高明度・低彩度の塗装では周囲の映り込み色がΔabを支配しやすいことが分かっている。
// これも暫定値であり、実車データが増え次第キャリブレーションが必要。
const WHITE_SILVER_L_THRESHOLD = 70.0;      // 平均Lがこれを超えたら高明度とみなす
const WHITE_SILVER_CHROMA_THRESHOLD = 15.0; // 平均クロマがこれ未満なら低彩度とみなす
const MAX_UPLOAD_BYTES = 15 * 1024 * 1024;
const ALLOWED_MIMES = ['image/jpeg', 'image/png', 'image/webp'];

function fail(string $message, int $httpStatus = 400): never
{
    http_response_code($httpStatus);
    echo json_encode(['ok' => false, 'error' => $message], JSON_UNESCAPED_UNICODE);
    exit;
}

/**
 * スマホ写真のEXIF Orientationを補正し、ブラウザ(Canvas)側の表示・座標系と一致させる。
 * ブラウザは<img>/Canvasへの描画時にEXIF Orientationを自動適用するが、GDは適用しないため。
 *
 * @param resource|\GdImage $im
 * @return resource|\GdImage
 */
function applyExifOrientation($im, string $path)
{
    $exif = @exif_read_data($path);
    if ($exif === false || !isset($exif['Orientation'])) {
        return $im;
    }

    switch ($exif['Orientation']) {
        case 2:
            imageflip($im, IMG_FLIP_HORIZONTAL);
            break;
        case 3:
            $im = imagerotate($im, 180, 0);
            break;
        case 4:
            imageflip($im, IMG_FLIP_VERTICAL);
            break;
        case 5:
            imageflip($im, IMG_FLIP_VERTICAL);
            $im = imagerotate($im, 90, 0);
            break;
        case 6:
            $im = imagerotate($im, -90, 0);
            break;
        case 7:
            imageflip($im, IMG_FLIP_VERTICAL);
            $im = imagerotate($im, -90, 0);
            break;
        case 8:
            $im = imagerotate($im, 90, 0);
            break;
    }

    return $im;
}

function parseRect(mixed $raw, string $label): array
{
    if (!is_string($raw)) {
        fail("{$label}が指定されていません");
    }
    $rect = json_decode($raw, true);
    if (!is_array($rect) || !isset($rect['x'], $rect['y'], $rect['w'], $rect['h'])
        || !is_numeric($rect['x']) || !is_numeric($rect['y']) || !is_numeric($rect['w']) || !is_numeric($rect['h'])) {
        fail("{$label} の形式が不正です");
    }
    return [
        'x' => (int) round((float) $rect['x']),
        'y' => (int) round((float) $rect['y']),
        'w' => (int) round((float) $rect['w']),
        'h' => (int) round((float) $rect['h']),
    ];
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    fail('POSTメソッドのみ対応しています', 405);
}

if (!isset($_FILES['photo']) || $_FILES['photo']['error'] !== UPLOAD_ERR_OK) {
    fail('写真のアップロードに失敗しました');
}

if ($_FILES['photo']['size'] > MAX_UPLOAD_BYTES) {
    fail('ファイルサイズが大きすぎます（上限15MB）');
}

$rectA = parseRect($_POST['rectA'] ?? null, 'パネルAの範囲');
$rectB = parseRect($_POST['rectB'] ?? null, 'パネルBの範囲');

$tmpPath = $_FILES['photo']['tmp_name'];

$imageInfo = @getimagesize($tmpPath);
if ($imageInfo === false) {
    fail('画像ファイルとして読み込めませんでした');
}
$mime = $imageInfo['mime'];
if (!in_array($mime, ALLOWED_MIMES, true)) {
    fail('対応していない画像形式です（JPEG/PNG/WebPのみ）');
}

$fileContents = file_get_contents($tmpPath);
$im = @imagecreatefromstring($fileContents);
if ($im === false) {
    fail('画像の読み込みに失敗しました');
}

if ($mime === 'image/jpeg') {
    $im = applyExifOrientation($im, $tmpPath);
}

$imgW = imagesx($im);
$imgH = imagesy($im);

foreach (['パネルA' => $rectA, 'パネルB' => $rectB] as $label => $rect) {
    if ($rect['x'] < 0 || $rect['y'] < 0 || $rect['x'] >= $imgW || $rect['y'] >= $imgH || $rect['w'] <= 0 || $rect['h'] <= 0) {
        fail("{$label}の座標が画像範囲外です（画像サイズ: {$imgW}x{$imgH}）");
    }
}

try {
    $sampleA = ColorDiff::sampleRegion($im, $rectA['x'], $rectA['y'], $rectA['w'], $rectA['h']);
    $sampleB = ColorDiff::sampleRegion($im, $rectB['x'], $rectB['y'], $rectB['w'], $rectB['h']);
} catch (Throwable $e) {
    fail('色のサンプリングに失敗しました: ' . $e->getMessage());
}

$rgbA = $sampleA['rgbMedian'];
$rgbB = $sampleB['rgbMedian'];

$labA = ColorDiff::srgbToLab($rgbA[0], $rgbA[1], $rgbA[2]);
$labB = ColorDiff::srgbToLab($rgbB[0], $rgbB[1], $rgbB[2]);

// 主判定: Δab（L非依存、色相・彩度のみ）
$deltaAb = ColorDiff::deltaAb($labA, $labB);
// 参考値: ΔE2000（Lの影響を含むため、湾曲パネルの陰影差で誤判定しうる）
$deltaE = ColorDiff::ciede2000($labA, $labB);

if ($deltaAb >= DELTA_AB_THRESHOLD) {
    $verdict = 'repaint_suspected';
    $message = '再塗装の可能性あり';
} elseif ($deltaAb >= DELTA_AB_CAUTION) {
    $verdict = 'caution';
    $message = 'わずかな色差があります（経年変化・光条件の影響の可能性もあり、目視でも確認してください）';
} else {
    $verdict = 'ok';
    $message = '明確な色差は検出されませんでした';
}

// 映り込み警告: 矩形内の明度(L*)ばらつきが大きい場合、選び直しを促す
$warnings = [];
foreach (['パネルA' => $sampleA, 'パネルB' => $sampleB] as $label => $sample) {
    if ($sample['lStdDev'] >= L_STDDEV_WARNING_THRESHOLD) {
        $warnings[] = "{$label}の選択範囲に反射や映り込みが含まれている可能性があります。塗装がフラットに見える場所を選び直してください。";
    }
}

// 白・シルバー・パール系警告: 高明度・低彩度の車は、パネル内がフラットに見えても
// パネルごとに違う空・周囲の映り込み色を拾いやすく、Δab判定そのものの信頼性が下がる。
// 判定結果(verdict)とは別枠の警告として返す。
$avgL = ($labA[0] + $labB[0]) / 2;
$avgChroma = (ColorDiff::chroma($labA) + ColorDiff::chroma($labB)) / 2;
$lowReliabilityWarning = null;
if ($avgL > WHITE_SILVER_L_THRESHOLD && $avgChroma < WHITE_SILVER_CHROMA_THRESHOLD) {
    $lowReliabilityWarning = 'この車は白・シルバー系のため、周囲の映り込みの影響が大きく、色差判定の精度が低下します。参考程度に留めてください。';
}

echo json_encode([
    'ok' => true,
    'deltaAb' => round($deltaAb, 3),
    'threshold' => DELTA_AB_THRESHOLD,
    'caution' => DELTA_AB_CAUTION,
    'deltaE' => round($deltaE, 3),
    'verdict' => $verdict,
    'message' => $message,
    'warnings' => $warnings,
    'lowReliabilityWarning' => $lowReliabilityWarning,
    'panelA' => [
        'rgb' => array_map(static fn($v) => round($v, 1), $rgbA),
        'lab' => array_map(static fn($v) => round($v, 2), $labA),
        'lStdDev' => round($sampleA['lStdDev'], 2),
    ],
    'panelB' => [
        'rgb' => array_map(static fn($v) => round($v, 1), $rgbB),
        'lab' => array_map(static fn($v) => round($v, 2), $labB),
        'lStdDev' => round($sampleB['lStdDev'], 2),
    ],
    'imageSize' => ['w' => $imgW, 'h' => $imgH],
], JSON_UNESCAPED_UNICODE);

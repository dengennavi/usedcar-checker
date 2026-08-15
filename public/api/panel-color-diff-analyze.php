<?php
declare(strict_types=1);

// ④パネル色差判定（再塗装検出）
// 写真 + パネルA/Bの矩形範囲を受け取り、平均RGB→Lab変換→ΔE2000を計算して判定結果を返す。

require __DIR__ . '/../../backend/ColorDiff.php';

header('Content-Type: application/json; charset=utf-8');

const DELTA_E_THRESHOLD = 2.0;  // これ以上で「再塗装の可能性あり」
const DELTA_E_CAUTION = 1.0;    // これ以上で「要注意」
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
    $rgbA = ColorDiff::averageRgbOfRegion($im, $rectA['x'], $rectA['y'], $rectA['w'], $rectA['h']);
    $rgbB = ColorDiff::averageRgbOfRegion($im, $rectB['x'], $rectB['y'], $rectB['w'], $rectB['h']);
} catch (Throwable $e) {
    fail('平均色の算出に失敗しました: ' . $e->getMessage());
}

$labA = ColorDiff::srgbToLab($rgbA[0], $rgbA[1], $rgbA[2]);
$labB = ColorDiff::srgbToLab($rgbB[0], $rgbB[1], $rgbB[2]);
$deltaE = ColorDiff::ciede2000($labA, $labB);

if ($deltaE >= DELTA_E_THRESHOLD) {
    $verdict = 'repaint_suspected';
    $message = '再塗装の可能性あり';
} elseif ($deltaE >= DELTA_E_CAUTION) {
    $verdict = 'caution';
    $message = 'わずかな色差があります（経年変化・光条件の影響の可能性もあり、目視でも確認してください）';
} else {
    $verdict = 'ok';
    $message = '明確な色差は検出されませんでした';
}

echo json_encode([
    'ok' => true,
    'deltaE' => round($deltaE, 3),
    'threshold' => DELTA_E_THRESHOLD,
    'verdict' => $verdict,
    'message' => $message,
    'panelA' => [
        'rgb' => array_map(static fn($v) => round($v, 1), $rgbA),
        'lab' => array_map(static fn($v) => round($v, 2), $labA),
    ],
    'panelB' => [
        'rgb' => array_map(static fn($v) => round($v, 1), $rgbB),
        'lab' => array_map(static fn($v) => round($v, 2), $labB),
    ],
    'imageSize' => ['w' => $imgW, 'h' => $imgH],
], JSON_UNESCAPED_UNICODE);

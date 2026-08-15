<?php
declare(strict_types=1);

// ④パネル色差判定の匿名利用ログを記録する。
// 判定が出るたびにフロントエンドから自動送信される(ユーザーへの質問なし)。
// 個人特定情報(写真そのもの、IPアドレス、位置情報等)は一切保存しない。
// 戻り値のtokenは、後続の主観フィードバック/自由記述(submit-feedback.php)を
// このログ行に紐付けるためだけに使う使い捨ての乱数(推測困難なランダムトークン)。

require __DIR__ . '/../../backend/FeedbackDb.php';

header('Content-Type: application/json; charset=utf-8');

const ALLOWED_VERDICTS = ['ok', 'caution', 'repaint_suspected'];

function fail(string $message, int $httpStatus = 400): never
{
    http_response_code($httpStatus);
    echo json_encode(['ok' => false, 'error' => $message], JSON_UNESCAPED_UNICODE);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    fail('POSTメソッドのみ対応しています', 405);
}

$body = json_decode(file_get_contents('php://input'), true);
if (!is_array($body)) {
    fail('リクエストボディが不正です');
}

$verdict = $body['verdict'] ?? null;
if (!is_string($verdict) || !in_array($verdict, ALLOWED_VERDICTS, true)) {
    fail('verdictの値が不正です');
}

$deltaAb = isset($body['deltaAb']) && is_numeric($body['deltaAb']) ? (float) $body['deltaAb'] : null;
$deltaE = isset($body['deltaE']) && is_numeric($body['deltaE']) ? (float) $body['deltaE'] : null;
$lowReliabilityWarning = !empty($body['lowReliabilityWarning']) ? 1 : 0;

$token = bin2hex(random_bytes(16));
$createdAt = gmdate('Y-m-d\TH:i:s\Z');

try {
    $pdo = FeedbackDb::connect();
    $stmt = $pdo->prepare('
        INSERT INTO feedback_logs (token, created_at, verdict, delta_ab, delta_e, low_reliability_warning)
        VALUES (:token, :created_at, :verdict, :delta_ab, :delta_e, :low_reliability_warning)
    ');
    $stmt->execute([
        ':token' => $token,
        ':created_at' => $createdAt,
        ':verdict' => $verdict,
        ':delta_ab' => $deltaAb,
        ':delta_e' => $deltaE,
        ':low_reliability_warning' => $lowReliabilityWarning,
    ]);
} catch (Throwable $e) {
    fail('ログの保存に失敗しました', 500);
}

echo json_encode(['ok' => true, 'token' => $token], JSON_UNESCAPED_UNICODE);

<?php
declare(strict_types=1);

// log-result.phpで発行されたtokenに対して、主観フィードバック(参考になった/ならなかった)や
// 自由記述(修復歴が後日判明した場合の任意入力)を追記する。どちらも完全に任意で、
// 別々のタイミングで送信されても既存の値を上書きしないよう指定されたフィールドのみ更新する。

require __DIR__ . '/../../backend/FeedbackDb.php';

header('Content-Type: application/json; charset=utf-8');

const ALLOWED_SUBJECTIVE_FEEDBACK = ['helpful', 'not_helpful'];
const FREE_TEXT_MAX_LENGTH = 2000;

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

$token = $body['token'] ?? null;
if (!is_string($token) || $token === '') {
    fail('tokenが指定されていません');
}

$subjectiveFeedback = $body['subjectiveFeedback'] ?? null;
if ($subjectiveFeedback !== null
    && (!is_string($subjectiveFeedback) || !in_array($subjectiveFeedback, ALLOWED_SUBJECTIVE_FEEDBACK, true))) {
    fail('subjectiveFeedbackの値が不正です');
}

$freeText = $body['freeText'] ?? null;
if ($freeText !== null && !is_string($freeText)) {
    fail('freeTextの値が不正です');
}
if (is_string($freeText)) {
    $freeText = mb_substr(trim($freeText), 0, FREE_TEXT_MAX_LENGTH);
    if ($freeText === '') {
        $freeText = null;
    }
}

if ($subjectiveFeedback === null && $freeText === null) {
    fail('subjectiveFeedbackまたはfreeTextのいずれかを指定してください');
}

try {
    $pdo = FeedbackDb::connect();

    $checkStmt = $pdo->prepare('SELECT id FROM feedback_logs WHERE token = :token');
    $checkStmt->execute([':token' => $token]);
    if ($checkStmt->fetch() === false) {
        fail('指定されたtokenのログが見つかりません', 404);
    }

    $sets = ['updated_at = :updated_at'];
    $params = [':token' => $token, ':updated_at' => gmdate('Y-m-d\TH:i:s\Z')];

    if ($subjectiveFeedback !== null) {
        $sets[] = 'subjective_feedback = :subjective_feedback';
        $params[':subjective_feedback'] = $subjectiveFeedback;
    }
    if ($freeText !== null) {
        $sets[] = 'free_text = :free_text';
        $params[':free_text'] = $freeText;
    }

    $sql = 'UPDATE feedback_logs SET ' . implode(', ', $sets) . ' WHERE token = :token';
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
} catch (Throwable $e) {
    fail('フィードバックの保存に失敗しました', 500);
}

echo json_encode(['ok' => true], JSON_UNESCAPED_UNICODE);

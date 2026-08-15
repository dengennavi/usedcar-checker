<?php
declare(strict_types=1);

// 管理者専用のフィードバック・利用ログ一覧。
// アクセス制御はこのディレクトリの.htaccess(Basic認証)で行う。

require __DIR__ . '/../../backend/FeedbackDb.php';

const DISPLAY_LIMIT = 500;

$pdo = FeedbackDb::connect();
$totalCount = (int) $pdo->query('SELECT COUNT(*) FROM feedback_logs')->fetchColumn();
$stmt = $pdo->prepare('SELECT * FROM feedback_logs ORDER BY created_at DESC LIMIT :limit');
$stmt->bindValue(':limit', DISPLAY_LIMIT, PDO::PARAM_INT);
$stmt->execute();
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

function h(?string $value): string
{
    return htmlspecialchars($value ?? '', ENT_QUOTES, 'UTF-8');
}

function verdictLabel(string $verdict): string
{
    return match ($verdict) {
        'repaint_suspected' => '再塗装の可能性あり',
        'caution' => '要注意',
        'ok' => '色差なし',
        default => $verdict,
    };
}

function feedbackLabel(?string $value): string
{
    return match ($value) {
        'helpful' => '参考になった',
        'not_helpful' => 'あまり参考にならなかった',
        default => '',
    };
}
?>
<!DOCTYPE html>
<html lang="ja">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>フィードバック一覧（管理者用） | usedcar-checker</title>
<style>
  * { box-sizing: border-box; }
  body {
    margin: 0;
    padding: 20px;
    font-family: -apple-system, BlinkMacSystemFont, "Hiragino Sans", "Yu Gothic", sans-serif;
    background: #f5f5f7;
    color: #1c1c1e;
  }
  h1 { font-size: 1.2rem; margin: 0 0 4px; }
  .meta { font-size: 0.85rem; color: #555; margin: 0 0 16px; }
  .table-wrap {
    overflow-x: auto;
    background: #fff;
    border-radius: 8px;
    box-shadow: 0 1px 4px rgba(0,0,0,0.08);
  }
  table { border-collapse: collapse; width: 100%; font-size: 0.8rem; }
  th, td {
    padding: 8px 10px;
    border-bottom: 1px solid #eee;
    text-align: left;
    vertical-align: top;
    white-space: nowrap;
  }
  td.free-text { white-space: pre-wrap; max-width: 320px; }
  th { background: #fafafa; position: sticky; top: 0; }
  tr:hover td { background: #fbfbfd; }
  .verdict-repaint_suspected { color: #b8231a; font-weight: bold; }
  .verdict-caution { color: #8a6300; }
  .verdict-ok { color: #1c6b3a; }
  .badge-warn {
    display: inline-block;
    background: #f3ecff;
    color: #4a1f99;
    border-radius: 4px;
    padding: 1px 6px;
    font-size: 0.75rem;
  }
</style>
</head>
<body>
<h1>フィードバック一覧（管理者用）</h1>
<p class="meta">
  全<?= $totalCount ?>件中、新しい順に最大<?= DISPLAY_LIMIT ?>件を表示しています。
  個人特定情報(写真・IPアドレス・位置情報等)は保存していません。日時はUTCです。
</p>

<div class="table-wrap">
<table>
<thead>
<tr>
  <th>日時 (UTC)</th>
  <th>判定</th>
  <th>Δab</th>
  <th>ΔE2000</th>
  <th>白/シルバー警告</th>
  <th>主観フィードバック</th>
  <th>自由記述</th>
</tr>
</thead>
<tbody>
<?php foreach ($rows as $row): ?>
<tr>
  <td><?= h($row['created_at']) ?></td>
  <td class="verdict-<?= h($row['verdict']) ?>"><?= h(verdictLabel($row['verdict'])) ?></td>
  <td><?= $row['delta_ab'] !== null ? h(number_format((float) $row['delta_ab'], 3)) : '-' ?></td>
  <td><?= $row['delta_e'] !== null ? h(number_format((float) $row['delta_e'], 3)) : '-' ?></td>
  <td><?= $row['low_reliability_warning'] ? '<span class="badge-warn">あり</span>' : '' ?></td>
  <td><?= h(feedbackLabel($row['subjective_feedback'])) ?></td>
  <td class="free-text"><?= h($row['free_text']) ?></td>
</tr>
<?php endforeach; ?>
<?php if (empty($rows)): ?>
<tr><td colspan="7">まだログがありません。</td></tr>
<?php endif; ?>
</tbody>
</table>
</div>
</body>
</html>

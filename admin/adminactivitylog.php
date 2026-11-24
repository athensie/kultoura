<?php
require_once __DIR__ . '/../config/session_boot.php';
define('BASE_URL', '/kultoura');
if (!isset($_SESSION['user_id']) || !in_array(strtolower($_SESSION['role'] ?? ''), ['admin', 'super admin'], true)) {
    header('Location: ' . BASE_URL . '/index.php');
    exit;
}
require_once __DIR__ . '/../config/dbmain.php';
require_once __DIR__ . '/../config/activity.php';
kt_activity_ensure_schema($conn);
$categories = kt_activity_categories();
$parents = kt_activity_parents();
$activityTypes = ['view', 'click', 'search', 'generate', 'download', 'rating', 'review', 'comment', 'recommendation', 'map_interaction', 'language', 'other'];
$filters = [
    'type' => in_array($_GET['type'] ?? '', $activityTypes, true) ? (string) $_GET['type'] : '',
    'category' => isset($categories[$_GET['category'] ?? '']) ? (string) $_GET['category'] : '',
    'parent' => isset($parents[$_GET['parent'] ?? '']) ? (string) $_GET['parent'] : '',
    'user' => mb_substr(trim((string) ($_GET['user'] ?? '')), 0, 80),
    'from' => preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) ($_GET['from'] ?? '')) ? (string) $_GET['from'] : '',
    'to' => preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) ($_GET['to'] ?? '')) ? (string) $_GET['to'] : '',
];
$page = max(1, (int) ($_GET['page'] ?? 1));
$perPage = 50;
$sortOptions = ['activity_id' => 'a.activity_id', 'activity_date' => 'a.activity_date', 'activity_type' => 'a.activity_type', 'category' => 'a.category', 'content_name' => 'a.content_name'];
$sortKey = isset($sortOptions[$_GET['sort'] ?? '']) ? (string) $_GET['sort'] : 'activity_date';
$direction = strtolower((string) ($_GET['direction'] ?? 'desc')) === 'asc' ? 'ASC' : 'DESC';
$where = [];
$types = '';
$params = [];
if ($filters['type'] !== '') { $where[] = 'a.activity_type = ?'; $types .= 's'; $params[] = $filters['type']; }
if ($filters['category'] !== '') { $where[] = 'a.category = ?'; $types .= 's'; $params[] = $filters['category']; }
if ($filters['parent'] !== '') { $where[] = 'a.parent_category = ?'; $types .= 's'; $params[] = $filters['parent']; }
if ($filters['user'] !== '') { $where[] = '(u.username LIKE ? OR CAST(a.user_id AS CHAR) LIKE ?)'; $types .= 'ss'; $params[] = '%' . $filters['user'] . '%'; $params[] = '%' . $filters['user'] . '%'; }
if ($filters['from'] !== '') { $where[] = 'a.activity_date >= ?'; $types .= 's'; $params[] = $filters['from'] . ' 00:00:00'; }
if ($filters['to'] !== '') { $where[] = 'a.activity_date < DATE_ADD(?, INTERVAL 1 DAY)'; $types .= 's'; $params[] = $filters['to']; }
$whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';
$bind = static function (mysqli_stmt $stmt, string $types, array $values): void {
    if (!$values) return;
    $args = [$types];
    foreach ($values as $index => &$value) $args[] = &$value;
    call_user_func_array([$stmt, 'bind_param'], $args);
};
$countStmt = $conn->prepare("SELECT COUNT(*) total FROM user_activity_log a LEFT JOIN users u ON u.user_id = a.user_id {$whereSql}");
if ($countStmt) { $bind($countStmt, $types, $params); $countStmt->execute(); $totalRows = (int) ($countStmt->get_result()->fetch_assoc()['total'] ?? 0); $countStmt->close(); } else { $totalRows = 0; }
$totalPages = max(1, (int) ceil($totalRows / $perPage));
$page = min($page, $totalPages);
$offset = ($page - 1) * $perPage;
$stmt = $conn->prepare("SELECT a.activity_id, a.user_id, a.session_hash, a.activity_type, a.category, a.parent_category, a.content_name, a.element_name, a.page_section, a.metadata, a.activity_date, COALESCE(u.username, IF(a.user_id IS NULL, CONCAT('Anonymous ', LEFT(a.session_hash, 8)), CONCAT('User #', a.user_id))) visitor FROM user_activity_log a LEFT JOIN users u ON u.user_id = a.user_id {$whereSql} ORDER BY {$sortOptions[$sortKey]} {$direction}, a.activity_id DESC LIMIT ? OFFSET ?");
$rows = [];
if ($stmt) { $listTypes = $types . 'ii'; $listParams = $params; $listParams[] = $perPage; $listParams[] = $offset; $bind($stmt, $listTypes, $listParams); $stmt->execute(); $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC); $stmt->close(); }
$baseParams = $_GET; unset($baseParams['page']);
function activity_log_url(array $params, int $page): string { $params['page'] = $page; return '?' . http_build_query($params); }
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
    <title>User Activity Log | KulToura Admin</title>
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:ital,wght@0,400;0,700;0,900;1,400&family=DM+Sans:wght@400;500;600;700&family=Bebas+Neue&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/admin-theme.css?v=20261124-headline-kpis">
    <link rel="stylesheet" href="../assets/css/admindashboard.css?v=20261102-1">
    <style>
        body { min-height:100vh; }
        .activity-page { max-width:1500px; margin:0 auto; padding:34px clamp(16px,4vw,56px); }
        .activity-header { display:flex; align-items:end; justify-content:space-between; gap:18px; margin-bottom:22px; }
        .activity-header h1 { margin:5px 0; color:var(--cream); font:700 clamp(1.7rem,3vw,2.4rem) 'Playfair Display',serif; }
        .activity-header p { margin:0; color:rgba(var(--cream-rgb),.58); }
        .activity-back { color:var(--gold); text-decoration:none; font-weight:600; }
        .activity-filters { display:grid; grid-template-columns:repeat(6,minmax(120px,1fr)); gap:10px; padding:16px; margin-bottom:16px; border:1px solid rgba(var(--overlay-rgb),.1); border-radius:14px; background:rgba(var(--overlay-rgb),.025); }
        .activity-filters label { display:grid; gap:5px; color:rgba(var(--cream-rgb),.62); font-size:.72rem; }
        .activity-filters input,.activity-filters select { min-width:0; height:38px; padding:0 9px; border:1px solid rgba(var(--overlay-rgb),.14); border-radius:8px; background:#142017; color:var(--cream); }
        .activity-filters button { align-self:end; height:38px; border:0; border-radius:999px; background:var(--clay); color:#fff; font-weight:700; cursor:pointer; }
        .activity-table-wrap { overflow:auto; border:1px solid rgba(var(--overlay-rgb),.1); border-radius:14px; background:rgba(var(--overlay-rgb),.025); }
        .activity-table { width:100%; border-collapse:collapse; min-width:950px; }
        .activity-table th,.activity-table td { padding:12px 14px; border-bottom:1px solid rgba(var(--overlay-rgb),.07); text-align:left; font-size:.78rem; }
        .activity-table th { color:var(--gold); font-weight:700; white-space:nowrap; }
        .activity-table th a { color:inherit; text-decoration:none; }
        .activity-table td { color:rgba(var(--cream-rgb),.72); }
        .activity-type { color:var(--sage)!important; font-weight:700; text-transform:capitalize; }
        .activity-empty { padding:34px; text-align:center; color:rgba(var(--cream-rgb),.58); }
        .activity-pagination { display:flex; justify-content:space-between; align-items:center; gap:12px; margin-top:15px; color:rgba(var(--cream-rgb),.6); font-size:.8rem; }
        .activity-pagination a { color:var(--gold); text-decoration:none; }
        @media(max-width:900px) { .activity-filters { grid-template-columns:repeat(3,minmax(0,1fr)); } }
        @media(max-width:560px) { .activity-header { align-items:flex-start; flex-direction:column; } .activity-filters { grid-template-columns:repeat(2,minmax(0,1fr)); } }
    </style>
</head>
<body>
<main class="activity-page">
    <header class="activity-header">
        <div><span class="section-label">Tourism Insights</span><h1>User Activity Log</h1><p>Views, clicks, searches, and other recorded visitor actions.</p></div>
        <a class="activity-back" href="admindashboard.php">← Analytics dashboard</a>
    </header>
    <form class="activity-filters" method="get">
        <label>Activity type<select name="type"><option value="">All activities</option><?php foreach ($activityTypes as $type): ?><option value="<?= htmlspecialchars($type) ?>"<?= $filters['type'] === $type ? ' selected' : '' ?>><?= htmlspecialchars(ucwords(str_replace('_', ' ', $type))) ?></option><?php endforeach; ?></select></label>
        <label>Parent category<select name="parent"><option value="">All groups</option><?php foreach ($parents as $key => $parent): ?><option value="<?= htmlspecialchars($key) ?>"<?= $filters['parent'] === $key ? ' selected' : '' ?>><?= htmlspecialchars($parent['label']) ?></option><?php endforeach; ?></select></label>
        <label>Tourism category<select name="category"><option value="">All categories</option><?php foreach ($categories as $key => $category): ?><option value="<?= htmlspecialchars($key) ?>"<?= $filters['category'] === $key ? ' selected' : '' ?>><?= htmlspecialchars($category['label']) ?></option><?php endforeach; ?></select></label>
        <label>User<input name="user" value="<?= htmlspecialchars($filters['user']) ?>" placeholder="Username or ID"></label>
        <label>From<input type="date" name="from" value="<?= htmlspecialchars($filters['from']) ?>"></label>
        <label>To<input type="date" name="to" value="<?= htmlspecialchars($filters['to']) ?>"></label>
        <button type="submit">Apply filters</button>
    </form>
    <div class="activity-table-wrap"><table class="activity-table"><thead><tr>
        <?php $headers = ['activity_id' => 'ID', 'activity_type' => 'Activity', 'category' => 'Category', 'content_name' => 'Content', 'activity_date' => 'Date & Time']; foreach ($headers as $key => $label): $nextDirection = $sortKey === $key && $direction === 'DESC' ? 'asc' : 'desc'; $query = array_merge($baseParams, ['sort' => $key, 'direction' => $nextDirection]); ?>
        <th><a href="?<?= htmlspecialchars(http_build_query($query)) ?>"><?= htmlspecialchars($label) ?><?= $sortKey === $key ? ($direction === 'ASC' ? ' ↑' : ' ↓') : '' ?></a></th>
        <?php endforeach; ?><th>Visitor</th><th>Page / Section</th><th>Element</th></tr></thead><tbody>
        <?php if (!$rows): ?><tr><td colspan="8" class="activity-empty">No activities match these filters.</td></tr><?php else: foreach ($rows as $row): ?>
        <tr><td><?= (int) $row['activity_id'] ?></td><td class="activity-type"><?= htmlspecialchars(str_replace('_', ' ', $row['activity_type'])) ?></td><td><?= htmlspecialchars($categories[$row['category']]['label'] ?? '—') ?></td><td><?= htmlspecialchars($row['content_name'] ?: '—') ?></td><td><?= htmlspecialchars($row['activity_date']) ?></td><td><?= htmlspecialchars($row['visitor']) ?></td><td><?= htmlspecialchars($row['page_section']) ?></td><td><?= htmlspecialchars($row['element_name'] ?: '—') ?></td></tr>
        <?php endforeach; endif; ?></tbody></table></div>
    <nav class="activity-pagination" aria-label="Activity pages"><span><?= number_format($totalRows) ?> activities · page <?= $page ?> of <?= $totalPages ?></span><span><?php if ($page > 1): ?><a href="<?= htmlspecialchars(activity_log_url($baseParams, $page - 1)) ?>">← Previous</a><?php endif; ?><?php if ($page > 1 && $page < $totalPages): ?> · <?php endif; ?><?php if ($page < $totalPages): ?><a href="<?= htmlspecialchars(activity_log_url($baseParams, $page + 1)) ?>">Next →</a><?php endif; ?></span></nav>
</main>

</body>
</html>

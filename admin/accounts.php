<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

require_once '../includes/session.php';
require_once '../includes/config.php';

if (!isAdmin()) {
    header('Location: ' . BASE_URL . '/index.php');
    exit;
}

$dbc = getConnection();

// ---- Handle actions ----
if (isset($_GET['action']) && isset($_GET['id'])) {
    $id     = (int)$_GET['id'];
    $action = $_GET['action'];

    if ($action === 'remove') {
        $stmt = mysqli_prepare($dbc,
            "UPDATE dbProj_accounts SET status = 'removed' WHERE account_id = ?");
        mysqli_stmt_bind_param($stmt, 'i', $id);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
    } elseif ($action === 'publish') {
        $stmt = mysqli_prepare($dbc,
            "UPDATE dbProj_accounts SET status = 'published', published_at = NOW() WHERE account_id = ?");
        mysqli_stmt_bind_param($stmt, 'i', $id);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
    } elseif ($action === 'restore') {
        $stmt = mysqli_prepare($dbc,
            "UPDATE dbProj_accounts SET status = 'published' WHERE account_id = ?");
        mysqli_stmt_bind_param($stmt, 'i', $id);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
    }
    header('Location: ' . BASE_URL . '/admin/accounts.php?updated=1');
    exit;
}

// ---- Filter ----
$filter = $_GET['status'] ?? 'all';

if ($filter !== 'all') {
    $stmt = mysqli_prepare($dbc,
        "SELECT a.*, c.name AS cat_name, c.slug AS cat_slug,
                u.username AS creator_name,
                COALESCE(AVG(r.score), 0) AS avg_rating,
                COUNT(DISTINCT cm.comment_id) AS comment_count
         FROM dbProj_accounts a
         JOIN dbProj_categories c ON a.cat_id = c.cat_id
         JOIN dbProj_users u ON a.creator_id = u.user_id
         LEFT JOIN dbProj_ratings r ON a.account_id = r.account_id
         LEFT JOIN dbProj_comments cm ON a.account_id = cm.account_id AND cm.is_removed = 0
         WHERE a.status = ?
         GROUP BY a.account_id
         ORDER BY a.created_at DESC");
    mysqli_stmt_bind_param($stmt, 's', $filter);
    mysqli_stmt_execute($stmt);
    $result   = mysqli_stmt_get_result($stmt);
    $accounts = mysqli_fetch_all($result, MYSQLI_ASSOC);
    mysqli_stmt_close($stmt);
} else {
    $result   = mysqli_query($dbc,
        "SELECT a.*, c.name AS cat_name, c.slug AS cat_slug,
                u.username AS creator_name,
                COALESCE(AVG(r.score), 0) AS avg_rating,
                COUNT(DISTINCT cm.comment_id) AS comment_count
         FROM dbProj_accounts a
         JOIN dbProj_categories c ON a.cat_id = c.cat_id
         JOIN dbProj_users u ON a.creator_id = u.user_id
         LEFT JOIN dbProj_ratings r ON a.account_id = r.account_id
         LEFT JOIN dbProj_comments cm ON a.account_id = cm.account_id AND cm.is_removed = 0
         GROUP BY a.account_id
         ORDER BY a.created_at DESC");
    $accounts = mysqli_fetch_all($result, MYSQLI_ASSOC);
}

$pageTitle  = 'Manage Accounts';
$activePage = 'admin';
include '../includes/header.php';
?>

<div class="panel-wrapper">
<div class="panel-container">

  <div class="panel-header">
    <div>
      <h1 class="panel-title">🎮 Manage Accounts</h1>
      <p class="panel-subtitle">View, publish or remove listings</p>
    </div>
    <div style="display:flex;gap:0.75rem;flex-wrap:wrap;align-items:center;">
      <a href="<?= BASE_URL ?>/admin/index.php" class="btn-outline">← Dashboard</a>
      <a href="?status=all"       class="page-btn <?= $filter==='all'       ?'active':'' ?>">All</a>
      <a href="?status=published" class="page-btn <?= $filter==='published' ?'active':'' ?>">Published</a>
      <a href="?status=draft"     class="page-btn <?= $filter==='draft'     ?'active':'' ?>">Drafts</a>
      <a href="?status=removed"   class="page-btn <?= $filter==='removed'   ?'active':'' ?>">Removed</a>
      <a href="?status=sold"      class="page-btn <?= $filter==='sold'      ?'active':'' ?>">Sold</a>
    </div>
  </div>

  <?php if (isset($_GET['updated'])): ?>
    <div style="background:rgba(16,185,129,0.1);border:1px solid rgba(16,185,129,0.3);
                color:var(--success);padding:1rem 1.5rem;margin-bottom:1.5rem;
                font-family:'Orbitron',sans-serif;font-size:0.8rem;letter-spacing:1px;">
      ✅ Account updated successfully.
    </div>
  <?php endif; ?>

  <p style="color:var(--muted);margin-bottom:1.5rem;">
    Showing <strong style="color:var(--text)"><?= count($accounts) ?></strong> account(s)
  </p>

  <div style="overflow-x:auto;">
    <table class="data-table">
      <thead>
        <tr>
          <th>#</th>
          <th>Title</th>
          <th>Category</th>
          <th>Creator</th>
          <th>Price</th>
          <th>Views</th>
          <th>Rating</th>
          <th>Comments</th>
          <th>Status</th>
          <th>Created</th>
          <th>Actions</th>
        </tr>
      </thead>
      <tbody>
        <?php if (empty($accounts)): ?>
          <tr>
            <td colspan="11" style="text-align:center;color:var(--muted);padding:3rem;">
              No accounts found.
            </td>
          </tr>
        <?php else: ?>
        <?php foreach ($accounts as $a): ?>
        <tr>
          <td style="color:var(--muted)"><?= $a['account_id'] ?></td>
          <td style="max-width:180px;">
            <a href="<?= BASE_URL ?>/detail.php?id=<?= $a['account_id'] ?>"
               style="color:var(--accent);text-decoration:none;font-weight:600;font-size:0.85rem;">
              <?= htmlspecialchars($a['title']) ?>
            </a>
          </td>
          <td>
            <span class="label-<?= htmlspecialchars($a['cat_slug']) ?>"
                  style="font-size:0.6rem;padding:0.15rem 0.5rem;">
              <?= strtoupper(htmlspecialchars($a['cat_slug'])) ?>
            </span>
          </td>
          <td style="color:var(--muted)">@<?= htmlspecialchars($a['creator_name']) ?></td>
          <td style="color:var(--accent3);font-weight:700;">$<?= number_format($a['price'], 2) ?></td>
          <td><?= number_format($a['view_count']) ?></td>
          <td><?= number_format($a['avg_rating'], 1) ?> ★</td>
          <td><?= $a['comment_count'] ?></td>
          <td>
            <span class="status-badge status-<?= $a['status'] ?>">
              <?= $a['status'] ?>
            </span>
          </td>
          <td style="color:var(--muted);font-size:0.78rem;">
            <?= date('M j, Y', strtotime($a['created_at'])) ?>
          </td>
          <td>
            <div style="display:flex;gap:0.4rem;flex-wrap:wrap;">
              <a href="<?= BASE_URL ?>/creator/edit.php?id=<?= $a['account_id'] ?>"
                 class="page-btn" style="font-size:0.55rem;">Edit</a>
              <?php if ($a['status'] === 'draft'): ?>
                <a href="?action=publish&id=<?= $a['account_id'] ?>"
                   class="page-btn" style="font-size:0.55rem;background:rgba(16,185,129,0.15);color:var(--success);"
                   onclick="return confirm('Publish this listing?')">Publish</a>
              <?php endif; ?>
              <?php if ($a['status'] === 'published' || $a['status'] === 'draft'): ?>
                <a href="?action=remove&id=<?= $a['account_id'] ?>"
                   class="page-btn" style="font-size:0.55rem;background:rgba(239,68,68,0.15);color:var(--danger);"
                   onclick="return confirm('Remove this listing?')">Remove</a>
              <?php endif; ?>
              <?php if ($a['status'] === 'removed' || $a['status'] === 'sold'): ?>
                <a href="?action=restore&id=<?= $a['account_id'] ?>"
                   class="page-btn" style="font-size:0.55rem;background:rgba(16,185,129,0.15);color:var(--success);"
                   onclick="return confirm('Restore this listing?')">Restore</a>
              <?php endif; ?>
            </div>
          </td>
        </tr>
        <?php endforeach; ?>
        <?php endif; ?>
      </tbody>
    </table>
  </div>

</div>
</div>

<?php include '../includes/footer.php'; ?>

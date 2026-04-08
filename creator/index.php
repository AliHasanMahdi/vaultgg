<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

require_once '../includes/session.php';
require_once '../includes/config.php';

// Only creators and admins can access
if (!isLoggedIn() || (!isCreator() && !isAdmin())) {
    header('Location: /~u202202670/vaultgg/login.php');
    exit;
}

$dbc    = getConnection();
$userId = $_SESSION['user_id'];

// ---- Get this creator's listings ----
$stmt = mysqli_prepare($dbc,
    "SELECT a.*, c.name AS cat_name, c.slug AS cat_slug,
            COALESCE(AVG(r.score), 0) AS avg_rating,
            COUNT(DISTINCT r.rating_id) AS rating_count,
            COUNT(DISTINCT cm.comment_id) AS comment_count
     FROM dbProj_accounts a
     JOIN dbProj_categories c ON a.cat_id = c.cat_id
     LEFT JOIN dbProj_ratings r ON a.account_id = r.account_id
     LEFT JOIN dbProj_comments cm ON a.account_id = cm.account_id
                                  AND cm.is_removed = 0
     WHERE a.creator_id = ?
     GROUP BY a.account_id
     ORDER BY a.created_at DESC");
mysqli_stmt_bind_param($stmt, 'i', $userId);
mysqli_stmt_execute($stmt);
$result   = mysqli_stmt_get_result($stmt);
$accounts = mysqli_fetch_all($result, MYSQLI_ASSOC);
mysqli_stmt_close($stmt);

// ---- Stats ----
$total      = count($accounts);
$published  = count(array_filter($accounts, fn($a) => $a['status'] === 'published'));
$drafts     = count(array_filter($accounts, fn($a) => $a['status'] === 'draft'));
$totalViews = array_sum(array_column($accounts, 'view_count'));

$pageTitle  = 'My Listings';
$activePage = 'creator';
include '../includes/header.php';
?>

<div class="panel-wrapper">
<div class="panel-container">

  <div class="panel-header">
    <div>
      <div class="panel-title">My Listings</div>
      <div class="panel-subtitle">
        Welcome back, <?= htmlspecialchars($_SESSION['username']) ?>!
      </div>
    </div>
    <a class="btn-primary"
       href="/~u202202670/vaultgg/creator/create.php">
      + Add New Listing
    </a>
  </div>

  <!-- STATS CARDS -->
  <div class="stats-cards">
    <div class="stat-card">
      <div class="stat-card-icon">📋</div>
      <div class="stat-card-num"><?= $total ?></div>
      <div class="stat-card-label">Total Listings</div>
    </div>
    <div class="stat-card">
      <div class="stat-card-icon">✅</div>
      <div class="stat-card-num"><?= $published ?></div>
      <div class="stat-card-label">Published</div>
    </div>
    <div class="stat-card">
      <div class="stat-card-icon">📝</div>
      <div class="stat-card-num"><?= $drafts ?></div>
      <div class="stat-card-label">Drafts</div>
    </div>
    <div class="stat-card">
      <div class="stat-card-icon">👁️</div>
      <div class="stat-card-num"><?= number_format($totalViews) ?></div>
      <div class="stat-card-label">Total Views</div>
    </div>
  </div>

  <!-- LISTINGS TABLE -->
  <div style="overflow-x:auto;">
    <table class="data-table">
      <thead>
        <tr>
          <th>#</th>
          <th>Title</th>
          <th>Category</th>
          <th>Status</th>
          <th>Price</th>
          <th>Views</th>
          <th>Rating</th>
          <th>Comments</th>
          <th>Created</th>
          <th>Actions</th>
        </tr>
      </thead>
      <tbody>
        <?php if (empty($accounts)): ?>
          <tr>
            <td colspan="10"
                style="text-align:center;color:var(--muted);padding:3rem;">
              No listings yet.
              <a href="/~u202202670/vaultgg/creator/create.php"
                 style="color:var(--accent);">Create your first one!</a>
            </td>
          </tr>
        <?php else: ?>
          <?php foreach ($accounts as $a): ?>
          <tr>
            <td style="color:var(--muted)"><?= $a['account_id'] ?></td>
            <td>
              <a href="/~u202202670/vaultgg/detail.php?id=<?= $a['account_id'] ?>"
                 style="color:var(--accent);text-decoration:none;font-weight:600;">
                <?= htmlspecialchars($a['title']) ?>
              </a>
            </td>
            <td>
              <span class="label-<?= htmlspecialchars($a['cat_slug']) ?>"
                    style="font-size:0.65rem;padding:0.2rem 0.5rem;">
                <?= htmlspecialchars($a['cat_name']) ?>
              </span>
            </td>
            <td>
              <span class="status-badge status-<?= $a['status'] ?>">
                <?= $a['status'] ?>
              </span>
            </td>
            <td style="color:var(--accent3);font-weight:700;">
              $<?= number_format($a['price'], 2) ?>
            </td>
            <td><?= number_format($a['view_count']) ?></td>
            <td><?= number_format($a['avg_rating'], 1) ?> ★</td>
            <td><?= $a['comment_count'] ?></td>
            <td style="color:var(--muted);font-size:0.8rem;">
              <?= date('M j, Y', strtotime($a['created_at'])) ?>
            </td>
            <td>
              <div style="display:flex;gap:0.5rem;flex-wrap:wrap;">
                <a href="/~u202202670/vaultgg/creator/edit.php?id=<?= $a['account_id'] ?>"
                   style="background:var(--surface2);border:1px solid var(--border);
                          color:var(--muted);font-family:'Orbitron',sans-serif;
                          font-size:0.6rem;font-weight:700;letter-spacing:1px;
                          padding:0.35rem 0.75rem;text-decoration:none;
                          text-transform:uppercase;transition:all 0.2s;"
                   onmouseover="this.style.color='var(--accent)'"
                   onmouseout="this.style.color='var(--muted)'">
                  Edit
                </a>
                <?php if ($a['status'] === 'draft'): ?>
                <a href="/~u202202670/vaultgg/creator/publish.php?id=<?= $a['account_id'] ?>"
                   style="background:rgba(16,185,129,0.15);
                          border:1px solid rgba(16,185,129,0.4);
                          color:var(--success);font-family:'Orbitron',sans-serif;
                          font-size:0.6rem;font-weight:700;letter-spacing:1px;
                          padding:0.35rem 0.75rem;text-decoration:none;
                          text-transform:uppercase;">
                  Publish
                </a>
                <?php endif; ?>
                <?php if ($a['status'] !== 'removed'): ?>
                <a href="/~u202202670/vaultgg/creator/delete.php?id=<?= $a['account_id'] ?>"
                   style="background:rgba(239,68,68,0.15);
                          border:1px solid rgba(239,68,68,0.4);
                          color:var(--danger);font-family:'Orbitron',sans-serif;
                          font-size:0.6rem;font-weight:700;letter-spacing:1px;
                          padding:0.35rem 0.75rem;text-decoration:none;
                          text-transform:uppercase;"
                   onclick="return confirm('Delete this listing?')">
                  Delete
                </a>
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

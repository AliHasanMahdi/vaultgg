<?php
require_once '../includes/session.php';
require_once '../includes/config.php';

if (!isAdmin()) {
    header('Location: ' . BASE_URL . '/index.php');
    exit;
}

$dbc = getConnection();

// ---- Handle remove/restore ----
if (isset($_GET['toggle']) && isset($_GET['id'])) {
    $id      = (int)$_GET['id'];
    $current = mysqli_fetch_row(mysqli_query($dbc, "SELECT is_removed FROM dbProj_comments WHERE comment_id = $id"))[0];
    $new     = $current ? 0 : 1;
    mysqli_query($dbc, "UPDATE dbProj_comments SET is_removed = $new WHERE comment_id = $id");
    header('Location: ' . BASE_URL . '/admin/comments.php?updated=1');
    exit;
}

$comments = mysqli_query($dbc,
    "SELECT cm.*, u.username, a.title AS account_title
     FROM dbProj_comments cm
     JOIN dbProj_users u ON cm.user_id = u.user_id
     JOIN dbProj_accounts a ON cm.account_id = a.account_id
     ORDER BY cm.created_at DESC");

$pageTitle  = 'Manage Comments';
$activePage = 'admin';
include '../includes/header.php';
?>

<div class="panel-wrapper">
  <div class="panel-container">

    <div class="panel-header">
      <div>
        <h1 class="panel-title">💬 Manage Comments</h1>
        <p class="panel-subtitle">Remove or restore inappropriate comments</p>
      </div>
      <a href="<?= BASE_URL ?>/admin/index.php" class="btn-outline">← Back to Dashboard</a>
    </div>

    <?php if (isset($_GET['updated'])): ?>
      <div class="error-msg" style="background:rgba(16,185,129,0.1);border-color:rgba(16,185,129,0.3);color:var(--success);">
        ✅ Comment updated.
      </div>
    <?php endif; ?>

    <table class="data-table">
      <thead>
        <tr>
          <th>ID</th>
          <th>User</th>
          <th>Account</th>
          <th>Comment</th>
          <th>Date</th>
          <th>Status</th>
          <th>Action</th>
        </tr>
      </thead>
      <tbody>
        <?php while ($c = mysqli_fetch_assoc($comments)): ?>
        <tr>
          <td><?= $c['comment_id'] ?></td>
          <td>@<?= htmlspecialchars($c['username']) ?></td>
          <td><?= htmlspecialchars($c['account_title']) ?></td>
          <td><?= htmlspecialchars(substr($c['body'], 0, 80)) ?>...</td>
          <td><?= date('M j, Y', strtotime($c['created_at'])) ?></td>
          <td>
            <span class="status-badge <?= $c['is_removed'] ? 'status-removed' : 'status-published' ?>">
              <?= $c['is_removed'] ? 'Removed' : 'Active' ?>
            </span>
          </td>
          <td>
            <a href="?toggle=1&id=<?= $c['comment_id'] ?>" class="page-btn" style="font-size:0.6rem; <?= $c['is_removed'] ? '' : 'background:rgba(239,68,68,0.1);color:var(--danger);' ?>">
              <?= $c['is_removed'] ? 'Restore' : 'Remove' ?>
            </a>
          </td>
        </tr>
        <?php endwhile; ?>
      </tbody>
    </table>

  </div>
</div>

<?php include '../includes/footer.php'; ?>

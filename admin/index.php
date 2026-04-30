<?php
require_once '../includes/session.php';
require_once '../includes/config.php';

if (!isAdmin()) {
    header('Location: ' . BASE_URL . '/index.php');
    exit;
}

$dbc = getConnection();

// Stats
$totalUsers     = mysqli_fetch_row(mysqli_query($dbc, "SELECT COUNT(*) FROM dbProj_users"))[0];
$totalAccounts  = mysqli_fetch_row(mysqli_query($dbc, "SELECT COUNT(*) FROM dbProj_accounts"))[0];
$totalPublished = mysqli_fetch_row(mysqli_query($dbc, "SELECT COUNT(*) FROM dbProj_accounts WHERE status='published'"))[0];
$totalComments  = mysqli_fetch_row(mysqli_query($dbc, "SELECT COUNT(*) FROM dbProj_comments WHERE is_removed=0"))[0];
$totalRatings   = mysqli_fetch_row(mysqli_query($dbc, "SELECT COUNT(*) FROM dbProj_ratings"))[0];

// Recent activity
$recentActivity = mysqli_query($dbc,
    "SELECT al.*, u.username
     FROM dbProj_activity_log al
     LEFT JOIN dbProj_users u ON al.user_id = u.user_id
     ORDER BY al.logged_at DESC LIMIT 10");

$pageTitle  = 'Admin Panel';
$activePage = 'admin';
include '../includes/header.php';
?>

<div class="panel-wrapper">
  <div class="panel-container">

    <div class="panel-header">
      <div>
        <h1 class="panel-title">🛡️ Admin Panel</h1>
        <p class="panel-subtitle">Manage users, accounts, and content</p>
      </div>
    </div>

    <!-- Quick Stats -->
    <div class="stats-cards">
      <div class="stat-card">
        <div class="stat-card-num"><?= $totalUsers ?></div>
        <div class="stat-card-label">Total Users</div>
      </div>
      <div class="stat-card">
        <div class="stat-card-num"><?= $totalAccounts ?></div>
        <div class="stat-card-label">Total Accounts</div>
      </div>
      <div class="stat-card">
        <div class="stat-card-num"><?= $totalPublished ?></div>
        <div class="stat-card-label">Published</div>
      </div>
      <div class="stat-card">
        <div class="stat-card-num"><?= $totalComments ?></div>
        <div class="stat-card-label">Active Comments</div>
      </div>
      <div class="stat-card">
        <div class="stat-card-num"><?= $totalRatings ?></div>
        <div class="stat-card-label">Ratings</div>
      </div>
    </div>

    <!-- Quick Actions -->
    <div style="display:flex;gap:1rem;margin-bottom:2.5rem;flex-wrap:wrap;">
      <a href="<?= BASE_URL ?>/admin/users.php" class="btn-outline">👥 Manage Users</a>
      <a href="<?= BASE_URL ?>/admin/accounts.php" class="btn-outline">🎮 Manage Accounts</a>
      <a href="<?= BASE_URL ?>/admin/comments.php" class="btn-outline">💬 Manage Comments</a>
      <a href="<?= BASE_URL ?>/index.php" class="btn-outline">🏠 Back to Site</a>
    </div>

    <!-- Recent Activity -->
    <h2 style="font-family:'Orbitron',sans-serif;font-size:1.2rem;margin-bottom:1rem;letter-spacing:1px;">📋 Recent Activity</h2>
    <table class="data-table">
      <thead>
        <tr>
          <th>Time</th>
          <th>Action</th>
          <th>Entity</th>
          <th>User</th>
          <th>Note</th>
        </tr>
      </thead>
      <tbody>
        <?php while ($log = mysqli_fetch_assoc($recentActivity)): ?>
        <tr>
          <td><?= date('M j, H:i', strtotime($log['logged_at'])) ?></td>
          <td><?= htmlspecialchars($log['action']) ?></td>
          <td><?= htmlspecialchars($log['entity_type'] . ' #' . $log['entity_id']) ?></td>
          <td><?= $log['username'] ? '@' . htmlspecialchars($log['username']) : 'System' ?></td>
          <td><?= htmlspecialchars($log['note']) ?></td>
        </tr>
        <?php endwhile; ?>
      </tbody>
    </table>

  </div>
</div>

<?php include '../includes/footer.php'; ?>
<?php
require_once '../includes/session.php';
require_once '../includes/config.php';

if (!isAdmin()) {
    header('Location: ' . BASE_URL . '/index.php');
    exit;
}

$dbc = getConnection();

// ---- Handle CREATOR_REQUEST accept/deny ----
if (isset($_GET['creator_action']) && isset($_GET['user_id'])) {
    $uid    = (int)$_GET['user_id'];
    $logId  = (int)($_GET['log_id'] ?? 0);
    $action = $_GET['creator_action'];

    if ($action === 'accept') {
        // Promote user to creator
        $stmt = mysqli_prepare($dbc,
            "UPDATE dbProj_users SET role = 'creator' WHERE user_id = ?");
        mysqli_stmt_bind_param($stmt, 'i', $uid);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);

        // Log it
        $note = 'Creator request ACCEPTED for user #' . $uid;
        $ins  = mysqli_prepare($dbc,
            "INSERT INTO dbProj_activity_log (action, entity_type, entity_id, user_id, note)
             VALUES ('CREATOR_ACCEPTED', 'user', ?, ?, ?)");
        mysqli_stmt_bind_param($ins, 'iis', $uid, $_SESSION['user_id'], $note);
        mysqli_stmt_execute($ins);
        mysqli_stmt_close($ins);

        // Remove the original request log entry
        if ($logId) {
            $del = mysqli_prepare($dbc,
                "DELETE FROM dbProj_activity_log WHERE log_id = ?");
            mysqli_stmt_bind_param($del, 'i', $logId);
            mysqli_stmt_execute($del);
            mysqli_stmt_close($del);
        }

    } elseif ($action === 'deny') {
        // Log the denial
        $note = 'Creator request DENIED for user #' . $uid;
        $ins  = mysqli_prepare($dbc,
            "INSERT INTO dbProj_activity_log (action, entity_type, entity_id, user_id, note)
             VALUES ('CREATOR_DENIED', 'user', ?, ?, ?)");
        mysqli_stmt_bind_param($ins, 'iis', $uid, $_SESSION['user_id'], $note);
        mysqli_stmt_execute($ins);
        mysqli_stmt_close($ins);

        // Remove the original request log entry
        if ($logId) {
            $del = mysqli_prepare($dbc,
                "DELETE FROM dbProj_activity_log WHERE log_id = ?");
            mysqli_stmt_bind_param($del, 'i', $logId);
            mysqli_stmt_execute($del);
            mysqli_stmt_close($del);
        }
    }

    header('Location: ' . BASE_URL . '/admin/index.php?msg=' . $action);
    exit;
}

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
     ORDER BY al.logged_at DESC LIMIT 15");

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

  <?php if (isset($_GET['msg'])): ?>
    <div style="background:<?= $_GET['msg']==='accept' ? 'rgba(16,185,129,0.1)' : 'rgba(239,68,68,0.1)' ?>;
                border:1px solid <?= $_GET['msg']==='accept' ? 'rgba(16,185,129,0.3)' : 'rgba(239,68,68,0.3)' ?>;
                color:<?= $_GET['msg']==='accept' ? 'var(--success)' : 'var(--danger)' ?>;
                padding:1rem 1.5rem;margin-bottom:1.5rem;
                font-family:'Orbitron',sans-serif;font-size:0.8rem;letter-spacing:1px;">
      <?= $_GET['msg']==='accept' ? '✅ Creator request accepted! User promoted to creator.' : '❌ Creator request denied.' ?>
    </div>
  <?php endif; ?>

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
    <a href="<?= BASE_URL ?>/admin/users.php"    class="btn-outline">👥 Manage Users</a>
    <a href="<?= BASE_URL ?>/admin/accounts.php" class="btn-outline">🎮 Manage Accounts</a>
    <a href="<?= BASE_URL ?>/admin/comments.php" class="btn-outline">💬 Manage Comments</a>
    <a href="<?= BASE_URL ?>/admin/reports.php"  class="btn-primary" style="font-size:0.7rem;">📊 Reports</a>
    <a href="<?= BASE_URL ?>/index.php"          class="btn-outline">🏠 Back to Site</a>
  </div>

  <!-- Recent Activity -->
  <h2 style="font-family:'Orbitron',sans-serif;font-size:1.2rem;
             margin-bottom:1rem;letter-spacing:1px;">📋 Recent Activity</h2>
  <table class="data-table">
    <thead>
      <tr>
        <th>Time</th>
        <th>Action</th>
        <th>Entity</th>
        <th>User</th>
        <th>Note</th>
        <th>Actions</th>
      </tr>
    </thead>
    <tbody>
      <?php while ($log = mysqli_fetch_assoc($recentActivity)): ?>
      <tr>
        <td style="color:var(--muted);font-size:0.8rem;">
          <?= date('M j, H:i', strtotime($log['logged_at'])) ?>
        </td>
        <td>
          <span style="background:<?= $log['action']==='CREATOR_REQUEST'
              ? 'rgba(245,158,11,0.15)' : 'rgba(0,240,255,0.08)' ?>;
                       color:<?= $log['action']==='CREATOR_REQUEST'
              ? 'var(--accent3)' : 'var(--accent)' ?>;
                       border:1px solid <?= $log['action']==='CREATOR_REQUEST'
              ? 'rgba(245,158,11,0.3)' : 'rgba(0,240,255,0.2)' ?>;
                       font-size:0.65rem;font-weight:700;letter-spacing:1px;
                       padding:0.2rem 0.5rem;text-transform:uppercase;">
            <?= htmlspecialchars($log['action']) ?>
          </span>
        </td>
        <td style="color:var(--muted);">
          <?= htmlspecialchars($log['entity_type'] . ' #' . $log['entity_id']) ?>
        </td>
        <td style="color:var(--muted);">
          <?= $log['username'] ? '@' . htmlspecialchars($log['username']) : 'System' ?>
        </td>
        <td style="font-size:0.85rem;">
          <?= htmlspecialchars($log['note']) ?>
        </td>
        <td>
          <?php if ($log['action'] === 'CREATOR_REQUEST'): ?>
            <div style="display:flex;gap:0.4rem;">
              <a href="?creator_action=accept&user_id=<?= $log['entity_id'] ?>&log_id=<?= $log['log_id'] ?>"
                 style="background:rgba(16,185,129,0.15);border:1px solid rgba(16,185,129,0.4);
                        color:var(--success);font-family:'Orbitron',sans-serif;
                        font-size:0.6rem;font-weight:700;letter-spacing:1px;
                        padding:0.3rem 0.6rem;text-decoration:none;text-transform:uppercase;"
                 onclick="return confirm('Accept this creator request?')">
                ✅ Accept
              </a>
              <a href="?creator_action=deny&user_id=<?= $log['entity_id'] ?>&log_id=<?= $log['log_id'] ?>"
                 style="background:rgba(239,68,68,0.15);border:1px solid rgba(239,68,68,0.4);
                        color:var(--danger);font-family:'Orbitron',sans-serif;
                        font-size:0.6rem;font-weight:700;letter-spacing:1px;
                        padding:0.3rem 0.6rem;text-decoration:none;text-transform:uppercase;"
                 onclick="return confirm('Deny this creator request?')">
                ❌ Deny
              </a>
            </div>
          <?php else: ?>
            <span style="color:var(--muted);font-size:0.75rem;">—</span>
          <?php endif; ?>
        </td>
      </tr>
      <?php endwhile; ?>
    </tbody>
  </table>

</div>
</div>

<?php include '../includes/footer.php'; ?>

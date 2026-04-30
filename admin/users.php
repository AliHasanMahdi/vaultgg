<?php
require_once '../includes/session.php';
require_once '../includes/config.php';

if (!isAdmin()) {
    header('Location: ' . BASE_URL . '/index.php');
    exit;
}

$dbc = getConnection();

// ---- Handle role/status update ----
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_user'])) {
    $userId   = (int)$_POST['user_id'];
    $role     = $_POST['role'];
    $isActive = (int)($_POST['is_active'] ?? 1);

    $stmt = mysqli_prepare($dbc,
        "UPDATE dbProj_users SET role = ?, is_active = ? WHERE user_id = ?");
    mysqli_stmt_bind_param($stmt, 'sii', $role, $isActive, $userId);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);
    header('Location: ' . BASE_URL . '/admin/users.php?updated=1');
    exit;
}

// ---- Get all users ----
$users = mysqli_query($dbc,
    "SELECT * FROM dbProj_users ORDER BY created_at DESC");

$pageTitle  = 'Manage Users';
$activePage = 'admin';
include '../includes/header.php';
?>

<div class="panel-wrapper">
  <div class="panel-container">

    <div class="panel-header">
      <div>
        <h1 class="panel-title">👥 Manage Users</h1>
        <p class="panel-subtitle">Change roles, activate/deactivate accounts</p>
      </div>
      <a href="<?= BASE_URL ?>/admin/index.php" class="btn-outline">← Back to Dashboard</a>
    </div>

    <?php if (isset($_GET['updated'])): ?>
      <div class="error-msg" style="background:rgba(16,185,129,0.1);border-color:rgba(16,185,129,0.3);color:var(--success);">
        ✅ User updated successfully.
      </div>
    <?php endif; ?>

    <table class="data-table">
      <thead>
        <tr>
          <th>ID</th>
          <th>Username</th>
          <th>Email</th>
          <th>Name</th>
          <th>Role</th>
          <th>Active</th>
          <th>Joined</th>
          <th>Action</th>
        </tr>
      </thead>
      <tbody>
        <?php while ($user = mysqli_fetch_assoc($users)): ?>
        <tr>
          <td><?= $user['user_id'] ?></td>
          <td>@<?= htmlspecialchars($user['username']) ?></td>
          <td><?= htmlspecialchars($user['email']) ?></td>
          <td><?= htmlspecialchars($user['first_name'] . ' ' . $user['last_name']) ?></td>
          <td>
            <form method="post" style="display:inline;">
              <input type="hidden" name="user_id" value="<?= $user['user_id'] ?>">
              <input type="hidden" name="update_user" value="1">
              <select name="role" onchange="this.form.submit()" class="form-select" style="width:auto;padding:0.2rem 0.5rem;font-size:0.8rem;">
                <option value="visitor" <?= $user['role']=='visitor'?'selected':'' ?>>Visitor</option>
                <option value="creator" <?= $user['role']=='creator'?'selected':'' ?>>Creator</option>
                <option value="admin"   <?= $user['role']=='admin'?'selected':'' ?>>Admin</option>
              </select>
              <input type="hidden" name="is_active" value="<?= $user['is_active'] ?>">
            </form>
          </td>
          <td>
            <form method="post" style="display:inline;">
              <input type="hidden" name="user_id" value="<?= $user['user_id'] ?>">
              <input type="hidden" name="role" value="<?= $user['role'] ?>">
              <input type="hidden" name="update_user" value="1">
              <select name="is_active" onchange="this.form.submit()" class="form-select" style="width:auto;padding:0.2rem 0.5rem;font-size:0.8rem;">
                <option value="1" <?= $user['is_active']?'selected':'' ?>>Yes</option>
                <option value="0" <?= !$user['is_active']?'selected':'' ?>>No</option>
              </select>
            </form>
          </td>
          <td><?= date('M j, Y', strtotime($user['created_at'])) ?></td>
          <td>
            <span class="status-badge <?= $user['is_active'] ? 'status-published' : 'status-removed' ?>">
              <?= $user['is_active'] ? 'Active' : 'Inactive' ?>
            </span>
          </td>
        </tr>
        <?php endwhile; ?>
      </tbody>
    </table>

  </div>
</div>

<?php include '../includes/footer.php'; ?>
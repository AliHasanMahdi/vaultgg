<?php
require_once '../includes/session.php';
require_once '../includes/config.php';

if (!isAdmin()) {
    header('Location: ' . BASE_URL . '/index.php');
    exit;
}

$dbc = getConnection();

if (isset($_GET['action']) && isset($_GET['id'])) {
    $id     = (int)$_GET['id'];
    $action = $_GET['action'];
    $message = '';

    if ($action === 'accept') {
        $stmt = mysqli_prepare($dbc,
            "UPDATE dbProj_purchases SET status = 'cancelled' WHERE purchase_id = ? AND status = 'refunded'");
        mysqli_stmt_bind_param($stmt, 'i', $id);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);

        $stmt = mysqli_prepare($dbc,
            "UPDATE dbProj_purchases p
             JOIN dbProj_accounts a ON p.account_id = a.account_id
             SET a.status = 'published'
             WHERE p.purchase_id = ? AND p.status = 'cancelled'");
        mysqli_stmt_bind_param($stmt, 'i', $id);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);

        $note = 'Refund accepted for purchase #' . $id;
        $message = 'accepted';
        $ins  = mysqli_prepare($dbc,
            "INSERT INTO dbProj_activity_log (action, entity_type, entity_id, user_id, note)
             VALUES ('REFUND_ACCEPTED', 'purchase', ?, ?, ?)");
        mysqli_stmt_bind_param($ins, 'iis', $id, $_SESSION['user_id'], $note);
        mysqli_stmt_execute($ins);
        mysqli_stmt_close($ins);
    } elseif ($action === 'deny') {
        $stmt = mysqli_prepare($dbc,
            "UPDATE dbProj_purchases SET status = 'active' WHERE purchase_id = ? AND status = 'refunded'");
        mysqli_stmt_bind_param($stmt, 'i', $id);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);

        $stmt = mysqli_prepare($dbc,
            "UPDATE dbProj_purchases p
             JOIN dbProj_accounts a ON p.account_id = a.account_id
             SET a.status = 'sold'
             WHERE p.purchase_id = ? AND p.status = 'active'");
        mysqli_stmt_bind_param($stmt, 'i', $id);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);

        $note = 'Refund denied for purchase #' . $id;
        $message = 'denied';
        $ins  = mysqli_prepare($dbc,
            "INSERT INTO dbProj_activity_log (action, entity_type, entity_id, user_id, note)
             VALUES ('REFUND_DENIED', 'purchase', ?, ?, ?)");
        mysqli_stmt_bind_param($ins, 'iis', $id, $_SESSION['user_id'], $note);
        mysqli_stmt_execute($ins);
        mysqli_stmt_close($ins);
    }

    $redirect = BASE_URL . '/admin/refunds.php';
    if ($message) {
        $redirect .= '?updated=' . $message;
    }
    header('Location: ' . $redirect);
    exit;
}

$result = mysqli_query($dbc,
    "SELECT p.*, u.username AS buyer_name, a.title AS account_title, a.account_id, c.name AS category_name,
            c.slug AS category_slug
     FROM dbProj_purchases p
     JOIN dbProj_users u ON p.user_id = u.user_id
     JOIN dbProj_accounts a ON p.account_id = a.account_id
     JOIN dbProj_categories c ON a.cat_id = c.cat_id
     WHERE p.status = 'refunded'
     ORDER BY p.created_at DESC");
$refunds = mysqli_fetch_all($result, MYSQLI_ASSOC);

$pageTitle  = 'Refund Requests';
$activePage = 'admin';
include '../includes/header.php';
?>

<div class="panel-wrapper">
<div class="panel-container">

  <div class="panel-header">
    <div>
      <h1 class="panel-title">💸 Refund Requests</h1>
      <p class="panel-subtitle">Review and clear user refund requests</p>
    </div>
    <div style="display:flex;gap:0.75rem;flex-wrap:wrap;align-items:center;">
      <a href="<?= BASE_URL ?>/admin/index.php" class="btn-outline">← Dashboard</a>
      <a href="<?= BASE_URL ?>/admin/accounts.php" class="btn-outline">Manage Accounts</a>
      <a href="<?= BASE_URL ?>/admin/users.php" class="btn-outline">Manage Users</a>
    </div>
  </div>

  <?php if (isset($_GET['updated'])): ?>
    <div style="background:rgba(16,185,129,0.1);border:1px solid rgba(16,185,129,0.3);
                color:var(--success);padding:1rem 1.5rem;margin-bottom:1.5rem;
                font-family:'Orbitron',sans-serif;font-size:0.8rem;letter-spacing:1px;">
      <?php if ($_GET['updated'] === 'accepted'): ?>
        ✅ Refund request accepted and processed.
      <?php elseif ($_GET['updated'] === 'denied'): ?>
        ❌ Refund request denied and restored.
      <?php else: ?>
        ✅ Refund request updated.
      <?php endif; ?>
    </div>
  <?php endif; ?>

  <p style="color:var(--muted);margin-bottom:1.5rem;">
    Showing <strong style="color:var(--text)"><?= count($refunds) ?></strong> refund request(s).
  </p>

  <div style="overflow-x:auto;">
    <table class="data-table">
      <thead>
        <tr>
          <th>#</th>
          <th>Buyer</th>
          <th>Account</th>
          <th>Category</th>
          <th>Price</th>
          <th>Purchased</th>
          <th>Status</th>
          <th>Actions</th>
        </tr>
      </thead>
      <tbody>
        <?php if (empty($refunds)): ?>
          <tr>
            <td colspan="8" style="text-align:center;color:var(--muted);padding:3rem;">
              No refund requests are pending.
            </td>
          </tr>
        <?php else: ?>
          <?php foreach ($refunds as $refund): ?>
            <tr>
              <td style="color:var(--muted)"><?= $refund['purchase_id'] ?></td>
              <td style="color:var(--accent);font-weight:600;">@<?= htmlspecialchars($refund['buyer_name']) ?></td>
              <td style="max-width:220px;">
                <a href="<?= BASE_URL ?>/detail.php?id=<?= $refund['account_id'] ?>"
                   style="color:var(--accent);text-decoration:none;font-weight:600;font-size:0.85rem;">
                  <?= htmlspecialchars($refund['account_title']) ?>
                </a>
              </td>
              <td>
                <span class="label-<?= htmlspecialchars($refund['category_slug']) ?>"
                      style="font-size:0.6rem;padding:0.15rem 0.5rem;">
                  <?= strtoupper(htmlspecialchars($refund['category_slug'])) ?>
                </span>
              </td>
              <td style="color:var(--accent3);font-weight:700;">$<?= number_format($refund['amount_paid'], 2) ?></td>
              <td style="color:var(--muted);font-size:0.85rem;">
                <?= date('M j, Y', strtotime($refund['created_at'])) ?>
              </td>
              <td>
                <span class="status-badge status-<?= $refund['status'] ?>">
                  <?= htmlspecialchars($refund['status']) ?>
                </span>
              </td>
              <td>
                <div style="display:flex;gap:0.4rem;flex-wrap:wrap;">
                  <a href="?action=accept&id=<?= $refund['purchase_id'] ?>"
                     class="page-btn" style="font-size:0.65rem;background:rgba(16,185,129,0.15);color:var(--success);"
                     onclick="return confirm('Accept this refund and release the account back to published status?')">
                    Accept
                  </a>
                  <a href="?action=deny&id=<?= $refund['purchase_id'] ?>"
                     class="page-btn" style="font-size:0.65rem;background:rgba(239,68,68,0.15);color:var(--danger);"
                     onclick="return confirm('Deny this refund request and keep the purchase active?')">
                    Deny
                  </a>
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

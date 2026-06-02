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

// ---- Report 1: Most popular in date range ----
$r1From  = $_GET['r1_from']  ?? date('Y-m-d', strtotime('-30 days'));
$r1To    = $_GET['r1_to']    ?? date('Y-m-d');
$r1Limit = max(1, min(20, (int)($_GET['r1_limit'] ?? 10)));

$stmt1 = mysqli_prepare($dbc, "CALL sp_report_popular(?, ?, ?)");
mysqli_stmt_bind_param($stmt1, 'ssi', $r1From, $r1To, $r1Limit);
mysqli_stmt_execute($stmt1);
$res1    = mysqli_stmt_get_result($stmt1);
$popular = mysqli_fetch_all($res1, MYSQLI_ASSOC);
mysqli_stmt_close($stmt1);

// ---- Report 2: Content by creator ----
$r2User    = (int)($_GET['r2_user'] ?? 0);
$r2Results = [];

if ($r2User) {
    $stmt2 = mysqli_prepare($dbc, "CALL sp_report_by_creator(?)");
    mysqli_stmt_bind_param($stmt2, 'i', $r2User);
    mysqli_stmt_execute($stmt2);
    $res2      = mysqli_stmt_get_result($stmt2);
    $r2Results = mysqli_fetch_all($res2, MYSQLI_ASSOC);
    mysqli_stmt_close($stmt2);
}

// ---- Get creators for dropdown ----
$creatorsRes = mysqli_query($dbc,
    "SELECT user_id, username, first_name, last_name
     FROM dbProj_users
     WHERE role IN ('creator','admin')
     ORDER BY username");
$creators = mysqli_fetch_all($creatorsRes, MYSQLI_ASSOC);

$pageTitle  = 'Reports';
$activePage = 'admin';
include '../includes/header.php';
?>

<div class="panel-wrapper">
<div class="panel-container">

  <div class="panel-header">
    <div>
      <h1 class="panel-title">📊 Reports</h1>
      <p class="panel-subtitle">Analytics powered by stored procedures</p>
    </div>
    <a href="<?= BASE_URL ?>/admin/index.php" class="btn-outline">← Dashboard</a>
  </div>

  <!-- ====== REPORT 1 ====== -->
  <div style="background:var(--surface);border:1px solid var(--border);
              padding:2rem;margin-bottom:2.5rem;">

    <div style="font-family:'Orbitron',sans-serif;font-size:0.9rem;font-weight:700;
                letter-spacing:2px;text-transform:uppercase;color:var(--accent);
                margin-bottom:0.5rem;">
      📈 Report 1: Most Popular Listings
    </div>
    <p style="color:var(--muted);font-size:0.82rem;margin-bottom:1.5rem;">
      Uses stored procedure: <code style="color:var(--accent3);">sp_report_popular(date_from, date_to, limit)</code>
    </p>

    <form method="get" style="display:flex;gap:1rem;flex-wrap:wrap;
                              align-items:flex-end;margin-bottom:1.5rem;">
      <input type="hidden" name="r2_user" value="<?= $r2User ?>">
      <div class="form-group" style="margin:0;flex:1;min-width:140px;">
        <label class="form-label">Date From</label>
        <input class="form-input" name="r1_from" type="date" value="<?= htmlspecialchars($r1From) ?>">
      </div>
      <div class="form-group" style="margin:0;flex:1;min-width:140px;">
        <label class="form-label">Date To</label>
        <input class="form-input" name="r1_to" type="date" value="<?= htmlspecialchars($r1To) ?>">
      </div>
      <div class="form-group" style="margin:0;min-width:100px;">
        <label class="form-label">Top N</label>
        <input class="form-input" name="r1_limit" type="number" min="1" max="20"
               value="<?= $r1Limit ?>" style="width:80px;">
      </div>
      <button type="submit" class="btn-primary" style="font-size:0.7rem;padding:0.75rem 1.5rem;">
        Generate
      </button>
    </form>

    <?php if (!empty($popular)): ?>
    <div style="overflow-x:auto;">
      <table class="data-table">
        <thead>
          <tr>
            <th>Rank</th>
            <th>Title</th>
            <th>Category</th>
            <th>Creator</th>
            <th>Price</th>
            <th>Views</th>
            <th>Avg Rating</th>
            <th>Comments</th>
            <th>Published</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($popular as $i => $row): ?>
          <tr>
            <td>
              <?php if ($i === 0): ?>
                <span style="font-size:1.2rem;">🥇</span>
              <?php elseif ($i === 1): ?>
                <span style="font-size:1.2rem;">🥈</span>
              <?php elseif ($i === 2): ?>
                <span style="font-size:1.2rem;">🥉</span>
              <?php else: ?>
                <span style="color:var(--muted);">#<?= $i+1 ?></span>
              <?php endif; ?>
            </td>
            <td>
              <a href="<?= BASE_URL ?>/detail.php?id=<?= $row['account_id'] ?>"
                 style="color:var(--accent);text-decoration:none;font-weight:600;">
                <?= htmlspecialchars($row['title']) ?>
              </a>
            </td>
            <td style="color:var(--muted)"><?= htmlspecialchars($row['category']) ?></td>
            <td style="color:var(--muted)">@<?= htmlspecialchars($row['creator']) ?></td>
            <td style="color:var(--accent3);font-weight:700;">$<?= number_format($row['price'], 2) ?></td>
            <td style="color:var(--success);font-weight:700;"><?= number_format($row['view_count']) ?></td>
            <td><?= number_format($row['avg_rating'], 1) ?> ★</td>
            <td><?= $row['comment_count'] ?></td>
            <td style="color:var(--muted);font-size:0.8rem;">
              <?= $row['published_at'] ? date('M j, Y', strtotime($row['published_at'])) : '—' ?>
            </td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <p style="color:var(--muted);font-size:0.8rem;margin-top:1rem;">
      Showing top <?= count($popular) ?> listings from
      <?= htmlspecialchars($r1From) ?> to <?= htmlspecialchars($r1To) ?>
    </p>
    <?php else: ?>
      <p style="color:var(--muted);">No data found for the selected date range.</p>
    <?php endif; ?>
  </div>

  <!-- ====== REPORT 2 ====== -->
  <div style="background:var(--surface);border:1px solid var(--border);padding:2rem;">

    <div style="font-family:'Orbitron',sans-serif;font-size:0.9rem;font-weight:700;
                letter-spacing:2px;text-transform:uppercase;color:var(--accent);
                margin-bottom:0.5rem;">
      👤 Report 2: Content by Creator
    </div>
    <p style="color:var(--muted);font-size:0.82rem;margin-bottom:1.5rem;">
      Uses stored procedure: <code style="color:var(--accent3);">sp_report_by_creator(user_id)</code>
    </p>

    <form method="get" style="display:flex;gap:1rem;flex-wrap:wrap;
                              align-items:flex-end;margin-bottom:1.5rem;">
      <input type="hidden" name="r1_from"  value="<?= htmlspecialchars($r1From) ?>">
      <input type="hidden" name="r1_to"    value="<?= htmlspecialchars($r1To) ?>">
      <input type="hidden" name="r1_limit" value="<?= $r1Limit ?>">
      <div class="form-group" style="margin:0;flex:1;min-width:200px;">
        <label class="form-label">Select Creator</label>
        <select class="form-select" name="r2_user">
          <option value="">-- Choose a creator --</option>
          <?php foreach ($creators as $c): ?>
            <option value="<?= $c['user_id'] ?>"
                    <?= $r2User === $c['user_id'] ? 'selected' : '' ?>>
              @<?= htmlspecialchars($c['username']) ?>
              (<?= htmlspecialchars($c['first_name'] . ' ' . $c['last_name']) ?>)
            </option>
          <?php endforeach; ?>
        </select>
      </div>
      <button type="submit" class="btn-primary" style="font-size:0.7rem;padding:0.75rem 1.5rem;">
        Generate
      </button>
    </form>

    <?php if ($r2User && !empty($r2Results)): ?>
      <?php
        $totalViews  = array_sum(array_column($r2Results, 'view_count'));
        $totalRating = count($r2Results)
            ? array_sum(array_column($r2Results, 'avg_rating')) / count($r2Results)
            : 0;
        $published   = count(array_filter($r2Results, fn($r) => $r['status'] === 'published'));
      ?>
      <div style="display:grid;grid-template-columns:repeat(4,1fr);gap:1rem;margin-bottom:1.5rem;">
        <div class="stat-card">
          <div class="stat-card-num"><?= count($r2Results) ?></div>
          <div class="stat-card-label">Total Listings</div>
        </div>
        <div class="stat-card">
          <div class="stat-card-num"><?= $published ?></div>
          <div class="stat-card-label">Published</div>
        </div>
        <div class="stat-card">
          <div class="stat-card-num"><?= number_format($totalViews) ?></div>
          <div class="stat-card-label">Total Views</div>
        </div>
        <div class="stat-card">
          <div class="stat-card-num"><?= number_format($totalRating, 1) ?> ★</div>
          <div class="stat-card-label">Avg Rating</div>
        </div>
      </div>

      <div style="overflow-x:auto;">
        <table class="data-table">
          <thead>
            <tr>
              <th>#</th><th>Title</th><th>Category</th><th>Status</th>
              <th>Price</th><th>Views</th><th>Rating</th><th>Comments</th>
              <th>Created</th><th>Published</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($r2Results as $row): ?>
            <tr>
              <td style="color:var(--muted)"><?= $row['account_id'] ?></td>
              <td>
                <a href="<?= BASE_URL ?>/detail.php?id=<?= $row['account_id'] ?>"
                   style="color:var(--accent);text-decoration:none;font-weight:600;">
                  <?= htmlspecialchars($row['title']) ?>
                </a>
              </td>
              <td style="color:var(--muted)"><?= htmlspecialchars($row['category']) ?></td>
              <td><span class="status-badge status-<?= $row['status'] ?>"><?= $row['status'] ?></span></td>
              <td style="color:var(--accent3);font-weight:700;">$<?= number_format($row['price'], 2) ?></td>
              <td><?= number_format($row['view_count']) ?></td>
              <td><?= number_format($row['avg_rating'], 1) ?> ★</td>
              <td><?= $row['comment_count'] ?></td>
              <td style="color:var(--muted);font-size:0.8rem;"><?= date('M j, Y', strtotime($row['created_at'])) ?></td>
              <td style="color:var(--muted);font-size:0.8rem;">
                <?= $row['published_at'] ? date('M j, Y', strtotime($row['published_at'])) : '—' ?>
              </td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php elseif ($r2User): ?>
      <p style="color:var(--muted);">No listings found for this creator.</p>
    <?php else: ?>
      <p style="color:var(--muted);">Select a creator above to generate the report.</p>
    <?php endif; ?>
  </div>

</div>
</div>

<?php include '../includes/footer.php'; ?>

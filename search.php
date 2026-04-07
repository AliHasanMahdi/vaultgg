<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

require_once 'includes/session.php';
require_once 'includes/config.php';

$pageTitle  = 'Search';
$activePage = 'search';

$dbc = getConnection();

// ---- Get categories for filter ----
$cats     = mysqli_query($dbc, "SELECT * FROM dbProj_categories ORDER BY sort_order");
$catsData = mysqli_fetch_all($cats, MYSQLI_ASSOC);
mysqli_free_result($cats);

// ---- Get search inputs ----
$q        = trim($_GET['q']         ?? '');
$cat      = trim($_GET['cat']       ?? '');
$dateFrom = trim($_GET['date_from'] ?? '');
$dateTo   = trim($_GET['date_to']   ?? '');
$creator  = trim($_GET['creator']   ?? '');
$sort     = trim($_GET['sort']      ?? 'newest');

$order = match($sort) {
    'popular'    => 'a.view_count DESC',
    'price_asc'  => 'a.price ASC',
    'price_desc' => 'a.price DESC',
    'rating'     => 'avg_rating DESC',
    default      => 'a.published_at DESC',
};

// ---- Build WHERE ----
$where  = ["a.status = 'published'"];
$params = [];
$types  = '';

if ($q) {
    $where[]  = "MATCH(a.title, a.short_desc, a.description, a.tags)
                 AGAINST(? IN BOOLEAN MODE)";
    $params[] = $q . '*';
    $types   .= 's';
}
if ($cat && $cat !== 'all') {
    $where[]  = 'c.slug = ?';
    $params[] = $cat;
    $types   .= 's';
}
if ($dateFrom) {
    $where[]  = 'DATE(a.published_at) >= ?';
    $params[] = $dateFrom;
    $types   .= 's';
}
if ($dateTo) {
    $where[]  = 'DATE(a.published_at) <= ?';
    $params[] = $dateTo;
    $types   .= 's';
}
if ($creator) {
    $where[]  = 'u.username LIKE ?';
    $params[] = '%' . $creator . '%';
    $types   .= 's';
}

$whereStr = implode(' AND ', $where);

// ---- Run query ----
$sql = "SELECT a.*, c.name AS cat_name, c.slug AS cat_slug,
               c.emoji AS cat_emoji, u.username AS creator_name,
               COALESCE(AVG(r.score), 0) AS avg_rating,
               COUNT(DISTINCT r.rating_id) AS rating_count
        FROM dbProj_accounts a
        JOIN dbProj_categories c ON a.cat_id = c.cat_id
        JOIN dbProj_users u ON a.creator_id = u.user_id
        LEFT JOIN dbProj_ratings r ON a.account_id = r.account_id
        WHERE $whereStr
        GROUP BY a.account_id
        ORDER BY $order
        LIMIT 20";

$stmt = mysqli_prepare($dbc, $sql);
if ($params) {
    mysqli_stmt_bind_param($stmt, $types, ...$params);
}
mysqli_stmt_execute($stmt);
$result   = mysqli_stmt_get_result($stmt);
$accounts = mysqli_fetch_all($result, MYSQLI_ASSOC);
mysqli_stmt_close($stmt);

include 'includes/header.php';
?>

<div class="section" style="padding-top:5rem;">
  <div class="section-title">🔍 Search Accounts</div>
  <div class="section-sub">Filter by game, date, creator or keyword</div>

  <!-- SEARCH FORM -->
  <form method="get"
        style="background:var(--surface);border:1px solid var(--border);
               padding:2rem;margin-bottom:2.5rem;">

    <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:1rem;margin-bottom:1rem;">

      <div class="form-group" style="margin:0;">
        <label class="form-label">Keyword</label>
        <input class="form-input" name="q" type="text"
               placeholder="Title, rank, tags..."
               value="<?= htmlspecialchars($q) ?>">
      </div>

      <div class="form-group" style="margin:0;">
        <label class="form-label">Creator / Seller</label>
        <input class="form-input" name="creator" type="text"
               placeholder="Username..."
               value="<?= htmlspecialchars($creator) ?>">
      </div>

      <div class="form-group" style="margin:0;">
        <label class="form-label">Category</label>
        <select class="form-select" name="cat">
          <option value="">All Games</option>
          <?php foreach ($catsData as $c): ?>
            <option value="<?= htmlspecialchars($c['slug']) ?>"
                    <?= $cat===$c['slug']?'selected':'' ?>>
              <?= $c['emoji'] . ' ' . htmlspecialchars($c['name']) ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>

    </div>

    <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:1rem;margin-bottom:1.5rem;">

      <div class="form-group" style="margin:0;">
        <label class="form-label">Date From</label>
        <input class="form-input" name="date_from" type="date"
               value="<?= htmlspecialchars($dateFrom) ?>">
      </div>

      <div class="form-group" style="margin:0;">
        <label class="form-label">Date To</label>
        <input class="form-input" name="date_to" type="date"
               value="<?= htmlspecialchars($dateTo) ?>">
      </div>

      <div class="form-group" style="margin:0;">
        <label class="form-label">Sort By</label>
        <select class="form-select" name="sort">
          <option value="newest"     <?= $sort==='newest'    ?'selected':'' ?>>Newest First</option>
          <option value="popular"    <?= $sort==='popular'   ?'selected':'' ?>>Most Popular</option>
          <option value="rating"     <?= $sort==='rating'    ?'selected':'' ?>>Top Rated</option>
          <option value="price_asc"  <?= $sort==='price_asc' ?'selected':'' ?>>Price: Low → High</option>
          <option value="price_desc" <?= $sort==='price_desc'?'selected':'' ?>>Price: High → Low</option>
        </select>
      </div>

    </div>

    <button type="submit" class="btn-primary"
            style="width:100%;font-size:0.8rem;padding:0.9rem;">
      🔍 Search Accounts
    </button>

  </form>

  <!-- RESULTS -->
  <?php if ($q || $cat || $dateFrom || $dateTo || $creator): ?>
    <p style="color:var(--muted);margin-bottom:1.5rem;">
      Found <strong style="color:var(--accent)"><?= count($accounts) ?></strong>
      result(s)
      <?php if ($q): ?>
        for "<strong style="color:var(--text)"><?= htmlspecialchars($q) ?></strong>"
      <?php endif; ?>
    </p>
  <?php endif; ?>

  <div class="accounts-grid">
    <?php if (empty($accounts)): ?>
      <div style="text-align:center;padding:4rem;color:var(--muted);grid-column:1/-1;">
        <div style="font-size:3rem;margin-bottom:1rem;">🎮</div>
        <div style="font-family:'Orbitron',sans-serif;">No accounts found</div>
        <div style="margin-top:0.5rem;font-size:0.85rem;">
          Try different search terms or filters
        </div>
      </div>
    <?php else: ?>
      <?php foreach ($accounts as $a):
        $emojis = ['fortnite'=>'⚡','valorant'=>'🎯','pubg'=>'🔫','fifa'=>'⚽'];
        $emoji  = $emojis[$a['cat_slug']] ?? '🎮';
        $tags   = array_filter(array_map('trim', explode(',', $a['tags'] ?? '')));
      ?>
      <a class="account-card"
         href="/~u202202670/vaultgg/detail.php?id=<?= $a['account_id'] ?>">
        <div class="card-banner card-banner-<?= htmlspecialchars($a['cat_slug']) ?>">
          <div class="card-game-label label-<?= htmlspecialchars($a['cat_slug']) ?>">
            <?= strtoupper(htmlspecialchars($a['cat_slug'])) ?>
          </div>
          <?php if ($a['is_hot']): ?>
            <div class="hot-badge">🔥 HOT</div>
          <?php endif; ?>
          <div class="card-banner-emoji"><?= $emoji ?></div>
        </div>
        <div class="card-body">
          <div class="card-title"><?= htmlspecialchars($a['title']) ?></div>
          <div class="card-stats">
            <?php if ($a['level_val']): ?>
              <div class="card-stat">Lvl <span><?= $a['level_val'] ?></span></div>
            <?php endif; ?>
            <div class="card-stat">Rank <span><?= htmlspecialchars($a['rank_label']) ?></span></div>
            <div class="card-stat">By <span>@<?= htmlspecialchars($a['creator_name']) ?></span></div>
          </div>
          <div style="color:var(--accent3);font-size:0.85rem;margin-bottom:0.75rem;">
            <?php
            $avg = round($a['avg_rating']);
            for ($i = 1; $i <= 5; $i++) echo $i <= $avg ? '★' : '☆';
            ?>
            <small style="color:var(--muted);">(<?= (int)$a['rating_count'] ?>)</small>
          </div>
          <?php if ($tags): ?>
          <div class="card-tags">
            <?php foreach ($tags as $t): ?>
              <span class="tag"><?= htmlspecialchars($t) ?></span>
            <?php endforeach; ?>
          </div>
          <?php endif; ?>
          <div class="card-footer">
            <div>
              <div class="card-price">$<?= number_format($a['price'], 2) ?></div>
              <div class="card-price-sub">
                Est. value $<?= number_format($a['original_val'], 2) ?>
              </div>
            </div>
            <div class="card-btn">View Deal →</div>
          </div>
        </div>
        <div class="card-verified"></div>
      </a>
      <?php endforeach; ?>
    <?php endif; ?>
  </div>

</div>

<?php include 'includes/footer.php'; ?>


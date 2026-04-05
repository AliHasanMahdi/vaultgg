<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

require_once 'includes/session.php';
require_once 'includes/config.php';

$pageTitle  = 'Game Account Marketplace';
$activePage = 'home';

$dbc = getConnection();

// ---- Get categories ----
$cats     = mysqli_query($dbc, "SELECT * FROM dbProj_categories ORDER BY sort_order");
$catsData = mysqli_fetch_all($cats, MYSQLI_ASSOC);
mysqli_free_result($cats);

// ---- Pagination ----
$page   = max(1, (int)($_GET['page'] ?? 1));
$limit  = ITEMS_PER_PAGE;
$offset = ($page - 1) * $limit;

// ---- Filter ----
$cat   = $_GET['cat']  ?? 'all';
$sort  = $_GET['sort'] ?? 'newest';
$order = match($sort) {
    'popular'    => 'a.view_count DESC',
    'price_asc'  => 'a.price ASC',
    'price_desc' => 'a.price DESC',
    default      => 'a.published_at DESC',
};

// ---- Count total ----
if ($cat !== 'all') {
    $countStmt = mysqli_prepare($dbc,
        "SELECT COUNT(DISTINCT a.account_id)
         FROM dbProj_accounts a
         JOIN dbProj_categories c ON a.cat_id = c.cat_id
         WHERE a.status = 'published' AND c.slug = ?");
    mysqli_stmt_bind_param($countStmt, 's', $cat);
} else {
    $countStmt = mysqli_prepare($dbc,
        "SELECT COUNT(*) FROM dbProj_accounts WHERE status = 'published'");
}
mysqli_stmt_execute($countStmt);
mysqli_stmt_bind_result($countStmt, $total);
mysqli_stmt_fetch($countStmt);
mysqli_stmt_close($countStmt);
$pages = (int)ceil($total / $limit);

// ---- Get accounts ----
if ($cat !== 'all') {
    $stmt = mysqli_prepare($dbc,
        "SELECT a.*, c.name AS cat_name, c.slug AS cat_slug,
                c.emoji AS cat_emoji,
                u.username AS creator_name,
                COALESCE(AVG(r.score), 0) AS avg_rating,
                COUNT(DISTINCT r.rating_id) AS rating_count
         FROM dbProj_accounts a
         JOIN dbProj_categories c ON a.cat_id = c.cat_id
         JOIN dbProj_users u ON a.creator_id = u.user_id
         LEFT JOIN dbProj_ratings r ON a.account_id = r.account_id
         WHERE a.status = 'published' AND c.slug = ?
         GROUP BY a.account_id
         ORDER BY $order
         LIMIT ? OFFSET ?");
    mysqli_stmt_bind_param($stmt, 'sii', $cat, $limit, $offset);
} else {
    $stmt = mysqli_prepare($dbc,
        "SELECT a.*, c.name AS cat_name, c.slug AS cat_slug,
                c.emoji AS cat_emoji,
                u.username AS creator_name,
                COALESCE(AVG(r.score), 0) AS avg_rating,
                COUNT(DISTINCT r.rating_id) AS rating_count
         FROM dbProj_accounts a
         JOIN dbProj_categories c ON a.cat_id = c.cat_id
         JOIN dbProj_users u ON a.creator_id = u.user_id
         LEFT JOIN dbProj_ratings r ON a.account_id = r.account_id
         WHERE a.status = 'published'
         GROUP BY a.account_id
         ORDER BY $order
         LIMIT ? OFFSET ?");
    mysqli_stmt_bind_param($stmt, 'ii', $limit, $offset);
}
mysqli_stmt_execute($stmt);
$result   = mysqli_stmt_get_result($stmt);
$accounts = mysqli_fetch_all($result, MYSQLI_ASSOC);
mysqli_stmt_close($stmt);

// ---- Site stats ----
$totalAcc  = mysqli_fetch_row(mysqli_query($dbc,
    'SELECT COUNT(*) FROM dbProj_accounts WHERE status="published"'))[0];
$avgRating = mysqli_fetch_row(mysqli_query($dbc,
    'SELECT COALESCE(AVG(score),0) FROM dbProj_ratings'))[0];

include 'includes/header.php';
?>

<!-- ===== HERO ===== -->
<div class="hero">
  <div class="hero-bg"></div>
  <div class="hero-grid"></div>
  <div class="hero-content">
    <div class="hero-badge">
      <span class="badge-dot"></span> Trusted Marketplace
    </div>
    <h1>
      <span class="h1-line1">Buy & Sell</span>
      <span class="h1-line2">Game Accounts</span>
    </h1>
    <p>The most secure marketplace for premium gaming accounts.
       Fortnite, PUBG, FIFA, Valorant — all verified & guaranteed.</p>
    <div class="hero-ctas">
      <a class="btn-primary" href="#listings">Browse Accounts</a>
      <?php if (isLoggedIn()): ?>
        <a class="btn-outline" href="/~u202202670/vaultgg/creator/index.php">My Panel</a>
      <?php else: ?>
        <a class="btn-outline" href="/~u202202670/vaultgg/login.php">Sell My Account</a>
      <?php endif; ?>
    </div>
  </div>

  <!-- STATS BAR -->
  <div class="stats-bar">
    <div class="stat">
      <div class="stat-num"><?= number_format($totalAcc) ?>+</div>
      <div class="stat-label">Accounts Listed</div>
    </div>
    <div class="stat">
      <div class="stat-num"><?= number_format($avgRating, 1) ?>★</div>
      <div class="stat-label">Avg Rating</div>
    </div>
    <div class="stat">
      <div class="stat-num">100%</div>
      <div class="stat-label">Verified</div>
    </div>
    <div class="stat">
      <div class="stat-num">24/7</div>
      <div class="stat-label">Support</div>
    </div>
  </div>
</div>

<!-- ===== LISTINGS ===== -->
<div class="section" id="listings">
  <div class="section-title">Featured Accounts</div>
  <div class="section-sub">All accounts verified & ready for instant delivery</div>

  <!-- SEARCH BAR -->
  <div style="display:flex;gap:1rem;margin-bottom:2rem;flex-wrap:wrap;">
    <input class="form-input" id="search-input" type="text"
           placeholder="🔍  Search accounts..."
           style="flex:1;min-width:220px;">
    <button class="nav-btn" onclick="doSearch()"
            style="padding:0.7rem 1.5rem;">Search</button>
  </div>

  <!-- SORT -->
  <form method="get" style="margin-bottom:1.5rem;display:flex;gap:1rem;align-items:center;">
    <input type="hidden" name="cat" value="<?= htmlspecialchars($cat) ?>">
    <label class="form-label" style="margin:0;">Sort by:</label>
    <select class="form-select" name="sort"
            style="width:auto;" onchange="this.form.submit()">
      <option value="newest"     <?= $sort==='newest'    ?'selected':'' ?>>Newest</option>
      <option value="popular"    <?= $sort==='popular'   ?'selected':'' ?>>Most Popular</option>
      <option value="price_asc"  <?= $sort==='price_asc' ?'selected':'' ?>>Price: Low → High</option>
      <option value="price_desc" <?= $sort==='price_desc'?'selected':'' ?>>Price: High → Low</option>
    </select>
  </form>

  <!-- CATEGORY FILTERS -->
  <div style="display:flex;gap:0.75rem;flex-wrap:wrap;margin-bottom:2rem;">
    <a style="clip-path:polygon(6px 0%,100% 0%,calc(100% - 6px) 100%,0% 100%);
              padding:0.5rem 1.2rem;font-family:'Orbitron',sans-serif;font-size:0.75rem;
              font-weight:700;letter-spacing:1px;text-decoration:none;
              background:<?= $cat==='all'?'var(--accent)':'var(--surface2)' ?>;
              color:<?= $cat==='all'?'#000':'var(--muted)' ?>;
              border:1px solid <?= $cat==='all'?'var(--accent)':'var(--border)' ?>;"
       href="?cat=all&sort=<?= htmlspecialchars($sort) ?>">All Games</a>

    <?php foreach ($catsData as $c):
      $active = $cat === $c['slug'];
    ?>
    <a style="clip-path:polygon(6px 0%,100% 0%,calc(100% - 6px) 100%,0% 100%);
              padding:0.5rem 1.2rem;font-family:'Orbitron',sans-serif;font-size:0.75rem;
              font-weight:700;letter-spacing:1px;text-decoration:none;
              background:<?= $active?'var(--'.$c['slug'].')':'var(--surface2)' ?>;
              color:<?= $active?'#000':'var(--muted)' ?>;
              border:1px solid <?= $active?'var(--'.$c['slug'].')':'var(--border)' ?>;"
       href="?cat=<?= htmlspecialchars($c['slug']) ?>&sort=<?= htmlspecialchars($sort) ?>">
      <?= $c['emoji'] . ' ' . htmlspecialchars($c['name']) ?>
    </a>
    <?php endforeach; ?>
  </div>

  <!-- CARDS GRID -->
  <div class="accounts-grid" id="accounts-grid">
    <?php if (empty($accounts)): ?>
      <div style="text-align:center;padding:4rem;color:var(--muted);grid-column:1/-1;">
        <div style="font-size:3rem;margin-bottom:1rem;">🎮</div>
        <div style="font-family:'Orbitron',sans-serif;">No accounts found</div>
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
            <?php if ($a['skins_count']): ?>
              <div class="card-stat">Skins <span><?= $a['skins_count'] ?></span></div>
            <?php endif; ?>
            <div class="card-stat">Rank <span><?= htmlspecialchars($a['rank_label']) ?></span></div>
          </div>
          <div style="color:var(--accent3);font-size:0.85rem;margin-bottom:0.75rem;">
            <?php
            $avg = round($a['avg_rating']);
            for ($i = 1; $i <= 5; $i++) {
                echo $i <= $avg ? '★' : '☆';
            }
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

  <!-- PAGINATION -->
  <?php if ($pages > 1): ?>
  <div class="pagination">
    <?php if ($page > 1): ?>
      <a class="page-btn"
         href="?cat=<?= $cat ?>&sort=<?= $sort ?>&page=<?= $page-1 ?>">← Prev</a>
    <?php endif; ?>
    <?php for ($i = 1; $i <= $pages; $i++): ?>
      <a class="page-btn <?= $i===$page?'active':'' ?>"
         href="?cat=<?= $cat ?>&sort=<?= $sort ?>&page=<?= $i ?>"><?= $i ?></a>
    <?php endfor; ?>
    <?php if ($page < $pages): ?>
      <a class="page-btn"
         href="?cat=<?= $cat ?>&sort=<?= $sort ?>&page=<?= $page+1 ?>">Next →</a>
    <?php endif; ?>
  </div>
  <?php endif; ?>

</div>

<script>
function doSearch() {
  const q = document.getElementById('search-input').value.trim();
  if (q) window.location.href = '/~u202202670/vaultgg/search.php?q='
                                + encodeURIComponent(q);
}
document.getElementById('search-input').addEventListener('keydown', e => {
  if (e.key === 'Enter') doSearch();
});
</script>

<?php include 'includes/footer.php'; ?>
<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

require_once 'includes/session.php';
require_once 'includes/config.php';

$dbc = getConnection();

// ---- Pagination & Filters ----
$page   = max(1, (int)($_GET['page'] ?? 1));
$limit  = ITEMS_PER_PAGE;
$offset = ($page - 1) * $limit;
$cat    = $_GET['cat']  ?? 'all';
$sort   = $_GET['sort'] ?? 'newest';
$order  = match($sort) {
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

// ---- Build cards HTML ----
ob_start();
if (empty($accounts)): ?>
  <div style="text-align:center;padding:4rem;color:var(--muted);grid-column:1/-1;">
    <div style="font-size:3rem;margin-bottom:1rem;">🎮</div>
    <div style="font-family:'Orbitron',sans-serif;">No accounts found</div>
  </div>
<?php else:
  foreach ($accounts as $a):
    $emojis = ['fortnite'=>'⚡','valorant'=>'🎯','pubg'=>'🔫','fifa'=>'⚽'];
    $emoji  = $emojis[$a['cat_slug']] ?? '🎮';
    $tags   = array_filter(array_map('trim', explode(',', $a['tags'] ?? '')));
?>
  <a class="account-card" href="<?= BASE_URL ?>/detail.php?id=<?= $a['account_id'] ?>">
    <div class="card-banner card-banner-<?= htmlspecialchars($a['cat_slug']) ?>" style="position:relative;overflow:hidden;">
      <?php if (!empty($a['image_path'])): ?>
        <img src="<?= BASE_URL ?>/<?= htmlspecialchars($a['image_path']) ?>"
             alt="" style="position:absolute;inset:0;width:100%;height:100%;object-fit:contain;object-position:center;">
      <?php else: ?>
        <div class="card-banner-emoji"><?= $emoji ?></div>
      <?php endif; ?>
      <div class="card-game-label label-<?= htmlspecialchars($a['cat_slug']) ?>" style="position:relative;z-index:1;">
        <?= strtoupper(htmlspecialchars($a['cat_slug'])) ?>
      </div>
      <?php if ($a['is_hot']): ?>
        <div class="hot-badge" style="position:relative;z-index:1;">🔥 HOT</div>
      <?php endif; ?>
    </div>
    <div class="card-body">
      <div class="card-title"><?= htmlspecialchars($a['title']) ?></div>
      <div class="card-stats">
        <?php if ($a['level_val']): ?><div class="card-stat">Lvl <span><?= $a['level_val'] ?></span></div><?php endif; ?>
        <?php if ($a['skins_count']): ?><div class="card-stat">Skins <span><?= $a['skins_count'] ?></span></div><?php endif; ?>
        <div class="card-stat">Rank <span><?= htmlspecialchars($a['rank_label']) ?></span></div>
      </div>
      <div style="color:var(--accent3);font-size:0.85rem;margin-bottom:0.75rem;">
        <?php $avg = round($a['avg_rating']); for ($i=1;$i<=5;$i++) echo $i<=$avg?'★':'☆'; ?>
        <small style="color:var(--muted);">(<?= (int)$a['rating_count'] ?>)</small>
      </div>
      <?php if ($tags): ?>
      <div class="card-tags">
        <?php foreach ($tags as $t): ?><span class="tag"><?= htmlspecialchars($t) ?></span><?php endforeach; ?>
      </div>
      <?php endif; ?>
      <div class="card-footer">
        <div>
          <div class="card-price">$<?= number_format($a['price'], 2) ?></div>
          <div class="card-price-sub">Est. value $<?= number_format($a['original_val'], 2) ?></div>
        </div>
        <div class="card-btn">View Deal →</div>
      </div>
    </div>
    <div class="card-verified"></div>
  </a>
<?php
  endforeach;
endif;
$cardsHtml = ob_get_clean();

// ---- Build pagination HTML ----
ob_start();
if ($pages > 1):
?>
<div class="pagination">
  <?php if ($page > 1): ?>
    <a class="page-btn ajax-page" data-page="<?= $page-1 ?>" href="#">← Prev</a>
  <?php endif; ?>
  <?php for ($i = 1; $i <= $pages; $i++): ?>
    <a class="page-btn ajax-page <?= $i===$page?'active':'' ?>" data-page="<?= $i ?>" href="#"><?= $i ?></a>
  <?php endfor; ?>
  <?php if ($page < $pages): ?>
    <a class="page-btn ajax-page" data-page="<?= $page+1 ?>" href="#">Next →</a>
  <?php endif; ?>
</div>
<?php
endif;
$paginationHtml = ob_get_clean();

// ---- If AJAX request: return JSON and exit ----
if (isset($_GET['ajax'])) {
    header('Content-Type: application/json');
    echo json_encode([
        'html'       => $cardsHtml,
        'pagination' => $paginationHtml,
        'total'      => $total,
    ]);
    exit;
}

// ---- Full page load below ----
$cats     = mysqli_query($dbc, "SELECT * FROM dbProj_categories ORDER BY sort_order");
$catsData = mysqli_fetch_all($cats, MYSQLI_ASSOC);

$totalAcc  = mysqli_fetch_row(mysqli_query($dbc, 'SELECT COUNT(*) FROM dbProj_accounts WHERE status="published"'))[0];
$avgRating = mysqli_fetch_row(mysqli_query($dbc, 'SELECT COALESCE(AVG(score),0) FROM dbProj_ratings'))[0];

$pageTitle  = 'Game Account Marketplace';
$activePage = 'home';
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
        <a class="btn-outline" href="<?= BASE_URL ?>/creator/index.php">My Panel</a>
      <?php else: ?>
        <a class="btn-outline" href="<?= BASE_URL ?>/login.php">Sell My Account</a>
      <?php endif; ?>
    </div>
  </div>
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
    <button class="nav-btn" onclick="doSearch()" style="padding:0.7rem 1.5rem;">Search</button>
  </div>

  <!-- SORT -->
  <div style="margin-bottom:1.5rem;display:flex;gap:1rem;align-items:center;">
    <label class="form-label" style="margin:0;">Sort by:</label>
    <select class="form-select" id="sort-select" style="width:auto;">
      <option value="newest"     <?= $sort==='newest'    ?'selected':'' ?>>Newest</option>
      <option value="popular"    <?= $sort==='popular'   ?'selected':'' ?>>Most Popular</option>
      <option value="price_asc"  <?= $sort==='price_asc' ?'selected':'' ?>>Price: Low → High</option>
      <option value="price_desc" <?= $sort==='price_desc'?'selected':'' ?>>Price: High → Low</option>
    </select>
  </div>

  <!-- CATEGORY FILTERS -->
  <div style="display:flex;gap:0.75rem;flex-wrap:wrap;margin-bottom:2rem;" id="cat-filters">

    <button class="cat-btn <?= $cat==='all'?'cat-btn-active':'' ?>"
            data-cat="all"
            style="clip-path:polygon(6px 0%,100% 0%,calc(100% - 6px) 100%,0% 100%);
                   padding:0.5rem 1.2rem;font-family:'Orbitron',sans-serif;font-size:0.75rem;
                   font-weight:700;letter-spacing:1px;border:none;cursor:pointer;
                   background:<?= $cat==='all'?'var(--accent)':'var(--surface2)' ?>;
                   color:<?= $cat==='all'?'#000':'var(--muted)' ?>;
                   outline:1px solid <?= $cat==='all'?'var(--accent)':'var(--border)' ?>;">
      All Games
    </button>

    <?php foreach ($catsData as $c):
      $isActive = $cat === $c['slug'];
    ?>
    <button class="cat-btn <?= $isActive?'cat-btn-active':'' ?>"
            data-cat="<?= htmlspecialchars($c['slug']) ?>"
            style="clip-path:polygon(6px 0%,100% 0%,calc(100% - 6px) 100%,0% 100%);
                   padding:0.5rem 1.2rem;font-family:'Orbitron',sans-serif;font-size:0.75rem;
                   font-weight:700;letter-spacing:1px;border:none;cursor:pointer;
                   background:<?= $isActive?'var(--'.$c['slug'].')':'var(--surface2)' ?>;
                   color:<?= $isActive?'#000':'var(--muted)' ?>;
                   outline:1px solid <?= $isActive?'var(--'.$c['slug'].')':'var(--border)' ?>;">
      <?= $c['emoji'] . ' ' . htmlspecialchars($c['name']) ?>
    </button>
    <?php endforeach; ?>
  </div>

  <!-- RESULTS COUNT -->
  <div id="results-count" style="color:var(--muted);font-size:0.85rem;margin-bottom:1rem;">
    Showing <strong style="color:var(--accent)"><?= $total ?></strong> account<?= $total!=1?'s':'' ?>
  </div>

  <!-- LOADING SPINNER (hidden by default) -->
  <div id="grid-loading" style="display:none;text-align:center;padding:3rem;grid-column:1/-1;">
    <div style="width:40px;height:40px;border:3px solid var(--border);
                border-top-color:var(--accent);border-radius:50%;
                animation:spin 0.7s linear infinite;margin:0 auto 1rem;"></div>
    <div style="font-family:'Orbitron',sans-serif;font-size:0.75rem;
                letter-spacing:2px;color:var(--muted);">LOADING...</div>
  </div>

  <!-- CARDS GRID -->
  <div class="accounts-grid" id="accounts-grid">
    <?= $cardsHtml ?>
  </div>

  <!-- PAGINATION -->
  <div id="pagination-wrap">
    <?= $paginationHtml ?>
  </div>

</div>

<style>
@keyframes spin { to { transform:rotate(360deg); } }
.cat-btn { transition: background 0.15s, color 0.15s, outline-color 0.15s; }
.cat-btn:hover { opacity: 0.85; }
#accounts-grid.loading { opacity: 0.4; pointer-events: none; transition: opacity 0.2s; }
</style>

<script>
var currentCat  = '<?= addslashes($cat) ?>';
var currentSort = '<?= addslashes($sort) ?>';
var currentPage = <?= $page ?>;

var catColors = {
  'all':      { bg: 'var(--accent)',    color: '#000',         outline: 'var(--accent)' },
  'fortnite': { bg: 'var(--fortnite)',  color: '#000',         outline: 'var(--fortnite)' },
  'valorant': { bg: 'var(--valorant)',  color: '#fff',         outline: 'var(--valorant)' },
  'pubg':     { bg: 'var(--pubg)',      color: '#000',         outline: 'var(--pubg)' },
  'fifa':     { bg: 'var(--fifa)',      color: '#000',         outline: 'var(--fifa)' },
};

function fetchAccounts(cat, sort, page) {
  currentCat  = cat;
  currentSort = sort;
  currentPage = page;

  // Update URL without reload
  var url = '<?= BASE_URL ?>/index.php?cat=' + cat + '&sort=' + sort + '&page=' + page;
  window.history.pushState({cat:cat, sort:sort, page:page}, '', url);

  // Update active category button styles
  document.querySelectorAll('.cat-btn').forEach(function(btn) {
    var btnCat = btn.getAttribute('data-cat');
    var active = btnCat === cat;
    var c = catColors[btnCat] || { bg:'var(--surface2)', color:'var(--muted)', outline:'var(--border)' };
    if (active) {
      btn.style.background    = c.bg;
      btn.style.color         = c.color;
      btn.style.outlineColor  = c.outline;
    } else {
      btn.style.background    = 'var(--surface2)';
      btn.style.color         = 'var(--muted)';
      btn.style.outlineColor  = 'var(--border)';
    }
  });

  // Show loading state
  var grid = document.getElementById('accounts-grid');
  grid.classList.add('loading');
  document.getElementById('grid-loading').style.display = 'block';

  // AJAX fetch
  $.getJSON('<?= BASE_URL ?>/index.php', { ajax:1, cat:cat, sort:sort, page:page }, function(data) {
    grid.innerHTML = data.html;
    document.getElementById('pagination-wrap').innerHTML = data.pagination;
    document.getElementById('results-count').innerHTML =
      'Showing <strong style="color:var(--accent)">' + data.total + '</strong> account' + (data.total != 1 ? 's' : '');

    // Bind pagination clicks
    bindPaginationClicks();

    grid.classList.remove('loading');
    document.getElementById('grid-loading').style.display = 'none';

    // Smooth scroll to listings
    document.getElementById('listings').scrollIntoView({ behavior: 'smooth', block: 'start' });
  });
}

function bindPaginationClicks() {
  document.querySelectorAll('.ajax-page').forEach(function(link) {
    link.addEventListener('click', function(e) {
      e.preventDefault();
      fetchAccounts(currentCat, currentSort, parseInt(this.getAttribute('data-page')));
    });
  });
}

// Category buttons
document.querySelectorAll('.cat-btn').forEach(function(btn) {
  btn.addEventListener('click', function() {
    var cat = this.getAttribute('data-cat');
    fetchAccounts(cat, currentSort, 1);
  });
});

// Sort dropdown
document.getElementById('sort-select').addEventListener('change', function() {
  fetchAccounts(currentCat, this.value, 1);
});

// Pagination (initial page load)
bindPaginationClicks();

// Browser back/forward
window.addEventListener('popstate', function(e) {
  if (e.state) {
    document.getElementById('sort-select').value = e.state.sort;
    fetchAccounts(e.state.cat, e.state.sort, e.state.page);
  }
});

// Search
function doSearch() {
  var q = document.getElementById('search-input').value.trim();
  if (q) window.location.href = '<?= BASE_URL ?>/search.php?q=' + encodeURIComponent(q);
}
document.getElementById('search-input').addEventListener('keydown', function(e) {
  if (e.key === 'Enter') doSearch();
});
</script>

<?php include 'includes/footer.php'; ?>
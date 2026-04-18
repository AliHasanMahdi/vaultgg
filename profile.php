<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

require_once 'includes/session.php';
require_once 'includes/config.php';

if (!isLoggedIn()) {
    header('Location: /~u202202670/vaultgg/login.php');
    exit;
}

$dbc    = getConnection();
$userId = $_SESSION['user_id'];

// ---- Handle wishlist remove ----
if (isset($_GET['remove_wish'])) {
    $wid  = (int)$_GET['remove_wish'];
    $stmt = mysqli_prepare($dbc,
        "DELETE FROM dbProj_wishlist WHERE wishlist_id = ? AND user_id = ?");
    mysqli_stmt_bind_param($stmt, 'ii', $wid, $userId);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);
    header('Location: /~u202202670/vaultgg/profile.php');
    exit;
}

// ---- Handle replacement request ----
// ---- Handle refund request ----
if (isset($_GET['refund'])) {
    $pid = (int)$_GET['refund'];

    // Step 1: Get account_id from this purchase
    $sel = mysqli_prepare($dbc,
        "SELECT account_id FROM dbProj_purchases
         WHERE purchase_id = ? AND user_id = ?");
    mysqli_stmt_bind_param($sel, 'ii', $pid, $userId);
    mysqli_stmt_execute($sel);
    mysqli_stmt_bind_result($sel, $accountId);
    mysqli_stmt_fetch($sel);
    mysqli_stmt_close($sel);

    if ($accountId) {
        // Step 2: Mark purchase as refunded
        $upd1 = mysqli_prepare($dbc,
            "UPDATE dbProj_purchases SET status = 'refunded'
             WHERE purchase_id = ? AND user_id = ?");
        mysqli_stmt_bind_param($upd1, 'ii', $pid, $userId);
        mysqli_stmt_execute($upd1);
        mysqli_stmt_close($upd1);

        // Step 3: Put account back to published
        $upd2 = mysqli_prepare($dbc,
            "UPDATE dbProj_accounts SET status = 'published'
             WHERE account_id = ?");
        mysqli_stmt_bind_param($upd2, 'i', $accountId);
        mysqli_stmt_execute($upd2);
        mysqli_stmt_close($upd2);
    }

    header('Location: /~u202202670/vaultgg/profile.php?refunded=1');
    exit;
}

// ---- Get user info ----
$stmt = mysqli_prepare($dbc,
    "SELECT * FROM dbProj_users WHERE user_id = ?");
mysqli_stmt_bind_param($stmt, 'i', $userId);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$user   = mysqli_fetch_assoc($result);
mysqli_stmt_close($stmt);

// ---- Get purchases ----
$stmt = mysqli_prepare($dbc,
    "SELECT p.*, a.title, a.price, a.rank_label,
            c.name AS cat_name, c.slug AS cat_slug, c.emoji AS cat_emoji
     FROM dbProj_purchases p
     JOIN dbProj_accounts a ON p.account_id = a.account_id
     JOIN dbProj_categories c ON a.cat_id = c.cat_id
     WHERE p.user_id = ?
     ORDER BY p.created_at DESC");
mysqli_stmt_bind_param($stmt, 'i', $userId);
mysqli_stmt_execute($stmt);
$result    = mysqli_stmt_get_result($stmt);
$purchases = mysqli_fetch_all($result, MYSQLI_ASSOC);
mysqli_stmt_close($stmt);

// ---- Get wishlist ----
$stmt = mysqli_prepare($dbc,
    "SELECT w.*, a.title, a.price, a.original_val, a.rank_label, a.status AS acc_status,
            c.name AS cat_name, c.slug AS cat_slug, c.emoji AS cat_emoji
     FROM dbProj_wishlist w
     JOIN dbProj_accounts a ON w.account_id = a.account_id
     JOIN dbProj_categories c ON a.cat_id = c.cat_id
     WHERE w.user_id = ?
     ORDER BY w.created_at DESC");
mysqli_stmt_bind_param($stmt, 'i', $userId);
mysqli_stmt_execute($stmt);
$result   = mysqli_stmt_get_result($stmt);
$wishlist = mysqli_fetch_all($result, MYSQLI_ASSOC);
mysqli_stmt_close($stmt);

// ---- Stats ----
$totalSpent    = array_sum(array_column($purchases, 'amount_paid'));
$totalPurchases = count($purchases);
$totalWishlist  = count($wishlist);

$pageTitle  = 'My Profile';
$activePage = 'profile';
include 'includes/header.php';
?>

<div style="padding-top:64px;min-height:100vh;">
<div style="max-width:1100px;margin:0 auto;padding:3rem 2rem;">

  <?php if (isset($_GET['refunded'])): ?>
  <div style="background:rgba(16,185,129,0.1);border:1px solid rgba(16,185,129,0.3);
              color:var(--success);padding:1rem 1.5rem;margin-bottom:2rem;
              font-family:'Orbitron',sans-serif;font-size:0.8rem;letter-spacing:1px;">
    ✅ Refund request submitted! Please allow 3–5 business days for processing.
  </div>
  <?php endif; ?>

  <!-- PROFILE HEADER -->
  <div style="display:flex;align-items:center;gap:2rem;margin-bottom:3rem;
              background:var(--surface);border:1px solid var(--border);
              padding:2rem;flex-wrap:wrap;
              clip-path:polygon(0 0,calc(100% - 20px) 0,100% 20px,100% 100%,20px 100%,0 calc(100% - 20px));">
    <!-- AVATAR -->
    <div style="width:80px;height:80px;flex-shrink:0;
                background:linear-gradient(135deg,var(--accent2),var(--accent));
                display:flex;align-items:center;justify-content:center;
                font-family:'Orbitron',sans-serif;font-size:1.8rem;
                font-weight:900;color:#fff;
                clip-path:polygon(8px 0%,100% 0%,calc(100% - 8px) 100%,0% 100%);">
      <?= strtoupper(substr($user['username'], 0, 2)) ?>
    </div>
    <div style="flex:1;">
      <div style="font-family:'Orbitron',sans-serif;font-size:1.5rem;
                  font-weight:900;margin-bottom:0.25rem;">
        @<?= htmlspecialchars($user['username']) ?>
      </div>
      <div style="color:var(--muted);font-size:0.9rem;">
        <?= htmlspecialchars($user['first_name'] . ' ' . $user['last_name']) ?>
        • <?= htmlspecialchars($user['email']) ?>
      </div>
      <div style="margin-top:0.5rem;">
        <span style="background:rgba(0,240,255,0.1);border:1px solid rgba(0,240,255,0.3);
                     color:var(--accent);font-size:0.7rem;font-weight:700;
                     letter-spacing:2px;padding:0.2rem 0.6rem;text-transform:uppercase;">
          <?= strtoupper($user['role']) ?>
        </span>
        <span style="color:var(--muted);font-size:0.8rem;margin-left:1rem;">
          Member since <?= date('M Y', strtotime($user['created_at'])) ?>
        </span>
      </div>
    </div>
  </div>

  <!-- STATS -->
  <div style="display:grid;grid-template-columns:repeat(3,1fr);
              gap:1.5rem;margin-bottom:3rem;">
    <div style="background:var(--surface);border:1px solid var(--border);
                padding:1.5rem;text-align:center;
                clip-path:polygon(0 0,calc(100% - 14px) 0,100% 14px,100% 100%,14px 100%,0 calc(100% - 14px));">
      <div style="font-family:'Orbitron',sans-serif;font-size:2rem;
                  font-weight:900;color:var(--accent);">
        <?= $totalPurchases ?>
      </div>
      <div style="color:var(--muted);font-size:0.8rem;letter-spacing:1px;
                  text-transform:uppercase;margin-top:0.4rem;">Purchases</div>
    </div>
    <div style="background:var(--surface);border:1px solid var(--border);
                padding:1.5rem;text-align:center;
                clip-path:polygon(0 0,calc(100% - 14px) 0,100% 14px,100% 100%,14px 100%,0 calc(100% - 14px));">
      <div style="font-family:'Orbitron',sans-serif;font-size:2rem;
                  font-weight:900;color:var(--accent3);">
        $<?= number_format($totalSpent, 2) ?>
      </div>
      <div style="color:var(--muted);font-size:0.8rem;letter-spacing:1px;
                  text-transform:uppercase;margin-top:0.4rem;">Total Spent</div>
    </div>
    <div style="background:var(--surface);border:1px solid var(--border);
                padding:1.5rem;text-align:center;
                clip-path:polygon(0 0,calc(100% - 14px) 0,100% 14px,100% 100%,14px 100%,0 calc(100% - 14px));">
      <div style="font-family:'Orbitron',sans-serif;font-size:2rem;
                  font-weight:900;color:var(--accent2);">
        <?= $totalWishlist ?>
      </div>
      <div style="color:var(--muted);font-size:0.8rem;letter-spacing:1px;
                  text-transform:uppercase;margin-top:0.4rem;">Wishlist</div>
    </div>
  </div>

  <!-- TABS -->
  <div style="display:flex;border-bottom:1px solid var(--border);
              margin-bottom:2rem;">
    <button onclick="showTab('purchases')" id="tab-purchases"
            style="flex:1;background:none;border:none;
                   color:var(--accent);font-family:'Rajdhani',sans-serif;
                   font-size:0.95rem;font-weight:700;letter-spacing:1px;
                   text-transform:uppercase;padding:0.75rem;cursor:pointer;
                   border-bottom:2px solid var(--accent);margin-bottom:-1px;">
      🎮 My Purchases (<?= $totalPurchases ?>)
    </button>
    <button onclick="showTab('wishlist')" id="tab-wishlist"
            style="flex:1;background:none;border:none;
                   color:var(--muted);font-family:'Rajdhani',sans-serif;
                   font-size:0.95rem;font-weight:700;letter-spacing:1px;
                   text-transform:uppercase;padding:0.75rem;cursor:pointer;
                   border-bottom:2px solid transparent;margin-bottom:-1px;">
      ❤️ Wishlist (<?= $totalWishlist ?>)
    </button>
  </div>

  <!-- PURCHASES TAB -->
  <div id="tab-purchases-content">
    <?php if (empty($purchases)): ?>
      <div style="text-align:center;padding:4rem;color:var(--muted);">
        <div style="font-size:3rem;margin-bottom:1rem;">🛒</div>
        <div style="font-family:'Orbitron',sans-serif;margin-bottom:1rem;">
          No purchases yet
        </div>
        <a class="btn-primary"
           href="/~u202202670/vaultgg/index.php">Browse Accounts</a>
      </div>
    <?php else: ?>
      <?php foreach ($purchases as $p):
        $emojis = ['fortnite'=>'⚡','valorant'=>'🎯','pubg'=>'🔫','fifa'=>'⚽'];
        $emoji  = $emojis[$p['cat_slug']] ?? '🎮';
      ?>
      <div style="background:var(--surface);border:1px solid var(--border);
                  padding:1.5rem;margin-bottom:1rem;display:flex;
                  align-items:center;gap:1.5rem;flex-wrap:wrap;
                  clip-path:polygon(0 0,calc(100% - 14px) 0,100% 14px,100% 100%,0 100%);">

        <!-- GAME ICON -->
        <div class="card-banner card-banner-<?= htmlspecialchars($p['cat_slug']) ?>"
             style="width:80px;height:80px;flex-shrink:0;border-radius:0;
                    display:flex;align-items:center;justify-content:center;
                    font-size:2.5rem;clip-path:none;">
          <?= $emoji ?>
        </div>

        <!-- INFO -->
        <div style="flex:1;min-width:200px;">
          <div style="font-family:'Orbitron',sans-serif;font-size:0.95rem;
                      font-weight:700;margin-bottom:0.4rem;">
            <?= htmlspecialchars($p['title']) ?>
          </div>
          <div style="color:var(--muted);font-size:0.85rem;margin-bottom:0.4rem;">
            <?= $p['cat_emoji'] ?> <?= htmlspecialchars($p['cat_name']) ?>
            • <?= htmlspecialchars($p['rank_label']) ?>
          </div>
          <div style="font-size:0.8rem;color:var(--muted);">
            Purchased: <?= date('M j, Y', strtotime($p['created_at'])) ?>
          </div>
        </div>

        <!-- PRICE -->
        <div style="text-align:center;">
          <div style="font-family:'Orbitron',sans-serif;font-size:1.3rem;
                      font-weight:900;color:var(--accent3);">
            $<?= number_format($p['amount_paid'], 2) ?>
          </div>
          <div style="font-size:0.75rem;color:var(--muted);">paid</div>
        </div>

        <!-- STATUS + ACTIONS -->
        <div style="text-align:right;min-width:150px;">
          <?php if ($p['status'] === 'active'): ?>
            <span style="background:rgba(16,185,129,0.15);
                         color:var(--success);
                         border:1px solid rgba(16,185,129,0.3);
                         font-size:0.65rem;font-weight:700;letter-spacing:1px;
                         padding:0.2rem 0.6rem;text-transform:uppercase;
                         display:block;margin-bottom:0.75rem;">
              ✅ Active
            </span>
            <a href="/~u202202670/vaultgg/profile.php?refund=<?= $p['purchase_id'] ?>"
               style="display:block;background:rgba(245,158,11,0.15);
                      border:1px solid rgba(245,158,11,0.3);
                      color:var(--accent3);font-family:'Orbitron',sans-serif;
                      font-size:0.6rem;font-weight:700;letter-spacing:1px;
                      padding:0.4rem 0.75rem;text-decoration:none;
                      text-transform:uppercase;text-align:center;
                      margin-bottom:0.5rem;"
               onclick="return confirm('Request a refund for this account?')">
              💸 Request Refund
            </a>
          <?php elseif ($p['status'] === 'refunded'): ?>
  <span style="background:rgba(239,68,68,0.15);
               color:var(--danger);
               border:1px solid rgba(239,68,68,0.3);
               font-size:0.65rem;font-weight:700;letter-spacing:1px;
               padding:0.2rem 0.6rem;text-transform:uppercase;
               display:block;margin-bottom:0.75rem;">
    💸 Refund Pending
  </span>
  <span style="color:var(--muted);font-size:0.75rem;
               display:block;line-height:1.5;">
    Your refund request has been received. Please allow 3–5 business days for processing.
  </span>
          <?php else: ?>
            <span style="background:rgba(239,68,68,0.15);
                         color:var(--danger);
                         border:1px solid rgba(239,68,68,0.3);
                         font-size:0.65rem;font-weight:700;letter-spacing:1px;
                         padding:0.2rem 0.6rem;text-transform:uppercase;">
              Cancelled
            </span>
          <?php endif; ?>
        </div>

      </div>
      <?php endforeach; ?>
    <?php endif; ?>
  </div>

  <!-- WISHLIST TAB -->
  <div id="tab-wishlist-content" style="display:none;">
    <?php if (empty($wishlist)): ?>
      <div style="text-align:center;padding:4rem;color:var(--muted);">
        <div style="font-size:3rem;margin-bottom:1rem;">❤️</div>
        <div style="font-family:'Orbitron',sans-serif;margin-bottom:1rem;">
          Your wishlist is empty
        </div>
        <a class="btn-primary"
           href="/~u202202670/vaultgg/index.php">Browse Accounts</a>
      </div>
    <?php else: ?>
      <div class="accounts-grid">
        <?php foreach ($wishlist as $w):
          $emojis = ['fortnite'=>'⚡','valorant'=>'🎯','pubg'=>'🔫','fifa'=>'⚽'];
          $emoji  = $emojis[$w['cat_slug']] ?? '🎮';
          $avail  = $w['acc_status'] === 'published';
        ?>
        <div style="position:relative;">
          <a class="account-card"
             href="/~u202202670/vaultgg/detail.php?id=<?= $w['account_id'] ?>"
             style="<?= !$avail ? 'opacity:0.5;pointer-events:none;' : '' ?>">
            <div class="card-banner card-banner-<?= htmlspecialchars($w['cat_slug']) ?>">
              <div class="card-game-label label-<?= htmlspecialchars($w['cat_slug']) ?>">
                <?= strtoupper(htmlspecialchars($w['cat_slug'])) ?>
              </div>
              <?php if (!$avail): ?>
                <div style="position:absolute;top:0.75rem;right:0.75rem;
                            background:var(--danger);color:#fff;
                            font-family:'Orbitron',sans-serif;font-size:0.55rem;
                            font-weight:700;padding:0.2rem 0.5rem;">SOLD</div>
              <?php endif; ?>
              <div class="card-banner-emoji"><?= $emoji ?></div>
            </div>
            <div class="card-body">
              <div class="card-title">
                <?= htmlspecialchars($w['title']) ?>
              </div>
              <div class="card-stats">
                <div class="card-stat">
                  Rank <span><?= htmlspecialchars($w['rank_label']) ?></span>
                </div>
              </div>
              <div class="card-footer">
                <div>
                  <div class="card-price">
                    $<?= number_format($w['price'], 2) ?>
                  </div>
                </div>
                <div class="card-btn">View →</div>
              </div>
            </div>
            <div class="card-verified"></div>
          </a>
          <!-- REMOVE FROM WISHLIST -->
          <a href="/~u202202670/vaultgg/profile.php?remove_wish=<?= $w['wishlist_id'] ?>"
             style="position:absolute;top:0.5rem;right:0.5rem;z-index:10;
                    background:rgba(239,68,68,0.9);color:#fff;border:none;
                    font-size:0.7rem;padding:0.3rem 0.6rem;cursor:pointer;
                    text-decoration:none;font-weight:700;"
             onclick="return confirm('Remove from wishlist?')">✕</a>
        </div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </div>

</div>
</div>

<script>
function showTab(tab) {
  // Hide all
  document.getElementById('tab-purchases-content').style.display = 'none';
  document.getElementById('tab-wishlist-content').style.display  = 'none';

  // Reset tabs
  document.getElementById('tab-purchases').style.color       = 'var(--muted)';
  document.getElementById('tab-purchases').style.borderBottom = '2px solid transparent';
  document.getElementById('tab-wishlist').style.color         = 'var(--muted)';
  document.getElementById('tab-wishlist').style.borderBottom  = '2px solid transparent';

  // Show selected
  document.getElementById('tab-' + tab + '-content').style.display = 'block';
  document.getElementById('tab-' + tab).style.color       = 'var(--accent)';
  document.getElementById('tab-' + tab).style.borderBottom = '2px solid var(--accent)';
}
</script>

<?php include 'includes/footer.php'; ?>


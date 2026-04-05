<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

require_once 'includes/session.php';
require_once 'includes/config.php';

$id  = (int)($_GET['id'] ?? 0);
$dbc = getConnection();

// ---- Get account ----
$stmt = mysqli_prepare($dbc,
    "SELECT a.*, c.name AS cat_name, c.slug AS cat_slug, c.emoji AS cat_emoji,
            u.username AS creator_name,
            COALESCE(AVG(r.score), 0) AS avg_rating,
            COUNT(DISTINCT r.rating_id) AS rating_count
     FROM dbProj_accounts a
     JOIN dbProj_categories c ON a.cat_id = c.cat_id
     JOIN dbProj_users u ON a.creator_id = u.user_id
     LEFT JOIN dbProj_ratings r ON a.account_id = r.account_id
     WHERE a.account_id = ? AND a.status = 'published'
     GROUP BY a.account_id");
mysqli_stmt_bind_param($stmt, 'i', $id);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$a      = mysqli_fetch_assoc($result);
mysqli_stmt_close($stmt);

// ---- Not found ----
if (!$a) {
    $pageTitle  = 'Not Found';
    $activePage = '';
    include 'includes/header.php';
    echo '
    <div style="text-align:center;padding:8rem 2rem;">
        <div style="font-size:4rem;margin-bottom:1rem;">🔍</div>
        <div style="font-family:Orbitron,sans-serif;font-size:1.5rem;margin-bottom:1rem;">
            Account Not Found
        </div>
        <a class="btn-primary" href="/~u202202670/vaultgg/index.php">
            Back to Listings
        </a>
    </div>';
    include 'includes/footer.php';
    exit;
}

// ---- Increment view count ----
mysqli_query($dbc,
    "UPDATE dbProj_accounts SET view_count = view_count + 1
     WHERE account_id = $id");

// ---- Get comments ----
$cStmt = mysqli_prepare($dbc,
    "SELECT cm.*, u.username
     FROM dbProj_comments cm
     JOIN dbProj_users u ON cm.user_id = u.user_id
     WHERE cm.account_id = ? AND cm.is_removed = 0
     ORDER BY cm.created_at DESC");
mysqli_stmt_bind_param($cStmt, 'i', $id);
mysqli_stmt_execute($cStmt);
$cResult  = mysqli_stmt_get_result($cStmt);
$comments = mysqli_fetch_all($cResult, MYSQLI_ASSOC);
mysqli_stmt_close($cStmt);

// ---- Get user rating ----
$userRating = 0;
if (isLoggedIn()) {
    $rStmt = mysqli_prepare($dbc,
        "SELECT score FROM dbProj_ratings
         WHERE account_id = ? AND user_id = ?");
    mysqli_stmt_bind_param($rStmt, 'ii', $id, $_SESSION['user_id']);
    mysqli_stmt_execute($rStmt);
    mysqli_stmt_bind_result($rStmt, $userRating);
    mysqli_stmt_fetch($rStmt);
    mysqli_stmt_close($rStmt);
}

// ---- Handle comment POST ----
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['comment'])) {
    if (!isLoggedIn()) {
        header('Location: /~u202202670/vaultgg/login.php');
        exit;
    }
    $body = trim($_POST['body'] ?? '');
    if (strlen($body) >= 2) {
        $ins = mysqli_prepare($dbc,
            "INSERT INTO dbProj_comments (account_id, user_id, body)
             VALUES (?, ?, ?)");
        mysqli_stmt_bind_param($ins, 'iis', $id, $_SESSION['user_id'], $body);
        mysqli_stmt_execute($ins);
        mysqli_stmt_close($ins);
        header("Location: /~u202202670/vaultgg/detail.php?id=$id");
        exit;
    }
}

// ---- Handle rating POST ----
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['rate'])) {
    if (!isLoggedIn()) {
        header('Location: /~u202202670/vaultgg/login.php');
        exit;
    }
    $score = (int)($_POST['score'] ?? 0);
    if ($score >= 1 && $score <= 5) {
        $ins = mysqli_prepare($dbc,
            "INSERT INTO dbProj_ratings (account_id, user_id, score)
             VALUES (?, ?, ?)
             ON DUPLICATE KEY UPDATE score = ?");
        mysqli_stmt_bind_param($ins, 'iiii',
            $id, $_SESSION['user_id'], $score, $score);
        mysqli_stmt_execute($ins);
        mysqli_stmt_close($ins);
        header("Location: /~u202202670/vaultgg/detail.php?id=$id");
        exit;
    }
}

$savings = round((1 - $a['price'] / $a['original_val']) * 100);
$perks   = json_decode($a['perks'] ?? '[]', true) ?: [];
$emojis  = ['fortnite'=>'⚡','valorant'=>'🎯','pubg'=>'🔫','fifa'=>'⚽'];
$emoji   = $emojis[$a['cat_slug']] ?? '🎮';

$pageTitle  = $a['title'];
$activePage = '';
include 'includes/header.php';
?>

<div style="padding-top:64px;">
<div style="max-width:1100px;margin:0 auto;padding:3rem 2rem;">

  <a href="/~u202202670/vaultgg/index.php"
     style="display:inline-flex;align-items:center;gap:0.5rem;
            color:var(--muted);text-decoration:none;
            font-family:'Rajdhani',sans-serif;font-size:0.9rem;
            font-weight:600;letter-spacing:1px;text-transform:uppercase;
            margin-bottom:2rem;transition:color 0.2s;"
     onmouseover="this.style.color='var(--accent)'"
     onmouseout="this.style.color='var(--muted)'">
    ← Back to Listings
  </a>

  <div style="display:grid;grid-template-columns:1fr 380px;gap:2.5rem;">

    <!-- LEFT COLUMN -->
    <div>
      <!-- Banner -->
      <div class="card-banner card-banner-<?= htmlspecialchars($a['cat_slug']) ?>"
           style="height:280px;margin-bottom:1.5rem;position:relative;
                  clip-path:polygon(0 0,calc(100% - 24px) 0,100% 24px,100% 100%,24px 100%,0 calc(100% - 24px));">
        <div style="position:absolute;inset:0;display:flex;align-items:center;
                    justify-content:center;font-size:7rem;opacity:0.18;">
          <?= $emoji ?>
        </div>
        <div class="label-<?= htmlspecialchars($a['cat_slug']) ?>"
             style="position:absolute;top:1.25rem;left:1.25rem;
                    font-family:'Orbitron',sans-serif;font-size:0.7rem;
                    font-weight:700;letter-spacing:2px;padding:0.4rem 1rem;text-transform:uppercase;">
          <?= strtoupper(htmlspecialchars($a['cat_slug'])) ?>
        </div>
      </div>

      <!-- Title -->
      <div style="font-family:'Orbitron',sans-serif;font-size:1.8rem;
                  font-weight:900;margin-bottom:0.75rem;line-height:1.2;">
        <?= htmlspecialchars($a['title']) ?>
      </div>

      <!-- Stats row -->
      <div style="display:flex;gap:2rem;margin-bottom:2rem;flex-wrap:wrap;">
        <?php if ($a['level_val']): ?>
        <div style="text-align:center;">
          <div style="font-family:'Orbitron',sans-serif;font-size:1.3rem;
                      font-weight:700;color:var(--accent);">
            <?= $a['level_val'] ?>
          </div>
          <div style="font-size:0.75rem;letter-spacing:1px;
                      text-transform:uppercase;color:var(--muted);">Level</div>
        </div>
        <?php endif; ?>
        <?php if ($a['skins_count']): ?>
        <div style="text-align:center;">
          <div style="font-family:'Orbitron',sans-serif;font-size:1.3rem;
                      font-weight:700;color:var(--accent);">
            <?= $a['skins_count'] ?>
          </div>
          <div style="font-size:0.75rem;letter-spacing:1px;
                      text-transform:uppercase;color:var(--muted);">Skins</div>
        </div>
        <?php endif; ?>
        <?php if ($a['wins_count']): ?>
        <div style="text-align:center;">
          <div style="font-family:'Orbitron',sans-serif;font-size:1.3rem;
                      font-weight:700;color:var(--accent);">
            <?= number_format($a['wins_count']) ?>
          </div>
          <div style="font-size:0.75rem;letter-spacing:1px;
                      text-transform:uppercase;color:var(--muted);">Wins</div>
        </div>
        <?php endif; ?>
        <div style="text-align:center;">
          <div style="font-family:'Orbitron',sans-serif;font-size:1.3rem;
                      font-weight:700;color:var(--accent);">
            <?= htmlspecialchars($a['rank_label']) ?>
          </div>
          <div style="font-size:0.75rem;letter-spacing:1px;
                      text-transform:uppercase;color:var(--muted);">Rank</div>
        </div>
        <div style="text-align:center;">
          <div style="font-size:1.1rem;color:var(--accent3);">
            <?php
            $avg = round($a['avg_rating']);
            for ($i = 1; $i <= 5; $i++) echo $i <= $avg ? '★' : '☆';
            ?>
          </div>
          <div style="font-size:0.75rem;letter-spacing:1px;
                      text-transform:uppercase;color:var(--muted);">
            Rating (<?= (int)$a['rating_count'] ?>)
          </div>
        </div>
        <div style="text-align:center;">
          <div style="font-family:'Orbitron',sans-serif;font-size:1.3rem;
                      font-weight:700;color:var(--accent);">
            <?= number_format($a['view_count']) ?>
          </div>
          <div style="font-size:0.75rem;letter-spacing:1px;
                      text-transform:uppercase;color:var(--muted);">Views</div>
        </div>
      </div>

      <!-- Perks -->
      <?php if ($perks): ?>
      <div style="font-family:'Orbitron',sans-serif;font-size:0.8rem;font-weight:700;
                  letter-spacing:2px;text-transform:uppercase;color:var(--muted);
                  margin-bottom:1rem;border-bottom:1px solid var(--border);padding-bottom:0.5rem;
                  margin-top:2rem;">
        What's Included
      </div>
      <div style="display:grid;grid-template-columns:1fr 1fr;gap:0.75rem;margin-bottom:2rem;">
        <?php foreach ($perks as $p):
          $parts = explode(' ', $p, 2);
          $icon  = $parts[0];
          $label = $parts[1] ?? '';
        ?>
        <div style="display:flex;align-items:center;gap:0.75rem;
                    background:var(--surface2);border:1px solid var(--border);
                    padding:0.75rem 1rem;font-size:0.85rem;font-weight:600;">
          <span style="font-size:1.2rem;"><?= $icon ?></span>
          <span><?= htmlspecialchars($label) ?></span>
        </div>
        <?php endforeach; ?>
      </div>
      <?php endif; ?>

      <!-- Description -->
      <div style="font-family:'Orbitron',sans-serif;font-size:0.8rem;font-weight:700;
                  letter-spacing:2px;text-transform:uppercase;color:var(--muted);
                  margin-bottom:1rem;border-bottom:1px solid var(--border);padding-bottom:0.5rem;">
        Account Description
      </div>
      <p style="color:var(--muted);line-height:1.8;font-size:0.95rem;margin-bottom:2rem;">
        <?= nl2br(htmlspecialchars($a['description'])) ?>
      </p>

      <!-- Creator -->
      <div style="font-family:'Orbitron',sans-serif;font-size:0.8rem;font-weight:700;
                  letter-spacing:2px;text-transform:uppercase;color:var(--muted);
                  margin-bottom:1rem;border-bottom:1px solid var(--border);padding-bottom:0.5rem;">
        About the Creator
      </div>
      <div style="background:var(--surface2);border:1px solid var(--border);
                  padding:1.25rem;margin-bottom:2rem;
                  clip-path:polygon(0 0,calc(100% - 12px) 0,100% 12px,100% 100%,0 100%);">
        <div style="display:flex;align-items:center;gap:0.75rem;">
          <div style="width:40px;height:40px;
                      background:linear-gradient(135deg,var(--accent2),var(--accent));
                      display:flex;align-items:center;justify-content:center;
                      font-family:'Orbitron',sans-serif;font-size:0.8rem;
                      font-weight:700;color:#fff;flex-shrink:0;">
            <?= strtoupper(substr($a['creator_name'], 0, 2)) ?>
          </div>
          <div>
            <div style="font-weight:700;">@<?= htmlspecialchars($a['creator_name']) ?></div>
            <div style="font-size:0.75rem;color:var(--muted);">Verified Creator</div>
          </div>
        </div>
      </div>

      <!-- RATE THIS ACCOUNT -->
      <div style="font-family:'Orbitron',sans-serif;font-size:0.8rem;font-weight:700;
                  letter-spacing:2px;text-transform:uppercase;color:var(--muted);
                  margin-bottom:1rem;border-bottom:1px solid var(--border);padding-bottom:0.5rem;">
        Rate This Account
      </div>
      <?php if (isLoggedIn()): ?>
      <form method="post" style="margin-bottom:2rem;">
        <input type="hidden" name="rate" value="1">
        <div style="display:flex;gap:0.5rem;margin-bottom:1rem;">
          <?php for ($i = 1; $i <= 5; $i++): ?>
          <button type="submit" name="score" value="<?= $i ?>"
                  style="font-size:2rem;background:none;border:none;
                         cursor:pointer;color:<?= $i <= $userRating ? 'var(--accent3)' : 'var(--muted)' ?>;">
            ★
          </button>
          <?php endfor; ?>
        </div>
        <p style="color:var(--muted);font-size:0.8rem;">
          <?= $userRating ? "You rated this $userRating/5. Click to update." : 'Click a star to rate.' ?>
        </p>
      </form>
      <?php else: ?>
        <p style="color:var(--muted);margin-bottom:2rem;">
          <a href="/~u202202670/vaultgg/login.php"
             style="color:var(--accent);">Login</a> to rate this account.
        </p>
      <?php endif; ?>

      <!-- COMMENTS -->
      <div style="font-family:'Orbitron',sans-serif;font-size:0.8rem;font-weight:700;
                  letter-spacing:2px;text-transform:uppercase;color:var(--muted);
                  margin-bottom:1rem;border-bottom:1px solid var(--border);padding-bottom:0.5rem;">
        Comments (<?= count($comments) ?>)
      </div>

      <?php if (isLoggedIn()): ?>
      <form method="post" style="margin-bottom:2rem;">
        <input type="hidden" name="comment" value="1">
        <div class="form-group">
          <textarea class="form-textarea" name="body" rows="3"
                    placeholder="Share your experience..."
                    required></textarea>
        </div>
        <button type="submit" class="btn-primary"
                style="font-size:0.7rem;padding:0.65rem 1.5rem;">
          Post Comment
        </button>
      </form>
      <?php else: ?>
        <p style="color:var(--muted);margin-bottom:1.5rem;">
          <a href="/~u202202670/vaultgg/login.php"
             style="color:var(--accent);">Login</a> to leave a comment.
        </p>
      <?php endif; ?>

      <!-- COMMENTS LIST -->
      <?php if (empty($comments)): ?>
        <p style="color:var(--muted);">No comments yet. Be the first!</p>
      <?php else: ?>
        <?php foreach ($comments as $c): ?>
        <div style="background:var(--surface2);border:1px solid var(--border);
                    padding:1rem;margin-bottom:1rem;
                    clip-path:polygon(0 0,calc(100% - 10px) 0,100% 10px,100% 100%,0 100%);">
          <div style="display:flex;justify-content:space-between;
                      margin-bottom:0.5rem;font-size:0.8rem;color:var(--muted);">
            <span style="color:var(--accent);font-weight:700;">
              @<?= htmlspecialchars($c['username']) ?>
            </span>
            <span><?= date('M j, Y', strtotime($c['created_at'])) ?></span>
          </div>
          <div style="font-size:0.9rem;line-height:1.6;">
            <?= nl2br(htmlspecialchars($c['body'])) ?>
          </div>
          <?php if (isAdmin()): ?>
          <form method="post" action="/~u202202670/vaultgg/admin/remove_comment.php"
                style="margin-top:0.5rem;">
            <input type="hidden" name="comment_id"
                   value="<?= $c['comment_id'] ?>">
            <input type="hidden" name="account_id" value="<?= $id ?>">
            <button type="submit"
                    style="background:rgba(239,68,68,0.1);border:1px solid rgba(239,68,68,0.3);
                           color:var(--danger);font-size:0.6rem;padding:0.2rem 0.6rem;cursor:pointer;"
                    onclick="return confirm('Remove this comment?')">
              Remove
            </button>
          </form>
          <?php endif; ?>
        </div>
        <?php endforeach; ?>
      <?php endif; ?>
    </div>

    <!-- RIGHT COLUMN: PURCHASE BOX -->
    <div>
      <div style="background:var(--surface);border:1px solid var(--border);
                  padding:2rem;position:sticky;top:80px;
                  clip-path:polygon(0 0,calc(100% - 20px) 0,100% 20px,100% 100%,20px 100%,0 calc(100% - 20px));">

        <div style="font-family:'Orbitron',sans-serif;font-size:2.5rem;
                    font-weight:900;color:var(--accent3);margin-bottom:0.25rem;">
          $<?= number_format($a['price'], 2) ?>
        </div>
        <div style="font-size:1rem;color:var(--muted);
                    text-decoration:line-through;margin-bottom:0.5rem;">
          Original: $<?= number_format($a['original_val'], 2) ?>
        </div>
        <div style="display:inline-block;background:rgba(16,185,129,0.15);
                    color:var(--success);border:1px solid rgba(16,185,129,0.3);
                    font-size:0.75rem;font-weight:700;letter-spacing:1px;
                    padding:0.2rem 0.6rem;margin-bottom:1.5rem;text-transform:uppercase;">
          You Save <?= $savings ?>%
        </div>

        <?php if (isLoggedIn()): ?>
          <button onclick="alert('⚡ Redirecting to checkout... (demo)')"
                  style="width:100%;background:linear-gradient(135deg,var(--accent2),var(--accent));
                         border:none;color:#fff;font-family:'Orbitron',sans-serif;
                         font-size:0.8rem;font-weight:700;letter-spacing:2px;padding:1rem;
                         cursor:pointer;text-transform:uppercase;margin-bottom:0.75rem;
                         clip-path:polygon(10px 0%,100% 0%,calc(100% - 10px) 100%,0% 100%);">
            ⚡ Buy Now – Instant Delivery
          </button>
        <?php else: ?>
          <a href="/~u202202670/vaultgg/login.php"
             style="display:block;width:100%;background:linear-gradient(135deg,var(--accent2),var(--accent));
                    color:#fff;font-family:'Orbitron',sans-serif;font-size:0.8rem;
                    font-weight:700;letter-spacing:2px;padding:1rem;text-align:center;
                    text-decoration:none;text-transform:uppercase;margin-bottom:0.75rem;
                    clip-path:polygon(10px 0%,100% 0%,calc(100% - 10px) 100%,0% 100%);">
            🔐 Login to Purchase
          </a>
        <?php endif; ?>

        <button onclick="alert('❤️ Added to wishlist!')"
                style="width:100%;background:transparent;border:1px solid var(--border);
                       color:var(--muted);font-family:'Rajdhani',sans-serif;font-size:0.85rem;
                       font-weight:600;letter-spacing:1px;padding:0.75rem;cursor:pointer;
                       text-transform:uppercase;margin-bottom:1.5rem;
                       clip-path:polygon(10px 0%,100% 0%,calc(100% - 10px) 100%,0% 100%);">
          ♡ Add to Wishlist
        </button>

        <!-- Guarantees -->
        <div style="border-top:1px solid var(--border);padding-top:1.25rem;">
          <?php
          $guarantees = [
            '✓ Verified & Tested Account',
            '✓ Full Email & Password Transfer',
            '✓ 24/7 Customer Support',
            '✓ 30-Day Replacement Guarantee',
            '✓ Secure Encrypted Payment',
          ];
          foreach ($guarantees as $g):
          ?>
          <div style="display:flex;align-items:center;gap:0.75rem;padding:0.5rem 0;
                      font-size:0.85rem;color:var(--muted);
                      border-bottom:1px solid var(--border);">
            <span style="color:var(--success);"><?= $g ?></span>
          </div>
          <?php endforeach; ?>
        </div>
      </div>
    </div>

  </div>
</div>
</div>

<?php include 'includes/footer.php'; ?>

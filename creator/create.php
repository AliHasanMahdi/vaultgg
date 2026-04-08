<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

require_once '../includes/session.php';
require_once '../includes/config.php';

if (!isLoggedIn() || (!isCreator() && !isAdmin())) {
    header('Location: /~u202202670/vaultgg/login.php');
    exit;
}

$dbc    = getConnection();
$errors = [];

// ---- Get categories ----
$cats     = mysqli_query($dbc, "SELECT * FROM dbProj_categories ORDER BY sort_order");
$catsData = mysqli_fetch_all($cats, MYSQLI_ASSOC);
mysqli_free_result($cats);

// ---- Handle POST ----
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $title       = trim($_POST['title']       ?? '');
    $cat_id      = (int)($_POST['cat_id']     ?? 0);
    $short_desc  = trim($_POST['short_desc']  ?? '');
    $description = trim($_POST['description'] ?? '');
    $price       = (float)($_POST['price']    ?? 0);
    $original_val= (float)($_POST['original_val'] ?? 0);
    $rank_label  = trim($_POST['rank_label']  ?? 'Unranked');
    $level_val   = $_POST['level_val']   !== '' ? (int)$_POST['level_val']   : null;
    $skins_count = $_POST['skins_count'] !== '' ? (int)$_POST['skins_count'] : null;
    $wins_count  = $_POST['wins_count']  !== '' ? (int)$_POST['wins_count']  : null;
    $tags        = trim($_POST['tags']   ?? '');
    $is_hot      = isset($_POST['is_hot']) ? 1 : 0;
    $perks_raw   = trim($_POST['perks_raw'] ?? '');

    // Validation
    if (!$title)        $errors[] = 'Title is required.';
    if (!$cat_id)       $errors[] = 'Please select a category.';
    if (!$short_desc)   $errors[] = 'Short description is required.';
    if (!$description)  $errors[] = 'Full description is required.';
    if ($price <= 0)    $errors[] = 'Price must be greater than 0.';
    if ($original_val <= 0) $errors[] = 'Original value must be greater than 0.';

    if (empty($errors)) {
        // Build perks JSON
        $perksArr  = array_values(array_filter(
            array_map('trim', explode("\n", $perks_raw))
        ));
        $perksJson = json_encode($perksArr);

        // Build slug
        $slug = strtolower(trim($title));
        $slug = preg_replace('/[^a-z0-9]+/', '-', $slug);
        $slug = trim($slug, '-') . '-' . substr(uniqid(), -5);

        $stmt = mysqli_prepare($dbc,
            "INSERT INTO dbProj_accounts
             (creator_id, cat_id, title, slug, short_desc, description,
              price, original_val, rank_label, level_val, skins_count,
              wins_count, perks, tags, is_hot, status)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'draft')");

        mysqli_stmt_bind_param($stmt, 'iissssddsiiissi',
            $_SESSION['user_id'],
            $cat_id,
            $title,
            $slug,
            $short_desc,
            $description,
            $price,
            $original_val,
            $rank_label,
            $level_val,
            $skins_count,
            $wins_count,
            $perksJson,
            $tags,
            $is_hot
        );

        if (mysqli_stmt_execute($stmt)) {
            mysqli_stmt_close($stmt);
            header('Location: /~u202202670/vaultgg/creator/index.php');
            exit;
        } else {
            $errors[] = 'Database error: ' . mysqli_error($dbc);
        }
    }
}

$pageTitle  = 'Create Listing';
$activePage = 'creator';
include '../includes/header.php';
?>

<div class="panel-wrapper">
<div class="panel-container">

  <div class="panel-header">
    <div>
      <div class="panel-title">Create New Listing</div>
      <div class="panel-subtitle">Fill in all details carefully</div>
    </div>
    <a class="btn-outline"
       href="/~u202202670/vaultgg/creator/index.php">← Back</a>
  </div>

  <?php if ($errors): ?>
    <div class="error-msg" style="display:block;margin-bottom:1.5rem;">
      <?= implode('<br>', array_map('htmlspecialchars', $errors)) ?>
    </div>
  <?php endif; ?>

  <form method="post" style="max-width:800px;">

    <div style="display:grid;grid-template-columns:1fr 1fr;gap:1.5rem;">

      <!-- LEFT -->
      <div>
        <div class="form-group">
          <label class="form-label">Title *</label>
          <input class="form-input" name="title" type="text"
                 placeholder="e.g. OG Black Knight Account"
                 value="<?= htmlspecialchars($_POST['title'] ?? '') ?>"
                 required>
        </div>

        <div class="form-group">
          <label class="form-label">Category *</label>
          <select class="form-select" name="cat_id" required>
            <option value="">-- Select Game --</option>
            <?php foreach ($catsData as $c): ?>
              <option value="<?= $c['cat_id'] ?>"
                      <?= ($_POST['cat_id'] ?? 0) == $c['cat_id'] ? 'selected' : '' ?>>
                <?= $c['emoji'] . ' ' . htmlspecialchars($c['name']) ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>

        <div class="form-group">
          <label class="form-label">Price (USD) *</label>
          <input class="form-input" name="price" type="number"
                 step="0.01" min="1" placeholder="e.g. 150.00"
                 value="<?= htmlspecialchars($_POST['price'] ?? '') ?>"
                 required>
        </div>

        <div class="form-group">
          <label class="form-label">Original / Estimated Value (USD) *</label>
          <input class="form-input" name="original_val" type="number"
                 step="0.01" min="1" placeholder="e.g. 400.00"
                 value="<?= htmlspecialchars($_POST['original_val'] ?? '') ?>"
                 required>
        </div>

        <div class="form-group">
          <label class="form-label">Rank</label>
          <input class="form-input" name="rank_label" type="text"
                 placeholder="e.g. Champion, Radiant, Diamond"
                 value="<?= htmlspecialchars($_POST['rank_label'] ?? '') ?>">
        </div>
      </div>

      <!-- RIGHT -->
      <div>
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:1rem;">
          <div class="form-group">
            <label class="form-label">Level</label>
            <input class="form-input" name="level_val" type="number"
                   min="1" placeholder="e.g. 200"
                   value="<?= htmlspecialchars($_POST['level_val'] ?? '') ?>">
          </div>
          <div class="form-group">
            <label class="form-label">Skins Count</label>
            <input class="form-input" name="skins_count" type="number"
                   min="0" placeholder="e.g. 50"
                   value="<?= htmlspecialchars($_POST['skins_count'] ?? '') ?>">
          </div>
        </div>

        <div class="form-group">
          <label class="form-label">Total Wins</label>
          <input class="form-input" name="wins_count" type="number"
                 min="0" placeholder="e.g. 500"
                 value="<?= htmlspecialchars($_POST['wins_count'] ?? '') ?>">
        </div>

        <div class="form-group">
          <label class="form-label">
            Tags
            <small style="color:var(--muted);text-transform:none;
                          letter-spacing:0;">(comma separated)</small>
          </label>
          <input class="form-input" name="tags" type="text"
                 placeholder="OG Skins, Champion, 1000+ Wins"
                 value="<?= htmlspecialchars($_POST['tags'] ?? '') ?>">
        </div>

        <div class="form-group">
          <label class="form-label">
            Perks / What's Included
            <small style="color:var(--muted);text-transform:none;
                          letter-spacing:0;">(one per line, start with emoji)</small>
          </label>
          <textarea class="form-textarea" name="perks_raw" rows="5"
                    placeholder="⚡ Black Knight Skin&#10;🏆 142 Skins&#10;🎯 1820 Wins"
                    ><?= htmlspecialchars($_POST['perks_raw'] ?? '') ?></textarea>
        </div>

        <div class="form-group">
          <label style="display:flex;align-items:center;gap:0.75rem;cursor:pointer;">
            <input type="checkbox" name="is_hot"
                   style="accent-color:var(--danger);width:16px;height:16px;"
                   <?= isset($_POST['is_hot']) ? 'checked' : '' ?>>
            <span class="form-label" style="margin:0;">🔥 Mark as HOT listing</span>
          </label>
        </div>
      </div>

    </div>

    <div class="form-group">
      <label class="form-label">
        Short Description *
        <small style="color:var(--muted);text-transform:none;
                      letter-spacing:0;">(max 300 chars, shown on card)</small>
      </label>
      <textarea class="form-textarea" name="short_desc"
                rows="2" maxlength="300"
                placeholder="A brief summary shown on the listing card..."
                required><?= htmlspecialchars($_POST['short_desc'] ?? '') ?></textarea>
    </div>

    <div class="form-group">
      <label class="form-label">Full Description *</label>
      <textarea class="form-textarea" name="description"
                rows="6"
                placeholder="Detailed description of the account..."
                required><?= htmlspecialchars($_POST['description'] ?? '') ?></textarea>
    </div>

    <button type="submit" class="form-submit">
      💾 Save as Draft
    </button>
    <p style="color:var(--muted);font-size:0.8rem;margin-top:0.75rem;">
      After saving you can publish the listing from your panel.
    </p>

  </form>

</div>
</div>

<?php include '../includes/footer.php'; ?>
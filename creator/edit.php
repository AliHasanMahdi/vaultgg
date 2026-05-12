<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

require_once '../includes/session.php';
require_once '../includes/config.php';

if (!isLoggedIn() || (!isCreator() && !isAdmin())) {
    header('Location: ' . BASE_URL . '/login.php');
    exit;
}

$dbc    = getConnection();
$userId = $_SESSION['user_id'];
$id     = (int)($_GET['id'] ?? 0);

// ---- Load the account (must belong to this creator, unless admin) ----
if (isAdmin()) {
    $stmt = mysqli_prepare($dbc,
        "SELECT * FROM dbProj_accounts WHERE account_id = ?");
    mysqli_stmt_bind_param($stmt, 'i', $id);
} else {
    $stmt = mysqli_prepare($dbc,
        "SELECT * FROM dbProj_accounts WHERE account_id = ? AND creator_id = ?");
    mysqli_stmt_bind_param($stmt, 'ii', $id, $userId);
}
mysqli_stmt_execute($stmt);
$result  = mysqli_stmt_get_result($stmt);
$account = mysqli_fetch_assoc($result);
mysqli_stmt_close($stmt);

if (!$account) {
    header('Location: ' . BASE_URL . '/creator/index.php');
    exit;
}

// ---- Get categories ----
$cats     = mysqli_query($dbc, "SELECT * FROM dbProj_categories ORDER BY sort_order");
$catsData = mysqli_fetch_all($cats, MYSQLI_ASSOC);
mysqli_free_result($cats);

$errors = [];

// ---- Handle POST ----
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $title        = trim($_POST['title']        ?? '');
    $cat_id       = (int)($_POST['cat_id']      ?? 0);
    $short_desc   = trim($_POST['short_desc']   ?? '');
    $description  = trim($_POST['description']  ?? '');
    $price        = (float)($_POST['price']     ?? 0);
    $original_val = (float)($_POST['original_val'] ?? 0);
    $rank_label   = trim($_POST['rank_label']   ?? 'Unranked');
    $level_val    = $_POST['level_val']   !== '' ? (int)$_POST['level_val']   : null;
    $skins_count  = $_POST['skins_count'] !== '' ? (int)$_POST['skins_count'] : null;
    $wins_count   = $_POST['wins_count']  !== '' ? (int)$_POST['wins_count']  : null;
    $tags         = trim($_POST['tags']   ?? '');
    $is_hot       = isset($_POST['is_hot']) ? 1 : 0;
    $perks_raw    = trim($_POST['perks_raw'] ?? '');

    // Validation
    if (!$title)            $errors[] = 'Title is required.';
    if (!$cat_id)           $errors[] = 'Please select a category.';
    if (!$short_desc)       $errors[] = 'Short description is required.';
    if (!$description)      $errors[] = 'Full description is required.';
    if ($price <= 0)        $errors[] = 'Price must be greater than 0.';
    if ($original_val <= 0) $errors[] = 'Original value must be greater than 0.';

    // Handle multiple image uploads (optional on edit)
    $new_image_path = null;
    $new_gallery    = [];
    $allowed        = ['jpg','jpeg','png','gif','webp'];
    $uploadDir      = dirname(__DIR__) . '/uploads/listings/';
    if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);

    $files = $_FILES['listing_images'] ?? [];
    if (!empty($files['name'][0])) {
        foreach ($files['name'] as $i => $fname) {
            if (empty($fname)) continue;
            $ext = strtolower(pathinfo($fname, PATHINFO_EXTENSION));
            if (!in_array($ext, $allowed)) {
                $errors[] = "File $fname: must be jpg, jpeg, png, gif, or webp."; continue;
            }
            if ($files['size'][$i] > 5 * 1024 * 1024) {
                $errors[] = "File $fname: must be under 5 MB."; continue;
            }
            $filename = 'listing_' . uniqid() . '.' . $ext;
            if (move_uploaded_file($files['tmp_name'][$i], $uploadDir . $filename)) {
                $new_gallery[] = 'uploads/listings/' . $filename;
            } else {
                $errors[] = "Failed to save $fname.";
            }
        }
        if (!empty($new_gallery)) {
            $new_image_path = $new_gallery[0];
        }
    }

    if (empty($errors)) {
        $perksArr  = array_values(array_filter(
            array_map('trim', explode("\n", $perks_raw))
        ));
        $perksJson = json_encode($perksArr);

        if ($new_image_path !== null) {
            $newGalleryJson = json_encode($new_gallery);
            $stmt = mysqli_prepare($dbc,
                "UPDATE dbProj_accounts
                 SET cat_id = ?, title = ?, short_desc = ?, description = ?,
                     price = ?, original_val = ?, rank_label = ?, level_val = ?,
                     skins_count = ?, wins_count = ?, perks = ?, tags = ?,
                     image_path = ?, gallery = ?, is_hot = ?
                 WHERE account_id = ?");
            mysqli_stmt_bind_param($stmt, 'isssddsiiiisssii',
                $cat_id, $title, $short_desc, $description,
                $price, $original_val, $rank_label,
                $level_val, $skins_count, $wins_count,
                $perksJson, $tags, $new_image_path, $newGalleryJson, $is_hot, $id
            );
        } else {
            $stmt = mysqli_prepare($dbc,
                "UPDATE dbProj_accounts
                 SET cat_id = ?, title = ?, short_desc = ?, description = ?,
                     price = ?, original_val = ?, rank_label = ?, level_val = ?,
                     skins_count = ?, wins_count = ?, perks = ?, tags = ?, is_hot = ?
                 WHERE account_id = ?");
            mysqli_stmt_bind_param($stmt, 'isssddsiiiisii',
                $cat_id, $title, $short_desc, $description,
                $price, $original_val, $rank_label,
                $level_val, $skins_count, $wins_count,
                $perksJson, $tags, $is_hot, $id
            );
        }

        if (mysqli_stmt_execute($stmt)) {
            mysqli_stmt_close($stmt);
            header('Location: ' . BASE_URL . '/creator/index.php?updated=1');
            exit;
        } else {
            $errors[] = 'Database error: ' . mysqli_error($dbc);
        }
    }

    // On error, use POST values
    $account = array_merge($account, [
        'title'        => $title,
        'cat_id'       => $cat_id,
        'short_desc'   => $short_desc,
        'description'  => $description,
        'price'        => $price,
        'original_val' => $original_val,
        'rank_label'   => $rank_label,
        'level_val'    => $level_val,
        'skins_count'  => $skins_count,
        'wins_count'   => $wins_count,
        'tags'         => $tags,
        'is_hot'       => $is_hot,
    ]);
    $perksForForm = $perks_raw;
} else {
    // Pre-fill perks textarea from stored JSON
    $storedPerks  = json_decode($account['perks'] ?? '[]', true);
    $perksForForm = implode("\n", $storedPerks ?: []);
}

$pageTitle  = 'Edit Listing';
$activePage = 'creator';
include '../includes/header.php';
?>

<div class="panel-wrapper">
<div class="panel-container">

  <div class="panel-header">
    <div>
      <div class="panel-title">Edit Listing</div>
      <div class="panel-subtitle">ID #<?= $account['account_id'] ?> — <?= htmlspecialchars($account['title']) ?></div>
    </div>
    <a class="btn-outline" href="<?= BASE_URL ?>/creator/index.php">← Back</a>
  </div>

  <?php if ($errors): ?>
    <div class="error-msg" style="display:block;margin-bottom:1.5rem;">
      <?= implode('<br>', array_map('htmlspecialchars', $errors)) ?>
    </div>
  <?php endif; ?>

  <form method="post" enctype="multipart/form-data" style="max-width:800px;">

    <div style="display:grid;grid-template-columns:1fr 1fr;gap:1.5rem;">

      <!-- LEFT -->
      <div>
        <div class="form-group">
          <label class="form-label">Title *</label>
          <input class="form-input" name="title" type="text"
                 placeholder="e.g. OG Black Knight Account"
                 value="<?= htmlspecialchars($account['title']) ?>"
                 required>
        </div>

        <div class="form-group">
          <label class="form-label">Category *</label>
          <select class="form-select" name="cat_id" required>
            <option value="">-- Select Game --</option>
            <?php foreach ($catsData as $c): ?>
              <option value="<?= $c['cat_id'] ?>"
                      <?= $account['cat_id'] == $c['cat_id'] ? 'selected' : '' ?>>
                <?= $c['emoji'] . ' ' . htmlspecialchars($c['name']) ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>

        <div class="form-group">
          <label class="form-label">Price (USD) *</label>
          <input class="form-input" name="price" type="number"
                 step="0.01" min="1"
                 value="<?= htmlspecialchars($account['price']) ?>"
                 required>
        </div>

        <div class="form-group">
          <label class="form-label">Original / Estimated Value (USD) *</label>
          <input class="form-input" name="original_val" type="number"
                 step="0.01" min="1"
                 value="<?= htmlspecialchars($account['original_val']) ?>"
                 required>
        </div>

        <div class="form-group">
          <label class="form-label">Rank</label>
          <input class="form-input" name="rank_label" type="text"
                 placeholder="e.g. Champion, Radiant, Diamond"
                 value="<?= htmlspecialchars($account['rank_label']) ?>">
        </div>
      </div>

      <!-- RIGHT -->
      <div>
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:1rem;">
          <div class="form-group">
            <label class="form-label">Level</label>
            <input class="form-input" name="level_val" type="number"
                   min="1" placeholder="e.g. 200"
                   value="<?= htmlspecialchars($account['level_val'] ?? '') ?>">
          </div>
          <div class="form-group">
            <label class="form-label">Skins Count</label>
            <input class="form-input" name="skins_count" type="number"
                   min="0" placeholder="e.g. 50"
                   value="<?= htmlspecialchars($account['skins_count'] ?? '') ?>">
          </div>
        </div>

        <div class="form-group">
          <label class="form-label">Total Wins</label>
          <input class="form-input" name="wins_count" type="number"
                 min="0" placeholder="e.g. 500"
                 value="<?= htmlspecialchars($account['wins_count'] ?? '') ?>">
        </div>

        <div class="form-group">
          <label class="form-label">
            Tags
            <small style="color:var(--muted);text-transform:none;letter-spacing:0;">(comma separated)</small>
          </label>
          <input class="form-input" name="tags" type="text"
                 placeholder="OG Skins, Champion, 1000+ Wins"
                 value="<?= htmlspecialchars($account['tags'] ?? '') ?>">
        </div>

        <div class="form-group">
          <label class="form-label">
            Perks / What's Included
            <small style="color:var(--muted);text-transform:none;letter-spacing:0;">(one per line, start with emoji)</small>
          </label>
          <textarea class="form-textarea" name="perks_raw" rows="5"
                    placeholder="⚡ Black Knight Skin&#10;🏆 142 Skins&#10;🎯 1820 Wins"
                    ><?= htmlspecialchars($perksForForm) ?></textarea>
        </div>

        <div class="form-group">
          <label style="display:flex;align-items:center;gap:0.75rem;cursor:pointer;">
            <input type="checkbox" name="is_hot"
                   style="accent-color:var(--danger);width:16px;height:16px;"
                   <?= $account['is_hot'] ? 'checked' : '' ?>>
            <span class="form-label" style="margin:0;">🔥 Mark as HOT listing</span>
          </label>
        </div>
      </div>

    </div>

    <div class="form-group">
      <label class="form-label">
        Short Description *
        <small style="color:var(--muted);text-transform:none;letter-spacing:0;">(max 300 chars, shown on card)</small>
      </label>
      <textarea class="form-textarea" name="short_desc"
                rows="2" maxlength="300"
                required><?= htmlspecialchars($account['short_desc']) ?></textarea>
    </div>

    <div class="form-group">
      <label class="form-label">Full Description *</label>
      <textarea class="form-textarea" name="description"
                rows="6"
                required><?= htmlspecialchars($account['description']) ?></textarea>
    </div>

    <div class="form-group">
      <label class="form-label">
        Listing Image
        <small style="color:var(--muted);text-transform:none;
                      letter-spacing:0;">(jpg, png, webp — max 5 MB; leave blank to keep existing)</small>
      </label>
      <?php if (!empty($account['image_path'])): ?>
        <div style="margin-bottom:0.75rem;">
          <img src="<?= BASE_URL ?>/<?= htmlspecialchars($account['image_path']) ?>"
               alt="Current image"
               style="max-height:120px;border:1px solid var(--border);">
          <div style="font-size:0.75rem;color:var(--muted);margin-top:0.25rem;">
            Current image — upload a new one to replace it.
          </div>
        </div>
      <?php endif; ?>
      <input class="form-input" name="listing_images[]" type="file"
             accept="image/jpeg,image/png,image/gif,image/webp"
             multiple style="padding:0.5rem;">
      <small style="color:var(--muted);display:block;margin-top:0.4rem;">
        💡 Best results with landscape images — <strong>1920×1080 recommended</strong>
      </small>
    </div>

    <button type="submit" class="form-submit">
      💾 Save Changes
    </button>

  </form>

</div>
</div>

<?php include '../includes/footer.php'; ?>

<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

require_once 'includes/session.php';
require_once 'includes/config.php';

if (!isLoggedIn()) {
    header('Location: /~u202202670/vaultgg/login.php');
    exit;
}

$id  = (int)($_GET['id'] ?? 0);
$dbc = getConnection();

// ---- Get account ----
$stmt = mysqli_prepare($dbc,
    "SELECT a.*, c.name AS cat_name, c.slug AS cat_slug, c.emoji AS cat_emoji
     FROM dbProj_accounts a
     JOIN dbProj_categories c ON a.cat_id = c.cat_id
     WHERE a.account_id = ? AND a.status = 'published'");
mysqli_stmt_bind_param($stmt, 'i', $id);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$a      = mysqli_fetch_assoc($result);
mysqli_stmt_close($stmt);

if (!$a) {
    header('Location: /~u202202670/vaultgg/index.php');
    exit;
}

$step    = (int)($_GET['step'] ?? 1);
$success = false;
$errors  = [];

// ---- Handle payment POST ----
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['pay'])) {

    $card_name   = trim($_POST['card_name']   ?? '');
    $card_number = preg_replace('/\s+/', '', $_POST['card_number'] ?? '');
    $card_expiry = trim($_POST['card_expiry'] ?? '');
    $card_cvv    = trim($_POST['card_cvv']    ?? '');

    // Validation
    if (!$card_name)
        $errors[] = 'Cardholder name is required.';
    if (strlen($card_number) < 16)
        $errors[] = 'Invalid card number.';
    if (!$card_expiry)
        $errors[] = 'Expiry date is required.';
    if (strlen($card_cvv) < 3)
        $errors[] = 'Invalid CVV.';

    if (empty($errors)) {
        $success = true;
        $step    = 3;
    }
}

$savings = round((1 - $a['price'] / $a['original_val']) * 100);
$emojis  = ['fortnite'=>'⚡','valorant'=>'🎯','pubg'=>'🔫','fifa'=>'⚽'];
$emoji   = $emojis[$a['cat_slug']] ?? '🎮';

// ---- Fake game credentials ----
$fakeEmails = [
    'fortnite' => 'fn_' . strtolower(substr(md5($id . 'fn'), 0, 8)) . '@epicgames-vault.com',
    'valorant' => 'val_' . strtolower(substr(md5($id . 'val'), 0, 8)) . '@riot-vault.com',
    'pubg'     => 'pubg_' . strtolower(substr(md5($id . 'pubg'), 0, 8)) . '@pubg-vault.com',
    'fifa'     => 'fifa_' . strtolower(substr(md5($id . 'fifa'), 0, 8)) . '@ea-vault.com',
];
$fakePass = strtoupper(substr(md5($id . $_SESSION['user_id']), 0, 4))
          . strtolower(substr(md5($_SESSION['user_id'] . $id), 0, 4))
          . '!V' . rand(10, 99);
$gameEmail = $fakeEmails[$a['cat_slug']] ?? 'account_' . $id . '@vaultgg.com';

$pageTitle  = 'Checkout';
$activePage = '';
include 'includes/header.php';
?>

<style>
.checkout-wrapper {
  padding-top:64px; min-height:100vh;
  background:radial-gradient(ellipse at 30% 50%,rgba(124,58,237,0.08),transparent 60%),
             radial-gradient(ellipse at 70% 50%,rgba(0,240,255,0.05),transparent 60%);
}
.checkout-container {
  max-width:1000px; margin:0 auto; padding:3rem 2rem;
}
.checkout-title {
  font-family:'Orbitron',sans-serif; font-size:1.5rem;
  font-weight:700; margin-bottom:0.5rem;
}
.checkout-sub { color:var(--muted); margin-bottom:2.5rem; }

/* STEPS */
.steps {
  display:flex; align-items:center; margin-bottom:3rem; gap:0;
}
.step-item {
  display:flex; align-items:center; gap:0.75rem; flex:1;
}
.step-circle {
  width:36px; height:36px; border-radius:50%;
  display:flex; align-items:center; justify-content:center;
  font-family:'Orbitron',sans-serif; font-size:0.75rem; font-weight:700;
  flex-shrink:0;
}
.step-circle.done    { background:var(--success); color:#000; }
.step-circle.active  { background:linear-gradient(135deg,var(--accent2),var(--accent)); color:#fff; }
.step-circle.pending { background:var(--surface2); color:var(--muted); border:1px solid var(--border); }
.step-label { font-size:0.8rem; font-weight:700; letter-spacing:1px; text-transform:uppercase; }
.step-label.active  { color:var(--accent); }
.step-label.pending { color:var(--muted); }
.step-line  { flex:1; height:2px; background:var(--border); margin:0 0.5rem; }
.step-line.done { background:var(--success); }

/* GRID */
.checkout-grid {
  display:grid; grid-template-columns:1fr 360px; gap:2rem;
}

/* ORDER SUMMARY */
.order-summary {
  background:var(--surface); border:1px solid var(--border); padding:1.5rem;
  clip-path:polygon(0 0,calc(100% - 16px) 0,100% 16px,100% 100%,16px 100%,0 calc(100% - 16px));
  position:sticky; top:80px;
}
.order-title {
  font-family:'Orbitron',sans-serif; font-size:0.8rem; font-weight:700;
  letter-spacing:2px; text-transform:uppercase; color:var(--muted);
  margin-bottom:1.25rem; border-bottom:1px solid var(--border); padding-bottom:0.75rem;
}
.order-game {
  display:flex; align-items:center; gap:1rem; margin-bottom:1.5rem;
  padding:1rem; background:var(--surface2); border:1px solid var(--border);
}
.order-game-emoji { font-size:2.5rem; }
.order-game-title { font-family:'Orbitron',sans-serif; font-size:0.85rem; font-weight:700; }
.order-game-rank  { color:var(--muted); font-size:0.8rem; margin-top:0.25rem; }
.order-row {
  display:flex; justify-content:space-between; align-items:center;
  padding:0.5rem 0; border-bottom:1px solid var(--border); font-size:0.9rem;
}
.order-row:last-child { border-bottom:none; }
.order-row .label { color:var(--muted); }
.order-total {
  font-family:'Orbitron',sans-serif; font-size:1.5rem;
  font-weight:900; color:var(--accent3);
}
.order-savings {
  display:inline-block; background:rgba(16,185,129,0.15); color:var(--success);
  border:1px solid rgba(16,185,129,0.3); font-size:0.7rem; font-weight:700;
  letter-spacing:1px; padding:0.15rem 0.5rem; text-transform:uppercase;
}

/* PAYMENT CARD */
.pay-card {
  background:var(--surface); border:1px solid var(--border); padding:2rem;
  clip-path:polygon(0 0,calc(100% - 16px) 0,100% 16px,100% 100%,0 100%);
  margin-bottom:1.5rem; position:relative;
}
.pay-card::before {
  content:''; position:absolute; top:0; left:0; right:0; height:2px;
  background:linear-gradient(90deg,var(--accent2),var(--accent));
}
.pay-card-title {
  font-family:'Orbitron',sans-serif; font-size:0.85rem; font-weight:700;
  letter-spacing:2px; text-transform:uppercase; margin-bottom:1.5rem;
  color:var(--accent);
}

/* CARD VISUAL */
.card-visual {
  background:linear-gradient(135deg,#1a1a2e,#16213e,#0f3460);
  border-radius:12px; padding:1.5rem; margin-bottom:1.5rem;
  position:relative; overflow:hidden; min-height:160px;
  border:1px solid rgba(99,179,237,0.2);
  box-shadow:0 20px 60px rgba(0,0,0,0.5);
}
.card-visual::before {
  content:''; position:absolute; top:-50%; right:-20%;
  width:200px; height:200px; border-radius:50%;
  background:rgba(0,240,255,0.05); pointer-events:none;
}
.card-chip {
  width:45px; height:35px; background:linear-gradient(135deg,#d4af37,#f5d060);
  border-radius:6px; margin-bottom:1rem;
  display:grid; grid-template-columns:1fr 1fr; gap:2px; padding:4px;
}
.card-chip-cell {
  background:rgba(0,0,0,0.3); border-radius:2px;
}
.card-number-display {
  font-family:'Orbitron',sans-serif; font-size:1.1rem; font-weight:700;
  letter-spacing:3px; color:#fff; margin-bottom:1rem;
  text-shadow:0 2px 4px rgba(0,0,0,0.5);
}
.card-bottom {
  display:flex; justify-content:space-between; align-items:flex-end;
}
.card-holder-label { font-size:0.6rem; color:rgba(255,255,255,0.5); letter-spacing:2px; text-transform:uppercase; }
.card-holder-name  { font-size:0.85rem; color:#fff; font-weight:600; letter-spacing:1px; margin-top:0.2rem; text-transform:uppercase; }
.card-expiry-label { font-size:0.6rem; color:rgba(255,255,255,0.5); letter-spacing:2px; text-transform:uppercase; text-align:right; }
.card-expiry-value { font-size:0.85rem; color:#fff; font-weight:600; text-align:right; margin-top:0.2rem; }
.card-logo {
  position:absolute; top:1.5rem; right:1.5rem;
  font-family:'Orbitron',sans-serif; font-size:1rem; font-weight:900;
  color:rgba(255,255,255,0.8); letter-spacing:1px;
}

/* PAYMENT METHODS */
.pay-methods {
  display:flex; gap:0.75rem; margin-bottom:1.5rem; flex-wrap:wrap;
}
.pay-method {
  flex:1; min-width:80px; padding:0.75rem 0.5rem; text-align:center;
  background:var(--surface2); border:2px solid var(--border); cursor:pointer;
  transition:all 0.2s; font-size:0.7rem; font-weight:700; letter-spacing:1px;
  text-transform:uppercase; color:var(--muted);
}
.pay-method:hover   { border-color:var(--accent); color:var(--accent); }
.pay-method.active  { border-color:var(--accent); color:var(--accent); background:rgba(0,240,255,0.05); }
.pay-method-icon    { font-size:1.5rem; display:block; margin-bottom:0.3rem; }

/* SECURITY BADGES */
.security-badges {
  display:flex; gap:1rem; flex-wrap:wrap; margin-top:1rem;
  padding-top:1rem; border-top:1px solid var(--border);
}
.security-badge {
  display:flex; align-items:center; gap:0.4rem;
  font-size:0.75rem; color:var(--muted);
}
.security-badge span { color:var(--success); }

/* SUCCESS PAGE */
.success-wrapper {
  text-align:center; padding:3rem 2rem; max-width:700px; margin:0 auto;
}
.success-icon {
  width:100px; height:100px; border-radius:50%;
  background:rgba(16,185,129,0.15); border:2px solid var(--success);
  display:flex; align-items:center; justify-content:center;
  font-size:3rem; margin:0 auto 2rem;
  animation:successPop 0.5s ease;
}
.success-title {
  font-family:'Orbitron',sans-serif; font-size:1.8rem; font-weight:900;
  color:var(--success); margin-bottom:0.75rem;
}
.credentials-box {
  background:var(--surface); border:2px solid var(--accent);
  padding:2rem; margin:2rem 0; text-align:left;
  clip-path:polygon(0 0,calc(100% - 16px) 0,100% 16px,100% 100%,16px 100%,0 calc(100% - 16px));
  position:relative;
}
.credentials-box::before {
  content:'🔐 YOUR ACCOUNT CREDENTIALS';
  position:absolute; top:-12px; left:20px;
  background:var(--bg); padding:0 10px;
  font-family:'Orbitron',sans-serif; font-size:0.65rem;
  font-weight:700; letter-spacing:2px; color:var(--accent);
}
.cred-row {
  display:flex; justify-content:space-between; align-items:center;
  padding:0.85rem 0; border-bottom:1px solid var(--border);
}
.cred-row:last-child { border-bottom:none; }
.cred-label { color:var(--muted); font-size:0.8rem; letter-spacing:1px; text-transform:uppercase; }
.cred-value {
  font-family:'Orbitron',sans-serif; font-size:0.85rem; font-weight:700;
  color:var(--text); background:var(--surface2); padding:0.4rem 0.75rem;
  border:1px solid var(--border); letter-spacing:1px;
}
.copy-btn {
  background:none; border:none; color:var(--accent); cursor:pointer;
  font-size:0.8rem; margin-left:0.5rem; transition:opacity 0.2s;
}
.copy-btn:hover { opacity:0.7; }
.warning-box {
  background:rgba(245,158,11,0.1); border:1px solid rgba(245,158,11,0.3);
  padding:1rem 1.25rem; margin-top:1.5rem; text-align:left;
  font-size:0.85rem; color:var(--accent3); line-height:1.6;
}
.warning-box strong { display:block; margin-bottom:0.4rem;
  font-family:'Orbitron',sans-serif; font-size:0.75rem; letter-spacing:1px; }
@keyframes successPop {
  0%   { transform:scale(0); opacity:0; }
  70%  { transform:scale(1.1); }
  100% { transform:scale(1); opacity:1; }
}

@media(max-width:768px) {
  .checkout-grid { grid-template-columns:1fr; }
  .order-summary { position:static; }
}
</style>

<div class="checkout-wrapper">
<div class="checkout-container">

<?php if ($step === 3 && $success): ?>
  <!-- ===== SUCCESS PAGE ===== -->
  <div class="success-wrapper">
    <div class="success-icon">✅</div>
    <div class="success-title">Payment Successful!</div>
    <p style="color:var(--muted);font-size:1rem;line-height:1.7;margin-bottom:0.5rem;">
      Your purchase of <strong style="color:var(--text)">
      <?= htmlspecialchars($a['title']) ?></strong> is confirmed.
    </p>
    <p style="color:var(--muted);font-size:0.9rem;">
      Order #VGG-<?= strtoupper(substr(md5($id . time()), 0, 8)) ?> •
      $<?= number_format($a['price'], 2) ?> charged
    </p>

    <!-- CREDENTIALS BOX -->
    <div class="credentials-box">
      <div class="cred-row">
        <span class="cred-label">Game</span>
        <span class="cred-value">
          <?= $emoji ?> <?= strtoupper(htmlspecialchars($a['cat_slug'])) ?>
        </span>
      </div>
      <div class="cred-row">
        <span class="cred-label">Account Email</span>
        <div style="display:flex;align-items:center;">
          <span class="cred-value" id="cred-email">
            <?= htmlspecialchars($gameEmail) ?>
          </span>
          <button class="copy-btn" onclick="copyText('cred-email')">📋</button>
        </div>
      </div>
      <div class="cred-row">
        <span class="cred-label">Password</span>
        <div style="display:flex;align-items:center;">
          <span class="cred-value" id="cred-pass">
            <?= htmlspecialchars($fakePass) ?>
          </span>
          <button class="copy-btn" onclick="copyText('cred-pass')">📋</button>
        </div>
      </div>
      <div class="cred-row">
        <span class="cred-label">Rank</span>
        <span class="cred-value"><?= htmlspecialchars($a['rank_label']) ?></span>
      </div>
      <div class="cred-row">
        <span class="cred-label">Support</span>
        <span class="cred-value">support@vaultgg.com</span>
      </div>
    </div>

    <div class="warning-box">
      <strong>⚠️ IMPORTANT – Read Before Logging In</strong>
      1. Change the password immediately after first login.<br>
      2. Enable 2FA on the account for security.<br>
      3. Do NOT share these credentials with anyone.<br>
      4. Contact support within 30 days if any issues arise.
    </div>

    <div style="display:flex;gap:1rem;justify-content:center;margin-top:2rem;flex-wrap:wrap;">
      <a class="btn-primary" href="/~u202202670/vaultgg/index.php">
        Browse More Accounts
      </a>
      <a class="btn-outline" href="/~u202202670/vaultgg/detail.php?id=<?= $id ?>">
        Back to Listing
      </a>
    </div>
  </div>

<?php else: ?>
  <!-- ===== CHECKOUT STEPS ===== -->
  <div class="checkout-title">Secure Checkout</div>
  <div class="checkout-sub">Complete your purchase safely</div>

  <!-- STEPS -->
  <div class="steps">
    <div class="step-item">
      <div class="step-circle done">✓</div>
      <div class="step-label" style="color:var(--success);">Review</div>
    </div>
    <div class="step-line done"></div>
    <div class="step-item">
      <div class="step-circle active">2</div>
      <div class="step-label active">Payment</div>
    </div>
    <div class="step-line"></div>
    <div class="step-item">
      <div class="step-circle pending">3</div>
      <div class="step-label pending">Delivery</div>
    </div>
  </div>

  <?php if ($errors): ?>
    <div class="error-msg" style="display:block;margin-bottom:1.5rem;">
      <?= implode('<br>', array_map('htmlspecialchars', $errors)) ?>
    </div>
  <?php endif; ?>

  <div class="checkout-grid">

    <!-- LEFT: PAYMENT FORM -->
    <div>

      <!-- PAYMENT METHOD SELECT -->
      <div class="pay-card">
        <div class="pay-card-title">Select Payment Method</div>
        <div class="pay-methods">
          <div class="pay-method active" onclick="selectMethod(this,'card')">
            <span class="pay-method-icon">💳</span>
            Credit Card
          </div>
          <div class="pay-method" onclick="selectMethod(this,'paypal')">
            <span class="pay-method-icon">🅿️</span>
            PayPal
          </div>
          <div class="pay-method" onclick="selectMethod(this,'crypto')">
            <span class="pay-method-icon">₿</span>
            Crypto
          </div>
          <div class="pay-method" onclick="selectMethod(this,'apple')">
            <span class="pay-method-icon">🍎</span>
            Apple Pay
          </div>
        </div>
      </div>

      <!-- CARD PAYMENT FORM -->
      <div class="pay-card" id="card-form">
        <div class="pay-card-title">💳 Card Details</div>

        <!-- CARD VISUAL -->
        <div class="card-visual">
          <div class="card-logo">VAULT<span style="color:var(--accent)">GG</span></div>
          <div class="card-chip">
            <div class="card-chip-cell"></div>
            <div class="card-chip-cell"></div>
            <div class="card-chip-cell"></div>
            <div class="card-chip-cell"></div>
          </div>
          <div class="card-number-display" id="card-display">
            •••• •••• •••• ••••
          </div>
          <div class="card-bottom">
            <div>
              <div class="card-holder-label">Card Holder</div>
              <div class="card-holder-name" id="name-display">YOUR NAME</div>
            </div>
            <div>
              <div class="card-expiry-label">Expires</div>
              <div class="card-expiry-value" id="expiry-display">MM/YY</div>
            </div>
          </div>
        </div>

        <!-- FORM FIELDS -->
        <form method="post"
              action="/~u202202670/vaultgg/payment.php?id=<?= $id ?>">
          <input type="hidden" name="pay" value="1">

          <div class="form-group">
            <label class="form-label">Cardholder Name</label>
            <input class="form-input" name="card_name" id="card_name"
                   type="text" placeholder="John Doe"
                   oninput="document.getElementById('name-display').textContent = this.value.toUpperCase() || 'YOUR NAME'"
                   required>
          </div>

          <div class="form-group">
            <label class="form-label">Card Number</label>
            <input class="form-input" name="card_number" id="card_number"
                   type="text" placeholder="1234 5678 9012 3456"
                   maxlength="19"
                   oninput="formatCard(this)"
                   required>
          </div>

          <div style="display:grid;grid-template-columns:1fr 1fr;gap:1rem;">
            <div class="form-group">
              <label class="form-label">Expiry Date</label>
              <input class="form-input" name="card_expiry" id="card_expiry"
                     type="text" placeholder="MM/YY" maxlength="5"
                     oninput="formatExpiry(this)"
                     required>
            </div>
            <div class="form-group">
              <label class="form-label">CVV</label>
              <input class="form-input" name="card_cvv"
                     type="password" placeholder="•••" maxlength="4"
                     required>
            </div>
          </div>

          <!-- SECURITY BADGES -->
          <div class="security-badges">
            <div class="security-badge"><span>🔒</span> SSL Encrypted</div>
            <div class="security-badge"><span>✓</span> PCI Compliant</div>
            <div class="security-badge"><span>🛡️</span> Fraud Protected</div>
            <div class="security-badge"><span>✓</span> 3D Secure</div>
          </div>

          <!-- SUBMIT -->
          <button type="submit" class="form-submit" style="margin-top:1.5rem;"
                  id="pay-btn" onclick="return startPayment()">
            🔒 Pay $<?= number_format($a['price'], 2) ?> Securely
          </button>
        </form>
      </div>

      <!-- PAYPAL (hidden by default) -->
      <div class="pay-card" id="paypal-form" style="display:none;">
        <div class="pay-card-title">🅿️ PayPal</div>
        <p style="color:var(--muted);margin-bottom:1.5rem;">
          You will be redirected to PayPal to complete your payment securely.
        </p>
        <button class="form-submit"
                onclick="showToast('Redirecting to PayPal... (demo mode)')">
          Pay with PayPal – $<?= number_format($a['price'], 2) ?>
        </button>
      </div>

      <!-- CRYPTO (hidden by default) -->
      <div class="pay-card" id="crypto-form" style="display:none;">
        <div class="pay-card-title">₿ Crypto Payment</div>
        <p style="color:var(--muted);margin-bottom:1rem;">
          Send exactly <strong style="color:var(--accent3)">
          <?= number_format($a['price'] / 65000, 6) ?> BTC</strong>
          to the address below:
        </p>
        <div style="background:var(--surface2);border:1px solid var(--border);
                    padding:1rem;font-family:'Orbitron',sans-serif;font-size:0.75rem;
                    word-break:break-all;color:var(--accent);letter-spacing:1px;
                    margin-bottom:1rem;">
          bc1qxy2kgdygjrsqtzq2n0yrf2493p83kkfjhx0wlh
        </div>
        <p style="color:var(--muted);font-size:0.8rem;">
          Payment will be confirmed after 3 blockchain confirmations (~30 mins)
        </p>
        <button class="form-submit" style="margin-top:1rem;"
                onclick="showToast('Waiting for blockchain confirmation... (demo)')">
          I Have Sent the Payment
        </button>
      </div>

      <!-- APPLE PAY (hidden by default) -->
      <div class="pay-card" id="apple-form" style="display:none;">
        <div class="pay-card-title">🍎 Apple Pay</div>
        <p style="color:var(--muted);margin-bottom:1.5rem;">
          Use Face ID or Touch ID to complete your payment.
        </p>
        <button class="form-submit"
                style="background:linear-gradient(135deg,#1c1c1e,#2c2c2e);"
                onclick="showToast('Apple Pay initiated... (demo mode)')">
          🍎 Pay $<?= number_format($a['price'], 2) ?> with Apple Pay
        </button>
      </div>

    </div>

    <!-- RIGHT: ORDER SUMMARY -->
    <div>
      <div class="order-summary">
        <div class="order-title">Order Summary</div>

        <div class="order-game">
          <div class="order-game-emoji"><?= $emoji ?></div>
          <div>
            <div class="order-game-title">
              <?= htmlspecialchars($a['title']) ?>
            </div>
            <div class="order-game-rank">
              <?= htmlspecialchars($a['rank_label']) ?>
              • <?= strtoupper(htmlspecialchars($a['cat_slug'])) ?>
            </div>
          </div>
        </div>

        <div class="order-row">
          <span class="label">Original Value</span>
          <span style="text-decoration:line-through;color:var(--muted);">
            $<?= number_format($a['original_val'], 2) ?>
          </span>
        </div>
        <div class="order-row">
          <span class="label">Discount</span>
          <span style="color:var(--success);">-<?= $savings ?>%</span>
        </div>
        <div class="order-row">
          <span class="label">Processing Fee</span>
          <span style="color:var(--muted);">$0.00</span>
        </div>
        <div class="order-row" style="border-top:2px solid var(--border);
                                       padding-top:1rem;margin-top:0.5rem;">
          <span style="font-weight:700;">Total</span>
          <span class="order-total">$<?= number_format($a['price'], 2) ?></span>
        </div>
        <div style="margin-top:0.75rem;">
          <span class="order-savings">You save <?= $savings ?>%</span>
        </div>

        <div style="margin-top:1.5rem;padding-top:1rem;
                    border-top:1px solid var(--border);">
          <div style="font-size:0.8rem;color:var(--muted);line-height:1.8;">
            ✅ Instant credential delivery<br>
            ✅ 30-day replacement guarantee<br>
            ✅ 24/7 customer support<br>
            ✅ Verified account
          </div>
        </div>
      </div>
    </div>

  </div>
<?php endif; ?>

</div>
</div>

<!-- LOADING OVERLAY -->
<div id="loading-overlay"
     style="display:none;position:fixed;inset:0;background:rgba(0,0,0,0.85);
            z-index:999;display:none;align-items:center;justify-content:center;
            flex-direction:column;gap:1.5rem;">
  <div style="width:60px;height:60px;border:3px solid var(--border);
              border-top-color:var(--accent);border-radius:50%;
              animation:spin 0.8s linear infinite;"></div>
  <div style="font-family:'Orbitron',sans-serif;font-size:0.8rem;
              letter-spacing:2px;color:var(--accent);"
       id="loading-text">PROCESSING PAYMENT...</div>
</div>

<style>
@keyframes spin { to { transform:rotate(360deg); } }
</style>

<script>
// ---- Payment method switcher ----
function selectMethod(el, method) {
  document.querySelectorAll('.pay-method').forEach(m => m.classList.remove('active'));
  el.classList.add('active');
  ['card','paypal','crypto','apple'].forEach(m => {
    const el = document.getElementById(m + '-form');
    if (el) el.style.display = m === method ? 'block' : 'none';
  });
}

// ---- Format card number ----
function formatCard(input) {
  let v = input.value.replace(/\D/g, '').substring(0, 16);
  let formatted = v.match(/.{1,4}/g)?.join(' ') || v;
  input.value = formatted;
  const display = document.getElementById('card-display');
  let parts = v.padEnd(16, '•').match(/.{1,4}/g) || [];
  display.textContent = parts.join(' ');
}

// ---- Format expiry ----
function formatExpiry(input) {
  let v = input.value.replace(/\D/g,'');
  if (v.length >= 2) v = v.substring(0,2) + '/' + v.substring(2,4);
  input.value = v;
  document.getElementById('expiry-display').textContent = v || 'MM/YY';
}

// ---- Payment processing animation ----
function startPayment() {
  const overlay  = document.getElementById('loading-overlay');
  const loadText = document.getElementById('loading-text');
  overlay.style.display = 'flex';

  const steps = [
    'VALIDATING CARD...',
    'CONNECTING TO BANK...',
    'PROCESSING PAYMENT...',
    'CONFIRMING TRANSACTION...',
    'PREPARING CREDENTIALS...',
  ];
  let i = 0;
  const interval = setInterval(() => {
    if (i < steps.length) {
      loadText.textContent = steps[i++];
    } else {
      clearInterval(interval);
    }
  }, 600);
  return true;
}

// ---- Copy credentials ----
function copyText(elementId) {
  const text = document.getElementById(elementId).textContent.trim();
  navigator.clipboard.writeText(text).then(() => {
    showToast('📋 Copied to clipboard!');
  });
}

// ---- Toast ----
function showToast(msg) {
  const t = document.getElementById('toast');
  if (!t) return;
  t.textContent = msg;
  t.classList.add('show');
  setTimeout(() => t.classList.remove('show'), 3000);
}
</script>

<?php include 'includes/footer.php'; ?>
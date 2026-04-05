<?php
require_once 'includes/session.php';
require_once 'includes/config.php';

// If already logged in, go home
if (isLoggedIn()) {
    header('Location: /index.php');
    exit;
}

$error = '';
$tab   = $_GET['tab'] ?? 'login';

// ---- Handle POST ----
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // LOGIN
    if (isset($_POST['action']) && $_POST['action'] === 'login') {
        $email    = trim($_POST['email']    ?? '');
        $password = trim($_POST['password'] ?? '');

        if (!$email || !$password) {
            $error = 'Please enter your email and password.';
            $tab   = 'login';
        } else {
            $dbc  = getConnection();
            $sql  = "SELECT * FROM dbProj_users 
                     WHERE email = ? AND is_active = 1 LIMIT 1";
            $stmt = mysqli_prepare($dbc, $sql);
            mysqli_stmt_bind_param($stmt, 's', $email);
            mysqli_stmt_execute($stmt);
            $result = mysqli_stmt_get_result($stmt);
            $user   = mysqli_fetch_assoc($result);

            if ($user && password_verify($password, $user['password'])) {
                $_SESSION['user_id']  = $user['user_id'];
                $_SESSION['username'] = $user['username'];
                $_SESSION['email']    = $user['email'];
                $_SESSION['role']     = $user['role'];
                $_SESSION['name']     = $user['first_name'];

                // Update last login
                $upd = mysqli_prepare($dbc, 
                    "UPDATE dbProj_users SET last_login = NOW() WHERE user_id = ?");
                mysqli_stmt_bind_param($upd, 'i', $user['user_id']);
                mysqli_stmt_execute($upd);

                header('Location: /~u202202670/vaultgg/index.php');
                exit;
            } else {
                $error = 'Invalid email or password.';
                $tab   = 'login';
            }
        }
    }

    // REGISTER
    if (isset($_POST['action']) && $_POST['action'] === 'register') {
        $first    = trim($_POST['first_name'] ?? '');
        $last     = trim($_POST['last_name']  ?? '');
        $email    = trim($_POST['email']      ?? '');
        $username = trim($_POST['username']   ?? '');
        $password = trim($_POST['password']   ?? '');
        $confirm  = trim($_POST['confirm']    ?? '');
        $tab      = 'register';

        // Validation
        if (!$first || !$last || !$email || !$username || !$password || !$confirm) {
            $error = 'Please fill in all fields.';
        } elseif (strlen($password) < 8) {
            $error = 'Password must be at least 8 characters.';
        } elseif ($password !== $confirm) {
            $error = 'Passwords do not match.';
        } else {
            $dbc = getConnection();

            // Check if email or username exists
            $chk  = mysqli_prepare($dbc, 
                "SELECT user_id FROM dbProj_users 
                 WHERE email = ? OR username = ? LIMIT 1");
            mysqli_stmt_bind_param($chk, 'ss', $email, $username);
            mysqli_stmt_execute($chk);
            mysqli_stmt_store_result($chk);

            if (mysqli_stmt_num_rows($chk) > 0) {
                $error = 'Email or username already in use.';
            } else {
                $hash = password_hash($password, PASSWORD_BCRYPT);
                $ins  = mysqli_prepare($dbc,
                    "INSERT INTO dbProj_users 
                     (username, email, password, first_name, last_name, role)
                     VALUES (?, ?, ?, ?, ?, 'visitor')");
                mysqli_stmt_bind_param($ins, 'sssss', 
                    $username, $email, $hash, $first, $last);
                mysqli_stmt_execute($ins);

                // Auto login
                $_SESSION['user_id']  = mysqli_insert_id($dbc);
                $_SESSION['username'] = $username;
                $_SESSION['email']    = $email;
                $_SESSION['role']     = 'visitor';
                $_SESSION['name']     = $first;

                header('Location: /vaultgg/index.php');
                exit;
            }
        }
    }
}

$pageTitle  = 'Login';
$activePage = 'auth';
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Login – VaultGG</title>
  <link rel="stylesheet" href="/vaultgg/assets/css/style.css">
  <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
</head>
<body>

<?php include 'includes/header.php'; ?>

<div class="auth-wrapper">
  <div class="auth-container">

    <!-- LOGO -->
    <div style="text-align:center;margin-bottom:2rem;">
      <a class="logo" href="/vaultgg/index.php">VAULT<span class="logo-gg">GG</span></a>
      <div style="color:var(--muted);font-size:0.8rem;letter-spacing:2px;text-transform:uppercase;margin-top:0.4rem;">
        Secure Game Account Marketplace
      </div>
    </div>

    <div class="auth-box">

      <!-- TABS -->
      <div class="auth-tabs">
        <button class="auth-tab <?= $tab==='login'?'active':'' ?>" 
                onclick="switchTab('login')">Login</button>
        <button class="auth-tab <?= $tab==='register'?'active':'' ?>" 
                onclick="switchTab('register')">Register</button>
      </div>

      <!-- ERROR -->
      <?php if ($error): ?>
        <div class="error-msg"><?= htmlspecialchars($error) ?></div>
      <?php endif; ?>

      <!-- LOGIN FORM -->
      <div id="login-form" style="display:<?= $tab==='login'?'block':'none' ?>">
        <div class="error-msg" id="login-error-js" style="display:none;"></div>
        <form method="post">
          <input type="hidden" name="action" value="login">
          <div class="form-group">
            <label class="form-label">Email Address</label>
            <input class="form-input" name="email" type="email" 
                   placeholder="your@email.com" required>
          </div>
          <div class="form-group">
            <label class="form-label">Password</label>
            <input class="form-input" name="password" type="password" 
                   placeholder="••••••••" required>
          </div>
          <button type="submit" class="form-submit" 
                  onclick="return validateLogin()">Login to VaultGG</button>
        </form>
        <div style="text-align:center;margin-top:1rem;color:var(--muted);font-size:0.85rem;">
          Demo: <strong>admin@vaultgg.com</strong> / <strong>password</strong>
        </div>
      </div>

      <!-- REGISTER FORM -->
      <div id="register-form" style="display:<?= $tab==='register'?'block':'none' ?>">
        <div class="error-msg" id="register-error-js" style="display:none;"></div>
        <form method="post">
          <input type="hidden" name="action" value="register">
          <div style="display:grid;grid-template-columns:1fr 1fr;gap:1rem;">
            <div class="form-group">
              <label class="form-label">First Name</label>
              <input class="form-input" name="first_name" type="text" placeholder="John">
            </div>
            <div class="form-group">
              <label class="form-label">Last Name</label>
              <input class="form-input" name="last_name" type="text" placeholder="Doe">
            </div>
          </div>
          <div class="form-group">
            <label class="form-label">Email Address</label>
            <input class="form-input" name="email" type="email" placeholder="your@email.com">
          </div>
          <div class="form-group">
            <label class="form-label">Username</label>
            <input class="form-input" name="username" type="text" placeholder="GamerTag123">
          </div>
          <div class="form-group">
            <label class="form-label">Password</label>
            <input class="form-input" name="password" id="reg-password" 
                   type="password" placeholder="Min. 8 characters">
          </div>
          <div class="form-group">
            <label class="form-label">Confirm Password</label>
            <input class="form-input" name="confirm" id="reg-confirm" 
                   type="password" placeholder="Repeat password">
          </div>
          <button type="submit" class="form-submit" 
                  onclick="return validateRegister()">Create Account</button>
          <div style="font-size:0.78rem;color:var(--muted);text-align:center;margin-top:1rem;">
            By registering you agree to our Terms of Service.
          </div>
        </form>
      </div>

    </div>
  </div>
</div>

<script>
function switchTab(tab) {
  document.getElementById('login-form').style.display    = tab==='login'    ? 'block' : 'none';
  document.getElementById('register-form').style.display = tab==='register' ? 'block' : 'none';
  document.querySelectorAll('.auth-tab').forEach((t,i) => {
    t.classList.toggle('active', (i===0 && tab==='login') || (i===1 && tab==='register'));
  });
}

function validateLogin() {
  const email = document.querySelector('#login-form input[name="email"]').value.trim();
  const pass  = document.querySelector('#login-form input[name="password"]').value.trim();
  const err   = document.getElementById('login-error-js');
  if (!email || !pass) {
    err.textContent = 'Please enter your email and password.';
    err.style.display = 'block';
    return false;
  }
  err.style.display = 'none';
  return true;
}

function validateRegister() {
  const pass    = document.getElementById('reg-password').value;
  const confirm = document.getElementById('reg-confirm').value;
  const err     = document.getElementById('register-error-js');
  if (pass.length < 8) {
    err.textContent = 'Password must be at least 8 characters.';
    err.style.display = 'block';
    return false;
  }
  if (pass !== confirm) {
    err.textContent = 'Passwords do not match.';
    err.style.display = 'block';
    return false;
  }
  err.style.display = 'none';
  return true;
}
</script>

<?php include 'includes/footer.php'; ?>
</parameter>
<parameter name="path">/home/claude/vaultgg/login.php</parameter>

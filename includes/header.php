<?php
require_once 'session.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= $pageTitle ?? 'VaultGG' ?> – VaultGG</title>
  <link rel="stylesheet" href="/~u202202670/vaultgg/css/style.css">
  <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
</head>
<body>

<nav>
  <a class="logo" href="/~u202202670/vaultgg/index.php">
    VAULT<span class="logo-gg">GG</span>
  </a>
  <div class="nav-links">
    <a class="nav-link <?= ($activePage??'')==='home'?'active':'' ?>" 
       href="/~u202202670/vaultgg/index.php">Browse</a>
    <a class="nav-link <?= ($activePage??'')==='search'?'active':'' ?>" 
       href="/~u202202670/vaultgg/search.php">Search</a>

    <?php if (isCreator()): ?>
      <a class="nav-link <?= ($activePage??'')==='creator'?'active':'' ?>" 
         href="/~u202202670/vaultgg/creator/index.php">My Listings</a>
    <?php endif; ?>

    <?php if (isAdmin()): ?>
      <a class="nav-link <?= ($activePage??'')==='admin'?'active':'' ?>" 
         href="/~u202202670/vaultgg/admin/index.php">Admin</a>
    <?php endif; ?>

    <?php if (isLoggedIn()): ?>
  <span style="color:var(--text);font-size:0.85rem;font-weight:600;">
    👤 <?= htmlspecialchars($_SESSION['username']) ?>
  </span>
  <a class="nav-link" href="/~u202202670/vaultgg/profile.php">My Profile</a>
  <a class="nav-btn" href="/~u202202670/vaultgg/logout.php">Logout</a>
<?php else: ?>
  <a class="nav-link" href="/~u202202670/vaultgg/login.php">Login</a>
  <a class="nav-btn" href="/~u202202670/vaultgg/login.php">Sell Account</a>
<?php endif; ?>
  </div>
</nav>


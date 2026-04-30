<?php
require_once 'session.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= $pageTitle ?? 'VaultGG' ?> – VaultGG</title>
  <link rel="stylesheet" href="<?= BASE_URL ?>/css/style.css">
  <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
</head>
<body>

<nav>
  <a class="logo" href="<?= BASE_URL ?>/index.php">
    VAULT<span class="logo-gg">GG</span>
  </a>
  <div class="nav-links">
    <a class="nav-link <?= ($activePage??'')==='home'?'active':'' ?>" 
       href="<?= BASE_URL ?>/index.php">Browse</a>
    <a class="nav-link <?= ($activePage??'')==='search'?'active':'' ?>" 
       href="<?= BASE_URL ?>/search.php">Search</a>

    <?php if (isCreator()): ?>
      <a class="nav-link <?= ($activePage??'')==='creator'?'active':'' ?>" 
         href="<?= BASE_URL ?>/creator/index.php">My Listings</a>
    <?php endif; ?>

    <?php if (isAdmin()): ?>
      <a class="nav-link <?= ($activePage??'')==='admin'?'active':'' ?>" 
         href="<?= BASE_URL ?>/admin/index.php">Admin</a>
    <?php endif; ?>

    <?php if (isLoggedIn()): ?>
      <span style="color:var(--text);font-size:0.85rem;font-weight:600;">
        👤 <?= htmlspecialchars($_SESSION['username']) ?>
      </span>
      <a class="nav-link" href="<?= BASE_URL ?>/profile.php">My Profile</a>
      <a class="nav-btn" href="<?= BASE_URL ?>/logout.php">Logout</a>
    <?php else: ?>
      <a class="nav-link" href="<?= BASE_URL ?>/login.php">Login</a>
      <a class="nav-btn" href="<?= BASE_URL ?>/login.php">Sell Account</a>
    <?php endif; ?>
  </div>
</nav>
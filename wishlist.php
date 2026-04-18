<?php
require_once 'includes/session.php';
require_once 'includes/config.php';

if (!isLoggedIn()) {
    header('Location: /~u202202670/vaultgg/login.php');
    exit;
}

$accountId = (int)($_POST['account_id'] ?? 0);
$dbc       = getConnection();

if ($accountId) {
    $stmt = mysqli_prepare($dbc,
        "INSERT IGNORE INTO dbProj_wishlist (user_id, account_id)
         VALUES (?, ?)");
    mysqli_stmt_bind_param($stmt, 'ii', $_SESSION['user_id'], $accountId);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);
}

header('Location: /~u202202670/vaultgg/profile.php');
exit;

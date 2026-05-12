<?php
require_once '../includes/session.php';
require_once '../includes/config.php';

if (!isLoggedIn() || (!isCreator() && !isAdmin())) {
    header('Location: ' . BASE_URL . '/login.php');
    exit;
}

$dbc    = getConnection();
$userId = $_SESSION['user_id'];
$id     = (int)($_GET['id'] ?? 0);

// Verify ownership (admins can publish any)
if (isAdmin()) {
    $stmt = mysqli_prepare($dbc,
        "SELECT account_id FROM dbProj_accounts
         WHERE account_id = ? AND status = 'draft'");
    mysqli_stmt_bind_param($stmt, 'i', $id);
} else {
    $stmt = mysqli_prepare($dbc,
        "SELECT account_id FROM dbProj_accounts
         WHERE account_id = ? AND creator_id = ? AND status = 'draft'");
    mysqli_stmt_bind_param($stmt, 'ii', $id, $userId);
}
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$found  = mysqli_fetch_assoc($result);
mysqli_stmt_close($stmt);

if ($found) {
    $upd = mysqli_prepare($dbc,
        "UPDATE dbProj_accounts
         SET status = 'published', published_at = NOW()
         WHERE account_id = ?");
    mysqli_stmt_bind_param($upd, 'i', $id);
    mysqli_stmt_execute($upd);
    mysqli_stmt_close($upd);
}

header('Location: ' . BASE_URL . '/creator/index.php?published=1');
exit;

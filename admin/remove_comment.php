<?php
require_once '../includes/session.php';
require_once '../includes/config.php';

if (!isAdmin()) {
    header('Location: ' . BASE_URL . '/index.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['comment_id'], $_POST['account_id'])) {
    $commentId = (int)$_POST['comment_id'];
    $accountId = (int)$_POST['account_id'];
    
    $dbc = getConnection();
    mysqli_query($dbc, "UPDATE dbProj_comments SET is_removed = 1 WHERE comment_id = $commentId");
    
    header('Location: ' . BASE_URL . '/detail.php?id=' . $accountId);
    exit;
} else {
    header('Location: ' . BASE_URL . '/index.php');
    exit;
}
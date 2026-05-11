<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';

header('Content-Type: application/json');

if (!isLoggedIn()) {
    echo json_encode(['status' => 'error', 'msg' => 'Unauthorized']);
    exit;
}

$myId = $_SESSION['user_id'];
$activeUsr = (int)($_GET['usr'] ?? 0);
$lastId = (int)($_GET['last_id'] ?? 0);

if (!$activeUsr) {
    echo json_encode(['status' => 'error', 'msg' => 'No selected user']);
    exit;
}

// Update read status for the requested user
dbUpdate("UPDATE messages SET is_read=1 WHERE receiver_id=? AND sender_id=?", [$myId, $activeUsr]);

// Fetch new messages since lastId
$messages = dbQuery("
    SELECT * FROM messages 
    WHERE (
            (sender_id=? AND receiver_id=?) OR 
            (sender_id=? AND receiver_id=?)
          )
      AND message_id > ?
    ORDER BY sent_at ASC
", [$myId, $activeUsr, $activeUsr, $myId, $lastId]);

$result = [];
foreach ($messages as $m) {
    $isMe = $m['sender_id'] == $myId;
    $result[] = [
        'msg_id' => $m['message_id'],
        'content' => nl2br(h($m['content'])),
        'time' => date('H:i', strtotime($m['sent_at'])),
        'isMe' => $isMe
    ];
}

echo json_encode(['status' => 'success', 'messages' => $result]);
?>

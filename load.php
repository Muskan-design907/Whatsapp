<?php
// load.php
session_start();
require_once 'db.php';
header('Content-Type: application/json; charset=utf-8');
 
if (!isset($_SESSION['user_id'])) {
    http_response_code(401); echo json_encode(['error'=>'unauth']); exit;
}
$me = (int)$_SESSION['user_id'];
$action = $_GET['action'] ?? '';
 
if ($action === 'contacts') {
    $q = $_GET['q'] ?? '';
    $like = '%' . $q . '%';
    // get other users plus last message
    $sql = "SELECT u.id, u.name, u.username, u.avatar,
      (SELECT message FROM messages m WHERE (m.sender_id = u.id AND m.receiver_id = ?) OR (m.sender_id = ? AND m.receiver_id = u.id) ORDER BY created_at DESC LIMIT 1) AS last_message
      FROM users u WHERE u.id != ? AND (u.name LIKE ? OR u.username LIKE ?) ORDER BY u.name ASC";
    $stmt = $conn->prepare($sql);
    if (!$stmt) { echo json_encode([]); exit; }
    $stmt->bind_param('iiiss', $me, $me, $me, $like, $like);
    $stmt->execute();
    $res = $stmt->get_result();
    $out = [];
    while ($r = $res->fetch_assoc()) { $out[] = $r; }
    echo json_encode($out);
    exit;
}
 
if ($action === 'messages') {
    $with = (int)($_GET['with'] ?? 0);
    $since = (int)($_GET['since'] ?? 0);
    if (!$with) { echo json_encode(['messages'=>[]]); exit; }
 
    if ($since === 0) {
        $stmt = $conn->prepare("SELECT id, sender_id, receiver_id, message, media, status, DATE_FORMAT(created_at,'%Y-%m-%d %H:%i') as created_at, UNIX_TIMESTAMP(created_at) as ts FROM messages WHERE (sender_id = ? AND receiver_id = ?) OR (sender_id = ? AND receiver_id = ?) ORDER BY created_at ASC LIMIT 500");
        $stmt->bind_param('iiii', $me, $with, $with, $me);
    } else {
        $stmt = $conn->prepare("SELECT id, sender_id, receiver_id, message, media, status, DATE_FORMAT(created_at,'%Y-%m-%d %H:%i') as created_at, UNIX_TIMESTAMP(created_at) as ts FROM messages WHERE ((sender_id = ? AND receiver_id = ?) OR (sender_id = ? AND receiver_id = ?)) AND UNIX_TIMESTAMP(created_at) > ? ORDER BY created_at ASC");
        $stmt->bind_param('iiiii', $me, $with, $with, $me, $since);
    }
    $stmt->execute();
    $res = $stmt->get_result();
    $messages = [];
    $last_ts = $since;
    while ($m = $res->fetch_assoc()) {
        if ($m['media']) { $m['media'] = $m['media']; }
        $messages[] = $m;
        if (!empty($m['ts'])) $last_ts = max($last_ts, (int)$m['ts']);
    }
    echo json_encode(['full' => ($since===0), 'messages'=>$messages, 'last_ts' => $last_ts]);
    exit;
}
 
if ($action === 'mark_seen') {
    $with = (int)($_GET['with'] ?? 0);
    if (!$with) { echo json_encode(['ok'=>0]); exit; }
    $stmt = $conn->prepare("UPDATE messages SET status = 'seen' WHERE sender_id = ? AND receiver_id = ? AND status != 'seen'");
    $stmt->bind_param('ii', $with, $me);
    $stmt->execute();
    echo json_encode(['ok'=>1]);
    exit;
}
 
echo json_encode(['error'=>'invalid_action']);
 

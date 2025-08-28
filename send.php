<?php
// send.php
session_start();
require_once 'db.php';
header('Content-Type: application/json; charset=utf-8');
 
if (!isset($_SESSION['user_id'])) {
    http_response_code(401); echo json_encode(['error'=>'unauth']); exit;
}
$me = (int)$_SESSION['user_id'];
$receiver = (int)($_POST['receiver_id'] ?? 0);
$message = trim($_POST['message'] ?? '');
 
if (!$receiver) { echo json_encode(['error'=>'no_receiver']); exit; }
 
$mediaPath = null;
if (!empty($_FILES['media']['name'])) {
    $f = $_FILES['media'];
    $ext = strtolower(pathinfo($f['name'], PATHINFO_EXTENSION));
    $allowed = ['png','jpg','jpeg','gif','webp'];
    if (!in_array($ext, $allowed) || $f['error'] !== 0) {
        echo json_encode(['error'=>'invalid_media']); exit;
    }
    if (!is_dir('uploads')) {
        if (!mkdir('uploads',0755,true)) {
            echo json_encode(['error'=>'cannot_create_upload_dir']); exit;
        }
    }
    $newname = 'uploads/msg_' . uniqid() . '.' . $ext;
    if (!move_uploaded_file($f['tmp_name'], $newname)) {
        echo json_encode(['error'=>'upload_failed']); exit;
    }
    $mediaPath = $newname;
}
 
// insert message
$stmt = $conn->prepare("INSERT INTO messages (sender_id, receiver_id, message, media, status) VALUES (?, ?, ?, ?, 'sent')");
$stmt->bind_param('iiss', $me, $receiver, $message, $mediaPath);
if ($stmt->execute()) {
    $msg_id = $stmt->insert_id;
    // simulate delivered
    $upd = $conn->prepare("UPDATE messages SET status='delivered' WHERE id = ?");
    $upd->bind_param('i', $msg_id);
    $upd->execute();
    echo json_encode(['success'=>1, 'id'=>$msg_id]);
    exit;
} else {
    echo json_encode(['error'=>'db_error', 'msg'=>$conn->error]);
    exit;
}
 

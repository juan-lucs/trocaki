<?php
session_start();
include 'protect.php';
include 'conexao.php';

$data = json_decode(file_get_contents('php://input'), true);
$vid  = (int)$data['video_id'];
$uid  = $_SESSION['id'];

// Tenta inserir a curtida (se já existir, o INSERT IGNORE não faz nada)
$stmt = $mysqli->prepare("
    INSERT IGNORE INTO video_likes (video_id, user_id)
    VALUES (?, ?)
");
$stmt->bind_param('ii', $vid, $uid);
$stmt->execute();

// Se não inseriu nenhuma linha (já curtido), apaga para descurtir
if ($stmt->affected_rows === 0) {
    $del = $mysqli->prepare("
        DELETE FROM video_likes
         WHERE video_id = ? AND user_id = ?
    ");
    $del->bind_param('ii', $vid, $uid);
    $del->execute();
}

// Conta quantas curtidas aquele vídeo tem agora
$res = $mysqli->query("
    SELECT COUNT(*) AS cnt
      FROM video_likes
     WHERE video_id = {$vid}
");
$count = $res->fetch_assoc()['cnt'];

// Retorna JSON: { "count": 5 }
echo json_encode(['count' => (int)$count]);

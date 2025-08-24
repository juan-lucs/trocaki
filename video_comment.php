<?php
session_start();
include 'protect.php';
include 'conexao.php';

$data = json_decode(file_get_contents('php://input'), true);
$vid  = (int)$data['video_id'];
$txt  = $mysqli->real_escape_string($data['comentario']);
$uid  = $_SESSION['id'];

// Insere o comentário
$mysqli->query("
    INSERT INTO video_comentarios (video_id, user_id, comentario)
    VALUES ({$vid}, {$uid}, '{$txt}')
");

// Prepara resposta: usuário, texto e timestamp
echo json_encode([
    'usuario'    => $_SESSION['nome'],
    'comentario' => $txt,
    'criado_em'  => date('Y-m-d H:i:s')
]);

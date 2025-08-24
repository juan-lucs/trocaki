<?php
// excluir_video.php

// Inicia sessão apenas se ainda não iniciada
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

include 'protect.php';
include 'conexao.php';

// Verifica se usuário está logado
if (!isset($_SESSION['id'])) {
    header('Location: login.php');
    exit;
}
$userId = (int) $_SESSION['id'];
$videoId = isset($_GET['id']) ? (int) $_GET['id'] : 0;

// 1) Busca o caminho do arquivo para remoção
$stmt = $mysqli->prepare("
    SELECT location
      FROM video
     WHERE video_id = ? AND usuario_id = ?
");
$stmt->bind_param('ii', $videoId, $userId);
$stmt->execute();
$stmt->bind_result($location);

if ($stmt->fetch()) {
    // Fecha o statement de SELECT antes de preparar o DELETE
    $stmt->close();

    // 2) Remove o arquivo físico, se existir
    if (file_exists($location)) {
        unlink($location);
    }

    // 3) Exclui o registro do banco
    $del = $mysqli->prepare("
        DELETE FROM video
         WHERE video_id = ? AND usuario_id = ?
    ");
    $del->bind_param('ii', $videoId, $userId);
    $del->execute();
    $del->close();
} else {
    // Fecha caso não tenha encontrado
    $stmt->close();
}

// 4) Redireciona de volta para “Meus Vídeos”
header('Location: adicionar_video.php');
exit;
?>

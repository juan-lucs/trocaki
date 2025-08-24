<?php
// excluir_video.php

// Inicia sessão apenas se não houver uma em andamento
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

include 'protect.php';
include 'conexao.php';  // fornece $mysqli

// Verifica se usuário está logado
if (!isset($_SESSION['id'])) {
    header('Location: login.php');
    exit;
}
$userId  = (int) $_SESSION['id'];
$videoId = isset($_GET['id']) ? (int) $_GET['id'] : 0;

// 1) Busca caminhos do vídeo e da capa
$stmt = $mysqli->prepare("
    SELECT location, thumbnail
      FROM video
     WHERE video_id = ? AND usuario_id = ?
");
$stmt->bind_param('ii', $videoId, $userId);
$stmt->execute();
$stmt->bind_result($location, $thumbnail);

if ($stmt->fetch()) {
    $stmt->close();

    // 2) Exclui arquivo de vídeo
    if ($location && file_exists($location)) {
        @unlink($location);
    }
    // 3) Exclui arquivo de thumbnail, se existir
    if ($thumbnail && file_exists($thumbnail)) {
        @unlink($thumbnail);
    }

    // 4) Remove registro do banco
    $del = $mysqli->prepare("DELETE FROM video WHERE video_id = ? AND usuario_id = ?");
    $del->bind_param('ii', $videoId, $userId);
    $del->execute();
    $del->close();
} else {
    // Fechar statement caso não existam resultados
    $stmt->close();
}

// 5) Redireciona de volta a “Meus Vídeos”
header('Location: adicionar_video.php?msg=excluido');
exit;
?>

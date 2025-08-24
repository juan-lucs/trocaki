<?php
session_start();
require_once 'conexao.php';

header('Content-Type: application/json');
if (!isset($_SESSION['id'])) {
    echo json_encode(['status'=>'error','msg'=>'Não autenticado']);
    exit;
}

$userId = (int) $_SESSION['id'];
$msgId  = isset($_POST['mensagem_id']) ? (int) $_POST['mensagem_id'] : 0;
$tipo   = $_POST['tipo'] ?? '';

if ($msgId <= 0) {
    echo json_encode(['status'=>'error','msg'=>'Mensagem inválida']);
    exit;
}

if ($tipo === 'so_para_mim') {
    // Registra exclusão apenas para este usuário
    $stmt = $mysqli->prepare("
      INSERT IGNORE INTO mensagens_exclusoes (mensagem_id, usuario_id)
      VALUES (?, ?)
    ");
    $stmt->bind_param('ii', $msgId, $userId);
    $ok = $stmt->execute();
    $stmt->close();

    if ($ok) {
        echo json_encode(['status'=>'success']);
    } else {
        echo json_encode(['status'=>'error','msg'=>'Falha ao excluir só para mim']);
    }

} elseif ($tipo === 'para_todos') {
    // Remove a mensagem inteira (ou marque como excluída para todos)
    $stmt = $mysqli->prepare("DELETE FROM mensagens WHERE id = ?");
    $stmt->bind_param('i', $msgId);
    $ok = $stmt->execute();
    $stmt->close();

    if ($ok) {
        echo json_encode(['status'=>'success']);
    } else {
        echo json_encode(['status'=>'error','msg'=>'Falha ao excluir para todos']);
    }

} else {
    echo json_encode(['status'=>'error','msg'=>'Tipo de exclusão inválido']);
}

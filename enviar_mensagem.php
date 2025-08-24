<?php
// enviar_mensagem.php
header('Content-Type: application/json; charset=utf-8');
session_start();
require_once 'conexao.php';

// 1) Verifica autenticação
if (!isset($_SESSION['id'])) {
    http_response_code(401);
    echo json_encode([
        'status'  => 'error',
        'message' => 'Usuário não autenticado'
    ]);
    exit;
}

$me           = (int) $_SESSION['id'];
$destinatario = isset($_POST['destinatario_id']) ? (int) $_POST['destinatario_id'] : 0;
$texto        = isset($_POST['mensagem']) ? trim($_POST['mensagem']) : '';

// 2) Validações simples
if ($destinatario <= 0) {
    http_response_code(400);
    echo json_encode([
        'status'  => 'error',
        'message' => 'Destinatário inválido'
    ]);
    exit;
}

if ($texto === '') {
    http_response_code(400);
    echo json_encode([
        'status'  => 'error',
        'message' => 'Mensagem vazia'
    ]);
    exit;
}

// 3) Prepara o INSERT usando MySQLi
$sql = "
    INSERT INTO mensagens (remetente_id, destinatario_id, mensagem, created_at)
    VALUES (?, ?, ?, NOW())
";
$stmt = $mysqli->prepare($sql);
if (!$stmt) {
    // Falha ao preparar (erro de sintaxe no SQL ou conexão quebrada)
    http_response_code(500);
    echo json_encode([
        'status'  => 'error',
        'message' => 'Erro ao preparar INSERT: ' . $mysqli->error
    ]);
    exit;
}

$stmt->bind_param('iis', $me, $destinatario, $texto);
if (!$stmt->execute()) {
    // Falha ao executar
    http_response_code(500);
    echo json_encode([
        'status'  => 'error',
        'message' => 'Falha ao executar INSERT: ' . $stmt->error
    ]);
    $stmt->close();
    exit;
}

$newId = $stmt->insert_id;
$stmt->close();

// 4) Se tudo der certo, devolve JSON de sucesso
echo json_encode([
    'status'     => 'success',
    'new_id'     => (int) $newId,
    'created_at' => date('Y-m-d H:i:s')
]);
exit;

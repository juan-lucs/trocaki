<?php
session_start();
include 'protect.php';
include 'conexao.php';

// Espera receber JSON pelo corpo da requisição
$data = json_decode(file_get_contents('php://input'), true);

// Verifica campos obrigatórios
if (
    !isset($data['item_solicitado']) ||
    !isset($data['item_ofertado'])   ||
    !isset($data['owner'])
) {
    http_response_code(400);
    echo json_encode(['error' => 'Parâmetros inválidos.']);
    exit;
}

$solicitante_id    = (int) $_SESSION['id'];
$item_solicitado   = (int) $data['item_solicitado'];
$item_ofertado     = (int) $data['item_ofertado'];
$destinatario_id   = (int) $data['owner'];

// Verifica se o usuário está tentando trocar consigo mesmo
if ($destinatario_id === $solicitante_id) {
    http_response_code(400);
    echo json_encode(['error' => 'Você não pode propor troca do seu próprio item.']);
    exit;
}

// Verifica se já existe essa troca (mesmos solicitante, solicitado e ofertado), independente do status
$chk = $mysqli->prepare("
    SELECT COUNT(*) 
      FROM trocas 
     WHERE solicitante_id = ? 
       AND item_solicitado_id = ? 
       AND item_ofertado_id = ?
");
$chk->bind_param('iii', $solicitante_id, $item_solicitado, $item_ofertado);
$chk->execute();
$chk->bind_result($countExists);
$chk->fetch();
$chk->close();

if ($countExists > 0) {
    // Já existe pelo menos um registro com esses três campos
    http_response_code(409);
    echo json_encode(['error' => 'Você já fez essa solicitação de troca anteriormente.']);
    exit;
}
//Faz o INSERT
$stmt = $mysqli->prepare("
    INSERT INTO trocas 
      (solicitante_id, destinatario_id, item_solicitado_id, item_ofertado_id)
    VALUES (?, ?, ?, ?)
");
if (!$stmt) {
    http_response_code(500);
    echo json_encode(['error' => 'Erro ao preparar a inserção: ' . $mysqli->error]);
    exit;
}
$stmt->bind_param(
    'iiii',
    $solicitante_id,
    $destinatario_id,
    $item_solicitado,
    $item_ofertado
);

if (!$stmt->execute()) {
    // Caso haja qualquer outro erro (além do duplicate, que já tratamos antes), retornamos 500
    http_response_code(500);
    echo json_encode(['error' => 'Falha ao cadastrar troca: ' . $stmt->error]);
    $stmt->close();
    exit;
}

$stmt->close();

//Respondemos com sucesso (código 201 Created)
http_response_code(201);
echo json_encode(['success' => true]);
exit;


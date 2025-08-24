<?php
// pegar_mensagem.php
header('Content-Type: application/json; charset=utf-8');
session_start();
require_once 'conexao.php'; // Certifique-se de que este arquivo define $mysqli

// 1) Verifica autenticação 
if (!isset($_SESSION['id'])) {
    http_response_code(401);
    echo json_encode(['status' => 'error', 'message' => 'Usuário não autenticado']);
    exit;
}

$me    = (int) $_SESSION['id'];
$other = isset($_GET['with']) ? (int) $_GET['with'] : 0;

if ($other <= 0) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Parâmetro “with” inválido']);
    exit;
}

// 2) Busca todas as mensagens entre $me e $other, ordenadas por data crescente,
//    exceto aquelas marcadas como “só para mim” pelo usuário atual.
$sql = "
    SELECT 
      m.id, 
      m.remetente_id, 
      m.destinatario_id, 
      m.mensagem, 
      DATE_FORMAT(m.created_at, '%d/%m/%Y %H:%i:%s') AS created_at
    FROM mensagens AS m
    WHERE (
            (m.remetente_id = ? AND m.destinatario_id = ?)
         OR (m.remetente_id = ? AND m.destinatario_id = ?)
          )
      AND NOT EXISTS (
            SELECT 1
              FROM mensagens_exclusoes AS me
             WHERE me.mensagem_id = m.id
               AND me.usuario_id   = ?
          )
    ORDER BY m.created_at ASC
";
$stmt = $mysqli->prepare($sql);
if (!$stmt) {
    http_response_code(500);
    echo json_encode([
        'status'  => 'error',
        'message' => 'Erro ao preparar SELECT: ' . $mysqli->error
    ]);
    exit;
}

// Bind: remetente=/destinatário nos dois sentidos, mais filtro de exclusão pelo próprio usuário
$stmt->bind_param('iiiii',
    $me,    // remetente = eu
    $other, // destinatário = outro
    $other, // remetente = outro
    $me,    // destinatário = eu
    $me     // exclusões do usuário atual
);

$stmt->execute();
$result = $stmt->get_result();

$msgs = [];
while ($row = $result->fetch_assoc()) {
    $msgs[] = [
        'id'              => (int) $row['id'],
        'remetente_id'    => (int) $row['remetente_id'],
        'destinatario_id' => (int) $row['destinatario_id'],
        'mensagem'        => htmlspecialchars($row['mensagem'], ENT_QUOTES, 'UTF-8'),
        'created_at'      => $row['created_at']
    ];
}

$stmt->close();

// 3) Retorna o array de mensagens em JSON puro
echo json_encode($msgs, JSON_UNESCAPED_UNICODE);
exit;

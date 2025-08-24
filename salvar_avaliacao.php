<?php
// salvar_avaliacao.php
ini_set('display_errors', 1);
error_reporting(E_ALL);

session_start();
include 'protect.php';
include 'conexao.php';

// lê o JSON do corpo
$input = json_decode(file_get_contents('php://input'), true);

if (!isset($input['produto_id'], $input['nota'])) {
  http_response_code(400);
  echo json_encode(['success'=>false,'error'=>'produto_id ou nota não enviados']);
  exit;
}

$produto = (int)$input['produto_id'];
$user    = $_SESSION['id'];
$nota    = (int)$input['nota'];
$coment  = trim($input['comentario'] ?? '');

$stmt = $mysqli->prepare("
  INSERT INTO avaliacoes (produto_id, user_id, nota, comentario)
  VALUES (?,?,?,?)
");
if (!$stmt) {
  http_response_code(500);
  echo json_encode(['success'=>false,'error'=>'prepare failed: '.$mysqli->error]);
  exit;
}

$stmt->bind_param('iiis',$produto,$user,$nota,$coment);

if (!$stmt->execute()) {
  http_response_code(500);
  echo json_encode(['success'=>false,'error'=>'execute failed: '.$stmt->error]);
  exit;
}

// sucesso
http_response_code(201);
echo json_encode(['success'=>true]);

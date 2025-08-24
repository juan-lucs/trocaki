<?php
session_start();
include 'protect.php';
include 'conexao.php';

$stmt = $mysqli->prepare("
  SELECT i.id, i.nome_item,
         IFNULL(AVG(a.nota),0) AS media,
         COUNT(a.id) AS total
    FROM itens i
    LEFT JOIN avaliacoes a ON a.produto_id = i.id
   WHERE i.usuario_id = ?
   GROUP BY i.id
");
$stmt->bind_param('i', $_SESSION['id']);
$stmt->execute();
$res = $stmt->get_result();

$out = [];
while($r=$res->fetch_assoc()){
  $out[] = [
    'id'       => $r['id'],
    'nome_item'=> $r['nome_item'],
    'media'    => (float)$r['media'],
    'total'    => (int)$r['total']
  ];
}
header('Content-Type: application/json');
echo json_encode($out);

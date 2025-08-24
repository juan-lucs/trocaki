<?php
session_start();
include 'protect.php';
include 'conexao.php';

$stmt = $mysqli->prepare("SELECT id, nome_item FROM itens WHERE usuario_id = ?");
$stmt->bind_param('i', $_SESSION['id']);
$stmt->execute();
$res = $stmt->get_result();
$items = [];

while($row = $res->fetch_assoc()) $items[] = $row;
header('Content-Type: application/json');
echo json_encode($items);

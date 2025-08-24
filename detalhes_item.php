<?php
// detalhes_item.php

header('Content-Type: application/json; charset=utf-8');
ini_set('display_errors', 0);
error_reporting(0);

// 1) Validação do parâmetro id: deve existir e conter apenas dígitos
if (empty($_GET['id']) || !ctype_digit($_GET['id'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Parâmetro id ausente ou inválido']);
    exit;
}

$id = (int) $_GET['id'];

require 'conexao.php';

// 2) Consulta dos dados do item
$stmt = $mysqli->prepare("
    SELECT 
        nome_item,
        descricao,
        localizacao,
        categoria,
        quantidade,
        DATE_FORMAT(data_criacao, '%d/%m/%Y %H:%i') AS criado_em,
        IF(image1<>'',1,0) AS has1,
        IF(image2<>'',1,0) AS has2,
        IF(image3<>'',1,0) AS has3
    FROM itens
    WHERE id = ?
");
$stmt->bind_param('i', $id);
$stmt->execute();
$res = $stmt->get_result();

if (!$res || $res->num_rows === 0) {
    http_response_code(404);
    echo json_encode(['error' => 'Item não encontrado']);
    exit;
}

$row = $res->fetch_assoc();

// 3) Monta array de URLs de imagens existentes
$imgs = [];
for ($k = 1; $k <= 3; $k++) {
    if ((int)$row["has{$k}"] === 1) {
        $imgs[] = "/ass/imagem_item.php?id={$id}&k={$k}";
    }
}

// 4) Retorna JSON final
echo json_encode([
    'nome_item'   => $row['nome_item'],
    'quantidade'  => (int)$row['quantidade'],
    'descricao'   => $row['descricao'],
    'localizacao' => $row['localizacao'],
    'categoria'   => $row['categoria'],
    'criado_em'   => $row['criado_em'],
    'images'      => $imgs
]);
<?php
// comentarios_detalhe.php

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

// 2) Consulta das avaliações
$stmt = $mysqli->prepare("
    SELECT
        a.nota,
        a.comentario,
        u.nome    AS usuario,
        DATE_FORMAT(a.criado_em, '%d/%m/%Y %H:%i') AS data
    FROM avaliacoes a
    JOIN usuarios u ON u.id = a.user_id
    WHERE a.produto_id = ?
    ORDER BY a.criado_em DESC
");
$stmt->bind_param('i', $id);
$stmt->execute();
$res = $stmt->get_result();

// 3) Monta array de avaliações
$out = [];
while ($r = $res->fetch_assoc()) {
    $out[] = [
        'usuario'    => $r['usuario'],
        'nota'       => (int)$r['nota'],
        'comentario' => $r['comentario'],
        'data'       => $r['data'],
    ];
}

// 4) Retorna JSON (array vazio se não houver avaliações)
echo json_encode($out);
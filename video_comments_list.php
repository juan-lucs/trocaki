<?php
session_start();
include 'protect.php';
include 'conexao.php';

$vid = (int)$_GET['video_id'];

$res = $mysqli->query("
    SELECT u.nome AS usuario,
           c.comentario,
           c.criado_em
      FROM video_comentarios c
      JOIN usuarios u ON u.id = c.user_id
     WHERE c.video_id = {$vid}
  ORDER BY c.criado_em DESC
     LIMIT 5
");

$out = $res->fetch_all(MYSQLI_ASSOC);
echo json_encode($out);

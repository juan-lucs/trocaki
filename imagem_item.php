<?php
include 'conexao.php';

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$k  = isset($_GET['k']) ? (int)$_GET['k'] : 1;
$k  = max(1, min(3, $k)); // garante que $k seja 1, 2 ou 3

$coluna = "image$k";

$query = $mysqli->prepare("SELECT $coluna FROM itens WHERE id = ?");
$query->bind_param("i", $id);
$query->execute();
$query->store_result();
$query->bind_result($imagem);
$query->fetch();

if ($query->num_rows > 0 && $imagem) {
    header("Content-Type: image/jpeg"); // ou image/png dependendo do tipo real
    echo $imagem;
} else {
    http_response_code(404);
}
?>

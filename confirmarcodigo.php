<?php
session_start();
$codigoRecebido = $_POST['codigo'] ?? '';

if (empty($codigoRecebido)) {
    echo "erro_codigo_vazio";
    exit;
}

if ($codigoRecebido == ($_SESSION['codigo_verificacao'] ?? '')) {
    unset($_SESSION['codigo_verificacao']);
    $_SESSION['codigo_confirmado'] = true;
    echo "codigo_ok";
} else {
    echo "erro_codigo_incorreto";
}
?>

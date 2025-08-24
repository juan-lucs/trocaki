<?php
//verificar_codigo.php
session_start();

$codigoRecebido = $_POST['codigo'];
$emailRecebido = $_POST['email'];

if (!isset($_SESSION['codigo_verificacao']) || !isset($_SESSION['email_verificado'])) {
    echo "Sessão expirada. Recarregue a página.";
    exit;
}

if ($emailRecebido === $_SESSION['email_verificado'] && $codigoRecebido == $_SESSION['codigo_verificacao']) {
    echo "Verificação concluída! Conta criada com sucesso.";
    session_destroy();
} else {
    echo "Código incorreto ou email não confere.";
}
?>

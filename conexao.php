<?php


$host     = '127.0.0.1';
$usuario  = 'root';
$senha    = '';          
$database = 'sistema';
$porta    =3406;

$mysqli = new mysqli($host, $usuario, $senha, $database, $porta);

if ($mysqli->connect_errno) {
    die("Falha ao conectar ao banco ({$mysqli->connect_errno}): {$mysqli->connect_error}");
}
//http://127.0.0.1/phpmyadmin/

<?php
require_once 'config.php';

try {
    $pdo = new PDO(
        "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET,
        DB_USER,
        DB_PASS,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
            PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES " . DB_CHARSET
        ]
    );
} catch (PDOException $e) {
    $error = "Erro de conexão com o banco de dados: " . $e->getMessage();
    error_log($error);
    
    if (ENVIRONMENT === 'development') {
        die($error);
    } else {
        die("Erro interno do servidor. Por favor, tente novamente mais tarde.");
    }
}
?>
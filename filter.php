<?php
require_once 'config/config.php';
require_once 'classes/Database.php';
require_once 'classes/Document.php';

header('Content-Type: application/json');

try {
    $search = filter_input(INPUT_POST, 'search', FILTER_SANITIZE_STRING) ?? '';
    $tipo = filter_input(INPUT_POST, 'tipo', FILTER_SANITIZE_STRING) ?? '';
    $page = filter_input(INPUT_GET, 'page', FILTER_VALIDATE_INT) ?? 1;

    $document = new Document();
    $docs = $document->getDocuments($search, $tipo, $page);

    echo json_encode([
        'success' => true,
        'data' => $docs
    ]);
} catch (Exception $e) {
    error_log($e->getMessage(), 3, LOG_FILE);
    echo json_encode([
        'success' => false,
        'message' => 'Erro ao processar a requisição'
    ]);
}
?>
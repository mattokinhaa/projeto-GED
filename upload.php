<?php
// Configuração do banco de dados
$host = "localhost";
$dbname = "ged_system";
$username = "root";
$password = "";

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Processar o filtro
    $search = $_POST['search'] ?? '';
    $tipo = $_POST['tipo'] ?? '';

    $query = "SELECT * FROM documentos WHERE 1=1";
    if (!empty($search)) {
        $query .= " AND nome LIKE :search";
    }
    if (!empty($tipo)) {
        $query .= " AND tipo = :tipo";
    }

    $stmt = $pdo->prepare($query);

    if (!empty($search)) {
        $stmt->bindValue(':search', "%$search%");
    }
    if (!empty($tipo)) {
        $stmt->bindValue(':tipo', $tipo);
    }

    $stmt->execute();
    $result = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Exibir os documentos
    foreach ($result as $doc) {
        echo "<div>";
        echo "<h3>{$doc['nome']}</h3>";
        echo "<p>Tipo: {$doc['tipo']}</p>";
        echo "<p>Data: {$doc['data_emissao']}</p>";
        echo "</div>";
    }
} catch (PDOException $e) {
    echo "Erro: " . $e->getMessage();
}
?>

<?php
require_once 'config/config.php';
require_once 'classes/Database.php';
require_once 'classes/Document.php';
require_once 'includes/header.php';

$document = new Document();
$page = $_GET['page'] ?? 1;
$search = $_GET['search'] ?? '';
$tipo = $_GET['tipo'] ?? '';

$docs = $document->getDocuments($search, $tipo, $page);
?>

<div class="container">
    <h1>Sistema GED</h1>
    
    <!-- Formulário de Busca -->
    <form method="GET" class="search-form">
        <div class="row">
            <div class="col-md-4">
                <input type="text" name="search" value="<?= htmlspecialchars($search) ?>" 
                       placeholder="Buscar documento..." class="form-control">
            </div>
            <div class="col-md-4">
                <select name="tipo" class="form-control">
                    <option value="">Todos os tipos</option>
                    <option value="contrato" <?= $tipo === 'contrato' ? 'selected' : '' ?>>Contrato</option>
                    <option value="nota_fiscal" <?= $tipo === 'nota_fiscal' ? 'selected' : '' ?>>Nota Fiscal</option>
                    <option value="relatorio" <?= $tipo === 'relatorio' ? 'selected' : '' ?>>Relatório</option>
                </select>
            </div>
            <div class="col-md-4">
                <button type="submit" class="btn btn-primary">Buscar</button>
            </div>
        </div>
    </form>

    <!-- Lista de Documentos -->
    <div class="documents-list">
        <?php if ($docs): ?>
            <?php foreach ($docs as $doc): ?>
                <div class="document-item">
                    <h3><?= htmlspecialchars($doc['nome']) ?></h3>
                    <p>Tipo: <?= htmlspecialchars($doc['tipo']) ?></p>
                    <p>Data: <?= date('d/m/Y H:i', strtotime($doc['data_upload'])) ?></p>
                    <a href="uploads/<?= htmlspecialchars($doc['caminho']) ?>" 
                       target="_blank" class="btn btn-info">Visualizar</a>
                </div>
            <?php endforeach; ?>
        <?php else: ?>
            <p>Nenhum documento encontrado.</p>
        <?php endif; ?>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?>
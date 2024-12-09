document.addEventListener('DOMContentLoaded', function() {
    // Manipulação do formulário de busca
    const searchForm = document.querySelector('.search-form');
    if (searchForm) {
        searchForm.addEventListener('submit', function(e) {
            e.preventDefault();
            const formData = new FormData(this);
            
            fetch('filter.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => updateDocumentsList(data))
            .catch(error => console.error('Erro:', error));
        });
    }

    // Upload de arquivo com preview
    const fileInput = document.querySelector('input[type="file"]');
    if (fileInput) {
        fileInput.addEventListener('change', function(e) {
            const file = e.target.files[0];
            if (file) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    // Adicionar preview se necessário
                };
                reader.readAsDataURL(file);
            }
        });
    }

    // Função para atualizar a lista de documentos
    function updateDocumentsList(documents) {
        const listContainer = document.querySelector('.documents-list');
        if (!listContainer) return;

        listContainer.innerHTML = '';

        if (documents.length === 0) {
            listContainer.innerHTML = '<p>Nenhum documento encontrado.</p>';
            return;
        }

        documents.forEach(doc => {
            const docElement = document.createElement('div');
            docElement.className = 'document-item';
            docElement.innerHTML = `
                <h3>${doc.nome}</h3>
                <p>Tipo: ${doc.tipo}</p>
                <p>Data: ${doc.data_upload}</p>
                <a href="uploads/${doc.caminho}" target="_blank" class="btn btn-info">Visualizar</a>
            `;
            listContainer.appendChild(docElement);
        });
    }
});
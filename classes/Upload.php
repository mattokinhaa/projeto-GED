<?php
class Upload {
    private $allowedTypes = [
        'application/pdf' => 'pdf',
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'application/msword' => 'doc',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document' => 'docx'
    ];
    
    private $maxSize = 5242880; // 5MB em bytes
    private $uploadPath;

    public function __construct() {
        $this->uploadPath = UPLOAD_PATH;
        $this->verificarDiretorios();
    }

    private function verificarDiretorios() {
        $diretorios = ['asos', 'exames', 'anexos_aso', 'anexos_exame', 'temp'];
        
        foreach ($diretorios as $dir) {
            $path = $this->uploadPath . '/' . $dir;
            if (!file_exists($path)) {
                if (!mkdir($path, 0755, true)) {
                    throw new Exception("Não foi possível criar o diretório: {$dir}");
                }
            }
        }
    }

    public function uploadFile($file, $destino) {
        try {
            if (!isset($file['tmp_name']) || empty($file['tmp_name'])) {
                throw new Exception("Nenhum arquivo enviado");
            }

            // Validações básicas
            $this->validarArquivo($file);

            // Gerar nome único para o arquivo
            $extensao = $this->allowedTypes[$file['type']];
            $nomeArquivo = $this->gerarNomeUnico($extensao);

            // Definir caminho completo
            $caminhoCompleto = $this->uploadPath . '/' . $destino . '/' . $nomeArquivo;

            // Mover arquivo
            if (!move_uploaded_file($file['tmp_name'], $caminhoCompleto)) {
                throw new Exception("Erro ao mover arquivo");
            }

            // Retornar caminho relativo para salvar no banco
            return $destino . '/' . $nomeArquivo;

        } catch (Exception $e) {
            error_log("Erro no upload de arquivo: " . $e->getMessage());
            throw $e;
        }
    }

    public function uploadMultiplos($files, $destino) {
        $uploads = [];
        
        foreach ($files['tmp_name'] as $key => $tmp_name) {
            if ($files['error'][$key] === UPLOAD_ERR_OK) {
                $arquivo = [
                    'name' => $files['name'][$key],
                    'type' => $files['type'][$key],
                    'tmp_name' => $tmp_name,
                    'error' => $files['error'][$key],
                    'size' => $files['size'][$key]
                ];

                try {
                    $uploads[] = $this->uploadFile($arquivo, $destino);
                } catch (Exception $e) {
                    // Log do erro mas continua com os outros arquivos
                    error_log("Erro no upload do arquivo {$arquivo['name']}: " . $e->getMessage());
                }
            }
        }

        return $uploads;
    }

    private function validarArquivo($file) {
        // Verificar se houve erro no upload
        if ($file['error'] !== UPLOAD_ERR_OK) {
            throw new Exception($this->getUploadErrorMessage($file['error']));
        }

        // Verificar tipo de arquivo
        if (!isset($this->allowedTypes[$file['type']])) {
            throw new Exception("Tipo de arquivo não permitido");
        }

        // Verificar tamanho
        if ($file['size'] > $this->maxSize) {
            throw new Exception("Arquivo muito grande. Tamanho máximo permitido: 5MB");
        }

        // Verificar se é realmente um arquivo enviado via POST
        if (!is_uploaded_file($file['tmp_name'])) {
            throw new Exception("Arquivo inválido");
        }

        // Verificar se o arquivo é realmente do tipo informado
        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $mimeType = $finfo->file($file['tmp_name']);
        
        if (!array_key_exists($mimeType, $this->allowedTypes)) {
            throw new Exception("Tipo de arquivo inválido");
        }
    }

    private function gerarNomeUnico($extensao) {
        return uniqid('doc_') . '_' . date('Ymd_His') . '.' . $extensao;
    }

    public function removerArquivo($caminhoRelativo) {
        $caminhoCompleto = $this->uploadPath . '/' . $caminhoRelativo;
        
        if (file_exists($caminhoCompleto)) {
            if (!unlink($caminhoCompleto)) {
                throw new Exception("Não foi possível remover o arquivo");
            }
            return true;
        }
        return false;
    }

    public function removerMultiplos($caminhos) {
        $erros = [];
        
        foreach ($caminhos as $caminho) {
            try {
                $this->removerArquivo($caminho);
            } catch (Exception $e) {
                $erros[] = "Erro ao remover {$caminho}: " . $e->getMessage();
            }
        }

        if (!empty($erros)) {
            throw new Exception(implode("\n", $erros));
        }

        return true;
    }

    private function getUploadErrorMessage($errorCode) {
        switch ($errorCode) {
            case UPLOAD_ERR_INI_SIZE:
                return "O arquivo excede o tamanho máximo permitido pelo PHP";
            case UPLOAD_ERR_FORM_SIZE:
                return "O arquivo excede o tamanho máximo permitido pelo formulário";
            case UPLOAD_ERR_PARTIAL:
                return "O upload do arquivo foi feito parcialmente";
            case UPLOAD_ERR_NO_FILE:
                return "Nenhum arquivo foi enviado";
            case UPLOAD_ERR_NO_TMP_DIR:
                return "Pasta temporária ausente";
            case UPLOAD_ERR_CANT_WRITE:
                return "Falha ao gravar arquivo em disco";
            case UPLOAD_ERR_EXTENSION:
                return "Uma extensão PHP interrompeu o upload do arquivo";
            default:
                return "Erro desconhecido no upload";
        }
    }

    public function moverArquivoTemp($origem, $destino) {
        $caminhoOrigem = $this->uploadPath . '/temp/' . $origem;
        $caminhoDestino = $this->uploadPath . '/' . $destino;

        if (!file_exists($caminhoOrigem)) {
            throw new Exception("Arquivo temporário não encontrado");
        }

        if (!rename($caminhoOrigem, $caminhoDestino)) {
            throw new Exception("Erro ao mover arquivo temporário");
        }

        return true;
    }

    public function getArquivoInfo($caminhoRelativo) {
        $caminhoCompleto = $this->uploadPath . '/' . $caminhoRelativo;
        
        if (!file_exists($caminhoCompleto)) {
            throw new Exception("Arquivo não encontrado");
        }

        return [
            'nome' => basename($caminhoRelativo),
            'tamanho' => filesize($caminhoCompleto),
            'tipo' => mime_content_type($caminhoCompleto),
            'data_modificacao' => filemtime($caminhoCompleto)
        ];
    }
}
?>
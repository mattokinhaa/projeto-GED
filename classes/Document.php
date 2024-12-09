<?php
class Document {
    private $db;
    private $upload;
    private $validator;

    public function __construct() {
        $this->db = Database::getInstance()->getConnection();
        $this->upload = new Upload();
        $this->validator = new Validator();
    }

    public function save($data, $files) {
        try {
            $this->db->beginTransaction();

            // Validar dados
            if (!$this->validator->validarDocumento($data)) {
                throw new Exception(implode(", ", $this->validator->getErrors()));
            }

            // Upload do arquivo principal
            $arquivo_path = $this->upload->uploadFile($files['arquivo'], 'documentos');
            if (!$arquivo_path) {
                throw new Exception("Erro ao fazer upload do arquivo");
            }

            // Inserir documento
            $sql = "INSERT INTO documentos (
                tipo, nome, descricao, arquivo_path, 
                data_emissao, data_vencimento, id_funcionario,
                created_by, created_at
            ) VALUES (
                :tipo, :nome, :descricao, :arquivo_path,
                :data_emissao, :data_vencimento, :id_funcionario,
                :created_by, NOW()
            )";

            $stmt = $this->db->prepare($sql);
            $stmt->execute([
                ':tipo' => $data['tipo'],
                ':nome' => $data['nome'],
                ':descricao' => $data['descricao'],
                ':arquivo_path' => $arquivo_path,
                ':data_emissao' => $data['data_emissao'],
                ':data_vencimento' => $data['data_vencimento'],
                ':id_funcionario' => $data['id_funcionario'],
                ':created_by' => $_SESSION['user_id']
            ]);

            $id_documento = $this->db->lastInsertId();

            // Upload e registro de anexos adicionais
            if (isset($files['anexos'])) {
                foreach ($files['anexos']['tmp_name'] as $key => $tmp_name) {
                    if ($files['anexos']['error'][$key] === UPLOAD_ERR_OK) {
                        $anexo = [
                            'name' => $files['anexos']['name'][$key],
                            'type' => $files['anexos']['type'][$key],
                            'tmp_name' => $tmp_name,
                            'error' => $files['anexos']['error'][$key],
                            'size' => $files['anexos']['size'][$key]
                        ];

                        $anexo_path = $this->upload->uploadFile($anexo, 'anexos');
                        
                        if ($anexo_path) {
                            $sql = "INSERT INTO documento_anexos (
                                id_documento, arquivo_path, created_at
                            ) VALUES (
                                :id_documento, :arquivo_path, NOW()
                            )";

                            $stmt = $this->db->prepare($sql);
                            $stmt->execute([
                                ':id_documento' => $id_documento,
                                ':arquivo_path' => $anexo_path
                            ]);
                        }
                    }
                }
            }

            // Registrar log
            LogSystem::registrar(
                'documentos', 
                'criar', 
                $id_documento, 
                "Documento criado: {$data['nome']}"
            );

            $this->db->commit();
            return $id_documento;

        } catch (Exception $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    public function getById($id) {
        $sql = "SELECT d.*, 
                       f.nome as funcionario_nome,
                       u.nome as criado_por
                FROM documentos d
                JOIN funcionarios f ON d.id_funcionario = f.id
                JOIN usuarios u ON d.created_by = u.id
                WHERE d.id = :id";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([':id' => $id]);
        
        $documento = $stmt->fetch();
        
        if ($documento) {
            // Buscar anexos
            $sql = "SELECT * FROM documento_anexos 
                    WHERE id_documento = :id_documento";
            
            $stmt = $this->db->prepare($sql);
            $stmt->execute([':id_documento' => $id]);
            
            $documento['anexos'] = $stmt->fetchAll();
        }

        return $documento;
    }

    public function update($id, $data, $files = null) {
        try {
            $this->db->beginTransaction();

            if (!$this->validator->validarDocumento($data)) {
                throw new Exception(implode(", ", $this->validator->getErrors()));
            }

            $sql = "UPDATE documentos SET
                    tipo = :tipo,
                    nome = :nome,
                    descricao = :descricao,
                    data_emissao = :data_emissao,
                    data_vencimento = :data_vencimento,
                    updated_by = :updated_by,
                    updated_at = NOW()
                    WHERE id = :id";

            $stmt = $this->db->prepare($sql);
            $stmt->execute([
                ':tipo' => $data['tipo'],
                ':nome' => $data['nome'],
                ':descricao' => $data['descricao'],
                ':data_emissao' => $data['data_emissao'],
                ':data_vencimento' => $data['data_vencimento'],
                ':updated_by' => $_SESSION['user_id'],
                ':id' => $id
            ]);

            // Processar novo arquivo principal se fornecido
            if (isset($files['arquivo']) && $files['arquivo']['error'] === UPLOAD_ERR_OK) {
                $arquivo_path = $this->upload->uploadFile($files['arquivo'], 'documentos');
                if ($arquivo_path) {
                    $stmt = $this->db->prepare("UPDATE documentos SET arquivo_path = :arquivo_path WHERE id = :id");
                    $stmt->execute([':arquivo_path' => $arquivo_path, ':id' => $id]);
                }
            }

            // Processar novos anexos
            if (isset($files['anexos'])) {
                foreach ($files['anexos']['tmp_name'] as $key => $tmp_name) {
                    if ($files['anexos']['error'][$key] === UPLOAD_ERR_OK) {
                        $anexo = [
                            'name' => $files['anexos']['name'][$key],
                            'type' => $files['anexos']['type'][$key],
                            'tmp_name' => $tmp_name,
                            'error' => $files['anexos']['error'][$key],
                            'size' => $files['anexos']['size'][$key]
                        ];

                        $anexo_path = $this->upload->uploadFile($anexo, 'anexos');
                        
                        if ($anexo_path) {
                            $sql = "INSERT INTO documento_anexos (
                                id_documento, arquivo_path, created_at
                            ) VALUES (
                                :id_documento, :arquivo_path, NOW()
                            )";

                            $stmt = $this->db->prepare($sql);
                            $stmt->execute([
                                ':id_documento' => $id,
                                ':arquivo_path' => $anexo_path
                            ]);
                        }
                    }
                }
            }

            LogSystem::registrar(
                'documentos', 
                'atualizar', 
                $id, 
                "Documento atualizado: {$data['nome']}"
            );

            $this->db->commit();
            return true;

        } catch (Exception $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    public function delete($id) {
        try {
            $this->db->beginTransaction();

            // Verificar existência do documento
            $documento = $this->getById($id);
            if (!$documento) {
                throw new Exception("Documento não encontrado");
            }

            // Deletar anexos físicos e registros
            $sql = "SELECT arquivo_path FROM documento_anexos WHERE id_documento = :id";
            $stmt = $this->db->prepare($sql);
            $stmt->execute([':id' => $id]);
            $anexos = $stmt->fetchAll();

            foreach ($anexos as $anexo) {
                if (file_exists(UPLOAD_PATH . '/' . $anexo['arquivo_path'])) {
                    unlink(UPLOAD_PATH . '/' . $anexo['arquivo_path']);
                }
            }

            // Deletar registros de anexos
            $stmt = $this->db->prepare("DELETE FROM documento_anexos WHERE id_documento = :id");
            $stmt->execute([':id' => $id]);

            // Deletar arquivo principal
            if (file_exists(UPLOAD_PATH . '/' . $documento['arquivo_path'])) {
                unlink(UPLOAD_PATH . '/' . $documento['arquivo_path']);
            }

            // Deletar documento
            $stmt = $this->db->prepare("DELETE FROM documentos WHERE id = :id");
            $stmt->execute([':id' => $id]);

            LogSystem::registrar(
                'documentos', 
                'deletar', 
                $id, 
                "Documento deletado: {$documento['nome']}"
            );

            $this->db->commit();
            return true;

        } catch (Exception $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    public function search($filters = [], $page = 1, $limit = ITEMS_PER_PAGE) {
        $where = ["1=1"];
        $params = [];
        
        if (!empty($filters['tipo'])) {
            $where[] = "tipo = :tipo";
            $params[':tipo'] = $filters['tipo'];
        }
        
        if (!empty($filters['nome'])) {
            $where[] = "nome LIKE :nome";
            $params[':nome'] = "%{$filters['nome']}%";
        }
        
        if (!empty($filters['id_funcionario'])) {
            $where[] = "id_funcionario = :id_funcionario";
            $params[':id_funcionario'] = $filters['id_funcionario'];
        }

        $offset = ($page - 1) * $limit;
        
        $sql = "SELECT d.*, 
                       f.nome as funcionario_nome,
                       u.nome as criado_por
                FROM documentos d
                JOIN funcionarios f ON d.id_funcionario = f.id
                JOIN usuarios u ON d.created_by = u.id
                WHERE " . implode(" AND ", $where) . "
                ORDER BY d.created_at DESC
                LIMIT :limit OFFSET :offset";

        $stmt = $this->db->prepare($sql);
        
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value);
        }
        
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        
        $stmt->execute();
        
        return $stmt->fetchAll();
    }
}
?>
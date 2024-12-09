<?php
class Aso {
    private $db;
    private $upload;
    private $validator;

    public function __construct() {
        $this->db = Database::getInstance()->getConnection();
        $this->upload = new Upload();
        $this->validator = new Validator();
    }

    public function create($data, $files) {
        try {
            $this->db->beginTransaction();

            if (!$this->validator->validarAso($data)) {
                throw new Exception(implode(", ", $this->validator->getErrors()));
            }

            // Upload do ASO
            $arquivo_path = $this->upload->uploadFile($files['arquivo'], 'asos');
            if (!$arquivo_path) {
                throw new Exception("Erro ao fazer upload do ASO");
            }

            $sql = "INSERT INTO asos (
                id_funcionario, tipo_aso, data_emissao, data_vencimento,
                resultado, restricoes, medico_nome, medico_crm,
                arquivo_path, observacoes, created_by, created_at
            ) VALUES (
                :id_funcionario, :tipo_aso, :data_emissao, :data_vencimento,
                :resultado, :restricoes, :medico_nome, :medico_crm,
                :arquivo_path, :observacoes, :created_by, NOW()
            )";

            $stmt = $this->db->prepare($sql);
            $stmt->execute([
                ':id_funcionario' => $data['id_funcionario'],
                ':tipo_aso' => $data['tipo_aso'],
                ':data_emissao' => $data['data_emissao'],
                ':data_vencimento' => $data['data_vencimento'],
                ':resultado' => $data['resultado'],
                ':restricoes' => $data['restricoes'] ?? null,
                ':medico_nome' => $data['medico_nome'],
                ':medico_crm' => $data['medico_crm'],
                ':arquivo_path' => $arquivo_path,
                ':observacoes' => $data['observacoes'] ?? null,
                ':created_by' => $_SESSION['user_id']
            ]);

            $id_aso = $this->db->lastInsertId();

            // Processar exames relacionados
            if (isset($data['exames'])) {
                foreach ($data['exames'] as $exame) {
                    $sql = "INSERT INTO exames_realizados (
                        id_aso, id_tipo_exame, data_realizacao,
                        data_vencimento, resultado, created_at
                    ) VALUES (
                        :id_aso, :id_tipo_exame, :data_realizacao,
                        :data_vencimento, :resultado, NOW()
                    )";

                    $stmt = $this->db->prepare($sql);
                    $stmt->execute([
                        ':id_aso' => $id_aso,
                        ':id_tipo_exame' => $exame['tipo'],
                        ':data_realizacao' => $exame['data_realizacao'],
                        ':data_vencimento' => $exame['data_vencimento'],
                        ':resultado' => $exame['resultado']
                    ]);
                }
            }

            // Processar anexos adicionais
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

                        $anexo_path = $this->upload->uploadFile($anexo, 'anexos_aso');
                        
                        if ($anexo_path) {
                            $sql = "INSERT INTO aso_anexos (
                                id_aso, arquivo_path, created_at
                            ) VALUES (
                                :id_aso, :arquivo_path, NOW()
                            )";

                            $stmt = $this->db->prepare($sql);
                            $stmt->execute([
                                ':id_aso' => $id_aso,
                                ':arquivo_path' => $anexo_path
                            ]);
                        }
                    }
                }
            }

            // Criar alerta de vencimento
            $alert = new Alert();
            $alert->criarAlertaVencimento('ASO', $id_aso, $data['data_vencimento']);

            LogSystem::registrar(
                'asos', 
                'criar', 
                $id_aso, 
                "ASO criado para funcionário ID: {$data['id_funcionario']}"
            );

            $this->db->commit();
            return $id_aso;

        } catch (Exception $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    public function update($id, $data, $files = null) {
        try {
            $this->db->beginTransaction();

            if (!$this->validator->validarAso($data)) {
                throw new Exception(implode(", ", $this->validator->getErrors()));
            }

            $sql = "UPDATE asos SET 
                    tipo_aso = :tipo_aso,
                    data_emissao = :data_emissao,
                    data_vencimento = :data_vencimento,
                    resultado = :resultado,
                    restricoes = :restricoes,
                    medico_nome = :medico_nome,
                    medico_crm = :medico_crm,
                    observacoes = :observacoes,
                    updated_by = :updated_by,
                    updated_at = NOW()
                    WHERE id = :id";

            $params = [
                ':tipo_aso' => $data['tipo_aso'],
                ':data_emissao' => $data['data_emissao'],
                ':data_vencimento' => $data['data_vencimento'],
                ':resultado' => $data['resultado'],
                ':restricoes' => $data['restricoes'] ?? null,
                ':medico_nome' => $data['medico_nome'],
                ':medico_crm' => $data['medico_crm'],
                ':observacoes' => $data['observacoes'] ?? null,
                ':updated_by' => $_SESSION['user_id'],
                ':id' => $id
            ];

            // Se um novo arquivo foi enviado
            if (isset($files['arquivo']) && $files['arquivo']['error'] === UPLOAD_ERR_OK) {
                $arquivo_path = $this->upload->uploadFile($files['arquivo'], 'asos');
                if ($arquivo_path) {
                    $sql = str_replace(
                        'updated_at = NOW()',
                        'arquivo_path = :arquivo_path, updated_at = NOW()',
                        $sql
                    );
                    $params[':arquivo_path'] = $arquivo_path;

                    // Remover arquivo antigo
                    $old_aso = $this->getById($id);
                    if ($old_aso && file_exists(UPLOAD_PATH . '/' . $old_aso['arquivo_path'])) {
                        unlink(UPLOAD_PATH . '/' . $old_aso['arquivo_path']);
                    }
                }
            }

            $stmt = $this->db->prepare($sql);
            $stmt->execute($params);

            // Atualizar alerta de vencimento
            $alert = new Alert();
            $alert->atualizarAlertaVencimento('ASO', $id, $data['data_vencimento']);

            LogSystem::registrar(
                'asos', 
                'atualizar', 
                $id, 
                "ASO atualizado"
            );

            $this->db->commit();
            return true;

        } catch (Exception $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    public function getById($id) {
        $sql = "SELECT a.*, 
                       f.nome as funcionario_nome,
                       f.cargo as funcionario_cargo,
                       f.matricula as funcionario_matricula,
                       e.razao_social as empresa_nome,
                       u.nome as criado_por,
                       u2.nome as atualizado_por
                FROM asos a
                JOIN funcionarios f ON a.id_funcionario = f.id
                JOIN empresas e ON f.id_empresa = e.id
                JOIN usuarios u ON a.created_by = u.id
                LEFT JOIN usuarios u2 ON a.updated_by = u2.id
                WHERE a.id = :id";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([':id' => $id]);
        $aso = $stmt->fetch();

        if ($aso) {
            // Buscar exames relacionados
            $sql = "SELECT er.*, te.nome as tipo_exame_nome
                    FROM exames_realizados er
                    JOIN tipos_exames te ON er.id_tipo_exame = te.id
                    WHERE er.id_aso = :id_aso";
            
            $stmt = $this->db->prepare($sql);
            $stmt->execute([':id_aso' => $id]);
            $aso['exames'] = $stmt->fetchAll();

            // Buscar anexos
            $sql = "SELECT * FROM aso_anexos WHERE id_aso = :id_aso";
            $stmt = $this->db->prepare($sql);
            $stmt->execute([':id_aso' => $id]);
            $aso['anexos'] = $stmt->fetchAll();
        }

        return $aso;
    }

    public function delete($id) {
        try {
            $this->db->beginTransaction();

            // Verificar existência do ASO
            $aso = $this->getById($id);
            if (!$aso) {
                throw new Exception("ASO não encontrado");
            }

            // Deletar anexos físicos e registros
            foreach ($aso['anexos'] as $anexo) {
                if (file_exists(UPLOAD_PATH . '/' . $anexo['arquivo_path'])) {
                    unlink(UPLOAD_PATH . '/' . $anexo['arquivo_path']);
                }
            }

            // Deletar registros de anexos
            $stmt = $this->db->prepare("DELETE FROM aso_anexos WHERE id_aso = :id");
            $stmt->execute([':id' => $id]);

            // Deletar exames relacionados
            $stmt = $this->db->prepare("DELETE FROM exames_realizados WHERE id_aso = :id");
            $stmt->execute([':id' => $id]);

            // Deletar alertas
            $stmt = $this->db->prepare("DELETE FROM alertas WHERE tipo = 'ASO' AND id_referencia = :id");
            $stmt->execute([':id' => $id]);

            // Deletar arquivo principal
            if (file_exists(UPLOAD_PATH . '/' . $aso['arquivo_path'])) {
                unlink(UPLOAD_PATH . '/' . $aso['arquivo_path']);
            }

            // Deletar ASO
            $stmt = $this->db->prepare("DELETE FROM asos WHERE id = :id");
            $stmt->execute([':id' => $id]);

            LogSystem::registrar(
                'asos', 
                'deletar', 
                $id, 
                "ASO deletado - Funcionário: {$aso['funcionario_nome']}"
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
        
        if (!empty($filters['tipo_aso'])) {
            $where[] = "a.tipo_aso = :tipo_aso";
            $params[':tipo_aso'] = $filters['tipo_aso'];
        }
        
        if (!empty($filters['funcionario'])) {
            $where[] = "f.nome LIKE :funcionario";
            $params[':funcionario'] = "%{$filters['funcionario']}%";
        }
        
        if (!empty($filters['empresa'])) {
            $where[] = "e.razao_social LIKE :empresa";
            $params[':empresa'] = "%{$filters['empresa']}%";
        }
        
        if (!empty($filters['data_inicio'])) {
            $where[] = "a.data_emissao >= :data_inicio";
            $params[':data_inicio'] = $filters['data_inicio'];
        }
        
        if (!empty($filters['data_fim'])) {
            $where[] = "a.data_emissao <= :data_fim";
            $params[':data_fim'] = $filters['data_fim'];
        }
        
        if (isset($filters['vencidos']) && $filters['vencidos']) {
            $where[] = "a.data_vencimento < CURDATE()";
        }

        $offset = ($page - 1) * $limit;
        
        $sql = "SELECT a.*, 
                       f.nome as funcionario_nome,
                       f.cargo as funcionario_cargo,
                       e.razao_social as empresa_nome,
                       CASE 
                           WHEN a.data_vencimento < CURDATE() THEN 'Vencido'
                           WHEN a.data_vencimento <= DATE_ADD(CURDATE(), INTERVAL 30 DAY) THEN 'Próximo ao Vencimento'
                           ELSE 'Regular'
                       END as status_vencimento
                FROM asos a
                JOIN funcionarios f ON a.id_funcionario = f.id
                JOIN empresas e ON f.id_empresa = e.id
                WHERE " . implode(" AND ", $where) . "
                ORDER BY a.data_vencimento ASC
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

    public function getVencimentos($dias = 30) {
        $sql = "SELECT a.*, 
                       f.nome as funcionario_nome,
                       f.cargo as funcionario_cargo,
                       e.razao_social as empresa_nome,
                       DATEDIFF(a.data_vencimento, CURDATE()) as dias_para_vencer
                FROM asos a
                JOIN funcionarios f ON a.id_funcionario = f.id
                JOIN empresas e ON f.id_empresa = e.id
                WHERE a.data_vencimento BETWEEN CURDATE() 
                AND DATE_ADD(CURDATE(), INTERVAL :dias DAY)
                ORDER BY a.data_vencimento";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([':dias' => $dias]);
        return $stmt->fetchAll();
    }

    public function gerarRelatorioVencimentos($inicio, $fim) {
        $sql = "SELECT a.*, 
                       f.nome as funcionario_nome,
                       f.cargo as funcionario_cargo,
                       f.matricula as funcionario_matricula,
                       e.razao_social as empresa_nome,
                       d.nome as departamento_nome,
                       DATEDIFF(a.data_vencimento, CURDATE()) as dias_para_vencer
                FROM asos a
                JOIN funcionarios f ON a.id_funcionario = f.id
                JOIN empresas e ON f.id_empresa = e.id
                JOIN departamentos d ON f.id_departamento = d.id
                WHERE a.data_vencimento BETWEEN :inicio AND :fim
                ORDER BY a.data_vencimento, e.razao_social, f.nome";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            ':inicio' => $inicio,
            ':fim' => $fim
        ]);
        return $stmt->fetchAll();
    }
}
?>
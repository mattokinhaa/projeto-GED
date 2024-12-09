<?php
class Validator {
    private $errors = [];

    public function validarAso($data) {
        $this->errors = [];

        // Campos obrigatórios
        $camposObrigatorios = [
            'id_funcionario' => 'Funcionário',
            'tipo_aso' => 'Tipo de ASO',
            'data_emissao' => 'Data de Emissão',
            'data_vencimento' => 'Data de Vencimento',
            'resultado' => 'Resultado',
            'medico_nome' => 'Nome do Médico',
            'medico_crm' => 'CRM do Médico'
        ];

        foreach ($camposObrigatorios as $campo => $label) {
            if (empty($data[$campo])) {
                $this->errors[] = "O campo {$label} é obrigatório";
            }
        }

        // Validar datas
        if (!empty($data['data_emissao'])) {
            if (!$this->validarData($data['data_emissao'])) {
                $this->errors[] = "Data de Emissão inválida";
            }
        }

        if (!empty($data['data_vencimento'])) {
            if (!$this->validarData($data['data_vencimento'])) {
                $this->errors[] = "Data de Vencimento inválida";
            }
            
            // Data de vencimento deve ser maior que data de emissão
            if (!empty($data['data_emissao']) && 
                strtotime($data['data_vencimento']) <= strtotime($data['data_emissao'])) {
                $this->errors[] = "Data de Vencimento deve ser posterior à Data de Emissão";
            }
        }

        // Validar CRM
        if (!empty($data['medico_crm']) && !$this->validarCRM($data['medico_crm'])) {
            $this->errors[] = "CRM inválido";
        }

        return empty($this->errors);
    }

    public function validarExame($data) {
        $this->errors = [];

        // Campos obrigatórios
        $camposObrigatorios = [
            'id_tipo_exame' => 'Tipo de Exame',
            'data_realizacao' => 'Data de Realização',
            'data_vencimento' => 'Data de Vencimento',
            'resultado' => 'Resultado'
        ];

        foreach ($camposObrigatorios as $campo => $label) {
            if (empty($data[$campo])) {
                $this->errors[] = "O campo {$label} é obrigatório";
            }
        }

        // Validar datas
        if (!empty($data['data_realizacao'])) {
            if (!$this->validarData($data['data_realizacao'])) {
                $this->errors[] = "Data de Realização inválida";
            }
        }

        if (!empty($data['data_vencimento'])) {
            if (!$this->validarData($data['data_vencimento'])) {
                $this->errors[] = "Data de Vencimento inválida";
            }
            
            // Data de vencimento deve ser maior que data de realização
            if (!empty($data['data_realizacao']) && 
                strtotime($data['data_vencimento']) <= strtotime($data['data_realizacao'])) {
                $this->errors[] = "Data de Vencimento deve ser posterior à Data de Realização";
            }
        }

        return empty($this->errors);
    }

    public function validarUsuario($data) {
        $this->errors = [];

        // Campos obrigatórios
        $camposObrigatorios = [
            'nome' => 'Nome',
            'email' => 'E-mail',
            'username' => 'Usuário',
            'password' => 'Senha',
            'nivel_acesso' => 'Nível de Acesso'
        ];

        foreach ($camposObrigatorios as $campo => $label) {
            if (empty($data[$campo])) {
                $this->errors[] = "O campo {$label} é obrigatório";
            }
        }

        // Validar e-mail
        if (!empty($data['email']) && !filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
            $this->errors[] = "E-mail inválido";
        }

        // Validar senha
        if (!empty($data['password'])) {
            if (strlen($data['password']) < 8) {
                $this->errors[] = "A senha deve ter no mínimo 8 caracteres";
            }
            if (!preg_match("/[A-Z]/", $data['password'])) {
                $this->errors[] = "A senha deve conter pelo menos uma letra maiúscula";
            }
            if (!preg_match("/[a-z]/", $data['password'])) {
                $this->errors[] = "A senha deve conter pelo menos uma letra minúscula";
            }
            if (!preg_match("/[0-9]/", $data['password'])) {
                $this->errors[] = "A senha deve conter pelo menos um número";
            }
        }

        // Validar nível de acesso
        $niveisPermitidos = ['Admin', 'Gestor', 'Usuario'];
        if (!empty($data['nivel_acesso']) && !in_array($data['nivel_acesso'], $niveisPermitidos)) {
            $this->errors[] = "Nível de acesso inválido";
        }

        return empty($this->errors);
    }

    public function validarUsuarioUpdate($data) {
        $this->errors = [];

        // Campos obrigatórios
        $camposObrigatorios = [
            'nome' => 'Nome',
            'email' => 'E-mail',
            'nivel_acesso' => 'Nível de Acesso'
        ];

        foreach ($camposObrigatorios as $campo => $label) {
            if (empty($data[$campo])) {
                $this->errors[] = "O campo {$label} é obrigatório";
            }
        }

        // Validar e-mail
        if (!empty($data['email']) && !filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
            $this->errors[] = "E-mail inválido";
        }

        // Validar senha se fornecida
        if (!empty($data['password'])) {
            if (strlen($data['password']) < 8) {
                $this->errors[] = "A senha deve ter no mínimo 8 caracteres";
            }
            if (!preg_match("/[A-Z]/", $data['password'])) {
                $this->errors[] = "A senha deve conter pelo menos uma letra maiúscula";
            }
            if (!preg_match("/[a-z]/", $data['password'])) {
                $this->errors[] = "A senha deve conter pelo menos uma letra minúscula";
            }
            if (!preg_match("/[0-9]/", $data['password'])) {
                $this->errors[] = "A senha deve conter pelo menos um número";
            }
        }

        // Validar nível de acesso
        $niveisPermitidos = ['Admin', 'Gestor', 'Usuario'];
        if (!empty($data['nivel_acesso']) && !in_array($data['nivel_acesso'], $niveisPermitidos)) {
            $this->errors[] = "Nível de acesso inválido";
        }

        return empty($this->errors);
    }

    public function validarFuncionario($data) {
        $this->errors = [];

        // Campos obrigatórios
        $camposObrigatorios = [
            'nome' => 'Nome',
            'matricula' => 'Matrícula',
            'cargo' => 'Cargo',
            'id_empresa' => 'Empresa',
            'id_departamento' => 'Departamento',
            'data_admissao' => 'Data de Admissão'
        ];

        foreach ($camposObrigatorios as $campo => $label) {
            if (empty($data[$campo])) {
                $this->errors[] = "O campo {$label} é obrigatório";
            }
        }

        // Validar datas
        if (!empty($data['data_admissao']) && !$this->validarData($data['data_admissao'])) {
            $this->errors[] = "Data de Admissão inválida";
        }

        if (!empty($data['data_demissao'])) {
            if (!$this->validarData($data['data_demissao'])) {
                $this->errors[] = "Data de Demissão inválida";
            }
            if (strtotime($data['data_demissao']) <= strtotime($data['data_admissao'])) {
                $this->errors[] = "Data de Demissão deve ser posterior à Data de Admissão";
            }
        }

        // Validar e-mail se fornecido
        if (!empty($data['email']) && !filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
            $this->errors[] = "E-mail inválido";
        }

        return empty($this->errors);
    }

    public function getErrors() {
        return $this->errors;
    }

    private function validarData($data) {
        if (empty($data)) return false;
        
        $d = DateTime::createFromFormat('Y-m-d', $data);
        return $d && $d->format('Y-m-d') === $data;
    }

    private function validarCRM($crm) {
        // Implementar validação específica de CRM
        // Por enquanto, apenas verifica se não está vazio
        return !empty($crm);
    }
}
?>
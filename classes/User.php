<?php
class User {
    private $db;
    private $validator;

    public function __construct() {
        $this->db = Database::getInstance()->getConnection();
        $this->validator = new Validator();
    }

    public function login($username, $password) {
        try {
            $sql = "SELECT id, nome, username, password_hash, nivel_acesso, status 
                    FROM usuarios 
                    WHERE username = :username";
            
            $stmt = $this->db->prepare($sql);
            $stmt->execute([':username' => $username]);
            $user = $stmt->fetch();

            if ($user && password_verify($password, $user['password_hash'])) {
                if ($user['status'] !== 'Ativo') {
                    throw new Exception("Usuário inativo");
                }

                // Iniciar sessão
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['user_name'] = $user['nome'];
                $_SESSION['user_nivel'] = $user['nivel_acesso'];

                // Registrar log de acesso
                LogSystem::registrar(
                    'usuarios', 
                    'login', 
                    $user['id'], 
                    "Login realizado com sucesso"
                );

                return true;
            }
            return false;

        } catch (Exception $e) {
            error_log($e->getMessage());
            throw $e;
        }
    }

    public function create($data) {
        try {
            if (!$this->validator->validarUsuario($data)) {
                throw new Exception(implode(", ", $this->validator->getErrors()));
            }

            $sql = "INSERT INTO usuarios (
                nome, email, username, password_hash, 
                nivel_acesso, status, created_at
            ) VALUES (
                :nome, :email, :username, :password_hash,
                :nivel_acesso, :status, NOW()
            )";

            $stmt = $this->db->prepare($sql);
            $result = $stmt->execute([
                ':nome' => $data['nome'],
                ':email' => $data['email'],
                ':username' => $data['username'],
                ':password_hash' => password_hash($data['password'], PASSWORD_DEFAULT),
                ':nivel_acesso' => $data['nivel_acesso'],
                ':status' => 'Ativo'
            ]);

            if ($result) {
                $id = $this->db->lastInsertId();
                LogSystem::registrar(
                    'usuarios', 
                    'criar', 
                    $id, 
                    "Usuário criado: {$data['username']}"
                );
                return $id;
            }
            return false;

        } catch (PDOException $e) {
            if ($e->getCode() == '23000') { // Violação de unique
                throw new Exception("Username ou email já existe");
            }
            throw $e;
        }
    }

    public function update($id, $data) {
        try {
            if (!$this->validator->validarUsuarioUpdate($data)) {
                throw new Exception(implode(", ", $this->validator->getErrors()));
            }

            $sql = "UPDATE usuarios SET 
                    nome = :nome,
                    email = :email,
                    nivel_acesso = :nivel_acesso,
                    updated_at = NOW()
                    WHERE id = :id";

            $params = [
                ':nome' => $data['nome'],
                ':email' => $data['email'],
                ':nivel_acesso' => $data['nivel_acesso'],
                ':id' => $id
            ];

            // Se uma nova senha foi fornecida
            if (!empty($data['password'])) {
                $sql = str_replace(
                    'updated_at = NOW()',
                    'password_hash = :password_hash, updated_at = NOW()',
                    $sql
                );
                $params[':password_hash'] = password_hash($data['password'], PASSWORD_DEFAULT);
            }

            $stmt = $this->db->prepare($sql);
            $result = $stmt->execute($params);

            if ($result) {
                LogSystem::registrar(
                    'usuarios', 
                    'atualizar', 
                    $id, 
                    "Usuário atualizado: {$data['email']}"
                );
                return true;
            }
            return false;

        } catch (PDOException $e) {
            if ($e->getCode() == '23000') {
                throw new Exception("Email já está em uso");
            }
            throw $e;
        }
    }

    public function getById($id) {
        $sql = "SELECT id, nome, email, username, nivel_acesso, status, 
                       created_at, updated_at
                FROM usuarios 
                WHERE id = :id";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([':id' => $id]);
        return $stmt->fetch();
    }

    public function getAll($filters = [], $page = 1, $limit = ITEMS_PER_PAGE) {
        $where = ["1=1"];
        $params = [];
        
        if (!empty($filters['nome'])) {
            $where[] = "nome LIKE :nome";
            $params[':nome'] = "%{$filters['nome']}%";
        }
        
        if (!empty($filters['nivel_acesso'])) {
            $where[] = "nivel_acesso = :nivel_acesso";
            $params[':nivel_acesso'] = $filters['nivel_acesso'];
        }
        
        if (!empty($filters['status'])) {
            $where[] = "status = :status";
            $params[':status'] = $filters['status'];
        }

        $offset = ($page - 1) * $limit;
        
        $sql = "SELECT id, nome, email, username, nivel_acesso, status, 
                       created_at, updated_at
                FROM usuarios
                WHERE " . implode(" AND ", $where) . "
                ORDER BY nome
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

    public function changeStatus($id, $status) {
        try {
            $sql = "UPDATE usuarios SET 
                    status = :status,
                    updated_at = NOW()
                    WHERE id = :id";

            $stmt = $this->db->prepare($sql);
            $result = $stmt->execute([
                ':status' => $status,
                ':id' => $id
            ]);

            if ($result) {
                LogSystem::registrar(
                    'usuarios', 
                    'status', 
                    $id, 
                    "Status alterado para: {$status}"
                );
                return true;
            }
            return false;

        } catch (Exception $e) {
            error_log($e->getMessage());
            throw $e;
        }
    }

    public function hasPermission($permission) {
        // Implementar lógica de permissões baseada no nivel_acesso
        $adminPermissions = ['admin', 'gerenciar_usuarios', 'gerenciar_permissoes'];
        $gestorPermissions = ['visualizar_relatorios', 'gerenciar_documentos'];
        $userPermissions = ['visualizar_documentos', 'criar_documentos'];

        switch ($_SESSION['user_nivel']) {
            case 'Admin':
                return true;
            case 'Gestor':
                return in_array($permission, array_merge($gestorPermissions, $userPermissions));
            case 'Usuario':
                return in_array($permission, $userPermissions);
            default:
                return false;
        }
    }

    public function logout() {
        if (isset($_SESSION['user_id'])) {
            LogSystem::registrar(
                'usuarios', 
                'logout', 
                $_SESSION['user_id'], 
                "Logout realizado"
            );
        }
        
        session_destroy();
        return true;
    }
}
?>
<?php
class LogSystem {
    private static $db;
    private static $logFile;

    private static function init() {
        if (!self::$db) {
            self::$db = Database::getInstance()->getConnection();
        }
        if (!self::$logFile) {
            self::$logFile = LOG_PATH . '/system_' . date('Y-m-d') . '.log';
        }
    }

    public static function registrar($modulo, $acao, $id_referencia, $descricao, $dados_adicionais = null) {
        self::init();

        try {
            // Registrar no banco de dados
            $sql = "INSERT INTO logs (
                modulo, acao, id_referencia, id_usuario,
                descricao, dados_adicionais, ip_address,
                user_agent, created_at
            ) VALUES (
                :modulo, :acao, :id_referencia, :id_usuario,
                :descricao, :dados_adicionais, :ip_address,
                :user_agent, NOW()
            )";

            $stmt = self::$db->prepare($sql);
            $stmt->execute([
                ':modulo' => $modulo,
                ':acao' => $acao,
                ':id_referencia' => $id_referencia,
                ':id_usuario' => $_SESSION['user_id'] ?? null,
                ':descricao' => $descricao,
                ':dados_adicionais' => $dados_adicionais ? json_encode($dados_adicionais) : null,
                ':ip_address' => self::getClientIP(),
                ':user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? null
            ]);

            // Registrar no arquivo de log
            self::registrarArquivo($modulo, $acao, $id_referencia, $descricao, $dados_adicionais);

            return true;

        } catch (Exception $e) {
            error_log("Erro ao registrar log: " . $e->getMessage());
            // Tentar pelo menos registrar no arquivo
            self::registrarArquivo($modulo, $acao, $id_referencia, $descricao, $dados_adicionais);
            return false;
        }
    }

    private static function registrarArquivo($modulo, $acao, $id_referencia, $descricao, $dados_adicionais = null) {
        $timestamp = date('Y-m-d H:i:s');
        $usuario = $_SESSION['user_id'] ?? 'Sistema';
        $ip = self::getClientIP();
        
        $logMessage = sprintf(
            "[%s] %s - %s - %s - Ref#%s - %s - IP: %s",
            $timestamp,
            $usuario,
            $modulo,
            $acao,
            $id_referencia,
            $descricao,
            $ip
        );

        if ($dados_adicionais) {
            $logMessage .= " - Dados: " . json_encode($dados_adicionais);
        }

        $logMessage .= PHP_EOL;

        if (!file_put_contents(self::$logFile, $logMessage, FILE_APPEND)) {
            error_log("Erro ao escrever no arquivo de log: " . self::$logFile);
        }
    }

    public static function buscar($filtros = [], $page = 1, $limit = ITEMS_PER_PAGE) {
        self::init();

        try {
            $where = ["1=1"];
            $params = [];
            
            if (!empty($filtros['modulo'])) {
                $where[] = "modulo = :modulo";
                $params[':modulo'] = $filtros['modulo'];
            }
            
            if (!empty($filtros['acao'])) {
                $where[] = "acao = :acao";
                $params[':acao'] = $filtros['acao'];
            }
            
            if (!empty($filtros['id_usuario'])) {
                $where[] = "id_usuario = :id_usuario";
                $params[':id_usuario'] = $filtros['id_usuario'];
            }
            
            if (!empty($filtros['data_inicio'])) {
                $where[] = "created_at >= :data_inicio";
                $params[':data_inicio'] = $filtros['data_inicio'] . ' 00:00:00';
            }
            
            if (!empty($filtros['data_fim'])) {
                $where[] = "created_at <= :data_fim";
                $params[':data_fim'] = $filtros['data_fim'] . ' 23:59:59';
            }

            $offset = ($page - 1) * $limit;
            
            $sql = "SELECT l.*, 
                           u.nome as usuario_nome
                    FROM logs l
                    LEFT JOIN usuarios u ON l.id_usuario = u.id
                    WHERE " . implode(" AND ", $where) . "
                    ORDER BY l.created_at DESC
                    LIMIT :limit OFFSET :offset";

            $stmt = self::$db->prepare($sql);
            
            foreach ($params as $key => $value) {
                $stmt->bindValue($key, $value);
            }
            
            $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
            $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
            
            $stmt->execute();
            return $stmt->fetchAll();

        } catch (Exception $e) {
            error_log("Erro ao buscar logs: " . $e->getMessage());
            return [];
        }
    }

    public static function limparLogs($dias = 90) {
        self::init();

        try {
            // Limpar registros antigos do banco
            $sql = "DELETE FROM logs 
                    WHERE created_at < DATE_SUB(NOW(), INTERVAL :dias DAY)";
            
            $stmt = self::$db->prepare($sql);
            $stmt->execute([':dias' => $dias]);

            // Limpar arquivos de log antigos
            $diretorio = LOG_PATH;
            $arquivos = glob($diretorio . '/system_*.log');
            $dataLimite = strtotime("-{$dias} days");

            foreach ($arquivos as $arquivo) {
                if (filemtime($arquivo) < $dataLimite) {
                    unlink($arquivo);
                }
            }

            return true;

        } catch (Exception $e) {
            error_log("Erro ao limpar logs: " . $e->getMessage());
            return false;
        }
    }

    public static function exportar($filtros = []) {
        self::init();

        try {
            $logs = self::buscar($filtros, 1, PHP_INT_MAX);
            
            if (empty($logs)) {
                throw new Exception("Nenhum log encontrado para exportar");
            }

            $filename = 'logs_' . date('Y-m-d_His') . '.csv';
            $output = fopen('php://temp', 'w');

            // Cabeçalho do CSV
            fputcsv($output, [
                'Data/Hora',
                'Usuário',
                'Módulo',
                'Ação',
                'ID Referência',
                'Descrição',
                'IP',
                'User Agent'
            ]);

            // Dados
            foreach ($logs as $log) {
                fputcsv($output, [
                    $log['created_at'],
                    $log['usuario_nome'],
                    $log['modulo'],
                    $log['acao'],
                    $log['id_referencia'],
                    $log['descricao'],
                    $log['ip_address'],
                    $log['user_agent']
                ]);
            }

            rewind($output);
            $csv = stream_get_contents($output);
            fclose($output);

            return [
                'filename' => $filename,
                'content' => $csv,
                'type' => 'text/csv'
            ];

        } catch (Exception $e) {
            error_log("Erro ao exportar logs: " . $e->getMessage());
            throw $e;
        }
    }

    private static function getClientIP() {
        $ipaddress = '';
        if (isset($_SERVER['HTTP_CLIENT_IP']))
            $ipaddress = $_SERVER['HTTP_CLIENT_IP'];
        else if(isset($_SERVER['HTTP_X_FORWARDED_FOR']))
            $ipaddress = $_SERVER['HTTP_X_FORWARDED_FOR'];
        else if(isset($_SERVER['HTTP_X_FORWARDED']))
            $ipaddress = $_SERVER['HTTP_X_FORWARDED'];
        else if(isset($_SERVER['HTTP_FORWARDED_FOR']))
            $ipaddress = $_SERVER['HTTP_FORWARDED_FOR'];
        else if(isset($_SERVER['HTTP_FORWARDED']))
            $ipaddress = $_SERVER['HTTP_FORWARDED'];
        else if(isset($_SERVER['REMOTE_ADDR']))
            $ipaddress = $_SERVER['REMOTE_ADDR'];
        else
            $ipaddress = 'UNKNOWN';
        return $ipaddress;
    }
}
?>
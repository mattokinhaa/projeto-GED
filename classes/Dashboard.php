<?php
class Dashboard {
    private $db;

    public function __construct() {
        $this->db = Database::getInstance()->getConnection();
    }

    public function getIndicadores() {
        return [
            'asos' => $this->getIndicadoresAso(),
            'exames' => $this->getIndicadoresExames(),
            'funcionarios' => $this->getIndicadoresFuncionarios(),
            'alertas' => $this->getAlertasRecentes(),
            'vencimentos' => $this->getProximosVencimentos(),
            'conformidade' => $this->getIndicadoresConformidade()
        ];
    }

    private function getIndicadoresAso() {
        try {
            $sql = "SELECT 
                        COUNT(*) as total,
                        SUM(CASE WHEN data_vencimento < CURDATE() THEN 1 ELSE 0 END) as vencidos,
                        SUM(CASE 
                            WHEN data_vencimento >= CURDATE() 
                            AND data_vencimento <= DATE_ADD(CURDATE(), INTERVAL 30 DAY) 
                            THEN 1 ELSE 0 END) as vencendo,
                        SUM(CASE WHEN data_vencimento > DATE_ADD(CURDATE(), INTERVAL 30 DAY) 
                            THEN 1 ELSE 0 END) as vigentes
                    FROM asos
                    WHERE status = 'Ativo'";

            $stmt = $this->db->query($sql);
            return $stmt->fetch();

        } catch (Exception $e) {
            error_log("Erro ao buscar indicadores ASO: " . $e->getMessage());
            return [
                'total' => 0,
                'vencidos' => 0,
                'vencendo' => 0,
                'vigentes' => 0
            ];
        }
    }

    private function getIndicadoresExames() {
        try {
            $sql = "SELECT 
                        COUNT(*) as total,
                        SUM(CASE WHEN data_vencimento < CURDATE() THEN 1 ELSE 0 END) as vencidos,
                        SUM(CASE 
                            WHEN data_vencimento >= CURDATE() 
                            AND data_vencimento <= DATE_ADD(CURDATE(), INTERVAL 30 DAY) 
                            THEN 1 ELSE 0 END) as vencendo,
                        COUNT(DISTINCT id_tipo_exame) as tipos_distintos
                    FROM exames_realizados
                    WHERE status = 'Ativo'";

            $stmt = $this->db->query($sql);
            return $stmt->fetch();

        } catch (Exception $e) {
            error_log("Erro ao buscar indicadores Exames: " . $e->getMessage());
            return [
                'total' => 0,
                'vencidos' => 0,
                'vencendo' => 0,
                'tipos_distintos' => 0
            ];
        }
    }

    private function getIndicadoresFuncionarios() {
        try {
            $sql = "SELECT 
                        COUNT(*) as total,
                        SUM(CASE WHEN status = 'Ativo' THEN 1 ELSE 0 END) as ativos,
                        COUNT(DISTINCT id_empresa) as empresas,
                        COUNT(DISTINCT id_departamento) as departamentos
                    FROM funcionarios";

            $stmt = $this->db->query($sql);
            return $stmt->fetch();

        } catch (Exception $e) {
            error_log("Erro ao buscar indicadores Funcionários: " . $e->getMessage());
            return [
                'total' => 0,
                'ativos' => 0,
                'empresas' => 0,
                'departamentos' => 0
            ];
        }
    }

    private function getAlertasRecentes($limite = 5) {
        try {
            $sql = "SELECT a.*, 
                       CASE 
                           WHEN a.tipo = 'ASO' THEN aso.tipo_aso
                           WHEN a.tipo = 'Exame' THEN te.nome
                           ELSE tr.descricao
                       END as documento,
                       f.nome as funcionario,
                       e.razao_social as empresa,
                       DATEDIFF(a.data_vencimento, CURDATE()) as dias_restantes
                    FROM alertas a
                    LEFT JOIN asos aso ON a.tipo = 'ASO' AND a.id_referencia = aso.id
                    LEFT JOIN exames_realizados er ON a.tipo = 'Exame' AND a.id_referencia = er.id
                    LEFT JOIN tipos_exames te ON er.id_tipo_exame = te.id
                    LEFT JOIN treinamentos_nr tr ON a.tipo = 'Treinamento' AND a.id_referencia = tr.id
                    LEFT JOIN funcionarios f ON aso.id_funcionario = f.id 
                        OR er.id_funcionario = f.id 
                        OR tr.id_funcionario = f.id
                    LEFT JOIN empresas e ON f.id_empresa = e.id
                    WHERE a.status = 'Pendente'
                    ORDER BY a.data_vencimento ASC
                    LIMIT :limite";

            $stmt = $this->db->prepare($sql);
            $stmt->bindValue(':limite', $limite, PDO::PARAM_INT);
            $stmt->execute();
            return $stmt->fetchAll();

        } catch (Exception $e) {
            error_log("Erro ao buscar alertas recentes: " . $e->getMessage());
            return [];
        }
    }

    private function getProximosVencimentos($limite = 5) {
        try {
            $sql = "SELECT 'ASO' as tipo,
                           aso.id,
                           aso.tipo_aso as documento,
                           f.nome as funcionario,
                           e.razao_social as empresa,
                           aso.data_vencimento,
                           DATEDIFF(aso.data_vencimento, CURDATE()) as dias_restantes
                    FROM asos aso
                    JOIN funcionarios f ON aso.id_funcionario = f.id
                    JOIN empresas e ON f.id_empresa = e.id
                    WHERE aso.data_vencimento >= CURDATE()
                    UNION ALL
                    SELECT 'Exame' as tipo,
                           er.id,
                           te.nome as documento,
                           f.nome as funcionario,
                           e.razao_social as empresa,
                           er.data_vencimento,
                           DATEDIFF(er.data_vencimento, CURDATE()) as dias_restantes
                    FROM exames_realizados er
                    JOIN tipos_exames te ON er.id_tipo_exame = te.id
                    JOIN asos aso ON er.id_aso = aso.id
                    JOIN funcionarios f ON aso.id_funcionario = f.id
                    JOIN empresas e ON f.id_empresa = e.id
                    WHERE er.data_vencimento >= CURDATE()
                    ORDER BY data_vencimento ASC
                    LIMIT :limite";

            $stmt = $this->db->prepare($sql);
            $stmt->bindValue(':limite', $limite, PDO::PARAM_INT);
            $stmt->execute();
            return $stmt->fetchAll();

        } catch (Exception $e) {
            error_log("Erro ao buscar próximos vencimentos: " . $e->getMessage());
            return [];
        }
    }

    private function getIndicadoresConformidade() {
        try {
            $sql = "SELECT 
                        e.razao_social as empresa,
                        COUNT(f.id) as total_funcionarios,
                        SUM(CASE 
                            WHEN aso.data_vencimento >= CURDATE() THEN 1 
                            ELSE 0 
                        END) as asos_conformes,
                        COUNT(DISTINCT CASE 
                            WHEN er.data_vencimento >= CURDATE() THEN er.id_tipo_exame 
                            ELSE NULL 
                        END) as exames_conformes
                    FROM empresas e
                    JOIN funcionarios f ON f.id_empresa = e.id
                    LEFT JOIN asos aso ON f.id = aso.id_funcionario
                    LEFT JOIN exames_realizados er ON aso.id = er.id_aso
                    WHERE f.status = 'Ativo'
                    GROUP BY e.id
                    ORDER BY e.razao_social";

            $stmt = $this->db->query($sql);
            return $stmt->fetchAll();

        } catch (Exception $e) {
            error_log("Erro ao buscar indicadores de conformidade: " . $e->getMessage());
            return [];
        }
    }

    public function getDadosGrafico($tipo, $periodo = 'mes') {
        try {
            switch ($tipo) {
                case 'vencimentos':
                    return $this->getGraficoVencimentos($periodo);
                case 'conformidade':
                    return $this->getGraficoConformidade();
                case 'tipos_aso':
                    return $this->getGraficoTiposAso();
                case 'tipos_exame':
                    return $this->getGraficoTiposExame();
                default:
                    throw new Exception("Tipo de gráfico não suportado");
            }
        } catch (Exception $e) {
            error_log("Erro ao buscar dados do gráfico: " . $e->getMessage());
            return [
                'labels' => [],
                'datasets' => []
            ];
        }
    }

    private function getGraficoVencimentos($periodo) {
        $sql = "SELECT 
                    DATE_FORMAT(data_vencimento, :format) as periodo,
                    COUNT(*) as total,
                    SUM(CASE WHEN data_vencimento < CURDATE() THEN 1 ELSE 0 END) as vencidos
                FROM asos
                WHERE data_vencimento BETWEEN 
                    DATE_SUB(CURDATE(), INTERVAL 6 MONTH) AND
                    DATE_ADD(CURDATE(), INTERVAL 6 MONTH)
                GROUP BY periodo
                ORDER BY periodo";

        $format = $periodo == 'mes' ? '%Y-%m' : '%Y-%m-%d';
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([':format' => $format]);
        $dados = $stmt->fetchAll();

        return [
            'labels' => array_column($dados, 'periodo'),
            'datasets' => [
                [
                    'label' => 'Total',
                    'data' => array_column($dados, 'total')
                ],
                [
                    'label' => 'Vencidos',
                    'data' => array_column($dados, 'vencidos')
                ]
            ]
        ];
    }

    private function getGraficoConformidade() {
        $sql = "SELECT 
                    e.razao_social,
                    COUNT(f.id) as total_funcionarios,
                    SUM(CASE WHEN aso.data_vencimento >= CURDATE() THEN 1 ELSE 0 END) as conformes
                FROM empresas e
                JOIN funcionarios f ON f.id_empresa = e.id
                LEFT JOIN asos aso ON f.id = aso.id_funcionario
                WHERE f.status = 'Ativo'
                GROUP BY e.id";

        $stmt = $this->db->query($sql);
        $dados = $stmt->fetchAll();

        return [
            'labels' => array_column($dados, 'razao_social'),
            'datasets' => [[
                'label' => 'Conformidade (%)',
                'data' => array_map(function($row) {
                    return $row['total_funcionarios'] > 0 
                        ? round(($row['conformes'] / $row['total_funcionarios']) * 100, 2)
                        : 0;
                }, $dados)
            ]]
        ];
    }

    private function getGraficoTiposAso() {
        $sql = "SELECT 
                    tipo_aso,
                    COUNT(*) as total
                FROM asos
                WHERE status = 'Ativo'
                GROUP BY tipo_aso";

        $stmt = $this->db->query($sql);
        $dados = $stmt->fetchAll();

        return [
            'labels' => array_column($dados, 'tipo_aso'),
            'data' => array_column($dados, 'total')
        ];
    }

    private function getGraficoTiposExame() {
        $sql = "SELECT 
                    te.nome,
                    COUNT(*) as total
                FROM exames_realizados er
                JOIN tipos_exames te ON er.id_tipo_exame = te.id
                WHERE er.status = 'Ativo'
                GROUP BY te.id";

        $stmt = $this->db->query($sql);
        $dados = $stmt->fetchAll();

        return [
            'labels' => array_column($dados, 'nome'),
            'data' => array_column($dados, 'total')
        ];
    }
}
?>
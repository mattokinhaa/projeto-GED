<?php
class Alert {
    private $db;
    private $mailer;

    public function __construct() {
        $this->db = Database::getInstance()->getConnection();
        $this->configurarMailer();
    }

    private function configurarMailer() {
        $this->mailer = new PHPMailer(true);
        
        // Configurações do servidor SMTP
        $this->mailer->isSMTP();
        $this->mailer->Host = SMTP_HOST;
        $this->mailer->SMTPAuth = true;
        $this->mailer->Username = SMTP_USER;
        $this->mailer->Password = SMTP_PASS;
        $this->mailer->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $this->mailer->Port = SMTP_PORT;
        $this->mailer->CharSet = 'UTF-8';
        
        // Configurações do remetente
        $this->mailer->setFrom(MAIL_FROM, MAIL_FROM_NAME);
    }

    public function criarAlertaVencimento($tipo, $id_referencia, $data_vencimento) {
        try {
            // Calcular data do alerta (30 dias antes do vencimento)
            $data_alerta = date('Y-m-d', strtotime($data_vencimento . ' -' . DIAS_ALERTA_VENCIMENTO . ' days'));

            $sql = "INSERT INTO alertas (
                tipo, id_referencia, data_vencimento, data_alerta,
                status, created_at
            ) VALUES (
                :tipo, :id_referencia, :data_vencimento, :data_alerta,
                'Pendente', NOW()
            )";

            $stmt = $this->db->prepare($sql);
            $stmt->execute([
                ':tipo' => $tipo,
                ':id_referencia' => $id_referencia,
                ':data_vencimento' => $data_vencimento,
                ':data_alerta' => $data_alerta
            ]);

            return $this->db->lastInsertId();

        } catch (Exception $e) {
            error_log("Erro ao criar alerta: " . $e->getMessage());
            throw $e;
        }
    }

    public function atualizarAlertaVencimento($tipo, $id_referencia, $data_vencimento) {
        try {
            $data_alerta = date('Y-m-d', strtotime($data_vencimento . ' -' . DIAS_ALERTA_VENCIMENTO . ' days'));

            $sql = "UPDATE alertas SET 
                    data_vencimento = :data_vencimento,
                    data_alerta = :data_alerta,
                    status = 'Pendente',
                    updated_at = NOW()
                    WHERE tipo = :tipo 
                    AND id_referencia = :id_referencia";

            $stmt = $this->db->prepare($sql);
            return $stmt->execute([
                ':data_vencimento' => $data_vencimento,
                ':data_alerta' => $data_alerta,
                ':tipo' => $tipo,
                ':id_referencia' => $id_referencia
            ]);

        } catch (Exception $e) {
            error_log("Erro ao atualizar alerta: " . $e->getMessage());
            throw $e;
        }
    }

    public function verificarAlertas() {
        try {
            $sql = "SELECT a.*, 
                       CASE 
                           WHEN a.tipo = 'ASO' THEN f1.email
                           WHEN a.tipo = 'Exame' THEN f2.email
                           ELSE f3.email
                       END as email_funcionario,
                       CASE 
                           WHEN a.tipo = 'ASO' THEN f1.nome
                           WHEN a.tipo = 'Exame' THEN f2.nome
                           ELSE f3.nome
                       END as nome_funcionario,
                       CASE 
                           WHEN a.tipo = 'ASO' THEN g1.email
                           WHEN a.tipo = 'Exame' THEN g2.email
                           ELSE g3.email
                       END as email_gestor
                FROM alertas a
                LEFT JOIN asos aso ON a.tipo = 'ASO' AND a.id_referencia = aso.id
                LEFT JOIN funcionarios f1 ON aso.id_funcionario = f1.id
                LEFT JOIN usuarios g1 ON f1.id_gestor = g1.id
                LEFT JOIN exames_realizados er ON a.tipo = 'Exame' AND a.id_referencia = er.id
                LEFT JOIN asos aso2 ON er.id_aso = aso2.id
                LEFT JOIN funcionarios f2 ON aso2.id_funcionario = f2.id
                LEFT JOIN usuarios g2 ON f2.id_gestor = g2.id
                LEFT JOIN treinamentos_nr tr ON a.tipo = 'Treinamento' AND a.id_referencia = tr.id
                LEFT JOIN funcionarios f3 ON tr.id_funcionario = f3.id
                LEFT JOIN usuarios g3 ON f3.id_gestor = g3.id
                WHERE a.status = 'Pendente'
                AND a.data_alerta <= CURDATE()";

            $stmt = $this->db->prepare($sql);
            $stmt->execute();
            $alertas = $stmt->fetchAll();

            foreach ($alertas as $alerta) {
                $this->enviarAlertaEmail($alerta);
                $this->marcarAlertaEnviado($alerta['id']);
            }

            return true;

        } catch (Exception $e) {
            error_log("Erro ao verificar alertas: " . $e->getMessage());
            throw $e;
        }
    }

    private function enviarAlertaEmail($alerta) {
        try {
            // Limpar quaisquer destinatários anteriores
            $this->mailer->clearAddresses();
            
            // Adicionar destinatários
            if ($alerta['email_funcionario']) {
                $this->mailer->addAddress($alerta['email_funcionario'], $alerta['nome_funcionario']);
            }
            if ($alerta['email_gestor']) {
                $this->mailer->addCC($alerta['email_gestor']);
            }

            // Configurar e-mail
            $this->mailer->Subject = $this->getAssuntoAlerta($alerta);
            $this->mailer->Body = $this->getCorpoAlerta($alerta);
            $this->mailer->AltBody = strip_tags($this->mailer->Body);

            // Enviar e-mail
            return $this->mailer->send();

        } catch (Exception $e) {
            error_log("Erro ao enviar e-mail de alerta: " . $e->getMessage());
            return false;
        }
    }

    private function getAssuntoAlerta($alerta) {
        $dias = floor((strtotime($alerta['data_vencimento']) - time()) / (60 * 60 * 24));
        return "Alerta de Vencimento - {$alerta['tipo']} - {$dias} dias restantes";
    }

    private function getCorpoAlerta($alerta) {
        // Carregar template
        $template = file_get_contents(__DIR__ . '/../templates/email_alerta.html');
        
        // Substituir placeholders
        $template = str_replace('{NOME}', $alerta['nome_funcionario'], $template);
        $template = str_replace('{TIPO_DOC}', $alerta['tipo'], $template);
        $template = str_replace('{DATA_VENCIMENTO}', 
                              date('d/m/Y', strtotime($alerta['data_vencimento'])), 
                              $template);
        
        return $template;
    }

    private function marcarAlertaEnviado($id) {
        $sql = "UPDATE alertas SET 
                status = 'Enviado',
                data_envio = NOW(),
                updated_at = NOW()
                WHERE id = :id";

        $stmt = $this->db->prepare($sql);
        return $stmt->execute([':id' => $id]);
    }

    public function getAlertasPendentes() {
        $sql = "SELECT a.*, 
                       CASE 
                           WHEN a.tipo = 'ASO' THEN aso.tipo_aso
                           WHEN a.tipo = 'Exame' THEN te.nome
                           ELSE tr.descricao
                       END as documento,
                       f.nome as funcionario,
                       e.razao_social as empresa
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
                ORDER BY a.data_alerta, a.data_vencimento";

        $stmt = $this->db->prepare($sql);
        $stmt->execute();
        return $stmt->fetchAll();
    }
}
?>
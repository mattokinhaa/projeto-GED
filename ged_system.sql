-- Criar banco de dados
CREATE DATABASE IF NOT EXISTS ged_system
CHARACTER SET utf8mb4
COLLATE utf8mb4_unicode_ci;

USE ged_system;

-- Tabela de Funcionários
CREATE TABLE funcionarios (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nome VARCHAR(255) NOT NULL,
    cpf VARCHAR(14) UNIQUE NOT NULL,
    data_nascimento DATE,
    email VARCHAR(255),
    telefone VARCHAR(20),
    cargo VARCHAR(100),
    departamento VARCHAR(100),
    data_admissao DATE,
    status ENUM('Ativo', 'Inativo') DEFAULT 'Ativo',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_nome (nome),
    INDEX idx_cpf (cpf),
    INDEX idx_departamento (departamento)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabela de Empresas
CREATE TABLE empresas (
    id INT AUTO_INCREMENT PRIMARY KEY,
    razao_social VARCHAR(255) NOT NULL,
    nome_fantasia VARCHAR(255),
    cnpj VARCHAR(18) UNIQUE NOT NULL,
    inscricao_estadual VARCHAR(20),
    endereco VARCHAR(255),
    numero VARCHAR(10),
    complemento VARCHAR(100),
    bairro VARCHAR(100),
    cidade VARCHAR(100),
    estado CHAR(2),
    cep VARCHAR(9),
    telefone VARCHAR(20),
    email VARCHAR(255),
    status ENUM('Ativa', 'Inativa') DEFAULT 'Ativa',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_razao_social (razao_social),
    INDEX idx_cnpj (cnpj)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabela de Usuários
CREATE TABLE usuarios (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nome VARCHAR(255) NOT NULL,
    email VARCHAR(255) UNIQUE NOT NULL,
    username VARCHAR(50) UNIQUE NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    nivel_acesso ENUM('Admin', 'Gestor', 'Usuario') DEFAULT 'Usuario',
    ultimo_acesso DATETIME,
    status ENUM('Ativo', 'Inativo') DEFAULT 'Ativo',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_email (email),
    INDEX idx_username (username)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabela de Documentos
CREATE TABLE documentos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    titulo VARCHAR(255) NOT NULL,
    descricao TEXT,
    tipo_documento ENUM('Contrato', 'Nota Fiscal', 'Relatório', 'RG', 'CPF', 'Certidão', 'Outros') NOT NULL,
    numero_documento VARCHAR(50),
    data_documento DATE,
    data_vencimento DATE,
    id_funcionario INT,
    id_empresa INT,
    caminho_arquivo VARCHAR(255) NOT NULL,
    tamanho_arquivo INT,
    tipo_arquivo VARCHAR(50),
    status ENUM('Ativo', 'Arquivado', 'Excluído') DEFAULT 'Ativo',
    created_by INT,
    updated_by INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (id_funcionario) REFERENCES funcionarios(id),
    FOREIGN KEY (id_empresa) REFERENCES empresas(id),
    FOREIGN KEY (created_by) REFERENCES usuarios(id),
    FOREIGN KEY (updated_by) REFERENCES usuarios(id),
    INDEX idx_titulo (titulo),
    INDEX idx_tipo_documento (tipo_documento),
    INDEX idx_data_documento (data_documento),
    INDEX idx_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabela de Categorias de Documentos
CREATE TABLE categorias_documentos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nome VARCHAR(100) NOT NULL,
    descricao TEXT,
    status ENUM('Ativa', 'Inativa') DEFAULT 'Ativa',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_nome (nome)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabela de Relacionamento entre Documentos e Categorias
CREATE TABLE documento_categoria (
    id_documento INT,
    id_categoria INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id_documento, id_categoria),
    FOREIGN KEY (id_documento) REFERENCES documentos(id),
    FOREIGN KEY (id_categoria) REFERENCES categorias_documentos(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabela de Log de Ações
CREATE TABLE log_acoes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    id_usuario INT,
    acao VARCHAR(50) NOT NULL,
    tabela_afetada VARCHAR(50) NOT NULL,
    id_registro INT,
    dados_anteriores TEXT,
    dados_novos TEXT,
    ip_address VARCHAR(45),
    user_agent VARCHAR(255),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (id_usuario) REFERENCES usuarios(id),
    INDEX idx_acao (acao),
    INDEX idx_created_at (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabela de Permissões
CREATE TABLE permissoes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nome VARCHAR(100) NOT NULL,
    descricao TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_nome (nome)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabela de Relacionamento entre Usuários e Permissões
CREATE TABLE usuario_permissao (
    id_usuario INT,
    id_permissao INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id_usuario, id_permissao),
    FOREIGN KEY (id_usuario) REFERENCES usuarios(id),
    FOREIGN KEY (id_permissao) REFERENCES permissoes(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Inserir dados iniciais para permissões
INSERT INTO permissoes (nome, descricao) VALUES
('visualizar_documentos', 'Permite visualizar documentos'),
('criar_documentos', 'Permite criar novos documentos'),
('editar_documentos', 'Permite editar documentos existentes'),
('excluir_documentos', 'Permite excluir documentos'),
('gerenciar_usuarios', 'Permite gerenciar usuários do sistema'),
('gerenciar_permissoes', 'Permite gerenciar permissões do sistema'),
('visualizar_logs', 'Permite visualizar logs do sistema');

-- Inserir usuário administrador inicial
INSERT INTO usuarios (nome, email, username, password_hash, nivel_acesso) VALUES
('Administrador', 'admin@sistema.com', 'admin', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Admin');

-- Inserir categorias iniciais de documentos
INSERT INTO categorias_documentos (nome, descricao) VALUES
('Documentos Pessoais', 'Documentos de identificação e pessoais'),
('Documentos Financeiros', 'Documentos relacionados a finanças'),
('Contratos', 'Contratos e documentos legais'),
('Relatórios', 'Relatórios diversos');
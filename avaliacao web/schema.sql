-- Schema do Banco de Dados - Sistema de Avaliação Acadêmica
-- Execute este script no MySQL para criar as tabelas

CREATE DATABASE IF NOT EXISTS avaliacao_web CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE avaliacao_web;

-- Tabela de usuários (alunos e administradores)
CREATE TABLE IF NOT EXISTS usuarios (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nome VARCHAR(150) NOT NULL,
    usuario VARCHAR(60) NULL UNIQUE,
    email VARCHAR(150) NOT NULL UNIQUE,
    senha_hash VARCHAR(255) NOT NULL,
    tipo ENUM('aluno', 'admin') NOT NULL DEFAULT 'aluno',
    ativo TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabela de questões
CREATE TABLE IF NOT EXISTS questoes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    enunciado TEXT NOT NULL,
    tipo ENUM('multipla_escolha', 'verdadeiro_falso', 'dissertativa') NOT NULL DEFAULT 'multipla_escolha',
    categoria VARCHAR(100) NOT NULL,
    explicacao TEXT NULL,
    ativa TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabela de alternativas
CREATE TABLE IF NOT EXISTS alternativas (
    id INT AUTO_INCREMENT PRIMARY KEY,
    questao_id INT NOT NULL,
    texto TEXT NOT NULL,
    correta TINYINT(1) NOT NULL DEFAULT 0,
    ordem INT NOT NULL DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (questao_id) REFERENCES questoes(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabela de avaliações (sessões de prova)
CREATE TABLE IF NOT EXISTS avaliacoes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    usuario_id INT NOT NULL,
    tempo_limite_segundos INT NOT NULL DEFAULT 3600,
    inicio DATETIME NOT NULL,
    fim DATETIME NULL,
    nota DECIMAL(5,2) NULL,
    acertos INT NULL DEFAULT 0,
    erros INT NULL DEFAULT 0,
    total_questoes INT NOT NULL DEFAULT 0,
    status ENUM('em_andamento', 'finalizada', 'tempo_esgotado', 'cancelada', 'fraude') NOT NULL DEFAULT 'em_andamento',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE,
    INDEX idx_usuario_status (usuario_id, status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabela de respostas do aluno
CREATE TABLE IF NOT EXISTS respostas (
    id INT AUTO_INCREMENT PRIMARY KEY,
    avaliacao_id INT NOT NULL,
    questao_id INT NOT NULL,
    alternativa_id INT NULL,
    resposta_texto TEXT NULL,
    correta TINYINT(1) NULL,
    tempo_resposta_segundos INT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (avaliacao_id) REFERENCES avaliacoes(id) ON DELETE CASCADE,
    FOREIGN KEY (questao_id) REFERENCES questoes(id) ON DELETE CASCADE,
    FOREIGN KEY (alternativa_id) REFERENCES alternativas(id) ON DELETE SET NULL,
    UNIQUE KEY unique_avaliacao_questao (avaliacao_id, questao_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabela de eventos suspeitos (anti-cola)
CREATE TABLE IF NOT EXISTS eventos_suspeitos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    avaliacao_id INT NOT NULL,
    tipo_evento VARCHAR(50) NOT NULL,
    descricao TEXT NULL,
    timestamp DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (avaliacao_id) REFERENCES avaliacoes(id) ON DELETE CASCADE,
    INDEX idx_avaliacao_timestamp (avaliacao_id, timestamp)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabela de questões de cada avaliação (a prova é sorteada e gravada no início)
CREATE TABLE IF NOT EXISTS avaliacao_questoes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    avaliacao_id INT NOT NULL,
    questao_id INT NOT NULL,
    ordem INT NOT NULL DEFAULT 0,
    UNIQUE KEY unique_avaliacao_questao (avaliacao_id, questao_id),
    FOREIGN KEY (avaliacao_id) REFERENCES avaliacoes(id) ON DELETE CASCADE,
    FOREIGN KEY (questao_id) REFERENCES questoes(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabela de feedbacks (avaliação do site com estrelas)
CREATE TABLE IF NOT EXISTS feedbacks (
    id INT AUTO_INCREMENT PRIMARY KEY,
    usuario_id INT NULL,
    estrelas TINYINT NOT NULL,
    comentario TEXT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Inserir usuário administrador padrão (senha: admin123)
INSERT INTO usuarios (nome, usuario, email, senha_hash, tipo) VALUES
('Administrador', 'admin', 'admin@sistema.com', '$2y$10$UCoLePleRtcq7JJ5akVpX.4Nog0OUJPtBKRlidL.RsGfRWSKexTIq', 'admin')
ON DUPLICATE KEY UPDATE nome = VALUES(nome), usuario = VALUES(usuario);

-- Inserir alguns alunos de teste (senha: aluno123)
INSERT INTO usuarios (nome, usuario, email, senha_hash, tipo) VALUES
('João Silva', 'joao', 'joao@aluno.com', '$2y$10$gKEleGtf.cnojdtgc1UgrugJwyupzZl.X2A2c8tWyXCW.PjgHn3YK', 'aluno'),
('Maria Santos', 'maria', 'maria@aluno.com', '$2y$10$gKEleGtf.cnojdtgc1UgrugJwyupzZl.X2A2c8tWyXCW.PjgHn3YK', 'aluno'),
('Pedro Oliveira', 'pedro', 'pedro@aluno.com', '$2y$10$gKEleGtf.cnojdtgc1UgrugJwyupzZl.X2A2c8tWyXCW.PjgHn3YK', 'aluno')
ON DUPLICATE KEY UPDATE nome = VALUES(nome), usuario = VALUES(usuario);

-- Inserir questões de exemplo
INSERT INTO questoes (enunciado, tipo, categoria, explicacao) VALUES
('Qual a função PHP usada para conectar ao MySQL usando PDO?', 'multipla_escolha', 'PHP/MySQL', 'PDO é a forma recomendada para conexão com banco de dados em PHP moderno.'),
('O que significa CRUD?', 'multipla_escolha', 'SQL', 'Create, Read, Update, Delete - as quatro operações básicas de banco de dados.'),
('A função password_hash() do PHP é segura para armazenar senhas?', 'verdadeiro_falso', 'Segurança', 'Sim, password_hash() usa bcrypt por padrão e é a forma recomendada no PHP.'),
('Qual método HTTP envia dados no corpo da requisição?', 'multipla_escolha', 'Formulários PHP', 'POST envia dados no corpo, GET na URL.'),
('O que é SQL Injection?', 'multipla_escolha', 'Segurança', 'Injeção de código SQL malicioso via input do usuário. Prevenido com prepared statements.'),
('Sessões em PHP são armazenadas no servidor por padrão?', 'verdadeiro_falso', 'Sessões', 'Sim, $_SESSION armazena dados no servidor, apenas o ID da sessão vai para o cliente.'),
('Qual comando SQL cria uma tabela?', 'multipla_escolha', 'SQL', 'CREATE TABLE é o comando DDL para criar tabelas.'),
('O que faz a cláusula WHERE no SQL?', 'multipla_escolha', 'SQL', 'Filtra registros baseado em condições.'),
('Prepared statements previnem SQL Injection?', 'verdadeiro_falso', 'Segurança', 'Sim, separam código SQL dos dados, prevenindo injeção.'),
('Qual superglobal PHP armazena dados de formulário POST?', 'multipla_escolha', 'Formulários PHP', '$_POST contém dados enviados via método POST.');

-- Alternativas para as questões
-- Questão 1: PDO connection
INSERT INTO alternativas (questao_id, texto, correta, ordem) VALUES
(1, 'mysqli_connect()', 0, 1),
(1, 'new PDO()', 1, 2),
(1, 'mysql_connect()', 0, 3),
(1, 'db_connect()', 0, 4);

-- Questão 2: CRUD
INSERT INTO alternativas (questao_id, texto, correta, ordem) VALUES
(2, 'Create, Read, Update, Delete', 1, 1),
(2, 'Connect, Read, Update, Disconnect', 0, 2),
(2, 'Create, Remove, Update, Delete', 0, 3),
(2, 'Connect, Read, Upgrade, Delete', 0, 4);

-- Questão 3: password_hash (V/F)
INSERT INTO alternativas (questao_id, texto, correta, ordem) VALUES
(3, 'Verdadeiro', 1, 1),
(3, 'Falso', 0, 2);

-- Questão 4: HTTP POST
INSERT INTO alternativas (questao_id, texto, correta, ordem) VALUES
(4, 'GET', 0, 1),
(4, 'POST', 1, 2),
(4, 'PUT', 0, 3),
(4, 'DELETE', 0, 4);

-- Questão 5: SQL Injection
INSERT INTO alternativas (questao_id, texto, correta, ordem) VALUES
(5, 'Injeção de código SQL malicioso via input do usuário', 1, 1),
(5, 'Erro de sintaxe no SQL', 0, 2),
(5, 'Backup do banco de dados', 0, 3),
(5, 'Otimização de consultas', 0, 4);

-- Questão 6: Sessões (V/F)
INSERT INTO alternativas (questao_id, texto, correta, ordem) VALUES
(6, 'Verdadeiro', 1, 1),
(6, 'Falso', 0, 2);

-- Questão 7: CREATE TABLE
INSERT INTO alternativas (questao_id, texto, correta, ordem) VALUES
(7, 'CREATE TABLE', 1, 1),
(7, 'MAKE TABLE', 0, 2),
(7, 'NEW TABLE', 0, 3),
(7, 'ADD TABLE', 0, 4);

-- Questão 8: WHERE
INSERT INTO alternativas (questao_id, texto, correta, ordem) VALUES
(8, 'Filtra registros baseado em condições', 1, 1),
(8, 'Ordena resultados', 0, 2),
(8, 'Agrupa resultados', 0, 3),
(8, 'Limita resultados', 0, 4);

-- Questão 9: Prepared statements (V/F)
INSERT INTO alternativas (questao_id, texto, correta, ordem) VALUES
(9, 'Verdadeiro', 1, 1),
(9, 'Falso', 0, 2);

-- Questão 10: $_POST
INSERT INTO alternativas (questao_id, texto, correta, ordem) VALUES
(10, '$_GET', 0, 1),
(10, '$_POST', 1, 2),
(10, '$_REQUEST', 0, 3),
(10, '$_SERVER', 0, 4);

-- ======= Questões adicionais cobrindo todos os tópicos da disciplina =======
INSERT INTO questoes (enunciado, tipo, categoria, explicacao) VALUES
('No PHP, como toda variável deve ser escrita?', 'multipla_escolha', 'Variáveis e Tipos', 'Em PHP toda variável começa com o sinal de dólar ($).'),
('Qual função do PHP retorna o tipo de uma variável?', 'multipla_escolha', 'Variáveis e Tipos', 'gettype() retorna o tipo da variável (integer, string, array, etc.).'),
('Qual estrutura o PHP usa para executar um bloco somente se uma condição for verdadeira?', 'multipla_escolha', 'Estruturas Condicionais', 'O if executa um bloco quando a condição é verdadeira; pode ser combinado com else.'),
('Em PHP, o operador === compara quais elementos?', 'multipla_escolha', 'Estruturas Condicionais', '=== compara valor e tipo (comparação estrita); == compara apenas o valor.'),
('Qual laço repete um bloco enquanto uma condição for verdadeira?', 'multipla_escolha', 'Estruturas de Repetição', 'while repete o bloco enquanto a condição permanecer verdadeira.'),
('Qual laço é o mais indicado para percorrer todos os itens de um array em PHP?', 'multipla_escolha', 'Estruturas de Repetição', 'foreach percorre automaticamente cada elemento do array.'),
('Qual palavra-chave declara uma função em PHP?', 'multipla_escolha', 'Funções', 'A palavra-chave function declara uma função em PHP.'),
('Qual função do PHP cria um cookie no navegador do usuário?', 'multipla_escolha', 'Cookies', 'setcookie() envia um cookie para o navegador. Deve ser chamada antes de qualquer saída HTML.'),
('Em uma consulta JOIN, qual cláusula define a condição que liga duas tabelas?', 'multipla_escolha', 'Relacionamentos', 'A cláusula ON define como as tabelas se relacionam no JOIN.'),
('Qual recurso do MySQL cria o vínculo (relacionamento) entre duas tabelas?', 'multipla_escolha', 'Relacionamentos', 'A FOREIGN KEY referencia a chave primária de outra tabela, criando o relacionamento.');

-- Alternativas (questões 11 a 20)
-- Questão 11: variável em PHP
INSERT INTO alternativas (questao_id, texto, correta, ordem) VALUES
(11, '$nome', 1, 1), (11, 'nome', 0, 2), (11, 'var nome', 0, 3), (11, '#nome', 0, 4);
-- Questão 12: gettype()
INSERT INTO alternativas (questao_id, texto, correta, ordem) VALUES
(12, 'gettype()', 1, 1), (12, 'type()', 0, 2), (12, 'typeof()', 0, 3), (12, 'vartype()', 0, 4);
-- Questão 13: if
INSERT INTO alternativas (questao_id, texto, correta, ordem) VALUES
(13, 'if', 1, 1), (13, 'when', 0, 2), (13, 'select', 0, 3), (13, 'repeat', 0, 4);
-- Questão 14: operador ===
INSERT INTO alternativas (questao_id, texto, correta, ordem) VALUES
(14, 'O valor e o tipo', 1, 1), (14, 'Apenas o valor', 0, 2), (14, 'Apenas o tipo', 0, 3), (14, 'A referência de memória', 0, 4);
-- Questão 15: while
INSERT INTO alternativas (questao_id, texto, correta, ordem) VALUES
(15, 'while', 1, 1), (15, 'foreach', 0, 2), (15, 'repeat', 0, 3), (15, 'loop', 0, 4);
-- Questão 16: foreach
INSERT INTO alternativas (questao_id, texto, correta, ordem) VALUES
(16, 'foreach', 1, 1), (16, 'while', 0, 2), (16, 'do...while', 0, 3), (16, 'switch', 0, 4);
-- Questão 17: function
INSERT INTO alternativas (questao_id, texto, correta, ordem) VALUES
(17, 'function', 1, 1), (17, 'def', 0, 2), (17, 'func', 0, 3), (17, 'procedure', 0, 4);
-- Questão 18: setcookie()
INSERT INTO alternativas (questao_id, texto, correta, ordem) VALUES
(18, 'setcookie()', 1, 1), (18, 'createcookie()', 0, 2), (18, 'cookie_set()', 0, 3), (18, 'make_cookie()', 0, 4);
-- Questão 19: cláusula ON
INSERT INTO alternativas (questao_id, texto, correta, ordem) VALUES
(19, 'ON', 1, 1), (19, 'LINK', 0, 2), (19, 'MATCH', 0, 3), (19, 'USING', 0, 4);
-- Questão 20: FOREIGN KEY
INSERT INTO alternativas (questao_id, texto, correta, ordem) VALUES
(20, 'FOREIGN KEY', 1, 1), (20, 'INDEX', 0, 2), (20, 'UNIQUE', 0, 3), (20, 'CHECK', 0, 4);

-- ======= Questões dissertativas (correção manual do professor) =======
-- A coluna explicacao funciona como gabarito esperado para a correção.
INSERT INTO questoes (enunciado, tipo, categoria, explicacao) VALUES
('Explique com suas palavras o que é uma variável em PHP e cite dois tipos de dados que ela pode armazenar.', 'dissertativa', 'Variáveis e Tipos', 'Esperado: variável é um espaço na memória que guarda um valor, identificado por um nome iniciado com $. Tipos: string, integer, float, boolean, array, etc.'),
('Descreva a diferença entre as estruturas condicionais if/else e switch em PHP e diga quando cada uma é mais indicada.', 'dissertativa', 'Estruturas Condicionais', 'Esperado: if/else avalia expressões lógicas e faixas de valores; switch compara uma única expressão com vários valores fixos, sendo mais legível quando há muitas opções discretas.'),
('Explique a diferença entre os laços while e do...while em PHP, citando uma situação adequada para cada um.', 'dissertativa', 'Estruturas de Repetição', 'Esperado: while testa a condição antes de executar (pode executar zero vezes); do...while executa ao menos uma vez antes de testar.'),
('O que é uma função em PHP e quais são as vantagens de organizar o código em funções?', 'dissertativa', 'Funções', 'Esperado: bloco de código nomeado, reutilizável, que recebe parâmetros e retorna valor; vantagens: reuso, organização, manutenção e testes.'),
('Explique a diferença entre os métodos GET e POST em formulários HTML e cite uma situação adequada para cada um.', 'dissertativa', 'Métodos GET e POST', 'Esperado: GET envia dados pela URL (visíveis, limitados, cacheáveis) e é indicado para buscas/filtros; POST envia dados no corpo da requisição e é indicado para dados sensíveis ou alterações.'),
('Explique o que é uma sessão em PHP e qual a principal diferença entre sessão e cookie.', 'dissertativa', 'Sessões', 'Esperado: sessão guarda os dados no servidor e o cliente recebe apenas o ID; o cookie fica armazenado no navegador do usuário.'),
('Descreva como o PHP se conecta a um banco MySQL usando PDO e por que o PDO é mais seguro que as funções antigas.', 'dissertativa', 'Conexão PHP/MySQL', 'Esperado: criar objeto PDO com DSN, executar consultas preparadas; PDO é mais seguro por suportar prepared statements, tratar erros por exceções e parametrizar consultas.'),
('Explique o que é SQL Injection e como os prepared statements ajudam a evitá-la.', 'dissertativa', 'Segurança', 'Esperado: inserção de comandos SQL maliciosos na entrada do usuário; prepared statements separam o código SQL dos dados, impedindo a interpolação.'),
('Explique o que é uma chave estrangeira (FOREIGN KEY) e qual a sua importância nos relacionamentos entre tabelas.', 'dissertativa', 'Relacionamentos', 'Esperado: coluna que referencia a chave primária de outra tabela; garante a integridade referencial dos relacionamentos.'),
('Descreva as quatro operações do CRUD e cite o comando SQL correspondente a cada uma.', 'dissertativa', 'CRUD', 'Esperado: Create (INSERT), Read (SELECT), Update (UPDATE) e Delete (DELETE).');
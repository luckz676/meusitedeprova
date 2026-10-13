<?php
/**
 * Funções auxiliares - Sistema de Avaliação Acadêmica
 */

require_once __DIR__ . '/config.php';

/**
 * Autentica usuário por login (usuário OU e-mail) e senha
 * @param string $login Usuário ou e-mail
 * @param string $senha
 * @return array|false Dados do usuário ou false se falhar
 */
function autenticarUsuario(string $login, string $senha): array|false
{
    try {
        $pdo = getDbConnection();
        $stmt = $pdo->prepare("SELECT * FROM usuarios WHERE (email = ? OR usuario = ?) AND ativo = 1 LIMIT 1");
        $stmt->execute([$login, $login]);
        $usuario = $stmt->fetch();

        if ($usuario && password_verify($senha, $usuario['senha_hash'])) {
            unset($usuario['senha_hash']);
            return $usuario;
        }
        return false;
    } catch (Exception $e) {
        error_log("Erro na autenticação: " . $e->getMessage());
        return false;
    }
}

/**
 * Faz login do usuário criando sessão
 * @param array $usuario
 */
function fazerLogin(array $usuario): void
{
    iniciarSessaoSegura();
    session_regenerate_id(true);

    $_SESSION['usuario_id'] = $usuario['id'];
    $_SESSION['usuario_nome'] = $usuario['nome'];
    $_SESSION['usuario_email'] = $usuario['email'];
    $_SESSION['usuario_tipo'] = $usuario['tipo'];
    $_SESSION['login_time'] = time();
}

/**
 * Faz logout destruindo a sessão
 */
function fazerLogout(): void
{
    iniciarSessaoSegura();
    $_SESSION = [];
    session_destroy();

    if (ini_get("session.use_cookies")) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000,
            $params["path"], $params["domain"],
            $params["secure"], $params["httponly"]
        );
    }
}

/**
 * Quantas questões (por tipo) a prova terá, respeitando o que existe no banco
 * @return array{objetivas:int, dissertativas:int, total:int}
 */
function contarQuestoesProva(): array
{
    try {
        $pdo = getDbConnection();
        $objetivas = (int)$pdo->query(
            "SELECT COUNT(*) FROM questoes WHERE ativa = 1 AND tipo IN ('multipla_escolha', 'verdadeiro_falso')"
        )->fetchColumn();
        $dissertativas = (int)$pdo->query(
            "SELECT COUNT(*) FROM questoes WHERE ativa = 1 AND tipo = 'dissertativa'"
        )->fetchColumn();

        $objetivas = min(PROVA_TOTAL_OBJETIVAS, $objetivas);
        $dissertativas = min(PROVA_TOTAL_DISSERTATIVAS, $dissertativas);

        return [
            'objetivas' => $objetivas,
            'dissertativas' => $dissertativas,
            'total' => $objetivas + $dissertativas,
        ];
    } catch (Exception $e) {
        error_log("Erro ao contar questões da prova: " . $e->getMessage());
        return ['objetivas' => 0, 'dissertativas' => 0, 'total' => 0];
    }
}

/**
 * Monta o conjunto de questões de uma nova prova, intercalando
 * questões objetivas (múltipla escolha / verdadeiro-falso) e dissertativas.
 * @return array Lista de questões com suas alternativas
 */
function montarQuestoesProva(): array
{
    try {
        $pdo = getDbConnection();
        $composicao = contarQuestoesProva();

        $objetivas = $pdo->query(
            "SELECT id FROM questoes
             WHERE ativa = 1 AND tipo IN ('multipla_escolha', 'verdadeiro_falso')
             ORDER BY RAND() LIMIT " . (int)$composicao['objetivas']
        )->fetchAll(PDO::FETCH_COLUMN);

        $dissertativas = $pdo->query(
            "SELECT id FROM questoes
             WHERE ativa = 1 AND tipo = 'dissertativa'
             ORDER BY RAND() LIMIT " . (int)$composicao['dissertativas']
        )->fetchAll(PDO::FETCH_COLUMN);

        // Intercala: objetiva, dissertativa, objetiva, dissertativa...
        $sequencia = [];
        $maior = max(count($objetivas), count($dissertativas));
        for ($i = 0; $i < $maior; $i++) {
            if (isset($objetivas[$i])) $sequencia[] = (int)$objetivas[$i];
            if (isset($dissertativas[$i])) $sequencia[] = (int)$dissertativas[$i];
        }

        $questoes = [];
        foreach ($sequencia as $posicao => $questaoId) {
            $questao = obterQuestaoComAlternativas($questaoId);
            if ($questao) {
                $questao['ordem_prova'] = $posicao + 1;
                $questoes[] = $questao;
            }
        }

        return $questoes;
    } catch (Exception $e) {
        error_log("Erro ao montar questões da prova: " . $e->getMessage());
        return [];
    }
}

/**
 * Cria nova avaliação para o aluno, gravando também as questões sorteadas
 * @param int $usuarioId
 * @param int $tempoLimite
 * @param array $questoes Questões geradas por montarQuestoesProva()
 * @return int|false ID da avaliação ou false
 */
function criarAvaliacao(int $usuarioId, int $tempoLimite, array $questoes): int|false
{
    try {
        $pdo = getDbConnection();
        $pdo->beginTransaction();

        $stmt = $pdo->prepare(
            "INSERT INTO avaliacoes (usuario_id, tempo_limite_segundos, inicio, total_questoes, status)
             VALUES (?, ?, NOW(), ?, 'em_andamento')"
        );
        $stmt->execute([$usuarioId, $tempoLimite, count($questoes)]);
        $avaliacaoId = (int)$pdo->lastInsertId();

        $stmtQ = $pdo->prepare(
            "INSERT INTO avaliacao_questoes (avaliacao_id, questao_id, ordem) VALUES (?, ?, ?)"
        );
        foreach ($questoes as $indice => $questao) {
            $stmtQ->execute([$avaliacaoId, (int)$questao['id'], $indice + 1]);
        }

        $pdo->commit();
        return $avaliacaoId;
    } catch (Exception $e) {
        if (isset($pdo) && $pdo->inTransaction()) {
            $pdo->rollBack();
        }
        error_log("Erro ao criar avaliação: " . $e->getMessage());
        return false;
    }
}

/**
 * Obtém avaliação ativa do aluno
 * @param int $usuarioId
 * @return array|false
 */
function obterAvaliacaoAtiva(int $usuarioId): array|false
{
    try {
        $pdo = getDbConnection();
        $stmt = $pdo->prepare(
            "SELECT * FROM avaliacoes
             WHERE usuario_id = ? AND status = 'em_andamento'
             ORDER BY inicio DESC LIMIT 1"
        );
        $stmt->execute([$usuarioId]);
        return $stmt->fetch() ?: false;
    } catch (Exception $e) {
        error_log("Erro ao obter avaliação ativa: " . $e->getMessage());
        return false;
    }
}

/**
 * Obtém avaliação por ID
 * @param int $avaliacaoId
 * @return array|false
 */
function obterAvaliacaoPorId(int $avaliacaoId): array|false
{
    try {
        $pdo = getDbConnection();
        $stmt = $pdo->prepare(
            "SELECT a.*, u.nome AS aluno_nome, u.email AS aluno_email
             FROM avaliacoes a
             LEFT JOIN usuarios u ON u.id = a.usuario_id
             WHERE a.id = ?"
        );
        $stmt->execute([$avaliacaoId]);
        return $stmt->fetch() ?: false;
    } catch (Exception $e) {
        error_log("Erro ao obter avaliação: " . $e->getMessage());
        return false;
    }
}

/**
 * Finaliza avaliação e calcula nota
 *
 * @param int $avaliacaoId
 * @param bool $fraude true quando a prova foi encerrada pelo anti-cola
 *                     (grava o status 'fraude' para o professor ver a ocorrência)
 * @return bool
 */
function finalizarAvaliacao(int $avaliacaoId, bool $fraude = false): bool
{
    try {
        $pdo = getDbConnection();

        // Quantiifica as questões objetivas da prova (as dissertativas são corrigidas pelo professor)
        $stmtQ = $pdo->prepare(
            "SELECT q.tipo FROM avaliacao_questoes aq
             JOIN questoes q ON q.id = aq.questao_id
             WHERE aq.avaliacao_id = ?"
        );
        $stmtQ->execute([$avaliacaoId]);
        $totalObjetivas = 0;
        foreach ($stmtQ->fetchAll() as $questaoProva) {
            if ($questaoProva['tipo'] !== 'dissertativa') {
                $totalObjetivas++;
            }
        }

        $stmt = $pdo->prepare(
            "SELECT r.*, a.correta as alternativa_correta
             FROM respostas r
             LEFT JOIN alternativas a ON r.alternativa_id = a.id
             WHERE r.avaliacao_id = ?"
        );
        $stmt->execute([$avaliacaoId]);
        $respostas = $stmt->fetchAll();

        $acertos = 0;
        $stmtUpd = $pdo->prepare("UPDATE respostas SET correta = ? WHERE id = ?");

        foreach ($respostas as $resp) {
            if ($resp['alternativa_id'] === null) {
                // Dissertativa: fica pendente de correção manual
                $stmtUpd->execute([null, $resp['id']]);
                continue;
            }

            $correta = $resp['alternativa_correta'] ? 1 : 0;
            if ($correta) {
                $acertos++;
            }
            $stmtUpd->execute([$correta, $resp['id']]);
        }

        $erros = max(0, $totalObjetivas - $acertos);
        $nota = $totalObjetivas > 0 ? round(($acertos / $totalObjetivas) * 10, 2) : 0;

        $status = $fraude ? 'fraude' : 'finalizada';

        $stmtUpd = $pdo->prepare(
            "UPDATE avaliacoes SET fim = NOW(), nota = ?, acertos = ?, erros = ?, status = ? WHERE id = ?"
        );
        return $stmtUpd->execute([$nota, $acertos, $erros, $status, $avaliacaoId]);
    } catch (Exception $e) {
        error_log("Erro ao finalizar avaliação: " . $e->getMessage());
        return false;
    }
}

/**
 * Salva resposta do aluno
 * @param int $avaliacaoId
 * @param int $questaoId
 * @param int|null $alternativaId
 * @param string|null $respostaTexto
 * @param int $tempoResposta
 * @return bool
 */
function salvarResposta(int $avaliacaoId, int $questaoId, ?int $alternativaId, ?string $respostaTexto, int $tempoResposta): bool
{
    try {
        $pdo = getDbConnection();
        $stmt = $pdo->prepare(
            "INSERT INTO respostas (avaliacao_id, questao_id, alternativa_id, resposta_texto, tempo_resposta_segundos)
             VALUES (?, ?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE
                alternativa_id = VALUES(alternativa_id),
                resposta_texto = VALUES(resposta_texto),
                tempo_resposta_segundos = VALUES(tempo_resposta_segundos)"
        );
        return $stmt->execute([$avaliacaoId, $questaoId, $alternativaId, $respostaTexto, $tempoResposta]);
    } catch (Exception $e) {
        error_log("Erro ao salvar resposta: " . $e->getMessage());
        return false;
    }
}

/**
 * Obtém as questões de uma avaliação já iniciada (ordem fixa gravada no banco)
 * @param int $avaliacaoId
 * @return array
 */
function obterQuestoesAvaliacao(int $avaliacaoId): array
{
    try {
        $pdo = getDbConnection();
        $stmt = $pdo->prepare(
            "SELECT q.* FROM avaliacao_questoes aq
             JOIN questoes q ON q.id = aq.questao_id
             WHERE aq.avaliacao_id = ?
             ORDER BY aq.ordem"
        );
        $stmt->execute([$avaliacaoId]);
        $questoes = $stmt->fetchAll();

        $stmtAlt = $pdo->prepare("SELECT id, texto, ordem FROM alternativas WHERE questao_id = ? ORDER BY ordem");
        foreach ($questoes as &$q) {
            $q['alternativas'] = [];
            if ($q['tipo'] !== 'dissertativa') {
                $stmtAlt->execute([$q['id']]);
                $alternativas = $stmtAlt->fetchAll();
                shuffle($alternativas);
                $q['alternativas'] = $alternativas;
            }
        }

        return $questoes;
    } catch (Exception $e) {
        error_log("Erro ao obter questões da avaliação: " . $e->getMessage());
        return [];
    }
}

/**
 * Obtém questão com alternativas
 * @param int $questaoId
 * @return array|false
 */
function obterQuestaoComAlternativas(int $questaoId): array|false
{
    try {
        $pdo = getDbConnection();
        $stmt = $pdo->prepare("SELECT * FROM questoes WHERE id = ? AND ativa = 1");
        $stmt->execute([$questaoId]);
        $questao = $stmt->fetch();

        if (!$questao) return false;

        $stmtAlt = $pdo->prepare("SELECT id, texto, ordem FROM alternativas WHERE questao_id = ? ORDER BY ordem");
        $stmtAlt->execute([$questaoId]);
        $questao['alternativas'] = $stmtAlt->fetchAll();

        return $questao;
    } catch (Exception $e) {
        error_log("Erro ao obter questão: " . $e->getMessage());
        return false;
    }
}

/**
 * Rótulo de exibição e classe de badge para cada tipo de questão
 * @param string $tipo
 * @return array{0:string, 1:string} [rótulo, classe do badge]
 */
function rotuloTipoQuestao(string $tipo): array
{
    $rotulos = [
        'multipla_escolha' => ['Múltipla Escolha', 'badge-info'],
        'verdadeiro_falso' => ['Verdadeiro/Falso', 'badge-warning'],
        'dissertativa' => ['Dissertativa', 'badge-primary'],
    ];

    return $rotulos[$tipo] ?? [$tipo, 'badge-secondary'];
}

/**
 * Última avaliação finalizada do aluno (para mostrar o resultado no dashboard)
 * @param int $usuarioId
 * @return array|false
 */
function obterUltimaAvaliacaoFinalizada(int $usuarioId): array|false
{
    try {
        $pdo = getDbConnection();
        $stmt = $pdo->prepare(
            "SELECT * FROM avaliacoes
             WHERE usuario_id = ? AND status IN ('finalizada', 'tempo_esgotado', 'fraude')
             ORDER BY fim DESC, id DESC LIMIT 1"
        );
        $stmt->execute([$usuarioId]);
        return $stmt->fetch() ?: false;
    } catch (Exception $e) {
        error_log("Erro ao obter última avaliação: " . $e->getMessage());
        return false;
    }
}

/**
 * Salva o feedback do site (avaliação com estrelas)
 * @param int|null $usuarioId
 * @param int $estrelas 1 a 5
 * @param string $comentario
 * @return bool
 */
function salvarFeedback(?int $usuarioId, int $estrelas, string $comentario): bool
{
    if ($estrelas < 1 || $estrelas > 5) {
        return false;
    }

    try {
        $pdo = getDbConnection();
        $stmt = $pdo->prepare(
            "INSERT INTO feedbacks (usuario_id, estrelas, comentario) VALUES (?, ?, ?)"
        );
        return $stmt->execute([$usuarioId, $estrelas, $comentario !== '' ? $comentario : null]);
    } catch (Exception $e) {
        error_log("Erro ao salvar feedback: " . $e->getMessage());
        return false;
    }
}

/**
 * Último feedback enviado pelo usuário
 * @param int $usuarioId
 * @return array|false
 */
function obterUltimoFeedback(int $usuarioId): array|false
{
    try {
        $pdo = getDbConnection();
        $stmt = $pdo->prepare(
            "SELECT * FROM feedbacks WHERE usuario_id = ? ORDER BY created_at DESC, id DESC LIMIT 1"
        );
        $stmt->execute([$usuarioId]);
        return $stmt->fetch() ?: false;
    } catch (Exception $e) {
        error_log("Erro ao obter feedback: " . $e->getMessage());
        return false;
    }
}

/**
 * Verifica se aluno já fez avaliação
 * @param int $usuarioId
 * @return bool
 */
function jaFezAvaliacao(int $usuarioId): bool
{
    try {
        $pdo = getDbConnection();
        $stmt = $pdo->prepare(
            "SELECT COUNT(*) FROM avaliacoes WHERE usuario_id = ? AND status IN ('finalizada', 'tempo_esgotado', 'fraude')"
        );
        $stmt->execute([$usuarioId]);
        return $stmt->fetchColumn() > 0;
    } catch (Exception $e) {
        error_log("Erro ao verificar avaliação prévia: " . $e->getMessage());
        return false;
    }
}

/**
 * Lista todos os usuários (admin)
 * @return array
 */
function listarUsuarios(): array
{
    try {
        $pdo = getDbConnection();
        $stmt = $pdo->query("SELECT id, nome, usuario, email, tipo, ativo, created_at FROM usuarios ORDER BY tipo, nome");
        return $stmt->fetchAll();
    } catch (Exception $e) {
        error_log("Erro ao listar usuários: " . $e->getMessage());
        return [];
    }
}

/**
 * Lista todas as avaliações (admin)
 * @return array
 */
function listarAvaliacoes(): array
{
    try {
        $pdo = getDbConnection();
        $stmt = $pdo->query(
            "SELECT a.*, u.nome as aluno_nome, u.email as aluno_email
             FROM avaliacoes a
             JOIN usuarios u ON a.usuario_id = u.id
             ORDER BY a.inicio DESC"
        );
        return $stmt->fetchAll();
    } catch (Exception $e) {
        error_log("Erro ao listar avaliações: " . $e->getMessage());
        return [];
    }
}

/**
 * Lista eventos suspeitos de uma avaliação
 * @param int $avaliacaoId
 * @return array
 */
function listarEventosSuspeitos(int $avaliacaoId): array
{
    try {
        $pdo = getDbConnection();
        $stmt = $pdo->prepare("SELECT * FROM eventos_suspeitos WHERE avaliacao_id = ? ORDER BY timestamp");
        $stmt->execute([$avaliacaoId]);
        return $stmt->fetchAll();
    } catch (Exception $e) {
        error_log("Erro ao listar eventos suspeitos: " . $e->getMessage());
        return [];
    }
}

/**
 * Cadastra nova questão (admin)
 * @param string $enunciado
 * @param string $tipo
 * @param string $categoria
 * @param string $explicacao
 * @param array $alternativas [['texto' => '', 'correta' => 0/1, 'ordem' => 0], ...]
 * @return int|false
 */
function cadastrarQuestao(string $enunciado, string $tipo, string $categoria, string $explicacao, array $alternativas): int|false
{
    try {
        $pdo = getDbConnection();
        $pdo->beginTransaction();

        $stmt = $pdo->prepare(
            "INSERT INTO questoes (enunciado, tipo, categoria, explicacao) VALUES (?, ?, ?, ?)"
        );
        $stmt->execute([$enunciado, $tipo, $categoria, $explicacao]);
        $questaoId = (int)$pdo->lastInsertId();

        foreach ($alternativas as $alt) {
            $stmtAlt = $pdo->prepare(
                "INSERT INTO alternativas (questao_id, texto, correta, ordem) VALUES (?, ?, ?, ?)"
            );
            $stmtAlt->execute([$questaoId, $alt['texto'], $alt['correta'] ? 1 : 0, $alt['ordem'] ?? 0]);
        }

        $pdo->commit();
        return $questaoId;
    } catch (Exception $e) {
        $pdo->rollBack();
        error_log("Erro ao cadastrar questão: " . $e->getMessage());
        return false;
    }
}

/**
 * Atualiza questão (admin)
 * @param int $questaoId
 * @param string $enunciado
 * @param string $tipo
 * @param string $categoria
 * @param string $explicacao
 * @param array $alternativas
 * @return bool
 */
function atualizarQuestao(int $questaoId, string $enunciado, string $tipo, string $categoria, string $explicacao, array $alternativas): bool
{
    try {
        $pdo = getDbConnection();
        $pdo->beginTransaction();

        $stmt = $pdo->prepare(
            "UPDATE questoes SET enunciado = ?, tipo = ?, categoria = ?, explicacao = ? WHERE id = ?"
        );
        $stmt->execute([$enunciado, $tipo, $categoria, $explicacao, $questaoId]);

        $pdo->prepare("DELETE FROM alternativas WHERE questao_id = ?")->execute([$questaoId]);

        foreach ($alternativas as $alt) {
            $stmtAlt = $pdo->prepare(
                "INSERT INTO alternativas (questao_id, texto, correta, ordem) VALUES (?, ?, ?, ?)"
            );
            $stmtAlt->execute([$questaoId, $alt['texto'], $alt['correta'] ? 1 : 0, $alt['ordem'] ?? 0]);
        }

        $pdo->commit();
        return true;
    } catch (Exception $e) {
        $pdo->rollBack();
        error_log("Erro ao atualizar questão: " . $e->getMessage());
        return false;
    }
}

/**
 * Exclui questão (admin)
 * @param int $questaoId
 * @return bool
 */
function excluirQuestao(int $questaoId): bool
{
    try {
        $pdo = getDbConnection();
        $stmt = $pdo->prepare("DELETE FROM questoes WHERE id = ?");
        return $stmt->execute([$questaoId]);
    } catch (Exception $e) {
        error_log("Erro ao excluir questão: " . $e->getMessage());
        return false;
    }
}

/**
 * Cria novo usuário (admin)
 * @param string $nome
 * @param string $email
 * @param string $senha
 * @param string $tipo
 * @param string|null $usuario Login do usuário (opcional)
 * @return int|false
 */
function criarUsuario(string $nome, string $email, string $senha, string $tipo = 'aluno', ?string $usuario = null): int|false
{
    try {
        $pdo = getDbConnection();
        $senhaHash = password_hash($senha, HASH_ALGO, ['cost' => HASH_COST]);

        $stmt = $pdo->prepare(
            "INSERT INTO usuarios (nome, usuario, email, senha_hash, tipo) VALUES (?, ?, ?, ?, ?)"
        );
        $stmt->execute([$nome, $usuario ?: null, $email, $senhaHash, $tipo]);
        return (int)$pdo->lastInsertId();
    } catch (Exception $e) {
        error_log("Erro ao criar usuário: " . $e->getMessage());
        return false;
    }
}

/**
 * Obtém estatísticas do dashboard admin
 * @return array
 */
function obterEstatisticas(): array
{
    try {
        $pdo = getDbConnection();

        $stats = [];
        $stats['total_alunos'] = $pdo->query("SELECT COUNT(*) FROM usuarios WHERE tipo = 'aluno'")->fetchColumn();
        $stats['total_questoes'] = $pdo->query("SELECT COUNT(*) FROM questoes WHERE ativa = 1")->fetchColumn();
        $stats['total_avaliacoes'] = $pdo->query("SELECT COUNT(*) FROM avaliacoes")->fetchColumn();
        $stats['avaliacoes_finalizadas'] = $pdo->query("SELECT COUNT(*) FROM avaliacoes WHERE status IN ('finalizada', 'fraude')")->fetchColumn();
        $stats['avaliacoes_fraude'] = $pdo->query("SELECT COUNT(*) FROM avaliacoes WHERE status = 'fraude'")->fetchColumn();
        $stats['avaliacoes_em_andamento'] = $pdo->query("SELECT COUNT(*) FROM avaliacoes WHERE status = 'em_andamento'")->fetchColumn();
        $stats['total_eventos_suspeitos'] = $pdo->query("SELECT COUNT(*) FROM eventos_suspeitos")->fetchColumn();

        $stmt = $pdo->query("SELECT AVG(nota) as media FROM avaliacoes WHERE status = 'finalizada' AND nota IS NOT NULL");
        $stats['media_notas'] = round($stmt->fetchColumn() ?? 0, 2);

        return $stats;
    } catch (Exception $e) {
        error_log("Erro ao obter estatísticas: " . $e->getMessage());
        return [];
    }
}
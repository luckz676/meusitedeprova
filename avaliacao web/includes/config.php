<?php
/**
 * Configuração do Banco de Dados - Sistema de Avaliação Acadêmica
 */

// Fuso horário: garante que o PHP use o mesmo horário do MySQL/SO,
// evitando que o tempo restante da avaliação seja calculado errado.
date_default_timezone_set('America/Sao_Paulo');

// Configurações do banco de dados
define('DB_HOST', 'localhost');
define('DB_NAME', 'avaliacao_web');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_CHARSET', 'utf8mb4');

// Configurações da aplicação
define('APP_NAME', 'Sistema de Avaliação Acadêmica');
define('SESSION_NAME', 'avaliacao_session');
define('TEMPO_LIMITE_PADRAO', 3600); // 1 hora em segundos

// Composição da avaliação (as questões são sorteadas do banco e intercaladas)
define('PROVA_TOTAL_OBJETIVAS', 5);      // questões de múltipla escolha / verdadeiro-falso
define('PROVA_TOTAL_DISSERTATIVAS', 5);  // questões dissertativas (correção manual)

// Anti-cola: quantas violações GRAVES (trocar de aba/janela, sair da tela cheia,
// abrir DevTools) finalizam a prova automaticamente. Use 0 para não ter limite.
define('LIMITE_FALTAS_ANTI_CHEAT', 5);

// Configurações de segurança
define('HASH_ALGO', PASSWORD_BCRYPT);
define('HASH_COST', 10);

/**
 * Devolve a "versão" de um arquivo estático (css/js) baseada na data de
 * modificação. Vai no fim da URL (?v=...) para o navegador nunca continuar
 * usando uma versão antiga em cache — era assim que uma correção no
 * anti-cheat.js ficava invisível para quem já tinha aberto a página antes.
 *
 * @param string $caminhoRelativo Caminho a partir da raiz do projeto
 * @return string
 */
function versaoEstatico(string $caminhoRelativo): string
{
    $caminho = __DIR__ . '/../' . ltrim($caminhoRelativo, '/');
    $mtime = is_file($caminho) ? filemtime($caminho) : false;
    return (string)($mtime ?: 1);
}

/**
 * Obtém conexão PDO com o banco de dados
 * @return PDO
 * @throws Exception
 */
function getDbConnection(): PDO
{
    static $pdo = null;

    if ($pdo === null) {
        try {
            $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET;
            $options = [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
                PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES " . DB_CHARSET
            ];

            $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
        } catch (PDOException $e) {
            error_log("Erro de conexão com banco: " . $e->getMessage());
            throw new Exception("Erro de conexão com o banco de dados");
        }
    }

    return $pdo;
}

/**
 * Inicia sessão segura
 */
function iniciarSessaoSegura(): void
{
    if (session_status() === PHP_SESSION_NONE) {
        // Configurações de segurança da sessão
        ini_set('session.cookie_httponly', '1');
        ini_set('session.cookie_secure', isset($_SERVER['HTTPS']) ? '1' : '0');
        ini_set('session.use_strict_mode', '1');
        ini_set('session.cookie_samesite', 'Strict');

        session_name(SESSION_NAME);
        session_start();

        // Regenera ID da sessão periodicamente para prevenir fixation
        if (!isset($_SESSION['ultima_regeneracao'])) {
            $_SESSION['ultima_regeneracao'] = time();
        } elseif (time() - $_SESSION['ultima_regeneracao'] > 300) { // 5 minutos
            session_regenerate_id(true);
            $_SESSION['ultima_regeneracao'] = time();
        }
    }
}

/**
 * Verifica se usuário está logado
 * @return bool
 */
function estaLogado(): bool
{
    iniciarSessaoSegura();
    return isset($_SESSION['usuario_id']) && isset($_SESSION['usuario_tipo']);
}

/**
 * Verifica se é administrador
 * @return bool
 */
function ehAdmin(): bool
{
    return estaLogado() && $_SESSION['usuario_tipo'] === 'admin';
}

/**
 * Verifica se é aluno
 * @return bool
 */
function ehAluno(): bool
{
    return estaLogado() && $_SESSION['usuario_tipo'] === 'aluno';
}

/**
 * Redireciona se não estiver logado
 */
function requerLogin(): void
{
    if (!estaLogado()) {
        header('Location: /avaliacao web/login.php');
        exit;
    }
}

/**
 * Redireciona se não for admin
 */
function requerAdmin(): void
{
    requerLogin();
    if (!ehAdmin()) {
        header('Location: /avaliacao web/dashboard.php');
        exit;
    }
}

/**
 * Redireciona se não for aluno
 */
function requerAluno(): void
{
    requerLogin();
    if (!ehAluno()) {
        header('Location: /avaliacao web/admin/dashboard.php');
        exit;
    }
}

/**
 * Sanitiza entrada para saída HTML
 * @param string $dado
 * @return string
 */
function e(string $dado): string
{
    return htmlspecialchars($dado, ENT_QUOTES, 'UTF-8');
}

/**
 * Sanitiza entrada para uso em SQL (prepared statements já fazem isso, mas útil para LIKE)
 * @param string $dado
 * @return string
 */
function sanitizar(string $dado): string
{
    return trim(strip_tags($dado));
}

/**
 * Gera token CSRF
 * @return string
 */
function gerarTokenCSRF(): string
{
    iniciarSessaoSegura();
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/**
 * Valida token CSRF
 * @param string $token
 * @return bool
 */
function validarTokenCSRF(string $token): bool
{
    iniciarSessaoSegura();
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

/**
 * Flash messages para feedback ao usuário
 */
function flash(string $tipo, string $mensagem): void
{
    iniciarSessaoSegura();
    $_SESSION['flash'][] = ['tipo' => $tipo, 'mensagem' => $mensagem];
}

function obterFlashes(): array
{
    iniciarSessaoSegura();
    $flashes = $_SESSION['flash'] ?? [];
    unset($_SESSION['flash']);
    return $flashes;
}

/**
 * Formata data/hora para exibição
 * @param string|DateTime $data
 * @param string $formato
 * @return string
 */
function formatarData($data, string $formato = 'd/m/Y H:i:s'): string
{
    if ($data instanceof DateTime) {
        return $data->format($formato);
    }
    return (new DateTime($data))->format($formato);
}

/**
 * Calcula tempo restante da avaliação
 * @param string $inicio
 * @param int $tempoLimite
 * @return int Segundos restantes (0 se acabou)
 */
function tempoRestante(string $inicio, int $tempoLimite): int
{
    $inicioTime = new DateTime($inicio);
    $agora = new DateTime();
    $decorrido = $agora->getTimestamp() - $inicioTime->getTimestamp();
    $restante = $tempoLimite - $decorrido;
    return max(0, $restante);
}

/**
 * Registra evento suspeito
 * @param int $avaliacaoId
 * @param string $tipoEvento
 * @param string $descricao
 * @return bool
 */
function registrarEventoSuspeito(int $avaliacaoId, string $tipoEvento, string $descricao = ''): bool
{
    try {
        $pdo = getDbConnection();
        $stmt = $pdo->prepare(
            "INSERT INTO eventos_suspeitos (avaliacao_id, tipo_evento, descricao) VALUES (?, ?, ?)"
        );
        return $stmt->execute([$avaliacaoId, $tipoEvento, $descricao]);
    } catch (Exception $e) {
        error_log("Erro ao registrar evento suspeito: " . $e->getMessage());
        return false;
    }
}

/**
 * Obtém configurações do sistema
 * @return array
 */
function getConfiguracoes(): array
{
    return [
        'tempo_limite_padrao' => TEMPO_LIMITE_PADRAO,
        'app_name' => APP_NAME
    ];
}
<?php
/**
 * Dashboard do Aluno - Sistema de Avaliação Acadêmica
 */

require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/feedback_widget.php';

requerAluno();

$usuarioId = $_SESSION['usuario_id'];
$usuarioNome = $_SESSION['usuario_nome'];

// Composição da prova (definida em includes/config.php)
$composicao = contarQuestoesProva();

$tempoLimiteTexto = TEMPO_LIMITE_PADRAO >= 3600
    ? (int)(TEMPO_LIMITE_PADRAO / 3600) . ' hora' . (TEMPO_LIMITE_PADRAO >= 7200 ? 's' : '')
    : (int)(TEMPO_LIMITE_PADRAO / 60) . ' minutos';

$avaliacaoAtiva = obterAvaliacaoAtiva($usuarioId);
$ultimaAvaliacao = obterUltimaAvaliacaoFinalizada($usuarioId);

$tempoRestante = 0;
if ($avaliacaoAtiva) {
    $tempoRestante = tempoRestante($avaliacaoAtiva['inicio'], $avaliacaoAtiva['tempo_limite_segundos']);
    if ($tempoRestante <= 0) {
        finalizarAvaliacao($avaliacaoAtiva['id']);
        $avaliacaoAtiva['status'] = 'tempo_esgotado';
        $tempoRestante = 0;
    }
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - <?= e(APP_NAME) ?></title>
    <link rel="stylesheet" href="/avaliacao web/assets/css/style.css?v=<?= versaoEstatico('assets/css/style.css') ?>">
</head>
<body class="dashboard-page">
    <header class="main-header">
        <div class="container header-content">
            <h1><?= e(APP_NAME) ?></h1>
            <div class="user-info">
                <span class="user-name"><?= e($usuarioNome) ?></span>
                <a href="/avaliacao web/logout.php" class="btn btn-secondary btn-sm">Sair</a>
            </div>
        </div>
    </header>

    <main class="container main-content">
        <div class="dashboard-header">
            <h2>Bem-vindo, <?= e($usuarioNome) ?>!</h2>
            <p class="subtitle">Sistema de Avaliação Acadêmica - Programação Web</p>
        </div>

        <?php if ($avaliacaoAtiva && $avaliacaoAtiva['status'] === 'em_andamento'): ?>
            <div class="card status-card in-progress">
                <div class="status-icon timer" id="timer-display">⏱</div>
                <h3>Avaliação em Andamento</h3>
                <p>Tempo restante: <strong id="tempo-restante"><?= gmdate('H:i:s', $tempoRestante) ?></strong></p>
                <a href="/avaliacao web/avaliacao.php" class="btn btn-primary btn-lg">Continuar Avaliação</a>
                <p class="warning">⚠️ Não feche esta aba! O tempo continua contando.</p>
            </div>

            <script>
                let tempoRestante = <?= $tempoRestante ?>;
                const timerEl = document.getElementById('tempo-restante');

                const interval = setInterval(() => {
                    tempoRestante--;
                    if (tempoRestante <= 0) {
                        timerEl.textContent = '00:00:00';
                        clearInterval(interval);
                        setTimeout(() => location.reload(), 1000);
                    } else {
                        const h = String(Math.floor(tempoRestante / 3600)).padStart(2, '0');
                        const m = String(Math.floor((tempoRestante % 3600) / 60)).padStart(2, '0');
                        const s = String(tempoRestante % 60).padStart(2, '0');
                        timerEl.textContent = `${h}:${m}:${s}`;
                    }
                }, 1000);
            </script>

        <?php else: ?>
            <div class="card status-card available">
                <div class="status-icon">📝</div>
                <h3>Avaliação Disponível</h3>
                <p>Você ainda não iniciou uma avaliação. Clique abaixo para começar.</p>
                <p class="info">
                    A avaliação possui <strong><?= $composicao['total'] ?> questões</strong>
                    (<?= $composicao['objetivas'] ?> objetivas e <?= $composicao['dissertativas'] ?> dissertativas),
                    intercalando os dois tipos.
                </p>
                <p class="info">Tempo limite: <strong><?= e($tempoLimiteTexto) ?></strong></p>

                <form method="POST" action="/avaliacao web/iniciar_avaliacao.php">
                    <input type="hidden" name="csrf_token" value="<?= e(gerarTokenCSRF()) ?>">
                    <button type="submit" class="btn btn-primary btn-lg" id="btn-iniciar">Iniciar Avaliação</button>
                </form>

                <div class="aviso-importante">
                    <h4>⚠️ Regras Importantes:</h4>
                    <ul>
                        <li>O tempo começa a contar imediatamente ao iniciar</li>
                        <li>Não é permitido mudar de aba/janela nem abrir novas abas durante a prova</li>
                        <li>Copiar e colar são bloqueados e registrados como tentativa de fraude</li>
                        <li>Eventos suspeitos são registrados automaticamente</li>
                        <li>A avaliação é finalizada automaticamente ao acabar o tempo</li>
                        <li>As questões dissertativas são corrigidas manualmente pelo professor</li>
                    </ul>
                </div>
            </div>

            <?php if ($ultimaAvaliacao): ?>
                <?php $ultimaFoiFraude = $ultimaAvaliacao['status'] === 'fraude'; ?>
                <div class="card status-card completed<?= $ultimaFoiFraude ? ' fraude' : '' ?>">
                    <div class="status-icon"><?= $ultimaFoiFraude ? '🚨' : '✓' ?></div>
                    <h3>Última Avaliação Concluída</h3>
                    <?php if ($ultimaFoiFraude): ?>
                        <p class="fraude">
                            🚨 Esta prova foi <strong>encerrada por fraude</strong> pelo sistema anti-cola.
                        </p>
                    <?php endif; ?>
                    <p>
                        Nota: <strong><?= number_format((float)($ultimaAvaliacao['nota'] ?? 0), 1) ?></strong>
                        &middot; <?= formatarData($ultimaAvaliacao['inicio'], 'd/m/Y \à\s H:i') ?>
                    </p>
                    <a href="/avaliacao web/resultado.php?id=<?= (int)$ultimaAvaliacao['id'] ?>" class="btn btn-secondary">Ver Resultado</a>
                </div>
            <?php endif; ?>
        <?php endif; ?>

        <div class="card info-card">
            <h3>📚 Conteúdos da Avaliação</h3>
            <div class="topics-grid">
                <span class="topic-tag">PHP</span>
                <span class="topic-tag">Variáveis e tipos de dados</span>
                <span class="topic-tag">Estruturas condicionais</span>
                <span class="topic-tag">Estruturas de repetição</span>
                <span class="topic-tag">Funções</span>
                <span class="topic-tag">Formulários HTML com PHP</span>
                <span class="topic-tag">Métodos GET e POST</span>
                <span class="topic-tag">Sessões</span>
                <span class="topic-tag">Cookies</span>
                <span class="topic-tag">Conexão PHP/MySQL</span>
                <span class="topic-tag">SQL</span>
                <span class="topic-tag">CRUD</span>
                <span class="topic-tag">Relacionamentos entre tabelas</span>
                <span class="topic-tag">Segurança em aplicações web</span>
            </div>
        </div>

        <?php renderFeedbackWidget(); ?>
    </main>

    <footer class="main-footer">
        <div class="container">
            <p>&copy; 2024 - Sistema de Avaliação Acadêmica</p>
        </div>
    </footer>
</body>
</html>

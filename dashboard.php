<?php
/**
 * Dashboard do Aluno - Sistema de Avaliação Acadêmica
 */

require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/feedback_widget.php';
require_once __DIR__ . '/includes/icones.php';

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
                <a href="/avaliacao web/logout.php" class="btn btn-secondary btn-sm"><?= icone('sair', 15) ?> Sair</a>
            </div>
        </div>
    </header>

    <main class="container main-content">
        <div class="dashboard-header">
            <div>
                <h2>Olá, <?= e($usuarioNome) ?>.</h2>
                <p class="subtitle">Avaliação de Programação Web</p>
            </div>
            <p class="dashboard-header__meta">
                <?= (int)$composicao['total'] ?> questões · <?= e($tempoLimiteTexto) ?> de prova
            </p>
        </div>

        <?php if ($avaliacaoAtiva && $avaliacaoAtiva['status'] === 'em_andamento'): ?>
            <div class="card status-card in-progress">
                <div class="status-card__topo">
                    <span class="status-card__selo">Em andamento</span>
                    <span class="mono text-muted" style="font-size: 0.7rem;">
                        iniciada em <?= formatarData($avaliacaoAtiva['inicio'], 'd/m \à\s H:i') ?>
                    </span>
                </div>

                <h3>Você tem uma prova aberta.</h3>
                <p>O relógio continua correndo enquanto esta tela não for retomada.</p>

                <dl class="prova-dados">
                    <div>
                        <dt>Tempo restante</dt>
                        <dd><span id="tempo-restante"><?= gmdate('H:i:s', $tempoRestante) ?></span></dd>
                    </div>
                    <div>
                        <dt>Questões</dt>
                        <dd><?= (int)$avaliacaoAtiva['total_questoes'] ?></dd>
                    </div>
                </dl>

                <a href="/avaliacao web/avaliacao.php" class="btn btn-primary btn-lg">Continuar avaliação</a>
                <p class="warning"><?= icone('alerta', 15) ?> Não feche esta aba: o tempo continua contando.</p>
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
                <div class="status-card__topo">
                    <span class="status-card__selo">Disponível</span>
                    <span class="mono text-muted" style="font-size: 0.7rem;">aguardando início</span>
                </div>

                <h3>Avaliação de Programação Web</h3>
                <p>
                    Você ainda não iniciou. Ao começar, a câmera é ligada e a prova entra em
                    tela cheia até a entrega.
                </p>

                <dl class="prova-dados">
                    <div>
                        <dt>Questões</dt>
                        <dd><?= (int)$composicao['total'] ?> <small><?= (int)$composicao['objetivas'] ?> objetivas · <?= (int)$composicao['dissertativas'] ?> dissertativas</small></dd>
                    </div>
                    <div>
                        <dt>Tempo</dt>
                        <dd><?= e($tempoLimiteTexto) ?></dd>
                    </div>
                    <div>
                        <dt>Modo</dt>
                        <dd>Monitorado</dd>
                    </div>
                </dl>

                <form method="POST" action="/avaliacao web/iniciar_avaliacao.php">
                    <input type="hidden" name="csrf_token" value="<?= e(gerarTokenCSRF()) ?>">
                    <button type="submit" class="btn btn-primary btn-lg" id="btn-iniciar">Iniciar avaliação</button>
                </form>

                <div class="aviso-importante">
                    <h4><?= icone('escudo', 15) ?> Regras da prova</h4>
                    <ul>
                        <li>O tempo começa a contar no instante em que a prova é iniciada</li>
                        <li>Não é permitido mudar de aba ou janela, nem abrir novas abas</li>
                        <li>Copiar e colar ficam bloqueados e vão para o relatório do professor</li>
                        <li>Sair da tela cheia, perder o foco ou desviar o olhar contam ocorrência</li>
                        <li>A prova é entregue automaticamente quando o tempo acaba</li>
                        <li>As questões dissertativas são corrigidas à mão pelo professor</li>
                    </ul>
                </div>
            </div>
        <?php endif; ?>

        <?php if ($ultimaAvaliacao): ?>
            <?php $ultimaFoiFraude = $ultimaAvaliacao['status'] === 'fraude'; ?>
            <div class="ultima-prova">
                <span class="badge <?= $ultimaFoiFraude ? 'badge-danger' : 'badge-success' ?>">
                    <?= $ultimaFoiFraude ? 'Encerrada' : 'Entregue' ?>
                </span>
                <span class="ultima-prova__nota"><?= number_format((float)($ultimaAvaliacao['nota'] ?? 0), 1, ',', '') ?></span>
                <span class="text-muted" style="font-size: 0.82rem;">
                    última prova · <?= formatarData($ultimaAvaliacao['inicio'], 'd/m/Y \à\s H:i') ?>
                    <?php if ($ultimaFoiFraude): ?>
                        · encerrada pelo acompanhamento
                    <?php endif; ?>
                </span>
                <a href="/avaliacao web/resultado.php?id=<?= (int)$ultimaAvaliacao['id'] ?>" class="btn btn-secondary btn-sm">Ver resultado</a>
            </div>
        <?php endif; ?>

        <div class="card info-card">
            <h3>Conteúdos da avaliação</h3>
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

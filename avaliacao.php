<?php
/**
 * Página da Avaliação - Sistema de Avaliação Acadêmica
 */

require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/functions.php';

requerAluno();

$usuarioId = $_SESSION['usuario_id'];
$avaliacao = obterAvaliacaoAtiva($usuarioId);

if (!$avaliacao) {
    flash('error', 'Nenhuma avaliação em andamento.');
    header('Location: /avaliacao web/dashboard.php');
    exit;
}

$avaliacaoId = $avaliacao['id'];
$tempoLimite = $avaliacao['tempo_limite_segundos'];
$inicio = $avaliacao['inicio'];
$tempoRestanteSeg = tempoRestante($inicio, $tempoLimite);

if ($tempoRestanteSeg <= 0) {
    finalizarAvaliacao($avaliacaoId);
    registrarEventoSuspeito($avaliacaoId, 'tempo_esgotado', 'Tempo limite atingido - finalização automática');
    flash('warning', 'Tempo esgotado! A avaliação foi finalizada automaticamente.');
    header('Location: /avaliacao web/resultado.php?id=' . $avaliacaoId);
    exit;
}

$questoes = obterQuestoesAvaliacao($avaliacaoId);

if (empty($questoes)) {
    flash('error', 'Não foi possível carregar as questões da prova.');
    header('Location: /avaliacao web/dashboard.php');
    exit;
}

$questoesIds = array_map(static fn($q) => (int)$q['id'], $questoes);

$rotulosTipo = [
    'multipla_escolha' => 'Múltipla Escolha',
    'verdadeiro_falso' => 'Verdadeiro ou Falso',
    'dissertativa' => 'Dissertativa',
];

// Salva uma resposta (alternativa ou texto dissertativo)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['acao'] ?? '') === 'salvar_resposta') {
    header('Content-Type: application/json');

    if (!validarTokenCSRF($_POST['csrf_token'] ?? '')) {
        echo json_encode(['success' => false, 'message' => 'Token inválido']);
        exit;
    }

    $questaoId = (int)($_POST['questao_id'] ?? 0);
    $alternativaId = !empty($_POST['alternativa_id']) ? (int)$_POST['alternativa_id'] : null;
    $respostaTexto = sanitizar((string)($_POST['resposta_texto'] ?? ''));
    $tempoResposta = (int)($_POST['tempo_resposta'] ?? 0);

    if ($questaoId <= 0 || !in_array($questaoId, $questoesIds, true)) {
        echo json_encode(['success' => false, 'message' => 'Dados inválidos']);
        exit;
    }

    $ok = salvarResposta(
        $avaliacaoId,
        $questaoId,
        $alternativaId,
        $respostaTexto !== '' ? $respostaTexto : null,
        $tempoResposta
    );
    echo json_encode(['success' => $ok]);
    exit;
}

// Finaliza a prova
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['acao'] ?? '') === 'finalizar') {
    if (!validarTokenCSRF($_POST['csrf_token'] ?? '')) {
        flash('error', 'Token de segurança inválido.');
        header('Location: /avaliacao web/avaliacao.php');
        exit;
    }

    // O anti-cola envia fraude=1 quando encerra a prova automaticamente
    $encerradaPorFraude = ($_POST['fraude'] ?? '') === '1';

    finalizarAvaliacao($avaliacaoId, $encerradaPorFraude);
    unset($_SESSION['questoes_avaliacao'][$avaliacaoId]);

    if ($encerradaPorFraude) {
        flash('warning', 'Avaliação encerrada por FRAUDE: você atingiu o limite de saídas/irregularidades permitidas.');
    } else {
        flash('success', 'Avaliação finalizada com sucesso!');
    }

    header('Location: /avaliacao web/concluido.php?id=' . $avaliacaoId);
    exit;
}

$totalQuestoes = count($questoes);
$totalDissertativas = 0;
foreach ($questoes as $q) {
    if ($q['tipo'] === 'dissertativa') {
        $totalDissertativas++;
    }
}
$totalObjetivas = $totalQuestoes - $totalDissertativas;
$flashes = obterFlashes();
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Avaliação - <?= e(APP_NAME) ?></title>
    <link rel="stylesheet" href="/avaliacao web/assets/css/style.css?v=<?= versaoEstatico('assets/css/style.css') ?>">
    <link rel="stylesheet" href="/avaliacao web/assets/css/avaliacao.css?v=<?= versaoEstatico('assets/css/avaliacao.css') ?>">
    <script>
        window.AVALIACAO_CONFIG = {
            avaliacaoId: <?= $avaliacaoId ?>,
            tempoLimite: <?= $tempoLimite ?>,
            tempoRestanteInicial: <?= $tempoRestanteSeg ?>,
            csrfToken: '<?= e(gerarTokenCSRF()) ?>',
            totalQuestoes: <?= $totalQuestoes ?>,
            limiteFaltas: <?= (int)LIMITE_FALTAS_ANTI_CHEAT ?>,
            baseUrl: '/avaliacao web'
        };
    </script>
</head>
<body class="avaliacao-page">
    <header class="avaliacao-header" role="banner">
        <div class="container header-content">
            <div class="header-left">
                <h1><?= e(APP_NAME) ?></h1>
                <span class="badge">Prova em Andamento</span>
            </div>
            <div class="header-center">
                <div class="timer-container" role="timer" aria-live="polite">
                    <svg class="timer-ring" viewBox="0 0 100 100">
                        <circle class="timer-ring-bg" cx="50" cy="50" r="45" />
                        <circle class="timer-ring-progress" id="timer-ring-progress" cx="50" cy="50" r="45" />
                    </svg>
                    <span class="timer-display" id="timer-display"><?= gmdate('H:i:s', $tempoRestanteSeg) ?></span>
                </div>
            </div>
            <div class="header-right">
                <div class="progress-info">
                    <span id="progress-text">Questão <span id="current-q">1</span> de <?= $totalQuestoes ?></span>
                    <div class="progress-bar">
                        <div class="progress-fill" id="progress-fill" style="width: <?= round(100 / $totalQuestoes) ?>%"></div>
                    </div>
                </div>
                <button type="button" class="btn btn-danger btn-finalizar" id="btn-finalizar" disabled>
                    Finalizar Avaliação
                </button>
            </div>
        </div>
    </header>

    <main class="avaliacao-main" role="main">
        <div class="container">
            <?php foreach ($flashes as $flash): ?>
                <div class="alert alert-<?= $flash['tipo'] === 'error' ? 'error' : ($flash['tipo'] === 'warning' ? 'warning' : 'success') ?>">
                    <?= e($flash['mensagem']) ?>
                </div>
            <?php endforeach; ?>

            <p class="prova-resumo">
                <?= $totalObjetivas ?> questões objetivas e <?= $totalDissertativas ?> dissertativas —
                as dissertativas são corrigidas manualmente pelo professor.
            </p>

            <aside class="questoes-nav" aria-label="Navegação das questões">
                <h3>Questões</h3>
                <div class="questoes-grid" id="questoes-nav-grid">
                    <?php foreach ($questoes as $index => $q): ?>
                        <button type="button"
                            class="questao-nav-btn <?= $index === 0 ? 'active' : '' ?> <?= $q['tipo'] === 'dissertativa' ? 'dissertativa' : '' ?>"
                            data-index="<?= $index ?>"
                            data-questao-id="<?= $q['id'] ?>"
                            title="<?= e($rotulosTipo[$q['tipo']] ?? $q['tipo']) ?>"
                            aria-label="Questão <?= $index + 1 ?>">
                            <?= $index + 1 ?>
                        </button>
                    <?php endforeach; ?>
                </div>
            </aside>

            <section class="questoes-area">
                <form id="form-avaliacao" novalidate>
                    <input type="hidden" name="csrf_token" value="<?= e(gerarTokenCSRF()) ?>">
                    <input type="hidden" name="acao" value="salvar_resposta">
                    <input type="hidden" name="avaliacao_id" value="<?= $avaliacaoId ?>">

                    <?php foreach ($questoes as $index => $q): ?>
                        <article class="questao-card <?= $index === 0 ? 'active' : '' ?>"
                                 data-index="<?= $index ?>"
                                 data-questao-id="<?= $q['id'] ?>"
                                 data-tipo="<?= e($q['tipo']) ?>"
                                 role="group"
                                 aria-labelledby="questao-<?= $index ?>-enunciado">
                            <header class="questao-header">
                                <h2 id="questao-<?= $index ?>-enunciado" class="questao-enunciado">
                                    <span class="questao-numero">Questão <?= $index + 1 ?></span>
                                    <?= e($q['enunciado']) ?>
                                </h2>
                                <span class="questao-tipo badge"><?= e($rotulosTipo[$q['tipo']] ?? $q['tipo']) ?></span>
                            </header>

                            <?php if ($q['tipo'] === 'dissertativa'): ?>
                                <div class="resposta-texto-container">
                                    <textarea class="resposta-texto-input"
                                              name="resposta_<?= $q['id'] ?>"
                                              data-questao-id="<?= $q['id'] ?>"
                                              rows="8"
                                              maxlength="4000"
                                              placeholder="Escreva sua resposta com suas próprias palavras..."></textarea>
                                    <small class="resposta-dica">Questão dissertativa: a resposta é enviada ao professor para correção.</small>
                                </div>
                            <?php else: ?>
                                <div class="alternativas-container">
                                    <?php foreach ($q['alternativas'] as $altIndex => $alt): ?>
                                        <label class="alternativa-item" data-alternativa-id="<?= $alt['id'] ?>">
                                            <input type="radio"
                                                   name="resposta_<?= $q['id'] ?>"
                                                   value="<?= $alt['id'] ?>"
                                                   class="alternativa-input"
                                                   data-questao-id="<?= $q['id'] ?>">
                                            <span class="alternativa-letra"><?= chr(65 + $altIndex) ?></span>
                                            <span class="alternativa-texto"><?= e($alt['texto']) ?></span>
                                            <span class="alternativa-check"></span>
                                        </label>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>

                            <div class="questao-footer">
                                <button type="button" class="btn btn-secondary btn-nav" data-nav="prev" <?= $index === 0 ? 'disabled' : '' ?>>Anterior</button>
                                <span class="questao-status" id="status-q<?= $index ?>">Não respondida</span>
                                <button type="button" class="btn btn-secondary btn-nav" data-nav="next" <?= $index === $totalQuestoes - 1 ? 'disabled' : '' ?>>Próxima</button>
                            </div>
                        </article>
                    <?php endforeach; ?>
                </form>
            </section>
        </div>
    </main>

    <div class="modal-overlay" id="modal-finalizar" role="dialog" aria-modal="true" aria-labelledby="modal-titulo" hidden>
        <div class="modal">
            <header class="modal-header">
                <h2 id="modal-titulo">Confirmar Finalização</h2>
            </header>
            <div class="modal-body">
                <p>Tem certeza que deseja finalizar a avaliação?</p>
                <p class="modal-warning">Após finalizar, não será possível alterar as respostas.</p>
                <div class="modal-stats" id="modal-stats">
                    <span>Respondidas: <strong id="count-respondidas">0</strong> de <?= $totalQuestoes ?></span>
                    <span>Não respondidas: <strong id="count-nao-respondidas"><?= $totalQuestoes ?></strong></span>
                </div>
            </div>
            <footer class="modal-footer">
                <button type="button" class="btn btn-secondary" id="modal-cancelar">Cancelar</button>
                <form method="POST" action="/avaliacao web/avaliacao.php" style="display:inline;">
                    <input type="hidden" name="acao" value="finalizar">
                    <input type="hidden" name="csrf_token" value="<?= e(gerarTokenCSRF()) ?>">
                    <button type="submit" class="btn btn-danger" id="modal-confirmar">Sim, Finalizar</button>
                </form>
            </footer>
        </div>
    </div>

    <script src="/avaliacao web/assets/js/avaliacao.js?v=<?= versaoEstatico('assets/js/avaliacao.js') ?>"></script>
    <!-- A webcam vem antes: o anti-cola só mostra o portão da tela cheia
         depois que a câmera estiver liberada. -->
    <script src="/avaliacao web/assets/js/webcam.js?v=<?= versaoEstatico('assets/js/webcam.js') ?>"></script>
    <script src="/avaliacao web/assets/js/anti-cheat.js?v=<?= versaoEstatico('assets/js/anti-cheat.js') ?>"></script>
    <script src="/avaliacao web/assets/js/paste-guard.js?v=<?= versaoEstatico('assets/js/paste-guard.js') ?>"></script>
</body>
</html>

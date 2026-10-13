<?php
/**
 * Dashboard Administrativo
 */

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/feedback_widget.php';

requerAdmin();

$stats = obterEstatisticas();
$avaliacoesRecent = array_slice(listarAvaliacoes(), 0, 10);
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin - <?= e(APP_NAME) ?></title>
    <link rel="stylesheet" href="/avaliacao web/assets/css/style.css?v=<?= versaoEstatico('assets/css/style.css') ?>">
    <link rel="stylesheet" href="/avaliacao web/assets/css/admin.css?v=<?= versaoEstatico('assets/css/admin.css') ?>">
</head>
<body class="admin-page">
    <header class="admin-header">
        <div class="container header-content">
            <h1><?= e(APP_NAME) ?></h1>
            <span class="badge admin-badge">Administrador</span>
            <div class="user-info">
                <span class="user-name"><?= e($_SESSION['usuario_nome']) ?></span>
                <a href="/avaliacao web/logout.php" class="btn btn-secondary btn-sm">Sair</a>
            </div>
        </div>
    </header>

    <div class="admin-container container">
        <aside class="admin-sidebar">
            <nav class="admin-nav">
                <h3>Menu</h3>
                <ul>
                    <li><a href="/avaliacao web/admin/dashboard.php" class="active">📊 Dashboard</a></li>
                    <li><a href="/avaliacao web/admin/questoes.php">📝 Questões</a></li>
                    <li><a href="/avaliacao web/admin/usuarios.php">👥 Usuários</a></li>
                    <li><a href="/avaliacao web/admin/avaliacoes.php">📋 Avaliações</a></li>
                    <li><a href="/avaliacao web/admin/eventos.php">🚨 Eventos Suspeitos</a></li>
                </ul>
                <hr style="margin: 1rem 0; border-color: var(--border-color);">
                <li><a href="/avaliacao web/dashboard.php" class="btn btn-outline">👁️ Ver como Aluno</a></li>
            </nav>
        </aside>

        <main class="admin-main">
            <div class="admin-header-title">
                <h2>Dashboard Administrativo</h2>
                <p>Visão geral do sistema</p>
            </div>

            <!-- Cards de Estatísticas -->
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-icon">👨‍🎓</div>
                    <div class="stat-info">
                        <span class="stat-value"><?= $stats['total_alunos'] ?? 0 ?></span>
                        <span class="stat-label">Alunos Cadastrados</span>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon">📝</div>
                    <div class="stat-info">
                        <span class="stat-value"><?= $stats['total_questoes'] ?? 0 ?></span>
                        <span class="stat-label">Questões Ativas</span>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon">📋</div>
                    <div class="stat-info">
                        <span class="stat-value"><?= $stats['total_avaliacoes'] ?? 0 ?></span>
                        <span class="stat-label">Total de Avaliações</span>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon">✅</div>
                    <div class="stat-info">
                        <span class="stat-value"><?= $stats['avaliacoes_finalizadas'] ?? 0 ?></span>
                        <span class="stat-label">Finalizadas</span>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon">⏳</div>
                    <div class="stat-info">
                        <span class="stat-value"><?= $stats['avaliacoes_em_andamento'] ?? 0 ?></span>
                        <span class="stat-label">Em Andamento</span>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon">🚨</div>
                    <div class="stat-info">
                        <span class="stat-value"><?= $stats['total_eventos_suspeitos'] ?? 0 ?></span>
                        <span class="stat-label">Eventos Suspeitos</span>
                    </div>
                </div>
                <div class="stat-card wide">
                    <div class="stat-icon">📊</div>
                    <div class="stat-info">
                        <span class="stat-value"><?= $stats['media_notas'] ?? 0 ?></span>
                        <span class="stat-label">Média de Notas</span>
                    </div>
                </div>
            </div>

            <!-- Avaliações Recentes -->
            <section class="admin-section">
                <div class="section-header">
                    <h3>Avaliações Recentes</h3>
                    <a href="/avaliacao web/admin/avaliacoes.php" class="btn btn-secondary btn-sm">Ver Todas</a>
                </div>
                <div class="table-responsive">
                    <table class="admin-table">
                        <thead>
                            <tr>
                                <th>Aluno</th>
                                <th>E-mail</th>
                                <th>Início</th>
                                <th>Fim</th>
                                <th>Status</th>
                                <th>Nota</th>
                                <th>Acertos/Erros</th>
                                <th>Ações</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($avaliacoesRecent as $av): ?>
                                <tr>
                                    <td><?= e($av['aluno_nome']) ?></td>
                                    <td><?= e($av['aluno_email']) ?></td>
                                    <td><?= formatarData($av['inicio']) ?></td>
                                    <td><?= $av['fim'] ? formatarData($av['fim']) : '—' ?></td>
                                    <td>
                                        <span class="badge <?= match($av['status']) {
                                            'finalizada' => 'success',
                                            'tempo_esgotado' => 'warning',
                                            'fraude' => 'danger',
                                            'em_andamento' => 'info',
                                            default => 'secondary'
                                        } ?>">
                                            <?= $av['status'] === 'fraude' ? 'Fraude' : ucfirst(str_replace('_', ' ', $av['status'])) ?>
                                        </span>
                                    </td>
                                    <td><?= $av['nota'] !== null ? number_format($av['nota'], 1) : '—' ?></td>
                                    <td><?= $av['acertos'] ?? 0 ?> / <?= $av['erros'] ?? 0 ?></td>
                                    <td>
                                        <a href="/avaliacao web/admin/ver_avaliacao.php?id=<?= $av['id'] ?>" class="btn btn-sm btn-outline">Ver</a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                            <?php if (empty($avaliacoesRecent)): ?>
                                <tr><td colspan="8" class="text-center">Nenhuma avaliação realizada ainda</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </section>

            <!-- Ações Rápidas -->
            <section class="admin-section">
                <h3>Ações Rápidas</h3>
                <div class="quick-actions">
                    <a href="/avaliacao web/admin/questoes.php?acao=novo" class="btn btn-primary">➕ Nova Questão</a>
                    <a href="/avaliacao web/admin/usuarios.php?acao=novo" class="btn btn-secondary">👤 Novo Usuário</a>
                    <a href="/avaliacao web/admin/eventos.php" class="btn btn-outline">🚨 Ver Eventos Suspeitos</a>
                </div>
            </section>

            <?php renderFeedbackWidget(); ?>
        </main>
    </div>

    <footer class="main-footer">
        <div class="container">
            <p>&copy; 2024 - Sistema de Avaliação Acadêmica - Área Administrativa</p>
        </div>
    </footer>
</body>
</html>
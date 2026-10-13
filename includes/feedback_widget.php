<?php
/**
 * Widget de avaliação do site (5 estrelas + comentário).
 * Usado no dashboard do aluno e no dashboard administrativo.
 */

require_once __DIR__ . '/functions.php';

if (!function_exists('renderFeedbackWidget')) {

    /**
     * Renderiza a área de avaliação do site.
     * @return void
     */
    function renderFeedbackWidget(): void
    {
        static $scriptIncluido = false;

        $usuarioId = (int)($_SESSION['usuario_id'] ?? 0);
        $feedbackAnterior = $usuarioId > 0 ? obterUltimoFeedback($usuarioId) : false;
        $estrelasAnteriores = $feedbackAnterior ? (int)$feedbackAnterior['estrelas'] : 0;
        ?>
        <section class="feedback-widget" aria-labelledby="feedback-titulo">
            <div class="feedback-widget__header">
                <h3 id="feedback-titulo">⭐ Avalie o site</h3>
                <p>Dê uma nota de 1 a 5 estrelas e conte o que você achou do sistema.</p>
            </div>

            <form class="feedback-form" id="feedback-form"
                  data-api="/avaliacao web/api/enviar_feedback.php"
                  data-csrf="<?= e(gerarTokenCSRF()) ?>"
                  novalidate>
                <div class="stars" id="feedback-stars" role="radiogroup" aria-label="Nota de 1 a 5 estrelas">
                    <?php for ($nota = 1; $nota <= 5; $nota++): ?>
                        <button type="button"
                                class="star"
                                data-valor="<?= $nota ?>"
                                role="radio"
                                aria-checked="false"
                                aria-label="<?= $nota ?> estrela<?= $nota > 1 ? 's' : '' ?>">★</button>
                    <?php endfor; ?>
                </div>
                <input type="hidden" name="estrelas" id="feedback-estrelas" value="0">

                <label class="sr-only" for="feedback-comentario">Comentário</label>
                <textarea id="feedback-comentario"
                          name="comentario"
                          rows="3"
                          maxlength="1000"
                          placeholder="Escreva seu comentário (opcional)"></textarea>

                <div class="feedback-form__actions">
                    <button type="submit" class="btn btn-primary">Enviar avaliação</button>
                    <p class="feedback-status" id="feedback-status" role="status" aria-live="polite"></p>
                </div>

                <?php if ($estrelasAnteriores > 0): ?>
                    <p class="feedback-ultimo">
                        Sua última avaliação: <strong><?= $estrelasAnteriores ?> estrela<?= $estrelasAnteriores > 1 ? 's' : '' ?></strong>
                    </p>
                <?php endif; ?>
            </form>
        </section>
        <?php

        if (!$scriptIncluido) {
            $scriptIncluido = true;
            ?>
            <script src="/avaliacao web/assets/js/feedback.js?v=<?= versaoEstatico('assets/js/feedback.js') ?>"></script>
            <?php
        }
    }
}

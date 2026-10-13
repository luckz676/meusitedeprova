/**
 * Guarda contra colar (Ctrl+V, Cmd+V, Shift+Insert, menu do navegador e toque).
 * Escurece a tela e mostra um gif de alerta, alternando entre dois gifs.
 *
 * Na página da prova quem cuida disso é o anti-cheat.js (window.AntiCheat.avisoColagem);
 * em outras páginas (como a tela de login) este arquivo mostra o aviso sozinho.
 */

(function() {
    'use strict';

    const BASE_URL = (window.AVALIACAO_CONFIG && window.AVALIACAO_CONFIG.baseUrl) || '/avaliacao web';

    // Loop de dois gifs: 1ª tentativa silly-tamamo, 2ª scary-oguri, e volta.
    // Sobre o anti-cheat.js este loop é compartilhado via indiceLoop; aqui é independente
    // (páginas que não são a prova usam apenas este arquivo).
    const AVISOS = [
        { gif: `${BASE_URL}/assets/gifs/silly-tamamo.gif`, texto: 'cole não meu rei, desista e morra' },
        { gif: `${BASE_URL}/assets/gifs/scary-oguri.gif`, texto: 'O QUE EU TE FALEI AGORA?' }
    ];

    const DURACAO_MS = 4000;

    let tentativas = 0;
    let overlay = null;
    let gifEl = null;
    let textoEl = null;
    let esconderTimer = null;

    function criarOverlay() {
        if (overlay && document.body.contains(overlay)) return overlay;

        overlay = document.createElement('div');
        // Aviso leve: escurece a tela só um pouco e não captura clique.
        overlay.className = 'gif-overlay gif-overlay--leve';
        overlay.id = 'paste-overlay';
        overlay.setAttribute('role', 'alertdialog');
        overlay.setAttribute('aria-live', 'assertive');

        const inner = document.createElement('div');
        inner.className = 'gif-overlay__inner';

        textoEl = document.createElement('p');
        textoEl.className = 'gif-overlay__texto';

        gifEl = document.createElement('img');
        gifEl.className = 'gif-overlay__gif';
        gifEl.alt = 'Alerta: colar não é permitido';

        inner.appendChild(textoEl);
        inner.appendChild(gifEl);
        overlay.appendChild(inner);
        document.body.appendChild(overlay);

        return overlay;
    }

    function esconder() {
        if (!overlay) return;
        clearTimeout(esconderTimer);
        overlay.classList.remove('is-visible');
        if (gifEl) gifEl.removeAttribute('src');
    }

    /** Mostra o aviso da tentativa atual (alterna entre os dois gifs) */
    function mostrarAviso() {
        criarOverlay();

        const aviso = AVISOS[tentativas % AVISOS.length];
        tentativas += 1;

        textoEl.textContent = aviso.texto;
        gifEl.src = aviso.gif;
        overlay.classList.add('is-visible');

        clearTimeout(esconderTimer);
        esconderTimer = setTimeout(esconder, DURACAO_MS);
    }

    document.addEventListener('paste', (evento) => {
        evento.preventDefault();
        evento.stopPropagation();

        const descricao = 'Tentativa de colar bloqueada (Ctrl+V, Shift+Insert ou menu do navegador)';

        if (window.AntiCheat && typeof window.AntiCheat.avisoColagem === 'function') {
            window.AntiCheat.avisoColagem(descricao);
            return;
        }

        mostrarAviso();
    }, true);

    window.PasteGuard = { mostrarAviso, esconder };
})();

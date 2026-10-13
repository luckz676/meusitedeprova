/**
 * Avaliação do site: 5 estrelas, comentário e reação em gif ao enviar.
 */

(function() {
    'use strict';

    const form = document.getElementById('feedback-form');
    if (!form) return;

    const baseUrl = '/avaliacao web';
    const estrelasWrap = document.getElementById('feedback-stars');
    const inputEstrelas = document.getElementById('feedback-estrelas');
    const comentarioEl = document.getElementById('feedback-comentario');
    const statusEl = document.getElementById('feedback-status');
    const botoesEstrela = Array.from(estrelasWrap.querySelectorAll('.star'));

    const DURACAO_REACAO_MS = 4500;

    const REACOES = {
        ruim: {
            gifs: [`${baseUrl}/assets/gifs/rice-shower-punching.gif`, `${baseUrl}/assets/gifs/angry-oguri.gif`],
            texto: 'ARGHHH PORQUE?????'
        },
        bom: {
            gifs: [`${baseUrl}/assets/gifs/shy-oguri.gif`, `${baseUrl}/assets/gifs/heart-rice-shower.gif`],
            texto: 'Você tem bom gosto, parabéns'
        }
    };

    let notaSelecionada = parseInt(inputEstrelas.value, 10) || 0;
    let overlayReacao = null;
    let reacaoTimer = null;

    function pintarEstrelas(ate) {
        botoesEstrela.forEach((botao) => {
            const valor = parseInt(botao.dataset.valor, 10);
            const ativa = valor <= ate;
            botao.classList.toggle('ativa', ativa);
            botao.setAttribute('aria-checked', valor === notaSelecionada ? 'true' : 'false');
        });
    }

    function selecionarNota(nota) {
        notaSelecionada = nota;
        inputEstrelas.value = String(nota);
        pintarEstrelas(nota);
    }

    botoesEstrela.forEach((botao) => {
        const valor = parseInt(botao.dataset.valor, 10);

        botao.addEventListener('click', () => selecionarNota(valor));
        botao.addEventListener('mouseenter', () => pintarEstrelas(valor));
    });

    estrelasWrap.addEventListener('mouseleave', () => pintarEstrelas(notaSelecionada));

    botoesEstrela.forEach((botao) => {
        botao.addEventListener('keydown', (evento) => {
            if (evento.key === 'Enter' || evento.key === ' ') {
                evento.preventDefault();
                selecionarNota(parseInt(botao.dataset.valor, 10));
            }
        });
    });

    function criarOverlayReacao() {
        if (overlayReacao) return overlayReacao;

        overlayReacao = document.createElement('div');
        overlayReacao.className = 'gif-overlay';
        overlayReacao.setAttribute('role', 'alertdialog');
        overlayReacao.setAttribute('aria-live', 'assertive');

        const inner = document.createElement('div');
        inner.className = 'gif-overlay__inner';

        const texto = document.createElement('p');
        texto.className = 'gif-overlay__texto';
        texto.id = 'reacao-texto';

        const gifs = document.createElement('div');
        gifs.className = 'gif-overlay__gifs';

        for (let i = 0; i < 2; i++) {
            const img = document.createElement('img');
            img.className = 'gif-overlay__gif';
            img.alt = 'Reação à avaliação do site';
            img.dataset.indice = String(i);
            gifs.appendChild(img);
        }

        inner.appendChild(texto);
        inner.appendChild(gifs);
        overlayReacao.appendChild(inner);
        document.body.appendChild(overlayReacao);

        return overlayReacao;
    }

    function mostrarReacao(estrelas) {
        criarOverlayReacao();

        const reacao = estrelas <= 3 ? REACOES.ruim : REACOES.bom;

        overlayReacao.querySelector('#reacao-texto').textContent = reacao.texto;
        overlayReacao.querySelectorAll('.gif-overlay__gif').forEach((img) => {
            img.src = reacao.gifs[parseInt(img.dataset.indice, 10)] || reacao.gifs[0];
        });

        overlayReacao.classList.add('is-visible');

        clearTimeout(reacaoTimer);
        reacaoTimer = setTimeout(() => {
            overlayReacao.classList.remove('is-visible');
            overlayReacao.querySelectorAll('.gif-overlay__gif').forEach((img) => img.removeAttribute('src'));
        }, DURACAO_REACAO_MS);
    }

    form.addEventListener('submit', (evento) => {
        evento.preventDefault();

        if (notaSelecionada < 1) {
            statusEl.textContent = 'Escolha de 1 a 5 estrelas antes de enviar.';
            statusEl.classList.add('erro');
            return;
        }

        const botao = form.querySelector('button[type="submit"]');
        botao.disabled = true;
        statusEl.classList.remove('erro');
        statusEl.textContent = 'Enviando...';

        fetch(form.dataset.api, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
                'X-CSRF-Token': form.dataset.csrf
            },
            credentials: 'same-origin',
            body: new URLSearchParams({
                estrelas: String(notaSelecionada),
                comentario: comentarioEl.value
            })
        })
        .then((resposta) => resposta.json())
        .then((dados) => {
            botao.disabled = false;

            if (!dados.success) {
                statusEl.textContent = dados.message || 'Não foi possível enviar sua avaliação.';
                statusEl.classList.add('erro');
                return;
            }

            statusEl.textContent = 'Obrigado pelo seu feedback!';
            comentarioEl.value = '';
            mostrarReacao(notaSelecionada);
        })
        .catch(() => {
            botao.disabled = false;
            statusEl.textContent = 'Erro de conexão ao enviar a avaliação.';
            statusEl.classList.add('erro');
        });
    });

    window.FeedbackWidget = { selecionarNota, mostrarReacao };
})();

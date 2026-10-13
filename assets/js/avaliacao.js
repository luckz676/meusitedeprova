/**
 * Lógica da Avaliação - Navegação, Timer, Salvamento Automático
 */

(function() {
    'use strict';

    const config = window.AVALIACAO_CONFIG || {};
    const avaliacaoId = config.avaliacaoId;
    const tempoLimite = config.tempoLimite;
    let tempoRestante = config.tempoRestanteInicial;
    const csrfToken = config.csrfToken;
    const baseUrl = config.baseUrl;
    const totalQuestoes = config.totalQuestoes;

    const timerDisplay = document.getElementById('timer-display');
    const timerRingProgress = document.getElementById('timer-ring-progress');
    const progressFill = document.getElementById('progress-fill');
    const progressText = document.getElementById('progress-text');
    const currentQEl = document.getElementById('current-q');
    const btnFinalizar = document.getElementById('btn-finalizar');
    const modalFinalizar = document.getElementById('modal-finalizar');
    const modalCancelar = document.getElementById('modal-cancelar');
    const modalConfirmar = document.getElementById('modal-confirmar');
    const countRespondidas = document.getElementById('count-respondidas');
    const countNaoRespondidas = document.getElementById('count-nao-respondidas');
    const formAvaliacao = document.getElementById('form-avaliacao');
    const questaoCards = document.querySelectorAll('.questao-card');
    const navBtns = document.querySelectorAll('.questao-nav-btn');
    const navPrevBtns = document.querySelectorAll('[data-nav="prev"]');
    const navNextBtns = document.querySelectorAll('[data-nav="next"]');

    let questaoAtual = 0;
    let questoesRespondidas = new Set();
    let timerInterval = null;
    const timersTexto = new Map();
    let tempoInicioQuestao = Date.now();

    const CIRCUNFERENCIA = 2 * Math.PI * 45;
    timerRingProgress.style.strokeDasharray = CIRCUNFERENCIA;
    timerRingProgress.style.strokeDashoffset = 0;

    function iniciarTimer() {
        atualizarTimerDisplay();

        timerInterval = setInterval(() => {
            tempoRestante--;
            atualizarTimerDisplay();

            if (tempoRestante <= 0) {
                clearInterval(timerInterval);
                tempoEsgotado();
            }
        }, 1000);
    }

    function atualizarTimerDisplay() {
        if (!timerDisplay) return;

        const h = String(Math.floor(tempoRestante / 3600)).padStart(2, '0');
        const m = String(Math.floor((tempoRestante % 3600) / 60)).padStart(2, '0');
        const s = String(tempoRestante % 60).padStart(2, '0');
        timerDisplay.textContent = `${h}:${m}:${s}`;

        const progresso = 1 - (tempoRestante / tempoLimite);
        const offset = CIRCUNFERENCIA * progresso;
        timerRingProgress.style.strokeDashoffset = offset;

        timerRingProgress.classList.remove('warning', 'danger');
        if (tempoRestante <= 300) {
            timerRingProgress.classList.add('danger');
            timerDisplay.classList.add('danger');
        } else if (tempoRestante <= 600) {
            timerRingProgress.classList.add('warning');
            timerDisplay.classList.add('warning');
        }
    }

    function tempoEsgotado() {
        const formFinalizar = document.createElement('form');
        formFinalizar.method = 'POST';
        formFinalizar.action = `${baseUrl}/avaliacao.php`;
        formFinalizar.innerHTML = `
            <input type="hidden" name="acao" value="finalizar">
            <input type="hidden" name="csrf_token" value="${csrfToken}">
        `;
        document.body.appendChild(formFinalizar);
        formFinalizar.submit();
    }

    function mostrarQuestao(indice) {
        if (indice < 0 || indice >= totalQuestoes) return;

        questaoCards[questaoAtual].classList.remove('active');
        navBtns[questaoAtual].classList.remove('active');

        questaoAtual = indice;
        questaoCards[questaoAtual].classList.add('active');
        navBtns[questaoAtual].classList.add('active');

        currentQEl.textContent = questaoAtual + 1;
        atualizarBotoesNavegacao();
        atualizarProgresso();
        scrollParaQuestao();

        tempoInicioQuestao = Date.now();
    }

    function atualizarBotoesNavegacao() {
        const prevBtn = questaoCards[questaoAtual].querySelector('[data-nav="prev"]');
        const nextBtn = questaoCards[questaoAtual].querySelector('[data-nav="next"]');

        if (prevBtn) prevBtn.disabled = questaoAtual === 0;
        if (nextBtn) nextBtn.disabled = questaoAtual === totalQuestoes - 1;
    }

    function scrollParaQuestao() {
        const card = questaoCards[questaoAtual];
        card.scrollIntoView({ behavior: 'smooth', block: 'start' });
    }

    navPrevBtns.forEach(btn => {
        btn.addEventListener('click', () => mostrarQuestao(questaoAtual - 1));
    });

    navNextBtns.forEach(btn => {
        btn.addEventListener('click', () => mostrarQuestao(questaoAtual + 1));
    });

    navBtns.forEach((btn, index) => {
        btn.addEventListener('click', () => mostrarQuestao(index));
    });

    document.addEventListener('keydown', (e) => {
        if (e.target.tagName === 'INPUT' || e.target.tagName === 'TEXTAREA') return;

        if (e.key === 'ArrowLeft' && questaoAtual > 0) {
            mostrarQuestao(questaoAtual - 1);
        } else if (e.key === 'ArrowRight' && questaoAtual < totalQuestoes - 1) {
            mostrarQuestao(questaoAtual + 1);
        }
    });

    // Questões objetivas (múltipla escolha / verdadeiro-falso)
    document.querySelectorAll('.alternativa-input').forEach(input => {
        input.addEventListener('change', function() {
            const questaoId = this.dataset.questaoId;
            const alternativaId = this.value;
            const tempoDecorrido = Math.floor((Date.now() - tempoInicioQuestao) / 1000);

            salvarResposta(questaoId, alternativaId, tempoDecorrido);
            marcarComoRespondida(questaoId);
            atualizarProgresso();
            atualizarStatusQuestao(questaoId);
            verificarBotaoFinalizar();
        });
    });

    // Questões dissertativas (texto salvo automaticamente enquanto escreve)
    function aplicarEstadoDissertativa(questaoId, texto) {
        if (texto.trim() !== '') {
            marcarComoRespondida(questaoId);
        } else {
            desmarcarRespondida(questaoId);
        }

        atualizarProgresso();
        atualizarStatusQuestao(questaoId);
        verificarBotaoFinalizar();
    }

    document.querySelectorAll('.resposta-texto-input').forEach(area => {
        area.addEventListener('input', function() {
            const questaoId = this.dataset.questaoId;
            const textoAtual = this.value;

            clearTimeout(timersTexto.get(questaoId));
            timersTexto.set(questaoId, setTimeout(() => {
                const tempoDecorrido = Math.floor((Date.now() - tempoInicioQuestao) / 1000);
                salvarResposta(questaoId, null, tempoDecorrido, textoAtual);
                aplicarEstadoDissertativa(questaoId, textoAtual);
            }, 900));
        });

        // Ao sair do campo, salva imediatamente o que foi escrito
        area.addEventListener('blur', function() {
            const questaoId = this.dataset.questaoId;
            const textoAtual = this.value;

            clearTimeout(timersTexto.get(questaoId));
            timersTexto.delete(questaoId);

            const tempoDecorrido = Math.floor((Date.now() - tempoInicioQuestao) / 1000);
            salvarResposta(questaoId, null, tempoDecorrido, textoAtual);
            aplicarEstadoDissertativa(questaoId, textoAtual);
        });
    });

    function salvarResposta(questaoId, alternativaId, tempoResposta, respostaTexto) {
        return fetch(`${baseUrl}/avaliacao.php`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            credentials: 'same-origin',
            body: new URLSearchParams({
                acao: 'salvar_resposta',
                csrf_token: csrfToken,
                questao_id: questaoId,
                alternativa_id: alternativaId || '',
                resposta_texto: respostaTexto || '',
                tempo_resposta: tempoResposta
            })
        })
        .then(res => res.json())
        .then(data => {
            if (!data.success) {
                console.error('Erro ao salvar resposta:', data.message);
            }
        })
        .catch(err => console.error('Erro de rede ao salvar:', err));
    }

    // Autosave periódico da questão visível
    setInterval(() => {
        const card = questaoCards[questaoAtual];
        const questaoId = card.dataset.questaoId;
        const tempoDecorrido = Math.floor((Date.now() - tempoInicioQuestao) / 1000);

        if (card.dataset.tipo === 'dissertativa') {
            const area = card.querySelector('.resposta-texto-input');
            if (area && area.value.trim() !== '') {
                salvarResposta(questaoId, null, tempoDecorrido, area.value);
            }
            return;
        }

        const inputSelecionado = card.querySelector('.alternativa-input:checked');
        if (inputSelecionado) {
            salvarResposta(questaoId, inputSelecionado.value, tempoDecorrido);
        }
    }, 30000);

    function marcarComoRespondida(questaoId) {
        questoesRespondidas.add(String(questaoId));
        const btn = document.querySelector(`.questao-nav-btn[data-questao-id="${questaoId}"]`);
        if (btn) btn.classList.add('respondida');
    }

    function desmarcarRespondida(questaoId) {
        questoesRespondidas.delete(String(questaoId));
        const btn = document.querySelector(`.questao-nav-btn[data-questao-id="${questaoId}"]`);
        if (btn) btn.classList.remove('respondida');
    }

    function atualizarProgresso() {
        const respondidas = questoesRespondidas.size;
        const porcentagem = (respondidas / totalQuestoes) * 100;

        if (progressFill) progressFill.style.width = `${porcentagem}%`;
        if (progressText) progressText.textContent = `Questão ${questaoAtual + 1} de ${totalQuestoes}`;
    }

    function atualizarStatusQuestao(questaoId) {
        const statusEl = document.getElementById(`status-q${Array.from(questaoCards).findIndex(c => c.dataset.questaoId == questaoId)}`);
        if (!statusEl) return;

        if (questoesRespondidas.has(String(questaoId))) {
            statusEl.textContent = 'Respondida ✓';
            statusEl.classList.add('respondida');
        } else {
            statusEl.textContent = 'Não respondida';
            statusEl.classList.remove('respondida');
        }
    }

    function atualizarContadoresModal() {
        const respondidas = questoesRespondidas.size;
        if (countRespondidas) countRespondidas.textContent = respondidas;
        if (countNaoRespondidas) countNaoRespondidas.textContent = totalQuestoes - respondidas;
    }

    function verificarBotaoFinalizar() {
        if (btnFinalizar) {
            btnFinalizar.disabled = questoesRespondidas.size === 0;
        }
    }

    if (btnFinalizar) {
        btnFinalizar.addEventListener('click', () => {
            atualizarContadoresModal();
            modalFinalizar.hidden = false;
            document.body.style.overflow = 'hidden';
        });
    }

    if (modalCancelar) {
        modalCancelar.addEventListener('click', fecharModal);
    }

    function fecharModal() {
        modalFinalizar.hidden = true;
        document.body.style.overflow = '';
    }

    document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape' && !modalFinalizar.hidden) {
            fecharModal();
        }
    });

    async function carregarRespostasSalvas() {
        try {
            const res = await fetch(`${baseUrl}/api/obter_respostas.php?avaliacao_id=${avaliacaoId}`, {
                credentials: 'same-origin'
            });
            const data = await res.json();
            if (data.success && data.respostas) {
                data.respostas.forEach(r => {
                    if (r.alternativa_id) {
                        const input = document.querySelector(`input[name="resposta_${r.questao_id}"][value="${r.alternativa_id}"]`);
                        if (input) {
                            input.checked = true;
                            marcarComoRespondida(r.questao_id);
                            atualizarStatusQuestao(r.questao_id);
                        }
                        return;
                    }

                    if (r.resposta_texto) {
                        const area = document.querySelector(`.resposta-texto-input[data-questao-id="${r.questao_id}"]`);
                        if (area) {
                            area.value = r.resposta_texto;
                            marcarComoRespondida(r.questao_id);
                            atualizarStatusQuestao(r.questao_id);
                        }
                    }
                });
                atualizarProgresso();
                verificarBotaoFinalizar();
            }
        } catch (e) {
            console.log('Sem respostas salvas ou erro:', e);
        }
    }

    carregarRespostasSalvas().then(() => {
        iniciarTimer();
        verificarBotaoFinalizar();
        console.log('✅ Avaliação carregada e timer iniciado');
    });

    window.AvaliacaoApp = {
        mostrarQuestao,
        tempoRestante: () => tempoRestante,
        questoesRespondidas: () => questoesRespondidas.size
    };
})();
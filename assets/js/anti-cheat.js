/**
 * Sistema Anti-Cola - Mecanismos de Controle da Avaliação
 *
 * O que este arquivo faz:
 *  - Bloqueia atalhos perigosos, copiar/colar, botão direito, seleção e arrastar
 *  - Detecta e denuncia troca de aba/janela, saída da tela cheia e DevTools
 *  - Mostra um gif de alerta (em tela cheia, escurecendo o site) a cada violação
 *  - Registra tudo no banco (eventos_suspeitos)
 *  - Conta as faltas graves e finaliza a prova ao atingir o limite
 *
 * Limitação do navegador (não dá para bloquear por código):
 *  teclas do próprio Windows (Win+X, Alt+Tab), troca de aba pelo mouse,
 *  a tecla Esc que sai da tela cheia e o fechamento da janela.
 *  Nesses casos o sistema detecta, avisa com o gif, conta falta e registra.
 */

(function() {
    'use strict';

    const config = window.AVALIACAO_CONFIG || {};
    const avaliacaoId = config.avaliacaoId;
    const baseUrl = config.baseUrl || '/avaliacao web';
    const limiteFaltas = parseInt(config.limiteFaltas, 10) || 0; // 0 = sem limite
    const DURACAO_AVISO_MS = 4500;
    const COOLDOWN_PADRAO_MS = 1500;

    const gif = (nome) => `${baseUrl}/assets/gifs/${nome}`;

    // O loop de dois gifs: 1ª tentativa silly-tamamo, 2ª scary-oguri, e volta.
    // É sempre este mesmo par (gif + frase) para todas as violações de cópia/
    // colagem, seleção, teclas e botão direito. Nas teclas bloqueadas o loop
    // mostra a frase e o texto de baixo diz qual tecla foi apertada.
    const LOOP_AVISOS = [
        { gif: gif('silly-tamamo.gif'), texto: 'cole não meu rei, desista e morra' },
        { gif: gif('scary-oguri.gif'), texto: 'O QUE EU TE FALEI AGORA?' }
    ];

    // Textos e gifs de cada tipo de violação
    const AVISOS = {
        colar: LOOP_AVISOS,
        copiar: LOOP_AVISOS,
        tecla: LOOP_AVISOS,
        selecao: LOOP_AVISOS,
        contexto: LOOP_AVISOS,
        aba: { gif: gif('scary-oguri.gif'), texto: 'VOLTE PARA A PROVA AGORA!', bloqueia: true },
        janela: { gif: gif('scary-oguri.gif'), texto: 'VOLTE PARA A PROVA AGORA!', bloqueia: true },
        telaCheia: { gif: gif('angry-oguri.gif'), texto: 'TELA CHEIA É OBRIGATÓRIA!', bloqueia: true },
        devtools: { gif: gif('angry-oguri.gif'), texto: 'FECHE AS FERRAMENTAS DE DESENVOLVEDOR!', bloqueia: true },
        windows: { gif: gif('angry-oguri.gif'), texto: 'A TECLA WINDOWS ESTÁ BLOQUEADA!' },
        fraude: { gif: gif('angry-oguri.gif'), texto: 'PROVA FINALIZADA POR FRAUDE!', bloqueia: true }
    };

    /* ============= PRÉ-CARGA DOS GIFS ============= */

    // Os gifs são grandes (até 6 MB). Sem pré-carga o aviso aparece sem imagem
    // por alguns segundos e parece que o anti-cola não funcionou.
    const urlsDosGifs = new Set();
    Object.keys(AVISOS).forEach((chave) => {
        const aviso = AVISOS[chave];
        (Array.isArray(aviso) ? aviso : [aviso]).forEach((item) => {
            if (item && item.gif) urlsDosGifs.add(item.gif);
        });
    });
    urlsDosGifs.forEach((url) => {
        const img = new Image();
        img.src = url;
    });

    let faltas = 0;
    let indiceColar = 0;
    let indiceLoop = 0; // índice compartilhado do loop de dois gifs (silly-tamamo / scary-oguri)
    let finalizando = false;
    let foraDaProva = false;
    let supressaoTelaCheiaAte = 0;
    const cooldowns = {};

    let overlay = null;
    let gifEl = null;
    let tituloEl = null;
    let detalheEl = null;
    let botaoEl = null;
    let esconderTimer = null;

    // Barra de status: deixa o anti-cola visivel durante toda a prova
    let statusEl = null;
    let statusFaltasEl = null;
    let statusTelaCheiaEl = null;
    let statusBotaoEl = null;

    // Portao de entrada: exige tela cheia antes de comecar a prova
    let portaoEl = null;
    let telaCheiaNegada = false;

    /* ============= REGISTRO NO BANCO ============= */

    function registrarEvento(tipoEvento, descricao = '') {
        fetch(`${baseUrl}/api/registrar_evento.php`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-Token': config.csrfToken
            },
            credentials: 'same-origin',
            body: JSON.stringify({
                avaliacao_id: avaliacaoId,
                tipo_evento: tipoEvento,
                descricao: descricao,
                timestamp: new Date().toISOString(),
                user_agent: navigator.userAgent,
                url: window.location.href
            })
        }).catch((err) => console.error('Erro ao registrar evento:', err));

        registrarNoHistorico(tipoEvento, descricao);
    }

    /** Guarda uma cópia local para enviar depois, caso a requisição falhe */
    const historicoLocal = [];
    function registrarNoHistorico(tipoEvento, descricao) {
        historicoLocal.push({ tipoEvento, descricao, hora: new Date().toISOString() });
        if (historicoLocal.length > 200) historicoLocal.shift();
    }

    /* ============= OVERLAY COM GIF ============= */

    function criarOverlay() {
        if (overlay && document.body.contains(overlay)) return overlay;

        overlay = document.createElement('div');
        overlay.className = 'gif-overlay';
        overlay.id = 'anti-cheat-overlay';
        overlay.setAttribute('role', 'alertdialog');
        overlay.setAttribute('aria-live', 'assertive');

        const inner = document.createElement('div');
        inner.className = 'gif-overlay__inner';

        tituloEl = document.createElement('p');
        tituloEl.className = 'gif-overlay__texto';

        gifEl = document.createElement('img');
        gifEl.className = 'gif-overlay__gif';
        gifEl.alt = 'Alerta do sistema anti-cola';

        detalheEl = document.createElement('p');
        detalheEl.className = 'gif-overlay__detalhe';

        botaoEl = document.createElement('button');
        botaoEl.type = 'button';
        botaoEl.className = 'btn btn-primary gif-overlay__botao';
        botaoEl.textContent = 'Voltar para a prova';
        botaoEl.hidden = true;
        botaoEl.addEventListener('click', () => {
            esconderOverlay();
            pedirTelaCheia();
        });

        inner.appendChild(tituloEl);
        inner.appendChild(gifEl);
        inner.appendChild(detalheEl);
        inner.appendChild(botaoEl);
        overlay.appendChild(inner);
        document.body.appendChild(overlay);

        return overlay;
    }

    function esconderOverlay() {
        if (!overlay) return;
        clearTimeout(esconderTimer);
        overlay.classList.remove('is-visible');
        if (gifEl) gifEl.removeAttribute('src');
    }

    /**
     * @param {object} aviso  {gif, texto, bloqueia}
     * @param {string} detalhe
     */
    function exibirOverlay(aviso, detalhe) {
        // Garante que só exista um overlay na tela (o paste-guard também cria um).
        // O portão da webcam fica de fora: ele é recriado pelo webcam.js.
        document.querySelectorAll('.gif-overlay').forEach((el) => {
            if (el !== overlay && el !== portaoEl && el.id !== 'webcam-portao') el.remove();
        });

        criarOverlay();

        tituloEl.textContent = aviso.texto;
        gifEl.src = aviso.gif;
        detalheEl.textContent = detalhe || '';
        botaoEl.hidden = !aviso.bloqueia;

        // Avisos leves (colar, tecla, copiar, seleção) escurecem só um pouco e
        // NÃO capturam clique: sem isso a camada escura engolia o clique e o
        // aluno não conseguia marcar a alternativa.
        overlay.classList.toggle('gif-overlay--leve', !aviso.bloqueia);
        overlay.classList.add('is-visible');
        atualizarBarraStatus();

        clearTimeout(esconderTimer);
        if (!aviso.bloqueia) {
            esconderTimer = setTimeout(esconderOverlay, DURACAO_AVISO_MS);
        }
    }

    /* ============= BARRA DE STATUS (anti-cola visível) ============= */

    function criarBarraStatus() {
        if (statusEl && document.body.contains(statusEl)) return statusEl;

        statusEl = document.createElement('div');
        statusEl.className = 'anti-cheat-status';
        statusEl.id = 'anti-cheat-status';
        statusEl.setAttribute('role', 'status');
        statusEl.setAttribute('aria-live', 'polite');
        statusEl.innerHTML = `
            <span class='anti-cheat-status__titulo'>🛡️ Anti-cola ativo</span>
            <span class='anti-cheat-status__linha' id='anti-cheat-status-faltas'></span>
            <span class='anti-cheat-status__linha' id='anti-cheat-status-tela'></span>
            <button type='button' class='btn btn-primary anti-cheat-status__botao' id='anti-cheat-status-btn' hidden>Voltar à tela cheia</button>
        `;
        document.body.appendChild(statusEl);

        statusFaltasEl = statusEl.querySelector('#anti-cheat-status-faltas');
        statusTelaCheiaEl = statusEl.querySelector('#anti-cheat-status-tela');
        statusBotaoEl = statusEl.querySelector('#anti-cheat-status-btn');

        statusBotaoEl.addEventListener('click', (evento) => {
            evento.stopPropagation();
            pedirTelaCheia();
        });

        return statusEl;
    }

    function atualizarBarraStatus() {
        criarBarraStatus();

        const emTelaCheia = !!document.fullscreenElement;
        const pertoDoLimite = limiteFaltas > 0 && faltas >= limiteFaltas - 1;

        statusFaltasEl.textContent = limiteFaltas > 0
            ? `Faltas graves: ${faltas} de ${limiteFaltas}`
            : `Faltas graves: ${faltas}`;
        statusTelaCheiaEl.textContent = emTelaCheia ? '🖥️ Tela cheia: ativa' : '⚠️ Tela cheia: saiu!';
        statusBotaoEl.hidden = emTelaCheia;

        statusEl.classList.toggle('anti-cheat-status--alerta', !emTelaCheia || pertoDoLimite);
    }

    /* ============= PORTÃO DE TELA CHEIA ============= */

    function mostrarPortao() {
        if (finalizando || document.fullscreenElement) return;

        if (!portaoEl) {
            portaoEl = document.createElement('div');
            portaoEl.className = 'gif-overlay anti-cheat-portao';
            portaoEl.id = 'anti-cheat-portao';
            portaoEl.setAttribute('role', 'alertdialog');
            portaoEl.innerHTML = `
                <div class='gif-overlay__inner'>
                    <p class='gif-overlay__texto'>MODO PROVA — TELA CHEIA OBRIGATÓRIA</p>
                    <img class='gif-overlay__gif' src='${gif('spinning-oguri.gif')}' alt='Modo prova'>
                    <p class='gif-overlay__detalhe'>
                        A prova roda em tela cheia. Sair da tela cheia, trocar de aba ou perder o foco
                        da janela mostra este aviso e conta <strong>falta grave</strong>.
                        ${limiteFaltas > 0 ? `Com ${limiteFaltas} faltas a prova é encerrada por fraude.` : ''}
                    </p>
                    <button type='button' class='btn btn-primary gif-overlay__botao' id='anti-cheat-portao-btn'>Entrar em tela cheia</button>
                </div>
            `;
            document.body.appendChild(portaoEl);

            portaoEl.querySelector('#anti-cheat-portao-btn').addEventListener('click', () => {
                pedirTelaCheia().then((ok) => {
                    if (ok || document.fullscreenElement) {
                        esconderPortao();
                        atualizarBarraStatus();
                        return;
                    }
                    // O navegador recusou a tela cheia: não trava o aluno, registra no
                    // relatório e segue com a barra de alerta em vez de um beco sem saída.
                    telaCheiaNegada = true;
                    registrarEvento('tela_cheia_negada', 'O navegador recusou entrar em tela cheia no início da prova');
                    esconderPortao();
                    atualizarBarraStatus();
                });
            });
        }

        portaoEl.classList.add('is-visible');
        atualizarBarraStatus();
    }

    function esconderPortao() {
        if (!portaoEl) return;
        portaoEl.classList.remove('is-visible');
    }

    /* ============= VIOLAÇÕES ============= */

    /**
     * Registra a violação, mostra o gif e (se for grave) conta falta.
     * @param {string} chaveCooldown
     * @param {{tipoEvento:string, descricao:string, aviso:object, grave?:boolean, cooldownMs?:number}} opcoes
     */
    function violacao(chaveCooldown, opcoes) {
        registrarEvento(opcoes.tipoEvento, opcoes.descricao);

        const agora = Date.now();
        const espera = opcoes.cooldownMs || COOLDOWN_PADRAO_MS;
        if (cooldowns[chaveCooldown] && agora - cooldowns[chaveCooldown] < espera) {
            return;
        }
        cooldowns[chaveCooldown] = agora;

        if (opcoes.grave) {
            faltas += 1;
        }

        const contador = opcoes.grave && limiteFaltas > 0
            ? ` — falta ${faltas} de ${limiteFaltas}`
            : '';

        exibirOverlay(opcoes.aviso, `${opcoes.descricao}${contador}`);

        if (opcoes.grave && limiteFaltas > 0 && faltas >= limiteFaltas) {
            finalizarPorFraude();
        }
    }

    /**
     * Conta uma saída da prova (troca de aba ou perda de foco da janela).
     * Só conta uma vez por saída: o aluno precisa voltar para contar de novo.
     * @param {string} tipoEvento
     * @param {string} descricao
     * @param {object} aviso
     */
    function contarSaida(tipoEvento, descricao, aviso) {
        registrarEvento(tipoEvento, descricao);

        if (foraDaProva || finalizando) return;
        foraDaProva = true;
        supressaoTelaCheiaAte = Date.now() + 3000;

        faltas += 1;
        const contador = limiteFaltas > 0 ? ` — falta ${faltas} de ${limiteFaltas}` : '';
        exibirOverlay(aviso, `${descricao}${contador}`);

        if (limiteFaltas > 0 && faltas >= limiteFaltas) {
            finalizarPorFraude();
        }
    }

    /** Finaliza a prova automaticamente quando o aluno estoura o limite de faltas */
    function finalizarPorFraude() {
        if (finalizando) return;
        finalizando = true;

        registrarEvento('tentativa_saida', `Prova finalizada automaticamente após ${faltas} violações graves`);

        criarOverlay();
        document.querySelectorAll('.gif-overlay').forEach((el) => { if (el !== overlay) el.remove(); });
        tituloEl.textContent = AVISOS.fraude.texto;
        gifEl.src = AVISOS.fraude.gif;
        detalheEl.textContent = `Você atingiu o limite de ${limiteFaltas} ocorrências. A prova foi encerrada.`;
        botaoEl.hidden = false;
        overlay.classList.add('is-visible');

        const form = document.getElementById('form-avaliacao');
        if (form) form.dataset.finalizado = 'true';

        const envio = document.createElement('form');
        envio.method = 'POST';
        envio.action = `${baseUrl}/avaliacao.php`;
        envio.innerHTML = `
            <input type="hidden" name="acao" value="finalizar">
            <input type="hidden" name="fraude" value="1">
            <input type="hidden" name="csrf_token" value="${config.csrfToken}">
        `;
        document.body.appendChild(envio);

        setTimeout(() => envio.submit(), 2500);
    }

    /* ============= TELA CHEIA ============= */

    let ultimoAvisoTelaCheia = 0;

    function pedirTelaCheia() {
        const el = document.documentElement;
        if (document.fullscreenElement) return Promise.resolve(true);
        if (!el.requestFullscreen) return Promise.resolve(false);

        return el.requestFullscreen().then(() => true).catch(() => {
            // O navegador só entra em tela cheia após uma interação do aluno;
            // registra no máximo um aviso a cada 60s para não poluir o relatório.
            if (Date.now() - ultimoAvisoTelaCheia >= 60000) {
                ultimoAvisoTelaCheia = Date.now();
                registrarEvento('tela_cheia_negada', 'O navegador não permitiu voltar para a tela cheia (precisa de um clique na página)');
            }
            return false;
        });
    }

    // Tenta voltar para a tela cheia em toda interação do aluno
    ['click', 'mousedown', 'keydown', 'touchstart'].forEach((evento) => {
        document.addEventListener(evento, (e) => {
            if (document.fullscreenElement || finalizando) return;
            // Clicar no portão da câmera é para liberar a webcam: pedir tela
            // cheia no mesmo gesto fazia o navegador abortar o pedido de câmera.
            if (e.target && e.target.closest && e.target.closest('#webcam-portao')) return;
            pedirTelaCheia();
        }, true);
    });

    // A webcam avisa quando a câmera cai no meio da prova: o portão volta.
    document.addEventListener('anti-cheat:pedir-portao', () => {
        if (!finalizando) mostrarPortao();
    });

    document.addEventListener('fullscreenchange', () => {
        if (document.fullscreenElement) {
            esconderPortao();
            atualizarBarraStatus();
            return;
        }

        atualizarBarraStatus();

        if (finalizando) return;
        if (Date.now() < supressaoTelaCheiaAte) return; // já contabilizado como troca de aba/janela

        registrarEvento('tela_cheia_saida', 'O aluno saiu do modo tela cheia');

        const agora = Date.now();
        if (agora - (cooldowns.telaCheia || 0) < 2500) return;
        cooldowns.telaCheia = agora;

        faltas += 1;
        const contador = limiteFaltas > 0 ? ` — falta ${faltas} de ${limiteFaltas}` : '';
        exibirOverlay(AVISOS.telaCheia, `O aluno saiu do modo tela cheia${contador}`);

        if (limiteFaltas > 0 && faltas >= limiteFaltas) {
            finalizarPorFraude();
        }
    });

    /* ============= ABA / JANELA ============= */

    document.addEventListener('visibilitychange', () => {
        if (document.hidden) {
            contarSaida('mudanca_aba', 'O aluno mudou de aba ou minimizou a janela', AVISOS.aba);
        } else {
            foraDaProva = false;
            registrarEvento('volta_foco', 'O aluno voltou para a aba da prova');
        }
    });

    window.addEventListener('blur', () => {
        if (document.hidden) return; // já tratado como mudança de aba

        contarSaida('mudanca_janela', 'A janela da prova perdeu o foco (alt+tab, outro programa, tecla Windows...)', AVISOS.janela);
    });

    window.addEventListener('focus', () => {
        foraDaProva = false;
        registrarEvento('volta_foco', 'A janela da prova recuperou o foco');
    });

    /* ============= ATALHOS DE TECLADO ============= */

    // Comparação em minúsculas: com Ctrl pressionado o navegador manda a letra minúscula
    const atalhosBloqueados = [
        { code: 'F12', desc: 'DevTools (F12)' },
        { code: 'F5', desc: 'Recarregar a página (F5)' },
        { code: 'F11', desc: 'Alternar tela cheia do navegador (F11)' },
        { key: 'printscreen', desc: 'Captura de tela (PrintScreen)' },
        { ctrl: true, shift: true, key: 'i', desc: 'DevTools (Ctrl+Shift+I)' },
        { ctrl: true, shift: true, key: 'j', desc: 'Console (Ctrl+Shift+J)' },
        { ctrl: true, shift: true, key: 'c', desc: 'Inspecionar elemento (Ctrl+Shift+C)' },
        { ctrl: true, shift: true, key: 'k', desc: 'Console (Ctrl+Shift+K)' },
        { ctrl: true, shift: true, key: 'r', desc: 'Recarregar sem cache (Ctrl+Shift+R)' },
        { ctrl: true, shift: true, key: 't', desc: 'Reabrir aba fechada (Ctrl+Shift+T)' },
        { ctrl: true, shift: true, key: 'n', desc: 'Janela anônima (Ctrl+Shift+N)' },
        { ctrl: true, key: 'u', desc: 'Ver código fonte (Ctrl+U)' },
        { ctrl: true, key: 's', desc: 'Salvar a página (Ctrl+S)' },
        { ctrl: true, key: 'p', desc: 'Imprimir (Ctrl+P)' },
        { ctrl: true, key: 'a', desc: 'Selecionar tudo (Ctrl+A)' },
        { ctrl: true, key: 'c', desc: 'Copiar (Ctrl+C)' },
        { ctrl: true, key: 'x', desc: 'Recortar (Ctrl+X)' },
        { ctrl: true, key: 't', desc: 'Abrir nova aba (Ctrl+T)' },
        { ctrl: true, key: 'n', desc: 'Abrir nova janela (Ctrl+N)' },
        { ctrl: true, key: 'w', desc: 'Fechar a aba (Ctrl+W)' },
        { ctrl: true, key: 'r', desc: 'Recarregar a página (Ctrl+R)' },
        { ctrl: true, key: 'l', desc: 'Barra de endereços (Ctrl+L)' },
        { ctrl: true, key: 'f', desc: 'Buscar na página (Ctrl+F)' },
        { ctrl: true, key: 'tab', desc: 'Trocar de aba (Ctrl+Tab)' },
        { ctrl: true, key: 'escape', desc: 'Menu Iniciar do Windows (Ctrl+Esc)' },
        { alt: true, key: 'f4', desc: 'Fechar a janela (Alt+F4)' },
        // Teclas do Windows — o sistema operacional decide se entrega ao navegador;
        // quando ele entrega, bloqueamos aqui. Quando não entrega, a saída é
        // detectada como perda de foco (blur) e contada como falta.
        { meta: true, key: 'x', desc: 'Atalho do Windows (Win+X)' },
        { meta: true, key: 'd', desc: 'Mostrar a área de trabalho (Win+D)' },
        { meta: true, key: 'e', desc: 'Abrir o Explorador de Arquivos (Win+E)' },
        { meta: true, key: 'r', desc: 'Abrir Executar (Win+R)' },
        { meta: true, key: 'l', desc: 'Bloquear o computador (Win+L)' },
        { meta: true, key: 'i', desc: 'Configurações do Windows (Win+I)' },
        { meta: true, key: 's', desc: 'Pesquisar no Windows (Win+S)' },
        { meta: true, key: 'g', desc: 'Barra de jogos (Win+G)' },
        { meta: true, key: 'p', desc: 'Projetar tela (Win+P)' },
        { meta: true, key: 'b', desc: 'Barra de tarefas (Win+B)' },
        { meta: true, key: 'm', desc: 'Minimizar tudo (Win+M)' },
        { meta: true, key: 'arrowleft', desc: 'Encaixar janela (Win+←)' },
        { meta: true, key: 'arrowright', desc: 'Encaixar janela (Win+→)' },
        { meta: true, key: 'arrowup', desc: 'Maximizar janela (Win+↑)' },
        { meta: true, key: 'arrowdown', desc: 'Minimizar janela (Win+↓)' },
        { meta: true, desc: 'Tecla Windows' },
        { meta: true, key: 'c', desc: 'Copiar (Cmd+C)' },
        { meta: true, key: 'a', desc: 'Selecionar tudo (Cmd+A)' },
        { meta: true, key: 'v', desc: 'Colar (Cmd+V)' },
        { alt: true, key: 'arrowleft', desc: 'Voltar página (Alt+←)' },
        { alt: true, key: 'arrowright', desc: 'Avançar página (Alt+→)' },
        { alt: true, key: 'tab', desc: 'Trocar de janela (Alt+Tab)' }
    ];

    function combinaAtalho(tecla, evento) {
        const keyEvento = (evento.key || '').toLowerCase();
        const codeEvento = (evento.code || '').toLowerCase();

        const matchKey = tecla.code
            ? codeEvento === tecla.code.toLowerCase()
            : keyEvento === tecla.key;

        if (!matchKey) return false;

        const matchCtrl = tecla.ctrl !== undefined ? evento.ctrlKey === tecla.ctrl : true;
        const matchShift = tecla.shift !== undefined ? evento.shiftKey === tecla.shift : true;
        const matchAlt = tecla.alt !== undefined ? evento.altKey === tecla.alt : true;
        const matchMeta = tecla.meta !== undefined ? evento.metaKey === tecla.meta : true;

        return matchCtrl && matchShift && matchAlt && matchMeta;
    }

    /**
     * Atalhos que quem entrega é o Windows, não o navegador (tecla Windows,
     * Alt+Tab, Ctrl+Esc, Alt+F4). O site não consegue impedir o sistema de agir,
     * então esses casos contam FALTA GRAVE — a punição máxima possível aqui.
     */
    function ehAtalhoDoSistema(tecla) {
        if (tecla.meta) return true;
        if (tecla.alt && (tecla.key === 'tab' || tecla.key === 'f4')) return true;
        if (tecla.ctrl && tecla.key === 'escape') return true;
        return false;
    }

    document.addEventListener('keydown', (evento) => {
        for (const tecla of atalhosBloqueados) {
            if (!combinaAtalho(tecla, evento)) continue;

            evento.preventDefault();
            evento.stopPropagation();

            const doSistema = ehAtalhoDoSistema(tecla);

            // Um único "Win+X" gera dois keydown (a tecla Windows e o X): os dois
            // vão para o relatório do professor, mas contam UMA falta só.
            const chave = doSistema ? 'windows' : 'tecla';
            const avisoBase = AVISOS[chave];
            const aviso = Array.isArray(avisoBase)
                ? avisoBase[indiceLoop % avisoBase.length]
                : avisoBase;

            if (!doSistema) indiceLoop += 1;

            violacao(doSistema ? 'tecla:sistema' : 'tecla:' + (tecla.key || tecla.code), {
                tipoEvento: 'tecla_bloqueada',
                descricao: `Tecla bloqueada: ${tecla.desc}`,
                aviso: { gif: aviso.gif, texto: aviso.texto },
                grave: doSistema,
                cooldownMs: doSistema ? 2500 : undefined
            });
            return false;
        }

        // Rede de segurança: qualquer outra combinação com a tecla Windows (Cmd no Mac)
        // que não esteja na lista acima também é bloqueada e denunciada.
        if (evento.metaKey) {
            evento.preventDefault();
            evento.stopPropagation();

            const junto = (evento.key === 'Meta' || evento.key === 'OS') ? '' : `+${evento.key}`;

            violacao('tecla:sistema', {
                tipoEvento: 'tecla_bloqueada',
                descricao: `Tecla Windows bloqueada (Win${junto})`,
                aviso: AVISOS.windows,
                grave: true,
                cooldownMs: 2500
            });
            return false;
        }
    }, true);

    /* ============= MOUSE / TEXTO ============= */

    document.addEventListener('contextmenu', (evento) => {
        evento.preventDefault();
        avisoLoop('contexto', 'Tentativa de abrir o menu de contexto (botão direito)');
        return false;
    });

    /**
     * Tudo que o aluno precisa CLICAR para responder. Bloquear a seleção aqui
     * era o que impedia de marcar a alternativa (e o aviso ainda cobria a tela
     * no meio do clique, engolindo o mouseup).
     */
    function alvoInterativo(alvo) {
        if (!alvo) return false;
        if (alvo.isContentEditable) return true;
        const tag = (alvo.tagName || '').toLowerCase();
        if (['input', 'textarea', 'select', 'button', 'label', 'a', 'option'].includes(tag)) return true;
        if (!alvo.closest) return false;
        return !!alvo.closest(
            '.alternativa-item, .alternativas-container, .questoes-nav, .questoes-grid, .modal, '
            + '.anti-cheat-status, .gif-overlay, .btn, .resposta-texto-container, .questao-footer'
        );
    }

    // Seleção de texto só é denunciada quando o aluno realmente ARRASTA o mouse
    // sobre o enunciado. Clique simples em alternativa/questão não é violação.
    let arrastandoSelecao = false;
    let pontoDoMouse = null;

    document.addEventListener('mousedown', (evento) => {
        arrastandoSelecao = false;
        pontoDoMouse = (evento.button === 0 && !alvoInterativo(evento.target))
            ? { x: evento.clientX, y: evento.clientY }
            : null;
    }, true);

    document.addEventListener('mousemove', (evento) => {
        if (!pontoDoMouse) return;
        if (Math.abs(evento.clientX - pontoDoMouse.x) > 8 || Math.abs(evento.clientY - pontoDoMouse.y) > 8) {
            arrastandoSelecao = true;
        }
    });

    document.addEventListener('mouseup', () => {
        const foiArrasto = arrastandoSelecao;
        pontoDoMouse = null;
        arrastandoSelecao = false;

        if (foiArrasto) {
            avisoLoop('selecao', 'Tentativa de selecionar texto da questão');
            cooldowns.selecao = Date.now();
        }
    });

    document.addEventListener('selectstart', (evento) => {
        // Alternativas, botões, campos e menus nunca entram aqui.
        if (alvoInterativo(evento.target)) return;
        evento.preventDefault();
        return false;
    });

    document.addEventListener('copy', (evento) => {
        evento.preventDefault();
        avisoLoop('copiar', 'Tentativa de copiar conteúdo');
    });

    document.addEventListener('cut', (evento) => {
        evento.preventDefault();
        avisoLoop('copiar', 'Tentativa de recortar conteúdo');
    });

    document.addEventListener('dragstart', (evento) => {
        evento.preventDefault();
        avisoLoop('copiar', 'Tentativa de arrastar conteúdo da página');
        cooldowns.drag = Date.now();
        return false;
    });

    /* ============= NOVAS ABAS E JANELAS ============= */

    window.open = function() {
        violacao('windowopen', {
            tipoEvento: 'nova_aba',
            descricao: 'Tentativa de abrir nova janela via window.open()',
            aviso: AVISOS.janela
        });
        return null;
    };

    document.addEventListener('auxclick', (evento) => {
        if (evento.button !== 0) {
            evento.preventDefault();
            violacao('auxclick', {
                tipoEvento: 'nova_aba',
                descricao: 'Clique com o botão do meio do mouse',
                aviso: AVISOS.janela
            });
        }
    }, true);

    document.addEventListener('click', (evento) => {
        const link = evento.target.closest && evento.target.closest('a[href]');
        if (link && (evento.ctrlKey || evento.metaKey || evento.shiftKey || evento.altKey || link.target === '_blank')) {
            evento.preventDefault();
            violacao('novaaba', {
                tipoEvento: 'nova_aba',
                descricao: 'Tentativa de abrir link em nova aba/janela',
                aviso: AVISOS.janela
            });
        }
    }, true);

    window.addEventListener('beforeunload', () => {
        if (window.opener) {
            try { window.opener = null; } catch (err) { /* ignora */ }
        }
    });

    /* ============= DEVTOOLS ============= */

    let devToolsAberto = false;

    function marcarDevTools(origem) {
        if (devToolsAberto) return;
        devToolsAberto = true;
        violacao('devtools', {
            tipoEvento: 'devtools_aberto',
            descricao: `Ferramentas de desenvolvedor abertas (${origem})`,
            aviso: AVISOS.devtools,
            grave: true,
            cooldownMs: 5000
        });
    }

    setInterval(() => {
        const diferencaLargura = window.outerWidth - window.innerWidth;
        const diferencaAltura = window.outerHeight - window.innerHeight;
        const aberto = diferencaLargura > 160 || diferencaAltura > 160;

        if (aberto) {
            marcarDevTools('tamanho da janela');
        } else {
            devToolsAberto = false;
        }
    }, 1200);

    setInterval(() => {
        const inicio = performance.now();
        // eslint-disable-next-line no-debugger
        debugger;
        if (performance.now() - inicio > 120) {
            marcarDevTools('pausa do debugger');
        }
    }, 2500);

    /* ============= SAÍDA DA PÁGINA ============= */

    window.addEventListener('beforeunload', (evento) => {
        // Finalização em andamento (botão do modal, tempo esgotado ou fraude):
        // não é o aluno saindo sem querer, então o navegador não pergunta nada
        // e a navegação segue direto para a tela de conclusão.
        if (finalizando) return;

        const form = document.getElementById('form-avaliacao');
        if (form && !form.dataset.finalizado) {
            registrarEvento('tentativa_saida', 'Tentativa de sair ou recarregar a página da prova');
            evento.preventDefault();
            evento.returnValue = 'Sair agora pode encerrar sua avaliação. Tem certeza?';
            return evento.returnValue;
        }
    });

    document.addEventListener('submit', (evento) => {
        const form = evento.target;
        const ehFinalizacao = !!(form.querySelector && form.querySelector('input[name="acao"][value="finalizar"]'));
        const ehProva = form.id === 'form-avaliacao';

        if (!ehProva && !ehFinalizacao) return;

        // Marca o form principal (é ele que o beforeunload consulta, não o form
        // que disparou o submit) e congela o anti-cola. Sem isso, a saída da
        // tela cheia e a perda de foco durante a navegação viravam falta, e o
        // navegador travava com o aviso "sair da guia?" no meio da finalização.
        finalizando = true;
        const formProva = document.getElementById('form-avaliacao');
        if (formProva) formProva.dataset.finalizado = 'true';
        form.dataset.finalizado = 'true';
    });

    /* ============= CAMPOS ============= */

    document.querySelectorAll('input, textarea').forEach((el) => {
        el.setAttribute('autocomplete', 'off');
        el.setAttribute('autocorrect', 'off');
        el.setAttribute('autocapitalize', 'off');
        el.setAttribute('spellcheck', 'false');
    });

    /* ============= API PÚBLICA (usada pelo paste-guard.js) ============= */

    /**
     * Avisa de uma violação de cópia/colar usando o loop de dois gifs
     * (silly-tamamo / scary-oguri). O índice é compartilhado entre colar,
     * copiar, seleção, teclas e botão direito, então os dois gifs se alternam
     * conforme as tentativas de trapaça vão acontecendo.
     * @param {string} tipoChave chave do AVISOS (ex: 'colar', 'copiar', 'selecao')
     * @param {string} descricao descrição registrada no banco
     */
    function avisoLoop(tipoChave, descricao) {
        registrarEvento('copiar_colar', descricao);

        const avisos = Array.isArray(AVISOS[tipoChave]) ? AVISOS[tipoChave] : [AVISOS[tipoChave]];
        const aviso = avisos[indiceLoop % avisos.length];
        indiceLoop += 1;

        exibirOverlay(aviso, '');
    }

    /**
     * Avisa de colagem bloqueada usando o loop de dois gifs.
     * Chamada pelo paste-guard.js quando o anti-cheat.js está presente.
     * @param {string} descricao descrição registrada no banco
     */
    function avisoColagem(descricao) {
        avisoLoop('colar', descricao);
    }

    /* ============= INICIALIZAÇÃO ============= */

    criarBarraStatus();
    atualizarBarraStatus();

    // Exige tela cheia desde o começo: sem isso o aluno podia fazer a prova
    // inteira fora da tela cheia e "sair da tela cheia" não gerava falta alguma.
    // A webcam é pedida antes: o portão da câmera (webcam.js) vem primeiro e
    // este só aparece depois que a câmera está ligada.
    if (window.WebcamGuard && window.WebcamGuard.liberacao) {
        window.WebcamGuard.liberacao.then(() => { mostrarPortao(); });
    } else {
        mostrarPortao();
    }

    window.AntiCheat = {
        registrarEvento,
        violacao,
        exibirOverlay,
        avisoColagem,
        pedirTelaCheia,
        faltas: () => faltas,
        historico: () => historicoLocal.slice()
    };

    console.log('%c🛡️ Sistema Anti-Cola Ativo', 'color: #dc2626; font-size: 14px; font-weight: bold;');
    console.log(`%cViolações graves finalizam a prova após ${limiteFaltas || '∞'} ocorrências.`, 'color: #6b7280;');
})();

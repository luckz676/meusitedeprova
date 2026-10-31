/**
 * Webcam Obrigatória - Prova monitorada
 *
 * O que este arquivo faz:
 *  - Exige liberar a webcam antes de começar a prova (portão em tela cheia,
 *    no mesmo visual do portão da tela cheia, com o gif no meio)
 *  - Mostra a câmera ao vivo no topo da página durante toda a prova, com o
 *    gif stay gold à esquerda e o weaving aston machan à direita
 *  - Analisa o quadro da câmera e conta FALTA GRAVE quando o aluno desvia o
 *    olhar para o lado, para baixo ou para cima por mais de alguns segundos
 *
 * Limitação do navegador (importante):
 *  não existe uma API padrão de rastreio facial no Chrome de desktop. Quando o
 *  navegador tem a FaceDetector (Chrome com flags/Android), ela é usada; sem ela
 *  o desvio é estimado pela posição da região de pele do quadro (eixo X e Y).
 *  Isso pega desvios claros de cabeça (virar para uma segunda tela, celular ao
 *  lado ou no colo, olhar para o teto) e pode errar em ambiente escuro ou com
 *  mão na frente do rosto. O movimento dos olhos sozinho não é medido.
 */

(function() {
    'use strict';

    const config = window.AVALIACAO_CONFIG || {};
    const baseUrl = config.baseUrl || '/avaliacao web';
    const limiteFaltas = parseInt(config.limiteFaltas, 10) || 0;

    const GIFS = {
        portao: `${baseUrl}/assets/gifs/cute-stay-gold.gif`,
        esquerda: `${baseUrl}/assets/gifs/cute-stay-gold.gif`,
        direita: `${baseUrl}/assets/gifs/weaving-aston-machan.gif`,
        olhar: `${baseUrl}/assets/gifs/scary-oguri.gif`
    };

    // Resolução de análise: pequena de propósito (só precisamos da posição do
    // rosto, não da imagem bonita) para gastar o mínimo de CPU durante a prova.
    const LARGURA = 160;
    const ALTURA = 120;

    const INTERVALO_MS = 250;          // uma análise a cada 0,25s
    const LIMIAR_DESVIO = 0.10;        // 10% da largura do quadro desvia para o lado
    const LIMIAR_VOLTA = 0.07;         // abaixo disso o rosto voltou e a base se ajusta
    // No eixo vertical o limite é medido em relação ao TAMANHO DO ROSTO (não ao
    // quadro): quem está perto da câmera tem um rosto grande e um aceno pequeno
    // já muda muito o centro; quem está longe tem o contrário. Usar a altura do
    // próprio rosto faz o mesmo aceno valer o mesmo nos dois casos.
    const LIMIAR_DESVIO_Y = 0.22;      // 22% da altura do rosto desvia para baixo/cima
    const LIMIAR_VOLTA_Y = 0.15;       // mesmo papel do LIMIAR_VOLTA no eixo vertical
    const TEMPO_DESVIO_MS = 1000;      // precisa ficar desviado 1s para contar
    // Olhar para baixo faz parte de digitar (teclado), então o eixo vertical
    // exige um pouco mais de tempo antes de virar falta — evita acusar quem só
    // baixou a cabeça por um instante.
    const TEMPO_DESVIO_VERTICAL_MS = 1300;
    const MIN_PROPORCAO = 0.012;       // rosto ocupa pelo menos 1,2% do quadro
    const CALIBRAGEM_MS = 3000;        // primeiros segundos só calibram
    const DURACAO_AVISO_OLHAR_MS = 2500; // o aviso some rápido depois que o aluno volta

    let stream = null;
    let video = null;
    let canvas = null;
    let ctx = null;
    let rodando = false;
    let timerAnalise = null;

    let portao = null;
    let barra = null;
    let statusEl = null;

    // Calibragem / desvio de olhar
    let baseCentro = null;
    let baseCentroY = null;
    let baseAlturaY = 0;      // altura do rosto calibrada, em pixels do quadro
    let baseProporcao = 0;
    let calibrandoAte = 0;
    let amostrasCalibragem = [];
    let desvioDesde = 0;
    let ladoDesvio = '';
    let avisouDesvio = false;
    let semRostoDesde = 0;
    let avisouSemRosto = false;
    let detectorFaces = null;

    let liberarProva;
    const liberacao = new Promise((resolve) => { liberarProva = resolve; });
    let jaLiberou = false;

    /* ============= REGISTRO NO BANCO ============= */

    function registrarEvento(tipoEvento, descricao) {
        const anti = window.AntiCheat;
        if (anti && typeof anti.registrarEvento === 'function') {
            anti.registrarEvento(tipoEvento, descricao);
            return;
        }
        // Antes do anti-cola carregar, manda direto pela API.
        fetch(`${baseUrl}/api/registrar_evento.php`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-Token': config.csrfToken
            },
            credentials: 'same-origin',
            body: JSON.stringify({
                avaliacao_id: config.avaliacaoId,
                tipo_evento: tipoEvento,
                descricao: descricao,
                timestamp: new Date().toISOString(),
                user_agent: navigator.userAgent,
                url: window.location.href
            })
        }).catch((err) => console.error('Erro ao registrar evento da webcam:', err));
    }

    /* ============= PORTÃO DA WEBCAM ============= */

    function criarPortao() {
        if (portao && document.body.contains(portao)) return portao;

        const textoLimite = limiteFaltas > 0
            ? `Olhar para o lado, para baixo ou para cima conta <strong>falta grave</strong> e com ${limiteFaltas} faltas a prova é encerrada.`
            : 'Olhar para o lado, para baixo ou para cima conta <strong>falta grave</strong>.';

        portao = document.createElement('div');
        portao.className = 'gif-overlay webcam-portao';
        portao.id = 'webcam-portao';
        portao.setAttribute('role', 'alertdialog');
        portao.setAttribute('aria-live', 'assertive');
        portao.innerHTML = `
            <div class='gif-overlay__inner'>
                <p class='gif-overlay__texto'>MODO PROVA — CÂMERA OBRIGATÓRIA</p>
                <img class='gif-overlay__gif' src='${GIFS.portao}' alt='Stay Gold'>
                <p class='gif-overlay__detalhe'>
                    A prova é monitorada pela webcam. A câmera fica ligada no topo da página
                    durante toda a avaliação. ${textoLimite}
                    <br>Sem liberar a câmera não é possível começar a prova.
                </p>
                <p class='webcam-portao__erro' id='webcam-portao-erro' hidden></p>
                <button type='button' class='btn btn-primary gif-overlay__botao' id='webcam-portao-btn'>Ligar a câmera</button>
            </div>
        `;
        document.body.appendChild(portao);

        portao.querySelector('#webcam-portao-btn').addEventListener('click', tentarLigarCamera);

        return portao;
    }

    function mostrarPortao() {
        criarPortao();

        // Só um aviso na tela por vez: o portão da câmera tem prioridade.
        document.querySelectorAll('.gif-overlay').forEach((el) => {
            if (el !== portao) el.classList.remove('is-visible');
        });

        portao.classList.add('is-visible');
    }

    function esconderPortao() {
        if (portao) portao.classList.remove('is-visible');
    }

    function definirErro(mensagem) {
        const erro = portao && portao.querySelector('#webcam-portao-erro');
        if (!erro) return;
        erro.textContent = mensagem || '';
        erro.hidden = !mensagem;
    }

    function mensagemDeErro(erro) {
        const nome = (erro && erro.name) || '';
        if (nome === 'NotAllowedError' || nome === 'SecurityError') {
            return 'Câmera bloqueada. Clique no cadeado da barra de endereço, libere a câmera e tente de novo.';
        }
        if (nome === 'NotFoundError' || nome === 'OverconstrainedError') {
            return 'Nenhuma câmera encontrada neste computador. Conecte uma webcam e tente de novo.';
        }
        if (nome === 'NotReadableError') {
            return 'A câmera está sendo usada por outro programa. Feche-o e tente de novo.';
        }
        return 'Não foi possível ligar a câmera. Tente de novo.';
    }

    async function tentarLigarCamera() {
        const botao = portao.querySelector('#webcam-portao-btn');

        if (!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) {
            definirErro('Este navegador não libera a câmera. Use o Chrome atualizado em uma conexão segura (https).');
            registrarEvento('webcam_negada', 'Navegador sem suporte a getUserMedia');
            return;
        }

        botao.disabled = true;
        botao.textContent = 'Ligando a câmera...';
        definirErro('');

        try {
            const capturado = await navigator.mediaDevices.getUserMedia({
                video: {
                    width: { ideal: 640 },
                    height: { ideal: 480 },
                    facingMode: 'user'
                },
                audio: false
            });
            iniciarCamera(capturado);
            esconderPortao();
        } catch (erro) {
            botao.disabled = false;
            botao.textContent = 'Tentar de novo';
            definirErro(mensagemDeErro(erro));
            registrarEvento('webcam_negada', `Câmera não liberada (${(erro && erro.name) || 'erro desconhecido'})`);
        }
    }

    /* ============= BARRA COM A CÂMERA AO VIVO ============= */

    function criarBarra() {
        if (barra && document.body.contains(barra)) return barra;

        barra = document.createElement('div');
        barra.className = 'webcam-barra';
        barra.id = 'webcam-barra';
        barra.innerHTML = `
            <img class='webcam-barra__gif' src='${GIFS.esquerda}' alt='Stay Gold'>
            <div class='webcam-barra__moldura'>
                <video class='webcam-barra__video' id='webcam-video' autoplay muted playsinline></video>
                <span class='webcam-barra__status' id='webcam-status'>Calibrando a câmera...</span>
            </div>
            <img class='webcam-barra__gif' src='${GIFS.direita}' alt='Aston Machan'>
        `;
        document.body.insertBefore(barra, document.body.firstChild);

        video = barra.querySelector('#webcam-video');
        statusEl = barra.querySelector('#webcam-status');
        statusAtual = '';
        alertaAtual = false;

        // O cabeçalho da prova é fixo no topo: com a barra da câmera ele desce
        // junto (o CSS usa esta classe em body.tem-webcam).
        document.body.classList.add('tem-webcam');

        return barra;
    }

    let statusAtual = '';
    let alertaAtual = false;

    function status(texto, alerta) {
        if (!statusEl) return;
        if (statusAtual !== texto) {
            statusEl.textContent = texto;
            statusAtual = texto;
        }
        if (alertaAtual !== !!alerta) {
            statusEl.classList.toggle('webcam-barra__status--alerta', !!alerta);
            alertaAtual = !!alerta;
        }
    }

    function iniciarCamera(capturado) {
        stream = capturado;
        criarBarra();

        video.srcObject = capturado;
        const promessa = video.play();
        if (promessa && typeof promessa.catch === 'function') {
            promessa.catch(() => { /* autoplay bloqueado: o muted resolve na prática */ });
        }

        // Se o aluno desligar a câmera no meio da prova (ou ela cair), a barra
        // some e o portão volta a pedir a câmera — com falta grave.
        capturado.getVideoTracks().forEach((trilha) => {
            trilha.addEventListener('ended', cameraCaiu);
            // "mute" também acontece quando outro programa pega a câmera: só
            // conta como câmera desligada se continuar mudo depois de 3s.
            trilha.addEventListener('mute', () => {
                setTimeout(() => { if (trilha.muted || trilha.readyState === 'ended') cameraCaiu(); }, 3000);
            });
        });

        registrarEvento('webcam_autorizada', 'O aluno liberou a câmera para a prova');

        baseCentro = null;
        baseCentroY = null;
        baseAlturaY = 0;
        baseProporcao = 0;
        amostrasCalibragem = [];
        calibrandoAte = Date.now() + CALIBRAGEM_MS;
        desvioDesde = 0;
        avisouDesvio = false;
        semRostoDesde = 0;
        avisouSemRosto = false;

        if (!canvas) {
            canvas = document.createElement('canvas');
            canvas.width = LARGURA;
            canvas.height = ALTURA;
            ctx = canvas.getContext('2d', { willReadFrequently: true });
        }

        try {
            if (!detectorFaces && 'FaceDetector' in window) {
                detectorFaces = new window.FaceDetector({ fastMode: true, maxDetectedFaces: 1 });
            }
        } catch (erro) {
            detectorFaces = null;
        }

        status('Calibrando a câmera...');
        rodando = true;
        clearInterval(timerAnalise);
        timerAnalise = setInterval(cicloDeAnalise, INTERVALO_MS);

        if (!jaLiberou) {
            jaLiberou = true;
            liberarProva();
        }
    }

    function cameraCaiu() {
        if (!stream) return;

        pararCamera();

        const descricao = 'A câmera foi desligada durante a prova';
        const anti = window.AntiCheat;
        if (anti && typeof anti.violacao === 'function') {
            anti.violacao('webcam:caiu', {
                tipoEvento: 'webcam_desligada',
                descricao: descricao,
                aviso: { gif: GIFS.portao, texto: 'LIGUE A CÂMERA DE VOLTA!' },
                grave: true,
                cooldownMs: 5000
            });
        } else {
            registrarEvento('webcam_desligada', descricao);
        }

        mostrarPortao();
        document.dispatchEvent(new CustomEvent('anti-cheat:pedir-portao'));
    }

    function pararCamera() {
        rodando = false;
        clearInterval(timerAnalise);
        timerAnalise = null;

        if (stream) {
            const atual = stream;
            stream = null; // antes do stop(), senão a verificação acha que a câmera caiu
            atual.getTracks().forEach((trilha) => trilha.stop());
        }

        if (barra) barra.remove();
        barra = null;
        video = null;
        statusEl = null;
        document.body.classList.remove('tem-webcam');
    }

    /* ============= ANÁLISE DO QUADRO ============= */

    /**
     * Procura a região de pele do quadro (regra clássica YCbCr) e devolve a
     * posição e o tamanho dela nos dois eixos. Usado quando o navegador não tem
     * FaceDetector.
     *
     * Em vez da média de todos os pixels (um pixel solto de parede bege puxava
     * o centro e o rosto "pulava" de posição), cada eixo é resumido pela faixa
     * que concentra 84% dos pixels de pele, descartando as pontas. É isso que
     * deixa a leitura estável o bastante para medir desvio vertical.
     *
     * @param {Uint8ClampedArray} dados pixels RGBA
     * @param {number} largura
     * @param {number} altura
     * @returns {{centroX: number|null, centroY: number|null, proporcao: number,
     *            larguraRelativa: number, alturaRelativa: number}}
     */
    function analisarPixels(dados, largura, altura) {
        const contagemX = new Uint16Array(largura);
        const contagemY = new Uint16Array(altura);
        let encontrados = 0;

        for (let y = 0; y < altura; y++) {
            const linha = y * largura;
            for (let x = 0; x < largura; x++) {
                const i = (linha + x) * 4;
                const r = dados[i];
                const g = dados[i + 1];
                const b = dados[i + 2];

                const cb = 128 - 0.168736 * r - 0.331264 * g + 0.5 * b;
                const cr = 128 + 0.5 * r - 0.418688 * g - 0.081312 * b;
                const yv = 0.299 * r + 0.587 * g + 0.114 * b;

                if (yv > 55 && yv < 245 && cb >= 77 && cb <= 127 && cr >= 133 && cr <= 173) {
                    contagemX[x]++;
                    contagemY[y]++;
                    encontrados++;
                }
            }
        }

        if (encontrados === 0) {
            return { centroX: null, centroY: null, proporcao: 0, larguraRelativa: 0, alturaRelativa: 0 };
        }

        const faixaX = faixaPercentil(contagemX, encontrados);
        const faixaY = faixaPercentil(contagemY, encontrados);

        return {
            centroX: faixaX.centro,
            centroY: faixaY.centro,
            proporcao: encontrados / (largura * altura),
            larguraRelativa: faixaX.tamanho / largura,
            alturaRelativa: faixaY.tamanho / altura
        };
    }

    /**
     * Faixa onde os pixels de pele se concentram num eixo: começa em 8% e
     * termina em 92% da contagem acumulada.
     * @param {Uint16Array} contagens
     * @param {number} total
     * @returns {{inicio: number, fim: number, centro: number, tamanho: number}}
     */
    function faixaPercentil(contagens, total) {
        const alvoInicio = total * 0.08;
        const alvoFim = total * 0.92;
        let acumulado = 0;
        let inicio = 0;
        let fim = contagens.length - 1;
        let comecou = false;

        for (let i = 0; i < contagens.length; i++) {
            acumulado += contagens[i];
            if (!comecou && acumulado >= alvoInicio) {
                inicio = i;
                comecou = true;
            }
            if (acumulado >= alvoFim) {
                fim = i;
                break;
            }
        }

        if (fim < inicio) fim = inicio;

        return {
            inicio: inicio,
            fim: fim,
            centro: (inicio + fim) / 2,
            tamanho: fim - inicio
        };
    }

    function cicloDeAnalise() {
        if (!rodando || !video || !ctx) return;

        // Câmera desligada/desconectada: o evento "ended" só chega quando o
        // dispositivo sai; conferir o estado do stream cobre os outros casos.
        if (stream && stream.active === false) {
            cameraCaiu();
            return;
        }

        if (document.hidden || video.readyState < 2 || !video.videoWidth) return;

        let amostra = null;
        try {
            ctx.drawImage(video, 0, 0, LARGURA, ALTURA);
            const dados = ctx.getImageData(0, 0, LARGURA, ALTURA).data;
            amostra = analisarPixels(dados, LARGURA, ALTURA);
        } catch (erro) {
            return; // quadro ainda não disponível
        }

        if (detectorFaces) {
            detectorFaces.detect(canvas).then((rostos) => {
                if (rostos && rostos.length > 0) {
                    const caixa = rostos[0].boundingBox;
                    amostra = {
                        centroX: caixa.x + caixa.width / 2,
                        centroY: caixa.y + caixa.height / 2,
                        proporcao: (caixa.width * caixa.height) / (LARGURA * ALTURA),
                        larguraRelativa: caixa.width / LARGURA,
                        alturaRelativa: caixa.height / ALTURA
                    };
                }
                avaliarAmostra(amostra);
            }).catch(() => {
                detectorFaces = null;
                avaliarAmostra(amostra);
            });
            return;
        }

        avaliarAmostra(amostra);
    }

    /**
     * Estado do olhar: calibra a posição normal do rosto, acompanha a
     * aproximação e conta falta grave quando o desvio passa do limite — nos
     * dois eixos (lado esquerdo/direito e baixo/cima).
     * @param {{centroX: number|null, centroY?: number|null, proporcao: number}} amostra
     */
    function avaliarAmostra(amostra) {
        const agora = Date.now();

        if (!amostra || amostra.centroX === null || amostra.proporcao < MIN_PROPORCAO) {
            tratarSemRosto(agora);
            return;
        }

        semRostoDesde = 0;
        avisouSemRosto = false;

        const temVertical = typeof amostra.centroY === 'number';

        // Primeiros segundos: só aprende onde fica o rosto do aluno.
        if (baseCentro === null) {
            amostrasCalibragem.push(amostra);
            if (agora >= calibrandoAte && amostrasCalibragem.length > 0) {
                let soma = 0;
                let somaY = 0;
                let somaAltura = 0;
                let somaProporcao = 0;
                let comY = 0;
                amostrasCalibragem.forEach((item) => {
                    soma += item.centroX;
                    somaProporcao += item.proporcao;
                    if (typeof item.centroY === 'number') {
                        somaY += item.centroY;
                        somaAltura += (item.alturaRelativa || 0) * ALTURA;
                        comY++;
                    }
                });
                baseCentro = soma / amostrasCalibragem.length;
                baseProporcao = somaProporcao / amostrasCalibragem.length;
                baseCentroY = comY > 0 ? somaY / comY : null;
                baseAlturaY = comY > 0 ? somaAltura / comY : 0;
                amostrasCalibragem = [];
                status('Câmera ligada');
            }
            return;
        }        // Quadro suspeito (mão na frente, luz mudou): não julga o olhar.
        if (amostra.proporcao > baseProporcao * 2.5 || amostra.proporcao < baseProporcao * 0.5) {
            return;
        }

        // Referência do eixo Y: altura do rosto calibrada. Se ela não foi
        // medida (navegador sem FaceDetector em quadro ruim), cai para 30% do
        // quadro, que é o tamanho típico de um rosto enquadrado de perto.
        const referenciaY = baseAlturaY > ALTURA * 0.08 ? baseAlturaY : ALTURA * 0.30;

        const desvioX = (amostra.centroX - baseCentro) / LARGURA;
        const desvioY = (temVertical && baseCentroY !== null)
            ? (amostra.centroY - baseCentroY) / referenciaY
            : 0;
        const distanciaX = Math.abs(desvioX);
        const distanciaY = Math.abs(desvioY);

        const desviouLado = distanciaX >= LIMIAR_DESVIO;
        const desviouVertical = distanciaY >= LIMIAR_DESVIO_Y;

        // Voltou para o centro: limpa o aviso na hora, sem esperar confirmação
        // (era isso que deixava o "olhando para o lado" na tela depois da volta).
        if (!desviouLado && !desviouVertical) {
            if (desvioDesde) limparDesvio();

            // Reaproxima a base devagar: o aluno pode mudar de posição na cadeira.
            if (distanciaX <= LIMIAR_VOLTA && distanciaY <= LIMIAR_VOLTA_Y) {
                baseCentro += (amostra.centroX - baseCentro) * 0.06;
                baseProporcao += (amostra.proporcao - baseProporcao) * 0.06;
                if (temVertical && baseCentroY !== null) {
                    baseCentroY += (amostra.centroY - baseCentroY) * 0.06;
                }
            }
            return;
        }

        // Qual desvio manda: compara o quanto cada eixo passou do próprio limite.
        let direcao;
        if (desviouLado && (!desviouVertical
                || distanciaX / LIMIAR_DESVIO >= distanciaY / LIMIAR_DESVIO_Y)) {
            direcao = desvioX > 0 ? 'direita' : 'esquerda';
        } else {
            direcao = desvioY > 0 ? 'baixo' : 'cima';
        }

        // Se a direção atual ainda está fora do limite, ela continua valendo:
        // sem isso, dois eixos desviados juntos reiniciavam o relógio a cada
        // quadro e a falta nunca era contada.
        if (desvioDesde && direcao !== ladoDesvio && aindaDesviado(ladoDesvio, desvioX, desvioY)) {
            direcao = ladoDesvio;
        }

        if (!desvioDesde || direcao !== ladoDesvio) {
            desvioDesde = agora;
            ladoDesvio = direcao;
            avisouDesvio = false;
            status(`Olhando ${descricaoDirecao(direcao)}...`, true);
            return;
        }

        const tempoNecessario = ehVertical(direcao) ? TEMPO_DESVIO_VERTICAL_MS : TEMPO_DESVIO_MS;
        if (!avisouDesvio && agora - desvioDesde >= tempoNecessario) {
            avisouDesvio = true;
            registrarFaltaDeOlhar(direcao, tempoNecessario);
        }
    }

    function ehVertical(direcao) {
        return direcao === 'baixo' || direcao === 'cima';
    }

    /** O desvio de `direcao` ainda passa do limite neste quadro? */
    function aindaDesviado(direcao, desvioX, desvioY) {
        if (direcao === 'direita') return desvioX >= LIMIAR_DESVIO;
        if (direcao === 'esquerda') return desvioX <= -LIMIAR_DESVIO;
        if (direcao === 'baixo') return desvioY >= LIMIAR_DESVIO_Y;
        if (direcao === 'cima') return desvioY <= -LIMIAR_DESVIO_Y;
        return false;
    }

    /** Texto usado no status e no evento: "para a direita", "para baixo"... */
    function descricaoDirecao(direcao) {
        if (direcao === 'direita') return 'para a direita';
        if (direcao === 'esquerda') return 'para a esquerda';
        if (direcao === 'baixo') return 'para baixo';
        return 'para cima';
    }

    function tratarSemRosto(agora) {
        if (!semRostoDesde) {
            semRostoDesde = agora;
            return;
        }

        // Rosto fora do enquadramento por tempo demais: registra e avisa, mas
        // não conta falta (luz ruim também faz a imagem "sumir").
        if (!avisouSemRosto && agora - semRostoDesde > 8000) {
            avisouSemRosto = true;
            status('Rosto fora do enquadramento', true);
            registrarEvento('rosto_fora', 'A câmera ficou mais de 8 segundos sem encontrar o rosto');
            avisarOlhar(GIFS.olhar, 'MANTENHA O ROSTO NA CÂMERA!', false);
        }
    }

    /** Volta ao normal na hora em que o rosto volta para o centro. */
    function limparDesvio() {
        desvioDesde = 0;
        ladoDesvio = '';
        avisouDesvio = false;
        status('Rosto no centro');
        if (barra) barra.classList.remove('webcam-barra--alerta');

        // O aluno já voltou: tira o aviso da tela em vez de esperar ele sumir.
        const anti = window.AntiCheat;
        if (anti && typeof anti.esconderAviso === 'function') anti.esconderAviso();
    }

    function registrarFaltaDeOlhar(direcao, tempoMs) {
        const segundos = (tempoMs / 1000).toFixed(1).replace('.', ',');
        const descricao = `O aluno desviou o olhar ${descricaoDirecao(direcao)} por mais de ${segundos} segundos`;

        status(`Olhar desviado ${descricaoDirecao(direcao)}`, true);

        const anti = window.AntiCheat;
        if (anti && typeof anti.violacao === 'function') {
            anti.violacao('webcam:olhar', {
                tipoEvento: 'olhar_desviado',
                descricao: descricao,
                aviso: {
                    gif: GIFS.olhar,
                    texto: 'OLHE PARA A PROVA!',
                    // some rápido: o aluno já voltou, não precisa cobrir a questão
                    duracaoMs: DURACAO_AVISO_OLHAR_MS
                },
                grave: true,
                cooldownMs: 8000
            });
        } else {
            registrarEvento('olhar_desviado', descricao);
            avisarOlhar(GIFS.olhar, 'OLHE PARA A PROVA!', false);
        }

        // A borda vermelha acompanha o aviso na tela e sai junto com ele.
        if (barra) {
            barra.classList.add('webcam-barra--alerta');
            setTimeout(() => barra && barra.classList.remove('webcam-barra--alerta'), DURACAO_AVISO_OLHAR_MS);
        }
    }

    function avisarOlhar(gif, texto, bloqueia) {
        const anti = window.AntiCheat;
        if (anti && typeof anti.exibirOverlay === 'function') {
            anti.exibirOverlay({ gif: gif, texto: texto, bloqueia: bloqueia }, '');
        }
    }

    /* ============= INICIALIZAÇÃO ============= */

    // A câmera é pedida antes de tudo: o portão da tela cheia só aparece depois
    // (o anti-cola espera a promessa "liberacao" para chamar o portão dele).
    mostrarPortao();

    // Ao finalizar a prova, desliga a câmera (some a luz da webcam).
    document.addEventListener('submit', (evento) => {
        const form = evento.target;
        const ehFinalizacao = !!(form.querySelector && form.querySelector('input[name="acao"][value="finalizar"]'));
        if (ehFinalizacao) setTimeout(pararCamera, 800);
    });

    window.addEventListener('beforeunload', pararCamera);

    window.WebcamGuard = {
        liberacao,
        ligada: () => !!stream,
        /** O portão da câmera está na tela? O anti-cola usa isso para não
         *  contar falta enquanto o aluno ainda está liberando a câmera. */
        gateAberto: () => !!(portao && portao.classList.contains('is-visible')),
        /**
         * Diagnóstico usado pelos testes automatizados (não desliga proteção
         * nenhuma, só permite conferir a análise e o contador de desvios).
         */
        diagnostico: {
            analisarPixels,
            avaliarAmostra,
            estado: () => ({
                ligada: !!stream,
                calibrada: baseCentro !== null,
                baseCentro,
                baseCentroY,
                baseAlturaY,
                baseProporcao,
                desvioDesde,
                ladoDesvio,
                rostoForaDesde: semRostoDesde
            })
        }
    };
})();

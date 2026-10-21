# Sistema de Avaliação Acadêmica

Sistema web completo para aplicação de provas acadêmicas com mecanismos anti-cola, desenvolvido em PHP, MySQL, HTML, CSS e JavaScript.

## 🚀 Funcionalidades

### Para Alunos
- **Login seguro** por usuário **ou** e-mail, com sessões protegidas
- **Avaliação cronometrada** com timer visual
- **10 questões intercaladas**: 5 objetivas (múltipla escolha / verdadeiro-falso) e 5 dissertativas
- **Navegação entre questões** com painel lateral
- **Salvamento automático** das respostas (inclusive das dissertativas, enquanto o aluno digita)
- **Finalização controlada** com confirmação
- **Tela de conclusão** da prova e **visualização de resultado** com detalhes por questão
- **Avaliação do site** com 5 estrelas + comentário

### Para Administradores
- **Dashboard** com estatísticas gerais
- **Gerenciamento de questões** (CRUD completo, incluindo questões dissertativas)
- **Gerenciamento de usuários** (alunos e admins)
- **Visualização de avaliações** com detalhes e respostas dissertativas
- **Monitoramento de eventos suspeitos** (anti-cola)
- **Avaliação do site** com 5 estrelas + comentário

### Mecanismos Anti-Cola (Segurança)

Em `assets/js/anti-cheat.js`. **Toda violação mostra um gif em tela cheia, escurecendo o site, e é registrada no banco.**

**Modo prova (tela cheia obrigatória):** ao abrir a prova aparece o portão *MODO PROVA — TELA CHEIA OBRIGATÓRIA*
(com gif). O aluno só começa depois de entrar em tela cheia. Se o navegador recusar a tela cheia, a prova não
trava (para não inviabilizar o aluno) — a recusa é registrada como `tela_cheia_negada` e a barra de status
passa a alertar em vermelho.

**Webcam obrigatória:** em `assets/js/webcam.js`. Ao abrir a prova, **antes** da tela cheia, aparece o portão
*MODO PROVA — CÂMERA OBRIGATÓRIA* (com o gif *cute stay gold* no meio). Sem liberar a câmera a prova não
começa — a recusa fica registrada como `webcam_negada`. Depois de liberar, a câmera aparece ao vivo no topo
da página durante toda a prova: gif *stay gold* à esquerda, vídeo no meio e gif *weaving aston machan* à
direita. Se a câmera for desligada no meio da prova, o portão volta e conta **falta grave**
(`webcam_desligada`). A câmera é desligada sozinha quando a prova é finalizada.

Nada conta falta antes de a prova começar: enquanto o portão da câmera (ou o da tela cheia) está na tela, o
foco que o **prompt de permissão do Chrome** rouba não é tratado como saída da prova.

**Detecção de olhar para o lado:** a cada 0,25s o quadro da câmera é analisado. O sistema calibra a posição
normal do rosto nos primeiros 3s e, se o rosto ficar desviado do centro por mais de 1s, conta **falta
grave** (`olhar_desviado`) e mostra o gif *OLHE PARA A PROVA!* (que some em 2,5s). Quando o rosto volta para
o centro o aviso e a borda vermelha saem na hora. Ficar 8s sem rosto no enquadramento registra
`rosto_fora` e avisa, sem contar falta. A análise usa a API `FaceDetector` quando o navegador tem; sem ela,
usa o centro de massa da região de pele do quadro — pega desvios claros de cabeça (virar para uma segunda
tela ou celular ao lado) e pode errar em ambiente escuro. Nenhum site consegue medir para onde os olhos
apontam sem rastreio facial. A câmera exige **https** ou `localhost`.

**Barra de status do anti-cola:** um cartão fixo no canto da tela mostra `🛡️ Anti-cola ativo`, o contador de
**faltas graves** (`x de 5`) e o estado da tela cheia. Quando o aluno está fora da tela cheia a barra fica
vermelha e o botão *Voltar à tela cheia* aparece. Assim o anti-cola fica visível durante toda a prova, em vez
de só se manifestar quando algo acontece.

**Bloqueado de verdade (o aluno não consegue fazer):**
- ✅ Menu de contexto (botão direito), seleção de texto e arrastar conteúdo
- ✅ Copiar e recortar (Ctrl+C / Ctrl+X / Cmd+C)
- ✅ **Colar** (Ctrl+V, Cmd+V, Shift+Insert, menu do navegador e toque) — a cada tentativa aparece um gif no
  meio da tela, escurecendo um pouco o site e com a frase em cima, sumindo sozinho em 4,5s:
  1ª tentativa **silly-tamamo** *"cole não meu rei, desista e morra"*, 2ª **scary-oguri** *"O QUE EU TE FALEI AGORA?"*,
  3ª volta para o primeiro e fica nesse loop. O detalhe técnico não aparece para o aluno — só no relatório do professor.
- ✅ Recarregar (F5, Ctrl+R, Ctrl+Shift+R), Ctrl+S, Ctrl+P, Ctrl+F, Ctrl+L
- ✅ Ver código fonte e DevTools (F12, Ctrl+U, Ctrl+Shift+I/J/C/K, Cmd+Shift+I)
- ✅ Abertura de novas abas/janelas por código (`window.open`, Ctrl+clique, botão do meio, `target="_blank"`)
- ✅ Sair da tela cheia (Esc/F11): conta **falta grave**, mostra o gif *TELA CHEIA É OBRIGATÓRIA!* e o sistema
  **volta para a tela cheia** — na próxima interação do aluno ou pelo botão da barra de status
- ✅ Teclas do Windows entregues ao navegador (Win+X, Win+D, Win+E, Win+R, Win+L, Ctrl+Esc, Alt+F4...)
  e qualquer outra combinação com a tecla Windows → aviso *A TECLA WINDOWS ESTÁ BLOQUEADA!* e **falta grave**
  (um Win+X conta uma falta só, não duas)
- ✅ Recarregar/fechar a página durante a prova (confirmação do navegador)

**Avisos leves não atrapalham a prova:** colar, tecla bloqueada, copiar, botão direito e seleção escurecem a
tela só um pouco, **não capturam clique** e somem sozinhos. Seleção de texto só é denunciada quando o aluno
**arrasta o mouse** sobre o enunciado — clicar numa alternativa para responder nunca é tratado como violação.

**Detectado, avisado com gif e contado como falta (o navegador não permite bloquear):**
- ✅ Troca de aba pelo mouse ou Ctrl+Tab (`visibilitychange`)
- ✅ Janela perdeu o foco: Alt+Tab, outro programa, tecla Windows (`blur`)
- ✅ DevTools aberto (tamanho da janela + pausa do `debugger`)

**Importante — o que nenhum site consegue bloquear:** teclas do próprio Windows (Win+X, Alt+Tab,
Win+D), trocar de aba clicando nela, a tecla **Esc** (por regra do navegador ela sempre sai da tela cheia)
e fechar a janela. Nesses casos o sistema não impede a ação, mas **detecta, mostra o gif e conta a falta**.
A página volta para a tela cheia na primeira interação seguinte.

**Limite de faltas:** cada violação *grave* (troca de aba/janela, sair da tela cheia, DevTools) conta uma
falta. Ao atingir `LIMITE_FALTAS_ANTI_CHEAT`, a prova é **finalizada automaticamente por fraude**: o aluno vê
o gif "PROVA FINALIZADA POR FRAUDE!" e a prova é encerrada sozinha, ficando com o status **Fraude**
(badge vermelho) no painel do professor, com o relatório completo das violações.
Para desativar o limite, use `0` em `includes/config.php`.

**Como o professor vê tudo:** *Admin → Avaliações* (filtro "🚨 Encerrada por Fraude") ou direto em
*Ver* uma avaliação → alerta no topo do relatório + tabela de eventos com tipo e hora de cada violação.

**Outras proteções:**
- ✅ Gifs de alerta **pré-carregados** ao abrir a prova (são grandes, até 6 MB: sem isso o aviso aparecia sem imagem)
- ✅ CSS/JS com `?v=<data de modificação>` — o navegador nunca roda uma versão antiga do `anti-cheat.js` em cache
- ✅ Registra todos os eventos suspeitos no banco (visíveis em *Eventos Suspeitos* no painel admin)
- ✅ Timer servidor-side (não confiável apenas no cliente)
- ✅ CSRF protection em todos formulários
- ✅ Prepared statements (prevenção SQL Injection)
- ✅ Password hashing com bcrypt

## 📋 Requisitos

- PHP 8.0+
- MySQL 5.7+ / MariaDB 10.3+
- Servidor web (Apache/Nginx) com mod_rewrite
- Extensões PHP: PDO, pdo_mysql, session, mbstring

## 🛠️ Instalação

### 1. Clone/Extraia o projeto
```bash
# No servidor web (ex: /var/www/html/)
git clone <repo> avaliacao-web
# ou extraia os arquivos em /var/www/html/avaliacao-web
```

### 2. Configure o banco de dados
```bash
# No MySQL, execute o script (o parâmetro de charset evita acentos quebrados):
mysql -u root -p --default-character-set=utf8mb4 < schema.sql
```

### 3. Configure a conexão
Edite `includes/config.php` com seus dados de banco:
```php
define('DB_HOST', 'localhost');
define('DB_NAME', 'avaliacao_web');
define('DB_USER', 'seu_usuario');
define('DB_PASS', 'sua_senha');
```

### 4. Configure o servidor web

**Apache (.htaccess já incluído):**
```apache
# Certifique-se que AllowOverride All está habilitado
# e mod_rewrite está ativo
```

**Nginx:**
```nginx
location /avaliacao-web/ {
    try_files $uri $uri/ /avaliacao-web/index.php?$query_string;
}
```

### 5. Permissões
```bash
chmod -R 755 /var/www/html/avaliacao-web
# Certifique-se que o PHP pode escrever sessões
```

### 6. Acesse
```
http://seu-servidor/avaliacao-web/login.php
```

## 🔑 Credenciais Padrão

| Tipo | E-mail | Senha |
|------|--------|-------|
| Admin | admin@sistema.com | admin123 |
| Aluno | joao@aluno.com | aluno123 |
| Aluno | maria@aluno.com | aluno123 |
| Aluno | pedro@aluno.com | aluno123 |

⚠️ **Altere as senhas padrão imediatamente em produção!**

## 📁 Estrutura do Projeto

```
avaliacao-web/
├── admin/                 # Área administrativa
│   ├── dashboard.php      # Dashboard admin
│   ├── questoes.php       # CRUD questões
│   ├── usuarios.php       # CRUD usuários
│   ├── avaliacoes.php     # Listagem avaliações
│   ├── ver_avaliacao.php  # Detalhes avaliação
│   └── eventos.php        # Eventos suspeitos
├── api/                   # Endpoints AJAX
│   ├── registrar_evento.php
│   ├── obter_respostas.php
│   └── enviar_feedback.php
├── assets/
│   ├── css/
│   │   ├── style.css      # Estilos base
│   │   ├── avaliacao.css  # Estilos prova
│   │   ├── resultado.css  # Estilos resultado
│   │   └── admin.css      # Estilos admin
│   ├── gifs/              # Gifs usados nos avisos e reações
│   └── js/
│       ├── avaliacao.js   # Lógica da prova
│       ├── anti-cheat.js  # Mecanismos anti-cola
│       ├── webcam.js      # Webcam obrigatória + detecção de olhar para o lado
│       ├── paste-guard.js # Bloqueio de colar (com aviso em gif)
│       └── feedback.js    # Estrelas e reação da avaliação do site
├── includes/
│   ├── config.php          # Configurações + DB + Helpers
│   ├── functions.php       # Regras de negócio
│   └── feedback_widget.php # Área de avaliação do site (estrelas)
├── schema.sql             # Schema do banco
├── login.php              # Login
├── dashboard.php          # Dashboard aluno
├── iniciar_avaliacao.php  # Inicia prova
├── avaliacao.php          # Página da prova
├── concluido.php          # Tela de conclusão da prova
├── resultado.php          # Resultado
├── logout.php             # Logout
└── README.md
```

## 🗄️ Schema do Banco

### Tabelas Principais
- **usuarios** - Alunos e administradores
- **questoes** - Banco de questões (objetivas e dissertativas)
- **alternativas** - Opções de resposta das questões objetivas
- **avaliacoes** - Sessões de prova (nota, acertos, erros, status: `em_andamento`, `finalizada`, `tempo_esgotado`, `cancelada`, `fraude`)
- **avaliacao_questoes** - Questões sorteadas para cada prova
- **respostas** - Respostas dos alunos (alternativa e/ou texto dissertativo)
- **eventos_suspeitos** - Logs de segurança
- **feedbacks** - Avaliações do site (estrelas + comentário)

## 🔧 Personalização

### Tempo da prova
Em `includes/config.php`:
```php
define('TEMPO_LIMITE_PADRAO', 3600); // 1 hora em segundos
```

### Limite de faltas do anti-cola
Em `includes/config.php`:
```php
define('LIMITE_FALTAS_ANTI_CHEAT', 5); // 0 = nunca finaliza automaticamente
```

### Número de questões
Em `includes/config.php`:
```php
define('PROVA_TOTAL_OBJETIVAS', 5);      // múltipla escolha / verdadeiro-falso
define('PROVA_TOTAL_DISSERTATIVAS', 5);  // correção manual do professor
```
As questões são sorteadas do banco e intercaladas (objetiva, dissertativa, objetiva, ...).

### Adicionar questões
Via painel admin: `/admin/questoes.php?acao=novo`

- As questões **objetivas** exigem pelo menos 2 alternativas, com a(s) correta(s) marcada(s).
- As questões **dissertativas** não usam alternativas: o aluno responde em um campo de texto
  e o campo "Explicação" funciona como gabarito para a correção manual.

## 🛡️ Segurança Implementada

1. **Autenticação**: `password_hash()` + `password_verify()` (bcrypt cost 10)
2. **Sessões**: HttpOnly, Secure, SameSite=Strict, regeneração periódica
3. **CSRF**: Tokens em todos formulários + headers AJAX
4. **SQL Injection**: Prepared statements em todas queries
5. **XSS**: `htmlspecialchars()` em todas saídas (`e()` helper)
6. **Anti-cola**: 10+ mecanismos client + server-side logging
7. **Controle de acesso**: Middleware `requerLogin()`, `requerAdmin()`, `requerAluno()`

## 📝 Licença

Projeto educacional para fins acadêmicos.

## 👥 Desenvolvimento em Equipe

Sugestão de divisão (conforme enunciado):
- **Aluno 1** - Banco de Dados (schema.sql, queries)
- **Aluno 2** - Backend (config.php, functions.php, API)
- **Aluno 3** - Frontend (CSS, JS, interfaces)
- **Aluno 4** - Segurança/Integração (anti-cheat.js, testes, deploy)

---

**Desenvolvido para a disciplina de Programação Web** 🎓
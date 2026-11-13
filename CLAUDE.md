# contabilidade-pro — guidelines

Tema WordPress da Entre Números. **Ainda não está em produção** — nem o
site público nem a Área de Cliente têm os textos finais aprovados
(`cp_portao_site_publico()` em `functions.php` fecha o site ao público por
isso). Isso dá margem para consolidar a estrutura agora, antes de haver
tráfego real a proteger.

Este tema tem **três superfícies distintas dentro do mesmo
repositório**, e as diferenças entre elas são propositadas, não
inconsistência:

| | Site público | Área de Cliente | Backoffice |
|---|---|---|---|
| Ficheiros | `front-page.php`, `page.php`, `page-servicos.php`, `header.php`, `footer.php` | `page-area-de-cliente.php` + `inc/area-cliente.php` + `inc/area-cliente-vistas.php` | `page-backoffice.php` + `inc/area-cliente-backoffice.php` |
| Público | Visitantes anónimos | Clientes autenticados (`subscriber`) | Equipa (`administrator` ou papel `equipa_en`) |
| Layout | Usa `header.php`/`footer.php`, `wp_head()`/`wp_footer()` normais | Documento HTML autónomo, sem `get_header()`/`get_footer()` | Igual à Área de Cliente |
| CSS | `assets/css/main.css`, prefixo de classes `c-` / sem prefixo | `assets/css/area-cliente.css`, prefixo `ac-` | **Partilha** `area-cliente.css` com a Área de Cliente (mesma família "app autenticada") |
| Variáveis de cor | `--c-*` (`--c-accent`, `--c-bg`, `--c-ink`, …) | `--ac-*` (`--ac-navy`, `--ac-gold`, `--ac-muted`, …) — **paleta própria**, não reutiliza as `--c-*` | Mesmas `--ac-*` da Área de Cliente |
| Tema claro/escuro | `localStorage['cp-theme']`, botão `.theme-toggle` | mesma chave `localStorage['cp-theme']`, botão `.ac-theme-toggle`, lógica em `assets/js/ac-theme-toggle.js` | Mesmo botão/ficheiro partilhado |
| Estilo de marcação | Classes CSS em `main.css` | Maioritariamente `style="..."` inline por elemento, só os padrões repetidos (`.ac-card`, `.ac-badge`, `.ac-table`, …) têm classe | Igual à Área de Cliente |

Ao editar o site público, **não "unificar" com as outras duas** sem
decisão explícita — é uma superfície com público, hierarquia visual e
ciclo de vida diferente. Área de Cliente e Backoffice já partilham CSS/JS
de propósito (ambas são "apps" autenticadas da mesma família); a ponte
deliberada entre as três é a chave de tema (`cp-theme`) e o prefixo comum
`cp_`/`cp-` nas funções e classes.

---

## Site público

- Toda a configuração editável vive no Customizer (`cp_customizer()` em
  `functions.php`) — contacto, hero, "Sobre nós". Não hardcodes textos que
  já têm campo lá; usa `cp_opt( 'chave', 'valor_por_omissão' )`.
- Cores só em `assets/css/main.css`, variáveis `--c-*` no `:root` /
  `:root[data-theme="dark"]`. Nunca cor literal fora dali.
- `page-servicos.php` gera o conteúdo a partir do array `$all_services` —
  o título/conteúdo da página no wp-admin é ignorado de propósito.
- Enquanto o site estiver fechado (`cp_portao_site_publico()`), qualquer
  alteração a páginas novas deve ter em conta que visitantes anónimos são
  redirecionados para a home — só a Área de Cliente e `wp-admin` escapam a
  isso.
- Formulário de contacto é nativo (`admin_post_cp_contact`), sem plugin.
  Mantém esse padrão (nonce + sanitização + `wp_mail`) se adicionares
  outro formulário no site público.

## Área de Cliente

- **Fonte de verdade é a app interna "Work"**, não o WordPress. O
  WordPress é uma caixa de correio de ida e volta: a Work escreve dados
  (empresas, pendentes, conta corrente, mapas de gestão, recibos) via REST
  (`/wp-json/cp/v1/...`, ver `inc/area-cliente.php`), o cliente responde
  no site, e a Work vai buscar as respostas e confirma-as — depois disso
  o WordPress apaga a sua cópia. Nunca tratar postmeta da Área de Cliente
  como dado definitivo de longo prazo.
- Autenticação dos endpoints REST é por **Application Passwords** do
  WordPress, permissão mínima por endpoint (`edit_posts`, `create_users`).
  Qualquer endpoint novo precisa de um `permission_callback` explícito —
  nunca `__return_true`.
- **Matriz de ferramentas por empresa** (`cp_ac_ferramentas()` +
  `_cp_ferramentas_ativas` no post da empresa) é o mecanismo de feature
  flag: uma ferramenta nova fica OFF em todas as empresas até ser ligada
  manualmente em wp-admin → Área de Cliente. Ao adicionar uma ferramenta
  nova, implementa sempre:
  1. entrada em `cp_ac_ferramentas()` (label, grupo, ícone);
  2. `cp_ac_vista_<chave>()` em `area-cliente-vistas.php`;
  3. gate com `cp_ac_tem_ferramenta( 'chave' )` antes de mostrar dados —
     nunca assumir que está ativa.
- Enquanto não há dados reais de uma ferramenta, a vista mostra **estado
  vazio honesto** (`cp_ac_vazio()`), nunca números/documentos inventados.
  Mantém esse princípio ao ligar fontes de dados reais: um erro de sync
  deve resultar em vazio, não em dado fantasma.
- `cp_empresa_ativa` (usermeta) guarda a empresa selecionada, mas é sempre
  revalidada contra `cp_ac_user_empresas()` — nunca confiar nela sem
  confirmar que o utilizador ainda tem acesso a essa empresa.
- Cores e layout em `assets/css/area-cliente.css` (partilhado com o
  Backoffice — ver secção própria abaixo), variáveis `--ac-*`. Nunca
  importar `main.css` aqui nem o contrário.
- **Notificações push** (`inc/area-cliente-push.php`): Web Push (VAPID +
  `aes128gcm`) implementado só com `ext-openssl`, **sem** nenhuma
  dependência de Composer em runtime — o deploy (`.cpanel.yml`) é uma
  cópia de ficheiros, sem `composer install`, e `vendor/` está no
  `.gitignore`. Se alguma vez for preciso trocar isto por uma biblioteca
  (ex. `minishlink/web-push`), o deploy tem de mudar primeiro (comitar
  `vendor/` ou acrescentar `composer install --no-dev` ao `.cpanel.yml`).
  Chaves VAPID vivem na opção `cp_push_vapid_keys` (geradas uma única vez,
  lazy). `/sw-push.js` e `/manifest.webmanifest` são servidos à mão em
  `template_redirect`, com o mesmo padrão de `cp_portao_site_publico()` —
  não usam `add_rewrite_rule`. Antes de disparar uma notificação nova,
  confirma sempre `cp_ac_empresa_tem_ferramenta( $empresa_id, 'chave' )`
  primeiro — nunca notificar sobre uma ferramenta que a empresa não tem
  ligada na matriz. `cp_push_notificar_empresa()` notifica os clientes de
  uma empresa; `cp_push_notificar_equipa()` (mesmo ficheiro) notifica a
  equipa — ver secção "Backoffice".
- **Lembretes** (`inc/area-cliente-tarefas.php`): ao contrário das outras
  ferramentas, não é "Work escreve, cliente responde" — é uma lista
  partilhada que tanto o cliente como a equipa podem escrever
  diretamente (postmeta `_cp_tarefas` no post da empresa). Escrita por
  formulário `admin-post.php` (mesmo padrão de
  `cp_ac_handle_pendente_resposta()`), nunca REST+fetch — não precisa de
  evitar reload de página. Quem pode concluir um lembrete:
  `cp_ac_tarefa_pode_concluir()` — o próprio criador, ou qualquer
  utilizador com `cp_backoffice_utilizador_tem_acesso()`. A UI
  (`cp_ac_render_tarefas()`) é partilhada entre a Área de Cliente e o
  Backoffice — ao estender esta funcionalidade, mantém os dois lados a
  chamar a mesma função em vez de duplicar markup. Na Área de Cliente não
  tem página/vista própria — gerido diretamente dentro do Dashboard
  (`cp_ac_vista_dashboard()`), de propósito: é um "to-do rápido" para
  deixar notas, não uma gestão de tarefas a sério (daí o nome na UI ser
  "Lembretes", não "Tarefas" — os nomes internos no código continuam
  `tarefa`/`tarefas` por não serem visíveis ao utilizador; só renomear
  também o código se isto um dia crescer para uma gestão de tarefas real).
- **Mensagens** (`inc/area-cliente-mensagens.php`): a única ferramenta que
  foge ao padrão "admin-post.php + reload" — a lista tem de atualizar
  sozinha, sem refresh. Por isso lê e escreve por REST (`GET`/`POST
  cp/v1/conversas` e `cp/v1/mensagens`), com `assets/js/ac-mensagens.js`
  a fazer polling (a cada 12s, pausado com `document.hidden`) e o envio
  por `fetch`, em vez do formulário clássico — nenhuma outra ferramenta
  deve copiar este padrão só porque está ali ao lado; é a exceção, não
  o novo normal. Mesma lógica de resolução de empresa anti-adulteração
  das outras ferramentas partilhadas (`cp_ac_mensagem_resolver_empresa()`,
  cópia de `cp_ac_tarefa_resolver_empresa()`).
  **Várias conversas por empresa**, cada uma com assunto — não é um fio
  único. `_cp_conversas` guarda as conversas, `_cp_mensagens` guarda
  todas as mensagens de todas as conversas da empresa (cada uma com
  `conversa_id`); nunca ler/escrever mensagens sem validar primeiro
  `cp_ac_conversa_pertence( $conversa_id, $empresa_id )` — um
  `conversa_id` vindo de um pedido nunca é de confiar sozinho. Mensagens
  anteriores a esta mudança (sem `conversa_id`) são agrupadas, lazy e
  uma única vez, numa conversa "Geral" por `cp_ac_conversas_migrar_legado()`
  — nunca escrever outra migração à parte, isto já trata disso na
  primeira leitura.
  Sem página própria na Área de Cliente (mesma razão que Lembretes) —
  vive no dropdown do utilizador (lista de conversas) e numa modal
  partilhada só, preenchida por JS a cada conversa aberta
  (`#cp-conversa-modal` — não tentar pré-renderizar todas as conversas
  em PHP). O Backoffice, por já ter o seu próprio espaço de ecrã, usa
  antes um `<select name="conversa_id">` com navegação por query-string,
  igual ao seletor de empresa ao lado — não precisa da modal.
  Lidas/por ler são por conversa (usermeta `cp_mensagens_lidas`), não por
  empresa: abrir uma conversa marca só essa como lida; o badge do avatar
  soma as não lidas de todas as conversas da empresa ativa.

- **Documentos** (`inc/area-cliente-documentos.php`): o upload do cliente
  segue para o ERP (Contabilidade Digital da CentralGest) **pela Work**. O
  WordPress nunca fala com a CentralGest. A Work faz polling a
  `GET cp/v1/documentos/novos` e confirma em `POST cp/v1/documentos/confirmar`.
  **Exceção ao "o WordPress apaga a sua cópia"** descrito acima: aqui a
  confirmação só grava `_cp_doc_work_recebido` no anexo e **não apaga
  nada** até haver indicação em contrário (decisão de 2026-11-12). Depois
  de recebido, o cliente já não pode apagar o documento, só a equipa. Ao
  cliente só se mostra "enviado"; o estado na CentralGest é controlado na
  Work. Só PDF/JPG/PNG (`cp_ac_documento_mimes_permitidos()`), até 10 MB
  (filtro `cp_ac_documento_max_mb`, valor ainda por avaliar).
  **Destino escolhido pelo cliente** (decisão de 2026-11-13): a Work envia
  as pastas da empresa na CentralGest (`POST cp/v1/documentos/pastas`,
  lista completa, meta `_cp_doc_pastas` = `[{id, nome, anos}]`). No upload o
  cliente escolhe a pasta, o ano e o mês. Os anos têm de existir na pasta e
  o mês não pode ser futuro (`cp_ac_documento_destino_valido()`). A escolha
  fica nas metas `_cp_doc_pasta`, `_cp_doc_ano` e `_cp_doc_mes`, e vai para
  a Work em `/documentos/novos`. **Sem pastas, o upload fica escondido**
  (formulário, modal e item do botão "+"; ver
  `cp_ac_documento_upload_disponivel()`).

## Backoffice

- **Âmbito desta fase, de propósito limitado**: central de notificações
  + atalhos para o `wp-admin`. Não tem (ainda) nenhum ecrã de gestão
  próprio — confirmar/editar continua só em `wp-admin`. Não acrescentar
  ações novas aqui sem decisão explícita de alargar o âmbito.
- **Acesso**: capacidade `cp_backoffice`, concedida ao papel `equipa_en`
  (criado uma vez em `cp_backoffice_registar_papel_equipa()`, hook
  `after_switch_theme`). Administradores têm acesso automático via
  `manage_options`. Atribuir o papel a alguém da equipa é só escolhê-lo
  no ecrã nativo wp-admin → Utilizadores → Editar — nenhuma UI nova.
  `cp_backoffice_utilizador_tem_acesso( $user_id )` é o gate único; usa-o
  em qualquer sítio novo que precise de distinguir equipa de clientes.
- **Feed de eventos**: `cp_backoffice_registar_evento()`/
  `cp_backoffice_listar_eventos()` (`inc/area-cliente-backoffice.php`)
  guardam na opção `cp_eventos_staff` (array, mais recente primeiro,
  cortado a 100 — mesma escala/padrão das listas da Área de Cliente,
  não precisa de tabela nova). Nunca escrever diretamente nessa opção —
  passa sempre por `cp_push_notificar_equipa()`, que regista o evento
  **e** envia o push num só sítio, para os dois nunca ficarem
  dessincronizados.
- Precisa de uma página WordPress com slug `backoffice` (mesmo padrão
  de `/servicos`/`/area-de-cliente` descrito no `README.md` do tema) —
  não existe por omissão, tem de ser criada em wp-admin.
- Antes de ligar um gatilho novo (ex.: quando o ecrã Documentos tiver
  upload real), repete exatamente o padrão já usado em
  `cp_ac_handle_pendente_resposta()`: só chamar
  `cp_push_notificar_equipa()` depois de o dado estar mesmo gravado.
- **Excecão deliberada ao "não tem ecrã de gestão próprio"**: as secções
  "Lembretes" e "Mensagens" do Backoffice são gestão a sério (criar/
  concluir lembretes, trocar mensagens, de qualquer empresa), decidido
  explicitamente porque é o mesmo mecanismo partilhado com a Área de
  Cliente (`cp_ac_render_tarefas()`/`cp_ac_render_mensagens()`, ver
  secção "Área de Cliente" acima), não um ecrã novo construído do zero.
  Partilham o mesmo seletor de empresa (`$bo_empresa_id`) — uma escolha
  só, não uma por secção. Não tratar isto como precedente para
  adicionar outra gestão aqui sem decisão igualmente explícita.

## Convenções gerais (nas três superfícies)

- Prefixo de função: `cp_` no geral, `cp_ac_` para tudo da Área de
  Cliente. Segue isto em código novo.
- Todo o texto visível passa por `__()`/`_e()`/`esc_html__()` com o
  domínio `contabilidade-pro` — o site é só em português, mas o domínio
  fica pronto para i18n.
- Nunca imprimir `$_GET`/`$_POST` sem `sanitize_*`/`absint`/`esc_*`
  primeiro. Todas as mutações (formulários, REST POST) exigem nonce ou
  Application Password — sem excepção, mesmo em endpoints "internos".
- `esc_url()` em todos os `href`/`action`, `esc_attr()` em todos os
  atributos, `esc_html()` em todo o texto escapado — o código existente é
  consistente nisto; não introduzir excepções.
- **Mobile (Área de Cliente/Backoffice)**: abaixo de 860px (ver media
  query em `assets/css/area-cliente.css`) o menu lateral (`#ac-sidebar`)
  vira painel deslizante, aberto pelo botão `#ac-sidebar-toggle`
  (`assets/js/ac-mobile-nav.js`) — qualquer página nova com este app
  shell precisa dos três elementos (`#ac-sidebar`, `#ac-sidebar-toggle`,
  `#ac-sidebar-backdrop`) para o menu funcionar em telemóvel. Grelhas de
  cartões usam sempre `repeat(auto-fit,minmax(...))`, nunca
  `repeat(<número fixo>,...)` — um número fixo de colunas não cabe num
  ecrã estreito e causa scroll horizontal (era o bug do Dashboard antes
  desta correção).

## Testes automáticos

Ver [tests/README.md](tests/README.md) para como correr. Resumo: PHPUnit
+ Brain Monkey para lógica pura (sem base de dados), com o caminho
preparado para adicionar testes de integração (`wp-env` + base de dados
real) mais tarde, quando o site se aproximar de produção — nessa altura
vale sobretudo testar os endpoints REST de ponta a ponta (autenticação,
upsert de empresa/utilizador, matriz de ferramentas) e o fluxo de
pendentes (resposta do cliente → confirmação da Work).

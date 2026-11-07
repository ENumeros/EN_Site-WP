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

## Testes automáticos

Ver [tests/README.md](tests/README.md) para como correr. Resumo: PHPUnit
+ Brain Monkey para lógica pura (sem base de dados), com o caminho
preparado para adicionar testes de integração (`wp-env` + base de dados
real) mais tarde, quando o site se aproximar de produção — nessa altura
vale sobretudo testar os endpoints REST de ponta a ponta (autenticação,
upsert de empresa/utilizador, matriz de ferramentas) e o fluxo de
pendentes (resposta do cliente → confirmação da Work).

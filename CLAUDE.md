# contabilidade-pro — guidelines

Tema WordPress da Entre Números. **Ainda não está em produção** — nem o
site público nem a Área de Cliente têm os textos finais aprovados
(`cp_portao_site_publico()` em `functions.php` fecha o site ao público por
isso). Isso dá margem para consolidar a estrutura agora, antes de haver
tráfego real a proteger.

Este tema tem **duas aplicações distintas dentro do mesmo repositório**,
e as diferenças entre elas são propositadas, não inconsistência:

| | Site público | Área de Cliente |
|---|---|---|
| Ficheiros | `front-page.php`, `page.php`, `page-servicos.php`, `header.php`, `footer.php` | `page-area-de-cliente.php` + `inc/area-cliente.php` + `inc/area-cliente-vistas.php` |
| Público | Visitantes anónimos | Clientes autenticados (`subscriber`) |
| Layout | Usa `header.php`/`footer.php`, `wp_head()`/`wp_footer()` normais | Documento HTML autónomo, sem `get_header()`/`get_footer()` |
| CSS | `assets/css/main.css`, prefixo de classes `c-` / sem prefixo | `<style>` inline no topo de `page-area-de-cliente.php`, prefixo `ac-` |
| Variáveis de cor | `--c-*` (`--c-accent`, `--c-bg`, `--c-ink`, …) | `--ac-*` (`--ac-navy`, `--ac-gold`, `--ac-muted`, …) — **paleta própria**, não reutiliza as `--c-*` |
| Tema claro/escuro | `localStorage['cp-theme']`, botão `.theme-toggle` | mesma chave `localStorage['cp-theme']`, botão `.ac-theme-toggle` — sincronizados de propósito |
| Estilo de marcação | Classes CSS em `main.css` | Maioritariamente `style="..."` inline por elemento, só os padrões repetidos (`.ac-card`, `.ac-badge`, `.ac-table`, …) têm classe |

Ao editar um dos dois lados, **não "unificar" com o outro** sem decisão
explícita — são duas superfícies com públicos, hierarquia visual e ciclo
de vida diferentes. A única ponte deliberada entre os dois é a chave de
tema (`cp-theme`) e o prefixo comum `cp_`/`cp-` nas funções e classes.

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
- Cores e layout só no `<style>` de `page-area-de-cliente.php`, variáveis
  `--ac-*`. Se este bloco crescer muito mais, considerar extrair para
  `assets/css/area-cliente.css` — mas mantendo o prefixo `ac-` e sem
  importar `main.css`.

## Convenções gerais (ambos os lados)

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

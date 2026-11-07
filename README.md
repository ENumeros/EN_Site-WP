# Tema WordPress — Entre Números

Tema do site da Entre Números — contabilidade, fiscalidade e recursos humanos.

---

## Configuração inicial

### 1. Nome do site e descrição
→ **Definições → Geral** → preenche o "Título do site" e a "Descrição"

### 2. Contacto
→ **Aparência → Personalizar → Informações do Gabinete → Contacto & Morada**
(telefone, e-mail, LinkedIn/Instagram/Facebook, URL da Área de Cliente, link do Cal.com)

O calendário de marcação de reuniões (Cal.com) aparece na secção de Contacto
da página inicial. O campo "Cal.com — link do evento" deve ter o formato
`utilizador/evento` (ex.: `enumeros/reuniao`), sem o `https://cal.com/`.

As redes sociais no rodapé só aparecem depois de preencheres o respetivo URL
aqui — por defeito estão vazias, por isso não aparece nada. O atalho
"Área de Cliente" no cabeçalho aponta para `#` por defeito; atualiza o URL
aqui quando a área de cliente estiver pronta.

### 3. Hero (texto do topo da página inicial)
→ **Aparência → Personalizar → Informações do Gabinete → Secção Hero**

### 4. Texto "Quem somos"
→ **Aparência → Personalizar → Informações do Gabinete → Secção Sobre Nós**

### 5. Logótipo
→ **Aparência → Personalizar → Identidade do Site → Logótipo**
(se não definires um logótipo aqui, o tema usa a imagem `assets/img/logo.png`)

### 6. Menus
→ **Aparência → Menus** → cria um menu com:
- "Serviços" → `/servicos`
- "Sobre" → `/#sobre` (âncora na página inicial)
- "Contactos" → `/#contacto` (âncora na página inicial)

Atribui-o à localização **Menu Principal**.

---

## Página de Serviços

O tema já tem o design pronto em `page-servicos.php`. Para ele aparecer,
cria uma página em **Páginas → Adicionar nova** com slug `servicos` (o
título e o conteúdo que escreveres aí não são usados — o design é todo
gerado pelo ficheiro do tema). Os 5 serviços e os respetivos textos estão
no array `$all_services` em `page-servicos.php`.

---

## Backoffice (equipa)

Mesmo princípio: cria uma página com slug `backoffice` para
`page-backoffice.php` aparecer. É uma central de notificações para a
equipa — mostra o que mudou recentemente (ex.: cliente respondeu a um
pendente) com um link direto para o sítio certo no wp-admin. Para dar
acesso a alguém da equipa, edita o utilizador em **Utilizadores** e
escolhe o papel **Equipa Entre Números**.

---

## Personalizações avançadas

### Alterar cores
Abre `assets/css/main.css` e edita as variáveis no topo (`:root { ... }`):
- `--c-accent` → dourado (botões e destaques)
- `--c-brand`  → azul-acinzentado (acento secundário)
- `--c-navy`   → azul profundo (títulos, hero, rodapé)

### Alterar os 2 serviços em destaque na home
Edita o array `$services` em `front-page.php`.

---

## Plugins recomendados

| Plugin | Para quê |
|---|---|
| Contact Form 7 | Formulário de contacto (opcional, não usado por agora) |
| Yoast SEO | Otimização para motores de busca |
| WP Super Cache | Velocidade / cache |
| UpdraftPlus | Backups automáticos |
| Wordfence | Segurança |

---

## Estrutura de ficheiros

```
contabilidade-pro/
├── style.css              ← Cabeçalho do tema WordPress
├── functions.php          ← Configuração, scripts, menus, customizer
├── header.php             ← Cabeçalho + navegação
├── footer.php             ← Rodapé
├── front-page.php         ← Página inicial (hero, serviços, como trabalhamos, sobre, contacto)
├── page.php               ← Páginas genéricas
├── index.php               ← Blog / listagem de artigos
├── single.php              ← Artigo individual
├── 404.php                 ← Página de erro
└── assets/
    ├── css/main.css        ← Todo o design (variáveis, layout, responsivo)
    ├── js/main.js          ← Menu mobile
    └── img/                ← Logótipo, monograma e fotos de fundo
```

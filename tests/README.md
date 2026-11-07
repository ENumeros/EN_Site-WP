# Testes — contabilidade-pro

## Como correr

Este ambiente Local (Flywheel) não tem `php`/`composer` no PATH do
sistema — usa o binário próprio do site. Corre os testes de dentro do
**Site Shell do Local** (Local → site `enumerospt` → ⋮ → "Open Site
Shell"), a partir da pasta do tema:

```bash
cd wp-content/themes/contabilidade-pro
composer install
composer test
```

(`composer test` corre `phpunit`.)

## O que há agora: unitários com Brain Monkey

`tests/Unit/` testa funções do tema **sem** WordPress a sério nem base de
dados — `Brain\Monkey` substitui `add_action`, `get_post_meta`,
`update_post_meta`, `__()`, etc. por stubs configurados em cada teste.
Serve para a lógica pura: a matriz de ferramentas por empresa, regras de
acesso, formatação, validação de parâmetros dos endpoints REST.

`tests/Unit/TestCase.php` é a base comum — já estuba as chamadas de topo
de ficheiro mais frequentes (`add_action`, `register_post_type`,
`register_rest_route`, tradução, sanitização). `AreaClienteTest.php` é o
exemplo a copiar para o próximo ficheiro de `inc/` a testar.

`PushTest.php` testa o módulo de notificações push
(`inc/area-cliente-push.php`): base64url, CRUD de subscrições e as duas
funções de criptografia mais propensas a erro (conversão DER→raw da
assinatura ECDSA, HKDF). **Não testa** o envio real de uma notificação —
isso depende de cifra `aes128gcm` de ponta a ponta e de um endpoint real
do browser, que só se confirma manualmente (ver "Notificações push" em
baixo). Nota de limitação: Brain Monkey só consegue substituir funções
que ainda não estão definidas (as do WordPress) — uma função do próprio
tema já carregada (ex. `cp_push_enviar_subscricao`) não pode ser
estubada a partir de outra função do tema, por isso
`cp_push_notificar_empresa()` não tem teste unitário próprio.

Não testa (de propósito, nesta fase): o HTML gerado pelas `cp_ac_vista_*`,
pedidos HTTP reais aos endpoints REST, nem nada que dependa de uma base
de dados MySQL real.

## Notificações push — verificação manual

Não automatizável (depende de criptografia real + endpoint do browser).
Passos:

1. Ativar a extensão `openssl` no PHP deste site no Local (site →
   aba "PHP" → extensões) — está desligada por omissão nesta instalação.
2. Em `https://enumerospt.local/area-de-cliente/`, autenticado, clicar
   "Ativar notificações" e aceitar a permissão do browser; confirmar no
   separador de rede que `POST /wp-json/cp/v1/push/subscrever` devolve
   `{ "ok": true }`.
3. Simular um sync da Work (`POST /wp-json/cp/v1/pendentes`, autenticado
   com Application Password, com um `work_id` já sincronizado e um
   documento novo) e confirmar que aparece uma notificação do sistema.
4. Alterar à mão o `endpoint` de uma subscrição em usermeta
   (`cp_push_subscricoes`) para um URL inválido, repetir o passo 3, e
   confirmar que essa subscrição morta desaparece em vez de dar erro.

## O que falta: integração com `wp-env`

Quando o site se aproximar de produção, vale a pena adicionar uma camada
de integração a sério, com WordPress e base de dados reais
(`WP_UnitTestCase`, via [`wp-env`](https://developer.wordpress.org/block-editor/reference-guides/packages/packages-env/)
ou o
[WP Test Suite](https://make.wordpress.org/cli/handbook/misc/plugin-unit-tests/)).
É aí que faz sentido testar:

- os endpoints REST `/wp-json/cp/v1/...` de ponta a ponta (autenticação
  por Application Password, upsert de empresa/utilizador por `work_id`,
  respostas de erro);
- o ciclo de "pendentes" completo (Work envia → cliente responde →
  Work confirma → resposta é apagada);
- o registo do CPT `cp_empresa` e a associação utilizador↔empresas.

Para já, fica só preparado o caminho (este ficheiro e a separação
unit/integration em `tests/`) — não há nenhuma configuração de `wp-env`
ainda.

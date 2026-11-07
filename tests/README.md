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

Não testa (de propósito, nesta fase): o HTML gerado pelas `cp_ac_vista_*`,
pedidos HTTP reais aos endpoints REST, nem nada que dependa de uma base
de dados MySQL real.

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

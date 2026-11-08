---
name: ver-app-interna
description: Usar sempre que o pedido envolva a integração entre o site (Área de Cliente/Backoffice neste tema) e a APP interna — sincronização de contas de cliente, criação/gestão de empresas, dados partilhados ou qualquer comportamento que dependa do que a APP envia/espera do site. Também quando o utilizador perguntar "como é que isto chega da app" ou mencionar `wordpress_sync`, `area_cliente.py`, ou a app interna em geral.
---

# Ver a APP interna (D:\EN_Work-APP)

Este tema (`contabilidade-pro`) implementa o lado do **site** da Área de
Cliente/Backoffice. O lado interno — onde a equipa cria/gere as contas de
cliente e as sincroniza para aqui — é outro projeto Git, a APP interna, em:

```
D:\EN_Work-APP
```

Ficheiros relevantes nesse projeto para a integração:
- `wordpress_sync.py` — sincroniza contas/dados de cliente da APP para o
  site WordPress (é aqui que nascem os dados que a Área de Cliente lê).
- `area_cliente.py` — lógica da Área de Cliente do lado da APP.
- `PROJECT.md` / `ARCHITECTURE.md` / `DATABASE.md` — contexto geral da app
  e do schema de dados.

## Como usar

Os ficheiros em `D:\EN_Work-APP` podem ser lidos diretamente com Read/Grep/
Glob, sem precisar de autorização extra — só é preciso pedir essa pasta
como diretório de trabalho adicional se for necessário **editar** algo lá.

Antes de assumir o formato dos dados ou o comportamento da sincronização,
ler o ficheiro relevante em `D:\EN_Work-APP` em vez de adivinhar — os dois
projetos têm de ficar consistentes.

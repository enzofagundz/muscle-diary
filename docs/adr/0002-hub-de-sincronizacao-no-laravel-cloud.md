# 2. Hub de sincronização no Laravel Cloud

Data: 2026-10-06

## Status

Aceita

## Contexto

A sincronização exigia o app Laravel rodando numa máquina alcançável pelo celular, e o endereço gravado no aparelho era `http://localhost:8000` — que no celular é o próprio celular. As alternativas eram hospedar este app, apontar o celular para o IP da máquina na Wi-Fi, ou trocar o protocolo por Supabase direto (Auth + REST + RLS), o que reescreveria o cliente de sync e trocaria o banco por um plano que pausa após uma semana sem uso.

## Decisão

- O próprio app roda no Laravel Cloud como hub de sincronização (plano Starter, Postgres gerenciado, sem cache, fila ou buckets — o sync adia o trabalho para depois da resposta e não precisa de worker).
- O protocolo continua o mesmo (Sanctum + `/api/token` + `/api/sync/push|pull`), sem mudança de schema: o Postgres aceita os ULIDs e FKs atuais.
- Sessão e cache no banco (`SESSION_DRIVER=database`, `CACHE_STORE=database`), porque o filesystem do ambiente é efêmero.
- O celular é o único escritor. O site local sai do uso diário, o que mantém os ids inteiros de autoincremento sem colisão — sem UUIDs. A conta do hub é o id 1, igual ao do aparelho.
- Semear um hub novo passa por "Reenviar tudo" na tela de sync, que marca o histórico como pendente e roda a rodada completa.

## Consequências

- O celular sincroniza de qualquer rede; localhost deixa de existir como endereço.
- Com um escritor só, dois lados criando linhas com o mesmo id não acontece. Se um segundo escritor aparecer, a decisão precisa ser revista (faixa de ids ou troca de esquema).
- O `app:backup` continua valendo só para o SQLite de desenvolvimento; o banco do hub é gerenciado pelo Cloud.
- Desconectar com o servidor fora do ar funciona (a revogação do token é melhor esforço), porque o aparelho precisa soltar credenciais mortas para apontar ao hub.

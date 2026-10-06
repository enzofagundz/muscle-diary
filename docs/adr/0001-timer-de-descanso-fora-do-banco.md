# 1. Timer de descanso é client-side e não vira dado

Data: 2026-10-06

## Status

Aceita

## Contexto

O app já guarda o descanso planejado por sessão e por exercício, mas não tinha contagem. Quem treina precisa controlar o intervalo entre séries, e as decisões pendentes eram: o descanso efetuado vira dado no histórico? Onde o estado da contagem vive — banco, servidor ou aparelho? O item #20 fechou essas decisões.

## Decisão

- O timer roda inteiramente no cliente (módulo JavaScript puro mais Alpine). Não há mudança de schema, de sincronização nem do histórico.
- O estado do descanso sobrevive em `sessionStorage`, por sessão de treino, com o instante de início e a duração; o tempo restante é sempre recalculado a partir do relógio, nunca acumulado por intervalos de tique.
- A duração inicial de uma contagem é o descanso planejado do exercício, com o da sessão como fallback. Zero significa "sem descanso".
- O fim do descanso alerta por vibração (`navigator.vibrate`) e destaque visual, sem som, e a contagem para em 00:00.
- Um descanso por vez: iniciar outro substitui o que está correndo.

## Consequências

- Nada do descanso aparece no histórico, e trocar de aparelho não carrega uma contagem em andamento (o estado é local).
- Com o app fechado ou em segundo plano não há alerta nativo; ao voltar, o visor se corrige e o alerta dispara uma vez.
- A regra de exibição do botão (duração efetiva zero, sessão finalizada) fica no render do servidor, testada em Pest; a contagem fica no módulo puro, testada em Vitest.

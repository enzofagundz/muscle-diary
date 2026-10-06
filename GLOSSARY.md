# Glossário

## Descanso planejado

O intervalo configurado para acontecer entre séries, guardado no banco e sincronizado entre aparelhos. Existe um valor por sessão de treino (padrão de 90 segundos, `workout_sessions.rest_seconds`) e um valor opcional por exercício da sessão (`session_items.rest_seconds`).

## Duração efetiva do descanso

O valor que o timer usa ao iniciar uma contagem para uma série: o descanso planejado do exercício quando existe; o da sessão caso contrário. Duração efetiva zero significa "sem descanso" e o botão não aparece.

## Timer de descanso

A contagem regressiva entre séries no treino ao vivo. Inicia por um toque no botão de descanso de uma série, roda inteiramente no aparelho (client-side) e não é gravada no banco nem aparece no histórico. Ver `docs/adr/0001-timer-de-descanso-fora-do-banco.md`.

## Hub de sincronização

O app Laravel rodando no Laravel Cloud como o outro lado do sync: o celular aponta para a URL pública em vez de localhost. O celular é o único escritor; o site local fica fora do uso diário. Ver `docs/adr/0002-hub-de-sincronizacao-no-laravel-cloud.md`.

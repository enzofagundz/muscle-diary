# Padrões de código

## Commits

Formato `tipo: mensagem`, em português, no imperativo, sem ponto final. O escopo é opcional: `tipo(escopo): mensagem`, em kebab-case, quando a mudança bate num bloco único e o escopo ajuda a localizar o alvo.

- Tipos usados: `feat`, `fix`, `docs`, `test`, `chore`.
- Assunto curto; corpo opcional explica o porquê quando o diff não conta sozinho.
- Ferramenta de agente que prescreva outro formato (por exemplo `tipo(entidade):` obrigatório) segue este arquivo.

Exemplos do histórico:

```text
feat: casca mobile com navegação inferior e abertura direta no treino
fix: Sync alcançável na casca nativa
docs(agent-skills): configura issue tracker e docs de domínio
test: smoke test de todas as telas, no navegador e na casca nativa
chore: casca Android com NativePHP Mobile em modo webview
```

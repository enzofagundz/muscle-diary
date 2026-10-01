# Diário de Treino

Diário de musculação: registrar cada série do treino, acompanhar a evolução e planejar as próximas sessões. Roda como app web e como aplicativo Android, com o mesmo código.

## Como rodar

O projeto roda no lerd, em `http://muscle-diary.test`.

```sh
lerd start
php artisan migrate --seed
php artisan app:user seu@email.com --name="Seu Nome"
```

O `--seed` popula o catálogo base de exercícios. Não existe registro aberto: contas são criadas pelo comando `app:user`, que pede a senha se ela não vier por `--password`.

Para o app Android, veja a seção NativePHP abaixo.

## Backup do banco

O histórico mora num único arquivo SQLite, então o backup é uma cópia dele.

```sh
php artisan app:backup
```

O comando grava `storage/app/backups/<data>-<hora>.sqlite`, mantém os 14 mais recentes por padrão (`--keep=N` muda isso) e está agendado para rodar todo dia às 03:30, pelo `routes/console.php`. O agendamento só executa se o scheduler estiver rodando:

```sh
php artisan schedule:work   # ou a entrada de cron equivalente
```

### Restaurar

```sh
php artisan app:backup --list
php artisan app:backup --restore=storage/app/backups/2026-10-01-200148.sqlite
```

A restauração substitui o banco atual pelo conteúdo do backup, então o comando pede confirmação. `--force` pula a pergunta, para uso em script.

Depois de restaurar, rode `php artisan migrate` para aplicar qualquer migração que o backup ainda não tenha.

Verificação já executada: um backup foi gerado, uma linha marcadora foi inserida no banco, o backup foi restaurado e a linha marcadora desapareceu enquanto o restante dos dados continuou íntegro.

## NativePHP (Android)

O app Android roda o mesmo Laravel embutido, com SQLite local, dentro de um webview. Nenhuma tela é reescrita.

```sh
php artisan native:jump --ip=<seu-ip-na-rede>
```

Isso sobe o servidor, o bridge e o proxy do Vite, e mostra um QR code. Instale o app **Jump** no Android e escaneie o código, com celular e computador na mesma rede Wi-Fi. As mudanças recarregam sozinhas.

Para gerar o APK assinado é preciso o Android Studio instalado:

```sh
php artisan native:package
```

## Testes

```sh
php artisan test --compact
```

# Diário de Treino

Diário de musculação: registrar cada série do treino, acompanhar a evolução e planejar as próximas sessões. Roda como app web e como aplicativo Android, com o mesmo código.

## Como rodar

O projeto roda no lerd, em `http://muscle-diary.test`.

```sh
lerd start
touch database/database.sqlite   # se o arquivo ainda não existir
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

### Gerar o APK

O build precisa do Android SDK. Sem o Android Studio, dá para instalar só as ferramentas de linha de comando:

```sh
export ANDROID_HOME="$HOME/android-sdk"
mkdir -p "$ANDROID_HOME/cmdline-tools"
curl -L -o /tmp/cmdtools.zip https://dl.google.com/android/repository/commandlinetools-linux-11076708_latest.zip
unzip -q /tmp/cmdtools.zip -d "$ANDROID_HOME/cmdline-tools"
mv "$ANDROID_HOME/cmdline-tools/cmdline-tools" "$ANDROID_HOME/cmdline-tools/latest"
yes | "$ANDROID_HOME/cmdline-tools/latest/bin/sdkmanager" --licenses
"$ANDROID_HOME/cmdline-tools/latest/bin/sdkmanager" --install \
    "platform-tools" "platforms;android-35" "build-tools;35.0.0"
```

O build também precisa de um **JDK 21 ou mais antigo**. O Java 25 quebra o compilador Kotlin que vem com o Gradle 8.14, com `IllegalArgumentException: 25.0.4.1` — a versão de quatro partes não é aceita. Sem JDK antigo no sistema, dá para baixar um sem root:

```sh
mkdir -p ~/.local/jdks
curl -L -o /tmp/jdk21.tar.gz \
    "https://api.adoptium.net/v3/binary/latest/21/ga/linux/x64/jdk/hotspot/normal/eclipse"
tar xzf /tmp/jdk21.tar.gz -C ~/.local/jdks
export JAVA_HOME=$HOME/.local/jdks/jdk-21*
```

Com o SDK no lugar, gere a chave de assinatura uma vez e guarde os dois arquivos longe do repositório:

```sh
keytool -genkeypair -v -keystore ~/diario-release.keystore -alias diario \
    -keyalg RSA -keysize 2048 -validity 10000
```

E então empacote:

```sh
php artisan native:package --android --no-tty \
    --keystore="$HOME/diario-release.keystore" \
    --keystore-password=SUA_SENHA \
    --key-alias=diario \
    --key-password=SUA_SENHA
```

O APK sai em `nativephp/android/app/build/outputs/apk/release/`.

O `--no-tty` importa quando não há terminal de verdade (um script, um agente, um CI): sem ele o Gradle falha com `TTY mode requires /dev/tty to be read/writable`.

Para um APK de teste, sem chave de release, dá para compilar a variante de debug, que é assinada com a chave de debug e instala com `adb install`:

```sh
cd nativephp/android && ./gradlew assembleDebug --console=plain
```

O resultado sai em `nativephp/android/app/build/outputs/apk/debug/app-debug.apk`.

O `config/nativephp.php` exclui do pacote o que não faz parte do app: testes, repositório git, dependências de desenvolvimento, backups e o banco de desenvolvimento.

### Rodar num emulador

O emulador precisa de uns 3 GB de RAM livres. Com a máquina apertada, ele não sobe:

```sh
export ANDROID_HOME="$HOME/android-sdk"
"$ANDROID_HOME/cmdline-tools/latest/bin/sdkmanager" --install \
    "emulator" "system-images;android-35;google_apis;x86_64"
echo no | "$ANDROID_HOME/cmdline-tools/latest/bin/avdmanager" create avd \
    -n diario -k "system-images;android-35;google_apis;x86_64" -d pixel_6
"$ANDROID_HOME/emulator/emulator" -avd diario -no-window -gpu swiftshader_indirect
php artisan native:run
```

## Importar as anotações antigas

```sh
php artisan app:import-notion anotacoes.md --user=seu@email.com
```

O arquivo segue o formato das anotações que já existiam:

```
## 2026-09-29 — Upper 1 — Sky

### Supino máquina — Peito
14 placas — 12
14 placas — 7
obs: aguentava mais

### Remada curvada — Costas
40 kg — 4 + 35 kg — 4
65 kg — 8
60 kg —
— 10
```

- `## data — nome — local` abre um treino; o local é opcional.
- `### exercício — grupo muscular` abre um exercício. O grupo só é obrigatório quando o exercício ainda não existe no catálogo; se ele já existe, o nome basta.
- Uma linha por série, no formato `carga unidade — repetições`. A unidade aceita `kg`, `placas`/`pl` e `libras`/`lb`; sem unidade e sem carga, a série é de peso corporal.
- `+` na mesma linha vira uma série combinada, com um segmento por carga.
- `obs:` guarda a observação do exercício.
- Rodar de novo não duplica: um treino já importado, com a mesma data e o mesmo nome, é pulado.

O que não puder ser lido com segurança é relatado na saída em vez de adivinhado.

## Testes

```sh
php artisan test --compact
```

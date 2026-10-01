<?php

namespace App\Livewire;

use App\Models\SyncSetting;
use App\Services\SyncRunner;
use App\Services\SyncService;
use App\Support\NativeNetwork;
use Illuminate\Support\Facades\Http;
use Illuminate\View\View;
use Livewire\Component;

class Sync extends Component
{
    public string $serverUrl = '';

    public string $email = '';

    public string $password = '';

    public ?string $notice = null;

    public ?string $failure = null;

    public function mount(): void
    {
        $this->serverUrl = SyncSetting::current()->server_url ?? '';
    }

    public function connect(): void
    {
        $validated = $this->validate([
            'serverUrl' => ['required', 'url'],
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        $response = Http::acceptJson()->post(rtrim($validated['serverUrl'], '/').'/api/token', [
            'email' => $validated['email'],
            'password' => $validated['password'],
            'device_name' => 'Diário de Treino',
        ]);

        if ($response->failed()) {
            $this->failure = 'Não consegui entrar: verifique e-mail, senha e o endereço do servidor.';

            return;
        }

        SyncSetting::current()->update([
            'server_url' => rtrim($validated['serverUrl'], '/'),
            'token' => $response->json('token'),
        ]);

        $this->reset('password');
        $this->notice = 'Conectado.';
        $this->failure = null;
    }

    public function disconnect(): void
    {
        $settings = SyncSetting::current();

        if ($settings->isConnected()) {
            Http::withToken($settings->token)
                ->delete($settings->server_url.'/api/token');
        }

        $settings->update(['server_url' => null, 'token' => null, 'last_synced_at' => null]);

        $this->reset('serverUrl', 'password');
        $this->notice = 'Desconectado.';
    }

    public function sync(): void
    {
        $runner = app(SyncRunner::class);

        if (! $runner->isConnected()) {
            $this->failure = 'Conecte o aparelho a um servidor primeiro.';

            return;
        }

        if (! NativeNetwork::isConnected()) {
            $this->failure = 'Sem conexão agora. O que está pendente continua guardado.';

            return;
        }

        if (! $runner->run()) {
            $this->failure = 'A sincronização falhou. Nada foi perdido: o que está pendente continua pendente.';

            return;
        }

        $this->failure = null;
        $this->notice = 'Sincronizado.';
    }

    public function render(): View
    {
        $settings = SyncSetting::current();

        return view('livewire.sync', [
            'settings' => $settings,
            'pending' => collect((new SyncService)->pending())->flatten(1)->count(),
        ]);
    }
}

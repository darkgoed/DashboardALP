<?php

class LogoutActionService
{
    public function handle(): array
    {
        Auth::logout();

        return [
            'redirect' => routeUrl('login'),
            'flash_success' => 'Sessao encerrada com sucesso.',
        ];
    }
}

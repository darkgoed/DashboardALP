<?php

class PersonalizacaoPageService
{
    public function getPageData(): array
    {
        return [
            'pagina_atual' => 'personalizar.php',
            'temas' => [
                'dark' => 'Dark',
                'light' => 'White',
            ],
        ];
    }
}

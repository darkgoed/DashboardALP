<?php

class FormValidationException extends RuntimeException
{
    private array $errors;
    private array $old;

    public function __construct(array $errors, array $old, string $message = 'Dados inválidos.')
    {
        parent::__construct($message);
        $this->errors = $errors;
        $this->old = $old;
    }

    public function getErrors(): array
    {
        return $this->errors;
    }

    public function getOld(): array
    {
        return $this->old;
    }
}

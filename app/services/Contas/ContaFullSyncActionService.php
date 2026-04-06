<?php

class ContaFullSyncActionService
{
    private ContaSyncActionService $service;

    public function __construct(PDO $conn)
    {
        $this->service = new ContaSyncActionService($conn);
    }

    public function enqueue(int $empresaId, int $contaId): string
    {
        return $this->service->enqueueFullSync($empresaId, $contaId);
    }
}

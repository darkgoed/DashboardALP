<?php

class ContaSyncActionService
{
    private MetaSyncEnqueueService $service;

    public function __construct(PDO $conn)
    {
        $this->service = new MetaSyncEnqueueService($conn);
    }

    public function enqueueSyncNow(int $empresaId, int $contaId, int $diasInsights = 7): string
    {
        $this->service->enqueueSyncNow($empresaId, $contaId, $diasInsights);

        return 'Sync enfileirada com sucesso. Estrutura e insights dos ultimos '
            . $diasInsights
            . ' dias foram solicitados.';
    }

    public function enqueueReprocessar(int $empresaId, int $contaId, int $dias = 7): string
    {
        $this->service->enqueueReprocessar($empresaId, $contaId, $dias);

        return 'Reprocessamento dos ultimos ' . $dias . ' dias enfileirado com sucesso.';
    }

    public function enqueueFullSync(int $empresaId, int $contaId): string
    {
        $resultado = $this->service->enqueueFullSync($empresaId, $contaId);

        return 'Full Sync enfileirada com sucesso. Estrutura e '
            . (int) ($resultado['total_jobs_insights'] ?? 0)
            . ' lotes de insights foram solicitados para a janela de '
            . $resultado['janela_inicio']
            . ' ate '
            . $resultado['janela_fim']
            . ' (maximo permitido pela Meta).';
    }
}

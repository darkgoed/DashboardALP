<?php

class MercadoPhoneSyncActionService
{
    private MercadoPhoneQueueService $queueService;

    public function __construct(PDO $conn)
    {
        $this->queueService = new MercadoPhoneQueueService($conn);
    }

    public function enqueue(int $empresaId, int $integracaoId, string $modo): string
    {
        if ($integracaoId <= 0) {
            throw new RuntimeException('Integracao invalida para sincronizacao.');
        }

        $resultado = $this->queueService->enqueueManualSync($empresaId, $integracaoId, $modo === 'full');

        if (!empty($resultado['already_pending'])) {
            return 'Ja existe uma sync Mercado Phone pendente ou processando para esta integracao.';
        }

        $modoLabel = $modo === 'full' ? 'full' : 'incremental';
        return 'Sync Mercado Phone enfileirada com sucesso. Job #' . (int) $resultado['job_id'] . ' (' . $modoLabel . ').';
    }
}

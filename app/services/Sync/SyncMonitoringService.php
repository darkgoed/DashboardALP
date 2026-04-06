<?php

class SyncMonitoringService
{
    private PDO $conn;
    private int $empresaId;
    private ContaAds $contaModel;

    public function __construct(PDO $conn, int $empresaId)
    {
        $this->conn = $conn;
        $this->empresaId = $empresaId;
        $this->contaModel = new ContaAds($conn, $empresaId);
    }

    public function getDashboardData(): array
    {
        $statusCount = [
            'pendente' => 0,
            'processando' => 0,
            'concluido' => 0,
            'erro' => 0,
            'cancelado' => 0,
        ];

        $stmtStatus = $this->conn->prepare("
            SELECT status, COUNT(*) AS total
            FROM sync_jobs
            WHERE empresa_id = :empresa_id
            GROUP BY status
        ");
        $stmtStatus->execute([':empresa_id' => $this->empresaId]);

        while ($row = $stmtStatus->fetch(PDO::FETCH_ASSOC)) {
            $status = (string) ($row['status'] ?? '');
            if (array_key_exists($status, $statusCount)) {
                $statusCount[$status] = (int) ($row['total'] ?? 0);
            }
        }

        $stmtHoje = $this->conn->prepare("
            SELECT COUNT(*) AS total
            FROM sync_jobs
            WHERE empresa_id = :empresa_id
              AND status = 'concluido'
              AND DATE(finalizado_em) = CURDATE()
        ");
        $stmtHoje->execute([':empresa_id' => $this->empresaId]);
        $concluidosHoje = (int) (($stmtHoje->fetch(PDO::FETCH_ASSOC)['total'] ?? 0));

        $stmtTipos = $this->conn->prepare("
            SELECT tipo, COUNT(*) AS total
            FROM sync_jobs
            WHERE empresa_id = :empresa_id
            GROUP BY tipo
            ORDER BY total DESC, tipo ASC
        ");
        $stmtTipos->execute([':empresa_id' => $this->empresaId]);
        $tiposFila = $stmtTipos->fetchAll(PDO::FETCH_ASSOC);

        $stmtOrigens = $this->conn->prepare("
            SELECT origem, COUNT(*) AS total
            FROM sync_jobs
            WHERE empresa_id = :empresa_id
            GROUP BY origem
            ORDER BY total DESC, origem ASC
        ");
        $stmtOrigens->execute([':empresa_id' => $this->empresaId]);
        $origensFila = $stmtOrigens->fetchAll(PDO::FETCH_ASSOC);

        $stmtUltimaExecucao = $this->conn->prepare("
            SELECT MAX(finalizado_em) AS ultima_execucao
            FROM sync_jobs
            WHERE empresa_id = :empresa_id
              AND finalizado_em IS NOT NULL
        ");
        $stmtUltimaExecucao->execute([':empresa_id' => $this->empresaId]);
        $ultimaExecucao = $stmtUltimaExecucao->fetch(PDO::FETCH_ASSOC)['ultima_execucao'] ?? null;

        $stmtFalhas = $this->conn->prepare("
            SELECT
                sj.id,
                sj.tipo,
                sj.mensagem,
                sj.finalizado_em,
                sj.criado_em,
                ca.nome AS conta_nome
            FROM sync_jobs sj
            LEFT JOIN contas_ads ca ON ca.id = sj.conta_id AND ca.empresa_id = sj.empresa_id
            WHERE sj.empresa_id = :empresa_id
              AND sj.status = 'erro'
            ORDER BY sj.id DESC
            LIMIT 10
        ");
        $stmtFalhas->execute([':empresa_id' => $this->empresaId]);
        $falhas = $stmtFalhas->fetchAll(PDO::FETCH_ASSOC);

        $stmtJobs = $this->conn->prepare("
            SELECT
                sj.id,
                sj.tipo,
                sj.status,
                sj.criado_em,
                sj.finalizado_em,
                ca.nome AS conta_nome
            FROM sync_jobs sj
            LEFT JOIN contas_ads ca ON ca.id = sj.conta_id AND ca.empresa_id = sj.empresa_id
            WHERE sj.empresa_id = :empresa_id
            ORDER BY sj.id DESC
            LIMIT 20
        ");
        $stmtJobs->execute([':empresa_id' => $this->empresaId]);
        $jobs = $stmtJobs->fetchAll(PDO::FETCH_ASSOC);

        return [
            'status_count' => $statusCount,
            'concluidos_hoje' => $concluidosHoje,
            'tipos_fila' => $tiposFila,
            'origens_fila' => $origensFila,
            'ultima_execucao' => $ultimaExecucao,
            'falhas' => $falhas,
            'jobs' => $jobs,
            'total_jobs' => array_sum($statusCount),
        ];
    }

    public function getLogsData(array $filters): array
    {
        $status = trim((string) ($filters['status'] ?? ''));
        $tipo = trim((string) ($filters['tipo'] ?? ''));
        $contaId = isset($filters['conta_id']) && $filters['conta_id'] !== ''
            ? (int) $filters['conta_id']
            : null;

        $contas = $this->contaModel->getAll();

        $sql = "
            SELECT
                sj.*,
                ca.nome AS conta_nome,
                ca.meta_account_id
            FROM sync_jobs sj
            LEFT JOIN contas_ads ca
                ON ca.id = sj.conta_id
               AND ca.empresa_id = sj.empresa_id
            WHERE sj.empresa_id = :empresa_id
        ";

        $params = [':empresa_id' => $this->empresaId];

        if ($status !== '') {
            $sql .= " AND sj.status = :status";
            $params[':status'] = $status;
        }

        if ($tipo !== '') {
            $sql .= " AND sj.tipo = :tipo";
            $params[':tipo'] = $tipo;
        }

        if ($contaId !== null) {
            $sql .= " AND sj.conta_id = :conta_id";
            $params[':conta_id'] = $contaId;
        }

        $sql .= " ORDER BY sj.id DESC LIMIT 100";

        $stmt = $this->conn->prepare($sql);
        $stmt->execute($params);

        return [
            'filters' => [
                'status' => $status,
                'tipo' => $tipo,
                'conta_id' => $contaId,
            ],
            'contas' => $contas,
            'jobs' => $stmt->fetchAll(PDO::FETCH_ASSOC),
        ];
    }
}

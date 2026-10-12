<?php

namespace Database\Seeders;

use App\Models\History;
use App\Models\Project;
use App\Models\Task;
use App\Models\TaskNote;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class DemoSeeder extends Seeder
{
    private const ADMIN_EMAIL = 'admin@admin.com';

    private const DEMO_EMAILS = [
        'suporte@chamados.ti',
        'ana.silva@chamados.ti',
        'carlos.souza@chamados.ti',
    ];

    private const DEMO_PROJECTS = [
        'Rede e Segurança',
        'ERP Financeiro',
        'Suporte Field Service',
    ];

    public function run(): void
    {
        DB::transaction(function () {
            $this->purgeDemoData();
            $this->seedDemoData();
        });
    }

    private function purgeDemoData(): void
    {
        $demoUserIds = DB::table('users')
            ->whereIn('email', self::DEMO_EMAILS)
            ->pluck('id');

        $projectIds = DB::table('projects')
            ->whereIn('name', self::DEMO_PROJECTS)
            ->pluck('id');

        $taskIds = $projectIds->isEmpty()
            ? collect()
            : DB::table('tasks')->whereIn('project_id', $projectIds)->pluck('id');

        $noteIds = $taskIds->isEmpty()
            ? collect()
            : DB::table('task_notes')->whereIn('task_id', $taskIds)->pluck('id');

        DB::table('histories')
            ->where(function ($query) use ($taskIds, $noteIds, $projectIds, $demoUserIds) {
                if ($taskIds->isNotEmpty()) {
                    $query->orWhere(function ($q) use ($taskIds) {
                        $q->where('auditable_type', Task::class)
                            ->whereIn('auditable_id', $taskIds);
                    });
                }

                if ($noteIds->isNotEmpty()) {
                    $query->orWhere(function ($q) use ($noteIds) {
                        $q->where('auditable_type', TaskNote::class)
                            ->whereIn('auditable_id', $noteIds);
                    });
                }

                if ($projectIds->isNotEmpty()) {
                    $query->orWhere(function ($q) use ($projectIds) {
                        $q->where('auditable_type', Project::class)
                            ->whereIn('auditable_id', $projectIds);
                    });
                }

                if ($demoUserIds->isNotEmpty()) {
                    $query->orWhere(function ($q) use ($demoUserIds) {
                        $q->where('auditable_type', User::class)
                            ->whereIn('auditable_id', $demoUserIds);
                    })->orWhereIn('user_id', $demoUserIds);
                }
            })
            ->delete();

        if ($taskIds->isNotEmpty()) {
            DB::table('task_notes')->whereIn('task_id', $taskIds)->delete();
            DB::table('tasks')->whereIn('id', $taskIds)->delete();
        }

        if ($projectIds->isNotEmpty()) {
            DB::table('project_user')->whereIn('project_id', $projectIds)->delete();
            DB::table('projects')->whereIn('id', $projectIds)->delete();
        }

        if ($demoUserIds->isNotEmpty()) {
            DB::table('project_user')->whereIn('user_id', $demoUserIds)->delete();
            DB::table('histories')->whereIn('user_id', $demoUserIds)->delete();
            DB::table('users')->whereIn('id', $demoUserIds)->delete();
        }
    }

    private function seedDemoData(): void
    {
        $groupIds = DB::table('groups')->pluck('id', 'code');

        $admin = DB::table('users')->where('email', self::ADMIN_EMAIL)->first();

        if (! $admin) {
            throw new \RuntimeException('Usuário admin@admin.com não encontrado. Rode as migrations primeiro.');
        }

        $now = now();
        $password = Hash::make('password');

        $suporteId = DB::table('users')->insertGetId([
            'name' => 'Equipe Suporte',
            'email' => 'suporte@chamados.ti',
            'password' => $password,
            'group_id' => $groupIds['SUPORTE'],
            'is_active' => true,
            'created_at' => $now->copy()->subDays(20),
            'updated_at' => $now->copy()->subDays(20),
        ]);

        $anaId = DB::table('users')->insertGetId([
            'name' => 'Ana Silva',
            'email' => 'ana.silva@chamados.ti',
            'password' => $password,
            'group_id' => $groupIds['USUARIOS'],
            'is_active' => true,
            'created_at' => $now->copy()->subDays(18),
            'updated_at' => $now->copy()->subDays(18),
        ]);

        $carlosId = DB::table('users')->insertGetId([
            'name' => 'Carlos Souza',
            'email' => 'carlos.souza@chamados.ti',
            'password' => $password,
            'group_id' => $groupIds['USUARIOS'],
            'is_active' => true,
            'created_at' => $now->copy()->subDays(17),
            'updated_at' => $now->copy()->subDays(17),
        ]);

        foreach ([$suporteId, $anaId, $carlosId] as $userId) {
            $this->recordHistory($admin->id, User::class, $userId, History::ACTION_CREATED, $now->copy()->subDays(18));
        }

        $redeId = DB::table('projects')->insertGetId([
            'name' => 'Rede e Segurança',
            'is_active' => true,
            'created_at' => $now->copy()->subDays(15),
            'updated_at' => $now->copy()->subDays(15),
        ]);

        $erpId = DB::table('projects')->insertGetId([
            'name' => 'ERP Financeiro',
            'is_active' => true,
            'created_at' => $now->copy()->subDays(15),
            'updated_at' => $now->copy()->subDays(15),
        ]);

        $fieldId = DB::table('projects')->insertGetId([
            'name' => 'Suporte Field Service',
            'is_active' => true,
            'created_at' => $now->copy()->subDays(14),
            'updated_at' => $now->copy()->subDays(14),
        ]);

        foreach ([$redeId, $erpId, $fieldId] as $projectId) {
            $this->recordHistory($admin->id, Project::class, $projectId, History::ACTION_CREATED, $now->copy()->subDays(15));
        }

        $allUserIds = [$admin->id, $suporteId, $anaId, $carlosId];
        $allProjectIds = [$redeId, $erpId, $fieldId];

        foreach ($allUserIds as $userId) {
            foreach ($allProjectIds as $projectId) {
                DB::table('project_user')->insert([
                    'user_id' => $userId,
                    'project_id' => $projectId,
                    'created_at' => $now->copy()->subDays(14),
                    'updated_at' => $now->copy()->subDays(14),
                ]);
            }
        }

        $tasks = [
            [
                'project_id' => $redeId,
                'code' => '1',
                'title' => 'VPN corporativa não conecta após atualização do Windows',
                'description' => 'Usuário do setor Comercial relata que, após a atualização do Windows 11, o cliente VPN deixou de conectar na rede interna. A mensagem exibida é "Falha na autenticação do túnel".',
                'severity' => 'high',
                'status' => 'in_progress',
                'reporter_id' => $anaId,
                'assignee_id' => $suporteId,
                'requested_at' => $now->copy()->subDays(5)->setTime(9, 15),
                'resolved_at' => null,
                'notes' => [
                    [
                        'user_id' => $suporteId,
                        'note' => 'Contato com o usuário confirmou o erro após atualização do Windows 11. Reinstalei o perfil VPN e limpei o cache de certificados, mas o erro "Falha na autenticação do túnel" continua. Cliente VPN está na versão 3.8.2 (desatualizada). Próximo passo: atualizar para a versão 4.1.0 homologada e validar a conexão.',
                        'created_at' => $now->copy()->subDays(4)->setTime(14, 30),
                    ],
                ],
                'history' => [
                    ['action' => History::ACTION_CREATED, 'user_id' => $anaId, 'at' => $now->copy()->subDays(5)->setTime(9, 15)],
                    ['action' => History::ACTION_ASSIGNED, 'user_id' => $suporteId, 'at' => $now->copy()->subDays(5)->setTime(10, 5)],
                ],
            ],
            [
                'project_id' => $erpId,
                'code' => '2',
                'title' => 'Erro ao gerar relatório de contas a pagar no módulo financeiro',
                'description' => 'Ao tentar exportar o relatório de contas a pagar do período mensal, o sistema retorna "Erro interno ao processar consulta" quando o filtro por centro de custo é aplicado.',
                'severity' => 'critical',
                'status' => 'open',
                'reporter_id' => $carlosId,
                'assignee_id' => null,
                'requested_at' => $now->copy()->subDays(2)->setTime(11, 40),
                'resolved_at' => null,
                'notes' => [],
                'history' => [
                    ['action' => History::ACTION_CREATED, 'user_id' => $carlosId, 'at' => $now->copy()->subDays(2)->setTime(11, 40)],
                ],
            ],
            [
                'project_id' => $fieldId,
                'code' => '3',
                'title' => 'Aplicativo mobile não sincroniza ordens de serviço em campo',
                'description' => 'Técnico de campo informa que o aplicativo de ordens de serviço não está sincronizando as OS concluídas. As atividades aparecem como "pendente de envio" mesmo com conexão 4G estável.',
                'severity' => 'high',
                'status' => 'resolved',
                'reporter_id' => $anaId,
                'assignee_id' => $suporteId,
                'requested_at' => $now->copy()->subDays(8)->setTime(8, 20),
                'resolved_at' => $now->copy()->subDays(6)->setTime(16, 45),
                'notes' => [
                    [
                        'user_id' => $suporteId,
                        'note' => 'Identificado timeout na API de sincronização. Ajustado o limite do gateway e republicada a versão 2.3.1 do app. Técnico validou o envio das 7 OS pendentes com sucesso.',
                        'created_at' => $now->copy()->subDays(7)->setTime(10, 10),
                    ],
                ],
                'history' => [
                    ['action' => History::ACTION_CREATED, 'user_id' => $anaId, 'at' => $now->copy()->subDays(8)->setTime(8, 20)],
                    ['action' => History::ACTION_ASSIGNED, 'user_id' => $suporteId, 'at' => $now->copy()->subDays(8)->setTime(9, 0)],
                    ['action' => History::ACTION_RESOLVED, 'user_id' => $suporteId, 'at' => $now->copy()->subDays(6)->setTime(16, 45)],
                ],
            ],
            [
                'project_id' => $redeId,
                'code' => '4',
                'title' => 'Solicitar liberação de porta no firewall para integração parceira',
                'description' => 'Necessário liberar a porta 8443 de saída para o endpoint do parceiro logístico. Sem a liberação, a integração de rastreio permanece interrompida.',
                'severity' => 'medium',
                'status' => 'closed',
                'reporter_id' => $carlosId,
                'assignee_id' => $admin->id,
                'requested_at' => $now->copy()->subDays(12)->setTime(13, 0),
                'resolved_at' => $now->copy()->subDays(10)->setTime(15, 20),
                'notes' => [
                    [
                        'user_id' => $admin->id,
                        'note' => 'Regra criada no firewall perimetral e validada com o time de integração. Chamado encerrado após confirmação do parceiro.',
                        'created_at' => $now->copy()->subDays(10)->setTime(15, 0),
                    ],
                ],
                'history' => [
                    ['action' => History::ACTION_CREATED, 'user_id' => $carlosId, 'at' => $now->copy()->subDays(12)->setTime(13, 0)],
                    ['action' => History::ACTION_ASSIGNED, 'user_id' => $admin->id, 'at' => $now->copy()->subDays(12)->setTime(13, 30)],
                    ['action' => History::ACTION_RESOLVED, 'user_id' => $admin->id, 'at' => $now->copy()->subDays(10)->setTime(15, 20)],
                    ['action' => History::ACTION_CLOSED, 'user_id' => $admin->id, 'at' => $now->copy()->subDays(9)->setTime(9, 0)],
                ],
            ],
            [
                'project_id' => $erpId,
                'code' => '5',
                'title' => 'Usuário sem permissão para aprovar lançamentos contábeis',
                'description' => 'Após troca de perfil no AD, o usuário financeiro perdeu a permissão de aprovação no módulo contábil. Solicita restauração do acesso com urgência para o fechamento.',
                'severity' => 'medium',
                'status' => 'in_progress',
                'reporter_id' => $carlosId,
                'assignee_id' => $admin->id,
                'requested_at' => $now->copy()->subDay()->setTime(10, 5),
                'resolved_at' => null,
                'notes' => [
                    [
                        'user_id' => $admin->id,
                        'note' => 'Perfil revalidado no ERP. Aguardando confirmação do usuário após novo login.',
                        'created_at' => $now->copy()->subDay()->setTime(15, 40),
                    ],
                ],
                'history' => [
                    ['action' => History::ACTION_CREATED, 'user_id' => $carlosId, 'at' => $now->copy()->subDay()->setTime(10, 5)],
                    ['action' => History::ACTION_ASSIGNED, 'user_id' => $admin->id, 'at' => $now->copy()->subDay()->setTime(10, 25)],
                ],
            ],
            [
                'project_id' => $fieldId,
                'code' => '6',
                'title' => 'Impressora de etiquetas offline na base regional',
                'description' => 'A impressora Zebra da base regional está offline no spooler. Técnicos não conseguem imprimir etiquetas de OS antes das visitas.',
                'severity' => 'low',
                'status' => 'open',
                'reporter_id' => $anaId,
                'assignee_id' => null,
                'requested_at' => $now->copy()->subHours(6),
                'resolved_at' => null,
                'notes' => [],
                'history' => [
                    ['action' => History::ACTION_CREATED, 'user_id' => $anaId, 'at' => $now->copy()->subHours(6)],
                ],
            ],
            [
                'project_id' => $redeId,
                'code' => '7',
                'title' => 'Renovação de certificado SSL do portal interno',
                'description' => 'O certificado do portal interno vence em 10 dias. Solicitar renovação e atualização nos balanceadores para evitar alerta de segurança nos navegadores.',
                'severity' => 'medium',
                'status' => 'closed',
                'reporter_id' => $suporteId,
                'assignee_id' => $admin->id,
                'requested_at' => $now->copy()->subDays(20)->setTime(9, 0),
                'resolved_at' => $now->copy()->subDays(18)->setTime(17, 10),
                'notes' => [],
                'history' => [
                    ['action' => History::ACTION_CREATED, 'user_id' => $suporteId, 'at' => $now->copy()->subDays(20)->setTime(9, 0)],
                    ['action' => History::ACTION_ASSIGNED, 'user_id' => $admin->id, 'at' => $now->copy()->subDays(20)->setTime(9, 30)],
                    ['action' => History::ACTION_RESOLVED, 'user_id' => $admin->id, 'at' => $now->copy()->subDays(18)->setTime(17, 10)],
                    ['action' => History::ACTION_CLOSED, 'user_id' => $admin->id, 'at' => $now->copy()->subDays(18)->setTime(17, 30)],
                ],
            ],
            [
                'project_id' => $erpId,
                'code' => '8',
                'title' => 'Lentidão no fechamento do período contábil',
                'description' => 'O processo de fechamento mensal está demorando mais de 40 minutos e ocasionalmente encerra com timeout. Impacta o prazo de entrega dos demonstrativos.',
                'severity' => 'high',
                'status' => 'in_progress',
                'reporter_id' => $carlosId,
                'assignee_id' => $suporteId,
                'requested_at' => $now->copy()->subDays(3)->setTime(14, 10),
                'resolved_at' => null,
                'notes' => [
                    [
                        'user_id' => $suporteId,
                        'note' => 'Índices da tabela de lançamentos reconstruídos. Em teste com o financeiro no ambiente de homologação.',
                        'created_at' => $now->copy()->subDays(2)->setTime(16, 0),
                    ],
                ],
                'history' => [
                    ['action' => History::ACTION_CREATED, 'user_id' => $carlosId, 'at' => $now->copy()->subDays(3)->setTime(14, 10)],
                    ['action' => History::ACTION_ASSIGNED, 'user_id' => $suporteId, 'at' => $now->copy()->subDays(3)->setTime(14, 40)],
                ],
            ],
            [
                'project_id' => $fieldId,
                'code' => '9',
                'title' => 'GPS do app de campo com posição imprecisa',
                'description' => 'Em áreas urbanas densas o app registra a localização com desvio de até 800 metros, dificultando a comprovação da visita.',
                'severity' => 'medium',
                'status' => 'open',
                'reporter_id' => $anaId,
                'assignee_id' => null,
                'requested_at' => $now->copy()->subDays(1)->setTime(8, 45),
                'resolved_at' => null,
                'notes' => [],
                'history' => [
                    ['action' => History::ACTION_CREATED, 'user_id' => $anaId, 'at' => $now->copy()->subDays(1)->setTime(8, 45)],
                ],
            ],
            [
                'project_id' => $redeId,
                'code' => '10',
                'title' => 'Wi-Fi corporativo instável no 3º andar',
                'description' => 'Colaboradores do 3º andar relatam quedas frequentes da rede sem fio entre 14h e 17h. Notebooks alternam entre conectado e desconectado.',
                'severity' => 'medium',
                'status' => 'resolved',
                'reporter_id' => $anaId,
                'assignee_id' => $suporteId,
                'requested_at' => $now->copy()->subDays(9)->setTime(10, 0),
                'resolved_at' => $now->copy()->subDays(7)->setTime(11, 30),
                'notes' => [
                    [
                        'user_id' => $suporteId,
                        'note' => 'Canal do access point alterado e potência ajustada. Monitoramento de 48h sem novas quedas.',
                        'created_at' => $now->copy()->subDays(7)->setTime(11, 15),
                    ],
                ],
                'history' => [
                    ['action' => History::ACTION_CREATED, 'user_id' => $anaId, 'at' => $now->copy()->subDays(9)->setTime(10, 0)],
                    ['action' => History::ACTION_ASSIGNED, 'user_id' => $suporteId, 'at' => $now->copy()->subDays(9)->setTime(10, 20)],
                    ['action' => History::ACTION_RESOLVED, 'user_id' => $suporteId, 'at' => $now->copy()->subDays(7)->setTime(11, 30)],
                ],
            ],
            [
                'project_id' => $erpId,
                'code' => '11',
                'title' => 'Duplicidade de notas fiscais na importação XML',
                'description' => 'A rotina de importação está gerando documentos duplicados quando o arquivo XML é reenviado pelo fornecedor no mesmo dia.',
                'severity' => 'critical',
                'status' => 'in_progress',
                'reporter_id' => $carlosId,
                'assignee_id' => $admin->id,
                'requested_at' => $now->copy()->subDays(4)->setTime(9, 30),
                'resolved_at' => null,
                'notes' => [
                    [
                        'user_id' => $admin->id,
                        'note' => 'Identificada falha na chave de idempotência. Correção em desenvolvimento; hotfix previsto para amanhã.',
                        'created_at' => $now->copy()->subDays(3)->setTime(18, 0),
                    ],
                ],
                'history' => [
                    ['action' => History::ACTION_CREATED, 'user_id' => $carlosId, 'at' => $now->copy()->subDays(4)->setTime(9, 30)],
                    ['action' => History::ACTION_ASSIGNED, 'user_id' => $admin->id, 'at' => $now->copy()->subDays(4)->setTime(9, 50)],
                ],
            ],
            [
                'project_id' => $fieldId,
                'code' => '12',
                'title' => 'Checklist de OS não salva fotos anexadas',
                'description' => 'Ao concluir o checklist, as fotos tiradas no celular não aparecem no portal web. O técnico precisa reenviar os arquivos manualmente.',
                'severity' => 'high',
                'status' => 'closed',
                'reporter_id' => $anaId,
                'assignee_id' => $suporteId,
                'requested_at' => $now->copy()->subDays(15)->setTime(7, 50),
                'resolved_at' => $now->copy()->subDays(13)->setTime(12, 0),
                'notes' => [],
                'history' => [
                    ['action' => History::ACTION_CREATED, 'user_id' => $anaId, 'at' => $now->copy()->subDays(15)->setTime(7, 50)],
                    ['action' => History::ACTION_ASSIGNED, 'user_id' => $suporteId, 'at' => $now->copy()->subDays(15)->setTime(8, 20)],
                    ['action' => History::ACTION_RESOLVED, 'user_id' => $suporteId, 'at' => $now->copy()->subDays(13)->setTime(12, 0)],
                    ['action' => History::ACTION_CLOSED, 'user_id' => $admin->id, 'at' => $now->copy()->subDays(12)->setTime(9, 15)],
                ],
            ],
            [
                'project_id' => $redeId,
                'code' => '13',
                'title' => 'Bloqueio indevido de domínio em filtro de conteúdo',
                'description' => 'O domínio do fornecedor de RH está sendo bloqueado pelo filtro web, impedindo acesso ao portal de folha.',
                'severity' => 'low',
                'status' => 'open',
                'reporter_id' => $carlosId,
                'assignee_id' => null,
                'requested_at' => $now->copy()->subHours(3),
                'resolved_at' => null,
                'notes' => [],
                'history' => [
                    ['action' => History::ACTION_CREATED, 'user_id' => $carlosId, 'at' => $now->copy()->subHours(3)],
                ],
            ],
            [
                'project_id' => $erpId,
                'code' => '14',
                'title' => 'Extrato bancário não concilia automaticamente',
                'description' => 'A rotina de conciliação automática falhou nos últimos 3 dias para a conta principal. Itens ficam pendentes mesmo com OFX válido.',
                'severity' => 'high',
                'status' => 'resolved',
                'reporter_id' => $carlosId,
                'assignee_id' => $suporteId,
                'requested_at' => $now->copy()->subDays(6)->setTime(15, 0),
                'resolved_at' => $now->copy()->subDays(5)->setTime(10, 40),
                'notes' => [
                    [
                        'user_id' => $suporteId,
                        'note' => 'Layout OFX do banco mudou. Parser atualizado e conciliação reprocessada com sucesso.',
                        'created_at' => $now->copy()->subDays(5)->setTime(10, 20),
                    ],
                ],
                'history' => [
                    ['action' => History::ACTION_CREATED, 'user_id' => $carlosId, 'at' => $now->copy()->subDays(6)->setTime(15, 0)],
                    ['action' => History::ACTION_ASSIGNED, 'user_id' => $suporteId, 'at' => $now->copy()->subDays(6)->setTime(15, 25)],
                    ['action' => History::ACTION_RESOLVED, 'user_id' => $suporteId, 'at' => $now->copy()->subDays(5)->setTime(10, 40)],
                ],
            ],
            [
                'project_id' => $fieldId,
                'code' => '15',
                'title' => 'Agenda do técnico não atualiza após remanejamento',
                'description' => 'Quando a OS é transferida entre técnicos, a agenda antiga continua exibindo o compromisso até o app ser reinstalado.',
                'severity' => 'medium',
                'status' => 'in_progress',
                'reporter_id' => $anaId,
                'assignee_id' => $suporteId,
                'requested_at' => $now->copy()->subDays(2)->setTime(7, 30),
                'resolved_at' => null,
                'notes' => [],
                'history' => [
                    ['action' => History::ACTION_CREATED, 'user_id' => $anaId, 'at' => $now->copy()->subDays(2)->setTime(7, 30)],
                    ['action' => History::ACTION_ASSIGNED, 'user_id' => $suporteId, 'at' => $now->copy()->subDays(2)->setTime(8, 0)],
                ],
            ],
            [
                'project_id' => $redeId,
                'code' => '16',
                'title' => 'Criar conta de e-mail para novo colaborador',
                'description' => 'Solicitar criação de mailbox corporativa para admissão do colaborador João Mendes, com acesso ao Outlook e lista do departamento Comercial.',
                'severity' => 'low',
                'status' => 'closed',
                'reporter_id' => $anaId,
                'assignee_id' => $admin->id,
                'requested_at' => $now->copy()->subDays(11)->setTime(11, 0),
                'resolved_at' => $now->copy()->subDays(11)->setTime(14, 20),
                'notes' => [],
                'history' => [
                    ['action' => History::ACTION_CREATED, 'user_id' => $anaId, 'at' => $now->copy()->subDays(11)->setTime(11, 0)],
                    ['action' => History::ACTION_ASSIGNED, 'user_id' => $admin->id, 'at' => $now->copy()->subDays(11)->setTime(11, 15)],
                    ['action' => History::ACTION_RESOLVED, 'user_id' => $admin->id, 'at' => $now->copy()->subDays(11)->setTime(14, 20)],
                    ['action' => History::ACTION_CLOSED, 'user_id' => $admin->id, 'at' => $now->copy()->subDays(11)->setTime(14, 35)],
                ],
            ],
            [
                'project_id' => $erpId,
                'code' => '17',
                'title' => 'Relatório de centros de custo exporta colunas vazias',
                'description' => 'No Excel gerado pelo ERP, as colunas de rateio e responsável saem em branco mesmo com dados cadastrados no sistema.',
                'severity' => 'medium',
                'status' => 'open',
                'reporter_id' => $carlosId,
                'assignee_id' => null,
                'requested_at' => $now->copy()->subHours(10),
                'resolved_at' => null,
                'notes' => [],
                'history' => [
                    ['action' => History::ACTION_CREATED, 'user_id' => $carlosId, 'at' => $now->copy()->subHours(10)],
                ],
            ],
            [
                'project_id' => $fieldId,
                'code' => '18',
                'title' => 'Notificação push não chega ao finalizar OS',
                'description' => 'Supervisores não recebem alerta no celular quando o técnico finaliza uma ordem de serviço crítica.',
                'severity' => 'medium',
                'status' => 'resolved',
                'reporter_id' => $anaId,
                'assignee_id' => $admin->id,
                'requested_at' => $now->copy()->subDays(10)->setTime(16, 0),
                'resolved_at' => $now->copy()->subDays(8)->setTime(9, 50),
                'notes' => [
                    [
                        'user_id' => $admin->id,
                        'note' => 'Certificado FCM renovado e tokens de dispositivo revalidados. Testes de push OK em Android e iOS.',
                        'created_at' => $now->copy()->subDays(8)->setTime(9, 30),
                    ],
                ],
                'history' => [
                    ['action' => History::ACTION_CREATED, 'user_id' => $anaId, 'at' => $now->copy()->subDays(10)->setTime(16, 0)],
                    ['action' => History::ACTION_ASSIGNED, 'user_id' => $admin->id, 'at' => $now->copy()->subDays(10)->setTime(16, 30)],
                    ['action' => History::ACTION_RESOLVED, 'user_id' => $admin->id, 'at' => $now->copy()->subDays(8)->setTime(9, 50)],
                ],
            ],
            [
                'project_id' => $redeId,
                'code' => '19',
                'title' => 'Acesso SSH ao servidor de homologação bloqueado',
                'description' => 'Desenvolvedores não conseguem conectar via SSH no server de homologação. A conexão encerra com "Connection timed out".',
                'severity' => 'high',
                'status' => 'in_progress',
                'reporter_id' => $suporteId,
                'assignee_id' => $admin->id,
                'requested_at' => $now->copy()->subDays(1)->setTime(13, 20),
                'resolved_at' => null,
                'notes' => [
                    [
                        'user_id' => $admin->id,
                        'note' => 'Security group revisado. Liberação parcial aplicada; validando com o time de desenvolvimento.',
                        'created_at' => $now->copy()->subDays(1)->setTime(17, 0),
                    ],
                ],
                'history' => [
                    ['action' => History::ACTION_CREATED, 'user_id' => $suporteId, 'at' => $now->copy()->subDays(1)->setTime(13, 20)],
                    ['action' => History::ACTION_ASSIGNED, 'user_id' => $admin->id, 'at' => $now->copy()->subDays(1)->setTime(13, 35)],
                ],
            ],
            [
                'project_id' => $erpId,
                'code' => '20',
                'title' => 'Parametrizar novo centro de custo para filial Sul',
                'description' => 'Criar e liberar o centro de custo 4500-SUL no ERP, vinculando às contas contábeis padrão da filial e aos aprovadores locais.',
                'severity' => 'low',
                'status' => 'closed',
                'reporter_id' => $carlosId,
                'assignee_id' => $admin->id,
                'requested_at' => $now->copy()->subDays(16)->setTime(10, 10),
                'resolved_at' => $now->copy()->subDays(15)->setTime(16, 0),
                'notes' => [],
                'history' => [
                    ['action' => History::ACTION_CREATED, 'user_id' => $carlosId, 'at' => $now->copy()->subDays(16)->setTime(10, 10)],
                    ['action' => History::ACTION_ASSIGNED, 'user_id' => $admin->id, 'at' => $now->copy()->subDays(16)->setTime(10, 40)],
                    ['action' => History::ACTION_RESOLVED, 'user_id' => $admin->id, 'at' => $now->copy()->subDays(15)->setTime(16, 0)],
                    ['action' => History::ACTION_CLOSED, 'user_id' => $admin->id, 'at' => $now->copy()->subDays(15)->setTime(16, 20)],
                ],
            ],
        ];

        foreach ($tasks as $taskData) {
            $notes = $taskData['notes'];
            $history = $taskData['history'];
            unset($taskData['notes'], $taskData['history']);

            $taskId = DB::table('tasks')->insertGetId([
                ...$taskData,
                'attachment' => null,
                'created_at' => $taskData['requested_at'],
                'updated_at' => $taskData['resolved_at'] ?? $taskData['requested_at'],
            ]);

            foreach ($history as $entry) {
                $this->recordHistory($entry['user_id'], Task::class, $taskId, $entry['action'], $entry['at']);
            }

            foreach ($notes as $noteData) {
                $noteId = DB::table('task_notes')->insertGetId([
                    'task_id' => $taskId,
                    'user_id' => $noteData['user_id'],
                    'note' => $noteData['note'],
                    'attachment' => null,
                    'created_at' => $noteData['created_at'],
                    'updated_at' => $noteData['created_at'],
                ]);

                $this->recordHistory($noteData['user_id'], TaskNote::class, $noteId, History::ACTION_CREATED, $noteData['created_at']);
            }
        }
    }

    private function recordHistory(int $userId, string $type, int $id, string $action, $createdAt): void
    {
        DB::table('histories')->insert([
            'user_id' => $userId,
            'auditable_type' => $type,
            'auditable_id' => $id,
            'action' => $action,
            'created_at' => $createdAt,
        ]);
    }
}

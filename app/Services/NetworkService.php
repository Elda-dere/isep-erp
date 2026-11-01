<?php

namespace App\Services;

use App\Models\ApprovedNetwork;
use Illuminate\Support\Facades\DB;

class NetworkService
{
    /** @param array{name: string, ip_range: string} $data */
    public function create(array $data): ApprovedNetwork
    {
        return DB::transaction(function () use ($data) {
            $network = ApprovedNetwork::create([
                'name' => $data['name'],
                'ip_range' => $data['ip_range'],
                'is_active' => true,
            ]);

            AuditLogger::log('network.created', 'approved_network', $network->id, null, $this->snapshot($network));

            return $network;
        });
    }

    /** @param array{name: string, ip_range: string} $data */
    public function update(ApprovedNetwork $network, array $data): ApprovedNetwork
    {
        return DB::transaction(function () use ($network, $data) {
            $old = $this->snapshot($network);

            $network->update([
                'name' => $data['name'],
                'ip_range' => $data['ip_range'],
            ]);

            $new = $this->snapshot($network->refresh());

            $changedOld = [];
            $changedNew = [];

            foreach (['name', 'ip_range'] as $field) {
                if ($old[$field] !== $new[$field]) {
                    $changedOld[$field] = $old[$field];
                    $changedNew[$field] = $new[$field];
                }
            }

            // Saving without changes writes no audit row.
            if ($changedNew !== []) {
                AuditLogger::log('network.updated', 'approved_network', $network->id, $changedOld, $changedNew);
            }

            return $network;
        });
    }

    public function setActive(ApprovedNetwork $network, bool $active): void
    {
        DB::transaction(function () use ($network, $active) {
            if ($network->is_active === $active) {
                return;
            }

            $network->update(['is_active' => $active]);

            AuditLogger::log(
                $active ? 'network.activated' : 'network.deactivated',
                'approved_network',
                $network->id,
                ['is_active' => ! $active],
                ['is_active' => $active],
            );
        });
    }

    /** @return array{name: string, ip_range: string, is_active: bool} */
    private function snapshot(ApprovedNetwork $network): array
    {
        return [
            'name' => $network->name,
            'ip_range' => $network->ip_range,
            'is_active' => (bool) $network->is_active,
        ];
    }
}
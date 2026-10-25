<?php

namespace App\Services;

use App\Models\Department;
use App\Models\Position;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class OrganisationService
{
    public function createDepartment(array $data): Department
    {
        return DB::transaction(function () use ($data) {
            $department = Department::create([
                'name' => $data['name'],
                'description' => $data['description'] ?? null,
            ]);

            AuditLogger::log('department.created', 'department', $department->id, null, $this->department($department));

            return $department;
        });
    }

    public function updateDepartment(Department $department, array $data): Department
    {
        return DB::transaction(function () use ($department, $data) {
            $old = $this->department($department);

            $department->update([
                'name' => $data['name'],
                'description' => $data['description'] ?? null,
            ]);

            $this->logChanges('department.updated', 'department', $department->id, $old, $this->department($department->refresh()));

            return $department;
        });
    }

    public function deleteDepartment(Department $department): void
    {
        DB::transaction(function () use ($department) {
            if ($department->positions()->exists()) {
                throw ValidationException::withMessages([
                    'department' => 'This department still has positions. Delete or move them first.',
                ]);
            }

            $old = $this->department($department);

            $department->delete();

            AuditLogger::log('department.deleted', 'department', $department->id, $old);
        });
    }

    public function createPosition(array $data): Position
    {
        return DB::transaction(function () use ($data) {
            $position = Position::create([
                'department_id' => $data['department_id'],
                'title' => $data['title'],
            ]);

            AuditLogger::log('position.created', 'position', $position->id, null, $this->position($position));

            return $position;
        });
    }

    public function updatePosition(Position $position, array $data): Position
    {
        return DB::transaction(function () use ($position, $data) {
            $old = $this->position($position);

            $position->update([
                'department_id' => $data['department_id'],
                'title' => $data['title'],
            ]);

            $this->logChanges('position.updated', 'position', $position->id, $old, $this->position($position->refresh()));

            return $position;
        });
    }

    public function deletePosition(Position $position): void
    {
        DB::transaction(function () use ($position) {
            if ($position->employees()->exists()) {
                throw ValidationException::withMessages([
                    'position' => 'An employee still holds this position. Change their position first.',
                ]);
            }

            $old = $this->position($position);

            $position->delete();

            AuditLogger::log('position.deleted', 'position', $position->id, $old);
        });
    }

    /** @return array<string, mixed> */
    private function department(Department $department): array
    {
        return ['name' => $department->name, 'description' => $department->description];
    }

    /** @return array<string, mixed> */
    private function position(Position $position): array
    {
        return ['department_id' => (int) $position->department_id, 'title' => $position->title];
    }

    /** Writes an audit row holding only the fields that really changed. */
    private function logChanges(string $action, string $type, int $id, array $old, array $new): void
    {
        $changedOld = [];
        $changedNew = [];

        foreach ($new as $key => $value) {
            if ($old[$key] !== $value) {
                $changedOld[$key] = $old[$key];
                $changedNew[$key] = $value;
            }
        }

        if ($changedNew !== []) {
            AuditLogger::log($action, $type, $id, $changedOld, $changedNew);
        }
    }
}
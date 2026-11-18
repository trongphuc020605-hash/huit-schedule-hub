<?php

declare(strict_types=1);

namespace HuitSchedule\Repositories;

use HuitSchedule\Core\Database;
use PDO;

final class BranchRepository
{
    private PDO $pdo;

    public function __construct(?PDO $pdo = null)
    {
        $this->pdo = $pdo ?? Database::getConnection();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function all(): array
    {
        $stmt = $this->pdo->query(
            'SELECT b.*,
                    (SELECT COUNT(*) FROM schedules s WHERE s.branch_id = b.id) AS schedule_count
             FROM branches b
             ORDER BY b.code ASC, b.id ASC'
        );
        return $stmt->fetchAll();
    }

    /**
     * @return array<string, mixed>|null
     */
    public function findById(int $id): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM branches WHERE id = :id LIMIT 1');
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function findByCode(string $code, ?int $excludeId = null): ?array
    {
        $sql = 'SELECT * FROM branches WHERE code = :code';
        $params = [':code' => strtoupper(trim($code))];
        if ($excludeId !== null) {
            $sql .= ' AND id != :exclude_id';
            $params[':exclude_id'] = $excludeId;
        }
        $sql .= ' LIMIT 1';
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    /**
     * @param array<string, mixed> $data
     */
    public function create(array $data): int
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO branches (branch_name, address, code, campus_zone, color_hex)
             VALUES (:branch_name, :address, :code, :campus_zone, :color_hex)'
        );
        $stmt->execute([
            ':branch_name' => trim((string) $data['branch_name']),
            ':address'     => trim((string) $data['address']),
            ':code'        => strtoupper(trim((string) $data['code'])),
            ':campus_zone' => trim((string) $data['campus_zone']),
            ':color_hex'   => trim((string) ($data['color_hex'] ?? '#1D4ED8')),
        ]);
        return (int) $this->pdo->lastInsertId();
    }

    /**
     * @param array<string, mixed> $data
     */
    public function update(int $id, array $data): bool
    {
        $stmt = $this->pdo->prepare(
            'UPDATE branches
             SET branch_name = :branch_name,
                 address = :address,
                 code = :code,
                 campus_zone = :campus_zone,
                 color_hex = :color_hex
             WHERE id = :id'
        );
        return $stmt->execute([
            ':id'          => $id,
            ':branch_name' => trim((string) $data['branch_name']),
            ':address'     => trim((string) $data['address']),
            ':code'        => strtoupper(trim((string) $data['code'])),
            ':campus_zone' => trim((string) $data['campus_zone']),
            ':color_hex'   => trim((string) ($data['color_hex'] ?? '#1D4ED8')),
        ]);
    }

    public function countSchedules(int $branchId): int
    {
        $stmt = $this->pdo->prepare('SELECT COUNT(*) FROM schedules WHERE branch_id = :id');
        $stmt->execute([':id' => $branchId]);
        return (int) $stmt->fetchColumn();
    }

    public function delete(int $id): bool
    {
        $stmt = $this->pdo->prepare('DELETE FROM branches WHERE id = :id');
        return $stmt->execute([':id' => $id]);
    }

    /**
     * Return travel matrix indexed by [from_branch_id][to_branch_id] => ['travel_minutes' => int, 'distance_km' => float].
     *
     * @return array<int, array<int, array{travel_minutes: int, distance_km: float}>>
     */
    public function getTravelMatrix(): array
    {
        $stmt = $this->pdo->query('SELECT from_branch_id, to_branch_id, travel_minutes, distance_km FROM branch_travel_times');
        $matrix = [];
        foreach ($stmt->fetchAll() as $row) {
            $from = (int) $row['from_branch_id'];
            $to   = (int) $row['to_branch_id'];
            $matrix[$from][$to] = [
                'travel_minutes' => (int) $row['travel_minutes'],
                'distance_km'    => (float) $row['distance_km'],
            ];
        }
        return $matrix;
    }

    /**
     * Upsert symmetric travel times between two branches.
     */
    public function upsertTravelTime(int $fromBranchId, int $toBranchId, int $travelMinutes, float $distanceKm): void
    {
        $sql = 'INSERT INTO branch_travel_times (from_branch_id, to_branch_id, travel_minutes, distance_km)
                VALUES (:from_id, :to_id, :mins, :km)
                ON DUPLICATE KEY UPDATE travel_minutes = VALUES(travel_minutes), distance_km = VALUES(distance_km)';
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            ':from_id' => $fromBranchId,
            ':to_id'   => $toBranchId,
            ':mins'    => $travelMinutes,
            ':km'      => $distanceKm,
        ]);
        if ($fromBranchId !== $toBranchId) {
            $stmt->execute([
                ':from_id' => $toBranchId,
                ':to_id'   => $fromBranchId,
                ':mins'    => $travelMinutes,
                ':km'      => $distanceKm,
            ]);
        }
    }
}

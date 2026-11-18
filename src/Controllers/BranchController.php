<?php

declare(strict_types=1);

namespace HuitSchedule\Controllers;

use HuitSchedule\Core\Flash;
use HuitSchedule\Core\Response;
use HuitSchedule\Core\View;
use HuitSchedule\Repositories\BranchRepository;

final class BranchController
{
    private BranchRepository $branchRepo;

    public function __construct()
    {
        $this->branchRepo = new BranchRepository();
    }

    public function index(): void
    {
        $branches = $this->branchRepo->all();
        $travelMatrix = $this->branchRepo->getTravelMatrix();

        View::render('branches/index', [
            'pageTitle'    => 'Quản lý Cơ sở & Ma trận Di chuyển',
            'activeNav'    => 'branches',
            'branches'     => $branches,
            'travelMatrix' => $travelMatrix,
        ]);
    }

    public function store(): void
    {
        $data = [
            'branch_name' => trim((string) ($_POST['branch_name'] ?? '')),
            'address'     => trim((string) ($_POST['address'] ?? '')),
            'code'        => strtoupper(trim((string) ($_POST['code'] ?? ''))),
            'campus_zone' => trim((string) ($_POST['campus_zone'] ?? '')),
            'color_hex'   => trim((string) ($_POST['color_hex'] ?? '#1D4ED8')),
        ];

        $error = $this->validate($data);
        if ($error !== null) {
            Flash::error($error);
            Response::redirect('/branches');
        }

        if ($this->branchRepo->findByCode($data['code']) !== null) {
            Flash::error('Mã cơ sở "' . $data['code'] . '" đã tồn tại.');
            Response::redirect('/branches');
        }

        $newId = $this->branchRepo->create($data);
        // Initialize self travel time = 0
        $this->branchRepo->upsertTravelTime($newId, $newId, 0, 0.0);

        Flash::success('Đã thêm cơ sở "' . $data['branch_name'] . '" thành công.');
        Response::redirect('/branches');
    }

    public function update(string $id): void
    {
        $branchId = (int) $id;
        $existing = $this->branchRepo->findById($branchId);
        if ($existing === null) {
            Flash::error('Không tìm thấy cơ sở cần cập nhật.');
            Response::redirect('/branches');
        }

        $data = [
            'branch_name' => trim((string) ($_POST['branch_name'] ?? '')),
            'address'     => trim((string) ($_POST['address'] ?? '')),
            'code'        => strtoupper(trim((string) ($_POST['code'] ?? ''))),
            'campus_zone' => trim((string) ($_POST['campus_zone'] ?? '')),
            'color_hex'   => trim((string) ($_POST['color_hex'] ?? '#1D4ED8')),
        ];

        $error = $this->validate($data);
        if ($error !== null) {
            Flash::error($error);
            Response::redirect('/branches');
        }

        if ($this->branchRepo->findByCode($data['code'], $branchId) !== null) {
            Flash::error('Mã cơ sở "' . $data['code'] . '" đã được sử dụng bởi cơ sở khác.');
            Response::redirect('/branches');
        }

        $this->branchRepo->update($branchId, $data);
        Flash::success('Đã cập nhật thông tin cơ sở "' . $data['branch_name'] . '".');
        Response::redirect('/branches');
    }

    public function destroy(string $id): void
    {
        $branchId = (int) $id;
        $branch = $this->branchRepo->findById($branchId);
        if ($branch === null) {
            Flash::error('Cơ sở không tồn tại.');
            Response::redirect('/branches');
        }

        $scheduleCount = $this->branchRepo->countSchedules($branchId);
        if ($scheduleCount > 0) {
            Flash::error(sprintf(
                'Không thể xóa cơ sở "%s" vì đang có %d lịch giảng dạy được phân công. Vui lòng chuyển hoặc xóa các lịch liên quan trước.',
                $branch['branch_name'],
                $scheduleCount
            ));
            Response::redirect('/branches');
        }

        $this->branchRepo->delete($branchId);
        Flash::success('Đã xóa cơ sở "' . $branch['branch_name'] . '".');
        Response::redirect('/branches');
    }

    public function updateTravel(): void
    {
        $fromId  = (int) ($_POST['from_branch_id'] ?? 0);
        $toId    = (int) ($_POST['to_branch_id'] ?? 0);
        $minutes = (int) ($_POST['travel_minutes'] ?? 0);
        $km      = (float) ($_POST['distance_km'] ?? 0);

        if ($fromId <= 0 || $toId <= 0 || $fromId === $toId) {
            Flash::error('Vui lòng chọn hai cơ sở khác nhau để cập nhật thời gian di chuyển.');
            Response::redirect('/branches');
        }
        if ($minutes < 1 || $minutes > 180 || $km < 0.1 || $km > 100) {
            Flash::error('Thời gian di chuyển (1–180 phút) hoặc khoảng cách (0.1–100 km) không hợp lệ.');
            Response::redirect('/branches');
        }

        $this->branchRepo->upsertTravelTime($fromId, $toId, $minutes, $km);
        Flash::success('Đã cập nhật ma trận thời gian di chuyển hai chiều giữa 2 cơ sở.');
        Response::redirect('/branches');
    }

    /**
     * @param array<string, mixed> $data
     */
    private function validate(array $data): ?string
    {
        if ($data['branch_name'] === '' || $data['address'] === '' || $data['code'] === '' || $data['campus_zone'] === '') {
            return 'Vui lòng điền đầy đủ Mã cơ sở, Tên cơ sở, Khu vực và Địa chỉ.';
        }
        if (!preg_match('/^#[0-9A-Fa-f]{6}$/', (string) $data['color_hex'])) {
            return 'Mã màu HEX không hợp lệ (ví dụ #1D4ED8).';
        }
        return null;
    }
}

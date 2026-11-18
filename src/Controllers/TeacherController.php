<?php

declare(strict_types=1);

namespace HuitSchedule\Controllers;

use HuitSchedule\Core\Auth;
use HuitSchedule\Core\Flash;
use HuitSchedule\Core\Response;
use HuitSchedule\Core\View;
use HuitSchedule\Domain\TimeSlot;
use HuitSchedule\Repositories\AvailabilityRepository;
use HuitSchedule\Repositories\BranchRepository;
use HuitSchedule\Repositories\TeacherRepository;

final class TeacherController
{
    private TeacherRepository $teacherRepo;
    private AvailabilityRepository $availRepo;
    private BranchRepository $branchRepo;

    public function __construct()
    {
        $this->teacherRepo = new TeacherRepository();
        $this->availRepo   = new AvailabilityRepository();
        $this->branchRepo  = new BranchRepository();
    }

    public function index(): void
    {
        $search = trim((string) ($_GET['search'] ?? ''));
        $allTeachers = $this->teacherRepo->all();

        if ($search !== '') {
            $needle = mb_strtolower($search, 'UTF-8');
            $allTeachers = array_values(array_filter($allTeachers, static function (array $t) use ($needle): bool {
                $haystack = mb_strtolower(
                    (string) $t['full_name'] . ' ' . (string) $t['email'] . ' ' . (string) $t['expertise'],
                    'UTF-8'
                );
                return str_contains($haystack, $needle);
            }));
        }

        $perPage = 10;
        $totalItems = count($allTeachers);
        $totalPages = max(1, (int) ceil($totalItems / $perPage));
        $page = max(1, min($totalPages, (int) ($_GET['page'] ?? 1)));
        $pagedTeachers = array_slice($allTeachers, ($page - 1) * $perPage, $perPage);

        $branches = $this->branchRepo->all();
        $availGrouped = $this->availRepo->allGroupedByTeacher();

        View::render('teachers/index', [
            'pageTitle'    => 'Quản lý Giảng viên & Tiết trống',
            'activeNav'    => 'teachers',
            'teachers'     => $pagedTeachers,
            'totalItems'   => $totalItems,
            'search'       => $search,
            'branches'     => $branches,
            'availGrouped' => $availGrouped,
            'pagination'   => [
                'page'        => $page,
                'per_page'    => $perPage,
                'total_items' => $totalItems,
                'total_pages' => $totalPages,
                'base_path'   => '/teachers',
                'query'       => $search !== '' ? ['search' => $search] : [],
            ],
        ]);
    }

    public function store(): void
    {
        $data = [
            'full_name'            => trim((string) ($_POST['full_name'] ?? '')),
            'email'                => strtolower(trim((string) ($_POST['email'] ?? ''))),
            'phone'                => trim((string) ($_POST['phone'] ?? '')),
            'expertise'            => trim((string) ($_POST['expertise'] ?? '')),
            'role'                 => in_array($_POST['role'] ?? 'teacher', ['admin', 'teacher'], true) ? (string) $_POST['role'] : 'teacher',
            'max_periods_per_week' => max(3, min(45, (int) ($_POST['max_periods_per_week'] ?? 18))),
            'password'             => trim((string) ($_POST['password'] ?? '')) ?: 'Teacher@123',
        ];

        if ($data['full_name'] === '' || $data['email'] === '' || $data['phone'] === '' || $data['expertise'] === '') {
            Flash::error('Vui lòng nhập đầy đủ Họ tên, Email, Số điện thoại và Chuyên môn.');
            Response::redirect('/teachers');
        }
        if (!filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
            Flash::error('Địa chỉ email không hợp lệ.');
            Response::redirect('/teachers');
        }
        if ($this->teacherRepo->findByEmail($data['email']) !== null) {
            Flash::error('Email "' . $data['email'] . '" đã tồn tại trong hệ thống.');
            Response::redirect('/teachers');
        }

        $this->teacherRepo->create($data);
        Flash::success('Đã thêm giảng viên "' . $data['full_name'] . '" thành công.');
        Response::redirect('/teachers');
    }

    public function update(string $id): void
    {
        $teacherId = (int) $id;
        $existing = $this->teacherRepo->findById($teacherId);
        if ($existing === null) {
            Flash::error('Không tìm thấy giảng viên.');
            Response::redirect('/teachers');
        }

        $data = [
            'full_name'            => trim((string) ($_POST['full_name'] ?? '')),
            'email'                => strtolower(trim((string) ($_POST['email'] ?? ''))),
            'phone'                => trim((string) ($_POST['phone'] ?? '')),
            'expertise'            => trim((string) ($_POST['expertise'] ?? '')),
            'role'                 => in_array($_POST['role'] ?? 'teacher', ['admin', 'teacher'], true) ? (string) $_POST['role'] : 'teacher',
            'max_periods_per_week' => max(3, min(45, (int) ($_POST['max_periods_per_week'] ?? 18))),
            'password'             => trim((string) ($_POST['password'] ?? '')),
        ];

        if ($data['full_name'] === '' || $data['email'] === '' || $data['phone'] === '' || $data['expertise'] === '') {
            Flash::error('Vui lòng nhập đầy đủ thông tin giảng viên.');
            Response::redirect('/teachers');
        }
        if (!filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
            Flash::error('Địa chỉ email không hợp lệ.');
            Response::redirect('/teachers');
        }
        if ($this->teacherRepo->findByEmail($data['email'], $teacherId) !== null) {
            Flash::error('Email "' . $data['email'] . '" đã thuộc về tài khoản khác.');
            Response::redirect('/teachers');
        }

        if (Auth::id() === $teacherId && $existing['role'] === 'admin' && $data['role'] !== 'admin') {
            Flash::error('Không thể tự hạ quyền tài khoản Quản trị viên đang đăng nhập.');
            Response::redirect('/teachers');
        }

        $this->teacherRepo->update($teacherId, $data);
        if (Auth::id() === $teacherId) {
            $updated = $this->teacherRepo->findById($teacherId);
            if ($updated !== null) {
                Auth::login($updated);
            }
        }
        Flash::success('Đã cập nhật hồ sơ giảng viên "' . $data['full_name'] . '".');
        Response::redirect('/teachers');
    }

    public function destroy(string $id): void
    {
        $teacherId = (int) $id;
        $teacher = $this->teacherRepo->findById($teacherId);
        if ($teacher === null) {
            Flash::error('Giảng viên không tồn tại.');
            Response::redirect('/teachers');
        }

        if (Auth::id() === $teacherId) {
            Flash::error('Không thể xóa tài khoản quản trị đang đăng nhập.');
            Response::redirect('/teachers');
        }

        $scheduleCount = $this->teacherRepo->countSchedules($teacherId);
        if ($scheduleCount > 0) {
            Flash::error(sprintf(
                'Không thể xóa giảng viên "%s" vì đang phụ trách %d lịch giảng dạy. Vui lòng phân công lại các lịch đó trước.',
                $teacher['full_name'],
                $scheduleCount
            ));
            Response::redirect('/teachers');
        }

        $this->teacherRepo->delete($teacherId);
        Flash::success('Đã xóa giảng viên "' . $teacher['full_name'] . '".');
        Response::redirect('/teachers');
    }

    public function storeAvailability(string $id): void
    {
        $teacherId = (int) $id;
        $teacher = $this->teacherRepo->findById($teacherId);
        if ($teacher === null) {
            Flash::error('Giảng viên không tồn tại.');
            Response::redirect('/teachers');
        }

        $dayOfWeek   = (int) ($_POST['day_of_week'] ?? 2);
        $startPeriod = (int) ($_POST['start_period'] ?? 1);
        $endPeriod   = (int) ($_POST['end_period'] ?? 3);
        $prefBranch  = !empty($_POST['preferred_branch_id']) ? (int) $_POST['preferred_branch_id'] : null;

        try {
            $slot = new TimeSlot($dayOfWeek, $startPeriod, $endPeriod);
        } catch (\InvalidArgumentException $e) {
            Flash::error($e->getMessage());
            Response::redirect('/teachers');
        }

        $this->availRepo->upsert([
            'teacher_id'          => $teacherId,
            'day_of_week'         => $dayOfWeek,
            'shift_or_periods'    => $slot->shiftLabel(),
            'start_period'        => $startPeriod,
            'end_period'          => $endPeriod,
            'preferred_branch_id' => $prefBranch,
        ]);

        Flash::success('Đã cập nhật khung tiết trống cho ' . $teacher['full_name'] . '.');
        Response::redirect('/teachers');
    }

    public function deleteAvailability(string $id, string $availId): void
    {
        $this->availRepo->delete((int) $availId, (int) $id);
        Flash::success('Đã xóa khung tiết trống.');
        Response::redirect('/teachers');
    }

    public function regenerateToken(string $id): void
    {
        $teacherId = (int) $id;
        $teacher = $this->teacherRepo->findById($teacherId);
        if ($teacher === null) {
            Flash::error('Giảng viên không tồn tại.');
            Response::redirect('/teachers');
        }
        $this->teacherRepo->regeneratePortalToken($teacherId);
        Flash::success('Đã làm mới Portal Token bảo mật cho ' . $teacher['full_name'] . '.');
        Response::redirect('/teachers');
    }
}

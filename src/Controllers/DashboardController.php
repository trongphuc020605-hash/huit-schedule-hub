<?php

declare(strict_types=1);

namespace HuitSchedule\Controllers;

use HuitSchedule\Core\View;
use HuitSchedule\Repositories\BranchRepository;
use HuitSchedule\Repositories\TeacherRepository;
use HuitSchedule\Services\AnalyticsService;

final class DashboardController
{
    public function index(): void
    {
        $filters = $this->extractFilters();

        $analytics  = new AnalyticsService();
        $branchRepo = new BranchRepository();
        $teacherRepo = new TeacherRepository();

        $data = $analytics->getDashboardData($filters);

        View::render('dashboard/index', [
            'pageTitle' => 'Tổng quan & Trung tâm Tối ưu',
            'activeNav' => 'dashboard',
            'filters'   => $filters,
            'branches'  => $branchRepo->all(),
            'teachers'  => $teacherRepo->all('teacher'),
            'kpi'       => $data['kpi'],
            'charts'    => $data['charts'],
            'alerts'    => $data['alerts'],
            'timeline'  => $data['timeline'],
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function extractFilters(): array
    {
        $filters = [];
        if (!empty($_GET['branch_id'])) {
            $filters['branch_id'] = (int) $_GET['branch_id'];
        }
        if (!empty($_GET['teacher_id'])) {
            $filters['teacher_id'] = (int) $_GET['teacher_id'];
        }
        if (!empty($_GET['day_of_week'])) {
            $filters['day_of_week'] = (int) $_GET['day_of_week'];
        }
        if (!empty($_GET['search'])) {
            $filters['search'] = trim((string) $_GET['search']);
        }
        return $filters;
    }
}

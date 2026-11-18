<?php

declare(strict_types=1);

return [
    'app_name' => $_ENV['APP_NAME'] ?? 'HUIT Schedule Hub',
    'timezone' => $_ENV['APP_TIMEZONE'] ?? 'Asia/Ho_Chi_Minh',

    // 15 chuẩn tiết học đại học (50 phút/tiết)
    // Nghỉ chuyển ca Sáng (T3->T4): 10p, Nghỉ trưa (T6->T7): 20p,
    // Nghỉ chuyển ca Chiều (T9->T10): 10p, Nghỉ chiều tối (T12->T13): 20p
    'periods' => [
        1  => ['start' => '07:00', 'end' => '07:50', 'start_min' => 420,  'end_min' => 470,  'session' => 'morning'],
        2  => ['start' => '07:50', 'end' => '08:40', 'start_min' => 470,  'end_min' => 520,  'session' => 'morning'],
        3  => ['start' => '08:40', 'end' => '09:30', 'start_min' => 520,  'end_min' => 570,  'session' => 'morning'],
        4  => ['start' => '09:40', 'end' => '10:30', 'start_min' => 580,  'end_min' => 630,  'session' => 'morning'],
        5  => ['start' => '10:30', 'end' => '11:20', 'start_min' => 630,  'end_min' => 680,  'session' => 'morning'],
        6  => ['start' => '11:20', 'end' => '12:10', 'start_min' => 680,  'end_min' => 730,  'session' => 'morning'],
        7  => ['start' => '12:30', 'end' => '13:20', 'start_min' => 750,  'end_min' => 800,  'session' => 'afternoon'],
        8  => ['start' => '13:20', 'end' => '14:10', 'start_min' => 800,  'end_min' => 850,  'session' => 'afternoon'],
        9  => ['start' => '14:10', 'end' => '15:00', 'start_min' => 850,  'end_min' => 900,  'session' => 'afternoon'],
        10 => ['start' => '15:10', 'end' => '16:00', 'start_min' => 910,  'end_min' => 960,  'session' => 'afternoon'],
        11 => ['start' => '16:00', 'end' => '16:50', 'start_min' => 960,  'end_min' => 1010, 'session' => 'afternoon'],
        12 => ['start' => '16:50', 'end' => '17:40', 'start_min' => 1010, 'end_min' => 1060, 'session' => 'afternoon'],
        13 => ['start' => '18:00', 'end' => '18:50', 'start_min' => 1080, 'end_min' => 1130, 'session' => 'evening'],
        14 => ['start' => '18:50', 'end' => '19:40', 'start_min' => 1130, 'end_min' => 1180, 'session' => 'evening'],
        15 => ['start' => '19:40', 'end' => '20:30', 'start_min' => 1180, 'end_min' => 1230, 'session' => 'evening'],
    ],

    // 5 Ca chuẩn HUIT
    'shifts' => [
        1 => ['name' => 'Ca 1', 'label' => 'Ca 1 (Tiết 1-3)',   'start_period' => 1,  'end_period' => 3,  'time' => '07:00 - 09:30', 'session' => 'morning'],
        2 => ['name' => 'Ca 2', 'label' => 'Ca 2 (Tiết 4-6)',   'start_period' => 4,  'end_period' => 6,  'time' => '09:40 - 12:10', 'session' => 'morning'],
        3 => ['name' => 'Ca 3', 'label' => 'Ca 3 (Tiết 7-9)',   'start_period' => 7,  'end_period' => 9,  'time' => '12:30 - 15:00', 'session' => 'afternoon'],
        4 => ['name' => 'Ca 4', 'label' => 'Ca 4 (Tiết 10-12)', 'start_period' => 10, 'end_period' => 12, 'time' => '15:10 - 17:40', 'session' => 'afternoon'],
        5 => ['name' => 'Ca 5', 'label' => 'Ca 5 (Tiết 13-15)', 'start_period' => 13, 'end_period' => 15, 'time' => '18:00 - 20:30', 'session' => 'evening'],
    ],

    'days' => [
        2 => 'Thứ 2',
        3 => 'Thứ 3',
        4 => 'Thứ 4',
        5 => 'Thứ 5',
        6 => 'Thứ 6',
        7 => 'Thứ 7',
        8 => 'Chủ nhật',
    ],

    'sessions' => [
        'morning'   => 'Buổi Sáng',
        'afternoon' => 'Buổi Chiều',
        'evening'   => 'Buổi Tối',
    ],

    // Tham số thuật toán tối ưu
    'optimization' => [
        'tight_travel_buffer_minutes' => 15,
        'dead_gap_threshold_periods'  => 3,
        'max_commutes_per_day'        => 2,
        'default_travel_minutes'      => 20,
        'health_penalties' => [
            'impossible_travel' => 15,
            'tight_travel'      => 5,
            'dead_gap'          => 3,
            'load_stddev'       => 2,
        ],
        'scoring' => [
            'expertise_direct'   => 40,
            'expertise_cluster'  => 25,
            'expertise_partial'  => 12,
            'avail_match'        => 20,
            'avail_branch_bonus' => 5,
            'opt_adjacent_same'  => 20,
            'opt_clean_day'      => 12,
            'opt_dead_gap'       => 0,
            'opt_diff_comfort'   => -5,
            'opt_diff_tight'     => -12,
            'opt_diff_impossible'=> -25,
            'workload_max'       => 15,
        ],
    ],

    // Stop-words học thuật tiếng Việt (đã bỏ dấu, chữ thường)
    'academic_stopwords' => [
        'nhap', 'mon', 'co', 'so', 'thuc', 'hanh', 'nang', 'cao', 'ung', 'dung',
        'do', 'an', 'chuyen', 'de', 'hoc', 'phan', 'dai', 'cuong', 'co', 'ban',
        'ky', 'thuat', 'cong', 'nghe', 'he', 'thong', 'phat', 'trien', 'lap', 'trinh',
        'va', 'voi', 'cho', 'trong', 'cua', 'tren', 'tai', 'tu', 've', 'cac', 'nhung',
        '1', '2', '3', 'i', 'ii', 'iii', 'a', 'b', 'c',
    ],

    // Từ điển cụm chuyên môn CNTT (đã bỏ dấu, chữ thường)
    'expertise_clusters' => [
        'opensource_web' => [
            'label' => 'Mã nguồn mở & Web Backend',
            'keywords' => [
                'ma nguon mo', 'open source', 'opensource', 'php', 'laravel', 'symfony',
                'wordpress', 'composer', 'web', 'backend', 'mvc', 'rest', 'api', 'git',
                'github', 'node', 'nodejs', 'express', 'django', 'flask', 'apache', 'nginx',
            ],
        ],
        'database_bigdata' => [
            'label' => 'Cơ sở dữ liệu & Dữ liệu lớn',
            'keywords' => [
                'co so du lieu', 'csdl', 'database', 'sql', 'mysql', 'postgresql', 'postgres',
                'mssql', 'oracle', 'nosql', 'mongodb', 'redis', 'neo4j', 'hadoop', 'spark',
                'bigdata', 'big data', 'du lieu lon', 'data warehouse', 'kho du lieu', 'etl',
                'he quan tri', 'hqtcsdl', 'truy van', 'quan tri du lieu',
            ],
        ],
        'ai_datascience' => [
            'label' => 'Trí tuệ nhân tạo & Khoa học dữ liệu',
            'keywords' => [
                'tri tue nhan tao', 'ai', 'machine learning', 'hoc may', 'deep learning',
                'hoc sau', 'data science', 'khoa hoc du lieu', 'python', 'nlp', 'xu ly ngon ngu',
                'thi giac may tinh', 'computer vision', 'khai pha du lieu', 'data mining',
                'pytorch', 'tensorflow', 'llm', 'neural', 'phan tich du lieu', 'thong ke',
            ],
        ],
        'software_engineering' => [
            'label' => 'Công nghệ phần mềm, OOP & Mobile',
            'keywords' => [
                'cong nghe phan mem', 'cnpm', 'software engineering', 'oop', 'huong doi tuong',
                'java', 'spring', 'csharp', 'c#', 'dotnet', '.net', 'aspnet', 'c++', 'cpp',
                'mobile', 'di dong', 'android', 'ios', 'flutter', 'kotlin', 'swift',
                'kiem thu', 'testing', 'qa', 'uml', 'phan tich thiet ke', 'kien truc phan mem',
                'cau truc du lieu', 'giai thuat', 'thuat toan', 'dsa',
            ],
        ],
        'network_security_devops' => [
            'label' => 'Mạng máy tính, An toàn thông tin & DevOps',
            'keywords' => [
                'mang may tinh', 'network', 'networking', 'cisco', 'routing', 'switching',
                'an toan thong tin', 'attt', 'bao mat', 'security', 'cybersecurity', 'pentest',
                'ma hoa', 'cryptography', 'linux', 'ubuntu', 'he dieu hanh', 'os',
                'devops', 'docker', 'kubernetes', 'k8s', 'ci/cd', 'cicd', 'cloud',
                'dien toan dam may', 'aws', 'azure', 'gcp', 'quan tri mang', 'firewall',
            ],
        ],
        'frontend_uiux' => [
            'label' => 'Frontend & Thiết kế giao diện UI/UX',
            'keywords' => [
                'frontend', 'front-end', 'html', 'css', 'javascript', 'js', 'typescript', 'ts',
                'react', 'reactjs', 'vue', 'vuejs', 'angular', 'nextjs', 'tailwind', 'bootstrap',
                'ui', 'ux', 'ui/ux', 'thiet ke web', 'giao dien', 'figma', 'do hoa', 'multimedia',
            ],
        ],
        'iot_embedded' => [
            'label' => 'Hệ thống nhúng & IoT',
            'keywords' => [
                'iot', 'internet of things', 'nhung', 'embedded', 'arduino', 'esp32',
                'raspberry', 'vi dieu khien', 'cam bien', 'sensor', 'mqtt', 'kien truc may tinh',
            ],
        ],
    ],
];

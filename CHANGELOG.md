# Changelog

Mọi thay đổi quan trọng của **HUIT Schedule Hub** đều được ghi nhận tại đây theo chuẩn [Keep a Changelog](https://keepachangelog.com/en/1.1.0/) và [Semantic Versioning](https://semver.org/).

## [1.0.0] - 2026-11-18

### Added
- **Kiến trúc 5 lớp chuẩn Clean Architecture (PHP 8.x, PSR-4):** `Router/Middleware` → `Controller` → `Application Service & Optimization Engine` → `Domain Value Objects` → `Repository (PDO)`.
- **Bảo mật & Toàn vẹn giao dịch:** MySQL Transaction + `SELECT ... FOR UPDATE` chống race condition khi phân lịch đồng thời, 100% PDO Prepared Statements (strict mode), CSRF `hash_equals()`, XSS escaping `e()`, Bcrypt password hashing, session hardening và `.htaccess` chặn truy cập file nhạy cảm.
- **Động cơ Phân lịch & Tối ưu Đa Cơ sở:**
  - `TimeSlot` Value Object (15 tiết chuẩn đại học, đại số giao khoảng & phút nghỉ thực tế).
  - `ScheduleOptimizerService` (Ma trận di chuyển 2 chiều giữa 4 cơ sở HUIT, phát hiện `IMPOSSIBLE_TRAVEL`, `TIGHT_TRAVEL`, `EXCESSIVE_COMMUTE`, `DEAD_GAP`, chỉ số `Optimization Health Score 0-100%`, gợi ý Top 5 ô tiết trống & khắc phục 1-click).
  - `ExpertiseMatcher` & `TeacherRecommendationService` (NLP-lite bỏ dấu tiếng Việt, từ điển cụm chuyên môn CNTT, chấm điểm đa mục tiêu 0–100 minh bạch).
- **Dashboard & Biểu đồ Chart.js 4:** 5 thẻ KPI, 3 biểu đồ tương tác (Horizontal Bar định mức GV, Doughnut cơ sở, Stacked Bar Thứ × Buổi) và Trung tâm Cảnh báo Tối ưu.
- **Ma trận Tiết trống & Phân lịch Trực quan:** Lưới Thứ 2–7 × 5 Ca với click-to-schedule.
- **Cổng thông tin Giảng viên (`/portal`):** Đăng nhập tài khoản hoặc Link Portal cá nhân (`token`), Lịch Tuần, Lịch Tháng, Widget Lộ trình Di chuyển, Xuất file `.ics` chuẩn RFC 5545 và tự đăng ký tiết trống.
- **Kiểm thử & CI/CD:** `tests/unit_tests.php`, `tests/integration_verify.php` (10 kịch bản giao dịch rollback) và GitHub Actions workflow `.github/workflows/php-lint.yml`.

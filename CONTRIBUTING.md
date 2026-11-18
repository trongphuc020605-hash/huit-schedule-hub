# Contributing to HUIT Schedule Hub

Cảm ơn bạn đã quan tâm và đóng góp cho **HUIT Schedule Hub** — Hệ thống Quản lý & Phân lịch Giảng dạy Đa Cơ sở Thông minh!

## Chuẩn Mã nguồn (Coding Standards)
- Tuân thủ chuẩn **PSR-12** và **PSR-4 Autoloading** (`HuitSchedule\` → `src/`).
- Bắt buộc khai báo `declare(strict_types=1);` ở đầu 100% file class, domain, service, repository và controller.
- **Tách lớp nghiêm ngặt:** Tuyệt đối không viết SQL hoặc logic thuật toán trong thư mục `views/`.
- Mọi thao tác ghi dữ liệu lịch học phải đi qua `Database::transaction()` kèm khóa hàng `SELECT ... FOR UPDATE`.
- Mọi output HTML bắt buộc escape qua helper `e()`. Mọi request `POST`/`DELETE` bắt buộc kiểm tra CSRF token.

## Quy ước Commit (Conventional Commits)
- `feat(scheduler): ...` — Tính năng mới
- `fix(optimizer): ...` — Sửa lỗi nghiệp vụ hoặc thuật toán
- `docs(readme): ...` — Cập nhật tài liệu
- `test(unit): ...` — Bổ sung hoặc cập nhật kiểm thử
- `chore(ci): ...` — Cấu hình CI/CD, Composer, build

## Quy trình Gửi Pull Request
1. Fork repository và tạo nhánh tính năng từ `main` (`git checkout -b feat/your-feature`).
2. Cài đặt dependencies bằng `composer install`, sao chép `.env.example` thành `.env` (không commit `.env` hay `vendor/`).
3. Chạy đầy đủ kiểm tra trước khi mở PR:
   ```bash
   # Kiểm tra cú pháp toàn bộ file PHP
   find . -path './vendor' -prune -o -name '*.php' -exec php -l {} +

   # Kiểm tra composer.json
   composer validate --no-check-publish

   # Chạy bộ kiểm thử đơn vị và tích hợp
   php tests/unit_tests.php
   php tests/integration_verify.php
   ```
4. Mở Pull Request mô tả rõ ngữ cảnh bài toán, hướng giải quyết và kết quả kiểm thử. GitHub Actions CI (`php-lint.yml`) sẽ tự động xác minh PR của bạn.

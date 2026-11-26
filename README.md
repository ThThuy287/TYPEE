# TYPEE
Đồ án môn Thiết kế hệ thống thương mại điện tử

## Chạy dự án bằng Docker

```powershell
docker compose up -d --build
```

- Website: http://localhost:8000 · phpMyAdmin: http://localhost:8081
- Plugin và theme bên thứ ba (WooCommerce, WPForms, ACF, Rank Math, Flatsome…) được **đóng sẵn vào image** để chạy nhanh trên Windows. Đọc file từ ổ C: gắn vào Docker chậm hơn khoảng 270 lần so với file trong container.
- Các thư mục vẫn nối trực tiếp với máy, sửa là thấy ngay (code PHP có hiệu lực sau tối đa 10 giây):
  `wp-content/themes/typee-child`, `wp-content/plugins/typee-core`, `wp-content/mu-plugins`, `wp-content/uploads`, `wp-content/fonts`.

### Khi thêm hoặc cập nhật plugin/theme bên thứ ba
1. Cập nhật thư mục plugin trong `wp-content/plugins/` của dự án (và commit).
2. Chạy lại `docker compose up -d --build`.

Không bấm "Cập nhật" plugin trong trang quản trị WordPress: bản cập nhật chỉ nằm trong container và sẽ mất khi khởi động lại.

### Thêm một plugin/theme do nhóm tự viết
Thêm một dòng nối thư mục đó trong `volumes` của dịch vụ `wordpress` (`docker-compose.yml`) và một dòng loại trừ trong `.dockerignore`, giống `typee-core`.

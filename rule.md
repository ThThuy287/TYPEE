## 📌 Quy tắc Làm việc & Tránh xung đột (Workflow & Conventions)

Để đảm bảo dự án vận hành trơn tru, tránh mất dữ liệu và hạn chế tối đa conflict khi ghép code, toàn bộ 7 thành viên vui lòng tuân thủ các nguyên tắc sau:

### 1. Quản lý Mã nguồn (Git Workflow)
*   **Không code/push trực tiếp lên nhánh `main`:** Nhánh `main` là môi trường hoàn thiện. Mọi thay đổi phải được thực hiện trên nhánh riêng.
*   **Quy tắc đặt tên nhánh:** Đặt tên theo cú pháp `loại/tên-task` hoặc ghép với mã sinh viên để dễ tracking.
    *   Ví dụ: `feature/keyboard-builder`, `ui/checkout-page`, `fix/cart-bug`.
*   **Pull trước khi Push:** Luôn chạy `git pull origin main` để cập nhật code mới nhất từ kho chung về máy trước khi làm việc, và trước khi đẩy code của bạn lên.
*   **Commit rõ ràng (Conventional Commits):** Sử dụng các tiền tố để mô tả công việc.
    *   `feat:` Thêm tính năng mới (PHP/JS).
    *   `ui:` Cập nhật giao diện (CSS/Tailwind).
    *   `fix:` Sửa lỗi.
    *   `docs:` Cập nhật tài liệu/README.

### 2. Quản lý Môi trường (Docker & Bảo mật)
*   **Môi trường chuẩn:** 100% thành viên khởi chạy dự án bằng **Docker** (`docker-compose up -d`). Không dùng XAMPP/WAMP ngoài luồng để tránh sai lệch phiên bản PHP/MySQL hay lỗi môi trường.
*   **Bảo mật thông tin:** **Tuyệt đối không push file `.env` lên GitHub.** 
*   **Setup lần đầu:** Thành viên mới khi clone source code về chỉ cần nhân bản file `.env.example` đổi tên thành `.env` và giữ nguyên cấu hình mặc định (hoặc sửa theo local của mình) để chạy Docker.

### 3. Đồng bộ Database (Quy tắc đặc thù của WordPress)
*   **Cảnh báo:** Git chỉ theo dõi **Code** (các file trong `wp-content/themes` hoặc `plugins`), **KHÔNG** theo dõi **Database**. Mọi thiết lập trong trang Admin (thêm sản phẩm, cài config WooCommerce, tạo page mới) sẽ chỉ lưu ở máy local của bạn.
*   **Tránh ghi đè Database:** Nếu bạn được phân công setup dữ liệu mẫu hoặc cấu hình lõi WooCommerce, sau khi hoàn thiện hãy export file `.sql` đẩy vào thư mục `database/` (hoặc gửi lên nhóm) và thông báo để các thành viên khác import.
*   Hạn chế tối đa việc 2 người cùng cấu hình setting Admin/Database trên 2 máy khác nhau cùng lúc để tránh việc không thể gộp (merge) dữ liệu sau này.

### 4. Tiêu chuẩn Code & QA
*   **Không sửa Core:** Mọi code custom (Theme, Plugin riêng cho EAV/Trình build phím) phải được đặt đúng trong thư mục `wp-content/`. Không can thiệp vào mã nguồn gốc của WordPress hay plugin WooCommerce.
*   **Tự kiểm thử (Self-QA):** Bắt buộc test kỹ luồng hoạt động trên local của mình (check cả Console Log xem có lỗi JS không, check Responsive giao diện) trước khi báo cáo hoàn thành hoặc tạo Pull Request.
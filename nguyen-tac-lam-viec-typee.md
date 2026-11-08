# NGUYÊN TẮC LÀM VIỆC VÀ QUẢN LÝ MÃ NGUỒN DỰ ÁN TYPEE

Tài liệu này quy định cách các thành viên làm việc với GitHub, đồng bộ
dữ liệu WordPress và tự kiểm tra sản phẩm trước khi gửi Pull Request
(PR). Mục tiêu là hạn chế xung đột, tránh ghi đè dữ liệu của nhau và giữ
cho nhánh `main` luôn ổn định.

## 1. Quy trình làm việc với Git và GitHub

### 1.1. Bắt đầu công việc mới

**Trước khi viết hoặc chỉnh sửa bất kỳ dòng code nào**, mỗi thành viên
phải cập nhật nhánh `main` mới nhất và tạo một nhánh làm việc riêng
(feature branch).

Thực hiện lần lượt:

``` bash
# Chuyển về nhánh main
git checkout main

# Lấy phiên bản mới nhất từ GitHub
git pull origin main

# Tạo và chuyển sang nhánh làm việc riêng
git checkout -b feature/custom-builder-ui
```

Đặt tên nhánh theo quy ước:

  -----------------------------------------------------------------------------
  Tiền tố                 Mục đích                Ví dụ
  ----------------------- ----------------------- -----------------------------
  `feature/`              Phát triển tính năng    `feature/custom-builder-ui`
                          mới                     

  `fix/`                  Sửa lỗi                 `fix/cart-total-error`

  `config/`               Cấu hình hệ thống hoặc  `config/eav-attributes`
                          dữ liệu thuộc phạm vi   
                          cấu hình                

  `docs/`                 Cập nhật tài liệu       `docs/setup-guide`
  -----------------------------------------------------------------------------

**Lưu ý:** - Mỗi task nên được thực hiện trên một nhánh riêng. - Không
code trực tiếp trên nhánh `main`. - Không dùng chung một feature branch
cho nhiều thành viên. - Nếu nhánh `main` có cập nhật mới trong lúc đang
làm, hãy đồng bộ theo hướng dẫn của người phụ trách trước khi tiếp tục
hoặc trước khi tạo PR.

### 1.2. Code và commit thường xuyên

Sau khi hoàn thành một phần việc nhỏ, có thể kiểm tra và commit ngay.
Không nên đợi đến khi hoàn thành toàn bộ task mới lưu lại lịch sử thay
đổi.

``` bash
# Kiểm tra các file đã thay đổi
git status

# Thêm các thay đổi vào staging area
git add .

# Tạo commit
git commit -m "feat: hoàn thiện giao diện bước 1 chọn Kit"
```

Sử dụng thông điệp commit rõ ràng theo quy ước **Conventional Commits**:

  --------------------------------------------------------------------------------------
  Loại commit             Ý nghĩa                 Ví dụ
  ----------------------- ----------------------- --------------------------------------
  `feat:`                 Thêm tính năng          `feat: thêm bộ lọc sản phẩm`

  `fix:`                  Sửa lỗi                 `fix: sửa lỗi tính tổng giá`

  `docs:`                 Cập nhật tài liệu       `docs: bổ sung hướng dẫn cài đặt`

  `style:`                Điều chỉnh định dạng,   `style: chuẩn hóa khoảng cách nút`
                          giao diện không đổi     
                          logic                   

  `refactor:`             Tái cấu trúc code,      `refactor: tách hàm xử lý giỏ hàng`
                          không thay đổi chức     
                          năng                    

  `test:`                 Thêm hoặc cập nhật kiểm `test: bổ sung kiểm thử rule engine`
                          thử                     

  `chore:`                Công việc cấu hình, bảo `chore: cập nhật cấu hình Docker`
                          trì                     
  --------------------------------------------------------------------------------------

**Lưu ý:** - Commit nên nhỏ, tập trung vào một nội dung thay đổi. -
Không commit file chứa thông tin bí mật như `.env`, mật khẩu, API key
hoặc dữ liệu cá nhân. - Kiểm tra `.gitignore` trước khi dùng
`git add .`. Không mặc định rằng mọi file nhạy cảm đều đã được loại trừ.

### 1.3. Push code và tạo Pull Request

Khi hoàn thành task và đã tự kiểm tra, thành viên đẩy nhánh cá nhân lên
GitHub:

``` bash
git push -u origin feature/custom-builder-ui
```

Sau đó thực hiện trên GitHub:

1.  Mở repository TYPEE.
2.  Chọn **Compare & pull request** hoặc tạo Pull Request thủ công.
3.  Chọn nhánh nguồn là nhánh cá nhân và nhánh đích là `main`.
4.  Đặt tiêu đề PR rõ ràng, mô tả nội dung đã thực hiện, cách kiểm tra
    và các ảnh chụp màn hình cần thiết.
5.  Gán Thanh Thủy hoặc người được phân công làm Reviewer.
6.  Chỉ merge khi PR đã được kiểm tra và chấp thuận.

**Nguyên tắc bắt buộc:** - Không merge trực tiếp vào `main` từ máy cá
nhân. - Không tự ý bỏ qua bước review. - Không force push lên nhánh dùng
chung hoặc nhánh `main`. - Nếu phát sinh conflict, thành viên phụ trách
nhánh cần phối hợp với Reviewer để xử lý và kiểm tra lại trước khi
merge. - Không xem trạng thái "không có conflict" là bằng chứng code đã
đúng; PR vẫn cần được review và kiểm thử.

## 2. Quy trình đồng bộ dữ liệu Database WordPress

### 2.1. Vì sao Git không đồng bộ được Database?

Git quản lý tốt các file source code, chẳng hạn:

-   `wp-content/themes/`
-   `wp-content/plugins/`
-   Các file cấu hình và tài liệu được đưa vào repository.

Tuy nhiên, Git **không tự động quản lý các bản ghi trong Database
WordPress**, ví dụ:

-   Sản phẩm, danh mục và bài viết.
-   Cấu hình plugin.
-   Coupon và chương trình khuyến mãi.
-   Custom field của ACF.
-   Các thuộc tính và dữ liệu EAV được lưu trong Database.

Vì vậy, khi hai thành viên thao tác trên hai Database local khác nhau,
dữ liệu của họ không tự động được đồng bộ thông qua Git.

### 2.2. Thiết lập Database chuẩn ban đầu

Người phụ trách thiết lập môi trường (ví dụ: Thanh Thủy) tạo Database
chuẩn ban đầu, bao gồm các cấu hình nền tảng đã thống nhất:

-   Cấu trúc WordPress và các plugin cần thiết.
-   Cấu hình cốt lõi của website.
-   Cấu trúc dữ liệu EAV và các thuộc tính dùng chung.
-   Dữ liệu mẫu cần thiết để các thành viên kiểm thử.

Sau khi kiểm tra, người phụ trách export Database thành file `.sql` và
lưu tại thư mục dùng chung của nhóm trên Google Drive, kèm ngày cập nhật
và ghi chú phiên bản.

Ví dụ tên file:

``` text
TYPEE_DB_2026-11-08_v1.sql
```

### 2.3. Quy trình import và đồng bộ dữ liệu

Khi cần làm việc dựa trên Database chuẩn mới nhất, thành viên thực hiện:

1.  Sao lưu Database local hiện tại trước khi import.
2.  Tải đúng file `.sql` mới nhất từ thư mục chung.
3.  Import vào Database local theo hướng dẫn của người phụ trách.
4.  Kiểm tra website, sản phẩm, cấu hình plugin và các chức năng liên
    quan.
5.  Tiếp tục thực hiện task được phân công.

**Cảnh báo:** Import đè có thể thay thế dữ liệu hiện có trong Database
local. Không import khi chưa sao lưu hoặc chưa xác nhận đúng Database
đích.

### 2.4. Phân chia công việc để tránh ghi đè dữ liệu

Các thao tác trên WordPress Admin có thể làm thay đổi cùng một Database
khi được thực hiện trên môi trường chung. Vì vậy, nhóm cần phân chia rõ
người phụ trách từng nhóm dữ liệu và tránh chỉnh sửa đồng thời cùng một
cấu hình.

Ví dụ:

  -----------------------------------------------------------------------
  Giai đoạn               Thành viên A            Thành viên B
  ----------------------- ----------------------- -----------------------
  1                       Nhập dữ liệu sản phẩm   Chờ Database chuẩn

  2                       Export Database và cập  Import Database mới
                          nhật file trên Drive    

  3                       Chuyển sang task khác   Cấu hình coupon/khuyến
                          hoặc chờ xác nhận       mãi

  4                       Import Database đã cập  Export Database và cập
                          nhật nếu cần            nhật file trên Drive
  -----------------------------------------------------------------------

**Quy tắc đồng bộ:** - Chỉ định một người phụ trách việc cập nhật bản
Database chuẩn tại từng thời điểm. - Trước khi import hoặc export, thông
báo cho nhóm để tránh hai người cập nhật chồng lấn. - Ghi rõ ai cập
nhật, nội dung thay đổi, thời gian và phiên bản file. - Không tự ý
export một Database cũ rồi ghi đè lên bản mới hơn trên Drive. - Nếu hai
task cần chỉnh sửa Database, phải thống nhất thứ tự thực hiện và bàn
giao dữ liệu.

**Bảo mật dữ liệu:** File `.sql` có thể chứa tài khoản, email, thông tin
khách hàng, mật khẩu đã mã hóa hoặc dữ liệu nhạy cảm khác. Chỉ chia sẻ
trong phạm vi nhóm được phép truy cập; ưu tiên dùng dữ liệu mẫu đã loại
bỏ thông tin cá nhân. Không đưa Database chứa dữ liệu nhạy cảm lên
repository công khai.

## 3. Quy trình Self-QA trước khi tạo Pull Request

Mỗi thành viên có trách nhiệm tự kiểm tra thay đổi trên môi trường
Docker local trước khi push code và tạo PR. Mục tiêu là phát hiện lỗi
sớm, tránh làm ảnh hưởng đến nhánh `main` và công việc của các thành
viên khác.

### 3.1. Kiểm tra Docker

Khởi động hệ thống:

``` bash
docker-compose up -d
```

Kiểm tra trạng thái các container:

``` bash
docker-compose ps
```

Đảm bảo các service cần thiết đang chạy ổn định, không có container bị
`Exited` hoặc liên tục khởi động lại.

Nếu có lỗi, kiểm tra log:

``` bash
docker-compose logs
```

### 3.2. Kiểm tra cache

Do dự án có sử dụng Redis Object Cache và các cơ chế cache khác, thay
đổi mới có thể chưa hiển thị ngay trên trình duyệt.

Thành viên cần:

-   Xóa cache phù hợp với môi trường đang sử dụng (WordPress, plugin
    cache, Redis Object Cache hoặc trình duyệt).
-   Tải lại trang và kiểm tra lại chức năng.
-   Có thể mở cửa sổ ẩn danh (Incognito) để đối chiếu kết quả.

Không tắt hoặc xóa dữ liệu cache trên môi trường chung nếu chưa được
người phụ trách cho phép.

### 3.3. Kiểm tra JavaScript Console

Mở website trên trình duyệt, nhấn `F12` và chọn tab **Console**.

Kiểm tra: - Không có lỗi JavaScript phát sinh từ phần code vừa thay
đổi. - Các nút bấm, sự kiện và thao tác tương tác hoạt động đúng. - Các
chức năng như Custom Builder và Rule Engine không phát sinh lỗi khi thao
tác. - Nếu có lỗi, cần ghi nhận và xử lý trước khi tạo PR.

### 3.4. Kiểm tra giao diện Responsive

Sử dụng **Responsive Design Mode** trong Developer Tools (`F12`) để kiểm
tra ở các kích thước màn hình khác nhau.

Tối thiểu cần kiểm tra: - Desktop. - Tablet. - Mobile.

Đảm bảo: - Nội dung không bị tràn hoặc che khuất. - Nút bấm và biểu mẫu
có thể thao tác được. - Hình ảnh, bảng và thành phần giao diện hiển thị
hợp lý. - Các bước trong Custom Builder vẫn dễ sử dụng trên màn hình
nhỏ.

### 3.5. Checklist Self-QA

Trước khi tạo PR, thành viên cần tự xác nhận:

-   [ ] Đã chạy Docker và các container cần thiết hoạt động bình thường.
-   [ ] Đã kiểm tra chức năng trên môi trường local.
-   [ ] Đã xóa hoặc làm mới cache khi cần.
-   [ ] Đã kiểm tra Console và xử lý lỗi JavaScript liên quan.
-   [ ] Đã kiểm tra giao diện trên Desktop, Tablet và Mobile.
-   [ ] Đã kiểm tra các chức năng cũ có liên quan, tránh gây lỗi hồi
    quy.
-   [ ] Không đưa `.env`, API key, mật khẩu hoặc dữ liệu nhạy cảm vào
    commit.
-   [ ] Đã commit với thông điệp rõ ràng.
-   [ ] Đã push đúng feature branch và tạo PR vào `main`.
-   [ ] Đã mô tả nội dung thay đổi và cách kiểm thử trong PR.

## 4. Nguyên tắc phối hợp chung

1.  **Một task -- một nhánh làm việc:** Giúp dễ theo dõi lịch sử và giới
    hạn phạm vi thay đổi.
2.  **Commit thường xuyên:** Lưu lại tiến độ theo từng phần việc nhỏ.
3.  **Không sửa trực tiếp `main`:** Mọi thay đổi phải đi qua Pull
    Request và được review.
4.  **Database có quy trình riêng:** Không kỳ vọng Git tự đồng bộ dữ
    liệu WordPress.
5.  **Có bàn giao khi cập nhật Database:** Thành viên tiếp theo chỉ
    import và làm việc khi đã xác nhận đúng phiên bản.
6.  **Self-QA trước PR:** Người viết code chịu trách nhiệm kiểm tra thay
    đổi của mình.
7.  **Trao đổi khi có rủi ro:** Chủ động thông báo khi gặp conflict, lỗi
    môi trường, thay đổi cấu trúc dữ liệu hoặc nguy cơ ảnh hưởng task
    khác.

------------------------------------------------------------------------

**Mục tiêu cuối cùng:** Mỗi thành viên có thể phát triển độc lập trên
môi trường local, phối hợp thông qua GitHub và quy trình bàn giao
Database rõ ràng, đồng thời giữ cho mã nguồn và dữ liệu của dự án TYPEE
ổn định.

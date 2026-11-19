# Hướng dẫn dành cho quản trị viên HalalBizs

Hướng dẫn này dành cho nhân viên HalalBizs vận hành sàn: duyệt người bán và chứng nhận halal, theo dõi đơn hàng, thanh toán, đổi trả và rút tiền, và quản lý giao diện cửa hàng. Tên nút và tên menu được in **đậm**, đúng như Admin panel hiển thị. Màn hình Admin hiện vẫn hầu hết bằng tiếng Anh dù bạn chọn tiếng Việt, nên hướng dẫn giữ nguyên nhãn tiếng Anh và giải thích bằng tiếng Việt. Bạn thấy được những gì tuỳ theo quyền của tài khoản.

> Hiện sàn đang ở chế độ chỉ đăng sản phẩm (**System > Settings > General**, **Allow buyers to add to cart and checkout** đang tắt). Thanh toán trực tuyến vẫn đang phát triển và chạy bằng trình mô phỏng có sẵn. Cần có mã merchant thật trước khi tiền thật được chuyển.

## 1. Đăng nhập

1. Mở trang đăng nhập admin (/admin/login). Nhập **Email** và **Password** rồi chọn **Log in**. Ở đây không có đăng ký: trang ghi "Admin access is granted by an existing administrator."
2. Nếu tài khoản bật đăng nhập hai bước, trang **Two-factor check** hỏi mã 6 chữ số. Nhập mã và chọn **Verify and log in**. Chọn **Email me a new code** nếu mã hết hạn.
3. Email của bạn phải được xác minh. Hiện tại hai bước cho admin là tuỳ chọn.
4. Khi bạn đăng nhập từ thiết bị mới mà chưa bật đăng nhập hai bước, trang **Two-factor check** ghi "You're signing in from a new device. Enter the code we emailed to confirm it's you." Nhập mã 6 chữ số trong email "Your login code" (hết hạn sau 10 phút) và chọn **Verify and log in**. Thiết bị chỉ được ghi nhớ sau khi mã đúng. Thiết bị đầu tiên bạn dùng không cần mã.
5. Menu gồm: **Dashboard**, **Sellers**, **Buyers**, **Catalog**, **Orders**, **Finance**, **Content**, **Support**, **System**. Mục nào bạn không có quyền sẽ bị ẩn hoặc bị chặn. <!-- SHOT: admin menu -->
6. Chọn **View storefront** để xem trang công khai và **Log out** để thoát.
7. Để thêm admin: **System > Staff & roles**, chọn **Invite admin**, nhập **Name** và **Email**, đánh dấu các **Permissions** và chọn **Invite admin**. Hệ thống tạo mật khẩu tạm để bạn chuyển cho họ. **Edit permissions**, **Make superadmin** và **Remove admin** nằm trên cùng danh sách.

## 2. Dashboard và báo cáo

1. **Dashboard** hiện **GMV (paid)**, **Commission revenue**, **Completed sub-orders**, **Take-rate**, **Boost revenue**, **Orders today**, **New buyers today**, **Coins in circulation**, **Active subscriptions**, **Affiliate referrals**, **Live now** và **Group-buy unlock rate**. Đổi kỳ bằng các nút chọn kỳ thống kê.
2. **Pending queues** dẫn thẳng tới việc đang chờ: đơn đăng ký, chứng nhận, kiểm duyệt, rút tiền, đổi trả.
3. Biểu đồ: **GMV — last 30 days**, **New buyers over time**, **Orders by status**, **Top categories by GMV**, **Top stores by GMV**.
4. **System > Search insights** hiện các tìm kiếm **Trending** và **Zero-result** (người mua muốn gì mà không thấy).
5. **System > Audit log** liệt kê ai đã đổi gì, có ô tìm kiếm (người thực hiện, sự kiện, mô tả), bộ lọc **Subject** và ngày, **Diff** các thay đổi, và **Rows per page** dưới danh sách.
6. **Finance > Boosts** báo cáo thu nhập từ boost. **Finance > Payouts** có **Export bank CSV**.

## 3. Người bán và đơn đăng ký

1. Mở **Sellers > Applications**. Hàng chờ xếp cũ nhất trước. Chọn **Review** ở một hàng. <!-- SHOT: application review panel -->
2. Kiểm tra chủ cửa hàng, **Phone**, **State**, **SST registered**, **Bank details** và **Documents** (chứng nhận SSM và bản sao IC). Chọn **Open file** để xem từng tệp (riêng tư, chỉ admin).
3. Với mỗi tài liệu chọn **Verify**, hoặc **Reject** (người đăng ký phải nộp lại). Thêm ghi chú nếu cần.
4. Để chấp nhận, chọn **Approve store**. Chủ cửa hàng có quyền người bán ngay và nhận email.
5. Để từ chối, viết **Rejection reason** (bắt buộc, gửi email cho người đăng ký) rồi chọn **Reject application**.
6. **Sellers > Stores** liệt kê mọi cửa hàng. Tìm theo tên cửa hàng hoặc email chủ, lọc theo trạng thái và sắp xếp theo tiêu đề cột. Mở một cửa hàng để xem **Live products**, **Sub-orders**, **GMV (completed)**.
7. Để đình chỉ, viết **Suspension reason (emailed to the owner)** rồi chọn **Suspend store**. Cửa hàng rời khỏi trang công khai. **Reinstate store** đưa nó trở lại.
8. **Record settlement**: khi người bán đang nợ tiền chuyển khoản trả, nhập **Amount (RM)**, **Bank reference** và ghi chú, rồi chọn **Record settlement**.
9. **Buyers**: tìm theo tên hoặc email, lọc theo trạng thái, sắp xếp theo tiêu đề cột, mở một người mua và dùng **Suspend account** (kèm lý do) hoặc **Unsuspend account**.

## 4. Duyệt chứng nhận halal

1. Mở **Catalog > Halal certificates**. Bộ lọc: **Awaiting review**, **Approved**, **Rejected**, **All**, cùng **Issuing body**, **Store**, **Expiring soon**.
2. Chọn **Review** ở một hàng. Kiểm tra **Holder**, **Issuing body**, **Standard**, **Facility**, **Scope** và chọn **Open the certificate document**. Tài liệu là riêng tư. Nếu không có tệp đính kèm, hãy từ chối và yêu cầu nộp.
3. So sánh các sản phẩm người bán đã đánh dấu với phạm vi của chứng nhận. Sản phẩm mà phạm vi không nêu hiện "Not on the certificate". Chỉ chọn **Allow** khi bạn chắc chắn nó được bao phủ.
4. Chọn **Approve** khi hợp lệ. Không sản phẩm nào có nhãn halal cho đến khi bạn duyệt.
5. Để từ chối, viết lý do ("The seller sees this word for word") và chọn **Reject**.
6. Chứng nhận sắp hết hạn hiện cảnh báo cho người bán. Khi chứng nhận hết hạn, nhãn mất, và với danh mục bị chặn thì sản phẩm không còn được phép đăng.

## 5. Danh mục và quy định halal

1. Mở **Catalog > Categories**. Cây danh mục sâu tối đa ba cấp. Dùng **Add root category**, **Add child**, **Edit**, nút di chuyển lên và xuống, và **Activate** hoặc **Deactivate**. **Delete** chỉ dùng được khi danh mục không có danh mục con và sản phẩm.
2. Trong biểu mẫu điền **Name (English)**, và tên, mô tả tiếng Malay và tiếng Việt tuỳ chọn. Thêm **Image** và đánh dấu **Active — visible on the storefront**. Chọn **Attributes for this category**.
3. Đặt **Halal certificate**: **Inherit from parent**, **Required to list** hoặc **Not required**. Required nghĩa là người bán không đăng được ở đó nếu thiếu chứng nhận đã duyệt bao phủ sản phẩm.
4. Quy định bắt đầu từ ngày 2 tháng 12 năm 2026. Trên bản live, các danh mục bắt buộc là **Food & Snacks**, **Groceries & Pantry**, **Drinks**, **Home & Living > Groceries**, **Cosmetics & Care** và **Pharma & Supplements**.
5. Chọn **Create category** hoặc **Save changes**.
6. Các menu danh mục liên quan: **Attributes**, **Brands**, **Reviews** (sắp xếp theo **Rating** hoặc **Posted**, ẩn đánh giá không phù hợp) và **Moderation**.
7. **Moderation**: khi **New products need admin approval before going live** bật (System > Settings), sản phẩm mới chờ ở đây. Chọn **Approve**, hoặc **Reject** kèm lý do (sản phẩm quay về bản nháp và người bán nhận lý do), hoặc **Ban** để gỡ. Bạn có thể xử lý hàng loạt bằng **Approve selected**, **Reject selected**, **Ban selected**.

## 6. Hoa hồng

1. Mở **Finance > Commission**. Quy tắc: mức riêng của cửa hàng, rồi chuỗi danh mục đi lên, rồi mức mặc định toàn sàn.
2. Ở **Global default rate** gõ **Rate (%)** và chọn **Save rate**.
3. Ở **Commission basis** chọn cơ sở tính hoa hồng và chọn **Save basis**. Áp dụng cho đơn hoàn tất từ lúc này trở đi.
4. Dùng **Effective-rate tester**: chọn một **Store** và một **Category** để xem mức một đơn mới sẽ tính.
5. Mức theo danh mục đặt ở **Catalog > Categories** (**Commission rate (%)**, để trống là kế thừa). Mức theo cửa hàng đặt ở **Sellers > Stores > (cửa hàng) > Commission override** với **Save override**.
6. Đổi mức chỉ ảnh hưởng đơn mới. Đơn đã đặt giữ mức của nó.
7. Người bán thấy mức của mình trên form **Become a seller** và trong **Shop settings** ("10% trên mỗi đơn bán", hoặc một khoảng "tùy theo danh mục"), và ở mỗi trang đơn hàng ("Platform commission rate"). Mỗi lần đổi mức toàn sàn hoặc mức danh mục được ghi vào **System > Audit log** với mức cũ và mức mới.

## 7. Đơn hàng, thanh toán và hoàn tiền

1. Mở **Orders > All orders**. Tìm theo mã đơn hoặc mã đơn con, và lọc theo **Status**, **Store**, **Payment method** và ngày. <!-- SHOT: All orders -->
2. Mở một hàng để xem sản phẩm, **Timeline**, **Payment** (phương thức, mã cổng và trạng thái) và **Totals**. Dữ liệu đơn là bản chụp và chỉ đọc.
3. **Admin actions**: **Force cancel** kèm **Cancellation reason** (hàng về kho, người mua được báo, không hoàn tác). Với đơn trực tuyến đã trả tiền và cần hoàn, trước tiên hoàn tiền ở cổng merchant iPay88, rồi nhập **iPay88 portal reference** và chọn **Mark refunded**. Với thanh toán khi nhận hàng thì không có tiền cổng để chuyển.
4. **Orders > Payments** là bảng đối soát. Bảng hiện **Ref no.**, **Trans ID**, **Signature** (**Signature verified** hoặc **Mismatch**), **Requery result** và **Needs portal refund**. Tìm theo mã đơn, **Ref no.** hoặc **Trans ID**, sắp xếp theo **Ref no.**, **Amount**, **Status** hoặc **Paid at**, và lọc bằng **Mismatches only**. Chọn **Requery** ở khoản thanh toán bị treo.
   - Hàng có nhãn **Needs portal refund** nghĩa là cổng thanh toán chưa hoàn tiền. Lọc bằng **Needs portal refund** để xem các hàng này. Trước tiên hoàn tiền ở cổng merchant iPay88. Sau đó chọn **Mark refund settled in portal** ở hàng đó, nhập **Portal reference (optional)** và chọn **Confirm settled**. Nhãn được xoá, hàng chuyển sang **Settled in portal**, và thao tác được lưu trong nhật ký kiểm tra kèm tên bạn và mã tham chiếu. **Cancel** đóng lại mà không thay đổi gì.
5. Khi thanh toán đang ở chế độ mô phỏng, các khoản mô phỏng cũng hiện ở đây.
6. **Orders > Subscriptions** liệt kê người mua định kỳ theo trạng thái.
7. **System > Settings > iPay88** hiện **Simulator active**, **Sandbox configured**, **Live configured** hoặc **Not configured**. Lưu **Merchant code**, **Merchant key** và **Sandbox mode**, rồi chọn **Test connection**. **Online payment is hidden from buyers until both credentials are saved.**
8. Trên bản live hiện chưa lưu mã hay khoá merchant, nên trình mô phỏng đang bật. Đừng nói với ai rằng thanh toán thẻ hoặc FPX thật đã hoạt động cho đến khi lưu đủ cả hai và **Test connection** thành công.

## 8. Đổi trả và hoàn tiền

1. Mở **Orders > Returns**. Các thẻ theo trạng thái đổi trả: **Requested**, **Accepted**, **Disputed**, **Escalated**, **Refunded**, **Rejected**. Đổi trả bị tranh chấp và tự động chuyển lên cần bạn quyết định.
2. Chọn **Resolve**. Đọc **Reason**, **Buyer says**, **Seller says**, ảnh và **Refund amount**.
3. Để hoàn tiền, với đơn trực tuyến hãy chuyển tiền ở cổng merchant iPay88 trước và nhập **iPay88 portal reference**. Với thanh toán khi nhận hàng, khoản hoàn được ghi là điều chỉnh sổ cái đối với cửa hàng. Sau đó chọn **Refund buyer**. Việc này đóng yêu cầu đổi trả và không hoàn tác được.
4. Để từ chối, chọn **Side with seller**. Yêu cầu bị bác và đơn quay về trạng thái trước đó.
5. Hoàn tiền trước khi đơn hoàn tất cũng thu lại số xu người mua đã nhận và doanh số của người bán.

## 9. Rút tiền

1. Mở **Finance > Payouts**. Các thẻ: **Requested**, **Approved**, **Paid**, **Rejected**.
2. Đối chiếu cửa hàng và thông tin ngân hàng với tài liệu đã xác minh. Chọn **Approve**, hoặc **Reject** kèm **Rejection reason** (tiền quay lại số dư khả dụng của người bán).
3. Chọn **Export bank CSV** cho các lệnh đã duyệt và chạy chúng tại ngân hàng.
4. Khi chuyển khoản xong, chọn **Mark paid** và nhập **Bank reference**. Đây là trạng thái cuối.
5. **Finance > Affiliates** liệt kê người sáng tạo và **Pending withdrawals** với **Mark paid** (mã tham chiếu chuyển khoản) và **Reject**. Hoa hồng người sáng tạo là 2.5% giá trị sản phẩm được giới thiệu; hệ thống giới hạn ở 2.5% kể cả khi cấu hình mức cao hơn, nên đơn được giới thiệu luôn để lại hoa hồng của nền tảng.

## 10. Banner, mục trang chủ và trang nội dung

1. **Content > Banners**: chọn **Add banner**. Đặt tiêu đề theo từng ngôn ngữ, **Button text**, và **When tapped, open** (một sản phẩm, cửa hàng, danh mục, flash sale, một trang, trung tâm trợ giúp, kết quả tìm kiếm, hoặc địa chỉ web). Thêm **Image** (rộng, tỉ lệ 3:1, ít nhất 1200 pixel, tối đa 4 MB), **Vietnamese image** và **Video** tuỳ chọn, **Starts at** và **Ends at**, và **Active**. Khi sửa, ảnh hiện tại hiện phía trên **Replace image**. Chọn **Create banner** hoặc **Save banner**. Sắp xếp bằng **Move up** và **Move down**.
2. **Content > Home sections**: chọn **Section type**, rồi **Add section**. Các mục hiện trên trang chủ từ trên xuống. Sửa **Heading**, **Source** (**Latest products**, **Top sellers**), **Limit**, sản phẩm hoặc cửa hàng được chọn, các ô ảnh kèm liên kết, và **Show on** (**Web and app**, **Web only**, **App only**). Chọn **Save section**.
3. Thay đổi ban đầu là bản nháp (nhãn **Draft**). Dùng **Preview** để xem, rồi **Publish** (hoặc **Publish all**). **Throw away** bỏ bản nháp. **History** liệt kê các phiên bản cũ, với **Restore as draft** và **Restore and publish**.
4. **Scheduled layouts**: **New scheduled layout** (ví dụ Ramadan) với **Starts at** và **Ends at**. Bố cục tự bật và tự quay về bố cục thường ngày khi kết thúc.
5. **Content > Pages**: **Add page** cho điều khoản, FAQ và các trang tương tự. Đặt **Slug**, tiêu đề và nội dung theo ngôn ngữ, tiêu đề và mô tả tìm kiếm, **Share picture**, **Show a link to this page in the website footer** và **Published**. Trang hệ thống giữ slug. Trang điều khoản và bảo mật luôn được xuất bản vì trang thanh toán liên kết tới chúng.
6. **Footer & contact**: cập nhật **Email**, **Phone**, **WhatsApp**, **Address** và các liên kết mạng xã hội, rồi **Save contact**.
7. **Content > Flash sales**: nhập **Title**, **Starts**, **Ends**, chọn **Create flash sale**, rồi **Add deal** với giá **Promo RM**, **Qty** và giới hạn **Per buyer**.
8. **Support > Help articles** và **Support > Tickets**: trả lời bằng **Send reply**, **Mark urgent**, **Close ticket**, **Reopen ticket**.
9. **System > Localization**: bật hoặc tắt ngôn ngữ và đặt tiền tệ và tỉ giá (chỉ để hiển thị, tiền thu vẫn bằng MYR). Với mỗi loại tiền, nhập **Rate** và **Margin %**, rồi chọn **Save rate**.

## 11. Cài đặt xu và voucher

1. **Finance > Loyalty Coins**: hiện **Coins in circulation** và **Balance** cùng **Lifetime** của từng người mua. Chọn **Adjust**, nhập **Coins (+ grant / − clawback)** và **Reason**, rồi chọn **Apply**. Lý do được ghi lại.
2. Quy tắc tích và dùng xu (xu trên mỗi RM, giá trị quy đổi, mức tối đa mỗi đơn, số ngày hết hạn, thưởng điểm danh và giải quay) không nằm trên màn hình nào. Đội kỹ thuật đặt chúng trong cấu hình ứng dụng. Trên bản live hiện tại: 1 xu cho mỗi RM1 trọn vẹn của đơn đã hoàn tất, 1 xu = giảm 1 sen, tối đa RM50 bằng xu mỗi đơn, xu hết hạn sau 180 ngày, và điểm danh hằng ngày được 5, 5, 10, 10, 15, 15 và 30 xu theo chuỗi 7 ngày.
3. **Content > Vouchers** (voucher của sàn): chọn **Add voucher**. Nhập **Code** (lưu dạng chữ hoa), **Type** (số tiền, phần trăm, miễn phí vận chuyển), **Maximum discount (RM)**, **Minimum spend (RM)**, **Quota**, **Per-user limit**, **Starts at**, **Ends at** và **Active**. Chọn **Create voucher** hoặc **Save voucher**.
4. Người mua dùng được một voucher của sàn, một voucher của cửa hàng và một voucher miễn phí vận chuyển cho mỗi đơn. Người bán tự tạo voucher cửa hàng.
5. Các phần của **System > Settings**, mỗi phần có nút lưu riêng: **General** (**Allow buyers to add to cart and checkout**: tắt để vào chế độ chỉ đăng sản phẩm, và **Display currencies**), **Orders** (**Return window (days)**, **Auto-complete after (days)**, **Unpaid order expiry (minutes)**, **Minimum payout (RM)**), **Cash on delivery** (**COD enabled platform-wide**, **Maximum COD order (RM)**), **Mobile app**, **Moderation**, **Security**, **Tracking** và **iPay88**. Giá trị trên bản live hiện tại: thời hạn đổi trả 7 ngày, tự hoàn tất sau 7 ngày, đơn chưa thanh toán hết hạn sau 60 phút, người bán phản hồi đổi trả trong 48 giờ, rút tiền tối thiểu RM50, COD bật với mức tối đa RM500.
6. Phần **Security** có **Cloudflare Turnstile**, **Google sign-in** và quy tắc mật khẩu. Kiểm tra mật khẩu bị lộ vẫn tắt trong lúc trang đang phát triển.

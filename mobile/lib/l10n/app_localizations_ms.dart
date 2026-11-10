// ignore: unused_import
import 'package:intl/intl.dart' as intl;

import 'app_localizations.dart';

// ignore_for_file: type=lint

/// The translations for Malay (`ms`).
class AppLocalizationsMs extends AppLocalizations {
  AppLocalizationsMs([String locale = 'ms']) : super(locale);

  @override
  String get appName => 'HalalBizs';

  @override
  String get tabHome => 'Laman utama';

  @override
  String get tabSearch => 'Cari';

  @override
  String get tabCart => 'Troli';

  @override
  String get tabOrders => 'Pesanan';

  @override
  String get tabAccount => 'Akaun';

  @override
  String get sellerTabDashboard => 'Papan pemuka';

  @override
  String get sellerTabOrders => 'Pesanan';

  @override
  String get sellerTabProducts => 'Produk';

  @override
  String get sellerTabEarnings => 'Pendapatan';

  @override
  String get sellerTabStore => 'Kedai';

  @override
  String get signIn => 'Log masuk';

  @override
  String get signInTitle => 'Selamat kembali';

  @override
  String get signInSubtitle => 'Log masuk ke akaun HalalBizs anda.';

  @override
  String get email => 'Email';

  @override
  String get password => 'Kata laluan';

  @override
  String get confirmPassword => 'Sahkan kata laluan';

  @override
  String get name => 'Nama';

  @override
  String get forgotPassword => 'Lupa kata laluan?';

  @override
  String get createAccount => 'Cipta akaun';

  @override
  String get createAccountSubtitle =>
      'Beli-belah halal daripada penjual yang anda percayai.';

  @override
  String get noAccount => 'Belum mempunyai akaun?';

  @override
  String get haveAccount => 'Sudah mempunyai akaun?';

  @override
  String get register => 'Daftar';

  @override
  String get twoFactorTitle => 'Pengesahan dua faktor';

  @override
  String get twoFactorHintTotp =>
      'Masukkan kod 6 digit daripada aplikasi pengesah anda.';

  @override
  String get twoFactorHintEmail =>
      'Masukkan kod yang kami e-melkan kepada anda.';

  @override
  String get code => 'Kod';

  @override
  String get verify => 'Sahkan';

  @override
  String get cancel => 'Batal';

  @override
  String get confirm => 'Sahkan';

  @override
  String get resetPassword => 'Tetapkan semula kata laluan';

  @override
  String get resetPasswordSubtitle =>
      'Kami akan e-melkan pautan untuk memilih kata laluan baharu.';

  @override
  String get sendResetLink => 'Hantar pautan tetapan semula';

  @override
  String get resetLinkSent =>
      'Jika e-mel itu berdaftar, pautan tetapan semula sedang dihantar.';

  @override
  String get verifyEmailTitle => 'Sahkan e-mel anda';

  @override
  String verifyEmailBody(String email) {
    return 'Kami menghantar pautan pengesahan kepada $email. Buka pautan itu, kemudian kembali ke sini.';
  }

  @override
  String get resendVerification => 'Hantar semula e-mel pengesahan';

  @override
  String get verificationSent => 'E-mel pengesahan dihantar.';

  @override
  String get iHaveVerified => 'Saya telah mengesahkan';

  @override
  String get categories => 'Kategori';

  @override
  String get featured => 'Pilihan';

  @override
  String get viewAll => 'Lihat semua';

  @override
  String get searchHint => 'Cari produk';

  @override
  String get searchMinChars =>
      'Taip sekurang-kurangnya 2 aksara untuk mencari.';

  @override
  String get searchNeedsConnection => 'Carian memerlukan sambungan';

  @override
  String get searchNeedsConnectionBody =>
      'Sambung semula dan cuba lagi. Produk cache masih ada di Laman utama.';

  @override
  String searchNoResults(String query) {
    return 'Tiada hasil untuk “$query”';
  }

  @override
  String get searchNoResultsBody => 'Cuba perkataan lain atau layari kategori.';

  @override
  String get sort => 'Susun';

  @override
  String get sortRelevance => 'Kaitan';

  @override
  String get sortPopular => 'Popular';

  @override
  String get sortNewest => 'Terbaru';

  @override
  String get sortPriceAsc => 'Harga: rendah ke tinggi';

  @override
  String get sortPriceDesc => 'Harga: tinggi ke rendah';

  @override
  String get allCategories => 'Semua';

  @override
  String get sold => 'dijual';

  @override
  String get addToCart => 'Tambah ke troli';

  @override
  String get outOfStock => 'Kehabisan stok';

  @override
  String inStock(int count) {
    return '$count dalam stok';
  }

  @override
  String onlyLeft(int count) {
    return 'Hanya tinggal $count';
  }

  @override
  String get variant => 'Varian';

  @override
  String get quantity => 'Kuantiti';

  @override
  String get description => 'Penerangan';

  @override
  String get soldBy => 'Dijual oleh';

  @override
  String get visitStore => 'Lawati kedai';

  @override
  String reviewsCount(int count) {
    return '$count ulasan';
  }

  @override
  String get cartComingSoon => 'Troli akan tiba dalam bahagian seterusnya';

  @override
  String get addedToCart => 'Ditambah ke troli';

  @override
  String get signInToContinue => 'Log masuk untuk meneruskan';

  @override
  String get noProductsYet => 'Belum ada produk';

  @override
  String get noProductsBody =>
      'Kembali tidak lama lagi. Penjual sedang menambah stok.';

  @override
  String get comingNextSlice => 'Akan datang dalam bahagian seterusnya';

  @override
  String get comingNextSliceBody =>
      'Skrin ini dibina dalam keluaran seterusnya.';

  @override
  String get retry => 'Cuba lagi';

  @override
  String get backToHome => 'Kembali ke laman utama';

  @override
  String get pageNotFound => 'Halaman tidak dijumpai';

  @override
  String get pageNotFoundBody =>
      'Pautan ini tidak sepadan dengan mana-mana halaman di HalalBizs.';

  @override
  String get profile => 'Profil';

  @override
  String get addresses => 'Alamat';

  @override
  String get wishlist => 'Senarai hajat';

  @override
  String get coins => 'Syiling';

  @override
  String get notifications => 'Pemberitahuan';

  @override
  String get messages => 'Mesej';

  @override
  String get language => 'Bahasa';

  @override
  String get deleteAccount => 'Padam akaun';

  @override
  String get sellerCentre => 'Pusat penjual';

  @override
  String get becomeSeller => 'Jadi penjual';

  @override
  String get signOut => 'Log keluar';

  @override
  String get signOutConfirmTitle => 'Log keluar?';

  @override
  String get signOutConfirmBody =>
      'Anda boleh log masuk semula pada bila-bila masa.';

  @override
  String get english => 'English';

  @override
  String get malay => 'Bahasa Melayu';

  @override
  String get backToShop => 'Kembali ke kedai';

  @override
  String get guestAccountTitle => 'Log masuk untuk melihat akaun anda';

  @override
  String get guestAccountBody =>
      'Pesanan, alamat, syiling dan mesej ada di sini setelah anda log masuk.';

  @override
  String get fieldRequired => 'Ruangan ini diperlukan.';

  @override
  String get emailInvalid => 'Masukkan alamat e-mel yang sah.';

  @override
  String get passwordMin => 'Gunakan sekurang-kurangnya 8 aksara.';

  @override
  String get passwordMismatch => 'Kata laluan tidak sepadan.';

  @override
  String get codeLength => 'Masukkan kod 6 digit.';

  @override
  String get offlineStrip => 'Luar talian · memaparkan data cache';

  @override
  String get cachedStrip => 'Memaparkan data cache';

  @override
  String get cachedChip => 'Cache';

  @override
  String get needsConnection => 'Memerlukan sambungan';

  @override
  String get somethingWentWrong => 'Sesuatu telah berlaku.';

  @override
  String get store => 'Kedai';

  @override
  String get products => 'Produk';

  @override
  String get category => 'Kategori';

  @override
  String get product => 'Produk';

  @override
  String get errorOffline => 'Tiada sambungan. Memaparkan data cache jika ada.';

  @override
  String get errorTimeout =>
      'Pelayan mengambil masa terlalu lama untuk membalas. Cuba lagi.';

  @override
  String get errorBadResponse => 'Balasan pelayan tidak dijangka.';

  @override
  String get errorNoChallenge =>
      'Log masuk semula untuk mendapatkan kod baharu.';

  @override
  String get errorTokenStorage =>
      'Peranti ini enggan menyimpan token log masuk dengan selamat. Semak akses rantai kunci aplikasi dan cuba lagi.';

  @override
  String get sessionEnded => 'Sesi anda telah tamat. Sila log masuk semula.';

  @override
  String get back => 'Kembali';

  @override
  String get justNow => 'baru sahaja';

  @override
  String minutesOld(int count) {
    return '$count min lalu';
  }

  @override
  String hoursOld(int count) {
    return '$count jam lalu';
  }

  @override
  String get showPassword => 'Tunjukkan kata laluan';

  @override
  String get hidePassword => 'Sembunyikan kata laluan';

  @override
  String get any => 'Mana-mana';

  @override
  String get clear => 'Kosongkan';

  @override
  String get newAddress => 'Alamat baharu';

  @override
  String get address => 'Alamat';

  @override
  String get conversation => 'Perbualan';

  @override
  String get checkout => 'Daftar keluar';

  @override
  String get payment => 'Pembayaran';

  @override
  String get orderPlaced => 'Pesanan dibuat';

  @override
  String get orderDetails => 'Butiran pesanan';

  @override
  String get orderReturn => 'Pemulangan';

  @override
  String get orderReview => 'Ulasan';

  @override
  String get cartCheckout => 'Daftar keluar';

  @override
  String get cartContinueShopping => 'Teruskan membeli-belah';

  @override
  String get cartEmptyTitle => 'Troli anda kosong';

  @override
  String get cartEmptyBody =>
      'Cari sesuatu yang halal dan baik — ia akan menunggu anda di sini.';

  @override
  String cartItemCount(int count) {
    return '$count item';
  }

  @override
  String get cartPurchasingPaused =>
      'Pembelian dijeda — anda boleh melayari, tetapi tidak boleh daftar keluar buat masa ini.';

  @override
  String get cartRemove => 'Buang';

  @override
  String get cartSelectAll => 'Pilih semua';

  @override
  String get cartSelectToCheckout =>
      'Pilih sekurang-kurangnya satu item untuk daftar keluar.';

  @override
  String cartSelectedSubtotal(int count) {
    return 'Subjumlah · $count dipilih';
  }

  @override
  String get cartUnavailable => 'Tidak tersedia';

  @override
  String get cartViewCart => 'Lihat troli';

  @override
  String get checkoutTitle => 'Daftar keluar';

  @override
  String get checkoutDeliverTo => 'Hantar ke';

  @override
  String get checkoutChange => 'Tukar';

  @override
  String get checkoutNoAddress =>
      'Anda belum mempunyai alamat penghantaran — tambah alamat untuk membuat pesanan.';

  @override
  String get checkoutChooseMethod => 'Pilih kaedah pembayaran yang tersedia.';

  @override
  String get checkoutSellerNote => 'Nota kepada penjual';

  @override
  String get checkoutSellerNoteHint =>
      'Pilihan — cth. tinggalkan di pondok pengawal';

  @override
  String get checkoutVouchers => 'Baucar';

  @override
  String get checkoutPlatformVoucher => 'Baucar HalalBizs';

  @override
  String get checkoutShopVoucher => 'Baucar kedai';

  @override
  String get checkoutVoucherHint => 'Kod baucar';

  @override
  String get checkoutVoucherApply => 'Mohon';

  @override
  String get checkoutVoucherRemove => 'Buang';

  @override
  String checkoutVoucherApplied(String code, String amount) {
    return '$code digunakan · diskaun $amount';
  }

  @override
  String checkoutUseCoins(int balance) {
    return 'Guna syiling (anda ada $balance)';
  }

  @override
  String get checkoutCoinsToUse => 'Syiling untuk digunakan';

  @override
  String checkoutCoinsWorth(int coins, String amount) {
    return '$coins syiling · diskaun $amount';
  }

  @override
  String get checkoutPaymentMethod => 'Kaedah pembayaran';

  @override
  String get checkoutMethodCod => 'Bayaran tunai semasa penghantaran';

  @override
  String get checkoutMethodIpay88 =>
      'Pembayaran dalam talian (FPX, kad, e-dompet)';

  @override
  String get checkoutMethodUnavailable => 'Tidak tersedia untuk pesanan ini.';

  @override
  String get checkoutSummary => 'Ringkasan';

  @override
  String get checkoutSubtotal => 'Subjumlah';

  @override
  String get checkoutShipping => 'Penghantaran';

  @override
  String get checkoutShippingPending => 'Pilih alamat';

  @override
  String get checkoutShippingDiscount => 'Diskaun penghantaran';

  @override
  String get checkoutPlatformDiscount => 'Baucar HalalBizs';

  @override
  String get checkoutShopDiscount => 'Diskaun kedai';

  @override
  String get checkoutTax => 'Cukai';

  @override
  String get checkoutGrandTotal => 'Jumlah keseluruhan';

  @override
  String get checkoutPlaceOrder => 'Buat pesanan';

  @override
  String get checkoutDoneTitle => 'Pesanan dibuat';

  @override
  String checkoutDoneBody(String orderNo) {
    return 'Pesanan $orderNo telah disahkan. Pesanan anda ada di sini:';
  }

  @override
  String get checkoutViewOrders => 'Lihat pesanan';

  @override
  String get checkoutPayTitle => 'Pembayaran';

  @override
  String get checkoutPayBrowserTitle =>
      'Selesaikan pembayaran dalam pelayar anda';

  @override
  String checkoutPayBrowserBody(String orderNo) {
    return 'Pesanan $orderNo dibuka dalam pelayar anda. Kembali ke sini setelah selesai — kami menyemak pembayaran secara automatik.';
  }

  @override
  String checkoutPayExpiresAt(String time) {
    return 'Bayar sebelum $time';
  }

  @override
  String get checkoutPayIHavePaid => 'Saya telah bayar';

  @override
  String get checkoutPayOpenAgain => 'Buka halaman pembayaran semula';

  @override
  String get checkoutPayRetry => 'Cuba bayar semula';

  @override
  String get checkoutPayExpiredTitle => 'Tempoh pembayaran tamat';

  @override
  String get checkoutPayExpiredBody =>
      'Pesanan ini tidak dibayar dalam masa yang ditetapkan. Tiada caj dikenakan.';

  @override
  String get ordersAll => 'Semua';

  @override
  String get ordersOrder => 'Pesanan';

  @override
  String get ordersTotal => 'Jumlah';

  @override
  String ordersMoreItems(int count) {
    return '+$count lagi';
  }

  @override
  String ordersPlacedAt(String date) {
    return 'Dibuat pada $date';
  }

  @override
  String get ordersEmptyTitle => 'Belum ada pesanan';

  @override
  String get ordersEmptyBody =>
      'Pesanan anda akan dipaparkan di sini selepas daftar keluar.';

  @override
  String get ordersLoadMore => 'Muat lagi';

  @override
  String get ordersTracking => 'Penjejakan';

  @override
  String get ordersTrack => 'Jejak';

  @override
  String get ordersReturnRequest => 'Permohonan pemulangan';

  @override
  String get ordersCancelOrder => 'Batalkan pesanan';

  @override
  String get ordersCancelTitle => 'Batalkan pesanan ini?';

  @override
  String get ordersCancelBody =>
      'Tiada caj dikenakan dan item akan dikembalikan ke stok.';

  @override
  String get ordersCancelConfirm => 'Batalkan pesanan';

  @override
  String get ordersKeep => 'Kekalkan pesanan';

  @override
  String get ordersCancelled => 'Pesanan dibatalkan';

  @override
  String get ordersReason => 'Sebab';

  @override
  String get ordersReasonHint => 'Pilih sebab';

  @override
  String get ordersConfirmReceived => 'Sahkan penerimaan';

  @override
  String get ordersReceivedTitle => 'Sudah terima pesanan anda?';

  @override
  String get ordersReceivedBody => 'Sahkan hanya setelah semuanya tiba.';

  @override
  String get ordersReceivedDone => 'Pesanan diterima';

  @override
  String get ordersRequestReturn => 'Mohon pemulangan';

  @override
  String get ordersNote => 'Nota';

  @override
  String get ordersReturnNoteHint => 'Apa yang tidak kena?';

  @override
  String get ordersPhotos => 'Foto';

  @override
  String ordersPhotosHelp(int max) {
    return 'Sehingga $max foto.';
  }

  @override
  String get ordersReturnConfirmTitle => 'Hantar permohonan pemulangan ini?';

  @override
  String get ordersReturnConfirmBody => 'Penjual akan dimaklumkan.';

  @override
  String get ordersReturnSubmit => 'Hantar permohonan pemulangan';

  @override
  String get ordersReturnSubmitted => 'Pemulangan dimohon';

  @override
  String get ordersWriteReview => 'Tulis ulasan';

  @override
  String get ordersReviewBody => 'Ulasan anda';

  @override
  String get ordersReviewBodyHint => 'Pilihan — sekurang-kurangnya 10 aksara';

  @override
  String get ordersSellerRating => 'Penilaian penjual';

  @override
  String get ordersSellerComment => 'Komen';

  @override
  String get ordersOptional => 'Pilihan';

  @override
  String get ordersRateEveryItem => 'Nilai setiap item sebelum menghantar.';

  @override
  String get ordersReviewSubmit => 'Hantar ulasan';

  @override
  String get ordersReviewSubmitted => 'Terima kasih atas ulasan anda.';

  @override
  String get ordersNothingToReview => 'Tiada lagi yang perlu diulas';

  @override
  String get accountSave => 'Simpan';

  @override
  String get accountDelete => 'Padam';

  @override
  String get accountDefault => 'Lalai';

  @override
  String get accountPhone => 'Telefon';

  @override
  String get accountPreferredLanguage => 'Bahasa pilihan';

  @override
  String get accountEmailUnverified => 'E-mel belum disahkan.';

  @override
  String get accountProfileSaved => 'Profil dikemas kini';

  @override
  String get accountChangePassword => 'Tukar kata laluan';

  @override
  String get accountCurrentPassword => 'Kata laluan semasa';

  @override
  String get accountNewPassword => 'Kata laluan baharu';

  @override
  String get accountPasswordChanged => 'Kata laluan dikemas kini';

  @override
  String get accountAddAddress => 'Tambah alamat';

  @override
  String get accountEditAddress => 'Sunting alamat';

  @override
  String get accountNoAddresses => 'Belum ada alamat';

  @override
  String get accountNoAddressesBody =>
      'Tambah alamat penghantaran untuk daftar keluar lebih pantas.';

  @override
  String get accountAddressLabel => 'Label';

  @override
  String get accountAddressLabelHint => 'Rumah, Pejabat…';

  @override
  String get accountRecipientName => 'Nama penerima';

  @override
  String get accountLine1 => 'Baris alamat 1';

  @override
  String get accountLine2 => 'Baris alamat 2';

  @override
  String get accountPostcode => 'Poskod';

  @override
  String get accountCity => 'Bandar';

  @override
  String get accountState => 'Negeri';

  @override
  String get accountStateHint => 'Pilih negeri';

  @override
  String get accountSetDefault => 'Tetapkan sebagai lalai';

  @override
  String get accountAddressSaved => 'Alamat disimpan';

  @override
  String get accountDeleteAddressTitle => 'Padam alamat ini?';

  @override
  String get accountDeleteAddressBody =>
      'Anda boleh menambahnya semula bila-bila masa.';

  @override
  String get accountWishlistEmpty => 'Belum ada apa-apa disimpan';

  @override
  String get accountWishlistEmptyBody =>
      'Ketik hati pada produk untuk menyimpannya di sini.';

  @override
  String get accountRemoveFromWishlist => 'Buang daripada senarai hajat';

  @override
  String get productAddToWishlist => 'Tambah ke senarai hajat';

  @override
  String get accountCoinsBalance => 'Baki';

  @override
  String get accountCoinsStreak => 'Rentetan';

  @override
  String accountCoinsDays(int days) {
    return '$days hari';
  }

  @override
  String accountCoinsLifetime(int coins) {
    return '$coins syiling diperoleh keseluruhannya';
  }

  @override
  String accountCoinsExpiring(String date) {
    return 'Sebahagian syiling luput $date';
  }

  @override
  String get accountCheckIn => 'Daftar masuk hari ini';

  @override
  String get accountCheckedIn => 'Sudah daftar masuk hari ini';

  @override
  String get accountCheckedInReason => 'Anda sudah daftar masuk hari ini.';

  @override
  String get accountCheckInBody =>
      'Daftar masuk setiap hari untuk menambah rentetan dan memperoleh lebih banyak syiling.';

  @override
  String accountCheckInDone(int coins, int streak) {
    return '+$coins syiling · rentetan hari ke-$streak';
  }

  @override
  String get accountCoinsHistory => 'Sejarah';

  @override
  String get accountCoinsNoHistory => 'Belum ada aktiviti syiling';

  @override
  String get accountCoinsUnavailable => 'Syiling tidak tersedia';

  @override
  String get accountCoinsUnavailableBody =>
      'Daftar masuk harian tidak tersedia buat masa ini.';

  @override
  String get accountMarkAllRead => 'Tandakan semua sebagai dibaca';

  @override
  String get accountNoNotifications => 'Tiada pemberitahuan';

  @override
  String get accountNoNotificationsBody =>
      'Kemas kini pesanan dan mesej daripada penjual dipaparkan di sini.';

  @override
  String get accountDeleteWarning => 'Tindakan ini tidak boleh dibatalkan.';

  @override
  String get accountDeleteBody =>
      'Butiran peribadi anda dianonimkan serta-merta; rekod pesanan disimpan atas sebab undang-undang dan kewangan. Masukkan kata laluan anda untuk meneruskan.';

  @override
  String get accountDeleteConfirmTitle => 'Padam akaun anda?';

  @override
  String get accountDeleteConfirmBody =>
      'Anda akan dilog keluar dan tidak boleh log masuk semula dengan akaun ini.';

  @override
  String get messagesChatWithSeller => 'Sembang dengan penjual';

  @override
  String get messagesEmpty => 'Belum ada mesej';

  @override
  String get messagesEmptyBody =>
      'Mulakan sembang dari mana-mana halaman produk.';

  @override
  String get messagesNoMessagesYet => 'Belum ada mesej dalam perbualan ini.';

  @override
  String get messagesSayHello => 'Sapa penjual.';

  @override
  String get messagesTypeMessage => 'Taip mesej';

  @override
  String get messagesSend => 'Hantar';

  @override
  String get checkoutPlacedCheckOrders =>
      'Pesanan anda telah dibuat. Semak pesanan anda.';

  @override
  String get checkoutMaybePlaced =>
      'Kami tidak dapat mengesahkan sama ada pesanan itu berjaya. Semak pesanan anda sebelum mencuba lagi.';

  @override
  String get checkoutPayLinkMissing =>
      'Pesanan dibuat tetapi tiada pautan pembayaran diterima. Bayar dari senarai pesanan anda.';

  @override
  String get accountWishlistStillSaved =>
      'Masih dalam senarai hajat — cuba lagi.';

  @override
  String get accountUnread => 'Belum dibaca';

  @override
  String ordersReviewStars(int n) {
    String _temp0 = intl.Intl.pluralLogic(
      n,
      locale: localeName,
      other: '$n bintang',
    );
    return '$_temp0';
  }

  @override
  String get ordersPaymentStatusPending => 'Menunggu';

  @override
  String get ordersPaymentStatusPaid => 'Paid';

  @override
  String get ordersPaymentStatusFailed => 'Failed';

  @override
  String get ordersPaymentStatusExpired => 'Expired';

  @override
  String get ordersPaymentStatusRefunded => 'Refunded';

  @override
  String get ordersReturnStatusRequested => 'Requested';

  @override
  String get ordersReturnStatusAccepted => 'Accepted';

  @override
  String get ordersReturnStatusDisputed => 'Disputed';

  @override
  String get ordersReturnStatusEscalated => 'Escalated';

  @override
  String get ordersReturnStatusRefunded => 'Refunded';

  @override
  String get ordersReturnStatusRejected => 'Rejected';
}

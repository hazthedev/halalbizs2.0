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
  String get checkout => 'Bayar';

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
  String get sellerBackToBuying => 'Kembali membeli';

  @override
  String get sellerRequired => 'Akaun anda bukan penjual yang diluluskan.';

  @override
  String get sellerTodaySales => 'Jualan hari ini';

  @override
  String get sellerTodayOrders => 'Pesanan hari ini';

  @override
  String get sellerToShip => 'Untuk dihantar';

  @override
  String get sellerLowStock => 'Stok rendah';

  @override
  String get sellerAvailable => 'Tersedia';

  @override
  String get sellerHeld => 'Ditahan';

  @override
  String get sellerPaidOut => 'Telah dibayar';

  @override
  String get sellerOrdersByStatus => 'Pesanan mengikut status';

  @override
  String get sellerRecentOrders => 'Pesanan terkini';

  @override
  String get sellerNoOrders => 'Tiada pesanan lagi';

  @override
  String get sellerNoOrdersBody =>
      'Pesanan akan muncul di sini apabila pembeli membuat pembayaran.';

  @override
  String get sellerAllStatuses => 'Semua';

  @override
  String get sellerStatusPendingPayment => 'Menunggu bayaran';

  @override
  String get sellerStatusConfirmed => 'Disahkan';

  @override
  String get sellerStatusProcessing => 'Diproses';

  @override
  String get sellerStatusShipped => 'Dihantar';

  @override
  String get sellerStatusDelivered => 'Diterima';

  @override
  String get sellerStatusCompleted => 'Selesai';

  @override
  String get sellerStatusCancelled => 'Dibatalkan';

  @override
  String get sellerStatusReturnRequested => 'Pemulangan dimohon';

  @override
  String get sellerStatusReturned => 'Dipulangkan';

  @override
  String get sellerStatusRefunded => 'Dibayar balik';

  @override
  String sellerItemsCount(int count) {
    String _temp0 = intl.Intl.pluralLogic(
      count,
      locale: localeName,
      other: '$count item',
      one: '1 item',
    );
    return '$_temp0';
  }

  @override
  String get sellerOrder => 'Pesanan';

  @override
  String get sellerBuyer => 'Pembeli';

  @override
  String get sellerShipTo => 'Hantar kepada';

  @override
  String get sellerItems => 'Item';

  @override
  String get sellerSubtotal => 'Subjumlah';

  @override
  String get sellerShippingFee => 'Penghantaran';

  @override
  String get sellerDiscount => 'Diskaun';

  @override
  String get sellerTax => 'Cukai';

  @override
  String get sellerTotal => 'Jumlah';

  @override
  String get sellerCommission => 'Komisen';

  @override
  String get sellerCommissionEstimated => 'Anggaran sehingga pesanan selesai.';

  @override
  String get sellerPayment => 'Pembayaran';

  @override
  String get sellerPaymentCod => 'Bayaran tunai semasa penghantaran';

  @override
  String get sellerPaymentOnline => 'Bayaran dalam talian';

  @override
  String get sellerTracking => 'Penjejakan';

  @override
  String get sellerTrack => 'Jejak bungkusan';

  @override
  String get sellerHistory => 'Sejarah';

  @override
  String get sellerReturnRequest => 'Permohonan pemulangan';

  @override
  String sellerRespondBy(String date) {
    return 'Balas sebelum $date';
  }

  @override
  String get sellerPack => 'Bungkus';

  @override
  String get sellerShip => 'Hantar';

  @override
  String get sellerMarkDelivered => 'Tanda sudah diterima';

  @override
  String get sellerAcceptReturn => 'Terima pemulangan';

  @override
  String get sellerDisputeReturn => 'Pertikai';

  @override
  String get sellerConfirmReceived => 'Sahkan barang diterima';

  @override
  String get sellerShipTitle => 'Hantar pesanan ini';

  @override
  String get sellerCourier => 'Kurier';

  @override
  String get sellerCourierOther => 'Lain-lain';

  @override
  String get sellerCourierName => 'Nama kurier';

  @override
  String get sellerTrackingNo => 'Nombor penjejakan';

  @override
  String get sellerTrackingNoLength => 'Masukkan 6 hingga 64 aksara.';

  @override
  String get sellerDisputeTitle => 'Pertikai pemulangan ini';

  @override
  String get sellerDisputeReason => 'Sebab';

  @override
  String get sellerDisputeReasonMin => 'Tulis sekurang-kurangnya 10 aksara.';

  @override
  String get sellerSubmit => 'Hantar';

  @override
  String get sellerOrderUpdated => 'Pesanan dikemas kini';

  @override
  String get sellerSearchProducts => 'Cari produk atau SKU';

  @override
  String get sellerNoProducts => 'Tiada produk lagi';

  @override
  String get sellerNoProductsBody =>
      'Tambah produk pertama anda untuk mula menjual.';

  @override
  String get sellerNewProduct => 'Produk baharu';

  @override
  String get sellerEditProduct => 'Sunting produk';

  @override
  String get sellerStatusDraft => 'Draf';

  @override
  String get sellerStatusPendingReview => 'Menunggu semakan';

  @override
  String get sellerStatusLive => 'Langsung';

  @override
  String get sellerStatusDelisted => 'Dinyahsenarai';

  @override
  String get sellerStatusBanned => 'Diharamkan';

  @override
  String get sellerStock => 'Stok';

  @override
  String sellerStockCount(int count) {
    return 'Stok $count';
  }

  @override
  String get sellerCod => 'COD';

  @override
  String get sellerNameEn => 'Nama (Inggeris)';

  @override
  String get sellerNameMs => 'Nama (Melayu)';

  @override
  String get sellerNameVi => 'Nama (Vietnam)';

  @override
  String get sellerDescriptionEn => 'Penerangan (Inggeris)';

  @override
  String get sellerDescriptionMs => 'Penerangan (Melayu)';

  @override
  String get sellerDescriptionVi => 'Penerangan (Vietnam)';

  @override
  String get sellerCategory => 'Kategori';

  @override
  String get sellerChooseCategory => 'Pilih kategori';

  @override
  String get sellerPriceRm => 'Harga (RM)';

  @override
  String get sellerSalePriceRm => 'Harga jualan (RM)';

  @override
  String get sellerPriceFormat => 'Masukkan jumlah seperti 12.50.';

  @override
  String get sellerSku => 'SKU';

  @override
  String get sellerWeightG => 'Berat (g)';

  @override
  String get sellerLengthMm => 'Panjang (mm)';

  @override
  String get sellerWidthMm => 'Lebar (mm)';

  @override
  String get sellerHeightMm => 'Tinggi (mm)';

  @override
  String get sellerCodEnabled => 'Bayaran tunai semasa penghantaran';

  @override
  String get sellerCodEnabledHelp =>
      'Benarkan pembeli membayar semasa penghantaran untuk produk ini.';

  @override
  String get sellerVariants => 'Varian';

  @override
  String get sellerImages => 'Imej';

  @override
  String get sellerImagesHelp =>
      'Sehingga 9 foto. Pilih beberapa sekali gus. Ia dipaparkan mengikut urutan anda menambahnya.';

  @override
  String get sellerImagesSaveFirst =>
      'Simpan produk dahulu untuk menambah imej.';

  @override
  String get sellerImagesFull =>
      '9 foto ialah had. Buang satu untuk menambah yang lain.';

  @override
  String sellerImagesTooMany(int count) {
    return 'Hanya $count lagi foto boleh ditambah. Satu produk maksimum 9.';
  }

  @override
  String get sellerDescriptionHasFormatting =>
      'Penerangan ini mempunyai format. Suntingnya di pusat penjual web.';

  @override
  String get sellerAddImage => 'Tambah imej';

  @override
  String get sellerRemoveImage => 'Buang imej';

  @override
  String get sellerRemoveImageBody =>
      'Imej ini akan dibuang daripada penyenaraian.';

  @override
  String get sellerRemove => 'Buang';

  @override
  String get sellerGallery => 'Pilih dari galeri';

  @override
  String get sellerCamera => 'Ambil gambar';

  @override
  String get sellerSave => 'Simpan';

  @override
  String get sellerSaved => 'Disimpan';

  @override
  String get sellerPublish => 'Terbitkan';

  @override
  String get sellerDelist => 'Nyahsenarai';

  @override
  String get sellerRelist => 'Senarai semula';

  @override
  String get sellerHalalBlocked => 'Penerbitan disekat';

  @override
  String get sellerNumberInvalid => 'Masukkan nombor bulat.';

  @override
  String get sellerRequestPayout => 'Mohon pembayaran';

  @override
  String get sellerPayoutPending => 'Pembayaran menunggu';

  @override
  String sellerPayoutMin(String amount) {
    return 'Pembayaran minimum $amount';
  }

  @override
  String sellerPayoutMax(String amount) {
    return 'Sehingga $amount tersedia';
  }

  @override
  String get sellerPayoutAmountRm => 'Jumlah (RM)';

  @override
  String get sellerPayoutRequested => 'Pembayaran dimohon';

  @override
  String get sellerPayoutBelowMin => 'Kurang daripada pembayaran minimum.';

  @override
  String get sellerPayoutAboveAvailable => 'Melebihi baki tersedia.';

  @override
  String get sellerLedger => 'Lejar';

  @override
  String get sellerNoLedger => 'Tiada aktiviti lagi';

  @override
  String get sellerNoLedgerBody =>
      'Jualan dan yuran akan muncul di sini setelah pesanan selesai.';

  @override
  String get sellerPayouts => 'Pembayaran';

  @override
  String get sellerNoPayouts => 'Tiada pembayaran lagi';

  @override
  String get sellerLoadMore => 'Muatkan lagi';

  @override
  String get sellerStoreDescription => 'Penerangan';

  @override
  String get sellerState => 'Negeri';

  @override
  String get sellerHolidayMode => 'Mod cuti';

  @override
  String get sellerHolidayModeHelp =>
      'Sembunyikan produk anda semasa anda bercuti.';

  @override
  String get sellerShipping => 'Penghantaran';

  @override
  String get sellerShippingMatrix =>
      'Kadar mengikut negeri diurus di pusat penjual web.';

  @override
  String get sellerFlatFeeRm => 'Yuran tetap (RM)';

  @override
  String get sellerFreeAboveRm => 'Penghantaran percuma melebihi (RM)';

  @override
  String get sellerBank => 'Butiran bank';

  @override
  String get sellerBankName => 'Bank';

  @override
  String get sellerAccountName => 'Nama akaun';

  @override
  String get sellerAccountNumber => 'Nombor akaun';

  @override
  String sellerAccountNumberHelp(String masked) {
    return 'Masukkan nombor penuh untuk menggantikan $masked.';
  }

  @override
  String get sellerBankHelp =>
      'Masukkan nombor akaun penuh untuk menukar butiran bank.';

  @override
  String get sellerChangeLogo => 'Tukar logo';

  @override
  String get sellerChangeBanner => 'Tukar sepanduk';

  @override
  String get sellerReviews => 'Ulasan';

  @override
  String get sellerNoReviews => 'Tiada ulasan lagi';

  @override
  String get sellerNoReviewsBody =>
      'Ulasan pembeli tentang produk anda akan muncul di sini.';

  @override
  String get sellerReplyHint => 'Tulis balasan kepada pembeli ini';

  @override
  String get sellerPostReply => 'Hantar balasan';

  @override
  String get sellerYourReply => 'Balasan anda';

  @override
  String get sellerReplyMin => 'Tulis sekurang-kurangnya 5 aksara.';

  @override
  String get sellerStoreSaved => 'Kedai disimpan';

  @override
  String get sellerSaveStoreSettings => 'Simpan tetapan kedai';

  @override
  String get sellerBankNeedsNumber =>
      'Masukkan semula nombor akaun penuh untuk menukar butiran bank.';

  @override
  String get sellerStatusPending => 'Menunggu';

  @override
  String get sellerStatusPaid => 'Dibayar';

  @override
  String get sellerStatusFailed => 'Gagal';

  @override
  String get sellerStatusExpired => 'Tamat tempoh';

  @override
  String get sellerDefaultVariant => 'Lalai';

  @override
  String sellerImageTooLarge(int kb) {
    return 'Pilih imej di bawah $kb KB.';
  }

  @override
  String get sellerAcceptReturnConfirm =>
      'Terima pemulangan ini? Pembeli akan diminta menghantar barang kembali.';

  @override
  String get sellerDisputeConfirm =>
      'Pertikai pemulangan ini? HalalBizs akan menyemak kes ini dengan sebab anda.';

  @override
  String get sellerDelistConfirm =>
      'Nyahsenarai produk ini? Pembeli tidak akan melihatnya sehingga anda menyenaraikannya semula.';

  @override
  String sellerPayoutConfirm(String amount) {
    return 'Mohon pembayaran sebanyak $amount ke akaun bank anda?';
  }

  @override
  String get cartCheckout => 'Bayar';

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
      'Pembelian dijeda — anda boleh melayari, tetapi tidak boleh membayar buat masa ini.';

  @override
  String get cartRemove => 'Buang';

  @override
  String get cartSelectAll => 'Pilih semua';

  @override
  String get cartSelectToCheckout =>
      'Pilih sekurang-kurangnya satu item untuk dibayar.';

  @override
  String cartSelectedSubtotal(int count) {
    return 'Subjumlah · $count dipilih';
  }

  @override
  String get cartUnavailable => 'Tidak tersedia';

  @override
  String get cartViewCart => 'Lihat troli';

  @override
  String get checkoutTitle => 'Bayar';

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
      'Pesanan anda akan dipaparkan di sini selepas anda membayar.';

  @override
  String get ordersEmptyBucketTitle => 'Tiada pesanan dalam status ini';

  @override
  String get ordersEmptyBucketBody =>
      'Pilih tab lain untuk melihat pesanan anda yang lain.';

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
      'Tambah alamat penghantaran untuk pembayaran lebih pantas.';

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

  @override
  String checkoutDonePaid(String amount, String orderNo) {
    return 'Dibayar $amount (bayaran $orderNo).';
  }

  @override
  String checkoutDoneCod(String amount, String orderNo) {
    return 'Jumlah $amount, dibayar semasa penghantaran (bayaran $orderNo).';
  }

  @override
  String checkoutDoneSplit(int count) {
    String _temp0 = intl.Intl.pluralLogic(
      count,
      locale: localeName,
      other:
          'Pesanan anda dibahagikan kepada $count penghantaran, satu bagi setiap kedai.',
      one: 'Ia dihantar sebagai satu penghantaran.',
    );
    return '$_temp0';
  }

  @override
  String ordersOrderNo(String no) {
    return 'Pesanan $no';
  }

  @override
  String ordersPaymentNo(String no) {
    return 'Bayaran $no';
  }

  @override
  String productSelect(String options) {
    return 'Pilih: $options';
  }

  @override
  String productChoose(String option) {
    return 'Pilih $option';
  }

  @override
  String productStock(int count) {
    return 'Stok: $count';
  }

  @override
  String get productViewImage => 'Lihat gambar';

  @override
  String get close => 'Tutup';
}

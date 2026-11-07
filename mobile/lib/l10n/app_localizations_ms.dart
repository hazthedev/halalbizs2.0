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
  String get sellerLiveProducts => 'Produk langsung';

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
  String get sellerImagesHelp => 'Sehingga 9 imej.';

  @override
  String get sellerImagesSaveFirst =>
      'Simpan produk dahulu untuk menambah imej.';

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
  String get sellerPayoutOpen => 'Satu permohonan pembayaran masih terbuka.';

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
  String get sellerStoreName => 'Nama kedai';

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
}

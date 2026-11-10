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
}

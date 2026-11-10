// ignore: unused_import
import 'package:intl/intl.dart' as intl;

import 'app_localizations.dart';

// ignore_for_file: type=lint

/// The translations for English (`en`).
class AppLocalizationsEn extends AppLocalizations {
  AppLocalizationsEn([String locale = 'en']) : super(locale);

  @override
  String get appName => 'HalalBizs';

  @override
  String get tabHome => 'Home';

  @override
  String get tabSearch => 'Search';

  @override
  String get tabCart => 'Cart';

  @override
  String get tabOrders => 'Orders';

  @override
  String get tabAccount => 'Account';

  @override
  String get sellerTabDashboard => 'Dashboard';

  @override
  String get sellerTabOrders => 'Orders';

  @override
  String get sellerTabProducts => 'Products';

  @override
  String get sellerTabEarnings => 'Earnings';

  @override
  String get sellerTabStore => 'Store';

  @override
  String get signIn => 'Sign in';

  @override
  String get signInTitle => 'Welcome back';

  @override
  String get signInSubtitle => 'Sign in to your HalalBizs account.';

  @override
  String get email => 'Email';

  @override
  String get password => 'Password';

  @override
  String get confirmPassword => 'Confirm password';

  @override
  String get name => 'Name';

  @override
  String get forgotPassword => 'Forgot password?';

  @override
  String get createAccount => 'Create account';

  @override
  String get createAccountSubtitle => 'Shop halal, from sellers you can trust.';

  @override
  String get noAccount => 'Don\'t have an account?';

  @override
  String get haveAccount => 'Already have an account?';

  @override
  String get register => 'Register';

  @override
  String get twoFactorTitle => 'Two-factor authentication';

  @override
  String get twoFactorHintTotp =>
      'Enter the 6-digit code from your authenticator app.';

  @override
  String get twoFactorHintEmail => 'Enter the code we emailed you.';

  @override
  String get code => 'Code';

  @override
  String get verify => 'Verify';

  @override
  String get cancel => 'Cancel';

  @override
  String get confirm => 'Confirm';

  @override
  String get resetPassword => 'Reset password';

  @override
  String get resetPasswordSubtitle =>
      'We will email you a link to choose a new password.';

  @override
  String get sendResetLink => 'Send reset link';

  @override
  String get resetLinkSent =>
      'If that email is registered, a reset link is on its way.';

  @override
  String get verifyEmailTitle => 'Verify your email';

  @override
  String verifyEmailBody(String email) {
    return 'We sent a verification link to $email. Open it, then come back here.';
  }

  @override
  String get resendVerification => 'Resend verification email';

  @override
  String get verificationSent => 'Verification email sent.';

  @override
  String get iHaveVerified => 'I have verified';

  @override
  String get categories => 'Categories';

  @override
  String get featured => 'Featured';

  @override
  String get viewAll => 'View all';

  @override
  String get searchHint => 'Search products';

  @override
  String get searchMinChars => 'Type at least 2 characters to search.';

  @override
  String get searchNeedsConnection => 'Search needs a connection';

  @override
  String get searchNeedsConnectionBody =>
      'Reconnect and try again. Cached products are still in Home.';

  @override
  String searchNoResults(String query) {
    return 'No results for “$query”';
  }

  @override
  String get searchNoResultsBody =>
      'Try a different word or browse the categories.';

  @override
  String get sort => 'Sort';

  @override
  String get sortRelevance => 'Relevance';

  @override
  String get sortPopular => 'Popular';

  @override
  String get sortNewest => 'Newest';

  @override
  String get sortPriceAsc => 'Price: low to high';

  @override
  String get sortPriceDesc => 'Price: high to low';

  @override
  String get allCategories => 'All';

  @override
  String get sold => 'sold';

  @override
  String get addToCart => 'Add to cart';

  @override
  String get outOfStock => 'Out of stock';

  @override
  String inStock(int count) {
    return '$count in stock';
  }

  @override
  String onlyLeft(int count) {
    return 'Only $count left';
  }

  @override
  String get variant => 'Variant';

  @override
  String get quantity => 'Quantity';

  @override
  String get description => 'Description';

  @override
  String get soldBy => 'Sold by';

  @override
  String get visitStore => 'Visit store';

  @override
  String reviewsCount(int count) {
    return '$count reviews';
  }

  @override
  String get cartComingSoon => 'Cart arrives in the next slice';

  @override
  String get addedToCart => 'Added to cart';

  @override
  String get signInToContinue => 'Sign in to continue';

  @override
  String get noProductsYet => 'No products yet';

  @override
  String get noProductsBody => 'Check back soon. Sellers are adding stock.';

  @override
  String get comingNextSlice => 'Coming in the next slice';

  @override
  String get comingNextSliceBody => 'This screen is built in the next release.';

  @override
  String get retry => 'Retry';

  @override
  String get backToHome => 'Back to home';

  @override
  String get pageNotFound => 'Page not found';

  @override
  String get pageNotFoundBody =>
      'This link doesn\'t match a page in HalalBizs.';

  @override
  String get profile => 'Profile';

  @override
  String get addresses => 'Addresses';

  @override
  String get wishlist => 'Wishlist';

  @override
  String get coins => 'Coins';

  @override
  String get notifications => 'Notifications';

  @override
  String get messages => 'Messages';

  @override
  String get language => 'Language';

  @override
  String get deleteAccount => 'Delete account';

  @override
  String get sellerCentre => 'Seller centre';

  @override
  String get becomeSeller => 'Become a seller';

  @override
  String get signOut => 'Sign out';

  @override
  String get signOutConfirmTitle => 'Sign out?';

  @override
  String get signOutConfirmBody => 'You can sign in again any time.';

  @override
  String get english => 'English';

  @override
  String get malay => 'Bahasa Melayu';

  @override
  String get backToShop => 'Back to shop';

  @override
  String get guestAccountTitle => 'Sign in to see your account';

  @override
  String get guestAccountBody =>
      'Orders, addresses, coins and messages live here once you are signed in.';

  @override
  String get fieldRequired => 'This field is required.';

  @override
  String get emailInvalid => 'Enter a valid email address.';

  @override
  String get passwordMin => 'Use at least 8 characters.';

  @override
  String get passwordMismatch => 'Passwords do not match.';

  @override
  String get codeLength => 'Enter the 6-digit code.';

  @override
  String get offlineStrip => 'Offline · showing cached data';

  @override
  String get cachedStrip => 'Showing cached data';

  @override
  String get cachedChip => 'Cached';

  @override
  String get needsConnection => 'Needs a connection';

  @override
  String get somethingWentWrong => 'Something went wrong.';

  @override
  String get store => 'Store';

  @override
  String get products => 'Products';

  @override
  String get category => 'Category';

  @override
  String get product => 'Product';

  @override
  String get errorOffline =>
      'No connection. Showing cached data where available.';

  @override
  String get errorTimeout => 'The server took too long to respond. Try again.';

  @override
  String get errorBadResponse => 'Unexpected server response.';

  @override
  String get errorNoChallenge => 'Sign in again to get a new code.';

  @override
  String get errorTokenStorage =>
      'This device refused to store the sign-in token securely. Check the app\'s keychain access and try again.';

  @override
  String get sessionEnded => 'Your session has ended. Please sign in again.';

  @override
  String get back => 'Back';

  @override
  String get justNow => 'just now';

  @override
  String minutesOld(int count) {
    return '$count min old';
  }

  @override
  String hoursOld(int count) {
    return '$count h old';
  }

  @override
  String get showPassword => 'Show password';

  @override
  String get hidePassword => 'Hide password';

  @override
  String get any => 'Any';

  @override
  String get clear => 'Clear';

  @override
  String get newAddress => 'New address';

  @override
  String get address => 'Address';

  @override
  String get conversation => 'Conversation';

  @override
  String get checkout => 'Checkout';

  @override
  String get payment => 'Payment';

  @override
  String get orderPlaced => 'Order placed';

  @override
  String get orderDetails => 'Order details';

  @override
  String get orderReturn => 'Return';

  @override
  String get orderReview => 'Review';
}

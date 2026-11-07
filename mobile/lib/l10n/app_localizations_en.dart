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
  String get sellerBackToBuying => 'Back to buying';

  @override
  String get sellerRequired => 'Your account is not an approved seller.';

  @override
  String get sellerTodaySales => 'Today\'s sales';

  @override
  String get sellerTodayOrders => 'Today\'s orders';

  @override
  String get sellerToShip => 'To ship';

  @override
  String get sellerLowStock => 'Low stock';

  @override
  String get sellerLiveProducts => 'Live products';

  @override
  String get sellerAvailable => 'Available';

  @override
  String get sellerHeld => 'Held';

  @override
  String get sellerPaidOut => 'Paid out';

  @override
  String get sellerOrdersByStatus => 'Orders by status';

  @override
  String get sellerRecentOrders => 'Recent orders';

  @override
  String get sellerNoOrders => 'No orders yet';

  @override
  String get sellerNoOrdersBody => 'Orders appear here as buyers check out.';

  @override
  String get sellerAllStatuses => 'All';

  @override
  String get sellerStatusPendingPayment => 'Pending payment';

  @override
  String get sellerStatusConfirmed => 'Confirmed';

  @override
  String get sellerStatusProcessing => 'Processing';

  @override
  String get sellerStatusShipped => 'Shipped';

  @override
  String get sellerStatusDelivered => 'Delivered';

  @override
  String get sellerStatusCompleted => 'Completed';

  @override
  String get sellerStatusCancelled => 'Cancelled';

  @override
  String get sellerStatusReturnRequested => 'Return requested';

  @override
  String get sellerStatusReturned => 'Returned';

  @override
  String get sellerStatusRefunded => 'Refunded';

  @override
  String sellerItemsCount(int count) {
    String _temp0 = intl.Intl.pluralLogic(
      count,
      locale: localeName,
      other: '$count items',
      one: '1 item',
    );
    return '$_temp0';
  }

  @override
  String get sellerOrder => 'Order';

  @override
  String get sellerBuyer => 'Buyer';

  @override
  String get sellerItems => 'Items';

  @override
  String get sellerSubtotal => 'Subtotal';

  @override
  String get sellerShippingFee => 'Shipping';

  @override
  String get sellerDiscount => 'Discount';

  @override
  String get sellerTax => 'Tax';

  @override
  String get sellerTotal => 'Total';

  @override
  String get sellerCommission => 'Commission';

  @override
  String get sellerPayment => 'Payment';

  @override
  String get sellerPaymentCod => 'Cash on delivery';

  @override
  String get sellerPaymentOnline => 'Online payment';

  @override
  String get sellerTracking => 'Tracking';

  @override
  String get sellerTrack => 'Track parcel';

  @override
  String get sellerHistory => 'History';

  @override
  String get sellerReturnRequest => 'Return request';

  @override
  String sellerRespondBy(String date) {
    return 'Respond by $date';
  }

  @override
  String get sellerPack => 'Pack';

  @override
  String get sellerShip => 'Ship';

  @override
  String get sellerMarkDelivered => 'Mark delivered';

  @override
  String get sellerAcceptReturn => 'Accept return';

  @override
  String get sellerDisputeReturn => 'Dispute';

  @override
  String get sellerConfirmReceived => 'Confirm item received';

  @override
  String get sellerShipTitle => 'Ship this order';

  @override
  String get sellerCourier => 'Courier';

  @override
  String get sellerCourierOther => 'Other';

  @override
  String get sellerCourierName => 'Courier name';

  @override
  String get sellerTrackingNo => 'Tracking number';

  @override
  String get sellerTrackingNoLength => 'Enter 6 to 64 characters.';

  @override
  String get sellerDisputeTitle => 'Dispute this return';

  @override
  String get sellerDisputeReason => 'Reason';

  @override
  String get sellerDisputeReasonMin => 'Write at least 10 characters.';

  @override
  String get sellerSubmit => 'Submit';

  @override
  String get sellerOrderUpdated => 'Order updated';

  @override
  String get sellerSearchProducts => 'Search products or SKU';

  @override
  String get sellerNoProducts => 'No products yet';

  @override
  String get sellerNoProductsBody => 'Add your first product to start selling.';

  @override
  String get sellerNewProduct => 'New product';

  @override
  String get sellerEditProduct => 'Edit product';

  @override
  String get sellerStatusDraft => 'Draft';

  @override
  String get sellerStatusPendingReview => 'Pending review';

  @override
  String get sellerStatusLive => 'Live';

  @override
  String get sellerStatusDelisted => 'Delisted';

  @override
  String get sellerStatusBanned => 'Banned';

  @override
  String get sellerStock => 'Stock';

  @override
  String sellerStockCount(int count) {
    return 'Stock $count';
  }

  @override
  String get sellerCod => 'COD';

  @override
  String get sellerNameEn => 'Name (English)';

  @override
  String get sellerNameMs => 'Name (Malay)';

  @override
  String get sellerNameVi => 'Name (Vietnamese)';

  @override
  String get sellerDescriptionEn => 'Description (English)';

  @override
  String get sellerDescriptionMs => 'Description (Malay)';

  @override
  String get sellerDescriptionVi => 'Description (Vietnamese)';

  @override
  String get sellerCategory => 'Category';

  @override
  String get sellerChooseCategory => 'Choose a category';

  @override
  String get sellerPriceRm => 'Price (RM)';

  @override
  String get sellerSalePriceRm => 'Sale price (RM)';

  @override
  String get sellerPriceFormat => 'Enter an amount like 12.50.';

  @override
  String get sellerSku => 'SKU';

  @override
  String get sellerWeightG => 'Weight (g)';

  @override
  String get sellerLengthMm => 'Length (mm)';

  @override
  String get sellerWidthMm => 'Width (mm)';

  @override
  String get sellerHeightMm => 'Height (mm)';

  @override
  String get sellerCodEnabled => 'Cash on delivery';

  @override
  String get sellerCodEnabledHelp =>
      'Let buyers pay on delivery for this product.';

  @override
  String get sellerVariants => 'Variants';

  @override
  String get sellerImages => 'Images';

  @override
  String get sellerImagesHelp => 'Up to 9 images.';

  @override
  String get sellerImagesSaveFirst => 'Save the product first to add images.';

  @override
  String get sellerAddImage => 'Add image';

  @override
  String get sellerRemoveImage => 'Remove image';

  @override
  String get sellerRemoveImageBody =>
      'This image will be removed from the listing.';

  @override
  String get sellerRemove => 'Remove';

  @override
  String get sellerGallery => 'Choose from gallery';

  @override
  String get sellerCamera => 'Take a photo';

  @override
  String get sellerSave => 'Save';

  @override
  String get sellerSaved => 'Saved';

  @override
  String get sellerPublish => 'Publish';

  @override
  String get sellerDelist => 'Delist';

  @override
  String get sellerRelist => 'Relist';

  @override
  String get sellerHalalBlocked => 'Publishing blocked';

  @override
  String get sellerNumberInvalid => 'Enter a whole number.';

  @override
  String get sellerRequestPayout => 'Request payout';

  @override
  String get sellerPayoutPending => 'Payout pending';

  @override
  String sellerPayoutMin(String amount) {
    return 'Minimum payout $amount';
  }

  @override
  String sellerPayoutMax(String amount) {
    return 'Up to $amount available';
  }

  @override
  String get sellerPayoutAmountRm => 'Amount (RM)';

  @override
  String get sellerPayoutRequested => 'Payout requested';

  @override
  String get sellerPayoutOpen => 'A payout request is already open.';

  @override
  String get sellerPayoutBelowMin => 'Below the minimum payout.';

  @override
  String get sellerPayoutAboveAvailable => 'More than the available balance.';

  @override
  String get sellerLedger => 'Ledger';

  @override
  String get sellerNoLedger => 'No activity yet';

  @override
  String get sellerNoLedgerBody =>
      'Sales and fees show up here once orders complete.';

  @override
  String get sellerPayouts => 'Payouts';

  @override
  String get sellerNoPayouts => 'No payouts yet';

  @override
  String get sellerLoadMore => 'Load more';

  @override
  String get sellerStoreName => 'Store name';

  @override
  String get sellerStoreDescription => 'Description';

  @override
  String get sellerState => 'State';

  @override
  String get sellerHolidayMode => 'Holiday mode';

  @override
  String get sellerHolidayModeHelp => 'Hide your products while you are away.';

  @override
  String get sellerShipping => 'Shipping';

  @override
  String get sellerShippingMatrix =>
      'Per-state rates are managed in the web seller centre.';

  @override
  String get sellerFlatFeeRm => 'Flat fee (RM)';

  @override
  String get sellerFreeAboveRm => 'Free shipping above (RM)';

  @override
  String get sellerBank => 'Bank details';

  @override
  String get sellerBankName => 'Bank';

  @override
  String get sellerAccountName => 'Account name';

  @override
  String get sellerAccountNumber => 'Account number';

  @override
  String sellerAccountNumberHelp(String masked) {
    return 'Enter the full number to replace $masked.';
  }

  @override
  String get sellerBankHelp =>
      'Enter the full account number to change bank details.';

  @override
  String get sellerChangeLogo => 'Change logo';

  @override
  String get sellerChangeBanner => 'Change banner';

  @override
  String get sellerReviews => 'Reviews';

  @override
  String get sellerNoReviews => 'No reviews yet';

  @override
  String get sellerNoReviewsBody =>
      'Buyer reviews of your products appear here.';

  @override
  String get sellerReplyHint => 'Write a reply to this buyer';

  @override
  String get sellerPostReply => 'Post reply';

  @override
  String get sellerYourReply => 'Your reply';

  @override
  String get sellerReplyMin => 'Write at least 5 characters.';

  @override
  String get sellerStoreSaved => 'Store saved';
}

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
  String get homePromotions => 'Promotions';

  @override
  String get flashSale => 'Flash Sale';

  @override
  String get flashEndsIn => 'Ends in';

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
  String get filterSort => 'Sort & filter';

  @override
  String get filterPrice => 'Price (RM)';

  @override
  String get filterMin => 'Min';

  @override
  String get filterMax => 'Max';

  @override
  String get filterReset => 'Reset';

  @override
  String get filterApply => 'Apply';

  @override
  String get filterPriceInvalid =>
      'Enter a price like 12.50, with Min no higher than Max.';

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
  String get reviews => 'Reviews';

  @override
  String get storeAbout => 'About';

  @override
  String storeJoined(String date) {
    return 'Joined $date';
  }

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
  String get sellerShipTo => 'Ship to';

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
  String get sellerCommissionEstimated =>
      'Estimated until the order completes.';

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
  String get sellerImagesHelp =>
      'Up to 9 photos. Pick several at once. They show in the order you add them.';

  @override
  String get sellerImagesSaveFirst => 'Save the product first to add images.';

  @override
  String get sellerImagesFull =>
      '9 photos is the limit. Remove one to add another.';

  @override
  String sellerImagesTooMany(int count) {
    return 'Only $count more photos fit. A product has at most 9.';
  }

  @override
  String get sellerDescriptionHasFormatting =>
      'This description has formatting. Edit it on the web seller centre.';

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

  @override
  String get sellerSaveStoreSettings => 'Save store settings';

  @override
  String get sellerBankNeedsNumber =>
      'Re-enter the full account number to change bank details.';

  @override
  String get sellerStatusPending => 'Pending';

  @override
  String get sellerStatusPaid => 'Paid';

  @override
  String get sellerStatusFailed => 'Failed';

  @override
  String get sellerStatusExpired => 'Expired';

  @override
  String get sellerDefaultVariant => 'Default';

  @override
  String sellerImageTooLarge(int kb) {
    return 'Choose an image under $kb KB.';
  }

  @override
  String get sellerAcceptReturnConfirm =>
      'Accept this return? The buyer will be asked to send the item back.';

  @override
  String get sellerDisputeConfirm =>
      'Dispute this return? HalalBizs will review the case with your reason.';

  @override
  String get sellerDelistConfirm =>
      'Delist this product? Buyers will no longer see it until you relist it.';

  @override
  String sellerPayoutConfirm(String amount) {
    return 'Request a payout of $amount to your bank account?';
  }

  @override
  String get cartCheckout => 'Checkout';

  @override
  String get cartContinueShopping => 'Continue shopping';

  @override
  String get cartEmptyTitle => 'Your cart is empty';

  @override
  String get cartEmptyBody =>
      'Find something halal and good — it will wait for you here.';

  @override
  String cartItemCount(int count) {
    return '$count items';
  }

  @override
  String get cartPurchasingPaused =>
      'Purchasing is paused — you can browse, but not check out right now.';

  @override
  String get cartRemove => 'Remove';

  @override
  String get cartSelectAll => 'Select all';

  @override
  String get cartSelectToCheckout => 'Select at least one item to check out.';

  @override
  String cartSelectedSubtotal(int count) {
    return 'Subtotal · $count selected';
  }

  @override
  String get cartUnavailable => 'Unavailable';

  @override
  String get cartViewCart => 'View cart';

  @override
  String get checkoutTitle => 'Checkout';

  @override
  String get checkoutDeliverTo => 'Deliver to';

  @override
  String get checkoutChange => 'Change';

  @override
  String get checkoutNoAddress =>
      'You have no delivery address yet — add one to place your order.';

  @override
  String get checkoutChooseMethod => 'Choose an available payment method.';

  @override
  String get checkoutSellerNote => 'Note to seller';

  @override
  String get checkoutSellerNoteHint =>
      'Optional — e.g. leave at the guard house';

  @override
  String get checkoutVouchers => 'Vouchers';

  @override
  String get checkoutPlatformVoucher => 'HalalBizs voucher';

  @override
  String get checkoutShopVoucher => 'Shop voucher';

  @override
  String get checkoutVoucherHint => 'Voucher code';

  @override
  String get checkoutVoucherApply => 'Apply';

  @override
  String get checkoutVoucherRemove => 'Remove';

  @override
  String checkoutVoucherApplied(String code, String amount) {
    return '$code applied · $amount off';
  }

  @override
  String checkoutUseCoins(int balance) {
    return 'Use coins (you have $balance)';
  }

  @override
  String get checkoutCoinsToUse => 'Coins to use';

  @override
  String checkoutCoinsWorth(int coins, String amount) {
    return '$coins coins · $amount off';
  }

  @override
  String get checkoutPaymentMethod => 'Payment method';

  @override
  String get checkoutMethodCod => 'Cash on delivery';

  @override
  String get checkoutMethodIpay88 => 'Online payment (FPX, card, e-wallet)';

  @override
  String get checkoutMethodUnavailable => 'Not available for this order.';

  @override
  String get checkoutSummary => 'Summary';

  @override
  String get checkoutSubtotal => 'Subtotal';

  @override
  String get checkoutShipping => 'Shipping';

  @override
  String get checkoutShippingPending => 'Choose an address';

  @override
  String get checkoutShippingDiscount => 'Shipping discount';

  @override
  String get checkoutPlatformDiscount => 'HalalBizs voucher';

  @override
  String get checkoutShopDiscount => 'Shop discount';

  @override
  String get checkoutTax => 'Tax';

  @override
  String get checkoutGrandTotal => 'Grand total';

  @override
  String get checkoutPlaceOrder => 'Place order';

  @override
  String get checkoutDoneTitle => 'Order placed';

  @override
  String checkoutDoneBody(String orderNo) {
    return 'Order $orderNo is confirmed. Your orders are here:';
  }

  @override
  String get checkoutViewOrders => 'View orders';

  @override
  String get checkoutPayTitle => 'Payment';

  @override
  String get checkoutPayBrowserTitle => 'Complete payment in your browser';

  @override
  String checkoutPayBrowserBody(String orderNo) {
    return 'Order $orderNo opened in your browser. Come back here when you are done — we check the payment automatically.';
  }

  @override
  String checkoutPayExpiresAt(String time) {
    return 'Pay before $time';
  }

  @override
  String get checkoutPayIHavePaid => 'I have paid';

  @override
  String get checkoutPayOpenAgain => 'Open payment page again';

  @override
  String get checkoutPayRetry => 'Retry payment';

  @override
  String get checkoutPayExpiredTitle => 'Payment window expired';

  @override
  String get checkoutPayExpiredBody =>
      'This order was not paid in time. Nothing was charged.';

  @override
  String get ordersAll => 'All';

  @override
  String get ordersOrder => 'Order';

  @override
  String get ordersTotal => 'Total';

  @override
  String ordersMoreItems(int count) {
    return '+$count more';
  }

  @override
  String ordersPlacedAt(String date) {
    return 'Placed $date';
  }

  @override
  String get ordersEmptyTitle => 'No orders yet';

  @override
  String get ordersEmptyBody => 'Your orders appear here once you check out.';

  @override
  String get ordersEmptyBucketTitle => 'No orders in this status';

  @override
  String get ordersEmptyBucketBody =>
      'Pick another tab to see your other orders.';

  @override
  String get ordersLoadMore => 'Load more';

  @override
  String get ordersTracking => 'Tracking';

  @override
  String get ordersTrack => 'Track';

  @override
  String get ordersReturnRequest => 'Return request';

  @override
  String get ordersCancelOrder => 'Cancel order';

  @override
  String get ordersCancelTitle => 'Cancel this order?';

  @override
  String get ordersCancelBody =>
      'Nothing will be charged and the items go back in stock.';

  @override
  String get ordersCancelConfirm => 'Cancel order';

  @override
  String get ordersKeep => 'Keep order';

  @override
  String get ordersCancelled => 'Order cancelled';

  @override
  String get ordersReason => 'Reason';

  @override
  String get ordersReasonHint => 'Choose a reason';

  @override
  String get ordersConfirmReceived => 'Confirm received';

  @override
  String get ordersReceivedTitle => 'Received your order?';

  @override
  String get ordersReceivedBody => 'Confirm only once everything has arrived.';

  @override
  String get ordersReceivedDone => 'Order received';

  @override
  String get ordersRequestReturn => 'Request return';

  @override
  String get ordersNote => 'Note';

  @override
  String get ordersReturnNoteHint => 'What went wrong?';

  @override
  String get ordersPhotos => 'Photos';

  @override
  String ordersPhotosHelp(int max) {
    return 'Up to $max photos.';
  }

  @override
  String get ordersReturnConfirmTitle => 'Submit this return request?';

  @override
  String get ordersReturnConfirmBody => 'The seller will be notified.';

  @override
  String get ordersReturnSubmit => 'Submit return request';

  @override
  String get ordersReturnSubmitted => 'Return requested';

  @override
  String get ordersWriteReview => 'Write a review';

  @override
  String get ordersReviewBody => 'Your review';

  @override
  String get ordersReviewBodyHint => 'Optional — at least 10 characters';

  @override
  String get ordersSellerRating => 'Seller rating';

  @override
  String get ordersSellerComment => 'Comment';

  @override
  String get ordersOptional => 'Optional';

  @override
  String get ordersRateEveryItem => 'Rate every item before submitting.';

  @override
  String get ordersReviewSubmit => 'Submit review';

  @override
  String get ordersReviewSubmitted => 'Thank you for your review.';

  @override
  String get ordersNothingToReview => 'Nothing left to review';

  @override
  String get accountSave => 'Save';

  @override
  String get accountDelete => 'Delete';

  @override
  String get accountDefault => 'Default';

  @override
  String get accountPhone => 'Phone';

  @override
  String get accountPreferredLanguage => 'Preferred language';

  @override
  String get accountEmailUnverified => 'Email not verified yet.';

  @override
  String get accountProfileSaved => 'Profile updated';

  @override
  String get accountChangePassword => 'Change password';

  @override
  String get accountCurrentPassword => 'Current password';

  @override
  String get accountNewPassword => 'New password';

  @override
  String get accountPasswordChanged => 'Password updated';

  @override
  String get accountAddAddress => 'Add address';

  @override
  String get accountEditAddress => 'Edit address';

  @override
  String get accountNoAddresses => 'No addresses yet';

  @override
  String get accountNoAddressesBody =>
      'Add a delivery address to check out faster.';

  @override
  String get accountAddressLabel => 'Label';

  @override
  String get accountAddressLabelHint => 'Home, Office…';

  @override
  String get accountRecipientName => 'Recipient name';

  @override
  String get accountLine1 => 'Address line 1';

  @override
  String get accountLine2 => 'Address line 2';

  @override
  String get accountPostcode => 'Postcode';

  @override
  String get accountCity => 'City';

  @override
  String get accountState => 'State';

  @override
  String get accountStateHint => 'Choose a state';

  @override
  String get accountSetDefault => 'Set as default';

  @override
  String get accountAddressSaved => 'Address saved';

  @override
  String get accountDeleteAddressTitle => 'Delete this address?';

  @override
  String get accountDeleteAddressBody => 'You can add it again any time.';

  @override
  String get accountWishlistEmpty => 'Nothing saved yet';

  @override
  String get accountWishlistEmptyBody =>
      'Tap the heart on a product to keep it here.';

  @override
  String get accountRemoveFromWishlist => 'Remove from wishlist';

  @override
  String get productAddToWishlist => 'Add to wishlist';

  @override
  String get accountCoinsBalance => 'Balance';

  @override
  String get accountCoinsStreak => 'Streak';

  @override
  String accountCoinsDays(int days) {
    return '$days days';
  }

  @override
  String accountCoinsLifetime(int coins) {
    return '$coins coins earned in total';
  }

  @override
  String accountCoinsExpiring(String date) {
    return 'Some coins expire $date';
  }

  @override
  String get accountCheckIn => 'Check in today';

  @override
  String get accountCheckedIn => 'Checked in today';

  @override
  String get accountCheckedInReason => 'You have already checked in today.';

  @override
  String get accountCheckInBody =>
      'Check in every day to grow your streak and earn more coins.';

  @override
  String accountCheckInDone(int coins, int streak) {
    return '+$coins coins · day $streak streak';
  }

  @override
  String get accountCoinsHistory => 'History';

  @override
  String get accountCoinsNoHistory => 'No coin activity yet';

  @override
  String get accountCoinsUnavailable => 'Coins are not available';

  @override
  String get accountCoinsUnavailableBody =>
      'Daily check-in is unavailable right now.';

  @override
  String get accountMarkAllRead => 'Mark all read';

  @override
  String get accountNoNotifications => 'No notifications';

  @override
  String get accountNoNotificationsBody =>
      'Order updates and messages from sellers show up here.';

  @override
  String get accountDeleteWarning => 'This cannot be undone.';

  @override
  String get accountDeleteBody =>
      'Your personal details are anonymized immediately; order records stay for legal and financial reasons. Enter your password to continue.';

  @override
  String get accountDeleteConfirmTitle => 'Delete your account?';

  @override
  String get accountDeleteConfirmBody =>
      'You will be signed out and cannot sign in again with this account.';

  @override
  String get messagesChatWithSeller => 'Chat with seller';

  @override
  String get messagesEmpty => 'No messages yet';

  @override
  String get messagesEmptyBody => 'Start a chat from any product page.';

  @override
  String get messagesNoMessagesYet => 'No messages in this conversation yet.';

  @override
  String get messagesSayHello => 'Say hello to the seller.';

  @override
  String get messagesTypeMessage => 'Type a message';

  @override
  String get messagesSend => 'Send';

  @override
  String get checkoutPlacedCheckOrders =>
      'Your order was placed. Check your orders.';

  @override
  String get checkoutMaybePlaced =>
      'We could not confirm whether the order went through. Check your orders before trying again.';

  @override
  String get checkoutPayLinkMissing =>
      'The order was placed but no payment link came back. Pay it from your orders.';

  @override
  String get accountWishlistStillSaved => 'Still in your wishlist — try again.';

  @override
  String get accountUnread => 'Unread';

  @override
  String ordersReviewStars(int n) {
    String _temp0 = intl.Intl.pluralLogic(
      n,
      locale: localeName,
      other: '$n stars',
      one: '1 star',
    );
    return '$_temp0';
  }

  @override
  String get ordersPaymentStatusPending => 'Pending';

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
    return 'Paid $amount (payment $orderNo).';
  }

  @override
  String checkoutDoneCod(String amount, String orderNo) {
    return 'Total $amount, paid on delivery (payment $orderNo).';
  }

  @override
  String checkoutDoneSplit(int count) {
    String _temp0 = intl.Intl.pluralLogic(
      count,
      locale: localeName,
      other: 'Your order is split into $count shipments, one per shop.',
      one: 'It ships as one shipment.',
    );
    return '$_temp0';
  }

  @override
  String ordersOrderNo(String no) {
    return 'Order $no';
  }

  @override
  String ordersPaymentNo(String no) {
    return 'Payment $no';
  }

  @override
  String productSelect(String options) {
    return 'Select: $options';
  }

  @override
  String productChoose(String option) {
    return 'Choose a $option';
  }

  @override
  String productStock(int count) {
    return 'Stock: $count';
  }

  @override
  String get productViewImage => 'View image';

  @override
  String get close => 'Close';

  @override
  String get checkoutVoucherBest => 'BEST VALUE';

  @override
  String get checkoutVoucherAppliedTag => 'APPLIED';

  @override
  String get checkoutVoucherFreeShipping => 'Free shipping';

  @override
  String checkoutVoucherShort(String amount) {
    return 'Add $amount more to use this voucher.';
  }

  @override
  String checkoutVoucherOff(String amount) {
    return '$amount off';
  }

  @override
  String get ordersPayNow => 'Pay now';

  @override
  String get ordersBuyAgain => 'Buy again';

  @override
  String ordersBuyAgainDone(int added) {
    String _temp0 = intl.Intl.pluralLogic(
      added,
      locale: localeName,
      other: '$added items',
      one: '1 item',
    );
    return '$_temp0 added to cart';
  }

  @override
  String ordersBuyAgainSkipped(int added, int skipped) {
    String _temp0 = intl.Intl.pluralLogic(
      added,
      locale: localeName,
      other: '$added items',
      one: '1 item',
    );
    String _temp1 = intl.Intl.pluralLogic(
      skipped,
      locale: localeName,
      other: '$skipped items',
      one: '1 item',
    );
    return '$_temp0 added to cart · $_temp1 skipped (not available)';
  }

  @override
  String get ordersBuyAgainNone =>
      'None of these items are available any more.';

  @override
  String get halalCertified => 'Halal certified';

  @override
  String get halalCertVerified => 'Halal certificate verified';

  @override
  String get halalCertLapsed => 'Certificate lapsed';

  @override
  String get halalCertNotInForce => 'Certificate not yet in force';

  @override
  String halalExpiredOn(String date) {
    return 'expired $date';
  }

  @override
  String halalValidFrom(String date) {
    return 'valid from $date';
  }

  @override
  String halalValidTo(String date) {
    return 'valid to $date';
  }

  @override
  String get halalReadRecord => 'Read the record';

  @override
  String get halalScopeTitle => 'Ingredients & scope';

  @override
  String halalScopeBody(String scope) {
    return 'Certified scope: $scope. The certificate names this SKU in its annex, so the badge is bound to the item you are buying, not to the shop that sells it.';
  }

  @override
  String get halalTraceability => 'Traceability';

  @override
  String get halalFacility => 'Facility';

  @override
  String get halalBatch => 'Batch';

  @override
  String halalPackedOn(String date) {
    return 'packed $date';
  }

  @override
  String get halalCertificate => 'Certificate';

  @override
  String get halalExpiry => 'Expiry';

  @override
  String get halalLapsed => 'lapsed';

  @override
  String halalNotInForceUntil(String date) {
    return 'not in force until $date';
  }

  @override
  String halalDaysRemaining(int count) {
    String _temp0 = intl.Intl.pluralLogic(
      count,
      locale: localeName,
      other: '$count days remaining',
      one: '1 day remaining',
    );
    return '$_temp0';
  }

  @override
  String get halalCertifyingBody => 'Certifying body';

  @override
  String get halalAssurance => 'Assurance';

  @override
  String get halalAssureValid12 => 'Certificate valid 12 months+';

  @override
  String get halalAssureDedicated => 'Dedicated halal facility';

  @override
  String get halalAssureExport => 'Export paperwork included';
}

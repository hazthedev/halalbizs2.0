import 'dart:async';

import 'package:flutter/foundation.dart';
import 'package:flutter/widgets.dart';
import 'package:flutter_localizations/flutter_localizations.dart';
import 'package:intl/intl.dart' as intl;

import 'app_localizations_en.dart';
import 'app_localizations_ms.dart';

// ignore_for_file: type=lint

/// Callers can lookup localized strings with an instance of AppLocalizations
/// returned by `AppLocalizations.of(context)`.
///
/// Applications need to include `AppLocalizations.delegate()` in their app's
/// `localizationDelegates` list, and the locales they support in the app's
/// `supportedLocales` list. For example:
///
/// ```dart
/// import 'l10n/app_localizations.dart';
///
/// return MaterialApp(
///   localizationsDelegates: AppLocalizations.localizationsDelegates,
///   supportedLocales: AppLocalizations.supportedLocales,
///   home: MyApplicationHome(),
/// );
/// ```
///
/// ## Update pubspec.yaml
///
/// Please make sure to update your pubspec.yaml to include the following
/// packages:
///
/// ```yaml
/// dependencies:
///   # Internationalization support.
///   flutter_localizations:
///     sdk: flutter
///   intl: any # Use the pinned version from flutter_localizations
///
///   # Rest of dependencies
/// ```
///
/// ## iOS Applications
///
/// iOS applications define key application metadata, including supported
/// locales, in an Info.plist file that is built into the application bundle.
/// To configure the locales supported by your app, you’ll need to edit this
/// file.
///
/// First, open your project’s ios/Runner.xcworkspace Xcode workspace file.
/// Then, in the Project Navigator, open the Info.plist file under the Runner
/// project’s Runner folder.
///
/// Next, select the Information Property List item, select Add Item from the
/// Editor menu, then select Localizations from the pop-up menu.
///
/// Select and expand the newly-created Localizations item then, for each
/// locale your application supports, add a new item and select the locale
/// you wish to add from the pop-up menu in the Value field. This list should
/// be consistent with the languages listed in the AppLocalizations.supportedLocales
/// property.
abstract class AppLocalizations {
  AppLocalizations(String locale)
    : localeName = intl.Intl.canonicalizedLocale(locale.toString());

  final String localeName;

  static AppLocalizations? of(BuildContext context) {
    return Localizations.of<AppLocalizations>(context, AppLocalizations);
  }

  static const LocalizationsDelegate<AppLocalizations> delegate =
      _AppLocalizationsDelegate();

  /// A list of this localizations delegate along with the default localizations
  /// delegates.
  ///
  /// Returns a list of localizations delegates containing this delegate along with
  /// GlobalMaterialLocalizations.delegate, GlobalCupertinoLocalizations.delegate,
  /// and GlobalWidgetsLocalizations.delegate.
  ///
  /// Additional delegates can be added by appending to this list in
  /// MaterialApp. This list does not have to be used at all if a custom list
  /// of delegates is preferred or required.
  static const List<LocalizationsDelegate<dynamic>> localizationsDelegates =
      <LocalizationsDelegate<dynamic>>[
        delegate,
        GlobalMaterialLocalizations.delegate,
        GlobalCupertinoLocalizations.delegate,
        GlobalWidgetsLocalizations.delegate,
      ];

  /// A list of this localizations delegate's supported locales.
  static const List<Locale> supportedLocales = <Locale>[
    Locale('en'),
    Locale('ms'),
  ];

  /// No description provided for @appName.
  ///
  /// In en, this message translates to:
  /// **'HalalBizs'**
  String get appName;

  /// No description provided for @tabHome.
  ///
  /// In en, this message translates to:
  /// **'Home'**
  String get tabHome;

  /// No description provided for @tabSearch.
  ///
  /// In en, this message translates to:
  /// **'Search'**
  String get tabSearch;

  /// No description provided for @tabCart.
  ///
  /// In en, this message translates to:
  /// **'Cart'**
  String get tabCart;

  /// No description provided for @tabOrders.
  ///
  /// In en, this message translates to:
  /// **'Orders'**
  String get tabOrders;

  /// No description provided for @tabAccount.
  ///
  /// In en, this message translates to:
  /// **'Account'**
  String get tabAccount;

  /// No description provided for @sellerTabDashboard.
  ///
  /// In en, this message translates to:
  /// **'Dashboard'**
  String get sellerTabDashboard;

  /// No description provided for @sellerTabOrders.
  ///
  /// In en, this message translates to:
  /// **'Orders'**
  String get sellerTabOrders;

  /// No description provided for @sellerTabProducts.
  ///
  /// In en, this message translates to:
  /// **'Products'**
  String get sellerTabProducts;

  /// No description provided for @sellerTabEarnings.
  ///
  /// In en, this message translates to:
  /// **'Earnings'**
  String get sellerTabEarnings;

  /// No description provided for @sellerTabStore.
  ///
  /// In en, this message translates to:
  /// **'Store'**
  String get sellerTabStore;

  /// No description provided for @signIn.
  ///
  /// In en, this message translates to:
  /// **'Sign in'**
  String get signIn;

  /// No description provided for @signInTitle.
  ///
  /// In en, this message translates to:
  /// **'Welcome back'**
  String get signInTitle;

  /// No description provided for @signInSubtitle.
  ///
  /// In en, this message translates to:
  /// **'Sign in to your HalalBizs account.'**
  String get signInSubtitle;

  /// No description provided for @email.
  ///
  /// In en, this message translates to:
  /// **'Email'**
  String get email;

  /// No description provided for @password.
  ///
  /// In en, this message translates to:
  /// **'Password'**
  String get password;

  /// No description provided for @confirmPassword.
  ///
  /// In en, this message translates to:
  /// **'Confirm password'**
  String get confirmPassword;

  /// No description provided for @name.
  ///
  /// In en, this message translates to:
  /// **'Name'**
  String get name;

  /// No description provided for @forgotPassword.
  ///
  /// In en, this message translates to:
  /// **'Forgot password?'**
  String get forgotPassword;

  /// No description provided for @createAccount.
  ///
  /// In en, this message translates to:
  /// **'Create account'**
  String get createAccount;

  /// No description provided for @createAccountSubtitle.
  ///
  /// In en, this message translates to:
  /// **'Shop halal, from sellers you can trust.'**
  String get createAccountSubtitle;

  /// No description provided for @noAccount.
  ///
  /// In en, this message translates to:
  /// **'Don\'t have an account?'**
  String get noAccount;

  /// No description provided for @haveAccount.
  ///
  /// In en, this message translates to:
  /// **'Already have an account?'**
  String get haveAccount;

  /// No description provided for @register.
  ///
  /// In en, this message translates to:
  /// **'Register'**
  String get register;

  /// No description provided for @twoFactorTitle.
  ///
  /// In en, this message translates to:
  /// **'Two-factor authentication'**
  String get twoFactorTitle;

  /// No description provided for @twoFactorHintTotp.
  ///
  /// In en, this message translates to:
  /// **'Enter the 6-digit code from your authenticator app.'**
  String get twoFactorHintTotp;

  /// No description provided for @twoFactorHintEmail.
  ///
  /// In en, this message translates to:
  /// **'Enter the code we emailed you.'**
  String get twoFactorHintEmail;

  /// No description provided for @code.
  ///
  /// In en, this message translates to:
  /// **'Code'**
  String get code;

  /// No description provided for @verify.
  ///
  /// In en, this message translates to:
  /// **'Verify'**
  String get verify;

  /// No description provided for @cancel.
  ///
  /// In en, this message translates to:
  /// **'Cancel'**
  String get cancel;

  /// No description provided for @confirm.
  ///
  /// In en, this message translates to:
  /// **'Confirm'**
  String get confirm;

  /// No description provided for @resetPassword.
  ///
  /// In en, this message translates to:
  /// **'Reset password'**
  String get resetPassword;

  /// No description provided for @resetPasswordSubtitle.
  ///
  /// In en, this message translates to:
  /// **'We will email you a link to choose a new password.'**
  String get resetPasswordSubtitle;

  /// No description provided for @sendResetLink.
  ///
  /// In en, this message translates to:
  /// **'Send reset link'**
  String get sendResetLink;

  /// No description provided for @resetLinkSent.
  ///
  /// In en, this message translates to:
  /// **'If that email is registered, a reset link is on its way.'**
  String get resetLinkSent;

  /// No description provided for @verifyEmailTitle.
  ///
  /// In en, this message translates to:
  /// **'Verify your email'**
  String get verifyEmailTitle;

  /// No description provided for @verifyEmailBody.
  ///
  /// In en, this message translates to:
  /// **'We sent a verification link to {email}. Open it, then come back here.'**
  String verifyEmailBody(String email);

  /// No description provided for @resendVerification.
  ///
  /// In en, this message translates to:
  /// **'Resend verification email'**
  String get resendVerification;

  /// No description provided for @verificationSent.
  ///
  /// In en, this message translates to:
  /// **'Verification email sent.'**
  String get verificationSent;

  /// No description provided for @iHaveVerified.
  ///
  /// In en, this message translates to:
  /// **'I have verified'**
  String get iHaveVerified;

  /// No description provided for @categories.
  ///
  /// In en, this message translates to:
  /// **'Categories'**
  String get categories;

  /// No description provided for @featured.
  ///
  /// In en, this message translates to:
  /// **'Featured'**
  String get featured;

  /// No description provided for @viewAll.
  ///
  /// In en, this message translates to:
  /// **'View all'**
  String get viewAll;

  /// No description provided for @searchHint.
  ///
  /// In en, this message translates to:
  /// **'Search products'**
  String get searchHint;

  /// No description provided for @searchMinChars.
  ///
  /// In en, this message translates to:
  /// **'Type at least 2 characters to search.'**
  String get searchMinChars;

  /// No description provided for @searchNeedsConnection.
  ///
  /// In en, this message translates to:
  /// **'Search needs a connection'**
  String get searchNeedsConnection;

  /// No description provided for @searchNeedsConnectionBody.
  ///
  /// In en, this message translates to:
  /// **'Reconnect and try again. Cached products are still in Home.'**
  String get searchNeedsConnectionBody;

  /// No description provided for @searchNoResults.
  ///
  /// In en, this message translates to:
  /// **'No results for “{query}”'**
  String searchNoResults(String query);

  /// No description provided for @searchNoResultsBody.
  ///
  /// In en, this message translates to:
  /// **'Try a different word or browse the categories.'**
  String get searchNoResultsBody;

  /// No description provided for @sort.
  ///
  /// In en, this message translates to:
  /// **'Sort'**
  String get sort;

  /// No description provided for @sortRelevance.
  ///
  /// In en, this message translates to:
  /// **'Relevance'**
  String get sortRelevance;

  /// No description provided for @sortPopular.
  ///
  /// In en, this message translates to:
  /// **'Popular'**
  String get sortPopular;

  /// No description provided for @sortNewest.
  ///
  /// In en, this message translates to:
  /// **'Newest'**
  String get sortNewest;

  /// No description provided for @sortPriceAsc.
  ///
  /// In en, this message translates to:
  /// **'Price: low to high'**
  String get sortPriceAsc;

  /// No description provided for @sortPriceDesc.
  ///
  /// In en, this message translates to:
  /// **'Price: high to low'**
  String get sortPriceDesc;

  /// No description provided for @allCategories.
  ///
  /// In en, this message translates to:
  /// **'All'**
  String get allCategories;

  /// No description provided for @sold.
  ///
  /// In en, this message translates to:
  /// **'sold'**
  String get sold;

  /// No description provided for @addToCart.
  ///
  /// In en, this message translates to:
  /// **'Add to cart'**
  String get addToCart;

  /// No description provided for @outOfStock.
  ///
  /// In en, this message translates to:
  /// **'Out of stock'**
  String get outOfStock;

  /// No description provided for @inStock.
  ///
  /// In en, this message translates to:
  /// **'{count} in stock'**
  String inStock(int count);

  /// No description provided for @onlyLeft.
  ///
  /// In en, this message translates to:
  /// **'Only {count} left'**
  String onlyLeft(int count);

  /// No description provided for @variant.
  ///
  /// In en, this message translates to:
  /// **'Variant'**
  String get variant;

  /// No description provided for @quantity.
  ///
  /// In en, this message translates to:
  /// **'Quantity'**
  String get quantity;

  /// No description provided for @description.
  ///
  /// In en, this message translates to:
  /// **'Description'**
  String get description;

  /// No description provided for @soldBy.
  ///
  /// In en, this message translates to:
  /// **'Sold by'**
  String get soldBy;

  /// No description provided for @visitStore.
  ///
  /// In en, this message translates to:
  /// **'Visit store'**
  String get visitStore;

  /// No description provided for @reviewsCount.
  ///
  /// In en, this message translates to:
  /// **'{count} reviews'**
  String reviewsCount(int count);

  /// No description provided for @cartComingSoon.
  ///
  /// In en, this message translates to:
  /// **'Cart arrives in the next slice'**
  String get cartComingSoon;

  /// No description provided for @addedToCart.
  ///
  /// In en, this message translates to:
  /// **'Added to cart'**
  String get addedToCart;

  /// No description provided for @signInToContinue.
  ///
  /// In en, this message translates to:
  /// **'Sign in to continue'**
  String get signInToContinue;

  /// No description provided for @noProductsYet.
  ///
  /// In en, this message translates to:
  /// **'No products yet'**
  String get noProductsYet;

  /// No description provided for @noProductsBody.
  ///
  /// In en, this message translates to:
  /// **'Check back soon. Sellers are adding stock.'**
  String get noProductsBody;

  /// No description provided for @comingNextSlice.
  ///
  /// In en, this message translates to:
  /// **'Coming in the next slice'**
  String get comingNextSlice;

  /// No description provided for @comingNextSliceBody.
  ///
  /// In en, this message translates to:
  /// **'This screen is built in the next release.'**
  String get comingNextSliceBody;

  /// No description provided for @retry.
  ///
  /// In en, this message translates to:
  /// **'Retry'**
  String get retry;

  /// No description provided for @backToHome.
  ///
  /// In en, this message translates to:
  /// **'Back to home'**
  String get backToHome;

  /// No description provided for @pageNotFound.
  ///
  /// In en, this message translates to:
  /// **'Page not found'**
  String get pageNotFound;

  /// No description provided for @pageNotFoundBody.
  ///
  /// In en, this message translates to:
  /// **'This link doesn\'t match a page in HalalBizs.'**
  String get pageNotFoundBody;

  /// No description provided for @profile.
  ///
  /// In en, this message translates to:
  /// **'Profile'**
  String get profile;

  /// No description provided for @addresses.
  ///
  /// In en, this message translates to:
  /// **'Addresses'**
  String get addresses;

  /// No description provided for @wishlist.
  ///
  /// In en, this message translates to:
  /// **'Wishlist'**
  String get wishlist;

  /// No description provided for @coins.
  ///
  /// In en, this message translates to:
  /// **'Coins'**
  String get coins;

  /// No description provided for @notifications.
  ///
  /// In en, this message translates to:
  /// **'Notifications'**
  String get notifications;

  /// No description provided for @messages.
  ///
  /// In en, this message translates to:
  /// **'Messages'**
  String get messages;

  /// No description provided for @language.
  ///
  /// In en, this message translates to:
  /// **'Language'**
  String get language;

  /// No description provided for @deleteAccount.
  ///
  /// In en, this message translates to:
  /// **'Delete account'**
  String get deleteAccount;

  /// No description provided for @sellerCentre.
  ///
  /// In en, this message translates to:
  /// **'Seller centre'**
  String get sellerCentre;

  /// No description provided for @becomeSeller.
  ///
  /// In en, this message translates to:
  /// **'Become a seller'**
  String get becomeSeller;

  /// No description provided for @signOut.
  ///
  /// In en, this message translates to:
  /// **'Sign out'**
  String get signOut;

  /// No description provided for @signOutConfirmTitle.
  ///
  /// In en, this message translates to:
  /// **'Sign out?'**
  String get signOutConfirmTitle;

  /// No description provided for @signOutConfirmBody.
  ///
  /// In en, this message translates to:
  /// **'You can sign in again any time.'**
  String get signOutConfirmBody;

  /// No description provided for @english.
  ///
  /// In en, this message translates to:
  /// **'English'**
  String get english;

  /// No description provided for @malay.
  ///
  /// In en, this message translates to:
  /// **'Bahasa Melayu'**
  String get malay;

  /// No description provided for @backToShop.
  ///
  /// In en, this message translates to:
  /// **'Back to shop'**
  String get backToShop;

  /// No description provided for @guestAccountTitle.
  ///
  /// In en, this message translates to:
  /// **'Sign in to see your account'**
  String get guestAccountTitle;

  /// No description provided for @guestAccountBody.
  ///
  /// In en, this message translates to:
  /// **'Orders, addresses, coins and messages live here once you are signed in.'**
  String get guestAccountBody;

  /// No description provided for @fieldRequired.
  ///
  /// In en, this message translates to:
  /// **'This field is required.'**
  String get fieldRequired;

  /// No description provided for @emailInvalid.
  ///
  /// In en, this message translates to:
  /// **'Enter a valid email address.'**
  String get emailInvalid;

  /// No description provided for @passwordMin.
  ///
  /// In en, this message translates to:
  /// **'Use at least 8 characters.'**
  String get passwordMin;

  /// No description provided for @passwordMismatch.
  ///
  /// In en, this message translates to:
  /// **'Passwords do not match.'**
  String get passwordMismatch;

  /// No description provided for @codeLength.
  ///
  /// In en, this message translates to:
  /// **'Enter the 6-digit code.'**
  String get codeLength;

  /// No description provided for @offlineStrip.
  ///
  /// In en, this message translates to:
  /// **'Offline · showing cached data'**
  String get offlineStrip;

  /// No description provided for @cachedStrip.
  ///
  /// In en, this message translates to:
  /// **'Showing cached data'**
  String get cachedStrip;

  /// No description provided for @cachedChip.
  ///
  /// In en, this message translates to:
  /// **'Cached'**
  String get cachedChip;

  /// No description provided for @needsConnection.
  ///
  /// In en, this message translates to:
  /// **'Needs a connection'**
  String get needsConnection;

  /// No description provided for @somethingWentWrong.
  ///
  /// In en, this message translates to:
  /// **'Something went wrong.'**
  String get somethingWentWrong;

  /// No description provided for @store.
  ///
  /// In en, this message translates to:
  /// **'Store'**
  String get store;

  /// No description provided for @products.
  ///
  /// In en, this message translates to:
  /// **'Products'**
  String get products;

  /// No description provided for @category.
  ///
  /// In en, this message translates to:
  /// **'Category'**
  String get category;

  /// No description provided for @product.
  ///
  /// In en, this message translates to:
  /// **'Product'**
  String get product;

  /// No description provided for @errorOffline.
  ///
  /// In en, this message translates to:
  /// **'No connection. Showing cached data where available.'**
  String get errorOffline;

  /// No description provided for @errorTimeout.
  ///
  /// In en, this message translates to:
  /// **'The server took too long to respond. Try again.'**
  String get errorTimeout;

  /// No description provided for @errorBadResponse.
  ///
  /// In en, this message translates to:
  /// **'Unexpected server response.'**
  String get errorBadResponse;

  /// No description provided for @errorNoChallenge.
  ///
  /// In en, this message translates to:
  /// **'Sign in again to get a new code.'**
  String get errorNoChallenge;

  /// No description provided for @errorTokenStorage.
  ///
  /// In en, this message translates to:
  /// **'This device refused to store the sign-in token securely. Check the app\'s keychain access and try again.'**
  String get errorTokenStorage;

  /// No description provided for @sessionEnded.
  ///
  /// In en, this message translates to:
  /// **'Your session has ended. Please sign in again.'**
  String get sessionEnded;

  /// No description provided for @back.
  ///
  /// In en, this message translates to:
  /// **'Back'**
  String get back;

  /// No description provided for @justNow.
  ///
  /// In en, this message translates to:
  /// **'just now'**
  String get justNow;

  /// No description provided for @minutesOld.
  ///
  /// In en, this message translates to:
  /// **'{count} min old'**
  String minutesOld(int count);

  /// No description provided for @hoursOld.
  ///
  /// In en, this message translates to:
  /// **'{count} h old'**
  String hoursOld(int count);

  /// No description provided for @showPassword.
  ///
  /// In en, this message translates to:
  /// **'Show password'**
  String get showPassword;

  /// No description provided for @hidePassword.
  ///
  /// In en, this message translates to:
  /// **'Hide password'**
  String get hidePassword;

  /// No description provided for @any.
  ///
  /// In en, this message translates to:
  /// **'Any'**
  String get any;

  /// No description provided for @clear.
  ///
  /// In en, this message translates to:
  /// **'Clear'**
  String get clear;

  /// No description provided for @newAddress.
  ///
  /// In en, this message translates to:
  /// **'New address'**
  String get newAddress;

  /// No description provided for @address.
  ///
  /// In en, this message translates to:
  /// **'Address'**
  String get address;

  /// No description provided for @conversation.
  ///
  /// In en, this message translates to:
  /// **'Conversation'**
  String get conversation;

  /// No description provided for @checkout.
  ///
  /// In en, this message translates to:
  /// **'Checkout'**
  String get checkout;

  /// No description provided for @payment.
  ///
  /// In en, this message translates to:
  /// **'Payment'**
  String get payment;

  /// No description provided for @orderPlaced.
  ///
  /// In en, this message translates to:
  /// **'Order placed'**
  String get orderPlaced;

  /// No description provided for @orderDetails.
  ///
  /// In en, this message translates to:
  /// **'Order details'**
  String get orderDetails;

  /// No description provided for @orderReturn.
  ///
  /// In en, this message translates to:
  /// **'Return'**
  String get orderReturn;

  /// No description provided for @orderReview.
  ///
  /// In en, this message translates to:
  /// **'Review'**
  String get orderReview;

  /// No description provided for @sellerBackToBuying.
  ///
  /// In en, this message translates to:
  /// **'Back to buying'**
  String get sellerBackToBuying;

  /// No description provided for @sellerRequired.
  ///
  /// In en, this message translates to:
  /// **'Your account is not an approved seller.'**
  String get sellerRequired;

  /// No description provided for @sellerTodaySales.
  ///
  /// In en, this message translates to:
  /// **'Today\'s sales'**
  String get sellerTodaySales;

  /// No description provided for @sellerTodayOrders.
  ///
  /// In en, this message translates to:
  /// **'Today\'s orders'**
  String get sellerTodayOrders;

  /// No description provided for @sellerToShip.
  ///
  /// In en, this message translates to:
  /// **'To ship'**
  String get sellerToShip;

  /// No description provided for @sellerLowStock.
  ///
  /// In en, this message translates to:
  /// **'Low stock'**
  String get sellerLowStock;

  /// No description provided for @sellerAvailable.
  ///
  /// In en, this message translates to:
  /// **'Available'**
  String get sellerAvailable;

  /// No description provided for @sellerHeld.
  ///
  /// In en, this message translates to:
  /// **'Held'**
  String get sellerHeld;

  /// No description provided for @sellerPaidOut.
  ///
  /// In en, this message translates to:
  /// **'Paid out'**
  String get sellerPaidOut;

  /// No description provided for @sellerOrdersByStatus.
  ///
  /// In en, this message translates to:
  /// **'Orders by status'**
  String get sellerOrdersByStatus;

  /// No description provided for @sellerRecentOrders.
  ///
  /// In en, this message translates to:
  /// **'Recent orders'**
  String get sellerRecentOrders;

  /// No description provided for @sellerNoOrders.
  ///
  /// In en, this message translates to:
  /// **'No orders yet'**
  String get sellerNoOrders;

  /// No description provided for @sellerNoOrdersBody.
  ///
  /// In en, this message translates to:
  /// **'Orders appear here as buyers check out.'**
  String get sellerNoOrdersBody;

  /// No description provided for @sellerAllStatuses.
  ///
  /// In en, this message translates to:
  /// **'All'**
  String get sellerAllStatuses;

  /// No description provided for @sellerStatusPendingPayment.
  ///
  /// In en, this message translates to:
  /// **'Pending payment'**
  String get sellerStatusPendingPayment;

  /// No description provided for @sellerStatusConfirmed.
  ///
  /// In en, this message translates to:
  /// **'Confirmed'**
  String get sellerStatusConfirmed;

  /// No description provided for @sellerStatusProcessing.
  ///
  /// In en, this message translates to:
  /// **'Processing'**
  String get sellerStatusProcessing;

  /// No description provided for @sellerStatusShipped.
  ///
  /// In en, this message translates to:
  /// **'Shipped'**
  String get sellerStatusShipped;

  /// No description provided for @sellerStatusDelivered.
  ///
  /// In en, this message translates to:
  /// **'Delivered'**
  String get sellerStatusDelivered;

  /// No description provided for @sellerStatusCompleted.
  ///
  /// In en, this message translates to:
  /// **'Completed'**
  String get sellerStatusCompleted;

  /// No description provided for @sellerStatusCancelled.
  ///
  /// In en, this message translates to:
  /// **'Cancelled'**
  String get sellerStatusCancelled;

  /// No description provided for @sellerStatusReturnRequested.
  ///
  /// In en, this message translates to:
  /// **'Return requested'**
  String get sellerStatusReturnRequested;

  /// No description provided for @sellerStatusReturned.
  ///
  /// In en, this message translates to:
  /// **'Returned'**
  String get sellerStatusReturned;

  /// No description provided for @sellerStatusRefunded.
  ///
  /// In en, this message translates to:
  /// **'Refunded'**
  String get sellerStatusRefunded;

  /// No description provided for @sellerItemsCount.
  ///
  /// In en, this message translates to:
  /// **'{count, plural, =1{1 item} other{{count} items}}'**
  String sellerItemsCount(int count);

  /// No description provided for @sellerOrder.
  ///
  /// In en, this message translates to:
  /// **'Order'**
  String get sellerOrder;

  /// No description provided for @sellerBuyer.
  ///
  /// In en, this message translates to:
  /// **'Buyer'**
  String get sellerBuyer;

  /// No description provided for @sellerShipTo.
  ///
  /// In en, this message translates to:
  /// **'Ship to'**
  String get sellerShipTo;

  /// No description provided for @sellerItems.
  ///
  /// In en, this message translates to:
  /// **'Items'**
  String get sellerItems;

  /// No description provided for @sellerSubtotal.
  ///
  /// In en, this message translates to:
  /// **'Subtotal'**
  String get sellerSubtotal;

  /// No description provided for @sellerShippingFee.
  ///
  /// In en, this message translates to:
  /// **'Shipping'**
  String get sellerShippingFee;

  /// No description provided for @sellerDiscount.
  ///
  /// In en, this message translates to:
  /// **'Discount'**
  String get sellerDiscount;

  /// No description provided for @sellerTax.
  ///
  /// In en, this message translates to:
  /// **'Tax'**
  String get sellerTax;

  /// No description provided for @sellerTotal.
  ///
  /// In en, this message translates to:
  /// **'Total'**
  String get sellerTotal;

  /// No description provided for @sellerCommission.
  ///
  /// In en, this message translates to:
  /// **'Commission'**
  String get sellerCommission;

  /// No description provided for @sellerCommissionEstimated.
  ///
  /// In en, this message translates to:
  /// **'Estimated until the order completes.'**
  String get sellerCommissionEstimated;

  /// No description provided for @sellerPayment.
  ///
  /// In en, this message translates to:
  /// **'Payment'**
  String get sellerPayment;

  /// No description provided for @sellerPaymentCod.
  ///
  /// In en, this message translates to:
  /// **'Cash on delivery'**
  String get sellerPaymentCod;

  /// No description provided for @sellerPaymentOnline.
  ///
  /// In en, this message translates to:
  /// **'Online payment'**
  String get sellerPaymentOnline;

  /// No description provided for @sellerTracking.
  ///
  /// In en, this message translates to:
  /// **'Tracking'**
  String get sellerTracking;

  /// No description provided for @sellerTrack.
  ///
  /// In en, this message translates to:
  /// **'Track parcel'**
  String get sellerTrack;

  /// No description provided for @sellerHistory.
  ///
  /// In en, this message translates to:
  /// **'History'**
  String get sellerHistory;

  /// No description provided for @sellerReturnRequest.
  ///
  /// In en, this message translates to:
  /// **'Return request'**
  String get sellerReturnRequest;

  /// No description provided for @sellerRespondBy.
  ///
  /// In en, this message translates to:
  /// **'Respond by {date}'**
  String sellerRespondBy(String date);

  /// No description provided for @sellerPack.
  ///
  /// In en, this message translates to:
  /// **'Pack'**
  String get sellerPack;

  /// No description provided for @sellerShip.
  ///
  /// In en, this message translates to:
  /// **'Ship'**
  String get sellerShip;

  /// No description provided for @sellerMarkDelivered.
  ///
  /// In en, this message translates to:
  /// **'Mark delivered'**
  String get sellerMarkDelivered;

  /// No description provided for @sellerAcceptReturn.
  ///
  /// In en, this message translates to:
  /// **'Accept return'**
  String get sellerAcceptReturn;

  /// No description provided for @sellerDisputeReturn.
  ///
  /// In en, this message translates to:
  /// **'Dispute'**
  String get sellerDisputeReturn;

  /// No description provided for @sellerConfirmReceived.
  ///
  /// In en, this message translates to:
  /// **'Confirm item received'**
  String get sellerConfirmReceived;

  /// No description provided for @sellerShipTitle.
  ///
  /// In en, this message translates to:
  /// **'Ship this order'**
  String get sellerShipTitle;

  /// No description provided for @sellerCourier.
  ///
  /// In en, this message translates to:
  /// **'Courier'**
  String get sellerCourier;

  /// No description provided for @sellerCourierOther.
  ///
  /// In en, this message translates to:
  /// **'Other'**
  String get sellerCourierOther;

  /// No description provided for @sellerCourierName.
  ///
  /// In en, this message translates to:
  /// **'Courier name'**
  String get sellerCourierName;

  /// No description provided for @sellerTrackingNo.
  ///
  /// In en, this message translates to:
  /// **'Tracking number'**
  String get sellerTrackingNo;

  /// No description provided for @sellerTrackingNoLength.
  ///
  /// In en, this message translates to:
  /// **'Enter 6 to 64 characters.'**
  String get sellerTrackingNoLength;

  /// No description provided for @sellerDisputeTitle.
  ///
  /// In en, this message translates to:
  /// **'Dispute this return'**
  String get sellerDisputeTitle;

  /// No description provided for @sellerDisputeReason.
  ///
  /// In en, this message translates to:
  /// **'Reason'**
  String get sellerDisputeReason;

  /// No description provided for @sellerDisputeReasonMin.
  ///
  /// In en, this message translates to:
  /// **'Write at least 10 characters.'**
  String get sellerDisputeReasonMin;

  /// No description provided for @sellerSubmit.
  ///
  /// In en, this message translates to:
  /// **'Submit'**
  String get sellerSubmit;

  /// No description provided for @sellerOrderUpdated.
  ///
  /// In en, this message translates to:
  /// **'Order updated'**
  String get sellerOrderUpdated;

  /// No description provided for @sellerSearchProducts.
  ///
  /// In en, this message translates to:
  /// **'Search products or SKU'**
  String get sellerSearchProducts;

  /// No description provided for @sellerNoProducts.
  ///
  /// In en, this message translates to:
  /// **'No products yet'**
  String get sellerNoProducts;

  /// No description provided for @sellerNoProductsBody.
  ///
  /// In en, this message translates to:
  /// **'Add your first product to start selling.'**
  String get sellerNoProductsBody;

  /// No description provided for @sellerNewProduct.
  ///
  /// In en, this message translates to:
  /// **'New product'**
  String get sellerNewProduct;

  /// No description provided for @sellerEditProduct.
  ///
  /// In en, this message translates to:
  /// **'Edit product'**
  String get sellerEditProduct;

  /// No description provided for @sellerStatusDraft.
  ///
  /// In en, this message translates to:
  /// **'Draft'**
  String get sellerStatusDraft;

  /// No description provided for @sellerStatusPendingReview.
  ///
  /// In en, this message translates to:
  /// **'Pending review'**
  String get sellerStatusPendingReview;

  /// No description provided for @sellerStatusLive.
  ///
  /// In en, this message translates to:
  /// **'Live'**
  String get sellerStatusLive;

  /// No description provided for @sellerStatusDelisted.
  ///
  /// In en, this message translates to:
  /// **'Delisted'**
  String get sellerStatusDelisted;

  /// No description provided for @sellerStatusBanned.
  ///
  /// In en, this message translates to:
  /// **'Banned'**
  String get sellerStatusBanned;

  /// No description provided for @sellerStock.
  ///
  /// In en, this message translates to:
  /// **'Stock'**
  String get sellerStock;

  /// No description provided for @sellerStockCount.
  ///
  /// In en, this message translates to:
  /// **'Stock {count}'**
  String sellerStockCount(int count);

  /// No description provided for @sellerCod.
  ///
  /// In en, this message translates to:
  /// **'COD'**
  String get sellerCod;

  /// No description provided for @sellerNameEn.
  ///
  /// In en, this message translates to:
  /// **'Name (English)'**
  String get sellerNameEn;

  /// No description provided for @sellerNameMs.
  ///
  /// In en, this message translates to:
  /// **'Name (Malay)'**
  String get sellerNameMs;

  /// No description provided for @sellerNameVi.
  ///
  /// In en, this message translates to:
  /// **'Name (Vietnamese)'**
  String get sellerNameVi;

  /// No description provided for @sellerDescriptionEn.
  ///
  /// In en, this message translates to:
  /// **'Description (English)'**
  String get sellerDescriptionEn;

  /// No description provided for @sellerDescriptionMs.
  ///
  /// In en, this message translates to:
  /// **'Description (Malay)'**
  String get sellerDescriptionMs;

  /// No description provided for @sellerDescriptionVi.
  ///
  /// In en, this message translates to:
  /// **'Description (Vietnamese)'**
  String get sellerDescriptionVi;

  /// No description provided for @sellerCategory.
  ///
  /// In en, this message translates to:
  /// **'Category'**
  String get sellerCategory;

  /// No description provided for @sellerChooseCategory.
  ///
  /// In en, this message translates to:
  /// **'Choose a category'**
  String get sellerChooseCategory;

  /// No description provided for @sellerPriceRm.
  ///
  /// In en, this message translates to:
  /// **'Price (RM)'**
  String get sellerPriceRm;

  /// No description provided for @sellerSalePriceRm.
  ///
  /// In en, this message translates to:
  /// **'Sale price (RM)'**
  String get sellerSalePriceRm;

  /// No description provided for @sellerPriceFormat.
  ///
  /// In en, this message translates to:
  /// **'Enter an amount like 12.50.'**
  String get sellerPriceFormat;

  /// No description provided for @sellerSku.
  ///
  /// In en, this message translates to:
  /// **'SKU'**
  String get sellerSku;

  /// No description provided for @sellerWeightG.
  ///
  /// In en, this message translates to:
  /// **'Weight (g)'**
  String get sellerWeightG;

  /// No description provided for @sellerLengthMm.
  ///
  /// In en, this message translates to:
  /// **'Length (mm)'**
  String get sellerLengthMm;

  /// No description provided for @sellerWidthMm.
  ///
  /// In en, this message translates to:
  /// **'Width (mm)'**
  String get sellerWidthMm;

  /// No description provided for @sellerHeightMm.
  ///
  /// In en, this message translates to:
  /// **'Height (mm)'**
  String get sellerHeightMm;

  /// No description provided for @sellerCodEnabled.
  ///
  /// In en, this message translates to:
  /// **'Cash on delivery'**
  String get sellerCodEnabled;

  /// No description provided for @sellerCodEnabledHelp.
  ///
  /// In en, this message translates to:
  /// **'Let buyers pay on delivery for this product.'**
  String get sellerCodEnabledHelp;

  /// No description provided for @sellerVariants.
  ///
  /// In en, this message translates to:
  /// **'Variants'**
  String get sellerVariants;

  /// No description provided for @sellerImages.
  ///
  /// In en, this message translates to:
  /// **'Images'**
  String get sellerImages;

  /// No description provided for @sellerImagesHelp.
  ///
  /// In en, this message translates to:
  /// **'Up to 9 photos. Pick several at once. They show in the order you add them.'**
  String get sellerImagesHelp;

  /// No description provided for @sellerImagesSaveFirst.
  ///
  /// In en, this message translates to:
  /// **'Save the product first to add images.'**
  String get sellerImagesSaveFirst;

  /// No description provided for @sellerImagesFull.
  ///
  /// In en, this message translates to:
  /// **'9 photos is the limit. Remove one to add another.'**
  String get sellerImagesFull;

  /// No description provided for @sellerImagesTooMany.
  ///
  /// In en, this message translates to:
  /// **'Only {count} more photos fit. A product has at most 9.'**
  String sellerImagesTooMany(int count);

  /// No description provided for @sellerDescriptionHasFormatting.
  ///
  /// In en, this message translates to:
  /// **'This description has formatting. Edit it on the web seller centre.'**
  String get sellerDescriptionHasFormatting;

  /// No description provided for @sellerAddImage.
  ///
  /// In en, this message translates to:
  /// **'Add image'**
  String get sellerAddImage;

  /// No description provided for @sellerRemoveImage.
  ///
  /// In en, this message translates to:
  /// **'Remove image'**
  String get sellerRemoveImage;

  /// No description provided for @sellerRemoveImageBody.
  ///
  /// In en, this message translates to:
  /// **'This image will be removed from the listing.'**
  String get sellerRemoveImageBody;

  /// No description provided for @sellerRemove.
  ///
  /// In en, this message translates to:
  /// **'Remove'**
  String get sellerRemove;

  /// No description provided for @sellerGallery.
  ///
  /// In en, this message translates to:
  /// **'Choose from gallery'**
  String get sellerGallery;

  /// No description provided for @sellerCamera.
  ///
  /// In en, this message translates to:
  /// **'Take a photo'**
  String get sellerCamera;

  /// No description provided for @sellerSave.
  ///
  /// In en, this message translates to:
  /// **'Save'**
  String get sellerSave;

  /// No description provided for @sellerSaved.
  ///
  /// In en, this message translates to:
  /// **'Saved'**
  String get sellerSaved;

  /// No description provided for @sellerPublish.
  ///
  /// In en, this message translates to:
  /// **'Publish'**
  String get sellerPublish;

  /// No description provided for @sellerDelist.
  ///
  /// In en, this message translates to:
  /// **'Delist'**
  String get sellerDelist;

  /// No description provided for @sellerRelist.
  ///
  /// In en, this message translates to:
  /// **'Relist'**
  String get sellerRelist;

  /// No description provided for @sellerHalalBlocked.
  ///
  /// In en, this message translates to:
  /// **'Publishing blocked'**
  String get sellerHalalBlocked;

  /// No description provided for @sellerNumberInvalid.
  ///
  /// In en, this message translates to:
  /// **'Enter a whole number.'**
  String get sellerNumberInvalid;

  /// No description provided for @sellerRequestPayout.
  ///
  /// In en, this message translates to:
  /// **'Request payout'**
  String get sellerRequestPayout;

  /// No description provided for @sellerPayoutPending.
  ///
  /// In en, this message translates to:
  /// **'Payout pending'**
  String get sellerPayoutPending;

  /// No description provided for @sellerPayoutMin.
  ///
  /// In en, this message translates to:
  /// **'Minimum payout {amount}'**
  String sellerPayoutMin(String amount);

  /// No description provided for @sellerPayoutMax.
  ///
  /// In en, this message translates to:
  /// **'Up to {amount} available'**
  String sellerPayoutMax(String amount);

  /// No description provided for @sellerPayoutAmountRm.
  ///
  /// In en, this message translates to:
  /// **'Amount (RM)'**
  String get sellerPayoutAmountRm;

  /// No description provided for @sellerPayoutRequested.
  ///
  /// In en, this message translates to:
  /// **'Payout requested'**
  String get sellerPayoutRequested;

  /// No description provided for @sellerPayoutBelowMin.
  ///
  /// In en, this message translates to:
  /// **'Below the minimum payout.'**
  String get sellerPayoutBelowMin;

  /// No description provided for @sellerPayoutAboveAvailable.
  ///
  /// In en, this message translates to:
  /// **'More than the available balance.'**
  String get sellerPayoutAboveAvailable;

  /// No description provided for @sellerLedger.
  ///
  /// In en, this message translates to:
  /// **'Ledger'**
  String get sellerLedger;

  /// No description provided for @sellerNoLedger.
  ///
  /// In en, this message translates to:
  /// **'No activity yet'**
  String get sellerNoLedger;

  /// No description provided for @sellerNoLedgerBody.
  ///
  /// In en, this message translates to:
  /// **'Sales and fees show up here once orders complete.'**
  String get sellerNoLedgerBody;

  /// No description provided for @sellerPayouts.
  ///
  /// In en, this message translates to:
  /// **'Payouts'**
  String get sellerPayouts;

  /// No description provided for @sellerNoPayouts.
  ///
  /// In en, this message translates to:
  /// **'No payouts yet'**
  String get sellerNoPayouts;

  /// No description provided for @sellerLoadMore.
  ///
  /// In en, this message translates to:
  /// **'Load more'**
  String get sellerLoadMore;

  /// No description provided for @sellerStoreDescription.
  ///
  /// In en, this message translates to:
  /// **'Description'**
  String get sellerStoreDescription;

  /// No description provided for @sellerState.
  ///
  /// In en, this message translates to:
  /// **'State'**
  String get sellerState;

  /// No description provided for @sellerHolidayMode.
  ///
  /// In en, this message translates to:
  /// **'Holiday mode'**
  String get sellerHolidayMode;

  /// No description provided for @sellerHolidayModeHelp.
  ///
  /// In en, this message translates to:
  /// **'Hide your products while you are away.'**
  String get sellerHolidayModeHelp;

  /// No description provided for @sellerShipping.
  ///
  /// In en, this message translates to:
  /// **'Shipping'**
  String get sellerShipping;

  /// No description provided for @sellerShippingMatrix.
  ///
  /// In en, this message translates to:
  /// **'Per-state rates are managed in the web seller centre.'**
  String get sellerShippingMatrix;

  /// No description provided for @sellerFlatFeeRm.
  ///
  /// In en, this message translates to:
  /// **'Flat fee (RM)'**
  String get sellerFlatFeeRm;

  /// No description provided for @sellerFreeAboveRm.
  ///
  /// In en, this message translates to:
  /// **'Free shipping above (RM)'**
  String get sellerFreeAboveRm;

  /// No description provided for @sellerBank.
  ///
  /// In en, this message translates to:
  /// **'Bank details'**
  String get sellerBank;

  /// No description provided for @sellerBankName.
  ///
  /// In en, this message translates to:
  /// **'Bank'**
  String get sellerBankName;

  /// No description provided for @sellerAccountName.
  ///
  /// In en, this message translates to:
  /// **'Account name'**
  String get sellerAccountName;

  /// No description provided for @sellerAccountNumber.
  ///
  /// In en, this message translates to:
  /// **'Account number'**
  String get sellerAccountNumber;

  /// No description provided for @sellerAccountNumberHelp.
  ///
  /// In en, this message translates to:
  /// **'Enter the full number to replace {masked}.'**
  String sellerAccountNumberHelp(String masked);

  /// No description provided for @sellerBankHelp.
  ///
  /// In en, this message translates to:
  /// **'Enter the full account number to change bank details.'**
  String get sellerBankHelp;

  /// No description provided for @sellerChangeLogo.
  ///
  /// In en, this message translates to:
  /// **'Change logo'**
  String get sellerChangeLogo;

  /// No description provided for @sellerChangeBanner.
  ///
  /// In en, this message translates to:
  /// **'Change banner'**
  String get sellerChangeBanner;

  /// No description provided for @sellerReviews.
  ///
  /// In en, this message translates to:
  /// **'Reviews'**
  String get sellerReviews;

  /// No description provided for @sellerNoReviews.
  ///
  /// In en, this message translates to:
  /// **'No reviews yet'**
  String get sellerNoReviews;

  /// No description provided for @sellerNoReviewsBody.
  ///
  /// In en, this message translates to:
  /// **'Buyer reviews of your products appear here.'**
  String get sellerNoReviewsBody;

  /// No description provided for @sellerReplyHint.
  ///
  /// In en, this message translates to:
  /// **'Write a reply to this buyer'**
  String get sellerReplyHint;

  /// No description provided for @sellerPostReply.
  ///
  /// In en, this message translates to:
  /// **'Post reply'**
  String get sellerPostReply;

  /// No description provided for @sellerYourReply.
  ///
  /// In en, this message translates to:
  /// **'Your reply'**
  String get sellerYourReply;

  /// No description provided for @sellerReplyMin.
  ///
  /// In en, this message translates to:
  /// **'Write at least 5 characters.'**
  String get sellerReplyMin;

  /// No description provided for @sellerStoreSaved.
  ///
  /// In en, this message translates to:
  /// **'Store saved'**
  String get sellerStoreSaved;

  /// No description provided for @sellerSaveStoreSettings.
  ///
  /// In en, this message translates to:
  /// **'Save store settings'**
  String get sellerSaveStoreSettings;

  /// No description provided for @sellerBankNeedsNumber.
  ///
  /// In en, this message translates to:
  /// **'Re-enter the full account number to change bank details.'**
  String get sellerBankNeedsNumber;

  /// No description provided for @sellerStatusPending.
  ///
  /// In en, this message translates to:
  /// **'Pending'**
  String get sellerStatusPending;

  /// No description provided for @sellerStatusPaid.
  ///
  /// In en, this message translates to:
  /// **'Paid'**
  String get sellerStatusPaid;

  /// No description provided for @sellerStatusFailed.
  ///
  /// In en, this message translates to:
  /// **'Failed'**
  String get sellerStatusFailed;

  /// No description provided for @sellerStatusExpired.
  ///
  /// In en, this message translates to:
  /// **'Expired'**
  String get sellerStatusExpired;

  /// No description provided for @sellerDefaultVariant.
  ///
  /// In en, this message translates to:
  /// **'Default'**
  String get sellerDefaultVariant;

  /// No description provided for @sellerImageTooLarge.
  ///
  /// In en, this message translates to:
  /// **'Choose an image under {kb} KB.'**
  String sellerImageTooLarge(int kb);

  /// No description provided for @sellerAcceptReturnConfirm.
  ///
  /// In en, this message translates to:
  /// **'Accept this return? The buyer will be asked to send the item back.'**
  String get sellerAcceptReturnConfirm;

  /// No description provided for @sellerDisputeConfirm.
  ///
  /// In en, this message translates to:
  /// **'Dispute this return? HalalBizs will review the case with your reason.'**
  String get sellerDisputeConfirm;

  /// No description provided for @sellerDelistConfirm.
  ///
  /// In en, this message translates to:
  /// **'Delist this product? Buyers will no longer see it until you relist it.'**
  String get sellerDelistConfirm;

  /// No description provided for @sellerPayoutConfirm.
  ///
  /// In en, this message translates to:
  /// **'Request a payout of {amount} to your bank account?'**
  String sellerPayoutConfirm(String amount);
}

class _AppLocalizationsDelegate
    extends LocalizationsDelegate<AppLocalizations> {
  const _AppLocalizationsDelegate();

  @override
  Future<AppLocalizations> load(Locale locale) {
    return SynchronousFuture<AppLocalizations>(lookupAppLocalizations(locale));
  }

  @override
  bool isSupported(Locale locale) =>
      <String>['en', 'ms'].contains(locale.languageCode);

  @override
  bool shouldReload(_AppLocalizationsDelegate old) => false;
}

AppLocalizations lookupAppLocalizations(Locale locale) {
  // Lookup logic when only language code is specified.
  switch (locale.languageCode) {
    case 'en':
      return AppLocalizationsEn();
    case 'ms':
      return AppLocalizationsMs();
  }

  throw FlutterError(
    'AppLocalizations.delegate failed to load unsupported locale "$locale". This is likely '
    'an issue with the localizations generation tool. Please file an issue '
    'on GitHub with a reproducible sample app and the gen-l10n configuration '
    'that was used.',
  );
}

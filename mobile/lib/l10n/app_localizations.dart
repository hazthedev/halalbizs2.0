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

  /// No description provided for @homePromotions.
  ///
  /// In en, this message translates to:
  /// **'Promotions'**
  String get homePromotions;

  /// No description provided for @flashSale.
  ///
  /// In en, this message translates to:
  /// **'Flash Sale'**
  String get flashSale;

  /// No description provided for @flashEndsIn.
  ///
  /// In en, this message translates to:
  /// **'Ends in'**
  String get flashEndsIn;

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

  /// No description provided for @filterSort.
  ///
  /// In en, this message translates to:
  /// **'Sort & filter'**
  String get filterSort;

  /// No description provided for @filterPrice.
  ///
  /// In en, this message translates to:
  /// **'Price (RM)'**
  String get filterPrice;

  /// No description provided for @filterMin.
  ///
  /// In en, this message translates to:
  /// **'Min'**
  String get filterMin;

  /// No description provided for @filterMax.
  ///
  /// In en, this message translates to:
  /// **'Max'**
  String get filterMax;

  /// No description provided for @filterReset.
  ///
  /// In en, this message translates to:
  /// **'Reset'**
  String get filterReset;

  /// No description provided for @filterApply.
  ///
  /// In en, this message translates to:
  /// **'Apply'**
  String get filterApply;

  /// No description provided for @filterPriceInvalid.
  ///
  /// In en, this message translates to:
  /// **'Enter a price like 12.50, with Min no higher than Max.'**
  String get filterPriceInvalid;

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

  /// No description provided for @reviews.
  ///
  /// In en, this message translates to:
  /// **'Reviews'**
  String get reviews;

  /// No description provided for @storeAbout.
  ///
  /// In en, this message translates to:
  /// **'About'**
  String get storeAbout;

  /// No description provided for @storeJoined.
  ///
  /// In en, this message translates to:
  /// **'Joined {date}'**
  String storeJoined(String date);

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

  /// No description provided for @cartCheckout.
  ///
  /// In en, this message translates to:
  /// **'Checkout'**
  String get cartCheckout;

  /// No description provided for @cartContinueShopping.
  ///
  /// In en, this message translates to:
  /// **'Continue shopping'**
  String get cartContinueShopping;

  /// No description provided for @cartEmptyTitle.
  ///
  /// In en, this message translates to:
  /// **'Your cart is empty'**
  String get cartEmptyTitle;

  /// No description provided for @cartEmptyBody.
  ///
  /// In en, this message translates to:
  /// **'Find something halal and good — it will wait for you here.'**
  String get cartEmptyBody;

  /// No description provided for @cartItemCount.
  ///
  /// In en, this message translates to:
  /// **'{count} items'**
  String cartItemCount(int count);

  /// No description provided for @cartPurchasingPaused.
  ///
  /// In en, this message translates to:
  /// **'Purchasing is paused — you can browse, but not check out right now.'**
  String get cartPurchasingPaused;

  /// No description provided for @cartRemove.
  ///
  /// In en, this message translates to:
  /// **'Remove'**
  String get cartRemove;

  /// No description provided for @cartSelectAll.
  ///
  /// In en, this message translates to:
  /// **'Select all'**
  String get cartSelectAll;

  /// No description provided for @cartSelectToCheckout.
  ///
  /// In en, this message translates to:
  /// **'Select at least one item to check out.'**
  String get cartSelectToCheckout;

  /// No description provided for @cartSelectedSubtotal.
  ///
  /// In en, this message translates to:
  /// **'Subtotal · {count} selected'**
  String cartSelectedSubtotal(int count);

  /// No description provided for @cartUnavailable.
  ///
  /// In en, this message translates to:
  /// **'Unavailable'**
  String get cartUnavailable;

  /// No description provided for @cartViewCart.
  ///
  /// In en, this message translates to:
  /// **'View cart'**
  String get cartViewCart;

  /// No description provided for @checkoutTitle.
  ///
  /// In en, this message translates to:
  /// **'Checkout'**
  String get checkoutTitle;

  /// No description provided for @checkoutDeliverTo.
  ///
  /// In en, this message translates to:
  /// **'Deliver to'**
  String get checkoutDeliverTo;

  /// No description provided for @checkoutChange.
  ///
  /// In en, this message translates to:
  /// **'Change'**
  String get checkoutChange;

  /// No description provided for @checkoutNoAddress.
  ///
  /// In en, this message translates to:
  /// **'You have no delivery address yet — add one to place your order.'**
  String get checkoutNoAddress;

  /// No description provided for @checkoutChooseMethod.
  ///
  /// In en, this message translates to:
  /// **'Choose an available payment method.'**
  String get checkoutChooseMethod;

  /// No description provided for @checkoutSellerNote.
  ///
  /// In en, this message translates to:
  /// **'Note to seller'**
  String get checkoutSellerNote;

  /// No description provided for @checkoutSellerNoteHint.
  ///
  /// In en, this message translates to:
  /// **'Optional — e.g. leave at the guard house'**
  String get checkoutSellerNoteHint;

  /// No description provided for @checkoutVouchers.
  ///
  /// In en, this message translates to:
  /// **'Vouchers'**
  String get checkoutVouchers;

  /// No description provided for @checkoutPlatformVoucher.
  ///
  /// In en, this message translates to:
  /// **'HalalBizs voucher'**
  String get checkoutPlatformVoucher;

  /// No description provided for @checkoutShopVoucher.
  ///
  /// In en, this message translates to:
  /// **'Shop voucher'**
  String get checkoutShopVoucher;

  /// No description provided for @checkoutVoucherHint.
  ///
  /// In en, this message translates to:
  /// **'Voucher code'**
  String get checkoutVoucherHint;

  /// No description provided for @checkoutVoucherApply.
  ///
  /// In en, this message translates to:
  /// **'Apply'**
  String get checkoutVoucherApply;

  /// No description provided for @checkoutVoucherRemove.
  ///
  /// In en, this message translates to:
  /// **'Remove'**
  String get checkoutVoucherRemove;

  /// No description provided for @checkoutVoucherApplied.
  ///
  /// In en, this message translates to:
  /// **'{code} applied · {amount} off'**
  String checkoutVoucherApplied(String code, String amount);

  /// No description provided for @checkoutUseCoins.
  ///
  /// In en, this message translates to:
  /// **'Use coins (you have {balance})'**
  String checkoutUseCoins(int balance);

  /// No description provided for @checkoutCoinsToUse.
  ///
  /// In en, this message translates to:
  /// **'Coins to use'**
  String get checkoutCoinsToUse;

  /// No description provided for @checkoutCoinsWorth.
  ///
  /// In en, this message translates to:
  /// **'{coins} coins · {amount} off'**
  String checkoutCoinsWorth(int coins, String amount);

  /// No description provided for @checkoutPaymentMethod.
  ///
  /// In en, this message translates to:
  /// **'Payment method'**
  String get checkoutPaymentMethod;

  /// No description provided for @checkoutMethodCod.
  ///
  /// In en, this message translates to:
  /// **'Cash on delivery'**
  String get checkoutMethodCod;

  /// No description provided for @checkoutMethodIpay88.
  ///
  /// In en, this message translates to:
  /// **'Online payment (FPX, card, e-wallet)'**
  String get checkoutMethodIpay88;

  /// No description provided for @checkoutMethodUnavailable.
  ///
  /// In en, this message translates to:
  /// **'Not available for this order.'**
  String get checkoutMethodUnavailable;

  /// No description provided for @checkoutSummary.
  ///
  /// In en, this message translates to:
  /// **'Summary'**
  String get checkoutSummary;

  /// No description provided for @checkoutSubtotal.
  ///
  /// In en, this message translates to:
  /// **'Subtotal'**
  String get checkoutSubtotal;

  /// No description provided for @checkoutShipping.
  ///
  /// In en, this message translates to:
  /// **'Shipping'**
  String get checkoutShipping;

  /// No description provided for @checkoutShippingPending.
  ///
  /// In en, this message translates to:
  /// **'Choose an address'**
  String get checkoutShippingPending;

  /// No description provided for @checkoutShippingDiscount.
  ///
  /// In en, this message translates to:
  /// **'Shipping discount'**
  String get checkoutShippingDiscount;

  /// No description provided for @checkoutPlatformDiscount.
  ///
  /// In en, this message translates to:
  /// **'HalalBizs voucher'**
  String get checkoutPlatformDiscount;

  /// No description provided for @checkoutShopDiscount.
  ///
  /// In en, this message translates to:
  /// **'Shop discount'**
  String get checkoutShopDiscount;

  /// No description provided for @checkoutTax.
  ///
  /// In en, this message translates to:
  /// **'Tax'**
  String get checkoutTax;

  /// No description provided for @checkoutGrandTotal.
  ///
  /// In en, this message translates to:
  /// **'Grand total'**
  String get checkoutGrandTotal;

  /// No description provided for @checkoutPlaceOrder.
  ///
  /// In en, this message translates to:
  /// **'Place order'**
  String get checkoutPlaceOrder;

  /// No description provided for @checkoutDoneTitle.
  ///
  /// In en, this message translates to:
  /// **'Order placed'**
  String get checkoutDoneTitle;

  /// No description provided for @checkoutDoneBody.
  ///
  /// In en, this message translates to:
  /// **'Order {orderNo} is confirmed. Your orders are here:'**
  String checkoutDoneBody(String orderNo);

  /// No description provided for @checkoutViewOrders.
  ///
  /// In en, this message translates to:
  /// **'View orders'**
  String get checkoutViewOrders;

  /// No description provided for @checkoutPayTitle.
  ///
  /// In en, this message translates to:
  /// **'Payment'**
  String get checkoutPayTitle;

  /// No description provided for @checkoutPayBrowserTitle.
  ///
  /// In en, this message translates to:
  /// **'Complete payment in your browser'**
  String get checkoutPayBrowserTitle;

  /// No description provided for @checkoutPayBrowserBody.
  ///
  /// In en, this message translates to:
  /// **'Order {orderNo} opened in your browser. Come back here when you are done — we check the payment automatically.'**
  String checkoutPayBrowserBody(String orderNo);

  /// No description provided for @checkoutPayExpiresAt.
  ///
  /// In en, this message translates to:
  /// **'Pay before {time}'**
  String checkoutPayExpiresAt(String time);

  /// No description provided for @checkoutPayIHavePaid.
  ///
  /// In en, this message translates to:
  /// **'I have paid'**
  String get checkoutPayIHavePaid;

  /// No description provided for @checkoutPayOpenAgain.
  ///
  /// In en, this message translates to:
  /// **'Open payment page again'**
  String get checkoutPayOpenAgain;

  /// No description provided for @checkoutPayRetry.
  ///
  /// In en, this message translates to:
  /// **'Retry payment'**
  String get checkoutPayRetry;

  /// No description provided for @checkoutPayExpiredTitle.
  ///
  /// In en, this message translates to:
  /// **'Payment window expired'**
  String get checkoutPayExpiredTitle;

  /// No description provided for @checkoutPayExpiredBody.
  ///
  /// In en, this message translates to:
  /// **'This order was not paid in time. Nothing was charged.'**
  String get checkoutPayExpiredBody;

  /// No description provided for @ordersAll.
  ///
  /// In en, this message translates to:
  /// **'All'**
  String get ordersAll;

  /// No description provided for @ordersOrder.
  ///
  /// In en, this message translates to:
  /// **'Order'**
  String get ordersOrder;

  /// No description provided for @ordersTotal.
  ///
  /// In en, this message translates to:
  /// **'Total'**
  String get ordersTotal;

  /// No description provided for @ordersMoreItems.
  ///
  /// In en, this message translates to:
  /// **'+{count} more'**
  String ordersMoreItems(int count);

  /// No description provided for @ordersPlacedAt.
  ///
  /// In en, this message translates to:
  /// **'Placed {date}'**
  String ordersPlacedAt(String date);

  /// No description provided for @ordersEmptyTitle.
  ///
  /// In en, this message translates to:
  /// **'No orders yet'**
  String get ordersEmptyTitle;

  /// No description provided for @ordersEmptyBody.
  ///
  /// In en, this message translates to:
  /// **'Your orders appear here once you check out.'**
  String get ordersEmptyBody;

  /// No description provided for @ordersEmptyBucketTitle.
  ///
  /// In en, this message translates to:
  /// **'No orders in this status'**
  String get ordersEmptyBucketTitle;

  /// No description provided for @ordersEmptyBucketBody.
  ///
  /// In en, this message translates to:
  /// **'Pick another tab to see your other orders.'**
  String get ordersEmptyBucketBody;

  /// No description provided for @ordersLoadMore.
  ///
  /// In en, this message translates to:
  /// **'Load more'**
  String get ordersLoadMore;

  /// No description provided for @ordersTracking.
  ///
  /// In en, this message translates to:
  /// **'Tracking'**
  String get ordersTracking;

  /// No description provided for @ordersTrack.
  ///
  /// In en, this message translates to:
  /// **'Track'**
  String get ordersTrack;

  /// No description provided for @ordersReturnRequest.
  ///
  /// In en, this message translates to:
  /// **'Return request'**
  String get ordersReturnRequest;

  /// No description provided for @ordersCancelOrder.
  ///
  /// In en, this message translates to:
  /// **'Cancel order'**
  String get ordersCancelOrder;

  /// No description provided for @ordersCancelTitle.
  ///
  /// In en, this message translates to:
  /// **'Cancel this order?'**
  String get ordersCancelTitle;

  /// No description provided for @ordersCancelBody.
  ///
  /// In en, this message translates to:
  /// **'Nothing will be charged and the items go back in stock.'**
  String get ordersCancelBody;

  /// No description provided for @ordersCancelConfirm.
  ///
  /// In en, this message translates to:
  /// **'Cancel order'**
  String get ordersCancelConfirm;

  /// No description provided for @ordersKeep.
  ///
  /// In en, this message translates to:
  /// **'Keep order'**
  String get ordersKeep;

  /// No description provided for @ordersCancelled.
  ///
  /// In en, this message translates to:
  /// **'Order cancelled'**
  String get ordersCancelled;

  /// No description provided for @ordersReason.
  ///
  /// In en, this message translates to:
  /// **'Reason'**
  String get ordersReason;

  /// No description provided for @ordersReasonHint.
  ///
  /// In en, this message translates to:
  /// **'Choose a reason'**
  String get ordersReasonHint;

  /// No description provided for @ordersConfirmReceived.
  ///
  /// In en, this message translates to:
  /// **'Confirm received'**
  String get ordersConfirmReceived;

  /// No description provided for @ordersReceivedTitle.
  ///
  /// In en, this message translates to:
  /// **'Received your order?'**
  String get ordersReceivedTitle;

  /// No description provided for @ordersReceivedBody.
  ///
  /// In en, this message translates to:
  /// **'Confirm only once everything has arrived.'**
  String get ordersReceivedBody;

  /// No description provided for @ordersReceivedDone.
  ///
  /// In en, this message translates to:
  /// **'Order received'**
  String get ordersReceivedDone;

  /// No description provided for @ordersRequestReturn.
  ///
  /// In en, this message translates to:
  /// **'Request return'**
  String get ordersRequestReturn;

  /// No description provided for @ordersNote.
  ///
  /// In en, this message translates to:
  /// **'Note'**
  String get ordersNote;

  /// No description provided for @ordersReturnNoteHint.
  ///
  /// In en, this message translates to:
  /// **'What went wrong?'**
  String get ordersReturnNoteHint;

  /// No description provided for @ordersPhotos.
  ///
  /// In en, this message translates to:
  /// **'Photos'**
  String get ordersPhotos;

  /// No description provided for @ordersPhotosHelp.
  ///
  /// In en, this message translates to:
  /// **'Up to {max} photos.'**
  String ordersPhotosHelp(int max);

  /// No description provided for @ordersReturnConfirmTitle.
  ///
  /// In en, this message translates to:
  /// **'Submit this return request?'**
  String get ordersReturnConfirmTitle;

  /// No description provided for @ordersReturnConfirmBody.
  ///
  /// In en, this message translates to:
  /// **'The seller will be notified.'**
  String get ordersReturnConfirmBody;

  /// No description provided for @ordersReturnSubmit.
  ///
  /// In en, this message translates to:
  /// **'Submit return request'**
  String get ordersReturnSubmit;

  /// No description provided for @ordersReturnSubmitted.
  ///
  /// In en, this message translates to:
  /// **'Return requested'**
  String get ordersReturnSubmitted;

  /// No description provided for @ordersWriteReview.
  ///
  /// In en, this message translates to:
  /// **'Write a review'**
  String get ordersWriteReview;

  /// No description provided for @ordersReviewBody.
  ///
  /// In en, this message translates to:
  /// **'Your review'**
  String get ordersReviewBody;

  /// No description provided for @ordersReviewBodyHint.
  ///
  /// In en, this message translates to:
  /// **'Optional — at least 10 characters'**
  String get ordersReviewBodyHint;

  /// No description provided for @ordersSellerRating.
  ///
  /// In en, this message translates to:
  /// **'Seller rating'**
  String get ordersSellerRating;

  /// No description provided for @ordersSellerComment.
  ///
  /// In en, this message translates to:
  /// **'Comment'**
  String get ordersSellerComment;

  /// No description provided for @ordersOptional.
  ///
  /// In en, this message translates to:
  /// **'Optional'**
  String get ordersOptional;

  /// No description provided for @ordersRateEveryItem.
  ///
  /// In en, this message translates to:
  /// **'Rate every item before submitting.'**
  String get ordersRateEveryItem;

  /// No description provided for @ordersReviewSubmit.
  ///
  /// In en, this message translates to:
  /// **'Submit review'**
  String get ordersReviewSubmit;

  /// No description provided for @ordersReviewSubmitted.
  ///
  /// In en, this message translates to:
  /// **'Thank you for your review.'**
  String get ordersReviewSubmitted;

  /// No description provided for @ordersNothingToReview.
  ///
  /// In en, this message translates to:
  /// **'Nothing left to review'**
  String get ordersNothingToReview;

  /// No description provided for @accountSave.
  ///
  /// In en, this message translates to:
  /// **'Save'**
  String get accountSave;

  /// No description provided for @accountDelete.
  ///
  /// In en, this message translates to:
  /// **'Delete'**
  String get accountDelete;

  /// No description provided for @accountDefault.
  ///
  /// In en, this message translates to:
  /// **'Default'**
  String get accountDefault;

  /// No description provided for @accountPhone.
  ///
  /// In en, this message translates to:
  /// **'Phone'**
  String get accountPhone;

  /// No description provided for @accountPreferredLanguage.
  ///
  /// In en, this message translates to:
  /// **'Preferred language'**
  String get accountPreferredLanguage;

  /// No description provided for @accountEmailUnverified.
  ///
  /// In en, this message translates to:
  /// **'Email not verified yet.'**
  String get accountEmailUnverified;

  /// No description provided for @accountProfileSaved.
  ///
  /// In en, this message translates to:
  /// **'Profile updated'**
  String get accountProfileSaved;

  /// No description provided for @accountChangePassword.
  ///
  /// In en, this message translates to:
  /// **'Change password'**
  String get accountChangePassword;

  /// No description provided for @accountCurrentPassword.
  ///
  /// In en, this message translates to:
  /// **'Current password'**
  String get accountCurrentPassword;

  /// No description provided for @accountNewPassword.
  ///
  /// In en, this message translates to:
  /// **'New password'**
  String get accountNewPassword;

  /// No description provided for @accountPasswordChanged.
  ///
  /// In en, this message translates to:
  /// **'Password updated'**
  String get accountPasswordChanged;

  /// No description provided for @accountAddAddress.
  ///
  /// In en, this message translates to:
  /// **'Add address'**
  String get accountAddAddress;

  /// No description provided for @accountEditAddress.
  ///
  /// In en, this message translates to:
  /// **'Edit address'**
  String get accountEditAddress;

  /// No description provided for @accountNoAddresses.
  ///
  /// In en, this message translates to:
  /// **'No addresses yet'**
  String get accountNoAddresses;

  /// No description provided for @accountNoAddressesBody.
  ///
  /// In en, this message translates to:
  /// **'Add a delivery address to check out faster.'**
  String get accountNoAddressesBody;

  /// No description provided for @accountAddressLabel.
  ///
  /// In en, this message translates to:
  /// **'Label'**
  String get accountAddressLabel;

  /// No description provided for @accountAddressLabelHint.
  ///
  /// In en, this message translates to:
  /// **'Home, Office…'**
  String get accountAddressLabelHint;

  /// No description provided for @accountRecipientName.
  ///
  /// In en, this message translates to:
  /// **'Recipient name'**
  String get accountRecipientName;

  /// No description provided for @accountLine1.
  ///
  /// In en, this message translates to:
  /// **'Address line 1'**
  String get accountLine1;

  /// No description provided for @accountLine2.
  ///
  /// In en, this message translates to:
  /// **'Address line 2'**
  String get accountLine2;

  /// No description provided for @accountPostcode.
  ///
  /// In en, this message translates to:
  /// **'Postcode'**
  String get accountPostcode;

  /// No description provided for @accountCity.
  ///
  /// In en, this message translates to:
  /// **'City'**
  String get accountCity;

  /// No description provided for @accountState.
  ///
  /// In en, this message translates to:
  /// **'State'**
  String get accountState;

  /// No description provided for @accountStateHint.
  ///
  /// In en, this message translates to:
  /// **'Choose a state'**
  String get accountStateHint;

  /// No description provided for @accountSetDefault.
  ///
  /// In en, this message translates to:
  /// **'Set as default'**
  String get accountSetDefault;

  /// No description provided for @accountAddressSaved.
  ///
  /// In en, this message translates to:
  /// **'Address saved'**
  String get accountAddressSaved;

  /// No description provided for @accountDeleteAddressTitle.
  ///
  /// In en, this message translates to:
  /// **'Delete this address?'**
  String get accountDeleteAddressTitle;

  /// No description provided for @accountDeleteAddressBody.
  ///
  /// In en, this message translates to:
  /// **'You can add it again any time.'**
  String get accountDeleteAddressBody;

  /// No description provided for @accountWishlistEmpty.
  ///
  /// In en, this message translates to:
  /// **'Nothing saved yet'**
  String get accountWishlistEmpty;

  /// No description provided for @accountWishlistEmptyBody.
  ///
  /// In en, this message translates to:
  /// **'Tap the heart on a product to keep it here.'**
  String get accountWishlistEmptyBody;

  /// No description provided for @accountRemoveFromWishlist.
  ///
  /// In en, this message translates to:
  /// **'Remove from wishlist'**
  String get accountRemoveFromWishlist;

  /// No description provided for @productAddToWishlist.
  ///
  /// In en, this message translates to:
  /// **'Add to wishlist'**
  String get productAddToWishlist;

  /// No description provided for @accountCoinsBalance.
  ///
  /// In en, this message translates to:
  /// **'Balance'**
  String get accountCoinsBalance;

  /// No description provided for @accountCoinsStreak.
  ///
  /// In en, this message translates to:
  /// **'Streak'**
  String get accountCoinsStreak;

  /// No description provided for @accountCoinsDays.
  ///
  /// In en, this message translates to:
  /// **'{days} days'**
  String accountCoinsDays(int days);

  /// No description provided for @accountCoinsLifetime.
  ///
  /// In en, this message translates to:
  /// **'{coins} coins earned in total'**
  String accountCoinsLifetime(int coins);

  /// No description provided for @accountCoinsExpiring.
  ///
  /// In en, this message translates to:
  /// **'Some coins expire {date}'**
  String accountCoinsExpiring(String date);

  /// No description provided for @accountCheckIn.
  ///
  /// In en, this message translates to:
  /// **'Check in today'**
  String get accountCheckIn;

  /// No description provided for @accountCheckedIn.
  ///
  /// In en, this message translates to:
  /// **'Checked in today'**
  String get accountCheckedIn;

  /// No description provided for @accountCheckedInReason.
  ///
  /// In en, this message translates to:
  /// **'You have already checked in today.'**
  String get accountCheckedInReason;

  /// No description provided for @accountCheckInBody.
  ///
  /// In en, this message translates to:
  /// **'Check in every day to grow your streak and earn more coins.'**
  String get accountCheckInBody;

  /// No description provided for @accountCheckInDone.
  ///
  /// In en, this message translates to:
  /// **'+{coins} coins · day {streak} streak'**
  String accountCheckInDone(int coins, int streak);

  /// No description provided for @accountCoinsHistory.
  ///
  /// In en, this message translates to:
  /// **'History'**
  String get accountCoinsHistory;

  /// No description provided for @accountCoinsNoHistory.
  ///
  /// In en, this message translates to:
  /// **'No coin activity yet'**
  String get accountCoinsNoHistory;

  /// No description provided for @accountCoinsUnavailable.
  ///
  /// In en, this message translates to:
  /// **'Coins are not available'**
  String get accountCoinsUnavailable;

  /// No description provided for @accountCoinsUnavailableBody.
  ///
  /// In en, this message translates to:
  /// **'Daily check-in is unavailable right now.'**
  String get accountCoinsUnavailableBody;

  /// No description provided for @accountMarkAllRead.
  ///
  /// In en, this message translates to:
  /// **'Mark all read'**
  String get accountMarkAllRead;

  /// No description provided for @accountNoNotifications.
  ///
  /// In en, this message translates to:
  /// **'No notifications'**
  String get accountNoNotifications;

  /// No description provided for @accountNoNotificationsBody.
  ///
  /// In en, this message translates to:
  /// **'Order updates and messages from sellers show up here.'**
  String get accountNoNotificationsBody;

  /// No description provided for @accountDeleteWarning.
  ///
  /// In en, this message translates to:
  /// **'This cannot be undone.'**
  String get accountDeleteWarning;

  /// No description provided for @accountDeleteBody.
  ///
  /// In en, this message translates to:
  /// **'Your personal details are anonymized immediately; order records stay for legal and financial reasons. Enter your password to continue.'**
  String get accountDeleteBody;

  /// No description provided for @accountDeleteConfirmTitle.
  ///
  /// In en, this message translates to:
  /// **'Delete your account?'**
  String get accountDeleteConfirmTitle;

  /// No description provided for @accountDeleteConfirmBody.
  ///
  /// In en, this message translates to:
  /// **'You will be signed out and cannot sign in again with this account.'**
  String get accountDeleteConfirmBody;

  /// No description provided for @messagesChatWithSeller.
  ///
  /// In en, this message translates to:
  /// **'Chat with seller'**
  String get messagesChatWithSeller;

  /// No description provided for @messagesEmpty.
  ///
  /// In en, this message translates to:
  /// **'No messages yet'**
  String get messagesEmpty;

  /// No description provided for @messagesEmptyBody.
  ///
  /// In en, this message translates to:
  /// **'Start a chat from any product page.'**
  String get messagesEmptyBody;

  /// No description provided for @messagesNoMessagesYet.
  ///
  /// In en, this message translates to:
  /// **'No messages in this conversation yet.'**
  String get messagesNoMessagesYet;

  /// No description provided for @messagesSayHello.
  ///
  /// In en, this message translates to:
  /// **'Say hello to the seller.'**
  String get messagesSayHello;

  /// No description provided for @messagesTypeMessage.
  ///
  /// In en, this message translates to:
  /// **'Type a message'**
  String get messagesTypeMessage;

  /// No description provided for @messagesSend.
  ///
  /// In en, this message translates to:
  /// **'Send'**
  String get messagesSend;

  /// No description provided for @checkoutPlacedCheckOrders.
  ///
  /// In en, this message translates to:
  /// **'Your order was placed. Check your orders.'**
  String get checkoutPlacedCheckOrders;

  /// No description provided for @checkoutMaybePlaced.
  ///
  /// In en, this message translates to:
  /// **'We could not confirm whether the order went through. Check your orders before trying again.'**
  String get checkoutMaybePlaced;

  /// No description provided for @checkoutPayLinkMissing.
  ///
  /// In en, this message translates to:
  /// **'The order was placed but no payment link came back. Pay it from your orders.'**
  String get checkoutPayLinkMissing;

  /// No description provided for @accountWishlistStillSaved.
  ///
  /// In en, this message translates to:
  /// **'Still in your wishlist — try again.'**
  String get accountWishlistStillSaved;

  /// No description provided for @accountUnread.
  ///
  /// In en, this message translates to:
  /// **'Unread'**
  String get accountUnread;

  /// No description provided for @ordersReviewStars.
  ///
  /// In en, this message translates to:
  /// **'{n, plural, =1{1 star} other{{n} stars}}'**
  String ordersReviewStars(int n);

  /// No description provided for @ordersPaymentStatusPending.
  ///
  /// In en, this message translates to:
  /// **'Pending'**
  String get ordersPaymentStatusPending;

  /// No description provided for @ordersPaymentStatusPaid.
  ///
  /// In en, this message translates to:
  /// **'Paid'**
  String get ordersPaymentStatusPaid;

  /// No description provided for @ordersPaymentStatusFailed.
  ///
  /// In en, this message translates to:
  /// **'Failed'**
  String get ordersPaymentStatusFailed;

  /// No description provided for @ordersPaymentStatusExpired.
  ///
  /// In en, this message translates to:
  /// **'Expired'**
  String get ordersPaymentStatusExpired;

  /// No description provided for @ordersPaymentStatusRefunded.
  ///
  /// In en, this message translates to:
  /// **'Refunded'**
  String get ordersPaymentStatusRefunded;

  /// No description provided for @ordersReturnStatusRequested.
  ///
  /// In en, this message translates to:
  /// **'Requested'**
  String get ordersReturnStatusRequested;

  /// No description provided for @ordersReturnStatusAccepted.
  ///
  /// In en, this message translates to:
  /// **'Accepted'**
  String get ordersReturnStatusAccepted;

  /// No description provided for @ordersReturnStatusDisputed.
  ///
  /// In en, this message translates to:
  /// **'Disputed'**
  String get ordersReturnStatusDisputed;

  /// No description provided for @ordersReturnStatusEscalated.
  ///
  /// In en, this message translates to:
  /// **'Escalated'**
  String get ordersReturnStatusEscalated;

  /// No description provided for @ordersReturnStatusRefunded.
  ///
  /// In en, this message translates to:
  /// **'Refunded'**
  String get ordersReturnStatusRefunded;

  /// No description provided for @ordersReturnStatusRejected.
  ///
  /// In en, this message translates to:
  /// **'Rejected'**
  String get ordersReturnStatusRejected;

  /// No description provided for @checkoutDonePaid.
  ///
  /// In en, this message translates to:
  /// **'Paid {amount} (payment {orderNo}).'**
  String checkoutDonePaid(String amount, String orderNo);

  /// No description provided for @checkoutDoneCod.
  ///
  /// In en, this message translates to:
  /// **'Total {amount}, paid on delivery (payment {orderNo}).'**
  String checkoutDoneCod(String amount, String orderNo);

  /// No description provided for @checkoutDoneSplit.
  ///
  /// In en, this message translates to:
  /// **'{count, plural, =1{It ships as one shipment.} other{Your order is split into {count} shipments, one per shop.}}'**
  String checkoutDoneSplit(int count);

  /// No description provided for @ordersOrderNo.
  ///
  /// In en, this message translates to:
  /// **'Order {no}'**
  String ordersOrderNo(String no);

  /// No description provided for @ordersPaymentNo.
  ///
  /// In en, this message translates to:
  /// **'Payment {no}'**
  String ordersPaymentNo(String no);

  /// No description provided for @productSelect.
  ///
  /// In en, this message translates to:
  /// **'Select: {options}'**
  String productSelect(String options);

  /// No description provided for @productChoose.
  ///
  /// In en, this message translates to:
  /// **'Choose a {option}'**
  String productChoose(String option);

  /// No description provided for @productStock.
  ///
  /// In en, this message translates to:
  /// **'Stock: {count}'**
  String productStock(int count);

  /// No description provided for @productViewImage.
  ///
  /// In en, this message translates to:
  /// **'View image'**
  String get productViewImage;

  /// No description provided for @close.
  ///
  /// In en, this message translates to:
  /// **'Close'**
  String get close;

  /// No description provided for @checkoutVoucherBest.
  ///
  /// In en, this message translates to:
  /// **'BEST VALUE'**
  String get checkoutVoucherBest;

  /// No description provided for @checkoutVoucherAppliedTag.
  ///
  /// In en, this message translates to:
  /// **'APPLIED'**
  String get checkoutVoucherAppliedTag;

  /// No description provided for @checkoutVoucherFreeShipping.
  ///
  /// In en, this message translates to:
  /// **'Free shipping'**
  String get checkoutVoucherFreeShipping;

  /// No description provided for @checkoutVoucherShort.
  ///
  /// In en, this message translates to:
  /// **'Add {amount} more to use this voucher.'**
  String checkoutVoucherShort(String amount);

  /// No description provided for @checkoutVoucherOff.
  ///
  /// In en, this message translates to:
  /// **'{amount} off'**
  String checkoutVoucherOff(String amount);

  /// No description provided for @ordersPayNow.
  ///
  /// In en, this message translates to:
  /// **'Pay now'**
  String get ordersPayNow;

  /// No description provided for @ordersBuyAgain.
  ///
  /// In en, this message translates to:
  /// **'Buy again'**
  String get ordersBuyAgain;

  /// No description provided for @ordersBuyAgainDone.
  ///
  /// In en, this message translates to:
  /// **'{added, plural, =1{1 item} other{{added} items}} added to cart'**
  String ordersBuyAgainDone(int added);

  /// No description provided for @ordersBuyAgainSkipped.
  ///
  /// In en, this message translates to:
  /// **'{added, plural, =1{1 item} other{{added} items}} added to cart · {skipped, plural, =1{1 item} other{{skipped} items}} skipped (not available)'**
  String ordersBuyAgainSkipped(int added, int skipped);

  /// No description provided for @ordersBuyAgainNone.
  ///
  /// In en, this message translates to:
  /// **'None of these items are available any more.'**
  String get ordersBuyAgainNone;

  /// No description provided for @halalCertified.
  ///
  /// In en, this message translates to:
  /// **'Halal certified'**
  String get halalCertified;

  /// No description provided for @halalCertVerified.
  ///
  /// In en, this message translates to:
  /// **'Halal certificate verified'**
  String get halalCertVerified;

  /// No description provided for @halalCertLapsed.
  ///
  /// In en, this message translates to:
  /// **'Certificate lapsed'**
  String get halalCertLapsed;

  /// No description provided for @halalCertNotInForce.
  ///
  /// In en, this message translates to:
  /// **'Certificate not yet in force'**
  String get halalCertNotInForce;

  /// No description provided for @halalExpiredOn.
  ///
  /// In en, this message translates to:
  /// **'expired {date}'**
  String halalExpiredOn(String date);

  /// No description provided for @halalValidFrom.
  ///
  /// In en, this message translates to:
  /// **'valid from {date}'**
  String halalValidFrom(String date);

  /// No description provided for @halalValidTo.
  ///
  /// In en, this message translates to:
  /// **'valid to {date}'**
  String halalValidTo(String date);

  /// No description provided for @halalReadRecord.
  ///
  /// In en, this message translates to:
  /// **'Read the record'**
  String get halalReadRecord;

  /// No description provided for @halalScopeTitle.
  ///
  /// In en, this message translates to:
  /// **'Ingredients & scope'**
  String get halalScopeTitle;

  /// No description provided for @halalScopeBody.
  ///
  /// In en, this message translates to:
  /// **'Certified scope: {scope}. The certificate names this SKU in its annex, so the badge is bound to the item you are buying, not to the shop that sells it.'**
  String halalScopeBody(String scope);

  /// No description provided for @halalTraceability.
  ///
  /// In en, this message translates to:
  /// **'Traceability'**
  String get halalTraceability;

  /// No description provided for @halalFacility.
  ///
  /// In en, this message translates to:
  /// **'Facility'**
  String get halalFacility;

  /// No description provided for @halalBatch.
  ///
  /// In en, this message translates to:
  /// **'Batch'**
  String get halalBatch;

  /// No description provided for @halalPackedOn.
  ///
  /// In en, this message translates to:
  /// **'packed {date}'**
  String halalPackedOn(String date);

  /// No description provided for @halalCertificate.
  ///
  /// In en, this message translates to:
  /// **'Certificate'**
  String get halalCertificate;

  /// No description provided for @halalExpiry.
  ///
  /// In en, this message translates to:
  /// **'Expiry'**
  String get halalExpiry;

  /// No description provided for @halalLapsed.
  ///
  /// In en, this message translates to:
  /// **'lapsed'**
  String get halalLapsed;

  /// No description provided for @halalNotInForceUntil.
  ///
  /// In en, this message translates to:
  /// **'not in force until {date}'**
  String halalNotInForceUntil(String date);

  /// No description provided for @halalDaysRemaining.
  ///
  /// In en, this message translates to:
  /// **'{count, plural, =1{1 day remaining} other{{count} days remaining}}'**
  String halalDaysRemaining(int count);

  /// No description provided for @halalCertifyingBody.
  ///
  /// In en, this message translates to:
  /// **'Certifying body'**
  String get halalCertifyingBody;

  /// No description provided for @halalAssurance.
  ///
  /// In en, this message translates to:
  /// **'Assurance'**
  String get halalAssurance;

  /// No description provided for @halalAssureValid12.
  ///
  /// In en, this message translates to:
  /// **'Certificate valid 12 months+'**
  String get halalAssureValid12;

  /// No description provided for @halalAssureDedicated.
  ///
  /// In en, this message translates to:
  /// **'Dedicated halal facility'**
  String get halalAssureDedicated;

  /// No description provided for @halalAssureExport.
  ///
  /// In en, this message translates to:
  /// **'Export paperwork included'**
  String get halalAssureExport;

  /// No description provided for @productBuyNow.
  ///
  /// In en, this message translates to:
  /// **'Buy now'**
  String get productBuyNow;

  /// No description provided for @productCod.
  ///
  /// In en, this message translates to:
  /// **'Cash on delivery'**
  String get productCod;

  /// No description provided for @productShipsFrom.
  ///
  /// In en, this message translates to:
  /// **'Ships from {state}'**
  String productShipsFrom(String state);

  /// No description provided for @productShippingAtCheckout.
  ///
  /// In en, this message translates to:
  /// **'Shipping calculated at checkout'**
  String get productShippingAtCheckout;

  /// No description provided for @productTabSpecs.
  ///
  /// In en, this message translates to:
  /// **'Specifications'**
  String get productTabSpecs;

  /// No description provided for @productTabQa.
  ///
  /// In en, this message translates to:
  /// **'Q&A'**
  String get productTabQa;

  /// No description provided for @productSpecBrand.
  ///
  /// In en, this message translates to:
  /// **'Brand'**
  String get productSpecBrand;

  /// No description provided for @productSpecCondition.
  ///
  /// In en, this message translates to:
  /// **'Condition'**
  String get productSpecCondition;

  /// No description provided for @productSpecWeight.
  ///
  /// In en, this message translates to:
  /// **'Weight'**
  String get productSpecWeight;

  /// No description provided for @productSpecCategory.
  ///
  /// In en, this message translates to:
  /// **'Category'**
  String get productSpecCategory;

  /// No description provided for @productNoBrand.
  ///
  /// In en, this message translates to:
  /// **'No brand'**
  String get productNoBrand;

  /// No description provided for @productNoReviews.
  ///
  /// In en, this message translates to:
  /// **'No reviews yet.'**
  String get productNoReviews;

  /// No description provided for @productVerified.
  ///
  /// In en, this message translates to:
  /// **'Verified'**
  String get productVerified;

  /// No description provided for @productSellerService.
  ///
  /// In en, this message translates to:
  /// **'Seller service'**
  String get productSellerService;

  /// No description provided for @qaTitle.
  ///
  /// In en, this message translates to:
  /// **'Questions & Answers ({count})'**
  String qaTitle(int count);

  /// No description provided for @qaAskLabel.
  ///
  /// In en, this message translates to:
  /// **'Ask the seller a question'**
  String get qaAskLabel;

  /// No description provided for @qaAskHint.
  ///
  /// In en, this message translates to:
  /// **'e.g. Is this halal-certified? Does it ship to East Malaysia?'**
  String get qaAskHint;

  /// No description provided for @qaPost.
  ///
  /// In en, this message translates to:
  /// **'Post question'**
  String get qaPost;

  /// No description provided for @qaLogin.
  ///
  /// In en, this message translates to:
  /// **'Log in'**
  String get qaLogin;

  /// No description provided for @qaLoginToAsk.
  ///
  /// In en, this message translates to:
  /// **'to ask the seller a question.'**
  String get qaLoginToAsk;

  /// No description provided for @qaEmpty.
  ///
  /// In en, this message translates to:
  /// **'No questions yet — be the first to ask.'**
  String get qaEmpty;

  /// No description provided for @qaAwaiting.
  ///
  /// In en, this message translates to:
  /// **'Awaiting seller response'**
  String get qaAwaiting;

  /// No description provided for @qaSeller.
  ///
  /// In en, this message translates to:
  /// **'Seller'**
  String get qaSeller;

  /// No description provided for @qaTypeFirst.
  ///
  /// In en, this message translates to:
  /// **'Type your question first.'**
  String get qaTypeFirst;

  /// No description provided for @qaTooShort.
  ///
  /// In en, this message translates to:
  /// **'Add a little more detail (at least 10 characters).'**
  String get qaTooShort;

  /// No description provided for @qaSent.
  ///
  /// In en, this message translates to:
  /// **'Question sent — we\'ll notify you when the seller answers.'**
  String get qaSent;

  /// No description provided for @recommendedForYou.
  ///
  /// In en, this message translates to:
  /// **'Recommended for you'**
  String get recommendedForYou;

  /// No description provided for @relatedVerified.
  ///
  /// In en, this message translates to:
  /// **'Verified alongside it'**
  String get relatedVerified;

  /// No description provided for @follow.
  ///
  /// In en, this message translates to:
  /// **'Follow'**
  String get follow;

  /// No description provided for @following.
  ///
  /// In en, this message translates to:
  /// **'Following'**
  String get following;

  /// No description provided for @followersCount.
  ///
  /// In en, this message translates to:
  /// **'{count} followers'**
  String followersCount(int count);

  /// No description provided for @followedShops.
  ///
  /// In en, this message translates to:
  /// **'Followed shops'**
  String get followedShops;

  /// No description provided for @followedShopsEmptyTitle.
  ///
  /// In en, this message translates to:
  /// **'No followed shops yet'**
  String get followedShopsEmptyTitle;

  /// No description provided for @followedShopsEmptyBody.
  ///
  /// In en, this message translates to:
  /// **'Follow a shop to see it here.'**
  String get followedShopsEmptyBody;

  /// No description provided for @reviewsFilterAll.
  ///
  /// In en, this message translates to:
  /// **'All'**
  String get reviewsFilterAll;

  /// No description provided for @reviewsFilterPhotos.
  ///
  /// In en, this message translates to:
  /// **'With photos'**
  String get reviewsFilterPhotos;

  /// No description provided for @reviewsFilterEmpty.
  ///
  /// In en, this message translates to:
  /// **'No reviews match this filter yet.'**
  String get reviewsFilterEmpty;

  /// No description provided for @reviewsSellerResponse.
  ///
  /// In en, this message translates to:
  /// **'Seller response'**
  String get reviewsSellerResponse;

  /// No description provided for @legalAndAbout.
  ///
  /// In en, this message translates to:
  /// **'Legal & about'**
  String get legalAndAbout;

  /// No description provided for @legalAboutUs.
  ///
  /// In en, this message translates to:
  /// **'About us'**
  String get legalAboutUs;

  /// No description provided for @legalTrustSafety.
  ///
  /// In en, this message translates to:
  /// **'Trust & safety'**
  String get legalTrustSafety;

  /// No description provided for @legalFaq.
  ///
  /// In en, this message translates to:
  /// **'FAQ'**
  String get legalFaq;

  /// No description provided for @legalRefundPolicy.
  ///
  /// In en, this message translates to:
  /// **'Refund policy'**
  String get legalRefundPolicy;

  /// No description provided for @legalTerms.
  ///
  /// In en, this message translates to:
  /// **'Terms & conditions'**
  String get legalTerms;

  /// No description provided for @legalPrivacy.
  ///
  /// In en, this message translates to:
  /// **'Privacy policy'**
  String get legalPrivacy;

  /// No description provided for @helpAndSupport.
  ///
  /// In en, this message translates to:
  /// **'Help & support'**
  String get helpAndSupport;

  /// No description provided for @ordersDownloadInvoice.
  ///
  /// In en, this message translates to:
  /// **'Download invoice'**
  String get ordersDownloadInvoice;

  /// No description provided for @sellerHalalCertificates.
  ///
  /// In en, this message translates to:
  /// **'Halal certificates'**
  String get sellerHalalCertificates;

  /// No description provided for @sellerVouchers.
  ///
  /// In en, this message translates to:
  /// **'Vouchers'**
  String get sellerVouchers;
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

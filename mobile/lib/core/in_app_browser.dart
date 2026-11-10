import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:url_launcher/url_launcher.dart';

/// The in-app browser sheet (Android Custom Tab / iOS SFSafariViewController)
/// for the web pages the app hands off to: payment and seller sign-up.
///
/// Closing: iOS dismisses the sheet through [close]. Android cannot close a
/// Custom Tab from code; there MainActivity's `singleTask` launch mode clears
/// it when the `halalbizs://` deep link brings the app back.
class InAppBrowser {
  Future<void> open(String url) async {
    try {
      await launchUrl(Uri.parse(url), mode: LaunchMode.inAppBrowserView);
    } catch (_) {
      // No browser (tests, odd devices): the screen keeps its retry button.
    }
  }

  Future<void> close() async {
    try {
      if (await supportsCloseForLaunchMode(LaunchMode.inAppBrowserView)) await closeInAppWebView();
    } catch (_) {}
  }
}

final inAppBrowserProvider = Provider<InAppBrowser>((ref) => InAppBrowser());

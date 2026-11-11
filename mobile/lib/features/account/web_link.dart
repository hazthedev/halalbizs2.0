import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../core/in_app_browser.dart';
import '../../core/widgets/souk_kit.dart';
import 'account_api.dart';

/// Opens an allowlisted web page already signed in: asks the API for a
/// one-time link (`to` = a destination the server knows, `id` for an invoice)
/// and shows it in the in-app browser sheet.
Future<void> openWebPage(BuildContext context, WidgetRef ref, String to, {int? id}) async {
  try {
    final url = await ref.read(accountApiProvider).webSignInLink(to, id: id);
    await ref.read(inAppBrowserProvider).open(url);
  } catch (e) {
    if (context.mounted) showApiError(context, e);
  }
}

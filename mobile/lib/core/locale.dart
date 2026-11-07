import 'dart:ui' show PlatformDispatcher;

import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import 'db/app_db.dart';

/// Locales the app ships (`lib/l10n/app_*.arb`). `vi` arrives later.
const kSupportedLocales = [Locale('en'), Locale('ms')];

/// UI locale, persisted in drift `kv` under `ui.locale`. Default = the device
/// locale when it is en/ms, else en. Also sent as `Accept-Language`.
class LocaleController extends Notifier<Locale> {
  static const kvKey = 'ui.locale';

  @override
  Locale build() {
    Future.microtask(_restore);
    return _deviceDefault();
  }

  static Locale _deviceDefault() {
    final code = PlatformDispatcher.instance.locale.languageCode;
    return kSupportedLocales.any((l) => l.languageCode == code) ? Locale(code) : const Locale('en');
  }

  Future<void> _restore() async {
    try {
      final saved = await ref.read(appDbProvider).kvGet(kvKey);
      if (saved != null && kSupportedLocales.any((l) => l.languageCode == saved)) {
        state = Locale(saved);
      }
    } catch (_) {
      // No database (web without the sqlite worker, tests): keep the default.
    }
  }

  Future<void> set(Locale locale) async {
    state = locale;
    try {
      await ref.read(appDbProvider).kvPut(kvKey, locale.languageCode);
    } catch (_) {}
  }
}

final localeProvider = NotifierProvider<LocaleController, Locale>(LocaleController.new);

/// `X-Display-Currency` header. MYR only until the currency picker lands.
final displayCurrencyProvider = Provider<String>((ref) => 'MYR');

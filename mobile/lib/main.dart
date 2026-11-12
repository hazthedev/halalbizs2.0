import 'package:flutter/material.dart';
import 'package:flutter_localizations/flutter_localizations.dart';
import 'package:flutter_native_splash/flutter_native_splash.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import 'core/api/api_client.dart';
import 'core/auth/session.dart';
import 'core/locale.dart';
import 'core/router.dart';
import 'core/version_gate.dart';
import 'core/widgets/souk_kit.dart';
import 'l10n/l10n.dart';

void main() {
  assertApiBaseUrl();
  // One splash only: the native one stays up until the session restores
  // (Haze 11-11: two logos, big then small, looked like a glitch).
  FlutterNativeSplash.preserve(widgetsBinding: WidgetsFlutterBinding.ensureInitialized());
  runApp(const ProviderScope(retry: soukRetry, child: HalalBizsApp()));
}

class HalalBizsApp extends ConsumerWidget {
  const HalalBizsApp({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    return MaterialApp.router(
      title: 'HalalBizs',
      debugShowCheckedModeBanner: false,
      theme: soukTheme(),
      locale: ref.watch(localeProvider),
      supportedLocales: kSupportedLocales,
      localizationsDelegates: const [
        AppLocalizations.delegate,
        GlobalMaterialLocalizations.delegate,
        GlobalWidgetsLocalizations.delegate,
        GlobalCupertinoLocalizations.delegate,
      ],
      routerConfig: ref.watch(routerProvider),
      builder: (context, child) => _StartupGate(child: child!),
    );
  }
}

/// Holds the native splash over the router while the stored session restores
/// (token → `GET me`, or the cached profile offline), so a guarded tab never
/// flashes before the redirect lands; removes it the moment the session resolves.
class _StartupGate extends ConsumerWidget {
  const _StartupGate({required this.child});

  final Widget child;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final loading = ref.watch(sessionProvider.select((s) => s is SessionLoading));
    if (loading) return const ColoredBox(color: SoukColors.paper);
    FlutterNativeSplash.remove();
    return VersionGate(child: child);
  }
}

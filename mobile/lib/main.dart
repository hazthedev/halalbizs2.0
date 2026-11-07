import 'package:flutter/material.dart';
import 'package:flutter_localizations/flutter_localizations.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import 'core/api/api_client.dart';
import 'core/auth/session.dart';
import 'core/locale.dart';
import 'core/router.dart';
import 'core/widgets/souk_kit.dart';
import 'l10n/l10n.dart';

void main() {
  WidgetsFlutterBinding.ensureInitialized();
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

/// Covers the router with a paper splash while the stored session restores
/// (token → `GET me`, or the cached profile offline), so a guarded tab never
/// flashes before the redirect lands. Cross-fades out the moment it resolves.
class _StartupGate extends ConsumerWidget {
  const _StartupGate({required this.child});

  final Widget child;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final loading = ref.watch(sessionProvider.select((s) => s is SessionLoading));
    return AnimatedSwitcher(
      duration: const Duration(milliseconds: 220),
      switchInCurve: Curves.easeOut,
      child: loading
          ? const ColoredBox(
              key: ValueKey('souk-splash'),
              color: SoukColors.paper,
              child: Center(child: KhatamMark(size: 64)),
            )
          : KeyedSubtree(key: const ValueKey('souk-app'), child: child),
    );
  }
}

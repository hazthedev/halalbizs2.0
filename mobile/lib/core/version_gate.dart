import 'package:flutter/foundation.dart';
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:package_info_plus/package_info_plus.dart';

import '../l10n/l10n.dart';
import 'api/json.dart';
import 'in_app_browser.dart';
import 'offline/offline.dart';
import 'widgets/souk_kit.dart';

final installedVersionProvider = FutureProvider<String>((ref) async => (await PackageInfo.fromPlatform()).version);

/// `1.10.0` is above `1.9.0`: compare the three numbers, not the strings.
bool isBelow(String have, String min) {
  List<int> parts(String v) => [...v.split('+').first.split('.').map((x) => int.tryParse(x) ?? 0), 0, 0, 0];
  final (a, b) = (parts(have), parts(min));
  for (var i = 0; i < 3; i++) {
    if (a[i] != b[i]) return a[i] < b[i];
  }
  return false;
}

typedef AppGate = ({bool outdated, String? maintenance, String? storeUrl});

/// What `GET config` says about this install. Never makes the first frame wait
/// on the network: it emits the saved config at once (null = none saved = let
/// the user in; offline must never lock people out), then the fresh answer. A
/// failed refresh keeps whatever was last emitted, so a saved "too old" still
/// blocks offline, and a fresh "too old" blocks as soon as it lands.
final appGateProvider = StreamProvider<AppGate?>((ref) async* {
  final repo = ref.watch(offlineRepoProvider);
  Future<AppGate?> read(dynamic data) async {
    try {
      final cfg = asMap(data);
      final min = asStr(cfg['min_app_version']) ?? '';
      final maintenance = asMap(cfg['maintenance']);
      final urls = asMap(cfg['urls']);
      return (
        outdated: min.isNotEmpty && isBelow(await ref.watch(installedVersionProvider.future), min),
        maintenance: asBool(maintenance['enabled']) ? asStr(maintenance['message']) ?? '' : null,
        storeUrl: asStr(urls[defaultTargetPlatform == TargetPlatform.iOS ? 'store_ios' : 'store_android']),
      );
    } catch (_) {
      return null;
    }
  }

  final saved = await repo.saved('config');
  yield saved == null ? null : await read(saved.data);
  try {
    yield await read((await repo.getJson('config')).data);
  } catch (_) {
    // keep the saved answer
  }
});

/// Blocks the app behind "Update required" or the maintenance message.
class VersionGate extends ConsumerWidget {
  const VersionGate({super.key, required this.child});

  final Widget child;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final gate = ref.watch(appGateProvider);
    final g = gate.value;
    // First read only: a re-read (language switch) keeps the app on screen.
    if (!gate.hasValue) return const ColoredBox(color: SoukColors.paper);
    if (g == null || (!g.outdated && g.maintenance == null)) return child;
    final l = context.l10n;
    return Scaffold(
      body: SafeArea(
        child: Center(
          child: g.maintenance != null
              ? SoukEmptyState(
                  key: const Key('gate-maintenance'),
                  title: l.maintenanceTitle,
                  message: g.maintenance!.isEmpty ? null : g.maintenance,
                  action: SoukButton(label: l.tryAgain, onPressed: () => ref.invalidate(appGateProvider)),
                )
              : SoukEmptyState(
                  key: const Key('gate-update'),
                  title: l.updateRequiredTitle,
                  message: l.updateRequiredBody,
                  action: g.storeUrl == null
                      ? null
                      : SoukButton(
                          key: const Key('gate-update-store'),
                          label: l.updateNow,
                          variant: SoukButtonVariant.primary,
                          onPressed: () => ref.read(inAppBrowserProvider).openExternal(g.storeUrl!),
                        ),
                ),
        ),
      ),
    );
  }
}

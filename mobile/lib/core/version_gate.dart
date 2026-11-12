import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:package_info_plus/package_info_plus.dart';

import '../l10n/l10n.dart';
import 'api/json.dart';
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

/// What `GET config` says about this install. Config unreachable with no saved
/// copy = null = let the user in (offline must never lock people out); a slow
/// network waits 3 s at most.
final appGateProvider = FutureProvider<({bool outdated, String? maintenance})?>((ref) async {
  try {
    final cfg = asMap((await ref.watch(offlineRepoProvider).getJson('config').timeout(const Duration(seconds: 3))).data);
    final min = asStr(cfg['min_app_version']) ?? '';
    final maintenance = asMap(cfg['maintenance']);
    return (
      outdated: min.isNotEmpty && isBelow(await ref.watch(installedVersionProvider.future), min),
      maintenance: asBool(maintenance['enabled']) ? asStr(maintenance['message']) ?? '' : null,
    );
  } catch (_) {
    return null;
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
    if (gate.isLoading) return const ColoredBox(color: SoukColors.paper);
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
              : SoukEmptyState(key: const Key('gate-update'), title: l.updateRequiredTitle, message: l.updateRequiredBody),
        ),
      ),
    );
  }
}

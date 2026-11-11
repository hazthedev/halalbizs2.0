import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../core/in_app_browser.dart';
import '../../core/widgets/souk_kit.dart';
import '../../l10n/l10n.dart';
import '../account/legal_screen.dart' show webPageUrl;
import 'catalog_api.dart';
import 'models.dart';

/// Room page on the web (public; no sign-in link needed).
String liveRoomUrl(String slug) => webPageUrl('/live/${Uri.encodeComponent(slug)}');

/// Small red pill with a dot, the web LIVE badge. Tap opens [slug]'s room in
/// the in-app browser (the app has no room of its own).
class LiveBadge extends ConsumerWidget {
  const LiveBadge(this.slug, {super.key});

  final String slug;

  @override
  Widget build(BuildContext context, WidgetRef ref) => GestureDetector(
    behavior: HitTestBehavior.opaque,
    onTap: () => ref.read(inAppBrowserProvider).open(liveRoomUrl(slug)),
    child: Semantics(
      button: true,
      label: context.l10n.liveBadge,
      child: Container(
        padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 3),
        decoration: BoxDecoration(color: SoukColors.danger, borderRadius: BorderRadius.circular(SoukRadius.pill)),
        child: Row(mainAxisSize: MainAxisSize.min, children: [
          Container(width: 6, height: 6, decoration: const BoxDecoration(color: Colors.white, shape: BoxShape.circle)),
          const SizedBox(width: 5),
          Text(context.l10n.liveBadge.toUpperCase(), style: SoukText.small.copyWith(color: Colors.white, fontSize: 10.5, fontWeight: FontWeight.w700, letterSpacing: .5)),
        ]),
      ),
    ),
  );
}

/// "Live" chip next to Sort & filter: renders nothing unless at least one
/// session is live. Tap lists them; a row opens that room in the browser.
class LivePill extends ConsumerWidget {
  const LivePill({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final sessions = ref.watch(liveSessionsProvider).value ?? const [];
    if (sessions.isEmpty) return const SizedBox.shrink();
    return InkWell(
      key: const Key('live-pill'),
      borderRadius: BorderRadius.circular(SoukRadius.pill),
      onTap: () => showModalBottomSheet<void>(
        context: context,
        useSafeArea: true,
        builder: (_) => _LiveSheet(sessions),
      ),
      child: ConstrainedBox(
        constraints: const BoxConstraints(minHeight: SoukSpace.minTap),
        child: const Center(widthFactor: 1, child: _PillBody()),
      ),
    );
  }
}

class _PillBody extends StatelessWidget {
  const _PillBody();

  @override
  Widget build(BuildContext context) => Container(
    padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 8),
    decoration: BoxDecoration(color: SoukColors.danger, borderRadius: BorderRadius.circular(SoukRadius.pill)),
    child: Row(mainAxisSize: MainAxisSize.min, children: [
      Container(width: 7, height: 7, decoration: const BoxDecoration(color: Colors.white, shape: BoxShape.circle)),
      const SizedBox(width: 6),
      Text(context.l10n.liveBadge, style: SoukText.small.copyWith(color: Colors.white, fontWeight: FontWeight.w600)),
    ]),
  );
}

class _LiveSheet extends ConsumerWidget {
  const _LiveSheet(this.sessions);

  final List<LiveSession> sessions;

  @override
  Widget build(BuildContext context, WidgetRef ref) => ListView(
    key: const Key('live-sheet'),
    shrinkWrap: true,
    padding: const EdgeInsets.all(SoukSpace.page),
    children: [
      Text(context.l10n.liveNowTitle, style: SoukText.displayH3),
      const SizedBox(height: SoukSpace.s3),
      for (final s in sessions)
        ListTile(
          key: Key('live-session-${s.slug}'),
          contentPadding: EdgeInsets.zero,
          leading: SizedBox(width: 64, child: AspectRatio(aspectRatio: 16 / 9, child: ProductImage(s.thumbnail, radius: SoukRadius.control))),
          title: Text(s.title, maxLines: 2, overflow: TextOverflow.ellipsis, style: SoukText.body),
          subtitle: Text(s.storeName, style: SoukText.meta),
          onTap: () {
            ref.read(inAppBrowserProvider).open(liveRoomUrl(s.slug));
            Navigator.of(context).pop();
          },
        ),
    ],
  );
}

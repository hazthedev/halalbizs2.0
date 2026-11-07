import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import 'package:intl/intl.dart';

import '../../../l10n/l10n.dart';
import '../../offline/offline.dart';
import '../../theme/souk_theme.dart';
import 'empty_state.dart';

/// Page scaffold for every screen: slim nav bar (serif title, optional
/// leading back chevron, khatam mark on tab roots), the offline strip, an
/// optional cached-data strip, the body (scrollable + pull-to-refresh when
/// asked) and a pinned [bottomBar].
///
///   SoukPage(title: 'Search', scrollable: true, onRefresh: _refresh, body: …)
class SoukPage extends StatelessWidget {
  const SoukPage({
    super.key,
    required this.title,
    required this.body,
    this.actions = const [],
    this.scrollable = false,
    this.onRefresh,
    this.bottomBar,
    this.leading,
    this.showBack,
    this.brandMark = false,
    this.cachedAt,
    this.fromCache = false,
    this.padding,
    this.titleWidget,
  });

  final String title;
  final Widget body;
  final List<Widget> actions;

  /// Body scrolls in a `ListView` (key `souk-page-scroll`) so short pages can
  /// still be pulled to refresh.
  final bool scrollable;
  final Future<void> Function()? onRefresh;

  /// Pinned under the body (sticky action bar).
  final Widget? bottomBar;
  final Widget? leading;

  /// Force the back chevron on/off; default = whether the route can pop.
  final bool? showBack;

  /// Khatam brand mark before the title (tab roots).
  final bool brandMark;

  /// Snapshot time when [fromCache]: shows the "cached" strip.
  final DateTime? cachedAt;
  final bool fromCache;
  final EdgeInsetsGeometry? padding;

  /// Replaces the title text (e.g. the search field).
  final Widget? titleWidget;

  @override
  Widget build(BuildContext context) {
    final router = GoRouter.maybeOf(context);
    final canPop = showBack ?? (router?.canPop() ?? Navigator.of(context).canPop());
    final pad = padding ?? const EdgeInsets.fromLTRB(SoukSpace.page, SoukSpace.s3, SoukSpace.page, SoukSpace.s6);

    Widget content = scrollable
        ? ListView(
            key: const Key('souk-page-scroll'),
            physics: const AlwaysScrollableScrollPhysics(),
            padding: pad,
            children: [body],
          )
        : Padding(padding: pad, child: body);
    if (scrollable && onRefresh != null) {
      content = RefreshIndicator(onRefresh: onRefresh!, color: SoukColors.emerald, child: content);
    }

    return Scaffold(
      backgroundColor: SoukColors.paper,
      appBar: AppBar(
        leading: leading ??
            (canPop
                ? IconButton(
                    key: const Key('souk-back'),
                    tooltip: context.l10n.back,
                    icon: const Icon(Icons.arrow_back_ios_new_rounded, size: 18),
                    onPressed: () => router != null && router.canPop() ? router.pop() : Navigator.of(context).maybePop(),
                  )
                : (brandMark
                    ? const Padding(
                        padding: EdgeInsets.only(left: SoukSpace.s4),
                        child: Center(child: KhatamMark(size: 26)),
                      )
                    : null)),
        leadingWidth: brandMark && !canPop && leading == null ? 48 : null,
        automaticallyImplyLeading: false,
        titleSpacing: canPop || brandMark || leading != null ? 4 : SoukSpace.page,
        title: titleWidget ?? Text(title, maxLines: 1, overflow: TextOverflow.ellipsis),
        actions: [...actions, if (actions.isNotEmpty) const SizedBox(width: SoukSpace.s2)],
      ),
      body: Column(children: [
        const SoukOfflineStrip(),
        if (fromCache) SoukCachedStrip(fetchedAt: cachedAt),
        Expanded(child: content),
      ]),
      bottomNavigationBar: bottomBar == null
          ? null
          : SafeArea(
              top: false,
              child: Container(
                padding: const EdgeInsets.fromLTRB(SoukSpace.page, SoukSpace.s3, SoukSpace.page, SoukSpace.s3),
                decoration: const BoxDecoration(
                  color: SoukColors.surface,
                  border: Border(top: BorderSide(color: SoukColors.line)),
                ),
                child: bottomBar,
              ),
            ),
    );
  }
}

/// Full-width ink strip while `isOfflineProvider` is true. Renders nothing
/// otherwise (and without a ProviderScope).
class SoukOfflineStrip extends StatelessWidget {
  const SoukOfflineStrip({super.key});

  @override
  Widget build(BuildContext context) {
    try {
      ProviderScope.containerOf(context, listen: false);
    } catch (_) {
      return const SizedBox.shrink();
    }
    return Consumer(builder: (context, ref, _) {
      final offline = ref.watch(isOfflineProvider).value ?? false;
      if (!offline) return const SizedBox.shrink();
      return Semantics(
        container: true,
        liveRegion: true,
        child: Container(
          key: const Key('souk-offline-strip'),
          width: double.infinity,
          color: SoukColors.emeraldNight,
          padding: const EdgeInsets.symmetric(horizontal: SoukSpace.page, vertical: SoukSpace.s2),
          child: Row(children: [
            const Icon(Icons.wifi_off_rounded, size: 16, color: SoukColors.onDark),
            const SizedBox(width: SoukSpace.s2),
            Expanded(
              child: Text(context.l10n.offlineStrip,
                  style: const TextStyle(fontSize: 13, fontWeight: FontWeight.w600, color: SoukColors.onDark)),
            ),
          ]),
        ),
      );
    });
  }
}

/// "just now" / "12 min old" / "2 h old" / "17 Oct 14:05" (≥ 1 day), in the
/// app locale.
String soukCachedAge(BuildContext context, DateTime fetchedAt, {DateTime? now}) {
  final l = context.l10n;
  final d = (now ?? DateTime.now()).difference(fetchedAt);
  if (d.isNegative || d.inMinutes < 1) return l.justNow;
  if (d.inMinutes < 60) return l.minutesOld(d.inMinutes);
  if (d.inHours < 24) return l.hoursOld(d.inHours);
  return DateFormat('d MMM HH:mm', Localizations.localeOf(context).toString()).format(fetchedAt);
}

/// Warm strip under the nav bar while the page shows a snapshot.
class SoukCachedStrip extends StatelessWidget {
  const SoukCachedStrip({super.key, this.fetchedAt});

  final DateTime? fetchedAt;

  @override
  Widget build(BuildContext context) => Container(
        key: const Key('souk-cached-strip'),
        width: double.infinity,
        color: SoukColors.warnTint,
        padding: const EdgeInsets.symmetric(horizontal: SoukSpace.page, vertical: SoukSpace.s2),
        child: Row(children: [
          const Icon(Icons.history_rounded, size: 16, color: SoukColors.warn),
          const SizedBox(width: SoukSpace.s2),
          Expanded(
            child: Text(
              '${context.l10n.cachedStrip}${fetchedAt == null ? '' : ' · ${soukCachedAge(context, fetchedAt!)}'}',
              style: const TextStyle(fontSize: 13, fontWeight: FontWeight.w600, color: SoukColors.warn),
            ),
          ),
        ]),
      );
}

/// Small "cached · 5 min old" chip for inside a card / list header.
class CachedChip extends StatelessWidget {
  const CachedChip({super.key, this.fetchedAt});

  final DateTime? fetchedAt;

  @override
  Widget build(BuildContext context) => Container(
        key: const Key('souk-cached-chip'),
        padding: const EdgeInsets.symmetric(horizontal: 9, vertical: 3),
        decoration: BoxDecoration(
          color: SoukColors.warnTint,
          borderRadius: BorderRadius.circular(SoukRadius.pill),
        ),
        child: Text(
          '${context.l10n.cachedChip}${fetchedAt == null ? '' : ' · ${soukCachedAge(context, fetchedAt!)}'}',
          style: const TextStyle(fontSize: 11.5, fontWeight: FontWeight.w600, color: SoukColors.warn),
        ),
      );
}

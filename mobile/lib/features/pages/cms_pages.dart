import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:font_awesome_flutter/font_awesome_flutter.dart';
import 'package:flutter_widget_from_html_core/flutter_widget_from_html_core.dart';
import 'package:go_router/go_router.dart';

import '../../core/api/json.dart';
import '../../core/offline/offline.dart';
import '../../core/widgets/souk_kit.dart';
import '../../l10n/l10n.dart';
import '../account/web_link.dart';
import '../preview/preview_mode.dart';

/// One read for every admin-written screen (`pages`, `pages/{slug}`, `help`,
/// `help/{id}`): through [OfflineRepo], so the last copy shows offline.
final cmsProvider = FutureProvider.autoDispose.family<CachedResult<dynamic>, String>(
    (ref, path) =>
        ref.watch(offlineRepoProvider).getJson(path, query: previewQuery(ref.watch(previewModeProvider))));

/// List page: title + rows + the cached strip, shared by Legal and Help.
class _CmsPage extends ConsumerWidget {
  const _CmsPage({required this.path, required this.title, required this.builder});

  final String path;
  final String? title;
  final Widget Function(BuildContext, dynamic data) builder;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final async = ref.watch(cmsProvider(path));
    return SoukPage(
      title: title ?? '',
      scrollable: true,
      onRefresh: () async => ref.refresh(cmsProvider(path).future).then((_) {}, onError: (_) {}),
      fromCache: async.value?.fromCache ?? false,
      cachedAt: async.value?.fetchedAt,
      body: Column(crossAxisAlignment: CrossAxisAlignment.stretch, children: [
        const PreviewStrip(),
        async.when(
          skipLoadingOnRefresh: true,
          loading: () => const SoukSkeleton.block(height: 160),
          error: (e, _) => ErrorPane(error: e, onRetry: () => ref.invalidate(cmsProvider(path))),
          data: (r) => builder(context, r.data),
        ),
      ]),
    );
  }
}

/// `/page/:slug` and `/help/:id`: title + the admin's HTML (p, br, h2, h3, ul, ol, li, strong, em, a).
class CmsDocScreen extends ConsumerWidget {
  const CmsDocScreen({super.key, required this.path});

  final String path;

  @override
  Widget build(BuildContext context, WidgetRef ref) => _CmsPage(
        path: path,
        title: asStr(asMap(ref.watch(cmsProvider(path)).value?.data)['title']),
        // Core package only: links render as text, never open.
        builder: (_, d) => HtmlWidget(asStr(asMap(d)['body']) ?? '', key: const Key('cms-body'), textStyle: SoukText.bodySoft),
      );
}

/// `/account/legal`: every page the admin has switched on.
class LegalScreen extends ConsumerWidget {
  const LegalScreen({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final l = context.l10n;
    return _CmsPage(
      path: 'pages',
      title: l.legalAndAbout,
      builder: (_, d) {
        final pages = asMapList(d);
        if (pages.isEmpty) return SoukEmptyState(title: l.pagesEmpty);
        return SoukCard(
          padding: EdgeInsets.zero,
          child: Column(children: [
            for (final p in pages)
              SoukRow(
                key: Key('legal-${p['slug']}'),
                icon: FontAwesomeIcons.fileLines,
                label: asStr(p['title']) ?? '',
                onTap: () => context.push('/page/${Uri.encodeComponent('${p['slug']}')}'),
              ),
          ]),
        );
      },
    );
  }
}

/// `/help`: articles grouped by category (the API already orders them), then
/// one "Contact support" row (the web sign-in link, as before).
class HelpScreen extends ConsumerWidget {
  const HelpScreen({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final l = context.l10n;
    return _CmsPage(
      path: 'help',
      title: l.helpAndSupport,
      builder: (_, d) {
        final groups = <String, List<Map<String, dynamic>>>{};
        for (final a in asMapList(d)) {
          (groups[asStr(a['category_label']) ?? ''] ??= []).add(a);
        }
        return Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
          if (groups.isEmpty) SoukEmptyState(title: l.helpEmpty),
          for (final MapEntry(:key, :value) in groups.entries) ...[
            if (key.isNotEmpty) SoukSectionHeading(key),
            const SizedBox(height: SoukSpace.s2),
            SoukCard(
              padding: EdgeInsets.zero,
              child: Column(children: [
                for (final a in value)
                  SoukRow(
                    key: Key('help-${a['id']}'),
                    label: asStr(a['title']) ?? '',
                    onTap: () => context.push('/help/${a['id']}'),
                  ),
              ]),
            ),
            const SizedBox(height: SoukSpace.s4),
          ],
          SoukCard(
            padding: EdgeInsets.zero,
            child: SoukRow(
              key: const Key('help-contact'),
              icon: FontAwesomeIcons.headset,
              label: l.contactSupport,
              onTap: () => openWebPage(context, ref, 'support'),
            ),
          ),
        ]);
      },
    );
  }
}

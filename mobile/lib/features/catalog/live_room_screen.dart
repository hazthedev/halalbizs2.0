import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import 'package:url_launcher/url_launcher.dart';
import 'package:webview_flutter/webview_flutter.dart';

import '../../core/widgets/souk_kit.dart';
import '../../l10n/l10n.dart';
import 'catalog_api.dart';
import 'models.dart';

/// Builds the in-app player for an embed URL. A provider so widget tests can
/// swap the platform view for a stub.
typedef LiveVideoBuilder = Widget Function(BuildContext context, String embedUrl);

final liveVideoBuilderProvider = Provider<LiveVideoBuilder>((ref) => (_, url) => LiveWebView(url));

/// Hosts the player may stay on; anything else opens outside the app.
bool _playerHost(String host) =>
    const ['youtube.com', 'youtube-nocookie.com', 'facebook.com'].any((h) => host == h || host.endsWith('.$h'));

const _platformNames = {'shopee': 'Shopee', 'tiktok': 'TikTok', 'lazada': 'Lazada'};

/// `/live/:slug` — Shopee-style room: player on top, "Shop the stream" below.
class LiveRoomScreen extends ConsumerWidget {
  const LiveRoomScreen({super.key, required this.slug});

  final String slug;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final l = context.l10n;
    final room = ref.watch(liveRoomProvider(slug));
    return SoukPage(
      title: room.value?.title ?? l.liveBadge,
      scrollable: true,
      onRefresh: () async => ref.invalidate(liveRoomProvider(slug)),
      body: room.when(
        loading: () => const Padding(padding: EdgeInsets.all(SoukSpace.s6), child: Center(child: CircularProgressIndicator())),
        // 404 (unknown slug / live feature off) and any other failure: friendly, no crash.
        error: (_, _) => _ended(context),
        data: (r) => r.isLive ? _room(context, ref, r) : _ended(context),
      ),
    );
  }

  Widget _ended(BuildContext context) => SoukEmptyState(
    key: const Key('live-ended'),
    title: context.l10n.liveEndedTitle,
    message: context.l10n.liveEndedBody,
    action: SoukButton(label: context.l10n.backToHome, onPressed: () => context.go('/home')),
  );

  Widget _room(BuildContext context, WidgetRef ref, LiveRoom r) {
    final l = context.l10n;
    final embed = r.embedUrl;
    final external = r.externalUrl;
    final name = _platformNames[r.platform] ?? '';
    return Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
      AspectRatio(
        aspectRatio: 16 / 9,
        child: embed != null
            ? ref.watch(liveVideoBuilderProvider)(context, embed)
            : Container(
                key: const Key('live-external'),
                color: SoukColors.ink,
                alignment: Alignment.center,
                padding: const EdgeInsets.all(SoukSpace.s4),
                child: Column(mainAxisSize: MainAxisSize.min, children: [
                  Text(l.liveOnPlatform(name), style: SoukText.body.copyWith(color: Colors.white), textAlign: TextAlign.center),
                  if (external != null) ...[
                    const SizedBox(height: SoukSpace.s3),
                    SoukButton(
                      key: const Key('live-watch-on'),
                      label: l.liveWatchOn(name),
                      onPressed: () => launchUrl(Uri.parse(external), mode: LaunchMode.externalApplication),
                    ),
                  ],
                ]),
              ),
      ),
      Padding(
        padding: const EdgeInsets.symmetric(vertical: SoukSpace.s4),
        child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
          Text(r.title, key: const Key('live-title'), style: SoukText.displayH3),
          if (r.storeSlug != null)
            InkWell(
              key: const Key('live-store'),
              onTap: () => context.push('/s/${Uri.encodeComponent(r.storeSlug!)}'),
              child: ConstrainedBox(
                constraints: const BoxConstraints(minHeight: SoukSpace.minTap),
                child: Align(alignment: Alignment.centerLeft, child: Text(r.storeName, style: SoukText.body.copyWith(color: SoukColors.emerald))),
              ),
            ),
        ]),
      ),
      if (r.products.isNotEmpty) ...[
        Text(l.liveShopTheStream, style: SoukText.displayH3),
        const SizedBox(height: SoukSpace.s3),
        ProductGrid(r.products, soldLabel: l.sold, onTap: (p) => context.push('/p/${Uri.encodeComponent(p.slug)}')),
      ],
    ]);
  }
}

/// The player: the embed URL in a WebView, kept on YouTube/Facebook hosts.
class LiveWebView extends StatefulWidget {
  const LiveWebView(this.url, {super.key});

  final String url;

  @override
  State<LiveWebView> createState() => _LiveWebViewState();
}

class _LiveWebViewState extends State<LiveWebView> {
  late final WebViewController _controller;

  @override
  void initState() {
    super.initState();
    final uri = Uri.parse(widget.url);
    final start = uri.replace(queryParameters: {...uri.queryParameters, 'playsinline': '1'});
    _controller = WebViewController()
      ..setJavaScriptMode(JavaScriptMode.unrestricted)
      ..setNavigationDelegate(NavigationDelegate(onNavigationRequest: (req) {
        final to = Uri.tryParse(req.url);
        if (to != null && to.scheme == 'https' && _playerHost(to.host)) return NavigationDecision.navigate;
        if (to != null && to.scheme == 'https') launchUrl(to, mode: LaunchMode.externalApplication);
        return NavigationDecision.prevent;
      }))
      ..loadRequest(start);
  }

  @override
  Widget build(BuildContext context) => WebViewWidget(key: const Key('live-player'), controller: _controller);
}

import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:font_awesome_flutter/font_awesome_flutter.dart';
import 'package:go_router/go_router.dart';

import '../../core/widgets/souk_kit.dart';
import '../../l10n/l10n.dart';
import 'messages_api.dart';
import 'thread_screen.dart';

List<RouteBase> get messagesSubRoutes => [
  GoRoute(
    path: ':id',
    builder: (_, s) => ThreadScreen(id: int.tryParse(s.pathParameters['id']!) ?? 0),
  ),
];

/// `/account/messages` — one row per conversation with the unread count.
class ConversationsScreen extends ConsumerWidget {
  const ConversationsScreen({super.key});

  Future<void> _refresh(WidgetRef ref) async {
    ref.invalidate(conversationsProvider);
    try {
      await ref.read(conversationsProvider.future);
    } catch (_) {}
  }

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final l = context.l10n;
    final async = ref.watch(conversationsProvider);
    return SoukPage(
      title: l.messages,
      scrollable: true,
      onRefresh: () => _refresh(ref),
      body: async.when(
        skipLoadingOnRefresh: true,
        loading: () => const SoukSkeleton.list(rows: 5),
        error: (e, _) => ErrorPane(error: e, onRetry: () => _refresh(ref), retryLabel: l.retry),
        data: (list) => list.isEmpty
            ? SoukEmptyState(key: const Key('messages-empty'), title: l.messagesEmpty, message: l.messagesEmptyBody)
            : SoukCard(
                padding: EdgeInsets.zero,
                child: Material(
                  type: MaterialType.transparency,
                  child: Column(
                    children: [
                      for (final c in list)
                        ListTile(
                          key: Key('conversation-${c.id}'),
                          onTap: () => context.push('/account/messages/${c.id}'),
                          leading: SizedBox(
                            width: 44,
                            height: 44,
                            child: c.storeLogo == null
                                ? Container(
                                    decoration: const BoxDecoration(color: SoukColors.cream, shape: BoxShape.circle),
                                    child: const Center(
                                      child: FaIcon(FontAwesomeIcons.store, size: 16, color: SoukColors.brassDeep),
                                    ),
                                  )
                                : ProductImage(c.storeLogo, radius: 22),
                          ),
                          title: Text(c.title, style: SoukText.label, maxLines: 1, overflow: TextOverflow.ellipsis),
                          subtitle: Text(
                            c.lastBody ?? l.messagesNoMessagesYet,
                            style: c.unreadCount > 0
                                ? SoukText.small.copyWith(fontWeight: FontWeight.w600)
                                : SoukText.meta,
                            maxLines: 1,
                            overflow: TextOverflow.ellipsis,
                          ),
                          trailing: Column(
                            mainAxisAlignment: MainAxisAlignment.center,
                            crossAxisAlignment: CrossAxisAlignment.end,
                            children: [
                              if (c.lastAt != null)
                                Text(soukDateTime(context, c.lastAt!), style: SoukText.meta.copyWith(fontSize: 11)),
                              if (c.unreadCount > 0) ...[
                                const SizedBox(height: 2),
                                SoukBadge('${c.unreadCount}', key: Key('conversation-unread-${c.id}'), status: 'sale'),
                              ],
                            ],
                          ),
                          shape: const Border(bottom: BorderSide(color: SoukColors.line)),
                        ),
                    ],
                  ),
                ),
              ),
      ),
    );
  }
}

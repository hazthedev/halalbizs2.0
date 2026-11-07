import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:font_awesome_flutter/font_awesome_flutter.dart';
import 'package:go_router/go_router.dart';

import '../../core/auth/session.dart';
import '../../core/widgets/souk_kit.dart';
import '../../l10n/l10n.dart';
import 'account_api.dart';

/// `/account/notifications` — newest first, unread dot; tap marks read and
/// follows `app_url` when the server mapped one.
class NotificationsScreen extends ConsumerStatefulWidget {
  const NotificationsScreen({super.key});

  @override
  ConsumerState<NotificationsScreen> createState() => _NotificationsScreenState();
}

class _NotificationsScreenState extends ConsumerState<NotificationsScreen> {
  bool _busy = false;

  Future<void> _refresh() async {
    ref.invalidate(notificationsProvider);
    try {
      await ref.read(notificationsProvider.future);
      await ref.read(sessionProvider.notifier).refreshProfile();
    } catch (_) {}
  }

  Future<void> _open(AppNotification n) async {
    final router = GoRouter.of(context);
    if (!n.read) {
      try {
        await ref.read(accountApiProvider).markNotificationRead(n.id);
        if (!mounted) return;
        ref.invalidate(notificationsProvider);
        // The unread count on the Account tab comes from `GET me`.
        ref.read(sessionProvider.notifier).refreshProfile().ignore();
      } catch (e) {
        if (mounted) showApiError(context, e);
      }
    }
    final url = n.appUrl;
    if (url != null && url.startsWith('/')) router.push(url);
  }

  Future<void> _readAll() async {
    setState(() => _busy = true);
    try {
      await ref.read(accountApiProvider).markAllNotificationsRead();
      if (!mounted) return;
      await _refresh();
    } catch (e) {
      if (mounted) showApiError(context, e);
    } finally {
      if (mounted) setState(() => _busy = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    final l = context.l10n;
    final async = ref.watch(notificationsProvider);
    final hasUnread = async.value?.any((n) => !n.read) ?? false;
    return SoukPage(
      title: l.notifications,
      scrollable: true,
      onRefresh: _refresh,
      actions: [
        if (hasUnread)
          TextButton(
            key: const Key('notifications-read-all'),
            onPressed: _busy ? null : _readAll,
            child: Text(l.accountMarkAllRead),
          ),
      ],
      body: async.when(
        skipLoadingOnRefresh: true,
        loading: () => const SoukSkeleton.list(rows: 5),
        error: (e, _) => ErrorPane(error: e, onRetry: _refresh, retryLabel: l.retry),
        data: (list) => list.isEmpty
            ? SoukEmptyState(
                key: const Key('notifications-empty'),
                title: l.accountNoNotifications,
                message: l.accountNoNotificationsBody,
              )
            : SoukCard(
                padding: EdgeInsets.zero,
                child: Material(
                  type: MaterialType.transparency,
                  child: Column(
                    children: [
                      for (final n in list)
                        ListTile(
                          key: Key('notification-${n.id}'),
                          onTap: () => _open(n),
                          tileColor: n.read ? null : SoukColors.emeraldTint.withValues(alpha: .5),
                          leading: SizedBox(
                            width: 24,
                            child: Center(
                              child: n.read
                                  ? const FaIcon(FontAwesomeIcons.bell, size: 14, color: SoukColors.inkFaint)
                                  : Semantics(
                                      label: l.accountUnread,
                                      child: Container(
                                        key: Key('notification-unread-${n.id}'),
                                        width: 10,
                                        height: 10,
                                        decoration: const BoxDecoration(
                                          color: SoukColors.emerald,
                                          shape: BoxShape.circle,
                                        ),
                                      ),
                                    ),
                            ),
                          ),
                          title: Text(
                            n.message ?? '',
                            style: n.read ? SoukText.body : SoukText.body.copyWith(fontWeight: FontWeight.w600),
                          ),
                          subtitle: Text(
                            [n.detail, if (n.createdAt != null) soukDateTime(context, n.createdAt!)].nonNulls.join('\n'),
                            style: SoukText.meta,
                          ),
                          trailing: n.appUrl == null
                              ? null
                              : const Icon(Icons.chevron_right_rounded, color: SoukColors.inkFaint),
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

import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:font_awesome_flutter/font_awesome_flutter.dart';
import 'package:go_router/go_router.dart';

import '../../core/auth/session.dart';
import '../../core/widgets/souk_kit.dart';
import '../../l10n/l10n.dart';
import '../messages/messages_api.dart';

/// Bell + chat icons for the app bar (buyer Home, Seller Dashboard). Counts
/// are the ones the Account menu already shows (`GET me` unread notifications,
/// unread across `GET conversations`); a badge hides at 0. Guests see the
/// icons too (as on Shopee) and a tap goes to login, then on to the page
/// (Haze 11-11). Taps open the Account sub-pages.
class InboxActions extends ConsumerWidget {
  const InboxActions({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final me = ref.watch(profileProvider);
    final l = context.l10n;
    final messages = me == null ? 0 : ref.watch(unreadMessagesProvider);
    void open(String path) =>
        context.push(me == null ? '/login?from=${Uri.encodeComponent(path)}' : path);
    return Row(mainAxisSize: MainAxisSize.min, children: [
      _Badged(
        count: me?.unreadNotifications ?? 0,
        badgeKey: const Key('inbox-bell-badge'),
        child: SoukIconButton(
          key: const Key('inbox-bell'),
          icon: FontAwesomeIcons.bell,
          label: l.notifications,
          onPressed: () => open('/account/notifications'),
        ),
      ),
      _Badged(
        count: messages,
        badgeKey: const Key('inbox-chat-badge'),
        child: SoukIconButton(
          key: const Key('inbox-chat'),
          icon: FontAwesomeIcons.comment,
          label: l.messages,
          onPressed: () => open('/account/messages'),
        ),
      ),
    ]);
  }
}

class _Badged extends StatelessWidget {
  const _Badged({required this.count, required this.badgeKey, required this.child});

  final int count;
  final Key badgeKey;
  final Widget child;

  @override
  Widget build(BuildContext context) => Stack(clipBehavior: Clip.none, children: [
        child,
        if (count > 0)
          Positioned(
            right: 4,
            top: 4,
            child: IgnorePointer(
              child: Container(
                key: badgeKey,
                constraints: const BoxConstraints(minWidth: 16, minHeight: 16),
                padding: const EdgeInsets.symmetric(horizontal: 4),
                decoration: BoxDecoration(color: SoukColors.danger, borderRadius: BorderRadius.circular(SoukRadius.pill)),
                alignment: Alignment.center,
                child: Text(count > 99 ? '99+' : '$count',
                    style: SoukText.meta.copyWith(color: SoukColors.onDark, fontSize: 10, height: 1.2)),
              ),
            ),
          ),
      ]);
}

import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:font_awesome_flutter/font_awesome_flutter.dart';
import 'package:go_router/go_router.dart';

import '../../core/auth/session.dart';
import '../../core/in_app_browser.dart';
import '../../core/locale.dart';
import '../../core/widgets/souk_kit.dart';
import '../../l10n/l10n.dart';
import '../messages/conversations_screen.dart';
import '../messages/messages_api.dart';
import 'account_api.dart';
import '../orders/orders_api.dart';
import 'addresses_screen.dart';
import 'coins_screen.dart';
import 'delete_account_screen.dart';
import 'followed_shops_screen.dart';
import 'notifications_screen.dart';
import 'profile_screen.dart';
import 'wishlist_screen.dart';

/// Account tab root: signed-in header + menu rows to the sub-pages.
class AccountScreen extends ConsumerWidget {
  const AccountScreen({super.key});

  Future<void> _signOut(BuildContext context, WidgetRef ref) async {
    final l = context.l10n;
    final ok = await showSoukConfirm(context,
        title: l.signOutConfirmTitle, message: l.signOutConfirmBody, confirmLabel: l.signOut, cancelLabel: l.cancel, danger: true);
    if (!ok || !context.mounted) return;
    await ref.read(sessionProvider.notifier).logout();
  }

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final l = context.l10n;
    final me = ref.watch(profileProvider);
    final unreadMessages = me == null ? 0 : ref.watch(unreadMessagesProvider);
    if (me == null) {
      return SoukPage(
        title: l.tabAccount,
        brandMark: true,
        showBack: false,
        body: Center(
          child: SoukEmptyState(
            key: const Key('account-guest'),
            title: l.guestAccountTitle,
            message: l.guestAccountBody,
            action: SoukButton(
              key: const Key('account-sign-in'),
              label: l.signIn,
              variant: SoukButtonVariant.primary,
              onPressed: () => context.push('/login?from=%2Faccount'),
            ),
          ),
        ),
      );
    }

    return SoukPage(
      title: l.tabAccount,
      brandMark: true,
      showBack: false,
      scrollable: true,
      padding: const EdgeInsets.fromLTRB(SoukSpace.page, SoukSpace.s2, SoukSpace.page, SoukSpace.s8),
      body: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          SoukCard(
            key: const Key('account-header'),
            child: Row(children: [
              Container(
                width: 52,
                height: 52,
                decoration: const BoxDecoration(color: SoukColors.emeraldTint, shape: BoxShape.circle),
                alignment: Alignment.center,
                child: Text(me.name.isEmpty ? '?' : me.name.characters.first.toUpperCase(),
                    style: SoukText.displayH3.copyWith(color: SoukColors.emerald)),
              ),
              const SizedBox(width: SoukSpace.s3),
              Expanded(
                child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
                  Text(me.name, style: SoukText.displayH3, maxLines: 1, overflow: TextOverflow.ellipsis),
                  Text(me.email ?? '', style: SoukText.meta, maxLines: 1, overflow: TextOverflow.ellipsis),
                ]),
              ),
              Column(crossAxisAlignment: CrossAxisAlignment.end, children: [
                Text(l.coins.toUpperCase(), style: SoukText.eyebrow),
                Text('${me.coins}', style: SoukText.price.copyWith(color: SoukColors.brassDeep)),
              ]),
            ]),
          ),
          const SizedBox(height: SoukSpace.s4),
          const OrderShortcuts(),
          const SizedBox(height: SoukSpace.s4),
          SoukCard(
            padding: EdgeInsets.zero,
            child: Column(children: [
              SoukRow(key: const Key('account-profile'), icon: FontAwesomeIcons.user, label: l.profile, onTap: () => context.push('/account/profile')),
              SoukRow(key: const Key('account-addresses'), icon: FontAwesomeIcons.locationDot, label: l.addresses, onTap: () => context.push('/account/addresses')),
              SoukRow(key: const Key('account-wishlist'), icon: FontAwesomeIcons.heart, label: l.wishlist, onTap: () => context.push('/account/wishlist')),
              SoukRow(key: const Key('account-following'), icon: FontAwesomeIcons.store, label: l.followedShops, onTap: () => context.push('/account/following')),
              SoukRow(key: const Key('account-coins'), icon: FontAwesomeIcons.coins, label: l.coins, onTap: () => context.push('/account/coins')),
              SoukRow(
                key: const Key('account-notifications'),
                icon: FontAwesomeIcons.bell,
                label: l.notifications,
                trailing: me.unreadNotifications > 0 ? SoukBadge('${me.unreadNotifications}', status: 'sale') : null,
                onTap: () => context.push('/account/notifications'),
              ),
              SoukRow(
                key: const Key('account-messages'),
                icon: FontAwesomeIcons.comment,
                label: l.messages,
                trailing: unreadMessages > 0 ? SoukBadge('$unreadMessages', status: 'sale') : null,
                onTap: () => context.push('/account/messages'),
              ),
              SoukRow(key: const Key('account-language'), icon: FontAwesomeIcons.language, label: l.language, onTap: () => context.push('/account/language')),
            ]),
          ),
          const SizedBox(height: SoukSpace.s4),
          SoukCard(
            padding: EdgeInsets.zero,
            child: Column(children: [
              if (me.isApprovedSeller)
                SoukRow(key: const Key('account-seller'), icon: FontAwesomeIcons.store, label: l.sellerCentre, onTap: () => context.go('/seller'))
              else
                SoukRow(
                  key: const Key('account-become-seller'),
                  icon: FontAwesomeIcons.store,
                  label: l.becomeSeller,
                  onTap: () => _becomeSeller(context, ref),
                ),
              SoukRow(key: const Key('account-delete'), icon: FontAwesomeIcons.userXmark, label: l.deleteAccount, onTap: () => context.push('/account/delete')),
              SoukRow(key: const Key('account-sign-out'), icon: FontAwesomeIcons.rightFromBracket, label: l.signOut, danger: true, onTap: () => _signOut(context, ref)),
            ]),
          ),
        ],
      ),
    );
  }

  /// Web seller sign-up, already signed in: a one-time link from the API,
  /// opened in the in-app browser sheet.
  static Future<void> _becomeSeller(BuildContext context, WidgetRef ref) async {
    try {
      final url = await ref.read(accountApiProvider).webSignInLink('seller-apply');
      await ref.read(inAppBrowserProvider).open(url);
    } catch (e) {
      if (context.mounted) showApiError(context, e);
    }
  }
}

/// Four order shortcuts with counts (web account dashboard: To Pay / To Ship /
/// To Receive / Completed, `GET orders/quick-status`). Tap = Orders on that tab.
class OrderShortcuts extends ConsumerWidget {
  const OrderShortcuts({super.key});

  static const _icons = {
    'to-pay': FontAwesomeIcons.wallet,
    'to-ship': FontAwesomeIcons.boxOpen,
    'to-receive': FontAwesomeIcons.truck,
    'completed': FontAwesomeIcons.circleCheck,
  };

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final rows = ref.watch(orderQuickStatusProvider).value;
    if (rows == null || rows.isEmpty) return const SizedBox.shrink();
    return SoukCard(
      key: const Key('account-order-shortcuts'),
      padding: const EdgeInsets.symmetric(vertical: SoukSpace.s3),
      child: Row(children: [
        for (final r in rows)
          Expanded(
            child: InkWell(
              key: Key('order-shortcut-${r.key}'),
              onTap: () => context.go('/orders?bucket=${r.key}'),
              child: Padding(
                padding: const EdgeInsets.symmetric(vertical: SoukSpace.s1),
                child: Column(mainAxisSize: MainAxisSize.min, children: [
                  Stack(clipBehavior: Clip.none, children: [
                    Padding(padding: const EdgeInsets.all(6), child: FaIcon(_icons[r.key] ?? FontAwesomeIcons.box, size: 20, color: SoukColors.inkHead)),
                    if (r.count > 0)
                      Positioned(
                        right: -4,
                        top: -2,
                        child: Container(
                          key: Key('order-shortcut-${r.key}-count'),
                          constraints: const BoxConstraints(minWidth: 16, minHeight: 16),
                          padding: const EdgeInsets.symmetric(horizontal: 4),
                          decoration: BoxDecoration(color: SoukColors.danger, borderRadius: BorderRadius.circular(SoukRadius.pill)),
                          alignment: Alignment.center,
                          child: Text(r.count > 99 ? '99+' : '${r.count}',
                              style: SoukText.meta.copyWith(color: SoukColors.onDark, fontSize: 10, height: 1.2)),
                        ),
                      ),
                  ]),
                  Text(r.label, style: SoukText.meta, textAlign: TextAlign.center, maxLines: 2, overflow: TextOverflow.ellipsis),
                ]),
              ),
            ),
          ),
      ]),
    );
  }
}

/// `/account/language` — en / ms, persisted in drift kv and, when signed in,
/// saved as the account's `preferred_locale` so Profile and the server agree.
class LanguageScreen extends ConsumerWidget {
  const LanguageScreen({super.key});

  static Future<void> _pick(WidgetRef ref, String code) async {
    await ref.read(localeProvider.notifier).set(Locale(code));
    if (ref.read(sessionProvider) is! SignedIn) return;
    try {
      await ref.read(accountApiProvider).updateMe(preferredLocale: code);
    } catch (_) {
      // The app language already changed; Profile can retry the save.
    }
  }

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final l = context.l10n;
    final current = ref.watch(localeProvider);
    final options = {'en': l.english, 'ms': l.malay};
    return SoukPage(
      title: l.language,
      // Scrollable like the other short pages: a bare card in the fixed body
      // stretched to full height (Haze 11-11 screenshot).
      scrollable: true,
      body: SoukCard(
        padding: EdgeInsets.zero,
        child: Column(children: [
          for (final e in options.entries)
            SoukRow(
              key: Key('language-${e.key}'),
              label: e.value,
              trailing: current.languageCode == e.key
                  ? const Icon(Icons.check_rounded, color: SoukColors.emerald)
                  : const SizedBox.shrink(),
              onTap: () => _pick(ref, e.key),
            ),
        ]),
      ),
    );
  }
}

List<RouteBase> get accountSubRoutes => [
      GoRoute(path: 'profile', builder: (_, _) => const ProfileScreen()),
      GoRoute(path: 'addresses', builder: (_, _) => const AddressesScreen(), routes: [
        GoRoute(path: 'new', builder: (_, _) => const AddressFormScreen()),
        GoRoute(path: ':id', builder: (_, s) => AddressFormScreen(id: int.tryParse(s.pathParameters['id']!))),
      ]),
      GoRoute(path: 'wishlist', builder: (_, _) => const WishlistScreen()),
      GoRoute(path: 'following', builder: (_, _) => const FollowedShopsScreen()),
      GoRoute(path: 'coins', builder: (_, _) => const CoinsScreen()),
      GoRoute(path: 'notifications', builder: (_, _) => const NotificationsScreen()),
      GoRoute(path: 'messages', builder: (_, _) => const ConversationsScreen(), routes: messagesSubRoutes),
      GoRoute(path: 'delete', builder: (_, _) => const DeleteAccountScreen()),
      GoRoute(path: 'language', builder: (_, _) => const LanguageScreen()),
    ];

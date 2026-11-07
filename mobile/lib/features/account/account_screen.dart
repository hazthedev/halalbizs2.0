import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:font_awesome_flutter/font_awesome_flutter.dart';
import 'package:go_router/go_router.dart';
import 'package:url_launcher/url_launcher.dart';

import '../../core/api/api_client.dart';
import '../../core/auth/session.dart';
import '../../core/locale.dart';
import '../../core/widgets/coming_soon.dart';
import '../../core/widgets/souk_kit.dart';
import '../../l10n/l10n.dart';

/// Account tab root: signed-in header + menu rows. Sub-pages are slice 5
/// placeholders except Language, which is live.
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
          SoukCard(
            padding: EdgeInsets.zero,
            child: Column(children: [
              SoukRow(key: const Key('account-profile'), icon: FontAwesomeIcons.user, label: l.profile, onTap: () => context.push('/account/profile')),
              SoukRow(key: const Key('account-addresses'), icon: FontAwesomeIcons.locationDot, label: l.addresses, onTap: () => context.push('/account/addresses')),
              SoukRow(key: const Key('account-wishlist'), icon: FontAwesomeIcons.heart, label: l.wishlist, onTap: () => context.push('/account/wishlist')),
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
                trailing: me.unreadMessages > 0 ? SoukBadge('${me.unreadMessages}', status: 'sale') : null,
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
                  onTap: () => launchUrl(Uri.parse('${_webBase()}/seller/apply'), mode: LaunchMode.externalApplication),
                ),
              SoukRow(key: const Key('account-delete'), icon: FontAwesomeIcons.userXmark, label: l.deleteAccount, onTap: () => context.push('/account/delete')),
              SoukRow(key: const Key('account-sign-out'), icon: FontAwesomeIcons.rightFromBracket, label: l.signOut, danger: true, onTap: () => _signOut(context, ref)),
            ]),
          ),
        ],
      ),
    );
  }

  /// Web origin derived from the API base (`…/api/v1` → `…`).
  static String _webBase() => kApiBaseUrl.replaceFirst(RegExp(r'/api/v1/?$'), '');
}

/// `/account/language` — en / ms, persisted in drift kv.
class LanguageScreen extends ConsumerWidget {
  const LanguageScreen({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final l = context.l10n;
    final current = ref.watch(localeProvider);
    final options = {'en': l.english, 'ms': l.malay};
    return SoukPage(
      title: l.language,
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
              onTap: () => ref.read(localeProvider.notifier).set(Locale(e.key)),
            ),
        ]),
      ),
    );
  }
}

List<RouteBase> get accountSubRoutes => [
      GoRoute(path: 'profile', builder: (_, _) => const ComingSoonScreen(title: 'Profile')),
      GoRoute(path: 'addresses', builder: (_, _) => const ComingSoonScreen(title: 'Addresses'), routes: [
        GoRoute(path: 'new', builder: (_, _) => const ComingSoonScreen(title: 'New address')),
        GoRoute(path: ':id', builder: (_, _) => const ComingSoonScreen(title: 'Address')),
      ]),
      GoRoute(path: 'wishlist', builder: (_, _) => const ComingSoonScreen(title: 'Wishlist')),
      GoRoute(path: 'coins', builder: (_, _) => const ComingSoonScreen(title: 'Coins')),
      GoRoute(path: 'notifications', builder: (_, _) => const ComingSoonScreen(title: 'Notifications')),
      GoRoute(path: 'messages', builder: (_, _) => const ComingSoonScreen(title: 'Messages'), routes: [
        GoRoute(path: ':id', builder: (_, _) => const ComingSoonScreen(title: 'Conversation')),
      ]),
      GoRoute(path: 'delete', builder: (_, _) => const ComingSoonScreen(title: 'Delete account')),
      GoRoute(path: 'language', builder: (_, _) => const LanguageScreen()),
    ];

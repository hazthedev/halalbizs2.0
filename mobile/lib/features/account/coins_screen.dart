import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:font_awesome_flutter/font_awesome_flutter.dart';

import '../../core/api/api_client.dart';
import '../../core/api/json.dart';
import '../../core/auth/session.dart';
import '../../core/widgets/souk_kit.dart';
import '../../l10n/l10n.dart';
import 'account_api.dart';

/// `/account/coins` — balance, streak, daily check-in, ledger. Coins are a
/// count (brass = premium), never money.
class CoinsScreen extends ConsumerStatefulWidget {
  const CoinsScreen({super.key});

  @override
  ConsumerState<CoinsScreen> createState() => _CoinsScreenState();
}

class _CoinsScreenState extends ConsumerState<CoinsScreen> {
  bool _checking = false;

  Future<void> _refresh() async {
    ref.invalidate(coinsProvider);
    try {
      await ref.read(coinsProvider.future);
    } catch (_) {}
  }

  Future<void> _checkIn() async {
    final l = context.l10n;
    setState(() => _checking = true);
    try {
      final r = await ref.read(accountApiProvider).checkIn();
      if (!mounted) return;
      ScaffoldMessenger.of(context)
        ..clearSnackBars()
        ..showSnackBar(SnackBar(content: Text(l.accountCheckInDone(asInt(r['coins']) ?? 0, asInt(r['streak']) ?? 1))));
      await _refresh();
      await ref.read(sessionProvider.notifier).refreshProfile();
    } catch (e) {
      // 409 already_checked_in carries its own message.
      if (mounted) showApiError(context, e);
    } finally {
      if (mounted) setState(() => _checking = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    final l = context.l10n;
    final async = ref.watch(coinsProvider);
    return SoukPage(
      title: l.coins,
      scrollable: true,
      onRefresh: _refresh,
      body: async.when(
        skipLoadingOnRefresh: true,
        loading: () => const SoukSkeleton.list(rows: 4),
        error: (e, _) => e is ApiException && e.isNotFound
            ? SoukEmptyState(key: const Key('coins-disabled'), title: l.accountCoinsUnavailable, message: l.accountCoinsUnavailableBody)
            : ErrorPane(error: e, onRetry: _refresh, retryLabel: l.retry),
        data: (c) => Column(crossAxisAlignment: CrossAxisAlignment.stretch, children: [
          SoukCard(
            key: const Key('coins-balance-card'),
            child: Column(crossAxisAlignment: CrossAxisAlignment.stretch, children: [
              Row(children: [
                Expanded(
                  child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
                    Text(l.accountCoinsBalance.toUpperCase(), style: SoukText.eyebrow),
                    Text('${c.balance}', key: const Key('coins-balance'), style: SoukText.priceLarge.copyWith(color: SoukColors.brassDeep, fontSize: 32)),
                  ]),
                ),
                Column(crossAxisAlignment: CrossAxisAlignment.end, children: [
                  Text(l.accountCoinsStreak.toUpperCase(), style: SoukText.eyebrow),
                  Text(l.accountCoinsDays(c.streak), style: SoukText.price),
                ]),
              ]),
              const SizedBox(height: SoukSpace.s2),
              Text(l.accountCoinsLifetime(c.lifetimeEarned), style: SoukText.meta),
              if (c.expiringAt != null) Text(l.accountCoinsExpiring(soukDateTime(context, c.expiringAt!)), style: SoukText.meta.copyWith(color: SoukColors.warn)),
              const SizedBox(height: SoukSpace.s4),
              SoukButton(
                key: const Key('coins-check-in'),
                label: c.canCheckIn ? l.accountCheckIn : l.accountCheckedIn,
                icon: FontAwesomeIcons.calendarCheck,
                variant: SoukButtonVariant.primary,
                expand: true,
                requiresOnline: true,
                busy: _checking,
                disabledReason: c.canCheckIn ? null : l.accountCheckedInReason,
                onPressed: c.canCheckIn ? _checkIn : null,
              ),
              const SizedBox(height: SoukSpace.s2),
              Text(l.accountCheckInBody, style: SoukText.meta, textAlign: TextAlign.center),
            ]),
          ),
          const SizedBox(height: SoukSpace.s5),
          Text(l.accountCoinsHistory, style: SoukText.displayH3),
          const SizedBox(height: SoukSpace.s3),
          if (c.ledger.isEmpty)
            SoukEmptyState(compact: true, title: l.accountCoinsNoHistory)
          else
            SoukCard(
              padding: EdgeInsets.zero,
              child: Column(children: [
                for (final t in c.ledger)
                  SoukRow(
                    key: Key('coins-txn-${t['id']}'),
                    label: asStr(t['type_label']) ?? asStr(t['type']) ?? '',
                    subtitle: [asStr(t['note']), if (asDate(t['created_at']) != null) soukDateTime(context, asDate(t['created_at'])!)].nonNulls.join(' · '),
                    trailing: Text(
                      '${(asInt(t['amount']) ?? 0) > 0 ? '+' : ''}${asInt(t['amount']) ?? 0}',
                      style: SoukText.price.copyWith(color: (asInt(t['amount']) ?? 0) >= 0 ? SoukColors.emerald : SoukColors.danger),
                    ),
                  ),
              ]),
            ),
        ]),
      ),
    );
  }
}

import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../../core/widgets/souk_kit.dart';
import '../../l10n/l10n.dart';
import '../../core/api/json.dart';
import '../cart/cart_api.dart';
import 'checkout_api.dart';

/// `/checkout/done/:orderNo` — order placed (COD) or paid (iPay88).
class DoneScreen extends ConsumerStatefulWidget {
  const DoneScreen({super.key, required this.orderNo});

  final String orderNo;

  @override
  ConsumerState<DoneScreen> createState() => _DoneScreenState();
}

class _DoneScreenState extends ConsumerState<DoneScreen> {
  @override
  void initState() {
    super.initState();
    // The selected lines are gone: refresh the tab badge (after this build,
    // so the shell's Consumer is not marked dirty mid-frame).
    WidgetsBinding.instance.addPostFrameCallback((_) => ref.invalidate(cartProvider));
  }

  @override
  Widget build(BuildContext context) {
    final l = context.l10n;
    final p = ref.watch(paymentStateProvider(widget.orderNo)).value;
    final shops = p?.subOrders ?? const [];
    return SoukPage(
      title: l.checkoutDoneTitle,
      showBack: false,
      scrollable: true,
      body: Column(crossAxisAlignment: CrossAxisAlignment.stretch, children: [
        SoukEmptyState(
          key: const Key('checkout-done'),
          title: l.checkoutDoneTitle,
          // One checkout, one order per shop: say so, and name each SO number.
          message: p == null
              ? l.checkoutDoneBody(widget.orderNo)
              : '${p.paid ? l.checkoutDonePaid(formatSen(p.totalSen), widget.orderNo) : l.checkoutDoneCod(formatSen(p.totalSen), widget.orderNo)}'
                  '${shops.isEmpty ? '' : ' ${l.checkoutDoneSplit(shops.length)}'}',
          action: SoukButton(
            key: const Key('checkout-done-orders'),
            label: l.checkoutViewOrders,
            variant: SoukButtonVariant.primary,
            onPressed: () => context.go('/orders'),
          ),
        ),
        for (final s in shops) ...[
          SoukCard(
            key: Key('done-sub-order-${s['id']}'),
            padding: const EdgeInsets.symmetric(horizontal: SoukSpace.s4, vertical: SoukSpace.s3),
            onTap: () => context.go('/orders/${s['id']}'),
            child: Row(children: [
              Text(asStr(s['sub_order_no']) ?? '', style: SoukText.mono),
              const SizedBox(width: SoukSpace.s3),
              Expanded(child: Text(asStr(asMap(s['store'])['name']) ?? '', style: SoukText.label, textAlign: TextAlign.end, maxLines: 1, overflow: TextOverflow.ellipsis)),
              const Icon(Icons.chevron_right_rounded, color: SoukColors.inkFaint),
            ]),
          ),
          const SizedBox(height: SoukSpace.s2),
        ],
      ]),
    );
  }
}

import 'dart:async';

import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:font_awesome_flutter/font_awesome_flutter.dart';
import 'package:go_router/go_router.dart';

import '../../core/in_app_browser.dart';
import '../../core/widgets/souk_kit.dart';
import '../../l10n/l10n.dart';
import 'checkout_api.dart';

/// `/checkout/pay/:orderNo` — the iPay88 hand-off. Opens the signed web
/// `payment_url` in the in-app browser sheet, then trusts only
/// `GET orders/{orderNo}/payment` (hard rule 4): polled every [pollEvery]
/// while visible and on every app resume; `paid` → `/checkout/done`.
/// Also the target of the `halalbizs://pay/{order_no}` deep link, in which
/// case the fresh `retry_url` from the status call is the one to open.
/// The deep link arrives as `?returned=1`: close the sheet and check at once.
class PayScreen extends ConsumerStatefulWidget {
  const PayScreen({super.key, required this.orderNo, this.initialUrl, this.returned = false});

  final String orderNo;

  /// Back from the web return page (`halalbizs://pay/{no}`).
  final bool returned;

  /// `payment_url` from `POST checkout/place`; launched once on open.
  final String? initialUrl;

  static Duration pollEvery = const Duration(seconds: 3);

  @override
  ConsumerState<PayScreen> createState() => _PayScreenState();
}

class _PayScreenState extends ConsumerState<PayScreen> with WidgetsBindingObserver {
  PaymentState? _state;
  Object? _error;
  Timer? _timer;
  bool _checking = false;

  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addObserver(this);
    if (widget.initialUrl != null) _open(widget.initialUrl!);
    if (widget.returned) ref.read(inAppBrowserProvider).close();
    _poll();
    _startTimer();
  }

  @override
  void didUpdateWidget(PayScreen old) {
    super.didUpdateWidget(old);
    if (widget.returned && !old.returned) {
      ref.read(inAppBrowserProvider).close();
      _poll();
    }
  }

  @override
  void dispose() {
    WidgetsBinding.instance.removeObserver(this);
    _timer?.cancel();
    super.dispose();
  }

  @override
  void didChangeAppLifecycleState(AppLifecycleState state) {
    if (state == AppLifecycleState.resumed) {
      if (_state?.paid == true || (_state?.expired ?? false)) return;
      _poll();
      _startTimer();
    } else if (state == AppLifecycleState.paused) {
      _timer?.cancel();
    }
  }

  void _startTimer() {
    _timer?.cancel();
    _timer = Timer.periodic(PayScreen.pollEvery, (_) => _poll());
  }

  Future<void> _open(String url) => ref.read(inAppBrowserProvider).open(url);

  Future<void> _poll() async {
    if (_checking) return;
    _checking = true;
    try {
      final s = await ref.read(checkoutApiProvider).payment(widget.orderNo);
      if (!mounted) return;
      setState(() {
        _state = s;
        _error = null;
      });
      if (s.paid) {
        _timer?.cancel();
        context.go('/checkout/done/${widget.orderNo}');
      } else if (s.expired) {
        _timer?.cancel();
      }
    } catch (e) {
      if (mounted && _state == null) setState(() => _error = e);
    } finally {
      _checking = false;
    }
  }

  @override
  Widget build(BuildContext context) {
    final l = context.l10n;
    final s = _state;
    final expired = s?.expired ?? false;
    final retryUrl = s?.retryUrl ?? widget.initialUrl;

    return SoukPage(
      title: l.checkoutPayTitle,
      showBack: false,
      leading: IconButton(
        key: const Key('pay-close'),
        tooltip: l.cancel,
        icon: const Icon(Icons.close_rounded),
        onPressed: () => context.canPop() ? context.pop() : context.go('/orders'),
      ),
      body: s == null && _error != null
          ? ErrorPane(error: _error!, onRetry: _poll, retryLabel: l.retry)
          : Center(
              child: Padding(
                padding: const EdgeInsets.all(SoukSpace.s6),
                child: Column(mainAxisSize: MainAxisSize.min, crossAxisAlignment: CrossAxisAlignment.stretch, children: [
                  Center(
                    child: Container(
                      width: 76,
                      height: 76,
                      decoration: BoxDecoration(color: expired ? SoukColors.dangerTint : SoukColors.emeraldTint, shape: BoxShape.circle),
                      alignment: Alignment.center,
                      child: FaIcon(expired ? FontAwesomeIcons.hourglassEnd : FontAwesomeIcons.arrowUpRightFromSquare,
                          size: 28, color: expired ? SoukColors.danger : SoukColors.emerald),
                    ),
                  ),
                  const SizedBox(height: SoukSpace.s5),
                  Text(expired ? l.checkoutPayExpiredTitle : l.checkoutPayBrowserTitle,
                      key: Key(expired ? 'pay-expired' : 'pay-waiting'), textAlign: TextAlign.center, style: SoukText.displayH2),
                  const SizedBox(height: SoukSpace.s2),
                  Text(expired ? l.checkoutPayExpiredBody : l.checkoutPayBrowserBody(widget.orderNo),
                      textAlign: TextAlign.center, style: SoukText.bodySoft),
                  if (s != null && !expired && s.expiresAt != null) ...[
                    const SizedBox(height: SoukSpace.s2),
                    Text(l.checkoutPayExpiresAt(_hhmm(s.expiresAt!)), textAlign: TextAlign.center, style: SoukText.mono),
                  ],
                  const SizedBox(height: SoukSpace.s6),
                  if (!expired)
                    SoukButton(
                      key: const Key('pay-paid'),
                      label: l.checkoutPayIHavePaid,
                      icon: FontAwesomeIcons.check,
                      variant: SoukButtonVariant.primary,
                      expand: true,
                      requiresOnline: true,
                      busy: _checking,
                      onPressed: _poll,
                    ),
                  if (retryUrl != null && (expired ? s?.retryUrl != null : true)) ...[
                    const SizedBox(height: SoukSpace.s3),
                    SoukButton(
                      key: const Key('pay-retry'),
                      label: expired ? l.checkoutPayRetry : l.checkoutPayOpenAgain,
                      icon: FontAwesomeIcons.arrowUpRightFromSquare,
                      variant: expired ? SoukButtonVariant.primary : SoukButtonVariant.secondary,
                      expand: true,
                      requiresOnline: true,
                      onPressed: () => _open(s?.retryUrl ?? retryUrl),
                    ),
                  ],
                  const SizedBox(height: SoukSpace.s3),
                  SoukButton(
                    key: const Key('pay-orders'),
                    label: l.checkoutViewOrders,
                    variant: SoukButtonVariant.ghost,
                    expand: true,
                    onPressed: () => context.go('/orders'),
                  ),
                ]),
              ),
            ),
    );
  }

  static String _hhmm(DateTime t) {
    final local = t.toLocal();
    return '${local.hour.toString().padLeft(2, '0')}:${local.minute.toString().padLeft(2, '0')}';
  }
}

import 'dart:async';

import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:font_awesome_flutter/font_awesome_flutter.dart';
import 'package:go_router/go_router.dart';

import '../../core/router.dart' show SoukNotFoundScreen;
import '../../core/widgets/souk_kit.dart';
import '../../l10n/l10n.dart';
import '../seller_shared/models.dart';
import '../seller_shared/seller_api.dart';
import '../seller_shared/seller_kit.dart';
import 'seller_product_form_screen.dart';

/// `/seller/products` tab root: status chips, debounced search (`q`), paged
/// rows, pinned "New product" CTA.
class SellerProductsScreen extends ConsumerStatefulWidget {
  const SellerProductsScreen({super.key});

  @override
  ConsumerState<SellerProductsScreen> createState() => _SellerProductsScreenState();
}

class _SellerProductsScreenState extends ConsumerState<SellerProductsScreen> {
  static const _debounce = Duration(milliseconds: 300);

  final _search = TextEditingController();
  Timer? _timer;
  String? _status;
  String _q = '';
  int _refresh = 0;

  @override
  void dispose() {
    _timer?.cancel();
    _search.dispose();
    super.dispose();
  }

  void _onSearch(String v) {
    _timer?.cancel();
    _timer = Timer(_debounce, () {
      if (mounted && _q != v.trim()) setState(() => _q = v.trim());
    });
  }

  @override
  Widget build(BuildContext context) {
    final l = context.l10n;
    final api = ref.watch(sellerApiProvider);
    final bumped = ref.watch(sellerRefreshProvider);
    return SoukPage(
      title: l.sellerTabProducts,
      leading: const SellerBackToBuying(),
      showBack: false,
      scrollable: true,
      onRefresh: () async => setState(() => _refresh++),
      bottomBar: SoukButton(
        key: const Key('seller-products-new'),
        label: l.sellerNewProduct,
        icon: FontAwesomeIcons.plus,
        variant: SoukButtonVariant.primary,
        expand: true,
        onPressed: () => context.push('/seller/products/new'),
      ),
      body: Column(crossAxisAlignment: CrossAxisAlignment.stretch, children: [
        TextField(
          key: const Key('seller-products-search'),
          controller: _search,
          onChanged: _onSearch,
          textInputAction: TextInputAction.search,
          style: SoukText.body,
          decoration: InputDecoration(
            labelText: l.sellerSearchProducts,
            floatingLabelBehavior: FloatingLabelBehavior.never,
            prefixIcon: const Padding(
              padding: EdgeInsets.only(left: SoukSpace.s3, right: SoukSpace.s2),
              child: Center(widthFactor: 1, child: FaIcon(FontAwesomeIcons.magnifyingGlass, size: 14, color: SoukColors.inkFaint)),
            ),
          ),
        ),
        const SizedBox(height: SoukSpace.s3),
        SizedBox(
          height: 40,
          child: ListView(
            scrollDirection: Axis.horizontal,
            children: [
              SoukChip(
                key: const Key('seller-products-tab-all'),
                label: l.sellerAllStatuses,
                selected: _status == null,
                onTap: () => setState(() => _status = null),
              ),
              for (final s in kProductStatuses) ...[
                const SizedBox(width: SoukSpace.s2),
                SoukChip(
                  key: Key('seller-products-tab-$s'),
                  label: sellerStatusLabel(l, s),
                  selected: _status == s,
                  onTap: () => setState(() => _status = s),
                ),
              ],
            ],
          ),
        ),
        const SizedBox(height: SoukSpace.s4),
        SellerPagedList<SellerProduct>(
          signature: '${_status ?? ''}|$_q#$_refresh#$bumped',
          fetch: (page) => api.products(status: _status, q: _q, page: page),
          itemBuilder: (context, p) => _ProductRow(p, onTap: () => context.push('/seller/products/${p.id}')),
          empty: SoukEmptyState(
            key: const Key('seller-products-empty'),
            title: l.sellerNoProducts,
            message: l.sellerNoProductsBody,
          ),
        ),
      ]),
    );
  }
}

class _ProductRow extends StatelessWidget {
  const _ProductRow(this.p, {required this.onTap});

  final SellerProduct p;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    final l = context.l10n;
    final price = p.salePriceSen ?? p.priceSen ?? p.minPriceSen;
    final regular = p.salePriceSen != null && p.priceSen != null && p.salePriceSen! < p.priceSen! ? p.priceSen : null;
    return SoukCard(
      key: Key('seller-product-${p.id}'),
      onTap: onTap,
      padding: const EdgeInsets.all(SoukSpace.s3),
      child: Row(children: [
        SizedBox(width: 56, height: 56, child: ProductImage(p.thumb, radius: SoukRadius.control)),
        const SizedBox(width: SoukSpace.s3),
        Expanded(
          child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
            Text(p.name, style: SoukText.displayName, maxLines: 1, overflow: TextOverflow.ellipsis),
            const SizedBox(height: 2),
            Row(children: [
              PriceText(price),
              if (regular != null) ...[const SizedBox(width: SoukSpace.s2), PriceText(regular, strike: true)],
            ]),
            const SizedBox(height: SoukSpace.s1),
            Wrap(spacing: SoukSpace.s2, runSpacing: SoukSpace.s1, crossAxisAlignment: WrapCrossAlignment.center, children: [
              SoukBadge(p.statusLabel, status: p.status),
              Text(l.sellerStockCount(p.stockTotal), style: SoukText.mono.copyWith(color: p.stockTotal == 0 ? SoukColors.danger : SoukColors.inkSoft)),
              if (p.codEnabled) SoukBadge(l.sellerCod, status: 'sale'),
            ]),
          ]),
        ),
        const Icon(Icons.chevron_right_rounded, color: SoukColors.inkFaint),
      ]),
    );
  }
}

List<RouteBase> get sellerProductsSubRoutes => [
      GoRoute(path: 'new', builder: (_, _) => const SellerProductFormScreen()),
      GoRoute(
        path: ':id',
        builder: (_, s) {
          final id = int.tryParse(s.pathParameters['id'] ?? '');
          return id == null ? SoukNotFoundScreen(location: s.uri.toString()) : SellerProductFormScreen(id: id);
        },
      ),
    ];

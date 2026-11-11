import 'package:flutter/material.dart';
import 'package:go_router/go_router.dart';

import '../../core/widgets/souk_kit.dart';
import '../../l10n/l10n.dart';
import '../seller_shared/rm.dart';
import 'live.dart';
import 'models.dart';

/// One page of a list from the API, plus whether it came from the snapshot.
typedef PageOf<T> = ({List<T> items, PageMeta meta, bool fromCache, DateTime? fetchedAt});

/// A paged API list: page 1 on [reload], the next page appended on [more].
/// A stale answer (an older reload, or a "more" overtaken by a reload) is dropped.
class PagedList<T> extends ChangeNotifier {
  PagedList(this._fetch);

  final Future<PageOf<T>> Function(int page) _fetch;
  final items = <T>[];
  PageOf<T>? last;
  Object? error;
  bool loading = false;
  bool _moreFailed = false;
  bool _disposed = false;
  int _seq = 0;

  bool get hasMore => last?.meta.hasMore ?? false;

  /// Page 1 again. [clear] drops the old rows first (new filter: skeleton, not stale rows).
  Future<void> reload({bool clear = false}) {
    if (clear) {
      items.clear();
      last = null;
    }
    return _load(1);
  }

  /// The next page; a failure is rethrown for the button to show.
  Future<void> more() async {
    if (loading || !hasMore) return;
    await _load(last!.meta.page + 1);
  }

  /// Scroll-end trigger: quiet, and stops after a failure until the button is tapped.
  void autoMore() {
    if (_moreFailed) return;
    more().catchError((Object _) {});
  }

  Future<void> _load(int page) async {
    final seq = ++_seq;
    loading = true;
    if (page == 1) error = null;
    _notify();
    try {
      final p = await _fetch(page);
      if (seq != _seq || _disposed) return;
      if (page == 1) items.clear();
      items.addAll(p.items);
      last = p;
      _moreFailed = false;
    } catch (e) {
      if (seq != _seq || _disposed) return;
      if (page > 1) {
        _moreFailed = true;
        rethrow;
      }
      error = e;
    } finally {
      if (seq == _seq) {
        loading = false;
        _notify();
      }
    }
  }

  void _notify() {
    if (!_disposed) notifyListeners();
  }

  @override
  void dispose() {
    _disposed = true;
    super.dispose();
  }
}

/// "Load more" under a [PagedList] (the tap path when scrolling did not
/// fetch, or after a failed page); nothing on the last page.
class LoadMoreButton extends StatelessWidget {
  const LoadMoreButton(this.list, {super.key});

  final PagedList<dynamic> list;

  @override
  Widget build(BuildContext context) {
    if (!list.hasMore) return const SizedBox.shrink();
    return Padding(
      padding: const EdgeInsets.only(top: SoukSpace.s4),
      child: Center(
        child: SoukButton(
          label: context.l10n.ordersLoadMore,
          busy: list.loading,
          requiresOnline: true,
          onPressed: () => list.more().catchError((Object e) {
            if (context.mounted) showApiError(context, e);
          }),
        ),
      ),
    );
  }
}

/// Web Listing::certifierCodes() (HalalCertificate::BODIES keys).
// ponytail: hardcoded; serve from GET config if a body is ever added.
const kCertifyingBodies = ['JAKIM', 'MUIS', 'BPJPH', 'ESMA'];

/// Sort + price range + the web Listing's halal facets, as API query keys.
/// A null [sort] is the list's default order.
class ProductFilter {
  const ProductFilter({this.sort, this.minSen, this.maxSen, this.certifiers = const [], this.assurances = const []});

  final String? sort;
  final int? minSen;
  final int? maxSen;
  final List<String> certifiers;
  final List<String> assurances;

  /// Groups in use (sort, price, body, assurance), for the button badge.
  int get count => [sort != null, minSen != null || maxSen != null, certifiers.isNotEmpty, assurances.isNotEmpty].where((b) => b).length;

  /// True when a filter beyond sort is set (`stores/{slug}/products` takes sort only).
  bool get narrows => minSen != null || maxSen != null || certifiers.isNotEmpty || assurances.isNotEmpty;

  Map<String, dynamic> get query => {
        'sort': ?sort,
        'min_sen': ?minSen,
        'max_sen': ?maxSen,
        if (certifiers.isNotEmpty) 'certifier[]': certifiers,
        if (assurances.isNotEmpty) 'assure[]': assurances,
      };
}

/// Product list body shared by search, category and shop: the "Sort &
/// filter" button, then skeleton / error / [empty] / grid + load more.
class PagedProductGrid extends StatelessWidget {
  const PagedProductGrid({
    super.key,
    required this.list,
    required this.filter,
    required this.sorts,
    required this.onFilter,
    required this.empty,
    this.livePill = false,
  });

  final PagedList<Product> list;
  final ProductFilter filter;

  /// Sort key → label; the first is the default order.
  final Map<String, String> sorts;
  final ValueChanged<ProductFilter> onFilter;
  final Widget empty;

  /// Search only: the red Live pill beside the filter button (hidden with 0 live).
  final bool livePill;

  @override
  Widget build(BuildContext context) {
    final l = context.l10n;
    final n = filter.count;
    return Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
      Row(children: [
        SoukChip(
          key: const Key('product-filter'),
          label: n > 0 ? '${l.filterSort} · $n' : l.filterSort,
          selected: n > 0,
          onTap: () async {
            final f = await showProductFilterSheet(context, filter, sorts);
            if (f != null) onFilter(f);
          },
        ),
        if (livePill) ...[const SizedBox(width: SoukSpace.s2), const LivePill()],
      ]),
      const SizedBox(height: SoukSpace.s4),
      if (list.error != null)
        ErrorPane(error: list.error!, onRetry: list.reload, retryLabel: l.retry)
      else if (list.last == null)
        const SoukSkeleton.cards(rows: 4)
      else if (list.items.isEmpty)
        empty
      else ...[
        ProductGrid(list.items, soldLabel: l.sold, onTap: (p) => context.push('/p/${Uri.encodeComponent(p.slug)}')),
        LoadMoreButton(list, key: const Key('products-load-more')),
      ],
    ]);
  }
}

Future<ProductFilter?> showProductFilterSheet(BuildContext context, ProductFilter current, Map<String, String> sorts) =>
    showModalBottomSheet<ProductFilter>(
      context: context,
      isScrollControlled: true,
      useSafeArea: true,
      builder: (_) => _FilterSheet(current: current, sorts: sorts),
    );

class _FilterSheet extends StatefulWidget {
  const _FilterSheet({required this.current, required this.sorts});

  final ProductFilter current;
  final Map<String, String> sorts;

  @override
  State<_FilterSheet> createState() => _FilterSheetState();
}

class _FilterSheetState extends State<_FilterSheet> {
  late String _sort = widget.current.sort ?? widget.sorts.keys.first;
  late final _min = TextEditingController(text: rmInput(widget.current.minSen));
  late final _max = TextEditingController(text: rmInput(widget.current.maxSen));
  late final _certifiers = [...widget.current.certifiers];
  late final _assurances = [...widget.current.assurances];
  String? _error;

  @override
  void dispose() {
    _min.dispose();
    _max.dispose();
    super.dispose();
  }

  void _apply() {
    int? sen(TextEditingController c) => c.text.trim().isEmpty ? null : parseRm(c.text) ?? -1;
    final min = sen(_min), max = sen(_max);
    if (min == -1 || max == -1 || (min != null && max != null && min > max)) {
      setState(() => _error = context.l10n.filterPriceInvalid);
      return;
    }
    Navigator.of(context).pop(ProductFilter(
      sort: _sort == widget.sorts.keys.first ? null : _sort,
      minSen: min,
      maxSen: max,
      certifiers: _certifiers,
      assurances: _assurances,
    ));
  }

  /// One titled group of toggle chips (the web Listing's halal facets).
  Widget _chips(String title, List<(String, String)> chips, List<String> on, String keyPrefix) => Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          const SizedBox(height: SoukSpace.s5),
          Text(title, style: SoukText.label),
          const SizedBox(height: SoukSpace.s2),
          Wrap(spacing: SoukSpace.s2, runSpacing: SoukSpace.s2, children: [
            for (final (code, label) in chips)
              SoukChip(
                key: Key('$keyPrefix-$code'),
                label: label,
                selected: on.contains(code),
                onTap: () => setState(() => on.contains(code) ? on.remove(code) : on.add(code)),
              ),
          ]),
        ],
      );

  @override
  Widget build(BuildContext context) {
    final l = context.l10n;
    return Padding(
      padding: EdgeInsets.only(bottom: MediaQuery.viewInsetsOf(context).bottom),
      child: Column(
        key: const Key('filter-sheet'),
        mainAxisSize: MainAxisSize.min,
        children: [
          Padding(
            padding: const EdgeInsets.fromLTRB(SoukSpace.page, SoukSpace.s3, SoukSpace.s2, SoukSpace.s2),
            child: Row(children: [
              Expanded(child: Text(l.filterSort, style: SoukText.displayH3)),
              IconButton(tooltip: l.close, icon: const Icon(Icons.close_rounded), onPressed: () => Navigator.of(context).pop()),
            ]),
          ),
          const Divider(height: 1),
          Flexible(
            child: SingleChildScrollView(
              padding: const EdgeInsets.fromLTRB(SoukSpace.page, SoukSpace.s4, SoukSpace.page, SoukSpace.s4),
              child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
                Text(l.sort, style: SoukText.label),
                const SizedBox(height: SoukSpace.s2),
                Wrap(spacing: SoukSpace.s2, runSpacing: SoukSpace.s2, children: [
                  for (final e in widget.sorts.entries)
                    SoukChip(key: Key('filter-sort-${e.key}'), label: e.value, selected: _sort == e.key, onTap: () => setState(() => _sort = e.key)),
                ]),
                const SizedBox(height: SoukSpace.s5),
                Text(l.filterPrice, style: SoukText.label),
                const SizedBox(height: SoukSpace.s2),
                Row(children: [
                  Expanded(
                    child: SoukTextField(
                      label: l.filterMin,
                      fieldKey: const Key('filter-min'),
                      controller: _min,
                      keyboardType: const TextInputType.numberWithOptions(decimal: true),
                    ),
                  ),
                  const SizedBox(width: SoukSpace.s3),
                  Expanded(
                    child: SoukTextField(
                      label: l.filterMax,
                      fieldKey: const Key('filter-max'),
                      controller: _max,
                      keyboardType: const TextInputType.numberWithOptions(decimal: true),
                    ),
                  ),
                ]),
                if (_error != null)
                  Padding(
                    padding: const EdgeInsets.only(top: SoukSpace.s2),
                    child: Text(_error!, key: const Key('filter-error'), style: SoukText.small.copyWith(color: SoukColors.danger)),
                  ),
                // Web Listing's halal facets: certifying body (any of) + assurance (all of).
                _chips(l.halalCertifyingBody, [for (final b in kCertifyingBodies) (b, b)], _certifiers, 'filter-certifier'),
                _chips(l.halalAssurance, [
                  ('valid12', l.halalAssureValid12),
                  ('dedicated', l.halalAssureDedicated),
                  ('export', l.halalAssureExport),
                ], _assurances, 'filter-assure'),
              ]),
            ),
          ),
          const Divider(height: 1),
          Padding(
            padding: const EdgeInsets.fromLTRB(SoukSpace.page, SoukSpace.s3, SoukSpace.page, SoukSpace.s3),
            child: Row(children: [
              Expanded(
                child: SoukButton(
                  key: const Key('filter-reset'),
                  label: l.filterReset,
                  expand: true,
                  onPressed: () => Navigator.of(context).pop(const ProductFilter()),
                ),
              ),
              const SizedBox(width: SoukSpace.s3),
              Expanded(
                child: SoukButton(
                  key: const Key('filter-apply'),
                  label: l.filterApply,
                  variant: SoukButtonVariant.primary,
                  expand: true,
                  onPressed: _apply,
                ),
              ),
            ]),
          ),
        ],
      ),
    );
  }
}

import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../../core/widgets/souk_kit.dart';
import '../../l10n/l10n.dart';
import 'catalog_api.dart';
import 'models.dart';
import 'paged.dart';

/// The `products` / `stores/{slug}/products` sort keys, default first.
Map<String, String> catalogSorts(AppLocalizations l) => {
      'latest': l.sortNewest,
      'top': l.sortPopular,
      'price_asc': l.sortPriceAsc,
      'price_desc': l.sortPriceDesc,
    };

/// `/c/:slug` — products filtered by category (`GET products?category=`),
/// sort + filter sheet, next page on scroll.
class CategoryScreen extends ConsumerStatefulWidget {
  const CategoryScreen({super.key, required this.slug});

  final String slug;

  @override
  ConsumerState<CategoryScreen> createState() => _CategoryScreenState();
}

class _CategoryScreenState extends ConsumerState<CategoryScreen> {
  var _filter = const ProductFilter();
  late final _list = PagedList<Product>((page) async =>
      (await ref.read(catalogApiProvider).products(category: widget.slug, filter: _filter, page: page)).paged)
    ..reload();

  @override
  void dispose() {
    _list.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final l = context.l10n;
    final name = ref.watch(categoriesProvider).value?.data.where((c) => c.slug == widget.slug).firstOrNull?.name;

    return ListenableBuilder(
      listenable: _list,
      builder: (context, _) => SoukPage(
        title: name ?? l.category,
        scrollable: true,
        onRefresh: _list.reload,
        onScrollEnd: _list.autoMore,
        fromCache: _list.last?.fromCache ?? false,
        cachedAt: _list.last?.fetchedAt,
        body: PagedProductGrid(
          list: _list,
          filter: _filter,
          sorts: catalogSorts(l),
          onFilter: (f) {
            setState(() => _filter = f);
            _list.reload(clear: true);
          },
          empty: SoukEmptyState(
            key: const Key('category-empty'),
            title: l.noProductsYet,
            message: l.noProductsBody,
            action: SoukButton(label: l.backToHome, onPressed: () => context.go('/home')),
          ),
        ),
      ),
    );
  }
}

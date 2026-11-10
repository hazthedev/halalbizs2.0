import 'dart:async';

import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:font_awesome_flutter/font_awesome_flutter.dart';
import 'package:go_router/go_router.dart';

import '../../core/offline/offline.dart';
import '../../core/widgets/souk_kit.dart';
import '../../l10n/l10n.dart';
import 'catalog_api.dart';
import 'models.dart';
import 'paged.dart';

/// Debounce between the last keystroke and the request.
const kSearchDebounce = Duration(milliseconds: 300);

/// Search tab: query (min 2 chars, 300 ms debounce) + category chips +
/// the sort/filter sheet. Online-only; offline shows "Search needs a connection".
class SearchScreen extends ConsumerStatefulWidget {
  const SearchScreen({super.key});

  @override
  ConsumerState<SearchScreen> createState() => _SearchScreenState();
}

class _SearchScreenState extends ConsumerState<SearchScreen> {
  final _query = TextEditingController();
  Timer? _debounce;
  String? _category;
  var _filter = const ProductFilter();

  /// A query of 2+ characters has been sent (else the hint shows).
  bool _active = false;
  bool _offline = false;
  late final _list = PagedList<Product>((page) async => (await ref
          .read(catalogApiProvider)
          .search(_query.text.trim(), category: _category, filter: _filter, page: page))
      .paged);

  @override
  void dispose() {
    _debounce?.cancel();
    _query.dispose();
    _list.dispose();
    super.dispose();
  }

  void _onChanged(String _) {
    _debounce?.cancel();
    _debounce = Timer(kSearchDebounce, _run);
  }

  void _run() {
    if (_query.text.trim().length < 2) {
      setState(() => _active = false);
      return;
    }
    final offline = ref.read(isOfflineProvider).value ?? false;
    setState(() {
      _active = true;
      _offline = offline;
    });
    if (!offline) _list.reload(clear: true);
  }

  @override
  Widget build(BuildContext context) {
    final l = context.l10n;
    final categories = ref.watch(categoriesProvider).value?.data ?? const [];
    final sorts = {
      'relevance': l.sortRelevance,
      'top': l.sortPopular,
      'latest': l.sortNewest,
      'price_asc': l.sortPriceAsc,
      'price_desc': l.sortPriceDesc,
    };

    return SoukPage(
      title: l.tabSearch,
      showBack: false,
      titleWidget: TextField(
        key: const Key('search-input'),
        controller: _query,
        autofocus: false,
        textInputAction: TextInputAction.search,
        onChanged: _onChanged,
        onSubmitted: (_) {
          _debounce?.cancel();
          _run();
        },
        style: SoukText.body,
        decoration: InputDecoration(
          hintText: l.searchHint,
          contentPadding: const EdgeInsets.symmetric(horizontal: SoukSpace.s4, vertical: 10),
          prefixIcon: const Padding(
            padding: EdgeInsets.only(left: SoukSpace.s3, right: SoukSpace.s2),
            child: Center(widthFactor: 1, child: FaIcon(FontAwesomeIcons.magnifyingGlass, size: 14, color: SoukColors.inkFaint)),
          ),
          suffixIcon: ListenableBuilder(
            listenable: _query,
            builder: (_, _) => _query.text.isEmpty
                ? const SizedBox.shrink()
                : IconButton(
                    key: const Key('search-clear'),
                    tooltip: l.clear,
                    icon: const Icon(Icons.close_rounded, size: 18, color: SoukColors.inkFaint),
                    onPressed: () {
                      _query.clear();
                      _onChanged('');
                    },
                  ),
          ),
        ),
      ),
      scrollable: true,
      onScrollEnd: _list.autoMore,
      body: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          if (categories.isNotEmpty)
            SizedBox(
              height: SoukSpace.minTap,
              child: ListView(
                scrollDirection: Axis.horizontal,
                children: [
                  SoukChip(
                    key: const Key('search-category-all'),
                    label: l.allCategories,
                    selected: _category == null,
                    onTap: () {
                      setState(() => _category = null);
                      _run();
                    },
                  ),
                  for (final c in categories) ...[
                    const SizedBox(width: SoukSpace.s2),
                    SoukChip(
                      key: Key('search-category-${c.slug}'),
                      label: c.name,
                      selected: _category == c.slug,
                      onTap: () {
                        setState(() => _category = c.slug);
                        _run();
                      },
                    ),
                  ],
                ],
              ),
            ),
          const SizedBox(height: SoukSpace.s3),
          _body(l, sorts),
        ],
      ),
    );
  }

  Widget _body(AppLocalizations l, Map<String, String> sorts) {
    if (!_active) {
      return Padding(
        padding: const EdgeInsets.only(top: SoukSpace.s6),
        child: Text(l.searchMinChars, textAlign: TextAlign.center, style: SoukText.meta),
      );
    }
    if (_offline) {
      return SoukEmptyState(
        key: const Key('search-offline'),
        title: l.searchNeedsConnection,
        message: l.searchNeedsConnectionBody,
        action: SoukButton(label: l.retry, onPressed: _run, requiresOnline: true),
      );
    }
    return ListenableBuilder(
      listenable: _list,
      builder: (context, _) => PagedProductGrid(
        list: _list,
        filter: _filter,
        sorts: sorts,
        onFilter: (f) {
          setState(() => _filter = f);
          _run();
        },
        empty: SoukEmptyState(
          key: const Key('search-empty'),
          title: l.searchNoResults(_query.text.trim()),
          message: l.searchNoResultsBody,
          action: SoukButton(label: l.tabHome, onPressed: () => context.go('/home')),
        ),
      ),
    );
  }
}

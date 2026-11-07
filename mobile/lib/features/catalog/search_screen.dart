import 'dart:async';

import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:font_awesome_flutter/font_awesome_flutter.dart';
import 'package:go_router/go_router.dart';

import '../../core/api/api_client.dart';
import '../../core/offline/offline.dart';
import '../../core/widgets/souk_kit.dart';
import '../../l10n/l10n.dart';
import 'catalog_api.dart';

/// Debounce between the last keystroke and the request.
const kSearchDebounce = Duration(milliseconds: 300);

/// Search tab: query (min 2 chars, 300 ms debounce) + category chips + sort.
/// Online-only; offline shows "Search needs a connection".
class SearchScreen extends ConsumerStatefulWidget {
  const SearchScreen({super.key});

  @override
  ConsumerState<SearchScreen> createState() => _SearchScreenState();
}

class _SearchScreenState extends ConsumerState<SearchScreen> {
  final _query = TextEditingController();
  Timer? _debounce;
  String? _category;
  String _sort = 'relevance';
  AsyncValue<ProductPage>? _results;
  int _request = 0;

  @override
  void dispose() {
    _debounce?.cancel();
    _query.dispose();
    super.dispose();
  }

  void _onChanged(String _) {
    _debounce?.cancel();
    _debounce = Timer(kSearchDebounce, _run);
  }

  Future<void> _run() async {
    final q = _query.text.trim();
    if (q.length < 2) {
      setState(() => _results = null);
      return;
    }
    if (ref.read(isOfflineProvider).value ?? false) {
      setState(() => _results = AsyncValue.error(ApiException.offline(), StackTrace.current));
      return;
    }
    final id = ++_request;
    setState(() => _results = const AsyncValue.loading());
    try {
      final page = await ref.read(catalogApiProvider).search(q,
          category: _category, sort: _sort == 'relevance' ? null : _sort);
      if (!mounted || id != _request) return;
      setState(() => _results = AsyncValue.data(page));
    } catch (e, s) {
      if (!mounted || id != _request) return;
      setState(() => _results = AsyncValue.error(e, s));
    }
  }

  @override
  Widget build(BuildContext context) {
    final l = context.l10n;
    final categories = ref.watch(categoriesProvider).value?.data ?? const [];
    final sorts = {
      'relevance': l.sortRelevance,
      'popular': l.sortPopular,
      'newest': l.sortNewest,
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
                    tooltip: l.cancel,
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
      body: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          if (categories.isNotEmpty)
            SizedBox(
              height: 40,
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
          Row(children: [
            Text(l.sort, style: SoukText.label),
            const SizedBox(width: SoukSpace.s3),
            Expanded(
              child: DropdownButtonHideUnderline(
                child: DropdownButton<String>(
                  key: const Key('search-sort'),
                  value: _sort,
                  isExpanded: true,
                  isDense: true,
                  style: SoukText.small,
                  borderRadius: BorderRadius.circular(SoukRadius.control),
                  items: [for (final e in sorts.entries) DropdownMenuItem(value: e.key, child: Text(e.value))],
                  onChanged: (v) {
                    if (v == null) return;
                    setState(() => _sort = v);
                    _run();
                  },
                ),
              ),
            ),
          ]),
          const SizedBox(height: SoukSpace.s4),
          _body(l),
        ],
      ),
    );
  }

  Widget _body(AppLocalizations l) {
    final r = _results;
    if (r == null) {
      return Padding(
        padding: const EdgeInsets.only(top: SoukSpace.s6),
        child: Text(l.searchMinChars, textAlign: TextAlign.center, style: SoukText.meta),
      );
    }
    return r.when(
      loading: () => const SoukSkeleton.cards(rows: 4),
      error: (e, _) => e is ApiException && e.isOffline
          ? SoukEmptyState(
              key: const Key('search-offline'),
              title: l.searchNeedsConnection,
              message: l.searchNeedsConnectionBody,
              action: SoukButton(label: l.retry, onPressed: _run, requiresOnline: true),
            )
          : ErrorPane(error: e, onRetry: _run, retryLabel: l.retry),
      data: (p) => p.items.isEmpty
          ? SoukEmptyState(
              key: const Key('search-empty'),
              title: l.searchNoResults(_query.text.trim()),
              message: l.searchNoResultsBody,
              action: SoukButton(label: l.tabHome, onPressed: () => context.go('/home')),
            )
          : ProductGrid(p.items, soldLabel: l.sold, onTap: (p) => context.push('/p/${p.slug}')),
    );
  }
}

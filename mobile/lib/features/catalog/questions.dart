import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import 'package:intl/intl.dart';

import '../../core/auth/session.dart';
import '../../core/widgets/souk_kit.dart';
import '../../l10n/l10n.dart';
import 'catalog_api.dart';
import 'models.dart';
import 'paged.dart';

/// Product page "Q&A" tab (web ProductQuestions): answered and awaiting
/// questions newest first, 10 a page, and an ask form for a signed-in buyer
/// (10-500 characters; the server also allows 3 a minute). A guest sees a
/// "Log in to ask" line. Built only when the tab opens, like the web's lazy island.
class QuestionsPanel extends ConsumerStatefulWidget {
  const QuestionsPanel({super.key, required this.slug});

  final String slug;

  @override
  ConsumerState<QuestionsPanel> createState() => _QuestionsPanelState();
}

class _QuestionsPanelState extends ConsumerState<QuestionsPanel> {
  late final _list = PagedList<Question>(
    (page) => ref.read(catalogApiProvider).questions(widget.slug, page: page),
  )..reload();
  final _input = TextEditingController();
  String? _error;
  bool _sending = false;

  @override
  void dispose() {
    _list.dispose();
    _input.dispose();
    super.dispose();
  }

  Future<void> _ask() async {
    final l = context.l10n;
    final text = _input.text.trim();
    if (text.isEmpty) return setState(() => _error = l.qaTypeFirst);
    if (text.length < 10) return setState(() => _error = l.qaTooShort);
    setState(() {
      _error = null;
      _sending = true;
    });
    try {
      await ref.read(catalogApiProvider).ask(widget.slug, text);
      if (!mounted) return;
      _input.clear();
      ScaffoldMessenger.of(context)
        ..clearSnackBars()
        ..showSnackBar(SnackBar(content: Text(l.qaSent)));
      // Held for review on the web too: the list shows only visible questions.
      await _list.reload();
    } catch (e) {
      if (mounted) showApiError(context, e);
    } finally {
      if (mounted) setState(() => _sending = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    final l = context.l10n;
    final signedIn = ref.watch(sessionProvider) is SignedIn;
    return ListenableBuilder(
      listenable: _list,
      builder: (context, _) {
        final total = (_list.last?.meta.total ?? 0) > 0
            ? _list.last!.meta.total
            : _list.items.length;
        return Column(
          key: const Key('product-qa'),
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: [
            Text(l.qaTitle(total), style: SoukText.displayH3),
            const SizedBox(height: SoukSpace.s3),
            if (signedIn)
              SoukCard(
                padding: const EdgeInsets.all(SoukSpace.s4),
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.stretch,
                  children: [
                    Text(l.qaAskLabel, style: SoukText.label),
                    const SizedBox(height: SoukSpace.s2),
                    TextField(
                      key: const Key('qa-input'),
                      controller: _input,
                      minLines: 2,
                      maxLines: 4,
                      maxLength: 500,
                      style: SoukText.body,
                      decoration: InputDecoration(
                        hintText: l.qaAskHint,
                        errorText: _error,
                      ),
                    ),
                    Align(
                      alignment: Alignment.centerRight,
                      child: SoukButton(
                        key: const Key('qa-post'),
                        label: l.qaPost,
                        variant: SoukButtonVariant.primary,
                        compact: true,
                        busy: _sending,
                        requiresOnline: true,
                        onPressed: _ask,
                      ),
                    ),
                  ],
                ),
              )
            else
              SoukCard(
                key: const Key('qa-login'),
                padding: const EdgeInsets.all(SoukSpace.s4),
                onTap: () => context.push(
                  '/login?from=${Uri.encodeComponent('/p/${widget.slug}')}',
                ),
                child: Text.rich(
                  TextSpan(
                    style: SoukText.small,
                    children: [
                      TextSpan(
                        text: l.qaLogin,
                        style: const TextStyle(
                          color: SoukColors.emerald,
                          fontWeight: FontWeight.w600,
                        ),
                      ),
                      TextSpan(text: ' ${l.qaLoginToAsk}'),
                    ],
                  ),
                ),
              ),
            const SizedBox(height: SoukSpace.s3),
            if (_list.error != null)
              ErrorPane(
                error: _list.error!,
                onRetry: _list.reload,
                retryLabel: l.retry,
              )
            else if (_list.last == null)
              const SoukSkeleton.list(rows: 3)
            else if (_list.items.isEmpty)
              Text(l.qaEmpty, key: const Key('qa-empty'), style: SoukText.small)
            else ...[
              for (final q in _list.items) _QuestionTile(q),
              LoadMoreButton(_list, key: const Key('qa-load-more')),
            ],
          ],
        );
      },
    );
  }
}

class _QuestionTile extends StatelessWidget {
  const _QuestionTile(this.q);

  final Question q;

  String _date(BuildContext context, DateTime? d) => d == null
      ? ''
      : DateFormat(
          'd MMM yyyy',
          Localizations.localeOf(context).toString(),
        ).format(d.toLocal());

  @override
  Widget build(BuildContext context) {
    final l = context.l10n;
    return Container(
      key: Key('qa-${q.id}'),
      padding: const EdgeInsets.symmetric(vertical: SoukSpace.s3),
      decoration: const BoxDecoration(
        border: Border(bottom: BorderSide(color: SoukColors.line)),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          _line(
            'Q',
            q.question,
            '${q.askerName} · ${_date(context, q.createdAt)}',
            SoukColors.inkFaint,
          ),
          const SizedBox(height: SoukSpace.s2),
          if (q.answer != null)
            Padding(
              padding: const EdgeInsets.only(left: SoukSpace.s5),
              child: _line(
                'A',
                q.answer!,
                '${l.qaSeller} · ${_date(context, q.answeredAt)}',
                SoukColors.emerald,
              ),
            )
          else
            Padding(
              padding: const EdgeInsets.only(left: SoukSpace.s5),
              child: Text(
                l.qaAwaiting,
                key: Key('qa-${q.id}-awaiting'),
                style: SoukText.small.copyWith(
                  color: SoukColors.warn,
                  fontWeight: FontWeight.w600,
                ),
              ),
            ),
        ],
      ),
    );
  }

  Widget _line(String mark, String text, String meta, Color color) => Row(
    crossAxisAlignment: CrossAxisAlignment.start,
    children: [
      SizedBox(
        width: SoukSpace.s5,
        child: Text(mark, style: SoukText.label.copyWith(color: color)),
      ),
      Expanded(
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Text(text, style: SoukText.body),
            Text(meta, style: SoukText.meta),
          ],
        ),
      ),
    ],
  );
}

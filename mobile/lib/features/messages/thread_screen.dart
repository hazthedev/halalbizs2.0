import 'dart:async';

import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:font_awesome_flutter/font_awesome_flutter.dart';
import 'package:go_router/go_router.dart';

import '../../core/api/json.dart';
import '../../core/offline/offline.dart';
import '../../core/widgets/souk_kit.dart';
import '../../l10n/l10n.dart';
import 'messages_api.dart';

/// `/account/messages/:id` — the thread, newest at the bottom, polled every
/// [pollEvery] while open; composer → `POST conversations/{id}/messages`.
class ThreadScreen extends ConsumerStatefulWidget {
  const ThreadScreen({super.key, required this.id});

  final int id;

  static Duration pollEvery = const Duration(seconds: 10);

  @override
  ConsumerState<ThreadScreen> createState() => _ThreadScreenState();
}

class _ThreadScreenState extends ConsumerState<ThreadScreen> with WidgetsBindingObserver {
  Thread? _thread;
  Object? _error;
  Timer? _timer;
  final _input = TextEditingController();
  final _scroll = ScrollController();
  bool _sending = false;
  bool _visible = true;
  bool _paused = false;

  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addObserver(this);
    _load();
    _timer = Timer.periodic(ThreadScreen.pollEvery, (_) => _load(silent: true));
  }

  @override
  void didChangeDependencies() {
    super.didChangeDependencies();
    // Offstage in a tab that is not showing: no point polling.
    _visible = TickerMode.valuesOf(context).enabled;
    _syncTimer();
  }

  @override
  void didChangeAppLifecycleState(AppLifecycleState state) {
    _paused = state != AppLifecycleState.resumed;
    _syncTimer();
  }

  void _syncTimer() {
    if (!_visible || _paused) {
      _timer?.cancel();
      _timer = null;
    } else if (_timer == null) {
      _timer = Timer.periodic(ThreadScreen.pollEvery, (_) => _load(silent: true));
      _load(silent: true);
    }
  }

  @override
  void dispose() {
    WidgetsBinding.instance.removeObserver(this);
    _timer?.cancel();
    _input.dispose();
    _scroll.dispose();
    super.dispose();
  }

  Future<void> _load({bool silent = false}) async {
    try {
      final t = await ref.read(messagesApiProvider).thread(widget.id);
      if (!mounted) return;
      final grew = t.messages.length != (_thread?.messages.length ?? -1);
      setState(() {
        _thread = t;
        _error = null;
      });
      if (grew) {
        _scrollToEnd();
        // New messages (or the first load, which marks the thread read) change the list's unread counts.
        ref.invalidate(conversationsProvider);
      }
    } catch (e) {
      if (!mounted || silent) return;
      setState(() => _error = e);
    }
  }

  void _scrollToEnd() {
    WidgetsBinding.instance.addPostFrameCallback((_) {
      if (_scroll.hasClients) _scroll.jumpTo(_scroll.position.maxScrollExtent);
    });
  }

  Future<void> _send() async {
    final body = _input.text.trim();
    if (body.isEmpty || _sending) return;
    setState(() => _sending = true);
    try {
      await ref.read(messagesApiProvider).send(widget.id, body);
      if (!mounted) return;
      _input.clear();
      await _load(silent: true);
    } catch (e) {
      if (mounted) showApiError(context, e);
    } finally {
      if (mounted) setState(() => _sending = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    final l = context.l10n;
    final t = _thread;
    final offline = ref.watch(isOfflineProvider).value ?? false;
    final mine = t?.conversation.side ?? 'buyer';
    return SoukPage(
      title: t?.conversation.title ?? l.messages,
      padding: EdgeInsets.zero,
      bottomBar: Row(crossAxisAlignment: CrossAxisAlignment.end, children: [
        Expanded(
          child: TextField(
            key: const Key('thread-input'),
            controller: _input,
            minLines: 1,
            maxLines: 4,
            textInputAction: TextInputAction.send,
            onSubmitted: (_) => _send(),
            enabled: !offline,
            style: SoukText.body,
            decoration: InputDecoration(hintText: l.messagesTypeMessage),
          ),
        ),
        const SizedBox(width: SoukSpace.s2),
        SoukButton(
          key: const Key('thread-send'),
          label: l.messagesSend,
          icon: FontAwesomeIcons.paperPlane,
          variant: SoukButtonVariant.primary,
          compact: true,
          requiresOnline: true,
          busy: _sending,
          onPressed: _send,
        ),
      ]),
      body: t == null
          ? (_error != null ? ErrorPane(error: _error!, onRetry: _load, retryLabel: l.retry) : const Padding(padding: EdgeInsets.all(SoukSpace.page), child: SoukSkeleton.list(rows: 5)))
          : t.messages.isEmpty
              ? Center(child: SoukEmptyState(key: const Key('thread-empty'), title: l.messagesNoMessagesYet, message: l.messagesSayHello))
              : ListView.builder(
                  key: const Key('thread-list'),
                  controller: _scroll,
                  padding: const EdgeInsets.fromLTRB(SoukSpace.page, SoukSpace.s3, SoukSpace.page, SoukSpace.s3),
                  itemCount: t.messages.length,
                  itemBuilder: (_, i) => _Bubble(t.messages[i], mine: t.messages[i].side == mine, onProduct: (slug) => context.push('/p/$slug')),
                ),
    );
  }
}

class _Bubble extends StatelessWidget {
  const _Bubble(this.m, {required this.mine, required this.onProduct});

  final ChatMessage m;
  final bool mine;
  final ValueChanged<String> onProduct;

  @override
  Widget build(BuildContext context) {
    final p = m.product;
    final slug = asStr(p?['slug']);
    final at = m.createdAt?.toLocal();
    return Align(
      alignment: mine ? Alignment.centerRight : Alignment.centerLeft,
      child: Container(
        key: Key('message-${m.id}'),
        constraints: const BoxConstraints(maxWidth: 300),
        margin: const EdgeInsets.only(bottom: SoukSpace.s2),
        padding: const EdgeInsets.symmetric(horizontal: SoukSpace.s3, vertical: SoukSpace.s2),
        decoration: BoxDecoration(
          color: mine ? SoukColors.emeraldTint : SoukColors.surface,
          borderRadius: BorderRadius.circular(SoukRadius.panel),
          border: Border.all(color: mine ? SoukColors.emeraldTintEdge : SoukColors.line),
        ),
        child: Column(crossAxisAlignment: CrossAxisAlignment.start, mainAxisSize: MainAxisSize.min, children: [
          if (p != null)
            InkWell(
              onTap: slug == null ? null : () => onProduct(slug),
              child: Padding(
                padding: const EdgeInsets.only(bottom: SoukSpace.s2),
                child: Row(mainAxisSize: MainAxisSize.min, children: [
                  SizedBox(width: 36, height: 36, child: ProductImage(asStr(p['thumb']), radius: 8)),
                  const SizedBox(width: SoukSpace.s2),
                  Flexible(child: Text(asStr(p['name']) ?? '', style: SoukText.meta, maxLines: 2, overflow: TextOverflow.ellipsis)),
                ]),
              ),
            ),
          Text(m.body, style: SoukText.body),
          if (at != null)
            Text('${at.hour.toString().padLeft(2, '0')}:${at.minute.toString().padLeft(2, '0')}',
                style: SoukText.meta.copyWith(fontSize: 11)),
        ]),
      ),
    );
  }
}

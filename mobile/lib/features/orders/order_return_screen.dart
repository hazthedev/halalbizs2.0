import 'dart:typed_data';

import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import 'package:image_picker/image_picker.dart';

import '../../core/api/api_client.dart';
import '../../core/api/json.dart';
import '../../core/widgets/souk_kit.dart';
import '../../l10n/l10n.dart';
import 'orders_api.dart';

/// `/orders/:id/return` — reason (from `GET return-reasons`), note, up to
/// five photos, multipart `POST orders/{id}/return`.
class OrderReturnScreen extends ConsumerStatefulWidget {
  const OrderReturnScreen({super.key, required this.id});

  final int id;

  static const maxPhotos = 5;

  @override
  ConsumerState<OrderReturnScreen> createState() => _OrderReturnScreenState();
}

class _OrderReturnScreenState extends ConsumerState<OrderReturnScreen> {
  int? _reasonId;
  final _note = TextEditingController();
  final _photos = <XFile>[];
  final _previews = <Uint8List>[];
  bool _busy = false;
  Object? _error;

  @override
  void dispose() {
    _note.dispose();
    super.dispose();
  }

  Future<void> _pick() async {
    final remaining = OrderReturnScreen.maxPhotos - _photos.length;
    if (remaining <= 0) return;
    try {
      final files = await ImagePicker().pickMultiImage(imageQuality: 80, limit: remaining);
      final take = files.take(remaining).toList();
      final bytes = [for (final f in take) await f.readAsBytes()];
      if (!mounted) return;
      setState(() {
        _photos.addAll(take);
        _previews.addAll(bytes);
      });
    } catch (e) {
      if (mounted) showApiError(context, e);
    }
  }

  Future<void> _submit() async {
    final l = context.l10n;
    final reasonId = _reasonId;
    if (reasonId == null) {
      setState(() => _error = ApiException('validation_failed', l.fieldRequired, status: 422, fields: {'reason_id': [l.fieldRequired]}));
      return;
    }
    final ok = await showSoukConfirm(context,
        title: l.ordersReturnConfirmTitle, message: l.ordersReturnConfirmBody, confirmLabel: l.ordersReturnSubmit, cancelLabel: l.cancel);
    if (!ok || !mounted) return;
    setState(() {
      _busy = true;
      _error = null;
    });
    final router = GoRouter.of(context);
    final messenger = ScaffoldMessenger.of(context);
    try {
      final note = _note.text.trim();
      await ref.read(ordersApiProvider).submitReturn(widget.id, reasonId: reasonId, note: note.isEmpty ? null : note, photos: _photos);
      if (!mounted) return;
      ref.invalidate(orderDetailProvider(widget.id));
      ref.invalidate(orderBucketsProvider);
      messenger
        ..clearSnackBars()
        ..showSnackBar(SnackBar(content: Text(l.ordersReturnSubmitted)));
      router.pop();
    } catch (e) {
      if (!mounted) return;
      setState(() => _error = e);
      // `photos.N` lands on the photo strip; anything else has no input to sit under.
      if (!fieldsCovered(e, const ['reason_id', 'note', 'photos'])) showApiError(context, e);
    } finally {
      if (mounted) setState(() => _busy = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    final l = context.l10n;
    final reasons = ref.watch(returnReasonsProvider);
    return SoukPage(
      title: l.ordersRequestReturn,
      scrollable: true,
      bottomBar: SoukButton(
        key: const Key('return-submit'),
        label: l.ordersReturnSubmit,
        variant: SoukButtonVariant.primary,
        expand: true,
        requiresOnline: true,
        busy: _busy,
        onPressed: _submit,
      ),
      body: Column(crossAxisAlignment: CrossAxisAlignment.stretch, children: [
        reasons.when(
          loading: () => const SoukSkeleton.block(height: 48),
          error: (e, _) => ErrorPane(error: e, onRetry: () => ref.invalidate(returnReasonsProvider), retryLabel: l.retry),
          data: (list) => SoukDropdown<int>(
            key: const Key('return-reason'),
            label: l.ordersReason,
            required: true,
            hint: l.ordersReasonHint,
            options: {for (final r in list) asInt(r['id']) ?? 0: asStr(r['label']) ?? ''},
            value: _reasonId,
            error: fieldError(_error, 'reason_id'),
            onChanged: (v) => setState(() => _reasonId = v),
          ),
        ),
        const SizedBox(height: SoukSpace.s4),
        SoukTextField(
          label: l.ordersNote,
          fieldKey: const Key('return-note'),
          controller: _note,
          maxLines: 4,
          hint: l.ordersReturnNoteHint,
          error: fieldError(_error, 'note'),
        ),
        const SizedBox(height: SoukSpace.s4),
        Text(l.ordersPhotos, style: SoukText.label),
        const SizedBox(height: 6),
        Wrap(spacing: SoukSpace.s2, runSpacing: SoukSpace.s2, children: [
          for (var i = 0; i < _previews.length; i++)
            Stack(children: [
              ClipRRect(
                borderRadius: BorderRadius.circular(SoukRadius.control),
                child: Image.memory(_previews[i], width: 72, height: 72, fit: BoxFit.cover),
              ),
              Positioned(
                right: 0,
                top: 0,
                child: InkWell(
                  key: Key('return-photo-remove-$i'),
                  onTap: () => setState(() {
                    _photos.removeAt(i);
                    _previews.removeAt(i);
                  }),
                  child: Container(
                    decoration: const BoxDecoration(color: SoukColors.overlay, shape: BoxShape.circle),
                    padding: const EdgeInsets.all(4),
                    child: const Icon(Icons.close_rounded, size: 14, color: Colors.white),
                  ),
                ),
              ),
            ]),
          if (_photos.length < OrderReturnScreen.maxPhotos)
            InkWell(
              key: const Key('return-photo-add'),
              onTap: _pick,
              borderRadius: BorderRadius.circular(SoukRadius.control),
              child: Container(
                width: 72,
                height: 72,
                decoration: BoxDecoration(
                  color: SoukColors.cream,
                  borderRadius: BorderRadius.circular(SoukRadius.control),
                  border: Border.all(color: SoukColors.lineStrong),
                ),
                child: const Icon(Icons.add_a_photo_outlined, color: SoukColors.inkFaint),
              ),
            ),
        ]),
        const SizedBox(height: SoukSpace.s1),
        Text(fieldErrorAt(_error, 'photos') ?? l.ordersPhotosHelp(OrderReturnScreen.maxPhotos),
            style: fieldErrorAt(_error, 'photos') != null ? SoukText.error : SoukText.meta),
      ]),
    );
  }
}

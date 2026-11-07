import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:font_awesome_flutter/font_awesome_flutter.dart';
import 'package:go_router/go_router.dart';

import '../../core/api/api_client.dart';
import '../../core/widgets/souk_kit.dart';
import '../../l10n/l10n.dart';
import 'account_api.dart';

/// `/account/addresses` — the address book; tap to edit, "Add address".
class AddressesScreen extends ConsumerWidget {
  const AddressesScreen({super.key});

  Future<void> _refresh(WidgetRef ref) async {
    ref.invalidate(addressesProvider);
    try {
      await ref.read(addressesProvider.future);
    } catch (_) {}
  }

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final l = context.l10n;
    final async = ref.watch(addressesProvider);
    return SoukPage(
      title: l.addresses,
      scrollable: true,
      onRefresh: () => _refresh(ref),
      bottomBar: SoukButton(
        key: const Key('addresses-add'),
        label: l.accountAddAddress,
        icon: FontAwesomeIcons.plus,
        variant: SoukButtonVariant.primary,
        expand: true,
        requiresOnline: true,
        onPressed: () => context.push('/account/addresses/new'),
      ),
      body: async.when(
        skipLoadingOnRefresh: true,
        loading: () => const SoukSkeleton.list(rows: 3),
        error: (e, _) => ErrorPane(error: e, onRetry: () => _refresh(ref), retryLabel: l.retry),
        data: (list) => list.isEmpty
            ? SoukEmptyState(key: const Key('addresses-empty'), title: l.accountNoAddresses, message: l.accountNoAddressesBody)
            : Column(children: [
                for (final a in list) ...[
                  SoukCard(
                    key: Key('address-${a.id}'),
                    onTap: () => context.push('/account/addresses/${a.id}'),
                    child: Row(children: [
                      const FaIcon(FontAwesomeIcons.locationDot, size: 16, color: SoukColors.inkFaint),
                      const SizedBox(width: SoukSpace.s3),
                      Expanded(
                        child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
                          Row(children: [
                            Flexible(child: Text(a.label ?? a.recipientName, style: SoukText.label, maxLines: 1, overflow: TextOverflow.ellipsis)),
                            if (a.isDefault) ...[const SizedBox(width: SoukSpace.s2), SoukBadge(l.accountDefault, status: 'sale')],
                          ]),
                          Text('${a.recipientName} · ${a.phone}', style: SoukText.small),
                          Text(a.oneLine, style: SoukText.meta),
                        ]),
                      ),
                      const Icon(Icons.chevron_right_rounded, color: SoukColors.inkFaint),
                    ]),
                  ),
                  const SizedBox(height: SoukSpace.s3),
                ],
              ]),
      ),
    );
  }
}

/// `/account/addresses/new` and `/account/addresses/:id` — the web form's
/// fields; `state` from `GET config`; 422 `fields` land on the inputs.
class AddressFormScreen extends ConsumerStatefulWidget {
  const AddressFormScreen({super.key, this.id});

  /// Null = create.
  final int? id;

  @override
  ConsumerState<AddressFormScreen> createState() => _AddressFormScreenState();
}

class _AddressFormScreenState extends ConsumerState<AddressFormScreen> {
  final _form = GlobalKey<FormState>();
  final _c = {for (final k in ['label', 'recipient_name', 'phone', 'line1', 'line2', 'postcode', 'city']) k: TextEditingController()};
  String? _state;
  bool _default = false;
  bool _loaded = false;
  bool _busy = false;
  Object? _error;

  @override
  void dispose() {
    for (final c in _c.values) {
      c.dispose();
    }
    super.dispose();
  }

  void _fill(Address a) {
    if (_loaded) return;
    _loaded = true;
    _c['label']!.text = a.label ?? '';
    _c['recipient_name']!.text = a.recipientName;
    _c['phone']!.text = a.phone;
    _c['line1']!.text = a.line1;
    _c['line2']!.text = a.line2 ?? '';
    _c['postcode']!.text = a.postcode;
    _c['city']!.text = a.city;
    _state = a.state;
    _default = a.isDefault;
  }

  Future<void> _save() async {
    if (!_form.currentState!.validate()) return;
    final l = context.l10n;
    setState(() {
      _busy = true;
      _error = null;
    });
    final router = GoRouter.of(context);
    final messenger = ScaffoldMessenger.of(context);
    try {
      final body = {
        for (final e in _c.entries) e.key: e.value.text.trim(),
        'state': ?_state,
        if (_default) 'is_default': true,
      };
      final api = ref.read(accountApiProvider);
      if (widget.id == null) {
        await api.createAddress(body);
      } else {
        await api.updateAddress(widget.id!, body);
      }
      if (!mounted) return;
      ref.invalidate(addressesProvider);
      messenger
        ..clearSnackBars()
        ..showSnackBar(SnackBar(content: Text(l.accountAddressSaved)));
      router.pop();
    } catch (e) {
      if (!mounted) return;
      setState(() => _error = e);
      if (e is! ApiException || !e.isValidation) showApiError(context, e);
    } finally {
      if (mounted) setState(() => _busy = false);
    }
  }

  Future<void> _delete() async {
    final l = context.l10n;
    final ok = await showSoukConfirm(context,
        title: l.accountDeleteAddressTitle, message: l.accountDeleteAddressBody, confirmLabel: l.accountDelete, cancelLabel: l.cancel, danger: true);
    if (!ok || !mounted) return;
    setState(() => _busy = true);
    final router = GoRouter.of(context);
    try {
      await ref.read(accountApiProvider).deleteAddress(widget.id!);
      if (!mounted) return;
      ref.invalidate(addressesProvider);
      router.pop();
    } catch (e) {
      if (mounted) showApiError(context, e);
    } finally {
      if (mounted) setState(() => _busy = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    final l = context.l10n;
    final states = ref.watch(statesProvider);
    final editing = widget.id != null;
    if (editing && !_loaded) {
      final existing = ref.watch(addressesProvider).value?.where((a) => a.id == widget.id).firstOrNull;
      if (existing != null) _fill(existing);
    }
    String? req(String? v) => v == null || v.trim().isEmpty ? l.fieldRequired : null;

    return SoukPage(
      title: editing ? l.accountEditAddress : l.accountAddAddress,
      scrollable: true,
      actions: [
        if (editing)
          SoukIconButton(key: const Key('address-delete'), icon: FontAwesomeIcons.trashCan, label: l.accountDelete, color: SoukColors.danger, onPressed: _busy ? null : _delete),
      ],
      bottomBar: SoukButton(
        key: const Key('address-save'),
        label: l.accountSave,
        icon: FontAwesomeIcons.check,
        variant: SoukButtonVariant.primary,
        expand: true,
        requiresOnline: true,
        busy: _busy,
        onPressed: _save,
      ),
      body: editing && !_loaded
          ? ref.watch(addressesProvider).when(
              loading: () => const SoukSkeleton.list(rows: 4),
              error: (e, _) => ErrorPane(error: e, onRetry: () => ref.invalidate(addressesProvider), retryLabel: l.retry),
              data: (_) => ErrorPane(error: ApiException('not_found', l.pageNotFoundBody, status: 404)),
            )
          : Form(
              key: _form,
              child: AutofillGroup(
                child: Column(crossAxisAlignment: CrossAxisAlignment.stretch, children: [
                  SoukTextField(label: l.accountAddressLabel, fieldKey: const Key('address-label'), controller: _c['label'], hint: l.accountAddressLabelHint, error: fieldError(_error, 'label')),
                  const SizedBox(height: SoukSpace.s4),
                  SoukTextField(label: l.accountRecipientName, fieldKey: const Key('address-recipient'), controller: _c['recipient_name'], required: true, validator: req, error: fieldError(_error, 'recipient_name'), autofillHints: const [AutofillHints.name]),
                  const SizedBox(height: SoukSpace.s4),
                  SoukTextField(label: l.accountPhone, fieldKey: const Key('address-phone'), controller: _c['phone'], required: true, validator: req, keyboardType: TextInputType.phone, error: fieldError(_error, 'phone'), autofillHints: const [AutofillHints.telephoneNumber]),
                  const SizedBox(height: SoukSpace.s4),
                  SoukTextField(label: l.accountLine1, fieldKey: const Key('address-line1'), controller: _c['line1'], required: true, validator: req, error: fieldError(_error, 'line1'), autofillHints: const [AutofillHints.streetAddressLine1]),
                  const SizedBox(height: SoukSpace.s4),
                  SoukTextField(label: l.accountLine2, fieldKey: const Key('address-line2'), controller: _c['line2'], error: fieldError(_error, 'line2'), autofillHints: const [AutofillHints.streetAddressLine2]),
                  const SizedBox(height: SoukSpace.s4),
                  Row(crossAxisAlignment: CrossAxisAlignment.start, children: [
                    SizedBox(
                      width: 130,
                      child: SoukTextField(label: l.accountPostcode, fieldKey: const Key('address-postcode'), controller: _c['postcode'], required: true, validator: req, keyboardType: TextInputType.number, error: fieldError(_error, 'postcode'), autofillHints: const [AutofillHints.postalCode]),
                    ),
                    const SizedBox(width: SoukSpace.s3),
                    Expanded(
                      child: SoukTextField(label: l.accountCity, fieldKey: const Key('address-city'), controller: _c['city'], required: true, validator: req, error: fieldError(_error, 'city'), autofillHints: const [AutofillHints.addressCity]),
                    ),
                  ]),
                  const SizedBox(height: SoukSpace.s4),
                  states.when(
                    loading: () => const SoukSkeleton.block(height: 48),
                    error: (e, _) => ErrorPane(error: e, onRetry: () => ref.invalidate(configProvider), retryLabel: l.retry),
                    data: (list) => SoukDropdown<String>(
                      key: const Key('address-state'),
                      label: l.accountState,
                      required: true,
                      hint: l.accountStateHint,
                      options: {for (final s in list) s: s},
                      value: _state,
                      error: fieldError(_error, 'state'),
                      onChanged: (v) => setState(() => _state = v),
                    ),
                  ),
                  const SizedBox(height: SoukSpace.s3),
                  SwitchListTile.adaptive(
                    key: const Key('address-default'),
                    contentPadding: EdgeInsets.zero,
                    activeTrackColor: SoukColors.emerald,
                    title: Text(l.accountSetDefault, style: SoukText.body),
                    value: _default,
                    onChanged: (v) => setState(() => _default = v),
                  ),
                ]),
              ),
            ),
    );
  }
}

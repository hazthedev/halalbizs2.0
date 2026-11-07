import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:font_awesome_flutter/font_awesome_flutter.dart';
import 'package:go_router/go_router.dart';

import '../../core/api/api_client.dart';
import '../../core/auth/session.dart';
import '../../core/widgets/souk_kit.dart';
import '../../l10n/l10n.dart';
import 'account_api.dart';

/// `/account/delete` — the PDPA / store-policy in-app deletion: explanation,
/// current password, confirm dialog, `DELETE me`, then sign out.
class DeleteAccountScreen extends ConsumerStatefulWidget {
  const DeleteAccountScreen({super.key});

  @override
  ConsumerState<DeleteAccountScreen> createState() => _DeleteAccountScreenState();
}

class _DeleteAccountScreenState extends ConsumerState<DeleteAccountScreen> {
  final _password = TextEditingController();
  bool _busy = false;
  Object? _error;

  @override
  void dispose() {
    _password.dispose();
    super.dispose();
  }

  Future<void> _delete() async {
    final l = context.l10n;
    if (_password.text.isEmpty) {
      setState(() => _error = ApiException('validation_failed', l.fieldRequired, status: 422, fields: {'password': [l.fieldRequired]}));
      return;
    }
    final ok = await showSoukConfirm(context,
        title: l.accountDeleteConfirmTitle, message: l.accountDeleteConfirmBody, confirmLabel: l.deleteAccount, cancelLabel: l.cancel, danger: true);
    if (!ok || !mounted) return;
    setState(() {
      _busy = true;
      _error = null;
    });
    final router = GoRouter.of(context);
    try {
      await ref.read(accountApiProvider).deleteAccount(_password.text);
      if (!mounted) return;
      await ref.read(sessionProvider.notifier).logout();
      router.go('/home');
    } catch (e) {
      if (!mounted) return;
      setState(() => _error = e);
      if (!fieldsCovered(e, const ['password'])) showApiError(context, e);
    } finally {
      if (mounted) setState(() => _busy = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    final l = context.l10n;
    return SoukPage(
      title: l.deleteAccount,
      scrollable: true,
      body: Column(crossAxisAlignment: CrossAxisAlignment.stretch, children: [
        SoukAlert(l.accountDeleteWarning, status: 'danger'),
        const SizedBox(height: SoukSpace.s4),
        Text(l.accountDeleteBody, style: SoukText.bodySoft),
        const SizedBox(height: SoukSpace.s5),
        SoukPasswordField(
          label: l.accountCurrentPassword,
          fieldKey: const Key('delete-password'),
          controller: _password,
          required: true,
          error: fieldError(_error, 'password'),
          textInputAction: TextInputAction.done,
          onSubmitted: (_) => _delete(),
        ),
        const SizedBox(height: SoukSpace.s5),
        SoukButton(
          key: const Key('delete-submit'),
          label: l.deleteAccount,
          icon: FontAwesomeIcons.userXmark,
          variant: SoukButtonVariant.danger,
          expand: true,
          requiresOnline: true,
          busy: _busy,
          onPressed: _delete,
        ),
        const SizedBox(height: SoukSpace.s3),
        SoukButton(label: l.cancel, variant: SoukButtonVariant.ghost, expand: true, onPressed: () => context.pop()),
      ]),
    );
  }
}

import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:font_awesome_flutter/font_awesome_flutter.dart';

import '../../core/api/api_client.dart';
import '../../core/auth/session.dart';
import '../../core/locale.dart';
import '../../core/widgets/souk_kit.dart';
import '../../l10n/l10n.dart';
import 'account_api.dart';

/// `/account/profile` — name, phone, preferred locale (`PATCH me`) and the
/// change-password form (`PUT me/password`).
class ProfileScreen extends ConsumerStatefulWidget {
  const ProfileScreen({super.key});

  @override
  ConsumerState<ProfileScreen> createState() => _ProfileScreenState();
}

class _ProfileScreenState extends ConsumerState<ProfileScreen> {
  late final TextEditingController _name;
  late final TextEditingController _phone;
  String? _locale;
  bool _saving = false;
  Object? _error;

  final _current = TextEditingController();
  final _password = TextEditingController();
  final _confirm = TextEditingController();
  bool _changing = false;
  Object? _pwError;

  @override
  void initState() {
    super.initState();
    final me = ref.read(profileProvider);
    _name = TextEditingController(text: me?.name ?? '');
    _phone = TextEditingController(text: me?.phone ?? '');
    _locale = me?.preferredLocale ?? ref.read(localeProvider).languageCode;
  }

  @override
  void dispose() {
    _name.dispose();
    _phone.dispose();
    _current.dispose();
    _password.dispose();
    _confirm.dispose();
    super.dispose();
  }

  Future<void> _save() async {
    final l = context.l10n;
    setState(() {
      _saving = true;
      _error = null;
    });
    try {
      await ref.read(accountApiProvider).updateMe(name: _name.text.trim(), phone: _phone.text.trim(), preferredLocale: _locale);
      await ref.read(sessionProvider.notifier).refreshProfile();
      if (!mounted) return;
      if (_locale != null) ref.read(localeProvider.notifier).set(Locale(_locale!));
      ScaffoldMessenger.of(context)
        ..clearSnackBars()
        ..showSnackBar(SnackBar(content: Text(l.accountProfileSaved)));
    } catch (e) {
      if (!mounted) return;
      setState(() => _error = e);
      if (e is! ApiException || !e.isValidation) showApiError(context, e);
    } finally {
      if (mounted) setState(() => _saving = false);
    }
  }

  Future<void> _changePassword() async {
    final l = context.l10n;
    if (_password.text != _confirm.text) {
      setState(() => _pwError = ApiException('validation_failed', l.passwordMismatch, status: 422, fields: {'password_confirmation': [l.passwordMismatch]}));
      return;
    }
    setState(() {
      _changing = true;
      _pwError = null;
    });
    try {
      await ref.read(accountApiProvider).changePassword(current: _current.text, password: _password.text, confirmation: _confirm.text);
      if (!mounted) return;
      _current.clear();
      _password.clear();
      _confirm.clear();
      ScaffoldMessenger.of(context)
        ..clearSnackBars()
        ..showSnackBar(SnackBar(content: Text(l.accountPasswordChanged)));
    } catch (e) {
      if (!mounted) return;
      setState(() => _pwError = e);
      if (e is! ApiException || !e.isValidation) showApiError(context, e);
    } finally {
      if (mounted) setState(() => _changing = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    final l = context.l10n;
    final me = ref.watch(profileProvider);
    return SoukPage(
      title: l.profile,
      scrollable: true,
      body: Column(crossAxisAlignment: CrossAxisAlignment.stretch, children: [
        SoukCard(
          child: Column(crossAxisAlignment: CrossAxisAlignment.stretch, children: [
            SoukTextField(label: l.name, fieldKey: const Key('profile-name'), controller: _name, required: true, error: fieldError(_error, 'name'), autofillHints: const [AutofillHints.name]),
            const SizedBox(height: SoukSpace.s4),
            SoukTextField(label: l.email, controller: TextEditingController(text: me?.email ?? ''), enabled: false, help: me?.emailVerified ?? true ? null : l.accountEmailUnverified),
            const SizedBox(height: SoukSpace.s4),
            SoukTextField(label: l.accountPhone, fieldKey: const Key('profile-phone'), controller: _phone, keyboardType: TextInputType.phone, error: fieldError(_error, 'phone'), autofillHints: const [AutofillHints.telephoneNumber]),
            const SizedBox(height: SoukSpace.s4),
            SoukDropdown<String>(
              key: const Key('profile-locale'),
              label: l.accountPreferredLanguage,
              allowNull: false,
              options: {'en': l.english, 'ms': l.malay},
              value: _locale,
              error: fieldError(_error, 'preferred_locale'),
              onChanged: (v) => setState(() => _locale = v),
            ),
            const SizedBox(height: SoukSpace.s5),
            SoukButton(
              key: const Key('profile-save'),
              label: l.accountSave,
              icon: FontAwesomeIcons.check,
              variant: SoukButtonVariant.primary,
              expand: true,
              requiresOnline: true,
              busy: _saving,
              onPressed: _save,
            ),
          ]),
        ),
        const SizedBox(height: SoukSpace.s4),
        SoukCard(
          child: Column(crossAxisAlignment: CrossAxisAlignment.stretch, children: [
            Text(l.accountChangePassword, style: SoukText.displayH3),
            const SizedBox(height: SoukSpace.s4),
            SoukPasswordField(label: l.accountCurrentPassword, fieldKey: const Key('password-current'), controller: _current, required: true, error: fieldError(_pwError, 'current_password')),
            const SizedBox(height: SoukSpace.s4),
            SoukPasswordField(label: l.accountNewPassword, fieldKey: const Key('password-new'), controller: _password, required: true, error: fieldError(_pwError, 'password'), autofillHints: const [AutofillHints.newPassword]),
            const SizedBox(height: SoukSpace.s4),
            SoukPasswordField(label: l.confirmPassword, fieldKey: const Key('password-confirm'), controller: _confirm, required: true, error: fieldError(_pwError, 'password_confirmation'), autofillHints: const [AutofillHints.newPassword]),
            const SizedBox(height: SoukSpace.s5),
            SoukButton(
              key: const Key('password-save'),
              label: l.accountChangePassword,
              expand: true,
              requiresOnline: true,
              busy: _changing,
              onPressed: _changePassword,
            ),
          ]),
        ),
      ]),
    );
  }
}

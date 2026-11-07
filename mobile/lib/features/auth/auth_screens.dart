import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:font_awesome_flutter/font_awesome_flutter.dart';
import 'package:go_router/go_router.dart';

import '../../core/api/api_client.dart';
import '../../core/api/json.dart';
import '../../core/auth/session.dart';
import '../../core/widgets/souk_kit.dart';
import '../../l10n/l10n.dart';

/// Auth pages live outside the tab shells.
List<RouteBase> get authRoutes => [
      GoRoute(path: '/login', builder: (_, s) => LoginScreen(from: s.uri.queryParameters['from'])),
      GoRoute(path: '/register', builder: (_, s) => RegisterScreen(from: s.uri.queryParameters['from'])),
      GoRoute(path: '/two-factor', builder: (_, s) => TwoFactorScreen(from: s.uri.queryParameters['from'])),
      GoRoute(path: '/forgot-password', builder: (_, _) => const ForgotPasswordScreen()),
      GoRoute(path: '/verify-email', builder: (_, _) => const VerifyEmailScreen()),
    ];

final _emailRe = RegExp(r'^[^@\s]+@[^@\s]+\.[^@\s]+$');

/// Shared auth page frame: khatam mark, serif title, one sentence, the form.
class _AuthFrame extends StatelessWidget {
  const _AuthFrame({required this.title, required this.subtitle, required this.child, this.canClose = true});

  final String title;
  final String subtitle;
  final Widget child;
  final bool canClose;

  @override
  Widget build(BuildContext context) {
    final router = GoRouter.of(context);
    return Scaffold(
      backgroundColor: SoukColors.paper,
      appBar: AppBar(
        leading: canClose
            ? IconButton(
                key: const Key('auth-close'),
                tooltip: context.l10n.cancel,
                icon: const Icon(Icons.close_rounded),
                onPressed: () => router.canPop() ? router.pop() : router.go('/home'),
              )
            : null,
        automaticallyImplyLeading: false,
      ),
      body: SafeArea(
        child: SingleChildScrollView(
          keyboardDismissBehavior: ScrollViewKeyboardDismissBehavior.onDrag,
          padding: const EdgeInsets.fromLTRB(SoukSpace.s6, SoukSpace.s4, SoukSpace.s6, SoukSpace.s8),
          child: Column(crossAxisAlignment: CrossAxisAlignment.stretch, children: [
            const KhatamMark(size: 44),
            const SizedBox(height: SoukSpace.s5),
            Text(title, style: SoukText.displayH1),
            const SizedBox(height: SoukSpace.s2),
            Text(subtitle, style: SoukText.bodySoft),
            const SizedBox(height: SoukSpace.s6),
            child,
          ]),
        ),
      ),
    );
  }
}

/// `/login` — email + password → [SessionController.login]; a two-factor
/// answer routes to `/two-factor` through the router redirect.
class LoginScreen extends ConsumerStatefulWidget {
  const LoginScreen({super.key, this.from});

  /// Where to go after sign-in (the guarded route that bounced here).
  final String? from;

  @override
  ConsumerState<LoginScreen> createState() => _LoginScreenState();
}

class _LoginScreenState extends ConsumerState<LoginScreen> {
  final _form = GlobalKey<FormState>();
  final _email = TextEditingController();
  final _password = TextEditingController();
  bool _busy = false;
  Object? _error;

  @override
  void dispose() {
    _email.dispose();
    _password.dispose();
    super.dispose();
  }

  Future<void> _submit() async {
    if (!_form.currentState!.validate()) return;
    setState(() {
      _busy = true;
      _error = null;
    });
    final router = GoRouter.of(context);
    final from = widget.from;
    try {
      await ref.read(sessionProvider.notifier).login(_email.text.trim(), _password.text);
      TextInput.finishAutofillContext();
      if (!mounted) return;
      final s = ref.read(sessionProvider);
      if (s is TwoFactorPending) {
        router.go('/two-factor${from == null ? '' : '?from=${Uri.encodeComponent(from)}'}');
      } else if (s is SignedIn) {
        router.go(from ?? '/home');
      }
    } catch (e) {
      if (!mounted) return;
      setState(() => _error = e);
      if (e is! ApiException || !e.isValidation) showApiError(context, e);
    } finally {
      if (mounted) setState(() => _busy = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    final l = context.l10n;
    final sessionMsg = switch (ref.watch(sessionProvider)) {
      SignedOut(:final message) => message,
      _ => null,
    };
    return _AuthFrame(
      title: l.signInTitle,
      subtitle: l.signInSubtitle,
      child: Form(
        key: _form,
        child: AutofillGroup(
          child: Column(crossAxisAlignment: CrossAxisAlignment.stretch, children: [
            if (sessionMsg != null) ...[
              SoukAlert(sessionMsg, status: 'warn'),
              const SizedBox(height: SoukSpace.s4),
            ],
            SoukTextField(
              label: l.email,
              fieldKey: const Key('login-email'),
              controller: _email,
              keyboardType: TextInputType.emailAddress,
              autofillHints: const [AutofillHints.email],
              prefixIcon: FontAwesomeIcons.envelope,
              enabled: !_busy,
              autocorrect: false,
              error: fieldError(_error, 'email'),
              validator: (v) => v == null || v.trim().isEmpty
                  ? l.fieldRequired
                  : (_emailRe.hasMatch(v.trim()) ? null : l.emailInvalid),
            ),
            const SizedBox(height: SoukSpace.s4),
            SoukPasswordField(
              label: l.password,
              fieldKey: const Key('login-password'),
              controller: _password,
              enabled: !_busy,
              textInputAction: TextInputAction.done,
              onSubmitted: (_) => _busy ? null : _submit(),
              error: fieldError(_error, 'password'),
              validator: (v) => v == null || v.isEmpty ? l.fieldRequired : null,
            ),
            Align(
              alignment: Alignment.centerRight,
              child: TextButton(
                key: const Key('login-forgot'),
                onPressed: _busy ? null : () => context.push('/forgot-password'),
                child: Text(l.forgotPassword),
              ),
            ),
            const SizedBox(height: SoukSpace.s2),
            SoukButton(
              key: const Key('login-submit'),
              label: l.signIn,
              variant: SoukButtonVariant.primary,
              expand: true,
              busy: _busy,
              requiresOnline: true,
              onPressed: _submit,
            ),
            const SizedBox(height: SoukSpace.s5),
            Wrap(alignment: WrapAlignment.center, crossAxisAlignment: WrapCrossAlignment.center, children: [
              Text(l.noAccount, style: SoukText.meta),
              TextButton(
                key: const Key('login-register'),
                onPressed: () => context.push('/register${widget.from == null ? '' : '?from=${Uri.encodeComponent(widget.from!)}'}'),
                child: Text(l.createAccount),
              ),
            ]),
          ]),
        ),
      ),
    );
  }
}

/// `/register` — name, email, password, confirmation → token.
class RegisterScreen extends ConsumerStatefulWidget {
  const RegisterScreen({super.key, this.from});

  final String? from;

  @override
  ConsumerState<RegisterScreen> createState() => _RegisterScreenState();
}

class _RegisterScreenState extends ConsumerState<RegisterScreen> {
  final _form = GlobalKey<FormState>();
  final _name = TextEditingController();
  final _email = TextEditingController();
  final _password = TextEditingController();
  final _confirm = TextEditingController();
  bool _busy = false;
  Object? _error;

  @override
  void dispose() {
    _name.dispose();
    _email.dispose();
    _password.dispose();
    _confirm.dispose();
    super.dispose();
  }

  Future<void> _submit() async {
    if (!_form.currentState!.validate()) return;
    setState(() {
      _busy = true;
      _error = null;
    });
    final router = GoRouter.of(context);
    try {
      await ref.read(sessionProvider.notifier).register(
            name: _name.text.trim(),
            email: _email.text.trim(),
            password: _password.text,
            passwordConfirmation: _confirm.text,
          );
      TextInput.finishAutofillContext();
      if (!mounted) return;
      final me = ref.read(profileProvider);
      router.go(me != null && !me.emailVerified ? '/verify-email' : (widget.from ?? '/home'));
    } catch (e) {
      if (!mounted) return;
      setState(() => _error = e);
      if (e is! ApiException || !e.isValidation) showApiError(context, e);
    } finally {
      if (mounted) setState(() => _busy = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    final l = context.l10n;
    return _AuthFrame(
      title: l.createAccount,
      subtitle: l.createAccountSubtitle,
      child: Form(
        key: _form,
        child: AutofillGroup(
          child: Column(crossAxisAlignment: CrossAxisAlignment.stretch, children: [
            SoukTextField(
              label: l.name,
              fieldKey: const Key('register-name'),
              controller: _name,
              autofillHints: const [AutofillHints.name],
              prefixIcon: FontAwesomeIcons.user,
              enabled: !_busy,
              error: fieldError(_error, 'name'),
              validator: (v) => v == null || v.trim().isEmpty ? l.fieldRequired : null,
            ),
            const SizedBox(height: SoukSpace.s4),
            SoukTextField(
              label: l.email,
              fieldKey: const Key('register-email'),
              controller: _email,
              keyboardType: TextInputType.emailAddress,
              autofillHints: const [AutofillHints.email],
              prefixIcon: FontAwesomeIcons.envelope,
              enabled: !_busy,
              autocorrect: false,
              error: fieldError(_error, 'email'),
              validator: (v) => v == null || v.trim().isEmpty
                  ? l.fieldRequired
                  : (_emailRe.hasMatch(v.trim()) ? null : l.emailInvalid),
            ),
            const SizedBox(height: SoukSpace.s4),
            SoukPasswordField(
              label: l.password,
              fieldKey: const Key('register-password'),
              controller: _password,
              enabled: !_busy,
              autofillHints: const [AutofillHints.newPassword],
              error: fieldError(_error, 'password'),
              help: l.passwordMin,
              validator: (v) => v == null || v.length < 8 ? l.passwordMin : null,
            ),
            const SizedBox(height: SoukSpace.s4),
            SoukPasswordField(
              label: l.confirmPassword,
              fieldKey: const Key('register-confirm'),
              controller: _confirm,
              enabled: !_busy,
              autofillHints: const [AutofillHints.newPassword],
              textInputAction: TextInputAction.done,
              onSubmitted: (_) => _busy ? null : _submit(),
              validator: (v) => v != _password.text ? l.passwordMismatch : null,
            ),
            const SizedBox(height: SoukSpace.s5),
            SoukButton(
              key: const Key('register-submit'),
              label: l.register,
              variant: SoukButtonVariant.primary,
              expand: true,
              busy: _busy,
              requiresOnline: true,
              onPressed: _submit,
            ),
            const SizedBox(height: SoukSpace.s5),
            Wrap(alignment: WrapAlignment.center, crossAxisAlignment: WrapCrossAlignment.center, children: [
              Text(l.haveAccount, style: SoukText.meta),
              TextButton(
                key: const Key('register-login'),
                onPressed: () => context.canPop() ? context.pop() : context.go('/login'),
                child: Text(l.signIn),
              ),
            ]),
          ]),
        ),
      ),
    );
  }
}

/// `/two-factor` — the 6-digit TOTP / emailed code after a 202 login.
class TwoFactorScreen extends ConsumerStatefulWidget {
  const TwoFactorScreen({super.key, this.from});

  final String? from;

  @override
  ConsumerState<TwoFactorScreen> createState() => _TwoFactorScreenState();
}

class _TwoFactorScreenState extends ConsumerState<TwoFactorScreen> {
  final _form = GlobalKey<FormState>();
  final _code = TextEditingController();
  bool _busy = false;
  Object? _error;

  @override
  void dispose() {
    _code.dispose();
    super.dispose();
  }

  Future<void> _submit() async {
    if (!_form.currentState!.validate()) return;
    setState(() {
      _busy = true;
      _error = null;
    });
    final router = GoRouter.of(context);
    try {
      await ref.read(sessionProvider.notifier).verifyTwoFactor(_code.text.trim());
      if (!mounted) return;
      router.go(widget.from ?? '/home');
    } catch (e) {
      if (!mounted) return;
      setState(() => _error = e);
      if (e is! ApiException || !e.isValidation) showApiError(context, e);
    } finally {
      if (mounted) setState(() => _busy = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    final l = context.l10n;
    final method = switch (ref.watch(sessionProvider)) {
      TwoFactorPending(:final method) => method,
      _ => 'totp',
    };
    return _AuthFrame(
      title: l.twoFactorTitle,
      subtitle: method == 'email' ? l.twoFactorHintEmail : l.twoFactorHintTotp,
      canClose: false,
      child: Form(
        key: _form,
        child: Column(crossAxisAlignment: CrossAxisAlignment.stretch, children: [
          SoukTextField(
            label: l.code,
            fieldKey: const Key('two-factor-code'),
            controller: _code,
            keyboardType: TextInputType.number,
            autofillHints: const [AutofillHints.oneTimeCode],
            prefixIcon: FontAwesomeIcons.shieldHalved,
            enabled: !_busy,
            autofocus: true,
            textInputAction: TextInputAction.done,
            onSubmitted: (_) => _busy ? null : _submit(),
            error: fieldError(_error, 'code'),
            validator: (v) => v == null || v.trim().length < 6 ? l.codeLength : null,
          ),
          const SizedBox(height: SoukSpace.s5),
          SoukButton(
            key: const Key('two-factor-submit'),
            label: l.verify,
            variant: SoukButtonVariant.primary,
            expand: true,
            busy: _busy,
            requiresOnline: true,
            onPressed: _submit,
          ),
          const SizedBox(height: SoukSpace.s3),
          SoukButton(
            key: const Key('two-factor-cancel'),
            label: l.cancel,
            variant: SoukButtonVariant.ghost,
            expand: true,
            onPressed: _busy
                ? null
                : () {
                    ref.read(sessionProvider.notifier).cancelTwoFactor();
                    context.go('/login');
                  },
          ),
        ]),
      ),
    );
  }
}

/// `/forgot-password` — `POST auth/forgot-password {email}`.
class ForgotPasswordScreen extends ConsumerStatefulWidget {
  const ForgotPasswordScreen({super.key});

  @override
  ConsumerState<ForgotPasswordScreen> createState() => _ForgotPasswordScreenState();
}

class _ForgotPasswordScreenState extends ConsumerState<ForgotPasswordScreen> {
  final _form = GlobalKey<FormState>();
  final _email = TextEditingController();
  bool _busy = false;
  bool _sent = false;
  Object? _error;

  @override
  void dispose() {
    _email.dispose();
    super.dispose();
  }

  Future<void> _submit() async {
    if (!_form.currentState!.validate()) return;
    setState(() {
      _busy = true;
      _error = null;
    });
    try {
      await ref.read(apiClientProvider).post('auth/forgot-password', body: {'email': _email.text.trim()});
      if (!mounted) return;
      setState(() => _sent = true);
    } catch (e) {
      if (!mounted) return;
      setState(() => _error = e);
      if (e is! ApiException || !e.isValidation) showApiError(context, e);
    } finally {
      if (mounted) setState(() => _busy = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    final l = context.l10n;
    return _AuthFrame(
      title: l.resetPassword,
      subtitle: l.resetPasswordSubtitle,
      child: Form(
        key: _form,
        child: Column(crossAxisAlignment: CrossAxisAlignment.stretch, children: [
          if (_sent) ...[
            SoukAlert(l.resetLinkSent, key: const Key('forgot-sent'), status: 'sale'),
            const SizedBox(height: SoukSpace.s4),
          ],
          SoukTextField(
            label: l.email,
            fieldKey: const Key('forgot-email'),
            controller: _email,
            keyboardType: TextInputType.emailAddress,
            autofillHints: const [AutofillHints.email],
            prefixIcon: FontAwesomeIcons.envelope,
            enabled: !_busy,
            autocorrect: false,
            textInputAction: TextInputAction.done,
            onSubmitted: (_) => _busy ? null : _submit(),
            error: fieldError(_error, 'email'),
            validator: (v) => v == null || v.trim().isEmpty
                ? l.fieldRequired
                : (_emailRe.hasMatch(v.trim()) ? null : l.emailInvalid),
          ),
          const SizedBox(height: SoukSpace.s5),
          SoukButton(
            key: const Key('forgot-submit'),
            label: l.sendResetLink,
            variant: SoukButtonVariant.primary,
            expand: true,
            busy: _busy,
            requiresOnline: true,
            onPressed: _submit,
          ),
        ]),
      ),
    );
  }
}

/// `/verify-email` — resend + "I have verified" (refreshes `GET me`).
class VerifyEmailScreen extends ConsumerStatefulWidget {
  const VerifyEmailScreen({super.key});

  @override
  ConsumerState<VerifyEmailScreen> createState() => _VerifyEmailScreenState();
}

class _VerifyEmailScreenState extends ConsumerState<VerifyEmailScreen> {
  bool _resending = false;
  bool _checking = false;

  Future<void> _resend() async {
    setState(() => _resending = true);
    final l = context.l10n;
    try {
      await ref.read(apiClientProvider).post('auth/verification/resend');
      if (!mounted) return;
      ScaffoldMessenger.of(context)
        ..clearSnackBars()
        ..showSnackBar(SnackBar(content: Text(l.verificationSent)));
    } catch (e) {
      if (mounted) showApiError(context, e);
    } finally {
      if (mounted) setState(() => _resending = false);
    }
  }

  Future<void> _check() async {
    setState(() => _checking = true);
    final router = GoRouter.of(context);
    try {
      await ref.read(sessionProvider.notifier).refreshProfile();
      if (!mounted) return;
      if (ref.read(profileProvider)?.emailVerified ?? false) router.go('/home');
    } catch (e) {
      if (mounted) showApiError(context, e);
    } finally {
      if (mounted) setState(() => _checking = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    final l = context.l10n;
    final email = asStr(ref.watch(profileProvider)?.email) ?? '';
    return _AuthFrame(
      title: l.verifyEmailTitle,
      subtitle: l.verifyEmailBody(email),
      child: Column(crossAxisAlignment: CrossAxisAlignment.stretch, children: [
        SoukButton(
          key: const Key('verify-check'),
          label: l.iHaveVerified,
          variant: SoukButtonVariant.primary,
          expand: true,
          busy: _checking,
          requiresOnline: true,
          onPressed: _check,
        ),
        const SizedBox(height: SoukSpace.s3),
        SoukButton(
          key: const Key('verify-resend'),
          label: l.resendVerification,
          expand: true,
          busy: _resending,
          requiresOnline: true,
          onPressed: _resend,
        ),
      ]),
    );
  }
}

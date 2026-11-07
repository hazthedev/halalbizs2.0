import 'package:flutter/foundation.dart';
import 'package:flutter/material.dart';
import 'package:font_awesome_flutter/font_awesome_flutter.dart';

import '../../../l10n/l10n.dart';
import '../../api/api_client.dart';
import '../../theme/souk_theme.dart';
import 'buttons.dart';

/// User-facing text for any error: client-side codes map to translations,
/// server envelope messages are shown as sent, anything else is generic.
String apiMessage(BuildContext context, Object error) {
  final l = context.l10n;
  if (error is! ApiException) return l.somethingWentWrong;
  return switch (error.code) {
    'offline' => l.errorOffline,
    'timeout' => l.errorTimeout,
    'bad_envelope' => l.errorBadResponse,
    'no_challenge' => l.errorNoChallenge,
    'token_storage' => l.errorTokenStorage,
    _ => error.message.isEmpty ? l.somethingWentWrong : error.message,
  };
}

/// Uniform error panel for ApiException and anything else, with retry.
/// Every async screen renders this on first-load failure.
class ErrorPane extends StatelessWidget {
  const ErrorPane({super.key, required this.error, this.onRetry, this.retryLabel});

  final Object error;
  final VoidCallback? onRetry;

  /// Defaults to the translated "Retry".
  final String? retryLabel;

  @override
  Widget build(BuildContext context) {
    final e = error;
    final offline = e is ApiException && e.isOffline;
    final message = apiMessage(context, e);
    if (kDebugMode && e is! ApiException) debugPrint('ErrorPane: $e');
    return Center(
      child: Padding(
        padding: const EdgeInsets.all(SoukSpace.s6),
        child: Column(mainAxisSize: MainAxisSize.min, children: [
          FaIcon(offline ? FontAwesomeIcons.wifi : FontAwesomeIcons.triangleExclamation,
              size: 26, color: offline ? SoukColors.warn : SoukColors.danger),
          const SizedBox(height: SoukSpace.s4),
          Text(message, textAlign: TextAlign.center, style: SoukText.bodySoft),
          if (onRetry != null) ...[
            const SizedBox(height: SoukSpace.s4),
            SoukButton(
              key: const Key('error-retry'),
              label: retryLabel ?? context.l10n.retry,
              icon: FontAwesomeIcons.arrowsRotate,
              onPressed: onRetry,
            ),
          ],
        ]),
      ),
    );
  }
}

/// Snackbar for any failed write: the envelope message, or the first three
/// validation messages.
void showApiError(BuildContext context, Object error) {
  final e = error;
  var message = apiMessage(context, e);
  if (e is ApiException && e.fields.isNotEmpty) {
    message = e.fields.values.expand((m) => m).take(3).join('\n');
  }
  ScaffoldMessenger.of(context)
    ..clearSnackBars()
    ..showSnackBar(SnackBar(content: Text(message), backgroundColor: SoukColors.danger));
}

/// First message for [path] from a 422, or null. Map it onto the field:
/// `SoukTextField(error: fieldError(_error, 'email'))`.
String? fieldError(Object? error, String path) {
  if (error is! ApiException) return null;
  return error.fields[path]?.firstOrNull;
}

/// Confirm dialog. True = confirmed. Keys: `souk-confirm-ok`, `souk-confirm-cancel`.
Future<bool> showSoukConfirm(
  BuildContext context, {
  required String title,
  required String message,
  String? confirmLabel,
  String? cancelLabel,
  bool danger = false,
}) async {
  final l = context.l10n;
  final ok = await showDialog<bool>(
    context: context,
    useRootNavigator: true,
    builder: (ctx) => AlertDialog(
      title: Text(title),
      content: Text(message),
      actionsPadding: const EdgeInsets.fromLTRB(SoukSpace.s4, 0, SoukSpace.s4, SoukSpace.s4),
      actions: [
        SoukButton(
          key: const Key('souk-confirm-cancel'),
          label: cancelLabel ?? l.cancel,
          variant: SoukButtonVariant.ghost,
          onPressed: () => Navigator.of(ctx).pop(false),
        ),
        SoukButton(
          key: const Key('souk-confirm-ok'),
          label: confirmLabel ?? l.confirm,
          variant: danger ? SoukButtonVariant.danger : SoukButtonVariant.primary,
          onPressed: () => Navigator.of(ctx).pop(true),
        ),
      ],
    ),
  );
  return ok ?? false;
}

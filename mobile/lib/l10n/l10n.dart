import 'package:flutter/widgets.dart';

import 'app_localizations.dart';
import 'app_localizations_en.dart';

export 'app_localizations.dart';

/// `context.l10n.addToCart` — the generated strings, English when a widget is
/// built outside a localized MaterialApp (kit previews, bare widget tests).
extension SoukL10n on BuildContext {
  AppLocalizations get l10n => AppLocalizations.of(this) ?? AppLocalizationsEn();
}

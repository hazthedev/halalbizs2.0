import 'package:flutter/widgets.dart';
import 'package:intl/intl.dart';

/// "7 Nov 2026, 14:05" in the device's zone and the app locale.
String soukDateTime(BuildContext context, DateTime t) =>
    DateFormat('d MMM yyyy, HH:mm', Localizations.localeOf(context).toString()).format(t.toLocal());

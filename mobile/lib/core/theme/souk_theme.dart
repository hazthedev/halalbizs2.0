import 'package:flutter/material.dart';

/// Souk (Emerald & Brass) design tokens, ported 1:1 from the web app's
/// `resources/css/app.css` `@theme` block. Names match the CSS custom
/// properties (`--color-emerald` → [SoukColors.emerald]) so web and app stay
/// in sync. Features never use hex literals: everything comes from here.
///
/// Roles (CLAUDE.md hard rule 8): emerald = money / action, brass = premium /
/// ornament only. A brass fill starts at [SoukColors.brassDeep]; plain
/// [SoukColors.brass] is never placed behind white text. No dark mode.
abstract final class SoukColors {
  // canvas + ink
  static const paper = Color(0xFFFAF7F1);
  static const surface = Color(0xFFFFFFFF);
  static const cream = Color(0xFFF4F2EC);
  static const ink = Color(0xFF1A1E19);
  static const inkHead = Color(0xFF14301C);
  static const inkSoft = Color(0xFF3D4A3C);
  static const inkFaint = Color(0xFF787163);
  static const inkMoss = Color(0xFF7E8A78);
  static const line = Color(0xFFE4DED1);
  static const lineStrong = Color(0xFFDED7C8);

  // green = action
  static const emerald = Color(0xFF22572F);
  static const emeraldDeep = Color(0xFF14301C);
  static const emeraldNight = Color(0xFF14301C);
  static const emeraldCard = Color(0xFF193A22);
  static const emeraldEdge = Color(0xFF2E4C36);
  static const emeraldTint = Color(0xFFF1F6EF);
  static const emeraldTintEdge = Color(0xFFC9D8C6);

  // on-dark inks
  static const onDark = Color(0xFFE8EDE4);
  static const onDarkSoft = Color(0xFFB6C4B2);
  static const onDarkFaint = Color(0xFFA69C86);

  // brass = premium & ornament
  static const brass = Color(0xFFB9902F);
  static const brassDeep = Color(0xFF8A5F22);
  static const brassDarker = Color(0xFF6F4C1B);
  static const brassTint = Color(0xFFF3EAD6);

  // semantic
  static const warn = Color(0xFFB45309);
  static const danger = Color(0xFFBE123C);
  static const warnTint = Color(0xFFFBF3E8);
  static const dangerTint = Color(0xFFFBEDF0);

  /// Modal / sheet backdrop (ink at 48%).
  static const overlay = Color(0x7A14301C);
}

abstract final class SoukRadius {
  static const control = 12.0;
  static const card = 18.0;
  static const panel = 16.0;
  static const pill = 999.0;
}

abstract final class SoukSpace {
  static const s1 = 4.0;
  static const s2 = 8.0;
  static const s3 = 12.0;
  static const s4 = 16.0;
  static const s5 = 20.0;
  static const s6 = 24.0;
  static const s8 = 32.0;
  static const s10 = 40.0;

  /// Page gutter on a phone.
  static const page = 16.0;

  /// Minimum touch target (Apple HIG 44pt).
  static const minTap = 44.0;
}

/// Shadows (`--shadow-soft / -card / -pop`), green-tinted like the CSS.
abstract final class SoukShadow {
  static const soft = [BoxShadow(color: Color(0x0D14301C), blurRadius: 2, offset: Offset(0, 1))];
  static const card = [BoxShadow(color: Color(0x0F14301C), blurRadius: 3, offset: Offset(0, 1))];
  static const pop = [
    BoxShadow(color: Color(0x5214301C), blurRadius: 40, spreadRadius: -22, offset: Offset(0, 18)),
  ];
}

/// Three families, one job each (CSS comment): Newsreader = voice (headings,
/// product names), Instrument Sans = everything a human wrote, IBM Plex Mono
/// = machine-checked facts (prices, SKUs, counts).
abstract final class SoukFonts {
  static const display = 'Newsreader';
  static const sans = 'Instrument Sans';
  static const mono = 'IBM Plex Mono';
}

/// Type scale measured off the reference (`--text-*`), phone-lifted sizes.
abstract final class SoukText {
  static const _tab = [FontFeature.tabularFigures()];

  // display (serif)
  static const displayH1 = TextStyle(
      fontFamily: SoukFonts.display, fontSize: 32, height: 1.1, fontWeight: FontWeight.w500,
      letterSpacing: -0.64, color: SoukColors.inkHead);
  static const displayH2 = TextStyle(
      fontFamily: SoukFonts.display, fontSize: 24, height: 1.15, fontWeight: FontWeight.w500,
      letterSpacing: -0.48, color: SoukColors.inkHead);
  static const displayH3 = TextStyle(
      fontFamily: SoukFonts.display, fontSize: 20, height: 1.2, fontWeight: FontWeight.w500,
      letterSpacing: -0.4, color: SoukColors.inkHead);
  static const displayName = TextStyle(
      fontFamily: SoukFonts.display, fontSize: 16, height: 1.25, fontWeight: FontWeight.w500,
      letterSpacing: -0.2, color: SoukColors.inkHead);

  // sans
  static const lead = TextStyle(fontFamily: SoukFonts.sans, fontSize: 17, height: 1.4, color: SoukColors.ink);
  static const body = TextStyle(fontFamily: SoukFonts.sans, fontSize: 15, height: 1.45, color: SoukColors.ink);
  static const bodySoft =
      TextStyle(fontFamily: SoukFonts.sans, fontSize: 15, height: 1.45, color: SoukColors.inkSoft);
  static const small = TextStyle(fontFamily: SoukFonts.sans, fontSize: 13, height: 1.4, color: SoukColors.ink);
  static const meta = TextStyle(fontFamily: SoukFonts.sans, fontSize: 12.5, height: 1.35, color: SoukColors.inkFaint);
  static const label =
      TextStyle(fontFamily: SoukFonts.sans, fontSize: 13.5, fontWeight: FontWeight.w600, color: SoukColors.ink);
  static const button =
      TextStyle(fontFamily: SoukFonts.sans, fontSize: 15, fontWeight: FontWeight.w600, height: 1.2);
  static const eyebrow = TextStyle(
      fontFamily: SoukFonts.sans, fontSize: 11, fontWeight: FontWeight.w600, letterSpacing: 1.1,
      color: SoukColors.inkFaint);
  static const error = TextStyle(fontFamily: SoukFonts.sans, fontSize: 12.5, fontWeight: FontWeight.w500, color: SoukColors.danger);

  // mono = evidence
  static const price = TextStyle(
      fontFamily: SoukFonts.mono, fontSize: 15, fontWeight: FontWeight.w500, color: SoukColors.ink,
      fontFeatures: _tab);
  static const priceLarge = TextStyle(
      fontFamily: SoukFonts.mono, fontSize: 22, fontWeight: FontWeight.w500, color: SoukColors.emerald,
      fontFeatures: _tab);
  static const mono = TextStyle(
      fontFamily: SoukFonts.mono, fontSize: 12.5, fontWeight: FontWeight.w400, color: SoukColors.inkSoft,
      fontFeatures: _tab);
  static const monoLabel = TextStyle(
      fontFamily: SoukFonts.mono, fontSize: 11, fontWeight: FontWeight.w500, letterSpacing: 1.1,
      color: SoukColors.inkFaint, fontFeatures: _tab);
}

/// Resolved colours of one status pill.
class SoukStatusColors {
  const SoukStatusColors({required this.bg, required this.fg, required this.border});

  final Color bg;
  final Color fg;
  final Color border;
}

/// Mirrors `resources/views/components/order-status-pill.blade.php`:
/// pending_payment → warn · completed → emerald tint · cancelled /
/// return_requested / returned / refunded → danger · everything else → ink
/// outline (confirmed, processing, shipped, delivered). Unknown → ink.
SoukStatusColors soukStatusColor(String apiStatus) => switch (apiStatus.trim().toLowerCase()) {
      'pending_payment' || 'pending' || 'warn' =>
        const SoukStatusColors(bg: SoukColors.warnTint, fg: SoukColors.warn, border: SoukColors.warnTint),
      'completed' || 'paid' || 'approved' || 'sale' =>
        const SoukStatusColors(bg: SoukColors.emeraldTint, fg: SoukColors.emerald, border: SoukColors.emeraldTintEdge),
      'cancelled' || 'return_requested' || 'returned' || 'refunded' || 'failed' || 'expired' || 'rejected' || 'suspended' || 'danger' =>
        const SoukStatusColors(bg: SoukColors.dangerTint, fg: SoukColors.danger, border: SoukColors.dangerTint),
      _ => const SoukStatusColors(bg: SoukColors.surface, fg: SoukColors.ink, border: SoukColors.lineStrong),
    };

/// Material 3, light only. Emerald is the action colour; inputs use the
/// `line` hairline and an emerald focus ring; buttons per docs/03.
ThemeData soukTheme() {
  const scheme = ColorScheme(
    brightness: Brightness.light,
    primary: SoukColors.emerald,
    onPrimary: Colors.white,
    secondary: SoukColors.brassDeep,
    onSecondary: Colors.white,
    error: SoukColors.danger,
    onError: Colors.white,
    surface: SoukColors.surface,
    onSurface: SoukColors.ink,
    surfaceContainerHighest: SoukColors.cream,
    outline: SoukColors.line,
  );

  final base = ThemeData(
    useMaterial3: true,
    colorScheme: scheme,
    fontFamily: SoukFonts.sans,
    scaffoldBackgroundColor: SoukColors.paper,
    dividerColor: SoukColors.line,
    splashFactory: InkSparkle.splashFactory,
  );

  OutlineInputBorder border(Color c, [double w = 1]) => OutlineInputBorder(
        borderRadius: BorderRadius.circular(SoukRadius.control),
        borderSide: BorderSide(color: c, width: w),
      );
  final pill = RoundedRectangleBorder(borderRadius: BorderRadius.circular(SoukRadius.pill));

  return base.copyWith(
    textTheme: base.textTheme.apply(bodyColor: SoukColors.ink, displayColor: SoukColors.inkHead),
    appBarTheme: const AppBarTheme(
      backgroundColor: SoukColors.paper,
      foregroundColor: SoukColors.inkHead,
      surfaceTintColor: Colors.transparent,
      elevation: 0,
      scrolledUnderElevation: 0,
      centerTitle: false,
      titleTextStyle: SoukText.displayH3,
    ),
    cardTheme: CardThemeData(
      color: SoukColors.surface,
      elevation: 0,
      margin: EdgeInsets.zero,
      shape: RoundedRectangleBorder(
        borderRadius: BorderRadius.circular(SoukRadius.card),
        side: const BorderSide(color: SoukColors.line),
      ),
    ),
    inputDecorationTheme: InputDecorationTheme(
      filled: true,
      fillColor: SoukColors.surface,
      isDense: true,
      contentPadding: const EdgeInsets.symmetric(horizontal: SoukSpace.s4, vertical: 13),
      border: border(SoukColors.line),
      enabledBorder: border(SoukColors.line),
      focusedBorder: border(SoukColors.emerald, 2),
      errorBorder: border(SoukColors.danger),
      focusedErrorBorder: border(SoukColors.danger, 2),
      labelStyle: const TextStyle(color: SoukColors.inkFaint),
      hintStyle: const TextStyle(color: SoukColors.inkFaint),
      errorStyle: SoukText.error,
    ),
    filledButtonTheme: FilledButtonThemeData(
      style: FilledButton.styleFrom(
        backgroundColor: SoukColors.emerald,
        foregroundColor: Colors.white,
        minimumSize: const Size(SoukSpace.minTap, SoukSpace.minTap),
        padding: const EdgeInsets.symmetric(horizontal: SoukSpace.s5),
        shape: pill,
        textStyle: SoukText.button,
      ),
    ),
    outlinedButtonTheme: OutlinedButtonThemeData(
      style: OutlinedButton.styleFrom(
        foregroundColor: SoukColors.ink,
        side: const BorderSide(color: SoukColors.ink),
        minimumSize: const Size(SoukSpace.minTap, SoukSpace.minTap),
        padding: const EdgeInsets.symmetric(horizontal: SoukSpace.s5),
        shape: pill,
        textStyle: SoukText.button,
      ),
    ),
    textButtonTheme: TextButtonThemeData(
      style: TextButton.styleFrom(
        foregroundColor: SoukColors.emerald,
        minimumSize: const Size(SoukSpace.minTap, SoukSpace.minTap),
        shape: pill,
        textStyle: SoukText.button,
      ),
    ),
    chipTheme: base.chipTheme.copyWith(
      backgroundColor: SoukColors.surface,
      selectedColor: SoukColors.emeraldTint,
      side: const BorderSide(color: SoukColors.line),
      labelStyle: SoukText.small,
      shape: pill,
      showCheckmark: false,
    ),
    dialogTheme: DialogThemeData(
      backgroundColor: SoukColors.surface,
      surfaceTintColor: Colors.transparent,
      shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(SoukRadius.panel)),
      titleTextStyle: SoukText.displayH3,
      contentTextStyle: SoukText.bodySoft,
    ),
    bottomSheetTheme: const BottomSheetThemeData(
      backgroundColor: SoukColors.surface,
      surfaceTintColor: Colors.transparent,
      shape: RoundedRectangleBorder(
          borderRadius: BorderRadius.vertical(top: Radius.circular(SoukRadius.card))),
    ),
    snackBarTheme: SnackBarThemeData(
      backgroundColor: SoukColors.emeraldNight,
      contentTextStyle: const TextStyle(fontFamily: SoukFonts.sans, color: SoukColors.onDark),
      behavior: SnackBarBehavior.floating,
      shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(SoukRadius.control)),
    ),
    navigationBarTheme: NavigationBarThemeData(
      backgroundColor: SoukColors.surface,
      surfaceTintColor: Colors.transparent,
      indicatorColor: SoukColors.emeraldTint,
      height: 64,
      labelTextStyle: WidgetStateProperty.resolveWith((s) => TextStyle(
            fontFamily: SoukFonts.sans,
            fontSize: 11.5,
            fontWeight: s.contains(WidgetState.selected) ? FontWeight.w600 : FontWeight.w500,
            color: s.contains(WidgetState.selected) ? SoukColors.emerald : SoukColors.inkFaint,
          )),
    ),
    progressIndicatorTheme: const ProgressIndicatorThemeData(color: SoukColors.emerald),
    dividerTheme: const DividerThemeData(color: SoukColors.line, thickness: 1, space: 1),
  );
}

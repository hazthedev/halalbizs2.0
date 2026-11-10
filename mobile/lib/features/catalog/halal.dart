import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:font_awesome_flutter/font_awesome_flutter.dart';
import 'package:intl/intl.dart';

import '../../core/in_app_browser.dart';
import '../../core/widgets/souk_kit.dart';
import '../../l10n/l10n.dart';
import 'models.dart';

/// The halal certificate, the way the web storefront shows it: the card and
/// basket tick, the PDP verdict panel, scope, traceability table and the
/// "Halal certification" metafield box. The verdict is the server's
/// (Product::halalVerdict()); nothing here re-derives it.

/// "7 Jan 2029" (web `j M Y`), in the app locale.
String halalDate(BuildContext context, DateTime d) => DateFormat('d MMM yyyy', Localizations.localeOf(context).toString()).format(d);

/// Mono emerald tick + text: the web card / basket / checkout certificate line.
class HalalTick extends StatelessWidget {
  const HalalTick(this.text, {super.key, this.caps = false});

  final String text;
  final bool caps;

  @override
  Widget build(BuildContext context) => Row(
    mainAxisSize: MainAxisSize.min,
    children: [
      const Icon(Icons.check_rounded, size: 13, color: SoukColors.emerald),
      const SizedBox(width: 3),
      Flexible(
        child: Text(
          caps ? text.toUpperCase() : text,
          maxLines: 1,
          overflow: TextOverflow.ellipsis,
          style: SoukText.monoLabel.copyWith(color: SoukColors.emerald, fontSize: 10.5, letterSpacing: .6),
        ),
      ),
    ],
  );
}

/// PDP verdict panel (web product-detail.blade.php "Halal certificate"):
/// tone by verdict, body · number · date, and "Read the record" opening the
/// public register in the in-app browser.
class HalalPanel extends ConsumerWidget {
  const HalalPanel(this.cert, {super.key});

  final HalalCert cert;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final l = context.l10n;
    final (border, bg, fg, icon) = switch (cert.verdict) {
      'verified' => (SoukColors.emeraldTintEdge, SoukColors.emeraldTint, SoukColors.emerald, Icons.check_rounded),
      'lapsed' => (SoukColors.danger.withValues(alpha: .3), SoukColors.dangerTint, SoukColors.danger, Icons.close_rounded),
      _ => (SoukColors.line, SoukColors.paper, SoukColors.inkSoft, Icons.warning_amber_rounded),
    };
    final title = switch (cert.verdict) {
      'verified' => l.halalCertVerified,
      'lapsed' => l.halalCertLapsed,
      _ => l.halalCertNotInForce,
    };
    final from = cert.validFrom, to = cert.validTo;
    final date = switch (cert.verdict) {
      'lapsed' when to != null => l.halalExpiredOn(halalDate(context, to)),
      'pending' when from != null => l.halalValidFrom(halalDate(context, from)),
      _ when to != null => l.halalValidTo(halalDate(context, to)),
      _ => null,
    };
    final url = cert.registerUrl;

    return Container(
      key: Key('halal-panel-${cert.verdict}'),
      width: double.infinity,
      padding: const EdgeInsets.symmetric(horizontal: SoukSpace.s4, vertical: SoukSpace.s3),
      decoration: BoxDecoration(
        color: bg,
        borderRadius: BorderRadius.circular(SoukRadius.panel),
        border: Border.all(color: border),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            children: [
              Icon(icon, size: 16, color: fg),
              const SizedBox(width: SoukSpace.s2),
              Expanded(
                child: Text(title.toUpperCase(), style: SoukText.monoLabel.copyWith(color: fg)),
              ),
            ],
          ),
          const SizedBox(height: SoukSpace.s1),
          Text([cert.issuingBody, cert.number, date].nonNulls.join(' · '), style: SoukText.small.copyWith(color: SoukColors.inkHead)),
          if (url != null) ...[
            const SizedBox(height: SoukSpace.s2),
            SoukButton(
              key: const Key('halal-read-record'),
              label: l.halalReadRecord,
              compact: true,
              requiresOnline: true,
              icon: FontAwesomeIcons.arrowUpRightFromSquare,
              onPressed: () => ref.read(inAppBrowserProvider).open(url),
            ),
          ],
        ],
      ),
    );
  }
}

/// Web PDP "Ingredients & scope" + "Traceability" (only rows the data can
/// answer) + the metafield groups (x-product-metafields).
class HalalDetails extends StatelessWidget {
  const HalalDetails(this.product, {super.key});

  final Product product;

  @override
  Widget build(BuildContext context) {
    final l = context.l10n;
    final cert = product.halal;
    final scope = cert?.scopeNote;
    final batch = product.halalBatchCode;
    final packed = product.halalPackedOn;
    final to = cert?.validTo;
    final from = cert?.validFrom;

    final rows = <(String, String?)>[
      (l.halalFacility, cert?.facility),
      (l.halalBatch, batch == null ? null : [batch, if (packed != null) l.halalPackedOn(halalDate(context, packed))].join(' · ')),
      (l.halalCertificate, cert != null ? [cert.issuingBody, cert.number].nonNulls.join(' · ') : product.halalCertNumber),
      (
        l.halalExpiry,
        cert == null || to == null
            ? null
            : '${halalDate(context, to)} · ${switch (cert.verdict) {
                'lapsed' => l.halalLapsed,
                'pending' when from != null => l.halalNotInForceUntil(halalDate(context, from)),
                _ => l.halalDaysRemaining(cert.daysRemaining),
              }}',
      ),
    ].where((r) => r.$2 != null && r.$2!.isNotEmpty).toList();
    final groups = product.metafields;

    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        if (scope != null) ...[
          const SizedBox(height: SoukSpace.s6),
          Text(l.halalScopeTitle, style: SoukText.displayH3),
          const SizedBox(height: SoukSpace.s2),
          Text(l.halalScopeBody(scope), style: SoukText.bodySoft),
        ],
        // Web: the table renders only for a certificate or a batch code.
        if ((cert != null || batch != null) && rows.isNotEmpty) ...[
          const SizedBox(height: SoukSpace.s6),
          Text(l.halalTraceability, key: const Key('halal-traceability'), style: SoukText.displayH3),
          const SizedBox(height: SoukSpace.s3),
          for (final (label, value) in rows)
            Padding(
              padding: const EdgeInsets.only(bottom: SoukSpace.s2),
              child: Row(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  SizedBox(width: 104, child: Text(label.toUpperCase(), style: SoukText.monoLabel)),
                  Expanded(child: Text(value!, style: SoukText.body)),
                ],
              ),
            ),
        ],
        if (groups.isNotEmpty) ...[
          const SizedBox(height: SoukSpace.s5),
          SoukCard(
            key: const Key('product-metafields'),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                for (final (i, g) in groups.indexed) ...[
                  if (i > 0) const SizedBox(height: SoukSpace.s4),
                  // Web: the halal group is a brass trust pill; the rest a caps heading.
                  if (g.key == 'halal')
                    Container(
                      padding: const EdgeInsets.symmetric(horizontal: SoukSpace.s3, vertical: SoukSpace.s1),
                      decoration: BoxDecoration(
                        color: SoukColors.brassTint,
                        borderRadius: BorderRadius.circular(SoukRadius.pill),
                        border: Border.all(color: SoukColors.brass.withValues(alpha: .4)),
                      ),
                      child: Row(
                        mainAxisSize: MainAxisSize.min,
                        children: [
                          const KhatamMark(size: 14, color: SoukColors.brass),
                          const SizedBox(width: 6),
                          Text(
                            g.label,
                            style: SoukText.small.copyWith(color: SoukColors.brassDeep, fontWeight: FontWeight.w600),
                          ),
                        ],
                      ),
                    )
                  else
                    Text(g.label.toUpperCase(), style: SoukText.eyebrow),
                  const SizedBox(height: SoukSpace.s2),
                  for (final r in g.rows)
                    Padding(
                      padding: const EdgeInsets.only(bottom: SoukSpace.s1),
                      child: Row(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          Expanded(
                            child: Text(r.label, style: SoukText.small.copyWith(color: SoukColors.inkSoft)),
                          ),
                          const SizedBox(width: SoukSpace.s3),
                          Expanded(
                            child: Text(r.value, style: SoukText.small.copyWith(fontWeight: FontWeight.w500)),
                          ),
                        ],
                      ),
                    ),
                ],
              ],
            ),
          ),
        ],
      ],
    );
  }
}

import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:font_awesome_flutter/font_awesome_flutter.dart';

import '../../core/api/api_client.dart' show kApiBaseUrl;
import '../../core/in_app_browser.dart';
import '../../core/widgets/souk_kit.dart';
import '../../l10n/l10n.dart';

/// Web page URL on the same host as the API (`https://host/api/v1` -> `https://host/page/faq`).
String webPageUrl(String path) => Uri.parse(kApiBaseUrl).replace(path: path).toString();

/// `/account/legal`: the CMS pages the web footer links to (`/page/{slug}`),
/// opened in the in-app browser. Slugs and labels are the web footer's.
class LegalScreen extends ConsumerWidget {
  const LegalScreen({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final l = context.l10n;
    final pages = <(String, String)>[
      ('about', l.legalAboutUs),
      ('trust-safety', l.legalTrustSafety),
      ('faq', l.legalFaq),
      ('refund-policy', l.legalRefundPolicy),
      ('terms', l.legalTerms),
      ('privacy', l.legalPrivacy),
    ];
    return SoukPage(
      title: l.legalAndAbout,
      scrollable: true,
      body: SoukCard(
        padding: EdgeInsets.zero,
        child: Column(children: [
          for (final (slug, label) in pages)
            SoukRow(
              key: Key('legal-$slug'),
              icon: FontAwesomeIcons.fileLines,
              label: label,
              onTap: () => ref.read(inAppBrowserProvider).open(webPageUrl('/page/$slug')),
            ),
        ]),
      ),
    );
  }
}

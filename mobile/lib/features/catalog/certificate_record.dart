import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../../core/api/api_client.dart';
import '../../core/api/json.dart';
import '../../core/widgets/souk_kit.dart';
import '../../l10n/l10n.dart';
import 'halal.dart' show halalDate;

/// `GET certificates/{number}`: the public register record, as the web page shows it.
final certificateRecordProvider = FutureProvider.autoDispose.family<Map<String, dynamic>, String>(
    (ref, number) async => asMap(await ref.watch(apiClientProvider).get('certificates/${Uri.encodeComponent(number)}')));

/// `/certificate/:number`: the web "Certificate register" record, native.
class CertificateRecordScreen extends ConsumerWidget {
  const CertificateRecordScreen({super.key, required this.number});

  final String number;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final l = context.l10n;
    final async = ref.watch(certificateRecordProvider(number));
    return SoukPage(
      title: l.certRegister,
      scrollable: true,
      body: async.when(
        loading: () => const SoukSkeleton.list(rows: 4),
        error: (e, _) => e is ApiException && e.isNotFound
            ? SoukCard(
                key: const Key('cert-no-record'),
                child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
                  Text(l.certNoRecord.toUpperCase(), style: SoukText.monoLabel),
                  const SizedBox(height: SoukSpace.s2),
                  Text(l.certNoRecordBody, style: SoukText.bodySoft),
                ]),
              )
            : ErrorPane(error: e, onRetry: () => ref.invalidate(certificateRecordProvider(number)), retryLabel: l.retry),
        data: (c) => _Record(c),
      ),
    );
  }
}

class _Record extends StatelessWidget {
  const _Record(this.c);

  final Map<String, dynamic> c;

  @override
  Widget build(BuildContext context) {
    final l = context.l10n;
    final valid = asBool(c['valid']);
    final days = asInt(c['days_remaining']) ?? 0;
    final from = asDate(c['valid_from']), to = asDate(c['valid_to']);
    final count = asInt(c['products_count']) ?? 0;
    final body = asStr(c['issuing_body']) ?? '';
    final bodyName = asStr(c['issuing_body_name']);
    final state = asStr(c['holder_state']);
    final scheme = asStr(c['scheme']);
    final scope = asStr(c['scope_note']);
    final fields = <(String, String)>[
      (l.certIssuingBody, body + (bodyName != null && bodyName.isNotEmpty ? ' — $bodyName' : '')),
      (l.certHolder, (asStr(c['holder_name']) ?? '') + (state != null && state.isNotEmpty ? ' · $state' : '')),
      (l.certSchemeScope, [if (scheme != null && scheme.isNotEmpty) scheme, if (scope != null && scope.isNotEmpty) scope].join(' — ')),
      (
        l.certValidity,
        '${from == null ? '' : halalDate(context, from)} → ${to == null ? '' : halalDate(context, to)}${valid ? ' · ${l.certDaysRemaining(days)}' : ''}'
      ),
      (l.certFacility, asStr(c['facility']) ?? '—'),
      (l.certCoveredSkus, '$count'),
    ];
    final events = asMapList(c['events']);
    final slug = asStr(c['store_slug']);
    return Column(crossAxisAlignment: CrossAxisAlignment.stretch, children: [
      SoukCard(
        key: Key('cert-verdict-${valid ? 'valid' : 'invalid'}'),
        color: valid ? SoukColors.emeraldTint : SoukColors.dangerTint,
        child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
          Text((valid ? l.certValid : l.certNotValid).toUpperCase(),
              style: SoukText.monoLabel.copyWith(color: valid ? SoukColors.emerald : SoukColors.danger)),
          const SizedBox(height: SoukSpace.s1),
          Text(asStr(c['number']) ?? '', style: SoukText.displayH3),
        ]),
      ),
      const SizedBox(height: SoukSpace.s3),
      SoukCard(
        child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
          for (final (label, value) in fields) ...[
            Text(label.toUpperCase(), style: SoukText.eyebrow),
            const SizedBox(height: 2),
            Text(value, style: SoukText.body),
            const SizedBox(height: SoukSpace.s3),
          ],
          if (asBool(c['dedicated_facility']) || asBool(c['export_paperwork']))
            Wrap(spacing: SoukSpace.s2, runSpacing: SoukSpace.s2, children: [
              if (asBool(c['dedicated_facility'])) SoukBadge(l.certDedicated, status: 'success'),
              if (asBool(c['export_paperwork'])) SoukBadge(l.certExport, status: 'success'),
            ]),
        ]),
      ),
      if (events.isNotEmpty) ...[
        const SizedBox(height: SoukSpace.s3),
        SoukCard(
          key: const Key('cert-audit-trail'),
          child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
            Text(l.certAuditTrail.toUpperCase(), style: SoukText.eyebrow),
            const SizedBox(height: SoukSpace.s2),
            for (final e in events)
              Padding(
                padding: const EdgeInsets.symmetric(vertical: SoukSpace.s1),
                child: Row(crossAxisAlignment: CrossAxisAlignment.start, children: [
                  SizedBox(width: 96, child: Text(asDate(e['occurred_on']) == null ? '' : halalDate(context, asDate(e['occurred_on'])!), style: SoukText.meta)),
                  Expanded(child: Text(asStr(e['summary']) ?? '', style: SoukText.small)),
                ]),
              ),
          ]),
        ),
      ],
      const SizedBox(height: SoukSpace.s3),
      SoukCard(
        color: SoukColors.cream,
        child: Column(crossAxisAlignment: CrossAxisAlignment.stretch, children: [
          Text(l.certBound, style: SoukText.bodySoft),
          if (slug != null) ...[
            const SizedBox(height: SoukSpace.s3),
            SoukButton(
              key: const Key('cert-see-skus'),
              label: l.certSeeSkus(count),
              compact: true,
              onPressed: () => context.push('/s/${Uri.encodeComponent(slug)}'),
            ),
          ],
        ]),
      ),
    ]);
  }
}

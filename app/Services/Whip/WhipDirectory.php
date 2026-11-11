<?php

namespace App\Services\Whip;

use App\Support\DisplayTime;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Read-only view of WHIP's database (`whip` connection, SELECT-only user).
 *
 * WHIP rules mirrored here: a certificate is live only if its application
 * status (lowercase, spaces removed) is certified or issued AND today sits in
 * valid_from..valid_to (NULL or '' = open). Per application only the latest
 * certificate row (highest id) counts.
 */
class WhipDirectory
{
    /**
     * Not found = null. A connection/query failure THROWS: callers decide how to fail closed.
     */
    public function lookup(string $certificateNo): ?WhipCertificate
    {
        $no = strtoupper(trim($certificateNo));

        // certificate_no may be NULL or '' in WHIP: those rows must never match.
        if ($no === '') {
            return null;
        }

        $db = DB::connection('whip');

        $rows = $db->table('halal_application_certificates as c')
            ->join('halal_applications as a', 'a.id', '=', 'c.application_id')
            ->whereRaw('UPPER(TRIM(c.certificate_no)) = ?', [$no])
            ->whereRaw('c.id = (select max(c2.id) from halal_application_certificates c2 where c2.application_id = c.application_id)')
            ->get(['c.id', 'c.application_id', 'c.certificate_no', 'c.valid_from', 'c.valid_to', 'a.status', 'a.certification_body_id']);

        if ($rows->isEmpty()) {
            return null;
        }

        $states = $rows->map(fn ($r) => $this->state($r))->all();
        $actives = array_keys($states, WhipCertificate::ACTIVE, true);

        // One row, or exactly one active among several: that is the answer.
        // Anything else is ambiguous and goes to a human.
        $pick = match (true) {
            $rows->count() === 1 => 0,
            count($actives) === 1 => $actives[0],
            default => null,
        };

        $row = $rows[$pick ?? 0];
        $profile = $db->table('halal_application_company_profiles')->where('application_id', $row->application_id)->orderBy('id')->first();
        $body = $db->table('certification_bodies')->where('id', $row->certification_body_id)->value('name');
        $products = $db->table('halal_application_products')->where('application_id', $row->application_id)->orderBy('id')
            ->get(['product_name', 'brand_name', 'barcode'])
            ->map(fn ($p) => ['name' => (string) $p->product_name, 'brand' => $p->brand_name ?: null, 'barcode' => $p->barcode ?: null])->all();

        return new WhipCertificate(
            status: $pick === null ? WhipCertificate::AMBIGUOUS : $states[$pick],
            certificateNo: trim((string) $row->certificate_no),
            validFrom: $row->valid_from ?: null,
            validTo: $row->valid_to ?: null,
            companyName: $profile->company_name ?? null,
            registrationNumber: $profile->registration_number ?? null,
            bodyName: $body,
            products: $products,
            applicationId: (int) $row->application_id,
        );
    }

    /**
     * Look up and judge in one call; never throws.
     *
     * @return array{0: string, 1: ?WhipCertificate} answer: verified|expired|not_certified|ambiguous|mismatch|incomplete|not_found|unavailable
     */
    public function check(string $certificateNo, ?string $storeRegistrationNo): array
    {
        try {
            $found = $this->lookup($certificateNo);
        } catch (Throwable $e) {
            Log::warning('WHIP lookup failed', ['error' => $e->getMessage()]);

            return ['unavailable', null];
        }

        return $found === null ? ['not_found', null] : [$found->answerFor($storeRegistrationNo), $found];
    }

    private function state(object $row): string
    {
        $status = strtolower(str_replace(' ', '', (string) $row->status));
        $today = DisplayTime::today()->toDateString();

        if (! in_array($status, ['certified', 'issued'], true)) {
            return WhipCertificate::NOT_CERTIFIED;
        }

        if ($row->valid_to && $row->valid_to < $today) {
            return WhipCertificate::EXPIRED;
        }

        return $row->valid_from && $row->valid_from > $today ? WhipCertificate::NOT_CERTIFIED : WhipCertificate::ACTIVE;
    }
}

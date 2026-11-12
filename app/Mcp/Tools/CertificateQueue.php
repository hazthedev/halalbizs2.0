<?php

namespace App\Mcp\Tools;

use App\Enums\CertificateStatus;
use App\Models\HalalCertificate;
use Illuminate\Support\Carbon;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[IsReadOnly]
class CertificateQueue extends InternalTool
{
    protected string $name = 'certificate_queue';

    protected string $description = 'Admin only. Certificate counts by review status and age in seconds of the oldest pending submission.';

    public function handle(): ResponseFactory
    {
        $counts = array_fill_keys(array_column(CertificateStatus::cases(), 'value'), 0);
        foreach (HalalCertificate::query()->selectRaw('status, COUNT(*) AS total')->groupBy('status')->get() as $row) {
            $counts[$row->status->value] = (int) $row->total;
        }

        $oldest = HalalCertificate::query()->awaitingReview()
            ->selectRaw('MIN(COALESCE(submitted_at, created_at)) AS oldest')->value('oldest');

        return Response::structured([
            'counts' => $counts,
            'oldest_pending_age_seconds' => $oldest === null ? null : max(0, (int) Carbon::parse($oldest)->diffInSeconds(now())),
        ]);
    }
}

<?php

namespace App\Mcp\Tools;

use App\Services\Whip\WhipCertificate;
use App\Services\Whip\WhipDirectory;
use App\Settings\GeneralSettings;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;
use Throwable;

#[IsReadOnly]
class CheckCertificate extends Tool
{
    protected string $name = 'check_certificate';

    protected string $description = 'Check a certificate number directly with WHIP. Returns status, company, certification body and validity dates only.';

    public function schema(JsonSchema $schema): array
    {
        return ['certificate_no' => $schema->string()->required()->max(255)];
    }

    public function handle(Request $request, WhipDirectory $directory): Response|ResponseFactory
    {
        if (! app(GeneralSettings::class)->mcp_public_enabled) {
            return Response::error('Public halal tools are disabled by the administrator.');
        }

        $data = $request->validate(['certificate_no' => ['required', 'string', 'max:255']]);

        try {
            $certificate = $directory->lookup($data['certificate_no']);
        } catch (Throwable) {
            return Response::error('WHIP is unavailable. The certificate could not be checked.');
        }

        if ($certificate?->status === WhipCertificate::AMBIGUOUS) {
            return Response::error('WHIP has conflicting records for this number. An administrator must check it.');
        }

        return Response::structured([
            'status' => $certificate?->status ?? 'not_found',
            'company_name' => $certificate?->companyName,
            'certification_body' => $certificate?->bodyName,
            'valid_from' => $certificate?->validFrom,
            'valid_to' => $certificate?->validTo,
        ]);
    }
}

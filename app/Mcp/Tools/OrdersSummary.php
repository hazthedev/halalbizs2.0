<?php

namespace App\Mcp\Tools;

use App\Enums\PaymentStatus;
use App\Models\Order;
use App\Support\DisplayTime;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[IsReadOnly]
class OrdersSummary extends InternalTool
{
    protected string $name = 'orders_summary';

    protected string $description = 'Admin only. Orders placed on a Malaysia calendar date (YYYY-MM-DD), grouped by payment status. Counts and MYR integer sen totals; no buyer data.';

    public function schema(JsonSchema $schema): array
    {
        return ['date' => $schema->string()->required()->description('Malaysia calendar date, YYYY-MM-DD.')];
    }

    public function handle(Request $request): ResponseFactory
    {
        $data = $request->validate(['date' => ['required', 'string', 'date_format:Y-m-d']]);
        $groups = array_fill_keys(array_column(PaymentStatus::cases(), 'value'), ['count' => 0, 'total_sen' => 0]);
        $rows = Order::query()->whereBetween('placed_at', [DisplayTime::dayStart($data['date']), DisplayTime::dayEnd($data['date'])])
            ->selectRaw('payment_status, COUNT(*) AS total_count, SUM(grand_total_sen) AS total_sen')
            ->groupBy('payment_status')->get();
        foreach ($rows as $row) {
            $groups[$row->payment_status->value] = ['count' => (int) $row->total_count, 'total_sen' => (int) $row->total_sen];
        }

        return Response::structured([
            'date' => $data['date'], 'timezone' => DisplayTime::zone(),
            'currency' => 'MYR', 'status_type' => 'payment_status', 'by_status' => $groups,
        ]);
    }
}

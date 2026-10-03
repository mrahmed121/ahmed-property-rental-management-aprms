<?php

namespace App\Http\Controllers\Api\V1;

use App\Domains\Billing\Models\Payment;
use App\Domains\Billing\Services\PaymentService;
use App\Domains\Billing\Services\ReceiptService;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;

class ReceiptController extends Controller
{
    public function __construct(
        private ReceiptService $receipts,
        private PaymentService $payments,
    ) {}

    public function show(int $paymentId): JsonResponse
    {
        $payment = $this->payments->find($paymentId);

        return response()->json(['data' => $this->receipts->data($payment)]);
    }

    public function pdf(int $paymentId)
    {
        $payment = Payment::findOrFail($paymentId);
        app(PaymentService::class)->ensurePaymentAccess($payment);

        return $this->receipts->pdf($payment);
    }
}

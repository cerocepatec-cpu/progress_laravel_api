<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Resources\InvoiceResource;
use App\Services\InvoiceService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class InvoiceController extends ApiController
{
    public function __construct(
        private readonly InvoiceService $invoices,
    ) {
    }

    public function index(Request $request)
    {
        $items = $this->invoices->listByUser(
            $request->user(),
            $request->input('from'),
            $request->input('to')
        );

        return $this->ok(InvoiceResource::collection($items));
    }

    public function show(int $invoice)
    {
        return $this->ok(InvoiceResource::make($this->invoices->details($invoice)));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'customer_id' => ['nullable', 'string'],
            'type_facture' => ['required', 'string'],
            'nature' => ['nullable', Rule::in(['products', 'solds', 'maj'])],
            'note' => ['nullable', 'string'],
            'total_received' => ['nullable', 'numeric'],
            'details' => ['required', 'array', 'min:1'],
            'details.*.product_id' => ['required', 'integer'],
            'details.*.deposit_id' => ['required', 'integer'],
            'details.*.quantity' => ['required', 'numeric'],
            'details.*.price' => ['required', 'numeric'],
            'details.*.point' => ['nullable', 'numeric'],
            'details.*.description' => ['nullable', 'string'],
        ]);

        $id = $this->invoices->create($request->user(), $data);

        return $this->ok(['id' => $id], 'Facture enregistree.', 201);
    }
}

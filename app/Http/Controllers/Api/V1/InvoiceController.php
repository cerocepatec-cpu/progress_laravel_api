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
    ) {}

    public function index(Request $request)
    {
        $data = $request->validate([
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date'],
            'q' => ['nullable', 'string'],
            'type_facture' => ['nullable', 'in:cash,credit'],
            'nature' => ['nullable', 'in:products,solds,maj'],
            'status' => ['nullable', 'string'],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $result = $this->invoices->listByUserPaginated(
            $request->user(),
            $data
        );

        return $this->ok($result);
    }

    public function show(int $invoice)
    {
        return $this->ok(InvoiceResource::make($this->invoices->details($invoice)));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'customer_id' => ['nullable', 'string'],
            'customer_name' => ['nullable', 'string', 'max:255'],

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

        return $this->show($id);
    }
}

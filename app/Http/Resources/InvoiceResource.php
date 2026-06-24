<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use function data_get;

class InvoiceResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => data_get($this->resource, 'id'),
            'uuid' => data_get($this->resource, 'uuid'),
            'edited_by_id' => data_get($this->resource, 'edited_by_id'),

            'customer_id' => data_get($this->resource, 'customer_id'),
            'customer_name' => data_get($this->resource, 'customer_name'),

            'total' => (float) data_get($this->resource, 'total', 0),
            'total_received' => (float) data_get($this->resource, 'total_received', 0),
            'type_facture' => data_get($this->resource, 'type_facture'),
            'nature' => data_get($this->resource, 'nature'),
            'status' => data_get($this->resource, 'status'),
            'note' => data_get($this->resource, 'note'),
            'done_at' => data_get($this->resource, 'done_at'),
            'created_at' => data_get($this->resource, 'created_at'),
            'updated_at' => data_get($this->resource, 'updated_at'),

            'customer' => data_get($this->resource, 'customer'),
            'seller' => data_get($this->resource, 'seller'),

            'details' => data_get($this->resource, 'details'),
        ];
    }
}

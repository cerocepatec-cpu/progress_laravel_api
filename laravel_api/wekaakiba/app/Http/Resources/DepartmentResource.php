<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class DepartmentResource extends JsonResource
{
    public function toArray($request)
    {
        return [
            'id'              => (string) $this->id,
            'department_name' => $this->department_name,
            'createdby'       => $this->createdby,
            'description'     => $this->description,
            'header_depart'   => $this->header_depart,
            'requests'        => $this->requests,
            'nbrusers'        => $this->nbrusers, // 👈 attribut exposé
            'created_at'      => $this->created_at,
            'updated_at'      => $this->updated_at,
        ];
    }
}


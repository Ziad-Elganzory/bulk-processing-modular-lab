<?php

namespace Modules\Orders\Services;

use Illuminate\Support\Facades\Validator;

class OrderRowValidator
{
    /**
     * @param  array<string, mixed>  $row
     * @return array{
     *     valid: bool,
     *     attributes: array<string, mixed>,
     *     errors: array<string, list<string>>
     * }
     */
    public function validate(array $row): array
    {
        $validator = Validator::make($row, [
            'order_id' => ['required', 'string', 'max:255'],
            'customer_id' => ['required', 'string', 'max:100'],
            'amount' => ['required', 'numeric', 'gt:0'],
            'currency' => ['required', 'string', 'size:3', 'alpha'],
            'order_date' => ['required', 'date'],
        ]);

        if ($validator->fails()) {
            return [
                'valid' => false,
                'attributes' => [],
                'errors' => $validator->errors()->toArray(),
            ];
        }

        $attributes = $validator->validated();
        $attributes['currency'] = strtoupper($attributes['currency']);

        return [
            'valid' => true,
            'attributes' => $attributes,
            'errors' => [],
        ];
    }
}

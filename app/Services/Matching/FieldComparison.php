<?php

namespace App\Services\Matching;

class FieldComparison
{
    public function __construct(private MatchEngine $engine) {}

    public function compare(string $field, ?string $left, ?string $right): array
    {
        $left = trim((string) $left);
        $right = trim((string) $right);
        if (in_array($field, ['user', 'reg'], true) || $left === '' || $right === '') {
            return ['label' => 'Not compared', 'color' => 'bg-secondary-lt'];
        }
        if ($left === $right) {
            return ['label' => 'Exact match', 'color' => 'bg-green-lt'];
        }
        if (in_array($field, ['name', 'fname', 'mname'], true) && $this->engine->similar($left, $right)) {
            return ['label' => 'Partial match', 'color' => 'bg-azure-lt'];
        }

        return ['label' => 'Not matched', 'color' => 'bg-red-lt'];
    }
}

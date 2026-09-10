<?php

namespace App\Services;

class ProspectScoringService
{
    public function calculate(int $fit, int $pain, int $contact): array
    {
        $fit = max(0, min(3, $fit));
        $pain = max(0, min(3, $pain));
        $contact = max(0, min(3, $contact));
        $total = $fit + $pain + $contact;

        return [
            'total' => $total,
            'priority' => $total >= 7 ? 'tinggi' : ($total >= 4 ? 'sedang' : 'rendah'),
        ];
    }
}

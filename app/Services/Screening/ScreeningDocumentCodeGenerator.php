<?php

namespace App\Services\Screening;

use App\Models\Screening\ScreeningSession;
use Illuminate\Support\Str;

class ScreeningDocumentCodeGenerator
{
    public function generate(): string
    {
        do {
            $code = 'VS-'.Str::upper(Str::random(20));
        } while (ScreeningSession::query()->where('document_authenticity_code', $code)->exists());

        return $code;
    }
}

<?php

namespace App\Models;

use App\Models\Concerns\HasSpanishAliases;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InquiryNote extends Model
{
    use HasSpanishAliases;

    protected $table = 'consulta_notas';

    protected $fillable = [
        'inquiry_id',
        'user_id',
        'body',
    ];

    protected function aliases(): array
    {
        return [
            'inquiry_id' => 'consulta_id',
            'body' => 'cuerpo',
        ];
    }

    public function inquiry(): BelongsTo
    {
        return $this->belongsTo(Inquiry::class, 'consulta_id');
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}

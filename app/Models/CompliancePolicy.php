<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CompliancePolicy extends Model
{
    protected $table = 'compliance_policies';

    protected $guarded = [];

    public function dealer(): BelongsTo
    {
        return $this->belongsTo(Dealer::class);
    }

    public function downloadName(): string
    {
        $original = trim((string) $this->original_filename);
        if ($original !== '') {
            return $original;
        }

        $slug = preg_replace('/[^A-Za-z0-9\- ]+/', '', (string) $this->name) ?: 'policy';

        return trim($slug).'.pdf';
    }
}

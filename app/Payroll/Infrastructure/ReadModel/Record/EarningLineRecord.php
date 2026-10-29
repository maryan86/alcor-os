<?php

declare(strict_types=1);

namespace App\Payroll\Infrastructure\ReadModel\Record;

use App\Shared\Domain\Money\Currency;
use DateTimeImmutable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property string $line_id
 * @property Currency $currency
 * @property int $system_value_minor
 * @property int $current_value_minor
 * @property bool $is_manually_adjusted
 * @property DateTimeImmutable $updated_at
 * @property-read Collection<int, AuditEntryRecord> $auditEntries
 */
final class EarningLineRecord extends Model
{
    public $incrementing = false;

    public $timestamps = false;

    protected $table = 'earning_lines';

    protected $primaryKey = 'line_id';

    protected $keyType = 'string';

    protected $dateFormat = 'Y-m-d H:i:s.u';

    protected $fillable = [
        'line_id',
        'currency',
        'system_value_minor',
        'current_value_minor',
        'is_manually_adjusted',
        'updated_at',
    ];

    /** @return HasMany<AuditEntryRecord, $this> */
    public function auditEntries(): HasMany
    {
        return $this->hasMany(AuditEntryRecord::class, 'line_id', 'line_id')->orderBy('sequence');
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'currency' => Currency::class,
            'system_value_minor' => 'integer',
            'current_value_minor' => 'integer',
            'is_manually_adjusted' => 'boolean',
            'updated_at' => 'immutable_datetime',
        ];
    }
}

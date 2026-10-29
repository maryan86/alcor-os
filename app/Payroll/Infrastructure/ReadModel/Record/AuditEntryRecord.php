<?php

declare(strict_types=1);

namespace App\Payroll\Infrastructure\ReadModel\Record;

use App\Payroll\Application\ReadModel\View\AuditEntryType;
use DateTimeImmutable;
use Illuminate\Database\Eloquent\Model;

/**
 * @property string $line_id
 * @property int $sequence
 * @property AuditEntryType $type
 * @property int $amount_minor
 * @property int $value_after_minor
 * @property int|null $adjustment_number
 * @property int|null $corrects_adjustment_number
 * @property string|null $comment
 * @property string|null $adjusted_by
 * @property DateTimeImmutable $occurred_at
 */
final class AuditEntryRecord extends Model
{
    public $timestamps = false;

    protected $table = 'earning_line_audit_entries';

    protected $dateFormat = 'Y-m-d H:i:s.u';

    protected $fillable = [
        'line_id',
        'sequence',
        'type',
        'amount_minor',
        'value_after_minor',
        'adjustment_number',
        'corrects_adjustment_number',
        'comment',
        'adjusted_by',
        'occurred_at',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'sequence' => 'integer',
            'type' => AuditEntryType::class,
            'amount_minor' => 'integer',
            'value_after_minor' => 'integer',
            'adjustment_number' => 'integer',
            'corrects_adjustment_number' => 'integer',
            'occurred_at' => 'immutable_datetime',
        ];
    }
}

<?php

declare(strict_types=1);

namespace AdeRidwan\PayRelayPhp\Models;

use AdeRidwan\PayRelayPhp\Enums\PaymentStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * @property int $id
 * @property string $reference_id
 * @property string|null $payable_type
 * @property int|string|null $payable_id
 * @property int $amount_original
 * @property string $unique_code
 * @property int $amount_final
 * @property string $qris_static
 * @property string $qris_dynamic
 * @property PaymentStatus $status
 * @property \Carbon\Carbon $expires_at
 * @property \Carbon\Carbon|null $paid_at
 * @property array|null $raw_notification
 * @property \Illuminate\Database\Eloquent\Model|null $payable
 */
class PayRelayPayment extends Model
{
    protected $fillable = [
        'reference_id',
        'payable_type',
        'payable_id',
        'amount_original',
        'unique_code',
        'amount_final',
        'qris_static',
        'qris_dynamic',
        'status',
        'expires_at',
        'paid_at',
        'raw_notification',
    ];

    protected $casts = [
        'status' => PaymentStatus::class,
        'expires_at' => 'datetime',
        'paid_at' => 'datetime',
        'raw_notification' => 'array',
    ];

    public function getTable(): string
    {
        return config('pay-relay.table', 'pay_relay_payments');
    }

    public function payable(): MorphTo
    {
        return $this->morphTo();
    }

    public function isPending(): bool
    {
        return $this->status === PaymentStatus::Pending;
    }

    public function isPaid(): bool
    {
        return $this->status === PaymentStatus::Paid;
    }

    public function isExpired(): bool
    {
        return $this->status === PaymentStatus::Expired
            || ($this->status === PaymentStatus::Pending && $this->expires_at->isPast());
    }

    public function scopePending($query)
    {
        return $query->where('status', PaymentStatus::Pending->value);
    }

    public function scopeOverdue($query)
    {
        return $query->where('status', PaymentStatus::Pending->value)
            ->where('expires_at', '<', now());
    }
}

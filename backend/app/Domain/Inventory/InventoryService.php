<?php

namespace App\Domain\Inventory;

use App\Exceptions\ApiException;
use App\Models\Inventory;
use App\Models\InventoryReservation;
use App\Models\InventoryTransaction;
use App\Models\Order;
use App\Models\ProductVariant;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * The only writer of stock (ARCHITECTURE §6.8). Every on_hand change appends a
 * ledger row in the same transaction; reservations hold stock during checkout.
 * Callers must already be inside a DB transaction for reserve/commit/release.
 */
class InventoryService
{
    public const RESERVATION_MINUTES = 30;

    /**
     * Manual adjustment from admin or seeding (positive or negative quantity).
     */
    public function adjust(ProductVariant $variant, string $type, int $quantity, ?string $note = null, ?User $actor = null): Inventory
    {
        return DB::transaction(function () use ($variant, $type, $quantity, $note, $actor) {
            $inventory = $this->lockFor($variant->id);
            $newOnHand = $inventory->on_hand + $quantity;

            if ($newOnHand < $inventory->reserved) {
                throw new ApiException('Stock cannot go below the quantity reserved for open orders.', 409, 'stock_below_reserved');
            }

            $inventory->forceFill(['on_hand' => $newOnHand])->save();
            $this->ledger($variant->id, $type, $quantity, $newOnHand, $note, $actor?->getKey());

            return $inventory;
        });
    }

    /**
     * Reserve stock for an order's tracked lines. Rows are locked in id order to avoid deadlocks.
     *
     * @param  array<int, int>  $quantities  variant_id => quantity
     */
    public function reserve(Order $order, array $quantities): void
    {
        ksort($quantities);
        $expiresAt = now()->addMinutes(self::RESERVATION_MINUTES);

        foreach ($quantities as $variantId => $quantity) {
            $inventory = $this->lockFor($variantId);

            if ($inventory->available() < $quantity) {
                throw new ApiException('Some items are no longer available in the quantity requested.', 409, 'out_of_stock', ['variant_id' => [$variantId]]);
            }

            $inventory->forceFill(['reserved' => $inventory->reserved + $quantity])->save();

            InventoryReservation::create([
                'variant_id' => $variantId, 'order_id' => $order->id, 'quantity' => $quantity,
                'status' => 'active', 'expires_at' => $expiresAt,
            ]);
        }
    }

    /**
     * Payment captured: reserved stock becomes sold. Returns false if stock was
     * insufficient (late payment after expiry) so the order can be flagged.
     */
    public function commit(Order $order): bool
    {
        $ok = true;

        foreach ($this->reservationsFor($order) as $reservation) {
            $inventory = $this->lockFor($reservation->variant_id);

            if ($reservation->status === 'active') {
                $inventory->reserved = max(0, $inventory->reserved - $reservation->quantity);
            } elseif ($inventory->available() < $reservation->quantity) {
                $ok = false; // reservation had expired and stock was sold elsewhere
            }

            $inventory->on_hand -= $reservation->quantity;
            $inventory->save();
            $reservation->forceFill(['status' => 'committed'])->save();
            $this->ledger($reservation->variant_id, 'sale', -$reservation->quantity, $inventory->on_hand, null, null, 'order', $order->id);
        }

        return $ok;
    }

    /** Payment failed / order cancelled / reservation expired: give stock back. */
    public function release(Order $order, string $status = 'released'): void
    {
        foreach ($this->reservationsFor($order)->where('status', 'active') as $reservation) {
            $inventory = $this->lockFor($reservation->variant_id);
            $inventory->forceFill(['reserved' => max(0, $inventory->reserved - $reservation->quantity)])->save();
            $reservation->forceFill(['status' => $status])->save();
        }
    }

    /** Returned or cancelled-after-payment stock goes back on hand. */
    public function restock(ProductVariant $variant, int $quantity, string $type, Order $order, ?User $actor = null): void
    {
        $inventory = $this->lockFor($variant->id);
        $inventory->forceFill(['on_hand' => $inventory->on_hand + $quantity])->save();
        $this->ledger($variant->id, $type, $quantity, $inventory->on_hand, null, $actor?->getKey(), 'order', $order->id);
    }

    private function lockFor(int $variantId): Inventory
    {
        Inventory::query()->firstOrCreate(['variant_id' => $variantId], ['on_hand' => 0, 'reserved' => 0]);

        return Inventory::query()->where('variant_id', $variantId)->lockForUpdate()->firstOrFail();
    }

    /** @return Collection<int, InventoryReservation> */
    private function reservationsFor(Order $order): Collection
    {
        return InventoryReservation::query()->where('order_id', $order->id)->orderBy('variant_id')->lockForUpdate()->get();
    }

    private function ledger(int $variantId, string $type, int $quantity, int $balanceAfter, ?string $note, ?int $actorId, ?string $refType = null, ?int $refId = null): void
    {
        InventoryTransaction::create([
            'variant_id' => $variantId, 'type' => $type, 'quantity' => $quantity, 'balance_after' => $balanceAfter,
            'reference_type' => $refType, 'reference_id' => $refId, 'actor_id' => $actorId, 'note' => $note,
        ]);
    }
}

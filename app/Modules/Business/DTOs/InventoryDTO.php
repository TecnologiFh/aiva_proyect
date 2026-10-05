<?php

namespace App\Modules\Business\DTOs;

class InventoryDTO
{
    public const STATUS_IN_STOCK = 'IN_STOCK';
    public const STATUS_LOW_STOCK = 'LOW_STOCK';
    public const STATUS_OUT_OF_STOCK = 'OUT_OF_STOCK';
    public const STATUS_UNKNOWN = 'UNKNOWN';

    public function __construct(
        public readonly ?int $stock = null,
        public readonly string $stockStatus = self::STATUS_UNKNOWN
    ) {}

    public static function fromRaw(?string $rawStock): self
    {
        if ($rawStock === null || trim($rawStock) === '' || strtoupper(trim($rawStock)) === 'N/D') {
            return new self(stock: null, stockStatus: self::STATUS_UNKNOWN);
        }

        $cleaned = trim($rawStock);
        if (is_numeric($cleaned)) {
            $qty = (int)$cleaned;
            if ($qty <= 0) {
                return new self(stock: 0, stockStatus: self::STATUS_OUT_OF_STOCK);
            }
            if ($qty <= 3) {
                return new self(stock: $qty, stockStatus: self::STATUS_LOW_STOCK);
            }
            return new self(stock: $qty, stockStatus: self::STATUS_IN_STOCK);
        }

        return new self(stock: null, stockStatus: self::STATUS_UNKNOWN);
    }

    public function toArray(): array
    {
        return [
            'stock' => $this->stock,
            'stock_status' => $this->stockStatus,
        ];
    }
}

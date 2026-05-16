<?php

declare(strict_types=1);

namespace App\Domain\ValueObject;

use Brick\Money\Money;

/**
 * Immutable value object representing a product price in CZK.
 *
 * Built on top of brick/money to ensure zero float-error arithmetic
 * and proper currency handling.
 */
final readonly class Price
{
    private const DEFAULT_CURRENCY = 'CZK';

    /**
     * Private constructor to enforce factory method usage.
     */
    private function __construct(private Money $money)
    {
    }

    /**
     * Creates a Price from a minor unit integer amount (as string).
     *
     * @param string $amount Minor unit amount (e.g. "19990" for 199.90 CZK)
     */
    public static function of(string $amount): self
    {
        $money = Money::ofMinor($amount, self::DEFAULT_CURRENCY);
        if ($money->isNegative()) {
            throw new \InvalidArgumentException('Price amount cannot be negative');
        }

        return new self($money);
    }

    /**
     * Returns the amount in minor currency units (integer) as a string.
     *
     * For CZK this excludes the decimal point (e.g. "100.50" -> "10050").
     */
    public function amount(): string
    {
        return $this->money->getMinorAmount()->toString();
    }

    /**
     * Returns the price formatted according to Czech locale (cs_CZ).
     */
    public function formatted(): string
    {
        return $this->money->formatToLocale('cs_CZ');
    }

    public function currency(): string
    {
        return $this->money->getCurrency()->getCurrencyCode();
    }

    public function toMoney(): Money
    {
        return $this->money;
    }
}

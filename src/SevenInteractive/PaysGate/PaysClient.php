<?php

declare(strict_types=1);

namespace SevenInteractive\PaysGate;

class PaysClient
{

    /** @var int */
    private $paysMerchant;
    /** @var int */
    private $paysShop;
    /** @var string */
    private $paysApiPassword;

    const URL = "https://www.pays.cz/paymentorder";
    const PARAMETER_MERCHANT = 'Merchant';
    const PARAMETER_SHOP = 'Shop';
    const PARAMETER_AMOUNT = 'Amount';
    const PARAMETER_CURRENCY = 'Currency';
    const PARAMETER_MERCHANT_ORDER_NUMBER = 'MerchantOrderNumber';
    const PARAMETER_CLIENT_EMAIL = 'Email';
    const PARAMETER_CLIENT_LANG = 'Lang';
    const PARAMETER_RETURN_URL = 'ReturnURL';

    const PARAMETER_PAYMENT_ORDER_ID = 'PaymentOrderID';
    const PARAMETER_PAYMENT_ORDER_STATUS_ID = 'PaymentOrderStatusID';
    const PARAMETER_STATUS_ID = 'Status';
    const PARAMETER_CURRENCY_ID = 'CurrencyID';
    const PARAMETER_CURRENCY_BASE_UNITS = 'CurrencyBaseUnits';
    const PARAMETER_PAYMENT_ORDER_STATUS_DESCRIPTION = 'PaymentOrderStatusDescription';
    const PARAMETER_PAYMENT_HASH = 'hash';

   const CURRENCY_CZK = 'CZK';
   const CURRENCY_EUR = 'EUR';
   const CURRENCY_USD = 'USD';
    const ALLOWED_CURRENCIES = [
        self::CURRENCY_CZK,
        self::CURRENCY_EUR,
        self::CURRENCY_USD,
    ];

   const LANG_CS_CZ = 'CS-CZ';
   const LANG_SK_SK = 'SK-SK';
   const LANG_EN_US = 'EN-US';
   const LANG_RU_RU = 'RU-RU';
   const LANG_JA_JP = 'JA-JP';
    const ALLOWED_LANGS = [
        self::LANG_CS_CZ,
        self::LANG_SK_SK,
        self::LANG_EN_US,
        self::LANG_RU_RU,
        self::LANG_JA_JP,
    ];

    const PAYMENT_ORDER_STATUS_RECEIVED = 1;
    const PAYMENT_ORDER_STATUS_FAILED = 2;
    const PAYMENT_ORDER_STATUS_SUCCESS = 3;

   const PAYMENT_STATUS_NOT_PROCESSED = 'NOT_PROCESSED';
   const PAYMENT_STATUS_FAILED = 'FAILED';
   const PAYMENT_STATUS_SUCCESS = 'SUCCESS';

    /**
     * PaysClient public constructor.
     * @param int $paysMerchant
     * @param int $paysShop
     * @param string $paysApiPassword
     */
    public function __construct(int $paysMerchant, int $paysShop, string $paysApiPassword)
    {
        $this->paysMerchant = $paysMerchant;
        $this->paysShop = $paysShop;
        $this->paysApiPassword = $paysApiPassword;
    }

    /**
     * @param int $amountInCents
     * @param string $currency
     * @param string $clientEmail
     * @param string $lang
     * @param mixed $paymentId
     * @param string|null $returnUrl
     * @return string
     * @throws InvalidCurrencyProvided
     * @throws InvalidLangProvided
     */
    public function getPaymentUrl(
        int $amountInCents,
        string $currency,
        string $clientEmail,
        string $lang,
        $paymentId,
        string $returnUrl = null
    ): string {
        if (!in_array($currency, self::ALLOWED_CURRENCIES)) {
            throw new InvalidCurrencyProvided();
        }
        if (!in_array($lang, self::ALLOWED_LANGS)) {
            throw new InvalidLangProvided();
        }
        return self::URL . '?' . http_build_query([
            self::PARAMETER_MERCHANT => $this->paysMerchant,
            self::PARAMETER_SHOP => $this->paysShop,
            self::PARAMETER_AMOUNT => $amountInCents,
            self::PARAMETER_CURRENCY => $currency,
            self::PARAMETER_MERCHANT_ORDER_NUMBER => $paymentId,
            self::PARAMETER_CLIENT_EMAIL => $clientEmail,
            self::PARAMETER_CLIENT_LANG => $lang,
            self::PARAMETER_RETURN_URL => $returnUrl,
        ]);
    }

    /**
     * @return string see PAYMENT_STATUS_* constants
     * @throws HashesNotEqualException
     */
    public function getPaymentStatus(array $response): string
    {
        $paymentOrderId = $response[self::PARAMETER_PAYMENT_ORDER_ID];
        $merchantOrderId = $response[self::PARAMETER_MERCHANT_ORDER_NUMBER];
        $currencyId = $response[self::PARAMETER_CURRENCY_ID];
        $amount = $response[self::PARAMETER_AMOUNT];
        $currencyBaseUnits = $response[self::PARAMETER_CURRENCY_BASE_UNITS];
        $paymentOrderStatusId = $response[self::PARAMETER_PAYMENT_ORDER_STATUS_ID] ?? $response[self::PARAMETER_STATUS_ID];
        $hash = $response[self::PARAMETER_PAYMENT_HASH];

        if ($paymentOrderStatusId === self::PAYMENT_ORDER_STATUS_RECEIVED || is_null($hash)) {
            return self::PAYMENT_STATUS_NOT_PROCESSED;
        }

        $stringToHash = implode(
            '',
            [
                $paymentOrderId,
                $merchantOrderId,
                $paymentOrderStatusId,
                $currencyId,
                $amount,
                $currencyBaseUnits,
            ]
        );
        $md5hmacHash = hash_hmac('md5', $stringToHash, $this->paysApiPassword);
        if (!hash_equals($md5hmacHash, $hash)) {
            throw new HashesNotEqualException();
        }

        if ($paymentOrderStatusId === self::PAYMENT_ORDER_STATUS_SUCCESS) {
            return self::PAYMENT_STATUS_SUCCESS;
        } else {
            return self::PAYMENT_STATUS_FAILED;
        }
    }

}
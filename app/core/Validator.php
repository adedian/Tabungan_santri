<?php
declare(strict_types=1);

namespace App\Core;

use DateTime;

/**
 * Validator ringkas. Aturan dipisah "|":
 *   required, nullable, string, int, numeric, min:n, max:n, in:a,b,c, date, email
 * min/max: nilai untuk int/numeric, panjang untuk string.
 */
final class Validator
{
    private array $errors = [];
    private array $clean = [];

    private function __construct(private array $data, private array $rules, private array $labels)
    {
        $this->run();
    }

    public static function make(array $data, array $rules, array $labels = []): self
    {
        return new self($data, $rules, $labels);
    }

    public function fails(): bool
    {
        return $this->errors !== [];
    }

    /** @return array<string,string> field => pesan pertama */
    public function errors(): array
    {
        return $this->errors;
    }

    /** Hanya field yang tercantum di rules, sudah di-trim. */
    public function validated(): array
    {
        return $this->clean;
    }

    private function run(): void
    {
        foreach ($this->rules as $field => $ruleString) {
            $rules = explode('|', $ruleString);
            $value = $this->data[$field] ?? null;
            if (is_string($value)) {
                $value = trim($value);
            }
            $label = $this->labels[$field] ?? ucfirst(str_replace('_', ' ', $field));
            $empty = $value === null || $value === '';

            if ($empty) {
                if (in_array('required', $rules, true)) {
                    $this->errors[$field] = "{$label} wajib diisi.";
                }
                $this->clean[$field] = null;
                continue;
            }

            foreach ($rules as $rule) {
                [$name, $arg] = array_pad(explode(':', $rule, 2), 2, null);
                if (($message = $this->check($name, $arg, $value, $label)) !== null) {
                    $this->errors[$field] = $message;
                    break;
                }
            }
            $this->clean[$field] = $value;
        }
    }

    private function check(string $rule, ?string $arg, mixed $value, string $label): ?string
    {
        switch ($rule) {
            case 'string':
                return is_string($value) ? null : "{$label} tidak valid.";
            case 'int':
                return filter_var($value, FILTER_VALIDATE_INT) !== false ? null : "{$label} harus berupa bilangan bulat.";
            case 'numeric':
                return is_numeric($value) ? null : "{$label} harus berupa angka.";
            case 'min':
                return $this->measure($value) >= (float) $arg ? null
                    : (is_numeric($value) ? "{$label} minimal {$arg}." : "{$label} minimal {$arg} karakter.");
            case 'max':
                return $this->measure($value) <= (float) $arg ? null
                    : (is_numeric($value) ? "{$label} maksimal {$arg}." : "{$label} maksimal {$arg} karakter.");
            case 'in':
                return in_array((string) $value, explode(',', (string) $arg), true) ? null : "{$label} tidak valid.";
            case 'date':
                $d = DateTime::createFromFormat('Y-m-d', (string) $value);
                return ($d && $d->format('Y-m-d') === $value) ? null : "{$label} bukan tanggal yang valid.";
            case 'email':
                return filter_var($value, FILTER_VALIDATE_EMAIL) ? null : "{$label} bukan email yang valid.";
            default:
                return null; // required, nullable, dsb. ditangani di run()
        }
    }

    private function measure(mixed $value): float
    {
        return is_numeric($value) ? (float) $value : (float) mb_strlen((string) $value);
    }
}

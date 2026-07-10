<?php

namespace App\Core;

/**
 * تحقق بسيط من المدخلات. rules مثال: ['name' => 'required|max:100', 'email' => 'required|email']
 */
class Validator
{
    private array $errors = [];

    public static function make(array $data, array $rules): self
    {
        $v = new self();
        foreach ($rules as $field => $ruleString) {
            $rules = explode('|', $ruleString);
            $value = $data[$field] ?? null;

            foreach ($rules as $rule) {
                $param = null;
                if (str_contains($rule, ':')) {
                    [$rule, $param] = explode(':', $rule, 2);
                }

                if (!$v->passRule($rule, $value, $param, $data)) {
                    $v->errors[$field] = $v->message($field, $rule, $param);
                    break;
                }
            }
        }
        return $v;
    }

    private function passRule(string $rule, $value, ?string $param, array $data): bool
    {
        return match ($rule) {
            'required' => $value !== null && trim((string) $value) !== '',
            'email' => $value === null || $value === '' || filter_var($value, FILTER_VALIDATE_EMAIL) !== false,
            'max' => $value === null || mb_strlen((string) $value) <= (int) $param,
            'min' => $value === null || mb_strlen((string) $value) >= (int) $param,
            'numeric' => $value === null || $value === '' || is_numeric($value),
            'in' => $value === null || $value === '' || in_array($value, explode(',', $param), true),
            default => true,
        };
    }

    private function message(string $field, string $rule, ?string $param): string
    {
        return match ($rule) {
            'required' => 'هذا الحقل مطلوب.',
            'email' => 'البريد الإلكتروني غير صحيح.',
            'max' => "الحد الأقصى للأحرف هو {$param}.",
            'min' => "الحد الأدنى للأحرف هو {$param}.",
            'numeric' => 'يجب أن تكون القيمة رقماً.',
            'in' => 'القيمة المختارة غير صحيحة.',
            default => 'قيمة غير صالحة.',
        };
    }

    public function fails(): bool
    {
        return count($this->errors) > 0;
    }

    public function errors(): array
    {
        return $this->errors;
    }

    public function firstError(): ?string
    {
        $first = reset($this->errors);
        return $first ?: null;
    }
}

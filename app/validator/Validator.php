<?php
namespace App\Validator;

use Database;

class Validator {
    private array $data;
    private array $errors = [];

    public function __construct(array $data) {
        $this->data = $data;
    }

    public static function make(array $data, array $rules): self {
        $validator = new self($data);
        $validator->validate($rules);
        return $validator;
    }

    public function validate(array $rules): void {
        foreach ($rules as $field => $fieldRules) {
            $value = $this->data[$field] ?? null;
            $ruleList = is_string($fieldRules) ? explode('|', $fieldRules) : $fieldRules;

            foreach ($ruleList as $rule) {
                $params = [];
                if (str_contains($rule, ':')) {
                    [$ruleName, $paramStr] = explode(':', $rule, 2);
                    $params = explode(',', $paramStr);
                } else {
                    $ruleName = $rule;
                }

                $fieldLabel = ucwords(str_replace('_', ' ', $field));

                switch ($ruleName) {
                    case 'required':
                        if ($value === null || trim((string)$value) === '') {
                            $this->addError($field, "{$fieldLabel} wajib diisi.");
                        }
                        break;

                    case 'email':
                        if (!empty($value) && !filter_var($value, FILTER_VALIDATE_EMAIL)) {
                            $this->addError($field, "Format {$fieldLabel} tidak valid.");
                        }
                        break;

                    case 'min':
                        $min = (int)($params[0] ?? 0);
                        if (!empty($value) && mb_strlen((string)$value) < $min) {
                            $this->addError($field, "{$fieldLabel} minimal {$min} karakter.");
                        }
                        break;

                    case 'max':
                        $max = (int)($params[0] ?? 255);
                        if (!empty($value) && mb_strlen((string)$value) > $max) {
                            $this->addError($field, "{$fieldLabel} maksimal {$max} karakter.");
                        }
                        break;

                    case 'numeric':
                        if (!empty($value) && !is_numeric($value)) {
                            $this->addError($field, "{$fieldLabel} harus berupa angka.");
                        }
                        break;

                    case 'in':
                        if (!empty($value) && !in_array($value, $params)) {
                            $this->addError($field, "Pilihan {$fieldLabel} tidak valid.");
                        }
                        break;

                    case 'unique':
                        $table = $params[0] ?? '';
                        $col = $params[1] ?? $field;
                        $ignoreId = $params[2] ?? null;

                        if (!empty($value) && !empty($table)) {
                            require_once CONFIG_PATH . '/database.php';
                            $db = Database::getConnection();
                            $sql = "SELECT COUNT(*) as c FROM `{$table}` WHERE `{$col}` = :val";
                            $p = ['val' => $value];
                            if ($ignoreId) {
                                $sql .= " AND id != :ignore_id";
                                $p['ignore_id'] = $ignoreId;
                            }
                            $stmt = $db->prepare($sql);
                            $stmt->execute($p);
                            if ((int)$stmt->fetchColumn() > 0) {
                                $this->addError($field, "{$fieldLabel} sudah digunakan.");
                            }
                        }
                        break;
                }
            }
        }
    }

    public function fails(): bool {
        return !empty($this->errors);
    }

    public function errors(): array {
        return $this->errors;
    }

    public function firstError(): ?string {
        foreach ($this->errors as $fieldErrors) {
            return $fieldErrors[0] ?? null;
        }
        return null;
    }

    private function addError(string $field, string $message): void {
        if (!isset($this->errors[$field])) {
            $this->errors[$field] = [];
        }
        $this->errors[$field][] = $message;
    }
}


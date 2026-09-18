<?php

namespace Darvis\MkgClient\Support;

class FieldMetaNormalizer
{
    /**
     * @param  array<string, array{label: string, type: string, isDatabaseField: bool}>  $meta
     * @return string[]
     */
    public function extractFieldNames(array $meta, bool $databaseOnly): array
    {
        $fields = [];

        foreach ($meta as $fieldName => $fieldMeta) {
            if ($databaseOnly && ! $fieldMeta['isDatabaseField']) {
                continue;
            }

            $fields[] = $fieldName;
        }

        return $fields;
    }

    /**
     * @return array<string, array{label: string, type: string, isDatabaseField: bool}>
     */
    public function loadFieldMetaFromCsv(string $path): array
    {
        if (! is_file($path) || ! is_readable($path)) {
            return [];
        }

        $handle = fopen($path, 'r');

        if ($handle === false) {
            return [];
        }

        $meta = [];

        try {
            fgetcsv($handle, 0, ';', '"', '');

            while (($row = fgetcsv($handle, 0, ';', '"', '')) !== false) {
                $fieldName = isset($row[0]) ? trim((string) $row[0]) : '';

                if ($fieldName === '') {
                    continue;
                }

                $label = isset($row[1]) ? trim((string) $row[1]) : '';
                $type = isset($row[3]) ? trim((string) $row[3]) : '';
                $isDatabaseField = isset($row[5])
                    ? strtoupper(trim((string) $row[5])) === 'WAAR'
                    : false;

                $meta[$fieldName] = [
                    'label' => $label,
                    'type' => strtolower($type),
                    'isDatabaseField' => $isDatabaseField,
                ];
            }
        } finally {
            fclose($handle);
        }

        return $meta;
    }

    /**
     * Applies type-based normalization for known fields from metadata.
     * Unknown fields are returned unchanged.
     *
     * @param  array<int, array<string, mixed>>  $rows
     * @param  array<string, array{label: string, type: string, isDatabaseField: bool}>  $meta
     * @return array<int, array<string, mixed>>
     */
    public function normalizeRows(array $rows, array $meta): array
    {
        if ($meta === []) {
            return $rows;
        }

        foreach ($rows as $rowIndex => $row) {
            foreach ($row as $fieldName => $value) {
                if (! isset($meta[$fieldName])) {
                    continue;
                }

                $rows[$rowIndex][$fieldName] = $this->normalizeValueByType($value, $meta[$fieldName]['type']);
            }
        }

        return $rows;
    }

    /**
     * @param  string[]  $defaultFieldList
     * @param  array<string, array{label: string, type: string, isDatabaseField: bool}>  $meta
     * @return string[]
     */
    public function filterAvailableFieldList(array $defaultFieldList, array $meta): array
    {
        if ($meta === []) {
            return $defaultFieldList;
        }

        return array_values(array_filter(
            $defaultFieldList,
            static fn (string $fieldName): bool => isset($meta[$fieldName])
        ));
    }

    public function normalizeValueByType(mixed $value, string $type): mixed
    {
        if ($value === null) {
            return null;
        }

        $type = strtolower(trim($type));

        if (in_array($type, ['integer'], true)) {
            return is_numeric($value) ? (int) $value : $value;
        }

        if (in_array($type, ['decimal', 'percentage', 'bedrag'], true)) {
            return $this->toFloatOrOriginal($value);
        }

        if (in_array($type, ['logical'], true)) {
            return $this->toBoolOrOriginal($value);
        }

        if (in_array($type, ['datum', 'date'], true)) {
            return $this->normalizeDateOrOriginal($value);
        }

        if (in_array($type, ['character', 'omschrijving', 'memo', 'e-mail', 'email', 'naw'], true)) {
            return is_scalar($value) ? (string) $value : $value;
        }

        return $value;
    }

    private function toFloatOrOriginal(mixed $value): mixed
    {
        if (is_int($value) || is_float($value)) {
            return (float) $value;
        }

        if (! is_string($value)) {
            return $value;
        }

        if (is_numeric($value)) {
            return (float) $value;
        }

        $trimmed = trim($value);
        $normalized = str_replace('.', '', $trimmed);
        $normalized = str_replace(',', '.', $normalized);

        return is_numeric($normalized) ? (float) $normalized : $value;
    }

    private function toBoolOrOriginal(mixed $value): mixed
    {
        if (is_bool($value)) {
            return $value;
        }

        if (is_int($value) || is_float($value)) {
            if ($value === 1 || $value === 1.0) {
                return true;
            }

            if ($value === 0 || $value === 0.0) {
                return false;
            }

            return $value;
        }

        if (! is_string($value)) {
            return $value;
        }

        $normalized = strtolower(trim($value));

        if (in_array($normalized, ['1', 'true', 'waar', 'yes', 'ja'], true)) {
            return true;
        }

        if (in_array($normalized, ['0', 'false', 'onwaar', 'no', 'nee'], true)) {
            return false;
        }

        return $value;
    }

    private function normalizeDateOrOriginal(mixed $value): mixed
    {
        if (! is_string($value)) {
            return $value;
        }

        $trimmed = trim($value);

        if ($trimmed === '') {
            return $value;
        }

        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $trimmed) === 1) {
            return $trimmed;
        }

        if (preg_match('/^(\d{2})-(\d{2})-(\d{4})$/', $trimmed, $matches) === 1) {
            return $matches[3].'-'.$matches[2].'-'.$matches[1];
        }

        return $value;
    }
}

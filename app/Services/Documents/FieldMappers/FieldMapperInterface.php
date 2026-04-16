<?php

declare(strict_types=1);

namespace App\Services\Documents\FieldMappers;

interface FieldMapperInterface
{
    /**
     * Map extracted fields to model-compatible array.
     */
    public function map(array $extractedFields): array;

    /**
     * Get the target model class.
     */
    public function getModelClass(): string;

    /**
     * Get required fields for this model.
     */
    public function getRequiredFields(): array;

    /**
     * Get optional fields for this model.
     */
    public function getOptionalFields(): array;

    /**
     * Validate mapped data against model rules.
     */
    public function validate(array $mappedData): array;

    /**
     * Get the document subtype this mapper handles.
     */
    public function getSubtype(): string;
}

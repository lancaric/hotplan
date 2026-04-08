<?php

declare(strict_types=1);

namespace HotPlan\Entities;

/**
 * Base Entity
 * 
 * Provides common functionality for all domain entities.
 */
abstract class BaseEntity
{
    protected array $data = [];
    protected bool $isModified = false;
    
    public function __construct(array $data = [])
    {
        $this->data = $data;
    }
    
    /**
     * Get entity ID
     */
    public function getId(): ?int
    {
        return $this->data['id'] ?? null;
    }
    
    /**
     * Set entity ID
     */
    public function setId(int $id): self
    {
        $this->data['id'] = $id;
        $this->isModified = true;
        return $this;
    }
    
    /**
     * Check if entity has been modified
     */
    public function isModified(): bool
    {
        return $this->isModified;
    }
    
    /**
     * Mark entity as saved (not modified)
     */
    public function markAsSaved(): void
    {
        $this->isModified = false;
    }
    
    /**
     * Get raw data
     */
    public function toArray(): array
    {
        return $this->data;
    }
    
    /**
     * Get specific value
     */
    protected function get(string $key, mixed $default = null): mixed
    {
        return $this->data[$key] ?? $default;
    }
    
    /**
     * Set specific value
     */
    protected function set(string $key, mixed $value): self
    {
        if (!isset($this->data[$key]) || $this->data[$key] !== $value) {
            $this->data[$key] = $value;
            $this->isModified = true;
        }
        return $this;
    }
    
    /**
     * Convert to JSON
     */
    public function toJson(): string
    {
        return json_encode($this->data, JSON_THROW_ON_ERROR);
    }
}

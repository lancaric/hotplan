<?php

declare(strict_types=1);

namespace HotPlan\Repositories;

use HotPlan\Database\Connection;

/**
 * State Repository
 * 
 * Manages system state in the database.
 */
class StateRepository
{
    private Connection $db;
    private string $table = 'system_state';
    
    public function __construct(?Connection $db = null)
    {
        $this->db = $db ?? Connection::getInstance();
    }
    
    /**
     * Get a state value
     */
    public function get(string $key, mixed $default = null): mixed
    {
        $sql = "SELECT state_value FROM {$this->table} WHERE state_key = ?";
        $result = $this->db->fetch($sql, [$key]);
        
        return $result['state_value'] ?? $default;
    }
    
    /**
     * Set a state value
     */
    public function set(string $key, ?string $value): void
    {
        // Insert or update
        $sql = "
            INSERT INTO {$this->table} (state_key, state_value, state_type, updated_at)
            VALUES (?, ?, 'string', CURRENT_TIMESTAMP)
            ON CONFLICT(state_key) DO UPDATE SET
                state_value = excluded.state_value,
                updated_at = CURRENT_TIMESTAMP
        ";
        
        $this->db->query($sql, [$key, $value]);
    }
    
    /**
     * Get all state values
     */
    public function getAll(): array
    {
        $sql = "SELECT * FROM {$this->table}";
        return $this->db->fetchAll($sql);
    }
    
    /**
     * Delete a state value
     */
    public function delete(string $key): bool
    {
        $count = $this->db->delete($this->table, 'state_key = ?', [$key]);
        return $count > 0;
    }
    
    /**
     * Check if a state key exists
     */
    public function has(string $key): bool
    {
        $sql = "SELECT 1 FROM {$this->table} WHERE state_key = ? LIMIT 1";
        return $this->db->fetch($sql, [$key]) !== null;
    }
    
    /**
     * Get current forward to
     */
    public function getCurrentForwardTo(): ?string
    {
        return $this->get('current_forward_to');
    }
    
    /**
     * Set current forward to
     */
    public function setCurrentForwardTo(?string $value): void
    {
        $this->set('current_forward_to', $value ?? '');
    }
    
    /**
     * Get last successful forward to
     */
    public function getLastSuccessfulForwardTo(): ?string
    {
        return $this->get('last_successful_forward_to');
    }
    
    /**
     * Set last successful forward to
     */
    public function setLastSuccessfulForwardTo(?string $value): void
    {
        $this->set('last_successful_forward_to', $value ?? '');
    }
    
    /**
     * Get device status
     */
    public function getDeviceStatus(): string
    {
        return $this->get('device_status', 'unknown');
    }
    
    /**
     * Set device status
     */
    public function setDeviceStatus(string $status): void
    {
        $this->set('device_status', $status);
    }
    
    /**
     * Get consecutive failures count
     */
    public function getConsecutiveFailures(): int
    {
        return (int) $this->get('consecutive_failures', '0');
    }
    
    /**
     * Increment consecutive failures
     */
    public function incrementConsecutiveFailures(): void
    {
        $current = $this->getConsecutiveFailures();
        $this->set('consecutive_failures', (string) ($current + 1));
    }
    
    /**
     * Reset consecutive failures
     */
    public function resetConsecutiveFailures(): void
    {
        $this->set('consecutive_failures', '0');
    }
    
    /**
     * Get last check timestamp
     */
    public function getLastCheckAt(): ?\DateTimeImmutable
    {
        $value = $this->get('last_check_at');
        return $value ? new \DateTimeImmutable($value) : null;
    }
    
    /**
     * Set last check timestamp
     */
    public function setLastCheckAt(): void
    {
        $this->set('last_check_at', date('Y-m-d H:i:s'));
    }
    
    /**
     * Get scheduler status
     */
    public function getSchedulerStatus(): string
    {
        return $this->get('scheduler_status', 'stopped');
    }
    
    /**
     * Set scheduler status
     */
    public function setSchedulerStatus(string $status): void
    {
        $this->set('scheduler_status', $status);
    }
    
    /**
     * Get last successful change timestamp
     */
    public function getLastSuccessfulChangeAt(): ?\DateTimeImmutable
    {
        $value = $this->get('last_successful_change_at');
        return $value ? new \DateTimeImmutable($value) : null;
    }
    
    /**
     * Set last successful change timestamp
     */
    public function setLastSuccessfulChangeAt(): void
    {
        $this->set('last_successful_change_at', date('Y-m-d H:i:s'));
    }
    
    /**
     * Get entire state as array
     */
    public function toArray(): array
    {
        $rows = $this->getAll();
        $result = [];
        
        foreach ($rows as $row) {
            $result[$row['state_key']] = $row['state_value'];
        }
        
        return $result;
    }
}

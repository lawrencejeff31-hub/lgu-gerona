<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use App\Models\Document;
use App\Models\DocumentType;
use App\Models\Department;
use App\Models\User;

class CacheService
{
    // Cache durations in minutes
    const CACHE_DURATION_SHORT = 5;      // 5 minutes for frequently changing data
    const CACHE_DURATION_MEDIUM = 30;    // 30 minutes for moderately changing data
    const CACHE_DURATION_LONG = 1440;    // 24 hours for rarely changing data

    // Cache key prefixes
    const PREFIX_DOCUMENT = 'document:';
    const PREFIX_DOCUMENT_LIST = 'documents:';
    const PREFIX_DOCUMENT_STATS = 'document_stats:';
    const PREFIX_DASHBOARD_STATS = 'dashboard_stats:';
    const PREFIX_DEPARTMENT = 'department:';
    const PREFIX_USER = 'user:';
    const PREFIX_DOCUMENT_TYPES = 'document_types';

    /**
     * Cache a document by ID
     */
    public static function cacheDocument(Document $document): void
    {
        $key = self::PREFIX_DOCUMENT . $document->id;
        Cache::put($key, $document->load(['documentType', 'department', 'creator', 'assignedTo']), self::CACHE_DURATION_MEDIUM);
        
        Log::info('Document cached', ['document_id' => $document->id, 'cache_key' => $key]);
    }

    /**
     * Get cached document by ID
     */
    public static function getCachedDocument(int $documentId): ?Document
    {
        $key = self::PREFIX_DOCUMENT . $documentId;
        $document = Cache::get($key);
        
        if ($document) {
            Log::info('Document cache hit', ['document_id' => $documentId, 'cache_key' => $key]);
        } else {
            Log::info('Document cache miss', ['document_id' => $documentId, 'cache_key' => $key]);
        }
        
        return $document;
    }

    /**
     * Cache document list with filters
     */
    public static function cacheDocumentList(string $filterKey, $documents, int $duration = self::CACHE_DURATION_SHORT): void
    {
        $key = self::PREFIX_DOCUMENT_LIST . $filterKey;
        Cache::put($key, $documents, $duration);
        
        Log::info('Document list cached', ['filter_key' => $filterKey, 'cache_key' => $key, 'duration' => $duration]);
    }

    /**
     * Get cached document list
     */
    public static function getCachedDocumentList(string $filterKey)
    {
        $key = self::PREFIX_DOCUMENT_LIST . $filterKey;
        $documents = Cache::get($key);
        
        if ($documents) {
            Log::info('Document list cache hit', ['filter_key' => $filterKey, 'cache_key' => $key]);
        } else {
            Log::info('Document list cache miss', ['filter_key' => $filterKey, 'cache_key' => $key]);
        }
        
        return $documents;
    }

    /**
     * Cache document statistics
     */
    public static function cacheDocumentStats(array $stats): void
    {
        $key = self::PREFIX_DOCUMENT_STATS . 'all';
        Cache::put($key, $stats, self::CACHE_DURATION_MEDIUM);
        
        Log::info('Document stats cached', ['cache_key' => $key]);
    }

    /**
     * Get cached document statistics
     */
    public static function getCachedDocumentStats(): ?array
    {
        $key = self::PREFIX_DOCUMENT_STATS . 'all';
        $stats = Cache::get($key);
        
        if ($stats) {
            Log::info('Document stats cache hit', ['cache_key' => $key]);
        } else {
            Log::info('Document stats cache miss', ['cache_key' => $key]);
        }
        
        return $stats;
    }

    /**
     * Cache document types
     */
    public static function cacheDocumentTypes($documentTypes): void
    {
        Cache::put(self::PREFIX_DOCUMENT_TYPES, $documentTypes, self::CACHE_DURATION_LONG);
        
        Log::info('Document types cached', ['cache_key' => self::PREFIX_DOCUMENT_TYPES]);
    }

    /**
     * Get cached document types
     */
    public static function getCachedDocumentTypes()
    {
        $documentTypes = Cache::get(self::PREFIX_DOCUMENT_TYPES);
        
        if ($documentTypes) {
            Log::info('Document types cache hit', ['cache_key' => self::PREFIX_DOCUMENT_TYPES]);
        } else {
            Log::info('Document types cache miss', ['cache_key' => self::PREFIX_DOCUMENT_TYPES]);
        }
        
        return $documentTypes;
    }

    /**
     * Cache department data
     */
    public static function cacheDepartment(Department $department): void
    {
        $key = self::PREFIX_DEPARTMENT . $department->id;
        Cache::put($key, $department, self::CACHE_DURATION_LONG);
        
        Log::info('Department cached', ['department_id' => $department->id, 'cache_key' => $key]);
    }

    /**
     * Get cached department
     */
    public static function getCachedDepartment(int $departmentId): ?Department
    {
        $key = self::PREFIX_DEPARTMENT . $departmentId;
        $department = Cache::get($key);
        
        if ($department) {
            Log::info('Department cache hit', ['department_id' => $departmentId, 'cache_key' => $key]);
        } else {
            Log::info('Department cache miss', ['department_id' => $departmentId, 'cache_key' => $key]);
        }
        
        return $department;
    }

    /**
     * Cache user data
     */
    public static function cacheUser(User $user): void
    {
        $key = self::PREFIX_USER . $user->id;
        Cache::put($key, $user->load(['roles', 'permissions']), self::CACHE_DURATION_MEDIUM);
        
        Log::info('User cached', ['user_id' => $user->id, 'cache_key' => $key]);
    }

    /**
     * Get cached user
     */
    public static function getCachedUser(int $userId): ?User
    {
        $key = self::PREFIX_USER . $userId;
        $user = Cache::get($key);
        
        if ($user) {
            Log::info('User cache hit', ['user_id' => $userId, 'cache_key' => $key]);
        } else {
            Log::info('User cache miss', ['user_id' => $userId, 'cache_key' => $key]);
        }
        
        return $user;
    }

    /**
     * Invalidate document cache
     */
    public static function invalidateDocument(int $documentId): void
    {
        $key = self::PREFIX_DOCUMENT . $documentId;
        Cache::forget($key);
        
        // Also invalidate related document lists
        self::invalidateDocumentLists();
        
        Log::info('Document cache invalidated', ['document_id' => $documentId, 'cache_key' => $key]);
    }

    /**
     * Invalidate document lists cache
     */
    public static function invalidateDocumentLists(): void
    {
        // Since we can't reliably get all keys with a pattern across all cache drivers,
        // we'll use a more targeted approach by clearing known cache keys
        
        // Clear common document list cache keys
        $commonKeys = [
            self::PREFIX_DOCUMENT_LIST . 'all',
            self::PREFIX_DOCUMENT_LIST . 'pending',
            self::PREFIX_DOCUMENT_LIST . 'approved',
            self::PREFIX_DOCUMENT_LIST . 'rejected',
            self::PREFIX_DOCUMENT_LIST . 'recent',
        ];
        
        foreach ($commonKeys as $key) {
            Cache::forget($key);
        }
        
        // Also invalidate document stats
        Cache::forget(self::PREFIX_DOCUMENT_STATS . 'all');
        
        Log::info('Document lists cache invalidated', ['keys_cleared' => count($commonKeys)]);
    }

    /**
     * Cache dashboard statistics per user
     */
    public static function cacheDashboardStats(int $userId, array $stats, int $duration = self::CACHE_DURATION_SHORT): void
    {
        $key = self::PREFIX_DASHBOARD_STATS . $userId;
        Cache::put($key, $stats, $duration);
        
        Log::info('Dashboard stats cached', ['user_id' => $userId, 'cache_key' => $key, 'duration' => $duration]);
    }

    /**
     * Get cached dashboard statistics per user
     */
    public static function getCachedDashboardStats(int $userId): ?array
    {
        $key = self::PREFIX_DASHBOARD_STATS . $userId;
        $stats = Cache::get($key);
        
        if ($stats) {
            Log::info('Dashboard stats cache hit', ['user_id' => $userId, 'cache_key' => $key]);
        } else {
            Log::info('Dashboard stats cache miss', ['user_id' => $userId, 'cache_key' => $key]);
        }
        
        return $stats;
    }

    /**
     * Invalidate dashboard statistics per user
     */
    public static function invalidateDashboardStats(int $userId): void
    {
        $key = self::PREFIX_DASHBOARD_STATS . $userId;
        Cache::forget($key);
        Log::info('Dashboard stats cache invalidated', ['user_id' => $userId, 'cache_key' => $key]);
    }

    /**
     * Cache all departments
     */
    public static function cacheDepartments($departments): void
    {
        $key = self::PREFIX_DEPARTMENT . 'all';
        Cache::put($key, $departments, self::CACHE_DURATION_LONG);
        
        Log::info('All departments cached', ['cache_key' => $key]);
    }

    /**
     * Get all cached departments
     */
    public static function getDepartments()
    {
        $key = self::PREFIX_DEPARTMENT . 'all';
        $departments = Cache::get($key);
        
        if ($departments) {
            Log::info('Departments cache hit', ['cache_key' => $key]);
            return $departments;
        }
        
        Log::info('Departments cache miss', ['cache_key' => $key]);
        
        // If not in cache, get from database and cache it
        $departments = Department::where('is_active', true)->orderBy('name')->get();
        self::cacheDepartments($departments);
        
        return $departments;
    }

    /**
     * Invalidate all departments cache
     */
    public static function invalidateDepartments(): void
    {
        $key = self::PREFIX_DEPARTMENT . 'all';
        Cache::forget($key);
        
        Log::info('All departments cache invalidated', ['cache_key' => $key]);
    }

    /**
     * Invalidate department cache
     */
    public static function invalidateDepartment(int $departmentId): void
    {
        $key = self::PREFIX_DEPARTMENT . $departmentId;
        Cache::forget($key);
        
        // Also invalidate the departments list cache
        self::invalidateDepartments();
        
        Log::info('Department cache invalidated', ['department_id' => $departmentId, 'cache_key' => $key]);
    }

    /**
     * Invalidate user cache
     */
    public static function invalidateUser(int $userId): void
    {
        $key = self::PREFIX_USER . $userId;
        Cache::forget($key);
        
        Log::info('User cache invalidated', ['user_id' => $userId, 'cache_key' => $key]);
    }

    /**
     * Invalidate document types cache
     */
    public static function invalidateDocumentTypes(): void
    {
        Cache::forget(self::PREFIX_DOCUMENT_TYPES);
        
        Log::info('Document types cache invalidated', ['cache_key' => self::PREFIX_DOCUMENT_TYPES]);
    }

    /**
     * Clear all application cache
     */
    public static function clearAll(): void
    {
        Cache::flush();
        
        Log::info('All cache cleared');
    }

    /**
     * Generate cache key for document list with filters
     */
    public static function generateDocumentListKey(array $filters = []): string
    {
        ksort($filters); // Sort to ensure consistent key generation
        return md5(serialize($filters));
    }

    /**
     * Get cache statistics
     */
    public static function getCacheStats(): array
    {
        try {
            // Check if we're using Redis cache driver
            $cacheDriver = config('cache.default');
            
            if ($cacheDriver === 'redis') {
                $redis = Cache::getRedis();
                $info = $redis->info('memory');
                
                return [
                    'driver' => 'redis',
                    'memory_used' => $info['used_memory_human'] ?? 'N/A',
                    'memory_peak' => $info['used_memory_peak_human'] ?? 'N/A',
                    'keys_count' => $redis->dbsize(),
                    'cache_hits' => $info['keyspace_hits'] ?? 0,
                    'cache_misses' => $info['keyspace_misses'] ?? 0,
                    'hit_rate' => $info['keyspace_hits'] && $info['keyspace_misses'] 
                        ? round(($info['keyspace_hits'] / ($info['keyspace_hits'] + $info['keyspace_misses'])) * 100, 2) . '%'
                        : 'N/A'
                ];
            } else {
                // For non-Redis cache drivers, return basic information
                return [
                    'driver' => $cacheDriver,
                    'message' => 'Cache statistics are only available for Redis driver',
                    'status' => 'Cache is operational'
                ];
            }
        } catch (\Exception $e) {
            Log::error('Failed to get cache stats', ['error' => $e->getMessage()]);
            return ['error' => 'Unable to retrieve cache statistics'];
        }
    }
}
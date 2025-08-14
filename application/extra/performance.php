<?php

return [
    // 数据库查询优化配置
    'database' => [
        // 查询超时时间（秒）
        'query_timeout' => 300,
        // 最大连接数
        'max_connections' => 1000,
        // 查询缓存时间（秒）
        'query_cache_time' => 300,
    ],
    
    // 缓存配置
    'cache' => [
        // 统计缓存时间（秒）
        'stats_cache_time' => 300,
        // 总计数据缓存时间（秒）
        'total_cache_time' => 600,
        // 团队数据缓存时间（秒）
        'team_cache_time' => 300,
    ],
    
    // 分页配置
    'pagination' => [
        // 默认每页数量
        'default_limit' => 20,
        // 最大每页数量
        'max_limit' => 100,
    ],
    
    // 异步任务配置
    'async' => [
        // 任务超时时间（秒）
        'task_timeout' => 3600,
        // 任务状态检查间隔（秒）
        'check_interval' => 5,
    ],
    
    // 性能监控配置
    'monitor' => [
        // 慢查询阈值（秒）
        'slow_query_threshold' => 2,
        // 内存使用阈值（MB）
        'memory_threshold' => 512,
        // 是否启用性能监控
        'enabled' => true,
    ],
    
    // 统计查询优化
    'statistics' => [
        // 是否启用缓存
        'enable_cache' => true,
        // 是否启用异步查询
        'enable_async' => true,
        // 是否启用分页查询
        'enable_pagination' => true,
        // 最大查询时间范围（天）
        'max_date_range' => 30,
        // 批量查询大小
        'batch_size' => 1000,
    ],
]; 
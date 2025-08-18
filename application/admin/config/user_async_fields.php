<?php

/**
 * 用户异步加载字段配置文件
 * 用于管理哪些字段需要异步加载以及加载策略
 */

return [
    // 异步加载字段配置
    'async_fields' => [
        // 推荐关系字段
        'zhituinum' => [
            'title' => '直推人数',
            'description' => '直接推荐的下级用户数量',
            'cache_time' => 300, // 缓存5分钟
            'batch_size' => 50,   // 批量处理大小
            'priority' => 1       // 优先级（1最高）
        ],
        'allnum' => [
            'title' => '全部下级',
            'description' => '所有下级用户总数',
            'cache_time' => 300,
            'batch_size' => 50,
            'priority' => 1
        ],
        
        // 业务统计字段
        'allbuy' => [
            'title' => '累计消费',
            'description' => '用户累计消费金额',
            'cache_time' => 600,  // 缓存10分钟
            'batch_size' => 50,
            'priority' => 2
        ],
        'levename' => [
            'title' => '身份等级',
            'description' => '用户身份等级名称',
            'cache_time' => 1800, // 缓存30分钟
            'batch_size' => 100,
            'priority' => 3
        ],
        
        // 地理位置字段
        'ipname' => [
            'title' => '地理位置',
            'description' => 'IP地址对应的地理位置',
            'cache_time' => 86400, // 缓存24小时
            'batch_size' => 100,
            'priority' => 4
        ],
        
        // 用户组字段
        'group' => [
            'title' => '用户组',
            'description' => '用户所属用户组',
            'cache_time' => 3600,  // 缓存1小时
            'batch_size' => 100,
            'priority' => 5
        ]
    ],
    
    // 缓存配置
    'cache' => [
        'prefix' => 'user_details_',
        'default_time' => 300,
        'max_time' => 86400,
        'cleanup_interval' => 3600 // 清理间隔（秒）
    ],
    
    // 批量处理配置
    'batch' => [
        'default_size' => 50,
        'max_size' => 100,
        'delay_between_batches' => 100, // 批次间延迟（毫秒）
        'max_concurrent_batches' => 3   // 最大并发批次
    ],
    
    // 性能优化配置
    'performance' => [
        'enable_query_cache' => true,
        'enable_result_cache' => true,
        'max_cache_size' => 1000,      // 最大缓存条目数
        'cache_cleanup_threshold' => 800 // 清理阈值
    ],
    
    // 错误处理配置
    'error_handling' => [
        'max_retries' => 3,
        'retry_delay' => 1000,         // 重试延迟（毫秒）
        'log_errors' => true,
        'show_user_friendly_errors' => true
    ],
    
    // 监控配置
    'monitoring' => [
        'enable_performance_logging' => true,
        'log_slow_queries' => true,
        'slow_query_threshold' => 1000, // 慢查询阈值（毫秒）
        'enable_metrics' => true
    ]
]; 
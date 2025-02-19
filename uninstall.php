<?php
// If uninstall not called from WordPress, then exit
if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

// Delete all plugin options
delete_option('jlp_company_name');

// Delete custom post type posts and metadata
global $wpdb;
$post_type = 'job_listing';

// Get all job listing posts
$posts = $wpdb->get_results(
    $wpdb->prepare(
        "SELECT ID FROM {$wpdb->posts} WHERE post_type = %s",
        $post_type
    )
);

// Delete all post meta and posts
foreach ($posts as $post) {
    delete_post_meta_by_key($post->ID);
    wp_delete_post($post->ID, true);
}

// Remove scheduled cron jobs
wp_clear_scheduled_hook('jlp_daily_job_cleanup');
wp_clear_scheduled_hook('jlp_weekly_report');
// Add any other cron jobs that need to be cleared

// Drop custom database tables if they exist
$wpdb->query("DROP TABLE IF EXISTS {$wpdb->prefix}job_applications");
$wpdb->query("DROP TABLE IF EXISTS {$wpdb->prefix}job_statistics");
// Add any other custom tables that need to be dropped

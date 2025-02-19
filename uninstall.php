<?php
// If uninstall not called from WordPress, then exit
if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

// Delete all plugin options
delete_option('job_listing_settings');

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
if ($posts) {
    foreach ($posts as $post) {
        delete_post_meta_by_key($post->ID);
        wp_delete_post($post->ID, true);
    }
}

// Remove scheduled cron jobs
wp_clear_scheduled_hook('jlp_fetch_jobs_cron');

// Drop custom database tables if they exist
$table_name = $wpdb->prefix . 'job_listings';
$wpdb->query("DROP TABLE IF EXISTS {$table_name}");

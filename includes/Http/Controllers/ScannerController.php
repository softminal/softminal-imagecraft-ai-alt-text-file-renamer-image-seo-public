<?php

namespace WpAiAltText\Includes\Http\Controllers;

if (!defined('ABSPATH')) {
    exit;
}

class ScannerController extends Controller
{
    /**
     * Scan media library
     */
    public function scan($request)
    {
        $params = $request->get_params();
        $filter = sanitize_text_field($params['filter'] ?? 'all');
        $page = (int) ($params['page'] ?? 1);
        $perPage = (int) ($params['per_page'] ?? 50);
        
        $args = [
            'post_type' => 'attachment',
            'post_mime_type' => 'image',
            'post_status' => 'inherit',
            'posts_per_page' => $perPage,
            'paged' => $page,
            'orderby' => 'date',
            'order' => 'DESC'
        ];
        
        if ($filter === 'missing') {
            $args['meta_query'] = [
                'relation' => 'OR',
                [
                    'key' => '_wp_attachment_image_alt',
                    'compare' => 'NOT EXISTS'
                ],
                [
                    'key' => '_wp_attachment_image_alt',
                    'value' => '',
                    'compare' => '='
                ]
            ];
        } elseif ($filter === 'has_alt') {
            $args['meta_query'] = [
                [
                    'key' => '_wp_attachment_image_alt',
                    'compare' => 'EXISTS'
                ],
                [
                    'key' => '_wp_attachment_image_alt',
                    'value' => '',
                    'compare' => '!='
                ]
            ];
        }
        
        $query = new \WP_Query($args);
        
        $images = [];
        foreach ($query->posts as $post) {
            $alt = get_post_meta($post->ID, '_wp_attachment_image_alt', true);
            $thumbnail = wp_get_attachment_image_src($post->ID, 'thumbnail');
            
            $images[] = [
                'id' => $post->ID,
                'title' => $post->post_title,
                'filename' => basename(get_attached_file($post->ID)),
                'url' => wp_get_attachment_url($post->ID),
                'thumbnail' => $thumbnail ? $thumbnail[0] : null,
                'alt_text' => $alt ?: '',
                'has_alt' => !empty($alt),
                'date' => $post->post_date
            ];
        }
        
        // Get total counts
        $totalQuery = new \WP_Query([
            'post_type' => 'attachment',
            'post_mime_type' => 'image',
            'post_status' => 'inherit',
            'posts_per_page' => -1,
            'fields' => 'ids'
        ]);
        
        $missingQuery = new \WP_Query([
            'post_type' => 'attachment',
            'post_mime_type' => 'image',
            'post_status' => 'inherit',
            'posts_per_page' => -1,
            'fields' => 'ids',
            'meta_query' => [
                'relation' => 'OR',
                [
                    'key' => '_wp_attachment_image_alt',
                    'compare' => 'NOT EXISTS'
                ],
                [
                    'key' => '_wp_attachment_image_alt',
                    'value' => '',
                    'compare' => '='
                ]
            ]
        ]);
        
        $totalImages = $totalQuery->found_posts;
        $missingAlt = $missingQuery->found_posts;
        $hasAlt = $totalImages - $missingAlt;
        
        return $this->response([
            'success' => true,
            'images' => $images,
            'pagination' => [
                'current_page' => $page,
                'per_page' => $perPage,
                'total' => $query->found_posts,
                'total_pages' => $query->max_num_pages
            ],
            'stats' => [
                'total_images' => $totalImages,
                'missing_alt' => $missingAlt,
                'has_alt' => $hasAlt,
                'missing_percentage' => $totalImages > 0 ? round(($missingAlt / $totalImages) * 100, 2) : 0
            ]
        ]);
    }
}

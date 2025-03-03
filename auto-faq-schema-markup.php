<?php
/**
 * Plugin Name: Auto FAQ Schema Markup
 * Description: Automatically adds FAQ schema markup to pages that have FAQ posts.
 * Version: 1.0
 * Author: Keshav Bhutra
 */

// Global array to collect FAQ post IDs from all queries.
global $collected_faq_posts;
$collected_faq_posts = array();

/**
 * Collect FAQ posts from any query.
 *
 * This function is hooked to 'the_posts' so that every time a query returns posts,
 * it checks for FAQ posts and stores their IDs in a global array.
 *
 * @param array $posts Array of WP_Post objects.
 * @return array Unmodified posts array.
 */
function collect_faq_posts_from_query( $posts ) {
    global $collected_faq_posts;
    
    if ( ! is_array( $posts ) ) {
        return $posts;
    }
    
    foreach ( $posts as $post ) {
        if ( isset( $post->post_type ) && 'faq' === $post->post_type ) {
            if ( ! isset( $collected_faq_posts[ $post->ID ] ) ) {
                $collected_faq_posts[ $post->ID ] = $post->ID;
            }
        }
    }
    
    return $posts;
}
add_filter( 'the_posts', 'collect_faq_posts_from_query', 10, 1 );

/**
 * Outputs the FAQ schema markup in the footer.
 *
 * It loops through all collected FAQ posts, retrieves the 'question' and 'answer'
 * ACF fields, then builds and outputs the JSON-LD.
 */
function output_faq_schema_markup() {
    global $collected_faq_posts;
    
    if ( empty( $collected_faq_posts ) ) {
        return;
    }
    
    // Build the base FAQ schema array.
    $faq_schema = array(
        '@context'   => 'https://schema.org',
        '@type'      => 'FAQPage',
        'mainEntity' => array()
    );
    
    foreach ( $collected_faq_posts as $faq_id ) {
        // Retrieve ACF fields for the FAQ post.
        $question = get_field( 'question', $faq_id );
        $answer   = get_field( 'answer', $faq_id );
        
        if ( empty( $question ) || empty( $answer ) ) {
            continue;
        }
        
        $faq_schema['mainEntity'][] = array(
            '@type'          => 'Question',
            'name'           => wp_strip_all_tags( $question ),
            'acceptedAnswer' => array(
                '@type' => 'Answer',
                'text'  => wp_strip_all_tags( $answer )
            )
        );
    }
    
    // If no valid FAQ entries were added, do not output schema.
    if ( empty( $faq_schema['mainEntity'] ) ) {
        return;
    }
    
    // JSON encode the schema markup.
    $json_ld = json_encode( $faq_schema, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );
    if ( false === $json_ld ) {
        return;
    }
    
    // Output the JSON-LD in the footer.
    echo "\n<!-- FAQ Schema Markup Start -->\n";
    echo '<script type="application/ld+json">' . $json_ld . '</script>';
    echo "\n<!-- FAQ Schema Markup End -->\n";
}
add_action( 'wp_footer', 'output_faq_schema_markup', 100 );

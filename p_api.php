<?php

require_once('/path/to/wp-load.php');
require_once( '/path/to/wp-includes/post.php' );
require_once('/path/to/wp-admin/includes/image.php' );


// Database Connection
$db_host = 'yourhost';
$db_name = 'your_dbname';
$db_user = 'your_dbuser';
$db_pass = 'your_dbpass';

$connection = new mysqli( $db_host, $db_user, $db_pass, $db_name );

if ( $connection->connect_error ) {
    die( json_encode( array( 'status' => 'error', 'message' => 'Database connection failed' ) ) );
}

// Check if all required parameters are present
if ( !isset( $_POST['title'], $_POST['content'], $_POST['categories'], $_POST['author'] ) ) {
    die( json_encode( array( 'status' => 'error', 'message' => 'Missing required parameters' ) ) );
}

// Extract POST data
$title = sanitize_text_field( $_POST['title'] );
$content = wp_kses_post( $_POST['content'] );
$categories = explode( ',', $_POST['categories'] );
$tags = isset( $_POST['tags'] ) ? explode( ',', $_POST['tags'] ) : array();
$author_id = intval( $_POST['author'] );
$featured_image_url = isset( $_POST['featured_image'] ) ? esc_url( $_POST['featured_image'] ) : '';
$visibility = isset( $_POST['visibility'] ) ? $_POST['visibility'] : 'public';


new_post = array(
    'post_title'    => $title,
    'post_content'  => $content,
    'post_status'   => 'publish',
    'post_author'   => $author_id,
    'post_category' => $categories,
    'tags_input'    => $tags,
    'comment_status' => 'closed',
    'ping_status'    => 'open', // Allow pingbacks
    'post_type'      => 'post',
    'post_visibility' => $visibility,
);

// Insert the post into the database
$post_id = wp_insert_post( $new_post );

// Set featured image if provided
if ( !empty( $featured_image_url ) ) {
    $image_id = custom_post_creator_api_upload_featured_image( $featured_image_url, $post_id, $title );
    if ( !is_wp_error( $image_id ) ) {
        set_post_thumbnail( $post_id, $image_id );
    }
}

// Check if post insertion was successful
if ( $post_id ) {
    echo json_encode( array( 'status' => 'success', 'message' => 'Post created successfully' ) );
} else {
    echo json_encode( array( 'status' => 'error', 'message' => 'Failed to create post' ) );
}

// Close database connection
$connection->close();

// Upload Featured Image
function custom_post_creator_api_upload_featured_image( $image_url, $post_id, $post_title ) {
    $upload_dir = wp_upload_dir();
    $image_data = file_get_contents( $image_url );
    $filename = basename( $image_url );

    if ( wp_mkdir_p( $upload_dir['path'] ) ) {
        $file = $upload_dir['path'] . '/' . $filename;
    } else {
        $file = $upload_dir['basedir'] . '/' . $filename;
    }

    file_put_contents( $file, $image_data );

    $wp_filetype = wp_check_filetype( $filename, null );

    $attachment = array(
        'post_mime_type' => $wp_filetype['type'],
        'post_title'     => sanitize_file_name( $post_title ),
        'post_content'   => '',
        'post_status'    => 'inherit'
    );

    $attach_id = wp_insert_attachment( $attachment, $file, $post_id );
    $attach_data = wp_generate_attachment_metadata( $attach_id, $file );
    wp_update_attachment_metadata( $attach_id, $attach_data );

    return $attach_id;
}
?>

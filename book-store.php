<?php
/**
 * Plugin Name: Book Store Plugin
 * Description: A plugin to manage a book store.
 * Version: 1.0
 * Author: amirreza soleimani
 * Text Domain: book-store
 * Domain Path: /languages
 */

defined('ABSPATH') or die('No script kiddies please!');

class BookStorePlugin {

    public function __construct() {
        register_activation_hook(__FILE__, [$this, 'create_books_info_table']);
        add_action('init', [$this, 'create_book_post_type']);
        add_action('add_meta_boxes', [$this, 'add_isbn_meta_box']);
        add_action('save_post', [$this, 'save_isbn_meta_box']);
        add_action('init', [$this, 'register_publisher_taxonomy']);
        add_action('init', [$this, 'register_author_taxonomy']);
        add_action('plugins_loaded', [$this, 'load_textdomain']);
    }

    public function load_textdomain() {
        load_plugin_textdomain('book-store', false, basename(dirname(__FILE__)) . '/languages');

    }

    public function create_books_info_table() {
        global $wpdb;
        $table_name = $wpdb->prefix . 'books_info';
        $charset_collate = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE $table_name (
            ID mediumint(9) NOT NULL AUTO_INCREMENT,
            post_id bigint(20) NOT NULL,
            isbn varchar(20) NOT NULL,
            PRIMARY KEY  (ID)
        ) $charset_collate;";

        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
    }

    public function create_book_post_type() {
        register_post_type('book', array(
            'labels' => array(
                'name' => __('Books', 'book-store'),
                'singular_name' => __('Book', 'book-store')
            ),
            'public' => true,
            'has_archive' => true,
            'supports' => array('title', 'editor', 'thumbnail'),
            'taxonomies' => array('publisher', 'authors'), // Ensure these taxonomies are registered
            'show_in_rest' => true,
            'menu_icon' => 'dashicons-book-alt', // Optional: Adds a specific icon
        ));
    }

    public function register_publisher_taxonomy() {
        $labels = array(
            'name' => _x('Publishers', 'taxonomy general name', 'book-store'),
            'singular_name' => _x('Publisher', 'taxonomy singular name', 'book-store'),
            'search_items' => __('Search Publishers', 'book-store'),
            'all_items' => __('All Publishers', 'book-store'),
            'parent_item' => __('Parent Publisher', 'book-store'),
            'parent_item_colon' => __('Parent Publisher:', 'book-store'),
            'edit_item' => __('Edit Publisher', 'book-store'),
            'update_item' => __('Update Publisher', 'book-store'),
            'add_new_item' => __('Add New Publisher', 'book-store'),
            'new_item_name' => __('New Publisher Name', 'book-store'),
            'menu_name' => __('Publishers', 'book-store'),
        );

        $args = array(
            'hierarchical' => true, // Set to false for non-hierarchical (like tags)
            'labels' => $labels,
            'show_ui' => true,
            'show_in_menu' => true, // Ensure it shows up in the admin menu
            'show_in_nav_menus' => true,
            'show_in_rest' => true, // Enables Gutenberg support
            'query_var' => true,
            'rewrite' => array('slug' => 'publisher'),
        );

        register_taxonomy('publisher', array('book'), $args);
    }

    public function register_author_taxonomy() {
        $labels = array(
            'name' => _x('Authors', 'taxonomy general name', 'book-store'),
            'singular_name' => _x('Author', 'taxonomy singular name', 'book-store'),
            'search_items' => __('Search Authors', 'book-store'),
            'all_items' => __('All Authors', 'book-store'),
            'parent_item' => __('Parent Author', 'book-store'),
            'parent_item_colon' => __('Parent Author:', 'book-store'),
            'edit_item' => __('Edit Author', 'book-store'),
            'update_item' => __('Update Author', 'book-store'),
            'add_new_item' => __('Add New Author', 'book-store'),
            'new_item_name' => __('New Author Name', 'book-store'),
            'menu_name' => __('Authors', 'book-store'),
        );

        $args = array(
            'hierarchical' => false, // Set false if you want a tag-like taxonomy (flat)
            'labels' => $labels,
            'show_ui' => true,
            'show_in_menu' => true,
            'show_in_nav_menus' => true,
            'show_in_rest' => true, // Enables Gutenberg editor support
            'query_var' => true,
            'rewrite' => array('slug' => 'author'),
        );

        register_taxonomy('author', array('book'), $args);
    }

    public function add_isbn_meta_box() {
        add_meta_box(
            'isbn_meta_box',
            __('ISBN Number', 'book-store'),
            [$this, 'render_isbn_meta_box'],
            'book',
            'side',
            'default'
        );
    }

    public function render_isbn_meta_box($post) {
        wp_nonce_field('save_isbn_meta_box_data', 'isbn_meta_box_nonce');
        $value = get_post_meta($post->ID, '_isbn', true);
        echo '<label for="isbn_field">' . __('ISBN Number', 'book-store') . '</label>';
        echo '<input type="text" id="isbn_field" name="isbn_field" value="' . esc_attr($value) . '" size="25" />';
    }

    public function save_isbn_meta_box($post_id) {
        if (!isset($_POST['isbn_meta_box_nonce'])) {
            return;
        }

        if (!wp_verify_nonce($_POST['isbn_meta_box_nonce'], 'save_isbn_meta_box_data')) {
            return;
        }

        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }

        if (!current_user_can('edit_post', $post_id)) {
            return;
        }

        if (!isset($_POST['isbn_field'])) {
            return;
        }

        $isbn = sanitize_text_field($_POST['isbn_field']);
        update_post_meta($post_id, '_isbn', $isbn);

        global $wpdb;
        $table_name = $wpdb->prefix . 'books_info';
        $wpdb->replace($table_name, array('post_id' => $post_id, 'isbn' => $isbn));
    }
}

if (!class_exists('WP_List_Table')) {
    require_once(ABSPATH . 'wp-admin/includes/class-wp-list-table.php');
}

class Books_Info_List_Table extends WP_List_Table {

    public function __construct() {
        parent::__construct([
            'singular' => __('Book', 'book-store'),
            'plural'   => __('Books', 'book-store'),
            'ajax'     => false
        ]);
    }

    public function get_columns() {
        $columns = array(
            'cb'      => '<input type="checkbox" />',
            'post_id' => __('Post ID', 'book-store'),
            'isbn'    => __('ISBN', 'book-store')
        );
        return $columns;
    }

    public function column_default($item, $column_name) {
        switch ($column_name) {
            case 'post_id':
            case 'isbn':
                return esc_html($item[$column_name]);
            default:
                return print_r($item, true); // Show the whole array for troubleshooting purposes.
        }
    }

    public function prepare_items() {
        $columns = $this->get_columns();
        $hidden = array();
        $sortable = array();
        $this->_column_headers = array($columns, $hidden, $sortable);

        global $wpdb;
        $table_name = $wpdb->prefix . 'books_info';
        $query = "SELECT * FROM $table_name";
        $data = $wpdb->get_results($query, ARRAY_A);

        $perPage = 5;
        $currentPage = $this->get_pagenum();
        $totalItems = count($data);

        $this->set_pagination_args([
            'total_items' => $totalItems,
            'per_page'    => $perPage
        ]);

        $data = array_slice($data, (($currentPage - 1) * $perPage), $perPage);
        $this->items = $data;
    }

    public function column_cb($item) {
        return sprintf('<input type="checkbox" name="book[]" value="%s" />', esc_attr($item['ID']));
    }
}

function books_info_menu() {
    add_menu_page(
        __('Books Info', 'book-store'),
        __('Books Info', 'book-store'),
        'manage_options',
        'books-info',
        'books_info_page_display'
    );
}

add_action('admin_menu', 'books_info_menu');

function books_info_page_display() {
    $booksListTable = new Books_Info_List_Table();
    $booksListTable->prepare_items();
    ?>
    <div class="wrap">
        <h2><?php echo esc_html(get_admin_page_title()); ?></h2>
        <form method="post">
            <?php
            $booksListTable->display();
            ?>
        </form>
    </div>
    <?php
}

new BookStorePlugin();

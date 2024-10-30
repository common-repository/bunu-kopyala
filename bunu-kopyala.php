<?php

/*
Plugin Name: Bunu Kopyala
Description: It is a plugin designed to create duplicates of pages or posts. You can quickly install it and freely duplicate as many posts and pages as you want.
Version: 1.6
Author: B4RIS
License: GPLv2 or later
*/


class BunuKopyala {

  function __construct() {
    add_action( 'admin_action_duplicate_post_as_draft', array( &$this, 'duplicate_post_as_draft' ) );
    add_filter( 'post_row_actions', array( &$this, 'duplicate_post_link' ), 10, 2 );
    add_filter( 'page_row_actions', array( &$this, 'duplicate_post_link' ), 10, 2 );
  }

  function duplicate_post_as_draft() {
    global $wpdb;
    if (! ( isset( $_GET['post'] ) || isset( $_POST['post'] )  || ( isset( $_REQUEST['action'] ) && 'duplicate_post_as_draft' == $_REQUEST['action'] ) ) ) {
      wp_die('Hiçbir gösterilecek içerik bulunamadı');
    }

    /*
     * orijinal gönderi idsini al
     */
    if (isset($_GET['post'])) {
    $post_id = sanitize_text_field($_GET['post']);
	} elseif (isset($_POST['post'])) {
    $post_id = sanitize_text_field($_POST['post']);
}
    /*
     * daha sonra tüm orijinal gönderi verileri al
     */
    $post = get_post( $post_id );

    /*
     *Eğer mevcut kullanıcının yeni yazı yazarı olmamasını istemiyorsanız,
	*sonraki birkaç satırı şu şekle değiştirin: $new_post_author = $post->post_author;
     */
    $current_user = wp_get_current_user();
    $new_post_author = $current_user->ID;

    /*
     * gönderi verileri varsa, gönderi kopyasını oluştur
     */
    if (isset( $post ) && $post != null) {

/*
   * yeni gönderi arrayi
   */
  $args = array(
    'comment_status' => $post->comment_status,
    'ping_status'    => $post->ping_status,
    'post_author'    => $new_post_author,
    'post_content'   => $post->post_content,
	'post_excerpt' => $post->post_excerpt,
	'post_name' => $post->post_name,
	'post_parent' => $post->post_parent,
	'post_password' => $post->post_password,
	'post_status' => 'draft',
	'post_title' => $post->post_title . ' - Copy',
	'post_type' => $post->post_type,
	'to_ping' => $post->to_ping,
	'menu_order' => $post->menu_order
);

/*

gönderiyi wp_insert_post() işleviyle ekleyin
*/
$new_post_id = wp_insert_post( $args );
/*

mevcut tüm gönderi terimlerini al ve bunları yeni gönderi taslağına ayarla
*/
$taxonomies = get_object_taxonomies($post->post_type); // Post türü için vergi adları dizisi döndürür, örnek dizi: ("category", "post_tag");
foreach ($taxonomies as $taxonomy) {
$post_terms = wp_get_object_terms($post_id, $taxonomy, array('fields' => 'slugs'));
wp_set_object_terms($new_post_id, $post_terms, $taxonomy, false);
}
/*

tüm post metalarını yalnızca iki SQL sorgusunda çoğalt
*/

$post_meta_infos = $wpdb->get_results( $wpdb->prepare( "SELECT meta_key, meta_value FROM $wpdb->postmeta WHERE post_id=%d", $post_id ) );
if (count($post_meta_infos)!=0) {
    $sql_query = "";
    $sql_query_sel = array();
    foreach ($post_meta_infos as $meta_info) {
        $meta_key = $meta_info->meta_key;
        if( $meta_key == '_wp_old_slug' ) continue;
        $meta_value = $wpdb->prepare( "%s", $meta_info->meta_value);
        $sql_query_sel[]= $wpdb->prepare("SELECT %d, %s, %s", $new_post_id, $meta_key, $meta_value);
    }
    $sql_query = "INSERT INTO $wpdb->postmeta (post_id, meta_key, meta_value) " . implode(" UNION ALL ", $sql_query_sel);
    $wpdb->query($sql_query);
}

/*

son olarak, yeni taslak için yazı düzenleme ekranına yönlendir
*/
wp_redirect( admin_url( 'edit.php?post_type=' . $post->post_type ) );
exit;
} else {
wp_die('Post copy failed, could not find original post: ' . $post_id);
}
}
/*

post_row_actions için yinelenen bağlantıyı eylem listesine ekle
*/
function duplicate_post_link( $actions, $post ) {
if (current_user_can('edit_posts')) {
$actions['duplicate'] = '<a href="admin.php?action=duplicate_post_as_draft&amp;post=' . $post->ID . '" title="Bu içeriğin bir kopyasını oluştur" rel="permalink">Bunu Kopyala</a>';
}
return $actions;
}

}

new BunuKopyala();

?>
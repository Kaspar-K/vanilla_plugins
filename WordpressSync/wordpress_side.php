<?php

class WordpressSync {

    function __construct() {
        
    }

    static function sync($post_id, $post, $update) {
        if($post->vanilla_synced)
        {
            return;
        }
        $vf_options = get_option("vf-options", false);
        if (!$vf_options) {
            return;
        }
        $url = $vf_options['url'];
        $categoryid = $vf_options['embed-categoryid'] ? $vf_options['embed-categoryid'] : 0;

        if ($vf_options['embed-matchcategories']) {
            // Send the post's category ID instead of the default.
            $categories = get_the_category();
            if (!empty($categories)) {
                $category = array_shift($categories);
                if (isset($category->slug)) {
                    $categoryid = $category->slug;
                }
            }
        }
        if ($url[strlen($url) - 1] !== '/') {
            $url.='/';
        }
        $url.="plugin/wordpresssync/" . $post_id . '/' . $categoryid;
        if(json_decode(file_get_contents($url))->status==WPSYNC_DISCUSSION_CREATED)
        {
            update_meta($post_id, "vanilla_synced", true);
        }
    }

}

add_action('save_post', array('WordpressSync', 'sync'), 10, 3);

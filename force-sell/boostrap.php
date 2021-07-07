<?php
/**
 * Plugin Name: Force Sell for WooCommerce
 * Plugin URI: demo.local
 * Description: Cooler bag
 * Version: 1.0
 * Author: ductrung081@gmail.com
 * Author URI: https://github.com/ductrung081
 * License: GPLv2
 */
try{
    $GLOBALS['forceSellCore'] = new ForceSellCore();
}catch(Exception $e){
    // print_r($e);
    // die;
}


class ForceSellCore
{
    private $availableProducts = [];
    private $categories = [];
    public function __construct()
    {

        session_start();
        /**
         * save setting
         */
        add_action('admin_post_force_sell_save_options', [$this, 'hookSaveSetting']);

        /**
         * add menu
         */
        add_action('admin_menu', [$this, 'hookCreateMenu']);
        /**
         * register setting
         */
        add_action('admin_init', [$this, 'hookRegisterSetting']);//'register_madara_clone_plugin_settings');

        /**
         * add to cart hook
         */
        add_action('woocommerce_add_to_cart', [$this, 'woocommerce_add_to_cart'], 10, 3);

        add_filter( 'woocommerce_add_to_cart_fragments', [$this, 'filter_woocommerce_add_to_cart_fragments'], 10, 2 ); 

        /**
         * add trigger callback
         */
        add_action( 'wp_footer', [$this, 'woocommerce_popup_alert'] );

        add_filter( 'woocommerce_cart_item_remove_link', [$this, 'filter_woocommerce_cart_item_remove_link'], 10, 2); 
                 
        // add the action 
        add_action( 'woocommerce_cart_item_removed', [$this, 'action_woocommerce_cart_item_removed'], 10, 2 ); 
    }

    function action_woocommerce_cart_item_removed( $cart_item_key, $instance ) { 
        $prForceSellId = $_SESSION['foce-sell-id'] ?? null;
        if($prForceSellId == null){
            return;
        }

        $cartItems = WC()->cart->get_cart();
        $categories = $this->getCategoriesWillForce();
        $catInForceSell = false;
        foreach($cartItems as $k=>$item){
            $_product = $item['data'];

            if($this->validForceSell($_product->id, $categories)){
                $catInForceSell = true;
                break;
            }
        }

        if($catInForceSell === false){
            $_SESSION['foce-sell-id'] = null;
        }

    }

     // define the woocommerce_cart_item_remove_link callback 
    function filter_woocommerce_cart_item_remove_link( $sprintf, $cart_item_key ) { 
        
        $prForceSellId = $_SESSION['foce-sell-id'] ?? null;

        if($prForceSellId !== $cart_item_key){
            return $sprintf; 
        }

        $cartItems = WC()->cart->get_cart();
        
        if(count( $cartItems ) > 1 && $prForceSellId === $cart_item_key){
            return '';
        }
        
        // make filter magic happen here... 
        return $sprintf; 
    }

    // define the woocommerce_add_to_cart_fragments callback 
    function filter_woocommerce_add_to_cart_fragments( $array ) { 
        if($_SESSION['foce-sell-trigger'] == 1){
            $_SESSION['foce-sell-trigger'] = null;
            $array['foce_sell_message'] = get_option('force-sell-product-message');
            //do_shortcode( get_option('force-sell-product-message') );
        }
        // make filter magic happen here... 
        return $array; 
    }

    function hookRegisterSetting(){
        register_setting('force-sell-settings-group', 'force-sell-products', ['type' => 'array']);
        register_setting('force-sell-settings-group', 'force-sell-product-id');
        register_setting('force-sell-settings-group', 'force-sell-product-message');
        register_setting('force-sell-settings-group', 'force-sell-product-categories');
    }

    public function hookCreateMenu(){
        add_menu_page('Force Sell', 'Force Sell', 'administrator', 'force-sell', [$this, 'viewAdmin']);
    }

    public function hookSaveSetting(){
        $product_id = $_POST['product_id_force'] ?? null;
        $message = $_POST['message'] ?? '';
        //$products = $_POST['products'] ?? [];
        $categories = $_POST['categories'] ?? [];
        if(!is_array($categories)){
            $categories = [];
        }
        $message = str_replace('\"', '"', $message);
        
        update_option('force-sell-product-id', $product_id);
        update_option('force-sell-product-message', $message);
        //update_option('force-sell-product-products', $products);
        update_option('force-sell-product-categories', $categories);

        wp_redirect(admin_url('admin.php?page=force-sell'));
    }

    public function woocommerce_add_to_cart(){
        //global $woocommerce;
        $product_id = $_POST['assessories'];
        $product_id = $_POST['product_id'];
        //$quantity = $_POST['product_id'] ?? 1;
        $categories = $this->getCategoriesWillForce();
        $foceSellProductId = get_option('force-sell-product-id');
        
        if(!is_numeric($foceSellProductId) || $foceSellProductId <= 0){
            return;
        }
        
        $itemsInCart = WC()->cart->get_cart();
        if ( sizeof( $itemsInCart ) <= 0 ) {

            $catInForceSell = $this->validForceSell($product_id, $categories);

            if($foceSellProductId =! $product_id && $catInForceSell){
                $this->autoAddForceSellProduct($foceSellProductId);
                return;
            }

        }else{
            $found = 1;
            $catInForceSell = false;
            
            foreach ( $itemsInCart as $cart_item_key => $values ) {
                $_product = $values['data'];

                if($this->validForceSell($_product->id, $categories)){
                    $catInForceSell = true;
                }
                
                if ( $_product->id == $foceSellProductId ){
                    $found = false;
                }
                
            }
            
            if ( $found === 1 && $catInForceSell == true){
                $this->autoAddForceSellProduct($foceSellProductId);
            }
            return;
        }
    }

    private function autoAddForceSellProduct($foceSellProductId){
        $_SESSION['foce-sell-trigger'] = 1;
        $_SESSION['foce-sell-id'] = WC()->cart->add_to_cart( $foceSellProductId );
    }

    private function validForceSell($produtId, $cats){
        $terms = wc_get_product_term_ids( $produtId, 'product_cat' );
        $isForceSell = array_intersect($terms, $cats);
        if(is_array($isForceSell) && count($isForceSell) > 0){
            return true;
        }
        return false;
    }

    private function getAvailableProducts(){
        $params = ['posts_per_page'=>100000, 'post_type'=>'product', 'post_status' => 'publish'];
        $this->availableProducts = get_posts($params);
    }

    private function setCategories(){
        $args = array(
            'taxonomy'     => 'product_cat',
            'hide_empty'   => true
        );
        $this->categories = get_categories( $args );
    }

    public function viewAdmin(){
        $this->getAvailableProducts();
        $this->setCategories();
        $init_product_id = get_option('force-sell-product-id');
        $message = get_option('force-sell-product-message');
        $products = [];//$this->getProductsWillForce();
        $selectedCategories = $this->getCategoriesWillForce();
        ?>  
            
            <style>
                .select-thumb{width: 26px; display: inline;}
            </style>
            <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
            <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
            <script type="text/javascript">
                function formatState(state){
                    if (!state.id) { return state.text; }
                    let imgUrl = jQuery(state.element).data('img');
                    if(imgUrl){
                        return jQuery(
                            `<span><img class="select-thumb" src="${jQuery(state.element).data('img')}" /> ` + state.text + '</span>'
                        );
                    }else{
                        return jQuery(
                            `<span>` + state.text + '</span>'
                        );
                    }
                }
                jQuery(document).ready(function() {
                    jQuery('.select2').select2({placeholder: "Product", templateSelection: formatState, templateResult: formatState,});
                });
            </script>

            <div class="wrap">

                <form method="post" action="/wp-admin/admin-post.php">
                    <input type="hidden" name="link" value="force_sell_save_options">
                    <input type="hidden" name="action" value="force_sell_save_options">

                    <div class="form-control">
                        <div>
                            <label>
                                Popup Id:
                            </label>
                        </div>
                        <textarea name="message"><?php echo $message;?></textarea>
                    </div>

                    <div class="form-control">
                        <div>
                            <label>
                                Force sell:
                            </label>
                        </div>
                        <select name="product_id_force" class="select2" >
                        <?php foreach($this->availableProducts as $p){?>
                            <?php $thumb = get_the_post_thumbnail_url($p, 'thumbnail');?>
                            <option data-img="<?=$thumb?>" <?= ($init_product_id == $p->ID) ? 'selected' : '' ?> value="<?=$p->ID?>"><?=$p->post_title?></option>
                        <?php }?>
                        </select>
                    </div>

                    <div class="form-control">
                        <div>
                            <label>
                                Categories:
                            </label>
                        </div>
                        <select name="categories[]" class="select2" multiple="multiple" style="width: 100%">
                        <?php foreach($this->categories as $c){
                            $thumbnail_id = get_term_meta( $c->term_id, 'thumbnail_id', true );
                            $thumb = wp_get_attachment_url( $thumbnail_id );
                            ?>
                            <option data-img="<?=$thumb?>" <?= (in_array($c->term_id, $selectedCategories)) ? 'selected' : '' ?> value="<?=$c->term_id?>"><?=$c->name?></option>
                        <?php }?>
                        </select>
                    </div>

                    <!-- <div class="form-control">
                        <div>
                            <label>
                                With:
                            </label>
                        </div>
                        <select name="products[]" class="select2" multiple="multiple" style="width: 100%">
                        <?php foreach($this->availableProducts as $p){?>
                            <?php $thumb = get_the_post_thumbnail_url($p, 'thumbnail');?>
                            <option data-img="<?=$thumb?>" <?= (in_array($p->ID, $products)) ? 'selected' : '' ?> value="<?=$p->ID?>"><?=$p->post_title?></option>
                        <?php }?>
                        </select>
                    </div> -->

                    <?php submit_button('Save');?>
                </form>
            </div>

            <style>
                .wrap{padding: 10px}
                .form-control{padding: 10px 0px}
                .form-control select{width: 100% !important;}
            </style>
            
        <?php
    }

    function woocommerce_popup_alert(){
        echo "
        <script>
        jQuery(document).ready(function($){
                $('body').on( 'added_to_cart', function(params, params2, params3){
                    if(params2.hasOwnProperty('foce_sell_message')){
                        if(parseInt(params2.foce_sell_message) > 0 && typeof elementorProFrontend != 'undefined'){
                            elementorProFrontend.modules.popup.showPopup( { id: parseInt(params2.foce_sell_message) } );
                        }else{
                            alert(params2.foce_sell_message);
                        }
                        //jQuery('body').append(params2.foce_sell_message);
                    }
                });
            });
        </script>
        ";
    }

    // private function getProductsWillForce(){
    //     $products = get_option('force-sell-product-products');
    //     if(!is_array($products)){
    //         return [];
    //     }
    //     return $products;
    // }

    private function getCategoriesWillForce(){
        $cats = get_option('force-sell-product-categories');
        if(!is_array($cats)){
            return [];
        }
        return $cats;
    }
    
}

//elementorProFrontend.modules.popup.showPopup( { id: 1810 } );
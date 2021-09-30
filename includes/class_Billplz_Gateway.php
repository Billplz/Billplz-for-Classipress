<?php

class Classi_Billplz extends APP_Gateway {

    /**
     * Sets up the gateway
     */
    public function __construct() {
        parent::__construct('billplz', array(
            'dropdown' => __('Billplz', APP_TD),
            'admin' => __('Billplz', APP_TD),
            'recurring' => false,
        ));
        add_action('init', array($this, 'register'));
    }

    public function register() {

        if (!APP_Gateway_Registry::is_gateway_enabled('billplz'))
            return;

        $options = APP_Gateway_Registry::get_gateway_options('billplz');
    }

    public function form() {


        $form_values = array(
            array(
                'title' => __('API Secret Key*', APP_TD),
                'tip' => __('Please enter your Billplz API Secret Key.', APP_TD) . ' ' . sprintf(__('Get Your API Key: %sBillplz%s.', 'wcbillplz'), '<a href="https://www.billplz.com/enterprise/setting" target="_blank">', '</a>'),
                'type' => 'text',
                'name' => 'api_key',
            ),
            array(
                'title' => __('XSignature Key*', APP_TD),
                'tip' => __('Please enter your Billplz XSignature Key.', APP_TD) . ' ' . sprintf(__('Get Your XSignature Key: %sBillplz%s.', 'wcbillplz'), '<a href="https://www.billplz.com/enterprise/setting" target="_blank">', '</a>'),
                'type' => 'text',
                'name' => 'x_signature',
            ),
            array(
                'title' => __('Collection ID', APP_TD),
                'tip' => __('Enter your Billplz Collection ID. This field is OPTIONAL', APP_TD),
                'type' => 'text',
                'name' => 'collection_id',
            ),
            array(
                'title' => __('Bill Payment Reminder', APP_TD),
                'tip' => __('We recommend you to set this to No Reminder.', APP_TD),
                'type' => 'select',
                'name' => 'reminder',
                'choices' => array(
                    '0' => __('No Reminder', APP_TD),
                    '1' => __('Email Only (FREE)', APP_TD),
                    '2' => __('SMS Only (RM0.15)', APP_TD),
                    '3' => __('Both (RM0.15)', APP_TD)
                )
            ),
        );

        $return_array = array(
            "title" => __('General', APP_TD),
            'desc' => __('Complete the fields below so you can start accepting payments with Billplz.', APP_TD),
            "fields" => $form_values
        );
        return $return_array;
    }

    protected function create_bill($order, $options) {
        $return_url = $order->get_return_url();
        $current_user = wp_get_current_user();

        $name = $current_user->user_firstname . $current_user->user_lastname;
        if (empty($name)) {
            $name = $current_user->user_login;
        }

        $billplz = new Billplz($options['api_key']);
        $billplz
                ->setAmount($order->get_total())
                ->setCollection($options['collection_id'])
                ->setDeliver($options['reminder'])
                ->setDescription($order->get_description())
                ->setEmail($current_user->user_email)
                ->setName($name)
                ->setPassbackURL(APP_Billplz_IPN_Listener::get_listener_url(), $return_url)
                ->setReference_1($order->get_id())
                ->setReference_1_Label('ID')
                ->create_bill(true);

        $bill_url = $billplz->getURL();
        return $bill_url;
    }

    protected function redirect($url) {

        if (!headers_sent()) {
            wp_redirect(esc_url_raw($url));
            exit;
            //wp_die();
        } else {
            //----------------------------------------------------------------//
            $ready = "If you are not redirected, please click <a href=" . '"' . $url . '"' . " target='_self'>Here</a><br />"
                    . "<script>location.href = '" . $url . "'</script>";
            echo $ready;
        }
    }

    public function process($order, $options) {
        if (!isset($_POST['x_signature']) && !isset($_GET['billplz']['x_signature'])) {
            $url = $this->create_bill($order, $options);
            $this->redirect($url);
        } else {
            $this->return_processing($order, $options);
        }
    }

    /**
     *  This method to do processing after both redirect and callback
     * 
     * @param type $order
     * @param type $options
     */
    protected function return_processing($order, $options) {
        if (isset($_GET['billplz']['x_signature'])) {
            $data = Billplz::getRedirectData($options['x_signature']);
        } else {
            wp_die('Invalid Request!');
        }

        $billplz = new Billplz($options['api_key']);
        $moreData = $billplz->check_bill($data['id']);

        if ($data['paid']) {
            $this->save_payment($order, $data);
        } else {
            $order->failed();
            $this->redirect($order->get_cancel_url());
        }
    }

    /**
     * Because the nature of $order->complete() is automatically redirect, 
     * so do nothing!
     * 
     * @param type $order
     * @param type $moreData
     */
    protected function save_payment($order, $moreData) {
        /*
         * To prevent duplicate update
         */
        if ($order->get_status() == APPTHEMES_ORDER_PENDING) {
            $order->log('Bill ID: ' . $moreData['id'], 'major');
            $order->log('Collection ID: ' . $moreData['collection_id'], 'major');
            $order->log('URL: ' . $moreData['url'], 'major');
            $order->log('Type: Return', 'major');
            $order->complete();
        }
    }

}

appthemes_register_gateway('Classi_Billplz');

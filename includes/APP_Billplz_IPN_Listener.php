<?php

class APP_Billplz_IPN_Listener {

    const QUERY_VAR = 'billplz_classi_call';
    const LISTENER_PASSPHRASE = 'billplz_listener_passphrase';

    private $callback;

    public function __construct() {
        $this->listen();
    }

    public static function get_listener_url() {

        $passphrase = get_option(self::LISTENER_PASSPHRASE, false);
        if (!$passphrase) {
            $passphrase = md5(site_url() . time());
            update_option(self::LISTENER_PASSPHRASE, $passphrase);
        }

        return add_query_arg(self::QUERY_VAR, $passphrase, site_url('/'));
    }

    public function listen() {
        if (!isset($_GET[self::QUERY_VAR]))
            return;

        $passphrase = get_option(self::LISTENER_PASSPHRASE, false);
        if (!$passphrase) {
            return;
        }

        if ($_GET[self::QUERY_VAR] != $passphrase) {
            return;
        }

        $options = APP_Gateway_Registry::get_gateway_options('billplz');

        $this->update_order_status($options);

        wp_die('Successful Callback');
    }

    protected function update_order_status($options) {

        $data = Billplz::getCallbackData($signkey);
        $billplz = new Billplz($options['api_key']);
        $moreData = $billplz->check_bill($data['id']);

        $order_id = (int) $moreData['reference_1'];
        $order = appthemes_get_order($order_id);

        sleep(10);

        if ($data['paid']) {
            $this->save_payment($order, $moreData);
        } else {
            $order->failed();
        }
    }

    protected function save_payment($order, $moreData) {
        /*
         * To prevent duplicate update
         */
        if ($order->get_status() == APPTHEMES_ORDER_PENDING) {
            $order->log('Bill ID: ' . $moreData['id'], 'major');
            $order->log('Collection ID: ' . $moreData['collection_id'], 'major');
            $order->log('URL: ' . $moreData['url'], 'major');
            $order->log('Type: Callback', 'major');
            $order->complete();
        }
    }

}

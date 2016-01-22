# paywork

这个小东西整理自[微信官方支付sdk](https://pay.weixin.qq.com/wiki/doc/api/index.html),[下载地址](https://pay.weixin.qq.com/wiki/doc/api/native.php?chapter=11_1)

＃ [使用方式](#markdown-pane)：

这东西造出来是放在laravel框架中的，但是感觉上就算不用框架也没问题，未测试。

在框架中可以创建一个ServiceProvider，使用 `php artisan make:provider`命令创建一个。在里面的`register`方法里添加bind代码到app。

```

    public function register()
    {
        $this->app->bind('wxpay', function ($app) {
            $wxpay = new \App\Services\Wxpay();
            $wxpay->setConfig($app->config->get('wechat'));
            return $wxpay;
        });
    }

```

`假如不用框架，使用的时候，直接new就好了。`

# 支付

支付分两步，第一步创建支付URL，或者是二维码

```
      //先创建好订单详情，然后发起下面的调用

      $wxpay = app('wxpay');
      //$wxpay = new Wxpay();

      $wxpay->SetBody($_order_name);
      $wxpay->SetOut_trade_no($_order_no);
      $wxpay->SetTotal_fee($_order_price);
      $wxpay->SetNotify_url(url('回调路径'));
      $wxpay->SetTrade_type("NATIVE");
      //$wxpay->SetAttach("上海本部"); // 附加信息
      //$wxpay->SetTime_start(date("YmdHis"));
      //$wxpay->SetTime_expire(date("YmdHis", time() + 600));
      //$wxpay->SetGoods_tag("test");
      //$wxpay->SetProduct_id("123456789");
      $return = $wxpay->unifiedOrder();
      $_return_url = $return['code_url'];
      return \PHPQRCode\QRcode::png($_return_url, false, 'L', 6, 4);
```

支付完成，处理回调业务：

```

      //
      $wxpay_notify = app('wxpay');

      if ( $wxpay_notify->verify() ) {
          if (!$this->处理订单($wxpay_notify->GetOut_trade_no())) {
              //订单处理失败
              $wxpay_notify->reply('FAIL', 'order does error');
          }
      }

      // 交易成功后: 记得在交易流水日志记录
      
      $wxpay_notify->reply();

```

# 退款

退款流程分三步，创建退款订单，然后发起退单请求，然后隔几天查一下退款结果，微信退款不像阿里那样有回调页面。

```
      $wxpay = app('wxpay');
      // 退款需要秘钥，在 https://pay.weixin.qq.com/index.php/home/login?return_url=%2F 申请下载
      $wxpay  ->set_SSLCERT_PATH(base_path('cert' . DIRECTORY_SEPARATOR . 'wxpay' . DIRECTORY_SEPARATOR ) . "apiclient_cert.pem")
              ->set_SSLKEY_PATH(base_path('cert' . DIRECTORY_SEPARATOR . 'wxpay' . DIRECTORY_SEPARATOR ) . "apiclient_key.pem")
              //->SetOut_trade_no($order_no) //只提供一个就行
              ->SetTransaction_id($wxtrade_no) // 腾讯支付完成后的微信方的订单号
              ->SetTotal_fee( $total_price ) // 腾讯的退款金额单位是分,不需要除100
              ->SetRefund_fee( $refund_price )// 
              ->SetOut_refund_no($order_refund_no)//退单号
              ->SetOp_user_id();

      $refund_result = $wxpay->refund();
```

<a name="markdown-pane"></a>查询退款结果。

```
      $wxpay = app('wxpay');

      $wxpay->SetOut_refund_no($order_refund_no);//退单号
      $refund_result = $wxpay->refundQuery();
```


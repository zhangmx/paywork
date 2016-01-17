<?php
/**
 * User: zhangmx
 * Date: 1/13/16
 * Time: 2:44 PM
 */

namespace App\Services;

use Log;

class Wxpay
{

    //////////////
    /// 配置参数
    //////////////


    //=======【基本信息设置】=====================================
    //
    /**
     * 微信公众号信息配置
     *
     * APPID：绑定支付的APPID（必须配置，开户邮件中可查看）
     *
     * MCHID：商户号（必须配置，开户邮件中可查看）
     *
     * KEY：商户支付密钥，参考开户邮件设置（必须配置，登录商户平台自行设置）
     * 设置地址：https://pay.weixin.qq.com/index.php/account/api_cert
     *
     * APPSECRET：公众帐号secert（仅JSAPI支付的时候需要配置， 登录公众平台，进入开发者中心可设置），
     * 获取地址：https://mp.weixin.qq.com/advanced/advanced?action=dev&t=advanced/dev&token=2005451881&lang=zh_CN
     * @var string
     */
    protected $APPID = '';//'wx426b3015555a46be';
    protected $MCHID = '';//'1225312702';
    protected $KEY = '';//'e10adc3949ba59abbe56e057f20f883e';
    protected $APPSECRET = '';//'01c6d59a3f9024db6336662ac95c8e74';

    //=======【证书路径设置】=====================================
    /**
     * 设置商户证书路径
     * 证书路径,注意应该填写绝对路径（仅退款、撤销订单时需要，可登录商户平台下载，
     * API证书下载地址：https://pay.weixin.qq.com/index.php/account/api_cert，下载之前需要安装商户操作证书）
     */
    protected $SSLCERT_PATH = '';// '../cert/apiclient_cert.pem';
    protected $SSLKEY_PATH = '';//'../cert/apiclient_key.pem';

    //=======【curl代理设置】===================================
    /**
     * 这里设置代理机器，只有需要代理的时候才设置，不需要代理，请设置为0.0.0.0和0
     * 本例程通过curl使用HTTP POST方法，此处可修改代理服务器，
     * 默认CURL_PROXY_HOST=0.0.0.0和CURL_PROXY_PORT=0，此时不开启代理（如有需要才设置）
     */
    protected $CURL_PROXY_HOST = "0.0.0.0";//"10.152.18.220";
    protected $CURL_PROXY_PORT = 0;//8080;

    //=======【上报信息配置】===================================
    /**
     * 接口调用上报等级，默认紧错误上报（注意：上报超时间为【1s】，上报无论成败【永不抛出异常】，
     * 不会影响接口调用流程），开启上报之后，方便微信监控请求调用的质量，建议至少
     * 开启错误上报。
     * 上报等级，0.关闭上报; 1.仅错误出错上报; 2.全量上报
     * @var int
     */
    protected $REPORT_LEVENL = 1;

    ///////////////
    /// 获取接口内容
    ///////////////

    // 传送到微信服务器的数据
    protected $values = array();
    // keys : 'return_msg' 'notify_url' 'out_trade_no' 'trade_type' 'body'  'total_fee'  'sign'

    // 异步通知回调URL
    protected $NOTIFY_URL = '';


    /**
     * 设置 KEY
     * @param string $value
     * @return $this
     **/
    public function set_KEY($value)
    {
        $this->KEY = $value;
        return $this;
    }

    /**
     * 设置 APPSECRET js 调用方式能用到
     * @param string $value
     * @return $this
     **/
    public function set_APPSECRET($value)
    {
        $this->APPSECRET = $value;
        return $this;
    }

    /**
     * 设置商户证书路径
     * @param string $value
     * @return $this
     **/
    public function set_SSLCERT_PATH($value)
    {
        $this->SSLCERT_PATH = $value;
        return $this;
    }
    /**
     * 设置商户证书路径
     * @param string $value
     * @return $this
     **/
    public function set_SSLKEY_PATH($value)
    {
        $this->SSLKEY_PATH = $value;
        return $this;
    }

    /**
     * 设置代理链接
     * @param string $value
     * @return $this
     **/
    public function set_PROXY_HOST($value)
    {
        $this->CURL_PROXY_HOST = $value;
        return $this;
    }

    /**
     * 设置代理链接端口
     * @param string $value
     * @return $this
     **/
    public function set_PROXY_PORT($value)
    {
        $this->CURL_PROXY_PORT = $value;
        return $this;
    }

    /**
     * 设置上报等级
     * @param int|string $value
     * @return $this
     */
    public function set_REPORT_LEVENL ($value = 1 )
    {
        $this->REPORT_LEVENL = $value;
        return $this;
    }

    /**
     * 基于本项目的配置文件  深度定制 需要 wechat.php
     * @param array $config
     */
    public function setConfig( $config = array()) {
        $this   -> SetAppid($config['app_id'])
                -> SetMch_id($config['mchid'])
                -> set_APPSECRET($config['secret'])
                -> set_KEY($config['key']);
    }

    /**
     *
     * 统一下单，WxPayUnifiedOrder中out_trade_no、body、total_fee、trade_type必填
     * appid、mchid、spbill_create_ip、nonce_str不需要填入
     * @param int $timeOut
     * @throws \Exception
     * @return string 成功时返回，其他抛异常
     */
    public function unifiedOrder($timeOut = 6)
    {
        $url = "https://api.mch.weixin.qq.com/pay/unifiedorder";
        //检测必填参数
        if (!$this->IsOut_trade_noSet()) {
            throw new \Exception("缺少统一支付接口必填参数out_trade_no！");
        } else {
            if (!$this->IsBodySet()) {
                throw new \Exception("缺少统一支付接口必填参数body！");
            } else {
                if (!$this->IsTotal_feeSet()) {
                    throw new \Exception("缺少统一支付接口必填参数total_fee！");
                } else {
                    if (!$this->IsTrade_typeSet()) {
                        throw new \Exception("缺少统一支付接口必填参数trade_type！");
                    }
                }
            }
        }

        //关联参数
        if ($this->GetTrade_type() == "JSAPI" && !$this->IsOpenidSet()) {
            throw new \Exception("统一支付接口中，缺少必填参数openid！trade_type为JSAPI时，openid为必填参数！");
        }
        //
//        if ($this->GetTrade_type() == "NATIVE" && !$this->IsProduct_idSet()) {
//            throw new \Exception("统一支付接口中，缺少必填参数product_id！trade_type为NATIVE时，product_id 为必填参数！");
//        }

        //异步通知url未设置，则使用配置文件中的url
        if (!$this->IsNotify_urlSet()) {
            throw new \Exception("缺少异步通知url！");
        }

//        $this->SetAppid($this->APPID);//公众账号ID
//        $this->SetMch_id($this->MCHID);//商户号
        $this->SetSpbill_create_ip($_SERVER['REMOTE_ADDR']);//终端ip
        $this->SetNonce_str($this->getNonceStr());//随机字符串

        //签名
        $this->SetSign();
        //dd($this);
        $xml = $this->ToXml();
//        dd($xml);
        $startTimeStamp = $this->getMillisecond();//请求开始时间
        $response = $this->postXmlCurl($xml, $url, false, $timeOut);
        $result = $this->Init($response);
        $this->reportCostTime($url, $startTimeStamp, $result);//上报请求花费时间

        return $result;
    }


    /**
     *
     * 产生随机字符串，不长于32位
     * @param int $length
     * @return string 产生的随机字符串
     */
    public function getNonceStr($length = 32)
    {
        $chars = "abcdefghijklmnopqrstuvwxyz0123456789";
        $str = "";
        for ($i = 0; $i < $length; $i++) {
            $str .= substr($chars, mt_rand(0, strlen($chars) - 1), 1);
        }
        return $str;
    }

    /**
     * 生成签名
     * @return string 签名，本函数不覆盖sign成员变量，如要设置签名需要调用SetSign方法赋值
     */
    public function MakeSign()
    {
        //签名步骤一：按字典序排序参数
        ksort($this->values);
        $string = $this->ToUrlParams();
        //签名步骤二：在string后加入KEY
        $string = $string . "&key=" . $this->KEY;
        //签名步骤三：MD5加密
        $string = md5($string);
        //签名步骤四：所有字符转为大写
        $result = strtoupper($string);
        return $result;
    }

    /**
     * 格式化参数格式化成url参数
     * @return string
     */
    public function ToUrlParams()
    {
        $buff = "";
        foreach ($this->values as $k => $v) {
            if ($k != "sign" && $v != "" && !is_array($v)) {
                $buff .= $k . "=" . $v . "&";
            }
        }

        $buff = trim($buff, "&");
        return $buff;
    }

    //////////////
    /// 回调
    //////////////


    /**
     * 输出xml字符
     * @throws \Exception
     *
     * @return string
     **/
    public function ToXml()
    {
        if (!is_array($this->values)
            || count($this->values) <= 0
        ) {
            throw new \Exception("数组数据异常！");
        }

        $xml = "<xml>";
        foreach ($this->values as $key => $val) {
            if (is_numeric($val)) {
                $xml .= "<" . $key . ">" . $val . "</" . $key . ">";
            } else {
                $xml .= "<" . $key . "><![CDATA[" . $val . "]]></" . $key . ">";
            }
        }
        $xml .= "</xml>";
        return $xml;
    }

    /**
     * 获取毫秒级别的时间戳
     */
    private function getMillisecond()
    {
        //获取毫秒的时间戳
        $time = explode(" ", microtime());
        $time = $time[1] . ($time[0] * 1000);
        $time2 = explode(".", $time);
        $time = $time2[0];
        return $time;
    }

    /**
     * 以post方式提交xml到对应的接口url
     *
     * @param string $xml 需要post的xml数据
     * @param string $url url
     * @param bool $useCert 是否需要证书，默认不需要
     * @param int $second url执行超时时间，默认30s
     * @return mixed
     * @throws \Exception
     */
    private function postXmlCurl($xml, $url, $useCert = false, $second = 30)
    {
        $ch = curl_init();
        //设置超时
        curl_setopt($ch, CURLOPT_TIMEOUT, $second);

        //如果有配置代理这里就设置代理
        if ($this->CURL_PROXY_HOST != "0.0.0.0"
            && $this->CURL_PROXY_PORT != 0
        ) {
            curl_setopt($ch, CURLOPT_PROXY, $this->CURL_PROXY_HOST);
            curl_setopt($ch, CURLOPT_PROXYPORT, $this->CURL_PROXY_PORT);
        }
        curl_setopt($ch, CURLOPT_URL, $url);
        //设置header
        curl_setopt($ch, CURLOPT_HEADER, false);
        //要求结果为字符串且输出到屏幕上
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

        if ($useCert == true) {
            //设置证书
            //使用证书：cert 与 key 分别属于两个.pem文件
            curl_setopt($ch, CURLOPT_SSLCERTTYPE, 'PEM');
            curl_setopt($ch, CURLOPT_SSLCERT, $this->SSLCERT_PATH);
            curl_setopt($ch, CURLOPT_SSLKEYTYPE, 'PEM');
            curl_setopt($ch, CURLOPT_SSLKEY, $this->SSLKEY_PATH);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);//严格校验
        } else {
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);//严格校验
        }
        //post提交方式
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $xml);
        //运行curl
        $data = curl_exec($ch);
        //返回结果
        if ($data) {
            curl_close($ch);
            return $data;
        } else {
            $error = curl_errno($ch);
            curl_close($ch);
            throw new \Exception("curl出错，错误码:$error");
        }
    }

    /**
     *
     * 上报数据， 上报的时候将屏蔽所有异常流程
     * @param string $url
     * @param int $startTimeStamp
     * @param array $data
     */
    private function reportCostTime($url, $startTimeStamp, $data)
    {
        //如果不需要上报数据
        if ($this->REPORT_LEVENL == 0) {
            return;
        }
        //如果仅失败上报
        if ($this->REPORT_LEVENL == 1 &&
            array_key_exists("return_code", $data) &&
            $data["return_code"] == "SUCCESS" &&
            array_key_exists("result_code", $data) &&
            $data["result_code"] == "SUCCESS"
        ) {
            return;
        }

        //上报逻辑
        $endTimeStamp = $this->getMillisecond();

        $this->SetInterface_url($url);
        $this->SetExecute_time_($endTimeStamp - $startTimeStamp);
        //返回状态码
        if (array_key_exists("return_code", $data)) {
            $this->SetReturn_code($data["return_code"]);
        }
        //返回信息
        if (array_key_exists("return_msg", $data)) {
            $this->SetReturn_msg($data["return_msg"]);
        }
        //业务结果
        if (array_key_exists("result_code", $data)) {
            $this->SetResult_code($data["result_code"]);
        }
        //错误代码
        if (array_key_exists("err_code", $data)) {
            $this->SetErr_code($data["err_code"]);
        }
        //错误代码描述
        if (array_key_exists("err_code_des", $data)) {
            $this->SetErr_code_des($data["err_code_des"]);
        }
        //商户订单号
        if (array_key_exists("out_trade_no", $data)) {
            $this->SetOut_trade_no($data["out_trade_no"]);
        }
        //设备号
        if (array_key_exists("device_info", $data)) {
            $this->SetDevice_info($data["device_info"]);
        }

        try {
            $this->report();
        } catch (\Exception $e) {
            //不做任何处理
        }
    }

    /**
     *
     * 测速上报，该方法内部封装在report中，使用时请注意异常流程
     * WxPay Report 中interface_url、return_code、result_code、user_ip、execute_time_必填
     * appid、mchid、spbill_create_ip、nonce_str不需要填入
     * @param int $timeOut
     * @throws \Exception
     * @return string 成功时返回，其他抛异常
     */
    public function report($timeOut = 1)
    {
        $url = "https://api.mch.weixin.qq.com/payitil/report";
        //检测必填参数
        if (!$this->IsInterface_urlSet()) {
            throw new \Exception("接口URL，缺少必填参数interface_url！");
        }
        if (!$this->IsReturn_codeSet()) {
            throw new \Exception("返回状态码，缺少必填参数return_code！");
        }
        if (!$this->IsResult_codeSet()) {
            throw new \Exception("业务结果，缺少必填参数result_code！");
        }
        if (!$this->IsUser_ipSet()) {
            throw new \Exception("访问接口IP，缺少必填参数user_ip！");
        }
        if (!$this->IsExecute_time_Set()) {
            throw new \Exception("接口耗时，缺少必填参数execute_time_！");
        }
//        $this->SetAppid($this->APPID);//公众账号ID
//        $this->SetMch_id($this->MCHID);//商户号
        $this->SetUser_ip($_SERVER['REMOTE_ADDR']);//终端ip
        $this->SetTime(date("YmdHis"));//商户上报时间
        $this->SetNonce_str($this->getNonceStr());//随机字符串

        $this->SetSign();//签名
        $xml = $this->ToXml();

        $response = $this->postXmlCurl($xml, $url, false, $timeOut);
        return $response;
    }


    /**
     *
     * 查询订单，WxPayOrderQuery中out_trade_no、transaction_id至少填一个
     * appid、mchid、spbill_create_ip、nonce_str不需要填入
     * @param int $timeOut
     * @throws \Exception
     * @return string 成功时返回，其他抛异常
     */
    public function orderQuery($timeOut = 6)
    {
        $url = "https://api.mch.weixin.qq.com/pay/orderquery";
        //检测必填参数
        if (!$this->IsOut_trade_noSet() && !$this->IsTransaction_idSet()) {
            throw new \Exception("订单查询接口中，out_trade_no、transaction_id至少填一个！");
        }
//        $this->SetAppid($this->APPID);//公众账号ID
//        $this->SetMch_id($this->MCHID);//商户号
        $this->SetNonce_str($this->getNonceStr());//随机字符串

        $this->SetSign();//签名
        $xml = $this->ToXml();

        $startTimeStamp = $this->getMillisecond();//请求开始时间
        $response = $this->postXmlCurl($xml, $url, false, $timeOut);
        $result = $this->Init($response);
        $this->reportCostTime($url, $startTimeStamp, $result);//上报请求花费时间

        return $result;
    }

    /**
     *
     * 关闭订单，WxPayCloseOrder中out_trade_no必填
     * appid、mchid、spbill_create_ip、nonce_str不需要填入
     * @param int $timeOut
     * @throws \Exception
     * @return string 成功时返回，其他抛异常
     */
    public function closeOrder($timeOut = 6)
    {
        $url = "https://api.mch.weixin.qq.com/pay/closeorder";
        //检测必填参数
        if (!$this->IsOut_trade_noSet()) {
            throw new \Exception("订单查询接口中，out_trade_no必填！");
        }
//        $this->SetAppid($this->APPID);//公众账号ID
//        $this->SetMch_id($this->MCHID);//商户号
        $this->SetNonce_str($this->getNonceStr());//随机字符串

        $this->SetSign();//签名
        $xml = $this->ToXml();

        $startTimeStamp = $this->getMillisecond();//请求开始时间
        $response = $this->postXmlCurl($xml, $url, false, $timeOut);
        $result = $this->Init($response);
        $this->reportCostTime($url, $startTimeStamp, $result);//上报请求花费时间

        return $result;
    }

    /**
     *
     * 申请退款，WxPayRefund中out_trade_no、transaction_id至少填一个且
     * out_refund_no、total_fee、refund_fee、op_user_id为必填参数
     * appid、mchid、spbill_create_ip、nonce_str不需要填入
     * @param int $timeOut
     * @throws \Exception
     * @return string 成功时返回，其他抛异常
     */
    public function refund($timeOut = 6)
    {
        $url = "https://api.mch.weixin.qq.com/secapi/pay/refund";
        //检测必填参数
        if (!$this->IsOut_trade_noSet() && !$this->IsTransaction_idSet()) {
            throw new \Exception("退款申请接口中，out_trade_no、transaction_id至少填一个！");
        } else {
            if (!$this->IsOut_refund_noSet()) {
                throw new \Exception("退款申请接口中，缺少必填参数out_refund_no！");
            } else {
                if (!$this->IsTotal_feeSet()) {
                    throw new \Exception("退款申请接口中，缺少必填参数total_fee！");
                } else {
                    if (!$this->IsRefund_feeSet()) {
                        throw new \Exception("退款申请接口中，缺少必填参数refund_fee！");
                    } else {
                        if (!$this->IsOp_user_idSet()) {
                            throw new \Exception("退款申请接口中，缺少必填参数op_user_id！");
                        }
                    }
                }
            }
        }
//        $this->SetAppid($this->APPID);//公众账号ID
//        $this->SetMch_id($this->MCHID);//商户号
        $this->SetNonce_str($this->getNonceStr());//随机字符串

        $this->SetSign();//签名
        $xml = $this->ToXml();
        $startTimeStamp = $this->getMillisecond();//请求开始时间
        $response = $this->postXmlCurl($xml, $url, true, $timeOut);
//        $response = $this->postXmlCurl($xml, $url, false, $timeOut);
        $result = $this->Init($response);
        $this->reportCostTime($url, $startTimeStamp, $result);//上报请求花费时间

        return $result;
    }

    /**
     *
     * 查询退款
     * 提交退款申请后，通过调用该接口查询退款状态。退款有一定延时，
     * 用零钱支付的退款20分钟内到账，银行卡支付的退款3个工作日后重新查询退款状态。
     * WxPayRefundQuery中out_refund_no、out_trade_no、transaction_id、refund_id四个参数必填一个
     * appid、mchid、spbill_create_ip、nonce_str不需要填入
     * @param int $timeOut
     * @throws \Exception
     * @return string  成功时返回，其他抛异常
     */
    public function refundQuery($timeOut = 6)
    {
        $url = "https://api.mch.weixin.qq.com/pay/refundquery";
        //检测必填参数
        if (!$this->IsOut_refund_noSet() &&
            !$this->IsOut_trade_noSet() &&
            !$this->IsTransaction_idSet() &&
            !$this->IsRefund_idSet()
        ) {
            throw new \Exception("退款查询接口中，out_refund_no、out_trade_no、transaction_id、refund_id四个参数必填一个！");
        }
//        $this->SetAppid($this->APPID);//公众账号ID
//        $this->SetMch_id($this->MCHID);//商户号
        $this->SetNonce_str($this->getNonceStr());//随机字符串

        $this->SetSign();//签名
        $xml = $this->ToXml();

        $startTimeStamp = $this->getMillisecond();//请求开始时间
        $response = $this->postXmlCurl($xml, $url, false, $timeOut);
        $result = $this->Init($response);
        $this->reportCostTime($url, $startTimeStamp, $result);//上报请求花费时间

        return $result;
    }

    /**
     * 下载对账单，WxPayDownloadBill中bill_date为必填参数
     * appid、mchid、spbill_create_ip、nonce_str不需要填入
     * @param int $timeOut
     * @throws \Exception
     * @return string 成功时返回，其他抛异常
     */
    public function downloadBill($timeOut = 6)
    {
        $url = "https://api.mch.weixin.qq.com/pay/downloadbill";
        //检测必填参数
        if (!$this->IsBill_dateSet()) {
            throw new \Exception("对账单接口中，缺少必填参数bill_date！");
        }
//        $this->SetAppid($this->APPID);//公众账号ID
//        $this->SetMch_id($this->MCHID);//商户号
        $this->SetNonce_str($this->getNonceStr());//随机字符串

        $this->SetSign();//签名
        $xml = $this->ToXml();

        $response = $this->postXmlCurl($xml, $url, false, $timeOut);
        if (substr($response, 0, 5) == "<xml>") {
            return "";
        }
        return $response;
    }

    /**
     * 提交被扫支付API
     * 收银员使用扫码设备读取微信用户刷卡授权码以后，二维码或条码信息传送至商户收银台，
     * 由商户收银台或者商户后台调用该接口发起支付。
     * WxPayWxPayMicroPay中body、out_trade_no、total_fee、auth_code参数必填
     * appid、mchid、spbill_create_ip、nonce_str不需要填入
     * @param int $timeOut
     * @return string
     * @throws \Exception
     */
    public function micropay($timeOut = 10)
    {
        $url = "https://api.mch.weixin.qq.com/pay/micropay";
        //检测必填参数
        if (!$this->IsBodySet()) {
            throw new \Exception("提交被扫支付API接口中，缺少必填参数body！");
        } else {
            if (!$this->IsOut_trade_noSet()) {
                throw new \Exception("提交被扫支付API接口中，缺少必填参数out_trade_no！");
            } else {
                if (!$this->IsTotal_feeSet()) {
                    throw new \Exception("提交被扫支付API接口中，缺少必填参数total_fee！");
                } else {
                    if (!$this->IsAuth_codeSet()) {
                        throw new \Exception("提交被扫支付API接口中，缺少必填参数auth_code！");
                    }
                }
            }
        }

        $this->SetSpbill_create_ip($_SERVER['REMOTE_ADDR']);//终端ip
//        $this->SetAppid($this->APPID);//公众账号ID
//        $this->SetMch_id($this->MCHID);//商户号
        $this->SetNonce_str($this->getNonceStr());//随机字符串

        $this->SetSign();//签名
        $xml = $this->ToXml();

        $startTimeStamp = $this->getMillisecond();//请求开始时间
        $response = $this->postXmlCurl($xml, $url, false, $timeOut);
        $result = $this->Init($response);
        $this->reportCostTime($url, $startTimeStamp, $result);//上报请求花费时间

        return $result;
    }

    /**
     *
     * 撤销订单API接口，WxPayReverse中参数out_trade_no和transaction_id必须填写一个
     * appid、mchid、spbill_create_ip、nonce_str不需要填入
     * @param int $timeOut
     * @throws \Exception
     * @return string
     */
    public function reverse($timeOut = 6)
    {
        $url = "https://api.mch.weixin.qq.com/secapi/pay/reverse";
        //检测必填参数
        if (!$this->IsOut_trade_noSet() && !$this->IsTransaction_idSet()) {
            throw new \Exception("撤销订单API接口中，参数out_trade_no和transaction_id必须填写一个！");
        }

//        $this->SetAppid($this->APPID);//公众账号ID
//        $this->SetMch_id($this->MCHID);//商户号
        $this->SetNonce_str($this->getNonceStr());//随机字符串

        $this->SetSign();//签名
        $xml = $this->ToXml();

        $startTimeStamp = $this->getMillisecond();//请求开始时间
        $response = $this->postXmlCurl($xml, $url, true, $timeOut);
        $result = $this->Init($response);
        $this->reportCostTime($url, $startTimeStamp, $result);//上报请求花费时间

        return $result;
    }

    /**
     *
     * 生成二维码规则,模式一生成支付二维码
     * appid、mchid、spbill_create_ip、nonce_str不需要填入
     * @throws \Exception
     * @return string 成功时返回，其他抛异常
     */
    public function bizpayurl()
    {
        if (!$this->IsProduct_idSet()) {
            throw new \Exception("生成二维码，缺少必填参数product_id！");
        }

//        $this->SetAppid($this->APPID);//公众账号ID
//        $this->SetMch_id($this->MCHID);//商户号
        $this->SetTime_stamp(time());//时间戳
        $this->SetNonce_str($this->getNonceStr());//随机字符串

        $this->SetSign();//签名

        return $this->GetValues();
    }

    /**
     *
     * 转换短链接
     * 该接口主要用于扫码原生支付模式一中的二维码链接转成短链接(weixin://wxpay/s/XXXXXX)，
     * 减小二维码数据量，提升扫描速度和精确度。
     * appid、mchid、spbill_create_ip、nonce_str不需要填入
     * @param int $timeOut
     * @throws \Exception
     * @return string 成功时返回，其他抛异常
     */
    public function shorturl($timeOut = 6)
    {
        $url = "https://api.mch.weixin.qq.com/tools/shorturl";
        //检测必填参数
        if (!$this->IsLong_urlSet()) {
            throw new \Exception("需要转换的URL，签名用原串，传输需URL encode！");
        }
//        $this->SetAppid($this->APPID);//公众账号ID
//        $this->SetMch_id($this->MCHID);//商户号
        $this->SetNonce_str($this->getNonceStr());//随机字符串

        $this->SetSign();//签名
        $xml = $this->ToXml();

        $startTimeStamp = $this->getMillisecond();//请求开始时间
        $response = $this->postXmlCurl($xml, $url, false, $timeOut);
        $result = $this->Init($response);
        $this->reportCostTime($url, $startTimeStamp, $result);//上报请求花费时间

        return $result;
    }

    ///////////////////////////  支付回调  \\\\\\\\\\\\\\\\\\\\\\\\\\\

    /**
     * 验证订单消息是否合法,
     * 不合法返回false, 合法返回订单信息详情
     *
     * @return bool
     */
    public function verify() {

        if (version_compare(PHP_VERSION, '5.6.0', '<')) {
            if (!empty($GLOBALS['HTTP_RAW_POST_DATA'])) {
                $xmlInput = $GLOBALS['HTTP_RAW_POST_DATA'];
            } else {
                $xmlInput = file_get_contents('php://input');
            }
        } else {
            $xmlInput = file_get_contents('php://input');
        }

        if (empty($xmlInput)) {
            return false;
        }
        // check sign
        $result = $this->Init($xmlInput);

        $msg = "OK";
        $result = $this->NotifyProcess($result, $msg);
        //
        if ($result == true) {
            // 可以进行业务逻辑处理,如果业务处理失败,需要重新设置
//            $this->SetReturn_code("SUCCESS");
//            $this->SetReturn_msg("OK");
        } else {
            $this->SetReturn_code("FAIL");
            $this->SetReturn_msg($msg);
            // 直接返回给微信错误信息.
            $this->_ReplyNotify(false);
        }
        return $result;
    }

    /**
     * 回复消息, 如果不回复, 微信会一直发送请求到notify_url
     *
     * @param string $code
     * @param string $msg
     *
     * @return string
     */
    public function reply($code = 'SUCCESS', $msg = 'OK') {

        $this->SetReturn_code($code);
        $this->SetReturn_msg($msg);

        $this->_ReplyNotify(false);
    }


    /**
     *
     * 回调入口
     * @param bool $needSign 是否需要签名输出
     */
    final public function Handle($needSign = true)
    {
        $msg = "OK";
        //当返回false的时候，表示notify中调用NotifyCallBack回调失败获取签名校验失败，此时直接回复失败
        $result = $this->notify(array($this, 'NotifyCallBack'), $msg);
        if ($result == false) {
            $this->SetReturn_code("FAIL");
            $this->SetReturn_msg($msg);
            $this->_ReplyNotify(false);
            return;
        } else {
            //该分支在成功回调到NotifyCallBack方法，处理完成之后流程
            $this->SetReturn_code("SUCCESS");
            $this->SetReturn_msg("OK");
        }
        $this->_ReplyNotify($needSign);
    }
    /**
     *
     * 支付结果通用通知
     * @param function $callback
     * 直接回调函数使用方法: notify(you_function);
     * 回调类成员函数方法:notify(array($this, you_function));
     * $callback  原型为：function function_name($data){}
     *
     *
     * @return bool|mixed
     */
    public function notify($callback, &$msg)
    {
        //获取通知的数据
        $xml = $GLOBALS['HTTP_RAW_POST_DATA'];
        //如果返回成功则验证签名
        try {
            $result = $this->Init($xml);
        } catch (\Exception $e) {
            $msg = $e->getMessage();
            return false;
        }

        return call_user_func($callback, $result);
    }

    /**
     *
     * notify回调方法，该方法中需要赋值需要输出的参数,不可重写
     * @param array $data
     * @return true 回调出来完成不需要继续回调，false回调处理未完成需要继续回调
     */
    final public function NotifyCallBack($data)
    {
        $msg = "OK";
        $result = $this->NotifyProcess($data, $msg);

        if ($result == true) {
            $this->SetReturn_code("SUCCESS");
            $this->SetReturn_msg("OK");
        } else {
            $this->SetReturn_code("FAIL");
            $this->SetReturn_msg($msg);
        }
        return $result;
    }

    /**
     *
     * 回调方法入口，子类可重写该方法
     * 注意：
     * 1、微信回调超时时间为2s，建议用户使用异步处理流程，确认成功之后立刻回复微信服务器
     * 2、微信服务器在调用失败或者接到回包为非确认包的时候，会发起重试，需确保你的回调是可以重入
     * @param array $data 回调解释出的参数
     * @param string $msg 如果回调处理失败，可以将错误信息输出到该方法
     * @return true 回调出来完成不需要继续回调，false回调处理未完成需要继续回调
     */
    public function NotifyProcess($data, &$msg)
    {
        if(!array_key_exists("transaction_id", $data)){
            $msg = "输入参数不正确";
            return false;
        }
        //查询订单，判断订单真实性
        if(!$this->Queryorder($data["transaction_id"])){
            $msg = "订单查询失败";
            return false;
        }
        return true;
    }

    /**
     * 查询订单
     * @param $transaction_id
     * @return bool
     * @throws \Exception
     */
    public function Queryorder($transaction_id)
    {
        $this->SetTransaction_id($transaction_id);
        $result = $this->orderQuery();
        Log::notice("query:" . json_encode($result));
        if(array_key_exists("return_code", $result)
            && array_key_exists("result_code", $result)
            && $result["return_code"] == "SUCCESS"
            && $result["result_code"] == "SUCCESS")
        {
            return true;
        }
        return false;
    }

    /**
     *
     * 回复通知
     * @param bool $needSign 是否需要签名输出
     */
    final private function _ReplyNotify($needSign = true)
    {
        //如果需要签名
        if ($needSign == true &&
            $this->GetReturn_code() == "SUCCESS"
        ) {
            $this->SetSign();
        }
        $this->replyNotify($this->ToXml());
    }

    /**
     * 直接输出xml
     * @param string $xml
     */
    public function replyNotify($xml)
    {
        echo $xml;
    }




    ///////////////
    /// bean 对象
    ///////////////

    /**
     * 返回结果 将xml转为array
     * @param string $xml
     * @return array
     * @throws \Exception
     */
    public function Init($xml)
    {
        $this->FromXml($xml);
        //fix bug 2015-06-29
        if ($this->values['return_code'] != 'SUCCESS') {
            return $this->GetValues();
        }
        $this->CheckSign();
        return $this->GetValues();
    }

    /**
     * 将xml转为array
     * @param string $xml
     * @throws \Exception
     * @return array
     */
    public function FromXml($xml)
    {
        if (!$xml) {
            throw new \Exception("xml数据异常！");
        }
        //将XML转为array
        //禁止引用外部xml实体
        libxml_disable_entity_loader(true);
        $this->values = json_decode(json_encode(simplexml_load_string($xml, 'SimpleXMLElement', LIBXML_NOCDATA)), true);
        return $this->values;
    }

    /**
     * 获取设置的值
     */
    public function GetValues()
    {
        return $this->values;
    }

    /**
     *
     * 检测签名
     */
    public function CheckSign()
    {
        //fix异常
        if (!$this->IsSignSet()) {
            throw new \Exception("签名错误！");
        }

        $sign = $this->MakeSign();
        if ($this->GetSign() == $sign) {
            return true;
        }
        throw new \Exception("签名错误！");
    }

    /**
     * 获取签名，详见签名生成算法的值
     * @return string 值
     **/
    public function GetSign()
    {
        return $this->values['sign'];
    }

    /**
     * 设置签名，详见签名生成算法
     * @return string
     * @internal param string $value
     */
    public function SetSign()
    {
        $sign = $this->MakeSign();
        $this->values['sign'] = $sign;
        return $this;
    }

    /**
     * 判断签名，详见签名生成算法是否存在
     * @return true 或 false
     **/
    public function IsSignSet()
    {
        return array_key_exists('sign', $this->values);
    }

    /**
     * 获取发起接口调用时的机器IP 的值
     * @return  string 值
     **/
    public function GetUser_ip()
    {
        return $this->values['user_ip'];
    }

    /**
     * 设置发起接口调用时的机器IP
     * @param string $value
     * @return $this
     **/
    public function SetUser_ip($value)
    {
        $this->values['user_ip'] = $value;
        return $this;
    }

    /**
     * 判断发起接口调用时的机器IP 是否存在
     * @return true 或 false
     **/
    public function IsUser_ipSet()
    {
        return array_key_exists('user_ip', $this->values);
    }

    /**
     *
     * 使用数组初始化对象
     * @param array $array
     * @param boolean $noCheckSign 是否检测签名
     * @return $this
     */
    public function InitFromArray($array, $noCheckSign = false)
    {
        $this->FromArray($array);
        if ($noCheckSign == false) {
            $this->CheckSign();
        }
        return $this;
    }

    /**
     *
     * 使用数组初始化
     * @param array $array
     */
    public function FromArray($array)
    {
        $this->values = $array;
    }

    /**
     *
     * 设置参数
     * @param string $key
     * @param string $value
     * @return $this
     */
    public function SetData($key, $value)
    {
        $this->values[$key] = $value;
        return $this;
    }

    /**
     *
     * 获取错误信息
     * @return string
     */
    public function GetReturn_msg()
    {
        return $this->values['return_msg'];
    }

    /**
     *
     * 设置错误信息
     * @param string $return_msg
     * @return $this
     */
    public function SetReturn_msg($return_msg)
    {
        $this->values['return_msg'] = $return_msg;
        return $this;
    }

    /**
     * 判断返回信息，如非空，为错误原因签名失败参数格式校验错误是否存在
     * @return true 或 false
     **/
    public function IsReturn_msgSet()
    {
        return array_key_exists('return_msg', $this->values);
    }

    /**
     * 获取微信支付分配的终端设备号，商户自定义的值
     * @return string 值
     **/
    public function GetDevice_info()
    {
        return $this->values['device_info'];
    }

    /**
     * 设置微信支付分配的终端设备号，商户自定义
     * @param string $value
     * @return $this
     **/
    public function SetDevice_info($value)
    {
        $this->values['device_info'] = $value;
        return $this;
    }

    /**
     * 判断微信支付分配的终端设备号，商户自定义是否存在
     * @return true 或 false
     **/
    public function IsDevice_infoSet()
    {
        return array_key_exists('device_info', $this->values);
    }

    /**
     * 获取随机字符串，不长于32位。推荐随机数生成算法的值
     * @return string  值
     **/
    public function GetNonce_str()
    {
        return $this->values['nonce_str'];
    }

    /**
     * 设置随机字符串，不长于32位。推荐随机数生成算法
     * @param string $value
     * @return $this
     **/
    public function SetNonce_str($value)
    {
        $this->values['nonce_str'] = $value;
        return $this;
    }

    /**
     * 判断随机字符串，不长于32位。推荐随机数生成算法是否存在
     * @return true 或 false
     **/
    public function IsNonce_strSet()
    {
        return array_key_exists('nonce_str', $this->values);
    }


    /**
     * 获取商品或支付单简要描述的值
     * @return string 值
     **/
    public function GetBody()
    {
        return $this->values['body'];
    }

    /**
     * 设置商品或支付单简要描述
     * @param string $value
     * @return $this
     **/
    public function SetBody($value)
    {
        $this->values['body'] = $value;
        return $this;
    }

    /**
     * 判断商品或支付单简要描述是否存在
     * @return true 或 false
     **/
    public function IsBodySet()
    {
        return array_key_exists('body', $this->values);
    }

    /**
     *
     * 获取错误码 FAIL 或者 SUCCESS
     * @return string $return_code
     */
    public function GetReturn_code()
    {
        return $this->values['return_code'];
    }

    /**
     *
     * 设置错误码 FAIL 或者 SUCCESS
     * @param string
     * @return $this
     */
    public function SetReturn_code($return_code)
    {
        $this->values['return_code'] = $return_code;
        return $this;
    }

    /**
     * 判断SUCCESS/FAIL此字段是通信标识，非交易标识，交易是否成功需要查看trade_state来判断是否存在
     * @return true 或 false
     **/
    public function IsReturn_codeSet()
    {
        return array_key_exists('return_code', $this->values);
    }

    /**
     * 获取取值如下：JSAPI，NATIVE，APP，详细说明见参数规定的值
     * @return string 值
     **/
    public function GetTrade_type()
    {
        return $this->values['trade_type'];
    }

    /**
     * 设置取值如下：JSAPI，NATIVE，APP，详细说明见参数规定
     * @param string $value
     * @return $this
     **/
    public function SetTrade_type($value)
    {
        $this->values['trade_type'] = $value;
        return $this;
    }

    /**
     * 判断取值如下：JSAPI，NATIVE，APP，详细说明见参数规定是否存在
     * @return true 或 false
     **/
    public function IsTrade_typeSet()
    {
        return array_key_exists('trade_type', $this->values);
    }

    /**
     * 获取商品名称明细列表的值
     * @return string 值
     **/
    public function GetDetail()
    {
        return $this->values['detail'];
    }

    /**
     * 设置商品名称明细列表
     * @param string $value
     * @return $this
     **/
    public function SetDetail($value)
    {
        $this->values['detail'] = $value;
        return $this;
    }

    /**
     * 判断商品名称明细列表是否存在
     * @return true 或 false
     **/
    public function IsDetailSet()
    {
        return array_key_exists('detail', $this->values);
    }

    /**
     * 获取附加数据，在查询API和支付通知中原样返回，该字段主要用于商户携带订单的自定义数据的值
     * @return string 值
     **/
    public function GetAttach()
    {
        return $this->values['attach'];
    }

    /**
     * 设置附加数据，在查询API和支付通知中原样返回，该字段主要用于商户携带订单的自定义数据
     * @param string $value
     * @return $this
     **/
    public function SetAttach($value)
    {
        $this->values['attach'] = $value;
        return $this;
    }

    /**
     * 判断附加数据，在查询API和支付通知中原样返回，该字段主要用于商户携带订单的自定义数据是否存在
     * @return true 或 false
     **/
    public function IsAttachSet()
    {
        return array_key_exists('attach', $this->values);
    }

    /**
     * 获取商户系统内部的订单号,32个字符内、可包含字母, 其他说明见商户订单号的值
     * @return string 值
     **/
    public function GetOut_trade_no()
    {
        return $this->values['out_trade_no'];
    }

    /**
     * 设置商户系统内部的订单号,32个字符内、可包含字母, 其他说明见商户订单号
     * @param string $value
     * @return $this
     **/
    public function SetOut_trade_no($value)
    {
        $this->values['out_trade_no'] = $value;
        return $this;
    }

    /**
     * 判断商户系统内部的订单号,32个字符内、可包含字母, 其他说明见商户订单号是否存在
     * @return true 或 false
     **/
    public function IsOut_trade_noSet()
    {
        return array_key_exists('out_trade_no', $this->values);
    }

    /**
     * 设置符合ISO 4217标准的三位字母代码，默认人民币：CNY，其他值列表详见货币类型
     * @param string $value
     * @return $this
     **/
    public function SetFee_type($value)
    {
        $this->values['fee_type'] = $value;
        return $this;
    }

    /**
     * 获取符合ISO 4217标准的三位字母代码，默认人民币：CNY，其他值列表详见货币类型的值
     * @return string 值
     **/
    public function GetFee_type()
    {
        return $this->values['fee_type'];
    }

    /**
     * 判断符合ISO 4217标准的三位字母代码，默认人民币：CNY，其他值列表详见货币类型是否存在
     * @return true 或 false
     **/
    public function IsFee_typeSet()
    {
        return array_key_exists('fee_type', $this->values);
    }

    /**
     * 设置订单总金额，只能为整数，详见支付金额
     * @param string $value
     * @return $this
     **/
    public function SetTotal_fee($value)
    {
        $this->values['total_fee'] = $value;
        return $this;
    }

    /**
     * 获取订单总金额，只能为整数，详见支付金额的值
     * @return string 值
     **/
    public function GetTotal_fee()
    {
        return $this->values['total_fee'];
    }

    /**
     * 判断订单总金额，只能为整数，详见支付金额是否存在
     * @return true 或 false
     **/
    public function IsTotal_feeSet()
    {
        return array_key_exists('total_fee', $this->values);
    }

    /**
     * 获取APP和网页支付提交用户端ip，Native支付填调用微信支付API的机器IP。的值
     * @return string 值
     **/
    public function GetSpbill_create_ip()
    {
        return $this->values['spbill_create_ip'];
    }

    /**
     * 设置APP和网页支付提交用户端ip，Native支付填调用微信支付API的机器IP。
     * @param string $value
     * @return $this
     **/
    public function SetSpbill_create_ip($value)
    {
        $this->values['spbill_create_ip'] = $value;
        return $this;
    }

    /**
     * 判断APP和网页支付提交用户端ip，Native支付填调用微信支付API的机器IP。是否存在
     * @return true 或 false
     **/
    public function IsSpbill_create_ipSet()
    {
        return array_key_exists('spbill_create_ip', $this->values);
    }

    /**
     * 设置订单生成时间，格式为yyyyMMddHHmmss，如2009年12月25日9点10分10秒表示为20091225091010。其他详见时间规则
     * @param string $value
     * @return $this
     **/
    public function SetTime_start($value)
    {
        $this->values['time_start'] = $value;
        return $this;
    }

    /**
     * 获取订单生成时间，格式为yyyyMMddHHmmss，如2009年12月25日9点10分10秒表示为20091225091010。其他详见时间规则的值
     * @return string 值
     **/
    public function GetTime_start()
    {
        return $this->values['time_start'];
    }

    /**
     * 判断订单生成时间，格式为yyyyMMddHHmmss，如2009年12月25日9点10分10秒表示为20091225091010。其他详见时间规则是否存在
     * @return true 或 false
     **/
    public function IsTime_startSet()
    {
        return array_key_exists('time_start', $this->values);
    }

    /**
     * 设置订单失效时间，格式为yyyyMMddHHmmss，如2009年12月27日9点10分10秒表示为20091227091010。其他详见时间规则
     * @param string $value
     * @return $this
     **/
    public function SetTime_expire($value)
    {
        $this->values['time_expire'] = $value;
        return $this;
    }

    /**
     * 获取订单失效时间，格式为yyyyMMddHHmmss，如2009年12月27日9点10分10秒表示为20091227091010。其他详见时间规则的值
     * @return string 值
     **/
    public function GetTime_expire()
    {
        return $this->values['time_expire'];
    }

    /**
     * 判断订单失效时间，格式为yyyyMMddHHmmss，如2009年12月27日9点10分10秒表示为20091227091010。其他详见时间规则是否存在
     * @return true 或 false
     **/
    public function IsTime_expireSet()
    {
        return array_key_exists('time_expire', $this->values);
    }

    /**
     * 设置商品标记，代金券或立减优惠功能的参数，说明详见代金券或立减优惠
     * @param string $value
     * @return $this
     **/
    public function SetGoods_tag($value)
    {
        $this->values['goods_tag'] = $value;
        return $this;
    }

    /**
     * 获取商品标记，代金券或立减优惠功能的参数，说明详见代金券或立减优惠的值
     * @return string 值
     **/
    public function GetGoods_tag()
    {
        return $this->values['goods_tag'];
    }

    /**
     * 判断商品标记，代金券或立减优惠功能的参数，说明详见代金券或立减优惠是否存在
     * @return true 或 false
     **/
    public function IsGoods_tagSet()
    {
        return array_key_exists('goods_tag', $this->values);
    }

    /**
     * 获取接收微信支付异步通知回调地址的值
     * @return string 值
     **/
    public function GetNotify_url()
    {
        return $this->values['notify_url'];
    }

    /**
     * 设置接收微信支付异步通知回调地址
     * @param string $value
     * @return $this
     **/
    public function SetNotify_url($value)
    {
        $this->values['notify_url'] = $value;
        return $this;
    }

    /**
     * 判断接收微信支付异步通知回调地址是否存在
     * @return true 或 false
     **/
    public function IsNotify_urlSet()
    {
        return array_key_exists('notify_url', $this->values);
    }

    /**
     * 设置trade_type=NATIVE，此参数必传。此id为二维码中包含的商品ID，商户自行定义。
     * @param string $value
     * @return $this
     **/
    public function SetProduct_id($value)
    {
        $this->values['product_id'] = $value;
        return $this;
    }

    /**
     * 获取trade_type=NATIVE，此参数必传。此id为二维码中包含的商品ID，商户自行定义。的值
     * @return string 值
     **/
    public function GetProduct_id()
    {
        return $this->values['product_id'];
    }

    /**
     * 判断trade_type=NATIVE，此参数必传。此id为二维码中包含的商品ID，商户自行定义。是否存在
     * @return true 或 false
     **/
    public function IsProduct_idSet()
    {
        return array_key_exists('product_id', $this->values);
    }

    /**
     * 设置trade_type=JSAPI，此参数必传，用户在商户appid下的唯一标识。下单前需要调用【网页授权获取用户信息】接口获取到用户的Openid。
     * @param string $value
     * @return $this
     **/
    public function SetOpenid($value)
    {
        $this->values['openid'] = $value;
        return $this;
    }

    /**
     * 获取trade_type=JSAPI，此参数必传，用户在商户appid下的唯一标识。下单前需要调用【网页授权获取用户信息】接口获取到用户的Openid。 的值
     * @return string 值
     **/
    public function GetOpenid()
    {
        return $this->values['openid'];
    }

    /**
     * 判断trade_type=JSAPI，此参数必传，用户在商户appid下的唯一标识。下单前需要调用【网页授权获取用户信息】接口获取到用户的Openid。 是否存在
     * @return true 或 false
     **/
    public function IsOpenidSet()
    {
        return array_key_exists('openid', $this->values);
    }

    /**
     * 获取微信的订单号，优先使用的值
     * @return string 值
     **/
    public function GetTransaction_id()
    {
        return $this->values['transaction_id'];
    }

    /**
     * 设置微信的订单号，优先使用
     * @param string $value
     * @return $this
     **/
    public function SetTransaction_id($value)
    {
        $this->values['transaction_id'] = $value;
        return $this;
    }

    /**
     * 判断微信的订单号，优先使用是否存在
     * @return true 或 false
     **/
    public function IsTransaction_idSet()
    {
        return array_key_exists('transaction_id', $this->values);
    }

    /**
     * 获取微信分配的公众账号ID的值
     * @return string 值
     **/
    public function GetAppid()
    {
        return $this->values['appid'];
    }

    /**
     * 设置微信分配的公众账号ID
     * @param string $value
     *
     * @return $this
     */
    public function SetAppid($value)
    {
        $this->APPID = $value;
        $this->values['appid'] = $value;
        return $this;
    }

    /**
     * 判断微信分配的公众账号ID是否存在
     * @return true 或 false
     **/
    public function IsAppidSet()
    {
        return array_key_exists('appid', $this->values);
    }

    /**
     * 获取微信支付分配的商户号的值
     * @return string 值
     **/
    public function GetMch_id()
    {
        return $this->values['mch_id'];
    }

    /**
     * 设置微信支付分配的商户号
     * @param string $value
     * @return $this
     **/
    public function SetMch_id($value)
    {
        $this->MCHID = $value;
        $this->values['mch_id'] = $value;
        return $this;
    }

    /**
     * 判断微信支付分配的商户号是否存在
     * @return true 或 false
     **/
    public function IsMch_idSet()
    {
        return array_key_exists('mch_id', $this->values);
    }

    ///////


    /**
     * 设置商户系统内部的退款单号，商户系统内部唯一，同一退款单号多次请求只退一笔
     * @param string $value
     * @return $this
     **/
    public function SetOut_refund_no($value)
    {
        $this->values['out_refund_no'] = $value;
        return $this;
    }

    /**
     * 获取商户系统内部的退款单号，商户系统内部唯一，同一退款单号多次请求只退一笔的值
     * @return string 值
     **/
    public function GetOut_refund_no()
    {
        return $this->values['out_refund_no'];
    }

    /**
     * 判断商户系统内部的退款单号，商户系统内部唯一，同一退款单号多次请求只退一笔是否存在
     * @return true 或 false
     **/
    public function IsOut_refund_noSet()
    {
        return array_key_exists('out_refund_no', $this->values);
    }

    /**
     * 设置退款总金额，订单总金额，单位为分，只能为整数，详见支付金额
     * @param string $value
     * @return $this
     **/
    public function SetRefund_fee($value)
    {
        $this->values['refund_fee'] = $value;
        return $this;
    }

    /**
     * 获取退款总金额，订单总金额，单位为分，只能为整数，详见支付金额的值
     * @return string 值
     **/
    public function GetRefund_fee()
    {
        return $this->values['refund_fee'];
    }

    /**
     * 判断退款总金额，订单总金额，单位为分，只能为整数，详见支付金额是否存在
     * @return true 或 false
     **/
    public function IsRefund_feeSet()
    {
        return array_key_exists('refund_fee', $this->values);
    }

    /**
     * 设置货币类型，符合ISO 4217标准的三位字母代码，默认人民币：CNY，其他值列表详见货币类型
     * @param string $value
     * @return $this
     **/
    public function SetRefund_fee_type($value)
    {
        $this->values['refund_fee_type'] = $value;
        return $this;
    }

    /**
     * 获取货币类型，符合ISO 4217标准的三位字母代码，默认人民币：CNY，其他值列表详见货币类型的值
     * @return string 值
     **/
    public function GetRefund_fee_type()
    {
        return $this->values['refund_fee_type'];
    }

    /**
     * 判断货币类型，符合ISO 4217标准的三位字母代码，默认人民币：CNY，其他值列表详见货币类型是否存在
     * @return true 或 false
     **/
    public function IsRefund_fee_typeSet()
    {
        return array_key_exists('refund_fee_type', $this->values);
    }

    /**
     * 获取操作员帐号, 默认为商户号的值
     * @return string 值
     **/
    public function GetOp_user_id()
    {
        return $this->values['op_user_id'];
    }

    /**
     * 设置操作员帐号, 默认为商户号
     * @param string $value
     * @return $this
     **/
    public function SetOp_user_id($value = '')
    {
        if ( $value == '' ) {
            $this->values['op_user_id'] = $this->GetMch_id();
        } else {
            $this->values['op_user_id'] = $value;
        }

        return $this;
    }

    /**
     * 判断操作员帐号, 默认为商户号是否存在
     * @return true 或 false
     **/
    public function IsOp_user_idSet()
    {
        return array_key_exists('op_user_id', $this->values);
    }

    /**
     * 获取微信退款单号refund_id、out_refund_no、out_trade_no、transaction_id四个参数必填一个，如果同时存在优先级为：refund_id>out_refund_no>transaction_id>out_trade_no的值
     * @return string 值
     **/
    public function GetRefund_id()
    {
        return $this->values['refund_id'];
    }

    /**
     * 设置微信退款单号refund_id、out_refund_no、out_trade_no、transaction_id四个参数必填一个，如果同时存在优先级为：refund_id>out_refund_no>transaction_id>out_trade_no
     * @param string $value
     * @return $this
     **/
    public function SetRefund_id($value)
    {
        $this->values['refund_id'] = $value;
        return $this;
    }

    /**
     * 判断微信退款单号refund_id、out_refund_no、out_trade_no、transaction_id四个参数必填一个，如果同时存在优先级为：refund_id>out_refund_no>transaction_id>out_trade_no是否存在
     * @return true 或 false
     **/
    public function IsRefund_idSet()
    {
        return array_key_exists('refund_id', $this->values);
    }

    /**
     * 设置下载对账单的日期，格式：20140603
     * @param string $value
     * @return $this
     **/
    public function SetBill_date($value)
    {
        $this->values['bill_date'] = $value;
        return $this;
    }

    /**
     * 获取下载对账单的日期，格式：20140603的值
     * @return  string 值
     **/
    public function GetBill_date()
    {
        return $this->values['bill_date'];
    }

    /**
     * 判断下载对账单的日期，格式：20140603是否存在
     * @return true 或 false
     **/
    public function IsBill_dateSet()
    {
        return array_key_exists('bill_date', $this->values);
    }

    /**
     * 设置ALL，返回当日所有订单信息，默认值SUCCESS，返回当日成功支付的订单REFUND，返回当日退款订单REVOKED，已撤销的订单
     * @param string $value
     * @return $this
     **/
    public function SetBill_type($value)
    {
        $this->values['bill_type'] = $value;
        return $this;
    }

    /**
     * 获取ALL，返回当日所有订单信息，默认值SUCCESS，返回当日成功支付的订单REFUND，返回当日退款订单REVOKED，已撤销的订单的值
     * @return  string 值
     **/
    public function GetBill_type()
    {
        return $this->values['bill_type'];
    }

    /**
     * 判断ALL，返回当日所有订单信息，默认值SUCCESS，返回当日成功支付的订单REFUND，返回当日退款订单REVOKED，已撤销的订单是否存在
     * @return true 或 false
     **/
    public function IsBill_typeSet()
    {
        return array_key_exists('bill_type', $this->values);
    }

    /**
     * 获取上报对应的接口的完整URL，类似：https://api.mch.weixin.qq.com/pay/unifiedorder对于被扫支付，为更好的和商户共同分析一次业务行为的整体耗时情况，对于两种接入模式，请都在门店侧对一次被扫行为进行一次单独的整体上报，上报URL指定为：https://api.mch.weixin.qq.com/pay/micropay/total关于两种接入模式具体可参考本文档章节：被扫支付商户接入模式其它接口调用仍然按照调用一次，上报一次来进行。的值
     * @return  string 值
     **/
    public function GetInterface_url()
    {
        return $this->values['interface_url'];
    }

    /**
     * 设置上报对应的接口的完整URL，类似：https://api.mch.weixin.qq.com/pay/unifiedorder对于被扫支付，为更好的和商户共同分析一次业务行为的整体耗时情况，对于两种接入模式，请都在门店侧对一次被扫行为进行一次单独的整体上报，上报URL指定为：https://api.mch.weixin.qq.com/pay/micropay/total关于两种接入模式具体可参考本文档章节：被扫支付商户接入模式其它接口调用仍然按照调用一次，上报一次来进行。
     * @param string $value
     * @return $this
     **/
    public function SetInterface_url($value)
    {
        $this->values['interface_url'] = $value;
        return $this;
    }

    /**
     * 判断上报对应的接口的完整URL，类似：https://api.mch.weixin.qq.com/pay/unifiedorder对于被扫支付，为更好的和商户共同分析一次业务行为的整体耗时情况，对于两种接入模式，请都在门店侧对一次被扫行为进行一次单独的整体上报，上报URL指定为：https://api.mch.weixin.qq.com/pay/micropay/total关于两种接入模式具体可参考本文档章节：被扫支付商户接入模式其它接口调用仍然按照调用一次，上报一次来进行。是否存在
     * @return true 或 false
     **/
    public function IsInterface_urlSet()
    {
        return array_key_exists('interface_url', $this->values);
    }

    /**
     * 获取接口耗时情况，单位为毫秒的值
     * @return  string 值
     **/
    public function GetExecute_time_()
    {
        return $this->values['execute_time_'];
    }

    /**
     * 设置接口耗时情况，单位为毫秒
     * @param string $value
     * @return $this
     **/
    public function SetExecute_time_($value)
    {
        $this->values['execute_time_'] = $value;
        return $this;
    }

    /**
     * 判断接口耗时情况，单位为毫秒是否存在
     * @return true 或 false
     **/
    public function IsExecute_time_Set()
    {
        return array_key_exists('execute_time_', $this->values);
    }

    /**
     * 获取SUCCESS/FAIL的值
     * @return  string 值
     **/
    public function GetResult_code()
    {
        return $this->values['result_code'];
    }

    /**
     * 设置SUCCESS/FAIL
     * @param string $value
     * @return $this
     **/
    public function SetResult_code($value)
    {
        $this->values['result_code'] = $value;
        return $this;
    }

    /**
     * 判断SUCCESS/FAIL是否存在
     * @return true 或 false
     **/
    public function IsResult_codeSet()
    {
        return array_key_exists('result_code', $this->values);
    }

    /**
     * 获取ORDERNOTEXIST—订单不存在SYSTEMERROR—系统错误的值
     * @return  string 值
     **/
    public function GetErr_code()
    {
        return $this->values['err_code'];
    }

    /**
     * 设置ORDERNOTEXIST—订单不存在SYSTEMERROR—系统错误
     * @param string $value
     * @return $this
     **/
    public function SetErr_code($value)
    {
        $this->values['err_code'] = $value;
        return $this;
    }

    /**
     * 判断ORDERNOTEXIST—订单不存在SYSTEMERROR—系统错误是否存在
     * @return true 或 false
     **/
    public function IsErr_codeSet()
    {
        return array_key_exists('err_code', $this->values);
    }

    /**
     * 获取结果信息描述的值
     * @return  string 值
     **/
    public function GetErr_code_des()
    {
        return $this->values['err_code_des'];
    }

    /**
     * 设置结果信息描述
     * @param string $value
     * @return $this
     **/
    public function SetErr_code_des($value)
    {
        $this->values['err_code_des'] = $value;
        return $this;
    }

    /**
     * 判断结果信息描述是否存在
     * @return true 或 false
     **/
    public function IsErr_code_desSet()
    {
        return array_key_exists('err_code_des', $this->values);
    }

    /**
     * 获取系统时间，格式为yyyyMMddHHmmss，如2009年12月27日9点10分10秒表示为20091227091010。其他详见时间规则的值
     * @return  string 值
     **/
    public function GetTime()
    {
        return $this->values['time'];
    }

    /**
     * 设置系统时间，格式为yyyyMMddHHmmss，如2009年12月27日9点10分10秒表示为20091227091010。其他详见时间规则
     * @param string $value
     * @return $this
     **/
    public function SetTime($value)
    {
        $this->values['time'] = $value;
        return $this;
    }

    /**
     * 判断系统时间，格式为yyyyMMddHHmmss，如2009年12月27日9点10分10秒表示为20091227091010。其他详见时间规则是否存在
     * @return true 或 false
     **/
    public function IsTimeSet()
    {
        return array_key_exists('time', $this->values);
    }

    /**
     * 获取需要转换的URL，签名用原串，传输需URL encode的值
     * @return  string 值
     **/
    public function GetLong_url()
    {
        return $this->values['long_url'];
    }

    /**
     * 设置需要转换的URL，签名用原串，传输需URL encode
     * @param string $value
     * @return $this
     **/
    public function SetLong_url($value)
    {
        $this->values['long_url'] = $value;
        return $this;
    }

    /**
     * 判断需要转换的URL，签名用原串，传输需URL encode是否存在
     * @return true 或 false
     **/
    public function IsLong_urlSet()
    {
        return array_key_exists('long_url', $this->values);
    }

    /**
     * 设置扫码支付授权码，设备读取用户微信中的条码或者二维码信息
     * @param string $value
     * @return $this
     **/
    public function SetAuth_code($value)
    {
        $this->values['auth_code'] = $value;
        return $this;
    }

    /**
     * 获取扫码支付授权码，设备读取用户微信中的条码或者二维码信息的值
     * @return  string 值
     **/
    public function GetAuth_code()
    {
        return $this->values['auth_code'];
    }

    /**
     * 判断扫码支付授权码，设备读取用户微信中的条码或者二维码信息是否存在
     * @return true 或 false
     **/
    public function IsAuth_codeSet()
    {
        return array_key_exists('auth_code', $this->values);
    }


    /**
     * 获取支付时间戳的值
     * @return string  值
     **/
    public function GetTimeStamp()
    {
        return $this->values['timeStamp'];
    }

    /**
     * 设置支付时间戳
     * @param string $value
     * @return $this
     **/
    public function SetTimeStamp($value)
    {
        $this->values['timeStamp'] = $value;
        return $this;
    }

    /**
     * 判断支付时间戳是否存在
     * @return true 或 false
     **/
    public function IsTimeStampSet()
    {
        return array_key_exists('timeStamp', $this->values);
    }

    /**
     * 随机字符串
     * @param string $value
     * @return $this
     **/
    public function SetNonceStr($value)
    {
        $this->values['nonceStr'] = $value;
        return $this;
    }

    /**
     * 设置订单详情扩展字符串
     * @param string $value
     * @return $this
     **/
    public function SetPackage($value)
    {
        $this->values['package'] = $value;
        return $this;
    }

    /**
     * 获取订单详情扩展字符串的值
     * @return  string 值
     **/
    public function GetPackage()
    {
        return $this->values['package'];
    }

    /**
     * 判断订单详情扩展字符串是否存在
     * @return true 或 false
     **/
    public function IsPackageSet()
    {
        return array_key_exists('package', $this->values);
    }

    /**
     * 设置签名方式
     * @param string $value
     * @return $this
     **/
    public function SetSignType($value)
    {
        $this->values['signType'] = $value;
        return $this;
    }

    /**
     * 获取签名方式
     * @return  string 值
     **/
    public function GetSignType()
    {
        return $this->values['signType'];
    }

    /**
     * 判断签名方式是否存在
     * @return true 或 false
     **/
    public function IsSignTypeSet()
    {
        return array_key_exists('signType', $this->values);
    }

    /**
     * 设置签名方式
     * @param string $value
     * @return $this
     **/
    public function SetPaySign($value)
    {
        $this->values['paySign'] = $value;
        return $this;
    }

    /**
     * 获取签名方式
     * @return  string 值
     **/
    public function GetPaySign()
    {
        return $this->values['paySign'];
    }

    /**
     * 判断签名方式是否存在
     * @return true 或 false
     **/
    public function IsPaySignSet()
    {
        return array_key_exists('paySign', $this->values);
    }

    /**
     * 获取支付时间戳的值
     * @return string  值
     **/
    public function GetTime_stamp()
    {
        return $this->values['time_stamp'];
    }

    /**
     * 设置支付时间戳
     * @param string $value
     * @return $this
     **/
    public function SetTime_stamp($value)
    {
        $this->values['time_stamp'] = $value;
        return $this;
    }

    /**
     * 判断支付时间戳是否存在
     * @return true 或 false
     **/
    public function IsTime_stampSet()
    {
        return array_key_exists('time_stamp', $this->values);
    }

}


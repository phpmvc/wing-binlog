<?php namespace Seals\Web;
use Seals\Web\Logic\User;

/**
 * Created by PhpStorm.
 * User: yuyi
 * Date: 17/3/13
 * Time: 12:55
 */
class HttpResponse
{
    protected $method;
    protected $host = "";
    protected $port = 80;
    protected $resource;
    protected $http_protocol;
    protected $buffer;
    protected $home;
    protected $http;
    protected $id;
    protected $client;

    protected $get     = [];
    protected $post    = [];
    protected $headers = [];
    protected $debug = false;

    protected static $static_files = [];

    public function __construct(Http $http,$home, $buffer, $data, $client)
    {
        $this->buffer = $buffer;
        $this->home   = $home;
        $this->http   = $http;
        $this->client = $client;

        $temp    = explode("\r\n\r\n", $data, 2);
        $headers = isset($temp[0])?$temp[0]:"";
        $content = isset($temp[1])?$temp[1]:"";
        unset($temp);

        $headers = explode("\r\n", $headers);
        $line1   = array_shift($headers);
        $temp    = explode(" ", $line1);
        unset($line1);

        $this->method        = isset($temp[0])?$temp[0]:"unknown";
        $resource            = isset($temp[1])?$temp[1]:"";
        $this->http_protocol = isset($temp[2])?$temp[2]:"unknown";
        unset($temp);

        foreach ($headers as $header) {
            $temp   = explode(":",$header,2);
            $key    = isset($temp[0])?$temp[0]:"";
            $value  = isset($temp[1])?$temp[1]:"";
            unset($temp);
            $this->headers[trim(strtolower($key))] = trim($value);
        }

        if (isset($headers[0])) {
            $temp = explode(":",$headers[0]);
            $this->host = isset($temp[1])?trim($temp[1]):"";
            $this->port = isset($temp[2])?trim($temp[2]):80;
            unset($temp);
        }
        unset($headers);

        if (!$this->port) {
            $this->port = 80;
        }

        $arr = parse_url($resource);
        $this->resource = isset($arr["path"])?$arr["path"]:"";

        //get参数解析
        if (isset($arr["query"])) {
            $query  = $arr["query"];
            $querys = preg_split("/\&+/", $query);
            unset($query);

            if (is_array($querys)) {
                foreach ($querys as $query) {
                    $query = trim($query);
                    list($key, $value) = explode("=", $query);
                    unset($query);
                    $this->get[$key] = $value;
                }
            }
            unset($querys);
        }
        unset($arr);

        //post数据解析
        if ($content) {
            if (preg_match("/--------------------------[\S\s]{1,}?\n/",$content)) {
                $querys = preg_split("/--------------------------[\S\s]{1,}?\n/", $content);
                foreach ($querys as $query) {

                    if (!$query) {
                        continue;
                    }

                    $query = trim($query);
                    $temp = explode("\r\n\r\n", $query);

                    preg_match("/\"[\s\S]{1,}?\"/", $temp[0], $m);

                    $key = trim($m[0], "\"");
                    $this->post[$key] = isset($temp[1]) ? $temp[1] : "";
                    unset($temp, $key, $query);
                }
                unset($querys);
            } else {
                $querys = preg_split("/\&+/", $content);
                unset($query);

                foreach ($querys as $query) {
                    if (!$query)
                        continue;
                    $query = trim($query);
                    list($key, $value) = explode("=", $query);
                    unset($query);
                    $this->post[$key] = $value;
                }
                unset($querys);
            }
        }
    }

    public function setDebug($debug)
    {
        $this->debug = !!$debug;
    }

    public function get($key)
    {
        if (!isset($this->get[$key]))
            return null;
        return $this->get[$key];
    }

    public function getAll()
    {
        return $this->get;
    }

    public function getMethod()
    {
        return strtolower(trim($this->method));
    }

    public function post($key)
    {
        if (!isset($this->post[$key]))
            return null;
        return $this->post[$key];
    }

    public function postAll()
    {
        return $this->post;
    }

    public function request($key)
    {
        $data = array_merge($this->get,$this->post);
        if (!isset($data[$key]))
            return null;
        return $data[$key];
    }

    public function getResource()
    {
        return $this->resource;
    }

    public function getProtocol()
    {
        return $this->http_protocol;
    }

    public function getHost()
    {
        return $this->host;
    }
    public function getPort()
    {
        return $this->port;
    }

    public function getHeader($key)
    {
        if (!isset($this->headers[$key]))
            return null;
        return $this->headers[$key];
    }

    public function getHeaders()
    {
        return $this->headers;
    }

    public function isAjax(){
         //X-Requested-With: XMLHttpRequest
        return !!$this->getHeader(strtolower("X-Requested-With"));
    }

    public function getCookie($_key)
    {
        if (!isset($this->headers["cookie"]))
            return null;

        $cookies = explode(";", $this->headers["cookie"]);
        foreach ($cookies as $cookie) {
            list($key,$value) = explode("=",$cookie);

            $key   = trim($key);
            $value = trim($value);

            if ($key == $_key)
                return $value;
        }
        return null;
    }

    public function getCookies()
    {
        if (!isset($this->headers["cookie"]))
            return null;

        $cookies = explode(";", $this->headers["cookie"]);
        $res     = [];

        foreach ($cookies as $cookie) {
            list($key,$value) = explode("=",$cookie);
            $key      = trim($key);
            $value    = trim($value);
            $res[$key]= $value;
        }
        return $res;
    }

    public function getAccepts()
    {
        if (!isset($this->headers["accept"]))
            return null;
        return explode(",",$this->headers["accept"]);
    }

    public function response()
    {
        $response  = "404 not fund";
        $resource  = $this->getResource();

        if (!$resource || $resource == "/")
            $resource = "/index.php";

        $mime_type = "text/html";

        $is_ajax  = $this->isAjax();
        $_GET     = $this->getAll();
        $_POST    = $this->postAll();
        $_REQUEST = array($_GET,$_POST);
        $_COOKIE  = $this->getCookies();

        //check is login
        $appid       = $this->getCookie("wing-binlog-appid");
        $token       = $this->getCookie("wing-binlog-token");
        $check_token = User::checkToken($appid, $token);
        unset($appid, $token);

        $status_code   = "HTTP/1.1 200 OK";
        $cache_control = "Cache-control: max-age=".(86400*30).",private,must-revalidation";


        $lang = $this->get("lang");
        if (!$lang)
            $lang = \Seals\Library\Context::instance()->lang;

        if (!in_array($lang, \Seals\Library\Lang::$ltypes))
            $lang = "zh";

        $_GET["lang"] = $lang;
        \Seals\Library\Context::instance()->lang = $lang;

        $home_path = $this->home."/lang/".$lang;
        echo $home_path . $resource,"\r\n";

        do {
            //try to visit ../ dir, do safe filter and return 404 page
            if (strpos($resource, "..") !== false) {
                echo "try to visit ..\r\n";
                if (isset(self::$static_files[$home_path . "/404.html"])) {
                    echo "404 page static\r\n";
                    $response    = self::$static_files[$home_path . "/404.html"]["content"];
                    $mime_type   = self::$static_files[$home_path . "/404.html"]["mime"];
                    //$status_code = "HTTP/1.1 304 Not Modified";
                    break;
                }

                echo "404 page\r\n";

                //else response 404 page
                ob_start();
                include $home_path . "/404.html";
                $response = ob_get_contents();
                ob_end_clean();

                self::$static_files[$home_path . "/404.html"] = [
                    "content" => $response,
                    "mime" => "text/html",
                ];
                break;
            }

            //check access power
            if ($check_token && !Route::access($this)) {
                echo "login, have not access power\r\n";

                if ($is_ajax) {
                    echo "ajax\r\n";
                    $cache_control = "Cache-control: max-age=0,private,must-revalidation";
                    $response      = json_encode(["error_code" => 403, "error_msg" => "not allow access"]);
                    break;
                }

                if (isset(self::$static_files[$home_path . "/403.html"])) {
                    echo "403 page static\r\n";
                    $response  = self::$static_files[$home_path . "/403.html"]["content"];
                    $mime_type = self::$static_files[$home_path . "/403.html"]["mime"];
                    //$status_code = "HTTP/1.1 304 Not Modified";
                    break;
                }

                echo "403 page\r\n";

                //else response 404 page
                ob_start();
                include $home_path . "/403.html";
                $response = ob_get_contents();
                ob_end_clean();

                self::$static_files[$home_path . "/403.html"] = [
                    "content" => $response,
                    "mime" => "text/html",
                ];
                break;
            }

            //if file exists
            if (file_exists($home_path . $resource)) {
                echo "file exists\r\n";
                //get from cache
                if (isset(self::$static_files[$home_path . $resource])) {
                    echo "static file cache\r\n";
                    $response  = self::$static_files[$home_path . $resource]["content"];
                    $mime_type = self::$static_files[$home_path . $resource]["mime"];
                    break;
                }

                //parse
                $mime_type = MimeType::getMimeType($home_path . $resource);
                if ($mime_type == "text/x-php") {
                    echo "php file\r\n";

                    $cache_control = "Cache-control: max-age=0,private,must-revalidation";

                    ob_start();
                    if ($check_token) {
                       // echo "login\r\n";
                        include $home_path . $resource;
                        $response = ob_get_contents();
                    } else {
                        //echo "not login";
                        include $home_path . "/login.php";
                        $response = ob_get_contents();
                    }
                    ob_end_clean();
                    $mime_type = "text/html";
                    break;
                }

                echo "static file\r\n";

                $cache_control = "Cache-control: max-age=".(86400*30).",private,must-revalidation";
                $response      = file_get_contents($home_path . $resource);
                //set cache
                self::$static_files[$home_path . $resource] = [
                    "content" => $response,
                    "mime"    => $mime_type,
                ];
                unset($check_token);
                break;
            }

            //if is login and has a route
            if ($check_token && Route::hasRoute($this->getMethod(), $resource)) {
                echo "login route\r\n";
                $cache_control = "Cache-control: max-age=0,private,must-revalidation";
                $route    = new Route($this, $resource);
                $response = $route->parse();
                unset($route);
                break;
            }

            //if is login and ajax
            if ($check_token && $is_ajax) {
                echo "login ajax\r\n";
                $cache_control = "Cache-control: max-age=0,private,must-revalidation";
                $response = json_encode(["error_code" => 404, "error_msg" => "request not found"]);
                break;
            }

            //if is not login and ajax
            if (!$check_token && $is_ajax) {
                echo "not login ajax\r\n";
                $cache_control = "Cache-control: max-age=0,private,must-revalidation";
                $response = json_encode(["error_code" => 4000, "error_msg" => "请重新登录，<a href='/login.php'>去登陆</a>"]);
                break;
            }

            if (isset(self::$static_files[$home_path . "/404.html"])) {
                echo "static 404 page --\r\n";
                $response  = self::$static_files[$home_path . "/404.html"]["content"];
                $mime_type = self::$static_files[$home_path . "/404.html"]["mime"];
                break;
            }

            echo "static 404 page -- last\r\n";

            //else response 404 page
            ob_start();
            include $home_path . "/404.html";
            $response = ob_get_contents();
            ob_end_clean();

            self::$static_files[$home_path . "/404.html"] = [
                "content" => $response,
                "mime" => "text/html",
            ];

        } while (0);

        unset($_GET, $_POST, $_REQUEST, $_COOKIE);

        $headers            = [
            $status_code,
            $cache_control,
            "Connection: keep-alive",
            "Server: wing-binlog-http by yuyi,297341015@qq.com,jilieryuyi@gmail.com,QQ group,535218312",
            "Date: " . gmdate("D,d M Y H:m:s")." GMT",
            "Content-Type: ".$mime_type,
            "Content-Length: " . strlen($response)
        ];

        return $this->http->send($this->buffer, implode("\r\n",$headers)."\r\n\r\n".$response, $this->client);
    }
}
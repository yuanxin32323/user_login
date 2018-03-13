<?php

//登录注册登录的实现类
class user {

    //当前访问的资源方法
    private $action;
    //数据库驱动
    private $model;
    //jwt权限配置
    private $jwt_config = [
        'from' => 'cityhome', //签发者
        'solt' => 'testlogin', //加盐 , 不同的用户系统用不同的solt
        'type' => 'HS256', //签名加密方式
        'exp' => 24 * 3600, //默认有效期
    ];
    //medoo数据库驱动配置
    private $database_config = [
        'database_type' => 'mysql',
        'database_name' => 'user',
        'server' => 'localhost',
        'username' => 'root',
        'password' => 'root',
        'charset' => 'utf8',
        'port' => 3306,
    ];

    /**
     * 运行
     */
    public function run() {
        $action = $this->action;
        $this->$action();
    }

    /**
     * 初始化，用于判断用户登录状态
     */
    public function __construct() {
        //创建数据库驱动
        $this->model = new \Medoo\Medoo($this->database_config);
        //判断用户请求的资源是否存在
        $this->action = $this->getParam('action');
        if (!$this->action) {

            $this->json([
                'error' => 1,
                'msg' => '找不到访问的资源方法'
            ]);
        }
        //定义不需要登录验证的方法
        $arr = [
            'login',
            'reg'
        ];
        if (!in_array($this->action, $arr)) {
            $uid = $this->check_login();
            if (!$uid) {

                $this->json([
                    'error' => -1,
                    'msg' => '未登录'
                ]);
            } else {
                $result = $this->model->select('user', '*', [
                    'uid' => $uid
                ]);
                if ($result) {
                    $GLOBALS['user'] = current($result);
                } else {
                    $this->json([
                        'error' => -1,
                        'msg' => '未登录'
                    ]);
                }
            }
        }
    }

    /**
     * 验证登录，如果是登录状态则返回用户uid
     * @return boolean
     */
    private function check_login() {
        $jwt = $this->getParam('auth_token');
        if (!$jwt) {
            return FALSE;
        }
        $config = $this->jwt_config;

        //jwt权限验证
        try {
            $validator = new \Gamegos\JWT\Validator();
            $token = $validator->validate($jwt, $config['solt']);
            return $token->getClaims()['sub'];
        } catch (\Gamegos\JWT\Exception\JWTException $e) {

            return FALSE;
        }
    }

    /**
     * 登录
     */
    public function login() {
        $username = $this->getParam('username');
        $password = $this->getParam('password');
        if (!$username || !$password) {
            $this->json([
                'error' => 1,
                'msg' => '请输入账号密码'
            ]);
        }

        $result = $this->model->select('user', '*', [
            'username' => $username,
            'password' => md5($password),
        ]);
        if (!$result) {
            $this->json([
                'error' => 1,
                'msg' => '账号密码错误'
            ]);
        } else {
            $info = current($result);
        }
        $key = 'logintest';

        $token = new \Gamegos\JWT\Token();
        $token->setClaim('sub', $info['uid']); //用户

        $token->setClaim('iss', $this->jwt_config['from']); //签发者
        $token->setClaim('iat', time()); //签发时间

        $token->setClaim('nbf', time()); //在此时间前不可被接收处理
        $token->setClaim('exp', time() + $this->jwt_config['exp']); //有效期
        $token->setClaim('jti', md5(uniqid())); //jwt唯一id
        $encoder = new \Gamegos\JWT\Encoder();
        $encoder->encode($token, $this->jwt_config['solt'], $this->jwt_config['type']);

        $this->json([
            'error' => 0,
            'msg' => '登录成功',
            'auth_token' => $token->getJWT(),
        ]);
    }

    /**
     * 注册
     */
    public function reg() {
        $username = $this->getParam('username');
        $password = $this->getParam('password');
        if (!$username || !$password) {
            $this->json([
                'error' => 1,
                'msg' => '请输入账号密码'
            ]);
        }
        $result = $this->model->select('user', '*', [
            'username' => $username,
        ]);
        if ($result) {
            $this->json([
                'error' => 1,
                'msg' => '账号已存在'
            ]);
        }
        $this->model->insert('user', [
            'username' => $username,
            'password' => md5($password)
        ]);
        $this->json([
            'error' => 0,
            'msg' => '注册成功',
            'val' => [
                'username' => $username,
                'password' => $password
            ]
        ]);
    }

    /**
     * 修改账号密码
     */
    public function edit() {
        $old = $this->getParam('old');
        $new = $this->getParam('new');
        if (!$old || !$new) {
            $this->json([
                'error' => 1,
                'msg' => '请输入新旧密码'
            ]);
        }

        if ($GLOBALS['user']['password'] != md5($old)) {
            $this->json([
                'error' => 1,
                'msg' => '旧密码错误'
            ]);
        }

        $this->model->update('user', [
            'password' => md5($new)
                ], [
            'uid' => $GLOBALS['user']['uid']
        ]);
        $this->json([
            'error' => 0,
            'msg' => '修改成功'
        ]);
    }

    /**
     * 获取参数
     */
    private function getParam($name) {

        return empty($_REQUEST[strval($name)]) ? '' : $_REQUEST[strval($name)];
    }

    /**
     * 返回json字符串
     */
    private function json($arr) {
        echo json_encode($arr, JSON_UNESCAPED_UNICODE);
        exit;
    }

    /**
     * 调用不存在的方法报错
     */
    public function __call($function_name, $arguments) {
        $this->json([
            'error' => 1,
            'msg' => '找不到访问的资源方法：' . $function_name
        ]);
    }

}

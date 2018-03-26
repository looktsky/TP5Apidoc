<?php
namespace app\app\controller;

use think\Controller;
use app\app\doc\Doc;
use think\Request;

class Apidoc extends Controller {

    public function _initialize(Request $request = null){
        config('default_return_type', 'html');
        if (is_null($request)) {
            $request = Request::instance();
        }
        $this->request = $request;
        $this->doc = new Doc();
        $this->root = $this->request->domain();
    }

    protected $assets_path = "";
    protected $view_path = "";
    protected $root = "";
    /**
     * @var \think\Request Request实例
     */
    protected $request;
    /**
     * @var \think\View 视图类实例
     */
    protected $view;
    /**
     * @var Doc
     */
    protected $doc;
    /**
     * @var array 资源类型
     */
    protected $mimeType = [
        'xml'  => 'application/xml,text/xml,application/x-xml',
        'json' => 'application/json,text/x-json,application/jsonrequest,text/json',
        'js'   => 'text/javascript,application/javascript,application/x-javascript',
        'css'  => 'text/css',
        'rss'  => 'application/rss+xml',
        'yaml' => 'application/x-yaml,text/yaml',
        'atom' => 'application/atom+xml',
        'pdf'  => 'application/pdf',
        'text' => 'text/plain',
        'png'  => 'image/png',
        'jpg'  => 'image/jpg,image/jpeg,image/pjpeg',
        'gif'  => 'image/gif',
        'csv'  => 'text/csv',
        'html' => 'text/html,application/xhtml+xml,*/*',
    ];


    public function index(){
        $root = $this->request->domain();
        $this->view->assign('root',$root);
        $this->view->assign('doc' , $this->request->input('name'));
        $this->view->assign('title',$this->doc->__get("title"));
        $this->view->assign('version',$this->doc->__get("version"));
        $this->view->assign('copyright',$this->doc->__get("copyright"));
        return $this->fetch();
    }

    /**
     * 文档搜素
     * @return mixed|\think\Response
     */
    public function search()
    {
        if($this->request->isAjax())
        {
            $data = $this->doc->searchList($this->request->param('query'));
            return response($data, 200, [], 'json');
        }
        else
        {
            $module = $this->doc->getModuleList();
            $root = $this->request->domain();
            $this->assign('root',$root);
            $this->assign('doc' , $this->request->input('name'));
            $this->assign('title',$this->doc->__get("title"));
            $this->assign('version',$this->doc->__get("version"));
            $this->assign('copyright',$this->doc->__get("copyright"));
            $this->assign('module',$module);
            return $this->fetch();
        }
    }


    /**
     * 接口列表
     * @return \think\Response
     */
    public function getList()
    {
        $list = $this->doc->getList();
        $list = $this->setIcon($list);
        return response(['firstId'=>'', 'list'=>$list], 200, [], 'json');
    }

    /**
     * 接口访问测试
     * @return \think\Response
     */
    public function debug()
    {
        $data = $this->request->param();
        $api_url = $this->request->param('url');
        $res['status'] = '404';
        $res['meaasge'] = '接口地址无法访问！';
        $res['result'] = '';
        $method =  $this->request->param('method_type', 'GET');
        $cookie = $this->request->param('cookie');
        $headers = $this->request->param('header/a', array());
        unset($data['method_type']);
        unset($data['url']);
        unset($data['cookie']);
        unset($data['header']);
        $res['result'] = $this->http_request($api_url, $cookie, $data, $method, $headers);
        if($res['result']){
            $res['status'] = '200';
            $res['meaasge'] = 'success';
        }
        return response($res, 200, [], 'json');
    }

    /**
     * 接口详情
     * @param string $name
     * @return mixed
     */
    public function getinfo($name = "")
    {
        list($class, $action) = explode("::", $name);
        $action_doc = $this->doc->getInfo($class, $action);
        if($action_doc)
        {
            $return = $this->doc->formatReturn($action_doc);
            $action_doc['header'] = isset($action_doc['header']) ? array_merge($this->doc->__get('public_header'), $action_doc['header']) : [];
            $action_doc['param'] = isset($action_doc['param']) ? array_merge($this->doc->__get('public_param'), $action_doc['param']) : [];

            $root = $this->request->domain();
            $this->assign('root',$root);
            $this->assign('title',$this->doc->__get("title"));
            $this->assign('version',$this->doc->__get("version"));
            $this->assign('copyright',$this->doc->__get("copyright"));
            $this->assign('doc',$action_doc);
            $this->assign('return',$return);
            return $this->fetch('info');
        }
    }

    /**
     * 设置目录树及图标
     * @param $actions
     * @return mixed
     */
    protected function setIcon($actions, $num = 1)
    {
        foreach ($actions as $key=>$moudel){
            if(isset($moudel['actions'])){
                $actions[$key]['iconClose'] = "/static/assets/js/zTree_v3/img/zt-folder.png";
                $actions[$key]['iconOpen'] = "/static/assets/js/zTree_v3/img/zt-folder-o.png";
                $actions[$key]['open'] = true;
                $actions[$key]['isParent'] = true;
                $actions[$key]['actions'] = $this->setIcon($moudel['actions'], $num = 1);
            }else{
                $actions[$key]['icon'] = "/static/assets/js/zTree_v3/img/zt-file.png";
                $actions[$key]['isParent'] = false;
                $actions[$key]['isText'] = true;
            }
        }
        return $actions;
    }

    /**
     * curl模拟请求方法
     * @param $url
     * @param $cookie
     * @param array $data
     * @param $method
     * @param array $headers
     * @return mixed
     */
    private function http_request($url, $cookie, $data = array(), $method = array(), $headers = array()){
        $curl = curl_init();
        if(count($data) && $method == "GET"){
            $data = array_filter($data);
            $url .= "?".http_build_query($data);
            $url = str_replace(array('%5B0%5D'), array('[]'), $url);
        }
        curl_setopt($curl, CURLOPT_URL, $url);
        curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, FALSE);
        curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, FALSE);
        if (count($headers)){
            $head = array();
            foreach ($headers as $name=>$value){
                $head[] = $name.":".$value;
            }
            curl_setopt($curl, CURLOPT_HTTPHEADER, $head);
        }
        $method = strtoupper($method);
        switch($method) {
            case 'GET':
                break;
            case 'POST':
                curl_setopt($curl, CURLOPT_POST, true);
                curl_setopt($curl, CURLOPT_POSTFIELDS, $data);
                break;
            case 'PUT':
                curl_setopt($curl, CURLOPT_CUSTOMREQUEST, 'PUT');
                curl_setopt($curl, CURLOPT_POSTFIELDS, $data);
                break;
            case 'DELETE':
                curl_setopt($curl, CURLOPT_CUSTOMREQUEST, 'DELETE');
                break;
        }
        if (!empty($cookie)){
            curl_setopt($curl, CURLOPT_COOKIE, $cookie);
        }
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
        $output = curl_exec($curl);
        curl_close($curl);
        return $output;
    }

}
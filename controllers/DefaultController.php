<?php

/**
 * @author ReRe Design studio
 * @email webmaster@rere-design.ru
 */
error_reporting(1);

class DefaultController extends CController
{
    public $zip = false;
    public $adminNotify = 0;
    public $dir = '_upload1C';
    public $fileLimit = 2000000;
    public $moduleUrl = 'product';
    public $email = 'error@rere-hosting.ru';

    private $_timestamp = 0;
    private $_result = array();
    private $_status = array();

    public function init()
    {
        header('Content-type: text/plain; charset=windows-1251');
    }

    public function actionPerm($type = null, $mode = null, $filename = null, $dir = false)
    {
        Yii::app()->params['thisCity'] = 1;
        $this->actionIndex($type, $mode, $filename, $dir);
    }

    public function actionIzhevsk($type = null, $mode = null, $filename = null, $dir = false)
    {
        Yii::app()->params['thisCity'] = 2;
        $this->actionIndex($type, $mode, $filename, $dir);
    }

    public function actionIndex($type = null, $mode = null, $filename = null, $dir = false)
    {
        ImportModel::$module_name = $this->moduleUrl;
        if (empty($_GET) || !count($_GET)) {
            $url = explode('?', $_SERVER['REQUEST_URI']);
            $url = end($url);
            foreach (explode('&', $url) as $row) {
                list($key, $value) = explode('=', $row);
                ${$key} = $value;
            }
        }
        if ($dir) $dir = $this->baseDir . $dir . DIRECTORY_SEPARATOR;
        if ($this->adminNotify) mail($this->email, 'COME ' . Yii::app()->session->sessionID, "REQUEST: " . print_r($_REQUEST, 1) . "\r\nSERVER: " . print_r($_SERVER, 1));

        switch ($mode):
            // Авторизация, отдаем сессию
            case 'checkauth':
                if ($this->register())
                    $this->result(array('success', 'PHPSESSID', Yii::app()->session->sessionID));
                else {
                    $this->result('failure', 0);
                }
                break;

            // Вход, отдаем настройки
            case 'init':
                if (Yii::app()->user->isGuest && !$this->register()) break;

                CFileHelper::removeDirectory($this->baseDir);

                $dir = $this->baseDir . date('Y-m-d_H-i_') . Yii::app()->user->id . DIRECTORY_SEPARATOR;
                Yii::app()->user->setState('dir', $dir);

                $this->result(array('zip=' . ($this->zip ? 'yes' : 'no'), 'file_limit=' . $this->fileLimit));
                break;

            // Файл, получаем файл
            case 'file':
                if (Yii::app()->user->isGuest && !$this->register()) break;
                $fileData = file_get_contents("php://input");
                if (empty($fileData))
                    $this->result(array('failure', 'data is empty'), 0);
                if (!$dir = Yii::app()->user->getState('dir'))
                    $this->result(array('failure', 'can`t get session dir'), 0);
                if (!$this->ensureDirectory(dirname($dir . $filename)))
                    $this->result(array('failure', 'can`t create dir in path ' . $dir . $filename), 0);
                if (!$fp = fopen($dir . $filename, "ab"))
                    $this->result(array('failure', 'can`t open file ' . $filename), 0);
                if (!fwrite($fp, $fileData))
                    $this->result(array('failure', 'can`t write in file ' . $filename), 0);
                $this->result('success');
                break;

            // Обрабатываем данные
            case 'import':
                if (Yii::app()->user->isGuest && !$this->register()) break;
                if (!$dir && !$dir = Yii::app()->user->getState('dir'))
                    $this->result(array('failure', 'can`t get session dir'), 0);
                Yii::app()->params['parseDir'] = $dir;
                if (!$filename)
                    $this->result(array('failure', 'filename is empty'), 0);
                if (!file_exists($dir . $filename))
                    $this->result(array('failure', 'can`t find file ' . $dir . $filename), 0);
                if ($this->zip && end(explode('.', $filename)) == 'zip') {
                    $this->unzip($dir, $filename);
                    $this->result('success');
                    break;
                }
                $this->_timestamp = filemtime($dir . $filename);
                if (!$xml = new XMLReader())
                    $this->result(array('failure', 'can`t create xml reader'), 0);
                if (!$xml->open($dir . $filename))
                    $this->result(array('failure', 'can`t open xml file ' . $dir . $filename), 0);

                $this->parseXml($dir . $filename);

                Yii::app()->user->setState(Yii::app()->request->requestUri, null);
                $this->result('success');
                break;

            // Отдаем файл с заказами
            case 'query':
                if (Yii::app()->user->isGuest && !$this->register()) break;
                if ($type == 'sale') OrderXml::printThis();
                if ($type == 'users') OrderXml::printThis();
                break;

            // Возвращаем такой же ответ
            case 'success':
                if (Yii::app()->user->isGuest && !$this->register()) break;
                ImportModel::clearBase(null, 'photo');
                $this->result('success');
//                mail($this->email, '1C FINISHED ' . $type, "REQUEST: " . print_r($_REQUEST, 1) . "\r\nSERVER: " . print_r($_SERVER, 1));
                break;

            // Если ничего не найдено пишем ошибку
            default:
                if (!$type && current($this->_status) > 0) {
                    sleep(5);
                    $this->refresh();
                } else {
                    mail($this->email, '1C ERROR ' . $_GET['mode'], "REQUEST: " . print_r($_REQUEST, 1) . "\r\nSERVER: " . print_r($_SERVER, 1));
                    $this->result(array('failure', 'invalid mode'));
                }
        endswitch;

        if (!$this->_result) $this->result(array('failure', 'invalid action or not logged in'));
        if ($this->adminNotify) mail($this->email, 'RESULT ALL ' . Yii::app()->session->sessionID, implode("\r\n", $this->_result) . "\r\n\r\nREQUEST: " . print_r($_REQUEST, 1) . "\r\nSERVER: " . print_r($_SERVER, 1));
        $this->_result[] = '';
        echo implode("\r\n", $this->_result);
    }

    public function parseXml($path)
    {
        $this->_status = Yii::app()->user->getState(Yii::app()->request->requestUri);
        $xml = simplexml_load_file($path);

        if (isset($xml->Классификатор)) {
            $this->addData($xml->Классификатор->Группы->Группа, 'group');
            $this->addData($xml->Классификатор->Свойства->Свойство, 'prop');
        }

        if (isset($xml->Каталог)) {
            $this->addData($xml->Каталог->Товары->Товар, 'item');
            if ($xml->Каталог['СодержитТолькоИзменения'] == 'false')
                ImportModel::clearBase($this->_timestamp, 'item');
        }

        if (isset($xml->ПакетПредложений)) {
            $this->addData($xml->ПакетПредложений->ТипыЦен->ТипЦены, 'priceType');
            $this->addData($xml->ПакетПредложений->Предложения->Предложение, 'offer');
            if ($xml->ПакетПредложений['СодержитТолькоИзменения'] == 'false')
                ImportModel::clearBase($this->_timestamp, 'offer');
        }

        if (isset($xml->Документ)) {
            $this->addData($xml->Документ, 'order');
        }
    }

    public function addData($data, $type)
    {
        $i = 0;
        $count = $this->_status['count'][$type];
        if (!$count) $this->_status['count'][$type] = $count = count($data);
        if ($this->_status[$type] == $count) return;
        echo "progress\r\n";
        foreach ($data as $row) {
            if ($this->_status[$type] - 1 >= $i++) continue;
            $row = $this->trimAll($row);
            ImportModel::createElement($row, $type, false, $this->_timestamp);

            $this->_status[$type] = $i;
            Yii::app()->user->setState(Yii::app()->request->requestUri, $this->_status);
            if ($this->life)
                $this->result(array('Success ' . $type . " {$this->_status[$type]}/{$count} " . round(100 * $this->_status[$type] / $count) . '%', '<script>location.reload()</script>'), false);

            if ($i % 10 == 0) gc_collect_cycles();
        }
        $this->result(array('Success ' . $type . " {$this->_status[$type]}/{$count} " . round(100 * $this->_status[$type] / $count) . '%', '<script>location.reload()</script>'), false);
    }

    public $alias = array(
        'Ид' => 'external_id',
        'Наименование' => 'name',
        'Группы' => array(
            'Группа' => 'group',
        ),
        'ЗначенияСвойств' => array(
            'ЗначенияСвойства' => 'propValue',
        ),
        'Сопутствующие' => array(
            'Сопутствующая' => 'likeProduct',
        ),
        'ЗначенияРеквизитов' => array(
            'ЗначениеРеквизита' => 'OtherValue',
        ),
        'Контрагенты' => array(
            'Контрагент' => 'user',
        ),
        'Товары' => array(
            'Товар' => 'item',
        ),
        'ЛогинНаСайте' => 'email',
        'ИНН' => 'inn',
        'Сумма' => 'total',
        'Артикул' => 'art',
        'Описание' => 'content',
        'Картинка' => 'image',
        'СтавкиНалогов' => array(
            'СтавкаНалога' => 'tax',
        ),
        'Цены' => array(
            'Цена' => 'price',
        ),
        'Значение' => 'value',
        'БазоваяЕдиница' => 'typeName',
        'Валюта' => 'currency',
        'Налог' => 'tax',
        'УчтеноВСумме' => 'taxInclude',
        'ИдТипаЦены' => 'external_id',
        'ЦенаЗаЕдиницу' => 'value',
        'Представление' => 'text',
        'Единица' => 'unit',
        'Коэффициент' => 'ratio',
        'Количество' => 'count',
    );

    public function trimAll($row)
    {
        if (is_object($row) || is_array($row)) {
            $row = array_map(array($this, 'trimAll'), (array)$row);
            $row = array_diff($row, array(''));
            $this->change_keys($row, $this->alias);
            if (empty($row)) return '';
            else return $row;
        } else return trim($row);
    }

    function change_keys(&$list, $alias)
    {
        if (is_array($list))
            foreach ($list as $key => $value)
                if (array_key_exists($key, $alias)) {
                    if (is_array($alias[$key])) foreach ($alias[$key] as $i => $val) {
                        if (isset($list[$key][$i][0])) $list[$val] = $list[$key][$i];
                        else {
                            if ($list[$key][$i]) $list[$val] = array($list[$key][$i]);
                            else $list[$val] = $list[$key];
                        }
                    }
                    else $list[$alias[$key]] = $list[$key];
                    unset($list[$key]);
                }
    }

    public function register()
    {
        $model = new User('login');
        $model->attributes = array(
            'email' => $_SERVER['PHP_AUTH_USER'],
            'password' => $_SERVER['PHP_AUTH_PW'],
        );
        return ($model->validate() && $model->login($_SERVER['PHP_AUTH_PW']));
    }

    public function result($data, $return = true)
    {
        $data = (array)$data;
        $this->_result = CMap::mergeArray($this->_result, $data);
        if (!$return) {
            if ($this->adminNotify) mail($this->email, 'RETURN ' . Yii::app()->session->sessionID, print_r($data, 1));
            die(implode("\r\n", $this->_result));
        } else return true;
    }

    public function ensureDirectory($directory)
    {
        if (!is_dir($directory)) {
            $this->ensureDirectory(dirname($directory));
            if (!mkdir($directory))
                return false;
        }
        return true;
    }

    public function unzip($dir, $filename)
    {
        $zip = new ZipArchive;
        if ($zip->open($dir . $filename) === true) {
            $zip->extractTo($dir);
            $zip->close();
            return true;
        }
        return false;
    }

    public function actionClearAll()
    {
        if (Yii::app()->user->checkAccess('webmaster')) {
            $sql = 'DELETE FROM `page` WHERE module_id=:module AND (`level`>1 OR `level`=0)';
            Yii::app()->db->createCommand($sql)->execute(array('module' => Module::get($this->moduleUrl)));
            $sql = 'DELETE FROM `exchange_1c` WHERE `type`=:type)';
            Yii::app()->db->createCommand($sql)->execute(array('type' => 'page'));
        }
    }

    public function getLife()
    {
        $time = time() - $_SERVER['REQUEST_TIME'];
        return ($time > (15)) || Yii::getLogger()->memoryUsage > ((ini_get('memory_limit') - 5) * 1024 * 1024);
    }

    public function getBaseDir()
    {
        return Yii::getPathOfAlias('webroot.data.' . $this->dir) . DIRECTORY_SEPARATOR;
    }

    public function actionError()
    {
        if ($error = Yii::app()->errorHandler->error) {
            $this->pageTitle = Yii::t('base', 'Error') . ' ' . $error['code'];
            echo iconv('utf8', 'cp1251', $error['message']);
        }
    }
}
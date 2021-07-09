<?php
if(!defined("B_PROLOG_INCLUDED") || B_PROLOG_INCLUDED !== true) die();

use Bitrix\Main\Context;
use Bitrix\Main\Engine\ActionFilter\Csrf;
use Bitrix\Main\Engine\ActionFilter\HttpMethod;
use Bitrix\Main\Engine\Contract\Controllerable;
use Bitrix\Main\Errorable;
use Bitrix\Main\Loader;
use Bitrix\Sale\Basket;
use Bitrix\Sale\Fuser;
use \Bitrix\Main\Web\Json;
use \Bitrix\Main\Data\Cache;
use Bitrix\Main\Application;
use Bitrix\Main\Type\DateTime;
use Bitrix\Iblock\ORM\PropertyValue;
use Bitrix\Main\Mail\Event;
use Bitrix\Main\Error;
use Bitrix\Main\ErrorCollection;


class CComments extends CBitrixComponent implements Controllerable, Errorable
{
    /**
     * Код свойства кол-во комментов для списка элементов
     */
    const PROPERTY_COMMENT_CNT_CODE = "COMMENTS_CNT";
    /**
     * Код свойства средняя оцена для списка элементов
     */
    const PROPERTY_AVG_RANG = "AVG_RANG";
    /**
     * @var string Путь до аватарки пользователя
     */
    private $userLogo = "/local/templates/vector_main/assets/dist/img/user.png";
    /**
     * @var string Путь до аватарки админа
     */
    private $adminLogo = "/local/templates/vector_main/assets/dist/img/logo-2.png";

    /**
     * @var array Список полей для вывода в шаблоне
     * Если нужно не выводить какое-то поле, то проставить active = false
     * Если нуожно сделать обязательным/не обязательным то проставить required = true|false
     * Порядок вывода идет как в массиве, если нужно изменить просто поменять местами прям в этом массиве
     */
    public $inputFields = [
        'rang' => [
            'order' => 0,
            'length' => 1000, //Ограничение на кол-во символов
            'required' => false, // Обязательное или нет
            'name' => 'Поставить оценку', // Название в шаблоне
            'type' => 'radio', // Нужно для шаблона (radio | text | file)
            'active' => false, // Выводить не выводить поле
            'error' => '', // Текст ошибки к этому полю, который сам заполнится на бэке и попадет в шаблон
            'placeholder' => '', // Плейсхолдер в инпуте
            'value' => '',
        ],
        'text' => [
            'order' =>1,
            'length' => 1000,
            'required' => true,
            'name' => 'Текст отзыв',
            'active' => true,
            'type' => 'textarea',
            'error' => '',
            'placeholder' => 'Опишите плюсы и минусы',
            'value' => '',
        ],
        'name' => [
            'order' =>2,
            'length' => 255,
            'required' => true,
            'name' => 'Фамилия Имя',
            'active' => true,
            'type' => 'text',
            'error' => '',
            'placeholder' => 'Представьтесь пожалуйста',
            'value' => '',
        ],
        'email' => [
            'order' =>3,
            'length' => 255,
            'required' => false,
            'name' => 'Email',
            'active' => false,
            'type' => 'text',
            'error' => '',
            'placeholder' => 'Никого спама',
            'value' => '',
        ],
        'phone' => [
            'order' =>4,
            'length' => 255,
            'required' => false,
            'name' => 'Телефона',
            'active' => false,
            'type' => 'tel',
            'error' => '',
            'placeholder' => '',
            'value' => '',
        ],
        'file' => [
            'order' =>5,
            'length' => 255,
            'required' => false,
            'name' => 'Файлы',
            'active' => false,
            'type' => 'file',
            'limitSize' => 5, // 5 мб максимльный вес файла
            'mimes' => array('gif','png','jpg','jpeg','pdf','xls','xlsx','doc','docx'), // Типы файлов
            'maxCount' => 10, // Макс кол-во файлов которые можно загрузить
            'error' => '',
            'placeholder' => '',
        ],
    ];
    /**
     * ID инфоблока в котором будут обновлятся свойства счетчиков
     */
    public $linkIblockId = 0;

    public $iblockId = 0;

    public $cacheDir = '/comments';

    public $cacheTime = 3600000;

    /**
     * @var array id групп пользователей, которые будут считаться как менеджеры
     * Для пометки комментариев как ответ от менеджера
     */
    public $managerGroups = [
        1, //Админы
    ];

    /**
     * @var int limit по умолчанию
     */
    public $limit = 1000;
    /**
     * @var array select поля в sql запросе
     */
    public $selectFields = [
        'ID',
        'PREVIEW_TEXT',
        'DATE_CREATE',
        'COMMENT_USER_NAME' => 'USER_NAME.VALUE',
        'COMMENT_USER_EMAIL' => 'USER_EMAIL.VALUE',
        'COMMENT_USER_PHONE' => 'USER_PHONE.VALUE',
        'COMMENT_USER_FILE' => 'USER_FILE',
        'COMMENT_USER_RANG' => 'USER_RANG.VALUE',
        'COMMENT_ROOT_ID' => 'ROOT_ID.VALUE',
        'COMMENT_IS_MANAGER' => 'IS_MANAGER.VALUE',
        'COMMENT_ELEMENT_ID' => 'ELEMENT_ID.VALUE',
        'COMMENT_PARENT_ID' => 'PARENT_ID.VALUE'
    ];

    /**
     * @var int ID элемента к которому привязанны комментарии
     */
    public $elementId = 0;

    /**
     * @var string Формат даты создания комментария
     */
    public $dateFormat = 'd.m.Y H:i:s';

    /**
     * @var array Сортировка по умолчанию
     */
    public $order = ["DATE_CREATE" => "DESC"];

    /**
     * @var string Код почтовго события для админов;
     */
    public $eventId = 'NEW_COMMENT';

    /**
     * @var string Код почтовго события для авторов комментария;
     */
    public $userMailEventId = 'NEW_COMMENT_USER';

    /**
     * @var int Cooldown в секундах между добавлением комментов
     */
    public $timeCoolDown = 60;

    /**
     * @var int Максимальная  вложеность
     */
    public $maxDepth = 3;

    /** @var ErrorCollection */
    protected $errors;

    const ERROR_BX = 0;

    const ERROR_VALID = 1;

    /**
     * @var array Перевод
     */
    private $translateArr = [];

    private $result = [];

    private $localStorage;

    public function __construct($component = null)
    {
        parent::__construct($component);

        Loader::includeModule('iblock');

        $this->localStorage = Application::getInstance()->getLocalSession('reviews');

        if (file_exists(__DIR__."/lang.php")) {
            $this->translateArr = include (__DIR__."/lang.php");
        }

        if (!defined('LANG_ID')) {
            define('LANG_ID', 'RU');
        }

        if (LANG_ID !== 'RU') {
            $this->translateFields();
        }

    }

    public function getMsg($code, $format = [])
    {
        if(empty($code)) return $code;

        if(empty($this->translateArr['messages'][LANG_ID][$code])) return $code;

        $string = $this->translateArr['messages'][LANG_ID][$code];

        foreach ($format as $k => $f) {
            $string = str_replace($k, $f, $string);
        }

        return $string;
    }

    /**
     * @return array
     */
    public function configureActions()
    {
        return [
            'data' => [
                'prefilters' => [
                    new HttpMethod([HttpMethod::METHOD_POST]),
                    new Csrf(false),
                ],
                'postfilters' => []
            ],
            'add' => [
                'prefilters' => [
                    new HttpMethod([HttpMethod::METHOD_POST]),
                    new Csrf(false),
                ],
                'postfilters' => []
            ],
        ];
    }

    public function translateFields()
    {
        $langFields = $this->translateArr['fields'][LANG_ID];

        foreach ($langFields as $k => $v) {
            if($v['name']) $this->inputFields[$k]['name'] = $v['name'];
            if($v['placeholder']) $this->inputFields[$k]['placeholder'] = $v['placeholder'];
        }
    }

    /**
     * @return array|Error[]
     */
    public function getErrors()
    {
        return $this->errors->toArray();
    }

    /**
     * Getting once error with the necessary code.
     * @param string $code Code of error.
     * @return \Bitrix\Main\Error
     */
    public function getErrorByCode($code)
    {
        // TODO: Implement getErrorByCode() method.
    }

    public function setResponseResult($result)
    {
        $this->result = $result;
    }

    public function getResponseResult()
    {
        $result = $this->result;
        $result['lang'] = $this->translateArr['tpl'][LANG_ID];

        return $result;
    }

    private function getInput()
    {
        if($_SERVER['REQUEST_METHOD'] == 'POST'){
            return $_POST;
        } else {
            return [];
        }
    }

    public function onPrepareComponentParams($arParams)
    {
        $this->errors = new ErrorCollection();

        $post = $this->getInput();

        if ($post['params_id']) {
            $this->setParams($this->getParamsFromSession($post['params_id']));
        } else {
            $this->setParams($arParams);
        }
        return $arParams;
    }

    /**
     * Установить значения свойств из настроек компонента
     * @param $arParams
     */
    private function setParams($arParams)
    {
        if ($arParams['IBLOCK_ID']) $this->iblockId = $arParams['IBLOCK_ID'];

        if ($arParams['SORT_DATE']) $this->order = ["DATE_CREATE" => $arParams['SORT_DATE']];

        if ($arParams['ELEMENT_ID']) $this->elementId = $arParams['ELEMENT_ID'];

        if ($arParams['LINK_IBLOCK_ID']) $this->linkIblockId = $arParams['LINK_IBLOCK_ID'];

        if ($arParams['TIME_COOL_DOWN']) $this->timeCoolDown = $arParams['TIME_COOL_DOWN'];

        if ($arParams['MAX_DEPTH']) $this->maxDepth = $arParams['MAX_DEPTH'];

        if ($arParams['CACHE_TIME']) $this->cacheTime = $arParams['CACHE_TIME'];

        if ($arParams['EVENT_ID']) $this->eventId = $arParams['EVENT_ID'];

        if ($arParams['EVENT_USER_ID']) $this->userMailEventId = $arParams['EVENT_USER_ID'];

        if ($arParams['FIELD_SORT_NAME']) $this->inputFields['name']['order'] = $arParams['FIELD_SORT_NAME'];

        if ($arParams['FIELD_SORT_TEXT']) $this->inputFields['text']['order'] = $arParams['FIELD_SORT_TEXT'];

        if ($arParams['FIELD_SORT_EMAIL']) $this->inputFields['email']['order'] = $arParams['FIELD_SORT_EMAIL'];

        if ($arParams['FIELD_SORT_PHONE']) $this->inputFields['phone']['order'] = $arParams['FIELD_SORT_PHONE'];

        if ($arParams['FIELD_SORT_RANG']) $this->inputFields['rang']['order'] = $arParams['FIELD_SORT_RANG'];

        if ($arParams['FIELD_SORT_FILE']) $this->inputFields['file']['order'] = $arParams['FIELD_SORT_FILE'];

        if ($arParams['REQUIRED_FIELDS']) {
            foreach ($arParams['REQUIRED_FIELDS'] as $f) {
                $this->inputFields[$f]['required'] = true;
            }
        }

        if ($arParams['SHOW_FIELDS']) {
            foreach ($arParams['SHOW_FIELDS'] as $f) {
                $this->inputFields[$f]['active'] = true;
            }
        }
    }

    public function addError($m, $code)
    {
        $this->errors[] = new Error($m, $code);
    }

    /**
     * Получить ключ параметров компонента, которые хранятся в сессии
     * @return mixed
     */
    public function getParamsId()
    {
        $params = [
            $this->inputFields,
            $this->iblockId,
            self::PROPERTY_COMMENT_CNT_CODE,
            self::PROPERTY_AVG_RANG,
            $this->order,
            $this->linkIblockId,
            $this->timeCoolDown,
            $this->maxDepth,
            $this->cacheTime,
            $this->eventId,
            $this->userMailEventId,
            $this->userLogo,
            $this->adminLogo,
        ];
        return md5(serialize($params));
    }
    /**
     * Сохранить текущие параметры компонента в сессию
     */
    public function setParamsToSession()
    {
        $this->localStorage->set($this->getParamsId(), $this->arParams);
    }
    /**
     * Получить текущие параметры компонента из сессии
     * @param $id
     * @return mixed
     */
    public function getParamsFromSession($id)
    {
        return $this->localStorage->get($id);
    }
    /**
     * Получить поля для вывода в шаблон
     * @return array
     */
    public function getActiveFields() {
        $fields = [];
        $fieldValues = $this->getSavedUserFields();

        foreach ($this->inputFields as $k =>$f) {
            if ($f['active']) {
                $f['value'] = $fieldValues[$k];
                $fields[$k] = $f;
            }
        }
        uasort($fields, function($a, $b) {
            return $a['order'] <=> $b['order'];
        });

        return $fields;
    }

    private function isAdminUser()
    {
        global $USER;
        return $USER->IsAdmin();
    }

    private function saveUserFields($inputs)
    {
        foreach ($inputs as $k => $v) {
            if (!empty($inputs[$k])) $_SESSION['COMMENT_FIELD'][$k] = $v;
        }
    }

    private function getSavedUserFields()
    {
        global $USER;
        $user = CUser::GetByID($USER->GetID())->Fetch();

        $name = $_SESSION['COMMENT_FIELD']['name'] ? $_SESSION['COMMENT_FIELD']['name'] : $USER->GetFirstName() . " " .$USER->GetLastName();
        $email = $_SESSION['COMMENT_FIELD']['email'] ? $_SESSION['COMMENT_FIELD']['email'] : $USER->GetEmail();
        $phone = $_SESSION['COMMENT_FIELD']['phone'] ? $_SESSION['COMMENT_FIELD']['phone'] : $user['PERSONAL_MOBILE'];

        return [
            'name' => $name,
            'email' => $email,
            'phone' => $phone,
        ];
    }

    private function checkFiles($files) {
        $hasFiles = false;
        foreach ($files['name'] as $i => $n) {
            if( !empty($n))
                $hasFiles = true;
        }

        if (!$hasFiles) return;

        // проверим размеры, количество и типы файлов
        if (count($files['size']) > $this->inputFields['file']['maxCount']) {
            $msg = $this->getMsg('LIMIT_FILE_COUNT_ERROR', ['#COUNT#'=>$this->inputFields['file']['maxCount']]);
            $this->addError($msg, self::ERROR_VALID);
        }

        $fileSizeLimit = 1024 * 1024 * $this->inputFields['file']['limitSize'];
        $fileExts = $this->inputFields['file']['mimes'];

        foreach ($files['size'] as $key => $size) {

            if ($size > $fileSizeLimit) {
                $msg = $this->getMsg('FILE_SIZE_LIMIT_ERROR', ['#SIZE#' => $this->inputFields['file']['limitSize']]);
                $this->addError($msg, self::ERROR_VALID);
            }

            $ext = pathinfo($files['name'][$key], PATHINFO_EXTENSION);

            if(!in_array($ext, $fileExts) ) {
                $msg = $this->getMsg('FILE_TYPE_ERROR', ['#TYPES#' => implode(', ', $this->inputFields['file']['mimes'])]);
                $this->addError($msg, self::ERROR_VALID);
            }
        }
    }

    private function uploadFiles($itemId)
    {
        $server = \Bitrix\Main\Context::getCurrent()->getServer();

        $fileUploadDir = $server->getDocumentRoot().'/upload/';
        $arFiles = array();
        $files = $_FILES['file'];
        $hasFiles = false;

        foreach ($files['name'] as $i => $n) {
            if( !empty($n))
                $hasFiles = true;
        }

        if (!$hasFiles) return;

        foreach ($files['size'] as $key => $size) {
            $arFiles[] = array(
                'name' => $files['name'][$key],
                'type' => $files['type'][$key],
                'tmp_name' => $files['tmp_name'][$key],
                'error' => $files['error'][$key],
                'size' => $files['size'][$key],
            );
        }
        $arFileBx = array();
        $cnt = count($arFiles);

        for ($i = 0; $i < $cnt; $i++) {
            $arFiles[$i]['save_to'] = $fileUploadDir.$itemId.'_'.date('Ymd_His_').$arFiles[$i]['name'];

            if(!move_uploaded_file($arFiles[$i]['tmp_name'], $arFiles[$i]['save_to'])){
                $msg = $this->getMsg('UPLOAD_FILE_ERROR', ['#FILE_NAME#' => $arFiles[$i]['name']]);
                $this->addError($msg, 1);
            }

            $arFileBx[] = array("VALUE" => CFile::MakeFileArray($arFiles[$i]['save_to']),"DESCRIPTION"=>"");
        }

        if(count($arFileBx) > 0) {
            \CIBlockElement::SetPropertyValuesEx($itemId, $this->iblockId, array('USER_FILE' => $arFileBx));
            foreach ( $arFiles as $file ) {
                unlink($file['save_to']);
            }
        }
    }

    /*!!!-----------------start ACTIONS section-------------------!!!*/
    /**
     * Данные со списком комментариев
     * @throws \Bitrix\Main\ArgumentException
     * @throws \Bitrix\Main\SystemException
     */
    public function dataAction()
    {
        $result = $this->getTree();
        $result['timer'] = $this->getCoolDownTimer();
        $result['fields'] = $this->getActiveFields();//Добавляем только активные поля
        $result['isAuthAdmin'] = $this->isAdminUser();
        $this->setResponseResult($result);

        return $this->getResponseResult();
    }

    /**
     * Добавить комментарий
     */
    public function addAction()
    {
        $input = $this->getInput();
        //Сохранение в сессию забиты данных
        $this->saveUserFields($input);
        //check fields
        $this->validFields($input);
        //Проверка файлов если они есть
        $this->checkFiles($_FILES['file']);

        if (count($this->getErrors()) == 0 && $created = $this->createElement($input)) {
            $this->onAfterAdd($created);

            $result = $this->getTree();

            $result['type'] ='success';
            $result['timer'] = $this->getCoolDownTimer();
            $result['msg'] = $this->getMsg('COMMENT_ADDED');
            $result['created'] = $created;
            $result['fields'] = $this->getActiveFields();
            $result['isAuthAdmin'] = $this->isAdminUser();

            $this->setResponseResult($result);
        }

        return $this->getResponseResult();
    }
    /*!!!-----------------end ACTIONS section-------------------!!!*/

    private function getUser()
    {
        global $USER;
        return $USER;
    }

    /**
     * Получить дерево комментариев
     * @throws \Bitrix\Main\ArgumentException
     * @throws \Bitrix\Main\SystemException
     */
    private function getTree()
    {
        $result = [];

        $cacheId = md5(serialize($this->arParams));
        $cache = Cache::createInstance();
        $cachePath = $this->cacheDir.'/'.$this->elementId;

        if ($cache->initCache($this->cacheTime, $cacheId, $cachePath)) {
            $result = $cache->getVars();
        } else {
            global $CACHE_MANAGER;
            $CACHE_MANAGER->StartTagCache($cachePath);
            $CACHE_MANAGER->RegisterTag('iblock_id_' . $this->iblockId);

            $rootResult = $this->getRootComments();

            $childResult = $this->getChildComments($rootResult['rootIds']);

            $total = $childResult['total_count'];

            foreach ($childResult['items'] as $childItem) {
                $rootResult['items'][] = $childItem;
            }

            $items = $rootResult['items'];
            $tree = array();
            $sub = array(0 => &$tree);

            foreach ($items as $item) {
                $branch = &$sub[$item['parentId']];
                $branch[$item['commentId']] = $item;
                $sub[$item['commentId']] = &$branch[$item['commentId']]['comments'];
            }

            //Восстанавливаем сортировку
            foreach ($rootResult['rootIds'] as $id) {
                $items = $tree[$id];
                $result[] = $items;
            }
            //Добавить глубину вложености
            $comments = $this->arraySetDepth($result);
            //Сортировка дочерных комментов
            foreach ($comments as &$comment) {
                $this->sortChild($comment['comments']);
            }

            $result = [
                'comments' => $comments,
                'count' => (int)$total
            ];

            $CACHE_MANAGER->RegisterTag('comment_' . $this->elementId);
            $CACHE_MANAGER->EndTagCache();

            if ($cache->startDataCache()) {
                $cache->endDataCache($result);
            }
        }

        return $result;
    }
    /**
     * Добавить элемент в инфоблок
     * @param $data
     * @return bool || array
     * @throws \Bitrix\Main\ArgumentException
     * @throws \Bitrix\Main\SystemException
     */
    private function createElement($data)
    {
        /** @var \Bitrix\Iblock\Elements\EO_ElementComments $element */
        $element = $this->getEntity()::createObject();
        $element->setName('Комментарий');
        $element->setPreviewText($this->clearTextInput($data['text']));
        $result = $element->save();

        if ($result->isSuccess()) {
            $id = $result->getId();

            $this->uploadFiles($id);

            $newElement = $this->getEntity()::query()
                ->where('ID', $id)
                ->where('IBLOCK_ID', $this->iblockId)
                ->fetchObject();

            $rangValue = intval($data['rang']);

            if ($rangValue < 0) $rangValue = 0;
            if ($rangValue > 5) $rangValue = 5;

            $newElement->setName('Комментарий № '.$id);
            $newElement->setElementId($data['elementId']);
            $newElement->setParentId($data['parentId']);
            $newElement->setUserRang($rangValue);
            $newElement->setUserId($this->getUser()->GetID());
            $newElement->setRootId($data['rootId'] > 0 ? $data['rootId'] : $id);
            $newElement->setUserName($this->clearTextInput($data['name']));
            $newElement->setUserEmail($this->clearTextInput($data['email']));
            $newElement->setUserPhone($this->clearTextInput($data['phone']));
            $newElement->setIp($this->getIp());

            if ($this->isManager())
                $newElement->setIsManager(1);

            $result = $newElement->save();

            if ($result->isSuccess()) {
                $_SESSION['COMMENT_ADDED_TIME'] = time(); // Время для таймера кулдауна
                return [
                    'id' => $id,
                    'name' => $this->clearTextOutPut($data['name']),
                    'text'=>$this->clearTextOutPut($data['text']),
                    'email' => $this->clearTextOutPut($data['email']),
                    'rang' => $rangValue,
                    'userLogo' => $this->isManager() ? $this->adminLogo : $this->userLogo,
                    'elementId' => $this->elementId,
                    'parentId' => $data['parentId'],
                    'ip' => $this->getIp(),
                ];
            } else {
                foreach ( $result->getErrorMessages() as $m) {
                    $this->addError($m, self::ERROR_BX);
                }
            }
        } else {
            foreach ( $result->getErrorMessages() as $m) {
                $this->addError($m, self::ERROR_BX);
            }
        }

        return false;
    }

    /**
     * Является ли текущий пользователь менеджером
     * @return bool
     */
    public function isManager()
    {
        global $USER;
        $isManager = false;

        if ($USER->IsAuthorized()) {
            $arGroups = $USER->GetUserGroupArray();
            foreach ($arGroups as $id) {
                if (in_array($id, $this->managerGroups)) {
                    $isManager = true;
                    break;
                }
            }
        }

        return $isManager;
    }

    /**
     * Получить среднюю оценку по элементу к которому привязанны комментарии
     * @return mixed
     * @throws \Bitrix\Main\ArgumentException
     * @throws \Bitrix\Main\ObjectPropertyException
     * @throws \Bitrix\Main\SystemException
     */
    private function getAvgRang()
    {
        $query = $this->prepareCommentsQuery();
        $query->addFilter(">USER_RANG.VALUE", 0);
        $query->setSelect(['AVG_RANG']);
        $query->registerRuntimeField("AVG_RANG", [
            // тип вычисляемого поля
            "data_type" => "integer",
            "expression" => ["avg(%s)", "USER_RANG.VALUE"]
        ]);

        return round($query->fetch()['AVG_RANG'], 1);
    }

    /**
     * Получить корневые комменты
     * @return array
     * @throws \Bitrix\Main\ArgumentException
     * @throws \Bitrix\Main\SystemException
     */
    private function getRootComments()
    {
        $query = $this->prepareCommentsQuery();
        $query->setOrder($this->order);
        $query->addFilter('PARENT_ID.VALUE', 0);
        $query->addFilter('ELEMENT_ID.VALUE', $this->elementId);
        $query->setLimit($this->limit);

        return $this->fetchComments($query);
    }

    /**
     * Получить дочерные комменты
     * @param $rootIds
     * @return array
     * @throws Exception
     */
    private function getChildComments($rootIds)
    {
        $query = $this->prepareCommentsQuery();
        $query->addFilter('ROOT_ID.VALUE', $rootIds);
        return $this->fetchComments($query);
    }

    /**Получить сформированный массив из запроса
     * @param $query
     * @return array
     */
    private function fetchComments($query)
    {
        $ids = [];
        $items = [];
        $queryRes = $query->fetchCollection();

        foreach ($queryRes as $resItem) {
            $rootId = 0;
            $elementId = 0;
            $name = '';
            $email = '';
            $phone = '';
            $rang = '';
            $isManager = '';
            $parentId = 0;
            $text = '';
            $date = '';

            if (is_a($resItem->getDateCreate(), DateTime::class))
                $date = $resItem->getDateCreate()->format($this->dateFormat);

            $files = [];

            foreach ($resItem->getUserFile() as $file) {
                $fileId = $file->getValue();
                $files[] = \CFile::GetFileArray($fileId);
            }

            if ($resItem->getRootId()) $rootId = (int)$resItem->getRootId()->getValue();
            if ($resItem->getElementId()) $elementId = (int)$resItem->getElementId()->getValue();
            if ($resItem->getParentId()) $parentId = (int)$resItem->getParentId()->getValue();
            if ($resItem->getUserName()) $name = $this->clearTextOutPut($resItem->getUserName()->getValue());
            if ($resItem->getUserEmail()) $email = $this->clearTextOutPut($resItem->getUserEmail()->getValue());
            if ($resItem->getUserPhone()) $phone = $this->clearTextOutPut($resItem->getUserPhone()->getValue());
            if ($resItem->getUserRang()) $rang = intval($resItem->getUserRang()->getValue());
            if ($resItem->getIsManager()) $isManager = (int)$resItem->getIsManager()->getValue() > 0;
            if ($resItem->getPreviewText()) $text = $this->clearTextOutPut($resItem->getPreviewText());

            if ($parentId == 0) // Если нет родителя значит это рут
                $ids[] = $resItem->getId(); // Собираем id рутов

            $items[] = [
                'commentId' => $resItem->getId(),
                'rootId' => $rootId,
                'elementId' => $elementId,
                'name' => $name,
                'email' => $email,
                'phone' => $phone,
                'rang' => $rang,
                'isManager' => $isManager,
                'parentId' => $parentId,
                'text' => $text,
                'date' => $date,
                'files' => $files,
                'userLogo' => $isManager ? $this->adminLogo : $this->userLogo,
                'maxDepth' => $this->maxDepth,
                'depth' => 0,
            ];
        }

        $query = $this->prepareCommentsQuery();
        $query->setSelect(['COUNT']);
        $query->registerRuntimeField("COUNT", [
            // тип вычисляемого поля
            "data_type" => "integer",
            "expression" => ["count(%s)", "ID"]
        ]);

        $count = $query->fetch()['COUNT'];

        return [
            'total_count' => $count,
            'items' => $items,
            'rootIds' => $ids
        ];
    }

    /**
     * Сортирует дочерные комменты
     * @param $comments
     */
    private function sortChild(&$comments)
    {
        $key = 'commentId';
        usort($comments, function($a, $b) use ($key) {
            if($this->order['DATE_CREATE' == 'ASC'])
                return $a[$key] <=> $b[$key];
            else
                return $b[$key] <=> $a[$key];
        });

        foreach ($comments as $key => $value) {
            if (is_array($value['comments'])) {
                $this->sortChild($value['comments']);
            }
        }
    }

    /**
     * Добавляет элементам уровень вложености в ключ depth
     * @param $array
     * @param int $depth
     * @return mixed
     */
    private function arraySetDepth($array, $depth = -1)
    {
        $subdepth = $depth + 1;

        if ($depth < 0) {
            foreach ($array as $key => $subarray) {
                $temp[$key] = $this->arraySetDepth(($subarray), $subdepth);
            }
        }

        if (is_array($array['comments'])) {
            foreach ($array['comments'] as $key => $subarray) {
                $temp[$key] = $this->arraySetDepth($subarray, $subdepth);
            }

            $array['comments'] = $temp;
        }
        if ($depth >=0)
            $array['depth'] = $depth;

        return $array;
    }

    /**
     * Получить подготовленный sql запрос для списка
     * @return \Bitrix\Main\Entity\Query
     * @throws Exception
     */
    private function prepareCommentsQuery()
    {
        $query = $this->getQuery()
            ->setFilter(['IBLOCK_ID', $this->iblockId, 'ACTIVE' => 'Y', '>PREVIEW_TEXT' => 0])
            ->setSelect($this->selectFields);

        return $query;
    }

    /**
     * Получить ORM сущность инфоблока
     * @return Bitrix\Main\Entity\DataManager
     * @throws Exception
     */
    private function getEntity()
    {
        $iblock = \Bitrix\Iblock\Iblock::wakeUp($this->iblockId);

        if (!$iblock->getEntityDataClass()) {
            throw new Exception("iblock " . $this->iblockId . " not found or you forgot to set api code!");
        }

        return $iblock->getEntityDataClass();
    }

    /**
     * Получить query запрос из текущей сущности
     * @return \Bitrix\Main\Entity\Query
     * @throws Exception
     */
    private function getQuery()
    {
        $query = $this->getEntity()::query();

        return $query;
    }

    /**
     * Вызывается после успешного добавления комментария
     * @param $data [id, name, email ,text, elementId, parentId, ip]
     *
     */
    public function onAfterAdd($data)
    {
        $this->sendToEmail($data);
        $this->clearCache($data['elementId']);
        $this->updateCount($data['elementId']); //Обновить счетчик у списка
        $this->updateRang($data['elementId']); //Обновить среднюю оценку у списка
        unset($_SESSION['COMMENT_FIELD']);
    }

    /**
     * Получить ссылку на редактирование элемента в админке
     * @param $id
     * @return string
     */
    private function getLinkToCommentIblockEdit($id)
    {
        return  "https://moigk.ru/bitrix/admin/iblock_element_edit.php?IBLOCK_ID=".$this->iblockId."&type=content&ID=".$id."&lang=ru&find_section_section=-1>";
    }

    /**
     * Отправка почтового уведомления
     * @param $data [id, name, email ,text, elementId, parentId, ip]
     */
    private function sendToEmail($data)
    {
        $linkToElement = $this->getLinkToCommentIblockEdit($data['id']);
        $linkToComment = $this->getCurrentPage().'#comment-'.$data['id'];

        $resQuery = \Bitrix\Iblock\ElementTable::getByPrimary($data["elementId"], ['select' => ["NAME"]])->fetch();

        $mailData = [
            'ID' => $data['id'],
            'ELEMENT_NAME' => $resQuery["NAME"],
            'USER_NAME' => $data['name'],
            'USER_EMAIL' => $data['email'],
            'USER_PHONE' => $data['phone'],
            'MESSAGE' => $data['text'],
            'ELEMENT_ID' => $data['elementId'],
            'IP' => $data['ip'],
            'LINK_TO_ELEMENT' => $linkToElement,
            'LINK_TO_COMMENT' => $linkToComment,
        ];

        Event::sendImmediate(array(
            "EVENT_NAME" => $this->eventId,
            "LID" => SITE_ID,
            "C_FIELDS" => $mailData,
        ));

        //Если это ответ на коммент, то отправляем письмо автору
        if ($data['parentId'] > 0 ) {
            $element = $this->prepareCommentsQuery()->where('ID', $data['parentId'])->fetch();
            if ($element && $element['COMMENT_USER_EMAIL']) {
                $mailData['AUTHOR_EMAIL'] = $element['COMMENT_USER_EMAIL'];
                Event::send(array(
                    "EVENT_NAME" => $this->userMailEventId,
                    "LID" => SITE_ID,
                    "C_FIELDS" => $mailData,
                ));
            }
        }
    }

    /**
     * Сбросить кэш ветки по elementId
     * @param $id
     */
    private function clearCache($elementId)
    {
        global $CACHE_MANAGER;

        if ($elementId > 0)
            $CACHE_MANAGER->ClearByTag('comment_'.$elementId);
    }

    /**
     * Получить url текущей страницы
     * @return string
     */
    private function getCurrentPage()
    {
        return $_SERVER['HTTP_REFERER'];
    }

    /**
     * Получить IP адрес клиента
     * @return false|string
     */
    private function getIp()
    {
        return \Bitrix\Main\Service\GeoIp\Manager::getRealIp();
    }

    /**
     * Валидация полей
     * @param $fields
     */
    private function validFields($fields)
    {
        $validFields = [];

        if ($this->getCoolDownTimer() > 0) {
            $msg = $this->getMsg('NEXT_COMMENT_COOLDOWN_ERROR', ['#TIME#' => $this->getCoolDownTimer()]);
            $this->addError($msg, self::ERROR_BX);
        }

        foreach ($this->inputFields as &$f) {
            $f['error'] = '';
        }

        foreach ($this->inputFields as $key => $field) {
            if(!$field['active']) continue;
            //Если это ответ то не проверяем обязательность оценки
            if($fields['rang'] <=0 && $fields['isAnswer']) continue;

            if ($field['required'] && empty($fields[$key])) {
                $msg = $this->getMsg('FILL_FIELD_VALID', ['#NAME#' => $field['name']]);
                $this->addError($msg, self::ERROR_VALID);
                $validFields[$key] =  $this->getMsg('ENTER_NAME_VALID', ['#NAME#' =>$field['name']]);
                $this->inputFields[$key]['error'] = $msg;
            } else if ($field['length'] > 0 && mb_strlen($fields[$key]) > $field['length']) {
                $msg = $this->getMsg('LENGTH_VALID', ['#LENGTH#' => $fields['length'], '#NAME#' =>$field['name']]);
                $this->addError($msg, self::ERROR_VALID) ;
                $validFields[$key] =  $msg;
                $this->inputFields[$key]['error'] = $msg;
            } else if ($key == 'email' && !empty($fields[$key]) && !$this->isValidEmail($fields[$key])) {
                $msg = $this->getMsg('FORMAT_VALID', ['#NAME#' => $field['name']]);
                $this->addError($msg, self::ERROR_VALID);
                $validFields[$key] =  $this->getMsg('CHECK_VALID', ['#NAME#' => $field['name']]);
                $this->inputFields[$key]['error'] = $msg;
            }
        }

        $this->setResponseResult(['type' => 'valid', 'fields' => $this->getActiveFields()]);
    }

    /**
     * Получить время в секундах сколько осталось до конца кулдауна
     * @return float|int
     */
    private function getCoolDownTimer()
    {
        $diff = abs($_SESSION['COMMENT_ADDED_TIME'] - time());

        if ($diff > $this->timeCoolDown) {
            return 0;
        } else {
            return $this->timeCoolDown - $diff;
        }
    }

    /**
     * Проверить email на корректность
     * @param $mail
     * @return mixed
     */
    private function isValidEmail($email)
    {
        return  filter_var($email, FILTER_VALIDATE_EMAIL);
    }

    /**
     * Отфильтровать строку отправленную пользователем
     * @param $text
     * @return null|string|string[]
     */
    private function clearTextInput($text)
    {
        $newString = htmlspecialchars_decode($text);
        $newString = filter_var($newString, FILTER_SANITIZE_STRING);

        return $newString;
    }

    /**
     * Отфильтровать строку перед выводом
     * @param $text
     */
    private function clearTextOutPut($text)
    {
        $newString = strip_tags($text);
        $newString = filter_var($newString, FILTER_SANITIZE_STRING);
        $newString = preg_replace('/\b(https?|http|ftp|file):\/\/[-A-Z0-9+&@#\/%?=~_|$!:,.;]*[A-Z0-9+&@#\/%=~_|$]/i', '', $newString);

        return $newString;
    }

    /**
     * Обновить кол-во элементов отзывов в инфоблоке с элементами
     */
    private function updateCount($elementId)
    {
        if ($elementId > 0) {
            $arSelect = Array("ID", "IBLOCK_ID","PROPERTY_".self::PROPERTY_COMMENT_CNT_CODE);
            $arFilter = Array("IBLOCK_ID"=>$this->linkIblockId, 'ID' => $elementId);
            $res = \CIBlockElement::GetList(Array(), $arFilter, false, false, $arSelect);

            if ($item = $res->fetch()) {

                $count = $this->getTree()['count'];
                if ( $count > 0) {
                    try {
                        \CIBlockElement::SetPropertyValues($item['ID'], $item['IBLOCK_ID'], $count, self::PROPERTY_COMMENT_CNT_CODE);

                        global $CACHE_MANAGER;
                        $CACHE_MANAGER->ClearByTag('iblock_id_'.$this->linkIblockId);
                    } catch (Exception $e){
                    }
                }
            }
        }
    }

    /**
     * Обновить среднюю оценку в списке элементов
     */
    private function updateRang($elementId)
    {
        $avgRang = $this->getAvgRang();

        if ($elementId > 0 && $avgRang > 0) {
            $arSelect = Array("ID", "IBLOCK_ID","PROPERTY_".self::PROPERTY_AVG_RANG);
            $arFilter = Array("IBLOCK_ID"=>$this->linkIblockId, 'ID' => $elementId);
            $res = \CIBlockElement::GetList(Array(), $arFilter, false, false, $arSelect);

            if ($item = $res->fetch()) {
                try {
                    \CIBlockElement::SetPropertyValues($item['ID'], $item['IBLOCK_ID'], $avgRang, self::PROPERTY_AVG_RANG);
                    global $CACHE_MANAGER;
                    $CACHE_MANAGER->ClearByTag('iblock_id_'.$this->linkIblockId);
                } catch (Exception $e){

                }
            }
        }
    }

    public function executeComponent()
    {
        $this->setParamsToSession(); //Сохраняем параметры компанента в сессию
        $this->arResult['params_id'] = $this->getParamsId(); // Ключ параметров в сессии в котором хранятся настройки компонента
        $this->arResult = array_merge($this->arResult, $this->getTree());
        $this->includeComponentTemplate();
    }
}
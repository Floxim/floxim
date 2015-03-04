<?php

namespace Floxim\Floxim\System;

class Import
{

    /**
     * Относительный путь в каталоге экспорта до хранения данных БД
     *
     * @var null
     */
    protected $pathRelDataDb = null;
    /**
     * Относительный путь в каталоге экспорта до хранения файлов
     *
     * @var null
     */
    protected $pathRelDataFile = null;
    /**
     * Временная таблица для обработки импорта
     *
     * @var null
     */
    protected $tmpTable = null;
    /**
     * Мета информация экспорта
     *
     * @var null
     */
    protected $metaInfo = null;
    /**
     * Уникальный ключ текущего импорта
     *
     * @var null
     */
    protected $key = null;
    /**
     * Новый корневой узел куда производится импорт
     *
     * @var null
     */
    protected $contentRootNew = null;
    /**
     * Старый корневой узел, который экспортировался
     * Храним его для кеша, чтобы не запрашивать каждый раз. Храниться в виде массива, а не Entity
     *
     * @var null
     */
    protected $contentRootOld = null;
    /**
     * Сайт в который нужно производить импорт
     *
     * @var null
     */
    protected $siteNew = null;
    /**
     * Служебная переменная для хранения текущей карты маппинга id (old -> new)
     * Список в разрезе типов. Для контента общий тип "content"
     *
     * @var array
     */
    protected $mapIds = array();
    /**
     * Служебная переменная для списка колбэков по обработке id
     *
     * @var array
     */
    protected $callbackIdUpdateStack = array();

    function __construct($params = array())
    {
        $this->init($params);
    }

    /**
     * Инициализация
     *
     * @param $params
     */
    protected function init($params)
    {
        $this->tmpTable = 'import_tmp';
    }

    /**
     * Импорт контента из zip архива в узел дерева
     *
     * @param $zipFile
     * @param $pageInsert
     */
    public function importContentFromZip($pageInsert, $zipFile)
    {
        /**
         * todo: Распаковка архива
         */

        $unzipDir = '';
        return $this->importContentFromDir($pageInsert, $unzipDir);
    }

    /**
     * Импорт контента из каталога в узел дерева
     *
     * @param $dir
     * @param $pageInsert
     */
    public function importContentFromDir($pageInsert, $dir)
    {
        $dir = fx::path('@files/export/');
        /**
         * Проверим страницу назначение на существование
         */
        if (!($this->contentRootNew = fx::data('floxim.main.content', $pageInsert))) {
            throw new \Exception("Content by ID ({$pageInsert}) not found");
        }
        /**
         * Определяем текущий сайт
         * todo: нужно учесть при импорте всего сайта
         */
        if (!($this->siteNew = fx::env()->getSite())) {
            throw new \Exception("Not defined current site");
        }
        /**
         * Получаем мета информацию импорта
         */
        if (!$this->readMetaInfo($dir)) {
            throw new \Exception("Can't read meta info");
        }
        $this->pathRelDataDb = $this->metaInfo['paths']['data_db'];
        $this->pathRelDataFile = $this->metaInfo['paths']['data_file'];
        /**
         * Генерируем уникальный ключ для идентификации импорта в таблице временных данных
         */
        $this->generateKey();
        /**
         * Импортируем все модули с компонентами
         */
        $this->importModules($dir);
        /**
         * Предварительно загружаем все данные БД во временную таблицу
         */
        if (!$this->checkTmpTableExists()) {
            throw new \Exception("Not found import tmp table {$this->tmpTable}");
        }
        $this->loadAllDataToTmpTable($dir);


        $_this = $this;
        /**
         * Получаем старый корневой узел
         */
        $itemTmp = $this->getTmpRowContent($this->metaInfo['content_root_id']);
        $this->contentRootOld = $itemTmp['data'];
        /**
         * Импортируем системные данные
         */
        $this->readTmpDataTable(array(array('component_type', 'system')), function ($item) use ($_this) {
            $_this->insertSystemItem($item);
        });
        /**
         * Импортируем данные контента
         */
        $this->readTmpDataTable(array(array('component_type', 'content')), function ($item) use ($_this) {
            $_this->insertContentItem($item);
        });
        /**
         * Запускаем отложенные коллбэки
         */
        $this->runCallbacksIdUpdate();

        /**
         * Удаляем из таблицы временные данные
         */
        $this->removeDataFromTmpTable();
    }

    protected function readMetaInfo($dir)
    {
        $file = $dir . DIRECTORY_SEPARATOR . 'meta.json';
        if (file_exists($file) and $content = @file_get_contents($file) and $content = @json_decode($content, true)) {
            return $this->metaInfo = $content;
        }
        return false;
    }

    protected function runCallbacksIdUpdate()
    {
        foreach ($this->callbackIdUpdateStack as $callback) {
            $callback();
        }
    }

    protected function insertSystemItem($item)
    {
        if ($finder = fx::data($item['target_type'])) {
            $_this = $this;
            $itemIdNew = null;
            $createNew = true;

            /**
             * Заполняем данные
             */
            $data = array();
            foreach ($item['data'] as $field => $value) {
                // todo: можно убрать цикл, если нет дополнительной обработки полей
                if ($field != 'id') {
                    $data[$field] = $value;
                }
            }

            if ($item['target_type'] == 'site') {
                if ($this->siteNew) {
                    $itemIdNew = $existing['id'];
                    $createNew = false;
                } else {
                    /**
                     * Ищем сайт по домену
                     */
                    if ($existing = fx::data($item['target_type'])->where('domain', $data['domain'])->one()) {
                        $itemIdNew = $existing['id'];
                        $createNew = false;
                    }
                }
            }

            /**
             * Список линкованных полей полей в зависимости от типа
             */
            if ($item['target_type'] == 'infoblock') {
                /**
                 * todo: site_id - пропускаем
                 */

                /**
                 * page_id
                 */
                $linkIdOld = $data['page_id'];
                if (false === ($idLinkNew = $this->getIdNewForType($linkIdOld, 'content'))) {
                    $data['page_id'] = 0;
                    /**
                     * Добавляем коллбэк на получение ID после импорта всех данных
                     */
                    $this->addCallbackIdUpdate($item['target_type'], $itemIdNew, 'content', $linkIdOld, 'page_id');
                } else {
                    $data['page_id'] = $idLinkNew;
                }

                /**
                 * parent_infoblock_id
                 */
                $linkIdOld = $data['parent_infoblock_id'];
                if (false === ($idLinkNew = $this->getIdNewForType($linkIdOld, 'infoblock'))) {
                    $data['parent_infoblock_id'] = 0;
                    /**
                     * Добавляем коллбэк на получение ID после импорта всех данных
                     */
                    $this->addCallbackIdUpdate($item['target_type'], $itemIdNew, 'infoblock', $linkIdOld, 'parent_infoblock_id');
                } else {
                    $data['parent_infoblock_id'] = $idLinkNew;
                }

                /**
                 * todo: Связи из параметров
                 */

                /**
                 * todo: Условия инфоблоков conditions
                 */

                /**
                 * todo: Линкованные параметры
                 */
            }

            if ($item['target_type'] == 'infoblock_visual') {
                /**
                 * infoblock_id
                 */
                $linkIdOld = $data['infoblock_id'];
                if (false === ($idLinkNew = $this->getIdNewForType($linkIdOld, 'infoblock'))) {
                    $data['infoblock_id'] = 0;
                    /**
                     * Добавляем коллбэк на получение ID после импорта всех данных
                     */
                    $this->addCallbackIdUpdate($item['target_type'], $itemIdNew, 'infoblock', $linkIdOld, 'infoblock_id');
                } else {
                    $data['infoblock_id'] = $idLinkNew;
                }

                /**
                 * todo: Получаем ссылки на контент из визуальных параметров
                 */
            }

            if ($createNew) {
                $itemNew = $finder->create($data);
                $itemNew->save();
                $itemIdNew = $itemNew['id'];
            }
            $this->mapIds[$item['target_type']][$item['target_id']] = $itemIdNew;
        }
    }

    protected function insertContentItem($item)
    {
        if ($finder = fx::data($item['target_type'])) {
            $_this = $this;
            $itemIdNew = null;
            $createNew = true;

            /**
             * Заполняем данные
             */
            $data = array();
            foreach ($item['data'] as $field => $value) {
                // todo: можно убрать цикл, если нет дополнительной обработки полей
                if ($field != 'id') {
                    $data[$field] = $value;
                }
            }

            if ($item['target_type'] == 'floxim.user.user') {
                /**
                 * Пользователя нужно сначала искать по емайлу, если нет, то создаем по стандартной схеме
                 */
                if ($existing = fx::data($item['target_type'])->where('email', $data['email'])->one()) {
                    $itemIdNew = $existing['id'];
                    $createNew = false;
                }
            }


            /**
             * infoblock_id
             */
            $linkIdOld = $data['infoblock_id'];
            if (false === ($idLinkNew = $this->getIdNewForType($linkIdOld, 'infoblock'))) {
                $data['infoblock_id'] = 0;
                /**
                 * Добавляем коллбэк на получение ID после импорта всех данных
                 */
                $this->addCallbackIdUpdate($item['target_type'], $itemIdNew, 'infoblock', $linkIdOld, 'infoblock_id');
            } else {
                $data['infoblock_id'] = $idLinkNew;
            }

            /**
             * Дополнительные линкованные поля
             */
            if (isset($this->metaInfo['component_linked_fields'][$item['target_type']])) {
                $linkFields = $this->metaInfo['component_linked_fields'][$item['target_type']];
                foreach ($linkFields as $field => $fieldParams) {

                    /**
                     * Обработка полей "один к одному"
                     */
                    if ($fieldParams['type'] == \Floxim\Floxim\Component\Field\Entity::FIELD_LINK) {
                        if ($fieldParams['target_type'] == 'component') {
                            // для контента
                            $linkType = 'content';
                        } else {
                            // для системных
                            $linkType = $fieldParams['target_id'];
                        }

                        $linkIdOld = $data[$field];
                        if (false === ($idLinkNew = $this->getIdNewForType($linkIdOld, $linkType))) {
                            $data[$field] = 0;
                            /**
                             * Добавляем коллбэк на получение ID после импорта всех данных
                             */
                            $this->addCallbackIdUpdate($item['target_type'], $itemIdNew, $linkType, $linkIdOld, $field);
                        } else {
                            $data[$field] = $idLinkNew;
                        }
                    }

                    /**
                     * todo: Обработка полей "один ко многими" и "многие ко многим"
                     */

                }

            }


            if ($createNew) {
                $itemNew = $finder->create($data);
                $itemNew->save();
                $itemIdNew = $itemNew['id'];
            }
            $this->mapIds['content'][$item['target_id']] = $itemIdNew;
        }
    }

    /**
     * Добавляет коллбэк на обновление ID
     *
     * @param $itemType
     * @param $itemIdNew
     * @param $linkType
     * @param $linkIdOld
     * @param $linkField
     */
    protected function addCallbackIdUpdate($itemType, &$itemIdNew, $linkType, $linkIdOld, $linkField)
    {
        $_this = $this;
        $this->callbackIdUpdateStack[] = function () use ($_this, $itemType, &$itemIdNew, $linkType, $linkIdOld, $linkField) {
            $itemNew = fx::data($itemType, $itemIdNew);
            if ($itemNew and false !== ($idNew = $_this->getIdNewForType($linkIdOld, $linkType))) {
                $itemNew[$linkField] = $idNew;
                $itemNew->save();
            }
        };
    }

    /**
     * Получает новый ID из карты маппинга
     *
     * @param $idOld
     * @param $type
     * @return bool
     */
    protected function getIdNewForType($idOld, $type)
    {
        if (!$idOld) {
            /**
             * Дефолтное пустое значение
             */
            return $idOld;
        }
        if (isset($this->mapIds[$type]) and array_key_exists($idOld, $this->mapIds[$type])) {
            return $this->mapIds[$type][$idOld];
        }
        /**
         * Проверяем на корневой узел
         */
        if ($type == 'content' and $this->contentRootOld['parent_id'] == $idOld) {
            return $this->mapIds[$type][$idOld] = $this->contentRootNew['id'];
        }
        if ($type == 'infoblock' and $this->contentRootOld['infoblock_id'] == $idOld) {
            return $this->mapIds[$type][$idOld] = $this->contentRootNew['infoblock_id'];
        }
        return false;
    }

    /**
     * Импортируем новые модули
     *
     * @param $dir
     */
    protected function importModules($dir)
    {
        /**
         * todo: Смотрим модули и запускаем импорт каждого из них
         */

        $moduleDir = '';
        $moduleName = '';
        $this->importModule($moduleDir, $moduleName);
    }

    /**
     * Импортирует конкретный модуль
     *
     * @param $moduleDir
     * @param $moduleName
     */
    protected function importModule($moduleDir, $moduleName)
    {
        /**
         * todo: Проверяем, если этот модуль уже есть в системе, то пропускаем импорт
         */
    }

    /**
     * Проверяет наличи временной таблицы, если ее нет, то создает
     * todo: перенести в основной дамп
     *
     * @return bool
     * @throws \Exception
     */
    protected function checkTmpTableExists()
    {
        if (fx::db()->getRow('SHOW TABLES LIKE \'{{' . $this->tmpTable . '}}\' ')) {
            return true;
        }

        /**
         * Создаем таблицу
         */
        $sqlCreate = "
            CREATE TABLE IF NOT EXISTS {{" . $this->tmpTable . "}} (
            `id` int(11) NOT NULL,
              `key` varchar(50) NOT NULL,
              `target_id` int(11) NOT NULL,
              `target_type` varchar(250) NOT NULL,
              `component_type` varchar(250) NOT NULL,
              `data` mediumtext NOT NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8;

            ALTER TABLE {{" . $this->tmpTable . "}}
             ADD PRIMARY KEY (`id`), ADD KEY `key` (`key`), ADD KEY `target_id` (`target_id`,`target_type`), ADD KEY `component_type` (`target_id`,`component_type`);

            ALTER TABLE {{" . $this->tmpTable . "}}
            MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;
        ";

        fx::db()->query($sqlCreate);
        return true;
    }

    /**
     * Генерирует уникальный ключ текущего импорта
     */
    protected function generateKey()
    {
        $this->key = md5(fx::util()->genUuid());
    }

    /**
     * Запускаек загрузку всех данных во временную таблицу
     *
     * @param $dir
     */
    protected function loadAllDataToTmpTable($dir)
    {
        $mask = $dir . DIRECTORY_SEPARATOR . $this->pathRelDataDb . DIRECTORY_SEPARATOR . '*';
        if ($dirs = glob($mask, GLOB_ONLYDIR)) {
            foreach ($dirs as $dirType) {

                $componentType = pathinfo($dirType, PATHINFO_FILENAME);
                $mask = $dir . DIRECTORY_SEPARATOR . $this->pathRelDataDb . DIRECTORY_SEPARATOR . '*';
                if ($files = glob($dirType . DIRECTORY_SEPARATOR . '*')) {
                    foreach ($files as $file) {
                        $this->loadDataFileToTmpTable($file, $componentType);
                    }
                }
            }
        }
    }

    /**
     * Загружает данные во временную таблицу
     *
     * @param $file
     */
    protected function loadDataFileToTmpTable($file, $componentType)
    {
        $nameFile = basename($file);
        if (preg_match('#^(.+)\.dat$#i', $nameFile, $match)) {
            $type = $match[1];
            $_this = $this;
            $tmpFinder = new TableTmpFinder();
            $this->readJsonFile($file, function ($data) use ($_this, $tmpFinder, $type, $componentType) {
                $item = $tmpFinder->create(array(
                    'key'            => $_this->key,
                    'target_id'      => $data['id'],
                    'target_type'    => $type,
                    'component_type' => $componentType,
                    'data'           => $data,
                ));
                $item->save();
            });
        }
    }

    /**
     * Читает данные из json файла
     *
     * @param $file
     * @param callable $callback
     */
    protected function readJsonFile($file, \Closure $callback)
    {
        if (!file_exists($file)) {
            return;
        }
        if ($content = @file_get_contents($file) and $content = @json_decode($content, true)) {
            if (is_array($content)) {
                foreach ($content as $data) {
                    if (!is_null($callback)) {
                        $callback($data);
                    }
                }
            }
        }
    }

    /**
     * Удаляет из временной таблицы текущие данные
     *
     * @throws \Exception
     */
    protected function removeDataFromTmpTable()
    {
        if ($this->key) {
            fx::db()->query("delete from {{" . $this->tmpTable . "}} where `key` = '{$this->key}'");
        }
    }

    /**
     * Возвращает конкретную запись из временной таблицы по id и type с учетом текущего ключа импорта
     *
     * @param $id
     * @param $type
     * @return bool|null
     */
    protected function getTmpRow($id, $type)
    {
        $tmpFinder = new TableTmpFinder();
        return $tmpFinder->where('key', $this->key)
            ->where('target_id', $id)
            ->where('target_type', $type)
            ->one();
    }

    /**
     * Возвращает запись контента из временной таблице по id
     *
     * @param $id
     * @param $type
     * @return bool|null
     */
    protected function getTmpRowContent($id)
    {
        $tmpFinder = new TableTmpFinder();
        return $tmpFinder->where('key', $this->key)
            ->where('target_id', $id)
            ->where('component_type', 'content')
            ->one();
    }

    public function readTmpDataTable($filter = array(), \Closure $callback = null)
    {
        $finder = new TableTmpFinder();
        $curPage = 1;
        $perPage = 100;
        /**
         * Build filter
         */
        $items = $finder->order('id', 'asc');
        $finder->where('key', $this->key);
        if ($filter and is_array($filter)) {
            foreach ($filter as $filterItem) {
                $finder->where($filterItem[0], $filterItem[1], isset($filterItem[2]) ? $filterItem[2] : '=');
            }
        }
        /**
         * Retrieve items from db
         */
        $data = array();
        while ($items = $finder->limit(($curPage - 1) * $perPage, $perPage)->all() and $items->count()) {
            foreach ($items as $item) {
                if (!is_null($callback)) {
                    $callback($item);
                }
                $data[$item['id']] = $item->get();
            }
            $curPage++;
        }
        return $data;
    }
}


class TableTmpEntity extends Entity
{
    public function getFinder()
    {
        return new TableTmpFinder();
    }
}

class TableTmpFinder extends Finder
{
    protected $json_encode = array('data');

    public function __construct($table = null)
    {
        parent::__construct('import_tmp');
    }

    public function getEntityClassName()
    {
        return '\\Floxim\\Floxim\\System\\TableTmpEntity';
    }
}
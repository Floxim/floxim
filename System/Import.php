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
     * Текущий каталог импорта
     *
     * @var null
     */
    protected $currentDir = null;
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
     * Служебная переменная для хранения текущей карты маппинга файлов (old -> new)
     *
     * @var array
     */
    protected $mapFiles = array();
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
     * импорт сайта из архива
     *
     * @param $newSiteParams
     * @param $zipFile
     * @throws \Exception
     */
    public function importSiteFromZip($newSiteParams, $zipFile)
    {
        /**
         * todo: Распаковка архива
         */

        $unzipDir = '';

        // for debug
        $newSiteParams['name'] = $newSiteParams['domain'];
        $newSiteParams['language'] = 'ru';
        $unzipDir = $dir = fx::path('@files/export/');

        return $this->importSiteFromDir($newSiteParams, $unzipDir);
    }

    /**
     * Импорт сайта из каталога
     *
     * @param $newSiteParams
     * @param $dir
     * @throws \Exception
     */
    public function importSiteFromDir($newSiteParams, $dir)
    {
        /**
         * Проверяем на дублирование сайта
         */
        if (fx::data('site')->where('domain', $newSiteParams['domain'])->one()) {
            throw new \Exception("Site already exists: " . $newSiteParams['domain']);
        }
        /**
         * Создаем новый сайт из параметров
         */
        $site = fx::data('site')->create(array(
            'name'     => $newSiteParams['name'],
            'domain'   => $newSiteParams['domain'],
            //'layout_id' => $newSiteParams['layout_id'],
            'language' => $newSiteParams['language'],
            'checked'  => 1
        ));

        if (!$site->validate()) {
            $errors = $site->getValidateErrors();
            throw new \Exception("Can't create new site: " . var_export($errors, true));
        }
        $site->save();
        $this->siteNew = $site;
        /**
         * Запускаем импорт
         */
        $this->importContentFromDir(null, $dir);
        /**
         * Для сайта необходимо установить главную страницу и страницу 404
         * В мета информации есть ID старых страниц, по маппингу нужно найти новые
         */
        $site['index_page_id'] = $this->getIdNewForType($this->metaInfo['index_page_id'], 'content') ?: 0;
        $site['error_page_id'] = $this->getIdNewForType($this->metaInfo['error_page_id'], 'content') ?: 0;
        $site['layout_id'] = $this->getIdNewForType($this->metaInfo['layout_id'], 'layout') ?: 0;

        $site->save();
    }

    /**
     * Импорт контента из каталога в узел дерева
     *
     * @param $dir
     * @param $pageInsert
     */
    public function importContentFromDir($pageInsert, $dir)
    {
        $this->currentDir = $dir = fx::path('@files/export/');
        /**
         * Проверим страницу назначение на существование
         * Если null - это экспорт всего сайта
         */
        if ($pageInsert) {
            if (!($this->contentRootNew = fx::data('floxim.main.content', $pageInsert))) {
                throw new \Exception("Content by ID ({$pageInsert}) not found");
            }
        }
        /**
         * Определяем текущий сайт
         */
        if (!$this->siteNew and !($this->siteNew = fx::env()->getSite())) {
            throw new \Exception("Not defined current site");
        }
        /**
         * Получаем мета информацию импорта
         */
        if (!$this->readMetaInfo($dir)) {
            throw new \Exception("Can't read meta info");
        }
        if ((!$pageInsert and $this->metaInfo['export_type'] != 'site')
            or ($pageInsert and $this->metaInfo['export_type'] != 'content')
        ) {
            throw new \Exception("Неверный тип у источника импорта: " . $this->metaInfo['export_type']);
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
                    $itemIdNew = $this->siteNew['id'];
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

                if ($createNew) {
                    /**
                     * index_page_id
                     */
                    $linkIdOld = $data['index_page_id'];
                    if (false === ($idLinkNew = $this->getIdNewForType($linkIdOld, 'content'))) {
                        $data['index_page_id'] = 0;
                        /**
                         * Добавляем коллбэк на получение ID после импорта всех данных
                         */
                        $this->addCallbackIdUpdate($item['target_type'], $itemIdNew, 'content', $linkIdOld, 'index_page_id');
                    } else {
                        $data['index_page_id'] = $idLinkNew;
                    }
                    /**
                     * error_page_id
                     */
                    $linkIdOld = $data['error_page_id'];
                    if (false === ($idLinkNew = $this->getIdNewForType($linkIdOld, 'content'))) {
                        $data['error_page_id'] = 0;
                        /**
                         * Добавляем коллбэк на получение ID после импорта всех данных
                         */
                        $this->addCallbackIdUpdate($item['target_type'], $itemIdNew, 'content', $linkIdOld, 'error_page_id');
                    } else {
                        $data['error_page_id'] = $idLinkNew;
                    }
                    /**
                     * layout_id
                     */
                    $linkIdOld = $data['layout_id'];
                    if (false === ($idLinkNew = $this->getIdNewForType($linkIdOld, 'layout'))) {
                        $data['layout_id'] = 0;
                        /**
                         * Добавляем коллбэк на получение ID после импорта всех данных
                         */
                        $this->addCallbackIdUpdate($item['target_type'], $itemIdNew, 'layout', $linkIdOld, 'layout_id');
                    } else {
                        $data['layout_id'] = $idLinkNew;
                    }
                }
            }

            if ($item['target_type'] == 'layout') {
                /**
                 * Ищем layout по ключевику
                 */
                if ($existing = fx::data($item['target_type'])->where('keyword', $data['keyword'])->one()) {
                    $itemIdNew = $existing['id'];
                    $createNew = false;
                }
            }

            /**
             * Список линкованных полей полей в зависимости от типа
             */
            if ($item['target_type'] == 'infoblock') {
                /**
                 * site_id
                 */
                $linkIdOld = $data['site_id'];
                if (false === ($idLinkNew = $this->getIdNewForType($linkIdOld, 'site'))) {
                    $data['site_id'] = 0;
                    /**
                     * Добавляем коллбэк на получение ID после импорта всех данных
                     */
                    $this->addCallbackIdUpdate($item['target_type'], $itemIdNew, 'site', $linkIdOld, 'site_id');
                } else {
                    $data['site_id'] = $idLinkNew;
                }

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

                $params = $data['params'];
                /**
                 * Связи из параметров
                 */
                if (isset($params['extra_infoblocks']) and is_array($params['extra_infoblocks'])) {
                    /**
                     * Обновляем параметры через коллбэк
                     */
                    $this->callbackIdUpdateStack[] = function () use ($_this, &$itemIdNew) {
                        if ($itemNew = fx::data('infoblock', $itemIdNew)) {
                            $params = $itemNew['params'];
                            foreach ($params['extra_infoblocks'] as $k => $id) {
                                if (false !== ($idNew = $_this->getIdNewForType($id, 'infoblock'))) {
                                    $params['extra_infoblocks'][$k] = $idNew;
                                } else {
                                    unset($params['extra_infoblocks'][$k]);
                                }
                            }
                            $itemNew['params'] = $params;
                            $itemNew->save();
                        }
                    };
                }

                /**
                 * Условия инфоблоков conditions
                 */
                if (isset($params['conditions']) and $params['conditions']) {
                    $this->callbackIdUpdateStack[] = function () use ($_this, &$itemIdNew, $data) {
                        if ($itemNew = fx::data('infoblock', $itemIdNew)) {
                            $params = $itemNew['params'];
                            $component = fx::controller($data['controller'])->getComponent();
                            if (isset($_this->metaInfo['component_linked_fields'][$component['keyword']])) {
                                $linkedFields = $_this->metaInfo['component_linked_fields'][$component['keyword']];
                                /**
                                 * Перебираем все поля в условии
                                 */
                                foreach ($params['conditions'] as $k => $fieldCond) {
                                    if (isset($linkedFields[$fieldCond['name']]) and $fieldCond['value']) {
                                        $linkedField = $linkedFields[$fieldCond['name']];
                                        $valueNew = array();
                                        foreach ($fieldCond['value'] as $id) {
                                            if ($linkedField['target_type'] == 'component') {
                                                $type = 'content';
                                            } else {
                                                $type = $linkedField['target_id'];
                                            }
                                            if ($idNew = $_this->getIdNewForType($id, $type)) {
                                                $valueNew[] = $idNew;
                                            }
                                        }
                                        $params['conditions'][$k]['value'] = $valueNew;
                                    }
                                    /**
                                     * Т.к. инфоблок реализован не через механизм дополнительных полей
                                     */
                                    if ($fieldCond['name'] == 'infoblock_id') {
                                        $valueNew = array();
                                        foreach ($fieldCond['value'] as $id) {
                                            if ($idNew = $_this->getIdNewForType($id, 'infoblock')) {
                                                $valueNew[] = $idNew;
                                            }
                                        }
                                        $params['conditions'][$k]['value'] = $valueNew;
                                    }
                                }
                                $itemNew['params'] = $params;
                                $itemNew->save();
                            }
                        }
                    };
                }


                /**
                 * Линкованные параметры
                 */
                // они обрабатываются автоматически за счет обычных связей в линкере, по аналогии с типом many-many
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
                 * layout_id
                 */
                $linkIdOld = $data['layout_id'];
                if (false === ($idLinkNew = $this->getIdNewForType($linkIdOld, 'layout'))) {
                    $data['layout_id'] = 0;
                    /**
                     * Добавляем коллбэк на получение ID после импорта всех данных
                     */
                    $this->addCallbackIdUpdate($item['target_type'], $itemIdNew, 'layout', $linkIdOld, 'layout_id');
                } else {
                    $data['layout_id'] = $idLinkNew;
                }

                /**
                 * area
                 */
                if (preg_match('#^(.+_)(\d+)$#', $data['area'], $match)) {
                    $this->callbackIdUpdateStack[] = function () use ($_this, &$itemIdNew, $match) {
                        if ($itemNew = fx::data('infoblock_visual', $itemIdNew)) {
                            if ($itemNew and false !== ($idNew = $_this->getIdNewForType($match[2], 'infoblock'))) {
                                $itemNew['area'] = $match[1] . $idNew;
                                $itemNew->setNeedRecountFiles(false);
                                $itemNew->save();
                            }
                        }
                    };
                }

                /**
                 * Получаем ссылки на контент из визуальных параметров
                 */
                $visuals = $data['template_visual'];
                if (is_array($visuals)) {
                    $startCallback = false;
                    foreach ($visuals as $name => $value) {
                        /**
                         * Ищем линки на изображения
                         */
                        if (preg_match('#^\/floxim_files\/#i', $value)) {
                            $visuals[$name] = $_this->importFile($value);
                        }

                        if (!$startCallback and preg_match('#^(.+_)(\d+)$#', $name, $match)) {
                            /**
                             * Есть ссылочные параметры - запускаем коллбэк обработку
                             */
                            $this->callbackIdUpdateStack[] = function () use ($_this, &$itemIdNew) {
                                if ($itemNew = fx::data('infoblock_visual', $itemIdNew)) {
                                    $visuals = $itemNew['template_visual'];
                                    foreach ($visuals as $name => $value) {
                                        if (preg_match('#^(.+_)(\d+)$#', $name, $match)) {
                                            unset($visuals[$name]);
                                            if ($idNew = $_this->getIdNewForType($match[2], 'content')) {
                                                $visuals[$match[1] . $idNew] = $value;
                                            }
                                        }
                                    }
                                    $itemNew['template_visual'] = $visuals;
                                    $itemNew->setNeedRecountFiles(false);
                                    $itemNew->save();
                                }
                            };
                            $startCallback = true;
                        }
                    }
                    $data['template_visual'] = $visuals;
                }
            }

            if ($createNew) {
                $itemNew = $finder->create($data);
                if ($item['target_type'] == 'infoblock_visual') {
                    /**
                     * Отключаем специфичную обработку
                     * Далее она сработает при сохранении из коллбэков
                     */
                    $itemNew->setNeedRecountFiles(false);
                }
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
                     * Обработка полей "один ко многими" и "многие ко многим"
                     */
                    // Обрабатывается автоматически по связям линкера

                }
            }

            /**
             * Дополнительные поля с изображением
             * todo: сюда попадает поле avatar пользователя - что с ним делать пока не ясно
             */
            if ($item['target_type'] != 'floxim.user.user') {
                if (isset($this->metaInfo['component_image_fields'][$item['target_type']])) {
                    $imageFields = $this->metaInfo['component_image_fields'][$item['target_type']];
                    foreach ($imageFields as $fieldParams) {
                        if ($fieldParams['type'] == \Floxim\Floxim\Component\Field\Entity::FIELD_IMAGE) {
                            $image = $data[$fieldParams['keyword']];
                            $data[$fieldParams['keyword']] = $this->importFile($image);
                        }
                    }
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
                if ($itemType == 'infoblock_visual') {
                    $itemNew->setNeedRecountFiles(false);
                }
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
        if ($this->contentRootNew) {
            if ($type == 'content' and $this->contentRootOld['parent_id'] == $idOld) {
                return $this->mapIds[$type][$idOld] = $this->contentRootNew['id'];
            }
            if ($type == 'infoblock' and $this->contentRootOld['infoblock_id'] == $idOld) {
                return $this->mapIds[$type][$idOld] = $this->contentRootNew['infoblock_id'];
            }
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
     * Импортирует файл из каталога импорта
     * Дополнительно проверяется соответствие по карте маппинга
     *
     * @param $file
     * @return string
     */
    protected function importFile($file)
    {
        $fileFullPath = $this->currentDir . DIRECTORY_SEPARATOR . $this->pathRelDataFile . $file;
        if (array_key_exists($fileFullPath, $this->mapFiles)) {
            return $this->mapFiles[$fileFullPath];
        }

        if (file_exists($fileFullPath)) {
            $fileName = pathinfo($file, PATHINFO_FILENAME);
            $filePath = pathinfo($file, PATHINFO_DIRNAME);
            $fileExt = pathinfo($file, PATHINFO_EXTENSION);
            $pathDest = fx::path($filePath) . DIRECTORY_SEPARATOR;

            $i = 0;
            $fileNameUniq = $fileName . '.' . $fileExt;
            /**
             * Формируем уникальное имя с проверкой на существование
             */
            while (file_exists($pathDest . $fileNameUniq)) {
                $i++;
                $fileNameUniq = $fileName . '_' . $i . '.' . $fileExt;
            }
            fx::files()->copy($fileFullPath, $pathDest . $fileNameUniq);
            /**
             * Возвращать нужно относительный путь
             */
            return $this->mapFiles[$fileFullPath] = $filePath . DIRECTORY_SEPARATOR . $fileNameUniq;
        }
        return null;
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
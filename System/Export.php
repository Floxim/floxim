<?php

namespace Floxim\Floxim\System;

class Export
{

    /**
     * Путь до временного каталога экспорта
     *
     * @var null
     */
    protected $pathExportTmp = null;
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
     * Служебная переменная для хранения текущего списка экспортируемых компонентов
     *
     * @var array
     */
    protected $componentsForExport = array();
    /**
     * Служебная переменная для хранения уже экспортированных элементов контента. Необходима для учета дублей при экспорте.
     *
     * @var array
     */
    protected $contentsForExport = array();
    /**
     * Служебная переменная для хранения уже экспортированных элементов системных таблиц. Необходима для учета дублей при экспорте.
     * Значения хранятся в разрезах названия сущностей
     *
     * @var array
     */
    protected $systemItemsForExport = array();
    /**
     * Служебная переменная для хранения текущих открытых для записи данных файлов
     *
     * @var array
     */
    protected $exportFilesOpened = array();


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
        if (isset($params['pathExportTmp'])) {
            $this->pathExportTmp = $params['pathExportTmp'];
        } else {
            $this->pathExportTmp = fx::path('@files/export/');
        }
        $this->pathRelDataDb = 'data' . DIRECTORY_SEPARATOR . 'db';
        $this->pathRelDataFile = 'data' . DIRECTORY_SEPARATOR . 'file';
    }


    protected function exportSystemItemsByField($type, $field, $values)
    {
        /**
         * Можно оптимизировать, чтобы обойтись одним запросом и одним проходом результата
         */
        $ids = array();
        $this->readDataTable($type, array(array($field, $values)), function ($item) use (&$ids) {
            $ids[] = $item['id'];
        });
        $this->exportSystemItems($type, $ids);
    }

    protected function exportSystemItems($type, $ids)
    {
        if (!is_array($ids)) {
            $ids = array($ids);
        }
        $ids = array_unique($ids);

        if (isset($this->systemItemsForExport[$type])) {
            $ids = array_diff($ids, $this->systemItemsForExport[$type]);
        }

        $usedSystemItems = array();
        $usedContentItems = array();
        $_this = $this;
        $this->readDataTable($type, array(array('id', $ids)),
            function ($item) use ($_this, $type, &$usedSystemItems, &$usedContentItems) {
                /**
                 * Сохраняем элемент в файл
                 */
                $_this->saveTableRowToFile($item, $type);
                $_this->systemItemsForExport[$type][] = $item['id'];
                /**
                 * Хардкодим для специфичных типов
                 */
                if ($type == 'infoblock') {
                    /**
                     * Формируем список дополнительных элементов по связям
                     */

                    /**
                     * Парент
                     */
                    if ($item['parent_infoblock_id'] and (!isset($usedSystemItems['infoblock']) or !in_array($item['parent_infoblock_id'],
                                $usedSystemItems['infoblock']))
                    ) {
                        $usedSystemItems['infoblock'][] = $item['parent_infoblock_id'];
                    }
                    /**
                     * Сайт
                     * todo: здесь нужно продумать, т.к. не понятно для чего экспортить сайт, если мы сделать экспорт только ветки дерева
                     */
                    if ($item['site_id'] and (!isset($usedSystemItems['site']) or !in_array($item['site_id'],
                                $usedSystemItems['site']))
                    ) {
                        $usedSystemItems['site'][] = $item['site_id'];
                    }
                    /**
                     * Страница контента
                     */
                    if ($item['page_id'] and !in_array($item['page_id'], $usedContentItems)) {
                        $usedContentItems[] = $item['page_id'];
                    }
                    /**
                     * Связи из параметров
                     */
                    $params = $item['params'];
                    if (isset($params['extra_infoblocks']) and is_array($params['extra_infoblocks'])) {
                        foreach ($params['extra_infoblocks'] as $id) {
                            $usedSystemItems['infoblock'][] = $item['parent_infoblock_id'];
                        }
                    }
                    /**
                     * Условия инфоблоков conditions
                     */
                    if (isset($params['conditions']) and $params['conditions']) {
                        /**
                         * Получаем компонент через контроллер инфоблока, он необходим для получения списка линкованных полей
                         */
                        $component = fx::controller($item['controller'])->getComponent();
                        $linkedFields = $_this->getLinkedFieldsForComponent($component);
                        /**
                         * Перебираем все поля в условии
                         */
                        foreach ($params['conditions'] as $fieldCond) {
                            if (isset($linkedFields[$fieldCond['name']]) and $fieldCond['value']) {
                                $linkedField = $linkedFields[$fieldCond['name']];
                                $_this->processingLinkedField($linkedField, $fieldCond['value'], $usedContentItems,
                                    $usedSystemItems);
                            }
                            /**
                             * Т.к. инфоблок реализован не через механизм дополнительных полей
                             */
                            if ($fieldCond['name'] == 'infoblock_id') {
                                $usedSystemItems['infoblock'] = array_merge($usedSystemItems['infoblock'],
                                    $fieldCond['value']);
                            }
                        }
                    }
                } elseif ($type == 'infoblock_visual') {
                    /**
                     * TODO: пока не понятно, что делать с layout_id
                     */

                    /**
                     * Получаем ссылки на контент из визуальных параметров
                     * todo: проблема - ссылки могут быть не только на контент, но и на инфоблоки, в итоге валит ошибку
                     */
                    $visuals = $item['template_visual'];
                    if (is_array($visuals)) {
                        foreach ($visuals as $name => $value) {
                            if (preg_match('#._(\d+)$#', $name, $match)) {
                                if (!in_array($match[1], $usedContentItems)) {
                                    $usedContentItems[] = $match[1];
                                }
                            }
                        }
                    }
                }
            }
        );

        /**
         * Экспортируем привязанные к инфоблокам infoblock_visual
         */
        if ($type == 'infoblock') {
            $this->exportSystemItemsByField('infoblock_visual', 'infoblock_id', $ids);
        }

        /**
         * Экспортируем связанные данные
         */
        foreach ($usedSystemItems as $linkType => $linkIds) {
            $this->exportSystemItems($linkType, $linkIds);
        }
        /**
         * Связанный контент
         */
        $this->exportContentTreeArray($usedContentItems);
    }

    /**
     * Запускат процесс экспорта контента
     *
     * @param int $contentId
     * @throws \Exception
     */
    public function exportContent($contentId)
    {
        /**
         * Начальный список компонентов и контента
         */
        $this->componentsForExport = array();
        $this->contentsForExport = array();
        $this->systemItemsForExport = array();
        $this->exportFilesOpened = array();
        /**
         * Рекурсивный экспорт ветки дерева
         */
        $this->exportContentTree($contentId);
        /**
         * Корректно завершаем файлы экспорта
         */
        $this->finishAllExportOpenedFiles();

        $this->exportComponents($this->componentsForExport);
    }

    protected function exportContentTree($contentId)
    {
        $contentFilter = array();
        if ($content = fx::data('floxim.main.content', $contentId)) {
            $contentFilter[] = array('materialized_path', $content['materialized_path'] . '%', 'like');
            $contentFilter[] = array('parent_id', $content['parent_id'], '<>');
        } else {
            //throw new \Exception("Content by ID ({$contentId}) not found");
            return;
        }

        $usedTypes = array();
        $usedSystemItems = array();
        /**
         * Обработка каждого узла, здесь нужно формировать вспомогательные данные
         */
        $callback = function ($item) use (&$usedTypes, &$usedSystemItems) {
            $usedTypes[$item['type']][] = $item['id'];
            if ($item['infoblock_id'] and (!isset($usedSystemItems['infoblock']) or !in_array($item['infoblock_id'],
                        $usedSystemItems['infoblock']))
            ) {
                $usedSystemItems['infoblock'][] = $item['infoblock_id'];
            }
        };
        /**
         * Текущий узел
         */
        if (isset($content)) {
            $callback($content);
        }
        /**
         * Все дочерние узлы
         */
        $this->readDataTable('floxim.main.content', $contentFilter, $callback);
        /**
         * Экспортируем системные данные
         */
        foreach ($usedSystemItems as $type => $ids) {
            $this->exportSystemItems($type, $ids);
        }

        $this->componentsForExport = array_merge($this->componentsForExport, array_keys($usedTypes));
        /**
         * Дополнительно проходим все типы
         */
        foreach ($usedTypes as $type => $contentIds) {
            /**
             * Для каждого компонента нужно получить список линкованных полей
             */
            $linkedFields = $this->getLinkedFieldsForComponent($type);
            $imageFields = $this->getImageFieldsForComponent($type);
            $_this = $this;
            $linkedContent = array();
            $linkedSystemItems = array();

            $this->readDataTable($type, array(array('id', $contentIds)),
                function ($item) use (
                    $type,
                    $linkedFields,
                    $imageFields,
                    $usedTypes,
                    $_this,
                    &$linkedContent,
                    &$linkedSystemItems
                ) {
                    /**
                     * Сохраняем элемент в файл
                     */
                    if (!in_array($item['id'], $_this->contentsForExport)) {
                        $_this->saveTableRowToFile($item, $type);
                        $_this->contentsForExport[] = $item['id'];
                    } else {
                        /**
                         * Нет смысла повторно обрабатывать контент
                         */
                        return;
                    }
                    /**
                     * Некоторые поля могут содержать линкованные данные на другие таблицы
                     * Нужно проверять поля на тип
                     */
                    foreach ($linkedFields as $linkedField) {
                        $_this->processingLinkedField($linkedField, $item[$linkedField['keyword']], $linkedContent,
                            $linkedSystemItems);
                    }
                    /**
                     * Экспортируем изображения
                     */
                    foreach ($imageFields as $imageField) {
                        $_this->processingImageField($imageField, $item[$imageField['keyword']]);
                    }
                });

            /**
             * Экспортируем привязанные к контенту инфоблоки
             */
            $this->exportSystemItemsByField('infoblock', 'page_id', $contentIds);

            /**
             * Экспортируем системные данные
             */
            foreach ($linkedSystemItems as $typeSystem => $ids) {
                $this->exportSystemItems($typeSystem, $ids);
            }

            /**
             * Вычитаем контент, который уже был экспортирован
             */
            $linkedContent = array_diff($linkedContent, $_this->contentsForExport);
            /**
             * Для каждого узла запускаем экспорт ветки
             */
            $this->exportContentTreeArray($linkedContent);
        }
        /**
         * Экспортируем дополнительно собранные данные, например, инфоблоки
         */

    }

    protected function exportContentTreeArray($ids)
    {
        if (!is_array($ids)) {
            $ids = array($ids);
        }
        $ids = array_unique($ids);
        /**
         * Для каждого узла запускаем экспорт ветки
         * todo: попробовать перевести на content\finder::descendantsOf
         */
        foreach ($ids as $id) {
            $this->exportContentTree($id);
        }
    }

    /**
     * Экспортирует набор компонентов
     * Метод является хелпером, т.к. экспортироваться могут только модули со всеми компонентами
     *
     * @param null $componentKeywords
     */
    public function exportComponents($componentKeywords = null)
    {
        if (is_null($componentKeywords)) {
            $componentKeywords = $this->componentsForExport;
        }
        if (!is_array($componentKeywords)) {
            $componentKeywords = array($componentKeywords);
        }

        $componentKeywords = array_unique($componentKeywords);
        /**
         * Выделяем список модулей из компонентов и экспортируем их
         */


    }

    protected function getImageFieldsForComponent($componentKeyword)
    {
        if (is_object($componentKeyword)) {
            $component = $componentKeyword;
        } else {
            if (!($component = fx::data('component', $componentKeyword))) {
                return array();
            }
        }

        $fields = $component->getAllFields()->find(function ($f) {
            return in_array($f->getTypeId(), array(\Floxim\Floxim\Component\Field\Entity::FIELD_IMAGE));
        });

        return $fields;
    }

    /**
     * Возвращает список полей компонента, которые задействуют линковку на другие объекты
     *
     * @param $componentKeyword
     * @return array
     * @throws \Exception
     */
    protected function getLinkedFieldsForComponent($componentKeyword)
    {
        $types = array();
        if (is_object($componentKeyword)) {
            $component = $componentKeyword;
        } else {
            if (!($component = fx::data('component', $componentKeyword))) {
                return $types;
            }
        }

        $chain = $component->getChain();
        $componetChainIds = array();
        foreach ($chain as $c_level) {
            $componetChainIds[] = $c_level['id'];
        }
        if (!$componetChainIds) {
            return $types;
        }
        /**
         * Получаем список линкованных полей для компонента
         */
        $linkFields = fx::data('field')->where('component_id', $componetChainIds)->where('type', array(
            \Floxim\Floxim\Component\Field\Entity::FIELD_LINK,
            \Floxim\Floxim\Component\Field\Entity::FIELD_MULTILINK
        ))->all();
        foreach ($linkFields as $field) {
            $item = array(
                'keyword' => $field['keyword'],
                'type'    => $field['type'],
            );
            $format = $field['format'];
            /**
             * Обработка формата "один к одному"
             */
            if ($field['type'] == \Floxim\Floxim\Component\Field\Entity::FIELD_LINK) {
                $target = $format['target'];
                if (is_numeric($target)) {
                    $item['target_id'] = fx::data('component', $target)->get('keyword');
                    $item['target_type'] = 'component';
                } else {
                    $item['target_id'] = $target;
                    $item['target_type'] = 'system';
                }
            } elseif ($field['type'] == \Floxim\Floxim\Component\Field\Entity::FIELD_MULTILINK) {
                /**
                 * Обработка формата "один ко многим"
                 */


            }
            $types[$item['keyword']] = $item;
        }
        return $types;
    }

    public function readContent()
    {
        /**
         * Основные этапы:
         * 1. чтение необходимых данных - БД, файлы php, пользовательские файлы (img, ...)
         * 2. упаковка данных в нужную структуру
         * 3. сохранение структуры в архив
         *
         * Импорт:
         * 1. распаковка архива
         * 2. чтение данных архива в структуру
         * 3. запись данных - БД, файлы php, пользовательские файлы (img, ...)
         */

        /**
         *
         */


        /**
         * Модули должны быть автономными - возможность экспорта/импорта отдельного модуля.
         *
         * Нужно отделить структуру от данных:
         * 1. модули - в отдельном каталоге modules/[name]/[src]/   modules/[name]/[seed]/
         * 2. данные - есть данные БД, есть пользовательские файлы
         * 3. шаблон
         * Вариант структуры:
         * /module/ - инсталляции модулей
         * /data/
         * /data/db/ - данные из БД, каждый файл отдельная таблица
         * /data/file/ - полные пути до файлов относительно корня сайта
         * /template/ - шаблон в каталоге со своим названием
         */
    }

    /**
     * Порционное получение данных из БД
     *
     * @param $datatype
     * @param array $filter
     * @param callable $callback
     * @return array
     * @throws \Exception
     */
    public function readDataTable($datatype, $filter = array(), \Closure $callback = null)
    {
        $finder = fx::data($datatype);
        $curPage = 1;
        $perPage = 100;
        /**
         * Build filter
         */
        $items = $finder->order('id', 'asc');
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

    /**
     * Сохраняет данные из таблицы БД в отдельный json файл
     *
     * @param $datatype
     * @param null $fileSave
     * @param array $filter
     * @param callable $callback
     * @throws Exception\Files
     */
    public function dumpDataTable($datatype, $fileSave = null, $filter = array(), \Closure $callback = null)
    {
        $data = $this->readDataTable($datatype, $filter, $callback);
        /**
         * Save to file
         */
        $fileSave = $fileSave ?: "{$datatype}.dat";
        $path = $this->pathExportTmp . DIRECTORY_SEPARATOR . $this->pathRelDataDb;
        $fileSave = $path . DIRECTORY_SEPARATOR . $fileSave;
        fx::files()->mkdir($path);
        fx::files()->writefile($fileSave, json_encode($data));
    }

    protected function saveTableRowToFile($item, $datatype, $fileSave = null)
    {
        if (is_object($item)) {
            $item = $item->get();
        }
        /**
         * Save to file
         */
        $fileSave = $fileSave ?: "{$datatype}.dat";
        $path = $this->pathExportTmp . DIRECTORY_SEPARATOR . $this->pathRelDataDb;
        $fileSave = $path . DIRECTORY_SEPARATOR . $fileSave;
        fx::files()->mkdir($path);

        if (in_array($fileSave, $this->exportFilesOpened)) {
            $this->writeFile($fileSave, ",\n" . json_encode($item), 'a');
        } else {
            $this->writeFile($fileSave, "[\n" . json_encode($item), 'w');
            $this->exportFilesOpened[] = $fileSave;
        }
    }

    protected function finishAllExportOpenedFiles()
    {
        foreach ($this->exportFilesOpened as $file) {
            $this->writeFile($file, "\n]", 'a');
        }
    }

    protected function writeFile($filename, $filedata = '', $modeOpen = 'a')
    {
        $fh = fx::files()->open($filename, $modeOpen);
        fputs($fh, $filedata);
        fclose($fh);
        return 0;
    }

    protected function processingLinkedField($linkedField, $value, &$linkedContent, &$linkedSystemItems)
    {
        if ($linkedField['type'] == \Floxim\Floxim\Component\Field\Entity::FIELD_LINK) {
            /**
             * Линкуемое значение
             */
            if (!$value) {
                return;
            }
            if (!is_array($value)) {
                $value = array($value);
            }

            /**
             * Обработка связи "один к одному"
             */
            if ($linkedField['target_type'] == 'component') {
                /**
                 * Добавляем линкуемый компонент в число экспортируемых
                 */
                $this->componentsForExport[] = $linkedField['target_id'];

                /**
                 * Пропускаем те элементы, которые уже есть в списке
                 * todo: Проверить пропуск этого условия
                 */
                /**
                 * if (isset($usedTypes[$linkedField['target_id']]) and in_array($value,
                 * $usedTypes[$linkedField['target_id']])
                 * ) {
                 * return;
                 * }
                 */


                /**
                 * Формируем новый список ID элементов контента
                 * Для каждого такого элемента необходимо будет получить полное дочернее дерево
                 * Еще проблема в том, что тип из настроек поля может не совпадать с фактическим конечным типом элемента
                 */
                $linkedContent = array_merge($linkedContent, $value);

            } elseif ($linkedField['target_type'] == 'system') {
                if (!isset($linkedSystemItems[$linkedField['target_id']])) {
                    $linkedSystemItems[$linkedField['target_id']] = array();
                }
                $linkedSystemItems[$linkedField['target_id']] = array_merge($linkedSystemItems[$linkedField['target_id']],
                    $value);
            }
        } elseif ($linkedField['type'] == \Floxim\Floxim\Component\Field\Entity::FIELD_MULTILINK) {
            /**
             * Обработка связи "один ко многим"
             */


        }
    }

    protected function processingImageField($field, $value)
    {
        if ($field['type'] == \Floxim\Floxim\Component\Field\Entity::FIELD_IMAGE) {
            /**
             * Путь до изображения
             */
            if (!$value) {
                return;
            }
            /**
             * Копируем файл
             */
            $this->exportFile($value);
        }
    }

    protected function exportFile($fileRel)
    {
        $pathSource = fx::path($fileRel);
        $pathDist = $this->pathExportTmp . DIRECTORY_SEPARATOR . $this->pathRelDataFile . $fileRel;
        if (!file_exists($pathDist)) {
            fx::files()->copy($pathSource, $pathDist);
        }
    }
}
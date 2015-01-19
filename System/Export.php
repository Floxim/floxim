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
     * Относительный пусть в каталоге экспорта до хранения данных БД
     *
     * @var null
     */
    protected $pathRelDataDb = null;
    /**
     * Служебная переменная для хранения текущего списка экспортируемых компонентов
     *
     * @var array
     */
    protected $componentsForExport = array();

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
    }

    /**
     * Запускат процесс экспорта контента
     *
     * @param $siteId
     * @param null $contentId
     * @throws \Exception
     */
    public function exportContent($siteId, $contentId = null)
    {
        $contentFilter = array(
            array('site_id', $siteId)
        );
        if ($contentId) {
            if ($content = fx::data('floxim.main.content', $contentId)) {
                $contentFilter[] = array('materialized_path', $content['materialized_path'] . '%', 'like');
                $contentFilter[] = array('parent_id', $content['parent_id'], '<>');
            } else {
                throw new \Exception("Content by ID ({$contentId}) not found");
            }
        }

        $usedTypes = array();
        $usedInfoblocks = array();
        /**
         * Обработка каждого узла, здесь нужно формировать вспомогательные данные
         */
        $callback = function ($item) use (&$usedTypes, &$usedInfoblocks) {
            $usedTypes[$item['type']][] = $item['id'];
            if ($item['infoblock_id'] and !in_array($item['infoblock_id'], $usedInfoblocks)) {
                $usedInfoblocks[] = $item['infoblock_id'];
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
         * Начальный список компонентов
         */
        $this->componentsForExport = array_keys($usedTypes);

        /**
         * Дополнительно проходим все типы
         */
        foreach ($usedTypes as $type => $contentIds) {
            /**
             * Для каждого компонента нужно получить список линкованных полей
             */
            $linkedFields = $this->getLinkedFieldsForComponent($type);
            $_this=$this;

            $this->readDataTable($type, array(array('id', $contentIds)),
                function ($item) use ($linkedFields, $usedTypes, $_this) {
                    /**
                     * Некоторые поля могут содержать линкованные данные на другие таблицы
                     * Нужно проверять поля на тип
                     */
                    foreach ($linkedFields as $linkedField) {
                        if ($linkedField['type'] == \Floxim\Floxim\Component\Field\Entity::FIELD_LINK) {
                            /**
                             * Обработка связи "один к одному"
                             */
                            if ($linkedField['target_type'] == 'component') {
                                /**
                                 * Добавляем линкуемый компонент в число экспортируемых
                                 */
                                $_this->componentsForExport[]=$linkedField['target_id'];
                                /**
                                 * Линкуемое значение
                                 */
                                if (!($value = $item[$linkedField['keyword']])) {
                                    continue;
                                }
                                /**
                                 * Пропускаем те элементы, которые уже есть в списке
                                 */
                                if (isset($usedTypes[$linkedField['target_id']]) and in_array($value,
                                        $usedTypes[$linkedField['target_id']])
                                ) {
                                    continue;
                                }
                                /**
                                 * Формируем новый список ID элементов контента
                                 * Для каждого такого элемента необходимо будет получить полное дочернее дерево
                                 */


                            }
                        } elseif ($linkedField['type'] == \Floxim\Floxim\Component\Field\Entity::FIELD_MULTILINK) {
                            /**
                             * Обработка связи "один ко многим"
                             */


                        }
                    }
                });
        }

        //var_dump($usedInfoblocks);
        //var_dump($usedTypes);


        $this->exportComponents($this->componentsForExport);
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
        if (!($component = fx::data('component', $componentKeyword))) {
            return $types;
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
            $types[] = $item;
        }
        fx::debug($types);
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
}
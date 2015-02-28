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
     * Уникальный ключ текущего импорта
     *
     * @var null
     */
    protected $key = null;

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
        $this->pathRelDataDb = 'data' . DIRECTORY_SEPARATOR . 'db';
        $this->pathRelDataFile = 'data' . DIRECTORY_SEPARATOR . 'file';
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
        if (!($contentInsert = fx::data('floxim.main.content', $pageInsert))) {
            throw new \Exception("Content by ID ({$pageInsert}) not found");
        }
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

        /**
         * Непосредственный импорт контента
         * 1.
         */


        /**
         * Удаляем из таблицы временные данные
         */
        $this->removeDataFromTmpTable();
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
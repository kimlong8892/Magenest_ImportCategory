<?php

namespace Magenest\ImportCategory\Model\Import;

use Exception;
use Magento\Catalog\Api\CategoryRepositoryInterface;
use Magento\Catalog\Model\CategoryFactory;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\DB\Adapter\AdapterInterface;
use Magento\Framework\Json\Helper\Data as JsonHelper;
use Magento\ImportExport\Helper\Data as ImportHelper;
use Magento\ImportExport\Model\Import;
use Magento\ImportExport\Model\Import\Entity\AbstractEntity;
use Magento\ImportExport\Model\Import\ErrorProcessing\ProcessingErrorAggregatorInterface;
use Magento\ImportExport\Model\ResourceModel\Helper;
use Magento\ImportExport\Model\ResourceModel\Import\Data;

class Category extends AbstractEntity
{
    const ENTITY_CODE = 'import_category';
    const TABLE = 'catalog_category_entity';
    const ENTITY_ID_COLUMN = 'entity_id';

    const STORE_ID = "store_id";
    const PARENT = "parent";
    const IS_ACTIVE = "is_active";
    const INCLUDE_IN_MENU = "include_in_menu";
    const NAME = "name";
    const AVAILABLE_SORT_BY = "available_sort_by";
    const URL_KEY = "url_key";
    const POSITION = "position";
    /**
     * If we should check column names
     */
    protected $needColumnCheck = true;

    /**
     * Need to log in import history
     */
    protected $logInHistory = true;

    /**
     * Permanent entity columns.
     */

    /**
     * Valid column names
     */
    protected $validColumnNames = [
        self::ENTITY_ID_COLUMN,
        self::STORE_ID,
        self::PARENT,
        self::IS_ACTIVE,
        self::INCLUDE_IN_MENU,
        self::NAME,
        self::AVAILABLE_SORT_BY,
        self::URL_KEY,
        self::POSITION,
    ];

    /**
     * @var AdapterInterface
     */
    protected $connection;

    /**
     * @var ResourceConnection
     */
    private $resource;
    protected $objectManager;

    protected $modelCategoryFactory;

    protected $resourceCategory;
    protected $categoryRepository;

    /**
     * Courses constructor.
     *
     * @param JsonHelper $jsonHelper
     * @param ImportHelper $importExportData
     * @param Data $importData
     * @param ResourceConnection $resource
     * @param Helper $resourceHelper
     * @param ProcessingErrorAggregatorInterface $errorAggregator
     */
    public function __construct(
        JsonHelper $jsonHelper,
        ImportHelper $importExportData,
        Data $importData,
        ResourceConnection $resource,
        Helper $resourceHelper,
        ProcessingErrorAggregatorInterface $errorAggregator,
        CategoryFactory $modelCategoryFactory,
        \Magento\Catalog\Model\ResourceModel\Category $resourceCategory,
        CategoryRepositoryInterface $categoryRepository
    ) {
        $this->jsonHelper = $jsonHelper;
        $this->_importExportData = $importExportData;
        $this->_resourceHelper = $resourceHelper;
        $this->_dataSourceModel = $importData;
        $this->resource = $resource;
        $this->connection = $resource->getConnection(ResourceConnection::DEFAULT_CONNECTION);
        $this->errorAggregator = $errorAggregator;
        $this->modelCategoryFactory = $modelCategoryFactory;
        $this->initMessageTemplates();
        $this->resourceCategory = $resourceCategory;
        $this->categoryRepository = $categoryRepository;
    }

    /**
     * Entity type code getter.
     *
     * @return string
     */
    public function getEntityTypeCode()
    {
        return static::ENTITY_CODE;
    }

    /**
     * Get available columns
     *
     * @return array
     */
    public function getValidColumnNames(): array
    {
        return $this->validColumnNames;
    }

    /**
     * Row validation
     *
     * @param array $rowData
     * @param int $rowNum
     *
     * @return bool
     */
    public function validateRow(array $rowData, $rowNum): bool
    {
        $b = $rowNum;
        $a = $rowData;

        $id = (string)$rowData[self::ENTITY_ID_COLUMN];
        $storeId = (string)$rowData[self::STORE_ID];
        $name = (string)$rowData[self::NAME];
        $urlKey = (string)$rowData[self::URL_KEY];
        $parent = (string)$rowData[self::PARENT];
        $isActive = (string)$rowData[self::IS_ACTIVE];

        if ($isActive == '') {
            $this->addRowError('IsActiveIsRequired', $rowNum);
        } elseif ($isActive != "0" && $isActive != "1") {
            $this->addRowError('IsActiveFormat', $rowNum);
        }

        if ($id == '') {
            $this->addRowError('IdIsRequired', $rowNum);
        } elseif (!preg_match('/^[0-9]+$/', $id)) {
            $this->addRowError('IdIsNumber', $rowNum);
            //The id must be a number
        }

        if ($storeId == '') {
            $this->addRowError('storeIdIsRequired', $rowNum);
        } elseif (!preg_match('/^[0-9]+$/', $storeId)) {
            $this->addRowError('StoreIdIsNumber', $rowNum);
            //The store id must be a number
        }
        if ($name == '') {
            $this->addRowError('nameIsRequired', $rowNum);
        }
        if ($urlKey == '') {
            $this->addRowError('urlKeyIsRequired', $rowNum);
        }
        if ($parent == '') {
            $this->addRowError('parentIsRequired', $rowNum);
        } elseif (!preg_match('/^[0-9]+$/', $parent)) {
            $this->addRowError('ParentIsNumber', $rowNum);
            //The parent must be a number
        }

        if (isset($this->_validatedRows[$rowNum])) {
            return !$this->getErrorAggregator()->isRowInvalid($rowNum);
        }

        $this->_validatedRows[$rowNum] = true;
        return !$this->getErrorAggregator()->isRowInvalid($rowNum);
    }

    /**
     * Init Error Messages
     */
    private function initMessageTemplates()
    {
        $this->addMessageTemplate(
            'IdIsRequired',
            __('The entity_id cannot be empty.')
        );
        $this->addMessageTemplate(
            'storeIdIsRequired',
            __('The store_id cannot be empty.')
        );
        $this->addMessageTemplate(
            'nameIsRequired',
            __('The name cannot be empty.')
        );
        $this->addMessageTemplate(
            'urlKeyIsRequired',
            __('The url_key cannot be empty.')
        );
        $this->addMessageTemplate(
            'parentIsRequired',
            __('The parent cannot be empty.')
        );
        $this->addMessageTemplate(
            'IdIsNumber',
            __('The entity_id must be a number')
        );
        $this->addMessageTemplate(
            'StoreIdIsNumber',
            __('The store_id must be a number')
        );
        $this->addMessageTemplate(
            'ParentIsNumber',
            __('The parent must be a number')
        );
        $this->addMessageTemplate(
            'IsActiveIsRequired',
            __('The is_active cannot be empty.')
        );
        $this->addMessageTemplate(
            'IsActiveFormat',
            __('The is_active Wrong format [Format 0 or 1]')
        );
    }

    /**
     * Import data
     *
     * @return bool
     *
     * @throws Exception
     */
    protected function _importData(): bool
    {
        switch ($this->getBehavior()) {
            case Import::BEHAVIOR_DELETE:
                $this->deleteEntity();
                break;
            case Import::BEHAVIOR_REPLACE:
                $this->saveAndReplaceEntity();
                break;
            case Import::BEHAVIOR_APPEND:
                $this->saveAndReplaceEntity();
                break;
        }
        return true;
    }

    /**
     * Delete entities
     *
     * @return bool
     */
    private function deleteEntity(): bool
    {
        $rows = [];
        while ($bunch = $this->_dataSourceModel->getNextBunch()) {
            foreach ($bunch as $rowNum => $rowData) {
                $this->validateRow($rowData, $rowNum);

                if (!$this->getErrorAggregator()->isRowInvalid($rowNum)) {
                    $rowId = $rowData[static::ENTITY_ID_COLUMN];
                    $rows[] = $rowId;
                }

                if ($this->getErrorAggregator()->hasToBeTerminated()) {
                    $this->getErrorAggregator()->addRowToSkip($rowNum);
                }
                $this->Delete($rows);
            }
        }

        if ($rows) {
            return $this->deleteEntityFinish(array_unique($rows));
        }

        return false;
    }

    protected function Delete($rows)
    {
        $modelCategory = $this->modelCategoryFactory->create();
        $countDelete = 0;
        $datas = $modelCategory->getCollection()->addFieldToFilter('entity_id', ['neq' => 1])->addFieldToFilter('entity_id', ['neq' => 2])->getData();
        foreach ($datas as $data) {
            $modelCategory->load($data['entity_id']);
            if (array_search($modelCategory->getId(), $rows) === true) {
                $countDelete++;
                $modelCategory->delete();
            }
        }
        $this->countItemsDeleted = (int)$countDelete;
    }

    /**
     * Save and replace entities
     *
     * @return void
     */
    private function saveAndReplaceEntity()
    {
        $behavior = $this->getBehavior();
        $rows = [];
        while ($bunch = $this->_dataSourceModel->getNextBunch()) {
            $entityList = [];

            foreach ($bunch as $rowNum => $row) {
                if (!$this->validateRow($row, $rowNum)) {
                    continue;
                }

                if ($this->getErrorAggregator()->hasToBeTerminated()) {
                    $this->getErrorAggregator()->addRowToSkip($rowNum);

                    continue;
                }

                if(!$this->getErrorAggregator()->isRowInvalid($rowNum))
                {
                    $a = 1;
                }

                $rowId = $row[static::ENTITY_ID_COLUMN];
                $rows[] = $rowId;
                $columnValues = [];

                foreach ($this->getAvailableColumns() as $columnKey) {
                    $columnValues[$columnKey] = $row[$columnKey];
                }

                $entityList[$rowId][] = $columnValues;
            }

            if (Import::BEHAVIOR_REPLACE === $behavior) {
                if ($rows && $this->deleteEntityFinish(array_unique($rows))) {
                    $this->saveEntityFinish($entityList, true);
                }
            } elseif (Import::BEHAVIOR_APPEND === $behavior) {
                $this->saveEntityFinish($entityList);
            }
        }
    }

    /**
     * Save entities
     *
     * @param array $entityData
     *
     * @return bool
     */
    private function saveEntityFinish(array $entityData, $replace = false): bool
    {
        if ($entityData) {
            $tableName = $this->connection->getTableName(static::TABLE);
            $rows = [];

            foreach ($entityData as $entityRows) {
                foreach ($entityRows as $row) {
                    $rows[] = $row;
                }
            }

            if ($rows) {
                if (!$replace) {
                    $this->AddOrUpdate($rows);
                } else {
                    $this->Replace($rows);
                }
                return true;
            }

            return false;
        }
    }

    private function Replace($rows)
    {
        $countDelete = 0;
        $modelCategory = $this->modelCategoryFactory->create();
        $datas = $modelCategory->getCollection()->addFieldToFilter('entity_id', ['neq' => 1])->addFieldToFilter('entity_id', ['neq' => 2])->getData();
        foreach ($datas as $data) {
            $modelCategory->load($data['entity_id']);
            $modelCategory->delete();
            $countDelete++;
        }
        $this->AddOrUpdate($rows);
        $this->countItemsDeleted = $countDelete;
    }

    private function AddOrUpdate($rows)
    {
        $countCreate = 0;
        $countUpdate = 0;
        foreach ($rows as $row) {
            $modelCategory = $this->modelCategoryFactory->create();
            $add = false;
            if ($modelCategory->load($row['entity_id'])->getId() != null) {
                $modelCategory->load($row['entity_id']);
                $countUpdate++;
            } else {
                $countCreate++;
                $add = true;
            }
            $modelCategory->setStoreId($row['store_id']);
            $modelCategory->setParentId($row['parent']);
            $modelCategory->setIsActive($row['is_active']);
            $modelCategory->setIncludeInMenu($row['include_in_menu']);
            $modelCategory->setName($row['name']);
            $modelCategory->setAvailableSortBy($row['available_sort_by']);
            $modelCategory->setUrlKey($row['url_key']);
            $modelCategory->setPosition('position');
            if($add)
                $this->categoryRepository->save($modelCategory);
            else
                $this->resourceCategory->save($modelCategory);
        }
        $this->countItemsCreated = (int)$countCreate;
        $this->countItemsUpdated = (int)$countUpdate;
    }

    /**
     * Delete entities
     *
     * @param array $entityIds
     *
     * @return bool
     */
    private function deleteEntityFinish(array $entityIds): bool
    {
        if ($entityIds) {
            try {
                $this->countItemsDeleted += $this->connection->delete(
                    $this->connection->getTableName(static::TABLE),
                    $this->connection->quoteInto(static::ENTITY_ID_COLUMN . ' IN (?)', $entityIds)
                );

                return true;
            } catch (Exception $e) {
                return false;
            }
        }

        return false;
    }

    /**
     * Get available columns
     *
     * @return array
     */
    private function getAvailableColumns(): array
    {
        return $this->validColumnNames;
    }
}

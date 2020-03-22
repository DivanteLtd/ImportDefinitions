<?php
/**
 * Data Definitions.
 *
 * LICENSE
 *
 * This source file is subject to the GNU General Public License version 3 (GPLv3)
 * For the full copyright and license information, please view the LICENSE.md and gpl-3.0.txt
 * files that are distributed with this source code.
 *
 * @copyright  Copyright (c) 2016-2019 w-vision AG (https://www.w-vision.ch)
 * @license    https://github.com/w-vision/DataDefinitions/blob/master/gpl-3.0.txt GNU General Public License version 3 (GPLv3)
 */

namespace Wvision\Bundle\DataDefinitionsBundle\Service;

use Pimcore\Model\DataObject;
use Pimcore\Tool;
use Wvision\Bundle\DataDefinitionsBundle\Model\ImportMapping\ToColumn;

class FieldSelection
{
    /** @var array */
    private $activatedLanguages = [];

    /** @var DataObject\ClassDefinition  */
    private $class = null;

    /**
     * @param DataObject\ClassDefinition $class
     * @return array
     * @throws \Exception
     */
    public function getClassDefinition(DataObject\ClassDefinition $class): array
    {
        $this->class = $class;
        $fields = $this->class->getFieldDefinitions();

        $systemColumns = [
            'o_published',
            'o_key',
            'o_parentId',
            'o_parent',
            'o_type',
        ];

        $fieldsTree = [];

        $this->activatedLanguages = Tool::getValidLanguages();

        foreach ($systemColumns as $sysColumn) {
            $toColumn = new ToColumn();

            $toColumn->setLabel($sysColumn);
            $toColumn->setFieldtype('input');
            $toColumn->setIdentifier($sysColumn);
            $toColumn->setType('systemColumn');
            $toColumn->setGroup('systemColumn');
            $toColumn->setPath($sysColumn);

            $fieldsTree[] = $toColumn;
        }

        foreach ($fields as $field) {
            $fieldsTree[] = $this->processFieldConfiguration($field);
        }

        $results = [];

        // Flatten the multidimensional array
        $traverseTree = function ($array) use (&$traverseTree, &$results) {
            foreach ($array as $item) {
                if ($item instanceof ToColumn) {
                    $results[] = $item;
                } elseif (is_array($item)) {
                    $traverseTree($item);
                }
            }
        };

        $traverseTree($fieldsTree);

        return $results;
    }

    /**
     * Collects fields from class and structured fields assigned to it
     * @param $field
     * @param null $path
     * @return array
     * @throws \Exception
     */
    protected function processFieldConfiguration(DataObject\ClassDefinition\Data $field, $path = null) : array
    {
        $result = [];
        switch (true) {
            case $field instanceof DataObject\ClassDefinition\Data\Localizedfields:
                foreach ($this->activatedLanguages as $language) {
                    $localizedFields = $field->getFieldDefinitions();

                    foreach ($localizedFields as $localizedField) {
                        $result[] = $this->processFieldConfiguration($localizedField, $path . "localizedfield." . strtolower($language) . "~");
                    }
                }
                break;
            case $field instanceof DataObject\ClassDefinition\Data\Objectbricks:
                $list = new DataObject\Objectbrick\Definition\Listing();
                $list = $list->load();

                foreach ($list as $brickDefinition) {
                    if ($brickDefinition instanceof DataObject\Objectbrick\Definition) {
                        $key = $brickDefinition->getKey();
                        $classDefs = $brickDefinition->getClassDefinitions();

                        foreach ($classDefs as $classDef) {
                            if ($classDef['classname'] === $this->class->getName() &&
                                $classDef['fieldname'] === $field->getName()) {
                                $fields = $brickDefinition->getFieldDefinitions();

                                foreach ($fields as $brickField) {
                                    $result[] = $this->processFieldConfiguration($brickField, $path . "objectbrick." . $field->getName() . "." . $key . "~");
                                }

                                break;
                            }
                        }
                    }
                }
                break;
            case $field instanceof DataObject\ClassDefinition\Data\Fieldcollections:
                foreach ($field->getAllowedTypes() as $type) {
                    $definition = DataObject\Fieldcollection\Definition::getByKey($type);

                    $fieldDefinition = $definition->getFieldDefinitions();

                    foreach ($fieldDefinition as $fieldcollectionField) {
                        $result[] = $this->processFieldConfiguration($fieldcollectionField, $path . "fieldcollection." . $type . "~");
                    }
                }
                break;
            case $field instanceof DataObject\ClassDefinition\Data\Classificationstore:
                // We don't need to traverse here recursively as CS cannot be a part of other structure
                $result[] = $this->getClassificationStoreConfiguration($field);
                break;
            default:
                $result[] = $this->getFieldConfiguration($field, $path);
                break;
        }

        return $result;
    }

    /**
     * @param DataObject\ClassDefinition\Data $field
     * @return array
     */
    protected function getClassificationStoreConfiguration(DataObject\ClassDefinition\Data $field) : array
    {
        return $result;
        $list = new DataObject\Classificationstore\GroupConfig\Listing();

        $allowedGroupIds = $field->getAllowedGroupIds();

        if ($allowedGroupIds) {
            $list->setCondition('ID in ('.implode(',', $allowedGroupIds).') AND storeId = ?',
                [$field->getStoreId()]);
        } else {
            $list->setCondition('storeId = ?', [$field->getStoreId()]);
        }

        $list->load();

        $groupConfigList = $list->getList();

        /**
         * @var DataObject\Classificationstore\GroupConfig $config
         */
        foreach ($groupConfigList as $config) {
            foreach ($config->getRelations() as $relation) {
                if ($relation instanceof DataObject\Classificationstore\KeyGroupRelation) {
                    $keyId = $relation->getKeyId();

                    $keyConfig = DataObject\Classificationstore\KeyConfig::getById($keyId);

                    $toColumn = new ToColumn();
                    $toColumn->setGroup(
                        sprintf('classificationstore - %s (%s)', $config->getName(), $config->getId())
                    );
                    $path = sprintf(
                        'classificationstore~%s~%s~%s',
                        $field->getName(),
                        $keyConfig->getId(),
                        $config->getId()
                    );
                    $toColumn->setIdentifier($path);
                    $toColumn->setPath($path);
                    $toColumn->setType('classificationstore');
                    $toColumn->setFieldtype($keyConfig->getType());
                    $toColumn->setSetter('classificationstore');
                    $toColumn->setConfig([
                        'field' => $field->getName(),
                        'keyId' => $keyConfig->getId(),
                        'groupId' => $config->getId(),
                    ]);
                    $toColumn->setLabel($keyConfig->getName());

                    $result[] = $toColumn;
                }
            }
        }

        return $result;
    }


    /**
     * @param DataObject\ClassDefinition\Data $field
     * @return ToColumn
     */
    protected function getFieldConfiguration(DataObject\ClassDefinition\Data $field, ?string $path) : ToColumn
    {
        $toColumn = new ToColumn();

        $toColumn->setLabel($field->getName());
        $toColumn->setFieldtype($field->getFieldtype());

        // To keep BC
        if ($path && count($structures = explode("~", $path)) > 1) {
            $structureName = explode(".", $structures[count($structures)-2])[0];
            switch ($structureName) {
                case "localizedfield":
                    $toColumn->setIdentifier($field->getName() . "~" . explode(".", $structures[count($structures)-2])[1]);
                    break;
                default:
                    $toColumn->setIdentifier($field->getName());
                    break;
            }
        } else {
            $toColumn->setIdentifier($field->getName());
        }

        if ($path) {
            $toColumn->setGroup(implode(".", array_slice(explode("~", $path), 0 , -1)));
        } else {
            $toColumn->setGroup('fields');
        }

        $toColumn->setPath($path . $field->getName());

        return $toColumn;
    }
}



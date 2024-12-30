<?php

namespace brikdigital\entrytypepermissions;

use Craft;
use craft\base\Event;
use craft\base\Plugin;
use craft\elements\Entry;
use craft\events\DefineEntryTypesEvent;
use craft\events\RegisterUserPermissionsEvent;
use craft\fields\Matrix;
use craft\models\EntryType;
use craft\models\Section;
use craft\services\UserPermissions;

class EntryTypePermissions extends Plugin
{
    public static EntryTypePermissions $plugin;
    public string $schemaVersion = "5.0.0";

    public function init()
    {
        parent::init();

        self::$plugin = $this;

        if (Craft::$app->getRequest()->getIsCpRequest()) {
            $this->registerUserPermissions();
            $this->registerEntryTypeFilter();
        }
    }

    public function registerEntryTypeFilter(): void
    {
        Event::on(Entry::class, Entry::EVENT_DEFINE_ENTRY_TYPES, function (DefineEntryTypesEvent $event) {
            $event->entryTypes = $this->_filterEntryTypes($event->entryTypes);
        });

        Event::on(Matrix::class, Matrix::EVENT_DEFINE_ENTRY_TYPES, function (DefineEntryTypesEvent $event) {
            $event->entryTypes = $this->_filterEntryTypes($event->entryTypes);
        });
    }

    public function registerUserPermissions(): void
    {
        Event::on(UserPermissions::class, UserPermissions::EVENT_REGISTER_PERMISSIONS,
            function (RegisterUserPermissionsEvent $event) {
                $types = Craft::$app->entries->getAllEntryTypes();
                $types = array_filter($types, function (EntryType $type) {
                    $usages = array_map(get_class(...), $type->findUsages());
                    $usages = array_filter($usages, function (string $type) {
                        return match($type) {
                            Matrix::class => true,
                            Section::class => true,
                            default => false,
                        };
                    });
                    return count($usages) !== 0;
                });

                $perms = [];
                array_map(function ($entryType) use (&$perms) {
                    $perms["createEntryType:$entryType->uid"] = [
                        "label" => $entryType->name,
                    ];
                }, $types);

                $event->permissions[] = [
                    'heading' => 'Entry Type Permissions',
                    'permissions' => $perms
                ];
            }
        );
    }

    private function _filterEntryTypes(array $entryTypes): array
    {
        return array_values( // reindex the array
            // filter out any entry types we don't have access to
            array_filter($entryTypes, function (EntryType $type) {
                return Craft::$app->user->checkPermission("createEntryType:$type->uid");
            })
        );
    }
}
